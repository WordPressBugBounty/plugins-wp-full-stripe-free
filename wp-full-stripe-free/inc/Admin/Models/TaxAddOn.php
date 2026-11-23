<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Views\TaxViewConstants;
use ThemeIsle\WPFullPay\Main;

trait TaxAddOn {
	protected $taxType;
	protected $collectCustomerTaxId;
	protected $taxRates;

	protected function bindTaxParameters( $postData ) {
		$taxType = $this->getSanitizedArrayParam( $postData, TaxViewConstants::FIELD_FORM_TAX_TYPE, Main::FIELD_VALUE_TAX_RATE_NO_TAX );
		$taxRateType = $this->getSanitizedArrayParam( $postData, TaxViewConstants::FIELD_FORM_TAX_RATE_TYPE, Main::FIELD_VALUE_TAX_RATE_FIXED );
		$this->taxType = ( $taxType === Main::FIELD_VALUE_TAX_RATE_TAX_RATES ? $taxRateType : $taxType );

		$this->collectCustomerTaxId = $this->getSanitizedArrayParam( $postData, TaxViewConstants::FIELD_FORM_COLLECT_CUSTOMER_TAX_ID, 0 );

		$taxRates = $this->getJSONDecodedArrayParam( $postData, TaxViewConstants::FIELD_FORM_TAX_RATES );
		$this->taxRates = $this->prepareTaxRates( $taxRates );
	}

	protected function getTaxData() {
		return [
			'vatRateType' => $this->taxType,
			'collectCustomerTaxId' => $this->collectCustomerTaxId,
			'vatRates' => json_encode( $this->taxRates ),
		];
	}

	/**
	 * @param $taxRateIds
	 */
	protected function prepareTaxRates( $taxRateIds ) {
		$taxRates = [];
		$taxRatesLookup = [];

		$stripeTaxRates = $this->stripe->getTaxRates();
		foreach ( $stripeTaxRates as $stripeTaxRate ) {
			if ( array_search( $stripeTaxRate->id, $taxRateIds ) !== false ) {
				$taxRatesLookup[ $stripeTaxRate->id ] = [
					'displayName' => $stripeTaxRate->display_name,
					'inclusive' => $stripeTaxRate->inclusive,
					'country' => $stripeTaxRate->country,
					'state' => $stripeTaxRate->state,
					'percentage' => $stripeTaxRate->percentage,
					'jurisdiction' => $stripeTaxRate->jurisdiction,
					'taxRateId' => $stripeTaxRate->id
				];
			}
		}

		foreach ( $taxRateIds as $priceId ) {
			if ( array_key_exists( $priceId, $taxRatesLookup ) ) {
				array_push( $taxRates, $taxRatesLookup[ $priceId ] );
			}
		}

		return $taxRates;
	}

	/**
	 * @return mixed
	 */
	public function getTaxType() {
		return $this->taxType;
	}

	/**
	 * @return mixed
	 */
	public function getTaxRates() {
		return $this->taxRates;
	}
}
