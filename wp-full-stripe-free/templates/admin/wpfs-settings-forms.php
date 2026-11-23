<?php
use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Admin\Views\FormsAppearanceView;
use ThemeIsle\WPFullPay\Admin\Views\FormsOptionsView;

/** @var $tabId */
/** @var $this Menu */

$pageStyle = 'wpfs-page-settings-forms-options';
if ( $tabId === Menu::PARAM_VALUE_TAB_OPTIONS ) {
    $pageStyle = 'wpfs-page-settings-forms-options';
} elseif ( $tabId === Menu::PARAM_VALUE_TAB_APPEARANCE ) {
    $pageStyle = 'wpfs-page-settings-forms-appearance';
}
?>
<div class="wrap">
    <div class="wpfs-page <?php echo $pageStyle; ?>">
        <?php include('partials/wpfs-header-with-back-link.php'); ?>
        <?php include('partials/wpfs-announcement.php'); ?>

        <div class="wpfs-page-settings-container">
            <?php include('partials/wpfs-settings-sidebar.php'); ?>

            <div>
                <?php include('partials/wpfs-header-block-tabs.php'); ?>

                <?php
                $pageUrl = add_query_arg(
                    [
                        'page' => Menu::SLUG_SETTINGS_FORMS,
                        'tab'  => $tabId
                    ],
                    admin_url( 'admin.php' )
                );

                if ( $tabId === Menu::PARAM_VALUE_TAB_OPTIONS ) {
                    $formsOptions = $this->getFormsOptionsData();
                    $view = new FormsOptionsView();

                    include('partials/wpfs-settings-forms-options.php');
                } elseif ( $tabId === Menu::PARAM_VALUE_TAB_APPEARANCE ) {
                    $formsAppearance = $this->getFormsAppearanceData();
                    $view = new FormsAppearanceView();

                    include('partials/wpfs-settings-forms-appearance.php');
                }
                ?>
                <div id="wpfs-success-message-container"></div>
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
	<?php include( 'partials/wpfs-demo-mode.php' ); ?>
</div>
