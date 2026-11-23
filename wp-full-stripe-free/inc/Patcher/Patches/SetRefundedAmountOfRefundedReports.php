<?php

namespace ThemeIsle\WPFullPay\Patcher\Patches;

use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Patcher\Patch;

/**
 * Reports stored only a refund's status before this version. A full refund refunded the whole
 * amount; a partial refund's amount is unknown without asking Stripe, so it stays at zero.
 *
 * Such a report counts at its full amount. Reading each refund would take a Stripe call per report,
 * in its mode and account, and before this version the report was left out of revenue entirely.
 */
class SetRefundedAmountOfRefundedReports extends Patch {
	/**
	 * @param LoggerService $loggerService
	 */
	public function __construct( $loggerService ) {
		parent::__construct( $loggerService );

		$this->id             = 'set_refunded_amount_of_refunded_reports';
		$this->plugin_version = '8.5.9';
		$this->description    = 'A patch for setting the refunded amount of fully refunded reports.';
		$this->repeatable     = false;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function apply() {
		global $wpdb;

		// A refund event may have stored the amount since the column was added.
		$result = $wpdb->query( "UPDATE {$wpdb->prefix}fullstripe_reports SET amount_refunded = amount WHERE status = 'refunded' AND amount_refunded = 0" );
		Database::handleDbError( $result, __FUNCTION__ . '(): an error occurred while setting the refunded amount of refunded reports!' );

		return true;
	}
}
