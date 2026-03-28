<?php
/**
 * Customize Workflow admin page — pro extension.
 *
 * Registers a hidden admin page under the XPressUI menu and injects a
 * "Customize" link into each workflow row via the xpressui_workflow_row_actions filter.
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
// Render page
// ---------------------------------------------------------------------------

function xpressui_pro_render_customize_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'xpressui-bridge-pro' ) );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- slug from GET, nonce checked on POST.
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

	// Handle save.
	if ( isset( $_POST['xpressui_save_overlay'] ) && check_admin_referer( 'xpressui_overlay_' . $slug, 'xpressui_overlay_nonce' ) ) {
		$overlay = [];

		$project_name = sanitize_text_field( wp_unslash( (string) ( $_POST['xpressui_overlay_project_name'] ?? '' ) ) );
		if ( $project_name !== '' ) {
			$overlay['project_name'] = $project_name;
		}

		$raw_fields = isset( $_POST['xpressui_overlay_fields'] ) && is_array( $_POST['xpressui_overlay_fields'] )
			? $_POST['xpressui_overlay_fields'] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: [];

		$fields_overlay = [];
		foreach ( $raw_fields as $field_name => $field_data ) {
			$field_name = sanitize_key( (string) $field_name );
			if ( $field_name === '' || ! is_array( $field_data ) ) {
				continue;
			}
			$field_entry = [];

			$label = sanitize_text_field( wp_unslash( (string) ( $field_data['label'] ?? '' ) ) );
			if ( $label !== '' ) {
				$field_entry['label'] = $label;
			}

			if ( isset( $field_data['choices'] ) && is_array( $field_data['choices'] ) ) {
				$choices_entry = [];
				foreach ( $field_data['choices'] as $choice_value => $choice_label ) {
					$choice_value = sanitize_key( (string) $choice_value );
					$choice_label = sanitize_text_field( wp_unslash( (string) $choice_label ) );
					if ( $choice_value !== '' && $choice_label !== '' ) {
						$choices_entry[ $choice_value ] = $choice_label;
					}
				}
				if ( ! empty( $choices_entry ) ) {
					$field_entry['choices'] = $choices_entry;
				}
			}

			if ( ! empty( $field_entry ) ) {
				$fields_overlay[ $field_name ] = $field_entry;
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

	// Load context and overlay for display.
	$template_context = xpressui_load_workflow_template_context( $slug );
	$overlay          = xpressui_pro_load_workflow_overlay( $slug );

	$original_title = (string) ( $template_context['rendered_form']['title'] ?? $template_context['project']['name'] ?? $slug );
	$current_title  = (string) ( $overlay['project_name'] ?? '' );
	$sections       = isset( $template_context['rendered_form']['sections'] ) && is_array( $template_context['rendered_form']['sections'] )
		? $template_context['rendered_form']['sections']
		: [];
	$fields_overlay = isset( $overlay['fields'] ) && is_array( $overlay['fields'] ) ? $overlay['fields'] : [];

	// Render page.
	echo '<div class="wrap xpressui-admin-wrap">';
	echo '<h1>' . esc_html__( 'Customize Workflow', 'xpressui-bridge-pro' ) . ' <span class="xpressui-badge xpressui-badge--muted">' . esc_html( $slug ) . '</span></h1>';
	echo '<p><a href="' . esc_url( $back_url ) . '">&larr; ' . esc_html__( 'Back to Manage Workflows', 'xpressui-bridge-pro' ) . '</a></p>';

	if ( $notice_message !== '' ) {
		echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $notice_message ) . '</p></div>';
	}

	echo '<p class="xpressui-page-intro">' . esc_html__( 'Override the form title, field labels, and choice labels. Leave a field blank to keep the pack default.', 'xpressui-bridge-pro' ) . '</p>';

	echo '<form method="post" action="">';
	wp_nonce_field( 'xpressui_overlay_' . $slug, 'xpressui_overlay_nonce' );

	// --- Form title ---
	echo '<div class="card xpressui-admin-card">';
	echo '<h2>' . esc_html__( 'Form Title', 'xpressui-bridge-pro' ) . '</h2>';
	echo '<table class="form-table"><tbody>';
	echo '<tr><th><label for="xpressui_overlay_project_name">' . esc_html__( 'Title', 'xpressui-bridge-pro' ) . '</label></th>';
	echo '<td>';
	echo '<input type="text" id="xpressui_overlay_project_name" name="xpressui_overlay_project_name" class="regular-text" value="' . esc_attr( $current_title ) . '" placeholder="' . esc_attr( $original_title ) . '" />';
	echo '<p class="description">' . esc_html__( 'Pack default:', 'xpressui-bridge-pro' ) . ' <em>' . esc_html( $original_title ) . '</em></p>';
	echo '</td></tr>';
	echo '</tbody></table>';
	echo '</div>';

	// --- Fields ---
	foreach ( $sections as $section ) {
		$section_label = (string) ( $section['label'] ?? $section['name'] ?? '' );
		$fields        = isset( $section['fields'] ) && is_array( $section['fields'] ) ? $section['fields'] : [];
		if ( empty( $fields ) ) {
			continue;
		}

		echo '<div class="card xpressui-admin-card">';
		echo '<h2>' . esc_html( $section_label ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		foreach ( $fields as $field ) {
			$field_name  = (string) ( $field['name'] ?? '' );
			$field_label = (string) ( $field['label'] ?? $field_name );
			$field_type  = (string) ( $field['type'] ?? '' );
			$choices     = isset( $field['choices'] ) && is_array( $field['choices'] ) ? $field['choices'] : [];

			if ( $field_name === '' ) {
				continue;
			}

			$current_label = (string) ( $fields_overlay[ $field_name ]['label'] ?? '' );

			echo '<tr>';
			echo '<th><label>' . esc_html( $field_label ) . ' <span class="xpressui-muted">(' . esc_html( $field_type ) . ')</span></label></th>';
			echo '<td>';
			echo '<input type="text" name="xpressui_overlay_fields[' . esc_attr( $field_name ) . '][label]" class="regular-text" value="' . esc_attr( $current_label ) . '" placeholder="' . esc_attr( $field_label ) . '" />';

			if ( ! empty( $choices ) ) {
				$current_choices = isset( $fields_overlay[ $field_name ]['choices'] ) && is_array( $fields_overlay[ $field_name ]['choices'] )
					? $fields_overlay[ $field_name ]['choices']
					: [];

				echo '<div style="margin-top:8px;padding-left:8px;border-left:3px solid #ddd">';
				echo '<p class="description" style="margin-bottom:6px">' . esc_html__( 'Choice labels:', 'xpressui-bridge-pro' ) . '</p>';
				foreach ( $choices as $choice ) {
					$choice_value        = (string) ( $choice['value'] ?? '' );
					$choice_label        = (string) ( $choice['label'] ?? $choice_value );
					$current_choice_label = (string) ( $current_choices[ $choice_value ] ?? '' );
					if ( $choice_value === '' ) {
						continue;
					}
					echo '<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">';
					echo '<span style="min-width:120px;color:#666;font-size:12px">' . esc_html( $choice_label ) . '</span>';
					echo '<input type="text" name="xpressui_overlay_fields[' . esc_attr( $field_name ) . '][choices][' . esc_attr( $choice_value ) . ']" class="regular-text" style="max-width:260px" value="' . esc_attr( $current_choice_label ) . '" placeholder="' . esc_attr( $choice_label ) . '" />';
					echo '</div>';
				}
				echo '</div>';
			}

			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	// --- Actions ---
	echo '<p>';
	submit_button( __( 'Save Customizations', 'xpressui-bridge-pro' ), 'primary', 'xpressui_save_overlay', false );
	echo ' &nbsp; ';
	submit_button( __( 'Reset to Defaults', 'xpressui-bridge-pro' ), 'secondary', 'xpressui_reset_overlay', false );
	echo '</p>';

	echo '</form>';
	echo '</div>';
}
