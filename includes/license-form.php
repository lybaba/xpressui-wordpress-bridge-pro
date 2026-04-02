<?php
/**
 * Overrides the default license form with the Pro version.
 *
 * @package XPressUI_Bridge_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into the admin page to replace the license form.
 */
function xpressui_pro_override_license_form() {
	// Remove the default form renderer from the free plugin.
	remove_action( 'xpressui_render_license_form', 'xpressui_render_default_license_form' );

	// Add our Pro form renderer.
	add_action( 'xpressui_render_license_form', 'xpressui_pro_render_license_form' );
}
add_action( 'admin_init', 'xpressui_pro_override_license_form' );


/**
 * Renders the Pro license form with Activate/Deactivate buttons.
 */
function xpressui_pro_render_license_form() {
	$license_data = get_option( XPRESSUI_PRO_LICENSE_OPTION_KEY, [] );
	$is_active    = xpressui_pro_is_license_active();
	$license_key  = isset( $license_data['license_key'] ) ? (string) $license_data['license_key'] : '';
	$masked_key   = '';
	if ( $license_key !== '' ) {
		$masked_key = substr( $license_key, 0, 4 ) . str_repeat( '*', max( 0, strlen( $license_key ) - 8 ) ) . substr( $license_key, -4 );
	}
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'xpressui_pro_license_actions', 'xpressui_pro_license_nonce' ); ?>
		<input type="hidden" name="action" value="xpressui_pro_license_actions" />
		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="xpressui_pro_license_key"><?php esc_html_e( 'License key', 'xpressui-wordpress-bridge-pro' ); ?></label></th>
					<td>
						<?php if ( $is_active && $masked_key ) : ?>
							<code><?php echo esc_html( $masked_key ); ?></code>
							<p class="description" style="margin-top:4px;"><?php esc_html_e( 'License active.', 'xpressui-wordpress-bridge-pro' ); ?></p>
						<?php else : ?>
							<input type="text" id="xpressui_pro_license_key" name="xpressui_pro_license_key" class="regular-text" value="" autocomplete="off" placeholder="iakp_..." />
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
		<p class="submit">
			<?php if ( $is_active ) : ?>
				<input type="submit" name="xpressui_pro_deactivate" class="button" value="<?php esc_attr_e( 'Deactivate License', 'xpressui-wordpress-bridge-pro' ); ?>" />
			<?php else : ?>
				<input type="submit" name="xpressui_pro_activate" class="button button-primary" value="<?php esc_attr_e( 'Activate License', 'xpressui-wordpress-bridge-pro' ); ?>" />
			<?php endif; ?>
		</p>
	</form>
	<?php
}