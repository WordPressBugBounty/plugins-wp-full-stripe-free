<?php

namespace ThemeIsle\WPFullPay\Forms\Models;

use ThemeIsle\WPFullPay\Forms\Validators\PopupDonationFormValidator;

class CheckoutDonationFormModel extends DonationFormModel implements PopupForm
{
	/**
	 * CheckoutDonationFormModel constructor.
	 */
	public function __construct($loggerService)
	{
		parent::__construct($loggerService);

		$this->__validator = new PopupDonationFormValidator($loggerService);
	}

}
