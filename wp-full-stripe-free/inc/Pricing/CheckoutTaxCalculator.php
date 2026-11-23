<?php

namespace ThemeIsle\WPFullPay\Pricing;

use ThemeIsle\WPFullPay\Main;

trait CheckoutTaxCalculator {
	protected function getApplicableTaxRates( $form ) {
		$taxRates = json_decode( $form->vatRates );

		$applicableTaxRates = [];
		if ( $form->vatRateType === Main::FIELD_VALUE_TAX_RATE_FIXED ) {
			$applicableTaxRates = $taxRates;
		} else if ( $form->vatRateType === Main::FIELD_VALUE_TAX_RATE_DYNAMIC ) {
			$applicableTaxRates = [];
		}

		return $applicableTaxRates;
	}
}
