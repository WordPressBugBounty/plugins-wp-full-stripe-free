<?php

namespace ThemeIsle\WPFullPay\Forms\Fields;

trait CustomAmountConfigurableFormFieldsAddOn {
    protected function getCustomAmountFields(): array {
        return [
            ConfigurableFormFields::FIELD_AMOUNT,
        ];
    }
}
