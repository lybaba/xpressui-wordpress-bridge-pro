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
	background:linear-gradient(135deg,#183ea8 0%,#2966ff 100%);
	color:#fff !important;
	font-size:12px;
	font-weight:700;
	line-height:1.2;
	text-decoration:none;
	box-shadow:0 6px 14px rgba(41,102,255,.18);
	transition:transform .15s ease, box-shadow .15s ease, opacity .15s ease;
	white-space:nowrap;
	vertical-align:middle;
}
.xpressui-pro-action-link:hover,
.xpressui-pro-action-link:focus{
	color:#fff !important;
	transform:translateY(-1px);
	box-shadow:0 8px 18px rgba(41,102,255,.24);
}
.xpressui-pro-action-link:focus{
	outline:none;
	box-shadow:0 0 0 2px rgba(255,255,255,.9),0 0 0 4px rgba(41,102,255,.45),0 8px 18px rgba(41,102,255,.24);
}
.xpressui-pro-action-badge{
	display:inline-flex;
	align-items:center;
	justify-content:center;
	border-radius:999px;
	padding:1px 6px;
	background:rgba(255,255,255,.16);
	border:1px solid rgba(255,255,255,.28);
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

	$notice_class    = '';
	$notice_messages = [];

	// -----------------------------------------------------------------------
	// Handle save
	// -----------------------------------------------------------------------

	if ( isset( $_POST['xpressui_save_overlay'] ) && check_admin_referer( 'xpressui_overlay_' . $slug, 'xpressui_overlay_nonce' ) ) {

		// Project settings (stored separately in xpressui_project_settings).
		$raw_notify_email = trim( wp_unslash( (string) ( $_POST['xpressui_notify_email'] ?? '' ) ) );
		$raw_redirect_url = trim( wp_unslash( (string) ( $_POST['xpressui_redirect_url'] ?? '' ) ) );
		$notify_email     = sanitize_email( $raw_notify_email );
		$redirect_url     = esc_url_raw( $raw_redirect_url );

		$save_warnings = [];
		if ( $raw_notify_email !== '' && $notify_email === '' ) {
			$save_warnings[] = __( 'The notification email was not saved because it is not a valid email address.', 'xpressui-bridge-pro' );
		}
		if ( $raw_redirect_url !== '' && $redirect_url === '' ) {
			$save_warnings[] = __( 'The post-submit redirect was not saved because it is not a valid URL.', 'xpressui-bridge-pro' );
		}
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
		'has_project_settings' => $ov_project_name !== '' || $proj_settings['notifyEmail'] !== '' || $proj_settings['redirectUrl'] !== '',
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
.xpressui-pro-header{background:linear-gradient(120deg,#122033 0%,#183ea8 55%,#2966ff 100%);margin:-10px -20px 16px;padding:28px 28px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;position:relative;overflow:hidden}
.xpressui-pro-header::after{content:"";position:absolute;right:-40px;top:-40px;width:200px;height:200px;background:rgba(255,255,255,.05);border-radius:50%}
.xpressui-pro-header-left{position:relative;z-index:1}
.xpressui-pro-header-left h1{margin:0 0 6px;font-size:24px;font-weight:700;color:#fff;line-height:1.2}
.xpressui-pro-header-left p{margin:0;font-size:13px;color:rgba(255,255,255,.7);line-height:1.5}
.xpressui-pro-header-right{position:relative;z-index:1;text-align:right;flex-shrink:0}
.xpressui-pro-badge{display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);border-radius:20px;padding:5px 14px;font-size:12px;font-weight:700;letter-spacing:.06em;color:#fff;text-transform:uppercase}
.xpressui-pro-back{display:block;margin-top:8px;font-size:12px;color:rgba(255,255,255,.6);text-decoration:none}
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
.xpressui-card-meta{display:inline-flex;align-items:center;gap:6px;margin-left:auto;flex-wrap:wrap}
.xpressui-card-badge{display:inline-flex;align-items:center;justify-content:center;padding:3px 8px;border-radius:999px;background:#eef4ff;color:#183ea8;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase}
.xpressui-card-badge.is-customized{background:#e9f7ef;color:#0a7a32}
.xpressui-sticky-status{margin-left:auto;font-size:12px;font-weight:600;color:#5b6b82}
.xpressui-sticky-status.is-dirty{color:#b45309}
.xpressui-sticky-status.is-saved{color:#0a7a32}
.xpressui-field-block{padding:12px 14px;border:1px solid #e5edf8;border-radius:10px;background:#fff}
.xpressui-field-block.is-customized{border-color:#b8ccff;background:linear-gradient(180deg,#f9fbff 0%,#f3f7ff 100%)}
.xpressui-field-block-header{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:0 0 10px}
.xpressui-field-block-title{font-size:13px;font-weight:700;color:#122033}
.xpressui-field-block-type{font-size:11px;color:#5b6b82;text-transform:uppercase;letter-spacing:.06em}
.xpressui-field-block-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px 12px}
.xpressui-field-control label{display:block;font-size:12px;color:#5b6b82;margin-bottom:4px;font-weight:600}
.xpressui-field-control.is-full{grid-column:1 / -1}
.xpressui-choice-group{margin-top:10px;padding-left:12px;border-left:3px solid #d8e3f7}
.xpressui-choice-row{display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap}
.xpressui-choice-label{min-width:120px;color:#5b6b82;font-size:12px;font-weight:600}
.xpressui-muted{color:#5b6b82;font-size:12px}
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
	echo '</div>';

	// Sticky save bar.
	echo '<div class="xpressui-sticky-actions">';
	submit_button( __( 'Save Customizations', 'xpressui-bridge-pro' ), 'primary', 'xpressui_save_overlay', false );
	echo ' &nbsp; ';
	submit_button(
		__( 'Reset to Defaults', 'xpressui-bridge-pro' ),
		'secondary',
		'xpressui_reset_overlay',
		false,
		[
			'onclick' => "return window.confirm('" . esc_js( __( 'Reset all customizations for this workflow and restore the pack defaults?', 'xpressui-bridge-pro' ) ) . "');",
		]
	);
	echo '<span class="xpressui-sticky-status is-saved" data-xpressui-dirty-status="saved">' . esc_html__( 'No unsaved changes', 'xpressui-bridge-pro' ) . '</span>';
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
	echo '<details class="xpressui-admin-card" open data-xpressui-card-type="project-settings" data-xpressui-customized="' . ( $summary_stats['has_project_settings'] ? '1' : '0' ) . '" id="xpressui-pro-card-project-settings">';
	echo '<summary class="xpressui-card-summary"><h2>' . esc_html__( 'Project Settings', 'xpressui-bridge-pro' ) . '</h2><span class="xpressui-card-meta">';
	if ( $summary_stats['has_project_settings'] ) {
		echo '<span class="xpressui-card-badge is-customized">' . esc_html__( 'Customized', 'xpressui-bridge-pro' ) . '</span>';
	}
	echo '<span class="xpressui-card-badge">' . esc_html( (string) $project_settings_count ) . ' ' . esc_html__( 'Active', 'xpressui-bridge-pro' ) . '</span>';
	echo '</span></summary>';
	echo '<div class="xpressui-card-body"><table class="form-table"><tbody>';

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
	echo '<details class="xpressui-admin-card" open data-xpressui-card-type="submit-feedback" data-xpressui-customized="' . ( $summary_stats['has_submit_feedback'] ? '1' : '0' ) . '" id="xpressui-pro-card-submit-feedback">';
	echo '<summary class="xpressui-card-summary"><h2>' . esc_html__( 'Submit Feedback', 'xpressui-bridge-pro' ) . '</h2><span class="xpressui-card-meta">';
	if ( $summary_stats['has_submit_feedback'] ) {
		echo '<span class="xpressui-card-badge is-customized">' . esc_html__( 'Customized', 'xpressui-bridge-pro' ) . '</span>';
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
	echo '<details class="xpressui-admin-card"' . $nav_open . ' data-xpressui-card-type="navigation" data-xpressui-customized="' . ( ! empty( $ov_navigation ) ? '1' : '0' ) . '" id="xpressui-pro-card-navigation">';
	echo '<summary class="xpressui-card-summary"><h2>' . esc_html__( 'Navigation Labels', 'xpressui-bridge-pro' ) . '</h2><span class="xpressui-card-meta">';
	if ( ! empty( $ov_navigation ) ) {
		echo '<span class="xpressui-card-badge is-customized">' . esc_html__( 'Customized', 'xpressui-bridge-pro' ) . '</span>';
	}
	echo '<span class="xpressui-card-badge">' . esc_html( (string) count( $nav_fields ) ) . ' ' . esc_html__( 'Buttons', 'xpressui-bridge-pro' ) . '</span>';
	echo '</span></summary>';
	echo '<div class="xpressui-card-body"><table class="form-table"><tbody>';

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

		echo '<details class="xpressui-admin-card"' . $card_open . ' data-xpressui-card-type="section" data-xpressui-customized="' . ( $section_has_custom ? '1' : '0' ) . '" id="xpressui-pro-card-' . esc_attr( $section_name ) . '">';
		echo '<summary class="xpressui-card-summary"><h2>' . esc_html( $section_label ) . '</h2><span class="xpressui-card-meta">';
		if ( $section_has_custom ) {
			echo '<span class="xpressui-card-badge is-customized">' . esc_html__( 'Customized', 'xpressui-bridge-pro' ) . '</span>';
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
			$field_has_custom = ! empty( $fo );

			$field_prefix = 'xpressui_overlay_fields[' . esc_attr( $fname ) . ']';
			$header       = '<div class="xpressui-field-block' . ( $field_has_custom ? ' is-customized' : '' ) . '">';
			$header      .= '<div class="xpressui-field-block-header">';
			$header      .= '<div><div class="xpressui-field-block-title">' . esc_html( $flabel ) . '</div><div class="xpressui-field-block-type">' . esc_html( $ftype ) . '</div></div>';
			if ( $field_has_custom ) {
				$header .= '<span class="xpressui-card-badge is-customized">' . esc_html__( 'Customized', 'xpressui-bridge-pro' ) . '</span>';
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

			// Choice labels.
			if ( ! empty( $choices ) ) {
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
function xpressuiSetDirtyState(isDirty){
	xpressuiProFormDirty = isDirty;
	if(!xpressuiDirtyStatus){return;}
	xpressuiDirtyStatus.classList.toggle("is-dirty", isDirty);
	xpressuiDirtyStatus.classList.toggle("is-saved", !isDirty);
	xpressuiDirtyStatus.textContent = isDirty ? "Unsaved changes" : "No unsaved changes";
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
