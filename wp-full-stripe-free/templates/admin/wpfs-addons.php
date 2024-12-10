<?php
    $link = tsdk_utmify( 'https://paymentsplugin.com/wp-full-members-addon/', 'admin-addons' );
?>
<div class="wrap">
    <div class="wpfs-page wpfs-page-payment-forms">
        <?php include('partials/wpfs-header-with-back-link.php'); ?>
        <?php include('partials/wpfs-announcement.php'); ?>
    </div>

<div class="wpfs-form">
    <div class="wpfs-form__cols">
        <div class="wpfs-form__col">
            <div class="wpfs-add-ons-explorer">
                <div class="wpfs-add-ons-explorer__item">
                    <img class="wpfs-add-ons-explorer__image" src="<?php echo MM_WPFS_Assets::images('members-addons.png'); ?>"/>
                    <div class="wpfs-add-ons-explorer__header">
                        <div class="wpfs-add-ons-explorer__title"><?php _e( 'WP Full Stripe Members', 'wp-full-stripe-admin' ) ?></div>
                        <?php if ( defined( 'WPFS_MEMBERS_BASENAME' ) ) : ?>
                            <span><?php _e( 'Installed', 'wp-full-stripe-admin' ) ?></span>
                        <?php else : ?>
                            <a target="_blank" href="<?php echo esc_url( $link ); ?>" class="button"><?php _e( 'More Details', 'wp-full-stripe-admin' ) ?></a>
                        <?php endif; ?>
                    </div>
                    <p class="wpfs-add-ons-explorer__desc"><?php _e( 'Make money from your WordPress website by creating subscriber only content!', 'wp-full-stripe-admin' ) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
	<?php include( 'partials/wpfs-demo-mode.php' ); ?>
</div>
