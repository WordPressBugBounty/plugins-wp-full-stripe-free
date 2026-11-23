<?php

namespace ThemeIsle\WPFullPay\Forms\Models;

use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Customer\Customer;
use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Forms\Fields\CustomFieldDef;
use ThemeIsle\WPFullPay\Forms\Fields\CustomFields;
use ThemeIsle\WPFullPay\Locale\Countries;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Payments\PaymentIntentBinding;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\Utils;

abstract class FormModel implements Binder
{
	use Model;
	use StaticContextAddOn;
	use LoggerAddOn;

	const CUSTOM_FIELD_IDENTIFIER_PREFIX = 'CustomField';

	const ARRAY_KEY_ADDRESS_LINE_1 = 'line1';
	const ARRAY_KEY_ADDRESS_LINE_2 = 'line2';
	const ARRAY_KEY_ADDRESS_CITY = 'city';
	const ARRAY_KEY_ADDRESS_STATE = 'state';
	const ARRAY_KEY_ADDRESS_COUNTRY = 'country';
	const ARRAY_KEY_ADDRESS_COUNTRY_CODE = 'country_code';
	const ARRAY_KEY_ADDRESS_ZIP = 'zip';

	const PARAM_WPFS_FORM_NAME = 'wpfs-form-name';
	const PARAM_WPFS_FORM_ACTION = 'action';
	const PARAM_WPFS_FORM_GET_PARAMETERS = 'wpfs-form-get-parameters';
	const PARAM_WPFS_REFERRER = 'wpfs-referrer';
	const PARAM_WPFS_STRIPE_PAYMENT_METHOD_ID = 'wpfs-stripe-payment-method-id';
	const PARAM_WPFS_STRIPE_PAYMENT_INTENT_ID = 'wpfs-stripe-payment-intent-id';
	const PARAM_WPFS_STRIPE_CLIENT_SECRET = 'wpfs-stripe-client-secret';
	const PARAM_WPFS_STRIPE_SETUP_INTENT_ID = 'wpfs-stripe-setup-intent-id';
	const PARAM_WPFS_CARD_HOLDER_NAME = 'wpfs-card-holder-name';
	const PARAM_WPFS_CARD_HOLDER_EMAIL = 'wpfs-card-holder-email';
	const PARAM_WPFS_CARD_HOLDER_PHONE = 'wpfs-card-holder-phone';
	const PARAM_WPFS_CUSTOM_INPUT = 'wpfs-custom-input';
	const PARAM_WPFS_SAME_BILLING_AND_SHIPPING_ADDRESS = 'wpfs-same-billing-and-shipping-address';
	const PARAM_WPFS_BILLING_NAME = 'wpfs-billing-name';
	const PARAM_WPFS_BILLING_ADDRESS_LINE_1 = 'wpfs-billing-address-line-1';
	const PARAM_WPFS_BILLING_ADDRESS_LINE_2 = 'wpfs-billing-address-line-2';
	const PARAM_WPFS_BILLING_ADDRESS_CITY = 'wpfs-billing-address-city';
	const PARAM_WPFS_BILLING_ADDRESS_STATE = 'wpfs-billing-address-state';
	const PARAM_WPFS_BILLING_ADDRESS_STATE_SELECT = 'wpfs-billing-address-state-select';
	const PARAM_WPFS_BILLING_ADDRESS_ZIP = 'wpfs-billing-address-zip';
	const PARAM_WPFS_BILLING_ADDRESS_COUNTRY = 'wpfs-billing-address-country';
	const PARAM_WPFS_SHIPPING_NAME = 'wpfs-shipping-name';
	const PARAM_WPFS_SHIPPING_ADDRESS_LINE_1 = 'wpfs-shipping-address-line-1';
	const PARAM_WPFS_SHIPPING_ADDRESS_LINE_2 = 'wpfs-shipping-address-line-2';
	const PARAM_WPFS_SHIPPING_ADDRESS_CITY = 'wpfs-shipping-address-city';
	const PARAM_WPFS_SHIPPING_ADDRESS_STATE = 'wpfs-shipping-address-state';
	const PARAM_WPFS_SHIPPING_ADDRESS_STATE_SELECT = 'wpfs-shipping-address-state-select';
	const PARAM_WPFS_SHIPPING_ADDRESS_ZIP = 'wpfs-shipping-address-zip';
	const PARAM_WPFS_SHIPPING_ADDRESS_COUNTRY = 'wpfs-shipping-address-country';
	const PARAM_WPFS_FEE_RECOVERY_ACCEPTED = 'wpfs-fee-recovery-accepted';
	const PARAM_WPFS_TERMS_OF_USE_ACCEPTED = 'wpfs-terms-of-use-accepted';
	/** When the terms of use were accepted. Set by the server, never by the browser. */
	const PARAM_WPFS_TERMS_OF_USE_ACCEPTED_AT = 'wpfs-terms-of-use-accepted-at';
	const PARAM_GOOGLE_RECAPTCHA_RESPONSE = 'g-recaptcha-response';
	const PARAM_WPFS_NONCE = 'wpfs-nonce';
	const PARAM_WPFS_COUPON = 'wpfs-coupon';
	const PARAM_WPFS_IP_ADDRESS = 'wpfs-ip-address';

	/** Stripe metadata key holding when the customer accepted the terms of use, in UTC ISO 8601. */
	const METADATA_KEY_TERMS_OF_USE_ACCEPTED_AT = 'wpfs_terms_accepted_at';

	/**
	 * @var string Metadata key for the tax country, state and postal code a payment's draft was priced with, joined
	 *             by "|". The page Stripe redirects back to posts the tax fields' defaults.
	 */
	const METADATA_KEY_TAX_LOCATION = 'wpfs_tax_location';

	protected $action;
	protected $formName;
	protected $formGetParameters;
	protected $referrer;
	protected $stripePaymentMethodId;
	protected $stripePaymentIntentId;
	protected $stripeSetupIntentId;
	protected $stripePaymentMethodType;
	protected $cardHolderName;
	protected $cardHolderEmail;
	protected $cardHolderPhone;
	protected $customInputValues;
	/**
	 * @var list<\stdClass> Saved custom fields the form no longer has, recorded after its current fields.
	 */
	protected $removedCustomFields = [];
	protected $sameBillingAndShippingAddress;
	protected $billingName;
	protected $billingAddressLine1;
	protected $billingAddressLine2;
	protected $billingAddressCity;
	protected $billingAddressState;
	protected $billingAddressZip;
	protected $billingAddressCountry;
	protected $shippingName;
	protected $shippingAddressLine1;
	protected $shippingAddressLine2;
	protected $shippingAddressCity;
	protected $shippingAddressState;
	protected $shippingAddressZip;
	protected $shippingAddressCountry;
	protected $feeRecoveryAccepted;
	protected $termsOfUseAccepted;
	/** @var string|null Unix timestamp of the acceptance, as bound from the submission. */
	protected $termsOfUseAcceptedAt;
	protected $googleReCaptchaResponse;
	protected $transactionId;
	protected $nonce;
	protected $couponCode;
	protected $ipAddress;

	/** @var bool */
	protected $__requireFreshCaptcha = false;

	protected $__form;
	protected $__formHash;
	protected $__billingAddressCountryComposite;
	protected $__billingAddressCountryName;
	protected $__billingAddressCountryCode;
	protected $__shippingAddressCountryComposite;
	protected $__shippingAddressCountryName;
	protected $__shippingAddressCountryCode;
	/**
	 * @var \StripeWPFS\Stripe\Customer
	 */
	protected $__stripeCustomer;
	protected $__productName;
	/**
	 * @var \StripeWPFS\Stripe\PaymentMethod
	 */
	protected $__stripePaymentMethod;

	/**
	 * @var \StripeWPFS\Stripe\Coupon|\StripeWPFS\Stripe\PromotionCode|null
	 */
	protected $__stripeDiscount;
	/**
	 * @var \StripeWPFS\Stripe\Coupon|null
	 */
	protected $__stripeCoupon;
	protected $__stripeDiscountId;
	protected $__stripeDiscountType;

	/**
	 * @var Database
	 */
	protected $__db;
	/**
	 * @var Stripe
	 */
	protected $__stripe;
	/**
	 * @var Options
	 */
	protected $options;

	/**
	 * Stripe Client Secret.
	 *
	 * @var string|null
	 */
	protected $stripeClientSecret;

	/**
	 * FormModel constructor.
	 */
	public function __construct($loggerService)
	{
		$this->initLogger($loggerService, LoggerService::MODULE_RUNTIME);
		$this->options = new Options();

		$this->initStaticContext();

		$this->__db = new Database();
		$this->__stripe = new Stripe(Stripe::getStripeAuthenticationToken($this->staticContext), $this->loggerService);
	}

	public function bind()
	{
		$this->detectIpAddress();
		$this->stampTermsOfUseAcceptance();
		$bindingResult = $this->bindByArray($_POST);

		// Only fresh submissions are filtered. Replays through bindByArray() (Checkout completion)
		// carry values that were already filtered when the submission was saved, and requests that
		// post no custom fields (the setup-intent request) have nothing to filter.
		if (!$bindingResult->hasErrors() && isset($_POST[self::PARAM_WPFS_CUSTOM_INPUT])) {
			$this->applyCustomFieldValuesFilter();
		}

		return $bindingResult;
	}

	/**
	 * Filters the custom field values of a request the form, as edited since, refuses, when the request completes a
	 * charge the form accepted before. bind() filters only the requests the form accepts.
	 *
	 * @return void
	 */
	public function filterChargedCustomFieldValues()
	{
		if (isset($_POST[self::PARAM_WPFS_CUSTOM_INPUT])) {
			$this->applyCustomFieldValuesFilter();
		}
	}

	/**
	 * Lets site code rewrite the submitted custom field values before anything is sent to Stripe
	 * or stored, so metadata, the saved record and the emails all get the same values.
	 *
	 * Runs in every request that binds a submission, so an inline payment (save, charge, 3D Secure
	 * resubmit) filters the same input more than once; filters must return the same output for it.
	 *
	 * @return void
	 */
	protected function applyCustomFieldValuesFilter()
	{
		$form = $this->getForm();
		if (is_null($form) || 1 != $form->showCustomInput || !has_filter(Main::FILTER_NAME_MODIFY_CUSTOM_FIELD_VALUES)) {
			return;
		}

		$params = [
			'formName' => $this->getFormName(),
			'formType' => Utils::getFormType($form),
			'formId' => Utils::getFormId($form),
			'urlParameters' => $this->getFormGetParametersAsArray(),
			'fields' => $this->describeCustomFields(),
		];

		// A broken filter must not fail the donor's payment: keep the submitted values instead.
		try {
			$filtered = apply_filters(Main::FILTER_NAME_MODIFY_CUSTOM_FIELD_VALUES, $this->customInputValues, $params);
		} catch (\Throwable $ex) {
			$this->logger->error(__FUNCTION__, 'The custom field values filter failed, keeping the submitted values.', $ex);

			return;
		}

		$values = $this->acceptFilteredCustomFieldValues($filtered);
		if (is_null($values)) {
			$this->logger->error(__FUNCTION__, 'The custom field values filter returned an invalid result, keeping the submitted values.');

			return;
		}

		$this->customInputValues = $values;
	}

	/**
	 * Sanitize and validate the result of the custom field values filter.
	 *
	 * Fields missing from the result keep their submitted value, unknown keys are dropped.
	 * The result is used whole or not at all.
	 *
	 * @param mixed $filtered
	 *
	 * @return string|array<int|string, mixed>|null the values to use, or null when the result is invalid
	 */
	protected function acceptFilteredCustomFieldValues($filtered)
	{
		if (is_null($this->__form->customInputs)) {
			if (!self::isCustomFieldScalar($filtered)) {
				return null;
			}
			$value = sanitize_text_field((string) $filtered);
			if (1 == $this->__form->customInputRequired && '' === trim($value)) {
				return null;
			}

			return mb_strlen($value) > Utils::STRIPE_METADATA_VALUE_MAX_LENGTH ? null : $value;
		}

		if (!is_array($filtered)) {
			return null;
		}

		$isJson = CustomFields::isJsonConfig($this->__form->customInputs);
		$values = is_array($this->customInputValues) ? $this->customInputValues : [];
		foreach (CustomFields::parse($this->__form->customInputs, $this->__form->customInputRequired) as $index => $def) {
			$key = $isJson ? $def->id : $index;
			if (!$def->isInteractive() || !array_key_exists($key, $filtered)) {
				continue;
			}
			$value = $this->sanitizeFilteredCustomFieldValue($def, $filtered[$key]);
			if (is_null($value) || !is_null(CustomFields::validateSubmittedValue($def, $value))) {
				return null;
			}
			$values[$key] = $value;
		}

		return $values;
	}

	/**
	 * @param CustomFieldDef $def
	 * @param mixed          $value
	 *
	 * @return string|string[]|null the sanitized value, or null when its type does not fit the field
	 */
	protected function sanitizeFilteredCustomFieldValue($def, $value)
	{
		$sanitize = CustomFields::TYPE_TEXTAREA === $def->type ? 'sanitize_textarea_field' : 'sanitize_text_field';

		if (!$def->isMultiValue()) {
			return self::isCustomFieldScalar($value) ? $sanitize((string) $value) : null;
		}

		if (!is_array($value)) {
			return null;
		}
		$result = [];
		foreach ($value as $single) {
			if (!self::isCustomFieldScalar($single)) {
				return null;
			}
			$result[] = $sanitize((string) $single);
		}

		return $result;
	}

	/**
	 * @param mixed $value
	 *
	 * @return bool
	 */
	private static function isCustomFieldScalar($value)
	{
		return is_string($value) || is_int($value) || is_float($value);
	}

	/**
	 * The interactive custom fields of the form, as passed to the custom field values filter.
	 *
	 * `key` is where the field's value sits in the values: a field id for JSON configs, a position
	 * for legacy {{ lists, and null for the legacy single field, whose value is a plain string.
	 *
	 * @return array<int, array{key:string|int|null, id:string|null, label:string, type:string, placeholder:string}>
	 */
	protected function describeCustomFields()
	{
		if (is_null($this->__form->customInputs)) {
			return [
				[
					'key' => null,
					'id' => null,
					'label' => $this->__form->customInputTitle,
					'type' => CustomFields::TYPE_TEXT,
					'placeholder' => '%CUSTOMFIELD1%',
				],
			];
		}

		$isJson   = CustomFields::isJsonConfig($this->__form->customInputs);
		$fields   = [];
		$position = 0;
		foreach (CustomFields::parse($this->__form->customInputs, $this->__form->customInputRequired) as $index => $def) {
			if (!$def->isInteractive()) {
				continue;
			}
			$position++;
			$fields[] = [
				'key' => $isJson ? $def->id : $index,
				'id' => $def->id,
				'label' => $def->label,
				'type' => $def->type,
				'placeholder' => '%CUSTOMFIELD' . $position . '%',
			];
		}

		return $fields;
	}

	/**
	 * @return string|null
	 */
	private function findIpAddress()
	{
		$httpHeaderNames = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

		foreach ($httpHeaderNames as $httpHeaderName) {
			if (array_key_exists($httpHeaderName, $_SERVER) === true) {
				foreach (explode(',', $_SERVER[$httpHeaderName]) as $ip) {
					if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
						return $ip;
					}
				}
			}
		}

		return null;
	}

	private function detectIpAddress()
	{
		$_POST[self::PARAM_WPFS_IP_ADDRESS] = $this->findIpAddress();
	}

	/**
	 * Records when this request accepted the terms of use, replacing any posted value. Replays
	 * through bindByArray() (Checkout completion) keep the time of the original submission.
	 */
	private function stampTermsOfUseAcceptance(): void
	{
		if (empty($_POST[self::PARAM_WPFS_TERMS_OF_USE_ACCEPTED])) {
			unset($_POST[self::PARAM_WPFS_TERMS_OF_USE_ACCEPTED_AT]);
		} else {
			$_POST[self::PARAM_WPFS_TERMS_OF_USE_ACCEPTED_AT] = (string) time();
		}
	}

	public function bindByArray($postData)
	{
		$bindingResult = new BindingResult();

		$this->action = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_FORM_ACTION);
		$this->formName = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_FORM_NAME);
		$this->formGetParameters = $this->getURLDecodedArrayParam($postData, self::PARAM_WPFS_FORM_GET_PARAMETERS);
		$this->referrer = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_REFERRER);
		$this->stripePaymentMethodId = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_STRIPE_PAYMENT_METHOD_ID);
		$this->stripePaymentIntentId = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_STRIPE_PAYMENT_INTENT_ID);
		$this->stripeSetupIntentId = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_STRIPE_SETUP_INTENT_ID);
		$this->stripeClientSecret = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_STRIPE_CLIENT_SECRET);
		$this->couponCode = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_COUPON);
		$this->cardHolderName = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_CARD_HOLDER_NAME);
		$this->cardHolderEmail = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_CARD_HOLDER_EMAIL, null, ModelConstants::SANITATION_TYPE_EMAIL);
		$this->cardHolderPhone = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_CARD_HOLDER_PHONE, null);
		$this->customInputValues = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_CUSTOM_INPUT);
		$this->sameBillingAndShippingAddress = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_SAME_BILLING_AND_SHIPPING_ADDRESS, 0);
		$this->billingName = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_BILLING_NAME);
		$this->billingAddressCountry = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_BILLING_ADDRESS_COUNTRY, Binder::EMPTY_STR);
		if ($this->billingAddressCountry === Main::COUNTRY_CODE_UNITED_STATES) {
			$this->billingAddressState = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_BILLING_ADDRESS_STATE_SELECT, Binder::EMPTY_STR);
		} else {
			$this->billingAddressState = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_BILLING_ADDRESS_STATE, Binder::EMPTY_STR);
		}
		$this->billingAddressLine1 = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_BILLING_ADDRESS_LINE_1, Binder::EMPTY_STR);
		$this->billingAddressLine2 = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_BILLING_ADDRESS_LINE_2, Binder::EMPTY_STR);
		$this->billingAddressCity = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_BILLING_ADDRESS_CITY, Binder::EMPTY_STR);
		$this->billingAddressZip = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_BILLING_ADDRESS_ZIP, Binder::EMPTY_STR);
		$this->shippingName = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_SHIPPING_NAME);
		$this->shippingAddressCountry = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_SHIPPING_ADDRESS_COUNTRY);
		if ($this->shippingAddressCountry === Main::COUNTRY_CODE_UNITED_STATES) {
			$this->shippingAddressState = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_SHIPPING_ADDRESS_STATE_SELECT, Binder::EMPTY_STR);
		} else {
			$this->shippingAddressState = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_SHIPPING_ADDRESS_STATE, Binder::EMPTY_STR);
		}
		$this->shippingAddressLine1 = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_SHIPPING_ADDRESS_LINE_1);
		$this->shippingAddressLine2 = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_SHIPPING_ADDRESS_LINE_2);
		$this->shippingAddressCity = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_SHIPPING_ADDRESS_CITY);
		$this->shippingAddressZip = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_SHIPPING_ADDRESS_ZIP);
		$this->feeRecoveryAccepted = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_FEE_RECOVERY_ACCEPTED, 0);
		$this->termsOfUseAccepted = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_TERMS_OF_USE_ACCEPTED, 0);
		$this->termsOfUseAcceptedAt = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_TERMS_OF_USE_ACCEPTED_AT);
		$this->googleReCaptchaResponse = $this->getSanitizedArrayParam($postData, self::PARAM_GOOGLE_RECAPTCHA_RESPONSE);
		$this->nonce = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_NONCE);
		$this->ipAddress = $this->getSanitizedArrayParam($postData, self::PARAM_WPFS_IP_ADDRESS);

		/*
					  if ( isset( $this->cardHolderName ) && ! empty( $this->cardHolderName ) ) {
						  if ( ! isset( $this->billingName ) || empty( $this->billingName ) ) {
							  $this->billingName = $this->cardHolderName;
						  }
						  if ( ! isset( $this->shippingName ) || empty( $this->shippingName ) ) {
							  $this->shippingName = $this->cardHolderName;
						  }
					  } else {
						  if ( isset( $this->billingName ) && ! empty( $this->billingName ) ) {
							  $this->cardHolderName = $this->billingName;
						  } elseif ( isset( $this->shippingName ) && ! empty( $this->shippingName ) ) {
							  $this->cardHolderName = $this->shippingName;
						  }
					  }
			  */

		$this->initBillingAddressCountryComposite();
		$this->initShippingAddressCountryComposite();

		return $bindingResult;
	}

	/**
	 * @param \StripeWPFS\Stripe\PromotionCode|\StripeWPFS\Stripe\Coupon|null $discount
	 * @return \StdClass|null Null when the discount is neither a coupon nor a promotion code.
	 */
	protected function extractDiscountData($discount)
	{
		$result = new \StdClass;
		
		if (isset($discount->promotion_code) || (isset($discount->object) && $discount->object === 'promotion_code')) {
			$result->type = Main::DISCOUNT_TYPE_PROMOTION_CODE;
			$result->id = $discount->coupon->id;
			$result->coupon = $discount->coupon;
		} else if (isset($discount->coupon) || (isset($discount->object) && $discount->object === 'coupon')) {
			$result->type = Main::DISCOUNT_TYPE_COUPON;
			if(isset($discount->coupon)) {
				$result->id = $discount->coupon->id;
			} else {
				$result->id = $discount->id;
			}
			$result->coupon = $discount;
		} else {
			return null;
		}

		return $result;
	}

	protected function prepareStripeCoupon()
	{
		if (isset($this->couponCode) && !empty($this->couponCode)) {
			$discount = $this->retrieveCouponOrPromotionalCode();
			$discountData = $this->extractDiscountData($discount);
			// An unknown code is reported by the validator.
			if (is_null($discountData)) {
				return;
			}

			$this->__stripeDiscount = $discount;
			$this->__stripeCoupon = $discountData->coupon;
			$this->__stripeDiscountId = $discountData->id;
			$this->__stripeDiscountType = $discountData->type;
		}
	}

	protected function initBillingAddressCountryComposite()
	{
		if (isset($this->billingAddressCountry)) {
			$this->__billingAddressCountryComposite = Countries::getCountryByCode($this->billingAddressCountry);
			if (isset($this->__billingAddressCountryComposite)) {
				$this->__billingAddressCountryName = $this->__billingAddressCountryComposite['name'];
				$this->__billingAddressCountryCode = $this->__billingAddressCountryComposite['alpha-2'];
			}
		}
	}

	protected function initShippingAddressCountryComposite()
	{
		if (isset($this->shippingAddressCountry)) {
			$this->__shippingAddressCountryComposite = Countries::getCountryByCode($this->shippingAddressCountry);
			if (isset($this->__shippingAddressCountryComposite)) {
				$this->__shippingAddressCountryName = $this->__shippingAddressCountryComposite['name'];
				$this->__shippingAddressCountryCode = $this->__shippingAddressCountryComposite['alpha-2'];
			}
		}
	}

	/**
	 * @param $stripeAddressHash
	 */
	public function updateBillingAddressByStripeAddressHash($stripeAddressHash)
	{
		if (isset($stripeAddressHash)) {
			if (isset($stripeAddressHash->line1)) {
				$this->billingAddressLine1 = $stripeAddressHash->line1;
			}
			if (isset($stripeAddressHash->line2)) {
				$this->billingAddressLine2 = $stripeAddressHash->line2;
			}
			if (isset($stripeAddressHash->city)) {
				$this->billingAddressCity = $stripeAddressHash->city;
			}
			if (isset($stripeAddressHash->state)) {
				$this->billingAddressState = $stripeAddressHash->state;
			}
			if (isset($stripeAddressHash->postal_code)) {
				$this->billingAddressZip = $stripeAddressHash->postal_code;
			}
			if (isset($stripeAddressHash->country)) {
				$this->billingAddressCountry = $stripeAddressHash->country;
			}
			$this->initBillingAddressCountryComposite();
		}
	}

	public function updateShippingAddressByStripeAddressHash($stripeAddressHash)
	{
		if (isset($stripeAddressHash)) {
			if (isset($stripeAddressHash->line1)) {
				$this->shippingAddressLine1 = $stripeAddressHash->line1;
			}
			if (isset($stripeAddressHash->line2)) {
				$this->shippingAddressLine2 = $stripeAddressHash->line2;
			}
			if (isset($stripeAddressHash->city)) {
				$this->shippingAddressCity = $stripeAddressHash->city;
			}
			if (isset($stripeAddressHash->state)) {
				$this->shippingAddressState = $stripeAddressHash->state;
			}
			if (isset($stripeAddressHash->postal_code)) {
				$this->shippingAddressZip = $stripeAddressHash->postal_code;
			}
			if (isset($stripeAddressHash->country)) {
				$this->shippingAddressCountry = $stripeAddressHash->country;
			}
			$this->initShippingAddressCountryComposite();
		}
	}

	/**
	 * @return Database
	 */
	public function getDb()
	{
		return $this->__db;
	}

	/**
	 * @return Stripe
	 */
	public function getStripe()
	{
		return $this->__stripe;
	}

	public function getData()
	{
		// tnagy unsupported operation
		return [];
	}

	public function getPostData()
	{

		$array = [
			self::PARAM_WPFS_FORM_ACTION => $this->action,
			self::PARAM_WPFS_FORM_NAME => $this->formName,
			self::PARAM_WPFS_FORM_GET_PARAMETERS => $this->formGetParameters,
			self::PARAM_WPFS_REFERRER => $this->referrer,
			self::PARAM_WPFS_STRIPE_PAYMENT_METHOD_ID => $this->stripePaymentMethodId,
			self::PARAM_WPFS_STRIPE_PAYMENT_INTENT_ID => $this->stripePaymentIntentId,
			self::PARAM_WPFS_STRIPE_SETUP_INTENT_ID => $this->stripeSetupIntentId,
			self::PARAM_WPFS_CARD_HOLDER_NAME => $this->cardHolderName,
			self::PARAM_WPFS_CARD_HOLDER_EMAIL => $this->cardHolderEmail,
			self::PARAM_WPFS_CARD_HOLDER_PHONE => $this->cardHolderPhone,
			self::PARAM_WPFS_CUSTOM_INPUT => $this->customInputValues,
			self::PARAM_WPFS_BILLING_NAME => $this->billingName,
			self::PARAM_WPFS_BILLING_ADDRESS_LINE_1 => $this->billingAddressLine1,
			self::PARAM_WPFS_BILLING_ADDRESS_LINE_2 => $this->billingAddressLine2,
			self::PARAM_WPFS_BILLING_ADDRESS_CITY => $this->billingAddressCity,
			self::PARAM_WPFS_BILLING_ADDRESS_STATE => $this->billingAddressState,
			self::PARAM_WPFS_BILLING_ADDRESS_ZIP => $this->billingAddressZip,
			self::PARAM_WPFS_BILLING_ADDRESS_COUNTRY => $this->billingAddressCountry,
			self::PARAM_WPFS_SHIPPING_NAME => $this->shippingName,
			self::PARAM_WPFS_SHIPPING_ADDRESS_LINE_1 => $this->shippingAddressLine1,
			self::PARAM_WPFS_SHIPPING_ADDRESS_LINE_2 => $this->shippingAddressLine2,
			self::PARAM_WPFS_SHIPPING_ADDRESS_CITY => $this->shippingAddressCity,
			self::PARAM_WPFS_SHIPPING_ADDRESS_STATE => $this->shippingAddressState,
			self::PARAM_WPFS_SHIPPING_ADDRESS_ZIP => $this->shippingAddressZip,
			self::PARAM_WPFS_SHIPPING_ADDRESS_COUNTRY => $this->shippingAddressCountry,
			self::PARAM_WPFS_FEE_RECOVERY_ACCEPTED => $this->feeRecoveryAccepted,
			self::PARAM_WPFS_TERMS_OF_USE_ACCEPTED => $this->termsOfUseAccepted,
			self::PARAM_WPFS_TERMS_OF_USE_ACCEPTED_AT => $this->termsOfUseAcceptedAt,
			self::PARAM_GOOGLE_RECAPTCHA_RESPONSE => $this->googleReCaptchaResponse,
			self::PARAM_WPFS_NONCE => $this->nonce,
			self::PARAM_WPFS_COUPON => $this->couponCode,
			self::PARAM_WPFS_IP_ADDRESS => $this->ipAddress
		];

		return $array;
	}

	/**
	 * @return mixed
	 */
	public function getAction()
	{
		return $this->action;
	}

	/**
	 * @return mixed
	 */
	public function getReferrer()
	{
		return $this->referrer;
	}

	/**
	 * @return mixed
	 */
	public function getStripePaymentMethodId()
	{
		return $this->stripePaymentMethodId;
	}

	/**
	 * @return mixed
	 */
	public function getStripePaymentIntentId()
	{
		return $this->stripePaymentIntentId;
	}

	/**
	 * @return mixed
	 */
	public function getStripeSetupIntentId()
	{
		return $this->stripeSetupIntentId;
	}
	public function setStripePaymentMethodType($stripePaymentMethodType)
	{
		$this->stripePaymentMethodType = $stripePaymentMethodType;
	}

	public function getStripePaymentMethodType()
	{
		return $this->stripePaymentMethodType;
	}

	/**
	 * Get Stripe Client Secret.
	 *
	 * @return mixed
	 */
	public function getStripePaymentIntentClientSecret()
	{
		return $this->stripeClientSecret;
	}

	/**
	 * @return mixed
	 */
	public function getCardHolderName()
	{
		return $this->cardHolderName;
	}

	/**
	 * @param mixed $cardHolderName
	 */
	public function setCardHolderName($cardHolderName)
	{
		$this->cardHolderName = $cardHolderName;
	}

	/**
	 * @return mixed
	 */
	public function getCardHolderEmail()
	{
		return $this->cardHolderEmail;
	}

	/**
	 * @param mixed $cardHolderEmail
	 */
	public function setCardHolderEmail($cardHolderEmail)
	{
		$this->cardHolderEmail = $cardHolderEmail;
	}

	/**
	 * @return mixed
	 */
	public function getCardHolderPhone()
	{
		return $this->cardHolderPhone;
	}

	/**
	 * @return mixed
	 */
	public function getCustomInputvalues()
	{
		return $this->customInputValues;
	}
	public function setCustomInputvalues($customInputValues)
	{
		$this->customInputValues = $customInputValues;
	}

	/**
	 * Takes the custom fields a payment saved before it was charged, as the customer typed them and the values filter
	 * changed them. Fields the admin removed since keep their saved values, after the form's current fields.
	 *
	 * @param string $customFieldsJSON The saved custom fields, as getCustomFieldsJSON() returned them.
	 * @return void
	 */
	public function restoreCustomFields($customFieldsJSON)
	{
		$customFields = json_decode((string) $customFieldsJSON);
		if (!is_array($customFields)) {
			return;
		}

		$customFieldValues = [];
		$this->removedCustomFields = [];
		// JSON configs key submitted values by stable id; legacy configs are positional, although their snapshots
		// carry an id too.
		$isJsonConfig = CustomFields::isJsonConfig($this->getForm()->customInputs);
		$currentIds = [];
		if ($isJsonConfig) {
			foreach (CustomFields::parse($this->getForm()->customInputs, $this->getForm()->customInputRequired) as $def) {
				$currentIds[] = $def->id;
			}
		}
		foreach ($customFields as $customField) {
			if ($isJsonConfig && isset($customField->id) && '' !== $customField->id) {
				$customFieldValues[$customField->id] = $customField->value;
				if (!in_array($customField->id, $currentIds, true)) {
					$this->removedCustomFields[] = $customField;
				}
			} else {
				array_push($customFieldValues, $customField->value);
			}
		}
		$this->customInputValues = $customFieldValues;
	}

	/**
	 * @return mixed
	 */
	public function getBillingAddressLine1()
	{
		return $this->billingAddressLine1;
	}

	/**
	 * @return string|null
	 */
	public function getBillingAddressLine2()
	{
		return $this->billingAddressLine2;
	}

	/**
	 * @return mixed
	 */
	public function getBillingAddressCity()
	{
		return $this->billingAddressCity;
	}

	/**
	 * @return mixed
	 */
	public function getBillingAddressState()
	{
		return $this->billingAddressState;
	}

	/**
	 * @return mixed
	 */
	public function getBillingAddressZip()
	{
		return $this->billingAddressZip;
	}

	/**
	 * @return mixed
	 */
	public function getBillingAddressCountry()
	{
		return $this->billingAddressCountry;
	}

	/**
	 * @return mixed
	 */
	public function getShippingAddressLine1()
	{
		return $this->shippingAddressLine1;
	}

	/**
	 * @return mixed
	 */
	public function getShippingAddressCity()
	{
		return $this->shippingAddressCity;
	}

	/**
	 * @return mixed
	 */
	public function getShippingAddressState()
	{
		return $this->shippingAddressState;
	}

	/**
	 * @return mixed
	 */
	public function getShippingAddressZip()
	{
		return $this->shippingAddressZip;
	}

	/**
	 * @return mixed
	 */
	public function getShippingAddressCountry()
	{
		return $this->shippingAddressCountry;
	}

	/**
	 * Whether the recovery fee is charged. Forms that always apply it charge it whatever the request
	 * posts; elsewhere it is the customer's choice.
	 *
	 * @return int|string|array<array-key, mixed>|null 1 on forms that always apply the fee; otherwise the
	 *                                                 posted or restored choice, or null before binding.
	 */
	public function getFeeRecoveryAccepted()
	{
		if (!is_null($this->getForm()) && Utils::isFeeRecoveryAlwaysApplied($this->getForm())) {
			return 1;
		}

		return $this->feeRecoveryAccepted;
	}

	/**
	 * @param int $feeRecoveryAccepted 1 when the customer accepted the recovery fee, otherwise 0.
	 */
	public function setFeeRecoveryAccepted(int $feeRecoveryAccepted): void
	{
		$this->feeRecoveryAccepted = $feeRecoveryAccepted;
	}

	/**
	 * @return mixed
	 */
	public function getTermsOfUseAccepted()
	{
		return $this->termsOfUseAccepted;
	}

	/**
	 * When the customer accepted the terms of use, on forms that ask for it.
	 *
	 * @return int|null Unix timestamp, or null when the form shows no terms of use or this
	 *                  submission did not accept them.
	 */
	public function getTermsOfUseAcceptedAt(): ?int
	{
		if (!isset($this->__form->showTermsOfUse) || 1 != $this->__form->showTermsOfUse) {
			return null;
		}
		if (!is_numeric($this->termsOfUseAcceptedAt) || (int) $this->termsOfUseAcceptedAt <= 0) {
			return null;
		}

		return (int) $this->termsOfUseAcceptedAt;
	}

	/**
	 * Takes the acceptance an earlier request of the same transaction stamped in Stripe metadata, so
	 * a later request of it records the same time.
	 *
	 * @param mixed $metadataValue
	 */
	public function restoreTermsOfUseAcceptedAt($metadataValue): void
	{
		$acceptedAt = self::parseTermsOfUseAcceptedAtMetadataValue($metadataValue);
		if (!is_null($acceptedAt)) {
			$this->termsOfUseAcceptedAt = (string) $acceptedAt;
		}
	}

	/**
	 * @param mixed $metadataValue
	 * @return int|null Unix timestamp, or null when the value is not a UTC ISO 8601 time.
	 */
	public static function parseTermsOfUseAcceptedAtMetadataValue($metadataValue): ?int
	{
		if (!is_string($metadataValue) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $metadataValue)) {
			return null;
		}
		$acceptedAt = strtotime($metadataValue);

		return false === $acceptedAt ? null : $acceptedAt;
	}

	/**
	 * @return string|null The acceptance time as stored in Stripe metadata, in UTC ISO 8601.
	 */
	public function getTermsOfUseAcceptedAtMetadataValue(): ?string
	{
		$termsOfUseAcceptedAt = $this->getTermsOfUseAcceptedAt();

		return is_null($termsOfUseAcceptedAt) ? null : gmdate('Y-m-d\TH:i:s\Z', $termsOfUseAcceptedAt);
	}

	/**
	 * @return mixed
	 */
	public function getGoogleReCaptchaResponse()
	{
		return $this->googleReCaptchaResponse;
	}

	/**
	 * @return mixed
	 */
	public function getTransactionId()
	{
		return $this->transactionId;
	}

	/**
	 * @param mixed $transactionId
	 */
	public function setTransactionId($transactionId)
	{
		$this->transactionId = $transactionId;
	}

	/**
	 * @return mixed
	 */
	public function getFormHash()
	{
		return $this->__formHash;
	}

	/**
	 * @param mixed $formHash
	 */
	public function setFormHash($formHash)
	{
		$this->__formHash = $formHash;
	}

	/**
	 * @return mixed
	 */
	public function getNonce()
	{
		return $this->nonce;
	}

	/**
	 * @param mixed $nonce
	 */
	public function setNonce($nonce)
	{
		$this->nonce = $nonce;
	}

	/**
	 * Forces reCAPTCHA verification against Google, ignoring any nonce in the
	 * request. Set by handlers that create Stripe objects.
	 *
	 * @param bool $requireFreshCaptcha
	 *
	 * @return void
	 */
	public function setRequireFreshCaptcha($requireFreshCaptcha)
	{
		$this->__requireFreshCaptcha = (bool) $requireFreshCaptcha;
	}

	/**
	 * @return bool
	 */
	public function getRequireFreshCaptcha()
	{
		return $this->__requireFreshCaptcha;
	}

	/**
	 * @return mixed
	 */
	public function getBillingAddressCountryComposite()
	{
		return $this->__billingAddressCountryComposite;
	}

	/**
	 * @return mixed
	 */
	public function getShippingAddressCountryComposite()
	{
		return $this->__shippingAddressCountryComposite;
	}

	/**
	 * Truncate a Stripe metadata key to a character length (multibyte-aware, byte fallback).
	 *
	 * @param string $value
	 * @param int    $maxLength
	 * @return string
	 */
	private function truncateMetadataKey($value, $maxLength)
	{
		if ($maxLength <= 0) {
			return '';
		}
		if (function_exists('mb_substr')) {
			return mb_substr($value, 0, $maxLength);
		}

		return substr($value, 0, $maxLength);
	}

	/**
	 * Whether a custom field may not use the key: it is already set, or the plugin writes it on the
	 * PaymentIntent, Checkout session or subscription next to the form metadata, where the two values
	 * would overwrite each other.
	 *
	 * @param string $key
	 * @param array<string, mixed> $metadata
	 * @return bool
	 */
	private function isMetadataKeyTaken($key, $metadata): bool
	{
		$internalKeys = array_merge(
			PaymentIntentBinding::getBindingMetadataKeys(),
			[
				Customer::METADATA_KEY_PRETAX_BASE_AMOUNT,
				Customer::METADATA_KEY_FEE_RECOVERY_ACCEPTED,
				Customer::METADATA_KEY_RECOVERY_FEE,
				Customer::METADATA_KEY_CHARGE_SIGNATURE,
				DonationFormModel::METADATA_KEY_DONATION_AMOUNT,
				SubscriptionFormModel::METADATA_KEY_SETUP_FEE,
				self::METADATA_KEY_TERMS_OF_USE_ACCEPTED_AT,
				self::METADATA_KEY_TAX_LOCATION,
				Customer::METADATA_KEY_SUBSCRIPTION_ID,
				Customer::METADATA_KEY_INVOICE_ID,
				Customer::METADATA_KEY_CUSTOM_FIELDS_SIGNATURE,
			]
		);

		return array_key_exists($key, $metadata) || in_array($key, $internalKeys, true);
	}

	/**
	 * @return array
	 */
	public function getMetadata()
	{
		$metadata = [];

		if (isset($this->cardHolderEmail)) {
			$metadata['customer_email'] = $this->cardHolderEmail;
		}
		if (isset($this->cardHolderName)) {
			$metadata['customer_name'] = $this->cardHolderName;
		}
		if (isset($this->formName)) {
			$metadata['form_name'] = $this->formName;
		}
		$termsOfUseAcceptedAt = $this->getTermsOfUseAcceptedAtMetadataValue();
		if (!is_null($termsOfUseAcceptedAt)) {
			$metadata[self::METADATA_KEY_TERMS_OF_USE_ACCEPTED_AT] = $termsOfUseAcceptedAt;
		}
		if (isset($this->__form->allowMultipleSubscriptions)) {
			$metadata['allow_multiple_subscriptions'] = $this->__form->allowMultipleSubscriptions;
			if (isset($this->__form->allowMultipleSubscriptions) && 1 == $this->__form->allowMultipleSubscriptions) {
				$metadata['minimum_quantity_of_subscriptions'] = $this->__form->minimumQuantityOfSubscriptions;
				$metadata['maximum_quantity_of_subscriptions'] = $this->__form->maximumQuantityOfSubscriptions;
			}
		}
		if (
			(isset($this->__form->showAddress) && 1 == $this->__form->showAddress)
			|| (isset($this->__form->showBillingAddress) && 1 == $this->__form->showBillingAddress)
		) {
			if (isset($this->billingName)) {
				$metadata['billing_name'] = $this->billingName;
			}
			if (isset($this->billingAddressLine1) || isset($this->billingAddressZip) || isset($this->billingAddressCity) || isset($this->billingAddressCountry)) {
				$metadata['billing_address'] = implode('|', [
					$this->billingAddressLine1,
					$this->billingAddressLine2,
					$this->billingAddressZip,
					$this->billingAddressCity,
					$this->billingAddressState,
					$this->__billingAddressCountryName,
					$this->__billingAddressCountryCode
				]);
			}
		}
		if (isset($this->__form->showShippingAddress) && 1 == $this->__form->showShippingAddress) {
			if (isset($this->shippingName)) {
				$metadata['shipping_name'] = $this->shippingName;
			}
			if (isset($this->shippingAddressLine1) || isset($this->shippingAddressZip) || isset($this->shippingAddressCity) || isset($this->shippingAddressCountry)) {
				$metadata['shipping_address'] = implode('|', [
					$this->shippingAddressLine1,
					$this->shippingAddressLine2,
					$this->shippingAddressZip,
					$this->shippingAddressCity,
					$this->shippingAddressState,
					$this->__shippingAddressCountryName,
					$this->__shippingAddressCountryCode
				]);
			}
		}
		if (is_null($this->__form->customInputs)) {
			$customInputValueString = is_array($this->customInputValues) ? implode(",", $this->customInputValues) : (string) $this->customInputValues;
			if (!empty($customInputValueString)) {
				$metadata['custom_inputs'] = $customInputValueString;
			}
		} else {
			$isJson = CustomFields::isJsonConfig($this->__form->customInputs);
			$defs   = CustomFields::parse($this->__form->customInputs, $this->__form->customInputRequired);
			foreach ($defs as $index => $def) {
				if (!$def->isInteractive()) {
					continue;
				}
				$raw       = $this->getSubmittedCustomFieldValue($def, $index, $isJson);
				$stored    = CustomFields::storedValueFor($def, $raw);
				$metaValue = CustomFields::metadataValueFor($def, $stored);
				if (is_null($metaValue)) {
					continue;
				}
				// Stripe rejects blank metadata keys and keys longer than the key max length,
				// which would fail the whole payment. Skip blank labels, and truncate to the limit
				// BEFORE de-duplicating so two labels that share the first $keyMaxLength characters
				// don't collapse to the same truncated key and silently drop the second value.
				$keyMaxLength = Utils::STRIPE_METADATA_KEY_MAX_LENGTH;
				$baseKey = trim($def->label);
				if ('' === $baseKey) {
					continue;
				}
				$key = $this->truncateMetadataKey($baseKey, $keyMaxLength);
				if ($this->isMetadataKeyTaken($key, $metadata)) {
					$suffix = (string) $index;
					$key = $this->truncateMetadataKey($baseKey, $keyMaxLength - strlen($suffix)) . $suffix;
				}
				if ($this->isMetadataKeyTaken($key, $metadata)) {
					continue;
				}
				$metadata[$key] = $metaValue;
			}
		}

		// users can add custom metadata via filter
		try {
			$user_meta = apply_filters(Main::FILTER_NAME_ADD_TRANSACTION_METADATA, [], $this->getFormName(), $this->getFormGetParametersAsArray());
			$metadata = array_merge($metadata, $user_meta);
		} catch (\Exception $ex) {
			$this->logger->error(__FUNCTION__, 'Cannot apply metadata filter', $ex);
		}

		return $metadata;
	}

	/**
	 * The metadata for the Stripe Customer. It leaves out the terms of use acceptance: one Customer
	 * serves every purchase of the customer, and an existing key is never replaced, so it would
	 * show the first acceptance. The PaymentIntent or Subscription keeps it per purchase.
	 *
	 * @return array<string, mixed>
	 */
	public function getCustomerMetadata(): array
	{
		$metadata = $this->getMetadata();
		unset($metadata[self::METADATA_KEY_TERMS_OF_USE_ACCEPTED_AT]);

		return $metadata;
	}

	/**
	 * @return string
	 */
	public function getCustomFieldsJSON()
	{
		$customFields = [];
		// Mirror getMetadata()'s branch check: only the tnagy single-field legacy
		// form (customInputs IS NULL) uses the positional path; an empty string
		// is an empty typed config and must take the parse() branch below.
		if (is_null($this->__form->customInputs)) {
			if (is_array($this->customInputValues)) {
				foreach ($this->customInputValues as $i => $value) {
					array_push($customFields, $this->createCustomFieldObject(self::CUSTOM_FIELD_IDENTIFIER_PREFIX . ($i + 1), self::CUSTOM_FIELD_IDENTIFIER_PREFIX . ($i + 1), 'text', $value));
				}
			} else if (!empty($this->customInputValues)) {
				array_push($customFields, $this->createCustomFieldObject(self::CUSTOM_FIELD_IDENTIFIER_PREFIX . 1, self::CUSTOM_FIELD_IDENTIFIER_PREFIX . 1, 'text', (string) $this->customInputValues));
			}
		} else {
			$isJson   = CustomFields::isJsonConfig($this->__form->customInputs);
			$defs     = CustomFields::parse($this->__form->customInputs, $this->__form->customInputRequired);
			$position = 0;
			foreach ($defs as $index => $def) {
				// html blocks are display-only and never recorded as a submitted value.
				if (!$def->isInteractive()) {
					continue;
				}
				$position++;
				$raw          = $this->getSubmittedCustomFieldValue($def, $index, $isJson);
				$stored       = CustomFields::storedValueFor($def, $raw);
				$displayValue = CustomFields::displayValueFor($def, $stored);
				$object       = $this->createCustomFieldObject(self::CUSTOM_FIELD_IDENTIFIER_PREFIX . $position, $def->label, $def->type, $stored, $def->id, $displayValue);
				array_push($customFields, $object);
			}
			// After the current fields, so they keep their placeholders.
			foreach ($this->removedCustomFields as $removedCustomField) {
				$position++;
				$object             = clone $removedCustomField;
				$object->identifier = self::CUSTOM_FIELD_IDENTIFIER_PREFIX . $position;
				array_push($customFields, $object);
			}
		}

		return json_encode($customFields);
	}

	/**
	 * Resolve the raw submitted value for a parsed field definition.
	 *
	 * JSON configs key submitted values by stable id; legacy {{ lists key by position.
	 *
	 * @param CustomFieldDef $def
	 * @param int                    $index
	 * @param bool                   $isJson
	 *
	 * @return mixed
	 */
	protected function getSubmittedCustomFieldValue($def, $index, $isJson)
	{
		if (!is_array($this->customInputValues)) {
			return null;
		}
		if ($isJson) {
			return isset($this->customInputValues[$def->id]) ? $this->customInputValues[$def->id] : null;
		}

		return isset($this->customInputValues[$index]) ? $this->customInputValues[$index] : null;
	}

	/**
	 * @param string          $identifier
	 * @param string          $label
	 * @param string          $type
	 * @param string|string[] $value
	 * @param string|null     $id           stable field id (JSON configs)
	 * @param mixed           $displayValue human facing value (option labels, Yes/No, ...)
	 *
	 * @return \stdClass
	 */
	public function createCustomFieldObject($identifier, $label, $type, $value, $id = null, $displayValue = null)
	{
		$customFieldObject = new \StdClass;

		$customFieldObject->identifier = $identifier;
		$customFieldObject->label = $label;
		$customFieldObject->type = $type;
		$customFieldObject->value = $value;
		if (!is_null($id)) {
			$customFieldObject->id = $id;
		}
		if (!is_null($displayValue)) {
			$customFieldObject->displayValue = $displayValue;
		}

		return $customFieldObject;
	}

	/**
	 * @return mixed
	 */
	public function getFormName()
	{
		return $this->formName;
	}

	/**
	 * @return array
	 */
	public function getFormGetParametersAsArray()
	{
		$res = json_decode($this->formGetParameters, true);

		return $res ? $res : [];
	}

	/**
	 * @return \StripeWPFS\Stripe\Customer|null
	 */
	public function getStripeCustomer()
	{
		return $this->__stripeCustomer;
	}

	/**
	 * @param \StripeWPFS\Stripe\Customer|null $stripeCustomer
	 * @param bool $updatePropertiesByCustomer
	 */
	public function setStripeCustomer($stripeCustomer, $updatePropertiesByCustomer = false)
	{
		$this->__stripeCustomer = $stripeCustomer;
		if ($updatePropertiesByCustomer && !is_null($stripeCustomer)) {
			$this->cardHolderEmail = $stripeCustomer->email;
			$this->cardHolderName = $stripeCustomer->name;
			$this->cardHolderPhone = $stripeCustomer->phone;
			$this->billingName = !is_null($this->cardHolderName) ? $this->cardHolderName : null;
			if (isset($stripeCustomer->address)) {
				$this->billingAddressLine1 = $stripeCustomer->address->line1;
				$this->billingAddressLine2 = $stripeCustomer->address->line2;
				$this->billingAddressCity = $stripeCustomer->address->city;
				$this->billingAddressState = $stripeCustomer->address->state;
				$this->billingAddressZip = $stripeCustomer->address->postal_code;
				$this->billingAddressCountry = $stripeCustomer->address->country;
				$this->initBillingAddressCountryComposite();
			}
			if (isset($stripeCustomer->shipping) && isset($stripeCustomer->shipping->address)) {
				$this->shippingName = $stripeCustomer->shipping->name;
				$this->shippingAddressLine1 = $stripeCustomer->shipping->address->line1;
				$this->shippingAddressLine2 = $stripeCustomer->shipping->address->line2;
				$this->shippingAddressCity = $stripeCustomer->shipping->address->city;
				$this->shippingAddressState = $stripeCustomer->shipping->address->state;
				$this->shippingAddressZip = $stripeCustomer->shipping->address->postal_code;
				$this->shippingAddressCountry = $stripeCustomer->shipping->address->country;
				$this->initShippingAddressCountryComposite();
			}
		}
	}

	/**
	 * @return \StripeWPFS\Stripe\PaymentMethod
	 */
	public function getStripePaymentMethod()
	{
		return $this->__stripePaymentMethod;
	}

	/**
	 * @param \StripeWPFS\Stripe\PaymentMethod $stripePaymentMethod
	 */
	public function setStripePaymentMethod($stripePaymentMethod)
	{
		$this->__stripePaymentMethod = $stripePaymentMethod;
	}

	/**
	 * @return mixed
	 */
	public function getSameBillingAndShippingAddress()
	{
		return $this->sameBillingAndShippingAddress;
	}

	/**
	 * @return mixed
	 */
	public function getBillingName()
	{
		return $this->billingName;
	}

	/**
	 * @param mixed $billingName
	 */
	public function setBillingName($billingName)
	{
		$this->billingName = $billingName;
	}

	/**
	 * @return mixed
	 */
	public function getShippingName()
	{
		return $this->shippingName;
	}

	/**
	 * @param mixed $shippingName
	 */
	public function setShippingName($shippingName)
	{
		$this->shippingName = $shippingName;
	}

	/**
	 * @param bool $mayReturnNull
	 *
	 * @return array
	 */
	public function getBillingAddress($mayReturnNull = true)
	{
		return $this->getAddressArray(
			$mayReturnNull,
			$this->billingAddressLine1,
			$this->billingAddressLine2,
			$this->billingAddressCity,
			$this->billingAddressState,
			$this->__billingAddressCountryName,
			$this->__billingAddressCountryCode,
			$this->billingAddressZip
		);
	}

	/**
	 * @param $mayReturnNull
	 * @param $line1
	 * @param $line2
	 * @param $city
	 * @param $state
	 * @param $countryName
	 * @param $countryCode
	 * @param $zip
	 *
	 * @return array|null
	 */
	protected function getAddressArray($mayReturnNull, $line1, $line2, $city, $state, $countryName, $countryCode, $zip)
	{
		$addressData = [
			self::ARRAY_KEY_ADDRESS_LINE_1 => is_null($line1) ? '' : $line1,
			self::ARRAY_KEY_ADDRESS_LINE_2 => is_null($line2) ? '' : $line2,
			self::ARRAY_KEY_ADDRESS_CITY => is_null($city) ? '' : $city,
			self::ARRAY_KEY_ADDRESS_STATE => is_null($state) ? '' : $state,
			self::ARRAY_KEY_ADDRESS_COUNTRY => is_null($countryName) ? '' : $countryName,
			self::ARRAY_KEY_ADDRESS_COUNTRY_CODE => is_null($countryCode) ? '' : $countryCode,
			self::ARRAY_KEY_ADDRESS_ZIP => is_null($zip) ? '' : $zip
		];
		if ($mayReturnNull) {
			$hasNotEmptyValue = false;
			foreach ($addressData as $key => $value) {
				if ($value !== '') {
					$hasNotEmptyValue = true;
				}
			}
			if ($hasNotEmptyValue) {
				return $addressData;
			} else {
				return null;
			}
		}

		return $addressData;
	}

	/**
	 * @param bool $mayReturnNull
	 *
	 * @return array
	 */
	public function getShippingAddress($mayReturnNull = true)
	{
		return $this->getAddressArray(
			$mayReturnNull,
			$this->shippingAddressLine1,
			$this->shippingAddressLine2,
			$this->shippingAddressCity,
			$this->shippingAddressState,
			$this->__shippingAddressCountryName,
			$this->__shippingAddressCountryCode,
			$this->shippingAddressZip
		);
	}

	/**
	 * @return mixed
	 */
	public function getProductName()
	{
		return $this->__productName;
	}

	/**
	 * @param mixed $productName
	 */
	public function setProductName($productName)
	{
		$this->__productName = $productName;
	}

	/**
	 * @param $popupFormSubmit
	 *
	 * @return array|mixed|object
	 */
	public function extractFormModelDataFromPopupFormSubmit($popupFormSubmit)
	{
		$postData = [];
		if (isset($popupFormSubmit) && isset($popupFormSubmit->postData)) {
			$postData = json_decode(
				$popupFormSubmit->postData,
				/* to associative array */
				true
			);
			if (JSON_ERROR_NONE !== json_last_error()) {
				$postData = [];
			}
		}

		return $postData;
	}

	/**
	 * @param $checkoutSession
	 *
	 * @return array
	 */
	public function extractFormModelDataFromCheckoutSession($checkoutSession)
	{
		// todo tnagy extract data from setup intent / subscription's setup intent / payment intent
		$result = [];

		if (isset($checkoutSession)) {
			if (isset($checkoutSession->line_items) && count($checkoutSession->line_items->data) > 0) {
				$lineItem = $checkoutSession->line_items->data[0];
				if (isset($lineItem)) {
					if (isset($lineItem->discounts) && count($lineItem->discounts) > 0) {
						$stripeDiscountObject = $lineItem->discounts[0];
						if (isset($stripeDiscountObject) && isset($stripeDiscountObject->discount)) {
							$stripeDiscount = $stripeDiscountObject->discount;
							if (isset($stripeDiscount)) {
								$result[FormModel::PARAM_WPFS_COUPON] = $stripeDiscount->coupon->id;
							}
						}
					}
				}
			}
			if (isset($checkoutSession->payment_intent)) {
				if ($checkoutSession->payment_intent->id) {
					$paymentIntent = $checkoutSession->payment_intent;
				} else {
					$paymentIntent = $this->__stripe->retrievePaymentIntent($checkoutSession->payment_intent);
				}
				if (isset($paymentIntent->id) && isset($paymentIntent->payment_method)) {
					if ($paymentIntent->payment_method->id) {
						$paymentMethod = $paymentIntent->payment_method;
					} else {
						$paymentMethod = $this->__stripe->retrievePaymentMethod($paymentIntent->payment_method);
					}
					if (
						isset($paymentMethod)
						&& isset($paymentMethod->billing_details)
						&& isset($paymentMethod->billing_details->address)
					) {
						$result[FormModel::PARAM_WPFS_BILLING_NAME] = $paymentMethod->billing_details->name;
						$result[FormModel::PARAM_WPFS_BILLING_ADDRESS_LINE_1] = $paymentMethod->billing_details->address->line1;
						$result[FormModel::PARAM_WPFS_BILLING_ADDRESS_LINE_2] = $paymentMethod->billing_details->address->line2;
						$result[FormModel::PARAM_WPFS_BILLING_ADDRESS_CITY] = $paymentMethod->billing_details->address->city;
						$result[FormModel::PARAM_WPFS_BILLING_ADDRESS_STATE] = $paymentMethod->billing_details->address->state;
						$result[FormModel::PARAM_WPFS_BILLING_ADDRESS_ZIP] = $paymentMethod->billing_details->address->postal_code;
						$result[FormModel::PARAM_WPFS_BILLING_ADDRESS_COUNTRY] = $paymentMethod->billing_details->address->country;
					}
				}
			}
		}
		$result[FormModel::PARAM_WPFS_SHIPPING_NAME] = null;
		$result[FormModel::PARAM_WPFS_SHIPPING_ADDRESS_LINE_1] = null;
		$result[FormModel::PARAM_WPFS_SHIPPING_ADDRESS_LINE_2] = null;
		$result[FormModel::PARAM_WPFS_SHIPPING_ADDRESS_CITY] = null;
		$result[FormModel::PARAM_WPFS_SHIPPING_ADDRESS_STATE] = null;
		$result[FormModel::PARAM_WPFS_SHIPPING_ADDRESS_COUNTRY] = null;
		$result[FormModel::PARAM_WPFS_SHIPPING_ADDRESS_ZIP] = null;

		return $result;
	}

	public function afterBind()
	{
		$this->updateShippingAddress();
	}

	private function updateShippingAddress()
	{
		if (!is_null($this->getForm())) {
			if (1 == $this->sameBillingAndShippingAddress && 1 == $this->getForm()->showShippingAddress) {
				$this->shippingName = $this->billingName;
				$this->shippingAddressLine1 = $this->billingAddressLine1;
				$this->shippingAddressLine2 = $this->billingAddressLine2;
				$this->shippingAddressCity = $this->billingAddressCity;
				$this->shippingAddressState = $this->billingAddressState;
				$this->shippingAddressZip = $this->billingAddressZip;
				$this->shippingAddressCountry = $this->billingAddressCountry;
				$this->initShippingAddressCountryComposite();
			}
		}
	}

	/**
	 * @return mixed
	 */
	public function getForm()
	{
		return $this->__form;
	}

	/**
	 * @param mixed $form
	 */
	public function setForm($form)
	{
		$this->__form = $form;
		$this->prepareFormHash();
	}

	protected function prepareFormHash()
	{
		$formType = Utils::getFormType($this->__form);
		$formId = Utils::getFormId($this->__form);
		$formName = $this->__form->name;
		$this->setFormHash(
			esc_attr(
				Utils::generateFormHash(
					$formType,
					$formId,
					$formName
				)
			)
		);
	}

	/**
	 * @return mixed
	 */
	public function getCouponCode()
	{
		return $this->couponCode;
	}

	/**
	 * @param string $couponCode
	 */
	public function setCouponCode($couponCode)
	{
		$this->couponCode = $couponCode;
	}

	/**
	 * Apply the coupon a transaction was already priced with, looking its discount up again so pricing
	 * applies it too. Forms without the coupon field keep only the code, as the validator leaves them.
	 * Stripe already charged the payment, so a coupon that is gone or no longer valid keeps only its
	 * code, and the payment is still checked and recorded, priced without the discount.
	 *
	 * @param string|null $couponCode
	 */
	public function restoreCouponCode($couponCode): void
	{
		// Replaces any coupon the request posted, which the transaction wasn't priced with.
		$this->couponCode = $couponCode;
		$this->__stripeDiscount = null;
		$this->__stripeCoupon = null;
		$this->__stripeDiscountId = null;
		$this->__stripeDiscountType = null;
		if (empty($couponCode) || empty($this->__form->showCouponInput)) {
			return;
		}

		try {
			$this->prepareStripeCoupon();
		} catch (\Exception $ex) {
			// A coupon that can no longer be found leaves only its code, as before.
			$this->logger->error(__FUNCTION__, 'Cannot look up the coupon the transaction was priced with', $ex);
		}
		// Stripe still returns an expired coupon, but rejects it on a new invoice.
		if (isset($this->__stripeCoupon) && false === $this->__stripeCoupon->valid) {
			$this->logger->error(__FUNCTION__, sprintf('Coupon %s is no longer valid, so it is not restored.', $couponCode));
			$this->__stripeDiscount = null;
			$this->__stripeCoupon = null;
			$this->__stripeDiscountId = null;
			$this->__stripeDiscountType = null;
		}
	}

	/**
	 * @return mixed
	 */
	public function getStripeDiscountId()
	{
		return $this->__stripeDiscountId;
	}

	/**
	 * @return mixed
	 */
	public function getStripeDiscountType()
	{
		return $this->__stripeDiscountType;
	}

	/**
	 * @return \StripeWPFS\Stripe\Coupon|null Null without a coupon.
	 */
	public function getStripeCoupon()
	{
		return $this->__stripeCoupon;
	}

	/**
	 * @param \StripeWPFS\Stripe\Coupon|null $stripeCoupon
	 */
	public function setStripeCoupon($stripeCoupon)
	{
		$this->__stripeCoupon = $stripeCoupon;
	}

	/**
	 * @return \StripeWPFS\Stripe\Coupon
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	protected function retrieveCouponOrPromotionalCode()
	{
		$result = $this->retrievePromotionalCode();
		if (is_null($result)) {
			$result = $this->retrieveCoupon();
		}

		return $result;
	}

	/**
	 * @return \StripeWPFS\Stripe\PromotionCode|null The active promotion code with the coupon code; inactive ones may share it.
	 */
	protected function retrievePromotionalCode()
	{
		try {
			return $this->__stripe->retrievePromotionalCode($this->couponCode, true);
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * @return \StripeWPFS\Stripe\Coupon|null
	 */
	protected function retrieveCoupon()
	{
		try {
			return $this->__stripe->retrieveCoupon($this->couponCode);
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * @return mixed
	 */
	public function getIpAddress()
	{
		return $this->ipAddress;
	}
}
