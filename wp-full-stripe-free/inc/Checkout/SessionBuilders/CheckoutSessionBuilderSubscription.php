<?php

namespace ThemeIsle\WPFullPay\Checkout\SessionBuilders;

use ThemeIsle\WPFullPay\Forms\Models\SubscriptionFormModel;
use ThemeIsle\WPFullPay\Locale\Localization;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\Utils;

class CheckoutSessionBuilderSubscription extends CheckoutSessionBuilderProduct {
	public function __construct( $submitHash, $formModel, $fieldConfiguration, $stripe, $loggerService ) {
		parent::__construct( $submitHash, $formModel, $fieldConfiguration, $stripe, $loggerService );
	}

	public function build() {
		$sessionData = parent::build();
		if ( isset( $sessionData['customer_creation'] ) && ! empty( $sessionData['customer_creation'] ) ) {
			unset( $sessionData['customer_creation'] );
			// customer creation is implicit for subscriptions and wil cause an error if included
		}
		$recoveryFee = $this->formModel->getFeeRecoveryAccepted();
		$recoveryFeeData = Utils::getFeeRecoveryData( $this->formModel->getForm() );

		$subscriptionData = [];
		$lineItems = [];

		$setupFee = $this->formModel->getSetupFee();
		$trialDays = $this->formModel->getTrialPeriodDays();

		if ( $trialDays > 0 ) {
			$subscriptionData['trial_period_days'] = $trialDays;
		}
		// Completion and later receipts report this fee, not one changed on the form since.
		$subscriptionData['metadata'] = [
			SubscriptionFormModel::METADATA_KEY_SETUP_FEE => (string) (int) $setupFee,
		];

		if ( $setupFee > 0 ) {
			$setupFeeLineItem = [
				'price_data' => [
					'currency' => $this->formModel->getStripePlan()->currency,
					'product_data' => [
						'name' => sprintf(
							/* translators: It's a line item for the initial payment of a subscription  */
							__( 'One-time setup fee (plan: %s)', 'wp-full-stripe-free' ),
							Localization::translateLabel( $this->formModel->getStripePlan()->product->name )
						),
						'metadata' => [ 
							'type' => 'setupFee'
						],
						'description' => sprintf(
							// It's an internal description, no need to localize it
							'Subscription plan: %s, quantity: %d',
							$this->formModel->getStripePlan()->id,
							$this->formModel->getStripePlanQuantity()
						),
					],
					'unit_amount' => $setupFee
				],
				'quantity' => $this->formModel->getStripePlanQuantity()
			];

			$this->updateLineItemWithTax( $setupFeeLineItem );
			array_push( $lineItems, $setupFeeLineItem );
		}

		$planLineItem = [
			'price' => $this->formModel->getStripePlan()->id,
			'quantity' => $this->formModel->getStripePlanQuantity()
		];
		$this->updateLineItemWithTax( $planLineItem );
		array_push( $lineItems, $planLineItem );

		$sessionData = array_merge( $sessionData, [ 
			'mode' => 'subscription',
			'line_items' => $lineItems,
			'subscription_data' => $subscriptionData,
		] );

		if ( $recoveryFee && ! empty( $recoveryFeeData ) ) {
			$amount = $this->formModel->getStripePlan()->unit_amount * $this->formModel->getStripePlanQuantity();
			// Subscription forms have no currency of their own; every line item must be in the plan's.
			$currency = $this->formModel->getStripePlan()->currency;

			$recoveryFeeLineItem = [
				'price_data' => [
					'currency' => $currency,
					'product_data' => [
						'name' => __( 'Transaction Fee', 'wp-full-stripe-free' ),
						// Plan changes recognise the fee by this, as its name is translated.
						'metadata' => [
							Stripe::METADATA_KEY_RECOVERY_FEE_ITEM => '1',
						],
					],
					'unit_amount' => Utils::calculateRecoveryFee( $amount, $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_PERCENTAGE ], $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT ], $currency ),
				],
				'quantity' => 1
			];

			if ( isset( $this->formModel->getStripePlan()->recurring ) ) {
				// Checkout refuses prices billed at different intervals, so the fee bills as often as the plan.
				$recurring = $this->formModel->getStripePlan()->recurring;
				$recoveryFeeLineItem['price_data']['recurring'] = [
					'interval' => $recurring->interval,
					'interval_count' => isset( $recurring->interval_count ) ? (int) $recurring->interval_count : 1,
				];
			}

			array_push( $sessionData['line_items'], $recoveryFeeLineItem );
		}

		return $sessionData;
	}
}
