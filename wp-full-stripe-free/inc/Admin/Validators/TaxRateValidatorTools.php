<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\PaymentFormModel;
use ThemeIsle\WPFullPay\Admin\Models\SubscriptionFormModel;
use ThemeIsle\WPFullPay\Admin\Views\TaxViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Main;

trait TaxRateValidatorTools {
    /**
     * @param PaymentFormModel|SubscriptionFormModel $formModel
     */
    protected function isFormWithTaxRates( $formModel ) {
        return Main::FIELD_VALUE_TAX_RATE_FIXED === $formModel->getTaxType() ||
            Main::FIELD_VALUE_TAX_RATE_DYNAMIC === $formModel->getTaxType();
    }

    protected function validateTaxRates( $bindingResult, $formModel ) {
        $taxRateTypes = [
            Main::FIELD_VALUE_TAX_RATE_NO_TAX,
            Main::FIELD_VALUE_TAX_RATE_STRIPE_TAX,
            Main::FIELD_VALUE_TAX_RATE_FIXED,
            Main::FIELD_VALUE_TAX_RATE_DYNAMIC,
        ];

        if ( false === array_search( $formModel->getTaxType(), $taxRateTypes )) {
            $error =
                __( 'Please select a tax type (Tax tab)', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( Main::FIELD_VALUE_TAX_RATE_FIXED === $formModel->getTaxType() ) {
            if ( count( $formModel->getTaxRates() ) > 2 ) {
                $fieldName = TaxViewConstants::FIELD_FORM_TAX_RATES_ERROR;
                $fieldId   = TaxViewConstants::FIELD_FORM_TAX_RATES_ERROR;
                $error     = __( 'Please select at most two tax rates for the fixed rate calculation', 'wp-full-stripe-free' );

                $bindingResult->addFieldError( $fieldName, $fieldId, $error );
            }
        }

        if ( $this->isFormWithTaxRates( $formModel ) && count( $formModel->getTaxRates() ) > 0 ) {
            $containsInclusive = false;
            $containsExclusive = false;

            foreach( $formModel->getTaxRates() as $taxRate ) {
                if ( $taxRate['inclusive'] ) {
                    $containsInclusive = true;
                } else {
                    $containsExclusive = true;
                }
            }

            if ( $containsInclusive && $containsExclusive ) {
                $fieldName = TaxViewConstants::FIELD_FORM_TAX_RATES_ERROR;
                $fieldId   = TaxViewConstants::FIELD_FORM_TAX_RATES_ERROR;
                $error     = __( 'Please don\'t mix inclusive and exclusive tax rates' , 'wp-full-stripe-free' );

                $bindingResult->addFieldError( $fieldName, $fieldId, $error );
            }
        }
    }
}
