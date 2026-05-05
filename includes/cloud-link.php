<?php
/**
 * Cloud Link (PRO-only): opt-in bridge to SaaS services.
 *
 * @package XPressUI-Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

defined( 'XPRESSUI_PRO_CLOUD_LINK_OPTION_KEY' ) || define( 'XPRESSUI_PRO_CLOUD_LINK_OPTION_KEY', 'xpressui_pro_cloud_link' );
defined( 'XPRESSUI_PRO_CLOUD_LINK_QUEUE_OPTION_KEY' ) || define( 'XPRESSUI_PRO_CLOUD_LINK_QUEUE_OPTION_KEY', 'xpressui_pro_cloud_link_event_queue' );
defined( 'XPRESSUI_PRO_CLOUD_LINK_REGISTER_PATH' ) || define( 'XPRESSUI_PRO_CLOUD_LINK_REGISTER_PATH', '/api/v1/bridge/register' );
defined( 'XPRESSUI_PRO_CLOUD_LINK_EVENTS_PATH' ) || define( 'XPRESSUI_PRO_CLOUD_LINK_EVENTS_PATH', '/api/v1/bridge/events/batch' );
defined( 'XPRESSUI_PRO_CLOUD_LINK_HEALTH_PATH' ) || define( 'XPRESSUI_PRO_CLOUD_LINK_HEALTH_PATH', '/api/v1/bridge/health' );

add_action( 'admin_menu', 'xpressui_pro_register_cloud_link_page' );
add_action( 'admin_post_xpressui_pro_cloud_link_save', 'xpressui_pro_handle_cloud_link_save' );
add_action( 'admin_post_xpressui_pro_cloud_link_test', 'xpressui_pro_handle_cloud_link_test' );
add_action( 'xpressui_submission_event_recorded', 'xpressui_pro_cloud_link_enqueue_submission_event', 10, 2 );
add_action( 'xpressui_submission_first_created', 'xpressui_pro_cloud_link_dispatch_channels', 10, 3 );
add_action( 'xpressui_pro_cloud_link_flush_events', 'xpressui_pro_cloud_link_flush_events' );
add_action( 'xpressui_pro_cloud_link_dispatch_channel', 'xpressui_pro_cloud_link_dispatch_channel_worker', 10, 3 );

function xpressui_pro_register_cloud_link_page(): void {
	add_submenu_page(
		'edit.php?post_type=xpressui_submission',
		__( 'Cloud Link', 'xpressui-wordpress-bridge-pro' ),
		__( 'Cloud Link', 'xpressui-wordpress-bridge-pro' ),
		'manage_options',
		'xpressui-pro-cloud-link',
		'xpressui_pro_render_cloud_link_page'
	);
}

function xpressui_pro_get_cloud_link_settings(): array {
	$defaults = [
		'enabled'       => false,
		'api_base_url'  => 'https://xpressui.iakpress.com',
		'workspace_id'  => '',
		'api_token'     => '',
		'site_id'       => '',
		'shared_secret' => '',
		'status'        => 'offline',
		'last_error'    => '',
		'last_seen_at'  => '',
	];
	$stored = get_option( XPRESSUI_PRO_CLOUD_LINK_OPTION_KEY, [] );
	$stored = is_array( $stored ) ? $stored : [];
	return wp_parse_args( $stored, $defaults );
}

function xpressui_pro_save_cloud_link_settings( array $settings ): void {
	update_option( XPRESSUI_PRO_CLOUD_LINK_OPTION_KEY, $settings, false );
}

function xpressui_pro_cloud_link_site_fingerprint_hash(): string {
	$raw = home_url( '/' ) . '|' . wp_salt();
	return hash( 'sha256', $raw );
}

function xpressui_pro_cloud_link_sign_payload( string $method, string $path, string $timestamp, string $site_id, string $body, string $shared_secret ): string {
	$body_hash = hash( 'sha256', $body );
	$canonical = strtoupper( $method ) . "\n" . $path . "\n" . $timestamp . "\n" . $site_id . "\n" . $body_hash;
	return 'v1=' . hash_hmac( 'sha256', $canonical, $shared_secret );
}

function xpressui_pro_cloud_link_api_request( string $method, string $path, array $payload, array $settings, bool $signed = false, array $extra_headers = [] ) {
	$base_url = rtrim( (string) ( $settings['api_base_url'] ?? '' ), '/' );
	if ( $base_url === '' ) {
		return new WP_Error( 'xpressui_pro_cloud_link_missing_base_url', __( 'Cloud Link base URL is missing.', 'xpressui-wordpress-bridge-pro' ) );
	}

	$url     = $base_url . $path;
	$method  = strtoupper( $method );
	$body    = ( 'GET' === $method ) ? '' : wp_json_encode( $payload );
	$headers = [
		'Content-Type' => 'application/json',
		'Accept'       => 'application/json',
	];

	if ( $signed ) {
		$site_id       = (string) ( $settings['site_id'] ?? '' );
		$shared_secret = (string) ( $settings['shared_secret'] ?? '' );
		if ( $site_id === '' || $shared_secret === '' ) {
			return new WP_Error( 'xpressui_pro_cloud_link_missing_signature_setup', __( 'Cloud Link site identity is not configured.', 'xpressui-wordpress-bridge-pro' ) );
		}
		$timestamp                    = (string) time();
		$headers['X-Bridge-Site-Id']  = $site_id;
		$headers['X-Bridge-Timestamp'] = $timestamp;
		$headers['X-Bridge-Signature'] = xpressui_pro_cloud_link_sign_payload( $method, $path, $timestamp, $site_id, (string) $body, $shared_secret );
	} else {
		$api_token = trim( (string) ( $settings['api_token'] ?? '' ) );
		if ( $api_token === '' ) {
			return new WP_Error( 'xpressui_pro_cloud_link_missing_api_token', __( 'Cloud Link API token is required.', 'xpressui-wordpress-bridge-pro' ) );
		}
		$headers['X-Api-Token'] = $api_token;
	}

	$args = [
		'method'  => $method,
		'timeout' => 12,
		'headers' => array_merge( $headers, $extra_headers ),
	];
	if ( 'GET' !== $method ) {
		$args['body'] = $body;
	}

	return wp_remote_request(
		$url,
		$args
	);
}

function xpressui_pro_cloud_link_register( array &$settings ) {
	$payload = [
		'workspaceId'         => (string) ( $settings['workspace_id'] ?? '' ),
		'siteUrl'             => home_url( '/' ),
		'siteFingerprintHash' => xpressui_pro_cloud_link_site_fingerprint_hash(),
		'pluginVersion'       => defined( 'XPRESSUI_PRO_VERSION' ) ? XPRESSUI_PRO_VERSION : 'unknown',
		'runtimeVersion'      => defined( 'XPRESSUI_PRO_RUNTIME_VERSION' ) ? XPRESSUI_PRO_RUNTIME_VERSION : 'unknown',
	];
	$response = xpressui_pro_cloud_link_api_request( 'POST', XPRESSUI_PRO_CLOUD_LINK_REGISTER_PATH, $payload, $settings, false );
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
		return new WP_Error( 'xpressui_pro_cloud_link_register_failed', __( 'Cloud Link registration failed.', 'xpressui-wordpress-bridge-pro' ) );
	}
	$settings['site_id'] = sanitize_text_field( (string) ( $data['siteId'] ?? '' ) );
	$settings['shared_secret'] = sanitize_text_field( (string) ( $data['sharedSecret'] ?? '' ) );
	$settings['status'] = sanitize_key( (string) ( $data['status'] ?? 'connected' ) );
	$settings['last_error'] = '';
	$settings['last_seen_at'] = gmdate( 'Y-m-d\TH:i:s\Z' );
	return true;
}

function xpressui_pro_handle_cloud_link_save(): void {
	check_admin_referer( 'xpressui_pro_cloud_link_save', 'xpressui_pro_cloud_link_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'xpressui-wordpress-bridge-pro' ) );
	}
	$settings = xpressui_pro_get_cloud_link_settings();
	$settings['enabled'] = isset( $_POST['xpressui_pro_cloud_link_enabled'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$settings['api_base_url'] = esc_url_raw( wp_unslash( (string) ( $_POST['xpressui_pro_cloud_link_api_base_url'] ?? $settings['api_base_url'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$settings['workspace_id'] = sanitize_text_field( wp_unslash( (string) ( $_POST['xpressui_pro_cloud_link_workspace_id'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$settings['api_token'] = sanitize_text_field( wp_unslash( (string) ( $_POST['xpressui_pro_cloud_link_api_token'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

	xpressui_pro_save_cloud_link_settings( $settings );
	wp_safe_redirect(
		add_query_arg(
			[
				'post_type' => 'xpressui_submission',
				'page'      => 'xpressui-pro-cloud-link',
				'updated'   => '1',
			],
			admin_url( 'edit.php' )
		)
	);
	exit;
}

function xpressui_pro_handle_cloud_link_test(): void {
	check_admin_referer( 'xpressui_pro_cloud_link_test', 'xpressui_pro_cloud_link_test_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'xpressui-wordpress-bridge-pro' ) );
	}
	$settings = xpressui_pro_get_cloud_link_settings();
	$register = xpressui_pro_cloud_link_register( $settings );
	if ( is_wp_error( $register ) ) {
		$settings['status'] = 'offline';
		$settings['last_error'] = $register->get_error_message();
		xpressui_pro_save_cloud_link_settings( $settings );
		wp_safe_redirect(
			add_query_arg(
				[
					'post_type' => 'xpressui_submission',
					'page'      => 'xpressui-pro-cloud-link',
					'error'     => rawurlencode( $register->get_error_message() ),
				],
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	$health_response = xpressui_pro_cloud_link_api_request( 'GET', XPRESSUI_PRO_CLOUD_LINK_HEALTH_PATH, [], $settings, true );
	if ( is_wp_error( $health_response ) ) {
		$settings['status'] = 'degraded';
		$settings['last_error'] = $health_response->get_error_message();
	} else {
		$code = (int) wp_remote_retrieve_response_code( $health_response );
		$settings['status'] = ( $code >= 200 && $code < 300 ) ? 'connected' : 'degraded';
		$settings['last_error'] = ( $code >= 200 && $code < 300 ) ? '' : 'Health check failed';
	}
	$settings['last_seen_at'] = gmdate( 'Y-m-d\TH:i:s\Z' );
	xpressui_pro_save_cloud_link_settings( $settings );
	wp_safe_redirect(
		add_query_arg(
			[
				'post_type' => 'xpressui_submission',
				'page'      => 'xpressui-pro-cloud-link',
				'connected' => '1',
			],
			admin_url( 'edit.php' )
		)
	);
	exit;
}

function xpressui_pro_render_cloud_link_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'xpressui-wordpress-bridge-pro' ) );
	}
	$settings = xpressui_pro_get_cloud_link_settings();
	echo '<div class="wrap"><h1>' . esc_html__( 'Cloud Link (PRO)', 'xpressui-wordpress-bridge-pro' ) . '</h1>';
	if ( isset( $_GET['updated'] ) ) {
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Cloud Link settings saved.', 'xpressui-wordpress-bridge-pro' ) . '</p></div>';
	}
	if ( isset( $_GET['connected'] ) ) {
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Cloud Link connection succeeded.', 'xpressui-wordpress-bridge-pro' ) . '</p></div>';
	}
	if ( isset( $_GET['error'] ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( (string) $_GET['error'] ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	echo '<p>' . esc_html__( 'Optional cloud services for dispatch and telemetry. Local standalone mode remains available when disabled.', 'xpressui-wordpress-bridge-pro' ) . '</p>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'xpressui_pro_cloud_link_save', 'xpressui_pro_cloud_link_nonce' );
	echo '<input type="hidden" name="action" value="xpressui_pro_cloud_link_save" />';
	echo '<table class="form-table"><tbody>';
	echo '<tr><th scope="row">' . esc_html__( 'Enable Cloud Link', 'xpressui-wordpress-bridge-pro' ) . '</th><td><label><input type="checkbox" name="xpressui_pro_cloud_link_enabled" value="1" ' . checked( ! empty( $settings['enabled'] ), true, false ) . ' /> ' . esc_html__( 'Enable (opt-in)', 'xpressui-wordpress-bridge-pro' ) . '</label></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'API base URL', 'xpressui-wordpress-bridge-pro' ) . '</th><td><input type="url" class="regular-text" name="xpressui_pro_cloud_link_api_base_url" value="' . esc_attr( (string) $settings['api_base_url'] ) . '" /></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Workspace ID', 'xpressui-wordpress-bridge-pro' ) . '</th><td><input type="text" class="regular-text" name="xpressui_pro_cloud_link_workspace_id" value="' . esc_attr( (string) $settings['workspace_id'] ) . '" /></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'API token', 'xpressui-wordpress-bridge-pro' ) . '</th><td><input type="password" class="regular-text" name="xpressui_pro_cloud_link_api_token" value="' . esc_attr( (string) $settings['api_token'] ) . '" autocomplete="off" /></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Status', 'xpressui-wordpress-bridge-pro' ) . '</th><td><code>' . esc_html( (string) $settings['status'] ) . '</code></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Last error', 'xpressui-wordpress-bridge-pro' ) . '</th><td>' . esc_html( (string) $settings['last_error'] ) . '</td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Last seen', 'xpressui-wordpress-bridge-pro' ) . '</th><td>' . esc_html( (string) $settings['last_seen_at'] ) . '</td></tr>';
	echo '</tbody></table>';
	submit_button( __( 'Save Cloud Link settings', 'xpressui-wordpress-bridge-pro' ) );
	echo '</form>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:16px;">';
	wp_nonce_field( 'xpressui_pro_cloud_link_test', 'xpressui_pro_cloud_link_test_nonce' );
	echo '<input type="hidden" name="action" value="xpressui_pro_cloud_link_test" />';
	submit_button( __( 'Test connection', 'xpressui-wordpress-bridge-pro' ), 'secondary', 'submit', false );
	echo '</form></div>';
}

function xpressui_pro_cloud_link_enqueue_submission_event( $post_id, $event ): void {
	$settings = xpressui_pro_get_cloud_link_settings();
	if ( empty( $settings['enabled'] ) || empty( $settings['site_id'] ) || empty( $settings['shared_secret'] ) ) {
		return;
	}
	$event = is_array( $event ) ? $event : [];
	if ( empty( $event ) ) {
		return;
	}
	$queue = get_option( XPRESSUI_PRO_CLOUD_LINK_QUEUE_OPTION_KEY, [] );
	$queue = is_array( $queue ) ? $queue : [];

	$queue[] = [
		'eventId'      => sanitize_text_field( (string) ( $event['eventId'] ?? wp_generate_uuid4() ) ),
		'occurredAt'   => sanitize_text_field( (string) ( $event['occurredAt'] ?? gmdate( 'Y-m-d\TH:i:s\Z' ) ) ),
		'source'       => 'bridge',
		'submissionId' => sanitize_text_field( (string) get_post_meta( (int) $post_id, '_xpressui_submission_id', true ) ),
		'projectId'    => sanitize_text_field( (string) get_post_meta( (int) $post_id, '_xpressui_project_id', true ) ),
		'projectSlug'  => sanitize_text_field( (string) get_post_meta( (int) $post_id, '_xpressui_project_slug', true ) ),
		'channel'      => 'web',
		'eventType'    => sanitize_text_field( (string) ( $event['eventType'] ?? '' ) ),
		'metrics'      => is_array( $event['metrics'] ?? null ) ? $event['metrics'] : [],
		'context'      => is_array( $event['context'] ?? null ) ? $event['context'] : [],
	];

	if ( count( $queue ) > 200 ) {
		$queue = array_slice( $queue, -200 );
	}

	update_option( XPRESSUI_PRO_CLOUD_LINK_QUEUE_OPTION_KEY, $queue, false );
	if ( ! wp_next_scheduled( 'xpressui_pro_cloud_link_flush_events' ) ) {
		wp_schedule_single_event( time() + 10, 'xpressui_pro_cloud_link_flush_events' );
	}
}

function xpressui_pro_cloud_link_flush_events(): void {
	$settings = xpressui_pro_get_cloud_link_settings();
	if ( empty( $settings['enabled'] ) || empty( $settings['site_id'] ) || empty( $settings['shared_secret'] ) ) {
		return;
	}

	$queue = get_option( XPRESSUI_PRO_CLOUD_LINK_QUEUE_OPTION_KEY, [] );
	$queue = is_array( $queue ) ? $queue : [];
	if ( empty( $queue ) ) {
		return;
	}

	$batch = array_slice( $queue, 0, 20 );
	$resp  = xpressui_pro_cloud_link_api_request( 'POST', XPRESSUI_PRO_CLOUD_LINK_EVENTS_PATH, [ 'events' => $batch ], $settings, true );
	if ( is_wp_error( $resp ) ) {
		$settings['status'] = 'degraded';
		$settings['last_error'] = $resp->get_error_message();
		xpressui_pro_save_cloud_link_settings( $settings );
		return;
	}

	$code = (int) wp_remote_retrieve_response_code( $resp );
	if ( $code >= 200 && $code < 300 ) {
		$settings['status'] = 'connected';
		$settings['last_error'] = '';
		$settings['last_seen_at'] = gmdate( 'Y-m-d\TH:i:s\Z' );
		xpressui_pro_save_cloud_link_settings( $settings );
		$remaining = array_slice( $queue, count( $batch ) );
		update_option( XPRESSUI_PRO_CLOUD_LINK_QUEUE_OPTION_KEY, $remaining, false );
		if ( ! empty( $remaining ) ) {
			wp_schedule_single_event( time() + 10, 'xpressui_pro_cloud_link_flush_events' );
		}
		return;
	}

	$settings['status'] = 'degraded';
	$settings['last_error'] = 'Cloud event batch rejected (' . $code . ')';
	xpressui_pro_save_cloud_link_settings( $settings );
}

/**
 * Fire-and-forget cloud dispatch for first submission creation.
 * Local delivery remains active in the free bridge; this call is additive.
 *
 * @param int    $post_id Submission post ID.
 * @param string $project_slug Workflow slug.
 * @param array  $payload Submission payload.
 */
function xpressui_pro_cloud_link_dispatch_channels( $post_id, $project_slug, $payload ): void {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return;
	}
	$settings = xpressui_pro_get_cloud_link_settings();
	if ( empty( $settings['enabled'] ) || empty( $settings['site_id'] ) || empty( $settings['shared_secret'] ) ) {
		return;
	}

	$channels = [];
	if ( function_exists( 'xpressui_get_project_setting' ) ) {
		$notify_email = (string) xpressui_get_project_setting( (string) $project_slug, 'notifyEmail' );
		$webhook_url  = (string) xpressui_get_project_setting( (string) $project_slug, 'webhookUrl' );
		if ( $notify_email !== '' ) {
			$channels[] = 'email';
		}
		if ( $webhook_url !== '' ) {
			$channels[] = 'webhook';
		}
	}

	$submission_id = (string) get_post_meta( $post_id, '_xpressui_submission_id', true );
	$payload       = is_array( $payload ) ? $payload : [];
	foreach ( $channels as $channel ) {
		$status_key = '_xpressui_cloud_dispatch_' . $channel . '_status';
		$error_key  = '_xpressui_cloud_dispatch_' . $channel . '_error';
		update_post_meta( $post_id, $status_key, 'cloud_queued' );
		update_post_meta( $post_id, $error_key, '' );
		wp_schedule_single_event(
			time() + 1,
			'xpressui_pro_cloud_link_dispatch_channel',
			[
				(int) $post_id,
				(string) $project_slug,
				(string) $channel,
			]
		);
	}
}

/**
 * Run a cloud dispatch job asynchronously (WP-Cron).
 *
 * @param int    $post_id Submission post ID.
 * @param string $project_slug Workflow slug.
 * @param string $channel Delivery channel.
 */
function xpressui_pro_cloud_link_dispatch_channel_worker( $post_id, $project_slug, $channel ): void {
	$post_id = (int) $post_id;
	$channel = (string) $channel;
	if ( $post_id <= 0 || '' === $channel ) {
		return;
	}

	$settings = xpressui_pro_get_cloud_link_settings();
	if ( empty( $settings['enabled'] ) || empty( $settings['site_id'] ) || empty( $settings['shared_secret'] ) ) {
		return;
	}

	$submission_id = (string) get_post_meta( $post_id, '_xpressui_submission_id', true );
	if ( '' === $submission_id ) {
		$submission_id = 'post_' . $post_id;
	}
	$dispatch_payload = [
		'submissionId' => $submission_id,
		'projectSlug'  => (string) $project_slug,
		'channel'      => $channel,
		'payload'      => [
			'postId' => $post_id,
		],
	];
	$idempotency_key = 'site:' . (string) $settings['site_id'] . '|submission:' . $submission_id . '|channel:' . $channel;
	$resp            = xpressui_pro_cloud_link_api_request(
		'POST',
		'/api/v1/bridge/dispatch',
		$dispatch_payload,
		$settings,
		true,
		[
			'X-Bridge-Idempotency-Key' => $idempotency_key,
		]
	);
	if ( is_wp_error( $resp ) ) {
		update_post_meta( $post_id, '_xpressui_cloud_dispatch_' . $channel . '_status', 'cloud_failed_fallback_local' );
		update_post_meta( $post_id, '_xpressui_cloud_dispatch_' . $channel . '_error', $resp->get_error_message() );
		if ( function_exists( 'xpressui_record_submission_event' ) ) {
			xpressui_record_submission_event(
				$post_id,
				'delivery.cloud_dispatch_failed_fallback_local',
				'bridge',
				[],
				[
					'channel' => (string) $channel,
					'error'   => (string) $resp->get_error_message(),
				]
			);
		}
		return;
	}

	$code = (int) wp_remote_retrieve_response_code( $resp );
	if ( $code >= 200 && $code < 300 ) {
		update_post_meta( $post_id, '_xpressui_cloud_dispatch_' . $channel . '_status', 'cloud_queued' );
		update_post_meta( $post_id, '_xpressui_cloud_dispatch_' . $channel . '_error', '' );
		return;
	}

	update_post_meta( $post_id, '_xpressui_cloud_dispatch_' . $channel . '_status', 'cloud_failed_fallback_local' );
	update_post_meta( $post_id, '_xpressui_cloud_dispatch_' . $channel . '_error', 'dispatch_http_' . $code );
}
