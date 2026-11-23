<?php

namespace ThemeIsle\WPFullPay\Webhooks\Events;

use ThemeIsle\WPFullPay\Webhooks\ChargeEventUtils;
use ThemeIsle\WPFullPay\Webhooks\EventProcessor;
use ThemeIsle\WPFullPay\Webhooks\LiveModeAwareEventProcessorContext;

class ChargeRefunded extends EventProcessor
{
	use ChargeEventUtils;

	public function getType()
	{
		return \StripeWPFS\Stripe\Event::CHARGE_REFUNDED;
	}

	protected function processEvent($event, LiveModeAwareEventProcessorContext $context)
	{
		/** @var \StripeWPFS\Stripe\Charge|null $charge */
		$charge = $this->getDataObject($event);
		if (!is_null($charge)) {
			$this->updatePaymentAndReportFromStripe($charge, $context);

			// A refund of a payment recorded later is kept for its report. saveReportRefund() only raises a refund, so
			// this event's amount can't lower the latest charge's.
			if ( is_string( $charge->payment_intent ) && '' !== $charge->payment_intent ) {
				$this->db->saveReportRefund( $charge->payment_intent, (int) $charge->amount_refunded, $this->db->getPaymentStatus( $charge ) );
			}
		}
	}
}
