<?php
use ThemeIsle\WPFullPay\Admin\StripeConnect;

/** @var $data */
$accountId = isset($_GET['accountId']) ? sanitize_text_field($_GET['accountId']) : null;
$mode = isset($_GET['mode']) ? sanitize_text_field($_GET['mode']) : null;
$connectToken = isset($_GET[ StripeConnect::PARAM_TOKEN ]) ? sanitize_text_field($_GET[ StripeConnect::PARAM_TOKEN ]) : null;
$connectCode = isset($_GET[ StripeConnect::PARAM_CODE ]) ? sanitize_text_field($_GET[ StripeConnect::PARAM_CODE ]) : null;
?>
<div class="wrap">
    <div class="wpfs-page wpfs-page-settings">
        <?php include('partials/wpfs-header.php'); ?>
        <?php include('partials/wpfs-announcement.php'); ?>

        <?php if ($data->disabled): ?>
            <div class="wpfs-announcement">
                <?php esc_html_e('Settings are disabled until you have connected your Stripe account.', 'wp-full-stripe-free'); ?><br />
                <?php
                printf(
                    /* translators: %1$s: opening link tag to the setup guide, %2$s: closing link tag */
                    esc_html__( 'Follow %1$sour guide%2$s for step-by-step instructions.', 'wp-full-stripe-free' ),
                    '<a href="' . esc_url( tsdk_utmify( 'https://docs.themeisle.com/wp-full-pay/step-by-step-guide-to-setup-stripe-on-fullpay-v7', 'stripe-setup', 'settings' ) ) . '">',
                    '</a>'
                );
                ?>
            </div>
        <?php endif; ?>
        <div class="wpfs-grid">
            <?php
            $currentGroup = '';
            foreach ($data->settingsItems as $item) {
            if ($currentGroup !== $item['group']['slug']) {
                if ($currentGroup !== '') {
                echo '</div></div>';
                }
                $currentGroup = $item['group']['slug'];
                echo '<div class="wpfs-grid-container" id="group-' . $currentGroup . '"><h2>' . esc_html( $item['group']['title'] ) . '</h2><div class="wpfs-grid-items">';
            }
            ?>
            <?php if ($item['disabled']) { ?>
                <div class="wpfs-list__item" style="opacity: 0.5; cursor: not-allowed;">
                <div class="<?php echo $item['cssClasses']; ?> wpfs-list__icon"></div>
                <div class="wpfs-list__text">
                    <div class="wpfs-list__title">
                    <?php echo esc_html( $item['title'] ); ?>
                    </div>
                    <div class="wpfs-list__desc">
                    <?php echo esc_html( $item['description'] ); ?>
                    </div>
                </div>
                </div>
            <?php } else { ?>
                <a class="wpfs-list__item" href="<?php echo $item['url']; ?>">
                <div class="<?php echo $item['cssClasses']; ?> wpfs-list__icon"></div>
                <div class="wpfs-list__text">
                    <div class="wpfs-list__title">
                    <?php echo esc_html( $item['title'] ); ?>
                    </div>
                    <div class="wpfs-list__desc">
                    <?php echo esc_html( $item['description'] ); ?>
                    </div>
                </div>
                </a>
            <?php } ?>
            <?php } ?>
            </div></div>
        </div>

        <?php include('partials/wpfs-settings-test-data.php'); ?>
    </div>
    <script type="text/javascript">
        // Define a global JavaScript variable for the accountId
        var accountIdFromPHP = <?php echo json_encode($accountId); ?>;
        var accountModeFromPHP = <?php echo json_encode($mode); ?>;
        var connectTokenFromPHP = <?php echo json_encode($connectToken); ?>;
        var connectCodeFromPHP = <?php echo json_encode($connectCode); ?>;
    </script>
</div>