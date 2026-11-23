<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\StripeAccountModel;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Validators\Validator;
use ThemeIsle\WPFullPay\Main;

class StripeAccountValidator extends Validator {
    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    /**
     * @param BindingResult $bindingResult
     * @param StripeAccountModel $formModelObject
     */
    public function validate( $bindingResult, $formModelObject ) {
        $apiModes = [
            Main::STRIPE_API_MODE_TEST,
            Main::STRIPE_API_MODE_LIVE
        ];
        if ( false === array_search( $formModelObject->getApiMode(), $apiModes ) ) {
            $error =
                /* translators: Save Stripe settings validation error when no API mode is selected */
                __( 'Please select an API mode', 'wp-full-stripe-free' );

            $bindingResult->addGlobalError( $error );
        }
    }
}
