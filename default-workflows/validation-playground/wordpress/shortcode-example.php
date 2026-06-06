<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Example shortcode output for validation-playground.
function xpressui_render_validation_playground() {
	$allowed_html = function_exists( 'xpressui_get_shell_allowed_html' ) ? xpressui_get_shell_allowed_html() : 'post';
	return wp_kses( do_shortcode( '[xpressui id="validation-playground"]' ), $allowed_html );
}

add_shortcode( 'xpressui_validation_playground', 'xpressui_render_validation_playground' );
