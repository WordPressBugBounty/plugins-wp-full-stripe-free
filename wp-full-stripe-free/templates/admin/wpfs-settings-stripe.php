<?php
/** @var $backLinkUrl */
/** @var $view MM_WPFS_Admin_ConfigureStripeAccountView */
/** @var $stripeData */
?>
<div class="wrap">
    <div class="wpfs-page wpfs-page-settings-configure-stripe-account">
        <?php include('partials/wpfs-header-with-back-link.php'); ?>
        <?php include('partials/wpfs-announcement.php'); ?>
        <form <?php $view->formAttributes(); ?>>
            <input id="<?php $view->action()->id(); ?>" name="<?php $view->action()->name(); ?>"
                value="<?php $view->action()->value(); ?>" <?php $view->action()->attributes(); ?>>
            <div class="wpfs-form__cols">
                <div class="wpfs-form__col">
                    <!-- instructions -->
                    <div class="wpfs-form-block">
                        <?php esc_html_e('WP Full Pay v7 and above requires Stripe Connect. Stripe Connect provides a greater degree of security than using regular Stripe private keys. For more information, see our ', 'wp-full-stripe-admin'); ?>
                        <a
                            href="https://support.paymentsplugin.com/article/31-step-by-step-guide-to-setup-stripe-on-fullpay-v7"
                            target="_blank">
                            Stripe Connect Setup Guide</a>
                        <br>
                        <br>
                        <?php esc_html_e('To complete the setup:', 'wp-full-stripe-admin'); ?>
                        <ol>
                            <li>
                                <b>configure your webhooks in Stripe</b>
                                <?php esc_html_e('This is so that Stripe is able to send notifications to WP Full Pay when events occur. This is required for the Connect flow to fully work. For more information on setting up webhooks, see our ', 'wp-full-stripe-admin'); ?>
                                <a href="https://support.paymentsplugin.com/article/17-setting-up-webhooks" target="_blank">Setting up webhooks guide</a><br/>
                                <p class="wpfs-webhook__word-break-all">
                                    <?php esc_html_e('Webhook URL:', 'wp-full-stripe-admin'); ?>
                                    <b><?php echo $stripeData->webHookUrl; ?></b>
                                    <br>
                                    <a class="wpfs-btn wpfs-btn-link js-copy-webhook-url" href=""
                                        data-webhook-url="<?php echo esc_html($stripeData->webHookUrl); ?>">
                                        <?php esc_html_e('Copy to clipboard', 'wp-full-stripe-admin'); ?>
                                    </a>
                                </p>
                            </li>
                            <li>
                                <b>connect your Stripe account to WP Full Pay</b>
                                <?php esc_html_e('This will allow WP Full Pay to access your Stripe account in a secure way that no longer requires access to your Secret key.', 'wp-full-stripe-admin'); ?>
                                <?php esc_html_e('Make sure the final status is "connected". If you see any other status, click the "Update Account Information" button and provide the missing information.', 'wp-full-stripe-admin'); ?>
                            </li>
                        </ol>
                        <br>
                        <?php esc_html_e('You can setup and test your forms in the "Test" mode, but make sure to switch to Live mode and create duplicate forms that use Live products and pricing, before adding the form(s) to your website.', 'wp-full-stripe-admin'); ?>
                    </div>
                    <!-- mode toggle -->
                    <div class="wpfs-form-block">
                        <div class="wpfs-form-block__subtitle">
                            <?php esc_html_e('Account mode', 'wp-full-stripe-admin'); ?>
                        </div>
                        <div class="wpfs-form-group">
                            <label class="wpfs-toggler">
                                <span>
                                    <?php esc_html_e('Test', 'wp-full-stripe-admin'); ?>
                                </span>
                                <input id="<?php $view->apiMode()->id(); ?>"
                                    name="<?php $view->apiMode()->name(); ?>"
                                    value="<?php echo MM_WPFS::STRIPE_API_MODE_LIVE; ?>" type="checkbox"
                                    <?php echo $stripeData->apiMode === MM_WPFS::STRIPE_API_MODE_LIVE ? 'checked' : ''; ?>
                                >
                                <span class="wpfs-toggler__switcher"></span>
                                <span>
                                    <?php esc_html_e('Live', 'wp-full-stripe-admin'); ?>
                                </span>
                            </label>
                        </div>
                    </div>
                    <!-- save/cancel -->
                    <div class="wpfs-form-actions">
                        <button class="wpfs-btn wpfs-btn-primary wpfs-button-loader" type="submit">
                            <?php esc_html_e('Save settings', 'wp-full-stripe-admin'); ?>
                        </button>
                        <a href="<?php echo $backLinkUrl; ?>" class="wpfs-btn wpfs-btn-text">
                            <?php esc_html_e('Cancel', 'wp-full-stripe-admin'); ?>
                        </a>
                    </div>
                </div>
                <div class="wpfs-form__col">
                    <div class="wpfs-form-block">
                        <div class="wpfs-form-block__title">
                            <?php esc_html_e('Live account', 'wp-full-stripe-admin'); ?>
                        </div>
                        <div class="wpfs-form-group">
                            <?php if (!($stripeData->useWpLivePlatform)): ?>
                                <!-- live Stripe connect setup -->
                                <div class="wpfs-form-group">
                                    <div>
                                        <?php esc_html_e('Connect your Stripe account to WP Full Pay in live mode to complete the upgrade to v7.0', 'wp-full-stripe-admin'); ?>
                                    </div>
                                    <button id="wpfs-create-live-stripe-connect-account" class="wpfs-btn wpfs-btn-primary wpfs-button-loader">
                                        <?php esc_html_e('Connect Live Account') ?>
                                    </button>
                                </div>
                                <?php if(isset($stripeData->liveSecretKey) && !empty($stripeData->liveSecretKey) && $stripeData->liveSecretKey !== 'YOUR_LIVE_SECRET_KEY'): ?>
                                    <div class="wpfs-form-group">
                                        <label for="<?php $view->liveSecretKey()->id(); ?>" class="wpfs-form-label">
                                            <?php $view->liveSecretKey()->label(); ?>
                                        </label>
                                        <input id="<?php $view->liveSecretKey()->id(); ?>"
                                            name="<?php $view->liveSecretKey()->name(); ?>" type="text"
                                            value="<?php echo esc_html($stripeData->liveSecretKey); ?>"
                                            class="wpfs-form-control">
                                    </div>
                                    <div class="wpfs-form-group">
                                        <label for="<?php $view->livePublishableKey()->id(); ?>"
                                            class="wpfs-form-label">
                                            <?php $view->livePublishableKey()->label(); ?>
                                        </label>
                                        <input id="<?php $view->livePublishableKey()->id(); ?>"
                                            name="<?php $view->livePublishableKey()->name(); ?>" type="text"
                                            value="<?php echo esc_html($stripeData->livePublishableKey); ?>"
                                            class="wpfs-form-control">
                                    </div>
                                <?php endif; ?>
                                <!-- live webhook -->
                                 <?php if(isset($stripeData->liveEventTitle)): ?>
                                    <div class="wpfs-form-block">
                                        <div class="wpfs-form-block__subtitle">
                                            <?php esc_html_e('Webhooks', 'wp-full-stripe-admin'); ?>
                                        </div>
                                        <div class="wpfs-webhook">
                                            <div
                                                class="wpfs-status-bullet <?php echo $stripeData->liveEventStyle; ?> wpfs-webhook__bullet">
                                                <strong>
                                                    <?php echo $stripeData->liveEventTitle; ?>
                                                </strong>
                                            </div>
                                            <div class="wpfs-webhook__last-action">
                                                <?php echo $stripeData->liveEventDescription; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- live connect -->
                                <?php if (is_null($stripeData->liveAccountId)): ?>
                                    <div class="wpfs-form-group">
                                    <div>
                                        <?php esc_html_e('Connect your Stripe account to WP Full Pay in live mode to complete the upgrade to v7.0', 'wp-full-stripe-admin'); ?>
                                    </div>
                                    <button id="wpfs-create-live-stripe-connect-account" class="wpfs-btn wpfs-btn-primary wpfs-button-loader">
                                        <?php esc_html_e('Connect Live Account') ?>
                                    </button>
                                </div>
                                <?php else: ?>
                                    <?php if($stripeData->liveAccountStatus === MM_WPFS_Options::OPTION_ACCOUNT_STATUS_COMPLETE || $stripeData->liveAccountStatus === MM_WPFS_Options::OPTION_ACCOUNT_STATUS_ENABLED ): ?>
                                        <div class="wpfs-form-block">
                                            <div class="wpfs-typo-body wpfs-typo-body--gunmetal">
                                                <?php esc_html_e('Live account connected', 'wp-full-stripe-admin'); ?>
                                            </div>
                                            <a href="https://dashboard.stripe.com/settings/account" class="wpfs-btn wpfs-btn-primary wpfs-button-loader">
                                                Manage account
                                            </a>
                                            <button class="wpfs-btn wpfs-btn-text wpfs-button-loader"  id="wpfs-clear-live-stripe-connect-account">
                                                Clear Stripe live setup
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="wpfs-form-block">
                                            <div class="wpfs-typo-body wpfs-typo-body--gunmetal">
                                                <?php esc_html_e('Live account status: ' . $stripeData->liveAccountStatus, 'wp-full-stripe-admin'); ?>
                                            </div>
                                            <div class="wpfs-inline-message wpfs-inline-message--info wpfs-inline-message--w448">
                                                <div class="wpfs-inline-message__inner">
                                                    <div class="wpfs-inline-message__title"><?php esc_html_e( 'Note: your account is not yet ready', 'wp-full-stripe-admin' ); ?></div>
                                                    <p><?php esc_html_e( 'Click the Update Account Information below to complete your setup.', 'wp-full-stripe-admin' ); ?></p>
                                                    <a href="<?php echo $stripeData->liveAccountLink; ?>" class="wpfs-btn wpfs-btn-primary wpfs-button-loader">
                                                        Update Account Information
                                                    </a>
                                                    <button class="wpfs-btn wpfs-btn-text wpfs-button-loader"  id="wpfs-clear-live-stripe-connect-account">
                                                        Clear Stripe live setup
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <!-- live webhook -->
                                    <div class="wpfs-form-block">
                                        <div class="wpfs-form-block__subtitle">
                                            <?php esc_html_e('Webhooks', 'wp-full-stripe-admin'); ?>
                                        </div>
                                        <div class="wpfs-webhook">
                                            <?php if(isset($stripeData->liveEventStyle) && isset($stripeData->liveEventTitle) && isset($stripeData->liveEventDescription)): ?>
                                                <div class="wpfs-status-bullet <?php echo $stripeData->liveEventStyle; ?> wpfs-webhook__bullet">
                                                    <strong>
                                                        <?php echo $stripeData->liveEventTitle; ?>
                                                    </strong>
                                                </div>
                                                <div class="wpfs-webhook__last-action">
                                                    <?php echo $stripeData->liveEventDescription; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="wpfs-webhook__last-action">
                                                    No webhooks received yet
                                                </div>
                                            <?php endif; ?>
                                            <?php if(!($stripeData->useWpLivePlatform)): ?>
                                                <div class="wpfs-form-group">
                                                    <label for="<?php $view->liveSecretKey()->id(); ?>" class="wpfs-form-label"><?php $view->liveSecretKey()->label(); ?></label>
                                                    <input id="<?php $view->liveSecretKey()->id(); ?>" name="<?php $view->liveSecretKey()->name(); ?>" type="text" value="<?php echo esc_html( $stripeData->liveSecretKey ); ?>" class="wpfs-form-control" />
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <!-- legacy setup -->
                                    <div class="wpfs-form-block">
                                        <div class="wpfs-inline-message wpfs-inline-message--info wpfs-inline-message--w448">
                                            <div class="wpfs-inline-message__inner">
                                                <div class="wpfs-inline-message__title"><?php esc_html_e( 'Add-on and custom hook configuration', 'wp-full-stripe-admin' ); ?></div>
                                                <p><?php esc_html_e( 'Supply your Stripe Live secret key below to be able to use Members Add-on or access Stripe from custom code using hooks.', 'wp-full-stripe-admin' ); ?></p>
                                            </div>
                                            <label for="<?php $view->liveSecretKey()->id(); ?>" class="wpfs-form-label">
                                                <?php $view->liveSecretKey()->label(); ?>
                                            </label>
                                            <input id="<?php $view->liveSecretKey()->id(); ?>"
                                                name="<?php $view->liveSecretKey()->name(); ?>" type="text"
                                                value="<?php echo esc_html($stripeData->liveSecretKey); ?>"
                                                class="wpfs-form-control">
                                        </div>
                                    </div> 
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="wpfs-form__col">
                    <div class="wpfs-form-block">
                        <div class="wpfs-form-block__title">
                            <?php esc_html_e('Test account', 'wp-full-stripe-admin'); ?>
                        </div>
                        <div class="wpfs-form-group">
                            <?php if (!($stripeData->useWpTestPlatform)): ?>
                                <div class="wpfs-form-group">
                                    <div>
                                        <?php esc_html_e('Connect your Stripe account to WP Full Pay in test mode to complete the upgrade to v7.0', 'wp-full-stripe-admin'); ?>
                                    </div>
                                    <button class="wpfs-btn wpfs-btn-primary wpfs-button-loader" id="wpfs-create-test-stripe-connect-account">
                                        <?php esc_html_e('Connect Test Account') ?>
                                    </button>
                                </div>
                                <!-- Test Stripe direct -->
                                <?php if($stripeData->testSecretKey !== 'YOUR_TEST_SECRET_KEY'): ?>
                                    <div class="wpfs-form-group">
                                        <label for="<?php $view->testSecretKey()->id(); ?>" class="wpfs-form-label">
                                            <?php $view->testSecretKey()->label(); ?>
                                        </label>
                                        <input id="<?php $view->testSecretKey()->id(); ?>"
                                            name="<?php $view->testSecretKey()->name(); ?>" type="text"
                                            value="<?php echo esc_html($stripeData->testSecretKey); ?>"
                                            class="wpfs-form-control">
                                    </div>
                                    <div class="wpfs-form-group">
                                        <label for="<?php $view->testPublishableKey()->id(); ?>"
                                            class="wpfs-form-label">
                                            <?php $view->testPublishableKey()->label(); ?>
                                        </label>
                                        <input id="<?php $view->testPublishableKey()->id(); ?>"
                                            name="<?php $view->testPublishableKey()->name(); ?>" type="text"
                                            value="<?php echo esc_html($stripeData->testPublishableKey); ?>"
                                            class="wpfs-form-control">
                                    </div>
                                <?php endif; ?>
                                <!-- test webhook -->
                                <?php if(isset($stripeData->testEventTitle)): ?>
                                    <div class="wpfs-form-block">
                                        <div class="wpfs-form-block__subtitle">
                                            <?php esc_html_e('Webhooks', 'wp-full-stripe-admin'); ?>
                                        </div>
                                        <div class="wpfs-webhook">
                                            <div
                                                class="wpfs-status-bullet <?php echo $stripeData->testEventStyle; ?> wpfs-webhook__bullet">
                                                <strong>
                                                    <?php echo $stripeData->testEventTitle; ?>
                                                </strong>
                                            </div>
                                            <div class="wpfs-webhook__last-action">
                                                <?php echo $stripeData->testEventDescription; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- Test connect -->
                                <?php if (is_null($stripeData->testAccountId)): ?>
                                    <div class="wpfs-form-group">
                                        <div>
                                            <?php esc_html_e('Connect your Stripe account to WP Full Pay in test mode to complete the upgrade to v7.0', 'wp-full-stripe-admin'); ?>
                                        </div>
                                        <button class="wpfs-btn wpfs-btn-primary wpfs-button-loader" id="wpfs-create-test-stripe-connect-account">
                                            <?php esc_html_e('Connect Test Account') ?>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <?php if($stripeData->testAccountStatus === MM_WPFS_Options::OPTION_ACCOUNT_STATUS_COMPLETE || $stripeData->testAccountStatus === MM_WPFS_Options::OPTION_ACCOUNT_STATUS_ENABLED ): ?>
                                        <div class="wpfs-form-block">
                                            <div class="wpfs-typo-body wpfs-typo-body--gunmetal">
                                                <?php esc_html_e('Test account connected', 'wp-full-stripe-admin'); ?>
                                            </div>
                                            <a href="https://dashboard.stripe.com/settings/account" class="wpfs-btn wpfs-btn-primary wpfs-button-loader">
                                                Manage account
                                            </a>
                                            <button class="wpfs-btn wpfs-btn-text wpfs-button-loader"  id="wpfs-clear-test-stripe-connect-account">
                                                Clear Stripe test setup
                                            </button>
                                       </div>
                                    <?php else: ?>
                                        <div class="wpfs-form-block">
                                            <div class="wpfs-typo-body wpfs-typo-body--gunmetal">
                                                <?php esc_html_e('Test account status: ' . $stripeData->testAccountStatus, 'wp-full-stripe-admin'); ?>
                                            </div>
                                            <div class="wpfs-inline-message wpfs-inline-message--info wpfs-inline-message--w448">
                                                <div class="wpfs-inline-message__inner">
                                                    <div class="wpfs-inline-message__title"><?php esc_html_e( 'Note: your account is not yet ready', 'wp-full-stripe-admin' ); ?></div>
                                                    <p><?php esc_html_e( 'Click the Update Account Information below to complete your setup.', 'wp-full-stripe-admin' ); ?></p>
                                                    <a href="<?php echo $stripeData->testAccountLink; ?>" class="wpfs-btn wpfs-btn-primary wpfs-button-loader">
                                                        Update Account Information
                                                    </a>
                                                    <button class="wpfs-btn wpfs-btn-text wpfs-button-loader"  id="wpfs-clear-test-stripe-connect-account">
                                                        Clear Stripe test setup
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <!-- test webhook -->
                                    <div class="wpfs-form-block">
                                        <div class="wpfs-form-block__subtitle">
                                            <?php esc_html_e('Webhooks', 'wp-full-stripe-admin'); ?>
                                        </div>
                                        <?php if(isset($stripeData->testEventTitle)): ?>
                                            <div class="wpfs-webhook">
                                                <div
                                                    class="wpfs-status-bullet <?php echo $stripeData->testEventStyle; ?> wpfs-webhook__bullet">
                                                    <strong>
                                                        <?php echo $stripeData->testEventTitle; ?>
                                                    </strong>
                                                </div>
                                                <div class="wpfs-webhook__last-action">
                                                    <?php echo $stripeData->testEventDescription; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="wpfs-webhook">
                                                <div class="wpfs-webhook__last-action">
                                                    No webhooks received yet
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <!-- legacy setup -->
                                    <div class="wpfs-form-block">
                                        <div class="wpfs-inline-message wpfs-inline-message--info wpfs-inline-message--w448">
                                            <div class="wpfs-inline-message__inner">
                                                <div class="wpfs-inline-message__title"><?php esc_html_e( 'Add-on and custom hook configuration', 'wp-full-stripe-admin' ); ?></div>
                                                <p><?php esc_html_e( 'Supply your Stripe Test secret key below to be able to use Members Add-on or access Stripe from custom code using hooks.', 'wp-full-stripe-admin' ); ?></p>
                                            </div>
                                            <label for="<?php $view->testSecretKey()->id(); ?>" class="wpfs-form-label">
                                                <?php $view->testSecretKey()->label(); ?>
                                            </label>
                                            <input id="<?php $view->testSecretKey()->id(); ?>"
                                                name="<?php $view->testSecretKey()->name(); ?>" type="text"
                                                value="<?php echo esc_html($stripeData->testSecretKey); ?>"
                                                class="wpfs-form-control">
                                        </div>
                                    </div> 

                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <div id="wpfs-success-message-container"></div>
    </div>
</div>
<script type="text/template" id="wpfs-success-message">
    <div class="wpfs-floating-message__inner">
        <div class="wpfs-floating-message__message"><%- successMessage %></div>
        <button class="wpfs-btn wpfs-btn-icon js-hide-flash-message">
            <span class="wpfs-icon-close"></span>
        </button>
    </div>
</script>
