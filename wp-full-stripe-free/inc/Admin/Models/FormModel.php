<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Views\FormViewConstants;
use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Forms\Fields\CustomFields;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Models\Model;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;

abstract class FormModel implements Binder {
	use Model;
	use LoggerAddOn;
	use StaticContextAddOn;

	const PARAM_CUSTOM_INPUTS = 'customInputs';
	const PARAM_FORM_DEFAULT_BILLING_COUNTRY = 'form_default_billing_country';

	protected $id;
	protected $name;
	protected $displayName;
	protected $buttonLabel;
	protected $redirectType;
	protected $redirectPageOrPostId;
	protected $redirectURl;
	protected $inheritLocale;
	protected $localeDecimalSeparator;
	protected $localeUseSymbolNotCode;
	protected $localeCurrencySymbolAtFirstPosition;
	protected $localePutSpaceBetweenSymbolAndAmount;
	protected $showTermsOfUse;
	protected $termsOfUseLabel;
	protected $termsOfUseErrorMessage;
	protected $customFields;
	protected $makeCustomFieldsRequired;
	protected $emailTemplatesHidden;
	protected $emailTemplates;
	protected $webhook;
	protected $stripeElementsTheme;
	protected $stripeElementsFont;
    protected $feeRecovery;
    protected $feeRecoveryOptIn;
    protected $feeRecoveryOptInMessage;
    protected $feeRecoveryFeePercentage;
    protected $feeRecoveryFeeAdditionalAmount;
	protected $formHash;
	protected $options;

	public function __construct( $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_ADMIN );
		$this->options = new Options();

		$this->initStaticContext();
	}

	public function bind() {
		return $this->bindByArray( $_POST );
	}

	public function bindByArray( $postData ) {
		$bindingResult = new BindingResult();

		$this->id = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_ID );
		$this->name = $this->getSanitizedArrayParam( $postData, \ThemeIsle\WPFullPay\Forms\Views\FormViewConstants::FIELD_FORM_NAME );
		$this->displayName = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_DISPLAY_NAME );

		$this->buttonLabel = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_BUTTON_LABEL,
			/* translators: Default payment button text on one-time payment forms */
			__( 'Pay', 'wp-full-stripe-free' ) );

		$this->redirectType = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_REDIRECT_TYPE, Main::REDIRECT_TYPE_SHOW_CONFIRMATION_MESSAGE );
		$this->redirectPageOrPostId = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_REDIRECT_PAGE_POST_ID );
		$this->redirectURl = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_REDIRECT_CUSTOM_URL );

		// Bound raw (not text-sanitized) so JSON structure and html block content survive;
		// CustomFields::normalizeFromAdminJson() performs per-field sanitization in getData().
		$this->customFields = $this->getArrayParam( $postData, FormViewConstants::FIELD_FORM_CUSTOM_FIELDS );
		$this->makeCustomFieldsRequired = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_MAKE_CUSTOM_FIELDS_REQUIRED, 0 );

		$this->webhook = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_WEBHOOK );

		$this->showTermsOfUse = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_SHOW_TERMS_OF_SERVICE, 0 );
		$this->termsOfUseLabel = $this->getArrayParam( $postData, FormViewConstants::FIELD_FORM_TERMS_OF_SERVICE_LABEL );
		$this->termsOfUseErrorMessage = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_TERMS_OF_SERVICE_ERROR );

		$this->inheritLocale = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_INHERIT_LOCALE, 0 );
		$this->localeDecimalSeparator = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_LOCALE_DECIMAL_SEPARATOR, Main::DECIMAL_SEPARATOR_SYMBOL_COMMA );
		$this->localeUseSymbolNotCode = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_LOCALE_SYMBOL_NOT_CODE, 1 );
		$this->localeCurrencySymbolAtFirstPosition = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_LOCALE_SYMBOL_AT_FIRST_POSITION, 1 );
		$this->localePutSpaceBetweenSymbolAndAmount = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_LOCALE_SPACE_BETWEEN_SYMBOL_AND_AMOUNT, 0 );

		$this->emailTemplatesHidden = $this->getJSONDecodedArrayParam( $postData, FormViewConstants::FIELD_FORM_EMAIL_TEMPLATES );
		$this->stripeElementsTheme = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_STRIPE_ELEMENTS_THEME_SELECTOR );
		$this->stripeElementsFont = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_STRIPE_ELEMENTS_FONT_SELECTOR );

		$this->feeRecovery = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_FEE_RECOVERY, 0 );
		$this->feeRecoveryOptIn = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_FEE_RECOVERY_OPT_IN, 1 );
		$this->feeRecoveryOptInMessage = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_FEE_RECOVERY_OPT_IN_MESSAGE );
		$this->feeRecoveryFeePercentage = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_FEE_RECOVERY_FEE_PERCENTAGE );
		$this->feeRecoveryFeeAdditionalAmount = $this->getSanitizedArrayParam( $postData, FormViewConstants::FIELD_FORM_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT );

        return $bindingResult;
	}

	/**
	 * Validates the bound form and runs afterBind(). Each form type's bindByArray() ends with it,
	 * after binding its own fields, so the validator sees every field.
	 */
	protected function finishBind( BindingResult $bindingResult ): BindingResult {
		if ( isset( $this->__validator ) ) {
			$this->__validator->validate( $bindingResult, $this );
		}

		$this->afterBind();

		return $bindingResult;
	}

	public function afterBind() {
	}

	public function getData() {

		$data = [
			'name' => $this->name,
			'displayName' => $this->displayName,
			'buttonTitle' => $this->buttonLabel,
			'showCustomInput' => '1',
			'customInputRequired' => $this->makeCustomFieldsRequired,
			'customInputs' => CustomFields::normalizeFromAdminJson( $this->customFields, Main::getCustomFieldMaxCount( $this->staticContext ) ),
			'redirectOnSuccess' => $this->redirectType !== Main::REDIRECT_TYPE_SHOW_CONFIRMATION_MESSAGE ? '1' : '0',
			'redirectPostID' => $this->redirectPageOrPostId,
			'redirectUrl' => $this->redirectURl,
			'redirectToPageOrPost' => $this->redirectType === Main::REDIRECT_TYPE_TO_PAGE_OR_POST ? '1' : '0',
			'showTermsOfUse' => $this->showTermsOfUse,
			'termsOfUseLabel' => $this->termsOfUseLabel,
			'termsOfUseNotCheckedErrorMessage' => $this->termsOfUseErrorMessage,
			'inheritLocale' => $this->inheritLocale,
			'decimalSeparator' => $this->localeDecimalSeparator,
			'showCurrencySymbolInsteadOfCode' => $this->localeUseSymbolNotCode,
			'showCurrencySignAtFirstPosition' => $this->localeCurrencySymbolAtFirstPosition,
			'putWhitespaceBetweenCurrencyAndAmount' => $this->localePutSpaceBetweenSymbolAndAmount,
			'emailTemplates' => $this->emailTemplates,
			'webhook' => $this->webhook,
			'stripeElementsTheme' => $this->stripeElementsTheme,
			'stripeElementsFont' => $this->stripeElementsFont,
			'feeRecovery' => $this->feeRecovery,
			'feeRecoveryOptIn' => $this->feeRecoveryOptIn,
			'feeRecoveryOptInMessage' => $this->feeRecoveryOptInMessage,
			'feeRecoveryFeePercentage' => $this->feeRecoveryFeePercentage,
			'feeRecoveryFeeAdditionalAmount' => $this->feeRecoveryFeeAdditionalAmount,
			// Legacy fields
			'showDetailedSuccessPage' => 1,
			'sendEmailReceipt' => 0
		];

		return $data;
	}

	public function getPostData() {
		// tnagy unsupported operation
		return [];
	}

	/**
	 * @return mixed
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return mixed
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return mixed
	 */
	public function getDisplayName() {
		return $this->displayName;
	}

	/**
	 * @return mixed
	 */
	public function getButtonLabel() {
		return $this->buttonLabel;
	}

	/**
	 * @return mixed
	 */
	public function getRedirectType() {
		return $this->redirectType;
	}

	/**
	 * @return mixed
	 */
	public function getRedirectURl() {
		return $this->redirectURl;
	}

	/**
	 * @return mixed
	 */
	public function getInheritLocale() {
		return $this->inheritLocale;
	}

	/**
	 * @return mixed
	 */
	public function getLocaleDecimalSeparator() {
		return $this->localeDecimalSeparator;
	}

	/**
	 * @return mixed
	 */
	public function getLocaleUseSymbolNotCode() {
		return $this->localeUseSymbolNotCode;
	}

	/**
	 * @return mixed
	 */
	public function getLocaleCurrencySymbolAtFirstPosition() {
		return $this->localeCurrencySymbolAtFirstPosition;
	}

	/**
	 * @return mixed
	 */
	public function getLocalePutSpaceBetweenSymbolAndAmount() {
		return $this->localePutSpaceBetweenSymbolAndAmount;
	}

	/**
	 * @return mixed
	 */
	public function getShowTermsOfUse() {
		return $this->showTermsOfUse;
	}

	/**
	 * @return mixed
	 */
	public function getTermsOfUseLabel() {
		return $this->termsOfUseLabel;
	}

	/**
	 * @return mixed
	 */
	public function getTermsOfUseErrorMessage() {
		return $this->termsOfUseErrorMessage;
	}

	/**
	 * @return mixed
	 */
	public function getEmailTemplatesHidden() {
		return $this->emailTemplatesHidden;
	}

	/**
	 * @param mixed $emailTemplates
	 */
	public function setEmailTemplates( $emailTemplates ) {
		$this->emailTemplates = $emailTemplates;
	}

	/**
	 * @return mixed
	 */
	public function getWebhook() {
		return $this->webhook;
	}

	/**
	 * @return mixed
	 */
	public function getFeeRecovery() {
		return $this->feeRecovery;
	}

	/**
	 * @return mixed
	 */
	public function getFeeRecoveryOptIn() {
		return $this->feeRecoveryOptIn;
	}

	/**
	 * @return mixed
	 */
	public function getFeeRecoveryOptInMessage() {
		return $this->feeRecoveryOptInMessage;
	}

	/**
	 * @return mixed
	 */
	public function getFeeRecoveryFeePercentage() {
		return $this->feeRecoveryFeePercentage;
	}

	/**
	 * @return mixed
	 */
	public function getFeeRecoveryFeeAdditionalAmount() {
		return $this->feeRecoveryFeeAdditionalAmount;
	}

	/**
	 * @return mixed
	 */
	public function getFormHash() {
		return $this->formHash;
	}

	/**
	 * @param mixed $formHash
	 */
	public function setFormHash( $formHash ) {
		$this->formHash = $formHash;
	}

}
