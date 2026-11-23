<?php

namespace ThemeIsle\WPFullPay\Forms\Validators;

use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Models\InlinePaymentFormModel;
use ThemeIsle\WPFullPay\Forms\Views\FormViewInlineTaxAddOnConstants;
use ThemeIsle\WPFullPay\Utils;

class InlinePaymentFormValidator extends PaymentFormValidator {
	use InlineFormValidator;
	use FormValidatorTaxAddOn;

	public function __construct( $loggerService ) {
		parent::__construct( $loggerService );
	}

	protected function validateFields( $bindingResult, $formModelObject ) {
		// if we're handling the customer coming back from the payment processor, we don't need to validate the captcha
		if ( $formModelObject->getAction() !== "wp_full_stripe_confirm_redirect" ) {
			parent::validateFields( $bindingResult, $formModelObject );
			if ( $formModelObject instanceof InlinePaymentFormModel ) {
				$this->validateInlineFields( $bindingResult, $formModelObject );
				$this->validateInlineTaxFields( $bindingResult, $formModelObject );
				$this->validatePaymentRecordLengths( $bindingResult, $formModelObject );
				$this->validateGoogleReCaptcha( $bindingResult, $formModelObject );
			}
		}
	}

	/**
	 * The draft payment row stores these fields, and a value wider than its column fails the insert.
	 */
	private function validatePaymentRecordLengths( BindingResult $bindingResult, InlinePaymentFormModel $formModelObject ): void {
		$fields = [
			FormViewInlineTaxAddOnConstants::FIELD_BUSINESS_NAME => [
				'value' => (string) $formModelObject->getBusinessName(),
				'maxLength' => Database::PAYMENT_BUSINESS_NAME_MAX_LENGTH,
				'label' => __( 'Business name', 'wp-full-stripe-free' ),
			],
			FormViewInlineTaxAddOnConstants::FIELD_TAX_ID => [
				'value' => (string) $formModelObject->getTaxId(),
				'maxLength' => Database::PAYMENT_TAX_ID_MAX_LENGTH,
				'label' => __( 'Tax ID', 'wp-full-stripe-free' ),
			],
		];

		foreach ( $fields as $fieldName => $field ) {
			if ( mb_strlen( $field['value'] ) > $field['maxLength'] ) {
				$error = sprintf(
					/* translators: Form field validation error for a value that is too long */
					__( "The value for '%s' is too long", 'wp-full-stripe-free' ),
					$field['label']
				);
				$bindingResult->addFieldError( $fieldName, Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() ), $error );
			}
		}
	}

}
