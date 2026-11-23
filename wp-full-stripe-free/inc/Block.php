<?php

namespace ThemeIsle\WPFullPay;

/**
 * Block class.
 */
class Block {

	const WPFS_PLUGIN_SLUG = 'wp-full-stripe';
	const WPFS_REST_API_VERSION = 'v1';
	const WPFS_REST_ROUTE = 'block/forms';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'enqueue_block_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	/**
	 * Load the form styles and jQuery UI in the editor so the block preview looks like the frontend.
	 *
	 * `enqueue_block_assets` reaches the iframed editor too. It also runs on the frontend,
	 * where the shortcode loads its own assets, so only the admin is handled here.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets() {
		if ( ! is_admin() ) {
			return;
		}

		Main::getInstance()->fullstripe_load_css();
		wp_enqueue_script( 'jquery-ui-selectmenu' );
	}

	/**
	 * Register block.
	 * 
	 * @return void
	 */
	public function register_block() {
		$block_path = WP_FULL_STRIPE_PATH . '/assets/build';

		if ( file_exists( $block_path . '/block.json' ) ) {
			register_block_type( $block_path );
		}
	}

	/**
	 * Rest Namespace.
	 */
	private function get_namespace() {
		return self::WPFS_PLUGIN_SLUG . '/' . self::WPFS_REST_API_VERSION;
	}

	/**
	 * Rest Route.
	 */
	private function get_route() {
		return '/' . self::WPFS_REST_ROUTE;
	}

	/**
	 * Register routes.
	 * 
	 * @return void
	 */
	public function register_routes() {
		register_rest_route( $this->get_namespace(), $this->get_route(), [
			[
				'methods' => \WP_REST_Server::READABLE,
				'callback' => [ $this, 'callback' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		]);
	}

	/**
	 * Callback.
	 * 
	 * @return \WP_REST_Response
	 */
	public function callback() {
		$db    = new Database();
		$forms = $db->getAllForms();

		$can_edit = current_user_can( 'manage_options' );

		for ( $idx = 0; $idx < count( $forms ); $idx++ ) {
			$form = $forms[ $idx ];
			$form->shortcode = Shortcode::createShortCodeByForm( $form );
			if ( $can_edit ) {
				$form->editUrl = Utils::getFormEditUrl( $form->id, $form->type, $form->layout );
			}
			$forms[ $idx ] = $form;
		}

		return rest_ensure_response( $forms );
	}

	/**
	 * Permission callback.
	 * 
	 * @return bool
	 */
	public function permission_callback() {
		return current_user_can( 'edit_posts' );
	}
}
