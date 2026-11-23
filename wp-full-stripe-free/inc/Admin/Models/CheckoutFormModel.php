<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Views\CheckoutFormViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\FormViewConstants;
use ThemeIsle\WPFullPay\Main;

trait CheckoutFormModel {
	protected $checkoutFormLanguage;
	protected $collectBillingAddress;
	protected $defaultBillingCountry;
	protected $collectShippingAddress;
	protected $openButtonLabel;

	protected function bindCheckoutParams( $dataArray ) {
		$this->openButtonLabel = $this->getSanitizedArrayParam( $dataArray, CheckoutFormViewConstants::FIELD_FORM_OPEN_BUTTON_LABEL,
			/* translators: Default payment button text on one-time payment forms */
			__( 'Pay', 'wp-full-stripe-free' ) );

		$this->checkoutFormLanguage = $this->getSanitizedArrayParam( $dataArray, CheckoutFormViewConstants::FIELD_FORM_CHECKOUT_LANGUAGE, Main::PREFERRED_LANGUAGE_AUTO );

		$this->collectBillingAddress = $this->getSanitizedArrayParam( $dataArray, FormViewConstants::FIELD_FORM_COLLECT_BILLING_ADDRESS, 0 );
		$this->defaultBillingCountry = $this->getSanitizedArrayParam( $dataArray, FormViewConstants::FIELD_FORM_DEFAULT_BILLING_COUNTRY );
		$this->collectShippingAddress = $this->getSanitizedArrayParam( $dataArray, FormViewConstants::FIELD_FORM_COLLECT_SHIPPING_ADDRESS, 0 );
		if ( $this->collectShippingAddress == 1 ) {
			$this->collectBillingAddress = 1;
		}
	}

	protected function getCheckoutDataArray() {

		$data = [
			'openButtonTitle' => $this->openButtonLabel,
			'preferredLanguage' => $this->checkoutFormLanguage,
			'showBillingAddress' => $this->collectBillingAddress,
			'defaultBillingCountry' => $this->defaultBillingCountry,
			'showShippingAddress' => $this->collectShippingAddress,
			// Legacy fields
			'image' => '',
		];

		return $data;
	}

	/**
	 * @return mixed
	 */
	public function getCheckoutFormLanguage() {
		return $this->checkoutFormLanguage;
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

	/**
	 * @return mixed
	 */
	public function getOpenButtonLabel() {
		return $this->openButtonLabel;
	}
}
