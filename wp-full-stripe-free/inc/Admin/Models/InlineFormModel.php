<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Views\FormViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\InlineFormViewConstants;
use ThemeIsle\WPFullPay\Main;

trait InlineFormModel {
	protected $cardInputFieldLanguage;
	protected $collectBillingAddress;
	protected $defaultBillingCountry;
	protected $collectShippingAddress;
	/** @var mixed */
	protected $stripeElementsCustomCss;

	protected function bindInlineParams( $dataArray ) {
		$this->cardInputFieldLanguage = $this->getSanitizedArrayParam( $dataArray, InlineFormViewConstants::FIELD_FORM_CARD_FIELD_LANGUAGE, Main::PREFERRED_LANGUAGE_AUTO );

		$this->collectBillingAddress = $this->getSanitizedArrayParam( $dataArray, FormViewConstants::FIELD_FORM_COLLECT_BILLING_ADDRESS, 0 );
		$this->defaultBillingCountry = $this->getSanitizedArrayParam( $dataArray, FormViewConstants::FIELD_FORM_DEFAULT_BILLING_COUNTRY );
		$this->collectShippingAddress = $this->getSanitizedArrayParam( $dataArray, FormViewConstants::FIELD_FORM_COLLECT_SHIPPING_ADDRESS, 0 );
		if ( $this->collectShippingAddress == 1 ) {
			$this->collectBillingAddress = 1;
		}
		$this->stripeElementsCustomCss = $this->getSanitizedArrayParam( $dataArray, FormViewConstants::FIELD_FORM_CUSTOM_CSS_HIDDEN );
	}

	protected function getInlineDataArray() {

		$data = [
			'preferredLanguage' => $this->cardInputFieldLanguage,
			'showAddress' => $this->collectBillingAddress,
			'defaultBillingCountry' => $this->defaultBillingCountry,
			'showShippingAddress' => $this->collectShippingAddress,
			'stripeElementsCustomCss' => $this->stripeElementsCustomCss,
		];

		return $data;
	}

	/**
	 * @return mixed
	 */
	public function getCardInputFieldLanguage() {
		return $this->cardInputFieldLanguage;
	}

	/**
	 * @return mixed
	 */
	public function getCollectBillingAddress() {
		return $this->collectBillingAddress;
	}

	/**
	 * @return mixed
	 */
	public function getDefaultBillingCountry() {
		return $this->defaultBillingCountry;
	}

	/**
	 * @return mixed
	 */
	public function getCollectShippingAddress() {
		return $this->collectShippingAddress;
	}

}
