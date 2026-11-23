<?php

namespace ThemeIsle\WPFullPay\Admin\FormTabCreators;

class CheckoutDonationFormTabCreator extends FormTabCreator {
	public function getTabs(): array {
		$tabs = [];

		$this->generalTab( $tabs );
		$this->paymentTab( $tabs );
		$this->appearanceTab( $tabs );
		$this->formFieldsTab( $tabs );
		$this->notificationsAndIntegrationTab( $tabs );

		return $tabs;
	}
}
