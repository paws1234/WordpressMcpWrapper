<?php
/**
 * Local environment conveniences.
 *
 * WordPress refuses to issue application passwords over plain HTTP unless the
 * environment reports itself as local. wp-env sets WP_ENVIRONMENT_TYPE=local,
 * so this is a safety net for setups where that constant is missing.
 *
 * @package WP_Agent_Bridge
 */

namespace WP_Agent_Bridge;

defined( 'ABSPATH' ) || exit;

add_filter(
	'wp_is_application_passwords_available',
	static function ( $available ) {
		if ( $available ) {
			return $available;
		}

		if ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'local' === WP_ENVIRONMENT_TYPE ) {
			return true;
		}

		return $available;
	}
);

/**
 * Report whether application passwords are usable, for diagnostics.
 */
function application_passwords_available(): bool {
	if ( function_exists( 'wp_is_application_passwords_available' ) ) {
		return (bool) wp_is_application_passwords_available();
	}

	return false;
}
