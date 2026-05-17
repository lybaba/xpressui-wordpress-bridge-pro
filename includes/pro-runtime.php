<?php
defined( 'ABSPATH' ) || exit;

require_once XPRESSUI_PRO_DIR . 'includes/license-handler.php';
require_once XPRESSUI_PRO_DIR . 'includes/license-form.php';
require_once XPRESSUI_PRO_DIR . 'includes/console-sync.php';
require_once XPRESSUI_PRO_DIR . 'includes/overlay.php';
require_once XPRESSUI_PRO_DIR . 'includes/overlay-admin.php';
require_once XPRESSUI_PRO_DIR . 'includes/status-page.php';
// automated-reminders.php removed — feature moved to cloud offering.
// stripe-payment.php removed — feature moved to cloud offering.
// require_once XPRESSUI_PRO_DIR . 'includes/ai-document-verification.php'; // disabled — not needed yet
require_once XPRESSUI_PRO_DIR . 'includes/mobile-capture.php';
require_once XPRESSUI_PRO_DIR . 'includes/cloud-link.php';
require_once XPRESSUI_PRO_DIR . 'includes/catalog-embed.php';
// update-checker.php is loaded unconditionally from the main plugin file.

add_filter( 'xpressui_runtime_url', 'xpressui_pro_override_runtime_url', 10, 2 );
add_filter( 'xpressui_runtime_file_path', 'xpressui_pro_override_runtime_file_path', 10, 2 );

function xpressui_pro_override_runtime_url( string $url, string $slug ): string {
	if ( ! xpressui_pro_has_bundled_runtime() ) {
		return $url;
	}
	return xpressui_pro_get_runtime_asset_url();
}

function xpressui_pro_override_runtime_file_path( string $path, string $slug ): string {
	if ( ! xpressui_pro_has_bundled_runtime() ) {
		return $path;
	}
	return XPRESSUI_PRO_DIR . 'runtime/xpressui-' . XPRESSUI_PRO_RUNTIME_VERSION . '.umd.js';
}

add_filter( 'xpressui_field_template_dirs', 'xpressui_pro_register_template_dirs' );

function xpressui_pro_register_template_dirs( array $dirs ): array {
	$dirs[] = XPRESSUI_PRO_DIR . 'templates/generated/';
	return $dirs;
}
