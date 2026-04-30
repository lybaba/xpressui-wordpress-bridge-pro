<?php
/**
 * Stripe payment integration — Pro feature.
 *
 * Registers a REST endpoint that creates Stripe Payment Intents server-side.
 * Injects the Stripe publishable key and endpoint URL into the page so the
 * XPressUI payment runtime can initialise Stripe Elements without any
 * inline scripts in the templates.
 *
 * Settings stored in `xpressui_project_settings[$slug]`:
 *   proStripePublishableKey   string  Stripe publishable key (pk_live_… / pk_test_…)
 *   proStripeSecretKey        string  Stripe secret key (sk_live_… / sk_test_…)
 *   proStripeTestMode         bool    true when using test keys
 *
 * @package XPressUI_Bridge_Pro
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// REST endpoint — create Payment Intent
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', 'xpressui_pro_register_stripe_routes' );

function xpressui_pro_register_stripe_routes(): void {
	register_rest_route( 'xpressui/v1', '/stripe/payment-intent', [
		'methods'             => 'POST',
		'callback'            => 'xpressui_pro_create_payment_intent',
		'permission_callback' => '__return_true',
		'args'                => [
			'projectSlug' => [ 'required' => true, 'sanitize_callback' => 'sanitize_title' ],
			'amount'      => [ 'required' => true, 'sanitize_callback' => 'absint' ],
			'currency'    => [
				'required'          => false,
				'default'           => 'usd',
				'sanitize_callback' => function ( $v ) {
					return strtolower( sanitize_key( (string) $v ) );
				},
			],
			'fieldName'   => [ 'required' => false, 'sanitize_callback' => 'sanitize_key' ],
		],
	] );
}

function xpressui_pro_create_payment_intent( WP_REST_Request $request ): WP_REST_Response {
	$project_slug = $request->get_param( 'projectSlug' );
	$amount       = (int) $request->get_param( 'amount' );
	$currency     = (string) $request->get_param( 'currency' );

	$all_settings = get_option( 'xpressui_project_settings', [] );
	$s            = is_array( $all_settings[ $project_slug ] ?? null ) ? $all_settings[ $project_slug ] : [];
	$secret_key   = trim( (string) ( $s['proStripeSecretKey'] ?? '' ) );

	if ( '' === $secret_key ) {
		return new WP_REST_Response(
			[ 'message' => __( 'Stripe is not configured for this workflow.', 'xpressui-wordpress-bridge-pro' ) ],
			400
		);
	}

	if ( $amount <= 0 ) {
		return new WP_REST_Response(
			[ 'message' => __( 'Invalid payment amount.', 'xpressui-wordpress-bridge-pro' ) ],
			400
		);
	}

	$response = wp_remote_post( 'https://api.stripe.com/v1/payment_intents', [
		'timeout' => 15,
		'headers' => [
			'Authorization' => 'Bearer ' . $secret_key,
			'Content-Type'  => 'application/x-www-form-urlencoded',
		],
		'body'    => http_build_query( [
			'amount'                    => $amount,
			'currency'                  => $currency,
			'automatic_payment_methods' => [ 'enabled' => 'true' ],
		] ),
	] );

	if ( is_wp_error( $response ) ) {
		return new WP_REST_Response(
			[ 'message' => $response->get_error_message() ],
			502
		);
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	$code = (int) wp_remote_retrieve_response_code( $response );

	if ( $code !== 200 || empty( $body['client_secret'] ) ) {
		$stripe_message = $body['error']['message'] ?? __( 'Stripe error.', 'xpressui-wordpress-bridge-pro' );
		return new WP_REST_Response( [ 'message' => $stripe_message ], 502 );
	}

	return new WP_REST_Response( [
		'clientSecret'    => $body['client_secret'],
		'paymentIntentId' => $body['id'],
	], 200 );
}

// ---------------------------------------------------------------------------
// Inject Stripe global variables when a payment-stripe field is on the page
// ---------------------------------------------------------------------------

add_action( 'xpressui_shortcode_scripts_enqueued', 'xpressui_pro_inject_stripe_globals', 10, 2 );

function xpressui_pro_inject_stripe_globals( string $slug, array $template_context ): void {
	if ( ! xpressui_pro_page_has_payment_stripe_field( $template_context ) ) {
		return;
	}

	$all_settings    = get_option( 'xpressui_project_settings', [] );
	$s               = is_array( $all_settings[ $slug ] ?? null ) ? $all_settings[ $slug ] : [];
	$publishable_key = trim( (string) ( $s['proStripePublishableKey'] ?? '' ) );

	if ( '' === $publishable_key ) {
		return;
	}

	$intent_url = rest_url( 'xpressui/v1/stripe/payment-intent' );
	$inline     = 'window.XPRESSUI_STRIPE_PUBLISHABLE_KEY = ' . wp_json_encode( $publishable_key ) . ';';
	$inline    .= 'window.XPRESSUI_STRIPE_PAYMENT_INTENT_URL = ' . wp_json_encode( $intent_url ) . ';';

	wp_add_inline_script( 'xpressui-shell-init', $inline, 'before' );
}

function xpressui_pro_page_has_payment_stripe_field( array $template_context ): bool {
	$sections = $template_context['rendered_form']['sections'] ?? [];
	if ( ! is_array( $sections ) ) {
		return false;
	}
	foreach ( $sections as $section ) {
		$fields = $section['fields'] ?? [];
		if ( ! is_array( $fields ) ) {
			continue;
		}
		foreach ( $fields as $field ) {
			if ( ( $field['type'] ?? '' ) === 'payment-stripe' ) {
				return true;
			}
		}
	}
	return false;
}

// ---------------------------------------------------------------------------
// Workflow Settings — render section
// ---------------------------------------------------------------------------

add_action( 'xpressui_workflow_settings_extra_sections', 'xpressui_pro_render_stripe_section', 30, 3 );

function xpressui_pro_render_stripe_section( string $slug, array $s, $overlay ): void {
	$publishable_key = (string) ( $s['proStripePublishableKey'] ?? '' );
	$secret_key      = (string) ( $s['proStripeSecretKey'] ?? '' );
	$test_mode       = filter_var( $s['proStripeTestMode'] ?? false, FILTER_VALIDATE_BOOLEAN );

	echo '<div class="card xpressui-admin-card">';
	echo '<details><summary><h2>' . esc_html__( 'Stripe Payments', 'xpressui-wordpress-bridge-pro' ) . '</h2><span class="xpressui-toggle-icon" aria-hidden="true">▾</span></summary>';
	echo '<p>' . esc_html__( 'Configure Stripe to accept card payments via the Stripe payment field.', 'xpressui-wordpress-bridge-pro' ) . '</p>';
	echo '<table class="form-table"><tbody>';

	echo '<tr><th><label for="xpressui_stripe_test_mode">' . esc_html__( 'Test mode', 'xpressui-wordpress-bridge-pro' ) . '</label></th>';
	echo '<td><input type="checkbox" id="xpressui_stripe_test_mode" name="xpressui_stripe_test_mode" value="1"' . checked( $test_mode, true, false ) . '>';
	echo '<p class="description">' . esc_html__( 'Enable to use Stripe test keys (pk_test_… / sk_test_…).', 'xpressui-wordpress-bridge-pro' ) . '</p></td></tr>';

	echo '<tr><th><label for="xpressui_stripe_publishable_key">' . esc_html__( 'Publishable key', 'xpressui-wordpress-bridge-pro' ) . '</label></th>';
	echo '<td><input type="text" id="xpressui_stripe_publishable_key" name="xpressui_stripe_publishable_key" class="regular-text" value="' . esc_attr( $publishable_key ) . '" placeholder="pk_live_…">';
	echo '<p class="description">' . esc_html__( 'Your Stripe publishable key. Sent to the browser.', 'xpressui-wordpress-bridge-pro' ) . '</p></td></tr>';

	echo '<tr><th><label for="xpressui_stripe_secret_key">' . esc_html__( 'Secret key', 'xpressui-wordpress-bridge-pro' ) . '</label></th>';
	echo '<td><input type="password" id="xpressui_stripe_secret_key" name="xpressui_stripe_secret_key" class="regular-text" value="' . esc_attr( $secret_key ) . '" placeholder="sk_live_…" autocomplete="new-password">';
	echo '<p class="description">' . esc_html__( 'Your Stripe secret key. Never exposed to the browser.', 'xpressui-wordpress-bridge-pro' ) . '</p></td></tr>';

	echo '</tbody></table>';
	echo '</details>';
	echo '</div>';
}

// ---------------------------------------------------------------------------
// Workflow Settings — save handler
// ---------------------------------------------------------------------------

add_action( 'xpressui_workflow_settings_extra_save', 'xpressui_pro_save_stripe_settings', 30 );

function xpressui_pro_save_stripe_settings( string $slug ): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by caller
	$test_mode       = ! empty( $_POST['xpressui_stripe_test_mode'] );
	$publishable_key = sanitize_text_field( wp_unslash( (string) ( $_POST['xpressui_stripe_publishable_key'] ?? '' ) ) );
	$secret_key      = sanitize_text_field( wp_unslash( (string) ( $_POST['xpressui_stripe_secret_key'] ?? '' ) ) );
	// phpcs:enable

	$all_settings = get_option( 'xpressui_project_settings', [] );
	if ( ! is_array( $all_settings ) ) {
		$all_settings = [];
	}
	if ( ! isset( $all_settings[ $slug ] ) || ! is_array( $all_settings[ $slug ] ) ) {
		$all_settings[ $slug ] = [];
	}

	$all_settings[ $slug ]['proStripeTestMode']       = $test_mode ? '1' : '0';
	$all_settings[ $slug ]['proStripePublishableKey'] = $publishable_key;

	// Only overwrite the secret key if a non-empty value was submitted
	// (the field may be blanked by the browser's password manager heuristics).
	if ( '' !== $secret_key ) {
		$all_settings[ $slug ]['proStripeSecretKey'] = $secret_key;
	}

	update_option( 'xpressui_project_settings', $all_settings );
}
