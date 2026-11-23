<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Views\CouponViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\FormViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\SubscriptionFormView;
use ThemeIsle\WPFullPay\Admin\Views\TaxViewConstants;
use ThemeIsle\WPFullPay\Stripe;

abstract class SubscriptionFormModel extends FormModel implements TaxViewConstants, CouponViewConstants {
	use TaxAddOn;
	use CouponAddOn;

	protected $allowSubscriptionQuantity;
	protected $subscriptionMinimumQuantity;
	protected $subscriptionMaximumQuantity;
	protected $productSelectorStyle;
	protected $recurringProducts;

	/** @var Stripe */
	protected $stripe;
	/**
	 * @var mixed
	 */
	protected $showPaymentDetail;

	public function __construct( $loggerService ) {
		parent::__construct( $loggerService );

		$this->stripe = new Stripe( Stripe::getStripeAuthenticationToken( $this->staticContext ), $loggerService );
	}

	/**
	 * @param $products array
	 * @return array
	 */
	protected function extractPriceIdsFromProducts( $products ) {
		$priceIds = [];

		foreach ( $products as $product ) {
			array_push( $priceIds, $product->stripePriceId );
		}

		return $priceIds;
	}

	/**
	 * @param $products array
	 *
	 * @return array
	 */
	protected function createProductsLookup( $products ) {
		$productsLookup = [];

		foreach ( $products as $product ) {
			$productsLookup[ $product->stripePriceId ] = $product;
		}

		return $productsLookup;
	}

	/**
	 * @param $form \StdClass
	 * @return array
	 */
	protected function prepareRecurringProducts( $recurringProducts ) {
		$priceIds = $this->extractPriceIdsFromProducts( $recurringProducts );
		$recurringProductsLookup = $this->createProductsLookup( $recurringProducts );

		$savedProducts = [];
		$savedProductsLookup = [];

		$stripePrices = $this->stripe->getRecurringPrices();
		foreach ( $stripePrices as $stripePrice ) {
			if ( array_search( $stripePrice->id, $priceIds ) !== false ) {
				$mergedProduct = $recurringProductsLookup[ $stripePrice->id ];

				$mergedProduct->name = $stripePrice->product->name;
				$mergedProduct->price = $stripePrice->unit_amount;
				$mergedProduct->currency = $stripePrice->currency;
				$mergedProduct->interval = $stripePrice->recurring->interval;
				$mergedProduct->intervalCount = $stripePrice->recurring->interval_count;

				$savedProductsLookup[ $stripePrice->id ] = $mergedProduct;
			}
		}

		foreach ( $priceIds as $priceId ) {
			if ( array_key_exists( $priceId, $savedProductsLookup ) ) {
				array_push( $savedProducts, $savedProductsLookup[ $priceId ] );
			}
		}

		return $savedProducts;
	}

	public function bindByArray( $postData ) {

		$bindingResult = parent::bindByArray( $postData );

		$this->allowSubscriptionQuantity = $this->getSanitizedArrayParam( $postData, SubscriptionFormView::FIELD_FORM_ALLOW_SUBSCRIPTION_QUANTITY, 0 );
		$this->subscriptionMinimumQuantity = $this->getSanitizedArrayParam( $postData, SubscriptionFormView::FIELD_FORM_SUBSCRIPTION_MINIMUM_QUANTITY, 0 );
		$this->subscriptionMaximumQuantity = $this->getSanitizedArrayParam( $postData, SubscriptionFormView::FIELD_FORM_SUBSCRIPTION_MAXIMUM_QUANTITY, 0 );
		$this->productSelectorStyle = $this->getSanitizedArrayParam( $postData, SubscriptionFormView::FIELD_FORM_PLAN_SELECTOR_STYLE );
		$recurringProducts = $this->getJSONDecodedArrayParam( $postData, SubscriptionFormView::FIELD_FORM_RECURRING_PRODUCTS );
		$this->recurringProducts = $this->prepareRecurringProducts( $recurringProducts );
		$this->showPaymentDetail = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_SHOW_PAYMENT_DETAIL, 1 );

		$this->bindTaxParameters( $postData );
		$this->bindCouponParameters( $postData );

		return $bindingResult;
	}

	public function getData() {
		$parentData = parent::getData();

		$data = [
			'decoratedPlans' => json_encode( $this->recurringProducts ),
			'planSelectorStyle' => $this->productSelectorStyle,
			'allowMultipleSubscriptions' => $this->allowSubscriptionQuantity,
			'minimumQuantityOfSubscriptions' => $this->subscriptionMinimumQuantity,
			'maximumQuantityOfSubscriptions' => $this->subscriptionMaximumQuantity,
			'showPaymentDetail' => $this->showPaymentDetail,
		];

		return array_merge( $data,
			$parentData,
			$this->getTaxData(),
			$this->getCouponData()
		);
	}

	/**
	 * @return mixed
	 */
	public function getAllowSubscriptionQuantity() {
		return $this->allowSubscriptionQuantity;
	}

	/**
	 * @return mixed
	 */
	public function getSubscriptionMinimumQuantity() {
		return $this->subscriptionMinimumQuantity;
	}

	/**
	 * @return mixed
	 */
	public function getSubscriptionMaximumQuantity() {
		return $this->subscriptionMaximumQuantity;
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
	public function getRecurringProducts() {
		return $this->recurringProducts;
	}

	/**
	 * @return mixed
	 */
	public function showPaymentDetail() {
		return $this->showPaymentDetail;
	}
}
