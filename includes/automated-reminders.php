<?php
/**
 * Automated reminders — Pro feature.
 *
 * Daily WP-Cron pass that emails submitters whose files have been sitting
 * in `new` or `in-review` status for longer than the configured delay.
 *
 * Settings stored in `xpressui_project_settings[$slug]`:
 *   proReminderEnabled     bool    true/false
 *   proReminderDelayHours  int     hours between reminder sends (default 48)
 *   proReminderMaxCount    int     max emails per submission (default 3)
 *   proReminderSubject     string  optional custom subject
 *   proReminderMessage     string  optional custom intro text
 *
 * Per-submission state (post meta):
 *   _xpressui_reminder_count      int       number of reminders sent so far
 *   _xpressui_reminder_last_sent  int       Unix timestamp of last send
 *
 * @package XPressUI_Bridge_Pro
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Cron schedule
// ---------------------------------------------------------------------------

add_action( 'xpressui_pro_process_reminders', 'xpressui_pro_run_reminder_pass' );

add_action( 'plugins_loaded', 'xpressui_pro_maybe_schedule_reminders' );

function xpressui_pro_maybe_schedule_reminders(): void {
	if ( ! wp_next_scheduled( 'xpressui_pro_process_reminders' ) ) {
		wp_schedule_event( time(), 'daily', 'xpressui_pro_process_reminders' );
	}
}

// ---------------------------------------------------------------------------
// Cron handler
// ---------------------------------------------------------------------------

function xpressui_pro_run_reminder_pass(): void {
	$all_settings = get_option( 'xpressui_project_settings', [] );
	if ( ! is_array( $all_settings ) ) {
		return;
	}

	foreach ( $all_settings as $slug => $s ) {
		if ( ! is_array( $s ) ) {
			continue;
		}
		$enabled = filter_var( $s['proReminderEnabled'] ?? false, FILTER_VALIDATE_BOOLEAN );
		if ( ! $enabled ) {
			continue;
		}

		$delay_hours = max( 1, (int) ( $s['proReminderDelayHours'] ?? 48 ) );
		$max_count   = max( 1, (int) ( $s['proReminderMaxCount'] ?? 3 ) );

		xpressui_pro_send_reminders_for_project( (string) $slug, $delay_hours, $max_count, $s );
	}
}

function xpressui_pro_send_reminders_for_project( string $slug, int $delay_hours, int $max_count, array $s ): void {
	$now        = time();
	$delay_secs = $delay_hours * HOUR_IN_SECONDS;

	$query = new WP_Query( [
		'post_type'      => 'xpressui_submission',
		'post_status'    => 'publish',
		'posts_per_page' => 200,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			[
				'key'     => '_xpressui_project_slug',
				'value'   => $slug,
				'compare' => '=',
			],
			[
				'key'     => '_xpressui_submission_status',
				'value'   => [ 'new', 'in-review' ],
				'compare' => 'IN',
			],
		],
	] );

	if ( empty( $query->posts ) ) {
		return;
	}

	foreach ( $query->posts as $post_id ) {
		$post_id = (int) $post_id;

		$count       = (int) get_post_meta( $post_id, '_xpressui_reminder_count', true );
		$last_sent   = (int) get_post_meta( $post_id, '_xpressui_reminder_last_sent', true );

		if ( $count >= $max_count ) {
			continue;
		}

		if ( $last_sent > 0 ) {
			if ( ( $now - $last_sent ) < $delay_secs ) {
				continue;
			}
		} else {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}
			$created_ts = strtotime( $post->post_date_gmt );
			if ( $created_ts === false || ( $now - $created_ts ) < $delay_secs ) {
				continue;
			}
		}

		$sent = xpressui_pro_send_reminder_email( $post_id, $slug, $s );
		if ( $sent ) {
			update_post_meta( $post_id, '_xpressui_reminder_count', $count + 1 );
			update_post_meta( $post_id, '_xpressui_reminder_last_sent', $now );
		}
	}
}

function xpressui_pro_send_reminder_email( int $post_id, string $slug, array $s ): bool {
	if ( ! function_exists( 'xpressui_get_submitter_email' ) ) {
		return false;
	}

	$to_email = xpressui_get_submitter_email( $post_id );
	if ( '' === $to_email ) {
		return false;
	}

	$site_name       = get_bloginfo( 'name' );
	$workflow_label  = function_exists( 'xpressui_get_submitter_workflow_label' )
		? xpressui_get_submitter_workflow_label( $slug )
		: $slug;
	$header_name     = function_exists( 'xpressui_get_submitter_email_header_name' )
		? xpressui_get_submitter_email_header_name( $slug )
		: __( 'Submission update', 'xpressui-wordpress-bridge-pro' );

	$custom_subject = trim( (string) ( $s['proReminderSubject'] ?? '' ) );
	$subject = $custom_subject !== ''
		? $custom_subject
		: sprintf(
			/* translators: 1: site name, 2: workflow label */
			__( '[%1$s / %2$s] Reminder: your submission is still being reviewed', 'xpressui-wordpress-bridge-pro' ),
			$site_name,
			$workflow_label
		);

	$custom_message = trim( (string) ( $s['proReminderMessage'] ?? '' ) );
	$intro = $custom_message !== ''
		? esc_html( $custom_message )
		: esc_html( sprintf(
			/* translators: %s: workflow label */
			__( 'This is a friendly reminder that your submission for %s is still under review. No action is needed on your end — we will reach out as soon as it is processed.', 'xpressui-wordpress-bridge-pro' ),
			$workflow_label
		) );

	$status_token = (string) get_post_meta( $post_id, '_xpressui_status_token', true );
	$cta_html     = '';
	if ( $status_token !== '' && function_exists( 'xpressui_pro_get_status_page_url' ) ) {
		$status_url = xpressui_pro_get_status_page_url( $status_token );
		$cta_html   = '<div style="margin:24px 0 0;text-align:center;">'
			. '<a href="' . esc_url( $status_url ) . '" style="display:inline-block;padding:10px 20px;background:#f0f4ff;color:#1e3a8a;text-decoration:none;border-radius:6px;font-size:14px;font-weight:600;border:1px solid #bfdbfe;">'
			. esc_html__( 'Track your submission', 'xpressui-wordpress-bridge-pro' )
			. '</a>'
			. '</div>';
	}

	$footer_note = sprintf(
		/* translators: %s: site name */
		__( 'Sent by %s.', 'xpressui-wordpress-bridge-pro' ),
		$site_name
	);

	$body    = xpressui_build_submitter_email_html(
		$header_name,
		__( 'Submission reminder', 'xpressui-wordpress-bridge-pro' ),
		'#2271b1',
		$intro,
		'',
		$footer_note,
		$cta_html
	);
	$headers = xpressui_build_notification_headers();

	return (bool) wp_mail( $to_email, $subject, $body, $headers );
}

// ---------------------------------------------------------------------------
// Cron cleanup on deactivation
// ---------------------------------------------------------------------------

register_deactivation_hook( XPRESSUI_PRO_DIR . 'xpressui-wordpress-bridge-pro.php', 'xpressui_pro_clear_reminder_cron' );

function xpressui_pro_clear_reminder_cron(): void {
	$timestamp = wp_next_scheduled( 'xpressui_pro_process_reminders' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'xpressui_pro_process_reminders' );
	}
}

// ---------------------------------------------------------------------------
// Workflow Settings — render section
// ---------------------------------------------------------------------------

add_action( 'xpressui_workflow_settings_extra_sections', 'xpressui_pro_render_reminders_section', 20, 3 );

function xpressui_pro_render_reminders_section( string $slug, array $s, $overlay ): void {
	$enabled     = filter_var( $s['proReminderEnabled'] ?? false, FILTER_VALIDATE_BOOLEAN );
	$delay_hours = (string) ( $s['proReminderDelayHours'] ?? '48' );
	$max_count   = (string) ( $s['proReminderMaxCount'] ?? '3' );
	$subject     = (string) ( $s['proReminderSubject'] ?? '' );
	$message     = (string) ( $s['proReminderMessage'] ?? '' );

	echo '<div class="card xpressui-admin-card">';
	echo '<details><summary><h2>' . esc_html__( 'Automated Reminders', 'xpressui-wordpress-bridge-pro' ) . '</h2><span class="xpressui-toggle-icon" aria-hidden="true">▾</span></summary>';
	echo '<p>' . esc_html__( 'Send automatic follow-up emails to submitters whose files are still in review after a configurable delay.', 'xpressui-wordpress-bridge-pro' ) . '</p>';
	echo '<table class="form-table"><tbody>';

	echo '<tr><th><label for="xpressui_reminder_enabled">' . esc_html__( 'Enable reminders', 'xpressui-wordpress-bridge-pro' ) . '</label></th>';
	echo '<td><input type="checkbox" id="xpressui_reminder_enabled" name="xpressui_reminder_enabled" value="1"' . checked( $enabled, true, false ) . '>';
	echo '<p class="description">' . esc_html__( 'Check to activate automatic reminder emails for this workflow.', 'xpressui-wordpress-bridge-pro' ) . '</p></td></tr>';

	echo '<tr><th><label for="xpressui_reminder_delay_hours">' . esc_html__( 'Delay (hours)', 'xpressui-wordpress-bridge-pro' ) . '</label></th>';
	echo '<td><input type="number" id="xpressui_reminder_delay_hours" name="xpressui_reminder_delay_hours" class="small-text" min="1" step="1" value="' . esc_attr( $delay_hours ) . '" placeholder="48">';
	echo '<p class="description">' . esc_html__( 'Hours to wait after submission (or after the previous reminder) before sending the next one.', 'xpressui-wordpress-bridge-pro' ) . '</p></td></tr>';

	echo '<tr><th><label for="xpressui_reminder_max_count">' . esc_html__( 'Max reminders', 'xpressui-wordpress-bridge-pro' ) . '</label></th>';
	echo '<td><input type="number" id="xpressui_reminder_max_count" name="xpressui_reminder_max_count" class="small-text" min="1" max="10" step="1" value="' . esc_attr( $max_count ) . '" placeholder="3">';
	echo '<p class="description">' . esc_html__( 'Maximum number of reminder emails to send per submission.', 'xpressui-wordpress-bridge-pro' ) . '</p></td></tr>';

	echo '<tr><th><label for="xpressui_reminder_subject">' . esc_html__( 'Custom subject (optional)', 'xpressui-wordpress-bridge-pro' ) . '</label></th>';
	echo '<td><input type="text" id="xpressui_reminder_subject" name="xpressui_reminder_subject" class="regular-text" value="' . esc_attr( $subject ) . '" placeholder="' . esc_attr__( 'Leave empty to use the default subject', 'xpressui-wordpress-bridge-pro' ) . '">';
	echo '</td></tr>';

	echo '<tr><th><label for="xpressui_reminder_message">' . esc_html__( 'Custom message (optional)', 'xpressui-wordpress-bridge-pro' ) . '</label></th>';
	echo '<td><textarea id="xpressui_reminder_message" name="xpressui_reminder_message" class="large-text" rows="3" placeholder="' . esc_attr__( 'Leave empty to use the default message', 'xpressui-wordpress-bridge-pro' ) . '">' . esc_textarea( $message ) . '</textarea>';
	echo '</td></tr>';

	echo '</tbody></table>';
	echo '</details>';
	echo '</div>';
}

// ---------------------------------------------------------------------------
// Workflow Settings — save handler
// ---------------------------------------------------------------------------

add_action( 'xpressui_workflow_settings_extra_save', 'xpressui_pro_save_reminders_settings', 20 );

function xpressui_pro_save_reminders_settings( string $slug ): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by caller
	$enabled     = ! empty( $_POST['xpressui_reminder_enabled'] );
	$delay_hours = isset( $_POST['xpressui_reminder_delay_hours'] ) ? max( 1, (int) $_POST['xpressui_reminder_delay_hours'] ) : 48;
	$max_count   = isset( $_POST['xpressui_reminder_max_count'] ) ? max( 1, min( 10, (int) $_POST['xpressui_reminder_max_count'] ) ) : 3;
	$subject     = sanitize_text_field( wp_unslash( (string) ( $_POST['xpressui_reminder_subject'] ?? '' ) ) );
	$message     = sanitize_textarea_field( wp_unslash( (string) ( $_POST['xpressui_reminder_message'] ?? '' ) ) );
	// phpcs:enable

	$all_settings = get_option( 'xpressui_project_settings', [] );
	if ( ! is_array( $all_settings ) ) {
		$all_settings = [];
	}
	if ( ! isset( $all_settings[ $slug ] ) || ! is_array( $all_settings[ $slug ] ) ) {
		$all_settings[ $slug ] = [];
	}

	$all_settings[ $slug ]['proReminderEnabled']    = $enabled ? '1' : '0';
	$all_settings[ $slug ]['proReminderDelayHours'] = $delay_hours;
	$all_settings[ $slug ]['proReminderMaxCount']   = $max_count;
	$all_settings[ $slug ]['proReminderSubject']    = $subject;
	$all_settings[ $slug ]['proReminderMessage']    = $message;

	update_option( 'xpressui_project_settings', $all_settings );
}
