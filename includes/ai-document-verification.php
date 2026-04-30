<?php
/**
 * AI Document Verification — Pro feature.
 *
 * When a new submission arrives, schedules an asynchronous WP-Cron pass
 * that sends each uploaded image or PDF to the Anthropic Claude API.
 * Claude returns a classification (detected type, confidence, match vs. the
 * expected document) that is stored in post meta and displayed in a
 * dedicated meta box on the submission edit screen.
 *
 * Settings stored in `xpressui_project_settings[$slug]`:
 *   proAiVerifyEnabled   bool    true/false
 *   proAiVerifyApiKey    string  Anthropic API key (sk-ant-…)
 *
 * Post meta set on `xpressui_submission`:
 *   _xpressui_ai_verify_status  string  '' | 'running' | 'done' | 'no_files'
 *   _xpressui_ai_verify         string  JSON map { field_name: result_object }
 *
 * @package XPressUI_Bridge_Pro
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// WP-Cron action
// ---------------------------------------------------------------------------

add_action( 'xpressui_pro_run_ai_verification', 'xpressui_pro_handle_ai_verification', 10, 2 );

add_action( 'xpressui_submission_first_created', 'xpressui_pro_schedule_ai_verification', 10, 3 );

function xpressui_pro_schedule_ai_verification( int $post_id, string $slug, $payload ): void {
	$all_settings = get_option( 'xpressui_project_settings', [] );
	$s            = is_array( $all_settings[ $slug ] ?? null ) ? $all_settings[ $slug ] : [];

	$enabled = filter_var( $s['proAiVerifyEnabled'] ?? false, FILTER_VALIDATE_BOOLEAN );
	if ( ! $enabled ) {
		return;
	}
	if ( '' === trim( (string) ( $s['proAiVerifyApiKey'] ?? '' ) ) ) {
		return;
	}

	wp_schedule_single_event( time(), 'xpressui_pro_run_ai_verification', [ $post_id, $slug ] );
}

// ---------------------------------------------------------------------------
// Cron handler
// ---------------------------------------------------------------------------

function xpressui_pro_handle_ai_verification( int $post_id, string $slug ): void {
	$all_settings = get_option( 'xpressui_project_settings', [] );
	$s            = is_array( $all_settings[ $slug ] ?? null ) ? $all_settings[ $slug ] : [];
	$api_key      = trim( (string) ( $s['proAiVerifyApiKey'] ?? '' ) );

	if ( '' === $api_key ) {
		return;
	}

	$files_raw = get_post_meta( $post_id, '_xpressui_uploaded_files', true );
	$files     = is_string( $files_raw ) ? json_decode( $files_raw, true ) : $files_raw;

	if ( ! is_array( $files ) || empty( $files ) ) {
		update_post_meta( $post_id, '_xpressui_ai_verify_status', 'no_files' );
		return;
	}

	update_post_meta( $post_id, '_xpressui_ai_verify_status', 'running' );

	$results = [];
	foreach ( $files as $file ) {
		$field_name    = (string) ( $file['field'] ?? '' );
		$attachment_id = (int) ( $file['attachmentId'] ?? 0 );

		if ( $field_name === '' || $attachment_id === 0 ) {
			continue;
		}

		$results[ $field_name ] = xpressui_pro_verify_one_file( $attachment_id, $field_name, $api_key );
	}

	update_post_meta( $post_id, '_xpressui_ai_verify', wp_json_encode( $results ) );
	update_post_meta( $post_id, '_xpressui_ai_verify_status', 'done' );
}

// ---------------------------------------------------------------------------
// Per-file verification
// ---------------------------------------------------------------------------

function xpressui_pro_verify_one_file( int $attachment_id, string $field_name, string $api_key ): array {
	$supported_image_types = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
	$mime_type             = (string) get_post_mime_type( $attachment_id );
	$is_image              = in_array( $mime_type, $supported_image_types, true );
	$is_pdf                = $mime_type === 'application/pdf';

	if ( ! $is_image && ! $is_pdf ) {
		return [ 'skipped' => true, 'reason' => 'unsupported_type', 'mime_type' => $mime_type ];
	}

	$file_path = (string) get_attached_file( $attachment_id );
	if ( '' === $file_path || ! file_exists( $file_path ) ) {
		return [ 'error' => 'file_not_found' ];
	}

	$file_size = filesize( $file_path );
	if ( false === $file_size || $file_size > 5 * 1024 * 1024 ) {
		return [ 'error' => 'file_too_large' ];
	}

	$file_content = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $file_content ) {
		return [ 'error' => 'file_read_error' ];
	}

	$base64_data = base64_encode( $file_content ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	$field_label = ucwords( str_replace( [ '_', '-' ], ' ', $field_name ) );

	$prompt = sprintf(
		"You are a document classifier. This file was submitted for a form field labeled \"%s\".\n\nClassify this document and respond ONLY with a valid JSON object (no markdown, no explanation):\n{\"detected_type\": \"brief description of document type\", \"confidence\": \"high|medium|low\", \"match\": true|false|\"unsure\"}\n\nWhere:\n- detected_type: what type of document this appears to be (e.g., bank statement, identity card, invoice, selfie)\n- confidence: your confidence in the classification\n- match: whether this document matches what was requested (field label: \"%s\")",
		$field_label,
		$field_label
	);

	$content_block = $is_image
		? [
			'type'   => 'image',
			'source' => [ 'type' => 'base64', 'media_type' => $mime_type, 'data' => $base64_data ],
		]
		: [
			'type'   => 'document',
			'source' => [ 'type' => 'base64', 'media_type' => 'application/pdf', 'data' => $base64_data ],
		];

	$request_body = [
		'model'      => 'claude-haiku-4-5-20251001',
		'max_tokens' => 256,
		'messages'   => [
			[
				'role'    => 'user',
				'content' => [
					$content_block,
					[ 'type' => 'text', 'text' => $prompt ],
				],
			],
		],
	];

	$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
		'timeout' => 60,
		'headers' => [
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
			'content-type'      => 'application/json',
		],
		'body'    => wp_json_encode( $request_body ),
	] );

	if ( is_wp_error( $response ) ) {
		return [ 'error' => $response->get_error_message() ];
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code !== 200 ) {
		$msg = $body['error']['message'] ?? 'Anthropic API error';
		return [ 'error' => $msg ];
	}

	$raw_text = $body['content'][0]['text'] ?? '';
	$parsed   = json_decode( $raw_text, true );

	if ( ! is_array( $parsed ) ) {
		// Try to extract JSON substring from the response text
		if ( preg_match( '/\{[^{}]+\}/', $raw_text, $matches ) ) {
			$parsed = json_decode( $matches[0], true );
		}
	}

	if ( ! is_array( $parsed ) || ! isset( $parsed['detected_type'] ) ) {
		return [ 'error' => 'invalid_response', 'raw' => mb_substr( $raw_text, 0, 300 ) ];
	}

	return [
		'detected_type' => (string) ( $parsed['detected_type'] ?? '' ),
		'confidence'    => (string) ( $parsed['confidence'] ?? 'low' ),
		'match'         => $parsed['match'] ?? 'unsure',
	];
}

// ---------------------------------------------------------------------------
// Admin meta box
// ---------------------------------------------------------------------------

add_action( 'add_meta_boxes', 'xpressui_pro_register_ai_verify_metabox', 10, 2 );

function xpressui_pro_register_ai_verify_metabox( string $post_type, $post ): void {
	if ( $post_type !== 'xpressui_submission' || ! ( $post instanceof WP_Post ) ) {
		return;
	}

	$slug         = (string) get_post_meta( $post->ID, '_xpressui_project_slug', true );
	$all_settings = get_option( 'xpressui_project_settings', [] );
	$s            = is_array( $all_settings[ $slug ] ?? null ) ? $all_settings[ $slug ] : [];

	if ( ! filter_var( $s['proAiVerifyEnabled'] ?? false, FILTER_VALIDATE_BOOLEAN ) ) {
		return;
	}

	add_meta_box(
		'xpressui_pro_ai_verify_mb',
		__( 'AI Document Verification', 'xpressui-wordpress-bridge-pro' ),
		'xpressui_pro_render_ai_verify_metabox',
		'xpressui_submission',
		'side',
		'default'
	);
}

function xpressui_pro_render_ai_verify_metabox( WP_Post $post ): void {
	$status      = (string) get_post_meta( $post->ID, '_xpressui_ai_verify_status', true );
	$results_raw = get_post_meta( $post->ID, '_xpressui_ai_verify', true );
	$results     = is_string( $results_raw ) ? json_decode( $results_raw, true ) : null;

	if ( '' === $status || 'running' === $status ) {
		echo '<p style="color:#64748b;font-size:12px;">' . esc_html__( 'Verification in progress…', 'xpressui-wordpress-bridge-pro' ) . '</p>';
		return;
	}

	if ( 'no_files' === $status ) {
		echo '<p style="color:#64748b;font-size:12px;">' . esc_html__( 'No uploaded files to verify.', 'xpressui-wordpress-bridge-pro' ) . '</p>';
		return;
	}

	if ( ! is_array( $results ) || empty( $results ) ) {
		echo '<p style="color:#64748b;font-size:12px;">' . esc_html__( 'No verification results.', 'xpressui-wordpress-bridge-pro' ) . '</p>';
		return;
	}

	foreach ( $results as $field_name => $result ) {
		$field_label = esc_html( ucwords( str_replace( [ '_', '-' ], ' ', (string) $field_name ) ) );

		echo '<div style="margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid #f1f5f9;">';
		echo '<div style="font-size:11px;font-weight:600;color:#475569;margin-bottom:4px;">' . $field_label . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! is_array( $result ) ) {
			echo '<span style="color:#94a3b8;font-size:11px;">—</span>';
		} elseif ( isset( $result['skipped'] ) ) {
			echo '<span style="color:#94a3b8;font-size:11px;">' . esc_html__( '⏭ File type not supported', 'xpressui-wordpress-bridge-pro' ) . '</span>';
		} elseif ( isset( $result['error'] ) ) {
			echo '<span style="color:#ef4444;font-size:11px;">⚠ ' . esc_html( (string) $result['error'] ) . '</span>';
		} else {
			$match      = $result['match'] ?? 'unsure';
			$confidence = ucfirst( (string) ( $result['confidence'] ?? 'low' ) );
			$detected   = (string) ( $result['detected_type'] ?? '' );

			if ( $match === true || $match === 'true' || $match === 1 ) {
				$color = '#16a34a';
				$icon  = '✓';
			} elseif ( $match === false || $match === 'false' || $match === 0 ) {
				$color = '#dc2626';
				$icon  = '✗';
			} else {
				$color = '#d97706';
				$icon  = '?';
			}

			echo '<span style="background:' . esc_attr( $color ) . ';color:#fff;border-radius:4px;padding:2px 6px;font-size:11px;font-weight:600;">'
				. esc_html( $icon . ' ' . $detected )
				. '</span>';
			echo '<span style="color:#64748b;font-size:11px;margin-left:5px;">'
				. esc_html( $confidence . ' ' . __( 'confidence', 'xpressui-wordpress-bridge-pro' ) )
				. '</span>';
		}

		echo '</div>';
	}
}

// ---------------------------------------------------------------------------
// Workflow Settings — render section
// ---------------------------------------------------------------------------

add_action( 'xpressui_workflow_settings_extra_sections', 'xpressui_pro_render_ai_verify_section', 25, 3 );

function xpressui_pro_render_ai_verify_section( string $slug, array $s, $overlay ): void {
	$enabled = filter_var( $s['proAiVerifyEnabled'] ?? false, FILTER_VALIDATE_BOOLEAN );
	$api_key = (string) ( $s['proAiVerifyApiKey'] ?? '' );

	echo '<div class="card xpressui-admin-card">';
	echo '<details><summary><h2>' . esc_html__( 'AI Document Verification', 'xpressui-wordpress-bridge-pro' ) . '</h2><span class="xpressui-toggle-icon" aria-hidden="true">▾</span></summary>';
	echo '<p>' . esc_html__( 'Automatically classify uploaded documents using Anthropic Claude. Results appear in the submission detail page. Images and PDFs only. Each analysis costs approximately $0.001.', 'xpressui-wordpress-bridge-pro' ) . '</p>';
	echo '<table class="form-table"><tbody>';

	echo '<tr><th><label for="xpressui_ai_verify_enabled">' . esc_html__( 'Enable AI verification', 'xpressui-wordpress-bridge-pro' ) . '</label></th>';
	echo '<td><input type="checkbox" id="xpressui_ai_verify_enabled" name="xpressui_ai_verify_enabled" value="1"' . checked( $enabled, true, false ) . '>';
	echo '<p class="description">' . esc_html__( 'Classify each uploaded image or PDF when a new submission arrives.', 'xpressui-wordpress-bridge-pro' ) . '</p></td></tr>';

	echo '<tr><th><label for="xpressui_ai_verify_api_key">' . esc_html__( 'Anthropic API key', 'xpressui-wordpress-bridge-pro' ) . '</label></th>';
	echo '<td><input type="password" id="xpressui_ai_verify_api_key" name="xpressui_ai_verify_api_key" class="regular-text" value="' . esc_attr( $api_key ) . '" placeholder="sk-ant-…" autocomplete="new-password">';
	echo '<p class="description">' . esc_html__( 'Used server-side only. Never exposed to the browser. Obtain your key at console.anthropic.com.', 'xpressui-wordpress-bridge-pro' ) . '</p></td></tr>';

	echo '</tbody></table>';
	echo '</details>';
	echo '</div>';
}

// ---------------------------------------------------------------------------
// Workflow Settings — save handler
// ---------------------------------------------------------------------------

add_action( 'xpressui_workflow_settings_extra_save', 'xpressui_pro_save_ai_verify_settings', 25 );

function xpressui_pro_save_ai_verify_settings( string $slug ): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by caller
	$enabled = ! empty( $_POST['xpressui_ai_verify_enabled'] );
	$api_key = sanitize_text_field( wp_unslash( (string) ( $_POST['xpressui_ai_verify_api_key'] ?? '' ) ) );
	// phpcs:enable

	$all_settings = get_option( 'xpressui_project_settings', [] );
	if ( ! is_array( $all_settings ) ) {
		$all_settings = [];
	}
	if ( ! isset( $all_settings[ $slug ] ) || ! is_array( $all_settings[ $slug ] ) ) {
		$all_settings[ $slug ] = [];
	}

	$all_settings[ $slug ]['proAiVerifyEnabled'] = $enabled ? '1' : '0';
	if ( '' !== $api_key ) {
		$all_settings[ $slug ]['proAiVerifyApiKey'] = $api_key;
	}

	update_option( 'xpressui_project_settings', $all_settings );
}
