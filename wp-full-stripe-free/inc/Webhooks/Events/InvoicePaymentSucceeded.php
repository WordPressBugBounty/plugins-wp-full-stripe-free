<?php

namespace ThemeIsle\WPFullPay\Webhooks\Events;

use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Utils;
use ThemeIsle\WPFullPay\Webhooks\InvoiceEventProcessor;
use ThemeIsle\WPFullPay\Webhooks\LiveModeAwareEventProcessorContext;

class InvoicePaymentSucceeded extends InvoiceEventProcessor
{

	public function getType()
	{
		return \StripeWPFS\Stripe\Event::INVOICE_PAYMENT_SUCCEEDED;
	}

	/**
	 * @param string $stripeSubscriptionId
	 */
	private function isSubscriptionPaidUp($stripeSubscriptionId, LiveModeAwareEventProcessorContext $context): bool
	{
		$subscription = $context->getStripe()->retrieveSubscription($stripeSubscriptionId);

		return in_array(
			$subscription->status,
			[\StripeWPFS\Stripe\Subscription::STATUS_ACTIVE, \StripeWPFS\Stripe\Subscription::STATUS_TRIALING],
			true
		);
	}

	protected function processEvent($event, LiveModeAwareEventProcessorContext $context)
	{
		$this->processSubscribersOfInvoice($event, $event->data->object->lines->data, function ($wpfsSubscriber) use ($event, $context) {
			// Paid at last, as after a first bank debit failed: Stripe kept billing it. Stripe can also deliver an
			// older invoice's payment again while the subscription is past due.
			if (Main::SUBSCRIBER_STATUS_INCOMPLETE === $wpfsSubscriber->status && $this->isSubscriptionPaidUp($wpfsSubscriber->stripeSubscriptionID, $context)) {
				$this->db->updateIncompleteSubscriptionToRunning($wpfsSubscriber->stripeSubscriptionID);
			}
			if ($this->updateSubscriberWithPaymentAndEvent($wpfsSubscriber, $event)) {
				$wpfsSubscriber = $this->findSubscriberByStripeSubscriptionId($wpfsSubscriber->stripeSubscriptionID);
			} else {
				// Still scheduled below, in case Stripe failed the delivery that counted the payment.
				$this->logger->debug('processEvent', 'event already processed');
			}

			// The payment that reaches the limit lets the customer keep the period it paid for.
			if ($wpfsSubscriber->chargeMaximumCount > 0 && $wpfsSubscriber->chargeCurrentCount >= $wpfsSubscriber->chargeMaximumCount) {
				$this->scheduleSubscriptionEnd($wpfsSubscriber, $context);
			}
		});

		$this->addRecord( $event, $context );
	}

	protected function addRecord( $event, LiveModeAwareEventProcessorContext $context ) {
		$event  = $event->data->object;

		if ( 'paid' !== $event->status ) {
			return;
		}

		$stripeSubscriptionId = $event->parent->subscription_details->subscription ?? $event->subscription ?? null;

		// We aren't sure if its a donation or subscription, so we must check both.
		$data = $this->db->getDonationByStripeSubscriptionId( $stripeSubscriptionId );

		if ( ! $data ) {
			$data = $this->db->getSubscriptionByStripeSubscriptionId( $stripeSubscriptionId );

			if ( ! $data ) {
				return;
			}

			$form = $this->getSubscriptionFormBySubscriber( $data );
			$data->formType = Utils::getFormType( $form );
		}

		$paymentIntentId = $this->findPaymentIntentIdInInvoice( $event, $context );

		if ( is_null( $paymentIntentId ) ) {
			return;
		}

		// Stripe sends the event again, and late, also once the payment was refunded, and a refund processed before
		// the report existed found no row: the report gets the latest charge's refund, written while holding the payment.
		$this->updateFromLatestCharge(
			$paymentIntentId,
			$context,
			function ( $latestCharge ) use ( $event, $data, $paymentIntentId, $stripeSubscriptionId ) {
				$this->db->saveSucceededReport(
					[
						'currency'              => $event->currency ?? null,
						'amount'                => $event->amount_paid ?? null,
						'formId'                => $data->formId ?? null,
						'formType'              => $data->formType ?? null,
						'stripePaymentIntentID' => $paymentIntentId,
						'stripeCustomerID'      => $event->customer ?? null,
						'stripeSubscriptionID'  => $stripeSubscriptionId,
					],
					$event
				);
				if ( ! empty( $latestCharge->amount_refunded ) ) {
					$this->db->updateReportRefund( $paymentIntentId, (int) $latestCharge->amount_refunded, $this->db->getPaymentStatus( $latestCharge ) );
				}
			}
		);
	}

	/**
	 * Basil invoices no longer carry payment_intent; it is only reachable through the expandable payments list.
	 *
	 * @param \StripeWPFS\Stripe\StripeObject $invoice
	 * @param LiveModeAwareEventProcessorContext $context
	 */
	private function findPaymentIntentIdInInvoice( \StripeWPFS\Stripe\StripeObject $invoice, LiveModeAwareEventProcessorContext $context ): ?string {
		if ( $invoice->offsetExists( 'payment_intent' ) ) {
			return $invoice['payment_intent'];
		}

		try {
			$invoice = $context->getStripe()->retrieveInvoiceWithParams( $invoice->id, [ 'expand' => [ 'payments' ] ] );
		} catch ( \Exception $ex ) {
			$this->logger->error( __FUNCTION__, 'Cannot retrieve invoice payments', $ex );

			return null;
		}

		$payments = $invoice->payments->data ?? [];
		// The expanded list is only its first page, so a later paid entry must be fetched.
		if ( ! empty( $invoice->payments->has_more ) ) {
			$payments = array_merge( $this->listPaidInvoicePayments( $invoice->id, $context ), $payments );
		}

		// Canceled and open attempts stay listed, so prefer the paid one over the default.
		$defaultPaymentIntentId = null;
		foreach ( $payments as $payment ) {
			if ( 'payment_intent' !== ( $payment->payment->type ?? null ) || ! isset( $payment->payment->payment_intent ) ) {
				continue;
			}
			if ( 'paid' === ( $payment->status ?? null ) ) {
				return $payment->payment->payment_intent;
			}
			if ( ! empty( $payment->is_default ) ) {
				$defaultPaymentIntentId = $payment->payment->payment_intent;
			}
		}

		return $defaultPaymentIntentId;
	}

	/**
	 * @return list<object> Paid InvoicePayments, or none when the list cannot be fetched.
	 */
	private function listPaidInvoicePayments( string $invoiceId, LiveModeAwareEventProcessorContext $context ): array {
		try {
			return $context->getStripe()->listPaidInvoicePayments( $invoiceId )->data ?? [];
		} catch ( \Exception $ex ) {
			$this->logger->error( __FUNCTION__, 'Cannot list paid invoice payments', $ex );

			return [];
		}
	}
}
