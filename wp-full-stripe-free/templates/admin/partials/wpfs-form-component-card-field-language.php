<?php
use ThemeIsle\WPFullPay\Admin\Views\InlineFormView;
use ThemeIsle\WPFullPay\Main;

    /** @var $view InlineFormView */
    /** @var $form */
    /** @var $data */
?>
<div class="wpfs-form-group">
    <label for="<?php $view->cardFieldLanguage()->id(); ?>" class="wpfs-form-label"><?php $view->cardFieldLanguage()->label(); ?></label>
    <div class="wpfs-ui wpfs-form-select">
        <select id="<?php $view->cardFieldLanguage()->id(); ?>" name="<?php $view->cardFieldLanguage()->name(); ?>" <?php $view->cardFieldLanguage()->attributes(); ?>>
            <option value="<?php echo Main::PREFERRED_LANGUAGE_AUTO; ?>"><?php esc_html_e( 'Auto', 'wp-full-stripe-free' ); ?></option>
            <?php foreach ( $data->cardFieldLanguages as $language ) { ?>
            <option value="<?php echo $language['value']; ?>" <?php echo $language['value'] === $form->preferredLanguage ? 'selected': ''; ?>><?php echo esc_html( $language['name'] ); ?></option>
            <?php } ?>
        </select>
    </div>
</div>
