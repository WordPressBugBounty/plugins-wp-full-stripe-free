<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\LogsModel;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Validators\Validator;
use ThemeIsle\WPFullPay\Logging\LoggerService;

class LogsValidator extends Validator {
    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    /**
     * @param BindingResult $bindingResult
     * @param LogsModel $formModelObject
     */
    public function validate( $bindingResult, $formModelObject ) {
        $yesNoValues            = [ '0', '1' ];
        $logLevelValues = [
            LoggerService::LEVEL_ERROR,
            LoggerService::LEVEL_WARNING,
            LoggerService::LEVEL_INFO,
            LoggerService::LEVEL_DEBUG
        ];

        if ( false === array_search( $formModelObject->getLogLevel(), $logLevelValues ) ) {
            $error =
                /* translators: Validation error message when the log level is not selected */
                __( 'Please select the logging level', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModelObject->getLogToWebServer(), $yesNoValues ) ) {
            $error =
                /* translators: Validation error message when it is not selected whether the plugin should log to the webserver error log */
                __( 'Please select whether the plugin should log to the webserver error log', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModelObject->getCatchUncaughtErrors(), $yesNoValues ) ) {
            $error =
                /* translators: Validation error message when it is not selected whether the plugin should catch all uncaught errors */
                __( 'Please select whether the plugin should catch all uncaught errors', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }
    }
}
