<?php

namespace ThemeIsle\WPFullPay\Forms\Models;

use ThemeIsle\WPFullPay\Forms\Validators\PopupSubscriptionFormValidator;

class CheckoutSubscriptionFormModel extends SubscriptionFormModel implements PopupForm
{

	/**
	 * CheckoutSubscriptionFormModel constructor.
	 */
	public function __construct($loggerService)
	{
		parent::__construct($loggerService);

		$this->__validator = new PopupSubscriptionFormValidator($loggerService);
	}

	protected function getFormFromDatabase()
	{
		return $this->__db->getCheckoutSubscriptionFormByName($this->getFormName());
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
