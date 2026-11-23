<?php
use ThemeIsle\WPFullPay\Admin\Views\FormView;

/** @var $view FormView */
/** @var $data */
/** @var $form */
?>
<div class="wpfs-form-group wpfs-form-group--custom-css">
    <div class="wpfs-form-label-with-action">
        <label class="wpfs-form-label">
            <?php $view->stripeElementsCustomCss()->label(); ?>
        </label>
        <a class="wpfs-btn wpfs-btn-link wpfs-btn-link--small" href="<?php echo esc_url( tsdk_utmify( 'https://docs.themeisle.com/wp-full-pay/customizing-forms-with-css', 'custom-css', 'form-editor' ) ); ?>" target="_blank">
            <?php esc_html_e( 'Learn more', 'wp-full-stripe-free'); ?>
        </a>
    </div>
    <textarea
        id="<?php $view->stripeElementsCustomCss()->id(); ?>"
        name="<?php $view->stripeElementsCustomCss()->name(); ?>"
        <?php $view->stripeElementsCustomCss()->attributes(); ?>><?php echo isset( $form->stripeElementsCustomCss ) ? esc_textarea( $form->stripeElementsCustomCss ) : ''; ?></textarea>
    <input name="<?php $view->stripeElementsCustomCssHidden()->name(); ?>" <?php $view->stripeElementsCustomCssHidden()->attributes(); ?> />
    <p class="wpfs-form-help">
        <?php
        printf(
            /* translators: %s is the CSS selector for the form */
            esc_html__( 'Add custom CSS specific to this form only. Use %s to target this form.', 'wp-full-stripe-free' ),
            '<code>' . esc_html( $data->cssSelector ) . '</code>'
        );
        ?>
    </p>
</div>