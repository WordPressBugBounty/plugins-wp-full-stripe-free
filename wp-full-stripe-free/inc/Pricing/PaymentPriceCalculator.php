<?php

namespace ThemeIsle\WPFullPay\Pricing;

use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Utils;

abstract class PaymentPriceCalculator extends PriceCalculator {

	protected function getProductBuckets( $form ) {
		$decoratedProducts = [];
		if ( ! is_null( $this->pricingData->customAmount ) ) {
			$customAmountProduct = new \StdClass;
			$customAmountProduct->isCustomAmount = true;

			$decoratedProducts[] = $customAmountProduct;
		}
		if ( $form->customAmount !== Main::PAYMENT_TYPE_CUSTOM_AMOUNT ) {
			$decoratedProducts = array_merge( $decoratedProducts, Utils::decodeJsonArray( $form->decoratedProducts ) );
		}

		if ( $this->previewsEachProductAlone( count( $decoratedProducts ), $form, $this->addsRecoveryFeeLine( $form ) ) ) {
			return $this->splitIntoSingleProducts( $decoratedProducts );
		}

		return $this->splitByPreviewLineLimit( $decoratedProducts, $form );
	}

	protected function getProductPriceId( $product ) {
		return isset( $product->isCustomAmount ) ? Main::PRICE_ID_CUSTOM_AMOUNT : $product->stripePriceId;
	}

	protected function prepareStripeInvoiceParams( $pricingData, $products, $taxRateIds ) {
		$result = parent::prepareStripeInvoiceParams( $pricingData, $products, $taxRateIds );
		$form = $this->getFormFromDatabase();

		$discounts = empty( $pricingData->couponCode ) ? [] : [ [ 'coupon' => $pricingData->couponCode ] ];
		// Stripe Checkout's coupon discounts a product and its recovery fee line together, so it goes on the
		// invoice. Otherwise a coupon on each item discounts every product as if it was bought on its own.
		$discountsEachItem = ! $this->addsRecoveryFeeLine( $form );
		if ( ! empty( $discounts ) && ! $discountsEachItem ) {
			$result['discounts'] = $discounts;
		}

		$invoiceItems = [];
		foreach ( $products as $product ) {
			$invoiceItem = null;

			if ( isset( $product->isCustomAmount ) ) {
				$invoiceItem = [ 
					'amount' => $pricingData->customAmount,
					'currency' => $this->extractCurrencyFromForm( $form ),
					'description' => __( 'Other amount', 'wp-full-stripe-free' ),
					'metadata' => [ 
						'type' => 'customAmount'
					]
				];
			} else {
				$invoiceItem = [ 
					'price' => $product->stripePriceId
				];
			}
			if ( ! $pricingData->stripeTax ) {
				$invoiceItem['tax_rates'] = $taxRateIds;
			}
			if ( ! empty( $discounts ) && $discountsEachItem ) {
				$invoiceItem['discounts'] = $discounts;
			}

			array_push( $invoiceItems, $invoiceItem );

			$amount = isset( $product->isCustomAmount ) ? (int) $pricingData->customAmount : (int) $product->price;
			$currency = $this->extractCurrencyFromForm( $form );
			$recoveryFeeItem = $this->prepareRecoveryFeeInvoiceItem( $form, $amount, $currency, isset( $product->isCustomAmount ) ? Main::PRICE_ID_CUSTOM_AMOUNT : $product->stripePriceId );
			// Stripe Checkout charges no fee on a price its coupon makes free. Otherwise an amount-off
			// coupon would be spread over the fee line too, and the preview would charge part of the fee.
			if ( ! is_null( $recoveryFeeItem ) && ! $this->isPriceMadeFreeByAnyProductCoupon( $amount, $currency ) ) {
				array_push( $invoiceItems, $recoveryFeeItem );
			}
		}
		$result['invoice_items'] = $invoiceItems;

		return $result;
	}

	protected function extractPaymentDetailsFromInvoiceLineItems( $lineItems, $taxRates, $products ) {
		$productPricing = [];
		$recoveryFeeItems = [];

		foreach ( $lineItems as $lineItem ) {
			if ( $this->isRecoveryFeeLineItem( $lineItem ) ) {
				array_push( $recoveryFeeItems, $this->createRecoveryFeeDisplayItem( $lineItem ) );
				continue;
			}

			$dislayItems = [];

			$priceId = $lineItem->pricing->price_details->price;
			$metaData = $lineItem->metadata;
			if (
				$metaData !== null && isset( $metaData->type ) &&
				$metaData->type === Main::LINE_ITEM_TYPE_CUSTOM_AMOUNT
			) {
				$priceId = Main::PRICE_ID_CUSTOM_AMOUNT;
			}
			$currency = $lineItem->currency;
			$displayName = $lineItem->description;
			$amount = $lineItem->amount;

			$displayItem = new \StdClass;
			$displayItem->type = Main::LINE_ITEM_TYPE_PRODUCT;
			$displayItem->subType = Main::LINE_ITEM_TYPE_PRODUCT;
			$displayItem->id = $priceId;
			$displayItem->displayName = $displayName;
			$displayItem->currency = $currency;
			$displayItem->amount = $amount;
			array_push( $dislayItems, $displayItem );

			$discountTotal = 0;
			foreach ( $lineItem->discount_amounts as $discountAmount ) {
				$discountTotal += $discountAmount->amount;
			}
			if ( $discountTotal > 0 ) {
				$displayItem = new \StdClass;
				$displayItem->type = Main::LINE_ITEM_TYPE_PRODUCT;
				$displayItem->subType = Main::LINE_ITEM_SUBTYPE_DISCOUNT;
				$displayItem->id = null;
				$displayItem->displayName = __( 'Discount', 'wp-full-stripe-free' );
				$displayItem->currency = $currency;
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
						$displayItem->type = Main::LINE_ITEM_TYPE_PRODUCT;
						$displayItem->subType = Main::LINE_ITEM_SUBTYPE_TAX;
						$displayItem->id = $taxRateId;
						$displayItem->displayName = $this->determineTaxLabel( $taxItem );
						$displayItem->currency = $currency;
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
						$displayItem->type = Main::LINE_ITEM_TYPE_PRODUCT;
						$displayItem->subType = Main::LINE_ITEM_SUBTYPE_TAX;
						$displayItem->id = $taxRate->taxRateId;
						$displayItem->displayName = $taxRate->displayName;
						$displayItem->currency = $currency;
						$displayItem->amount = $taxAmount;
						$displayItem->percentage = $taxRate->percentage;
						$displayItem->inclusive = $taxRate->inclusive;
						array_push( $dislayItems, $displayItem );
					}
				}
			}

			$productPricing[ $priceId ] = $dislayItems;
		}

		foreach ( $recoveryFeeItems as $recoveryFeeItem ) {
			if ( ! array_key_exists( $recoveryFeeItem->id, $productPricing ) ) {
				continue;
			}
			// A coupon limited to some products never discounts the fee line, so a product it makes
			// free still has one, which Stripe Checkout leaves out.
			if ( $this->isPreviewedPriceFree( $productPricing[ $recoveryFeeItem->id ] ) ) {
				continue;
			}
			array_push( $productPricing[ $recoveryFeeItem->id ], $recoveryFeeItem );
		}

		return $productPricing;
	}

	/**
	 * Whether a coupon that applies to every product makes a price free, as
	 * CheckoutSessionBuilderProduct::isPriceMadeFreeByCoupon() decides for the session.
	 *
	 * @param int    $amount   The price, in the smallest currency unit.
	 * @param string $currency
	 * @return bool
	 */
	private function isPriceMadeFreeByAnyProductCoupon( $amount, $currency ) {
		$coupon = isset( $this->pricingData->coupon ) ? $this->pricingData->coupon : null;
		if ( is_null( $coupon ) || ! empty( $coupon->applies_to->products ) ) {
			return false;
		}

		return Pricing::doesCouponCoverAmount( $coupon, $amount, $currency );
	}

	/**
	 * Whether the preview discounts a product to nothing.
	 *
	 * @param list<\stdClass> $displayItems The product's payment details.
	 * @return bool
	 */
	private function isPreviewedPriceFree( $displayItems ) {
		$amount = 0;
		foreach ( $displayItems as $displayItem ) {
			if ( Main::LINE_ITEM_SUBTYPE_DISCOUNT === $displayItem->subType || Main::LINE_ITEM_TYPE_PRODUCT === $displayItem->subType ) {
				$amount += $displayItem->amount;
			}
		}

		return $amount <= 0;
	}
}
