<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\WordpressDashboardModel;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Validators\Validator;
use ThemeIsle\WPFullPay\Main;

class WordpressDashboardValidator extends Validator {
    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    /**
     * @param BindingResult $bindingResult
     * @param WordpressDashboardModel $formModelObject
     */
    public function validate( $bindingResult, $formModelObject ) {
        $yesNoValues            = [ '0', '1' ];
        $decimalSeparatorValues = [
            Main::DECIMAL_SEPARATOR_SYMBOL_DOT,
            Main::DECIMAL_SEPARATOR_SYMBOL_COMMA
        ];

        if ( false === array_search( $formModelObject->getDecimalSeparator(), $decimalSeparatorValues ) ) {
            $error =
                /* translators: Validation error message when the decimal separator character of numbers is not selected */
                __( 'Please select the decimal separator', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModelObject->getUseSymbolNotCode(), $yesNoValues ) ) {
            $error =
                /* translators: Validation error message when it is not selected whether currency symbols or currecny codes should be displayed */
                __( 'Please select whether currency symbols or codes should be displayed', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModelObject->getCurrencySymbolAtFirstPosition(), $yesNoValues ) ) {
            $error =
                /* translators: Validation error message when it is not selected whether currency symbols should be displayed on the left or right */
                __( 'Please select whether the currency symbol should be displayed on the left or right', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModelObject->getPutSpaceBetweenSymbolAndAmount(), $yesNoValues ) ) {
            $error =
                __( 'Please select whether space should be inserted between the currency symbol and amount', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }
    }
}
