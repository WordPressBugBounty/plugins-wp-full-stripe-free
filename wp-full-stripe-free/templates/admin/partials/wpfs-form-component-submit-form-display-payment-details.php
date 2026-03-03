<?php
    /** @var $view MM_WPFS_Admin_FormView */
    /** @var $form */
?>
<div class="wpfs-form-group">
    <label for="<?php $view->paymentDetail()->id(); ?>" class="wpfs-form-label"><?php $view->paymentDetail()->label(); ?></label>
    <div class="wpfs-ui wpfs-form-select wpfs-page-controls__control wpfs-submit-form-display-payment-details">
        <select class="js-selectmenu js-form-list-mode-filter" name="<?php $view->paymentDetail()->name(); ?>" <?php $view->paymentDetail()->attributes(); ?>" id="<?php $view->paymentDetail()->id(); ?>">
            <option value="1" <?php selected( $form->showPaymentDetail, 1 ); ?>><?php esc_html_e( 'Yes', 'wp-full-stripe-free' ); ?></option>
            <option value="0" <?php selected( $form->showPaymentDetail, 0 ); ?>><?php esc_html_e( 'No', 'wp-full-stripe-free' ); ?></option>
        </select>
    </div>
</div>
