<?php

namespace ThemeIsle\WPFullPay\Admin;

use ThemeIsle\WPFullPay\Exceptions\StripeTaxBehaviorException;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Utils;

/**
 * Tells the site admin how to fix Stripe Tax refusing a form's prices (#795), until they dismiss it. A form priced
 * since doesn't clear it, as Stripe Tax may still refuse another form's prices.
 */
class StripeTaxBehaviorNotice {

	/**
	 * Each API mode's refusal is an option of its own, so recording one mode never overwrites the other's dismissal.
	 */
	const OPTION_PREFIX = 'wpfs_stripe_tax_behavior_refused_';

	const MODES = [ Main::STRIPE_API_MODE_TEST, Main::STRIPE_API_MODE_LIVE ];

	const AJAX_DISMISS = 'wpfs-dismiss-stripe-tax-behavior-notice';

	/**
	 * @param string $apiMode The API mode Stripe Tax refused the prices in.
	 */
	public static function recordRefusal( string $apiMode ): void {
		// Every visit to a refused form ends here; add_option() writes only when the mode has no refusal recorded.
		add_option( self::OPTION_PREFIX . $apiMode, '1' );
	}

	/**
	 * @param 'test'|'live' $apiMode The mode of the notice the admin dismissed.
	 */
	public static function dismiss( string $apiMode ): void {
		delete_option( self::OPTION_PREFIX . $apiMode );
	}

	/**
	 * @return string The Stripe Tax settings in the Stripe Dashboard, for the site's API mode.
	 */
	public static function getStripeTaxSettingsUrl(): string {
		return Main::STRIPE_API_MODE_LIVE === ( new Options() )->get( Options::OPTION_API_MODE )
			? 'https://dashboard.stripe.com/settings/tax'
			: 'https://dashboard.stripe.com/test/settings/tax';
	}

	/**
	 * admin_notices: shown while the site uses an API mode Stripe Tax refused prices in.
	 */
	public static function display(): void {
		if ( ! current_user_can( 'manage_options' ) || Utils::isDemoMode() ) {
			return;
		}

		$apiMode = (string) ( new Options() )->get( Options::OPTION_API_MODE );
		if ( ! get_option( self::OPTION_PREFIX . $apiMode ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible wpfs-stripe-tax-behavior-notice" data-mode="%7$s" data-nonce="%1$s" data-ajax-url="%2$s"><p><strong>%3$s</strong> %4$s <a href="%5$s" target="_blank" rel="noopener noreferrer">%6$s</a></p></div>',
			esc_attr( wp_create_nonce( 'wp-full-stripe-admin-nonce' ) ),
			esc_url( admin_url( 'admin-ajax.php' ) ),
			/* translators: Title of the admin notice shown after Stripe Tax refused the prices of a form */
			esc_html__( "WP Full Pay: Stripe Tax can't price some of your forms.", 'wp-full-stripe-free' ),
			esc_html( StripeTaxBehaviorException::getAdminMessage() ),
			esc_url( self::getStripeTaxSettingsUrl() ),
			/* translators: Link to the Stripe Tax settings in the Stripe Dashboard */
			esc_html__( 'Open Stripe Tax settings', 'wp-full-stripe-free' ),
			esc_attr( $apiMode )
		);
		?>
		<script>
		document.addEventListener( 'click', function ( event ) {
			const notice = event.target.closest( '.wpfs-stripe-tax-behavior-notice' );
			if ( ! notice || ! event.target.closest( '.notice-dismiss' ) ) {
				return;
			}

			fetch( notice.dataset.ajaxUrl, {
				method: 'POST',
				body: new URLSearchParams( { action: '<?php echo esc_js( self::AJAX_DISMISS ); ?>', nonce: notice.dataset.nonce, mode: notice.dataset.mode } ),
			} );
		} );
		</script>
		<?php
	}
}
