<?php

namespace ThemeIsle\WPFullPay\Checkout\SessionBuilders;

use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\Utils;

class CheckoutSessionBuilderOneTimePayment extends CheckoutSessionBuilderProduct {
	public function __construct( $submitHash, $formModel, $fieldConfiguration, $stripe, $loggerService ) {
		parent::__construct( $submitHash, $formModel, $fieldConfiguration, $stripe, $loggerService );
	}

	public function build() {
		$sessionData = parent::build();

		$sessionData['mode'] = 'payment';
		$sessionData['customer_creation'] = 'always'; // always capture a customer. we need it post payment for internal logic

		$amount = $this->formModel->getAmount();
		$currency = $this->formModel->getForm()->currency;
		$recoveryFee = $this->formModel->getFeeRecoveryAccepted();
		$recoveryFeeData = Utils::getFeeRecoveryData( $this->formModel->getForm() );

		$lineItem = [];
		if ( ! is_null( $this->formModel->getPriceId() ) ) {
			$lineItem = [
				'price' => $this->formModel->getPriceId(),
				'quantity' => 1
			];
		} else {
			$lineItem = [
				'price_data' => [
					'currency' => $currency,
					'product_data' => [
						'name' => $this->formModel->getProductName()
					],
					'unit_amount' => $this->formModel->getAmount(),
				],
				'quantity' => 1
			];

			if ( isset( $this->formModel->getForm()->companyName ) && ! empty( $this->formModel->getForm()->companyName ) ) {
				$lineItem['price_data']['product_data']['description'] = $this->formModel->getForm()->companyName;
			}

			$imagesValueArray = $this->prepareImagesValueArray( $this->formModel->getForm() );
			if ( count( $imagesValueArray ) > 0 ) {
				$lineItem['images'] = $imagesValueArray;
			}
		}

		$this->updateLineItemWithTax( $lineItem );
		$sessionData['line_items'] = [ $lineItem ];

		// A payment its coupon makes free has no processing fee to recover, as on inline forms.
		if ( $recoveryFee && ! empty( $recoveryFeeData ) && ! $this->isPriceMadeFreeByCoupon( $amount, $currency, $this->formModel->getPriceId() ) ) {
			$recoveryFeeLineItem = [
				'price_data' => [
					'currency' => $currency,
					'product_data' => [
						'name' => __( 'Transaction Fee', 'wp-full-stripe-free' ),
						// Completion recognises the fee by this, as its name is translated.
						'metadata' => [
							Stripe::METADATA_KEY_RECOVERY_FEE_ITEM => '1',
						],
					],
					'unit_amount' => Utils::calculateRecoveryFee( $amount, $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_PERCENTAGE ], $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT ], $currency ),
				],
				'quantity' => 1
			];

			array_push( $sessionData['line_items'], $recoveryFeeLineItem );
		}

		return $sessionData;
	}

	private function prepareImagesValueArray( $checkoutForm ) {
		$imagesValue = [];
		if ( ! empty( $checkoutForm->image ) ) {
			array_push( $imagesValue, $checkoutForm->image );
		}

		return $imagesValue;
	}
}
