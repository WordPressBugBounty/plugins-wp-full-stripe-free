<?php

namespace ThemeIsle\WPFullPay\Macros;

abstract class ProductMacroReplacer extends FormMacroReplacer {
    public static function getMacroKeys() {
        return array_merge( parent::getMacroKeys(), [
            '%PRODUCT_NAME%',
            '%CUSTOMER_PHONE%',
        ]);
    }

    private function getKeyValuePairs() {
        $keyValuePairs = [
            '%PRODUCT_NAME%'    => $this->transactionData->getProductName(),
            '%CUSTOMER_PHONE%'    => $this->transactionData->getCustomerPhone(),
        ];

        return $keyValuePairs;
    }

    protected function initRawKeyValuePairs() {
        parent::initRawKeyValuePairs();

        $this->rawKeyValuePairs = array_merge( $this->rawKeyValuePairs, $this->getKeyValuePairs() );
    }

    protected function initDecoratedKeyValuePairs() {
        parent::initDecoratedKeyValuePairs();

        $this->decoratedKeyValuePairs = array_merge( $this->decoratedKeyValuePairs, $this->getKeyValuePairs() );
    }
}
