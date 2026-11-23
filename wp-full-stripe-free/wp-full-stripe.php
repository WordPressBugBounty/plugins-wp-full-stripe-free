<?php
// leave plugin name as is; otherwise it will break the pot files
/*
Plugin Name: WP Full Pay
Plugin URI: https://paymentsplugin.com
Description: Use WP Full Pay to accept Stripe payments on your WordPress. Prebuilt forms to accept payments, donations and subscriptions. 
Author: Themeisle
Version: 8.6.0
Author URI: https://themeisle.com
Text Domain: wp-full-stripe-free
Domain Path: /languages
Requires License: yes
WordPress Available: yes
*/

use ThemeIsle\WPFullPay\Assets;
use ThemeIsle\WPFullPay\Checkout\CheckoutSubmissionService;
use ThemeIsle\WPFullPay\CustomerPortal\CustomerPortalService;
use ThemeIsle\WPFullPay\License;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Subscriptions\FeeSync;

//defines

//define( 'WP_FULL_STRIPE_DEMO_MODE', true );

define( 'WP_FULL_STRIPE_MIN_PHP_VERSION', '6.4.0' );
define( 'WP_FULL_STRIPE_MIN_WP_VERSION', '5.0.0' );
define( 'WP_FULL_STRIPE_STRIPE_API_VERSION', '7.24.0' );

define( 'WP_FULL_STRIPE_CRON_SCHEDULES_KEY_15_MIN', '15min' );

if ( ! defined( 'WP_FULL_STRIPE_NAME' ) ) {
	define( 'WP_FULL_STRIPE_NAME', trim( dirname( plugin_basename( __FILE__ ) ), '/' ) );
    define( 'WP_FULL_STRIPE_PRODUCT_SLUG', dirname( plugin_basename( __FILE__ ) ) );
}

if ( ! defined( 'WP_FULL_STRIPE_BASENAME' ) ) {
	define( 'WP_FULL_STRIPE_BASENAME', __FILE__ );
}

if ( ! defined( 'WP_FULL_STRIPE_DIR' ) ) {
	define( 'WP_FULL_STRIPE_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WP_FULL_STRIPE_PATH' ) ) {
	define( 'WP_FULL_STRIPE_PATH', __DIR__ );
}


function wp_full_stripe_prepare_cron_schedules( $schedules ) {
    if ( ! isset( $schedules[ WP_FULL_STRIPE_CRON_SCHEDULES_KEY_15_MIN ] ) ) {
        $schedules[ WP_FULL_STRIPE_CRON_SCHEDULES_KEY_15_MIN ] = [
            'interval' => 15 * 60,
            'display'  =>
            /* translators: Textual description of how often a periodic task of the plugin runs */
                __( 'Every 15 minutes', 'wp-full-stripe-free' )
        ];
    }

    // WordPress adds this one itself from 5.4.
    if ( ! isset( $schedules['weekly'] ) ) {
        $schedules['weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => __( 'Once Weekly', 'wp-full-stripe-free' ),
        ];
    }

    return $schedules;
}

function wpfsShowAdminNotice( $message ) {
    echo "<div class='notice notice-error'><p><b>WP Full Pay error</b>: {$message}</p></div>";
}

function wpfsIsPhpCompatible() {
    return version_compare( PHP_VERSION, WP_FULL_STRIPE_MIN_PHP_VERSION ) >= 0;
}

function wpfsIsWordpressCompatible() {
    return version_compare( get_bloginfo( 'version' ), WP_FULL_STRIPE_MIN_WP_VERSION ) >= 0;
}

function wpfsIsCurlAvailable() {
    return extension_loaded( 'curl' );
}

function wpfsIsMbStringAvailable() {
    return extension_loaded( 'mbstring' );
}

function wpfsShowAdminNotices() {
     if ( ! wpfsIsPhpCompatible() ) {
         /* translators: %1$s is the required PHP version and %2$s is the currently installed version. */
         wpfsShowAdminNotice( sprintf( __( 'PHP version required is %1$s but %2$s found.', 'wp-full-stripe-free' ), WP_FULL_STRIPE_MIN_PHP_VERSION, PHP_VERSION ));
     }
     if ( ! wpfsIsWordpressCompatible() ) {
          /* translators: %1$s is the required PHP version and %2$s is the currently installed version. */
         wpfsShowAdminNotice( sprintf( __( 'WordPress version required is %1$s but %2$s found.', 'wp-full-stripe-free' ), WP_FULL_STRIPE_MIN_WP_VERSION, get_bloginfo( 'version' )));
     }
     if ( ! wpfsIsCurlAvailable() ) {
         /* translators: %1$s is the PHP extension name. */
         wpfsShowAdminNotice( sprintf( __( 'Required PHP extension called "%1$s" is missing.', 'wp-full-stripe-free' ), 'cURL' ));
     }
     if ( ! wpfsIsMbStringAvailable() ) {
         /* translators: %1$s is the PHP extension name. */
         wpfsShowAdminNotice( sprintf( __( 'Required PHP extension called "%1$s" is missing.', 'wp-full-stripe-free' ), 'MBString' ));
     }
 }

$wpfsDiagCheck = true;
$wpfsDiagCheck = $wpfsDiagCheck && wpfsIsPhpCompatible();
$wpfsDiagCheck = $wpfsDiagCheck && wpfsIsWordpressCompatible();
$wpfsDiagCheck = $wpfsDiagCheck && wpfsIsCurlAvailable();
$wpfsDiagCheck = $wpfsDiagCheck && wpfsIsMbStringAvailable();

if ( $wpfsDiagCheck ) {
    require_once( dirname( __FILE__ ) . '/vendor/autoload.php' );
    require_once( dirname( __FILE__ ) . '/includes/stripe/autoload.php' );
    require_once( dirname( __FILE__ ) . '/inc/bootstrap.php' );

    add_filter(
        'themeisle_sdk_products',
        function ( $products ) {
            $products[] = __FILE__;
    
            return $products;
        }
    );

    add_filter(
        'themesle_sdk_namespace_' . md5( __FILE__ ),
        function () {
            return 'wpfs';
        }
    );


    $namespace = License::get_namespace();

    // We hide the license notice as it is not required for this plugin.
    add_filter( $namespace . '_hide_license_notices', '__return_true', 10, 1 );
    add_action( 'admin_notices', [ License::class, 'maybe_render_fee_fallback_notice' ] );
    add_filter( $namespace . '_hide_license_field', '__return_true' );

    add_filter( $namespace . '_about_us_metadata', function ( $config ) {
        return [
            'location'         => 'wpfs-transactions',
            'logo'             => Assets::images( 'wpfs-logo.svg' ),
            'has_upgrade_menu' => ! License::is_active(),
            'upgrade_link'     => tsdk_utmify( 'https://paymentsplugin.com/pricing/' ,'admin-menu'),
            'upgrade_text'     => __( 'Get Pro Version', 'wp-full-stripe-free' ),
        ];
    } );

    add_filter( $namespace . '_ai_connect_metadata', function () {
        return [
            'name'         => 'WP Full Pay',
            'notice_cases' => [
                __( 'create payment forms', 'wp-full-stripe-free' ),
                __( 'look up payments', 'wp-full-stripe-free' ),
                __( 'review your subscriptions', 'wp-full-stripe-free' ),
            ],
            'prompts'      => [
                __( 'Create an inline donation form called "Spring fundraiser" in WP Full Pay and add it to my Donate page.', 'wp-full-stripe-free' ),
                __( 'How much did each form take this month?', 'wp-full-stripe-free' ),
                __( 'Which subscriptions failed to renew this month?', 'wp-full-stripe-free' ),
            ],
            'ability_prefix'    => 'fullpay',
        ];
    } );

    /*
     * Opt into the SDK Support Hub: a Help button on WP Full Pay's own screens and
     * the deactivation window, so free users can message support instead of only
     * the forum. WP Full Pay already fires `themeisle_internal_page` on its screens
     * (Admin\Menu), so no `pages` list is needed. The Pro flag reads the stored
     * license so Pro messages are tagged correctly.
     */
    add_filter( $namespace . '_support_hub_metadata', function () {
        return [
            'is_pro' => License::is_active(),
            'links'  => [
                [
                    'icon' => 'book-alt',
                    'text' => __( 'Documentation', 'wp-full-stripe-free' ),
                    'url'  => tsdk_utmify( 'https://docs.themeisle.com/collection/2079-wp-full-pay', 'support-hub' ),
                ],
                [
                    'icon' => 'groups',
                    'text' => __( 'Community Forum', 'wp-full-stripe-free' ),
                    'url'  => 'https://wordpress.org/support/plugin/wp-full-stripe-free/',
                ],
            ],
        ];
    } );

    Main::getInstance();
    require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'includes'. DIRECTORY_SEPARATOR
        . 'payment-methods' . DIRECTORY_SEPARATOR . 'functions.php';

    register_activation_hook( __FILE__, [ Main::class, 'setup_db' ] );
    register_activation_hook( __FILE__, [CustomerPortalService::class, 'onActivation' ] );
    register_deactivation_hook( __FILE__, [CustomerPortalService::class, 'onDeactivation' ] );
    register_activation_hook( __FILE__, [ CheckoutSubmissionService::class, 'onActivation' ] );
    register_deactivation_hook( __FILE__, [ CheckoutSubmissionService::class, 'onDeactivation' ] );
    register_deactivation_hook( __FILE__, [ FeeSync::class, 'onDeactivation' ] );

    \StripeWPFS\Stripe\Stripe::setAppInfo( 'WP Full Pay', Main::VERSION, 'https://paymentsplugin.com', 'pp_partner_FnULHViL0IqHp6' );

    add_filter( 'cron_schedules', 'wp_full_stripe_prepare_cron_schedules' );
    add_filter( 'themeisle_sdk_enable_telemetry', '__return_true' );
    add_filter( 'themeisle_sdk_telemetry_products', function ( $products ) {
        if ( is_array( $products ) ) {
            foreach ( $products as $key => &$product ) {
                if ( isset( $product['slug'] ) && $product['slug'] === 'wp' ) {
                    $product['slug'] = 'wp_full_stripe';
                }
            }
        }
        return $products;
    } );
} else {
    add_action( 'admin_notices', 'wpfsShowAdminNotices' );
}
