<?php
use ThemeIsle\WPFullPay\Admin\Views\InlineSaveCardFormView;
use ThemeIsle\WPFullPay\PaymentMethods;

/** @var $view InlineSaveCardFormView */
/** @var $form */

$stripeLinkEnabled = PaymentMethods::is_link_disabled( $form->paymentMethods ?? null ) ? '0' : '1';
?>
<div class="wpfs-form-group">
    <label class="wpfs-form-label"><?php $view->stripeLink()->label(); ?></label>
    <div class="wpfs-form-check-list">
        <?php foreach ( $view->stripeLink()->options() as $option ) { ?>
            <div class="wpfs-form-check">
                <input id="<?php $option->id(); ?>" name="<?php $option->name(); ?>" <?php $option->attributes(); ?> value="<?php $option->value(); ?>" <?php checked( $stripeLinkEnabled, $option->value( false ) ); ?>>
                <label class="wpfs-form-check-label" for="<?php $option->id(); ?>"><?php $option->label(); ?></label>
            </div>
        <?php } ?>
    </div>
    <div class="wpfs-form-help"><?php esc_html_e( 'When disabled, the form doesn’t offer Stripe Link. The other payment methods enabled in your Stripe Dashboard stay available.', 'wp-full-stripe-free' ); ?></div>
</div>
