<?php

namespace ThemeIsle\WPFullPay\Webhooks\Events;

use ThemeIsle\WPFullPay\Webhooks\InvoiceEventProcessor;
use ThemeIsle\WPFullPay\Webhooks\LiveModeAwareEventProcessorContext;

class InvoiceCreated extends InvoiceEventProcessor
{

	public function getType()
	{
		return \StripeWPFS\Stripe\Event::INVOICE_CREATED;
	}

	protected function processEvent($event, LiveModeAwareEventProcessorContext $context)
	{
		$this->processSubscribersOfInvoice($event, $event->data->object->lines->data, function ($wpfsSubscriber) use ($event, $context) {
			$this->updateSubscriberWithInvoiceAndEvent($wpfsSubscriber, $event);
			// Read again for the count just updated. The end is checked on every delivery, so a redelivery
			// retries an end that failed.
			$wpfsSubscriber = $this->findSubscriberByStripeSubscriptionId($wpfsSubscriber->stripeSubscriptionID);

			// An invoice created after the last charge ends the subscription at once.
			if (
				$wpfsSubscriber->chargeMaximumCount > 0
				&& $wpfsSubscriber->chargeCurrentCount >= $wpfsSubscriber->chargeMaximumCount
				&& $wpfsSubscriber->invoiceCreatedCount > $wpfsSubscriber->chargeMaximumCount
			) {
				$this->endSubscription($wpfsSubscriber, $context, $this->getSubscriptionMetadataOfInvoice($event->data->object));
			}
		});
	}
}
