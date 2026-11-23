<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Views\CouponViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\FormViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\InvoiceViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\PaymentFormViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\TaxViewConstants;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Stripe;

abstract class PaymentFormModel extends FormModel implements TaxViewConstants, CouponViewConstants, InvoiceViewConstants {
	use TaxAddOn;
	use CouponAddOn;
	use InvoiceAddOn;
	/**
	 * @var mixed
	 */
	protected $showPaymentDetail;

	protected $transactionDescription;
	protected $currency;
	protected $paymentType;
	protected $chargeType;
	protected $allowCustomPaymentAmount;
	protected $productSelectorStyle;
	protected $onetimeProducts;
	protected $minimumPaymentAmount;
    protected $paymentMethods;

	/** @var Stripe */
	protected $stripe;

	public function __construct( $loggerService ) {
		parent::__construct( $loggerService );

		$this->stripe = new Stripe( Stripe::getStripeAuthenticationToken( $this->staticContext ), $loggerService );
	}

	/**
	 * @param $onetimeProducts
	 */
	protected function prepareOnetimeProducts( $priceIds ) {
		$products = [];
		$productsLookup = [];

		$stripePrices = $this->stripe->getOnetimePrices();
		foreach ( $stripePrices as $stripePrice ) {
			if ( array_search( $stripePrice->id, $priceIds ) !== false ) {
				$productsLookup[ $stripePrice->id ] = [
					'stripePriceId' => $stripePrice->id,
					'name' => $stripePrice->product->name,
					'price' => $stripePrice->unit_amount,
					'currency' => $stripePrice->currency,
				];
			}
		}

		foreach ( $priceIds as $priceId ) {
			if ( array_key_exists( $priceId, $productsLookup ) ) {
				array_push( $products, $productsLookup[ $priceId ] );
			}
		}

		return $products;
	}

	public function bindByArray( $postData ) {
		$bindingResult = parent::bindByArray( $postData );

		$this->transactionDescription = $this->getSanitizedArrayParam( $postData, PaymentFormViewConstants::FIELD_FORM_TRANSACTION_DESCRIPTION );

		$this->currency = $this->getSanitizedArrayParam( $postData, PaymentFormViewConstants::FIELD_FORM_CURRENCY );
		$this->chargeType = $this->getSanitizedArrayParam( $postData, PaymentFormViewConstants::FIELD_FORM_CHARGE_TYPE, Main::CHARGE_TYPE_IMMEDIATE );
		$this->paymentType = $this->getSanitizedArrayParam( $postData, PaymentFormViewConstants::FIELD_FORM_PAYMENT_TYPE, Main::PAYMENT_TYPE_SPECIFIED_AMOUNT );
		$this->minimumPaymentAmount = $this->getSanitizedArrayParam( $postData, PaymentFormViewConstants::FIELD_FORM_MINIMUM_PAYMENT_AMOUNT_HIDDEN );
		$this->allowCustomPaymentAmount = $this->getSanitizedArrayParam( $postData, PaymentFormViewConstants::FIELD_FORM_ALLOW_CUSTOM_PAYMENT_AMOUNT, 0 );

		$this->bindInvoiceParameters( $postData );

		$onetimeProducts = $this->getJSONDecodedArrayParam( $postData, PaymentFormViewConstants::FIELD_FORM_ONETIME_PRODUCTS );
		$this->onetimeProducts = $this->prepareOnetimeProducts( $onetimeProducts );

		$this->bindTaxParameters( $postData );

		$this->productSelectorStyle = $this->getSanitizedArrayParam( $postData, PaymentFormViewConstants::FIELD_FORM_PRODUCT_SELECTOR_STYLE, Main::SELECTOR_STYLE_RADIO_BUTTONS );

		$this->bindCouponParameters( $postData );

        $this->paymentMethods = $this->getArrayParam( $postData, PaymentFormViewConstants::FIELD_FORM_PAYMENT_METHOD );

		$this->showPaymentDetail = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_SHOW_PAYMENT_DETAIL, 1 );

        return $bindingResult;
	}

	public function getData() {
		$parentData = parent::getData();

		$data = [
			'stripeDescription' => $this->transactionDescription,
			'currency' => $this->currency,
			'minimumPaymentAmount' => $this->minimumPaymentAmount,
			'customAmount' => $this->paymentType,
			'chargeType' => $this->chargeType,
			'allowListOfAmountsCustom' => $this->allowCustomPaymentAmount,
			'amountSelectorStyle' => $this->productSelectorStyle,
			'decoratedProducts' => json_encode( $this->onetimeProducts ),
			// The fields below are here for compatibility
			'amount' => 0,
			'showButtonAmount' => 0,
			'paymentMethods' => $this->paymentMethods,
			'showPaymentDetail' => $this->showPaymentDetail,
		];

		return array_merge( $data,
			$parentData,
			$this->getInvoiceData(),
			$this->getTaxData(),
			$this->getCouponData(),
			$this->getPaymentMethodData()
		);
	}

	/**
	 * @return mixed
	 */
	public function getTransactionDescription() {
		return $this->transactionDescription;
	}

	/**
	 * @return mixed
	 */
	public function getCurrency() {
		return $this->currency;
	}

	/**
	 * @return mixed
	 */
	public function getPaymentType() {
		return $this->paymentType;
	}

	/**
	 * @return mixed
	 */
	public function getChargeType() {
		return $this->chargeType;
	}

	/**
	 * @return mixed
	 */
	public function getAllowCustomPaymentAmount() {
		return $this->allowCustomPaymentAmount;
	}

	/**
	 * @return mixed
	 */
	public function getProductSelectorStyle() {
		return $this->productSelectorStyle;
	}

	/**
	 * @return mixed
	 */
	public function getOnetimeProducts() {
		return $this->onetimeProducts;
	}

	public function getPaymentMethodData() {
		return [ 
			'paymentMethods' => json_encode( $this->paymentMethods )
		];
	}

	/**
	 * @return mixed
	 */
	public function getMinimumPaymentAmount() {
		return $this->minimumPaymentAmount;
	}

	/**
	 * @return mixed
	 */
	public function showPaymentDetail() {
		return $this->showPaymentDetail;
	}
}
