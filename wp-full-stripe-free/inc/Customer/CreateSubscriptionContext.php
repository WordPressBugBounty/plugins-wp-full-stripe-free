<?php

namespace ThemeIsle\WPFullPay\Customer;

class CreateSubscriptionContext {
	public $stripeCustomerId;
	public $stripePriceId;
	public $stripePaymentMethodId;
	public $setupFee;
	public $trialPeriodDays;
	public $stripePlanQuantity;
	public $billingCycleAnchorDay;
	public $prorateUntilAnchorDay;
	public $metadata;
	public $discountId;
	public $discountType;
	public $productName;
	public $isStripeTax;
	public $feeRecoveryLineItem;
}
