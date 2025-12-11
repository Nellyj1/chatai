<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Check license status
$has_license = false;
$current_tier = 'none';
$has_basic = false;
$has_business = false;

if (class_exists('AIPC_License')) {
    $license = AIPC_License::getInstance();
    $has_license = $license->is_active();
    $current_tier = $has_license ? $license->get_current_tier() : 'none';
    $has_basic = $has_license;
    $has_business = in_array($current_tier, ['business', 'enterprise']);
}

$product_manager = new AIPC_Product_Manager();
$document_manager = new AIPC_Document_Manager();

$products = [];
$product_count = 0;
// Prefer WooCommerce live count
$woo_active = class_exists('WooCommerce');
if ($woo_active) {
    $counts = wp_count_posts('product');
    if ($counts && isset($counts->publish)) {
        $product_count = intval($counts->publish);
    }
} else {
    $products_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}aipc_products'");
    if ($products_table_exists) {
        $products = $product_manager->get_all_products();
        $product_count = count($products);
    }
}

// Get ingredients count from database
$ingredient_count = 0;
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}aipc_ingredients'");
if ($table_exists) {
    $ingredient_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aipc_ingredients WHERE status = 'active'");
}

$documents = [];
$document_count = 0;
$documents_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}aipc_documents'");
if ($documents_table_exists) {
    $documents = $document_manager->get_documents();
    $document_count = count($documents);
}

$conversation_count = 0;
$recent_conversations = [];
$conversations_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}aipc_conversations'");
if ($conversations_table_exists) {
    $conversation_count = $wpdb->get_var("SELECT COUNT(DISTINCT conversation_id) FROM {$wpdb->prefix}aipc_conversations");
    $recent_conversations = $wpdb->get_results("SELECT conversation_id, MAX(created_at) as last_message FROM {$wpdb->prefix}aipc_conversations GROUP BY conversation_id ORDER BY last_message DESC LIMIT 5");
}
?>

<div class="wrap">
    <h1><?php _e('AI Product Chatbot Dashboard', 'ai-product-chatbot'); ?></h1>
    
    <div class="aipc-dashboard-grid">
        <!-- FAQ Card - Only if has license -->
        <?php if ($has_basic): ?>
        <div class="aipc-dashboard-card">
            <div class="aipc-card-header">
                <h3><?php _e('FAQ', 'ai-product-chatbot'); ?></h3>
                <span class="aipc-card-count"><?php 
                    $faq_count = 0;
                    $faq_table = $wpdb->prefix . 'aipc_faq';
                    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $faq_table)) === $faq_table) {
                        $faq_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $faq_table WHERE status != 'deleted'");
                    }
                    echo $faq_count;
                ?></span>
            </div>
            <?php if ($current_tier === 'basic'): ?>
                <p><?php _e('FAQ items (max 15 met Basic)', 'ai-product-chatbot'); ?></p>
            <?php else: ?>
                <p><?php _e('FAQ items (onbeperkt)', 'ai-product-chatbot'); ?></p>
            <?php endif; ?>
            <a href="<?php echo admin_url('admin.php?page=aipc-faq'); ?>" class="button button-primary">
                <?php _e('Beheer FAQ', 'ai-product-chatbot'); ?>
            </a>
        </div>
        <?php endif; ?>
        
        <!-- WooCommerce Products Card - Only if has basic+ license -->
        <?php if ($has_basic): ?>
        <div class="aipc-dashboard-card">
            <div class="aipc-card-header">
                <h3><?php _e('WooCommerce', 'ai-product-chatbot'); ?></h3>
                <span class="aipc-card-count"><?php echo $woo_active ? intval($product_count) : '—'; ?></span>
            </div>
            <?php if ($woo_active): ?>
                <?php if ($current_tier === 'basic'): ?>
                    <p><?php _e('WooCommerce producten beschikbaar', 'ai-product-chatbot'); ?></p>
                    <p style="color: #f0b849; font-size: 12px;"><?php _e('⚡ Geavanceerde sync vereist Business+ licentie', 'ai-product-chatbot'); ?></p>
                <?php else: ?>
                    <p><?php _e('WooCommerce producten (onbeperkt sync)', 'ai-product-chatbot'); ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p style="color:#a00;"><?php _e('WooCommerce niet geïnstalleerd of actief.', 'ai-product-chatbot'); ?></p>
            <?php endif; ?>
            <?php if ($woo_active): ?>
                <?php if ($has_business && $license->has_feature('woocommerce_full')): ?>
                    <a href="<?php echo admin_url('admin.php?page=aipc-woocommerce'); ?>" class="button button-primary">
                        <?php _e('WooCommerce Sync', 'ai-product-chatbot'); ?>
                    </a>
                <?php else: ?>
                    <a href="<?php echo admin_url('admin.php?page=aipc-license-compare'); ?>" class="button button-secondary">
                        <?php _e('🚀 Upgrade voor Sync', 'ai-product-chatbot'); ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Kenmerken Card - Only if has business+ license -->
        <?php if ($has_business): ?>
        <div class="aipc-dashboard-card">
            <div class="aipc-card-header">
                <h3><?php _e('Kenmerken', 'ai-product-chatbot'); ?></h3>
                <span class="aipc-card-count"><?php echo $ingredient_count; ?></span>
            </div>
            <p><?php _e('Totaal aantal kenmerken (volledig beheer)', 'ai-product-chatbot'); ?></p>
            <a href="<?php echo admin_url('admin.php?page=aipc-ingredients'); ?>" class="button button-primary">
                <?php _e('Beheer Kenmerken', 'ai-product-chatbot'); ?>
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Documenten Card - Only if has enterprise license -->
        <?php if ($current_tier === 'enterprise'): ?>
        <div class="aipc-dashboard-card">
            <div class="aipc-card-header">
                <h3><?php _e('Documenten', 'ai-product-chatbot'); ?></h3>
                <span class="aipc-card-count"><?php echo $document_count; ?></span>
            </div>
            <p><?php _e('Totaal aantal documenten in de kennisbank', 'ai-product-chatbot'); ?></p>
            <a href="<?php echo admin_url('admin.php?page=aipc-documents'); ?>" class="button button-primary">
                <?php _e('Beheer Documenten', 'ai-product-chatbot'); ?>
            </a>
        </div>
        <?php endif; ?>
        
        <div class="aipc-dashboard-card">
            <div class="aipc-card-header">
                <h3><?php _e('Gesprekken', 'ai-product-chatbot'); ?></h3>
                <span class="aipc-card-count"><?php echo $conversation_count; ?></span>
            </div>
            <p><?php _e('Totaal aantal chatbot gesprekken', 'ai-product-chatbot'); ?></p>
        </div>
        
        <!-- Status Card - Always visible -->
        <div class="aipc-dashboard-card">
            <div class="aipc-card-header">
                <h3><?php _e('Chatbot Status', 'ai-product-chatbot'); ?></h3>
                <span class="aipc-status-indicator <?php 
                    if (!$has_license) {
                        echo 'inactive'; // No license: limited functionality
                    } elseif ($current_tier === 'basic') {
                        echo 'active'; // Basic license: active without API key
                    } elseif ($has_business && !empty(get_option('aipc_openai_api_key'))) {
                        echo 'active'; // Business+ license + API key
                    } elseif ($has_business) {
                        echo 'inactive'; // Business+ license but no API key
                    } else {
                        echo 'active'; // Fallback
                    }
                ?>"></span>
            </div>
            <?php if (!$has_license): ?>
                <p><?php _e('Geen licentie: Chatbot uitgeschakeld', 'ai-product-chatbot'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=aipc-license-compare'); ?>" class="button button-primary">
                    <?php _e('Activeer Licentie', 'ai-product-chatbot'); ?>
                </a>
            <?php elseif ($current_tier === 'basic'): ?>
                <p><?php _e('Basic licentie actief: FAQ + WooCommerce beschikbaar', 'ai-product-chatbot'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=aipc-settings'); ?>" class="button">
                    <?php _e('Instellingen', 'ai-product-chatbot'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=aipc-license-compare'); ?>" class="button button-primary">
                    <?php _e('Upgrade voor AI', 'ai-product-chatbot'); ?>
                </a>
            <?php elseif ($has_business && !empty(get_option('aipc_openai_api_key'))): ?>
                <p><?php _e('Volledig actief: AI + FAQ + WooCommerce + Meer', 'ai-product-chatbot'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=aipc-settings'); ?>" class="button">
                    <?php _e('Instellingen', 'ai-product-chatbot'); ?>
                </a>
            <?php elseif ($has_business): ?>
                <p><?php _e('Business+ licentie actief - API key vereist voor AI', 'ai-product-chatbot'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=aipc-settings'); ?>" class="button button-primary">
                    <?php _e('API Key Configureren', 'ai-product-chatbot'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- No License Upgrade Section -->
    <?php if (!$has_license): ?>
    <div class="aipc-dashboard-section">
        <h2><?php _e('🚀 Activeer je AI Chatbot', 'ai-product-chatbot'); ?></h2>
        <div style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 24px; text-align: center;">
            <h3 style="color: #2271b1; margin-top: 0;"><?php _e('Kies je licentie om te starten', 'ai-product-chatbot'); ?></h3>
            <p><?php _e('Zonder actieve licentie is de chatbot uitgeschakeld. Kies een licentie die past bij jouw behoeften:', 'ai-product-chatbot'); ?></p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin: 20px 0;">
                <div style="background: white; border: 1px solid #ddd; border-radius: 6px; padding: 16px;">
                    <h4 style="color: #2271b1; margin: 0 0 8px 0;">💼 Basic (€99)</h4>
                    <p style="margin: 0 0 12px 0; font-size: 14px; color: #666;">AI Chatbot + FAQ + WooCommerce</p>
                    <a href="<?php echo admin_url('admin.php?page=aipc-license-compare'); ?>" class="button button-primary" style="width: 100%;"><?php _e('Bekijk Details', 'ai-product-chatbot'); ?></a>
                </div>
                <div style="background: white; border: 2px solid #00a32a; border-radius: 6px; padding: 16px;">
                    <h4 style="color: #00a32a; margin: 0 0 8px 0;">🚀 Business (€299)</h4>
                    <p style="margin: 0 0 12px 0; font-size: 14px; color: #666;">Alles + Product Quiz + Documenten</p>
                    <a href="<?php echo admin_url('admin.php?page=aipc-license-compare'); ?>" class="button" style="background: #00a32a; border-color: #00a32a; color: white; width: 100%;"><?php _e('Populair - Bekijk', 'ai-product-chatbot'); ?></a>
                </div>
                <div style="background: white; border: 1px solid #ddd; border-radius: 6px; padding: 16px;">
                    <h4 style="color: #8b5cf6; margin: 0 0 8px 0;">🏆 Enterprise (€599)</h4>
                    <p style="margin: 0 0 12px 0; font-size: 14px; color: #666;">Alles + Advanced Analytics</p>
                    <a href="<?php echo admin_url('admin.php?page=aipc-license-compare'); ?>" class="button button-secondary" style="width: 100%;"><?php _e('Bekijk Details', 'ai-product-chatbot'); ?></a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Basic License Upgrade Section -->
    <?php if ($current_tier === 'basic'): ?>
    <div class="aipc-dashboard-section">
        <h2><?php _e('🚀 Upgrade naar Business+', 'ai-product-chatbot'); ?></h2>
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; padding: 24px; color: white; text-align: center;">
            <h3 style="color: white; margin-top: 0;"><?php _e('Ontgrendel de volledige kracht van AI', 'ai-product-chatbot'); ?></h3>
            <p style="opacity: 0.9; margin-bottom: 20px;"><?php _e('Met Business+ krijg je toegang tot geavanceerde AI-features:', 'ai-product-chatbot'); ?></p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin: 20px 0; text-align: left;">
                <div style="background: rgba(255,255,255,0.1); border-radius: 6px; padding: 16px;">
                    <h4 style="color: white; margin: 0 0 8px 0;">🤖 AI & API Configuratie</h4>
                    <p style="margin: 0; font-size: 14px; opacity: 0.9;">OpenAI/OpenRouter integratie voor slimme antwoorden</p>
                </div>
                <div style="background: rgba(255,255,255,0.1); border-radius: 6px; padding: 16px;">
                    <h4 style="color: white; margin: 0 0 8px 0;">🏷️ Kenmerken Beheer</h4>
                    <p style="margin: 0; font-size: 14px; opacity: 0.9;">Volledig beheer van product eigenschappen</p>
                </div>
                <div style="background: rgba(255,255,255,0.1); border-radius: 6px; padding: 16px;">
                    <h4 style="color: white; margin: 0 0 8px 0;">🎯 Product Quiz</h4>
                    <p style="margin: 0; font-size: 14px; opacity: 0.9;">Interactieve tests voor gepersonaliseerde aanbevelingen</p>
                </div>
                <div style="background: rgba(255,255,255,0.1); border-radius: 6px; padding: 16px;">
                    <h4 style="color: white; margin: 0 0 8px 0;">📄 Content Sources</h4>
                    <p style="margin: 0; font-size: 14px; opacity: 0.9;">Geavanceerde content integratie en custom post types</p>
                </div>
            </div>
            
            <a href="<?php echo admin_url('admin.php?page=aipc-license-compare'); ?>" class="button button-secondary" style="background: white; color: #764ba2; border: none; font-weight: bold; padding: 10px 20px; margin-top: 10px;">
                <?php _e('🚀 Upgrade naar Business - Bekijk Prijzen', 'ai-product-chatbot'); ?>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="aipc-dashboard-section">
        <h2><?php _e('Recente Gesprekken', 'ai-product-chatbot'); ?></h2>
        <?php if (!empty($recent_conversations)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Gesprek ID', 'ai-product-chatbot'); ?></th>
                        <th><?php _e('Laatste Bericht', 'ai-product-chatbot'); ?></th>
                        <th><?php _e('Acties', 'ai-product-chatbot'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_conversations as $conversation): ?>
                        <tr>
                            <td><code><?php echo esc_html(substr($conversation->conversation_id, 0, 20)); ?>...</code></td>
                            <td><?php echo esc_html(human_time_diff(strtotime($conversation->last_message), current_time('timestamp'))); ?> <?php _e('geleden', 'ai-product-chatbot'); ?></td>
                            <td>
                                <a href="#" class="button button-small" onclick="aipcViewConversation('<?php echo esc_js($conversation->conversation_id); ?>')">
                                    <?php _e('Bekijk', 'ai-product-chatbot'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('Nog geen gesprekken gevonden.', 'ai-product-chatbot'); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="aipc-dashboard-section">
        <h2><?php _e('Snelle Acties', 'ai-product-chatbot'); ?></h2>
        <div class="aipc-quick-actions">
            <!-- FAQ Action - Only if has license -->
            <?php if ($has_basic): ?>
            <a href="<?php echo admin_url('admin.php?page=aipc-faq&action=add'); ?>" class="button button-secondary">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php _e('Nieuwe FAQ', 'ai-product-chatbot'); ?>
            </a>
            <?php endif; ?>
            
            <!-- Settings - Only if has license -->
            <?php if ($has_basic): ?>
            <a href="<?php echo admin_url('admin.php?page=aipc-settings'); ?>" class="button button-secondary">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php _e('Instellingen', 'ai-product-chatbot'); ?>
            </a>
            <?php endif; ?>
            
            <!-- Kenmerken - Only if has Business+ license -->
            <?php if ($has_business): ?>
            <a href="<?php echo admin_url('admin.php?page=aipc-ingredients&action=add'); ?>" class="button button-secondary">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php _e('Nieuw Kenmerk', 'ai-product-chatbot'); ?>
            </a>
            <?php endif; ?>
            
            <!-- Test Chatbot - Only if has license -->
            <?php if ($has_basic): ?>
            <button type="button" class="button button-secondary" onclick="aipcTestChatbot()">
                <span class="dashicons dashicons-format-chat"></span>
                <?php _e('Test Chatbot', 'ai-product-chatbot'); ?>
            </button>
            <?php else: ?>
            <span class="button button-secondary button-disabled" title="<?php _e('Licentie vereist - Chatbot is uitgeschakeld', 'ai-product-chatbot'); ?>">
                <span class="dashicons dashicons-lock"></span>
                <?php _e('Test Chatbot', 'ai-product-chatbot'); ?>
            </span>
            <?php endif; ?>
            
            <!-- Upgrade Button - Only if no license or basic -->
            <?php if (!$has_license || $current_tier === 'basic'): ?>
            <a href="<?php echo admin_url('admin.php?page=aipc-license-compare'); ?>" class="button button-primary">
                <span class="dashicons dashicons-star-filled"></span>
                <?php _e(!$has_license ? 'Upgrade Licentie' : 'Upgrade naar Business', 'ai-product-chatbot'); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.aipc-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.aipc-dashboard-card {
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.aipc-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.aipc-card-header h3 {
    margin: 0;
    font-size: 16px;
}

.aipc-card-count {
    background: #0073aa;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: bold;
}

.aipc-status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
}

.aipc-status-indicator.active {
    background: #46b450;
}

.aipc-status-indicator.inactive {
    background: #dc3232;
}

.aipc-dashboard-section {
    margin: 30px 0;
}

.aipc-quick-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.aipc-quick-actions .button {
    display: flex;
    align-items: center;
    gap: 5px;
}

.button-disabled {
    opacity: 0.6;
    cursor: not-allowed !important;
    pointer-events: none;
}
</style>

<style>
.aipc-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: none; z-index: 10000; }
.aipc-modal { background: #fff; max-width: 700px; margin: 60px auto; padding: 20px; border-radius: 8px; position: relative; }
.aipc-modal-close { position: absolute; top: 8px; right: 10px; border: none; background: transparent; font-size: 20px; cursor: pointer; }
</style>

<script>
function aipcViewConversation(conversationId) {
    var nonce = '<?php echo wp_create_nonce('aipc_admin_nonce'); ?>';
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'aipc_get_conversation', conversation_id: conversationId, nonce: nonce },
        success: function(resp){
            var html = '';
            if (resp.success && resp.data && resp.data.messages) {
                resp.data.messages.forEach(function(m){
                    var role = m.role === 'assistant' ? '🤖' : '👤';
                    var content = (m.content || '').replace(/\n/g,'<br>');
                    html += '<div style="margin-bottom:10px;"><strong>'+role+'</strong><br><div>'+content+'</div><small>'+m.created_at+'</small></div>';
                });
            } else {
                html = (resp.data && resp.data.message) ? resp.data.message : '<?php echo esc_js(__('Fout bij ophalen van gesprek.', 'ai-product-chatbot')); ?>';
            }
            aipcOpenModal(html);
        },
        error: function(){ aipcOpenModal('<?php echo esc_js(__('Fout bij ophalen van gesprek.', 'ai-product-chatbot')); ?>'); }
    });
}

function aipcOpenModal(contentHtml){
    var $ = jQuery;
    var $modal = $('#aipc-conv-modal');
    if ($modal.length === 0) {
        $('body').append('<div id="aipc-conv-modal" class="aipc-modal-overlay"><div class="aipc-modal"><button class="aipc-modal-close">×</button><div class="aipc-modal-body"></div></div></div>');
        $modal = $('#aipc-conv-modal');
        $modal.on('click', '.aipc-modal-close, .aipc-modal-overlay', function(e){ if (e.target === this) $modal.hide(); });
    }
    $modal.find('.aipc-modal-body').html(contentHtml || '');
    $modal.show();
}

function aipcTestChatbot() {
    // Check if we're on frontend
    if (window.location.href.indexOf('/wp-admin/') === -1) {
        if (typeof window.openAIPCChatbot === 'function') {
            window.openAIPCChatbot();
        } else {
            alert('Chatbot is nog niet geladen. Probeer de pagina te verversen.');
        }
    } else {
        // We're in admin area, redirect to frontend
        var frontendUrl = window.location.origin;
        var newWindow = window.open(frontendUrl, '_blank');
        if (newWindow) {
            newWindow.onload = function() {
                setTimeout(function() {
                    if (typeof newWindow.openAIPCChatbot === 'function') {
                        newWindow.openAIPCChatbot();
                    } else {
                        newWindow.alert('Chatbot is nog niet geladen. Probeer de pagina te verversen.');
                    }
                }, 2000);
            };
        }
    }
}
</script>
