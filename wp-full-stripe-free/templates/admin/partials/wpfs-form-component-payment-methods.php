<?php
use ThemeIsle\WPFullPay\Assets;
use ThemeIsle\WPFullPay\Forms\Views\Control;

/** @var Control $option*/

$payment_methods = [];
if ( isset( $form->paymentMethods ) && ! empty( $form->paymentMethods ) ) {
	$payment_methods = json_decode( $form->paymentMethods );
}

$payment_id = null;
$payment_metadata = $option->metadata();
if ( is_array( $payment_metadata) && isset( $payment_metadata['id'] ) ) {
	$payment_id = $payment_metadata['id'];
}

$is_payment_method_used = false;
if (
	is_array( $payment_methods ) &&
	null !== $payment_id &&
	in_array( $payment_id, $payment_methods )
) {
	$is_payment_method_used = true;
}

?>
<div class="wpfs-form-check wpfs-form-check--block">
	<input
		id="<?php $option->id(); ?>"
		name="<?php $option->name(); ?>[]"
		<?php checked( $is_payment_method_used ) ?>
		value="<?php $option->value(); ?>" <?php $option->attributes(); ?>
	/>
	<label
		class="wpfs-form-check-label__payment_method wpfs-form-check-label"
		for="<?php $option->id(); ?>"
	>
		<span class="wpfs-form-check-label__title">
			<?php $option->label(); ?> (<a href="<?php echo esc_url( $option->metadata()['external_docs'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Stripe docs', 'wp-full-stripe-free' ); ?></a>)
		</span>
		<?php if ( isset( $option->metadata()['currencies'] ) and count( $option->metadata()['currencies'] ) > 0 ) : ?>
			<div class="wpfs-form-check-label__desc">
				<?php esc_html_e( 'Currencies:', 'wp-full-stripe-free' ); ?>
				<code style="line-height: 26px;">
					<?php
					if ( is_array( $option->metadata()['currencies'] ) && count( $option->metadata()['currencies'] ) > 1 ) {
						echo strtoupper( implode( ', ', $option->metadata()['currencies'] ) );
					} else {
						echo strtoupper( $option->metadata()['currencies'][0] );
					}
					?>
				</code>
			</div>
		<?php endif; ?>
		<?php if ( isset( $option->metadata()['countries'] ) and count( $option->metadata()['countries'] ) > 0 ) : ?>
			<div class="wpfs-form-check-label__desc">
				<?php esc_html_e( 'Countries:', 'wp-full-stripe-free' ); ?>
				<code style="line-height: 26px;">
					<?php
					if ( is_array( $option->metadata()['countries'] ) && count( $option->metadata()['countries'] ) > 1 ) {
						echo strtoupper( implode( ', ', $option->metadata()['countries'] ) );
					} else {
						echo strtoupper( $option->metadata()['countries'][0] );
					}
					?>
				</code>
			</div>
		<?php endif; ?>
		<?php if ( isset( $option->metadata()['recurring'] ) and $option->metadata()['recurring'] ) : ?>
			<div class="wpfs-form-check-label__desc info">
				<?php esc_html_e( 'Payment type: Recurring', 'wp-full-stripe-free' ); ?>
			</div>
		<?php elseif ( isset( $option->metadata()['recurring'] ) and $option->metadata()['recurring'] === false ) : ?>
			<div class="wpfs-form-check-label__desc info">
				<?php esc_html_e( 'Payment type: One-off only', 'wp-full-stripe-free' ); ?>
			</div>
		<?php endif; ?>
		<span class="wpfs-form-check-label__illu">
			<img src="<?php echo esc_url( Assets::images( 'payment-methods/' . $option->metadata()['icon'] ) ); ?>" alt="" />
		</span>
	</label>
</div>
