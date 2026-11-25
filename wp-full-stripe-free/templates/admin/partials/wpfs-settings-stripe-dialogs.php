<?php
use ThemeIsle\WPFullPay\Admin\StripeAccessRevocation;

/**
 * The Disconnect dialog of the Stripe settings page. Every tab has it, because the confirm prompt above the
 * tabs can disconnect a mode too.
 */
?>
<div id="wpfs-dialog-container"></div>
<script type="text/template" id="wpfs-modal-disconnect-stripe">
    <div class="wpfs-dialog-scrollable">
        <p class="wpfs-dialog-content-text"><?php esc_html_e( 'This site will stop using this Stripe account. Your forms will not take payments until you connect again.', 'wp-full-stripe-free' ); ?></p>
        <% if ( canRevoke ) { %>
        <div class="wpfs-form-check">
            <input class="wpfs-form-check-input js-revoke-stripe-access" id="wpfs-revoke-stripe-access" type="checkbox">
            <label class="wpfs-form-check-label" for="wpfs-revoke-stripe-access"><?php esc_html_e( 'Also remove WP Full Pay\'s access in Stripe', 'wp-full-stripe-free' ); ?></label>
        </div>
        <div class="wpfs-inline-message wpfs-inline-message--warning js-revoke-stripe-access-warning" style="display: none;">
            <div class="wpfs-inline-message__inner">
                <p><?php esc_html_e( 'You will sign in on Stripe to confirm. Removing access stops payments on every site that uses this Stripe account with WP Full Pay, not just this one.', 'wp-full-stripe-free' ); ?></p>
            </div>
        </div>
        <% } else { %>
        <p class="wpfs-dialog-content-text js-revoke-stripe-access-unavailable">
            <?php
            echo sprintf(
                /* translators: %1$s: opening anchor tag, %2$s: closing anchor tag */
                esc_html__( 'Disconnecting does not remove WP Full Pay from your Stripe account. %1$sSee how to remove it%2$s.', 'wp-full-stripe-free' ),
                '<a href="' . esc_url( tsdk_utmify( StripeAccessRevocation::DOCS_URL, 'revoke-access', 'settings' ) ) . '" target="_blank" rel="noopener">',
                '</a>'
            );
            ?>
        </p>
        <% } %>
    </div>
    <div class="wpfs-dialog-content-actions">
        <button class="wpfs-btn wpfs-btn-danger js-disconnect-stripe-dialog"><?php esc_html_e( 'Disconnect', 'wp-full-stripe-free' ); ?></button>
        <button class="wpfs-btn wpfs-btn-text js-close-this-dialog"><?php esc_html_e( 'Keep connected', 'wp-full-stripe-free' ); ?></button>
    </div>
</script>
<script type="text/template" id="wpfs-modal-disconnect-stripe-in-progress">
    <div class="wpfs-dialog-scrollable">
        <p class="wpfs-dialog-content-text"><?php esc_html_e( 'This site will stop using this Stripe account. Your forms will not take payments until you connect again.', 'wp-full-stripe-free' ); ?></p>
    </div>
    <div class="wpfs-dialog-content-actions">
        <button class="wpfs-btn wpfs-btn-danger wpfs-btn-danger--loader" disabled><?php esc_html_e( 'Disconnect', 'wp-full-stripe-free' ); ?></button>
    </div>
</script>
<script type="text/template" id="wpfs-modal-dialog-error">
    <div class="wpfs-dialog-scrollable">
        <div class="wpfs-inline-message wpfs-inline-message--error">
            <div class="wpfs-inline-message__inner">
                <strong><%- errorMessage %></strong>
            </div>
        </div>
    </div>
    <div class="wpfs-dialog-content-actions">
        <a class="wpfs-btn wpfs-btn-text js-close-this-dialog"><?php esc_html_e( 'Close', 'wp-full-stripe-free' ); ?></a>
    </div>
</script>
