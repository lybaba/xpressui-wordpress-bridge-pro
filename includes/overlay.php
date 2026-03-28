<?php
/**
 * Workflow overlay: per-workflow label customizations stored in wp_options.
 *
 * The overlay is a minimal diff applied on top of the static template context
 * at render time. Only values that differ from the pack defaults are stored.
 *
 * Overlay structure (stored as an associative array in wp_options):
 *   [
 *     'project_name' => 'Custom form title',
 *     'fields' => [
 *       'fieldname' => [
 *         'label'   => 'Custom label',
 *         'choices' => [ 'choice_value' => 'Custom choice label', ... ],
 *       ],
 *       ...
 *     ],
 *   ]
 *
 * @package XPressUI_Bridge_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the wp_options key for a workflow's overlay.
 */
function xpressui_pro_get_overlay_option_key( string $slug ): string {
	return 'xpressui_overlay_' . $slug;
}

/**
 * Load the customization overlay for a workflow.
 *
 * @return array<string, mixed>
 */
function xpressui_pro_load_workflow_overlay( string $slug ): array {
	$value = get_option( xpressui_pro_get_overlay_option_key( $slug ), [] );
	return is_array( $value ) ? $value : [];
}

/**
 * Save the customization overlay for a workflow.
 *
 * @param array<string, mixed> $overlay
 */
function xpressui_pro_save_workflow_overlay( string $slug, array $overlay ): void {
	update_option( xpressui_pro_get_overlay_option_key( $slug ), $overlay, false );
}

/**
 * Delete the customization overlay for a workflow (reset to pack defaults).
 */
function xpressui_pro_delete_workflow_overlay( string $slug ): void {
	delete_option( xpressui_pro_get_overlay_option_key( $slug ) );
}

/**
 * Apply a customization overlay onto a template context array.
 *
 * Patches project.name, rendered_form.title, field labels, and choice labels
 * in-place. Only non-empty overlay values are applied.
 *
 * @param array<string, mixed> $context Original template context.
 * @param array<string, mixed> $overlay Customization overlay.
 * @return array<string, mixed> Patched context.
 */
function xpressui_pro_apply_workflow_overlay( array $context, array $overlay ): array {
	// --- Form title ---
	$project_name = isset( $overlay['project_name'] ) ? trim( (string) $overlay['project_name'] ) : '';
	if ( $project_name !== '' ) {
		if ( isset( $context['project'] ) && is_array( $context['project'] ) ) {
			$context['project']['name'] = $project_name;
		}
		if ( isset( $context['rendered_form'] ) && is_array( $context['rendered_form'] ) ) {
			$context['rendered_form']['title'] = $project_name;
		}
	}

	// --- Field labels and choice labels ---
	$fields_overlay = isset( $overlay['fields'] ) && is_array( $overlay['fields'] ) ? $overlay['fields'] : [];
	if ( empty( $fields_overlay ) ) {
		return $context;
	}

	if ( ! isset( $context['rendered_form']['sections'] ) || ! is_array( $context['rendered_form']['sections'] ) ) {
		return $context;
	}

	foreach ( $context['rendered_form']['sections'] as &$section ) {
		if ( ! isset( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
			continue;
		}
		foreach ( $section['fields'] as &$field ) {
			$field_name = (string) ( $field['name'] ?? '' );
			if ( $field_name === '' || ! isset( $fields_overlay[ $field_name ] ) ) {
				continue;
			}
			$field_overlay = $fields_overlay[ $field_name ];

			$custom_label = isset( $field_overlay['label'] ) ? trim( (string) $field_overlay['label'] ) : '';
			if ( $custom_label !== '' ) {
				$field['label'] = $custom_label;
			}

			$choices_overlay = isset( $field_overlay['choices'] ) && is_array( $field_overlay['choices'] )
				? $field_overlay['choices']
				: [];

			if ( ! empty( $choices_overlay ) && isset( $field['choices'] ) && is_array( $field['choices'] ) ) {
				foreach ( $field['choices'] as &$choice ) {
					$choice_value = (string) ( $choice['value'] ?? '' );
					if ( $choice_value !== '' && isset( $choices_overlay[ $choice_value ] ) ) {
						$custom_choice_label = trim( (string) $choices_overlay[ $choice_value ] );
						if ( $custom_choice_label !== '' ) {
							$choice['label'] = $custom_choice_label;
						}
					}
				}
				unset( $choice );
			}
		}
		unset( $field );
	}
	unset( $section );

	// --- Patch runtime.form_config_json (read by the JS runtime) ---
	// The JS runtime hydrates the form from this embedded JSON and would
	// override the PHP-rendered labels if left unpatched.
	$raw_config_json = isset( $context['runtime']['form_config_json'] )
		? (string) $context['runtime']['form_config_json']
		: '';

	if ( $raw_config_json !== '' ) {
		$form_config = json_decode( $raw_config_json, true );
		if ( is_array( $form_config ) && isset( $form_config['sections'] ) && is_array( $form_config['sections'] ) ) {
			foreach ( $form_config['sections'] as $section_key => &$section_fields ) {
				if ( $section_key === 'custom' || ! is_array( $section_fields ) ) {
					continue;
				}
				foreach ( $section_fields as &$field ) {
					$field_name = (string) ( $field['name'] ?? '' );
					if ( $field_name === '' || ! isset( $fields_overlay[ $field_name ] ) ) {
						continue;
					}
					$field_overlay = $fields_overlay[ $field_name ];

					$custom_label = isset( $field_overlay['label'] ) ? trim( (string) $field_overlay['label'] ) : '';
					if ( $custom_label !== '' ) {
						$field['label'] = $custom_label;
					}

					$choices_overlay = isset( $field_overlay['choices'] ) && is_array( $field_overlay['choices'] )
						? $field_overlay['choices']
						: [];

					if ( ! empty( $choices_overlay ) && isset( $field['choices'] ) && is_array( $field['choices'] ) ) {
						foreach ( $field['choices'] as &$choice ) {
							$choice_value = (string) ( $choice['value'] ?? '' );
							if ( $choice_value !== '' && isset( $choices_overlay[ $choice_value ] ) ) {
								$custom_choice_label = trim( (string) $choices_overlay[ $choice_value ] );
								if ( $custom_choice_label !== '' ) {
									$choice['label'] = $custom_choice_label;
								}
							}
						}
						unset( $choice );
					}
				}
				unset( $field );
			}
			unset( $section_fields );

			$patched = wp_json_encode( $form_config );
			if ( $patched !== false ) {
				$context['runtime']['form_config_json'] = $patched;
			}
		}
	}

	return $context;
}

// ---------------------------------------------------------------------------
// Filter hooks — bridge the overlay into the base plugin's render pipeline
// ---------------------------------------------------------------------------

add_filter( 'xpressui_template_context', 'xpressui_pro_filter_template_context', 10, 2 );

function xpressui_pro_filter_template_context( array $context, string $slug ): array {
	$overlay = xpressui_pro_load_workflow_overlay( $slug );
	if ( empty( $overlay ) ) {
		return $context;
	}
	return xpressui_pro_apply_workflow_overlay( $context, $overlay );
}
