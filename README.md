# WPAdmin Button

WPAdmin Button adds a small round floating settings button on the frontend for logged-in users who have disabled the WordPress toolbar.

From **Tools > WPAdmin Button**, administrators can choose:

- which user roles see the button
- whether the button appears bottom-left or bottom-right
- which WordPress admin section opens when the button is clicked

When WordPress reports pending updates, users with update permissions also see a small red badge on the button. The badge links directly to **Dashboard > Updates**.

Logged-in dashboard users can also update their own frontend toolbar preference from the same plugin page. Only administrators can manage the global role, position, and destination settings.

The button uses the logged-in user's Administration Color Scheme.

## Updates

The plugin includes a GitHub Releases update checker. To publish a new version:

1. Update the plugin version in `wpadmin-button.php` and `readme.txt`.
2. Commit the changes.
3. Create a release tag such as `v1.4.0`.
4. Attach `wpadmin-button.zip` to the GitHub release.

WordPress will use the attached release asset for dashboard updates.

The update code preserves the active plugin directory during GitHub package installs so WordPress can reactivate the plugin after an update even if a previous manual install used a different folder name.
