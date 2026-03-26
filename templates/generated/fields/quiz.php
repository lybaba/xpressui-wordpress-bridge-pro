<?php
// Generated from export/_partials/fields/quiz.j2. Do not edit manually.
if (!isset($__ctx) || !is_array($__ctx)) {
    throw new RuntimeException('Missing template context array.');
}
?><?php $__ctx['choice_layout'] = (xui_jinja_truthy(xui_jinja_in(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'layout'), ["horizontal", "vertical"])) ? xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'layout') : "auto"); ?><div class="template-field" data-template-zone="field" data-field-name="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>" data-field-type="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'type')); ?>">
  <div class="template-field-label-row">
    <div class="template-field-label"><?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'label')); ?></div>
<?php if (xui_jinja_truthy(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'required'))): ?>      <span class="template-required">*</span>
<?php endif; ?>  </div>

<?php if (xui_jinja_truthy(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'choices'))): ?>    <input type="hidden" id="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>" name="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>" data-name="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>" data-label="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'label')); ?>" data-type="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'type')); ?>" data-section-name="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'section'), 'name')); ?>" />
    <div class="template-quiz-wrap">
      <div id="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>_selection" data-quiz-zone="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>">
        <div class="template-choice-grid template-choice-grid--<?php echo xui_jinja_escape(xui_jinja_context_get($__ctx, 'choice_layout')); ?>" data-quiz-catalog="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>" data-choice-layout="<?php echo xui_jinja_escape(xui_jinja_context_get($__ctx, 'choice_layout')); ?>">
<?php $__loop_parent_ctx_2 = $__ctx; $__loop_items_1 = xui_jinja_iterable(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'choices')); foreach ($__loop_items_1 as $__loop_index_3 => $__loop_value_4): $__ctx = $__loop_parent_ctx_2; $__ctx['choice'] = $__loop_value_4; $__ctx['loop'] = ['index' => $__loop_index_3 + 1, 'index0' => $__loop_index_3, 'first' => $__loop_index_3 === 0, 'last' => ($__loop_index_3 + 1) === count($__loop_items_1)]; ?><?php $__ctx['choice_id'] = xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'id'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'value')); ?><?php $__ctx['choice_title'] = xui_jinja_or(xui_jinja_or(xui_jinja_or(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'label'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'title')), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'name')), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'value')), 'Choice ' . xui_jinja_stringify(xui_jinja_context_get($__ctx, 'loop')['index'])); ?><?php $__ctx['choice_has_media'] = xui_jinja_or(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'image_url'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'image_medium')), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'image_thumbnail')); ?><?php $__ctx['choice_desc'] = xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'desc'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'helpText')); ?>            <article class="template-choice-card" data-quiz-answer-card="<?php echo xui_jinja_escape(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'id'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'value'))); ?>" data-quiz-answer-action="toggle" data-quiz-answer-id="<?php echo xui_jinja_escape(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'id'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'value'))); ?>">
<?php if (xui_jinja_truthy(xui_jinja_context_get($__ctx, 'choice_has_media'))): ?>                <div class="template-choice-media">
                  <img src="<?php echo xui_jinja_escape(xui_jinja_or(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'image_url'), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'image_medium')), xui_jinja_attr(xui_jinja_context_get($__ctx, 'choice'), 'image_thumbnail'))); ?>" alt="<?php echo xui_jinja_escape(xui_jinja_context_get($__ctx, 'choice_title')); ?>" loading="lazy" data-quiz-answer-image="<?php echo xui_jinja_escape(xui_jinja_context_get($__ctx, 'choice_id')); ?>" />
                </div>
<?php endif; ?>              <div class="template-choice-title" data-quiz-answer-title="<?php echo xui_jinja_escape(xui_jinja_context_get($__ctx, 'choice_id')); ?>"><?php echo xui_jinja_escape(xui_jinja_context_get($__ctx, 'choice_title')); ?></div>
<?php if (xui_jinja_truthy(xui_jinja_context_get($__ctx, 'choice_desc'))): ?>                <div class="template-field-help" data-quiz-answer-desc="<?php echo xui_jinja_escape(xui_jinja_context_get($__ctx, 'choice_id')); ?>"><?php echo xui_jinja_escape(xui_jinja_context_get($__ctx, 'choice_desc')); ?></div>
<?php endif; ?>              <div class="template-gallery-caption" data-quiz-answer-state="<?php echo xui_jinja_escape(xui_jinja_context_get($__ctx, 'choice_id')); ?>">
                <span data-quiz-mode="<?php echo xui_jinja_escape(xui_jinja_context_get($__ctx, 'choice_id')); ?>" hidden></span>
                <span data-quiz-selected-state="<?php echo xui_jinja_escape(xui_jinja_context_get($__ctx, 'choice_id')); ?>" hidden></span>
              </div>
            </article>
<?php endforeach; $__ctx = $__loop_parent_ctx_2; ?>        </div>
      </div>
    </div>
<?php else: ?>    <div class="template-quiz-wrap">
      <input
        type="hidden"
        id="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>"
        name="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>"
        data-name="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>"
        data-label="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'label')); ?>"
        data-type="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'type')); ?>"
        data-section-name="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'section'), 'name')); ?>"
      />
      <div
        id="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>_selection"
        data-quiz-zone="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>"
      >
        <div data-quiz-open-wrapper="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>">
          <textarea
            class="template-textarea"
            placeholder="<?php echo xui_jinja_escape(xui_jinja_or(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'placeholder'), __("Write your answer", 'xpressui-bridge'))); ?>"
<?php if (xui_jinja_truthy(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'required'))): ?>required aria-required="true"<?php endif; ?>            data-quiz-open-answer="<?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'name')); ?>"
          ></textarea>
        </div>
      </div>
    </div>
<?php endif; ?>
<?php if (xui_jinja_truthy(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'desc'))): ?>    <div class="template-field-help"><?php echo xui_jinja_escape(xui_jinja_attr(xui_jinja_context_get($__ctx, 'field'), 'desc')); ?></div>
<?php endif; ?><?php xui_jinja_include('field-meta.php', $__ctx); ?></div>
