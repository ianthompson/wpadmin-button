# Expandable Admin Shortcut Menu Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the single-destination floating button into a button that expands into a vertical, capability-aware menu of admin shortcuts, configurable globally by an admin and trimmable per-user, with its own visibility control independent of the WordPress toolbar.

**Architecture:** Server-rendered, matching the existing plugin. PHP renders the menu in the footer (filtered to the current user); a small vanilla-JS file handles open/close, keyboard, and tap-outside; CSS handles the expand animation. The bug-prone decision logic (visibility mode, item filtering, upgrade migration) is extracted into pure PHP functions in `includes/menu-logic.php` and unit-tested with plain PHPUnit (no WordPress, no DB). WordPress glue and UI are verified with a manual QA checklist.

**Tech Stack:** PHP 7.4+ (runtime), PHP 8.5 (dev), PHPUnit (dev only, via Composer), vanilla JS, plain CSS, WordPress Settings API + user meta, jQuery UI Sortable (bundled with WordPress).

**Spec:** `docs/superpowers/specs/2026-05-22-expandable-admin-shortcut-menu-design.md`

---

## File Structure

- **Create** `includes/menu-logic.php` — pure decision functions (no WP calls): visibility resolution, menu-item filtering, upgrade seeding. Unit-testable in isolation.
- **Create** `tests/bootstrap.php` — defines `WPADMIN_BUTTON_TESTING` and loads `includes/menu-logic.php`.
- **Create** `tests/MenuLogicTest.php` — PHPUnit tests for the pure functions.
- **Create** `composer.json`, `phpunit.xml.dist` — dev-only test harness (not shipped in the release zip).
- **Modify** `wpadmin-button.php` — load the logic file; add the `edit_current` shortcut and a menu-items catalog; change settings to store an ordered `menu_items` list; add per-user visibility + hidden-items meta; rewrite display logic; render the menu; rebuild the Tools page (admin-only, sortable); add Profile-page fields; remove the old toolbar toggle + handler; run migration.
- **Modify** `assets/wpadmin-button.js` — open/close, hover (desktop), tap toggle, tap-outside, keyboard, `aria-expanded`, reduced-motion awareness.
- **Modify** `assets/wpadmin-button.css` — pill styling, vertical stack, expand/stagger animation, reduced-motion, capped-height scroll, left/right mirroring.
- **Modify** `.gitignore` — ignore `vendor/`.
- **Modify** `readme.txt`, plugin header — version bump + changelog at the end.

---

## Task 1: Test harness + visibility resolver (pure logic, TDD)

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `tests/bootstrap.php`
- Create: `includes/menu-logic.php`
- Create: `tests/MenuLogicTest.php`
- Modify: `.gitignore`

- [ ] **Step 1: Create the Composer dev harness**

Create `composer.json`:

```json
{
    "name": "ianthompson/wpadmin-button",
    "description": "Floating admin shortcut button for WordPress.",
    "license": "GPL-2.0-or-later",
    "type": "wordpress-plugin",
    "require": {
        "php": ">=7.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.6"
    }
}
```

> Note: do **not** add `menu-logic.php` to Composer autoload. The test bootstrap defines `WPADMIN_BUTTON_TESTING` and then requires the file; autoloading it earlier would trip its `exit` guard before the constant exists.

- [ ] **Step 2: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.6/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true">
    <testsuites>
        <testsuite name="unit">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Create `tests/bootstrap.php`**

```php
<?php
// Lets includes/menu-logic.php load outside WordPress for unit tests.
define( 'WPADMIN_BUTTON_TESTING', true );
require_once __DIR__ . '/../includes/menu-logic.php';
```

- [ ] **Step 4: Add `vendor/` to `.gitignore`**

Append to `.gitignore`:

```
vendor/
```

- [ ] **Step 5: Write the failing test for the visibility resolver**

Create `tests/MenuLogicTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class MenuLogicTest extends TestCase {

    public function test_visibility_never_is_always_hidden() {
        $this->assertFalse( wpadmin_button_resolve_visibility( 'never', true ) );
        $this->assertFalse( wpadmin_button_resolve_visibility( 'never', false ) );
    }

    public function test_visibility_always_is_always_shown() {
        $this->assertTrue( wpadmin_button_resolve_visibility( 'always', true ) );
        $this->assertTrue( wpadmin_button_resolve_visibility( 'always', false ) );
    }

    public function test_visibility_auto_follows_toolbar_hidden_state() {
        $this->assertTrue( wpadmin_button_resolve_visibility( 'auto', true ) );
        $this->assertFalse( wpadmin_button_resolve_visibility( 'auto', false ) );
    }

    public function test_visibility_unknown_mode_defaults_to_auto() {
        $this->assertTrue( wpadmin_button_resolve_visibility( 'bogus', true ) );
        $this->assertFalse( wpadmin_button_resolve_visibility( '', false ) );
    }
}
```

- [ ] **Step 6: Create `includes/menu-logic.php` with the guard and the resolver**

```php
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
```

- [ ] **Step 7: Install dependencies and run the test**

Run: `composer install && ./vendor/bin/phpunit --filter test_visibility`
Expected: 4 tests pass. (If `composer` is unavailable, install it first; this is environment setup.)

- [ ] **Step 8: Commit**

```bash
git add composer.json phpunit.xml.dist tests/bootstrap.php tests/MenuLogicTest.php includes/menu-logic.php .gitignore
git commit -m "test: add PHPUnit harness and visibility resolver (IAN-40)"
```

---

## Task 2: Menu-item filtering (pure logic, TDD)

**Files:**
- Modify: `includes/menu-logic.php`
- Test: `tests/MenuLogicTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/MenuLogicTest.php` (inside the class):

```php
    public function test_filter_keeps_admin_order_and_drops_hidden_and_uncapable() {
        $ordered = array( 'edit_current', 'dashboard', 'posts', 'plugins' );
        $hidden  = array( 'posts' );
        $can_use = function ( $key ) {
            // Simulate: user lacks access to 'plugins'; 'edit_current' not on an editable page.
            $blocked = array( 'plugins', 'edit_current' );
            return ! in_array( $key, $blocked, true );
        };

        $result = wpadmin_button_filter_menu_items( $ordered, $hidden, $can_use );

        $this->assertSame( array( 'dashboard' ), $result );
    }

    public function test_filter_preserves_order_when_nothing_removed() {
        $ordered = array( 'dashboard', 'posts', 'media' );
        $can_use = function () {
            return true;
        };

        $result = wpadmin_button_filter_menu_items( $ordered, array(), $can_use );

        $this->assertSame( array( 'dashboard', 'posts', 'media' ), $result );
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/phpunit --filter test_filter`
Expected: FAIL — "Call to undefined function wpadmin_button_filter_menu_items()".

- [ ] **Step 3: Implement the filter in `includes/menu-logic.php`**

Append:

```php
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
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `./vendor/bin/phpunit --filter test_filter`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/menu-logic.php tests/MenuLogicTest.php
git commit -m "feat: add capability/hidden menu-item filter (IAN-40)"
```

---

## Task 3: Upgrade seeding (pure logic, TDD)

**Files:**
- Modify: `includes/menu-logic.php`
- Test: `tests/MenuLogicTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/MenuLogicTest.php`:

```php
    private function valid_keys() {
        return array( 'edit_current', 'dashboard', 'posts', 'media', 'pages', 'comments', 'appearance', 'plugins', 'users', 'profile', 'tools', 'settings' );
    }

    public function test_seed_includes_edit_current_and_dashboard_plus_previous() {
        $result = wpadmin_button_seed_menu_items( 'posts', $this->valid_keys() );
        $this->assertSame( array( 'edit_current', 'dashboard', 'posts' ), $result );
    }

    public function test_seed_dedupes_when_previous_already_seeded() {
        $result = wpadmin_button_seed_menu_items( 'dashboard', $this->valid_keys() );
        $this->assertSame( array( 'edit_current', 'dashboard' ), $result );
    }

    public function test_seed_ignores_unknown_previous_destination() {
        $result = wpadmin_button_seed_menu_items( 'nonsense', $this->valid_keys() );
        $this->assertSame( array( 'edit_current', 'dashboard' ), $result );
    }

    public function test_seed_handles_empty_previous() {
        $result = wpadmin_button_seed_menu_items( '', $this->valid_keys() );
        $this->assertSame( array( 'edit_current', 'dashboard' ), $result );
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/phpunit --filter test_seed`
Expected: FAIL — "Call to undefined function wpadmin_button_seed_menu_items()".

- [ ] **Step 3: Implement the seeder in `includes/menu-logic.php`**

Append:

```php
/**
 * Builds the default menu_items list when a site upgrades from the old
 * single-destination model (or has no menu configured yet).
 *
 * @param string|null $previous_destination Old 'destination' setting value, if any.
 * @param string[]    $valid_keys           Keys present in the menu-items catalog.
 * @return string[] Ordered, de-duplicated, validated seed list.
 */
function wpadmin_button_seed_menu_items( $previous_destination, array $valid_keys ) {
	$seed = array( 'edit_current', 'dashboard' );

	if ( is_string( $previous_destination ) && '' !== $previous_destination ) {
		$seed[] = $previous_destination;
	}

	$out = array();

	foreach ( $seed as $key ) {
		if ( in_array( $key, $valid_keys, true ) && ! in_array( $key, $out, true ) ) {
			$out[] = $key;
		}
	}

	return $out;
}
```

- [ ] **Step 4: Run the full suite**

Run: `./vendor/bin/phpunit`
Expected: PASS — all tests green (visibility + filter + seed).

- [ ] **Step 5: Commit**

```bash
git add includes/menu-logic.php tests/MenuLogicTest.php
git commit -m "feat: add upgrade seeding for menu items (IAN-40)"
```

---

## Task 4: Menu-items catalog + contextual "Edit current page" helpers

**Files:**
- Modify: `wpadmin-button.php` (load logic file; add catalog + edit-current helpers)

This task adds WordPress-dependent helpers; verification is manual (they need a WP runtime).

- [ ] **Step 1: Load the pure-logic file**

In `wpadmin-button.php`, immediately after the `define()` block (after line defining `WPADMIN_BUTTON_GITHUB_RELEASES_URL`), add:

```php
require_once __DIR__ . '/includes/menu-logic.php';
```

- [ ] **Step 2: Add the menu-items catalog helper**

Add this function near `wpadmin_button_get_destinations()`:

```php
/**
 * Returns the full menu-items catalog: the contextual edit item plus the
 * static destinations. Used by settings UI and rendering.
 *
 * @return array<string, array{label: string, contextual: bool}>
 */
function wpadmin_button_get_menu_catalog() {
	$catalog = array(
		'edit_current' => array(
			'label'      => __( 'Edit current page', 'wpadmin-button' ),
			'contextual' => true,
		),
	);

	foreach ( wpadmin_button_get_destinations() as $key => $dest ) {
		$catalog[ $key ] = array(
			'label'      => $dest['label'],
			'contextual' => false,
		);
	}

	return $catalog;
}
```

- [ ] **Step 3: Add the "edit current page" link + label helpers**

Add:

```php
/**
 * Returns the edit URL for the current singular view if the user can edit it.
 *
 * @return string Empty string when not on an editable singular view.
 */
function wpadmin_button_edit_current_url() {
	if ( ! is_singular() ) {
		return '';
	}

	$post_id = get_queried_object_id();

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		return '';
	}

	$link = get_edit_post_link( $post_id );

	return $link ? $link : '';
}

/**
 * Returns the content-type-aware label for the current edit item
 * ("Edit Page", "Edit Post", "Edit Product", ...).
 *
 * @return string
 */
function wpadmin_button_edit_current_label() {
	$post_id   = get_queried_object_id();
	$post_type = $post_id ? get_post_type( $post_id ) : '';
	$type_obj  = $post_type ? get_post_type_object( $post_type ) : null;

	if ( $type_obj && isset( $type_obj->labels->edit_item ) && $type_obj->labels->edit_item ) {
		return $type_obj->labels->edit_item;
	}

	return __( 'Edit', 'wpadmin-button' );
}
```

- [ ] **Step 4: Verify the file parses**

Run: `php -l wpadmin-button.php`
Expected: "No syntax errors detected in wpadmin-button.php".

- [ ] **Step 5: Commit**

```bash
git add wpadmin-button.php
git commit -m "feat: add menu catalog and edit-current helpers (IAN-40)"
```

---

## Task 5: Settings schema — store ordered `menu_items` + migration

**Files:**
- Modify: `wpadmin-button.php` (`wpadmin_button_get_settings`, `wpadmin_button_sanitize_settings`, `register_setting` default)

- [ ] **Step 1: Update `wpadmin_button_get_settings()` defaults and run migration**

Replace the body of `wpadmin_button_get_settings()` with:

```php
function wpadmin_button_get_settings() {
	$settings = get_option( WPADMIN_BUTTON_OPTION, array() );

	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	$valid_keys = array_keys( wpadmin_button_get_menu_catalog() );

	// Migrate sites that predate the menu: seed menu_items from old destination.
	if ( ! isset( $settings['menu_items'] ) || ! is_array( $settings['menu_items'] ) ) {
		$previous              = isset( $settings['destination'] ) ? (string) $settings['destination'] : '';
		$settings['menu_items'] = wpadmin_button_seed_menu_items( $previous, $valid_keys );
	}

	$settings = wp_parse_args(
		$settings,
		array(
			'roles'      => array( 'administrator' ),
			'position'   => 'right',
			'menu_items' => wpadmin_button_seed_menu_items( '', $valid_keys ),
		)
	);

	$settings['roles'] = array_values( array_filter( array_map( 'sanitize_key', (array) $settings['roles'] ) ) );

	if ( ! in_array( $settings['position'], array( 'left', 'right' ), true ) ) {
		$settings['position'] = 'right';
	}

	// Keep only known keys, de-duplicated, in stored order.
	$clean_items = array();
	foreach ( (array) $settings['menu_items'] as $key ) {
		$key = sanitize_key( $key );
		if ( in_array( $key, $valid_keys, true ) && ! in_array( $key, $clean_items, true ) ) {
			$clean_items[] = $key;
		}
	}
	$settings['menu_items'] = $clean_items;

	return $settings;
}
```

- [ ] **Step 2: Update `wpadmin_button_sanitize_settings()`**

Replace the destination handling (the block building `$destination`/`$destinations` and the returned `'destination'` key) so the function returns `menu_items` instead. The full replacement function:

```php
function wpadmin_button_sanitize_settings( $input ) {
	$current_settings = wpadmin_button_get_settings();
	$roles            = array();

	if ( wpadmin_button_can_manage_global_settings() && isset( $input['roles'] ) && is_array( $input['roles'] ) ) {
		$editable_roles = get_editable_roles();
		$valid_roles    = array_keys( $editable_roles );

		foreach ( $input['roles'] as $role ) {
			$role = sanitize_key( $role );

			if ( in_array( $role, $valid_roles, true ) ) {
				$roles[] = $role;
			}
		}
	}

	if ( ! wpadmin_button_can_manage_global_settings() ) {
		$roles = $current_settings['roles'];
	}

	$position = wpadmin_button_can_manage_global_settings() && isset( $input['position'] ) ? sanitize_key( $input['position'] ) : $current_settings['position'];

	if ( ! in_array( $position, array( 'left', 'right' ), true ) ) {
		$position = 'right';
	}

	// Menu items: an ordered, hidden-field-encoded list "key1,key2,..." from the
	// sortable UI, plus per-row checkboxes for which are enabled.
	if ( wpadmin_button_can_manage_global_settings() ) {
		$valid_keys = array_keys( wpadmin_button_get_menu_catalog() );
		$order      = isset( $input['menu_order'] ) ? explode( ',', (string) $input['menu_order'] ) : array();
		$enabled    = isset( $input['menu_items'] ) && is_array( $input['menu_items'] ) ? array_map( 'sanitize_key', $input['menu_items'] ) : array();

		$menu_items = array();
		foreach ( $order as $key ) {
			$key = sanitize_key( $key );
			if ( in_array( $key, $valid_keys, true ) && in_array( $key, $enabled, true ) && ! in_array( $key, $menu_items, true ) ) {
				$menu_items[] = $key;
			}
		}
	} else {
		$menu_items = $current_settings['menu_items'];
	}

	return array(
		'roles'      => array_values( array_unique( $roles ) ),
		'position'   => $position,
		'menu_items' => $menu_items,
	);
}
```

- [ ] **Step 3: Update the `register_setting()` default**

In `wpadmin_button_register_settings()`, change the `'default'` array to:

```php
				'default'           => array(
					'roles'      => array( 'administrator' ),
					'position'   => 'right',
					'menu_items' => array( 'edit_current', 'dashboard' ),
				),
```

- [ ] **Step 4: Verify syntax**

Run: `php -l wpadmin-button.php`
Expected: "No syntax errors detected".

- [ ] **Step 5: Run unit tests (ensure logic functions still pass)**

Run: `./vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add wpadmin-button.php
git commit -m "feat: store ordered menu_items with upgrade migration (IAN-40)"
```

---

## Task 6: Display logic + per-user meta

**Files:**
- Modify: `wpadmin-button.php` (`wpadmin_button_should_display`; add meta accessors; remove `wpadmin_button_get_destination_url`)

- [ ] **Step 1: Add per-user meta accessors**

Add near the top of the runtime section:

```php
/**
 * Returns the user's button visibility mode: 'auto' | 'always' | 'never'.
 *
 * @param int $user_id User ID.
 * @return string
 */
function wpadmin_button_get_visibility_mode( $user_id ) {
	$mode = get_user_meta( $user_id, 'wpadmin_button_visibility', true );

	if ( ! in_array( $mode, array( 'auto', 'always', 'never' ), true ) ) {
		$mode = 'auto';
	}

	return $mode;
}

/**
 * Returns the list of menu-item keys the user has hidden for themselves.
 *
 * @param int $user_id User ID.
 * @return string[]
 */
function wpadmin_button_get_hidden_items( $user_id ) {
	$hidden = get_user_meta( $user_id, 'wpadmin_button_hidden_items', true );

	if ( ! is_array( $hidden ) ) {
		return array();
	}

	return array_values( array_filter( array_map( 'sanitize_key', $hidden ) ) );
}
```

- [ ] **Step 2: Rewrite `wpadmin_button_should_display()`**

Replace the toolbar-coupled check with the role check + the resolver:

```php
function wpadmin_button_should_display() {
	if ( is_admin() || ! is_user_logged_in() ) {
		return false;
	}

	$user     = wp_get_current_user();
	$settings = wpadmin_button_get_settings();

	if ( empty( $settings['roles'] ) || ! array_intersect( (array) $user->roles, $settings['roles'] ) ) {
		return false;
	}

	$user_id        = get_current_user_id();
	$mode           = wpadmin_button_get_visibility_mode( $user_id );
	$toolbar_hidden = ( 'false' === get_user_option( 'show_admin_bar_front', $user_id ) );

	return wpadmin_button_resolve_visibility( $mode, $toolbar_hidden );
}
```

> Note: leave `wpadmin_button_get_destination_url()` in place for now — the old render function still calls it. It is deleted in Task 8 once the render function is replaced.

- [ ] **Step 3: Verify syntax + tests**

Run: `php -l wpadmin-button.php && ./vendor/bin/phpunit`
Expected: no syntax errors; tests PASS.

- [ ] **Step 4: Commit**

```bash
git add wpadmin-button.php
git commit -m "feat: visibility modes via per-user meta, decoupled from toolbar (IAN-40)"
```

---

## Task 7: Build the menu-items list for the current user

**Files:**
- Modify: `wpadmin-button.php` (add `wpadmin_button_get_render_items()`)

- [ ] **Step 1: Add the render-list builder**

```php
/**
 * Builds the ordered list of menu items to render for the current user, each
 * resolved to its URL and label.
 *
 * @return array<int, array{key: string, url: string, label: string}>
 */
function wpadmin_button_get_render_items() {
	$settings     = wpadmin_button_get_settings();
	$user_id      = get_current_user_id();
	$hidden       = wpadmin_button_get_hidden_items( $user_id );
	$destinations = wpadmin_button_get_destinations();
	$edit_url     = wpadmin_button_edit_current_url();

	$can_use = function ( $key ) use ( $destinations, $edit_url ) {
		if ( 'edit_current' === $key ) {
			return '' !== $edit_url;
		}

		if ( ! isset( $destinations[ $key ] ) ) {
			return false;
		}

		return current_user_can( $destinations[ $key ]['capability'] );
	};

	$keys  = wpadmin_button_filter_menu_items( $settings['menu_items'], $hidden, $can_use );
	$items = array();

	foreach ( $keys as $key ) {
		if ( 'edit_current' === $key ) {
			$items[] = array(
				'key'   => 'edit_current',
				'url'   => $edit_url,
				'label' => wpadmin_button_edit_current_label(),
			);
			continue;
		}

		$items[] = array(
			'key'   => $key,
			'url'   => admin_url( $destinations[ $key ]['path'] ),
			'label' => $destinations[ $key ]['label'],
		);
	}

	return $items;
}
```

- [ ] **Step 2: Verify syntax**

Run: `php -l wpadmin-button.php`
Expected: "No syntax errors detected".

- [ ] **Step 3: Commit**

```bash
git add wpadmin-button.php
git commit -m "feat: build per-user resolved menu render list (IAN-40)"
```

---

## Task 8: Render the menu markup

**Files:**
- Modify: `wpadmin-button.php` (`wpadmin_button_render_frontend_button`)

- [ ] **Step 0: Delete the now-unused single-destination URL helper**

Delete `wpadmin_button_get_destination_url()` entirely — the new render function below builds per-item URLs instead.

- [ ] **Step 1: Replace the render function**

Replace `wpadmin_button_render_frontend_button()` so it renders the toggle button plus the pill list:

```php
function wpadmin_button_render_frontend_button() {
	if ( ! wpadmin_button_should_display() ) {
		return;
	}

	$settings = wpadmin_button_get_settings();
	$items    = wpadmin_button_get_render_items();
	$classes  = array( 'wpadmin-button' );

	if ( 'left' === $settings['position'] ) {
		$classes[] = 'wpadmin-button--left';
	}

	$update_badge = wpadmin_button_get_update_badge_data();
	$badge_label  = $update_badge['title'];

	if ( ! $badge_label && $update_badge['count'] > 0 ) {
		$badge_label = sprintf(
			/* translators: %d: Number of available WordPress updates. */
			_n( '%d WordPress update available', '%d WordPress updates available', $update_badge['count'], 'wpadmin-button' ),
			$update_badge['count']
		);
	}
	?>
	<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-wpadmin-button>
		<?php if ( ! empty( $items ) ) : ?>
			<ul class="wpadmin-button__menu" role="menu" hidden>
				<?php foreach ( $items as $item ) : ?>
					<li class="wpadmin-button__menu-item" role="none">
						<a class="wpadmin-button__pill" role="menuitem" href="<?php echo esc_url( $item['url'] ); ?>">
							<span class="wpadmin-button__pill-icon" aria-hidden="true"></span>
							<span class="wpadmin-button__pill-label"><?php echo esc_html( $item['label'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<button
			type="button"
			class="wpadmin-button__toggle"
			aria-haspopup="true"
			aria-expanded="false"
			aria-label="<?php esc_attr_e( 'Open admin shortcuts', 'wpadmin-button' ); ?>"
		>
			<span class="wpadmin-button__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" focusable="false" role="img">
					<path d="M19.43 12.98c.04-.32.07-.65.07-.98s-.02-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.37-.31-.6-.22l-2.49 1a7.31 7.31 0 0 0-1.69-.98l-.38-2.65A.49.49 0 0 0 14.01 2h-4c-.25 0-.46.18-.5.42l-.38 2.65c-.61.24-1.18.56-1.69.98l-2.49-1c-.23-.08-.48 0-.6.22l-2 3.46c-.13.22-.07.49.12.64l2.11 1.65c-.04.32-.08.65-.08.98s.03.66.08.98l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.12.22.37.31.6.22l2.49-1c.51.4 1.08.73 1.69.98l.38 2.65c.04.24.25.42.5.42h4c.25 0 .46-.18.49-.42l.38-2.65c.61-.24 1.18-.57 1.69-.98l2.49 1c.23.08.48 0 .6-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65ZM12 15.5A3.5 3.5 0 1 1 12 8a3.5 3.5 0 0 1 0 7.5Z" />
				</svg>
			</span>
		</button>

		<?php if ( $update_badge['count'] > 0 ) : ?>
			<a class="wpadmin-button__badge" href="<?php echo esc_url( $update_badge['url'] ); ?>" aria-label="<?php echo esc_attr( $badge_label ); ?>">
				<?php echo esc_html( min( 99, $update_badge['count'] ) ); ?>
			</a>
		<?php endif; ?>
	</div>
	<?php
}
```

- [ ] **Step 2: Verify syntax**

Run: `php -l wpadmin-button.php`
Expected: "No syntax errors detected".

- [ ] **Step 3: Commit**

```bash
git add wpadmin-button.php
git commit -m "feat: render expandable menu markup (IAN-40)"
```

---

## Task 9: Tools page — admin-only, sortable menu config

**Files:**
- Modify: `wpadmin-button.php` (`wpadmin_button_register_admin_page`, `wpadmin_button_render_admin_page`, remove toolbar form + handler)

- [ ] **Step 1: Make the Tools page admin-only**

In `wpadmin_button_register_admin_page()`, change the capability argument from `'read'` to `'manage_options'`:

```php
	add_management_page(
		__( 'WPAdmin Button', 'wpadmin-button' ),
		__( 'WPAdmin Button', 'wpadmin-button' ),
		'manage_options',
		'wpadmin-button',
		'wpadmin_button_render_admin_page'
	);
```

- [ ] **Step 2: Replace `wpadmin_button_render_admin_page()`**

The page now shows only the global settings (no per-user toolbar form), with a sortable, checkbox menu list:

```php
function wpadmin_button_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings        = wpadmin_button_get_settings();
	$roles           = get_editable_roles();
	$catalog         = wpadmin_button_get_menu_catalog();
	$settings_saved  = isset( $_GET['settings-updated'] );

	// Order rows: enabled items first (in saved order), then the rest.
	$ordered_keys = $settings['menu_items'];
	foreach ( array_keys( $catalog ) as $key ) {
		if ( ! in_array( $key, $ordered_keys, true ) ) {
			$ordered_keys[] = $key;
		}
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'WPAdmin Button', 'wpadmin-button' ); ?></h1>

		<?php if ( $settings_saved ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'WPAdmin Button settings saved.', 'wpadmin-button' ); ?></p>
			</div>
		<?php endif; ?>

		<p class="description">
			<?php esc_html_e( 'Each user controls whether the button appears and which of these shortcuts they see from their own profile page.', 'wpadmin-button' ); ?>
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'wpadmin_button_settings' ); ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Menu shortcuts', 'wpadmin-button' ); ?></th>
						<td>
							<input type="hidden" id="wpadmin-button-menu-order" name="<?php echo esc_attr( WPADMIN_BUTTON_OPTION ); ?>[menu_order]" value="<?php echo esc_attr( implode( ',', $ordered_keys ) ); ?>" />
							<ul id="wpadmin-button-menu-list" class="wpadmin-button-sortable">
								<?php foreach ( $ordered_keys as $key ) : ?>
									<li class="wpadmin-button-sortable__row" data-key="<?php echo esc_attr( $key ); ?>">
										<span class="wpadmin-button-sortable__handle dashicons dashicons-menu" aria-hidden="true"></span>
										<label>
											<input
												type="checkbox"
												name="<?php echo esc_attr( WPADMIN_BUTTON_OPTION ); ?>[menu_items][]"
												value="<?php echo esc_attr( $key ); ?>"
												<?php checked( in_array( $key, $settings['menu_items'], true ) ); ?>
											/>
											<?php echo esc_html( $catalog[ $key ]['label'] ); ?>
										</label>
									</li>
								<?php endforeach; ?>
							</ul>
							<p class="description">
								<?php esc_html_e( 'Tick the shortcuts to include and drag to reorder. "Edit current page" only appears when viewing an editable post or page.', 'wpadmin-button' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Display for roles', 'wpadmin-button' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Display for roles', 'wpadmin-button' ); ?></legend>
								<?php foreach ( $roles as $role_key => $role ) : ?>
									<label>
										<input
											type="checkbox"
											name="<?php echo esc_attr( WPADMIN_BUTTON_OPTION ); ?>[roles][]"
											value="<?php echo esc_attr( $role_key ); ?>"
											<?php checked( in_array( $role_key, $settings['roles'], true ) ); ?>
										/>
										<?php echo esc_html( translate_user_role( $role['name'] ) ); ?>
									</label>
									<br />
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wpadmin-button-position"><?php esc_html_e( 'Button position', 'wpadmin-button' ); ?></label>
						</th>
						<td>
							<select id="wpadmin-button-position" name="<?php echo esc_attr( WPADMIN_BUTTON_OPTION ); ?>[position]">
								<option value="right" <?php selected( 'right', $settings['position'] ); ?>><?php esc_html_e( 'Right bottom', 'wpadmin-button' ); ?></option>
								<option value="left" <?php selected( 'left', $settings['position'] ); ?>><?php esc_html_e( 'Left bottom', 'wpadmin-button' ); ?></option>
							</select>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
```

- [ ] **Step 3: Add the admin enqueue for jQuery UI Sortable + a tiny inline script**

Add a new admin enqueue function and hook:

```php
/**
 * Enqueues the sortable script on the plugin's Tools page.
 *
 * @param string $hook Current admin page hook.
 */
function wpadmin_button_enqueue_admin_assets( $hook ) {
	if ( 'tools_page_wpadmin-button' !== $hook ) {
		return;
	}

	wp_enqueue_script( 'jquery-ui-sortable' );

	$script = <<<JS
jQuery(function($){
	var list = $('#wpadmin-button-menu-list');
	if(!list.length){return;}
	function sync(){
		var order = list.find('.wpadmin-button-sortable__row').map(function(){return $(this).data('key');}).get();
		$('#wpadmin-button-menu-order').val(order.join(','));
	}
	list.sortable({handle:'.wpadmin-button-sortable__handle',update:sync});
	sync();
});
JS;

	wp_add_inline_script( 'jquery-ui-sortable', $script );

	wp_add_inline_style( 'common', '.wpadmin-button-sortable{margin:0;max-width:360px}.wpadmin-button-sortable__row{padding:8px 10px;border:1px solid #dcdcde;background:#fff;margin-bottom:-1px;display:flex;align-items:center;gap:8px}.wpadmin-button-sortable__handle{cursor:move;color:#787c82}' );
}
add_action( 'admin_enqueue_scripts', 'wpadmin_button_enqueue_admin_assets' );
```

- [ ] **Step 4: Remove the old per-user toolbar form and its handler**

Delete `wpadmin_button_update_toolbar_preference()` and its `add_action( 'admin_post_wpadmin_button_update_toolbar', ... )` line. (The toolbar form markup was already removed by replacing the render function in Step 2.)

- [ ] **Step 5: Verify syntax + tests**

Run: `php -l wpadmin-button.php && ./vendor/bin/phpunit`
Expected: no syntax errors; tests PASS.

- [ ] **Step 6: Commit**

```bash
git add wpadmin-button.php
git commit -m "feat: admin-only Tools page with sortable menu config (IAN-40)"
```

---

## Task 10: Profile-page fields — visibility + per-user hide

**Files:**
- Modify: `wpadmin-button.php` (add profile render + save hooks)

- [ ] **Step 1: Add the profile fields renderer**

```php
/**
 * Renders the WPAdmin Button fields on a user's profile screen.
 *
 * @param WP_User $user The user being edited.
 */
function wpadmin_button_render_profile_fields( $user ) {
	$settings = wpadmin_button_get_settings();
	$mode     = wpadmin_button_get_visibility_mode( $user->ID );
	$hidden   = wpadmin_button_get_hidden_items( $user->ID );
	$catalog  = wpadmin_button_get_menu_catalog();
	?>
	<h2><?php esc_html_e( 'WPAdmin Button', 'wpadmin-button' ); ?></h2>
	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Show the floating button', 'wpadmin-button' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Show the floating button', 'wpadmin-button' ); ?></legend>
						<label><input type="radio" name="wpadmin_button_visibility" value="auto" <?php checked( 'auto', $mode ); ?> /> <?php esc_html_e( 'Automatically — only when the toolbar above is hidden', 'wpadmin-button' ); ?></label><br />
						<label><input type="radio" name="wpadmin_button_visibility" value="always" <?php checked( 'always', $mode ); ?> /> <?php esc_html_e( 'Always', 'wpadmin-button' ); ?></label><br />
						<label><input type="radio" name="wpadmin_button_visibility" value="never" <?php checked( 'never', $mode ); ?> /> <?php esc_html_e( 'Never', 'wpadmin-button' ); ?></label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Your menu shortcuts', 'wpadmin-button' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Your menu shortcuts', 'wpadmin-button' ); ?></legend>
						<?php
						$shown_any = false;
						foreach ( $settings['menu_items'] as $key ) {
							if ( ! isset( $catalog[ $key ] ) ) {
								continue;
							}
							$shown_any = true;
							?>
							<label>
								<input
									type="checkbox"
									name="wpadmin_button_shown_items[]"
									value="<?php echo esc_attr( $key ); ?>"
									<?php checked( ! in_array( $key, $hidden, true ) ); ?>
								/>
								<?php echo esc_html( $catalog[ $key ]['label'] ); ?>
							</label><br />
							<?php
						}
						if ( ! $shown_any ) {
							echo '<p class="description">' . esc_html__( 'No shortcuts have been enabled by an administrator yet.', 'wpadmin-button' ) . '</p>';
						}
						?>
						<p class="description"><?php esc_html_e( 'Untick any shortcuts you do not want in your menu. You can only choose from shortcuts an administrator has enabled.', 'wpadmin-button' ); ?></p>
					</fieldset>
				</td>
			</tr>
		</tbody>
	</table>
	<?php
}
add_action( 'show_user_profile', 'wpadmin_button_render_profile_fields' );
add_action( 'edit_user_profile', 'wpadmin_button_render_profile_fields' );
```

- [ ] **Step 2: Add the profile save handler**

The "shown items" checkboxes are inverted into a stored "hidden items" list, so unticking hides. Only items the admin currently enables are considered.

```php
/**
 * Saves the WPAdmin Button profile fields.
 *
 * @param int $user_id The user being saved.
 */
function wpadmin_button_save_profile_fields( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	if ( isset( $_POST['wpadmin_button_visibility'] ) ) {
		$mode = sanitize_key( wp_unslash( $_POST['wpadmin_button_visibility'] ) );
		if ( ! in_array( $mode, array( 'auto', 'always', 'never' ), true ) ) {
			$mode = 'auto';
		}
		update_user_meta( $user_id, 'wpadmin_button_visibility', $mode );
	}

	$settings = wpadmin_button_get_settings();
	$shown    = isset( $_POST['wpadmin_button_shown_items'] ) && is_array( $_POST['wpadmin_button_shown_items'] )
		? array_map( 'sanitize_key', wp_unslash( $_POST['wpadmin_button_shown_items'] ) )
		: array();

	// Hidden = enabled items the user did NOT keep ticked.
	$hidden = array();
	foreach ( $settings['menu_items'] as $key ) {
		if ( ! in_array( $key, $shown, true ) ) {
			$hidden[] = $key;
		}
	}

	update_user_meta( $user_id, 'wpadmin_button_hidden_items', $hidden );
}
add_action( 'personal_options_update', 'wpadmin_button_save_profile_fields' );
add_action( 'edit_user_profile_update', 'wpadmin_button_save_profile_fields' );
```

- [ ] **Step 3: Verify syntax + tests**

Run: `php -l wpadmin-button.php && ./vendor/bin/phpunit`
Expected: no syntax errors; tests PASS.

- [ ] **Step 4: Commit**

```bash
git add wpadmin-button.php
git commit -m "feat: profile-page visibility and per-user shortcut fields (IAN-40)"
```

---

## Task 11: CSS — pills, stack, animation, reduced motion

**Files:**
- Modify: `assets/wpadmin-button.css`

- [ ] **Step 1: Rename the link styles to the toggle and add menu styles**

Replace the `.wpadmin-button__link` selectors with `.wpadmin-button__toggle` (same circular button styling, plus `border: 0; cursor: pointer; padding: 0;` since it's now a `<button>`), and append the menu/pill styles:

```css
.wpadmin-button__toggle {
	align-items: center;
	background: var(--wpadmin-button-bg, #2271b1);
	border: 0;
	border-radius: 999px;
	box-shadow: 0 10px 24px var(--wpadmin-button-shadow, rgba(0, 0, 0, 0.22));
	color: var(--wpadmin-button-fg, #fff);
	cursor: pointer;
	display: inline-flex;
	height: 44px;
	justify-content: center;
	padding: 0;
	transition: background-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
	width: 44px;
}

.wpadmin-button__toggle:hover,
.wpadmin-button__toggle:focus {
	background: var(--wpadmin-button-hover, #135e96);
	box-shadow: 0 12px 28px var(--wpadmin-button-shadow, rgba(0, 0, 0, 0.22));
	color: var(--wpadmin-button-fg, #fff);
	transform: translateY(-1px);
}

.wpadmin-button__toggle:focus,
.wpadmin-button__badge:focus,
.wpadmin-button__pill:focus {
	outline: 2px solid var(--wpadmin-button-fg, #fff);
	outline-offset: 3px;
}

/* Open state: rotate the icon toward an "x" feel. */
.wpadmin-button[data-open="true"] .wpadmin-button__icon {
	transform: rotate(135deg);
}

.wpadmin-button__icon {
	transition: transform 200ms ease;
}

/* Vertical menu, stacked above the toggle. */
.wpadmin-button__menu {
	bottom: 56px;
	display: flex;
	flex-direction: column;
	gap: 10px;
	list-style: none;
	margin: 0;
	max-height: calc(100vh - 96px);
	overflow-y: auto;
	padding: 0;
	position: absolute;
	right: 0;
}

.wpadmin-button--left .wpadmin-button__menu {
	left: 0;
	right: auto;
	align-items: flex-start;
}

.wpadmin-button__menu:not([hidden]) {
	display: flex;
}

.wpadmin-button__menu-item {
	display: flex;
	justify-content: flex-end;
}

.wpadmin-button--left .wpadmin-button__menu-item {
	justify-content: flex-start;
}

.wpadmin-button__pill {
	align-items: center;
	background: var(--wpadmin-button-bg, #2271b1);
	border-radius: 999px;
	box-shadow: 0 6px 16px var(--wpadmin-button-shadow, rgba(0, 0, 0, 0.22));
	color: var(--wpadmin-button-fg, #fff);
	display: inline-flex;
	gap: 8px;
	min-height: 40px;
	padding: 0 16px;
	text-decoration: none;
	white-space: nowrap;
}

.wpadmin-button__pill:hover,
.wpadmin-button__pill:focus {
	background: var(--wpadmin-button-hover, #135e96);
	color: var(--wpadmin-button-fg, #fff);
}

.wpadmin-button__pill-label {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
	font-size: 14px;
	font-weight: 600;
	line-height: 1;
}
```

- [ ] **Step 2: Add the staggered open animation + reduced-motion fallback**

Append:

```css
/* Entrance animation for each pill when the menu opens. */
.wpadmin-button[data-open="true"] .wpadmin-button__menu-item {
	animation: wpadmin-button-pop 200ms ease both;
}

.wpadmin-button[data-open="true"] .wpadmin-button__menu-item:nth-child(1) { animation-delay: 0ms; }
.wpadmin-button[data-open="true"] .wpadmin-button__menu-item:nth-child(2) { animation-delay: 30ms; }
.wpadmin-button[data-open="true"] .wpadmin-button__menu-item:nth-child(3) { animation-delay: 60ms; }
.wpadmin-button[data-open="true"] .wpadmin-button__menu-item:nth-child(4) { animation-delay: 90ms; }
.wpadmin-button[data-open="true"] .wpadmin-button__menu-item:nth-child(n+5) { animation-delay: 120ms; }

@keyframes wpadmin-button-pop {
	from { opacity: 0; transform: translateY(8px); }
	to   { opacity: 1; transform: translateY(0); }
}

@media (prefers-reduced-motion: reduce) {
	.wpadmin-button__toggle,
	.wpadmin-button__icon,
	.wpadmin-button[data-open="true"] .wpadmin-button__menu-item {
		animation: none;
		transition: none;
	}
}
```

- [ ] **Step 3: Visually confirm in a browser (manual)**

Load a frontend page with the button visible (see Task 13 QA setup). Confirm: toggle is a circle; opening shows a vertical stack of labelled pills above it; pills match the admin colour scheme; icon rotates on open; with OS "reduce motion" on, pills appear without sliding.

- [ ] **Step 4: Commit**

```bash
git add assets/wpadmin-button.css
git commit -m "feat: menu pill styling and open animation (IAN-40)"
```

---

## Task 12: JS — open/close, keyboard, tap-outside

**Files:**
- Modify: `assets/wpadmin-button.js`

- [ ] **Step 1: Add the menu interaction module**

Inside the existing IIFE in `assets/wpadmin-button.js`, after the existing viewport code, add:

```javascript
	var container = document.querySelector( '[data-wpadmin-button]' );
	var toggle = container ? container.querySelector( '.wpadmin-button__toggle' ) : null;
	var menu = container ? container.querySelector( '.wpadmin-button__menu' ) : null;

	if ( container && toggle && menu ) {
		var items = function () {
			return Array.prototype.slice.call( menu.querySelectorAll( '.wpadmin-button__pill' ) );
		};

		var open = function () {
			menu.hidden = false;
			container.setAttribute( 'data-open', 'true' );
			toggle.setAttribute( 'aria-expanded', 'true' );
		};

		var close = function ( returnFocus ) {
			menu.hidden = true;
			container.removeAttribute( 'data-open' );
			toggle.setAttribute( 'aria-expanded', 'false' );
			if ( returnFocus ) {
				toggle.focus();
			}
		};

		var isOpen = function () {
			return 'true' === toggle.getAttribute( 'aria-expanded' );
		};

		// Click / tap toggles.
		toggle.addEventListener( 'click', function () {
			if ( isOpen() ) {
				close( false );
			} else {
				open();
			}
		} );

		// Desktop hover: open on enter, close on leave.
		container.addEventListener( 'mouseenter', open );
		container.addEventListener( 'mouseleave', function () {
			close( false );
		} );

		// Keyboard.
		toggle.addEventListener( 'keydown', function ( event ) {
			if ( 'ArrowUp' === event.key || 'ArrowDown' === event.key || 'Enter' === event.key || ' ' === event.key ) {
				event.preventDefault();
				open();
				var list = items();
				if ( list.length ) {
					list[0].focus();
				}
			}
		} );

		menu.addEventListener( 'keydown', function ( event ) {
			var list = items();
			var index = list.indexOf( document.activeElement );

			if ( 'Escape' === event.key ) {
				close( true );
			} else if ( 'ArrowDown' === event.key ) {
				event.preventDefault();
				if ( index < list.length - 1 ) { list[ index + 1 ].focus(); }
			} else if ( 'ArrowUp' === event.key ) {
				event.preventDefault();
				if ( index > 0 ) { list[ index - 1 ].focus(); } else { toggle.focus(); }
			}
		} );

		// Tap / click outside closes.
		document.addEventListener( 'click', function ( event ) {
			if ( isOpen() && ! container.contains( event.target ) ) {
				close( false );
			}
		} );
	}
```

- [ ] **Step 2: Manually verify behaviour**

In a browser with the button visible: hover opens on desktop; click toggles; Enter/Space/Arrow opens and focuses first pill; Up/Down move between pills; Escape closes and returns focus to the toggle; clicking elsewhere closes. On a phone (or device emulation), tap opens, tap a pill navigates, tap outside closes.

- [ ] **Step 3: Commit**

```bash
git add assets/wpadmin-button.js
git commit -m "feat: menu open/close, keyboard, and tap-outside behaviour (IAN-40)"
```

---

## Task 13: Manual QA, version bump, changelog, release zip

**Files:**
- Modify: `wpadmin-button.php` (version header + `WPADMIN_BUTTON_VERSION`)
- Modify: `readme.txt`
- Refresh: `../releases/v1.5.0/wpadmin-button.zip`

- [ ] **Step 1: Run the full QA checklist in a real WordPress install**

Activate the plugin on a test site and verify each spec requirement:

- Admin Tools page is admin-only; non-admins don't see it.
- Tools page: tick/untick shortcuts, drag to reorder, Save — order and selection persist after reload.
- "Display for roles" and "Button position" still work; left/right mirrors the menu side.
- Profile page (own profile): visibility radios (auto/always/never) and "Your menu shortcuts" save and persist.
- An admin editing **another** user's profile can set that user's visibility and shortcuts.
- Visibility: `never` hides; `always` shows even with toolbar on; `auto` shows only when toolbar is off (the WordPress toolbar checkbox still controls the toolbar independently).
- Capability filtering: a non-admin role (e.g. Author) sees only items they can access; "Plugins"/"Users"/"Settings" don't appear for them.
- "Edit current page": appears with the right label ("Edit Page"/"Edit Post"/CPT) only on editable singular views; absent on archives/search/404 and for users who can't edit that item.
- Per-user hide: unticking an item on the profile removes it from that user's menu while the admin order stays intact.
- Migration: on a site that had the old single "destination" set, the menu seeds sensibly (Edit current page + Dashboard + previous destination) and the button's visibility is unchanged for existing users.
- Accessibility: full keyboard flow and screen-reader labels (per Task 12); reduced-motion path (per Task 11).
- Mobile: tap open/close, tap-outside, capped-height scroll with many items.

Fix any failures by returning to the relevant task. Re-run `./vendor/bin/phpunit` after any logic change.

- [ ] **Step 2: Bump the version to 1.5.0**

In `wpadmin-button.php`, change the header `Version:` to `1.5.0` and `define( 'WPADMIN_BUTTON_VERSION', '1.4.3' )` to `'1.5.0'`.

- [ ] **Step 3: Update `readme.txt` changelog**

Add a changelog entry under the changelog section:

```
= 1.5.0 =
* New: the floating button now expands into a menu of admin shortcuts.
* New: admins choose and order the shortcuts; each user can hide ones they don't want, all from their profile.
* New: "Edit current page" shortcut on posts and pages.
* New: choose when the button shows — automatically, always, or never — independent of the WordPress toolbar.
```

- [ ] **Step 4: Refresh the release zip (per AGENTS.md)**

Build a clean zip excluding dev files. Run from the plugin directory:

```bash
mkdir -p ../releases/v1.5.0
zip -r ../releases/v1.5.0/wpadmin-button.zip . \
  -x '.git/*' 'vendor/*' 'tests/*' 'docs/*' \
  -x 'composer.json' 'phpunit.xml.dist' '.gitignore' '*.DS_Store'
```

Expected: `../releases/v1.5.0/wpadmin-button.zip` created containing `wpadmin-button.php`, `includes/`, `assets/`, `readme.txt`, `README.md`.

- [ ] **Step 5: Commit**

```bash
git add wpadmin-button.php readme.txt
git commit -m "release: v1.5.0 expandable admin shortcut menu (IAN-40)"
```

---

## Notes for the implementer

- The release zip is built from the working tree but must **exclude** `vendor/`, `tests/`, `docs/`, and the Composer/PHPUnit config — those are dev-only and should never ship.
- `includes/menu-logic.php` must stay free of WordPress function calls so the unit tests keep running without a WordPress bootstrap. Put anything WordPress-dependent in `wpadmin-button.php`.
- After the branch is ready, open a PR for IAN-40 (none exists yet) per `AGENTS.md`.
