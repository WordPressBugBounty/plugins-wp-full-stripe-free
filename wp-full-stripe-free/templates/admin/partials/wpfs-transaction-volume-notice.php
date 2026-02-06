<?php
/**
 * Transaction Volume Notice Template
 *
 */

// Don't show if user already has a valid license
if ( WPFS_License::is_active() ) {
	return;
}

$options = new MM_WPFS_Options();

// Check if notice has been dismissed
if ( $options->get( MM_WPFS_Options::OPTION_TRANSACTION_VOLUME_NOTICE ) ) {
	return;
}

// Check if transaction volume threshold is exceeded for USD or EUR (with caching)
$db = new MM_WPFS_Database();
if ( ! $db->requiresVolumeWarning() ) {
	return;
}

$upgrade_url = tsdk_utmify( 'https://paymentsplugin.com/pricing/', 'transaction-volume-notice' );
$nonce = wp_create_nonce( 'wp-full-stripe-admin-nonce' );
$ajax_url = admin_url( 'admin-ajax.php' );

$user_version = get_option( 'wpfp_user_v1', 'no' );  
$commission   = 'yes' === $user_version ? '1.9%' : '5%';  

$notice_message = sprintf(
	// translators: %1$s is the name of the paid plan (Plus), %2$s is the fee percentage (1.9% or 5%).
	__( 'Based on your transaction volume, upgrading to our %1$s plan could save you money by eliminating the %2$s transaction fee.', 'wp-full-stripe-free' ),
	// translators: the name of the paid plan.
	_x( 'Plus', 'Paid plan name' , 'wp-full-stripe-free' ),
	$commission
);
?>

<div class="wpfs-transaction-volume-notice wpfs-inline-message wpfs-inline-message--info " data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ajax-url="<?php echo esc_url( $ajax_url ); ?>">
	<div class="wpfs-inline-message__inner">
		<span class="dashicons dashicons-info"></span>
		<div class="wpfs-inline-message__content">
			<?php 
			echo esc_html( $notice_message );
			?> 
			<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer" class="wpfs-btn wpfs-btn-link">
				<strong><?php esc_html_e( 'Upgrade now', 'wp-full-stripe-free' ); ?></strong>
			</a>
		</div>
		<div class="wpfs-transaction-volume-notice__actions">
			<button type="button" class="wpfs-inline-message__close js-dismiss-transaction-volume-notice button button-link">
				<span class="dashicons dashicons-no-alt"></span>
				<span class="screen-reader-text">
					<?php esc_html_e( 'Dismiss this notice.', 'wp-full-stripe-free' ); ?>
				</span>
			</button>
		</div>
	</div>
</div>

<script>
(function() {
	'use strict';
	
	const notice = document.querySelector('.wpfs-transaction-volume-notice');
	if (!notice) return;

	const dismissButton = notice.querySelector('.js-dismiss-transaction-volume-notice');
	if (!dismissButton) return;

	dismissButton.addEventListener('click', function(e) {
		e.preventDefault();

		const nonce = notice.getAttribute('data-nonce');
		const ajaxUrl = notice.getAttribute('data-ajax-url');
		
		// Hide notice immediately.
		notice.style.transition = 'opacity 0.3s';
		notice.style.opacity = '0';
		setTimeout(() => notice.remove(), 300);

		fetch(ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded'
			},
			body: new URLSearchParams({
				action: 'wpfs-dismiss-transaction-volume-notice',
				nonce: nonce
			})
		})
		.catch(error => {
			console.error('Error dismissing notice:', error);
		});
	});
})();
</script>
