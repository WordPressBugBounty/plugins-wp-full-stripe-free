<?php

namespace ThemeIsle\WPFullPay\Payments;

use ThemeIsle\WPFullPay\Context\StaticContext;
use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Logging\Logger;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Stripe;

/**
 * Brings Stripe's capture and refund to a payment its own request recorded without reading Stripe again or holding the
 * payment: a refund made before the payment was recorded found no record for its webhook to update, and a capture
 * recorded meanwhile can have been overwritten, and no later event would repair either.
 */
class PaymentReconciler {

	/** The cron hook, run with the PaymentIntent ID and the attempt. */
	const HOOK = 'fullstripe_reconcile_payment';

	/** Seconds before the first attempt; each later one waits twice as long, up to MAX_DELAY. */
	const DELAY = 5 * MINUTE_IN_SECONDS;

	/** The longest wait between attempts. */
	const MAX_DELAY = 12 * HOUR_IN_SECONDS;

	/** Attempts Stripe can't answer before the reconciliation gives up: about two days. */
	const MAX_ATTEMPTS = 10;

	/** @var LoggerService */
	private $loggerService;

	/** @var Logger */
	private $logger;

	/** @var StaticContext */
	private $staticContext;

	/**
	 * @param LoggerService $loggerService
	 */
	public function __construct( $loggerService ) {
		$this->loggerService = $loggerService;
		$this->logger = $loggerService->createLogger( LoggerService::MODULE_STRIPE, self::class );
		$this->staticContext = new StaticContext( $loggerService, new Options() );

		add_action( self::HOOK, [ $this, 'reconcile' ], 10, 2 );
	}

	/**
	 * @param string $paymentIntentId
	 * @param int $attempt
	 */
	public static function schedule( $paymentIntentId, $attempt = 1 ): void {
		if ( false === wp_next_scheduled( self::HOOK, [ $paymentIntentId, $attempt ] ) ) {
			wp_schedule_single_event( time() + min( self::DELAY * ( 2 ** ( $attempt - 1 ) ), self::MAX_DELAY ), self::HOOK, [ $paymentIntentId, $attempt ] );
		}
	}

	/**
	 * @param string $paymentIntentId
	 * @param int $attempt
	 */
	public function reconcile( $paymentIntentId, $attempt = 1 ): void {
		$db = new Database();
		try {
			// The client reaches the account of the site's mode only: a payment of the other mode waits for it, without
			// using up an attempt, as long as the site stays in that mode.
			$liveMode = Stripe::isStripeApiInLiveMode( $this->staticContext );
			if ( ( $db->isPaymentIntentLive( $paymentIntentId ) ?? $liveMode ) !== $liveMode ) {
				$this->logger->debug( __FUNCTION__, "The payment '{$paymentIntentId}' is of the other mode than the site, waiting for it." );
				if ( false === wp_next_scheduled( self::HOOK, [ $paymentIntentId, $attempt ] ) ) {
					wp_schedule_single_event( time() + self::MAX_DELAY, self::HOOK, [ $paymentIntentId, $attempt ] );
				}

				return;
			}
			LatestCharge::write(
				$db,
				new Stripe( Stripe::getStripeAuthenticationTokenByMode( $this->staticContext, $liveMode ), $this->loggerService ),
				$paymentIntentId,
				function ( $charge ) use ( $db, $paymentIntentId ) {
					if ( ! is_null( $charge ) ) {
						$db->recordChargeState( $paymentIntentId, $charge );
					}
				}
			);
		} catch ( \Exception $ex ) {
			$this->logger->error( __FUNCTION__, "Cannot reconcile the payment '{$paymentIntentId}' (attempt {$attempt}).", $ex );
			if ( $attempt < self::MAX_ATTEMPTS ) {
				self::schedule( $paymentIntentId, $attempt + 1 );
			}
		}
	}
}
