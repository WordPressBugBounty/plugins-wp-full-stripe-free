<?php

namespace ThemeIsle\WPFullPay\Customer;

class CreateCustomerContext {
	public $paymentMethodId;
	public $paymentIntentId;
	public $cardHolderName;
	public $cardHolderEmail;
	public $cardHolderPhone;
	public $businessName;
	public $taxCountry;
	public $taxState;
	public $taxPostalCode;
	public $taxIdType;
	public $taxId;
	public $billingName;
	public $billingAddress;
	public $shippingName;
	public $shippingAddress;
	public $metadata;
	public $isStripeTax;
}
