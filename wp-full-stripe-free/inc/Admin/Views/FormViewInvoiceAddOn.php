<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;

trait FormViewInvoiceAddOn {
    /** @var Control */
    protected $generateInvoice;

    protected function initGenerateInvoice() {
        $this->generateInvoice = ControlUtils::createControl( $this->formHash, InvoiceViewConstants::FIELD_FORM_GENERATE_INVOICE, null, null,
            __( 'Generate invoice', 'wp-full-stripe-free' ), null );

        $optionIndex = 0;
        $options = [];

        $option = ControlUtils::createControl( $this->formHash, InvoiceViewConstants::FIELD_FORM_GENERATE_INVOICE, null, null,
            __( 'No', 'wp-full-stripe-free' ), $optionIndex );
        $option->setValue( '0' );
        $optionAttributes = [
            'type'      => 'radio',
            'class'     => 'wpfs-form-check-input'
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );
        $optionIndex++;

        $option = ControlUtils::createControl( $this->formHash, InvoiceViewConstants::FIELD_FORM_GENERATE_INVOICE, null, null,
            __( 'Yes', 'wp-full-stripe-free' ), $optionIndex );
        $option->setValue( '1' );
        $optionAttributes = [
            'type'      => 'radio',
            'class'     => 'wpfs-form-check-input'
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );

        $this->generateInvoice->setOptions( $options );
    }

    public static function getInvoiceFields() {
        return [
            InvoiceViewConstants::FIELD_FORM_GENERATE_INVOICE => ControlUtils::input( InvoiceViewConstants::FIELD_FORM_GENERATE_INVOICE ),
        ];
    }

    /**
     * @return Control
     */
    public function generateInvoice(): Control {
        return $this->generateInvoice;
    }
}
