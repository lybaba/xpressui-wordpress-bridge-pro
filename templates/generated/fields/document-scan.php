<?php
// Generated from export/_partials/fields/document-scan.j2. Do not edit manually.
if (!isset($__ctx) || !is_array($__ctx)) {
    throw new RuntimeException('Missing template context array.');
}
?><div class="template-field" data-template-zone="field" data-field-name="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>" data-field-type="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'type')); ?>">
  <div class="template-field-label-row">
    <div class="template-field-label"><?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'label')); ?></div>
    <div class="template-field-meta-inline">
<?php if (xui_jinja_truthy(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'required'))): ?>        <span class="template-required">*</span>
<?php endif; ?>    </div>
  </div>
  <div class="template-upload-box" data-file-drop-zone="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>" data-file-drag-active="false">
    <span class="template-upload-icon">&#128196;</span>
    <div class="template-field-label"><?php echo xui_jinja_escape(__("Scan document", 'xpressui-bridge')); ?></div>
    <div class="template-field-help">
<?php if (xui_jinja_truthy(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'placeholder'))): ?>        <?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'placeholder')); ?>
<?php else: ?>        <?php echo xui_jinja_escape(__("Capture or upload the front and back of your document.", 'xpressui-bridge')); ?>
<?php endif; ?>    </div>
<?php if (xui_jinja_truthy(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'upload_accept_label'))): ?>      <div class="template-upload-pills">
        <span class="template-field-pill"><?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'upload_accept_label')); ?></span>
      </div>
<?php endif; ?>    <input
      id="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>"
      class="template-input"
      type="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'input_type')); ?>"
      name="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>"
      data-name="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>"
      data-label="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'label')); ?>"
      data-type="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'type')); ?>"
      data-section-name="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'section'), 'name')); ?>"
<?php if (xui_jinja_truthy(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'accept'))): ?>accept="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'accept')); ?>"<?php endif; ?><?php if (xui_jinja_truthy(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'capture'))): ?>capture="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'capture')); ?>"<?php endif; ?><?php if (xui_jinja_truthy(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'required'))): ?>required aria-required="true"<?php endif; ?><?php if (xui_jinja_truthy(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'document_scan_mode'))): ?>data-document-scan-mode="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'document_scan_mode')); ?>"<?php endif; ?>      style="display:none;"
    />
  </div>
  <div
    id="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>_selection"
    class="template-upload-selection"
    data-upload-selection-zone="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>"
  >
    <div class="template-upload-selection-row">
      <span class="template-upload-selection-title" data-upload-selection-title="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>"><?php echo xui_jinja_escape(__("Awaiting document scan", 'xpressui-bridge')); ?></span>
      <span class="template-field-pill" data-upload-selection-kind="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>"><?php echo xui_jinja_escape(__("Document scan", 'xpressui-bridge')); ?></span>
    </div>
    <div class="template-field-help" data-upload-selection-message="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>">
      <?php echo xui_jinja_escape(__("Capture or upload the front and back of your document.", 'xpressui-bridge')); ?>
    </div>
    <div data-upload-selection-body="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>">
      <div class="template-upload-selection-row" data-document-scan-controls="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>">
<?php $__loop_parent_ctx_2 = $__ctx; $__loop_items_1 = xui_jinja_iterable(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'document_scan_slots')); foreach ($__loop_items_1 as $__loop_index_3 => $__loop_value_4): $__ctx = $__loop_parent_ctx_2; $__ctx['slot'] = $__loop_value_4; $__ctx['loop'] = ['index' => $__loop_index_3 + 1, 'index0' => $__loop_index_3, 'first' => $__loop_index_3 === 0, 'last' => ($__loop_index_3 + 1) === count($__loop_items_1)]; ?>          <button
            type="button"
            class="template-field-pill"
            data-document-scan-control="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>:<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'slot'), 'key')); ?>"
            data-document-scan-slot="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'slot'), 'index')); ?>"
          ><?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'slot'), 'control_label')); ?></button>
<?php endforeach; $__ctx = $__loop_parent_ctx_2; ?>      </div>
      <div class="template-choice-grid" data-document-scan-grid="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>">
<?php $__loop_parent_ctx_6 = $__ctx; $__loop_items_5 = xui_jinja_iterable(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'document_scan_slots')); foreach ($__loop_items_5 as $__loop_index_7 => $__loop_value_8): $__ctx = $__loop_parent_ctx_6; $__ctx['slot'] = $__loop_value_8; $__ctx['loop'] = ['index' => $__loop_index_7 + 1, 'index0' => $__loop_index_7, 'first' => $__loop_index_7 === 0, 'last' => ($__loop_index_7 + 1) === count($__loop_items_5)]; ?>          <div
            class="template-choice-card"
            data-document-scan-slot-card="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>:<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'slot'), 'index')); ?>"
            data-document-scan-slot-key="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'slot'), 'key')); ?>"
          >
            <div class="template-choice-title" data-document-scan-slot-title="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>:<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'slot'), 'key')); ?>"><?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'slot'), 'label')); ?></div>
            <div class="template-choice-media" data-document-scan-preview="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>:<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'slot'), 'index')); ?>"></div>
            <div class="template-field-help" data-document-scan-slot-status="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>:<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'slot'), 'key')); ?>"></div>
<?php if (xui_jinja_truthy(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'enable_document_ocr'))): ?>              <div class="template-field-help" data-document-scan-ocr-status="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>:<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'slot'), 'key')); ?>"></div>
<?php endif; ?><?php if (xui_jinja_truthy(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'document_mrz_target_field'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'require_valid_document_mrz')))): ?>              <div class="template-field-help" data-document-scan-mrz-status="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>:<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'slot'), 'key')); ?>"></div>
<?php endif; ?>          </div>
<?php endforeach; $__ctx = $__loop_parent_ctx_6; ?>      </div>
      <div class="template-field-help" data-document-scan-helper="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>"></div>
<?php if (xui_jinja_truthy(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'enable_document_ocr'), xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'document_mrz_target_field'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'require_valid_document_mrz'))))): ?>        <div class="template-upload-selection-row" data-document-scan-insights="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>">
<?php if (xui_jinja_truthy(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'enable_document_ocr'))): ?>            <span class="template-field-pill" data-document-scan-ocr-target="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>">OCR target: <?php echo xui_jinja_escape(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'document_text_target_field'), 'runtime-only document payload')); ?></span>
<?php endif; ?><?php if (xui_jinja_truthy(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'document_mrz_target_field'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'require_valid_document_mrz')))): ?>            <span class="template-field-pill" data-document-scan-mrz-target="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>">MRZ target: <?php echo xui_jinja_escape(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'document_mrz_target_field'), 'runtime-only document payload')); ?></span>
<?php endif; ?>        </div>
<?php endif; ?>    </div>
  </div>
<?php if (xui_jinja_truthy(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'desc'))): ?>    <div class="template-field-help"><?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'desc')); ?></div>
<?php endif; ?><?php xui_jinja_include('field-meta.php', $__ctx); ?></div>
