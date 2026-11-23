<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\MyAccountModel;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Validators\Validator;
use ThemeIsle\WPFullPay\Main;

class MyAccountValidator extends Validator {
    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    /**
     * @param BindingResult $bindingResult
     * @param MyAccountModel $formModelObject
     */
    public function validate( $bindingResult, $formModelObject ) {
        $yesNoValues     = [ '0', '1' ];
        $whenCancelValues = [
            Main::CANCEL_SUBSCRIPTION_IMMEDIATELY,
            Main::CANCEL_SUBSCRIPTION_AT_PERIOD_END
        ];

        if ( false === array_search( $formModelObject->getShowSubscriptions(), $yesNoValues ) ) {
            $error =
                /* translators: Validation error message displayed if it's not selected whether subscriptions should be shown to customers */
                __( 'Please select if customers can see their subscriptions', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModelObject->getCancelSubscriptions(), $yesNoValues ) ) {
            $error =
                /* translators: Validation error message displayed if it's not selected whether customers can cancel subscriptions */
                __( 'Please select if customers can cancel subscriptions', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModelObject->getWhenCancelSubscriptions(), $whenCancelValues ) ) {
            $error =
                /* translators: Validation error message displayed if it's not selected when subscriptions are canceled (immediately, or at the end of the term) */
                __( 'Please select when to cancel subscriptions', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModelObject->getUpdowngradeSubscriptions(), $yesNoValues ) ) {
            $error =
                /* translators: Validation error message displayed if it's not selected whether customers can upgrade/downgrade subscriptions */
                __( 'Please select if customers can upgrade/downgrade subscriptions', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModelObject->getShowInvoices(), $yesNoValues ) ) {
            $error =
                /* translators: Validation error message displayed if it's not selected whether customers can view invoices */
                __( 'Please select if customers can view invoices', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModelObject->getScrollingPaneIntoView(), $yesNoValues ) ) {
            $error =
                /* translators: Validation error message displayed if it's not selected whether scrolling pane into view is enabled or disabled */
                __( 'Please select if pane should scroll into view', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModelObject->useStripeCustomerPortal(), $yesNoValues ) ) {
            $error =
                /* translators: Validation error message displayed if it's not selected whether the Stripe Customer Portal is enabled or disabled */
                __( 'Please select if the Stripe Customer Portal should be enabled.', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }
    }
}
