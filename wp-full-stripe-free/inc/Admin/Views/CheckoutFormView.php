<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;

trait CheckoutFormView {
    /** @var Control */
    protected $checkoutLanguage;
    protected $openButtonLabel;

    protected function initCheckoutFields( $formHash ) {
        $this->openButtonLabel = ControlUtils::createControl( $this->formHash, CheckoutFormViewConstants::FIELD_FORM_OPEN_BUTTON_LABEL, null, null,
            __( 'Open form button label', 'wp-full-stripe-free' ), null );
        $this->openButtonLabel->setAttributes( [
            'type'      => 'text',
            'class'     => 'wpfs-form-control'
        ]);

        $this->checkoutLanguage = ControlUtils::createControl( $formHash, CheckoutFormViewConstants::FIELD_FORM_CHECKOUT_LANGUAGE, null, null,
            __( 'Checkout form language', 'wp-full-stripe-free' ), null );
        $this->checkoutLanguage->setAttributes( [
            'class'     => 'js-combobox'
        ]);
    }

    /**
     * @return array
     */
    public static function getCheckoutFields() {
        $fields = [
            CheckoutFormViewConstants::FIELD_FORM_OPEN_BUTTON_LABEL => ControlUtils::input( CheckoutFormViewConstants::FIELD_FORM_OPEN_BUTTON_LABEL ),
            CheckoutFormViewConstants::FIELD_FORM_CHECKOUT_LANGUAGE => ControlUtils::input( CheckoutFormViewConstants::FIELD_FORM_CHECKOUT_LANGUAGE )
        ];

        return $fields;
    }

    /**
     * @return Control
     */
    public function checkoutLanguage(): Control {
        return $this->checkoutLanguage;
    }

    /**
     * @return Control
     */
    public function openButtonLabel(): Control {
        return $this->openButtonLabel;
    }
}
