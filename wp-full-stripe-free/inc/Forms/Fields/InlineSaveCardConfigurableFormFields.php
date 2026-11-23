<?php

namespace ThemeIsle\WPFullPay\Forms\Fields;

class InlineSaveCardConfigurableFormFields extends  ConfigurableFormFields {
    use InlineConfigurableFormFieldsAddOn;

    public function getFields() : array {
        return $this->generateFalseArray( array_merge(
            $this->getCommonFields(),
            $this->getInlineFields()
        ));
    }
}
