<?php

namespace ThemeIsle\WPFullPay\Checkout\SessionBuilders;

use ThemeIsle\WPFullPay\Forms\Fields\ConfigurableFormFields;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Pricing\Pricing;

abstract class CheckoutSessionBuilderProduct extends CheckoutSessionBuilder {
	use CheckoutSessionBuilderPhoneNumberAddOn;

	/** @var \StripeWPFS\Stripe\PromotionCode|null The promotion code the form passes to Checkout, when the form has one. */
	protected $promotionCode = null;

	public function __construct( $submitHash, $formModel, $fieldConfiguration, $stripe, $loggerService ) {
		parent::__construct( $submitHash, $formModel, $fieldConfiguration, $stripe, $loggerService );
	}

	public function build() {
		$sessionData = parent::build();

		$this->buildPhoneNumber( $sessionData );

		if ( $this->formModel->getForm()->showCouponInput == '1' ) {
			$couponField = $this->fieldConfiguration[ ConfigurableFormFields::FIELD_COUPON ];
			$isCouponConfigurable = $couponField->isConfigurable() && ! is_null( $couponField->getValue() );
			$coupon = null;

			if ( $isCouponConfigurable ) {
				$coupon = $this->stripe->retrieveActivePromotionCode( $couponField->getValue() );
			}

			if ( ! $isCouponConfigurable || is_null( $coupon ) ) {
				$sessionData['allow_promotion_codes'] = 'true';
			} else {
				$this->promotionCode = $coupon;
				$sessionData['discounts'] = [
					[
						'promotion_code' => $coupon->id
					]
				];
			}
		}

		$taxRateType = $this->formModel->getForm()->vatRateType;
		if ( $taxRateType === Main::FIELD_VALUE_TAX_RATE_STRIPE_TAX ) {
			$sessionData['automatic_tax'] = [ 
				'enabled' => true,
			];
		}
		if (
			$taxRateType !== Main::FIELD_VALUE_TAX_RATE_NO_TAX &&
			1 == $this->formModel->getForm()->collectCustomerTaxId
		) {
			$sessionData['tax_id_collection'] = [ 
				'enabled' => true,
			];
		}

		return $sessionData;
	}

	/**
	 * Whether the promotion code the form passes to Checkout makes the price free. A coupon limited
	 * to some products discounts only their prices.
	 *
	 * @param int         $amount   The price, in the smallest currency unit.
	 * @param string      $currency
	 * @param string|null $priceId  The form's Stripe price, or null for an amount priced in the session.
	 * @return bool
	 */
	protected function isPriceMadeFreeByCoupon( $amount, $currency, $priceId ) {
		if ( ! isset( $this->promotionCode->coupon ) ) {
			return false;
		}

		$coupon = $this->promotionCode->coupon;
		if ( ! empty( $coupon->applies_to->products ) ) {
			$productIds = is_null( $priceId ) ? [] : $this->stripe->retrieveProductIdsByPriceIds( [ $priceId ] );
			if ( empty( array_intersect( $coupon->applies_to->products, $productIds ) ) ) {
				return false;
			}
		}

		return Pricing::doesCouponCoverAmount( $coupon, $amount, $currency );
	}

	protected function updateLineItemWithTax( &$lineItem ) {
		$taxRateType = $this->formModel->getForm()->vatRateType;
		switch ( $taxRateType ) {
			case Main::FIELD_VALUE_TAX_RATE_FIXED:
			case Main::FIELD_VALUE_TAX_RATE_DYNAMIC:
				$taxRates = Pricing::extractTaxRateIdsStatic( json_decode( $this->formModel->getForm()->vatRates ) );
				$index = $taxRateType === Main::FIELD_VALUE_TAX_RATE_FIXED ? 'tax_rates' : 'dynamic_tax_rates';

				$lineItem[ $index ] = $taxRates;
				break;
		}
	}
}
