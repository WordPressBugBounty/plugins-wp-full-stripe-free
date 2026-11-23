<?php

namespace ThemeIsle\WPFullPay\Macros;

use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Utils;

abstract class FormMacroReplacer {
    use MacroReplacerAddOn;
    use LoggerAddOn;
    use StaticContextAddOn;

    /** @var \stdClass */
    protected $form;

    /** @var Options */
    protected $options;

    function __construct( $form, $transactionData, $loggerService ) {
        $this->initLogger( $loggerService, LoggerService::MODULE_RUNTIME );
        $this->options = new Options();

        $this->initStaticContext();

        $this->form             = $form;
        $this->transactionData  = $transactionData;

        $this->rawKeyValuePairs = [];
        $this->decoratedKeyValuePairs = [];

        $this->initRawKeyValuePairs();
        $this->initDecoratedKeyValuePairs();
    }

    public static function getMacroKeys() {
        return [
            '%NAME%',
            '%CUSTOMERNAME%',
            '%CUSTOMER_EMAIL%',
            '%BILLING_NAME%',
            '%ADDRESS1%',
            '%ADDRESS2%',
            '%CITY%',
            '%STATE%',
            '%COUNTRY%',
            '%COUNTRY_CODE%',
            '%ZIP%',
            '%SHIPPING_NAME%',
            '%SHIPPING_ADDRESS1%',
            '%SHIPPING_ADDRESS2%',
            '%SHIPPING_CITY%',
            '%SHIPPING_STATE%',
            '%SHIPPING_COUNTRY%',
            '%SHIPPING_COUNTRY_CODE%',
            '%SHIPPING_ZIP%',
            '%DATE%',
            '%FORM_NAME%',
            '%STRIPE_CUSTOMER_ID%',
            '%TRANSACTION_ID%',
            '%CUSTOMFIELD1%',
            '%IP_ADDRESS%'
        ];
    }

    private function getKeyValuePairs() {
        $siteTitle      = get_bloginfo( 'name' );
        $dateFormat     = get_option( 'date_format' );
        $billingAddress = $this->transactionData->getBillingAddress();
        $shippingAddress = $this->transactionData->getShippingAddress();

        $keyValuePairs = [
            '%NAME%'                  => $siteTitle,
            '%CUSTOMERNAME%'          => $this->transactionData->getCustomerName(),
            '%CUSTOMER_EMAIL%'        => $this->transactionData->getCustomerEmail(),
            '%BILLING_NAME%'          => $this->transactionData->getBillingName(),
            '%ADDRESS1%'              => is_null( $billingAddress ) ? null : $billingAddress['line1'],
            '%ADDRESS2%'              => is_null( $billingAddress ) ? null : $billingAddress['line2'],
            '%CITY%'                  => is_null( $billingAddress ) ? null : $billingAddress['city'],
            '%STATE%'                 => is_null( $billingAddress ) ? null : $billingAddress['state'],
            '%COUNTRY%'               => is_null( $billingAddress ) ? null : $billingAddress['country'],
            '%COUNTRY_CODE%'          => is_null( $billingAddress ) ? null : $billingAddress['country_code'],
            '%ZIP%'                   => is_null( $billingAddress ) ? null : $billingAddress['zip'],
            '%SHIPPING_NAME%'         => $this->transactionData->getShippingName(),
            '%SHIPPING_ADDRESS1%'     => is_null( $shippingAddress ) ? null : $shippingAddress['line1'],
            '%SHIPPING_ADDRESS2%'     => is_null( $shippingAddress ) ? null : $shippingAddress['line2'],
            '%SHIPPING_CITY%'         => is_null( $shippingAddress ) ? null : $shippingAddress['city'],
            '%SHIPPING_STATE%'        => is_null( $shippingAddress ) ? null : $shippingAddress['state'],
            '%SHIPPING_COUNTRY%'      => is_null( $shippingAddress ) ? null : $shippingAddress['country'],
            '%SHIPPING_COUNTRY_CODE%' => is_null( $shippingAddress ) ? null : $shippingAddress['country_code'],
            '%SHIPPING_ZIP%'          => is_null( $shippingAddress ) ? null : $shippingAddress['zip'],
            '%DATE%'                  => Utils::formatTimestampWithWordpressDateFormat( time() ),
            '%FORM_NAME%'             => $this->transactionData->getFormName(),
            '%STRIPE_CUSTOMER_ID%'    => $this->transactionData->getStripeCustomerId(),
            '%TRANSACTION_ID%'        => $this->transactionData->getTransactionId(),
            '%IP_ADDRESS%'            => $this->transactionData->getIpAddress()
        ];

        $customInputKeyValuePairs = $this->getCustomInputKeyValuePairs();

        return array_merge( $keyValuePairs, $customInputKeyValuePairs );
    }

    protected function initRawKeyValuePairs() {
        $this->rawKeyValuePairs = array_merge( $this->rawKeyValuePairs, $this->getKeyValuePairs() );
    }

    protected function initDecoratedKeyValuePairs() {
        $this->decoratedKeyValuePairs = array_merge( $this->decoratedKeyValuePairs, $this->getKeyValuePairs() );
    }

    private function getCustomInputKeyValuePairs() {
        $keyValuePairs            = [];
        $customInputFieldMaxCount = Main::getCustomFieldMaxCount( $this->staticContext );
        $displayValues            = $this->getCustomInputDisplayValues();

        for ( $idx = 0; $idx < $customInputFieldMaxCount; $idx++ ) {
            $key   = "%CUSTOMFIELD" . ( $idx + 1 ) . "%";
            $value = isset( $displayValues[ $idx ] ) ? $displayValues[ $idx ] : '';

            $keyValuePairs = array_merge( $keyValuePairs, [ $key => $value ] );
        }

        return $keyValuePairs;
    }

    /**
     * Ordered, human-facing values for the interactive custom fields.
     *
     * Prefers the type-aware transaction snapshot (display values, Yes/No,
     * comma-joined arrays) and falls back to the legacy positional values.
     *
     * @return array<int, string>
     */
    private function getCustomInputDisplayValues() {
        $values       = [];
        $snapshotJSON = method_exists( $this->transactionData, 'getCustomFieldsJSON' )
            ? $this->transactionData->getCustomFieldsJSON()
            : null;

        if ( ! empty( $snapshotJSON ) ) {
            $snapshot = json_decode( $snapshotJSON, true );
            if ( is_array( $snapshot ) ) {
                foreach ( $snapshot as $field ) {
                    if ( ! is_array( $field ) ) {
                        continue;
                    }
                    if ( isset( $field['type'] ) && 'html' === $field['type'] ) {
                        continue;
                    }
                    $values[] = $this->formatCustomFieldDisplayValue( $field );
                }

                return $values;
            }
        }

        // Legacy fallback: raw positional values.
        $customInputValues = $this->transactionData->getCustomInputValues();
        if ( isset( $customInputValues ) && is_array( $customInputValues ) ) {
            foreach ( $customInputValues as $value ) {
                $values[] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
            }
        } elseif ( ! empty( $customInputValues ) ) {
            $values[] = (string) $customInputValues;
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $field decoded custom field snapshot object
     *
     * @return string
     */
    private function formatCustomFieldDisplayValue( $field ) {
        $type = isset( $field['type'] ) ? $field['type'] : 'text';

        if ( 'checkbox' === $type ) {
            $value = isset( $field['value'] ) ? $field['value'] : '';

            return 'yes' === $value
                ? __( 'Yes', 'wp-full-stripe-free' )
                : __( 'No', 'wp-full-stripe-free' );
        }

        if ( array_key_exists( 'displayValue', $field ) ) {
            $display = $field['displayValue'];
        } else {
            $display = isset( $field['value'] ) ? $field['value'] : '';
        }

        return is_array( $display ) ? implode( ', ', $display ) : (string) $display;
    }
}
