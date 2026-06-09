<?php
/**
 * A5 — Submitter notifications on submission status change (self-hosted / PRO).
 *
 * When a submission moves to pending_info / done / rejected, e-mail the
 * submitter. This existed in the free plugin (added in 92ac643) but was removed
 * during a wordpress.org review (6f0c648). It is reintroduced HERE in the PRO
 * add-on — which is NOT distributed via wordpress.org — so it is not subject to
 * that review.
 *
 * Self-contained: reads submission DATA via post_meta only; it never calls a
 * function of the free plugin (wordpress.org forbids dormant code in the free
 * that is only activated by a paid add-on). Reading post_meta is data, not code.
 *
 * Active only in "local" autonomy mode (autonomy.php); in cloud mode the
 * IntakeFlow SaaS sends these notifications.
 *
 * NOTE: code-complete + lint-clean, but requires testing on a WordPress staging
 * site (wp_mail delivery, WP-Cron dispatch, status transitions) before release.
 *
 * @package XPressUI_Bridge_Pro
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'XPRESSUI_PRO_NOTIFY_STATUS_OPTION_KEY' ) ) {
	define( 'XPRESSUI_PRO_NOTIFY_STATUS_OPTION_KEY', 'xpressui_pro_notify_submitter_on_status_change' );
}
if ( ! defined( 'XPRESSUI_PRO_STATUS_NOTIFY_HOOK' ) ) {
	define( 'XPRESSUI_PRO_STATUS_NOTIFY_HOOK', 'xpressui_pro_dispatch_status_notification' );
}

/**
 * Statuses that trigger a submitter notification.
 *
 * @return array<int,string>
 */
function xpressui_pro_status_notify_statuses(): array {
	return array( 'pending_info', 'done', 'rejected' );
}

/**
 * Whether submitter status-change notifications are enabled: only in local
 * autonomy mode, and when the toggle is on (default on).
 */
function xpressui_pro_status_notifications_enabled(): bool {
	if ( ! function_exists( 'xpressui_pro_is_autonomous' ) || ! xpressui_pro_is_autonomous() ) {
		return false;
	}
	return (bool) get_option( XPRESSUI_PRO_NOTIFY_STATUS_OPTION_KEY, '1' );
}

add_action( 'updated_postmeta', 'xpressui_pro_on_submission_status_meta', 10, 4 );
add_action( 'added_postmeta', 'xpressui_pro_on_submission_status_meta', 10, 4 );

/**
 * Schedules an async notification when a submission's status meta changes.
 *
 * @param int    $meta_id    Unused.
 * @param int    $post_id    Submission post ID.
 * @param string $meta_key   Meta key being written.
 * @param mixed  $meta_value New status value.
 */
function xpressui_pro_on_submission_status_meta( $meta_id, $post_id, $meta_key, $meta_value ): void {
	unset( $meta_id );
	if ( '_xpressui_submission_status' !== $meta_key ) {
		return;
	}
	$status = is_string( $meta_value ) ? $meta_value : '';
	if ( ! in_array( $status, xpressui_pro_status_notify_statuses(), true ) ) {
		return;
	}
	if ( 'xpressui_submission' !== get_post_type( (int) $post_id ) ) {
		return;
	}
	if ( ! xpressui_pro_status_notifications_enabled() ) {
		return;
	}

	// Defer: the free plugin writes additional meta (resume token, timestamps)
	// right after the status; running after the request keeps the data settled
	// and never blocks the admin action on e-mail delivery.
	$args = array( (int) $post_id, $status );
	if ( ! wp_next_scheduled( XPRESSUI_PRO_STATUS_NOTIFY_HOOK, $args ) ) {
		wp_schedule_single_event( time() + 5, XPRESSUI_PRO_STATUS_NOTIFY_HOOK, $args );
	}
}

add_action( XPRESSUI_PRO_STATUS_NOTIFY_HOOK, 'xpressui_pro_send_status_notification', 10, 2 );

/**
 * Sends the submitter notification e-mail for a submission + status.
 *
 * @param int    $post_id Submission post ID.
 * @param string $status  Status the notification was scheduled for.
 */
function xpressui_pro_send_status_notification( $post_id, $status ): void {
	$post_id = (int) $post_id;
	$status  = (string) $status;

	if ( 'xpressui_submission' !== get_post_type( $post_id ) ) {
		return;
	}
	if ( ! xpressui_pro_status_notifications_enabled() ) {
		return;
	}
	// Only notify for the submission's CURRENT status (skip a superseded one).
	$current = (string) get_post_meta( $post_id, '_xpressui_submission_status', true );
	if ( $current !== $status || ! in_array( $status, xpressui_pro_status_notify_statuses(), true ) ) {
		return;
	}

	$to_email = xpressui_pro_get_submitter_email( $post_id );
	if ( '' === $to_email ) {
		return;
	}

	$project_slug = (string) get_post_meta( $post_id, '_xpressui_project_slug', true );
	$note         = (string) get_post_meta( $post_id, '_xpressui_review_note', true );

	$subject = xpressui_pro_build_status_subject( $status, $project_slug );
	$body    = xpressui_pro_build_status_body( $post_id, $status, $project_slug, $note );

	wp_mail( $to_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
}

/**
 * Reads the submitter e-mail from the stored submission payload (data only).
 */
function xpressui_pro_get_submitter_email( int $post_id ): string {
	$json    = (string) get_post_meta( $post_id, '_xpressui_payload_json', true );
	$payload = '' !== $json ? json_decode( $json, true ) : array();
	$email   = is_array( $payload ) ? trim( (string) ( $payload['email'] ?? '' ) ) : '';
	return is_email( $email ) ? $email : '';
}

/**
 * Builds the subject line for a given status.
 */
function xpressui_pro_build_status_subject( string $status, string $project_slug ): string {
	$site = get_bloginfo( 'name' );
	switch ( $status ) {
		case 'done':
			/* translators: 1: site name, 2: workflow slug */
			return sprintf( __( '[%1$s] Your submission for %2$s has been processed', 'xpressui-bridge-pro' ), $site, $project_slug );
		case 'rejected':
			/* translators: 1: site name, 2: workflow slug */
			return sprintf( __( '[%1$s] Update on your submission for %2$s', 'xpressui-bridge-pro' ), $site, $project_slug );
		case 'pending_info':
		default:
			/* translators: 1: site name, 2: workflow slug */
			return sprintf( __( '[%1$s] Your submission for %2$s needs additional information', 'xpressui-bridge-pro' ), $site, $project_slug );
	}
}

/**
 * Builds the HTML e-mail body for a given status.
 */
function xpressui_pro_build_status_body( int $post_id, string $status, string $project_slug, string $note ): string {
	$site_name = esc_html( get_bloginfo( 'name' ) );

	switch ( $status ) {
		case 'done':
			$header_label = __( 'Your submission has been processed', 'xpressui-bridge-pro' );
			/* translators: %s: workflow slug */
			$intro   = sprintf( __( 'Good news — your submission for %s has been processed by our team.', 'xpressui-bridge-pro' ), $project_slug );
			$closing = __( 'No further action is required on your part.', 'xpressui-bridge-pro' );
			break;
		case 'rejected':
			$header_label = __( 'Update on your submission', 'xpressui-bridge-pro' );
			/* translators: %s: workflow slug */
			$intro   = sprintf( __( 'After review, your submission for %s could not be accepted.', 'xpressui-bridge-pro' ), $project_slug );
			$closing = __( 'Please reply to this email if you have any questions.', 'xpressui-bridge-pro' );
			break;
		case 'pending_info':
		default:
			$header_label = __( 'Additional information required', 'xpressui-bridge-pro' );
			/* translators: %s: workflow slug */
			$intro   = sprintf( __( 'Thank you for your submission for %s. After review, our team needs some additional information before we can proceed.', 'xpressui-bridge-pro' ), $project_slug );
			$closing = __( 'Use the button below to complete the requested corrections, or reply to this email.', 'xpressui-bridge-pro' );
			break;
	}

	$header_html  = esc_html( $header_label );
	$intro_html   = esc_html( $intro );
	$closing_html = esc_html( $closing );

	$note_html = '';
	if ( '' !== trim( $note ) ) {
		$note_html = '<p style="margin:16px 0 0;padding:14px 16px;background:#fffaf0;border-left:3px solid #f6cc87;font-size:13px;color:#374151;line-height:1.6;">'
			. nl2br( esc_html( $note ) ) . '</p>';
	}

	// Optional operator-provided "done info" file (e.g. a finalized document).
	$file_html = '';
	if ( 'done' === $status ) {
		$file_id = (int) get_post_meta( $post_id, '_xpressui_done_info_file_id', true );
		if ( $file_id > 0 ) {
			$file_url = wp_get_attachment_url( $file_id );
			if ( $file_url ) {
				$file_html = '<p style="margin:20px 0 0;"><a href="' . esc_url( $file_url )
					. '" style="display:inline-block;padding:10px 18px;background:#1d2327;color:#ffffff;border-radius:6px;font-size:13px;text-decoration:none;">'
					. esc_html__( 'Download your document', 'xpressui-bridge-pro' ) . '</a></p>';
			}
		}
	}

	// Resume link for pending_info: lets the submitter re-open the form
	// pre-filled and correct only the flagged fields (see resubmission.php).
	$cta_html = '';
	if ( 'pending_info' === $status && function_exists( 'xpressui_pro_build_resume_url' ) ) {
		$resume_url = xpressui_pro_build_resume_url( $post_id );
		if ( '' !== $resume_url ) {
			$cta_html = '<p style="margin:20px 0 0;"><a href="' . esc_url( $resume_url )
				. '" style="display:inline-block;padding:11px 20px;background:#c2562a;color:#ffffff;border-radius:6px;font-size:14px;font-weight:600;text-decoration:none;">'
				. esc_html__( 'Complete my submission', 'xpressui-bridge-pro' ) . '</a></p>';
		}
	}

	/* translators: %s: site name */
	$footer_note = esc_html( sprintf( __( 'Sent by %s.', 'xpressui-bridge-pro' ), get_bloginfo( 'name' ) ) );

	return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">'
		. '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px;"><tr><td align="center">'
		. '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.08);max-width:600px;">'
		. '<tr><td style="background:#1d2327;padding:22px 28px;">'
		. '<p style="margin:0;font-size:15px;font-weight:700;color:#ffffff;">' . $site_name . '</p>'
		. '<p style="margin:4px 0 0;font-size:13px;color:#9ca3af;">' . $header_html . '</p></td></tr>'
		. '<tr><td style="padding:28px 28px 24px;">'
		. '<p style="margin:0;font-size:14px;color:#374151;line-height:1.6;">' . $intro_html . '</p>'
		. $note_html
		. $file_html
		. $cta_html
		. '<p style="margin:20px 0 0;font-size:13px;color:#6b7280;line-height:1.6;">' . $closing_html . '</p>'
		. '</td></tr>'
		. '<tr><td style="padding:16px 28px;background:#f9fafb;border-top:1px solid #f0f0f0;">'
		. '<p style="margin:0;font-size:11px;color:#d1d5db;">' . $footer_note . '</p></td></tr>'
		. '</table></td></tr></table></body></html>';
}
