<?php

namespace ThemeIsle\WPFullPay\Forms\Fields;

class CheckoutSaveCardConfigurableFormFields extends ConfigurableFormFields {
    use CheckoutConfigurableFormFieldsAddOn;
    public function getFields() : array {
        return $this->generateFalseArray( array_merge(
            $this->getCommonFields(),
            $this->getCheckoutFields()
        ));
    }
}
