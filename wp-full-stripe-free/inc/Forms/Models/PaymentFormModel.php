<?php

namespace ThemeIsle\WPFullPay\Forms\Models;

use ThemeIsle\WPFullPay\Exceptions\UserFriendlyException;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Payments\PaymentIntentBinding;
use ThemeIsle\WPFullPay\Utils;

abstract class PaymentFormModel extends FormModel
{
	use FormModelInlineTaxAddOn;

	const PARAM_WPFS_CUSTOM_AMOUNT_INDEX = 'wpfs-custom-amount-index';
	const PARAM_WPFS_CUSTOM_AMOUNT = 'wpfs-custom-amount';
	const PARAM_WPFS_CUSTOM_AMOUNT_UNIQUE = 'wpfs-custom-amount-unique';
	const INITIAL_CUSTOM_AMOUNT_INDEX = -1;
	protected $customAmountIndex;
	protected $customAmountValue;
	protected $customAmountUniqueValue;

	protected $__amount;
	protected $__priceId;
	protected $__price;
	protected $__stripePaymentIntent;

	/**
	 * PaymentFormModel constructor.
	 */
	public function __construct($loggerService)
	{
		parent::__construct($loggerService);
	}

	public function bindByArray($postData)
	{
		$bindingResult = parent::bindByArray($postData);
		$this->customAmountIndex = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_CUSTOM_AMOUNT_INDEX, self::INITIAL_CUSTOM_AMOUNT_INDEX);
		$this->customAmountValue = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_CUSTOM_AMOUNT);
		$this->customAmountUniqueValue = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_CUSTOM_AMOUNT_UNIQUE);

		$this->bindTaxDataByArray($postData);

		$this->prepareStripeCouponAndProduct();

		if (isset($this->__validator)) {
			$this->__validator->validate($bindingResult, $this);
		}

		$this->afterBind();

		return $bindingResult;
	}

	protected function prepareStripeCouponAndProduct()
	{
		$this->prepareStripeCoupon();
	}

	public function getPostData()
	{
		$parentPostData = parent::getPostData();

		$postData = [
			self::PARAM_WPFS_CUSTOM_AMOUNT_INDEX => $this->customAmountIndex,
			self::PARAM_WPFS_CUSTOM_AMOUNT => $this->customAmountValue,
			self::PARAM_WPFS_CUSTOM_AMOUNT_UNIQUE => $this->customAmountUniqueValue
		];

		return array_merge($postData, $parentPostData);
	}

	/**
	 * @return mixed
	 */
	public function getCustomAmountIndex()
	{
		return $this->customAmountIndex;
	}

	/**
	 * @param mixed $customAmountIndex
	 */
	public function setCustomAmountIndex($customAmountIndex)
	{
		$this->customAmountIndex = $customAmountIndex;
	}

	/**
	 * @return mixed
	 */
	public function getCustomAmountValue()
	{
		return $this->customAmountValue;
	}

	/**
	 * @param mixed $customAmountValue
	 */
	public function setCustomAmountValue($customAmountValue)
	{
		$this->customAmountValue = $customAmountValue;
	}

	/**
	 * @return mixed
	 */
	public function getCustomAmountUniqueValue()
	{
		return $this->customAmountUniqueValue;
	}

	/**
	 * @param mixed $customAmountUniqueValue
	 */
	public function setCustomAmountUniqueValue($customAmountUniqueValue)
	{
		$this->customAmountUniqueValue = $customAmountUniqueValue;
	}

	/**
	 * @return mixed
	 */
	public function getAmount()
	{
		return $this->__amount;
	}

	/**
	 * @param mixed $amount
	 */
	public function setAmount($amount)
	{
		$this->__amount = $amount;
	}

	/**
	 * @return mixed
	 */
	public function getPriceId()
	{
		return $this->__priceId;
	}

	public function setForm($form)
	{
		parent::setForm($form);
		$this->prepareAmountAndProductName();
	}

	/**
	 * Select the product a draft payment was saved with, replacing the one the request chose.
	 *
	 * @param string|null $priceId Stripe price id stored on the draft, empty for a custom amount.
	 * @return bool Whether the form still offers that product.
	 */
	public function selectProductByPriceId( $priceId ): bool
	{
		if ( empty( $priceId ) ) {
			if ( ! PaymentIntentBinding::allowsCustomAmount( $this->__form ) ) {
				return false;
			}

			$this->__productName = isset( $this->__form->productDesc ) ? $this->__form->productDesc : Utils::getDefaultProductDescription();
			$this->__priceId = null;
			$this->__price = null;

			return true;
		}

		foreach ( Utils::decodeJsonArray( $this->__form->decoratedProducts ) as $product ) {
			if ( isset( $product->stripePriceId ) && (string) $product->stripePriceId === (string) $priceId ) {
				$this->__amount = $product->price;
				$this->__productName = $product->name;
				$this->__priceId = $product->stripePriceId;
				$this->__price = $product;

				return true;
			}
		}

		return false;
	}

	/**
	 * @return mixed
	 */
	public function getStripePaymentIntent()
	{
		return $this->__stripePaymentIntent;
	}

	/**
	 * @param mixed $stripePaymentIntent
	 */
	public function setStripePaymentIntent($stripePaymentIntent)
	{
		$this->__stripePaymentIntent = $stripePaymentIntent;
	}

	/**
	 * Whether the payment is charged at the amount the customer typed rather than a product's price.
	 *
	 * @return bool
	 */
	public function isCustomAmountSelected()
	{
		return self::isCustomAmountSelectedOn($this->__form, $this->customAmountValue);
	}

	/**
	 * @param object $form Payment form record from the database.
	 * @param string|null $customAmountValue The posted amount choice.
	 */
	private static function isCustomAmountSelectedOn($form, $customAmountValue): bool
	{
		return Main::PAYMENT_TYPE_CUSTOM_AMOUNT === $form->customAmount ||
			(Main::PAYMENT_TYPE_LIST_OF_AMOUNTS === $form->customAmount
				&& 1 == $form->allowListOfAmountsCustom
				&& 'other' === $customAmountValue);
	}

	/**
	 * Whether a payment posted to the form is charged at one of its products' prices.
	 *
	 * @param object $form Payment form record from the database.
	 * @param string|null $customAmountValue The posted amount choice.
	 */
	public static function sellsProduct($form, $customAmountValue): bool
	{
		return Main::PAYMENT_TYPE_CARD_CAPTURE !== $form->customAmount && !self::isCustomAmountSelectedOn($form, $customAmountValue);
	}

	/**
	 * @throws UserFriendlyException
	 */
	protected function prepareAmountAndProductName()
	{
		$this->__amount = null;
		$this->__productName = Utils::getDefaultProductDescription();

		$customAmount = $this->isCustomAmountSelected();

		if (!$customAmount && Main::PAYMENT_TYPE_CARD_CAPTURE !== $this->__form->customAmount) {
			$products = Utils::decodeJsonArray($this->__form->decoratedProducts);

			if (count($products) === 0) {
				$ex = new UserFriendlyException(__('Please add products to the form.', 'wp-full-stripe-free'));
				$ex->setTitle(__('Form configuration error', 'wp-full-stripe-free'));
				$this->logger->error( __FUNCTION__, 'Error while rendering form. Products are missing. ', $ex );
				throw $ex;
			} else if (count($products) === 1) {
				$product = $products[0];
			} elseif (isset($this->customAmountIndex) && ctype_digit((string) $this->customAmountIndex) && count($products) > (int) $this->customAmountIndex) {
				// Digits only, so an index like "0.5" or "1.0" can't miss the product list and leave no amount.
				$product = $products[(int) $this->customAmountIndex];
			} else {
				$ex = new UserFriendlyException(
					/* translators: Error when the submitted product choice doesn't match any product of the form */
					__( 'The selected product is not available. Please select a product again.', 'wp-full-stripe-free' )
				);
				$this->logger->error( __FUNCTION__, 'Submitted amount index not found: ' . var_export( $this->customAmountIndex, true ), $ex );
				throw $ex;
			}

			$this->__amount = $product->price;
			$this->__productName = $product->name;
			$this->__priceId = $product->stripePriceId;
			$this->__price = $product;
		} elseif ($customAmount) {
			// null when malformed, the validator reports it
			$this->__amount = Currencies::parseToSmallestCurrencyUnitByForm( $this->__form, $this->__form->currency, $this->customAmountUniqueValue );

			$this->__productName = isset($this->__form->productDesc) ? $this->__form->productDesc : Utils::getDefaultProductDescription();
			$this->__priceId = null;
			$this->__price = null;
		}
	}
}
