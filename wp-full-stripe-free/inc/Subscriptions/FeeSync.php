<?php

namespace ThemeIsle\WPFullPay\Subscriptions;

use ThemeIsle\WPFullPay\Admin\StripeConnect;
use ThemeIsle\WPFullPay\Exceptions\PlatformRequestException;
use ThemeIsle\WPFullPay\License;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Logging\Logger;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Platform\SiteCredential;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\Utils;

/**
 * Keeps the platform fee on this site's existing subscriptions in line with its license (#608).
 *
 * The platform asks the license store itself and changes the fees. The site only sends its key and,
 * a page at a time from WP-Cron, the subscriptions it created, so a site with thousands of them never
 * holds one request open for long. Every call checks the license again, so a run that changed course
 * finds out on its next page.
 *
 * @phpstan-type SyncState array{status: 'idle'|'running'|'failed', step: 'check'|'page', run_verdict: string, cursor: array{subscribers: int, donations: int, untracked: int}, queue: array<string, int>, incomplete: bool, attempts: int, restarts: int, last_error: string, failed_since: int, last_verdict: string, force: bool, updated_at: int}
 * @phpstan-type SyncRow array{table: 'subscribers'|'donations'|'untracked', row: int, id: string}
 */
class FeeSync {

	/** Runs the next step of a mode's sync. Its only argument is the mode. */
	const HOOK_RUN = 'wpfs_subscription_fee_sync';

	/** The routine check. */
	const HOOK_WEEKLY = 'wpfs_subscription_fee_sync_weekly';

	/** One check after the license's expiry date. */
	const HOOK_EXPIRY = 'wpfs_subscription_fee_sync_expiry';

	/** A mode's run, followed by the mode. */
	const OPTION_STATE = 'wpfs_subscription_fee_sync_';

	/** Set, followed by the mode, when a new run is asked for, so a step whose call was out then starts over instead of saving its answer. */
	const OPTION_RESTART = 'wpfs_subscription_fee_sync_restart_';

	/** The restart flag's value when the new run pages even though the platform already applied its verdict. */
	const RESTART_FORCED = 'force';

	/** Set, followed by the mode, when a forced run is asked for while one is in progress: one more follows it. */
	const OPTION_FORCE_AGAIN = 'wpfs_subscription_fee_sync_again_';

	/**
	 * Followed by the mode, an underscore and the subscription ID: one option per subscription the tables don't
	 * have, which Stripe may keep billing. It's new and its row isn't recorded yet, or the admin deleted its row.
	 * Separate options let requests at the same time each add theirs, and their option_id is the order the run's
	 * cursor follows.
	 */
	const OPTION_UNTRACKED = 'wpfs_subscription_fee_sync_untracked_';

	/** Platform answers that an untracked subscription is gone, isn't the platform's, or has ended. */
	const ENDED_REASONS = [ 'not_found', 'not_ours', 'inactive' ];

	/** The key of a license deactivated here, sent until a valid activation replaces it. */
	const OPTION_LAST_KEY = 'wpfs_subscription_fee_sync_last_key';

	/** The platform takes at most this many subscriptions a call. */
	const PAGE_SIZE = 20;

	/** Failed calls in a row before the run stops and the admin is told. */
	const MAX_ATTEMPTS = 5;

	/** How often one subscription is sent again after the platform couldn't update it. */
	const MAX_RETRIES = 3;

	/** Restarts in one run, when the license answer keeps changing while it pages. */
	const MAX_RESTARTS = 5;

	/** The store's timezone isn't known, so the expiry check waits until the date has passed in all of them. */
	const EXPIRY_DELAY = DAY_IN_SECONDS;

	/** After a first refusal, the next check waits this long past the time the fee may go back. */
	const RE_ADD_DELAY = 5 * MINUTE_IN_SECONDS;

	/** The platform's rule for a license key; any other value is sent as no key. */
	const KEY_PATTERN = '/^[A-Za-z0-9._~-]{8,2048}$/';

	/** The platform's rule for a subscription ID; it refuses the whole page for one that doesn't match. */
	const SUBSCRIPTION_PATTERN = '/^sub_[A-Za-z0-9]+$/';

	const STATUS_IDLE = 'idle';
	const STATUS_RUNNING = 'running';
	const STATUS_FAILED = 'failed';

	const STEP_CHECK = 'check';
	const STEP_PAGE = 'page';

	const MODES = [ 'test', 'live' ];

	/** Verdicts that change fees, so the run pages through the subscriptions. */
	const PAGING_VERDICTS = [ 'confirmed', 'refused' ];

	/** Under `confirmed`, these skips leave subscriptions this site may own with their fee. */
	const OWNERSHIP_SKIPS = [ 'other_site', 'ambiguous_owner' ];

	/** @var LoggerService */
	private $loggerService;

	/** @var Logger */
	private $logger;

	/** @var Stripe|null */
	private $stripe;

	/**
	 * @param LoggerService $loggerService
	 */
	public function __construct( $loggerService ) {
		$this->loggerService = $loggerService;
		$this->logger = $loggerService->createLogger( LoggerService::MODULE_STRIPE, self::class );

		$licenseOption = License::get_namespace() . '_license_data';
		add_action( 'add_option_' . $licenseOption, [ $this, 'onLicenseAdded' ], 10, 2 );
		add_action( 'update_option_' . $licenseOption, [ $this, 'onLicenseUpdated' ], 10, 2 );
		add_action( 'delete_option', [ $this, 'onOptionDeleting' ] );
		foreach ( self::MODES as $mode ) {
			add_action( 'add_option_' . SiteCredential::OPTION_CREDENTIAL . $mode, [ $this, 'onCredentialAdded' ] );
			add_action( 'update_option_' . SiteCredential::OPTION_CREDENTIAL . $mode, [ $this, 'onCredentialUpdated' ], 10, 3 );
		}

		add_action( Main::ACTION_NAME_AFTER_CHECKOUT_SUBSCRIPTION_CHARGE, [ self::class, 'onSubscriptionRecorded' ] );
		add_action( Main::ACTION_NAME_AFTER_CHECKOUT_DONATION_CHARGE, [ self::class, 'onSubscriptionRecorded' ] );
		add_action( Main::ACTION_NAME_AFTER_DONATION_CHARGE, [ self::class, 'onSubscriptionRecorded' ] );
		add_action( 'fullstripe_admin_delete_subscription_record_action', [ self::class, 'onSubscriberDeleting' ] );
		add_action( 'fullstripe_admin_delete_donation_action', [ self::class, 'onDonationDeleting' ] );

		add_action( self::HOOK_RUN, [ $this, 'run' ] );
		add_action( self::HOOK_WEEKLY, [ self::class, 'onWeeklyCheck' ] );
		add_action( self::HOOK_EXPIRY, [ self::class, 'onExpiryCheck' ] );
		add_action( 'init', [ self::class, 'scheduleWeeklyCheck' ] );
		add_action( 'admin_notices', [ self::class, 'displayFailureNotice' ] );
	}

	/**
	 * add_option_{license data}: a license was activated.
	 *
	 * @param string $option
	 * @param mixed  $value
	 */
	public function onLicenseAdded( $option, $value ): void {
		$this->onLicenseChanged( null, $value );
	}

	/**
	 * update_option_{license data}: an activation, or the SDK's re-check.
	 *
	 * @param mixed $oldValue
	 * @param mixed $value
	 */
	public function onLicenseUpdated( $oldValue, $value ): void {
		$this->onLicenseChanged( $oldValue, $value );
	}

	/**
	 * delete_option, before the option goes: a deactivation deletes the license data, so its key is kept
	 * for the platform to confirm the lapse.
	 *
	 * @param string $option
	 */
	public function onOptionDeleting( $option ): void {
		if ( License::get_namespace() . '_license_data' !== $option ) {
			return;
		}

		$old = get_option( $option );
		$key = self::fingerprint( $old )['key'];
		if ( '' !== $key ) {
			update_option( self::OPTION_LAST_KEY, $key, false );
		}

		$this->onLicenseChanged( $old, null );
	}

	/**
	 * add_option_{credential}: Connect or Confirm gave this mode a credential.
	 *
	 * @param string $option
	 */
	public function onCredentialAdded( $option ): void {
		self::requestSync( substr( (string) $option, strlen( SiteCredential::OPTION_CREDENTIAL ) ) );
	}

	/**
	 * update_option_{credential}: Confirm replaced this mode's credential.
	 *
	 * @param mixed  $oldValue
	 * @param mixed  $value
	 * @param string $option
	 */
	public function onCredentialUpdated( $oldValue, $value, $option ): void {
		$this->onCredentialAdded( $option );
	}

	/**
	 * After a Checkout subscription or recurring donation, or an inline recurring donation, is recorded.
	 *
	 * @param mixed $params The after-charge action's parameters.
	 */
	public static function onSubscriptionRecorded( $params ): void {
		self::checkRecordedFee( is_array( $params ) ? ( $params['stripeSubscription'] ?? null ) : null );
	}

	/**
	 * A subscription's row was recorded, so it's no longer untracked. Its fee is checked again: a Checkout session
	 * may have been opened before the license changed, and an inline recurring donation is recorded only once the
	 * donor completes 3D Secure.
	 *
	 * @param mixed $subscription The Stripe subscription.
	 */
	public static function checkRecordedFee( $subscription ): void {
		if ( ! is_object( $subscription ) || ! isset( $subscription->livemode ) ) {
			return;
		}

		// Its row is recorded now, so the tables send it.
		if ( isset( $subscription->id ) && is_string( $subscription->id ) ) {
			delete_option( self::OPTION_UNTRACKED . ( $subscription->livemode ? 'live' : 'test' ) . '_' . $subscription->id );
		}
		self::syncIfFeeDisagrees( $subscription );
	}

	/**
	 * Starts a run that sends every subscription when this one's fee disagrees with the verdict the platform last
	 * applied: that run may be over without it.
	 *
	 * @param object $subscription The Stripe subscription, with its livemode.
	 */
	private static function syncIfFeeDisagrees( $subscription ): void {
		$mode = $subscription->livemode ? 'live' : 'test';
		$hasFee = ! empty( $subscription->application_fee_percent );
		$verdict = self::getState( $mode )['last_verdict'];
		if ( ( 'confirmed' === $verdict && $hasFee ) || ( 'refused' === $verdict && ! $hasFee ) ) {
			self::requestSync( $mode, true );
		}
	}

	/**
	 * Before the admin deletes a subscription's row.
	 *
	 * @param mixed $id The row's subscriberID.
	 */
	public static function onSubscriberDeleting( $id ): void {
		self::keepDeletedRow( 'fullstripe_subscribers', 'subscriberID', $id );
	}

	/**
	 * Before the admin deletes a donation's row.
	 *
	 * @param mixed $id The row's donationID.
	 */
	public static function onDonationDeleting( $id ): void {
		self::keepDeletedRow( 'fullstripe_donations', 'donationID', $id );
	}

	/**
	 * Deleting a row doesn't cancel its subscription in Stripe, so it's kept as untracked.
	 *
	 * @param 'fullstripe_subscribers'|'fullstripe_donations' $table
	 * @param 'subscriberID'|'donationID'                     $column
	 * @param mixed                                           $id
	 */
	private static function keepDeletedRow( string $table, string $column, $id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the callers fix the table and column names.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT stripeSubscriptionID AS id, livemode FROM {$wpdb->prefix}{$table} WHERE {$column} = %d", $id ) );
		if ( ! is_object( $row ) || ! is_string( $row->id ) || 1 !== preg_match( self::SUBSCRIPTION_PATTERN, $row->id ) ) {
			return;
		}

		// Rows from before the plugin stored the mode go to both modes, as in fetchRows().
		self::keepUntrackedId( $row->id, null === $row->livemode ? self::MODES : [ $row->livemode ? 'live' : 'test' ] );
	}

	/**
	 * A subscription Stripe just returned, before its row is recorded: runs send it after the tables until the row
	 * is recorded or the platform reports it ended. A call that fails after Stripe created it, or a donor who closes
	 * the tab at 3D Secure, would otherwise leave it out of every run. Its fee is checked when it's first kept, not
	 * each time the Checkout recovery finds its pending session again.
	 *
	 * @param mixed $subscription The Stripe subscription.
	 */
	public static function keepUntracked( $subscription ): void {
		if ( ! is_object( $subscription ) || ! isset( $subscription->id, $subscription->livemode ) || ! is_string( $subscription->id ) || self::isRecorded( $subscription->id ) ) {
			return;
		}

		if ( self::keepUntrackedId( $subscription->id, [ $subscription->livemode ? 'live' : 'test' ] ) ) {
			self::syncIfFeeDisagrees( $subscription );
		}
	}

	/**
	 * Whether a row in the tables already has the subscription, as when a completion is replayed.
	 */
	private static function isRecorded( string $subscriptionId ): bool {
		global $wpdb;

		return null !== $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->prefix}fullstripe_subscribers WHERE stripeSubscriptionID = %s UNION ALL SELECT 1 FROM {$wpdb->prefix}fullstripe_donations WHERE stripeSubscriptionID = %s LIMIT 1", $subscriptionId, $subscriptionId ) );
	}

	/**
	 * @param list<string> $modes
	 * @return bool Whether it wasn't kept yet in one of them.
	 */
	private static function keepUntrackedId( string $id, array $modes ): bool {
		$added = false;
		// An ID kept already stays.
		foreach ( $modes as $mode ) {
			$added = add_option( self::OPTION_UNTRACKED . $mode . '_' . $id, 1, '', false ) || $added;
		}

		return $added;
	}

	/**
	 * Stops sending untracked subscriptions the platform reported as ended.
	 *
	 * @param \stdClass $answer
	 */
	private static function forgetEnded( string $mode, $answer ): void {
		foreach ( isset( $answer->results ) && is_array( $answer->results ) ? $answer->results : [] as $result ) {
			if ( is_object( $result ) && isset( $result->id, $result->reason ) && is_string( $result->id ) && in_array( $result->reason, self::ENDED_REASONS, true ) ) {
				delete_option( self::OPTION_UNTRACKED . $mode . '_' . $result->id );
			}
		}
	}

	/**
	 * @param mixed $old
	 * @param mixed $new
	 */
	private function onLicenseChanged( $old, $new ): void {
		$fingerprint = self::fingerprint( $new );
		// The SDK stores its re-check every 12 hours; only a change the store reported starts a sync.
		if ( self::fingerprint( $old ) === $fingerprint ) {
			return;
		}

		// A refused activation also stores its key, so only a valid one replaces the outgoing key.
		if ( '' !== $fingerprint['key'] && 'valid' === $fingerprint['license'] ) {
			delete_option( self::OPTION_LAST_KEY );
		}

		// The SDK's failed re-checks end with the same key and no expiry date; the known check stays.
		if ( '' !== $fingerprint['expires'] || self::fingerprint( $old )['key'] !== $fingerprint['key'] ) {
			self::scheduleExpiryCheck( $new );
		}
		self::requestSync();
	}

	/**
	 * @param mixed $data License data, as the SDK stores it.
	 * @return array{key: string, license: string, expires: string}
	 */
	private static function fingerprint( $data ): array {
		$data = is_object( $data ) ? $data : new \stdClass();

		return [
			'key' => isset( $data->key ) && is_string( $data->key ) ? $data->key : '',
			'license' => isset( $data->license ) && is_string( $data->license ) ? $data->license : '',
			'expires' => isset( $data->expires ) && is_scalar( $data->expires ) ? (string) $data->expires : '',
		];
	}

	/**
	 * @param mixed $data License data, as the SDK stores it.
	 */
	private static function scheduleExpiryCheck( $data ): void {
		wp_clear_scheduled_hook( self::HOOK_EXPIRY );

		$expires = strtotime( self::fingerprint( $data )['expires'] );
		if ( false !== $expires && $expires + self::EXPIRY_DELAY > time() ) {
			wp_schedule_single_event( $expires + self::EXPIRY_DELAY, self::HOOK_EXPIRY );
		}
	}

	/**
	 * Starts a new run in $mode, or in every mode connected through the platform, replacing one in
	 * progress. A plain request only leaves the flag the next step takes.
	 *
	 * @param string|null $mode
	 * @param bool        $force Page even if the platform already applied its verdict. A forced run in progress
	 *                           stays forced.
	 */
	public static function requestSync( $mode = null, bool $force = false ): void {
		foreach ( is_string( $mode ) ? [ $mode ] : self::MODES as $syncMode ) {
			if ( ! self::isEligible( $syncMode ) ) {
				continue;
			}

			// A plain request leaves only its flag: the step that takes it starts the run from the stored state, so a
			// force saved since this request began can't be lost to a state it read before.
			if ( ! $force ) {
				self::flagRestart( $syncMode, false );
				self::scheduleRun( $syncMode, time() );
				continue;
			}

			$previous = self::readState( $syncMode );
			// A forced run in progress already sends every subscription, rows and kept IDs added since included, so
			// starting over would only lose its place. Before its check is done it has fetched no page, so it sends
			// what was just added; once paging, one more run after it covers a request during its last page.
			if ( self::STATUS_RUNNING === $previous['status'] && $previous['force'] ) {
				if ( self::STEP_CHECK === $previous['step'] ) {
					continue;
				}

				update_option( self::OPTION_FORCE_AGAIN . $syncMode, time(), false );
				// The run's last step saves its state before it looks for the flag, so reading the state after setting
				// it, one of the two sees the other. A run that ended without seeing it is followed here instead.
				$previous = self::readState( $syncMode );
				if ( ( self::STATUS_RUNNING === $previous['status'] && $previous['force'] ) || ! delete_option( self::OPTION_FORCE_AGAIN . $syncMode ) ) {
					continue;
				}
			}

			$state = self::newRun( $previous );
			$state['force'] = true;
			self::saveState( $syncMode, $state );
			self::flagRestart( $syncMode, true );
			self::scheduleRun( $syncMode, time() );
		}
	}

	/**
	 * The weekly check: starts a run in every connected mode without one in progress. A run whose next step
	 * isn't scheduled, as after the plugin was deactivated mid-run, is started over. It also schedules the
	 * expiry check for a license activated before this version, whose data hasn't changed since.
	 */
	public static function onWeeklyCheck(): void {
		if ( false === wp_next_scheduled( self::HOOK_EXPIRY ) ) {
			self::scheduleExpiryCheck( License::get_data() );
		}

		foreach ( self::MODES as $mode ) {
			$state = self::getState( $mode );
			if ( self::STATUS_RUNNING === $state['status'] && false !== wp_next_scheduled( self::HOOK_RUN, [ $mode ] ) ) {
				continue;
			}

			self::requestSync( $mode );
		}
	}

	/**
	 * The check after the license's expiry date. A hook fired without arguments passes '', so this
	 * doesn't take the mode requestSync() does.
	 */
	public static function onExpiryCheck(): void {
		self::requestSync();
	}

	/**
	 * init: schedules the weekly check, at a random time in the next hour so that sites don't all call at once.
	 */
	public static function scheduleWeeklyCheck(): void {
		if ( false !== wp_next_scheduled( self::HOOK_WEEKLY ) ) {
			return;
		}
		if ( ! array_filter( self::MODES, [ self::class, 'isEligible' ] ) ) {
			return;
		}

		wp_schedule_event( time() + wp_rand( 0, HOUR_IN_SECONDS ), 'weekly', self::HOOK_WEEKLY );
	}

	public static function onDeactivation(): void {
		foreach ( self::MODES as $mode ) {
			wp_clear_scheduled_hook( self::HOOK_RUN, [ $mode ] );
		}
		wp_clear_scheduled_hook( self::HOOK_WEEKLY );
		wp_clear_scheduled_hook( self::HOOK_EXPIRY );
	}

	/**
	 * Whether $mode is connected through the platform with a credential. A mode using the site's own Stripe
	 * keys pays no platform fee.
	 *
	 * @param string $mode
	 */
	private static function isEligible( $mode ): bool {
		return in_array( $mode, self::MODES, true )
			&& null !== StripeConnect::getConnectedAccountId( new Options(), $mode )
			&& null !== SiteCredential::get( $mode );
	}

	/**
	 * Schedules the mode's next step at $when, unless one is already due by then.
	 */
	private static function scheduleRun( string $mode, int $when ): void {
		$next = wp_next_scheduled( self::HOOK_RUN, [ $mode ] );
		if ( false !== $next && $next <= $when ) {
			return;
		}
		if ( false !== $next ) {
			wp_unschedule_event( $next, self::HOOK_RUN, [ $mode ] );
		}

		wp_schedule_single_event( $when, self::HOOK_RUN, [ $mode ] );
	}

	/**
	 * The state as stored now: another request may have saved it since this one read it.
	 *
	 * @return SyncState
	 */
	private static function readState( string $mode ): array {
		wp_cache_delete( self::OPTION_STATE . $mode, 'options' );

		return self::getState( $mode );
	}

	/**
	 * @return SyncState
	 */
	public static function getState( string $mode ): array {
		$stored = get_option( self::OPTION_STATE . $mode );
		$state = array_merge( self::newRun(), [ 'status' => self::STATUS_IDLE ], is_array( $stored ) ? $stored : [] );
		$state['cursor'] = array_merge( self::newRun()['cursor'], (array) $state['cursor'] );

		return $state;
	}

	/**
	 * @param SyncState $state
	 */
	private static function saveState( string $mode, array $state ): void {
		$state['updated_at'] = time();
		update_option( self::OPTION_STATE . $mode, $state, false );
	}

	/**
	 * A new run. It keeps when the previous run stopped after failing, so the admin is told until a run succeeds,
	 * the platform's last verdict, and a force no run has paged to its end with yet, as after a forced run failed.
	 *
	 * @param array{failed_since?: int, last_verdict?: string, force?: bool} $previous
	 * @return SyncState
	 */
	private static function newRun( array $previous = [], int $restarts = 0 ): array {
		return [
			'status' => self::STATUS_RUNNING,
			'step' => self::STEP_CHECK,
			'run_verdict' => '',
			'cursor' => [ 'subscribers' => 0, 'donations' => 0, 'untracked' => 0 ],
			'queue' => [],
			'incomplete' => false,
			'attempts' => 0,
			'restarts' => $restarts,
			'last_error' => '',
			'failed_since' => (int) ( $previous['failed_since'] ?? 0 ),
			'last_verdict' => (string) ( $previous['last_verdict'] ?? '' ),
			'force' => ! empty( $previous['force'] ),
			'updated_at' => time(),
		];
	}

	/**
	 * HOOK_RUN: sends the mode's next call to the platform.
	 *
	 * @param string $mode
	 */
	public function run( $mode ): void {
		if ( ! self::isEligible( $mode ) ) {
			return;
		}

		$state = self::getState( $mode );
		// A flag left from before this step means a new run, even if a step that had just missed the flag saved
		// its old state over it. Only a trigger or the run itself schedules this hook, so a finished run starts again.
		$restart = self::takeRestart( $mode, $state );
		if ( null !== $restart ) {
			$state = $restart;
		} elseif ( self::STATUS_RUNNING !== $state['status'] ) {
			$state = self::newRun( $state );
		}

		$next = null;
		try {
			list( $state, $next ) = self::STEP_CHECK === $state['step'] ? $this->check( $mode, $state ) : $this->page( $mode, $state );
		} catch ( \Exception $e ) {
			list( $state, $next ) = $this->onFailure( $state, $e );
		}

		// A license or credential change during the call asked for a new run; this answer must not replace it.
		$restart = self::takeRestart( $mode, $state );
		if ( null !== $restart ) {
			list( $state, $next ) = [ $restart, time() ];
		}

		self::saveState( $mode, $state );
		// Looked for only once the finished run is saved: requestSync() sets it before reading the state.
		if ( self::STATUS_IDLE === $state['status'] && delete_option( self::OPTION_FORCE_AGAIN . $mode ) ) {
			list( $state, $next ) = [ self::newRun( $state ), time() ];
			$state['force'] = true;
			self::saveState( $mode, $state );
		}
		if ( null !== $next ) {
			self::scheduleRun( $mode, $next );
		}
	}

	/**
	 * Leaves the flag takeRestart() takes, in one statement each, so a step taking the flag at the same moment can't
	 * lose it. A forced request's replaces a plain one's; a plain one's never replaces a forced one's.
	 */
	private static function flagRestart( string $mode, bool $force ): void {
		global $wpdb;

		$option = self::OPTION_RESTART . $mode;
		if ( $force ) {
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no') ON DUPLICATE KEY UPDATE option_value = %s", $option, self::RESTART_FORCED, self::RESTART_FORCED ) );
		} else {
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, 'new', 'no')", $option ) );
		}
		wp_cache_delete( $option, 'options' );
	}

	/**
	 * Takes the flag requestSync() leaves, once.
	 *
	 * @param SyncState $state
	 * @return SyncState|null The new run it asks for, forced if it is or a force is pending, or null without one.
	 */
	private static function takeRestart( string $mode, array $state ): ?array {
		global $wpdb;

		$option = self::OPTION_RESTART . $mode;
		// Another request sets it, so it's read from the table: get_option() keeps "no flag" for the whole request.
		// It's deleted only while it still holds the value read: a newer request landing in between keeps its flag
		// for the next take, after the call or at the next step.
		$requested = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) );
		if ( null === $requested || ! $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $option, $requested ) ) ) {
			return null;
		}
		wp_cache_delete( $option, 'options' );

		$run = self::newRun( $state );
		$run['force'] = self::RESTART_FORCED === $requested || $run['force'];

		return $run;
	}

	/**
	 * The run's first call: an empty page, which only checks the license.
	 *
	 * @param SyncState $state
	 * @return array{0: SyncState, 1: int|null} The new state, and when to run the next step.
	 * @throws \Exception
	 */
	private function check( string $mode, array $state ): array {
		$key = self::getKeyToSend();
		$answer = $this->send( $mode, $key, [] );
		$verdict = $answer->verdict;
		$state['last_verdict'] = $verdict;

		if ( in_array( $verdict, self::PAGING_VERDICTS, true ) && ( $state['force'] || ! self::isApplied( $answer, $verdict ) ) ) {
			$state['step'] = self::STEP_PAGE;
			$state['run_verdict'] = $verdict;
			$state['attempts'] = 0;

			return [ $state, time() ];
		}

		$next = null;
		if ( 'refused_pending' === $verdict && isset( $answer->reAddEligibleAt ) && is_numeric( $answer->reAddEligibleAt ) ) {
			$next = (int) ceil( $answer->reAddEligibleAt / 1000 ) + self::RE_ADD_DELAY;
		}

		return [ self::finished( $state ), $next ];
	}

	/**
	 * Sends the next page: IDs to send again first, then this mode's subscriptions after the cursor.
	 *
	 * @param SyncState $state
	 * @return array{0: SyncState, 1: int|null}
	 * @throws \Exception
	 */
	private function page( string $mode, array $state ): array {
		$ids = array_slice( array_keys( $state['queue'] ), 0, self::PAGE_SIZE );
		$more = count( $state['queue'] ) > count( $ids );
		$cursor = $state['cursor'];

		$room = self::PAGE_SIZE - count( $ids );
		if ( $room > 0 ) {
			$rows = self::fetchRows( $mode, $cursor, $room + 1 );
			$more = $more || count( $rows ) > $room;
			foreach ( array_slice( $rows, 0, $room ) as $row ) {
				$cursor[ $row['table'] ] = $row['row'];
				if ( preg_match( self::SUBSCRIPTION_PATTERN, $row['id'] ) && ! in_array( $row['id'], $ids, true ) ) {
					$ids[] = $row['id'];
				}
			}
		} elseif ( ! $more ) {
			// The IDs to send again fill the page: rows after the cursor still make it not the last.
			$more = [] !== self::fetchRows( $mode, $cursor, 1 );
		}

		$body = [];
		if ( ! $more ) {
			$body = [
				'final' => true,
				'runVerdict' => $state['run_verdict'],
				'complete' => ! $state['incomplete'],
			];
		}

		$key = self::getKeyToSend();
		$answer = $this->send( $mode, $key, $ids, $body );

		if ( $answer->verdict !== $state['run_verdict'] || ! empty( $answer->superseded ) ) {
			return $this->restart( $state );
		}

		$state = self::recordResults( $state, $ids, $answer );
		$state['cursor'] = $cursor;
		self::forgetEnded( $mode, $answer );

		// The platform processed none of them, deferring or leaving them out: wait, and stop if it keeps happening.
		if ( [] !== $ids && [] === array_intersect( $ids, self::answeredIds( $answer ) ) ) {
			$state['attempts']++;
			if ( $state['attempts'] >= self::MAX_ATTEMPTS ) {
				return [ self::failed( $state, 'no_progress' ), null ];
			}

			return [ $state, time() + self::backoff( $state['attempts'] ) ];
		}
		$state['attempts'] = 0;

		// The last page told the platform the run was complete before an ID here ran out of retries: say it wasn't.
		if ( $more || [] !== $state['queue'] || ( ! empty( $body['complete'] ) && $state['incomplete'] ) ) {
			return [ $state, time() ];
		}

		// Its last page is sent, so every subscription was: a force it carried is done. A check that didn't page
		// keeps it.
		$state['force'] = false;

		return [ self::finished( $state ), null ];
	}

	/**
	 * Queues the IDs the platform couldn't finish: deferred ones as they are, failed ones until they run
	 * out of retries, which leaves the run incomplete.
	 *
	 * @param SyncState    $state
	 * @param list<string> $ids
	 * @param \stdClass    $answer
	 * @return SyncState
	 */
	private static function recordResults( array $state, array $ids, $answer ): array {
		$sent = array_flip( $ids );
		$retries = $state['queue'];
		$state['queue'] = array_diff_key( $state['queue'], $sent );

		foreach ( isset( $answer->results ) && is_array( $answer->results ) ? $answer->results : [] as $result ) {
			if ( ! is_object( $result ) || ! isset( $result->id ) || ! is_string( $result->id ) || ! isset( $sent[ $result->id ] ) ) {
				continue;
			}

			if ( ! empty( $result->retry ) ) {
				$state = self::retryLater( $state, $result->id, $retries );
			}

			if ( 'confirmed' === $state['run_verdict'] && isset( $result->reason ) && in_array( $result->reason, self::OWNERSHIP_SKIPS, true ) ) {
				$state['incomplete'] = true;
			}
		}

		$deferred = [];
		foreach ( isset( $answer->deferred ) && is_array( $answer->deferred ) ? $answer->deferred : [] as $id ) {
			if ( is_string( $id ) && isset( $sent[ $id ] ) ) {
				$state['queue'][ $id ] = $retries[ $id ] ?? 0;
				$deferred[ $id ] = true;
			}
		}

		// An ID the answer left out wasn't processed, so it's sent again like a failed update.
		foreach ( array_keys( array_diff_key( $sent, $deferred, array_flip( self::answeredIds( $answer ) ) ) ) as $id ) {
			$state = self::retryLater( $state, (string) $id, $retries );
		}

		return $state;
	}

	/**
	 * Queues an ID to send again, until it runs out of retries, which leaves the run incomplete.
	 *
	 * @param SyncState         $state
	 * @param array<string,int> $retries The queue before this page.
	 * @return SyncState
	 */
	private static function retryLater( array $state, string $id, array $retries ): array {
		$tries = ( $retries[ $id ] ?? 0 ) + 1;
		if ( $tries > self::MAX_RETRIES ) {
			$state['incomplete'] = true;
		} else {
			$state['queue'][ $id ] = $tries;
		}

		return $state;
	}

	/**
	 * @param \stdClass $answer
	 * @return list<string> The IDs the answer has a result for.
	 */
	private static function answeredIds( $answer ): array {
		$ids = [];
		foreach ( isset( $answer->results ) && is_array( $answer->results ) ? $answer->results : [] as $result ) {
			if ( is_object( $result ) && isset( $result->id ) && is_string( $result->id ) ) {
				$ids[] = $result->id;
			}
		}

		return $ids;
	}

	/**
	 * The license answer changed while the run paged, so it starts over with the check.
	 *
	 * @param SyncState $state
	 * @return array{0: SyncState, 1: int|null}
	 */
	private function restart( array $state ): array {
		$restarts = $state['restarts'] + 1;
		if ( $restarts > self::MAX_RESTARTS ) {
			$this->logger->error( __FUNCTION__, 'The license answer kept changing; the fee sync stopped.' );

			return [ self::failed( $state, 'verdict_changing' ), null ];
		}

		return [ self::newRun( $state, $restarts ), time() ];
	}

	/**
	 * @param SyncState $state
	 * @return array{0: SyncState, 1: int|null}
	 */
	private function onFailure( array $state, \Exception $e ): array {
		$status = $e instanceof PlatformRequestException ? $e->getHttpStatus() : 0;
		$this->logger->error( __FUNCTION__, 'The fee sync call failed', $e );
		$state['last_error'] = $e instanceof PlatformRequestException && '' !== $e->getErrorCode() ? $e->getErrorCode() : (string) $status;

		// A refused credential asks the admin to confirm the connection, which syncs again. A platform
		// without the route answers 404; the next weekly check asks again.
		if ( ( $e instanceof PlatformRequestException && $e->isCredentialRefusal() ) || 404 === $status ) {
			$state['status'] = self::STATUS_IDLE;

			return [ $state, null ];
		}

		$state['attempts']++;
		// The platform refuses a request it can't read; sending it again won't help.
		if ( 400 === $status || $state['attempts'] >= self::MAX_ATTEMPTS ) {
			return [ self::failed( $state, $state['last_error'] ), null ];
		}

		$retryAfter = $e instanceof PlatformRequestException ? $e->getRetryAfter() : 0;
		if ( $retryAfter > 0 ) {
			$delay = $retryAfter;
		} elseif ( 409 === $status ) {
			$delay = MINUTE_IN_SECONDS;
		} elseif ( 503 === $status ) {
			$delay = 5 * MINUTE_IN_SECONDS;
		} else {
			$delay = self::backoff( $state['attempts'] );
		}

		return [ $state, time() + min( max( $delay, MINUTE_IN_SECONDS ), HOUR_IN_SECONDS ) ];
	}

	/**
	 * @return int Seconds before attempt $attempts + 1: a minute, doubling up to an hour.
	 */
	private static function backoff( int $attempts ): int {
		return min( MINUTE_IN_SECONDS * ( 2 ** ( $attempts - 1 ) ), HOUR_IN_SECONDS );
	}

	/**
	 * @param SyncState $state
	 * @return SyncState
	 */
	private static function finished( array $state ): array {
		$state['status'] = self::STATUS_IDLE;
		$state['attempts'] = 0;
		$state['last_error'] = '';
		// A run that left a subscription failing hasn't succeeded, so an earlier stop is still worth telling.
		if ( ! $state['incomplete'] ) {
			$state['failed_since'] = 0;
		}

		return $state;
	}

	/**
	 * The run stops; the admin is told until a later run succeeds.
	 *
	 * @param SyncState $state
	 * @return SyncState
	 */
	private static function failed( array $state, string $error ): array {
		$state['status'] = self::STATUS_FAILED;
		$state['last_error'] = $error;
		if ( 0 === $state['failed_since'] ) {
			$state['failed_since'] = time();
		}

		return $state;
	}

	/**
	 * Whether the platform has already applied $verdict to every subscription it could.
	 *
	 * @param \stdClass $answer
	 */
	private static function isApplied( $answer, string $verdict ): bool {
		$applied = $answer->applied ?? null;

		return is_object( $applied ) && ( $applied->verdict ?? null ) === $verdict && true === ( $applied->complete ?? null );
	}

	/**
	 * The key deactivated here, kept until a valid activation replaces it, or else the current key. A refused
	 * activation stores its key too, and sending it would restart the platform's wait for a second refusal. A key
	 * the platform would reject counts as none.
	 */
	private static function getKeyToSend(): string {
		foreach ( [ (string) get_option( self::OPTION_LAST_KEY, '' ), (string) License::get_key() ] as $key ) {
			if ( 1 === preg_match( self::KEY_PATTERN, $key ) ) {
				return $key;
			}
		}

		return '';
	}

	/**
	 * This mode's subscriptions after the cursor: subscriber rows first, then donation rows, including rows
	 * cancelled here, since the admin's cancel marks the row before Stripe confirms it, and then the untracked
	 * subscriptions. Rows from before the plugin stored the mode go to both modes; the other mode's Stripe answers
	 * that it doesn't know them.
	 *
	 * @param array{subscribers: int, donations: int, untracked: int} $cursor
	 * @return list<SyncRow>
	 */
	private static function fetchRows( string $mode, array $cursor, int $limit ): array {
		global $wpdb;

		$livemode = 'live' === $mode ? 1 : 0;
		$rows = [];
		$tables = [
			'subscribers' => [ $wpdb->prefix . 'fullstripe_subscribers', 'subscriberID' ],
			'donations' => [ $wpdb->prefix . 'fullstripe_donations', 'donationID' ],
		];

		foreach ( $tables as $name => list( $table, $column ) ) {
			$wanted = $limit - count( $rows );
			if ( $wanted <= 0 ) {
				break;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table and column names are fixed above.
			$found = $wpdb->get_results( $wpdb->prepare( "SELECT {$column} AS row_id, stripeSubscriptionID AS id FROM {$table} WHERE {$column} > %d AND stripeSubscriptionID LIKE %s AND ( livemode = %d OR livemode IS NULL ) ORDER BY {$column} LIMIT %d", $cursor[ $name ], $wpdb->esc_like( 'sub_' ) . '%', $livemode, $wanted ) );
			// A failed read would otherwise look like the end of the table, and the run would claim it was complete.
			if ( '' !== $wpdb->last_error ) {
				throw new \RuntimeException( 'Reading the subscriptions failed: ' . $wpdb->last_error );
			}
			foreach ( is_array( $found ) ? $found : [] as $row ) {
				$rows[] = [ 'table' => $name, 'row' => (int) $row->row_id, 'id' => (string) $row->id ];
			}
		}

		$wanted = $limit - count( $rows );
		if ( $wanted > 0 ) {
			$prefix = self::OPTION_UNTRACKED . $mode . '_';
			$found = $wpdb->get_results( $wpdb->prepare( "SELECT option_id AS row_id, option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_id > %d ORDER BY option_id LIMIT %d", $wpdb->esc_like( $prefix ) . '%', $cursor['untracked'], $wanted ) );
			if ( '' !== $wpdb->last_error ) {
				throw new \RuntimeException( 'Reading the untracked subscriptions failed: ' . $wpdb->last_error );
			}
			foreach ( is_array( $found ) ? $found : [] as $row ) {
				$rows[] = [ 'table' => 'untracked', 'row' => (int) $row->row_id, 'id' => substr( (string) $row->option_name, strlen( $prefix ) ) ];
			}
		}

		return $rows;
	}

	/**
	 * @param list<string>                                         $ids
	 * @param array{final?: true, runVerdict?: string, complete?: bool} $extra The last page's fields.
	 * @return \stdClass The platform's answer, with a verdict.
	 * @throws \Exception
	 */
	private function send( string $mode, string $key, array $ids, array $extra = [] ) {
		$accountId = StripeConnect::getConnectedAccountId( new Options(), $mode );
		$body = '' === $key ? [] : [ 'licenseId' => $key ];
		$body['subscriptions'] = $ids;

		$answer = $this->getStripe()->syncSubscriptionFees( $mode, (string) $accountId, $body + $extra );
		if ( ! is_object( $answer ) || ! isset( $answer->verdict ) || ! is_string( $answer->verdict ) ) {
			throw new PlatformRequestException( __( 'The platform sent an answer without a verdict.', 'wp-full-stripe-free' ) );
		}

		return $answer;
	}

	private function getStripe(): Stripe {
		if ( null === $this->stripe ) {
			// Only platform calls are made, which need no Stripe key.
			$this->stripe = new Stripe( null, $this->loggerService );
		}

		return $this->stripe;
	}

	/**
	 * admin_notices: tells the admin when a run stopped after repeated failures, until a later run succeeds.
	 */
	public static function displayFailureNotice(): void {
		if ( ! current_user_can( 'manage_options' ) || Utils::isDemoMode() ) {
			return;
		}

		foreach ( [ 'live', 'test' ] as $mode ) {
			if ( 0 === self::getState( $mode )['failed_since'] || ! self::isEligible( $mode ) ) {
				continue;
			}

			printf(
				'<div class="notice notice-warning wpfs-fee-sync-notice"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html( 'live' === $mode
					? __( 'WP Full Pay couldn\'t update the platform fee on your existing subscriptions.', 'wp-full-stripe-free' )
					: __( 'WP Full Pay couldn\'t update the platform fee on your existing test subscriptions.', 'wp-full-stripe-free' ) ),
				esc_html__( 'It tries again within a week, or when your license changes. If this keeps happening, please contact support.', 'wp-full-stripe-free' )
			);
		}
	}
}
