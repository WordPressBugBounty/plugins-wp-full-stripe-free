<?php
use ThemeIsle\WPFullPay\Admin\Views\DonationFormView;
use ThemeIsle\WPFullPay\Admin\Views\PaymentFormView;
use ThemeIsle\WPFullPay\Locale\Currencies;

    /** @var $view DonationFormView|PaymentFormView */
    /** @var $form */
    /** @var $data */
?>
<div class="wpfs-form-group">
    <label for="<?php $view->currency()->id(); ?>" class="wpfs-form-label"><?php $view->currency()->label(); ?></label>
    <div class="wpfs-ui wpfs-form-select">
        <select id="<?php $view->currency()->id(); ?>" name="<?php $view->currency()->name(); ?>" <?php $view->currency()->attributes(); ?>>
            <option value=""><?php esc_html_e( 'Select currency', 'wp-full-stripe-free' ); ?></option>
            <?php
            $currencyLabels = Currencies::getCurrencyLabels();
            foreach ( $data->currencies as $currencyKey => $currency ) {
            ?>
            <option value="<?php echo $currencyKey ?>" data-currency-code="<?php echo $currency['code']; ?>" data-currency-symbol="<?php echo $currency['symbol']; ?>" data-zero-decimal-support="<?php echo $currency['zeroDecimalSupport'] == true ? 'true' : 'false'; ?>" <?php echo $form->currency === $currencyKey ? 'selected' : ''; ?>><?php
                /* translators: 1: currency name, 2: currency code */
                echo esc_html( sprintf( __( '%1$s (%2$s)', 'wp-full-stripe-free' ), $currencyLabels[ $currencyKey ], $currency['code'] ) );
            ?></option>
            <?php } ?>
        </select>
    </div>
</div>
