# Expandable Admin Shortcut Menu — Design

**Linear issue:** [IAN-40 — Design expandable admin shortcut menu](https://linear.app/ianthompson/issue/IAN-40/design-expandable-admin-shortcut-menu)
**Date:** 2026-05-22
**Status:** Approved for planning

## Summary

Evolve the WPAdmin Button from a single floating link into a floating button that
expands into a vertical menu of admin shortcuts. The menu is configured globally by an
administrator (which shortcuts, what order) and trimmed per-user (show/hide). Visibility
of the button itself becomes an independent per-user choice, decoupled from the
WordPress toolbar setting.

Visual direction is taken from two references the owner provided: the Google Drive
"speed dial" FAB (vertical stack of buttons springing up from the main button) for
structure, and an "expand on hover" pill for flavour (icon + label).

## Goals

- Replace the single-destination button with an expandable shortcut menu.
- Let an admin choose which shortcuts appear and in what order (site-wide default).
- Let each user hide/show shortcuts for themselves (no reordering, no adding).
- Respect each user's capabilities — never show a shortcut they cannot use.
- Add a contextual "Edit current page" shortcut.
- Make the button's visibility an independent per-user setting, leaving WordPress's
  own toolbar setting untouched.
- Full keyboard accessibility, screen-reader support, and mobile tap behaviour.

## Non-goals (explicitly out of scope for this version)

- Nested / cascading submenus (noted as a future enhancement in IAN-40).
- Custom or external links as shortcuts (only the predefined set below).
- Per-user reordering or per-user addition of shortcuts.
- Drag-to-reorder for anyone other than the admin.

## Architecture

Server-rendered, matching the plugin's existing approach (PHP renders markup, a small
vanilla-JS file handles interaction, plain CSS handles presentation). No new
dependencies and no build step.

- **PHP** renders the full menu in the footer — the main toggle button plus a list of
  shortcut pills, already filtered to what the current user may see and has chosen to
  keep, in the admin-defined order.
- **JavaScript** (extends the existing `assets/wpadmin-button.js`) handles open/close,
  keyboard interaction, tap-outside-to-close, and reuses the existing viewport/safe-area
  positioning logic.
- **CSS** (extends `assets/wpadmin-button.css`) handles the expand/stagger animation,
  the pill styling, and `prefers-reduced-motion`.

## The shortcut menu

### Interaction

- The floating button stays a round icon button anchored bottom-left or bottom-right
  (existing `position` setting).
- **Desktop:** hovering the button or the open menu opens it; moving away closes it.
- **All devices:** clicking/tapping the button toggles it; it also closes on tap/click
  outside or `Escape`.
- The button's icon changes from a gear to a close (×) state while open, with a small
  rotation transition.

### Layout

- Shortcuts stack **vertically above** the button, nearest-the-button first.
- Each shortcut is a rounded pill showing **icon + always-visible text label**
  (e.g. "Dashboard", "Posts"), aligned to the same side as the button so labels read
  outward.
- On open, pills fade/slide up in a quick staggered sequence; reverse on close.
- If the stack would exceed the available viewport height, it caps and scrolls
  internally so the top items stay reachable.

### Available shortcuts

The contextual item plus the 11 destinations already defined in
`wpadmin_button_get_destinations()`:

| Key            | Label (default)     | Capability (existing)         | Notes |
|----------------|---------------------|-------------------------------|-------|
| `edit_current` | "Edit Page"/"Edit Post"/etc. | `edit_post` for the current object | Contextual — see below |
| `dashboard`    | Dashboard           | `read`                        | |
| `posts`        | Posts               | `edit_posts`                  | |
| `media`        | Media               | `upload_files`                | |
| `pages`        | Pages               | `edit_pages`                  | |
| `comments`     | Comments            | `moderate_comments`           | |
| `appearance`   | Appearance          | `switch_themes`               | |
| `plugins`      | Plugins             | `activate_plugins`            | |
| `users`        | Users               | `list_users`                  | |
| `profile`      | Profile             | `read`                        | |
| `tools`        | Tools               | `edit_posts`                  | |
| `settings`     | Settings            | `manage_options`              | |

### "Edit current page" (contextual shortcut)

Unlike the fixed destinations, this item is conditional:

- Shown **only** on a singular, editable view (single post, page, or custom post type).
  Hidden on archives, search, blog index, and 404s.
- Capability check is **per-object**: `current_user_can( 'edit_post', $post_id )` for the
  current queried object — not a blanket capability.
- Links directly to the editor for the current object
  (`get_edit_post_link( $post_id )`).
- Label adapts to the content type using the post type's `edit_item` label
  ("Edit Page", "Edit Post", "Edit Product", …), mirroring WordPress's own toolbar.
- Admins can include/exclude and order it like any other item; users can hide it.
- When enabled but the current page is not editable, it simply drops out of the menu for
  that page view.
- Default order: near the top of the default menu.

### Update badge

The existing "updates available" count badge remains exactly as today, layered on the
main button as a small indicator.

## Settings

Personal preferences live on the **Profile** page (WordPress convention); site-wide
configuration lives on the **Tools → WPAdmin Button** page (admin only).

### Profile page (every user)

Added via `show_user_profile` / `edit_user_profile`, saved via
`personal_options_update` / `edit_user_profile_update`. Works for editing your own
profile and (for admins) editing another user's.

- **Show the WPAdmin Button** — a three-mode choice stored in plugin user meta:
  - `auto` (default): show the button only when the WordPress toolbar is hidden on the
    frontend. Matches today's behaviour, so existing sites see no change on upgrade.
  - `always`: show the button even when the toolbar is on (both together).
  - `never`: hide the button.
  - In `auto` mode the plugin only **reads** `show_admin_bar_front`; it never writes it.
    WordPress's native toolbar checkbox is left entirely alone.
- **Your menu shortcuts** — show/hide checkboxes for each shortcut the admin enabled
  *and* that this user can access. Cannot add items the admin didn't enable; cannot
  reorder. Default: all shown.

### Tools → WPAdmin Button page (admin only)

The page's required capability becomes `manage_options` (was `read`).

- **Menu shortcuts** — checkboxes to include each available item, plus drag-to-reorder
  for the order, using WordPress's bundled jQuery UI Sortable (no new dependency).
  Replaces the old single "Admin section on click" dropdown, which is removed.
- **Display for roles** — unchanged.
- **Button position** (left/right) — unchanged.

### Retired

- The plugin's custom frontend toolbar toggle on the Tools page and its
  `admin_post_wpadmin_button_update_toolbar` handler are removed. The button's
  visibility is now governed by the new `auto/always/never` setting, and the toolbar
  itself is managed only by WordPress's native control.

## Data model

### Global option (`wpadmin_button_settings`)

Existing keys `roles` and `position` are unchanged. `destination` (single string) is
replaced by an ordered list of enabled shortcut keys:

```
roles      => string[]   (unchanged)
position   => 'left'|'right' (unchanged)
menu_items => string[]   (ordered, enabled shortcut keys)
```

**Upgrade / migration:** on first load after upgrade, if `menu_items` is absent, seed it
from the old `destination` value so nothing feels broken. Default seed:
`['edit_current', 'dashboard', <previous destination if any and not duplicate>]`, all
enabled. The old `destination` key may be left in place but is no longer read.

### Per-user meta

- `wpadmin_button_visibility` — `auto` | `always` | `never` (default `auto`).
- `wpadmin_button_hidden_items` — array of shortcut keys this user has hidden
  (default empty = show all enabled items they can access).

## Display logic (`wpadmin_button_should_display`)

Replaces the current "only when toolbar off" rule:

1. Bail if `is_admin()` or not logged in (unchanged).
2. Bail if the user's role is not in the enabled `roles` (unchanged).
3. Read `wpadmin_button_visibility` (default `auto`):
   - `never` → do not display.
   - `always` → display.
   - `auto` → display only if `get_user_option( 'show_admin_bar_front' )` is `'false'`
     (today's behaviour).

## Rendering logic (menu items for the current user)

1. Start from the admin's ordered `menu_items`.
2. Remove items the user has hidden (`wpadmin_button_hidden_items`).
3. For each remaining item, drop it if the user lacks its capability
   (per-object check for `edit_current`; static capability for the rest).
4. Drop `edit_current` if the current view is not a singular editable object.
5. Render the surviving items as pills, in order.

## Accessibility & mobile

- **Keyboard:** main control is a real `<button>` exposed as a menu
  (`aria-haspopup`, `aria-expanded` reflecting state, accessible label). Enter/Space
  opens and focuses the first shortcut; Up/Down arrows move between shortcuts; `Escape`
  closes and returns focus to the button; Tab works naturally. Shortcuts are real
  anchors.
- **Screen readers:** the button has a clear label; each pill announces its own text
  (including the dynamic "Edit Page" label); the update badge keeps its spoken label.
- **Focus:** visible focus outlines on the button and every pill.
- **Reduced motion:** under `prefers-reduced-motion: reduce`, skip the slide/stagger and
  show/hide instantly.
- **Touch:** tap to open, tap a shortcut to navigate, tap outside or the button to
  close. Pills are comfortable tap targets. Menu opens upward, reuses the existing
  safe-area/viewport positioning, and scrolls within a capped height if taller than the
  screen.
- **Theming:** the menu uses the existing admin colour-scheme CSS variables so it
  matches each user's WordPress colours.

## Testing

- **Display logic:** `auto`/`always`/`never` × toolbar on/off × eligible/ineligible
  role produce the right visibility.
- **Capability filtering:** items the user can't access never render; `edit_current`
  respects per-object `edit_post` and only appears on editable singular views with the
  correct dynamic label.
- **Per-user hide:** hidden items are excluded; admin order is preserved; a user cannot
  surface items the admin disabled.
- **Migration:** a site upgrading from the single-`destination` model gets a sensible
  seeded menu and unchanged button visibility.
- **Settings save:** admin menu_items + order persist; profile visibility + hidden_items
  persist, including an admin editing another user's profile.
- **Accessibility:** keyboard open/close/navigation, focus return on `Escape`,
  `aria-expanded` state, reduced-motion path.
- **Mobile:** tap open/close, tap-outside-to-close, capped-height scroll, positioning on
  small viewports.

## Affected files

- `wpadmin-button.php` — destinations list (add `edit_current`), settings (option +
  user meta), display logic, menu rendering, Tools page (admin-only + sortable items),
  new Profile page fields, remove old toolbar toggle/handler, migration.
- `assets/wpadmin-button.js` — open/close, keyboard, tap-outside, reduced-motion-aware.
- `assets/wpadmin-button.css` — pill styling, vertical stack, expand/stagger animation,
  reduced-motion, capped-height scroll.
- Per `AGENTS.md`: refresh the matching release zip under `../releases/` once the source
  and version metadata change.
