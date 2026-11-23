<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Main;

class CheckoutPaymentFormView extends PaymentFormView implements CheckoutPaymentFormViewConstants  {
    use CheckoutFormView;
    use CheckoutPhoneViewAddOn;

    /** @var Control */
    protected $checkoutProductName;
    /** @var Control */
    protected $checkoutProductDescription;

    public function __construct() {
        $this->formHash = AdminViewConstants::FORM_HASH_ADMIN_CHECKOUT_PAYMENT_FORM;
        parent::__construct();
        $this->action->setValue( self::FIELD_ACTION_VALUE_SAVE_CHECKOUT_PAYMENT_FORM );

        $this->initCheckoutFields( $this->formHash );
        $this->initCheckoutPaymentFields( );
        $this->initCheckoutPhoneFields( $this->formHash );
        $this->initPaymentDetailField();
    }

    protected function initCheckoutPaymentFields() {
        $this->checkoutProductName = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_CHECKOUT_PRODUCT_NAME, null, null,
            /* translators: When a custom amount is entered on the form, this is the name which is used for the line item  */
            __( 'Custom product name', 'wp-full-stripe-free' ), null );
        $this->checkoutProductName->setAttributes( [
            'class'     => 'wpfs-form-control',
            'type'      => 'text'
        ]);

        $this->checkoutProductDescription = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_CHECKOUT_PRODUCT_DESCRIPTION, null, null,
            /* translators: When a custom amount is entered on the form, this is the description which is used for the line item  */
            __( 'Custom product description', 'wp-full-stripe-free' ), null );
        $this->checkoutProductDescription->setAttributes( [
            'class'     => 'wpfs-form-control',
            'type'      => 'text'
        ]);
    }

    public static function getFields() {
        $checkoutFields = self::getCheckoutFields();

        $fields = [
            self::FIELD_FORM_CHECKOUT_PRODUCT_NAME        => ControlUtils::input( self::FIELD_FORM_CHECKOUT_PRODUCT_NAME ),
            self::FIELD_FORM_CHECKOUT_PRODUCT_DESCRIPTION  => ControlUtils::input( self::FIELD_FORM_CHECKOUT_PRODUCT_DESCRIPTION ),
        ];

        return array_merge( $fields, $checkoutFields, parent::getFields() );
    }

    protected function getFormAttributes() {
        $attributes = [];

        $attributes[ self::ATTR_ID ]                  = self::FIELD_ACTION_VALUE_SAVE_CHECKOUT_PAYMENT_FORM;
        $attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_ADMIN_CHECKOUT_PAYMENT_FORM;
        $attributes                                   = array_merge( $attributes, parent::getFormAttributes() );

        return $attributes;
    }

    /**
     * @return Control
     */
    public function checkoutProductName(): Control {
        return $this->checkoutProductName;
    }

    /**
     * @return Control
     */
    public function checkoutProductDescription(): Control {
        return $this->checkoutProductDescription;
    }
}
