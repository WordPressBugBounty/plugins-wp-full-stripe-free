<?php

namespace ThemeIsle\WPFullPay\Forms\Validators;

use ThemeIsle\WPFullPay\Forms\Models\InlineSubscriptionFormModel;

class InlineSubscriptionFormValidator extends SubscriptionFormValidator {
	use InlineFormValidator;
	use FormValidatorTaxAddOn;

	public function __construct( $loggerService ) {
		parent::__construct( $loggerService );
	}

	protected function validateFields( $bindingResult, $formModelObject ) {
		parent::validateFields( $bindingResult, $formModelObject );
		if ( $formModelObject instanceof InlineSubscriptionFormModel ) {
			$this->validateInlineFields( $bindingResult, $formModelObject );
			$this->validateInlineTaxFields( $bindingResult, $formModelObject );
			$this->validateGoogleReCaptcha( $bindingResult, $formModelObject );
		}
	}
}
