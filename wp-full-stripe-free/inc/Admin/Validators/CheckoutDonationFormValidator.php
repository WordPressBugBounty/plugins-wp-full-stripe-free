<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\CheckoutDonationFormModel;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutDonationFormView;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Utils;

class CheckoutDonationFormValidator extends DonationFormValidator {
    use CheckoutFormValidator;
    use CheckoutPhoneNumberValidatorAddOn;

    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    public function validate($bindingResult, $formModel) {
        parent::validate( $bindingResult, $formModel );
        $this->validateCheckoutFields( $bindingResult, $formModel );

        $this->validateCheckoutProductName( $bindingResult, $formModel );
        $this->validateCheckoutCollectPhoneNumber( $bindingResult, $formModel );
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|CheckoutDonationFormModel
     */
    protected function validateCheckoutProductName( $bindingResult, $formModel ) {
        if ( empty( $formModel->getCheckoutProductName() )) {
            $fieldName = CheckoutDonationFormView::FIELD_FORM_CHECKOUT_PRODUCT_NAME;
            $fieldId   = Utils::generateFormElementId( $fieldName, $formModel->getFormHash() );
            /* translators: Validation error message when no donation product name is entered. The product name is displayed as line item of the donation form. */
            $error     = __( 'Please enter a donation product name', 'wp-full-stripe-free' );
            $bindingResult->addFieldError( $fieldName, $fieldId, $error );
        }
    }
}
