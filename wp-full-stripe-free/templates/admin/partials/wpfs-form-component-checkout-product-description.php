<?php
use ThemeIsle\WPFullPay\Admin\Views\CheckoutDonationFormView;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutPaymentFormView;

/** @var $view CheckoutDonationFormView|CheckoutPaymentFormView */
/** @var $form */
?>
<div class="wpfs-form-group">
    <label for="" class="wpfs-form-label"><?php $view->checkoutProductName()->label(); ?></label>
    <input id="<?php $view->checkoutProductName()->id(); ?>" name="<?php $view->checkoutProductName()->name(); ?>" <?php $view->checkoutProductName()->attributes(); ?>" value="<?php echo $form->productDesc; ?>">
</div>
<div class="wpfs-form-group">
    <label for="" class="wpfs-form-label"><?php $view->checkoutProductDescription()->label(); ?></label>
    <input id="<?php $view->checkoutProductDescription()->id(); ?>" name="<?php $view->checkoutProductDescription()->name(); ?>" <?php $view->checkoutProductDescription()->attributes(); ?>" value="<?php echo $form->companyName; ?>">
</div>
