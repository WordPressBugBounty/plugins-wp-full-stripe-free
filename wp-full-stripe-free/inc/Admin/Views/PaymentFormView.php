<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Main;

class PaymentFormView extends FormView implements PaymentFormViewConstants, TaxViewConstants, CouponViewConstants, InvoiceViewConstants {
    use FormViewCouponAddOn;
    use FormViewTaxAddOn;
    use FormViewInvoiceAddOn;

    /** @var Control */
    protected $transactionDescription;
    /** @var Control */
    protected $currency;
    /** @var Control */
    protected $paymentType;
    /** @var Control */
    protected $chargeType;
    /** @var Control */
    protected $allowCustomPaymentAmount;
    /** @var Control */
    protected $productSelectorStyle;
    /** @var Control */
    protected $onetimeProducts;
    /** @var Control */
    protected $paymentMethod;
    /** @var Control */
    protected $minimumPaymentAmount;
    /** @var Control */
    protected $minimumPaymentAmountHidden;

    public function __construct() {
        parent::__construct();
        $this->initFields();
    }

    protected function initPaymentType() {
        $this->paymentType = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_PAYMENT_TYPE, null, null,
            __( 'Payment type', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->paymentType, [
            [ Main::PAYMENT_TYPE_LIST_OF_AMOUNTS,
                /* translators: Payment type where customers can pick a product offered by the payment form  */
                __( 'List of products', 'wp-full-stripe-free' ) ],
            [ Main::PAYMENT_TYPE_CUSTOM_AMOUNT,
                /* translators: Payment type where customers can enter the amount to be paid, eg. paying invoices  */
                __( 'User defined amount', 'wp-full-stripe-free' ) ],
        ] );
    }

    protected function initChargeType() {
        $this->chargeType = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_CHARGE_TYPE, null, null,
            __( 'Charge type', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->chargeType, [
            [ Main::CHARGE_TYPE_IMMEDIATE,
                /* translators: Immediate charge type, the card is charged when the form is submitted  */
                __( 'Immediate', 'wp-full-stripe-free' ) ],
            [ Main::CHARGE_TYPE_AUTHORIZE_AND_CAPTURE,
                /* translators: Authorize and capture charge type, the payment is in a pending state when the form is submitted, it can be captured later */
                __( 'Authorize and capture', 'wp-full-stripe-free' ) ],
        ] );
    }

    protected function initProductSelectorStyle() {
        $this->productSelectorStyle = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_PRODUCT_SELECTOR_STYLE, null, null,
            __( 'Product selector style', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->productSelectorStyle, [
            [ Main::SELECTOR_STYLE_RADIO_BUTTONS,
                /* translators: This selector style is a list of radio buttons  */
                __( 'List of products', 'wp-full-stripe-free' ),
                [
                    'description'   =>
                        __( 'Recommended if there are less than 5 options', 'wp-full-stripe-free' ),
                    'iconClass'     => 'wpfs-illu-layout-bullets'
                ] ],
            [ Main::SELECTOR_STYLE_DROPDOWN,
                /* translators: This selector style is a dropdown  */
                __( 'Product selector dropdown', 'wp-full-stripe-free' ),
                [
                    'description'   =>
                        __( 'Recommended if there are 5 or more options', 'wp-full-stripe-free' ),
                    'iconClass'     => 'wpfs-illu-layout-top'
                ] ],
            [ Main::SELECTOR_STYLE_BUTTON_GROUP,
                /* translators: This selector style is a group of buttons showing only payment amounts, not product names */
                __( 'Product buttons with amount (deprecated)', 'wp-full-stripe-free' ),
                [
                    'description'   =>
                        __( 'Use donation forms instead', 'wp-full-stripe-free' ),
                    'iconClass'     => 'wpfs-illu-amount'
                ] ],
        ] );
    }

    protected function initMinimumPaymentAmount() {
        $this->minimumPaymentAmount = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_MINIMUM_PAYMENT_AMOUNT, null, null,
            /* translators: Form field label for the minimum payment amount */
            __( 'Minimum payment amount', 'wp-full-stripe-free' ), null );

        $this->minimumPaymentAmountHidden = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_MINIMUM_PAYMENT_AMOUNT_HIDDEN, null, null,
            __( 'Minimum payment amount hidden', 'wp-full-stripe-free' ), null );
        $this->minimumPaymentAmountHidden->setAttributes( [
            'type'      => 'hidden',
        ]);
    }

    protected function initFields() {
        $this->transactionDescription = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_TRANSACTION_DESCRIPTION, null, null,
            __( 'Transaction description', 'wp-full-stripe-free' ), null );
        $this->transactionDescription->setAttributes( [
            'class'     => 'wpfs-form-control js-token-target-transaction-description js-position-tracking-transaction-description',
            'rows'      => '3'
        ]);

        $this->currency = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_CURRENCY, null, null,
            __( 'Currency', 'wp-full-stripe-free' ), null );
        $this->currency->setAttributes( [
            'class'     => 'js-combobox'
        ]);

        $this->allowCustomPaymentAmount = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_ALLOW_CUSTOM_PAYMENT_AMOUNT, null, null,
            __( 'Allow custom amount to be entered', 'wp-full-stripe-free' ), null );
        $optionAttributes = [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input',
            'value'     => 1
        ];
        $this->allowCustomPaymentAmount->setAttributes( $optionAttributes );

        $this->onetimeProducts = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_ONETIME_PRODUCTS, null, null,
            __( 'One-time products', 'wp-full-stripe-free' ), null );
        $this->onetimeProducts->setAttributes( [
            'type'      => 'hidden',
        ]);
        $this->initPaymentType();
        $this->initChargeType();
        $this->initMinimumPaymentAmount();
        $this->initGenerateInvoice();
        $this->initTaxFields();
        $this->initCollectCustomerTaxId();
        $this->initProductSelectorStyle();
        $this->initCoupon();
        $this->initPaymentMethod();
        $this->initPaymentDetailField();
    }

    public static function getFields() {
        $addOns = new FormViewAddOns();

        $fields = [
            self::FIELD_FORM_TRANSACTION_DESCRIPTION        => ControlUtils::input( self::FIELD_FORM_TRANSACTION_DESCRIPTION ),
            self::FIELD_FORM_CURRENCY                       => ControlUtils::input( self::FIELD_FORM_CURRENCY ),
            self::FIELD_FORM_MINIMUM_PAYMENT_AMOUNT         => ControlUtils::inputGroup( self::FIELD_FORM_MINIMUM_PAYMENT_AMOUNT ),
            self::FIELD_FORM_PAYMENT_TYPE                   => ControlUtils::input( self::FIELD_FORM_PAYMENT_TYPE ),
            self::FIELD_FORM_CHARGE_TYPE                    => ControlUtils::input( self::FIELD_FORM_CHARGE_TYPE ),
            self::FIELD_FORM_ALLOW_CUSTOM_PAYMENT_AMOUNT    => ControlUtils::input( self::FIELD_FORM_ALLOW_CUSTOM_PAYMENT_AMOUNT ),
            self::FIELD_FORM_PRODUCT_SELECTOR_STYLE         => ControlUtils::input( self::FIELD_FORM_PRODUCT_SELECTOR_STYLE ),
            self::FIELD_FORM_ONETIME_PRODUCTS_ERROR         => ControlUtils::products( self::FIELD_FORM_ONETIME_PRODUCTS_ERROR ),
            self::FIELD_FORM_PAYMENT_METHOD                 => ControlUtils::input( self::FIELD_FORM_PAYMENT_METHOD ),
            self::FIELD_FORM_SHOW_PAYMENT_DETAIL            => ControlUtils::input( self::FIELD_FORM_SHOW_PAYMENT_DETAIL ),
        ];

        return array_merge( $fields,
            parent::getFields(),
            $addOns->getTaxFields(),
            $addOns->getInvoiceFields(),
            $addOns->getCouponFields()
        );
    }

    /**
     * @return Control
     */
    public function transactionDescription(): Control {
        return $this->transactionDescription;
    }

    /**
     * @return Control
     */
    public function currency(): Control {
        return $this->currency;
    }

    /**
     * @return Control
     */
    public function paymentType(): Control {
        return $this->paymentType;
    }

    /**
     * @return Control
     */
    public function chargeType(): Control {
        return $this->chargeType;
    }

    /**
     * @return Control
     */
    public function allowCustomPaymentAmount(): Control {
        return $this->allowCustomPaymentAmount;
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
    public function onetimeProducts(): Control {
        return $this->onetimeProducts;
    }

    /**
     * @return Control
     */
    public function getPaymentMethod(): Control {
        return $this->paymentMethod;
    }

    /**
     * @return Control
     */
    public function minimumPaymentAmount(): Control {
        return $this->minimumPaymentAmount;
    }

    /**
     * @return Control
     */
    public function minimumPaymentAmountHidden(): Control {
        return $this->minimumPaymentAmountHidden;
    }
}
