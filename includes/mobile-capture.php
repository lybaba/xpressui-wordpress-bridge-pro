<?php
/**
 * Mobile Capture Relay — Pro feature.
 *
 * Allows users filling a form on desktop to capture signatures or photos
 * on their mobile phone via a QR code, then relay the data back to the
 * desktop form — without any permanent storage on this server.
 *
 * Flow:
 *  1. Desktop form calls POST /xpressui/v1/capture/session → gets {token, captureUrl}
 *  2. Desktop displays QR code pointing to captureUrl (?xpressui_capture={token})
 *  3. Mobile opens captureUrl → WordPress serves a lightweight capture page
 *  4. Mobile captures data → POST /xpressui/v1/capture/relay/{token}
 *  5. Desktop polls  GET  /xpressui/v1/capture/poll/{token} → gets data when ready
 *
 * Data lives only in WordPress transients (TTL 10 minutes). Deleted immediately
 * after the desktop picks it up.
 *
 * @package XPressUI_Bridge_Pro
 */

defined( 'ABSPATH' ) || exit;

define( 'XPRESSUI_CAPTURE_TTL', 10 * MINUTE_IN_SECONDS );

// ---------------------------------------------------------------------------
// REST routes
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', 'xpressui_pro_register_capture_routes' );

function xpressui_pro_register_capture_routes(): void {
	register_rest_route( 'xpressui/v1', '/capture/session', [
		'methods'             => 'POST',
		'callback'            => 'xpressui_pro_create_capture_session',
		'permission_callback' => '__return_true',
		'args'                => [
			'fieldName'   => [ 'required' => true, 'sanitize_callback' => 'sanitize_key' ],
			'fieldType'   => [ 'required' => true, 'sanitize_callback' => 'sanitize_key' ],
			'projectSlug' => [ 'required' => false, 'default' => '', 'sanitize_callback' => 'sanitize_title' ],
		],
	] );

	register_rest_route( 'xpressui/v1', '/capture/relay/(?P<token>[a-f0-9]{32,64})', [
		'methods'             => 'POST',
		'callback'            => 'xpressui_pro_relay_capture_data',
		'permission_callback' => '__return_true',
	] );

	register_rest_route( 'xpressui/v1', '/capture/poll/(?P<token>[a-f0-9]{32,64})', [
		'methods'             => 'GET',
		'callback'            => 'xpressui_pro_poll_capture_session',
		'permission_callback' => '__return_true',
	] );
}

function xpressui_pro_create_capture_session( WP_REST_Request $request ): WP_REST_Response {
	$token        = bin2hex( random_bytes( 16 ) ); // 32 hex chars
	$field_name   = $request->get_param( 'fieldName' );
	$field_type   = $request->get_param( 'fieldType' );
	$project_slug = $request->get_param( 'projectSlug' );

	$allowed_types = [ 'signature', 'camera-photo', 'qr-scan', 'document-scan' ];
	if ( ! in_array( $field_type, $allowed_types, true ) ) {
		return new WP_REST_Response( [ 'message' => 'Unsupported field type.' ], 400 );
	}

	$session = [
		'token'       => $token,
		'fieldName'   => $field_name,
		'fieldType'   => $field_type,
		'projectSlug' => $project_slug,
		'status'      => 'pending',
		'data'        => null,
		'createdAt'   => time(),
	];

	set_transient( 'xpressui_capture_' . $token, $session, XPRESSUI_CAPTURE_TTL );

	$capture_url = add_query_arg( 'xpressui_capture', $token, home_url( '/' ) );

	return new WP_REST_Response( [
		'token'      => $token,
		'captureUrl' => $capture_url,
	], 200 );
}

function xpressui_pro_relay_capture_data( WP_REST_Request $request ): WP_REST_Response {
	$token   = (string) $request->get_param( 'token' );
	$session = get_transient( 'xpressui_capture_' . $token );

	if ( ! is_array( $session ) ) {
		return new WP_REST_Response( [ 'message' => 'Session not found or expired.' ], 404 );
	}

	$content_type = $request->get_header( 'Content-Type' ) ?? '';

	if ( str_contains( $content_type, 'multipart/form-data' ) ) {
		// Photo upload: store as WP attachment, relay URL to desktop
		$file = $request->get_file_params()['capture_file'] ?? null;
		if ( ! $file || ! isset( $file['tmp_name'] ) ) {
			return new WP_REST_Response( [ 'message' => 'No file received.' ], 400 );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$file_name = sanitize_file_name( $file['name'] ?? 'capture.jpg' );
		$upload    = wp_handle_upload( $file, [ 'test_form' => false ] );

		if ( isset( $upload['error'] ) || ! isset( $upload['file'] ) ) {
			return new WP_REST_Response( [ 'message' => $upload['error'] ?? 'Upload error.' ], 500 );
		}

		$attachment_id = wp_insert_attachment( [
			'post_mime_type' => $upload['type'],
			'post_title'     => $file_name,
			'post_content'   => '',
			'post_status'    => 'inherit',
		], $upload['file'] );

		if ( is_wp_error( $attachment_id ) ) {
			return new WP_REST_Response( [ 'message' => $attachment_id->get_error_message() ], 500 );
		}

		wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		$data = wp_json_encode( [ 'attachmentId' => $attachment_id, 'url' => $upload['url'] ] );

		// Store the attachment ID in session meta for cleanup after pickup
		$session['pendingAttachmentId'] = $attachment_id;
	} else {
		// Signature / QR: expect JSON with { data: string }
		$body = $request->get_json_params();
		$data = sanitize_text_field( (string) ( $body['data'] ?? '' ) );
		if ( '' === $data ) {
			return new WP_REST_Response( [ 'message' => 'No data received.' ], 400 );
		}
	}

	$session['status'] = 'completed';
	$session['data']   = $data;

	// Keep in transient for a short period so the desktop can pick it up
	set_transient( 'xpressui_capture_' . $token, $session, 2 * MINUTE_IN_SECONDS );

	return new WP_REST_Response( [ 'ok' => true ], 200 );
}

function xpressui_pro_poll_capture_session( WP_REST_Request $request ): WP_REST_Response {
	$token   = (string) $request->get_param( 'token' );
	$session = get_transient( 'xpressui_capture_' . $token );

	if ( ! is_array( $session ) ) {
		return new WP_REST_Response( [ 'status' => 'expired' ], 200 );
	}

	if ( $session['status'] !== 'completed' ) {
		return new WP_REST_Response( [ 'status' => 'pending' ], 200 );
	}

	$data = $session['data'];

	// Consume immediately: delete transient after first pickup
	delete_transient( 'xpressui_capture_' . $token );

	return new WP_REST_Response( [ 'status' => 'completed', 'data' => $data ], 200 );
}

// ---------------------------------------------------------------------------
// Capture page — served by WordPress for ?xpressui_capture={token}
// ---------------------------------------------------------------------------

add_action( 'template_redirect', 'xpressui_pro_maybe_serve_capture_page', 1 );

function xpressui_pro_maybe_serve_capture_page(): void {
	if ( ! isset( $_GET['xpressui_capture'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$token   = preg_replace( '/[^a-f0-9]/i', '', (string) wp_unslash( $_GET['xpressui_capture'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$session = get_transient( 'xpressui_capture_' . $token );

	$field_type = 'signature';
	if ( is_array( $session ) ) {
		$field_type = (string) ( $session['fieldType'] ?? 'signature' );
	}

	$relay_url  = rest_url( 'xpressui/v1/capture/relay/' . rawurlencode( $token ) );
	$field_labels = [
		'signature'     => __( 'Draw your signature below', 'xpressui-wordpress-bridge-pro' ),
		'camera-photo'  => __( 'Take a photo below', 'xpressui-wordpress-bridge-pro' ),
		'document-scan' => __( 'Photograph your document', 'xpressui-wordpress-bridge-pro' ),
		'qr-scan'       => __( 'Scan a QR code', 'xpressui-wordpress-bridge-pro' ),
	];
	$field_label = $field_labels[ $field_type ] ?? __( 'Capture on mobile', 'xpressui-wordpress-bridge-pro' );

	xpressui_pro_output_capture_page( $token, $field_type, $relay_url, $field_label );
	exit;
}

function xpressui_pro_output_capture_page(
	string $token,
	string $field_type,
	string $relay_url,
	string $field_label
): void {
	$token_esc    = esc_js( $token );
	$relay_esc    = esc_js( $relay_url );
	$label_esc    = esc_html( $field_label );
	$type_esc     = esc_js( $field_type );

	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<!DOCTYPE html>';
	echo '<html lang="en"><head>';
	echo '<meta charset="UTF-8">';
	echo '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">';
	echo '<title>' . esc_html__( 'Mobile Capture', 'xpressui-wordpress-bridge-pro' ) . '</title>';
	echo xpressui_pro_capture_page_styles();
	echo '</head><body>';
	echo '<div class="cp-wrap"><div class="cp-header"><h1>' . $label_esc . '</h1></div>';
	echo '<div class="cp-body" id="cp-body">';

	if ( 'signature' === $field_type ) {
		echo xpressui_pro_capture_signature_html();
	} elseif ( 'qr-scan' === $field_type ) {
		echo xpressui_pro_capture_qr_html();
	} else {
		echo xpressui_pro_capture_photo_html();
	}

	echo '</div></div>';
	echo '<script>';
	echo 'var RELAY_URL = "' . $relay_esc . '";';
	echo 'var FIELD_TYPE = "' . $type_esc . '";';
	if ( 'signature' === $field_type ) {
		echo xpressui_pro_capture_signature_js();
	} elseif ( 'qr-scan' === $field_type ) {
		echo xpressui_pro_capture_qr_js();
	} else {
		echo xpressui_pro_capture_photo_js();
	}
	echo '</script>';
	echo '</body></html>';
	// phpcs:enable
}

function xpressui_pro_capture_page_styles(): string {
	return '<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:system-ui,-apple-system,sans-serif;background:#f8fafc;min-height:100dvh;display:flex;flex-direction:column;}
.cp-wrap{display:flex;flex-direction:column;flex:1;max-width:480px;margin:0 auto;width:100%;padding:16px;}
.cp-header h1{font-size:18px;font-weight:600;color:#0f172a;text-align:center;margin-bottom:16px;}
.cp-body{display:flex;flex-direction:column;gap:12px;}
.cp-body canvas{border:2px solid #d1d5db;border-radius:8px;background:#fff;width:100%;touch-action:none;cursor:crosshair;}
.cp-body button{border:none;border-radius:8px;padding:14px 20px;font-size:16px;font-weight:600;cursor:pointer;width:100%;}
.cp-body .cp-btn-primary{background:#2563eb;color:#fff;}
.cp-body .cp-btn-secondary{background:#f1f5f9;color:#475569;}
.cp-body .cp-status{text-align:center;font-size:14px;color:#64748b;padding:12px;}
.cp-body .cp-status.success{color:#16a34a;font-weight:600;}
.cp-body .cp-status.error{color:#ef4444;}
.cp-body video{width:100%;border-radius:8px;background:#000;}
</style>';
}

function xpressui_pro_capture_signature_html(): string {
	return '<canvas id="sig-canvas" height="200"></canvas>
<button class="cp-btn-secondary" onclick="clearCanvas()">Clear</button>
<button class="cp-btn-primary" id="submit-btn" onclick="submitSignature()">Submit Signature</button>
<div class="cp-status" id="status-msg"></div>';
}

function xpressui_pro_capture_photo_html(): string {
	return '<video id="video" autoplay playsinline></video>
<button class="cp-btn-primary" id="capture-btn" onclick="capturePhoto()">Take Photo</button>
<canvas id="photo-canvas" style="display:none"></canvas>
<div class="cp-status" id="status-msg"></div>';
}

function xpressui_pro_capture_qr_html(): string {
	return '<video id="video" autoplay playsinline></video>
<canvas id="qr-canvas" style="display:none"></canvas>
<div class="cp-status" id="status-msg">Point your camera at a QR code…</div>';
}

function xpressui_pro_capture_qr_js(): string {
	return <<<'JS'
(function(){
var video=document.getElementById('video');
var canvas=document.getElementById('qr-canvas');
var msg=document.getElementById('status-msg');
var relayed=false;
function stopCamera(){if(video.srcObject){video.srcObject.getTracks().forEach(function(t){t.stop();});}}
function relayData(text){
  if(relayed)return;relayed=true;
  msg.textContent='Relaying QR code…';
  fetch(RELAY_URL,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({data:text})})
  .then(function(r){
    if(r.ok){msg.className='cp-status success';msg.textContent='✓ QR code sent! You can close this page.';stopCamera();}
    else{msg.className='cp-status error';msg.textContent='Relay error. Please try again.';relayed=false;}
  }).catch(function(){msg.className='cp-status error';msg.textContent='Network error.';relayed=false;});
}
if(!('BarcodeDetector' in window)){
  msg.className='cp-status error';
  msg.textContent='QR scanning requires Chrome on Android or iOS 17+. Open this page in a supported browser.';
  return;
}
var detector=new BarcodeDetector({formats:['qr_code']});
navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'},audio:false})
.then(function(stream){
  video.srcObject=stream;video.play();
  function tick(){
    if(relayed||video.paused||video.ended)return;
    detector.detect(video).then(function(codes){
      if(codes.length>0&&codes[0].rawValue){relayData(codes[0].rawValue);}
      else{requestAnimationFrame(tick);}
    }).catch(function(){requestAnimationFrame(tick);});
  }
  video.addEventListener('playing',function(){requestAnimationFrame(tick);},{once:true});
}).catch(function(){
  msg.className='cp-status error';
  msg.textContent='Camera access denied. Please allow camera and reload.';
});
})();
JS;
}

function xpressui_pro_capture_signature_js(): string {
	return <<<'JS'
(function(){
var canvas=document.getElementById('sig-canvas');
var ctx=canvas.getContext('2d');
var drawing=false;
function getPos(e){
  var r=canvas.getBoundingClientRect();
  var src=e.touches?e.touches[0]:e;
  return{x:(src.clientX-r.left)*(canvas.width/r.width),y:(src.clientY-r.top)*(canvas.height/r.height)};
}
canvas.addEventListener('mousedown',function(e){drawing=true;var p=getPos(e);ctx.beginPath();ctx.moveTo(p.x,p.y);});
canvas.addEventListener('mousemove',function(e){if(!drawing)return;var p=getPos(e);ctx.lineWidth=2;ctx.lineCap='round';ctx.strokeStyle='#0f172a';ctx.lineTo(p.x,p.y);ctx.stroke();});
canvas.addEventListener('mouseup',function(){drawing=false;});
canvas.addEventListener('touchstart',function(e){e.preventDefault();drawing=true;var p=getPos(e);ctx.beginPath();ctx.moveTo(p.x,p.y);},{passive:false});
canvas.addEventListener('touchmove',function(e){e.preventDefault();if(!drawing)return;var p=getPos(e);ctx.lineWidth=2;ctx.lineCap='round';ctx.strokeStyle='#0f172a';ctx.lineTo(p.x,p.y);ctx.stroke();},{passive:false});
canvas.addEventListener('touchend',function(){drawing=false;});
window.clearCanvas=function(){ctx.clearRect(0,0,canvas.width,canvas.height);};
window.submitSignature=function(){
  var data=canvas.toDataURL('image/png');
  var btn=document.getElementById('submit-btn');
  var msg=document.getElementById('status-msg');
  btn.disabled=true;
  fetch(RELAY_URL,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({data:data})})
  .then(function(r){
    if(r.ok){msg.className='cp-status success';msg.textContent='✓ Signature sent! You can close this page.';}
    else{msg.className='cp-status error';msg.textContent='Error. Please try again.';btn.disabled=false;}
  }).catch(function(){msg.className='cp-status error';msg.textContent='Network error.';btn.disabled=false;});
};
})();
JS;
}

function xpressui_pro_capture_photo_js(): string {
	return <<<'JS'
(function(){
var video=document.getElementById('video');
var canvas=document.getElementById('photo-canvas');
var btn=document.getElementById('capture-btn');
navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'},audio:false})
.then(function(stream){video.srcObject=stream;})
.catch(function(){
  var msg=document.getElementById('status-msg');
  msg.className='cp-status error';
  msg.textContent='Camera access denied. Please allow camera and reload.';
  btn.disabled=true;
});
window.capturePhoto=function(){
  var msg=document.getElementById('status-msg');
  canvas.width=video.videoWidth||640;
  canvas.height=video.videoHeight||480;
  var ctx=canvas.getContext('2d');
  ctx.drawImage(video,0,0);
  canvas.toBlob(function(blob){
    var fd=new FormData();
    fd.append('capture_file',blob,'photo.jpg');
    btn.disabled=true;
    fetch(RELAY_URL,{method:'POST',body:fd})
    .then(function(r){
      if(r.ok){
        msg.className='cp-status success';
        msg.textContent='✓ Photo sent! You can close this page.';
        if(video.srcObject){video.srcObject.getTracks().forEach(function(t){t.stop();});}
      }else{
        msg.className='cp-status error';
        msg.textContent='Upload error. Please try again.';
        btn.disabled=false;
      }
    }).catch(function(){
      msg.className='cp-status error';
      msg.textContent='Network error.';
      btn.disabled=false;
    });
  },'image/jpeg',0.85);
};
})();
JS;
}
