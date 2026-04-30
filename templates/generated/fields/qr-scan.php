<?php
/**
 * Field type: qr-scan
 *
 * @status  beta
 * @scope   v1-unsupported
 * @reason  Camera API — device/browser-dependent (fragile on iOS Safari, Android WebView).
 *          Not covered by v1 QA baseline. Not promoted in v1 sales materials.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Generated from export/_partials/fields/qr-scan.j2. Do not edit manually.
if (!isset($xpressui_ctx) || !is_array($xpressui_ctx)) {
    throw new RuntimeException('Missing template context array.');
}

$_qr_field   = xpressui_bridge_template_context_get($xpressui_ctx, 'field');
$_qr_section = xpressui_bridge_template_context_get($xpressui_ctx, 'section');
$_qr_fn      = xpressui_bridge_template_stringify(xpressui_bridge_template_attr($_qr_field, 'name'));

?><div class="template-field" data-template-zone="field" data-field-name="<?php echo esc_attr($_qr_fn); ?>" data-field-type="<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr($_qr_field, 'type'))); ?>">
  <div class="template-field-label-row">
    <div class="template-field-label"><?php echo esc_html(xpressui_bridge_template_stringify(xpressui_bridge_template_attr($_qr_field, 'label'))); ?></div>
    <div class="template-field-meta-inline">
      <span class="template-required"<?php if (!xpressui_bridge_template_truthy(xpressui_bridge_template_attr($_qr_field, 'required'))): ?> style="display:none"<?php endif; ?>>*</span>
    </div>
  </div>
  <div class="template-upload-box" data-file-drop-zone="<?php echo esc_attr($_qr_fn); ?>" data-file-drag-active="false">
    <span class="template-upload-icon">&#128439;</span>
    <div class="template-field-label">Scan QR code</div>
    <div class="template-field-help">
      <?php if (xpressui_bridge_template_truthy(xpressui_bridge_template_attr($_qr_field, 'placeholder'))): ?>
        <?php echo esc_html(xpressui_bridge_template_stringify(xpressui_bridge_template_attr($_qr_field, 'placeholder'))); ?>
      <?php else: ?>
        Use the camera or upload an image containing a QR code.
      <?php endif; ?>
    </div>
    <?php if (xpressui_bridge_template_truthy(xpressui_bridge_template_attr($_qr_field, 'upload_accept_label'))): ?>
      <div class="template-upload-pills">
        <span class="template-field-pill"><?php echo esc_html(xpressui_bridge_template_stringify(xpressui_bridge_template_attr($_qr_field, 'upload_accept_label'))); ?></span>
      </div>
    <?php endif; ?>
    <input
      id="<?php echo esc_attr($_qr_fn); ?>"
      class="template-input"
      type="<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr($_qr_field, 'input_type'))); ?>"
      name="<?php echo esc_attr($_qr_fn); ?>"
      data-name="<?php echo esc_attr($_qr_fn); ?>"
      data-label="<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr($_qr_field, 'label'))); ?>"
      data-type="<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr($_qr_field, 'type'))); ?>"
      data-section-name="<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr($_qr_section, 'name'))); ?>"
      <?php if (xpressui_bridge_template_truthy(xpressui_bridge_template_attr($_qr_field, 'accept'))): ?>accept="<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr($_qr_field, 'accept'))); ?>"<?php endif; ?>
      <?php if (xpressui_bridge_template_truthy(xpressui_bridge_template_attr($_qr_field, 'capture'))): ?>capture="<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr($_qr_field, 'capture'))); ?>"<?php endif; ?>
      <?php if (xpressui_bridge_template_truthy(xpressui_bridge_template_attr($_qr_field, 'required'))): ?>required aria-required="true"<?php endif; ?>
    />
  </div>
  <div
    id="<?php echo esc_attr($_qr_fn); ?>_selection"
    class="template-upload-selection"
    data-upload-selection-zone="<?php echo esc_attr($_qr_fn); ?>"
  >
    <div class="template-upload-selection-row">
      <span class="template-upload-selection-title" data-upload-selection-title="<?php echo esc_attr($_qr_fn); ?>">Awaiting QR scan</span>
      <span class="template-field-pill" data-upload-selection-kind="<?php echo esc_attr($_qr_fn); ?>">QR scan</span>
    </div>
    <div class="template-field-help" data-upload-selection-message="<?php echo esc_attr($_qr_fn); ?>">
      Use the camera or upload an image containing a QR code.
    </div>
    <div data-upload-selection-body="<?php echo esc_attr($_qr_fn); ?>">
      <div class="template-field-message" data-qr-result="<?php echo esc_attr($_qr_fn); ?>" hidden></div>
      <div class="template-upload-selection-row" data-qr-controls="<?php echo esc_attr($_qr_fn); ?>">
        <button type="button" class="template-field-pill" data-qr-action="start">Start Camera</button>
        <button type="button" class="template-field-pill" data-qr-action="scan" hidden>Scan Now</button>
        <button type="button" class="template-field-pill" data-qr-action="stop" hidden>Stop</button>
      </div>
      <video class="xpui-qr-video" data-qr-video="<?php echo esc_attr($_qr_fn); ?>" playsinline muted hidden></video>
      <div class="template-field-help" data-qr-status-message="<?php echo esc_attr($_qr_fn); ?>" hidden></div>
      <div class="template-field-help" data-qr-hint="<?php echo esc_attr($_qr_fn); ?>">You can also upload or capture an image with the file picker.</div>
    </div>
  </div>
  <?php if (xpressui_bridge_template_truthy(xpressui_bridge_template_attr($_qr_field, 'desc'))): ?>
    <div class="template-field-help"><?php echo esc_html(xpressui_bridge_template_stringify(xpressui_bridge_template_attr($_qr_field, 'desc'))); ?></div>
  <?php endif; ?>
  <?php xpressui_bridge_template_include_template('field-meta.php', $xpressui_ctx); ?>
  <button type="button" class="template-field-pill" data-mobile-capture-btn="<?php echo esc_attr($_qr_fn); ?>" hidden>📱 Capture on mobile</button>
</div>
