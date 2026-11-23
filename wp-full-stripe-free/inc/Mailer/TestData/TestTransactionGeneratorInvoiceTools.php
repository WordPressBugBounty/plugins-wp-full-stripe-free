<?php

namespace ThemeIsle\WPFullPay\Mailer\TestData;

use ThemeIsle\WPFullPay\TransactionData\DonationTransactionData;
use ThemeIsle\WPFullPay\TransactionData\OneTimePaymentTransactionData;
use ThemeIsle\WPFullPay\TransactionData\SubscriptionTransactionData;

trait TestTransactionGeneratorInvoiceTools {
    /**
     * @param OneTimePaymentTransactionData|SubscriptionTransactionData|DonationTransactionData $result
     * @noinspection PhpUndefinedClassConstantInspection
     */
    protected function setInvoiceFields( $result ) {
        $result->setInvoiceNumber( self::INVOICE_NUMBER );
        $result->setInvoiceUrl( self::INVOICE_URL );
        $result->setReceiptUrl( self::RECEIPT_URL );
    }
}
