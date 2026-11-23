<?php

namespace ThemeIsle\WPFullPay\Macros;

use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\TransactionData\SubscriptionTransactionData;

class SubscriptionMacroReplacer extends ProductMacroReplacer {
    public static function getMacroKeys() {
        return array_merge( parent::getMacroKeys(), [
            '%SETUP_FEE%',
            '%SETUP_FEE_NET%',
            '%SETUP_FEE_GROSS%',
            '%SETUP_FEE_VAT%',
            '%SETUP_FEE_VAT_RATE%',
            '%SETUP_FEE_TOTAL%',
            '%SETUP_FEE_NET_TOTAL%',
            '%SETUP_FEE_GROSS_TOTAL%',
            '%SETUP_FEE_VAT_TOTAL%',
            '%PLAN_NAME%',
            '%PLAN_AMOUNT%',
            '%PLAN_AMOUNT_NET%',
            '%PLAN_AMOUNT_GROSS%',
            '%PLAN_AMOUNT_VAT%',
            '%PLAN_AMOUNT_VAT_RATE%',
            '%PLAN_QUANTITY%',
            '%PLAN_AMOUNT_TOTAL%',
            '%PLAN_AMOUNT_NET_TOTAL%',
            '%PLAN_AMOUNT_GROSS_TOTAL%',
            '%PLAN_AMOUNT_VAT_TOTAL%',
            '%PLAN_FUTURE_AMOUNT_NET%',
            '%PLAN_FUTURE_AMOUNT_VAT%',
            '%PLAN_FUTURE_AMOUNT_GROSS%',
            '%INVOICE_URL%',
            '%INVOICE_NUMBER%',
            '%RECEIPT_URL%',
            '%AMOUNT%',
            '%RECOVERY_FEE%',
            '%CHARGED_TOTAL%',
            '%COUPON_CODE%',
            '%CUSTOMER_TAX_ID%'
        ]);
    }

    /** Placeholders filled in with their value as it is. */
    private const UNFORMATTED_KEYS = [ '%PLAN_NAME%', '%PLAN_QUANTITY%', '%INVOICE_URL%', '%INVOICE_NUMBER%', '%RECEIPT_URL%', '%COUPON_CODE%', '%CUSTOMER_TAX_ID%' ];
    /** Placeholders formatted as a percentage; the rest are amounts in the plan's currency. */
    private const RATE_KEYS = [ '%SETUP_FEE_VAT_RATE%', '%PLAN_AMOUNT_VAT_RATE%' ];

    protected function initRawKeyValuePairs() {
        parent::initRawKeyValuePairs();

        $this->rawKeyValuePairs = array_merge( $this->rawKeyValuePairs, $this->getSubscriptionValues() );
    }

    protected function initDecoratedKeyValuePairs() {
        parent::initDecoratedKeyValuePairs();

        $stripePlanCurrency = $this->transactionData->getPlanCurrency();

        $keyValuePairs = [];
        foreach ( $this->getSubscriptionValues() as $key => $value ) {
            if ( in_array( $key, self::RATE_KEYS, true ) ) {
                $value = Currencies::formatAndEscapePercentageByForm( $this->form, $value );
            } elseif ( ! in_array( $key, self::UNFORMATTED_KEYS, true ) ) {
                $value = Currencies::formatAndEscapeByForm( $this->form, $stripePlanCurrency, $value );
            }
            $keyValuePairs[ $key ] = $value;
        }

        $this->decoratedKeyValuePairs = array_merge( $this->decoratedKeyValuePairs, $keyValuePairs );
    }

    /**
     * The subscription's raw values, in the order the formatted ones are filled in.
     *
     * @return array<string,int|float|string|null>
     */
    private function getSubscriptionValues() {
        /** @var SubscriptionTransactionData $transactionData */
        $transactionData = $this->transactionData;

        return [
            '%SETUP_FEE%'                => $transactionData->getSetupFeeGrossAmount(),
            '%SETUP_FEE_NET%'            => $transactionData->getSetupFeeNetAmount(),
            '%SETUP_FEE_GROSS%'          => $transactionData->getSetupFeeGrossAmount(),
            '%SETUP_FEE_VAT%'            => $transactionData->getSetupFeeTaxAmount(),
            '%SETUP_FEE_VAT_RATE%'       => $this->getSetupFeeTaxRate(),
            '%SETUP_FEE_TOTAL%'          => $transactionData->getSetupFeeGrossAmountTotal(),
            '%SETUP_FEE_NET_TOTAL%'      => $transactionData->getSetupFeeNetAmountTotal(),
            '%SETUP_FEE_GROSS_TOTAL%'    => $transactionData->getSetupFeeGrossAmountTotal(),
            '%SETUP_FEE_VAT_TOTAL%'      => $transactionData->getSetupFeeTaxAmountTotal(),
            '%PLAN_NAME%'                => $transactionData->getPlanName(),
            '%PLAN_AMOUNT%'              => $transactionData->getPlanGrossAmount(),
            '%PLAN_AMOUNT_NET%'          => $transactionData->getPlanNetAmount(),
            '%PLAN_AMOUNT_GROSS%'        => $transactionData->getPlanGrossAmount(),
            '%PLAN_AMOUNT_VAT%'          => $transactionData->getPlanTaxAmount(),
            '%PLAN_AMOUNT_VAT_RATE%'     => $this->getPlanTaxRate(),
            '%PLAN_QUANTITY%'            => $transactionData->getPlanQuantity(),
            '%PLAN_AMOUNT_TOTAL%'        => $transactionData->getPlanGrossAmountTotal(),
            '%PLAN_AMOUNT_NET_TOTAL%'    => $transactionData->getPlanNetAmountTotal(),
            '%PLAN_AMOUNT_GROSS_TOTAL%'  => $transactionData->getPlanGrossAmountTotal(),
            '%PLAN_AMOUNT_VAT_TOTAL%'    => $transactionData->getPlanTaxAmountTotal(),
            '%PLAN_FUTURE_AMOUNT_GROSS%' => $transactionData->getPlanFutureGrossAmount(),
            '%PLAN_FUTURE_AMOUNT_VAT%'   => $transactionData->getPlanFutureTaxAmount(),
            '%PLAN_FUTURE_AMOUNT_NET%'   => $transactionData->getPlanFutureNetAmount(),
            '%INVOICE_URL%'              => $transactionData->getInvoiceUrl(),
            '%INVOICE_NUMBER%'           => $transactionData->getInvoiceNumber(),
            '%RECEIPT_URL%'              => $transactionData->getReceiptUrl(),
            '%AMOUNT%'                   => $transactionData->getPlanGrossAmountAndGrossSetupFeeTotal(),
            '%RECOVERY_FEE%'             => $transactionData->getRecoveryFee(),
            '%CHARGED_TOTAL%'            => $transactionData->getChargedTotal(),
            '%COUPON_CODE%'              => $transactionData->getCouponCode(),
            '%CUSTOMER_TAX_ID%'          => $transactionData->getCustomerTaxId(),
        ];
    }

    /**
     * The tax charged on the setup fee as a percentage of it, whether one rate, several or Stripe Tax set it.
     *
     * @return float
     */
    private function getSetupFeeTaxRate() {
        /** @var SubscriptionTransactionData $transactionData */
        $transactionData = $this->transactionData;

        return self::resolveTaxRate( $transactionData->getPlanSetupFeeVATRate(), $transactionData->getSetupFeeNetAmountTotal(), $transactionData->getSetupFeeTaxAmountTotal() );
    }

    /**
     * @return float
     */
    private function getPlanTaxRate() {
        /** @var SubscriptionTransactionData $transactionData */
        $transactionData = $this->transactionData;

        return self::resolveTaxRate( $transactionData->getPlanAmountVATRate(), $transactionData->getPlanNetAmountTotal(), $transactionData->getPlanTaxAmountTotal() );
    }

    /**
     * The rate recorded, else one derived from the totals, which round less than the amounts per unit.
     *
     * @param float|null $recordedRate
     * @param int|float|null $netAmount
     * @param int|float|null $taxAmount
     * @return float
     */
    private static function resolveTaxRate( $recordedRate, $netAmount, $taxAmount ) {
        if ( ! is_null( $recordedRate ) ) {
            return round( $recordedRate, 2 );
        }

        return $netAmount > 0 ? round( (float) $taxAmount / $netAmount * 100, 2 ) : 0.0;
    }
}
