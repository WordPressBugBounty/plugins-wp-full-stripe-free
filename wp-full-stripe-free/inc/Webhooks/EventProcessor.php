<?php

namespace ThemeIsle\WPFullPay\Webhooks;

use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Exceptions\PlatformRequestException;
use ThemeIsle\WPFullPay\Forms\Models\SubscriptionFormModel;
use ThemeIsle\WPFullPay\Locale\Countries;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Mailer\Mailer;
use ThemeIsle\WPFullPay\Payments\LatestCharge;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Pricing\Pricing;
use ThemeIsle\WPFullPay\Pricing\RecoveryFee;
use ThemeIsle\WPFullPay\TransactionData\SubscriptionTransactionData;
use ThemeIsle\WPFullPay\Utils;

abstract class EventProcessor
{
	use LoggerAddOn;

	const STRIPE_API_VERSION_2018_02_28 = '2018-02-28';
	const STRIPE_API_VERSION_2018_05_21 = '2018-05-21';

	/** The most invoices Stripe lists at once. */
	const INVOICE_PAGE_SIZE = 100;

	/** How often an event is added to a subscriber whose event IDs other deliveries keep changing meanwhile. */
	const MAX_EVENT_ID_WRITE_ATTEMPTS = 5;

	/* @var $db Database */
	protected $db = null;
	/* @var $mailer Mailer */
	protected $mailer = null;

	/**
	 * EventProcessor constructor.
	 *
	 * @param Database $db
	 * @param Mailer $mailer
	 * @param LoggerService $loggerService
	 */
	public function __construct(Database $db, Mailer $mailer, LoggerService $loggerService)
	{
		$this->initLogger($loggerService, LoggerService::MODULE_WEBHOOK_EVENT_HANDLER);

		$this->db = $db;
		$this->mailer = $mailer;
	}

	public final function process($eventObject, LiveModeAwareEventProcessorContext $context)
	{
		if ($this->getType() === $eventObject->type) {
			$event = null;
			try {
				$event = $context->getStripe()->retrieveEvent($eventObject->id);
			} catch (\Exception $ex) {
				// We want to make sure that the event exists
				$this->logger->error(__FUNCTION__, 'Cannot retrieve event', $ex);

				// The platform couldn't answer or refused this site's credential: fail the webhook so Stripe
				// sends it again, instead of acknowledging an event that was never processed.
				if ($ex instanceof PlatformRequestException && ($ex->isAmbiguous() || $ex->isCredentialRefusal())) {
					throw $ex;
				}
			}

			if (!is_null($event)) {
				$event = \StripeWPFS\Stripe\Event::constructFrom(json_decode(json_encode($event), true));
				$this->processEvent($event, $context);
			}
		}
	}

	public abstract function getType();

	/**
	 * @param $event
	 * @param LiveModeAwareEventProcessorContext $context
	 */
	protected function processEvent($event, LiveModeAwareEventProcessorContext $context)
	{
		// tnagy default implementation, override in subclasses
	}

	/**
	 * @param $event
	 *
	 * @return null|\StripeWPFS\Stripe\ApiResource
	 */
	protected function getDataObject($event)
	{
		$object = null;
		if (isset($event) && isset($event->data) && isset($event->data->object)) {
			$object = $event->data->object;
		}

		return $object;
	}

	/**
	 * Upper bound for the processed-event-ID list kept per subscriber. Stripe retries a
	 * failed delivery for at most 72 hours, so only recent IDs matter for replay protection.
	 */
	const MAX_PROCESSED_EVENT_IDS = 100;

	/**
	 * Adds an event ID to a JSON encoded array if the ID is not in the array
	 *
	 * @param string|null $encodedStripeEventIDs JSON encoded event ID array
	 * @param object|null $stripeEvent
	 * @param bool $success output variable to determine whether the event ID has been added to the array
	 *
	 * @return string|null the new JSON encoded array
	 */
	protected function insertIfNotExists(?string $encodedStripeEventIDs, ?object $stripeEvent, bool &$success): ?string
	{
		$decodedStripeEventIDs = [];
		if (isset($encodedStripeEventIDs)) {
			$decodedStripeEventIDs = json_decode($encodedStripeEventIDs);
			if (json_last_error() !== JSON_ERROR_NONE) {
				$decodedStripeEventIDs = [];
			}
			if (!is_array($decodedStripeEventIDs)) {
				$decodedStripeEventIDs = [];
			}
		}
		if (isset($stripeEvent) && isset($stripeEvent->id)) {
			if (in_array($stripeEvent->id, $decodedStripeEventIDs)) {
				$data = $encodedStripeEventIDs;
				$success = false;
			} else {
				array_push($decodedStripeEventIDs, $stripeEvent->id);
				// Cap the list so the TEXT column stays bounded for long-running subscriptions.
				$decodedStripeEventIDs = array_slice($decodedStripeEventIDs, -self::MAX_PROCESSED_EVENT_IDS);
				$data = json_encode($decodedStripeEventIDs);
				if (json_last_error() === JSON_ERROR_NONE) {
					$success = true;
				} else {
					$success = false;
				}
			}
		} else {
			$data = $encodedStripeEventIDs;
			$success = false;
		}

		return $data;
	}

	/**
	 * @param $event
	 *
	 * @return null|array
	 */
	protected function getDataPreviousAttributes($event)
	{
		$previous_attributes = null;
		if (isset($event) && isset($event->data) && isset($event->data->previous_attributes)) {
			$previous_attributes = $event->data->previous_attributes;
		}

		return $previous_attributes;
	}

	/**
	 * @param $wpfsSubscriber
	 *
	 * @return array|object|void|null
	 */
	protected function getSubscriptionFormBySubscriber($wpfsSubscriber)
	{
		$form = $this->db->getInlineSubscriptionFormById($wpfsSubscriber->formId);
		if (isset($form) && $form->name === $wpfsSubscriber->formName) {
			return $form;
		}
		$form = $this->db->getCheckoutSubscriptionFormById($wpfsSubscriber->formId);
		if (isset($form) && $form->name === $wpfsSubscriber->formName) {
			return $form;
		}

		return null;
	}

	/**
	 * Built before the subscription is ended: a Stripe call that fails then fails the delivery, and Stripe's retry
	 * sends the receipt.
	 *
	 * @param object $wpfsSubscriber
	 * @param LiveModeAwareEventProcessorContext $context
	 * @param object|array<string,mixed>|null $subscriptionMetadata Metadata of the ended subscription, when the event carries it.
	 * @param object|null $stripeSubscription The ended subscription with its items, which tell its recovery fee apart.
	 * @return array{form: object, transactionData: SubscriptionTransactionData}|null Null when the form sends none.
	 *
	 * @throws \Exception
	 */
	protected function createSubscriptionEndedReceipt($wpfsSubscriber, LiveModeAwareEventProcessorContext $context, $subscriptionMetadata = null, $stripeSubscription = null)
	{
		$form = $this->getSubscriptionFormBySubscriber($wpfsSubscriber);
		if (!Mailer::canSendSubscriptionEndedPluginNotification($form)) {
			return null;
		}

		return [
			'form' => $form,
			'transactionData' => $this->createSubscriptionDataBySubscriber($form, $wpfsSubscriber, $context, $subscriptionMetadata, $stripeSubscription),
		];
	}

	/**
	 * @param array{form: object, transactionData: SubscriptionTransactionData}|null $receipt
	 */
	protected function sendSubscriptionEndedReceipt($receipt)
	{
		if (!is_null($receipt)) {
			$this->mailer->sendSubscriptionFinishedEmailReceipt($receipt['form'], $receipt['transactionData']);
		}
	}

	protected function getSetupFeeFromSubscriptionForm($form, $planId)
	{
		$result = 0;

		$decoratedPlans = Utils::decodeJsonArray($form->decoratedPlans);
		foreach ($decoratedPlans as $decoratedPlan) {
			if ($decoratedPlan->stripePriceId === $planId) {
				if (property_exists($decoratedPlan, 'setupFee') && !empty($decoratedPlan->setupFee)) {
					$result = $decoratedPlan->setupFee;
				}
				break;
			}
		}

		return $result;
	}

	/**
	 * The subscription's paid invoices, newest first, back to its first one. Stripe lists them a page at a time.
	 *
	 * @param object $wpfsSubscriber
	 * @param LiveModeAwareEventProcessorContext $context
	 * @return list<\stdClass>
	 *
	 * @throws \Exception When Stripe can't list them.
	 */
	protected function listPaidInvoicesOfSubscription($wpfsSubscriber, LiveModeAwareEventProcessorContext $context)
	{
		$invoices = [];
		$params = [
			'subscription' => $wpfsSubscriber->stripeSubscriptionID,
			'status' => 'paid',
			'limit' => self::INVOICE_PAGE_SIZE,
		];
		while (true) {
			/** @var list<\stdClass> $page */
			$page = $context->getStripe()->listInvoicesWithParams($params);
			$lastInvoice = end($page);
			// A page ending where the previous one did means the cursor was ignored, and would repeat forever.
			if (false !== $lastInvoice && isset($params['starting_after']) && $lastInvoice->id === $params['starting_after']) {
				$this->logger->error(__FUNCTION__, "Listing the invoices of subscription '{$wpfsSubscriber->stripeSubscriptionID}' doesn't advance past '{$params['starting_after']}'.");
				break;
			}
			$invoices = array_merge($invoices, $page);
			if (count($page) < self::INVOICE_PAGE_SIZE) {
				break;
			}
			$params['starting_after'] = $lastInvoice->id;
		}

		return $invoices;
	}

	/**
	 * @param list<\stdClass> $invoices
	 * @return \stdClass|null
	 */
	private static function findFirstInvoice($invoices)
	{
		foreach ($invoices as $invoice) {
			if (isset($invoice->billing_reason) && 'subscription_create' === $invoice->billing_reason) {
				return $invoice;
			}
		}

		return null;
	}

	/**
	 * Records what the subscription last charged for a period, not a plan change's proration since: its newest
	 * paid cycle or first invoice's total, and the line of its fee item on it.
	 *
	 * @param SubscriptionTransactionData $transactionData
	 * @param list<\stdClass> $invoices Newest first.
	 * @param object $wpfsSubscriber
	 * @param object|null $stripeSubscription
	 * @param LiveModeAwareEventProcessorContext $context
	 *
	 * @throws \Exception When Stripe can't return the product of a fee item it needs to look at.
	 */
	private function setChargedRecoveryFeeOfLastPeriod($transactionData, $invoices, $wpfsSubscriber, $stripeSubscription, LiveModeAwareEventProcessorContext $context): void
	{
		$lastPeriodInvoice = null;
		foreach ($invoices as $invoice) {
			if (isset($invoice->billing_reason) && in_array($invoice->billing_reason, ['subscription_cycle', 'subscription_create'], true)) {
				$lastPeriodInvoice = $invoice;
				break;
			}
		}
		if (is_null($lastPeriodInvoice)) {
			return;
		}

		$items = is_null($stripeSubscription) ? null : RecoveryFee::findPlanAndFeeItems($stripeSubscription, $wpfsSubscriber->planID, [$context->getStripe(), 'retrieveProduct']);
		RecoveryFee::setChargedFeeOfInvoice($transactionData, $lastPeriodInvoice, $items);
	}

	/**
	 * A trial or a fully discounted period taxed nothing, so the plan's tax comes from the newest invoice that
	 * taxed it.
	 *
	 * @param list<\stdClass> $invoices Newest first.
	 * @param string $planId
	 * @param list<object{taxRateId?: string, percentage?: int|float}> $formTaxRates
	 * @return array{percent: float, inclusive: bool, taxed: bool}
	 */
	private static function findPlanTaxRate($invoices, $planId, $formTaxRates)
	{
		foreach ($invoices as $invoice) {
			$taxRate = Pricing::getTaxRateOfLineItem(Pricing::getPlanLineItem($invoice, $planId), $formTaxRates);
			if ($taxRate['taxed']) {
				return $taxRate;
			}
		}

		return Pricing::getTaxRateOfLineItem(null);
	}

	/**
	 * Checkout marks the product it creates for the setup fee.
	 *
	 * @param string $productId
	 * @param LiveModeAwareEventProcessorContext $context
	 * @return bool
	 *
	 * @throws \Exception When Stripe can't return the product.
	 */
	private function isSetupFeeProduct($productId, LiveModeAwareEventProcessorContext $context)
	{
		$product = $context->getStripe()->retrieveProduct($productId);

		return Main::LINE_ITEM_TYPE_SETUP_FEE === self::getMetadataValue(isset($product->metadata) ? $product->metadata : null, 'type');
	}

	/**
	 * @param object|array<string,mixed>|null $metadata
	 * @param string $key
	 * @return string|null
	 */
	private static function getMetadataValue($metadata, $key)
	{
		if (is_array($metadata)) {
			return isset($metadata[$key]) ? $metadata[$key] : null;
		}

		return is_object($metadata) && isset($metadata->{$key}) ? $metadata->{$key} : null;
	}

	/**
	 * @param int $amount The price, which includes the tax when the tax is inclusive.
	 * @param array{percent: float, inclusive: bool, taxed: bool} $taxRate
	 * @return array{net: int, tax: int, gross: int}
	 */
	private static function splitTax($amount, $taxRate)
	{
		if ($taxRate['inclusive']) {
			$net = (int) round($amount * 100 / (100 + $taxRate['percent']));

			return ['net' => $net, 'tax' => $amount - $net, 'gross' => $amount];
		}
		$composite = Utils::calculateGrossFromNet($amount, $taxRate['percent']);

		return ['net' => $amount, 'tax' => (int) $composite['taxValue'], 'gross' => (int) $composite['gross']];
	}

	/**
	 * @param $wpfsSubscriber
	 * @param LiveModeAwareEventProcessorContext $context
	 * @param object|array<string,mixed>|null $subscriptionMetadata
	 * @param object|null $stripeSubscription The ended subscription with its items.
	 *
	 * @return SubscriptionTransactionData
	 *
	 * @throws \Exception When Stripe can't return the price or the invoices.
	 */
	protected function createSubscriptionDataBySubscriber($form, $wpfsSubscriber, LiveModeAwareEventProcessorContext $context, $subscriptionMetadata = null, $stripeSubscription = null)
	{
		$transactionData = new SubscriptionTransactionData();

		$billingCountryComposite = Countries::getCountryByName($wpfsSubscriber->addressCountry);
		$billingAddress = Utils::prepareAddressData(
			$wpfsSubscriber->addressLine1,
			$wpfsSubscriber->addressLine2,
			$wpfsSubscriber->addressCity,
			$wpfsSubscriber->addressState,
			$wpfsSubscriber->addressCountry,
			is_null($billingCountryComposite) ? '' : $billingCountryComposite['alpha-2'],
			$wpfsSubscriber->addressZip
		);

		$shippingCountryComposite = Countries::getCountryByName($wpfsSubscriber->shippingAddressCountry);
		$shippingAddress = Utils::prepareAddressData(
			$wpfsSubscriber->shippingAddressLine1,
			$wpfsSubscriber->shippingAddressLine2,
			$wpfsSubscriber->shippingAddressCity,
			$wpfsSubscriber->shippingAddressState,
			$wpfsSubscriber->shippingAddressCountry,
			is_null($shippingCountryComposite) ? '' : $shippingCountryComposite['alpha-2'],
			$wpfsSubscriber->shippingAddressZip
		);

		$transactionData->setFormName($wpfsSubscriber->formName);
		$transactionData->setStripeCustomerId($wpfsSubscriber->stripeCustomerID);
		// The subscriber's record keeps the billing name when there is one; the subscription keeps the cardholder's.
		$cardHolderName = self::getMetadataValue($subscriptionMetadata, 'customer_name');
		$transactionData->setCustomerName(empty($cardHolderName) ? $wpfsSubscriber->name : $cardHolderName);
		$transactionData->setCustomerEmail($wpfsSubscriber->email);
		$transactionData->setPlanId($wpfsSubscriber->planID);
		$transactionData->setBillingName($wpfsSubscriber->billingName);
		$transactionData->setBillingAddress($billingAddress);
		$transactionData->setShippingName($wpfsSubscriber->shippingName);
		$transactionData->setShippingAddress($shippingAddress);
		$transactionData->setTransactionId($wpfsSubscriber->stripeSubscriptionID);
		$transactionData->setCouponCode($wpfsSubscriber->coupon);
		$transactionData->setCustomerPhone($wpfsSubscriber->phoneNumber);
		$transactionData->setIpAddress($wpfsSubscriber->ipAddressSubmit);
		$transactionData->setCustomFieldsJSON($wpfsSubscriber->customFields);

		$stripePlan = $context->getStripe()->retrievePlan($wpfsSubscriber->planID);
		if (is_null($stripePlan)) {
			// Stripe keeps every price, so it failed to answer.
			throw new \Exception("Cannot retrieve price '{$wpfsSubscriber->planID}' of subscription '{$wpfsSubscriber->stripeSubscriptionID}'.");
		}
		$transactionData->setPlanName($stripePlan->product->name);
		$transactionData->setPlanCurrency($stripePlan->currency);
		$transactionData->setProductName($stripePlan->product->name);

		$invoices = $this->listPaidInvoicesOfSubscription($wpfsSubscriber, $context);
		$firstInvoice = self::findFirstInvoice($invoices);
		$isCheckout = Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION === Utils::getFormType($form);

		$setupFeeLineItem = is_null($firstInvoice) ? null : Pricing::getSetupFeeLineItemOfFirstInvoice(
			$firstInvoice,
			$isCheckout,
			function ($productId) use ($context) {
				return $this->isSetupFeeProduct($productId, $context);
			}
		);

		// The fee the subscription was created with; older subscriptions didn't record it, so it's read from
		// their first invoice, and the form's current fee is the last resort.
		$stripePlanSetupFee = SubscriptionFormModel::getSetupFeeFromMetadata($subscriptionMetadata);
		if (is_null($stripePlanSetupFee)) {
			$stripePlanSetupFee = Pricing::getSetupFeeOfLineItem($setupFeeLineItem);
		}
		if (is_null($stripePlanSetupFee)) {
			$stripePlanSetupFee = $this->getSetupFeeFromSubscriptionForm($form, $wpfsSubscriber->planID);
		}

		// The tax the plan was last charged and the first invoice charged on the setup fee, none when unknown.
		$formTaxRates = Utils::decodeJsonArray($form->vatRates);
		$planTaxRate = self::findPlanTaxRate($invoices, $wpfsSubscriber->planID, $formTaxRates);
		$setupFeeTaxRate = Pricing::getTaxRateOfLineItem(is_object($setupFeeLineItem) ? $setupFeeLineItem : null, $formTaxRates);

		$plan = self::splitTax($stripePlan->unit_amount, $planTaxRate);
		$planTotal = self::splitTax($wpfsSubscriber->quantity * $stripePlan->unit_amount, $planTaxRate);
		$setupFee = self::splitTax($stripePlanSetupFee, $setupFeeTaxRate);
		$setupFeeTotal = self::splitTax($wpfsSubscriber->quantity * $stripePlanSetupFee, $setupFeeTaxRate);

		$transactionData->setPlanAmountVATRate($planTaxRate['percent']);
		$transactionData->setPlanSetupFeeVATRate($setupFeeTaxRate['percent']);
		$transactionData->setSetupFeeNetAmount($setupFee['net']);
		$transactionData->setSetupFeeGrossAmount($setupFee['gross']);
		$transactionData->setSetupFeeTaxAmount($setupFee['tax']);
		$transactionData->setSetupFeeNetAmountTotal($setupFeeTotal['net']);
		$transactionData->setSetupFeeGrossAmountTotal($setupFeeTotal['gross']);
		$transactionData->setSetupFeeTaxAmountTotal($setupFeeTotal['tax']);

		$transactionData->setPlanNetAmount($plan['net']);
		$transactionData->setPlanGrossAmount($plan['gross']);
		$transactionData->setPlanTaxAmount($plan['tax']);
		$transactionData->setPlanFutureNetAmount($plan['net']);
		$transactionData->setPlanFutureGrossAmount($plan['gross']);
		$transactionData->setPlanFutureTaxAmount($plan['tax']);
		$transactionData->setPlanQuantity($wpfsSubscriber->quantity);
		$transactionData->setPlanNetAmountTotal($planTotal['net']);
		$transactionData->setPlanGrossAmountTotal($planTotal['gross']);
		$transactionData->setPlanTaxAmountTotal($planTotal['tax']);

		$this->setChargedRecoveryFeeOfLastPeriod($transactionData, $invoices, $wpfsSubscriber, $stripeSubscription, $context);

		/*
		 *  todo: The following TransactionData attributes are not set at the moment:
		 * - companyName
		 * - taxCountry
		 * - taxId
		 * - Metadata
		 * - Billing cycle anchor day
		 * - Prorate until anchor day
		 */

		return $transactionData;
	}

	/**
	 * @param $stripeSubscriptionId
	 *
	 * @return object|null
	 */
	protected function findSubscriberByStripeSubscriptionId($stripeSubscriptionId)
	{
		return $this->db->getSubscriptionByStripeSubscriptionId($stripeSubscriptionId);
	}

	/**
	 * Runs $update with the PaymentIntent's latest charge from Stripe, holding the payment: events for it are also
	 * delivered in parallel, and one that read Stripe before a refund must not write after the refund's delivery did.
	 *
	 * @param string $paymentIntentId
	 * @param LiveModeAwareEventProcessorContext $context
	 * @param callable(\StripeWPFS\Stripe\Charge|\stdClass|null): void $update Given null when the PaymentIntent has no charge.
	 *
	 * @throws \Exception When another delivery holds the payment for too long, so that Stripe sends the event again.
	 */
	protected function updateFromLatestCharge($paymentIntentId, LiveModeAwareEventProcessorContext $context, callable $update): void
	{
		LatestCharge::write($this->db, $context->getStripe(), $paymentIntentId, $update);
	}

	/**
	 * Runs $update holding the payment, one at a time with the requests and deliveries that write it.
	 *
	 * @param string $paymentIntentId
	 * @param callable(): void $update
	 *
	 * @throws \Exception When another request holds the payment for too long, so that Stripe sends the event again.
	 */
	protected function whileHoldingPayment($paymentIntentId, callable $update): void
	{
		if (!$this->db->lockPaymentIntent($paymentIntentId, Database::PAYMENT_LOCK_TIMEOUT)) {
			throw new \Exception("Another request is updating the payment '{$paymentIntentId}'.");
		}
		try {
			$update();
		} finally {
			$this->db->unlockPaymentIntent($paymentIntentId);
		}
	}

	/**
	 * Adds the event to the subscriber's processed event IDs, as addEventID() does.
	 *
	 * @param object{stripeSubscriptionID: string, processedStripeEventIDs: string|null} $wpfsSubscriber
	 * @param \StripeWPFS\Stripe\Event $stripeEvent
	 * @param callable(string|null, string): (bool|int) $update Given the new event IDs and those read, '' for none.
	 *
	 * @return bool|int False when the subscriber has the event already.
	 * @throws \Exception When other deliveries keep changing the event IDs, so that Stripe sends the event again.
	 */
	protected function addEventToSubscriber($wpfsSubscriber, \StripeWPFS\Stripe\Event $stripeEvent, callable $update)
	{
		$stripeSubscriptionId = $wpfsSubscriber->stripeSubscriptionID;

		return $this->addEventID(
			$wpfsSubscriber->processedStripeEventIDs ?? '',
			$stripeEvent,
			$update,
			function () use ($stripeSubscriptionId) {
				$wpfsSubscriber = $this->findSubscriberByStripeSubscriptionId($stripeSubscriptionId);

				return is_null($wpfsSubscriber) ? null : ($wpfsSubscriber->processedStripeEventIDs ?? '');
			}
		);
	}

	/**
	 * Adds the event to a list of event IDs with $update, which writes the list only while it is the one read. Events
	 * for one subscription are delivered in parallel: writing back the list read would drop the ID another delivery
	 * added meanwhile, and that event would be counted again.
	 *
	 * @param string $readStripeEventIDs The JSON encoded list, '' for none.
	 * @param \StripeWPFS\Stripe\Event $stripeEvent
	 * @param callable(string|null, string): (bool|int) $update Given the new list and the one read.
	 * @param callable(): (string|null) $readAgain The list now, '' for none, null when its record is gone.
	 *
	 * @return bool|int False when the list has the event already.
	 * @throws \Exception When other deliveries keep changing the list, so that Stripe sends the event again.
	 */
	protected function addEventID(string $readStripeEventIDs, \StripeWPFS\Stripe\Event $stripeEvent, callable $update, callable $readAgain)
	{
		for ($attempt = 0; $attempt < self::MAX_EVENT_ID_WRITE_ATTEMPTS; $attempt++) {
			$inserted = false;
			$processedStripeEventIDs = $this->insertIfNotExists($readStripeEventIDs, $stripeEvent, $inserted);
			if (!$inserted) {
				return false;
			}

			$updated = $update($processedStripeEventIDs, $readStripeEventIDs);
			if ($updated) {
				return $updated;
			}

			$readStripeEventIDs = $readAgain();
			if (is_null($readStripeEventIDs)) {
				return false;
			}
		}

		throw new \Exception("Cannot add the event '{$stripeEvent->id}': other deliveries keep changing the list.");
	}

}
