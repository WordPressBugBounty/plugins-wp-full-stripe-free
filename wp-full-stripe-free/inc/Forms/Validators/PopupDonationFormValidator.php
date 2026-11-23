<?php

namespace ThemeIsle\WPFullPay\Forms\Validators;

use ThemeIsle\WPFullPay\Forms\Models\CheckoutDonationFormModel;

class PopupDonationFormValidator extends DonationFormValidator {

	public function __construct( $loggerService ) {
		parent::__construct( $loggerService );
	}

	protected function validateFields( $bindingResult, $formModelObject ) {
		parent::validateFields( $bindingResult, $formModelObject );
		if ( $formModelObject instanceof CheckoutDonationFormModel ) {
			$this->validateGoogleReCaptcha( $bindingResult, $formModelObject );
		}
	}

}
