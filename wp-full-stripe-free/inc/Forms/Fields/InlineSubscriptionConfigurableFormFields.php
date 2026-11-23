<?php

namespace ThemeIsle\WPFullPay\Forms\Fields;

class InlineSubscriptionConfigurableFormFields extends ConfigurableFormFields {
    use InlineConfigurableFormFieldsAddOn;
    use ProductConfigurableFormFieldsAddOn;
    use CouponConfigurableFormFieldsAddOn;

    public function getFields() : array {
        return $this->generateFalseArray( array_merge(
            $this->getCommonFields(),
            $this->getInlineFields(),
            $this->getProductFields(),
            $this->getCouponFields()
        ));
    }
}
