<?php

namespace ThemeIsle\WPFullPay\Payments;

use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Stripe;

/**
 * Writes a payment from its PaymentIntent's latest charge while holding the payment. Its webhooks and the requests
 * that record it run in parallel: one that read Stripe before a refund must not write after the refund was recorded.
 */
class LatestCharge {

	/**
	 * The latest charge a PaymentIntent was read with, when the read expanded it.
	 *
	 * @param \StripeWPFS\Stripe\PaymentIntent|\stdClass $paymentIntent
	 *
	 * @return \StripeWPFS\Stripe\Charge|\stdClass|null
	 */
	public static function expandedOf( $paymentIntent ) {
		return isset( $paymentIntent->latest_charge ) && is_object( $paymentIntent->latest_charge ) ? $paymentIntent->latest_charge : null;
	}

	/**
	 * @template T
	 *
	 * @param Database $db
	 * @param Stripe $stripe
	 * @param string $paymentIntentId
	 * @param callable(\StripeWPFS\Stripe\Charge|\stdClass|null): T $write Given null when the PaymentIntent has no charge.
	 * @param bool $mustHold False to write anyway when another request holds the payment for too long, from a read
	 *                       without the lock. When Stripe can't be read, $write gets null, and the caller uses what it
	 *                       read before. An inline request recording a payment the customer just made does, since
	 *                       nothing would retry it. Either way, PaymentReconciler brings a refund made meanwhile later.
	 *
	 * @return T
	 * @throws \Exception When $mustHold is true and another request holds the payment for too long or Stripe can't be
	 *                    read, so that whatever retries the write, a webhook delivery or the Checkout recovery, does.
	 */
	public static function write( Database $db, $stripe, $paymentIntentId, callable $write, $mustHold = true ) {
		$held = $db->lockPaymentIntent( $paymentIntentId, Database::PAYMENT_LOCK_TIMEOUT );
		if ( ! $held && $mustHold ) {
			throw new \Exception( "Another request is updating the payment '{$paymentIntentId}'." );
		}
		// Written without holding the payment or a fresh read: a refund made meanwhile is brought to it later.
		$reconcile = ! $held;
		try {
			try {
				$paymentIntent = $stripe->retrievePaymentIntent( $paymentIntentId );
				$latestCharge = empty( $paymentIntent->latest_charge ) ? null : $stripe->getLatestCharge( $paymentIntent );
			} catch ( \Exception $ex ) {
				if ( $mustHold ) {
					throw $ex;
				}
				// The payment is still recorded, from what the request read before.
				$latestCharge = null;
				$reconcile = true;
			}

			$result = $write( $latestCharge );
			if ( $reconcile ) {
				PaymentReconciler::schedule( $paymentIntentId );
			}

			return $result;
		} finally {
			if ( $held ) {
				$db->unlockPaymentIntent( $paymentIntentId );
			}
		}
	}
}
