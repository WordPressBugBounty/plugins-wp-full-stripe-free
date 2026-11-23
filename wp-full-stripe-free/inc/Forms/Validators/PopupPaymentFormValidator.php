<?php

namespace ThemeIsle\WPFullPay\Forms\Validators;

use ThemeIsle\WPFullPay\Forms\Models\CheckoutPaymentFormModel;

class PopupPaymentFormValidator extends PaymentFormValidator {

	public function __construct( $loggerService ) {
		parent::__construct( $loggerService );
	}

	protected function validateFields( $bindingResult, $formModelObject ) {
		parent::validateFields( $bindingResult, $formModelObject );
		if ( $formModelObject instanceof CheckoutPaymentFormModel ) {
			$this->validateGoogleReCaptcha( $bindingResult, $formModelObject );
		}
	}

}
