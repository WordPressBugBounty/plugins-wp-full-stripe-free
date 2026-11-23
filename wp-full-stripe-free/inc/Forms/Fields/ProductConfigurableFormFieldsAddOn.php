<?php

namespace ThemeIsle\WPFullPay\Forms\Fields;

trait ProductConfigurableFormFieldsAddOn {
    protected function getProductFields(): array {
        return [
            ConfigurableFormFields::FIELD_PRICE
        ];
    }
}
