<?php

namespace ThemeIsle\WPFullPay\Customer;

use ThemeIsle\WPFullPay\Forms\Models\PaymentFormModel;
use ThemeIsle\WPFullPay\Forms\Models\SubscriptionFormModel;
use ThemeIsle\WPFullPay\Main;

class CustomerContextCreatorPaymentForm extends CustomerContextCreator {
	public function __construct( $formModel ) {
		parent::__construct( $formModel );
	}

	/**
	 * @return PaymentFormModel|SubscriptionFormModel
	 *
	 * This getter is created so that we can have type hints in the IDE
	 */
	protected function getFormModel() {
		return $this->formModel;
	}

	public function getContext() {
		$result = parent::getContext();

		$formModel = $this->getFormModel();

		$result->businessName = $formModel->getBusinessName();
		$result->taxIdType = $formModel->getTaxIdType();
		$result->taxId = $formModel->getTaxId();
		$result->taxCountry = $formModel->getTaxCountry();
		$result->taxState = $formModel->getTaxState();
		$result->taxPostalCode = $formModel->getTaxZip();
		$result->isStripeTax = ( $formModel->getForm()->vatRateType === Main::FIELD_VALUE_TAX_RATE_STRIPE_TAX );

		return $result;
	}
}
