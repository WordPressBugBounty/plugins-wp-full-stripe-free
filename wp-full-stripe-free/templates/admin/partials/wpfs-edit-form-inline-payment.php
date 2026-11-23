<?php
use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Admin\Models\PaymentFormModel;
use ThemeIsle\WPFullPay\Admin\Views\FormViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\InlinePaymentFormView;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;

/** @var $view InlinePaymentFormView */
/** @var $form PaymentFormModel */

// check status of connect
$liveStatus = $this->options->get( Options::OPTION_LIVE_ACCOUNT_STATUS );
$testStatus = $this->options->get( Options::OPTION_TEST_ACCOUNT_STATUS );

$liveComplete = $liveStatus === Options::OPTION_ACCOUNT_STATUS_COMPLETE || $liveStatus === Options::OPTION_ACCOUNT_STATUS_ENABLED ? true : false;
$testComplete = $testStatus === Options::OPTION_ACCOUNT_STATUS_COMPLETE || $testStatus === Options::OPTION_ACCOUNT_STATUS_ENABLED ? true : false;


?>
<style>
	.wpfs-form-check-label__payment_method::before {
		top: 50% !important;
		left: 0 !important;
		margin-top: -8px !important;
		margin-left: 15px;
	}

	.wpfs-form-check-label__payment_method::after {
		left: 15px !important;
	}
</style>

<form <?php $view->formAttributes(); ?>>
	<input id="<?php $view->action()->id(); ?>" name="<?php $view->action()->name(); ?>"
		value="<?php $view->action()->value(); ?>" <?php $view->action()->attributes(); ?>>
	<input name="<?php echo FormViewConstants::FIELD_FORM_ID; ?>"
		value="<?php echo $form->paymentFormID; ?>" type="hidden">
	<input id="<?php $view->minimumPaymentAmountHidden()->id(); ?>"
		name="<?php $view->minimumPaymentAmountHidden()->name(); ?>" <?php $view->minimumPaymentAmountHidden()->attributes(); ?>>
	<?php include( 'wpfs-edit-form-pane-general.php' ); ?>
	<div class="wpfs-edit-form-pane" data-tab-id="<?php echo Menu::PARAM_VALUE_TAB_PAYMENT_AND_TAX; ?>"
		style="display: none;">
		<div class="wpfs-form__cols">
			<div class="wpfs-form__col">
				<div class="wpfs-form-block">
					<?php include ( 'wpfs-form-component-currency.php' ); ?>
					<?php include ( 'wpfs-form-component-seat-country.php' ); ?>
					<?php include ( 'wpfs-form-component-payment-type.php' ); ?>
				</div>
			</div>
		</div>
		<div class="wpfs-form__cols" id="onetime-payment-stripe-product-list"
			style="<?php echo $form->customAmount == Main::PAYMENT_TYPE_CUSTOM_AMOUNT ? 'display: none;' : ''; ?>">
			<div class="wpfs-form__col">
				<div class="wpfs-form-block">
					<?php include ( 'wpfs-form-component-payment-products.php' ); ?>
				</div>
			</div>
		</div>
		<div class="wpfs-form__cols">
			<div class="wpfs-form__col">
				<div class="wpfs-form-block">
					<?php include ( 'wpfs-form-component-minimum-payment-amount.php' ); ?>
					<?php include ( 'wpfs-form-component-charge-type.php' ); ?>
					<?php include ( 'wpfs-form-component-generate-invoice.php' ); ?>
					<?php include ( 'wpfs-form-component-transaction-description.php' ); ?>
				</div>
			</div>
		</div>
		<div class="wpfs-form__cols">
			<div class="wpfs-form__col">
				<div class="wpfs-form-block">
					<div class="wpfs-form-block__title">
						<?php esc_html_e( 'Tax', 'wp-full-stripe-free' ); ?>
					</div>
					<?php include ( 'wpfs-form-component-tax-type.php' ); ?>
				</div>
				<div class="wpfs-form-block" id="tax-rates-settings"
					style="<?php echo $view->doesFormCalculateTax( $form ) ? '' : 'display: none;' ?>">
					<?php include ( 'wpfs-form-component-tax-rate-type.php' ); ?>
					<?php include ( 'wpfs-form-component-collect-customer-tax-id.php' ); ?>
					<?php include ( 'wpfs-form-component-tax-rates.php' ); ?>
				</div>
			</div>
		</div>
		<div class="wpfs-form__cols">
			<div class="wpfs-form__col">
				<div class="wpfs-form-block">
					<?php
					// set default payment methods on the form if they are not set
					if ( ! isset( $form->paymentMethods ) || empty( $form->paymentMethods ) ) {
						$form->paymentMethods = "[\"card\", \"link\"]";
					}
					// loop throug the payment methods and display them
					$counter = 0;
					$count_of_payment_methods = count( $view->getPaymentMethod()->options() );
					?>
					<label for=""
						class="wpfs-form-label wpfs-form-label--mb"><?php $view->getPaymentMethod()->label(); ?></label>
					<?php if ( $liveComplete || $testComplete ) { ?>
						<?php foreach ( $view->getPaymentMethod()->options() as $option ) {
							/* @var $option Control */
							if ( round( $count_of_payment_methods / 2 ) == $counter ) {
								echo '
						</div>
					</div>
					<div class="wpfs-form__col">
                        <div class="wpfs-form-block">';
							}
							$counter++;
							?>
							<?php include ( 'wpfs-form-component-payment-methods.php' ); ?>
						<?php } ?>
					<?php } else {
						$url = admin_url( 'admin.php?page=wpfs-settings-stripe' );

						?>
						<p>
							<?php
							printf(
								/* translators: %1$s: opening link tag to the Stripe settings page, %2$s: closing link tag */
								esc_html__( '%1$sConnect your Stripe account%2$s to be able to select payment methods', 'wp-full-stripe-free' ),
								'<a href="' . esc_url( $url ) . '">',
								'</a>'
							);
							?>
						</p>
					<?php } ?>
				</div>
			</div>
		</div>
		<div class="wpfs-form__cols">
			<div class="wpfs-form__col">
				<div class="wpfs-form-block">
					<?php include ( 'wpfs-form-component-action-buttons.php' ); ?>
				</div>
			</div>
		</div>
	</div>
	<div class="wpfs-edit-form-pane" data-tab-id="<?php echo Menu::PARAM_VALUE_TAB_APPEARANCE; ?>"
		style="display: none;">
		<div class="wpfs-form__cols">
			<div class="wpfs-form__col">
				<div class="wpfs-form-block">
					<?php include ( 'wpfs-form-component-submit-form-button-label.php' ); ?>
					<?php include( 'wpfs-form-component-submit-form-display-payment-details.php' ); ?>
					<?php include ( 'wpfs-form-component-product-selector-style.php' ); ?>
					<?php include ( 'wpfs-form-component-card-field-language.php' ); ?>
				</div>
				<div class="wpfs-form-block">
					<div class="wpfs-form-block__title">
						<?php esc_html_e( 'Locale', 'wp-full-stripe-free' ); ?>
					</div>
					<?php include ( 'wpfs-form-component-locale-currency.php' ); ?>
				</div>
				<?php include ( 'wpfs-form-component-action-buttons.php' ); ?>
			</div>
			<div class="wpfs-form__col">
				<div class="wpfs-form-block wpfs-form-block--appearance">
					<?php include( 'wpfs-form-component-form-id-badge.php' ); ?>
					<?php include ( 'wpfs-form-component-elements-appearance-selector.php' ); ?>
					<?php include ( 'wpfs-form-component-custom-css.php' ); ?>
				</div>
			</div>
		</div>
	</div>
	<div class="wpfs-edit-form-pane" data-tab-id="<?php echo Menu::PARAM_VALUE_TAB_FORM_LAYOUT; ?>"
		style="display: none;">
		<div class="wpfs-form__cols">
			<div class="wpfs-form__col">
				<div class="wpfs-form-block">
					<div class="wpfs-form-block__title">
						<?php esc_html_e( 'Optional form fields', 'wp-full-stripe-free' ); ?>
					</div>
					<?php include ( 'wpfs-form-component-customer-data-inline.php' ); ?>
					<?php include ( 'wpfs-form-component-terms-of-service.php' ); ?>
					<?php include ( 'wpfs-form-component-coupon.php' ); ?>
				</div>
                <div class="wpfs-form-block">
                    <div class="wpfs-form-block__title"><?php esc_html_e( 'Fee Recovery', 'wp-full-stripe-free'); ?></div>
                    <?php include( 'wpfs-form-component-fee-recovery.php' ); ?>
                </div>
				<div class="wpfs-form-block">
					<div class="wpfs-form-block__title">
						<?php esc_html_e( 'Custom fields', 'wp-full-stripe-free' ); ?>
					</div>
					<?php include ( 'wpfs-form-component-custom-fields.php' ); ?>
				</div>
                <?php include( 'wpfs-form-component-security-notice.php' ); ?>
				<?php include ( 'wpfs-form-component-action-buttons.php' ); ?>
			</div>
		</div>
	</div>
	<?php include( 'wpfs-edit-form-pane-notifications.php' ); ?>
</form>