<?php

namespace ThemeIsle\WPFullPay\Macros;

use ThemeIsle\WPFullPay\Utils;

class GenericMacroReplacer {
    use MacroReplacerAddOn;

    function __construct( $transactionData ) {
        $this->transactionData  = $transactionData;

        $this->initRawKeyValuePairs();
        $this->initDecoratedKeyValuePairs();
    }

    protected function initRawKeyValuePairs() {
        $this->rawKeyValuePairs = array_merge( self::getSiteKeyValuePairs(), (array) $this->transactionData );
    }

    protected function initDecoratedKeyValuePairs() {
        $this->decoratedKeyValuePairs = array_merge( self::getSiteKeyValuePairs(), (array) $this->transactionData );
    }

    /**
     * The site's placeholders every email fills, which the add-on sending the email doesn't pass.
     *
     * @return array<string, string>
     */
    private static function getSiteKeyValuePairs() {
        return [
            '%NAME%' => get_bloginfo( 'name' ),
            '%DATE%' => Utils::formatTimestampWithWordpressDateFormat( time() ),
        ];
    }

    public static function getMacroKeys() {
        return [];
    }
}
