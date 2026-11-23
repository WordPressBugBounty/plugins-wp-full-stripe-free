<?php

namespace ThemeIsle\WPFullPay\ThankYou;

use ThemeIsle\WPFullPay\Macros\DonationMacroReplacer;
use ThemeIsle\WPFullPay\Macros\OneTimePaymentMacroReplacer;
use ThemeIsle\WPFullPay\Macros\SaveCardMacroReplacer;
use ThemeIsle\WPFullPay\Macros\SubscriptionMacroReplacer;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\TransactionData\DonationTransactionData;
use ThemeIsle\WPFullPay\TransactionData\OneTimePaymentTransactionData;
use ThemeIsle\WPFullPay\TransactionData\SaveCardTransactionData;
use ThemeIsle\WPFullPay\TransactionData\SubscriptionTransactionData;

class ThankYouPostProcessorFactory {
    public static function create( $database, $transactionData, $loggerService) {
        $processor = null;

        if ($transactionData instanceof SubscriptionTransactionData) {
            $processor = new ThankYouPostProcessor( $database, $transactionData, $loggerService, Main::FORM_TYPE_SUBSCRIPTION, SubscriptionMacroReplacer::class );
        } else if ($transactionData instanceof DonationTransactionData) {
            $processor = new ThankYouPostProcessor( $database, $transactionData, $loggerService, Main::FORM_TYPE_DONATION, DonationMacroReplacer::class );
        } else if ($transactionData instanceof OneTimePaymentTransactionData) {
            $processor = new ThankYouPostProcessor( $database, $transactionData, $loggerService, Main::FORM_TYPE_PAYMENT, OneTimePaymentMacroReplacer::class );
        } else if ($transactionData instanceof SaveCardTransactionData) {
            $processor = new ThankYouPostProcessor( $database, $transactionData, $loggerService, Main::FORM_TYPE_SAVE_CARD, SaveCardMacroReplacer::class );
        } else {
            throw new \Exception("Unknown thank you postprocessor class: " . get_class($transactionData));
        }

        return $processor;
    }
}
