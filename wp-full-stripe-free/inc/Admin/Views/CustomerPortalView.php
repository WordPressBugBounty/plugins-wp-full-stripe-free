<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Main;

class CustomerPortalView extends AdminView implements CustomerPortalViewConstants {
    /** @var Control */
    protected $useStripeCustomerPortal;

    /** @var Control */
    protected $showSubscriptions;
    /** @var Control */
    protected $cancelSubscriptions;
    /** @var Control */
    protected $whenCancelSubscriptions;
    /** @var Control */
    protected $updowngradeSubscriptions;
    /** @var Control */
    protected $showInvoices;
    /** @var Control */
    protected $scrollingPaneIntoView;

    /** @var Control */
    protected $customersCanManage;
    /** @var Control */
    protected $subscriptionSettings;
    /** @var Control */
    protected $displaySettings;

    /**
     * CustomerPortalView constructor.
     */
    public function __construct() {
        $this->formHash = AdminViewConstants::FORM_HASH_ADMIN_CUSTOMER_PORTAL;
        parent::__construct();

        $this->action->setValue( CustomerPortalViewConstants::FIELD_ACTION_VALUE_SAVE_CUSTOMER_PORTAL );

        $this->initStripePortalSettings();
        $this->initCustomersCanManage();
        $this->initSubscriptionSettings();
        $this->initDisplaySettings();
    }

    protected function initStripePortalSettings() {
        $this->useStripeCustomerPortal = ControlUtils::createControl( $this->formHash, self::FIELD_MY_ACCOUNT_STRIPE_PORTAL_SETTINGS, null, null,
            __( 'Use Stripe Customer Portal', 'wp-full-stripe-free' ), null );

        $optionIndex = 0;
        $options = [];

        $option = ControlUtils::createControl( $this->formHash, self::FIELD_MY_ACCOUNT_USE_STRIPE_CUSTOMER_PORTAL, null, null,
            __('Use Stripe Customer Portal', 'wp-full-stripe-free'), $optionIndex );
        $option->setValue( 1 );
        $optionAttributes = [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input',
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );

        $this->useStripeCustomerPortal->setOptions( $options );
    }

    protected function initDisplaySettings() {
        $this->displaySettings = ControlUtils::createControl( $this->formHash, self::FIELD_MY_ACCOUNT_DISPLAY_SETTINGS, null, null,
            __( 'Display settings', 'wp-full-stripe-free' ), null );

        $optionIndex = 0;
        $options = [];

        $option = ControlUtils::createControl( $this->formHash, self::FIELD_MY_ACCOUNT_SCROLLING_PANE_INTO_VIEW, null, null,
            __('Scroll pane into view', 'wp-full-stripe-free'), $optionIndex );
        $option->setValue( 1 );
        $optionAttributes = [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input',
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );

        $this->displaySettings->setOptions( $options );
    }

    protected function initSubscriptionSettings() {
        $this->subscriptionSettings = ControlUtils::createControl( $this->formHash, self::FIELD_MY_ACCOUNT_SUBSCRIPTION_SETTINGS, null, null,
            __( 'Subscription settings', 'wp-full-stripe-free' ), null );

        $optionIndex = 0;
        $options = [];

        $option = ControlUtils::createControl( $this->formHash, self::FIELD_MY_ACCOUNT_UPDOWNGRADE_SUBSCRIPTIONS, null, null,
            __('Customers can upgrade/downgrade subscriptions', 'wp-full-stripe-free'), $optionIndex );
        $option->setValue( 1 );
        $optionAttributes = [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input',
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );
        $optionIndex++;

        $option = ControlUtils::createControl( $this->formHash, self::FIELD_MY_ACCOUNT_CANCEL_SUBSCRIPTIONS, null, null,
            __('Customers can cancel subscriptions', 'wp-full-stripe-free'), $optionIndex );
        $option->setValue( 1 );
        $optionAttributes = [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input js-cancel-subscriptions',
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );

        $this->subscriptionSettings->setOptions( $options );

        $this->whenCancelSubscriptions = ControlUtils::createControl( $this->formHash, CustomerPortalViewConstants::FIELD_MY_ACCOUNT_WHEN_CANCEL_SUBSCRIPTONS, null, null,
            /* translators: Form field label for the 'When to cancel subscriptions' option of the form */
            __( 'When to cancel subscriptions', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->whenCancelSubscriptions, [
            [ Main::CANCEL_SUBSCRIPTION_IMMEDIATELY,
                /* translators: Subscription cancellation type when the subscription is canceled immediately */
                __( 'Immediately', 'wp-full-stripe-free' ) ],
            [ Main::CANCEL_SUBSCRIPTION_AT_PERIOD_END,
                /* translators: Subscription cancellation type when the subscription is canceled at the end of the current recurring period */
                __( 'At period end', 'wp-full-stripe-free' ) ],
        ] );
    }

    protected function initCustomersCanManage() {
        $this->customersCanManage = ControlUtils::createControl( $this->formHash, self::FIELD_MY_ACCOUNT_CUSTOMERS_CAN_VIEW, null, null,
            __( 'Customers can manage', 'wp-full-stripe-free' ), null );

        $optionIndex = 0;
        $options = [];

        $option = ControlUtils::createControl( $this->formHash, self::FIELD_MY_ACCOUNT_SHOW_SUBSCRIPTIONS, null, null,
            __('Subscriptions', 'wp-full-stripe-free'), $optionIndex );
        $option->setValue( 1 );
        $optionAttributes = [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input js-show-subscriptions',
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );
        $optionIndex++;

        $option = ControlUtils::createControl( $this->formHash, self::FIELD_MY_ACCOUNT_SHOW_INVOICES, null, null,
            __('Invoices', 'wp-full-stripe-free'), $optionIndex );
        $option->setValue( 1 );
        $optionAttributes = [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input',
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );

        $this->customersCanManage->setOptions( $options );
    }

    /**
     * @return array
     */
    public static function getFields() {
        $fields = [
            CustomerPortalViewConstants::FIELD_MY_ACCOUNT_USE_STRIPE_CUSTOMER_PORTAL   => ControlUtils::input( CustomerPortalViewConstants::FIELD_MY_ACCOUNT_USE_STRIPE_CUSTOMER_PORTAL ),
            CustomerPortalViewConstants::FIELD_MY_ACCOUNT_SHOW_SUBSCRIPTIONS           => ControlUtils::input( CustomerPortalViewConstants::FIELD_MY_ACCOUNT_SHOW_SUBSCRIPTIONS ),
            CustomerPortalViewConstants::FIELD_MY_ACCOUNT_CANCEL_SUBSCRIPTIONS         => ControlUtils::input( CustomerPortalViewConstants::FIELD_MY_ACCOUNT_CANCEL_SUBSCRIPTIONS ),
            CustomerPortalViewConstants::FIELD_MY_ACCOUNT_WHEN_CANCEL_SUBSCRIPTONS     => ControlUtils::input( CustomerPortalViewConstants::FIELD_MY_ACCOUNT_WHEN_CANCEL_SUBSCRIPTONS ),
            CustomerPortalViewConstants::FIELD_MY_ACCOUNT_UPDOWNGRADE_SUBSCRIPTIONS    => ControlUtils::input( CustomerPortalViewConstants::FIELD_MY_ACCOUNT_UPDOWNGRADE_SUBSCRIPTIONS ),
            CustomerPortalViewConstants::FIELD_MY_ACCOUNT_SHOW_INVOICES                => ControlUtils::input( CustomerPortalViewConstants::FIELD_MY_ACCOUNT_SHOW_INVOICES ),
            CustomerPortalViewConstants::FIELD_MY_ACCOUNT_SCROLLING_PANE_INTO_VIEW     => ControlUtils::input( CustomerPortalViewConstants::FIELD_MY_ACCOUNT_SCROLLING_PANE_INTO_VIEW )
        ];

        return array_merge( $fields, parent::getFields() );
    }

    /**
     * @return array
     */
    protected function getFormAttributes() {
        $attributes = [];

        $attributes[ self::ATTR_ID ]                  = CustomerPortalViewConstants::FIELD_ACTION_VALUE_SAVE_CUSTOMER_PORTAL;
        $attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_ADMIN_CONFIGURE_CUSTOMER_PORTAL;
        $attributes                                   = array_merge( $attributes, parent::getFormAttributes() );

        return $attributes;
    }

    /**
     * @return Control
     */
    public function useStripeCustomerPortal(): Control {
        return $this->useStripeCustomerPortal;
    }

    /**
     * @return Control
     */
    public function showSubscriptions(): Control {
        return $this->showSubscriptions;
    }

    /**
     * @return Control
     */
    public function cancelSubscriptions(): Control {
        return $this->cancelSubscriptions;
    }

    /**
     * @return Control
     */
    public function whenCancelSubscriptions(): Control {
        return $this->whenCancelSubscriptions;
    }

    /**
     * @return Control
     */
    public function updowngradeSubscriptions(): Control {
        return $this->updowngradeSubscriptions;
    }

    /**
     * @return Control
     */
    public function showInvoices(): Control {
        return $this->showInvoices;
    }

    /**
     * @return Control
     */
    public function scrollingPaneIntoView(): Control {
        return $this->scrollingPaneIntoView;
    }

    /**
     * @return Control
     */
    public function customersCanManage(): Control {
        return $this->customersCanManage;
    }

    /**
     * @return Control
     */
    public function subscriptionSettings(): Control {
        return $this->subscriptionSettings;
    }

    /**
     * @return Control
     */
    public function displaySettings(): Control {
        return $this->displaySettings;
    }
}
