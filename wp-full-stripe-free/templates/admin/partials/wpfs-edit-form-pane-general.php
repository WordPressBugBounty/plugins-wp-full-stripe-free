<?php
use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Main;

/** @var \ThemeIsle\WPFullPay\Forms\FormType $formTypeInfo */
?>
<div class="wpfs-edit-form-pane" data-tab-id="<?php echo Menu::PARAM_VALUE_TAB_GENERAL; ?>">
    <div class="wpfs-form__cols">
        <div class="wpfs-form__col">
            <div class="wpfs-form-block">
                <div class="wpfs-form-block__title"><?php esc_html_e( 'Properties', 'wp-full-stripe-free'); ?></div>
                <?php include( 'wpfs-form-component-display-name.php' ); ?>
            </div>
            <div class="wpfs-form-block">
                <div class="wpfs-form-block__title"><?php esc_html_e( 'Behavior', 'wp-full-stripe-free'); ?></div>
                <?php include( 'wpfs-form-component-redirect-after-payment.php' ); ?>
                <?php if ( Main::FORM_TYPE_SUBSCRIPTION === $formTypeInfo->type ) : ?>
                    <?php include( 'wpfs-form-component-members-upsell.php' ); ?>
                <?php endif; ?>
            </div>
            <?php include( 'wpfs-form-component-action-buttons.php' ); ?>
        </div>
    </div>
</div>
