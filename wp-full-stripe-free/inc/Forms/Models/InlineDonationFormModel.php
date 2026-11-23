<?php

namespace ThemeIsle\WPFullPay\Forms\Models;

use ThemeIsle\WPFullPay\Forms\Validators\InlineDonationFormValidator;

class InlineDonationFormModel extends DonationFormModel implements InlineForm
{

	/**
	 * InlineDonationFormModel constructor.
	 */
	public function __construct($loggerService)
	{
		parent::__construct($loggerService);

		$this->__validator = new InlineDonationFormValidator($loggerService);
	}

}
