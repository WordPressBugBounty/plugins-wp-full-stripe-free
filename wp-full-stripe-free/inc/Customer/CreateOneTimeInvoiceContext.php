<?php

namespace ThemeIsle\WPFullPay\Customer;

class CreateOneTimeInvoiceContext {
	public $stripeCustomerId;
	public $stripePriceId;
	public $currency;
	public $amount;
	public $productName;
	public $stripeCouponId;
	public $isStripeTax;
	public $taxCountry;
	public $taxState;
	public $taxPostalCode;
	/** @var string|null */
	public $taxCity;
	/** @var string|null */
	public $taxLine1;
	/** @var string|null */
	public $taxLine2;
	public $taxIdType;
	public $taxId;
	/** @var int The recovery fee charged with the payment, in the smallest unit of the currency; 0 for none. */
	public $recoveryFee = 0;
	/**
	 * @var bool Whether the recovery fee was worked out on the discounted amount and added after tax, as inline
	 *           payments charge it, so the coupon and tax leave its line out.
	 */
	public $recoveryFeeAfterDiscountAndTax = false;
}
