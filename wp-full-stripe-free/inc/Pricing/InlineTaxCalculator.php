<?php

namespace ThemeIsle\WPFullPay\Pricing;

trait InlineTaxCalculator {

	protected function getApplicableTaxRates( $form ) {
		return PriceCalculator::getApplicableInlineTaxRatesStatic( $form, $this->pricingData );
	}
}
