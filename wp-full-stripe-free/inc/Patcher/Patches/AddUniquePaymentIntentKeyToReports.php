<?php

namespace ThemeIsle\WPFullPay\Patcher\Patches;

use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Patcher\Patch;

class AddUniquePaymentIntentKeyToReports extends Patch {
	/**
	 * @param LoggerService $loggerService
	 */
	public function __construct( $loggerService ) {
		parent::__construct( $loggerService );

		$this->id             = 'add_unique_payment_intent_key_to_reports';
		$this->plugin_version = '8.5.9';
		$this->description    = 'A patch for removing duplicate payment reports and making their PaymentIntent unique.';
		$this->repeatable     = false;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function apply() {
		global $wpdb;

		if ( $wpdb->get_var( "SHOW INDEX FROM {$wpdb->prefix}fullstripe_reports WHERE Key_name = 'stripePaymentIntentID'" ) ) {
			return true;
		}

		$result = $wpdb->query( "UPDATE {$wpdb->prefix}fullstripe_reports SET stripePaymentIntentID = NULL WHERE stripePaymentIntentID = ''" );
		Database::handleDbError( $result, __FUNCTION__ . '(): an error occurred while clearing empty PaymentIntents!' );

		// Subscription reports are written without amount and currency, which a duplicate webhook row may hold.
		$result = $wpdb->query(
			"UPDATE {$wpdb->prefix}fullstripe_reports kept
			INNER JOIN (
				SELECT MIN(id) AS keptId, MAX(amount) AS amount, MAX(NULLIF(currency, '')) AS currency FROM {$wpdb->prefix}fullstripe_reports
				WHERE stripePaymentIntentID IS NOT NULL
				GROUP BY stripePaymentIntentID
				HAVING COUNT(*) > 1
			) merged ON kept.id = merged.keptId
			SET kept.amount = IF(kept.amount = 0, merged.amount, kept.amount),
				kept.currency = IF(kept.currency = '', COALESCE(merged.currency, ''), kept.currency)"
		);
		Database::handleDbError( $result, __FUNCTION__ . '(): an error occurred while merging duplicate reports!' );

		// Report updates land on the first row of a PaymentIntent, so the lowest id is kept.
		$result = $wpdb->query(
			"DELETE reports FROM {$wpdb->prefix}fullstripe_reports reports
			INNER JOIN (
				SELECT stripePaymentIntentID, MIN(id) AS keptId FROM {$wpdb->prefix}fullstripe_reports
				WHERE stripePaymentIntentID IS NOT NULL
				GROUP BY stripePaymentIntentID
				HAVING COUNT(*) > 1
			) duplicates ON reports.stripePaymentIntentID = duplicates.stripePaymentIntentID AND reports.id <> duplicates.keptId"
		);
		Database::handleDbError( $result, __FUNCTION__ . '(): an error occurred while deleting duplicate reports!' );

		$result = $wpdb->query( "ALTER TABLE {$wpdb->prefix}fullstripe_reports ADD UNIQUE KEY stripePaymentIntentID (stripePaymentIntentID)" );
		Database::handleDbError( $result, __FUNCTION__ . '(): an error occurred while adding the unique key!' );

		return true;
	}
}
