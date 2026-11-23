<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

use ThemeIsle\WPFullPay\Locale\Countries;
use ThemeIsle\WPFullPay\Pricing\CustomerTaxId;

trait FormViewInlineTaxAddOn {
	/** @var Control */
	protected $buyingAsBusiness;
	/** @var Control */
	protected $businessName;
	/** @var Control */
	protected $taxId;
	/** @var Control */
	protected $taxCountry;
	/** @var Control */
	protected $taxState;
	/** @var Control */
	protected $taxZip;
	/** @var Control */
	protected $taxIdType;

	protected function initInlineTaxFields() {
		$this->prepareBuyingAsBusiness();
		$this->prepareTaxCountryFields();
	}

	protected function prepareTaxCountryFields() {
		$this->taxCountry = ControlUtils::createControl(
			$this->formHash,
			self::FIELD_TAX_COUNTRY,
			null,
			null,
			__( 'Country', 'wp-full-stripe-free' ),
			null
		);
		$this->taxCountry->setOptions( $this->prepareCountryOptions( Countries::getAvailableCountries(), $this->getDefaultCountry() ) );

		$this->taxState = ControlUtils::createControl(
			$this->formHash,
			self::FIELD_TAX_STATE,
			null,
			null,
			__( 'State', 'wp-full-stripe-free' ),
			null
		);
		$this->taxState->setOptions( $this->prepareStateOptions( null ) );

		$this->taxZip = ControlUtils::createControl(
			$this->formHash,
			self::FIELD_TAX_ZIP,
			null,
			null,
			__( 'Postal code', 'wp-full-stripe-free' ),
			null
		);
	}

	protected function prepareBuyingAsBusiness() {
		$this->buyingAsBusiness = ControlUtils::createControl(
			$this->formHash,
			self::FIELD_BUYING_AS_BUSINESS,
			null,
			null,
			__( "I'm buying as a business", 'wp-full-stripe-free' ),
			null
		);

		$this->businessName = ControlUtils::createControl(
			$this->formHash,
			self::FIELD_BUSINESS_NAME,
			null,
			null,
			__( 'Business name', 'wp-full-stripe-free' ),
			null
		);

		$this->taxIdType = ControlUtils::createControl(
			$this->formHash,
			self::FIELD_TAX_ID_TYPE,
			null,
			null,
			/* translators: Form field label for the country of the billing address */
			__( 'Tax ID Type', 'wp-full-stripe-free' ),
			null
		);
		$this->taxIdType->setOptions( $this->prepareTaxTypeOptions( $this->getDefaultCountry() ) );

		$this->taxId = ControlUtils::createControl(
			$this->formHash,
			self::FIELD_TAX_ID,
			null,
			null,
			__( 'Tax ID', 'wp-full-stripe-free' ),
			null
		);
	}

	/**
	 * @param string $defaultCountryCode
	 * @return array
	 */
	protected function prepareTaxTypeOptions( $defaultCountryCode ) {
		$result = [];
		$taxIdTypesByCountry = CustomerTaxId::getTaxIdTypesByCountry();

		$isTaxIdSelected = false;
		$isCountrySupported = array_key_exists( $defaultCountryCode, $taxIdTypesByCountry );
		foreach ( CustomerTaxId::getUniqueTaxIdTypes() as $taxIdItem ) {
			$id = $taxIdItem['id'];

			$option = ControlUtils::createControl( $this->formHash, null, null, $taxIdItem['description'], null, null );
			$option->setValue( $id );

			$attributes = [];
			if ( ! $isTaxIdSelected && $isCountrySupported ) {
				$countryItem = $taxIdTypesByCountry[ $defaultCountryCode ];

				if ( array_key_exists( $id, $countryItem ) ) {
					$attributes['selected'] = 'selected';
					$isTaxIdSelected = true;
				}
			}
			$option->setAttributes( $attributes );

			$result[] = $option;
		}

		return $result;
	}

	protected static function getInlineTaxFields() {
		return [ 
			self::FIELD_BUYING_AS_BUSINESS => ControlUtils::checkbox( self::FIELD_BUYING_AS_BUSINESS ),
			self::FIELD_BUSINESS_NAME => ControlUtils::input( self::FIELD_BUSINESS_NAME ),
			self::FIELD_TAX_ID_TYPE => ControlUtils::input( self::FIELD_TAX_ID_TYPE ),
			self::FIELD_TAX_ID => ControlUtils::input( self::FIELD_TAX_ID ),
			self::FIELD_TAX_COUNTRY => ControlUtils::selectMenu( self::FIELD_TAX_COUNTRY ),
			self::FIELD_TAX_STATE => ControlUtils::selectMenu( self::FIELD_TAX_STATE ),
			self::FIELD_TAX_ZIP => ControlUtils::input( self::FIELD_TAX_ZIP ),
		];
	}

	/**
	 * @return Control
	 */
	public function buyingAsBusiness(): Control {
		return $this->buyingAsBusiness;
	}

	/**
	 * @return Control
	 */
	public function businessName(): Control {
		return $this->businessName;
	}

	/**
	 * @return Control
	 */
	public function taxId(): Control {
		return $this->taxId;
	}

	/**
	 * @return Control
	 */
	public function taxIdType(): Control {
		return $this->taxIdType;
	}

	/**
	 * @return Control
	 */
	public function taxCountry(): Control {
		return $this->taxCountry;
	}

	/**
	 * @return Control
	 */
	public function taxState(): Control {
		return $this->taxState;
	}

	/**
	 * @return Control
	 */
	public function taxZip(): Control {
		return $this->taxZip;
	}
}
