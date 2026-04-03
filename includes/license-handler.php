<?php
/**
 * Handles the logic for the XPressUI Pro license activation and validation.
 *
 * Security model:
 * - The API signs license validation responses with a private key.
 * - This plugin verifies the signature using a bundled public key.
 * - The plugin stores the signed payload locally and never trusts unsigned status values.
 *
 * @package XPressUI-Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The API endpoint for license verification.
 */
defined( 'XPRESSUI_PRO_LICENSE_API_URL' )             || define( 'XPRESSUI_PRO_LICENSE_API_URL', 'https://xpressui.iakpress.com/api/v1/licenses/verify' );
defined( 'XPRESSUI_PRO_LICENSE_OPTION_KEY' )          || define( 'XPRESSUI_PRO_LICENSE_OPTION_KEY', 'xpressui_pro_license_data' );
defined( 'XPRESSUI_PRO_PRODUCT_ID' )                  || define( 'XPRESSUI_PRO_PRODUCT_ID', 'xpressui-wordpress-bridge-pro' );
defined( 'XPRESSUI_PRO_PUBLIC_KEY_PATH' )             || define( 'XPRESSUI_PRO_PUBLIC_KEY_PATH', dirname( __DIR__ ) . '/keys/license_signing_public.pem' );
defined( 'XPRESSUI_PRO_LICENSE_STATUS_TRANSIENT' )    || define( 'XPRESSUI_PRO_LICENSE_STATUS_TRANSIENT', 'xpressui_pro_license_status' );
defined( 'XPRESSUI_PRO_LICENSE_STATUS_TRANSIENT_TTL' ) || define( 'XPRESSUI_PRO_LICENSE_STATUS_TRANSIENT_TTL', 6 * HOUR_IN_SECONDS );
defined( 'XPRESSUI_PRO_LICENSE_GRACE_PERIOD' )        || define( 'XPRESSUI_PRO_LICENSE_GRACE_PERIOD', 3 * DAY_IN_SECONDS );


// L'action est enregistrée directement pour éviter tout problème de timing d'inclusion.
add_action( 'admin_post_xpressui_pro_license_actions', 'xpressui_pro_handle_license_form_submission' );

/**
 * Handles the submission of the license activation/deactivation form.
 *
 * @return void
 */
function xpressui_pro_handle_license_form_submission() {
	// Use the standard WordPress nonce verification helper. It will die with a 403 error if the check fails.
	check_admin_referer( 'xpressui_pro_license_actions', 'xpressui_pro_license_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage licenses.', 'xpressui-wordpress-bridge-pro' ) );
	}

	$base_redirect_url = add_query_arg(
		[
			'post_type' => 'xpressui_submission',
			'page'      => 'xpressui-bridge',
		],
		admin_url( 'edit.php' )
	);

	if ( isset( $_POST['xpressui_pro_deactivate'] ) ) {
		delete_option( XPRESSUI_PRO_LICENSE_OPTION_KEY );
		delete_transient( XPRESSUI_PRO_LICENSE_STATUS_TRANSIENT );

		$redirect_url = add_query_arg(
			[
				'xpressui_notice'      => rawurlencode( __( 'License deactivated.', 'xpressui-wordpress-bridge-pro' ) ),
				'xpressui_notice_type' => 'success',
			],
			$base_redirect_url
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	if ( isset( $_POST['xpressui_pro_activate'] ) && ! empty( $_POST['xpressui_pro_license_key'] ) ) {
		$license_key = sanitize_text_field( wp_unslash( $_POST['xpressui_pro_license_key'] ) );

		$result = xpressui_pro_fetch_and_verify_license_from_api( $license_key );

		if ( is_wp_error( $result ) ) {
			$redirect_url = add_query_arg(
				[
					'xpressui_notice'      => rawurlencode( $result->get_error_message() ),
					'xpressui_notice_type' => 'error',
				],
				$base_redirect_url
			);
		} else {
			update_option( XPRESSUI_PRO_LICENSE_OPTION_KEY, $result );
			delete_transient( XPRESSUI_PRO_LICENSE_STATUS_TRANSIENT );

			$redirect_url = add_query_arg(
				[
					'xpressui_notice'      => rawurlencode( __( 'License activated successfully!', 'xpressui-wordpress-bridge-pro' ) ),
					'xpressui_notice_type' => 'success',
				],
				$base_redirect_url
			);
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}
}

/**
 * Fetches a signed response from the API and verifies it.
 *
 * @param string $license_key The license key entered by the user.
 * @return array|WP_Error Verified and normalized license data, or WP_Error on failure.
 */
function xpressui_pro_fetch_and_verify_license_from_api( $license_key ) {
	$site_url = xpressui_pro_normalize_site_url( get_site_url() );

	$api_response = wp_remote_post(
		XPRESSUI_PRO_LICENSE_API_URL,
		[
			'timeout' => 15,
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
			'body'    => wp_json_encode(
				[
					'licenseKey' => $license_key,
					'siteUrl'    => $site_url,
					'productId'  => XPRESSUI_PRO_PRODUCT_ID,
				]
			),
		]
	);

	// --- DEBUGGING: Log API response ---
	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		$log_message = is_wp_error( $api_response ) ? 'WP_Error: ' . $api_response->get_error_message() : 'Response Body: ' . wp_remote_retrieve_body( $api_response );
		error_log( '[XPressUI Pro] License API Response: ' . $log_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional debug logging, guarded by WP_DEBUG_LOG
	}
	// --- END DEBUGGING ---

	if ( is_wp_error( $api_response ) ) {
		return new WP_Error(
			'xpressui_pro_api_error',
			__( 'API connection error: ', 'xpressui-wordpress-bridge-pro' ) . $api_response->get_error_message()
		);
	}

	$status_code = wp_remote_retrieve_response_code( $api_response );
	$body        = wp_remote_retrieve_body( $api_response );
	$response    = json_decode( $body, true );

	if ( 200 !== (int) $status_code || ! is_array( $response ) ) {
		return new WP_Error(
			'xpressui_pro_invalid_api_response',
			__( 'Invalid response received from the license server.', 'xpressui-wordpress-bridge-pro' )
		);
	}

	$verified = xpressui_pro_verify_signed_license_response( $response, $site_url );

	if ( is_wp_error( $verified ) ) {
		return $verified;
	}

	$normalized = [
		'license_key'   => $license_key,
		'status'        => $verified['status'],
		'product_id'    => $verified['product_id'],
		'site_url'      => $verified['site_url'],
		'issued_at'     => $verified['issued_at'],
		'expires_at'    => $verified['expires_at'],
		'last_check'    => time(),
		'key_id'        => $response['key_id'],
		'alg'           => $response['alg'],
		'payload_b64'   => $response['payload_b64'],
		'signature_b64' => $response['signature_b64'],
	];

	if ( isset( $verified['license_key_hash'] ) ) {
		$normalized['license_key_hash'] = $verified['license_key_hash'];
	}

	return $normalized;
}

/**
 * Verifies the signed API response.
 *
 * Expected response structure:
 * - alg
 * - key_id
 * - payload_b64
 * - signature_b64
 *
 * @param array  $response      Raw API response.
 * @param string $expected_site Expected normalized site URL.
 * @return array|WP_Error Verified payload as array or WP_Error.
 */
function xpressui_pro_verify_signed_license_response( array $response, $expected_site ) {
	if (
		empty( $response['alg'] ) ||
		empty( $response['key_id'] ) ||
		empty( $response['payload_b64'] ) ||
		empty( $response['signature_b64'] )
	) {
		return new WP_Error(
			'xpressui_pro_missing_signature_fields',
			__( 'The license server response is incomplete.', 'xpressui-wordpress-bridge-pro' )
		);
	}

	$allowed_algorithms = [ 'RSA-SHA256' ];

	if ( ! in_array( $response['alg'], $allowed_algorithms, true ) ) {
		return new WP_Error(
			'xpressui_pro_unsupported_algorithm',
			__( 'Unsupported license signature algorithm.', 'xpressui-wordpress-bridge-pro' )
		);
	}

	$payload   = xpressui_pro_base64url_decode( $response['payload_b64'] );
	$signature = xpressui_pro_base64url_decode( $response['signature_b64'] );

	if ( false === $payload || false === $signature ) {
		return new WP_Error(
			'xpressui_pro_invalid_encoding',
			__( 'Invalid license response encoding.', 'xpressui-wordpress-bridge-pro' )
		);
	}

	$public_key_pem = xpressui_pro_get_public_key_pem();
	if ( is_wp_error( $public_key_pem ) ) {
		return $public_key_pem;
	}

	$public_key = openssl_pkey_get_public( $public_key_pem );
	if ( false === $public_key ) {
		return new WP_Error(
			'xpressui_pro_invalid_public_key',
			__( 'Unable to load the bundled public key.', 'xpressui-wordpress-bridge-pro' )
		);
	}

	$verify_result = openssl_verify( $payload, $signature, $public_key, OPENSSL_ALGO_SHA256 );

	if ( 1 !== $verify_result ) {
		return new WP_Error(
			'xpressui_pro_invalid_signature',
			__( 'License signature verification failed.', 'xpressui-wordpress-bridge-pro' )
		);
	}

	$data = json_decode( $payload, true );
	if ( ! is_array( $data ) ) {
		return new WP_Error(
			'xpressui_pro_invalid_payload',
			__( 'Invalid signed license payload.', 'xpressui-wordpress-bridge-pro' )
		);
	}

	$status     = isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : '';
	$product_id = isset( $data['product_id'] ) ? sanitize_text_field( $data['product_id'] ) : '';
	$site_url   = isset( $data['site_url'] ) ? xpressui_pro_normalize_site_url( $data['site_url'] ) : '';
	$issued_at  = isset( $data['issued_at'] ) ? sanitize_text_field( $data['issued_at'] ) : '';
	$expires_at = isset( $data['expires_at'] ) ? sanitize_text_field( $data['expires_at'] ) : '';

	if ( 'active' !== $status ) {
		return new WP_Error(
			'xpressui_pro_inactive_license',
			xpressui_pro_get_license_error_message( $status )
		);
	}

	if ( XPRESSUI_PRO_PRODUCT_ID !== $product_id ) {
		return new WP_Error(
			'xpressui_pro_wrong_product',
			__( 'This license does not belong to this product.', 'xpressui-wordpress-bridge-pro' )
		);
	}

	if ( $expected_site !== $site_url ) {
		return new WP_Error(
			'xpressui_pro_wrong_site',
			__( 'This license response does not match the current site.', 'xpressui-wordpress-bridge-pro' )
		);
	}

	if ( empty( $issued_at ) || empty( $expires_at ) ) {
		return new WP_Error(
			'xpressui_pro_missing_dates',
			__( 'The signed license response is missing required dates.', 'xpressui-wordpress-bridge-pro' )
		);
	}

	$issued_ts  = strtotime( $issued_at );
	$expires_ts = strtotime( $expires_at );

	if ( false === $issued_ts || false === $expires_ts ) {
		return new WP_Error(
			'xpressui_pro_invalid_dates',
			__( 'The signed license response contains invalid dates.', 'xpressui-wordpress-bridge-pro' )
		);
	}

	if ( $expires_ts < time() ) {
		return new WP_Error(
			'xpressui_pro_expired_payload',
			__( 'The signed license response has expired.', 'xpressui-wordpress-bridge-pro' )
		);
	}

	return $data;
}

/**
 * Returns the public key PEM content.
 *
 * @return string|WP_Error
 */
function xpressui_pro_get_public_key_pem() {
	if ( ! file_exists( XPRESSUI_PRO_PUBLIC_KEY_PATH ) ) {
		return new WP_Error(
			'xpressui_pro_missing_public_key',
			__( 'Public key file not found.', 'xpressui-wordpress-bridge-pro' )
		);
	}

	$key = file_get_contents( XPRESSUI_PRO_PUBLIC_KEY_PATH );

	if ( false === $key || '' === trim( $key ) ) {
		return new WP_Error(
			'xpressui_pro_empty_public_key',
			__( 'Public key file is empty or unreadable.', 'xpressui-wordpress-bridge-pro' )
		);
	}

	return $key;
}

/**
 * Decodes a base64url string.
 *
 * @param string $data Encoded value.
 * @return string|false
 */
function xpressui_pro_base64url_decode( $data ) {
	$data = strtr( $data, '-_', '+/' );
	$pad  = strlen( $data ) % 4;

	if ( $pad > 0 ) {
		$data .= str_repeat( '=', 4 - $pad );
	}

	return base64_decode( $data, true );
}

/**
 * Normalizes a site URL for strict comparison.
 *
 * @param string $url Raw URL.
 * @return string
 */
function xpressui_pro_normalize_site_url( $url ) {
	$url = trim( (string) $url );
	$url = untrailingslashit( $url );

	return strtolower( $url );
}

/**
 * Returns a user-friendly error message for a given license status.
 *
 * @param string $status The license status from the signed payload.
 * @return string
 */
function xpressui_pro_get_license_error_message( $status ) {
	switch ( $status ) {
		case 'invalid_key':
			return __( 'The license key is invalid.', 'xpressui-wordpress-bridge-pro' );
		case 'expired':
			return __( 'Your license key has expired.', 'xpressui-wordpress-bridge-pro' );
		case 'disabled':
			return __( 'Your license key has been disabled.', 'xpressui-wordpress-bridge-pro' );
		case 'site_inactive':
			return __( 'This site is not active for this license.', 'xpressui-wordpress-bridge-pro' );
		case 'max_sites_reached':
			return __( 'The maximum number of sites for this license has been reached.', 'xpressui-wordpress-bridge-pro' );
		case 'inactive':
			return __( 'The license is inactive.', 'xpressui-wordpress-bridge-pro' );
		case 'active':
			return __( 'License active.', 'xpressui-wordpress-bridge-pro' );
		default:
			return __( 'An unknown error occurred during license validation.', 'xpressui-wordpress-bridge-pro' );
	}
}

/**
 * Checks if the license is active.
 *
 * The function:
 * - prefers a short-lived transient for performance
 * - falls back to the stored signed payload
 * - re-validates with the API when needed
 * - allows a temporary grace period if the API is unavailable
 *
 * @return bool
 */
function xpressui_pro_is_license_active() {
	$cached = get_transient( XPRESSUI_PRO_LICENSE_STATUS_TRANSIENT );
	if ( false !== $cached ) {
		return 'active' === $cached;
	}

	$license_data = get_option( XPRESSUI_PRO_LICENSE_OPTION_KEY, [] );

	if ( empty( $license_data['license_key'] ) ) {
		set_transient( XPRESSUI_PRO_LICENSE_STATUS_TRANSIENT, 'inactive', HOUR_IN_SECONDS );
		return false;
	}

	/**
	 * First: trust only locally stored signed data if it is still valid.
	 */
	$local_verification = xpressui_pro_verify_stored_license_data( $license_data );
	if ( ! is_wp_error( $local_verification ) ) {
		set_transient(
			XPRESSUI_PRO_LICENSE_STATUS_TRANSIENT,
			'active',
			XPRESSUI_PRO_LICENSE_STATUS_TRANSIENT_TTL
		);
		return true;
	}

	/**
	 * Second: attempt fresh validation with the API.
	 */
	$fresh_result = xpressui_pro_fetch_and_verify_license_from_api( $license_data['license_key'] );
	if ( ! is_wp_error( $fresh_result ) ) {
		update_option( XPRESSUI_PRO_LICENSE_OPTION_KEY, $fresh_result );
		set_transient(
			XPRESSUI_PRO_LICENSE_STATUS_TRANSIENT,
			'active',
			XPRESSUI_PRO_LICENSE_STATUS_TRANSIENT_TTL
		);
		return true;
	}

	/**
	 * Third: if API fails, optionally keep a grace period using the last signed response.
	 */
	if ( ! empty( $license_data['last_check'] ) ) {
		$age = time() - (int) $license_data['last_check'];

		if ( $age < XPRESSUI_PRO_LICENSE_GRACE_PERIOD ) {
			$soft_valid = xpressui_pro_verify_stored_license_data( $license_data, true );
			if ( ! is_wp_error( $soft_valid ) ) {
				set_transient(
					XPRESSUI_PRO_LICENSE_STATUS_TRANSIENT,
					'active',
					12 * HOUR_IN_SECONDS
				);
				return true;
			}
		}
	}

	set_transient( XPRESSUI_PRO_LICENSE_STATUS_TRANSIENT, 'inactive', HOUR_IN_SECONDS );
	return false;
}

/**
 * Verifies the signed license data already stored in wp_options.
 *
 * @param array $license_data Stored option value.
 * @param bool  $allow_expired_payload_in_grace Whether to tolerate an expired payload during grace mode.
 * @return true|WP_Error
 */
function xpressui_pro_verify_stored_license_data( array $license_data, $allow_expired_payload_in_grace = false ) {
	if (
		empty( $license_data['alg'] ) ||
		empty( $license_data['key_id'] ) ||
		empty( $license_data['payload_b64'] ) ||
		empty( $license_data['signature_b64'] )
	) {
		return new WP_Error(
			'xpressui_pro_missing_stored_signature',
			__( 'Stored license data is incomplete.', 'xpressui-wordpress-bridge-pro' )
		);
	}

	$response = [
		'alg'           => $license_data['alg'],
		'key_id'        => $license_data['key_id'],
		'payload_b64'   => $license_data['payload_b64'],
		'signature_b64' => $license_data['signature_b64'],
	];

	$result = xpressui_pro_verify_signed_license_response(
		$response,
		xpressui_pro_normalize_site_url( get_site_url() )
	);

	if ( ! is_wp_error( $result ) ) {
		return true;
	}

	if ( ! $allow_expired_payload_in_grace ) {
		return $result;
	}

	if ( 'xpressui_pro_expired_payload' !== $result->get_error_code() ) {
		return $result;
	}

	/**
	 * Grace mode:
	 * accept only if all the signed data is structurally correct except for expiration,
	 * and we still have a recent last_check.
	 */
	$payload = xpressui_pro_base64url_decode( $license_data['payload_b64'] );
	if ( false === $payload ) {
		return $result;
	}

	$data = json_decode( $payload, true );
	if ( ! is_array( $data ) ) {
		return $result;
	}

	$status     = isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : '';
	$product_id = isset( $data['product_id'] ) ? sanitize_text_field( $data['product_id'] ) : '';
	$site_url   = isset( $data['site_url'] ) ? xpressui_pro_normalize_site_url( $data['site_url'] ) : '';

	if (
		'active' !== $status ||
		XPRESSUI_PRO_PRODUCT_ID !== $product_id ||
		xpressui_pro_normalize_site_url( get_site_url() ) !== $site_url
	) {
		return $result;
	}

	return true;
}
