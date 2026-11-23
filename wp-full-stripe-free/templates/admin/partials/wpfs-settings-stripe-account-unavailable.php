<?php
/**
 * Shown instead of the account status when the platform API could not be asked about the
 * connected account. The site stays connected; disconnecting is left to the merchant.
 *
 * @var string $accountMode     'test' or 'live'.
 * @var bool   $accessRevocable Whether the Disconnect dialog offers removing WP Full Pay's access.
 */
?>
<div class="wpfs-inline-message wpfs-inline-message--warning wpfs-inline-message--w448 wpfs-account-unavailable">
    <div class="wpfs-inline-message__inner">
        <label class="wpfs-form-label"><?php esc_html_e( 'Connection Status', 'wp-full-stripe-free' ); ?></label>
        <p><?php esc_html_e( 'WP Full Pay can\'t check this Stripe account right now, so this site stays connected to it. Reload the page to try again. If you removed WP Full Pay\'s access in Stripe, disconnect the site.', 'wp-full-stripe-free' ); ?></p>
        <p>
            <button class="wpfs-btn-secondary wpfs-button-loader" id="wpfs-clear-<?php echo esc_attr( $accountMode ); ?>-stripe-connect-account" data-can-revoke="<?php echo $accessRevocable ? '1' : '0'; ?>">
                <?php esc_html_e( 'Disconnect', 'wp-full-stripe-free' ); ?>
            </button>
        </p>
    </div>
</div>
