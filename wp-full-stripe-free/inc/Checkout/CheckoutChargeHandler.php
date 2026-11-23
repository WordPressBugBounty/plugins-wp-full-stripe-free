<?php

namespace ThemeIsle\WPFullPay\Checkout;

use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Forms\Models\CheckoutPaymentFormModel;
use ThemeIsle\WPFullPay\Forms\Models\CheckoutSubscriptionFormModel;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Mailer\Mailer;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Pricing\RecoveryFee;
use ThemeIsle\WPFullPay\Results\ChargeResult;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\ThankYou\ThankYouAddOn;
use ThemeIsle\WPFullPay\TransactionData\PaymentTransactionData;
use ThemeIsle\WPFullPay\TransactionData\TransactionDataService;
use ThemeIsle\WPFullPay\Webhooks\EventHandler;

abstract class CheckoutChargeHandler
{
    use ThankYouAddOn;
    use LoggerAddOn;
    use StaticContextAddOn;

    /**
     * @var Database
     */
    protected $db;
    /**
     * @var Stripe
     */
    protected $stripe;
    /**
     * @var CheckoutSubmissionService
     */
    protected $checkoutSubmissionService;
    /**
     * @var TransactionDataService
     */
    protected $transactionDataService;
    /**
     * @var Mailer
     */
    protected $mailer;
    /**
     * @var EventHandler
     */
    protected $eventHandler;
    /**
     * @var Options
     */
    protected $options;

    /**
     * CheckoutChargeHandler constructor.
     */
    public function __construct($loggerService)
    {
        $this->initLogger($loggerService, LoggerService::MODULE_CHECKOUT_SUBMISSION);
        $this->options = new Options();

        $this->initStaticContext();

        $this->db = new Database();
        $this->stripe = new Stripe(Stripe::getStripeAuthenticationToken($this->staticContext), $this->loggerService);
        $this->checkoutSubmissionService = new CheckoutSubmissionService($this->loggerService);
        $this->transactionDataService = new TransactionDataService();
        $this->mailer = new Mailer($this->loggerService);
        $this->eventHandler = new EventHandler(
            $this->db,
            $this->mailer,
            $this->loggerService
        );
    }

    /**
     * Records the recovery fee and the total the completed session charged, rather than the form's fee settings.
     *
     * @param PaymentTransactionData $transactionData
     * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession Retrieved with its line items and their prices' products.
     */
    protected function setChargedRecoveryFee($transactionData, $checkoutSession): void
    {
        $transactionData->setRecoveryFee(RecoveryFee::getFeeOfCheckoutSession($checkoutSession));
        if (isset($checkoutSession->amount_total)) {
            $transactionData->setChargedTotal((int) $checkoutSession->amount_total);
        }
    }

    /**
     * @param CheckoutPaymentFormModel|CheckoutSubscriptionFormModel $formModel
     * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession
     *
     * @return ChargeResult
     */
    public abstract function handle($formModel, $checkoutSession);

    /**
     * @param $stripeCustomer \StripeWPFS\Stripe\Customer
     * @param $paymentMethod \StripeWPFS\Stripe\PaymentMethod
     */
    protected function setBillingAddress(&$stripeCustomer, &$paymentMethod)
    {
        if(isset($stripeCustomer) && isset($paymentMethod)) {
            $stripeCustomer->name = $paymentMethod->billing_details->name;

            if (isset($paymentMethod->billing_details->address)) {
                $stripeCustomer->address = $paymentMethod->billing_details->address;
            }
        }
    }

    /**
     * @param $stripeCustomer \StripeWPFS\Stripe\Customer
     * @param $checkoutSession \StripeWPFS\Stripe\Checkout\Session
     */
    protected function setShippingAddress(&$stripeCustomer, &$checkoutSession)
    {
        if (isset($checkoutSession->shipping->address)) {
            $shipping = [
                'name' => $checkoutSession->shipping->name,
                'address' => $checkoutSession->shipping->address
            ];

            $stripeCustomer->shipping = $shipping;
        }
    }

    /**
     * @param \StripeWPFS\Stripe\Customer $stripeCustomer
     * @param \StripeWPFS\Stripe\PaymentMethod|null $paymentMethod None for a trial or a payment that costs nothing.
     * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession
     *
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    protected function fixCustomerNamesAndAddresses(&$stripeCustomer, &$paymentMethod, &$checkoutSession)
    {
        $this->setBillingAddress($stripeCustomer, $paymentMethod);
        $this->setShippingAddress($stripeCustomer, $checkoutSession);
        $this->stripe->updateCustomer($stripeCustomer);
    }
}
