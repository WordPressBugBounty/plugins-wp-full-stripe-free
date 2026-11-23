<?php
use ThemeIsle\WPFullPay\Admin\Views\FormView;

    /** @var $view FormView */
    /** @var $form */
    /** @var $submitButtonDescription */
?>
<div class="wpfs-form-group">
    <label for="<?php $view->buttonLabel()->id(); ?>" class="wpfs-form-label"><?php $view->buttonLabel()->label(); ?></label>
    <input id="<?php $view->buttonLabel()->id(); ?>" name="<?php $view->buttonLabel()->name(); ?>" <?php $view->buttonLabel()->attributes(); ?> value="<?php echo $form->buttonTitle; ?>">
    <?php if ( isset( $submitButtonDescription ) ) { ?>
    <div class="wpfs-form-help"><?php echo esc_html( $submitButtonDescription ) ?></div>
    <?php } ?>
</div>
