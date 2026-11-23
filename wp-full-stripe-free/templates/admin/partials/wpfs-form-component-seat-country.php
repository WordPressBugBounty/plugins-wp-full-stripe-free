<?php
use ThemeIsle\WPFullPay\Admin\Views\DonationFormView;
use ThemeIsle\WPFullPay\Admin\Views\PaymentFormView;
use ThemeIsle\WPFullPay\Admin\Views\SaveCardFormView;
use ThemeIsle\WPFullPay\Admin\Views\SubscriptionFormView;
use ThemeIsle\WPFullPay\Locale\Countries;
use ThemeIsle\WPFullPay\Main;

/** @var $view PaymentFormView|SubscriptionFormView|DonationFormView|SaveCardFormView */
/** @var $form */
/** @var $data */
if ( is_null( $form->defaultBillingCountry ) || empty( $form->defaultBillingCountry ) ) {
    return;
}
?>
<div class="wpfs-form-group" id="seller-seat-country">
    <label for="<?php $view->defaultBillingCountry()->id(); ?>" class="wpfs-form-label"><?php $view->defaultBillingCountry()->label(); ?></label>
    <div class="wpfs-ui wpfs-form-select">
        <?php
        $defaultBillingCountry = is_null( $form->defaultBillingCountry ) ? Main::DEFAULT_BILLING_COUNTRY_INITIAL_VALUE : $form->defaultBillingCountry;
        ?>
        <select id="<?php $view->defaultBillingCountry()->id(); ?>" name="<?php $view->defaultBillingCountry()->name(); ?>" <?php $view->defaultBillingCountry()->attributes(); ?>>
            <?php foreach ( Countries::getCountryLabels() as $countryKey => $countryLabel ) { ?>
                <option value="<?php echo $countryKey; ?>" <?php echo $countryKey === $defaultBillingCountry ? 'selected': ''; ?>><?php echo esc_html( $countryLabel ); ?></option>
            <?php } ?>
        </select>
    </div>
</div>
