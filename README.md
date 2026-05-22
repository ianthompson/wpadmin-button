# WPAdmin Button

WPAdmin Button adds a round floating button on the frontend that expands into a menu of WordPress admin shortcuts — including a contextual "Edit current page" link when viewing an editable post or page.

From **Tools > WPAdmin Button**, administrators can choose:

- which shortcuts appear in the menu, and their order
- which user roles see the button
- whether the button appears bottom-left or bottom-right

Each shortcut is automatically hidden from any user who lacks permission to use it.

From their own profile page, each user chooses when the button appears — automatically (only when the WordPress toolbar is hidden), always, or never — and can hide any shortcuts they don't want to see.

When WordPress reports pending updates, users with update permissions also see a small red badge on the button. The badge links directly to **Dashboard > Updates**.

The button and its menu use the logged-in user's Administration Color Scheme.

## Updates

The plugin includes a GitHub Releases update checker. To publish a new version:

1. Update the plugin version in `wpadmin-button.php` and `readme.txt`.
2. Commit the changes.
3. Create a release tag such as `v1.4.0`.
4. Attach `wpadmin-button.zip` to the GitHub release.

WordPress will use the attached release asset for dashboard updates.

The update code preserves the active plugin directory during GitHub package installs so WordPress can reactivate the plugin after an update even if a previous manual install used a different folder name.
