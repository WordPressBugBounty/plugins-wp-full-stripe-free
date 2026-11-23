<?php

namespace ThemeIsle\WPFullPay\Pricing;

use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Utils;

class Pricing {
	public function __construct() {
	}

	public static function getCountryCodesRequiringPostalCodeForTax() {
		return [ 
			'CA',
			'US'
		];
	}

	public static function createFormPriceCalculator( $pricingData, $loggerService ) {
		switch ( $pricingData->formType ) {
			case Main::FORM_TYPE_INLINE_PAYMENT:
				return new InlinePaymentPriceCalculator( $pricingData, $loggerService );

			case Main::FORM_TYPE_CHECKOUT_PAYMENT:
				return new CheckoutPaymentPriceCalculator( $pricingData, $loggerService );

			case Main::FORM_TYPE_INLINE_SUBSCRIPTION:
				return new InlineSubscriptionPriceCalculator( $pricingData, $loggerService );

			case Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION:
				return new CheckoutSubscriptionPriceCalculator( $pricingData, $loggerService );

			default:
				Utils::log( __CLASS__ . '.' . __FUNCTION__ . ": unknown form type '{$pricingData->formType}'!" );
				throw new \Exception( "Unknown form type '{$pricingData->formType}'!" );
		}
	}

	/**
	 * Whether a coupon takes the whole amount off a price it applies to. Checking that it applies to
	 * the price's product is left to the caller.
	 *
	 * @param \stdClass|\StripeWPFS\Stripe\Coupon $coupon
	 * @param int                               $amount   The price, in the smallest currency unit.
	 * @param string                            $currency
	 * @return bool
	 */
	public static function doesCouponCoverAmount( $coupon, $amount, $currency ) {
		if ( ! empty( $coupon->percent_off ) ) {
			return $coupon->percent_off >= 100;
		}

		return ! empty( $coupon->amount_off )
			&& strtolower( (string) $coupon->currency ) === strtolower( $currency )
			&& $coupon->amount_off >= $amount;
	}

	/**
	 * Whether a Stripe invoice line-item tax entry is inclusive. Handles the newer Stripe
	 * `tax_behavior` ('inclusive'/'exclusive') shape as well as the legacy boolean `inclusive`
	 * property, so inclusive taxes aren't silently treated as exclusive (and no PHP warning is
	 * raised) when the line item carries the newer shape.
	 *
	 * @param object $taxAmount A line-item tax entry (from $lineItem->taxes).
	 * @return bool
	 */
	public static function isTaxAmountInclusive( $taxAmount ) {
		if ( property_exists( $taxAmount, 'tax_behavior' ) && $taxAmount->tax_behavior !== null ) {
			return 'inclusive' === $taxAmount->tax_behavior;
		}
		if ( property_exists( $taxAmount, 'inclusive' ) ) {
			return (bool) $taxAmount->inclusive;
		}

		return false;
	}

	public static function extractSimplifiedPricingFromInvoiceLineItems( $lineItems ) {
		$result = new \StdClass;
		$amountTotal = 0;
		$taxExclusiveTotal = 0;
		$taxInclusiveTotal = 0;
		$discountTotal = 0;

		foreach ( $lineItems as $lineItem ) {
			$amountTotal += $lineItem->amount;

			foreach ( $lineItem->discount_amounts as $discountAmount ) {
				$discountTotal += $discountAmount->amount;
			}

			foreach ( $lineItem->taxes as $taxAmount ) {
				if ( self::isTaxAmountInclusive( $taxAmount ) ) {
					$taxInclusiveTotal += $taxAmount->amount;
				} else {
					$taxExclusiveTotal += $taxAmount->amount;
				}
			}
		}

		$result->totalAmount = $amountTotal;
		$result->taxAmountExclusive = $taxExclusiveTotal;
		$result->taxAmountInclusive = $taxInclusiveTotal;
		$result->discountAmount = $discountTotal;

		return $result;
	}

	/**
	 * What a subscription's invoice charged for its plan and its setup fee. The plan's line is the one priced with
	 * the plan, so other lines, such as a recovery fee, aren't read as the plan. When no line is priced with the plan,
	 * every line but the setup fee's is read as the plan's, the last one winning.
	 *
	 * @param object $invoice
	 * @param string $planId
	 * @return object{product: object|null, setupFee: object|null}
	 */
	public static function extractSubscriptionPricingFromInvoice( $invoice, $planId ) {
		$result = new \StdClass;
		$result->product = null;
		$result->setupFee = null;

		$planLineItem = self::getPlanLineItem( $invoice, $planId );
		foreach ( $invoice->lines->data as $lineItem ) {
			$metaData = $lineItem->metadata;
			if ( $metaData !== null && isset( $metaData->type ) && $metaData->type === 'setupFee' ) {
				$result->setupFee = self::extractPricingOfLineItem( $lineItem );
			} elseif ( is_null( $planLineItem ) || $lineItem === $planLineItem ) {
				$result->product = self::extractPricingOfLineItem( $lineItem );
			}
		}

		return $result;
	}

	/**
	 * @param object $lineItem
	 * @return object{quantity: int, amount: int, taxExclusive: int, taxInclusive: int, discount: int}
	 */
	private static function extractPricingOfLineItem( $lineItem ) {
		$statItem = new \StdClass;
		$statItem->quantity = $lineItem->quantity;
		$statItem->amount = $lineItem->amount;
		$statItem->taxExclusive = 0;
		$statItem->taxInclusive = 0;
		$statItem->discount = 0;

		foreach ( $lineItem->discount_amounts as $discountAmount ) {
			$statItem->discount += $discountAmount->amount;
		}

		foreach ( $lineItem->taxes as $taxAmount ) {
			if ( self::isTaxAmountInclusive( $taxAmount ) ) {
				$statItem->taxInclusive += $taxAmount->amount;
			} else {
				$statItem->taxExclusive += $taxAmount->amount;
			}
		}

		return $statItem;
	}

	/**
	 * The setup fee per unit a subscription's first invoice charged.
	 *
	 * @param object $invoice The subscription's first invoice.
	 * @param bool $isCheckout Whether a Checkout session created the subscription.
	 * @return int|null 0 when the invoice charged no setup fee, null when it can't be told.
	 */
	public static function getSetupFeeOfFirstInvoice( $invoice, $isCheckout = false ) {
		return self::getSetupFeeOfLineItem( self::getSetupFeeLineItemOfFirstInvoice( $invoice, $isCheckout ) );
	}

	/**
	 * @param object|false|null $setupFeeLineItem As getSetupFeeLineItemOfFirstInvoice() returns it.
	 * @return int|null 0 when the invoice charged no setup fee, null when it can't be told.
	 */
	public static function getSetupFeeOfLineItem( $setupFeeLineItem ) {
		if ( false === $setupFeeLineItem ) {
			return 0;
		}
		if ( is_null( $setupFeeLineItem ) ) {
			return null;
		}

		return empty( $setupFeeLineItem->quantity ) ? null : intdiv( (int) $setupFeeLineItem->amount, (int) $setupFeeLineItem->quantity );
	}

	/**
	 * The setup fee's line of a subscription's first invoice. Inline subscriptions mark the fee's invoice item.
	 * Checkout marks the fee's product, which the invoice line doesn't carry, so there the fee is the one line
	 * the subscription doesn't bill, or of several, the one whose product is marked.
	 *
	 * @param object $invoice The subscription's first invoice.
	 * @param bool $isCheckout Whether a Checkout session created the subscription.
	 * @param (callable(string): bool)|null $isSetupFeeProduct Whether the product of an id is marked as the setup fee.
	 * @return object|false|null False when the invoice has no setup fee line, null when it can't be told.
	 */
	public static function getSetupFeeLineItemOfFirstInvoice( $invoice, $isCheckout = false, $isSetupFeeProduct = null ) {
		if ( empty( $invoice->lines->data ) ) {
			return null;
		}

		$oneTimeLineItems = array_values(
			array_filter(
				$invoice->lines->data,
				function ( $lineItem ) {
					return ! self::isSubscriptionLineItem( $lineItem );
				}
			)
		);

		if ( $isCheckout ) {
			if ( count( $oneTimeLineItems ) > 1 ) {
				return is_null( $isSetupFeeProduct ) ? null : self::findLineItemOfSetupFeeProduct( $oneTimeLineItems, $isSetupFeeProduct );
			}

			return isset( $oneTimeLineItems[0] ) ? $oneTimeLineItems[0] : false;
		}

		foreach ( $oneTimeLineItems as $lineItem ) {
			if ( isset( $lineItem->metadata->type ) && Main::LINE_ITEM_TYPE_SETUP_FEE === $lineItem->metadata->type ) {
				return $lineItem;
			}
		}

		return false;
	}

	/**
	 * @param list<object> $lineItems
	 * @param callable(string): bool $isSetupFeeProduct
	 * @return object|null Null unless exactly one line's product is marked.
	 */
	private static function findLineItemOfSetupFeeProduct( $lineItems, $isSetupFeeProduct ) {
		$setupFeeLineItems = array_values(
			array_filter(
				$lineItems,
				function ( $lineItem ) use ( $isSetupFeeProduct ) {
					$productId = self::getProductIdOfLineItem( $lineItem );

					return ! is_null( $productId ) && $isSetupFeeProduct( $productId );
				}
			)
		);

		return 1 === count( $setupFeeLineItems ) ? $setupFeeLineItems[0] : null;
	}

	/**
	 * @param object $lineItem
	 * @return string|null
	 */
	private static function getProductIdOfLineItem( $lineItem ) {
		// Basil (2025-03-31) moved the price under pricing.
		if ( isset( $lineItem->pricing->price_details->product ) ) {
			return $lineItem->pricing->price_details->product;
		}
		if ( isset( $lineItem->price->product ) ) {
			return $lineItem->price->product;
		}

		return null;
	}

	/**
	 * The line of an invoice that bills the plan.
	 *
	 * @param object $invoice
	 * @param string $planId
	 * @return object|null
	 */
	public static function getPlanLineItem( $invoice, $planId ) {
		foreach ( isset( $invoice->lines->data ) ? $invoice->lines->data : [] as $lineItem ) {
			// Basil (2025-03-31) moved the price under pricing.
			$priceId = isset( $lineItem->pricing->price_details->price ) ? $lineItem->pricing->price_details->price : ( isset( $lineItem->price->id ) ? $lineItem->price->id : null );
			if ( self::isSubscriptionLineItem( $lineItem ) && $planId === $priceId ) {
				return $lineItem;
			}
		}

		return null;
	}

	/**
	 * The tax an invoice line was charged, as one rate whether one tax rate, several or Stripe Tax set it. Each of
	 * several rates is charged on the same taxable amount, so their percentages add up. Stripe rounds the amounts,
	 * so the percentages come from the form's tax rates, and from the amounts only for a rate the form doesn't
	 * have, such as one Stripe Tax set.
	 *
	 * @param object|null $lineItem
	 * @param list<object{taxRateId?: string, percentage?: int|float}> $formTaxRates The form's tax rates, as its vatRates setting stores them.
	 * @return array{percent: float, inclusive: bool, taxed: bool} Taxed when the line had an amount to tax.
	 */
	public static function getTaxRateOfLineItem( $lineItem, $formTaxRates = [] ) {
		$percentages = [];
		foreach ( $formTaxRates as $formTaxRate ) {
			if ( isset( $formTaxRate->taxRateId, $formTaxRate->percentage ) ) {
				$percentages[ $formTaxRate->taxRateId ] = (float) $formTaxRate->percentage;
			}
		}

		$taxAmount = 0;
		$taxableAmount = 0;
		$inclusive = false;
		$percent = 0.0;
		$percentKnown = true;
		// Basil (2025-03-31) renamed tax_amounts to taxes and moved the tax rate under tax_rate_details.
		$taxes = isset( $lineItem->taxes ) ? $lineItem->taxes : ( isset( $lineItem->tax_amounts ) ? $lineItem->tax_amounts : [] );
		foreach ( $taxes as $tax ) {
			$taxAmount += (int) $tax->amount;
			$taxableAmount = max( $taxableAmount, isset( $tax->taxable_amount ) ? (int) $tax->taxable_amount : 0 );
			$inclusive = $inclusive || self::isTaxAmountInclusive( $tax );

			$taxRateId = isset( $tax->tax_rate_details->tax_rate ) ? $tax->tax_rate_details->tax_rate : ( isset( $tax->tax_rate ) ? $tax->tax_rate : null );
			if ( is_string( $taxRateId ) && isset( $percentages[ $taxRateId ] ) ) {
				$percent += $percentages[ $taxRateId ];
			} else {
				$percentKnown = false;
			}
		}

		if ( ! $percentKnown || empty( $taxes ) ) {
			$percent = $taxableAmount > 0 ? $taxAmount / $taxableAmount * 100 : 0.0;
		}

		return [
			'percent' => $percent,
			'inclusive' => $inclusive,
			'taxed' => $taxableAmount > 0,
		];
	}

	/**
	 * A subscription line carries the subscription's metadata, so its metadata can't mark a setup fee.
	 * Basil (2025-03-31) moved the line's type under `parent`.
	 *
	 * @param object $lineItem
	 * @return bool
	 */
	private static function isSubscriptionLineItem( $lineItem ) {
		return isset( $lineItem->parent->subscription_item_details )
			|| ( isset( $lineItem->type ) && 'subscription' === $lineItem->type );
	}

	public static function extractTaxRateIdsStatic( $taxRates ) {
		$taxRateIds = [];

		foreach ( $taxRates as $taxRate ) {
			array_push( $taxRateIds, $taxRate->taxRateId );
		}

		return $taxRateIds;
	}

	/**
	 * @param $savedProducts array
	 * @return array
	 */
	public static function extractPriceIdsFromProductsStatic( $savedProducts ) {
		$priceIds = [];

		foreach ( $savedProducts as $savedProduct ) {
			array_push( $priceIds, $savedProduct->stripePriceId );
		}

		return $priceIds;
	}
}
