<?php

namespace ThemeIsle\WPFullPay\Forms\Models;

use ThemeIsle\WPFullPay\Forms\Validators\PopupPaymentFormValidator;

class CheckoutPaymentFormModel extends PaymentFormModel implements PopupForm
{

	/**
	 * CheckoutPaymentFormModel constructor.
	 */
	public function __construct($loggerService)
	{
		parent::__construct($loggerService);

		$this->__validator = new PopupPaymentFormValidator($loggerService);
	}

	public function bindByArray($postData)
	{
		$bindingResult = parent::bindByArray($postData);

		if (isset($this->__validator)) {
			$this->__validator->validate($bindingResult, $this);
		}

		$this->afterBind();

		return $bindingResult;
	}
}
