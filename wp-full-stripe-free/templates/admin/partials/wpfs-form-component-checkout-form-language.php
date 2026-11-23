<?php
use ThemeIsle\WPFullPay\Admin\Views\CheckoutFormView;
use ThemeIsle\WPFullPay\Main;

/** @var $view CheckoutFormView */
/** @var $form */
/** @var $data */
?>
<div class="wpfs-form-group">
    <label for="<?php $view->checkoutLanguage()->id(); ?>" class="wpfs-form-label"><?php $view->checkoutLanguage()->label(); ?></label>
    <div class="wpfs-ui wpfs-form-select">
        <select id="<?php $view->checkoutLanguage()->id(); ?>" name="<?php $view->checkoutLanguage()->name(); ?>" <?php $view->checkoutLanguage()->attributes(); ?>>
            <option value="<?php echo Main::PREFERRED_LANGUAGE_AUTO; ?>"><?php esc_html_e( 'Auto', 'wp-full-stripe-free' ); ?></option>
            <?php foreach ( $data->checkoutFormLanguages as $language ) { ?>
                <option value="<?php echo $language['value']; ?>" <?php echo $language['value'] === $form->preferredLanguage ? 'selected': ''; ?>><?php echo esc_html( $language['name'] ); ?></option>
            <?php } ?>
        </select>
    </div>
</div>
