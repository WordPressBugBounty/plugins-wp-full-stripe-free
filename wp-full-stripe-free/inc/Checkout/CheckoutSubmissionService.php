<?php

namespace ThemeIsle\WPFullPay\Checkout;

use ThemeIsle\WPFullPay\Checkout\SessionBuilders\CheckoutSessionBuilderDonation;
use ThemeIsle\WPFullPay\Checkout\SessionBuilders\CheckoutSessionBuilderOneTimePayment;
use ThemeIsle\WPFullPay\Checkout\SessionBuilders\CheckoutSessionBuilderSaveCard;
use ThemeIsle\WPFullPay\Checkout\SessionBuilders\CheckoutSessionBuilderSubscription;
use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Forms\Models\CheckoutDonationFormModel;
use ThemeIsle\WPFullPay\Forms\Models\CheckoutPaymentFormModel;
use ThemeIsle\WPFullPay\Forms\Models\CheckoutSubscriptionFormModel;
use ThemeIsle\WPFullPay\Forms\Models\FormModel;
use ThemeIsle\WPFullPay\Forms\Models\PaymentFormModel;
use ThemeIsle\WPFullPay\Forms\Views\FormViewConstants;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Mailer\Mailer;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Results\ChargeResult;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\Subscriptions\FeeSync;
use ThemeIsle\WPFullPay\Utils;

/**
 * @phpstan-import-type CheckoutFormSubmit from Database
 */
class CheckoutSubmissionService {
	use LoggerAddOn;
	use StaticContextAddOn;

	const POPUP_FORM_SUBMIT_STATUS_CREATED = 'created';
	const POPUP_FORM_SUBMIT_STATUS_PENDING = 'pending';
	const POPUP_FORM_SUBMIT_STATUS_FAILED = 'failed';
	const POPUP_FORM_SUBMIT_STATUS_CANCELLED = 'cancelled';
	const POPUP_FORM_SUBMIT_STATUS_SUCCESS = 'success';
	const POPUP_FORM_SUBMIT_STATUS_COMPLETE = 'complete';
	const POPUP_FORM_SUBMIT_STATUS_INTERNAL_ERROR = 'internal_error';
	const CHECKOUT_SESSION_STATUS_SUCCESS = 'success';
	const CHECKOUT_SESSION_STATUS_CANCELLED = 'cancelled';
	const PROCESS_RESULT_SET_TO_SUCCESS = 1;
	const PROCESS_RESULT_SET_TO_FAILED = 2;
	const PROCESS_RESULT_EXPIRED = 3;
	const PROCESS_RESULT_WAIT_FOR_STATUS_CHANGE = 4;
	const PROCESS_RESULT_INTERNAL_ERROR = 20;
	const ACTION_FULLSTRIPE_PROCESS_CHECKOUT_SUBMISSIONS = 'fullstripe_process_checkout_submissions';
	const STRIPE_CALLBACK_PARAM_WPFS_POPUP_FORM_SUBMIT_HASH = 'wpfs-sid';
	const STRIPE_CALLBACK_PARAM_WPFS_CHECKOUT_SESSION_ID = 'wpfs-csid';
	const STRIPE_CALLBACK_PARAM_WPFS_STATUS = 'wpfs-status';
	/** @var string Key of the custom fields, as the form checked them, in a saved submission. */
	const SUBMIT_KEY_CUSTOM_FIELDS = 'wpfs-custom-fields-snapshot';
	/** @var string Key of the price of the product a payment's session charges, in a saved submission. */
	const SUBMIT_KEY_PRICE_ID = 'wpfs-price-id-snapshot';

	/** @var bool */
	private static $running = false;

	/** @var Stripe $stripe */
	private $stripe = null;

	/** @var Database $db */
	private $db = null;

	/** @var Options  */
	private $options = null;

	/** @var Mailer */
	private $mailer = null;

	/** @var int Iteration count for processing entries in the scheduled function */
	private $iterationCount = 5;
	/** @var int Entry count processed in one iteration */

	private $entryCount = 50;
	/** @var int how many times can be an entry processed with error before putting it in INTERNAL_ERROR status */

	private $processErrorLimit = 3;

	/** @var int The length of the errorMessage column. */
	const ERROR_MESSAGE_MAX_LENGTH = 180;

	/** @var string The option keeping the ID of the last submission the recovery read. */
	const OPTION_LAST_PROCESSED_ID = 'wpfs_checkout_recovery_last_id';

	/** @var string The option keeping the ID of the last submission the recovery's pass reads. */
	const OPTION_PASS_END_ID = 'wpfs_checkout_recovery_pass_end_id';

	/** @var int Seconds the return callback waits for another run completing the same submission, to show its result. */
	protected $returnLockTimeout = 10;

	/**
	 * CheckoutSubmissionService constructor.
	 *
	 * @throws \Exception
	 */
	public function __construct( $loggerService ) {
		$this->setup( $loggerService );
		$this->hooks();

		$this->logger->debug( __FUNCTION__, 'CALLED, running=' . ( $this->isRunning() ? 'true' : 'false' ) );
	}

	private function setup( $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_CHECKOUT_SUBMISSION );
		$this->options = new Options();

		$this->initStaticContext();

		$this->db = new Database();
		$this->stripe = new Stripe( Stripe::getStripeAuthenticationToken( $this->staticContext ), $this->loggerService );
		$this->mailer = new Mailer( $this->loggerService );
	}

	private function hooks() {
		add_action(
			self::ACTION_FULLSTRIPE_PROCESS_CHECKOUT_SUBMISSIONS,
			[
				$this,
				'processCheckoutSubmissions'
			]
		);
	}

	/**
	 * @return bool
	 */
	private function isRunning() {
		return self::$running;
	}

	public static function onActivation() {
		if ( ! wp_next_scheduled( self::ACTION_FULLSTRIPE_PROCESS_CHECKOUT_SUBMISSIONS ) ) {
			wp_schedule_event( time(), WP_FULL_STRIPE_CRON_SCHEDULES_KEY_15_MIN, self::ACTION_FULLSTRIPE_PROCESS_CHECKOUT_SUBMISSIONS );
			Utils::log( 'CheckoutSubmissionService->onActivation(): Event scheduled.' );
		}
	}

	public static function onDeactivation() {
		wp_clear_scheduled_hook( self::ACTION_FULLSTRIPE_PROCESS_CHECKOUT_SUBMISSIONS );
		Utils::log( 'CheckoutSubmissionService->onDeactivation(): Scheduled event cleared.' );
	}

	/**
	 * @param FormModel $formModel
	 *
	 * @return string
	 * @throws \Exception
	 */
	private function createSubmitEntry( $formModel ) {
		$liveMode = $this->options->get( Options::OPTION_API_MODE ) === Main::STRIPE_API_MODE_LIVE;
		$salt = wp_generate_password( 16, false );
		$submitId = time() . '|' . $formModel->getFormHash() . '|' . $liveMode . '|' . $salt;
		$submitHash = hash( 'sha256', $submitId );

		// Remove optional anchor from the URL tail
		$rawReferrer = $formModel->getReferrer();
		$prunedReferrer = strpos( $rawReferrer, '#' ) === false ?
			$rawReferrer :
			strstr( $formModel->getReferrer(), '#', true );
		$decoratedReferrer = add_query_arg(
			[
				self::STRIPE_CALLBACK_PARAM_WPFS_POPUP_FORM_SUBMIT_HASH => $submitHash
			],
			$prunedReferrer
		) . '#' . FormViewConstants::ATTR_ID_VALUE_PREFIX . $formModel->getFormHash();

		$postData = $formModel->getPostData();
		$postData[ self::SUBMIT_KEY_CUSTOM_FIELDS ] = $formModel->getCustomFieldsJSON();
		if ( $formModel instanceof CheckoutPaymentFormModel ) {
			$postData[ self::SUBMIT_KEY_PRICE_ID ] = $formModel->getPriceId();
		}

		$this->db->insertCheckoutFormSubmit(
			$submitHash,
			$formModel->getFormHash(),
			Utils::getFormType( $formModel->getForm() ),
			$decoratedReferrer,
			json_encode( $postData, JSON_UNESCAPED_UNICODE ),
			$liveMode
		);

		return $submitHash;
	}

	/**
	 * @param $submitHash
	 * @param $stripeCheckoutSessionId
	 *
	 * @return false|int
	 * @throws \Exception
	 */
	public function updateSubmitEntryWithSessionIdToPending( $submitHash, $stripeCheckoutSessionId ) {
		return $this->db->update_popup_form_submit_by_hash(
			$submitHash,
			[
				'checkoutSessionId' => $stripeCheckoutSessionId,
				'status' => self::POPUP_FORM_SUBMIT_STATUS_PENDING
			]
		);
	}

	/**
	 * @param CheckoutPaymentFormModel|CheckoutSubscriptionFormModel|CheckoutDonationFormModel $formModel
	 *
	 * @return \StripeWPFS\Stripe\Checkout\Session
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	public function createCheckoutSession( $formModel ) {

		$submitHash = $this->createSubmitEntry( $formModel );
		$fieldConfiguration = Main::getInstance()->getFormFieldConfiguration( $formModel->getFormGetParametersAsArray(), Utils::getFormType( $formModel->getForm() ), $formModel->getFormName() );

		if ( $formModel instanceof CheckoutPaymentFormModel ) {
			if ( Main::PAYMENT_TYPE_CARD_CAPTURE === $formModel->getForm()->customAmount ) {
				$checkoutSessionParameters = ( new CheckoutSessionBuilderSaveCard( $submitHash, $formModel, $fieldConfiguration, $this->stripe, $this->loggerService ) )->build();
			} else {
				$checkoutSessionParameters = ( new CheckoutSessionBuilderOneTimePayment( $submitHash, $formModel, $fieldConfiguration, $this->stripe, $this->loggerService ) )->build();
			}
		} else if ( $formModel instanceof CheckoutDonationFormModel ) {
			$checkoutSessionParameters = ( new CheckoutSessionBuilderDonation( $submitHash, $formModel, $fieldConfiguration, $this->stripe, $this->loggerService ) )->build();
		} else if ( $formModel instanceof CheckoutSubscriptionFormModel ) {
			$checkoutSessionParameters = ( new CheckoutSessionBuilderSubscription( $submitHash, $formModel, $fieldConfiguration, $this->stripe, $this->loggerService ) )->build();
		} else {
			throw new \Exception( "Not supported checkout form model. ", $formModel );
		}

		$stripeCheckoutSession = $this->stripe->createCheckoutSession( $checkoutSessionParameters );

		$this->updateSubmitEntryWithSessionIdToPending( $submitHash, $stripeCheckoutSession->id );

		return $stripeCheckoutSession;
	}

	/**
	 * @param $submitHash
	 *
	 * @return CheckoutFormSubmit|null
	 */
	public function retrieveSubmitEntry( $submitHash ) {
		return $this->db->findPopupFormSubmitByHash( $submitHash );
	}

	/**
	 * Completes the submission the customer returned from Checkout with. The cron, or a reload of the return page,
	 * can reach the same submission at the same moment; only the run holding it completes it.
	 *
	 * @param string|null $submitHash
	 * @param string|null $submitStatus The Checkout session's status the return URL carries.
	 *
	 * @return string Where to send the customer: the home page when the submission is unknown, as for an old link
	 *                after the submission was deleted.
	 */
	public function completeReturnedSubmission( $submitHash, $submitStatus ) {
		$popupFormSubmit = null;
		try {
			if ( empty( $submitHash ) || empty( $submitStatus ) ) {
				$this->logger->error( __FUNCTION__, 'SubmitHash and/or submitStatus is empty: submitHash=' . $submitHash . ', submitStatus=' . $submitStatus );

				return home_url();
			}

			$popupFormSubmit = $this->retrieveSubmitEntry( $submitHash );
			if ( is_null( $popupFormSubmit ) || ! isset( $popupFormSubmit->checkoutSessionId ) ) {
				$this->logger->error( __FUNCTION__, 'Submit entry not found: submitHash=' . $submitHash . ', submitStatus=' . $submitStatus );

				return home_url();
			}

			if ( self::isSubmitEntryCompleted( $popupFormSubmit ) ) {
				$this->logger->debug( __FUNCTION__, 'Submit entry already processed (status=' . $popupFormSubmit->status . '), redirect to=' . $popupFormSubmit->referrer );

				return $popupFormSubmit->referrer;
			}

			if ( self::CHECKOUT_SESSION_STATUS_SUCCESS !== $submitStatus ) {
				$this->updateSubmitEntryWithCancelled( $popupFormSubmit );
				$this->logger->debug( __FUNCTION__, 'Submit entry cancelled, redirect to=' . $popupFormSubmit->referrer );

				return $popupFormSubmit->referrer;
			}

			// The client reaches the account of the site's mode only: a return after the admin switched it leaves the
			// submission, without counting an error, for the recovery run in its mode.
			$liveMode = $this->options->get( Options::OPTION_API_MODE ) === Main::STRIPE_API_MODE_LIVE;
			if ( isset( $popupFormSubmit->liveMode ) && (bool) $popupFormSubmit->liveMode !== $liveMode ) {
				$this->logger->debug( __FUNCTION__, 'Submit entry of the other mode than the site, left for the recovery, redirect to=' . $popupFormSubmit->referrer );
				$this->updateSubmitEntryForRecovery( $popupFormSubmit );

				return $popupFormSubmit->referrer;
			}

			if ( ! $this->lockSubmitEntry( $popupFormSubmit, $this->returnLockTimeout ) ) {
				$this->logger->debug( __FUNCTION__, 'Another run is completing the submit entry, redirect to=' . $popupFormSubmit->referrer );

				return $popupFormSubmit->referrer;
			}
			try {
				return $this->completeLockedReturnedSubmission( $popupFormSubmit );
			} catch ( \Throwable $ex ) {
				// The customer finished Checkout, so a later run of the recovery completes it.
				$this->logger->error( __FUNCTION__, 'Error completing the checkout session, left for the recovery', $ex );
				$this->updateSubmitEntryForRecovery( $popupFormSubmit, $ex->getMessage() );

				return $popupFormSubmit->referrer;
			} finally {
				$this->unlockSubmitEntry( $popupFormSubmit );
			}
		} catch ( \Throwable $ex ) {
			$this->logger->error( __FUNCTION__, 'Error handling the checkout session', $ex );

			return is_null( $popupFormSubmit ) ? home_url() : $popupFormSubmit->referrer;
		}
	}

	/**
	 * @param CheckoutFormSubmit $popupFormSubmit A submission this run holds.
	 *
	 * @return string Where to send the customer.
	 * @throws \Exception
	 */
	private function completeLockedReturnedSubmission( $popupFormSubmit ) {
		// Another run may have completed it while this one waited.
		$freshSubmit = $this->retrieveSubmitEntry( $popupFormSubmit->hash );
		if ( is_object( $freshSubmit ) && self::isSubmitEntryCompleted( $freshSubmit ) ) {
			$this->logger->debug( __FUNCTION__, 'Submit entry processed meanwhile (status=' . $freshSubmit->status . '), redirect to=' . $popupFormSubmit->referrer );

			return $popupFormSubmit->referrer;
		}

		$checkoutSession = $this->retrieveCheckoutSession( $popupFormSubmit->checkoutSessionId );
		self::keepSessionSubscription( $checkoutSession );
		$formModelAndChargeHandler = $this->createFormModelAndChargeHandler( $popupFormSubmit->formType );
		if ( is_null( $formModelAndChargeHandler ) ) {
			$this->logger->debug( __FUNCTION__, "Cannot find handler and form model for form type '" . $popupFormSubmit->formType . "'." );

			return $popupFormSubmit->referrer;
		}
		list( $formModel, $checkoutChargeHandler ) = $formModelAndChargeHandler;

		// Already processed (e.g. by cron): mark success and redirect without resending notifications.
		if ( $this->isCheckoutSessionRecorded( $popupFormSubmit->formType, $checkoutSession ) ) {
			$this->updateSubmitEntryWithAlreadyProcessed( $popupFormSubmit );
			$this->logger->debug( __FUNCTION__, 'Checkout session already recorded: ' . $checkoutSession->id . ', redirect to=' . $popupFormSubmit->referrer );

			return $popupFormSubmit->referrer;
		}

		$chargeResult = $this->chargeCheckoutSession( $formModel, $checkoutChargeHandler, $popupFormSubmit, $checkoutSession );
		if ( ! $chargeResult->isSuccess() ) {
			$this->updateSubmitEntryWithFailed( $popupFormSubmit, $chargeResult->getMessageTitle(), $chargeResult->getMessage() );
			$this->logger->debug( __FUNCTION__, 'Submit entry failed, redirect to=' . $popupFormSubmit->referrer );

			return $popupFormSubmit->referrer;
		}

		$this->updateSubmitEntryWithSuccess( $popupFormSubmit, $chargeResult->getMessageTitle(), $chargeResult->getMessage() );
		$redirectURL = $chargeResult->isRedirect() ? $chargeResult->getRedirectURL() : $popupFormSubmit->referrer;
		$this->logger->debug( __FUNCTION__, 'Submit entry successfully processed, redirect to=' . $redirectURL );

		return $redirectURL;
	}

	/**
	 * Binds the form model to what the customer submitted and Checkout collected, then records the charge.
	 *
	 * @param CheckoutPaymentFormModel|CheckoutSubscriptionFormModel|CheckoutDonationFormModel $formModel
	 * @param CheckoutChargeHandler $checkoutChargeHandler
	 * @param CheckoutFormSubmit $popupFormSubmit
	 * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession
	 *
	 * @return \ThemeIsle\WPFullPay\Results\ChargeResult
	 * @throws \Exception
	 */
	private function chargeCheckoutSession( $formModel, $checkoutChargeHandler, $popupFormSubmit, $checkoutSession ) {
		$postData = array_merge(
			$formModel->extractFormModelDataFromPopupFormSubmit( $popupFormSubmit ),
			$formModel->extractFormModelDataFromCheckoutSession( $checkoutSession )
		);
		if ( $formModel instanceof CheckoutPaymentFormModel ) {
			$paymentIntent = $this->findPaymentIntentInCheckoutSession( $checkoutSession );
			// A payment refunded or released before it was recorded stays unrecorded, even once its product is back.
			// Donations and subscriptions are never refunded here, so their completions don't check this.
			if ( Utils::isPaymentIntentRefunded( $this->stripe, $paymentIntent ) ) {
				return self::createRefundedChargeResult();
			}
			// A payment's session charged the product its submission saved, wherever the form lists it now.
			if ( ! $this->selectSubmittedProduct( $postData, $checkoutSession ) ) {
				return $this->refundChargeOfRemovedProduct( $postData, $paymentIntent );
			}
		}
		$formModel->bindByArray( $postData );
		// The submission saved the custom fields the form checked, also those of a field removed since.
		if ( ! empty( $postData[ self::SUBMIT_KEY_CUSTOM_FIELDS ] ) ) {
			$formModel->restoreCustomFields( $postData[ self::SUBMIT_KEY_CUSTOM_FIELDS ] );
		}

		return $checkoutChargeHandler->handle( $formModel, $checkoutSession );
	}

	/**
	 * Points the submission's product index at the product its session charged, which the admin may have moved in the
	 * form's list since.
	 *
	 * @param array<string,mixed> $postData The saved submission, updated in place.
	 * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession
	 * @return bool False when the form no longer has that product.
	 */
	private function selectSubmittedProduct( array &$postData, $checkoutSession ): bool {
		$form = $this->db->getCheckoutPaymentFormByName( $postData[ FormModel::PARAM_WPFS_FORM_NAME ] ?? '' );
		// A submission saved before its product's price was: its session's first line item is the product, if any.
		if ( ! array_key_exists( self::SUBMIT_KEY_PRICE_ID, $postData ) && ! is_null( $form ) && PaymentFormModel::sellsProduct( $form, $postData[ PaymentFormModel::PARAM_WPFS_CUSTOM_AMOUNT ] ?? '' ) ) {
			$postData[ self::SUBMIT_KEY_PRICE_ID ] = $checkoutSession->line_items->data[0]->price->id ?? null;
		}
		// A custom amount or a card setup.
		if ( empty( $postData[ self::SUBMIT_KEY_PRICE_ID ] ) ) {
			return true;
		}

		$products = is_null( $form ) ? [] : Utils::decodeJsonArray( $form->decoratedProducts );
		foreach ( $products as $index => $product ) {
			if ( isset( $product->stripePriceId ) && (string) $product->stripePriceId === (string) $postData[ self::SUBMIT_KEY_PRICE_ID ] ) {
				$postData[ PaymentFormModel::PARAM_WPFS_CUSTOM_AMOUNT_INDEX ] = $index;

				return true;
			}
		}

		return false;
	}

	/**
	 * Refunds a paid session, or releases an authorized one, whose product the admin removed from the form while the
	 * customer was on Stripe's page, since the form can't record it, and tells the admin. A retry finds the payment
	 * refunded and stops before this.
	 *
	 * @param array<string,mixed> $postData The saved submission.
	 * @param \StripeWPFS\Stripe\PaymentIntent|null $paymentIntent The session's PaymentIntent.
	 * @return ChargeResult A failed result saying the payment was refunded.
	 */
	private function refundChargeOfRemovedProduct( array $postData, $paymentIntent ) {
		if (
			! is_null( $paymentIntent )
			&& in_array( $paymentIntent->status, [ \StripeWPFS\Stripe\PaymentIntent::STATUS_SUCCEEDED, \StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_CAPTURE ], true )
		) {
			$isAuthorization = \StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_CAPTURE === $paymentIntent->status;
			$this->stripe->cancelOrRefundPaymentIntent( $paymentIntent->id );
			$this->logger->error( __FUNCTION__, sprintf( 'PaymentIntent %s %s: its product is no longer on the form.', $paymentIntent->id, $isAuthorization ? 'cancelled' : 'refunded' ) );

			$form = $this->db->getCheckoutPaymentFormByName( $postData[ FormModel::PARAM_WPFS_FORM_NAME ] ?? '' );
			if ( is_null( $form ) || ! $this->mailer->sendRemovedProductChargeAdminNotice( $form, $paymentIntent->id, (int) $paymentIntent->amount, $isAuthorization ) ) {
				$this->logger->error( __FUNCTION__, sprintf( 'The site admin could not be told about PaymentIntent %s.', $paymentIntent->id ) );
			}
		}

		return self::createRefundedChargeResult();
	}

	/**
	 * @return ChargeResult
	 */
	private static function createRefundedChargeResult() {
		$chargeResult = new ChargeResult();
		$chargeResult->setSuccess( false );
		/* translators: Banner title of failed transaction */
		$chargeResult->setMessageTitle( __( 'Failed', 'wp-full-stripe-free' ) );
		$chargeResult->setMessage( __( 'Payment refunded.', 'wp-full-stripe-free' ) );

		return $chargeResult;
	}

	/**
	 * @param object{status: string} $popupFormSubmit
	 *
	 * @return bool
	 */
	private static function isSubmitEntryCompleted( $popupFormSubmit ) {
		return in_array( $popupFormSubmit->status, [ self::POPUP_FORM_SUBMIT_STATUS_SUCCESS, self::POPUP_FORM_SUBMIT_STATUS_COMPLETE ], true );
	}

	/**
	 * Takes a submission for this run until it releases it or the request ends.
	 *
	 * @param object{hash: string} $popupFormSubmit
	 * @param int $timeout Seconds to wait for another run holding it.
	 *
	 * @return bool False when another run still holds it.
	 */
	protected function lockSubmitEntry( $popupFormSubmit, $timeout ) {
		return $this->db->lockCheckoutFormSubmit( $popupFormSubmit->hash, $timeout );
	}

	/**
	 * @param object{hash: string} $popupFormSubmit
	 */
	protected function unlockSubmitEntry( $popupFormSubmit ): void {
		$this->db->unlockCheckoutFormSubmit( $popupFormSubmit->hash );
	}

	/**
	 * @param string $formType
	 *
	 * @return array{0: CheckoutPaymentFormModel|CheckoutSubscriptionFormModel|CheckoutDonationFormModel, 1: CheckoutChargeHandler}|null Null for an unknown form type.
	 */
	protected function createFormModelAndChargeHandler( $formType ) {
		if ( Utils::isCheckoutPaymentFormType( $formType ) || Utils::isCheckoutSaveCardFormType( $formType ) ) {
			return [ new CheckoutPaymentFormModel( $this->loggerService ), new CheckoutPaymentChargeHandler( $this->loggerService ) ];
		}
		if ( Utils::isCheckoutSubscriptionFormType( $formType ) ) {
			return [ new CheckoutSubscriptionFormModel( $this->loggerService ), new CheckoutSubscriptionChargeHandler( $this->loggerService ) ];
		}
		if ( Utils::isCheckoutDonationFormType( $formType ) ) {
			return [ new CheckoutDonationFormModel( $this->loggerService ), new CheckoutDonationChargeHandler( $this->loggerService ) ];
		}

		return null;
	}

	/**
	 * True if a record already exists for the session's PaymentIntent, subscription or saved card's SetupIntent. Check before handle()
	 * so notifications fire at most once, even if a prior run left the submission non-terminal. A trial's
	 * subscription has no PaymentIntent, nor does a payment its promotion code made free.
	 *
	 * @param string $formType
	 * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession
	 *
	 * @return bool
	 */
	public function isCheckoutSessionRecorded( $formType, $checkoutSession ) {
		$paymentIntent = $this->findPaymentIntentInCheckoutSession( $checkoutSession );
		// A payment its promotion code made free has none, and is recorded under the session's ID.
		$paymentIntentId = isset( $paymentIntent->id ) ? $paymentIntent->id : CheckoutPaymentChargeHandler::getFreePaymentId( $checkoutSession );
		if ( $this->db->isCheckoutPaymentProcessedByPaymentIntent( $formType, $paymentIntentId ) ) {
			return true;
		}
		// A saved card's SetupIntent: a run can fail after it saved the card, as when an add-on's action throws.
		if ( Utils::isCheckoutSaveCardFormType( $formType ) && isset( $checkoutSession->setup_intent ) ) {
			$setupIntentId = is_object( $checkoutSession->setup_intent ) ? $checkoutSession->setup_intent->id : $checkoutSession->setup_intent;

			return $this->db->isCheckoutSavedCardRecorded( $setupIntentId );
		}
		if ( ! isset( $checkoutSession->subscription ) ) {
			return false;
		}
		$subscriptionId = is_object( $checkoutSession->subscription ) ? $checkoutSession->subscription->id : $checkoutSession->subscription;

		return $this->db->isCheckoutSubscriptionRecorded( $formType, $subscriptionId );
	}

	public function processCheckoutSubmissions() {
		$this->logger->debug( __FUNCTION__, 'CALLED' );

		try {

			if ( ! $this->isRunning() ) {
				$this->start();
				$this->findAndProcessSubmissions();
				$this->stop();
			}

		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Error while processing checkout submissions', $ex );
			$this->stop();
		}

		$this->logger->debug( __FUNCTION__, 'FINISHED' );
	}

	private function start() {
		self::$running = true;
	}

	/**
	 * Deletes the finished submissions old enough, then completes the unfinished ones, oldest first, a page at a time.
	 *
	 * @throws \Exception
	 */
	private function findAndProcessSubmissions() {
		// A session can be paid until it expires a day after it was created. A late or repeated return finds its
		// submission for at least another day.
		$deleted = $this->db->deleteFinishedCheckoutFormSubmits( wp_date( 'Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS ) );
		$this->logger->debug( __FUNCTION__, 'Deleted ' . $deleted . ' Checkout Form Submission(s).' );

		// A run reads a few pages, so the next one carries on after the last submission it read: sessions left open for a
		// day can outnumber them. A pass reads up to the newest submission there was when it started, then the next pass
		// starts again from the oldest, so that a session read while it was open is read again however many submissions
		// come after it. A run starts one pass at most, and reads it up to where the run started, so it reads a
		// submission once.
		$startId = (int) get_option( self::OPTION_LAST_PROCESSED_ID, 0 );
		$passEndId = (int) get_option( self::OPTION_PASS_END_ID, 0 );
		$lastId = $startId;
		$upToId = $passEndId;
		$passStarted = false;
		if ( 0 === $startId || 0 === $passEndId ) {
			$lastId = 0;
			$passEndId = $this->db->findLastCheckoutFormSubmitId();
			$upToId = $passEndId;
			$passStarted = true;
		}
		for ( $iteration = 0; $iteration < $this->iterationCount; $iteration++ ) {
			$popupFormSubmits = $this->findPopupEntries( $lastId, $upToId );
			if ( empty( $popupFormSubmits ) ) {
				if ( $passStarted ) {
					break;
				}
				$lastId = 0;
				$passEndId = $this->db->findLastCheckoutFormSubmitId();
				$upToId = min( $passEndId, $startId );
				$passStarted = true;
				continue;
			}
			$this->logger->debug( __FUNCTION__, 'Found ' . count( $popupFormSubmits ) . ' record(s) to process.' );

			$popupFormSubmitIdsToFaulty = [];
			foreach ( $popupFormSubmits as $popupFormSubmit ) {
				$lastId = (int) $popupFormSubmit->id;

				if ( $popupFormSubmit->processedWithError > $this->processErrorLimit ) {
					array_push( $popupFormSubmitIdsToFaulty, $popupFormSubmit->id );
					$this->logger->debug( __FUNCTION__, 'Proposed to FAULTY.' );
					continue;
				}

				$result = $this->processSinglePopupFormSubmit( $popupFormSubmit );
				if ( self::PROCESS_RESULT_SET_TO_SUCCESS === $result ) {
					$this->logger->debug( __FUNCTION__, 'Checkout Form Submission successfully processed.' );
				} elseif ( self::PROCESS_RESULT_SET_TO_FAILED === $result ) {
					$this->logger->debug( __FUNCTION__, 'Checkout Form Submission processing failed.' );
				} elseif ( self::PROCESS_RESULT_EXPIRED === $result ) {
					$this->logger->debug( __FUNCTION__, 'CheckoutSession expired for Checkout Form Submission.' );

					$this->updateSubmitEntryWithCancelled( $popupFormSubmit );
				} elseif ( self::PROCESS_RESULT_WAIT_FOR_STATUS_CHANGE === $result ) {
					$this->logger->debug( __FUNCTION__, 'Checkout Form Submission skipped, waiting for status change.' );
				} elseif ( self::PROCESS_RESULT_INTERNAL_ERROR === $result ) {
					$this->logger->error( __FUNCTION__, 'Internal error occurred during Checkout Form Submission.' );
				}
			}

			$faulty = $this->updateSubmitEntriesWithInternalError( $popupFormSubmitIdsToFaulty );
			$this->logger->debug( __FUNCTION__, 'Marked as FAULTY ' . $faulty . ' Checkout Form Submission(s).' );
		}
		update_option( self::OPTION_LAST_PROCESSED_ID, $lastId, false );
		update_option( self::OPTION_PASS_END_ID, $passEndId, false );
	}

	/**
	 * @param int $afterId
	 * @param int $upToId The last ID to read.
	 *
	 * @return list<CheckoutFormSubmit>
	 */
	private function findPopupEntries( $afterId, $upToId ) {
		$liveMode = $this->options->get( Options::OPTION_API_MODE ) === Main::STRIPE_API_MODE_LIVE;

		return $this->db->findUnfinishedCheckoutFormSubmits( $liveMode, $afterId, $this->entryCount, $upToId );
	}

	/**
	 * @param CheckoutFormSubmit $popupFormSubmit
	 *
	 * @return int
	 * @throws \Exception
	 */
	private function processSinglePopupFormSubmit( $popupFormSubmit ) {
		try {
			if ( isset( $popupFormSubmit->checkoutSessionId ) ) {
				if ( ! $this->lockSubmitEntry( $popupFormSubmit, 0 ) ) {
					$this->logger->debug( __FUNCTION__, 'Skipping: the return callback is completing the submission.' );

					return self::PROCESS_RESULT_WAIT_FOR_STATUS_CHANGE;
				}
				try {
					$result = $this->processLockedPopupFormSubmit( $popupFormSubmit );
				} finally {
					$this->unlockSubmitEntry( $popupFormSubmit );
				}
				if ( ! is_null( $result ) ) {
					return $result;
				}
			}

			// tnagy check expiration
			$expirationDate = time() - 24 * 60 * 60;
			$creationDate = strtotime( $popupFormSubmit->created );

			if ( $creationDate < $expirationDate ) {
				return self::PROCESS_RESULT_EXPIRED;
			}

		} catch ( \Throwable $ex ) {
			// A PHP error too, so that one submission can't stop every run before it's marked faulty.
			$this->updateSubmitEntryWithErrorCount( $popupFormSubmit, $ex->getMessage() );
			$this->logger->error( __FUNCTION__, 'Error while processing checkout form submit', $ex );

			return self::PROCESS_RESULT_INTERNAL_ERROR;
		}

		return self::PROCESS_RESULT_WAIT_FOR_STATUS_CHANGE;
	}

	/**
	 * Completes a submission this run holds, once the customer finished its session.
	 *
	 * @param object $popupFormSubmit
	 *
	 * @return int|null The result, null while the customer hasn't finished the session.
	 * @throws \Exception
	 */
	private function processLockedPopupFormSubmit( $popupFormSubmit ) {
		// Skip if the return callback completed it since this run listed it (avoids duplicate emails).
		$freshSubmit = $this->retrieveSubmitEntry( $popupFormSubmit->hash );
		$freshStatus = is_object( $freshSubmit ) ? $freshSubmit->status : null;
		if (
			! in_array( $freshStatus, [
				self::POPUP_FORM_SUBMIT_STATUS_CREATED,
				self::POPUP_FORM_SUBMIT_STATUS_PENDING,
			], true )
		) {
			$this->logger->debug(
				__FUNCTION__,
				'Skipping: submission already handled or missing, status=' . ( is_null( $freshStatus ) ? 'not found' : $freshStatus )
			);
			return self::PROCESS_RESULT_WAIT_FOR_STATUS_CHANGE;
		}

		$checkoutSession = $this->retrieveCheckoutSession( $popupFormSubmit->checkoutSessionId );
		self::keepSessionSubscription( $checkoutSession );

		// Stripe sends the customer back once the session is complete, as the return records it, whatever it charged:
		// a trial or a saved card charges nothing, and a bank debit is still processing.
		if ( ! isset( $checkoutSession->status ) || \StripeWPFS\Stripe\Checkout\Session::STATUS_COMPLETE !== $checkoutSession->status ) {
			return null;
		}

		// Already processed by a prior run: resolve without resending notifications.
		if ( $this->isCheckoutSessionRecorded( $popupFormSubmit->formType, $checkoutSession ) ) {
			$this->updateSubmitEntryWithAlreadyProcessed( $popupFormSubmit );
			$this->logger->debug( __FUNCTION__, 'Checkout session already recorded: ' . $checkoutSession->id . ', skipping notifications.' );

			return self::PROCESS_RESULT_SET_TO_SUCCESS;
		}

		$formModelAndChargeHandler = $this->createFormModelAndChargeHandler( $popupFormSubmit->formType );
		if ( is_null( $formModelAndChargeHandler ) ) {
			$errorMessage = 'Unknown formType=' . $popupFormSubmit->formType;
			$this->updateSubmitEntryWithErrorCount( $popupFormSubmit, $errorMessage );
			$this->logger->error( __FUNCTION__, $errorMessage );

			return self::PROCESS_RESULT_INTERNAL_ERROR;
		}

		list( $formModel, $checkoutChargeHandler ) = $formModelAndChargeHandler;
		$chargeResult = $this->chargeCheckoutSession( $formModel, $checkoutChargeHandler, $popupFormSubmit, $checkoutSession );
		if ( ! $chargeResult->isSuccess() ) {
			$this->updateSubmitEntryWithFailed( $popupFormSubmit, $chargeResult->getMessageTitle(), $chargeResult->getMessage() );

			return self::PROCESS_RESULT_SET_TO_FAILED;
		}
		$this->updateSubmitEntryWithSuccess( $popupFormSubmit, $chargeResult->getMessageTitle(), $chargeResult->getMessage() );

		return self::PROCESS_RESULT_SET_TO_SUCCESS;
	}

	/**
	 * Keeps the session's subscription for the fee sync before any other Stripe call: until a handler records it, a
	 * failed call loses it.
	 *
	 * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession With its subscription expanded.
	 */
	private static function keepSessionSubscription( $checkoutSession ): void {
		if ( isset( $checkoutSession->subscription ) && is_object( $checkoutSession->subscription ) ) {
			FeeSync::keepUntracked( $checkoutSession->subscription );
		}
	}

	/**
	 * @param $checkoutSessionId
	 *
	 * @return \StripeWPFS\Stripe\Checkout\Session
	 */
	public function retrieveCheckoutSession( $checkoutSessionId ) {
		$checkoutSession = $this->stripe->retrieveCheckoutSessionWithParams(
			$checkoutSessionId,
			[
				'expand' => [
					'customer',
					'customer.tax_ids',
					'payment_intent',
					'payment_intent.payment_method',
					'setup_intent',
					'setup_intent.payment_method',
					'subscription',
					'subscription.latest_invoice',
					'subscription.latest_invoice.payments',
					'subscription.pending_setup_intent',
					'subscription.default_payment_method',
					'line_items',
					'line_items.data.discounts',
					'line_items.data.taxes',
					'line_items.data.price.product'
				]
			]
		);

		return $checkoutSession;
	}

	/**
	 * @param $checkoutSession
	 *
	 * @return \StripeWPFS\Stripe\PaymentIntent|null
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	public function findPaymentIntentInCheckoutSession( $checkoutSession ) {
		$paymentIntent = null;
		if ( isset( $checkoutSession ) ) {
			$paymentIntent = $this->retrieveStripePaymentIntentByCheckoutSession( $checkoutSession );
			if ( is_null( $paymentIntent ) ) {
				$stripeSubscription = $this->retrieveStripeSubscriptionByCheckoutSession( $checkoutSession );
				$paymentIntent = $this->findPaymentIntentInSubscription( $stripeSubscription );
			}
		}

		return $paymentIntent;
	}

	/**
	 * @param $checkoutSession
	 *
	 * @return \StripeWPFS\Stripe\PaymentIntent|null
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	public function retrieveStripePaymentIntentByCheckoutSession( $checkoutSession ) {
		$stripePaymentIntent = null;
		if ( isset( $checkoutSession ) ) {
			if ( isset( $checkoutSession->payment_intent ) ) {
				$stripePaymentIntent = $this->expandedOrRetrieved( $checkoutSession->payment_intent, [ $this->stripe, 'retrievePaymentIntent' ] );
			} else {
				$this->logger->error( __FUNCTION__, 'Error while retrieving PaymentIntent from CheckoutSession, paymentIntent is missing.' );
			}
		} else {
			$this->logger->error( __FUNCTION__, 'Error while retrieving PaymentIntent from CheckoutSession, checkoutSession is missing.' );
		}

		return $stripePaymentIntent;
	}

	/**
	 * @param $checkoutSession
	 *
	 * @return \StripeWPFS\Stripe\Subscription|null
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	public function retrieveStripeSubscriptionByCheckoutSession( $checkoutSession ) {
		$stripeSubscription = null;
		if ( isset( $checkoutSession ) ) {
			if ( isset( $checkoutSession->subscription ) ) {
				$subscriptionId = is_object( $checkoutSession->subscription ) ? $checkoutSession->subscription->id : $checkoutSession->subscription;

				$stripeSubscription = $this->stripe->retrieveSubscriptionWithParams(
					$subscriptionId,
					[
						'expand' => [
							'latest_invoice',
							'latest_invoice.payments',
							'pending_setup_intent',
							'default_payment_method',
							'discount.promotion_code'
						]
					]
				);
			}
		}

		return $stripeSubscription;
	}

	/**
	 * @param $stripeSubscription
	 *
	 * @return \StripeWPFS\Stripe\PaymentIntent|null
	 */
	public function findPaymentIntentInSubscription( $stripeSubscription ) {
		$paymentIntent = null;
		if ( ! isset( $stripeSubscription ) || ! isset( $stripeSubscription->latest_invoice ) ) {
			return $paymentIntent;
		}

		$stripeInvoice = $stripeSubscription->latest_invoice;

		if ( ! is_object( $stripeInvoice ) || ! isset( $stripeInvoice->payments ) ) {
			$invoiceId = is_object( $stripeInvoice ) && isset( $stripeInvoice->id ) ? $stripeInvoice->id : $stripeInvoice;
			try {
				$stripeInvoice = $this->stripe->retrieveInvoiceWithParams(
					$invoiceId,
					[ 'expand' => [ 'payments' ] ]
				);
			} catch ( \Exception $ex ) {
				$this->logger->error( __FUNCTION__, 'Error while retrieving invoice for subscription', $ex );
				return $paymentIntent;
			}
		}

		$paymentIntentId = null;

		if ( isset( $stripeInvoice->payments ) && isset( $stripeInvoice->payments->data ) ) {
			foreach ( $stripeInvoice->payments->data as $payment ) {
				if (
					isset( $payment->payment ) &&
					isset( $payment->payment->type ) &&
					'payment_intent' === $payment->payment->type &&
					isset( $payment->payment->payment_intent )
				) {
					$paymentIntentId = $payment->payment->payment_intent;
					break;
				}
			}
		}

		if ( is_null( $paymentIntentId ) && isset( $stripeInvoice->payment_intent ) ) {
			if ( is_object( $stripeInvoice->payment_intent ) && isset( $stripeInvoice->payment_intent->id ) ) {
				return $stripeInvoice->payment_intent;
			}
			$paymentIntentId = $stripeInvoice->payment_intent;
		}

		if ( ! empty( $paymentIntentId ) ) {
			try {
				$paymentIntent = $this->stripe->retrievePaymentIntent( $paymentIntentId );
			} catch ( \Exception $ex ) {
				$this->logger->error( __FUNCTION__, 'Error while retrieving PaymentIntent from subscription invoice', $ex );
			}
		}

		return $paymentIntent;
	}

	/**
	 * @param $popupFormSubmit
	 * @param $lastMessageTitle
	 * @param $lastMessage
	 *
	 * @return bool|false|int
	 * @throws \Exception
	 */
	public function updateSubmitEntryWithSuccess( $popupFormSubmit, $lastMessageTitle, $lastMessage ) {
		if ( is_null( $popupFormSubmit ) ) {
			return false;
		}

		return $this->db->update_popup_form_submit_by_hash(
			$popupFormSubmit->hash,
			[
				'status' => self::POPUP_FORM_SUBMIT_STATUS_SUCCESS,
				'lastMessageTitle' => $lastMessageTitle,
				'lastMessage' => $lastMessage
			]
		);
	}

	/**
	 * Marks a submission whose payment a prior run recorded, without the charge result that run had.
	 *
	 * @param object{hash: string} $popupFormSubmit
	 *
	 * @return bool|false|int
	 * @throws \Exception
	 */
	private function updateSubmitEntryWithAlreadyProcessed( $popupFormSubmit ) {
		return $this->updateSubmitEntryWithSuccess(
			$popupFormSubmit,
			/* translators: Banner title of successful transaction */
			__( 'Success', 'wp-full-stripe-free' ),
			/* translators: Banner message of successful payment */
			__( 'Payment Successful!', 'wp-full-stripe-free' )
		);
	}

	/**
	 * @param $popupFormSubmit
	 * @param $lastMessageTitle
	 * @param $lastMessage
	 *
	 * @return bool|false|int
	 * @throws \Exception
	 */
	public function updateSubmitEntryWithFailed( $popupFormSubmit, $lastMessageTitle = null, $lastMessage = null ) {
		if ( is_null( $popupFormSubmit ) ) {
			return false;
		}

		if ( is_null( $lastMessageTitle ) ) {
			$lastMessageTitle =
				/* translators: Banner title shown on the form when a Checkout payment failed */
				__( 'Failed', 'wp-full-stripe-free' );
		}
		if ( is_null( $lastMessage ) ) {
			$lastMessage =
				/* translators: Banner message shown on the form when a Checkout payment failed */
				__( 'Payment failed!', 'wp-full-stripe-free' );
		}

		return $this->db->update_popup_form_submit_by_hash(
			$popupFormSubmit->hash,
			[
				'status' => self::POPUP_FORM_SUBMIT_STATUS_FAILED,
				'lastMessageTitle' => $lastMessageTitle,
				'lastMessage' => $lastMessage
			]
		);
	}

	/**
	 * @param $popupFormSubmit
	 * @param $errorMessage
	 *
	 * @return bool|false|int
	 * @throws \Exception
	 */
	public function updateSubmitEntryWithErrorCount( $popupFormSubmit, $errorMessage = null ) {
		if ( is_null( $popupFormSubmit ) ) {
			return false;
		}

		return $this->db->update_popup_form_submit_by_hash( $popupFormSubmit->hash, self::getErrorCountFields( $popupFormSubmit, $errorMessage ) );
	}

	/**
	 * Leaves a submission whose completion failed on the customer's return, or that the return can't read in the site's
	 * mode, for the recovery, and tells the customer until it completes it.
	 *
	 * @param object{hash: string, processedWithError: string|null} $popupFormSubmit
	 * @param string|null $errorMessage The error to count, none when null.
	 *
	 * @return bool|false|int
	 * @throws \Exception
	 */
	private function updateSubmitEntryForRecovery( $popupFormSubmit, $errorMessage = null ) {
		return $this->db->update_popup_form_submit_by_hash(
			$popupFormSubmit->hash,
			( is_null( $errorMessage ) ? [] : self::getErrorCountFields( $popupFormSubmit, $errorMessage ) ) + [
				/* translators: Banner title shown on the form while a Checkout payment is being confirmed */
				'lastMessageTitle' => __( 'Thanks!', 'wp-full-stripe-free' ),
				/* translators: Banner message shown on the form while a Checkout payment is being confirmed */
				'lastMessage' => __( 'We\'re confirming your payment and will email you once it\'s done.', 'wp-full-stripe-free' ),
			]
		);
	}

	/**
	 * @param object{processedWithError: string|null} $popupFormSubmit
	 * @param string|null $errorMessage
	 *
	 * @return array{processedWithError: int, errorMessage: string|null}
	 */
	private static function getErrorCountFields( $popupFormSubmit, $errorMessage ) {
		return [
			'processedWithError' => $popupFormSubmit->processedWithError + 1,
			// The update fails for a message longer than the column.
			'errorMessage' => is_null( $errorMessage ) ? null : mb_substr( $errorMessage, 0, self::ERROR_MESSAGE_MAX_LENGTH ),
		];
	}

	/**
	 * @param $popupFormSubmit
	 *
	 * @return false|int
	 * @throws \Exception
	 */
	public function updateSubmitEntryWithCancelled( $popupFormSubmit ) {
		if ( is_null( $popupFormSubmit ) ) {
			return false;
		}

		return $this->db->update_popup_form_submit_by_hash(
			$popupFormSubmit->hash,
			[
				'status' => self::CHECKOUT_SESSION_STATUS_CANCELLED,
				'lastMessageTitle' =>
					/* translators: Banner title of cancelled transaction */
					__( 'Cancelled', 'wp-full-stripe-free' ),
				'lastMessage' =>
					/* translators: Banner message of cancelled transaction */
					__( 'The customer has cancelled the payment.', 'wp-full-stripe-free' )
			]
		);
	}

	/**
	 * @param $popupFormSubmit
	 *
	 * @return bool|false|int
	 * @throws \Exception
	 */
	public function updateSubmitEntryWithComplete( $popupFormSubmit ) {
		if ( is_null( $popupFormSubmit ) ) {
			return false;
		}

		return $this->db->update_popup_form_submit_by_hash(
			$popupFormSubmit->hash,
			[
				'status' => self::POPUP_FORM_SUBMIT_STATUS_COMPLETE
			]
		);
	}

	/**
	 * @param $popupFormSubmitIdsToInternalError
	 *
	 * @return int
	 * @throws \Exception
	 */
	private function updateSubmitEntriesWithInternalError( $popupFormSubmitIdsToInternalError ) {
		$updated = 0;
		if (
			isset( $popupFormSubmitIdsToInternalError )
			&& is_array( $popupFormSubmitIdsToInternalError )
			&& sizeof( $popupFormSubmitIdsToInternalError ) > 0
		) {
			$updated = $this->db->update_popup_form_submits_with_status_by_id(
				CheckoutSubmissionService::POPUP_FORM_SUBMIT_STATUS_INTERNAL_ERROR,
				$popupFormSubmitIdsToInternalError
			);
		}

		return $updated;
	}

	private function stop() {
		self::$running = false;
	}

	/**
	 * @param $checkoutSession
	 *
	 * @return \StripeWPFS\Stripe\SetupIntent|null
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	public function findSetupIntentInCheckoutSession( $checkoutSession ) {
		$setupIntent = null;
		if ( isset( $checkoutSession ) ) {
			$setupIntent = $this->retrieveStripeSetupIntentByCheckoutSession( $checkoutSession );
			if ( is_null( $setupIntent ) ) {
				$stripeSubscription = $this->retrieveStripeSubscriptionByCheckoutSession( $checkoutSession );
				$setupIntent = $this->findSetupIntentInSubscription( $stripeSubscription );
			}
		}

		return $setupIntent;
	}

	/**
	 * @param $checkoutSession
	 *
	 * @return \StripeWPFS\Stripe\SetupIntent|null
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	public function retrieveStripeSetupIntentByCheckoutSession( $checkoutSession ) {
		$stripeSetupIntent = null;
		if ( isset( $checkoutSession ) ) {
			if ( isset( $checkoutSession->setup_intent ) ) {
				$stripeSetupIntent = $this->expandedOrRetrieved( $checkoutSession->setup_intent, [ $this->stripe, 'retrieveSetupIntent' ] );
			}
		}

		return $stripeSetupIntent;
	}

	/**
	 * @param $stripeSubscription
	 *
	 * @return \StripeWPFS\Stripe\SetupIntent|null
	 */
	public function findSetupIntentInSubscription( $stripeSubscription ) {
		$setupIntent = null;
		if ( isset( $stripeSubscription ) ) {
			if ( isset( $stripeSubscription->pending_setup_intent ) ) {
				$setupIntent = $this->expandedOrRetrieved( $stripeSubscription->pending_setup_intent, [ $this->stripe, 'retrieveSetupIntent' ] );
			}
		}

		return $setupIntent;
	}

	/**
	 * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession
	 *
	 * @return \StripeWPFS\Stripe\Customer|null
	 */
	public function retrieveStripeCustomerByCheckoutSession( $checkoutSession ) {
		$stripeCustomer = null;
		if ( isset( $checkoutSession ) ) {
			if ( isset( $checkoutSession->customer ) ) {
				$stripeCustomer = $this->expandedOrRetrieved( $checkoutSession->customer, [ $this->stripe, 'retrieveCustomer' ] );
			} else {
				$this->logger->error( __FUNCTION__, 'Error while retrieving Customer from CheckoutSession, customer is missing.' );
			}
		} else {
			$this->logger->error( __FUNCTION__, 'Error while retrieving Customer from CheckoutSession, checkoutSession is missing.' );
		}

		return $stripeCustomer;
	}

	/**
	 * @param $setupIntent
	 *
	 * @return \StripeWPFS\Stripe\PaymentMethod|null
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	public function retrieveStripePaymentMethodBySetupIntent( $setupIntent ) {
		$stripePaymentMethod = null;
		if ( isset( $setupIntent ) ) {
			if ( isset( $setupIntent->payment_method ) ) {
				$stripePaymentMethod = $this->expandedOrRetrieved( $setupIntent->payment_method, [ $this->stripe, 'retrievePaymentMethod' ] );
			}
		}

		return $stripePaymentMethod;
	}

	/**
	 * @param $paymentIntent
	 *
	 * @return \StripeWPFS\Stripe\PaymentMethod|null
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	public function retrieveStripePaymentMethodByPaymentIntent( $paymentIntent ) {
		$stripePaymentMethod = null;
		if ( isset( $paymentIntent ) ) {
			if ( isset( $paymentIntent->payment_method ) ) {
				$stripePaymentMethod = $this->expandedOrRetrieved( $paymentIntent->payment_method, [ $this->stripe, 'retrievePaymentMethod' ] );
			}
		}

		return $stripePaymentMethod;
	}

	/**
	 * @param \StripeWPFS\Stripe\PaymentMethod $paymentMethod
	 *
	 * @return null|\StripeWPFS\Stripe\Customer
	 */
	public function retrieveStripeCustomerByPaymentMethod( $paymentMethod ) {
		$stripeCustomer = null;
		if ( isset( $paymentMethod ) ) {
			if ( isset( $paymentMethod->customer ) ) {
				$stripeCustomer = $this->expandedOrRetrieved( $paymentMethod->customer, [ $this->stripe, 'retrieveCustomer' ] );
			}
		}

		return $stripeCustomer;
	}

	/**
	 * The Stripe object its parent holds expanded, or the one retrieved by the id the parent holds instead.
	 *
	 * @template TObject of \StripeWPFS\Stripe\StripeObject
	 * @param TObject|string $expandedOrId
	 * @param callable(string): TObject $retrieve
	 *
	 * @return TObject
	 */
	private function expandedOrRetrieved( $expandedOrId, callable $retrieve ) {
		if ( is_object( $expandedOrId ) && isset( $expandedOrId->id ) ) {
			return $expandedOrId;
		}

		return $retrieve( $expandedOrId );
	}
}
