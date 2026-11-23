<?php
use ThemeIsle\WPFullPay\Admin\Menu;
?>
<div class="wpfs-form-block">
    <div class="wpfs-form-block__title"><?php esc_html_e( 'Security', 'wp-full-stripe-free'); ?></div>
    <div class="wpfs-form-help">
        <?php
            echo sprintf(
                /* translators: %1$s: opening anchor tag, %2$s: closing anchor tag */
                esc_html__( 'You can enable reCaptcha in the %1$sWP Full Pay settings%2$s.', 'wp-full-stripe-free' ),
                '<a target="_blank" href="' . Menu::getAdminUrlBySlug( Menu::SLUG_SETTINGS_SECURITY ) . '">',
                '</a>'
            );
        ?>
    </div>
</div>
