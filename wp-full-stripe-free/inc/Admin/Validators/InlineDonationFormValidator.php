<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\InlineDonationFormModel;
use ThemeIsle\WPFullPay\Admin\Views\InlineDonationFormViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Utils;

class InlineDonationFormValidator extends DonationFormValidator {
    use InlineFormValidator;

    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    /**
     * @param BindingResult $bindingResult
     * @param InlineDonationFormModel $formModel
     */
    protected function validateDefaultProductName( $bindingResult, $formModel ) {
        if ( empty( $formModel->getDefaultProductName() )) {
            $fieldName = InlineDonationFormViewConstants::FIELD_FORM_DEFAULT_PRODUCT_NAME;
            $fieldId   = Utils::generateFormElementId( $fieldName, $formModel->getFormHash() );
            /* translators: Validation error message when no default donation product name is entered. */
            $error     = __( 'Please enter a donation product name', 'wp-full-stripe-free' );
            $bindingResult->addFieldError( $fieldName, $fieldId, $error );
        }
    }

    protected function validateInlineDonationFields( $bindingResult, $formModel ) {
        $this->validateDefaultProductName( $bindingResult, $formModel );
    }

    public function validate($bindingResult, $formModel) {
        parent::validate( $bindingResult, $formModel );
        $this->validateInlineFields( $bindingResult, $formModel );
        $this->validateInlineDonationFields( $bindingResult, $formModel );
    }
}
