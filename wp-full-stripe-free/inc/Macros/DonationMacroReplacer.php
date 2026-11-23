<?php

namespace ThemeIsle\WPFullPay\Macros;

use ThemeIsle\WPFullPay\Locale\Localization;

class DonationMacroReplacer extends OneTimeInvolvedMacroReplacer {
    public static function getMacroKeys() {
        return array_merge( parent::getMacroKeys(), [
            '%DONATION_FREQUENCY%',
        ]);
    }

    protected function initRawKeyValuePairs() {
        parent::initRawKeyValuePairs();

        $keyValuePairs = [
            '%DONATION_FREQUENCY%'    => Localization::getDonationFrequencyLabel( $this->transactionData->getDonationFrequency() ),
        ];

        $this->rawKeyValuePairs = array_merge( $this->rawKeyValuePairs, $keyValuePairs );
    }

    protected function initDecoratedKeyValuePairs() {
        parent::initDecoratedKeyValuePairs();

        $keyValuePairs = [
            '%DONATION_FREQUENCY%' => Localization::getDonationFrequencyLabel( $this->transactionData->getDonationFrequency() ),
        ];

        $this->decoratedKeyValuePairs = array_merge( $this->decoratedKeyValuePairs, $keyValuePairs );
    }
}
