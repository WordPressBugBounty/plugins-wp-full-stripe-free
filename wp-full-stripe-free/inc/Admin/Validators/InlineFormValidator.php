<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\InlineFormModel;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Locale\Languages;
use ThemeIsle\WPFullPay\Main;

trait InlineFormValidator {

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|InlineFormModel
     */
    protected function validateCardInputFieldLanguage( $bindingResult, $formModel ) {
        if ( $formModel->getCardInputFieldLanguage() !== Main::PREFERRED_LANGUAGE_AUTO ) {
            if ( false === array_search( $formModel->getCardInputFieldLanguage(), Languages::getStripeElementsLanguageCodes()) ) {
                $error =
                    __( 'Please select the card input field language (Appearance tab)', 'wp-full-stripe-free' );
                $bindingResult->addGlobalError( $error );
            }
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param Binder $formModel
     */
    protected function validateInlineFields( $bindingResult, $formModel ) {
        $this->validateCardInputFieldLanguage( $bindingResult, $formModel );
    }
}
