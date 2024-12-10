<?php
/*
 * This is a generic license manager for WP Full Pay
 */

class WPFS_License {

	/**
	 * Price ID to licence type map
	 *
	 * @var int[]
	 */
	public static $plans_map = [
		1 => 1,
		2 => 2,
		3 => 3,
	];

	/**
	 * Get the license data.
	 *
	 * @return bool|\stdClass
	 */
	public static function get_data() {
		$option_name = basename( dirname( WP_FULL_STRIPE_BASENAME ) );
		$option_name = str_replace( '-', '_', strtolower( trim( $option_name ) ) );
		return get_option( $option_name . '_license_data' );
	}

	/**
	 * Get active license.
	 *
	 * @return bool
	 */
	public static function is_active() {
		$status = self::get_data();

		if ( ! $status ) {
			return false;
		}

		if ( ! isset( $status->license ) ) {
			return false;
		}

		if ( 'valid' !== $status->license ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if license is expired.
	 *
	 * @return bool
	 */
	public static function is_expired() {
		$status = self::get_data();

		if ( ! $status ) {
			return false;
		}

		if ( ! isset( $status->license ) ) {
			return false;
		}

		if ( 'active_expired' !== $status->license ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the license expiration date.
	 *
	 * @param string $format format of the date.
	 * @return false|string
	 */
	public static function get_expiration_date( $format = 'F Y' ) {
		$data = self::get_data();

		if ( isset( $data->expires ) ) {
			$parsed = date_parse( $data->expires );
			$time   = mktime( $parsed['hour'], $parsed['minute'], $parsed['second'], $parsed['month'], $parsed['day'], $parsed['year'] );
			return gmdate( $format, $time );
		}

		return false;
	}

	/**
	 * Get the licence type.
	 * 1 - personal, 2 - business, 3 - agency.
	 *
	 * @return int
	 */
	public static function get_type() {
		$license = self::get_data();
		if ( false === $license ) {
			return -1;
		}

		if ( ! isset( $license->price_id ) ) {
			return -1;
		}

		if ( isset( $license->license ) && ( 'valid' !== $license->license && 'active_expired' !== $license->license ) ) {
			return -1;
		}

		if ( ! array_key_exists( $license->price_id, self::$plans_map ) ) {
			return -1;
		}

		return self::$plans_map[ $license->price_id ];
	}

	/**
	 * Get User ID.
	 * 
	 * @return int
	 */
	public static function get_user_id() {
		$license = self::get_data();

		// We don't have user_id like WPFS previously used with freemium, so we use payment_id instead.
		if ( false === $license ) {
			return -1;
		}

		if ( ! isset( $license->payment_id ) ) {
			return -1;
		}

		return $license->payment_id;
	}

	/**
	 * Get License Key.
	 * 
	 * @return string
	 */
	public static function get_key() {
		$license = self::get_data();

		if ( false === $license ) {
			return '';
		}

		if ( ! isset( $license->key ) ) {
			return '';
		}

		return $license->key;
	}

	/**
	 * Get Activation URL.
	 * 
	 * @return string
	 */
	public static function get_activation_url() {
		$admin_url = admin_url( 'options-general.php' );
		return $admin_url;
	}
}
