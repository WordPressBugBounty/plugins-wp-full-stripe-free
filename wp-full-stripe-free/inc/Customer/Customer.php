<?php

namespace ThemeIsle\WPFullPay\Customer;

use ThemeIsle\WPFullPay\Checkout\CheckoutSubmissionService;
use ThemeIsle\WPFullPay\Context\FindStripeCustomerAddOn;
use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Exceptions\InvalidTaxIdException;
use ThemeIsle\WPFullPay\Exceptions\UserFriendlyException;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Models\CheckoutDonationFormModel;
use ThemeIsle\WPFullPay\Forms\Models\CheckoutPaymentFormModel;
use ThemeIsle\WPFullPay\Forms\Models\CheckoutSubscriptionFormModel;
use ThemeIsle\WPFullPay\Forms\Models\DonationFormModel;
use ThemeIsle\WPFullPay\Forms\Models\FormModel;
use ThemeIsle\WPFullPay\Forms\Models\InlineDonationFormModel;
use ThemeIsle\WPFullPay\Forms\Models\InlinePaymentFormModel;
use ThemeIsle\WPFullPay\Forms\Models\InlineSubscriptionFormModel;
use ThemeIsle\WPFullPay\Forms\Models\PaymentFormModel;
use ThemeIsle\WPFullPay\Forms\Models\SubscriptionFormModel;
use ThemeIsle\WPFullPay\Forms\Views\DonationFormViewConstants;
use ThemeIsle\WPFullPay\Forms\Views\FormView;
use ThemeIsle\WPFullPay\Forms\Views\FormViewInlineTaxAddOnConstants;
use ThemeIsle\WPFullPay\Forms\Validators\SubscriptionFormValidator;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Locale\Localization;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Macros\DonationMacroReplacer;
use ThemeIsle\WPFullPay\Macros\OneTimePaymentMacroReplacer;
use ThemeIsle\WPFullPay\Macros\SaveCardMacroReplacer;
use ThemeIsle\WPFullPay\Macros\SubscriptionMacroReplacer;
use ThemeIsle\WPFullPay\Mailer\Mailer;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\PaymentMethods;
use ThemeIsle\WPFullPay\Payments\FreePayment;
use ThemeIsle\WPFullPay\Payments\LatestCharge;
use ThemeIsle\WPFullPay\Payments\PaymentIntentBinding;
use ThemeIsle\WPFullPay\Payments\PaymentIntentBindingContext;
use ThemeIsle\WPFullPay\Pricing\PriceCalculator;
use ThemeIsle\WPFullPay\Pricing\Pricing;
use ThemeIsle\WPFullPay\Pricing\RecoveryFee;
use ThemeIsle\WPFullPay\Results\ChargeResult;
use ThemeIsle\WPFullPay\Results\DonationPaymentIntentResult;
use ThemeIsle\WPFullPay\Results\PaymentIntentResult;
use ThemeIsle\WPFullPay\Results\SetupIntentResult;
use ThemeIsle\WPFullPay\Results\SubscriptionResult;
use ThemeIsle\WPFullPay\Results\TransactionResult;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\Subscriptions\FeeSync;
use ThemeIsle\WPFullPay\ThankYou\ThankYouAddOn;
use ThemeIsle\WPFullPay\TransactionData\DonationTransactionData;
use ThemeIsle\WPFullPay\TransactionData\OneTimePaymentTransactionData;
use ThemeIsle\WPFullPay\TransactionData\PaymentTransactionData;
use ThemeIsle\WPFullPay\TransactionData\SaveCardTransactionData;
use ThemeIsle\WPFullPay\TransactionData\SubscriptionTransactionData;
use ThemeIsle\WPFullPay\TransactionData\TransactionDataService;
use ThemeIsle\WPFullPay\Utils;

/**
 * Class Customer deals with customer front-end input i.e. payment forms submission
 */
class Customer {
	use DonationToolsAddOn;
	use ThankYouAddOn;
	use LoggerAddOn;
	use StaticContextAddOn;
	use FindStripeCustomerAddOn;
	use OneTimeInvoiceCreatorAddOn;

	// PaymentIntent metadata key holding the pre-tax base amount, so tax can always be
	// recomputed from a clean base instead of an already tax-inclusive amount (#413).
	const METADATA_KEY_PRETAX_BASE_AMOUNT = 'wpfs_pretax_base_amount';

	// PaymentIntent metadata key holding the fee recovery choice, which the form reloaded by the
	// Stripe redirect no longer shows (#704).
	/** @var string Metadata key for fee recovery acceptance. */
	const METADATA_KEY_FEE_RECOVERY_ACCEPTED = 'wpfs_fee_recovery_accepted';

	// PaymentIntent metadata key holding the recovery fee the save request added, so completing the
	// payment or donation does not recompute it from fee settings that may have changed since.
	/** @var string Metadata key for the recovery fee in the smallest currency unit. */
	const METADATA_KEY_RECOVERY_FEE = 'wpfs_recovery_fee';

	// PaymentIntent metadata key holding the amount the one-time donation save request charged, so the
	// charge request records it even if the form's presets changed since.
	/** @var string Metadata key for the donation amount without the recovery fee. */
	const METADATA_KEY_DONATION_AMOUNT = DonationFormModel::METADATA_KEY_DONATION_AMOUNT;

	// Stripe documents client-secret updates of a PaymentIntent without saying which fields they may
	// set, so the values above are signed and only trusted when the signature matches.
	/** @var string Metadata key for the signature of the saved charge values. */
	const METADATA_KEY_CHARGE_SIGNATURE = 'wpfs_charge_signature';

	/** @var string[] The inline payment values the draft signs. */
	const PAYMENT_CHARGE_BASIS_KEYS = [ self::METADATA_KEY_PRETAX_BASE_AMOUNT, self::METADATA_KEY_FEE_RECOVERY_ACCEPTED, self::METADATA_KEY_RECOVERY_FEE ];

	/** @var string[] The one-time donation values the save request signs. */
	const DONATION_CHARGE_BASIS_KEYS = [ self::METADATA_KEY_DONATION_AMOUNT, self::METADATA_KEY_FEE_RECOVERY_ACCEPTED, self::METADATA_KEY_RECOVERY_FEE ];

	// PaymentIntent metadata key naming the subscription whose first invoice a recurring donation's
	// PaymentIntent pays, so the request after 3D Secure finishes that subscription (#738).
	/** @var string Metadata key for the subscription of a recurring donation. */
	const METADATA_KEY_SUBSCRIPTION_ID = 'wpfs_subscription_id';

	/** @var string Metadata key for the invoice an inline payment was given, which a retry finishing its completion reuses. */
	const METADATA_KEY_INVOICE_ID = 'wpfs_invoice_id';

	/** @var string Metadata key for a donation's custom fields, as its save request or first request recorded them. */
	const METADATA_KEY_CUSTOM_FIELDS = 'custom_fields';

	/** @var string Metadata key for the signature of a donation's custom fields, which are only restored when it matches. */
	const METADATA_KEY_CUSTOM_FIELDS_SIGNATURE = 'wpfs_custom_fields_signature';

	/** @var string POST flag the front-end sets when the final payable amount is 0 (e.g. a 100%-off coupon). */
	const PARAM_WPFS_FREE_TRANSACTION = 'wpfs-free-transaction';

	/** @var list<string> Statuses of a PaymentIntent whose money Stripe took, or holds for capture. */
	const CHARGED_PAYMENT_INTENT_STATUSES = [
		\StripeWPFS\Stripe\PaymentIntent::STATUS_SUCCEEDED,
		\StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_CAPTURE,
		\StripeWPFS\Stripe\PaymentIntent::STATUS_PROCESSING,
	];

	/** @var int Seconds after which the claim of a request completing a payment counts as abandoned. */
	const COMPLETION_CLAIM_TIMEOUT = 5 * MINUTE_IN_SECONDS;

	/** @var string POST field carrying the client_secret of the PaymentIntent being submitted. */
	const PARAM_WPFS_STRIPE_CLIENT_SECRET = 'wpfs-stripe-client-secret';

	/** @var Stripe $stripe */
	protected $stripe = null;

	/** @var Database $db */
	protected $db = null;

	/** @var Mailer $mailer */
	protected $mailer = null;

	/** @var TransactionDataService */
	private $transactionDataService = null;

	/** @var CheckoutSubmissionService */
	private $checkoutSubmissionService = null;

	/** @var Options */
	protected $options = null;

	public function __construct( $loggerService ) {
		$this->setup( $loggerService );
		$this->hooks();
	}

	private function setup( $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_RUNTIME );
		$this->options = new Options();

		$this->initStaticContext();

		$this->stripe = new Stripe( Stripe::getStripeAuthenticationToken( $this->staticContext ), $this->loggerService );
		$this->db = new Database();
		$this->mailer = new Mailer( $this->loggerService );
		$this->transactionDataService = new TransactionDataService();
		$this->checkoutSubmissionService = new CheckoutSubmissionService( $this->loggerService );
	}

	private function hooks() {
		add_action( 'wp_ajax_wp_full_stripe_confirm_redirect', [ $this, 'fullstripe_confirm_redirect' ] );
		add_action( 'wp_ajax_nopriv_wp_full_stripe_confirm_redirect', [ $this, 'fullstripe_confirm_redirect' ] );

		add_action( 'wp_ajax_wpfs-save-draft-transaction', [ $this, 'fullstripe_save_draft_transaction' ] );
		add_action( 'wp_ajax_nopriv_wpfs-save-draft-transaction', [ $this, 'fullstripe_save_draft_transaction' ] );

		add_action( 'wp_ajax_wpfs-check-coupon', [ $this, 'fullstripe_check_coupon' ] );
		add_action( 'wp_ajax_nopriv_wpfs-check-coupon', [ $this, 'fullstripe_check_coupon' ] );

		add_action( 'wp_ajax_wp_get_Setup_Intent_Client_Secret', [ $this, 'get_Setup_Intent_Client_Secret' ] );
		add_action( 'wp_ajax_nopriv_wp_get_Setup_Intent_Client_Secret', [ $this, 'get_Setup_Intent_Client_Secret' ] );

		add_action( 'wp_ajax_wp_full_stripe_inline_payment_charge', [ $this, 'fullstripe_inline_payment_charge' ] );
		add_action( 'wp_ajax_nopriv_wp_full_stripe_inline_payment_charge', [ $this, 'fullstripe_inline_payment_charge' ] );

		add_action( 'wp_ajax_wp_full_stripe_inline_donation_charge', [ $this, 'fullstripe_inline_donation_charge' ] );
		add_action( 'wp_ajax_nopriv_wp_full_stripe_inline_donation_charge', [ $this, 'fullstripe_inline_donation_charge' ] );

		add_action( 'wp_ajax_wp_full_stripe_inline_subscription_charge', [
			$this,
			'fullstripe_inline_subscription_charge'
		] );
		add_action( 'wp_ajax_nopriv_wp_full_stripe_inline_subscription_charge', [
			$this,
			'fullstripe_inline_subscription_charge'
		] );
		add_action( 'wp_ajax_wp_full_stripe_popup_payment_charge', [
			$this,
			'fullstripe_checkout_payment_charge'
		] );
		add_action( 'wp_ajax_nopriv_wp_full_stripe_popup_payment_charge', [
			$this,
			'fullstripe_checkout_payment_charge'
		] );
		add_action( 'wp_ajax_wp_full_stripe_popup_donation_charge', [
			$this,
			'fullstripe_checkout_donation_charge'
		] );
		add_action( 'wp_ajax_nopriv_wp_full_stripe_popup_donation_charge', [
			$this,
			'fullstripe_checkout_donation_charge'
		] );
		add_action( 'wp_ajax_wp_full_stripe_popup_subscription_charge', [
			$this,
			'fullstripe_checkout_subscription_charge'
		] );
		add_action( 'wp_ajax_nopriv_wp_full_stripe_popup_subscription_charge', [
			$this,
			'fullstripe_checkout_subscription_charge'
		] );
		add_action( 'wp_ajax_wp_full_stripe_handle_checkout_session', [
			$this,
			'fullstripe_handle_checkout_session'
		] );
		add_action( 'wp_ajax_nopriv_wp_full_stripe_handle_checkout_session', [
			$this,
			'fullstripe_handle_checkout_session'
		] );

		// actions for pricing/tax calculations
		add_action( 'wp_ajax_wpfs-calculate-pricing', [ $this, 'calculatePricing' ] );
		add_action( 'wp_ajax_nopriv_wpfs-calculate-pricing', [ $this, 'calculatePricing' ] );

		// action for updating payment intent
		add_action( 'wp_ajax_wpfs-update-payment-intent', [ $this, 'updatePaymentIntent' ] );
		add_action( 'wp_ajax_nopriv_wpfs-update-payment-intent', [ $this, 'updatePaymentIntent' ] );

		add_action( 'wp_ajax_wpfs-save-one-time-donation', [ $this, 'fullstripe_save_onetime_donation' ] );
		add_action( 'wp_ajax_nopriv_wpfs-save-one-time-donation', [ $this, 'fullstripe_save_onetime_donation' ] );

		add_action( 'wp_ajax_wp_full_stripe_onetime_donation_charge', [ $this, 'fullstripe_onetime_donation_charge' ] );
		add_action( 'wp_ajax_nopriv_wp_full_stripe_onetime_donation_charge', [ $this, 'fullstripe_onetime_donation_charge' ] );

		add_action( 'wp_ajax_wpfs_update_failed_payment_status', [ $this, 'update_failed_payment_status' ] );
		add_action( 'wp_ajax_nopriv_wpfs_update_failed_payment_status', [ $this, 'update_failed_payment_status' ] );

	}

	function fullstripe_handle_checkout_session() {
		$this->logger->debug( __FUNCTION__, 'CALLED' );

		$submitHash = isset(
			$_GET[ CheckoutSubmissionService::STRIPE_CALLBACK_PARAM_WPFS_POPUP_FORM_SUBMIT_HASH ]
		) ? sanitize_text_field( $_GET[ CheckoutSubmissionService::STRIPE_CALLBACK_PARAM_WPFS_POPUP_FORM_SUBMIT_HASH ] ) : null;
		$submitStatus = isset(
			$_GET[ CheckoutSubmissionService::STRIPE_CALLBACK_PARAM_WPFS_STATUS ]
		) ? sanitize_text_field( $_GET[ CheckoutSubmissionService::STRIPE_CALLBACK_PARAM_WPFS_STATUS ] ) : null;

		$this->logger->debug( __FUNCTION__, "submitHash={$submitHash}, submitStatus={$submitStatus}" );

		wp_redirect( $this->checkoutSubmissionService->completeReturnedSubmission( $submitHash, $submitStatus ) );

		$this->logger->debug( __FUNCTION__, 'FINISHED' );

		exit;
	}

	protected function createCustomerContext( $formModel ) {
		if (
			$formModel instanceof PaymentFormModel ||
			$formModel instanceof SubscriptionFormModel
		) {
			return ( new CustomerContextCreatorPaymentForm( $formModel ) )->getContext();
		} else if ( $formModel instanceof DonationFormModel ) {
			return ( new CustomerContextCreatorDonationForm( $formModel ) )->getContext();
		} else {
			throw new \Exception( __CLASS__ . '::' . __FUNCTION__ . '(): form model type not supported.' );
		}
	}

	protected function createSubscriptionContext( $formModel, $transactionData ) {
		if ( $formModel instanceof SubscriptionFormModel ) {
			return ( new SubscriptionContextCreator( $formModel, $transactionData, $this->stripe ) )->getContext();
		} else {
			throw new \Exception( __CLASS__ . '::' . __FUNCTION__ . '(): form model type not supported.' );
		}
	}

	/**
	 * @param SubscriptionFormModel $formModel
	 * @param SubscriptionTransactionData $transactionData
	 * @param CreateSubscriptionOptions $options
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	protected function createSubscription( $formModel, $transactionData, $options ) {
		return $this->stripe->createSubscriptionForCustomer( $this->createSubscriptionContext( $formModel, $transactionData ), $options );
	}

	/**
	 * Check if the current request is a free transaction request.
	 * If so, we skip confirmation step and record the transaction directly.
	 *
	 * @return bool
	 */
	private function isFreeTransactionRequest() {
		return isset( $_POST[ self::PARAM_WPFS_FREE_TRANSACTION ] )
			&& '1' === sanitize_text_field( wp_unslash( $_POST[ self::PARAM_WPFS_FREE_TRANSACTION ] ) );
	}

	/**
	 * Whether the request posts a PaymentIntent that Stripe already charged or authorized. A fully discounted
	 * payment never confirms the one the page created, so such a request is not a free payment (#759).
	 *
	 * @param PaymentFormModel $paymentFormModel
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	private function isPostedPaymentIntentCharged( $paymentFormModel ): bool {
		$paymentIntentId = $paymentFormModel->getStripePaymentIntentId();
		if ( empty( $paymentIntentId ) ) {
			return false;
		}

		return in_array( $this->stripe->retrievePaymentIntent( $paymentIntentId )->status, self::CHARGED_PAYMENT_INTENT_STATUSES, true );
	}

	/**
	 * Create or retrieve a Stripe Customer for a free inline Payment.
	 *
	 * @param FormModel $formModel
	 * @param CreateCustomerOptions $options
	 * @return void
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	private function createCustomerWithoutPaymentMethod( $formModel, $options ) {
		$ctx = $this->createCustomerContext( $formModel );
		$stripeCustomer = $this->findStripeCustomerByEmail( $ctx->cardHolderEmail );
		$metadata = $options->addMetadata ? $ctx->metadata : null;
		if ( ! is_array( $metadata ) ) {
			$metadata = [];
		}

		if ( ! isset( $stripeCustomer ) ) {
			$this->logger->debug( __FUNCTION__, 'Creating Stripe Customer without PaymentMethod (free transaction)...' );
			$stripeCustomer = $this->stripe->createCustomerWithPaymentMethod(
				null,
				Utils::determineCustomerName( $ctx->cardHolderName, $ctx->businessName, $ctx->billingName ),
				$ctx->cardHolderEmail,
				$metadata,
				$ctx->taxIdType,
				$ctx->taxId,
				$ctx->billingAddress,
				$ctx->billingName,
				$ctx->shippingAddress,
				$ctx->shippingName
			);
		}

		$formModel->setStripeCustomer( $stripeCustomer );
	}

	/**
	 * Process a free inline Payment (e.g. 100%-off coupon) by creating a Stripe Customer.
	 * 
	 *
	 * @param PaymentFormModel $paymentFormModel
	 * @return PaymentIntentResult
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	private function processFreePayment( $paymentFormModel ) {
		$this->logger->debug( __FUNCTION__, 'CALLED' );

		$paymentIntentResult = new PaymentIntentResult();
		$paymentIntentResult->setNonce( $paymentFormModel->getNonce() );

		$createCustomerOptions = new CreateCustomerOptions();
		$createCustomerOptions->addMetadata = false;
		$this->createCustomerWithoutPaymentMethod( $paymentFormModel, $createCustomerOptions );

		$transactionData = TransactionDataService::createOneTimePaymentDataByModel( $paymentFormModel );
		$transactionData->setAmount( 0 );
		$transactionData->setChargedTotal( 0 );
		$transactionData->setProductAmountNet( 0 );
		$transactionData->setProductAmountTax( 0 );
		$transactionData->setProductAmountGross( 0 );

		$this->fireBeforeInlinePaymentAction( $paymentFormModel, $transactionData );

		$liveMode = ( $this->options->get( Options::OPTION_API_MODE ) === 'live' );
		$stubPaymentIntent = FreePayment::paymentIntent(
			FreePayment::ID_PREFIX . wp_generate_uuid4(),
			strtolower( $paymentFormModel->getForm()->currency ),
			$liveMode,
			Utils::prepareStripeChargeDescription( $this->staticContext, $paymentFormModel, $transactionData ),
			$paymentFormModel->getFormName()
		);
		$stubCharge = FreePayment::charge( $liveMode );

		$paymentFormModel->setStripePaymentMethodType(
			$paymentFormModel->getStripePaymentMethodType() ?: 'card'
		);
		$paymentFormModel->setStripePaymentIntent( $stubPaymentIntent );
		$paymentFormModel->setTransactionId( $stubPaymentIntent->id );
		$transactionData->setTransactionId( $stubPaymentIntent->id );

		$this->db->insertOrUpdatePayment( $paymentFormModel, $transactionData, $stubCharge );

		$this->fireAfterInlinePaymentAction( $paymentFormModel, $transactionData, $stubPaymentIntent );

		$paymentIntentResult->setRequiresAction( false );
		$paymentIntentResult->setSuccess( true );
		$paymentIntentResult->setMessageTitle(
			/* translators: Banner title of successful transaction */
			__( 'Success', 'wp-full-stripe-free' )
		);
		$paymentIntentResult->setMessage(
			/* translators: Banner message of successful payment */
			__( 'Payment Successful!', 'wp-full-stripe-free' )
		);

		$this->handleRedirect( $paymentFormModel, $transactionData, $paymentIntentResult );

		if ( Mailer::canSendPaymentPluginReceipt( $paymentFormModel->getForm() ) ) {
			$this->mailer->sendOneTimePaymentReceipt( $paymentFormModel->getForm(), $transactionData );
		}

		return $paymentIntentResult;
	}

	/**
	 * retrieves a client secret for the payment element to use
	 * is used for both setup intents and payment intents
	 * @return string
	 */
	function get_Setup_Intent_Client_Secret() {
		$result = null;
		$intent_type = null;
		try {
			$form_type = isset( $_POST['type' ] ) ? sanitize_text_field( $_POST['type'] ) : Main::FORM_TYPE_INLINE_PAYMENT;

			/*
				NOTE 1.1: All the forms call this endpoint to get the client secret, but only the one-time and inline-donation payment is processing at full.

				For all the form that enters, the `validateForm` method is working only with InlinePaymentFormModel and InlineDonationFormModel branch check. This makes that $paymentFormModel->getForm() to be null for all other forms (since it fails the DB lookup), thus using only the `createSetupIntent`.
			*/
			$paymentFormModel = $this->getFormModel( $form_type );
			// This step creates the Stripe object, so the captcha must be verified against
			// Google here; the nonce it returns is proof for the later save/charge steps only.
			$paymentFormModel->setRequireFreshCaptcha( true );
			$bindingResult = $paymentFormModel->bind();

			// Block donation forms on validation errors before creating a PaymentIntent.
			$blockBeforeIntent = Main::FORM_TYPE_INLINE_DONATION === $form_type
				? $bindingResult->hasErrors()
				: (
					// Other inline forms create Stripe objects here, so bad or
					// missing captcha must block them all.
					$bindingResult->hasFieldErrors( FormModel::PARAM_GOOGLE_RECAPTCHA_RESPONSE )
					// Only resolved inline payments can have meaningful global errors,
					// and their PaymentIntent is created with the custom amount.
					|| (
						Main::FORM_TYPE_INLINE_PAYMENT === Utils::getFormType( $paymentFormModel->getForm() )
						&& (
							$bindingResult->hasGlobalErrors()
							|| $bindingResult->hasFieldErrors( PaymentFormModel::PARAM_WPFS_CUSTOM_AMOUNT_UNIQUE )
						)
					)
				);

			if ( $blockBeforeIntent ) {
				$return = Utils::generateReturnValueFromBindings( $bindingResult );
				// Prefer the captcha error for the front-end handler.
				$captchaErrors = $bindingResult->getFieldErrors( FormModel::PARAM_GOOGLE_RECAPTCHA_RESPONSE );
				$amountErrors = $bindingResult->getFieldErrors( PaymentFormModel::PARAM_WPFS_CUSTOM_AMOUNT_UNIQUE );
				$fieldErrors = ! empty( $captchaErrors ) ? $captchaErrors : ( ! empty( $amountErrors ) ? $amountErrors : $bindingResult->getFieldErrors() );
				$globalErrors = $bindingResult->getGlobalErrors();
				$return['message'] = ! empty( $fieldErrors ) ? $fieldErrors[0]['message'] : reset( $globalErrors );

				header( "Content-Type: application/json" );
				status_header( 400 );
				echo json_encode( $return );
				exit;
			}

			$email = isset( $_POST['wpfs-card-holder-email'] ) ? sanitize_email( $_POST['wpfs-card-holder-email'] ) : null;
			$intent = $this->createClientSecretIntent( $form_type, $paymentFormModel, $this->findExistingStripeCustomerIdByEmail( $email ) );
			$result = $intent['clientSecret'];
			$intent_type = $intent['intentType'];
		} catch (\Exception $ex) {
			$result = [
				'success' => false,
				'messageTitle' =>
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				'message' => Localization::translateLabel( $ex->getMessage() ),
				'exceptionMessage' => $ex->getMessage()
			];
			header( "Content-Type: application/json" );
			status_header( 400 );
			echo json_encode( $result );
			exit;

		}

		header( "Content-Type: application/json" );
		echo json_encode( [ 'clientSecret' => $result, 'intentType' => $intent_type, 'nonce' => $paymentFormModel->getNonce() ] );
		exit;
	}

	/**
	 * The Customer to attach a SetupIntent to, so a reused email doesn't fail confirmation.
	 */
	private function findExistingStripeCustomerIdByEmail( ?string $email ): ?string {
		if ( empty( $email ) ) {
			return null;
		}
		$existingCustomer = $this->findStripeCustomerByEmail( $email );

		return $existingCustomer ? $existingCustomer->id : null;
	}

	/**
	 * Creates the intent the form's Payment Element confirms.
	 *
	 * @param InlineDonationFormModel|InlinePaymentFormModel $paymentFormModel
	 *
	 * @return array{clientSecret: string, intentType: 'payment'|'setup'}
	 */
	private function createClientSecretIntent( string $formType, $paymentFormModel, ?string $customerId ): array {
		$supportedPaymentMethods = $this->findCurrencySupportedPaymentMethods( $paymentFormModel->getForm() );

		// The inline payment front-end always confirms a PaymentIntent, even with no methods configured (NOTE 1.1).
		$supportRecurring = Main::FORM_TYPE_INLINE_PAYMENT !== Utils::getFormType( $paymentFormModel->getForm() );
		foreach ( $supportedPaymentMethods as $paymentMethod ) {
			// a non-card method that doesn't support recurring disables the setup-intent path
			if ( $paymentMethod !== Main::PAYMENT_METHOD_CARD ) {
				$supportRecurring = PaymentMethods::support_recurring( $paymentMethod );
			}
		}

		// Bind the intent to this form transaction, so the public pricing endpoints can verify
		// later that a client-supplied PaymentIntent id really belongs to this checkout.
		$bindingMetadata = PaymentIntentBinding::createBindingMetadata(
			$formType,
			$paymentFormModel->getForm()
		);

		if ( Main::FORM_TYPE_INLINE_DONATION === $formType && ! $paymentFormModel->isRecurringDonation() ) {
			// Donation forms have no payment method selector, so rely on automatic payment methods (Stripe Dashboard config) rather than a hard-coded ['card','link'] list.
			$intent = $this->stripe->createPaymentIntentForElement(
				$paymentFormModel->getForm()->currency,
				$paymentFormModel->getAmount(),
				"never",
				$bindingMetadata
			);
			$intentType = "payment";
		} elseif (
			Main::PAYMENT_TYPE_CARD_CAPTURE === ( $paymentFormModel->getForm()->customAmount ?? null )
			|| $supportRecurring
		) {
			// depending on the form capabilities we need to create payment intent or setup intent
			$intent = $this->stripe->createSetupIntent( $customerId );
			$intentType = "setup";
		} else {
			$intent = $this->stripe->createPaymentIntent(
				null, // payment method id
				null, // customer id
				$paymentFormModel->getForm()->currency,
				$paymentFormModel->getAmount(),
				null, // manual capture or not
				null, // description is updated later
				! empty( $bindingMetadata ) ? $bindingMetadata : null,
				null, // stripe email
				"always",
				! empty( $supportedPaymentMethods ) ? $supportedPaymentMethods : [ 'card', 'link' ]
			);
			$intentType = "payment";
		}

		return [
			'clientSecret' => $intent->client_secret,
			'intentType' => $intentType,
		];
	}

	/**
	 * The form's payment methods that support its currency. A currency-incompatible method (e.g. PayNow
	 * on a non-SGD form) makes Stripe reject the intent, so it is dropped instead of failing the whole
	 * request — mirroring the front-end filtering of the Payment Element.
	 *
	 * @param \stdClass|null $form
	 *
	 * @return list<string>
	 */
	private function findCurrencySupportedPaymentMethods( $form ): array {
		$paymentMethods = isset( $form->paymentMethods ) ? json_decode( $form->paymentMethods ) : null;
		if ( empty( $paymentMethods ) ) {
			return [];
		}

		$formCurrency = strtolower( (string) $form->currency );

		return array_values(
			array_filter(
				$paymentMethods,
				static function ( string $paymentMethod ) use ( $formCurrency ): bool {
					return PaymentMethods::is_supported_currency( $paymentMethod, $formCurrency );
				}
			)
		);
	}

	function fullstripe_inline_payment_charge() {

		try {

			$paymentFormModel = new InlinePaymentFormModel( $this->loggerService );
			$bindingResult = $this->bindChargedPayment( $paymentFormModel );

			// Stripe charged what the draft approved, and the form checked the draft's request. The form as edited since,
			// such as a raised minimum or terms of use turned on, doesn't refuse it, as on the page Stripe redirects back to.
			if ( $bindingResult->hasErrors() && ! $this->isChargedDraftPayment( $paymentFormModel ) ) {
				$return = Utils::generateReturnValueFromBindings( $bindingResult );
			} else {
				if ( Main::PAYMENT_TYPE_CARD_CAPTURE === $paymentFormModel->getForm()->customAmount ) {
					$result = $this->processSetupIntent( $paymentFormModel );
				} else {
					$result = $this->processPaymentIntentCharge( $paymentFormModel );
				}
				$return = $result->getAsArray();
			}
		} catch (UserFriendlyException $ex) {
			$this->logger->error( __FUNCTION__, 'User-friendly exception while handling payment charge', $ex );

			$messageTitle = is_null( $ex->getTitle() ) ?
				/* translators: Banner title of an error returned from an extension point by a developer */
				__( 'Internal Error', 'wp-full-stripe-free' ) :
				$ex->getTitle();
			$message = $ex->getMessage();
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\StripeWPFS\Stripe\Exception\CardException $ex) {
			$this->logger->error( __FUNCTION__, 'Stripe card exception while handling payment charge', $ex );

			$messageTitle =
				/* translators: Banner title of error returned by Stripe */
				__( 'Stripe Error', 'wp-full-stripe-free' );
			$message = $this->stripe->resolveErrorMessageByCode( $ex->getCode() );
			if ( is_null( $message ) ) {
				$message = Localization::translateLabel( $ex->getMessage() );
			}
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Generic exception while handling payment charge', $ex );

			$return = [
				'success' => false,
				'messageTitle' =>
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				'message' => Localization::translateLabel( $ex->getMessage() ),
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\Error $err) {
			$this->logger->error( __FUNCTION__, 'Generic error while handling payment charge', $err );

			$return = [
				'success' => false,
				'messageTitle' =>
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				'message' => Localization::translateLabel( $err->getMessage() ),
				'exceptionMessage' => $err->getMessage()
			];
		}

		header( "Content-Type: application/json" );
		echo json_encode( apply_filters( 'fullstripe_inline_payment_charge_return_message', $return ) );
		exit;

	}

	function fullstripe_inline_donation_charge() {

		try {

			$donationFormModel = new InlineDonationFormModel( $this->loggerService );
			$bindingResult = $donationFormModel->bind();

			// A donation Stripe already charged is checked against what it was charged for, which the form checked, not
			// the form as edited since.
			$isChargedDonation = $bindingResult->hasErrors()
				&& ( $this->bindToChargedDonation( $donationFormModel ) || $this->bindToChargedRecurringDonation( $donationFormModel ) );
			// Bound again, the request still refused only has its custom field values filtered here.
			if ( $isChargedDonation && $donationFormModel->bind()->hasErrors() ) {
				$donationFormModel->filterChargedCustomFieldValues();
			}

			if ( $bindingResult->hasErrors() && ! $isChargedDonation ) {
				$return = Utils::generateReturnValueFromBindings( $bindingResult );
			} else {
				$result = $this->processDonationPaymentIntentCharge( $donationFormModel );
				$return = $result->getAsArray();
			}
		} catch (UserFriendlyException $ex) {
			$this->logger->error( __FUNCTION__, 'User-friendly exception while handling donation charge', $ex );

			$messageTitle = is_null( $ex->getTitle() ) ?
				/* translators: Banner title of an error returned from an extension point by a developer */
				__( 'Internal Error', 'wp-full-stripe-free' ) :
				$ex->getTitle();
			$message = $ex->getMessage();
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\StripeWPFS\Stripe\Exception\CardException $ex) {
			$this->logger->error( __FUNCTION__, 'Stripe card exception while handling donation charge', $ex );

			$messageTitle =
				/* translators: Banner title of error returned by Stripe */
				__( 'Stripe Error', 'wp-full-stripe-free' );
			$message = $this->stripe->resolveErrorMessageByCode( $ex->getCode() );
			if ( is_null( $message ) ) {
				$message = Localization::translateLabel( $ex->getMessage() );
			}
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Generic exception while handling donation charge', $ex );

			$return = [
				'success' => false,
				'messageTitle' =>
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				'message' => Localization::translateLabel( $ex->getMessage() ),
				'exceptionMessage' => $ex->getMessage()
			];
		}

		header( "Content-Type: application/json" );
		echo json_encode( apply_filters( 'fullstripe_inline_donation_charge_return_message', $return ) );
		exit;

	}

	/**
	 * @param $saveCardFormModel InlinePaymentFormModel
	 * @param $transactionData PaymentTransactionData
	 */
	protected function fireBeforeInlineSaveCardAction( $saveCardFormModel, $transactionData ) {
		$params = [
			'email' => $saveCardFormModel->getCardHolderEmail(),
			'urlParameters' => $saveCardFormModel->getFormGetParametersAsArray(),
			'formName' => $saveCardFormModel->getFormName(),
			'stripeClient' => $this->stripe->getStripeClient(),
		];

		do_action( Main::ACTION_NAME_BEFORE_SAVE_CARD, $params );
	}

	/**
	 * @param PaymentFormModel $saveCardFormModel
	 * @param SaveCardTransactionData $transactionData
	 * @param \StripeWPFS\Stripe\Customer $stripeCustomer
	 */
	protected function fireAfterInlineSaveCardAction( $saveCardFormModel, $transactionData, $stripeCustomer ) {
		$replacer = new SaveCardMacroReplacer( $saveCardFormModel->getForm(), $transactionData, $this->loggerService );

		$params = [
			'email' => $saveCardFormModel->getCardHolderEmail(),
			'urlParameters' => $saveCardFormModel->getFormGetParametersAsArray(),
			'formName' => $saveCardFormModel->getFormName(),
			'stripeClient' => $this->stripe->getStripeClient(),
			'stripeCustomer' => $stripeCustomer,
			'rawPlaceholders' => $replacer->getRawKeyValuePairs(),
			'decoratedPlaceholders' => $replacer->getDecoratedKeyValuePairs(),
		];

		do_action( Main::ACTION_NAME_AFTER_SAVE_CARD, $params );
		do_action( Main::ACTION_NAME_FIRE_WEBHOOK, $saveCardFormModel->getForm(), $params );
	}

	/**
	 * @param PaymentFormModel $paymentFormModel
	 *
	 * @return ChargeResult
	 */
	private function processSetupIntent( $paymentFormModel ) {
		$this->logger->debug( __FUNCTION__, 'CALLED' );

		$setupIntentResult = new SetupIntentResult();
		$transactionData = null;

		// Find or create the customer once; both branches below save the card on it.
		$createCustomerOptions = new CreateCustomerOptions();
		$createCustomerOptions->addMetadata = true;
		$this->createOrRetrieveCustomerByFormModel( $paymentFormModel, $createCustomerOptions );

		if ( empty( $paymentFormModel->getStripeSetupIntentId() ) ) {
			// The frontend already confirmed a SetupIntent via stripe.confirmSetup()
			// We cannot create another SetupIntent with the same PaymentMethod
			// Instead, go directly to the success flow and save the card on the customer found above.
			$this->logger->debug( __FUNCTION__, 'Processing PaymentMethod directly (frontend already confirmed SetupIntent)...' );

			$this->fireBeforeInlineSaveCardAction( $paymentFormModel, $transactionData );

			$paymentFormModel->setTransactionId( $paymentFormModel->getStripeCustomer()->id );
			$transactionData = TransactionDataService::createSaveCardDataByModel( $paymentFormModel );
			$transactionData->setTransactionId( $paymentFormModel->getTransactionId() );
			$stripeCardSavedDescription = Utils::prepareStripeCardSavedDescription( $this->staticContext, $paymentFormModel, $transactionData );

			$stripeCustomer = $paymentFormModel->getStripeCustomer();
			$stripeCustomer->description = $stripeCardSavedDescription;
			// Only the description: the before-save hook may have updated the customer since it was retrieved.
			$this->stripe->updateCustomerDescription( $stripeCustomer, $stripeCardSavedDescription );

			$this->db->insertSavedCard( $paymentFormModel, $transactionData );

			$this->fireAfterInlineSaveCardAction( $paymentFormModel, $transactionData, $stripeCustomer );

			$setupIntentResult->setRequiresAction( false );
			$setupIntentResult->setSuccess( true );
			$setupIntentResult->setMessageTitle(
				/* translators: Banner title of successful transaction */
				__( 'Success', 'wp-full-stripe-free' )
			);
			$setupIntentResult->setMessage(
				/* translators: Banner message of saving card successfully */
				__( 'Saving Card Successful!', 'wp-full-stripe-free' )
			);

		} else {
			$this->logger->debug( __FUNCTION__, 'Retrieving SetupIntent...' );

			$setupIntent = $this->stripe->retrieveSetupIntent( $paymentFormModel->getStripeSetupIntentId() );

			if ( isset( $setupIntent ) ) {
				if (
					\StripeWPFS\Stripe\SetupIntent::STATUS_REQUIRES_ACTION === $setupIntent->status
					&& 'use_stripe_sdk' === $setupIntent->next_action->type
				) {
					$this->logger->debug( __FUNCTION__, 'SetupIntent requires action...' );

					$setupIntentResult->setSuccess( false );
					$setupIntentResult->setRequiresAction( true );
					$setupIntentResult->setSetupIntentClientSecret( $setupIntent->client_secret );
					$setupIntentResult->setMessageTitle(
						/* translators: Banner title of pending transaction requiring a second factor authentication (SCA/PSD2) */
						__( 'Action required', 'wp-full-stripe-free' )
					);
					$setupIntentResult->setMessage(
						/* translators: Banner message of a pending card saving transaction requiring a second factor authentication (SCA/PSD2) */
						__( 'Saving this card requires additional action before completion!', 'wp-full-stripe-free' )
					);
				} elseif ( \StripeWPFS\Stripe\SetupIntent::STATUS_SUCCEEDED === $setupIntent->status ) {
					$this->logger->debug( __FUNCTION__, 'SetupIntent succeeded.' );

					$this->fireBeforeInlineSaveCardAction( $paymentFormModel, $transactionData );

					$paymentFormModel->setTransactionId( $paymentFormModel->getStripeCustomer()->id );
					$transactionData = TransactionDataService::createSaveCardDataByModel( $paymentFormModel );
					$transactionData->setTransactionId( $paymentFormModel->getTransactionId() );
					$stripeCardSavedDescription = Utils::prepareStripeCardSavedDescription( $this->staticContext, $paymentFormModel, $transactionData );

					$stripeCustomer = $paymentFormModel->getStripeCustomer();
					$stripeCustomer->description = $stripeCardSavedDescription;
					// Only the description: the before-save hook may have updated the customer since it was retrieved.
					$this->stripe->updateCustomerDescription( $stripeCustomer, $stripeCardSavedDescription );

					$this->db->insertSavedCard( $paymentFormModel, $transactionData );

					$this->fireAfterInlineSaveCardAction( $paymentFormModel, $transactionData, $stripeCustomer );

					$setupIntentResult->setRequiresAction( false );
					$setupIntentResult->setSuccess( true );
					$setupIntentResult->setMessageTitle(
						/* translators: Banner title of successful transaction */
						__( 'Success', 'wp-full-stripe-free' )
					);
					$setupIntentResult->setMessage(
						/* translators: Banner message of saving card successfully */
						__( 'Saving Card Successful!', 'wp-full-stripe-free' )
					);
				} else {
					$setupIntentResult->setSuccess( false );
					$setupIntentResult->setMessageTitle(
						/* translators: Banner title of failed transaction */
						__( 'Failed', 'wp-full-stripe-free' )
					);
					$setupIntentResult->setMessage(
						sprintf(
							/* translators: %s: status reported by Stripe */
							__( "Invalid SetupIntent status '%s'.", 'wp-full-stripe-free' ),
							$setupIntent->status
						)
					);
				}
			}
		}

		$this->handleRedirect( $paymentFormModel, $transactionData, $setupIntentResult );

		if ( $setupIntentResult->isSuccess() ) {
			if ( Mailer::canSendSaveCardPluginReceipt( $paymentFormModel->getForm() ) ) {
				$this->mailer->sendSaveCardNotification( $paymentFormModel->getForm(), $transactionData );
			}
		}

		return $setupIntentResult;
	}

	protected function isTaxIdAddedToCustomer( $stripeCustomerId, $taxIdType, $taxId ) {
		$result = false;

		$taxIdItems = $this->stripe->getTaxIdsForCustomer( $stripeCustomerId );
		foreach ( $taxIdItems->data as $taxIdItem ) {
			if ( $taxIdItem->type === $taxIdType && $taxIdItem->value === $taxId ) {
				$result = true;
				break;
			}
		}

		return $result;
	}

	private function createOrRetrieveCustomerByFormModel( $formModel, $options ) {
		// try and find the customer by email
		$ctx = $this->createCustomerContext( $formModel );
		$stripeCustomer = $this->findStripeCustomerByEmail( $ctx->cardHolderEmail );
		$newCustomerPaymentMethodId = null;
		$metadata = $options->addMetadata && is_array( $ctx->metadata ) ? $ctx->metadata : [];

		// handle case where we save the payment method
		if ( PaymentMethods::support_recurring( $formModel->getStripePaymentMethodType() ) ) {
			// get the payment method
			$paymentMethod = $this->stripe->validatePaymentMethodCVCCheck( $ctx->paymentMethodId );
			// if we didn't find the customer before but have one via the payment method, use that, unless it has
			// another email in Stripe
			if ( ! isset( $stripeCustomer ) && isset( $paymentMethod->customer ) ) {
				$paymentMethodCustomer = $this->stripe->retrieveCustomer( $paymentMethod->customer );
				if ( $this->isStripeCustomerEmail( $paymentMethodCustomer, $ctx->cardHolderEmail ) ) {
					$stripeCustomer = $paymentMethodCustomer;
				}
			}
			$newCustomerPaymentMethodId = $options->attachPaymentMethod ? $paymentMethod->id : null;
			if ( $options->attachPaymentMethod && isset( $stripeCustomer ) ) {
				$this->logger->debug( __FUNCTION__, "Attaching PaymentMethod to existing Stripe Customer..." );
				$paymentMethod = $this->stripe->attachPaymentMethodToCustomerIfMissing(
					$stripeCustomer,
					$paymentMethod,
					/* set to default */
					true
				);
			}
			$formModel->setStripePaymentMethod( $paymentMethod );
		}

		if ( isset( $stripeCustomer ) ) {
			$this->updateExistingStripeCustomerByContext( $stripeCustomer, $ctx, $options );
		} else {
			$stripeCustomer = $this->createStripeCustomerByContext( $ctx, $metadata, $newCustomerPaymentMethodId );
		}

		// save the customer for later
		$formModel->setStripeCustomer( $stripeCustomer );
	}

	/**
	 * @param CreateCustomerContext $ctx
	 * @param array<string, string> $metadata
	 * @param string|null $paymentMethodId Saved on the new Customer as its default when given.
	 *
	 * @return \StripeWPFS\Stripe\Customer
	 */
	private function createStripeCustomerByContext( CreateCustomerContext $ctx, array $metadata, ?string $paymentMethodId ) {
		$this->logger->debug( __FUNCTION__, is_null( $paymentMethodId ) ? "Creating Stripe Customer without PaymentMethod..." : "Creating Stripe Customer with PaymentMethod..." );

		$stripeCustomer = $this->stripe->createCustomerWithPaymentMethod(
			$paymentMethodId,
			Utils::determineCustomerName( $ctx->cardHolderName, $ctx->businessName, $ctx->billingName ),
			$ctx->cardHolderEmail,
			$metadata,
			$ctx->taxIdType,
			$ctx->taxId,
			$ctx->billingAddress,
			$ctx->billingName,
			$ctx->shippingAddress,
			$ctx->shippingName
		);

		// Without address fields, Stripe Tax taxes the customer's invoices at the tax location, as for an existing customer.
		if ( is_null( $ctx->billingAddress ) && ! empty( $ctx->taxCountry ) ) {
			$this->updateCustomerTaxAddress( $stripeCustomer, $ctx );
			$this->stripe->updateCustomer( $stripeCustomer );
		}

		return $stripeCustomer;
	}

	/**
	 * @param \StripeWPFS\Stripe\Customer $stripeCustomer
	 */
	private function updateExistingStripeCustomerByContext( $stripeCustomer, CreateCustomerContext $ctx, CreateCustomerOptions $options ): void {
		if ( $options->addMetadata ) {
			$stripeCustomer->metadata = $ctx->metadata;
		}
		if ( ! empty( $ctx->taxIdType ) && ! empty( $ctx->taxId ) ) {
			if ( ! $this->isTaxIdAddedtoCustomer( $stripeCustomer->id, $ctx->taxIdType, $ctx->taxId ) ) {
				$this->stripe->createTaxIdForCustomer( $stripeCustomer->id, $ctx->taxIdType, $ctx->taxId );
			}
		}
		if ( ! is_null( $ctx->billingAddress ) ) {
			$this->updateCustomerBillingAddress( $stripeCustomer, $ctx );
		} else if ( ! empty( $ctx->taxCountry ) ) {
			$this->updateCustomerTaxAddress( $stripeCustomer, $ctx );
		}
		if ( ! is_null( $ctx->shippingAddress ) ) {
			$this->updateCustomerShippingAddress( $stripeCustomer, $ctx->shippingName, $ctx->cardHolderPhone, $ctx->shippingAddress );
		}
		$stripeCustomer->name = Utils::determineCustomerName( $ctx->cardHolderName, $ctx->businessName, $ctx->billingName );
		$this->stripe->updateCustomer( $stripeCustomer );
	}

	/**
	 * Updates the \StripeWPFS\Stripe\Customer object's address property with an appropriate address array.
	 *
	 * @param $stripeCustomer \StripeWPFS\Stripe\Customer
	 * @param $ctx CreateCustomerContext
	 */
	public function updateCustomerBillingAddress( &$stripeCustomer, $ctx ) {
		$stripeArrayHash = Utils::prepareStripeBillingAddressHashFromArray( $ctx->billingAddress );
		if ( isset( $stripeArrayHash ) ) {
			$stripeCustomer->address = $stripeArrayHash;
		}
		if ( ! empty( $ctx->billingName ) ) {
			$stripeCustomer->name = $ctx->billingName;
		}
	}

	/**
	 * @param CreateCustomerContext $ctx
	 * @return array
	 */
	protected function prepareTaxAddress( $ctx ) {
		$result = [];

		if ( ! empty( $ctx->taxState ) ) {
			$result['state'] = $ctx->taxState;
		}
		if ( ! empty( $ctx->taxCountry ) ) {
			$result['country'] = $ctx->taxCountry;
		}
		if ( ! empty( $ctx->taxPostalCode ) ) {
			$result['postal_code'] = $ctx->taxPostalCode;
		}

		return $result;
	}

	protected function updateCustomerTaxAddress( &$stripeCustomer, $ctx ) {
		$stripeCustomer->address = $this->prepareTaxAddress( $ctx );
	}

	/**
	 * Updates the \StripeWPFS\Stripe\Customer object's shipping property with an appropriate address array.
	 *
	 * @param $stripeCustomer \StripeWPFS\Stripe\Customer
	 * @param $shippingName
	 * @param $shippingPhone
	 * @param $shippingAddress array
	 */
	public function updateCustomerShippingAddress( &$stripeCustomer, $shippingName, $shippingPhone, $shippingAddress ) {
		$stripeShippingHash = Utils::prepareStripeShippingHashFromArray( $shippingName, $shippingPhone, $shippingAddress );
		$stripeCustomer->shipping = $stripeShippingHash;
	}

	/**
	 * @param DonationPaymentIntentResult $paymentIntentResult
	 * @param \StripeWPFS\Stripe\PaymentIntent $paymentIntent
	 * @param string $title
	 * @param string $message
	 *
	 * @return DonationPaymentIntentResult
	 */
	protected function createPaymentIntentResultActionRequired( &$paymentIntentResult, $paymentIntent, $title, $message ) {
		$paymentIntentResult->setSuccess( false );
		$paymentIntentResult->setRequiresAction( true );
		$paymentIntentResult->setPaymentIntentClientSecret( $paymentIntent->client_secret );
		$paymentIntentResult->setIsManualConfirmation( $paymentIntent->confirmation_method === 'manual' );
		$paymentIntentResult->setMessageTitle( $title );
		$paymentIntentResult->setMessage( $message );

		return $paymentIntentResult;
	}

	/**
	 * @param $paymentIntentResult DonationPaymentIntentResult
	 * @param $paymentIntent \StripeWPFS\Stripe\PaymentIntent
	 * @param $title string
	 * @param $message string
	 *
	 * @return DonationPaymentIntentResult
	 */
	protected function createPaymentIntentResultSuccess( &$paymentIntentResult, $title, $message ) {
		$paymentIntentResult->setRequiresAction( false );
		$paymentIntentResult->setSuccess( true );
		$paymentIntentResult->setMessageTitle( $title );
		$paymentIntentResult->setMessage( $message );

		return $paymentIntentResult;
	}

	/**
	 * @param $paymentIntentResult DonationPaymentIntentResult
	 * @param $paymentIntent \StripeWPFS\Stripe\PaymentIntent
	 * @param $title string
	 * @param $message string
	 *
	 * @return DonationPaymentIntentResult
	 */
	protected function createPaymentIntentResultFailed( &$paymentIntentResult, $title, $message ) {
		$paymentIntentResult->setSuccess( false );
		$paymentIntentResult->setMessageTitle( $title );
		$paymentIntentResult->setMessage( $message );

		return $paymentIntentResult;
	}

	/**
	 * @param $paymentIntent \StripeWPFS\Stripe\PaymentIntent
	 * @param $formName
	 */
	protected function addFormNameToPaymentIntent( &$paymentIntent, $formName ) {
		$paymentIntent->wpfs_form = $formName;
	}

	/**
	 * @param DonationFormModel $donationFormModel
	 * @param DonationTransactionData $transactionData
	 *
	 * @return \StripeWPFS\Stripe\PaymentIntent
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	protected function createPaymentIntentForDonation( $donationFormModel, $transactionData ) {
		$donationDescription = Utils::prepareStripeDonationDescription( $this->staticContext, $donationFormModel, $transactionData );
		if ( $donationFormModel->getForm()->generateInvoice == 1 ) {
			$createInvoiceOptions = new CreateOneTimeInvoiceOptions();
			$createInvoiceOptions->autoAdvance = true;
			$stripeInvoice = $this->createInvoiceForOneTimePaymentByFormModel( $donationFormModel, $createInvoiceOptions );

			$finalizedInvoice = $this->stripe->finalizeInvoice( $stripeInvoice->id );

			$payments = $finalizedInvoice->payments->data ?? [];
			$stripePaymentIntent = null;

			foreach ( $payments as $payment ) {
				if (
					isset( $payment->payment->type ) &&
					$payment->payment->type === 'payment_intent' &&
					isset( $payment->payment->payment_intent )
				) {
					$stripePaymentIntent = $payment->payment->payment_intent;
					break;
				}
			}

			$this->stripe->updatePaymentIntentByInvoice(
				$finalizedInvoice,
				$donationFormModel->getStripePaymentMethodId(),
				$donationDescription,
				$donationFormModel->getMetadata(),
				Mailer::canSendDonationStripeReceipt( $donationFormModel->getForm() ) ? $donationFormModel->getCardHolderEmail() : null
			);

			$paymentIntent = $this->stripe->retrievePaymentIntent( $stripePaymentIntent );
		} else {
			$metadata = $donationFormModel->getMetadata();

			$amount = $this->calculateDonationChargeAmount( $donationFormModel );
			$currency = $donationFormModel->getForm()->currency;

			$paymentIntent = $this->stripe->createPaymentIntent(
				$donationFormModel->getStripePaymentMethodId(),
				$donationFormModel->getStripeCustomer()->id,
				$currency,
				$amount,
				true,
				$donationDescription,
				$metadata,
				Mailer::canSendDonationStripeReceipt( $donationFormModel->getForm() ) ? $donationFormModel->getCardHolderEmail() : null
			);
		}

		return $paymentIntent;
	}

	/**
	 * @param \StripeWPFS\Stripe\PaymentIntent $paymentIntent
	 *
	 * @return boolean
	 */
	protected function paymentIntentRequiresAction( $paymentIntent ) {
		return \StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_ACTION === $paymentIntent->status
			&& 'use_stripe_sdk' === $paymentIntent->next_action->type;
	}

	/**
	 * @param \StripeWPFS\Stripe\PaymentIntent $paymentIntent
	 *
	 * @return boolean
	 */
	protected function paymentIntentSucceeded( $paymentIntent ) {
		return \StripeWPFS\Stripe\PaymentIntent::STATUS_SUCCEEDED === $paymentIntent->status
			|| \StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_CAPTURE === $paymentIntent->status;
	}

	/**
	 * @param DonationFormModel $donationFormModel
	 */
	protected function fireBeforeInlineDonationAction( $donationFormModel, $transactionData ) {
		$params = [
			'email' => $donationFormModel->getCardHolderEmail(),
			'urlParameters' => $donationFormModel->getFormGetParametersAsArray(),
			'formName' => $donationFormModel->getFormName(),
			'currency' => $transactionData->getCurrency(),
			'frequency' => $donationFormModel->getDonationFrequency(),
			'amount' => $donationFormModel->getAmount(),
			'stripeClient' => $this->stripe->getStripeClient()
		];

		do_action( Main::ACTION_NAME_BEFORE_DONATION_CHARGE, $params );
	}

	/**
	 * @param DonationFormModel $donationFormModel
	 * @param DonationTransactionData $transactionData
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent
	 * @return void
	 */
	protected function fireAfterInlineDonationAction( $donationFormModel, $transactionData, $paymentIntent ) {
		// Exactly once per PaymentIntent, whichever request or webhook recorded the row. The request that reports the
		// donation also sends its receipt, so a donation posted again isn't emailed twice.
		if ( ! $this->db->claimDonationAfterChargeAction( $paymentIntent->id ) ) {
			return;
		}

		$replacer = new DonationMacroReplacer( $donationFormModel->getForm(), $transactionData, $this->loggerService );

		$params = [
			'email' => $donationFormModel->getCardHolderEmail(),
			'urlParameters' => $donationFormModel->getFormGetParametersAsArray(),
			'formName' => $donationFormModel->getFormName(),
			'currency' => $transactionData->getCurrency(),
			'frequency' => $donationFormModel->getDonationFrequency(),
			'amount' => $donationFormModel->getAmount(),
			'recoveryFee' => $transactionData->getRecoveryFee(),
			'chargedTotal' => $transactionData->getChargedTotal(),
			'stripeClient' => $this->stripe->getStripeClient(),
			'stripePaymentIntent' => $paymentIntent,
			'stripeSubscription' => $donationFormModel->getStripeSubscription(),
			'rawPlaceholders' => $replacer->getRawKeyValuePairs(),
			'decoratedPlaceholders' => $replacer->getDecoratedKeyValuePairs(),
		];

		try {
			do_action( Main::ACTION_NAME_AFTER_DONATION_CHARGE, $params );
			do_action( Main::ACTION_NAME_FIRE_WEBHOOK, $donationFormModel->getForm(), $params );
		} finally {
			// A failing listener keeps the claim, so a retry doesn't run the listeners again; the receipt goes out now.
			if ( Mailer::canSendDonationPluginReceipt( $donationFormModel->getForm() ) ) {
				$this->mailer->sendDonationEmailReceipt( $donationFormModel->getForm(), $transactionData );
			}
		}
	}

	/**
	 * Records the recovery fee and the total a donation's PaymentIntent charged. By now the PaymentIntent was checked
	 * to charge the donation the model holds and its fee: the amounts its save request signed, or the donation and the
	 * fee items of the subscription whose first invoice it pays. So the fee is what it charged beyond the donation,
	 * whatever the form's fee settings or the donor's fee choice say now.
	 *
	 * @param DonationTransactionData $transactionData
	 * @param DonationFormModel $donationFormModel
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent
	 */
	private function setChargedDonationFee( $transactionData, $donationFormModel, $paymentIntent ): void {
		$chargedTotal = (int) $paymentIntent->amount;

		$transactionData->setChargedTotal( $chargedTotal );
		$transactionData->setRecoveryFee( max( 0, $chargedTotal - (int) $donationFormModel->getAmount() ) );
	}

	/**
	 * @param $paymentIntent
	 * @param $transactionData OneTimePaymentTransactionData|DonationTransactionData
	 */
	private function setInvoiceDataFromPaymentIntent( $paymentIntent, $transactionData ) {

		$charge = $this->stripe->getLatestCharge( $paymentIntent );

		if ( ! empty( $charge->invoice ) ) {
			$invoice = $this->stripe->retrieveInvoice( $charge->invoice );

			$transactionData->setStripeInvoiceId( $invoice->id );
			$transactionData->setInvoiceUrl( $invoice->invoice_pdf );
			$transactionData->setInvoiceNumber( $invoice->number );
		}
	}

	/**
	 * @param $donationFormModel DonationFormModel
	 *
	 * @return ChargeResult
	 * @throws \Exception
	 */
	private function processDonationPaymentIntentCharge( $donationFormModel ) {
		// A one-time donation is charged once; that handler records it, redirects and sends the receipt itself.
		if ( ! $this->isRecurringDonation( $donationFormModel ) ) {
			return $this->processOnetimeDonationCharge( $donationFormModel );
		}

		$paymentIntentResult = new DonationPaymentIntentResult();
		$paymentIntentResult->setNonce( $donationFormModel->getNonce() );

		// Refuse a PaymentIntent of another donation before the customer is touched or integrations are told.
		// Sent after 3D Secure, it finishes the subscription the first request created (#738).
		$submittedSubscription = null;
		if ( $donationFormModel->getStripePaymentIntentId() ) {
			$submittedSubscription = $this->findSubmittedRecurringDonationSubscription( $donationFormModel );
			if ( is_null( $submittedSubscription ) ) {
				return $this->createPaymentIntentResultFailed(
					$paymentIntentResult,
					/* translators: Banner title of failed transaction */
					__( 'Failed', 'wp-full-stripe-free' ),
					__( 'Invalid request', 'wp-full-stripe-free' )
				);
			}
			$this->restoreSavedDonationCustomFields(
				$donationFormModel,
				$donationFormModel->getStripePaymentIntent(),
				$this->getPaymentIntentMetadataArray( $donationFormModel->getStripePaymentIntent() )
			);
		}

		$createCustomerOptions = new CreateCustomerOptions();
		$createCustomerOptions->addMetadata = false;
		$createCustomerOptions->attachPaymentMethod = true;
		$this->createOrRetrieveCustomerByFormModel( $donationFormModel, $createCustomerOptions );

		$transactionData = TransactionDataService::createDonationDataByFormModel( $donationFormModel );

		$this->fireBeforeInlineDonationAction( $donationFormModel, $transactionData );

		$paymentIntent = null;
		$subscription = null;
		$latestCharge = null;

		// Recurring donation: create subscription ONLY (no separate PaymentIntent charge), unless the
		// request finishes the one an earlier request created.
		$subscription = is_null( $submittedSubscription ) ?
			$this->createSubscriptionForDonation( $donationFormModel ) :
			$submittedSubscription;
		$donationFormModel->setStripeSubscription( $subscription );
		if ( is_null( $submittedSubscription ) ) {
			// Its row comes only once it's paid, which a failed call or a donor closing the tab at 3D Secure prevents.
			FeeSync::keepUntracked( $subscription );
		}

		// NOTE: Donation do not have trial time. They are charged immediately.
		// Get the PaymentIntent and charge from the subscription's latest invoice (expanded)
		if ( ! is_null( $submittedSubscription ) ) {
			$paymentIntent = $donationFormModel->getStripePaymentIntent();
			$donationFormModel->setTransactionId( $paymentIntent->id );
			$transactionData->setTransactionId( $paymentIntent->id );
		} elseif ( isset( $subscription->latest_invoice ) ) {
			$invoice = $subscription->latest_invoice;
			
			// Get payment_intent for transaction ID only
			$payments = $invoice->payments->data ?? [];
			foreach ( $payments as $payment ) {
				if ( isset( $payment->payment->payment_intent ) ) {
					$paymentIntent = $this->stripe->retrievePaymentIntent( 
						$payment->payment->payment_intent 
					);
					if ( $paymentIntent !== null ) {
						$donationFormModel->setTransactionId( $paymentIntent->id );
						$transactionData->setTransactionId( $paymentIntent->id );
					}
					break;
				}
			}
		}

		$latestCharge = $this->stripe->getLatestCharge( $paymentIntent );

		// Handle different subscription/payment states
		if ( $paymentIntent !== null && $this->paymentIntentRequiresAction( $paymentIntent ) ) {
			if ( is_null( $submittedSubscription ) ) {
				$this->bindRecurringDonationPaymentIntent( $paymentIntent, $subscription, $donationFormModel );
			}

			$this->createPaymentIntentResultActionRequired(
				$paymentIntentResult,
				$paymentIntent,
				/* translators: Banner title of pending transaction requiring a second factor authentication (SCA/PSD2) */
				__( 'Action required', 'wp-full-stripe-free' ),
				/* translators: Banner message of a one-time payment requiring a second factor authentication (SCA/PSD2) */
				__( 'The donation needs additional action before completion!', 'wp-full-stripe-free' )
			);
		} elseif ( $paymentIntent !== null && $this->paymentIntentSucceeded( $paymentIntent ) ) {

			$this->setInvoiceDataFromPaymentIntent( $paymentIntent, $transactionData );

			if ( null === $this->db->getDonationByPaymentIntentId( $paymentIntent->id ) ) {
				$this->insertInlineDonationFromLatestCharge( $donationFormModel, $paymentIntent, $subscription, $latestCharge );
			}

			$this->setChargedDonationFee( $transactionData, $donationFormModel, $paymentIntent );
			$this->fireAfterInlineDonationAction( $donationFormModel, $transactionData, $paymentIntent );

			$this->createPaymentIntentResultSuccess(
				$paymentIntentResult,
				/* translators: Banner title of successful transaction */
				__( 'Success', 'wp-full-stripe-free' ),
				/* translators: Banner message of successful payment */
				__( 'Donation Successful!', 'wp-full-stripe-free' )
			);
		} else {
			$this->createPaymentIntentResultFailed(
				$paymentIntentResult,
				/* translators: Banner title of failed transaction */
				__( 'Failed', 'wp-full-stripe-free' ),
				$paymentIntent !== null
					? sprintf(
						/* translators: %s: status reported by Stripe */
						__( "Invalid PaymentIntent status '%s'.", 'wp-full-stripe-free' ),
						$paymentIntent->status
					)
					: __( 'Could not process recurring donation.', 'wp-full-stripe-free' )
			);
		}

		$this->handleRedirect( $donationFormModel, $transactionData, $paymentIntentResult );

		return $paymentIntentResult;
	}

	/**
	 * @param PaymentFormModel $paymentFormModel
	 * @param OneTimePaymentTransactionData $transactionData
	 */
	private function fireBeforeInlinePaymentAction( $paymentFormModel, $transactionData ) {
		$params = [
			'email' => $paymentFormModel->getCardHolderEmail(),
			'urlParameters' => $paymentFormModel->getFormGetParametersAsArray(),
			'formName' => $paymentFormModel->getFormName(),
			'priceId' => $paymentFormModel->getPriceId(),
			'productName' => $paymentFormModel->getProductName(),
			'currency' => $transactionData->getCurrency(),
			'amount' => $paymentFormModel->getAmount(),
			'stripeClient' => $this->stripe->getStripeClient(),
		];

		do_action( Main::ACTION_NAME_BEFORE_PAYMENT_CHARGE, $params );
	}

	/**
	 * @param $paymentFormModel PaymentFormModel
	 * @param $transactionData OneTimePaymentTransactionData
	 * @param $paymentIntent \StripeWPFS\Stripe\PaymentIntent
	 */
	private function fireAfterInlinePaymentAction( $paymentFormModel, $transactionData, $paymentIntent ) {
		$replacer = new OneTimePaymentMacroReplacer( $paymentFormModel->getForm(), $transactionData, $this->loggerService );

		$params = [
			'email' => $paymentFormModel->getCardHolderEmail(),
			'urlParameters' => $paymentFormModel->getFormGetParametersAsArray(),
			'formName' => $paymentFormModel->getFormName(),
			'priceId' => $paymentFormModel->getPriceId(),
			'productName' => $paymentFormModel->getProductName(),
			'currency' => $transactionData->getCurrency(),
			'amount' => $transactionData->getAmount(),
			'recoveryFee' => $transactionData->getRecoveryFee(),
			'chargedTotal' => $transactionData->getChargedTotal(),
			'stripeClient' => $this->stripe->getStripeClient(),
			'stripePaymentIntent' => $paymentIntent,
			'rawPlaceholders' => $replacer->getRawKeyValuePairs(),
			'decoratedPlaceholders' => $replacer->getDecoratedKeyValuePairs(),
		];

		do_action( Main::ACTION_NAME_AFTER_PAYMENT_CHARGE, $params );
		do_action( Main::ACTION_NAME_FIRE_WEBHOOK, $paymentFormModel->getForm(), $params );
	}

	/**
	 * @param PaymentFormModel $formModel
	 * @return mixed
	 */
	protected function getTaxCountry( $formModel ) {
		$result = null;

		$result = $formModel->getBillingAddressCountry();
		if ( empty( $result ) ) {
			$result = $formModel->getTaxCountry();
		}

		return $result;
	}

	/**
	 * @param PaymentFormModel $formModel
	 * @return null
	 */
	protected function getTaxState( $formModel ) {
		$result = null;

		$result = $formModel->getBillingAddressState();
		if ( empty( $result ) ) {
			$result = $formModel->getTaxState();
		}

		return $result;
	}

	/**
	 * @param PaymentFormModel|SubscriptionFormModel $formModel
	 */
	protected function getApplicableTaxRates( $formModel ) {
		$result = [];

		if ( $formModel->getForm()->vatRateType === Main::FIELD_VALUE_TAX_RATE_FIXED ) {
			$result = json_decode( $formModel->getForm()->vatRates );
		} else if ( $formModel->getForm()->vatRateType === Main::FIELD_VALUE_TAX_RATE_DYNAMIC ) {
			$result = PriceCalculator::filterApplicableTaxRatesStatic(
				$this->getTaxCountry( $formModel ),
				$this->getTaxState( $formModel ),
				json_decode( $formModel->getForm()->vatRates ),
				$formModel->getTaxId()
			);
		}

		return $result;
	}

	/**
	 * @param $transactionData PaymentTransactionData
	 * @param $invoice \StripeWPFS\Stripe\Invoice
	 */
	private function updatePaymentTransactionDataPricing( $transactionData, $invoice ) {
		$pricingDetails = Pricing::extractSimplifiedPricingFromInvoiceLineItems( $invoice->lines->data );

		$transactionData->setProductAmountDiscount( $pricingDetails->discountAmount );
		$transactionData->setProductAmountNet( $pricingDetails->totalAmount - $pricingDetails->discountAmount - $pricingDetails->taxAmountInclusive );
		$transactionData->setProductAmountTax( $pricingDetails->taxAmountExclusive + $pricingDetails->taxAmountInclusive );
		$transactionData->setProductAmountGross( $transactionData->getProductAmountNet() + $transactionData->getProductAmountTax() );
		$transactionData->setAmount( $transactionData->getProductAmountGross() );
	}

	/**
	 * Compute the tax-inclusive total for an inline payment form via a Stripe preview invoice
	 * and populate the tax breakdown (net/tax/gross) on the given transaction data.
	 *
	 * For inline payment forms the frontend creates the PaymentIntent up front with the
	 * pre-tax base amount. Tax-address changes update the on-screen preview but do not
	 * reliably push the new amount back onto the PaymentIntent. As a result the confirmed
	 * PaymentIntent could still carry the pre-tax amount, so tax was shown but never charged
	 * (#413). Recomputing the total server-side, just before the charge is confirmed, closes
	 * the whole class of triggers (country/state/postal code/tax id changes, coupons, custom
	 * amounts) instead of relying on the frontend to keep the amount in sync.
	 *
	 * @param PaymentFormModel $paymentFormModel The payment form model.
	 * @param OneTimePaymentTransactionData $transactionData Transaction data updated with the tax breakdown.
	 *
	 * @return int|null The discounted, tax-inclusive gross amount in the smallest currency unit
	 *                  (which may be 0, e.g. a 100% coupon), or null only when the form has
	 *                  neither tax nor a coupon configured (in which case the caller should keep
	 *                  the base amount).
	 * @throws \Exception If the preview invoice cannot be created.
	 */
	private function calculateTaxInclusivePaymentAmount( $paymentFormModel, $transactionData ) {
		// Recompute via a preview invoice whenever tax OR a coupon applies — both change the
		// amount the customer should actually be charged (a coupon discounts it; tax adds to
		// it). Previously this only ran when tax was configured, so on a no-tax form a coupon
		// discount was never applied to the charge and the customer was overcharged the full
		// price (the preview invoice is the only place the coupon is applied). Without tax AND
		// without a coupon the up-front amount already matches, so we skip the extra API call.
		$taxableRateTypes = [
			Main::FIELD_VALUE_TAX_RATE_STRIPE_TAX,
			Main::FIELD_VALUE_TAX_RATE_FIXED,
			Main::FIELD_VALUE_TAX_RATE_DYNAMIC,
		];
		$hasTax    = in_array( $paymentFormModel->getForm()->vatRateType, $taxableRateTypes, true );
		$hasCoupon = ! is_null( $paymentFormModel->getStripeDiscountId() );
		if ( ! $hasTax && ! $hasCoupon ) {
			return null;
		}

		$taxRateIds = Pricing::extractTaxRateIdsStatic( $this->getApplicableTaxRates( $paymentFormModel ) );

		$createInvoiceOptions = new CreateOneTimeInvoiceOptions();
		$createInvoiceOptions->autoAdvance = true;
		$createInvoiceOptions->taxRateIds = $taxRateIds;

		// For custom-amount forms the preview invoice is built from the form model's amount.
		// In redirect/legacy flows that amount may already be the tax-inclusive PaymentIntent
		// amount, which would tax an already-taxed total. Compute from the pre-tax base instead
		// (stamped on the PaymentIntent when we first adjusted it) and restore afterwards (#413).
		$originalAmount = $paymentFormModel->getAmount();
		$paymentFormModel->setAmount( $this->resolvePretaxBaseAmount( $paymentFormModel ) );

		try {
			$previewInvoice = $this->createPreviewInvoiceForOneTimePaymentByFormModel( $paymentFormModel, $createInvoiceOptions );

			// Populate the tax breakdown so the recorded transaction reflects the tax-inclusive total too.
			$this->updatePaymentTransactionDataPricing( $transactionData, $previewInvoice );
		} finally {
			$paymentFormModel->setAmount( $originalAmount );
		}

		// Return the computed gross even when it is 0 (e.g. a 100% coupon); only the "no tax
		// configured" case above returns null. Conflating a 0 total with "no tax" would make
		// the caller fall back to the pre-tax base amount and overcharge.
		return (int) round( $transactionData->getProductAmountGross() );
	}

	/**
	 * Compute the full amount that should be charged for an inline payment form: the
	 * tax-inclusive product total (when tax is configured) plus the recovery fee (when
	 * accepted and the total isn't 0), all derived from the pre-tax base amount. The recovery fee
	 * is the one signed on the PaymentIntent by its draft when there is one, so a fee changed since
	 * is not applied. Populates the tax breakdown on $transactionData as a side effect. Returns
	 * [chargeAmount, baseAmount, recoveryFee] in the smallest currency unit, or null when neither
	 * tax nor a recovery fee applies (the up-front amount already matches, so the caller should
	 * leave the PaymentIntent untouched).
	 *
	 * @param PaymentFormModel $paymentFormModel
	 * @param OneTimePaymentTransactionData $transactionData
	 * @return array{0: int, 1: int, 2: int}|null [chargeAmount, baseAmount, recoveryFee], or null when no adjustment is needed.
	 */
	private function computeInlinePaymentChargeAmount( $paymentFormModel, $transactionData ) {
		$baseAmount = $this->resolvePretaxBaseAmount( $paymentFormModel );
		$taxInclusiveAmount = $this->calculateTaxInclusivePaymentAmount( $paymentFormModel, $transactionData );

		$recoveryFee = $paymentFormModel->getFeeRecoveryAccepted();
		$recoveryFeeData = Utils::getFeeRecoveryData( $paymentFormModel->getForm() );
		$stampedRecoveryFee = $this->resolveStampedRecoveryFee( $paymentFormModel );
		$hasRecoveryFee = is_null( $stampedRecoveryFee ) ? $recoveryFee && ! empty( $recoveryFeeData ) : $stampedRecoveryFee > 0;
		$recoveryFeeAmount = 0;

		// Nothing to adjust for plain forms; the up-front amount already matches the charge.
		if ( is_null( $taxInclusiveAmount ) && ! $hasRecoveryFee ) {
			return null;
		}

		$chargeAmount = is_null( $taxInclusiveAmount ) ? $baseAmount : $taxInclusiveAmount;

		if ( ! is_null( $stampedRecoveryFee ) ) {
			$recoveryFeeAmount = $stampedRecoveryFee;
		} elseif ( $hasRecoveryFee && $chargeAmount > 0 ) {
			// A fully discounted payment is free, so there is no processing fee to recover.
			// Compute the recovery fee on the discounted PRE-TAX amount when a preview ran
			// (so a coupon reduces the fee too); otherwise on the plain base. Using the
			// undiscounted base here would inflate the fee on coupon orders.
			$feeBaseAmount = is_null( $taxInclusiveAmount )
				? $baseAmount
				: (int) round( $transactionData->getProductAmountNet() );
			$recoveryFeeAmount = (int) Utils::calculateRecoveryFee(
				$feeBaseAmount,
				$recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_PERCENTAGE ],
				$recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT ],
				$paymentFormModel->getForm()->currency
			);
		}
		$chargeAmount += $recoveryFeeAmount;

		return [ (int) round( $chargeAmount ), (int) round( $baseAmount ), $recoveryFeeAmount ];
	}

	/**
	 * The recovery fee stamped on the payment's PaymentIntent when its draft was saved.
	 *
	 * @param PaymentFormModel $paymentFormModel
	 * @return int|null Null for a PaymentIntent drafted before the fee was stamped.
	 */
	private function resolveStampedRecoveryFee( $paymentFormModel ) {
		$paymentIntent = $paymentFormModel->getStripePaymentIntent();
		if ( empty( $paymentIntent ) ) {
			return null;
		}

		$metadata = $this->getPaymentIntentMetadataArray( $paymentIntent );
		if ( ! $this->isSignedChargeBasis( $paymentIntent, $metadata, self::PAYMENT_CHARGE_BASIS_KEYS ) ) {
			return null;
		}

		return (int) $metadata[ self::METADATA_KEY_RECOVERY_FEE ];
	}

	/**
	 * Takes the pre-tax amount the payment's draft signed on the PaymentIntent, so a different amount
	 * posted since, or a product price changed since, is not recorded.
	 *
	 * @param PaymentFormModel $paymentFormModel
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent
	 * @return void
	 */
	private function applySignedPaymentBase( $paymentFormModel, $paymentIntent ) {
		$metadata = $this->getPaymentIntentMetadataArray( $paymentIntent );
		if ( $this->isSignedChargeBasis( $paymentIntent, $metadata, self::PAYMENT_CHARGE_BASIS_KEYS ) ) {
			$paymentFormModel->setAmount( (int) $metadata[ self::METADATA_KEY_PRETAX_BASE_AMOUNT ] );
		}
	}

	/**
	 * Signs the given metadata values for the PaymentIntent.
	 *
	 * @param string $paymentIntentId
	 * @param array<string,mixed> $metadata
	 * @param string[] $keys
	 * @return string
	 */
	private function signChargeBasis( $paymentIntentId, array $metadata, array $keys ) {
		$values = [ (string) $paymentIntentId ];
		foreach ( $keys as $key ) {
			$values[] = $key . '=' . ( isset( $metadata[ $key ] ) ? (string) $metadata[ $key ] : '' );
		}

		return wp_hash( implode( '|', $values ), 'auth' );
	}

	/**
	 * Whether the PaymentIntent carries the given values as this site saved them: present, a fee that
	 * isn't negative, and a matching signature.
	 *
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent
	 * @param array<string,mixed> $metadata
	 * @param string[] $keys
	 * @return bool
	 */
	private function isSignedChargeBasis( $paymentIntent, array $metadata, array $keys ) {
		foreach ( $keys as $key ) {
			if ( ! isset( $metadata[ $key ] ) || ! is_numeric( $metadata[ $key ] ) ) {
				return false;
			}
		}
		if ( (int) $metadata[ self::METADATA_KEY_RECOVERY_FEE ] < 0 || ! isset( $metadata[ self::METADATA_KEY_CHARGE_SIGNATURE ] ) ) {
			return false;
		}

		return hash_equals( $this->signChargeBasis( $paymentIntent->id, $metadata, $keys ), (string) $metadata[ self::METADATA_KEY_CHARGE_SIGNATURE ] );
	}

	/**
	 * Get the expected charge amount.
	 *
	 * @param PaymentFormModel $paymentFormModel
	 * @param OneTimePaymentTransactionData $transactionData
	 * @return int
	 */
	private function resolveExpectedChargeAmount( $paymentFormModel, $transactionData ) {
		$grossAmount = (int) round( $transactionData->getProductAmountGross() );

		if ( $grossAmount > 0 ) {
			return $grossAmount;
		}

		return (int) round( $paymentFormModel->getAmount() );
	}

	/**
	 * Re-sync an existing PaymentIntent's amount with the tax/fee-inclusive total before it is
	 * (re-)confirmed, for the legacy/existing-PI charge path.
	 *
	 * @param PaymentFormModel $paymentFormModel
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent
	 * @param OneTimePaymentTransactionData $transactionData
	 * @return void
	 */
	private function resyncPaymentIntentAmountWithTax( $paymentFormModel, $paymentIntent, $transactionData ) {
		// A PaymentIntent's amount can only be changed while it is still awaiting confirmation.
		// Once it reaches requires_action (SCA/3DS), processing or succeeded it has already been
		// confirmed and Stripe rejects amount changes, so we must not touch it then.
		$mutableStatuses = [
			\StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD,
			\StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_CONFIRMATION,
		];
		if ( ! in_array( $paymentIntent->status, $mutableStatuses, true ) ) {
			return;
		}

		$resolved = $this->computeInlinePaymentChargeAmount( $paymentFormModel, $transactionData );
		if ( is_null( $resolved ) ) {
			return;
		}
		list( $chargeAmount, $baseAmount, $recoveryFeeAmount ) = $resolved;

		if ( $chargeAmount > 0 && $chargeAmount !== (int) $paymentIntent->amount ) {
			$this->logger->debug(
				__FUNCTION__,
				sprintf( 'Re-syncing PaymentIntent %s amount from %d to %d (tax/fee-inclusive).', $paymentIntent->id, (int) $paymentIntent->amount, $chargeAmount )
			);
			$this->stampChargeBasis( $paymentIntent, $baseAmount, $paymentFormModel, $recoveryFeeAmount );
			$paymentIntent->amount = $chargeAmount;
			$this->stripe->updatePaymentIntent( $paymentIntent, true );
		}
	}

	/**
	 * Normalise a PaymentIntent's metadata to a plain array, regardless of whether it is a
	 * Stripe object (direct API) or a stdClass (WP test/live platform proxy).
	 *
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent
	 * @return array<string, mixed>
	 */
	private function getPaymentIntentMetadataArray( $paymentIntent ) {
		if ( ! isset( $paymentIntent->metadata ) || empty( $paymentIntent->metadata ) ) {
			return [];
		}
		if ( is_array( $paymentIntent->metadata ) ) {
			return $paymentIntent->metadata;
		}

		// Guard against json_decode returning null on unexpected metadata shapes/encoding issues,
		// which would otherwise make a later $metadata[...] write fatal.
		$decoded = json_decode( json_encode( $paymentIntent->metadata ), true );

		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Resolve the pre-tax base amount for a payment form, preferring a value previously stamped
	 * on the PaymentIntent metadata. This keeps tax computation idempotent for custom-amount
	 * forms in redirect/legacy flows where the form model amount may already be tax-inclusive.
	 *
	 * @param PaymentFormModel $paymentFormModel
	 * @return int
	 */
	private function resolvePretaxBaseAmount( $paymentFormModel ) {
		$paymentIntent = $paymentFormModel->getStripePaymentIntent();
		if ( ! empty( $paymentIntent ) ) {
			$metadata = $this->getPaymentIntentMetadataArray( $paymentIntent );
			if ( isset( $metadata[ self::METADATA_KEY_PRETAX_BASE_AMOUNT ] ) && is_numeric( $metadata[ self::METADATA_KEY_PRETAX_BASE_AMOUNT ] ) ) {
				return (int) $metadata[ self::METADATA_KEY_PRETAX_BASE_AMOUNT ];
			}
		}

		return (int) round( $paymentFormModel->getAmount() );
	}

	/**
	 * Stamp the pre-tax base amount and the fee recovery choice on the PaymentIntent metadata so
	 * later recomputations (e.g. on redirect confirmation) work from the original base rather than
	 * a tax- and fee-inclusive amount.
	 *
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent
	 * @param int $baseAmount
	 * @param PaymentFormModel $paymentFormModel
	 * @param int $recoveryFee The recovery fee in the charged amount.
	 * @return void
	 */
	private function stampChargeBasis( $paymentIntent, int $baseAmount, PaymentFormModel $paymentFormModel, int $recoveryFee ): void {
		$metadata = $this->getPaymentIntentMetadataArray( $paymentIntent );
		// Stripe metadata values must be strings.
		$metadata[ self::METADATA_KEY_PRETAX_BASE_AMOUNT ] = (string) $baseAmount;
		$metadata[ self::METADATA_KEY_FEE_RECOVERY_ACCEPTED ] = empty( $paymentFormModel->getFeeRecoveryAccepted() ) ? '0' : '1';
		$metadata[ self::METADATA_KEY_RECOVERY_FEE ] = (string) $recoveryFee;
		$metadata[ self::METADATA_KEY_CHARGE_SIGNATURE ] = $this->signChargeBasis(
			$paymentIntent->id,
			$metadata,
			self::PAYMENT_CHARGE_BASIS_KEYS
		);
		$paymentIntent->metadata = $metadata;
	}

	/**
	 * Drop the charge basis an earlier draft stamped on the PaymentIntent, so a new draft prices the
	 * submitted amount and fee recovery choice, which a retry after a declined card may have changed.
	 *
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent
	 * @return bool Whether a charge basis was stamped.
	 */
	private function discardChargeBasis( $paymentIntent ): bool {
		$chargeBasisKeys = array_flip( [ self::METADATA_KEY_PRETAX_BASE_AMOUNT, self::METADATA_KEY_FEE_RECOVERY_ACCEPTED, self::METADATA_KEY_RECOVERY_FEE, self::METADATA_KEY_CHARGE_SIGNATURE ] );
		$metadata = $this->getPaymentIntentMetadataArray( $paymentIntent );
		$paymentIntent->metadata = array_diff_key( $metadata, $chargeBasisKeys );

		return ! empty( array_intersect_key( $metadata, $chargeBasisKeys ) );
	}

	/**
	 * Stamp when the customer accepted the terms of use on the draft PaymentIntent, because the page
	 * a redirect reloads posts the checkbox unticked. Keeps the time an earlier draft stamped, so a
	 * retry after a declined card does not move the acceptance.
	 *
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent
	 * @param PaymentFormModel $paymentFormModel
	 * @return bool Whether the acceptance was stamped.
	 */
	private function stampTermsOfUseAcceptance( $paymentIntent, PaymentFormModel $paymentFormModel ): bool {
		$acceptedAt = $paymentFormModel->getTermsOfUseAcceptedAtMetadataValue();
		$metadata = $this->getPaymentIntentMetadataArray( $paymentIntent );
		if ( is_null( $acceptedAt ) || ! empty( $metadata[ FormModel::METADATA_KEY_TERMS_OF_USE_ACCEPTED_AT ] ) ) {
			return false;
		}

		$metadata[ FormModel::METADATA_KEY_TERMS_OF_USE_ACCEPTED_AT ] = $acceptedAt;
		$paymentIntent->metadata = $metadata;

		return true;
	}

	/**
	 * Stamp the tax location the draft was priced with on its PaymentIntent, because the page Stripe redirects back
	 * to posts the defaults of the tax fields, which a form without address fields shows. The draft of a taxed form
	 * always updates its PaymentIntent.
	 *
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent
	 * @param PaymentFormModel $paymentFormModel
	 * @return void
	 */
	private function stampTaxLocation( $paymentIntent, PaymentFormModel $paymentFormModel ): void {
		if ( Main::FIELD_VALUE_TAX_RATE_NO_TAX === $paymentFormModel->getForm()->vatRateType ) {
			return;
		}

		$metadata = $this->getPaymentIntentMetadataArray( $paymentIntent );
		// Without a country nothing is stamped, and Stripe deletes a key set to an empty value, so the location of an
		// earlier draft doesn't stay.
		$metadata[ FormModel::METADATA_KEY_TAX_LOCATION ] = empty( $paymentFormModel->getTaxCountry() ) ? '' : implode(
			'|',
			[ $paymentFormModel->getTaxCountry(), $paymentFormModel->getTaxState(), $paymentFormModel->getTaxZip() ]
		);
		$paymentIntent->metadata = $metadata;
	}

	/**
	 * Restore the tax location stamped on the draft, so the charge is priced, and the customer taxed, where it was
	 * drafted. A draft saved before the location was stamped keeps the submitted one.
	 *
	 * @param PaymentFormModel $paymentFormModel
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent
	 * @return void
	 */
	private function restoreTaxLocation( PaymentFormModel $paymentFormModel, $paymentIntent ): void {
		$metadata = $this->getPaymentIntentMetadataArray( $paymentIntent );
		if ( empty( $metadata[ FormModel::METADATA_KEY_TAX_LOCATION ] ) ) {
			return;
		}

		list( $country, $state, $zip ) = array_pad( explode( '|', (string) $metadata[ FormModel::METADATA_KEY_TAX_LOCATION ] ), 3, '' );
		$paymentFormModel->setTaxLocation( $country, '' === $state ? null : $state, '' === $zip ? null : $zip );
	}

	/**
	 * Restore the fee recovery choice stamped on the draft, for forms where the customer may decline
	 * the fee. Forms that always apply it keep the submitted value.
	 *
	 * @param PaymentFormModel $paymentFormModel
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent
	 * @return void
	 */
	private function restoreFeeRecoveryAccepted( PaymentFormModel $paymentFormModel, $paymentIntent ): void {
		$recoveryFeeData = Utils::getFeeRecoveryData( $paymentFormModel->getForm() );
		if ( empty( $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_OPT_IN ] ) ) {
			return;
		}

		$metadata = $this->getPaymentIntentMetadataArray( $paymentIntent );
		if ( isset( $metadata[ self::METADATA_KEY_FEE_RECOVERY_ACCEPTED ] ) ) {
			$paymentFormModel->setFeeRecoveryAccepted( '1' === $metadata[ self::METADATA_KEY_FEE_RECOVERY_ACCEPTED ] ? 1 : 0 );
		}
	}

	/**
	 * Drafts saved before the fee recovery choice was stamped on the PaymentIntent lose an accepted
	 * opt-in fee on redirect, because the reloaded form posts the checkbox unticked. For those, accept
	 * the fee when the charged amount is exactly the amount with the fee. The amount was set server-side
	 * by the draft, so it cannot be raised by the client.
	 *
	 * @param PaymentFormModel $paymentFormModel
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent
	 * @param OneTimePaymentTransactionData $transactionData
	 * @return array{0: int, 1: int, 2: int}|null The charge with the fee, as computeInlinePaymentChargeAmount() returns it,
	 *                                            when the fee was accepted on the model.
	 */
	private function chargedFeeOfUnstampedDraft( PaymentFormModel $paymentFormModel, $paymentIntent, $transactionData ): ?array {
		$recoveryFeeData = Utils::getFeeRecoveryData( $paymentFormModel->getForm() );
		$metadata = $this->getPaymentIntentMetadataArray( $paymentIntent );
		if (
			empty( $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_OPT_IN ] )
			|| isset( $metadata[ self::METADATA_KEY_FEE_RECOVERY_ACCEPTED ] )
			|| ! empty( $paymentFormModel->getFeeRecoveryAccepted() )
		) {
			return null;
		}

		$paymentFormModel->setFeeRecoveryAccepted( 1 );
		$withFee = $this->computeInlinePaymentChargeAmount( $paymentFormModel, $transactionData );
		if ( ! is_null( $withFee ) && (int) $paymentIntent->amount === $withFee[0] ) {
			return $withFee;
		}

		$paymentFormModel->setFeeRecoveryAccepted( 0 );

		return null;
	}

	/**
	 * Refund a charge at an amount the payment's draft never approved, such as a PaymentIntent re-priced after the
	 * draft, and refuse to record it.
	 *
	 * @param PaymentIntentResult $paymentIntentResult
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent A succeeded or authorized PaymentIntent.
	 * @param PaymentFormModel $paymentFormModel
	 * @param int $approvedAmount
	 * @return void
	 */
	private function refundUnapprovedCharge( PaymentIntentResult $paymentIntentResult, $paymentIntent, PaymentFormModel $paymentFormModel, int $approvedAmount ): void {
		$this->logger->error(
			__FUNCTION__,
			sprintf(
				'PaymentIntent %1$s charged %2$d but its draft on form %3$s approved %4$d, refusing to record the transaction.',
				$paymentIntent->id,
				(int) $paymentIntent->amount,
				$paymentFormModel->getFormName(),
				$approvedAmount
			)
		);

		$this->refundPaymentIntentOnce( $paymentIntent, __FUNCTION__, sprintf( 'the draft transaction approved %d.', $approvedAmount ) );

		$this->createRefundedPaymentResult( $paymentIntentResult );
	}

	/**
	 * Tell the customer the payment was refunded, so nothing was recorded.
	 *
	 * @param PaymentIntentResult $paymentIntentResult
	 * @return PaymentIntentResult
	 */
	private function createRefundedPaymentResult( PaymentIntentResult $paymentIntentResult ): PaymentIntentResult {
		$this->createPaymentIntentResultFailed(
			$paymentIntentResult,
			/* translators: Banner title of failed transaction */
			__( 'Failed', 'wp-full-stripe-free' ),
			/* translators: Banner message of a payment refunded, such as for a product removed from the form while the customer paid */
			__( 'Payment refunded.', 'wp-full-stripe-free' )
		);

		return $paymentIntentResult;
	}

	/**
	 * Tell the site admin about a charge its draft approved that the form now prices otherwise, such as after its tax
	 * rates changed. The draft priced it when the customer paid, so it is recorded at the charged amount (#779). It is
	 * reported once, also when a retry finishes a completion that failed.
	 *
	 * @param PaymentFormModel $paymentFormModel
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent A succeeded or authorized PaymentIntent.
	 * @param int|null $expectedAmount What the form prices the payment at now, or null when it can't price it.
	 * @param bool $isRecoveryFeeUnknown Whether the charge's recovery fee could not be told apart from its amount.
	 * @return void
	 */
	private function noticeRepricedCharge( PaymentFormModel $paymentFormModel, $paymentIntent, ?int $expectedAmount, bool $isRecoveryFeeUnknown ): void {
		$this->logger->error(
			__FUNCTION__,
			sprintf(
				'PaymentIntent %1$s charged %2$d, which its draft approved, but form %3$s now prices it at %4$s. Recording the charged amount.',
				$paymentIntent->id,
				(int) $paymentIntent->amount,
				$paymentFormModel->getFormName(),
				is_null( $expectedAmount ) ? 'nothing' : (string) $expectedAmount
			)
		);

		$sent = $this->sendAdminNoticeOnce(
			$paymentIntent->id,
			function () use ( $paymentFormModel, $paymentIntent, $expectedAmount, $isRecoveryFeeUnknown ): bool {
				return $this->mailer->sendRepricedChargeAdminNotice( $paymentFormModel->getForm(), $paymentIntent->id, (int) $paymentIntent->amount, $expectedAmount, $isRecoveryFeeUnknown );
			}
		);
		if ( ! $sent ) {
			$this->logger->error( __FUNCTION__, sprintf( 'The site admin could not be told about PaymentIntent %s.', $paymentIntent->id ) );
		}
	}

	/**
	 * Sends an admin notice about a PaymentIntent once across requests, claiming it first and releasing the claim when
	 * the send fails, so a later request retries it.
	 *
	 * @param string $paymentIntentId
	 * @param callable(): bool $send Sends the notice, and answers whether it was sent.
	 * @return bool False when the send failed.
	 */
	private function sendAdminNoticeOnce( string $paymentIntentId, callable $send ): bool {
		// A constant value makes a racing duplicate insert affect no rows, so add_option() fails for it.
		$noticeKey = 'wpfs_refused_charge_' . $paymentIntentId;
		if ( ! add_option( $noticeKey, '1', '', false ) ) {
			return true;
		}
		if ( $send() ) {
			return true;
		}

		delete_option( $noticeKey );

		return false;
	}

	/**
	 * Report the charged amount, less the recovery fee, as the product's taxed total, keeping the pre-tax amount the
	 * form prices now where the charge covers it, so the amounts reported add up to what Stripe charged.
	 *
	 * @param OneTimePaymentTransactionData $transactionData
	 * @param int $chargedAmount
	 * @param int $recoveryFee
	 * @return void
	 */
	private function reconcileChargedPricing( $transactionData, int $chargedAmount, int $recoveryFee ): void {
		$grossAmount = max( 0, $chargedAmount - $recoveryFee );
		$netAmount = min( (int) round( $transactionData->getProductAmountNet() ), $grossAmount );

		$transactionData->setProductAmountNet( $netAmount );
		$transactionData->setProductAmountTax( $grossAmount - $netAmount );
		$transactionData->setProductAmountGross( $grossAmount );
		$transactionData->setAmount( $grossAmount );
	}

	/**
	 * Refuse to finish a PaymentIntent charge that doesn't match what was priced.
	 *
	 * @param PaymentIntentResult $paymentIntentResult
	 * @param string $reason What the log records about the refused request.
	 * @return PaymentIntentResult
	 */
	private function refusePaymentIntentCharge( $paymentIntentResult, string $reason ) {
		$this->logger->error( __FUNCTION__, $reason );

		$this->createPaymentIntentResultFailed(
			$paymentIntentResult,
			/* translators: Banner title of failed transaction */
			__( 'Failed', 'wp-full-stripe-free' ),
			__( 'Invalid request', 'wp-full-stripe-free' )
		);

		return $paymentIntentResult;
	}

	/**
	 * Refuse a request completing an inline payment, such as the confirmation the page Stripe redirects back to sends,
	 * when it doesn't match the draft.
	 *
	 * @param string $reason What the log records about the refused request.
	 * @param string|null $message What the customer is told, "Invalid request" when null.
	 * @return never
	 * @throws UserFriendlyException
	 */
	private function refuseRedirectConfirmation( string $reason, ?string $message = null ) {
		$this->logger->error( __FUNCTION__, $reason );

		$ex = new UserFriendlyException( is_null( $message ) ? __( 'Invalid request', 'wp-full-stripe-free' ) : $message );
		/* translators: Banner title of failed transaction */
		$ex->setTitle( __( 'Failed', 'wp-full-stripe-free' ) );
		throw $ex;
	}

	/**
	 * Binds the request that completes an inline payment. A product the form can't bind any more, such as one removed
	 * since the customer paid for it, refunds the charge rather than leaving it unrecorded.
	 *
	 * @param PaymentFormModel $paymentFormModel
	 * @return BindingResult
	 * @throws UserFriendlyException
	 */
	private function bindChargedPayment( PaymentFormModel $paymentFormModel ) {
		try {
			return $paymentFormModel->bind();
		} catch ( UserFriendlyException $ex ) {
			$this->refundChargeOfRemovedDraftProduct( $paymentFormModel );
			throw $ex;
		}
	}

	/**
	 * Whether the request completes a payment its draft approved and Stripe charged: the PaymentIntent has a draft, and
	 * the request proves it is the payer's with its client secret.
	 *
	 * @param PaymentFormModel $paymentFormModel
	 * @return bool
	 */
	private function isChargedDraftPayment( PaymentFormModel $paymentFormModel ): bool {
		$form = $paymentFormModel->getForm();
		$paymentIntentId = $paymentFormModel->getStripePaymentIntentId();
		if ( empty( $paymentIntentId ) || is_null( $form ) || is_null( $this->db->getPaymentByEventId( $paymentIntentId ) ) ) {
			return false;
		}

		$clientSecret = $this->readSanitizedPostValue( $_POST, 'payment_intent_client_secret' );
		$paymentIntent = $this->getClientSecretVerifiedPaymentIntent( $paymentIntentId, $clientSecret ? $clientSecret : $paymentFormModel->getStripePaymentIntentClientSecret() );

		return ! is_null( $paymentIntent )
			&& $this->isPaymentIntentBoundToForm( $paymentIntent, Utils::getFormType( $form ), $form )
			&& in_array( $paymentIntent->status, self::CHARGED_PAYMENT_INTENT_STATUSES, true );
	}

	/**
	 * Refunds the charge of a drafted product the form no longer offers, when the request proves the PaymentIntent is
	 * the payer's with its client secret, and refuses the request telling the customer so.
	 *
	 * @param PaymentFormModel $paymentFormModel Bound as far as its form and PaymentIntent.
	 * @return void
	 * @throws UserFriendlyException When the charge was refunded.
	 */
	private function refundChargeOfRemovedDraftProduct( PaymentFormModel $paymentFormModel ): void {
		$form = $paymentFormModel->getForm();
		$paymentIntentId = $paymentFormModel->getStripePaymentIntentId();
		$draftPayment = empty( $paymentIntentId ) || is_null( $form ) ? null : $this->db->getPaymentByEventId( $paymentIntentId );
		if ( is_null( $draftPayment ) || $paymentFormModel->selectProductByPriceId( $draftPayment->priceId ) ) {
			return;
		}

		$clientSecret = $this->readSanitizedPostValue( $_POST, 'payment_intent_client_secret' );
		$paymentIntent = $this->getClientSecretVerifiedPaymentIntent( $paymentIntentId, $clientSecret ? $clientSecret : $paymentFormModel->getStripePaymentIntentClientSecret() );
		if (
			! is_null( $paymentIntent )
			&& $this->isPaymentIntentBoundToForm( $paymentIntent, Utils::getFormType( $form ), $form )
			&& $this->refundChargeOfRemovedProduct( $form, $paymentIntent )
		) {
			$this->refuseRedirectConfirmation(
				sprintf( 'PaymentIntent %s was charged for a product no longer on the form, refunding it.', $paymentIntent->id ),
				__( 'Payment refunded.', 'wp-full-stripe-free' )
			);
		}
	}

	/**
	 * Refund money taken for a drafted product that was removed from the form while the customer was paying, since the
	 * form no longer sells it, and tell the site admin once.
	 *
	 * @param object $form
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent A PaymentIntent the request proved is the payer's.
	 * @return bool Whether Stripe had charged or authorized the PaymentIntent.
	 */
	private function refundChargeOfRemovedProduct( $form, $paymentIntent ): bool {
		// A recorded payment isn't refunded, also when the request recording it failed afterwards.
		if (
			! $this->paymentIntentSucceeded( $paymentIntent )
			|| $this->isRecordedPayment( $this->db->getPaymentByEventId( $paymentIntent->id ) )
		) {
			return false;
		}

		$this->refundPaymentIntentOnce( $paymentIntent, __FUNCTION__, 'its product is no longer on the form.' );

		// Reported once across reloads.
		$this->sendAdminNoticeOnce(
			$paymentIntent->id,
			function () use ( $form, $paymentIntent ): bool {
				return $this->mailer->sendRemovedProductChargeAdminNotice( $form, $paymentIntent->id, (int) $paymentIntent->amount );
			}
		);

		return true;
	}

	/**
	 * Creates the paid invoice of a captured inline payment, on a form that generates invoices: the product or amount
	 * its draft priced, with its coupon and tax, and the recovery fee the charge added (#759). A payment only
	 * authorized gets none, as before: it can still be cancelled, and capturing it in the admin doesn't create one.
	 * Stripe charged the payment, so a failure is logged and leaves it recorded.
	 *
	 * @param PaymentFormModel $paymentFormModel
	 * @param OneTimePaymentTransactionData $transactionData Takes the invoice's id, number and PDF.
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent
	 * @param int $recoveryFee The fee the charge added.
	 * @return void
	 */
	private function createInvoiceOfInlinePayment( PaymentFormModel $paymentFormModel, $transactionData, $paymentIntent, int $recoveryFee ): void {
		if ( 1 !== (int) $paymentFormModel->getForm()->generateInvoice || \StripeWPFS\Stripe\PaymentIntent::STATUS_SUCCEEDED !== $paymentIntent->status ) {
			return;
		}

		$invoiceContext = $this->createOneTimeInvoiceContext( $paymentFormModel );
		$invoiceContext->currency = $paymentIntent->currency;
		$invoiceContext->recoveryFee = $recoveryFee;
		$invoiceContext->recoveryFeeAfterDiscountAndTax = true;

		$createInvoiceOptions = new CreateOneTimeInvoiceOptions();
		// Marked paid below. Advanced automatically, Stripe would finalize it later and try to collect it if that failed.
		$createInvoiceOptions->autoAdvance = false;
		$createInvoiceOptions->taxRateIds = Pricing::extractTaxRateIdsStatic( $this->getApplicableTaxRates( $paymentFormModel ) );

		// A retry finishing a completion that failed later reuses the invoice: Stripe keeps idempotency keys for a day
		// only.
		$metadata = $this->getPaymentIntentMetadataArray( $paymentIntent );
		$stampedInvoiceId = empty( $metadata[ self::METADATA_KEY_INVOICE_ID ] ) ? null : (string) $metadata[ self::METADATA_KEY_INVOICE_ID ];
		try {
			$paidStripeInvoice = is_null( $stampedInvoiceId ) ?
				$this->createPaidInvoiceForPaymentIntent( $invoiceContext, $createInvoiceOptions, $paymentIntent->id ) :
				$this->stripe->retrieveInvoice( $stampedInvoiceId );
		} catch ( \Exception $ex ) {
			$this->logger->error( __FUNCTION__, sprintf( 'Cannot create the invoice of PaymentIntent %s.', $paymentIntent->id ), $ex );

			return;
		}

		if ( isset( $paidStripeInvoice->total ) && (int) $paidStripeInvoice->total !== (int) $paymentIntent->amount ) {
			$this->logger->error(
				__FUNCTION__,
				sprintf( 'Invoice %1$s of PaymentIntent %2$s totals %3$d, but Stripe charged %4$d.', $paidStripeInvoice->id, $paymentIntent->id, (int) $paidStripeInvoice->total, (int) $paymentIntent->amount )
			);
		}

		$transactionData->setStripeInvoiceId( $paidStripeInvoice->id );
		$transactionData->setInvoiceUrl( $paidStripeInvoice->invoice_pdf );
		$transactionData->setInvoiceNumber( $paidStripeInvoice->number );

		if ( is_null( $stampedInvoiceId ) ) {
			try {
				$this->stampInvoiceOnPaymentIntent( $paymentIntent, $paidStripeInvoice->id );
			} catch ( \Exception $ex ) {
				$this->logger->error( __FUNCTION__, sprintf( 'Cannot name invoice %1$s on PaymentIntent %2$s.', $paidStripeInvoice->id, $paymentIntent->id ), $ex );
			}
		}
	}

	/**
	 * Names the invoice of an inline payment on its PaymentIntent, writing only that key: Stripe merges metadata.
	 *
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent
	 * @param string $invoiceId
	 * @return void
	 */
	private function stampInvoiceOnPaymentIntent( $paymentIntent, string $invoiceId ): void {
		$paymentIntentUpdate = new \stdClass();
		$paymentIntentUpdate->id = $paymentIntent->id;
		$paymentIntentUpdate->description = isset( $paymentIntent->description ) ? $paymentIntent->description : null;
		$paymentIntentUpdate->metadata = [ self::METADATA_KEY_INVOICE_ID => $invoiceId ];

		$this->stripe->updatePaymentIntent( $paymentIntentUpdate );
	}

	/**
	 * Whether the payment was completed: recorded and reported. Completing a payment saves its customer, which neither
	 * its draft nor Stripe's webhooks do, and then releases its claim. A claim left behind means a request recorded the
	 * payment but didn't finish reporting it.
	 *
	 * @param \stdClass|null $payment The payment's row, or null for none.
	 * @return bool
	 */
	private function isCompletedPayment( $payment ): bool {
		return $this->isRecordedPayment( $payment ) && is_null( $this->findPaymentCompletionClaim( (string) $payment->eventID ) );
	}

	/**
	 * Whether a request recorded the payment, which saves its customer, finished reporting it or not.
	 *
	 * @param \stdClass|null $payment The payment's row, or null for none.
	 * @return bool
	 */
	private function isRecordedPayment( $payment ): bool {
		return ! is_null( $payment ) && ! empty( $payment->stripeCustomerID );
	}

	/**
	 * Refunds a charged PaymentIntent, or cancels an authorized one, unless that was done already: a reload of the
	 * return URL runs the refund again.
	 *
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent
	 * @param string $caller The method the log line names.
	 * @param string $reason Why it is refunded, for the log.
	 * @return void
	 */
	private function refundPaymentIntentOnce( $paymentIntent, string $caller, string $reason ): void {
		if ( empty( $this->stripe->getLatestCharge( $paymentIntent )->refunded ) ) {
			$this->stripe->cancelOrRefundPaymentIntent( $paymentIntent->id );
			$this->logger->error( $caller, sprintf( 'PaymentIntent %1$s refunded: %2$s', $paymentIntent->id, $reason ) );
		}
	}

	/**
	 * Answer a request for a payment completed already, such as a reload of the return URL, without recording or
	 * reporting it again.
	 *
	 * @param PaymentIntentResult $paymentIntentResult
	 * @param string $paymentIntentId
	 * @return PaymentIntentResult
	 */
	private function createCompletedPaymentResult( $paymentIntentResult, string $paymentIntentId ) {
		$this->logger->debug( __FUNCTION__, sprintf( 'PaymentIntent %s was completed already.', $paymentIntentId ) );

		$paymentIntentResult->setSuccess( true );
		$paymentIntentResult->setMessageTitle(
			/* translators: Banner title of successful transaction */
			__( 'Success', 'wp-full-stripe-free' )
		);
		$paymentIntentResult->setMessage(
			/* translators: Banner message of successful payment */
			__( 'Payment Successful!', 'wp-full-stripe-free' )
		);

		return $paymentIntentResult;
	}

	/**
	 * Claim the completion of a payment for this request, so overlapping requests, such as reloads of the return URL,
	 * record and report it once. A claim older than COMPLETION_CLAIM_TIMEOUT was left by a request that died, or was
	 * abandoned by one that failed, and is taken over.
	 *
	 * @param string $paymentIntentId
	 * @return string|null 'new' for a new claim, 'abandoned' for one taken over, or null while another request holds it.
	 */
	private function claimPaymentCompletion( string $paymentIntentId ): ?string {
		global $wpdb;

		$claimKey = self::getPaymentCompletionClaimKey( $paymentIntentId );
		// Unlike add_option(), whose upsert overwrites a claim made after its existence check.
		$inserted = $wpdb->query(
			$wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $claimKey, (string) time() )
		);
		wp_cache_delete( $claimKey, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		if ( 1 === $inserted ) {
			return 'new';
		}

		$claimedAt = $this->findPaymentCompletionClaim( $paymentIntentId );
		if ( is_null( $claimedAt ) || time() - (int) $claimedAt < self::COMPLETION_CLAIM_TIMEOUT ) {
			return null;
		}

		// Of the requests taking an abandoned claim over, only the first changes its value.
		$taken = 1 === $wpdb->update( $wpdb->options, [ 'option_value' => (string) time() ], [ 'option_name' => $claimKey, 'option_value' => $claimedAt ] );
		wp_cache_delete( $claimKey, 'options' );

		return $taken ? 'abandoned' : null;
	}

	/**
	 * @param string $paymentIntentId
	 * @return string|null When the request completing the payment claimed it, as a Unix time, or null for no claim.
	 */
	private function findPaymentCompletionClaim( string $paymentIntentId ): ?string {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::getPaymentCompletionClaimKey( $paymentIntentId ) )
		);
	}

	/**
	 * Leave the claim of a request that failed while completing the payment to the next request, at once.
	 *
	 * @param string $paymentIntentId
	 * @return void
	 */
	private function abandonPaymentCompletion( string $paymentIntentId ): void {
		global $wpdb;

		$claimKey = self::getPaymentCompletionClaimKey( $paymentIntentId );
		$wpdb->update( $wpdb->options, [ 'option_value' => '0' ], [ 'option_name' => $claimKey ] );
		wp_cache_delete( $claimKey, 'options' );
	}

	/**
	 * @param string $paymentIntentId
	 * @return string The option a request completing the payment holds.
	 */
	private static function getPaymentCompletionClaimKey( string $paymentIntentId ): string {
		return 'wpfs_completing_payment_' . $paymentIntentId;
	}

	/**
	 * Process the payment intent charge.
	 *
	 * @param PaymentFormModel $paymentFormModel The payment form model.
	 * @param int|null $approvedAmount The amount the draft payment approved, when one was saved.
	 *
	 * @return ChargeResult The charge result.
	 *
	 * @throws \Exception If an error occurs during the process.
	 */
	private function processPaymentIntentCharge( $paymentFormModel, ?int $approvedAmount = null ) {
		$this->logger->debug( __FUNCTION__, "CALLED" );

		// Handle free transactions first, as they don't require a PaymentIntent. One Stripe charged is never free.
		if ( $this->isFreeTransactionRequest() && ! $this->isPostedPaymentIntentCharged( $paymentFormModel ) ) {
			$freeCheckData = TransactionDataService::createOneTimePaymentDataByModel( $paymentFormModel );
			$resolved      = $this->computeInlinePaymentChargeAmount( $paymentFormModel, $freeCheckData );

			if ( $resolved !== null ) {
				// Tax and/or coupon was applied; $resolved[0] is the final charge amount.
				list( $chargeAmount ) = $resolved;
				$isFree = ( $chargeAmount <= 0 );
			} else {
				// No tax/coupon/fee adjustment: the raw model amount is the charge.
				$isFree = ( (int) round( $paymentFormModel->getAmount() ) <= 0 );
			}

			if ( $isFree ) {
				return $this->processFreePayment( $paymentFormModel );
			}
		}

		$paymentIntentResult = new PaymentIntentResult();
		$paymentIntentResult->setNonce( $paymentFormModel->getNonce() );

		// The page always confirms a PaymentIntent the form priced. Charging the card here instead would skip the
		// recovery fee and the idempotency of the charge (#759).
		$paymentIntentId = $paymentFormModel->getStripePaymentIntentId();
		if ( empty( $paymentIntentId ) ) {
			return $this->refusePaymentIntentCharge(
				$paymentIntentResult,
				sprintf( 'No PaymentIntent was submitted on form %s, refusing the charge.', $paymentFormModel->getFormName() )
			);
		}

		$clientSecret = $this->readSanitizedPostValue( $_POST, 'payment_intent_client_secret' );
		$clientSecret = $clientSecret ? $clientSecret : $paymentFormModel->getStripePaymentIntentClientSecret();

		$submittedPaymentIntent = $this->getClientSecretVerifiedPaymentIntent( $paymentIntentId, $clientSecret );

		if (
			! is_null( $submittedPaymentIntent )
			&& ! $this->isPaymentIntentBoundToForm(
				$submittedPaymentIntent,
				Utils::getFormType( $paymentFormModel->getForm() ),
				$paymentFormModel->getForm()
			)
		) {
			$submittedPaymentIntent = null;
		}

		if ( is_null( $submittedPaymentIntent ) ) {
			return $this->refusePaymentIntentCharge(
				$paymentIntentResult,
				sprintf(
					'PaymentIntent %1$s does not belong to the transaction submitted on form %2$s, refusing the charge.',
					$paymentIntentId,
					$paymentFormModel->getFormName()
				)
			);
		}

		$paymentFormModel->setStripePaymentIntent( $submittedPaymentIntent );

		$draftPayment = $this->db->getPaymentByEventId( $paymentIntentId );
		if ( $this->isCompletedPayment( $draftPayment ) ) {
			return $this->createCompletedPaymentResult( $paymentIntentResult, $paymentIntentId );
		}
		// A payment refunded or released before it was recorded, such as for a product removed while the customer
		// paid, stays unrecorded, also once its product is back.
		if ( Utils::isPaymentIntentRefunded( $this->stripe, $submittedPaymentIntent ) ) {
			return $this->createRefundedPaymentResult( $paymentIntentResult );
		}

		// The form saves a draft before it confirms the PaymentIntent it was given, and the draft holds the
		// product and amount that were priced, whichever endpoint finishes the payment (#728).
		if ( is_null( $approvedAmount ) ) {
			if ( is_null( $draftPayment ) || ! $paymentFormModel->selectProductByPriceId( $draftPayment->priceId ) ) {
				if ( ! is_null( $draftPayment ) && $this->refundChargeOfRemovedProduct( $paymentFormModel->getForm(), $submittedPaymentIntent ) ) {
					return $this->createRefundedPaymentResult( $paymentIntentResult );
				}

				return $this->refusePaymentIntentCharge(
					$paymentIntentResult,
					sprintf(
						'PaymentIntent %1$s has no draft payment for a product of form %2$s, refusing the charge.',
						$paymentIntentId,
						$paymentFormModel->getFormName()
					)
				);
			}
			// The draft was priced with its coupon, and Stripe charged that price; one posted since was never applied.
			if ( (string) $paymentFormModel->getCouponCode() !== (string) $draftPayment->coupon ) {
				$paymentFormModel->restoreCouponCode( $draftPayment->coupon );
			}
			if ( empty( $draftPayment->priceId ) ) {
				// A custom amount has no product to take it from, so take the one the draft approved.
				$paymentFormModel->setAmount( (int) $draftPayment->amount );
			}
			$approvedAmount = (int) $draftPayment->amount;
		}
		// The draft holds the custom fields the form checked, as the values filter changed them, also those of a field
		// removed since.
		if ( ! is_null( $draftPayment ) && ! empty( $draftPayment->customFields ) ) {
			$paymentFormModel->restoreCustomFields( $draftPayment->customFields );
		}

		// The charge is checked against the amount its draft signed, so that amount is the one recorded.
		$this->applySignedPaymentBase( $paymentFormModel, $submittedPaymentIntent );
		$this->restoreTaxLocation( $paymentFormModel, $submittedPaymentIntent );
		if ( isset( $paymentFormModel->getStripePaymentIntent()->latest_charge ) ) {
			$latest_charge = $paymentFormModel->getStripePaymentIntent()->latest_charge;
			if ( is_string( $latest_charge ) ) {
				$latest_charge = $this->stripe->getLatestCharge( $paymentFormModel->getStripePaymentIntent() );
			}
			$paymentFormModel->setStripePaymentMethodType( $latest_charge->payment_method_details ? $latest_charge->payment_method_details->type : null );
		}

		$claim = $this->claimPaymentCompletion( $paymentIntentId );
		if ( is_null( $claim ) ) {
			$this->logger->debug( __FUNCTION__, sprintf( 'PaymentIntent %s is being completed by another request.', $paymentIntentId ) );
			$this->createPaymentIntentResultFailed(
				$paymentIntentResult,
				/* translators: Banner title of failed transaction */
				__( 'Failed', 'wp-full-stripe-free' ),
				/* translators: Banner message of a payment that another request of the same customer is completing */
				__( 'This payment is already being processed. Reload the page in a moment to see its result.', 'wp-full-stripe-free' )
			);

			return $paymentIntentResult;
		}

		$abandoned = false;
		try {
			// Another request may have completed the payment between the check above and the claim. One that failed
			// after recording it left its claim, which this request took over to finish the work.
			$payment = $this->db->getPaymentByEventId( $paymentIntentId );
			if ( 'new' === $claim && $this->isRecordedPayment( $payment ) ) {
				return $this->createCompletedPaymentResult( $paymentIntentResult, $paymentIntentId );
			}

			$createCustomerOptions = new CreateCustomerOptions();
			$createCustomerOptions->addMetadata = false;
			// The PaymentIntent is charged once, so its PaymentMethod is not saved on the customer.
			$createCustomerOptions->attachPaymentMethod = false;
			$this->createOrRetrieveCustomerByFormModel( $paymentFormModel, $createCustomerOptions );

			$transactionData = TransactionDataService::createOneTimePaymentDataByModel( $paymentFormModel );

			$this->fireBeforeInlinePaymentAction( $paymentFormModel, $transactionData );

			$paymentIntent = $paymentFormModel->getStripePaymentIntent();

			// Make sure the amount about to be charged matches the tax-inclusive total
			// shown in the form preview. This must happen before any (re-)confirmation,
			// as the amount can no longer be changed once the PaymentIntent is captured.
			$this->resyncPaymentIntentAmountWithTax( $paymentFormModel, $paymentIntent, $transactionData );

			// update description and metadata
			$transactionData->setTransactionId( $paymentIntent->id );
			$stripePaymentIntentDescription = Utils::prepareStripeChargeDescription( $this->staticContext, $paymentFormModel, $transactionData );

			$paymentIntent->description = empty( $stripePaymentIntentDescription ) ? null : $stripePaymentIntentDescription;
			// Keep the existing keys: the binding is needed to re-price after a declined card, and the
			// charge basis stamped on the draft to verify the charged amount below.
			$paymentIntent->metadata = array_merge(
				$paymentFormModel->getMetadata(),
				$this->getPaymentIntentMetadataArray( $paymentIntent )
			);

			if ( $paymentFormModel->getStripeCustomer() ) {
				$paymentIntent->customerId = $paymentFormModel->getStripeCustomer()->id;
			}

			$this->stripe->updatePaymentIntent(
				$paymentIntent,
				false,
				Mailer::canSendPaymentStripeReceipt( $paymentFormModel->getForm() ) ? $paymentFormModel->getCardHolderEmail() : null
			);

			$paymentFormModel->setTransactionId( $paymentIntent->id );

			// in some cases we need to re-confirm the PaymentIntent
			if ( \StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_CONFIRMATION === $paymentIntent->status ) {
				$paymentIntent = $this->stripe->confirmPaymentIntent( $paymentIntent->id, $paymentFormModel->getStripePaymentMethodId() );
			}
			if (
				\StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_ACTION === $paymentIntent->status
				&& 'use_stripe_sdk' === $paymentIntent->next_action->type
			) {
				$this->logger->debug( __FUNCTION__, "PaymentIntent requires action..." );

				$paymentIntentResult->setSuccess( false );
				$paymentIntentResult->setIsManualConfirmation( $paymentIntent->confirmation_method === 'manual' );
				$paymentIntentResult->setRequiresAction( true );
				$paymentIntentResult->setPaymentIntentClientSecret( $paymentIntent->client_secret );
				$paymentIntentResult->setMessageTitle(
					/* translators: Banner title of pending transaction requiring a second factor authentication (SCA/PSD2) */
					__( 'Action required', 'wp-full-stripe-free' )
				);
				$paymentIntentResult->setMessage(
					/* translators: Banner message of a one-time payment requiring a second factor authentication (SCA/PSD2) */
					__( 'The payment needs additional action before completion!', 'wp-full-stripe-free' )
				);
			} elseif (
				\StripeWPFS\Stripe\PaymentIntent::STATUS_SUCCEEDED === $paymentIntent->status
				|| \StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_CAPTURE === $paymentIntent->status
			) {
				$this->logger->debug( __FUNCTION__, "processPaymentIntentCharge(): PaymentIntent succeeded." );

				// Null when the form can no longer price the drafted payment, such as when Stripe refuses a tax rate
				// archived since. A charge the draft approved is still recorded then.
				$expectedAmount = null;
				$resolvedChargeAmount = null;
				try {
					$resolvedChargeAmount = $this->computeInlinePaymentChargeAmount( $paymentFormModel, $transactionData );
					if ( ! is_null( $resolvedChargeAmount ) ) {
						list( $expectedAmount ) = $resolvedChargeAmount;
					} else {
						$expectedAmount = $this->resolveExpectedChargeAmount( $paymentFormModel, $transactionData );
					}
					if ( $expectedAmount > 0 && (int) $paymentIntent->amount !== $expectedAmount ) {
						$chargeWithFee = $this->chargedFeeOfUnstampedDraft( $paymentFormModel, $paymentIntent, $transactionData );
						if ( ! is_null( $chargeWithFee ) ) {
							$resolvedChargeAmount = $chargeWithFee;
							$expectedAmount = (int) $paymentIntent->amount;
						}
					}
				} catch ( \Exception $ex ) {
					$this->logger->error( __FUNCTION__, sprintf( 'Cannot price PaymentIntent %s again.', $paymentIntent->id ), $ex );
				}
				if ( (int) $paymentIntent->amount !== $approvedAmount ) {
					// The PaymentIntent was re-priced after the draft, possibly to another product of the form.
					$this->refundUnapprovedCharge( $paymentIntentResult, $paymentIntent, $paymentFormModel, $approvedAmount );
				} else {
					// The fee the verified charge added, never the form's current fee choice.
					$isRecoveryFeeUnknown = false;
					if ( is_null( $expectedAmount ) ) {
						$stampedRecoveryFee = $this->resolveStampedRecoveryFee( $paymentFormModel );
						// A draft an earlier version saved has no fee stamped, so the fee of a form recovering fees is
						// recorded as part of the payment amount, and the admin is told.
						$isRecoveryFeeUnknown = is_null( $stampedRecoveryFee ) && Utils::hasFeeRecovery( $paymentFormModel->getForm() );
						$recoveryFee = (int) $stampedRecoveryFee;
					} else {
						$recoveryFee = is_null( $resolvedChargeAmount ) ? 0 : $resolvedChargeAmount[2];
					}
					$isRepriced = (int) $paymentIntent->amount !== $expectedAmount;
					if ( $isRepriced ) {
						$this->noticeRepricedCharge( $paymentFormModel, $paymentIntent, $expectedAmount, $isRecoveryFeeUnknown );
						$this->reconcileChargedPricing( $transactionData, (int) $paymentIntent->amount, $recoveryFee );
					}
					$transactionData->setRecoveryFee( $recoveryFee );
					$transactionData->setChargedTotal( (int) $paymentIntent->amount );
					$paymentIntent->wpfs_form = $paymentFormModel->getFormName();
					$paymentFormModel->setStripePaymentIntent( $paymentIntent );
					if ( ! isset( $latest_charge ) || empty( $latest_charge ) ) {
						$latest_charge = $this->stripe->getLatestCharge( $paymentIntent );
					}
					// From Stripe's latest charge, so that a refund recorded since the one above was read is kept.
					LatestCharge::write(
						$this->db,
						$this->stripe,
						$paymentIntent->id,
						function ( $freshCharge ) use ( $paymentFormModel, $transactionData, $latest_charge ) {
							$this->db->insertOrUpdatePayment( $paymentFormModel, $transactionData, $freshCharge ?? $latest_charge );
						},
						false
					);

					// An invoice of what the form prices now would not add up to the charge, and Stripe would tax and
					// discount one made of the recorded amounts again. The admin email says it has none.
					if ( ! $isRepriced ) {
						$this->createInvoiceOfInlinePayment( $paymentFormModel, $transactionData, $paymentIntent, $recoveryFee );
					}

					$this->fireAfterInlinePaymentAction( $paymentFormModel, $transactionData, $paymentIntent );

					$paymentIntentResult->setRequiresAction( false );
					$paymentIntentResult->setSuccess( true );
					$paymentIntentResult->setMessageTitle(
						/* translators: Banner title of successful transaction */
						__( 'Success', 'wp-full-stripe-free' )
					);
					$paymentIntentResult->setMessage(
						/* translators: Banner message of successful payment */
						__( 'Payment Successful!', 'wp-full-stripe-free' )
					);
				}
			} else {
				// A PaymentIntent still processing, such as a bank debit, is not completed here, as before: Stripe's charge
				// webhooks update its draft once it settles, and integrations are not told about money that may not arrive.
				$paymentIntentResult->setSuccess( false );
				$paymentIntentResult->setMessageTitle(
					/* translators: Banner title of failed transaction */
					__( 'Failed', 'wp-full-stripe-free' )
				);
				$paymentIntentResult->setMessage(
					sprintf(
						/* translators: %s: status reported by Stripe */
						__( "Invalid PaymentIntent status '%s'.", 'wp-full-stripe-free' ),
						$paymentIntent->status
					)
				);
			}

			$this->handleRedirect( $paymentFormModel, $transactionData, $paymentIntentResult );

			if ( $paymentIntentResult->isSuccess() ) {
				if ( Mailer::canSendPaymentPluginReceipt( $paymentFormModel->getForm() ) ) {
					$this->mailer->sendOneTimePaymentReceipt( $paymentFormModel->getForm(), $transactionData );
				}
			}
		} catch ( \Throwable $ex ) {
			$abandoned = true;
			$this->abandonPaymentCompletion( $paymentIntentId );
			throw $ex;
		} finally {
			if ( ! $abandoned ) {
				delete_option( self::getPaymentCompletionClaimKey( $paymentIntentId ) );
			}
		}

		return $paymentIntentResult;
	}

	/**
	 * @param TransactionResult $transactionResult
	 *
	 * @return array
	 */
	private function generateReturnValueFromTransactionResult( $transactionResult ) {
		$returnValue = [
			'success' => $transactionResult->isSuccess(),
			'messageTitle' => $transactionResult->getMessageTitle(),
			'message' => $transactionResult->getMessage(),
			'redirect' => $transactionResult->isRedirect(),
			'redirectURL' => $transactionResult->getRedirectURL(),
			'requiresAction' => $transactionResult->isRequiresAction(),
			'paymentIntentClientSecret' => $transactionResult->getPaymentIntentClientSecret(),
			'setupIntentClientSecret' => $transactionResult->getSetupIntentClientSecret(),
			'formType' => $transactionResult->getFormType(),
			'nonce' => $transactionResult->getNonce(),
		];

		return $returnValue;
	}

	function fullstripe_inline_subscription_charge() {

		try {

			$subscriptionFormModel = new InlineSubscriptionFormModel( $this->loggerService );
			$bindingResult = $subscriptionFormModel->bind();

			if ( $bindingResult->hasErrors() ) {
				$return = Utils::generateReturnValueFromBindings( $bindingResult );
			} else {
				$subscriptionResult = $this->processSubscription( $subscriptionFormModel );
				$return = self::generateReturnValueFromTransactionResult( $subscriptionResult );
			}
		} catch (UserFriendlyException $ex) {
			$this->logger->error( __FUNCTION__, "User-friendly exception while processing subscription charge", $ex );

			$messageTitle = is_null( $ex->getTitle() ) ?
				/* translators: Banner title of an error returned from an extension point by a developer */
				__( 'Internal Error', 'wp-full-stripe-free' ) :
				$ex->getTitle();
			$message = $ex->getMessage();
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\StripeWPFS\Stripe\Exception\CardException $ex) {
			$this->logger->error( __FUNCTION__, "Card exception while processing subscription charge", $ex );

			$messageTitle =
				/* translators: Banner title of error returned by Stripe */
				__( 'Stripe Error', 'wp-full-stripe-free' );
			$message = $this->stripe->resolveErrorMessageByCode( $ex->getCode() );
			if ( is_null( $message ) ) {
				$message = Localization::translateLabel( $ex->getMessage() );
			}
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, "Generic exception while processing subscription charge", $ex );

			$return = [
				'success' => false,
				'messageTitle' =>
					/* Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				'message' => Localization::translateLabel( $ex->getMessage() ),
				'exceptionMessage' => $ex->getMessage()
			];
		}

		header( "Content-Type: application/json" );
		echo json_encode( apply_filters( 'fullstripe_inline_subscription_charge_return_message', $return ) );
		exit;

	}

	/**
	 * @param SubscriptionFormModel $subscriptionFormModel
	 * @param SubscriptionTransactionData $transactionData
	 */
	protected function fireBeforeInlineSubscriptionAction( $subscriptionFormModel, $transactionData ) {
		$params = [
			'email' => $subscriptionFormModel->getCardHolderEmail(),
			'urlParameters' => $subscriptionFormModel->getFormGetParametersAsArray(),
			'formName' => $subscriptionFormModel->getFormName(),
			'productName' => $subscriptionFormModel->getProductName(),
			'planId' => $subscriptionFormModel->getStripePlanId(),
			'currency' => $transactionData->getPlanCurrency(),
			'amount' => $subscriptionFormModel->getPlanAmount(),
			'setupFee' => $subscriptionFormModel->getSetupFee(),
			'quantity' => $subscriptionFormModel->getStripePlanQuantity(),
			'stripeClient' => $this->stripe->getStripeClient(),
		];

		do_action( Main::ACTION_NAME_BEFORE_SUBSCRIPTION_CHARGE, $params );
	}

	/**
	 * @param SubscriptionFormModel $subscriptionFormModel
	 * @param SubscriptionTransactionData $transactionData
	 * @param \StripeWPFS\Stripe\Subscription $subscription
	 */
	protected function fireAfterInlineSubscriptionAction( $subscriptionFormModel, $transactionData, $subscription ) {
		$replacer = new SubscriptionMacroReplacer( $subscriptionFormModel->getForm(), $transactionData, $this->loggerService );

		$params = [
			'email' => $subscriptionFormModel->getCardHolderEmail(),
			'urlParameters' => $subscriptionFormModel->getFormGetParametersAsArray(),
			'formName' => $subscriptionFormModel->getFormName(),
			'productName' => $subscriptionFormModel->getProductName(),
			'planId' => $subscriptionFormModel->getStripePlanId(),
			'currency' => $transactionData->getPlanCurrency(),
			'amount' => $transactionData->getAmount(),
			'recoveryFee' => $transactionData->getRecoveryFee(),
			'chargedTotal' => $transactionData->getChargedTotal(),
			'setupFee' => $subscriptionFormModel->getSetupFee(),
			'quantity' => $subscriptionFormModel->getStripePlanQuantity(),
			'stripeClient' => $this->stripe->getStripeClient(),
			'stripeSubscription' => $subscription,
			'rawPlaceholders' => $replacer->getRawKeyValuePairs(),
			'decoratedPlaceholders' => $replacer->getDecoratedKeyValuePairs(),
		];

		do_action( Main::ACTION_NAME_AFTER_SUBSCRIPTION_CHARGE, $params );
		do_action( Main::ACTION_NAME_FIRE_WEBHOOK, $subscriptionFormModel->getForm(), $params );
	}

	/**
	 * @param SubscriptionTransactionData $transactionData
	 * @param \StripeWPFS\Stripe\Invoice $invoice The subscription's first invoice.
	 * @param list<object{taxRateId?: string, percentage?: int|float}> $formTaxRates The form's tax rates, as its vatRates setting stores them.
	 */
	private function updateSubscriptionTransactionDataPricing( &$transactionData, $invoice, $formTaxRates ) {
		$pricingDetails = Pricing::extractSubscriptionPricingFromInvoice( $invoice, $transactionData->getPlanId() );

		$planTaxRate = Pricing::getTaxRateOfLineItem( Pricing::getPlanLineItem( $invoice, $transactionData->getPlanId() ), $formTaxRates );
		if ( $planTaxRate['taxed'] ) {
			$transactionData->setPlanAmountVATRate( $planTaxRate['percent'] );
		}
		$setupFeeLineItem = Pricing::getSetupFeeLineItemOfFirstInvoice( $invoice );
		$setupFeeTaxRate = Pricing::getTaxRateOfLineItem( is_object( $setupFeeLineItem ) ? $setupFeeLineItem : null, $formTaxRates );
		if ( $setupFeeTaxRate['taxed'] ) {
			$transactionData->setPlanSetupFeeVATRate( $setupFeeTaxRate['percent'] );
		}

		$setupFeeAmount =
			$setupFeeTaxInclusive =
			$setupFeeTaxExclusive =
			$setupFeeDiscount = 0;
		if ( $pricingDetails->setupFee !== null ) {
			$setupFeeAmount = $pricingDetails->setupFee->amount;
			$setupFeeTaxExclusive = $pricingDetails->setupFee->taxExclusive;
			$setupFeeTaxInclusive = $pricingDetails->setupFee->taxInclusive;
			$setupFeeDiscount = $pricingDetails->setupFee->discount;
		}

		$transactionData->setPlanQuantity( $pricingDetails->product->quantity );

		$isTrial = $pricingDetails->product->amount == 0 && $transactionData->getTrialPeriodDays() > 0;
		if ( $isTrial ) {
			// the subscription is in a trial phase so Stripe replied back with a $0 value
			// save the original values so that they can be used in templates
			$transactionData->setPlanFutureNetAmount( $transactionData->getPlanNetAmount() );
			$transactionData->setPlanFutureTaxAmount( $transactionData->getPlanTaxAmount() );
			$transactionData->setPlanFutureGrossAmount( $transactionData->getPlanNetAmount() + $transactionData->getPlanTaxAmount() );
		}

		$transactionData->setPlanNetAmountTotal( $pricingDetails->product->amount - $pricingDetails->product->discount - $pricingDetails->product->taxInclusive );
		$transactionData->setPlanTaxAmountTotal( $pricingDetails->product->taxInclusive + $pricingDetails->product->taxExclusive );
		$transactionData->setPlanGrossAmountTotal( $transactionData->getPlanNetAmountTotal() + $transactionData->getPlanTaxAmountTotal() );
		$transactionData->setPlanNetAmount( $transactionData->getPlanNetAmountTotal() / $transactionData->getPlanQuantity() );
		$transactionData->setPlanTaxAmount( $transactionData->getPlanTaxAmountTotal() / $transactionData->getPlanQuantity() );
		$transactionData->setPlanGrossAmount( $transactionData->getPlanGrossAmountTotal() / $transactionData->getPlanQuantity() );
		if ( ! $isTrial ) {
			// Without a trial, the next periods cost what this one was charged, tax included.
			$transactionData->setPlanFutureNetAmount( $transactionData->getPlanNetAmount() );
			$transactionData->setPlanFutureTaxAmount( $transactionData->getPlanTaxAmount() );
			$transactionData->setPlanFutureGrossAmount( $transactionData->getPlanGrossAmount() );
		}

		$transactionData->setSetupFeeNetAmountTotal( $setupFeeAmount - $setupFeeDiscount - $setupFeeTaxInclusive );
		$transactionData->setSetupFeeTaxAmountTotal( $setupFeeTaxExclusive + $setupFeeTaxInclusive );
		$transactionData->setSetupFeeGrossAmountTotal( $transactionData->getSetupFeeNetAmountTotal() + $transactionData->getSetupFeeTaxAmountTotal() );
		$transactionData->setSetupFeeNetAmount( $transactionData->getSetupFeeNetAmountTotal() / $transactionData->getPlanQuantity() );
		$transactionData->setSetupFeeTaxAmount( $transactionData->getSetupFeeTaxAmountTotal() / $transactionData->getPlanQuantity() );
		$transactionData->setSetupFeeGrossAmount( $transactionData->getSetupFeeGrossAmountTotal() / $transactionData->getPlanQuantity() );

		$transactionData->setAmount( $transactionData->getPlanGrossAmountTotal() + $transactionData->getSetupFeeGrossAmountTotal() );
	}

	/**
	 * Records the recovery fee and the total a subscription's first invoice charged: the line of its fee item, and
	 * the invoice's total. An inline subscription's fee item is priced with the shared fee price, known by its lookup
	 * key, so no product needs to be looked up.
	 *
	 * @param SubscriptionTransactionData $transactionData
	 * @param object $subscription
	 * @param object $invoice The subscription's first invoice.
	 */
	private function setChargedSubscriptionFee( $transactionData, $subscription, $invoice ): void {
		RecoveryFee::setChargedFeeOfInvoice( $transactionData, $invoice, RecoveryFee::findPlanAndFeeItems( $subscription, $transactionData->getPlanId() ) );
	}

	private function getInvoiceId( $invoiceOrId ) {
		if ( isset( $invoiceOrId->id ) && ! empty( $invoiceOrId->id ) && is_string( $invoiceOrId->id ) ) {
			return $invoiceOrId->id;
		}

		return $invoiceOrId;
	}

	private function retrieveInvoiceExpanded( $invoice ) {
		if ( isset( $invoice->lines ) ) {
			return $invoice;
		}

		return $this->stripe->retrieveInvoice( $this->getInvoiceId( $invoice ) );
	}

	/**
	 * @param SubscriptionFormModel $subscriptionFormModel
	 *
	 * @return SubscriptionResult
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	private function processSubscription( $subscriptionFormModel ) {
		$subscriptionResult = new SubscriptionResult();
		$subscriptionResult->setNonce( $subscriptionFormModel->getNonce() );

		$stripeSubscription = null;
		$stripePaymentIntent = null;
		$stripeSetupIntent = null;
		$stripeCustomer = null;
		$wpfsSubscriber = null;

		// A second request finishes the subscription the first one created, for the plan and quantity it
		// recorded. Refuse any other before the customer is touched (#728).
		$paymentIntentId = $subscriptionFormModel->getStripePaymentIntentId();
		if ( ! empty( $paymentIntentId ) ) {
			$stripePaymentIntent = $this->getClientSecretVerifiedPaymentIntent( $paymentIntentId, $subscriptionFormModel->getStripePaymentIntentClientSecret() );

			if (
				! is_null( $stripePaymentIntent )
				&& ! $this->isPaymentIntentBoundToForm(
					$stripePaymentIntent,
					Utils::getFormType( $subscriptionFormModel->getForm() ),
					$subscriptionFormModel->getForm()
				)
			) {
				$stripePaymentIntent = null;
			}

			if ( is_null( $stripePaymentIntent ) ) {
				return $this->refuseSubscriptionConfirmation(
					$subscriptionResult,
					sprintf(
						'PaymentIntent %1$s does not belong to the transaction submitted on form %2$s, refusing the subscription.',
						$paymentIntentId,
						$subscriptionFormModel->getFormName()
					)
				);
			}

			$wpfsSubscriber = $this->db->findSubscriberByPaymentIntentId( $stripePaymentIntent->id );
			$stripeSubscription = $this->retrieveSubscriptionOfSubmittedPlan( $wpfsSubscriber, $subscriptionFormModel );
			if ( is_null( $stripeSubscription ) ) {
				return $this->refuseSubscriptionConfirmation( $subscriptionResult, $this->describeUnknownSubscriber( $stripePaymentIntent->id, $subscriptionFormModel ) );
			}
		}
		if ( ! empty( $subscriptionFormModel->getStripeSetupIntentId() ) ) {
			// No client secret comes with the SetupIntent, so the recorded subscriber is all that ties it to the form.
			$wpfsSubscriber = $this->db->findSubscriberBySetupIntentId( $subscriptionFormModel->getStripeSetupIntentId() );
			$stripeSubscription = $this->retrieveSubscriptionOfSubmittedPlan( $wpfsSubscriber, $subscriptionFormModel );
			if ( is_null( $stripeSubscription ) ) {
				return $this->refuseSubscriptionConfirmation( $subscriptionResult, $this->describeUnknownSubscriber( $subscriptionFormModel->getStripeSetupIntentId(), $subscriptionFormModel ) );
			}
		}
		// The first request saved the custom fields the form checked, also those of a field removed since.
		if ( ! is_null( $wpfsSubscriber ) && ! empty( $wpfsSubscriber->customFields ) ) {
			$subscriptionFormModel->restoreCustomFields( $wpfsSubscriber->customFields );
		}

		$createCustomerOptions = new CreateCustomerOptions();
		$createCustomerOptions->addMetadata = false;
		$this->createOrRetrieveCustomerByFormModel( $subscriptionFormModel, $createCustomerOptions );

		$transactionData = TransactionDataService::createSubscriptionDataByModel( $subscriptionFormModel );

		if (
			empty( $subscriptionFormModel->getStripePaymentIntentId() )
			&& empty( $subscriptionFormModel->getStripeSetupIntentId() )
		) {
			$this->logger->debug( __FUNCTION__, "Creating Subscription..." );

			$this->fireBeforeInlineSubscriptionAction( $subscriptionFormModel, $transactionData );

			$createSubscriptionOptions = new CreateSubscriptionOptions();
			$createSubscriptionOptions->taxRateIds = Pricing::extractTaxRateIdsStatic( $this->getApplicableTaxRates( $subscriptionFormModel ) );
			$stripeSubscription = $this->createSubscription( $subscriptionFormModel, $transactionData, $createSubscriptionOptions );
			// Before the calls below: if one fails, the subscription has no row, but the fee sync still sends it.
			FeeSync::keepUntracked( $stripeSubscription );

			$stripeCustomer = $this->stripe->retrieveCustomer( $stripeSubscription->customer );
			$subscriptionFormModel->setStripeCustomer( $stripeCustomer );
			$subscriptionFormModel->setStripeSubscription( $stripeSubscription );
			$subscriptionFormModel->setTransactionId( $stripeSubscription->id );
			$transactionData->setTransactionId( $stripeSubscription->id );

			if ( isset( $stripeSubscription ) ) {
				if ( isset( $stripeSubscription->latest_invoice ) && isset( $stripeSubscription->latest_invoice->id ) ) {
					$payments = $stripeSubscription->latest_invoice->payments->data ?? [];

					$stripePaymentIntent = null;

					foreach ( $payments as $payment ) {
						if (
							isset( $payment->payment->type ) &&
							$payment->payment->type === 'payment_intent' &&
							isset( $payment->payment->payment_intent )
						) {
							$stripePaymentIntent = $this->stripe->retrievePaymentIntent( $payment->payment->payment_intent );
							break; // You can break after first match, or collect all if needed
						}
					}

					if ( isset( $stripePaymentIntent ) && ! empty( $stripePaymentIntent ) ) {
						// Update payment intent for metadata
						$this->updatePaymentIntentWithMetadataAndWebhookUrl( $stripePaymentIntent, $subscriptionFormModel );
						$subscriptionFormModel->setStripePaymentIntent( $stripePaymentIntent );
					} else {
						$this->logger->error( __FUNCTION__, "Subscription has no payment intent." );
					}
					$subscriptionFormModel->setTransactionId( $stripeSubscription->id );
					$transactionData->setTransactionId( $subscriptionFormModel->getTransactionId() );
				}
				if ( isset( $stripeSubscription->pending_setup_intent ) ) {
					$stripeSetupIntent = $stripeSubscription->pending_setup_intent;
					$subscriptionFormModel->setStripeSetupIntent( $stripeSetupIntent );
					$subscriptionFormModel->setTransactionId( $stripeSubscription->id );
					$transactionData->setTransactionId( $subscriptionFormModel->getTransactionId() );
				}
			}

			// tnagy insert subscriber
			$stripePaymentIntent = $subscriptionFormModel->getStripePaymentIntent();
			if ( isset( $stripePaymentIntent ) ) {
				// Its report from Stripe's latest charge, so that a refund recorded meanwhile is kept.
				LatestCharge::write(
					$this->db,
					$this->stripe,
					$stripePaymentIntent->id,
					function ( $latestCharge ) use ( $subscriptionFormModel, $transactionData, $stripePaymentIntent ) {
						$this->db->insertSubscriber( $subscriptionFormModel, $transactionData, $latestCharge ?? LatestCharge::expandedOf( $stripePaymentIntent ) );
					},
					false
				);
			} else {
				$this->db->insertSubscriber( $subscriptionFormModel, $transactionData );
			}
			// Checked now: with 3D Secure, the request that finishes the subscription may never come.
			FeeSync::checkRecordedFee( $stripeSubscription );

		} else {
			$this->logger->debug( __FUNCTION__, "Retrieving Subscription..." );

			if ( ! empty( $paymentIntentId ) ) {
				// Update payment intent for metadata, keeping the binding it was created with.
				$this->updatePaymentIntentWithMetadataAndWebhookUrl(
					$stripePaymentIntent,
					$subscriptionFormModel,
					Main::FORM_TYPE_INLINE_SUBSCRIPTION,
					false
				);

				$stripeCustomer = $this->stripe->retrieveCustomer( $stripePaymentIntent->customer );
				$subscriptionFormModel->setStripeCustomer( $stripeCustomer );
			}
			if ( ! empty( $subscriptionFormModel->getStripeSetupIntentId() ) ) {
				$stripeSetupIntent = $this->stripe->retrieveSetupIntent( $subscriptionFormModel->getStripeSetupIntentId() );
				if ( isset( $stripeSetupIntent ) ) {
					$stripeCustomer = $this->stripe->retrieveCustomer( $stripeSetupIntent->customer );
					$subscriptionFormModel->setStripeCustomer( $stripeCustomer );
				}
			}
			// tnagy update transaction id
			$subscriptionFormModel->setTransactionId( $stripeSubscription->id );
			$transactionData->setTransactionId( $subscriptionFormModel->getTransactionId() );
		}

		$latestInvoice = null;
		if ( isset( $stripeSubscription->latest_invoice ) ) {
			$latestInvoice = $this->retrieveInvoiceExpanded( $stripeSubscription->latest_invoice );
		}
		if ( isset( $stripeSubscription ) ) {
			// Report the setup fee the subscription was created with, not one changed on the form since.
			// The subscription was just created or confirmed, so its latest invoice is its first.
			$subscriptionFormModel->setSetupFeeFromSubscription(
				$stripeSubscription,
				is_null( $latestInvoice ) ? null : Pricing::getSetupFeeOfFirstInvoice( $latestInvoice )
			);
		}
		if ( ! is_null( $latestInvoice ) ) {
			$transactionData->setInvoiceUrl( $latestInvoice->invoice_pdf );
			$transactionData->setInvoiceNumber( $latestInvoice->number );
			$transactionData->setReceiptUrl( $this->stripe->getReceiptUrl( $stripePaymentIntent ) );
			$this->updateSubscriptionTransactionDataPricing( $transactionData, $latestInvoice, Utils::decodeJsonArray( $subscriptionFormModel->getForm()->vatRates ) );
			$this->setChargedSubscriptionFee( $transactionData, $stripeSubscription, $latestInvoice );
		}
		$transactionData->setStripeCustomerId( $stripeCustomer->id );

		$this->handleIntent( $subscriptionResult, $stripeSubscription, $stripePaymentIntent, $stripeSetupIntent );
		$this->handleRedirect( $subscriptionFormModel, $transactionData, $subscriptionResult );
		if ( $subscriptionResult->isSuccess() ) {
			$this->fireAfterInlineSubscriptionAction( $subscriptionFormModel, $transactionData, $stripeSubscription );

			if ( Mailer::canSendSubscriptionPluginReceipt( $subscriptionFormModel->getForm() ) ) {
				$this->mailer->sendSubscriptionStartedEmailReceipt( $subscriptionFormModel->getForm(), $transactionData );
			}
		}

		return $subscriptionResult;
	}

	/**
	 * The Stripe subscription the first request of an inline subscription created, if it is the one the
	 * request finishing it posts: the same form, plan, coupon and quantity.
	 *
	 * @param object|null $wpfsSubscriber The subscriber row recorded for the posted intent.
	 * @param SubscriptionFormModel $subscriptionFormModel
	 * @return \StripeWPFS\Stripe\Subscription|null
	 */
	private function retrieveSubscriptionOfSubmittedPlan( $wpfsSubscriber, $subscriptionFormModel ) {
		if (
			is_null( $wpfsSubscriber )
			|| empty( $wpfsSubscriber->stripeSubscriptionID )
			|| (string) $wpfsSubscriber->formName !== (string) $subscriptionFormModel->getForm()->name
			|| (string) $wpfsSubscriber->planID !== (string) $subscriptionFormModel->getStripePlanId()
			|| (string) $wpfsSubscriber->coupon !== (string) $subscriptionFormModel->getCouponCode()
		) {
			return null;
		}

		$stripeSubscription = $this->stripe->retrieveSubscription( $wpfsSubscriber->stripeSubscriptionID );

		// The quantity is the plan item's: a subscription with a fee recovery line has none of its own.
		$items = isset( $stripeSubscription->items->data ) ? $stripeSubscription->items->data : [];
		foreach ( $items as $item ) {
			$itemPlanId = isset( $item->price->id ) ? $item->price->id : ( isset( $item->plan->id ) ? $item->plan->id : null );
			if ( (string) $itemPlanId === (string) $wpfsSubscriber->planID ) {
				return (int) $item->quantity === (int) $subscriptionFormModel->getStripePlanQuantity() ? $stripeSubscription : null;
			}
		}

		return null;
	}

	/**
	 * @param string $intentId The PaymentIntent or SetupIntent the request posted.
	 * @param SubscriptionFormModel $subscriptionFormModel
	 * @return string
	 */
	private function describeUnknownSubscriber( $intentId, $subscriptionFormModel ): string {
		return sprintf(
			'Intent %1$s has no subscriber for the plan submitted on form %2$s, refusing the subscription.',
			$intentId,
			$subscriptionFormModel->getFormName()
		);
	}

	/**
	 * @param SubscriptionResult $subscriptionResult
	 * @param string $reason What the log records about the refused request.
	 * @return SubscriptionResult
	 */
	private function refuseSubscriptionConfirmation( $subscriptionResult, string $reason ) {
		$this->logger->error( __FUNCTION__, $reason );

		$subscriptionResult->setSuccess( false );
		$subscriptionResult->setMessageTitle(
			/* translators: Banner title of failed transaction */
			__( 'Failed', 'wp-full-stripe-free' )
		);
		$subscriptionResult->setMessage( __( 'Invalid request', 'wp-full-stripe-free' ) );

		return $subscriptionResult;
	}

	/**
	 * Updates the given result by the given PaymentIntent or SetupIntent. When no PaymentIntent nor
	 * SetupIntent are given, we consider the subscription as successful.
	 *
	 * @param SubscriptionResult $subscriptionResult
	 * @param \StripeWPFS\Stripe\Subscription $subscription
	 * @param \StripeWPFS\Stripe\PaymentIntent $paymentIntent
	 * @param \StripeWPFS\Stripe\SetupIntent $setupIntent
	 */
	private function handleIntent( $subscriptionResult, $subscription, $paymentIntent, $setupIntent ) {
		if ( isset( $paymentIntent ) ) {
			if (
				\StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_ACTION === $paymentIntent->status
				&& 'use_stripe_sdk' === $paymentIntent->next_action->type
			) {
				$this->logger->debug( __FUNCTION__, "PaymentIntent requires action..." );

				$subscriptionResult->setSuccess( false );
				$subscriptionResult->setRequiresAction( true );
				$subscriptionResult->setPaymentIntentClientSecret( $paymentIntent->client_secret );
				$subscriptionResult->setMessageTitle(
					/* translators: Banner title of pending transaction requiring a second factor authentication (SCA/PSD2) */
					__( 'Action required', 'wp-full-stripe-free' )
				);
				$subscriptionResult->setMessage(
					/* translators: Banner message of a one-time payment requiring a second factor authentication (SCA/PSD2) */
					__( 'The payment needs additional action before completion!', 'wp-full-stripe-free' )
				);
			} elseif (
				\StripeWPFS\Stripe\PaymentIntent::STATUS_SUCCEEDED === $paymentIntent->status
				|| \StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_CAPTURE === $paymentIntent->status
				|| \StripeWPFS\Stripe\PaymentIntent::STATUS_PROCESSING === $paymentIntent->status
			) {
				$this->logger->debug( __FUNCTION__, "PaymentIntent succeeded." );

				// Held as a failed first bank debit's webhook holds it: either it finds the subscription running, or this finds
				// the charge failed.
				LatestCharge::write(
					$this->db,
					$this->stripe,
					$paymentIntent->id,
					function ( $latestCharge ) use ( $paymentIntent ) {
						if ( Main::STRIPE_CHARGE_STATUS_FAILED !== ( $latestCharge->status ?? null ) ) {
							$this->db->updateSubscriptionByPaymentIntentToRunning( $paymentIntent->id );
						}
					},
					false
				);
				$subscriptionResult->setRequiresAction( false );
				$subscriptionResult->setSuccess( true );
				$subscriptionResult->setMessageTitle(
					/* translators: Banner title of successful transaction */
					__( 'Success', 'wp-full-stripe-free' )
				);
				$subscriptionResult->setMessage(
					/* translators: Banner message of successful payment */
					__( 'Payment Successful!', 'wp-full-stripe-free' )
				);
			} else {
				$subscriptionResult->setSuccess( false );
				$subscriptionResult->setMessageTitle(
					/* translators: Banner title of failed transaction */
					__( 'Failed', 'wp-full-stripe-free' )
				);
				$subscriptionResult->setMessage(
					sprintf(
						/* translators: %s: status reported by Stripe */
						__( "Invalid PaymentIntent status '%s'.", 'wp-full-stripe-free' ),
						$paymentIntent->status
					)
				);
			}
		} elseif ( isset( $setupIntent ) ) {
			if (
				\StripeWPFS\Stripe\SetupIntent::STATUS_REQUIRES_ACTION === $setupIntent->status
				&& 'use_stripe_sdk' === $setupIntent->next_action->type
			) {
				$this->logger->debug( __FUNCTION__, "SetupIntent requires action..." );

				$subscriptionResult->setSuccess( false );
				$subscriptionResult->setRequiresAction( true );
				$subscriptionResult->setSetupIntentClientSecret( $setupIntent->client_secret );
				$subscriptionResult->setMessageTitle(
					/* translators: Banner title of pending transaction requiring a second factor authentication (SCA/PSD2) */
					__( 'Action required', 'wp-full-stripe-free' )
				);
				$subscriptionResult->setMessage(
					/* translators: Banner message of a one-time payment requiring a second factor authentication (SCA/PSD2) */
					__( 'The payment needs additional action before completion!', 'wp-full-stripe-free' )
				);
			} elseif (
				\StripeWPFS\Stripe\SetupIntent::STATUS_SUCCEEDED === $setupIntent->status
			) {
				$this->logger->debug( __FUNCTION__, "SetupIntent succeeded." );

				$this->db->updateSubscriptionBySetupIntentToRunning( $setupIntent->id );
				$subscriptionResult->setRequiresAction( false );
				$subscriptionResult->setSuccess( true );
				$subscriptionResult->setMessageTitle(
					/* translators: Banner title of successful transaction */
					__( 'Success', 'wp-full-stripe-free' )
				);
				$subscriptionResult->setMessage(
					/* translators: Banner message of successful payment */
					__( 'Payment Successful!', 'wp-full-stripe-free' )
				);
			} else {
				$subscriptionResult->setSuccess( false );
				$subscriptionResult->setMessageTitle(
					/* translators: Banner title of failed transaction */
					__( 'Failed', 'wp-full-stripe-free' )
				);
				$subscriptionResult->setMessage(
					sprintf(
						/* translators: %s: status reported by Stripe */
						__( "Invalid PaymentIntent status '%s'.", 'wp-full-stripe-free' ),
						$setupIntent->status
					)
				);
			}
		} else {
			/*
			 * WPFS-1012: When a Subscription has a trial period without a setup fee then the Invoice has no
			 * PaymentIntent. When SCA is not triggered then the pending SetupIntent is also missing.
			 * In these cases the PaymentIntent and SetupIntent are both null.
			 * We consider these subscriptions as successful.
			 */
			$this->db->updateNewSubscriptionToRunning( $subscription->id );
			$subscriptionResult->setRequiresAction( false );
			$subscriptionResult->setSuccess( true );
			$subscriptionResult->setMessageTitle(
				/* translators: Banner title of successful transaction */
				__( 'Success', 'wp-full-stripe-free' )
			);
			$subscriptionResult->setMessage(
				/* translators: Banner message of successful payment */
				__( 'Payment Successful!', 'wp-full-stripe-free' )
			);
		}
	}

	/**
	 * @param $stripePaymentIntent
	 * @param $subscriptionFormModel
	 * @param string $formType Form type the subscription belongs to.
	 * @param bool $createBinding Whether to bind the PaymentIntent to the form.
	 * @return void
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	public function updatePaymentIntentWithMetadataAndWebhookUrl( $stripePaymentIntent, $subscriptionFormModel, $formType = Main::FORM_TYPE_INLINE_SUBSCRIPTION, $createBinding = true ): void {
		// Update payment intent for metadata
		$metadata = $subscriptionFormModel->getMetadata();

		$metadata = PaymentIntentBinding::preserveBindingMetadata( $stripePaymentIntent, $metadata );
		$stripePaymentIntent->metadata = $createBinding ?
			PaymentIntentBinding::createBindingMetadata(
				$formType,
				$subscriptionFormModel->getForm(),
				$metadata
			) :
			$metadata;

		$this->stripe->updatePaymentIntent( $stripePaymentIntent, false, Mailer::canSendSubscriptionStripeReceipt( $subscriptionFormModel->getForm() ) ? $subscriptionFormModel->getCardHolderEmail() : null );
	}

	/**
	 * @param CheckoutPaymentFormModel $saveCardFormModel
	 */
	protected function fireBeforeCheckoutSaveCardAction( $saveCardFormModel ) {
		$params = [
			'urlParameters' => $saveCardFormModel->getFormGetParametersAsArray(),
			'formName' => $saveCardFormModel->getFormName(),
			'stripeClient' => $this->stripe->getStripeClient(),
		];

		do_action( Main::ACTION_NAME_BEFORE_CHECKOUT_SAVE_CARD, $params );
	}

	/**
	 * @param CheckoutPaymentFormModel $paymentFormModel
	 * @param PaymentTransactionData $transactionData
	 */
	private function fireBeforeCheckoutPaymentAction( $paymentFormModel, $transactionData ) {
		$params = [
			'urlParameters' => $paymentFormModel->getFormGetParametersAsArray(),
			'formName' => $paymentFormModel->getFormName(),
			'priceId' => $paymentFormModel->getPriceId(),
			'productName' => $paymentFormModel->getProductName(),
			'currency' => $transactionData->getCurrency(),
			'amount' => $paymentFormModel->getAmount(),
			'stripeClient' => $this->stripe->getStripeClient(),
		];

		do_action( Main::ACTION_NAME_BEFORE_CHECKOUT_PAYMENT_CHARGE, $params );
	}

	/**
	 * Stripe refuses a Checkout session with a price, or a price of a product, that the site owner archived. Says so
	 * before the before-charge hook fires and the submission is saved. Only fresh submissions get here: Checkout
	 * completion binds the saved submission elsewhere.
	 *
	 * @param CheckoutPaymentFormModel $paymentFormModel
	 * @param BindingResult $bindingResult
	 *
	 * @return void
	 */
	private function refuseArchivedCheckoutPrice( $paymentFormModel, $bindingResult ) {
		$priceId = $paymentFormModel->getPriceId();
		if ( empty( $priceId ) ) {
			return;
		}

		$price = $this->stripe->retrievePlan( $priceId );
		if ( is_null( $price ) ) {
			// retrievePlan() logs and swallows the error; Stripe would likely refuse the session too.
			$bindingResult->addGlobalError(
				/* translators: Error when the product chosen on a Checkout payment form couldn't be looked up in Stripe */
				__( 'This product could not be checked with Stripe. Please try again.', 'wp-full-stripe-free' )
			);
		} elseif ( Utils::isArchivedPrice( $price ) ) {
			$bindingResult->addGlobalError(
				/* translators: Error when the product chosen on a Checkout payment form was archived in Stripe */
				__( 'This product is no longer available. Please choose another product.', 'wp-full-stripe-free' )
			);
		}
	}

	function fullstripe_checkout_payment_charge() {
		try {
			$paymentFormModel = new CheckoutPaymentFormModel( $this->loggerService );
			$bindingResult = $paymentFormModel->bind();
			if ( ! $bindingResult->hasErrors() ) {
				$this->refuseArchivedCheckoutPrice( $paymentFormModel, $bindingResult );
			}

			if ( $bindingResult->hasErrors() ) {
				$return = Utils::generateReturnValueFromBindings( $bindingResult );
			} else {
				if ( Main::PAYMENT_TYPE_CARD_CAPTURE === $paymentFormModel->getForm()->customAmount ) {
					$this->fireBeforeCheckoutSaveCardAction( $paymentFormModel );
				} else {
					$transactionData = TransactionDataService::createOneTimePaymentDataByModel( $paymentFormModel );
					$this->fireBeforeCheckoutPaymentAction( $paymentFormModel, $transactionData );
				}

				$checkoutSession = $this->checkoutSubmissionService->createCheckoutSession( $paymentFormModel );
				$return = $this->generateReturnValueFromCheckoutSession( $checkoutSession );
			}
		} catch (UserFriendlyException $ex) {
			$this->logger->error( __FUNCTION__, "User-friendly exception while submitting checkout payment charge.", $ex );

			$messageTitle = is_null( $ex->getTitle() ) ?
				/* translators: Banner title of an error returned from an extension point by a developer */
				__( 'Internal Error', 'wp-full-stripe-free' ) :
				$ex->getTitle();
			$message = $ex->getMessage();
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\StripeWPFS\Stripe\Exception\CardException $ex) {
			$this->logger->error( __FUNCTION__, "Card exception while submitting checkout payment charge.", $ex );

			$messageTitle =
				/* translators: Banner title of error returned by Stripe */
				__( 'Stripe Error', 'wp-full-stripe-free' );
			$message = $this->stripe->resolveErrorMessageByCode( $ex->getCode() );
			if ( is_null( $message ) ) {
				$message = Localization::translateLabel( $ex->getMessage() );
			}
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, "Generic exception while submitting checkout payment charge.", $ex );

			$return = [
				'success' => false,
				'messageTitle' =>
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				'message' => Localization::translateLabel( $ex->getMessage() ),
				'exceptionMessage' => $ex->getMessage()
			];
		}

		header( "Content-Type: application/json" );
		echo json_encode( apply_filters( 'fullstripe_checkout_payment_charge_return_message', $return ) );
		exit;

	}

	/**
	 * @param DonationFormModel $donationFormModel
	 * @param DonationTransactionData $transactionData
	 */
	protected function fireBeforeCheckoutDonationAction( $donationFormModel, $transactionData ) {
		$params = [
			'urlParameters' => $donationFormModel->getFormGetParametersAsArray(),
			'formName' => $donationFormModel->getFormName(),
			'currency' => $transactionData->getCurrency(),
			'frequency' => $donationFormModel->getDonationFrequency(),
			'amount' => $donationFormModel->getAmount(),
			'stripeClient' => $this->stripe->getStripeClient()
		];

		do_action( Main::ACTION_NAME_BEFORE_CHECKOUT_DONATION_CHARGE, $params );
	}

	function fullstripe_checkout_donation_charge() {
		try {

			$donationFormModel = new CheckoutDonationFormModel( $this->loggerService );
			$bindingResult = $donationFormModel->bind();
			if ( $bindingResult->hasErrors() ) {
				$return = Utils::generateReturnValueFromBindings( $bindingResult );
			} else {
				$transactionData = TransactionDataService::createDonationDataByFormModel( $donationFormModel );
				$this->fireBeforeCheckoutDonationAction( $donationFormModel, $transactionData );

				$checkoutSession = $this->checkoutSubmissionService->createCheckoutSession( $donationFormModel );
				$return = $this->generateReturnValueFromCheckoutSession( $checkoutSession );
			}
		} catch (UserFriendlyException $ex) {
			$this->logger->error( __FUNCTION__, "User-friendly exception while submitting checkout donation charge.", $ex );

			$messageTitle = is_null( $ex->getTitle() ) ?
				/* translators: Banner title of an error returned from an extension point by a developer */
				__( 'Internal Error', 'wp-full-stripe-free' ) :
				$ex->getTitle();
			$message = $ex->getMessage();
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\StripeWPFS\Stripe\Exception\CardException $ex) {
			$this->logger->error( __FUNCTION__, "Card exception while submitting checkout donation charge.", $ex );

			$messageTitle =
				/* translators: Banner title of error returned by Stripe */
				__( 'Stripe Error', 'wp-full-stripe-free' );
			$message = $this->stripe->resolveErrorMessageByCode( $ex->getCode() );
			if ( is_null( $message ) ) {
				$message = Localization::translateLabel( $ex->getMessage() );
			}
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, "Generic exception while submitting checkout donation charge.", $ex );

			$return = [
				'success' => false,
				'messageTitle' =>
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				'message' => Localization::translateLabel( $ex->getMessage() ),
				'exceptionMessage' => $ex->getMessage()
			];
		}

		header( "Content-Type: application/json" );
		echo json_encode( apply_filters( 'fullstripe_checkout_donation_charge_return_message', $return ) );
		exit;

	}

	/**
	 * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession
	 *
	 * @return array
	 */
	private function generateReturnValueFromCheckoutSession( $checkoutSession ) {
		return [
			'success' => true,
			'checkoutSessionId' => $checkoutSession->id,
			'redirectUrl' => $checkoutSession->url
		];
	}

	/**
	 * @param SubscriptionFormModel $subscriptionFormModel
	 * @param SubscriptionTransactionData $transactionData
	 */
	protected function fireBeforeCheckoutSubscriptionAction( $subscriptionFormModel, $transactionData ) {
		$params = [
			'urlParameters' => $subscriptionFormModel->getFormGetParametersAsArray(),
			'formName' => $subscriptionFormModel->getFormName(),
			'productName' => $subscriptionFormModel->getProductName(),
			'planId' => $subscriptionFormModel->getStripePlanId(),
			'currency' => $transactionData->getPlanCurrency(),
			'amount' => $subscriptionFormModel->getPlanAmount(),
			'setupFee' => $subscriptionFormModel->getSetupFee(),
			'quantity' => $subscriptionFormModel->getStripePlanQuantity(),
			'stripeClient' => $this->stripe->getStripeClient(),
		];

		do_action( Main::ACTION_NAME_BEFORE_CHECKOUT_SUBSCRIPTION_CHARGE, $params );
	}

	function fullstripe_checkout_subscription_charge() {
		try {
			$subscriptionFormModel = new CheckoutSubscriptionFormModel( $this->loggerService );
			$bindingResult = $subscriptionFormModel->bind();

			if ( $bindingResult->hasErrors() ) {
				$return = Utils::generateReturnValueFromBindings( $bindingResult );
			} else {
				$this->fireBeforeCheckoutSubscriptionAction(
					$subscriptionFormModel,
					TransactionDataService::createSubscriptionDataByModel( $subscriptionFormModel )
				);

				$checkoutSession = $this->checkoutSubmissionService->createCheckoutSession( $subscriptionFormModel );
				$return = $this->generateReturnValueFromCheckoutSession( $checkoutSession );
			}
		} catch (UserFriendlyException $ex) {
			$this->logger->error( __FUNCTION__, "User-friendly exception while submitting checkout subscription charge.", $ex );

			$messageTitle = is_null( $ex->getTitle() ) ?
				/* translators: Banner title of an error returned from an extension point by a developer */
				__( 'Internal Error', 'wp-full-stripe-free' ) :
				$ex->getTitle();
			$message = $ex->getMessage();
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\StripeWPFS\Stripe\Exception\CardException $ex) {
			$this->logger->error( __FUNCTION__, "Card exception while submitting checkout subscription charge.", $ex );

			$messageTitle =
				/* translators: Banner title of error returned by Stripe */
				__( 'Stripe Error', 'wp-full-stripe-free' );
			$message = $this->stripe->resolveErrorMessageByCode( $ex->getCode() );
			if ( is_null( $message ) ) {
				$message = Localization::translateLabel( $ex->getMessage() );
			}
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, "Generic exception while submitting checkout subscription charge.", $ex );

			$return = [
				'success' => false,
				'messageTitle' =>
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				'message' => Localization::translateLabel( $ex->getMessage() ),
				'exceptionMessage' => $ex->getMessage()
			];
		}

		header( "Content-Type: application/json" );
		echo json_encode( apply_filters( 'fullstripe_checkout_subscription_charge_return_message', $return ) );
		exit;

	}

	function fullstripe_save_draft_transaction() {
		try {
			$paymentFormModel = new InlinePaymentFormModel( $this->loggerService );
			$bindingResult = $paymentFormModel->bind();

			if ( $bindingResult->hasErrors() ) {
				$return = Utils::generateReturnValueFromBindings( $bindingResult );
			} else {
				$return = $this->saveDraftTransaction( $paymentFormModel );
			}
		} catch (UserFriendlyException $ex) {
			$this->logger->error( __FUNCTION__, 'User-friendly exception while handling payment charge', $ex );

			$messageTitle = is_null( $ex->getTitle() ) ?
				/* translators: Banner title of an error returned from an extension point by a developer */
				__( 'Internal Error', 'wp-full-stripe-free' ) :
				$ex->getTitle();
			$message = $ex->getMessage();
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\StripeWPFS\Stripe\Exception\CardException $ex) {
			$this->logger->error( __FUNCTION__, 'Stripe card exception while handling payment charge', $ex );

			$messageTitle =
				/* translators: Banner title of error returned by Stripe */
				__( 'Stripe Error', 'wp-full-stripe-free' );
			$message = $this->stripe->resolveErrorMessageByCode( $ex->getCode() );
			if ( is_null( $message ) ) {
				$message = Localization::translateLabel( $ex->getMessage() );
			}
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Generic exception while handling payment charge', $ex );

			$return = [
				'success' => false,
				'messageTitle' =>
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				'message' => Localization::translateLabel( $ex->getMessage() ),
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\Error $err) {
			$this->logger->error( __FUNCTION__, 'Generic error while handling payment charge', $err );

			$return = [
				'success' => false,
				'messageTitle' =>
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				'message' => Localization::translateLabel( $err->getMessage() ),
				'exceptionMessage' => $err->getMessage()
			];
		}

		header( "Content-Type: application/json" );
		echo json_encode( apply_filters( 'fullstripe_payment_charge_return_message', $return ) );
		exit;
	}

	/**
	 * @throws \Exception
	 */
	function saveDraftTransaction( $paymentFormModel ) {
		// Verify that the PaymentIntent about to be confirmed belongs to this form transaction before
		// a draft is recorded for it.
		$paymentIntent = $this->getTransactionBoundPaymentIntent(
			$paymentFormModel->getStripePaymentIntentId(),
			$this->readSanitizedPostValue( $_POST, self::PARAM_WPFS_STRIPE_CLIENT_SECRET ),
			Utils::getFormType( $paymentFormModel->getForm() ),
			$paymentFormModel->getForm(),
			$paymentFormModel->getPriceId()
		);

		// A draft prices the PaymentIntent before it is confirmed. Saved again once Stripe charged it, it would reset the
		// payment to a pending one.
		if ( empty( $paymentIntent ) || in_array( $paymentIntent->status, self::CHARGED_PAYMENT_INTENT_STATUSES, true ) ) {
			return [
				'success' => false,
				'messageTitle' =>
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				'message' => __( 'Invalid request', 'wp-full-stripe-free' ),
			];
		}

		$paymentFormModel->setStripePaymentIntent( $paymentIntent );
		$hadChargeBasis = $this->discardChargeBasis( $paymentIntent );
		$stampedTermsOfUse = $this->stampTermsOfUseAcceptance( $paymentIntent, $paymentFormModel );
		$this->stampTaxLocation( $paymentIntent, $paymentFormModel );

		$transactionData = TransactionDataService::createOneTimePaymentDataByModel( $paymentFormModel );

		// save the draft transaction
		$latest_charge = new \stdClass();
		$latest_charge->paid = false;
		$latest_charge->captured = false;
		$latest_charge->refunded = false;
		$latest_charge->failure_code = null;
		$latest_charge->failure_message = null;
		$latest_charge->status = "pending";

		// Recompute the amount (tax-inclusive total + recovery fee) before the payment is
		// confirmed client-side in the Payment Element flow, otherwise the customer is charged
		// the pre-tax amount even though the form preview shows tax (#413). Returns null for
		// plain forms, where the up-front amount already matches and we leave it untouched.
		$resolved = $this->computeInlinePaymentChargeAmount( $paymentFormModel, $transactionData );

		// Ensure the PaymentIntent amount matches the submitted product price.
		if ( is_null( $resolved ) ) {
			$expectedAmount = (int) round( $paymentFormModel->getAmount() );
			$carriedAmount = (int) $paymentIntent->amount;

			if ( $expectedAmount > 0 && $carriedAmount !== $expectedAmount ) {
				$this->logger->error(
					__FUNCTION__,
					sprintf(
						'PaymentIntent %1$s carries %2$d but the product submitted on form %3$s costs %4$d, refusing the draft transaction.',
						$paymentIntent->id,
						$carriedAmount,
						$paymentFormModel->getFormName(),
						$expectedAmount
					)
				);

				return [
					'success' => false,
					'messageTitle' =>
						/* translators: Banner title of internal error */
						__( 'Internal Error', 'wp-full-stripe-free' ),
					'message' => __( 'Invalid request', 'wp-full-stripe-free' ),
				];
			}

			// Stripe keeps metadata keys an update leaves out, so overwrite the stale basis. Fee
			// recovery forms always record theirs, so completion never recomputes a declined fee.
			$stampChargeBasis = $hadChargeBasis || Utils::hasFeeRecovery( $paymentFormModel->getForm() );
			if ( $stampChargeBasis ) {
				$this->stampChargeBasis( $paymentIntent, $expectedAmount, $paymentFormModel, 0 );
			}
			if ( $stampChargeBasis || $stampedTermsOfUse ) {
				$this->stripe->updatePaymentIntent( $paymentIntent );
			}
		} else {
			list( $chargeAmount, $baseAmount, $recoveryFeeAmount ) = $resolved;

			// Stamp the charge basis even when the amount was already set elsewhere (e.g. the coupon
			// flow), so a later recompute (e.g. on redirect confirmation) never taxes or adds the fee
			// to an already tax- and fee-inclusive amount (#413, #704).
			$this->stampChargeBasis( $paymentIntent, $baseAmount, $paymentFormModel, $recoveryFeeAmount );
			if ( $chargeAmount > 0 ) {
				$paymentIntent->amount = $chargeAmount;
			}
			$this->stripe->updatePaymentIntent( $paymentIntent, true );
		}

		$this->db->insertOrUpdatePayment( $paymentFormModel, $transactionData, $latest_charge );
		// just return if everything is fine
		return [
			'success' => true
		];
	}

	function fullstripe_confirm_redirect() {
		try {
			$paymentFormModel = new InlinePaymentFormModel( $this->loggerService );
			$bindingResult = $this->bindChargedPayment( $paymentFormModel );

			if ( $bindingResult->hasErrors() ) {
				$return = Utils::generateReturnValueFromBindings( $bindingResult );
			} else {
				// replace bindings with data from draft payment
				$draft_payment = $this->db->getPaymentByEventId( $paymentFormModel->getStripePaymentIntentId() );
				// The reloaded page posts its default product, the draft holds the one that was priced (#728).
				if ( is_null( $draft_payment ) || ! $paymentFormModel->selectProductByPriceId( $draft_payment->priceId ) ) {
					$this->refundChargeOfRemovedDraftProduct( $paymentFormModel );
					$this->refuseRedirectConfirmation(
						sprintf(
							'PaymentIntent %1$s has no draft payment for a product of form %2$s, refusing the confirmation.',
							$paymentFormModel->getStripePaymentIntentId(),
							$paymentFormModel->getFormName()
						)
					);
				}

				// get payment method type
				$paymentIntent = $this->stripe->retrievePaymentIntent( $paymentFormModel->getStripePaymentIntentId() );
				$paymentFormModel->setStripePaymentIntent( $paymentIntent );
				$this->restoreFeeRecoveryAccepted( $paymentFormModel, $paymentIntent );
				if ( isset( $paymentIntent->last_charge ) && isset( $paymentIntent->last_charge->payment_method_details ) ) {
					$payment_type = $paymentIntent->last_charge->payment_method_details->type;
					$paymentFormModel->setStripePaymentMethodType( $payment_type );
				}
				// get amount
				$paymentFormModel->setAmount( $paymentIntent->amount );
				// billling and shipping info
				$paymentFormModel->setBillingName( $draft_payment->billingName );
				$billing_address = new \stdClass();
				$billing_address->line1 = $draft_payment->addressLine1;
				$billing_address->line2 = $draft_payment->addressLine2;
				$billing_address->city = $draft_payment->addressCity;
				$billing_address->state = $draft_payment->addressState;
				$billing_address->postal_code = $draft_payment->addressZip;
				// The draft stores the country's name and code, and a Stripe address takes the code.
				$billing_address->country = $draft_payment->addressCountryCode;
				$paymentFormModel->updateBillingAddressByStripeAddressHash( $billing_address );

				$paymentFormModel->setShippingName( $draft_payment->shippingName );
				$shipping_address = new \stdClass();
				$shipping_address->line1 = $draft_payment->shippingAddressLine1;
				$shipping_address->line2 = $draft_payment->shippingAddressLine2;
				$shipping_address->city = $draft_payment->shippingAddressCity;
				$shipping_address->state = $draft_payment->shippingAddressState;
				$shipping_address->postal_code = $draft_payment->shippingAddressZip;
				$shipping_address->country = $draft_payment->shippingAddressCountryCode;
				$paymentFormModel->updateShippingAddressByStripeAddressHash( $shipping_address );

				// The coupon the draft was priced with, discount included, so a taxed total is previewed discounted.
				$paymentFormModel->restoreCouponCode( $draft_payment->coupon );

				// card holder name
				$paymentFormModel->setCardHolderName( $draft_payment->name );
				// card holdeer email
				$paymentFormModel->setCardHolderEmail( $draft_payment->email );
				$paymentFormModel->setBusinessName( $draft_payment->businessName );
				$paymentFormModel->setTaxIdType( $draft_payment->taxIdType );
				$paymentFormModel->setTaxId( $draft_payment->taxId );

				$result = $this->processPaymentIntentCharge( $paymentFormModel, (int) $draft_payment->amount );
				$return = $result->getAsArray();
			}
		} catch (UserFriendlyException $ex) {
			$this->logger->error( __FUNCTION__, 'User-friendly exception while handling payment charge', $ex );

			$messageTitle = is_null( $ex->getTitle() ) ?
				/* translators: Banner title of an error returned from an extension point by a developer */
				__( 'Internal Error', 'wp-full-stripe-free' ) :
				$ex->getTitle();
			$message = $ex->getMessage();
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\StripeWPFS\Stripe\Exception\CardException $ex) {
			$this->logger->error( __FUNCTION__, 'Stripe card exception while handling payment charge', $ex );

			$messageTitle =
				/* translators: Banner title of error returned by Stripe */
				__( 'Stripe Error', 'wp-full-stripe-free' );
			$message = $this->stripe->resolveErrorMessageByCode( $ex->getCode() );
			if ( is_null( $message ) ) {
				$message = Localization::translateLabel( $ex->getMessage() );
			}
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Generic exception while handling payment charge', $ex );

			$return = [
				'success' => false,
				'messageTitle' =>
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				'message' => Localization::translateLabel( $ex->getMessage() ),
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\Error $err) {
			$this->logger->error( __FUNCTION__, 'Generic error while handling payment charge', $err );

			$return = [
				'success' => false,
				'messageTitle' =>
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				'message' => Localization::translateLabel( $err->getMessage() ),
				'exceptionMessage' => $err->getMessage()
			];
		}

		header( "Content-Type: application/json" );
		echo json_encode( apply_filters( 'fullstripe_payment_charge_return_message', $return ) );
		exit;
	}

	/**
	 * Verify the form nonce sent with public coupon/pricing AJAX requests.
	 * Halts with a 403 JSON response when the nonce is missing or invalid.
	 *
	 * @return void
	 */
	private function verifyFormNonce() {
		if (
			! isset( $_POST['nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), Main::NONCE_ACTION_UPDATE_FAILED_PAYMENT_STATUS )
		) {
			wp_send_json_error( [ 'message' => __( 'Invalid request', 'wp-full-stripe-free' ) ], 403 );
		}
	}

	/**
	 * Read a scalar value from a request array, unslashed and sanitized as text.
	 *
	 * @param array<string,mixed> $source The source array (e.g. $_POST or a nested array of it).
	 * @param string $key The key to read.
	 * @param string|null $default Value returned when the key is missing or not a scalar.
	 * @return string|null
	 */
	private function readSanitizedPostValue( $source, $key, $default = null ) {
		if ( ! isset( $source[ $key ] ) || ! is_scalar( $source[ $key ] ) ) {
			return $default;
		}

		return sanitize_text_field( wp_unslash( (string) $source[ $key ] ) );
	}

	/**
	 * Reads the address and tax id the page prices the form at, the same for every pricing request, so a coupon is
	 * priced at the address the price is shown for (#779).
	 *
	 * @param array<string,mixed> $source The posted pricing fields.
	 * @param \stdClass $pricingData Takes the country, state, zip, city, line1, line2, taxIdType and taxId.
	 * @return void
	 */
	private function readPricingTaxDetails( array $source, $pricingData ): void {
		foreach ( [ 'country', 'state', 'zip', 'city', 'line1', 'line2', 'taxIdType', 'taxId' ] as $field ) {
			$pricingData->$field = $this->readSanitizedPostValue( $source, $field );
		}
	}

	/**
	 * @throws \Exception
	 */
	function fullstripe_check_coupon() {
		$this->verifyFormNonce();

		$return = [];
		$taxData = isset( $_POST['taxData'] ) && is_array( $_POST['taxData'] ) ? $_POST['taxData'] : [];
		$couponCode = $this->readSanitizedPostValue( $_POST, 'code', '' );

		$formType = $this->readSanitizedPostValue( $taxData, 'formType' );
		$formId = $this->readSanitizedPostValue( $taxData, 'formId' );
		$form = Main::getInstance()->getFormByTypeAndName( $formType, $formId );
		$formHash = Utils::generateFormHash( $formType, Utils::getFormId( $form ), $form->name );
		$bindingResult = new BindingResult( $formHash );

		$fieldName = FormView::FIELD_COUPON;
		$fieldId = Utils::generateFormElementId( $fieldName, $formHash );

		if ( empty( $couponCode ) ) {
			$bindingResult->addFieldError(
				$fieldName,
				$fieldId,
				/* translators: Banner message of expired coupon */
				__( 'Please enter a coupon code', 'wp-full-stripe-free' )
			);

			$return = Utils::generateReturnValueFromBindings( $bindingResult );
		} else {
			$coupon = $this->stripe->retrieveCouponByPromotionalCodeOrCouponCode( $couponCode );

			if ( is_null( $coupon ) || false == $coupon->valid ) {
				$bindingResult->addFieldError(
					$fieldName,
					$fieldId,
					/* translators: Banner message of expired coupon */
					__( 'This coupon has expired', 'wp-full-stripe-free' )
				);

				$return = Utils::generateReturnValueFromBindings( $bindingResult );
			} else {
				$result = Main::getInstance()->isCouponApplicableToForm(
					$coupon,
					$formType,
					$formId,
					$this->readSanitizedPostValue( $taxData, 'currentPriceId' )
				);

				if ( ! $result->applicableToForm ) {
					$bindingResult->addFieldError(
						$fieldName,
						$fieldId,
						/* translators: Banner message of a coupon that cannot be applied to the products of the form */
						__( 'This coupon cannot be applied to these products', 'wp-full-stripe-free' )
					);

					$return = Utils::generateReturnValueFromBindings( $bindingResult );
				} else if ( ! $result->applicableToProduct ) {
					$bindingResult->addFieldError(
						$fieldName,
						$fieldId,
						/* translators: Banner message of a coupon that cannot be applied to the products of the form */
						__( 'This coupon cannot be applied to the selected product', 'wp-full-stripe-free' )
					);

					$return = Utils::generateReturnValueFromBindings( $bindingResult );
				} else {
					$customAmount = $this->readSanitizedPostValue( $taxData, 'customAmount' );
					$pricingData = new \StdClass;
					$pricingData->formType = $formType;
					$pricingData->formId = $formId;
					$this->readPricingTaxDetails( $taxData, $pricingData );
					$pricingData->stripePaymentIntentId = $this->readSanitizedPostValue( $taxData, 'stripePaymentIntentId' );
					$stripeClientSecret = $this->readSanitizedPostValue( $taxData, 'stripeClientSecret' );

					$verifiedPaymentIntent = null;
					if ( ! empty( $pricingData->stripePaymentIntentId ) ) {
						$verifiedPaymentIntent = $this->getTransactionBoundPaymentIntent(
							$pricingData->stripePaymentIntentId,
							$stripeClientSecret,
							$formType,
							$form,
							$this->readSanitizedPostValue( $taxData, 'currentPriceId' )
						);
						if ( empty( $verifiedPaymentIntent ) ) {
							wp_send_json_error( [ 'message' => __( 'Invalid request', 'wp-full-stripe-free' ) ], 403 );
						}
					}

					$pricingData->couponCode = $coupon->id;
					$pricingData->coupon = $coupon;
					$pricingData->feeRecoveryAccepted = '1' === $this->readSanitizedPostValue( $taxData, 'feeRecoveryAccepted' );
					$pricingData->customAmount = ! empty( $customAmount ) ? $customAmount : null;
					// A missing quantity is one, as at submission.
					$pricingData->quantity = $this->readSanitizedPostValue( $taxData, 'quantity', '1' );
					$pricingData->priceId = $this->readSanitizedPostValue( $taxData, 'currentPriceId' );
					$pricingData->stripeTax = ( $formType === Main::FORM_TYPE_INLINE_PAYMENT || $formType == Main::FORM_TYPE_INLINE_SUBSCRIPTION ) &&
						$form->vatRateType === Main::FIELD_VALUE_TAX_RATE_STRIPE_TAX;

					$productPricing = null;
					// The PaymentIntent is repriced below.
					$this->validateRepricing( $bindingResult, $verifiedPaymentIntent, $form, $customAmount, $pricingData->priceId, $pricingData->quantity );

					try {
						if ( ! $bindingResult->hasErrors() ) {
							$productPricing = Pricing::createFormPriceCalculator( $pricingData, $this->loggerService )->getProductPrices();
							$discountedPriceIds = Main::getInstance()->getDiscountedPriceIdsByCouponAndForm( $coupon, $formType, $formId );
						}
					} catch (InvalidTaxIdException $tax) {
						$fieldName = FormViewInlineTaxAddOnConstants::FIELD_TAX_ID;
						$fieldId = Utils::generateFormElementId( $fieldName, $formHash );
						$error =
							__( 'Invalid tax id', 'wp-full-stripe-free' );

						$bindingResult->addFieldError( $fieldName, $fieldId, $error );
					} catch (\Exception $ex) {
						$this->logger->error( __FUNCTION__, "Cannot apply coupon", $ex );
						$bindingResult->addGlobalError( $ex->getMessage() );
					}

					// A form without products has nothing the coupon could discount.
					if ( empty( $productPricing ) && ! $bindingResult->hasErrors() ) {
						$bindingResult->addFieldError(
							$fieldName,
							$fieldId,
							/* translators: Banner message of a coupon that cannot be applied to the products of the form */
							__( 'This coupon cannot be applied to these products', 'wp-full-stripe-free' )
						);
					}

					if ( ! empty( $productPricing ) && ! $bindingResult->hasErrors() ) {
						// for payment intent scenarios we need to update the payment intent 
						// with an updated amount as they don't support coupons
						if ( ! empty( $verifiedPaymentIntent ) ) {
							try {
								$this->updatePaymentIntentAmount( $verifiedPaymentIntent, $productPricing, $pricingData->priceId );
							} catch ( \Exception $ex ) {
								$bindingResult->addGlobalError( $ex->getMessage() );
							}
						}
					}

					if ( ! empty( $productPricing ) && ! $bindingResult->hasErrors() ) {
						$return = [
							'msg_title' =>
								/* translators: Banner title for messages related to applying a coupon */
								__( 'Coupon redemption', 'wp-full-stripe-free' ),
							'msg' =>
								/* translators: Banner message of successfully applying a coupon */
								__( 'The coupon has been applied successfully', 'wp-full-stripe-free' ),
							'coupon' => [
								'id' => $coupon->id,
								'name' => $couponCode,
								'currency' => $coupon->currency,
								'percent_off' => $coupon->percent_off,
								'amount_off' => $coupon->amount_off,
								'discounted_price_ids' => $discountedPriceIds
							],
							'success' => true,
							'productPricing' => $productPricing
						];
					} else {
						$return = Utils::generateReturnValueFromBindings( $bindingResult );
					}
				}
			}
		}

		header( "Content-Type: application/json" );
		echo json_encode( $return );
		exit;
	}

	/**
	 * Retrieve a client-supplied PaymentIntent and verify that it belongs to the current form
	 * transaction.
	 *
	 * @param string      $paymentIntentId PaymentIntent id.
	 * @param string|null $clientSecret client_secret for that PaymentIntent.
	 * @param string|null $formType Form type.
	 * @param object|null $form The current form.
	 * @param string|null $selectedPriceId Selected price id.
	 * @return \StripeWPFS\Stripe\PaymentIntent|null The bound PaymentIntent, or null when validation fails.
	 */
	protected function getTransactionBoundPaymentIntent( $paymentIntentId, $clientSecret, $formType, $form, $selectedPriceId = null ) {
		if ( empty( $paymentIntentId ) || empty( $clientSecret ) || empty( $formType ) || empty( $form ) ) {
			$this->logger->error(
				__FUNCTION__,
				'PaymentIntent rejected: missing PaymentIntent id, client secret or form context'
			);

			return null;
		}

		try {
			$paymentIntent = $this->stripe->retrievePaymentIntent( $paymentIntentId );
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Cannot retrieve PaymentIntent to validate its binding', $ex );

			return null;
		}

		$formName = isset( $form->name ) ? $form->name : null;
		$products = $this->getProductsOfForm( $formType, $formName );

		$context = new PaymentIntentBindingContext();
		$context->formType = $formType;
		$context->formName = $formName;
		$context->formId = Utils::getFormId( $form );
		// Subscription form records keep no currency of their own, it comes with their plans.
		$context->currency = ! empty( $form->currency ) ?
			$form->currency :
			PaymentIntentBinding::extractCurrencyFromProducts( $products );
		$context->selectedPriceId = $selectedPriceId;
		$context->allowsCustomAmount = PaymentIntentBinding::allowsCustomAmount( $form );
		$context->allowedPriceIds = empty( $products ) ?
			null :
			Pricing::extractPriceIdsFromProductsStatic( $products );

		$result = PaymentIntentBinding::validate( $paymentIntent, $clientSecret, $context );

		if ( ! $result->isValid() ) {
			$this->logger->error(
				__FUNCTION__,
				sprintf(
					'PaymentIntent %1$s rejected for form %2$s/%3$s, reason=%4$s',
					$paymentIntentId,
					$formType,
					$context->formName,
					$result->getReasonCode()
				)
			);

			return null;
		}

		return $paymentIntent;
	}

	/**
	 * Whether a PaymentIntent retrieved from Stripe carries the binding of the given form.
	 *
	 * @param object|null $paymentIntent PaymentIntent as retrieved from Stripe.
	 * @param string|null $formType Form type of the submitted form.
	 * @param object|null $form The submitted form record.
	 * @return bool
	 */
	protected function isPaymentIntentBoundToForm( $paymentIntent, $formType, $form ) {
		$context = new PaymentIntentBindingContext();
		$context->formType = $formType;
		$context->formName = isset( $form->name ) ? $form->name : null;
		$context->formId = Utils::getFormId( $form );

		$result = PaymentIntentBinding::matchesForm( $paymentIntent, $context );

		if ( ! $result->isValid() ) {
			$this->logger->error(
				__FUNCTION__,
				sprintf(
					'PaymentIntent %1$s rejected for form %2$s/%3$s, reason=%4$s',
					isset( $paymentIntent->id ) ? $paymentIntent->id : '(none)',
					$formType,
					$context->formName,
					$result->getReasonCode()
				)
			);

			return false;
		}

		return true;
	}

	/**
	 * Retrieve a PaymentIntent and verify only that the client knows its client_secret.
	 *
	 * @param string      $paymentIntentId The client-supplied PaymentIntent id.
	 * @param string|null $clientSecret The client-supplied client_secret for that PaymentIntent.
	 * @return \StripeWPFS\Stripe\PaymentIntent|null The verified PaymentIntent, or null if verification fails.
	 */
	private function getClientSecretVerifiedPaymentIntent( $paymentIntentId, $clientSecret ) {
		if ( empty( $paymentIntentId ) || empty( $clientSecret ) ) {
			return null;
		}

		try {
			$paymentIntent = $this->stripe->retrievePaymentIntent( $paymentIntentId );
		} catch (\Exception $ex) {
			return null;
		}

		if ( empty( $paymentIntent ) || empty( $paymentIntent->client_secret ) ||
			! hash_equals( $paymentIntent->client_secret, $clientSecret ) ) {
			return null;
		}

		return $paymentIntent;
	}

	/**
	 * The submitted PaymentIntent, only if the client knows its client_secret and it is bound to the donation form.
	 *
	 * @param InlineDonationFormModel $donationFormModel The donation form model.
	 * @return \StripeWPFS\Stripe\PaymentIntent|null
	 */
	private function getVerifiedDonationPaymentIntent( InlineDonationFormModel $donationFormModel ) {
		$paymentIntent = $this->getClientSecretVerifiedPaymentIntent(
			$donationFormModel->getStripePaymentIntentId(),
			$donationFormModel->getStripePaymentIntentClientSecret()
		);

		if ( is_null( $paymentIntent ) || ! $this->isPaymentIntentBoundToForm( $paymentIntent, Main::FORM_TYPE_INLINE_DONATION, $donationFormModel->getForm() ) ) {
			return null;
		}

		return $paymentIntent;
	}

	/**
	 * The products configured on a form, read from the form record: decoratedProducts for payment
	 * forms, decoratedPlans for subscription forms. Custom-amount-only forms have none.
	 *
	 * @param string $formType
	 * @param string|null $formName
	 * @return array<int,object> Products of the form, empty when they cannot be determined.
	 */
	private function getProductsOfForm( $formType, $formName ) {
		if ( empty( $formName ) ) {
			return [];
		}

		try {
			return Main::getInstance()->getProductsByFormTypeAndId( $formType, $formName );
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Cannot read the products of the form', $ex );

			return [];
		}
	}

	/**
	 * @param \StripeWPFS\Stripe\PaymentIntent $paymentIntent The PaymentIntent to update.
	 * @param array<string,mixed> $productPricing The current product pricing data.
	 * @param string|null $selectedPriceId The ID of the selected price, if any.
	 */
	private function updatePaymentIntentAmount( $paymentIntent, $productPricing, $selectedPriceId ) {
		// The amount always comes from the server-calculated pricing data, never from the browser.
		$new_amount = PaymentIntentBinding::calculatePayableAmount( $productPricing, $selectedPriceId );

		// find the selected price and then update the payment intent
		if ( $new_amount > 0 ) {
			$paymentIntent->amount = $new_amount;
			$this->stripe->updatePaymentIntent( $paymentIntent, true );
		}
	}

	/**
	 * handling recalculating pricing and udating payment intent
	 * this is used because the "calculatePricing" causes UI updates on the client side
	 * 
	 * @throws \Exception
	 */
	public function updatePaymentIntent() {
		$this->verifyFormNonce();

		// TODO: get full billing address if available and update the payment intent
		// this will help with calculating taxes more accurately
		try {
			$return = $this->reCalculatePricing();
		} catch (\Exception $e) {
			$return = [
				'success' => false,
				'msg' => sprintf(
					/* translators: %s: error message */
					__( 'There was an error updating the payment intent: %s', 'wp-full-stripe-free' ),
					$e->getMessage()
				)
			];
		}

		header( "Content-Type: application/json" );
		echo json_encode( $return );
		exit;
	}

	private function reCalculatePricing() {
		$couponId = null;
		$coupon = null;
		$return = [
			'success' => false
		];

		$stripePaymentIntentId = $this->readSanitizedPostValue( $_POST, 'stripePaymentIntentId' );
		$stripeClientSecret = $this->readSanitizedPostValue( $_POST, 'stripeClientSecret' );

		$formType = $this->readSanitizedPostValue( $_POST, 'formType' );
		$formId = $this->readSanitizedPostValue( $_POST, 'formId' );

		$form = Main::getInstance()->getFormByTypeAndName( $formType, $formId );

		if ( empty( $form ) ) {
			$this->logger->error( __FUNCTION__, sprintf( 'Unknown form %1$s/%2$s', $formType, $formId ) );
			wp_send_json_error( [ 'message' => __( 'Invalid request', 'wp-full-stripe-free' ) ], 403 );
		}

		$customAmount = $this->readSanitizedPostValue( $_POST, 'customAmount' );
		$stripePriceId = $this->readSanitizedPostValue( $_POST, 'stripePriceId' );

		$selectedPriceId = ! empty( $stripePriceId ) ?
			$stripePriceId :
			$this->readSanitizedPostValue( $_POST, 'currentPriceId' );

		$verifiedPaymentIntent = null;
		if ( ! empty( $stripePaymentIntentId ) ) {
			$verifiedPaymentIntent = $this->getTransactionBoundPaymentIntent(
				$stripePaymentIntentId,
				$stripeClientSecret,
				$formType,
				$form,
				$selectedPriceId
			);
			if ( empty( $verifiedPaymentIntent ) ) {
				wp_send_json_error( [ 'message' => __( 'Invalid request', 'wp-full-stripe-free' ) ], 403 );
			}
		}

		$couponInput = $this->readSanitizedPostValue( $_POST, 'coupon', '' );
		if ( ! empty( $couponInput ) ) {
			$coupon = $this->stripe->retrieveCouponByPromotionalCodeOrCouponCode( $couponInput );
			$couponId = ! is_null( $coupon ) ? $coupon->id : null;
		}

		$pricingData = new \StdClass;
		$pricingData->formType = $formType;
		$pricingData->formId = $formId;
		$this->readPricingTaxDetails( $_POST, $pricingData );
		$pricingData->couponCode = $couponId;
		$pricingData->coupon = $coupon;
		$pricingData->feeRecoveryAccepted = '1' === $this->readSanitizedPostValue( $_POST, 'feeRecoveryAccepted' );
		$pricingData->customAmount = ! empty( $customAmount ) ? $customAmount : null;
		// A missing quantity is one, as at submission.
		$pricingData->quantity = $this->readSanitizedPostValue( $_POST, 'quantity', '1' );
		$pricingData->stripePriceId = ! empty( $stripePriceId ) ? $stripePriceId : null;
		$pricingData->stripeTax = ( $formType === Main::FORM_TYPE_INLINE_PAYMENT || $formType == Main::FORM_TYPE_INLINE_SUBSCRIPTION ) &&
			$form->vatRateType === Main::FIELD_VALUE_TAX_RATE_STRIPE_TAX;

		$formHash = Utils::generateFormHash( $formType, Utils::getFormId( $form ), $form->name );
		$bindingResult = new BindingResult( $formHash );

		if ( $this->validateRepricing( $bindingResult, $verifiedPaymentIntent, $form, $customAmount, $selectedPriceId, $pricingData->quantity ) ) {
			return Utils::generateReturnValueFromBindings( $bindingResult );
		}

		try {
			$pricing = Pricing::createFormPriceCalculator( $pricingData, $this->loggerService )->getProductPrices();
		} catch (InvalidTaxIdException $tax) {
			$fieldName = FormViewInlineTaxAddOnConstants::FIELD_TAX_ID;
			$fieldId = Utils::generateFormElementId( $fieldName, $formHash );
			$error =
				__( 'Invalid tax id', 'wp-full-stripe-free' );

			$bindingResult->addFieldError( $fieldName, $fieldId, $error );
		} catch (\Exception $ex) {
			$bindingResult->addGlobalError( $ex->getMessage() );
		}

		if ( ! empty( $pricing ) && ! $bindingResult->hasErrors() ) {
			if ( ! empty( $verifiedPaymentIntent ) ) {
				$this->updatePaymentIntentAmount( $verifiedPaymentIntent, $pricing, $pricingData->stripePriceId );
			}
			$return = [
				'success' => true,
				'productPricing' => $pricing
			];
		} else {
			$return = Utils::generateReturnValueFromBindings( $bindingResult );
		}
		return $return;
	}

	/**
	 * A PaymentIntent is only repriced to an amount and quantity the form would accept at submission.
	 *
	 * @param BindingResult $bindingResult Receives the field errors.
	 * @param \StripeWPFS\Stripe\PaymentIntent|null $paymentIntent The PaymentIntent to reprice, if any.
	 * @param object $form
	 * @param string|null $customAmount As posted, in minor units.
	 * @param string|null $selectedPriceId
	 * @param string $quantity As posted, or "1" when missing.
	 * @return bool Whether an error was added.
	 */
	private function validateRepricing( $bindingResult, $paymentIntent, $form, $customAmount, $selectedPriceId, $quantity ) {
		if ( empty( $paymentIntent ) ) {
			return false;
		}

		$errors = [];
		// With "Other" selected a missing or zero amount is refused, not dropped in favour of the products.
		if ( Main::PRICE_ID_CUSTOM_AMOUNT === $selectedPriceId || ! empty( $customAmount ) ) {
			$errors[ PaymentFormModel::PARAM_WPFS_CUSTOM_AMOUNT_UNIQUE ] = $this->getRepricingCustomAmountError( $form, (string) $customAmount );
		}
		if ( Main::FORM_TYPE_INLINE_SUBSCRIPTION === Utils::getFormType( $form ) || Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION === Utils::getFormType( $form ) ) {
			$errors[ SubscriptionFormModel::PARAM_WPFS_STRIPE_PLAN_QUANTITY ] = SubscriptionFormValidator::getQuantityError( $form, $quantity );
		}

		$errors = array_filter( $errors );
		foreach ( $errors as $fieldName => $error ) {
			$bindingResult->addFieldError( $fieldName, Utils::generateFormElementId( $fieldName, $bindingResult->getFormHash() ), $error );
		}

		return ! empty( $errors );
	}

	/**
	 * Why a custom amount, in minor units, may not be charged on the form, or null when it may.
	 *
	 * @param object $form
	 * @param string $customAmount
	 * @return string|null
	 */
	private function getRepricingCustomAmountError( $form, $customAmount ) {
		if ( ! PaymentIntentBinding::allowsCustomAmount( $form ) || ! ctype_digit( (string) $customAmount ) || (int) $customAmount <= 0 ) {
			return sprintf(
				/* translators: Form field validation error message when the custom amount is malformed. p1: example amount, e.g. 12,34 */
				__( 'Please enter a valid amount, for example %s', 'wp-full-stripe-free' ),
				Currencies::formatExampleAmountByForm( $form, $form->currency )
			);
		}

		$minimumAmount = isset( $form->minimumPaymentAmount ) ? (int) $form->minimumPaymentAmount : 0;
		if ( (int) $customAmount < $minimumAmount ) {
			return sprintf(
				/* translators: Form field validation error message when custom amount is less than the minimum payment amount */
				__( 'The minimum payment amount is %s', 'wp-full-stripe-free' ),
				Currencies::formatByForm( $form, $form->currency, $minimumAmount, false, true )
			);
		}

		return null;
	}

	public function calculatePricing() {
		$this->verifyFormNonce();

		$return = null;
		try {
			$return = $this->reCalculatePricing();
		} catch (\Exception $e) {
			$return = [
				'success' => false,
				'msg' => sprintf(
					/* translators: %s: error message */
					__( 'There was an error calculating product pricing: %s', 'wp-full-stripe-free' ),
					$e->getMessage()
				)
			];
		}

		header( "Content-Type: application/json" );
		echo json_encode( $return );
		exit;
	}

	/**
	 * Get form model by form type.
	 *
	 * @param string $form_type The form type.
	 * @return InlineDonationFormModel|InlinePaymentFormModel
	 */
	private function getFormModel( $form_type ) {
		if ( Main::FORM_TYPE_INLINE_DONATION === $form_type ) {
			return new InlineDonationFormModel( $this->loggerService );
		}

		return new InlinePaymentFormModel( $this->loggerService );
	}

	/**
	 * Handling onetime donation save.
	 *
	 * @return void
	 */
	public function fullstripe_save_onetime_donation() {
		try {
			$paymentFormModel = $this->getFormModel( Main::FORM_TYPE_INLINE_DONATION );
			$bindingResult = $paymentFormModel->bind();

			if ( $bindingResult->hasErrors() ) {
				$return = Utils::generateReturnValueFromBindings( $bindingResult );
			} else {
				$return = $this->saveOnetimeDonation( $paymentFormModel );
			}
		} catch (UserFriendlyException $ex) {
			$this->logger->error( __FUNCTION__, 'User-friendly exception while handling onetime donation save', $ex );

			$messageTitle = is_null( $ex->getTitle() ) ?
				/* translators: Banner title of an error returned from an extension point by a developer */
				__( 'Internal Error', 'wp-full-stripe-free' ) :
				$ex->getTitle();
			$message = $ex->getMessage();
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Generic exception while handling onetime donation save', $ex );

			$return = [
				'success' => false,
				'messageTitle' =>
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				'message' => Localization::translateLabel( $ex->getMessage() ),
				'exceptionMessage' => $ex->getMessage()
			];
		}

		header( "Content-Type: application/json" );
		echo json_encode( apply_filters( 'fullstripe_save_onetime_donation_return_message', $return ) );
		exit;
	}

	/**
	 * The amount a one-time donation charges, recovery fee included, in the smallest currency unit.
	 *
	 * @param DonationFormModel $donationFormModel
	 * @return int
	 */
	private function calculateDonationChargeAmount( $donationFormModel ) {
		return (int) $donationFormModel->getAmount() + $this->calculateDonationRecoveryFee( $donationFormModel );
	}

	/**
	 * The recovery fee a one-time donation adds under the form's current settings, in the smallest currency unit.
	 *
	 * @param DonationFormModel $donationFormModel
	 * @return int
	 */
	private function calculateDonationRecoveryFee( $donationFormModel ) {
		$recoveryFeeData = Utils::getFeeRecoveryData( $donationFormModel->getForm() );

		if ( ! $donationFormModel->getFeeRecoveryAccepted() || empty( $recoveryFeeData ) ) {
			return 0;
		}

		return (int) Utils::calculateRecoveryFee(
			(int) $donationFormModel->getAmount(),
			$recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_PERCENTAGE ],
			$recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT ],
			$donationFormModel->getForm()->currency
		);
	}

	/**
	 * Whether a one-time donation's PaymentIntent charges what its save request set. When the save
	 * request recorded the donation on the PaymentIntent, the model takes the saved amount and fee
	 * choice, so the donation is recorded as charged even if the form changed since.
	 *
	 * @param InlineDonationFormModel $donationFormModel
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent
	 * @return bool
	 */
	private function applySavedDonationCharge( $donationFormModel, $paymentIntent ) {
		$metadata = $this->getPaymentIntentMetadataArray( $paymentIntent );

		// Saved before the save request recorded the donation on the PaymentIntent, or not by this site.
		if ( ! $this->isSignedChargeBasis( $paymentIntent, $metadata, self::DONATION_CHARGE_BASIS_KEYS ) ) {
			return (int) $paymentIntent->amount === $this->calculateDonationChargeAmount( $donationFormModel );
		}

		$savedAmount = (int) $metadata[ self::METADATA_KEY_DONATION_AMOUNT ];
		if ( (int) $paymentIntent->amount !== $savedAmount + (int) $metadata[ self::METADATA_KEY_RECOVERY_FEE ] ) {
			return false;
		}

		$donationFormModel->setAmount( $savedAmount );
		$donationFormModel->setFeeRecoveryAccepted( '1' === (string) $metadata[ self::METADATA_KEY_FEE_RECOVERY_ACCEPTED ] ? 1 : 0 );

		return true;
	}

	/**
	 * Adds the donation's custom fields, signed, to its PaymentIntent's metadata. Stripe rejects metadata values over
	 * 500 characters, so a longer snapshot is sent empty instead of failing the donation; it also clears a snapshot an
	 * earlier save left on the intent. Such a donation records the values its charge request posts, so the value of a
	 * field the admin removed meanwhile is lost: a limit kept on purpose rather than storing snapshots on the site.
	 *
	 * @param array<string,mixed> $metadata
	 * @param string $paymentIntentId
	 * @param DonationFormModel $donationFormModel
	 * @return array<string,mixed>
	 */
	private function addDonationCustomFieldsSnapshot( array $metadata, $paymentIntentId, $donationFormModel ): array {
		$customFieldsJSON = $donationFormModel->getCustomFieldsJSON();
		$metadata[ self::METADATA_KEY_CUSTOM_FIELDS ] = mb_strlen( $customFieldsJSON ) > Utils::STRIPE_METADATA_VALUE_MAX_LENGTH ? '' : $customFieldsJSON;
		$metadata[ self::METADATA_KEY_CUSTOM_FIELDS_SIGNATURE ] = $this->signChargeBasis( $paymentIntentId, $metadata, [ self::METADATA_KEY_CUSTOM_FIELDS ] );

		return $metadata;
	}

	/**
	 * Takes the custom fields a donation's PaymentIntent saved before it was charged, as the values filter changed them,
	 * also those of a field the admin removed since. Without a snapshot this site signed, the request's values stay.
	 *
	 * @param DonationFormModel $donationFormModel
	 * @param \StripeWPFS\Stripe\PaymentIntent|object $paymentIntent
	 * @param array<string,mixed> $metadata The PaymentIntent's metadata.
	 * @return void
	 */
	private function restoreSavedDonationCustomFields( $donationFormModel, $paymentIntent, array $metadata ): void {
		if (
			empty( $metadata[ self::METADATA_KEY_CUSTOM_FIELDS ] )
			|| ! isset( $metadata[ self::METADATA_KEY_CUSTOM_FIELDS_SIGNATURE ] )
			|| ! hash_equals( $this->signChargeBasis( $paymentIntent->id, $metadata, [ self::METADATA_KEY_CUSTOM_FIELDS ] ), (string) $metadata[ self::METADATA_KEY_CUSTOM_FIELDS_SIGNATURE ] )
		) {
			return;
		}

		$donationFormModel->restoreCustomFields( (string) $metadata[ self::METADATA_KEY_CUSTOM_FIELDS ] );
	}

	/**
	 * Binds a one-time donation to the amount its save request set, when Stripe already charged the
	 * posted PaymentIntent at that amount.
	 *
	 * @param InlineDonationFormModel $donationFormModel
	 * @return bool Whether the donation was bound to the charged amount.
	 */
	private function bindToChargedDonation( $donationFormModel ) {
		if ( is_null( $donationFormModel->getForm() ) || empty( $donationFormModel->getStripePaymentIntentId() ) ) {
			return false;
		}

		$paymentIntent = $this->getVerifiedDonationPaymentIntent( $donationFormModel );
		if ( is_null( $paymentIntent ) || ! in_array( $paymentIntent->status, self::CHARGED_PAYMENT_INTENT_STATUSES, true ) ) {
			return false;
		}

		$metadata = $this->getPaymentIntentMetadataArray( $paymentIntent );
		if (
			! $this->isSignedChargeBasis( $paymentIntent, $metadata, self::DONATION_CHARGE_BASIS_KEYS )
			|| (int) $paymentIntent->amount !== (int) $metadata[ self::METADATA_KEY_DONATION_AMOUNT ] + (int) $metadata[ self::METADATA_KEY_RECOVERY_FEE ]
		) {
			return false;
		}

		$donationFormModel->setChargedAmount( (int) $metadata[ self::METADATA_KEY_DONATION_AMOUNT ] );

		return true;
	}

	/**
	 * Binds a recurring donation to the subscription its first request created, when Stripe charged that subscription's
	 * first PaymentIntent after 3D Secure (#738), so a preset or frequency the admin removed since doesn't refuse it.
	 *
	 * @param InlineDonationFormModel $donationFormModel
	 * @return bool Whether the donation was bound to the charged subscription.
	 */
	private function bindToChargedRecurringDonation( $donationFormModel ) {
		if ( is_null( $donationFormModel->getForm() ) || empty( $donationFormModel->getStripePaymentIntentId() ) ) {
			return false;
		}

		// Takes the amount, frequency and fee choice from the subscription.
		if ( is_null( $this->findSubmittedRecurringDonationSubscription( $donationFormModel ) ) ) {
			return false;
		}
		if ( ! in_array( $donationFormModel->getStripePaymentIntent()->status, self::CHARGED_PAYMENT_INTENT_STATUSES, true ) ) {
			return false;
		}

		$donationFormModel->setChargedAmount( (int) $donationFormModel->getAmount() );

		return true;
	}

	/**
	 * Update onetime donation payment intent with the final amount.
	 *
	 * @param InlineDonationFormModel $donationFormModel The donation form model.
	 * @return array<string,bool>
	 */
	private function saveOnetimeDonation( $donationFormModel ) {
		$paymentIntentId = $donationFormModel->getStripePaymentIntentId();
		$clientSecret = $donationFormModel->getStripePaymentIntentClientSecret();

		$paymentIntent = $this->getClientSecretVerifiedPaymentIntent( $paymentIntentId, $clientSecret );
		if ( empty( $paymentIntent ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'wp-full-stripe-free' ) ], 403 );
		}

		$donationFormModel->setStripePaymentIntent( $paymentIntent );

		$recoveryFee = $this->calculateDonationRecoveryFee( $donationFormModel );
		$amount = (int) $donationFormModel->getAmount() + $recoveryFee;

		$customerEmail = $donationFormModel->getCardHolderEmail();
		$customerName = $donationFormModel->getCardHolderName();

		// The lookup the charge uses (createOrRetrieveCustomerByFormModel()), so the PaymentIntent names
		// the customer the charge does. Not Stripe's customer search: it finds a new customer only up
		// to a minute later, so a donor retrying after a decline would get a second customer.
		$stripeCustomer = $this->findStripeCustomerByEmail( $customerEmail );

		if ( is_null( $stripeCustomer ) ) {
			$metadata = [];

			$stripeCustomer = $this->stripe->createCustomerWithPaymentMethod(
				null, // No payment method at this stage
				$customerName,
				$customerEmail,
				$metadata,
				null, // taxIdType
				null, // taxId
				$donationFormModel->getBillingAddress(),
				$donationFormModel->getBillingName(),
				$donationFormModel->getShippingAddress(),
				$donationFormModel->getShippingName()
			);
		}
		$donationFormModel->setStripeCustomer( $stripeCustomer );

		$metadata = $donationFormModel->getMetadata();
		$metadata['ip_address'] = $donationFormModel->getIpAddress();
		$metadata['donation_frequency'] = $donationFormModel->getDonationFrequency();
		$metadata['form_id'] = $donationFormModel->getForm()->donationFormID;
		$metadata['form_type'] = Main::FORM_TYPE_INLINE_DONATION;
		// The snapshot feeds the webhook fallback for abandoned flows, and the charge request keeps a removed field's value.
		$metadata = $this->addDonationCustomFieldsSnapshot( $metadata, $paymentIntent->id, $donationFormModel );
		$metadata['customer_id'] = $donationFormModel->getStripeCustomer()->id;
		// Stripe metadata values must be strings.
		$metadata[ self::METADATA_KEY_DONATION_AMOUNT ] = (string) (int) $donationFormModel->getAmount();
		$metadata[ self::METADATA_KEY_RECOVERY_FEE ] = (string) $recoveryFee;
		$metadata[ self::METADATA_KEY_FEE_RECOVERY_ACCEPTED ] = empty( $donationFormModel->getFeeRecoveryAccepted() ) ? '0' : '1';
		$metadata[ self::METADATA_KEY_CHARGE_SIGNATURE ] = $this->signChargeBasis( $paymentIntent->id, $metadata, self::DONATION_CHARGE_BASIS_KEYS );

		// Stripe updates need metadata as a plain key/value map, a retrieved
		// PaymentIntent declares metadata as a StripeObject, so build a separate payload.
		$paymentIntentUpdate = new \stdClass();
		$paymentIntentUpdate->id = $paymentIntent->id;
		$paymentIntentUpdate->amount = $amount;
		$paymentIntentUpdate->description = isset( $paymentIntent->description ) ? $paymentIntent->description : null;
		$paymentIntentUpdate->metadata = PaymentIntentBinding::preserveBindingMetadata( $paymentIntent, $metadata );

		$this->stripe->updatePaymentIntent( $paymentIntentUpdate, true );

		return [
			'success' => true
		];
	}

	/**
	 * The subscription a recurring donation's first request created, if the request finishing it after
	 * 3D Secure posts that subscription's first PaymentIntent (#738). The donation is finished as it was
	 * subscribed: the amount, frequency and fee choice are taken from the subscription, not the form.
	 *
	 * @param InlineDonationFormModel $donationFormModel
	 * @return object|null The Stripe subscription.
	 */
	private function findSubmittedRecurringDonationSubscription( $donationFormModel ) {
		$paymentIntent = $this->getVerifiedDonationPaymentIntent( $donationFormModel );
		$metadata = is_null( $paymentIntent ) ? [] : $this->getPaymentIntentMetadataArray( $paymentIntent );
		$subscription = empty( $metadata[ self::METADATA_KEY_SUBSCRIPTION_ID ] ) ?
			null :
			$this->stripe->retrieveSubscription( $metadata[ self::METADATA_KEY_SUBSCRIPTION_ID ] );

		$donationItem = null;
		$hasRecoveryFee = false;
		$items = isset( $subscription->items->data ) ? $subscription->items->data : [];
		foreach ( $items as $item ) {
			$lookupKey = isset( $item->price->lookup_key ) ? (string) $item->price->lookup_key : '';
			if ( 0 === strpos( $lookupKey, Main::DONATION_PLAN_ID_PREFIX ) ) {
				$donationItem = $item;
			} elseif ( 0 === strpos( $lookupKey, Main::SUBSCRIPTION_PLAN_ID_PREFIX ) ) {
				$hasRecoveryFee = true;
			}
		}

		// The PaymentIntent names its subscription in metadata only the plugin writes; it must also be the
		// subscription's own, and the subscription a donation.
		if (
			is_null( $donationItem )
			|| empty( $paymentIntent->customer )
			|| (string) $subscription->customer !== (string) $paymentIntent->customer
		) {
			$this->logger->error(
				__FUNCTION__,
				sprintf(
					'PaymentIntent %1$s does not pay a recurring donation submitted on form %2$s, refusing the charge.',
					$donationFormModel->getStripePaymentIntentId(),
					$donationFormModel->getFormName()
				)
			);

			return null;
		}

		$donationFormModel->setStripePaymentIntent( $paymentIntent );
		$donationFormModel->setAmount( (int) $donationItem->quantity );
		$donationFormModel->setDonationFrequency( $this->translateIntervalToFrequency( $donationItem->price->recurring->interval ) );
		$donationFormModel->setFeeRecoveryAccepted( $hasRecoveryFee ? 1 : 0 );

		return $subscription;
	}

	/**
	 * Binds the first invoice's PaymentIntent of a recurring donation to the form and names the
	 * subscription it pays, before the donor is asked to authenticate it (#738).
	 *
	 * @param object $paymentIntent The PaymentIntent of the subscription's first invoice.
	 * @param object $subscription The subscription the request created.
	 * @param InlineDonationFormModel $donationFormModel
	 */
	private function bindRecurringDonationPaymentIntent( $paymentIntent, $subscription, $donationFormModel ): void {
		// Only what the request after authentication needs: Stripe merges these keys into the metadata, and
		// the form's own metadata, which may already be near Stripe's 50 keys, stays on the subscription.
		$metadata = $this->addDonationCustomFieldsSnapshot(
			[ self::METADATA_KEY_SUBSCRIPTION_ID => $subscription->id ],
			$paymentIntent->id,
			$donationFormModel
		);

		// Stripe updates need metadata as a plain key/value map, a retrieved
		// PaymentIntent declares metadata as a StripeObject, so build a separate payload.
		$paymentIntentUpdate = new \stdClass();
		$paymentIntentUpdate->id = $paymentIntent->id;
		$paymentIntentUpdate->description = isset( $paymentIntent->description ) ? $paymentIntent->description : null;
		$paymentIntentUpdate->metadata = PaymentIntentBinding::createBindingMetadata(
			Main::FORM_TYPE_INLINE_DONATION,
			$donationFormModel->getForm(),
			PaymentIntentBinding::preserveBindingMetadata( $paymentIntent, $metadata )
		);

		$this->stripe->updatePaymentIntent( $paymentIntentUpdate );
	}

	/**
	 * Handling onetime donation.
	 *
	 * @return void
	 */
	public function fullstripe_onetime_donation_charge() {
		try {
			$paymentFormModel = $this->getFormModel( Main::FORM_TYPE_INLINE_DONATION );
			$bindingResult = $paymentFormModel->bind();

			// A donation Stripe already charged is checked against its save request, which the form checked, not the
			// form as edited since.
			$isChargedDonation = $bindingResult->hasErrors() && $this->bindToChargedDonation( $paymentFormModel );
			// Bound again, the request still refused only has its custom field values filtered here.
			if ( $isChargedDonation && $paymentFormModel->bind()->hasErrors() ) {
				$paymentFormModel->filterChargedCustomFieldValues();
			}
			$isRefused = $bindingResult->hasErrors() && ! $isChargedDonation;

			// This action charges once, so it only takes one-time donations; recurring ones go to the inline donation action.
			if ( ! $isRefused && $paymentFormModel->isRecurringDonation() ) {
				if ( empty( $paymentFormModel->getStripePaymentIntentId() ) ) {
					$bindingResult->addGlobalError(
						/* translators: Form field validation error message when no donation frequency is selected */
						__( 'Please select a donation frequency', 'wp-full-stripe-free' )
					);
					$isRefused = true;
				} else {
					// The donor changed the frequency while the one-time PaymentIntent was confirming; it charges once.
					$paymentFormModel->setDonationFrequency( DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_ONE_TIME );
				}
			}

			if ( $isRefused ) {
				$return = Utils::generateReturnValueFromBindings( $bindingResult );
			} else {
				$result = $this->processOnetimeDonationCharge( $paymentFormModel );
				$return = $result->getAsArray();
			}
		} catch (UserFriendlyException $ex) {
			$this->logger->error( __FUNCTION__, 'User-friendly exception while handling onetime donation charge', $ex );

			$messageTitle = is_null( $ex->getTitle() ) ?
				/* translators: Banner title of an error returned from an extension point by a developer */
				__( 'Internal Error', 'wp-full-stripe-free' ) :
				$ex->getTitle();
			$message = $ex->getMessage();
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\StripeWPFS\Stripe\Exception\CardException $ex) {
			$this->logger->error( __FUNCTION__, 'Stripe card exception while handling onetime donation charge', $ex );

			$messageTitle =
				/* translators: Banner title of error returned by Stripe */
				__( 'Stripe Error', 'wp-full-stripe-free' );
			$message = $this->stripe->resolveErrorMessageByCode( $ex->getCode() );
			if ( is_null( $message ) ) {
				$message = Localization::translateLabel( $ex->getMessage() );
			}
			$return = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Generic exception while handling onetime donation charge', $ex );

			$return = [
				'success' => false,
				'messageTitle' =>
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				'message' => Localization::translateLabel( $ex->getMessage() ),
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\Error $err) {
			$this->logger->error( __FUNCTION__, 'Generic error while handling onetime donation charge', $err );

			$return = [
				'success' => false,
				'messageTitle' =>
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				'message' => Localization::translateLabel( $err->getMessage() ),
				'exceptionMessage' => $err->getMessage()
			];
		}

		header( "Content-Type: application/json" );
		echo json_encode( apply_filters( 'fullstripe_onetime_donation_charge_return_message', $return ) );
		exit;
	}

	/**
	 * Process the onetime donation charge.
	 *
	 * @param InlineDonationFormModel $donationFormModel The donation form model.
	 *
	 * @return DonationPaymentIntentResult The charge result.
	 *
	 * @throws \Exception If an error occurs during the process.
	 */
	private function processOnetimeDonationCharge( $donationFormModel ) {
		$this->logger->debug( __FUNCTION__, "CALLED" );

		$paymentIntentResult = new DonationPaymentIntentResult();
		$paymentIntentResult->setNonce( $donationFormModel->getNonce() );
		// get the payment intent from Stripe to be able to react to status etc.
		if ( $donationFormModel->getStripePaymentIntentId() ) {
			$submittedPaymentIntent = $this->getVerifiedDonationPaymentIntent( $donationFormModel );

			if ( is_null( $submittedPaymentIntent ) ) {
				$this->logger->error(
					__FUNCTION__,
					sprintf(
						'PaymentIntent %1$s does not belong to the donation submitted on form %2$s, refusing the charge.',
						$donationFormModel->getStripePaymentIntentId(),
						$donationFormModel->getFormName()
					)
				);

				return $this->createPaymentIntentResultFailed(
					$paymentIntentResult,
					/* translators: Banner title of failed transaction */
					__( 'Failed', 'wp-full-stripe-free' ),
					__( 'Invalid request', 'wp-full-stripe-free' )
				);
			}

			// The first invoice of a recurring donation is not a one-time donation (#738).
			$submittedMetadata = $this->getPaymentIntentMetadataArray( $submittedPaymentIntent );
			if ( ! empty( $submittedMetadata[ self::METADATA_KEY_SUBSCRIPTION_ID ] ) ) {
				$this->logger->error(
					__FUNCTION__,
					sprintf(
						'PaymentIntent %1$s pays subscription %2$s, refusing it as a one-time donation on form %3$s.',
						$submittedPaymentIntent->id,
						$submittedMetadata[ self::METADATA_KEY_SUBSCRIPTION_ID ],
						$donationFormModel->getFormName()
					)
				);

				return $this->createPaymentIntentResultFailed(
					$paymentIntentResult,
					/* translators: Banner title of failed transaction */
					__( 'Failed', 'wp-full-stripe-free' ),
					__( 'Invalid request', 'wp-full-stripe-free' )
				);
			}

			// Record the donation as its save request set it, and refuse a PaymentIntent amount it did not set.
			if ( ! $this->applySavedDonationCharge( $donationFormModel, $submittedPaymentIntent ) ) {
				$this->logger->error(
					__FUNCTION__,
					sprintf(
						'PaymentIntent %1$s carries %2$d, which its save request did not set for the donation submitted on form %3$s, refusing the charge.',
						$submittedPaymentIntent->id,
						$submittedPaymentIntent->amount,
						$donationFormModel->getFormName()
					)
				);

				return $this->createPaymentIntentResultFailed(
					$paymentIntentResult,
					/* translators: Banner title of failed transaction */
					__( 'Failed', 'wp-full-stripe-free' ),
					__( 'Invalid request', 'wp-full-stripe-free' )
				);
			}

			$donationFormModel->setStripePaymentIntent( $submittedPaymentIntent );
			// Keep the acceptance the save step stamped: this request binds the form again.
			$donationFormModel->restoreTermsOfUseAcceptedAt( $submittedMetadata[ FormModel::METADATA_KEY_TERMS_OF_USE_ACCEPTED_AT ] ?? null );
			$this->restoreSavedDonationCustomFields( $donationFormModel, $submittedPaymentIntent, $submittedMetadata );
			if ( isset( $donationFormModel->getStripePaymentIntent()->latest_charge ) ) {
				$latest_charge = $donationFormModel->getStripePaymentIntent()->latest_charge;
				if ( is_string( $latest_charge ) ) {
					$latest_charge = $this->stripe->getLatestCharge( $donationFormModel->getStripePaymentIntent() );
				}
				$donationFormModel->setStripePaymentMethodType( $latest_charge->payment_method_details ? $latest_charge->payment_method_details->type : null );
			}
		} elseif ( '1' === $donationFormModel->getForm()->generateInvoice ) {
			// The donation form always sends the PaymentIntent it confirmed. Without one, the server would
			// invoice and charge the donation itself, without the recovery fee (#748).
			$this->logger->error(
				__FUNCTION__,
				sprintf( 'No PaymentIntent was submitted for the invoiced donation on form %s, refusing the charge.', $donationFormModel->getFormName() )
			);

			return $this->createPaymentIntentResultFailed(
				$paymentIntentResult,
				/* translators: Banner title of failed transaction */
				__( 'Failed', 'wp-full-stripe-free' ),
				__( 'Invalid request', 'wp-full-stripe-free' )
			);
		}

		$createCustomerOptions = new CreateCustomerOptions();
		$createCustomerOptions->addMetadata = false;
		// One-time donations are charged once, never again, so the PaymentMethod is not saved.
		$createCustomerOptions->attachPaymentMethod = false;
		$this->createOrRetrieveCustomerByFormModel( $donationFormModel, $createCustomerOptions );

		$transactionData = TransactionDataService::createDonationDataByFormModel( $donationFormModel );

		$this->fireBeforeInlineDonationAction( $donationFormModel, $transactionData );

		$paymentIntent = null;
		$latestCharge = null;

		if ( '1' === $donationFormModel->getForm()->generateInvoice ) {
			$createInvoiceOptions = new CreateOneTimeInvoiceOptions();
			// Marked paid below. Advanced automatically, Stripe would finalize it later and try to collect it if
			// that failed.
			$createInvoiceOptions->autoAdvance = false;
			// Records the charge after the fact: the donation as saved, and the rest of what the PaymentIntent
			// charged as its recovery fee.
			$invoiceContext = $this->createOneTimeInvoiceContext( $donationFormModel );
			$chargedPaymentIntent = $donationFormModel->getStripePaymentIntent();
			if ( ! empty( $chargedPaymentIntent ) ) {
				$invoiceContext->currency = $chargedPaymentIntent->currency;
				$invoiceContext->recoveryFee = max( 0, (int) $chargedPaymentIntent->amount - (int) $invoiceContext->amount );
			}
			$paidStripeInvoice = $this->createPaidInvoiceForPaymentIntent( $invoiceContext, $createInvoiceOptions, $donationFormModel->getStripePaymentIntentId() );

			$transactionData->setStripeInvoiceId( $paidStripeInvoice->id );
			$transactionData->setInvoiceUrl( $paidStripeInvoice->invoice_pdf );
			$transactionData->setInvoiceNumber( $paidStripeInvoice->number );

			if ( $donationFormModel->getStripePaymentIntent() ) {
				// no need to refetch the PaymentIntent if it is already available
				$paymentIntent = $donationFormModel->getStripePaymentIntent();
			} else {
				$this->logger->debug( __FUNCTION__, "Retrieving PaymentIntent..." );
				try {
					$paymentIntent = $this->stripe->retrievePaymentIntent( $donationFormModel->getStripePaymentIntentId() );
				} catch ( \Exception $e ) {
					// Fallback if retrieval fails
					$this->logger->debug( __FUNCTION__, "Failed to retrieve PaymentIntent: " . $e->getMessage() );
					$paymentIntent = null;
				}
			}
		} else {
			if ( $donationFormModel->getStripePaymentIntent() ) {
				// no need to refetch the PaymentIntent if it is already available
				$paymentIntent = $donationFormModel->getStripePaymentIntent();
			} else if ( ! empty( $donationFormModel->getStripePaymentIntentId() ) ) {
				$this->logger->debug( __FUNCTION__, "Retrieving PaymentIntent..." );
				try {
					$paymentIntent = $this->stripe->retrievePaymentIntent( $donationFormModel->getStripePaymentIntentId() );
				} catch ( \Exception $e ) {
					// Fallback if retrieval fails
					$this->logger->debug( __FUNCTION__, "Failed to retrieve PaymentIntent: " . $e->getMessage() );
					$paymentIntent = null;
				}
			} else {
				$paymentIntent = $this->createPaymentIntentForDonation( $donationFormModel, $transactionData );
			}
		}

		if ( $paymentIntent !== null ) {
			$paymentIntent->metadata = PaymentIntentBinding::preserveBindingMetadata(
				$paymentIntent,
				$donationFormModel->getMetadata()
			);

			// update description and metadata
			$transactionData->setTransactionId( $paymentIntent->id );
			$stripePaymentIntentDescription = Utils::prepareStripeDonationDescription( $this->staticContext, $donationFormModel, $transactionData );

			$paymentIntent->description = empty( $stripePaymentIntentDescription ) ? null : $stripePaymentIntentDescription;

			if ( $donationFormModel->getStripeCustomer() ) {
				$paymentIntent->customerId = $donationFormModel->getStripeCustomer()->id;
			}

			$this->stripe->updatePaymentIntent(
				$paymentIntent,
				! $this->paymentIntentSucceeded( $paymentIntent ),
				Mailer::canSendDonationStripeReceipt( $donationFormModel->getForm() ) ? $donationFormModel->getCardHolderEmail() : null
			);

			// in some cases we need to re-confirm the PaymentIntent
			if ( \StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_CONFIRMATION === $paymentIntent->status ) {
				$paymentIntent = $this->stripe->confirmPaymentIntent( $paymentIntent->id, $donationFormModel->getStripePaymentMethodId() );
			}

			$donationFormModel->setTransactionId( $paymentIntent->id );

			if ( $this->paymentIntentRequiresAction( $paymentIntent ) ) {
				$this->createPaymentIntentResultActionRequired(
					$paymentIntentResult,
					$paymentIntent,
					/* translators: Banner title of pending transaction requiring a second factor authentication (SCA/PSD2) */
					__( 'Action required', 'wp-full-stripe-free' ),
					/* translators: Banner message of a one-time payment requiring a second factor authentication (SCA/PSD2) */
					__( 'The donation needs additional action before completion!', 'wp-full-stripe-free' )
				);
			} elseif ( $this->paymentIntentSucceeded( $paymentIntent ) ) {
				$this->setInvoiceDataFromPaymentIntent( $paymentIntent, $transactionData );
				$this->addFormNameToPaymentIntent( $paymentIntent, $donationFormModel->getFormName() );

				$latestCharge = $this->stripe->getLatestCharge( $paymentIntent );

				if ( $latestCharge !== null ) {
					if ( null === $this->db->getDonationByPaymentIntentId( $paymentIntent->id ) ) {
						$this->insertInlineDonationFromLatestCharge( $donationFormModel, $paymentIntent, null, $latestCharge );
					}

					// The webhook fallback records the donation without custom fields when they were too
					// long for the PaymentIntent's custom_fields snapshot, possibly while this request runs.
					$this->db->fillMissingDonationCustomFields( $paymentIntent->id, $donationFormModel->getCustomFieldsJSON() );

					// Not tied to the insert: the webhook fallback may have recorded the donation first.
					$this->setChargedDonationFee( $transactionData, $donationFormModel, $paymentIntent );
					$this->fireAfterInlineDonationAction( $donationFormModel, $transactionData, $paymentIntent );

					$this->createPaymentIntentResultSuccess(
						$paymentIntentResult,
						/* translators: Banner title of successful transaction */
						__( 'Success', 'wp-full-stripe-free' ),
						/* translators: Banner message of successful payment */
						__( 'Donation Successful!', 'wp-full-stripe-free' )
					);
				} else {
					$this->createPaymentIntentResultFailed(
						$paymentIntentResult,
						/* translators: Banner title of failed transaction */
						__( 'Failed', 'wp-full-stripe-free' ),
						/* translators: Banner message of a failed donation */
						__( 'Payment succeeded but charge data is unavailable.', 'wp-full-stripe-free' )
					);
				}
			} else {
				$this->createPaymentIntentResultFailed(
					$paymentIntentResult,
					/* translators: Banner title of failed transaction */
					__( 'Failed', 'wp-full-stripe-free' ),
					sprintf(
						/* translators: %s: status reported by Stripe */
						__( "Invalid PaymentIntent status '%s'.", 'wp-full-stripe-free' ),
						$paymentIntent->status
					)
				);
			}
		} else {
			$this->createPaymentIntentResultFailed(
				$paymentIntentResult,
				/* translators: Banner title of failed transaction */
				__( 'Failed', 'wp-full-stripe-free' ),
				/* translators: Banner message of a failed donation */
				__( 'PaymentIntent was neither created nor retrieved.', 'wp-full-stripe-free' )
			);
		}

		$this->handleRedirect( $donationFormModel, $transactionData, $paymentIntentResult );

		return $paymentIntentResult;
	}

	/**
	 * Records the donation from Stripe's latest charge, so that a refund recorded meanwhile is kept.
	 *
	 * @param InlineDonationFormModel $donationFormModel
	 * @param \StripeWPFS\Stripe\PaymentIntent $paymentIntent
	 * @param \StripeWPFS\Stripe\Subscription|null $subscription
	 * @param object|null $latestCharge The charge read before, used when Stripe has none.
	 *
	 * @throws \Exception
	 */
	private function insertInlineDonationFromLatestCharge( $donationFormModel, $paymentIntent, $subscription, $latestCharge ): void {
		LatestCharge::write(
			$this->db,
			$this->stripe,
			$paymentIntent->id,
			function ( $freshCharge ) use ( $donationFormModel, $paymentIntent, $subscription, $latestCharge ) {
				$this->db->insertInlineDonation( $donationFormModel, $paymentIntent, $subscription, $freshCharge ?? $latestCharge );
			},
			false
		);
	}

	/**
	 * Update payment status in database when payment intent confirmation fails.
	 *
	 * @return void
	 */
	function update_failed_payment_status() {
		if (
			! isset( $_POST['nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), Main::NONCE_ACTION_UPDATE_FAILED_PAYMENT_STATUS )
		) {
			wp_send_json_error( [ 'message' => __( 'Invalid request', 'wp-full-stripe-free' ) ], 403 );
		}

		try {
			$result = [];
			// Bound the client-reported failure strings to their column sizes (failure_code
			// VARCHAR(100), failure_message VARCHAR(512)) so a caller can't store oversized data.
			$failureCode = isset( $_POST['failureCode'] ) ? Utils::truncateString( sanitize_text_field( wp_unslash( $_POST['failureCode'] ) ), 100 ) : null;
			$failureMessage = isset( $_POST['failureMessage'] ) ? Utils::truncateString( sanitize_text_field( wp_unslash( $_POST['failureMessage'] ) ), 512 ) : null;
			$paymentIntentId = isset( $_POST['paymentIntentId'] ) ? sanitize_text_field( wp_unslash( $_POST['paymentIntentId'] ) ) : null;
			$clientSecret = isset( $_POST['clientSecret'] ) ? sanitize_text_field( wp_unslash( $_POST['clientSecret'] ) ) : null;

			$paymentIntent = $this->getClientSecretVerifiedPaymentIntent( $paymentIntentId, $clientSecret );
			if ( empty( $paymentIntent ) ) {
				wp_send_json_error( [ 'message' => __( 'Unauthorized', 'wp-full-stripe-free' ) ], 403 );
			}

			// Webhooks write the payment too: none may land between this read of Stripe and the write. Taken once the
			// client proved it knows the payment.
			if ( ! $this->db->lockPaymentIntent( $paymentIntentId, Database::PAYMENT_LOCK_TIMEOUT ) ) {
				wp_send_json_error( [ 'message' => __( 'Payment is being updated', 'wp-full-stripe-free' ) ], 409 );
			}
			try {
				$paymentIntent = $this->stripe->retrievePaymentIntent( $paymentIntentId );

				// This endpoint only records a *failed* confirmation. Refuse to mark a payment unpaid
				// unless Stripe agrees it actually failed: a succeeded, still-processing or
				// authorized-awaiting-capture intent must never be flipped to unpaid based on a
				// client-reported failure, otherwise a good payment gets desynced from Stripe.
				$nonFailedStatuses = [
					\StripeWPFS\Stripe\PaymentIntent::STATUS_SUCCEEDED,
					\StripeWPFS\Stripe\PaymentIntent::STATUS_PROCESSING,
					\StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_CAPTURE,
				];
				if ( in_array( $paymentIntent->status, $nonFailedStatuses, true ) ) {
					wp_send_json_error( [ 'message' => __( 'Payment is not in a failed state', 'wp-full-stripe-free' ) ], 409 );
				}

				$lastCharge = null;

				if ( isset( $paymentIntent->latest_charge ) && ! empty( $paymentIntent->latest_charge ) ) {
					$lastCharge = $paymentIntent->latest_charge;
				}

				$updateData = [
					'paid' => 0,
					'captured' => 0,
					'refunded' => 0
				];

				if ( $lastCharge ) {
					$updateData['last_charge_status'] = $lastCharge->status;
					$updateData['failure_code'] = $lastCharge->failure_code;
					$updateData['failure_message'] = $lastCharge->failure_message;
				} else {
					$updateData['last_charge_status'] = 'failed';
					$updateData['failure_code'] = $failureCode;
					$updateData['failure_message'] = $failureMessage;
				}

				$this->db->updatePaymentByEventId( $paymentIntentId, $updateData );
			} finally {
				$this->db->unlockPaymentIntent( $paymentIntentId );
			}

			$result = [
				'success' => true,
				'message' => __( 'Payment status updated successfully', 'wp-full-stripe-free' ),
			];

			$this->logger->info( __FUNCTION__, 'Payment status updated for payment intent: ' . $paymentIntentId );
		} catch (UserFriendlyException $ex) {
			$this->logger->error( __FUNCTION__, 'User-friendly exception while updating failed payment status', $ex );

			$messageTitle = is_null( $ex->getTitle() ) ?
				/* translators: Banner title of an error returned from an extension point by a developer */
				__( 'Internal Error', 'wp-full-stripe-free' ) :
				$ex->getTitle();
			$message = $ex->getMessage();
			$result = [
				'success' => false,
				'messageTitle' => $messageTitle,
				'message' => $message,
				'exceptionMessage' => $ex->getMessage()
			];
		} catch ( \Exception $ex ) {
			$this->logger->error( __FUNCTION__, 'Exception in update_failed_payment_status', $ex );
			$result = [
				'success' => false,
				'messageTitle' =>
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				'message' => Localization::translateLabel( $ex->getMessage() ),
				'exceptionMessage' => $ex->getMessage()
			];
		} catch (\Error $err) {
			$this->logger->error( __FUNCTION__, 'Generic error while handling payment charge', $err );

			$result = [
				'success' => false,
				'messageTitle' =>
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				'message' => Localization::translateLabel( $err->getMessage() ),
				'exceptionMessage' => $err->getMessage()
			];
		}

		header( 'Content-Type: application/json' );
		echo json_encode( $result );
		exit;
	}
}
