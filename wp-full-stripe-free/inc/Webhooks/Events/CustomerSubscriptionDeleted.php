<?php

namespace ThemeIsle\WPFullPay\Webhooks\Events;

use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Webhooks\EventProcessor;
use ThemeIsle\WPFullPay\Webhooks\LiveModeAwareEventProcessorContext;

class CustomerSubscriptionDeleted extends EventProcessor
{

	public function getType()
	{
		return \StripeWPFS\Stripe\Event::CUSTOMER_SUBSCRIPTION_DELETED;
	}

	protected function isDonationSubscription($subscriptionId)
	{
		$isDonation = false;

		$donationRecord = $this->db->getDonationByStripeSubscriptionId($subscriptionId);
		if (!is_null($donationRecord)) {
			$isDonation = true;
		}

		if (!$isDonation) {
			$subscriptionRecord = $this->db->getSubscriptionByStripeSubscriptionId($subscriptionId);
			if (is_null($subscriptionRecord)) {
				$this->logger->error(__FUNCTION__, "Cannot find subscription record with id '{$subscriptionId}'.");
			}
		}

		return $isDonation;
	}

	protected function updateSubscriptionToCancelled($event, $stripeSubscription, LiveModeAwareEventProcessorContext $context)
	{
		$wpfsSubscriber = $this->findSubscriberByStripeSubscriptionId($stripeSubscription->id);

		if (isset($wpfsSubscriber)) {
			if (
				Main::SUBSCRIPTION_STATUS_ENDED !== $wpfsSubscriber->status &&
				Main::SUBSCRIPTION_STATUS_CANCELLED !== $wpfsSubscriber->status
			) {
				$this->updateSubscriberWithEvent($wpfsSubscriber, $event);
				$wpfsSubscriber = $this->findSubscriberByStripeSubscriptionId($stripeSubscription->id);

				if ($wpfsSubscriber->chargeMaximumCount > 0 && $wpfsSubscriber->chargeCurrentCount >= $wpfsSubscriber->chargeMaximumCount) {
					$this->manageEndedSubscription($wpfsSubscriber, $stripeSubscription, $context);
				} else {
					$this->db->cancelSubscriptionByStripeSubscriptionId($stripeSubscription->id);
				}

				do_action(Main::ACTION_NAME_AFTER_SUBSCRIPTION_CANCELLATION, $stripeSubscription->id);
			}
		}
	}

	protected function updateDonationToCancelled($stripeSubscription)
	{
		$wpfsDonation = $this->db->getDonationByStripeSubscriptionId($stripeSubscription->id);

		if (isset($wpfsDonation)) {
			if (Main::SUBSCRIPTION_STATUS_CANCELLED !== $wpfsDonation->subscriptionStatus) {
				$this->db->cancelDonationByStripeSubscriptionId($stripeSubscription->id);
				do_action(Main::ACTION_NAME_AFTER_SUBSCRIPTION_CANCELLATION, $stripeSubscription->id);
			}
		}
	}

	protected function processEvent($event, LiveModeAwareEventProcessorContext $context)
	{
		$stripeSubscription = $this->getDataObject($event);

		if (!is_null($stripeSubscription)) {
			if ($this->isDonationSubscription($stripeSubscription->id)) {
				$this->updateDonationToCancelled($stripeSubscription);
			} else {
				$this->updateSubscriptionToCancelled($event, $stripeSubscription, $context);
			}
		}
	}

	private function updateSubscriberWithEvent($wpfsSubscriber, \StripeWPFS\Stripe\Event $stripeEvent)
	{
		return $this->addEventToSubscriber(
			$wpfsSubscriber,
			$stripeEvent,
			function ($processedStripeEventIDs, $readStripeEventIDs) use ($wpfsSubscriber) {
				return $this->db->updateSubscriberWithEvent($wpfsSubscriber->stripeSubscriptionID, $processedStripeEventIDs, $readStripeEventIDs);
			}
		);
	}

	/**
	 * @param $wpfsSubscriber
	 * @param \StripeWPFS\Stripe\Subscription $stripeSubscription
	 */
	protected function manageEndedSubscription($wpfsSubscriber, $stripeSubscription, LiveModeAwareEventProcessorContext $context)
	{
		$receipt = $this->createSubscriptionEndedReceipt($wpfsSubscriber, $context, isset($stripeSubscription->metadata) ? $stripeSubscription->metadata : null, $stripeSubscription);
		// Another delivery that ended it meanwhile sends the email.
		if ($this->db->endSubscription($stripeSubscription->id)) {
			$this->sendSubscriptionEndedReceipt($receipt);
		}
	}

}
