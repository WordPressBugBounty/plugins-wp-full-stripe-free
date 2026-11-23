<?php

namespace ThemeIsle\WPFullPay\Forms\Models;

use ThemeIsle\WPFullPay\Forms\Validators\InlinePaymentFormValidator;

class InlinePaymentFormModel extends PaymentFormModel implements InlineForm
{
	/**
	 * InlinePaymentFormModel constructor.
	 */
	public function __construct($loggerService)
	{
		parent::__construct($loggerService);

		$this->__validator = new InlinePaymentFormValidator($loggerService);
	}
}
