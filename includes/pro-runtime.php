<?php
defined( 'ABSPATH' ) || exit;

add_filter( 'xpressui_runtime_url', 'xpressui_pro_override_runtime_url', 10, 2 );

function xpressui_pro_override_runtime_url( string $url, string $slug ): string {
	$runtime_file = XPRESSUI_PRO_DIR . 'runtime/xpressui-' . XPRESSUI_PRO_RUNTIME_VERSION . '.umd.js';
	if ( ! file_exists( $runtime_file ) ) {
		return $url;
	}
	return plugin_dir_url( XPRESSUI_PRO_DIR . 'xpressui-wordpress-bridge-pro.php' ) . 'runtime/xpressui-' . XPRESSUI_PRO_RUNTIME_VERSION . '.umd.js';
}

add_filter( 'xpressui_field_template_dirs', 'xpressui_pro_register_template_dirs' );

function xpressui_pro_register_template_dirs( array $dirs ): array {
	$dirs[] = XPRESSUI_PRO_DIR . 'templates/generated/fields/';
	return $dirs;
}
