<?php
/**
 * Automatic update checker for XPressUI WordPress Bridge PRO.
 *
 * Hooks into the WordPress update system so wp-admin shows the standard
 * "Update available" badge and allows one-click plugin updates.
 * Updates are gated behind a valid license: the API validates the key
 * and serves the download URL (proxied from the private GitHub repo).
 *
 * @package XPressUI_Bridge_Pro
 */

defined( 'ABSPATH' ) || exit;

define( 'XPRESSUI_PRO_UPDATE_API_URL', 'https://xpressui.iakpress.com/api/v1/plugins/xpressui-wordpress-bridge-pro/update-check' );
define( 'XPRESSUI_PRO_UPDATE_TRANSIENT', 'xpressui_pro_update_info' );
define( 'XPRESSUI_PRO_PLUGIN_FILE', 'xpressui-wordpress-bridge-pro/xpressui-wordpress-bridge-pro.php' );

// ---------------------------------------------------------------------------
// WordPress update hooks
// ---------------------------------------------------------------------------

add_filter( 'pre_set_site_transient_update_plugins', 'xpressui_pro_inject_update_info' );

/**
 * Injects Pro plugin update data into the WordPress update transient.
 *
 * @param object $transient The update_plugins transient.
 * @return object
 */
function xpressui_pro_inject_update_info( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$current_version = $transient->checked[ XPRESSUI_PRO_PLUGIN_FILE ] ?? XPRESSUI_PRO_VERSION;
	$update_info     = xpressui_pro_fetch_update_info( $current_version );

	if ( is_array( $update_info ) && ! empty( $update_info['version'] ) ) {
		$transient->response[ XPRESSUI_PRO_PLUGIN_FILE ] = (object) [
			'slug'        => 'xpressui-wordpress-bridge-pro',
			'plugin'      => XPRESSUI_PRO_PLUGIN_FILE,
			'new_version' => $update_info['version'],
			'url'         => 'https://iakpress.com',
			'package'     => $update_info['download_url'],
			'requires'    => $update_info['requires'] ?? '6.0',
			'tested'      => $update_info['tested'] ?? '6.9',
		];
	} else {
		unset( $transient->response[ XPRESSUI_PRO_PLUGIN_FILE ] );
	}

	return $transient;
}

add_filter( 'plugins_api', 'xpressui_pro_plugin_info', 10, 3 );

/**
 * Provides plugin details for the "View version details" popup in wp-admin.
 *
 * @param false|object $result
 * @param string       $action
 * @param object       $args
 * @return false|object
 */
function xpressui_pro_plugin_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || 'xpressui-wordpress-bridge-pro' !== ( $args->slug ?? '' ) ) {
		return $result;
	}

	$update_info = xpressui_pro_fetch_update_info( XPRESSUI_PRO_VERSION );
	if ( ! is_array( $update_info ) || empty( $update_info['version'] ) ) {
		return $result;
	}

	return (object) [
		'name'          => 'XPressUI WordPress Bridge PRO',
		'slug'          => 'xpressui-wordpress-bridge-pro',
		'version'       => $update_info['version'],
		'author'        => '<a href="https://iakpress.com">IAKPress</a>',
		'requires'      => $update_info['requires'] ?? '6.0',
		'tested'        => $update_info['tested'] ?? '6.9',
		'download_link' => $update_info['download_url'],
		'sections'      => [
			'description' => 'PRO extension for XPressUI WordPress Bridge — full runtime and advanced field types.',
			'changelog'   => $update_info['changelog'] ?? '',
		],
	];
}

// ---------------------------------------------------------------------------
// Clear our transient after the plugin is updated (so next check is fresh)
// ---------------------------------------------------------------------------

add_action( 'upgrader_process_complete', 'xpressui_pro_clear_update_transient_after_upgrade', 10, 2 );
add_action( 'wp_update_plugins', 'xpressui_pro_clear_update_transient_on_wp_check' );

/**
 * Clears our cache whenever WordPress forces a fresh plugin update check
 * (e.g. Dashboard > Updates > "Check again").
 */
function xpressui_pro_clear_update_transient_on_wp_check(): void {
	delete_transient( XPRESSUI_PRO_UPDATE_TRANSIENT );
}

/**
 * @param \WP_Upgrader $upgrader
 * @param array        $hook_extra
 */
function xpressui_pro_clear_update_transient_after_upgrade( $upgrader, array $hook_extra ): void {
	if (
		isset( $hook_extra['type'], $hook_extra['action'] ) &&
		'plugin' === $hook_extra['type'] &&
		'update' === $hook_extra['action']
	) {
		$plugins = $hook_extra['plugins'] ?? [ $hook_extra['plugin'] ?? '' ];
		if ( in_array( XPRESSUI_PRO_PLUGIN_FILE, (array) $plugins, true ) ) {
			delete_transient( XPRESSUI_PRO_UPDATE_TRANSIENT );
		}
	}
}

add_action( 'admin_notices', 'xpressui_pro_update_available_notice' );

/**
 * Shows an update-available notice on the XPressUI Workflows page.
 */
function xpressui_pro_update_available_notice(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'xpressui_submission_page_xpressui-bridge' !== $screen->id ) {
		return;
	}

	$update_info = xpressui_pro_fetch_update_info( XPRESSUI_PRO_VERSION );
	if ( ! is_array( $update_info ) || empty( $update_info['version'] ) ) {
		return;
	}

	$new_version = esc_html( $update_info['version'] );
	$update_url  = esc_url(
		wp_nonce_url(
			admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( XPRESSUI_PRO_PLUGIN_FILE ) ),
			'upgrade-plugin_' . XPRESSUI_PRO_PLUGIN_FILE
		)
	);

	echo '<div class="notice notice-warning is-dismissible">';
	echo '<p>';
	printf(
		/* translators: 1: new version number, 2: update URL */
		wp_kses(
			__( '<strong>XPressUI WordPress Bridge PRO %1$s</strong> is available. <a href="%2$s">Update now</a>.', 'xpressui-wordpress-bridge-pro' ),
			[ 'strong' => [], 'a' => [ 'href' => [] ] ]
		),
		$new_version,
		$update_url
	);
	echo '</p>';
	echo '</div>';
}

// ---------------------------------------------------------------------------
// Core fetch logic
// ---------------------------------------------------------------------------

/**
 * Fetches update info from the API, with a 2-hour transient cache.
 * Returns null if no update is available or the license is missing/invalid.
 *
 * @param string $current_version The currently installed version.
 * @return array|null Update payload or null.
 */
function xpressui_pro_fetch_update_info( string $current_version ): ?array {
	$cached = get_transient( XPRESSUI_PRO_UPDATE_TRANSIENT );
	if ( false !== $cached ) {
		return is_array( $cached ) ? $cached : null;
	}

	$license_data = get_option( XPRESSUI_PRO_LICENSE_OPTION_KEY, [] );
	$license_key  = $license_data['license_key'] ?? '';

	if ( empty( $license_key ) ) {
		set_transient( XPRESSUI_PRO_UPDATE_TRANSIENT, 'no_license', 2 * HOUR_IN_SECONDS );
		return null;
	}

	$api_url = add_query_arg(
		[
			'license_key'     => $license_key,
			'current_version' => $current_version,
		],
		XPRESSUI_PRO_UPDATE_API_URL
	);

	$response = wp_remote_get(
		$api_url,
		[
			'timeout'    => 10,
			'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_site_url(),
		]
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		set_transient( XPRESSUI_PRO_UPDATE_TRANSIENT, 'error', HOUR_IN_SECONDS );
		return null;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $data ) || ! empty( $data['up_to_date'] ) ) {
		set_transient( XPRESSUI_PRO_UPDATE_TRANSIENT, 'up_to_date', 2 * HOUR_IN_SECONDS );
		return null;
	}

	set_transient( XPRESSUI_PRO_UPDATE_TRANSIENT, $data, 2 * HOUR_IN_SECONDS );
	return $data;
}
