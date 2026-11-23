<?php

namespace ThemeIsle\WPFullPay\Admin\FormTabCreators;

class CheckoutSubscriptionFormTabCreator extends FormTabCreator {
	use TaxAwareFormTabCreator;

	/**
	 * @return array<array<string, string>>
	 */
	public function getTabs() {
		$tabs = [];

		$this->generalTab( $tabs );
		$this->paymentAndTaxTab( $tabs );
		$this->appearanceTab( $tabs );
		$this->formFieldsTab( $tabs );
		$this->notificationsAndIntegrationTab( $tabs );

		return $tabs;
	}
}
