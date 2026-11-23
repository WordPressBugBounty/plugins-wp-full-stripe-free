<?php

namespace ThemeIsle\WPFullPay\Forms\Validators;

use ThemeIsle\WPFullPay\Forms\Models\CheckoutSubscriptionFormModel;
use ThemeIsle\WPFullPay\Forms\Models\FormModel;

class PopupSubscriptionFormValidator extends SubscriptionFormValidator {

	public function __construct( $loggerService ) {
		parent::__construct( $loggerService );
	}

	protected function validateFields( $bindingResult, $formModelObject ) {
		if ( $formModelObject instanceof CheckoutSubscriptionFormModel ) {
			if ( 1 == $formModelObject->getForm()->simpleButtonLayout ) {
				$this->ignore( FormModel::PARAM_WPFS_CUSTOM_INPUT );
			}
		}
		parent::validateFields( $bindingResult, $formModelObject );
		if ( $formModelObject instanceof CheckoutSubscriptionFormModel ) {
			$this->validateGoogleReCaptcha( $bindingResult, $formModelObject );
		}
	}

}
