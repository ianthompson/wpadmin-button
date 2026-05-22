=== WPAdmin Button ===
Contributors: ianthompson
Tags: admin, dashboard, toolbar
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.5.0
License: GPL-2.0-or-later

Adds a small floating dashboard button on the frontend for logged-in users who have disabled the WordPress toolbar.

== Description ==

WPAdmin Button shows a round settings button that links to a selected WordPress admin section. It only appears when the logged-in user has "Show Toolbar when viewing site" switched off.

Administrators can choose which roles see the button, which admin section opens on click, and whether it appears at the bottom-left or bottom-right of the page from Tools > WPAdmin Button.

When WordPress reports pending updates, users with update permissions also see a small red badge on the button. The badge links directly to Dashboard > Updates.

Logged-in dashboard users can update their own "Show Toolbar when viewing site" preference from the plugin page. Only administrators can manage the global role, position, and destination settings.

The frontend button uses the logged-in user's Administration Color Scheme.

== Updates ==

Plugin updates are served from GitHub Releases.

== Changelog ==

= 1.5.0 =
* New: the floating button now expands into a menu of admin shortcuts.
* New: admins choose and order the shortcuts; each user can hide ones they don't want, all from their profile.
* New: "Edit current page" shortcut on posts and pages.
* New: choose when the button shows — automatically, always, or never — independent of the WordPress toolbar.
