<?php

namespace ThemeIsle\WPFullPay\Patcher\Patches;

use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Forms\FormTypes;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Patcher\Patch;

/**
 * Checkout payment and save card forms were created without a tax type, which the repeatable
 * patches removed in this version set on the next update. Forms created since then get it here.
 */
class SetMissingTaxTypeOfPaymentForms extends Patch {
	/**
	 * @param LoggerService $loggerService
	 */
	public function __construct( $loggerService ) {
		parent::__construct( $loggerService );

		$this->id             = 'set_missing_tax_type_of_payment_forms';
		$this->plugin_version = '8.5.9';
		$this->description    = 'A patch for giving payment and save card forms without a tax type no tax.';
		$this->repeatable     = false;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function apply() {
		global $wpdb;

		foreach ( [ Main::FORM_TYPE_INLINE_PAYMENT, Main::FORM_TYPE_CHECKOUT_PAYMENT ] as $formType ) {
			$result = $wpdb->update(
				$wpdb->prefix . FormTypes::get( $formType )->table,
				[ 'vatRateType' => Main::FIELD_VALUE_TAX_RATE_NO_TAX, 'vatRates' => wp_json_encode( [] ) ],
				[ 'vatRateType' => null ]
			);
			Database::handleDbError( $result, __FUNCTION__ . '(): an error occurred while setting the tax type of ' . $formType . ' forms!' );
		}

		return true;
	}
}
