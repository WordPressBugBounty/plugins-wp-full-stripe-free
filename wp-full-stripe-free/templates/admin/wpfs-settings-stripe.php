<?php
use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Admin\StripeAccessRevocation;
use ThemeIsle\WPFullPay\Admin\StripeConnect;
use ThemeIsle\WPFullPay\Admin\Views\ConfigureStripeAccountView;

/** @var $tabId */
/** @var $this Menu */
/** @var $data */

?>
<div class="wrap">
    <div class="wpfs-page wpfs-page-settings-configure-stripe-account">
        <?php include('partials/wpfs-header-with-back-link.php'); ?>
        <?php include('partials/wpfs-announcement.php'); ?>

        <div class="wpfs-page-settings-container">
            <?php include('partials/wpfs-settings-sidebar.php'); ?>

            <div>
                <?php include('partials/wpfs-header-block-tabs.php'); ?>
                <?php StripeAccessRevocation::displayResultNotice(); ?>
                <?php StripeConnect::displayConfirmationPrompt(); ?>
                <?php StripeConnect::displayResultNotice(); ?>
                <?php
                    $stripeData = $this->getStripeAccountData();

                    if ( $tabId === Menu::PARAM_VALUE_TAB_CONNECTION ) {
                        $view = new ConfigureStripeAccountView();

                        include('partials/wpfs-settings-stripe-connection.php');
                    } elseif ( $tabId === Menu::PARAM_VALUE_TAB_WEBHOOK ) {
                        $view = new ConfigureStripeAccountView();

                        include('partials/wpfs-settings-stripe-webhooks.php');
                    }
                ?>
                <?php include('partials/wpfs-settings-stripe-dialogs.php'); ?>
            <div id="wpfs-success-message-container"></div>
            </div>
        </div>
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
