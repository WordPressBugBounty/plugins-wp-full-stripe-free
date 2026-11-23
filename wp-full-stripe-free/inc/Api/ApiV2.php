<?php

namespace ThemeIsle\WPFullPay\Api;

use ThemeIsle\WPFullPay\Forms\Fields\FormFieldConfiguration;
use ThemeIsle\WPFullPay\Utils;

class ApiV2 extends ApiV1 {
    protected function __construct () {
        parent::__construct();
    }

    /**
     * @return ApiV1
     */
    protected static function getInstance() {
        if ( self::$instance === null ) {
            self::$instance = new ApiV2();
        }

        return self::$instance;
    }

    /**
     * @param $config
     * @param $fieldName
     * @param $isConfigurable
     * @return array
     */
    public static function setIsFormFieldConfigurable( & $config, $fieldName, $isConfigurable ) {
        if ( is_null( $config ) ||
            ! is_array( $config ) ||
            ! array_key_exists( $fieldName, $config ) ||
            ! is_bool( $isConfigurable )) {
            return $config;
        }

        $fieldConfig = $config[ $fieldName ];
        /** @var FormFieldConfiguration $fieldConfig  */
        $fieldConfig->setIsConfigurable( $isConfigurable );

        return $config;
    }

    public static function isFormFieldConfigurable( & $config, $fieldName ) {
        if ( is_null( $config ) ||
            ! is_array( $config ) ||
            ! array_key_exists( $fieldName, $config )) {
            return false;
        }

        $fieldConfig = $config[ $fieldName ];
        /** @var FormFieldConfiguration $fieldConfig  */

        return $fieldConfig->isConfigurable();
    }

    public static function setFormFieldValue( & $config, $fieldName, $value ) {
        if ( is_null( $config ) ||
            ! is_array( $config ) ||
            ! array_key_exists( $fieldName, $config )) {
            return $config;
        }

        $fieldConfig = $config[ $fieldName ];
        /** @var FormFieldConfiguration $fieldConfig  */
        $fieldConfig->setValue( $value );

        return $config;
    }

    public static function getFormFieldValue( & $config, $fieldName ) {
        if ( is_null( $config ) ||
            ! is_array( $config ) ||
            ! array_key_exists( $fieldName, $config )) {
            return false;
        }

        $fieldConfig = $config[ $fieldName ];
        /** @var FormFieldConfiguration $fieldConfig  */

        return $fieldConfig->getValue();
    }

    public static function setIsFormFieldReadonly( & $config, $fieldName, $isReadonly ) {
        if ( is_null( $config ) ||
            ! is_array( $config ) ||
            ! array_key_exists( $fieldName, $config ) ||
            ! is_bool( $isReadonly )) {
            return $config;
        }

        $fieldConfig = $config[ $fieldName ];
        /** @var FormFieldConfiguration $fieldConfig  */
        $fieldConfig->setIsReadonly( $isReadonly );

        return $config;
    }

    public static function isFormFieldReadonly( & $config, $fieldName ) {
        if ( is_null( $config ) ||
            ! is_array( $config ) ||
            ! array_key_exists( $fieldName, $config )) {
            return false;
        }

        $fieldConfig = $config[ $fieldName ];
        /** @var FormFieldConfiguration $fieldConfig  */

        return $fieldConfig->isReadonly();
    }

    /**
     * @param $stripeSubscriptionId
     * @return bool
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    public static function cancelSubscriptionById( $stripeSubscriptionId ) {
        return self::getInstance()->stripe->cancelSubscription(
            null,
            $stripeSubscriptionId,
            Utils::getCancelSubscriptionsAtPeriodEnd( self::getInstance()->staticContext )
        );
    }

}
