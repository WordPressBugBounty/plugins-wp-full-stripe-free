<?php

namespace ThemeIsle\WPFullPay\Macros;

use ThemeIsle\WPFullPay\Locale\Currencies;

class OneTimePaymentMacroReplacer extends OneTimeInvolvedMacroReplacer {
    public static function getMacroKeys() {
        return array_merge( parent::getMacroKeys(), [
            '%COUPON_CODE%',
            '%CUSTOMER_TAX_ID%',
            '%PRODUCT_AMOUNT_GROSS%',
            '%PRODUCT_AMOUNT_TAX%',
            '%PRODUCT_AMOUNT_NET%',
            '%PRODUCT_AMOUNT_DISCOUNT%'
        ]);
    }

    protected function initRawKeyValuePairs() {
        parent::initRawKeyValuePairs();

        $keyValuePairs = [
            '%COUPON_CODE%'             => $this->transactionData->getCouponCode(),
            '%CUSTOMER_TAX_ID%'         => $this->transactionData->getCustomerTaxId(),
            '%PRODUCT_AMOUNT_GROSS%'    => $this->transactionData->getProductAmountGross(),
            '%PRODUCT_AMOUNT_TAX%'      => $this->transactionData->getProductAmountTax(),
            '%PRODUCT_AMOUNT_NET%'      => $this->transactionData->getProductAmountNet(),
            '%PRODUCT_AMOUNT_DISCOUNT%' => $this->transactionData->getProductAmountDiscount(),
        ];

        $this->rawKeyValuePairs = array_merge( $this->rawKeyValuePairs, $keyValuePairs );
    }

    protected function initDecoratedKeyValuePairs() {
        parent::initDecoratedKeyValuePairs();

        $keyValuePairs = [
            '%COUPON_CODE%'     => $this->transactionData->getCouponCode(),
            '%CUSTOMER_TAX_ID%' => $this->transactionData->getCustomerTaxId(),
            '%PRODUCT_AMOUNT_GROSS%'  => Currencies::formatAndEscapeByForm(
                $this->form,
                $this->transactionData->getCurrency(),
                $this->transactionData->getProductAmountGross()
            ),
            '%PRODUCT_AMOUNT_TAX%'  => Currencies::formatAndEscapeByForm(
                $this->form,
                $this->transactionData->getCurrency(),
                $this->transactionData->getProductAmountTax()
            ),
            '%PRODUCT_AMOUNT_NET%'  => Currencies::formatAndEscapeByForm(
                $this->form,
                $this->transactionData->getCurrency(),
                $this->transactionData->getProductAmountNet()
            ),
            '%PRODUCT_AMOUNT_DISCOUNT%'  => Currencies::formatAndEscapeByForm(
                $this->form,
                $this->transactionData->getCurrency(),
                $this->transactionData->getProductAmountDiscount()
            ),
        ];

        $this->decoratedKeyValuePairs = array_merge( $this->decoratedKeyValuePairs, $keyValuePairs );
    }
}
