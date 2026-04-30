<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if (!isset($xpressui_ctx) || !is_array($xpressui_ctx)) {
    throw new RuntimeException('Missing template context array.');
}
?><div class="template-field" data-template-zone="field" data-field-name="<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'name'))); ?>" data-field-type="<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'type'))); ?>">
  <div class="template-field-label-row">
    <label class="template-field-label" for="sig-canvas-<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'name'))); ?>">
      <span><?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'label'))); ?></span>
      <span class="template-required" aria-hidden="true"<?php if (xpressui_bridge_template_truthy((!xpressui_bridge_template_truthy(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'required'))))): ?> style="display:none"<?php endif; ?>>*</span>
    </label>
  </div>
  <div class="xpressui-signature-wrap" data-signature-field="<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'name'))); ?>" style="display:flex;flex-direction:column;gap:8px;">
    <canvas
      id="sig-canvas-<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'name'))); ?>"
      class="xpressui-signature-canvas"
      data-signature-canvas="<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'name'))); ?>"
      width="580"
      height="160"
      style="border:1px solid #d1d5db;border-radius:6px;background:#ffffff;cursor:crosshair;touch-action:none;width:100%;max-width:580px;display:block;"
    ></canvas>
    <div style="display:flex;align-items:center;gap:12px;">
      <button type="button" class="template-field-pill" data-signature-clear="<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'name'))); ?>">Clear</button>
      <span class="template-field-help xpressui-signature-hint" data-signature-hint="<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'name'))); ?>"><?php if (xpressui_bridge_template_truthy(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'placeholder'))): ?><?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'placeholder'))); ?><?php else: ?>Draw your signature above<?php endif; ?></span>
    </div>
  </div>
  <input
    id="sig-<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'name'))); ?>"
    type="hidden"
    class="template-input"
    name="<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'name'))); ?>"
    data-name="<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'name'))); ?>"
    data-label="<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'label'))); ?>"
    data-type="<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'type'))); ?>"
    data-section-name="<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'section'), 'name'))); ?>"
<?php if (xpressui_bridge_template_truthy(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'required'))): ?>
data-signature-required="true"<?php endif; ?>
    value=""
  />
<?php if (xpressui_bridge_template_truthy(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'desc'))): ?>
    <div class="template-field-help"><?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'desc'))); ?></div>
<?php endif; ?>
<?php xpressui_bridge_template_include_template('field-meta.php', $xpressui_ctx); ?>
  <script>
  (function(){
    var _fn = '<?php echo esc_attr(xpressui_bridge_template_stringify(xpressui_bridge_template_attr(xpressui_bridge_template_context_get($xpressui_ctx, 'field'), 'name'))); ?>';
    function _init(){
      var wrap=document.querySelector('[data-signature-field="'+_fn+'"]');
      if(!wrap||wrap.dataset.sigReady){return;}
      wrap.dataset.sigReady='1';
      var canvas=wrap.querySelector('[data-signature-canvas="'+_fn+'"]');
      var input=document.querySelector('input[data-name="'+_fn+'"]');
      var clearBtn=wrap.querySelector('[data-signature-clear="'+_fn+'"]');
      var hint=wrap.querySelector('[data-signature-hint="'+_fn+'"]');
      if(!canvas||!input){return;}
      var ctx=canvas.getContext('2d');
      var drawing=false;
      ctx.strokeStyle='#1a1a2e';ctx.lineWidth=2;ctx.lineCap='round';ctx.lineJoin='round';
      function pos(e){var r=canvas.getBoundingClientRect();var s=e.touches?e.touches[0]:e;return{x:(s.clientX-r.left)*(canvas.width/r.width),y:(s.clientY-r.top)*(canvas.height/r.height)};}
      function start(e){e.preventDefault();drawing=true;var p=pos(e);ctx.beginPath();ctx.moveTo(p.x,p.y);}
      function move(e){if(!drawing){return;}e.preventDefault();var p=pos(e);ctx.lineTo(p.x,p.y);ctx.stroke();if(hint){hint.style.display='none';}input.value=canvas.toDataURL('image/png');}
      function end(){drawing=false;}
      canvas.addEventListener('mousedown',start);
      canvas.addEventListener('mousemove',move);
      canvas.addEventListener('mouseup',end);
      canvas.addEventListener('mouseleave',end);
      canvas.addEventListener('touchstart',start,{passive:false});
      canvas.addEventListener('touchmove',move,{passive:false});
      canvas.addEventListener('touchend',end);
      if(clearBtn){clearBtn.addEventListener('click',function(){ctx.clearRect(0,0,canvas.width,canvas.height);input.value='';if(hint){hint.style.display='';}});}
    }
    if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',_init);}else{_init();}
  })();
  </script>
</div>
