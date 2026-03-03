<?php
    /** @var $view MM_WPFS_Admin_InlineSaveCardFormView */
    /** @var $form */
?>
<form <?php $view->formAttributes(); ?>>
    <input id="<?php $view->action()->id(); ?>" name="<?php $view->action()->name(); ?>" value="<?php $view->action()->value(); ?>" <?php $view->action()->attributes(); ?>>
    <input name="<?php echo MM_WPFS_Admin_FormViewConstants::FIELD_FORM_ID; ?>" value="<?php echo $form->paymentFormID; ?>" type="hidden">
    <div class="wpfs-edit-form-pane" data-tab-id="<?php echo MM_WPFS_Admin_Menu::PARAM_VALUE_TAB_GENERAL; ?>">
        <div class="wpfs-form__cols">
            <div class="wpfs-form__col">
                <div class="wpfs-form-block">
                    <div class="wpfs-form-block__title"><?php esc_html_e( 'Properties', 'wp-full-stripe-free'); ?></div>
                    <?php include( 'wpfs-form-component-display-name.php' ); ?>
                </div>
                <div class="wpfs-form-block">
                    <div class="wpfs-form-block__title"><?php esc_html_e( 'Behavior', 'wp-full-stripe-free'); ?></div>
                    <?php include( 'wpfs-form-component-redirect-after-payment.php' ); ?>
                </div>
                <?php include( 'wpfs-form-component-action-buttons.php' ); ?>
            </div>
        </div>
    </div>
    <div class="wpfs-edit-form-pane" data-tab-id="<?php echo MM_WPFS_Admin_Menu::PARAM_VALUE_TAB_PAYMENT; ?>" style="display: none;">
        <div class="wpfs-form__cols">
            <div class="wpfs-form__col">
                <div class="wpfs-form-block">
                    <?php include( 'wpfs-form-component-seat-country.php' ); ?>
                    <?php include( 'wpfs-form-component-transaction-description.php' ); ?>
                    <?php include( 'wpfs-form-component-action-buttons.php' ); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="wpfs-edit-form-pane" data-tab-id="<?php echo MM_WPFS_Admin_Menu::PARAM_VALUE_TAB_APPEARANCE; ?>" style="display: none;">
        <div class="wpfs-form__cols">
            <div class="wpfs-form__col">
                <div class="wpfs-form-block">
                    <?php include( 'wpfs-form-component-submit-form-button-label.php' ); ?>
                    <?php include( 'wpfs-form-component-card-field-language.php' ); ?>
                </div>
                <?php include( 'wpfs-form-component-action-buttons.php' ); ?>
            </div>
            <div class="wpfs-form__col">
                <div class="wpfs-form-block wpfs-form-block--appearance">
                    <div class="wpfs-form-block__title-with-badge">
                        <span class="wpfs-form-block__title"><?php esc_html_e( 'Form Appearance', 'wp-full-stripe-free' ); ?></span>
                        <span class="wpfs-form-id-badge">
                            <?php esc_html_e( 'Form ID:', 'wp-full-stripe-free' ); ?> <strong><?php echo esc_html( $form->name ); ?></strong>
                            <a class="wpfs-btn wpfs-btn-link wpfs-btn-link--small js-copy-form-id" data-form-id="<?php echo esc_attr( $form->name ); ?>"><?php esc_html_e( 'Copy', 'wp-full-stripe-free' ); ?></a>
                        </span>
                    </div>
                    <?php include( 'wpfs-form-component-elements-appearance-selector.php' ); ?>
                    <?php include( 'wpfs-form-component-custom-css.php' ); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="wpfs-edit-form-pane" data-tab-id="<?php echo MM_WPFS_Admin_Menu::PARAM_VALUE_TAB_FORM_LAYOUT; ?>" style="display: none;">
        <div class="wpfs-form__cols">
            <div class="wpfs-form__col">
                <div class="wpfs-form-block">
                    <div class="wpfs-form-block__title"><?php esc_html_e( 'Optional form fields', 'wp-full-stripe-free'); ?></div>
                    <?php include( 'wpfs-form-component-customer-data-inline.php' ); ?>
                    <?php include( 'wpfs-form-component-terms-of-service.php' ); ?>
                </div>
                <div class="wpfs-form-block">
                    <div class="wpfs-form-block__title"><?php esc_html_e( 'Custom fields', 'wp-full-stripe-free'); ?></div>
                    <?php include( 'wpfs-form-component-custom-fields.php' ); ?>
                </div>
                <div class="wpfs-form-block">
                    <div class="wpfs-form-block__title"><?php esc_html_e( 'Security', 'wp-full-stripe-free'); ?></div>
                    <div class="wpfs-form-help">
                        <?php
                            echo sprintf(
                                /* translators: %1$s: opening anchor tag, %2$s: closing anchor tag */
                                __( 'You can enable reCaptcha in the %1$sWP Full Pay settings%2$s.', 'wp-full-stripe-free' ),
                                '<a target="_blank" href="' . MM_WPFS_Admin_Menu::getAdminUrlBySlug( MM_WPFS_Admin_Menu::SLUG_SETTINGS_SECURITY ) . '">',
                                '</a>'
                            );
                        ?>
                    </div>
                </div>
                <?php include( 'wpfs-form-component-action-buttons.php' ); ?>
            </div>
        </div>
    </div>
    <div class="wpfs-edit-form-pane" data-tab-id="<?php echo MM_WPFS_Admin_Menu::PARAM_VALUE_TAB_NOTIFICATIONS_AND_INTEGRATION; ?>" style="display: none;">
        <?php include( 'wpfs-form-component-email-templates.php' ); ?>
        <div class="wpfs-form__cols">
            <div class="wpfs-form__col wpfs-form__col__third">
                <?php include( 'wpfs-form-component-webhook.php' ); ?>
                <?php include( 'wpfs-form-component-action-buttons.php' ); ?>
            </div>
        </div>
    </div>
</form>
