<?php
/**
 * [xpressui_catalog] shortcode — embeds a hosted XPressUI catalog page.
 *
 * The product catalog is a SaaS cloud feature: all data, cart logic, checkout
 * and member-gate logic run on the IntakeFlow console. This shortcode creates a
 * responsive iframe pointing to the public hosted-catalog URL so the catalog
 * renders seamlessly inside any WordPress page or post.
 *
 * Usage:
 *   [xpressui_catalog url="https://console.example.com/api/v1/hosted-catalogs/user/my-catalog"]
 *   [xpressui_catalog url="…" height="700px" title="Our product catalog"]
 *
 * Attributes:
 *   url    (required) Full URL of the hosted catalog page.
 *   height (optional) CSS height of the iframe. Default: "700px".
 *   title  (optional) Accessible title for the iframe. Default: "Product catalog".
 *
 * @package XPressUI_Bridge_Pro
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'xpressui_catalog', 'xpressui_pro_catalog_shortcode' );

/**
 * Renders the [xpressui_catalog] shortcode.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string HTML output.
 */
function xpressui_pro_catalog_shortcode( $atts ): string {
	$atts = shortcode_atts(
		[
			'url'    => '',
			'height' => '700px',
				'title'  => __( 'Product catalog', 'xpressui-bridge-pro' ),
		],
		$atts,
		'xpressui_catalog'
	);

	$url = trim( (string) $atts['url'] );

	if ( $url === '' ) {
		return '<p class="xpressui-embed-error">'
				. esc_html__( '[xpressui_catalog] error: the "url" attribute is required.', 'xpressui-bridge-pro' )
			. '</p>';
	}

	// Only allow http/https URLs.
	$safe_url = esc_url( $url, [ 'http', 'https' ] );
	if ( $safe_url === '' ) {
		return '<p class="xpressui-embed-error">'
				. esc_html__( '[xpressui_catalog] error: invalid URL.', 'xpressui-bridge-pro' )
			. '</p>';
	}

	$height = sanitize_text_field( (string) $atts['height'] );
	// Accept px / vh / em / rem / % values only; fall back to default.
	if ( ! preg_match( '/^\d+(\.\d+)?(px|vh|em|rem|%)$/', $height ) ) {
		$height = '700px';
	}

	$title = sanitize_text_field( (string) $atts['title'] );
	if ( $title === '' ) {
		$title = __( 'Product catalog', 'xpressui-bridge-pro' );
	}

	// Unique wrapper ID to scope the auto-resize script.
	$wrapper_id = 'xpressui-cat-' . wp_unique_id();

	// Derive the iframe origin so the resize listener only trusts messages from
	// the catalog host (cross-origin postMessage security).
	$parts  = wp_parse_url( $safe_url );
	$origin = '';
	if ( is_array( $parts ) && ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
		$origin = $parts['scheme'] . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . (int) $parts['port'];
		}
	}

	ob_start();
	?>
<div
	id="<?php echo esc_attr( $wrapper_id ); ?>"
	class="xpressui-catalog-embed"
	style="width:100%;min-height:<?php echo esc_attr( $height ); ?>;overflow:hidden;background:#f6f8fb;border-radius:8px;transition:height .2s ease;"
>
	<iframe
			src="<?php echo esc_url( $safe_url ); ?>"
		title="<?php echo esc_attr( $title ); ?>"
		style="width:100%;height:<?php echo esc_attr( $height ); ?>;border:none;display:block;opacity:0;transition:opacity .25s ease;"
		loading="lazy"
		referrerpolicy="strict-origin-when-cross-origin"
	></iframe>
</div>
<script>
(function(){
	var wrap = document.getElementById(<?php echo wp_json_encode( $wrapper_id ); ?>);
	if ( ! wrap ) { return; }
	var frame = wrap.querySelector('iframe');
	if ( ! frame ) { return; }
	var origin = <?php echo wp_json_encode( $origin ); ?>;
	frame.addEventListener('load', function(){ frame.style.opacity = '1'; });
	// Auto-resize: the hosted catalog page may post its content height so the
	// iframe grows to fit (no inner scrollbar). Only messages from the catalog
	// origin are trusted. No-op until the cloud page sends the message.
	window.addEventListener('message', function(e){
		if ( ! origin || e.origin !== origin ) { return; }
		var d = e.data || {};
		if ( d && d.type === 'xpressui-catalog-height' ) {
			var h = parseInt( d.height, 10 );
			if ( h > 0 && h < 20000 ) { frame.style.height = h + 'px'; }
		}
	});
})();
</script>
	<?php
	return (string) ob_get_clean();
}
