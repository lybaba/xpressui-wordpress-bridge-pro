<?php
/**
 * Pro license admin page.
 *
 * @package XPressUI_Bridge_Pro
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'xpressui_pro_register_license_page' );

/**
 * Registers the dedicated Pro license page under the XPressUI menu.
 */
function xpressui_pro_register_license_page(): void {
	add_submenu_page(
		'edit.php?post_type=xpressui_submission',
		__( 'Pro License', 'xpressui-wordpress-bridge-pro' ),
		__( 'Pro License', 'xpressui-wordpress-bridge-pro' ),
		'manage_options',
		'xpressui-pro-license',
		'xpressui_pro_render_license_page'
	);
}

/**
 * Returns the admin URL for the dedicated Pro license page.
 */
function xpressui_pro_get_license_page_url(): string {
	return add_query_arg(
		[
			'post_type' => 'xpressui_submission',
			'page'      => 'xpressui-pro-license',
		],
		admin_url( 'edit.php' )
	);
}

/**
 * Masks a license key for display purposes.
 */
function xpressui_pro_get_masked_license_key( string $license_key ): string {
	$license_key = trim( $license_key );
	if ( '' === $license_key ) {
		return '';
	}

	if ( strlen( $license_key ) <= 8 ) {
		return str_repeat( '*', strlen( $license_key ) );
	}

	return substr( $license_key, 0, 4 ) . str_repeat( '*', max( 0, strlen( $license_key ) - 8 ) ) . substr( $license_key, -4 );
}

/**
 * Renders the dedicated Pro license page.
 */
function xpressui_pro_render_license_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'xpressui-wordpress-bridge-pro' ) );
	}

	$license_data = get_option( XPRESSUI_PRO_LICENSE_OPTION_KEY, [] );
	$license_data = is_array( $license_data ) ? $license_data : [];
	$is_active    = xpressui_pro_is_license_active();
	$license_key  = isset( $license_data['license_key'] ) ? (string) $license_data['license_key'] : '';
	$masked_key   = xpressui_pro_get_masked_license_key( $license_key );
	$status       = isset( $license_data['status'] ) ? sanitize_text_field( (string) $license_data['status'] ) : '';
	$site_url     = isset( $license_data['site_url'] ) ? esc_url( (string) $license_data['site_url'] ) : '';
	$expires_at   = isset( $license_data['expires_at'] ) ? sanitize_text_field( (string) $license_data['expires_at'] ) : '';
	$issued_at    = isset( $license_data['issued_at'] ) ? sanitize_text_field( (string) $license_data['issued_at'] ) : '';
	$last_check   = ! empty( $license_data['last_check'] ) ? (int) $license_data['last_check'] : 0;

	$notice_message = '';
	$notice_class   = 'notice-success';
	if ( isset( $_GET['xpressui_notice'] ) ) {
		$notice_message = sanitize_text_field( wp_unslash( (string) $_GET['xpressui_notice'] ) );
	}
	if ( isset( $_GET['xpressui_notice_type'] ) && 'error' === sanitize_key( wp_unslash( (string) $_GET['xpressui_notice_type'] ) ) ) {
		$notice_class = 'notice-error';
	}

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'XPressUI Pro License', 'xpressui-wordpress-bridge-pro' ) . '</h1>';
	echo '<p>' . esc_html__( 'Activate your commercial Pro license to receive updates and enable Pro-only capabilities shipped by the Pro add-on.', 'xpressui-wordpress-bridge-pro' ) . '</p>';

	if ( '' !== $notice_message ) {
		echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $notice_message ) . '</p></div>';
	}

	echo '<div class="card" style="max-width:900px;">';
	echo '<h2>' . esc_html__( 'License Status', 'xpressui-wordpress-bridge-pro' ) . '</h2>';
	echo '<table class="widefat striped"><tbody>';
	echo '<tr><td><strong>' . esc_html__( 'Current status', 'xpressui-wordpress-bridge-pro' ) . '</strong></td><td>' . esc_html( $is_active ? __( 'Active', 'xpressui-wordpress-bridge-pro' ) : __( 'Inactive', 'xpressui-wordpress-bridge-pro' ) ) . '</td></tr>';
	if ( '' !== $status ) {
		echo '<tr><td><strong>' . esc_html__( 'Signed status', 'xpressui-wordpress-bridge-pro' ) . '</strong></td><td>' . esc_html( xpressui_pro_get_license_error_message( $status ) ) . '</td></tr>';
	}
	if ( '' !== $masked_key ) {
		echo '<tr><td><strong>' . esc_html__( 'Stored key', 'xpressui-wordpress-bridge-pro' ) . '</strong></td><td><code>' . esc_html( $masked_key ) . '</code></td></tr>';
	}
	if ( '' !== $site_url ) {
		echo '<tr><td><strong>' . esc_html__( 'Licensed site', 'xpressui-wordpress-bridge-pro' ) . '</strong></td><td><code>' . esc_html( $site_url ) . '</code></td></tr>';
	}
	if ( '' !== $issued_at ) {
		echo '<tr><td><strong>' . esc_html__( 'Issued at', 'xpressui-wordpress-bridge-pro' ) . '</strong></td><td>' . esc_html( $issued_at ) . '</td></tr>';
	}
	if ( '' !== $expires_at ) {
		echo '<tr><td><strong>' . esc_html__( 'Expires at', 'xpressui-wordpress-bridge-pro' ) . '</strong></td><td>' . esc_html( $expires_at ) . '</td></tr>';
	}
	if ( $last_check > 0 ) {
		echo '<tr><td><strong>' . esc_html__( 'Last verification', 'xpressui-wordpress-bridge-pro' ) . '</strong></td><td>' . esc_html( wp_date( 'Y-m-d H:i:s', $last_check ) ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '</div>';

	echo '<div class="card" style="max-width:900px;">';
	echo '<h2>' . esc_html__( 'Manage License', 'xpressui-wordpress-bridge-pro' ) . '</h2>';
	echo '<p>' . esc_html__( 'Use your Pro license key from your purchase email or customer account.', 'xpressui-wordpress-bridge-pro' ) . '</p>';
	xpressui_pro_render_license_form();
	echo '</div>';

	echo '</div>';
}

/**
 * Renders the Pro license form with Activate/Deactivate buttons.
 */
function xpressui_pro_render_license_form(): void {
	$license_data = get_option( XPRESSUI_PRO_LICENSE_OPTION_KEY, [] );
	$license_data = is_array( $license_data ) ? $license_data : [];
	$is_active    = xpressui_pro_is_license_active();
	$license_key  = isset( $license_data['license_key'] ) ? (string) $license_data['license_key'] : '';
	$masked_key   = xpressui_pro_get_masked_license_key( $license_key );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'xpressui_pro_license_actions', 'xpressui_pro_license_nonce' ); ?>
		<input type="hidden" name="action" value="xpressui_pro_license_actions" />
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th><label for="xpressui_pro_license_key"><?php esc_html_e( 'License key', 'xpressui-wordpress-bridge-pro' ); ?></label></th>
					<td>
						<?php if ( $is_active && '' !== $masked_key ) : ?>
							<code><?php echo esc_html( $masked_key ); ?></code>
							<p class="description"><?php esc_html_e( 'Your current key is active on this site. Deactivate it before entering a different key.', 'xpressui-wordpress-bridge-pro' ); ?></p>
						<?php else : ?>
							<input type="text" id="xpressui_pro_license_key" name="xpressui_pro_license_key" class="regular-text" value="" autocomplete="off" placeholder="iakp_..." />
							<p class="description"><?php esc_html_e( 'Paste the Pro license key issued for this product.', 'xpressui-wordpress-bridge-pro' ); ?></p>
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
