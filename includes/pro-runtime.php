<?php
defined( 'ABSPATH' ) || exit;

require_once XPRESSUI_PRO_DIR . 'includes/license-handler.php';
require_once XPRESSUI_PRO_DIR . 'includes/license-form.php';
require_once XPRESSUI_PRO_DIR . 'includes/console-sync.php';
// update-checker.php is loaded unconditionally from the main plugin file.

add_filter( 'xpressui_runtime_url', 'xpressui_pro_override_runtime_url', 10, 2 );

function xpressui_pro_override_runtime_url( string $url, string $slug ): string {
	if ( ! xpressui_pro_has_bundled_runtime() ) {
		return $url;
	}
	return xpressui_pro_get_runtime_asset_url();
}

add_filter( 'xpressui_field_template_dirs', 'xpressui_pro_register_template_dirs' );

function xpressui_pro_register_template_dirs( array $dirs ): array {
	$dirs[] = XPRESSUI_PRO_DIR . 'templates/generated/fields/';
	return $dirs;
}
