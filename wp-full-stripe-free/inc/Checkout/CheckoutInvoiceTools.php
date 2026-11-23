<?php

namespace ThemeIsle\WPFullPay\Checkout;

use ThemeIsle\WPFullPay\TransactionData\DonationTransactionData;
use ThemeIsle\WPFullPay\TransactionData\PaymentTransactionData;
use ThemeIsle\WPFullPay\TransactionData\SubscriptionTransactionData;

trait CheckoutInvoiceTools
{
    /**
     * @param $transactionData PaymentTransactionData|DonationTransactionData|SubscriptionTransactionData
     * @param $stripeInvoice \StripeWPFS\Stripe\Invoice
     */
    protected function setTransactionDataFromInvoice(&$transactionData, $invoice)
    {
        $transactionData->setStripeInvoiceId($invoice->id);
        $transactionData->setInvoiceUrl($invoice->invoice_pdf);
        $transactionData->setInvoiceNumber($invoice->number);
    }
}
