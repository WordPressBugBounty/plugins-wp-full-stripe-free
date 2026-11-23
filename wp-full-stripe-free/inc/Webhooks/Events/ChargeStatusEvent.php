<?php

namespace ThemeIsle\WPFullPay\Webhooks\Events;

use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Mailer\Mailer;
use ThemeIsle\WPFullPay\Webhooks\ChargeEventUtils;
use ThemeIsle\WPFullPay\Webhooks\EventProcessor;
use ThemeIsle\WPFullPay\Webhooks\LiveModeAwareEventProcessorContext;

/**
 * Copies a charge's state onto its payment or donation for the charge events that only do that:
 * captured, expired, failed, pending and updated. A failed first payment also marks its subscription incomplete.
 */
class ChargeStatusEvent extends EventProcessor
{
	use ChargeEventUtils;

	/** @var string */
	private $type;

	/**
	 * @param string $type A \StripeWPFS\Stripe\Event::CHARGE_* event type.
	 */
	public function __construct(string $type, Database $db, Mailer $mailer, LoggerService $loggerService)
	{
		parent::__construct($db, $mailer, $loggerService);
		$this->type = $type;
	}

	public function getType(): string
	{
		return $this->type;
	}

	/**
	 * @param \StripeWPFS\Stripe\Event $event
	 */
	protected function processEvent($event, LiveModeAwareEventProcessorContext $context): void
	{
		/** @var \StripeWPFS\Stripe\Charge|null $charge */
		$charge = $this->getDataObject($event);
		if (!is_null($charge)) {
			$this->updatePaymentStatusFromStripe($charge, $context, $this->getEventFields($charge));
			if (\StripeWPFS\Stripe\Event::CHARGE_FAILED === $this->type && !empty($charge->payment_intent)) {
				// Held as the completion that records the subscription and sets it running holds it: either this finds the
				// subscriber running, or the completion finds the charge failed.
				$this->whileHoldingPayment($charge->payment_intent, function () use ($charge, $context) {
					$this->markSubscriberWithFailedFirstPaymentIncomplete($charge, $context);
				});
			}
		}
	}

	/**
	 * A subscription paid by a bank debit runs while the debit processes; Stripe sends no other event the merchant
	 * listens to when the debit fails days later.
	 *
	 * @param \StripeWPFS\Stripe\Charge $charge
	 */
	private function markSubscriberWithFailedFirstPaymentIncomplete($charge, LiveModeAwareEventProcessorContext $context): void
	{
		$wpfsSubscriber = empty($charge->payment_intent) ? null : $this->db->findSubscriberByPaymentIntentId($charge->payment_intent);
		if (is_null($wpfsSubscriber)) {
			return;
		}

		// The event can arrive after the invoice was paid since, by another attempt or another way: Stripe has the
		// subscription active again.
		if (!$this->isSubscriptionUnpaid($wpfsSubscriber->stripeSubscriptionID, $context)) {
			return;
		}

		$this->db->updateRunningSubscriptionByPaymentIntentToIncomplete($charge->payment_intent);

		// Webhooks run in parallel: the invoice can have been paid, and its webhook found the row still running,
		// since Stripe was asked.
		if (!$this->isSubscriptionUnpaid($wpfsSubscriber->stripeSubscriptionID, $context)) {
			$this->db->updateIncompleteSubscriptionToRunning($wpfsSubscriber->stripeSubscriptionID);
		}
	}

	/**
	 * Asks Stripe each time it's called.
	 *
	 * @param string $stripeSubscriptionId
	 *
	 * @phpstan-impure
	 */
	private function isSubscriptionUnpaid($stripeSubscriptionId, LiveModeAwareEventProcessorContext $context): bool
	{
		$subscription = $context->getStripe()->retrieveSubscription($stripeSubscriptionId);

		return in_array(
			$subscription->status,
			[
				\StripeWPFS\Stripe\Subscription::STATUS_PAST_DUE,
				\StripeWPFS\Stripe\Subscription::STATUS_UNPAID,
				\StripeWPFS\Stripe\Subscription::STATUS_INCOMPLETE,
			],
			true
		);
	}

	/**
	 * @param \StripeWPFS\Stripe\Charge $charge
	 *
	 * @return array{expired?: true, failureCode?: string|null, failureMessage?: string|null} Columns this event sets besides the charge state.
	 */
	private function getEventFields($charge): array
	{
		switch ($this->type) {
			case \StripeWPFS\Stripe\Event::CHARGE_EXPIRED:
				return ['expired' => true];
			case \StripeWPFS\Stripe\Event::CHARGE_FAILED:
				return ['failureCode' => $charge->failure_code, 'failureMessage' => $charge->failure_message];
			default:
				return [];
		}
	}
}
