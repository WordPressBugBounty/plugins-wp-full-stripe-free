<?php

namespace ThemeIsle\WPFullPay\Webhooks\Events;

use ThemeIsle\WPFullPay\Webhooks\EventProcessor;
use ThemeIsle\WPFullPay\Webhooks\LiveModeAwareEventProcessorContext;

class CustomerSubscriptionUpdated extends EventProcessor
{

	public function getType()
	{
		return \StripeWPFS\Stripe\Event::CUSTOMER_SUBSCRIPTION_UPDATED;
	}

	protected function processEvent($event, LiveModeAwareEventProcessorContext $context)
	{
		$previousAttributes = $this->getDataPreviousAttributes($event);
		if (!is_null($previousAttributes)) {
			/** @var \StripeWPFS\Stripe\Subscription $stripeSubscription */
			$stripeSubscription = $this->getDataObject($event);
			if (!is_null($stripeSubscription)) {
				$wpfsSubscriber = $this->findSubscriberByStripeSubscriptionId($stripeSubscription->id);
				if (isset($wpfsSubscriber)) {
					if ($previousAttributes->offsetExists('quantity')) {
						$this->db->updateSubscriber(
							$wpfsSubscriber->subscriberID,
							['quantity' => $stripeSubscription->quantity]
						);
					}
				}
			}
		}
	}

}
