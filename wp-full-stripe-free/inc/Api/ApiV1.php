<?php

namespace ThemeIsle\WPFullPay\Api;

use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Mailer\Senders\GenericEmailNotificationSender;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\Utils;

class ApiV1 {
    use LoggerAddOn;
    use StaticContextAddOn;

    protected static $instance;

    /** @var Database */
    private $db = null;
    /** @var Stripe */
    protected $stripe;
    /** @var Options */
    protected $options;

    const STRIPE_API_MODE_CURRENT = 'current';
    const STRIPE_API_MODE_LIVE = 'live';
    const STRIPE_API_MODE_TEST = 'test';

    protected function __construct() {
        $this->options          = new Options();
        $this->loggerService    = new LoggerService( $this->options->get( Options::OPTION_LOG_LEVEL ), $this->options->get( Options::OPTION_LOG_TO_WEB_SERVER ) == 1 );
        $this->initLogger( $this->loggerService, LoggerService::MODULE_API );

        $this->initStaticContext();

        $this->db               = new Database();
        $this->stripe           = new Stripe( Stripe::getStripeAuthenticationToken($this->staticContext), $this->loggerService );

    }

    /**
     * @return ApiV1
     */
    protected static function getInstance() {
        if ( self::$instance === null ) {
            self::$instance = new ApiV1();
        }

        return self::$instance;
    }

    /**
     * @return string
     */
    public static function getPluginVersion() {
        return Main::VERSION;
    }

    /**
     * @param $apiMode
     * @return \StripeWPFS\Stripe\StripeClient
     * @throws \Exception
     */
    public static function getStripeClient( $apiMode = self::STRIPE_API_MODE_CURRENT ) {
        switch( $apiMode ) {
            case self::STRIPE_API_MODE_CURRENT:
                return self::getInstance()->stripe->getStripeClient();

            case self::STRIPE_API_MODE_LIVE:
            case self::STRIPE_API_MODE_TEST:
                return Stripe::createStripeClient(Stripe::getStripeAuthenticationTokenByMode(self::getInstance()->staticContext, $apiMode === self::STRIPE_API_MODE_LIVE));

            default:
                throw new \Exception( __CLASS__ . "." . __FUNCTION__ . "() - Invalid API mode: " . $apiMode );
        }
    }

    /**
     * @return array
     */
    public static function getStripeSubscriptionPlans() {
        return self::getInstance()->stripe->getSubscriptionPlans();
    }

    /**
     * @param string $subscriptionId
     * @return \StripeWPFS\Stripe\Subscription
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    public static function getStripeSubscription($subscriptionId ) {
        return self::getInstance()->stripe->retrieveSubscription($subscriptionId );
    }

    /**
     * @param $subscriptionId
     * @param $params
     * @return \StripeWPFS\Stripe\Subscription
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    public static function getStripeSubscriptionWithParams( $subscriptionId, $params ) {
        return self::getInstance()->stripe->retrieveSubscriptionWithParams( $subscriptionId, $params );
    }

    /**
     * @param $customerId
     * @return \StripeWPFS\Stripe\Customer
     */
    public static function getStripeCustomer( $customerId ) {
        return self::getInstance()->stripe->retrieveCustomer( $customerId );
    }

    /**
     * @param $customerId
     * @param $params
     * @return \StripeWPFS\Stripe\Customer
     */
    public static function getStripeCustomerWithParams( $customerId, $params ) {
        return self::getInstance()->stripe->retrieveCustomerWithParams( $customerId, $params );
    }

    /**
     * @param $params
     * @return \StripeWPFS\Stripe\Collection
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    public static function getStripeCustomersWithParams( $params )  {
        return self::getInstance()->stripe->getCustomersWithParams( $params );
    }

    /**
     * @param $planId
     * @return \StripeWPFS\Stripe\Price|null
     */
    public static function getStripePlan( $planId ) {
        return self::getInstance()->stripe->retrievePlan( $planId );
    }

    /**
     * @param $stripeCustomerId
     * @param $stripeSubscriptionId
     * @param $stripePlanId
     *
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    public static function changeSubscriptionPlan( $stripeCustomerId, $stripeSubscriptionId, $stripePlanId ) {
        $success = self::getInstance()->stripe->updateSubscriptionPlanAndQuantity( $stripeCustomerId, $stripeSubscriptionId, $stripePlanId );
        if ( $success ) {
            self::getInstance()->db->updateSubscriptionPlanByStripeSubscriptionId( $stripeSubscriptionId, $stripePlanId );
        }
    }

    /**
     * @param $stripeCustomerId
     * @param $stripeSubscriptionId
     * @return bool
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    public static function cancelSubscription( $stripeCustomerId, $stripeSubscriptionId ) {
        return self::getInstance()->stripe->cancelSubscription(
            $stripeCustomerId,
            $stripeSubscriptionId,
            Utils::getCancelSubscriptionsAtPeriodEnd( self::getInstance()->staticContext )
        );
    }

    /**
     * @param string $currency
     * @return string|null
     */
    public static function getCurrencySymbolFor( $currency ) {
        return Currencies::getCurrencySymbolFor( $currency );
    }

    /**
     * @param $emailType string
     * @param $decoratedKeyValuePairs array
     */
    public static function sendEmailByTemplate( $recipientEmail, $templateType, $decoratedKeyValuePairs ) {
        if ( Utils::isDemoMode() ) {
            return;
        }

        // The default receipts show %CHARGED_TOTAL%. Callers written before it pass %AMOUNT%, which is what they charged.
        if ( is_array( $decoratedKeyValuePairs ) && isset( $decoratedKeyValuePairs['%AMOUNT%'] ) && ! isset( $decoratedKeyValuePairs['%CHARGED_TOTAL%'] ) ) {
            $decoratedKeyValuePairs['%CHARGED_TOTAL%'] = $decoratedKeyValuePairs['%AMOUNT%'];
        }

        $sender = new GenericEmailNotificationSender( new PhonyTransactionData( $recipientEmail ), self::getInstance()->staticContext->getLoggerService() );
        $sender->setTemplateType( $templateType );
        $sender->setDecoratedKeyValuePairs( $decoratedKeyValuePairs );
        $sender->sendEmail();
    }

    /**
     * @return bool
     */
    public static function isDemoMode() {
        return Utils::isDemoMode();
    }
}
