<?php

namespace ThemeIsle\WPFullPay\Pricing;

class CheckoutSubscriptionPriceCalculator extends SubscriptionPriceCalculator {
	use CheckoutTaxCalculator;

	protected function getFormFromDatabase() {
		return $this->db->getCheckoutSubscriptionFormByName( $this->pricingData->formId );
	}
}
