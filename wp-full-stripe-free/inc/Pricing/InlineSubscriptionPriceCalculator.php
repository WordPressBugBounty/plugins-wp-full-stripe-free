<?php

namespace ThemeIsle\WPFullPay\Pricing;

class InlineSubscriptionPriceCalculator extends SubscriptionPriceCalculator {
	use InlineTaxCalculator;

	protected function getFormFromDatabase() {
		return $this->db->getInlineSubscriptionFormByName( $this->pricingData->formId );
	}
}
