<?php
/**
 * Plugin Name: XPressUI WordPress Bridge PRO
 * Plugin URI:  https://github.com/lybaba/xpressui-wordpress-bridge-pro
 * Description: PRO extension for XPressUI WordPress Bridge — full runtime and advanced field types.
 * Version:     1.0.0
 * Author:      Babaly LY
 * License:     GPL-2.0-or-later
 * Text Domain: xpressui-bridge-pro
 */

defined( 'ABSPATH' ) || exit;

define( 'XPRESSUI_PRO_VERSION', '1.0.1' );
define( 'XPRESSUI_PRO_RUNTIME_VERSION', '0.88.2' );
define( 'XPRESSUI_PRO_DIR', plugin_dir_path( __FILE__ ) );

register_activation_hook( __FILE__, 'xpressui_pro_check_dependencies' );

function xpressui_pro_check_dependencies(): void {
	if ( ! defined( 'XPRESSUI_BRIDGE_VERSION' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'XPressUI Bridge PRO requires the XPressUI WordPress Bridge plugin to be installed and active.', 'xpressui-bridge-pro' )
		);
	}
}

add_action( 'admin_notices', 'xpressui_pro_dependency_notice' );

function xpressui_pro_dependency_notice(): void {
	if ( ! defined( 'XPRESSUI_BRIDGE_VERSION' ) ) {
		echo '<div class="notice notice-error"><p>' .
			esc_html__( 'XPressUI Bridge PRO requires the XPressUI WordPress Bridge plugin.', 'xpressui-bridge-pro' ) .
			'</p></div>';
	}
}

if ( defined( 'XPRESSUI_BRIDGE_VERSION' ) ) {
	require_once XPRESSUI_PRO_DIR . 'includes/pro-runtime.php';
}
