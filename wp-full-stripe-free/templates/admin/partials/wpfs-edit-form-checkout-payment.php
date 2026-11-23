<?php
use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutPaymentFormView;
use ThemeIsle\WPFullPay\Admin\Views\FormViewConstants;
use ThemeIsle\WPFullPay\Main;

/** @var $view CheckoutPaymentFormView */
/** @var $form */
?>
<form <?php $view->formAttributes(); ?>>
    <input id="<?php $view->action()->id(); ?>" name="<?php $view->action()->name(); ?>" value="<?php $view->action()->value(); ?>" <?php $view->action()->attributes(); ?>>
    <input name="<?php echo FormViewConstants::FIELD_FORM_ID; ?>" value="<?php echo $form->checkoutFormID; ?>" type="hidden">
    <input id="<?php $view->minimumPaymentAmountHidden()->id(); ?>" name="<?php $view->minimumPaymentAmountHidden()->name(); ?>" <?php $view->minimumPaymentAmountHidden()->attributes(); ?>>
    <?php include( 'wpfs-edit-form-pane-general.php' ); ?>
    <div class="wpfs-edit-form-pane" data-tab-id="<?php echo Menu::PARAM_VALUE_TAB_PAYMENT_AND_TAX; ?>" style="display: none;">
        <div class="wpfs-form__cols">
            <div class="wpfs-form__col">
                <div class="wpfs-form-block">
                    <?php include( 'wpfs-form-component-currency.php' ); ?>
                    <?php include( 'wpfs-form-component-seat-country.php' ); ?>
                    <?php include( 'wpfs-form-component-payment-type.php' ); ?>
                </div>
            </div>
        </div>
        <div class="wpfs-form__cols" id="onetime-payment-stripe-product-list" style="<?php echo $form->customAmount == Main::PAYMENT_TYPE_CUSTOM_AMOUNT ? 'display: none;' : '';  ?>">
            <div class="wpfs-form__col">
                <div class="wpfs-form-block">
                    <?php include( 'wpfs-form-component-payment-products.php' ); ?>
                </div>
            </div>
        </div>
        <div class="wpfs-form__cols">
            <div class="wpfs-form__col">
                <div class="wpfs-form-block">
                    <?php include( 'wpfs-form-component-minimum-payment-amount.php' ); ?>
                    <?php include( 'wpfs-form-component-charge-type.php' ); ?>
                    <?php include( 'wpfs-form-component-generate-invoice.php' ); ?>
                    <?php include( 'wpfs-form-component-transaction-description.php' ); ?>
                </div>
            </div>
        </div>
        <div class="wpfs-form__cols">
            <div class="wpfs-form__col">
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
    <div class="wpfs-edit-form-pane" data-tab-id="<?php echo Menu::PARAM_VALUE_TAB_APPEARANCE; ?>" style="display: none;">
        <div class="wpfs-form__cols">
            <div class="wpfs-form__col">
                <div class="wpfs-form-block">
                    <?php include( 'wpfs-form-component-open-form-button-label.php' ); ?>
                    <?php include( 'wpfs-form-component-submit-form-display-payment-details.php' ); ?>
                    <?php include( 'wpfs-form-component-product-selector-style.php' ); ?>
                    <?php include( 'wpfs-form-component-checkout-product-description.php' ); ?>
                    <?php include( 'wpfs-form-component-checkout-form-language.php' ); ?>
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
