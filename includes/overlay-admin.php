<?php
/**
 * Customize Workflow admin page — pro extension.
 *
 * @package XPressUI_Bridge_Pro
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Menu registration
// ---------------------------------------------------------------------------

add_action( 'admin_menu', 'xpressui_pro_register_customize_page' );

function xpressui_pro_register_customize_page(): void {
	add_submenu_page(
		null,
		__( 'Customize Workflow', 'xpressui-bridge-pro' ),
		__( 'Customize Workflow', 'xpressui-bridge-pro' ),
		'manage_options',
		'xpressui-customize',
		'xpressui_pro_render_customize_page'
	);
}

// ---------------------------------------------------------------------------
// Inject "Customize" link into workflow row actions
// ---------------------------------------------------------------------------

add_filter( 'xpressui_workflow_row_actions', 'xpressui_pro_workflow_row_actions', 10, 2 );

function xpressui_pro_workflow_row_actions( array $actions, string $slug ): array {
	$url = add_query_arg(
		[
			'post_type'     => 'xpressui_submission',
			'page'          => 'xpressui-customize',
			'xpressui_slug' => $slug,
		],
		admin_url( 'edit.php' )
	);
	$actions[] = '<a href="' . esc_url( $url ) . '" class="xpressui-pro-action-link">'
		. '<span>' . esc_html__( 'Customize', 'xpressui-bridge-pro' ) . '</span>'
		. ' <span class="xpressui-pro-action-badge">PRO</span>'
		. '</a>';
	return $actions;
}

add_action( 'admin_head', 'xpressui_pro_render_workflow_action_styles' );

function xpressui_pro_render_workflow_action_styles(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'xpressui_submission_page_xpressui-bridge' !== $screen->id ) {
		return;
	}

	echo '<style>
.xpressui-pro-action-link{
	display:inline-flex;
	align-items:center;
	gap:6px;
	max-width:100%;
	padding:3px 9px;
	border-radius:999px;
	background:linear-gradient(135deg,#14213d 0%,#2b4fd8 52%,#6d4dff 100%);
	color:#fff !important;
	font-size:12px;
	font-weight:700;
	line-height:1.2;
	text-decoration:none;
	box-shadow:0 8px 16px rgba(43,79,216,.22);
	transition:transform .15s ease, box-shadow .15s ease, opacity .15s ease;
	white-space:nowrap;
	vertical-align:middle;
}
.xpressui-pro-action-link:hover,
.xpressui-pro-action-link:focus{
	color:#fff !important;
	transform:translateY(-1px);
	box-shadow:0 10px 20px rgba(43,79,216,.28);
}
.xpressui-pro-action-link:focus{
	outline:none;
	box-shadow:0 0 0 2px rgba(255,255,255,.92),0 0 0 4px rgba(109,77,255,.4),0 10px 20px rgba(43,79,216,.28);
}
.xpressui-pro-action-badge{
	display:inline-flex;
	align-items:center;
	justify-content:center;
	border-radius:999px;
	padding:1px 6px;
	background:rgba(255,255,255,.14);
	border:1px solid rgba(255,255,255,.24);
	color:#fff;
	font-size:9px;
	font-weight:800;
	letter-spacing:.08em;
	line-height:1.2;
}
.wp-list-table .column-actions .xpressui-pro-action-link{
	margin:0 0 4px;
}
</style>';
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Returns current project settings for a slug.
 *
 * @return array{notifyEmail: string, redirectUrl: string, showProjectTitle: string, showRequiredFieldsNote: string}
 */
function xpressui_pro_get_project_settings( string $slug ): array {
	$all = get_option( 'xpressui_project_settings', [] );
	$row = isset( $all[ $slug ] ) && is_array( $all[ $slug ] ) ? $all[ $slug ] : [];
	return [
		'notifyEmail'            => (string) ( $row['notifyEmail'] ?? '' ),
		'redirectUrl'            => (string) ( $row['redirectUrl'] ?? '' ),
		'showProjectTitle'       => ! empty( $row['showProjectTitle'] ) ? '1' : '0',
		'showRequiredFieldsNote' => ! empty( $row['showRequiredFieldsNote'] ) ? '1' : '0',
	];
}

/**
 * Save project settings for a slug (merges into the shared option).
 */
function xpressui_pro_save_project_settings(
	string $slug,
	string $notify_email,
	string $redirect_url,
	string $show_project_title,
	string $show_required_fields_note
): void {
	$all = get_option( 'xpressui_project_settings', [] );
	if ( ! is_array( $all ) ) {
		$all = [];
	}
	$current_row = isset( $all[ $slug ] ) && is_array( $all[ $slug ] ) ? $all[ $slug ] : [];
	$all[ $slug ] = array_merge(
		$current_row,
		[
		'notifyEmail'            => $notify_email,
		'redirectUrl'            => $redirect_url,
		'showProjectTitle'       => $show_project_title,
		'showRequiredFieldsNote' => $show_required_fields_note,
		]
	);
	update_option( 'xpressui_project_settings', $all );
}

/**
 * Returns a trimmed raw form value from an overlay field payload.
 */
function xpressui_pro_overlay_raw_value( array $field_data, string $key ): string {
	return trim( wp_unslash( (string) ( $field_data[ $key ] ?? '' ) ) );
}

/**
 * Stores a non-empty integer overlay value, or records a validation warning.
 *
 * @param array<string, mixed> $entry
 * @param array<int, string>   $warnings
 * @param array<int, string>   $invalid_fields
 */
function xpressui_pro_collect_overlay_int(
	array &$entry,
	array &$warnings,
	array &$invalid_fields,
	array $field_data,
	string $field_name,
	string $source_key,
	string $target_key,
	string $label
): void {
	$raw = xpressui_pro_overlay_raw_value( $field_data, $source_key );
	if ( $raw === '' ) {
		return;
	}
	if ( ! preg_match( '/^\d+$/', $raw ) ) {
		$warnings[]       = sprintf(
			/* translators: 1: field label, 2: validation label. */
			__( 'The value for "%1$s" (%2$s) was not saved because it must be a whole number.', 'xpressui-bridge-pro' ),
			$field_name,
			$label
		);
		$invalid_fields[] = 'xpressui_overlay_fields_' . sanitize_html_class( $field_name ) . '_' . sanitize_html_class( $source_key );
		return;
	}
	$entry[ $target_key ] = (int) $raw;
}

/**
 * Stores a non-empty numeric overlay value, or records a validation warning.
 *
 * @param array<string, mixed> $entry
 * @param array<int, string>   $warnings
 * @param array<int, string>   $invalid_fields
 */
function xpressui_pro_collect_overlay_number(
	array &$entry,
	array &$warnings,
	array &$invalid_fields,
	array $field_data,
	string $field_name,
	string $source_key,
	string $target_key,
	string $label
): void {
	$raw = xpressui_pro_overlay_raw_value( $field_data, $source_key );
	if ( $raw === '' ) {
		return;
	}
	if ( ! is_numeric( $raw ) ) {
		$warnings[]       = sprintf(
			/* translators: 1: field label, 2: validation label. */
			__( 'The value for "%1$s" (%2$s) was not saved because it must be numeric.', 'xpressui-bridge-pro' ),
			$field_name,
			$label
		);
		$invalid_fields[] = 'xpressui_overlay_fields_' . sanitize_html_class( $field_name ) . '_' . sanitize_html_class( $source_key );
		return;
	}
	$entry[ $target_key ] = 0 + $raw;
}

/**
 * Returns whether a field supports min/max choice limits.
 */
function xpressui_pro_field_supports_choice_limits( array $field ): bool {
	$field_type = (string) ( $field['type'] ?? '' );
	$multiple   = ! empty( $field['multiple'] );

	return $multiple || in_array( $field_type, [ 'select-multiple', 'checkboxes' ], true );
}

/**
 * Returns whether a field exposes predefined choices that can be relabeled.
 */
function xpressui_pro_field_has_choices( array $field ): bool {
	return isset( $field['choices'] ) && is_array( $field['choices'] ) && ! empty( $field['choices'] );
}

/**
 * Returns whether a field supports regex pattern validation.
 */
function xpressui_pro_field_supports_pattern( array $field ): bool {
	$field_type = (string) ( $field['type'] ?? '' );

	return in_array( $field_type, [ 'text', 'email', 'tel', 'url', 'search', 'slug' ], true );
}

// ---------------------------------------------------------------------------
// Render page
// ---------------------------------------------------------------------------

function xpressui_pro_render_customize_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'xpressui-bridge-pro' ) );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$slug = sanitize_title( wp_unslash( (string) ( $_GET['xpressui_slug'] ?? '' ) ) );
	if ( $slug === '' || ! xpressui_is_installed_workflow( $slug ) ) {
		wp_die( esc_html__( 'Workflow not found.', 'xpressui-bridge-pro' ) );
	}

	$back_url = add_query_arg(
		[ 'post_type' => 'xpressui_submission', 'page' => 'xpressui-bridge' ],
		admin_url( 'edit.php' )
	);

	$pack_template_context = xpressui_load_workflow_template_context( $slug );
	$pack_sections         = isset( $pack_template_context['rendered_form']['sections'] ) && is_array( $pack_template_context['rendered_form']['sections'] )
		? $pack_template_context['rendered_form']['sections']
		: [];
	$pack_fields_by_name   = [];
	foreach ( $pack_sections as $pack_section ) {
		$pack_section_fields = isset( $pack_section['fields'] ) && is_array( $pack_section['fields'] ) ? $pack_section['fields'] : [];
		foreach ( $pack_section_fields as $pack_field ) {
			$pack_field_name = (string) ( $pack_field['name'] ?? '' );
			if ( $pack_field_name !== '' ) {
				$pack_fields_by_name[ $pack_field_name ] = $pack_field;
			}
		}
	}

	$notice_class    = '';
	$notice_messages = [];
	$invalid_fields  = [];

	// -----------------------------------------------------------------------
	// Handle save
	// -----------------------------------------------------------------------

	if ( isset( $_POST['xpressui_save_overlay'] ) && check_admin_referer( 'xpressui_overlay_' . $slug, 'xpressui_overlay_nonce' ) ) {

		// Project settings (stored separately in xpressui_project_settings).
		$raw_notify_email         = trim( wp_unslash( (string) ( $_POST['xpressui_notify_email'] ?? '' ) ) );
		$raw_redirect_url         = trim( wp_unslash( (string) ( $_POST['xpressui_redirect_url'] ?? '' ) ) );
		$show_project_title       = ! empty( $_POST['xpressui_show_project_title'] ) ? '1' : '0';
		$show_required_note       = ! empty( $_POST['xpressui_show_required_fields_note'] ) ? '1' : '0';
		$notify_email             = sanitize_email( $raw_notify_email );
		$redirect_url             = esc_url_raw( $raw_redirect_url );

		$save_warnings = [];
		if ( $raw_notify_email !== '' && $notify_email === '' ) {
			$save_warnings[] = __( 'The notification email was not saved because it is not a valid email address.', 'xpressui-bridge-pro' );
			$invalid_fields[] = 'xpressui_notify_email';
		}
		if ( $raw_redirect_url !== '' && $redirect_url === '' ) {
			$save_warnings[] = __( 'The post-submit redirect was not saved because it is not a valid URL.', 'xpressui-bridge-pro' );
			$invalid_fields[] = 'xpressui_redirect_url';
		}
		xpressui_pro_save_project_settings( $slug, $notify_email, $redirect_url, $show_project_title, $show_required_note );

		// Overlay.
		$overlay = [];

		$project_name = sanitize_text_field( wp_unslash( (string) ( $_POST['xpressui_overlay_project_name'] ?? '' ) ) );
		if ( $project_name !== '' ) {
			$overlay['project_name'] = $project_name;
		}

		$success_msg = sanitize_text_field( wp_unslash( (string) ( $_POST['xpressui_overlay_success_message'] ?? '' ) ) );
		if ( $success_msg !== '' ) {
			$overlay['success_message'] = $success_msg;
		}

		$error_msg = sanitize_text_field( wp_unslash( (string) ( $_POST['xpressui_overlay_error_message'] ?? '' ) ) );
		if ( $error_msg !== '' ) {
			$overlay['error_message'] = $error_msg;
		}

		// Navigation labels.
		$nav = [];
		foreach ( [ 'prev', 'next', 'submit' ] as $nav_key ) {
			$v = sanitize_text_field( wp_unslash( (string) ( $_POST[ 'xpressui_overlay_nav_' . $nav_key ] ?? '' ) ) );
			if ( $v !== '' ) {
				$nav[ $nav_key ] = $v;
			}
		}
		if ( ! empty( $nav ) ) {
			$overlay['navigation'] = $nav;
		}

		// Section labels.
		$raw_sections = isset( $_POST['xpressui_overlay_sections'] ) && is_array( $_POST['xpressui_overlay_sections'] )
			? $_POST['xpressui_overlay_sections'] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: [];
		$sections_overlay = [];
		foreach ( $raw_sections as $sname => $slabel ) {
			$sname  = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $sname );
			$slabel = sanitize_text_field( wp_unslash( (string) $slabel ) );
			if ( $sname !== '' && $slabel !== '' ) {
				$sections_overlay[ $sname ] = $slabel;
			}
		}
		if ( ! empty( $sections_overlay ) ) {
			$overlay['sections'] = $sections_overlay;
		}

		// Fields.
		$raw_fields = isset( $_POST['xpressui_overlay_fields'] ) && is_array( $_POST['xpressui_overlay_fields'] )
			? $_POST['xpressui_overlay_fields'] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: [];
		$fields_overlay = [];
		foreach ( $raw_fields as $field_name => $field_data ) {
			$field_name = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $field_name );
			if ( $field_name === '' || ! is_array( $field_data ) ) {
				continue;
			}
			$pack_field = isset( $pack_fields_by_name[ $field_name ] ) && is_array( $pack_fields_by_name[ $field_name ] ) ? $pack_fields_by_name[ $field_name ] : [];
			$entry = [];

			$v = sanitize_text_field( wp_unslash( (string) ( $field_data['label'] ?? '' ) ) );
			if ( $v !== '' ) {
				$entry['label'] = $v;
			}

			// Required: '' = pack default, '1' = required, '0' = optional.
			$req = (string) ( $field_data['required'] ?? '' );
			if ( $req === '1' ) {
				$entry['required'] = true;
			} elseif ( $req === '0' ) {
				$entry['required'] = false;
			}

			$v = sanitize_text_field( wp_unslash( (string) ( $field_data['placeholder'] ?? '' ) ) );
			if ( $v !== '' ) {
				$entry['placeholder'] = $v;
			}

			$v = sanitize_textarea_field( wp_unslash( (string) ( $field_data['desc'] ?? '' ) ) );
			if ( $v !== '' ) {
				$entry['desc'] = $v;
			}

			$v = sanitize_text_field( wp_unslash( (string) ( $field_data['error_message'] ?? '' ) ) );
			if ( $v !== '' ) {
				$entry['error_message'] = $v;
			}

			$v = sanitize_text_field( xpressui_pro_overlay_raw_value( $field_data, 'pattern' ) );
			if ( $v !== '' && xpressui_pro_field_supports_pattern( $pack_field ) ) {
				$entry['pattern'] = $v;
			}

			xpressui_pro_collect_overlay_int( $entry, $save_warnings, $invalid_fields, $field_data, $field_name, 'min_len', 'min_len', __( 'Min length', 'xpressui-bridge-pro' ) );
			xpressui_pro_collect_overlay_int( $entry, $save_warnings, $invalid_fields, $field_data, $field_name, 'max_len', 'max_len', __( 'Max length', 'xpressui-bridge-pro' ) );
			if ( xpressui_pro_field_supports_choice_limits( $pack_field ) ) {
				xpressui_pro_collect_overlay_int( $entry, $save_warnings, $invalid_fields, $field_data, $field_name, 'min_choices', 'min_choices', __( 'Minimum choices', 'xpressui-bridge-pro' ) );
				xpressui_pro_collect_overlay_int( $entry, $save_warnings, $invalid_fields, $field_data, $field_name, 'max_choices', 'max_choices', __( 'Maximum choices', 'xpressui-bridge-pro' ) );
			}
			xpressui_pro_collect_overlay_number( $entry, $save_warnings, $invalid_fields, $field_data, $field_name, 'min_value', 'min_value', __( 'Minimum value', 'xpressui-bridge-pro' ) );
			xpressui_pro_collect_overlay_number( $entry, $save_warnings, $invalid_fields, $field_data, $field_name, 'max_value', 'max_value', __( 'Maximum value', 'xpressui-bridge-pro' ) );
			xpressui_pro_collect_overlay_number( $entry, $save_warnings, $invalid_fields, $field_data, $field_name, 'step_value', 'step_value', __( 'Step', 'xpressui-bridge-pro' ) );
			xpressui_pro_collect_overlay_number( $entry, $save_warnings, $invalid_fields, $field_data, $field_name, 'max_file_size_mb', 'max_file_size_mb', __( 'Maximum file size (MB)', 'xpressui-bridge-pro' ) );

			$v = sanitize_text_field( xpressui_pro_overlay_raw_value( $field_data, 'accept' ) );
			if ( $v !== '' ) {
				$entry['accept'] = $v;
			}

			$v = sanitize_text_field( xpressui_pro_overlay_raw_value( $field_data, 'upload_accept_label' ) );
			if ( $v !== '' ) {
				$entry['upload_accept_label'] = $v;
			}
			$v = sanitize_text_field( xpressui_pro_overlay_raw_value( $field_data, 'file_type_error_message' ) );
			if ( $v !== '' ) {
				$entry['file_type_error_message'] = $v;
			}
			$v = sanitize_text_field( xpressui_pro_overlay_raw_value( $field_data, 'file_size_error_message' ) );
			if ( $v !== '' ) {
				$entry['file_size_error_message'] = $v;
			}

			if ( isset( $entry['min_len'], $entry['max_len'] ) && (int) $entry['min_len'] > (int) $entry['max_len'] ) {
				unset( $entry['min_len'], $entry['max_len'] );
				$save_warnings[] = sprintf(
					/* translators: %s: field label. */
					__( 'The min/max length values for "%s" were not saved because the minimum cannot be greater than the maximum.', 'xpressui-bridge-pro' ),
					$field_name
				);
				$invalid_fields[] = 'xpressui_overlay_fields_' . sanitize_html_class( $field_name ) . '_min_len';
				$invalid_fields[] = 'xpressui_overlay_fields_' . sanitize_html_class( $field_name ) . '_max_len';
			}

			if ( isset( $entry['min_value'], $entry['max_value'] ) && (float) $entry['min_value'] > (float) $entry['max_value'] ) {
				unset( $entry['min_value'], $entry['max_value'] );
				$save_warnings[] = sprintf(
					/* translators: %s: field label. */
					__( 'The min/max values for "%s" were not saved because the minimum cannot be greater than the maximum.', 'xpressui-bridge-pro' ),
					$field_name
				);
				$invalid_fields[] = 'xpressui_overlay_fields_' . sanitize_html_class( $field_name ) . '_min_value';
				$invalid_fields[] = 'xpressui_overlay_fields_' . sanitize_html_class( $field_name ) . '_max_value';
			}

			if ( isset( $entry['step_value'] ) && (float) $entry['step_value'] <= 0 ) {
				unset( $entry['step_value'] );
				$save_warnings[] = sprintf(
					/* translators: %s: field label. */
					__( 'The step value for "%s" was not saved because it must be greater than zero.', 'xpressui-bridge-pro' ),
					$field_name
				);
				$invalid_fields[] = 'xpressui_overlay_fields_' . sanitize_html_class( $field_name ) . '_step_value';
			}

			if ( isset( $entry['max_file_size_mb'] ) && (float) $entry['max_file_size_mb'] <= 0 ) {
				unset( $entry['max_file_size_mb'] );
				$save_warnings[] = sprintf(
					/* translators: %s: field label. */
					__( 'The maximum file size for "%s" was not saved because it must be greater than zero.', 'xpressui-bridge-pro' ),
					$field_name
				);
				$invalid_fields[] = 'xpressui_overlay_fields_' . sanitize_html_class( $field_name ) . '_max_file_size_mb';
			}

			if ( xpressui_pro_field_supports_choice_limits( $pack_field ) && isset( $entry['min_choices'], $entry['max_choices'] ) && (int) $entry['min_choices'] > (int) $entry['max_choices'] ) {
				unset( $entry['min_choices'], $entry['max_choices'] );
				$save_warnings[] = sprintf(
					/* translators: %s: field label. */
					__( 'The minimum/maximum choices for "%s" were not saved because the minimum cannot be greater than the maximum.', 'xpressui-bridge-pro' ),
					$field_name
				);
				$invalid_fields[] = 'xpressui_overlay_fields_' . sanitize_html_class( $field_name ) . '_min_choices';
				$invalid_fields[] = 'xpressui_overlay_fields_' . sanitize_html_class( $field_name ) . '_max_choices';
			}

			// Choice labels.
			if ( xpressui_pro_field_has_choices( $pack_field ) && isset( $field_data['choices'] ) && is_array( $field_data['choices'] ) ) {
				$choices_entry = [];
				foreach ( $field_data['choices'] as $cv => $cl ) {
					$cv = (string) $cv;
					$cl = sanitize_text_field( wp_unslash( (string) $cl ) );
					if ( $cv !== '' && $cl !== '' ) {
						$choices_entry[ $cv ] = $cl;
					}
				}
				if ( ! empty( $choices_entry ) ) {
					$entry['choices'] = $choices_entry;
				}
			}

			if ( ! empty( $entry ) ) {
				$fields_overlay[ $field_name ] = $entry;
			}
		}
		if ( ! empty( $fields_overlay ) ) {
			$overlay['fields'] = $fields_overlay;
		}

		xpressui_pro_save_workflow_overlay( $slug, $overlay );
		$notice_class    = empty( $save_warnings ) ? 'notice-success' : 'notice-warning';
		$notice_messages = array_merge(
			[ __( 'Customizations saved.', 'xpressui-bridge-pro' ) ],
			$save_warnings
		);
	}

	// Handle reset.
	if ( isset( $_POST['xpressui_reset_overlay'] ) && check_admin_referer( 'xpressui_overlay_' . $slug, 'xpressui_overlay_nonce' ) ) {
		xpressui_pro_delete_workflow_overlay( $slug );
		$notice_class    = 'notice-success';
		$notice_messages = [ __( 'Customizations reset to pack defaults.', 'xpressui-bridge-pro' ) ];
	}

	// -----------------------------------------------------------------------
	// Load data for display
	// -----------------------------------------------------------------------

	$template_context = xpressui_load_workflow_template_context( $slug );
	$overlay          = xpressui_pro_load_workflow_overlay( $slug );
	$proj_settings    = xpressui_pro_get_project_settings( $slug );

	$original_title  = (string) ( $template_context['rendered_form']['title'] ?? $template_context['project']['name'] ?? $slug );
	$sections        = isset( $template_context['rendered_form']['sections'] ) && is_array( $template_context['rendered_form']['sections'] )
		? $template_context['rendered_form']['sections']
		: [];

	$ov_project_name    = (string) ( $overlay['project_name'] ?? '' );
	$ov_success_message = (string) ( $overlay['success_message'] ?? '' );
	$ov_error_message   = (string) ( $overlay['error_message'] ?? '' );
	$ov_navigation      = isset( $overlay['navigation'] ) && is_array( $overlay['navigation'] ) ? $overlay['navigation'] : [];
	$ov_sections        = isset( $overlay['sections'] ) && is_array( $overlay['sections'] ) ? $overlay['sections'] : [];
	$ov_fields          = isset( $overlay['fields'] ) && is_array( $overlay['fields'] ) ? $overlay['fields'] : [];

	$summary_stats = [
		'section_count'        => 0,
		'field_count'          => 0,
		'choice_count'         => 0,
		'navigation_count'     => count( $ov_navigation ),
		'has_project_settings' => $ov_project_name !== '' || $proj_settings['notifyEmail'] !== '' || $proj_settings['redirectUrl'] !== '' || $proj_settings['showProjectTitle'] === '1' || $proj_settings['showRequiredFieldsNote'] === '1',
		'has_submit_feedback'  => $ov_success_message !== '' || $ov_error_message !== '',
	];

	foreach ( $ov_sections as $section_value ) {
		if ( (string) $section_value !== '' ) {
			$summary_stats['section_count']++;
		}
	}
	foreach ( $ov_fields as $field_overlay ) {
		if ( ! is_array( $field_overlay ) || empty( $field_overlay ) ) {
			continue;
		}
		$summary_stats['field_count']++;
		$field_choices = isset( $field_overlay['choices'] ) && is_array( $field_overlay['choices'] ) ? $field_overlay['choices'] : [];
		foreach ( $field_choices as $choice_label ) {
			if ( (string) $choice_label !== '' ) {
				$summary_stats['choice_count']++;
			}
		}
	}

	// Read pack defaults for navigation labels from rendered_form (source of truth for PHP-rendered buttons).
	$rf_nav_labels = isset( $template_context['rendered_form']['navigation_labels'] ) && is_array( $template_context['rendered_form']['navigation_labels'] )
		? $template_context['rendered_form']['navigation_labels']
		: [];
	$pack_nav = [
		'prevLabel'   => (string) ( $rf_nav_labels['previous'] ?? 'Back' ),
		'nextLabel'   => (string) ( $rf_nav_labels['next'] ?? 'Continue' ),
		'submitLabel' => (string) ( $template_context['rendered_form']['submit_label'] ?? 'Submit' ),
	];

	// -----------------------------------------------------------------------
	// Render
	// -----------------------------------------------------------------------

	echo '<div class="wrap xpressui-admin-wrap">';
	echo '<style>
.xpressui-admin-card{background:#fff;border:1px solid #c3c4c7;border-radius:4px;margin-bottom:10px;box-shadow:0 1px 1px rgba(0,0,0,.04)}
.xpressui-card-summary{cursor:pointer;padding:11px 16px;display:flex;align-items:center;gap:8px;list-style:none;user-select:none;border-bottom:1px solid transparent}
.xpressui-card-summary::-webkit-details-marker{display:none}
.xpressui-card-summary::before{content:"▶";font-size:9px;color:#2966ff;flex-shrink:0;transition:transform .15s}
.xpressui-admin-card[open]>.xpressui-card-summary::before{transform:rotate(90deg)}
.xpressui-admin-card[open]>.xpressui-card-summary{border-bottom-color:#dfe8f2}
.xpressui-card-summary h2{margin:0;font-size:13px;font-weight:600;flex:1;text-transform:uppercase;letter-spacing:.04em;color:#122033}
.xpressui-card-body{padding:0 12px}
.xpressui-sticky-actions{position:sticky;top:32px;z-index:100;background:#fff;border-left:3px solid #2966ff;border-radius:0 4px 4px 0;padding:8px 16px;margin-bottom:14px;box-shadow:0 2px 10px rgba(41,102,255,.15);display:flex;align-items:center;gap:10px}
.xpressui-sticky-actions-buttons{display:inline-flex;align-items:center;gap:10px;margin-left:auto}
.xpressui-pro-header{background:
radial-gradient(circle at top right, rgba(109,77,255,.28), transparent 28%),
radial-gradient(circle at 85% 20%, rgba(56,189,248,.18), transparent 24%),
linear-gradient(125deg,#0f172a 0%,#14213d 34%,#2b4fd8 68%,#6d4dff 100%);
margin:-10px -20px 16px;padding:28px 28px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;position:relative;overflow:hidden;box-shadow:inset 0 -1px 0 rgba(255,255,255,.08)}
.xpressui-pro-header::after{content:"";position:absolute;right:-56px;top:-56px;width:220px;height:220px;background:rgba(255,255,255,.05);border-radius:50%}
.xpressui-pro-header::before{content:"";position:absolute;left:-48px;bottom:-72px;width:220px;height:220px;background:rgba(56,189,248,.08);border-radius:50%}
.xpressui-pro-header-left{position:relative;z-index:1}
.xpressui-pro-header-left h1{margin:0 0 6px;font-size:24px;font-weight:700;color:#fff;line-height:1.2}
.xpressui-pro-header-left p{margin:0;font-size:13px;color:rgba(255,255,255,.74);line-height:1.5}
.xpressui-pro-header-right{position:relative;z-index:1;text-align:right;flex-shrink:0}
.xpressui-pro-badge{display:inline-flex;align-items:center;gap:5px;background:linear-gradient(135deg,rgba(255,255,255,.16),rgba(255,255,255,.08));border:1px solid rgba(255,255,255,.24);border-radius:20px;padding:6px 14px;font-size:12px;font-weight:800;letter-spacing:.08em;color:#fff;text-transform:uppercase;box-shadow:0 10px 24px rgba(15,23,42,.18)}
.xpressui-pro-back{display:block;margin-top:8px;font-size:12px;color:rgba(255,255,255,.68);text-decoration:none}
.xpressui-pro-back:hover{color:#fff}
.xpressui-inline-notice{margin:0 0 16px;padding:12px 16px;border-radius:6px;border-left:4px solid #00a32a;background:#fff;color:#1d2327;box-shadow:0 1px 2px rgba(15,23,42,.06)}
.xpressui-inline-notice p{margin:0;font-size:13px;font-weight:600;color:#1d2327}
.xpressui-inline-notice.is-error{border-left-color:#d63638}
.xpressui-inline-notice.is-warning{border-left-color:#dba617;background:#fffbf0}
.xpressui-inline-notice ul{margin:8px 0 0 18px;padding:0}
.xpressui-inline-notice li{margin:0 0 4px;font-size:13px;color:#1d2327}
.xpressui-pro-summary{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 16px}
.xpressui-pro-summary-chip{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:#fff;border:1px solid #dfe8f2;color:#122033;box-shadow:0 1px 2px rgba(15,23,42,.05)}
.xpressui-pro-summary-chip strong{font-size:13px}
.xpressui-pro-summary-chip span{font-size:11px;color:#5b6b82;text-transform:uppercase;letter-spacing:.06em}
.xpressui-pro-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:0 0 14px}
.xpressui-pro-toolbar button{border:1px solid #c5d4ee;background:#fff;color:#183ea8;border-radius:999px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer}
.xpressui-pro-toolbar button:hover{background:#f5f9ff}
.xpressui-pro-toolbar button.is-accent{background:#183ea8;border-color:#183ea8;color:#fff}
.xpressui-pro-toolbar button.is-accent:hover{background:#122f80}
.xpressui-pro-toolbar button.is-active{background:#e9f0ff;border-color:#9db6f7;color:#183ea8}
.xpressui-pro-toolbar-search{display:flex;align-items:center;gap:8px;margin-left:auto;min-width:min(360px,100%)}
.xpressui-pro-toolbar-search input{width:100%;min-height:34px;border:1px solid #c5d4ee;border-radius:999px;padding:0 14px;font-size:12px;box-shadow:none}
.xpressui-pro-toolbar-search input:focus{border-color:#183ea8;box-shadow:0 0 0 1px rgba(24,62,168,.15)}
.xpressui-pro-toolbar-meta{display:inline-flex;align-items:center;gap:8px;font-size:12px;color:#5b6b82}
.xpressui-pro-toolbar-meta strong{color:#122033}
.xpressui-pro-empty-state{display:none;margin:0 0 14px;padding:14px 16px;border:1px dashed #c7d7f6;border-radius:10px;background:linear-gradient(180deg,#f8fbff 0%,#f1f6ff 100%);color:#36507a;font-size:13px}
.xpressui-admin-card.is-filtered-out{display:none}
.xpressui-card-meta{display:inline-flex;align-items:center;gap:6px;margin-left:auto;flex-wrap:wrap}
.xpressui-card-badge{display:inline-flex;align-items:center;justify-content:center;padding:3px 8px;border-radius:999px;background:#eef4ff;color:#183ea8;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase}
.xpressui-card-badge.is-customized{background:#e9f7ef;color:#0a7a32}
.xpressui-sticky-status{font-size:12px;font-weight:600;color:#5b6b82}
.xpressui-sticky-status.is-dirty{color:#b45309}
.xpressui-sticky-status.is-saved{color:#0a7a32}
.xpressui-reset-chip{display:inline-flex;align-items:center;justify-content:center;padding:5px 10px;border-radius:999px;border:1px solid #d6def2;background:#fff;color:#183ea8;font-size:11px;font-weight:700;letter-spacing:.04em;cursor:pointer;transition:background .15s ease,border-color .15s ease,color .15s ease}
.xpressui-reset-chip:hover{background:#f5f9ff;border-color:#b7c8eb}
.xpressui-field-block{padding:12px 14px;border:1px solid #e5edf8;border-radius:10px;background:#fff}
.xpressui-field-block.is-customized{border-color:#b8ccff;background:linear-gradient(180deg,#f9fbff 0%,#f3f7ff 100%)}
.xpressui-field-block-header{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:0 0 10px}
.xpressui-field-block-title{font-size:13px;font-weight:700;color:#122033}
.xpressui-field-block-type{font-size:11px;color:#5b6b82;text-transform:uppercase;letter-spacing:.06em}
.xpressui-field-block-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px 12px}
.xpressui-field-control label{display:block;font-size:12px;color:#5b6b82;margin-bottom:4px;font-weight:600}
.xpressui-field-control.is-full{grid-column:1 / -1}
.xpressui-field-control-row{grid-column:1 / -1;display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px 12px}
.xpressui-choice-group{margin-top:10px;padding-left:12px;border-left:3px solid #d8e3f7}
.xpressui-choice-row{display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap}
.xpressui-choice-label{min-width:120px;color:#5b6b82;font-size:12px;font-weight:600}
.xpressui-muted{color:#5b6b82;font-size:12px}
.xpressui-input-invalid{border-color:#d63638 !important;box-shadow:0 0 0 1px rgba(214,54,56,.18)}
.xpressui-inline-field-error{margin:6px 0 0;color:#b42318;font-size:12px;font-weight:600}
@media (max-width: 782px){.xpressui-field-control-row{grid-template-columns:1fr}}
</style>';
	echo '<div class="xpressui-pro-header">';
	echo '<div class="xpressui-pro-header-left">';
	echo '<h1>' . esc_html__( 'Customize Workflow', 'xpressui-bridge-pro' ) . '</h1>';
	echo '<p><strong style="color:rgba(255,255,255,.9)">' . esc_html( $slug ) . '</strong> &mdash; '
		. esc_html__( 'Override labels, section titles, field settings and navigation.', 'xpressui-bridge-pro' ) . '</p>';
	echo '</div>';
	echo '<div class="xpressui-pro-header-right">';
	echo '<span class="xpressui-pro-badge">✦ &nbsp;XPressUI Pro</span>';
	echo '<a href="' . esc_url( $back_url ) . '" class="xpressui-pro-back">&larr; ' . esc_html__( 'Back to Manage Workflows', 'xpressui-bridge-pro' ) . '</a>';
	echo '</div>';
	echo '</div>';

	echo '<div class="xpressui-pro-summary">';
	echo '<div class="xpressui-pro-summary-chip"><strong>' . esc_html( (string) $summary_stats['section_count'] ) . '</strong><span>' . esc_html__( 'Sections customized', 'xpressui-bridge-pro' ) . '</span></div>';
	echo '<div class="xpressui-pro-summary-chip"><strong>' . esc_html( (string) $summary_stats['field_count'] ) . '</strong><span>' . esc_html__( 'Fields overridden', 'xpressui-bridge-pro' ) . '</span></div>';
	echo '<div class="xpressui-pro-summary-chip"><strong>' . esc_html( (string) $summary_stats['choice_count'] ) . '</strong><span>' . esc_html__( 'Choice labels changed', 'xpressui-bridge-pro' ) . '</span></div>';
	echo '<div class="xpressui-pro-summary-chip"><strong>' . esc_html( (string) $summary_stats['navigation_count'] ) . '</strong><span>' . esc_html__( 'Navigation labels', 'xpressui-bridge-pro' ) . '</span></div>';
	if ( $summary_stats['has_project_settings'] ) {
		echo '<div class="xpressui-pro-summary-chip"><strong>' . esc_html__( 'Active', 'xpressui-bridge-pro' ) . '</strong><span>' . esc_html__( 'Project settings', 'xpressui-bridge-pro' ) . '</span></div>';
	}
	if ( $summary_stats['has_submit_feedback'] ) {
		echo '<div class="xpressui-pro-summary-chip"><strong>' . esc_html__( 'Active', 'xpressui-bridge-pro' ) . '</strong><span>' . esc_html__( 'Submit feedback', 'xpressui-bridge-pro' ) . '</span></div>';
	}
	echo '</div>';

	if ( ! empty( $notice_messages ) ) {
		$inline_notice_class = 'xpressui-inline-notice';
		if ( $notice_class === 'notice-error' ) {
			$inline_notice_class .= ' is-error';
		} elseif ( $notice_class === 'notice-warning' ) {
			$inline_notice_class .= ' is-warning';
		}
		echo '<div class="' . esc_attr( $inline_notice_class ) . '" role="status" aria-live="polite">';
		echo '<p>' . esc_html( (string) array_shift( $notice_messages ) ) . '</p>';
		if ( ! empty( $notice_messages ) ) {
			echo '<ul>';
			foreach ( $notice_messages as $notice_message ) {
				echo '<li>' . esc_html( (string) $notice_message ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	echo '<form method="post" action="">';
	wp_nonce_field( 'xpressui_overlay_' . $slug, 'xpressui_overlay_nonce' );

	echo '<div class="xpressui-pro-toolbar">';
	echo '<button type="button" class="xpressui-pro-details-toggle" data-target="all">' . esc_html__( 'Open all sections', 'xpressui-bridge-pro' ) . '</button>';
	echo '<button type="button" class="xpressui-pro-details-toggle" data-target="customized">' . esc_html__( 'Open customized only', 'xpressui-bridge-pro' ) . '</button>';
	echo '<button type="button" class="xpressui-pro-details-toggle" data-target="none">' . esc_html__( 'Collapse all', 'xpressui-bridge-pro' ) . '</button>';
	echo '<button type="button" class="xpressui-pro-details-toggle is-accent" data-target="jump-customized">' . esc_html__( 'Jump to first customized section', 'xpressui-bridge-pro' ) . '</button>';
	echo '<button type="button" class="xpressui-pro-filter-toggle" data-filter="customized-only" aria-pressed="false">' . esc_html__( 'Customized cards only', 'xpressui-bridge-pro' ) . '</button>';
	echo '<div class="xpressui-pro-toolbar-search">';
	echo '<input type="search" value="" placeholder="' . esc_attr__( 'Search sections, fields, labels…', 'xpressui-bridge-pro' ) . '" data-xpressui-card-search />';
	echo '<button type="button" class="xpressui-pro-clear-search" data-action="clear-search">' . esc_html__( 'Clear', 'xpressui-bridge-pro' ) . '</button>';
	echo '<span class="xpressui-pro-toolbar-meta"><strong data-xpressui-visible-count>0</strong> ' . esc_html__( 'visible', 'xpressui-bridge-pro' ) . '</span>';
	echo '</div>';
	echo '</div>';
	echo '<div class="xpressui-pro-empty-state" data-xpressui-empty-state>' . esc_html__( 'No customization cards match the current filters. Try clearing the search or turning off the customized-only filter.', 'xpressui-bridge-pro' ) . '</div>';

	// Sticky save bar.
	echo '<div class="xpressui-sticky-actions">';
	echo '<span class="xpressui-sticky-status is-saved" data-xpressui-dirty-status="saved">' . esc_html__( 'No unsaved changes', 'xpressui-bridge-pro' ) . '</span>';
	echo '<div class="xpressui-sticky-actions-buttons">';
	submit_button( __( 'Save Customizations', 'xpressui-bridge-pro' ), 'primary', 'xpressui_save_overlay', false );
	submit_button(
		__( 'Reset to Defaults', 'xpressui-bridge-pro' ),
		'secondary',
		'xpressui_reset_overlay',
		false,
		[
			'onclick' => "return window.confirm('" . esc_js( __( 'Reset all customizations for this workflow and restore the pack defaults?', 'xpressui-bridge-pro' ) ) . "');",
		]
	);
	echo '</div>';
	echo '</div>';

	// -----------------------------------------------------------------------
	// Card: Project Settings
	// -----------------------------------------------------------------------

	$project_settings_count = 0;
	foreach ( [ $ov_project_name, $proj_settings['notifyEmail'], $proj_settings['redirectUrl'] ] as $project_setting_value ) {
		if ( (string) $project_setting_value !== '' ) {
			$project_settings_count++;
		}
	}
	if ( $proj_settings['showProjectTitle'] === '1' ) {
		$project_settings_count++;
	}
	if ( $proj_settings['showRequiredFieldsNote'] === '1' ) {
		$project_settings_count++;
	}
	echo '<details class="xpressui-admin-card" open data-xpressui-card-type="project-settings" data-xpressui-customized="' . ( $summary_stats['has_project_settings'] ? '1' : '0' ) . '" data-xpressui-search-text="project settings custom form title notification email post submit redirect redirect url form title required fields note wordpress page title" data-xpressui-reset-scope="project-settings" id="xpressui-pro-card-project-settings">';
	echo '<summary class="xpressui-card-summary"><h2>' . esc_html__( 'Project Settings', 'xpressui-bridge-pro' ) . '</h2><span class="xpressui-card-meta">';
	if ( $summary_stats['has_project_settings'] ) {
		echo '<span class="xpressui-card-badge is-customized">' . esc_html__( 'Customized', 'xpressui-bridge-pro' ) . '</span>';
		echo '<button type="button" class="xpressui-reset-chip" data-xpressui-reset-trigger="project-settings">' . esc_html__( 'Restore block', 'xpressui-bridge-pro' ) . '</button>';
	}
	echo '<span class="xpressui-card-badge">' . esc_html( (string) $project_settings_count ) . ' ' . esc_html__( 'Active', 'xpressui-bridge-pro' ) . '</span>';
	echo '</span></summary>';
	echo '<div class="xpressui-card-body"><table class="form-table"><tbody>';

	xpressui_pro_row(
		'xpressui_overlay_project_name',
		__( 'Custom form title', 'xpressui-bridge-pro' ),
		'<input type="text" id="xpressui_overlay_project_name" name="xpressui_overlay_project_name" class="regular-text" value="' . esc_attr( $ov_project_name ) . '" placeholder="' . esc_attr( $original_title ) . '" />'
		. '<p class="description">' . esc_html__( 'Pack default:', 'xpressui-bridge-pro' ) . ' <em>' . esc_html( $original_title ) . '</em></p>'
	);

	xpressui_pro_row(
		'xpressui_notify_email',
		__( 'Notification email', 'xpressui-bridge-pro' ),
		'<input type="email" id="xpressui_notify_email" name="xpressui_notify_email" class="regular-text' . ( in_array( 'xpressui_notify_email', $invalid_fields, true ) ? ' xpressui-input-invalid' : '' ) . '" value="' . esc_attr( $proj_settings['notifyEmail'] ) . '" />'
		. ( in_array( 'xpressui_notify_email', $invalid_fields, true ) ? '<p class="xpressui-inline-field-error">' . esc_html__( 'Enter a valid email address to keep notifications enabled.', 'xpressui-bridge-pro' ) . '</p>' : '' )
		. '<p class="description">' . esc_html__( 'Receive an email for each new submission.', 'xpressui-bridge-pro' ) . '</p>'
	);

	xpressui_pro_row(
		'xpressui_redirect_url',
		__( 'Post-submit redirect', 'xpressui-bridge-pro' ),
		'<input type="url" id="xpressui_redirect_url" name="xpressui_redirect_url" class="regular-text' . ( in_array( 'xpressui_redirect_url', $invalid_fields, true ) ? ' xpressui-input-invalid' : '' ) . '" value="' . esc_attr( $proj_settings['redirectUrl'] ) . '" placeholder="https://" />'
		. ( in_array( 'xpressui_redirect_url', $invalid_fields, true ) ? '<p class="xpressui-inline-field-error">' . esc_html__( 'Enter a full valid URL, including https://, to keep the redirect active.', 'xpressui-bridge-pro' ) . '</p>' : '' )
		. '<p class="description">' . esc_html__( 'Redirect users here after a successful submission.', 'xpressui-bridge-pro' ) . '</p>'
	);

	xpressui_pro_row(
		'xpressui_show_project_title',
		__( 'Form title', 'xpressui-bridge-pro' ),
		'<label><input type="checkbox" id="xpressui_show_project_title" name="xpressui_show_project_title" value="1"' . checked( $proj_settings['showProjectTitle'], '1', false ) . ' /> '
		. esc_html__( 'Display the workflow title above the form inside the WordPress page.', 'xpressui-bridge-pro' ) . '</label>'
		. '<p class="description">' . esc_html__( 'Disabled by default to avoid duplicating the WordPress page title.', 'xpressui-bridge-pro' ) . '</p>'
	);

	xpressui_pro_row(
		'xpressui_show_required_fields_note',
		__( 'Required fields note', 'xpressui-bridge-pro' ),
		'<label><input type="checkbox" id="xpressui_show_required_fields_note" name="xpressui_show_required_fields_note" value="1"' . checked( $proj_settings['showRequiredFieldsNote'], '1', false ) . ' /> '
		. esc_html__( 'Display the "* Required fields" note above the form.', 'xpressui-bridge-pro' ) . '</label>'
		. '<p class="description">' . esc_html__( 'Disabled by default for a cleaner WordPress page layout.', 'xpressui-bridge-pro' ) . '</p>'
	);

	echo '</tbody></table></div>';
	echo '</details>';

	// -----------------------------------------------------------------------
	// Card: Submit feedback
	// -----------------------------------------------------------------------

	$submit_feedback_count = 0;
	foreach ( [ $ov_success_message, $ov_error_message ] as $submit_feedback_value ) {
		if ( (string) $submit_feedback_value !== '' ) {
			$submit_feedback_count++;
		}
	}
	echo '<details class="xpressui-admin-card" open data-xpressui-card-type="submit-feedback" data-xpressui-customized="' . ( $summary_stats['has_submit_feedback'] ? '1' : '0' ) . '" data-xpressui-search-text="submit feedback success message error message submission failed submission received" data-xpressui-reset-scope="submit-feedback" id="xpressui-pro-card-submit-feedback">';
	echo '<summary class="xpressui-card-summary"><h2>' . esc_html__( 'Submit Feedback', 'xpressui-bridge-pro' ) . '</h2><span class="xpressui-card-meta">';
	if ( $summary_stats['has_submit_feedback'] ) {
		echo '<span class="xpressui-card-badge is-customized">' . esc_html__( 'Customized', 'xpressui-bridge-pro' ) . '</span>';
		echo '<button type="button" class="xpressui-reset-chip" data-xpressui-reset-trigger="submit-feedback">' . esc_html__( 'Restore block', 'xpressui-bridge-pro' ) . '</button>';
	}
	echo '<span class="xpressui-card-badge">' . esc_html( (string) $submit_feedback_count ) . ' ' . esc_html__( 'Messages', 'xpressui-bridge-pro' ) . '</span>';
	echo '</span></summary>';
	echo '<div class="xpressui-card-body"><table class="form-table"><tbody>';

	xpressui_pro_row(
		'xpressui_overlay_success_message',
		__( 'Success message', 'xpressui-bridge-pro' ),
		'<input type="text" id="xpressui_overlay_success_message" name="xpressui_overlay_success_message" class="large-text" value="' . esc_attr( $ov_success_message ) . '" />'
	);

	xpressui_pro_row(
		'xpressui_overlay_error_message',
		__( 'Error message', 'xpressui-bridge-pro' ),
		'<input type="text" id="xpressui_overlay_error_message" name="xpressui_overlay_error_message" class="large-text" value="' . esc_attr( $ov_error_message ) . '" />'
	);

	echo '</tbody></table></div>';
	echo '</details>';

	// -----------------------------------------------------------------------
	// Card: Navigation labels
	// -----------------------------------------------------------------------

	$nav_open = ! empty( $ov_navigation ) ? ' open' : '';
	$nav_fields = [
		'prev'   => [ __( 'Back button', 'xpressui-bridge-pro' ), (string) ( $pack_nav['prevLabel'] ?? 'Back' ) ],
		'next'   => [ __( 'Continue button', 'xpressui-bridge-pro' ), (string) ( $pack_nav['nextLabel'] ?? 'Continue' ) ],
		'submit' => [ __( 'Submit button', 'xpressui-bridge-pro' ), (string) ( $pack_nav['submitLabel'] ?? 'Submit' ) ],
	];
	echo '<details class="xpressui-admin-card"' . $nav_open . ' data-xpressui-card-type="navigation" data-xpressui-customized="' . ( ! empty( $ov_navigation ) ? '1' : '0' ) . '" data-xpressui-search-text="navigation labels back continue submit buttons" data-xpressui-reset-scope="navigation" id="xpressui-pro-card-navigation">';
	echo '<summary class="xpressui-card-summary"><h2>' . esc_html__( 'Navigation Labels', 'xpressui-bridge-pro' ) . '</h2><span class="xpressui-card-meta">';
	if ( ! empty( $ov_navigation ) ) {
		echo '<span class="xpressui-card-badge is-customized">' . esc_html__( 'Customized', 'xpressui-bridge-pro' ) . '</span>';
		echo '<button type="button" class="xpressui-reset-chip" data-xpressui-reset-trigger="navigation">' . esc_html__( 'Restore block', 'xpressui-bridge-pro' ) . '</button>';
	}
	echo '<span class="xpressui-card-badge">' . esc_html( (string) count( $nav_fields ) ) . ' ' . esc_html__( 'Buttons', 'xpressui-bridge-pro' ) . '</span>';
	echo '</span></summary>';
	echo '<div class="xpressui-card-body"><table class="form-table"><tbody>';

	foreach ( $nav_fields as $nav_key => [ $nav_label, $nav_default ] ) {
		$current_val = (string) ( $ov_navigation[ $nav_key ] ?? '' );
		xpressui_pro_row(
			'xpressui_overlay_nav_' . $nav_key,
			$nav_label,
			'<input type="text" id="xpressui_overlay_nav_' . esc_attr( $nav_key ) . '" name="xpressui_overlay_nav_' . esc_attr( $nav_key ) . '" class="regular-text" value="' . esc_attr( $current_val ) . '" placeholder="' . esc_attr( $nav_default ) . '" />'
			. '<p class="description">' . esc_html__( 'Pack default:', 'xpressui-bridge-pro' ) . ' <em>' . esc_html( $nav_default ) . '</em></p>'
		);
	}

	echo '</tbody></table></div>';
	echo '</details>';

	// -----------------------------------------------------------------------
	// Cards: Sections and fields
	// -----------------------------------------------------------------------

	foreach ( $sections as $section ) {
		$section_name  = (string) ( $section['name'] ?? '' );
		$section_label = (string) ( $section['label'] ?? $section_name );
		$fields        = isset( $section['fields'] ) && is_array( $section['fields'] ) ? $section['fields'] : [];
		if ( $section_name === '' || empty( $fields ) ) {
			continue;
		}

		$current_section_label = (string) ( $ov_sections[ $section_name ] ?? '' );

		// Open the card if any customization exists for this section or its fields.
		$section_has_custom = $current_section_label !== '';
		if ( ! $section_has_custom ) {
			foreach ( $fields as $f ) {
				$fn = (string) ( $f['name'] ?? '' );
				if ( $fn !== '' && isset( $ov_fields[ $fn ] ) && ! empty( $ov_fields[ $fn ] ) ) {
					$section_has_custom = true;
					break;
				}
			}
		}
		$card_open = $section_has_custom ? ' open' : '';

		$customized_field_count = 0;
		foreach ( $fields as $field_for_count ) {
			$field_name_for_count = (string) ( $field_for_count['name'] ?? '' );
			if ( $field_name_for_count !== '' && isset( $ov_fields[ $field_name_for_count ] ) && ! empty( $ov_fields[ $field_name_for_count ] ) ) {
				$customized_field_count++;
			}
		}

		$section_search_tokens = [ $section_name, $section_label ];
		foreach ( $fields as $field_for_search ) {
			$section_search_tokens[] = (string) ( $field_for_search['name'] ?? '' );
			$section_search_tokens[] = (string) ( $field_for_search['label'] ?? '' );
			$section_search_tokens[] = (string) ( $field_for_search['type'] ?? '' );
		}
		$section_search_text = strtolower( trim( implode( ' ', array_filter( array_map( 'strval', $section_search_tokens ) ) ) ) );

		echo '<details class="xpressui-admin-card"' . $card_open . ' data-xpressui-card-type="section" data-xpressui-customized="' . ( $section_has_custom ? '1' : '0' ) . '" data-xpressui-search-text="' . esc_attr( $section_search_text ) . '" data-xpressui-reset-scope="section-' . esc_attr( $section_name ) . '" id="xpressui-pro-card-' . esc_attr( $section_name ) . '">';
		echo '<summary class="xpressui-card-summary"><h2>' . esc_html( $section_label ) . '</h2><span class="xpressui-card-meta">';
		if ( $section_has_custom ) {
			echo '<span class="xpressui-card-badge is-customized">' . esc_html__( 'Customized', 'xpressui-bridge-pro' ) . '</span>';
			echo '<button type="button" class="xpressui-reset-chip" data-xpressui-reset-trigger="section-' . esc_attr( $section_name ) . '">' . esc_html__( 'Restore section', 'xpressui-bridge-pro' ) . '</button>';
		}
		echo '<span class="xpressui-card-badge">' . esc_html( (string) count( $fields ) ) . ' ' . esc_html__( 'Fields', 'xpressui-bridge-pro' ) . '</span>';
		if ( $customized_field_count > 0 ) {
			echo '<span class="xpressui-card-badge">' . esc_html( (string) $customized_field_count ) . ' ' . esc_html__( 'Overrides', 'xpressui-bridge-pro' ) . '</span>';
		}
		echo '</span></summary>';
		echo '<div class="xpressui-card-body"><table class="form-table"><tbody>';

		// Section label row.
		xpressui_pro_row(
			'xpressui_overlay_sections[' . esc_attr( $section_name ) . ']',
			'<strong>' . esc_html__( 'Section label', 'xpressui-bridge-pro' ) . '</strong>',
			'<input type="text" name="xpressui_overlay_sections[' . esc_attr( $section_name ) . ']" class="regular-text" value="' . esc_attr( $current_section_label ) . '" placeholder="' . esc_attr( $section_label ) . '" />'
			. '<p class="description">' . esc_html__( 'Pack default:', 'xpressui-bridge-pro' ) . ' <em>' . esc_html( $section_label ) . '</em></p>'
		);

		echo '<tr><td colspan="2"><hr style="border:none;border-top:1px solid #eee;margin:4px 0 8px"></td></tr>';

		// Field rows.
		foreach ( $fields as $field ) {
			$fname       = (string) ( $field['name'] ?? '' );
			$flabel      = (string) ( $field['label'] ?? $fname );
			$ftype       = (string) ( $field['type'] ?? '' );
			$choices     = isset( $field['choices'] ) && is_array( $field['choices'] ) ? $field['choices'] : [];
			$pack_req    = ! empty( $field['required'] );
			if ( $fname === '' ) {
				continue;
			}

			$fo = isset( $ov_fields[ $fname ] ) && is_array( $ov_fields[ $fname ] ) ? $ov_fields[ $fname ] : [];

			$ov_label  = (string) ( $fo['label'] ?? '' );
			$ov_req    = array_key_exists( 'required', $fo ) ? ( $fo['required'] ? '1' : '0' ) : '';
			$ov_ph     = (string) ( $fo['placeholder'] ?? '' );
			$ov_desc   = (string) ( $fo['desc'] ?? '' );
			$ov_errmsg = (string) ( $fo['error_message'] ?? '' );
			$ov_pattern = isset( $fo['pattern'] ) ? (string) $fo['pattern'] : '';
			$ov_min_len = isset( $fo['min_len'] ) ? (string) $fo['min_len'] : '';
			$ov_max_len = isset( $fo['max_len'] ) ? (string) $fo['max_len'] : '';
			$ov_min_value = isset( $fo['min_value'] ) ? (string) $fo['min_value'] : '';
			$ov_max_value = isset( $fo['max_value'] ) ? (string) $fo['max_value'] : '';
			$ov_step_value = isset( $fo['step_value'] ) ? (string) $fo['step_value'] : '';
			$ov_min_choices = isset( $fo['min_choices'] ) ? (string) $fo['min_choices'] : '';
			$ov_max_choices = isset( $fo['max_choices'] ) ? (string) $fo['max_choices'] : '';
			$ov_accept = isset( $fo['accept'] ) ? (string) $fo['accept'] : '';
			$ov_upload_accept_label = isset( $fo['upload_accept_label'] ) ? (string) $fo['upload_accept_label'] : '';
			$ov_max_file_size_mb = isset( $fo['max_file_size_mb'] ) ? (string) $fo['max_file_size_mb'] : '';
			$ov_file_type_error_message = isset( $fo['file_type_error_message'] ) ? (string) $fo['file_type_error_message'] : '';
			$ov_file_size_error_message = isset( $fo['file_size_error_message'] ) ? (string) $fo['file_size_error_message'] : '';
			$field_has_custom = ! empty( $fo );
			$text_validation_types = [ 'text', 'email', 'tel', 'url', 'search', 'slug', 'textarea', 'rich-editor' ];
			$pattern_validation_types = [ 'text', 'email', 'tel', 'url', 'search', 'slug' ];
			$numeric_validation_types = [ 'number', 'price', 'integer', 'age', 'tax', 'date', 'time', 'datetime' ];
			$upload_validation_types = [ 'file', 'upload-image', 'camera-photo', 'qr-scan', 'document-scan' ];
			$multi_choice_validation_types = [ 'select-multiple', 'checkboxes' ];
			$supports_choice_limits = ! empty( $choices ) && ( ! empty( $field['multiple'] ) || in_array( $ftype, $multi_choice_validation_types, true ) );
			$supports_choice_labels = xpressui_pro_field_has_choices( $field );

			$field_prefix = 'xpressui_overlay_fields[' . esc_attr( $fname ) . ']';
			$header       = '<div class="xpressui-field-block' . ( $field_has_custom ? ' is-customized' : '' ) . '" data-xpressui-reset-scope="field-' . esc_attr( $fname ) . '">';
			$header      .= '<div class="xpressui-field-block-header">';
			$header      .= '<div><div class="xpressui-field-block-title">' . esc_html( $flabel ) . '</div><div class="xpressui-field-block-type">' . esc_html( $ftype ) . '</div></div>';
			if ( $field_has_custom ) {
				$header .= '<div style="display:flex;align-items:center;gap:8px">';
				$header .= '<span class="xpressui-card-badge is-customized">' . esc_html__( 'Customized', 'xpressui-bridge-pro' ) . '</span>';
				$header .= '<button type="button" class="xpressui-reset-chip" data-xpressui-reset-trigger="field-' . esc_attr( $fname ) . '">' . esc_html__( 'Restore this field', 'xpressui-bridge-pro' ) . '</button>';
				$header .= '</div>';
			}
			$header .= '</div>';
			$header .= '<div class="xpressui-field-block-grid">';

			$html = '';

			// Label.
			$html .= '<div class="xpressui-field-control">';
			$html .= '<label>' . esc_html__( 'Label', 'xpressui-bridge-pro' ) . '</label>';
			$html .= '<input type="text" name="' . $field_prefix . '[label]" class="regular-text" value="' . esc_attr( $ov_label ) . '" placeholder="' . esc_attr( $flabel ) . '" />';
			$html .= '</div>';

			// Required (select, 3 states).
			$req_options = [
				''  => __( 'Pack default', 'xpressui-bridge-pro' ) . ' (' . ( $pack_req ? __( 'required', 'xpressui-bridge-pro' ) : __( 'optional', 'xpressui-bridge-pro' ) ) . ')',
				'1' => __( 'Required', 'xpressui-bridge-pro' ),
				'0' => __( 'Optional', 'xpressui-bridge-pro' ),
			];
			$html .= '<div class="xpressui-field-control">';
			$html .= '<label>' . esc_html__( 'Required', 'xpressui-bridge-pro' ) . '</label>';
			$html .= '<select name="' . $field_prefix . '[required]">';
			foreach ( $req_options as $opt_val => $opt_label ) {
				$html .= '<option value="' . esc_attr( (string) $opt_val ) . '"' . selected( $ov_req, (string) $opt_val, false ) . '>' . esc_html( $opt_label ) . '</option>';
			}
			$html .= '</select>';
			$html .= '</div>';

			// Placeholder (text-like fields).
			$text_types = [ 'text', 'email', 'tel', 'url', 'number', 'price', 'integer', 'age', 'tax', 'date', 'time', 'datetime', 'search', 'slug', 'textarea', 'rich-editor' ];
			if ( in_array( $ftype, $text_types, true ) ) {
				$html .= '<div class="xpressui-field-control">';
				$html .= '<label>' . esc_html__( 'Placeholder', 'xpressui-bridge-pro' ) . '</label>';
				$html .= '<input type="text" name="' . $field_prefix . '[placeholder]" class="regular-text" value="' . esc_attr( $ov_ph ) . '" placeholder="' . esc_attr( (string) ( $field['placeholder'] ?? '' ) ) . '" />';
				$html .= '</div>';
			}

			// Description.
			$html .= '<div class="xpressui-field-control is-full">';
			$html .= '<label>' . esc_html__( 'Help text', 'xpressui-bridge-pro' ) . '</label>';
			$html .= '<textarea name="' . $field_prefix . '[desc]" class="large-text" rows="2">' . esc_textarea( $ov_desc ) . '</textarea>';
			$html .= '</div>';

			// Error message.
			$pack_errmsg = (string) ( $field['error_message'] ?? '' );
			$html .= '<div class="xpressui-field-control is-full">';
			$html .= '<label>' . esc_html__( 'Error message', 'xpressui-bridge-pro' ) . '</label>';
			$html .= '<input type="text" name="' . $field_prefix . '[error_message]" class="large-text" value="' . esc_attr( $ov_errmsg ) . '" placeholder="' . esc_attr( $pack_errmsg ) . '" />';
			$html .= '</div>';

			if ( in_array( $ftype, $text_validation_types, true ) ) {
				$html .= '<div class="xpressui-field-control-row">';
				$html .= '<div class="xpressui-field-control">';
				$html .= '<label>' . esc_html__( 'Min length', 'xpressui-bridge-pro' ) . '</label>';
				$html .= '<input type="number" min="0" step="1" id="xpressui_overlay_fields_' . esc_attr( $fname ) . '_min_len" name="' . $field_prefix . '[min_len]" class="small-text' . ( in_array( 'xpressui_overlay_fields_' . $fname . '_min_len', $invalid_fields, true ) ? ' xpressui-input-invalid' : '' ) . '" value="' . esc_attr( $ov_min_len ) . '" placeholder="" />';
				$html .= '</div>';
				$html .= '<div class="xpressui-field-control">';
				$html .= '<label>' . esc_html__( 'Max length', 'xpressui-bridge-pro' ) . '</label>';
				$html .= '<input type="number" min="0" step="1" id="xpressui_overlay_fields_' . esc_attr( $fname ) . '_max_len" name="' . $field_prefix . '[max_len]" class="small-text' . ( in_array( 'xpressui_overlay_fields_' . $fname . '_max_len', $invalid_fields, true ) ? ' xpressui-input-invalid' : '' ) . '" value="' . esc_attr( $ov_max_len ) . '" placeholder="" />';
				$html .= '</div>';
				$html .= '</div>';
				if ( in_array( $ftype, $pattern_validation_types, true ) ) {
					$html .= '<div class="xpressui-field-control is-full">';
					$html .= '<label>' . esc_html__( 'Pattern', 'xpressui-bridge-pro' ) . '</label>';
					$html .= '<input type="text" name="' . $field_prefix . '[pattern]" class="large-text" value="' . esc_attr( $ov_pattern ) . '" placeholder="' . esc_attr( (string) ( $field['pattern'] ?? '' ) ) . '" />';
					$html .= '<p class="description">' . esc_html__( 'Optional regex pattern enforced by the runtime schema. Use ^...$ if you want to match the whole value.', 'xpressui-bridge-pro' ) . '</p>';
					$html .= '</div>';
				}
			}

			if ( in_array( $ftype, $numeric_validation_types, true ) ) {
				$html .= '<div class="xpressui-field-control-row">';
				$html .= '<div class="xpressui-field-control">';
				$html .= '<label>' . esc_html__( 'Min value', 'xpressui-bridge-pro' ) . '</label>';
				$html .= '<input type="text" id="xpressui_overlay_fields_' . esc_attr( $fname ) . '_min_value" name="' . $field_prefix . '[min_value]" class="small-text' . ( in_array( 'xpressui_overlay_fields_' . $fname . '_min_value', $invalid_fields, true ) ? ' xpressui-input-invalid' : '' ) . '" value="' . esc_attr( $ov_min_value ) . '" placeholder="' . esc_attr( (string) ( $field['min_value'] ?? '' ) ) . '" />';
				$html .= '</div>';
				$html .= '<div class="xpressui-field-control">';
				$html .= '<label>' . esc_html__( 'Max value', 'xpressui-bridge-pro' ) . '</label>';
				$html .= '<input type="text" id="xpressui_overlay_fields_' . esc_attr( $fname ) . '_max_value" name="' . $field_prefix . '[max_value]" class="small-text' . ( in_array( 'xpressui_overlay_fields_' . $fname . '_max_value', $invalid_fields, true ) ? ' xpressui-input-invalid' : '' ) . '" value="' . esc_attr( $ov_max_value ) . '" placeholder="' . esc_attr( (string) ( $field['max_value'] ?? '' ) ) . '" />';
				$html .= '</div>';
				$html .= '<div class="xpressui-field-control">';
				$html .= '<label>' . esc_html__( 'Step', 'xpressui-bridge-pro' ) . '</label>';
				$html .= '<input type="text" id="xpressui_overlay_fields_' . esc_attr( $fname ) . '_step_value" name="' . $field_prefix . '[step_value]" class="small-text' . ( in_array( 'xpressui_overlay_fields_' . $fname . '_step_value', $invalid_fields, true ) ? ' xpressui-input-invalid' : '' ) . '" value="' . esc_attr( $ov_step_value ) . '" placeholder="' . esc_attr( (string) ( $field['step_value'] ?? '' ) ) . '" />';
				$html .= '</div>';
				$html .= '</div>';
			}

			if ( $supports_choice_limits ) {
				$html .= '<div class="xpressui-field-control-row">';
				$html .= '<div class="xpressui-field-control">';
				$html .= '<label>' . esc_html__( 'Minimum choices', 'xpressui-bridge-pro' ) . '</label>';
				$html .= '<input type="number" min="0" step="1" id="xpressui_overlay_fields_' . esc_attr( $fname ) . '_min_choices" name="' . $field_prefix . '[min_choices]" class="small-text' . ( in_array( 'xpressui_overlay_fields_' . $fname . '_min_choices', $invalid_fields, true ) ? ' xpressui-input-invalid' : '' ) . '" value="' . esc_attr( $ov_min_choices ) . '" placeholder="' . esc_attr( (string) ( $field['min_choices'] ?? '' ) ) . '" />';
				$html .= '</div>';
				$html .= '<div class="xpressui-field-control">';
				$html .= '<label>' . esc_html__( 'Maximum choices', 'xpressui-bridge-pro' ) . '</label>';
				$html .= '<input type="number" min="0" step="1" id="xpressui_overlay_fields_' . esc_attr( $fname ) . '_max_choices" name="' . $field_prefix . '[max_choices]" class="small-text' . ( in_array( 'xpressui_overlay_fields_' . $fname . '_max_choices', $invalid_fields, true ) ? ' xpressui-input-invalid' : '' ) . '" value="' . esc_attr( $ov_max_choices ) . '" placeholder="' . esc_attr( (string) ( $field['max_choices'] ?? '' ) ) . '" />';
				$html .= '</div>';
				$html .= '</div>';
			}

			if ( in_array( $ftype, $upload_validation_types, true ) ) {
				$html .= '<div class="xpressui-field-control">';
				$html .= '<label>' . esc_html__( 'Max file size (MB)', 'xpressui-bridge-pro' ) . '</label>';
				$html .= '<input type="text" id="xpressui_overlay_fields_' . esc_attr( $fname ) . '_max_file_size_mb" name="' . $field_prefix . '[max_file_size_mb]" class="small-text' . ( in_array( 'xpressui_overlay_fields_' . $fname . '_max_file_size_mb', $invalid_fields, true ) ? ' xpressui-input-invalid' : '' ) . '" value="' . esc_attr( $ov_max_file_size_mb ) . '" placeholder="' . esc_attr( (string) ( $field['maxFileSizeMb'] ?? '' ) ) . '" />';
				$html .= '</div>';
				$html .= '<div class="xpressui-field-control is-full">';
				$html .= '<label>' . esc_html__( 'Accepted file types', 'xpressui-bridge-pro' ) . '</label>';
				$html .= '<input type="text" name="' . $field_prefix . '[accept]" class="large-text" value="' . esc_attr( $ov_accept ) . '" placeholder="' . esc_attr( (string) ( $field['accept'] ?? '' ) ) . '" />';
				$html .= '<p class="description">' . esc_html__( 'Example: image/*,application/pdf', 'xpressui-bridge-pro' ) . '</p>';
				$html .= '</div>';
				$html .= '<div class="xpressui-field-control is-full">';
				$html .= '<label>' . esc_html__( 'Accepted file types label', 'xpressui-bridge-pro' ) . '</label>';
				$html .= '<input type="text" name="' . $field_prefix . '[upload_accept_label]" class="large-text" value="' . esc_attr( $ov_upload_accept_label ) . '" placeholder="' . esc_attr( (string) ( $field['upload_accept_label'] ?? '' ) ) . '" />';
				$html .= '</div>';
				$html .= '<div class="xpressui-field-control is-full">';
				$html .= '<label>' . esc_html__( 'File type error message', 'xpressui-bridge-pro' ) . '</label>';
				$html .= '<input type="text" name="' . $field_prefix . '[file_type_error_message]" class="large-text" value="' . esc_attr( $ov_file_type_error_message ) . '" placeholder="' . esc_attr( (string) ( $field['fileTypeErrorMsg'] ?? '' ) ) . '" />';
				$html .= '</div>';
				$html .= '<div class="xpressui-field-control is-full">';
				$html .= '<label>' . esc_html__( 'File size error message', 'xpressui-bridge-pro' ) . '</label>';
				$html .= '<input type="text" name="' . $field_prefix . '[file_size_error_message]" class="large-text" value="' . esc_attr( $ov_file_size_error_message ) . '" placeholder="' . esc_attr( (string) ( $field['fileSizeErrorMsg'] ?? '' ) ) . '" />';
				$html .= '</div>';
			}

			// Choice labels.
			if ( $supports_choice_labels ) {
				$ov_choices = isset( $fo['choices'] ) && is_array( $fo['choices'] ) ? $fo['choices'] : [];
				$html      .= '<div class="xpressui-field-control is-full"><div class="xpressui-choice-group">';
				$html      .= '<p class="description" style="margin-bottom:6px">' . esc_html__( 'Choice labels:', 'xpressui-bridge-pro' ) . '</p>';
				foreach ( $choices as $choice ) {
					$cv = (string) ( $choice['value'] ?? '' );
					$cl = (string) ( $choice['label'] ?? $cv );
					if ( $cv === '' ) {
						continue;
					}
					$ov_cl = (string) ( $ov_choices[ $cv ] ?? '' );
					$html .= '<div class="xpressui-choice-row">';
					$html .= '<span class="xpressui-choice-label">' . esc_html( $cl ) . '</span>';
					$html .= '<input type="text" name="' . $field_prefix . '[choices][' . esc_attr( $cv ) . ']" class="regular-text" style="max-width:260px" value="' . esc_attr( $ov_cl ) . '" placeholder="' . esc_attr( $cl ) . '" />';
					$html .= '</div>';
				}
				$html .= '</div></div>';
			}

			$html .= '</div></div>';

			xpressui_pro_row( '', $header, $html );
		}

		echo '</tbody></table></div>';
		echo '</details>';
	}

	echo '</form>';
	echo '<script>
let xpressuiProFormDirty = false;
const xpressuiProForm = document.querySelector(".xpressui-admin-wrap form");
const xpressuiDirtyStatus = document.querySelector("[data-xpressui-dirty-status]");
const xpressuiCardSearch = document.querySelector("[data-xpressui-card-search]");
const xpressuiVisibleCount = document.querySelector("[data-xpressui-visible-count]");
const xpressuiEmptyState = document.querySelector("[data-xpressui-empty-state]");
let xpressuiCustomizedOnly = false;
function xpressuiSetDirtyState(isDirty){
	xpressuiProFormDirty = isDirty;
	if(!xpressuiDirtyStatus){return;}
	xpressuiDirtyStatus.classList.toggle("is-dirty", isDirty);
	xpressuiDirtyStatus.classList.toggle("is-saved", !isDirty);
	xpressuiDirtyStatus.textContent = isDirty ? "Unsaved changes" : "No unsaved changes";
}
function xpressuiApplyCardFilters(){
	const container = document.querySelector(".xpressui-admin-wrap");
	if(!container){return;}
	const query = xpressuiCardSearch && typeof xpressuiCardSearch.value === "string"
		? xpressuiCardSearch.value.trim().toLowerCase()
		: "";
	let visibleCount = 0;
	container.querySelectorAll("details.xpressui-admin-card").forEach(function(card){
		const searchText = (card.getAttribute("data-xpressui-search-text") || "").toLowerCase();
		const isCustomized = card.getAttribute("data-xpressui-customized") === "1";
		const matchesQuery = !query || searchText.indexOf(query) !== -1;
		const matchesCustomized = !xpressuiCustomizedOnly || isCustomized;
		const isVisible = matchesQuery && matchesCustomized;
		card.classList.toggle("is-filtered-out", !isVisible);
		if(isVisible){
			visibleCount += 1;
			if(query){
				card.open = true;
			}
		}
	});
	if(xpressuiVisibleCount){
		xpressuiVisibleCount.textContent = String(visibleCount);
	}
	if(xpressuiEmptyState){
		xpressuiEmptyState.style.display = visibleCount === 0 ? "" : "none";
	}
}
if(xpressuiProForm){
	xpressuiProForm.addEventListener("input", function(){ xpressuiSetDirtyState(true); });
	xpressuiProForm.addEventListener("change", function(){ xpressuiSetDirtyState(true); });
	xpressuiProForm.addEventListener("submit", function(){ xpressuiSetDirtyState(false); });
	window.addEventListener("beforeunload", function(event){
		if(!xpressuiProFormDirty){return;}
		event.preventDefault();
		event.returnValue = "";
	});
}
if(xpressuiCardSearch){
	xpressuiCardSearch.addEventListener("input", function(){
		xpressuiApplyCardFilters();
	});
}
document.addEventListener("click", function(event){
	const trigger = event.target.closest(".xpressui-pro-details-toggle");
	if(!trigger){return;}
	const container = document.querySelector(".xpressui-admin-wrap");
	if(!container){return;}
	const target = trigger.getAttribute("data-target");
	if(target === "jump-customized"){
		const firstCustomized = container.querySelector("details.xpressui-admin-card[data-xpressui-customized=\"1\"]");
		if(firstCustomized){
			firstCustomized.open = true;
			firstCustomized.scrollIntoView({behavior:"smooth", block:"start"});
		}
		return;
	}
	container.querySelectorAll("details.xpressui-admin-card").forEach(function(card){
		if(target === "all"){
			card.open = true;
		}else if(target === "none"){
			card.open = false;
		}else if(target === "customized"){
			card.open = card.getAttribute("data-xpressui-customized") === "1";
		}
	});
});
document.addEventListener("click", function(event){
	const clearTrigger = event.target.closest("[data-action=\"clear-search\"]");
	if(clearTrigger && xpressuiCardSearch){
		event.preventDefault();
		xpressuiCardSearch.value = "";
		xpressuiApplyCardFilters();
		xpressuiCardSearch.focus();
		return;
	}
	const filterTrigger = event.target.closest(".xpressui-pro-filter-toggle");
	if(!filterTrigger){return;}
	event.preventDefault();
	xpressuiCustomizedOnly = !xpressuiCustomizedOnly;
	filterTrigger.classList.toggle("is-active", xpressuiCustomizedOnly);
	filterTrigger.setAttribute("aria-pressed", xpressuiCustomizedOnly ? "true" : "false");
	xpressuiApplyCardFilters();
});
document.addEventListener("click", function(event){
	const trigger = event.target.closest("[data-xpressui-reset-trigger]");
	if(!trigger){return;}
	event.preventDefault();
	event.stopPropagation();
	const scopeId = trigger.getAttribute("data-xpressui-reset-trigger");
	if(!scopeId){return;}
	let scope = null;
	if(scopeId.indexOf("field-") === 0){
		scope = trigger.closest("tr");
	}else{
		scope = document.querySelector("[data-xpressui-reset-scope=\"" + scopeId + "\"]");
	}
	if(!scope){return;}
	scope.querySelectorAll("input, textarea, select").forEach(function(field){
		if(field.tagName === "SELECT"){
			field.value = "";
		}else if(field.type === "checkbox" || field.type === "radio"){
			field.checked = false;
		}else{
			field.value = "";
		}
		field.dispatchEvent(new Event("input", { bubbles: true }));
		field.dispatchEvent(new Event("change", { bubbles: true }));
	});
});
xpressuiApplyCardFilters();
</script>';
	echo '</div>';
}

// ---------------------------------------------------------------------------
// Helper: render a form-table row
// ---------------------------------------------------------------------------

function xpressui_pro_row( string $for, string $label, string $content ): void {
	echo '<tr>';
	echo '<th scope="row">';
	if ( $for !== '' ) {
		echo '<label for="' . esc_attr( $for ) . '">' . wp_kses_post( $label ) . '</label>';
	} else {
		echo wp_kses_post( $label );
	}
	echo '</th>';
	echo '<td>' . $content . '</td>'; // $content is already escaped at call sites.
	echo '</tr>';
}
