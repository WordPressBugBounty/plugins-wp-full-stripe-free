<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\PaymentMethods;

trait FormViewTaxAddOn {
    /** @var Control */
    protected $taxType;
    /** @var Control */
    protected $taxRateType;
    /** @var Control */
    protected $taxRates;
    /** @var Control */
    protected $collectCustomerTaxId;

    protected function initTaxFields() {
        $this->initTaxRates();
        $this->initTaxType();
        $this->initTaxRateType();
    }

    protected function initTaxRates() {
        $this->taxRates = ControlUtils::createControl( $this->formHash, TaxViewConstants::FIELD_FORM_TAX_RATES, null, null,
            __( 'Tax rates', 'wp-full-stripe-free' ), null );
        $this->taxRates->setAttributes( [
            'type'      => 'hidden',
        ]);
    }

    protected function initPaymentMethod(){
        $this->paymentMethod = ControlUtils::createControl( $this->formHash,
            PaymentMethodViewConstants::FIELD_FORM_PAYMENT_METHOD, null, null,
            __( 'Payment Methods', 'wp-full-stripe-free' ), null );

        $optionIndex = 0;
        $options = [];

        $paymentMethods = PaymentMethods::get_payment_methods();

        foreach ($paymentMethods as $paymentMethod) {
            $option = ControlUtils::createControl( $this->formHash,
                PaymentMethodViewConstants::FIELD_FORM_PAYMENT_METHOD, null, null,
                $paymentMethod['longTitle'], $optionIndex );
            $option->setValue( $paymentMethod['id'] );
            $optionAttributes = [
                'type'      => 'checkbox',
                'class'     => 'wpfs-form-check-input'
            ];
            $option->setAttributes( $optionAttributes );
            $option->setMetadata($paymentMethod);
            array_push( $options, $option );
            $optionIndex++;
        }
        // sort the options alphabetically by label
        usort($options, function($a, $b) {
            return strcmp($a->getMetadata('id'), $b->getMetadata('id'));
        });
        $this->paymentMethod->setOptions( $options );
    }

    protected function initTaxType() {
        $this->taxType = ControlUtils::createControl( $this->formHash, TaxViewConstants::FIELD_FORM_TAX_TYPE, null, null,
            __( 'Tax calculation method', 'wp-full-stripe-free' ), null );

        $optionIndex = 0;
        $options = [];

        $option = ControlUtils::createControl( $this->formHash, TaxViewConstants::FIELD_FORM_TAX_TYPE, null, null,
            /* translators: It means no tax is applied to the purchased goods */
            __( 'No tax', 'wp-full-stripe-free' ), $optionIndex );
        $option->setValue(Main::FIELD_VALUE_TAX_RATE_NO_TAX);
        $optionAttributes = [
            'type'      => 'radio',
            'class'     => 'wpfs-form-check-input'
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );
        $optionIndex++;

        $option = ControlUtils::createControl( $this->formHash, TaxViewConstants::FIELD_FORM_TAX_TYPE, null, null,
            /* translators: It means that the tax rates are automatically calculated by Stripe Tax */
            __( 'Stripe auto tax', 'wp-full-stripe-free' ), $optionIndex );
        $option->setValue(Main::FIELD_VALUE_TAX_RATE_STRIPE_TAX);
        $optionAttributes = [
            'type'      => 'radio',
            'class'     => 'wpfs-form-check-input'
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );
        $optionIndex++;

        $option = ControlUtils::createControl( $this->formHash, TaxViewConstants::FIELD_FORM_TAX_TYPE, null, null,
            /* translators: It means that the applied tax rates are based on tax rates added manually by the user */
            __( 'Stripe tax rates', 'wp-full-stripe-free' ), $optionIndex );
        $option->setValue(Main::FIELD_VALUE_TAX_RATE_TAX_RATES);
        $optionAttributes = [
            'type'      => 'radio',
            'class'     => 'wpfs-form-check-input'
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );

        $this->taxType->setOptions( $options );
    }

    protected function initTaxRateType() {
        $this->taxRateType = ControlUtils::createControl( $this->formHash, TaxViewConstants::FIELD_FORM_TAX_RATE_TYPE, null, null,
            __( 'Tax rate type', 'wp-full-stripe-free' ), null );

        $optionIndex = 0;
        $options = [];

        $option = ControlUtils::createControl( $this->formHash, TaxViewConstants::FIELD_FORM_TAX_RATE_TYPE, null, null,
            /* translators: It means that the applied tax rates are preselected, and they don't vary by country and state */
            __( 'Fixed rate', 'wp-full-stripe-free' ), $optionIndex );
        $option->setValue(Main::FIELD_VALUE_TAX_RATE_FIXED);
        $optionAttributes = [
            'type'      => 'radio',
            'class'     => 'wpfs-form-check-input'
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );
        $optionIndex++;

        $option = ControlUtils::createControl( $this->formHash, TaxViewConstants::FIELD_FORM_TAX_RATE_TYPE, null, null,
            /* translators: It means that the applied tax rates are selected dynamically depending on the country and state */
            __( 'Dynamic rate', 'wp-full-stripe-free' ), $optionIndex );
        $option->setValue(Main::FIELD_VALUE_TAX_RATE_DYNAMIC);
        $optionAttributes = [
            'type'      => 'radio',
            'class'     => 'wpfs-form-check-input'
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );

        $this->taxRateType->setOptions( $options );
    }

    protected function initCollectCustomerTaxId() {
        $this->collectCustomerTaxId = ControlUtils::createControl( $this->formHash, TaxViewConstants::FIELD_FORM_COLLECT_CUSTOMER_TAX_ID, null, null,
            /* translators: Is there a dedicated field on the form to edit the tax id or not? */
            __( "Collect customer's tax id?", 'wp-full-stripe-free' ), null );

        $optionIndex = 0;
        $options = [];

        $option = ControlUtils::createControl( $this->formHash, TaxViewConstants::FIELD_FORM_COLLECT_CUSTOMER_TAX_ID, null, null,
            __( 'No', 'wp-full-stripe-free' ), $optionIndex );
        $option->setValue( 0 );
        $optionAttributes = [
            'type'      => 'radio',
            'class'     => 'wpfs-form-check-input'
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );
        $optionIndex++;

        $option = ControlUtils::createControl( $this->formHash, TaxViewConstants::FIELD_FORM_COLLECT_CUSTOMER_TAX_ID, null, null,
            __( 'Yes', 'wp-full-stripe-free' ), $optionIndex );
        $option->setValue( 1 );
        $optionAttributes = [
            'type'      => 'radio',
            'class'     => 'wpfs-form-check-input'
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );

        $this->collectCustomerTaxId->setOptions( $options );
    }

    public function doesFormUseTaxRates( $form ) {
        return $form->vatRateType == Main::FIELD_VALUE_TAX_RATE_FIXED || $form->vatRateType == Main::FIELD_VALUE_TAX_RATE_DYNAMIC;
    }

    public function doesFormCalculateTax( $form ) {
        return $form->vatRateType !== Main::FIELD_VALUE_TAX_RATE_NO_TAX;
    }

    public static function getTaxFields() {
        return [
            TaxViewConstants::FIELD_FORM_TAX_TYPE                       => ControlUtils::input( TaxViewConstants::FIELD_FORM_TAX_TYPE ),
            TaxViewConstants::FIELD_FORM_TAX_RATE_TYPE                  => ControlUtils::input( TaxViewConstants::FIELD_FORM_TAX_RATE_TYPE ),
            TaxViewConstants::FIELD_FORM_COLLECT_CUSTOMER_TAX_ID        => ControlUtils::input( TaxViewConstants::FIELD_FORM_COLLECT_CUSTOMER_TAX_ID ),
            TaxViewConstants::FIELD_FORM_TAX_RATES_ERROR                => ControlUtils::products( TaxViewConstants::FIELD_FORM_TAX_RATES_ERROR ),
        ];
    }

    /**
     * @return Control
     */
    public function taxType(): Control {
        return $this->taxType;
    }

    /**
     * @return Control
     */
    public function taxRateType(): Control {
        return $this->taxRateType;
    }

    /**
     * @return Control
     */
    public function taxRates(): Control {
        return $this->taxRates;
    }

    /**
     * @return Control
     */
    public function collectCustomerTaxId(): Control {
        return $this->collectCustomerTaxId;
    }
}
