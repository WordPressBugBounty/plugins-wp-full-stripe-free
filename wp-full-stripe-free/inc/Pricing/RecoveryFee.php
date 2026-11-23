<?php

namespace ThemeIsle\WPFullPay\Pricing;

use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\Utils;

/**
 * Finds the recovery fee in what Stripe charged: a subscription's fee item, its line on an invoice, or the fee line of
 * a Checkout session. Receipts, hooks, the customer portal, plan changes and invoices read the fee from these, never
 * from the form's current fee settings or fee choice, which may have changed since the charge.
 */
class RecoveryFee {

	/**
	 * Whether a subscription item is the plugin's fee recovery line, rather than a product added to the subscription
	 * some other way: the shared fee price of an inline subscription or recurring donation, the marked or "Transaction
	 * Fee" product of a Checkout subscription, or a fee a plan change repriced.
	 *
	 * @param object $item A subscription item, with its price.
	 * @param (callable(string): (object|null))|null $retrieveProduct Looks up a product its price only names by id.
	 *
	 * @return bool
	 */
	public static function isFeeItem( $item, $retrieveProduct = null ) {
		if ( self::hasMark( $item ) ) {
			return true;
		}
		if ( isset( $item->price->lookup_key ) && 0 === strpos( (string) $item->price->lookup_key, Main::SUBSCRIPTION_PLAN_ID_PREFIX ) ) {
			return true;
		}

		$product = self::findProductOfPrice( isset( $item->price ) ? $item->price : null, $retrieveProduct );
		if ( self::hasMark( $product ) ) {
			return true;
		}

		// Checkout's legacy fee price and product are inactive, unlike a merchant's active catalog product.
		return empty( $item->price->active ) && empty( $product->active ) && self::isFeeProduct( $product );
	}

	/**
	 * The plan's and the fee's item of a subscription created with a fee recovery line: two items, one priced with the
	 * plan, the other the fee. The order Stripe lists the items in doesn't matter.
	 *
	 * @param object $subscription
	 * @param string $planId The plan the subscription was created with or last changed to.
	 * @param (callable(string): (object|null))|null $retrieveProduct
	 *
	 * @return array{planItem: object, feeItem: object}|null Null for any other subscription.
	 */
	public static function findPlanAndFeeItems( $subscription, $planId, $retrieveProduct = null ) {
		$items = isset( $subscription->items->data ) ? array_values( (array) $subscription->items->data ) : [];
		if ( 2 !== count( $items ) || '' === (string) $planId ) {
			return null;
		}

		foreach ( [ 0, 1 ] as $index ) {
			if (
				isset( $items[ $index ]->price->id )
				&& (string) $items[ $index ]->price->id === (string) $planId
				&& self::isFeeItem( $items[ 1 - $index ], $retrieveProduct )
			) {
				return [
					'planItem' => $items[ $index ],
					'feeItem' => $items[ 1 - $index ],
				];
			}
		}

		return null;
	}

	/**
	 * The plan's and the fee's item of a subscription created with a fee recovery line, priced with the plan its
	 * subscriber row records, and the fee settings of the form it was created on. The settings reprice the fee when
	 * the plan or quantity changes; they're empty once the form no longer recovers fees.
	 *
	 * @param object $subscription
	 * @param (callable(string): (object|null))|null $retrieveProduct
	 *
	 * @return array{planItem: object, feeItem: object, feeRecoveryData: array<string, mixed>}|null Null for any other subscription.
	 */
	public static function findFeeRecoveryOfSubscription( $subscription, $retrieveProduct = null ) {
		if ( ! isset( $subscription->items->data ) || 2 !== count( (array) $subscription->items->data ) ) {
			return null;
		}

		$db = new Database();
		$subscriber = $db->getSubscriptionByStripeSubscriptionId( $subscription->id );
		$recordedPlanId = is_object( $subscriber ) && isset( $subscriber->planID ) ? (string) $subscriber->planID : '';
		$items = self::findPlanAndFeeItems( $subscription, $recordedPlanId, $retrieveProduct );
		if ( is_null( $items ) ) {
			return null;
		}

		$form = null;
		if ( isset( $subscription->metadata->form_name ) && '' !== (string) $subscription->metadata->form_name ) {
			$form = $db->getInlineSubscriptionFormByName( $subscription->metadata->form_name );
			$form = $form ?: $db->getCheckoutSubscriptionFormByName( $subscription->metadata->form_name );
		}

		return array_merge( $items, [ 'feeRecoveryData' => Utils::getFeeRecoveryData( $form ) ] );
	}

	/**
	 * The fee a fee item bills every period: the shared fee price is one minor unit charged the fee as its quantity,
	 * a Checkout or repriced fee is the fee charged once.
	 *
	 * @param object $feeItem
	 *
	 * @return int In the smallest unit of the item's currency.
	 */
	public static function getAmountOfItem( $feeItem ) {
		$unitAmount = isset( $feeItem->price->unit_amount ) ? (int) $feeItem->price->unit_amount : 0;
		$quantity = isset( $feeItem->quantity ) ? (int) $feeItem->quantity : 1;

		return $unitAmount * $quantity;
	}

	/**
	 * The line of an invoice that billed a subscription item.
	 *
	 * @param object $invoice
	 * @param object $item
	 *
	 * @return object|null
	 */
	public static function findLineItemOfItem( $invoice, $item ) {
		$itemId = isset( $item->id ) ? (string) $item->id : '';
		$priceId = isset( $item->price->id ) ? (string) $item->price->id : '';

		foreach ( isset( $invoice->lines->data ) ? $invoice->lines->data : [] as $lineItem ) {
			// Basil (2025-03-31) moved the subscription item under parent, and the price under pricing.
			$lineItemId = isset( $lineItem->parent->subscription_item_details->subscription_item ) ?
				$lineItem->parent->subscription_item_details->subscription_item :
				( isset( $lineItem->subscription_item ) ? $lineItem->subscription_item : null );
			if ( ! is_null( $lineItemId ) ) {
				if ( '' !== $itemId && (string) $lineItemId === $itemId ) {
					return $lineItem;
				}
				continue;
			}

			$linePriceId = isset( $lineItem->pricing->price_details->price ) ?
				$lineItem->pricing->price_details->price :
				( isset( $lineItem->price->id ) ? $lineItem->price->id : null );
			if ( '' !== $priceId && (string) $linePriceId === $priceId ) {
				return $lineItem;
			}
		}

		return null;
	}

	/**
	 * What an invoice line charged: its amount, less its share of the discounts, plus the tax added on top of it.
	 *
	 * @param object $lineItem
	 *
	 * @return int
	 */
	public static function getChargedAmountOfLineItem( $lineItem ) {
		$amount = isset( $lineItem->amount ) ? (int) $lineItem->amount : 0;

		foreach ( isset( $lineItem->discount_amounts ) ? $lineItem->discount_amounts : [] as $discountAmount ) {
			$amount -= (int) $discountAmount->amount;
		}

		// Basil (2025-03-31) renamed tax_amounts to taxes.
		$taxes = isset( $lineItem->taxes ) ? $lineItem->taxes : ( isset( $lineItem->tax_amounts ) ? $lineItem->tax_amounts : [] );
		foreach ( $taxes as $tax ) {
			if ( ! Pricing::isTaxAmountInclusive( $tax ) ) {
				$amount += (int) $tax->amount;
			}
		}

		return $amount;
	}

	/**
	 * Records what an invoice of a subscription charged: the line of its fee item, and the invoice's total.
	 *
	 * @param \ThemeIsle\WPFullPay\TransactionData\SubscriptionTransactionData $transactionData
	 * @param object $invoice
	 * @param array{planItem: object, feeItem: object}|null $items The subscription's plan and fee items, or null without a fee.
	 */
	public static function setChargedFeeOfInvoice( $transactionData, $invoice, $items ): void {
		$feeLineItem = is_null( $items ) ? null : self::findLineItemOfItem( $invoice, $items['feeItem'] );

		$transactionData->setRecoveryFee( is_null( $feeLineItem ) ? 0 : self::getChargedAmountOfLineItem( $feeLineItem ) );
		if ( isset( $invoice->total ) ) {
			$transactionData->setChargedTotal( (int) $invoice->total );
		}
	}

	/**
	 * Whether a line of a Checkout session is its recovery fee. The session's line items are retrieved with their
	 * prices' products, which carry the mark; sessions created before the mark have the "Transaction Fee" product.
	 *
	 * @param object $lineItem
	 *
	 * @return bool
	 */
	public static function isFeeLineItemOfCheckoutSession( $lineItem ) {
		return self::isFeeProduct( self::findProductOfLineItem( $lineItem ) );
	}

	/**
	 * @param object $lineItem
	 *
	 * @return object|null The line's product, when the session was retrieved with it.
	 */
	private static function findProductOfLineItem( $lineItem ) {
		return isset( $lineItem->price->product ) && is_object( $lineItem->price->product ) ? $lineItem->price->product : null;
	}

	/**
	 * The recovery fee a Checkout session charged, after its share of a promotion code and with any tax on it.
	 *
	 * @param object $checkoutSession Retrieved with its line items and their prices' products.
	 *
	 * @return int 0 when the session charged no fee.
	 */
	public static function getFeeOfCheckoutSession( $checkoutSession ) {
		$fee = 0;
		foreach ( self::findFeeLineItemsOfCheckoutSession( $checkoutSession ) as $lineItem ) {
			$fee += (int) $lineItem->amount_total;
		}

		return $fee;
	}

	/**
	 * The recovery fee lines of a Checkout session. A merchant's product may be called "Transaction Fee" too, so when a
	 * line carries the fee's mark only marked lines are the fee, and on sessions created before the mark a line named
	 * like the fee is the fee only beside another product line, not just a subscription's setup fee.
	 *
	 * @param object $checkoutSession Retrieved with its line items and their prices' products.
	 *
	 * @return list<object>
	 */
	public static function findFeeLineItemsOfCheckoutSession( $checkoutSession ) {
		$lineItems = isset( $checkoutSession->line_items->data ) ? array_values( $checkoutSession->line_items->data ) : [];
		$markedLineItems = array_values(
			array_filter(
				$lineItems,
				static function ( $lineItem ) {
					return self::hasMark( self::findProductOfLineItem( $lineItem ) );
				}
			)
		);
		if ( ! empty( $markedLineItems ) ) {
			return $markedLineItems;
		}

		$productLineItems = array_values(
			array_filter(
				$lineItems,
				static function ( $lineItem ) {
					$product = self::findProductOfLineItem( $lineItem );

					return ! isset( $product->metadata->type ) || 'setupFee' !== $product->metadata->type;
				}
			)
		);
		$namedLineItems = array_values( array_filter( $productLineItems, [ self::class, 'isFeeLineItemOfCheckoutSession' ] ) );

		return count( $namedLineItems ) < count( $productLineItems ) ? $namedLineItems : [];
	}

	/**
	 * The line of a Checkout payment's product or donation: the session's first line that isn't its recovery fee.
	 *
	 * @param object $checkoutSession Retrieved with its line items and their prices' products.
	 *
	 * @return object|null Null when the session has no other line.
	 */
	public static function findProductLineItemOfCheckoutSession( $checkoutSession ) {
		$feeLineItems = self::findFeeLineItemsOfCheckoutSession( $checkoutSession );
		foreach ( isset( $checkoutSession->line_items->data ) ? $checkoutSession->line_items->data : [] as $lineItem ) {
			if ( ! in_array( $lineItem, $feeLineItems, true ) ) {
				return $lineItem;
			}
		}

		return null;
	}

	/**
	 * @param object|null $product
	 *
	 * @return bool
	 */
	private static function isFeeProduct( $product ) {
		if ( ! is_object( $product ) ) {
			return false;
		}
		if ( self::hasMark( $product ) ) {
			return true;
		}

		// Checkout fees created before the product was marked, named in the language of the checkout.
		$name = isset( $product->name ) ? (string) $product->name : '';

		return in_array( $name, [ 'Transaction Fee', __( 'Transaction Fee', 'wp-full-stripe-free' ) ], true );
	}

	/**
	 * @param object|null $price
	 * @param (callable(string): (object|null))|null $retrieveProduct
	 *
	 * @return object|null
	 */
	private static function findProductOfPrice( $price, $retrieveProduct ) {
		if ( ! isset( $price->product ) ) {
			return null;
		}
		if ( is_object( $price->product ) ) {
			return $price->product;
		}
		// The customer portal looks the products up beforehand.
		if ( isset( $price->productExpanded ) && is_object( $price->productExpanded ) ) {
			return $price->productExpanded;
		}

		return is_null( $retrieveProduct ) ? null : call_user_func( $retrieveProduct, (string) $price->product );
	}

	/**
	 * @param object|null $stripeObject A subscription item or a product.
	 *
	 * @return bool
	 */
	private static function hasMark( $stripeObject ) {
		return isset( $stripeObject->metadata->{Stripe::METADATA_KEY_RECOVERY_FEE_ITEM} ) && '1' === (string) $stripeObject->metadata->{Stripe::METADATA_KEY_RECOVERY_FEE_ITEM};
	}
}
