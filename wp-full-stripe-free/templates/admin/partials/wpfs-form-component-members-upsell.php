<?php
/**
 * Members add-on upsell notice component
 * Only shows if WP Full Members add-on is not active.
 */

$plugin_url = tsdk_utmify( 'https://paymentsplugin.com/wp-full-members-addon/', 'behavior-upsell' );
if ( ! defined( 'WPFS_MEMBERS_BASENAME' ) ) : ?>
<div class="wpfs-members-upsell">
    <h4 class="wpfs-form-label">
        <?php esc_html_e( 'Additional Actions', 'wp-full-stripe-free' ); ?>
    </h4>
    <div class="wpfs-form-help">
        <span class="dashicons dashicons-admin-users"></span>
        <?php esc_html_e( 'Automatically create users on subscription with our Members feature.', 'wp-full-stripe-free' ); ?>
        <a class="wpfs-btn wpfs-btn-link" href="<?php echo esc_url( $plugin_url ); ?>" target="_blank">
            <?php esc_html_e( 'Learn more', 'wp-full-stripe-free' ); ?>
        </a>
    </div>
</div>
<?php endif; ?>
