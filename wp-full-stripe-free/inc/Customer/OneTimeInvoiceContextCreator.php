<?php

namespace ThemeIsle\WPFullPay\Customer;

use ThemeIsle\WPFullPay\Forms\Models\DonationFormModel;
use ThemeIsle\WPFullPay\Forms\Models\PaymentFormModel;

abstract class OneTimeInvoiceContextCreator {
	/** @var PaymentFormModel|DonationFormModel */
	protected $formModel;

	public function __construct( $formModel ) {
		$this->formModel = $formModel;
	}

	public function getContext() {
		$result = new CreateOneTimeInvoiceContext();

		// The customer may not exist yet on preview/pricing flows (e.g. coupon/tax recompute
		// before checkout), so guard against reading ->id on null.
		$stripeCustomer = $this->formModel->getStripeCustomer();
		$result->stripeCustomerId = is_null( $stripeCustomer ) ? null : $stripeCustomer->id;
		$result->currency = $this->formModel->getForm()->currency;
		$result->amount = $this->formModel->getAmount();
		$result->productName = $this->formModel->getProductName();

		return $result;
	}
}
