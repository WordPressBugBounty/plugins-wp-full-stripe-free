<?php

namespace ThemeIsle\WPFullPay\Forms\Validators;

use ThemeIsle\WPFullPay\Forms\Models\InlineDonationFormModel;

class InlineDonationFormValidator extends DonationFormValidator {
	use InlineFormValidator;

	public function __construct( $loggerService ) {
		parent::__construct( $loggerService );
	}

	protected function validateFields( $bindingResult, $formModelObject ) {
		parent::validateFields( $bindingResult, $formModelObject );
		if ( $formModelObject instanceof InlineDonationFormModel ) {
			$this->validateInlineFields( $bindingResult, $formModelObject );

			// Gate the client-secret step that creates the PaymentIntent (#520). Save stays
			// excluded: its payload is serialized before client-secret injects the nonce,
			// so it would re-verify the already-spent captcha token and fail.
			if ( 'wpfs-save-one-time-donation' !== $formModelObject->getAction() ) {
				$this->validateGoogleReCaptcha( $bindingResult, $formModelObject );
			}
		}
	}

}
