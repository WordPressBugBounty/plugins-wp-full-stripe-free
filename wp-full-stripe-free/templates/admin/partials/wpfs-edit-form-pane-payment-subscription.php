<?php
use ThemeIsle\WPFullPay\Admin\Menu;

/** @var $view \ThemeIsle\WPFullPay\Admin\Views\SubscriptionFormView */
/** @var $form */
?>
<div class="wpfs-edit-form-pane" data-tab-id="<?php echo Menu::PARAM_VALUE_TAB_PAYMENT_AND_TAX; ?>" style="display: none;">
    <div class="wpfs-form__cols">
        <div class="wpfs-form__col">
            <div class="wpfs-form-block">
                <?php include( 'wpfs-form-component-seat-country.php' ); ?>
                <?php include( 'wpfs-form-component-subscription-plans.php' ); ?>
                <?php include( 'wpfs-form-component-subscription-quantity.php' ); ?>
            </div>
            <div class="wpfs-form-block">
                <div class="wpfs-form-block__title">
                    <?php esc_html_e( 'Tax', 'wp-full-stripe-free' ); ?>
                </div>
                <?php include('wpfs-form-component-tax-type.php'); ?>
            </div>
            <div class="wpfs-form-block" id="tax-rates-settings" style="<?php echo $view->doesFormCalculateTax( $form ) ? '' : 'display: none;' ?>">
                <?php include('wpfs-form-component-tax-rate-type.php'); ?>
                <?php include( 'wpfs-form-component-collect-customer-tax-id.php' ); ?>
                <?php include( 'wpfs-form-component-tax-rates.php' ); ?>
            </div>
            <div class="wpfs-form-block">
                <?php include( 'wpfs-form-component-action-buttons.php' ); ?>
            </div>
        </div>
    </div>
</div>
