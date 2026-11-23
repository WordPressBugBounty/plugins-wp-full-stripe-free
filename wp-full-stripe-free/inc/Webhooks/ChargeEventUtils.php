<?php

namespace ThemeIsle\WPFullPay\Webhooks;

trait ChargeEventUtils
{
	/**
	 * @param \StripeWPFS\Stripe\PaymentIntent|string|null $paymentIntentId The charge's unexpanded payment_intent.
	 */
	protected function isDonationPayment($paymentIntentId): bool
	{
		return !is_null($this->db->getDonationByPaymentIntentId($paymentIntentId));
	}

	/**
	 * Copies the state of the payment the event's charge belongs to onto its donation or payment, from Stripe:
	 * events arrive out of order, and Stripe makes a charge per attempt, so a failed attempt's charge stays failed
	 * after a later one succeeded. The payment's state is its PaymentIntent's latest charge.
	 *
	 * @param \StripeWPFS\Stripe\Charge $charge The event's charge.
	 * @param array{expired?: true, failureCode?: string|null, failureMessage?: string|null} $fields More columns the
	 *        event sets, named as in the donations table, set only while its charge is the payment's latest.
	 * @param (callable(\StripeWPFS\Stripe\Charge|\stdClass): void)|null $alsoWithLatestCharge Run with the latest
	 *        charge while the payment is still held, for more the event writes from it.
	 *
	 * @return \StripeWPFS\Stripe\Charge|\stdClass|null The latest charge, null when the plugin recorded no payment or
	 *         donation for it.
	 *
	 * @throws \Exception When another delivery holds the payment for too long, so that Stripe sends the event again.
	 */
	protected function updatePaymentStatusFromStripe($charge, LiveModeAwareEventProcessorContext $context, array $fields = [], ?callable $alsoWithLatestCharge = null)
	{
		if (
			empty($charge->payment_intent)
			|| (!$this->isDonationPayment($charge->payment_intent) && is_null($this->db->getPaymentByEventId($charge->payment_intent)))
		) {
			return null;
		}

		$latestCharge = $charge;
		$this->updateFromLatestCharge(
			$charge->payment_intent,
			$context,
			function ($stripeLatestCharge) use ($charge, $fields, $alsoWithLatestCharge, &$latestCharge) {
				$latestCharge = $stripeLatestCharge ?? $charge;
				$this->updatePaymentStatus($latestCharge, $latestCharge->id === $charge->id ? $fields : []);
				if (!is_null($alsoWithLatestCharge)) {
					$alsoWithLatestCharge($latestCharge);
				}
			}
		);

		return $latestCharge;
	}

	/**
	 * Copies the payment's state onto its donation or payment and its report, as updatePaymentStatusFromStripe()
	 * does. A subscription invoice's payment has a report only.
	 *
	 * @param \StripeWPFS\Stripe\Charge $charge The event's charge.
	 *
	 * @throws \Exception When another delivery holds the payment for too long, so that Stripe sends the event again.
	 */
	protected function updatePaymentAndReportFromStripe($charge, LiveModeAwareEventProcessorContext $context): void
	{
		// The report is written from the same latest charge, before another delivery for the payment can write it.
		$updateReport = function ($latestCharge) use ($charge) {
			$this->storeReportRefund($charge->payment_intent, $latestCharge);
		};
		if (
			is_null($this->updatePaymentStatusFromStripe($charge, $context, [], $updateReport))
			&& !empty($charge->payment_intent)
			&& !is_null($this->db->getReportByPaymentIntentID($charge->payment_intent))
		) {
			$this->updateFromLatestCharge($charge->payment_intent, $context, function ($latestCharge) use ($charge, $updateReport) {
				$updateReport($latestCharge ?? $charge);
			});
		}
	}

	/**
	 * Stores the charge's refund on the payment's report, which keeps it for a report saved later; a refund there
	 * only rises.
	 *
	 * @param string $paymentIntentId
	 * @param \StripeWPFS\Stripe\Charge|\stdClass $charge
	 */
	protected function storeReportRefund($paymentIntentId, $charge): void
	{
		if (isset($charge->amount_refunded) && $charge->amount_refunded > 0) {
			$this->db->saveReportRefund($paymentIntentId, (int) $charge->amount_refunded, $this->db->getPaymentStatus($charge));
		}
	}

	/**
	 * Copies the charge's state onto its donation, or onto its payment when there is no donation. A refunded one stays
	 * refunded.
	 *
	 * @param \StripeWPFS\Stripe\Charge|object $charge
	 * @param array{expired?: true, failureCode?: string|null, failureMessage?: string|null} $fields More columns to set, named as in the donations table.
	 */
	private function updatePaymentStatus($charge, array $fields = []): void
	{
		$fields = array_merge(
			// Only set flags are copied: a request can record the payment from a charge it read before, and Stripe
			// doesn't clear them, except refunded when a refund fails, which isn't handled.
			array_filter([
				'paid' => $charge->paid,
				'captured' => $charge->captured,
				'refunded' => $charge->refunded
			]),
			['lastChargeStatus' => $charge->status],
			$fields
		);

		if ($this->isDonationPayment($charge->payment_intent)) {
			$this->db->updateDonationByPaymentIntentId($charge->payment_intent, $fields);
		} else {
			// The payments table names these columns in snake case.
			$paymentColumns = [
				'lastChargeStatus' => 'last_charge_status',
				'failureCode' => 'failure_code',
				'failureMessage' => 'failure_message'
			];
			$data = [];
			foreach ($fields as $column => $value) {
				$data[$paymentColumns[$column] ?? $column] = $value;
			}
			$this->db->updatePaymentByEventId($charge->payment_intent, $data);
		}
	}
}
