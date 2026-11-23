<?php

namespace ThemeIsle\WPFullPay\Results;

use ThemeIsle\WPFullPay\Main;

class SubscriptionResult extends TransactionResult {

	/**
	 * SubscriptionResult constructor.
	 */
	public function __construct() {
		$this->formType = Main::FORM_TYPE_INLINE_SUBSCRIPTION;
	}
}
