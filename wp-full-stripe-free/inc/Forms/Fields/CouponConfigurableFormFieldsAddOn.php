<?php

namespace ThemeIsle\WPFullPay\Forms\Fields;

trait CouponConfigurableFormFieldsAddOn {
    protected function getCouponFields(): array {
        return [
            ConfigurableFormFields::FIELD_COUPON
        ];
    }
}
