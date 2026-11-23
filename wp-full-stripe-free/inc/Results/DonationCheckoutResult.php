<?php

namespace ThemeIsle\WPFullPay\Results;

use ThemeIsle\WPFullPay\Main;

class DonationCheckoutResult extends ChargeResult {

	/**
	 * DonationCheckoutResult constructor.
	 */
	public function __construct() {
		$this->formType = Main::FORM_TYPE_CHECKOUT_DONATION;
	}
}
