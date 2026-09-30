<?php
/*
 * This is a generic license manager for WP Full Pay
 */

class WPFS_License {

	/**
	 * Price ID to licence type map
	 *
	 * @var int[]
	 */
	public static $plans_map = [
		1 => 1,
		2 => 2,
		3 => 3,
		4 => 4,
		5 => 1,
		6 => 2,
		7 => 3,
	];

	/**
	 * Get Namespace.
	 *
	 * @return string
	 */
	public static function get_namespace() {
		$namespace = basename( dirname( WP_FULL_STRIPE_BASENAME ) );
		$namespace = str_replace( '-', '_', strtolower( trim( $namespace ) ) );
		return $namespace;
	}

	/**
	 * Get the license data.
	 *
	 * @return bool|\stdClass
	 */
	public static function get_data() {
		$namespace = self::get_namespace();
		return get_option( $namespace . '_license_data' );
	}

	/**
	 * Get active license.
	 *
	 * @return bool
	 */
	public static function is_active() {
		$status = self::get_data();

		if ( ! $status ) {
			return false;
		}

		if ( ! isset( $status->license ) ) {
			return false;
		}

		if ( 'valid' !== $status->license ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether the license should be treated as valid for the platform fee decision.
	 *
	 * Unlike is_active(), a license-check failure must not silently flip this
	 * to false (issue #535): on URL-mismatch or unknown-key statuses the store
	 * is re-asked with legitimate URL variants of this site, and only an
	 * explicit "no" from the store (or an expired/disabled license) applies
	 * the fee. A store outage gets a bounded grace window, never open-ended.
	 *
	 * @return bool
	 */
	public static function has_valid_key_for_fees() {
		if ( self::is_active() ) {
			self::record_last_valid();
			return true;
		}

		$data = self::get_data();

		if ( ! $data || ! isset( $data->license ) ) {
			return false;
		}

		// Only URL-mismatch and unknown-key statuses are worth re-checking;
		// expired/disabled licenses pay the fee immediately, as before. They
		// also drop the evidence: the SDK rewrites such a status to 'invalid'
		// when its own store check fails, which must not reopen the grace.
		if ( ! in_array( $data->license, [ 'site_inactive', 'invalid' ], true ) ) {
			self::forget_fee_evidence();
			return false;
		}

		$verdict = self::recheck_license_with_store();

		if ( 'confirmed' === $verdict ) {
			return true;
		}

		if ( 'denied' === $verdict ) {
			// The store actively refused every variant: the license was moved
			// or deactivated, so no local evidence may waive the fee - not now,
			// and not through a later outage once this verdict's cache expires.
			delete_option( self::get_namespace() . '_license_last_valid' );
			return false;
		}

		// Store unreachable: bounded grace from the last time the license was
		// seen valid on this site, so a store outage cannot start charging.
		$last_valid = self::get_last_valid();

		return false !== $last_valid && ( time() - $last_valid['time'] ) < 7 * DAY_IN_SECONDS;
	}

	/**
	 * Drop both pieces of fee evidence: the last-valid record and the cached
	 * store verdict.
	 *
	 * @return void
	 */
	private static function forget_fee_evidence() {
		delete_option( self::get_namespace() . '_license_last_valid' );
		delete_transient( self::get_namespace() . '_license_fee_recheck' );
	}

	/**
	 * Fingerprint of a license key for cache invalidation. Not salted: the
	 * raw key sits unencrypted in the license data option, so a salt
	 * protects nothing.
	 *
	 * @param string $key License key.
	 * @return string
	 */
	private static function hash_key( $key ) {
		return hash( 'sha256', $key );
	}

	/**
	 * Remember that the license was seen valid on this site: which key, when,
	 * and under which URL. Refreshed at most daily to avoid a DB write on
	 * every request.
	 *
	 * @param string $url The URL the store accepted; defaults to home_url().
	 * @return void
	 */
	private static function record_last_valid( $url = '' ) {
		$option = self::get_namespace() . '_license_last_valid';
		$last   = get_option( $option );
		$stamp  = [ 'key' => self::hash_key( self::get_key() ), 'time' => time(), 'url' => '' === $url ? home_url() : $url ];

		if ( ! is_array( $last ) || ! isset( $last['key'], $last['time'], $last['url'] )
			|| $last['key'] !== $stamp['key'] || $last['url'] !== $stamp['url']
			|| ( time() - (int) $last['time'] ) > DAY_IN_SECONDS ) {
			update_option( $option, $stamp );
		}
	}

	/**
	 * The last-known-valid record for the currently configured key, or
	 * false. Records stamped for another install do not count: a cloned
	 * database must not borrow another domain's or subdirectory's evidence.
	 *
	 * @return array{key: string, time: int, url: string}|false
	 */
	private static function get_last_valid() {
		$last = get_option( self::get_namespace() . '_license_last_valid' );

		if ( ! is_array( $last ) || ! isset( $last['key'], $last['time'] ) ) {
			return false;
		}

		if ( self::hash_key( self::get_key() ) !== $last['key'] ) {
			return false;
		}

		if ( empty( $last['url'] ) || ! self::is_same_site( $last['url'], home_url() ) ) {
			return false;
		}

		$last['time'] = (int) $last['time'];

		return $last;
	}

	/**
	 * Ask the store whether this key is genuinely activated for a legitimate
	 * URL variant of this site. The verdict is cached so the payment path is
	 * not hitting the API.
	 *
	 * @return 'confirmed'|'denied'|'unreachable'
	 */
	private static function recheck_license_with_store() {
		$key = self::get_key();

		if ( '' === $key ) {
			return 'denied';
		}

		$home   = home_url();
		$cached = self::get_cached_verdict( $key, $home );

		if ( false !== $cached ) {
			return $cached;
		}

		if ( ! has_filter( 'themeisle_sdk_license_process_wpfs' ) ) {
			// A check before the SDK registers this filter on init must not
			// cache the miss as a store outage.
			return 'unreachable';
		}

		$verdict = 'denied';

		foreach ( self::get_own_url_variants() as $variant ) {
			$result = self::query_store( $key, $variant );

			if ( 'valid' === $result ) {
				// Refresh the grace evidence with the URL the store accepted,
				// not home_url(): a permanent mismatch (issue #535) never goes
				// locally valid again, and the accepted URL can be the only
				// variant the store knows (e.g. a scheme or path difference).
				self::record_last_valid( $variant );
				$verdict = 'confirmed';
				break;
			}

			if ( 'unreachable' === $result ) {
				// Never report a hard "denied" for a variant we could not
				// verify, and stop: each further variant would wait for its
				// own HTTP timeout on the checkout path.
				$verdict = 'unreachable';
				break;
			}
		}

		// Definitive answers hold for 12h; outages are retried hourly.
		$ttl = 'unreachable' === $verdict ? HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS;
		set_transient( self::get_namespace() . '_license_fee_recheck', [ 'key' => self::hash_key( $key ), 'verdict' => $verdict, 'url' => $home ], $ttl );

		return $verdict;
	}

	/**
	 * The cached store verdict for this key and install, or false. A verdict
	 * is bound to the key and the install it was obtained for: a cloned
	 * database must not carry a "confirmed" over.
	 *
	 * @param string $key  License key.
	 * @param string $home Current home URL.
	 * @return 'confirmed'|'denied'|'unreachable'|false
	 */
	private static function get_cached_verdict( $key, $home ) {
		$cached = get_transient( self::get_namespace() . '_license_fee_recheck' );

		if ( ! is_array( $cached ) || ! isset( $cached['key'], $cached['verdict'], $cached['url'] ) ) {
			return false;
		}

		if ( self::hash_key( $key ) !== $cached['key'] || ! self::is_same_site( $cached['url'], $home ) ) {
			return false;
		}

		return $cached['verdict'];
	}

	/**
	 * Legitimate URL variants of this site to re-check against the store:
	 * the URL recorded when the license was last valid, parent domains of
	 * the current host up to its registrable domain (admin.example.com ->
	 * example.com), and the www/non-www form of that root.
	 *
	 * @return list<string>
	 */
	private static function get_own_url_variants() {
		$home = home_url();
		$host = wp_parse_url( $home, PHP_URL_HOST );

		if ( empty( $host ) ) {
			return [];
		}

		// Hostnames are case-insensitive; the store gets the canonical form.
		$host = strtolower( $host );

		$variants = [];

		// get_last_valid() already rejects stamps from another site family.
		$last_valid = self::get_last_valid();
		if ( false !== $last_valid ) {
			$variants[] = $last_valid['url'];
		}

		// The registrable domain is the likeliest activation URL. Never walk
		// past it: a public suffix (co.uk) is shared with unrelated sites.
		$root = self::get_registrable_domain( $host );
		if ( false !== $root && $root !== $host ) {
			$variants[] = self::url_with_host( $home, $root );
		}

		// The www form of the root, not of the current host: a site served
		// from admin.example.org was licensed at www.example.org, never at
		// www.admin.example.org. An IP has no www form.
		$flip_base = false !== $root ? $root : $host;
		if ( ! filter_var( $flip_base, FILTER_VALIDATE_IP ) ) {
			$variants[] = self::url_with_host( $home, 0 === strpos( $flip_base, 'www.' ) ? substr( $flip_base, 4 ) : 'www.' . $flip_base );
		}

		// Intermediate parents are the only variants the cap may trim.
		$intermediates = [];
		if ( false !== $root ) {
			foreach ( self::get_intermediate_parents( $host, $root ) as $parent ) {
				$intermediates[] = self::url_with_host( $home, $parent );
			}
		}

		// The current URL comes last: the SDK's stored status already failed
		// for it, but that status can be a stale network failure, and for a
		// host with no parents (localhost, an IP) it is the only URL there is.
		$variants = array_values( array_unique( $variants ) );
		$room     = max( 0, 5 - count( $variants ) - 1 );
		$variants = array_merge( $variants, array_slice( $intermediates, 0, $room ), [ $home ] );

		return array_values( array_unique( $variants ) );
	}

	/**
	 * The URL with its host replaced, keeping its scheme, port and path.
	 *
	 * @param string $url  Source URL.
	 * @param string $host Replacement host.
	 * @return string
	 */
	private static function url_with_host( $url, $host ) {
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$port   = wp_parse_url( $url, PHP_URL_PORT );
		$path   = wp_parse_url( $url, PHP_URL_PATH );

		return ( $scheme ? $scheme : 'https' ) . '://' . $host . ( $port ? ':' . $port : '' ) . ( $path ? $path : '' );
	}

	/**
	 * Parent hosts between a host and its registrable domain, nearest first
	 * (shop.eu.example.org -> eu.example.org). Neither end is included.
	 *
	 * @param string $host Host name.
	 * @param string $root Registrable domain of the host.
	 * @return list<string>
	 */
	private static function get_intermediate_parents( $host, $root ) {
		$labels  = explode( '.', $host );
		$parents = [];

		while ( implode( '.', $labels ) !== $root && count( $labels ) > 1 ) {
			array_shift( $labels );
			$parent = implode( '.', $labels );
			if ( $parent !== $root ) {
				$parents[] = $parent;
			}
		}

		return $parents;
	}

	/**
	 * Whether two URLs belong to the same install: hosts in the same site
	 * family, the same port and the same path. example.org/blog and
	 * example.org/shop, or example.org:8443 and example.org:9443, are
	 * separate installs and never share evidence.
	 *
	 * @param string $url_a First URL.
	 * @param string $url_b Second URL.
	 * @return bool
	 */
	private static function is_same_site( $url_a, $url_b ) {
		$host_a = wp_parse_url( $url_a, PHP_URL_HOST );
		$host_b = wp_parse_url( $url_b, PHP_URL_HOST );

		if ( ! $host_a || ! $host_b || ! self::is_same_site_family( $host_a, $host_b ) ) {
			return false;
		}

		return self::get_port( $url_a ) === self::get_port( $url_b )
			&& untrailingslashit( (string) wp_parse_url( $url_a, PHP_URL_PATH ) )
			=== untrailingslashit( (string) wp_parse_url( $url_b, PHP_URL_PATH ) );
	}

	/**
	 * The non-default port of a URL, or 0. Absent, 80 and 443 are all 0, so
	 * an http -> https move stays the same install while :8443 and :9443
	 * are different installs.
	 *
	 * @param string $url URL.
	 * @return int
	 */
	private static function get_port( $url ) {
		$port = (int) wp_parse_url( $url, PHP_URL_PORT );

		return in_array( $port, [ 0, 80, 443 ], true ) ? 0 : $port;
	}

	/**
	 * Whether two hosts belong to the same site: equal, or one is a
	 * subdomain of the other. Siblings (foo.github.io, bar.github.io) are
	 * not family, so a shared hosting suffix the heuristic below does not
	 * know cannot join unrelated sites. A bare public suffix (co.uk) is
	 * nobody's site, so it is never family either.
	 *
	 * @param string $host_a First host.
	 * @param string $host_b Second host.
	 * @return bool
	 */
	private static function is_same_site_family( $host_a, $host_b ) {
		if ( false === self::get_registrable_domain( $host_a ) || false === self::get_registrable_domain( $host_b ) ) {
			return false;
		}

		$host_a = preg_replace( '/^www\./', '', strtolower( $host_a ) );
		$host_b = preg_replace( '/^www\./', '', strtolower( $host_b ) );

		if ( filter_var( $host_a, FILTER_VALIDATE_IP ) || filter_var( $host_b, FILTER_VALIDATE_IP ) ) {
			// An IP has no subdomains: 192.168.1.10 is not under 168.1.10.
			return $host_a === $host_b;
		}

		return $host_a === $host_b
			|| substr( $host_a, -strlen( '.' . $host_b ) ) === '.' . $host_b
			|| substr( $host_b, -strlen( '.' . $host_a ) ) === '.' . $host_a;
	}

	/**
	 * The registrable domain of a host (example.co.uk for admin.example.co.uk),
	 * or false for a bare public suffix. Caveat: heuristic, not the public
	 * suffix list — a short generic second-level label under a two-letter
	 * country code is treated as part of the suffix; swap in a PSL lookup if
	 * a real host slips through.
	 *
	 * @param string $host Host name.
	 * @return string|false
	 */
	private static function get_registrable_domain( $host ) {
		// www is never a different site, so www.localhost is localhost.
		$host   = preg_replace( '/^www\./', '', strtolower( $host ) );
		$labels = explode( '.', $host );
		$count  = count( $labels );
		$suffix = 1;

		if ( 1 === $count || filter_var( $host, FILTER_VALIDATE_IP ) ) {
			// localhost, intranet names, raw IPs: no parents, the host is the site.
			return $host;
		}

		if ( 2 === strlen( $labels[ $count - 1 ] )
			&& in_array( $labels[ $count - 2 ], [ 'co', 'com', 'org', 'net', 'gov', 'edu', 'ac', 'or', 'ne', 'mil', 'nom', 'gob', 'sch', 'ltd', 'plc' ], true ) ) {
			$suffix = 2;
		}

		if ( $count <= $suffix ) {
			return false;
		}

		return implode( '.', array_slice( $labels, -( $suffix + 1 ) ) );
	}

	/**
	 * Run the SDK's license check against one specific URL. The SDK builds
	 * the check request from home_url(), so the URL is swapped in via the
	 * home_url filter for the duration of this one call. The 'check' action
	 * has no side effects in the SDK.
	 *
	 * @param string $key License key.
	 * @param string $url URL variant to check.
	 * @return 'valid'|'refused'|'unreachable'
	 */
	private static function query_store( $key, $url ) {
		$override = function () use ( $url ) {
			return $url;
		};

		add_filter( 'home_url', $override, 9999 );
		$response = apply_filters( 'themeisle_sdk_license_process_wpfs', $key, 'check' );
		remove_filter( 'home_url', $override, 9999 );

		if ( is_wp_error( $response ) ) {
			// Transport failure or malformed store response.
			return 'unreachable';
		}

		if ( ! is_object( $response ) || ! isset( $response->license ) ) {
			// The SDK licenser is not loaded, or the SDK passed through a JSON
			// error body (it never checks the HTTP status): nothing definitive
			// was answered.
			return 'unreachable';
		}

		return 'valid' === $response->license ? 'valid' : 'refused';
	}

	/**
	 * Warn admins when the commission is being waived on a re-checked /
	 * last-known-valid basis, so the broken license activation gets fixed
	 * instead of lingering.
	 *
	 * @return void
	 */
	public static function maybe_render_fee_fallback_notice() {
		// Capability first: non-admins must never trigger a store re-check.
		if ( ! current_user_can( 'manage_options' ) || self::is_active() || ! self::has_valid_key_for_fees() ) {
			return;
		}
		echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__( 'WP Full Pay', 'wp-full-stripe-free' ) . ':</strong> '
			. sprintf(
				/* translators: 1: opening anchor tag to the license settings page, 2: closing anchor tag */
				esc_html__( 'your license could not be re-validated for this site URL. Payments keep working, but please %1$sre-activate your license%2$s (or contact support), otherwise the platform commission may be applied to future transactions.', 'wp-full-stripe-free' ),
				'<a href="' . esc_url( self::get_activation_url() ) . '">',
				'</a>'
			)
			. '</p></div>';
	}

	/**
	 * Check if license is expired.
	 *
	 * @return bool
	 */
	public static function is_expired() {
		$status = self::get_data();

		if ( ! $status ) {
			return false;
		}

		if ( ! isset( $status->license ) ) {
			return false;
		}

		if ( 'active_expired' !== $status->license ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the license expiration date.
	 *
	 * @param string $format format of the date.
	 * @return false|string
	 */
	public static function get_expiration_date( $format = 'F Y' ) {
		$data = self::get_data();

		if ( isset( $data->expires ) ) {
			$parsed = date_parse( $data->expires );
			$time   = mktime( $parsed['hour'], $parsed['minute'], $parsed['second'], $parsed['month'], $parsed['day'], $parsed['year'] );
			return gmdate( $format, $time );
		}

		return false;
	}

	/**
	 * Get the licence type.
	 * 1 - personal, 2 - business, 3 - agency.
	 *
	 * @return int
	 */
	public static function get_type() {
		$license = self::get_data();
		if ( false === $license ) {
			return -1;
		}

		if ( ! isset( $license->price_id ) ) {
			return -1;
		}

		if ( isset( $license->license ) && ( 'valid' !== $license->license && 'active_expired' !== $license->license ) ) {
			return -1;
		}

		if ( ! array_key_exists( $license->price_id, self::$plans_map ) ) {
			return -1;
		}

		return self::$plans_map[ $license->price_id ];
	}

	/**
	 * Get the license price ID.
	 *
	 * @return int
	 */
	public static function get_price_id() {
		$license = self::get_data();
		if ( false === $license ) {
			return -1;
		}

		if ( ! isset( $license->price_id ) ) {
			return -1;
		}

		return $license->price_id;
	}

	/**
	 * Get User ID.
	 *
	 * @return int
	 */
	public static function get_user_id() {
		$license = self::get_data();

		// We don't have user_id like WPFS previously used with freemium, so we use payment_id instead.
		if ( false === $license ) {
			return -1;
		}

		if ( ! isset( $license->payment_id ) ) {
			return -1;
		}

		return $license->payment_id;
	}

	/**
	 * Get License Key.
	 *
	 * @return string
	 */
	public static function get_key() {
		$license = self::get_data();

		if ( false === $license ) {
			return '';
		}

		if ( ! isset( $license->key ) ) {
			return '';
		}

		return $license->key;
	}

	/**
	 * Get Activation URL.
	 *
	 * @return string
	 */
	public static function get_activation_url() {
		$admin_url = MM_WPFS_Admin_Menu::getAdminUrlBySlug( MM_WPFS_Admin_Menu::SLUG_SETTINGS_LICENSE );
		return $admin_url;
	}

	/**
	 * Toggle License.
	 *
	 * @param string $key    License key.
	 * @param string $status License status.
	 * @return array
	 */
	public static function toggle( $key, $status ) {
		$namespace = self::get_namespace();
		$response  = apply_filters( 'themeisle_sdk_license_process_wpfs', $key, $status );

		if ( is_wp_error( $response ) ) {
			return [
				'message' => $response->get_error_message(),
				'success' => false,
			];
		}

		if ( 'deactivate' === $status ) {
			// The store freed the activation slot, so the fee-exemption
			// evidence goes too, or one license could be rotated across
			// sites (activate, deactivate, repeat) without ever paying. A
			// failed deactivation leaves the license activated here and
			// must keep the evidence.
			self::forget_fee_evidence();
		} else {
			// The license state changed at the store: a cached verdict for
			// the old state (e.g. a denial before this activation) is stale.
			delete_transient( $namespace . '_license_fee_recheck' );
		}

		return [
			'success' => true,
			'message' => 'activate' === $status ? __( 'Activated.', 'wp-full-stripe-free' ) : __( 'Deactivated', 'wp-full-stripe-free' ),
			'license' => [
				'key'        => apply_filters( 'product_wpfs_license_key', 'free' ),
				'valid'      => apply_filters( 'product_wpfs_license_status', false ),
				'expiration' => self::get_expiration_date(),
			],
		];
	}
}
