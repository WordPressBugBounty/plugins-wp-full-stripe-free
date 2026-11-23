<?php

namespace ThemeIsle\WPFullPay\Platform;

/**
 * The credential that tells the WP Full Pay platform API which site is calling.
 *
 * The site creates the secret itself and saves it before asking the platform for anything, so a lost
 * answer never leaves it without the value the platform expects. Only a sha256 hash of the secret
 * travels through the browser. Each mode has its own credential, kept in its own option rather than in
 * the shared settings option, which other requests rewrite as a whole.
 *
 * A connection in progress is one "pending generation" per mode, shared by every admin and tab. The
 * code that finishes a flow, and Disconnect, both hold the mode's lock while they check the generation
 * and write, so a late answer from an older flow can never overwrite a newer result or undo a Disconnect.
 */
final class SiteCredential {

	const PREFIX = 'wpfpsc1';

	const HEADER = 'X-WPFP-Site-Credential';
	const VERSION_HEADER = 'X-WPFP-Plugin-Version';

	const OPTION_CREDENTIAL = 'wpfs_platform_credential_';
	const OPTION_PENDING = 'wpfs_platform_pending_';
	const OPTION_REFUSED = 'wpfs_platform_credential_refused_';

	/** Signing in, or creating an account, on Stripe can take a while. Matches StripeConnect::PENDING_TTL. */
	const PENDING_TTL = DAY_IN_SECONDS;

	/** How long the platform accepts the code it sends back with the merchant. */
	const CODE_TTL = 15 * MINUTE_IN_SECONDS;

	const LOCK_TIMEOUT = 10;

	/**
	 * @param mixed $mode
	 */
	public static function isValidMode( $mode ): bool {
		return 'test' === $mode || 'live' === $mode;
	}

	/**
	 * The address of this installation's admin, which identifies the site to the platform: lowercase
	 * scheme and host, the port when there is one, and the full path ending in a slash. Bytes outside
	 * printable ASCII are percent-encoded, so the site and the platform hash the same characters.
	 */
	public static function adminBase(): string {
		$parts = wp_parse_url( admin_url( '/' ) );
		$scheme = strtolower( $parts['scheme'] ?? 'https' );
		$host = strtolower( $parts['host'] ?? '' );
		$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$path = '/' . trim( $parts['path'] ?? '', '/' );

		return self::encodeNonAscii( $scheme . '://' . $host . $port . rtrim( $path, '/' ) . '/' );
	}

	/**
	 * @return string The site key part of a credential: which installation it was issued to.
	 */
	public static function siteKeyOf( string $credential ): string {
		$parts = explode( '.', $credential );

		return 3 === count( $parts ) && self::PREFIX === $parts[0] ? $parts[1] : '';
	}

	/**
	 * Whether a credential was issued to this installation's current admin address. A copy of the site
	 * under another address, such as a staging clone, holds a credential that is not its own.
	 */
	public static function belongsToThisSite( string $credential ): bool {
		return hash_equals( self::base64Url( hash( 'sha256', self::adminBase(), true ) ), self::siteKeyOf( $credential ) );
	}

	private static function encodeNonAscii( string $url ): string {
		return (string) preg_replace_callback(
			'/[^\x21-\x7E]/',
			static function ( $match ) {
				return '%' . strtoupper( bin2hex( $match[0] ) );
			},
			$url
		);
	}

	/**
	 * @return string base64url, without padding, of sha256 over the secret (RFC 7636 S256).
	 */
	public static function challenge( string $secret ): string {
		return self::base64Url( hash( 'sha256', $secret, true ) );
	}

	/**
	 * @return string The header value for this site and secret. The platform finds the site's row from it.
	 */
	public static function build( string $adminBase, string $secret ): string {
		return self::PREFIX . '.' . self::base64Url( hash( 'sha256', $adminBase, true ) ) . '.' . $secret;
	}

	public static function newSecret(): string {
		return self::base64Url( random_bytes( 32 ) );
	}

	/**
	 * @return string|null The credential this site sends in this mode.
	 */
	public static function get( string $mode ) {
		if ( ! self::isValidMode( $mode ) ) {
			return null;
		}

		$credential = get_option( self::OPTION_CREDENTIAL . $mode );

		return is_string( $credential ) && '' !== $credential ? $credential : null;
	}

	/**
	 * Runs $callback while holding this mode's lock. Every write to the credential, the pending generation
	 * and the connection settings that has to be ordered against another request goes through here.
	 *
	 * @template T
	 * @param string          $mode
	 * @param callable(): T   $callback
	 * @return T
	 * @throws \RuntimeException When the lock can't be taken.
	 */
	public static function withLock( string $mode, callable $callback ) {
		global $wpdb;

		$name = self::lockName( $mode );
		$suppress = $wpdb->suppress_errors( true );
		$locked = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, self::LOCK_TIMEOUT ) );
		$wpdb->suppress_errors( $suppress );

		// A database without named locks, such as the SQLite drop-in, answers nothing. The lock only orders rare
		// races, so the change goes ahead rather than leaving the site unable to connect or disconnect.
		if ( null === $locked ) {
			return $callback();
		}
		if ( '1' !== (string) $locked ) {
			throw new \RuntimeException( __( 'Another change to this Stripe connection is in progress. Nothing was changed. Please try again in a moment.', 'wp-full-stripe-free' ) );
		}

		try {
			return $callback();
		} finally {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		}
	}

	/**
	 * The connection in progress in this mode, read from the database rather than a cache.
	 *
	 * @return array{id:string,secret:string,admin_base:string,created:int,account:string,code:string,code_expires:int}|null
	 */
	public static function getPending( string $mode ) {
		global $wpdb;

		if ( ! self::isValidMode( $mode ) ) {
			return null;
		}

		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::OPTION_PENDING . $mode ) );
		$pending = null === $raw ? null : maybe_unserialize( $raw );
		if ( ! is_array( $pending ) || empty( $pending['id'] ) || empty( $pending['secret'] ) || empty( $pending['admin_base'] ) ) {
			return null;
		}

		return array_merge( [ 'created' => 0, 'account' => '', 'code' => '', 'code_expires' => 0 ], $pending );
	}

	/**
	 * Reuses the connection in progress, so a second click, tab or admin carries the same secret, or starts
	 * a new one when there is none, it expired, or the site's address changed.
	 *
	 * @return array{id:string,secret:string,admin_base:string,created:int,account:string,code:string,code_expires:int}
	 */
	public static function startPending( string $mode ): array {
		return self::withLock( $mode, static function () use ( $mode ) {
			$adminBase = self::adminBase();
			$pending = self::getPending( $mode );
			if ( null !== $pending && $pending['admin_base'] === $adminBase && $pending['created'] + self::PENDING_TTL > time() ) {
				return $pending;
			}

			$pending = [
				'id' => bin2hex( random_bytes( 16 ) ),
				'secret' => self::newSecret(),
				'admin_base' => $adminBase,
				'created' => time(),
				'account' => '',
				'code' => '',
				'code_expires' => 0,
			];
			self::savePending( $mode, $pending );

			return $pending;
		} );
	}

	/**
	 * Call only while holding the mode's lock.
	 *
	 * @param array<string,mixed> $pending
	 */
	public static function savePending( string $mode, array $pending ): void {
		update_option( self::OPTION_PENDING . $mode, $pending, false );
	}

	/**
	 * Call only while holding the mode's lock.
	 */
	public static function deletePending( string $mode ): void {
		delete_option( self::OPTION_PENDING . $mode );
	}

	/**
	 * Call only while holding the mode's lock.
	 */
	public static function store( string $mode, string $credential ): void {
		update_option( self::OPTION_CREDENTIAL . $mode, $credential, true );
		delete_option( self::OPTION_REFUSED . $mode );
	}

	/**
	 * Forgets everything about this mode's credential: the credential, the connection in progress and a
	 * recorded refusal. Call only while holding the mode's lock.
	 */
	public static function forget( string $mode ): void {
		delete_option( self::OPTION_CREDENTIAL . $mode );
		delete_option( self::OPTION_PENDING . $mode );
		delete_option( self::OPTION_REFUSED . $mode );
	}

	/**
	 * @return string|null The credential stored in this mode, read from the database rather than a cache that
	 *                     another request may have made stale.
	 */
	public static function getStored( string $mode ) {
		global $wpdb;

		if ( ! self::isValidMode( $mode ) ) {
			return null;
		}

		$credential = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::OPTION_CREDENTIAL . $mode ) );

		return is_string( $credential ) && '' !== $credential ? $credential : null;
	}

	/**
	 * Forgets a recorded refusal once the platform accepts this site's credential again.
	 */
	public static function clearRefused( string $mode ): void {
		if ( false !== get_option( self::OPTION_REFUSED . $mode ) ) {
			delete_option( self::OPTION_REFUSED . $mode );
		}
	}

	/**
	 * Remembers that the platform refused this site's credential, so the admin is asked to confirm.
	 */
	public static function markRefused( string $mode ): void {
		if ( self::isValidMode( $mode ) && false === get_option( self::OPTION_REFUSED . $mode ) ) {
			update_option( self::OPTION_REFUSED . $mode, time(), false );
		}
	}

	/**
	 * Whether a connection in progress ended without a definite answer and is still being resolved.
	 */
	public static function isUnresolved( string $mode ): bool {
		$pending = self::getPending( $mode );

		return null !== $pending && '' !== $pending['account'];
	}

	/**
	 * Whether the admin should confirm this mode's Stripe connection: the site is connected through the
	 * platform, but has no credential, the platform refused it, or a confirmation is still unresolved.
	 */
	public static function needsConfirmation( string $mode, bool $connectedThroughPlatform ): bool {
		if ( ! $connectedThroughPlatform || ! self::isValidMode( $mode ) ) {
			return false;
		}

		return null === self::get( $mode ) || false !== get_option( self::OPTION_REFUSED . $mode ) || self::isUnresolved( $mode );
	}

	public static function lockName( string $mode ): string {
		global $wpdb;

		// Locks are server-wide, so the name includes the database, the table prefix and the subsite.
		return 'wpfs_pc_' . substr( md5( DB_NAME . '|' . $wpdb->prefix . '|' . get_current_blog_id() ), 0, 16 ) . '_' . $mode;
	}

	private static function base64Url( string $bytes ): string {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
	}
}
