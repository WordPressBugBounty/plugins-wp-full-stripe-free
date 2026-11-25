<?php

namespace ThemeIsle\WPFullPay\Admin;

use ThemeIsle\WPFullPay\Context\StaticContext;
use ThemeIsle\WPFullPay\Exceptions\PlatformRequestException;
use ThemeIsle\WPFullPay\Logging\Logger;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Platform\SiteCredential;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\Utils;

/**
 * Connects a Stripe account to this site with Connect with Stripe.
 *
 * The merchant signs in on Stripe, and the platform API sends them back with the account ID in the
 * URL. Anyone can put an account ID in a URL, so the site saves it only when the URL also carries the
 * token it created when this admin clicked Connect. Connecting never replaces an account already
 * connected in the same mode: that account would keep WP Full Pay's access, and the site could no
 * longer remove it.
 *
 * The same flow gives the site the credential it sends to the platform: the platform shows the
 * merchant which site they are connecting, then sends back a code that, with the secret this site kept,
 * activates the credential. A site connected before credentials existed gets one with "Confirm your
 * Stripe connection", which runs the flow again for the account it already has.
 */
class StripeConnect {

	const AJAX_START = 'wpfs-create-stripe-connect-account';
	const AJAX_CONFIRM = 'wpfs-confirm-stripe-connection';
	const AJAX_ADD_ACCOUNT = 'wpfs-add-stripe-account';

	/** Query parameter carrying the code that activates this site's credential. */
	const PARAM_CODE = 'wpfs_connect_code';

	/** The date (UTC) from which the platform refuses requests without a credential. */
	const CONFIRM_DEADLINE = '2027-01-07';

	/** Refusals meaning the connection's secret can never be activated, so the next try needs a new one. */
	const DEAD_SECRET_CODES = [ 'pending_consumed', 'pending_superseded' ];

	/** How often an unresolved confirmation is checked again, at most. */
	const RESOLVE_INTERVAL = MINUTE_IN_SECONDS;

	/** Query parameter carrying the token through Stripe and the platform API back to this site. */
	const PARAM_TOKEN = 'wpfs_connect';

	/** Query parameter carrying the outcome to the notice on the Stripe settings page. */
	const PARAM_RESULT = 'wpfs_connect_result';

	const RESULT_ALREADY_CONNECTED = 'connected';
	const RESULT_EXPIRED = 'expired';
	const RESULT_REPLACED = 'replaced';
	const RESULT_OTHER_ACCOUNT = 'other_account';
	const RESULT_NOT_CONNECTED = 'not_connected';
	const RESULT_NOT_CONFIRMED = 'not_confirmed';
	const RESULT_UNAVAILABLE = 'unavailable';
	const RESULT_CANCELLED = 'cancelled';

	/** Signing in, or creating an account, on Stripe can take a while. */
	const PENDING_TTL = DAY_IN_SECONDS;

	const CAPABILITY = 'manage_options';

	/** @var Logger */
	private $logger;

	/** @var Options */
	private $options;

	/** @var Stripe */
	private $stripe;

	/**
	 * @param LoggerService $loggerService
	 */
	public function __construct( $loggerService ) {
		$this->logger = $loggerService->createLogger( LoggerService::MODULE_ADMIN, self::class );
		$this->options = new Options();

		$staticContext = new StaticContext( $loggerService, $this->options );
		$this->stripe = new Stripe( Stripe::getStripeAuthenticationToken( $staticContext ), $loggerService );

		add_action( 'wp_ajax_' . self::AJAX_START, [ $this, 'startConnection' ] );
		add_action( 'wp_ajax_' . self::AJAX_CONFIRM, [ $this, 'startConfirmation' ] );
		add_action( 'wp_ajax_' . self::AJAX_ADD_ACCOUNT, [ $this, 'addAccount' ] );
		add_action( 'admin_init', [ $this, 'handleFailedReturn' ] );
		add_action( 'admin_init', [ $this, 'resolveUnconfirmed' ] );
		add_action( 'admin_notices', [ self::class, 'displayConfirmationNotice' ] );
	}

	/**
	 * @param Options $options
	 * @param string  $mode 'test' or 'live'.
	 * @return string|null The account this site is connected to through the platform in this mode.
	 */
	public static function getConnectedAccountId( Options $options, string $mode ) {
		if ( 'test' !== $mode && 'live' !== $mode ) {
			return null;
		}

		$accountId = $options->get( 'live' === $mode ? Options::OPTION_LIVE_ACCOUNT_ID : Options::OPTION_TEST_ACCOUNT_ID );
		$usesPlatform = $options->get( 'live' === $mode ? Options::OPTION_USE_WP_LIVE_PLATFORM : Options::OPTION_USE_WP_TEST_PLATFORM );

		if ( empty( $accountId ) || empty( $usesPlatform ) ) {
			return null;
		}

		return (string) $accountId;
	}

	/**
	 * AJAX: answers with the Stripe URL the merchant connects their account on.
	 */
	public function startConnection(): void {
		$this->verifyRequest();

		$currentUrl = isset( $_POST['current_page_url'] ) ? sanitize_text_field( wp_unslash( $_POST['current_page_url'] ) ) : '';
		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : '';

		if ( ! $this->isValidMode( $mode ) ) {
			wp_send_json( [
				'success' => false,
				'msg' => __( 'Unknown Stripe mode.', 'wp-full-stripe-free' ),
			] );
		}

		if ( null !== self::getConnectedAccountId( $this->options, $mode ) ) {
			$this->sendRefusal( self::RESULT_ALREADY_CONNECTED, $mode );
		}

		$this->sendStripeLink( $mode, $currentUrl );
	}

	/**
	 * AJAX: starts "Confirm your Stripe connection", which gives a site connected before credentials
	 * existed its credential, or replaces one the platform refused. The merchant signs in on Stripe again
	 * for the account this site already has.
	 */
	public function startConfirmation(): void {
		$this->verifyRequest();

		$currentUrl = isset( $_POST['current_page_url'] ) ? sanitize_text_field( wp_unslash( $_POST['current_page_url'] ) ) : '';
		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : '';

		if ( ! $this->isValidMode( $mode ) ) {
			wp_send_json( [
				'success' => false,
				'msg' => __( 'Unknown Stripe mode.', 'wp-full-stripe-free' ),
			] );
		}

		if ( null === self::getConnectedAccountId( $this->options, $mode ) ) {
			$this->sendRefusal( self::RESULT_NOT_CONNECTED, $mode );
		}

		$this->sendStripeLink( $mode, $currentUrl );
	}

	/**
	 * Answers with the Stripe URL for a connection this admin starts, or with the error.
	 */
	private function sendStripeLink( string $mode, string $currentUrl ): void {
		// Every admin and tab shares the secret of the connection in progress, so whichever return comes
		// back activates the same credential.
		try {
			$pending = SiteCredential::startPending( $mode );
		} catch ( \RuntimeException $e ) {
			wp_send_json( [
				'success' => false,
				'msg' => $e->getMessage(),
			] );
		}

		// A second click or another tab reuses the pending token, so the Stripe page opened first still
		// comes back to a token this site accepts. A failed start leaves it for the same reason. A newer
		// connection gets a new token, so a late return from the older one can't act on it.
		$token = $this->getPendingToken( $mode );
		$record = get_option( $this->getPendingKey( $mode ) );
		if ( null === $token || ! is_array( $record ) || ( $record['generation'] ?? '' ) !== $pending['id'] ) {
			$token = bin2hex( random_bytes( 16 ) );
		}
		update_option(
			$this->getPendingKey( $mode ),
			[
				'token' => $token,
				'expires' => time() + self::PENDING_TTL,
				'generation' => $pending['id'],
				'onboarding' => $this->isOnboarding( $currentUrl ),
				// The account a confirmation is for; empty for a new connection.
				'account' => (string) self::getConnectedAccountId( $this->options, $mode ),
			],
			false
		);

		try {
			$accountLink = $this->stripe->getAccountLink(
				add_query_arg( self::PARAM_TOKEN, $token, $currentUrl ),
				$mode,
				[
					'admin_base' => $pending['admin_base'],
					'home_url' => home_url( '/' ),
					'credential_challenge' => SiteCredential::challenge( $pending['secret'] ),
					'wpfs_connect' => $token,
				]
			);
		} catch ( \Exception $e ) {
			$this->logger->error( __FUNCTION__, 'Error creating Stripe connect account', $e );

			wp_send_json( [
				'success' => false,
				'msg' =>
					sprintf(
						/* translators: Error banner label for not being able to create a Stripe connect account. %s: error message */
						__( 'There was an error creating the Stripe connect account: %s', 'wp-full-stripe-free' ),
						$e->getMessage()
					),
			] );
		}

		wp_send_json( [
			'success' => true,
			'msg' => __( 'Connect account created successfully, redirecting to Stripe...', 'wp-full-stripe-free' ),
			'redirectURL' => $accountLink,
		] );
	}

	/**
	 * AJAX: saves the account the platform API sent the merchant back with.
	 */
	public function addAccount(): void {
		$this->verifyRequest();

		$accountId = isset( $_POST['account_id'] ) ? sanitize_text_field( wp_unslash( $_POST['account_id'] ) ) : '';
		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : '';
		$token = isset( $_POST['connect_token'] ) ? sanitize_text_field( wp_unslash( $_POST['connect_token'] ) ) : '';
		$code = isset( $_POST['connect_code'] ) ? sanitize_text_field( wp_unslash( $_POST['connect_code'] ) ) : '';

		$redeemed = $this->redeemToken( $mode, $token );
		if ( null === $redeemed || 1 !== preg_match( '/^acct_\w+$/D', $accountId ) ) {
			$this->sendRefusal( self::RESULT_EXPIRED, $mode );
		}

		// Another account is never saved over the one this site uses: switching takes Disconnect first.
		$connectedAccountId = self::getConnectedAccountId( $this->options, $mode );
		if ( null !== $connectedAccountId && $connectedAccountId !== $accountId ) {
			// The platform bound this connection's secret to the other account, so the next try needs a new one.
			$this->retire( $mode, (string) ( $redeemed['generation'] ?? '' ) );

			// A confirmation of the account the site still uses means the merchant signed in to another one on
			// Stripe. Otherwise the mode was connected again, for example in another tab, while they were there.
			// A token from before the update has no account; its message names the account the site uses, which
			// holds either way.
			$signedInToAnother = ! array_key_exists( 'account', $redeemed ) || $redeemed['account'] === $connectedAccountId;
			$this->sendRefusal( $signedInToAnother ? self::RESULT_OTHER_ACCOUNT : self::RESULT_REPLACED, $mode );
		}

		$result = $this->finishConnection( $mode, $accountId, $code, (string) ( $redeemed['generation'] ?? '' ), null !== $connectedAccountId );
		if ( null !== $result ) {
			$this->sendRefusal( $result, $mode );
		}

		// The platform returns to the settings page, so the setup wizard is resumed from here.
		wp_send_json( [
			'success' => true,
			'onboarding' => ! empty( $redeemed['onboarding'] ),
		] );
	}

	/**
	 * Saves the account and activates the site's credential for a return whose token was redeemed.
	 *
	 * @param string $mode
	 * @param string $accountId
	 * @param string $code         The code the platform sent back, or '' from a platform that sends none.
	 * @param string $generationId The connection in progress the redeemed token belongs to.
	 * @param bool   $isConfirmation Whether the site was already connected to this account.
	 * @return string|null A RESULT_* to show instead of success.
	 */
	private function finishConnection( string $mode, string $accountId, string $code, string $generationId, bool $isConfirmation ) {
		$pending = SiteCredential::getPending( $mode );

		// Disconnect, or a newer connection, replaced the one this return belongs to.
		if ( null === $pending || $pending['id'] !== $generationId ) {
			return self::RESULT_EXPIRED;
		}

		// A platform that issues no credentials sends no code. Nothing was refused, so nothing is reported.
		if ( '' === $code ) {
			return $this->complete( $mode, $generationId, $accountId, null, false );
		}

		try {
			$this->stripe->activateSiteCredential( $mode, $accountId, $code, $pending['secret'] );
		} catch ( PlatformRequestException $e ) {
			$this->logger->error( __FUNCTION__, 'Cannot activate the site credential', $e );

			// The platform may have activated it, so the site keeps the secret and asks again later.
			if ( $e->isAmbiguous() ) {
				return $this->keepUnresolved( $mode, $generationId, $accountId, $code );
			}

			// A refusal of this return's own, for example an expired code, leaves the connection in progress,
			// because another admin's return may be activating the same shared secret. A refusal of the secret
			// itself ends it.
			$ends = in_array( $e->getErrorCode(), self::DEAD_SECRET_CODES, true );

			return $this->complete( $mode, $generationId, $accountId, null, $isConfirmation, $ends, $code );
		}

		return $this->complete( $mode, $generationId, $accountId, SiteCredential::build( $pending['admin_base'], $pending['secret'] ), $isConfirmation );
	}

	/**
	 * Ends the connection in progress: saves the account and, when the platform activated it, the credential.
	 * Holding the lock orders this against Disconnect and against other returns, so a late return can't undo
	 * either.
	 *
	 * @param string      $mode
	 * @param string      $generationId
	 * @param string      $accountId
	 * @param string|null $credential   The activated credential, or null when there is none.
	 * @param bool        $isConfirmation Whether a missing credential means the platform refused a confirmation.
	 * @param bool        $ends           Whether this ends the connection in progress. A refusal of one return
	 *                                    doesn't: it only clears what that return left unresolved.
	 * @param string      $code           The code of the return being refused, when it doesn't end the connection.
	 *                                    Only an unresolved activation with this code is cleared.
	 * @return string|null A RESULT_* to show instead of success.
	 */
	private function complete( string $mode, string $generationId, string $accountId, $credential, bool $isConfirmation, bool $ends = true, string $code = '' ) {
		try {
			// The credential this replaces is not revoked: on the same address the platform already replaced
			// it, and under another address it may belong to the original site this one was copied from.
			return SiteCredential::withLock( $mode, function () use ( $mode, $generationId, $accountId, $credential, $isConfirmation, $ends, $code ) {
				$pending = SiteCredential::getPending( $mode );
				if ( null === $pending || $pending['id'] !== $generationId ) {
					// Another return with the same shared secret saved this very credential first.
					$saved = null !== $credential && SiteCredential::get( $mode ) === $credential && self::getConnectedAccountId( $this->options, $mode ) === $accountId;

					return $saved ? null : self::RESULT_EXPIRED;
				}

				$connectedAccountId = self::getConnectedAccountId( $this->options, $mode );
				if ( null !== $connectedAccountId && $connectedAccountId !== $accountId ) {
					SiteCredential::deletePending( $mode );

					return self::RESULT_REPLACED;
				}

				if ( $ends ) {
					SiteCredential::deletePending( $mode );
				} elseif ( '' !== $code && $pending['code'] === $code ) {
					// Another admin's unresolved activation, with another code, stays to be checked again.
					$pending['account'] = '';
					$pending['code'] = '';
					$pending['code_expires'] = 0;
					SiteCredential::savePending( $mode, $pending );
				}

				if ( null === $connectedAccountId ) {
					$this->saveAccount( $mode, $accountId );
				}

				if ( null === $credential ) {
					// A failed confirmation keeps the credential the site already has.
					return $isConfirmation ? self::RESULT_NOT_CONFIRMED : null;
				}

				SiteCredential::store( $mode, $credential );

				return null;
			} );
		} catch ( \RuntimeException $e ) {
			return self::RESULT_UNAVAILABLE;
		}
	}

	/**
	 * Ends a connection in progress without saving anything, when it belongs to this generation.
	 */
	private function retire( string $mode, string $generationId ): void {
		try {
			SiteCredential::withLock( $mode, static function () use ( $mode, $generationId ) {
				$pending = SiteCredential::getPending( $mode );
				if ( null !== $pending && $pending['id'] === $generationId ) {
					SiteCredential::deletePending( $mode );
				}
			} );
		} catch ( \RuntimeException $e ) {
			// The next start replaces it once it expires.
			$this->logger->error( __FUNCTION__, 'Cannot end the connection in progress', $e );
		}
	}

	/**
	 * Keeps a connection whose activation got no definite answer, so it can be checked again. A first
	 * connection saves the account now, as it would without a credential.
	 *
	 * @return string|null A RESULT_* to show instead of success.
	 */
	private function keepUnresolved( string $mode, string $generationId, string $accountId, string $code ) {
		try {
			return SiteCredential::withLock( $mode, function () use ( $mode, $generationId, $accountId, $code ) {
				$pending = SiteCredential::getPending( $mode );
				if ( null === $pending || $pending['id'] !== $generationId ) {
					return self::RESULT_EXPIRED;
				}

				$pending['account'] = $accountId;
				$pending['code'] = $code;
				$pending['code_expires'] = time() + SiteCredential::CODE_TTL;
				SiteCredential::savePending( $mode, $pending );

				if ( null === self::getConnectedAccountId( $this->options, $mode ) ) {
					$this->saveAccount( $mode, $accountId );
				}

				return null;
			} );
		} catch ( \RuntimeException $e ) {
			return self::RESULT_UNAVAILABLE;
		}
	}

	/**
	 * admin_init: settles a connection whose activation got no definite answer. It retries the activation
	 * while the platform's code is valid, and afterwards asks whether the credential is active.
	 */
	public function resolveUnconfirmed(): void {
		if ( wp_doing_ajax() || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		foreach ( [ 'test', 'live' ] as $mode ) {
			// Sites not connected through the platform skip the database reads below.
			if ( null === self::getConnectedAccountId( $this->options, $mode ) ) {
				continue;
			}

			$pending = SiteCredential::getPending( $mode );
			if ( null === $pending || '' === $pending['account'] ) {
				continue;
			}

			$throttle = 'wpfs_platform_resolve_' . $mode;
			if ( false !== get_transient( $throttle ) ) {
				continue;
			}
			set_transient( $throttle, 1, self::RESOLVE_INTERVAL );

			$this->resolve( $mode, $pending );
		}
	}

	/**
	 * @param string              $mode
	 * @param array<string,mixed> $pending
	 */
	private function resolve( string $mode, array $pending ): void {
		$accountId = (string) $pending['account'];
		$credential = SiteCredential::build( $pending['admin_base'], $pending['secret'] );
		$isConfirmation = SiteCredential::get( $mode ) !== null;

		$active = null;
		if ( '' !== $pending['code'] && (int) $pending['code_expires'] > time() ) {
			try {
				$this->stripe->activateSiteCredential( $mode, $accountId, (string) $pending['code'], (string) $pending['secret'] );
				$active = true;
			} catch ( PlatformRequestException $e ) {
				if ( $e->isAmbiguous() ) {
					return;
				}
				if ( in_array( $e->getErrorCode(), self::DEAD_SECRET_CODES, true ) ) {
					$this->complete( $mode, (string) $pending['id'], $accountId, null, $isConfirmation );

					return;
				}
				// A refused retry, for example an expired code, says nothing about the first attempt, which
				// may have activated the credential. Only the status tells.
			}
		}

		if ( null === $active ) {
			try {
				$active = $this->stripe->isSiteCredentialActive( $mode, $accountId, $credential );
			} catch ( PlatformRequestException $e ) {
				if ( $e->isAmbiguous() ) {
					return;
				}
				$active = false;
			}
		}

		$this->complete( $mode, (string) $pending['id'], $accountId, $active ? $credential : null, $isConfirmation, $active, (string) $pending['code'] );
	}

	/**
	 * admin_init: a return without an account, because the merchant cancelled on Stripe or the platform
	 * could not finish. Nothing is saved; the Stripe settings page says why.
	 */
	public function handleFailedReturn(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the connection's own token protects the one change made here.
		if ( ! isset( $_GET['page'], $_GET['error'], $_GET[ self::PARAM_TOKEN ] ) || Menu::SLUG_SETTINGS !== $_GET['page'] || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$error = sanitize_text_field( wp_unslash( $_GET['error'] ) );
		$mode = isset( $_GET['mode'] ) ? sanitize_text_field( wp_unslash( $_GET['mode'] ) ) : '';
		$token = sanitize_text_field( wp_unslash( $_GET[ self::PARAM_TOKEN ] ) );
		// phpcs:enable

		// The platform bound this connection's secret to another account or site, so the next try needs a
		// new one. Only the admin's own connection in progress is ended.
		if ( 'pending_conflict' === $error && $this->isValidMode( $mode ) ) {
			$pendingToken = $this->getPendingToken( $mode );
			$record = get_option( $this->getPendingKey( $mode ) );
			if ( null !== $pendingToken && hash_equals( $pendingToken, $token ) && is_array( $record ) ) {
				$this->retire( $mode, (string) ( $record['generation'] ?? '' ) );
			}
		}

		$this->redirectToResult( 'access_denied' === $error ? self::RESULT_CANCELLED : self::RESULT_EXPIRED, $mode );
	}

	/**
	 * @param string $result
	 * @param string $mode
	 */
	protected function redirectToResult( string $result, string $mode ): void {
		wp_safe_redirect( $this->getResultUrl( $result, $mode ) );
		exit;
	}

	/**
	 * @param string $mode
	 * @param string $accountId
	 */
	private function saveAccount( string $mode, string $accountId ): void {
		$isLive = 'live' === $mode;
		$this->options->setSeveral( [
			$isLive ? Options::OPTION_LIVE_ACCOUNT_ID : Options::OPTION_TEST_ACCOUNT_ID => $accountId,
			$isLive ? Options::OPTION_USE_WP_LIVE_PLATFORM : Options::OPTION_USE_WP_TEST_PLATFORM => 1,
		] );
	}

	/**
	 * admin_notices: asks the admin to confirm a Stripe connection that has no working credential yet.
	 */
	public static function displayConfirmationNotice(): void {
		if ( ! current_user_can( self::CAPABILITY ) || Utils::isDemoMode() ) {
			return;
		}

		// The Stripe settings page shows its own prompt with the button.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only decides where the notice shows.
		if ( isset( $_GET['page'] ) && Menu::SLUG_SETTINGS_STRIPE === $_GET['page'] ) {
			return;
		}

		$options = new Options();
		foreach ( [ 'live', 'test' ] as $mode ) {
			if ( ! SiteCredential::needsConfirmation( $mode, null !== self::getConnectedAccountId( $options, $mode ) ) ) {
				continue;
			}

			printf(
				'<div class="notice notice-warning wpfs-confirm-connection-notice"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
				esc_html( 'live' === $mode
					? __( 'WP Full Pay: please confirm your Stripe connection.', 'wp-full-stripe-free' )
					: __( 'WP Full Pay: please confirm your test Stripe connection.', 'wp-full-stripe-free' ) ),
				esc_html( 'live' === $mode
					? sprintf(
						/* translators: %s: date from which unconfirmed connections stop taking payments */
						__( 'It takes one quick sign-in to Stripe. From %s, payments stop on sites that haven\'t confirmed.', 'wp-full-stripe-free' ),
						self::getDeadline()
					)
					: sprintf(
						/* translators: %s: date from which unconfirmed connections stop taking test payments */
						__( 'From %s, test-mode payments stop on sites that haven\'t confirmed.', 'wp-full-stripe-free' ),
						self::getDeadline()
					) ),
				esc_url( Menu::getAdminUrlBySlugAndParams( Menu::SLUG_SETTINGS_STRIPE, [ 'mode' => $mode ] ) ),
				esc_html__( 'Confirm now', 'wp-full-stripe-free' )
			);
		}
	}

	/**
	 * The prompt with the Confirm button, on the Stripe settings page.
	 */
	public static function displayConfirmationPrompt(): void {
		if ( ! current_user_can( self::CAPABILITY ) || Utils::isDemoMode() ) {
			return;
		}

		$options = new Options();
		foreach ( [ 'live', 'test' ] as $mode ) {
			$accountId = self::getConnectedAccountId( $options, $mode );
			if ( null === $accountId || ! SiteCredential::needsConfirmation( $mode, true ) ) {
				continue;
			}

			// The page shows only the mode the site is in, so the prompt carries its own Disconnect for the other.
			// Removing access is not offered: it needs a sign-in to the account the merchant may not have any more.
			printf(
				'<div class="wpfs-inline-message wpfs-inline-message--warning wpfs-inline-message--mb wpfs-confirm-connection"><div class="wpfs-inline-message__inner"><p><strong>%1$s</strong> %2$s</p><p>%6$s</p><p>%3$s</p><p><button type="button" class="wpfs-btn wpfs-btn-primary wpfs-button-loader js-wpfs-confirm-stripe-connection" data-mode="%4$s">%5$s</button> <button type="button" class="wpfs-btn wpfs-btn-text js-wpfs-disconnect-stripe-mode" data-mode="%4$s" data-can-revoke="0">%7$s</button></p></div></div>',
				esc_html( 'live' === $mode
					? __( 'Please confirm your Stripe connection.', 'wp-full-stripe-free' )
					: __( 'Please confirm your test Stripe connection.', 'wp-full-stripe-free' ) ),
				esc_html( sprintf(
					/* translators: %s: date from which unconfirmed connections stop taking payments */
					__( 'Sign in to Stripe once; nothing about your account or payments changes. From %s, payments stop on sites that haven\'t confirmed.', 'wp-full-stripe-free' ),
					self::getDeadline()
				) ),
				esc_html__( 'Using this Stripe account on another site too? Update WP Full Pay there as well. Once you confirm here, older versions of WP Full Pay stop working with this account.', 'wp-full-stripe-free' ),
				esc_attr( $mode ),
				esc_html__( 'Confirm with Stripe', 'wp-full-stripe-free' ),
				esc_html( sprintf(
					'live' === $mode
						/* translators: %s: Stripe account ID, e.g. acct_123 */
						? __( 'This site uses Stripe account %s. Sign in to that account on Stripe. Can\'t sign in to it, or don\'t use it any more? Disconnect live mode, then connect the account you use.', 'wp-full-stripe-free' )
						/* translators: %s: Stripe account ID, e.g. acct_123 */
						: __( 'This site uses Stripe account %s in test mode. Sign in to that account on Stripe. Can\'t sign in to it, or don\'t use it any more? Disconnect test mode, then connect the account you use.', 'wp-full-stripe-free' ),
					$accountId
				) ),
				esc_html( self::getDisconnectLabel( $mode ) )
			);
		}
	}

	/**
	 * @return string The label of the prompt's button that disconnects this mode.
	 */
	private static function getDisconnectLabel( string $mode ): string {
		return 'live' === $mode ? __( 'Disconnect live mode', 'wp-full-stripe-free' ) : __( 'Disconnect test mode', 'wp-full-stripe-free' );
	}

	/**
	 * @return string The date from which unconfirmed connections stop taking payments, in UTC so it is the
	 *                same day in every time zone.
	 */
	private static function getDeadline(): string {
		return wp_date( get_option( 'date_format' ), (int) strtotime( self::CONFIRM_DEADLINE . ' 00:00:00 UTC' ), new \DateTimeZone( 'UTC' ) ) . ' (UTC)';
	}

	private function verifyRequest(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have sufficient permissions.', 'wp-full-stripe-free' ) ], 403 );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp-full-stripe-admin-nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'wp-full-stripe-free' ) ], 400 );
		}
	}

	/**
	 * Tells the merchant why nothing was connected, on the Stripe settings page.
	 * It is part of the page rather than an admin notice, because other notices can hide admin notices there.
	 */
	public static function displayResultNotice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects a message.
		if ( ! isset( $_GET['page'], $_GET[ self::PARAM_RESULT ] ) || Menu::SLUG_SETTINGS_STRIPE !== $_GET['page'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects a message.
		$mode = isset( $_GET['mode'] ) ? sanitize_text_field( wp_unslash( $_GET['mode'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects a message.
		$notice = self::getResultNotice( sanitize_text_field( wp_unslash( $_GET[ self::PARAM_RESULT ] ) ), $mode );
		if ( null === $notice ) {
			return;
		}

		printf(
			'<div class="wpfs-inline-message wpfs-inline-message--%1$s wpfs-inline-message--mb wpfs-connect-notice"><div class="wpfs-inline-message__inner">%2$s</div></div>',
			esc_attr( $notice['type'] ),
			wp_kses( $notice['message'], [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ] )
		);
	}

	/**
	 * @param string $result
	 * @param string $mode The mode the result is for, from the URL.
	 * @return array{type:string,message:string}|null
	 */
	private static function getResultNotice( string $result, string $mode ) {
		switch ( $result ) {
			case self::RESULT_OTHER_ACCOUNT:
				// The account comes from this site's settings, never from the URL, which anyone can write.
				$accountId = SiteCredential::isValidMode( $mode ) ? self::getConnectedAccountId( new Options(), $mode ) : null;
				if ( null === $accountId ) {
					return null;
				}

				// The account the merchant signed in to may be the one this site uses in the other mode, so removing
				// WP Full Pay's access to it in Stripe is not suggested.
				return [
					'type' => 'error',
					'message' => esc_html( sprintf(
						'live' === $mode
							/* translators: %1$s: Stripe account ID, e.g. acct_123. %2$s: the Disconnect button's label */
							? __( 'You signed in to a different Stripe account than the one this site uses (%1$s), so your connection was not confirmed. Nothing was changed. Click Confirm with Stripe and sign in to %1$s. If you can\'t, or you no longer use it, click %2$s, then connect the account you use.', 'wp-full-stripe-free' )
							/* translators: %1$s: Stripe account ID, e.g. acct_123. %2$s: the Disconnect button's label */
							: __( 'You signed in to a different Stripe account than the one this site uses in test mode (%1$s), so your test connection was not confirmed. Nothing was changed. Click Confirm with Stripe and sign in to %1$s. If you can\'t, or you no longer use it, click %2$s, then connect the account you use.', 'wp-full-stripe-free' ),
						$accountId,
						self::getDisconnectLabel( $mode )
					) ),
				];
			case self::RESULT_ALREADY_CONNECTED:
				return [
					'type' => 'warning',
					'message' => esc_html__( 'This site is already connected to a Stripe account in this mode. Disconnect it before you connect another account.', 'wp-full-stripe-free' ),
				];
			case self::RESULT_EXPIRED:
				return [
					'type' => 'warning',
					'message' => esc_html__( 'This Stripe connection was not started from this site, or it has expired. Nothing was changed. Click Connect with Stripe to try again.', 'wp-full-stripe-free' ),
				];
			case self::RESULT_NOT_CONNECTED:
				return [
					'type' => 'warning',
					'message' => esc_html__( 'This site isn\'t connected to Stripe through WP Full Pay in this mode, so there\'s nothing to confirm.', 'wp-full-stripe-free' ),
				];
			case self::RESULT_NOT_CONFIRMED:
				return [
					'type' => 'error',
					'message' => esc_html__( 'We couldn\'t confirm your Stripe connection. Your site is still connected. Please try again.', 'wp-full-stripe-free' ),
				];
			case self::RESULT_CANCELLED:
				return [
					'type' => 'warning',
					'message' => esc_html__( 'You cancelled on Stripe. Nothing was changed.', 'wp-full-stripe-free' ),
				];
			case self::RESULT_UNAVAILABLE:
				return [
					'type' => 'error',
					'message' => esc_html__( 'Another change to this Stripe connection is in progress. Nothing was changed. Please try again in a moment.', 'wp-full-stripe-free' ),
				];
			case self::RESULT_REPLACED:
				return [
					'type' => 'error',
					'message' => sprintf(
						/* translators: %1$s: opening anchor tag, %2$s: closing anchor tag */
						esc_html__( 'This site was connected to another Stripe account while you were on Stripe, so the account you just connected was not saved. WP Full Pay may still have access to that account: %1$sremove it in Stripe%2$s.', 'wp-full-stripe-free' ),
						'<a href="' . esc_url( tsdk_utmify( StripeAccessRevocation::DOCS_URL, 'connect-replaced', 'settings' ) ) . '" target="_blank" rel="noopener">',
						'</a>'
					),
				];
		}

		return null;
	}

	/**
	 * Uses up the token of the connection this admin started in this mode. Only a start in a known
	 * mode stores one. A token that does not match leaves it in place, so a stray link cannot cancel it.
	 *
	 * @param string $mode
	 * @param string $token
	 * @return array<string,mixed>|null The redeemed record, when the token matched and this request used it up.
	 */
	private function redeemToken( string $mode, string $token ) {
		$pending = $this->getPendingToken( $mode );
		if ( null === $pending || ! hash_equals( $pending, $token ) ) {
			return null;
		}

		$record = get_option( $this->getPendingKey( $mode ) );

		// Tabs share the token, so when their returns arrive together only the one that deletes it may save.
		// delete_option() answers from the database row, unlike a transient in an object cache.
		return is_array( $record ) && delete_option( $this->getPendingKey( $mode ) ) ? $record : null;
	}

	/**
	 * The token is kept in an option rather than a transient: an object cache can drop a transient
	 * before the merchant comes back, and some report every transient delete as successful.
	 *
	 * @param string $mode
	 * @return string|null The unexpired token of the connection this admin started in this mode.
	 */
	private function getPendingToken( string $mode ) {
		$pending = get_option( $this->getPendingKey( $mode ) );
		if ( ! is_array( $pending ) || empty( $pending['token'] ) || ! is_string( $pending['token'] ) || (int) ( $pending['expires'] ?? 0 ) < time() ) {
			return null;
		}

		return $pending['token'];
	}

	/**
	 * Sends the merchant to the notice that explains why nothing was connected.
	 *
	 * @param string $result
	 * @param string $mode
	 */
	private function sendRefusal( string $result, string $mode ): void {
		wp_send_json( [
			'success' => false,
			'redirectURL' => $this->getResultUrl( $result, $mode ),
		] );
	}

	/**
	 * @param string $result
	 * @param string $mode
	 * @return string The Stripe settings page showing the notice for this result.
	 */
	private function getResultUrl( string $result, string $mode ): string {
		return Menu::getAdminUrlBySlugAndParams(
			Menu::SLUG_SETTINGS_STRIPE,
			[
				self::PARAM_RESULT => $result,
				'mode' => $this->isValidMode( $mode ) ? $mode : '',
			]
		);
	}

	/**
	 * Whether the connection starts from the setup wizard.
	 */
	private function isOnboarding( string $currentUrl ): bool {
		parse_str( (string) wp_parse_url( $currentUrl, PHP_URL_QUERY ), $query );

		return isset( $query['onboarding'] ) && 'true' === $query['onboarding'];
	}

	private function isValidMode( string $mode ): bool {
		return 'test' === $mode || 'live' === $mode;
	}

	private function getPendingKey( string $mode ): string {
		return 'wpfs_connect_account_' . get_current_user_id() . '_' . $mode;
	}
}
