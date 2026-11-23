<?php

namespace ThemeIsle\WPFullPay;

use ThemeIsle\WPFullPay\Admin\StripeTaxBehaviorNotice;
use ThemeIsle\WPFullPay\Context\StaticContext;
use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Customer\CreateOneTimeInvoiceContext;
use ThemeIsle\WPFullPay\Customer\CreateOneTimeInvoiceOptions;
use ThemeIsle\WPFullPay\Customer\CreateSubscriptionContext;
use ThemeIsle\WPFullPay\Customer\CreateSubscriptionOptions;
use ThemeIsle\WPFullPay\Customer\SubscriptionContextCreator;
use ThemeIsle\WPFullPay\Exceptions\PlatformRequestException;
use ThemeIsle\WPFullPay\Exceptions\StripeTaxBehaviorException;
use ThemeIsle\WPFullPay\Exceptions\UserFriendlyException;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Platform\SiteCredential;
use ThemeIsle\WPFullPay\Pricing\RecoveryFee;

/**
 * Class Stripe
 *
 * deals with calls to Stripe API
 *
 */
class Stripe {
	use LoggerAddOn;
	use StaticContextAddOn;

	const DESIRED_STRIPE_API_VERSION = '2025-06-30.basil';
	/** Metadata marking a fee recovery line: on the product of a Checkout fee, and on a fee item a plan change repriced. */
	const METADATA_KEY_RECOVERY_FEE_ITEM = 'wpfs_recovery_fee';
	/** Stripe Tax's code for a product that is never taxed. */
	const TAX_CODE_NONTAXABLE = 'txcd_00000000';

	/** Seconds to wait for a fee sync page, which can update 20 subscriptions on Stripe. */
	const FEE_SYNC_TIMEOUT = 20;

	/**
	 * @var string
	 */
	const INVALID_NUMBER_ERROR = 'invalid_number';
	/**
	 * @var string
	 */
	const INVALID_NUMBER_ERROR_EXP_MONTH = 'invalid_number_exp_month';
	/**
	 * @var string
	 */
	const INVALID_NUMBER_ERROR_EXP_YEAR = 'invalid_number_exp_year';
	/**
	 * @var string
	 */
	const INVALID_EXPIRY_MONTH_ERROR = 'invalid_expiry_month';
	/**
	 * @var string
	 */
	const INVALID_EXPIRY_YEAR_ERROR = 'invalid_expiry_year';
	/**
	 * @var string
	 */
	const INVALID_CVC_ERROR = 'invalid_cvc';
	/**
	 * @var string
	 */
	const INCORRECT_NUMBER_ERROR = 'incorrect_number';
	/**
	 * @var string
	 */
	const EXPIRED_CARD_ERROR = 'expired_card';
	/**
	 * @var string
	 */
	const INCORRECT_CVC_ERROR = 'incorrect_cvc';
	/**
	 * @var string
	 */
	const INCORRECT_ZIP_ERROR = 'incorrect_zip';
	/**
	 * @var string
	 */
	const CARD_DECLINED_ERROR = 'card_declined';
	/**
	 * @var string
	 */
	const MISSING_ERROR = 'missing';
	/**
	 * @var string
	 */
	const PROCESSING_ERROR = 'processing_error';
	/**
	 * @var string
	 */
	const MISSING_PAYMENT_INFORMATION = 'missing_payment_information';
	/**
	 * @var string
	 */
	const COULD_NOT_FIND_PAYMENT_INFORMATION = 'Could not find payment information';

	/* @var $stripe \StripeWPFS\Stripe\StripeClient */
	public $stripe;

	/* @var Options */
	private $options;

	/* @var string */
	private $apiMode;

	/* @var bool */
	private $usingWpTestPlatform;

	/* @var bool */
	private $usingWpLivePlatform;

	/* @var string */
	private $liveStripeAcountId;

	/* @var string */
	private $testStripeAcountId;

	/* @var string */
	private $connectUrl;

	/* @var  string */
	private $userVersion;

	/**
	 * Stripe constructor.
	 *
	 * @param $token
	 *
	 * @throws \Exception
	 */
	public function __construct( $token, $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_STRIPE );
		$this->options = new Options();

		$this->initStaticContext();
		$this->apiMode = $this->options->get( Options::OPTION_API_MODE );
		$this->usingWpTestPlatform = $this->options->get( Options::OPTION_USE_WP_TEST_PLATFORM );
		$this->usingWpLivePlatform = $this->options->get( Options::OPTION_USE_WP_LIVE_PLATFORM );
		$this->liveStripeAcountId = $this->options->get( Options::OPTION_LIVE_ACCOUNT_ID );
		$this->testStripeAcountId = $this->options->get( Options::OPTION_TEST_ACCOUNT_ID );
		$this->connectUrl = $this->options->getFunctionsUrl();
		$this->userVersion = Main::get_user_version();

		if ( ! is_null( $token ) && ! empty( $token ) ) {
			try {
				$this->stripe = self::createStripeClient( $token );
			} catch (\Exception $ex) {
				$this->logger->error( __FUNCTION__, 'Error while initializing the Stripe client', $ex );
			}
		}
	}

	/**
	 * Whether the platform may waive its commission. Decided per request:
	 * this service is constructed while plugins load, before the SDK
	 * registers its license filter on init, so a boot-time verdict would be
	 * stale for every payment that follows.
	 *
	 * @return bool
	 */
	private function hasValidLicense() {
		return License::has_valid_key_for_fees();
	}

	/**
	 * @param string $token
	 * @return \StripeWPFS\Stripe\StripeClient
	 */
	public static function createStripeClient( $token ) {
		return new \StripeWPFS\Stripe\StripeClient( [ 
			"api_key" => $token,
			"stripe_version" => self::DESIRED_STRIPE_API_VERSION
		] );
	}

	/**
	 * Makes sure the Stripe client is usable before it is dereferenced.
	 *
	 * The constructor leaves it null when no API token is stored or the client cannot be built,
	 * so every direct-client branch has to check it.
	 *
	 * @return void
	 * @throws UserFriendlyException
	 */
	private function assertStripeClient(): void {
		if ( is_null( $this->stripe ) ) {
			throw new UserFriendlyException( __( 'Stripe client is not initialized.', 'wp-full-stripe-free' ) );
		}
	}

	/**
	 * @param StaticContext $context
	 * @param $liveMode
	 * @return mixed
	 */
	public static function getStripeAuthenticationTokenByMode( $context, $liveMode ) {
		$optionKey = $liveMode ? Options::OPTION_API_LIVE_SECRET_KEY : Options::OPTION_API_TEST_SECRET_KEY;

		return $context->getOptions()->get( $optionKey );
	}

	/**
	 * @param StaticContext $context
	 * @return string
	 */
	public static function getStripeAuthenticationToken( $context ) {
		return Stripe::getStripeAuthenticationTokenByMode( $context, self::isStripeApiInLiveMode( $context ) );
	}

	/**
	 * @param StaticContext $context
	 * @return bool
	 */
	public static function isStripeApiInLiveMode( $context ) {
		return $context->getOptions()->get( Options::OPTION_API_MODE ) === Main::STRIPE_API_MODE_LIVE;
	}

	/**
	 * @return mixed
	 */
	public static function getStripeTestAuthenticationToken( $context ) {
		return $context->getOptions()->get( Options::OPTION_API_TEST_SECRET_KEY );
	}

	/**
	 * @return mixed
	 */
	public static function getStripeLiveAuthenticationToken( $context ) {
		return $context->getOptions()->get( Options::OPTION_API_LIVE_SECRET_KEY );
	}

	/**
	 * Calls the platform API. Sends this site's credential for the request's mode when it has one, or
	 * $credential instead: another credential to check or revoke, or '' to send none.
	 *
	 * @param string               $method     'get', 'post' or 'delete'.
	 * @param string               $url        Path and query, always with `mode=`.
	 * @param mixed                $body
	 * @param array<string,string> $headers    Headers to send as well, such as an idempotency key.
	 * @param string|null          $credential
	 * @param int                  $timeout    Seconds to wait for the answer.
	 * @return mixed The platform's `data`.
	 * @throws PlatformRequestException
	 */
	function remoteRequest( $method, $url, $body = null, $headers = [], ?string $credential = null, int $timeout = 10 ) {
		$mode = self::getRequestMode( $url );
		$requestHeaders = $headers;
		$headers[ SiteCredential::VERSION_HEADER ] = Main::VERSION;
		$usesStoredCredential = null === $credential;
		$originalBody = $body;
		$credential = $credential ?? ( null !== $mode ? SiteCredential::get( $mode ) : null );
		if ( null !== $credential && '' !== $credential ) {
			$headers[ SiteCredential::HEADER ] = $credential;
		}

		// A redirect must not carry the credential to another address.
		$response = null;
		if ( $method === 'get' ) {
			$response = wp_remote_get( $this->connectUrl . $url,
				[
					'timeout' => $timeout,
					'headers' => $headers,
					'redirection' => 0,
				] );
		} else if ( $method === 'post' ) {
			$payload = null;
			// if there is a valid license, we need to send the license id and user id to the cloud function
			if ( is_array( $body ) && isset( $body["validLicense"] ) && $body["validLicense"] === true ) {
				// add the license id and user id to the body before sending it
				if ( License::get_user_id() ) {
					$license_user_id = License::get_user_id();
					$body["licenseUserId"] = $license_user_id;
				}
				if ( License::get_key() ) {
					$license_id = License::get_key();
					$body["licenseId"] = $license_id;
				}
			}
			if ( isset( $body ) && ! empty( $body ) ) {
				if ( isset( $body['amount'] ) ) {
					// make the amount an integer to avoid issues when encoding the payload
					$body['amount'] = intval( $body['amount'] );
				}
				$payload = json_encode( $body );
				if ( $payload == false ) {
					$this->logger->error(
						__FUNCTION__,
						'Error while encoding the payload: ' . json_last_error_msg()
					);
					throw new UserFriendlyException(
						sprintf(
							/* translators: %s: JSON encoding error message */
							__( 'Error while encoding the payload: %s', 'wp-full-stripe-free' ),
							json_last_error_msg()
						)
					);
				}
			}
			$response = wp_remote_post(
				$this->connectUrl . $url,
				[
					'headers' => array_merge( $headers, [ 'Content-Type' => 'application/json; charset=utf-8' ] ),
					'body' => $payload,
					'timeout' => $timeout,
					'redirection' => 0,
				]
			);
		} else if ( $method === 'delete' ) {
			$response = wp_remote_request( $this->connectUrl . $url, [ 'method' => 'DELETE', 'timeout' => $timeout, 'headers' => $headers, 'redirection' => 0 ] );
		}

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$this->logger->error( __FUNCTION__, 'Transport error: ' . $error_message );
			throw new PlatformRequestException(
				sprintf(
					/* translators: %s: transport error message */
					__( 'API request failed: %s', 'wp-full-stripe-free' ),
					$error_message
				)
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$decoded_body  = json_decode( $response_body ); // decode as object

		if ( $response_code !== 200 || ! is_object( $decoded_body ) || empty( $decoded_body->success ) ) {
			$error_detail = isset( $decoded_body->error ) ? $decoded_body->error : 'Unknown error';
			$context      = isset( $decoded_body->context ) ? ' (' . $decoded_body->context . ')' : '';
			$this->logger->error(
				__FUNCTION__,
				"API error {$context}: " . $error_detail
			);

			$user_detail = isset( $decoded_body->error ) ? $decoded_body->error : __( 'Unknown error', 'wp-full-stripe-free' );
			$status = (int) $response_code;
			$code = is_object( $decoded_body ) && isset( $decoded_body->code ) && is_string( $decoded_body->code ) ? $decoded_body->code : '';
			$retryAfter = wp_remote_retrieve_header( $response, 'retry-after' );
			$retryAfter = is_string( $retryAfter ) && ctype_digit( $retryAfter ) ? (int) $retryAfter : 0;
			$exception = isset( $decoded_body->context )
				? new PlatformRequestException(
					sprintf(
						/* translators: %1$s: context reported by the API, %2$s: error message */
						__( 'API error (%1$s): %2$s', 'wp-full-stripe-free' ),
						$decoded_body->context,
						$user_detail
					),
					$status,
					$code,
					null,
					$retryAfter
				)
				: new PlatformRequestException(
					sprintf(
						/* translators: %s: error message */
						__( 'API error: %s', 'wp-full-stripe-free' ),
						$user_detail
					),
					$status,
					$code,
					null,
					$retryAfter
				);

			// Only a refusal of the credential this site uses asks the admin to confirm, never one being checked.
			if ( $exception->isCredentialRefusal() && null !== $mode && $usesStoredCredential ) {
				// A Confirm may have finished while this request ran with the credential it replaced. The
				// refused request never reached Stripe, so it is sent once more with the current credential.
				$current = SiteCredential::getStored( $mode );
				if ( null !== $current && $current !== $credential ) {
					return $this->remoteRequest( $method, $url, $originalBody, $requestHeaders, $current, $timeout );
				}
				SiteCredential::markRefused( $mode );
			}

			throw $exception;
		}

		if ( null !== $mode && $usesStoredCredential && null !== $credential ) {
			SiteCredential::clearRefused( $mode );
		}

		return $decoded_body->data;
	}

	/**
	 * Builds a query string.
	 *
	 * @param array $params Query parameters.
	 * @return string Query string.
	 */
	public function build_query( array $params ): string {
		$parts = [];

		foreach ( $params as $key => $value ) {
			if ( is_array( $value ) ) {
				// Repeat key=value for each array item
				foreach ( $value as $item ) {
					$parts[] = urlencode( $key ) . '=' . urlencode( $item );
				}
			} else {
				// Handle scalar values normally
				$parts[] = urlencode( $key ) . '=' . urlencode( $value );
			}
		}

		return implode( '&', $parts );
	}

	/**
	 * @param string $stripeCustomerId
	 * @param string $stripePlanId
	 * @param float $amount
	 * @param array<string, string> $metadata
	 * @param array<int, array<string, mixed>> $additionalItems Subscription items billed after the plan, such as a recovery fee.
	 * @param string|null $idempotencyKey Makes Stripe answer a request sent again with the subscription it created.
	 *
	 * @return \StripeWPFS\Stripe\Subscription
	 * @throws \Exception
	 */
	public function subscribeCustomerToPlan( $stripeCustomerId, $stripePlanId, $amount, $metadata = [], $additionalItems = [], $idempotencyKey = null ) {
		$subscriptionData = [
			'customer' => $stripeCustomerId,
			'items' => array_merge(
				[
					[
						'price' => $stripePlanId,
						'quantity' => $amount
					]
				],
				$additionalItems
			),
			'expand' => [
				'latest_invoice',
				'latest_invoice.payments',
				'latest_invoice.payment_intent',
				'pending_setup_intent'
			],
		];

		if ( ! empty( $metadata ) ) {
			$subscriptionData['metadata'] = $metadata;
		}

		$headers = empty( $idempotencyKey ) ? [] : [ 'Idempotency-Key' => $idempotencyKey ];

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$subscriptionData = array_merge( $subscriptionData, [ 'validLicense' => $this->hasValidLicense() ] );
			$stripeSubscription = $this->remoteRequest(
				'post',
				'/subscriptions?mode=test&accountId=' . $this->testStripeAcountId . '&apiVersion=' . $this->userVersion,
				$subscriptionData,
				$headers
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$subscriptionData = array_merge( $subscriptionData, [ 'validLicense' => $this->hasValidLicense() ] );
			$stripeSubscription = $this->remoteRequest(
				'post',
				'/subscriptions?mode=live&accountId=' . $this->liveStripeAcountId . '&apiVersion=' . $this->userVersion,
				$subscriptionData,
				$headers
			);
		} else {
			$options = empty( $idempotencyKey ) ? [] : [ 'idempotency_key' => $idempotencyKey ];
			$stripeSubscription = json_decode( $this->stripe->subscriptions->create( $subscriptionData, $options )->toJSON() );
		}

		return $stripeSubscription;
	}

	/**
	 * @param $stripePaymentMethodId
	 *
	 * @return \StripeWPFS\Stripe\PaymentMethod
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws \Exception
	 */
	public function validatePaymentMethodCVCCheck( $stripePaymentMethodId ) {
		/* @var $paymentMethod \StripeWPFS\Stripe\PaymentMethod */
		$paymentMethod = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$paymentMethod = $this->remoteRequest(
				'get',
				'/payment_methods/' . $stripePaymentMethodId . '?mode=test&accountId=' . $this->testStripeAcountId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$paymentMethod = $this->remoteRequest(
				'get',
				'/payment_methods/' . $stripePaymentMethodId . '?mode=live&accountId=' . $this->liveStripeAcountId
			);
		} else {
			$paymentMethod = json_decode( $this->stripe->paymentMethods->retrieve( $stripePaymentMethodId )->toJSON() );
		}

		if ( $paymentMethod->type === 'card' && is_null( $paymentMethod->card->checks->cvc_check ) && is_null( $paymentMethod->card->wallet ) ) {
			throw new \Exception(
				/* translators: Validation error message for a card number without a CVC code */
				__( 'Please enter a CVC code', 'wp-full-stripe-free' )
			);
		}

		return $paymentMethod;
	}

	/**
	 * @param CreateSubscriptionContext $ctx
	 * @param CreateSubscriptionOptions $options
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 * @return \StripeWPFS\Stripe\Subscription
	 */
	public function createSubscriptionForCustomer( $ctx, $options ) {
		try {
			return $this->requestSubscriptionForCustomer( $ctx, $options );
		} catch ( \Exception $ex ) {
			throw $this->recognizeTaxBehaviorRefusal( $ex );
		}
	}

	/**
	 * @param CreateSubscriptionContext $ctx
	 * @param CreateSubscriptionOptions $options
	 * @return mixed The subscription, decoded from Stripe's or the platform's JSON.
	 *
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	private function requestSubscriptionForCustomer( $ctx, $options ) {
		$stripeCustomer = $this->retrieveCustomer( $ctx->stripeCustomerId );

		$useTestFunctions = $this->apiMode === 'test' && $this->usingWpTestPlatform;
		$useLiveFunctions = $this->apiMode === 'live' && $this->usingWpLivePlatform;

		$recurringPrice = null;
		if ( $useTestFunctions ) {
			$recurringPrice = $this->remoteRequest(
				'get',
				'/prices/' . $ctx->stripePriceId . '?mode=test&accountId=' . $this->testStripeAcountId
			);
		} elseif ( $useLiveFunctions ) {
			$recurringPrice = $this->remoteRequest(
				'get',
				'/prices/' . $ctx->stripePriceId . '?mode=live&accountId=' . $this->liveStripeAcountId
			);
		} else {
			$recurringPrice = json_decode( $this->stripe->prices->retrieve( $ctx->stripePriceId )->toJSON() );
		}

		if ( ! isset( $recurringPrice ) ) {
			throw new \Exception(
				sprintf(
					/* translators: %s: Stripe price ID */
					__( "Recurring price with id '%s' doesn't exist.", 'wp-full-stripe-free' ),
					$ctx->stripePriceId
				)
			);
		}

		// The browser confirms a new SetupIntent, and so saves a new PaymentMethod, for every subscription, so
		// only a request sent again, such as a retry, repeats the key: Stripe answers it with the subscription the
		// first one created.
		// The metadata is left out: every request stamps the time the donor accepted the terms of use, and an
		// integration can add values that change on every request too.
		$hashedCtx = clone $ctx;
		$hashedCtx->metadata = null;
		$idempotencyHash = is_null( $ctx->stripePaymentMethodId ) ?
			null :
			hash( 'sha256', (string) wp_json_encode( [ $hashedCtx, $options->taxRateIds ] ) );

		if ( ! is_null( $ctx->stripePaymentMethodId ) ) {
			$paymentMethod = $this->retrievePaymentMethod( $ctx->stripePaymentMethodId );
			$this->attachPaymentMethodToCustomer( $paymentMethod, $stripeCustomer->id );

			$params = [
				'invoice_settings' => [
					'default_payment_method' => $ctx->stripePaymentMethodId
				]
			];
			$this->updateCustomerDetails( $stripeCustomer, $params );
		}

		$hasBillingCycleAnchor = $ctx->billingCycleAnchorDay > 0;
		$hasMonthlyBillingCycleAnchor = $recurringPrice->recurring->interval === 'month' && $hasBillingCycleAnchor;
		$hasTrialPeriod = $ctx->trialPeriodDays > 0;

		$subscriptionItemsParams = [
			[
				'price' => $recurringPrice->id,
				'quantity' => $ctx->stripePlanQuantity,
			]
		];

		if ( ! $ctx->isStripeTax ) {
			$subscriptionItemsParams[0]['tax_rates'] = $options->taxRateIds;
		}

		if ( isset( $ctx->feeRecoveryLineItem ) && ! empty( $ctx->feeRecoveryLineItem ) ) {
			$subscriptionItemsParams[] = $ctx->feeRecoveryLineItem;
		}

		$addInvoiceItems = [];
		if ( $ctx->setupFee > 0 ) {
			// The first invoice bills the setup fee, so a subscription Stripe refuses leaves nothing on the customer.
			$setupFeePrice = ( new SubscriptionContextCreator( null, null, $this ) )->createOrRetrieveSetupFeePrice( $recurringPrice->currency );
			$setupFeeItem = [
				'price_data' => [
					'currency' => $recurringPrice->currency,
					'product' => is_object( $setupFeePrice->product ) ? $setupFeePrice->product->id : $setupFeePrice->product,
					'unit_amount_decimal' => $ctx->setupFee,
				],
				'quantity' => $ctx->stripePlanQuantity,
				// Receipts find the setup fee's line by this mark.
				'metadata' => [ 'type' => Main::LINE_ITEM_TYPE_SETUP_FEE ],
			];
			if ( ! $ctx->isStripeTax ) {
				$setupFeeItem['tax_rates'] = $options->taxRateIds;
			}
			$addInvoiceItems[] = $setupFeeItem;
		}

		$subscriptionData = [
			'customer' => $stripeCustomer->id,
			'items' => $subscriptionItemsParams,
			'expand' => [
				'latest_invoice',
				'latest_invoice.payments',
				'latest_invoice.payment_intent',
				'latest_invoice.charge',
				'pending_setup_intent'
			],

		];
		if ( ! empty( $addInvoiceItems ) ) {
			$subscriptionData['add_invoice_items'] = $addInvoiceItems;
		}
		if ( ! empty( $ctx->discountId ) ) {
			// it's all coupon codes now
			$subscriptionData['discounts'] = [
				[ 'coupon' => $ctx->discountId ]
			];
		}
		if ( $hasTrialPeriod ) {
			$subscriptionData['trial_period_days'] = $ctx->trialPeriodDays;
		}
		if ( $hasMonthlyBillingCycleAnchor ) {
			if ( $hasTrialPeriod ) {
				$subscriptionData['billing_cycle_anchor'] = Utils::calculateBillingCycleAnchorFromTimestamp(
					$ctx->billingCycleAnchorDay,
					Utils::calculateTrialEndFromNow( $ctx->trialPeriodDays )
				);
			} else {
				$subscriptionData['billing_cycle_anchor'] = Utils::calculateBillingCycleAnchorFromNow( $ctx->billingCycleAnchorDay );
			}

			if ( $ctx->prorateUntilAnchorDay === 1 ) {
				$subscriptionData['proration_behavior'] = 'create_prorations';
			} else {
				$subscriptionData['proration_behavior'] = 'none';
			}
		}
		if ( ! is_null( $ctx->metadata ) ) {
			$subscriptionData['metadata'] = $ctx->metadata;
		}
		if ( $ctx->isStripeTax ) {
			$subscriptionData['automatic_tax'] = [ 
				'enabled' => true
			];
		}

		$subscriptionKey = is_null( $idempotencyHash ) ? null : 'wpfs-subscription-' . $idempotencyHash;
		$headers = is_null( $subscriptionKey ) ? [] : [ 'Idempotency-Key' => $subscriptionKey ];

		$stripeSubscription = null;
		if ( $useTestFunctions ) {
			$subscriptionData = array_merge( $subscriptionData, [ 'validLicense' => $this->hasValidLicense() ] );
			$stripeSubscription = $this->remoteRequest(
				'post',
				'/subscriptions?mode=test&accountId=' . $this->testStripeAcountId . '&apiVersion=' . $this->userVersion,
				$subscriptionData,
				$headers
			);
		} elseif ( $useLiveFunctions ) {
			$subscriptionData = array_merge( $subscriptionData, [ 'validLicense' => $this->hasValidLicense() ] );
			$stripeSubscription = $this->remoteRequest(
				'post',
				'/subscriptions?mode=live&accountId=' . $this->liveStripeAcountId . '&apiVersion=' . $this->userVersion,
				$subscriptionData,
				$headers
			);

		} else {
			$stripeSubscription = json_decode( $this->stripe->subscriptions->create( $subscriptionData, is_null( $subscriptionKey ) ? [] : [ 'idempotency_key' => $subscriptionKey ] )->toJSON() );
		}

		return $stripeSubscription;
	}

	/**
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function attachPaymentMethodToCustomer( $paymentMethod, $customerId ) {
		$params = [ 'customer' => $customerId ];

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$paymentMethod = $this->remoteRequest(
				'post',
				'/payment_methods/' . $paymentMethod->id . '/attach?mode=test&accountId=' . $this->testStripeAcountId,
				$params
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$paymentMethod = $this->remoteRequest(
				'post',
				'/payment_methods/' . $paymentMethod->id . '/attach?mode=live&accountId=' . $this->liveStripeAcountId,
				$params
			);
		} else {
			$this->stripe->paymentMethods->attach( $paymentMethod->id, $params );
		}

		return $paymentMethod;
	}

	/**
	 * @param $customerId
	 *
	 * @return \StripeWPFS\Stripe\Customer
	 * @throws UserFriendlyException
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	public function retrieveCustomer( $customerId ) {
		$customer = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$customer = $this->remoteRequest(
				'get',
				'/customers/' . $customerId . '?mode=test&accountId=' . $this->testStripeAcountId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$customer = $this->remoteRequest(
				'get',
				'/customers/' . $customerId . '?mode=live&accountId=' . $this->liveStripeAcountId
			);
		} else {
			if ( is_null( $this->stripe ) ) {
				throw new UserFriendlyException( __( 'Stripe client is not initialized', 'wp-full-stripe-free' ) );
			}
			$customer = json_decode( $this->stripe->customers->retrieve( $customerId )->toJSON() );
		}
		return $customer;
	}

	/**
	 * @param $customerId
	 * @param $params
	 *
	 * @return \StripeWPFS\Stripe\Customer
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function retrieveCustomerWithParams( $customerId, $params ) {
		$customer = null;
		$query_string = $this->build_query( $params );

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$customer = $this->remoteRequest(
				'get',
				'/customers/' .$customerId . '?mode=test&accountId=' . $this->testStripeAcountId . '&' . $query_string
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$customer = $this->remoteRequest(
				'get',
				'/customers/' .$customerId . '?mode=live&accountId=' . $this->liveStripeAcountId . '&' . $query_string
			);
		} else {
			$this->assertStripeClient();
			$customer = json_decode( $this->stripe->customers->retrieve( $customerId, $params )->toJSON() );
		}

		return $customer;
	}

	/**
	 * @param $productId
	 *
	 * @return \StripeWPFS\Stripe\Product
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function retrieveProduct( $productId ) {
		$product = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$product = $this->remoteRequest(
				'get',
				'/products/' . $productId . '?mode=test&accountId=' . $this->testStripeAcountId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$product = $this->remoteRequest(
				'get',
				'/products/' . $productId . '?mode=live&accountId=' . $this->liveStripeAcountId
			);
		} else {
			$product = json_decode( $this->stripe->products->retrieve( $productId )->toJSON() );
		}
		return $product;
	}

	/**
	 * @param $code
	 *
	 * @return string|void
	 */
	function resolveErrorMessageByCode( $code ) {
		if ( $code === self::INVALID_NUMBER_ERROR ) {
			$resolved_message =  /* translators: message for Stripe error code 'invalid_number' */
				__( 'Your card number is invalid.', 'wp-full-stripe-free' );
		} elseif ( $code === self::INVALID_EXPIRY_MONTH_ERROR || $code === self::INVALID_NUMBER_ERROR_EXP_MONTH ) {
			$resolved_message = /* translators: message for Stripe error code 'invalid_expiry_month' */
				__( 'Your card\'s expiration month is invalid.', 'wp-full-stripe-free' );
		} elseif ( $code === self::INVALID_EXPIRY_YEAR_ERROR || $code === self::INVALID_NUMBER_ERROR_EXP_YEAR ) {
			$resolved_message = /* translators: message for Stripe error code 'invalid_expiry_year' */
				__( 'Your card\'s expiration year is invalid.', 'wp-full-stripe-free' );
		} elseif ( $code === self::INVALID_CVC_ERROR ) {
			$resolved_message = /* translators: message for Stripe error code 'invalid_cvc' */
				__( 'Your card\'s security code is invalid.', 'wp-full-stripe-free' );
		} elseif ( $code === self::INCORRECT_NUMBER_ERROR ) {
			$resolved_message = /* translators: message for Stripe error code 'incorrect_number' */
				__( 'Your card number is incorrect.', 'wp-full-stripe-free' );
		} elseif ( $code === self::EXPIRED_CARD_ERROR ) {
			$resolved_message = /* translators: message for Stripe error code 'expired_card' */
				__( 'Your card has expired.', 'wp-full-stripe-free' );
		} elseif ( $code === self::INCORRECT_CVC_ERROR ) {
			$resolved_message = /* translators: message for Stripe error code 'incorrect_cvc' */
				__( 'Your card\'s security code is incorrect.', 'wp-full-stripe-free' );
		} elseif ( $code === self::INCORRECT_ZIP_ERROR ) {
			$resolved_message = /* translators: message for Stripe error code 'incorrect_zip' */
				__( 'Your card\'s zip code failed validation.', 'wp-full-stripe-free' );
		} elseif ( $code === self::CARD_DECLINED_ERROR ) {
			$resolved_message = /* translators: message for Stripe error code 'card_declined' */
				__( 'Your card was declined.', 'wp-full-stripe-free' );
		} elseif ( $code === self::MISSING_ERROR ) {
			$resolved_message = /* translators: message for Stripe error code 'missing' */
				__( 'There is no card on a customer that is being charged.', 'wp-full-stripe-free' );
		} elseif ( $code === self::PROCESSING_ERROR ) {
			$resolved_message = /* translators: message for Stripe error code 'processing_error' */
				__( 'An error occurred while processing your card.', 'wp-full-stripe-free' );
		} elseif ( $code === self::MISSING_PAYMENT_INFORMATION ) {
			$resolved_message = /* translators: Stripe error message 'Missing payment information' */
				__( 'Missing payment information', 'wp-full-stripe-free' );
		} elseif ( $code === self::COULD_NOT_FIND_PAYMENT_INFORMATION ) {
			$resolved_message = /* translators: Stripe error message 'Could not find payment information' */
				__( 'Could not find payment information', 'wp-full-stripe-free' );
		} else {
			$resolved_message = null;
		}

		return $resolved_message;
	}

	/**
	 * @param $id
	 * @param $name
	 * @param $currency
	 * @param $interval
	 * @param $intervalCount
	 *
	 * @return \StripeWPFS\Stripe\Price
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	function createRecurringPlan( $id, $name, $currency, $interval, $intervalCount, $usageType = 'licensed' ) {
		$planData = [
			"currency" => $currency,
			"unit_amount" => "1",
			"nickname" => $name,
			"recurring" => [
				"interval" => $interval,
				"interval_count" => $intervalCount,
				"usage_type" => $usageType,
			],
			"product_data" => [
				"name" => $name
			],
			"lookup_key" => $id,
		];

		$plan = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$plan = $this->remoteRequest(
				'post',
				'/prices?mode=test&accountId=' . $this->testStripeAcountId,
				$planData
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$plan = $this->remoteRequest(
				'post',
				'/prices?mode=live&accountId=' . $this->liveStripeAcountId,
				$planData
			);
		} else {
			$plan = json_decode( $this->stripe->prices->create( $planData )->toJSON() );
		}

		return $plan;
	}

	/**
	 * Creates the shared price whose product inline subscriptions bill their setup fee on. The price only finds the
	 * product again, by its lookup key; each setup fee is priced on the subscription.
	 *
	 * @param string $lookupKey
	 * @param string $name The product's name, which the setup fee's invoice line shows.
	 * @param string $currency
	 *
	 * @return object{id: string, active: bool, product: string}
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	function createSetupFeePrice( $lookupKey, $name, $currency ) {
		$priceData = [
			'currency' => $currency,
			'unit_amount' => 0,
			'nickname' => $name,
			'product_data' => [
				'name' => $name,
				'metadata' => [ 'type' => Main::LINE_ITEM_TYPE_SETUP_FEE ],
			],
			'lookup_key' => $lookupKey,
		];

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			return $this->remoteRequest( 'post', '/prices?mode=test&accountId=' . $this->testStripeAcountId, $priceData );
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			return $this->remoteRequest( 'post', '/prices?mode=live&accountId=' . $this->liveStripeAcountId, $priceData );
		}

		return json_decode( $this->stripe->prices->create( $priceData )->toJSON() );
	}

	/**
	 * Create Stripe Product for One Time & Subscription
	 * 
	 * @param $name
	 * @param $currency
	 * @param $price
	 * @param $interval
	 * 
	 * @return \StripeWPFS\Stripe\Product
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	function createProduct( $name, $currency, $price, $interval = null ) {
		$currency = sanitize_text_field( $currency );
		$price    = Currencies::convertAmountToMinorUnits( $price, $currency );
		$name     = sanitize_text_field( $name );
		$interval = sanitize_text_field( $interval );

		if ( ! in_array( $interval, [ 'day', 'week', 'month', 'year' ] ) ) {
			$interval = null;
		}

		$productData = [
			'currency'     => $currency,
			'unit_amount'  => $price,
			'product_data' => [
				'name' => $name
			],
		];

		if ( ! is_null( $interval ) ) {
			$productData['recurring'] = [
				'interval' => $interval,
			];
		}

		$product = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$product = $this->remoteRequest(
				'post',
				'/prices?mode=test&accountId=' . $this->testStripeAcountId,
				$productData
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$product = $this->remoteRequest(
				'post',
				'/prices?mode=live&accountId=' . $this->liveStripeAcountId,
				$productData
			);
		} else {
			if ( is_null( $this->stripe ) ) {
				throw new UserFriendlyException( __( 'Stripe client is not initialized', 'wp-full-stripe-free' ) );
			}
			$product = json_decode( $this->stripe->prices->create( $productData )->toJSON() );
		}

		return $product;
	}

	/**
	 * Get the Stripe Plan/Price by its ID.
	 * 
	 * @param $planId The Stripe Plan/Price ID
	 *
	 * @return \StripeWPFS\Stripe\Price|null NOTE. The Price class is not used, all the return is a generic object, but kept for type hinting.
	 */
	public function retrievePlan( $planId ) {
		$plan = null;
		$params = [ 'expand' => [ 'product' ] ];
		$query_string = $this->build_query( $params );

		try {
			if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
				$plan = $this->remoteRequest(
					'get',
					'/prices/' . $planId . '?mode=test&accountId=' . $this->testStripeAcountId . '&' . $query_string
				);
			} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
				$plan = $this->remoteRequest(
					'get',
					'/prices/' . $planId . '?mode=live&accountId=' . $this->liveStripeAcountId . '&' . $query_string
				);
			} else if ( isset( $this->stripe ) && null !== $this->stripe ) {
				$plan = json_decode( $this->stripe->prices->retrieve(
					$planId,
					$params
				)->toJSON() );
			}
		} catch (\Exception $e) {
			// plan not found, let's fall through
		}

		return $plan;
	}

	/**
	 * @param $planId
	 * @param bool $active False for archived prices.
	 *
	 * @return \StripeWPFS\Stripe\Collection
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function retrievePlansWithLookupKey( $planId, $active = true ) {
		$prices = null;

		$params = [
			'active' => $active ? 'true' : 'false',
			'lookup_keys' => [ $planId ]
		];

		$query_string = $this->build_query( $params );

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$prices = $this->remoteRequest(
				'get',
				'/prices?mode=test&accountId=' . $this->testStripeAcountId . '&' . $query_string
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$prices = $this->remoteRequest(
				'get',
				'/prices?mode=live&accountId=' . $this->liveStripeAcountId . '&' . $query_string
			);
		} else {
			$prices = json_decode( $this->stripe->prices->all($params )->toJSON() );
		}

		return $prices;
	}

	/**
	 * @param string $priceId
	 * @param array<string, mixed> $params
	 *
	 * @return object{id: string, active: bool, product: string|object{id: string, active: bool}} The updated price.
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function updatePrice( $priceId, $params ) {
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			return $this->remoteRequest(
				'post',
				'/prices/' . $priceId . '?mode=test&accountId=' . $this->testStripeAcountId,
				$params
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			return $this->remoteRequest(
				'post',
				'/prices/' . $priceId . '?mode=live&accountId=' . $this->liveStripeAcountId,
				$params
			);
		}

		return json_decode( $this->stripe->prices->update( $priceId, $params )->toJSON() );
	}

	/**
	 * @param string $productId
	 * @param array<string, mixed> $params
	 *
	 * @return object{id: string, active: bool} The updated product.
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function updateProduct( $productId, $params ) {
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			return $this->remoteRequest(
				'post',
				'/products/' . $productId . '?mode=test&accountId=' . $this->testStripeAcountId,
				$params
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			return $this->remoteRequest(
				'post',
				'/products/' . $productId . '?mode=live&accountId=' . $this->liveStripeAcountId,
				$params
			);
		}

		return json_decode( $this->stripe->products->update( $productId, $params )->toJSON() );
	}

	/**
	 * The customers Stripe lists with the email, or none when the list fails.
	 *
	 * @param string $email
	 * @return list<\StripeWPFS\Stripe\Customer|\stdClass>
	 */
	public function getCustomersByEmail( $email ) {
		try {
			return $this->listCustomersByEmail( $email );
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Error while getting customers by email', $ex );

			return [];
		}
	}

	/**
	 * The customers Stripe lists with the email.
	 *
	 * @param string $email
	 * @return list<\StripeWPFS\Stripe\Customer|\stdClass>
	 * @throws \Exception When the list fails.
	 */
	public function listCustomersByEmail( $email ) {
		$customers = [];

		do {
			$params = [ 'limit' => 100, 'email' => $email ];
			$last_customer = end( $customers );
			if ( $last_customer ) {
				if ( is_array( $last_customer ) )
					$params['starting_after'] = $last_customer['id'];
				else
					$params['starting_after'] = $last_customer->id;
			}
			$query_string = $this->build_query( $params );
			if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
				$customer_collection = $this->remoteRequest(
					'get',
					'/customers?mode=test&accountId=' . $this->testStripeAcountId . '&' . $query_string
				);
				$customers = array_merge( $customers, $customer_collection->data );
			} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
				$customer_collection = $this->remoteRequest(
					'get',
					'/customers?mode=live&accountId=' . $this->liveStripeAcountId . '&' . $query_string
				);
				$customers = array_merge( $customers, $customer_collection->data );
			} else {
				if ( is_null( $this->stripe ) ) {
					throw new UserFriendlyException( __( 'Stripe client is not initialized', 'wp-full-stripe-free' ) );
				}
				$customer_collection = $this->stripe->customers->all( $params );
				$customers = array_merge( $customers, $customer_collection->data );
			}
		} while ( $customer_collection->has_more );

		return $customers;
	}

	/**
	 * @param $params
	 * @return \StripeWPFS\Stripe\Collection
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function getCustomersWithParams( $params ) {
		$customers = null;
		$query_string = $this->build_query( $params );

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$customers = $this->remoteRequest(
				'get',
				'/customers?mode=test&accountId=' . $this->testStripeAcountId . '&' . $query_string
			);
			$customers = $customers->data;
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$customers = $this->remoteRequest(
				'get',
				'/customers?mode=live&accountId=' . $this->liveStripeAcountId . '&' . $query_string
			);
			$customers = $customers->data;
		} else {
			$this->assertStripeClient();
			$customers = json_decode( $this->stripe->customers->all( $params )->toJSON() );
		}
		return $customers;
	}

	/**
	 * @param $customerId
	 * @param $params
	 * @return array
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function createCustomerPortalSession( $customerId, $returnUrl ) {
		$session = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$session = $this->remoteRequest(
				'post',
				'/billing/portal?mode=test&accountId=' . $this->testStripeAcountId,
				[
					'customer' => $customerId,
					'return_url' => $returnUrl
				]
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$session = $this->remoteRequest(
				'post',
				'/billing/portal?mode=live&accountId=' . $this->liveStripeAcountId,
				[
					'customer' => $customerId,
					'return_url' => $returnUrl
				]
			);
		} else {
			$session = json_decode( $this->stripe->billingPortal->sessions->create( [ 'customer' => $customerId ] )->toJSON() );
		}

		return $session;
	}

	/**
	 * @param $current_url
	 * @param string               $mode
	 * @param array<string,string> $credentialRequest The fields that start issuing this site's credential.
	 * @return mixed|null
	 * @throws UserFriendlyException
	 */
	public function getAccountLink( $current_url, $mode = 'test', array $credentialRequest = [] ) {
		$accountLink = null;
		// A platform that issues credentials builds the return address itself from admin_base, and ignores
		// return_url. An older one ignores the credential fields and still uses return_url.
		if ( $mode === 'test' ) {
			$accountLink = $this->remoteRequest(
				'post',
				'/account/link?mode=test',
				array_merge( [ 'return_url' => $current_url ], $credentialRequest )
			);
		} elseif ( $mode === 'live' ) {
			$accountLink = $this->remoteRequest(
				'post',
				'/account/link?mode=live',
				array_merge( [ 'return_url' => $current_url ], $credentialRequest )
			);
		}

		return $accountLink;
	}

	/**
	 * Activates this site's credential with the code the platform sent back after the merchant approved
	 * the connection, and the secret the site kept since it started.
	 *
	 * @param string $mode
	 * @param string $accountId
	 * @param string $code
	 * @param string $secret
	 * @return mixed
	 * @throws PlatformRequestException
	 */
	public function activateSiteCredential( string $mode, string $accountId, string $code, string $secret ) {
		return $this->remoteRequest(
			'post',
			'/account/credential?mode=' . rawurlencode( $mode ),
			[
				'accountId' => $accountId,
				'code' => $code,
				'secret' => $secret,
			],
			[],
			''
		);
	}

	/**
	 * Asks whether a credential is active, without changing anything.
	 *
	 * @param string $mode
	 * @param string $accountId
	 * @param string $credential
	 * @return bool
	 * @throws PlatformRequestException
	 */
	public function isSiteCredentialActive( string $mode, string $accountId, string $credential ): bool {
		$status = $this->remoteRequest(
			'get',
			'/account/credential/status?mode=' . rawurlencode( $mode ) . '&accountId=' . rawurlencode( $accountId ),
			null,
			[],
			$credential
		);

		return is_object( $status ) && ! empty( $status->active );
	}

	/**
	 * Revokes a credential on the platform, so nobody can use it any more.
	 *
	 * @param string $mode
	 * @param string $accountId
	 * @param string $credential
	 * @throws PlatformRequestException
	 */
	public function revokeSiteCredential( string $mode, string $accountId, string $credential ): void {
		$this->remoteRequest(
			'post',
			'/account/credential/revoke?mode=' . rawurlencode( $mode ) . '&accountId=' . rawurlencode( $accountId ),
			[],
			[],
			$credential
		);
	}

	/**
	 * Asks the platform to bring one page of this site's subscriptions in line with its license (#608).
	 * The platform stops starting new work after 8 seconds, and asks for at least 15 to answer.
	 *
	 * @param string               $mode      'test' or 'live'.
	 * @param string               $accountId The account this site is connected to.
	 * @param array{licenseId?: string, subscriptions: list<string>, final?: true, runVerdict?: string, complete?: bool} $body
	 * @return mixed
	 * @throws PlatformRequestException
	 */
	public function syncSubscriptionFees( string $mode, string $accountId, array $body ) {
		return $this->remoteRequest(
			'post',
			'/account/fee-sync?mode=' . rawurlencode( $mode ) . '&accountId=' . rawurlencode( $accountId ) . '&apiVersion=' . rawurlencode( $this->userVersion ),
			$body,
			[],
			null,
			self::FEE_SYNC_TIMEOUT
		);
	}

	/**
	 * @param string $url A platform path with its query.
	 * @return string|null The request's mode, when it is test or live.
	 */
	private static function getRequestMode( string $url ) {
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$mode = $query['mode'] ?? null;

		return SiteCredential::isValidMode( $mode ) ? $mode : null;
	}

	/**
	 * Asks the platform for a Stripe sign-in link that proves the merchant owns the connected
	 * account, before the platform's access to it is removed.
	 *
	 * @param string $returnUrl Where Stripe sends the merchant back to.
	 * @param string $mode      'test' or 'live'.
	 * @param string $nonce     Binds the returned ticket to this removal request.
	 * @return string The Stripe OAuth URL.
	 * @throws UserFriendlyException
	 */
	public function getRevokeAccessLink( string $returnUrl, string $mode, string $nonce ) {
		return $this->remoteRequest(
			'post',
			'/account/revoke?mode=' . rawurlencode( $mode ),
			[
				'return_url' => $returnUrl,
				'nonce' => $nonce,
			]
		);
	}

	/**
	 * Removes the platform's access to a connected account, redeeming the ticket the merchant
	 * brought back from Stripe.
	 *
	 * @param string $mode      'test' or 'live'.
	 * @param string $ticket    The ticket from the Stripe redirect.
	 * @param string $accountId The account this site is connected to.
	 * @param string $nonce     The nonce the removal was started with.
	 * @return mixed
	 * @throws UserFriendlyException
	 */
	public function deauthorizeAccount( string $mode, string $ticket, string $accountId, string $nonce ) {
		return $this->remoteRequest(
			'post',
			'/account/deauthorize?mode=' . rawurlencode( $mode ),
			[
				'ticket' => $ticket,
				'accountId' => $accountId,
				'nonce' => $nonce,
			]
		);
	}

	/**
	 * @param $accountId
	 * @return mixed
	 * @throws UserFriendlyException
	 */
	public function getTestAccount( $accountId ) {
		try {
			return $this->remoteRequest( 'get', '/account?mode=test&accountId=' . $accountId );
		} catch ( \Exception $ex ) {
			$this->logger->error( __FUNCTION__, 'Error while getting test account', $ex );
			throw new UserFriendlyException( 'Error while getting test account: ' . $ex->getMessage(), 0, $ex );
		}
	}

	/**
	 * @param $accountId
	 * @throws UserFriendlyException
	 */
	public function getLiveAccount( $accountId ) {
		try {
			return $this->remoteRequest( 'get', '/account?mode=live&accountId=' . $accountId );
		} catch ( \Exception $ex ) {
			$this->logger->error( __FUNCTION__, 'Error while getting live account', $ex );
			throw new UserFriendlyException( 'Error while getting live account: ' . $ex->getMessage(), 0, $ex );
		}
	}

	/**
	 * @return array|\StripeWPFS\Stripe\Collection
	 */
	public function getSubscriptionPlans() {
		$plans = [];
		$params = [
			'type' => 'recurring'
		];
		try {
			$plans = $this->getPriceList( $params );
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Error while getting subscription plans', $ex );

			$plans = [];
		}

		return $plans;
	}

	private function getPriceList( $params ) {
		$params['limit'] = 100;
		$params['expand'] = [ 'data.product' ];
		$prices = [];
		do {
			$lastPrice = end( $prices );
			if ( $lastPrice ) {
				if ( isset( $lastPrice ) && is_array( $lastPrice ) )
					$params['starting_after'] = ( isset( $lastPrice['id'] ) && ! empty( $lastPrice['id'] ) ) ? $lastPrice['id'] : null;
				else
					$params['starting_after'] = $lastPrice->id ? $lastPrice->id : null;
			}
			$priceCollection = null;
			$query_string = $this->build_query( $params );
			if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
				$priceCollection = $this->remoteRequest(
					'get',
					'/prices?mode=test&accountId=' . $this->testStripeAcountId . '&' . $query_string
				);
				$prices = array_merge( $prices, $priceCollection->data );
			} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
				$priceCollection = $this->remoteRequest(
					'get',
					'/prices?mode=live&accountId=' . $this->liveStripeAcountId . '&' . $query_string
				);
				$prices = array_merge( $prices, $priceCollection->data );
			} else {
				$this->assertStripeClient();
				$priceCollection = $this->stripe->prices->all( $params );
				$prices = array_merge( $prices, $priceCollection['data'] );
			}
		} while ( $priceCollection->has_more );

		return $prices;
	}

	/**
	 * @return array|\StripeWPFS\Stripe\Collection
	 * @throws UserFriendlyException
	 */
	public function getOnetimePrices() {
		$prices = [];
		$params = [
			'active' => 'true',
			'type' => 'one_time'
		];
		try {
			$prices = $this->getPriceList( $params );
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Error while getting one-time prices', $ex );
			$prices = [];
		}
		return $prices;
	}

	/**
	 * @return array|\StripeWPFS\Stripe\Collection
	 */
	public function getRecurringPrices() {
		$prices = [];
		$params = [
			'active' => 'true',
			'type' => 'recurring'
		];
		try {
			$prices = $this->getPriceList( $params );
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Error while getting recurring prices', $ex );
			$prices = [];
		}
		return $prices;
	}

	/**
	 * @return array|\StripeWPFS\Stripe\Collection
	 * @throws UserFriendlyException
	 */
	public function getTaxRates() {
		$taxRates = [];
		do {
			$params = [
				'active' => 'true',
				'limit' => 100,
			];

			$lastTaxRate = end( $taxRates );
			if ( $lastTaxRate ) {
				if ( is_array( $lastTaxRate ) )
					$params['starting_after'] = $lastTaxRate['id'];
				else
					$params['starting_after'] = $lastTaxRate->id;
			}
			$taxRateCollection = null;
			$query_string = $this->build_query( $params );
			if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
				$taxRateCollection = $this->remoteRequest(
					'get',
					'/tax_rates?mode=test&accountId=' . $this->testStripeAcountId . '&' . $query_string
				);
				$taxRates = array_merge( $taxRates, $taxRateCollection->data );
			} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
				$taxRateCollection = $this->remoteRequest(
					'get',
					'/tax_rates?mode=live&accountId=' . $this->liveStripeAcountId . '&' . $query_string
				);
				$taxRates = array_merge( $taxRates, $taxRateCollection->data );
			} else {
				if ( is_null( $this->stripe ) ) {
					throw new UserFriendlyException( __( 'Stripe client is not initialized', 'wp-full-stripe-free' ) );
				}
				$taxRateCollection = json_decode( $this->stripe->taxRates->all( $params )->toJSON() );
				$taxRates = array_merge( $taxRates, $taxRateCollection->data );
			}
		} while ( $taxRateCollection->has_more );

		return $taxRates;
	}

	/**
	 * @param $code
	 *
	 * @return \StripeWPFS\Stripe\Coupon
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function retrieveCoupon( $code ) {
		$coupons = null;
		$params = [ 'expand' => [ 'applies_to' ] ];
		$query_string = $this->build_query( $params );

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$coupons = $this->remoteRequest(
				'get',
				'/coupons/' . $code . '?mode=test&accountId=' . $this->testStripeAcountId . '&' . $query_string
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$coupons = $this->remoteRequest(
				'get',
				'/coupons/' . $code . '?mode=live&accountId=' . $this->liveStripeAcountId . '&' . $query_string
			);
		} else {
			$coupons = json_decode( $this->stripe->coupons->retrieve( $code, $params )->toJSON() );
		}
		return $coupons;
	}

	/**
	 * @param string $code
	 * @param bool $activeOnly Skip inactive promotion codes, which may share the code of the active one.
	 *
	 * @return \StripeWPFS\Stripe\PromotionCode|null
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function retrievePromotionalCode( $code, $activeOnly = false ) {
		$promotionalCodesCollection = null;
		$params = [
			'code' => $code,
			'expand' => [ 'data.coupon.applies_to' ]
		];
		$query_string = $this->build_query( $params );

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$promotionalCodesCollection = $this->remoteRequest(
				'get',
				'/promotion_codes?mode=test&accountId=' . $this->testStripeAcountId . '&' . $query_string
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$promotionalCodesCollection = $this->remoteRequest(
				'get',
				'/promotion_codes?mode=live&accountId=' . $this->liveStripeAcountId . '&' . $query_string
			);
		} else {
			$promotionalCodesCollection = $this->stripe->promotionCodes->all( $params );
		}

		$result = null;

		// TODO: replace this with something else that doesn't require the stripe specific object
		$promotionalCodesCollection = \StripeWPFS\Stripe\Collection::constructFrom(
			json_decode(
				json_encode( $promotionalCodesCollection ),
				true
			)
		);

		/** @var \StripeWPFS\Stripe\PromotionCode $promotionCode */
		foreach ( $promotionalCodesCollection->autoPagingIterator() as $promotionCode ) {
			if ( strcasecmp( $code, $promotionCode->code ) === 0 && ( ! $activeOnly || $promotionCode->active ) ) {
				$result = $promotionCode;
				break;
			}
		}

		return $result;
	}

	/**
	 * The active promotion code with the given code, the only kind Checkout applies.
	 *
	 * @param string $code
	 * @return \StripeWPFS\Stripe\PromotionCode|null
	 */
	public function retrieveActivePromotionCode( $code ) {
		return $this->getPromotionalCode( $code, true );
	}

	/**
	 * @param string $code
	 * @param bool $activeOnly
	 * @return \StripeWPFS\Stripe\PromotionCode|null
	 */
	protected function getPromotionalCode( $code, $activeOnly = false ) {
		try {
			return $this->retrievePromotionalCode( $code, $activeOnly );
		} catch (\Exception $ex) {
			$this->logger->debug( __FUNCTION__, "Cannot retrieve promotional code" . $ex );
			return null;
		}
	}

	protected function getCoupon( $code ) {
		try {
			return $this->retrieveCoupon( $code );
		} catch (\Exception $ex) {
			$this->logger->debug( __FUNCTION__, "Cannot retrieve coupon" . $ex );

			return null;
		}
	}

	/**
	 * @param string $code
	 * @return \StripeWPFS\Stripe\Coupon|null
	 */
	public function retrieveCouponByPromotionalCodeOrCouponCode( $code ) {
		$result = null;

		try {
			// An inactive promotion code may share its code with the active one. A code no active promotion code
			// has may still be a coupon's ID.
			$promotionalCode = $this->getPromotionalCode( $code, true );
			$result = is_null( $promotionalCode ) ? $this->getCoupon( $code ) : $promotionalCode->coupon;
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, "Cannot retrieve coupon or promotional code", $ex );
		}

		return $result;
	}

	/**
	 * @param $invoiceId
	 *
	 * @return \StripeWPFS\Stripe\Invoice
	 * @throws UserFriendlyException
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	function retrieveInvoice( $invoiceId ) {
		$invoice = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$invoice = $this->remoteRequest(
				'get',
				'/invoices/' . $invoiceId . '?mode=test&accountId=' . $this->testStripeAcountId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$invoice = $this->remoteRequest(
				'get',
				'/invoices/' . $invoiceId . '?mode=live&accountId=' . $this->liveStripeAcountId
			);
		} else {
			$invoice = json_decode( $this->stripe->invoices->retrieve( $invoiceId )->toJSON() );
		}

		return $invoice;
	}

	/**
	 * @param $paymentMethodId
	 * @param $customerName
	 * @param $customerEmail
	 * @param $metadata
	 *
	 * @return \StripeWPFS\Stripe\Customer
	 *
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	function createCustomerWithPaymentMethod(
		$paymentMethodId,
		$customerName,
		$customerEmail,
		$metadata,
		$taxIdType = null,
		$taxId = null,
		$billing_address = null,
		$billing_name = null,
		$shipping_address = null,
		$shipping_name = null
	) {
		$customer = [
			'email' => $customerEmail,
		];
		if ( ! is_null( $paymentMethodId ) ) {
			$customer['payment_method'] = $paymentMethodId;
			$customer['invoice_settings']['default_payment_method'] = $paymentMethodId;
		}

		// A form without address fields posts no billing name, which binds as an empty string.
		if ( '' !== (string) $billing_name ) {
			$customer['name'] = $billing_name;
		} elseif ( '' !== (string) $customerName ) {
			$customer['name'] = $customerName;
		}

		if ( ! is_null( $metadata ) ) {
			$customer['metadata'] = $metadata;
		}

		if ( ! empty( $taxIdType ) && ! empty( $taxId ) ) {
			$customer['tax_id_data'] = [ 
				[ 
					'type' => $taxIdType,
					'value' => $taxId
				]
			];
		}

		if ( ! empty( $billing_address ) ) {
			$customer['address'] = Utils::prepareStripeBillingAddressHashFromArray( $billing_address );
		}
		if ( ! empty( $shipping_address ) ) {
			$customer['shipping'] = [ 
				'address' => Utils::prepareStripeBillingAddressHashFromArray( $shipping_address ),
				'name' => $shipping_name
			];
		}

		$createdCustomer = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$createdCustomer = $this->remoteRequest(
				'post',
				'/customers?mode=test&accountId=' . $this->testStripeAcountId,
				$customer
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$createdCustomer = $this->remoteRequest(
				'post',
				'/customers?mode=live&accountId=' . $this->liveStripeAcountId,
				$customer
			);
		} else {
			$createdCustomer = json_decode( $this->stripe->customers->create( $customer )->toJSON() );
		}

		return $createdCustomer;
	}

	/**
	 * @param $paymentMethodId
	 * @param $customerId
	 * @param $currency
	 * @param $amount
	 * @param $capture
	 * @param $description
	 * @param $metadata - optional default null
	 * @param $stripeEmail - optional default null
	 * @param $allowRedirects - 'never' or 'always' defaults to 'never'
	 * @param $paymentMethodTypes - list of payment methods to allow defaults to ['card','link']
	 *
	 * @return \stdClass
	 *
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	function createPaymentIntent(
		$paymentMethodId,
		$customerId,
		$currency,
		$amount,
		$capture,
		$description,
		$metadata = null,
		$stripeEmail = null,
		$allowRedirects = 'never',
		$paymentMethodTypes = [ 'card', 'link' ]
	) {
		$paymentIntentParameters = [
			'amount' => isset( $amount ) && ! empty( $amount ) ? $amount : 100,
			'currency' => $currency,
			'expand' => [ 'latest_charge' ],
		];
		if ( ! empty( $customerId ) ) {
			$paymentIntentParameters['customer'] = $customerId;
		}
		if ( ! empty( $paymentMethodId ) ) {
			$paymentIntentParameters['payment_method'] = $paymentMethodId;
		}
		if ( ! empty( $description ) ) {
			$paymentIntentParameters['description'] = $description;
		}
		if ( false === $capture ) {
			$paymentIntentParameters['capture_method'] = 'manual';
		}
		if ( isset( $stripeEmail ) ) {
			$paymentIntentParameters['receipt_email'] = $stripeEmail;
		}
		if ( isset( $metadata ) ) {
			$paymentIntentParameters['metadata'] = $metadata;
		}
		if ( isset( $paymentMethodTypes ) ) {
			if ( ! is_array( $paymentMethodTypes ) )
				$paymentIntentParameters['payment_method_types'] = json_decode( $paymentMethodTypes );
			else
				$paymentIntentParameters['payment_method_types'] = $paymentMethodTypes;
		} else {
			$paymentIntentParameters['automatic_payment_methods'] = [ 
				'enabled' => true,
				'allow_redirects' => $allowRedirects, // payment intents can be redirected e.g. for BNPL type payments so 
			];
			$paymentIntentParameters['confirm'] = true;
		}

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$paymentIntentParameters = array_merge(
				$paymentIntentParameters,
				[
					'validLicense' => $this->hasValidLicense(),
				]
			);
			$intent = $this->remoteRequest(
				'post',
				'/payment_intents?mode=test&accountId=' . $this->testStripeAcountId . '&apiVersion=' . $this->userVersion,
				apply_filters( 'fullstripe_payment_intent_parameters', $paymentIntentParameters )
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$paymentIntentParameters = array_merge(
				$paymentIntentParameters,
				[
					'validLicense' => $this->hasValidLicense(),
				]
			);
			$intent = $this->remoteRequest(
				'post',
				'/payment_intents?mode=live&accountId=' . $this->liveStripeAcountId . '&apiVersion=' . $this->userVersion,
				apply_filters( 'fullstripe_payment_intent_parameters', $paymentIntentParameters )
			);
		} else {
			$this->assertStripeClient();
			$paymentIntentParameters['automatic_payment_methods']['enabled'] = false;
			$intent = json_decode( $this->stripe->paymentIntents->create(
				apply_filters(
					'fullstripe_payment_intent_parameters',
					$paymentIntentParameters
				)
			)->toJSON() );
		}

		return $intent;
	}

	/**
	 * Creates a PaymentIntent intended to back a deferred Payment Element (i.e. the client
	 * confirms it later via stripe.confirmPayment). Unlike createPaymentIntent(), this does
	 * nnot attach a payment method, does not confirm server-side, and relies on
	 * automatic_payment_methods so the available payment methods follow the Stripe Dashboard
	 * configuration (e.g. Link enabled/disabled), subject to the allow_redirects setting.
	 *
	 * Used by donation forms, which have no payment method selector of their own.
	 *
	 * @param string $currency
	 * @param int $amount
	 * @param string $allowRedirects 'never' or 'always', defaults to 'never'; any other value is normalized to 'never'
	 * @param array<string,mixed>|null $metadata Metadata to store on the PaymentIntent
	 *
	 * @return \stdClass
	 *
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	function createPaymentIntentForElement( $currency, $amount, $allowRedirects = 'never', $metadata = null ) {
		$allowRedirects = ( 'always' === $allowRedirects ) ? 'always' : 'never';
		$paymentIntentParameters = [
			'amount' => ! empty( $amount ) ? $amount : 100,
			'currency' => $currency,
			'expand' => [ 'latest_charge' ],
			'automatic_payment_methods' => [
				'enabled' => true,
				'allow_redirects' => $allowRedirects,
			],
		];

		if ( ! empty( $metadata ) ) {
			$paymentIntentParameters['metadata'] = $metadata;
		}

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$paymentIntentParameters['validLicense'] = $this->hasValidLicense();
			$intent = $this->remoteRequest(
				'post',
				'/payment_intents?mode=test&accountId=' . $this->testStripeAcountId . '&apiVersion=' . $this->userVersion,
				apply_filters( 'fullstripe_payment_intent_parameters', $paymentIntentParameters )
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$paymentIntentParameters['validLicense'] = $this->hasValidLicense();
			$intent = $this->remoteRequest(
				'post',
				'/payment_intents?mode=live&accountId=' . $this->liveStripeAcountId . '&apiVersion=' . $this->userVersion,
				apply_filters( 'fullstripe_payment_intent_parameters', $paymentIntentParameters )
			);
		} else {
			$this->assertStripeClient();
			$intent = json_decode( $this->stripe->paymentIntents->create(
				apply_filters(
					'fullstripe_payment_intent_parameters',
					$paymentIntentParameters
				)
			)->toJSON() );
		}

		return $intent;
	}

	/**
	 * @throws UserFriendlyException
	 */
	function getTestAccountLink( $accountId, $refreshUrl, $returnUrl ) {
		$params = [
			'refresh_url' => $refreshUrl,
			'return_url' => $returnUrl,
		];

		$data = $this->remoteRequest(
			'post',
			'/account/onboarding?mode=test&accountId=' . $accountId,
			$params
		);

		return $data->url;
	}

	/**
	 * @throws UserFriendlyException
	 */
	function getLiveAccountLink( $accountId, $refreshUrl, $returnUrl ) {
		$params = [
			'refresh_url' => $refreshUrl,
			'return_url' => $returnUrl,
		];

		$data = $this->remoteRequest(
			'post',
			'/account/onboarding?mode=live&accountId=' . $accountId,
			$params
		);

		return $data->url;
	}

	/**
	 * @param CreateOneTimeInvoiceContext $ctx
	 * @param CreateOneTimeInvoiceOptions $options
	 * @return \StripeWPFS\Stripe\Invoice
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	function createInvoiceForOneTimePayment( $ctx, $options ) {
		try {
			return $this->requestInvoiceForOneTimePayment( $ctx, $options );
		} catch ( \Exception $ex ) {
			throw $this->recognizeTaxBehaviorRefusal( $ex );
		}
	}

	/**
	 * @param CreateOneTimeInvoiceContext $ctx
	 * @param CreateOneTimeInvoiceOptions $options
	 * @return mixed The invoice, decoded from Stripe's or the platform's JSON.
	 *
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	private function requestInvoiceForOneTimePayment( $ctx, $options ) {
		$currency = strtolower( $ctx->currency );
		$recoveryFee = (int) $ctx->recoveryFee;

		$invoiceParams = [
			'customer' => $ctx->stripeCustomerId,
			// Without it Stripe creates the invoice in the customer's or the account's default currency, and
			// refuses lines in another one (#758).
			'currency' => $currency,
			'auto_advance' => $options->autoAdvance,
			// Checkout applies a promotion code to the whole session, the fee included. Without a coupon, the empty
			// value keeps Stripe from applying the customer's own discount, which the charge didn't have (#758).
			'discounts' => isset( $ctx->stripeCouponId ) ? [ [ 'coupon' => $ctx->stripeCouponId ] ] : '',
			'metadata' => [
			]
		];

		// if we're not using connect, don't send the amount as it's not allowed by Stripe in the direct call
		// we use the amount in the cloud functions to calculate the application fee that is added to the invoice
		if ( $this->usingWpTestPlatform || $this->usingWpLivePlatform ) {
			$invoiceParams['amount'] = $ctx->amount + $recoveryFee;
		}

		$invoiceItemParams = [
			'customer' => $ctx->stripeCustomerId,
		];

		if ( $ctx->stripePriceId !== null ) {
			$invoiceItemParams['pricing'] = [
				'price' => $ctx->stripePriceId,
			];
		} else {
			$invoiceItemParams['amount'] = $ctx->amount;
			$invoiceItemParams['currency'] = $currency;
			$invoiceItemParams['description'] = $ctx->productName;
		}

		if ( $ctx->isStripeTax ) {
			$invoiceParams['automatic_tax'] = [ 
				'enabled' => true
			];
		} else {
			if ( isset( $options->taxRateIds ) && count( $options->taxRateIds ) > 0 ) {
				$invoiceItemParams['tax_rates'] = $options->taxRateIds;
			}
		}

		// The lines, by the suffix of their idempotency key.
		$lineItems = [ '-item' => $invoiceItemParams ];
		if ( $recoveryFee > 0 ) {
			// As on the Checkout session, the fee carries none of the form's tax rates.
			$lineItems['-fee'] = [
				'customer' => $ctx->stripeCustomerId,
				'amount' => $recoveryFee,
				'currency' => $currency,
				'description' => __( 'Transaction Fee', 'wp-full-stripe-free' ),
			];
			if ( $ctx->recoveryFeeAfterDiscountAndTax ) {
				$lineItems['-fee']['discountable'] = false;
				$lineItems['-fee']['tax_code'] = self::TAX_CODE_NONTAXABLE;
				// Stripe Tax refuses a line without one, on an account without a default tax behavior.
				$lineItems['-fee']['tax_behavior'] = 'exclusive';
			}
		}

		$invoiceKey = empty( $options->idempotencyKey ) ? null : $options->idempotencyKey;

		$platformQuery = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$platformQuery = 'mode=test&accountId=' . $this->testStripeAcountId;
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$platformQuery = 'mode=live&accountId=' . $this->liveStripeAcountId;
		}

		if ( ! is_null( $platformQuery ) ) {
			// add the license key to the invoice params
			$invoiceParams = array_merge( $invoiceParams, [ 'validLicense' => $this->hasValidLicense() ] );
			$createdInvoice = $this->remoteRequest(
				'post',
				'/invoices?' . $platformQuery . '&apiVersion=' . $this->userVersion,
				$invoiceParams,
				is_null( $invoiceKey ) ? [] : [ 'Idempotency-Key' => $invoiceKey ]
			);
		} else {
			$createdInvoice = json_decode( $this->stripe->invoices->create( $invoiceParams, is_null( $invoiceKey ) ? [] : [ 'idempotency_key' => $invoiceKey ] )->toJSON() );
		}

		// Stripe leaves pending invoice items off a new invoice, so every line is created on it (#758).
		foreach ( $lineItems as $keySuffix => $lineItemParams ) {
			$lineItemParams['invoice'] = $createdInvoice->id;
			$lineItemKey = is_null( $invoiceKey ) ? null : $invoiceKey . $keySuffix;

			if ( ! is_null( $platformQuery ) ) {
				$this->remoteRequest(
					'post',
					'/invoice_items?' . $platformQuery,
					$lineItemParams,
					is_null( $lineItemKey ) ? [] : [ 'Idempotency-Key' => $lineItemKey ]
				);
			} else {
				$this->stripe->invoiceItems->create( $lineItemParams, is_null( $lineItemKey ) ? [] : [ 'idempotency_key' => $lineItemKey ] );
			}
		}

		return $createdInvoice;
	}

	/**
	 * @param CreateOneTimeInvoiceContext $ctx
	 * @param CreateOneTimeInvoiceOptions $options
	 * @return \StripeWPFS\Stripe\Invoice
	 */
	function createPreviewInvoiceForOneTimePayment( $ctx, $options ) {
		$invoiceParams = [];

		$address = [ 
			'country' => $ctx->taxCountry
		];
		if ( $ctx->isStripeTax ) {
			$invoiceParams['automatic_tax'] = [ 
				'enabled' => true
			];

			// The whole address, as the form's price is previewed with it.
			$stripeTaxAddress = [
				'postal_code' => $ctx->taxPostalCode,
				'city' => $ctx->taxCity,
				'line1' => $ctx->taxLine1,
				'line2' => $ctx->taxLine2,
				'state' => $ctx->taxState,
			];
			$address = array_merge( $address, array_filter( $stripeTaxAddress, static function ( $value ): bool {
				return ! empty( $value );
			} ) );
		} else {
			if ( ! empty( $ctx->taxState ) ) {
				$address['state'] = $ctx->taxState;
			}
		}
		$invoiceParams['customer_details'] = [ 
			'address' => $address
		];

		if ( $ctx->isStripeTax && ! empty( $ctx->taxIdType ) && ! empty( $ctx->taxId ) ) {
			$invoiceParams['customer_details']['tax_ids'] = [ 
				[ 
					'type' => $ctx->taxIdType,
					'value' => $ctx->taxId,
				]
			];
		}

		$itemParams = [];
		if ( $ctx->stripePriceId !== null ) {
			$itemParams['price'] = $ctx->stripePriceId;
		} else {
			$itemParams['amount'] = round( $ctx->amount );
			$itemParams['currency'] = $ctx->currency;
			$itemParams['description'] = $ctx->productName;
		}

		if ( isset( $ctx->stripeCouponId ) ) {
			$itemParams['discounts'] = [
				[ 'coupon' => $ctx->stripeCouponId ]
			];
		}

		if ( ! $ctx->isStripeTax ) {
			if ( isset( $options->taxRateIds ) && count( $options->taxRateIds ) > 0 ) {
				$itemParams['tax_rates'] = $options->taxRateIds;
			}
		}

		$invoiceParams['invoice_items'] = [ 
			$itemParams
		];

		return $this->getUpcomingInvoice( $invoiceParams );
	}

	/**
	 * @param $finalizedInvoice
	 * @param $stripePaymentMethodId
	 * @param $stripeChargeDescription
	 * @param $stripeReceiptEmailAddress
	 *
	 * @return \StripeWPFS\Stripe\Invoice
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	function updatePaymentIntentByInvoice(
		$finalizedInvoice,
		$stripePaymentMethodId,
		$stripeChargeDescription,
		$metadata,
		$stripeReceiptEmailAddress
	) {
		$paymentIntentParameters = [];
		if ( ! empty( $stripeChargeDescription ) ) {
			$paymentIntentParameters['description'] = $stripeChargeDescription;
		}
		if ( isset( $stripeReceiptEmailAddress ) ) {
			$paymentIntentParameters['receipt_email'] = $stripeReceiptEmailAddress;
		}
		if ( isset( $metadata ) ) {
			$paymentIntentParameters['metadata'] = $metadata;
		}

		$generatedPaymentIntent = null;
		$updatedPaymentIntent = null;
		$updatedInvoice = null;

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

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$generatedPaymentIntent = $this->remoteRequest(
				'get',
				'/payment_intents/' . $stripePaymentIntent. '?mode=test&accountId=' . $this->testStripeAcountId
			);

			$updatedPaymentIntent = $this->remoteRequest(
				'post',
				'/payment_intents/' . $generatedPaymentIntent->id . '?mode=test&accountId=' . $this->testStripeAcountId . '&apiVersion=' . $this->userVersion,
				apply_filters( 'fullstripe_payment_intent_parameters', $paymentIntentParameters )
			);

			$this->remoteRequest(
				'post',
				'/payment_intents/' . $updatedPaymentIntent->id . '/confirm?mode=test&accountId=' . $this->testStripeAcountId,
				[ 'payment_method' => $stripePaymentMethodId ]
			);

			$updatedInvoice = $this->remoteRequest(
				'post',
				'/invoices/' . $finalizedInvoice->id . '?mode=test&accountId=' . $this->testStripeAcountId,
				[]
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$generatedPaymentIntent = $this->remoteRequest(
				'get',
				'/payment_intents/' . $stripePaymentIntent. '?mode=live&accountId=' . $this->liveStripeAcountId
			);

			$updatedPaymentIntent = $this->remoteRequest(
				'post',
				'/payment_intents/' . $generatedPaymentIntent->id . '?mode=live&accountId=' . $this->liveStripeAcountId . '&apiVersion=' . $this->userVersion,
				apply_filters( 'fullstripe_payment_intent_parameters', $paymentIntentParameters )
			);

			$this->remoteRequest(
				'post',
				'/payment_intents/' . $updatedPaymentIntent->id . '/confirm?mode=live&accountId=' . $this->liveStripeAcountId,
				[ 'payment_method' => $stripePaymentMethodId ]
			);

			$updatedInvoice = $this->remoteRequest(
				'post',
				'/invoices/' . $finalizedInvoice->id . '?mode=live&accountId=' . $this->liveStripeAcountId,
				[]
			);
		} else {
			$generatedPaymentIntent = json_decode( $this->stripe->paymentIntents->retrieve( $stripePaymentIntent )->toJSON() );
			$updatedPaymentIntent = json_decode( $this->stripe->paymentIntents->update(
				$generatedPaymentIntent->id,
				apply_filters( 'fullstripe_payment_intent_parameters', $paymentIntentParameters )
			)->toJSON() );
			$this->stripe->paymentIntents->confirm(
				$updatedPaymentIntent->id,
				[ 'payment_method' => $stripePaymentMethodId ]
			);
			$updatedInvoice = json_decode( $this->stripe->invoices->update( $finalizedInvoice->id )->toJSON() );
		}

		return $updatedInvoice;
	}

	/**
	 * @param $paymentIntentId
	 *
	 * @return \StripeWPFS\Stripe\PaymentIntent
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	function retrievePaymentIntent( $paymentIntentId ) {
		$intent = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$intent = $this->remoteRequest(
				'get',
				'/payment_intents/' . $paymentIntentId . '?mode=test&accountId=' . $this->testStripeAcountId . '&expand[]=latest_charge'
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$intent = $this->remoteRequest(
				'get',
				'/payment_intents/' . $paymentIntentId . '?mode=live&accountId=' . $this->liveStripeAcountId . '&expand[]=latest_charge'
			);
		} else {
			$intent = json_decode( $this->stripe->paymentIntents->retrieve( $paymentIntentId, [ 'expand' => [ 'latest_charge' ] ] )->toJSON() );
		}

		return $intent;
	}

	/**
	 * @param $invoiceId
	 * @param $params
	 *
	 * @return \StripeWPFS\Stripe\Invoice
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	public function retrieveInvoiceWithParams( $invoiceId, $params ) {
		$invoice = null;
		$query_string = $this->build_query( $params );

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$invoice = $this->remoteRequest(
				'get',
				'/invoices/' . $invoiceId . '?mode=test&accountId=' . $this->testStripeAcountId . '&' . $query_string
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$invoice = $this->remoteRequest(
				'get',
				'/invoices/' . $invoiceId . '?mode=live&accountId=' . $this->liveStripeAcountId . '&' . $query_string
			);
		} else {
			$invoice = json_decode( $this->stripe->invoices->retrieve( $invoiceId, $params )->toJSON() );
		}

		return $invoice;
	}

	/**
	 * @param string $invoiceId
	 *
	 * @return object{data: list<object>} A one-item list of the invoice's paid InvoicePayments.
	 * @throws UserFriendlyException
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	public function listPaidInvoicePayments( string $invoiceId ): object {
		$params       = [ 'invoice' => $invoiceId, 'status' => 'paid', 'limit' => 1 ];
		$query_string = $this->build_query( $params );

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			return $this->remoteRequest(
				'get',
				'/invoice_payments?mode=test&accountId=' . $this->testStripeAcountId . '&' . $query_string
			);
		}
		if ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			return $this->remoteRequest(
				'get',
				'/invoice_payments?mode=live&accountId=' . $this->liveStripeAcountId . '&' . $query_string
			);
		}

		return json_decode( $this->stripe->invoicePayments->all( $params )->toJSON() );
	}

	/**
	 * @param $sessionId
	 * @param $params
	 *
	 * @return \StripeWPFS\Stripe\Checkout\Session
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function retrieveCheckoutSessionWithParams( $sessionId, $params ) {
		$checkoutSession = null;
		$query_string = $this->build_query( $params );
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$checkoutSession = $this->remoteRequest(
				'get',
				'/checkout/' .$sessionId . '?mode=test&accountId=' . $this->testStripeAcountId . '&' . $query_string
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$checkoutSession = $this->remoteRequest(
				'get',
				'/checkout/' .$sessionId . '?mode=live&accountId=' . $this->liveStripeAcountId . '&' . $query_string
			);
		} else {
			$this->assertStripeClient();
			$checkoutSession = json_decode( $this->stripe->checkout->sessions->retrieve( $sessionId, $params )->toJSON() );
		}

		return $checkoutSession;
	}

	/**
	 * @param $paymentMethodId
	 *
	 * @return \StripeWPFS\Stripe\PaymentMethod
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function retrievePaymentMethod( $paymentMethodId ) {
		$paymentMethod = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$paymentMethod = $this->remoteRequest(
				'get',
				'/payment_methods/' . $paymentMethodId . '?mode=test&accountId=' . $this->testStripeAcountId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$paymentMethod = $this->remoteRequest(
				'get',
				'/payment_methods/' . $paymentMethodId . '?mode=live&accountId=' . $this->liveStripeAcountId
			);
		} else {
			$paymentMethod = json_decode( $this->stripe->paymentMethods->retrieve( $paymentMethodId )->toJSON() );
		}

		return $paymentMethod;
	}

	/**
	 * @param $eventID
	 *
	 * @return \StripeWPFS\Stripe\Event
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function retrieveEvent( $eventID ) {
		$event = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$event = $this->remoteRequest(
				'get',
				'/events/' . $eventID . '?mode=test&accountId=' . $this->testStripeAcountId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$event = $this->remoteRequest(
				'get',
				'/events/' . $eventID . '?mode=live&accountId=' . $this->liveStripeAcountId
			);
		} else {
			$event = json_decode( $this->stripe->events->retrieve( $eventID )->toJSON() );
		}

		return $event;
	}

	/**
	 * Create a setup intent.
	 * 
	 * @param string|null $customerId Optional customer ID to attach to the SetupIntent
	 * 
	 * @return \StripeWPFS\Stripe\Invoice
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 * 
	 * @see https://docs.stripe.com/api/setup_intents
	 */
	public function createSetupIntent( $customerId = null ) {
		$params = [
			'usage' => 'off_session',
			'metadata' => [
			],
			'automatic_payment_methods' => [
				'enabled' => true,
				'allow_redirects' => 'never', //setup intents do not support redirects
			],
		];

		// Attach customer to SetupIntent if provided. It ensures PaymentMethods can be attached during confirmation
		if ( ! empty( $customerId ) ) {
			$params['customer'] = $customerId;
		}

		$setupIntent = null;
		if ( ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) || ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) ) {
			$accountId = $this->apiMode === 'test' ? $this->testStripeAcountId : $this->liveStripeAcountId;
			$setupIntent = $this->remoteRequest(
				'post',
				'/setup_intents?mode=' . $this->apiMode . '&accountId=' . $accountId,
				$params
			);
		} else {
			$this->assertStripeClient();
			$setupIntent = json_decode( $this->stripe->setupIntents->create( $params )->toJSON() );
		}

		return $setupIntent;
	}

	/**
	 * @param $stripePaymentMethodId
	 * @param string|null $customerId Optional customer ID to attach to the SetupIntent
	 *
	 * @return \StripeWPFS\Stripe\SetupIntent
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function createSetupIntentWithPaymentMethod( $stripePaymentMethodId, $customerId = null ) {
		$params = [
			'usage' => 'off_session',
			'payment_method' => $stripePaymentMethodId,
			'confirm' => false,
			'metadata' => [
			],
			'automatic_payment_methods' => [
				'enabled' => true,
				'allow_redirects' => 'never', //setup intents do not support redirects
			],
		];

		if ( ! empty( $customerId ) ) {
			$params['customer'] = $customerId;
		}

		$intent = null;
		if ( ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) || ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) ) {
			$accountId = $this->apiMode === 'test' ? $this->testStripeAcountId : $this->liveStripeAcountId;
			$intent = $this->remoteRequest(
				'post',
				'/setup_intents?mode=' . $this->apiMode . '&accountId=' . $accountId,
				$params
			);
		} else {
			$intent = json_decode( $this->stripe->setupIntents->create( $params )->toJSON() );
		}

		return $intent;
	}

	/**
	 * @param $stripeSetupIntentId
	 *
	 * @return \StripeWPFS\Stripe\SetupIntent
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	function retrieveSetupIntent( $stripeSetupIntentId ) {
		$intent = null;
		if ( ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) || ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) ) {
			$accountId = $this->apiMode === 'test' ? $this->testStripeAcountId : $this->liveStripeAcountId;
			$intent = $this->remoteRequest(
				'get',
				'/setup_intents/' . $stripeSetupIntentId . '?mode=' . $this->apiMode . '&accountId=' . $accountId
			);
		} else {
			$intent = json_decode( $this->stripe->setupIntents->retrieve( $stripeSetupIntentId )->toJSON() );
		}

		return $intent;
	}

	/**
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	function attachPaymentMethodToStripeCustomer( $stripeCustomer, $currentPaymentMethod ) {
		$attachedPaymentMethod = null;

		if ( isset( $stripeCustomer ) && isset( $currentPaymentMethod ) ) {
			$params = [ 'customer' => $stripeCustomer->id ];
			if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
				$attachedPaymentMethod = $this->remoteRequest(
					'post',
					'/payment_methods/' . $currentPaymentMethod->id . '/attach?mode=test&accountId=' . $this->testStripeAcountId,
					$params
				);
			} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
				$attachedPaymentMethod = $this->remoteRequest(
					'post',
					'/payment_methods/' . $currentPaymentMethod->id . '/attach?mode=live&accountId=' . $this->liveStripeAcountId,
					$params
				);
			} else {
				if ( is_null( $currentPaymentMethod->customer ) ) {
					$this->stripe->paymentMethods->attach(
						$currentPaymentMethod->id,
						$params
					);
					$this->logger->debug( __FUNCTION__, 'PaymentMethod attached.' );
				}
				$attachedPaymentMethod = $currentPaymentMethod;
			}
		}

		return $attachedPaymentMethod;
	}

	/**
	 * Attaches the given PaymentMethod to the given Customer if the Customer do not have an identical PaymentMethod
	 * by card fingerprint.
	 *
	 * @param \StripeWPFS\Stripe\Customer $stripeCustomer
	 * @param \StripeWPFS\Stripe\PaymentMethod $currentPaymentMethod
	 * @param bool $setToDefault
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 * @return \StripeWPFS\Stripe\PaymentMethod the attached PaymentMethod or the existing one
	 */
	function attachPaymentMethodToCustomerIfMissing( $stripeCustomer, $currentPaymentMethod, $setToDefault = false ) {
		$attachedPaymentMethod = null;

		if ( isset( $stripeCustomer ) && isset( $currentPaymentMethod ) ) {
			// WPFS-983: tnagy find existing PaymentMethod with identical fingerprint and reuse it

			/**
			 * @var \StripeWPFS\Stripe\PaymentMethod|null
			 */
			$existingStripePaymentMethod = null;

			if (
				isset($currentPaymentMethod->card) &&
				isset( $currentPaymentMethod->card->fingerprint )
			) {
				$existingStripePaymentMethod = $this->findExistingPaymentMethodByFingerPrintAndExpiry(
					$stripeCustomer,
					$currentPaymentMethod->card->fingerprint,
					$currentPaymentMethod->card->exp_year,
					$currentPaymentMethod->card->exp_month
				);
			}
			if ( isset( $existingStripePaymentMethod ) ) {
				$this->logger->debug(
					__FUNCTION__,
					'PaymentMethod with identical card fingerprint exists, won\'t attach.'
				);

				$attachedPaymentMethod = $existingStripePaymentMethod;
			} else {
				if ( is_null( $currentPaymentMethod->customer ) ) {
					$this->attachPaymentMethodToStripeCustomer( $stripeCustomer, $currentPaymentMethod );

					$this->logger->debug( __FUNCTION__, 'PaymentMethod ' . $currentPaymentMethod->id . ' attached.' );
				}
				$attachedPaymentMethod = $currentPaymentMethod;
			}
			if ( $setToDefault ) {
				$updateCustomerBody = [
					'invoice_settings' => [
						'default_payment_method' => $attachedPaymentMethod->id
					]
				];
				$this->updateCustomerDetails( $stripeCustomer, $updateCustomerBody );
				$this->logger->debug( __FUNCTION__, 'Default PaymentMethod updated.' );
			}

		}

		return $attachedPaymentMethod;
	}

	/**
	 * Find a Customer's PaymentMethod by fingerprint if exists.
	 *
	 * @param \StripeWPFS\Stripe\Customer $stripeCustomer
	 * @param string|null $paymentMethodCardFingerPrint
	 * @param $expiryYear
	 * @param $expiryMonth
	 *
	 * @return null|\StripeWPFS\Stripe\PaymentMethod the existing PaymentMethod
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	public function findExistingPaymentMethodByFingerPrintAndExpiry(
		$stripeCustomer,
		$paymentMethodCardFingerPrint,
		$expiryYear,
		$expiryMonth
	) {
		if ( empty( $paymentMethodCardFingerPrint ) ) {
			return null;
		}

		$paymentMethodBody = [
			'customer' => $stripeCustomer->id,
			'type' => 'card'
		];

		$query_string = $this->build_query( $paymentMethodBody );
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$paymentMethods = $this->remoteRequest(
				'get',
				'/payment_methods?mode=test&accountId=' . $this->testStripeAcountId . '&' . $query_string
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$paymentMethods = $this->remoteRequest(
				'get',
				'/payment_methods?mode=live&accountId=' . $this->liveStripeAcountId . '&' . $query_string
			);
		} else {
			// fallback to using the Stripe API directly
			$paymentMethods = json_decode( $this->stripe->paymentMethods->all( $paymentMethodBody )->toJSON() );
		}
		$existingPaymentMethod = null;
		if ( isset( $paymentMethods ) && isset( $paymentMethods->data ) ) {
			foreach ( $paymentMethods->data as $paymentMethod ) {
				/**
				 * @var \StripeWPFS\Stripe\PaymentMethod $paymentMethod
				 */
				if ( is_null( $existingPaymentMethod ) ) {
					if ( isset( $paymentMethod ) && isset( $paymentMethod->card ) && isset( $paymentMethod->card->fingerprint ) ) {
						if (
							$paymentMethod->card->fingerprint == $paymentMethodCardFingerPrint &&
							$paymentMethod->card->exp_year == $expiryYear &&
							$paymentMethod->card->exp_month == $expiryMonth
						) {
							$existingPaymentMethod = $paymentMethod;
						}
					}
				}
			}
		}

		return $existingPaymentMethod;
	}

	/**
	 * @param string $subscriptionId
	 *
	 * @return object The subscription as Stripe answered the update with it.
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 */
	public function activateCancelledSubscription( $subscriptionId ) {
		$subscription = $this->retrieveSubscription( $subscriptionId );

		do_action( Main::ACTION_NAME_BEFORE_SUBSCRIPTION_ACTIVATION, $subscriptionId );
		if ( is_array( $subscription ) ) {
			$subscription['cancel_at_period_end'] = false;
		} else {
			$subscription->cancel_at_period_end = false;
		}
		$resumedSubscription = $this->updateSubscription($subscription);
		// $this->stripe->subscriptions->update( $subscriptionId, $subscription );

		do_action( Main::ACTION_NAME_AFTER_SUBSCRIPTION_ACTIVATION, $subscriptionId );

		return $resumedSubscription;
	}

	/**
	 * @param $stripeSubscriptionId
	 */
	private function fireBeforeSubscriptionCancellationAction( $stripeSubscriptionId ) {
		do_action( Main::ACTION_NAME_BEFORE_SUBSCRIPTION_CANCELLATION, $stripeSubscriptionId );
	}

	/**
	 * @param $stripeSubscriptionId
	 */
	private function fireAfterSubscriptionCancellationAction( $stripeSubscriptionId ) {
		do_action( Main::ACTION_NAME_AFTER_SUBSCRIPTION_CANCELLATION, $stripeSubscriptionId );
	}

	/**
	 * @param $stripeCustomerId
	 * @param $stripeSubscriptionId
	 * @param bool $atPeriodEnd
	 * @param object|null $subscription The subscription, when the caller already retrieved it. Saves a Stripe GET.
	 *
	 * @return bool
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function cancelSubscription( $stripeCustomerId, $stripeSubscriptionId, $atPeriodEnd = false, $subscription = null ) {
		if ( ! empty( $stripeSubscriptionId ) ) {
			if ( is_null( $subscription ) ) {
				$subscription = $this->retrieveSubscription( $stripeSubscriptionId );
			}

			if ( $subscription ) {
				$this->fireBeforeSubscriptionCancellationAction( $stripeSubscriptionId );

				/** @noinspection PhpUnusedLocalVariableInspection */
				$cancellationResult = null;
				if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
					if ( $atPeriodEnd ) {
						$this->remoteRequest(
							'post',
							'/subscriptions/' . $stripeSubscriptionId . '?mode=test&accountId=' . $this->testStripeAcountId,
							[
								'cancel_at_period_end' => true
							]
						);
					} else {
						$cancellationResult = $this->remoteRequest(
							'delete',
							'/subscriptions/' . $stripeSubscriptionId . '?mode=test&accountId=' . $this->testStripeAcountId
						);
					}
				} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
					if ( $atPeriodEnd ) {
						$this->remoteRequest(
							'post',
							'/subscriptions/' . $stripeSubscriptionId . '?mode=live&accountId=' . $this->liveStripeAcountId,
							[
								'cancel_at_period_end' => true
							]
						);
					} else {
						$cancellationResult = $this->remoteRequest(
							'delete',
							'/subscriptions/' . $stripeSubscriptionId . '?mode=live&accountId=' . $this->liveStripeAcountId
						);
					}
				} else {
					if ( $atPeriodEnd ) {
						$cancellationResult = json_decode( $this->stripe->subscriptions->update(
							$stripeSubscriptionId,
							[
								'cancel_at_period_end' => true
							]
						)->toJSON() );
					} else {
						$cancellationResult = $this->stripe->subscriptions->cancel( $stripeSubscriptionId );
					}
				}

				$this->fireAfterSubscriptionCancellationAction( $stripeSubscriptionId );

				if ( isset( $cancellationResult ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Cancels the subscription unless Stripe already ended it, when its webhook was missed or its first payment
	 * failed: Stripe can't cancel those, so the caller only records the cancellation.
	 *
	 * @param string $stripeCustomerId
	 * @param string|null $stripeSubscriptionId
	 * @param bool $atPeriodEnd
	 * @param object|null $subscription The subscription, when the caller already retrieved it. Saves a Stripe GET.
	 *
	 * @return bool Whether Stripe confirmed the cancellation or had ended the subscription already.
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function cancelSubscriptionUnlessEnded( $stripeCustomerId, $stripeSubscriptionId, $atPeriodEnd = false, $subscription = null ) {
		if ( empty( $stripeSubscriptionId ) ) {
			return false;
		}

		if ( is_null( $subscription ) ) {
			$subscription = $this->retrieveSubscription( $stripeSubscriptionId );
		}
		$endedStatuses = [ \StripeWPFS\Stripe\Subscription::STATUS_CANCELED, \StripeWPFS\Stripe\Subscription::STATUS_INCOMPLETE_EXPIRED ];
		if ( isset( $subscription->status ) && in_array( $subscription->status, $endedStatuses, true ) ) {
			return true;
		}

		return $this->cancelSubscription( $stripeCustomerId, $stripeSubscriptionId, $atPeriodEnd, $subscription );
	}

	/**
	 * @param $params array
	 *
	 * @return \StripeWPFS\Stripe\Collection
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function listSubscriptionsWithParams( $params ) {
		$subscriptions = null;
		$query_string = $this->build_query( $params );
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$subscriptions = $this->remoteRequest(
				'get',
				'/subscriptions?mode=test&accountId=' . $this->testStripeAcountId . '&' . $query_string
			);
			$subscriptions = $subscriptions->data;
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$subscriptions = $this->remoteRequest(
				'get',
				'/subscriptions?mode=live&accountId=' . $this->liveStripeAcountId . '&' . $query_string
			);
			$subscriptions = $subscriptions->data;
		} else {
			$subscriptions = json_decode( $this->stripe->subscriptions->all( $params )->toJSON() );
			$subscriptions = $subscriptions->data;
		}
		return $subscriptions;
	}

	/**
	 * @param $params array
	 *
	 * @return list<object>
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function listInvoicesWithParams( $params ) {
		return $this->listInvoicesPageWithParams( $params )->data;
	}

	/**
	 * Lists one page of invoices; page further with `starting_after` while `has_more` is true.
	 *
	 * @param array<string, mixed> $params
	 *
	 * @return object{data: list<object>, has_more: bool}
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function listInvoicesPageWithParams( $params ) {
		$query_string = $this->build_query( $params );
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$invoices = $this->remoteRequest(
				'get',
				'/invoices?mode=test&accountId=' . $this->testStripeAcountId. '&' . $query_string
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$invoices = $this->remoteRequest(
				'get',
				'/invoices?mode=live&accountId=' . $this->liveStripeAcountId. '&' . $query_string
			);
		} else {
			$invoices = json_decode( $this->stripe->invoices->all( $params )->toJSON() );
		}
		return $invoices;
	}

	/**
	 * @param $params array
	 *
	 * @return \StripeWPFS\Stripe\Collection
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function listPaymentMethodsWithParams( $params ) {
		$paymentMethods = null;
		$query_string = $this->build_query( $params );

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$paymentMethods = $this->remoteRequest(
				'get',
				'/payment_methods?mode=test&accountId=' . $this->testStripeAcountId . '&' . $query_string
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$paymentMethods = $this->remoteRequest(
				'get',
				'/payment_methods?mode=live&accountId=' . $this->liveStripeAcountId . '&' . $query_string
			);
		} else {
			$paymentMethods = json_decode( $this->stripe->paymentMethods->all( $params )->toJSON() );
		}
		return $paymentMethods;
	}

	/**
	 * @param $subscriptionID
	 *
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	function retrieveSubscription( $subscriptionID ) {
		$subscription = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$subscription = $this->remoteRequest(
				'get',
				'/subscriptions/' . $subscriptionID . '?mode=test&accountId=' . $this->testStripeAcountId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$subscription = $this->remoteRequest(
				'get',
				'/subscriptions/' . $subscriptionID . '?mode=live&accountId=' . $this->liveStripeAcountId
			);
		} else {
			$this->assertStripeClient();
			$subscription = json_decode( $this->stripe->subscriptions->retrieve( $subscriptionID )->toJSON() );
		}
		return $subscription;
	}

	/**
	 * @param $subscriptionId string
	 * @param $params array
	 *
	 * @return \StripeWPFS\Stripe\Subscription
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function retrieveSubscriptionWithParams( $subscriptionId, $params ) {
		$stripeSubscription = null;
		$query_string = $this->build_query( $params );
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$stripeSubscription = $this->remoteRequest(
				'get',
				'/subscriptions/' . $subscriptionId . '?mode=test&accountId=' . $this->testStripeAcountId . '&' . $query_string
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$stripeSubscription = $this->remoteRequest(
				'get',
				'/subscriptions/' . $subscriptionId . '?mode=live&accountId=' . $this->liveStripeAcountId . '&' . $query_string
			);
		} else {
			$this->assertStripeClient();
			$stripeSubscription = json_decode( $this->stripe->subscriptions->retrieve(
				$subscriptionId,
				$params
			)->toJSON() );
		}

		return $stripeSubscription;
	}

	/**
	 * @param $stripeSubscriptionId
	 * @param $newPlanId
	 * @param $newQuantity
	 */
	protected function fireBeforeSubscriptionUpdateAction( $stripeSubscriptionId, $newPlanId, $newQuantity ) {
		$params = [ 
			'stripeSubscriptionId' => $stripeSubscriptionId,
			'planId' => $newPlanId,
			'quantity' => $newQuantity
		];

		do_action( Main::ACTION_NAME_BEFORE_SUBSCRIPTION_UPDATE, $params );
	}

	/**
	 * @param $stripeSubscriptionId
	 * @param $newPlanId
	 * @param $newQuantity
	 */
	protected function fireAfterSubscriptionUpdateAction( $stripeSubscriptionId, $newPlanId, $newQuantity ) {
		$params = [ 
			'stripeSubscriptionId' => $stripeSubscriptionId,
			'planId' => $newPlanId,
			'quantity' => $newQuantity
		];

		do_action( Main::ACTION_NAME_AFTER_SUBSCRIPTION_UPDATE, $params );
	}

	/**
	 * @param $stripeCustomerId
	 * @param $stripeSubscriptionId
	 * @param $planId
	 * @param $newPlanQuantity
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 * @return bool
	 */
	public function updateSubscriptionPlanAndQuantity(
		$stripeCustomerId,
		$stripeSubscriptionId,
		$planId,
		$planQuantity = null
	) {
		if ( ! empty( $stripeSubscriptionId ) && ! empty( $planId ) ) {
			/* @var $subscription \StripeWPFS\Stripe\Subscription */
			$subscription = $this->retrieveSubscription( $stripeSubscriptionId );

			// The plan's item holds the plan and quantity: a subscription with a fee recovery line has none of its own.
			$items = isset( $subscription->items->data ) ? $subscription->items->data : [];
			$feeRecovery = count( $items ) > 1 ? $this->findFeeRecoveryOfSubscription( $subscription ) : null;
			$planItem = is_null( $feeRecovery ) ? ( isset( $items[0] ) ? $items[0] : null ) : $feeRecovery['planItem'];
			$currentPlanId = isset( $planItem->price->id ) ? $planItem->price->id : null;
			$currentPlanQuantity = isset( $planItem->quantity ) ? intval( $planItem->quantity ) : 1;

			if ( ! empty( $planQuantity ) && is_numeric( $planQuantity ) ) {
				$newPlanQuantity = intval( $planQuantity );
			} else {
				$newPlanQuantity = $currentPlanQuantity;
			}

			if ( isset( $subscription ) ) {
				$parameters = [];
				$performUpdate = false;
				$planUpdated = false;
				// tnagy update subscription plan
				if ( (string) $currentPlanId !== (string) $planId ) {
					$parameters = array_merge( $parameters, [ 'plan' => $planId ] );
					$planUpdated = true;
					$performUpdate = true;
				}
				// tnagy update subscription quantity
				$allowMultipleSubscriptions = false;
				if ( isset( $subscription->metadata ) && isset( $subscription->metadata->allow_multiple_subscriptions ) ) {
					$allowMultipleSubscriptions = boolval( $subscription->metadata->allow_multiple_subscriptions );
				}
				$minimumQuantity = Utils::getMinimumPlanQuantityOfSubscription( $subscription );
				$maximumQuantity = Utils::getMaximumPlanQuantityOfSubscription( $subscription );
				if ( $allowMultipleSubscriptions ) {
					if ( $minimumQuantity > 0 && $newPlanQuantity < $minimumQuantity ) {
						throw new \Exception(
							sprintf(
								/* translators: Error message displayed when subscriber tries to set a quantity for a subscription which is beyond allowed value */
								__(
									"Subscription quantity '%d' is not allowed for this subscription!",
									'wp-full-stripe-free'
								),
								$newPlanQuantity
							)
						);
					}
					if ( $maximumQuantity > 0 && $newPlanQuantity > $maximumQuantity ) {
						throw new \Exception(
							sprintf(
								/* translators: Error message displayed when subscriber tries to set a quantity for a subscription which is over allowed value */
								__(
									"Subscription quantity '%d' is not allowed for this subscription!",
									'wp-full-stripe-free'
								),
								$newPlanQuantity
							)
						);
					}
					if ( $currentPlanQuantity !== $newPlanQuantity || $planUpdated ) {
						$parameters = array_merge( $parameters, [ 'quantity' => $newPlanQuantity ] );
						$performUpdate = true;
					}
				} elseif ( $newPlanQuantity > 1 ) {
					throw new \Exception(
						/* translators: Error message displayed when subscriber tries to set a quantity for a
						 * subscription where quantity other than one is not allowed.
						 */
						__( 'Quantity update is not allowed for this subscription!', 'wp-full-stripe-free' )
					);
				}
			} else {
				throw new \Exception(
					sprintf(
						/* translators: Error message displayed when a subscription is not found.
						 * p1: Subscription identifier
						 */
						__( "Subscription '%s' not found!", 'wp-full-stripe-free' ),
						$stripeSubscriptionId
					)
				);
			}
			if ( $performUpdate && ( $planUpdated || ! is_null( $feeRecovery ) ) ) {
				$plan = $this->retrievePlanToChangeTo( $planId, $planUpdated );
				if ( ! is_null( $feeRecovery ) ) {
					// Stripe takes the plan and quantity of a subscription with several items only through its items.
					$parameters = [
						'items' => $this->buildPlanChangeItems( $feeRecovery, $plan, $newPlanQuantity ),
					];
				}
			}
			if ( $performUpdate ) {
				$this->fireBeforeSubscriptionUpdateAction( $stripeSubscriptionId, $planId, $newPlanQuantity );
				try {
					if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
						$this->remoteRequest(
							'post',
							'/subscriptions/' . $stripeSubscriptionId . '?mode=test&accountId=' . $this->testStripeAcountId,
							$parameters
						);
					} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
						$this->remoteRequest(
							'post',
							'/subscriptions/' . $stripeSubscriptionId . '?mode=live&accountId=' . $this->liveStripeAcountId,
							$parameters
						);
					} else {
						$this->stripe->subscriptions->update( $stripeSubscriptionId, $parameters );
					}
				} catch ( \Exception $ex ) {
					// Stripe Tax refuses a plan without a tax behavior on an account without a default one.
					throw $this->recognizeTaxBehaviorRefusal( $ex );
				}
				$this->fireAfterSubscriptionUpdateAction( $stripeSubscriptionId, $planId, $newPlanQuantity );
			}

			return true;
		} else {
			throw new \Exception(
				/* translators: Error when a subscription update request has no valid plan or quantity */
				__( 'Invalid parameters!', 'wp-full-stripe-free' )
			);
		}
	}

	/**
	 * Finds the plan's and the fee's item of a subscription created with a fee recovery line. The plan's item is the
	 * one priced with the plan the subscriber row records, so the order Stripe lists the items in doesn't matter.
	 * Any other subscription with several items is refused: it may hold products the plugin doesn't manage.
	 *
	 * @param object $subscription The subscription as retrieveSubscription() decodes it.
	 *
	 * @return array{planItem: object, feeItem: object, feeRecoveryData: array<string, mixed>}
	 * @throws \Exception
	 */
	private function findFeeRecoveryOfSubscription( $subscription ) {
		$feeRecovery = RecoveryFee::findFeeRecoveryOfSubscription( $subscription, [ $this, 'retrieveProduct' ] );
		if ( ! is_null( $feeRecovery ) && ! empty( $feeRecovery['feeRecoveryData'] ) ) {
			return $feeRecovery;
		}

		throw new \Exception(
			/* translators: Error message displayed when the plan of a subscription with several products is to be changed */
			__( 'The plan of this subscription cannot be changed because it has several products.', 'wp-full-stripe-free' )
		);
	}

	/**
	 * The plan a subscription is changed to, retrieved with its product. Stripe refuses to move a subscription to a
	 * plan, or a plan of a product, that the site owner archived, so a plan change to one is refused before the update
	 * hooks fire.
	 *
	 * @param string $planId
	 * @param bool $planUpdated Whether the subscription moves to this plan, rather than keeping it.
	 *
	 * @return object
	 * @throws \Exception
	 */
	private function retrievePlanToChangeTo( $planId, $planUpdated ) {
		$plan = $this->retrievePlan( $planId );
		if ( is_null( $plan ) ) {
			throw new \Exception(
				/* translators: Error message displayed when the plan a subscription is changed to is not found. p1: Plan identifier */
				sprintf( __( "Plan '%s' not found!", 'wp-full-stripe-free' ), $planId )
			);
		}
		if ( $planUpdated && Utils::isArchivedPrice( $plan ) ) {
			throw new \Exception( __( 'This plan is no longer available. Please choose another plan.', 'wp-full-stripe-free' ) );
		}

		return $plan;
	}

	/**
	 * The item changes of a plan change on a subscription with a fee recovery line: the plan's item takes the new
	 * plan and quantity, and the fee is repriced for them with the form's fee settings.
	 *
	 * @param array{planItem: object, feeItem: object, feeRecoveryData: array<string, mixed>} $feeRecovery
	 * @param object $plan The plan the subscription is changed to, as retrievePlan() decodes it.
	 * @param int $planQuantity
	 *
	 * @return array<int, array<string, mixed>>
	 * @throws \Exception
	 */
	private function buildPlanChangeItems( $feeRecovery, $plan, $planQuantity ) {
		$feeItem = $feeRecovery['feeItem'];
		$feeRecoveryData = $feeRecovery['feeRecoveryData'];
		$feePriceData = [
			'currency' => $plan->currency,
			'product' => $this->findFeeProductId( $feeItem, $plan ),
			'unit_amount' => Utils::calculateRecoveryFee(
				$plan->unit_amount * $planQuantity,
				$feeRecoveryData[ Options::OPTION_FEE_RECOVERY_FEE_PERCENTAGE ],
				$feeRecoveryData[ Options::OPTION_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT ],
				$plan->currency
			),
			'recurring' => [
				'interval' => $plan->recurring->interval,
				'interval_count' => $plan->recurring->interval_count,
			],
		];
		// Keep how tax applies to the fee, rather than falling back to the account's default.
		if ( isset( $feeItem->price->tax_behavior ) && in_array( $feeItem->price->tax_behavior, [ 'inclusive', 'exclusive' ], true ) ) {
			$feePriceData['tax_behavior'] = $feeItem->price->tax_behavior;
		}

		return [
			[
				'id' => $feeRecovery['planItem']->id,
				'price' => $plan->id,
				'quantity' => $planQuantity,
			],
			[
				'id' => $feeItem->id,
				'price_data' => $feePriceData,
				'quantity' => 1,
				// The repriced fee loses the lookup key it was recognised by, so mark the item itself.
				'metadata' => [ self::METADATA_KEY_RECOVERY_FEE_ITEM => '1' ],
			],
		];
	}

	/**
	 * The product a repriced fee is billed on: its own, unless that is inactive. Stripe refuses new prices on an
	 * inactive product, and Checkout creates its fee line's product inactive, so such a fee moves to the product of
	 * the shared fee price inline subscriptions use for the plan's currency and billing period.
	 *
	 * @param object $feeItem
	 * @param object $plan
	 *
	 * @return string
	 * @throws \Exception
	 */
	private function findFeeProductId( $feeItem, $plan ) {
		$product = is_object( $feeItem->price->product ) ? $feeItem->price->product : $this->retrieveProduct( $feeItem->price->product );
		if ( ! empty( $product->active ) ) {
			return $product->id;
		}

		$intervalCount = isset( $plan->recurring->interval_count ) ? (int) $plan->recurring->interval_count : 1;
		$sharedFeePrice = ( new SubscriptionContextCreator( null, null, $this ) )->createSubscriptionForRecoveryFee( $plan->currency, $plan->recurring->interval, $intervalCount );

		return is_object( $sharedFeePrice->product ) ? $sharedFeePrice->product->id : $sharedFeePrice->product;
	}

	/**
	 * @param $chargeId
	 *
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	function captureCharge( $chargeId ) {
		$charge = null;

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$charge = $this->remoteRequest(
				'get',
				'/charges/' . $chargeId . '?mode=test&accountId=' . $this->testStripeAcountId
			);

			if ( isset( $charge ) ) {
				return $this->remoteRequest(
					'post',
					'/charges/' . $chargeId . '/capture?mode=test&accountId=' . $this->testStripeAcountId
				);
			}

		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$charge = $this->remoteRequest(
				'get',
				'/charges/' . $chargeId . '?mode=live&accountId=' . $this->liveStripeAcountId
			);

			if ( isset( $charge ) ) {
				return $this->remoteRequest(
					'post',
					'/charges/' . $chargeId . '/capture?mode=live&accountId=' . $this->liveStripeAcountId
				);
			}
		} else {
			$charge = json_decode( $this->stripe->charges->retrieve( $chargeId )->toJSON() );
		}

		return $charge;
	}

	/**
	 * The receipt of the charge that paid a PaymentIntent. Since API version 2025-03-31.basil an invoice no longer
	 * carries its charge, so a subscription's receipt is found through the PaymentIntent that paid its invoice.
	 *
	 * @param object|null $paymentIntent
	 * @return string|null
	 */
	public function getReceiptUrl( $paymentIntent ) {
		if ( empty( $paymentIntent->latest_charge ) ) {
			return null;
		}
		$charge = $this->getLatestCharge( $paymentIntent );

		return isset( $charge->receipt_url ) ? $charge->receipt_url : null;
	}

	public function getLatestCharge( $paymentIntent ) {
		if ( is_string( $paymentIntent->latest_charge ) ) {
			$charge = null;
			if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
				$charge = $this->remoteRequest(
					'get',
					'/charges/' . $paymentIntent->latest_charge . '?mode=test&accountId=' . $this->testStripeAcountId
				);
			} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
				$charge = $this->remoteRequest(
					'get',
					'/charges/' . $paymentIntent->latest_charge . '?mode=live&accountId=' . $this->liveStripeAcountId
				);
			} else {
				$charge = json_decode( $this->stripe->charges->retrieve( $paymentIntent->latest_charge )->toJSON() );
			}
			return $charge;
		} else {
			return $paymentIntent->latest_charge;
		}
	}

	/**
	 * @param $paymentIntentId
	 *
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function capturePaymentIntent( $paymentIntentId ) {
		$paymentIntent = $this->retrievePaymentIntent( $paymentIntentId );
		if ( isset( $paymentIntent ) ) {
			if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
				return $this->remoteRequest(
					'post',
					'/payment_intents/' . $paymentIntent->id . '/capture?mode=test&accountId=' . $this->testStripeAcountId
				);
			} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
				return $this->remoteRequest(
					'post',
					'/payment_intents/' . $paymentIntent->id . '/capture?mode=live&accountId=' . $this->liveStripeAcountId
				);
			} else {
				return json_decode( $this->stripe->paymentIntents->capture( $paymentIntentId )->toJSON() );
			}
		}

		return $paymentIntent;
	}

	/**
	 * @param $chargeId
	 *
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	function refundCharge( $chargeId ) {
		$refund = null;
		$refundBody = [
			'charge' => $chargeId->id ? $chargeId->id : $chargeId
		];

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$refund = $this->remoteRequest(
				'post',
				'/refunds?mode=test&accountId=' . $this->testStripeAcountId,
				$refundBody
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$refund = $this->remoteRequest(
				'post',
				'/refunds?mode=live&accountId=' . $this->liveStripeAcountId,
				$refundBody
			);
		} else {
			$refund = json_decode( $this->stripe->refunds->create( $refundBody )->toJSON() );
		}
		return $refund;
	}

	/**
	 * @param $paymentIntentId
	 *
	 * @return \StripeWPFS\Stripe\PaymentIntent|\StripeWPFS\Stripe\Refund
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function cancelOrRefundPaymentIntent( $paymentIntentId ) {
		$paymentIntent = $this->retrievePaymentIntent( $paymentIntentId );
		if ( isset( $paymentIntent ) ) {
			/* @var $paymentIntent \StripeWPFS\Stripe\PaymentIntent */
			if (
				\StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD === $paymentIntent->status
				|| \StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_CAPTURE === $paymentIntent->status
				|| \StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_CONFIRMATION === $paymentIntent->status
				|| \StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_ACTION === $paymentIntent->status
			) {
				if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
					return $this->remoteRequest(
						'post',
						'/payment_intents/' . $paymentIntent->id . '/cancel?mode=test&accountId=' . $this->testStripeAcountId
					);
				} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
					return $this->remoteRequest(
						'post',
						'/payment_intents/' . $paymentIntent->id . '/cancel?mode=live&accountId=' . $this->liveStripeAcountId
					);
				} else {
					return json_decode( $this->stripe->paymentIntents->cancel( $paymentIntentId )->toJSON() );
				}
			} elseif (
				\StripeWPFS\Stripe\PaymentIntent::STATUS_PROCESSING === $paymentIntent->status
				|| \StripeWPFS\Stripe\PaymentIntent::STATUS_SUCCEEDED === $paymentIntent->status
			) {
				return $this->refundCharge( $paymentIntent->latest_charge );
			}
		}
		return $paymentIntent;
	}

	/**
	 * Update payment intent with metadata and description
	 *
	 * @param $paymentIntent
	 * @param bool $includeAmount
	 * @param $stripeReceiptEmailAddress
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function updatePaymentIntent( $paymentIntent, $includeAmount = false, $stripeReceiptEmailAddress = null ) {
		$updateIntentBody = [
			"metadata" => $paymentIntent->metadata,
			"description" => $paymentIntent->description,
		];

		if ( isset( $stripeReceiptEmailAddress ) ) {
			$updateIntentBody['receipt_email'] = $stripeReceiptEmailAddress;
		}

		if ( $includeAmount && isset( $paymentIntent->amount ) ) {
			$updateIntentBody["amount"] = $paymentIntent->amount;
		}

		if ( ! empty( $paymentIntent->customerId ) ) {
			$updateIntentBody['customer'] = $paymentIntent->customerId;
		}

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$updateIntentBody['validLicense'] = $this->hasValidLicense();
			$this->remoteRequest(
				'post',
				'/payment_intents/' . $paymentIntent->id . '?mode=test&accountId=' . $this->testStripeAcountId . '&apiVersion=' . $this->userVersion,
				$updateIntentBody
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$updateIntentBody['validLicense'] = $this->hasValidLicense();
			$this->remoteRequest(
				'post',
				'/payment_intents/' . $paymentIntent->id . '?mode=live&accountId=' . $this->liveStripeAcountId . '&apiVersion=' . $this->userVersion,
				$updateIntentBody
			);
		} else {
			// update the payment intent using the stripe API
			$params = [
				'metadata' => $paymentIntent->metadata,
				'description' => $paymentIntent->description,
			];
			if ( $includeAmount && isset( $paymentIntent->amount ) ) {
				$params["amount"] = $paymentIntent->amount;
			}
			if ( ! empty( $paymentIntent->customerId ) ) {
				$params['customer'] = $paymentIntent->customerId;
			}
			$this->stripe->paymentIntents->update( $paymentIntent->id, $params );
		}
	}

	/**
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function updateCustomer( $stripeCustomer ) {
		$address = null;
		$shipping = null;
		// trying to deal with the different types of objects the customer and/or the address can have
		if ( is_object( $stripeCustomer->address ) && get_class( $stripeCustomer->address ) === 'stdClass' ) {
			$address = json_decode( json_encode( $stripeCustomer->address ), true );
		} elseif ( is_array( $stripeCustomer->address ) ) {
			$address = $stripeCustomer->address;
		} elseif ( isset( $stripeCustomer->address ) ) {
			$address = $stripeCustomer->address->toArray();
		}
		if ( is_object( $stripeCustomer->shipping ) && get_class( $stripeCustomer->shipping ) === 'stdClass' ) {
			$shipping = json_decode( json_encode( $stripeCustomer->shipping ), true );
		} elseif ( is_array( $stripeCustomer->shipping ) ) {
			$shipping = $stripeCustomer->shipping;
		} elseif ( isset( $stripeCustomer->shipping ) ) {
			$shipping = $stripeCustomer->shipping->toArray();
		}

		// update the customer using the stripe API
		$params = [
			'address' => $address,
			'name' => $stripeCustomer->name,
			'shipping' => $shipping,
			'description' => $stripeCustomer->description,
		];
		$this->updateCustomerDetails( $stripeCustomer, $params );
	}

	/**
	 * Sets the customer's description only, so changes made to its other fields since it was retrieved are kept.
	 *
	 * @param \StripeWPFS\Stripe\Customer|\stdClass $stripeCustomer
	 * @param string $description
	 * @return void
	 */
	public function updateCustomerDescription( $stripeCustomer, $description ) {
		$this->updateCustomerDetails( $stripeCustomer, [ 'description' => $description ] );
	}

	/**
	 * Update subscription metadata
	 *
	 * @param $stripeSubscription
	 * @return object The subscription as Stripe answered the update with it.
	 * @throws UserFriendlyException
	 */
	public function updateSubscription( $stripeSubscription ) {
		// we're just updating the metadata at this point
		// also handle cancellation updates

		$subscriptionMetadata = [
			'metadata' => $stripeSubscription->metadata,
			'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end
		];

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			return $this->remoteRequest(
				'post',
				'/subscriptions/' . $stripeSubscription->id . '?mode=test&accountId=' . $this->testStripeAcountId,
				$subscriptionMetadata
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			return $this->remoteRequest(
				'post',
				'/subscriptions/' . $stripeSubscription->id . '?mode=live&accountId=' . $this->liveStripeAcountId,
				$subscriptionMetadata
			);
		}

		// update the subscription using the stripe API
		return json_decode( $this->stripe->subscriptions->update( $stripeSubscription->id, $subscriptionMetadata )->toJSON() );
	}

	/**
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function confirmSetupIntent( $setupIntent ) {
		$params = [
			'payment_method' => $setupIntent->payment_method
		];

		if ( ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) || ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) ) {
			$accountId = $this->apiMode === 'test' ? $this->testStripeAcountId : $this->liveStripeAcountId;
			$setupIntent = $this->remoteRequest(
				'post',
				'/setup_intents/' . $setupIntent->id . '/confirm?mode=' . $this->apiMode . '&accountId=' . $accountId,
				$params
			);
		} else {
			// confirm the setup intent using the stripe API
			$setupIntent = json_decode( $this->stripe->setupIntents->confirm(
				$setupIntent->id,
				$params
			)->toJSON() );
		}

		return $setupIntent;
	}

	/**
	 * Confirms a payment intent with the provided payment intent ID and payment method ID.
	 *
	 * @param string $paymentIntentId The ID of the payment intent.
	 * @param string $paymentMethodId The ID of the payment method.
	 */
	public function confirmPaymentIntent( $paymentIntentId, $paymentMethodId ) {
		$paymentIntent = null;
		$params = [
			'payment_method' => $paymentMethodId
		];

		if ( ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) || ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) ) {
			$accountId = $this->apiMode === 'test' ? $this->testStripeAcountId : $this->liveStripeAcountId;
			$paymentIntent = $this->remoteRequest(
				'post',
				'/payment_intents/' . $paymentIntentId . '/confirm?mode=' . $this->apiMode . '&accountId=' . $accountId,
				$params
			);
		} else {
			// confirm the setup intent using the stripe API
			$paymentIntent = json_decode( $this->stripe->paymentIntents->confirm(
				$paymentIntentId,
				$params
			)->toJSON() );
		}
		return $paymentIntent;
	}

	private function updateCustomerDetails( $stripeCustomer, $params ) {
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$this->remoteRequest(
				'post',
				'/customers/' . $stripeCustomer->id . '?mode=test&accountId=' . $this->testStripeAcountId,
				$params
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$this->remoteRequest(
				'post',
				'/customers/' . $stripeCustomer->id . '?mode=live&accountId=' . $this->liveStripeAcountId,
				$params
			);
		} else {
			$this->stripe->customers->update( $stripeCustomer->id, $params );
		}
	}

	/**
	 * @param $parameters array
	 *
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function createCheckoutSession( $parameters ) {
		try {
			return $this->requestCheckoutSession( $parameters );
		} catch ( \Exception $ex ) {
			throw $this->recognizeTaxBehaviorRefusal( $ex );
		}
	}

	/**
	 * @param \Exception $ex An error of a Stripe Tax request.
	 * @return \Exception The error to throw: Stripe Tax's refusal of a price without a tax behavior, recorded for the
	 *                    admin notice, or the error itself.
	 */
	private function recognizeTaxBehaviorRefusal( \Exception $ex ): \Exception {
		$error = StripeTaxBehaviorException::fromStripeError( $ex );
		if ( $error instanceof StripeTaxBehaviorException ) {
			StripeTaxBehaviorNotice::recordRefusal( (string) $this->apiMode );
		}

		return $error;
	}

	/**
	 * @param array<string, mixed> $parameters
	 * @return mixed The session, decoded from Stripe's or the platform's JSON.
	 *
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	private function requestCheckoutSession( $parameters ) {
		$session = null;
		$parameters = apply_filters( 'fullstripe_checkout_session_parameters', $parameters );
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$parameters = array_merge( $parameters, [ 'validLicense' => $this->hasValidLicense() ] );
			$session = $this->remoteRequest(
				'post',
				'/checkout?mode=test&accountId=' . $this->testStripeAcountId . '&apiVersion=' . $this->userVersion,
				apply_filters( 'fullstripe_checkout_session_parameters', $parameters )
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$parameters = array_merge( $parameters, [ 'validLicense' => $this->hasValidLicense() ] );
			$session = $this->remoteRequest(
				'post',
				'/checkout?mode=live&accountId=' . $this->liveStripeAcountId . '&apiVersion=' . $this->userVersion,
				apply_filters( 'fullstripe_checkout_session_parameters', $parameters )
			);
		} else {
			$session = json_decode( $this->stripe->checkout->sessions->create(
				apply_filters(
					'fullstripe_checkout_session_parameters',
					$parameters
				)
			)->toJSON() );
		}
		return $session;
	}

	/**
	 * @param string $stripeInvoiceId
	 * @param string|null $idempotencyKey Makes Stripe answer a request sent again with the invoice it already paid.
	 * @throws UserFriendlyException
	 */
	public function payInvoiceOutOfBand( $stripeInvoiceId, $idempotencyKey = null ) {
		$invoice = null;
		$headers = empty( $idempotencyKey ) ? [] : [ 'Idempotency-Key' => $idempotencyKey ];
		$requestOptions = empty( $idempotencyKey ) ? [] : [ 'idempotency_key' => $idempotencyKey ];
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$invoice = $this->remoteRequest(
				'post',
				'/invoices/' . $stripeInvoiceId . '/pay?mode=test&accountId=' . $this->testStripeAcountId,
				[ 'paid_out_of_band' => true ],
				$headers
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$invoice = $this->remoteRequest(
				'post',
				'/invoices/' . $stripeInvoiceId . '/pay?mode=live&accountId=' . $this->liveStripeAcountId,
				[ 'paid_out_of_band' => true ],
				$headers
			);
		} else {
			$invoice = json_decode( $this->stripe->invoices->pay(
				$stripeInvoiceId,
				[ 'paid_out_of_band' => true ],
				$requestOptions
			)->toJSON() );
		}

		return $invoice;
	}

	/**
	 * @param $stripeInvoice
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function finalizeInvoice( $stripeInvoiceId ) {
		$invoice = null;
		$params = [
			'expand' => [ 'payments' ]
		];

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$invoice = $this->remoteRequest(
				'post',
				'/invoices/' . $stripeInvoiceId . '/finalize?mode=test&accountId=' . $this->testStripeAcountId,
				$params
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$invoice = $this->remoteRequest(
				'post',
				'/invoices/' . $stripeInvoiceId . '/finalize?mode=live&accountId=' . $this->liveStripeAcountId,
				$params
			);
		} else {
			if ( is_null( $this->stripe ) ) {
				throw new UserFriendlyException( __( 'Stripe client is not initialized', 'wp-full-stripe-free' ) );
			}
			$invoice = json_decode( $this->stripe->invoices->finalizeInvoice( $stripeInvoiceId, $params )->toJSON() );
		}
		return $invoice;
	}

	/**
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function getUpcomingInvoice( $invoiceParams ) {
		try {
			return $this->requestUpcomingInvoice( $invoiceParams );
		} catch ( \Exception $ex ) {
			throw $this->recognizeTaxBehaviorRefusal( $ex );
		}
	}

	/**
	 * @param array<string, mixed> $invoiceParams
	 * @return mixed The preview invoice, decoded from Stripe's or the platform's JSON.
	 *
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	private function requestUpcomingInvoice( $invoiceParams ) {
		$invoice = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$invoice = $this->remoteRequest(
				'post',
				'/invoices/preview?mode=test&accountId=' . $this->testStripeAcountId,
				$invoiceParams
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$invoice = $this->remoteRequest(
				'post',
				'/invoices/preview?mode=live&accountId=' . $this->liveStripeAcountId,
				$invoiceParams
			);
		} else {
			if ( is_null( $this->stripe ) ) {
				throw new UserFriendlyException( __( 'Stripe client is not initialized', 'wp-full-stripe-free' ) );
			}
			$invoice = json_decode( $this->stripe->invoices->createPreview( $invoiceParams )->toJSON() );
		}
		return $invoice;
	}

	/**
	 * @return \StripeWPFS\Stripe\StripeClient
	 */
	public function getStripeClient() {
		return $this->stripe;
	}

	/**
	 * @param $priceIds array
	 *
	 * @return array
	 * @throws UserFriendlyException
	 */
	public function retrieveProductsByPriceIds( $priceIds ) {
		$products = [];
		$params = [ 'expand' => [ 'product' ] ];
		$query_string = $this->build_query( $params );

		foreach ( $priceIds as $priceId ) {
			$price = null;

			if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
				$price = $this->remoteRequest(
					'get',
					'/prices/' . $priceId . '?mode=test&accountId=' . $this->testStripeAcountId . '&' . $query_string
				);
			} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
				$price = $this->remoteRequest(
					'get',
					'/prices/' . $priceId . '?mode=live&accountId=' . $this->liveStripeAcountId . '&' . $query_string
				);
			} else {
				$price = json_decode( $this->stripe->prices->retrieve( $priceId, $params )->toJSON() );
			}
			array_push( $products, $price->product );
		}

		return $products;
	}

	/**
	 * @param $priceIds array
	 *
	 * @return array
	 */
	public function retrieveProductIdsByPriceIds( $priceIds ) {
		$products = $this->retrieveProductsByPriceIds( $priceIds );
		$productIds = array_map(
			function ($o) {
				return $o->id;
			},
			$products
		);

		return $productIds;
	}

	/**
	 * @param $priceId
	 *
	 * @return \StripeWPFS\Stripe\Price
	 * @throws UserFriendlyException
	 */
	public function retrievePriceWithProductExpanded( $priceId ) {
		$price = null;
		$params = [ 'expand' => [ 'product' ] ];
		$query_string = $this->build_query( $params );

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$price = $this->remoteRequest(
				'get',
				'/prices/' . $priceId . '?mode=test&accountId=' . $this->testStripeAcountId . '&' . $query_string
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$price = $this->remoteRequest(
				'get',
				'/prices/' . $priceId . '?mode=live&accountId=' . $this->liveStripeAcountId . '&' . $query_string
			);
		} else {
			$price = json_decode( $this->stripe->prices->retrieve(
				$priceId,
				$params
			)->toJSON() );
		}
		return $price;
	}

	/**
	 * @param $stripeCustomerId
	 * @param $taxIdType
	 * @param $taxId
	 * @return \StripeWPFS\Stripe\TaxId
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function createTaxIdForCustomer( $stripeCustomerId, $taxIdType, $taxId ) {
		$taxBody = [
			'type' => $taxIdType,
			'value' => $taxId
		];

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$taxId = $this->remoteRequest(
				'post',
				'/customers/' . $stripeCustomerId . '/tax_ids?mode=test&accountId=' . $this->testStripeAcountId,
				$taxBody
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$taxId = $this->remoteRequest(
				'post',
				'/customers/' . $stripeCustomerId . '/tax_ids?mode=live&accountId=' . $this->liveStripeAcountId,
				$taxBody
			);
		} else {
			$taxId = json_decode( $this->stripe->customers->createTaxId( $stripeCustomerId, $taxBody )->toJSON() );
		}
		return $taxId;
	}

	/**
	 * @param $stripeCustomerId
	 * @return \StripeWPFS\Stripe\Collection
	 * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
	 * @throws UserFriendlyException
	 */
	public function getTaxIdsForCustomer( $stripeCustomerId ) {
		$taxIds = null;
		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$taxIds = $this->remoteRequest(
				'get',
				'/customers/' . $stripeCustomerId . '/tax_ids?mode=test&accountId=' . $this->testStripeAcountId
			);
			$taxIds = $taxIds->data;
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$taxIds = $this->remoteRequest(
				'get',
				'/customers/' . $stripeCustomerId . '/tax_ids?mode=live&accountId=' . $this->liveStripeAcountId
			);
			$taxIds = $taxIds->data;
		} else {
			$taxIds = json_decode( $this->stripe->customers->allTaxIds( $stripeCustomerId, [] )->toJSON() );
		}
		return $taxIds;
	}

	/**
	 * Retrieves the customer's name from Stripe and caches it using a transient.
	 *
	 * @param string $stripeCustomerId The ID of the Stripe customer.
	 * @return string The customer's full name or the customer ID if an error occurs.
	 */
	public function getCustomerName( $stripeCustomerId ) {
		$transient_key = 'wpfs_stripe_customer_name_' . $stripeCustomerId;
		$customer_name = get_transient( $transient_key );

		if ( false === $customer_name ) {
			try {
				$customer = $this->retrieveCustomer( $stripeCustomerId );
				if ( isset( $customer->name ) && ! empty( $customer->name ) ) {
					$customer_name = $customer->name;
					set_transient( $transient_key, $customer_name, WEEK_IN_SECONDS );
				} else {
					$customer_name = $stripeCustomerId;
				}
			} catch ( \Exception $e ) {
				$customer_name = $stripeCustomerId;
			}
		}

		return $customer_name;
	}

	/**
	 * Searches Stripe customers by email address.
	 *
  	 * Returns the raw result of Stripe's customers.search endpoint, which contains
 	 * a `data` array of matching customer records. Callers must inspect `->data`
 	 * and handle zero, one, or multiple matches.
 	 *
 	 * @param string $email The email address to search for.
 	 * @return object Search result object returned by Stripe's customers.search endpoint.
	 */
	public function retrieveCustomerByEmail( $email ) {
		$customer = null;

		if ( $this->apiMode === 'test' && $this->usingWpTestPlatform ) {
			$customer = $this->remoteRequest(
				'get',
				'/customers/search?query=' . urlencode( 'email:\'' . $email . '\'' ) . '&mode=test&accountId=' . $this->testStripeAcountId
			);
		} elseif ( $this->apiMode === 'live' && $this->usingWpLivePlatform ) {
			$customer = $this->remoteRequest(
				'get',
				'/customers/search?query=' . urlencode( 'email:\'' . $email . '\'' ) . '&mode=live&accountId=' . $this->liveStripeAcountId
			);
		} else {
			$customer = json_decode( $this->stripe->customers->search( [ 'query' => 'email:\'' . $email . '\'' ] )->toJSON() );
		}

		return $customer;
	}
}
