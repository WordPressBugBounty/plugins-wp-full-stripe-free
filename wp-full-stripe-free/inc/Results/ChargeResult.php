<?php

namespace ThemeIsle\WPFullPay\Results;

class ChargeResult extends TransactionResult {

	/**
	 * @var string
	 */
	protected $paymentType;

	/**
	 * @return string
	 */
	public function getPaymentType() {
		return $this->paymentType;
	}

	/**
	 * @param string $paymentType
	 */
	public function setPaymentType( $paymentType ) {
		$this->paymentType = $paymentType;
	}
}
