<?php
/** @var $view MM_WPFS_Admin_FormView */
/** @var $form */
/** @var $data */
?>
<div class="wpfs-form-group">
    <label class="wpfs-form-label"><?php esc_html_e( "Customer data", 'wp-full-stripe-free' ); ?></label>
    <?php include( 'wpfs-form-component-billing-shipping-address.php' ); ?>
    <?php include( 'wpfs-form-component-phone-number.php' ); ?>
</div>
