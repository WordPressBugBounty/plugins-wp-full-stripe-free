<?php

namespace ThemeIsle\WPFullPay\Locale;

use ThemeIsle\WPFullPay\Context\StaticContext;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;

class Currencies {

	/**
	 * @param $currency
	 *
	 * @return mixed|null
	 */
	public static function getCurrencySymbolFor($currency ) {
		$currencyArray = Currencies::getCurrencyFor( $currency );
		if ( is_array( $currencyArray ) && array_key_exists( 'symbol', $currencyArray ) ) {
			return $currencyArray['symbol'];
		}

		return null;
	}

	/**
	 * @param $currency
	 *
	 * @return mixed|null
	 */
	public static function getCurrencyFor($currency ) {
		if ( isset( $currency ) ) {
			$currencyKey         = strtolower( $currency );
			$availableCurrencies = Currencies::getAvailableCurrencies();
			if ( isset( $availableCurrencies ) && array_key_exists( $currencyKey, $availableCurrencies ) ) {
				$currencyArray = $availableCurrencies[ $currencyKey ];
			} else {
				$currencyArray = null;
			}

			return $currencyArray;
		}

		return null;
	}

	/**
	 * Convert a decimal amount to the currency's minor unit for Stripe API.
	 * 
	 * Stripe API expects amounts in the currency's smallest unit:
	 * - Two-decimal currencies (USD, EUR, etc.): multiply by 100 (e.g., 10.00 USD → 1000)
	 * - Zero-decimal currencies (JPY, KRW, etc.): use as-is (e.g., 1000 JPY → 1000)
	 * - Special cases: ISK and UGX require two-decimal representation with .00 in Stripe for backward compatibility.
	 *
	 * @param float|int $amount The decimal amount (e.g., 10.99 or 1000)
	 * @param string $currency The currency code (e.g., 'usd', 'jpy')
	 *
	 * @return int The amount in the currency's minor unit
	 * 
	 * @see https://docs.stripe.com/currencies#minor-units
	 */
	public static function convertAmountToMinorUnits( $amount, $currency ) {
		$currencyArray = self::getCurrencyFor( $currency );
		
		if ( ! is_array( $currencyArray ) ) {
			// Fallback: assume two-decimal currency
			return intval( round( floatval( $amount ) * 100 ) );
		}
		
		// Zero-decimal currencies: use amount as-is (no multiplication)
		if ( $currencyArray['zeroDecimalSupport'] ) {
			return intval( round( floatval( $amount ) ) );
		}
		
		// Two-decimal currencies: multiply by 100
		return intval( round( floatval( $amount ) * 100 ) );
	}

	/**
	 * @return array
	 */
	public static function getAvailableCurrencies() {
		return [
			'aed' => [
				'code'               => 'AED',
				'name'               => 'United Arab Emirates Dirham',
				'symbol'             => 'DH',
				'zeroDecimalSupport' => false
			],
			'afn' => [
				'code'               => 'AFN',
				'name'               => 'Afghan Afghani',
				'symbol'             => '؋',
				'zeroDecimalSupport' => false
			],
			'all' => [
				'code'               => 'ALL',
				'name'               => 'Albanian Lek',
				'symbol'             => 'L',
				'zeroDecimalSupport' => false
			],
			'amd' => [
				'code'               => 'AMD',
				'name'               => 'Armenian Dram',
				'symbol'             => '֏',
				'zeroDecimalSupport' => false
			],
			'ang' => [
				'code'               => 'ANG',
				'name'               => 'Netherlands Antillean Gulden',
				'symbol'             => 'ƒ',
				'zeroDecimalSupport' => false
			],
			'aoa' => [
				'code'               => 'AOA',
				'name'               => 'Angolan Kwanza',
				'symbol'             => 'Kz',
				'zeroDecimalSupport' => false
			],
			'ars' => [
				'code'               => 'ARS',
				'name'               => 'Argentine Peso',
				'symbol'             => '$',
				'zeroDecimalSupport' => false
			],
			'aud' => [
				'code'               => 'AUD',
				'name'               => 'Australian Dollar',
				'symbol'             => '$',
				'zeroDecimalSupport' => false
			],
			'awg' => [
				'code'               => 'AWG',
				'name'               => 'Aruban Florin',
				'symbol'             => 'ƒ',
				'zeroDecimalSupport' => false
			],
			'azn' => [
				'code'               => 'AZN',
				'name'               => 'Azerbaijani Manat',
				'symbol'             => 'm.',
				'zeroDecimalSupport' => false
			],
			'bam' => [
				'code'               => 'BAM',
				'name'               => 'Bosnia & Herzegovina Convertible Mark',
				'symbol'             => 'KM',
				'zeroDecimalSupport' => false
			],
			'bbd' => [
				'code'               => 'BBD',
				'name'               => 'Barbadian Dollar',
				'symbol'             => 'Bds$',
				'zeroDecimalSupport' => false
			],
			'bdt' => [
				'code'               => 'BDT',
				'name'               => 'Bangladeshi Taka',
				'symbol'             => '৳',
				'zeroDecimalSupport' => false
			],
			'bgn' => [
				'code'               => 'BGN',
				'name'               => 'Bulgarian Lev',
				'symbol'             => 'лв',
				'zeroDecimalSupport' => false
			],
			'bif' => [
				'code'               => 'BIF',
				'name'               => 'Burundian Franc',
				'symbol'             => 'FBu',
				'zeroDecimalSupport' => true
			],
			'bmd' => [
				'code'               => 'BMD',
				'name'               => 'Bermudian Dollar',
				'symbol'             => 'BD$',
				'zeroDecimalSupport' => false
			],
			'bnd' => [
				'code'               => 'BND',
				'name'               => 'Brunei Dollar',
				'symbol'             => 'B$',
				'zeroDecimalSupport' => false
			],
			'bob' => [
				'code'               => 'BOB',
				'name'               => 'Bolivian Boliviano',
				'symbol'             => 'Bs.',
				'zeroDecimalSupport' => false
			],
			'brl' => [
				'code'               => 'BRL',
				'name'               => 'Brazilian Real',
				'symbol'             => 'R$',
				'zeroDecimalSupport' => false
			],
			'bsd' => [
				'code'               => 'BSD',
				'name'               => 'Bahamian Dollar',
				'symbol'             => 'B$',
				'zeroDecimalSupport' => false
			],
			'bwp' => [
				'code'               => 'BWP',
				'name'               => 'Botswana Pula',
				'symbol'             => 'P',
				'zeroDecimalSupport' => false
			],
			'bzd' => [
				'code'               => 'BZD',
				'name'               => 'Belize Dollar',
				'symbol'             => 'BZ$',
				'zeroDecimalSupport' => false
			],
			'cad' => [
				'code'               => 'CAD',
				'name'               => 'Canadian Dollar',
				'symbol'             => '$',
				'zeroDecimalSupport' => false
			],
			'cdf' => [
				'code'               => 'CDF',
				'name'               => 'Congolese Franc',
				'symbol'             => 'CF',
				'zeroDecimalSupport' => false
			],
			'chf' => [
				'code'               => 'CHF',
				'name'               => 'Swiss Franc',
				'symbol'             => 'Fr',
				'zeroDecimalSupport' => false
			],
			'clp' => [
				'code'               => 'CLP',
				'name'               => 'Chilean Peso',
				'symbol'             => 'CLP$',
				'zeroDecimalSupport' => true
			],
			'cny' => [
				'code'               => 'CNY',
				'name'               => 'Chinese Renminbi Yuan',
				'symbol'             => '¥',
				'zeroDecimalSupport' => false
			],
			'cop' => [
				'code'               => 'COP',
				'name'               => 'Colombian Peso',
				'symbol'             => 'COL$',
				'zeroDecimalSupport' => false
			],
			'crc' => [
				'code'               => 'CRC',
				'name'               => 'Costa Rican Colón',
				'symbol'             => '₡',
				'zeroDecimalSupport' => false
			],
			'cve' => [
				'code'               => 'CVE',
				'name'               => 'Cape Verdean Escudo',
				'symbol'             => 'Esc',
				'zeroDecimalSupport' => false
			],
			'czk' => [
				'code'               => 'CZK',
				'name'               => 'Czech Koruna',
				'symbol'             => 'Kč',
				'zeroDecimalSupport' => false
			],
			'djf' => [
				'code'               => 'DJF',
				'name'               => 'Djiboutian Franc',
				'symbol'             => 'Fr',
				'zeroDecimalSupport' => true
			],
			'dkk' => [
				'code'               => 'DKK',
				'name'               => 'Danish Krone',
				'symbol'             => 'kr',
				'zeroDecimalSupport' => false
			],
			'dop' => [
				'code'               => 'DOP',
				'name'               => 'Dominican Peso',
				'symbol'             => 'RD$',
				'zeroDecimalSupport' => false
			],
			'dzd' => [
				'code'               => 'DZD',
				'name'               => 'Algerian Dinar',
				'symbol'             => 'DA',
				'zeroDecimalSupport' => false
			],
			'egp' => [
				'code'               => 'EGP',
				'name'               => 'Egyptian Pound',
				'symbol'             => 'L.E.',
				'zeroDecimalSupport' => false
			],
			'etb' => [
				'code'               => 'ETB',
				'name'               => 'Ethiopian Birr',
				'symbol'             => 'Br',
				'zeroDecimalSupport' => false
			],
			'eur' => [
				'code'               => 'EUR',
				'name'               => 'Euro',
				'symbol'             => '€',
				'zeroDecimalSupport' => false
			],
			'fjd' => [
				'code'               => 'FJD',
				'name'               => 'Fijian Dollar',
				'symbol'             => 'FJ$',
				'zeroDecimalSupport' => false
			],
			'fkp' => [
				'code'               => 'FKP',
				'name'               => 'Falkland Islands Pound',
				'symbol'             => 'FK£',
				'zeroDecimalSupport' => false
			],
			'gbp' => [
				'code'               => 'GBP',
				'name'               => 'British Pound',
				'symbol'             => '£',
				'zeroDecimalSupport' => false
			],
			'gel' => [
				'code'               => 'GEL',
				'name'               => 'Georgian Lari',
				'symbol'             => 'ლ',
				'zeroDecimalSupport' => false
			],
			'gip' => [
				'code'               => 'GIP',
				'name'               => 'Gibraltar Pound',
				'symbol'             => '£',
				'zeroDecimalSupport' => false
			],
			'gmd' => [
				'code'               => 'GMD',
				'name'               => 'Gambian Dalasi',
				'symbol'             => 'D',
				'zeroDecimalSupport' => false
			],
			'gnf' => [
				'code'               => 'GNF',
				'name'               => 'Guinean Franc',
				'symbol'             => 'FG',
				'zeroDecimalSupport' => true
			],
			'gtq' => [
				'code'               => 'GTQ',
				'name'               => 'Guatemalan Quetzal',
				'symbol'             => 'Q',
				'zeroDecimalSupport' => false
			],
			'gyd' => [
				'code'               => 'GYD',
				'name'               => 'Guyanese Dollar',
				'symbol'             => 'G$',
				'zeroDecimalSupport' => false
			],
			'hkd' => [
				'code'               => 'HKD',
				'name'               => 'Hong Kong Dollar',
				'symbol'             => 'HK$',
				'zeroDecimalSupport' => false
			],
			'hnl' => [
				'code'               => 'HNL',
				'name'               => 'Honduran Lempira',
				'symbol'             => 'L',
				'zeroDecimalSupport' => false
			],
			'hrk' => [
				'code'               => 'HRK',
				'name'               => 'Croatian Kuna',
				'symbol'             => 'kn',
				'zeroDecimalSupport' => false
			],
			'htg' => [
				'code'               => 'HTG',
				'name'               => 'Haitian Gourde',
				'symbol'             => 'G',
				'zeroDecimalSupport' => false
			],
			'huf' => [
				'code'               => 'HUF',
				'name'               => 'Hungarian Forint',
				'symbol'             => 'Ft',
				'zeroDecimalSupport' => false
			],
			'idr' => [
				'code'               => 'IDR',
				'name'               => 'Indonesian Rupiah',
				'symbol'             => 'Rp',
				'zeroDecimalSupport' => false
			],
			'ils' => [
				'code'               => 'ILS',
				'name'               => 'Israeli New Sheqel',
				'symbol'             => '₪',
				'zeroDecimalSupport' => false
			],
			'inr' => [
				'code'               => 'INR',
				'name'               => 'Indian Rupee',
				'symbol'             => '₹',
				'zeroDecimalSupport' => false
			],
			'isk' => [
				'code'               => 'ISK',
				'name'               => 'Icelandic Króna',
				'symbol'             => 'ikr',
				'zeroDecimalSupport' => false
			],
			'jmd' => [
				'code'               => 'JMD',
				'name'               => 'Jamaican Dollar',
				'symbol'             => 'J$',
				'zeroDecimalSupport' => false
			],
			'jpy' => [
				'code'               => 'JPY',
				'name'               => 'Japanese Yen',
				'symbol'             => '¥',
				'zeroDecimalSupport' => true
			],
			'kes' => [
				'code'               => 'KES',
				'name'               => 'Kenyan Shilling',
				'symbol'             => 'Ksh',
				'zeroDecimalSupport' => false
			],
			'kgs' => [
				'code'               => 'KGS',
				'name'               => 'Kyrgyzstani Som',
				'symbol'             => 'COM',
				'zeroDecimalSupport' => false
			],
			'khr' => [
				'code'               => 'KHR',
				'name'               => 'Cambodian Riel',
				'symbol'             => '៛',
				'zeroDecimalSupport' => false
			],
			'kmf' => [
				'code'               => 'KMF',
				'name'               => 'Comorian Franc',
				'symbol'             => 'CF',
				'zeroDecimalSupport' => true
			],
			'krw' => [
				'code'               => 'KRW',
				'name'               => 'South Korean Won',
				'symbol'             => '₩',
				'zeroDecimalSupport' => true
			],
			'kyd' => [
				'code'               => 'KYD',
				'name'               => 'Cayman Islands Dollar',
				'symbol'             => 'CI$',
				'zeroDecimalSupport' => false
			],
			'kzt' => [
				'code'               => 'KZT',
				'name'               => 'Kazakhstani Tenge',
				'symbol'             => '₸',
				'zeroDecimalSupport' => false
			],
			'lak' => [
				'code'               => 'LAK',
				'name'               => 'Lao Kip',
				'symbol'             => '₭',
				'zeroDecimalSupport' => false
			],
			'lbp' => [
				'code'               => 'LBP',
				'name'               => 'Lebanese Pound',
				'symbol'             => 'LL',
				'zeroDecimalSupport' => false
			],
			'lkr' => [
				'code'               => 'LKR',
				'name'               => 'Sri Lankan Rupee',
				'symbol'             => 'SLRs',
				'zeroDecimalSupport' => false
			],
			'lrd' => [
				'code'               => 'LRD',
				'name'               => 'Liberian Dollar',
				'symbol'             => 'L$',
				'zeroDecimalSupport' => false
			],
			'lsl' => [
				'code'               => 'LSL',
				'name'               => 'Lesotho Loti',
				'symbol'             => 'M',
				'zeroDecimalSupport' => false
			],
			'mad' => [
				'code'               => 'MAD',
				'name'               => 'Moroccan Dirham',
				'symbol'             => 'DH',
				'zeroDecimalSupport' => false
			],
			'mdl' => [
				'code'               => 'MDL',
				'name'               => 'Moldovan Leu',
				'symbol'             => 'MDL',
				'zeroDecimalSupport' => false
			],
			'mga' => [
				'code'               => 'MGA',
				'name'               => 'Malagasy Ariary',
				'symbol'             => 'Ar',
				'zeroDecimalSupport' => true
			],
			'mkd' => [
				'code'               => 'MKD',
				'name'               => 'Macedonian Denar',
				'symbol'             => 'ден',
				'zeroDecimalSupport' => false
			],
			'mnt' => [
				'code'               => 'MNT',
				'name'               => 'Mongolian Tögrög',
				'symbol'             => '₮',
				'zeroDecimalSupport' => false
			],
			'mop' => [
				'code'               => 'MOP',
				'name'               => 'Macanese Pataca',
				'symbol'             => 'MOP$',
				'zeroDecimalSupport' => false
			],
			'mro' => [
				'code'               => 'MRO',
				'name'               => 'Mauritanian Ouguiya',
				'symbol'             => 'UM',
				'zeroDecimalSupport' => false
			],
			'mur' => [
				'code'               => 'MUR',
				'name'               => 'Mauritian Rupee',
				'symbol'             => 'Rs',
				'zeroDecimalSupport' => false
			],
			'mvr' => [
				'code'               => 'MVR',
				'name'               => 'Maldivian Rufiyaa',
				'symbol'             => 'Rf.',
				'zeroDecimalSupport' => false
			],
			'mwk' => [
				'code'               => 'MWK',
				'name'               => 'Malawian Kwacha',
				'symbol'             => 'MK',
				'zeroDecimalSupport' => false
			],
			'mxn' => [
				'code'               => 'MXN',
				'name'               => 'Mexican Peso',
				'symbol'             => '$',
				'zeroDecimalSupport' => false
			],
			'myr' => [
				'code'               => 'MYR',
				'name'               => 'Malaysian Ringgit',
				'symbol'             => 'RM',
				'zeroDecimalSupport' => false
			],
			'mzn' => [
				'code'               => 'MZN',
				'name'               => 'Mozambican Metical',
				'symbol'             => 'MT',
				'zeroDecimalSupport' => false
			],
			'nad' => [
				'code'               => 'NAD',
				'name'               => 'Namibian Dollar',
				'symbol'             => 'N$',
				'zeroDecimalSupport' => false
			],
			'ngn' => [
				'code'               => 'NGN',
				'name'               => 'Nigerian Naira',
				'symbol'             => '₦',
				'zeroDecimalSupport' => false
			],
			'nio' => [
				'code'               => 'NIO',
				'name'               => 'Nicaraguan Córdoba',
				'symbol'             => 'C$',
				'zeroDecimalSupport' => false
			],
			'nok' => [
				'code'               => 'NOK',
				'name'               => 'Norwegian Krone',
				'symbol'             => 'kr',
				'zeroDecimalSupport' => false
			],
			'npr' => [
				'code'               => 'NPR',
				'name'               => 'Nepalese Rupee',
				'symbol'             => 'NRs',
				'zeroDecimalSupport' => false
			],
			'nzd' => [
				'code'               => 'NZD',
				'name'               => 'New Zealand Dollar',
				'symbol'             => 'NZ$',
				'zeroDecimalSupport' => false
			],
			'pab' => [
				'code'               => 'PAB',
				'name'               => 'Panamanian Balboa',
				'symbol'             => 'B/.',
				'zeroDecimalSupport' => false
			],
			'pen' => [
				'code'               => 'PEN',
				'name'               => 'Peruvian Nuevo Sol',
				'symbol'             => 'S/.',
				'zeroDecimalSupport' => false
			],
			'pgk' => [
				'code'               => 'PGK',
				'name'               => 'Papua New Guinean Kina',
				'symbol'             => 'K',
				'zeroDecimalSupport' => false
			],
			'php' => [
				'code'               => 'PHP',
				'name'               => 'Philippine Peso',
				'symbol'             => '₱',
				'zeroDecimalSupport' => false
			],
			'pkr' => [
				'code'               => 'PKR',
				'name'               => 'Pakistani Rupee',
				'symbol'             => 'PKR',
				'zeroDecimalSupport' => false
			],
			'pln' => [
				'code'               => 'PLN',
				'name'               => 'Polish Złoty',
				'symbol'             => 'zł',
				'zeroDecimalSupport' => false
			],
			'pyg' => [
				'code'               => 'PYG',
				'name'               => 'Paraguayan Guaraní',
				'symbol'             => '₲',
				'zeroDecimalSupport' => true
			],
			'qar' => [
				'code'               => 'QAR',
				'name'               => 'Qatari Riyal',
				'symbol'             => 'QR',
				'zeroDecimalSupport' => false
			],
			'ron' => [
				'code'               => 'RON',
				'name'               => 'Romanian Leu',
				'symbol'             => 'RON',
				'zeroDecimalSupport' => false
			],
			'rsd' => [
				'code'               => 'RSD',
				'name'               => 'Serbian Dinar',
				'symbol'             => 'дин',
				'zeroDecimalSupport' => false
			],
			'rub' => [
				'code'               => 'RUB',
				'name'               => 'Russian Ruble',
				'symbol'             => 'руб',
				'zeroDecimalSupport' => false
			],
			'rwf' => [
				'code'               => 'RWF',
				'name'               => 'Rwandan Franc',
				'symbol'             => 'FRw',
				'zeroDecimalSupport' => true
			],
			'sar' => [
				'code'               => 'SAR',
				'name'               => 'Saudi Riyal',
				'symbol'             => 'SR',
				'zeroDecimalSupport' => false
			],
			'sbd' => [
				'code'               => 'SBD',
				'name'               => 'Solomon Islands Dollar',
				'symbol'             => 'SI$',
				'zeroDecimalSupport' => false
			],
			'scr' => [
				'code'               => 'SCR',
				'name'               => 'Seychellois Rupee',
				'symbol'             => 'SRe',
				'zeroDecimalSupport' => false
			],
			'sek' => [
				'code'               => 'SEK',
				'name'               => 'Swedish Krona',
				'symbol'             => 'kr',
				'zeroDecimalSupport' => false
			],
			'sgd' => [
				'code'               => 'SGD',
				'name'               => 'Singapore Dollar',
				'symbol'             => 'S$',
				'zeroDecimalSupport' => false
			],
			'shp' => [
				'code'               => 'SHP',
				'name'               => 'Saint Helenian Pound',
				'symbol'             => '£',
				'zeroDecimalSupport' => false
			],
			'sll' => [
				'code'               => 'SLL',
				'name'               => 'Sierra Leonean Leone',
				'symbol'             => 'Le',
				'zeroDecimalSupport' => false
			],
			'sos' => [
				'code'               => 'SOS',
				'name'               => 'Somali Shilling',
				'symbol'             => 'Sh.So.',
				'zeroDecimalSupport' => false
			],
			'std' => [
				'code'               => 'STD',
				'name'               => 'São Tomé and Príncipe Dobra',
				'symbol'             => 'Db',
				'zeroDecimalSupport' => false
			],
			'srd' => [
				'code'               => 'SRD',
				'name'               => 'Surinamese Dollar',
				'symbol'             => 'SRD',
				'zeroDecimalSupport' => false
			],
			'svc' => [
				'code'               => 'SVC',
				'name'               => 'Salvadoran Colón',
				'symbol'             => '₡',
				'zeroDecimalSupport' => false
			],
			'szl' => [
				'code'               => 'SZL',
				'name'               => 'Swazi Lilangeni',
				'symbol'             => 'E',
				'zeroDecimalSupport' => false
			],
			'thb' => [
				'code'               => 'THB',
				'name'               => 'Thai Baht',
				'symbol'             => '฿',
				'zeroDecimalSupport' => false
			],
			'tjs' => [
				'code'               => 'TJS',
				'name'               => 'Tajikistani Somoni',
				'symbol'             => 'TJS',
				'zeroDecimalSupport' => false
			],
			'top' => [
				'code'               => 'TOP',
				'name'               => 'Tongan Paʻanga',
				'symbol'             => '$',
				'zeroDecimalSupport' => false
			],
			'try' => [
				'code'               => 'TRY',
				'name'               => 'Turkish Lira',
				'symbol'             => '₺',
				'zeroDecimalSupport' => false
			],
			'ttd' => [
				'code'               => 'TTD',
				'name'               => 'Trinidad and Tobago Dollar',
				'symbol'             => 'TT$',
				'zeroDecimalSupport' => false
			],
			'twd' => [
				'code'               => 'TWD',
				'name'               => 'New Taiwan Dollar',
				'symbol'             => 'NT$',
				'zeroDecimalSupport' => false
			],
			'tzs' => [
				'code'               => 'TZS',
				'name'               => 'Tanzanian Shilling',
				'symbol'             => 'TSh',
				'zeroDecimalSupport' => false
			],
			'uah' => [
				'code'               => 'UAH',
				'name'               => 'Ukrainian Hryvnia',
				'symbol'             => '₴',
				'zeroDecimalSupport' => false
			],
			'ugx' => [
				'code'               => 'UGX',
				'name'               => 'Ugandan Shilling',
				'symbol'             => 'USh',
				'zeroDecimalSupport' => false
			],
			'usd' => [
				'code'               => 'USD',
				'name'               => 'United States Dollar',
				'symbol'             => '$',
				'zeroDecimalSupport' => false
			],
			'uyu' => [
				'code'               => 'UYU',
				'name'               => 'Uruguayan Peso',
				'symbol'             => '$U',
				'zeroDecimalSupport' => false
			],
			'uzs' => [
				'code'               => 'UZS',
				'name'               => 'Uzbekistani Som',
				'symbol'             => 'UZS',
				'zeroDecimalSupport' => false
			],
			'vnd' => [
				'code'               => 'VND',
				'name'               => 'Vietnamese Đồng',
				'symbol'             => '₫',
				'zeroDecimalSupport' => true
			],
			'vuv' => [
				'code'               => 'VUV',
				'name'               => 'Vanuatu Vatu',
				'symbol'             => 'VT',
				'zeroDecimalSupport' => true
			],
			'wst' => [
				'code'               => 'WST',
				'name'               => 'Samoan Tala',
				'symbol'             => 'WS$',
				'zeroDecimalSupport' => false
			],
			'xaf' => [
				'code'               => 'XAF',
				'name'               => 'Central African Cfa Franc',
				'symbol'             => 'FCFA',
				'zeroDecimalSupport' => true
			],
			'xcd' => [
				'code'               => 'XCD',
				'name'               => 'East Caribbean Dollar',
				'symbol'             => 'EC$',
				'zeroDecimalSupport' => false
			],
			'xof' => [
				'code'               => 'XOF',
				'name'               => 'West African Cfa Franc',
				'symbol'             => 'CFA',
				'zeroDecimalSupport' => true
			],
			'xpf' => [
				'code'               => 'XPF',
				'name'               => 'Cfp Franc',
				'symbol'             => 'F',
				'zeroDecimalSupport' => true
			],
			'yer' => [
				'code'               => 'YER',
				'name'               => 'Yemeni Rial',
				'symbol'             => '﷼',
				'zeroDecimalSupport' => false
			],
			'zar' => [
				'code'               => 'ZAR',
				'name'               => 'South African Rand',
				'symbol'             => 'R',
				'zeroDecimalSupport' => false
			],
			'zmw' => [
				'code'               => 'ZMW',
				'name'               => 'Zambian Kwacha',
				'symbol'             => 'ZK',
				'zeroDecimalSupport' => false
			]
		];
	}

    /**
     * @deprecated
     *
     * @param $currency
     * @param $amount
     * @param bool $decimalsForInteger
     *
     * @return null|string
     */
    public static function format_amount_with_currency( $currency, $amount, $decimalsForInteger = true ) {
        return self::formatAmountInternal( $currency, $amount, $decimalsForInteger, true );
    }

    /**
	 * @deprecated
	 *
	 * @param $currency
	 * @param $amount
	 * @param $decimalsForInteger
	 * @param $showCurrencySymbol
	 *
	 * @return null|string
	 */
	protected static function formatAmountInternal($currency, $amount, $decimalsForInteger, $showCurrencySymbol ) {
		$currencyArray = Currencies::getCurrencyFor( $currency );
		if ( is_array( $currencyArray ) ) {
			if ( $currencyArray['zeroDecimalSupport'] == true ) {
				$theAmount = is_numeric( $amount ) ? $amount : 0;
				$pattern   = '%0d';
			} else {
				$theAmount = is_numeric( $amount ) ? ( $amount / 100.0 ) : 0;
				// tnagy check if theAmount is whole number
				if ( false === $decimalsForInteger && intval( $theAmount ) == ( $theAmount + 0 ) ) {
					$pattern = '%0d';
				} else {
					$pattern = '%0.2f';
				}
			}

			if ( $showCurrencySymbol ) {
				$pattern = '%s' . $pattern;
				$result  = sprintf( $pattern, $currencyArray['symbol'], $theAmount );
			} else {
				$result = sprintf( $pattern, $theAmount );
			}

			return $result;
		}

		return null;
	}

	/**
	 * @param $currency
	 * @param $amount
	 * @param bool $decimalsForInteger
	 *
	 * @return null|string
	 */
	public static function formatAmount($currency, $amount, $decimalsForInteger = true ) {
	    //todo: rename this function to make sure it's understood that it must format currencies always with dot as decimal separator
		return self::formatAmountInternal( $currency, $amount, $decimalsForInteger, false );
	}

	/**
     * @param StaticContext $context
	 * @param $currency
	 * @param $amount
	 * @param bool $decimalsForInteger
	 * @param bool $showCurrency
	 *
	 * @return string|void
	 */
	public static function formatAndEscape( $context, $currency, $amount, $decimalsForInteger = true, $showCurrency = true ) {
		return self::createCurrencyFormatByContext( $context, $currency )->formatAndEscape( $amount, $decimalsForInteger, $showCurrency );
	}

	/**
	 * Returns the name of the selected decimal separator symbol according to WPFS options or the default value
	 * if not set.
	 * @see CurrencyFormat::DECIMAL_SEPARATOR_COMMA
	 * @see CurrencyFormat::DECIMAL_SEPARATOR_DOT
     *
     * @param StaticContext $context
     *
	 * @return string
	 */
	public static function getGlobalDecimalSeparatorSymbolOption( $context ) {
        $decimalSeparatorSymbol = $context->getOptions()->get( Options::OPTION_DECIMAL_SEPARATOR_SYMBOL );
        if ( Main::DECIMAL_SEPARATOR_SYMBOL_COMMA == $decimalSeparatorSymbol ) {
            return Main::DECIMAL_SEPARATOR_SYMBOL_COMMA;
        }

		return Main::DECIMAL_SEPARATOR_SYMBOL_DOT;
	}

	/**
	 * Returns the WPFS option whether to show currency symbol instead of code or the default value if not set.
     *
     * @param StaticContext $context
     *
	 * @return bool
	 */
	public static function getGlobalShowCurrencySymbolInsteadOfCode( $context ) {
        $showCurrencySymbolInsteadOfCode = $context->getOptions()->get( Options::OPTION_SHOW_CURRENCY_SYMBOL_INSTEAD_OF_CODE );
        if ( '1' == $showCurrencySymbolInsteadOfCode ) {
            return true;
        }

		return false;
	}

	/**
	 * Returns the WPFS option whether to show currency sign first or the default value if not set.
     *
     * @param StaticContext $context
     *
	 * @return bool
	 */
	public static function getGlobalShowCurrencySignAtFirstPosition( $context ) {
        $showCurrencySignFirst = $context->getOptions()->get( Options::OPTION_SHOW_CURRENCY_SIGN_AT_FIRST_POSITION );
        if ( '1' == $showCurrencySignFirst ) {
            return true;
        }

		return false;
	}

	/**
	 * Returns the WPFS option whether to show currency symbol instead of code or the default value if not set.
     *
     * @param StaticContext $context
     *
	 * @return bool
	 */
	public static function getGlobalPutWhitespaceBetweenCurrencyAndAmount( $context ) {
        $putWhitespaceBetweenCurrencyAndAmount = $context->getOptions()->get( Options::OPTION_PUT_WHITESPACE_BETWEEN_CURRENCY_AND_AMOUNT );
        if ( '1' == $putWhitespaceBetweenCurrencyAndAmount ) {
            return true;
        }

		return false;
	}

	/**
	 * @param $currency
	 *
	 * @param $decimalSeparatorSymbol
	 * @param $showCurrencySymbolInsteadOfCode
	 * @param $showCurrencySignAtFirstPosition
	 * @param $putWhitespaceBetweenCurrencyAndAmount
	 *
	 * @return CurrencyFormat
	 * @throws \Exception
	 */
	protected static function createCurrencyFormat(
		$currency,
		$decimalSeparatorSymbol,
		$showCurrencySymbolInsteadOfCode,
		$showCurrencySignAtFirstPosition,
		$putWhitespaceBetweenCurrencyAndAmount
	) {
		$currencyArray = Currencies::getCurrencyFor( $currency );
		if ( is_null( $currencyArray ) ) {
			throw new \Exception( 'Unknown currency: ' . $currency );
		}
		if (
			Main::DECIMAL_SEPARATOR_SYMBOL_DOT !== $decimalSeparatorSymbol
			&& Main::DECIMAL_SEPARATOR_SYMBOL_COMMA !== $decimalSeparatorSymbol
		) {
			throw new \Exception( 'decimalSeparatorSymbol has an invalid value: ' . $decimalSeparatorSymbol );
		}
		$currencyObject = Currency::fromArray( $currencyArray );
		if ( Main::DECIMAL_SEPARATOR_SYMBOL_COMMA === $decimalSeparatorSymbol ) {
			$decimalSeparator = CurrencyFormat::DECIMAL_SEPARATOR_COMMA;
		} else {
			$decimalSeparator = CurrencyFormat::DECIMAL_SEPARATOR_DOT;
		}
		$currencyFormat = new CurrencyFormat(
			$currencyObject,
			$decimalSeparator,
			$showCurrencySymbolInsteadOfCode,
			$showCurrencySignAtFirstPosition,
			$putWhitespaceBetweenCurrencyAndAmount
		);

		return $currencyFormat;
	}

	/**
	 * @param $form
	 * @param $currency
	 * @param $amount
	 * @param bool $decimalsForInteger
	 * @param bool $showCurrency
	 *
	 * @return string|void
	 */
	public static function formatAndEscapeByForm( $form, $currency, $amount, $decimalsForInteger = true, $showCurrency = true ) {
		$currencyFormat = self::createCurrencyFormatByForm(
			$form,
			$currency
		);

		return $currencyFormat->formatAndEscape( $amount, $decimalsForInteger, $showCurrency );
	}

	/**
	 * A format with the WordPress dashboard's currency settings.
	 *
	 * @param StaticContext $context
	 * @param string $currency
	 *
	 * @return CurrencyFormat
	 */
	private static function createCurrencyFormatByContext( $context, $currency ) {
		return self::createCurrencyFormat(
			$currency,
			self::getGlobalDecimalSeparatorSymbolOption( $context ),
			self::getGlobalShowCurrencySymbolInsteadOfCode( $context ),
			self::getGlobalShowCurrencySignAtFirstPosition( $context ),
			self::getGlobalPutWhitespaceBetweenCurrencyAndAmount( $context )
		);
	}

	/**
	 * @param $form
	 * @param $currency
	 *
	 * @return CurrencyFormat
	 * @throws \Exception
	 */
	protected static function createCurrencyFormatByForm( $form, $currency ) {
		if ( is_null( $form ) ) {
			throw new \Exception( 'form is null' );
		}
		$currencyFormat = self::createCurrencyFormat(
			$currency,
			self::getLocaleSetting( $form, 'decimalSeparator' ),
			1 == self::getLocaleSetting( $form, 'showCurrencySymbolInsteadOfCode' ) ? true : false,
			1 == self::getLocaleSetting( $form, 'showCurrencySignAtFirstPosition' ) ? true : false,
			1 == self::getLocaleSetting( $form, 'putWhitespaceBetweenCurrencyAndAmount' ) ? true : false
		);

		return $currencyFormat;
	}

    /**
     * @param StaticContext $context
     * @param $currency
     * @param $amount
     * @param bool $decimalsForInteger
     * @param bool $showCurrency
     *
     * @return string|void
     */
    public static function formatAndEscapeByAdmin( $context, $currency, $amount, $decimalsForInteger = true, $showCurrency = true ) {
        $currencyFormat = self::createCurrencyFormatByAdmin(
            $context,
            $currency
        );

        return $currencyFormat->formatAndEscape( $amount, $decimalsForInteger, $showCurrency );
    }

    /**
     * @param StaticContext $context
     * @param $currency
     *
     * @return CurrencyFormat
     * @throws \Exception
     */
    protected static function createCurrencyFormatByAdmin( $context, $currency ) {
        $options = $context->getOptions();

        $currencyFormat = self::createCurrencyFormat(
            $currency,
            $options->get( Options::OPTION_DECIMAL_SEPARATOR_SYMBOL ),
            1 == $options->get( Options::OPTION_SHOW_CURRENCY_SYMBOL_INSTEAD_OF_CODE ),
            1 == $options->get( Options::OPTION_SHOW_CURRENCY_SIGN_AT_FIRST_POSITION ),
            1 == $options->get( Options::OPTION_PUT_WHITESPACE_BETWEEN_CURRENCY_AND_AMOUNT )
        );

        return $currencyFormat;
    }

    /**
     * @param $currency
     * @param $amount
     * @param bool $decimalsForInteger
     * @param bool $showCurrency
     *
     * @return string|void
     */
    public static function formatAndEscapeByMyAccount( $currency, $amount, $decimalsForInteger = true, $showCurrency = true ) {
        $currencyFormat = self::createCurrencyFormatByMyAccount(
            $currency
        );

        return $currencyFormat->formatAndEscape( $amount, $decimalsForInteger, $showCurrency );
    }

    /**
     * @param $currency
     *
     * @return CurrencyFormat
     * @throws \Exception
     */
    protected static function createCurrencyFormatByMyAccount( $currency ) {
        $currencyFormat = self::createCurrencyFormat(
            $currency,
            Main::DECIMAL_SEPARATOR_SYMBOL_DOT,
            true,
            true,
            false
        );

        return $currencyFormat;
    }

    /**
     * @param StaticContext $context
	 * @param $currency
	 * @param $amount
	 * @param bool $decimalsForInteger
	 * @param bool $showCurrency
	 *
	 * @return null|string
	 * @throws \Exception
	 */
	public static function format( $context, $currency, $amount, $decimalsForInteger = true, $showCurrency = true ) {
		return self::createCurrencyFormatByContext( $context, $currency )->format( $amount, $decimalsForInteger, $showCurrency );
	}

	public static function formatByForm( $form, $currency, $amount, $decimalsForInteger = true, $showCurrency = true ) {
		$currencyFormat = self::createCurrencyFormatByForm(
			$form,
			$currency
		);

		return $currencyFormat->format( $amount, $decimalsForInteger, $showCurrency );
	}

	/**
	 * @deprecated 8.6.0 Strips unexpected characters instead of rejecting them, use parseToSmallestCurrencyUnitByForm().
	 *
	 * @param \StdClass $form
	 * @param string $currency
	 * @param mixed $amount
	 *
	 * @return mixed|null
	 * @throws \Exception
	 */
	public static function parseByForm( $form, $currency, $amount ) {
		_deprecated_function( __METHOD__, '8.6.0', __CLASS__ . '::parseToSmallestCurrencyUnitByForm()' );

		$currencyFormat = self::createCurrencyFormatByForm( $form, $currency );

		return $currencyFormat->parse( $amount );
	}

	/**
	 * @param \StdClass $form
	 * @param string $currency
	 * @param mixed $amount Amount typed by the customer.
	 *
	 * @return int|null The amount in the smallest currency unit, or null when it is malformed.
	 * @throws \Exception
	 */
	public static function parseToSmallestCurrencyUnitByForm( $form, $currency, $amount ) {
		return self::createCurrencyFormatByForm( $form, $currency )->parseToSmallestCurrencyUnit( $amount );
	}

	/**
	 * @param \StdClass $form
	 * @param string $currency
	 *
	 * @return string An example amount in the format the form accepts, e.g. "12,34".
	 * @throws \Exception
	 */
	public static function formatExampleAmountByForm( $form, $currency ) {
		return self::createCurrencyFormatByForm( $form, $currency )->formatExampleAmount();
	}

	/**
	 * @param object $form
	 * @param float|int $percentage
	 *
	 * @return string The percentage with up to two decimals in the form's decimal separator, such as 8.25%.
	 */
	public static function formatAndEscapePercentageByForm( $form, $percentage ) {
		$decimalSeparator = Main::DECIMAL_SEPARATOR_SYMBOL_COMMA === self::getLocaleSetting( $form, 'decimalSeparator' ) ? ',' : '.';
		$number = rtrim( rtrim( number_format( (float) $percentage, 2, $decimalSeparator, '' ), '0' ), $decimalSeparator );

		return esc_attr( $number . '%' );
	}

	/**
	 * Get Locale Setting
	 * 
	 * @param $form
	 * @param $setting
	 */
	public static function getLocaleSetting( $form, $setting ) {
		if ( ! isset( $form->inheritLocale ) || (int) $form->inheritLocale !== 1 ) {
			return $form->$setting;
		}

		$options = new Options();
		
		switch ( $setting ) {
			case 'decimalSeparator':
				return $options->get( Options::OPTION_DECIMAL_SEPARATOR_SYMBOL );
			case 'showCurrencySymbolInsteadOfCode':
				return $options->get( Options::OPTION_SHOW_CURRENCY_SYMBOL_INSTEAD_OF_CODE );
			case 'showCurrencySignAtFirstPosition':
				return $options->get( Options::OPTION_SHOW_CURRENCY_SIGN_AT_FIRST_POSITION );
			case 'putWhitespaceBetweenCurrencyAndAmount':
				return $options->get( Options::OPTION_PUT_WHITESPACE_BETWEEN_CURRENCY_AND_AMOUNT );
			default:
				return $form->$setting;
		}
	}

	/**
	 * Currency names translated for display, keyed by lowercase ISO 4217 code.
	 *
	 * getAvailableCurrencies() stays untranslated: it is also used before WordPress loads translations, when patches run on upgrade.
	 *
	 * @return array<string, string>
	 */
	public static function getCurrencyLabels(): array {
		return [
			'aed' => __( 'United Arab Emirates Dirham', 'wp-full-stripe-free' ),
			'afn' => __( 'Afghan Afghani', 'wp-full-stripe-free' ),
			'all' => __( 'Albanian Lek', 'wp-full-stripe-free' ),
			'amd' => __( 'Armenian Dram', 'wp-full-stripe-free' ),
			'ang' => __( 'Netherlands Antillean Gulden', 'wp-full-stripe-free' ),
			'aoa' => __( 'Angolan Kwanza', 'wp-full-stripe-free' ),
			'ars' => __( 'Argentine Peso', 'wp-full-stripe-free' ),
			'aud' => __( 'Australian Dollar', 'wp-full-stripe-free' ),
			'awg' => __( 'Aruban Florin', 'wp-full-stripe-free' ),
			'azn' => __( 'Azerbaijani Manat', 'wp-full-stripe-free' ),
			'bam' => __( 'Bosnia & Herzegovina Convertible Mark', 'wp-full-stripe-free' ),
			'bbd' => __( 'Barbadian Dollar', 'wp-full-stripe-free' ),
			'bdt' => __( 'Bangladeshi Taka', 'wp-full-stripe-free' ),
			'bgn' => __( 'Bulgarian Lev', 'wp-full-stripe-free' ),
			'bif' => __( 'Burundian Franc', 'wp-full-stripe-free' ),
			'bmd' => __( 'Bermudian Dollar', 'wp-full-stripe-free' ),
			'bnd' => __( 'Brunei Dollar', 'wp-full-stripe-free' ),
			'bob' => __( 'Bolivian Boliviano', 'wp-full-stripe-free' ),
			'brl' => __( 'Brazilian Real', 'wp-full-stripe-free' ),
			'bsd' => __( 'Bahamian Dollar', 'wp-full-stripe-free' ),
			'bwp' => __( 'Botswana Pula', 'wp-full-stripe-free' ),
			'bzd' => __( 'Belize Dollar', 'wp-full-stripe-free' ),
			'cad' => __( 'Canadian Dollar', 'wp-full-stripe-free' ),
			'cdf' => __( 'Congolese Franc', 'wp-full-stripe-free' ),
			'chf' => __( 'Swiss Franc', 'wp-full-stripe-free' ),
			'clp' => __( 'Chilean Peso', 'wp-full-stripe-free' ),
			'cny' => __( 'Chinese Renminbi Yuan', 'wp-full-stripe-free' ),
			'cop' => __( 'Colombian Peso', 'wp-full-stripe-free' ),
			'crc' => __( 'Costa Rican Colón', 'wp-full-stripe-free' ),
			'cve' => __( 'Cape Verdean Escudo', 'wp-full-stripe-free' ),
			'czk' => __( 'Czech Koruna', 'wp-full-stripe-free' ),
			'djf' => __( 'Djiboutian Franc', 'wp-full-stripe-free' ),
			'dkk' => __( 'Danish Krone', 'wp-full-stripe-free' ),
			'dop' => __( 'Dominican Peso', 'wp-full-stripe-free' ),
			'dzd' => __( 'Algerian Dinar', 'wp-full-stripe-free' ),
			'egp' => __( 'Egyptian Pound', 'wp-full-stripe-free' ),
			'etb' => __( 'Ethiopian Birr', 'wp-full-stripe-free' ),
			'eur' => __( 'Euro', 'wp-full-stripe-free' ),
			'fjd' => __( 'Fijian Dollar', 'wp-full-stripe-free' ),
			'fkp' => __( 'Falkland Islands Pound', 'wp-full-stripe-free' ),
			'gbp' => __( 'British Pound', 'wp-full-stripe-free' ),
			'gel' => __( 'Georgian Lari', 'wp-full-stripe-free' ),
			'gip' => __( 'Gibraltar Pound', 'wp-full-stripe-free' ),
			'gmd' => __( 'Gambian Dalasi', 'wp-full-stripe-free' ),
			'gnf' => __( 'Guinean Franc', 'wp-full-stripe-free' ),
			'gtq' => __( 'Guatemalan Quetzal', 'wp-full-stripe-free' ),
			'gyd' => __( 'Guyanese Dollar', 'wp-full-stripe-free' ),
			'hkd' => __( 'Hong Kong Dollar', 'wp-full-stripe-free' ),
			'hnl' => __( 'Honduran Lempira', 'wp-full-stripe-free' ),
			'hrk' => __( 'Croatian Kuna', 'wp-full-stripe-free' ),
			'htg' => __( 'Haitian Gourde', 'wp-full-stripe-free' ),
			'huf' => __( 'Hungarian Forint', 'wp-full-stripe-free' ),
			'idr' => __( 'Indonesian Rupiah', 'wp-full-stripe-free' ),
			'ils' => __( 'Israeli New Sheqel', 'wp-full-stripe-free' ),
			'inr' => __( 'Indian Rupee', 'wp-full-stripe-free' ),
			'isk' => __( 'Icelandic Króna', 'wp-full-stripe-free' ),
			'jmd' => __( 'Jamaican Dollar', 'wp-full-stripe-free' ),
			'jpy' => __( 'Japanese Yen', 'wp-full-stripe-free' ),
			'kes' => __( 'Kenyan Shilling', 'wp-full-stripe-free' ),
			'kgs' => __( 'Kyrgyzstani Som', 'wp-full-stripe-free' ),
			'khr' => __( 'Cambodian Riel', 'wp-full-stripe-free' ),
			'kmf' => __( 'Comorian Franc', 'wp-full-stripe-free' ),
			'krw' => __( 'South Korean Won', 'wp-full-stripe-free' ),
			'kyd' => __( 'Cayman Islands Dollar', 'wp-full-stripe-free' ),
			'kzt' => __( 'Kazakhstani Tenge', 'wp-full-stripe-free' ),
			'lak' => __( 'Lao Kip', 'wp-full-stripe-free' ),
			'lbp' => __( 'Lebanese Pound', 'wp-full-stripe-free' ),
			'lkr' => __( 'Sri Lankan Rupee', 'wp-full-stripe-free' ),
			'lrd' => __( 'Liberian Dollar', 'wp-full-stripe-free' ),
			'lsl' => __( 'Lesotho Loti', 'wp-full-stripe-free' ),
			'mad' => __( 'Moroccan Dirham', 'wp-full-stripe-free' ),
			'mdl' => __( 'Moldovan Leu', 'wp-full-stripe-free' ),
			'mga' => __( 'Malagasy Ariary', 'wp-full-stripe-free' ),
			'mkd' => __( 'Macedonian Denar', 'wp-full-stripe-free' ),
			'mnt' => __( 'Mongolian Tögrög', 'wp-full-stripe-free' ),
			'mop' => __( 'Macanese Pataca', 'wp-full-stripe-free' ),
			'mro' => __( 'Mauritanian Ouguiya', 'wp-full-stripe-free' ),
			'mur' => __( 'Mauritian Rupee', 'wp-full-stripe-free' ),
			'mvr' => __( 'Maldivian Rufiyaa', 'wp-full-stripe-free' ),
			'mwk' => __( 'Malawian Kwacha', 'wp-full-stripe-free' ),
			'mxn' => __( 'Mexican Peso', 'wp-full-stripe-free' ),
			'myr' => __( 'Malaysian Ringgit', 'wp-full-stripe-free' ),
			'mzn' => __( 'Mozambican Metical', 'wp-full-stripe-free' ),
			'nad' => __( 'Namibian Dollar', 'wp-full-stripe-free' ),
			'ngn' => __( 'Nigerian Naira', 'wp-full-stripe-free' ),
			'nio' => __( 'Nicaraguan Córdoba', 'wp-full-stripe-free' ),
			'nok' => __( 'Norwegian Krone', 'wp-full-stripe-free' ),
			'npr' => __( 'Nepalese Rupee', 'wp-full-stripe-free' ),
			'nzd' => __( 'New Zealand Dollar', 'wp-full-stripe-free' ),
			'pab' => __( 'Panamanian Balboa', 'wp-full-stripe-free' ),
			'pen' => __( 'Peruvian Nuevo Sol', 'wp-full-stripe-free' ),
			'pgk' => __( 'Papua New Guinean Kina', 'wp-full-stripe-free' ),
			'php' => __( 'Philippine Peso', 'wp-full-stripe-free' ),
			'pkr' => __( 'Pakistani Rupee', 'wp-full-stripe-free' ),
			'pln' => __( 'Polish Złoty', 'wp-full-stripe-free' ),
			'pyg' => __( 'Paraguayan Guaraní', 'wp-full-stripe-free' ),
			'qar' => __( 'Qatari Riyal', 'wp-full-stripe-free' ),
			'ron' => __( 'Romanian Leu', 'wp-full-stripe-free' ),
			'rsd' => __( 'Serbian Dinar', 'wp-full-stripe-free' ),
			'rub' => __( 'Russian Ruble', 'wp-full-stripe-free' ),
			'rwf' => __( 'Rwandan Franc', 'wp-full-stripe-free' ),
			'sar' => __( 'Saudi Riyal', 'wp-full-stripe-free' ),
			'sbd' => __( 'Solomon Islands Dollar', 'wp-full-stripe-free' ),
			'scr' => __( 'Seychellois Rupee', 'wp-full-stripe-free' ),
			'sek' => __( 'Swedish Krona', 'wp-full-stripe-free' ),
			'sgd' => __( 'Singapore Dollar', 'wp-full-stripe-free' ),
			'shp' => __( 'Saint Helenian Pound', 'wp-full-stripe-free' ),
			'sll' => __( 'Sierra Leonean Leone', 'wp-full-stripe-free' ),
			'sos' => __( 'Somali Shilling', 'wp-full-stripe-free' ),
			'std' => __( 'São Tomé and Príncipe Dobra', 'wp-full-stripe-free' ),
			'srd' => __( 'Surinamese Dollar', 'wp-full-stripe-free' ),
			'svc' => __( 'Salvadoran Colón', 'wp-full-stripe-free' ),
			'szl' => __( 'Swazi Lilangeni', 'wp-full-stripe-free' ),
			'thb' => __( 'Thai Baht', 'wp-full-stripe-free' ),
			'tjs' => __( 'Tajikistani Somoni', 'wp-full-stripe-free' ),
			'top' => __( 'Tongan Paʻanga', 'wp-full-stripe-free' ),
			'try' => __( 'Turkish Lira', 'wp-full-stripe-free' ),
			'ttd' => __( 'Trinidad and Tobago Dollar', 'wp-full-stripe-free' ),
			'twd' => __( 'New Taiwan Dollar', 'wp-full-stripe-free' ),
			'tzs' => __( 'Tanzanian Shilling', 'wp-full-stripe-free' ),
			'uah' => __( 'Ukrainian Hryvnia', 'wp-full-stripe-free' ),
			'ugx' => __( 'Ugandan Shilling', 'wp-full-stripe-free' ),
			'usd' => __( 'United States Dollar', 'wp-full-stripe-free' ),
			'uyu' => __( 'Uruguayan Peso', 'wp-full-stripe-free' ),
			'uzs' => __( 'Uzbekistani Som', 'wp-full-stripe-free' ),
			'vnd' => __( 'Vietnamese Đồng', 'wp-full-stripe-free' ),
			'vuv' => __( 'Vanuatu Vatu', 'wp-full-stripe-free' ),
			'wst' => __( 'Samoan Tala', 'wp-full-stripe-free' ),
			'xaf' => __( 'Central African Cfa Franc', 'wp-full-stripe-free' ),
			'xcd' => __( 'East Caribbean Dollar', 'wp-full-stripe-free' ),
			'xof' => __( 'West African Cfa Franc', 'wp-full-stripe-free' ),
			'xpf' => __( 'Cfp Franc', 'wp-full-stripe-free' ),
			'yer' => __( 'Yemeni Rial', 'wp-full-stripe-free' ),
			'zar' => __( 'South African Rand', 'wp-full-stripe-free' ),
			'zmw' => __( 'Zambian Kwacha', 'wp-full-stripe-free' ),
		];
	}
}
