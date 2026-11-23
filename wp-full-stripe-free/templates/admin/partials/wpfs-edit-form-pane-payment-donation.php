<?php
use ThemeIsle\WPFullPay\Admin\Menu;
?>
<div class="wpfs-edit-form-pane" data-tab-id="<?php echo Menu::PARAM_VALUE_TAB_PAYMENT; ?>" style="display: none;">
    <div class="wpfs-form__cols">
        <div class="wpfs-form__col">
            <div class="wpfs-form-block">
                <?php include( 'wpfs-form-component-currency.php' ); ?>
                <?php include( 'wpfs-form-component-seat-country.php' ); ?>
                <?php include( 'wpfs-form-component-suggested-donation-amounts.php' ); ?>
                <?php include( 'wpfs-form-component-minimum-donation-amount.php' ); ?>
                <?php include( 'wpfs-form-component-donation-frequencies.php' ); ?>
                <?php include( 'wpfs-form-component-generate-invoice.php' ); ?>
                <?php include( 'wpfs-form-component-transaction-description.php' ); ?>
                <?php include( 'wpfs-form-component-action-buttons.php' ); ?>
            </div>
        </div>
    </div>
</div>
