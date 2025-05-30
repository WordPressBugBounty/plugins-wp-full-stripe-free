<?php
    /** @var $data */
?>
<div class="wpfs-inline-message wpfs-inline-message--info wpfs-inline-message--w448">
    <div class="wpfs-inline-message__inner">
        <div class="wpfs-inline-message__title"><?php esc_html_e( 'Create one-time products', 'wp-full-stripe-free'); ?></div>
        <?php
            $hint = sprintf( __( 'You can create one-time products on the <a href="%s" target="_blank">Stripe dashboard</a>.', 'wp-full-stripe-free'), MM_WPFS_Admin::buildStripeProductsUrlStatic( $data->stripeApiModeInteger ));
        ?>
        <p><?php echo $hint; ?></p>
    </div>
</div>
