<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\FormsOptionsModel;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Validators\Validator;
use ThemeIsle\WPFullPay\Locale\Countries;

class FormsOptionsValidator extends Validator {
    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    /**
     * @param BindingResult $bindingResult
     * @param FormsOptionsModel $formModelObject
     */
    public function validate( $bindingResult, $formModelObject ) {
        $yesNoValues = [ '0', '1' ];
        $countries = Countries::getAvailableCountries();

        if ( ! array_key_exists( $formModelObject->getDefaultBillingCountry(), $countries ) ) {
            $error =
                /* translators: Validation error message when the default billing country is not selected */
                __( 'Please select the default billing country', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModelObject->getFillInEmail(), $yesNoValues ) ) {
            $error =
                /* translators: Validation error message when it's not specified whether the email address field on forms should be filled in for logged in users */
                __( 'Please select whether the email address should be filled in for logged in user', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModelObject->getSetFormFieldsViaUrlParameters(), $yesNoValues ) ) {
            $error =
                /* translators: Validation error message when it's not specified whether fields forms can be set via URL parameters */
                __( 'Please select whether form fields can be set via URL parameters', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        $paymentDetailValues = [ '0', '1', '2' ];
        if ( false === array_search( $formModelObject->getShowPaymentDetail(), $paymentDetailValues ) ) {
            $error =
                /* translators: Validation error message when the default payment details display mode is not selected */
                __( 'Please select how the payment details should be displayed by default', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }
    }
}
