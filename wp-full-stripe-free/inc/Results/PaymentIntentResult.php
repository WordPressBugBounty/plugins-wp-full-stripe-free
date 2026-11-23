<?php

namespace ThemeIsle\WPFullPay\Results;

use ThemeIsle\WPFullPay\Main;

class PaymentIntentResult extends ChargeResult {

	/**
	 * PaymentIntentResult constructor.
	 */
	public function __construct() {
		$this->formType = Main::FORM_TYPE_INLINE_PAYMENT;
	}
}
