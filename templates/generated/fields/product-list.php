<?php
// Generated from export/_partials/fields/product-list.j2. Do not edit manually.
if (!isset($__ctx) || !is_array($__ctx)) {
    throw new RuntimeException('Missing template context array.');
}
?><div class="template-field" data-template-zone="field" data-field-name="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>" data-field-type="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'type')); ?>">
  <div class="template-field-label-row">
    <div class="template-field-label"><?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'label')); ?></div>
    <div class="template-field-meta-inline">
<?php if (xui_jinja_truthy(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'required'))): ?>        <span class="template-required">*</span>
<?php endif; ?>      <span class="template-field-pill" data-product-list-total="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>" style="display:none;">
        <span data-product-list-total-icon="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>" aria-hidden="true">&#128722;</span>
        <span data-product-list-total-amount="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>"></span>
      </span>
    </div>
  </div>
  <input type="hidden" id="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>" name="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>" data-name="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>" data-label="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'label')); ?>" data-type="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'type')); ?>" data-section-name="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'section'), 'name')); ?>"/>
  <div id="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>_selection" class="template-product-grid" data-product-list-zone="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>">
<?php $__loop_parent_ctx_2 = $__ctx; $__loop_items_1 = xui_jinja_iterable(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'choices')); foreach ($__loop_items_1 as $__loop_index_3 => $__loop_value_4): $__ctx = $__loop_parent_ctx_2; $__ctx['choice'] = $__loop_value_4; $__ctx['loop'] = ['index' => $__loop_index_3 + 1, 'index0' => $__loop_index_3, 'first' => $__loop_index_3 === 0, 'last' => ($__loop_index_3 + 1) === count($__loop_items_1)]; ?><?php $__ctx['choice_title'] = xui_jinja_or(xui_jinja_or(xui_jinja_or(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'label'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'title')), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'name')), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'value')), 'Item ' . xui_jinja_stringify(xui_jinja_context_get($__ctx, 'loop')['index'])); ?>      <article class="template-product-card" data-product-card="<?php echo xui_jinja_escape(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'id'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'value'))); ?>">
        <div class="template-product-media" data-product-media="<?php echo xui_jinja_escape(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'id'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'value'))); ?>" data-product-open-gallery="<?php echo xui_jinja_escape(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'id'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'value'))); ?>">
<?php if (xui_jinja_truthy(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'image_medium'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'image_thumbnail')))): ?>            <img src="<?php echo xui_jinja_escape(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'image_medium'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'image_thumbnail'))); ?>" alt="<?php echo xui_jinja_escape(xui_jinja_context_get($__ctx, 'choice_title')); ?>" loading="lazy" data-product-image="<?php echo xui_jinja_escape(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'id'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'value'))); ?>" />
<?php else: ?>            <span class="template-field-help"><?php echo xui_jinja_escape(__(\"No image\", 'xpressui-bridge')); ?></span>
<?php endif; ?>        </div>
        <div class="template-product-title" data-product-title="<?php echo xui_jinja_escape(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'id'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'value'))); ?>" data-product-open-gallery="<?php echo xui_jinja_escape(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'id'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'value'))); ?>"><?php echo xui_jinja_escape(xui_jinja_context_get($__ctx, 'choice_title')); ?></div>
        <div class="template-product-meta" data-product-pricing="<?php echo xui_jinja_escape(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'id'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'value'))); ?>">
          <span class="template-product-price" data-product-price="<?php echo xui_jinja_escape(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'id'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'value'))); ?>">
<?php if (!xui_jinja_test_none(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'discount_price'))): ?>              <?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'discount_price')); ?>€
<?php elseif (!xui_jinja_test_none(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'sale_price'))): ?>              <?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'sale_price')); ?>€
<?php else: ?>              <?php echo xui_jinja_escape(__(\"Price on request\", 'xpressui-bridge')); ?>
<?php endif; ?>          </span>
        </div>
        <div class="template-product-actions" data-product-controls="<?php echo xui_jinja_escape(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'id'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'value'))); ?>" data-product-control-row="<?php echo xui_jinja_escape(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'id'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'value'))); ?>"></div>
      </article>
<?php endforeach; $__ctx = $__loop_parent_ctx_2; ?>  </div>

<?php if (xui_jinja_truthy(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'desc'))): ?>    <div class="template-field-help"><?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'desc')); ?></div>
<?php endif; ?><?php xui_jinja_include('field-meta.php', $__ctx); ?></div>
