<?php

namespace ThemeIsle\WPFullPay\Checkout;

use ThemeIsle\WPFullPay\Customer\CreateOneTimeInvoiceOptions;
use ThemeIsle\WPFullPay\Customer\DonationToolsAddOn;
use ThemeIsle\WPFullPay\Customer\OneTimeInvoiceCreatorAddOn;
use ThemeIsle\WPFullPay\Forms\Models\DonationFormModel;
use ThemeIsle\WPFullPay\Macros\DonationMacroReplacer;
use ThemeIsle\WPFullPay\Mailer\Mailer;
use ThemeIsle\WPFullPay\Payments\LatestCharge;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Pricing\RecoveryFee;
use ThemeIsle\WPFullPay\Results\ChargeResult;
use ThemeIsle\WPFullPay\Results\DonationCheckoutResult;
use ThemeIsle\WPFullPay\TransactionData\DonationTransactionData;
use ThemeIsle\WPFullPay\TransactionData\TransactionDataService;
use ThemeIsle\WPFullPay\Utils;

class CheckoutDonationChargeHandler extends CheckoutChargeHandler
{
    use DonationToolsAddOn;
    use CheckoutInvoiceTools;
    use OneTimeInvoiceCreatorAddOn;

    /**
     * @param $formModel DonationFormModel
     * @param $transactionData DonationTransactionData
     * @param $paymentIntent \StripeWPFS\Stripe\PaymentIntent
     *
     * @throws \Exception
     */
    private function updatePaymentIntent($formModel, $transactionData, &$paymentIntent)
    {
        $paymentIntent->description = Utils::prepareStripeDonationDescription($this->staticContext, $formModel, $transactionData);
        if (isset($paymentIntent->metadata) && is_array($paymentIntent->metadata)) {
            $paymentIntent->metadata = array_merge($formModel->getMetadata(), $paymentIntent->metadata);
        } else {
            $paymentIntent->metadata = $formModel->getMetadata();
        }
        $this->stripe->updatePaymentIntent($paymentIntent);
        $paymentIntent = $this->stripe->retrievePaymentIntent($paymentIntent->id);
    }

    /**
     * @param $paymentIntent \StripeWPFS\Stripe\PaymentIntent
     * @param $formName
     */
    protected function addFormNameToPaymentIntent(&$paymentIntent, $formName)
    {
        $paymentIntent->wpfs_form = $formName;
    }

    /**
     * @param $donationFormModel DonationFormModel
     * @param $transactionData DonationTransactionData
     * @param $paymentIntent \StripeWPFS\Stripe\PaymentIntent
     */
    protected function fireAfterCheckoutDonationAction($donationFormModel, $transactionData, $paymentIntent)
    {
        $replacer = new DonationMacroReplacer($donationFormModel->getForm(), $transactionData, $this->loggerService);

        $params = [
            'email' => $donationFormModel->getCardHolderEmail(),
            'urlParameters' => $donationFormModel->getFormGetParametersAsArray(),
            'formName' => $donationFormModel->getFormName(),
            'currency' => $transactionData->getCurrency(),
            'frequency' => $donationFormModel->getDonationFrequency(),
            'amount' => $donationFormModel->getAmount(),
            'recoveryFee' => $transactionData->getRecoveryFee(),
            'chargedTotal' => $transactionData->getChargedTotal(),
            'stripeClient' => $this->stripe->getStripeClient(),
            'stripePaymentIntent' => $paymentIntent,
            'stripeSubscription' => $donationFormModel->getStripeSubscription(),
            'rawPlaceholders' => $replacer->getRawKeyValuePairs(),
            'decoratedPlaceholders' => $replacer->getDecoratedKeyValuePairs(),
        ];

        do_action(Main::ACTION_NAME_AFTER_CHECKOUT_DONATION_CHARGE, $params);
		do_action( Main::ACTION_NAME_FIRE_WEBHOOK, $donationFormModel->getForm(), $params );
    }

    /**
     * @param DonationCheckoutResult $donationResult
     * @param string $title
     * @param string $message
     *
     * @return DonationCheckoutResult
     */
    protected function createDonationResultSuccess(&$donationResult, $title, $message)
    {
        $donationResult->setSuccess(true);
        $donationResult->setMessageTitle($title);
        $donationResult->setMessage($message);

        return $donationResult;
    }

    /**
     * @param \StripeWPFS\Stripe\PaymentIntent $paymentIntent
     * @param \StripeWPFS\Stripe\Customer $stripeCustomer
     * @param boolean $isRecurringDonation
     *
     * @return \StripeWPFS\Stripe\PaymentMethod
     */
    protected function setDefaultPaymentMethodFromPaymentIntent($paymentIntent, &$stripeCustomer, $isRecurringDonation = true)
    {
        $paymentMethod = $this->checkoutSubmissionService->retrieveStripePaymentMethodByPaymentIntent($paymentIntent);
        if (!is_null($paymentMethod) && $isRecurringDonation) {
            $paymentMethod = $this->stripe->attachPaymentMethodToCustomerIfMissing(
                $stripeCustomer,
                $paymentMethod,
                /* set to default */
                true
            );
        }

        return $paymentMethod;
    }

    /**
     * Resolves the PaymentMethod to use for a subscription donation.
     *
     * @param \StripeWPFS\Stripe\Subscription $subscription
     * @param \StripeWPFS\Stripe\PaymentIntent|null $paymentIntent
     *
     * @return \StripeWPFS\Stripe\PaymentMethod|null
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    protected function resolvePaymentMethodForSubscription($subscription, $paymentIntent)
    {
        $paymentMethod = null;
        if (!is_null($paymentIntent)) {
            $paymentMethod = $this->checkoutSubmissionService->retrieveStripePaymentMethodByPaymentIntent($paymentIntent);
        }
        if (is_null($paymentMethod) && isset($subscription->default_payment_method)) {
            $defaultPaymentMethod = $subscription->default_payment_method;
            if (is_object($defaultPaymentMethod) && isset($defaultPaymentMethod->id)) {
                $paymentMethod = $defaultPaymentMethod;
            } elseif (is_string($defaultPaymentMethod) && !empty($defaultPaymentMethod)) {
                try {
                    $paymentMethod = $this->stripe->retrievePaymentMethod($defaultPaymentMethod);
                } catch (\Exception $ex) {
                    $this->logger->error(__FUNCTION__, 'Error while retrieving subscription default PaymentMethod', $ex);
                }
            }
        }
        return $paymentMethod;
    }

    /**
     * @param DonationFormModel $formModel
     * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession
     *
     * @return ChargeResult
     * @throws \Exception
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    public function handle($formModel, $checkoutSession)
    {
        $chargeResult = new DonationCheckoutResult();

        $isSubscriptionMode = isset($checkoutSession->mode) && 'subscription' === $checkoutSession->mode;
        $isRecurring = $this->isRecurringDonation($formModel);

        $stripeCustomer = $this->checkoutSubmissionService->retrieveStripeCustomerByCheckoutSession($checkoutSession);
        if (is_null($stripeCustomer)) {
            $this->logger->error(__FUNCTION__, 'Error while handling checkout donation: cannot find Stripe customer.');
        }

        $subscription = null;
        $paymentIntent = null;
        $paymentMethod = null;

        if ($isSubscriptionMode) {
            $subscription = $this->checkoutSubmissionService->retrieveStripeSubscriptionByCheckoutSession($checkoutSession);
            if (is_null($subscription)) {
                $this->logger->error(__FUNCTION__, 'Error while handling checkout donation: cannot find Stripe Subscription on checkout session.');
            } else {
                $paymentIntent = $this->checkoutSubmissionService->findPaymentIntentInSubscription($subscription);
                if (is_null($paymentIntent)) {
                    $this->logger->error(__FUNCTION__, 'Error while handling checkout donation: cannot find PaymentIntent on subscription invoice.');
                }

                $paymentMethod = $this->resolvePaymentMethodForSubscription($subscription, $paymentIntent);
                if (!is_null($paymentMethod) && !is_null($stripeCustomer)) {
                    $paymentMethod = $this->stripe->attachPaymentMethodToCustomerIfMissing(
                        $stripeCustomer,
                        $paymentMethod,
                        /* set to default */
                        true
                    );
                }
            }
        } else {
            $paymentIntent = $this->checkoutSubmissionService->retrieveStripePaymentIntentByCheckoutSession($checkoutSession);
            if (is_null($paymentIntent)) {
                $this->logger->error(__FUNCTION__, 'Error while handling checkout donation: cannot find PaymentIntent on checkout session.');
            } else {
                $paymentMethod = $this->setDefaultPaymentMethodFromPaymentIntent($paymentIntent, $stripeCustomer, $isRecurring);
            }
        }

        if (is_null($stripeCustomer) || is_null($paymentIntent) || ($isSubscriptionMode && is_null($subscription))) {
            $chargeResult->setSuccess(false);
            $chargeResult->setMessageTitle(
                /* translators: Banner title of failed transaction */
                __('Failed', 'wp-full-stripe-free')
            );
            $chargeResult->setMessage(
                /* translators: Banner message when a Checkout donation can't be completed because Stripe data is missing */
                __('Cannot complete donation: missing Stripe customer / payment details.', 'wp-full-stripe-free')
            );
            return $chargeResult;
        }

        // Stripe charged the amount the session was created with, even if the form's presets changed since.
        // Sessions created before this amount was stored on them use their donation line.
        if (isset($checkoutSession->metadata->{DonationFormModel::METADATA_KEY_DONATION_AMOUNT})) {
            $chargedAmount = $checkoutSession->metadata->{DonationFormModel::METADATA_KEY_DONATION_AMOUNT};
        } else {
            $donationLineItem = RecoveryFee::findProductLineItemOfCheckoutSession($checkoutSession);
            $chargedAmount = isset($donationLineItem->amount_subtotal) ? $donationLineItem->amount_subtotal : null;
        }
        if (is_numeric($chargedAmount)) {
            $formModel->setChargedAmount((int) $chargedAmount);
        }

        $this->fixCustomerNamesAndAddresses($stripeCustomer, $paymentMethod, $checkoutSession);
        $formModel->setTransactionId($paymentIntent->id);
        $formModel->setStripePaymentMethod($paymentMethod);
        $formModel->setStripeCustomer($stripeCustomer, true);
        if (!is_null($subscription)) {
            $formModel->setStripeSubscription($subscription);
        }

        $transactionData = TransactionDataService::createDonationDataByFormModel($formModel);
        $this->setChargedRecoveryFee($transactionData, $checkoutSession);

        $this->updatePaymentIntent($formModel, $transactionData, $paymentIntent);
        $this->addFormNameToPaymentIntent($paymentIntent, $formModel->getFormName());

        $donationLineItem = RecoveryFee::findProductLineItemOfCheckoutSession($checkoutSession);
        // From Stripe's latest charge, so that a refund recorded meanwhile is kept. When Stripe can't be read or another
        // request holds the payment too long, this fails: the submission stays pending, and the recovery records it later.
        LatestCharge::write($this->db, $this->stripe, $paymentIntent->id, function ($latestCharge) use ($formModel, $paymentIntent, $subscription, $donationLineItem) {
            $this->db->insertCheckoutDonation($formModel, $paymentIntent, $subscription, $latestCharge ?? $this->stripe->getLatestCharge($paymentIntent), isset($donationLineItem->price->id) ? $donationLineItem->price->id : null);
        });

        // After the donation is recorded: a write that fails leaves the session for the recovery, which would invoice it
        // again.
        if ($formModel->getForm()->generateInvoice == 1) {
            if ($isSubscriptionMode) {
                $invoice = isset($subscription->latest_invoice) && is_object($subscription->latest_invoice)
                    ? $subscription->latest_invoice
                    : null;
                if (!is_null($invoice)) {
                    $this->setTransactionDataFromInvoice($transactionData, $invoice);
                }
            } else {
                $createInvoiceOptions = new CreateOneTimeInvoiceOptions();
                $createInvoiceOptions->autoAdvance = false;
                $paidStripeInvoice = $this->createPaidInvoiceForRecordedPayment($this->createOneTimeInvoiceContextOfCheckoutSession($formModel, $checkoutSession), $createInvoiceOptions, $paymentIntent->id);
                if (!is_null($paidStripeInvoice)) {
                    $this->setTransactionDataFromInvoice($transactionData, $paidStripeInvoice);
                }
            }
        }

        $this->fireAfterCheckoutDonationAction($formModel, $transactionData, $paymentIntent);

        // tnagy update result
        $this->createDonationResultSuccess(
            $chargeResult,
            /* translators: Banner title of successful transaction */
            __('Success', 'wp-full-stripe-free'),
            /* translators: Banner message of successful payment */
            __('Donation Successful!', 'wp-full-stripe-free')
        );

        $this->handleRedirect($formModel, $transactionData, $chargeResult);

        if (Mailer::canSendDonationPluginReceipt($formModel->getForm())) {
            $this->mailer->sendDonationEmailReceipt($formModel->getForm(), $transactionData);
        }

        return $chargeResult;
    }
}
