<?php
if (!defined('ABSPATH')) { exit; }

$questions = get_option('aipc_skin_test_questions', '');
$mapping = get_option('aipc_skin_test_mapping', '');

// License gating: allow read-only view without write if no valid license/tier
$license_allowed = true;
$upgrade_url = '';
if (class_exists('AIPC_License')) {
    $lic = AIPC_License::getInstance();
    $license_allowed = ($lic->is_active() && $lic->has_feature('custom_skin_test'));
    $upgrade_url = $lic->generate_upgrade_url('business');
}

if (isset($_POST['aipc_skin_test_save']) && wp_verify_nonce($_POST['aipc_settings_nonce'] ?? '', 'aipc_save_settings')) {
    if (!$license_allowed) {
        echo '<div class="notice notice-error"><p>' . __('Opslaan niet toegestaan: Business of Enterprise licentie vereist.', 'ai-product-chatbot') . '</p></div>';
    } else {
        if (isset($_POST['aipc_skin_test_questions'])) {
            update_option('aipc_skin_test_questions', is_string($_POST['aipc_skin_test_questions']) ? wp_unslash($_POST['aipc_skin_test_questions']) : '');
        }
        if (isset($_POST['aipc_skin_test_mapping'])) {
            update_option('aipc_skin_test_mapping', is_string($_POST['aipc_skin_test_mapping']) ? wp_unslash($_POST['aipc_skin_test_mapping']) : '');
        }
        echo '<div class="notice notice-success"><p>' . __('Huidtest opgeslagen', 'ai-product-chatbot') . '</p></div>';
        $questions = get_option('aipc_skin_test_questions', '');
        $mapping = get_option('aipc_skin_test_mapping', '');
    }
}
?>
<div class="wrap">
  <h1><?php _e('Product Quiz - JSON Editor', 'ai-product-chatbot'); ?></h1>
  
  <!-- Visual Builder Notice -->
  <div class="notice notice-info" style="border-left-color: #2271b1;">
    <p>
      <span class="dashicons dashicons-info" style="margin-right: 5px;"></span>
      <strong>💡 Let op:</strong> Dit is een <strong>read-only weergave</strong> van je quiz data, automatisch gegenereerd door de Visual Builder.
      <br><br>
      Voor wijzigingen gebruik je nu de nieuwe: 
      <a href="admin.php?page=aipc-quiz-builder" class="button button-primary" style="margin-left: 10px;">
        <span class="dashicons dashicons-admin-customizer" style="margin-right: 3px;"></span>
        Visual Quiz Builder ✨
      </a>
    </p>
  </div>
  
  <?php if (!$license_allowed): ?>
    <div class="notice notice-warning"><p>
      <?php _e('Lezen toegestaan. Bewerken vereist Business of Enterprise licentie.', 'ai-product-chatbot'); ?>
      <?php if (!empty($upgrade_url)): ?>
        <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-secondary" style="margin-left:10px;">
          <?php _e('Upgrade naar Business', 'ai-product-chatbot'); ?>
        </a>
      <?php endif; ?>
    </p></div>
  <?php endif; ?>
  <form method="post" action="">
    <?php wp_nonce_field('aipc_save_settings', 'aipc_settings_nonce'); ?>

    <h2><?php _e('Vragen (JSON)', 'ai-product-chatbot'); ?> <span style="color: #d63638; font-size: 12px; font-weight: normal;">[READ-ONLY]</span></h2>
    <textarea id="aipc_skin_test_questions2" name="aipc_skin_test_questions" rows="14" style="width:100%; background: #f9f9f9; cursor: not-allowed;" readonly><?php echo esc_textarea($questions); ?></textarea>
    <p class="description"><?php _e('Deze data wordt automatisch gegenereerd door de Visual Quiz Builder', 'ai-product-chatbot'); ?></p>

    <h2 style="margin-top:24px;"><?php _e('Product Mappings (JSON)', 'ai-product-chatbot'); ?> <span style="color: #d63638; font-size: 12px; font-weight: normal;">[READ-ONLY]</span></h2>
    <textarea id="aipc_skin_test_mapping2" name="aipc_skin_test_mapping" rows="10" style="width:100%; background: #f9f9f9; cursor: not-allowed;" readonly><?php echo esc_textarea($mapping); ?></textarea>
    <p class="description"><?php _e('Deze mappings worden automatisch gegenereerd door de Visual Quiz Builder', 'ai-product-chatbot'); ?></p>

  <p>
    <a href="admin.php?page=aipc-quiz-builder" class="button button-primary">
      <span class="dashicons dashicons-admin-customizer" style="margin-right: 3px;"></span>
      <?php _e('Bewerken in Visual Builder', 'ai-product-chatbot'); ?>
    </a>
    <button type="button" class="button button-secondary" id="aipc-copy-json" style="margin-left: 10px;">
      <span class="dashicons dashicons-admin-page" style="margin-right: 3px;"></span>
      <?php _e('Kopieer JSON Data', 'ai-product-chatbot'); ?>
    </button>
  </p>
  </form>

  <div class="aipc-skin-tools">
    <h2><?php _e('Tools', 'ai-product-chatbot'); ?></h2>
    <button type="button" class="button" id="aipc-validate-json"><?php _e('Valideer JSON', 'ai-product-chatbot'); ?></button>
    <button type="button" class="button" id="aipc-test-mapping"><?php _e('Test mapping met voorbeeld', 'ai-product-chatbot'); ?></button>
    <div id="aipc-skin-test-result" style="margin-top:10px;"></div>
  </div>
</div>

<script>
jQuery(function($){
  function validateJson(txt){ try { JSON.parse(txt); return {ok:true}; } catch(e){ return {ok:false, err:e.message}; } }
  
  // Validation functionality
  $('#aipc-validate-json').on('click', function(){
    const q = $('#aipc_skin_test_questions2').val()||'';
    const m = $('#aipc_skin_test_mapping2').val()||'';
    const vq = validateJson(q), vm = validateJson(m);
    let html = '';
    html += '<div class="notice '+(vq.ok?'notice-success':'notice-error')+' inline"><p>Vragen: '+(vq.ok?'OK':'FOUT: '+vq.err)+'</p></div>';
    html += '<div class="notice '+(vm.ok?'notice-success':'notice-error')+' inline"><p>Mapping: '+(vm.ok?'OK':'FOUT: '+vm.err)+'</p></div>';
    $('#aipc-skin-test-result').html(html);
  });
  
  // Test mapping functionality
  $('#aipc-test-mapping').on('click', function(){
    const q = $('#aipc_skin_test_questions2').val()||'[]';
    const m = $('#aipc_skin_test_mapping2').val()||'[]';
    let steps = [], rules = [];
    try { steps = JSON.parse(q)||[]; } catch(e){}
    try { rules = JSON.parse(m)||[]; } catch(e){}
    // Neem de eerste zichtbare opties en simuleer antwoorden
    const answers = {};
    steps.forEach(function(s){ if (s && s.key && Array.isArray(s.options) && s.options[0]) { answers[s.key] = s.options[0]; }});
    // Match rule
    let matched = null;
    rules.some(function(r){
      if (!r || !r.if) return false;
      for (const k in r.if){ if (!answers[k] || (answers[k]+'').toLowerCase().indexOf((r.if[k]+'').toLowerCase()) === -1) return false; }
      matched = r; return true;
    });
    if (matched){
      $('#aipc-skin-test-result').html('<div class="notice notice-success inline"><p><?php echo esc_js(__('Match:', 'ai-product-chatbot')); ?> '+ $('<div>').text(matched.label||'-').html() +'</p></div>');
    } else {
      $('#aipc-skin-test-result').html('<div class="notice notice-warning inline"><p><?php echo esc_js(__('Geen match met voorbeeld.', 'ai-product-chatbot')); ?></p></div>');
    }
  });
  
  // Copy JSON data functionality
  $('#aipc-copy-json').on('click', function(){
    const questions = $('#aipc_skin_test_questions2').val() || '[]';
    const mapping = $('#aipc_skin_test_mapping2').val() || '[]';
    
    const combinedData = {
      questions: JSON.parse(questions),
      mapping: JSON.parse(mapping),
      exported_at: new Date().toISOString(),
      export_source: 'json_editor_readonly'
    };
    
    const jsonString = JSON.stringify(combinedData, null, 2);
    
    if (navigator.clipboard) {
      navigator.clipboard.writeText(jsonString).then(function(){
        alert('<?php echo esc_js(__('Quiz JSON data gekopieerd naar klembord!', 'ai-product-chatbot')); ?>');
      }).catch(function(){
        // Fallback: show in alert
        prompt('<?php echo esc_js(__('Kopieer deze JSON data:', 'ai-product-chatbot')); ?>', jsonString);
      });
    } else {
      // Fallback for older browsers
      prompt('<?php echo esc_js(__('Kopieer deze JSON data:', 'ai-product-chatbot')); ?>', jsonString);
    }
  });
});
</script>

<style>
.aipc-skin-tools{margin-top:20px;background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:16px}
</style>

