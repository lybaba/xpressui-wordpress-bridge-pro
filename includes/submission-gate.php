<?php
/**
 * Pro submission gate.
 *
 * Blocks submission of Pro-tier workflows when no valid Pro license is active
 * (missing or expired). Free-tier workflows remain fully submittable.
 *
 * @package XPressUI_Bridge_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decide whether a workflow submission should be blocked for licensing reasons.
 *
 * Hooked to the free plugin's `xpressui_submission_gate` filter. Returns a
 * WP_Error to block the submission, or passes the incoming value through to let
 * it proceed (and to respect any block set by another add-on earlier).
 *
 * @param mixed  $gate         Current gate value (WP_Error to block, otherwise null).
 * @param string $project_slug Workflow slug being submitted.
 * @param mixed  $payload      Submission payload (unused).
 * @param mixed  $request      REST request (unused).
 * @return mixed
 */
function xpressui_pro_submission_license_gate( $gate, $project_slug, $payload = null, $request = null ) {
	// Respect a block already set by another gate.
	if ( is_wp_error( $gate ) ) {
		return $gate;
	}

	$slug = sanitize_title( (string) $project_slug );
	if ( '' === $slug ) {
		return $gate;
	}

	if ( ! function_exists( 'xpressui_get_workflow_manifest_meta' ) ) {
		return $gate;
	}

	$meta = xpressui_get_workflow_manifest_meta( $slug );
	$tier = is_array( $meta ) ? (string) ( $meta['runtimeTier'] ?? '' ) : '';

	// Only gate workflows that require the Pro runtime/license.
	if ( 'pro' !== $tier ) {
		return $gate;
	}

	if ( function_exists( 'xpressui_pro_is_license_active' ) && xpressui_pro_is_license_active() ) {
		return $gate;
	}

	return new WP_Error(
		'xpressui_pro_license_required',
		__( 'This form requires an active Pro license to accept submissions. Please contact the site owner.', 'xpressui-bridge-pro' ),
		[ 'status' => 403 ]
	);
}
add_filter( 'xpressui_submission_gate', 'xpressui_pro_submission_license_gate', 10, 4 );
