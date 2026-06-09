<?php
/**
 * Resubmission — request corrections (PRO).
 *
 * Lets an operator flag specific fields a submitter must correct, and builds the
 * self-service resume link delivered in the pending_info notification so the
 * submitter can re-open the form pre-filled and resubmit only the flagged
 * fields.
 *
 * This operator UI + resume-link building lived in the free plugin but was
 * removed during the wordpress.org review; re-introduced here in PRO (not on
 * wordpress.org). The resume MECHANICS stay in the free plugin and are reused
 * as-is: the resume token, the /resume REST endpoint, the partial-merge on
 * resubmit, the resume-mode form rendering, and the metabox save handler that
 * persists `xpressui_flagged_fields[]`. This module only reads submission DATA
 * (post_meta / pages) and renders the operator checkboxes — no free-plugin
 * function is called (no dormant code in free).
 *
 * NOTE: code-complete + lint-clean; requires a WordPress staging test of the
 * full loop (flag fields -> pending_info -> email link -> resume -> resubmit).
 *
 * @package XPressUI_Bridge_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the resume URL for a submission: the published page embedding the
 * workflow, with ?xpressui_resume=<token>. Self-contained (data + WP queries).
 */
function xpressui_pro_build_resume_url( int $post_id ): string {
	$token = (string) get_post_meta( $post_id, '_xpressui_resume_token', true );
	if ( '' === $token ) {
		return '';
	}
	$slug = sanitize_title( (string) get_post_meta( $post_id, '_xpressui_project_slug', true ) );
	if ( '' === $slug ) {
		return '';
	}
	$base_url = xpressui_pro_resolve_workflow_page_url( $slug );
	if ( '' === $base_url ) {
		return '';
	}
	return add_query_arg( 'xpressui_resume', rawurlencode( $token ), $base_url );
}

/**
 * Finds the published page that embeds the given workflow shortcode and returns
 * its permalink (short-cached). Data-only lookup over page content.
 */
function xpressui_pro_resolve_workflow_page_url( string $slug ): string {
	$slug = sanitize_title( $slug );
	if ( '' === $slug ) {
		return '';
	}
	$cache_key = 'xpressui_pro_wf_page_' . md5( $slug );
	$cached    = wp_cache_get( $cache_key, 'xpressui_bridge_pro' );
	if ( false !== $cached ) {
		return (string) $cached;
	}

	$url = '';
	foreach (
		get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish' ),
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		) as $page
	) {
		$content = (string) ( $page->post_content ?? '' );
		if (
			false !== strpos( $content, '[xpressui id="' . $slug . '"' )
			|| false !== strpos( $content, "[xpressui id='" . $slug . "'" )
		) {
			$url = (string) get_permalink( (int) $page->ID );
			break;
		}
	}

	wp_cache_set( $cache_key, $url, 'xpressui_bridge_pro', MINUTE_IN_SECONDS );
	return $url;
}

/**
 * Submission payload field keys (data only).
 *
 * @return array<int,string>
 */
function xpressui_pro_submission_field_names( int $post_id ): array {
	$json    = (string) get_post_meta( $post_id, '_xpressui_payload_json', true );
	$payload = '' !== $json ? json_decode( $json, true ) : array();
	if ( ! is_array( $payload ) ) {
		return array();
	}
	$names = array();
	foreach ( array_keys( $payload ) as $key ) {
		$key = (string) $key;
		if ( '' === $key || str_starts_with( $key, '_' ) ) {
			continue;
		}
		$names[] = $key;
	}
	return $names;
}

/**
 * Currently-flagged field names (data only).
 *
 * @return array<int,string>
 */
function xpressui_pro_get_flagged_fields( int $post_id ): array {
	$raw     = (string) get_post_meta( $post_id, '_xpressui_flagged_fields', true );
	$decoded = '' !== $raw ? json_decode( $raw, true ) : array();
	return is_array( $decoded ) ? array_values( array_filter( $decoded, 'is_string' ) ) : array();
}

add_action( 'add_meta_boxes', 'xpressui_pro_register_resubmission_metabox' );

/**
 * Registers the operator "request resubmission" metabox on submissions.
 */
function xpressui_pro_register_resubmission_metabox(): void {
	add_meta_box(
		'xpressui_pro_resubmission_mb',
		__( 'Request resubmission', 'xpressui-bridge-pro' ),
		'xpressui_pro_render_resubmission_metabox',
		'xpressui_submission',
		'side',
		'high'
	);
}

/**
 * Renders per-field "needs correction" checkboxes. The inputs are named
 * xpressui_flagged_fields[] and are persisted by the free plugin's existing
 * submission save handler (which runs on Update under its own nonce).
 *
 * @param WP_Post|object $post
 */
function xpressui_pro_render_resubmission_metabox( $post ): void {
	$post_id = (int) ( is_object( $post ) ? ( $post->ID ?? 0 ) : 0 );
	$fields  = xpressui_pro_submission_field_names( $post_id );
	$flagged = xpressui_pro_get_flagged_fields( $post_id );

	echo '<p class="description">'
		. esc_html__( 'Tick the fields the submitter must correct, set status to “Pending info”, then click Update. The submitter gets a resume link to fix only those fields.', 'xpressui-bridge-pro' )
		. '</p>';

	if ( empty( $fields ) ) {
		echo '<p>' . esc_html__( 'No fields available for this submission yet.', 'xpressui-bridge-pro' ) . '</p>';
		return;
	}

	echo '<div class="xpressui-pro-flagged-fields" style="max-height:240px;overflow:auto;">';
	foreach ( $fields as $name ) {
		$checked = in_array( $name, $flagged, true );
		echo '<label style="display:block;margin:4px 0;">';
		echo '<input type="checkbox" name="xpressui_flagged_fields[]" value="' . esc_attr( $name ) . '" ' . checked( $checked, true, false ) . ' /> ';
		echo esc_html( $name );
		echo '</label>';
	}
	echo '</div>';

	$resume_url = xpressui_pro_build_resume_url( $post_id );
	if ( '' !== $resume_url ) {
		echo '<p class="description" style="margin-top:8px;">'
			. esc_html__( 'Resume link', 'xpressui-bridge-pro' ) . ': '
			. '<a href="' . esc_url( $resume_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'open', 'xpressui-bridge-pro' ) . '</a>'
			. '</p>';
	}
}
