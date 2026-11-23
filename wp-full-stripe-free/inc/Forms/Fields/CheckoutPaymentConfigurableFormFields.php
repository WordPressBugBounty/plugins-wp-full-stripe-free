<?php

namespace ThemeIsle\WPFullPay\Forms\Fields;

class CheckoutPaymentConfigurableFormFields extends ConfigurableFormFields {
    use CheckoutConfigurableFormFieldsAddOn;
    use CustomAmountConfigurableFormFieldsAddOn;
    use ProductConfigurableFormFieldsAddOn;
    use CouponConfigurableFormFieldsAddOn;
    public function getFields() : array {
        return $this->generateFalseArray( array_merge(
            $this->getCommonFields(),
            $this->getCheckoutFields(),
            $this->getProductFields(),
            $this->getCouponFields(),
            $this->getCustomAmountFields()
        ));
    }
}
