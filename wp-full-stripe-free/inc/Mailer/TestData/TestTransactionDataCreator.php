<?php

namespace ThemeIsle\WPFullPay\Mailer\TestData;

use ThemeIsle\WPFullPay\TransactionData\TransactionData;

abstract class TestTransactionDataCreator {
    const CUSTOMER_NAME = 'John Doe';

    /**
     * @param TransactionData $result
     */
    protected function setCommonFields( $result ) {
        $result->setCustomerName( self::CUSTOMER_NAME );
    }

    public abstract function create( $recipient );
}
