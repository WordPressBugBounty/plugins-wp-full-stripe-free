<?php

namespace ThemeIsle\WPFullPay\Checkout\SessionBuilders;

trait CheckoutSessionBuilderPhoneNumberAddOn {
	protected function buildPhoneNumber( &$sessionData ) {
		if (
			! is_null( $this->formModel->getForm()->collectPhoneNumber ) &&
			1 == $this->formModel->getForm()->collectPhoneNumber
		) {
			$sessionData['phone_number_collection'] = [ 
				'enabled' => true,
			];
		}
	}
}
