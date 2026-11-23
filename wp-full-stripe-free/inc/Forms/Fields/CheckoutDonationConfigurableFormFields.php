<?php

namespace ThemeIsle\WPFullPay\Forms\Fields;

class CheckoutDonationConfigurableFormFields extends ConfigurableFormFields {
    use CheckoutConfigurableFormFieldsAddOn;
    use CustomAmountConfigurableFormFieldsAddOn;

    public function getFields() : array {
        return $this->generateFalseArray( array_merge(
            $this->getCommonFields(),
            $this->getCheckoutFields(),
            $this->getCustomAmountFields()
        ));
    }
}
