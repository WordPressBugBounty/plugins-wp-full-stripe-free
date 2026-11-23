<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Main;

class CheckoutSubscriptionFormView extends SubscriptionFormView implements CheckoutSubscriptionFormViewConstants  {
    use CheckoutFormView;
    use CheckoutPhoneViewAddOn;

    /** @var Control */
    protected $simpleButtonLayout;

    public function __construct() {
        $this->formHash = AdminViewConstants::FORM_HASH_ADMIN_CHECKOUT_SUBSCRIPTION_FORM;
        parent::__construct();
        $this->action->setValue( self::FIELD_ACTION_VALUE_SAVE_CHECKOUT_SUBSCRIPTION_FORM );

        $this->initCheckoutFields( $this->formHash );
        $this->initCheckoutSubscriptionFields( $this->formHash );
        $this->initCheckoutPhoneFields( $this->formHash );
        $this->initPaymentDetailField();
    }

    protected function initCheckoutSubscriptionFields( $formHash ) {
        $this->simpleButtonLayout = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_SIMPLE_BUTTON_LAYOUT, null, null,
            /* translators: When turned on, only the button to open the form is displayed, all other fields are hidden */
            __( 'Simple button layout', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->simpleButtonLayout, [
            [ 0,
                __( 'Disable', 'wp-full-stripe-free' ) ],
            [ 1,
                __( 'Enable', 'wp-full-stripe-free' ) ],
        ] );
    }

    public static function getFields() {
        $checkoutFields = self::getCheckoutFields();

        $fields = [
            self::FIELD_FORM_SIMPLE_BUTTON_LAYOUT => ControlUtils::input( self::FIELD_FORM_SIMPLE_BUTTON_LAYOUT ),
        ];

        return array_merge( $fields, $checkoutFields, parent::getFields() );
    }

    protected function getFormAttributes() {
        $attributes = [];

        $attributes[ self::ATTR_ID ]                  = self::FIELD_ACTION_VALUE_SAVE_CHECKOUT_SUBSCRIPTION_FORM;
        $attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_ADMIN_CHECKOUT_SUBSCRIPTION_FORM;
        $attributes                                   = array_merge( $attributes, parent::getFormAttributes() );

        return $attributes;
    }

    /**
     * @return Control
     */
    public function simpleButtonLayout(): Control {
        return $this->simpleButtonLayout;
    }
}
