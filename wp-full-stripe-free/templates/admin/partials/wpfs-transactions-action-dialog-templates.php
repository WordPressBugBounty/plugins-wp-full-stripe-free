<?php
/**
 * One transaction action's confirmation dialog, and the dialog shown while the action runs,
 * as underscore templates.
 *
 * @var string $dialogAction            E.g. "refund-payment".
 * @var string $dialogButtonStyle       "primary" or "danger".
 * @var string $dialogConfirmLabel
 * @var string $dialogCancelLabel
 * @var string $dialogInProgressMessage
 */
?>
<script type="text/template" id="wpfs-modal-<?php echo esc_attr( $dialogAction ); ?>">
    <div class="wpfs-dialog-scrollable">
        <p class="wpfs-dialog-content-text"><%- confirmationMessage %></p>
    </div>
    <div class="wpfs-dialog-content-actions">
        <button class="wpfs-btn wpfs-btn-<?php echo esc_attr( $dialogButtonStyle ); ?> js-<?php echo esc_attr( $dialogAction ); ?>-dialog"><?php echo esc_html( $dialogConfirmLabel ); ?></button>
        <button class="wpfs-btn wpfs-btn-text js-close-this-dialog"><?php echo esc_html( $dialogCancelLabel ); ?></button>
    </div>
</script>
<script type="text/template" id="wpfs-modal-<?php echo esc_attr( $dialogAction ); ?>-in-progress">
    <div class="wpfs-dialog-scrollable">
        <p class="wpfs-dialog-content-text"><?php echo esc_html( $dialogInProgressMessage ); ?></p>
    </div>
    <div class="wpfs-dialog-content-actions">
        <button class="wpfs-btn wpfs-btn-<?php echo esc_attr( $dialogButtonStyle ); ?> wpfs-btn-<?php echo esc_attr( $dialogButtonStyle ); ?>--loader" disabled><?php echo esc_html( $dialogConfirmLabel ); ?></button>
    </div>
</script>
