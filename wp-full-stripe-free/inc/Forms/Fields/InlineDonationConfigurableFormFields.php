<?php

namespace ThemeIsle\WPFullPay\Forms\Fields;

class InlineDonationConfigurableFormFields extends ConfigurableFormFields {
    use InlineConfigurableFormFieldsAddOn;
    use CustomAmountConfigurableFormFieldsAddOn;

    public function getFields() : array {
        return $this->generateFalseArray( array_merge(
            $this->getCommonFields(),
            $this->getInlineFields(),
            $this->getCustomAmountFields()
        ));
    }
}
