<?php

namespace ThemeIsle\WPFullPay\Pricing;

class CustomerTaxId {

	/**
	 * @return array
	 */
	static function getTaxIdTypes() {
		return [ 
			[ 
				'id' => 'au_abn',
				'description' => __( 'Australian Business Number (AU ABN)', 'wp-full-stripe-free' ),
				'countryCode' => 'AU',
				'example' => '12345678912'
			],
			[ 
				'id' => 'au_arn',
				'description' => __( 'Australian Taxation Office Reference Number', 'wp-full-stripe-free' ),
				'countryCode' => 'AU',
				'example' => '123456789123'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'AT',
				'example' => 'ATU12345678'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'BE',
				'example' => 'BE0123456789'
			],
			[ 
				'id' => 'br_cnpj',
				'description' => __( 'Brazilian CNPJ number', 'wp-full-stripe-free' ),
				'countryCode' => 'BR',
				'example' => '01.234.456/5432-10'
			],
			[ 
				'id' => 'br_cpf',
				'description' => __( 'Brazilian CPF number', 'wp-full-stripe-free' ),
				'countryCode' => 'BR',
				'example' => '123.456.789-87'
			],
			[ 
				'id' => 'bg_uic',
				'description' => __( 'Bulgaria Unified Identification Code', 'wp-full-stripe-free' ),
				'countryCode' => 'BG',
				'example' => '123456789'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'BG',
				'example' => 'BG0123456789'
			],
			[ 
				'id' => 'ca_bn',
				'description' => __( 'Canadian BN', 'wp-full-stripe-free' ),
				'countryCode' => 'CA',
				'example' => '123456789'
			],
			[ 
				'id' => 'ca_gst_hst',
				'description' => __( 'Canadian GST/HST number', 'wp-full-stripe-free' ),
				'countryCode' => 'CA',
				'example' => '123456789RT0002'
			],
			[ 
				'id' => 'ca_pst_bc',
				'description' => __( 'Canadian PST number (British Columbia)', 'wp-full-stripe-free' ),
				'countryCode' => 'CA',
				'example' => 'PST-1234-5678'
			],
			[ 
				'id' => 'ca_pst_mb',
				'description' => __( 'Canadian PST number (Manitoba)', 'wp-full-stripe-free' ),
				'countryCode' => 'CA',
				'example' => '123456-7'
			],
			[ 
				'id' => 'ca_pst_sk',
				'description' => __( 'Canadian PST number (Saskatchewan)', 'wp-full-stripe-free' ),
				'countryCode' => 'CA',
				'example' => '1234567'
			],
			[ 
				'id' => 'ca_qst',
				'description' => __( 'Canadian QST number (Québec)', 'wp-full-stripe-free' ),
				'countryCode' => 'CA',
				'example' => '1234567890TQ1234'
			],
			[ 
				'id' => 'cl_tin',
				'description' => __( 'Chilean TIN', 'wp-full-stripe-free' ),
				'countryCode' => 'CL',
				'example' => 'Chilean TIN'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'HR',
				'example' => 'HR12345678912'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'CY',
				'example' => 'CY12345678Z'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'CZ',
				'example' => 'CZ1234567890'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'DK',
				'example' => 'DK12345678'
			],
			[ 
				'id' => 'eg_tin',
				'description' => __( 'Egyptian Tax Identification Number', 'wp-full-stripe-free' ),
				'countryCode' => 'EG',
				'example' => '123456789'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'EE',
				'example' => 'EE123456789'
			],
			[ 
				'id' => 'eu_oss_vat',
				'description' => __( 'European One Stop Shop VAT number for non-Union scheme', 'wp-full-stripe-free' ),
				'countryCode' => null,
				'example' => 'EU123456789'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'FI',
				'example' => 'FI12345678'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'FR',
				'example' => 'FRAB123456789'
			],
			[ 
				'id' => 'ge_vat',
				'description' => __( 'Georgian VAT', 'wp-full-stripe-free' ),
				'countryCode' => 'GE',
				'example' => '123456789'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'DE',
				'example' => 'DE123456789'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'GR',
				'example' => 'EL123456789'
			],
			[ 
				'id' => 'hk_br',
				'description' => __( 'Hong Kong BR number', 'wp-full-stripe-free' ),
				'countryCode' => 'HK',
				'example' => '12345678'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'HU',
				'example' => 'HU12345678912'
			],
			[ 
				'id' => 'hu_tin',
				'description' => __( 'Hungary tax number (adószám)', 'wp-full-stripe-free' ),
				'countryCode' => 'HU',
				'example' => '12345678-1-23'
			],
			[ 
				'id' => 'is_vat',
				'description' => __( 'Icelandic VAT', 'wp-full-stripe-free' ),
				'countryCode' => 'IS',
				'example' => '123456'
			],
			[ 
				'id' => 'in_gst',
				'description' => __( 'Indian GST number', 'wp-full-stripe-free' ),
				'countryCode' => 'IN',
				'example' => '12ABCDE3456FGZH'
			],
			[ 
				'id' => 'id_npwp',
				'description' => __( 'Indonesian NPWP number', 'wp-full-stripe-free' ),
				'countryCode' => 'ID',
				'example' => '12.345.678.9-012.345'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'IE',
				'example' => 'IE1234567AB'
			],
			[ 
				'id' => 'il_vat',
				'description' => __( 'Israel VAT', 'wp-full-stripe-free' ),
				'countryCode' => 'IL',
				'example' => '000012345'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'IT',
				'example' => 'IT12345678912'
			],
			[ 
				'id' => 'jp_cn',
				'description' => __( 'Japanese Corporate Number (*Hōjin Bangō*)', 'wp-full-stripe-free' ),
				'countryCode' => 'JP',
				'example' => '1234567891234'
			],
			[ 
				'id' => 'jp_rn',
				'description' => __( 'Japanese Registered Foreign Businesses\' Registration Number (*Tōroku Kokugai Jigyōsha no Tōroku Bangō*)', 'wp-full-stripe-free' ),
				'countryCode' => 'JP',
				'example' => '12345'
			],
			[ 
				'id' => 'jp_trn',
				'description' => __( 'Japanese Tax Registration Number (*Tōroku Bangō*)', 'wp-full-stripe-free' ),
				'countryCode' => 'JP',
				'example' => 'T1234567891234'
			],
			[ 
				'id' => 'ke_pin',
				'description' => __( 'Kenya Revenue Authority Personal Identification Number', 'wp-full-stripe-free' ),
				'countryCode' => 'KE',
				'example' => 'P000111111A'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'LV',
				'example' => 'LV12345678912'
			],
			[ 
				'id' => 'li_uid',
				'description' => __( 'Liechtensteinian UID number', 'wp-full-stripe-free' ),
				'countryCode' => 'LI',
				'example' => 'CHE123456789'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'LT',
				'example' => 'LT123456789123'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'LU',
				'example' => 'LU12345678'
			],
			[ 
				'id' => 'my_frp',
				'description' => __( 'Malaysian FRP number', 'wp-full-stripe-free' ),
				'countryCode' => 'MY',
				'example' => '12345678'
			],
			[ 
				'id' => 'my_itn',
				'description' => __( 'Malaysian ITN', 'wp-full-stripe-free' ),
				'countryCode' => 'MY',
				'example' => 'MT12345678'
			],
			[ 
				'id' => 'my_sst',
				'description' => __( 'Malaysian SST number', 'wp-full-stripe-free' ),
				'countryCode' => 'MY',
				'example' => 'A12-3456-78912345'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'MT',
				'example' => 'MT12345678'
			],
			[ 
				'id' => 'mx_rfc',
				'description' => __( 'Mexican RFC number', 'wp-full-stripe-free' ),
				'countryCode' => 'MX',
				'example' => 'ABC010203AB9'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'NL',
				'example' => 'NL123456789B12'
			],
			[ 
				'id' => 'nz_gst',
				'description' => __( 'New Zealand GST number', 'wp-full-stripe-free' ),
				'countryCode' => 'NZ',
				'example' => '123456789'
			],
			[ 
				'id' => 'no_vat',
				'description' => __( 'Norwegian VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'NO',
				'example' => '123456789MVA'
			],
			[ 
				'id' => 'ph_tin',
				'description' => __( 'Philippines Tax Identification Number', 'wp-full-stripe-free' ),
				'countryCode' => 'PH',
				'example' => '123456789012'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'PL',
				'example' => 'PL1234567890'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'PT',
				'example' => 'PT123456789'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'RO',
				'example' => 'RO1234567891'
			],
			[ 
				'id' => 'ru_inn',
				'description' => __( 'Russian INN', 'wp-full-stripe-free' ),
				'countryCode' => 'RU',
				'example' => '1234567891'
			],
			[ 
				'id' => 'ru_kpp',
				'description' => __( 'Russian KPP', 'wp-full-stripe-free' ),
				'countryCode' => 'RU',
				'example' => '123456789'
			],
			[ 
				'id' => 'sa_vat',
				'description' => __( 'Saudi Arabia VAT', 'wp-full-stripe-free' ),
				'countryCode' => 'SA',
				'example' => '123456789012345'
			],
			[ 
				'id' => 'sg_gst',
				'description' => __( 'Singaporean GST', 'wp-full-stripe-free' ),
				'countryCode' => 'SG',
				'example' => 'M12345678X'
			],
			[ 
				'id' => 'sg_uen',
				'description' => __( 'Singaporean UEN', 'wp-full-stripe-free' ),
				'countryCode' => 'SG',
				'example' => '123456789F'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'SK',
				'example' => 'SK1234567891'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'SI',
				'example' => 'SI12345678'
			],
			[ 
				'id' => 'si_tin',
				'description' => __( 'Slovenia tax number (davčna številka)', 'wp-full-stripe-free' ),
				'countryCode' => 'SI',
				'example' => '12345678'
			],
			[ 
				'id' => 'za_vat',
				'description' => __( 'South African VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'ZA',
				'example' => '4123456789'
			],
			[ 
				'id' => 'kr_brn',
				'description' => __( 'Korean BRN', 'wp-full-stripe-free' ),
				'countryCode' => 'KR',
				'example' => '123-45-67890'
			],
			[ 
				'id' => 'es_cif',
				'description' => __( 'Spanish NIF number (previously Spanish CIF number)', 'wp-full-stripe-free' ),
				'countryCode' => 'ES',
				'example' => 'A12345678'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'ES',
				'example' => 'ESA1234567Z'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'European VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'SE',
				'example' => 'SE123456789123'
			],
			[ 
				'id' => 'ch_vat',
				'description' => __( 'Switzerland VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'CH',
				'example' => 'CHE-123.456.789 MWST'
			],
			[ 
				'id' => 'tw_vat',
				'description' => __( 'Taiwanese VAT', 'wp-full-stripe-free' ),
				'countryCode' => 'TW',
				'example' => '12345678'
			],
			[ 
				'id' => 'th_vat',
				'description' => __( 'Thai VAT', 'wp-full-stripe-free' ),
				'countryCode' => 'TH',
				'example' => '1234567891234'
			],
			[ 
				'id' => 'tr_tin',
				'description' => __( 'Turkish Tax Identification Number', 'wp-full-stripe-free' ),
				'countryCode' => 'TR',
				'example' => '0123456789'
			],
			[ 
				'id' => 'ua_vat',
				'description' => __( 'Ukrainian VAT', 'wp-full-stripe-free' ),
				'countryCode' => 'UA',
				'example' => '123456789'
			],
			[ 
				'id' => 'ae_trn',
				'description' => __( 'United Arab Emirates TRN', 'wp-full-stripe-free' ),
				'countryCode' => 'AE',
				'example' => '123456789012345'
			],
			[ 
				'id' => 'eu_vat',
				'description' => __( 'Northern Ireland VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'GB',
				'example' => 'XI123456789'
			],
			[ 
				'id' => 'gb_vat',
				'description' => __( 'United Kingdom VAT number', 'wp-full-stripe-free' ),
				'countryCode' => 'GB',
				'example' => 'GB123456789'
			],
			[ 
				'id' => 'us_ein',
				'description' => __( 'United States EIN', 'wp-full-stripe-free' ),
				'countryCode' => 'US',
				'example' => '12-3456789'
			],
		];
	}

	static function getUniqueTaxIdTypes() {
		$result = [];

		foreach ( self::getTaxIdTypes() as $taxIdItem ) {
			$taxId = $taxIdItem['id'];

			if ( ! array_key_exists( $taxId, $result ) ) {
				$result[ $taxId ] = [ 
					'id' => $taxId,
					'description' => $taxIdItem['description']
				];
			}
		}

		return array_values( $result );
	}

	static function getTaxIdTypesByCountry() {
		$result = [];

		foreach ( self::getTaxIdTypes() as $taxIdTypeItem ) {
			$countryCode = $taxIdTypeItem['countryCode'];
			$id = $taxIdTypeItem['id'];

			$countryItem = $countryCode !== null && array_key_exists( $countryCode, $result ) ? $result[ $countryCode ] : [];
			if ( ! array_key_exists( $id, $countryItem ) ) {
				$countryItem[ $id ] = [ 
					'id' => $id,
					'example' => $taxIdTypeItem['example']
				];
			}
			$result[ $countryCode ] = $countryItem;
		}

		return $result;
	}
}
