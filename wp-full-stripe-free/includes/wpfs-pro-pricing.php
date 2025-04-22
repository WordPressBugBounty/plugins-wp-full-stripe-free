<?php

/**
 * Class WPFS_Pro_Pricing
 *
 * This class handles the Pro Pricing functionality for the WP Full Stripe plugin.
 */
class WPFS_Pro_Pricing {

	/**
	 * @var string The capability required to manage options.
	 */
	public $capability = 'manage_options';

	/**
	 * @var string The error state identifier.
	 */
	public $error_state = 'error';

	/**
	 * Initializes the class by adding necessary actions.
	 *
	 * This method hooks into the WordPress admin menu and admin initialization
	 * to register the Pro Pricing page and capture upgrade actions.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register' ) );
		add_action( 'admin_init', [ $this, 'load' ] );
	}

	/**
	 * Loads the necessary actions for the Pro Pricing feature.
	 *
	 * This function checks if the Pro Pricing feature is active. If it is active,
	 * it adds an action to handle the AJAX request for activating the license.
	 */
	public function load() {
		if ( ! $this->is_active() ) {
			return;
		}
		add_action( 'wp_ajax_fullstripe_activate_license', array( $this, 'activate_license' ) );
	}

	/**
	 * Checks if the Pro Pricing feature is active.
	 *
	 * This function determines if the Pro Pricing feature should be displayed.
	 * It uses a filter to allow customization of the visibility and checks if the license is active.
	 *
	 * @return bool True if the Pro Pricing feature is active, false otherwise.
	 */
	private function is_active() {
		if ( apply_filters( 'fullstripe_show_pro_pricing', true ) === false ) {
			return false;
		}
		if ( WPFS_License::is_active() ) {
			return false;
		}

		return true;
	}
	/**
	 * Registers the Pro Pricing submenu in the WordPress admin menu.
	 *
	 * This function checks if the Pro Pricing feature is active. If it is active,
	 * it adds a submenu page under the Transactions menu in the WordPress admin.
	 * The submenu page is titled "Upgrade to Pro" and links to the Pro Pricing page.
	 */
	public function register() {
		if ( ! $this->is_active() ) {
			return;
		}

		$function       = array( $this, 'render' );
		$sub_menu_title =
			/* translators: Submenu title of the "Transactions" page in WordPress admin */
			__( 'Upgrade to Pro', 'wp-full-stripe-free' );
		add_submenu_page( MM_WPFS_Admin_Menu::SLUG_TRANSACTIONS, $sub_menu_title, '<span class="tsdk-upg-menu-item" style="color: #009528">' . $sub_menu_title . '</span>', $this->capability, MM_WPFS_Admin_Menu::SLUG_UPGRADE_PRO, $function, 100 );

	}
	/**
	 * Activates the license via an AJAX request.
	 *
	 * This function handles the AJAX request to activate the license. It verifies the nonce,
	 * sanitizes the input data, and makes an API call to activate the license. If the activation
	 * is successful, it triggers the license process action and sends a success response.
	 * Otherwise, it sends an error response.
	 */
	function activate_license() {
		check_ajax_referer( 'check_activation_status', 'nonce' );
		$cses  = sanitize_text_field( $_POST['cses'] );
		$index = sanitize_text_field( $_POST['index'] );

		// Make API call to activate license
		$response = wp_remote_get( sprintf( 'https://api.themeisle.com/checkout/activation/%s/%s', $cses, $index ) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		if ( $data && isset( $data->license_key ) ) {
			do_action( 'themeisle_sdk_license_process_wpfs', $data->license_key, 'activate' );
			if ( ! WPFS_License::is_active() ) {
				wp_send_json_error();
			}
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}

		wp_die();
	}
	/**
	 * Captures the upgrade process.
	 *
	 * This function handles the upgrade process by displaying different states
	 * (loading, success, error) based on the upgrade status. It uses AJAX to check
	 * the activation status and updates the UI accordingly.
	 */
	public function capture_upgrade() {

		$thank_you_config = [
			'checking' => [
				'title'    => __( 'Activating Your Product...', 'wp-full-stripe-free' ),
				'messages' => [
					__( 'Verifying purchase details 🔍', 'wp-full-stripe-free' ),
					__( 'Processing your payment 💳', 'wp-full-stripe-free' ),
					__( 'Generating your license key 🔑', 'wp-full-stripe-free' ),
					__( 'Registering your product 📝', 'wp-full-stripe-free' ),
					__( 'Finalizing activation 🔄', 'wp-full-stripe-free' ),
					__( 'Optimizing settings ⚙️', 'wp-full-stripe-free' ),
					__( 'Checking everything one last time ✅', 'wp-full-stripe-free' ),
					__( 'This is taking longer than expected... ⏳', 'wp-full-stripe-free' ),
					__( 'Still working on it, hang tight! 🚧', 'wp-full-stripe-free' ),
				]
			],
			'success'  => [
				'title'   => __( 'Activation Successful! 🎉', 'wp-full-stripe-free' ),
				'message' => __( 'Your product is now activated and ready to use.', 'wp-full-stripe-free' ),
				'cta'     => [
					'text' => __( 'Go to Dashboard', 'wp-full-stripe-free' ),
					'url'  => admin_url( 'admin.php?page=wpfs-forms' )
				]
			],
			'error'    => [
				'title'       => __( 'Activation Pending 🔑', 'wp-full-stripe-free' ),
				'message'     => __( 'We weren\'t able to activate your license automatically. Check your email for your login credentials and license key.', 'wp-full-stripe-free' ),
				'sub_message' => __( 'Haven\'t received the email? Contact our support team for assistance.', 'wp-full-stripe-free' ),
				'actions'     => [
					'license' => [
						'text'        => __( 'Enter License Key', 'wp-full-stripe-free' ),
						'url'         => admin_url( 'admin.php?page=license-activation' ),
						'description' => __( 'Already have your license key? Activate it here', 'wp-full-stripe-free' )
					],
					'login'   => [
						'text'        => __( 'Login to Get License Key', 'wp-full-stripe-free' ),
						'url'         => 'https://store.themeisle.com',
						'description' => __( 'Access your account to find and get your license key.', 'wp-full-stripe-free' )
					],
					'support' => [
						'text'        => __( 'Contact Support', 'wp-full-stripe-free' ),
						'url'         => add_query_arg( [ 'slkey' => sanitize_key( $_GET['cses'] ) ], 'https://store.themeisle.com/direct-support/' ),
						'description' => __( 'Get help from our support team', 'wp-full-stripe-free' )
					]
				]
			]
		];
		?>
        <div class="wpfsp-pricing-container wpfsp-thank-you">

            <div class="wpfsp-thank-you-content " id="thankYouContent">
                <!-- Loading State -->
                <div class="wpfsp-loading-state" id="loadingState">
                    <div class="wpfsp-loading-spinner"></div>
                    <h2 class="wpfsp-loading-title"></h2>
                    <p class="wpfsp-loading-message"></p>
                </div>

                <!-- Success State -->
                <div class="wpfsp-success-state hidden" id="successState">
                    <div class="wpfsp-success-icon">✨</div>
                    <h2 class="wpfsp-success-title"></h2>
                    <p class="wpfsp-success-message"></p>
                    <div class="wpfsp-button-container">
                        <a href="#" class="button button-primary button-hero" id="successButton"></a>
                    </div>
                </div>

                <!-- Error State -->
                <div class="wpfsp-error-state hidden" id="errorState">
                    <div class="wpfsp-error-icon">🔑</div>
                    <h2 class="wpfsp-error-title"></h2>
                    <p class="wpfsp-error-message"></p>

                    <div class="wpfsp-main-actions">
                        <a href="<?php echo esc_url( $thank_you_config['error']['actions']['license']['url'] ); ?>"
                           class="button button-primary button-hero">
							<?php echo esc_html( $thank_you_config['error']['actions']['license']['text'] ); ?>
                        </a>
                        <p class="wpfsp-action-description"><?php echo esc_html( $thank_you_config['error']['actions']['license']['description'] ); ?></p>
                    </div>

                    <div class="wpfsp-main-actions">
                        <button class="button button-secondary button-hero">
							<?php echo esc_html( $thank_you_config['error']['actions']['login']['text'] ); ?>
                        </button>
                        <p class="wpfsp-action-description"><?php echo esc_html( $thank_you_config['error']['actions']['login']['description'] ); ?></p>
                    </div>

                    <div class="wpfsp-support-section">
                        <span class="wpfsp-divider"><?php esc_html_e( 'Still having trouble?', 'wp-full-stripe-free' ); ?></span>
                        <a href="<?php echo esc_url( $thank_you_config['error']['actions']['support']['url'] ); ?>"
                           class="button button-link">
							<?php echo esc_html( $thank_you_config['error']['actions']['support']['text'] ); ?> →
                        </a>
                    </div>
                </div>

            </div>
        </div>

        <style>
            /* Additional styles - keeps existing wpfsp-pricing-container styles */
            .wpfsp-thank-you {
                min-height: 400px;
                display: flex;
                align-items: center;
                justify-content: center;
                text-align: center;
            }

            .wpfsp-thank-you-content {
                padding: 40px;
                margin-top: 20px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                width: 100%;
                max-width: 500px;
            }

            .wpfsp-loading-spinner {
                border: 4px solid #f3f3f3;
                border-top: 4px solid #2271b1;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
                margin: 0 auto 20px;
            }

            @keyframes spin {
                0% {
                    transform: rotate(0deg);
                }
                100% {
                    transform: rotate(360deg);
                }
            }

            .wpfsp-loading-message {
                margin-top: 20px;
                color: #50575e;
            }

            .wpfsp-success-icon {
                font-size: 48px;
                margin-bottom: 20px;
            }

            .hidden {
                display: none;
            }

            .wpfsp-error-state {
                padding: 40px;
                max-width: 480px;
                margin: 0 auto;
            }

            .wpfsp-error-icon {
                font-size: 48px;
                margin-bottom: 20px;
            }

            .wpfsp-error-title {
                color: #1d2327;
                margin-bottom: 15px;
            }

            .wpfsp-error-message {
                font-size: 1.1em;
                color: #2c3338;
                margin-bottom: 30px;
                line-height: 1.5;
            }

            .wpfsp-main-actions {
                margin-bottom: 25px;
                text-align: center;
            }

            .wpfsp-main-actions .button {
                min-width: 240px;
                margin-bottom: 10px;
            }

            .wpfsp-action-description {
                color: #50575e;
                font-size: 0.9em;
                margin: 0;
            }

            .wpfsp-support-section {
                margin-top: 40px;
                text-align: center;
                position: relative;
                padding-top: 20px;
            }

            .wpfsp-divider {
                position: relative;
                color: #646970;
                font-size: 0.9em;
                display: block;
                margin-bottom: 15px;
            }

            .wpfsp-divider:before {
                content: '';
                display: block;
                height: 1px;
                background: #dcdcde;
                position: absolute;
                top: -20px;
                left: 50%;
                transform: translateX(-50%);
                width: 60px;
            }

            .wpfsp-thank-you-content .button-link {
                color: #2271b1;
                text-decoration: none;
                border: none;
                background: none;
                padding: 0;
                margin: 0;
                cursor: pointer;
            }

            .wpfsp-thank-you-content .button-link:hover {
                color: #135e96;
                background: none;
                text-decoration: underline;
            }
        </style>

        <script>
            jQuery(document).ready(function ($) {
                const config = <?php echo wp_json_encode( $thank_you_config ); ?>;
                const messageDelay = 2000;
                let currentMessageIndex = 0;

                function updateLoadingMessage() {
                    if (currentMessageIndex < config.checking.messages.length) {
                        $('.wpfsp-loading-title').text(config.checking.title);
                        $('.wpfsp-loading-message').text(config.checking.messages[currentMessageIndex]);
                        currentMessageIndex++;
                        return true;
                    } else {
                        setTimeout(showError, messageDelay);
                        return false;
                    }
                }

                function showSuccess() {
                    $('#loadingState').addClass('hidden');
                    $('#successState').removeClass('hidden');
                    $('.wpfsp-success-title').text(config.success.title);
                    $('.wpfsp-success-message').text(config.success.message);
                    $('#successButton').text(config.success.cta.text).attr('href', config.success.cta.url);
                }

                function showError() {
                    $('#loadingState').addClass('hidden');
                    $('#errorState').removeClass('hidden');
                    $('.wpfsp-error-title').text(config.error.title);
                    $('.wpfsp-error-message').text(config.error.message);
                    $('#supportButton').attr('href', config.error.support_url);
                }

                let messageInterval = null;
                checkStatus();

                function checkStatus() {
                    // Start the loading sequence
                    if (!updateLoadingMessage()) {
                        return;
                    }
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fullstripe_activate_license',
                            nonce: '<?php echo wp_create_nonce( 'check_activation_status' ); ?>',
                            cses: '<?php echo $_GET['cses']; ?>',
                            index: currentMessageIndex,
                        },
                        success: function (response) {
                            if (response.success) {
                                setTimeout(showSuccess, messageDelay);
                                clearInterval(messageInterval);
                            } else {
                                messageInterval = setTimeout(checkStatus, messageDelay);
                            }
                        },
                        error: function () {
                            messageInterval = setTimeout(checkStatus, messageDelay);
                        }
                    });
                }
            });
        </script>
		<?php
	}

	/**
	 * Redirects the user to the pricing URL with a specified state and terminates execution.
	 *
	 * This function performs a redirect to the pricing URL with the given state parameter.
	 * It uses the `tsdk_utmify` function to append the state to the URL and then calls
	 * `wp_redirect` to perform the redirection. The script execution is terminated using `die()`.
	 *
	 * @param string $state The state to append to the pricing URL.
	 */
	public function redirect_on_error( $state ) {
		?>
        <meta http-equiv="refresh"
              content="0; url=<?php echo esc_url( ( tsdk_utmify( MM_WPFS::PRICING_URL, $state ) ) ) ?>">
		<?php
		die();
	}

	/**
	 * Formats a number with grouped thousands and decimal points.
	 *
	 * This function formats a number according to the locale settings, ensuring
	 * that the number is displayed with two decimal places. If the decimal part
	 * is not '00', it wraps the decimal part in a span with a specific class.
	 *
	 * @param float $number The number to format.
	 *
	 * @return string The formatted number as an HTML string.
	 */
	private function number_format( $number ) {
		$number_format = number_format_i18n( $number, 2 );
		global $wp_locale;
		$number_format = explode( $wp_locale->number_format['decimal_point'], $number_format );
		if ( isset( $number_format[1] ) && $number_format[1] !== '00' ) {
			return sprintf( '%s<span class="wpfsp-pricing-dec-sub">.%s</span>', esc_html( $number_format[0] ), esc_html( $number_format[1] ) );
		}

		return esc_html( $number_format[0] );
	}

	/**
	 * Retrieves the Pro Pricing data from the cache or remote API.
	 *
	 * This function attempts to retrieve the Pro Pricing data from the cache.
	 * If the cached data is not available or indicates an error, it fetches the data
	 * from the remote API. The data is then cached for future use.
	 * If any errors occur during the process, the user is redirected to the pricing URL with an error state.
	 *
	 * @return object The Pro Pricing data as a JSON-decoded object.
	 */
	public function get_data() {
		$cache_key   = 'wpfs_pro_pricing_data';
		$cached_data = get_transient( $cache_key );

		if ( $cached_data === $this->error_state ) {
			$this->redirect_on_error( 'pro_price_error' );

		}
		if ( ! empty( $cached_data ) ) {
			if ( ( $json_data = json_decode( $cached_data, true ) ) === false ) {
				$this->redirect_on_error( 'pro_price_jcerror' );
			}

			return $json_data;
		}

		$response = wp_remote_get( add_query_arg( [ 'api_version' => MM_WPFS::get_user_version() ], tsdk_translate_link( 'https://api.themeisle.com/checkout/configs/wpfsp', 'query' ) ) );
		if ( is_wp_error( $response ) ) {
			set_transient( $cache_key, $this->error_state, DAY_IN_SECONDS );
			$this->redirect_on_error( 'pro_price_werror' );
		}
		$json_data = wp_remote_retrieve_body( $response );
		$json_data = json_decode( $json_data, true );
		if ( $json_data === false ) {
			set_transient( $cache_key, $this->error_state, DAY_IN_SECONDS );
			$this->redirect_on_error( 'pro_price_jerror' );
		}
		if ( ! isset( $json_data['plans'] ) ) {
			set_transient( $cache_key, $this->error_state, DAY_IN_SECONDS );
			$this->redirect_on_error( 'pro_price_jstruct' );
		}
		set_transient( $cache_key, wp_json_encode( $json_data ), DAY_IN_SECONDS );

		return $json_data;
	}
	/**
	 * Renders the Pro Pricing page.
	 *
	 * This function checks if a valid nonce and session are present in the URL parameters.
	 * If they are, it captures the upgrade process. Otherwise, it retrieves the pricing
	 * configuration data and displays the Pro Pricing page with various states (loading, success, error).
	 */
	public function render() {

		if ( isset( $_GET['cnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'wp-full-stripe-admin-nonce'  ) !== false && isset( $_GET['cses'] ) && strlen( $_GET['cses'] ) > 20 ) {
			$this->capture_upgrade();

			return;
		}
		$pricing_config = $this->get_data();
		?>

        <style>
            .wpfsp-pricing-container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 40px 20px;
                background: #f0f0f1;
            }

            .wpfsp-pricing-header {
                text-align: center;
                margin-bottom: 50px;
            }

            .wpfsp-pricing-title {
                color: #1d2327;
                font-size: 2.5em;
                margin-bottom: 10px;
            }

            .wpfsp-pricing-dec-sub {
                font-size: 50%;
            }

            .wpfsp-pricing-subtitle {
                color: #50575e;
                font-size: 1.2em;
                max-width: 600px;
                margin: 0 auto 20px;
            }

            .wpfsp-pricing-wrapper {
                display: flex;
                gap: 24px;
                justify-content: center;
                margin-bottom: 10px;
            }

            .wpfsp-price-card {
                flex: 1;
                max-width: 340px;
                background: white;
                transition: transform 0.2s;
                border-radius: 4px;
            }

            .wpfsp-price-card:hover {
                transform: translateY(-5px);
            }

            .wpfsp-price-card.featured {
                position: relative;
                border: 2px solid #2271b1;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            }

            .wpfsp-popular-tag {
                position: absolute;
                top: -12px;
                right: 20px;
                background: #2271b1;
                color: white;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 0.9em;
            }

            .wpfsp-price-header {
                padding: 20px;
                border-bottom: 1px solid #dcdcde;
            }

            .wpfsp-price-title {
                font-size: 1.5em;
                margin: 0;
                color: #1d2327;
            }

            .wpfsp-price-amount {
                font-size: 3em;
                color: #2271b1;
                margin: 20px 0 10px;
            }

            .wpfsp-period {
                font-size: 0.4em;
                color: #646970;
            }

            .wpfsp-price-description {
                color: #50575e;
                margin-bottom: 20px;
            }

            .wpfsp-feature-list {
                margin: 0;
                padding: 20px;
                list-style: none;
            }

            .wpfsp-feature-item {
                margin: 12px 0;
                color: #2c3338;
                padding-left: 25px;
                position: relative;
            }

            .wpfsp-feature-item:before {
                content: "✓";
                position: absolute;
                left: 0;
                color: #2271b1;
            }

            .wpfsp-feature-item.disabled {
                color: #646970;
                text-decoration: line-through;
            }

            .wpfsp-feature-item.disabled:before {
                content: "×";
                color: #646970;
            }

            .wpfsp-button-container {
                padding: 20px;
                text-align: center;
            }

            .wpfsp-pricing-footer {
                text-align: center;
                max-width: 700px;
                margin: 0 auto;
                color: #50575e;
            }

            .wpfsp-guarantee {
                background: #f6f7f7;
                padding: 15px;
                border-radius: 4px;
                margin-top: 30px;
            }

            .wpfsp-original-price {
                text-decoration: line-through;
                color: #646970;
                font-size: 0.5em;
                margin-right: 10px;
            }

            .wpfsp-discount-note {
                text-align: center;
                font-style: italic;
                margin: 2px 0;
                font-size: 0.9em;
            }

            .wpfsp-faq-section {
                max-width: 800px;
                margin: 60px auto 0;
                padding: 0 20px;
            }

            .wpfsp-faq-title {
                text-align: center;
                margin-bottom: 30px;
                color: #1d2327;
            }

            .wpfsp-faq-item {
                margin-bottom: 25px;
            }

            .wpfsp-faq-question {
                font-weight: bold;
                color: #1d2327;
                margin-bottom: 10px;
            }

            .wpfsp-faq-answer {
                color: #50575e;
                line-height: 1.6;
            }
        </style>

        <div class="wpfsp-pricing-container">
            <div class="wpfsp-pricing-header">
                <h1 class="wpfsp-pricing-title"><?php echo esc_html( $pricing_config['header']['title'] ); ?></h1>
                <p class="wpfsp-pricing-subtitle"><?php echo esc_html( $pricing_config['header']['subtitle'] ); ?></p>
            </div>

            <div class="wpfsp-pricing-wrapper">
				<?php foreach ( $pricing_config['plans'] as $plan_key => $plan ): ?>
                    <div class="wpfsp-price-card postbox<?php echo ! empty( $plan['popular'] ) ? ' featured' : ''; ?>">

						<?php if ( isset( $plan['popular'] ) && ! empty( ( $plan['popular'] ) ) ): ?>
                            <div class="wpfsp-popular-tag"><?php echo esc_html( $plan['popular'] ); ?></div>
						<?php endif; ?>
                        <div class="wpfsp-price-header">
                            <h2 class="wpfsp-price-title"><?php echo esc_html( $plan['title'] ); ?></h2>
                            <div class="wpfsp-price-amount">
                                <span class="wpfsp-original-price"><?php echo esc_html( html_entity_decode( $plan['currency'] ) ); ?><?php echo( $this->number_format( $plan['original_price'], 2 ) ); ?></span>
								<?php echo esc_html( html_entity_decode( $plan['currency'] ) ); ?><?php echo( $this->number_format( $plan['price'] ) ); ?>
                                <span class="wpfsp-period">/<?php echo esc_html( $plan['period'] ); ?></span>
                            </div>
                            <div class="wpfsp-price-description"><?php echo esc_html( $plan['description'] ); ?></div>
                        </div>

                        <ul class="wpfsp-feature-list">
							<?php foreach ( $plan['features'] as $feature ): ?>
								<?php if ( is_array( $feature ) ): ?>
                                    <li class="wpfsp-feature-item<?php echo $feature['disabled'] ? ' disabled' : ''; ?>">
										<?php echo esc_html( $feature['text'] ); ?>
                                    </li>
								<?php else: ?>
                                    <li class="wpfsp-feature-item"><?php echo esc_html( $feature ); ?></li>
								<?php endif; ?>
							<?php endforeach; ?>
                        </ul>

                        <div class="wpfsp-button-container">
                            <a href="<?php echo esc_url( add_query_arg( [
								'return_url' => rawurlencode( html_entity_decode( MM_WPFS_Admin_Menu::getAdminUrlBySlug(MM_WPFS_Admin_Menu::SLUG_UPGRADE_PRO) ) )
							], $plan['button']['url'] ) ); ?>"
                               class="button button-primary<?php echo isset( $plan['popular'] ) ? ' button-hero' : ' button-large'; ?>">
								<?php echo esc_html( $plan['button']['text'] ); ?>
                            </a>
                        </div>
                    </div>
				<?php endforeach; ?>
            </div>

            <div class="wpfsp-discount-note">
				<?php echo esc_html( $pricing_config['discount_note'] ); ?>
            </div>
			<?php if ( isset( $pricing_config['footer'] ) ) { ?>
                <div class="wpfsp-pricing-footer">
                    <p><?php echo esc_html( $pricing_config['footer']['text'] ); ?></p>
                    <div class="wpfsp-guarantee">
                        <strong><?php echo esc_html( $pricing_config['footer']['guarantee']['title'] ); ?></strong>
                        <p><?php echo esc_html( $pricing_config['footer']['guarantee']['text'] ); ?></p>
                    </div>
                </div>
			<?php } ?>

            <div class="wpfsp-faq-section">
                <h2 class="wpfsp-faq-title"><?php echo esc_html( $pricing_config['faq']['title'] ); ?></h2>

				<?php foreach ( $pricing_config['faq']['items'] as $faq ): ?>
                    <div class="wpfsp-faq-item">
                        <div class="wpfsp-faq-question"><?php echo esc_html( $faq['question'] ); ?></div>
                        <div class="wpfsp-faq-answer"><?php echo wp_kses( ( $faq['answer'] ), $this->kses_allowed_html(), [
								'http',
								'https'
							] ); ?></div>
                    </div>
				<?php endforeach; ?>
            </div>
        </div>
		<?php
	}

	/**
	 * Returns an array of allowed HTML tags and attributes for use in wp_kses for the pricing plan.
	 *
	 * This function defines a set of HTML tags and attributes that are considered safe
	 * and can be used in the context of the plugin's output.
	 *
	 * @return array An associative array of allowed HTML tags and their attributes.
	 */
	private function kses_allowed_html() {
		$allowed_html = [
			'a'      => [
				'href' => true,
			],
			'br'     => [],
			'p'      => [],
			'em'     => [],
			'strong' => [],
		];

		return $allowed_html;
	}
}