<?php

namespace ThemeIsle\WPFullPay\Forms\Fields;

use ThemeIsle\WPFullPay\Utils;

class FormFieldConfiguration {
    protected $fieldName;
    protected $value;
    protected $isReadonly;
    protected $isConfigurable;

    public function __construct( $fieldName, $isConfigurable, $isReadyOnly, $value ) {
        $this->fieldName = $fieldName;
        $this->isConfigurable = $isConfigurable;
        $this->isReadonly = $isReadyOnly;
        $this->value = $value;
    }

    public function __toString() {
        return 'field: '            . $this->fieldName .
               ' value: '           . $this->value .
               ' isConfigurable: '  . Utils::boolToString($this->isConfigurable) .
               ' isReadonly: '      . Utils::boolToString($this->isReadonly);
    }

    /**
     * @return mixed
     */
    public function getValue() {
        return $this->value;
    }

    /**
     * @return mixed
     */
    public function isReadonly() {
        return $this->isReadonly;
    }

    /**
     * @return mixed
     */
    public function isConfigurable() {
        return $this->isConfigurable;
    }

    /**
     * @param mixed $value
     */
    public function setValue( $value ) {
        $this->value = $value;
    }

    /**
     * @param mixed $isReadonly
     */
    public function setIsReadonly($isReadonly ) {
        $this->isReadonly = $isReadonly;
    }

    /**
     * @param mixed $isConfigurable
     */
    public function setIsConfigurable($isConfigurable ) {
        $this->isConfigurable = $isConfigurable;
    }
}
