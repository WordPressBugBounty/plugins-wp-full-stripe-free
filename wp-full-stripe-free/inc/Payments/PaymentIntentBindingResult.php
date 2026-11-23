<?php

namespace ThemeIsle\WPFullPay\Payments;

/**
 * Outcome of a PaymentIntent binding check: valid, or invalid.
 * Reasons are never returned to the client, so a caller cannot
 * probe foreign PaymentIntents through the error message.
 */
class PaymentIntentBindingResult {
	/** @var string|null */
	private $reasonCode;

	/**
	 * @param string|null $reasonCode
	 */
	private function __construct( $reasonCode ) {
		$this->reasonCode = $reasonCode;
	}

	/**
	 * @return PaymentIntentBindingResult
	 */
	public static function valid() {
		return new self( null );
	}

	/**
	 * @param string $reasonCode One of the PaymentIntentBinding::REASON_* constants.
	 * @return PaymentIntentBindingResult
	 */
	public static function invalid( $reasonCode ) {
		return new self( $reasonCode );
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		return is_null( $this->reasonCode );
	}

	/**
	 * @return string|null
	 */
	public function getReasonCode() {
		return $this->reasonCode;
	}
}
