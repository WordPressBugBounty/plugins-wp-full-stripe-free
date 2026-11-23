<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\CheckoutPaymentFormModel;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutPaymentFormViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Utils;

class CheckoutPaymentFormValidator extends PaymentFormValidator {
    use CheckoutFormValidator;
    use CheckoutPhoneNumberValidatorAddOn;

    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    public function validate($bindingResult, $formModel) {
        parent::validate( $bindingResult, $formModel );
        $this->validateCheckoutFields( $bindingResult, $formModel );

        $this->validateCheckoutPaymentFields( $bindingResult, $formModel );
        $this->validateCheckoutCollectPhoneNumber( $bindingResult, $formModel );
    }

    protected function validateCheckoutPaymentFields( $bindingResult, $formModel ) {
        $this->validateCheckoutProductName( $bindingResult, $formModel );
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|CheckoutPaymentFormModel
     */
    protected function validateCheckoutProductName( $bindingResult, $formModel ) {
        $isCustomAllowed = $formModel->getPaymentType() === Main::PAYMENT_TYPE_CUSTOM_AMOUNT ||
            ( $formModel->getPaymentType() === Main::PAYMENT_TYPE_LIST_OF_AMOUNTS && $formModel->getAllowCustomPaymentAmount() == 1 );

        if ( $isCustomAllowed === true && empty( $formModel->getCheckoutProductName() )) {
            $fieldName = CheckoutPaymentFormViewConstants::FIELD_FORM_CHECKOUT_PRODUCT_NAME;
            $fieldId   = Utils::generateFormElementId( $fieldName, $formModel->getFormHash() );
            /* translators: Validation error message when no name specified for the custom product. When a custom amount is entered on the form, the custom product name will be the name of the line item */
            $error     = __( 'Please enter a custom product name', 'wp-full-stripe-free' );
            $bindingResult->addFieldError( $fieldName, $fieldId, $error );
        }
    }
}
