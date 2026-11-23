<?php
use ThemeIsle\WPFullPay\Admin\Views\DonationFormView;

/** @var $view DonationFormView */
/** @var $form */
?>
<div class="wpfs-form-group">
    <input <?php $view->showDonationGoal()->attributes(); ?> id="<?php $view->showDonationGoal()->id(); ?>" value="<?php $view->showDonationGoal()->value(); ?>" name="<?php $view->showDonationGoal()->name(); ?>" <?php echo (int)$form->showDonationGoal === (int)$view->showDonationGoal()->value() ? 'checked' : ''; ?>>
    <label class="wpfs-form-check-label" for="<?php $view->showDonationGoal()->id(); ?>"><?php $view->showDonationGoal()->label(); ?></label>
</div>
