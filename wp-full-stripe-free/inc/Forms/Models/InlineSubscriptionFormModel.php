<?php

namespace ThemeIsle\WPFullPay\Forms\Models;

use ThemeIsle\WPFullPay\Forms\Validators\InlineSubscriptionFormValidator;

class InlineSubscriptionFormModel extends SubscriptionFormModel implements InlineForm
{
	/**
	 * InlineSubscriptionFormModel constructor.
	 */
	public function __construct($loggerService)
	{
		parent::__construct($loggerService);

		$this->__validator = new InlineSubscriptionFormValidator($loggerService);
	}

	protected function getFormFromDatabase()
	{
		return $this->__db->getInlineSubscriptionFormByName($this->getFormName());
	}
}
