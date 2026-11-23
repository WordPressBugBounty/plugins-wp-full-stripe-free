<?php

namespace ThemeIsle\WPFullPay\Admin;

use ThemeIsle\WPFullPay\Context\StaticContext;
use ThemeIsle\WPFullPay\Exceptions\PlatformRequestException;
use ThemeIsle\WPFullPay\Logging\Logger;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Platform\SiteCredential;
use ThemeIsle\WPFullPay\Stripe;

/**
 * Removes WP Full Pay's access to a Connect account in Stripe when the merchant disconnects.
 *
 * Disconnecting only clears this site's settings, so the account stays connected to the platform.
 * To also remove the platform's access, the merchant signs in on Stripe again, which proves they
 * own the account. Stripe sends them back to the Stripe settings page with a ticket, and the
 * platform API redeems it to deauthorize the account.
 */
class StripeAccessRevocation {

	const AJAX_ACTION = 'wpfs-revoke-stripe-access';

	/** Query parameters the platform API adds when it sends the merchant back. */
	const PARAM_TICKET = 'wpfs_revoke_ticket';
	const PARAM_STATUS = 'wpfs_revoke';

	/** Query parameter carrying the outcome to the notice on the settings page. */
	const PARAM_RESULT = 'wpfs_revoke_result';

	const RESULT_REMOVED = 'removed';
	const RESULT_FAILED = 'failed';
	const RESULT_CANCELLED = 'cancelled';
	const RESULT_EXPIRED = 'expired';

	const STATUS_CANCELLED = 'cancelled';

	/** Tries the removal again with the ticket kept from a failed attempt. */
	const STATUS_RETRY = 'retry';

	/** Nonce action of the "Try again" link. */
	const NONCE_RETRY = 'wpfs-revoke-retry';

	/** The platform's tickets are valid for 15 minutes; the pending removal lives as long. */
	const PENDING_TTL = 15 * MINUTE_IN_SECONDS;

	const CAPABILITY = 'manage_options';

	const DOCS_URL = 'https://docs.themeisle.com/wp-full-pay/removing-from-stripe-authorized-applications';

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

		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'startRevocation' ] );
		add_action( 'admin_init', [ $this, 'handleReturn' ] );
	}

	/**
	 * Clears this site's Connect settings for a mode. The account stays connected to the platform.
	 *
	 * @param Options $options
	 * @param string  $mode 'test' or 'live'.
	 */
	public static function clearConnection( Options $options, string $mode ): void {
		if ( ! SiteCredential::isValidMode( $mode ) ) {
			return;
		}

		// Holding the lock orders this against a connection being finished, so that can't restore it afterwards.
		// While another request holds it, nothing is cleared and the caller asks the merchant to try again.
		SiteCredential::withLock( $mode, static function () use ( $options, $mode ) {
			self::clearSettings( $options, $mode );
		} );
	}

	/**
	 * Disconnects this site in a mode, then tells the platform to stop accepting the site's credential.
	 *
	 * @param Options $options
	 * @param Stripe  $stripe
	 * @param string  $mode 'test' or 'live'.
	 * @throws \RuntimeException When another request is changing the connection: nothing changed.
	 * @throws PlatformRequestException When the platform could not revoke the credential: the site is disconnected.
	 */
	public static function disconnect( Options $options, Stripe $stripe, string $mode ): void {
		$credential = SiteCredential::isValidMode( $mode ) ? SiteCredential::get( $mode ) : null;
		$accountId = StripeConnect::getConnectedAccountId( $options, $mode );

		self::clearConnection( $options, $mode );

		// A copy of the site under another address, such as a staging clone, holds the original site's
		// credential. Disconnecting the copy must not cut the original off.
		if ( null !== $credential && null !== $accountId && SiteCredential::belongsToThisSite( $credential ) ) {
			$stripe->revokeSiteCredential( $mode, $accountId, $credential );
		}
	}

	/**
	 * @param Options $options
	 * @param string  $mode 'test' or 'live'.
	 */
	private static function clearSettings( Options $options, string $mode ): void {
		SiteCredential::forget( $mode );

		if ( 'test' === $mode ) {
			$options->setSeveral( [
				Options::OPTION_TEST_ACCOUNT_ID => null,
				Options::OPTION_USE_WP_TEST_PLATFORM => null,
				Options::OPTION_TEST_ACCOUNT_STATUS => null,
				Options::OPTION_STRIPE_CONNECT_NOTICE => null,
			] );
		} elseif ( 'live' === $mode ) {
			$options->setSeveral( [
				Options::OPTION_LIVE_ACCOUNT_ID => null,
				Options::OPTION_USE_WP_LIVE_PLATFORM => null,
				Options::OPTION_LIVE_ACCOUNT_STATUS => null,
				Options::OPTION_STRIPE_CONNECT_NOTICE => null,
			] );
		}
	}

	/**
	 * Whether the platform's access to a connected account can be removed here.
	 *
	 * Only Standard accounts are connected through Stripe OAuth, which is what the platform can
	 * deauthorize, so this is true only for an account object whose type is `standard`. The settings
	 * page also offers the removal for an account it could not look up, because starting it looks
	 * the account up again.
	 *
	 * @param mixed $account The account object from the platform API, or null.
	 */
	public static function isRevocableAccount( $account ): bool {
		return is_object( $account ) && isset( $account->type ) && 'standard' === $account->type;
	}

	/**
	 * AJAX: starts the removal and answers with the Stripe URL to send the merchant to.
	 */
	public function startRevocation(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have sufficient permissions.', 'wp-full-stripe-free' ) ], 403 );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp-full-stripe-admin-nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'wp-full-stripe-free' ) ], 400 );
		}

		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : '';
		$accountId = $this->getConnectedAccountId( $mode );
		if ( null === $accountId ) {
			wp_send_json( [
				'success' => false,
				'msg' => __( 'This site is not connected with Stripe Connect in this mode.', 'wp-full-stripe-free' ),
			] );
		}

		$account = null;
		$lookedUp = true;
		try {
			$account = 'live' === $mode ? $this->stripe->getLiveAccount( $accountId ) : $this->stripe->getTestAccount( $accountId );
		} catch ( \Exception $ex ) {
			// A site without a working credential can't look the account up, but signing in on Stripe proves
			// ownership by itself, so the removal goes ahead.
			$previous = $ex->getPrevious();
			if ( ! ( $previous instanceof PlatformRequestException && $previous->isCredentialRefusal() ) ) {
				$this->logger->error( __FUNCTION__, 'Cannot retrieve the Stripe account', $ex );
				$this->sendStartFailure( $mode );

				return;
			}
			$lookedUp = false;
		}

		// An answer without an account is a failed lookup too, not an account of another kind.
		if ( $lookedUp && ! is_object( $account ) ) {
			$this->sendStartFailure( $mode );

			return;
		}

		if ( $lookedUp && ! self::isRevocableAccount( $account ) ) {
			wp_send_json( [
				'success' => false,
				'msg' => __( 'WP Full Pay\'s access to this Stripe account cannot be removed from here. Contact WP Full Pay support to remove it.', 'wp-full-stripe-free' ),
			] );
		}

		$nonce = bin2hex( random_bytes( 16 ) );
		set_transient( $this->getPendingKey( $mode ), [ 'nonce' => $nonce, 'accountId' => $accountId ], self::PENDING_TTL );

		try {
			$redirectUrl = $this->stripe->getRevokeAccessLink( Menu::getAdminUrlBySlug( Menu::SLUG_SETTINGS_STRIPE ), $mode, $nonce );
		} catch ( \Exception $ex ) {
			delete_transient( $this->getPendingKey( $mode ) );
			$this->logger->error( __FUNCTION__, 'Cannot start removing access to the Stripe account', $ex );
			$this->sendStartFailure( $mode );

			return;
		}

		wp_send_json( [
			'success' => true,
			'msg' => __( 'Redirecting to Stripe...', 'wp-full-stripe-free' ),
			'redirectURL' => $redirectUrl,
		] );
	}

	/**
	 * admin_init: finishes the removal when Stripe sends the merchant back to the Stripe settings page.
	 */
	public function handleReturn(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the pending removal's own nonce protects this request.
		if ( ! isset( $_GET['page'] ) || Menu::SLUG_SETTINGS_STRIPE !== $_GET['page'] ) {
			return;
		}
		if ( ! isset( $_GET[ self::PARAM_TICKET ] ) && ! isset( $_GET[ self::PARAM_STATUS ] ) ) {
			return;
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$mode = isset( $_GET['mode'] ) ? sanitize_text_field( wp_unslash( $_GET['mode'] ) ) : '';
		$ticket = isset( $_GET[ self::PARAM_TICKET ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::PARAM_TICKET ] ) ) : '';
		$status = isset( $_GET[ self::PARAM_STATUS ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::PARAM_STATUS ] ) ) : '';
		// phpcs:enable

		// A return from Stripe carries the pending removal's nonce; "Try again" is a link on this site.
		if ( self::STATUS_RETRY === $status && ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::NONCE_RETRY ) ) ) {
			return;
		}

		$this->redirectWithResult( $this->completeRevocation( $mode, $ticket, $status ), $mode );
	}

	/**
	 * Tells the merchant how the removal ended, on the Stripe settings page.
	 * It is part of the page rather than an admin notice, because other notices can hide admin notices there.
	 */
	public static function displayResultNotice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects a message.
		if ( ! isset( $_GET['page'], $_GET[ self::PARAM_RESULT ] ) || Menu::SLUG_SETTINGS_STRIPE !== $_GET['page'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects a message.
		$result = sanitize_text_field( wp_unslash( $_GET[ self::PARAM_RESULT ] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects a message.
		$mode = isset( $_GET['mode'] ) ? sanitize_text_field( wp_unslash( $_GET['mode'] ) ) : '';
		$notice = self::getResultNotice( $result, $mode );
		if ( null === $notice ) {
			return;
		}

		printf(
			'<div class="wpfs-inline-message wpfs-inline-message--%1$s wpfs-inline-message--mb wpfs-revoke-notice"><div class="wpfs-inline-message__inner">%2$s</div></div>',
			esc_attr( $notice['type'] ),
			wp_kses( $notice['message'], [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ] )
		);
	}

	/**
	 * @param string $mode
	 * @param string $ticket
	 * @param string $status
	 * @return string One of the RESULT_* constants.
	 */
	private function completeRevocation( string $mode, string $ticket, string $status ): string {
		if ( ! $this->isValidMode( $mode ) ) {
			return self::RESULT_EXPIRED;
		}

		// "Try again" uses the record a failed attempt kept, and leaves it in place until the removal finishes, so
		// a request that ends during the platform call can still be retried. Any other return uses up the
		// removal it started.
		$isRetry = self::STATUS_RETRY === $status;
		$key = $isRetry ? self::getRetryKey( $mode ) : $this->getPendingKey( $mode );
		$pending = get_transient( $key );
		if ( ! $isRetry ) {
			delete_transient( $key );
		}
		if ( ! is_array( $pending ) || empty( $pending['nonce'] ) || empty( $pending['accountId'] ) ) {
			delete_transient( $key );

			// A return reloaded after its request ended during the platform call: the ticket it kept offers
			// "Try again".
			return ! $isRetry && null !== self::getUsableRetry( $this->options, $mode ) ? self::RESULT_FAILED : self::RESULT_EXPIRED;
		}

		if ( self::STATUS_RETRY === $status ) {
			$ticket = isset( $pending['ticket'] ) && is_string( $pending['ticket'] ) ? $pending['ticket'] : '';
			if ( '' === $ticket ) {
				return self::RESULT_EXPIRED;
			}
		}

		// The site was disconnected or connected to another account since the removal started.
		if ( $this->getConnectedAccountId( $mode ) !== $pending['accountId'] ) {
			delete_transient( $key );

			return self::RESULT_EXPIRED;
		}

		if ( '' === $ticket ) {
			return self::STATUS_CANCELLED === $status ? self::RESULT_CANCELLED : self::RESULT_FAILED;
		}

		// The ticket is kept before the platform call, so a request that ends during it can still be retried.
		$this->keepForRetry( $mode, $pending, $ticket );

		// Only the platform can tell a valid ticket from a forged one, so nothing changes here until it confirms.
		try {
			$this->stripe->deauthorizeAccount( $mode, $ticket, $pending['accountId'], $pending['nonce'] );
		} catch ( \Exception $ex ) {
			$this->logger->error( __FUNCTION__, 'Cannot remove access to the Stripe account', $ex );

			// Stripe may have removed the access while the platform failed to record it, so the ticket is kept
			// for "Try again" until it expires.
			$this->keepForRetry( $mode, $pending, $ticket );

			return self::RESULT_FAILED;
		}

		try {
			self::clearConnection( $this->options, $mode );
		} catch ( \RuntimeException $ex ) {
			// The access is gone; "Try again" repeats the removal, which the platform accepts again, and clears.
			$this->keepForRetry( $mode, $pending, $ticket );

			return self::RESULT_FAILED;
		}

		delete_transient( self::getRetryKey( $mode ) );

		return self::RESULT_REMOVED;
	}

	/**
	 * @param string $result
	 * @param string $mode
	 */
	private function redirectWithResult( string $result, string $mode ): void {
		wp_safe_redirect( $this->getResultUrl( $result, $mode ) );
		exit;
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
	 * @param string $result
	 * @param string $mode
	 * @return array{type:string,message:string}|null
	 */
	private static function getResultNotice( string $result, string $mode = '' ) {
		switch ( $result ) {
			case self::RESULT_REMOVED:
				return [
					'type' => 'info',
					'message' => esc_html__( 'WP Full Pay no longer has access to your Stripe account, and this site is disconnected from it.', 'wp-full-stripe-free' ),
				];
			case self::RESULT_FAILED:
				$docsLink = '<a href="' . esc_url( tsdk_utmify( self::DOCS_URL, 'revoke-access', 'settings' ) ) . '" target="_blank" rel="noopener">';

				// "Try again" can only finish a removal whose ticket a failed attempt kept. A removal that failed to
				// start is tried again from Disconnect.
				$message = null === self::getUsableRetry( new Options(), $mode )
					? sprintf(
						/* translators: %1$s: opening anchor tag, %2$s: closing anchor tag */
						esc_html__( 'WP Full Pay\'s access to your Stripe account could not be removed, and this site is still connected. Try again, or disconnect without removing access and %1$sremove it in Stripe%2$s.', 'wp-full-stripe-free' ),
						$docsLink,
						'</a>'
					)
					: sprintf(
						/* translators: %1$s: opening anchor tag, %2$s: closing anchor tag */
						esc_html__( 'WP Full Pay\'s access to your Stripe account could not be removed, and this site is still connected. %3$sTry again%4$s, or disconnect without removing access and %1$sremove it in Stripe%2$s.', 'wp-full-stripe-free' ),
						$docsLink,
						'</a>',
						'<a href="' . esc_url( wp_nonce_url( Menu::getAdminUrlBySlugAndParams( Menu::SLUG_SETTINGS_STRIPE, [ self::PARAM_STATUS => self::STATUS_RETRY, 'mode' => $mode ] ), self::NONCE_RETRY ) ) . '">',
						'</a>'
					);

				return [
					'type' => 'error',
					'message' => $message,
				];
			case self::RESULT_CANCELLED:
				return [
					'type' => 'warning',
					'message' => esc_html__( 'Removal was cancelled on Stripe. This site is still connected to your Stripe account.', 'wp-full-stripe-free' ),
				];
			case self::RESULT_EXPIRED:
				return [
					'type' => 'warning',
					'message' => esc_html__( 'The request to remove WP Full Pay\'s access has expired or was already used. Nothing was changed. Click Disconnect to try again.', 'wp-full-stripe-free' ),
				];
		}

		return null;
	}

	/**
	 * @param string $mode
	 * @return string|null The account this site is connected to through the platform in this mode.
	 */
	private function getConnectedAccountId( string $mode ) {
		return StripeConnect::getConnectedAccountId( $this->options, $mode );
	}

	/**
	 * Sends the dialog to the failure notice, which explains how to remove access in Stripe instead.
	 *
	 * @param string $mode
	 */
	private function sendStartFailure( string $mode ): void {
		wp_send_json( [
			'success' => false,
			'redirectURL' => $this->getResultUrl( self::RESULT_FAILED, $mode ),
		] );
	}

	private function isValidMode( string $mode ): bool {
		return 'test' === $mode || 'live' === $mode;
	}

	private function getPendingKey( string $mode ): string {
		return 'wpfs_revoke_access_' . get_current_user_id() . '_' . $mode;
	}

	/**
	 * Keeps the removal's ticket for "Try again" in a record apart from the pending removal, so it never replaces
	 * a removal started meanwhile in another tab. Called before the platform call and after a failure; forgets
	 * the record once the site is no longer connected to that account.
	 *
	 * @param string                              $mode
	 * @param array{nonce:string,accountId:string} $pending
	 * @param string                              $ticket
	 */
	private function keepForRetry( string $mode, array $pending, string $ticket ): void {
		// Another "Try again" may have finished the removal meanwhile; a finished removal keeps no ticket.
		if ( $this->getConnectedAccountId( $mode ) !== $pending['accountId'] ) {
			delete_transient( self::getRetryKey( $mode ) );

			return;
		}

		set_transient( self::getRetryKey( $mode ), array_merge( $pending, [ 'ticket' => $ticket ] ), self::PENDING_TTL );
	}

	/**
	 * A ticket left behind for an account the site no longer uses, for example by an overlapping "Try again",
	 * is not offered: retrying it would only answer "expired".
	 *
	 * @param Options $options
	 * @param string  $mode
	 * @return array<string,string>|null The kept ticket's record, when it belongs to the connected account.
	 */
	private static function getUsableRetry( Options $options, string $mode ) {
		$retry = get_transient( self::getRetryKey( $mode ) );
		if ( ! is_array( $retry ) || empty( $retry['ticket'] ) || empty( $retry['accountId'] ) ) {
			return null;
		}

		return StripeConnect::getConnectedAccountId( $options, $mode ) === $retry['accountId'] ? $retry : null;
	}

	private static function getRetryKey( string $mode ): string {
		return 'wpfs_revoke_retry_' . get_current_user_id() . '_' . $mode;
	}
}
