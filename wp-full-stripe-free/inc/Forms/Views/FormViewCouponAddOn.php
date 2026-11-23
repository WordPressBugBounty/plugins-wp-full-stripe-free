<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

use ThemeIsle\WPFullPay\Forms\Fields\ConfigurableFormFields;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Utils;

trait FormViewCouponAddOn {
	protected $couponData;

	protected function prepareCouponData() {
		$result = null;
		$couponFieldConfig = $this->fieldConfiguration[ ConfigurableFormFields::FIELD_COUPON ];

		$this->couponData = null;
		if ( $this->isPrefilledCouponPriced() && $couponFieldConfig->isConfigurable() && ! is_null( $couponFieldConfig->getValue() ) ) {
			$couponCode = $couponFieldConfig->getValue();

			$coupon = $this->retrievePrefilledCoupon( $couponCode );
			if ( ! is_null( $coupon ) ) {
				$formType = Utils::getFormType( $this->form );
				$formId = $this->form->name;

				$isApplicable = Main::getInstance()->isCouponApplicableToForm(
					$coupon,
					$formType,
					$formId,
					$this->getSelectedPriceId()
				);

				// Checkout discounts the products the code is limited to, whichever product the page selects first.
				$isPriced = $this->isCheckoutForm() ? $isApplicable->applicableToForm : $isApplicable->applicableToProduct;
				if ( $isPriced ) {
					$result = $coupon;

					$discountedPriceIds = Main::getInstance()->getDiscountedPriceIdsByCouponAndForm( $coupon, $formType, $formId );
					$this->couponData = [
						'id' => $coupon->id,
						'name' => $couponCode,
						'currency' => $coupon->currency,
						'percent_off' => $coupon->percent_off,
						'amount_off' => $coupon->amount_off,
						'discounted_price_ids' => $discountedPriceIds
					];

					$this->logger->debug( __FUNCTION__, "Coupon code {$couponCode} is applicable to form" );
				}
			} else {
				$this->logger->debug( __FUNCTION__, "Coupon code {$couponCode} is NOT applicable to form" );
			}
		}

		return $result;
	}

	/**
	 * Whether the page is priced with the coupon the field configuration prefills. Checkout forms hide the
	 * coupon field and pass the code to Checkout, see CheckoutSessionBuilderProduct::build().
	 *
	 * @return bool
	 */
	protected function isPrefilledCouponPriced() {
		if ( $this->isCheckoutForm() ) {
			return isset( $this->form->showCouponInput ) && '1' == $this->form->showCouponInput;
		}

		return $this->isCouponFieldVisible();
	}

	/**
	 * The coupon a prefilled code names. Checkout applies only an active promotion code, so a Checkout
	 * form is priced with nothing else.
	 *
	 * @param string $couponCode
	 * @return \StripeWPFS\Stripe\Coupon|null
	 */
	private function retrievePrefilledCoupon( $couponCode ) {
		if ( ! $this->isCheckoutForm() ) {
			return $this->stripe->retrieveCouponByPromotionalCodeOrCouponCode( $couponCode );
		}

		$promotionCode = $this->stripe->retrieveActivePromotionCode( $couponCode );

		return is_null( $promotionCode ) ? null : $promotionCode->coupon;
	}

	/**
	 * @return array
	 */
	public function getCouponData() {
		return $this->couponData;
	}
}
