<?php

namespace ThemeIsle\WPFullPay\Locale;

class Currency {

	const ARRAY_KEY_CODE = 'code';
	const ARRAY_KEY_NAME = 'name';
	const ARRAY_KEY_SYMBOL = 'symbol';
	const ARRAY_KEY_ZERO_DECIMAL_SUPPORT = 'zeroDecimalSupport';

	/**
	 * @var string
	 */
	protected $code;
	/**
	 * @var string
	 */
	protected $name;
	/**
	 * @var string
	 */
	protected $symbol;
	/**
	 * @var bool
	 */
	protected $zeroDecimalSupport;

	/**
	 * Currency constructor.
	 *
	 * @param string $code
	 * @param string $name
	 * @param string $symbol
	 * @param bool $zeroDecimalSupport
	 *
	 * @throws \Exception
	 */
	public function __construct( $code, $name, $symbol, $zeroDecimalSupport ) {
		if ( empty( $code ) ) {
			throw new \Exception( 'code is empty' );
		}
		if ( empty( $name ) ) {
			throw new \Exception( 'name is empty' );
		}
		if ( empty( $symbol ) ) {
			throw new \Exception( 'symbol is empty' );
		}
		if ( is_null( $zeroDecimalSupport ) ) {
			throw new \Exception( 'zeroDecimalSupport is null' );
		}
		$this->code               = $code;
		$this->name               = $name;
		$this->symbol             = $symbol;
		$this->zeroDecimalSupport = $zeroDecimalSupport;
	}

	/**
	 * @param array $currencyArray
	 *
	 * @return Currency
	 * @throws \Exception
	 */
	public static function fromArray( array $currencyArray ) {
		if ( is_array( $currencyArray ) ) {
			$code               = null;
			$name               = null;
			$symbol             = null;
			$zeroDecimalSupport = null;
			if ( array_key_exists( self::ARRAY_KEY_CODE, $currencyArray ) ) {
				$code = $currencyArray[ self::ARRAY_KEY_CODE ];
			}
			if ( array_key_exists( self::ARRAY_KEY_NAME, $currencyArray ) ) {
				$name = $currencyArray[ self::ARRAY_KEY_NAME ];
			}
			if ( array_key_exists( self::ARRAY_KEY_SYMBOL, $currencyArray ) ) {
				$symbol = $currencyArray[ self::ARRAY_KEY_SYMBOL ];
			}
			if ( array_key_exists( self::ARRAY_KEY_ZERO_DECIMAL_SUPPORT, $currencyArray ) ) {
				$zeroDecimalSupport = $currencyArray[ self::ARRAY_KEY_ZERO_DECIMAL_SUPPORT ];
			}

			return new Currency( $code, $name, $symbol, $zeroDecimalSupport );
		}
		throw new \Exception( 'parameter is not an array' );
	}

	/**
	 * @return string
	 */
	public function getCode() {
		return $this->code;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getSymbol() {
		return $this->symbol;
	}

	/**
	 * @return boolean
	 */
	public function hasZeroDecimalSupport() {
		return $this->zeroDecimalSupport;
	}

}
