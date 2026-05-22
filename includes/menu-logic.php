<?php
/**
 * Pure decision logic for the WPAdmin Button menu.
 *
 * These functions take primitives and never call WordPress, so they can be
 * unit-tested in isolation. WordPress glue lives in wpadmin-button.php.
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPADMIN_BUTTON_TESTING' ) ) {
	exit;
}

/**
 * Resolves whether the floating button should display for a user whose role is
 * already known to be eligible.
 *
 * @param string $mode           Visibility mode: 'auto' | 'always' | 'never'.
 * @param bool   $toolbar_hidden Whether the frontend toolbar is hidden for the user.
 * @return bool
 */
function wpadmin_button_resolve_visibility( $mode, $toolbar_hidden ) {
	switch ( $mode ) {
		case 'never':
			return false;
		case 'always':
			return true;
		case 'auto':
		default:
			return (bool) $toolbar_hidden;
	}
}
