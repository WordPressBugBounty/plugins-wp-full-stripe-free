<?php

namespace ThemeIsle\WPFullPay\Locale;

class CurrencyFormat {

	/**
	 * Comma character used as decimal separator
	 */
	const DECIMAL_SEPARATOR_COMMA = ',';
	/**
	 * Dot character used as decimal separator
	 */
	const DECIMAL_SEPARATOR_DOT = '.';

	const GROUP_SEPARATOR_EMPTY = '';
	const GROUP_SEPARATOR_WHITESPACE = ' ';
	const GROUP_SEPARATOR_COMMA = ',';

	/**
	 * Longest integer part accepted for a typed amount, keeps the result within an integer.
	 */
	const MAX_INTEGER_DIGITS = 12;

	/**
	 * @var Currency
	 */
	protected $currency;
	protected $decimalSeparator;
	protected $groupSeparatorSymbol;
	protected $showCurrencySymbolInsteadOfCode;
	protected $showCurrencySignAtFirstPosition;
	protected $putWhitespaceBetweenCurrencyAndAmount;

	/**
	 * CurrencyFormat constructor.
	 *
	 * @param Currency $currency
	 * @param string $decimalSeparator
	 * @param bool $showCurrencySymbolInsteadOfCode
	 * @param bool $showCurrencySignAtFirstPosition
	 * @param bool $putWhitespaceBetweenCurrencyAndAmount
	 *
	 * @throws \Exception
	 */
	public function __construct(
		$currency,
		$decimalSeparator = self::DECIMAL_SEPARATOR_DOT,
		$showCurrencySymbolInsteadOfCode = true,
		$showCurrencySignAtFirstPosition = true,
		$putWhitespaceBetweenCurrencyAndAmount = false
	) {
		if ( is_null( $currency ) ) {
			throw new \Exception( 'currency is null' );
		}
		if (
			self::DECIMAL_SEPARATOR_DOT !== $decimalSeparator
			&& self::DECIMAL_SEPARATOR_COMMA !== $decimalSeparator
		) {
			throw new \Exception( 'decimalSeparator has an invalid value: ' . $decimalSeparator );
		}
		if ( ! is_bool( $showCurrencySymbolInsteadOfCode ) ) {
			throw new \Exception( 'showCurrencySymbolInsteadOfCode is not a boolean: ' . $showCurrencySymbolInsteadOfCode );
		}
		if ( ! is_bool( $showCurrencySignAtFirstPosition ) ) {
			throw new \Exception( 'showCurrencySignAtFirstPosition is not a boolean: ' . $showCurrencySignAtFirstPosition );
		}
		if ( ! is_bool( $putWhitespaceBetweenCurrencyAndAmount ) ) {
			throw new \Exception( 'putWhitespaceBetweenCurrencyAndAmount is not a boolean: ' . $putWhitespaceBetweenCurrencyAndAmount );
		}
		$this->currency                              = $currency;
		$this->decimalSeparator                      = $decimalSeparator;
		$this->groupSeparatorSymbol                  = self::GROUP_SEPARATOR_EMPTY;
		$this->showCurrencySymbolInsteadOfCode       = $showCurrencySymbolInsteadOfCode;
		$this->showCurrencySignAtFirstPosition       = $showCurrencySignAtFirstPosition;
		$this->putWhitespaceBetweenCurrencyAndAmount = $putWhitespaceBetweenCurrencyAndAmount;
	}

	/**
	 * @param $amount
	 * @param $decimalsForInteger
	 * @param $showCurrency
	 *
	 * @return string|void
	 * @throws \Exception
	 */
	public function formatAndEscape( $amount, $decimalsForInteger, $showCurrency ) {
		return esc_attr( $this->format( $amount, $decimalsForInteger, $showCurrency ) );
	}

	/**
	 * @param $amount
	 * @param bool $decimalsForInteger
	 * @param bool $showCurrency
	 *
	 * @return null|string
	 * @throws \Exception
	 */
	public function format( $amount, $decimalsForInteger = true, $showCurrency = true ) {
		if ( ! is_numeric( $amount ) ) {
			throw new \Exception( 'amount is not a number: ' . print_r( $amount, true ) );
		}
		if ( $this->currency->hasZeroDecimalSupport() ) {
			// tnagy we don't use decimals
			$amountString = number_format( $amount, 0, $this->decimalSeparator, $this->groupSeparatorSymbol );
		} else {
			$normalizedAmount = $amount / 100.0;
			if ( false === $decimalsForInteger && intval( $normalizedAmount ) == ( $normalizedAmount + 0 ) ) {
				// tnagy we use decimals but the current amount is an integer
				// tnagy we pass decimalSeparatorSymbol too because we pass groupSeparator too
				$amountString = number_format( $normalizedAmount, 0, $this->decimalSeparator, $this->groupSeparatorSymbol );
			} else {
				// tnagy we use decimals with the selected decimal separator and group separator
				$amountString = number_format( $normalizedAmount, 2, $this->decimalSeparator, $this->groupSeparatorSymbol );
			}
		}
		// tnagy assemble the pattern
		$pattern = '%s';
		if ( $showCurrency ) {
			// tnagy currency symbol or code
			$currencyString = $this->showCurrencySymbolInsteadOfCode ? $this->currency->getSymbol() : $this->currency->getCode();
			// tnagy currency and amount with or without whitespace
			$whitespaceString = $this->putWhitespaceBetweenCurrencyAndAmount ? ' ' : '';
			if ( $this->showCurrencySignAtFirstPosition ) {
				// tnagy currency sign at first position
				$pattern = '%s' . $whitespaceString . $pattern;
				// tnagy format currency amount string
				$result = sprintf( $pattern, $currencyString, $amountString );
			} else {
				// tnagy currency sign at last position
				$pattern = $pattern . $whitespaceString . '%s';
				// tnagy format currency amount string
				$result = sprintf( $pattern, $amountString, $currencyString );
			}
		} else {
			// tnagy format currency amount string
			$result = sprintf( $pattern, $amountString );
		}

		return $result;

	}

	/**
	 * Parse an amount with the current decimal separator
	 *
	 * @deprecated 8.6.0 Strips unexpected characters instead of rejecting them, use parseToSmallestCurrencyUnit().
	 *
	 * @param $amount
	 *
	 * @return mixed|null
	 */
	public function parse( $amount ) {
		_deprecated_function( __METHOD__, '8.6.0', __CLASS__ . '::parseToSmallestCurrencyUnit()' );

		$parsedAmount = null;
		if ( ! is_null( $amount ) ) {
			if ( self::DECIMAL_SEPARATOR_COMMA === $this->decimalSeparator ) {
				$parsedAmount = preg_replace( '/[^0-9,]+/', '', $amount );
				$parsedAmount = preg_replace( '/[,]+/', '.', $parsedAmount );
			} else {
				$parsedAmount = preg_replace( '/[^0-9.]+/', '', $amount );
			}
		}

		return $parsedAmount;
	}

	/**
	 * Parse an amount typed by a customer into the smallest currency unit.
	 *
	 * Only digits are accepted, optionally followed by the current decimal separator and at most
	 * as many digits as the currency has decimals. Group separators, signs, currency signs and the
	 * other decimal separator are rejected rather than guessed, because guessing changes the
	 * amount charged (e.g. "1.234" on a comma form).
	 *
	 * @param mixed $amount
	 *
	 * @return int|null The amount in the smallest currency unit, or null when the amount is malformed.
	 */
	public function parseToSmallestCurrencyUnit( $amount ) {
		if ( ! is_string( $amount ) && ! is_int( $amount ) ) {
			return null;
		}

		$decimals = $this->currency->hasZeroDecimalSupport() ? 0 : 2;
		$pattern  = '/^(\d{1,' . self::MAX_INTEGER_DIGITS . '})';
		if ( $decimals > 0 ) {
			$pattern .= '(?:' . preg_quote( $this->decimalSeparator, '/' ) . '(\d{1,' . $decimals . '}))?';
		}
		$pattern .= '$/';

		if ( 1 !== preg_match( $pattern, trim( (string) $amount ), $matches ) ) {
			return null;
		}

		$fraction = isset( $matches[2] ) ? $matches[2] : '';

		return (int) ( $matches[1] . str_pad( $fraction, $decimals, '0' ) );
	}

	/**
	 * An example amount written the way parseToSmallestCurrencyUnit() expects it, e.g. "12,34".
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function formatExampleAmount() {
		return $this->format( $this->currency->hasZeroDecimalSupport() ? 12 : 1234, true, false );
	}

}
