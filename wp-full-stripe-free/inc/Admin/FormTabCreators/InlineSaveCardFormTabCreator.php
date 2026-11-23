<?php

namespace ThemeIsle\WPFullPay\Admin\FormTabCreators;

class InlineSaveCardFormTabCreator extends FormTabCreator {
	/**
	 * @return array<array<string, string>>
	 */
	public function getTabs() {
		$tabs = [];

		$this->generalTab( $tabs );
		$this->paymentTab( $tabs );
		$this->appearanceTab( $tabs );
		$this->formFieldsTab( $tabs );
		$this->notificationsAndIntegrationTab( $tabs );

		return $tabs;
	}
}
