<?php
/**
 * Plugin Name: XPressUI WordPress Bridge PRO
 * Plugin URI:  https://github.com/lybaba/xpressui-wordpress-bridge-pro
 * Description: PRO extension for XPressUI WordPress Bridge — full runtime and advanced field types.
 * Version:     1.0.7
 * Author:      Babaly LY
 * License:     GPL-2.0-or-later
 * Text Domain: xpressui-bridge-pro
 */

defined( 'ABSPATH' ) || exit;

define( 'XPRESSUI_PRO_VERSION', '1.0.7' );
define( 'XPRESSUI_PRO_RUNTIME_VERSION', '1.0.0' );
define( 'XPRESSUI_PRO_DIR', plugin_dir_path( __FILE__ ) );
define( 'XPRESSUI_PRO_BUNDLED_WORKFLOWS_DIR', XPRESSUI_PRO_DIR . 'default-workflows/' );
define( 'XPRESSUI_PRO_LICENSE_KEY', '' );

// Register PRO detection filters immediately — before other plugins finish loading.
// This ensures xpressui_is_pro_extension_active() returns true regardless of plugin load order.
add_filter( 'xpressui_bridge_is_pro_extension_active', '__return_true' );
add_filter( 'xpressui_bridge_has_valid_pro_license', '__return_true' );
add_filter( 'xpressui_bundled_workflow_source_dirs', 'xpressui_pro_register_bundled_workflow_source_dirs' );

function xpressui_pro_register_bundled_workflow_source_dirs( $dirs ) {
	$dirs   = is_array( $dirs ) ? $dirs : [];
	if ( ! in_array( XPRESSUI_PRO_BUNDLED_WORKFLOWS_DIR, $dirs, true ) ) {
		$dirs[] = XPRESSUI_PRO_BUNDLED_WORKFLOWS_DIR;
	}
	return $dirs;
}

function xpressui_pro_get_runtime_file(): string {
	return XPRESSUI_PRO_DIR . 'runtime/xpressui-' . XPRESSUI_PRO_RUNTIME_VERSION . '.umd.js';
}

function xpressui_pro_has_bundled_runtime(): bool {
	return file_exists( xpressui_pro_get_runtime_file() );
}

function xpressui_pro_get_runtime_asset_url(): string {
	return plugin_dir_url( __FILE__ ) . 'runtime/xpressui-' . XPRESSUI_PRO_RUNTIME_VERSION . '.umd.js';
}

register_activation_hook( __FILE__, 'xpressui_pro_check_dependencies' );

function xpressui_pro_check_dependencies(): void {
	if ( ! defined( 'XPRESSUI_BRIDGE_VERSION' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'XPressUI Bridge PRO requires the XPressUI WordPress Bridge plugin to be installed and active.', 'xpressui-bridge-pro' )
		);
	}

	if ( function_exists( 'xpressui_maybe_install_bundled_workflows' ) ) {
		xpressui_maybe_install_bundled_workflows();
	}
}

add_action( 'admin_notices', 'xpressui_pro_dependency_notice' );
add_action( 'admin_notices', 'xpressui_pro_runtime_notice' );

function xpressui_pro_dependency_notice(): void {
	if ( ! defined( 'XPRESSUI_BRIDGE_VERSION' ) ) {
		echo '<div class="notice notice-error"><p>' .
			esc_html__( 'XPressUI Bridge PRO requires the XPressUI WordPress Bridge plugin.', 'xpressui-bridge-pro' ) .
			'</p></div>';
	}
}

function xpressui_pro_runtime_notice(): void {
	if ( ! defined( 'XPRESSUI_BRIDGE_VERSION' ) || xpressui_pro_has_bundled_runtime() ) {
		return;
	}

	echo '<div class="notice notice-warning"><p>' .
		esc_html__( 'XPressUI Bridge PRO is active but its bundled runtime file is missing. Advanced field types will fall back to the base runtime until the PRO package is reinstalled.', 'xpressui-bridge-pro' ) .
		'</p></div>';
}

// Load runtime integrations after all plugins are loaded so XPRESSUI_BRIDGE_VERSION is defined.
add_action( 'plugins_loaded', 'xpressui_pro_load_runtime' );

function xpressui_pro_load_runtime(): void {
	if ( defined( 'XPRESSUI_BRIDGE_VERSION' ) ) {
		require_once XPRESSUI_PRO_DIR . 'includes/pro-runtime.php';
	}
}
