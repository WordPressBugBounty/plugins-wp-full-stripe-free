<?php

namespace ThemeIsle\WPFullPay\Macros;

use ThemeIsle\WPFullPay\Utils;

class MyAccountLoginMacroReplacer {
    use MacroReplacerAddOn;

    function __construct( $transactionData ) {
        $this->transactionData  = $transactionData;

        $this->initRawKeyValuePairs();
        $this->initDecoratedKeyValuePairs();
    }

    public static function getMacroKeys() {
        return [
            '%NAME%',
            '%CUSTOMERNAME%',
            '%CUSTOMER_EMAIL%',
            '%CARD_UPDATE_SECURITY_CODE%',
            '%CARD_UPDATE_SESSION_HASH%',
            '%DATE%'
        ];
    }

    protected function getKeyValuePairs() {
        $siteTitle      = get_bloginfo( 'name' );
        $dateFormat     = get_option( 'date_format' );

        $keyValuePairs = [
            '%NAME%'		                => $siteTitle,
            '%CUSTOMERNAME%'		        => $this->transactionData->getCustomerName(),
            '%CUSTOMER_EMAIL%'		        => $this->transactionData->getCustomerEmail(),
            '%CARD_UPDATE_SECURITY_CODE%'   => $this->transactionData->getSecurityCode(),
            '%CARD_UPDATE_SESSION_HASH%'    => $this->transactionData->getSessionHash(),
            '%DATE%'                        => Utils::formatTimestampWithWordpressDateFormat( time() )
        ];

        return $keyValuePairs;
    }

    protected function initRawKeyValuePairs() {
        $this->rawKeyValuePairs = $this->getKeyValuePairs();
    }

    protected function initDecoratedKeyValuePairs() {
        $this->decoratedKeyValuePairs = $this->getKeyValuePairs();
    }
}
