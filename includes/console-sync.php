<?php
/**
 * Console Sync — pull workflow packs directly from the XPressUI Console.
 *
 * @package XPressUI_Bridge_Pro
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Settings helpers
// ---------------------------------------------------------------------------

function xpressui_get_console_connection(): array {
	$defaults = [ 'apiUrl' => '', 'apiToken' => '' ];
	$stored   = get_option( 'xpressui_console_connection', [] );
	return is_array( $stored ) ? array_merge( $defaults, $stored ) : $defaults;
}

function xpressui_render_console_connection_form(): void {
	$conn = xpressui_get_console_connection();
	?>
	<form id="xpressui-console-connection-form" method="post" data-ajax-action="xpressui_save_console_connection">
		<?php wp_nonce_field( 'xpressui_console_connection_action', 'xpressui_console_connection_nonce' ); ?>
		<input type="hidden" name="xpressui_save_console_connection" value="1">
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="xpressui_console_api_url"><?php esc_html_e( 'Console API URL', 'xpressui-wordpress-bridge-pro' ); ?></label></th>
				<td>
					<input type="url" id="xpressui_console_api_url" name="xpressui_console_api_url"
						value="<?php echo esc_attr( $conn['apiUrl'] ); ?>"
						class="regular-text" placeholder="https://your-console.example.com">
					<p class="description"><?php esc_html_e( 'Base URL of your XPressUI Console instance (no trailing slash).', 'xpressui-wordpress-bridge-pro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="xpressui_console_api_token"><?php esc_html_e( 'API Token', 'xpressui-wordpress-bridge-pro' ); ?></label></th>
				<td>
					<input type="password" id="xpressui_console_api_token" name="xpressui_console_api_token"
						value="<?php echo esc_attr( $conn['apiToken'] ); ?>"
						class="regular-text" autocomplete="off">
					<p class="description">
						<?php esc_html_e( 'Generate a token in the Console under Profile → API Tokens.', 'xpressui-wordpress-bridge-pro' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Save Connection', 'xpressui-wordpress-bridge-pro' ), 'secondary', 'submit', false ); ?>
		<span class="xpressui-ajax-status" style="margin-left:1rem;vertical-align:middle"></span>
	</form>
	<?php
}

// ---------------------------------------------------------------------------
// AJAX: save console connection
// ---------------------------------------------------------------------------

add_action( 'wp_ajax_xpressui_save_console_connection', 'xpressui_ajax_save_console_connection' );

function xpressui_ajax_save_console_connection(): void {
	check_ajax_referer( 'xpressui_console_connection_action', 'xpressui_console_connection_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'xpressui-wordpress-bridge-pro' ) ], 403 );
	}
	$api_url   = esc_url_raw( trim( wp_unslash( (string) ( $_POST['xpressui_console_api_url'] ?? '' ) ) ) );
	$api_token = sanitize_text_field( wp_unslash( (string) ( $_POST['xpressui_console_api_token'] ?? '' ) ) );
	update_option( 'xpressui_console_connection', [ 'apiUrl' => $api_url, 'apiToken' => $api_token ] );
	wp_send_json_success( [ 'message' => __( 'Console connection saved.', 'xpressui-wordpress-bridge-pro' ) ] );
}

// ---------------------------------------------------------------------------
// AJAX: list projects from the Console
// ---------------------------------------------------------------------------

add_action( 'wp_ajax_xpressui_console_list_projects', 'xpressui_ajax_console_list_projects' );

function xpressui_ajax_console_list_projects(): void {
	check_ajax_referer( 'xpressui_console_sync_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'xpressui-wordpress-bridge-pro' ) ], 403 );
	}

	if ( ! xpressui_pro_is_license_active() ) {
		wp_send_json_error( [ 'message' => __( 'Console Sync requires an active XPressUI Pro license.', 'xpressui-wordpress-bridge-pro' ) ], 403 );
	}

	$conn = xpressui_get_console_connection();
	if ( empty( $conn['apiUrl'] ) || empty( $conn['apiToken'] ) ) {
		wp_send_json_error( [ 'message' => __( 'Console connection not configured. Save your API URL and token first.', 'xpressui-wordpress-bridge-pro' ) ] );
	}

	$response = wp_remote_get(
		trailingslashit( $conn['apiUrl'] ) . 'api/v1/projects',
		[
			'headers' => [
				'X-Api-Token' => $conn['apiToken'],
				'Accept'      => 'application/json',
			],
			'timeout' => 15,
		]
	);

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( [ 'message' => $response->get_error_message() ] );
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		/* translators: %d: HTTP status code returned by the Console API */
		wp_send_json_error( [ 'message' => sprintf( __( 'Console API returned status %d. Check your API URL and token.', 'xpressui-wordpress-bridge-pro' ), $code ) ] );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	wp_send_json_success( [ 'projects' => is_array( $body['items'] ?? null ) ? $body['items'] : [] ] );
}

// ---------------------------------------------------------------------------
// AJAX: sync (download + install) one project
// ---------------------------------------------------------------------------

add_action( 'wp_ajax_xpressui_console_sync_project', 'xpressui_ajax_console_sync_project' );

function xpressui_ajax_console_sync_project(): void {
	check_ajax_referer( 'xpressui_console_sync_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'xpressui-wordpress-bridge-pro' ) ], 403 );
	}

	if ( ! xpressui_pro_is_license_active() ) {
		wp_send_json_error( [ 'message' => __( 'Console Sync requires an active XPressUI Pro license.', 'xpressui-wordpress-bridge-pro' ) ], 403 );
	}

	$project_id = sanitize_text_field( wp_unslash( (string) ( $_POST['project_id'] ?? '' ) ) );
	if ( '' === $project_id ) {
		wp_send_json_error( [ 'message' => __( 'Missing project_id.', 'xpressui-wordpress-bridge-pro' ) ] );
	}

	$conn = xpressui_get_console_connection();
	if ( empty( $conn['apiUrl'] ) || empty( $conn['apiToken'] ) ) {
		wp_send_json_error( [ 'message' => __( 'Console connection not configured.', 'xpressui-wordpress-bridge-pro' ) ] );
	}

	$response = wp_remote_get(
		trailingslashit( $conn['apiUrl'] ) . 'api/v1/projects/' . rawurlencode( $project_id ) . '/download',
		[
			'headers' => [
				'X-Api-Token' => $conn['apiToken'],
			],
			'timeout' => 30,
		]
	);

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( [ 'message' => $response->get_error_message() ] );
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		/* translators: %d: HTTP status code returned by the Console API */
		wp_send_json_error( [ 'message' => sprintf( __( 'Download failed (status %d).', 'xpressui-wordpress-bridge-pro' ), $code ) ] );
	}

	$zip_content = wp_remote_retrieve_body( $response );
	$tmp_zip     = wp_tempnam( 'xpressui-sync' ) . '.zip';
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	if ( false === file_put_contents( $tmp_zip, $zip_content ) ) {
		wp_send_json_error( [ 'message' => __( 'Could not write temporary ZIP file.', 'xpressui-wordpress-bridge-pro' ) ] );
	}

	$content_disp  = wp_remote_retrieve_header( $response, 'content-disposition' );
	preg_match( '/filename="?([^";\s]+)"?/', (string) $content_disp, $matches );
	$original_name = isset( $matches[1] ) ? sanitize_file_name( $matches[1] ) : 'sync.zip';

	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();

	$inspection = xpressui_validate_workflow_zip( $tmp_zip, $original_name );
	if ( is_wp_error( $inspection ) ) {
		wp_delete_file( $tmp_zip );
		wp_send_json_error( [ 'message' => $inspection->get_error_message() ] );
	}

	$target_dir = xpressui_get_workflows_base_dir();
	if ( '' === $target_dir ) {
		wp_delete_file( $tmp_zip );
		wp_send_json_error( [ 'message' => __( 'The uploads directory is not available.', 'xpressui-wordpress-bridge-pro' ) ] );
	}

	if ( ! file_exists( $target_dir ) ) {
		wp_mkdir_p( $target_dir );
		global $wp_filesystem;
		$wp_filesystem->put_contents( $target_dir . 'index.php', '<?php' . PHP_EOL . '// Silence is golden.' . PHP_EOL, FS_CHMOD_FILE );
	}

	$slug     = (string) $inspection['slug'];
	$slug_dir = trailingslashit( $target_dir ) . $slug . '/';

	global $wp_filesystem;
	if ( file_exists( $slug_dir ) ) {
		$wp_filesystem->delete( $slug_dir, true );
	}

	$unzip_result = unzip_file( $tmp_zip, $target_dir );
	wp_delete_file( $tmp_zip );

	if ( is_wp_error( $unzip_result ) ) {
		wp_send_json_error( [ 'message' => $unzip_result->get_error_message() ] );
	}

	/* translators: %s: installed workflow slug */
	wp_send_json_success( [
		'slug'    => $slug,
		'message' => sprintf( __( 'Synced! Embed with: [xpressui id="%s"]', 'xpressui-wordpress-bridge-pro' ), $slug ),
	] );
}

// ---------------------------------------------------------------------------
// UI section — injected into the free plugin's Workflows page
// ---------------------------------------------------------------------------

add_action( 'xpressui_workflows_page_sections', 'xpressui_pro_render_console_sync_section' );

function xpressui_pro_render_console_sync_section(): void {
	$nonce = wp_create_nonce( 'xpressui_console_sync_nonce' );
	?>
	<div class="card xpressui-admin-card">
		<h2><?php esc_html_e( 'Console Sync', 'xpressui-wordpress-bridge-pro' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Pull workflow packs directly from your XPressUI Console — no ZIP download required.', 'xpressui-wordpress-bridge-pro' ); ?>
		</p>

		<h3 style="margin-top:1rem"><?php esc_html_e( 'Connection', 'xpressui-wordpress-bridge-pro' ); ?></h3>
		<?php xpressui_render_console_connection_form(); ?>

		<h3 style="margin-top:1.5rem"><?php esc_html_e( 'Your Workflows', 'xpressui-wordpress-bridge-pro' ); ?></h3>
		<p>
			<button type="button" id="xpressui-load-projects" class="button button-secondary">
				<?php esc_html_e( 'Load from Console', 'xpressui-wordpress-bridge-pro' ); ?>
			</button>
		</p>
		<div id="xpressui-projects-list"></div>

		<script>
		(function () {
			var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

			function setStatus(el, msg, isError) {
				el.innerHTML = '<p style="color:' + (isError ? '#c00' : '#3a3') + '">' + msg + '</p>';
			}

			document.getElementById('xpressui-load-projects').addEventListener('click', function () {
				var list = document.getElementById('xpressui-projects-list');
				list.innerHTML = '<p><?php echo esc_js( __( 'Loading…', 'xpressui-wordpress-bridge-pro' ) ); ?></p>';

				var data = new URLSearchParams();
				data.set('action', 'xpressui_console_list_projects');
				data.set('nonce', nonce);

				fetch(ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (res) {
						if (!res.success) {
							setStatus(list, res.data.message, true);
							return;
						}
						var projects = res.data.projects;
						if (!projects.length) {
							list.innerHTML = '<p><?php echo esc_js( __( 'No projects found in your Console.', 'xpressui-wordpress-bridge-pro' ) ); ?></p>';
							return;
						}
						var html = '<table class="widefat striped"><thead><tr>'
							+ '<th><?php echo esc_js( __( 'Name', 'xpressui-wordpress-bridge-pro' ) ); ?></th>'
							+ '<th><?php echo esc_js( __( 'Slug', 'xpressui-wordpress-bridge-pro' ) ); ?></th>'
							+ '<th><?php echo esc_js( __( 'Updated', 'xpressui-wordpress-bridge-pro' ) ); ?></th>'
							+ '<th></th></tr></thead><tbody>';
						projects.forEach(function (p) {
							var date = p.updatedAt ? new Date(p.updatedAt).toLocaleDateString() : '—';
							html += '<tr id="xpressui-row-' + p.id + '">'
								+ '<td>' + p.name + '</td>'
								+ '<td><code>' + p.slug + '</code></td>'
								+ '<td>' + date + '</td>'
								+ '<td><button type="button" class="button button-primary xpressui-sync-btn" data-id="' + p.id + '">'
								+ '<?php echo esc_js( __( 'Sync', 'xpressui-wordpress-bridge-pro' ) ); ?></button></td>'
								+ '</tr>';
						});
						html += '</tbody></table>';
						list.innerHTML = html;

						list.querySelectorAll('.xpressui-sync-btn').forEach(function (btn) {
							btn.addEventListener('click', function () {
								var projectId = this.dataset.id;
								var row = document.getElementById('xpressui-row-' + projectId);
								this.disabled = true;
								this.textContent = '<?php echo esc_js( __( 'Syncing…', 'xpressui-wordpress-bridge-pro' ) ); ?>';

								var syncData = new URLSearchParams();
								syncData.set('action', 'xpressui_console_sync_project');
								syncData.set('nonce', nonce);
								syncData.set('project_id', projectId);

								fetch(ajaxUrl, { method: 'POST', body: syncData, credentials: 'same-origin' })
									.then(function (r) { return r.json(); })
									.then(function (res) {
										if (!res.success) {
											row.querySelector('td:last-child').innerHTML =
												'<span style="color:#c00">' + res.data.message + '</span>';
										} else {
											row.querySelector('td:last-child').innerHTML =
												'<span style="color:#3a3">✓ ' + res.data.message + ' — Reloading…</span>';
											sessionStorage.setItem('xpressui_synced_slug', res.data.slug || '');
											setTimeout(function () { window.location.reload(); }, 1200);
										}
									})
									.catch(function () {
										row.querySelector('td:last-child').innerHTML =
											'<span style="color:#c00"><?php echo esc_js( __( 'Network error.', 'xpressui-wordpress-bridge-pro' ) ); ?></span>';
									});
							});
						});
					})
					.catch(function () {
						setStatus(list, '<?php echo esc_js( __( 'Network error. Check your connection.', 'xpressui-wordpress-bridge-pro' ) ); ?>', true);
					});
			});
		}());
		</script>
	</div>
	<?php
}
