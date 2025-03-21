<?php

// todo tnagy should we add google ads parameters to the url?

$purchasePluginUrl = tsdk_utmify(
	'https://paymentsplugin.com/pricing', 'demo'
);

?>
<?php if ( MM_WPFS_Utils::isDemoMode() ): ?>
    <div class="wpfs-demo-message js-demo-message">
        <div class="wpfs-illu-flash"></div>
        <div class="wpfs-demo-message__message"><?php esc_html_e( 'WP Full Pay is running in Demo Mode.', 'wp-full-stripe-free' ); ?></div>
        <a class="wpfs-demo-message__link" href="#"><?php esc_html_e( 'Learn more', 'wp-full-stripe-free' ); ?></a>
    </div>
    <div id="wpfs-demo-dialog-container" class="wpfs-dialog-content wpfs-dialog-demo"
         title="<?php esc_attr_e( 'WP Full Pay Demo Mode', 'wp-full-stripe-free' ); ?>">
        <div class="wpfs-dialog-scrollable">
            <p><?php esc_html_e( 'The plugin is running in Demo Mode which means the following restrictions:', 'wp-full-stripe-free' ); ?></p>
            <ul>
                <li><?php esc_html_e( 'Plugin displays placeholders in place of sensitive credentials (API keys)', 'wp-full-stripe-free' ); ?></li>
                <li><?php esc_html_e( 'Plugin pretends to save modifications but it scraps them', 'wp-full-stripe-free' ); ?></li>
                <li><?php esc_html_e( 'Plugin pretends to delete entities but it does nothing', 'wp-full-stripe-free' ); ?></li>
            </ul>
        </div>
        <div class="wpfs-inline-message wpfs-inline-message--info wpfs-inline-message--dialog">
            <div class="wpfs-inline-message__inner">
                <div class="wpfs-dialog-demo__title"><?php esc_html_e( 'Give the plugin a try', 'wp-full-stripe-free' ); ?></div>
                <p><?php _e( 'Try the full-featured plugin with our <strong>14 day money back guarantee</strong>.', 'wp-full-stripe-free' ); ?></p>
                <a class="wpfs-btn wpfs-btn-primary" target="_blank"
                   href="<?php echo $purchasePluginUrl; ?>"><?php esc_html_e( 'Purchase plugin', 'wp-full-stripe-free' ); ?></a>
            </div>
        </div>
    </div>
<?php endif; ?>

