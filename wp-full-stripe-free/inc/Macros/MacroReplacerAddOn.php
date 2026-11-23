<?php

namespace ThemeIsle\WPFullPay\Macros;

use ThemeIsle\WPFullPay\TransactionData\TransactionData;
use ThemeIsle\WPFullPay\Utils;

trait MacroReplacerAddOn {
    /** @var array */
    protected $rawKeyValuePairs;
    /** @var array */
    protected $decoratedKeyValuePairs;
    /** @var TransactionData */
    protected $transactionData;

    public function getRawKeyValuePairs() {
        return $this->rawKeyValuePairs;
    }

    public function getDecoratedKeyValuePairs() {
        return $this->decoratedKeyValuePairs;
    }

    protected function getDecoratedMacroKeys() {
        return array_keys( $this->decoratedKeyValuePairs );
    }

    protected function getDecoratedMacroValues() {
        return array_values( $this->decoratedKeyValuePairs );
    }

    protected function replaceMacrosWithEscape($template, $escapeType ) {
        $keys 	= $this->getDecoratedMacroKeys();
        $values = $this->getDecoratedMacroValues();

        $escapedValues = [];
        foreach ( $values as $value ) {
            array_push( $escapedValues, Utils::escape( $value, $escapeType ));
        }

        $template = str_replace( $keys, $escapedValues, $template );

        return $template;
    }

    public function replaceMacrosWithHtmlEscape( $template ) {
        return $this->replaceMacrosWithEscape( $template, Utils::ESCAPE_TYPE_HTML );
    }

    abstract protected function initRawKeyValuePairs();
    abstract protected function initDecoratedKeyValuePairs();
    abstract public static function getMacroKeys();
}
