<?php

namespace ThemeIsle\WPFullPay\Patcher\Patches;

use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Patcher\Patch;

class MigrateFeeRecoverySettings extends Patch {
    /**
     * MigrateFeeRecoverySettings constructor.
	 *
	 * @param LoggerService $loggerService
     */
    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );

        $this->id             = 'migrate_fee_recovery_settings';
        $this->plugin_version = '8.5.0';
        $this->description    = 'A patch for migrating fee recovery settings to form specific settings.';
        $this->repeatable     = false;
    }

	/**
	 * @return bool
	 * @throws \Exception
	 */
    public function apply() {
        $fee_recovery = [];

		$fee_recovery['feeRecovery'] = $this->options->get( Options::OPTION_FEE_RECOVERY );
		$fee_recovery['feeRecoveryOptIn'] = $this->options->get( Options::OPTION_FEE_RECOVERY_OPT_IN );
		$fee_recovery['feeRecoveryOptInMessage'] = $this->options->get( Options::OPTION_FEE_RECOVERY_OPT_IN_MESSAGE );
		$fee_recovery['feeRecoveryFeeAdditionalAmount'] = $this->options->get( Options::OPTION_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT );
		$fee_recovery['feeRecoveryFeePercentage'] = $this->options->get( Options::OPTION_FEE_RECOVERY_FEE_PERCENTAGE );

		$this->migrate_inline_payment_fee_recovery( $fee_recovery );
		$this->migrate_checkout_payment_fee_recovery( $fee_recovery );
		$this->migrate_inline_subscription_fee_recovery( $fee_recovery );
		$this->migrate_checkout_subscription_fee_recovery( $fee_recovery );
		$this->migrate_inline_donation_fee_recovery( $fee_recovery );
		$this->migrate_checkout_donation_fee_recovery( $fee_recovery );

        return true;
    }

	/**
	 * Migrate inline payment form's fee recovery settings.
	 * @param array<string, mixed> $fee_recovery
	 * @return void
	 */
    private function migrate_inline_payment_fee_recovery( $fee_recovery ) {
        global $wpdb;

        $payment_forms = $wpdb->get_results( "SELECT paymentFormID, feeRecovery from {$wpdb->prefix}fullstripe_payment_forms where customAmount != 'card_capture';", ARRAY_A );
        Database::handleDbError( $payment_forms, __FUNCTION__ . '(): an error occurred during querying inline payment forms!' );

        if ( is_array( $payment_forms ) ) {
            foreach ( $payment_forms as $form ) {
                if ( isset( $form['paymentFormID'] ) ) {
					$data = $this->get_data( $form, $fee_recovery );
					$updated = $wpdb->update( "{$wpdb->prefix}fullstripe_payment_forms", $data, [ 'paymentFormID' => $form['paymentFormID'] ] );
			        Database::handleDbError( $updated, __FUNCTION__ . '(): an error occurred during querying inline payment forms!' );

                }
            }
        }
    }

	/**
	 * Migrate checkout payment form's fee recovery settings.
	 * @param array<string, mixed> $fee_recovery
	 * @return void
	 */
    private function migrate_checkout_payment_fee_recovery( $fee_recovery ) {
        global $wpdb;

        $checkout_forms = $wpdb->get_results( "SELECT checkoutFormID, feeRecovery from {$wpdb->prefix}fullstripe_checkout_forms;", ARRAY_A );
        Database::handleDbError( $checkout_forms, __FUNCTION__ . '(): an error occurred during querying checkout payment forms!' );

        if ( is_array( $checkout_forms ) ) {
            foreach ( $checkout_forms as $form ) {
                if ( isset( $form['checkoutFormID'] ) ) {
					$data = $this->get_data( $form, $fee_recovery );
					$updated = $wpdb->update( "{$wpdb->prefix}fullstripe_checkout_forms", $data, [ 'checkoutFormID' => $form['checkoutFormID'] ] );
        			Database::handleDbError( $updated, __FUNCTION__ . '(): an error occurred during querying checkout payment forms!' );
                }
            }
        }
    }

	/**
	 * Migrate inline subscription form's fee recovery settings.
	 * @param array<string, mixed> $fee_recovery
	 * @return void
	 */
    private function migrate_inline_subscription_fee_recovery( $fee_recovery ) {
        global $wpdb;

        $subscription_forms = $wpdb->get_results( "SELECT subscriptionFormID, feeRecovery from {$wpdb->prefix}fullstripe_subscription_forms;", ARRAY_A );
        Database::handleDbError( $subscription_forms, __FUNCTION__ . '(): an error occurred during querying inline subscription forms!' );

        if ( is_array( $subscription_forms ) ) {
            foreach ( $subscription_forms as $form ) {
                if ( isset( $form['subscriptionFormID'] ) ) {
					$data = $this->get_data( $form, $fee_recovery );
					$updated = $wpdb->update( "{$wpdb->prefix}fullstripe_subscription_forms", $data, [ 'subscriptionFormID' => $form['subscriptionFormID'] ] );
					Database::handleDbError( $updated, __FUNCTION__ . '(): an error occurred during querying inline subscription forms!' );
                }
            }
        }
    }

	/**
	 * Migrate checkout subscription form's fee recovery settings.
	 * @param array<string, mixed> $fee_recovery
	 * @return void
	 */
    private function migrate_checkout_subscription_fee_recovery( $fee_recovery ) {
        global $wpdb;

        $checkout_subscription_forms = $wpdb->get_results( "SELECT checkoutSubscriptionFormID, feeRecovery from {$wpdb->prefix}fullstripe_checkout_subscription_forms;", ARRAY_A );
        Database::handleDbError( $checkout_subscription_forms, __FUNCTION__ . '(): an error occurred during querying checkout subscription forms!' );

        if ( is_array( $checkout_subscription_forms ) ) {
            foreach ( $checkout_subscription_forms as $form ) {
                if ( isset( $form['checkoutSubscriptionFormID'] ) ) {
					$data = $this->get_data( $form, $fee_recovery );
					$updated = $wpdb->update( "{$wpdb->prefix}fullstripe_checkout_subscription_forms", $data, [ 'checkoutSubscriptionFormID' => $form['checkoutSubscriptionFormID'] ] );
					Database::handleDbError( $updated, __FUNCTION__ . '(): an error occurred during querying checkout subscription forms!' );
                }
            }
        }
    }

	/**
	 * Migrate inline donation form's fee recovery settings.
	 * @param array<string, mixed> $fee_recovery
	 * @return void
	 */
    private function migrate_inline_donation_fee_recovery( $fee_recovery ) {
        global $wpdb;

        $donation_forms = $wpdb->get_results( "SELECT donationFormID, feeRecovery from {$wpdb->prefix}fullstripe_donation_forms;", ARRAY_A );
        Database::handleDbError( $donation_forms, __FUNCTION__ . '(): an error occurred during querying inline donation forms!' );

        if ( is_array( $donation_forms ) ) {
            foreach ( $donation_forms as $form ) {
                if ( isset( $form['donationFormID'] ) ) {
					$data = $this->get_data( $form, $fee_recovery );
					$updated = $wpdb->update( "{$wpdb->prefix}fullstripe_donation_forms", $data, [ 'donationFormID' => $form['donationFormID'] ] );
			        Database::handleDbError( $updated, __FUNCTION__ . '(): an error occurred during querying inline donation forms!' );
                }
            }
        }
    }

	/**
	 * Migrate checkout donation form's fee recovery settings.
	 * @param array<string, mixed> $fee_recovery
	 * @return void
	 */
    private function migrate_checkout_donation_fee_recovery( $fee_recovery ) {
        global $wpdb;

        $checkout_donation_forms = $wpdb->get_results( "SELECT checkoutDonationFormID, feeRecovery from {$wpdb->prefix}fullstripe_checkout_donation_forms;", ARRAY_A );
        Database::handleDbError( $checkout_donation_forms, __FUNCTION__ . '(): an error occurred during querying checkout donation forms!' );

        if ( is_array( $checkout_donation_forms ) ) {
            foreach ( $checkout_donation_forms as $form ) {
                if ( isset( $form['checkoutDonationFormID'] ) ) {
					$data = $this->get_data( $form, $fee_recovery );
					$updated = $wpdb->update( "{$wpdb->prefix}fullstripe_checkout_donation_forms", $data, [ 'checkoutDonationFormID' => $form['checkoutDonationFormID'] ] );
					Database::handleDbError( $updated, __FUNCTION__ . '(): an error occurred during querying checkout donation forms!' );
                }
            }
        }
    }

	/**
	 * Get data.
	 *
	 * @param array<string, mixed> $form
	 * @param array<string, mixed> $fee_recovery
	 * @return array<string, string>
	 */
	private function get_data( $form, $fee_recovery ) {		
		if ( ! isset( $form['feeRecovery'] ) ) {
			return [];
		}

		$data = [];

		if ( 'inherit' === $form['feeRecovery'] ) {
			$fee_recovery['feeRecovery'] = Main::FEE_RECOVERY_ENABLE;
			$data = $fee_recovery;
		} else if ( 'customize' === $form['feeRecovery'] ) {
			$data = [ 'feeRecovery' => Main::FEE_RECOVERY_ENABLE ];
		} else {
			$data = [ 'feeRecovery' => Main::FEE_RECOVERY_DISABLE ];
		}

		return $data;
	}
}
