<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\CheckoutDonationFormModel;
use ThemeIsle\WPFullPay\Admin\Models\CheckoutPaymentFormModel;
use ThemeIsle\WPFullPay\Admin\Models\CheckoutSubscriptionFormModel;
use ThemeIsle\WPFullPay\Forms\Models\Binder;

trait CheckoutPhoneNumberValidatorAddOn {
    /**
     * @param $bindingResult
     * @param $formModelObject Binder|CheckoutPaymentFormModel|CheckoutSubscriptionFormModel|CheckoutDonationFormModel
     * @return void
     */
    protected function validateCheckoutCollectPhoneNumber( $bindingResult, $formModelObject ) {
        $yesNoValues     = [ '0', '1' ];

        if ( false === array_search( $formModelObject->getCollectPhoneNumber(), $yesNoValues ) ) {
            $error =
                /* translators: Validation error message displayed if it's not selected whether the customer's phone number should be collected  */
                __( 'Please select if the customer\'s phone number should be collected', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }
    }
}
