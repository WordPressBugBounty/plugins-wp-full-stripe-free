<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Main;

class CheckoutDonationFormView extends DonationFormView implements CheckoutDonationFormViewConstants  {
    use CheckoutFormView;
    use CheckoutPhoneViewAddOn;
    use CheckoutProductImageViewAddOn;

    /** @var Control */
    protected $checkoutProductName;
    /** @var Control */
    protected $checkoutProductDescription;

    public function __construct() {
        $this->formHash = AdminViewConstants::FORM_HASH_ADMIN_CHECKOUT_DONATION_FORM;
        parent::__construct();
        $this->action->setValue( self::FIELD_ACTION_VALUE_SAVE_CHECKOUT_DONATION_FORM );

        $this->initCheckoutFields( $this->formHash );
        $this->initCheckoutDonationFields();
        $this->initCheckoutPhoneFields( $this->formHash );
        $this->initCheckoutProductImageFields( $this->formHash );
    }

    protected function initCheckoutDonationFields() {
        $this->checkoutProductName = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_CHECKOUT_PRODUCT_NAME, null, null,
            /* translators: Name of the donation product displayed as a line item */
            __( 'Donation product name', 'wp-full-stripe-free' ), null );
        $this->checkoutProductName->setAttributes( [
            'class'     => 'wpfs-form-control',
            'type'      => 'text'
        ]);

        $this->checkoutProductDescription = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_CHECKOUT_PRODUCT_DESCRIPTION, null, null,
            /* translators: Description of the donation product displayed as descripition of the line item */
            __( 'Donation product description', 'wp-full-stripe-free' ), null );
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

        $attributes[ self::ATTR_ID ]                  = self::FIELD_ACTION_VALUE_SAVE_CHECKOUT_DONATION_FORM;
        $attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_ADMIN_CHECKOUT_DONATION_FORM;
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
    public function checkoutProductDescription(): Control
    {
        return $this->checkoutProductDescription;
    }
}
