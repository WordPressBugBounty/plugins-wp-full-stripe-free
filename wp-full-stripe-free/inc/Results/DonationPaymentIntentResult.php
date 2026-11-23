<?php

namespace ThemeIsle\WPFullPay\Results;

use ThemeIsle\WPFullPay\Main;

class DonationPaymentIntentResult extends ChargeResult {

	/**
	 * DonationPaymentIntentResult constructor.
	 */
	public function __construct() {
		$this->formType = Main::FORM_TYPE_INLINE_DONATION;
	}
}
