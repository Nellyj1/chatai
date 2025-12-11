<?php
if (!defined('ABSPATH')) {
    exit;
}

$woocommerce_integration = new AIPC_WooCommerce_Integration();
$sync_status = $woocommerce_integration->get_sync_status();
$woo_products = $woocommerce_integration->get_woocommerce_products();
$synced_products = $woocommerce_integration->get_synced_products();

// License gating: WooCommerce limits and features
$advanced_features_allowed = true;
$woocommerce_allowed = true;
$product_limit_reached = false;
$current_tier = 'business';
$upgrade_url = '';
$product_limit = 50;
$synced_count = $sync_status['synced_products'];

if (class_exists('AIPC_License')) {
    $lic = AIPC_License::getInstance();
    $is_active = $lic->is_active();
    $current_tier = $is_active ? $lic->get_current_tier() : 'none';
    $advanced_features_allowed = ($is_active && $lic->has_feature('api_integrations'));
    $upgrade_url = $lic->generate_upgrade_url('business');
    
    // Check WooCommerce access and limits
    if (!$is_active) {
        $woocommerce_allowed = false;
    } elseif ($current_tier === 'basic') {
        $woocommerce_allowed = true;
        $product_limit_reached = ($synced_count >= $product_limit);
    }
} else {
    $woocommerce_allowed = false;
    $current_tier = 'none';
}
?>

<div class="wrap">
    <h1><?php _e('WooCommerce Integratie', 'ai-product-chatbot'); ?></h1>
    
    <?php if (!$woocommerce_allowed): ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('🔒 WooCommerce Integratie Vereist Licentie', 'ai-product-chatbot'); ?></strong><br>
                <?php _e('Voor WooCommerce product sync heb je minimaal een Basic licentie nodig. Zonder licentie werkt de chatbot wel met FAQ responses.', 'ai-product-chatbot'); ?>
                <?php if ($upgrade_url): ?>
                    <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-primary" style="margin-left:10px;">
                        <?php _e('Upgrade naar Basic', 'ai-product-chatbot'); ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
    <?php elseif ($current_tier === 'basic' && $product_limit_reached): ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('🔒 Product Limiet Bereikt (Basic Tier)', 'ai-product-chatbot'); ?></strong><br>
                <?php printf(__('Je hebt de limiet van %d gesynchroniseerde producten bereikt. Nieuwe producten worden niet automatisch gesynchroniseerd. Upgrade naar Business voor onbeperkte producten.', 'ai-product-chatbot'), $product_limit); ?>
                <?php if ($upgrade_url): ?>
                    <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-primary" style="margin-left:10px;">
                        <?php _e('Upgrade naar Business', 'ai-product-chatbot'); ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
    <?php elseif ($current_tier === 'basic'): ?>
        <div class="notice notice-info">
            <p>
                <strong><?php _e('ℹ️ Basic Tier Limiet', 'ai-product-chatbot'); ?></strong><br>
                <?php printf(__('WooCommerce producten: %d / %d (Basic tier limiet). Automatische sync werkt tot de limiet. Upgrade naar Business voor onbeperkte producten + bulk sync.', 'ai-product-chatbot'), $synced_count, $product_limit); ?>
                <?php if ($upgrade_url): ?>
                    <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-secondary" style="margin-left:10px;">
                        <?php _e('Upgrade naar Business', 'ai-product-chatbot'); ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
    <?php elseif (!$advanced_features_allowed): ?>
        <div class="notice notice-info">
            <p>
                <?php _e('🚀 Volledige WooCommerce integratie actief: automatische sync + individuele handmatige sync inbegrepen. Bulk operaties vereisen Business upgrade.', 'ai-product-chatbot'); ?>
                <?php if ($upgrade_url): ?>
                    <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-secondary" style="margin-left:10px;">
                        <?php _e('Upgrade voor Bulk Sync', 'ai-product-chatbot'); ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
    
    <?php if (!class_exists('WooCommerce')): ?>
        <div class="notice notice-warning">
            <p><strong><?php _e('WooCommerce niet gevonden', 'ai-product-chatbot'); ?></strong></p>
            <p><?php _e('Installeer en activeer WooCommerce om deze functionaliteit te gebruiken.', 'ai-product-chatbot'); ?></p>
        </div>
    <?php else: ?>
        
        <!-- Sync Status Overview -->
        <div class="aipc-woocommerce-overview">
            <div class="aipc-sync-status-card">
                <h3><?php _e('Sync Status', 'ai-product-chatbot'); ?></h3>
                <?php if (isset($sync_status['legacy_products']) && $sync_status['legacy_products'] > 0): ?>
                    <div class="notice notice-info inline">
                        <p><strong><?php _e('Info:', 'ai-product-chatbot'); ?></strong> <?php printf(__('Je hebt %d bestaande producten die nog werken in de chatbot. Deze zijn toegevoegd voordat de nieuwe WooCommerce sync werd geïmplementeerd.', 'ai-product-chatbot'), $sync_status['legacy_products']); ?></p>
                    </div>
                <?php endif; ?>
                <div class="aipc-sync-stats">
                    <div class="aipc-stat">
                        <span class="aipc-stat-number"><?php echo $sync_status['total_woo_products']; ?></span>
                        <span class="aipc-stat-label"><?php _e('WooCommerce Producten', 'ai-product-chatbot'); ?></span>
                    </div>
                    <div class="aipc-stat">
                        <span class="aipc-stat-number"><?php echo $sync_status['synced_products']; ?></span>
                        <span class="aipc-stat-label"><?php _e('Nieuwe Sync', 'ai-product-chatbot'); ?></span>
                    </div>
                    <?php if (isset($sync_status['legacy_products']) && $sync_status['legacy_products'] > 0): ?>
                    <div class="aipc-stat">
                        <span class="aipc-stat-number"><?php echo $sync_status['legacy_products']; ?></span>
                        <span class="aipc-stat-label"><?php _e('Bestaande Producten', 'ai-product-chatbot'); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="aipc-stat">
                        <span class="aipc-stat-number"><?php echo isset($sync_status['total_chatbot_products']) ? $sync_status['total_chatbot_products'] : $sync_status['synced_products']; ?></span>
                        <span class="aipc-stat-label"><?php _e('Totaal Chatbot', 'ai-product-chatbot'); ?></span>
                    </div>
                    <div class="aipc-stat">
                        <span class="aipc-stat-number"><?php echo $sync_status['sync_percentage']; ?>%</span>
                        <span class="aipc-stat-label"><?php _e('WC Sync %', 'ai-product-chatbot'); ?></span>
                    </div>
                </div>
                <div class="aipc-sync-progress">
                    <div class="aipc-progress-bar">
                        <div class="aipc-progress-fill" style="width: <?php echo $sync_status['sync_percentage']; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sync Actions -->
        <div class="aipc-woocommerce-actions">
            <h2><?php _e('Sync Acties', 'ai-product-chatbot'); ?></h2>
            <div class="aipc-action-buttons">
                <?php if ($advanced_features_allowed): ?>
                    <button type="button" class="button button-primary" id="aipc-sync-products">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Synchroniseer Alle Producten', 'ai-product-chatbot'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="aipc-clear-sync">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Wis Sync Data', 'ai-product-chatbot'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="aipc-cleanup-duplicates">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php _e('Ruim Duplicaten Op', 'ai-product-chatbot'); ?>
                    </button>
                <?php else: ?>
                    <span class="button button-primary button-disabled" title="<?php _e('Business licentie vereist', 'ai-product-chatbot'); ?>">
                        <span class="dashicons dashicons-lock"></span>
                        <?php _e('Synchroniseer Alle Producten', 'ai-product-chatbot'); ?>
                    </span>
                    <span class="button button-secondary button-disabled" title="<?php _e('Business licentie vereist', 'ai-product-chatbot'); ?>">
                        <span class="dashicons dashicons-lock"></span>
                        <?php _e('Wis Sync Data', 'ai-product-chatbot'); ?>
                    </span>
                    <span class="button button-secondary button-disabled" title="<?php _e('Business licentie vereist', 'ai-product-chatbot'); ?>">
                        <span class="dashicons dashicons-lock"></span>
                        <?php _e('Ruim Duplicaten Op', 'ai-product-chatbot'); ?>
                    </span>
                    <div class="aipc-upgrade-prompt">
                        <p style="color:#666;margin:10px 0;"><?php _e('Upgrade voor bulk sync van alle producten tegelijk, custom fields configuratie en cleanup tools.', 'ai-product-chatbot'); ?></p>
                        <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-primary">
                            <?php _e('Upgrade naar Business', 'ai-product-chatbot'); ?>
                        </a>
                    </div>
                <?php endif; ?>
                
                <a href="<?php echo admin_url('admin.php?page=aipc-settings'); ?>" class="button">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Sync Instellingen', 'ai-product-chatbot'); ?>
                </a>
            </div>
            <div id="aipc-sync-result" class="aipc-sync-result"></div>
        </div>
        
        <!-- WooCommerce Products List -->
        <div class="aipc-woocommerce-products">
            <h2><?php _e('WooCommerce Producten', 'ai-product-chatbot'); ?></h2>
            <?php 
            $shown_count = count($woo_products);
            $total_count = $sync_status['total_woo_products'];
            $showing_all = ($shown_count >= $total_count);
            ?>
            <div class="notice notice-info inline" style="margin: 0 0 15px 0;">
                <p>
                    <strong><?php _e('ℹ️ Product Overzicht:', 'ai-product-chatbot'); ?></strong>
                    <?php if ($showing_all): ?>
                        <?php printf(
                            __('Alle %d WooCommerce producten worden getoond. Automatische sync werkt voor alle wijzigingen, individuele handmatige sync per product inbegrepen.', 'ai-product-chatbot'),
                            $total_count
                        ); ?>
                    <?php else: ?>
                        <?php printf(
                            __('De eerste %d van %d WooCommerce producten worden getoond. Automatische sync + individuele handmatige sync inbegrepen. Voor bulk "sync alle": upgrade naar Business.', 'ai-product-chatbot'),
                            $shown_count,
                            $total_count
                        ); ?>
                    <?php endif; ?>
                </p>
            </div>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Product', 'ai-product-chatbot'); ?></th>
                        <th><?php _e('Prijs', 'ai-product-chatbot'); ?></th>
                        <th><?php _e('Voorraad', 'ai-product-chatbot'); ?></th>
                        <th><?php _e('Sync Status', 'ai-product-chatbot'); ?></th>
                        <th><?php _e('Acties', 'ai-product-chatbot'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($woo_products as $product): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($product['name']); ?></strong>
                                <br><small>ID: <?php echo $product['id']; ?></small>
                            </td>
                            <td>
                                <?php 
                                $price = isset($product['price']) && is_numeric($product['price']) ? (float)$product['price'] : 0.00;
                                echo '€' . number_format($price, 2);
                                ?>
                            </td>
                            <td>
                                <span class="aipc-stock-status aipc-stock-<?php echo $product['stock_status']; ?>">
                                    <?php echo ucfirst($product['stock_status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($product['is_synced']): ?>
                                    <span class="aipc-sync-status aipc-synced">
                                        <span class="dashicons dashicons-yes"></span>
                                        <?php _e('Gesynchroniseerd', 'ai-product-chatbot'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="aipc-sync-status aipc-not-synced">
                                        <span class="dashicons dashicons-no"></span>
                                        <?php _e('Niet gesynchroniseerd', 'ai-product-chatbot'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo get_edit_post_link($product['id']); ?>" class="button button-small">
                                    <?php _e('Bewerken', 'ai-product-chatbot'); ?>
                                </a>
                                <a href="<?php echo get_permalink($product['id']); ?>" class="button button-small" target="_blank">
                                    <?php _e('Bekijken', 'ai-product-chatbot'); ?>
                                </a>
                                <?php if (!$product['is_synced']): ?>
                                    <button type="button" class="button button-small button-primary aipc-sync-single-product" data-product-id="<?php echo $product['id']; ?>" title="<?php _e('Sync dit product naar de chatbot', 'ai-product-chatbot'); ?>">
                                        <span class="dashicons dashicons-update"></span>
                                        <?php _e('Sync', 'ai-product-chatbot'); ?>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="button button-small button-secondary aipc-unsync-single-product" data-product-id="<?php echo $product['id']; ?>" title="<?php _e('Verwijder dit product uit de chatbot', 'ai-product-chatbot'); ?>">
                                        <span class="dashicons dashicons-no"></span>
                                        <?php _e('Unsync', 'ai-product-chatbot'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Synced Products List -->
        <div class="aipc-synced-products">
            <h2><?php _e('Gesynchroniseerde Producten', 'ai-product-chatbot'); ?></h2>
            <?php if (!empty($synced_products)): ?>
                <div class="notice notice-info inline" style="margin: 0 0 15px 0;">
                    <p>
                        <strong><?php _e('ℹ️ Recente Sync:', 'ai-product-chatbot'); ?></strong>
                        <?php printf(
                            __('Hieronder worden de %d meest recent gesynchroniseerde producten getoond. In totaal zijn er %d producten beschikbaar voor de chatbot.', 'ai-product-chatbot'),
                            count($synced_products),
                            $sync_status['synced_products'] + (isset($sync_status['legacy_products']) ? $sync_status['legacy_products'] : 0)
                        ); ?>
                    </p>
                </div>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Chatbot Product', 'ai-product-chatbot'); ?></th>
                            <th><?php _e('WooCommerce ID', 'ai-product-chatbot'); ?></th>
                            <th><?php _e('Prijs', 'ai-product-chatbot'); ?></th>
                            <th><?php _e('Laatste Update', 'ai-product-chatbot'); ?></th>
                            <th><?php _e('Acties', 'ai-product-chatbot'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($synced_products as $product): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($product['name']); ?></strong>
                                    <br><small><?php echo esc_html(wp_trim_words($product['description'], 10)); ?></small>
                                </td>
                                <td>
                                    <?php if ($product['woocommerce_id']): ?>
                                        <a href="<?php echo get_edit_post_link($product['woocommerce_id']); ?>" target="_blank">
                                            #<?php echo $product['woocommerce_id']; ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="aipc-no-woo-id"><?php _e('Geen WooCommerce ID', 'ai-product-chatbot'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $price = isset($product['price']) && is_numeric($product['price']) ? (float)$product['price'] : 0.00;
                                    echo '€' . number_format($price, 2);
                                    ?>
                                </td>
                                <td><?php echo esc_html(human_time_diff(strtotime($product['updated_at']), current_time('timestamp'))); ?> <?php _e('geleden', 'ai-product-chatbot'); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=aipc-products&action=edit&id=' . $product['id']); ?>" class="button button-small">
                                        <?php _e('Bewerken', 'ai-product-chatbot'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('Nog geen producten gesynchroniseerd.', 'ai-product-chatbot'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Integration Settings Info -->
        <div class="aipc-integration-info">
            <h2><?php _e('Integratie Informatie', 'ai-product-chatbot'); ?></h2>
            <div class="aipc-info-grid">
                <div class="aipc-info-card">
                    <h3><?php _e('Automatische Sync', 'ai-product-chatbot'); ?></h3>
                    <?php $auto_sync_enabled = get_option('aipc_woocommerce_auto_sync', true); ?>
                    <?php if ($auto_sync_enabled): ?>
                        <p><?php _e('Producten worden automatisch gesynchroniseerd wanneer ze worden toegevoegd, bijgewerkt of verwijderd in WooCommerce.', 'ai-product-chatbot'); ?></p>
                        <p><strong><?php _e('Status:', 'ai-product-chatbot'); ?></strong> 
                            <span style="color: #46b450;"><?php _e('✓ Ingeschakeld', 'ai-product-chatbot'); ?></span>
                        </p>
                    <?php else: ?>
                        <p><?php _e('Automatische sync is uitgeschakeld. Producten moeten handmatig worden gesynchroniseerd via de "Sync" knoppen hierboven.', 'ai-product-chatbot'); ?></p>
                        <p><strong><?php _e('Status:', 'ai-product-chatbot'); ?></strong> 
                            <span style="color: #dc3232;"><?php _e('✗ Uitgeschakeld', 'ai-product-chatbot'); ?></span>
                        </p>
                        <p><small><a href="<?php echo admin_url('admin.php?page=aipc-settings#woocommerce'); ?>"><?php _e('→ Inschakelen in Instellingen', 'ai-product-chatbot'); ?></a></small></p>
                    <?php endif; ?>
                </div>
                
                <div class="aipc-info-card">
                    <h3><?php _e('Custom Fields', 'ai-product-chatbot'); ?></h3>
                    <p><?php _e('Product eigenschappen en geschiktheid worden gehaald uit WooCommerce custom fields of product tags/categorieën.', 'ai-product-chatbot'); ?></p>
                    <p><strong><?php _e('Eigenschappen veld:', 'ai-product-chatbot'); ?></strong> <code><?php echo get_option('aipc_woocommerce_ingredients_field', '_ingredients'); ?></code> <small>(fallback: Product Tags)</small></p>
                    <p><strong><?php _e('Geschikt voor veld:', 'ai-product-chatbot'); ?></strong> <code><?php echo get_option('aipc_woocommerce_skin_types_field', '_skin_types'); ?></code> <small>(fallback: Product Categories)</small></p>
                </div>
                
                <div class="aipc-info-card">
                    <h3><?php _e('Chatbot Integratie', 'ai-product-chatbot'); ?></h3>
                    <p><?php _e('Gesynchroniseerde producten worden automatisch gebruikt door de chatbot voor productaanbevelingen en vragen.', 'ai-product-chatbot'); ?></p>
                    <p><strong><?php _e('Shopping cart:', 'ai-product-chatbot'); ?></strong> 
                        <?php echo get_option('aipc_woocommerce_show_cart', true) ? __('Ingeschakeld', 'ai-product-chatbot') : __('Uitgeschakeld', 'ai-product-chatbot'); ?>
                    </p>
                </div>
            </div>
        </div>
        
    <?php endif; ?>
</div>

<style>
.aipc-woocommerce-overview {
    margin: 20px 0;
}

.aipc-sync-status-card {
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.aipc-sync-stats {
    display: flex;
    gap: 30px;
    margin: 20px 0;
}

.aipc-stat {
    text-align: center;
}

.aipc-stat-number {
    display: block;
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
}

.aipc-stat-label {
    display: block;
    font-size: 14px;
    color: #666;
    margin-top: 5px;
}

.aipc-sync-progress {
    margin-top: 20px;
}

.aipc-progress-bar {
    width: 100%;
    height: 20px;
    background: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
}

.aipc-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #0073aa, #00a0d2);
    transition: width 0.3s ease;
}

.aipc-woocommerce-actions {
    margin: 30px 0;
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
}

.aipc-action-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.aipc-action-buttons .button {
    display: flex;
    align-items: center;
    gap: 5px;
}

.aipc-sync-result {
    margin-top: 15px;
    padding: 10px;
    border-radius: 4px;
    display: none;
}

.aipc-sync-result.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.aipc-sync-result.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.aipc-woocommerce-products,
.aipc-synced-products {
    margin: 30px 0;
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
}

.aipc-stock-status {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.aipc-stock-instock {
    background: #d4edda;
    color: #155724;
}

.aipc-stock-outofstock {
    background: #f8d7da;
    color: #721c24;
}

.aipc-sync-status {
    display: flex;
    align-items: center;
    gap: 5px;
}

.aipc-synced {
    color: #46b450;
}

.aipc-not-synced {
    color: #dc3232;
}

.aipc-integration-info {
    margin: 30px 0;
}

.aipc-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.aipc-info-card {
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 15px;
}

.aipc-info-card h3 {
    margin-top: 0;
    color: #333;
}

.aipc-no-woo-id {
    color: #999;
    font-style: italic;
}

.button-disabled {
    opacity: 0.6;
    cursor: not-allowed;
    pointer-events: none;
}

.aipc-upgrade-prompt {
    margin-top: 15px;
    padding: 10px;
    background: #f0f8ff;
    border: 1px solid #b3d9ff;
    border-radius: 4px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Sync products
    $('#aipc-sync-products').on('click', function() {
        const $button = $(this);
        const $result = $('#aipc-sync-result');
        
        $button.prop('disabled', true).html('<span class="aipc-loading"></span> Synchroniseren...');
        $result.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_sync_woocommerce_products',
                nonce: '<?php echo wp_create_nonce('aipc_woocommerce_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('error').addClass('success').html(response.data.message).show();
                    // Reload page after 2 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $result.removeClass('success').addClass('error').html(response.data.message).show();
                }
            },
            error: function() {
                $result.removeClass('success').addClass('error').html('Fout bij synchroniseren van producten.').show();
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> <?php _e('Synchroniseer Alle Producten', 'ai-product-chatbot'); ?>');
            }
        });
    });
    
    // Clear sync
    $('#aipc-clear-sync').on('click', function() {
        if (!confirm('<?php _e('Weet je zeker dat je alle gesynchroniseerde WooCommerce producten wilt verwijderen?', 'ai-product-chatbot'); ?>')) {
            return;
        }
        
        const $button = $(this);
        const $result = $('#aipc-sync-result');
        
        $button.prop('disabled', true).html('<span class="aipc-loading"></span> Verwijderen...');
        $result.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_clear_woocommerce_sync',
                nonce: '<?php echo wp_create_nonce('aipc_woocommerce_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('error').addClass('success').html(response.data.message).show();
                    // Reload page after 2 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $result.removeClass('success').addClass('error').html(response.data.message).show();
                }
            },
            error: function() {
                $result.removeClass('success').addClass('error').html('Fout bij verwijderen van sync data.').show();
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> <?php _e('Wis Sync Data', 'ai-product-chatbot'); ?>');
            }
        });
    });
    
    // Cleanup duplicates
    $('#aipc-cleanup-duplicates').on('click', function() {
        const $button = $(this);
        const $result = $('#aipc-sync-result');
        
        $button.prop('disabled', true).html('<span class="aipc-loading"></span> Opschonen...');
        $result.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_cleanup_duplicates',
                nonce: '<?php echo wp_create_nonce('aipc_woocommerce_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('error').addClass('success').html(response.data.message).show();
                    // Reload page after 2 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $result.removeClass('success').addClass('error').html(response.data.message).show();
                }
            },
            error: function() {
                $result.removeClass('success').addClass('error').html('Fout bij opschonen van duplicaten.').show();
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools"></span> <?php _e('Ruim Duplicaten Op', 'ai-product-chatbot'); ?>');
            }
        });
    });
    
    // Single product sync
    $('.aipc-sync-single-product').on('click', function() {
        const $button = $(this);
        const productId = $button.data('product-id');
        const $result = $('#aipc-sync-result');
        
        $button.prop('disabled', true).html('<span class="aipc-loading"></span> Sync...');
        $result.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_sync_single_product',
                product_id: productId,
                nonce: '<?php echo wp_create_nonce('aipc_woocommerce_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('error').addClass('success').html(response.data.message).show();
                    // Reload page after 1 second
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $result.removeClass('success').addClass('error').html(response.data.message).show();
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', xhr.responseText);
                console.log('Status:', status);
                console.log('Error:', error);
                $result.removeClass('success').addClass('error').html('Fout bij synchroniseren van product: ' + error).show();
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> <?php _e('Sync', 'ai-product-chatbot'); ?>');
            }
        });
    });
    
    // Single product unsync
    $('.aipc-unsync-single-product').on('click', function() {
        const $button = $(this);
        const productId = $button.data('product-id');
        const $result = $('#aipc-sync-result');
        
        if (!confirm('<?php _e('Weet je zeker dat je dit product wilt verwijderen uit de chatbot?', 'ai-product-chatbot'); ?>')) {
            return;
        }
        
        $button.prop('disabled', true).html('<span class="aipc-loading"></span> Unsync...');
        $result.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_unsync_single_product',
                product_id: productId,
                nonce: '<?php echo wp_create_nonce('aipc_woocommerce_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('error').addClass('success').html(response.data.message).show();
                    // Reload page after 1 second
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $result.removeClass('success').addClass('error').html(response.data.message).show();
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', xhr.responseText);
                console.log('Status:', status);
                console.log('Error:', error);
                $result.removeClass('success').addClass('error').html('Fout bij verwijderen van product: ' + error).show();
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> <?php _e('Unsync', 'ai-product-chatbot'); ?>');
            }
        });
    });
});
</script>
