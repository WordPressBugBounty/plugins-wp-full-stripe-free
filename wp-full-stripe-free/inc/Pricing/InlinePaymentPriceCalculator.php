<?php

namespace ThemeIsle\WPFullPay\Pricing;

class InlinePaymentPriceCalculator extends PaymentPriceCalculator {
	use InlineTaxCalculator;

	protected function getFormFromDatabase() {
		return $this->db->getInlinePaymentFormByName( $this->pricingData->formId );
	}
}
