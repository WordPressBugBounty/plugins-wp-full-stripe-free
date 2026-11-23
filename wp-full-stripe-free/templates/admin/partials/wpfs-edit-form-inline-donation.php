<?php
use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Admin\Views\FormViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\InlineDonationFormView;

/** @var $view InlineDonationFormView */
/** @var $form */
?>
<form <?php $view->formAttributes(); ?>>
    <input id="<?php $view->action()->id(); ?>" name="<?php $view->action()->name(); ?>" value="<?php $view->action()->value(); ?>" <?php $view->action()->attributes(); ?>>
    <input name="<?php echo FormViewConstants::FIELD_FORM_ID; ?>" value="<?php echo $form->donationFormID; ?>" type="hidden">
    <input id="<?php $view->minimumDonationAmountHidden()->id(); ?>" name="<?php $view->minimumDonationAmountHidden()->name(); ?>" <?php $view->minimumDonationAmountHidden()->attributes(); ?>>
    <input id="<?php $view->donationGoalHidden()->id(); ?>" name="<?php $view->donationGoalHidden()->name(); ?>" <?php $view->donationGoalHidden()->attributes(); ?>>
    <input id="<?php $view->donationAmounts()->id(); ?>" name="<?php $view->donationAmounts()->name(); ?>" value="" <?php $view->donationAmounts()->attributes(); ?>>
    <?php include( 'wpfs-edit-form-pane-general.php' ); ?>
    <?php include( 'wpfs-edit-form-pane-payment-donation.php' ); ?>
    <div class="wpfs-edit-form-pane" data-tab-id="<?php echo Menu::PARAM_VALUE_TAB_APPEARANCE; ?>" style="display: none;">
        <div class="wpfs-form__cols">
            <div class="wpfs-form__col">
                <div class="wpfs-form-block">
                    <?php include( 'wpfs-form-component-submit-form-button-label.php' ); ?>
                    <?php include( 'wpfs-form-component-card-field-language.php' ); ?>
                    <?php include( 'wpfs-form-component-default-product-name.php' ); ?>
                    <?php include( 'wpfs-form-component-show-donation-goal.php' ); ?>
                    <?php include( 'wpfs-form-component-donation-goal.php' ); ?>
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
                    <?php include( 'wpfs-form-component-elements-appearance-selector.php' ); ?>
                    <?php include( 'wpfs-form-component-custom-css.php' ); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="wpfs-edit-form-pane" data-tab-id="<?php echo Menu::PARAM_VALUE_TAB_FORM_LAYOUT; ?>" style="display: none;">
        <div class="wpfs-form__cols">
            <div class="wpfs-form__col">
                <div class="wpfs-form-block">
                    <div class="wpfs-form-block__title"><?php esc_html_e( 'Optional form fields', 'wp-full-stripe-free'); ?></div>
                    <?php include( 'wpfs-form-component-customer-data-inline.php' ); ?>
                    <?php include( 'wpfs-form-component-terms-of-service.php' ); ?>
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
