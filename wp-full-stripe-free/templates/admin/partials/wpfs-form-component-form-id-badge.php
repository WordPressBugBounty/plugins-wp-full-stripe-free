<?php
use ThemeIsle\WPFullPay\Main;

/** @var \ThemeIsle\WPFullPay\Forms\FormType $formTypeInfo */
/** @var $form */
?>
<div class="wpfs-form-block__title-with-badge">
<?php if ( Main::FORM_LAYOUT_INLINE === $formTypeInfo->layout ) : ?>
    <span class="wpfs-form-block__title"><?php esc_html_e( 'Form Appearance', 'wp-full-stripe-free' ); ?></span>
    <span class="wpfs-form-id-badge">
        <?php esc_html_e( 'Form ID:', 'wp-full-stripe-free' ); ?> <strong><?php echo esc_html( $form->name ); ?></strong>
        <a class="wpfs-btn wpfs-btn-link wpfs-btn-link--small js-copy-form-id" data-form-id="<?php echo esc_attr( $form->name ); ?>"><?php esc_html_e( 'Copy', 'wp-full-stripe-free' ); ?></a>
    </span>
<?php else : ?>
    <span class="wpfs-form-block__title"><?php esc_html_e( 'Form ID', 'wp-full-stripe-free' ); ?></span>
    <span class="wpfs-form-id-badge">
        <strong><?php echo esc_html( $form->name ); ?></strong>
        <a class="wpfs-btn wpfs-btn-link wpfs-btn-link--small js-copy-form-id" data-form-id="<?php echo esc_attr( $form->name ); ?>"><?php esc_html_e( 'Copy', 'wp-full-stripe-free' ); ?></a>
    </span>
<?php endif; ?>
</div>
