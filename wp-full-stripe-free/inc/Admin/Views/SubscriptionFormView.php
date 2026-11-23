<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Main;

class SubscriptionFormView extends FormView implements SubscriptionFormViewConstants {
    use FormViewCouponAddOn;
    use FormViewTaxAddOn;

    /** @var Control */
    protected $allowSubscriptionQuantity;
    /** @var Control */
    protected $subscriptionMinimumQuantity;
    /** @var Control */
    protected $subscriptionMaximumQuantity;
    /** @var Control */
    protected $productSelectorStyle;
    /** @var Control */
    protected $recurringProducts;

    public function __construct() {
        parent::__construct();
        $this->initFields();
    }

    protected function initAllowSubscriptionQuantity() {
        $this->allowSubscriptionQuantity = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_ALLOW_SUBSCRIPTION_QUANTITY, null, null,
            __( 'Allow subscribing to plans in bulk', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->allowSubscriptionQuantity, [
            [ '0',
                __( 'No', 'wp-full-stripe-free' ) ],
            [ '1',
                __( 'Yes', 'wp-full-stripe-free' ) ],
        ] );
    }

    protected function initPlanSelectorStyle() {
        $this->productSelectorStyle = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_PLAN_SELECTOR_STYLE, null, null,
            __( 'Plan selector style', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->productSelectorStyle, [
            [ Main::SELECTOR_STYLE_RADIO_BUTTONS,
                /* translators: This style is a list of radio buttons  */
                __( 'List of products', 'wp-full-stripe-free' ),
                [
                    'description'   =>
                        __( 'Recommended if there are less than 5 options', 'wp-full-stripe-free' ),
                    'iconClass'     => 'wpfs-illu-layout-bullets'
                ] ],
            [ Main::SELECTOR_STYLE_DROPDOWN,
                /* translators: This style is a dropdown */
                __( 'Product selector dropdown', 'wp-full-stripe-free' ),
                [
                    'description'   =>
                        __( 'Recommended if there are 5 or more options', 'wp-full-stripe-free' ),
                    'iconClass'     => 'wpfs-illu-layout-top'
                ] ],
        ] );
    }

    protected function initFields() {
        $this->recurringProducts = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_RECURRING_PRODUCTS, null, null,
            __( 'Recurring products', 'wp-full-stripe-free' ), null );
        $this->recurringProducts->setAttributes( [
            'type'      => 'hidden',
        ]);

        $this->subscriptionMinimumQuantity = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_SUBSCRIPTION_MINIMUM_QUANTITY, null, null,
            __( 'MIN', 'wp-full-stripe-free' ), null );
        $this->subscriptionMinimumQuantity->setAttributes( [
            'type'      => 'text',
            'class'     => 'wpfs-input-group-form-control wpfs-input-group-form-control--center'
        ]);

        $this->subscriptionMaximumQuantity = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_SUBSCRIPTION_MAXIMUM_QUANTITY, null, null,
            __( 'MAX', 'wp-full-stripe-free' ), null );
        $this->subscriptionMaximumQuantity->setAttributes( [
            'type'      => 'text',
            'class'     => 'wpfs-input-group-form-control wpfs-input-group-form-control--center'
        ]);

        $this->initAllowSubscriptionQuantity();
        $this->initTaxFields();
        $this->initCollectCustomerTaxId();
        $this->initPlanSelectorStyle();
        $this->initCoupon();
        $this->initPaymentDetailField();
    }

    public static function getFields() {
        $addOns = new FormViewAddOns();

        $fields = [
            self::FIELD_FORM_ALLOW_SUBSCRIPTION_QUANTITY      => ControlUtils::input( self::FIELD_FORM_ALLOW_SUBSCRIPTION_QUANTITY ),
            self::FIELD_FORM_SUBSCRIPTION_MINIMUM_QUANTITY    => ControlUtils::inputGroupMinMax( self::FIELD_FORM_SUBSCRIPTION_MINIMUM_QUANTITY ),
            self::FIELD_FORM_SUBSCRIPTION_MAXIMUM_QUANTITY    => ControlUtils::inputGroupMinMax( self::FIELD_FORM_SUBSCRIPTION_MAXIMUM_QUANTITY ),
            self::FIELD_FORM_PLAN_SELECTOR_STYLE              => ControlUtils::input( self::FIELD_FORM_PLAN_SELECTOR_STYLE ),
            self::FIELD_FORM_RECURRING_PRODUCTS_ERROR         => ControlUtils::products( self::FIELD_FORM_RECURRING_PRODUCTS_ERROR ),
            self::FIELD_FORM_SHOW_PAYMENT_DETAIL              => ControlUtils::input( self::FIELD_FORM_SHOW_PAYMENT_DETAIL ),
        ];

        return array_merge( $fields,
            parent::getFields(),
            $addOns->getTaxFields(),
            $addOns->getCouponFields()
        );
    }

    /**
     * @return Control
     */
    public function allowSubscriptionQuantity(): Control {
        return $this->allowSubscriptionQuantity;
    }

    /**
     * @return Control
     */
    public function subscriptionMinimumQuantity(): Control {
        return $this->subscriptionMinimumQuantity;
    }

    /**
     * @return Control
     */
    public function subscriptionMaximumQuantity(): Control {
        return $this->subscriptionMaximumQuantity;
    }

    /**
     * @return Control
     */
    public function productSelectorStyle(): Control {
        return $this->productSelectorStyle;
    }

    /**
     * @return Control
     */
    public function recurringProducts(): Control {
        return $this->recurringProducts;
    }
}
