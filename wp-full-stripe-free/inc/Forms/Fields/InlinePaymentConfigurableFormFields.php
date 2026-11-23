<?php

namespace ThemeIsle\WPFullPay\Forms\Fields;

class InlinePaymentConfigurableFormFields extends ConfigurableFormFields {
    use InlineConfigurableFormFieldsAddOn;
    use ProductConfigurableFormFieldsAddOn;
    use CouponConfigurableFormFieldsAddOn;
    use CustomAmountConfigurableFormFieldsAddOn;

    public function getFields() : array {
        return $this->generateFalseArray( array_merge(
            $this->getCommonFields(),
            $this->getInlineFields(),
            $this->getProductFields(),
            $this->getCouponFields(),
            $this->getCustomAmountFields()
        ));
    }
}
