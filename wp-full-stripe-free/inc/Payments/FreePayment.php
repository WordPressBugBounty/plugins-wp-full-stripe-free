<?php

namespace ThemeIsle\WPFullPay\Payments;

/**
 * The PaymentIntent and charge a payment that costs nothing is recorded with. Stripe creates neither for it.
 */
class FreePayment {

	/** The prefix of the IDs free payments are recorded under. */
	const ID_PREFIX = 'wpfs_free_pi_';

	/**
	 * @param string $id          An ID starting with ID_PREFIX.
	 * @param string $currency
	 * @param bool   $liveMode
	 * @param string $description
	 * @param string $formName
	 *
	 * @return object{id: string, amount: int, currency: string, created: int, livemode: bool, application_fee_amount: int, description: string, wpfs_form: string}
	 */
	public static function paymentIntent( $id, $currency, $liveMode, $description, $formName ) {
		return (object) [
			'id' => $id,
			'amount' => 0,
			'currency' => $currency,
			'created' => time(),
			'livemode' => $liveMode,
			'application_fee_amount' => 0,
			'description' => $description,
			'wpfs_form' => $formName,
		];
	}

	/**
	 * @param bool $liveMode
	 *
	 * @return object{paid: bool, captured: bool, refunded: bool, failure_code: null, failure_message: null, status: string, livemode: bool, created: int}
	 */
	public static function charge( $liveMode ) {
		return (object) [
			'paid' => true,
			'captured' => true,
			'refunded' => false,
			'failure_code' => null,
			'failure_message' => null,
			'status' => 'succeeded',
			'livemode' => $liveMode,
			'created' => time(),
		];
	}
}
