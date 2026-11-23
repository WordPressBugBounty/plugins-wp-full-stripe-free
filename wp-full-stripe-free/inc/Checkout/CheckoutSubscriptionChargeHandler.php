<?php

namespace ThemeIsle\WPFullPay\Checkout;

use ThemeIsle\WPFullPay\Forms\Models\CheckoutSubscriptionFormModel;
use ThemeIsle\WPFullPay\Macros\SubscriptionMacroReplacer;
use ThemeIsle\WPFullPay\Mailer\Mailer;
use ThemeIsle\WPFullPay\Payments\LatestCharge;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Pricing\RecoveryFee;
use ThemeIsle\WPFullPay\Results\ChargeResult;
use ThemeIsle\WPFullPay\TransactionData\SubscriptionTransactionData;
use ThemeIsle\WPFullPay\TransactionData\TransactionDataService;

class CheckoutSubscriptionChargeHandler extends CheckoutChargeHandler
{
    use CheckoutTaxTools;
    use CheckoutInvoiceTools;

    /**
     * @param \StripeWPFS\Stripe\Subscription $subscription
     *
     * @return string
     */
    private function getCouponCode($subscription)
    {
        $res = null;

        if (isset($subscription->discount)) {
            $discount = $subscription->discount;

            if (isset($discount->promotion_code) && isset($discount->promotion_code->code)) {
                $res = $discount->promotion_code->code;
            } elseif (isset($discount->coupon) && isset($discount->coupon->name)) {
                $res = $discount->coupon->name;
            }
        }

        return $res;
    }

    /**
     * @param $transactionData SubscriptionTransactionData
     * @param $subscription \StripeWPFS\Stripe\Subscription
     * @param $formModel CheckoutSubscriptionFormModel
     */
    private function updateTransactionData(&$transactionData, $subscription, $formModel)
    {
        $latestInvoice = $this->getLatestInvoice($subscription);
        $this->setTransactionDataFromInvoice($transactionData, $latestInvoice);

        $transactionData->setReceiptUrl($this->stripe->getReceiptUrl($formModel->getStripePaymentIntent()));
    }

    /**
     * @param $stripeSubscription \StripeWPFS\Stripe\Subscription
     *
     * @return object|null
     */
    private function extractPopupFormSubmit($stripeSubscription)
    {
        $popupFormSubmit = null;
        // tnagy retrieve Stripe Subscription and update form model
        if (isset($stripeSubscription)) {
            if (isset($stripeSubscription->metadata) && isset($stripeSubscription->metadata->client_reference_id)) {
                $submitHash = $stripeSubscription->metadata->client_reference_id;
                $popupFormSubmit = $this->db->findPopupFormSubmitByHash($submitHash);
            }
        }

        return $popupFormSubmit;
    }

    /**
     * @param \StripeWPFS\Stripe\Subscription $subscription
     *
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    private function processStripeEvents($subscription)
    {
        // Finds no submission, so replays nothing: Checkout names it on the session, not in the subscription's metadata,
        // which setMetadataForSubscription() replaced before this runs.
        $popupFormSubmit = $this->extractPopupFormSubmit($subscription);

        if (isset($popupFormSubmit) && isset($popupFormSubmit->relatedStripeEventIDs)) {
            $relatedStripeEventIDs = $this->retrieveStripeEventIDs($popupFormSubmit->relatedStripeEventIDs);

            foreach ($relatedStripeEventIDs as $relatedStripeEventID) {
                $stripeEvent = $this->retrieveStripeEvent($relatedStripeEventID);
                if (isset($stripeEvent)) {
                    $this->eventHandler->handle($stripeEvent);
                }
            }
        }
    }

    /**
     * @param \StripeWPFS\Stripe\PaymentIntent $stripePaymentIntent
     * @param \StripeWPFS\Stripe\SetupIntent $stripeSetupIntent
     *
     * @return \StripeWPFS\Stripe\PaymentMethod|null
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    private function extractPaymentMethod($stripePaymentIntent, $stripeSetupIntent)
    {
        $paymentMethod = $this->checkoutSubmissionService->retrieveStripePaymentMethodByPaymentIntent($stripePaymentIntent);
        if (is_null($paymentMethod)) {
            $paymentMethod = $this->checkoutSubmissionService->retrieveStripePaymentMethodBySetupIntent($stripeSetupIntent);
        }

        return $paymentMethod;
    }

    /**
     * @param \StripeWPFS\Stripe\PaymentIntent|null $stripePaymentIntent
     * @param SubscriptionTransactionData $transactionData
     * @param \StripeWPFS\Stripe\Charge|\stdClass|null $latestCharge The PaymentIntent's latest charge, read while holding
     *        the payment: a first bank debit that failed since $stripePaymentIntent was read leaves it incomplete.
     */
    private function updateSubscriptionToRunning($stripePaymentIntent, $transactionData, $latestCharge = null)
    {
        if (isset($stripePaymentIntent)) {
            if (
                (
                    \StripeWPFS\Stripe\PaymentIntent::STATUS_SUCCEEDED === $stripePaymentIntent->status
                    || \StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_CAPTURE === $stripePaymentIntent->status
                    || \StripeWPFS\Stripe\PaymentIntent::STATUS_PROCESSING === $stripePaymentIntent->status
                )
                && Main::STRIPE_CHARGE_STATUS_FAILED !== ($latestCharge->status ?? null)
            ) {
                $this->db->updateSubscriptionByPaymentIntentToRunning($stripePaymentIntent->id);
            }
        } else if ($transactionData->getPlanGrossAmountAndGrossSetupFeeTotal() == 0) {
            // If there is no charge then there is no payment intent
            $this->db->updateNewSubscriptionToRunning($transactionData->getTransactionId());
        }
    }

    /**
     * @param $stripeSubscription
     * @param $formModel CheckoutSubscriptionFormModel
     */
    private function setMetadataForSubscription($stripeSubscription, $formModel)
    {
        if (isset($stripeSubscription->metadata) && is_array($stripeSubscription->metadata)) {
            $stripeSubscription->metadata = array_merge($formModel->getMetadata(), $stripeSubscription->metadata);
        } else {
            $stripeSubscription->metadata = $formModel->getMetadata();
        }
        $this->stripe->updateSubscription($stripeSubscription);
    }

    /**
     * @param $formModel CheckoutSubscriptionFormModel
     * @param $transactionData SubscriptionTransactionData
     * @param $subscription \StripeWPFS\Stripe\Subscription
     */
    private function fireAfterSubscriptionAction($subscriptionFormModel, $transactionData, $subscription)
    {
        $replacer = new SubscriptionMacroReplacer($subscriptionFormModel->getForm(), $transactionData, $this->loggerService);

        $params = [
            'email' => $subscriptionFormModel->getCardHolderEmail(),
            'urlParameters' => $subscriptionFormModel->getFormGetParametersAsArray(),
            'formName' => $subscriptionFormModel->getFormName(),
            'productName' => $subscriptionFormModel->getProductName(),
            'planId' => $subscriptionFormModel->getStripePlanId(),
            'currency' => $transactionData->getPlanCurrency(),
            'amount' => $transactionData->getAmount(),
            'recoveryFee' => $transactionData->getRecoveryFee(),
            'chargedTotal' => $transactionData->getChargedTotal(),
            'setupFee' => $subscriptionFormModel->getSetupFee(),
            'quantity' => $subscriptionFormModel->getStripePlanQuantity(),
            'stripeClient' => $this->stripe->getStripeClient(),
            'stripeSubscription' => $subscription,
            'rawPlaceholders' => $replacer->getRawKeyValuePairs(),
            'decoratedPlaceholders' => $replacer->getDecoratedKeyValuePairs(),
        ];

        do_action(Main::ACTION_NAME_AFTER_CHECKOUT_SUBSCRIPTION_CHARGE, $params);
		do_action( Main::ACTION_NAME_FIRE_WEBHOOK, $subscriptionFormModel->getForm(), $params );
    }

    /**
     * @param $lineItem
     * @return bool
     */
    protected function isSetupFeeLineItem($lineItem)
    {
        $result = false;
        $metaData = $lineItem->price->product->metadata;

        if (
            $metaData !== null && isset($metaData->type) && 
            $metaData->type === 'setupFee'
        ) {
            $result = true;
        }

        return $result;
    }

    /**
     * The setup fee per unit the session charged; a session without a setup fee line charged none.
     *
     * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession
     * @param object{setupFee?: object{unitAmount: int}} $invoiceData
     * @return int|null Null when the session's line items are unknown.
     */
    protected function getChargedSetupFee($checkoutSession, $invoiceData)
    {
        if (empty($checkoutSession->line_items->data)) {
            return null;
        }

        return isset($invoiceData->setupFee) ? (int) $invoiceData->setupFee->unitAmount : 0;
    }

    /**
     * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession
     */
    protected function getInvoiceDataFromCheckoutSession($checkoutSession)
    {
        $invoiceData = new \StdClass;
        $currency = null;

        if (count($checkoutSession->line_items->data) > 0) {
            $feeLineItems = RecoveryFee::findFeeLineItemsOfCheckoutSession($checkoutSession);
            foreach ($checkoutSession->line_items->data as $lineItem) {
                if ($this->isSetupFeeLineItem($lineItem)) {
                    $invoiceData->setupFee = $this->extractPricingDataFromLineItem($lineItem);
                } elseif (in_array($lineItem, $feeLineItems, true)) {
                    // Reported as the recovery fee, not as the plan.
                    continue;
                } else {
                    $invoiceData->plan = $this->extractPricingDataFromLineItem($lineItem);
                }
            }
        } else {
            $result = new \StdClass;

            $result->currency = 'usd';
            $result->quantity = 0;
            $result->unitAmount = 0;
            $result->amount = 0;
            $result->discountAmount = 0;
            $result->taxAmount = 0;
            $result->description = '';
            $result->couponCode = '';
            $result->taxRates = [];

            $invoiceData->plan = $result;
        }

        return $invoiceData;
    }

    /**
     * @param SubscriptionTransactionData $transactionData
     * @param $data
     */
    protected function setPricingPlaceholders(&$transactionData, $data)
    {
        $setupFeeAmount = 0;
        $setupFeeDiscountAmount = 0;
        $setupFeeTaxAmount = 0;
        $setupFeeUnitAmount = 0;
        if (property_exists($data, 'setupFee')) {
            $setupFeeAmount = $data->setupFee->amount;
            $setupFeeDiscountAmount = $data->setupFee->discountAmount;
            $setupFeeTaxAmount = $data->setupFee->taxAmount;
            $setupFeeUnitAmount = $data->setupFee->unitAmount;
        }

        if ($data->plan->taxAmount > 0) {
            $transactionData->setPlanAmountVATRate($data->plan->taxPercentage);
        }
        if ($setupFeeTaxAmount > 0) {
            $transactionData->setPlanSetupFeeVATRate($data->setupFee->taxPercentage);
        }

        $transactionData->setAmount($setupFeeAmount + $data->plan->amount);
        $transactionData->setPlanQuantity($data->plan->quantity);
        $transactionData->setPlanCurrency($data->plan->currency);
        if ($data->plan->quantity == 1) {
            $transactionData->setPlanGrossAmountTotal($data->plan->amount);
            $transactionData->setPlanGrossAmount($transactionData->getPlanGrossAmountTotal());

            $transactionData->setPlanTaxAmountTotal($data->plan->taxAmount);
            $transactionData->setPlanTaxAmount($transactionData->getPlanTaxAmountTotal());

            $transactionData->setPlanNetAmountTotal($transactionData->getPlanGrossAmountTotal() - $transactionData->getPlanTaxAmountTotal());
            $transactionData->setPlanNetAmount($transactionData->getPlanNetAmountTotal());

            $transactionData->setSetupFeeGrossAmountTotal($setupFeeAmount);
            $transactionData->setSetupFeeGrossAmount($transactionData->getSetupFeeGrossAmountTotal());

            $transactionData->setSetupFeeTaxAmountTotal($setupFeeTaxAmount);
            $transactionData->setSetupFeeTaxAmount($transactionData->getSetupFeeTaxAmountTotal());

            $transactionData->setSetupFeeNetAmountTotal($transactionData->getSetupFeeGrossAmountTotal() - $transactionData->getSetupFeeTaxAmountTotal());
            $transactionData->setSetupFeeNetAmount($transactionData->getSetupFeeNetAmountTotal());
        } else {
            $transactionData->setPlanGrossAmountTotal($data->plan->amount);
            $planUnitAmount = (int) round($transactionData->getPlanGrossAmountTotal() / $data->plan->quantity);
            $transactionData->setPlanGrossAmount($planUnitAmount);

            $transactionData->setPlanTaxAmountTotal($data->plan->taxAmount);
            $planTaxUnitAmount = (int) round($transactionData->getPlanTaxAmountTotal() / $data->plan->quantity);
            $transactionData->setPlanTaxAmount($planTaxUnitAmount);

            $transactionData->setPlanNetAmountTotal($transactionData->getPlanGrossAmountTotal() - $transactionData->getPlanTaxAmountTotal());
            $planNetUnitAmount = (int) round($transactionData->getPlanNetAmountTotal() / $data->plan->quantity);
            $transactionData->setPlanNetAmount($planNetUnitAmount);

            $transactionData->setSetupFeeGrossAmountTotal($setupFeeAmount);
            $setupFeeUnitAmount = (int) round($transactionData->getSetupFeeGrossAmountTotal() / $data->plan->quantity);
            $transactionData->setSetupFeeGrossAmount($setupFeeUnitAmount);

            $transactionData->setSetupFeeTaxAmountTotal($setupFeeTaxAmount);
            $setupFeeTaxUnitAmount = (int) round($transactionData->getSetupFeeTaxAmountTotal() / $data->plan->quantity);
            $transactionData->setSetupFeeTaxAmount($setupFeeTaxUnitAmount);

            $transactionData->setSetupFeeNetAmountTotal($transactionData->getSetupFeeGrossAmountTotal() - $transactionData->getSetupFeeTaxAmountTotal());
            $setupFeeNetUnitAmount = (int) round($transactionData->getSetupFeeNetAmountTotal() / $data->plan->quantity);
            $transactionData->setSetupFeeNetAmount($setupFeeNetUnitAmount);
        }

        // Without a trial, the next periods cost what this one was charged, tax included.
        if ($data->plan->amount > 0) {
            $transactionData->setPlanFutureNetAmount($transactionData->getPlanNetAmount());
            $transactionData->setPlanFutureTaxAmount($transactionData->getPlanTaxAmount());
            $transactionData->setPlanFutureGrossAmount($transactionData->getPlanGrossAmount());
        }
    }

    /**
     * @param $transactionData SubscriptionTransactionData
     * @param $stripeCustomer \StripeWPFS\Stripe\Customer
     */
    private function setTransactionDataFromContext(&$transactionData, $context)
    {
        $transactionData->setCustomerTaxId($context->customerTaxId);

        $this->setPricingPlaceholders($transactionData, $context->invoiceData);
    }

    public function handle($formModel, $checkoutSession)
    {
        $chargeResult = new ChargeResult();

        $stripeCustomer = $this->checkoutSubmissionService->retrieveStripeCustomerByCheckoutSession($checkoutSession);
        $stripeSubscription = $this->checkoutSubmissionService->retrieveStripeSubscriptionByCheckoutSession($checkoutSession);
        $stripePaymentIntent = $this->checkoutSubmissionService->findPaymentIntentInCheckoutSession($checkoutSession);
        $stripeSetupIntent = $this->checkoutSubmissionService->findSetupIntentInCheckoutSession($checkoutSession);
        $paymentMethod = $this->extractPaymentMethod($stripePaymentIntent, $stripeSetupIntent);

        if (!is_null($paymentMethod)) {
            $paymentMethod = $this->stripe->attachPaymentMethodToCustomerIfMissing(
                $stripeCustomer,
                $paymentMethod,
                /* set to default */
                true
            );
        }

        $ctx = $this->getContextDataFromStripeObjects($checkoutSession, $stripeCustomer);

        $this->fixCustomerNamesAndAddresses($stripeCustomer, $paymentMethod, $checkoutSession);
        $formModel->setStripeCustomer($stripeCustomer, true);
        $formModel->setStripePaymentMethod($paymentMethod);
        $formModel->setStripePaymentIntent($stripePaymentIntent);
        $formModel->setStripeSetupIntent($stripeSetupIntent);
        $formModel->setStripeSubscription($stripeSubscription);
        $formModel->setSetupFeeFromSubscription($stripeSubscription, $this->getChargedSetupFee($checkoutSession, $ctx->invoiceData));
        $formModel->setTransactionId($stripeSubscription->id);

        $formModel->setCouponCode($this->getCouponCode($stripeSubscription));

        $transactionData = TransactionDataService::createSubscriptionDataByModel($formModel);
        $this->setTransactionDataFromContext($transactionData, $ctx);
        $this->setChargedRecoveryFee($transactionData, $checkoutSession);
        $this->updateTransactionData($transactionData, $stripeSubscription, $formModel);

        $this->setMetadataForSubscription($stripeSubscription, $formModel);

        if (isset($stripePaymentIntent)) {
            // Its report from Stripe's latest charge, so that a refund recorded meanwhile is kept. When Stripe can't be read
            // or another request holds the payment too long, this fails: the submission stays pending, and the recovery
            // records it later.
            LatestCharge::write($this->db, $this->stripe, $stripePaymentIntent->id, function ($latestCharge) use ($formModel, $transactionData, $stripePaymentIntent) {
                $this->db->insertSubscriber($formModel, $transactionData, $latestCharge ?? LatestCharge::expandedOf($stripePaymentIntent));
                $this->updateSubscriptionToRunning($stripePaymentIntent, $transactionData, $latestCharge);
            });
        } else {
            $this->db->insertSubscriber($formModel, $transactionData);
            $this->updateSubscriptionToRunning(null, $transactionData);
        }

        $this->processStripeEvents($stripeSubscription);

        $this->fireAfterSubscriptionAction($formModel, $transactionData, $stripeSubscription);

        $chargeResult->setSuccess(true);
        $chargeResult->setMessageTitle(
            /* translators: Banner title of successful transaction */
            __('Success', 'wp-full-stripe-free')
        );
        $chargeResult->setMessage(
            /* translators: Banner message of successful payment */
            __('Payment Successful!', 'wp-full-stripe-free')
        );

        $this->handleRedirect($formModel, $transactionData, $chargeResult);

        if (Mailer::canSendSubscriptionPluginReceipt($formModel->getForm())) {
            $this->mailer->sendSubscriptionStartedEmailReceipt($formModel->getForm(), $transactionData);
        }

        return $chargeResult;
    }

    /**
     * @param $encodedStripeEventIDs
     *
     * @return array|mixed|object
     */
    protected function retrieveStripeEventIDs($encodedStripeEventIDs)
    {
        $decodedStripeEventIDs = json_decode($encodedStripeEventIDs);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decodedStripeEventIDs = [];
        }
        if (!is_array($decodedStripeEventIDs)) {
            $decodedStripeEventIDs = [];
        }

        return $decodedStripeEventIDs;
    }

    /**
     * @param $stripeEventID
     *
     * @return \StripeWPFS\Stripe\Event
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    protected function retrieveStripeEvent($stripeEventID)
    {
        return $this->stripe->retrieveEvent($stripeEventID);
    }

    /**
     * @param $stripeSubscription
     *
     * @return \StripeWPFS\Stripe\Invoice
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    protected function getLatestInvoice($stripeSubscription)
    {
        $result = null;

        if (isset($stripeSubscription->latest_invoice)) {
            $result = $stripeSubscription->latest_invoice;
        } else {
            $params = [
                'expand' => [
                    'payment_intent',
                    'charge'
                ]
            ];
            $result = $this->stripe->retrieveInvoiceWithParams($stripeSubscription->latest_invoice, $params);
        }

        return $result;
    }

}
