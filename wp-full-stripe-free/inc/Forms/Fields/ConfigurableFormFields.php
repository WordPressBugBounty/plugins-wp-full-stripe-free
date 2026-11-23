<?php

namespace ThemeIsle\WPFullPay\Forms\Fields;

use ThemeIsle\WPFullPay\Main;

abstract class ConfigurableFormFields {
    const FIELD_EMAIL = 'email';
    const FIELD_CUSTOMER_ID = 'customerId';
    const FIELD_CARDHOLDERS_NAME = 'cardholdersName';
    const FIELD_CUSTOM_PREFIX = 'customField';
    const FIELD_BILLING_NAME = 'billingName';
    const FIELD_BILLING_ADDRESS = 'billingAddress';
    const FIELD_BILLING_ADDRESS2 = 'billingAddress2';
    const FIELD_BILLING_CITY = 'billingCity';
    const FIELD_BILLING_ZIP = 'billingZip';
    const FIELD_BILLING_STATE = 'billingState';
    const FIELD_BILLING_COUNTRY_CODE = 'billingCountryCode';
    const FIELD_SHIPPING_NAME = 'shippingName';
    const FIELD_SHIPPING_ADDRESS = 'shippingAddress';
    const FIELD_SHIPPING_ADDRESS2 = 'shippingAddress2';
    const FIELD_SHIPPING_CITY = 'shippingCity';
    const FIELD_SHIPPING_ZIP = 'shippingZip';
    const FIELD_SHIPPING_STATE = 'shippingState';
    const FIELD_SHIPPING_COUNTRY_CODE = 'shippingCountryCode';
    const FIELD_PRICE = 'price';
    const FIELD_COUPON = 'coupon';
    const FIELD_AMOUNT = 'amount';

    public static function generateFieldConfiguration( $configurableFields ) {
        $result = [];

        foreach ( $configurableFields as $fieldName => $isConfigurable ) {
            $fieldConfig = new FormFieldConfiguration( $fieldName, $isConfigurable, false, null );
            $result[ $fieldName ] = $fieldConfig;
        }

        return $result;
    }

    public static function addUrlParamsToConfiguration( $fieldConfiguration, $urlParams ) {
        $result = $fieldConfiguration;

        foreach ( $fieldConfiguration as $fieldName => $config ) {
            /** @var FormFieldConfiguration $config */
            if ( array_key_exists( $fieldName, $urlParams ) ) {
                $config->setValue( $urlParams[ $fieldName ] );
                $result[ $fieldName ] = $config;
            }
        }

        return $result;
    }

    protected function getCommonFields() : array {
        $result = [];

        for ( $idx = 0; $idx < Main::DEFAULT_CUSTOM_INPUT_FIELD_MAX_COUNT; $idx++ ) {
            $result[] = self::FIELD_CUSTOM_PREFIX . ($idx + 1);
        }

        $result = array_merge( $result, [
            ConfigurableFormFields::FIELD_EMAIL,

            ConfigurableFormFields::FIELD_BILLING_NAME,
            ConfigurableFormFields::FIELD_BILLING_ADDRESS,
            ConfigurableFormFields::FIELD_BILLING_ADDRESS2,
            ConfigurableFormFields::FIELD_BILLING_ZIP,
            ConfigurableFormFields::FIELD_BILLING_CITY,
            ConfigurableFormFields::FIELD_BILLING_STATE,
            ConfigurableFormFields::FIELD_BILLING_COUNTRY_CODE,

            ConfigurableFormFields::FIELD_SHIPPING_NAME,
            ConfigurableFormFields::FIELD_SHIPPING_ADDRESS,
            ConfigurableFormFields::FIELD_SHIPPING_ADDRESS2,
            ConfigurableFormFields::FIELD_SHIPPING_ZIP,
            ConfigurableFormFields::FIELD_SHIPPING_CITY,
            ConfigurableFormFields::FIELD_SHIPPING_STATE,
            ConfigurableFormFields::FIELD_SHIPPING_COUNTRY_CODE,
        ]);

        return $result;
    }

    /**
     * @param $fields
     * @return array
     */
    protected function generateFalseArray( $fields ) : array {
        $result = [];

        foreach( $fields as $field ) {
            $result[ $field ] = false;
        }

        return $result;
    }
}
