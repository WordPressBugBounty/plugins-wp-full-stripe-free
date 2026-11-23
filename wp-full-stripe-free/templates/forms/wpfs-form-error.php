<?php
    /** @var $ex Exception */
    if ( current_user_can( 'administrator' ) ) {
        $this->fullstripe_load_css();
        $errorMessage = $ex->getMessage();
        if ( $ex instanceof \ThemeIsle\WPFullPay\Exceptions\StripeTaxBehaviorException ) {
            $errorMessage = \ThemeIsle\WPFullPay\Exceptions\StripeTaxBehaviorException::getAdminMessage();
        } elseif ( strpos( $errorMessage, 'Invalid API Key provided' ) !== false ) {
            $errorMessage = __( 'Invalid API Key provided. Please connect your Stripe account in the WP Full Stripe settings.', 'wp-full-stripe-free' );
        } elseif ( strpos( $errorMessage, 'No such price' ) !== false ) {
            $errorMessage = __( 'No such price found. Please select the correct price from the WP Full Stripe settings.', 'wp-full-stripe-free' );
        }
    } elseif ( $ex instanceof \ThemeIsle\WPFullPay\Exceptions\StripeTaxBehaviorException ) {
        $errorMessage = $ex->getMessage();
    } else {
        $errorMessage = __( 'An error occurred. Please contact the website administrator.', 'wp-full-stripe-free' );
    }
?>
<form class="wpfs-form wpfs-w-60">
    <div class="wpfs-form-message wpfs-form-message--notice">
        <div class="wpfs-form-message-title"><?php esc_html_e( 'WP Full Pay shortcode error', 'wp-full-stripe-free' ); ?></div>
        <?php echo esc_html( $errorMessage ); ?>

        <?php if ( current_user_can( 'administrator' ) ): ?>
            <div class="wpfs-form-actions">
                <?php if ( $ex instanceof \ThemeIsle\WPFullPay\Exceptions\StripeTaxBehaviorException ): ?>
                    <a target="_blank" rel="noopener noreferrer" class="wpfs-btn wpfs-btn-primary wpfs-mr-2" href="<?php echo esc_url( \ThemeIsle\WPFullPay\Admin\StripeTaxBehaviorNotice::getStripeTaxSettingsUrl() ); ?>"><?php esc_html_e( 'Open Stripe Tax settings', 'wp-full-stripe-free' ); ?></a>
                <?php else: ?>
                    <a target="_blank" class="wpfs-btn wpfs-btn-primary wpfs-mr-2" href="<?php echo esc_url( admin_url( 'admin.php?page=wpfs-settings-stripe' ) ); ?>"><?php esc_html_e( 'Go to Settings', 'wp-full-stripe-free' ); ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</form>
