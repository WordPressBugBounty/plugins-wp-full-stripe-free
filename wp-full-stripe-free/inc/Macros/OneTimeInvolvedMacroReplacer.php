<?php

namespace ThemeIsle\WPFullPay\Macros;

use ThemeIsle\WPFullPay\Locale\Currencies;

abstract class OneTimeInvolvedMacroReplacer extends ProductMacroReplacer {
    public static function getMacroKeys() {
        return array_merge( parent::getMacroKeys(), [
            '%AMOUNT%',
            '%RECOVERY_FEE%',
            '%CHARGED_TOTAL%',
            '%INVOICE_URL%',
            '%INVOICE_NUMBER%',
        ]);
    }

    protected function initRawKeyValuePairs() {
        parent::initRawKeyValuePairs();

        $keyValuePairs = [
            '%AMOUNT%' =>               $this->transactionData->getAmount(),
            '%RECOVERY_FEE%'            => $this->transactionData->getRecoveryFee(),
            '%CHARGED_TOTAL%'           => $this->transactionData->getChargedTotal(),
            '%INVOICE_URL%'             => $this->transactionData->getInvoiceUrl(),
            '%INVOICE_NUMBER%'          => $this->transactionData->getInvoiceNumber(),
        ];

        $this->rawKeyValuePairs = array_merge( $this->rawKeyValuePairs, $keyValuePairs );
    }

    protected function initDecoratedKeyValuePairs() {
        parent::initDecoratedKeyValuePairs();

        $keyValuePairs = [
            '%AMOUNT%'		            => Currencies::formatAndEscapeByForm(
                $this->form,
                $this->transactionData->getCurrency(),
                $this->transactionData->getAmount()
            ),
            '%RECOVERY_FEE%'            => Currencies::formatAndEscapeByForm(
                $this->form,
                $this->transactionData->getCurrency(),
                $this->transactionData->getRecoveryFee()
            ),
            '%CHARGED_TOTAL%'           => Currencies::formatAndEscapeByForm(
                $this->form,
                $this->transactionData->getCurrency(),
                $this->transactionData->getChargedTotal()
            ),
            '%INVOICE_URL%'             => $this->transactionData->getInvoiceUrl(),
            '%INVOICE_NUMBER%'          => $this->transactionData->getInvoiceNumber(),
        ];

        $this->decoratedKeyValuePairs = array_merge( $this->decoratedKeyValuePairs, $keyValuePairs );
    }
}
