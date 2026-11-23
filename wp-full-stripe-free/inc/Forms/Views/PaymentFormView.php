<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

use ThemeIsle\WPFullPay\Forms\Fields\ConfigurableFormFields;
use ThemeIsle\WPFullPay\Forms\Fields\FormFieldConfiguration;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Locale\Localization;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\PaymentMethods;
use ThemeIsle\WPFullPay\Pricing\Pricing;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\Utils;

abstract class PaymentFormView extends FormView implements PaymentFormViewConstants {
	use FormViewPricingAddOn;
	use FormViewCouponAddOn;

	/** @var Stripe */
	protected $stripe;

	protected $currencyCode;
	protected $currencySymbol;
	protected $currencyName;
	protected $currencyZeroDecimalSupport;
	/** @var Control|null */
	protected $customAmount;
	/** @var Control|null */
	protected $customAmountOptions;
	/** @var array */
	protected $productPricing;

	/**
	 * PaymentFormView constructor.
	 *
	 * @param $form
	 */
	public function __construct( $form, $fieldConfiguration, $stripe, $loggerService ) {
		parent::__construct( $form, $fieldConfiguration, $loggerService );

		$this->stripe = $stripe;

		$this->currencyCode = null;
		$this->currencyName = null;
		$this->currencySymbol = null;
		$this->currencyZeroDecimalSupport = null;
		$this->customAmount = null;
		$this->customAmountOptions = null;

		$this->prepareCurrency();
		$this->prepareCustomAmountAndOptions();
		$this->prepareProductPricing();
		$this->formatProductPricing();

		$this->attributes = $this->getFormAttributes();

	}

	protected function getCustomAmountForPricing() {
		$result = null;

		/** @var FormFieldConfiguration $amountFieldConfig */
		$amountFieldConfig = $this->fieldConfiguration[ ConfigurableFormFields::FIELD_AMOUNT ];
		$isAmountConfigurable = $amountFieldConfig->isConfigurable() && ! is_null( $amountFieldConfig->getValue() );

		if (
			$isAmountConfigurable && (
				Main::PAYMENT_TYPE_CUSTOM_AMOUNT == $this->form->customAmount ||
				( Main::PAYMENT_TYPE_LIST_OF_AMOUNTS == $this->form->customAmount && 1 == $this->form->allowListOfAmountsCustom ) )
		) {
			$result = $amountFieldConfig->getValue();
		}

		return $result;
	}

	protected function getSelectedPriceId() {
		$result = null;

		if ( Main::PAYMENT_TYPE_CUSTOM_AMOUNT == $this->form->customAmount ) {
			$result = 'customAmount';
		} else if ( Main::PAYMENT_TYPE_SPECIFIED_AMOUNT == $this->form->customAmount ) {
			/** @var Control $control */
			$control = $this->customAmountOptions->options()[0];
			$result = $control->attributes( false )[ self::ATTR_DATA_WPFS_AMOUNT_PRICE_ID ];
		} else if ( Main::PAYMENT_TYPE_LIST_OF_AMOUNTS == $this->form->customAmount ) {
			foreach ( $this->customAmountOptions->options() as $control ) {
				/** @var Control $control */
				$attributes = $control->attributes( false );

				if ( array_key_exists( 'checked', $attributes ) || array_key_exists( 'selected', $attributes ) ) {
					$result = $attributes[ self::ATTR_DATA_WPFS_AMOUNT_PRICE_ID ];
					break;
				}
			}

			if ( is_null( $result ) && ! empty( $this->customAmountOptions->options() ) ) {
				$control = $this->customAmountOptions->options()[0];
				$result = $control->attributes( false )[ self::ATTR_DATA_WPFS_AMOUNT_PRICE_ID ];
			}
		}

		return $result;
	}

	protected function prepareProductPricing() {
		$coupon = $this->prepareCouponData();

		$pricingData = new \StdClass;
		$pricingData->formType = Utils::getFormType( $this->form );
		$pricingData->formId = $this->form->name;
		$pricingData->country = $this->getDefaultCountry();
		$pricingData->state = null;
		$pricingData->zip = null;
		$pricingData->taxIdType = null;
		$pricingData->taxId = null;
		$pricingData->couponCode = is_null( $coupon ) ? null : $coupon->id;
		$pricingData->coupon = $coupon;
		// The opt-in fee checkbox renders unticked.
		$pricingData->feeRecoveryAccepted = false;
		$pricingData->customAmount = $this->getCustomAmountForPricing();
		$pricingData->quantity = 1;
		$pricingData->stripeTax = Utils::getFormType( $this->form ) === Main::FORM_TYPE_INLINE_PAYMENT &&
			$this->form->vatRateType === Main::FIELD_VALUE_TAX_RATE_STRIPE_TAX;

		$this->productPricing = Pricing::createFormPriceCalculator( $pricingData, $this->loggerService )->getProductPrices();
	}

	protected function formatProductLabel( $productName, $amountInCents ) {
		$amountLabel = Currencies::formatAndEscapeByForm( $this->form, $this->currencyCode, $amountInCents, false );
		$taxTicker = $this->getTaxLabel();
		$taxLabel = $this->isCheckoutForm() && ! empty( $taxTicker ) ? ( ' + ' . $taxTicker ) : '';

		switch ( $this->form->amountSelectorStyle ) {
			case Main::SELECTOR_STYLE_BUTTON_GROUP:
				return $amountLabel . $taxLabel;

			case Main::SELECTOR_STYLE_DROPDOWN:
			case Main::SELECTOR_STYLE_RADIO_BUTTONS:
			default:
				return Localization::translateLabel( $productName ) . ' - ' . $amountLabel . $taxLabel;
		}
	}

	protected function formatProductPricing() {
		if ( ! empty( $this->customAmountOptions ) ) {
			foreach ( $this->customAmountOptions->options() as $control ) {
				/** @var Control $control */
				$attributes = $control->attributes( false );

				$priceId = $attributes[ self::ATTR_DATA_WPFS_AMOUNT_PRICE_ID ];
				if ( $priceId === Main::PRICE_ID_CUSTOM_AMOUNT ) {
					continue;
				}

				$amountInCents = $this->calculateGrossAmount( $this->productPricing[ $priceId ] );
				$productLabel = $this->formatProductLabel(
					$attributes[ self::ATTR_DATA_WPFS_PRODUCT_NAME ],
					$amountInCents
				);

				$control->setLabel( $productLabel );
				$attributes[ self::ATTR_DATA_WPFS_AMOUNT_DESCRIPTION ] = $productLabel;
				$attributes[ self::ATTR_DATA_WPFS_AMOUNT_IN_SMALLEST_COMMON_CURRENCY ] = $amountInCents;

				$control->setValue( Currencies::formatAmount( $this->currencyCode, $amountInCents ) );

				$control->setAttributes( $attributes );
			}
		}
	}

	protected function prepareCurrency() {
		$currency = Currencies::getCurrencyFor( $this->form->currency );
		$this->currencyCode = $currency['code'];
		$this->currencyName = $currency['name'];
		$this->currencySymbol = $currency['symbol'];
		$this->currencyZeroDecimalSupport = $currency['zeroDecimalSupport'];
	}

	protected function getAmountButtonTitle() {
		$result = $this->form->buttonTitle;
		if ( $this instanceof CheckoutPaymentFormView || $this instanceof CheckoutSaveCardFormView ) {
			$result = $this->form->openButtonTitle;
		}

		return $result;
	}

	protected function getOptionSelectedAttribute() {
		$result = 'checked';

		if ( Main::SELECTOR_STYLE_DROPDOWN === $this->form->amountSelectorStyle ) {
			$result = 'selected';
		}

		return $result;
	}

	protected function prepareCustomAmountAndOptions() {
		/** @var FormFieldConfiguration $priceFieldConfig */
		$priceFieldConfig = $this->fieldConfiguration[ ConfigurableFormFields::FIELD_PRICE ];

		$isPriceConfigurable = $priceFieldConfig->isConfigurable() && ! is_null( $priceFieldConfig->getValue() );
		$selectedPriceId = $isPriceConfigurable ? $priceFieldConfig->getValue() : null;

		/** @var FormFieldConfiguration $amountFieldConfig */
		$amountFieldConfig = $this->fieldConfiguration[ ConfigurableFormFields::FIELD_AMOUNT ];
		$isAmountConfigurable = $amountFieldConfig->isConfigurable() && ! is_null( $amountFieldConfig->getValue() );
		$isAmountConfigured = false;

		$amountButtonTitle = $this->getAmountButtonTitle();
		$attributes = [
			self::ATTR_DATA_WPFS_BUTTON_TITLE => Localization::translateLabel( $amountButtonTitle ),
			self::ATTR_DATA_WPFS_CURRENCY => $this->currencyCode,
			self::ATTR_DATA_WPFS_ZERO_DECIMAL_SUPPORT => $this->currencyZeroDecimalSupport ? 'true' : 'false',
			self::ATTR_DATA_WPFS_CURRENCY_SYMBOL => $this->currencySymbol,
			self::ATTR_DATA_WPFS_FORM_ID => $this->formHash,
			self::ATTR_DATA_WPFS_SELECTOR_STYLE => $this->form->amountSelectorStyle,
			self::ATTR_DATA_WPFS_PRODUCT_NAME => Utils::getDefaultProductDescription(),
			self::ATTR_DATA_WPFS_FEE_RECOVERY_ENABLED => false,
		];
		if ( Utils::hasFeeRecovery( $this->form ) ) {
			$recoveryFeeData = Utils::getFeeRecoveryData( $this->form );

			$attributes[ self::ATTR_DATA_WPFS_FEE_RECOVERY_ENABLED ] = true;
			$attributes[ self::ATTR_DATA_WPFS_FEE_AMOUNT ]           = $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT ];
			$attributes[ self::ATTR_DATA_WPFS_FEE_PERCENTAGE ]       = $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_PERCENTAGE ];
		}

		if ( Main::PAYMENT_TYPE_CUSTOM_AMOUNT == $this->form->customAmount ) {
			$this->customAmount = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_CUSTOM_AMOUNT_UNIQUE,
				null,
				null,
				/* translators: Form field label for the payment amount that can be entered manually (think paying invoices) */
				__( 'Amount', 'wp-full-stripe-free' ),
				null
			);
			$customAmountOptionAttributes = [
				self::ATTR_DATA_WPFS_AMOUNT_PRICE_ID => 'customAmount',
			];
			$this->customAmount->setAttributes( array_merge( $attributes, $customAmountOptionAttributes ) );
			$this->customAmount->setLabelAttributes(
				[
					'class' => 'wpfs-form-label'
				]
			);
			if ( $isAmountConfigurable ) {
				$this->configureCustomAmountControl( $this->customAmount, $amountFieldConfig );
				$isAmountConfigured = true;
			}

		} elseif (
			Main::PAYMENT_TYPE_LIST_OF_AMOUNTS == $this->form->customAmount ||
			Main::PAYMENT_TYPE_SPECIFIED_AMOUNT == $this->form->customAmount
		) {
			$this->customAmountOptions = ControlUtils::createControl(
				$this->formHash,
				self::FIELD_CUSTOM_AMOUNT,
				null,
				null,
				/* translators: Form field label for selecting a one-time payment amount */
				__( 'Product', 'wp-full-stripe-free' ),
				null
			);
			$this->customAmountOptions->setAttributes( $attributes );

			$listOfProducts = Utils::decodeJsonArray( $this->form->decoratedProducts );
			if ( count( $listOfProducts ) == 0 && Main::PAYMENT_TYPE_SPECIFIED_AMOUNT == $this->form->customAmount ) {
				$this->form->customAmount = Main::PAYMENT_TYPE_LIST_OF_AMOUNTS;
			} else if ( count( $listOfProducts ) == 1 && $this->form->allowListOfAmountsCustom == 0 ) {
				$this->form->customAmount = Main::PAYMENT_TYPE_SPECIFIED_AMOUNT;
			}

			$customAmountOptions = [];
			$lastIndex = -1;
			$index = 0;
			$selectedAttribute = $this->getOptionSelectedAttribute();
			foreach ( $listOfProducts as $product ) {
				$lastIndex = $index;
				$amount = $product->price;
				$descriptionLabel = $this->formatProductLabel( $product->name, $amount );

				$customAmountOption = ControlUtils::createControl(
					$this->formHash,
					self::FIELD_CUSTOM_AMOUNT,
					null,
					null,
					$descriptionLabel,
					$index
				);
				$customAmountOption->setValue( Currencies::formatAmount( $this->currencyCode, $amount ) );
				$optionAttributes = [
					self::ATTR_DATA_WPFS_AMOUNT_IN_SMALLEST_COMMON_CURRENCY => $amount,
					self::ATTR_DATA_WPFS_AMOUNT_INDEX => $index,
					self::ATTR_DATA_WPFS_AMOUNT_DESCRIPTION => $descriptionLabel,
					self::ATTR_DATA_WPFS_AMOUNT_PRICE_ID => $product->stripePriceId,
					self::ATTR_DATA_WPFS_PRODUCT_NAME => Localization::translateLabel( $product->name ),
				];
				if ( ! $isAmountConfigurable && $isPriceConfigurable && $product->stripePriceId == $selectedPriceId ) {
					$optionAttributes[ $selectedAttribute ] = $selectedAttribute;
				}

				$optionAttributes = array_merge( $attributes, $optionAttributes );
				$customAmountOption->setAttributes( $optionAttributes );

				array_push( $customAmountOptions, $customAmountOption );
				$index++;
			}
			if ( 1 == $this->form->allowListOfAmountsCustom ) {
				$this->customAmount = ControlUtils::createControl(
					$this->formHash,
					self::FIELD_CUSTOM_AMOUNT_UNIQUE,
					null,
					null,
					/* translators: Form field label for the payment amount that can be entered manually (think paying invoices) */
					__( 'Amount', 'wp-full-stripe-free' ),
					null
				);
				$customAmountAttributes = array_merge( $attributes, [ self::ATTR_DATA_WPFS_AMOUNT_PRICE_ID => 'customAmount' ] );
				if ( count( $customAmountOptions ) > 0 && $isAmountConfigured ) {
					$customAmountAttributes['disabled'] = true;
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
				$customAmountOption->setValue( 'other' );
				$customAmountOptionAttributes = array_merge( $attributes, [ self::ATTR_DATA_WPFS_AMOUNT_PRICE_ID => 'customAmount' ] );
				$customAmountOption->setAttributes( $customAmountOptionAttributes );

				if ( $isAmountConfigurable ) {
					$this->configureCustomAmountControl( $this->customAmount, $amountFieldConfig );

					$customAmountOption->setAttributes(
						array_merge( $customAmountOption->attributes( false ), [ $selectedAttribute => $selectedAttribute ] )
					);
				}

				array_push( $customAmountOptions, $customAmountOption );
			}

			$this->customAmountOptions->setOptions( $customAmountOptions );

			if ( count( $this->customAmountOptions->options() ) == 0 ) {
				// Tells wpfs.js not to re-enable the button when it re-enables the rest of the form.
				$this->submitButton->setAttributes(
					array_merge(
						$this->submitButton->attributes( false ),
						[
							'disabled' => true,
							self::ATTR_DATA_WPFS_KEEP_DISABLED => 'true',
						]
					)
				);
			}
		}
	}

	/**
	 * @return array
	 */
	protected function getFormAttributes() {
		$attributes = [];

		$attributes[ self::ATTR_DATA_WPFS_AMOUNT_TYPE ] = $this->form->customAmount;
		if ( Main::PAYMENT_TYPE_SPECIFIED_AMOUNT === $this->form->customAmount || Main::PAYMENT_TYPE_CARD_CAPTURE === $this->form->customAmount ) {
			$attributes[ self::ATTR_DATA_WPFS_AMOUNT ] = $this->form->amount;
			$attributes[ self::ATTR_DATA_WPFS_CURRENCY ] = $this->currencyCode;
			$attributes[ self::ATTR_DATA_WPFS_ZERO_DECIMAL_SUPPORT ] = $this->currencyZeroDecimalSupport ? 'true' : 'false';
			$attributes[ self::ATTR_DATA_WPFS_CURRENCY_SYMBOL ] = $this->currencySymbol;
		} elseif ( Main::PAYMENT_TYPE_LIST_OF_AMOUNTS === $this->form->customAmount ) {
			$attributes[ self::ATTR_DATA_WPFS_SELECTOR_STYLE ] = $this->form->amountSelectorStyle;
			$attributes[ self::ATTR_DATA_WPFS_ALLOW_LIST_OF_AMOUNTS_CUSTOM ] = $this->form->allowListOfAmountsCustom;
		}
		$attributes[ self::ATTR_DATA_WPFS_TAX_RATE_TYPE ] = $this->form->vatRateType;
		$attributes[ self::ATTR_DATA_WPFS_SHOW_COUPON_FIELD ] = $this->isCouponFieldVisible() ? 'true' : 'false';
		if ( ! empty( $this->form->paymentMethods ) ) {
			$paymentMethods = is_array( $this->form->paymentMethods )
				? $this->form->paymentMethods
				: json_decode( $this->form->paymentMethods, true );

			// A card-only list still shows Link inside the card form, so the Payment Element must hide it.
			// Decided on the admin's list, before the currency filter can empty it and fall back to Link.
			if ( PaymentMethods::is_link_disabled( $paymentMethods ) ) {
				$attributes[ self::ATTR_DATA_WPFS_STRIPE_LINK ] = 'never';
			}

			if ( is_array( $paymentMethods ) ) {
				// Only expose methods supported for the form's currency. The Payment Element is
				// created with an explicit paymentMethodTypes list, so a currency-incompatible
				// method (e.g. PayNow/GrabPay on a non-SGD form) makes the whole element fail to
				// load — taking card and Link down with it. card/Link have no currency
				// restriction so they always pass this filter.
				$currency = strtolower( (string) $this->form->currency );
				$paymentMethods = array_values( array_filter(
					$paymentMethods,
					function ( $method ) use ( $currency ) {
						return PaymentMethods::is_supported_currency( $method, $currency );
					}
				) );
			}

			// If nothing remains, leave the attribute unset so the client falls back to its
			// default (card/Link) rather than an empty list.
			if ( ! empty( $paymentMethods ) ) {
				$attributes[ self::ATTR_DATA_WPFS_PAYMENT_METHOD_TYPES ] = json_encode( $paymentMethods );
			}
		}

		return array_merge( $attributes, parent::getFormAttributes() );
	}

	/**
	 * @return array
	 */
	public static function getFields() {
		$fields = [
			self::FIELD_CUSTOM_AMOUNT_UNIQUE => ControlUtils::inputGroup( self::FIELD_CUSTOM_AMOUNT_UNIQUE ),
		];

		return array_merge( $fields, parent::getFields() );
	}

	/**
	 * @param $form
	 *
	 * @return string
	 */
	protected function getSubmitButtonCaptionForForm( $form ) {
		$submitButtonCaption = $this->getSubmitButtonRawCaptionForForm( $form );
		$amountLabel = '';

		if ( isset( $form->customAmount ) ) {
			if (
				Main::PAYMENT_TYPE_SPECIFIED_AMOUNT == $form->customAmount ||
				Main::PAYMENT_TYPE_LIST_OF_AMOUNTS == $form->customAmount
			) {
				$listOfProducts = json_decode( (string) $form->decoratedProducts );
				if ( JSON_ERROR_NONE === json_last_error() ) {
					$firstProduct = reset( $listOfProducts );

					$recoveryFeeData = Utils::getFeeRecoveryData( $form );
					$currency        = $form->currency;

					$amount = $firstProduct->price;

					if ( ! empty( $recoveryFeeData ) ) {
						$amount = $amount + Utils::calculateRecoveryFee( $amount, $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_PERCENTAGE ], $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT ], $currency );
					}
					$amountLabel = Currencies::formatAndEscapeByForm( $form, $form->currency, $amount, false );
				}
			}
		}

		return $this->insertAmountIntoLabel( $submitButtonCaption, $amountLabel );
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
	public function customAmountOptions() {
		return $this->customAmountOptions;
	}

	/**
	 * @return array
	 */
	public function getProductPricing(): array {
		return $this->productPricing;
	}
}
