<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

use ThemeIsle\WPFullPay\Main;

trait FormViewPricingAddOn {
	protected function getDefaultCountry() {
		return $this->inheritDefaultCountry();
	}

	/**
	 * @param $taxRates array
	 * @return string
	 */
	private function extractTaxLabel( $taxRates ) {
		$result = '';

		if ( count( $taxRates ) > 0 ) {
			$result = $taxRates[0]->displayName;

			if ( count( $taxRates ) > 1 ) {
				foreach ( $taxRates as $taxRate ) {
					if ( $taxRate->displayName !== $result ) {
						$result = __( 'Tax', 'wp-full-stripe-free' );
						break;
					}
				}
			}
		}

		return $result;
	}

	protected function areExclusiveTaxRates( $taxRates ) {
		$result = true;

		if ( count( $taxRates ) > 0 ) {
			foreach ( $taxRates as $taxRate ) {
				if ( $taxRate->inclusive ) {
					$result = false;
					break;
				}
			}
		}

		return $result;
	}

	protected function getTaxLabel() {
		$taxRates = json_decode( $this->form->vatRates );
		$result = '';

		if (
			$this->form->vatRateType !== null &&
			$this->form->vatRateType !== Main::FIELD_VALUE_TAX_RATE_NO_TAX &&
			$this->form->vatRateType !== Main::FIELD_VALUE_TAX_RATE_STRIPE_TAX &&
			$this->areExclusiveTaxRates( $taxRates )
		) {
			$result = $this->extractTaxLabel( $taxRates );
		}

		return $result;
	}
}
