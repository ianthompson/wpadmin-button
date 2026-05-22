<?php
/**
 * Plugin Name: WPAdmin Button
 * Description: Shows a small floating dashboard button when the current user has the frontend toolbar disabled.
 * Version: 1.4.3
 * Author: Ian Thompson
 * License: GPL-2.0-or-later
 * Requires PHP: 7.4
 * Update URI: https://github.com/ianthompson/wpadmin-button
 * Text Domain: wpadmin-button
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPADMIN_BUTTON_VERSION', '1.4.3' );
define( 'WPADMIN_BUTTON_OPTION', 'wpadmin_button_settings' );
define( 'WPADMIN_BUTTON_FILE', __FILE__ );
define( 'WPADMIN_BUTTON_URL', plugin_dir_url( __FILE__ ) );
define( 'WPADMIN_BUTTON_SLUG', 'wpadmin-button' );
define( 'WPADMIN_BUTTON_GITHUB_OWNER', 'ianthompson' );
define( 'WPADMIN_BUTTON_GITHUB_REPO', 'wpadmin-button' );
define( 'WPADMIN_BUTTON_GITHUB_ASSET', 'wpadmin-button.zip' );
define( 'WPADMIN_BUTTON_GITHUB_REPO_URL', 'https://github.com/ianthompson/wpadmin-button' );
define( 'WPADMIN_BUTTON_GITHUB_RELEASES_URL', 'https://api.github.com/repos/ianthompson/wpadmin-button/releases/latest' );

require_once __DIR__ . '/includes/menu-logic.php';

/**
 * Returns the plugin settings with defaults applied.
 *
 * @return array{roles: string[], position: string, menu_items: string[]}
 */
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

/**
 * Determines whether the current user may manage global plugin settings.
 *
 * @return bool
 */
function wpadmin_button_can_manage_global_settings() {
	return current_user_can( 'manage_options' );
}

/**
 * Returns available admin button destinations.
 *
 * @return array<string, array{label: string, path: string, capability: string}>
 */
function wpadmin_button_get_destinations() {
	return array(
		'dashboard'  => array(
			'label'      => __( 'Dashboard', 'wpadmin-button' ),
			'path'       => 'index.php',
			'capability' => 'read',
		),
		'posts'      => array(
			'label'      => __( 'Posts', 'wpadmin-button' ),
			'path'       => 'edit.php',
			'capability' => 'edit_posts',
		),
		'media'      => array(
			'label'      => __( 'Media', 'wpadmin-button' ),
			'path'       => 'upload.php',
			'capability' => 'upload_files',
		),
		'pages'      => array(
			'label'      => __( 'Pages', 'wpadmin-button' ),
			'path'       => 'edit.php?post_type=page',
			'capability' => 'edit_pages',
		),
		'comments'   => array(
			'label'      => __( 'Comments', 'wpadmin-button' ),
			'path'       => 'edit-comments.php',
			'capability' => 'moderate_comments',
		),
		'appearance' => array(
			'label'      => __( 'Appearance', 'wpadmin-button' ),
			'path'       => 'themes.php',
			'capability' => 'switch_themes',
		),
		'plugins'    => array(
			'label'      => __( 'Plugins', 'wpadmin-button' ),
			'path'       => 'plugins.php',
			'capability' => 'activate_plugins',
		),
		'users'      => array(
			'label'      => __( 'Users', 'wpadmin-button' ),
			'path'       => 'users.php',
			'capability' => 'list_users',
		),
		'profile'    => array(
			'label'      => __( 'Profile', 'wpadmin-button' ),
			'path'       => 'profile.php',
			'capability' => 'read',
		),
		'tools'      => array(
			'label'      => __( 'Tools', 'wpadmin-button' ),
			'path'       => 'tools.php',
			'capability' => 'edit_posts',
		),
		'settings'   => array(
			'label'      => __( 'Settings', 'wpadmin-button' ),
			'path'       => 'options-general.php',
			'capability' => 'manage_options',
		),
	);
}

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
	if ( ! is_singular() ) {
		return __( 'Edit', 'wpadmin-button' );
	}

	$post_id   = get_queried_object_id();
	$post_type = $post_id ? get_post_type( $post_id ) : '';
	$type_obj  = $post_type ? get_post_type_object( $post_type ) : null;

	if ( $type_obj && isset( $type_obj->labels->edit_item ) && $type_obj->labels->edit_item ) {
		return $type_obj->labels->edit_item;
	}

	return __( 'Edit', 'wpadmin-button' );
}

/**
 * Sanitizes settings before storage.
 *
 * @param array $input Raw option input.
 * @return array{roles: string[], position: string, menu_items: string[]}
 */
function wpadmin_button_sanitize_settings( $input ) {
	$can_manage       = wpadmin_button_can_manage_global_settings();
	$current_settings = wpadmin_button_get_settings();
	$roles            = array();

	if ( $can_manage && isset( $input['roles'] ) && is_array( $input['roles'] ) ) {
		$editable_roles = get_editable_roles();
		$valid_roles    = array_keys( $editable_roles );

		foreach ( $input['roles'] as $role ) {
			$role = sanitize_key( $role );

			if ( in_array( $role, $valid_roles, true ) ) {
				$roles[] = $role;
			}
		}
	}

	if ( ! $can_manage ) {
		$roles = $current_settings['roles'];
	}

	$position = $can_manage && isset( $input['position'] ) ? sanitize_key( $input['position'] ) : $current_settings['position'];

	if ( ! in_array( $position, array( 'left', 'right' ), true ) ) {
		$position = 'right';
	}

	// Menu items: an ordered, hidden-field-encoded list "key1,key2,..." from the
	// sortable UI, plus per-row checkboxes for which are enabled.
	if ( $can_manage ) {
		$valid_keys = array_keys( wpadmin_button_get_menu_catalog() );
		$order      = isset( $input['menu_order'] ) ? explode( ',', sanitize_text_field( wp_unslash( (string) $input['menu_order'] ) ) ) : array();
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

/**
 * Registers the Tools page and settings.
 */
function wpadmin_button_register_admin_page() {
	add_management_page(
		__( 'WPAdmin Button', 'wpadmin-button' ),
		__( 'WPAdmin Button', 'wpadmin-button' ),
		'manage_options',
		'wpadmin-button',
		'wpadmin_button_render_admin_page'
	);
}
add_action( 'admin_menu', 'wpadmin_button_register_admin_page' );

/**
 * Registers plugin options.
 */
function wpadmin_button_register_settings() {
	register_setting(
		'wpadmin_button_settings',
		WPADMIN_BUTTON_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'wpadmin_button_sanitize_settings',
			'default'           => array(
				'roles'      => array( 'administrator' ),
				'position'   => 'right',
				'menu_items' => array( 'edit_current', 'dashboard' ),
			),
		)
	);
}
add_action( 'admin_init', 'wpadmin_button_register_settings' );

/**
 * Restricts the global settings endpoint to administrators.
 *
 * @return string
 */
function wpadmin_button_settings_capability() {
	return 'manage_options';
}
add_filter( 'option_page_capability_wpadmin_button_settings', 'wpadmin_button_settings_capability' );

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

/**
 * Determines whether the current user should see the floating button.
 *
 * @return bool
 */
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

/**
 * Gets update count data for the current user.
 *
 * @return array{count: int, title: string, url: string}
 */
function wpadmin_button_get_update_badge_data() {
	if ( ! current_user_can( 'update_core' ) && ! current_user_can( 'update_plugins' ) && ! current_user_can( 'update_themes' ) ) {
		return array(
			'count' => 0,
			'title' => '',
			'url'   => '',
		);
	}

	if ( ! function_exists( 'wp_get_update_data' ) ) {
		require_once ABSPATH . 'wp-includes/update.php';
	}

	$update_data = wp_get_update_data();
	$count       = isset( $update_data['counts']['total'] ) ? (int) $update_data['counts']['total'] : 0;
	$title       = isset( $update_data['title'] ) ? wp_strip_all_tags( $update_data['title'] ) : '';

	return array(
		'count' => max( 0, $count ),
		'title' => $title,
		'url'   => admin_url( 'update-core.php' ),
	);
}

/**
 * Gets frontend colors for the current user's admin color scheme.
 *
 * @return array{background: string, foreground: string, hover: string, shadow: string}
 */
function wpadmin_button_get_color_scheme() {
	global $_wp_admin_css_colors;

	$scheme = get_user_option( 'admin_color', get_current_user_id() );

	if ( ! $scheme ) {
		$scheme = 'fresh';
	}

	if ( empty( $_wp_admin_css_colors ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}

	if ( isset( $_wp_admin_css_colors[ $scheme ] ) && ! empty( $_wp_admin_css_colors[ $scheme ]->colors ) ) {
		$colors = $_wp_admin_css_colors[ $scheme ]->colors;

		return array(
			'background' => isset( $colors[2] ) ? $colors[2] : '#2271b1',
			'foreground' => '#ffffff',
			'hover'      => isset( $colors[3] ) ? $colors[3] : '#135e96',
			'shadow'     => 'rgba(0, 0, 0, 0.22)',
		);
	}

	$schemes = array(
		'fresh'     => array( '#2271b1', '#135e96' ),
		'light'     => array( '#0085ba', '#0073aa' ),
		'modern'    => array( '#3858e9', '#2145e6' ),
		'blue'      => array( '#096484', '#52accc' ),
		'coffee'    => array( '#c7a589', '#9ea476' ),
		'ectoplasm' => array( '#a3b745', '#d46f15' ),
		'midnight'  => array( '#69a8bb', '#e14d43' ),
		'ocean'     => array( '#9ebaa0', '#aa9d88' ),
		'sunrise'   => array( '#dd823b', '#ccaf0b' ),
	);

	$colors = isset( $schemes[ $scheme ] ) ? $schemes[ $scheme ] : $schemes['fresh'];

	return array(
		'background' => $colors[0],
		'foreground' => '#ffffff',
		'hover'      => $colors[1],
		'shadow'     => 'rgba(0, 0, 0, 0.22)',
	);
}

/**
 * Enqueues frontend styles when the button is visible.
 */
function wpadmin_button_enqueue_assets() {
	if ( ! wpadmin_button_should_display() ) {
		return;
	}

	$colors = wpadmin_button_get_color_scheme();

	wp_enqueue_style(
		'wpadmin-button',
		WPADMIN_BUTTON_URL . 'assets/wpadmin-button.css',
		array(),
		WPADMIN_BUTTON_VERSION
	);

	wp_enqueue_script(
		'wpadmin-button',
		WPADMIN_BUTTON_URL . 'assets/wpadmin-button.js',
		array(),
		WPADMIN_BUTTON_VERSION,
		true
	);

	$inline_css = sprintf(
		':root{--wpadmin-button-bg:%1$s;--wpadmin-button-fg:%2$s;--wpadmin-button-hover:%3$s;--wpadmin-button-shadow:%4$s;}',
		esc_html( $colors['background'] ),
		esc_html( $colors['foreground'] ),
		esc_html( $colors['hover'] ),
		esc_html( $colors['shadow'] )
	);

	wp_add_inline_style( 'wpadmin-button', $inline_css );
}
add_action( 'wp_enqueue_scripts', 'wpadmin_button_enqueue_assets' );

/**
 * Prints the floating dashboard link.
 */
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
add_action( 'wp_footer', 'wpadmin_button_render_frontend_button' );

/**
 * Fetches the latest GitHub release metadata.
 *
 * @param bool $force_refresh Whether to bypass the cached release response.
 * @return array|null
 */
function wpadmin_button_get_latest_release( $force_refresh = false ) {
	$cache_key = 'wpadmin_button_latest_release';

	if ( ! $force_refresh ) {
		$cached = get_site_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}
	}

	$response = wp_remote_get(
		WPADMIN_BUTTON_GITHUB_RELEASES_URL,
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'WPAdmin Button/' . WPADMIN_BUTTON_VERSION . '; ' . home_url(),
			),
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$release = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
		return null;
	}

	set_site_transient( $cache_key, $release, 6 * HOUR_IN_SECONDS );

	return $release;
}

/**
 * Gets the plugin zip download URL from a GitHub release.
 *
 * @param array $release GitHub release metadata.
 * @return string
 */
function wpadmin_button_get_release_package_url( $release ) {
	if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
		foreach ( $release['assets'] as $asset ) {
			if ( isset( $asset['name'], $asset['browser_download_url'] ) && WPADMIN_BUTTON_GITHUB_ASSET === $asset['name'] ) {
				return $asset['browser_download_url'];
			}
		}
	}

	return isset( $release['zipball_url'] ) ? $release['zipball_url'] : '';
}

/**
 * Adds GitHub release updates to the WordPress plugin update checker.
 *
 * @param object $transient Plugin update transient.
 * @return object
 */
function wpadmin_button_check_for_update( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$plugin_file = plugin_basename( WPADMIN_BUTTON_FILE );
	$release     = wpadmin_button_get_latest_release();

	if ( ! $release ) {
		return $transient;
	}

	$latest_version = ltrim( $release['tag_name'], 'vV' );
	$package_url    = wpadmin_button_get_release_package_url( $release );

	if ( ! $package_url || ! version_compare( WPADMIN_BUTTON_VERSION, $latest_version, '<' ) ) {
		return $transient;
	}

	$transient->response[ $plugin_file ] = (object) array(
		'id'          => WPADMIN_BUTTON_GITHUB_REPO_URL,
		'slug'        => WPADMIN_BUTTON_SLUG,
		'plugin'      => $plugin_file,
		'new_version' => $latest_version,
		'url'         => WPADMIN_BUTTON_GITHUB_REPO_URL,
		'package'     => $package_url,
		'tested'      => '6.5',
		'requires'    => '5.8',
		'requires_php' => '7.4',
	);

	return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', 'wpadmin_button_check_for_update' );

/**
 * Keeps GitHub update packages aligned with the currently installed plugin folder.
 *
 * WordPress reactivates plugins by their active plugin basename after update. If a
 * custom package extracts to a different top-level folder, the update can succeed
 * but WordPress may report that the plugin file no longer exists.
 *
 * @param string      $source The path to the package source.
 * @param string      $remote_source The path to the remote source.
 * @param WP_Upgrader $upgrader The upgrader instance.
 * @param array       $hook_extra Extra arguments passed to hooked filters.
 * @return string
 */
function wpadmin_button_preserve_install_directory( $source, $remote_source, $upgrader, $hook_extra ) {
	if ( empty( $hook_extra['plugin'] ) || plugin_basename( WPADMIN_BUTTON_FILE ) !== $hook_extra['plugin'] ) {
		return $source;
	}

	$installed_directory = dirname( plugin_basename( WPADMIN_BUTTON_FILE ) );

	if ( '.' === $installed_directory || basename( $source ) === $installed_directory ) {
		return $source;
	}

	global $wp_filesystem;

	if ( ! $wp_filesystem ) {
		return $source;
	}

	$renamed_source = trailingslashit( $remote_source ) . $installed_directory;

	if ( $wp_filesystem->exists( $renamed_source ) ) {
		$wp_filesystem->delete( $renamed_source, true );
	}

	if ( $wp_filesystem->move( $source, $renamed_source, true ) ) {
		return $renamed_source;
	}

	return $source;
}
add_filter( 'upgrader_source_selection', 'wpadmin_button_preserve_install_directory', 10, 4 );

/**
 * Provides plugin details for the WordPress update modal.
 *
 * @param false|object|array $result Current plugin API result.
 * @param string             $action Plugin API action.
 * @param object             $args Plugin API arguments.
 * @return false|object|array
 */
function wpadmin_button_plugin_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || WPADMIN_BUTTON_SLUG !== $args->slug ) {
		return $result;
	}

	$release = wpadmin_button_get_latest_release( true );

	if ( ! $release ) {
		return $result;
	}

	$latest_version = ltrim( $release['tag_name'], 'vV' );
	$package_url    = wpadmin_button_get_release_package_url( $release );
	$published_at   = isset( $release['published_at'] ) ? $release['published_at'] : '';
	$body           = ! empty( $release['body'] ) ? wp_kses_post( wpautop( $release['body'] ) ) : __( 'No release notes provided.', 'wpadmin-button' );

	return (object) array(
		'name'          => __( 'WPAdmin Button', 'wpadmin-button' ),
		'slug'          => WPADMIN_BUTTON_SLUG,
		'version'       => $latest_version,
		'author'        => '<a href="https://github.com/' . esc_attr( WPADMIN_BUTTON_GITHUB_OWNER ) . '">Ian Thompson</a>',
		'homepage'      => WPADMIN_BUTTON_GITHUB_REPO_URL,
		'requires'      => '5.8',
		'tested'        => '6.5',
		'requires_php'  => '7.4',
		'last_updated'  => $published_at,
		'download_link' => $package_url,
		'sections'      => array(
			'description' => __( 'Adds a small floating admin button on the frontend for logged-in users who have disabled the WordPress toolbar.', 'wpadmin-button' ),
			'changelog'   => $body,
		),
	);
}
add_filter( 'plugins_api', 'wpadmin_button_plugin_info', 20, 3 );
