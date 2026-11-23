<?php

namespace ThemeIsle\WPFullPay\Mailer\TestData;

use ThemeIsle\WPFullPay\TransactionData\FormTransactionData;
use ThemeIsle\WPFullPay\Utils;

abstract class TestFormTransactionDataCreator extends TestTransactionDataCreator {
    const STRIPE_CUSTOMER_ID = 'cus_FDgty569gn546NB465';
    const CUSTOMER_PHONE = '+1-562-555-0177';

    const BILLING_NAME = "John 'Billing' Doe";
    const BILLING_LINE_1 = '50553 Bartell Circles';
    const BILLING_LINE_2 = '2nd floor, C building';
    const BILLING_CITY = 'Tabithaburgh';
    const BILLING_ZIP = '31965';
    const BILLING_STATE = 'Bilzen';
    const BILLING_COUNTRY = 'United States';
    const BILLING_COUNTRY_CODE = 'US';

    const SHIPPING_NAME = "John 'Shipping' Doe";
    const SHIPPING_LINE_1 = '206 Jimmie Terrace';
    const SHIPPING_LINE_2 = 'Apt. 052';
    const SHIPPING_CITY = 'East Paulinestad';
    const SHIPPING_ZIP = '76284';
    const SHIPPING_STATE = 'Bilzen';
    const SHIPPING_COUNTRY = 'United States';
    const SHIPPING_COUNTRY_CODE = 'US';

    const IP_ADDRESS = '127.0.0.1';

    const CUSTOM_FIELD_WEBSITE_URL = 'https://paymentsplugin.com';

    const PAYMENT_METHOD_ID = 'pm_Fg56hBd56bfy8Hnbasd3';
    const PAYMENT_INTENT_ID = 'pi_XZCv3fASD4HFGd7ujNdf';

    /**
     * @param FormTransactionData $result
     */
    protected function setFormCommonFields( $result ) {
        $result->setIpAddress( self::IP_ADDRESS );
        $result->setStripeCustomerId( self::STRIPE_CUSTOMER_ID );
        $result->setCustomerPhone( self::CUSTOMER_PHONE );

        $result->setBillingName( self::BILLING_NAME );
        $result->setBillingAddress( Utils::prepareAddressData(
           self::BILLING_LINE_1,
            self::BILLING_LINE_2,
            self::BILLING_CITY,
            self::BILLING_STATE,
            self::BILLING_COUNTRY,
            self::BILLING_COUNTRY_CODE,
            self::BILLING_ZIP
        ));

        $result->setShippingName( self::SHIPPING_NAME );
        $result->setShippingAddress( Utils::prepareAddressData(
            self::SHIPPING_LINE_1,
            self::SHIPPING_LINE_2,
            self::SHIPPING_CITY,
            self::SHIPPING_STATE,
            self::SHIPPING_COUNTRY,
            self::SHIPPING_COUNTRY_CODE,
            self::SHIPPING_ZIP
        ));

        $result->setCustomInputValues( [
            self::CUSTOM_FIELD_WEBSITE_URL
        ]);

        $result->setStripePaymentMethodId( self::PAYMENT_METHOD_ID );
        $result->setStripePaymentIntentId( self::PAYMENT_INTENT_ID );
    }
}
