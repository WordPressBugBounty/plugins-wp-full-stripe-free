<?php

namespace ThemeIsle\WPFullPay\Forms\Models;

use ThemeIsle\WPFullPay\Utils;

abstract class SubscriptionFormModel extends FormModel
{
	use FormModelInlineTaxAddOn;

	const PARAM_WPFS_STRIPE_PLAN = 'wpfs-plan';
	const PARAM_WPFS_STRIPE_PLAN_QUANTITY = 'wpfs-plan-quantity';

	/** @var string Subscription metadata key holding the setup fee per unit the subscription was created with. */
	const METADATA_KEY_SETUP_FEE = 'wpfs_setup_fee';

	protected $stripePlanId;
	protected $stripePlanQuantity;
	/**
	 * @var \StripeWPFS\Stripe\Price|null
	 */
	protected $__stripePlan;
	protected $__stripePlanAmount;
	protected $__stripePlanSetupFee;
	protected $__stripePlanBillingAnchorDay;
	protected $__stripePlanProrateUntilAnchorDay;
	protected $__stripePlanTrialPeriodDays;
	protected $__stripePlanCancellationCount;

	protected $__stripePlanProperties;

	protected $__stripeSubscription;
	protected $__stripePaymentIntent;
	protected $__stripeSetupIntent;

	/**
	 * SubscriptionFormModel constructor.
	 */
	public function __construct($loggerService)
	{
		parent::__construct($loggerService);
	}

	public function bindByArray($postData)
	{
		$bindingResult = parent::bindByArray($postData);
		$this->stripePlanId = $this->getHTMLDecodedArrayParam($postData, self::PARAM_WPFS_STRIPE_PLAN);
		$stripePlanQuantityDefaultValue = 1;
		$this->stripePlanQuantity = $this->getNumericArrayParam($postData, self::PARAM_WPFS_STRIPE_PLAN_QUANTITY, $stripePlanQuantityDefaultValue);

		$this->bindTaxDataByArray($postData);

		$this->__productName = '';

		$form = $this->getFormFromDatabase();
		$this->setForm($form);

		$this->prepareStripePlan();
		$this->prepareStripeCouponAndProduct();

		if (isset($this->__validator)) {
			$this->__validator->validate($bindingResult, $this);
		}

		$this->afterBind();

		return $bindingResult;
	}

	abstract protected function getFormFromDatabase();

	protected function getPlanFromStripe()
	{
		$this->__stripePlan = $this->__stripe->retrievePlan($this->stripePlanId);
	}

	protected function getPlanPropertiesFromForm()
	{
		$stripePriceId = $this->__stripePlan->id;
		$planProperties = json_decode($this->__form->decoratedPlans);

		foreach ($planProperties as $planProperty) {
			if ($planProperty->stripePriceId === $stripePriceId) {
				$this->__stripePlanProperties = $planProperty;
				break;
			}
		}

		// A completed Checkout session is recorded even if its plan was removed from the form
		// after submission. New submissions with such a plan are refused by the validator.
		if (is_null($this->__stripePlanProperties)) {
			$prop = new \StdClass;
			$prop->stripePriceId = $this->__stripePlan->id;
			$prop->name = $this->__stripePlan->product->name;
			$prop->currency = $this->__stripePlan->currency;
			$prop->interval = $this->__stripePlan->recurring->interval;
			$prop->intervalCount = $this->__stripePlan->recurring->interval_count;
			$prop->price = $this->__stripePlan->unit_amount;
			$prop->setupFee = 0;
			$prop->trialDays = 0;
			$prop->cancellationCount = 0;
			$prop->billingAnchorDay = 0;
			$prop->prorateUntilBillingAnchorDay = false;

			$this->__stripePlanProperties = $prop;
		}
	}

	protected function extractPlanPropertiesData()
	{
		$this->__productName = $this->__stripePlan->product->name;
		$this->__stripePlanBillingAnchorDay = $this->__stripePlanProperties->billingAnchorDay;
		$this->__stripePlanProrateUntilAnchorDay = $this->__stripePlanProperties->prorateUntilBillingAnchorDay ? 1 : 0;
		$this->__stripePlanTrialPeriodDays = $this->__stripePlanProperties->trialDays;
		$this->__stripePlanCancellationCount = $this->__stripePlanProperties->cancellationCount;
	}

	protected function prepareStripePlan()
	{
		$this->getPlanFromStripe();
		$this->getPlanPropertiesFromForm();
		$this->extractPlanPropertiesData();
	}

	protected function prepareStripeCouponAndProduct()
	{
		parent::prepareStripeCoupon();

		if (isset($this->stripePlanId)) {
			$this->__stripePlanSetupFee = $this->__stripePlanProperties->setupFee;
			$this->__stripePlanAmount = $this->__stripePlan->unit_amount;
		}
	}

	public function getPostData()
	{
		$parentPostData = parent::getPostData();

		$postData = [
			self::PARAM_WPFS_STRIPE_PLAN => $this->stripePlanId,
			self::PARAM_WPFS_STRIPE_PLAN_QUANTITY => $this->stripePlanQuantity
		];

		return array_merge($postData, $parentPostData);
	}

	/**
	 * @return mixed
	 */
	public function getStripePlanId()
	{
		return $this->stripePlanId;
	}

	/**
	 * @return mixed
	 */
	public function getStripePlanQuantity()
	{
		return $this->stripePlanQuantity;
	}

	/**
	 * @return mixed
	 */
	public function getStripePlan()
	{
		return $this->__stripePlan;
	}

	/**
	 * Whether the submitted price is one of the plans offered on the form.
	 *
	 * @return bool
	 */
	public function isStripePlanOnForm()
	{
		foreach (Utils::decodeJsonArray($this->__form->decoratedPlans) as $plan) {
			if (isset($plan->stripePriceId) && $plan->stripePriceId === $this->stripePlanId) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return mixed
	 */
	public function getSetupFee()
	{
		return $this->__stripePlanSetupFee;
	}

	/**
	 * @return mixed
	 */
	public function getPlanAmount()
	{
		return $this->__stripePlanAmount;
	}

	/**
	 * @return mixed
	 */
	public function getBillingAnchorDay()
	{
		return $this->__stripePlanBillingAnchorDay;
	}

	/**
	 * @return mixed
	 */
	public function getProrateUntilAnchorDay()
	{
		return $this->__stripePlanProrateUntilAnchorDay;
	}

	/**
	 * @return mixed
	 */
	public function getTrialPeriodDays()
	{
		return $this->__stripePlanTrialPeriodDays;
	}

	/**
	 * @return mixed
	 */
	public function getCancellationCount()
	{
		return $this->__stripePlanCancellationCount;
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
	 * @return mixed
	 */
	public function getStripeSetupIntent()
	{
		return $this->__stripeSetupIntent;
	}

	/**
	 * @param mixed $stripeSetupIntent
	 */
	public function setStripeSetupIntent($stripeSetupIntent)
	{
		$this->__stripeSetupIntent = $stripeSetupIntent;
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
	 * Takes the setup fee the subscription was created with, so a fee changed on the form since is
	 * not reported. Subscriptions created before the fee was recorded take the fee their first invoice
	 * or Checkout session charged, when the caller knows it, and keep the form's fee otherwise.
	 *
	 * @param object|null $stripeSubscription
	 * @param int|null $chargedSetupFee The setup fee per unit the first invoice or session charged, null when unknown.
	 * @return void
	 */
	public function setSetupFeeFromSubscription($stripeSubscription, $chargedSetupFee = null)
	{
		$setupFee = self::getSetupFeeFromMetadata(isset($stripeSubscription->metadata) ? $stripeSubscription->metadata : null);
		if (is_null($setupFee)) {
			$setupFee = $chargedSetupFee;
		}
		if (!is_null($setupFee)) {
			$this->__stripePlanSetupFee = (int) $setupFee;
		}
	}

	/**
	 * @param object|array<string,mixed>|null $metadata Metadata of a subscription, or the snapshot an invoice carries.
	 * @return int|null The setup fee per unit the subscription was created with, null when it wasn't recorded.
	 */
	public static function getSetupFeeFromMetadata($metadata)
	{
		$setupFee = null;
		if (is_array($metadata) && isset($metadata[self::METADATA_KEY_SETUP_FEE])) {
			$setupFee = $metadata[self::METADATA_KEY_SETUP_FEE];
		} elseif (is_object($metadata) && isset($metadata->{self::METADATA_KEY_SETUP_FEE})) {
			$setupFee = $metadata->{self::METADATA_KEY_SETUP_FEE};
		}

		return is_numeric($setupFee) ? (int) $setupFee : null;
	}
}
