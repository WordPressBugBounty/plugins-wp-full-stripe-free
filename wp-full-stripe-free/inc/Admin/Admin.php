<?php

namespace ThemeIsle\WPFullPay\Admin;

use ThemeIsle\WPFullPay\Admin\FormFactories\CloneFormFactory;
use ThemeIsle\WPFullPay\Admin\FormFactories\CreateFormFactory;
use ThemeIsle\WPFullPay\Admin\FormFactories\DeleteFormFactory;
use ThemeIsle\WPFullPay\Admin\Models\CreateFormModel;
use ThemeIsle\WPFullPay\Admin\Models\EmailOptionsModel;
use ThemeIsle\WPFullPay\Admin\Models\EmailTemplatesModel;
use ThemeIsle\WPFullPay\Admin\Models\FormModel;
use ThemeIsle\WPFullPay\Admin\Models\FormsAppearanceModel;
use ThemeIsle\WPFullPay\Admin\Models\FormsOptionsModel;
use ThemeIsle\WPFullPay\Admin\Models\LogsModel;
use ThemeIsle\WPFullPay\Admin\Models\MyAccountModel;
use ThemeIsle\WPFullPay\Admin\Models\SecurityModel;
use ThemeIsle\WPFullPay\Admin\Models\StripeAccountModel;
use ThemeIsle\WPFullPay\Admin\Models\WordpressDashboardModel;
use ThemeIsle\WPFullPay\Admin\Views\EmailOptionsViewConstants;
use ThemeIsle\WPFullPay\Context\StaticContext;
use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Exceptions\UserFriendlyException;
use ThemeIsle\WPFullPay\Forms\FormTypes;
use ThemeIsle\WPFullPay\License;
use ThemeIsle\WPFullPay\Locale\Countries;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Locale\States;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Mailer\Mailer;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\Utils;
use ThemeIsle\WPFullPay\Webhooks\EventHandler;

/**
 * Class Admin deals with admin back-end input i.e. create plans, transfers
 *
 * @phpstan-import-type FormTypeKey from FormTypes
 */
class Admin {
	use LoggerAddOn;
	use StaticContextAddOn;

	const HTTPS_DASHBOARD_STRIPE_COM = "https://dashboard.stripe.com/";
	const PATH_TEST = "test/";
	const PATH_CUSTOMERS = 'customers/';
	const PATH_CHARGES = 'charges/';
	const PATH_PAYMENTS = 'payments/';
	const PATH_SUBSCRIPTIONS = 'subscriptions/';
	const PATH_PRODUCTS = 'products/';

	/** @var Stripe */
	private $stripe = null;

	/** @var Database */
	private $db = null;

	/** @var Mailer */
	private $mailer = null;

	/** @var EventHandler $eventHandler */
	private $eventHandler = null;

	/** @var Options  */
	private $options = null;

	public function __construct( $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_ADMIN );
		$this->options = new Options();

		$this->initStaticContext();

		$this->stripe = new Stripe( Stripe::getStripeAuthenticationToken( $this->staticContext ), $this->loggerService );
		$this->db = new Database();
		$this->mailer = new Mailer( $this->loggerService );
		$this->eventHandler = new EventHandler(
			$this->db,
			$this->mailer,
			$this->loggerService
		);

		$this->hooks();
	}

	private function hooks() {

		// show notice banner if not fully configured
		add_action( 'admin_notices', [ $this, 'wpfp_config_notice' ] );
		add_action( 'wp_ajax_wpfs-dismiss-stripe-connect-notice', [ $this, 'wpfp_dismiss_notice' ] );
		add_action( 'wp_ajax_wpfs-dismiss-transaction-volume-notice', [ $this, 'wpfp_dismiss_transaction_volume_notice' ] );
		add_action( 'admin_notices', [ StripeTaxBehaviorNotice::class, 'display' ] );
		add_action( 'wp_ajax_' . StripeTaxBehaviorNotice::AJAX_DISMISS, [ $this, 'wpfp_dismiss_stripe_tax_behavior_notice' ] );

		// actions for forms
		add_action( 'wp_ajax_wpfs-create-form', [ $this, 'createForm' ] );
		add_action( 'wp_ajax_wpfs-delete-form', [ $this, 'deleteForm' ] );
		add_action( 'wp_ajax_wpfs-clone-form', [ $this, 'cloneForm' ] );

		// actions for subscription forms
		add_action( 'wp_ajax_wpfs-save-inline-subscription-form', [ $this, 'saveInlineSubscriptionForm' ] );
		add_action( 'wp_ajax_wpfs-save-checkout-subscription-form', [ $this, 'saveCheckoutSubscriptionForm' ] );

		// actions for payment forms
		add_action( 'wp_ajax_wpfs-save-inline-payment-form', [ $this, 'saveInlinePaymentForm' ] );
		add_action( 'wp_ajax_wpfs-save-checkout-payment-form', [ $this, 'saveCheckoutPaymentForm' ] );

		// actions for save card forms
		add_action( 'wp_ajax_wpfs-save-inline-save-card-form', [ $this, 'saveInlineSaveCardForm' ] );
		add_action( 'wp_ajax_wpfs-save-checkout-save-card-form', [ $this, 'saveCheckoutSaveCardForm' ] );

		// actions for donation forms
		add_action( 'wp_ajax_wpfs-save-inline-donation-form', [ $this, 'saveInlineDonationForm' ] );
		add_action( 'wp_ajax_wpfs-save-checkout-donation-form', [ $this, 'saveCheckoutDonationForm' ] );

		// actions for payments
		add_action( 'wp_ajax_wpfs-delete-payment', [ $this, 'deletePayment' ] );
		add_action( 'wp_ajax_wpfs-capture-payment', [ $this, 'capturePayment' ] );
		add_action( 'wp_ajax_wpfs-refund-payment', [ $this, 'refundPayment' ] );
		add_action( 'wp_ajax_wpfs-get-payment-details', [ $this, 'getPaymentDetails' ] );

		// actions for subscriptions
		add_action( 'wp_ajax_wpfs-cancel-subscription', [ $this, 'cancelSubscription' ] );
		add_action( 'wp_ajax_wpfs-delete-subscription', [ $this, 'deleteSubscription' ] );
		add_action( 'wp_ajax_wpfs-get-subscription-details', [ $this, 'getSubscriptionDetails' ] );

		// actions for donations
		add_action( 'wp_ajax_wpfs-refund-donation', [ $this, 'refundDonation' ] );
		add_action( 'wp_ajax_wpfs-cancel-donation', [ $this, 'cancelDonation' ] );
		add_action( 'wp_ajax_wpfs-delete-donation', [ $this, 'deleteDonation' ] );
		add_action( 'wp_ajax_wpfs-get-donation-details', [ $this, 'getDonationDetails' ] );

		// actions for saved cards
		add_action( 'wp_ajax_wpfs-delete-saved-card', [ $this, 'deleteSavedCard' ] );
		add_action( 'wp_ajax_wpfs-get-saved-card-details', [ $this, 'getSavedCardDetails' ] );

		// actions for settings pages
		add_action( 'wp_ajax_wpfs-save-stripe-account', [ $this, 'saveStripeAccount' ] );
		add_action( 'wp_ajax_wpfs-clear-stripe-settings', [ $this, 'clearStripeSettings' ] );
		add_action( 'wp_ajax_wpfs-save-my-account', [ $this, 'saveMyAccount' ] );
		add_action( 'wp_ajax_wpfs-save-security', [ $this, 'saveSecurity' ] );
		add_action( 'wp_ajax_wpfs-save-email-options', [ $this, 'saveEmailOptions' ] );
		add_action( 'wp_ajax_wpfs-save-email-templates', [ $this, 'saveEmailTemplates' ] );
		add_action( 'wp_ajax_wpfs-save-forms-options', [ $this, 'saveFormsOptions' ] );
		add_action( 'wp_ajax_wpfs-save-forms-appearance', [ $this, 'saveFormsAppearance' ] );
		add_action( 'wp_ajax_wpfs-save-wp-dashboard', [ $this, 'saveWordpressDashboard' ] );
		add_action( 'wp_ajax_wpfs-save-logs', [ $this, 'saveLogs' ] );
		add_action( 'wp_ajax_wpfs-empty-logs', [ $this, 'emptyLogs' ] );
		add_action( 'wp_ajax_wpfs-toggle-license', [ $this, 'toggleLicense' ] );

		// actions for in-form ajax requests
		add_action( 'wp_ajax_wpfs-get-onetime-products', [ $this, 'getOnetimeProducts' ] );
		add_action( 'wp_ajax_wpfs-get-recurring-products', [ $this, 'getRecurringProducts' ] );
		add_action( 'wp_ajax_wpfs-create-new-product', [ $this, 'createProduct' ] );
		add_action( 'wp_ajax_wpfs-get-tax-rates', [ $this, 'getTaxRates' ] );
		add_action( 'wp_ajax_wpfs-send-test-email', [ $this, 'sendTestEmail' ] );

		// actions for form preview
		add_action( 'wp_ajax_wpfs-preview-form', [ $this, 'previewForm' ] );

		// handle stripe webhook events
		add_action(
			'admin_post_nopriv_handle_wpfs_event',
			[
				$this,
				'fullstripe_handle_wpfs_event'
			]
		);

		// trigger webhook events
		add_action( Main::ACTION_NAME_FIRE_WEBHOOK, [ $this, 'triggerWebhook' ], 10, 2 );

		add_action( 'admin_init', [ $this->loggerService, 'downloadLog' ] );

		register_activation_hook( WP_FULL_STRIPE_BASENAME, [ $this, 'activate' ] );
		add_action( 'admin_init', [ $this, 'maybeRedirect' ] );

		add_action( 'admin_bar_menu', [ $this, 'adminBarNotice' ], 1000, 1 );
		add_action( 'admin_head', [ $this, 'adminBarNoticeCSS' ] );
		add_action( 'wp_head', [ $this, 'adminBarNoticeCSS' ] );
	}

	/**
	 * Stripe Connect Notice.
	 * 
	 * @return void
	 */
	public static function wpfp_config_notice() {
		$capability = 'manage_options';
		if ( Utils::isDemoMode() ) {
			$capability = 'read';
		}

		if ( ! current_user_can( $capability ) ) {
			return;
		}

		$options = new Options();

		if ( $options->get( Options::OPTION_STRIPE_CONNECT_NOTICE ) ||
			 ( Utils::isTestMode() && ( Utils::isConnectedToTest() || Utils::isConnectedToApiTest() ) ) ||
			 ( Utils::isLiveMode() && Utils::isConnectedToLive() ) ) {
			return;
		}

		echo '<div class="wpfs-stripe-connect-notice notice notice-info is-dismissible">
			<p>' . __( 'WP Full Pay is not fully configured. <b>Connect your Stripe account</b> to ensure you have access to all features and can continue to use the plugin.', 'wp-full-stripe-free' ) . '</p>
			<p>
				<a href="#" aria-label="' . __( 'Connect with Stripe', 'wp-full-stripe-free' ) . '" class="wpfs-stripe-connect">
					<span>' . __( 'Connect with', 'wp-full-stripe-free' ) . '</span>
					<svg width="49" height="20" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M48.4718 10.3338c0-3.41791-1.6696-6.11484-4.8607-6.11484-3.2045 0-5.1434 2.69693-5.1434 6.08814 0 4.0187 2.289 6.048 5.5743 6.048 1.6023 0 2.8141-.3604 3.7296-.8678v-2.6702c-.9155.4539-1.9658.7343-3.2987.7343-1.3061 0-2.464-.4539-2.6121-2.0294h6.5841c0-.1735.0269-.8678.0269-1.1882Zm-6.6514-1.26838c0-1.50868.929-2.13618 1.7773-2.13618.8213 0 1.6965.6275 1.6965 2.13618h-3.4738Zm-8.5499-4.84646c-1.3195 0-2.1678.61415-2.639 1.04139l-.1751-.82777h-2.9621V20l3.3661-.7076.0134-3.7784c.4847.3471 1.1984.8411 2.3832.8411 2.4102 0 4.6048-1.9225 4.6048-6.1548-.0134-3.87186-2.235-5.98134-4.5913-5.98134Zm-.8079 9.19894c-.7944 0-1.2656-.2804-1.5888-.6275l-.0134-4.95328c.35-.38719.8348-.65421 1.6022-.65421 1.2253 0 2.0735 1.36182 2.0735 3.11079 0 1.7891-.8347 3.1242-2.0735 3.1242Zm-9.6001-9.98666 3.3796-.72096V0l-3.3796.70761v2.72363Zm0 1.01469h3.3796V16.1282h-3.3796V4.44593Zm-3.6219.98798-.2154-.98798h-2.9083V16.1282h3.3661V8.21095c.7944-1.02804 2.1408-.84112 2.5582-.69426V4.44593c-.4309-.16022-2.0062-.45394-2.8006.98798Zm-6.7322-3.88518-3.2853.69426-.01346 10.69421c0 1.976 1.49456 3.4313 3.48726 3.4313 1.1041 0 1.912-.2003 2.3563-.4406v-2.7103c-.4309.1736-2.5583.7877-2.5583-1.1882V7.28972h2.5583V4.44593h-2.5583l.0135-2.8972ZM3.40649 7.83712c0-.5207.43086-.72096 1.14447-.72096 1.0233 0 2.31588.30707 3.33917.85447V4.83311c-1.11755-.44059-2.22162-.61415-3.33917-.61415C1.81769 4.21896 0 5.63418 0 7.99733c0 3.68487 5.11647 3.09747 5.11647 4.68627 0 .6141-.53858.8144-1.29258.8144-1.11755 0-2.54477-.4539-3.675782-1.0681v3.1776c1.252192.534 2.517842.761 3.675782.761 2.80059 0 4.72599-1.3752 4.72599-3.765-.01346-3.97867-5.14339-3.27106-5.14339-4.76638Z" fill="#fff"></path></svg>
				</a>
				<a href="' . esc_url( tsdk_utmify( 'https://docs.themeisle.com/wp-full-pay/getting-started-with-wp-full-pay', 'connect-stripe', 'notice' ) ) . '" target="_blank" rel="noopener noreferrer" class="button button-secondary" style="margin-left: 5px;">' . __( 'Learn More', 'wp-full-stripe-free' ) . '</a>
			</p>
			<button type="button" class="notice-dismiss"><span class="screen-reader-text">' . __( 'Dismiss this notice.', 'wp-full-stripe-free' ) . '</span></button>

			<style>
				.wpfs-stripe-connect {
					color: #fff;
					font-size: 15px;
					font-weight: bold;
					text-decoration: none;
					line-height: 1;
					background-color: #625afa;
					border-radius: 3px;
					padding: 8px 16px;
					display: inline-flex;
					align-items: center;
				}

				.wpfs-stripe-connect:focus,
				.wpfs-stripe-connect:hover {
					color: #fff;
					box-shadow: none;
					outline: none;
				}

				.wpfs-stripe-connect svg {
					margin-left: 5px;
				}
			</style>
		</div>';

		add_action( 'admin_footer',  [ __CLASS__, 'enqueue_inline_script' ] );
	}

	/**
	 * Dismiss the Stripe Connect notice.
	 * 
	 * @return void
	 */
	public function wpfp_dismiss_notice() {
		$this->validate_request( $_POST );

		$options = new Options();
		$options->set( Options::OPTION_STRIPE_CONNECT_NOTICE, true );

		wp_send_json_success();
	}

	/**
	 * Enqueue inline script for connecting to Stripe.
	 * 
	 * @return void
	 */
	public static function enqueue_inline_script() {
		$mode = Utils::isLiveMode() ? 'live' : 'test';
		?>
		<script>
			window.document.addEventListener( 'DOMContentLoaded', () => {
				const connectButton = document.querySelector( '.wpfs-stripe-connect-notice .wpfs-stripe-connect' );
				const dismissButton = document.querySelector( '.wpfs-stripe-connect-notice .notice-dismiss' );

				if ( connectButton ) {
					connectButton.addEventListener( 'click', ( e ) => {
						e.preventDefault();

						fetch( '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded'
							},
							body: new URLSearchParams( {
								action: 'wpfs-create-stripe-connect-account',
								current_page_url: '<?php echo esc_url( admin_url( 'admin.php?page=wpfs-settings-stripe' ) ); ?>',
								mode: '<?php echo $mode; ?>',
								nonce: '<?php echo esc_attr( wp_create_nonce( 'wp-full-stripe-admin-nonce' ) ); ?>'
							} )
						} )
						.then( response => response.json() )
						.then( responseData => {
							// A refused start also answers with a URL: the notice that says why.
							if ( responseData.redirectURL ) {
								window.location = responseData.redirectURL;
							}
						} )
						.catch( error => {
							console.error( 'Error:', error );
							const errorNotice = document.createElement( 'div' );
							errorNotice.className = 'notice notice-error is-dismissible';
							errorNotice.innerHTML = `<p>${ error.message }</p>`;
							document.querySelector( '.wpfs-stripe-connect-notice' ).insertAdjacentElement( 'beforebegin', errorNotice );
						});
					});
				}

				if ( dismissButton ) {
					dismissButton.addEventListener( 'click', ( e ) => {
						e.preventDefault();

						fetch( '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded'
							},
							body: new URLSearchParams( {
								action: 'wpfs-dismiss-stripe-connect-notice',
								nonce: '<?php echo esc_attr( wp_create_nonce( 'wp-full-stripe-admin-nonce' ) ); ?>'
							} )
						} )
						.then( response => response.json() )
						.then( responseData => {
							if ( responseData.success ) {
								document.querySelector( '.wpfs-stripe-connect-notice' ).remove();
							}
						} )
						.catch( error => {
							console.error( 'Error:', error );
							const errorNotice = document.createElement( 'div' );
							errorNotice.className = 'notice notice-error is-dismissible';
							errorNotice.innerHTML = `<p>${ error.message }</p>`;
							document.querySelector( '.wpfs-stripe-connect-notice' ).insertAdjacentElement( 'beforebegin', errorNotice );
						});
					});
				}
			});
		</script>
		<?php
	}

	/**
	 * Dismiss the Transaction Volume notice.
	 * 
	 * @return void
	 */
	public function wpfp_dismiss_transaction_volume_notice() {
		$this->validate_request( $_POST );

		$options = new Options();
		$options->set( Options::OPTION_TRANSACTION_VOLUME_NOTICE, true );

		// Clear the cached transaction volume check
		delete_transient( 'wpfs_transaction_volume_notice_check' );

		wp_send_json_success();
	}

	/**
	 * Dismiss the notice about Stripe Tax refusing a form's prices in the mode the notice was shown in, until Stripe
	 * Tax refuses prices in that mode again. The site may have changed mode since.
	 */
	public function wpfp_dismiss_stripe_tax_behavior_notice(): void {
		$this->validate_request( $_POST );

		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : '';
		if ( ! in_array( $mode, StripeTaxBehaviorNotice::MODES, true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request', 'wp-full-stripe-free' ) ], 400 );
		}

		StripeTaxBehaviorNotice::dismiss( $mode );

		wp_send_json_success();
	}

	/**
	 * @param $paymentStatus
	 *
	 * @return string|void
	 */
	public static function getPaymentStatusLabel( $paymentStatus ) {
		if ( Main::PAYMENT_STATUS_AUTHORIZED === $paymentStatus ) {
			$label =
				/* translators: The 'Authorized' payment status */
				__( 'Authorized', 'wp-full-stripe-free' );
		} elseif ( Main::PAYMENT_STATUS_PAID === $paymentStatus ) {
			$label =
				/* translators: The 'Paid' payment status */
				__( 'Paid', 'wp-full-stripe-free' );
		} elseif ( Main::PAYMENT_STATUS_EXPIRED === $paymentStatus ) {
			$label =
				/* translators: The 'Expired' payment status */
				__( 'Expired', 'wp-full-stripe-free' );
		} elseif ( Main::PAYMENT_STATUS_RELEASED === $paymentStatus ) {
			$label =
				/* translators: The 'Released' payment status */
				__( 'Released', 'wp-full-stripe-free' );
		} elseif ( Main::PAYMENT_STATUS_REFUNDED === $paymentStatus ) {
			$label =
				/* translators: The 'Refunded' payment status */
				__( 'Refunded', 'wp-full-stripe-free' );
		} elseif ( Main::PAYMENT_STATUS_FAILED === $paymentStatus ) {
			$label =
				/* translators: The 'Failed' payment status */
				__( 'Failed', 'wp-full-stripe-free' );
		} elseif ( Main::PAYMENT_STATUS_PENDING === $paymentStatus ) {
			$label =
				/* translators: The 'Pending' payment status */
				__( 'Pending', 'wp-full-stripe-free' );
		} else {
			$label =
				/* translators: The 'Unknown' payment status */
				__( 'Unknown', 'wp-full-stripe-free' );
		}

		return $label;
	}

	/**
	 * @param $subscriptionStatus
	 *
	 * @return string
	 */
	public static function getSubscriberStatusLabel( $subscriptionStatus ): string {
		if ( Main::SUBSCRIBER_STATUS_RUNNING === $subscriptionStatus ) {
			$label =
				/* translators: The 'Running' subscription status */
				__( 'Running', 'wp-full-stripe-free' );
		} elseif ( Main::SUBSCRIBER_STATUS_INCOMPLETE === $subscriptionStatus ) {
			$label =
				/* translators: The 'Incomplete' subscription status */
				__( 'Incomplete', 'wp-full-stripe-free' );
		} elseif ( Main::SUBSCRIBER_STATUS_CANCELLED === $subscriptionStatus ) {
			$label =
				/* translators: The 'Canceled' subscription status */
				__( 'Canceled', 'wp-full-stripe-free' );
		} elseif ( Main::SUBSCRIBER_STATUS_ENDED === $subscriptionStatus ) {
			$label =
				/* translators: The 'Ended' subscription status */
				__( 'Ended', 'wp-full-stripe-free' );
		} else {
			$label =
				/* translators: The 'Unknown' subscription status */
				__( 'Unknown', 'wp-full-stripe-free' );
		}

		return $label;
	}

	/**
	 * @param $subscriptionForm
	 *
	 * @return string
	 */
	public static function getSubscriberStatusLabelByForm( $subscriptionForm ) {
		$statusLabel = Admin::getSubscriberStatusLabel( $subscriptionForm->status );
		if ( $subscriptionForm->chargeMaximumCount > 0 ) {
			$statusLabel = sprintf( '%1$s (%2$d/%3$d)', $statusLabel, $subscriptionForm->chargeCurrentCount, $subscriptionForm->chargeMaximumCount );
		}

		return $statusLabel;
	}

	public static function getDonationStatusLabel( $donationStatus ): string {
		if ( Main::DONATION_STATUS_RUNNING === $donationStatus ) {
			$label =
				/* translators: The 'Running' donation status */
				__( 'Running', 'wp-full-stripe-free' );
		} elseif ( Main::DONATION_STATUS_PAID === $donationStatus ) {
			$label =
				/* translators: The 'Paid' donation status */
				__( 'Paid', 'wp-full-stripe-free' );
		} elseif ( Main::DONATION_STATUS_REFUNDED === $donationStatus ) {
			$label =
				/* translators: The 'Refunded' donation status */
				__( 'Refunded', 'wp-full-stripe-free' );
		} else {
			$label =
				/* translators: The 'Unknown' donation status */
				__( 'Unknown', 'wp-full-stripe-free' );
		}

		return $label;
	}

	/**
	 * @param int $liveMode
	 *
	 * @return string
	 */
	public static function getApiModeLabelFromInteger( $liveMode ): string {
		return $liveMode == 1 ?
			/* translators: The 'Live' API mode status */
			__( 'Live', 'wp-full-stripe-free' ) :
			/* translators: The 'Test' API mode status */
			__( 'Test', 'wp-full-stripe-free' );
	}

	/**
	 * @param string $apiMode
	 *
	 * @return string
	 */
	public static function getApiModeLabelFromString( $apiMode ): string {
		return $apiMode === Main::STRIPE_API_MODE_LIVE ?
			/* translators: The 'Live' API mode status */
			__( 'Live', 'wp-full-stripe-free' ) :
			/* translators: The 'Test' API mode status */
			__( 'Test', 'wp-full-stripe-free' );
	}

	/**
	 * @param $apiMode
	 *
	 * @return int
	 */
	public static function getApiModeIntegerFromString( $apiMode ): int {
		return $apiMode === Main::STRIPE_API_MODE_LIVE ? 1 : 0;
	}

	/**
	 * @param $formType string
	 * @param $emailTemplates string
	 * @param $model FormModel
	 */
	protected function updateFormEmailTemplates( $formType, $emailTemplatesJson, &$model ) {
		$emailTemplates = Mailer::extractEmailTemplates( $this->staticContext, $formType, $emailTemplatesJson );

		foreach ( $model->getEmailTemplatesHidden() as $srcTemplate ) {
			if ( ! is_object( $srcTemplate ) || ! isset( $srcTemplate->type ) || ! is_scalar( $srcTemplate->type ) ) {
				continue;
			}

			$type = (string) $srcTemplate->type;
			if ( property_exists( $emailTemplates, $type ) ) {
				$emailTemplates->{$type}->enabled = $srcTemplate->enabled;
				if ( Mailer::isPluginEmailTemplate( $type ) ) {
					$subject = ( isset( $srcTemplate->subject ) && is_scalar( $srcTemplate->subject ) ) ? sanitize_text_field( (string) $srcTemplate->subject ) : '';
					$body    = ( isset( $srcTemplate->body ) && is_scalar( $srcTemplate->body ) ) ? (string) $srcTemplate->body : '';

					if ( ! isset( $emailTemplates->{$type}->content ) || ! is_object( $emailTemplates->{$type}->content ) ) {
						$emailTemplates->{$type}->content = new \StdClass;
					}
					if ( ! isset( $emailTemplates->{$type}->content->default ) || ! is_object( $emailTemplates->{$type}->content->default ) ) {
						$emailTemplates->{$type}->content->default = new \StdClass;
					}

					$emailTemplates->{$type}->content->default->subject = $subject;
					$emailTemplates->{$type}->content->default->body    = $body;
				}
			}
		}

		$model->setEmailTemplates( json_encode( $emailTemplates ) );
	}

	function saveInlineSaveCardForm() {
		$this->saveForm( Main::FORM_TYPE_INLINE_SAVE_CARD );
	}

	function saveInlinePaymentForm() {
		$this->saveForm( Main::FORM_TYPE_INLINE_PAYMENT );
	}

	function saveCheckoutPaymentForm() {
		$this->saveForm( Main::FORM_TYPE_CHECKOUT_PAYMENT );
	}

	function saveInlineSubscriptionForm() {
		$this->saveForm( Main::FORM_TYPE_INLINE_SUBSCRIPTION );
	}

	function saveCheckoutSubscriptionForm() {
		$this->saveForm( Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION );
	}

	function saveInlineDonationForm() {
		$this->saveForm( Main::FORM_TYPE_INLINE_DONATION );
	}

	function saveCheckoutDonationForm() {
		$this->saveForm( Main::FORM_TYPE_CHECKOUT_DONATION );
	}

	function saveCheckoutSaveCardForm() {
		$this->saveForm( Main::FORM_TYPE_CHECKOUT_SAVE_CARD );
	}

	/**
	 * Saves the form posted from its editor and answers with JSON.
	 *
	 * @param FormTypeKey $formType
	 */
	private function saveForm( string $formType ): void {
		$this->validate_request( $_POST );

		$formTypeInfo = FormTypes::get( $formType );
		$this->sendActionResult(
			function () use ( $formType, $formTypeInfo ) {
				$model = new $formTypeInfo->modelClass( $this->loggerService );
				$bindingResult = $model->bind();
				if ( $bindingResult->hasErrors() ) {
					return Utils::generateReturnValueFromBindings( $bindingResult );
				}

				$form = $this->db->getFormById( $formType, $model->getId() );
				$this->updateFormEmailTemplates( $formType, $form->emailTemplates, $model );
				$this->db->updateForm( $formType, $model->getId(), $model->getData() );

				return [
					'success' => true,
					'msg' => $this->getFormSavedMessage( $formTypeInfo->type ),
					'redirectURL' => Menu::getAdminUrlBySlug( Menu::SLUG_FORMS )
				];
			},
			$this->getFormSaveErrorMessage( $formTypeInfo->type )
		);
	}

	/**
	 * @param Main::FORM_TYPE_PAYMENT|Main::FORM_TYPE_SUBSCRIPTION|Main::FORM_TYPE_DONATION|Main::FORM_TYPE_SAVE_CARD $type
	 */
	private function getFormSavedMessage( string $type ): string {
		switch ( $type ) {
			case Main::FORM_TYPE_PAYMENT:
				/* translators: Success banner label after a payment form is saved */
				return __( 'Payment form saved', 'wp-full-stripe-free' );
			case Main::FORM_TYPE_SUBSCRIPTION:
				/* translators: Success banner label after a subscription form is saved */
				return __( 'Subscription form saved', 'wp-full-stripe-free' );
			case Main::FORM_TYPE_DONATION:
				/* translators: Success banner label after a donation form is saved */
				return __( 'Donation form saved', 'wp-full-stripe-free' );
			default: // Main::FORM_TYPE_SAVE_CARD
				/* translators: Success banner label after a save card form is saved */
				return __( 'Save card form saved', 'wp-full-stripe-free' );
		}
	}

	/**
	 * @param Main::FORM_TYPE_PAYMENT|Main::FORM_TYPE_SUBSCRIPTION|Main::FORM_TYPE_DONATION|Main::FORM_TYPE_SAVE_CARD $type
	 *
	 * @return string With %s for the error message.
	 */
	private function getFormSaveErrorMessage( string $type ): string {
		switch ( $type ) {
			case Main::FORM_TYPE_PAYMENT:
				/* translators: Error banner label after a payment form is saved. %s: error message */
				return __( 'There was an error saving the payment form: %s', 'wp-full-stripe-free' );
			case Main::FORM_TYPE_SUBSCRIPTION:
				/* translators: Error banner label after a subscription form is saved. %s: error message */
				return __( 'There was an error saving the subscription form: %s', 'wp-full-stripe-free' );
			case Main::FORM_TYPE_DONATION:
				/* translators: Error banner label after a donation form is saved. %s: error message */
				return __( 'There was an error saving the donation form: %s', 'wp-full-stripe-free' );
			default: // Main::FORM_TYPE_SAVE_CARD
				/* translators: Error banner label after a save card form is saved. %s: error message */
				return __( 'There was an error saving the save card form: %s', 'wp-full-stripe-free' );
		}
	}

	/**
	 * @param $id
	 *
	 * @return array
	 */
	private function gatherSubscriptionDetails( $id ): array {
		$subscription = $this->db->findSubscriberById( $id );

		$subscriptionStatus = $subscription->status;
		$liveMode = $subscription->livemode;
		$stripeCustomerId = $subscription->stripeCustomerID;
		$stripeSubscriptionId = $subscription->stripeSubscriptionID;
		list( $paymentMethodCssClass, $paymentMethodTooltip ) = $this->getPaymentMethodStyleForPayment( $subscription );

		$stripePlan = $this->stripe->retrievePlan( $subscription->planID );

		$subscriptionDetails = [
			'id' => $subscription->subscriberID,
			'subscriptionId' => $stripeSubscriptionId,
			'subscriptionUrl' => $this->buildStripeSubscriptionUrl( $stripeSubscriptionId, $liveMode ),
			'subscriptionStatus' => $subscriptionStatus,
			'paymentMethodCssClass' => $paymentMethodCssClass,
			'paymentMethodTooltip' => $paymentMethodTooltip,
			'localizedSubscriptionStatus' => Admin::getSubscriberStatusLabelByForm( $subscription ),
			'localizedAmount' => Admin::getSubscriptionAmountLabel( $this->staticContext, $subscription, $stripePlan ),
			'date' => Utils::formatTimestampWithWordpressDateTimeFormat( strtotime( $subscription->created ) ),
			'customerId' => $stripeCustomerId,
			'customerUrl' => $this->buildStripeCustomerUrl( $stripeCustomerId, $liveMode ),
			'customerName' => $subscription->name,
			'customerEmail' => $subscription->email,
			'customerPhone' => $subscription->phoneNumber,
			'formDisplayName' => $subscription->formName,
			'isLiveMode' => $subscription->livemode,
			'localizedApiMode' => $this->getLocalizedApiMode( $liveMode ),
			'coupon' => $subscription->coupon,
			'ipAddress' => $subscription->ipAddressSubmit,
			'termsOfUseAcceptedAt' => $this->formatTermsOfUseAcceptedAt( $subscription ),
			'customFields' => $this->decodeCustomFieldsJSON( $subscription )
		];

		if ( ! is_null( $stripePlan ) ) {
			$subscriptionDetails['productName'] = $stripePlan->product->name;
		} else {
			$subscriptionDetails['productName'] =
				/* translators: This label is displayed when a product has no name */
				__( '(Not available)', 'wp-full-stripe-free' );
		}

		if ( ! empty( $subscription->addressCountry ) ) {
			$subscriptionDetails['billingName'] = ! empty( $subscription->billingName ) ? $subscription->billingName : null;
			$subscriptionDetails['billingAddressLine1'] = ! empty( $subscription->addressLine1 ) ? $subscription->addressLine1 : null;
			;
			$subscriptionDetails['billingAddressLine2'] = ! empty( $subscription->addressLine2 ) ? $subscription->addressLine2 : null;
			;
			$subscriptionDetails['billingAddressCity'] = ! empty( $subscription->addressCity ) ? $subscription->addressCity : null;
			;
			$subscriptionDetails['billingAddressState'] = ! empty( $subscription->addressState ) ? $subscription->addressState : null;
			;
			$subscriptionDetails['billingAddressZip'] = ! empty( $subscription->addressZip ) ? $subscription->addressZip : null;
			;
			$subscriptionDetails['billingAddressCountry'] = ! empty( $subscription->addressCountry ) ? $subscription->addressCountry : null;
			;
		} else {
			$subscriptionDetails['billingAddressCountry'] = null;
		}

		if ( ! empty( $subscription->shippingAddressCountry ) ) {
			$subscriptionDetails['shippingName'] = ! empty( $subscription->shippingName ) ? $subscription->shippingName : null;
			$subscriptionDetails['shippingAddressLine1'] = ! empty( $subscription->shippingAddressLine1 ) ? $subscription->shippingAddressLine1 : null;
			;
			$subscriptionDetails['shippingAddressLine2'] = ! empty( $subscription->shippingAddressLine2 ) ? $subscription->shippingAddressLine2 : null;
			;
			$subscriptionDetails['shippingAddressCity'] = ! empty( $subscription->shippingAddressCity ) ? $subscription->shippingAddressCity : null;
			;
			$subscriptionDetails['shippingAddressState'] = ! empty( $subscription->shippingAddressState ) ? $subscription->addressState : null;
			;
			$subscriptionDetails['shippingAddressZip'] = ! empty( $subscription->shippingAddressZip ) ? $subscription->shippingAddressZip : null;
			;
			$subscriptionDetails['shippingAddressCountry'] = ! empty( $subscription->shippingAddressCountry ) ? $subscription->shippingAddressCountry : null;
			;
		} else {
			$subscriptionDetails['shippingAddressCountry'] = null;
		}

		return $subscriptionDetails;
	}

	function getSubscriptionDetails() {
		$this->validate_request( $_POST );

		$id = $_POST['id'];

		$this->sendDetails(
			function () use ( $id ) {
				return $this->gatherSubscriptionDetails( $id );
			},
			__FUNCTION__,
			'Error while gathering subscription details'
		);
	}

	function cancelSubscription() {
		$this->validate_request( $_POST );

		$id = $_POST['id'];

		$this->sendActionResult(
			function () use ( $id ) {
				do_action( 'fullstripe_admin_cancel_subscriber_action', $id ); // todo: review action name and signature
				$subscriber = $this->db->findSubscriberById( $id );

				if ( ! $subscriber ) {
					return [ 'success' => false ];
				}

				// Stripe first, so a failed request leaves the local record untouched.
				$this->stripe->cancelSubscriptionUnlessEnded( $subscriber->stripeCustomerID, $subscriber->stripeSubscriptionID );
				$this->db->cancelSubscription( $id );

				return [
					'success' => true,
					'msg' =>
						/* translators: Success banner label after a subscription is canceled */
						__( 'Subscription canceled.', 'wp-full-stripe-free' ),
					'redirectURL' => self::getTransactionsTabUrl( Menu::PARAM_VALUE_TAB_SUBSCRIPTIONS )
				];
			},
			/* translators: Error banner label after a subscription is canceled. %s: error message */
			__( 'There was an error canceling the subscription: %s', 'wp-full-stripe-free' ),
			true,
			__FUNCTION__,
			'Error while canceling subscription'
		);
	}

	function cancelDonation() {
		$this->validate_request( $_POST );

		$id = $_POST['id'];

		$this->sendTransactionActionResult(
			function () use ( $id ) {
				do_action( 'fullstripe_admin_cancel_donation_action', $id ); // todo: review action name and signature
				$donation = $this->db->getDonation( $id );

				if ( $donation ) {
					// Stripe first, so a failed request leaves the local record untouched.
					$this->stripe->cancelSubscriptionUnlessEnded( $donation->stripeCustomerID, $donation->stripeSubscriptionID );
					$this->db->cancelDonationByDonationId( $id );
				}
			},
			/* translators: Success banner label after a recurring donation is canceled */
			__( 'Donation canceled.', 'wp-full-stripe-free' ),
			Menu::PARAM_VALUE_TAB_DONATIONS,
			/* translators: Error banner label after a recurring donation is canceled. %s: error message */
			__( 'There was an error canceling the donation: %s', 'wp-full-stripe-free' ),
			__FUNCTION__,
			'Error while canceling donation'
		);
	}

	public function deleteSubscription() {
		$this->validate_request( $_POST );

		$id = $_POST['id'];

		$this->sendTransactionActionResult(
			function () use ( $id ) {
				do_action( 'fullstripe_admin_delete_subscription_record_action', $id ); // todo: review action name and signature
				$this->db->deleteSubscriptionById( $id );
			},
			/* translators: Success banner label after a subscription is deleted */
			__( 'Subscription deleted.', 'wp-full-stripe-free' ),
			Menu::PARAM_VALUE_TAB_SUBSCRIPTIONS,
			/* translators: Error banner label after a subscription is deleted. %s: error message */
			__( 'There was an error deleting the subscription: %s', 'wp-full-stripe-free' ),
			__FUNCTION__,
			'Error while deleting subscription'
		);
	}

	function deletePayment() {
		$this->validate_request( $_POST );

		$id = $_POST['id'];

		$this->sendTransactionActionResult(
			function () use ( $id ) {
				do_action( 'fullstripe_admin_delete_payment_action', $id ); // todo: revise action name and parameters
				$this->db->deletePayment( $id );
			},
			/* translators: Success banner label after a one-time payment is deleted */
			__( 'Payment deleted.', 'wp-full-stripe-free' ),
			Menu::PARAM_VALUE_TAB_PAYMENTS,
			/* translators: Error banner label after a one-time payment is deleted. %s: error message */
			__( 'There was an error deleting the payment: %s', 'wp-full-stripe-free' ),
			__FUNCTION__,
			'Error while deleting payment'
		);
	}

	function deleteDonation() {
		$this->validate_request( $_POST );

		$id = $_POST['id'];

		$this->sendTransactionActionResult(
			function () use ( $id ) {
				do_action( 'fullstripe_admin_delete_donation_action', $id ); // todo: review action name and signature
				$this->db->deleteDonation( $id );
			},
			/* translators: Success banner label after a donaton is deleted */
			__( 'Donation deleted.', 'wp-full-stripe-free' ),
			Menu::PARAM_VALUE_TAB_DONATIONS,
			/* translators: Error banner label after a donaton is deleted. %s: error message */
			__( 'There was an error deleting the donation: %s', 'wp-full-stripe-free' ),
			__FUNCTION__,
			'Error while deleting donation'
		);
	}

	/**
	 * @param $isLiveMode
	 *
	 * @return string
	 */
	private function getLocalizedApiMode( $isLiveMode ): string {
		return $isLiveMode == 1 ?
			/* translators: The 'Live' API mode status */
			__( 'Live', 'wp-full-stripe-free' ) :
			/* translators: The 'Test' API mode status */
			__( 'Test', 'wp-full-stripe-free' );
	}

	/**
	 * @param $liveMode
	 *
	 * @return string
	 */
	public static function buildStripeBaseUrlStatic( $liveMode ): string {
		$href = self::HTTPS_DASHBOARD_STRIPE_COM;
		if ( $liveMode == 0 ) {
			$href .= self::PATH_TEST;
		}

		return $href;
	}

	/**
	 * @param $liveMode
	 * @return string
	 */
	protected function buildStripeBaseUrl( $liveMode ): string {
		return self::buildStripeBaseUrlStatic( $liveMode );
	}

	/**
	 * @param $stripeSubscriptionId
	 * @param $liveMode
	 *
	 * @return string
	 */
	protected function buildStripeSubscriptionUrl( $stripeSubscriptionId, $liveMode ): string {
		$href = $this->buildStripeBaseUrl( $liveMode );
		$href .= self::PATH_SUBSCRIPTIONS . $stripeSubscriptionId;

		return $href;
	}

	/**
	 * @param $stripePaymentId
	 * @param $liveMode
	 *
	 * @return string
	 */
	protected function buildStripePaymentUrl( $stripePaymentId, $liveMode ): string {
		$href = $this->buildStripeBaseUrl( $liveMode );
		$href .= self::PATH_PAYMENTS . $stripePaymentId;

		return $href;
	}

	/**
	 * @param $stripeCustomerId
	 * @param $liveMode
	 *
	 * @return string
	 */
	protected function buildStripeCustomerUrl( $stripeCustomerId, $liveMode ): string {
		$href = $this->buildStripeBaseUrl( $liveMode );
		$href .= self::PATH_CUSTOMERS . $stripeCustomerId;

		return $href;
	}

	protected function getPaymentMethodStyleForPayment( $payment ) {
		$return = [
			'wpfs-credit-card wpfs-credit-card--generic wpfs-credit-card--lg',
			/* translators: Label for the credit or debit card payment method */
			__( 'Credit or debit card', 'wp-full-stripe-free' )
		];
		if ( isset( $payment->payment_method ) ) {
			switch ( $payment->payment_method ) {
				case 'alipay':
					$return = [
						'wpfs-credit-card wpfs-alipay wpfs-credit-card--lg',
						/* translators: Label for the alipay payment method */
						__( 'AliPay', 'wp-full-stripe-free' )
					];
					break;
				case 'amazon_pay':
					$return = [
						'wpfs-credit-card wpfs-bancontact wpfs-credit-card--lg',
						/* translators: Label for the amazonpay payment method */
						__( 'Bancontact', 'wp-full-stripe-free' )
					];
					break;
				case 'bancontact':
					$return = [
						'wpfs-credit-card wpfs-bancontact wpfs-credit-card--lg',
						/* translators: Label for the bancontact payment method */
						__( 'Bancontact', 'wp-full-stripe-free' )
					];
					break;
				case 'blik':
					$return = [
						'wpfs-credit-card wpfs-blik wpfs-credit-card--lg',
						/* translators: Label for the blik payment method */
						__( 'BLIK', 'wp-full-stripe-free' )
					];
					break;
				case 'cashapp':
					$return = [
						'wpfs-credit-card wpfs-cashapp wpfs-credit-card--lg',
						/* translators: Label for the cashapp payment method */
						__( 'Cash App Pay', 'wp-full-stripe-free' )
					];
					break;
				case 'eps':
					$return = [
						'wpfs-credit-card wpfs-eps wpfs-credit-card--lg',
						/* translators: Label for the eps payment method */
						__( 'EPS', 'wp-full-stripe-free' )
					];
					break;
				case 'grabpay':
					$return = [
						'wpfs-credit-card wpfs-grabpay wpfs-credit-card--lg',
						/* translators: Label for the GrabPay payment method */
						__( 'GrabPay', 'wp-full-stripe-free' )
					];
					break;
				case 'ideal':
					$return = [
						'wpfs-credit-card wpfs-ideal wpfs-credit-card--lg',
						/* translators: Label for the iDEAL payment method */
						__( 'iDEAL', 'wp-full-stripe-free' )
					];
					break;
				case 'klarna':
					$return = [
						'wpfs-credit-card wpfs-klarna wpfs-credit-card--lg',
						/* translators: Label for the klarna payment method */
						__( 'Klarna', 'wp-full-stripe-free' )
					];
					break;
				case 'link':
					$return = [
						'wpfs-credit-card wpfs-link wpfs-credit-card--lg',
						/* translators: Label for the eps payment method */
						__( 'Link', 'wp-full-stripe-free' )
					];
					break;
				case 'mobilepay':
					$return = [
						'wpfs-credit-card wpfs-mobilepay wpfs-credit-card--lg',
						/* translators: Label for the mobilepay payment method */
						__( 'MobilePay', 'wp-full-stripe-free' )
					];
					break;
				case 'p24':
					$return = [
						'wpfs-credit-card wpfs-przelewy24 wpfs-credit-card--lg',
						/* translators: Label for the przelewy24 payment method */
						__( 'Przelewy24', 'wp-full-stripe-free' )
					];
					break;
				case 'paynow':
					$return = [
						'wpfs-credit-card wpfs-paynow wpfs-credit-card--lg',
						/* translators: Label for the PayNow payment method */
						__( 'PayNow', 'wp-full-stripe-free' )
					];
					break;
				case 'revolut_pay':
					$return = [
						'wpfs-credit-card wpfs-revolut wpfs-credit-card--lg',
						/* translators: Label for the revolut payment method */
						__( 'Revolut Pay', 'wp-full-stripe-free' )
					];
					break;
				case 'twint':
					$return = [
						'wpfs-credit-card wpfs-twint wpfs-credit-card--lg',
						/* translators: Label for the twint payment method */
						__( 'TWINT', 'wp-full-stripe-free' )
					];
					break;
				case 'wechat_pay':
					$return = [
						'wpfs-credit-card wpfs-wechat wpfs-credit-card--lg',
						/* translators: Label for the wechat payment method */
						__( 'WeChat Pay', 'wp-full-stripe-free' )
					];
					break;
			}
		}
		return $return;
	}

	/**
	 * @param $id
	 *
	 * @return array
	 */
	private function gatherPaymentDetails( $id ): array {
		$payment = $this->db->getPayment( $id );

		$paymentStatus = Utils::getPaymentStatus( $payment );
		$liveMode = $payment->livemode;
		$stripeCustomerId = $payment->stripeCustomerID;
		$stripePaymentId = $payment->eventID;
		list( $paymentMethodCssClass, $paymentMethodTooltip ) = $this->getPaymentMethodStyleForPayment( $payment );

		$paymentDetails = [
			'id' => $payment->paymentID,
			'paymentId' => $stripePaymentId,
			'paymentUrl' => $this->buildStripePaymentUrl( $stripePaymentId, $liveMode ),
			'paymentStatus' => $paymentStatus,
			'paymentMethodCssClass' => $paymentMethodCssClass,
			'paymentMethodTooltip' => $paymentMethodTooltip,
			'localizedPaymentStatus' => Admin::getPaymentStatusLabel( $paymentStatus ),
			'date' => Utils::formatTimestampWithWordpressDateTimeFormat( strtotime( $payment->created ) ),
			'amount' => $payment->amount,
			'currency' => $payment->currency,
			'localizedAmount' => Currencies::formatAndEscape( $this->staticContext, $payment->currency, $payment->amount ),
			'customerId' => $stripeCustomerId,
			'customerUrl' => $this->buildStripeCustomerUrl( $stripeCustomerId, $liveMode ),
			'customerName' => $payment->name,
			'customerEmail' => $payment->email,
			'customerPhone' => $payment->phoneNumber,
			'formDisplayName' => $payment->formName,
			'isLiveMode' => $payment->livemode,
			'localizedApiMode' => $this->getLocalizedApiMode( $liveMode ),
			'coupon' => $payment->coupon,
			'ipAddress' => $payment->ipAddressSubmit,
			'termsOfUseAcceptedAt' => $this->formatTermsOfUseAcceptedAt( $payment ),
			'customFields' => $this->decodeCustomFieldsJSON( $payment )
		];

		if ( ! empty( $payment->addressCountry ) ) {
			$paymentDetails['billingName'] = ! empty( $payment->billingName ) ? $payment->billingName : null;
			$paymentDetails['billingAddressLine1'] = ! empty( $payment->addressLine1 ) ? $payment->addressLine1 : null;
			;
			$paymentDetails['billingAddressLine2'] = ! empty( $payment->addressLine2 ) ? $payment->addressLine2 : null;
			;
			$paymentDetails['billingAddressCity'] = ! empty( $payment->addressCity ) ? $payment->addressCity : null;
			;
			$paymentDetails['billingAddressState'] = ! empty( $payment->addressState ) ? $payment->addressState : null;
			;
			$paymentDetails['billingAddressZip'] = ! empty( $payment->addressZip ) ? $payment->addressZip : null;
			;
			$paymentDetails['billingAddressCountry'] = ! empty( $payment->addressCountry ) ? $payment->addressCountry : null;
			;
		} else {
			$paymentDetails['billingAddressCountry'] = null;
		}

		if ( ! empty( $payment->shippingAddressCountry ) ) {
			$paymentDetails['shippingName'] = ! empty( $payment->shippingName ) ? $payment->shippingName : null;
			$paymentDetails['shippingAddressLine1'] = ! empty( $payment->shippingAddressLine1 ) ? $payment->shippingAddressLine1 : null;
			;
			$paymentDetails['shippingAddressLine2'] = ! empty( $payment->shippingAddressLine2 ) ? $payment->shippingAddressLine2 : null;
			;
			$paymentDetails['shippingAddressCity'] = ! empty( $payment->shippingAddressCity ) ? $payment->shippingAddressCity : null;
			;
			$paymentDetails['shippingAddressState'] = ! empty( $payment->shippingAddressState ) ? $payment->addressState : null;
			;
			$paymentDetails['shippingAddressZip'] = ! empty( $payment->shippingAddressZip ) ? $payment->shippingAddressZip : null;
			;
			$paymentDetails['shippingAddressCountry'] = ! empty( $payment->shippingAddressCountry ) ? $payment->shippingAddressCountry : null;
			;
		} else {
			$paymentDetails['shippingAddressCountry'] = null;
		}

		return $paymentDetails;
	}

	function getPaymentDetails() {
		$this->validate_request( $_POST );

		$id = $_POST['id'];

		$this->sendDetails(
			function () use ( $id ) {
				return $this->gatherPaymentDetails( $id );
			},
			__FUNCTION__,
			'Error while gathering payment details'
		);
	}

	/**
	 * @param $id
	 *
	 * @return array
	 */
	private function gatherDonationDetails( $id ): array {
		$donation = $this->db->getDonation( $id );

		$paymentStatus = Utils::getDonationPaymentStatus( $donation );
		$donationStatus = Utils::getDonationStatus( $donation );
		$liveMode = $donation->livemode;
		$stripeCustomerId = $donation->stripeCustomerID;
		$stripePaymentIntentId = $donation->stripePaymentIntentID;
		$stripeSubscriptionId = $donation->stripeSubscriptionID;

		list( $paymentMethodCssClass, $paymentMethodTooltip ) = $this->getPaymentMethodStyleForPayment( $donation );

		$paymentDetails = [
			'id' => $donation->donationID,
			'paymentIntentId' => $stripePaymentIntentId,
			'paymentIntentUrl' => $this->buildStripePaymentUrl( $stripePaymentIntentId, $liveMode ),
			'subscriptionId' => $stripeSubscriptionId,
			'subscriptionUrl' => ! is_null( $stripeSubscriptionId ) ? $this->buildStripeSubscriptionUrl( $stripeSubscriptionId, $liveMode ) : null,
			'paymentStatus' => $paymentStatus,
			'donationStatus' => $donationStatus,
			'localizedDonationStatus' => Admin::getDonationStatusLabel( $donationStatus ),
			'paymentMethodCssClass' => $paymentMethodCssClass,
			'paymentMethodTooltip' => $paymentMethodTooltip,
			'date' => Utils::formatTimestampWithWordpressDateTimeFormat( strtotime( $donation->created ) ),
			'amount' => $donation->amount,
			'currency' => $donation->currency,
			'localizedAmount' => Currencies::formatAndEscape( $this->staticContext, $donation->currency, $donation->amount ),
			'customerId' => $stripeCustomerId,
			'customerUrl' => $this->buildStripeCustomerUrl( $stripeCustomerId, $liveMode ),
			'customerName' => $donation->name,
			'customerEmail' => $donation->email,
			'customerPhone' => $donation->phoneNumber,
			'formDisplayName' => $donation->formName,
			'isLiveMode' => $donation->livemode,
			'localizedApiMode' => $this->getLocalizedApiMode( $liveMode ),
			'ipAddress' => $donation->ipAddressSubmit,
			'termsOfUseAcceptedAt' => $this->formatTermsOfUseAcceptedAt( $donation ),
			'customFields' => $this->decodeCustomFieldsJSON( $donation )
		];

		if ( ! empty( $donation->addressCountry ) ) {
			$paymentDetails['billingName'] = ! empty( $donation->billingName ) ? $donation->billingName : null;
			$paymentDetails['billingAddressLine1'] = ! empty( $donation->addressLine1 ) ? $donation->addressLine1 : null;
			;
			$paymentDetails['billingAddressLine2'] = ! empty( $donation->addressLine2 ) ? $donation->addressLine2 : null;
			;
			$paymentDetails['billingAddressCity'] = ! empty( $donation->addressCity ) ? $donation->addressCity : null;
			;
			$paymentDetails['billingAddressState'] = ! empty( $donation->addressState ) ? $donation->addressState : null;
			;
			$paymentDetails['billingAddressZip'] = ! empty( $donation->addressZip ) ? $donation->addressZip : null;
			;
			$paymentDetails['billingAddressCountry'] = ! empty( $donation->addressCountry ) ? $donation->addressCountry : null;
			;
		} else {
			$paymentDetails['billingAddressCountry'] = null;
		}

		if ( ! empty( $donation->shippingAddressCountry ) ) {
			$paymentDetails['shippingName'] = ! empty( $donation->shippingName ) ? $donation->shippingName : null;
			$paymentDetails['shippingAddressLine1'] = ! empty( $donation->shippingAddressLine1 ) ? $donation->shippingAddressLine1 : null;
			;
			$paymentDetails['shippingAddressLine2'] = ! empty( $donation->shippingAddressLine2 ) ? $donation->shippingAddressLine2 : null;
			;
			$paymentDetails['shippingAddressCity'] = ! empty( $donation->shippingAddressCity ) ? $donation->shippingAddressCity : null;
			;
			$paymentDetails['shippingAddressState'] = ! empty( $donation->shippingAddressState ) ? $donation->addressState : null;
			;
			$paymentDetails['shippingAddressZip'] = ! empty( $donation->shippingAddressZip ) ? $donation->shippingAddressZip : null;
			;
			$paymentDetails['shippingAddressCountry'] = ! empty( $donation->shippingAddressCountry ) ? $donation->shippingAddressCountry : null;
			;
		} else {
			$paymentDetails['shippingAddressCountry'] = null;
		}

		return $paymentDetails;
	}

	function getDonationDetails() {
		$this->validate_request( $_POST );

		$id = $_POST['id'];

		$this->sendDetails(
			function () use ( $id ) {
				return $this->gatherDonationDetails( $id );
			},
			__FUNCTION__,
			'Error while gathering donation details'
		);
	}

	function refundPayment() {
		$this->validate_request( $_POST );

		$id = $_POST['id'];

		$this->sendTransactionActionResult(
			function () use ( $id ) {
				do_action( 'fullstripe_admin_refund_payment_action', $id ); // todo: rethink action name and parameters
				$this->voidOrRefundPayment( $id );
			},
			/* translators: Success banner label for a successfully refunded payment */
			__( 'Payment refunded.', 'wp-full-stripe-free' ),
			Menu::PARAM_VALUE_TAB_PAYMENTS,
			/* translators: Error banner label for a payment that cannot be refunded. %s: error message */
			__( 'There was an error refunding the payment: %s', 'wp-full-stripe-free' ),
			__FUNCTION__,
			'Error while refunding payment'
		);
	}

	function refundDonation() {
		$this->validate_request( $_POST );

		$id = $_POST['id'];

		$this->sendTransactionActionResult(
			function () use ( $id ) {
				do_action( 'fullstripe_admin_refund_donation_action', $id ); // todo: review action name and signature
				$this->voidOrRefundDonation( $id );
			},
			/* translators: Success banner label for a successfully refunded donation */
			__( 'Donation refunded.', 'wp-full-stripe-free' ),
			Menu::PARAM_VALUE_TAB_DONATIONS,
			/* translators: Error banner label for a donation that cannot be refunded. %s: error message */
			__( 'There was an error refunding the donation: %s', 'wp-full-stripe-free' ),
			__FUNCTION__,
			'Error while refunding donation'
		);
	}

	/**
	 * @param $id
	 *
	 * @return mixed|null|\StripeWPFS\Stripe\ApiResource
	 */
	private function voidOrRefundDonation( $id ) {
		$this->logger->debug( __FUNCTION__, 'CALLED, id=' . print_r( $id, true ) );

		$donation = null;
		if ( ! is_null( $id ) ) {
			$donation = $this->db->getDonation( $id );
		}

		if ( isset( $donation ) ) {
			$donationStatus = Utils::getDonationPaymentStatus( $donation );

			if (
				Main::PAYMENT_STATUS_AUTHORIZED === $donationStatus ||
				Main::PAYMENT_STATUS_PAID === $donationStatus
			) {
				return $this->whileHoldingPayment(
					$donation->stripePaymentIntentID,
					function () use ( $donation ) {
						$refundedSuccessfully = false;
						$refund = $this->stripe->cancelOrRefundPaymentIntent( $donation->stripePaymentIntentID );
						if ( isset( $refund ) ) {
							$paymentIntent = $refund;
							if ( \StripeWPFS\Stripe\PaymentIntent::STATUS_CANCELED === $paymentIntent->status ) {
								$refundedSuccessfully = true;
							}
							if ( Main::REFUND_STATUS_SUCCEEDED === $refund->status ) {
								$refundedSuccessfully = true;
							}
						}

						if ( $refundedSuccessfully ) {
							$this->db->updateDonationByPaymentIntentId(
								$donation->stripePaymentIntentID,
								[
									'refunded' => true
								]
							);
						}

						return $refundedSuccessfully;
					}
				);
			}
		}

		return false;
	}

	/**
	 * @param $paymentId
	 *
	 * @return bool
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	private function voidOrRefundPayment( $paymentId ) {
		$this->logger->debug( __FUNCTION__, 'CALLED, id=' . print_r( $paymentId, true ) );

		$payment = null;
		if ( ! is_null( $paymentId ) ) {
			$payment = $this->db->getPayment( $paymentId );
		}

		if ( isset( $payment ) ) {
			$paymentStatus = Utils::getPaymentStatus( $payment );
			$paymentObjectType = Utils::getPaymentObjectType( $payment );

			$this->logger->debug( __FUNCTION__, "payment_status={$paymentStatus}, payment_object_type={$paymentObjectType}" );

			if (
				Main::PAYMENT_STATUS_AUTHORIZED === $paymentStatus
				|| Main::PAYMENT_STATUS_PAID === $paymentStatus
			) {
				return $this->whileHoldingPayment(
					$payment->eventID,
					function () use ( $payment, $paymentObjectType ) {
						$refundedSuccessfully = false;
						if ( Main::PAYMENT_OBJECT_TYPE_STRIPE_CHARGE === $paymentObjectType ) {
							$refund = $this->stripe->refundCharge( $payment->eventID );

							if ( isset( $refund ) && Main::REFUND_STATUS_SUCCEEDED === $refund->status ) {
								$refundedSuccessfully = true;
							}
						} elseif ( Main::PAYMENT_OBJECT_TYPE_STRIPE_PAYMENT_INTENT === $paymentObjectType ) {
							$refund = $this->stripe->cancelOrRefundPaymentIntent( $payment->eventID );
							if ( isset( $refund ) ) {
								$paymentIntent = $refund;
								if ( \StripeWPFS\Stripe\PaymentIntent::STATUS_CANCELED === $paymentIntent->status ) {
									$refundedSuccessfully = true;
								}
								if ( Main::REFUND_STATUS_SUCCEEDED === $refund->status ) {
									$refundedSuccessfully = true;
								}
							}
						}
						if ( $refundedSuccessfully ) {
							$this->db->updatePaymentByEventId(
								$payment->eventID,
								[
									'refunded' => true
								]
							);
						}

						return $refundedSuccessfully;
					}
				);
			}
		}

		return false;
	}

	function capturePayment() {
		$this->validate_request( $_POST );

		$id = $_POST['id'];

		$this->sendTransactionActionResult(
			function () use ( $id ) {
				do_action( 'fullstripe_admin_capture_payment_action', $id ); // todo: revise action name and params
				$this->captureChargeAndPaymentIntent( $id );
			},
			/* translators: Success banner label for an authorize & capture payment which has been captured successfully */
			__( 'Payment captured.', 'wp-full-stripe-free' ),
			Menu::PARAM_VALUE_TAB_PAYMENTS,
			/* translators: Error banner label for an authorize & capture payment which cannot be captured. %s: error message */
			__( 'There was an error capturing the payment: %s', 'wp-full-stripe-free' ),
			__FUNCTION__,
			'Error while capturing payment'
		);
	}

	/**
	 * @param $paymentId
	 *
	 * @return bool
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	private function captureChargeAndPaymentIntent( $paymentId ) {
		$this->logger->debug( __FUNCTION__, 'CALLED, payment_id=' . print_r( $paymentId, true ) );

		$payment = $this->db->getPayment( $paymentId );
		if ( isset( $payment ) ) {
			$paymentStatus = Utils::getPaymentStatus( $payment );
			$paymentObjectType = Utils::getPaymentObjectType( $payment );

			$this->logger->debug( __FUNCTION__, "payment_status={$paymentStatus}, payment_object_type={$paymentObjectType}" );

			if ( Main::PAYMENT_STATUS_AUTHORIZED === $paymentStatus ) {
				return $this->whileHoldingPayment(
					$payment->eventID,
					function () use ( $payment, $paymentObjectType ) {
						// TODO: fix this so that it doesn't rely on the object being a stripe object
						if ( Main::PAYMENT_OBJECT_TYPE_STRIPE_CHARGE === $paymentObjectType ) {
							$charge = $this->stripe->captureCharge( $payment->eventID );
						} elseif ( Main::PAYMENT_OBJECT_TYPE_STRIPE_PAYMENT_INTENT === $paymentObjectType ) {
							$charge = $this->stripe->getLatestCharge( $this->stripe->capturePaymentIntent( $payment->eventID ) );
						} else {
							return false;
						}
						if ( ! isset( $charge ) || true !== $charge->captured || Main::STRIPE_CHARGE_STATUS_SUCCEEDED !== $charge->status ) {
							return false;
						}

						$this->db->updatePaymentByEventId(
							$payment->eventID,
							[
								'paid' => $charge->paid,
								'captured' => $charge->captured,
								'refunded' => $charge->refunded,
								'last_charge_status' => $charge->status,
								'failure_code' => $charge->failure_code,
								'failure_message' => $charge->failure_message
							]
						);

						return true;
					}
				);
			}
		}

		return false;
	}

	/**
	 * Runs $update holding the payment, which its webhooks and the other admin actions write too: none may land
	 * between this request's Stripe call and its write.
	 *
	 * @template T
	 *
	 * @param string $paymentId The payment's PaymentIntent or, for an older payment, its charge.
	 * @param callable(): T $update
	 *
	 * @return T
	 * @throws UserFriendlyException When another request holds the payment for too long.
	 */
	private function whileHoldingPayment( $paymentId, callable $update ) {
		if ( ! $this->db->lockPaymentIntent( $paymentId, Database::PAYMENT_LOCK_TIMEOUT ) ) {
			throw new UserFriendlyException( __( 'This payment is being updated. Try again in a moment.', 'wp-full-stripe-free' ) );
		}
		try {
			return $update();
		} finally {
			$this->db->unlockPaymentIntent( $paymentId );
		}
	}

	/**
	 * @param $id
	 *
	 * @return array
	 */
	private function gatherSavedCardDetails( $id ): array {
		$savedCard = $this->db->getSavedCard( $id );

		$liveMode = $savedCard->livemode;
		$stripeCustomerId = $savedCard->stripeCustomerID;
		list( $paymentMethodCssClass, $paymentMethodTooltip ) = $this->getPaymentMethodStyleForPayment( $savedCard );

		$savedCardDetails = [
			'id' => $savedCard->captureID,
			'customerId' => $stripeCustomerId,
			'customerUrl' => $this->buildStripeCustomerUrl( $stripeCustomerId, $liveMode ),
			'paymentMethodCssClass' => $paymentMethodCssClass,
			'paymentMethodTooltip' => $paymentMethodTooltip,
			'date' => Utils::formatTimestampWithWordpressDateTimeFormat( strtotime( $savedCard->created ) ),
			'customerName' => $savedCard->name,
			'customerEmail' => $savedCard->email,
			'formDisplayName' => $savedCard->formName,
			'isLiveMode' => $liveMode,
			'localizedApiMode' => $this->getLocalizedApiMode( $liveMode ),
			'ipAddress' => $savedCard->ipAddressSubmit,
			'termsOfUseAcceptedAt' => $this->formatTermsOfUseAcceptedAt( $savedCard ),
			'customFields' => $this->decodeCustomFieldsJSON( $savedCard )
		];

		if ( ! empty( $savedCard->addressCountry ) ) {
			$savedCardDetails['billingName'] = ! empty( $savedCard->billingName ) ? $savedCard->billingName : null;
			$savedCardDetails['billingAddressLine1'] = ! empty( $savedCard->addressLine1 ) ? $savedCard->addressLine1 : null;
			;
			$savedCardDetails['billingAddressLine2'] = ! empty( $savedCard->addressLine2 ) ? $savedCard->addressLine2 : null;
			;
			$savedCardDetails['billingAddressCity'] = ! empty( $savedCard->addressCity ) ? $savedCard->addressCity : null;
			;
			$savedCardDetails['billingAddressState'] = ! empty( $savedCard->addressState ) ? $savedCard->addressState : null;
			;
			$savedCardDetails['billingAddressZip'] = ! empty( $savedCard->addressZip ) ? $savedCard->addressZip : null;
			;
			$savedCardDetails['billingAddressCountry'] = ! empty( $savedCard->addressCountry ) ? $savedCard->addressCountry : null;
			;
		} else {
			$savedCardDetails['billingAddressCountry'] = null;
		}

		if ( ! empty( $savedCard->shippingAddressCountry ) ) {
			$savedCardDetails['shippingName'] = ! empty( $savedCard->shippingName ) ? $savedCard->shippingName : null;
			$savedCardDetails['shippingAddressLine1'] = ! empty( $savedCard->shippingAddressLine1 ) ? $savedCard->shippingAddressLine1 : null;
			;
			$savedCardDetails['shippingAddressLine2'] = ! empty( $savedCard->shippingAddressLine2 ) ? $savedCard->shippingAddressLine2 : null;
			;
			$savedCardDetails['shippingAddressCity'] = ! empty( $savedCard->shippingAddressCity ) ? $savedCard->shippingAddressCity : null;
			;
			$savedCardDetails['shippingAddressState'] = ! empty( $savedCard->shippingAddressState ) ? $savedCard->addressState : null;
			;
			$savedCardDetails['shippingAddressZip'] = ! empty( $savedCard->shippingAddressZip ) ? $savedCard->shippingAddressZip : null;
			;
			$savedCardDetails['shippingAddressCountry'] = ! empty( $savedCard->shippingAddressCountry ) ? $savedCard->shippingAddressCountry : null;
			;
		} else {
			$savedCardDetails['shippingAddressCountry'] = null;
		}

		return $savedCardDetails;
	}

	function getSavedCardDetails() {
		$this->validate_request( $_POST );

		$id = $_POST['id'];

		$this->sendDetails(
			function () use ( $id ) {
				return $this->gatherSavedCardDetails( $id );
			},
			__FUNCTION__,
			'Error reading save card details'
		);
	}

	function deleteSavedCard() {
		$this->validate_request( $_POST );

		$id = $_POST['id'];

		$this->sendTransactionActionResult(
			function () use ( $id ) {
				do_action( 'fullstripe_admin_delete_card_capture_action', $id ); // todo: review action name and signature
				$this->db->deleteSavedCard( $id );
			},
			/* translators: Success banner label for successfully deleted save card record */
			__( 'Saved card deleted.', 'wp-full-stripe-free' ),
			Menu::PARAM_VALUE_TAB_SAVED_CARDS,
			/* translators: Error banner label for save card record that cannot deleted. %s: error message */
			__( 'There was an error deleting the saved card: %s', 'wp-full-stripe-free' ),
			__FUNCTION__,
			'Error deleting saved card'
		);
	}

	public function cloneForm() {
		$this->validate_request( $_POST );

		$id = stripslashes( $_POST['id'] );
		$type = stripslashes( $_POST['type'] );
		$layout = stripslashes( $_POST['layout'] );
		$newFormName = stripslashes( $_POST['newFormName'] );
		$newFormDisplayName = stripslashes( $_POST['newFormDisplayName'] );
		$editNewForm = 'true' === stripslashes( $_POST['editNewForm'] );

		$this->sendActionResult(
			function () use ( $id, $type, $layout, $newFormName, $newFormDisplayName, $editNewForm ) {
				$newFormId = ( new CloneFormFactory() )->cloneForm( $id, $type, $layout, $newFormName, $newFormDisplayName );
				$redirectUrl = admin_url( 'admin.php?page=' . Menu::SLUG_FORMS );
				if ( true === $editNewForm ) {
					$redirectUrl = Utils::getFormEditUrl( $newFormId, $type, $layout );
				}

				return [
					'success' => true,
					'msg' =>
						/* translators: Success banner label for cloned form */
						__( 'Form cloned.', 'wp-full-stripe-free' ),
					'redirectURL' => $redirectUrl
				];
			},
			/* translators: Error banner label for a form that cannot be cloned. %s: error message */
			__( 'There was an error cloning the form: %s', 'wp-full-stripe-free' ),
			true
		);
	}

	public function deleteForm() {
		$this->validate_request( $_POST );

		$id = stripslashes( $_POST['id'] );
		$type = stripslashes( $_POST['type'] );
		$layout = stripslashes( $_POST['layout'] );

		$this->sendActionResult(
			function () use ( $id, $type, $layout ) {
				( new DeleteFormFactory() )->deleteForm( $id, $type, $layout );

				return [
					'success' => true,
					'msg' =>
						/* translators: Success banner label for a deleted form */
						__( 'Form deleted.', 'wp-full-stripe-free' ),
					'redirectURL' => admin_url( 'admin.php?page=' . Menu::SLUG_FORMS )
				];
			},
			/* translators: Error banner label for a form that cannot be deleted. %s: error message */
			__( 'There was an error deleting the form: %s', 'wp-full-stripe-free' )
		);
	}

	/**
	 * @param StripeAccountModel $stripeAccountModel
	 */
	protected function saveStripeSettings( StripeAccountModel $stripeAccountModel ) {
		$this->options->setSeveral( [ 
			Options::OPTION_API_TEST_SECRET_KEY => $stripeAccountModel->getTestSecretKey(),
			Options::OPTION_API_TEST_PUBLISHABLE_KEY => $stripeAccountModel->getTestPublishableKey(),
			Options::OPTION_API_LIVE_SECRET_KEY => $stripeAccountModel->getLiveSecretKey(),
			Options::OPTION_API_LIVE_PUBLISHABLE_KEY => $stripeAccountModel->getLivePublishableKey(),
			Options::OPTION_API_MODE => $stripeAccountModel->getApiMode()
		] );
	}

	public function saveStripeAccount() {
		$this->validate_request( $_POST );

		$this->sendSettingsSaveResult(
			StripeAccountModel::class,
			[ $this, 'saveStripeSettings' ],
			/* translators: Success banner label for saving the Stripe settings */
			__( 'Stripe settings saved.', 'wp-full-stripe-free' ),
			admin_url( 'admin.php?page=' . Menu::SLUG_SETTINGS_STRIPE ),
			/* translators: Error banner label for not being able to save the Stripe settings. %s: error message */
			__( 'There was an error saving Stripe settings: %s', 'wp-full-stripe-free' )
		);
	}

	// function to clear stripe settings
	public function clearStripeSettings() {
		$this->validate_request( $_POST );

		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : '';

		try {
			StripeAccessRevocation::disconnect( $this->options, $this->stripe, $mode );
		} catch ( \RuntimeException $e ) {
			wp_send_json( [
				'success' => false,
				'msg' => __( 'Another change to this Stripe connection is in progress. Nothing was changed. Please try again in a moment.', 'wp-full-stripe-free' ),
			] );
		} catch ( \Exception $e ) {
			$this->logger->error( __FUNCTION__, 'Cannot revoke the site credential', $e );
		}

		$return = [
			'success' => true,
			'msg' =>
				/* translators: Success banner label for clearing the Stripe settings */
				__( 'Stripe settings cleared.', 'wp-full-stripe-free' ),
			'redirectURL' => admin_url( 'admin.php?page=' . Menu::SLUG_SETTINGS )
		];

		wp_send_json( $return );
	}

	/**
	 * @param MyAccountModel $stripeAccountModel
	 */
	protected function saveMyAccountSettings( MyAccountModel $stripeAccountModel ) {
		$this->options->setSeveral( [ 
			Options::OPTION_CUSTOMER_PORTAL_SHOW_SUBSCRIPTIONS_TO_CUSTOMERS => $stripeAccountModel->getShowSubscriptions(),
			Options::OPTION_CUSTOMER_PORTAL_LET_SUBSCRIBERS_CANCEL_SUBSCRIPTIONS => $stripeAccountModel->getCancelSubscriptions(),
			Options::OPTION_CUSTOMER_PORTAL_WHEN_CANCEL_SUBSCRIPTIONS => $stripeAccountModel->getWhenCancelSubscriptions(),
			Options::OPTION_CUSTOMER_PORTAL_LET_SUBSCRIBERS_UPDOWNGRADE_SUBSCRIPTIONS => $stripeAccountModel->getUpdowngradeSubscriptions(),
			Options::OPTION_CUSTOMER_PORTAL_SHOW_INVOICES_SECTION => $stripeAccountModel->getShowInvoices(),
			Options::OPTION_CUSTOMER_PORTAL_SCROLLING_PANE_INTO_VIEW => $stripeAccountModel->getScrollingPaneIntoView(),
			Options::OPTION_CUSTOMER_PORTAL_USE_STRIPE_CUSTOMER_PORTAL => $stripeAccountModel->useStripeCustomerPortal(),
		] );
	}

	/**
	 *
	 */
	public function saveMyAccount() {
		$this->validate_request( $_POST );

		$this->sendSettingsSaveResult(
			MyAccountModel::class,
			[ $this, 'saveMyAccountSettings' ],
			/* translators: Success banner label for saving the Customer portal settings */
			__( 'Customer portal settings saved', 'wp-full-stripe-free' ),
			admin_url( 'admin.php?page=' . Menu::SLUG_SETTINGS ),
			/* translators: Error banner label for not being able to save the Customer portal settings. %s: error message */
			__( 'There was an error saving Customer portal settings: %s', 'wp-full-stripe-free' )
		);
	}

	/**
	 * @param WordpressDashboardModel $wpDashboardModel
	 */
	protected function saveWordpressDashboardSettings( WordpressDashboardModel $wpDashboardModel ) {
		$this->options->setSeveral( [ 
			Options::OPTION_DECIMAL_SEPARATOR_SYMBOL => $wpDashboardModel->getDecimalSeparator(),
			Options::OPTION_SHOW_CURRENCY_SYMBOL_INSTEAD_OF_CODE => $wpDashboardModel->getUseSymbolNotCode(),
			Options::OPTION_SHOW_CURRENCY_SIGN_AT_FIRST_POSITION => $wpDashboardModel->getCurrencySymbolAtFirstPosition(),
			Options::OPTION_PUT_WHITESPACE_BETWEEN_CURRENCY_AND_AMOUNT => $wpDashboardModel->getPutSpaceBetweenSymbolAndAmount(),
		] );
	}

	/**
	 *
	 */
	public function saveWordpressDashboard() {
		$this->validate_request( $_POST );

		$this->sendSettingsSaveResult(
			WordpressDashboardModel::class,
			[ $this, 'saveWordpressDashboardSettings' ],
			/* translators: Success banner label for saving the WordPress dashboard settings */
			__( 'WordPress dashboard settings saved', 'wp-full-stripe-free' ),
			admin_url( 'admin.php?page=' . Menu::SLUG_SETTINGS ),
			/* translators: Error banner label for not being able to save the WordPress dashboard settings. %s: error message */
			__( 'There was an error saving WordPress dashboard settings: %s', 'wp-full-stripe-free' )
		);
	}

	/**
	 * @param LogsModel $logsModel
	 */
	protected function saveLogSettings( LogsModel $logsModel ) {
		$this->options->setSeveral( [ 
			Options::OPTION_LOG_LEVEL => $logsModel->getLogLevel(),
			Options::OPTION_LOG_TO_WEB_SERVER => $logsModel->getLogToWebServer(),
			Options::OPTION_CATCH_UNCAUGHT_ERRORS => $logsModel->getCatchUncaughtErrors(),
		] );
	}

	public function saveLogs() {
		$this->validate_request( $_POST );

		$this->sendSettingsSaveResult(
			LogsModel::class,
			[ $this, 'saveLogSettings' ],
			/* translators: Success banner label for saving the Log settings */
			__( 'Log settings saved', 'wp-full-stripe-free' ),
			admin_url( 'admin.php?page=' . Menu::SLUG_SETTINGS ),
			/* translators: Error banner label for not being able to save the Log settings. %s: error message */
			__( 'There was an error saving log settings: %s', 'wp-full-stripe-free' )
		);
	}

	public function emptyLogs() {
		$this->validate_request( $_POST );

		$this->sendActionResult(
			function () {
				$this->db->deleteLogs();

				return [
					'success' => true,
					'msg' =>
						/* translators: Success banner label for deleting the log entries */
						__( 'Log entries deleted', 'wp-full-stripe-free' ),
					'redirectURL' => admin_url( 'admin.php?page=' . Menu::SLUG_SETTINGS )
				];
			},
			/* translators: Error banner label for not being able to delete logs. %s: error message */
			__( 'There was an error emptying the log: %s', 'wp-full-stripe-free' )
		);
	}

	public function toggleLicense() {
		$this->validate_request( $_POST );

		$key    = isset( $_POST['key'] ) ? sanitize_text_field( $_POST['key'] ) : '';
		$action = isset( $_POST['licenseAction'] ) ? sanitize_text_field( $_POST['licenseAction'] ) : '';

		// If key or action are empty, return an error
		if ( empty( $key ) || empty( $action ) ) {
			wp_send_json( [
				'success' => false,
				'msg' =>
					/* translators: Error banner label for not being able to toggle the license */
					__( 'There was an error toggling the license.', 'wp-full-stripe-free' )
			] );
		}

		$this->sendActionResult(
			function () use ( $key, $action ) {
				$return = License::toggle( $key, $action );
				$return['redirectURL'] = admin_url( 'admin.php?page=' . Menu::SLUG_SETTINGS_LICENSE );

				return $return;
			},
			/* translators: Error banner label for not being able to toggle license. %s: error message */
			__( 'There was an error toggling the license: %s', 'wp-full-stripe-free' )
		);
	}

	/**
	 * @param SecurityModel $securityModel
	 */
	protected function saveSecuritySettings( SecurityModel $securityModel ) {
		$this->options->setSeveral( [ 
			Options::OPTION_SECURE_INLINE_FORMS_WITH_GOOGLE_RE_CAPTCHA => $securityModel->getSecureInlineForms(),
			Options::OPTION_SECURE_CHECKOUT_FORMS_WITH_GOOGLE_RE_CAPTCHA => $securityModel->getSecureCheckoutForms(),
			Options::OPTION_SECURE_CUSTOMER_PORTAL_WITH_GOOGLE_RE_CAPTCHA => $securityModel->getSecureCustomerPortal(),
			Options::OPTION_GOOGLE_RE_CAPTCHA_SECRET_KEY => $securityModel->getReCaptchaSecretKey(),
			Options::OPTION_GOOGLE_RE_CAPTCHA_SITE_KEY => $securityModel->getReCaptchaSiteKey(),
		] );
	}

	/**
	 *
	 */
	public function saveSecurity() {
		$this->validate_request( $_POST );

		$this->sendSettingsSaveResult(
			SecurityModel::class,
			[ $this, 'saveSecuritySettings' ],
			/* translators: Success banner label for saving the security settings */
			__( 'Security settings saved', 'wp-full-stripe-free' ),
			admin_url( 'admin.php?page=' . Menu::SLUG_SETTINGS ),
			/* translators: Error banner label for not being able to save the security settings. %s: error message */
			__( 'There was an error saving Security settings: %s', 'wp-full-stripe-free' )
		);
	}

	/**
	 * @param EmailOptionsModel $optionsModel
	 */
	protected function saveEmailOptionsSettings( $optionsModel ) {
		$fromAddress = get_bloginfo( 'admin_email' );
		if ( $optionsModel->getFromAddress() === EmailOptionsViewConstants::FIELD_VALUE_FROM_ADDRESS_CUSTOM ) {
			$fromAddress = $optionsModel->getFromAddressCustom();
		}

		$bccAddresses = $optionsModel->getSendCopyToList();
		if ( ! empty( $optionsModel->getSendCopyToAdmin() ) ) {
			if ( array_search( $optionsModel->getSendCopyToAdmin(), $bccAddresses ) === false ) {
				array_push( $bccAddresses, $optionsModel->getSendCopyToAdmin() );
			}
		}

		$this->options->setSeveral( [ 
			Options::OPTION_EMAIL_NOTIFICATION_SENDER_ADDRESS => $fromAddress,
			Options::OPTION_EMAIL_NOTIFICATION_BCC_ADDRESSES => json_encode( $bccAddresses )
		] );
	}

	/**
	 *
	 */
	public function saveEmailOptions() {
		$this->validate_request( $_POST );

		$this->sendSettingsSaveResult(
			EmailOptionsModel::class,
			[ $this, 'saveEmailOptionsSettings' ],
			/* translators: Success banner label for saving the email notification settings */
			__( 'Email notification options saved', 'wp-full-stripe-free' ),
			Menu::getAdminUrlBySlugAndParams( Menu::SLUG_SETTINGS_EMAIL_NOTIFICATIONS, [ Menu::PARAM_NAME_TAB => Menu::PARAM_VALUE_TAB_OPTIONS ] ),
			/* translators: Error banner label for not being able to save the email notification settings. %s: error message */
			__( 'There was an error saving Email notification options: %s', 'wp-full-stripe-free' )
		);
	}

	/**
	 * @param EmailTemplatesModel $templatesModel
	 */
	protected function saveEmailTemplatesSettings( $templatesModel ) {
		$this->options->set( Options::OPTION_EMAIL_TEMPLATES, json_encode( $templatesModel->getEmailTemplates() ) );
	}

	/**
	 *
	 */
	public function saveEmailTemplates() {
		$this->validate_request( $_POST );

		$this->sendSettingsSaveResult(
			EmailTemplatesModel::class,
			[ $this, 'saveEmailTemplatesSettings' ],
			/* translators: Success banner label for saving the email templates */
			__( 'Email templates saved', 'wp-full-stripe-free' ),
			Menu::getAdminUrlBySlugAndParams( Menu::SLUG_SETTINGS_EMAIL_NOTIFICATIONS, [ Menu::PARAM_NAME_TAB => Menu::PARAM_VALUE_TAB_TEMPLATES ] ),
			/* translators: Error banner label for not being able to save the email templates. %s: error message */
			__( 'There was an error saving the email templates: %s', 'wp-full-stripe-free' )
		);
	}

	/**
	 * @param FormsOptionsModel $formsOptionsModel
	 */
	protected function saveFormsOptionsSettings( $formsOptionsModel ) {
		$this->options->setSeveral( [ 
			Options::OPTION_FILL_IN_EMAIL_FOR_LOGGED_IN_USERS => $formsOptionsModel->getFillInEmail(),
			Options::OPTION_SET_FORM_FIELDS_VIA_URL_PARAMETERS => $formsOptionsModel->getSetFormFieldsViaUrlParameters(),
			Options::OPTION_DEFAULT_BILLING_COUNTRY => $formsOptionsModel->getDefaultBillingCountry(),
			Options::OPTION_DEFAULT_SHOW_PAYMENT_DETAIL => $formsOptionsModel->getShowPaymentDetail(),
		] );
	}

	/**
	 *
	 */
	public function saveFormsOptions() {
		$this->validate_request( $_POST );

		$this->sendSettingsSaveResult(
			FormsOptionsModel::class,
			[ $this, 'saveFormsOptionsSettings' ],
			/* translators: Success banner label for saving the form options */
			__( 'Form options saved', 'wp-full-stripe-free' ),
			Menu::getAdminUrlBySlugAndParams( Menu::SLUG_SETTINGS_FORMS, [ Menu::PARAM_NAME_TAB => Menu::PARAM_VALUE_TAB_OPTIONS ] ),
			/* translators: Error banner label for not being able to save the form options. %s: error message */
			__( 'There was an error saving Forms settings: %s', 'wp-full-stripe-free' )
		);
	}

	/**
	 * @param FormsAppearanceModel $formsAppearanceModel
	 */
	protected function saveFormsAppearanceSettings( $formsAppearanceModel ) {
		$this->options->set( Options::OPTION_FORM_CUSTOM_CSS, $formsAppearanceModel->getCustomCss() );
	}

	/**
	 *
	 */
	public function saveFormsAppearance() {
		$this->validate_request( $_POST );

		$this->sendSettingsSaveResult(
			FormsAppearanceModel::class,
			[ $this, 'saveFormsAppearanceSettings' ],
			/* translators: Success banner label for saving the form appearance settings */
			__( 'Form appearance settings saved', 'wp-full-stripe-free' ),
			Menu::getAdminUrlBySlugAndParams( Menu::SLUG_SETTINGS_FORMS, [ Menu::PARAM_NAME_TAB => Menu::PARAM_VALUE_TAB_APPEARANCE ] ),
			/* translators: Error banner label for not being able to save the form appearance settings. %s: error message */
			__( 'There was an error saving Form appearance settings: %s', 'wp-full-stripe-free' )
		);
	}

	public function createForm() {
		$this->validate_request( $_POST );

		$this->sendActionResult(
			function () {
				$createFormModel = new CreateFormModel( $this->loggerService );
				$bindingResult = $createFormModel->bind();
				if ( $bindingResult->hasErrors() ) {
					return Utils::generateReturnValueFromBindings( $bindingResult );
				}

				$formId = ( new CreateFormFactory() )->createForm( $createFormModel );

				return [
					'success' => true,
					'msg' =>
						/* translators: Success banner label for creating a new form */
						__( 'Form created. Redirecting to edit form.', 'wp-full-stripe-free' ),
					'redirectURL' => Utils::getFormEditUrl( $formId, $createFormModel->getType(), $createFormModel->getLayout() )
				];
			},
			/* translators: Error banner label for not being able to create a new form. %s: error message */
			__( 'There was an error creating the form: %s', 'wp-full-stripe-free' )
		);
	}

	protected function getOnetimeProductsForSelector() {
		$result = [];

		$onetimePrices = $this->stripe->getOnetimePrices();

		foreach ( $onetimePrices as $onetimePrice ) {
			$product = new \StdClass;

			$product->stripePriceId = $onetimePrice->id;
			$product->currency = $onetimePrice->currency;
			$product->price = $onetimePrice->unit_amount;
			$product->name = $onetimePrice->product->name;

			array_push( $result, $product );
		}

		return $result;
	}

	public function getOnetimeProducts() {
		$this->validate_request( $_POST );

		$this->sendActionResult(
			function () {
				return [
					'success' => true,
					'data' => $this->getOnetimeProductsForSelector()
				];
			},
			/* translators: Error message for not being able to fetch one-time products from Stripe. %s: error message */
			__( 'There was an error getting one-time products from Stripe: %s', 'wp-full-stripe-free' )
		);
	}

	protected function getTaxRatesForSelector() {
		$result = [];

		$taxRates = $this->stripe->getTaxRates();

		foreach ( $taxRates as $taxRate ) {
			$res = new \StdClass;

			$res->displayName = $taxRate->display_name;
			$res->inclusive = $taxRate->inclusive;
			$res->countryCode = $taxRate->country;
			$res->countryLabel = Countries::getCountryLabel( $taxRate->country );
			$res->stateCode = $taxRate->state;
			$res->stateLabel = States::getStateNameFor( $taxRate->state );
			$res->percentage = $taxRate->percentage;
			$res->jurisdiction = $taxRate->jurisdiction;
			$res->taxRateId = $taxRate->id;

			array_push( $result, $res );
		}

		return $result;
	}

	public function getTaxRates() {
		$this->validate_request( $_POST );

		$this->sendActionResult(
			function () {
				return [
					'success' => true,
					'data' => $this->getTaxRatesForSelector()
				];
			},
			/* translators: Error message for not being able to fetch tax rates from Stripe. %s: error message */
			__( 'There was an error getting tax rates from Stripe: %s', 'wp-full-stripe-free' )
		);
	}

	public function sendTestEmail() {
		$this->validate_request( $_POST );

		$this->sendActionResult(
			function () {
				$data = json_decode( rawurldecode( stripslashes( $_POST['data'] ) ) );
				$this->mailer->sendTestEmail( $data->recipients, $data->subject, $data->body, $data->emailTemplateType );

				return [
					'success' => true,
					'msg' =>
						/* translators: Success banner label after the test email is sent */
						__( 'Test email sent successfully.', 'wp-full-stripe-free' )
				];
			},
			/* translators: Error message for not being able to send the test email. %s: error message */
			__( 'There was an error sending the test email: %s', 'wp-full-stripe-free' ),
			false,
			__FUNCTION__,
			'Error sending test email'
		);
	}

	/**
	 * @param \StripeWPFS\Stripe\Price $recurringPrice
	 */
	protected function isSupportedRecurringPrice( $recurringPrice ) {
		if ( $recurringPrice->billing_scheme === \StripeWPFS\Stripe\Price::BILLING_SCHEME_TIERED ) {
			return false;
		}
		if ( $recurringPrice->recurring->usage_type === 'metered' ) {
			return false;
		}

		return true;
	}

	protected function getRecurringProductsForSelector() {
		$result = [];

		$recurringPrices = $this->stripe->getRecurringPrices();
		foreach ( $recurringPrices as $recurringPrice ) {
			if ( ! $this->isSupportedRecurringPrice( $recurringPrice ) ) {
				continue;
			}

			$product = new \StdClass;
			$product->stripePriceId = $recurringPrice->id;
			$product->currency = $recurringPrice->currency;
			$product->price = $recurringPrice->unit_amount;
			$product->name = $recurringPrice->product->name;
			$product->interval = $recurringPrice->recurring->interval;
			$product->intervalCount = $recurringPrice->recurring->interval_count;
			$product->usageType = $recurringPrice->recurring->usage_type;     // licensed or metered
			$product->billingScheme = $recurringPrice->billing_scheme;              // per_unit or tiered
			$product->tiersMode = $recurringPrice->tiers_mode;                  // graduated or volume

			array_push( $result, $product );
		}

		return $result;
	}

	public function createProduct() {
		$this->validate_request( $_POST );

		if ( ! isset( $_POST['data'] ) || ! is_array( $_POST['data'] ) ) {
			wp_send_json_error( [ 'msg' => __( 'Invalid data provided.', 'wp-full-stripe-free' ) ] );
		}

		$data = json_decode( rawurldecode( stripslashes( json_encode( $_POST['data'] ) ) ) );

		$requiredFields = [
			'name' => __( 'Product name is required.', 'wp-full-stripe-free' ),
			'currency' => __( 'Currency is required.', 'wp-full-stripe-free' ),
			'price' => __( 'Price is required and must be a number.', 'wp-full-stripe-free' )
		];

		foreach ( $requiredFields as $field => $errorMessage ) {
			if ( empty( $data->$field ) || ( $field === 'price' && ! is_numeric( $data->$field ) ) ) {
				wp_send_json_error( [ 'msg' => $errorMessage ] );
			}
		}

		try {
			$this->stripe->createProduct( ...array_values( (array) $data ) );

			wp_send_json_success( [ 'msg' => __( 'Product created.', 'wp-full-stripe-free' ) ] );
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Error creating product', $ex );
			wp_send_json_error( [ 'msg' => sprintf(
				/* translators: %s: error message */
				__( 'There was an error in creating the product: %s', 'wp-full-stripe-free' ),
				$ex->getMessage()
			) ] );
		}
	}

	public function getRecurringProducts() {
		$this->validate_request( $_POST );

		$this->sendActionResult(
			function () {
				return [
					'success' => true,
					'data' => $this->getRecurringProductsForSelector()
				];
			},
			/* translators: Error message for not being able to fetch recurring products from Stripe. %s: error message */
			__( 'There was an error getting recurring products from Stripe: %s', 'wp-full-stripe-free' )
		);
	}

	/**
	 * Stripe Web hook handler
	 */
	function fullstripe_handle_wpfs_event() {
		$this->eventHandler->handleRESTRequest();
	}

	/**
	 * @param $interval
	 * @param $intervalCount
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function getSubscriptionIntervalLabel( $interval, $intervalCount ) {
		$formatStr = '';

		switch ( $interval ) {
			case 'day':
				if ( $intervalCount == 1 ) {
					$formatStr = __( 'daily', 'wp-full-stripe-free' );
				} else {
					/* translators: Recurring pricing descriptor.
					 * p1: interval count
					 */
					$formatStr = _n(
						'every %d day',
						'every %d days',
						$intervalCount,
						'wp-full-stripe-free'
					);
				}
				break;

			case 'week':
				if ( $intervalCount == 1 ) {
					$formatStr = __( 'weekly', 'wp-full-stripe-free' );
				} else {
					/* translators: Recurring pricing descriptor.
					 * p1: interval count
					 */
					$formatStr = _n(
						'every %d week',
						'every %d weeks',
						$intervalCount,
						'wp-full-stripe-free'
					);
				}
				break;

			case 'month':
				if ( $intervalCount == 1 ) {
					$formatStr = __( 'monthly', 'wp-full-stripe-free' );
				} else {
					/* translators: Recurring pricing descriptor.
					 * p1: interval count
					 */
					$formatStr = _n(
						'every %d month',
						'every %d months',
						$intervalCount,
						'wp-full-stripe-free'
					);
				}
				break;

			case 'year':
				if ( $intervalCount == 1 ) {
					$formatStr = __( 'annually', 'wp-full-stripe-free' );
				} else {
					/* translators: Recurring pricing descriptor.
					 * p1: interval count
					 */
					$formatStr = _n(
						'every %d year',
						'every %d years',
						$intervalCount,
						'wp-full-stripe-free'
					);
				}
				break;

			default:
				throw new \Exception( sprintf( '%s.%s(): Unknown plan interval \'%s\'.', __CLASS__, __FUNCTION__, $interval ) );
		}

		return $intervalCount > 1 ? sprintf( $formatStr, $intervalCount ) : $formatStr;
	}

	/**
	 * @param StaticContext $context
	 * @param $subscription
	 * @param $stripePlan \StripeWPFS\Stripe\Price|null
	 * @return string
	 */
	public static function getSubscriptionAmountLabel( $context, $subscription, $stripePlan ) {
		$amountLabel = '';

		if ( ! is_null( $stripePlan ) ) {
			$formattedAmount = Currencies::formatAndEscape( $context, $stripePlan->currency, $stripePlan->unit_amount );

			if ( $subscription->quantity > 1 ) {
				$amountLabel = sprintf( '%1$dx %2$s', $subscription->quantity, $formattedAmount );
			} else {
				$amountLabel = $formattedAmount;
			}
		} else {
			if ( $subscription->quantity > 1 ) {
				$amountLabel = sprintf( '%1$dx %2$s', $subscription->quantity, $subscription->planID );
			} else {
				$amountLabel = $subscription->planID;
			}
		}

		return $amountLabel;
	}

	/**
	 * @param object $record A payment, subscription, donation or saved card row.
	 *
	 * @return string|null When the customer accepted the terms of use, in the site's date and time
	 *                     format, or null when the record holds no acceptance.
	 */
	private function formatTermsOfUseAcceptedAt( $record ) {
		if ( empty( $record->termsOfUseAcceptedAt ) ) {
			return null;
		}

		return Utils::formatTimestampWithWordpressDateTimeFormat( strtotime( $record->termsOfUseAcceptedAt . ' UTC' ) );
	}

	/**
	 * @param $record
	 *
	 * @return mixed
	 */
	private function decodeCustomFieldsJSON( $record ) {
		$rawCustomFields = [];
		if ( isset( $record ) && isset( $record->customFields ) ) {
			$rawCustomFields = json_decode( $record->customFields, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$rawCustomFields = [];
			}
		}

		return $rawCustomFields;
	}

	/**
	 * @param $data
	 *
	 * @return mixed
	 */
	public function triggerWebhook( $form, $data ) {
		if ( ! isset( $form->webhook ) ) {
			return;
		}

		$webhook = $form->webhook;
		$webhook = json_decode( $webhook, true );

		if ( ! isset( $webhook['url'] ) ) {
			return;
		}

		$webhookUrl     = $webhook['url'];
		$webhookHeaders = isset( $webhook['headers'] ) ? $webhook['headers'] : [];
		$webhookData    = apply_filters( 'fullstripe_webhook_data', $data );

		$webhookData = json_encode( $webhookData );

		$webhookResponse = wp_remote_post(
			$webhookUrl,
			[
				'headers' => $webhookHeaders,
				'body'    => $webhookData,
			]
		);

		$webhookResponseCode = wp_remote_retrieve_response_code( $webhookResponse );
		$webhookResponseBody = wp_remote_retrieve_body( $webhookResponse );

		$this->logger->info(
			__FUNCTION__,
			"Webhook response code: $webhookResponseCode, response body: $webhookResponseBody"
		);
	}
 
	/**
	 * On plugin activate.
	 *
	 * @return void
	 */
	public function activate() {
		if ( get_option( Main::ONBOARDING_WIZARD_OPTION_NAME ) === false ) {
			update_option( Main::ONBOARDING_WIZARD_OPTION_NAME, 'yes' );
		}
	}

	/**
	 * Redirection for onboarding wizard.
	 * 
	 * @return void
	 */
	public function maybeRedirect() {
		if ( 'yes' !== get_option( Main::ONBOARDING_WIZARD_OPTION_NAME, 'no' ) ) {
			return;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
			return;
		}

		update_option( Main::ONBOARDING_WIZARD_OPTION_NAME, 'no' );
		wp_safe_redirect( admin_url( 'admin.php?page=wpfs-settings-stripe&onboarding=true' ) );
		exit;
	}

	/**
	 * Form preview.
	 */
	public function previewForm() {
		$this->validate_request( $_GET );

		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			wp_die( esc_html__( 'Invalid request', 'wp-full-stripe-free' ) );
		}

		$shortcode      = isset( $_GET['shortcode'] ) ? wp_unslash( $_GET['shortcode'] ) : '';
		$stylesheet_url = get_stylesheet_uri();

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo('charset'); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<link rel="stylesheet" href="<?php echo esc_url( $stylesheet_url ); ?>">
			<style>
				.shortcode-preview {
					padding: 20px;
					pointer-events: none; /* Disable interactions */
				}
			</style>
			<?php wp_head(); ?>
		</head>
		<body>
			<div class="shortcode-preview">
				<?php echo do_shortcode( $shortcode ); ?>
			</div>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Add admin bar notice for test mode.
	 */
	public function adminBarNotice( $menu ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$is_test = Utils::isTestMode();

		$menu->add_menu(
			[
				'id'     => 'wpfs-menu-notice',
				'parent' => 'top-secondary',
				'title'  => __( 'WP Full Pay', 'wp-full-stripe-free' ) . ( $is_test ? ': ' . __( 'Test Mode', 'wp-full-stripe-free' ) : '' ),
				'meta'   => [
					'class' => $is_test ? 'wpfs-test-mode' : '',
				],
				'href'   => Menu::getAdminUrlBySlug( $is_test ? Menu::SLUG_SETTINGS_STRIPE : Menu::SLUG_SETTINGS ),
			]
		);

		$submenus = [
			'wpfs-menu-forms' => [
				'title' => __( 'Payment Forms', 'wp-full-stripe-free' ),
				'href'  => Menu::getAdminUrlBySlug( Menu::SLUG_FORMS ),
			],
			'wpfs-menu-transactions' => [
				'title' => __( 'Transactions', 'wp-full-stripe-free' ),
				'href'  => Menu::getAdminUrlBySlug( Menu::SLUG_TRANSACTIONS ),
			],
			'wpfs-menu-settings' => [
				'title' => __( 'Settings', 'wp-full-stripe-free' ),
				'href'  => Menu::getAdminUrlBySlug( Menu::SLUG_SETTINGS ),
			],
		];

		foreach ( $submenus as $id => $submenu ) {
			$menu->add_menu(
				[
					'parent' => 'wpfs-menu-notice',
					'id'     => $id,
					'title'  => $submenu['title'],
					'href'   => $submenu['href'],
				]
			);
		}
	}

	/**
	 * Add admin bar notice CSS.
	 */
	public function adminBarNoticeCSS( $menu ) {
		if ( ! Utils::isTestMode() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<style>
			#wpadminbar .wpfs-test-mode > .ab-item {
				color: #fff;
				background-color: #568ee8;
			}
		</style>
		<?php
	}

	/**
	 * Validate AJAX request.
	 * @param array<string, mixed> $post
	 * @return void
	 */
	private function validate_request( $post ) {
		$capability = 'manage_options';
		if ( Utils::isDemoMode() ) {
			$capability = 'read';
		}

		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have sufficient permissions.', 'wp-full-stripe-free' ) ], 403 );
		}

		if ( ! isset( $post['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $post['nonce'] ) ), 'wp-full-stripe-admin-nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'wp-full-stripe-free' ) ], 400 );
		}
	}

	/**
	 * Answers an AJAX action with what $action returns, or with `success: false` and $errorMessage when it fails.
	 *
	 * @param callable(): array<string,mixed> $action
	 * @param string $errorMessage Translated, with %s for the failure's message.
	 * @param bool $showUserFriendlyMessage Whether a UserFriendlyException's message is shown as it is, unlogged.
	 * @param string $function The action's method, to log a failure under.
	 * @param string $logMessage Logged with a failure; nothing is logged without it.
	 */
	private function sendActionResult( callable $action, string $errorMessage, bool $showUserFriendlyMessage = false, string $function = '', string $logMessage = '' ): void {
		try {
			$return = $action();
		} catch ( \Exception $e ) {
			if ( $showUserFriendlyMessage && $e instanceof UserFriendlyException ) {
				$return = [
					'success' => false,
					'msg' => $e->getMessage()
				];
			} else {
				if ( '' !== $logMessage ) {
					$this->logger->error( $function, $logMessage, $e );
				}
				$return = [
					'success' => false,
					'msg' => sprintf( $errorMessage, $e->getMessage() )
				];
			}
		}

		wp_send_json( $return );
	}

	/**
	 * Answers a refund, capture, cancel or delete with its banner and the transactions tab to reload.
	 *
	 * @param callable(): void $action
	 * @param string $successMessage Translated.
	 * @param string $tab The tab of the transactions page to reload.
	 * @param string $errorMessage Translated, with %s for the failure's message.
	 * @param string $function The action's method, to log a failure under.
	 * @param string $logMessage
	 */
	private function sendTransactionActionResult( callable $action, string $successMessage, string $tab, string $errorMessage, string $function, string $logMessage ): void {
		$this->sendActionResult(
			function () use ( $action, $successMessage, $tab ) {
				$action();

				return [
					'success' => true,
					'msg' => $successMessage,
					'redirectURL' => self::getTransactionsTabUrl( $tab )
				];
			},
			$errorMessage,
			true,
			$function,
			$logMessage
		);
	}

	private static function getTransactionsTabUrl( string $tab ): string {
		return add_query_arg(
			[
				'page' => Menu::SLUG_TRANSACTIONS,
				'tab' => $tab
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Binds the posted settings and saves them, answering with the fields to fix, or with the banner and the page to load.
	 *
	 * @template TModel of object
	 * @param class-string<TModel> $modelClass
	 * @param callable(TModel): void $save
	 * @param string $savedMessage Translated.
	 * @param string $redirectUrl
	 * @param string $errorMessage Translated, with %s for the failure's message.
	 */
	private function sendSettingsSaveResult( string $modelClass, callable $save, string $savedMessage, string $redirectUrl, string $errorMessage ): void {
		$this->sendActionResult(
			function () use ( $modelClass, $save, $savedMessage, $redirectUrl ) {
				$model = new $modelClass( $this->loggerService );
				$bindingResult = $model->bind();
				if ( $bindingResult->hasErrors() ) {
					return Utils::generateReturnValueFromBindings( $bindingResult );
				}

				$save( $model );

				return [
					'success' => true,
					'msg' => $savedMessage,
					'redirectURL' => $redirectUrl
				];
			},
			$errorMessage
		);
	}

	/**
	 * Answers with the details $gather finds, or with no data when it fails.
	 *
	 * @param callable(): array<string,mixed> $gather
	 * @param string $function The action's method, to log a failure under.
	 * @param string $logMessage
	 */
	private function sendDetails( callable $gather, string $function, string $logMessage ): void {
		try {
			$return = [
				'success' => true,
				'data' => $gather()
			];
		} catch ( UserFriendlyException $e ) {
			$return = [
				'success' => false,
				'data' => []
			];
		} catch ( \Exception $e ) {
			$this->logger->error( $function, $logMessage, $e );
			$return = [
				'success' => false,
				'data' => []
			];
		}

		wp_send_json( $return );
	}
}
