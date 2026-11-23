<?php

namespace ThemeIsle\WPFullPay\Pricing;

class CheckoutPaymentPriceCalculator extends PaymentPriceCalculator {
	use CheckoutTaxCalculator;

	/**
	 * Stripe Checkout charges the fee as a line of its own, which the session's coupon discounts.
	 */
	protected function previewsRecoveryFeeLine() {
		return true;
	}

	protected function getFormFromDatabase() {
		return $this->db->getCheckoutPaymentFormByName( $this->pricingData->formId );
	}
}
