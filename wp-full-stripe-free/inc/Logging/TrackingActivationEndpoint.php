<?php

namespace ThemeIsle\WPFullPay\Logging;

use ThemeIsle\WPFullPay\License;

/**
 * Tracking/Logger Activation REST API Endpoint Handler
 */
class TrackingActivationEndpoint {

	const ROUTE_NAMESPACE = 'wp-full-stripe/v1';
	const ROUTE_PATH = '/tracking-activation';
	const NONCE_ACTION = 'wp_rest';

	/**
	 * Register the REST API endpoint.
	 * 
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/**
	 * Get the URL for the tracking activation endpoint.
	 * @return string
	 */
	public static function get_url() {
		return rest_url( self::ROUTE_NAMESPACE . self::ROUTE_PATH );
	}

	/**
	 * Register REST routes for tracking activation.
	 * 
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE_PATH,
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'handle_tracking_activation' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'enabled' => [
						'type'        => 'boolean',
						'description' => 'Enable or disable tracking',
						'required'    => true,
					],
					'nonce'   => [
						'type'        => 'string',
						'description' => 'Security nonce',
						'required'    => true,
					],
				],
			]
		);
	}

	/**
	 * Check if user has permission to access this endpoint.
	 *
	 * @param \WP_REST_Request<array{enabled: bool, nonce: string}> $request The request object.
	 *
	 * @return bool|\WP_Error
	 */
	public static function check_permission( $request ) {
		$nonce = $request->get_param( 'nonce' );
		
		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return new \WP_Error(
				'rest_nonce_invalid',
				__( 'Invalid security nonce.', 'wp-full-stripe-free' ),
				[ 'status' => 403 ]
			);
		}
	   
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage tracking settings.', 'wp-full-stripe-free' ), // NOTE: This is used internally only.
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Handle the tracking activation request.
	 *
	 * @param \WP_REST_Request<array{enabled: bool, nonce: string}> $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_tracking_activation( $request ) {
		try {
			$enabled = $request->get_param( 'enabled' );
			
			$flag_value = $enabled ? 'yes' : 'no';
			$option_key = License::get_namespace() . '_logger_flag';
			update_option( $option_key, $flag_value );
			
			return new \WP_REST_Response(
				[
					'success'      => true,
					'message'      => __( 'Tracking settings updated successfully.', 'wp-full-stripe-free' ), // NOTE: This is used internally only.
					'enabled'      => $enabled,
					'option_key'   => $option_key,
					'option_value' => $flag_value,
				],
				200
			);
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'tracking_activation_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to update tracking settings: %s', 'wp-full-stripe-free' ),
					$e->getMessage()
				),
				[ 'status' => 500 ]
			);
		}
	}
}
