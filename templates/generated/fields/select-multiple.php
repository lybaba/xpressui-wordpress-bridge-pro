<?php
// PRO field — server-side shell rendered by XPressUI Bridge PRO.
// The PRO runtime hydrates this element into a fully interactive field.
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
  <input
    type="hidden"
    id="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>"
    name="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>"
    data-name="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>"
    data-label="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'label')); ?>"
    data-type="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'type')); ?>"
    data-section-name="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'section'), 'name')); ?>"
  />
  <div id="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>_selection" data-pro-field-shell="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'type')); ?>"></div>
<?php if (xui_jinja_truthy(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'desc'))): ?>    <div class="template-field-help"><?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'desc')); ?></div>
<?php endif; ?><?php xui_jinja_include('field-meta.php', $__ctx); ?></div>
