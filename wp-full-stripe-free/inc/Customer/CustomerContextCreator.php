<?php

namespace ThemeIsle\WPFullPay\Customer;

use ThemeIsle\WPFullPay\Forms\Models\DonationFormModel;
use ThemeIsle\WPFullPay\Forms\Models\PaymentFormModel;
use ThemeIsle\WPFullPay\Forms\Models\SubscriptionFormModel;

abstract class CustomerContextCreator {
	/** @var PaymentFormModel|SubscriptionFormModel|DonationFormModel */
	protected $formModel;

	public function __construct( $formModel ) {
		$this->formModel = $formModel;
	}

	protected function findBillingName() {
		return is_null( $this->formModel->getBillingName() ) ? $this->formModel->getCardHolderName() : $this->formModel->getBillingName();
	}

	public function getContext() {
		$result = new CreateCustomerContext();

		$result->paymentMethodId = $this->formModel->getStripePaymentMethodId();
		$result->paymentIntentId = $this->formModel->getStripePaymentIntentId();
		$result->cardHolderName = $this->formModel->getCardHolderName();
		$result->cardHolderEmail = $this->formModel->getCardHolderEmail();
		$result->cardHolderPhone = $this->formModel->getCardHolderPhone();

		$result->billingName = $this->findBillingName();
		$result->billingAddress = $this->formModel->getBillingAddress();
		$result->shippingName = $this->formModel->getShippingName();
		$result->shippingAddress = $this->formModel->getShippingAddress();

		$result->metadata = $this->formModel->getCustomerMetadata();

		return $result;
	}
}
