<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

use ThemeIsle\WPFullPay\Forms\Fields\ConfigurableFormFields;
use ThemeIsle\WPFullPay\Forms\Fields\CustomFields;
use ThemeIsle\WPFullPay\Forms\Fields\FormFieldConfiguration;
use ThemeIsle\WPFullPay\Locale\Countries;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Locale\Localization;
use ThemeIsle\WPFullPay\Locale\States;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Utils;

abstract class FormView implements FormViewConstants {
	use LoggerAddOn;

	/** @var Control */
	protected $action;
	/** @var Control */
	protected $formName;
	/** @var Control */
	protected $formGetParameters;
	/** @var Control|null */
	protected $sameBillingAndShippingAddress;
	/** @var Control|null */
	protected $showBillingAddressPanelRadio;
	/** @var Control|null */
	protected $showShippingAddressPanelRadio;
	/** @var Control|null */
	protected $addressSwitcher;
	/** @var Control|null */
	protected $billingAddressPanel;
	/** @var Control|null */
	protected $billingName;
	/** @var Control|null */
	protected $billingAddressLine1;
	/** @var Control|null */
	protected $billingAddressLine2;
	/** @var Control|null */
	protected $billingAddressCity;
	/** @var Control|null */
	protected $billingAddressState;
	/** @var Control|null */
	protected $billingAddressStateSelect;
	/** @var Control|null */
	protected $billingAddressZip;
	/** @var Control|null */
	protected $billingAddressCountry;
	/** @var Control|null */
	protected $shippingAddressPanel;
	/** @var Control|null */
	protected $shippingName;
	/** @var Control|null */
	protected $shippingAddressLine1;
	/** @var Control|null */
	protected $shippingAddressLine2;
	/** @var Control|null */
	protected $shippingAddressCity;
	/** @var Control|null */
	protected $shippingAddressState;
	/** @var Control|null */
	protected $shippingAddressStateSelect;
	/** @var Control|null */
	protected $shippingAddressZip;
	/** @var Control|null */
	protected $shippingAddressCountry;
	/** @var Control */
	protected $coupon;
	/** @var array */
	protected $customInputs;
	/** @var Control */
	protected $feeRecoveryAccepted;
	/** @var Control */
	protected $termsOfUseAccepted;
	/** @var Control */
	protected $submitButton;

	protected $form;
	protected $formHash;
	protected $attributes = [];

	protected $defaultBillingCountry;
	protected $defaultShippingCountry;

	protected $__currentVATPercent;

	/** @var array */
	protected $fieldConfiguration;
	/**
	 * FormView constructor.
	 *
	 * @param $form
	 */
	public function __construct( $form, $fieldConfiguration, $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_CUSTOMER_PORTAL );

		$this->form = $form;
		$this->fieldConfiguration = $fieldConfiguration;

		$this->formHash = $this->getFormHash();
		$this->attributes = $this->getFormAttributes();
		$this->action = ControlUtils::createControl( $this->formHash, self::FIELD_ACTION, null, null, null, null );
		$this->formName = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_NAME, null, null, null, null );
		$this->formGetParameters = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_GET_PARAMETERS, null, null, null, null );
		$this->sameBillingAndShippingAddress = null;
		$this->showBillingAddressPanelRadio = null;
		$this->showShippingAddressPanelRadio = null;
		$this->addressSwitcher = null;
		$this->billingAddressPanel = null;
		$this->billingName = null;
		$this->billingAddressLine1 = null;
		$this->billingAddressLine2 = null;
		$this->billingAddressZip = null;
		$this->billingAddressState = null;
		$this->billingAddressStateSelect = null;
		$this->billingAddressCity = null;
		$this->billingAddressCountry = null;
		$this->shippingAddressPanel = null;
		$this->shippingName = null;
		$this->shippingAddressLine1 = null;
		$this->shippingAddressLine2 = null;
		$this->shippingAddressZip = null;
		$this->shippingAddressState = null;
		$this->shippingAddressStateSelect = null;
		$this->shippingAddressCity = null;
		$this->shippingAddressCountry = null;
		$this->coupon = ControlUtils::createControl(
			$this->formHash,
			self::FIELD_COUPON,
			/* translators: Form field placeholder for coupon code */
			__( 'Enter coupon code', 'wp-full-stripe-free' ),
			null,
			/* translators: Form field label for coupon code */
			__( 'Coupon', 'wp-full-stripe-free' ),
			null
		);
		$this->customInputs = [];
		$feeRecovery = Utils::getFeeRecoveryData( $this->form );
		$feeRecoveryLabel = isset( $feeRecovery[ Options::OPTION_FEE_RECOVERY_CURRENCY ] ) ? $this->formatFeeRecoveryLabel( $feeRecovery[ Options::OPTION_FEE_RECOVERY_CURRENCY ] ) : '';

		$this->feeRecoveryAccepted = ControlUtils::createControl( $this->formHash, self::FIELD_FEE_RECOVERY_ACCEPTED, null, null, $feeRecoveryLabel, null );
		$this->termsOfUseAccepted = ControlUtils::createControl( $this->formHash, self::FIELD_TERMS_OF_USE_ACCEPTED, null, null, $this->form->termsOfUseLabel, null );
		$this->submitButton = ControlUtils::createControl( $this->formHash, FormViewConstants::BUTTON_SUBMIT, null, $this->getSubmitButtonCaptionForForm( $form ), null, null );

		$this->coupon->setTooltip(
			/* translators: Tooltip label for the coupon code field */
			__( 'Apply a coupon or promotional code for a discount to your subscription or payment', 'wp-full-stripe-free' )
		);

		$this->action->setAttributes(
			[
				'type' => 'hidden'
			]
		);
		$this->formName->setAttributes(
			[
				'type' => 'hidden'
			]
		);
		$this->formName->setValue( $this->form->name );
		$this->formGetParameters->setAttributes(
			[
				'type' => 'hidden'
			]
		);
		$this->feeRecoveryAccepted->setLabelEscape( Control::ESCAPE_TYPE_NONE );
		$this->termsOfUseAccepted->setLabelEscape( Control::ESCAPE_TYPE_NONE );

		$this->prepareCustomInputs();
		$this->prepareAddresses();

	}

	/**
	 * The fee recovery opt-in message, with {{fee_amount}} showing the fee in the given currency.
	 *
	 * @param string $currency The currency the fee is charged in.
	 *
	 * @return string
	 */
	protected function formatFeeRecoveryLabel( $currency ) {
		$feeRecovery = Utils::getFeeRecoveryData( $this->form );
		if ( ! isset( $feeRecovery[ Options::OPTION_FEE_RECOVERY_OPT_IN_MESSAGE ] ) || ! isset( $feeRecovery[ Options::OPTION_FEE_RECOVERY_FEE_PERCENTAGE ] ) || ! isset( $feeRecovery[ Options::OPTION_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT ] ) ) {
			return '';
		}

		// The fixed fee as it is charged, see Utils::calculateRecoveryFee().
		$fixedFee = Currencies::convertAmountToMinorUnits( $feeRecovery[ Options::OPTION_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT ], $currency );

		return str_replace(
			'{{fee_amount}}',
			Currencies::format_amount_with_currency( $currency, $fixedFee, true ) . ' + ' . $feeRecovery[ Options::OPTION_FEE_RECOVERY_FEE_PERCENTAGE ] . '%',
			$feeRecovery[ Options::OPTION_FEE_RECOVERY_OPT_IN_MESSAGE ]
		);
	}

	public function getFormHash() {
		$formType = Utils::getFormType( $this->form );
		$formId = Utils::getFormId( $this->form );

		return esc_attr(
			Utils::generateFormHash(
				$formType,
				$formId,
				$this->form->name
			)
		);
	}

	protected function isCheckoutForm() {
		return method_exists( $this, 'getCheckoutFormAttributes' );
	}

	/**
	 * @return array
	 */
	protected function getFormAttributes() {
		$attributes = [];

		$attributes[ self::ATTR_ACTION ] = '';
		$attributes[ self::ATTR_METHOD ] = self::ATTR_METHOD_VALUE_POST;
		$attributes[ self::ATTR_CLASS ] = self::ATTR_CLASS_VALUE_WPFS_FORM_WPFS_W_60;
		$attributes[ self::ATTR_ID ] = Utils::generateCSSFormID( $this->formHash );
		$attributes[ self::ATTR_DATA_WPFS_FORM_ID ] = $this->form->name;
		$attributes[ self::ATTR_DATA_WPFS_FORM_HASH ] = $this->formHash;
		if ( 1 == $this->form->showCustomInput && $this->form->customInputs ) {
			$attributes[ self::ATTR_DATA_WPFS_CUSTOM_INPUT_TITLE ] = $this->form->customInputTitle;
			$attributes[ self::ATTR_DATA_WPFS_CUSTOM_INPUTS ] = $this->form->customInputs;
			$attributes[ self::ATTR_DATA_WPFS_CUSTOM_INPUT_REQUIRED ] = $this->form->customInputRequired;
		}
		if ( isset( $this->form->showAddress ) && 1 == $this->form->showAddress ) {
			$attributes[ self::ATTR_DATA_WPFS_SHOW_ADDRESS ] = $this->form->showAddress;
		}
		if ( isset( $this->form->showBillingAddress ) && 1 == $this->form->showBillingAddress ) {
			$attributes[ self::ATTR_DATA_WPFS_SHOW_BILLING_ADDRESS ] = $this->form->showBillingAddress;
		}
		if ( isset( $this->form->showShippingAddress ) && 1 == $this->form->showShippingAddress ) {
			$attributes[ self::ATTR_DATA_WPFS_SHOW_SHIPPING_ADDRESS ] = $this->form->showShippingAddress;
		}
		$attributes[ self::ATTR_DATA_WPFS_SHOW_TERMS_OF_USE ] = $this->form->showTermsOfUse;
		$attributes[ self::ATTR_DATA_WPFS_TERMS_OF_USE_NOT_CHECKED_ERROR_MESSAGE ] = $this->form->termsOfUseNotCheckedErrorMessage;
		$attributes[ self::ATTR_DATA_WPFS_DECIMAL_SEPARATOR ] = Currencies::getLocaleSetting( $this->form, 'decimalSeparator' );
		$attributes[ self::ATTR_DATA_WPFS_SHOW_CURRENCY_SYMBOL_INSTEAD_OF_CODE ] = Currencies::getLocaleSetting( $this->form, 'showCurrencySymbolInsteadOfCode' );
		$attributes[ self::ATTR_DATA_WPFS_SHOW_CURRENCY_SIGN_AT_FIRST_POSITION ] = Currencies::getLocaleSetting( $this->form, 'showCurrencySignAtFirstPosition' );
		$attributes[ self::ATTR_DATA_WPFS_PUT_WHITESPACE_BETWEEN_CURRENCY_AND_AMOUNT ] = Currencies::getLocaleSetting( $this->form, 'putWhitespaceBetweenCurrencyAndAmount' );
		if ( isset( $this->form->stripeElementsTheme ) ) {
			$attributes[ self::ATTR_DATA_WPFS_ELEMENTS_THEME ] = $this->form->stripeElementsTheme;
		}
		if ( isset( $this->form->stripeElementsFont ) ) {
			$attributes[ self::ATTR_DATA_WPFS_ELEMENTS_FONT ] = $this->form->stripeElementsFont;
		}
		return $attributes;
	}

	/**
	 * @param Control $control
	 * @param FormFieldConfiguration $fieldConfig
	 * @return void
	 */
	protected function configureTextControl( $control, $fieldConfig ) {
		if ( $fieldConfig->isConfigurable() && ! is_null( $fieldConfig->getValue() ) ) {
			$control->setValue( $fieldConfig->getValue() );
			if ( $fieldConfig->isReadonly() ) {
				$control->setAttributes(
					[ 'readonly' => 'readonly' ]
				);
			}
		}
	}

	protected function getSubmitButtonRawCaptionForForm( $form ) {
		$submitButtonCaption = null;

		if (
			$this instanceof CheckoutPaymentFormView ||
			$this instanceof CheckoutSaveCardFormView ||
			$this instanceof CheckoutDonationFormView ||
			$this instanceof CheckoutSubscriptionFormView
		) {
			$submitButtonCaption = Localization::translateLabel( $this->form->openButtonTitle );
		} elseif (
			$this instanceof InlinePaymentFormView ||
			$this instanceof InlineSaveCardFormView ||
			$this instanceof InlineDonationFormView ||
			$this instanceof InlineSubscriptionFormView
		) {
			$submitButtonCaption = Localization::translateLabel( $form->buttonTitle );
		}

		return $submitButtonCaption;
	}

	/**
	 * @param $form
	 *
	 * @return string
	 */
	protected function getSubmitButtonCaptionForForm( $form ) {
		$submitButtonCaption = $this->getSubmitButtonRawCaptionForForm( $form );

		return $this->insertAmountIntoLabel( $submitButtonCaption, '' );
	}

	protected function containsAmountMacro( $label ) {
		return false === strpos( $label, FormViewConstants::MACRO_SUBMIT_BUTTON_CAPTION_AMOUNT ) ? false : true;
	}

	protected function insertAmountIntoLabel( $label, $amountLabel ) {
		if ( is_null( $label ) ) {
			return $label;
		}
		$labelWithAmount = $label;
		if ( false !== strpos( $labelWithAmount, FormViewConstants::MACRO_SUBMIT_BUTTON_CAPTION_AMOUNT ) ) {
			$labelWithAmount = trim( str_replace( FormViewConstants::MACRO_SUBMIT_BUTTON_CAPTION_AMOUNT, $amountLabel, $labelWithAmount ) );
		} else {
			$labelWithAmount .= ' ' . $amountLabel;
		}

		return $labelWithAmount;
	}

	protected function prepareCustomInputs() {
		if ( 1 != $this->form->showCustomInput ) {
			return;
		}

		// tnagy legacy forms have only one custom input field
		if ( is_null( $this->form->customInputs ) ) {
			$control = ControlUtils::createControl( $this->formHash, self::FIELD_CUSTOM_INPUT, null, null, Localization::translateLabel( $this->form->customInputTitle ), null );
			$control->setMetadata(
				[
					self::CUSTOM_FIELD_META_TYPE     => CustomFields::TYPE_TEXT,
					self::CUSTOM_FIELD_META_REQUIRED => ( 1 == $this->form->customInputRequired ),
				]
			);
			array_push( $this->customInputs, $control );

			return;
		}

		$isJson = CustomFields::isJsonConfig( $this->form->customInputs );
		$defs   = CustomFields::parse( $this->form->customInputs, $this->form->customInputRequired );

		foreach ( $defs as $index => $def ) {
			$elementIndex = $isJson ? $def->id : $index;

			if ( CustomFields::TYPE_HTML === $def->type ) {
				$control = ControlUtils::createControl( $this->formHash, self::FIELD_CUSTOM_INPUT, null, null, '', $elementIndex );
				$control->setMetadata(
					[
						self::CUSTOM_FIELD_META_TYPE    => CustomFields::TYPE_HTML,
						self::CUSTOM_FIELD_META_CONTENT => $def->content,
					]
				);
				array_push( $this->customInputs, $control );
				continue;
			}

			$customInputLabel = Localization::translateLabel( $def->label );
			$placeholder      = '' === $def->placeholder ? null : Localization::translateLabel( $def->placeholder );
			$control          = ControlUtils::createControl( $this->formHash, self::FIELD_CUSTOM_INPUT, $placeholder, null, $customInputLabel, $elementIndex );

			// Field name: keyed by stable id for JSON configs, legacy indexed array otherwise.
			if ( $isJson ) {
				$control->setName( self::FIELD_CUSTOM_INPUT . '[' . $def->id . ']' );
				$control->setMultiValue( $def->isMultiValue() );
			} else {
				$control->setMultiValue( true );
			}

			$localizedOptions = [];
			foreach ( $def->options as $option ) {
				$localizedOptions[] = [
					'label' => Localization::translateLabel( $option['label'] ),
					'value' => $option['value'],
				];
			}

			$control->setMetadata(
				[
					self::CUSTOM_FIELD_META_TYPE        => $def->type,
					self::CUSTOM_FIELD_META_REQUIRED    => $def->required,
					self::CUSTOM_FIELD_META_DESCRIPTION => Localization::translateLabel( $def->description ),
					self::CUSTOM_FIELD_META_OPTIONS     => $localizedOptions,
					self::CUSTOM_FIELD_META_SETTINGS    => $def->settings,
					self::CUSTOM_FIELD_META_FIELD_ID    => $def->id,
				]
			);

			$control->setAttributes(
				[
					self::ATTR_DATA_WPFS_CUSTOM_INPUT_FIELD => 'input',
					self::ATTR_DATA_WPFS_CUSTOM_INPUT_LABEL => $customInputLabel,
				]
			);

			// Existing positional prefill/readonly support applies to scalar text-like fields only.
			$configKey = ConfigurableFormFields::FIELD_CUSTOM_PREFIX . ( $index + 1 );
			$prefillTypes = [
				CustomFields::TYPE_TEXT,
				CustomFields::TYPE_PHONE,
				CustomFields::TYPE_NUMBER,
				CustomFields::TYPE_DATE,
				CustomFields::TYPE_TEXTAREA,
				CustomFields::TYPE_SELECT,
			];
			if ( in_array( $def->type, $prefillTypes, true ) && isset( $this->fieldConfiguration[ $configKey ] ) ) {
				/** @var FormFieldConfiguration $customFieldConfig */
				$customFieldConfig = $this->fieldConfiguration[ $configKey ];
				if ( $customFieldConfig->isConfigurable() && ! is_null( $customFieldConfig->getValue() ) ) {
					$control->setValue( $customFieldConfig->getValue() );

					if ( $customFieldConfig->isReadonly() ) {
						$control->setAttributes(
							array_merge( $control->attributes( false ), [ 'readonly' => 'readonly' ] )
						);
					}
				}
			}

			array_push( $this->customInputs, $control );
		}
	}

	protected function prepareCountryFilterParams() {
		return [ 
			'formName' => $this->form->name,
			'formType' => Utils::getFormType( $this->form ),
		];
	}

	protected function prepareBillingCountryOptions( &$selectedCountryCode ) {
		return $this->prepareFilteredCountryOptions( $selectedCountryCode, 'fullstripe_billing_countries' );
	}

	protected function prepareShippingCountryOptions( &$selectedCountryCode ) {
		return $this->prepareFilteredCountryOptions( $selectedCountryCode, 'fullstripe_shipping_countries' );
	}

	protected function prepareFilteredCountryOptions( &$selectedCountryCode, $filterName ) {
		$countryCodes = Countries::getAvailableCountryCodes();

		try {
			$countryCodes = apply_filters(
				$filterName,
				$countryCodes,
				$this->prepareCountryFilterParams()
			);
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Cannot get country options from filter', $ex );
		}

		if ( is_null( $selectedCountryCode ) || ! array_search( $selectedCountryCode, $countryCodes ) ) {
			$selectedCountryCode = count( $countryCodes ) > 0 ? $countryCodes[0] : Main::COUNTRY_CODE_UNITED_STATES;
		}

		return $this->prepareCountryOptions( $countryCodes, $selectedCountryCode );
	}

	protected function prepareCountryOptions( $countryCodes, $selectedCountryCode ) {
		$locallySelectedCountryCode = $selectedCountryCode;
		$availableCountries = Countries::getCountriesByCode( $countryCodes );
		$countryOptions = [];
		$defaultCountrySelected = false;

		if ( count( $availableCountries ) > 1 ) {
			$selectCountryOption = ControlUtils::createControl(
				$this->formHash,
				null,
				null,
				/* translators: Option label for the default option of the country selector dropdown */
				__( 'Select country', 'wp-full-stripe-free' ),
				null,
				null
			);
			$selectCountryOption->setAttributes(
				[
					'disabled' => 'disabled'
				]
			);

			if ( ! array_key_exists( $locallySelectedCountryCode, $availableCountries ) ) {
				$selectCountryOption->setAttributes(
					[
						'disabled' => 'disabled',
						'selected' => 'selected'
					]
				);

				$defaultCountrySelected = true;
			}

			array_push( $countryOptions, $selectCountryOption );
		} elseif ( ! empty( $countryCodes ) ) {
			$locallySelectedCountryCode = $countryCodes[0];
		}

		$countryLabels = Countries::getCountryLabels();
		foreach ( $availableCountries as $countryKey => $country ) {
			$option = ControlUtils::createControl( $this->formHash, null, null, isset( $countryLabels[ $countryKey ] ) ? $countryLabels[ $countryKey ] : $country['name'], null, null );
			if ( ! $defaultCountrySelected && isset( $locallySelectedCountryCode ) && $locallySelectedCountryCode === $country['alpha-2'] ) {
				$defaultCountrySelected = true;
				$option->setAttributes( [ 'selected' => 'selected' ] );
			}
			$option->setValue( $countryKey );
			array_push( $countryOptions, $option );
		}

		return $countryOptions;
	}

	protected function prepareStateOptions( $selectedStateCode ) {
		$stateOptions = [];

		$selectStateOption = ControlUtils::createControl(
			$this->formHash,
			null,
			null,
			/* translators: Option label for the default option of the country selector dropdown */
			__( 'Select state', 'wp-full-stripe-free' ),
			null,
			null
		);
		$selectStateOption->setAttributes(
			[
				'disabled' => 'disabled'
			]
		);
		array_push( $stateOptions, $selectStateOption );
		$defaultStateSelected = false;
		foreach ( States::getAvailableStates() as $stateKey => $state ) {
			$option = ControlUtils::createControl( $this->formHash, null, null, Localization::translateLabel( $state['name'] ), null, null );
			if ( isset( $selectedStateCode ) && $selectedStateCode === $state['code'] ) {
				$defaultStateSelected = true;
				$option->setAttributes( [ 'selected' => 'selected' ] );
			}
			$option->setValue( $stateKey );
			array_push( $stateOptions, $option );
		}
		if ( false === $defaultStateSelected ) {
			$selectStateOption->setAttributes(
				[
					'disabled' => 'disabled',
					'selected' => 'selected'
				]
			);
		}

		return $stateOptions;
	}

	/**
	 * Get Default Billing Country
	 * 
	 * @return string
	 */
	protected function inheritDefaultCountry() {
		if ( isset( $this->form->defaultBillingCountry ) && ! empty( $this->form->defaultBillingCountry ) ) {
			return $this->form->defaultBillingCountry;
		}

		$options = new Options();
		
		return $options->get( Options::OPTION_DEFAULT_BILLING_COUNTRY );
	}

	protected function prepareAddresses() {
		$this->defaultBillingCountry = $this->inheritDefaultCountry();
		$this->defaultShippingCountry = $this->inheritDefaultCountry();

		if ( $this->showAddressSwitcher() ) {
			$this->sameBillingAndShippingAddress = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_SAME_BILLING_AND_SHIPPING_ADDRESS,
				null,
				null,
				/* translators: Label for a checkbox which controls whether billing and shipping address can be entered separately  */
				__( 'Same billing and shipping address', 'wp-full-stripe-free' ),
				null
			);
			$this->addressSwitcher = ControlUtils::createControl( $this->formHash, self::FIELD_ADDRESS_SWITCHER, null, null, null, null );
			$this->showBillingAddressPanelRadio = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_ADDRESS_SWITCHER,
				null,
				null,
				/* translators: Form field label for billing address */
				__( 'Billing address', 'wp-full-stripe-free' ),
				0
			);
			$this->showShippingAddressPanelRadio = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_ADDRESS_SWITCHER,
				null,
				null,
				/* translators: Form field label for shipping address */
				__( 'Shipping address', 'wp-full-stripe-free' ),
				1
			);
		}

		if ( isset( $this->form->showAddress ) && 1 == $this->form->showAddress ) {
			$defaultBillingCountry = $this->defaultBillingCountry;
			/** @var FormFieldConfiguration $billingCountryField */
			$billingCountryField = $this->fieldConfiguration[ ConfigurableFormFields::FIELD_BILLING_COUNTRY_CODE ];

			if ( $billingCountryField->isConfigurable() && ! is_null( $billingCountryField->getValue() ) ) {
				$defaultBillingCountry = $billingCountryField->getValue();
			}
			$countryOptions = $this->prepareBillingCountryOptions( $defaultBillingCountry );
			$this->defaultBillingCountry = $defaultBillingCountry;

			$this->billingAddressPanel = ControlUtils::createControl( $this->formHash, self::FIELD_BILLING_ADDRESS_PANEL, null, null, null, null );

			$this->billingName = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_BILLING_NAME,
				null,
				null,
				/* translators: Form field label for the billing name */
				__( 'Billing name', 'wp-full-stripe-free' ),
				null
			);
			$this->configureTextControl( $this->billingName, $this->fieldConfiguration[ ConfigurableFormFields::FIELD_BILLING_NAME ] );

			$this->billingAddressLine1 = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_BILLING_ADDRESS_LINE1,
				null,
				null,
				/* translators: Form field label for the billing address */
				__( 'Billing address street', 'wp-full-stripe-free' ),
				null
			);
			$this->configureTextControl( $this->billingAddressLine1, $this->fieldConfiguration[ ConfigurableFormFields::FIELD_BILLING_ADDRESS ] );

			$this->billingAddressLine2 = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_BILLING_ADDRESS_LINE2,
				null,
				null,
				/* translators: Form field label for the billing address line 2 */
				__( 'Billing address line 2', 'wp-full-stripe-free' ),
				null
			);
			$this->configureTextControl( $this->billingAddressLine2, $this->fieldConfiguration[ ConfigurableFormFields::FIELD_BILLING_ADDRESS2 ] );

			$this->billingAddressZip = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_BILLING_ADDRESS_ZIP,
				null,
				null,
				/* translators: Form field label for the postal code of the billing address */
				__( 'Postal code', 'wp-full-stripe-free' ),
				null
			);
			$this->configureTextControl( $this->billingAddressZip, $this->fieldConfiguration[ ConfigurableFormFields::FIELD_BILLING_ZIP ] );

			$this->billingAddressState = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_BILLING_ADDRESS_STATE,
				null,
				null,
				/* translators: Form field label for the state of the billing address */
				__( 'State', 'wp-full-stripe-free' ),
				null
			);
			$this->billingAddressStateSelect = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_BILLING_ADDRESS_STATE_SELECT,
				null,
				null,
				__( 'State', 'wp-full-stripe-free' ),
				null
			);

			$defaultBillingState = null;
			if ( $defaultBillingCountry == Main::COUNTRY_CODE_UNITED_STATES ) {
				/** @var FormFieldConfiguration $billingStateField */
				$billingStateField = $this->fieldConfiguration[ ConfigurableFormFields::FIELD_BILLING_STATE ];
				if ( $billingStateField->isConfigurable() && ! is_null( $billingStateField->getValue() ) ) {
					$defaultBillingState = States::getStateCodeByName( $billingStateField->getValue() );
				}
			} else {
				$this->configureTextControl( $this->billingAddressState, $this->fieldConfiguration[ ConfigurableFormFields::FIELD_BILLING_STATE ] );
			}
			$this->billingAddressStateSelect->setOptions( $this->prepareStateOptions( $defaultBillingState ) );

			$this->billingAddressCity = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_BILLING_ADDRESS_CITY,
				null,
				null,
				/* translators: Form field label for the city of the billing address */
				__( 'City', 'wp-full-stripe-free' ),
				null
			);
			$this->configureTextControl( $this->billingAddressCity, $this->fieldConfiguration[ ConfigurableFormFields::FIELD_BILLING_CITY ] );

			$this->billingAddressCountry = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_BILLING_ADDRESS_COUNTRY,
				null,
				null,
				/* translators: Form field label for the country of the billing address */
				__( 'Country', 'wp-full-stripe-free' ),
				null
			);
			$this->billingAddressCountry->setOptions( $countryOptions );

			if ( isset( $this->form->vatRateType ) ) {
				$this->billingAddressCountry->setAttributes( [ self::ATTR_DATA_WPFS_VAT_RATE_TYPE => $this->form->vatRateType ] );
			}
		}
		if ( isset( $this->form->showShippingAddress ) && 1 == $this->form->showShippingAddress ) {
			$defaultShippingCountry = $this->defaultShippingCountry;
			/** @var FormFieldConfiguration $shippingCountryField */
			$shippingCountryField = $this->fieldConfiguration[ ConfigurableFormFields::FIELD_SHIPPING_COUNTRY_CODE ];

			if ( $shippingCountryField->isConfigurable() && ! is_null( $shippingCountryField->getValue() ) ) {
				$defaultShippingCountry = $shippingCountryField->getValue();
			}
			$countryOptions = $this->prepareShippingCountryOptions( $defaultShippingCountry );
			$this->defaultShippingCountry = $defaultShippingCountry;

			$this->shippingAddressPanel = ControlUtils::createControl( $this->formHash, self::FIELD_SHIPPING_ADDRESS_PANEL, null, null, null, null );

			$this->shippingName = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_SHIPPING_NAME,
				null,
				null,
				/* translators: Form field label for the shipping name */
				__( 'Shipping name', 'wp-full-stripe-free' ),
				null
			);
			$this->configureTextControl( $this->shippingName, $this->fieldConfiguration[ ConfigurableFormFields::FIELD_SHIPPING_NAME ] );

			$this->shippingAddressLine1 = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_SHIPPING_ADDRESS_LINE1,
				null,
				null,
				/* translators: Form field label for the shipping address */
				__( 'Shipping address street', 'wp-full-stripe-free' ),
				null
			);
			$this->configureTextControl( $this->shippingAddressLine1, $this->fieldConfiguration[ ConfigurableFormFields::FIELD_SHIPPING_ADDRESS ] );

			$this->shippingAddressLine2 = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_SHIPPING_ADDRESS_LINE2,
				null,
				null,
				/* translators: Form field label for the shipping address line 2 */
				__( 'Shipping address line 2', 'wp-full-stripe-free' ),
				null
			);
			$this->configureTextControl( $this->shippingAddressLine2, $this->fieldConfiguration[ ConfigurableFormFields::FIELD_SHIPPING_ADDRESS2 ] );

			$this->shippingAddressZip = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_SHIPPING_ADDRESS_ZIP,
				null,
				null,
				/* translators: Form field label for the postal code of the shipping address */
				__( 'Postal code', 'wp-full-stripe-free' ),
				null
			);
			$this->configureTextControl( $this->shippingAddressZip, $this->fieldConfiguration[ ConfigurableFormFields::FIELD_SHIPPING_ZIP ] );

			$this->shippingAddressState = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_SHIPPING_ADDRESS_STATE,
				null,
				null,
				/* translators: Form field label for the state of the shipping address */
				__( 'State', 'wp-full-stripe-free' ),
				null
			);
			$this->shippingAddressStateSelect = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_SHIPPING_ADDRESS_STATE_SELECT,
				null,
				null,
				__( 'State', 'wp-full-stripe-free' ),
				null
			);

			$defaultShippingState = null;
			if ( $defaultShippingCountry == Main::COUNTRY_CODE_UNITED_STATES ) {
				/** @var FormFieldConfiguration $shippingStateField */
				$shippingStateField = $this->fieldConfiguration[ ConfigurableFormFields::FIELD_SHIPPING_STATE ];
				if ( $shippingStateField->isConfigurable() && ! is_null( $shippingStateField->getValue() ) ) {
					$defaultShippingState = States::getStateCodeByName( $shippingStateField->getValue() );
				}
			} else {
				$this->configureTextControl( $this->shippingAddressState, $this->fieldConfiguration[ ConfigurableFormFields::FIELD_SHIPPING_STATE ] );
			}
			$this->shippingAddressStateSelect->setOptions( $this->prepareStateOptions( $defaultShippingState ) );

			$this->shippingAddressCity = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_SHIPPING_ADDRESS_CITY,
				null,
				null,
				/* translators: Form field label for the city of the shipping address */
				__( 'City', 'wp-full-stripe-free' ),
				null
			);
			$this->configureTextControl( $this->shippingAddressCity, $this->fieldConfiguration[ ConfigurableFormFields::FIELD_SHIPPING_CITY ] );

			$this->shippingAddressCountry = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_SHIPPING_ADDRESS_COUNTRY,
				null,
				null,
				/* translators: Form field label for the country of the shipping address */
				__( 'Country', 'wp-full-stripe-free' ),
				null
			);
			$this->shippingAddressCountry->setOptions( $countryOptions );
		}
	}

	/**
	 * @return bool
	 */
	protected function showAddressSwitcher() {
		$showBillingAddress = false;
		$showShippingAddress = false;
		if ( isset( $this->form->showAddress ) && 1 == $this->form->showAddress ) {
			$showBillingAddress = true;
		} elseif ( isset( $this->form->showBillingAddress ) && 1 == $this->form->showBillingAddress ) {
			$showBillingAddress = true;
		}
		if ( isset( $this->form->showShippingAddress ) && 1 == $this->form->showShippingAddress ) {
			$showShippingAddress = true;
		}

		return $showBillingAddress && $showShippingAddress;
	}

	/**
	 * @return bool
	 */
	protected function showAnyAddress() {
		$showAnyAddress = false;
		if ( isset( $this->form->showAddress ) && 1 == $this->form->showAddress ) {
			$showAnyAddress = true;
		}
		if (
			false === $showAnyAddress
			&& ( isset( $this->form->showBillingAddress ) && 1 == $this->form->showBillingAddress )
		) {
			$showAnyAddress = true;
		}
		if (
			false === $showAnyAddress
			&& ( isset( $this->form->showShippingAddress ) && 1 == $this->form->showShippingAddress )
		) {
			$showAnyAddress = true;
		}

		return $showAnyAddress;
	}

	/**
	 * Note: billing and shipping address fields must be added in child classes manually.
	 *
	 * @return array
	 */
	public static function getFields() {
		$fields = [
			self::FIELD_ACTION => ControlUtils::inputHidden( self::FIELD_ACTION ),
			self::FIELD_FORM_NAME => ControlUtils::inputHidden( self::FIELD_FORM_NAME ),
			self::FIELD_CUSTOM_INPUT => ControlUtils::customInput( self::FIELD_CUSTOM_INPUT ),
			self::FIELD_COUPON => ControlUtils::inputGroup( self::FIELD_COUPON ),
			self::FIELD_TERMS_OF_USE_ACCEPTED => ControlUtils::checkbox( self::FIELD_TERMS_OF_USE_ACCEPTED ),
			self::FIELD_FEE_RECOVERY_ACCEPTED => ControlUtils::checkbox( self::FIELD_FEE_RECOVERY_ACCEPTED ),
			self::FIELD_GOOGLE_RECAPTCHA_RESPONSE => ControlUtils::captcha( self::FIELD_GOOGLE_RECAPTCHA_RESPONSE ),
			self::FIELD_NONCE => ControlUtils::inputHidden( self::FIELD_NONCE )
		];

		return $fields;
	}

	protected static function getPopupBillingAddressFields() {
		$fields = [
			self::FIELD_BILLING_ADDRESS_LINE1 => ControlUtils::inputHidden( self::FIELD_BILLING_ADDRESS_LINE1 ),
			self::FIELD_BILLING_ADDRESS_LINE2 => ControlUtils::inputHidden( self::FIELD_BILLING_ADDRESS_LINE2 ),
			self::FIELD_BILLING_ADDRESS_ZIP => ControlUtils::inputHidden( self::FIELD_BILLING_ADDRESS_ZIP ),
			self::FIELD_BILLING_ADDRESS_STATE => ControlUtils::inputHidden( self::FIELD_BILLING_ADDRESS_STATE ),
			self::FIELD_BILLING_ADDRESS_CITY => ControlUtils::inputHidden( self::FIELD_BILLING_ADDRESS_CITY ),
			self::FIELD_BILLING_ADDRESS_COUNTRY => ControlUtils::inputHidden( self::FIELD_BILLING_ADDRESS_COUNTRY ),
		];

		return $fields;
	}

	protected static function getInlineSameBillingAndShippingAddressField() {
		return [
			self::FIELD_SAME_BILLING_AND_SHIPPING_ADDRESS => ControlUtils::checkbox( self::FIELD_SAME_BILLING_AND_SHIPPING_ADDRESS )
		];
	}

	protected static function getInlineBillingAddressFields() {
		$fields = [
			self::FIELD_BILLING_NAME => ControlUtils::input( self::FIELD_BILLING_NAME ),
			self::FIELD_BILLING_ADDRESS_LINE1 => ControlUtils::input( self::FIELD_BILLING_ADDRESS_LINE1 ),
			self::FIELD_BILLING_ADDRESS_LINE2 => ControlUtils::input( self::FIELD_BILLING_ADDRESS_LINE2 ),
			self::FIELD_BILLING_ADDRESS_ZIP => ControlUtils::input( self::FIELD_BILLING_ADDRESS_ZIP ),
			self::FIELD_BILLING_ADDRESS_STATE => ControlUtils::input( self::FIELD_BILLING_ADDRESS_STATE ),
			self::FIELD_BILLING_ADDRESS_STATE_SELECT => ControlUtils::selectMenu( self::FIELD_BILLING_ADDRESS_STATE_SELECT ),
			self::FIELD_BILLING_ADDRESS_CITY => ControlUtils::input( self::FIELD_BILLING_ADDRESS_CITY ),
			self::FIELD_BILLING_ADDRESS_COUNTRY => ControlUtils::selectMenu( self::FIELD_BILLING_ADDRESS_COUNTRY ),
		];

		return $fields;
	}

	protected static function getInlineShippingAddressFields() {
		$fields = [
			self::FIELD_SHIPPING_NAME => ControlUtils::input( self::FIELD_SHIPPING_NAME ),
			self::FIELD_SHIPPING_ADDRESS_LINE1 => ControlUtils::input( self::FIELD_SHIPPING_ADDRESS_LINE1 ),
			self::FIELD_SHIPPING_ADDRESS_LINE2 => ControlUtils::input( self::FIELD_SHIPPING_ADDRESS_LINE2 ),
			self::FIELD_SHIPPING_ADDRESS_ZIP => ControlUtils::input( self::FIELD_SHIPPING_ADDRESS_ZIP ),
			self::FIELD_SHIPPING_ADDRESS_STATE => ControlUtils::input( self::FIELD_SHIPPING_ADDRESS_STATE ),
			self::FIELD_SHIPPING_ADDRESS_STATE_SELECT => ControlUtils::selectMenu( self::FIELD_SHIPPING_ADDRESS_STATE_SELECT ),
			self::FIELD_SHIPPING_ADDRESS_CITY => ControlUtils::input( self::FIELD_SHIPPING_ADDRESS_CITY ),
			self::FIELD_SHIPPING_ADDRESS_COUNTRY => ControlUtils::selectMenu( self::FIELD_SHIPPING_ADDRESS_COUNTRY ),
		];

		return $fields;
	}

	public function isCouponFieldVisible() {
		$result = isset( $this->form->showCouponInput ) && 1 == $this->form->showCouponInput && method_exists( $this, 'coupon' );
		if ( $this instanceof CheckoutSubscriptionFormView || $this instanceof CheckoutPaymentFormView ) {
			$result = false;
		}

		return $result;
	}

	/**
	 * @return mixed
	 */
	public function getFormName() {
		return ( isset( $this->form ) && isset( $this->form->name ) ) ? $this->form->name : null;
	}

	public function _formName() {
		echo ( ( isset( $this->form ) && isset( $this->form->name ) ) ? esc_attr( $this->form->name ) : null );
	}

	/**
	 * @return array
	 */
	public function customInputs() {
		return $this->customInputs;
	}

	/**
	 * @return Control
	 */
	public function coupon() {
		return $this->coupon;
	}

	/**
	 * @return Control
	 */
	public function feeRecoveryAccepted() {
		return $this->feeRecoveryAccepted;
	}

	/**
	 * @return Control
	 */
	public function tOUAccepted() {
		return $this->termsOfUseAccepted;
	}

	/**
	 * @return Control
	 */
	public function submitButton() {
		return $this->submitButton;
	}

	/**
	 * @return Control
	 */
	public function sameBillingAndShippingAddress() {
		return $this->sameBillingAndShippingAddress;
	}

	/**
	 * @return Control|null
	 */
	public function addressSwitcher() {
		return $this->addressSwitcher;
	}

	/**
	 * @return Control|null
	 */
	public function billingAddressRadio() {
		return $this->showBillingAddressPanelRadio;
	}

	/**
	 * @return Control|null
	 */
	public function shippingAddressRadio() {
		return $this->showShippingAddressPanelRadio;
	}

	/**
	 * @return Control|null
	 */
	public function billingAddressPanel() {
		return $this->billingAddressPanel;
	}

	/**
	 * @return Control|null
	 */
	public function billingName() {
		return $this->billingName;
	}

	/**
	 * @return Control|null
	 */
	public function billingAddressLine1() {
		return $this->billingAddressLine1;
	}

	/**
	 * @return Control|null
	 */
	public function billingAddressLine2() {
		return $this->billingAddressLine2;
	}

	/**
	 * @return Control|null
	 */
	public function billingAddressCity() {
		return $this->billingAddressCity;
	}

	/**
	 * @return Control|null
	 */
	public function billingAddressState() {
		return $this->billingAddressState;
	}

	/**
	 * @return Control
	 */
	public function billingAddressStateSelect(): Control {
		return $this->billingAddressStateSelect;
	}

	/**
	 * @return Control|null
	 */
	public function billingAddressZip() {
		return $this->billingAddressZip;
	}

	/**
	 * @return Control|null
	 */
	public function billingAddressCountry() {
		return $this->billingAddressCountry;
	}

	/**
	 * @return Control|null
	 */
	public function shippingAddressPanel() {
		return $this->shippingAddressPanel;
	}

	/**
	 * @return Control|null
	 */
	public function shippingName() {
		return $this->shippingName;
	}

	/**
	 * @return Control|null
	 */
	public function shippingAddressLine1() {
		return $this->shippingAddressLine1;
	}

	/**
	 * @return Control|null
	 */
	public function shippingAddressLine2() {
		return $this->shippingAddressLine2;
	}

	/**
	 * @return Control|null
	 */
	public function shippingAddressCity() {
		return $this->shippingAddressCity;
	}

	/**
	 * @return Control|null
	 */
	public function shippingAddressState() {
		return $this->shippingAddressState;
	}

	/**
	 * @return Control
	 */
	public function shippingAddressStateSelect(): Control {
		return $this->shippingAddressStateSelect;
	}

	/**
	 * @return Control|null
	 */
	public function shippingAddressZip() {
		return $this->shippingAddressZip;
	}

	/**
	 * @return Control|null
	 */
	public function shippingAddressCountry() {
		return $this->shippingAddressCountry;
	}

	public function formAttributes() {
		$attributesAsString = Control::attributesAsString( $this->attributes );

		echo ( $attributesAsString );
	}

	/**
	 * @return Control
	 */
	public function action() {
		return $this->action;
	}

	/**
	 * @return Control
	 */
	public function formName() {
		return $this->formName;
	}

	/**
	 * @return Control
	 */
	public function formGetParameters() {
		return $this->formGetParameters;
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
	public function getDefaultShippingCountry() {
		return $this->defaultShippingCountry;
	}

	/**
	 * @param Control $control
	 * @param FormFieldConfiguration $fieldConfig
	 * @return void
	 */
	protected function configureCustomAmountControl( $control, $fieldConfig ) {
		if ( $fieldConfig->isConfigurable() && ! is_null( $fieldConfig->getValue() ) ) {
			$control->setValue(
				Currencies::formatByForm( $this->form, $this->form->currency, $fieldConfig->getValue(), false, false )
			);
			if ( $fieldConfig->isReadonly() ) {
				$control->setAttributes(
					array_merge( $control->attributes( false ), [ 'readonly' => 'readonly' ] )
				);
			}
		}
	}

	protected function calculateGrossAmount( $displayItems ) {
		$result = 0;

		foreach ( $displayItems as $displayItem ) {
			if (
				$displayItem->subType === Main::LINE_ITEM_SUBTYPE_TAX &&
				$displayItem->inclusive === true
			) {
				continue;
			}
			// The product's price leaves out the recovery fee the preview lists with it.
			if ( Main::LINE_ITEM_TYPE_FEE_RECOVERY === $displayItem->type ) {
				continue;
			}

			$result += $displayItem->amount;
		}

		return $result;
	}

	protected function calculateGrossAmountForComponent( $displayItems, $component ) {
		$result = 0;

		foreach ( $displayItems as $displayItem ) {
			if ( $displayItem->type === $component ) {
				if (
					$displayItem->subType === Main::LINE_ITEM_SUBTYPE_TAX &&
					$displayItem->inclusive === true
				) {
					continue;
				}

				$result += $displayItem->amount;
			}
		}

		return $result;
	}
}
