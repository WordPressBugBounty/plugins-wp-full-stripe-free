<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Views\CheckoutPhoneViewConstants;

trait CheckoutPhoneNumberAddOn {
	protected $collectPhoneNumber;

	protected function bindCheckoutPhoneNumberParams( $postData ) {
		$this->collectPhoneNumber = $this->getSanitizedArrayParam( $postData, CheckoutPhoneViewConstants::FIELD_FORM_COLLECT_PHONE_NUMBER, 0 );
	}

	protected function getCheckoutPhoneNumberData() {
		return [
			'collectPhoneNumber' => $this->collectPhoneNumber,
		];
	}

	/**
	 * @return mixed
	 */
	public function getCollectPhoneNumber() {
		return $this->collectPhoneNumber;
	}
}
