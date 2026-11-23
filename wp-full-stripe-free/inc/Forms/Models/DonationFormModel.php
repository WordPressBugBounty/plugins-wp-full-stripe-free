<?php

namespace ThemeIsle\WPFullPay\Forms\Models;

use ThemeIsle\WPFullPay\Forms\Views\DonationFormViewConstants;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Utils;

abstract class DonationFormModel extends FormModel
{

	const PARAM_WPFS_CUSTOM_AMOUNT_INDEX = 'wpfs-custom-amount-index';
	const PARAM_WPFS_CUSTOM_AMOUNT = 'wpfs-custom-amount';
	const PARAM_WPFS_CUSTOM_AMOUNT_UNIQUE = 'wpfs-custom-amount-unique';
	const PARAM_WPFS_DONATION_FREQUENCY = 'wpfs-donation-frequency';
	const INITIAL_CUSTOM_AMOUNT_INDEX = -1;

	/** @var string Metadata key holding the donation amount, without any recovery fee, that Stripe was asked to charge. */
	const METADATA_KEY_DONATION_AMOUNT = 'wpfs_donation_amount';
	protected $customAmountIndex;
	protected $customAmountValue;
	protected $customAmountUniqueValue;
	protected $donationFrequency;
	protected $__amount;
	protected $__stripeSubscription;
	/** @var mixed */
	protected $__stripePaymentIntent;
	/** @var int|null The amount Stripe already charged for this donation, which stands in for the selected one. */
	protected $__chargedAmount = null;

	/**
	 * DonationFormModel constructor.
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
		$this->donationFrequency = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_DONATION_FREQUENCY);

		if (isset($this->__validator)) {
			$this->__validator->validate($bindingResult, $this);
		}

		$this->afterBind();

		return $bindingResult;
	}

	public function getPostData()
	{
		$parentPostData = parent::getPostData();

		$postData = [
			self::PARAM_WPFS_CUSTOM_AMOUNT_INDEX => $this->customAmountIndex,
			self::PARAM_WPFS_CUSTOM_AMOUNT => $this->customAmountValue,
			self::PARAM_WPFS_CUSTOM_AMOUNT_UNIQUE => $this->customAmountUniqueValue,
			self::PARAM_WPFS_DONATION_FREQUENCY => $this->donationFrequency
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

	public function setForm($form)
	{
		parent::setForm($form);
		$this->prepareAmountAndProductName();
	}

	/**
	 * Whether the donation is charged at the amount the donor typed rather than a preset amount.
	 *
	 * @return bool
	 */
	public function isCustomAmountSelected()
	{
		return 1 == $this->__form->allowCustomDonationAmount && 'other' === $this->customAmountValue;
	}

	protected function prepareAmountAndProductName()
	{
		$this->__amount = null;
		if (isset($this->__form->productDesc)) {
			$this->__productName = esc_attr($this->__form->productDesc);
		} else {
			$this->__productName = __("Donation", 'wp-full-stripe-free');
		}

		if ($this->hasChargedAmount()) {
			$this->__amount = $this->__chargedAmount;
		} elseif ($this->isCustomAmountSelected()) {
			// null when malformed, the validator reports it
			$this->__amount = Currencies::parseToSmallestCurrencyUnitByForm( $this->__form, $this->__form->currency, $this->customAmountUniqueValue );
		} else {
			$donationAmounts = Utils::decodeJsonArray($this->__form->donationAmounts);
			if (isset($this->customAmountIndex) && ctype_digit((string) $this->customAmountIndex) && count($donationAmounts) > (int) $this->customAmountIndex) {
				$this->__amount = $donationAmounts[(int) $this->customAmountIndex];
			}
		}
	}

	/**
	 * Binds the donation to the amount Stripe already charged for it, so changes to the form's
	 * amounts and frequencies since then neither change nor refuse it.
	 *
	 * @param int $chargedAmount
	 * @return void
	 */
	public function setChargedAmount($chargedAmount)
	{
		$this->__chargedAmount = (int) $chargedAmount;
		$this->__amount = $this->__chargedAmount;
	}

	/**
	 * @return bool Whether the donation is bound to an amount Stripe already charged.
	 */
	public function hasChargedAmount()
	{
		return ! is_null($this->__chargedAmount);
	}

	public function getDonationFrequency()
	{
		return $this->donationFrequency;
	}

	/**
	 * @param string $donationFrequency
	 * @return void
	 */
	public function setDonationFrequency($donationFrequency)
	{
		$this->donationFrequency = $donationFrequency;
	}

	public function isRecurringDonation()
	{
		return $this->donationFrequency !== DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_ONE_TIME ? true : false;
	}

	/**
	 * @return mixed
	 */
	public function getStripeSubscription()
	{
		return $this->__stripeSubscription;
	}

	/**
	 * @param mixed $stripeSubscription
	 */
	public function setStripeSubscription($stripeSubscription)
	{
		$this->__stripeSubscription = $stripeSubscription;
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
	 * @return mixed
	 */
	public function setStripePaymentIntent($stripePaymentIntent)
	{
		$this->__stripePaymentIntent = $stripePaymentIntent;
	}

}
