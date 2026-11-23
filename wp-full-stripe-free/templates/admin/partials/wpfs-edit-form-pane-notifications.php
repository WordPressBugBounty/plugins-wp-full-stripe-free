<?php
use ThemeIsle\WPFullPay\Admin\Menu;
?>
<div class="wpfs-edit-form-pane" data-tab-id="<?php echo Menu::PARAM_VALUE_TAB_NOTIFICATIONS_AND_INTEGRATION; ?>" style="display: none;">
    <?php include( 'wpfs-form-component-email-templates.php' ); ?>
    <div class="wpfs-form__cols">
        <div class="wpfs-form__col wpfs-form__col__third">
            <?php include( 'wpfs-form-component-webhook.php' ); ?>
            <?php include( 'wpfs-form-component-action-buttons.php' ); ?>
        </div>
    </div>
</div>
