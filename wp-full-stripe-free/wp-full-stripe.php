<?php
// leave plugin name as is; otherwise it will break the pot files
/*
Plugin Name: WP Full Pay
Plugin URI: https://paymentsplugin.com
Description: Use WP Full Pay to accept Stripe payments on your WordPress. Prebuilt forms to accept payments, donations and subscriptions. 
Author: Themeisle
Version: 8.0.1
Author URI: https://themeisle.com
Text Domain: wp-full-stripe
Domain Path: /languages
Requires License: yes
WordPress Available: yes
*/

//defines

//define( 'WP_FULL_STRIPE_DEMO_MODE', true );

define( 'WP_FULL_STRIPE_MIN_PHP_VERSION', '6.4.0' );
define( 'WP_FULL_STRIPE_MIN_WP_VERSION', '5.0.0' );
define( 'WP_FULL_STRIPE_STRIPE_API_VERSION', '7.24.0' );

define( 'WP_FULL_STRIPE_CRON_SCHEDULES_KEY_15_MIN', '15min' );

if ( ! defined( 'WP_FULL_STRIPE_NAME' ) ) {
	define( 'WP_FULL_STRIPE_NAME', trim( dirname( plugin_basename( __FILE__ ) ), '/' ) );
}

if ( ! defined( 'WP_FULL_STRIPE_BASENAME' ) ) {
	define( 'WP_FULL_STRIPE_BASENAME', __FILE__ );
}

if ( ! defined( 'WP_FULL_STRIPE_DIR' ) ) {
	define( 'WP_FULL_STRIPE_DIR', plugin_dir_path( __FILE__ ) );
}

function wp_full_stripe_load_plugin_textdomain() {
    load_plugin_textdomain( 'wp-full-stripe', false, basename( dirname( __FILE__ ) ) . '/languages/' );
    load_plugin_textdomain( 'wp-full-stripe-admin', false, basename( dirname( __FILE__ ) ) . '/languages/' );
}

function wp_full_stripe_prepare_cron_schedules( $schedules ) {
    if ( ! isset( $schedules[ WP_FULL_STRIPE_CRON_SCHEDULES_KEY_15_MIN ] ) ) {
        $schedules[ WP_FULL_STRIPE_CRON_SCHEDULES_KEY_15_MIN ] = array(
            'interval' => 15 * 60,
            'display'  =>
            /* translators: Textual description of how often a periodic task of the plugin runs */
                __( 'Every 15 minutes', 'wp-full-stripe' )
        );
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
        wpfsShowAdminNotice( sprintf( __( 'PHP version required is %1$s but %2$s found.', 'wp-full-stripe-admin' ), WP_FULL_STRIPE_MIN_PHP_VERSION, PHP_VERSION ));
    }
    if ( ! wpfsIsWordpressCompatible() ) {
        wpfsShowAdminNotice( sprintf( __( 'WordPress version required is %1$s but %2$s found.', 'wp-full-stripe-admin' ), WP_FULL_STRIPE_MIN_WP_VERSION, get_bloginfo( 'version' )));
    }
    if ( ! wpfsIsCurlAvailable() ) {
        wpfsShowAdminNotice( sprintf( __( 'Required PHP extension called "%1$s" is missing.', 'wp-full-stripe-admin' ), 'cURL' ));
    }
    if ( ! wpfsIsMbStringAvailable() ) {
        wpfsShowAdminNotice( sprintf( __( 'Required PHP extension called "%1$s" is missing.', 'wp-full-stripe-admin' ), 'MBString' ));
    }
}

$wpfsDiagCheck = true;
$wpfsDiagCheck = $wpfsDiagCheck && wpfsIsPhpCompatible();
$wpfsDiagCheck = $wpfsDiagCheck && wpfsIsWordpressCompatible();
$wpfsDiagCheck = $wpfsDiagCheck && wpfsIsCurlAvailable();
$wpfsDiagCheck = $wpfsDiagCheck && wpfsIsMbStringAvailable();

if ( $wpfsDiagCheck ) {
    require_once( dirname( __FILE__ ) . '/vendor/autoload.php' );

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

    // We hide the license notice as it is not required for this plugin.
    add_filter( 'wp_full_stripe_free_hide_license_notices', '__return_true', 10, 1 );

    add_filter( 'wp_full_stripe_free_about_us_metadata', function ( $config ) {
        return [
            'location'         => 'wpfs-transactions',
            'logo'             => MM_WPFS_Assets::images( 'wpfs-logo.svg' ),
            'has_upgrade_menu' => ! WPFS_License::is_active(),
            'upgrade_link'     => esc_url( 'https://paymentsplugin.com/pricing/' ),
            'upgrade_text'     => __( 'Get Pro Version', 'wp-full-stripe-admin' ),
        ];
    } );

    if ( ! class_exists( '\StripeWPFS\StripeWPFS' ) ) {
        require_once( dirname( __FILE__ ) . '/includes/stripe/init.php' );
    }

    if ( ! class_exists( 'WPFS_License' ) ) {
        include( dirname( __FILE__ ) . '/includes/wpfs-license.php' );
    }

    require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'wpfs-main.php';
    require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'includes'. DIRECTORY_SEPARATOR
        . 'payment-methods' . DIRECTORY_SEPARATOR . 'functions.php';

    register_activation_hook( __FILE__, array( 'MM_WPFS', 'setup_db' ) );
    register_activation_hook( __FILE__, array('MM_WPFS_CustomerPortalService', 'onActivation' ) );
    register_deactivation_hook( __FILE__, array('MM_WPFS_CustomerPortalService', 'onDeactivation' ) );
    register_activation_hook( __FILE__, array( 'MM_WPFS_CheckoutSubmissionService', 'onActivation' ) );
    register_deactivation_hook( __FILE__, array( 'MM_WPFS_CheckoutSubmissionService', 'onDeactivation' ) );

    \StripeWPFS\StripeWPFS::setAppInfo( 'WP Full Pay', MM_WPFS::VERSION, 'https://paymentsplugin.com', 'pp_partner_FnULHViL0IqHp6' );

    add_action( 'init', 'wp_full_stripe_load_plugin_textdomain' );
    add_filter( 'cron_schedules', 'wp_full_stripe_prepare_cron_schedules' );
} else {
    add_action( 'admin_notices', 'wpfsShowAdminNotices' );
}
