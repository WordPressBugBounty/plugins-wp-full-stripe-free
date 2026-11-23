<?php

namespace ThemeIsle\WPFullPay\Forms\Fields;

trait CheckoutConfigurableFormFieldsAddOn {
    protected function getCheckoutFields(): array {
        return [
            ConfigurableFormFields::FIELD_CUSTOMER_ID
        ];
    }
}
