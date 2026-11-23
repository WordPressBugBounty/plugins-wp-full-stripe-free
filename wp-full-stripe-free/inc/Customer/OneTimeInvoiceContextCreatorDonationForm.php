<?php

namespace ThemeIsle\WPFullPay\Customer;

use ThemeIsle\WPFullPay\Forms\Models\DonationFormModel;

class OneTimeInvoiceContextCreatorDonationForm extends OneTimeInvoiceContextCreator {
	public function __construct( $formModel ) {
		parent::__construct( $formModel );
	}

	/**
	 * @return DonationFormModel
	 *
	 * This getter is created so that we can have type hints in the IDE
	 */
	protected function getFormModel() {
		return $this->formModel;
	}

	public function getContext() {
		$result = parent::getContext();

		$result->stripePriceId = null;
		$result->stripeCouponId = null;
		$result->isStripeTax = false;
		$result->taxCountry = null;
		$result->taxState = null;
		$result->taxPostalCode = null;
		$result->taxIdType = null;
		$result->taxId = null;

		return $result;
	}
}
