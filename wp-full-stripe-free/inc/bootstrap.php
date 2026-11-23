<?php
/**
 * Eager-loaded bootstrap: everything that must run on include rather than
 * on first class use.
 *
 * @package ThemeIsle\WPFullPay
 */

// Resolve legacy class names lazily, so an alias costs nothing until
// something outside the plugin asks for it.
spl_autoload_register(
	static function ( $class_name ) {
		static $aliases = null;

		if ( null === $aliases ) {
			$aliases = array_change_key_case( require __DIR__ . '/compat/aliases.php', CASE_LOWER );
		}

		$key = strtolower( $class_name );
		if ( isset( $aliases[ $key ] ) ) {
			class_alias( $aliases[ $key ], $class_name );
		}
	}
);
