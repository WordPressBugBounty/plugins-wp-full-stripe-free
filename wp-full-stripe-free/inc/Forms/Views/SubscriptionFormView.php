<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

use ThemeIsle\WPFullPay\Forms\Fields\ConfigurableFormFields;
use ThemeIsle\WPFullPay\Forms\Fields\FormFieldConfiguration;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Locale\Localization;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Pricing\Pricing;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\Utils;

abstract class SubscriptionFormView extends FormView implements SubscriptionFormViewConstants {
	use FormViewPricingAddOn;
	use FormViewCouponAddOn;

	/** @var Stripe */
	protected $stripe;

	/** @var array $stripePlans */
	protected $stripePlans;
	/** @var list<\stdClass> $selectedStripePlans Plans returned by Utils::getSortedFormPlans() */
	protected $selectedStripePlans = [];
	/** @var array<string, string> The fee recovery opt-in of each plan, in its currency, by price ID. */
	protected $feeRecoveryLabels = [];
	/** @var Control */
	protected $plans;
	/** @var Control */
	protected $firstPlan;
	/** @var Control */
	protected $planQuantity;
	/** @var array */
	protected $productPricing;

	/**
	 * SubscriptionFormView constructor.
	 *
	 * @param $form
	 * @param $stripe
	 */
	public function __construct( $form, $fieldConfiguration, $stripe, $loggerService ) {
		parent::__construct( $form, $fieldConfiguration, $loggerService );

		if ( ! $stripe instanceof Stripe ) {
			throw new \Exception( 'Stripe must be an instance of ' . Stripe::class );
		}

		$this->stripe = $stripe;

		$this->stripePlans = $this->stripe->getRecurringPrices();
		$this->plans = ControlUtils::createControl(
			$this->formHash,
			self::FIELD_PLAN,
			null,
			null,
			/* translators: Form field label for the plan selector */
			__( 'Choose subscription plan', 'wp-full-stripe-free' ),
			null
		);
		$this->planQuantity = ControlUtils::createControl(
			$this->formHash,
			self::FIELD_PLAN_QUANTITY,
			null,
			null,
			/* translators: Form field label for subscription quantity */
			__( 'Subscription quantity', 'wp-full-stripe-free' ),
			null
		);
		$this->plans->setAttributes(
			[
				self::ATTR_DATA_WPFS_FORM_ID => $this->form->name
			]
		);

		$this->preparePlans();
		$this->prepareFirstPlan();
		$this->prepareFeeRecoveryLabel();
		$this->preparePlanQuantity();
		$this->prepareProductPricing();
		$this->formatProductPricing();
	}

	protected function getSelectedPriceId() {
		$result = null;

		foreach ( $this->plans->options() as $control ) {
			/** @var Control $control */
			$attributes = $control->attributes( false );

			if ( array_key_exists( 'checked', $attributes ) || array_key_exists( 'selected', $attributes ) ) {
				$result = $attributes[ self::ATTR_DATA_WPFS_VALUE ];
				break;
			}
		}

		if ( is_null( $result ) && ! empty( $this->plans->options() ) ) {
			$control = $this->plans->options()[0];
			$result = $control->attributes( false )[ self::ATTR_DATA_WPFS_VALUE ];
		}

		return $result;
	}

	protected function formatProductLabel( $productName, $priceLabel ) {
		$taxTicker = $this->getTaxLabel();
		$taxLabel = $this->isCheckoutForm() && ! empty( $taxTicker ) ? ( ' + ' . $taxTicker ) : '';

		return $productName . ' - ' . $priceLabel . $taxLabel;
	}

	protected function formatProductPricing() {
		foreach ( $this->plans->options() as $control ) {
			/** @var Control $control */
			$attributes = $control->attributes( false );

			$priceId = $attributes[ self::ATTR_DATA_WPFS_VALUE ];
			$currency = $attributes[ self::ATTR_DATA_WPFS_CURRENCY ];
			$interval = $attributes[ self::ATTR_DATA_WPFS_INTERVAL ];
			$intervalCount = $attributes[ self::ATTR_DATA_WPFS_INTERVAL_COUNT ];

			$setupFeeInCents = $this->calculateGrossAmountForComponent( $this->productPricing[ $priceId ], Main::LINE_ITEM_TYPE_SETUP_FEE );
			$productPriceInCents = $this->calculateGrossAmountForComponent( $this->productPricing[ $priceId ], Main::LINE_ITEM_TYPE_PRODUCT );

			$amountLabel = Localization::getPriceAndIntervalLabel(
				$interval,
				$intervalCount,
				Currencies::formatAndEscapeByForm( $this->form, $currency, $productPriceInCents, true, true )
			);
			$productLabel = $this->formatProductLabel( $attributes[ self::ATTR_DATA_WPFS_PRODUCT_NAME ], $amountLabel );

			$control->setLabel( $productLabel );
			$attributes[ self::ATTR_DATA_WPFS_PLAN_AMOUNT ] = Currencies::formatAmount( $currency, $productPriceInCents, true );
			$attributes[ self::ATTR_DATA_WPFS_PLAN_AMOUNT_IN_SMALLEST_COMMON_CURRENCY ] = $productPriceInCents;
			$attributes[ self::ATTR_DATA_WPFS_PLAN_SETUP_FEE ] = Currencies::formatAmount( $currency, $setupFeeInCents, true );
			$attributes[ self::ATTR_DATA_WPFS_PLAN_SETUP_FEE_IN_SMALLEST_COMMON_CURRENCY ] = $setupFeeInCents;

			$control->setAttributes( $attributes );
		}
	}

	protected function prepareProductPricing() {
		$coupon = $this->prepareCouponData();

		if ( $this->form->allowMultipleSubscriptions == 1 ) {
			$defaultQuantity = $this->form->minimumQuantityOfSubscriptions + 0;
			if ( $defaultQuantity < 1 ) {
				$defaultQuantity = 1;
			}
		} else {
			$defaultQuantity = 1;
		}

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
		$pricingData->customAmount = null;
		$pricingData->quantity = $defaultQuantity;
		$pricingData->stripeTax = Utils::getFormType( $this->form ) === Main::FORM_TYPE_INLINE_SUBSCRIPTION &&
			$this->form->vatRateType === Main::FIELD_VALUE_TAX_RATE_STRIPE_TAX;

		$this->productPricing = Pricing::createFormPriceCalculator( $pricingData, $this->loggerService )->getProductPrices();
	}

	protected function getOptionSelectedAttribute() {
		$result = 'checked';

		if ( Main::PLAN_SELECTOR_STYLE_DROPDOWN === $this->form->planSelectorStyle ) {
			$result = 'selected';
		}

		return $result;
	}

	protected function preparePlans() {
		if ( ! empty( $this->stripePlans ) ) {
			$planOptions = [];
			$this->selectedStripePlans = Utils::getSortedFormPlans( $this->stripePlans, $this->form->decoratedPlans );

			/** @var FormFieldConfiguration $priceFieldConfig */
			$priceFieldConfig = $this->fieldConfiguration[ ConfigurableFormFields::FIELD_PRICE ];

			$isPriceConfigurable = $priceFieldConfig->isConfigurable() && ! is_null( $priceFieldConfig->getValue() );
			$selectedPriceId = $isPriceConfigurable ? $priceFieldConfig->getValue() : null;

			$planIndex = 0;
			$taxLabel = $this->getTaxLabel();
			$selectedAttribute = $this->getOptionSelectedAttribute();
			foreach ( $this->selectedStripePlans as $selectedStripePlan ) {
				$planId = $selectedStripePlan->stripePlan->id;
				$planCurrency = $selectedStripePlan->stripePlan->currency;
				$planCancellationCount = $selectedStripePlan->properties->cancellationCount;    // todo: for legacy plans, it's in the metadata
				$planAmount = $selectedStripePlan->stripePlan->unit_amount;
				$planSetupFee = $selectedStripePlan->properties->setupFee;             // todo: for legacy plans, it's in the metadata
				$planName = $selectedStripePlan->stripePlan->product->name;
				$planInterval = $selectedStripePlan->stripePlan->recurring->interval;
				$planIntervalCount = $selectedStripePlan->stripePlan->recurring->interval_count;

				$currencyArray = Currencies::getCurrencyFor( $planCurrency );

				$controlLabel = Localization::translateLabel( $planName );
				$formattedAmount = Currencies::formatAndEscapeByForm( $this->form, $planCurrency, $planAmount, true, true );
				$amountLabel = Localization::getPriceAndIntervalLabel( $planInterval, $planIntervalCount, $formattedAmount ) . ( ! empty( $taxLabel ) ? ( ' + ' . $taxLabel ) : '' );

				$name = null;
				if ( Main::PLAN_SELECTOR_STYLE_RADIO_BUTTONS === $this->form->planSelectorStyle ) {
					$name = self::FIELD_PLAN;
				}
				$plan = ControlUtils::createControl( $this->formHash, $name, null, null, null, $planIndex );
				$plan->setValue( $planId );

				$planAttributes = [
					self::ATTR_DATA_WPFS_VALUE => $planId,
					self::ATTR_DATA_WPFS_PLAN_AMOUNT => Currencies::formatAmount( $planCurrency, $planAmount, true ),
					self::ATTR_DATA_WPFS_PLAN_AMOUNT_IN_SMALLEST_COMMON_CURRENCY => $planAmount,
					self::ATTR_DATA_WPFS_PLAN_SETUP_FEE => Currencies::formatAmount( $planCurrency, $planSetupFee, true ),
					self::ATTR_DATA_WPFS_PLAN_SETUP_FEE_IN_SMALLEST_COMMON_CURRENCY => $planSetupFee,           // todo: for legacy plans, it's in the metadata
					self::ATTR_DATA_WPFS_PRODUCT_NAME => $controlLabel,
					self::ATTR_DATA_WPFS_INTERVAL => $planInterval,
					self::ATTR_DATA_WPFS_INTERVAL_COUNT => $planIntervalCount,
					self::ATTR_DATA_WPFS_CANCELLATION_COUNT => $planCancellationCount,  // todo: for legacy plans, it's in the metadata
					self::ATTR_DATA_WPFS_CURRENCY => $planCurrency,
					self::ATTR_DATA_WPFS_ZERO_DECIMAL_SUPPORT => $currencyArray['zeroDecimalSupport'] ? 'true' : 'false',
					self::ATTR_DATA_WPFS_CURRENCY_SYMBOL => Currencies::getCurrencySymbolFor( $planCurrency ),
					self::ATTR_DATA_WPFS_FEE_RECOVERY_ENABLED => false,
					self::ATTR_DATA_WPFS_FIRST_CHARGE => $this->getFirstCharge( $selectedStripePlan ),
				];
				if ( $isPriceConfigurable && $planId == $selectedPriceId ) {
					$planAttributes[ $selectedAttribute ] = $selectedAttribute;
				}

				if ( Utils::hasFeeRecovery( $this->form ) ) {
					$recoveryFeeData = Utils::getFeeRecoveryData( $this->form );

					$planAttributes[ self::ATTR_DATA_WPFS_FEE_RECOVERY_ENABLED ] = true;
					$planAttributes[ self::ATTR_DATA_WPFS_FEE_AMOUNT ]           = $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT ];
					$planAttributes[ self::ATTR_DATA_WPFS_FEE_PERCENTAGE ]       = $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_PERCENTAGE ];
					// The fee is charged in the plan's currency; wpfs.js shows this opt-in when the plan is selected.
					// Attributes are escaped without encoding entities again, which would turn text the message shows
					// as markup into markup when the browser decodes it; encoded here, it decodes to the label's HTML.
					$this->feeRecoveryLabels[ $planId ]                          = $this->formatFeeRecoveryLabel( $planCurrency );
					$planAttributes[ self::ATTR_DATA_WPFS_FEE_RECOVERY_LABEL ]   = htmlspecialchars( $this->feeRecoveryLabels[ $planId ], ENT_QUOTES, 'UTF-8' );
				}

				$plan->setAttributes( $planAttributes );

				$plan->setLabel( sprintf( '%s - <strong>%s</strong>', $controlLabel, $amountLabel ), Control::ESCAPE_TYPE_NONE );
				array_push( $planOptions, $plan );

				$planIndex++;
			}
			$this->plans->setOptions( $planOptions );
		}

		if ( count( $this->plans->options() ) == 0 ) {
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

	/**
	 * What the first invoice of a plan charges at once, as Stripe::createSubscriptionForCustomer() creates
	 * the subscription. Wallet payment sheets (Apple Pay, Google Pay) show it.
	 *
	 * @param \stdClass $plan A plan returned by Utils::getSortedFormPlans().
	 *
	 * @return string One of the FIRST_CHARGE_* values.
	 */
	protected function getFirstCharge( $plan ) {
		$trialDays = isset( $plan->properties->trialDays ) ? (int) $plan->properties->trialDays : 0;
		$billingAnchorDay = isset( $plan->properties->billingAnchorDay ) ? (int) $plan->properties->billingAnchorDay : 0;

		if ( $trialDays > 0 ) {
			return self::FIRST_CHARGE_SETUP_FEE;
		}
		// With proration, Stripe charges the days until the anchor day, an amount that changes by the second.
		// Without, it charges nothing until the anchor day. Either way the wallet gets no amount.
		if ( $billingAnchorDay > 0 && 'month' === $plan->stripePlan->recurring->interval ) {
			return self::FIRST_CHARGE_UNKNOWN;
		}

		return self::FIRST_CHARGE_PLAN;
	}

	/**
	 * Subscription forms have no currency of their own, so the opt-in shows the fee in the selected plan's.
	 */
	protected function prepareFeeRecoveryLabel(): void {
		if ( ! Utils::hasFeeRecovery( $this->form ) || empty( $this->plans->options() ) ) {
			return;
		}

		$selectedPriceId = $this->getSelectedPriceId();
		if ( isset( $this->feeRecoveryLabels[ $selectedPriceId ] ) ) {
			$this->feeRecoveryAccepted->setLabel( $this->feeRecoveryLabels[ $selectedPriceId ], Control::ESCAPE_TYPE_NONE );
		}
	}

	protected function prepareFirstPlan() {
		$firstStripePlan = null;
		if ( ! empty( $this->stripePlans ) ) {
			if ( sizeof( $this->selectedStripePlans ) > 0 ) {
				$firstStripePlan = $this->selectedStripePlans[0];
			}
		}
		if ( ! is_null( $firstStripePlan ) ) {
			$planId = $firstStripePlan->stripePlan->id;
			$planCurrency = $firstStripePlan->stripePlan->currency;
			$planCancellationCount = $firstStripePlan->properties->cancellationCount;
			$planAmount = $firstStripePlan->stripePlan->unit_amount;
			$planSetupFee = $firstStripePlan->properties->setupFee;
			$planName = $firstStripePlan->stripePlan->product->name;
			$planInterval = $firstStripePlan->stripePlan->recurring->interval;
			$planIntervalCount = $firstStripePlan->stripePlan->recurring->interval_count;

			$currencyArray = Currencies::getCurrencyFor( $planCurrency );

			$this->firstPlan = ControlUtils::createControl( $this->formHash, self::FIELD_PLAN, null, null, null, null );
			$this->firstPlan->setValue( $planId );
			$attributes = [
				'type' => 'hidden',
				self::ATTR_DATA_WPFS_VALUE => $planId,
				self::ATTR_DATA_WPFS_PLAN_AMOUNT => Currencies::formatAmount( $planCurrency, $planAmount, true ),
				self::ATTR_DATA_WPFS_PLAN_AMOUNT_IN_SMALLEST_COMMON_CURRENCY => $planAmount,
				self::ATTR_DATA_WPFS_PLAN_SETUP_FEE => Currencies::formatAmount( $planCurrency, $planSetupFee, true ),
				self::ATTR_DATA_WPFS_PLAN_SETUP_FEE_IN_SMALLEST_COMMON_CURRENCY => $planSetupFee,
				self::ATTR_DATA_WPFS_PRODUCT_NAME => Localization::translateLabel( $planName ),
				self::ATTR_DATA_WPFS_INTERVAL => $planInterval,
				self::ATTR_DATA_WPFS_INTERVAL_COUNT => $planIntervalCount,
				self::ATTR_DATA_WPFS_CANCELLATION_COUNT => $planCancellationCount,
				self::ATTR_DATA_WPFS_CURRENCY => $planCurrency,
				self::ATTR_DATA_WPFS_ZERO_DECIMAL_SUPPORT => $currencyArray['zeroDecimalSupport'] ? 'true' : 'false',
				self::ATTR_DATA_WPFS_CURRENCY_SYMBOL => Currencies::getCurrencySymbolFor( $planCurrency ),
				self::ATTR_DATA_WPFS_FEE_RECOVERY_ENABLED   => false,
				self::ATTR_DATA_WPFS_FIRST_CHARGE           => $this->getFirstCharge( $firstStripePlan ),
			];

			if ( Utils::hasFeeRecovery( $this->form ) ) {
				$recoveryFeeData = Utils::getFeeRecoveryData( $this->form );

				$attributes[ self::ATTR_DATA_WPFS_FEE_RECOVERY_ENABLED ] = true;
				$attributes[ self::ATTR_DATA_WPFS_FEE_AMOUNT ]           = $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT ];
				$attributes[ self::ATTR_DATA_WPFS_FEE_PERCENTAGE ]       = $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_PERCENTAGE ];
			}
			$this->firstPlan->setAttributes( $attributes );
		}
	}

	protected function preparePlanQuantity() {
		$attributes = [
			self::ATTR_DATA_WPFS_STEPPER => self::FIELD_PLAN_QUANTITY,
			self::ATTR_DATA_DEFAULT_VALUE => 1,
			self::ATTR_DATA_MINIMUM_VALUE => 1
		];
		if ( isset( $this->form->minimumQuantityOfSubscriptions ) && $this->form->minimumQuantityOfSubscriptions > 0 ) {
			$attributes[ self::ATTR_DATA_MINIMUM_VALUE ] = $this->form->minimumQuantityOfSubscriptions;
			$attributes[ self::ATTR_DATA_DEFAULT_VALUE ] = $this->form->minimumQuantityOfSubscriptions;
		}
		if ( isset( $this->form->maximumQuantityOfSubscriptions ) && $this->form->maximumQuantityOfSubscriptions > 0 ) {
			$attributes[ self::ATTR_DATA_MAXIMUM_VALUE ] = $this->form->maximumQuantityOfSubscriptions;
		}
		$this->planQuantity->setAttributes( $attributes );
	}

	/**
	 * @return array
	 */
	public static function getFields() {

		$fields = [
			self::FIELD_PLAN => ControlUtils::selectMenu( self::FIELD_PLAN ),
			self::FIELD_PLAN_QUANTITY => ControlUtils::stepper( self::FIELD_PLAN_QUANTITY )
		];

		return array_merge( $fields, parent::getFields() );
	}

	/**
	 * @return array
	 */
	public function getSelectedStripePlanIds() {
		$planList = [];

		foreach ( $this->selectedStripePlans as $plan ) {
			$planList[ $plan->stripePlan->id ] = $plan->stripePlan->id;
		}

		return $planList;
	}

	/**
	 * @return array
	 */
	protected function getFormAttributes() {
		$attributes = [];

		$attributes[ self::ATTR_DATA_WPFS_TAX_RATE_TYPE ] = $this->form->vatRateType;
		$attributes[ self::ATTR_DATA_WPFS_SHOW_COUPON_FIELD ] = $this->isCouponFieldVisible() ? 'true' : 'false';
		$attributes[ self::ATTR_DATA_WPFS_SELECTOR_STYLE ] = $this->form->planSelectorStyle;

		return array_merge( $attributes, parent::getFormAttributes() );
	}

	public function firstPlan() {
		return $this->firstPlan;
	}

	/**
	 * @return Control
	 */
	public function plans() {
		return $this->plans;
	}

	/**
	 * @return Control
	 */
	public function planQuantity() {
		return $this->planQuantity;
	}

	/**
	 * @return array
	 */
	public function getProductPricing(): array {
		return $this->productPricing;
	}
}
