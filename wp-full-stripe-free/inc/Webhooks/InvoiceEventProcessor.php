<?php

namespace ThemeIsle\WPFullPay\Webhooks;

use ThemeIsle\WPFullPay\Main;

abstract class InvoiceEventProcessor extends EventProcessor
{

	const INVOICE_ITEM_TYPE_SUBSCRIPTION = 'subscription';

	protected function findSubscriptionIdInLine($event, $line)
	{
		$stripeSubscriptionId = null;
		$stripeSubscriptionIdSource = null;
		if ($this->isEventApiVersionAtLeast($event, self::STRIPE_API_VERSION_2018_05_21)) {
			if ($this->isSubscriptionLine($line)) {
				$stripeSubscriptionId = $line->parent->subscription_item_details->subscription ?? $line->subscription;
				$stripeSubscriptionIdSource = 'subscription';
			}
		} else {
			$stripeSubscriptionId = $line->id;
			$stripeSubscriptionIdSource = 'id';
		}

		$this->logger->debug(__FUNCTION__, "api_version={$event->api_version}, stripe_subscription_id={$stripeSubscriptionId}, stripe_subscription_id_source={$stripeSubscriptionIdSource}");

		return $stripeSubscriptionId;
	}

	/**
	 * Compares the date part only: suffixed versions like 2025-06-30.basil make strtotime() return false.
	 *
	 * @param \StripeWPFS\Stripe\Event $event
	 * @param string $version Y-m-d API version.
	 */
	private function isEventApiVersionAtLeast(\StripeWPFS\Stripe\Event $event, string $version): bool
	{
		return strcmp(substr((string) $event->api_version, 0, 10), $version) >= 0;
	}

	/**
	 * Basil (2025-03-31) moved the line type and subscription under `parent`.
	 *
	 * @param \StripeWPFS\Stripe\StripeObject $line
	 */
	private function isSubscriptionLine(\StripeWPFS\Stripe\StripeObject $line): bool
	{
		return isset($line->parent->subscription_item_details)
			|| self::INVOICE_ITEM_TYPE_SUBSCRIPTION === ($line->type ?? null);
	}

	/**
	 * Hands the subscriber of each subscription line to $process, unless its subscription has ended or been
	 * cancelled. A line of a subscription not recorded yet records the event on its Checkout submission instead.
	 *
	 * @param \StripeWPFS\Stripe\Event $event
	 * @param iterable<\StripeWPFS\Stripe\InvoiceLineItem> $lines The lines of the event's invoice.
	 * @param callable(object): void $process Receives the subscriber.
	 */
	protected function processSubscribersOfInvoice(\StripeWPFS\Stripe\Event $event, iterable $lines, callable $process): void
	{
		foreach ($lines as $line) {
			$stripeSubscriptionId = $this->findSubscriptionIdInLine($event, $line);
			$this->logger->debug(__FUNCTION__, "stripeSubscriptionId=$stripeSubscriptionId");
			if (is_null($stripeSubscriptionId)) {
				continue;
			}

			$wpfsSubscriber = $this->findSubscriberByStripeSubscriptionId($stripeSubscriptionId);
			if (!isset($wpfsSubscriber)) {
				$this->updatePopupFormSubmitOfLine($event, $line);
			} elseif (Main::SUBSCRIBER_STATUS_ENDED === $wpfsSubscriber->status || Main::SUBSCRIBER_STATUS_CANCELLED === $wpfsSubscriber->status) {
				$this->logger->debug(__FUNCTION__, "subscription status is 'ended' or 'cancelled', skip");
			} else {
				$process($wpfsSubscriber);
			}
		}
	}

	/**
	 * @param \StripeWPFS\Stripe\Event $event
	 * @param \StripeWPFS\Stripe\InvoiceLineItem $line
	 */
	private function updatePopupFormSubmitOfLine(\StripeWPFS\Stripe\Event $event, $line): void
	{
		$submitHash = $this->findSubmitHashInLine($event, $line);
		$this->logger->debug(__FUNCTION__, "subscription not found, submitHash=$submitHash");

		$popupFormSubmit = is_null($submitHash) ? null : $this->findPopupFormSubmitByHash($submitHash);
		if (!is_null($popupFormSubmit)) {
			$this->updatePopupFormSubmitWithEvent($popupFormSubmit, $event);
		}
	}

	/**
	 * @param \StripeWPFS\Stripe\Event $event
	 * @param \StripeWPFS\Stripe\InvoiceLineItem $line
	 *
	 * @return string|null
	 */
	protected function findSubmitHashInLine($event, $line)
	{
		$submitHash = null;
		if ($this->isEventApiVersionAtLeast($event, self::STRIPE_API_VERSION_2018_05_21)) {
			if ($this->isSubscriptionLine($line)) {
				if (isset($line->metadata) && isset($line->metadata->client_reference_id)) {
					$submitHash = $line->metadata->client_reference_id;
				}
			}
		}

		return $submitHash;
	}

	/**
	 * @param $submitHash
	 *
	 * @return array|null|object|void
	 */
	protected function findPopupFormSubmitByHash($submitHash)
	{
		return $this->db->findPopupFormSubmitByHash($submitHash);
	}

	/**
	 * @param $popupFormSubmit
	 * @param \StripeWPFS\Stripe\Event $stripeEvent
	 *
	 * @return bool|int
	 */
	protected function updatePopupFormSubmitWithEvent($popupFormSubmit, \StripeWPFS\Stripe\Event $stripeEvent)
	{
		$hash = $popupFormSubmit->hash;

		// The subscriber counts these events once it's recorded.
		return $this->addEventID(
			$popupFormSubmit->relatedStripeEventIDs ?? '',
			$stripeEvent,
			function ($relatedStripeEventIDs, $readStripeEventIDs) use ($hash, $stripeEvent) {
				$this->logger->debug('updatePopupFormSubmitWithEvent', sprintf('Updating PopupFormSubmit \'%s\' with event ID \'%s\'', $hash, $stripeEvent->id));

				return $this->db->updatePopupFormSubmitWithEvent($hash, $relatedStripeEventIDs, $readStripeEventIDs);
			},
			function () use ($hash) {
				$popupFormSubmit = $this->findPopupFormSubmitByHash($hash);

				return is_null($popupFormSubmit) ? null : ($popupFormSubmit->relatedStripeEventIDs ?? '');
			}
		);
	}

	/**
	 * The snapshot of its subscription's metadata an invoice carries: under parent since API version
	 * 2025-03-31.basil, at the top level before.
	 *
	 * @param object $invoice
	 * @return object|null
	 */
	protected function getSubscriptionMetadataOfInvoice($invoice)
	{
		if (isset($invoice->parent->subscription_details->metadata)) {
			return $invoice->parent->subscription_details->metadata;
		}

		return isset($invoice->subscription_details->metadata) ? $invoice->subscription_details->metadata : null;
	}

	/**
	 * @param $wpfsSubscriber
	 * @param LiveModeAwareEventProcessorContext $context
	 * @param object|array<string,mixed>|null $subscriptionMetadata Metadata of the ended subscription, when the event carries it.
	 *
	 * @throws \Exception
	 */
	protected function endSubscription($wpfsSubscriber, LiveModeAwareEventProcessorContext $context, $subscriptionMetadata = null)
	{
		// Cancelled first, so that it isn't billed again while a Stripe failure, here or in the receipt, fails the
		// delivery before the subscription is ended. The retry finds it cancelled and finishes the rest.
		$stripeSubscription = $context->getStripe()->retrieveSubscription($wpfsSubscriber->stripeSubscriptionID);
		if (
			(!isset($stripeSubscription->status) || \StripeWPFS\Stripe\Subscription::STATUS_CANCELED !== $stripeSubscription->status)
			&& !$context->getStripe()->cancelSubscription($wpfsSubscriber->stripeCustomerID, $wpfsSubscriber->stripeSubscriptionID, false, $stripeSubscription)
		) {
			throw new \Exception("Cannot cancel subscription '{$wpfsSubscriber->stripeSubscriptionID}'.");
		}

		$receipt = $this->createSubscriptionEndedReceipt($wpfsSubscriber, $context, $subscriptionMetadata, $stripeSubscription);
		// Another delivery that ended it meanwhile sends the email.
		if ($this->db->endSubscription($wpfsSubscriber->stripeSubscriptionID)) {
			$this->sendSubscriptionEndedReceipt($receipt);
		}
	}

	/**
	 * Lets the customer keep the period they just paid for: Stripe cancels the
	 * subscription when the current period ends, and the resulting
	 * customer.subscription.deleted event marks the local record as ended.
	 * A subscription Stripe already cancels is left as it is, so a redelivery can retry a scheduling that failed.
	 *
	 * @param object $wpfsSubscriber
	 * @param LiveModeAwareEventProcessorContext $context
	 *
	 * @throws \Exception
	 */
	protected function scheduleSubscriptionEnd(object $wpfsSubscriber, LiveModeAwareEventProcessorContext $context): void
	{
		$stripeSubscription = $context->getStripe()->retrieveSubscription($wpfsSubscriber->stripeSubscriptionID);
		if (
			!empty($stripeSubscription->cancel_at_period_end)
			|| (isset($stripeSubscription->status) && \StripeWPFS\Stripe\Subscription::STATUS_CANCELED === $stripeSubscription->status)
		) {
			$this->logger->debug(__FUNCTION__, "subscription '{$wpfsSubscriber->stripeSubscriptionID}' already ends");

			return;
		}

		$this->logger->debug(__FUNCTION__, "maximum charge count reached, cancelling subscription '{$wpfsSubscriber->stripeSubscriptionID}' at period end");
		$context->getStripe()->cancelSubscription($wpfsSubscriber->stripeCustomerID, $wpfsSubscriber->stripeSubscriptionID, true, $stripeSubscription);
	}

	/**
	 * @param $wpfsSubscriber
	 * @param \StripeWPFS\Stripe\Event $stripeEvent
	 *
	 * @return bool|int
	 */
	protected function updateSubscriberWithPaymentAndEvent($wpfsSubscriber, \StripeWPFS\Stripe\Event $stripeEvent)
	{
		return $this->addEventToSubscriber(
			$wpfsSubscriber,
			$stripeEvent,
			function ($processedStripeEventIDs, $readStripeEventIDs) use ($wpfsSubscriber, $stripeEvent) {
				return $this->db->updateSubscriberWithPaymentAndEvent($wpfsSubscriber->stripeSubscriptionID, $processedStripeEventIDs, $stripeEvent->id, $readStripeEventIDs);
			}
		);
	}

	/**
	 * @param $wpfsSubscriber
	 * @param \StripeWPFS\Stripe\Event $stripeEvent
	 *
	 * @return bool|int
	 */
	protected function updateSubscriberWithInvoiceAndEvent($wpfsSubscriber, \StripeWPFS\Stripe\Event $stripeEvent)
	{
		return $this->addEventToSubscriber(
			$wpfsSubscriber,
			$stripeEvent,
			function ($processedStripeEventIDs, $readStripeEventIDs) use ($wpfsSubscriber, $stripeEvent) {
				return $this->db->updateSubscriberWithInvoiceAndEvent($wpfsSubscriber->stripeSubscriptionID, $processedStripeEventIDs, $stripeEvent->id, $readStripeEventIDs);
			}
		);
	}

}
