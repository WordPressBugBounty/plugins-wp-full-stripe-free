<?php

namespace ThemeIsle\WPFullPay\Customer;

class CreateCustomerOptions {
	public $addMetadata;
	/** Save the PaymentMethod on the Customer as its default for future charges. */
	public bool $attachPaymentMethod = true;
}
