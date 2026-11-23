<?php

namespace ThemeIsle\WPFullPay\Pricing;

use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Utils;

abstract class SubscriptionPriceCalculator extends PriceCalculator {
	protected function getProductBuckets( $form ) {
		$result = [];
		$decoratedPlans = Utils::decodeJsonArray( $form->decoratedPlans );

		// The coupon goes on the invoice, to cover a plan with its fees.
		if ( $this->previewsEachProductAlone( count( $decoratedPlans ), $form, true ) ) {
			return $this->splitIntoSingleProducts( $decoratedPlans );
		}

		$hashedBuckets = [];
		foreach ( $decoratedPlans as $decoratedPlan ) {
			$key = $decoratedPlan->currency . $decoratedPlan->interval . $decoratedPlan->intervalCount;

			if ( array_key_exists( $key, $hashedBuckets ) ) {
				$hashedBucket = $hashedBuckets[ $key ];
				array_push( $hashedBucket, $decoratedPlan );
				$hashedBuckets[ $key ] = $hashedBucket;
			} else {
				$hashedBuckets[ $key ] = [ $decoratedPlan ];
			}
		}

		foreach ( $hashedBuckets as $hashedBucket ) {
			$result = array_merge( $result, $this->splitByPreviewLineLimit( $hashedBucket, $form ) );
		}

		return $result;
	}

	protected function previewsRecoveryFeeLine() {
		return true;
	}

	/**
	 * @return array<string, list<\stdClass>> The payment details of each plan, by price id.
	 */
	public function getProductPrices() {
		$productPricing = parent::getProductPrices();

		// The preview has no trial, so Stripe spreads an amount-off coupon over the plan, its setup fee
		// and its recovery fee. A trial's first invoice holds only the setup fee, which the whole
		// coupon comes off. A percentage coupon discounts each line alike, so the preview holds.
		$coupon = isset( $this->pricingData->coupon ) ? $this->pricingData->coupon : null;
		if ( is_null( $coupon ) || empty( $coupon->amount_off ) ) {
			return $productPricing;
		}

		$form = $this->getFormFromDatabase();
		$taxRateIds = Pricing::extractTaxRateIdsStatic( $this->getApplicableTaxRates( $form ) );
		foreach ( Utils::decodeJsonArray( $form->decoratedPlans ) as $plan ) {
			if ( empty( $plan->trialDays ) || empty( $plan->setupFee ) || ! array_key_exists( $plan->stripePriceId, $productPricing ) ) {
				continue;
			}

			$firstCharge = $this->previewTrialFirstCharge( $plan, $taxRateIds );
			if ( ! is_null( $firstCharge ) ) {
				array_push( $productPricing[ $plan->stripePriceId ], $firstCharge );
			}
		}

		return $productPricing;
	}

	/**
	 * Previews the first invoice of a trial plan: its setup fee, with the coupon and tax.
	 *
	 * @param \stdClass    $plan       A plan of the form, with a trial and a setup fee.
	 * @param list<string> $taxRateIds
	 * @return object{type: 'trial-first-charge', subType: 'trial-first-charge', id: string, currency: string, amount: int}|null
	 */
	private function previewTrialFirstCharge( $plan, $taxRateIds ) {
		// Only the address and Stripe Tax settings, without the subscription.
		$params = parent::prepareStripeInvoiceParams( $this->pricingData, [ $plan ], $taxRateIds );
		$params['discounts'] = [
			[
				'coupon' => $this->pricingData->couponCode,
			],
		];
		$setupFeeItem = [
			'amount' => (int) $plan->setupFee * (int) $this->pricingData->quantity,
			'currency' => $plan->currency,
			'description' => __( 'Setup fee', 'wp-full-stripe-free' ),
		];
		if ( ! $this->pricingData->stripeTax ) {
			$setupFeeItem['tax_rates'] = $taxRateIds;
		}
		$params['invoice_items'] = [ $setupFeeItem ];

		$lines = $this->stripe->getUpcomingInvoice( $params )->lines->data;
		if ( empty( $lines ) ) {
			return null;
		}

		$amount = $lines[0]->amount;
		foreach ( $lines[0]->discount_amounts as $discountAmount ) {
			$amount -= $discountAmount->amount;
		}
		foreach ( $lines[0]->taxes as $taxItem ) {
			if ( ! Pricing::isTaxAmountInclusive( $taxItem ) ) {
				$amount += $taxItem->amount;
			}
		}

		$displayItem = new \StdClass;
		$displayItem->type = Main::LINE_ITEM_TYPE_TRIAL_FIRST_CHARGE;
		$displayItem->subType = Main::LINE_ITEM_TYPE_TRIAL_FIRST_CHARGE;
		$displayItem->id = $plan->stripePriceId;
		$displayItem->currency = $lines[0]->currency;
		$displayItem->amount = $amount;

		return $displayItem;
	}

	protected function getProductPriceId( $product ) {
		return $product->stripePriceId;
	}

	protected function countPreviewLines( $product, $form ) {
		// A setup fee is a line of its own.
		return parent::countPreviewLines( $product, $form ) + ( $product->setupFee > 0 ? 1 : 0 );
	}

	protected function prepareStripeInvoiceParams( $pricingData, $products, $taxRateIds ) {
		$result = parent::prepareStripeInvoiceParams( $pricingData, $products, $taxRateIds );
		$form = $this->getFormFromDatabase();

		if ( ! empty( $pricingData->couponCode ) ) {
			$result['discounts'] = [ 
				[ 
					'coupon' => $pricingData->couponCode
				]
			];
		}

		$invoiceItems = [];
		$subscriptionItems = [];
		foreach ( $products as $product ) {
			$subscriptionItem = [ 
				'price' => $product->stripePriceId,
				'quantity' => $pricingData->quantity,
			];
			if ( ! $pricingData->stripeTax ) {
				$subscriptionItem['tax_rates'] = $taxRateIds;
			}
			array_push( $subscriptionItems, $subscriptionItem );

			if ( $product->setupFee > 0 ) {
				$invoiceItem = [ 
					'amount' => $product->setupFee * $pricingData->quantity,
					'currency' => $product->currency,
					'description' => __( 'Setup fee', 'wp-full-stripe-free' ),
					'metadata' => [ 
						'type' => 'setupFee',
						'priceId' => $product->stripePriceId
					],
				];
				if ( ! $pricingData->stripeTax ) {
					$invoiceItem['tax_rates'] = $taxRateIds;
				}
				array_push( $invoiceItems, $invoiceItem );
			}

			// The subscription bills the fee on the plan's price for the quantity, before tax and discount.
			$recoveryFeeItem = $this->prepareRecoveryFeeInvoiceItem( $form, (int) $product->price * (int) $pricingData->quantity, $product->currency, $product->stripePriceId );
			if ( ! is_null( $recoveryFeeItem ) ) {
				array_push( $invoiceItems, $recoveryFeeItem );
			}
		}

		$result['invoice_items'] = $invoiceItems;
		$result['subscription_details'] = [
			'items' => $subscriptionItems,
		];

		return $result;
	}

	/**
	 * @param $lineItems
	 * @return mixed
	 */
	protected function extractCurrencyFromLineItems( $lineItems ) {
		return $lineItems[0]->currency;
	}

	/**
	 * @param $label
	 * @param $quantity
	 * @return string
	 */
	protected function createLineItemDisplayName( $label, $quantity ) {
		return $quantity === 1 ? $label : "{$quantity}x {$label}";
	}

	/**
	 * @param $lineItems
	 * @param $taxRates
	 * @param $products
	 * @return array
	 */
	protected function extractPaymentDetailsFromInvoiceLineItems( $lineItems, $taxRates, $products ) {
		$productPricing = [];

		$productLookup = [];
		foreach ( $products as $product ) {
			$productLookup[ $product->stripePriceId ] = $product;
		}

		$priceGroupLookup = [];
		$recoveryFeeItems = [];
		foreach ( $lineItems as $lineItem ) {
			if ( $this->isRecoveryFeeLineItem( $lineItem ) ) {
				$recoveryFeeItems[ $lineItem->metadata->priceId ] = $this->createRecoveryFeeDisplayItem( $lineItem );
				continue;
			}

			$priceId = $lineItem->pricing->price_details->price;
			$isSetupFee = false;

			$metaData = $lineItem->metadata;
			if ( $metaData !== null && ! empty( (array) $metaData ) && $metaData->type === Main::LINE_ITEM_TYPE_SETUP_FEE ) {
				$isSetupFee = true;
				$priceId = $metaData->priceId;
			}

			if ( ! array_key_exists( $priceId, $priceGroupLookup ) ) {
				$priceGroupLookup[ $priceId ] = [];
			}

			$priceItems = $priceGroupLookup[ $priceId ];
			if ( count( $priceItems ) == 0 || count( $priceItems ) > 1 ) {
				array_push( $priceItems, $lineItem );
			} else if ( count( $priceItems ) === 1 ) {
				if ( $isSetupFee ) {
					array_unshift( $priceItems, $lineItem );
				} else {
					array_push( $priceItems, $lineItem );
				}
			}
			$priceGroupLookup[ $priceId ] = $priceItems;
		}

		foreach ( $priceGroupLookup as $priceId => $lineItems ) {
			$dislayItems = [];

			foreach ( $lineItems as $lineItem ) {
				$priceId = $lineItem->pricing->price_details->price;
				$type = Main::LINE_ITEM_TYPE_PRODUCT;
				$discountTotal = 0;
				$taxAmountLookup = [];

				$metaData = $lineItem->metadata;
				if ( $metaData !== null && ! empty( (array) $metaData ) && $metaData->type === Main::LINE_ITEM_TYPE_SETUP_FEE ) {
					$priceId = $metaData->priceId;
					$type = Main::LINE_ITEM_TYPE_SETUP_FEE;
					$displayName = $this->createLineItemDisplayName( __( 'Setup fee', 'wp-full-stripe-free' ), $lineItem->quantity );
				} else {
					$displayName = $this->createLineItemDisplayName( $productLookup[ $priceId ]->name, $lineItem->quantity );
				}

				$displayItem = new \StdClass;
				$displayItem->type = $type;
				$displayItem->subType = $type;
				$displayItem->id = $priceId;
				$displayItem->displayName = $displayName;
				$displayItem->currency = $lineItem->currency;
				$displayItem->amount = $lineItem->amount;
				array_push( $dislayItems, $displayItem );

				foreach ( $lineItem->discount_amounts as $discountAmount ) {
					$discountTotal += $discountAmount->amount;
				}

				if ( $discountTotal > 0 ) {
					$displayItem = new \StdClass;
					$displayItem->type = $type;
					$displayItem->subType = Main::LINE_ITEM_SUBTYPE_DISCOUNT;
					$displayItem->id = $priceId;
					$displayItem->displayName = __( 'Discount', 'wp-full-stripe-free' );
					$displayItem->currency = $this->extractCurrencyFromLineItems( $lineItems );
					$displayItem->amount = -$discountTotal;
					array_push( $dislayItems, $displayItem );
				}

				$taxItemLookup = [];
				foreach ( $lineItem->taxes as $taxItem ) {
					$taxRateId = $this->extractTaxRateId( $taxItem );
					if ( $taxRateId !== null ) {
						$taxItemLookup[ $taxRateId ] = $taxItem;
					}
				}

				if ( $this->pricingData->stripeTax ) {
					foreach ( $taxItemLookup as $taxRateId => $taxItem ) {
						if ( $taxItem->amount > 0 ) {
							$inclusive = null;
							if ( property_exists( $taxItem, 'tax_behavior' ) && $taxItem->tax_behavior !== null ) {
								$inclusive = ( $taxItem->tax_behavior === 'inclusive' );
							} elseif ( property_exists( $taxItem, 'inclusive' ) ) {
								$inclusive = (bool) $taxItem->inclusive;
							}

							$displayItem = new \StdClass;
							$displayItem->type = $type;
							$displayItem->subType = Main::LINE_ITEM_SUBTYPE_TAX;
							$displayItem->id = $taxRateId;
							$displayItem->displayName = $this->determineTaxLabel( $taxItem );
							$displayItem->currency = $this->extractCurrencyFromLineItems( $lineItems );
							$displayItem->amount = $taxItem->amount;
							$displayItem->percentage = null;
							$displayItem->inclusive = $inclusive;
							array_push( $dislayItems, $displayItem );
						}
					}
				} else {
					foreach ( $taxRates as $taxRate ) {
						$taxAmount = array_key_exists( $taxRate->taxRateId, $taxItemLookup ) ? $taxItemLookup[ $taxRate->taxRateId ]->amount : 0;

						if ( $taxAmount > 0 ) {
							$displayItem = new \StdClass;
							$displayItem->type = $type;
							$displayItem->subType = Main::LINE_ITEM_SUBTYPE_TAX;
							$displayItem->id = $taxRate->taxRateId;
							$displayItem->displayName = $taxRate->displayName;
							$displayItem->currency = $this->extractCurrencyFromLineItems( $lineItems );
							$displayItem->amount = $taxAmount;
							$displayItem->percentage = $taxRate->percentage;
							$displayItem->inclusive = $taxRate->inclusive;
							array_push( $dislayItems, $displayItem );
						}
					}
				}
			}

			if ( array_key_exists( $priceId, $recoveryFeeItems ) ) {
				array_push( $dislayItems, $recoveryFeeItems[ $priceId ] );
			}
			$productPricing[ $priceId ] = $dislayItems;
		}

		return $productPricing;
	}
}
