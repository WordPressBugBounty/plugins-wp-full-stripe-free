<?php

namespace ThemeIsle\WPFullPay\Mailer\TestData;

use ThemeIsle\WPFullPay\TransactionData\OneTimePaymentTransactionData;
use ThemeIsle\WPFullPay\TransactionData\SubscriptionTransactionData;

trait TestTransactionGeneratorTaxTools {
    /**
     * @param OneTimePaymentTransactionData|SubscriptionTransactionData $result
     * @noinspection PhpUndefinedClassConstantInspection
     */
    protected function setTaxFields( $result ) {
        $result->setCustomerTaxId( self::CUSTOMER_TAX_ID );
    }
}
