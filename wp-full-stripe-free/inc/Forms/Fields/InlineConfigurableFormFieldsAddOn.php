<?php

namespace ThemeIsle\WPFullPay\Forms\Fields;

trait InlineConfigurableFormFieldsAddOn {
    protected function getInlineFields() : array {
        return [
            ConfigurableFormFields::FIELD_CARDHOLDERS_NAME,
        ];
    }
}
