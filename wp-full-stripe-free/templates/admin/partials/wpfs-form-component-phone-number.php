<?php
use ThemeIsle\WPFullPay\Admin\Views\CheckoutDonationFormView;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutPaymentFormView;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutSubscriptionFormView;

/** @var $view CheckoutPaymentFormView|CheckoutSubscriptionFormView|CheckoutDonationFormView */
/** @var $form */
/** @var $data */
?>
<div class="wpfs-form-check-list">
    <div class="wpfs-form-check">
        <input id="<?php $view->collectPhoneNumber()->id(); ?>" name="<?php $view->collectPhoneNumber()->name(); ?>" value="<?php $view->collectPhoneNumber()->value(); ?>" <?php $view->collectPhoneNumber()->attributes(); ?> <?php echo $form->collectPhoneNumber == $view->collectPhoneNumber()->value(false) ? 'checked' : ''; ?>>
        <label class="wpfs-form-check-label" for="<?php $view->collectPhoneNumber()->id(); ?>"><?php $view->collectPhoneNumber()->label(); ?></label>
    </div>
</div>
