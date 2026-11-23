<?php

namespace ThemeIsle\WPFullPay\Checkout\SessionBuilders;

class CheckoutSessionBuilderSaveCard extends CheckoutSessionBuilder {
	public function __construct( $submitHash, $formModel, $fieldConfiguration, $stripe, $loggerService ) {
		parent::__construct( $submitHash, $formModel, $fieldConfiguration, $stripe, $loggerService );
	}

	public function build() {
		$sessionData = parent::build();

		$sessionData['mode'] = 'setup';
		$sessionData['customer_creation'] = 'always'; // always capture a customer. we need it post payment for internal logic
		// set a currency to help Stripe determine which payment methods to show
		$sessionData['currency'] = $this->formModel->getForm()->currency;

		return $sessionData;
	}
}
