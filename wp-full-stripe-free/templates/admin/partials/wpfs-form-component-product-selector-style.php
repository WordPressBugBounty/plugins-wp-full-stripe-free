<?php
use ThemeIsle\WPFullPay\Admin\Views\PaymentFormView;
use ThemeIsle\WPFullPay\Admin\Views\SubscriptionFormView;

    /** @var $view PaymentFormView|SubscriptionFormView */
    /** @var $form */

    $currentValue = $view instanceof PaymentFormView ? $form->amountSelectorStyle : $form->planSelectorStyle;
?>
<div class="wpfs-form-group" id="product-selector-style-list">
    <label class="wpfs-form-label"><?php $view->productSelectorStyle()->label(); ?></label>
    <div class="wpfs-horizontal-radio-group">
    <?php foreach ( $view->productSelectorStyle()->options() as $option ) {
        /* @var $option Control */
        ?>
        <label class="wpfs-horizontal-radio-option" for="<?php $option->id(); ?>">
            <input id="<?php $option->id(); ?>" name="<?php $option->name(); ?>" value="<?php $option->value(); ?>" <?php $option->attributes(); ?> <?php echo $option->value(false) == $currentValue ? 'checked' : ''; ?> class="wpfs-horizontal-radio-input"/>
            <div class="wpfs-horizontal-radio-content">
                <div class="wpfs-horizontal-radio-icon">
                    <span class="<?php echo $option->metadata()['iconClass']; ?>"></span>
                </div>
                <div class="wpfs-horizontal-radio-label"><?php $option->label(); ?></div>
            </div>
        </label>
    <?php } ?>
    </div>
</div>
