<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Forms\Fields\ConfigurableFormFields;
use ThemeIsle\WPFullPay\Forms\Fields\FormFieldConfiguration;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Locale\Localization;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Utils;

abstract class DonationFormView extends FormView implements DonationFormViewConstants {

	protected $currencyCode;
	protected $currencySymbol;
	protected $currencyName;
	protected $currencyZeroDecimalSupport;

	protected $customAmount;
	/** @var Control|null */
	protected $donationAmountOptions;
	/** @var Control|null */
	protected $donationFrequencyOptions;
	protected $donationGoal;

	/**
	 * PaymentFormView constructor.
	 *
	 * @param $form
	 */
	public function __construct( $form, $fieldConfiguration, $loggerService ) {
		parent::__construct( $form, $fieldConfiguration, $loggerService );

		$this->currencyCode = null;
		$this->currencyName = null;
		$this->currencySymbol = null;
		$this->currencyZeroDecimalSupport = null;
		$this->customAmount = null;
		$this->donationAmountOptions = null;
		$this->donationFrequencyOptions = null;
		$this->donationGoal = null;

		$this->prepareCurrency();
		$this->prepareDonationAmountOptions();
		$this->prepareDonationFrequencyOptions();
		$this->prepareDonationGoal();

		$this->attributes = $this->getFormAttributes();

	}

	protected function prepareCurrency() {
		$currency = Currencies::getCurrencyFor( $this->form->currency );
		$this->currencyCode = $currency['code'];
		$this->currencyName = $currency['name'];
		$this->currencySymbol = $currency['symbol'];
		$this->currencyZeroDecimalSupport = $currency['zeroDecimalSupport'];
	}

	protected function getAmountButtonTitle() {
		$amountButtonTitle = $this->form->buttonTitle;
		if ( $this instanceof CheckoutDonationFormView ) {
			$amountButtonTitle = $this->form->openButtonTitle;
		}

		return $amountButtonTitle;
	}

	protected function getSubmitButtonCaptionForForm( $form ) {
		$caption = $this->getAmountButtonTitle();

		if ( $this->containsAmountMacro( $caption ) ) {
			$donationAmounts = Utils::decodeJsonArray( $this->form->donationAmounts );
			if ( count( $donationAmounts ) > 0 ) {
				$donationAmount = (int) $donationAmounts[0];
				$recoveryFeeData = Utils::getFeeRecoveryData( $form );

				if ( ! empty ( $recoveryFeeData ) ) {
					$currency = $form->currency;	
					$donationAmount = $donationAmount + Utils::calculateRecoveryFee( $donationAmount, $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_PERCENTAGE ], $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT ], $currency );	
				}
				$amountLabel = Currencies::formatAndEscapeByForm( $this->form, $this->form->currency, $donationAmount, false );

				$caption = $this->insertAmountIntoLabel( $caption, $amountLabel );
			}
		}

		return $caption;
	}

	protected function createDonationFrequencyOption( $label, $value, $index ) {
		$frequencyOption = ControlUtils::createControl( $this->formHash, self::FIELD_DONATION_FREQUENCY, null, null, $label, $index );
		$frequencyOption->setValue( $value );
		$frequencyAttributes = [];
		$frequencyOption->setAttributes( $frequencyAttributes );
		$frequencyOption->setLabel( $label );

		return $frequencyOption;
	}

	/**
	 * @return bool
	 */
	protected function isRecurringDonationForm() {
		$isRecurringDonation = $this->form->allowDailyRecurring == 1 ||
			$this->form->allowWeeklyRecurring == 1 ||
			$this->form->allowMonthlyRecurring == 1 ||
			$this->form->allowAnnualRecurring == 1;

		return $isRecurringDonation;
	}

	/**
	 * @return bool
	 */
	public function isCustomAmountOnly() {
		$res = false;

		if (
			! is_null( $this->donationAmountOptions ) &&
			! is_null( $this->donationAmountOptions->options() )
		) {
			if ( count( $this->donationAmountOptions->options() ) == 1 ) {
				/* @var $onlyOption Control */
				$onlyOption = $this->donationAmountOptions->options()[0];

				if ( DonationFormViewConstants::FIELD_VALUE_CUSTOM_AMOUNT_OTHER === $onlyOption->value( false ) ) {
					$res = true;
				}
			}
		}

		return $res;
	}

	/**
	 * @return bool
	 */
	public function isOneSuggestedAmountOnly() {
		$res = false;

		if (
			! is_null( $this->donationAmountOptions ) &&
			! is_null( $this->donationAmountOptions->options() )
		) {
			if ( count( $this->donationAmountOptions->options() ) == 1 ) {
				/* @var $onlyOption Control */
				$onlyOption = $this->donationAmountOptions->options()[0];

				if ( DonationFormViewConstants::FIELD_VALUE_CUSTOM_AMOUNT_OTHER !== $onlyOption->value( false ) ) {
					$res = true;
				}
			}
		}

		return $res;
	}

	protected function prepareDonationFrequencyOptions() {
		$donationFrequencyAttributes = [];

		$this->donationFrequencyOptions = ControlUtils::createControl(
			$this->formHash,
			self::FIELD_DONATION_FREQUENCY,
			null,
			null,
			/* translators: Form field label for selecting a one-time donation amount */
			__( 'Donation frequency', 'wp-full-stripe-free' ),
			null
		);
		$this->donationFrequencyOptions->setAttributes( $donationFrequencyAttributes );

		$donationFrequencyOptions = [];
		$frequencyOptionIndex = 0;

		if ( $this->form->allowOneTimeDonation == 1 ) {
			$oneTimeOption = $this->createDonationFrequencyOption(
				__( 'One-time', 'wp-full-stripe-free' ),
				self::FIELD_VALUE_DONATION_FREQUENCY_ONE_TIME,
				$frequencyOptionIndex
			);
			array_push( $donationFrequencyOptions, $oneTimeOption );
			$frequencyOptionIndex += 1;
		}

		if ( $this->isRecurringDonationForm() ) {
			// Let's add the frequencies selected by the the user
			if ( $this->form->allowDailyRecurring == 1 ) {
				$dailyOption = $this->createDonationFrequencyOption(
					__( 'Daily', 'wp-full-stripe-free' ),
					self::FIELD_VALUE_DONATION_FREQUENCY_DAILY,
					$frequencyOptionIndex
				);
				array_push( $donationFrequencyOptions, $dailyOption );
				$frequencyOptionIndex += 1;
			}
			if ( $this->form->allowWeeklyRecurring == 1 ) {
				$weeklyOption = $this->createDonationFrequencyOption(
					__( 'Weekly', 'wp-full-stripe-free' ),
					self::FIELD_VALUE_DONATION_FREQUENCY_WEEKLY,
					$frequencyOptionIndex
				);
				array_push( $donationFrequencyOptions, $weeklyOption );
				$frequencyOptionIndex += 1;
			}
			if ( $this->form->allowMonthlyRecurring == 1 ) {
				$monthlyOption = $this->createDonationFrequencyOption(
					__( 'Monthly', 'wp-full-stripe-free' ),
					self::FIELD_VALUE_DONATION_FREQUENCY_MONTHLY,
					$frequencyOptionIndex
				);
				array_push( $donationFrequencyOptions, $monthlyOption );
				$frequencyOptionIndex += 1;
			}
			if ( $this->form->allowAnnualRecurring == 1 ) {
				$annualOption = $this->createDonationFrequencyOption(
					__( 'Annual', 'wp-full-stripe-free' ),
					self::FIELD_VALUE_DONATION_FREQUENCY_ANNUAL,
					$frequencyOptionIndex
				);
				array_push( $donationFrequencyOptions, $annualOption );
			}
		}

		$this->donationFrequencyOptions->setOptions( $donationFrequencyOptions );
	}

	protected function getOptionSelectedAttribute() {
		return 'checked';
	}

	protected function prepareDonationAmountOptions() {
		$amountButtonTitle = $this->getAmountButtonTitle();

		/** @var FormFieldConfiguration $amountFieldConfig */
		$amountFieldConfig = $this->fieldConfiguration[ ConfigurableFormFields::FIELD_AMOUNT ];
		$isAmountConfigurable = $amountFieldConfig->isConfigurable() && ! is_null( $amountFieldConfig->getValue() );
		$isAmountConfigured = false;

		$attributes = [
			self::ATTR_DATA_WPFS_BUTTON_TITLE => Localization::translateLabel( $amountButtonTitle ),
			self::ATTR_DATA_WPFS_CURRENCY => $this->currencyCode,
			self::ATTR_DATA_WPFS_ZERO_DECIMAL_SUPPORT => $this->currencyZeroDecimalSupport ? 'true' : 'false',
			self::ATTR_DATA_WPFS_CURRENCY_SYMBOL => $this->currencySymbol,
			self::ATTR_DATA_WPFS_FORM_ID => $this->formHash,
			self::ATTR_DATA_WPFS_SHOW_AMOUNT => $this->containsAmountMacro( $amountButtonTitle ) ? 1 : 0,
			self::ATTR_DATA_WPFS_FEE_RECOVERY_ENABLED => false,

		];
		if ( Utils::hasFeeRecovery( $this->form ) ) {
			$recoveryFeeData = Utils::getFeeRecoveryData( $this->form );

			$attributes[ self::ATTR_DATA_WPFS_FEE_RECOVERY_ENABLED ] = true;
			$attributes[ self::ATTR_DATA_WPFS_FEE_AMOUNT ]           = $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT ];
			$attributes[ self::ATTR_DATA_WPFS_FEE_PERCENTAGE ]       = $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_PERCENTAGE ];
		}

		$this->donationAmountOptions = ControlUtils::createControl(
			$this->formHash,
			self::FIELD_CUSTOM_AMOUNT,
			null,
			null,
			/* translators: Form field label for selecting a one-time donation amount */
			__( 'Donation amount', 'wp-full-stripe-free' ),
			null
		);
		$this->donationAmountOptions->setAttributes( $attributes );

		$donationAmountOptions = [];
		$donationAmountsArray = Utils::decodeJsonArray( $this->form->donationAmounts );
		$selectedAttribute = $this->getOptionSelectedAttribute();
		$lastIndex = -1;
		foreach ( $donationAmountsArray as $index => $listElement ) {
			$lastIndex = $index;
			$donationAmount = (int) $listElement;

			if ( $donationAmount !== 0 ) {
				$amountLabel = Currencies::formatAndEscapeByForm( $this->form, $this->currencyCode, $donationAmount, false );
				$customAmountOption = ControlUtils::createControl( $this->formHash, self::FIELD_CUSTOM_AMOUNT, null, null, $amountLabel, $index );
				$formattedAmount = Currencies::formatAmount( $this->currencyCode, $donationAmount );
				$customAmountOption->setValue( $formattedAmount );
				$optionAttributes = [
					self::ATTR_DATA_WPFS_AMOUNT_IN_SMALLEST_COMMON_CURRENCY => $donationAmount,
					self::ATTR_DATA_WPFS_AMOUNT_INDEX => $index,
				];

				if ( $isAmountConfigurable && $donationAmount == $amountFieldConfig->getValue() ) {
					$optionAttributes[ $selectedAttribute ] = $selectedAttribute;
					$isAmountConfigured = true;
				}

				$optionAttributes = array_merge( $attributes, $optionAttributes );
				$customAmountOption->setAttributes( $optionAttributes );
				$customAmountOption->setLabel( $amountLabel );
				array_push( $donationAmountOptions, $customAmountOption );
			}
		}

		if ( 1 == $this->form->allowCustomDonationAmount ) {
			$this->customAmount = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_CUSTOM_AMOUNT_UNIQUE,
				null,
				null,
				/* translators: Form field label for the donation amount that can be entered manually */
				__( 'Amount', 'wp-full-stripe-free' ),
				null
			);
			$customAmountAttributes = $attributes;
			if ( count( $donationAmountOptions ) > 0 && $isAmountConfigured ) {
				$customAmountAttributes = array_merge( $customAmountAttributes, [ 'disabled' => true ] );
			}
			$this->customAmount->setAttributes( $customAmountAttributes );
			$this->customAmount->setLabelAttributes(
				[
					'class' => 'wpfs-sr-only'
				]
			);
			$customAmountOption = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_CUSTOM_AMOUNT,
				null,
				null,
				/* translators: Button label for entering a payment amount manually (think donation form) */
				__( 'Other', 'wp-full-stripe-free' ),
				$lastIndex + 1
			);
			$customAmountOption->setValue( DonationFormViewConstants::FIELD_VALUE_CUSTOM_AMOUNT_OTHER );
			$customAmountOption->setAttributes( $attributes );

			if ( $isAmountConfigurable && ! $isAmountConfigured ) {
				$this->configureCustomAmountControl( $this->customAmount, $amountFieldConfig );

				$customAmountOption->setAttributes(
					array_merge( $customAmountOption->attributes( false ), [ $selectedAttribute => $selectedAttribute ] )
				);
			}

			array_push( $donationAmountOptions, $customAmountOption );
		}

		$this->donationAmountOptions->setOptions( $donationAmountOptions );
	}

	protected function prepareDonationGoal() {
		if ( ! isset( $this->form->showDonationGoal ) || ! $this->form->showDonationGoal ) {
			$this->donationGoal = null;

			return;
		}

		$db       = new Database();
		$formType = Utils::getFormType( $this->form );
		$formId   = Main::FORM_TYPE_CHECKOUT_DONATION == $formType ? $this->form->checkoutDonationFormID : $this->form->donationFormID;
		$donated  = $db->getTotalDonationsByFormId( $formId, Utils::getFormType( $this->form ) );
		$goal     = isset( $this->form->donationGoal ) ? $this->form->donationGoal : 0;
		$percent  = $goal > 0 ? round( $donated / $goal * 100, 2 ) : 0;
		$goal     = Currencies::formatByForm( $this->form, $this->form->currency, $goal, false, true );
		$donated  = Currencies::formatByForm( $this->form, $this->form->currency, $donated, false, true );

		$this->donationGoal = [
			'goal'    => $goal,
			'donated' => $donated,
			'percent' => $percent,
		];
	}

	/**
	 * @return array
	 */
	protected function getFormAttributes() {
		$attributes = [];

		$attributes[ self::ATTR_DATA_WPFS_CURRENCY ] = $this->currencyCode;
		$attributes[ self::ATTR_DATA_WPFS_ZERO_DECIMAL_SUPPORT ] = $this->currencyZeroDecimalSupport ? 'true' : 'false';
		$attributes[ self::ATTR_DATA_WPFS_CURRENCY_SYMBOL ] = $this->currencySymbol;
		$attributes[ self::ATTR_DATA_WPFS_AMOUNT_TYPE ] = $this->isCustomAmountOnly() ? Main::PAYMENT_TYPE_CUSTOM_AMOUNT : Main::PAYMENT_TYPE_LIST_OF_AMOUNTS;
		$attributes[ self::ATTR_DATA_WPFS_ALLOW_LIST_OF_AMOUNTS_CUSTOM ] = $this->form->allowCustomDonationAmount;
		$attributes[ self::ATTR_DATA_WPFS_SELECTOR_STYLE ] = Main::SELECTOR_STYLE_BUTTON_GROUP;
		$attributes[ self::ATTR_DATA_WPFS_FEE_RECOVERY_ENABLED ] = false;

		if ( Utils::hasFeeRecovery( $this->form ) ) {
			$recoveryFeeData = Utils::getFeeRecoveryData( $this->form );

			$attributes[ self::ATTR_DATA_WPFS_FEE_RECOVERY_ENABLED ] = true;
			$attributes[ self::ATTR_DATA_WPFS_FEE_AMOUNT ]           = $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT ];
			$attributes[ self::ATTR_DATA_WPFS_FEE_PERCENTAGE ]       = $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_PERCENTAGE ];
		}

		return array_merge( $attributes, parent::getFormAttributes() );
	}

	/**
	 * @return array
	 */
	public static function getFields() {
		$fields = [
			self::FIELD_CUSTOM_AMOUNT_UNIQUE => ControlUtils::inputGroup( self::FIELD_CUSTOM_AMOUNT_UNIQUE )
		];

		return array_merge( $fields, parent::getFields() );
	}

	public function _currencySign() {
		if ( 1 == Currencies::getLocaleSetting( $this->form, 'showCurrencySymbolInsteadOfCode' ) ) {
			$this->_currencySymbol();
		} else {
			$this->_currencyCode();
		}
	}

	public function _currencySymbol() {
		echo ( esc_attr( $this->currencySymbol ) );
	}

	public function _currencyCode() {
		echo ( esc_attr( $this->currencyCode ) );
	}

	public function showCurrencySignAtFirstPosition() {
		return 1 == Currencies::getLocaleSetting( $this->form, 'showCurrencySignAtFirstPosition' );
	}

	/**
	 * @return Control|null
	 */
	public function customAmount() {
		return $this->customAmount;
	}

	/**
	 * @return Control|null
	 */
	public function donationAmountOptions() {
		return $this->donationAmountOptions;
	}

	/**
	 * @return Control|null
	 */
	public function donationFrequencyOptions() {
		return $this->donationFrequencyOptions;
	}

	/**
	 * @return array
	 */
	public function donationGoal() {
		return $this->donationGoal;
	}

}
