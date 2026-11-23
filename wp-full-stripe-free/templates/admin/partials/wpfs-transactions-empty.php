<?php
use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Main;

    /** @var $tabId */
    /** @var $transactionCount */
    /** @var $formCount */
    /** @var $createButtonLabel */
    /** @var $createButtonUrl */
    /** @var $this Menu */

    $title = '';
    $description = '';
    $icon = 'wpfs-icon-card';
    $formType = '';

    if ( $tabId === Menu::PARAM_VALUE_TAB_PAYMENTS ) {
        $title = __( 'No payments yet.', 'wp-full-stripe-free' );
        $description = $formCount > 0 ?
                       __( 'You will find one-time payments here.', 'wp-full-stripe-free' ) :
                       __( 'Create your first one-time payment form to receive payments.', 'wp-full-stripe-free' );
        $icon = 'wpfs-icon-card';
        $formType = Main::FORM_TYPE_PAYMENT;
    } elseif ( $tabId === Menu::PARAM_VALUE_TAB_SUBSCRIPTIONS ) {
        $title = __( 'No subscriptions yet.', 'wp-full-stripe-free' );
        $description = $formCount > 0 ?
            __( 'You will find your subscribers here.', 'wp-full-stripe-free' ) :
            __( 'Create your first subscription form to collect subscriptions.', 'wp-full-stripe-free' );
        $icon = 'wpfs-icon-subscription';
        $formType = Main::FORM_TYPE_SUBSCRIPTION;
    } elseif ( $tabId === Menu::PARAM_VALUE_TAB_DONATIONS ) {
        $title = __( 'No donations yet.', 'wp-full-stripe-free' );
        $description = $formCount > 0 ?
            __( 'You will find donations here.', 'wp-full-stripe-free' ) :
            __( 'Create your first donation form to accept donations.', 'wp-full-stripe-free' );
        $icon = 'wpfs-icon-donation';
        $formType = Main::FORM_TYPE_DONATION;
    } elseif ( $tabId === Menu::PARAM_VALUE_TAB_SAVED_CARDS ) {
        $title = __( 'No saved cards yet.', 'wp-full-stripe-free' );
        $description = $formCount > 0 ?
            __( 'You will find saved cards here.', 'wp-full-stripe-free' ) :
            __( 'Create your first save card form to collect card details from customers.', 'wp-full-stripe-free' );
        $icon = 'wpfs-icon-card';
        $formType = Main::FORM_TYPE_SAVE_CARD;
    }
?>

<div class="wpfs-empty-state">
    <div class="wpfs-empty-state__icon">
        <span class="<?php echo $icon; ?>"></span>
    </div>
    <div class="wpfs-empty-state__title"><?php echo esc_html( $title ); ?></div>
    <div class="wpfs-empty-state__message"><?php echo esc_html( $description ); ?></div>
    <?php if ( $formCount == 0 ) {
        $createFormParams = [
            Menu::PARAM_NAME_TYPE => $formType
        ];
        $createButtonUrl = Menu::getAdminUrlBySlugAndParams(Menu::SLUG_CREATE_FORM, $createFormParams);
    ?>
    <a class="wpfs-btn wpfs-btn-primary" href="<?php echo esc_url( $createButtonUrl ); ?>"><?php echo esc_html( $createButtonLabel ); ?></a>
    <?php } ?>
</div>
