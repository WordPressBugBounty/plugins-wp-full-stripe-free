<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;

trait CheckoutPhoneViewAddOn {
    /** @var Control */
    protected $collectPhoneNumber;

    protected function initCheckoutPhoneFields( $formHash ) {
        $this->collectPhoneNumber = ControlUtils::createControl( $this->formHash, CheckoutPhoneViewConstants::FIELD_FORM_COLLECT_PHONE_NUMBER, null, null,
            __( 'Phone number', 'wp-full-stripe-free' ), null );
        $this->collectPhoneNumber->setValue('1' );
        $this->collectPhoneNumber->setAttributes( [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input'
        ]);
    }

    /**
     * @return Control
     */
    public function collectPhoneNumber(): Control {
        return $this->collectPhoneNumber;
    }
}
