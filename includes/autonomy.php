<?php
/**
 * PRO autonomy mode (A0).
 *
 * Switches operator / integration features between "cloud" (managed by the
 * IntakeFlow SaaS) and "local" (fully self-hosted by WordPress — no Cloud
 * transit, recommended for GDPR / data-sovereignty). Read by the local
 * notification, OTP and webhook features (A2-A4) and the status-change
 * notifications (A5).
 *
 * Self-contained in PRO: no dependency on the free plugin's code.
 *
 * @package XPressUI_Bridge_Pro
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'XPRESSUI_PRO_AUTONOMY_OPTION_KEY' ) ) {
	define( 'XPRESSUI_PRO_AUTONOMY_OPTION_KEY', 'xpressui_pro_autonomy_mode' );
}

/**
 * Returns the configured autonomy mode: 'cloud' (default) or 'local'.
 */
function xpressui_pro_autonomy_mode(): string {
	$mode = (string) get_option( XPRESSUI_PRO_AUTONOMY_OPTION_KEY, 'cloud' );
	return 'local' === $mode ? 'local' : 'cloud';
}

/**
 * True when the site runs in self-hosted (local / GDPR) mode.
 */
function xpressui_pro_is_autonomous(): bool {
	return 'local' === xpressui_pro_autonomy_mode();
}

add_action( 'admin_menu', 'xpressui_pro_register_autonomy_page' );

/**
 * Registers the Autonomy settings page under the XPressUI submissions menu.
 */
function xpressui_pro_register_autonomy_page(): void {
	add_submenu_page(
		'edit.php?post_type=xpressui_submission',
		__( 'Autonomy Mode', 'xpressui-bridge-pro' ),
		__( 'Autonomy Mode', 'xpressui-bridge-pro' ),
		'manage_options',
		'xpressui-pro-autonomy',
		'xpressui_pro_render_autonomy_page'
	);
}

/**
 * Renders the Autonomy settings page.
 */
function xpressui_pro_render_autonomy_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'xpressui-bridge-pro' ) );
	}

	$mode  = xpressui_pro_autonomy_mode();
	$saved = isset( $_GET['xpressui_autonomy_saved'], $_GET['_wpnonce'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'xpressui_pro_autonomy_saved' );

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Autonomy Mode', 'xpressui-bridge-pro' ) . '</h1>';
	echo '<p>' . esc_html__( 'Choose how operator and integration features behave. In Local mode the plugin runs fully self-hosted (confirmation emails, email verification and outbound webhooks handled by WordPress) with no Cloud dependency — recommended for GDPR / data sovereignty.', 'xpressui-bridge-pro' ) . '</p>';

	if ( $saved ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Autonomy mode saved.', 'xpressui-bridge-pro' ) . '</p></div>';
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'xpressui_pro_save_autonomy', 'xpressui_pro_autonomy_nonce' );
	echo '<input type="hidden" name="action" value="xpressui_pro_save_autonomy" />';
	echo '<table class="form-table" role="presentation"><tbody><tr>';
	echo '<th scope="row">' . esc_html__( 'Mode', 'xpressui-bridge-pro' ) . '</th><td><fieldset>';
	echo '<label style="display:block;margin-bottom:8px;"><input type="radio" name="xpressui_pro_autonomy_mode" value="cloud" ' . checked( 'cloud', $mode, false ) . '> '
		. esc_html__( 'Cloud (IntakeFlow) — managed verification, notifications and routing via the SaaS.', 'xpressui-bridge-pro' ) . '</label>';
	echo '<label style="display:block;"><input type="radio" name="xpressui_pro_autonomy_mode" value="local" ' . checked( 'local', $mode, false ) . '> '
		. esc_html__( 'Local (self-hosted / GDPR) — emails, verification and webhooks handled by WordPress, no Cloud transit.', 'xpressui-bridge-pro' ) . '</label>';
	echo '</fieldset></td></tr></tbody></table>';
	submit_button( __( 'Save changes', 'xpressui-bridge-pro' ) );
	echo '</form></div>';
}

add_action( 'admin_post_xpressui_pro_save_autonomy', 'xpressui_pro_handle_save_autonomy' );

/**
 * Persists the autonomy mode from the settings form.
 */
function xpressui_pro_handle_save_autonomy(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions.', 'xpressui-bridge-pro' ) );
	}
	check_admin_referer( 'xpressui_pro_save_autonomy', 'xpressui_pro_autonomy_nonce' );

	$mode = isset( $_POST['xpressui_pro_autonomy_mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['xpressui_pro_autonomy_mode'] ) ) : 'cloud';
	$mode = 'local' === $mode ? 'local' : 'cloud';
	update_option( XPRESSUI_PRO_AUTONOMY_OPTION_KEY, $mode, false );

	wp_safe_redirect(
		add_query_arg(
			[
				'post_type'               => 'xpressui_submission',
				'page'                    => 'xpressui-pro-autonomy',
				'xpressui_autonomy_saved' => '1',
				'_wpnonce'                => wp_create_nonce( 'xpressui_pro_autonomy_saved' ),
			],
			admin_url( 'edit.php' )
		)
	);
	exit;
}
