<?php

namespace ThemeIsle\WPFullPay\Patcher;

use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Patcher\Patches\AddUniquePaymentIntentKeyToReports;
use ThemeIsle\WPFullPay\Patcher\Patches\MigrateFeeRecoverySettings;
use ThemeIsle\WPFullPay\Patcher\Patches\SetMissingTaxTypeOfPaymentForms;
use ThemeIsle\WPFullPay\Patcher\Patches\SetRefundedAmountOfRefundedReports;

class Patcher {

	public static function applyPatches() {
        $options       = new Options();
        $loggerService = new LoggerService( $options->get( Options::OPTION_LOG_LEVEL ), $options->get( Options::OPTION_LOG_TO_WEB_SERVER ) == 1 );
        $logger        = $loggerService->createPatcherLogger(__CLASS__);

        $logger->debug( __FUNCTION__, 'Apply patches...' );

		$patches        = self::preparePatches( $loggerService );
		$appliedPatches = self::loadAppliedPatches();

		foreach ( $patches as $patch ) {
			if ( array_key_exists( $patch->getId(), $appliedPatches ) && ! $patch->isRepeatable() ) {
				continue;
			}

			try {
				$logger->debug( __FUNCTION__, $patch->getId() . ' applying ' );

				if ( $patch->apply() ) {
					self::bookApplied( $patch );

					$logger->debug( __FUNCTION__, $patch->getId() . ' applied successfully.' );
				} else {
					$logger->debug( __FUNCTION__, $patch->getId() . ' failed!' );
				}
			} catch ( \Exception $ex ) {
				$logger->error( __FUNCTION__, 'Error while applying patches', $ex );
			}
		}

		$logger->debug( __FUNCTION__, 'Patches applied.' );
	}

	/**
	 * Patches for plugin versions up to 6.2.0 were removed, so a site updating from
	 * 6.2.0 or older straight to this version skips them.
	 *
	 * @param LoggerService $loggerService
	 * @return Patch[]
	 */
	private static function preparePatches( $loggerService ) {
		return [
			new MigrateFeeRecoverySettings( $loggerService ),
			new AddUniquePaymentIntentKeyToReports( $loggerService ),
			new SetMissingTaxTypeOfPaymentForms( $loggerService ),
			new SetRefundedAmountOfRefundedReports( $loggerService ),
		];
	}

	/**
	 * @return array
	 */
	private static function loadAppliedPatches() {
		global $wpdb;

		$result = $wpdb->get_results( "select id,patch_id,plugin_version,applied_at,description from {$wpdb->prefix}fullstripe_patch_info" );

		$applied_patches = [];

		foreach ( $result as $applied_patch ) {
			$applied_patches[ $applied_patch->patch_id ] = $applied_patch;
		}

		return $applied_patches;
	}

	private static function bookApplied($patch ) {

		if ( ! isset( $patch ) ) {
			return;
		}

		/* @var $patch Patch */

		global $wpdb;

		$data = [
			'patch_id'       => $patch->getId(),
			'plugin_version' => $patch->getPluginVersion(),
			'applied_at'     => current_time( 'mysql', true ),
			'description'    => $patch->getDescription()
		];

		if ( $wpdb->insert( "{$wpdb->prefix}fullstripe_patch_info", $data ) === false ) {
			throw new \Exception( 'Cannot insert patch_info: ' . $wpdb->last_error );
		}
	}

}
