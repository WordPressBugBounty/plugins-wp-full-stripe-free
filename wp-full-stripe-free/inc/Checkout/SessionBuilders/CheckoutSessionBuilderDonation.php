<?php

namespace ThemeIsle\WPFullPay\Checkout\SessionBuilders;

use ThemeIsle\WPFullPay\Forms\Models\DonationFormModel;
use ThemeIsle\WPFullPay\Locale\Localization;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\Utils;

class CheckoutSessionBuilderDonation extends CheckoutSessionBuilder {
	use CheckoutSessionBuilderPhoneNumberAddOn;

	public function __construct( $submitHash, $formModel, $fieldConfiguration, $stripe, $loggerService ) {
		parent::__construct( $submitHash, $formModel, $fieldConfiguration, $stripe, $loggerService );
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	protected function getProductLabel(): string {
		return $this->formModel->getProductName() . ' (' . Localization::getDonationFrequencyLabel( $this->formModel->getDonationFrequency() ) . ')';
	}

	public function build() {
		$sessionData = parent::build();

		$amount = $this->formModel->getAmount();
		$currency = $this->formModel->getForm()->currency;
		$recoveryFee = $this->formModel->getFeeRecoveryAccepted();
		$recoveryFeeData = Utils::getFeeRecoveryData( $this->formModel->getForm() );

		$this->buildPhoneNumber( $sessionData );

		$sessionData['mode'] = 'payment';
		if (  $this->formModel->isRecurringDonation() ) {
			$sessionData['mode'] = 'subscription';
		} else {
			$sessionData['customer_creation'] = 'always'; // always capture a customer. we need it post payment for internal logic
		}

		$productData = [
			'name' => $this->getProductLabel()
		];
		if ( isset( $this->formModel->getForm()->companyName ) && ! empty( $this->formModel->getForm()->companyName ) ) {
			$productData['description'] = $this->formModel->getForm()->companyName;
		}

		if ( ! empty( $this->formModel->getForm()->image ) ) {
			$productData['images'] = [ 
				$this->formModel->getForm()->image
			];
		}

		$lineItem = [
			'price_data' => [
				'currency' => $currency,
				'product_data' => $productData,
				'unit_amount' => $this->formModel->getAmount(),
			],
			'quantity' => 1,
		];

		$intervals = [
			'daily' => 'day',
			'weekly' => 'week',
			'monthly' => 'month',
			'annual' => 'year'
		];

		if ( $this->formModel->isRecurringDonation() ) {
			$lineItem['price_data']['recurring'] = [
				'interval' => $intervals[ $this->formModel->getDonationFrequency() ],
			];
		}

		$sessionData['line_items'] = [ $lineItem ];
		// Completion records this amount, not the preset as edited since.
		$sessionData['metadata'] = [
			DonationFormModel::METADATA_KEY_DONATION_AMOUNT => (string) (int) $amount,
		];

		if ( $recoveryFee && ! empty( $recoveryFeeData ) ) {
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

			if ( $this->formModel->isRecurringDonation() ) {
				$recoveryFeeLineItem['price_data']['recurring'] = [
					'interval' => $intervals[ $this->formModel->getDonationFrequency() ],
				];
			}

			array_push( $sessionData['line_items'], $recoveryFeeLineItem );
		}

		return $sessionData;
	}
}
