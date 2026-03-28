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
	$actions[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Customize', 'xpressui-bridge-pro' ) . '</a>';
	return $actions;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Returns current project settings (notify email + redirect URL) for a slug.
 *
 * @return array{notifyEmail: string, redirectUrl: string}
 */
function xpressui_pro_get_project_settings( string $slug ): array {
	$all = get_option( 'xpressui_project_settings', [] );
	$row = isset( $all[ $slug ] ) && is_array( $all[ $slug ] ) ? $all[ $slug ] : [];
	return [
		'notifyEmail' => (string) ( $row['notifyEmail'] ?? '' ),
		'redirectUrl' => (string) ( $row['redirectUrl'] ?? '' ),
	];
}

/**
 * Save project settings for a slug (merges into the shared option).
 */
function xpressui_pro_save_project_settings( string $slug, string $notify_email, string $redirect_url ): void {
	$all = get_option( 'xpressui_project_settings', [] );
	if ( ! is_array( $all ) ) {
		$all = [];
	}
	$all[ $slug ] = [
		'notifyEmail' => $notify_email,
		'redirectUrl' => $redirect_url,
	];
	update_option( 'xpressui_project_settings', $all );
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

	$notice_class   = '';
	$notice_message = '';

	// -----------------------------------------------------------------------
	// Handle save
	// -----------------------------------------------------------------------

	if ( isset( $_POST['xpressui_save_overlay'] ) && check_admin_referer( 'xpressui_overlay_' . $slug, 'xpressui_overlay_nonce' ) ) {

		// Project settings (stored separately in xpressui_project_settings).
		$notify_email = sanitize_email( wp_unslash( (string) ( $_POST['xpressui_notify_email'] ?? '' ) ) );
		$redirect_url = esc_url_raw( wp_unslash( (string) ( $_POST['xpressui_redirect_url'] ?? '' ) ) );
		xpressui_pro_save_project_settings( $slug, $notify_email, $redirect_url );

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
			$sname  = sanitize_key( (string) $sname );
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
			$field_name = sanitize_key( (string) $field_name );
			if ( $field_name === '' || ! is_array( $field_data ) ) {
				continue;
			}
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

			// Choice labels.
			if ( isset( $field_data['choices'] ) && is_array( $field_data['choices'] ) ) {
				$choices_entry = [];
				foreach ( $field_data['choices'] as $cv => $cl ) {
					$cv = sanitize_key( (string) $cv );
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
		$notice_class   = 'notice-success';
		$notice_message = __( 'Customizations saved.', 'xpressui-bridge-pro' );
	}

	// Handle reset.
	if ( isset( $_POST['xpressui_reset_overlay'] ) && check_admin_referer( 'xpressui_overlay_' . $slug, 'xpressui_overlay_nonce' ) ) {
		xpressui_pro_delete_workflow_overlay( $slug );
		$notice_class   = 'notice-success';
		$notice_message = __( 'Customizations reset to pack defaults.', 'xpressui-bridge-pro' );
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
	echo '<h1>' . esc_html__( 'Customize Workflow', 'xpressui-bridge-pro' ) . ' <span class="xpressui-badge xpressui-badge--muted">' . esc_html( $slug ) . '</span></h1>';
	echo '<p><a href="' . esc_url( $back_url ) . '">&larr; ' . esc_html__( 'Back to Manage Workflows', 'xpressui-bridge-pro' ) . '</a></p>';

	if ( $notice_message !== '' ) {
		echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $notice_message ) . '</p></div>';
	}

	echo '<p class="xpressui-page-intro">' . esc_html__( 'Override form settings, section labels, and field properties. Leave a field blank to keep the pack default.', 'xpressui-bridge-pro' ) . '</p>';

	echo '<form method="post" action="">';
	wp_nonce_field( 'xpressui_overlay_' . $slug, 'xpressui_overlay_nonce' );

	// -----------------------------------------------------------------------
	// Card: Project Settings
	// -----------------------------------------------------------------------

	echo '<div class="card xpressui-admin-card">';
	echo '<h2>' . esc_html__( 'Project Settings', 'xpressui-bridge-pro' ) . '</h2>';
	echo '<table class="form-table"><tbody>';

	xpressui_pro_row(
		'xpressui_overlay_project_name',
		__( 'Form title', 'xpressui-bridge-pro' ),
		'<input type="text" id="xpressui_overlay_project_name" name="xpressui_overlay_project_name" class="regular-text" value="' . esc_attr( $ov_project_name ) . '" placeholder="' . esc_attr( $original_title ) . '" />'
		. '<p class="description">' . esc_html__( 'Pack default:', 'xpressui-bridge-pro' ) . ' <em>' . esc_html( $original_title ) . '</em></p>'
	);

	xpressui_pro_row(
		'xpressui_notify_email',
		__( 'Notification email', 'xpressui-bridge-pro' ),
		'<input type="email" id="xpressui_notify_email" name="xpressui_notify_email" class="regular-text" value="' . esc_attr( $proj_settings['notifyEmail'] ) . '" />'
		. '<p class="description">' . esc_html__( 'Receive an email for each new submission.', 'xpressui-bridge-pro' ) . '</p>'
	);

	xpressui_pro_row(
		'xpressui_redirect_url',
		__( 'Post-submit redirect', 'xpressui-bridge-pro' ),
		'<input type="url" id="xpressui_redirect_url" name="xpressui_redirect_url" class="regular-text" value="' . esc_attr( $proj_settings['redirectUrl'] ) . '" placeholder="https://" />'
		. '<p class="description">' . esc_html__( 'Redirect users here after a successful submission.', 'xpressui-bridge-pro' ) . '</p>'
	);

	echo '</tbody></table>';
	echo '</div>';

	// -----------------------------------------------------------------------
	// Card: Submit feedback
	// -----------------------------------------------------------------------

	echo '<div class="card xpressui-admin-card">';
	echo '<h2>' . esc_html__( 'Submit Feedback', 'xpressui-bridge-pro' ) . '</h2>';
	echo '<table class="form-table"><tbody>';

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

	echo '</tbody></table>';
	echo '</div>';

	// -----------------------------------------------------------------------
	// Card: Navigation labels
	// -----------------------------------------------------------------------

	echo '<div class="card xpressui-admin-card">';
	echo '<h2>' . esc_html__( 'Navigation Labels', 'xpressui-bridge-pro' ) . '</h2>';
	echo '<table class="form-table"><tbody>';

	$nav_fields = [
		'prev'   => [ __( 'Back button', 'xpressui-bridge-pro' ), (string) ( $pack_nav['prevLabel'] ?? 'Back' ) ],
		'next'   => [ __( 'Continue button', 'xpressui-bridge-pro' ), (string) ( $pack_nav['nextLabel'] ?? 'Continue' ) ],
		'submit' => [ __( 'Submit button', 'xpressui-bridge-pro' ), (string) ( $pack_nav['submitLabel'] ?? 'Submit' ) ],
	];

	foreach ( $nav_fields as $nav_key => [ $nav_label, $nav_default ] ) {
		$current_val = (string) ( $ov_navigation[ $nav_key ] ?? '' );
		xpressui_pro_row(
			'xpressui_overlay_nav_' . $nav_key,
			$nav_label,
			'<input type="text" id="xpressui_overlay_nav_' . esc_attr( $nav_key ) . '" name="xpressui_overlay_nav_' . esc_attr( $nav_key ) . '" class="regular-text" value="' . esc_attr( $current_val ) . '" placeholder="' . esc_attr( $nav_default ) . '" />'
			. '<p class="description">' . esc_html__( 'Pack default:', 'xpressui-bridge-pro' ) . ' <em>' . esc_html( $nav_default ) . '</em></p>'
		);
	}

	echo '</tbody></table>';
	echo '</div>';

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

		echo '<div class="card xpressui-admin-card">';
		echo '<h2>' . esc_html( $section_label ) . '</h2>';
		echo '<table class="form-table"><tbody>';

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

			$field_prefix = 'xpressui_overlay_fields[' . esc_attr( $fname ) . ']';
			$header       = esc_html( $flabel ) . ' <span class="xpressui-muted">(' . esc_html( $ftype ) . ')</span>';

			$html = '';

			// Label.
			$html .= '<div style="margin-bottom:6px">';
			$html .= '<label style="display:block;font-size:12px;color:#666;margin-bottom:2px">' . esc_html__( 'Label', 'xpressui-bridge-pro' ) . '</label>';
			$html .= '<input type="text" name="' . $field_prefix . '[label]" class="regular-text" value="' . esc_attr( $ov_label ) . '" placeholder="' . esc_attr( $flabel ) . '" />';
			$html .= '</div>';

			// Required (select, 3 states).
			$req_options = [
				''  => __( 'Pack default', 'xpressui-bridge-pro' ) . ' (' . ( $pack_req ? __( 'required', 'xpressui-bridge-pro' ) : __( 'optional', 'xpressui-bridge-pro' ) ) . ')',
				'1' => __( 'Required', 'xpressui-bridge-pro' ),
				'0' => __( 'Optional', 'xpressui-bridge-pro' ),
			];
			$html .= '<div style="margin-bottom:6px">';
			$html .= '<label style="display:block;font-size:12px;color:#666;margin-bottom:2px">' . esc_html__( 'Required', 'xpressui-bridge-pro' ) . '</label>';
			$html .= '<select name="' . $field_prefix . '[required]">';
			foreach ( $req_options as $opt_val => $opt_label ) {
				$html .= '<option value="' . esc_attr( (string) $opt_val ) . '"' . selected( $ov_req, (string) $opt_val, false ) . '>' . esc_html( $opt_label ) . '</option>';
			}
			$html .= '</select>';
			$html .= '</div>';

			// Placeholder (text-like fields).
			$text_types = [ 'text', 'email', 'tel', 'url', 'number', 'price', 'integer', 'age', 'tax', 'date', 'time', 'datetime', 'search', 'slug', 'textarea', 'rich-editor' ];
			if ( in_array( $ftype, $text_types, true ) ) {
				$html .= '<div style="margin-bottom:6px">';
				$html .= '<label style="display:block;font-size:12px;color:#666;margin-bottom:2px">' . esc_html__( 'Placeholder', 'xpressui-bridge-pro' ) . '</label>';
				$html .= '<input type="text" name="' . $field_prefix . '[placeholder]" class="regular-text" value="' . esc_attr( $ov_ph ) . '" placeholder="' . esc_attr( (string) ( $field['placeholder'] ?? '' ) ) . '" />';
				$html .= '</div>';
			}

			// Description.
			$html .= '<div style="margin-bottom:6px">';
			$html .= '<label style="display:block;font-size:12px;color:#666;margin-bottom:2px">' . esc_html__( 'Help text', 'xpressui-bridge-pro' ) . '</label>';
			$html .= '<textarea name="' . $field_prefix . '[desc]" class="large-text" rows="2">' . esc_textarea( $ov_desc ) . '</textarea>';
			$html .= '</div>';

			// Error message.
			$pack_errmsg = (string) ( $field['error_message'] ?? '' );
			$html .= '<div style="margin-bottom:6px">';
			$html .= '<label style="display:block;font-size:12px;color:#666;margin-bottom:2px">' . esc_html__( 'Error message', 'xpressui-bridge-pro' ) . '</label>';
			$html .= '<input type="text" name="' . $field_prefix . '[error_message]" class="large-text" value="' . esc_attr( $ov_errmsg ) . '" placeholder="' . esc_attr( $pack_errmsg ) . '" />';
			$html .= '</div>';

			// Choice labels.
			if ( ! empty( $choices ) ) {
				$ov_choices = isset( $fo['choices'] ) && is_array( $fo['choices'] ) ? $fo['choices'] : [];
				$html      .= '<div style="margin-top:8px;padding-left:8px;border-left:3px solid #ddd">';
				$html      .= '<p class="description" style="margin-bottom:6px">' . esc_html__( 'Choice labels:', 'xpressui-bridge-pro' ) . '</p>';
				foreach ( $choices as $choice ) {
					$cv = (string) ( $choice['value'] ?? '' );
					$cl = (string) ( $choice['label'] ?? $cv );
					if ( $cv === '' ) {
						continue;
					}
					$ov_cl = (string) ( $ov_choices[ $cv ] ?? '' );
					$html .= '<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">';
					$html .= '<span style="min-width:120px;color:#666;font-size:12px">' . esc_html( $cl ) . '</span>';
					$html .= '<input type="text" name="' . $field_prefix . '[choices][' . esc_attr( $cv ) . ']" class="regular-text" style="max-width:260px" value="' . esc_attr( $ov_cl ) . '" placeholder="' . esc_attr( $cl ) . '" />';
					$html .= '</div>';
				}
				$html .= '</div>';
			}

			xpressui_pro_row( '', $header, $html );
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	// -----------------------------------------------------------------------
	// Actions
	// -----------------------------------------------------------------------

	echo '<p>';
	submit_button( __( 'Save Customizations', 'xpressui-bridge-pro' ), 'primary', 'xpressui_save_overlay', false );
	echo ' &nbsp; ';
	submit_button( __( 'Reset to Defaults', 'xpressui-bridge-pro' ), 'secondary', 'xpressui_reset_overlay', false );
	echo '</p>';

	echo '</form>';
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
