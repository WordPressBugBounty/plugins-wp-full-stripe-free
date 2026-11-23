<?php

namespace ThemeIsle\WPFullPay\Customer;

use ThemeIsle\WPFullPay\Forms\Models\PaymentFormModel;
use ThemeIsle\WPFullPay\Main;

class OneTimeInvoiceContextCreatorPaymentForm extends OneTimeInvoiceContextCreator {
	public function __construct( $formModel ) {
		parent::__construct( $formModel );
	}

	/**
	 * @return PaymentFormModel
	 *
	 * This getter is created so that we can have type hints in the IDE
	 */
	protected function getFormModel() {
		return $this->formModel;
	}

	public function getContext() {
		$result = parent::getContext();

		$formModel = $this->getFormModel();

		$result->stripePriceId = $formModel->getPriceId();
		$result->stripeCouponId = is_null( $formModel->getStripeCoupon() ) ? null : $formModel->getStripeCoupon()->id;
		$result->isStripeTax = ( $formModel->getForm()->vatRateType === Main::FIELD_VALUE_TAX_RATE_STRIPE_TAX );
		$result->taxCountry = $formModel->getTaxCountry();
		$result->taxState = $formModel->getTaxState();
		// As the page prices the payment: the postal code of the tax fields, or of the billing address.
		$result->taxPostalCode = empty( $formModel->getTaxZip() ) ? $formModel->getBillingAddressZip() : $formModel->getTaxZip();
		$result->taxCity = $formModel->getBillingAddressCity();
		$result->taxLine1 = $formModel->getBillingAddressLine1();
		$result->taxLine2 = $formModel->getBillingAddressLine2();
		$result->taxIdType = $formModel->getTaxIdType();
		$result->taxId = $formModel->getTaxId();

		return $result;
	}
}
