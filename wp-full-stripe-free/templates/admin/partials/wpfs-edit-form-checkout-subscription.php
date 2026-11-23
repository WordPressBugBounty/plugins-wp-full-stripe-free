<?php
use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutSubscriptionFormView;
use ThemeIsle\WPFullPay\Admin\Views\FormViewConstants;

/** @var $view CheckoutSubscriptionFormView */
/** @var $form */
?>
<form <?php $view->formAttributes(); ?>>
    <input id="<?php $view->action()->id(); ?>" name="<?php $view->action()->name(); ?>" value="<?php $view->action()->value(); ?>" <?php $view->action()->attributes(); ?>>
    <input name="<?php echo FormViewConstants::FIELD_FORM_ID; ?>" value="<?php echo $form->checkoutSubscriptionFormID; ?>" type="hidden">
    <?php include( 'wpfs-edit-form-pane-general.php' ); ?>
    <?php include( 'wpfs-edit-form-pane-payment-subscription.php' ); ?>
    <div class="wpfs-edit-form-pane" data-tab-id="<?php echo Menu::PARAM_VALUE_TAB_APPEARANCE; ?>" style="display: none;">
        <div class="wpfs-form__cols">
            <div class="wpfs-form__col">
                <div class="wpfs-form-block">
                    <?php include( 'wpfs-form-component-open-form-button-label.php' ); ?>
                    <?php include( 'wpfs-form-component-submit-form-display-payment-details.php' ); ?>
                    <?php include( 'wpfs-form-component-product-selector-style.php' ); ?>
                    <?php include( 'wpfs-form-component-checkout-form-language.php' ); ?>
                    <?php include( 'wpfs-form-component-simple-button-layout.php' ); ?>
                </div>
                <div class="wpfs-form-block">
                    <div class="wpfs-form-block__title">
                        <?php esc_html_e( 'Locale', 'wp-full-stripe-free' ); ?>
                    </div>
                    <?php include( 'wpfs-form-component-locale-currency.php' ); ?>
                </div>
                <?php include( 'wpfs-form-component-action-buttons.php' ); ?>
            </div>
            <div class="wpfs-form__col">
                <div class="wpfs-form-block wpfs-form-block--appearance">
                    <?php include( 'wpfs-form-component-form-id-badge.php' ); ?>
                    <p class="wpfs-form-help">
                        <?php esc_html_e( 'Checkout forms use Stripe-hosted pages. Custom CSS cannot be applied to checkout forms.', 'wp-full-stripe-free' ); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <div class="wpfs-edit-form-pane" data-tab-id="<?php echo Menu::PARAM_VALUE_TAB_FORM_LAYOUT; ?>" style="display: none;">
        <div class="wpfs-form__cols">
            <div class="wpfs-form__col">
                <div class="wpfs-form-block">
                    <div class="wpfs-form-block__title"><?php esc_html_e( 'Optional form fields', 'wp-full-stripe-free'); ?></div>
                    <?php include( 'wpfs-form-component-customer-data-checkout-with-phone-number.php' ); ?>
                    <?php include( 'wpfs-form-component-terms-of-service.php' ); ?>
                    <?php include( 'wpfs-form-component-coupon.php' ); ?>
                </div>
                <div class="wpfs-form-block">
                    <div class="wpfs-form-block__title"><?php esc_html_e( 'Fee Recovery', 'wp-full-stripe-free'); ?></div>
                    <?php include( 'wpfs-form-component-fee-recovery.php' ); ?>
                </div>
                <div class="wpfs-form-block">
                    <div class="wpfs-form-block__title"><?php esc_html_e( 'Custom fields', 'wp-full-stripe-free'); ?></div>
                    <?php include( 'wpfs-form-component-custom-fields.php' ); ?>
                </div>
                <?php include( 'wpfs-form-component-security-notice.php' ); ?>
                <?php include( 'wpfs-form-component-action-buttons.php' ); ?>
            </div>
        </div>
    </div>
    <?php include( 'wpfs-edit-form-pane-notifications.php' ); ?>
</form>
