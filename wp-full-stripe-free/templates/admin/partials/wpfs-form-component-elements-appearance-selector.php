<?php
/** @var $view MM_WPFS_Admin_PaymentFormView|MM_WPFS_Admin_SubscriptionFormView|MM_WPFS_Admin_DonationFormView|MM_WPFS_Admin_SaveCardFormView */
/** @var $form */


$currentThemeValue = $form->stripeElementsTheme;
?>
<div class="wpfs-appearance-subsection" id="stripe-elements-theme-selector">
    <label class="wpfs-form-label"><?php $view->stripeElementsThemeSelector()->label(); ?></label>
    <div class="wpfs-theme-grid">
    <?php foreach ( $view->stripeElementsThemeSelector()->options() as $option ) {
        /* @var $option MM_WPFS_Control */
        ?>
        <label class="wpfs-theme-option" for="<?php $option->id(); ?>" title="<?php echo esc_attr( $option->label() ); ?>">
            <input id="<?php $option->id(); ?>" name="<?php $option->name(); ?>" value="<?php $option->value(); ?>" <?php $option->attributes(); ?> <?php echo $option->value(false) == $currentThemeValue ? 'checked' : ''; ?>/>
            <div class="wpfs-theme-preview">
                <span class="<?php echo $option->metadata()['iconClass']; ?>"></span>
            </div>
        </label>
    <?php } ?>
    </div>
</div>

<div class="wpfs-appearance-subsection">
    <label for="<?php echo esc_attr( $view->stripeElementsFont()->id() ); ?>" class="wpfs-form-label">
        <?php $view->stripeElementsFont()->label(); ?>
    </label>
    <div class="wpfs-form-group">
        <input id="<?php $view->stripeElementsFont()->id(); ?>" name="<?php $view->stripeElementsFont()->name(); ?>" type="text" class="wpfs-form-control js-to-pascal-case" value="<?php echo esc_attr( $form->stripeElementsFont ); ?>" placeholder="<?php esc_attr_e( 'e.g., Inter, Helvetica, sans-serif', 'wp-full-stripe-free' ); ?>" data-to-pascal-case="#<?php $view->name()->id(); ?>">
        <p class="wpfs-form-help"><?php esc_html_e( 'Specify font family for Stripe payment elements', 'wp-full-stripe-free' ); ?></p>
    </div>
</div>
