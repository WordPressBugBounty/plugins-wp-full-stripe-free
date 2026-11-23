<?php

namespace ThemeIsle\WPFullPay\Checkout;

use ThemeIsle\WPFullPay\Context\FindStripeCustomerAddOn;
use ThemeIsle\WPFullPay\Customer\CreateOneTimeInvoiceOptions;
use ThemeIsle\WPFullPay\Customer\OneTimeInvoiceCreatorAddOn;
use ThemeIsle\WPFullPay\Forms\Models\CheckoutPaymentFormModel;
use ThemeIsle\WPFullPay\Forms\Models\PaymentFormModel;
use ThemeIsle\WPFullPay\Macros\OneTimePaymentMacroReplacer;
use ThemeIsle\WPFullPay\Macros\SaveCardMacroReplacer;
use ThemeIsle\WPFullPay\Mailer\Mailer;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Payments\FreePayment;
use ThemeIsle\WPFullPay\Payments\LatestCharge;
use ThemeIsle\WPFullPay\Pricing\RecoveryFee;
use ThemeIsle\WPFullPay\Results\ChargeResult;
use ThemeIsle\WPFullPay\TransactionData\OneTimePaymentTransactionData;
use ThemeIsle\WPFullPay\TransactionData\SaveCardTransactionData;
use ThemeIsle\WPFullPay\TransactionData\TransactionDataService;
use ThemeIsle\WPFullPay\Utils;

class CheckoutPaymentChargeHandler extends CheckoutChargeHandler
{
    use CheckoutTaxTools;
    use CheckoutInvoiceTools;
    use FindStripeCustomerAddOn;
    use OneTimeInvoiceCreatorAddOn;

    /**
     * @param $stripeCustomer \StripeWPFS\Stripe\Customer
     * @param $formModel CheckoutPaymentFormModel
     * @param $transactionData SaveCardTransactionData
     *
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    private function setMetadataAndDescriptionForStripeCustomer(&$stripeCustomer, $formModel, $transactionData)
    {
        $stripeCardSavedDescription = Utils::prepareStripeCardSavedDescription($this->staticContext, $formModel, $transactionData);

        $stripeCustomer->description = empty($stripeCardSavedDescription) ? null : $stripeCardSavedDescription;
        if (isset($stripeCustomer->metadata) && is_array($stripeCustomer->metadata)) {
            $stripeCustomer->metadata = array_merge($formModel->getCustomerMetadata(), $stripeCustomer->metadata);
        } else {
            $stripeCustomer->metadata = $formModel->getCustomerMetadata();
        }
        $this->stripe->updateCustomer($stripeCustomer);
    }

    /**
     * @param $paymentIntent \StripeWPFS\Stripe\PaymentIntent
     * @param $formModel CheckoutPaymentFormModel
     * @param $transactionData OneTimePaymentTransactionData
     *
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    private function setMetadataAndDescriptionForPaymentIntent(&$paymentIntent, $formModel, $transactionData)
    {
        $stripePaymentIntentDescription = Utils::prepareStripeChargeDescription($this->staticContext, $formModel, $transactionData);

        $paymentIntent->description = empty($stripePaymentIntentDescription) ? null : $stripePaymentIntentDescription;
        if (isset($paymentIntent->metadata) && is_array($paymentIntent->metadata)) {
            $paymentIntent->metadata = array_merge($formModel->getMetadata(), $paymentIntent->metadata);
        } else {
            $paymentIntent->metadata = $formModel->getMetadata();
        }
        $this->stripe->updatePaymentIntent($paymentIntent);

        $paymentIntent = $this->stripe->retrievePaymentIntent($paymentIntent->id);
        $paymentIntent->wpfs_form = $formModel->getFormName();
    }

    /**
     * @param \StripeWPFS\Stripe\PaymentMethod $paymentMethod
     * @param CheckoutPaymentFormModel $formModel
     *
     * @return \StripeWPFS\Stripe\Customer
     */
    private function findOrCreateStripeCustomer(&$paymentMethod, $formModel)
    {
        // Checkout attached the card to its Session's customer before this runs, so that customer is recorded as is.
        // Its email isn't compared with the payer's, unlike a customer found by email (#791).
        $stripeCustomer = $this->checkoutSubmissionService->retrieveStripeCustomerByPaymentMethod($paymentMethod);

        if (is_null($stripeCustomer)) {
            $stripeCustomer = $this->findStripeCustomerByEmail($paymentMethod->billing_details->email);
        }
        if (is_null($stripeCustomer)) {
            $metadata = $formModel->getCustomerMetadata();
            $stripeCustomer = $this->stripe->createCustomerWithPaymentMethod(
                $paymentMethod->id,
                $paymentMethod->billing_details->name,
                $paymentMethod->billing_details->email,
                $metadata
            );
        } else {
            $paymentMethod = $this->stripe->attachPaymentMethodToCustomerIfMissing(
                $stripeCustomer,
                $paymentMethod,
                /* set to default */
                true
            );
        }

        return $stripeCustomer;
    }

    /**
     * @param CheckoutPaymentFormModel $saveCardFormModel
     * @param SaveCardTransactionData $transactionData
     * @param \StripeWPFS\Stripe\Customer $stripeCustomer
     */
    protected function fireAfterCheckoutSaveCardAction($saveCardFormModel, $transactionData, $stripeCustomer)
    {
        $replacer = new SaveCardMacroReplacer($saveCardFormModel->getForm(), $transactionData, $this->loggerService);

        $params = [
            'email' => $saveCardFormModel->getCardHolderEmail(),
            'urlParameters' => $saveCardFormModel->getFormGetParametersAsArray(),
            'formName' => $saveCardFormModel->getFormName(),
            'stripeClient' => $this->stripe->getStripeClient(),
            'stripeCustomer' => $stripeCustomer,
            'rawPlaceholders' => $replacer->getRawKeyValuePairs(),
            'decoratedPlaceholders' => $replacer->getDecoratedKeyValuePairs(),
        ];

        do_action(Main::ACTION_NAME_AFTER_CHECKOUT_SAVE_CARD, $params);
		do_action( Main::ACTION_NAME_FIRE_WEBHOOK, $saveCardFormModel->getForm(), $params );
    }

    /**
     * @param $paymentFormModel PaymentFormModel
     * @param $transactionData OneTimePaymentTransactionData
     * @param $paymentIntent \StripeWPFS\Stripe\PaymentIntent
     */
    private function fireAfterCheckoutPaymentAction($paymentFormModel, $transactionData, $paymentIntent)
    {
        $replacer = new OneTimePaymentMacroReplacer($paymentFormModel->getForm(), $transactionData, $this->loggerService);

        $params = [
            'email' => $paymentFormModel->getCardHolderEmail(),
            'urlParameters' => $paymentFormModel->getFormGetParametersAsArray(),
            'formName' => $paymentFormModel->getFormName(),
            'priceId' => $paymentFormModel->getPriceId(),
            'productName' => $paymentFormModel->getProductName(),
            'currency' => $transactionData->getCurrency(),
            'amount' => $transactionData->getAmount(),
            'recoveryFee' => $transactionData->getRecoveryFee(),
            'chargedTotal' => $transactionData->getChargedTotal(),
            'stripeClient' => $this->stripe->getStripeClient(),
            'stripePaymentIntent' => $paymentIntent,
            'rawPlaceholders' => $replacer->getRawKeyValuePairs(),
            'decoratedPlaceholders' => $replacer->getDecoratedKeyValuePairs(),
        ];

        do_action(Main::ACTION_NAME_AFTER_CHECKOUT_PAYMENT_CHARGE, $params);
		do_action( Main::ACTION_NAME_FIRE_WEBHOOK, $paymentFormModel->getForm(), $params );
    }

    /**
     * @param $transactionData OneTimePaymentTransactionData
     * @param $stripeCustomer \StripeWPFS\Stripe\Customer
     */
    private function setTransactionDataFromContext(&$transactionData, $context)
    {
        $transactionData->setCustomerTaxId($context->customerTaxId);

        $transactionData->setAmount($context->invoiceData->amount);
        $transactionData->setProductAmountGross($context->invoiceData->amount);
        $transactionData->setProductAmountTax($context->invoiceData->taxAmount);
        $transactionData->setProductAmountNet($context->invoiceData->amount - $context->invoiceData->taxAmount);
        $transactionData->setProductAmountDiscount($context->invoiceData->discountAmount);
    }

    /**
     * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession
     */
    protected function getInvoiceDataFromCheckoutSession($checkoutSession)
    {
        $productLineItem = RecoveryFee::findProductLineItemOfCheckoutSession($checkoutSession);
        if (!is_null($productLineItem)) {
            return $this->extractPricingDataFromLineItem($productLineItem);
        }

        $invoiceData = new \StdClass;
        $invoiceData->amount = 0;
        $invoiceData->currency = 'usd';
        $invoiceData->description = '';
        $invoiceData->discountAmount = 0;
        $invoiceData->couponCode = '';
        $invoiceData->taxRates = [];
        $invoiceData->taxAmount = 0;

        return $invoiceData;
    }

    /**
     * The ID a payment its promotion code made free is recorded under. Stripe completes it as paid without a
     * PaymentIntent, and a run that completes the session again finds it by this ID.
     *
     * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession
     * @return string
     */
    public static function getFreePaymentId($checkoutSession)
    {
        return FreePayment::ID_PREFIX . $checkoutSession->id;
    }

    /**
     * Records a payment its promotion code made free, as an inline form records one.
     *
     * @param CheckoutPaymentFormModel $formModel
     * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession
     * @param \StripeWPFS\Stripe\Customer $stripeCustomer The customer Checkout created.
     * @param ChargeResult $chargeResult
     * @return ChargeResult
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    private function handleFreePayment($formModel, $checkoutSession, $stripeCustomer, $chargeResult)
    {
        $ctx = $this->getContextDataFromStripeObjects($checkoutSession, $stripeCustomer);

        $paymentMethod = null;
        $this->fixCustomerNamesAndAddresses($stripeCustomer, $paymentMethod, $checkoutSession);
        $formModel->setStripeCustomer($stripeCustomer, true);
        $formModel->setTransactionId(self::getFreePaymentId($checkoutSession));

        $transactionData = TransactionDataService::createOneTimePaymentDataByModel($formModel);
        $this->setTransactionDataFromContext($transactionData, $ctx);
        $this->setChargedRecoveryFee($transactionData, $checkoutSession);

        $liveMode = (bool) $checkoutSession->livemode;
        $paymentIntent = FreePayment::paymentIntent(
            self::getFreePaymentId($checkoutSession),
            $checkoutSession->currency,
            $liveMode,
            Utils::prepareStripeChargeDescription($this->staticContext, $formModel, $transactionData),
            $formModel->getFormName()
        );
        $formModel->setStripePaymentIntent($paymentIntent);
        $this->db->insertOrUpdatePayment($formModel, $transactionData, FreePayment::charge($liveMode));

        $this->fireAfterCheckoutPaymentAction($formModel, $transactionData, $paymentIntent);

        if (Mailer::canSendPaymentPluginReceipt($formModel->getForm())) {
            $this->mailer->sendOneTimePaymentReceipt($formModel->getForm(), $transactionData);
        }

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

        return $chargeResult;
    }

    /**
     * @param CheckoutPaymentFormModel $formModel
     * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession
     * @return ChargeResult
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     *
     */
    public function handle($formModel, $checkoutSession)
    {
        $chargeResult = new ChargeResult();

        $transactionData = null;
        if (Main::PAYMENT_TYPE_CARD_CAPTURE === $formModel->getForm()->customAmount) {
            // tnagy update result with payment type
            $chargeResult->setPaymentType(Main::PAYMENT_TYPE_CARD_CAPTURE);

            $setupIntent = $this->checkoutSubmissionService->retrieveStripeSetupIntentByCheckoutSession($checkoutSession);
            $paymentMethod = $this->checkoutSubmissionService->retrieveStripePaymentMethodBySetupIntent($setupIntent);

            if (isset($paymentMethod)) {
                $stripeCustomer = $this->findOrCreateStripeCustomer($paymentMethod, $formModel);

                $formModel->setTransactionId($stripeCustomer->id);
                $formModel->setStripePaymentMethod($paymentMethod);

                $this->fixCustomerNamesAndAddresses($stripeCustomer, $paymentMethod, $checkoutSession);
                $formModel->setStripeCustomer($stripeCustomer, true);

                $transactionData = TransactionDataService::createSaveCardDataByModel($formModel);
                $this->setMetadataAndDescriptionForStripeCustomer($stripeCustomer, $formModel, $transactionData);

                $this->db->insertSavedCard($formModel, $transactionData, $setupIntent->id);

                $this->fireAfterCheckoutSaveCardAction($formModel, $transactionData, $stripeCustomer);

                // convert to array to make sure mailchimp add-on can use it
                do_action(Main::ACTION_NAME_AFTER_CHECKOUT_SAVE_CARD, json_decode(json_encode($stripeCustomer), true));

                if (Mailer::canSendSaveCardPluginReceipt($formModel->getForm())) {
                    $this->mailer->sendSaveCardNotification($formModel->getForm(), $transactionData);
                }

                $chargeResult->setSuccess(true);
                $chargeResult->setMessageTitle(
                    /* translators: Banner title of successful transaction */
                    __('Success', 'wp-full-stripe-free')
                );
                $chargeResult->setMessage(
                    /* translators: Banner message of saving card successfully */
                    __('Card saved successfully!', 'wp-full-stripe-free')
                );
            } else {
                $chargeResult->setSuccess(false);
                $chargeResult->setMessageTitle(
                    /* translators: Banner title of failed transaction */
                    __('Failed', 'wp-full-stripe-free')
                );
                $chargeResult->setMessage(
                    /* translators: Banner message when the payment method of a Checkout payment can't be found */
                    __('Cannot find PaymentMethod!', 'wp-full-stripe-free')
                );
            }
        } else {
            // tnagy retrieve Stripe Customer and update form model
            $stripeCustomer = $this->checkoutSubmissionService->retrieveStripeCustomerByCheckoutSession($checkoutSession);
            if (!isset($stripeCustomer)) {
                $this->logger->error( __FUNCTION__, 'Error while handling checkout payment: cannot find Stripe customer.');
            }
            $paymentIntent = $this->checkoutSubmissionService->retrieveStripePaymentIntentByCheckoutSession($checkoutSession);
            if (!isset($paymentIntent) && isset($checkoutSession->amount_total) && 0 === (int) $checkoutSession->amount_total) {
                return $this->handleFreePayment($formModel, $checkoutSession, $stripeCustomer, $chargeResult);
            }
            if (!isset($paymentIntent)) {
                $this->logger->error( __FUNCTION__, 'Error while handling checkout payment: cannot find PaymentIntent.');
            }
            $paymentMethod = $this->checkoutSubmissionService->retrieveStripePaymentMethodByPaymentIntent($paymentIntent);
            if (!isset($paymentMethod)) {
                $this->logger->error( __FUNCTION__, 'Error while handling checkout payment: cannot find PaymentMethod.');
            }

            $ctx = $this->getContextDataFromStripeObjects($checkoutSession, $stripeCustomer);

            $this->fixCustomerNamesAndAddresses($stripeCustomer, $paymentMethod, $checkoutSession);
            $formModel->setStripeCustomer($stripeCustomer, true);
            $formModel->setStripePaymentMethod($paymentMethod);
            $formModel->setTransactionId($paymentIntent->id);

            $transactionData = TransactionDataService::createOneTimePaymentDataByModel($formModel);
            $this->setTransactionDataFromContext($transactionData, $ctx);
            $this->setChargedRecoveryFee($transactionData, $checkoutSession);

            $this->setMetadataAndDescriptionForPaymentIntent($paymentIntent, $formModel, $transactionData);
            $formModel->setStripePaymentIntent($paymentIntent);

            // From Stripe's latest charge, so that a refund recorded meanwhile is kept. When Stripe can't be read or another
            // request holds the payment too long, this fails: the submission stays pending, and the recovery records it later.
            LatestCharge::write($this->db, $this->stripe, $paymentIntent->id, function ($latestCharge) use ($formModel, $transactionData, $paymentIntent) {
                $this->db->insertOrUpdatePayment($formModel, $transactionData, $latestCharge ?? $this->stripe->getLatestCharge($paymentIntent));
            });

            if ($formModel->getForm()->generateInvoice == 1) {
                $createInvoiceOptions = new CreateOneTimeInvoiceOptions();
                $createInvoiceOptions->autoAdvance = false;
                $createInvoiceOptions->taxRateIds = $ctx->invoiceData->taxRates;
                $paidStripeInvoice = $this->createPaidInvoiceForRecordedPayment($this->createOneTimeInvoiceContextOfCheckoutSession($formModel, $checkoutSession), $createInvoiceOptions, $paymentIntent->id);
                if (!is_null($paidStripeInvoice)) {
                    $this->setTransactionDataFromInvoice($transactionData, $paidStripeInvoice);
                }
            }

            $this->fireAfterCheckoutPaymentAction($formModel, $transactionData, $paymentIntent);

            if (Mailer::canSendPaymentPluginReceipt($formModel->getForm())) {
                $this->mailer->sendOneTimePaymentReceipt($formModel->getForm(), $transactionData);
            }

            $chargeResult->setSuccess(true);
            $chargeResult->setMessageTitle(
                /* translators: Banner title of successful transaction */
                __('Success', 'wp-full-stripe-free')
            );
            $chargeResult->setMessage(
                /* translators: Banner message of successful payment */
                __('Payment Successful!', 'wp-full-stripe-free')
            );
        }

        $this->handleRedirect($formModel, $transactionData, $chargeResult);

        return $chargeResult;
    }
}
