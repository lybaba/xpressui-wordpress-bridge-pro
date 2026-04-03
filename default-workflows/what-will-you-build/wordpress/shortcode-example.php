<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Example shortcode output for what-will-you-build.
function xpressui_render_what_will_you_build() {
	return do_shortcode( '[xpressui id="what-will-you-build"]' );
}

add_shortcode( 'xpressui_what_will_you_build', 'xpressui_render_what_will_you_build' );
