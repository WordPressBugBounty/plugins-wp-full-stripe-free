<?php

namespace ThemeIsle\WPFullPay\Forms\Validators;

use ThemeIsle\WPFullPay\Forms\Models\PaymentFormModel;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Utils;

class PaymentFormValidator extends FormValidator {
	public function __construct( $loggerService ) {
		parent::__construct( $loggerService );
	}

	protected function validateFields( $bindingResult, $formModelObject ) {
		parent::validateFields( $bindingResult, $formModelObject );
		if ( $formModelObject instanceof PaymentFormModel ) {

			if ( $formModelObject->isCustomAmountSelected() ) {
				$fieldName = $formModelObject::PARAM_WPFS_CUSTOM_AMOUNT_UNIQUE;
				$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
				if ( $this->isCustomAmountEmpty( $formModelObject->getCustomAmountUniqueValue() ) ) {
					$error =
						/* translators: Form field validation error message when custom amount is empty */
						__( 'Please enter an amount', 'wp-full-stripe-free' );
					$bindingResult->addFieldError( $fieldName, $fieldId, $error );
				} elseif ( is_null( $formModelObject->getAmount() ) || $formModelObject->getAmount() <= 0 ) {
					$error = $this->getInvalidCustomAmountMessage( $formModelObject->getForm() );
					$bindingResult->addFieldError( $fieldName, $fieldId, $error );
				} elseif ( $formModelObject->getAmount() < $formModelObject->getForm()->minimumPaymentAmount ) {
					$error =
						/* translators: Form field validation error message when custom amount is less than the minimum payment amount */
						sprintf( __( 'The minimum payment amount is %s', 'wp-full-stripe-free' ), Currencies::formatByForm( $formModelObject->getForm(), $formModelObject->getForm()->currency, $formModelObject->getForm()->minimumPaymentAmount, false, true ) );
					$bindingResult->addFieldError( $fieldName, $fieldId, $error );
				}
			}
		}
	}
}
