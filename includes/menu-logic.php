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

/**
 * Filters and orders the menu items to render for a user.
 *
 * The capability check is injected so this stays pure: the caller decides what
 * each key means (including the per-object check and editable-page check for
 * the contextual 'edit_current' item).
 *
 * @param string[] $ordered_keys Admin-enabled item keys, in display order.
 * @param string[] $hidden_keys  Item keys the user has hidden for themselves.
 * @param callable $can_use      fn(string $key): bool — true if the item should show.
 * @return string[] Ordered list of item keys to render.
 */
function wpadmin_button_filter_menu_items( array $ordered_keys, array $hidden_keys, callable $can_use ) {
	$result = array();

	foreach ( $ordered_keys as $key ) {
		if ( in_array( $key, $hidden_keys, true ) ) {
			continue;
		}

		if ( ! $can_use( $key ) ) {
			continue;
		}

		$result[] = $key;
	}

	return $result;
}
