<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\CheckoutFormModel;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutFormViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Locale\Languages;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Utils;

trait CheckoutFormValidator {

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|CheckoutFormModel
     */
    protected function validateOpenButtonLabel( $bindingResult, $formModel ) {
        $fieldName = CheckoutFormViewConstants::FIELD_FORM_OPEN_BUTTON_LABEL;
        $fieldId   = Utils::generateFormElementId( $fieldName, $formModel->getFormHash() );

        if ( empty( $formModel->getOpenButtonLabel() )) {
            $error     =
                /* translators: Validation error message when the form open button label is empty */
                __( 'Please enter a button label', 'wp-full-stripe-free' );
            $bindingResult->addFieldError( $fieldName, $fieldId, $error );
        } elseif ( empty( preg_replace('/\s+/', '', $formModel->getOpenButtonLabel())) ) {
            $error  =
                /* translators: Validation error message when the form open button label doesn't contain at least one character other than whitespace */
                __( 'The button label should contain at least one character other than whitespace', 'wp-full-stripe-free' );
            $bindingResult->addFieldError( $fieldName, $fieldId, $error );
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|CheckoutFormModel
     */
    protected function validateCheckoutLanguage( $bindingResult, $formModel ) {
        if ( $formModel->getCheckoutFormLanguage() !== Main::PREFERRED_LANGUAGE_AUTO ) {
            if ( false === array_search( $formModel->getCheckoutFormLanguage(), Languages::getCheckoutLanguageCodes()) ) {
                $error =
                    __( 'Please select the checkout form language (Appearance tab)', 'wp-full-stripe-free' );
                $bindingResult->addGlobalError( $error );
            }
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param Binder $formModel
     */
    protected function validateCheckoutFields( $bindingResult, $formModel ) {
        $this->validateOpenButtonLabel( $bindingResult, $formModel );
        $this->validateCheckoutLanguage( $bindingResult, $formModel );
    }
}
