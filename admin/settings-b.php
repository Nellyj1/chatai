<?php
if (!defined('ABSPATH')) {
    exit;
}

// Check license status early for form processing
if (!class_exists('AIPC_License')) {
    require_once AIPC_PLUGIN_DIR . 'includes/class-aipc-license.php';
}
$license_check = AIPC_License::getInstance();
$is_license_active = $license_check->is_active();

// Handle form submission
if (isset($_POST['submit']) && wp_verify_nonce($_POST['aipc_settings_nonce'], 'aipc_save_settings')) {
    
    // Always process license server URLs (available in both modes)
    update_option('aipc_license_server_url', esc_url_raw($_POST['aipc_license_server_url'] ?? ''));
    update_option('aipc_upgrade_url', esc_url_raw($_POST['aipc_upgrade_url'] ?? ''));
    
    // Only process full settings if user has active license
    if ($is_license_active) {
        // Basic settings (always available)
        update_option('aipc_chatbot_enabled', isset($_POST['aipc_chatbot_enabled']) ? 1 : 0);
        update_option('aipc_chatbot_title', sanitize_text_field($_POST['aipc_chatbot_title'] ?? 'AI Product Assistant'));
        update_option('aipc_chatbot_welcome_message', sanitize_textarea_field($_POST['aipc_chatbot_welcome_message'] ?? 'Hallo! Ik ben je AI product assistant. Hoe kan ik je vandaag helpen?'));
        update_option('aipc_chatbot_position', sanitize_text_field($_POST['aipc_chatbot_position'] ?? 'bottom-right'));
        update_option('aipc_chatbot_theme', sanitize_text_field($_POST['aipc_chatbot_theme'] ?? 'modern'));
        
        // Custom color settings (available to all license holders)
        update_option('aipc_primary_color', sanitize_hex_color($_POST['aipc_primary_color'] ?? '#667eea'));
        update_option('aipc_secondary_color', sanitize_hex_color($_POST['aipc_secondary_color'] ?? '#764ba2'));
        update_option('aipc_background_color', sanitize_hex_color($_POST['aipc_background_color'] ?? '#f8f9fa'));
        update_option('aipc_text_color', sanitize_hex_color($_POST['aipc_text_color'] ?? '#333333'));
        update_option('aipc_user_message_color', sanitize_hex_color($_POST['aipc_user_message_color'] ?? '#667eea'));
        update_option('aipc_assistant_message_color', sanitize_hex_color($_POST['aipc_assistant_message_color'] ?? '#ffffff'));
        update_option('aipc_header_text_color', sanitize_hex_color($_POST['aipc_header_text_color'] ?? '#ffffff'));
        update_option('aipc_input_background_color', sanitize_hex_color($_POST['aipc_input_background_color'] ?? '#f8f9fa'));
        update_option('aipc_input_border_color', sanitize_hex_color($_POST['aipc_input_border_color'] ?? '#e9ecef'));
        update_option('aipc_input_padding', absint($_POST['aipc_input_padding'] ?? 5));
        update_option('aipc_input_border_radius', absint($_POST['aipc_input_border_radius'] ?? 25));
        update_option('aipc_input_border_width', absint($_POST['aipc_input_border_width'] ?? 1));
        update_option('aipc_chatbot_icon', sanitize_text_field($_POST['aipc_chatbot_icon'] ?? 'robot'));
        
        // API and advanced features - available for all active licenses but with different capabilities
        if ($license_check->has_feature('basic_chatbot')) {
            update_option('aipc_openai_api_key', sanitize_text_field($_POST['aipc_openai_api_key'] ?? ''));
            update_option('aipc_openai_model', sanitize_text_field($_POST['aipc_openai_model'] ?? 'gpt-4'));
            update_option('aipc_api_provider', sanitize_text_field($_POST['aipc_api_provider'] ?? 'openai'));
            update_option('aipc_api_base', esc_url_raw($_POST['aipc_api_base'] ?? ''));
            update_option('aipc_show_api_errors', isset($_POST['aipc_show_api_errors']) ? 1 : 0);
            update_option('aipc_chatbot_tone', sanitize_text_field($_POST['aipc_chatbot_tone'] ?? 'neutral'));
            update_option('aipc_fallback_message', sanitize_textarea_field($_POST['aipc_fallback_message'] ?? ''));
            update_option('aipc_allow_judgement', isset($_POST['aipc_allow_judgement']) ? 1 : 0);
            update_option('aipc_tips_style', sanitize_text_field($_POST['aipc_tips_style'] ?? 'default'));
            update_option('aipc_allow_ai_ingredient_info', isset($_POST['aipc_allow_ai_ingredient_info']) ? 1 : 0);
            update_option('aipc_live_only_mode', isset($_POST['aipc_live_only_mode']) ? 1 : 0);
            
            // Privacy settings
            update_option('aipc_store_conversations', isset($_POST['aipc_store_conversations']) ? 1 : 0);
            update_option('aipc_retention_days', absint($_POST['aipc_retention_days'] ?? 90));
            update_option('aipc_mask_email', isset($_POST['aipc_mask_email']) ? 1 : 0);
            update_option('aipc_mask_phone', isset($_POST['aipc_mask_phone']) ? 1 : 0);
            
            // API monitoring privacy settings
            update_option('aipc_rate_limit_per_minute', absint($_POST['aipc_rate_limit_per_minute'] ?? 10));
            update_option('aipc_monitoring_retention_days', absint($_POST['aipc_monitoring_retention_days'] ?? 30));
            update_option('aipc_anonymize_monitoring_ips', isset($_POST['aipc_anonymize_monitoring_ips']) ? 1 : 0);
            
            // AI Prompts
            update_option('aipc_system_prompt', wp_kses_post($_POST['aipc_system_prompt'] ?? ''));
            update_option('aipc_greet_with_products', sanitize_textarea_field($_POST['aipc_greet_with_products'] ?? ''));
            update_option('aipc_greet_without_products', sanitize_textarea_field($_POST['aipc_greet_without_products'] ?? ''));
            update_option('aipc_max_faq_items', absint($_POST['aipc_max_faq_items'] ?? 20));
            update_option('aipc_max_doc_snippet', absint($_POST['aipc_max_doc_snippet'] ?? 500));
            
            // Token pricing
            update_option('aipc_token_input_rate_per_1k', isset($_POST['aipc_token_input_rate_per_1k']) ? floatval($_POST['aipc_token_input_rate_per_1k']) : 0);
            update_option('aipc_token_output_rate_per_1k', isset($_POST['aipc_token_output_rate_per_1k']) ? floatval($_POST['aipc_token_output_rate_per_1k']) : 0);

            // Features - moved skin test to correct feature check below
            update_option('aipc_enable_product_recommendations', isset($_POST['aipc_enable_product_recommendations']) ? 1 : 0);
            
            // Content Sources (Business+ feature)
            $use_woo_source = isset($_POST['aipc_source_use_woocommerce']) ? 1 : 0;
            update_option('aipc_source_use_woocommerce', $use_woo_source);

            if (isset($_POST['aipc_content_sources']) && is_array($_POST['aipc_content_sources'])) {
                // Process content sources (same logic as before)
                $raw_sources = $_POST['aipc_content_sources'];
                $cleaned = [];
                foreach ($raw_sources as $pt => $cfg) {
                    $pt_key = sanitize_key($pt);
                    if (!$pt_key) { continue; }
                    $enabled = !empty($cfg['enabled']);
                    $max_items = isset($cfg['max_items']) ? max(1, absint($cfg['max_items'])) : 5;
                    $max_chars = isset($cfg['max_chars']) ? max(50, absint($cfg['max_chars'])) : 400;
                    $orderby = isset($cfg['orderby']) && in_array($cfg['orderby'], ['date','title'], true) ? $cfg['orderby'] : 'date';
                    $order = isset($cfg['order']) && strtoupper($cfg['order']) === 'ASC' ? 'ASC' : 'DESC';
                    $fields = isset($cfg['fields']) && is_array($cfg['fields']) ? array_intersect(array_keys($cfg['fields']), ['title','excerpt','content','link']) : ['title','excerpt','link'];
                    $meta_keys = [];
                    if (!empty($cfg['meta_keys']) && is_string($cfg['meta_keys'])) {
                        $parts = array_filter(array_map('trim', explode(',', $cfg['meta_keys'])));
                        foreach ($parts as $mk) { $meta_keys[] = sanitize_key($mk); }
                    }
                    $tax = [];
                    if (!empty($cfg['tax']) && is_array($cfg['tax'])) {
                        foreach ($cfg['tax'] as $tax_name => $terms) {
                            $tkey = sanitize_key($tax_name);
                            if (!$tkey) continue;
                            if (is_string($terms)) {
                                $terms = array_filter(array_map('trim', explode(',', $terms)));
                            }
                            if (!is_array($terms)) $terms = [];
                            $tax[$tkey] = array_map('sanitize_title', $terms);
                        }
                    }
                    $template = '';
                    if (isset($cfg['template']) && is_string($cfg['template'])) {
                        $template = sanitize_textarea_field($cfg['template']);
                    }
                    $cleaned[$pt_key] = [
                        'enabled' => (bool)$enabled,
                        'max_items' => $max_items,
                        'max_chars' => $max_chars,
                        'orderby' => $orderby,
                        'order' => $order,
                        'fields' => array_values($fields),
                        'meta_keys' => $meta_keys,
                        'tax' => $tax,
                        'template' => $template,
                    ];
                }
                update_option('aipc_content_sources', $cleaned);
            }
            
            // WooCommerce settings (Business+ feature)
            update_option('aipc_woocommerce_enabled', isset($_POST['aipc_woocommerce_enabled']) ? 1 : 0);
            update_option('aipc_woocommerce_auto_sync', isset($_POST['aipc_woocommerce_auto_sync']) ? 1 : 0);
            update_option('aipc_woocommerce_show_cart', isset($_POST['aipc_woocommerce_show_cart']) ? 1 : 0);
            update_option('aipc_woocommerce_ingredients_field', sanitize_text_field($_POST['aipc_woocommerce_ingredients_field'] ?? '_ingredients'));
            update_option('aipc_woocommerce_skin_types_field', sanitize_text_field($_POST['aipc_woocommerce_skin_types_field'] ?? '_skin_types'));
        }
        
        // Skin test settings (Business+ feature)
        if ($license_check->has_feature('custom_skin_test')) {
            // Move enable/disable setting here where it belongs
            update_option('aipc_enable_skin_test', isset($_POST['aipc_enable_skin_test']) ? 1 : 0);
            
            if (isset($_POST['aipc_skin_test_questions'])) {
                update_option('aipc_skin_test_questions', is_string($_POST['aipc_skin_test_questions']) ? wp_unslash($_POST['aipc_skin_test_questions']) : '');
            }
            if (isset($_POST['aipc_skin_test_mapping'])) {
                update_option('aipc_skin_test_mapping', is_string($_POST['aipc_skin_test_mapping']) ? wp_unslash($_POST['aipc_skin_test_mapping']) : '');
            }
        }
    } // End license check
    
    // Show appropriate success message
    if ($is_license_active) {
        echo '<div class="notice notice-success"><p>' . __('Instellingen opgeslagen!', 'ai-product-chatbot') . '</p></div>';
    } else {
        echo '<div class="notice notice-success"><p>' . __('Licentie server URLs opgeslagen! Activeer nu je licentie om alle instellingen te ontgrendelen.', 'ai-product-chatbot') . '</p></div>';
    }
}

// Load current settings
$license = AIPC_License::getInstance();
$current_tier = $license->get_current_tier();
$is_active = $license->is_active();


$api_key = get_option('aipc_openai_api_key', '');
$model = get_option('aipc_openai_model', 'gpt-4');
$provider = get_option('aipc_api_provider', 'openai');
$api_base = get_option('aipc_api_base', '');
$title = get_option('aipc_chatbot_title', 'AI Product Assistant');
$welcome_message = get_option('aipc_chatbot_welcome_message', 'Hallo! Ik ben je AI product assistant. Hoe kan ik je vandaag helpen?');
$fallback_message = get_option('aipc_fallback_message', 'Bedankt voor je bericht! Ik ben hier om je te helpen met productaanbevelingen, ingrediënten uitleg en persoonlijke adviezen. Kun je me meer vertellen over wat je zoekt?');
$position = get_option('aipc_chatbot_position', 'bottom-right');
$theme = get_option('aipc_chatbot_theme', 'modern');
$tone = get_option('aipc_chatbot_tone', 'neutral');

// Privacy/AVG settings
$store_conversations = get_option('aipc_store_conversations', true);
$retention_days = get_option('aipc_retention_days', 90);
$mask_email = get_option('aipc_mask_email', true);
$mask_phone = get_option('aipc_mask_phone', true);
$system_prompt = get_option('aipc_system_prompt', '');
$greet_with_products = get_option('aipc_greet_with_products', '');
$greet_without_products = get_option('aipc_greet_without_products', '');
$max_faq_items = get_option('aipc_max_faq_items', 20);
$max_doc_snippet = get_option('aipc_max_doc_snippet', 500);
$token_in_rate = get_option('aipc_token_input_rate_per_1k', 0);
$token_out_rate = get_option('aipc_token_output_rate_per_1k', 0);

// AVG status helpers
$auto_purge_scheduled = (bool) wp_next_scheduled('aipc_daily_purge');
$email_masking_on = (bool)$mask_email;
$phone_masking_on = (bool)$mask_phone;
$ip_anonymized = get_option('aipc_anonymize_monitoring_ips', true);
$storage_ok = (!$store_conversations) || ($store_conversations && $retention_days > 0 && $auto_purge_scheduled);
$avg_pass = ($email_masking_on && $phone_masking_on && $storage_ok && $ip_anonymized);
?>

<div class="wrap">
    <h1><?php _e('AI Chatbot Instellingen', 'ai-product-chatbot'); ?></h1>
    
    <?php if (!$is_active): ?>
        <!-- License Configuration Mode -->
        <div class="notice notice-info">
            <p>
                <strong><?php _e('🔒 Licentie Configuratie', 'ai-product-chatbot'); ?></strong><br>
                <?php _e('Configureer eerst je licentie server en activeer een licentie om alle instellingen te ontgrendelen.', 'ai-product-chatbot'); ?>
            </p>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('aipc_save_settings', 'aipc_settings_nonce'); ?>
            
            <div class="aipc-settings-section">
                <h2><?php _e('Licentie Configuratie & Status', 'ai-product-chatbot'); ?></h2>
                
                <?php $license_info = $license->get_license_info(); ?>
                <?php $grace = $license->get_grace_status(); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Huidige Status', 'ai-product-chatbot'); ?></th>
                        <td>
                            <strong style="color: #d63638;">  
                                <?php echo ucfirst($current_tier); ?> 
                                <span class="dashicons dashicons-warning"></span> <?php _e('Niet geactiveerd', 'ai-product-chatbot'); ?>
                            </strong>
                            <p class="description">
                                <?php _e('Activeer een licentie om premium features zoals AI-antwoorden, FAQ beheer en WooCommerce sync te ontgrendelen.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Licentie Server URL', 'ai-product-chatbot'); ?></th>
                        <td>
                            <input type="url" name="aipc_license_server_url" value="<?php echo esc_attr(get_option('aipc_license_server_url', '')); ?>" class="regular-text" placeholder="https://your-license-server.com" required />
                            <p class="description">
                                <?php _e('URL van je licentie server (waar de license manager plugin geïnstalleerd is). <strong>Verplicht</strong> voor licentie activatie.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Upgrade/Koop URL', 'ai-product-chatbot'); ?></th>
                        <td>
                            <input type="url" name="aipc_upgrade_url" value="<?php echo esc_attr(get_option('aipc_upgrade_url', 'https://your-website.com/upgrade')); ?>" class="regular-text" placeholder="https://your-website.com/upgrade" />
                            <p class="description">
                                <?php _e('URL waar gebruikers naartoe worden gestuurd om een licentie te kopen of te upgraden.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Licentie Key', 'ai-product-chatbot'); ?></th>
                        <td>
                            <input type="text" id="aipc_license_key" placeholder="AIPC-BUSINESS-12345678" class="regular-text" />
                            <button type="button" id="aipc_activate_license" class="button button-primary"><?php _e('Activeren', 'ai-product-chatbot'); ?></button>
                            <p class="description">
                                <?php _e('Voer je licentie key in om premium features te ontgrendelen.', 'ai-product-chatbot'); ?>
                                <a href="<?php echo esc_url(get_option('aipc_upgrade_url', 'https://your-website.com/purchase')); ?>" target="_blank"><?php _e('Koop een licentie', 'ai-product-chatbot'); ?></a>
                            </p>
                            <div id="aipc_license_result" style="margin-top: 10px;"></div>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Server URLs Opslaan', 'ai-product-chatbot')); ?>
            </div>
        </form>
        
    <?php else: ?>
        <!-- Full Settings Mode - Clean License-based Structure -->
        <form method="post" action="">
            <?php wp_nonce_field('aipc_save_settings', 'aipc_settings_nonce'); ?>
            
            <!-- ===== BASIC LICENSE FEATURES ===== -->
            <div class="aipc-settings-section">
                <h2>
                    <span class="dashicons dashicons-admin-generic" style="color: #46b450;"></span>
                    <?php _e('Basis Chatbot Instellingen', 'ai-product-chatbot'); ?>
                    <small style="color: #46b450; font-weight: normal;"><?php _e('(Beschikbaar met alle licenties)', 'ai-product-chatbot'); ?></small>
                </h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="aipc_chatbot_enabled"><?php _e('Chatbot Inschakelen', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="aipc_chatbot_enabled" name="aipc_chatbot_enabled" 
                                   value="1" <?php checked(get_option('aipc_chatbot_enabled', true)); ?> />
                            <label for="aipc_chatbot_enabled"><?php _e('Chatbot inschakelen op alle pagina\'s', 'ai-product-chatbot'); ?></label>
                            <p class="description">
                                <?php _e('De chatbot verschijnt standaard rechtsonder op alle frontend pagina\'s.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_chatbot_title"><?php _e('Chatbot Titel', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="aipc_chatbot_title" name="aipc_chatbot_title" 
                                   value="<?php echo esc_attr($title); ?>" class="regular-text" />
                            <p class="description">
                                <?php _e('De titel die wordt getoond in de chatbot interface.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_chatbot_welcome_message"><?php _e('Welkomstbericht', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <textarea id="aipc_chatbot_welcome_message" name="aipc_chatbot_welcome_message" 
                                      rows="3" cols="50" class="large-text"><?php echo esc_textarea($welcome_message); ?></textarea>
                            <p class="description">
                                <?php _e('Het eerste bericht dat gebruikers zien wanneer ze de chatbot openen.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_chatbot_position"><?php _e('Positie', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <select id="aipc_chatbot_position" name="aipc_chatbot_position">
                                <option value="bottom-right" <?php selected($position, 'bottom-right'); ?>><?php _e('Rechtsonder', 'ai-product-chatbot'); ?></option>
                                <option value="bottom-left" <?php selected($position, 'bottom-left'); ?>><?php _e('Linksonder', 'ai-product-chatbot'); ?></option>
                                <option value="top-right" <?php selected($position, 'top-right'); ?>><?php _e('Rechtsboven', 'ai-product-chatbot'); ?></option>
                                <option value="top-left" <?php selected($position, 'top-left'); ?>><?php _e('Linksboven', 'ai-product-chatbot'); ?></option>
                            </select>
                            <p class="description">
                                <?php _e('Waar de chatbot knop moet verschijnen op de website.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_chatbot_theme"><?php _e('Thema', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <select id="aipc_chatbot_theme" name="aipc_chatbot_theme">
                                <option value="modern" <?php selected($theme, 'modern'); ?>><?php _e('Modern (Standaard)', 'ai-product-chatbot'); ?></option>
                                <option value="minimal" <?php selected($theme, 'minimal'); ?>><?php _e('Minimaal', 'ai-product-chatbot'); ?></option>
                                <option value="colorful" <?php selected($theme, 'colorful'); ?>><?php _e('Kleurrijk', 'ai-product-chatbot'); ?></option>
                                <option value="custom" <?php selected($theme, 'custom'); ?>><?php _e('Custom (Eigen kleuren)', 'ai-product-chatbot'); ?></option>
                            </select>
                            <p class="description">
                                <?php _e('Het visuele thema van de chatbot. Kies "Custom" om eigen kleuren in te stellen.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Custom Color Settings (only visible when custom theme is selected) -->
                    <tr id="custom-colors-section" style="display: none;">
                        <th scope="row">
                            <label><?php _e('🎨 Custom Kleuren', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <div class="aipc-color-settings">
                                <div class="aipc-color-grid">
                                    <div class="aipc-color-item">
                                        <label for="aipc_primary_color"><?php _e('Primaire kleur', 'ai-product-chatbot'); ?></label>
                                        <input type="color" id="aipc_primary_color" name="aipc_primary_color" 
                                               value="<?php echo esc_attr(get_option('aipc_primary_color', '#667eea')); ?>" />
                                        <small><?php _e('Hoofdkleur (toggle button, headers, send button)', 'ai-product-chatbot'); ?></small>
                                    </div>
                                    
                                    <div class="aipc-color-item">
                                        <label for="aipc_secondary_color"><?php _e('Secundaire kleur', 'ai-product-chatbot'); ?></label>
                                        <input type="color" id="aipc_secondary_color" name="aipc_secondary_color" 
                                               value="<?php echo esc_attr(get_option('aipc_secondary_color', '#764ba2')); ?>" />
                                        <small><?php _e('Gradient eindkleur', 'ai-product-chatbot'); ?></small>
                                    </div>
                                    
                                    <div class="aipc-color-item">
                                        <label for="aipc_background_color"><?php _e('Achtergrondkleur', 'ai-product-chatbot'); ?></label>
                                        <input type="color" id="aipc_background_color" name="aipc_background_color" 
                                               value="<?php echo esc_attr(get_option('aipc_background_color', '#f8f9fa')); ?>" />
                                        <small><?php _e('Chat berichten gebied', 'ai-product-chatbot'); ?></small>
                                    </div>
                                    
                                    <div class="aipc-color-item">
                                        <label for="aipc_text_color"><?php _e('Tekstkleur', 'ai-product-chatbot'); ?></label>
                                        <input type="color" id="aipc_text_color" name="aipc_text_color" 
                                               value="<?php echo esc_attr(get_option('aipc_text_color', '#333333')); ?>" />
                                        <small><?php _e('Hoofdtekst kleur', 'ai-product-chatbot'); ?></small>
                                    </div>
                                    
                                    <div class="aipc-color-item">
                                        <label for="aipc_user_message_color"><?php _e('Gebruiker bericht', 'ai-product-chatbot'); ?></label>
                                        <input type="color" id="aipc_user_message_color" name="aipc_user_message_color" 
                                               value="<?php echo esc_attr(get_option('aipc_user_message_color', '#667eea')); ?>" />
                                        <small><?php _e('Achtergrond van gebruiker berichten', 'ai-product-chatbot'); ?></small>
                                    </div>
                                    
                                    <div class="aipc-color-item">
                                        <label for="aipc_assistant_message_color"><?php _e('Assistant bericht', 'ai-product-chatbot'); ?></label>
                                        <input type="color" id="aipc_assistant_message_color" name="aipc_assistant_message_color" 
                                               value="<?php echo esc_attr(get_option('aipc_assistant_message_color', '#ffffff')); ?>" />
                                        <small><?php _e('Achtergrond van assistant berichten', 'ai-product-chatbot'); ?></small>
                                    </div>
                                    
                                    <div class="aipc-color-item">
                                        <label for="aipc_header_text_color"><?php _e('Header tekst', 'ai-product-chatbot'); ?></label>
                                        <input type="color" id="aipc_header_text_color" name="aipc_header_text_color" 
                                               value="<?php echo esc_attr(get_option('aipc_header_text_color', '#ffffff')); ?>" />
                                        <small><?php _e('Titel en status tekst in header', 'ai-product-chatbot'); ?></small>
                                    </div>
                                    
                                    <div class="aipc-color-item">
                                        <label for="aipc_input_background_color"><?php _e('Input achtergrond', 'ai-product-chatbot'); ?></label>
                                        <input type="color" id="aipc_input_background_color" name="aipc_input_background_color" 
                                               value="<?php echo esc_attr(get_option('aipc_input_background_color', '#f8f9fa')); ?>" />
                                        <small><?php _e('Achtergrond van het input veld', 'ai-product-chatbot'); ?></small>
                                    </div>
                                    
                                    <div class="aipc-color-item">
                                        <label for="aipc_input_border_color"><?php _e('Input rand', 'ai-product-chatbot'); ?></label>
                                        <input type="color" id="aipc_input_border_color" name="aipc_input_border_color" 
                                               value="<?php echo esc_attr(get_option('aipc_input_border_color', '#e9ecef')); ?>" />
                                        <small><?php _e('Rand kleur van het input veld', 'ai-product-chatbot'); ?></small>
                                    </div>
                                </div>
                                
                                <div class="aipc-input-styling" style="margin-top: 20px;">
                                    <h4><?php _e('⌨️ Input Field Styling', 'ai-product-chatbot'); ?></h4>
                                    <div class="aipc-input-controls">
                                        <div class="aipc-control-row">
                                            <label for="aipc_input_padding"><?php _e('Padding (px)', 'ai-product-chatbot'); ?></label>
                                            <input type="number" id="aipc_input_padding" name="aipc_input_padding" 
                                                   value="<?php echo esc_attr(get_option('aipc_input_padding', '5')); ?>" 
                                                   min="0" max="20" step="1" style="width: 80px;" />
                                        </div>
                                        
                                        <div class="aipc-control-row">
                                            <label for="aipc_input_border_radius"><?php _e('Border Radius (px)', 'ai-product-chatbot'); ?></label>
                                            <input type="number" id="aipc_input_border_radius" name="aipc_input_border_radius" 
                                                   value="<?php echo esc_attr(get_option('aipc_input_border_radius', '25')); ?>" 
                                                   min="0" max="50" step="1" style="width: 80px;" />
                                        </div>
                                        
                                        <div class="aipc-control-row">
                                            <label for="aipc_input_border_width"><?php _e('Border Width (px)', 'ai-product-chatbot'); ?></label>
                                            <input type="number" id="aipc_input_border_width" name="aipc_input_border_width" 
                                                   value="<?php echo esc_attr(get_option('aipc_input_border_width', '1')); ?>" 
                                                   min="0" max="5" step="1" style="width: 80px;" />
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="aipc-icon-settings" style="margin-top: 20px;">
                                    <h4><?php _e('🚀 Chatbot Icon', 'ai-product-chatbot'); ?></h4>
                                    <div class="aipc-icon-grid">
                                        <?php 
                                        $current_icon = get_option('aipc_chatbot_icon', 'robot');
                                        $icons = [
                                            'robot' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM12 8.5C13.66 8.5 15 9.84 15 11.5V17.5C15 19.16 13.66 20.5 12 20.5S9 19.16 9 17.5V11.5C9 9.84 10.34 8.5 12 8.5ZM7.5 11C7.78 11 8 11.22 8 11.5V13.5C8 13.78 7.78 14 7.5 14S7 13.78 7 13.5V11.5C7 11.22 7.22 11 7.5 11ZM16.5 11C16.78 11 17 11.22 17 11.5V13.5C17 13.78 16.78 14 16.5 14S16 13.78 16 13.5V11.5C16 11.22 16.22 11 16.5 11Z"/></svg>',
                                            'chat' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM20 16H5.17L4 17.17V4H20V16Z"/></svg>',
                                            'support' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM12 20C7.59 20 4 16.41 4 12S7.59 4 12 4 20 7.59 20 12 16.41 20 12 20ZM12 6C8.69 6 6 8.69 6 12S8.69 18 12 18 18 15.31 18 12 15.31 6 12 6ZM12 8C13.1 8 14 8.9 14 10S13.1 12 12 12 10 11.1 10 10 10.9 8 12 8ZM7 19H9V21H7V19ZM15 19H17V21H15V19Z"/></svg>',
                                            'help' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12 17.52 2 12 2ZM13 19H11V17H13V19ZM15.07 11.25L14.17 12.17C13.45 12.9 13 13.5 13 15H11V14.5C11 13.4 11.45 12.4 12.17 11.67L13.41 10.41C13.78 10.05 14 9.55 14 9C14 7.9 13.1 7 12 7S10 7.9 10 9H8C8 6.79 9.79 5 12 5S16 6.79 16 9C16 9.88 15.64 10.67 15.07 11.25Z"/></svg>',
                                            'message' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4C2.9 4 2 4.9 2 6V18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V6C22 4.9 21.1 4 20 4ZM20 8L12 13L4 8V6L12 11L20 6V8Z"/></svg>',
                                            'phone' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79C8.06 13.62 10.38 15.94 13.21 17.38L15.41 15.18C15.69 14.9 16.08 14.82 16.43 14.93C17.55 15.3 18.75 15.5 20 15.5C20.55 15.5 21 15.95 21 16.5V20C21 20.55 20.55 21 20 21C10.61 21 3 13.39 3 4C3 3.45 3.45 3 4 3H7.5C8.05 3 8.5 3.45 8.5 4C8.5 5.25 8.7 6.45 9.07 7.57C9.18 7.92 9.1 8.31 8.82 8.59L6.62 10.79Z"/></svg>',
                                            'star' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>',
                                            'heart' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35L10.55 20.03C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3C9.24 3 10.91 3.81 12 5.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5 22 12.28 18.6 15.36 13.45 20.04L12 21.35Z"/></svg>',
                                            'lightbulb' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M9 21C9 21.55 9.45 22 10 22H14C14.55 22 15 21.55 15 21V20H9V21ZM12 2C8.14 2 5 5.14 5 9C5 11.38 6.19 13.47 8 14.74V17C8 17.55 8.45 18 9 18H15C15.55 18 16 17.55 16 17V14.74C17.81 13.47 19 11.38 19 9C19 5.14 15.86 2 12 2Z"/></svg>',
                                            'gear' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 15.5C10.07 15.5 8.5 13.93 8.5 12S10.07 8.5 12 8.5 15.5 10.07 15.5 12 13.93 15.5 12 15.5ZM19.43 12.98C19.47 12.66 19.5 12.34 19.5 12S19.47 11.34 19.43 11.02L21.54 9.37C21.73 9.22 21.78 8.95 21.66 8.73L19.66 5.27C19.54 5.05 19.27 4.97 19.05 5.05L16.56 6.05C16.04 5.65 15.48 5.32 14.87 5.07L14.49 2.42C14.46 2.18 14.25 2 14 2H10C9.75 2 9.54 2.18 9.51 2.42L9.13 5.07C8.52 5.32 7.96 5.66 7.44 6.05L4.95 5.05C4.72 4.96 4.46 5.05 4.34 5.27L2.34 8.73C2.21 8.95 2.27 9.22 2.46 9.37L4.57 11.02C4.53 11.34 4.5 11.67 4.5 12S4.53 12.66 4.57 12.98L2.46 14.63C2.27 14.78 2.21 15.05 2.34 15.27L4.34 18.73C4.46 18.95 4.73 19.03 4.95 18.95L7.44 17.95C7.96 18.35 8.52 18.68 9.13 18.93L9.51 21.58C9.54 21.82 9.75 22 10 22H14C14.25 22 14.46 21.82 14.49 21.58L14.87 18.93C15.48 18.68 16.04 18.34 16.56 17.95L19.05 18.95C19.28 19.04 19.54 18.95 19.66 18.73L21.66 15.27C21.78 15.05 21.73 14.78 21.54 14.63L19.43 12.98Z"/></svg>',
                                            'shield' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 1ZM10 17L6 13L7.41 11.59L10 14.17L16.59 7.58L18 9L10 17Z"/></svg>',
                                            'rocket' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C12 2 21 6.5 21 12C21 12 21 12 21 12H12V2ZM11 4V11H4C4 11 4 11 4 11C4 6.5 11 4 11 4ZM12 13H21C21 17.5 12 22 12 22V13ZM4 13H11V20C11 20 4 17.5 4 13Z"/></svg>',
                                            'diamond' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M6 2L2 8L12 22L22 8L18 2H6ZM6.5 4H8.5L7 7H4L6.5 4ZM11 4H13V7H11V4ZM15.5 4H17.5L20 7H17L15.5 4ZM5 9H8L12 18L5 9ZM10 9H14L12 16L10 9ZM16 9H19L12 18L16 9Z"/></svg>',
                                            'crown' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M5 16L3 5L8.5 10L12 4L15.5 10L21 5L19 16H5ZM19 19C19 19.6 18.6 20 18 20H6C5.4 20 5 19.6 5 19V18H19V19Z"/></svg>',
                                            'compass' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12S7.59 4 12 4 20 7.59 20 12 16.41 20 12 20ZM12 7C9.24 7 7 9.24 7 12S9.24 17 12 17 17 14.76 17 12 14.76 7 12 7ZM15.5 8.5L13 11L10.5 13.5L13 11L15.5 8.5Z"/></svg>',
                                            'magic' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M7.5 5.6L5 7L6.4 4.5L5 2L7.5 3.4L10 2L8.6 4.5L10 7L7.5 5.6ZM19.5 15.4L22 14L20.6 16.5L22 19L19.5 17.6L17 19L18.4 16.5L17 14L19.5 15.4ZM22 2L20.6 4.5L22 7L19.5 5.6L17 7L18.4 4.5L17 2L19.5 3.4L22 2ZM13.34 12.78L15.78 10.34L13.66 8.22L11.22 10.66L13.34 12.78ZM14.37 7.29L16.71 9.63C17.1 10.02 17.1 10.65 16.71 11.04L11.04 16.71C10.65 17.1 10.02 17.1 9.63 16.71L7.29 14.37C6.9 13.98 6.9 13.35 7.29 12.96L12.96 7.29C13.35 6.9 13.98 6.9 14.37 7.29Z"/></svg>'
                                        ];
                                        foreach ($icons as $key => $svg): ?>
                                            <label class="aipc-icon-option" title="<?php echo esc_attr(ucfirst(str_replace('_', ' ', $key))); ?>">
                                                <input type="radio" name="aipc_chatbot_icon" value="<?php echo esc_attr($key); ?>" 
                                                       <?php checked($current_icon, $key); ?> />
                                                <span class="aipc-icon-preview"><?php echo $svg; ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="description"><?php _e('Kies een icon voor de chatbot toggle button en header.', 'ai-product-chatbot'); ?></p>
                                </div>
                                
                                <div class="aipc-color-preview" style="margin-top: 20px;">
                                    <h4><?php _e('👀 Live Preview', 'ai-product-chatbot'); ?></h4>
                                    <div class="aipc-preview-chatbot">
                                        <div class="aipc-preview-toggle">
                                            <span class="aipc-preview-icon">🤖</span>
                                            <span class="aipc-preview-text">Chat met ons</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Style for color customization interface -->
            <style>
            .aipc-color-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-top: 10px;
            }
            
            .aipc-color-item {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .aipc-color-item label {
                font-weight: 600;
                color: #1d2327;
            }
            
            .aipc-color-item input[type="color"] {
                width: 60px;
                height: 40px;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                cursor: pointer;
            }
            
            .aipc-color-item small {
                color: #646970;
                font-style: italic;
            }
            
            .aipc-icon-grid {
                display: grid;
                grid-template-columns: repeat(8, 1fr);
                gap: 10px;
                margin-top: 10px;
            }
            
            .aipc-icon-option {
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                padding: 10px;
                border: 2px solid transparent;
                border-radius: 8px;
                transition: all 0.2s ease;
            }
            
            .aipc-icon-option:hover {
                background-color: #f0f0f1;
            }
            
            .aipc-icon-option input {
                display: none;
            }
            
            .aipc-icon-option input:checked + .aipc-icon-preview {
                transform: scale(1.2);
            }
            
            .aipc-icon-option input:checked {
                border-color: #0073aa;
            }
            
            .aipc-icon-preview {
                font-size: 24px;
                transition: transform 0.2s ease;
            }
            
            .aipc-preview-chatbot {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 12px;
                display: inline-block;
            }
            
            .aipc-preview-toggle {
                display: flex;
                align-items: center;
                background: var(--aipc-primary-gradient, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
                color: var(--aipc-header-text-color, white);
                padding: 12px 20px;
                border-radius: 50px;
                font-size: 14px;
                font-weight: 500;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                min-width: 180px;
            }
            
            .aipc-preview-icon {
                margin-right: 10px;
                font-size: 18px;
            }
            
            .aipc-color-settings h4 {
                margin-top: 0;
                margin-bottom: 10px;
                color: #1d2327;
            }
            
            .aipc-input-controls {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 15px;
                margin-top: 10px;
            }
            
            .aipc-control-row {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .aipc-control-row label {
                font-weight: 600;
                color: #1d2327;
                font-size: 13px;
            }
            
            .aipc-control-row input[type="number"] {
                padding: 6px 8px;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                font-size: 14px;
            }
            </style>
            
            <!-- JavaScript for color customization -->
            <script>
            jQuery(document).ready(function($) {
                // Show/hide custom colors section based on theme selection
                function toggleCustomColors() {
                    const theme = $('#aipc_chatbot_theme').val();
                    if (theme === 'custom') {
                        $('#custom-colors-section').show();
                    } else {
                        $('#custom-colors-section').hide();
                    }
                }
                
                // Initialize on page load
                toggleCustomColors();
                
                // Toggle on theme change
                $('#aipc_chatbot_theme').on('change', toggleCustomColors);
                
                // SVG icon map
                const svgIcons = {
                    'robot': '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM12 8.5C13.66 8.5 15 9.84 15 11.5V17.5C15 19.16 13.66 20.5 12 20.5S9 19.16 9 17.5V11.5C9 9.84 10.34 8.5 12 8.5ZM7.5 11C7.78 11 8 11.22 8 11.5V13.5C8 13.78 7.78 14 7.5 14S7 13.78 7 13.5V11.5C7 11.22 7.22 11 7.5 11ZM16.5 11C16.78 11 17 11.22 17 11.5V13.5C17 13.78 16.78 14 16.5 14S16 13.78 16 13.5V11.5C16 11.22 16.22 11 16.5 11Z"/></svg>',
                    'chat': '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM20 16H5.17L4 17.17V4H20V16Z"/></svg>',
                    'support': '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM12 20C7.59 20 4 16.41 4 12S7.59 4 12 4 20 7.59 20 12 16.41 20 12 20ZM12 6C8.69 6 6 8.69 6 12S8.69 18 12 18 18 15.31 18 12 15.31 6 12 6ZM12 8C13.1 8 14 8.9 14 10S13.1 12 12 12 10 11.1 10 10 10.9 8 12 8ZM7 19H9V21H7V19ZM15 19H17V21H15V19Z"/></svg>',
                    'help': '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12 17.52 2 12 2ZM13 19H11V17H13V19ZM15.07 11.25L14.17 12.17C13.45 12.9 13 13.5 13 15H11V14.5C11 13.4 11.45 12.4 12.17 11.67L13.41 10.41C13.78 10.05 14 9.55 14 9C14 7.9 13.1 7 12 7S10 7.9 10 9H8C8 6.79 9.79 5 12 5S16 6.79 16 9C16 9.88 15.64 10.67 15.07 11.25Z"/></svg>',
                    'message': '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4C2.9 4 2 4.9 2 6V18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V6C22 4.9 21.1 4 20 4ZM20 8L12 13L4 8V6L12 11L20 6V8Z"/></svg>',
                    'phone': '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79C8.06 13.62 10.38 15.94 13.21 17.38L15.41 15.18C15.69 14.9 16.08 14.82 16.43 14.93C17.55 15.3 18.75 15.5 20 15.5C20.55 15.5 21 15.95 21 16.5V20C21 20.55 20.55 21 20 21C10.61 21 3 13.39 3 4C3 3.45 3.45 3 4 3H7.5C8.05 3 8.5 3.45 8.5 4C8.5 5.25 8.7 6.45 9.07 7.57C9.18 7.92 9.1 8.31 8.82 8.59L6.62 10.79Z"/></svg>',
                    'star': '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>',
                    'heart': '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35L10.55 20.03C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3C9.24 3 10.91 3.81 12 5.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5 22 12.28 18.6 15.36 13.45 20.04L12 21.35Z"/></svg>',
                    'lightbulb': '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M9 21C9 21.55 9.45 22 10 22H14C14.55 22 15 21.55 15 21V20H9V21ZM12 2C8.14 2 5 5.14 5 9C5 11.38 6.19 13.47 8 14.74V17C8 17.55 8.45 18 9 18H15C15.55 18 16 17.55 16 17V14.74C17.81 13.47 19 11.38 19 9C19 5.14 15.86 2 12 2Z"/></svg>',
                    'gear': '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 15.5C10.07 15.5 8.5 13.93 8.5 12S10.07 8.5 12 8.5 15.5 10.07 15.5 12 13.93 15.5 12 15.5ZM19.43 12.98C19.47 12.66 19.5 12.34 19.5 12S19.47 11.34 19.43 11.02L21.54 9.37C21.73 9.22 21.78 8.95 21.66 8.73L19.66 5.27C19.54 5.05 19.27 4.97 19.05 5.05L16.56 6.05C16.04 5.65 15.48 5.32 14.87 5.07L14.49 2.42C14.46 2.18 14.25 2 14 2H10C9.75 2 9.54 2.18 9.51 2.42L9.13 5.07C8.52 5.32 7.96 5.66 7.44 6.05L4.95 5.05C4.72 4.96 4.46 5.05 4.34 5.27L2.34 8.73C2.21 8.95 2.27 9.22 2.46 9.37L4.57 11.02C4.53 11.34 4.5 11.67 4.5 12S4.53 12.66 4.57 12.98L2.46 14.63C2.27 14.78 2.21 15.05 2.34 15.27L4.34 18.73C4.46 18.95 4.73 19.03 4.95 18.95L7.44 17.95C7.96 18.35 8.52 18.68 9.13 18.93L9.51 21.58C9.54 21.82 9.75 22 10 22H14C14.25 22 14.46 21.82 14.49 21.58L14.87 18.93C15.48 18.68 16.04 18.34 16.56 17.95L19.05 18.95C19.28 19.04 19.54 18.95 19.66 18.73L21.66 15.27C21.78 15.05 21.73 14.78 21.54 14.63L19.43 12.98Z"/></svg>',
                    'shield': '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 1ZM10 17L6 13L7.41 11.59L10 14.17L16.59 7.58L18 9L10 17Z"/></svg>',
                    'rocket': '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C12 2 21 6.5 21 12C21 12 21 12 21 12H12V2ZM11 4V11H4C4 11 4 11 4 11C4 6.5 11 4 11 4ZM12 13H21C21 17.5 12 22 12 22V13ZM4 13H11V20C11 20 4 17.5 4 13Z"/></svg>',
                    'diamond': '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M6 2L2 8L12 22L22 8L18 2H6ZM6.5 4H8.5L7 7H4L6.5 4ZM11 4H13V7H11V4ZM15.5 4H17.5L20 7H17L15.5 4ZM5 9H8L12 18L5 9ZM10 9H14L12 16L10 9ZM16 9H19L12 18L16 9Z"/></svg>',
                    'crown': '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M5 16L3 5L8.5 10L12 4L15.5 10L21 5L19 16H5ZM19 19C19 19.6 18.6 20 18 20H6C5.4 20 5 19.6 5 19V18H19V19Z"/></svg>',
                    'compass': '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12S7.59 4 12 4 20 7.59 20 12 16.41 20 12 20ZM12 7C9.24 7 7 9.24 7 12S9.24 17 12 17 17 14.76 17 12 14.76 7 12 7ZM15.5 8.5L13 11L10.5 13.5L13 11L15.5 8.5Z"/></svg>',
                    'magic': '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M7.5 5.6L5 7L6.4 4.5L5 2L7.5 3.4L10 2L8.6 4.5L10 7L7.5 5.6ZM19.5 15.4L22 14L20.6 16.5L22 19L19.5 17.6L17 19L18.4 16.5L17 14L19.5 15.4ZM22 2L20.6 4.5L22 7L19.5 5.6L17 7L18.4 4.5L17 2L19.5 3.4L22 2ZM13.34 12.78L15.78 10.34L13.66 8.22L11.22 10.66L13.34 12.78ZM14.37 7.29L16.71 9.63C17.1 10.02 17.1 10.65 16.71 11.04L11.04 16.71C10.65 17.1 10.02 17.1 9.63 16.71L7.29 14.37C6.9 13.98 6.9 13.35 7.29 12.96L12.96 7.29C13.35 6.9 13.98 6.9 14.37 7.29Z"/></svg>'
                };
                
                // Live preview updates
                function updatePreview() {
                    const primaryColor = $('#aipc_primary_color').val();
                    const secondaryColor = $('#aipc_secondary_color').val();
                    const headerTextColor = $('#aipc_header_text_color').val();
                    const selectedIcon = $('input[name="aipc_chatbot_icon"]:checked').val();
                    
                    // Update CSS custom properties
                    document.documentElement.style.setProperty('--aipc-primary-gradient', 
                        `linear-gradient(135deg, ${primaryColor} 0%, ${secondaryColor} 100%)`);
                    document.documentElement.style.setProperty('--aipc-header-text-color', headerTextColor);
                    
                    // Update preview icon with SVG
                    const iconSvg = svgIcons[selectedIcon] || svgIcons['robot'];
                    $('.aipc-preview-icon').html(iconSvg);
                }
                
                // Update preview on color changes
                $('#aipc_primary_color, #aipc_secondary_color, #aipc_header_text_color, #aipc_input_background_color, #aipc_input_border_color').on('input', updatePreview);
                $('#aipc_input_padding, #aipc_input_border_radius, #aipc_input_border_width').on('input', updatePreview);
                $('input[name="aipc_chatbot_icon"]').on('change', updatePreview);
                
                // Initialize preview
                updatePreview();
            });
            </script>
            
            <!-- Upgrade to Business+ Section (only for Basic users) -->
            <?php if (!$license->has_feature('api_integrations')): ?>
            <div class="aipc-settings-section">
                <h2>
                    <span class="dashicons dashicons-unlock" style="color: #f0b849;"></span>
                    <?php _e('🚀 Upgrade naar Business+', 'ai-product-chatbot'); ?>
                </h2>
                <div class="notice notice-info inline" style="margin: 0 0 20px 0; padding: 20px; border-left: 4px solid #f0b849;">
                    <p><strong><?php _e('🔒 Ontgrendel Geavanceerde Features:', 'ai-product-chatbot'); ?></strong></p>
                    <ul style="margin: 10px 0 15px 20px; list-style: disc;">
                        <li><?php _e('AI & API Configuratie (OpenAI, OpenRouter)', 'ai-product-chatbot'); ?></li>
                        <li><?php _e('Custom AI Persona & Prompts', 'ai-product-chatbot'); ?></li>
                        <li><?php _e('Geavanceerde Content Sources', 'ai-product-chatbot'); ?></li>
                        <li><?php _e('Product Quiz & Recommendations', 'ai-product-chatbot'); ?></li>
                        <li><?php _e('WooCommerce Custom Fields', 'ai-product-chatbot'); ?></li>
                        <li><?php _e('Privacy & Analytics Instellingen', 'ai-product-chatbot'); ?></li>
                        <li><?php _e('Token Cost Tracking', 'ai-product-chatbot'); ?></li>
                    </ul>
                    <a href="<?php echo esc_url($license->generate_upgrade_url('business')); ?>" class="button button-primary" style="margin-top: 10px;">
                        <?php _e('🚀 Upgrade naar Business', 'ai-product-chatbot'); ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- ===== BUSINESS+ LICENSE FEATURES ===== -->
            <?php if ($license->has_feature('api_integrations')): ?>
            
            <!-- AI & API Configuration -->
            <div class="aipc-settings-section">
                <h2>
                    <span class="dashicons dashicons-admin-tools" style="color: #f0b849;"></span>
                    <?php _e('AI & API Configuratie', 'ai-product-chatbot'); ?>
                    <small style="color: #f0b849; font-weight: normal;"><?php _e('(Business+ Feature)', 'ai-product-chatbot'); ?></small>
                </h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="aipc_openai_api_key"><?php _e('API Key', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="aipc_openai_api_key" name="aipc_openai_api_key" 
                                   value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                            <button type="button" id="aipc-test-api" class="button button-secondary" data-nonce="<?php echo esc_attr(wp_create_nonce('aipc_test_api')); ?>"><?php _e('Test API', 'ai-product-chatbot'); ?></button>
                            <p class="description">
                                <?php if ($provider === 'openrouter'): ?>
                                    <?php _e('Je OpenRouter API key. Krijg er een op', 'ai-product-chatbot'); ?> 
                                    <a href="https://openrouter.ai/keys" target="_blank">openrouter.ai/keys</a>
                                <?php else: ?>
                                    <?php _e('Je OpenAI API key. Krijg er een op', 'ai-product-chatbot'); ?> 
                                    <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>
                                <?php endif; ?>
                            </p>
                            <div id="aipc-api-test-result" style="margin-top: 10px;"></div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_api_provider"><?php _e('API Provider', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <select id="aipc_api_provider" name="aipc_api_provider">
                                <option value="openai" <?php selected($provider, 'openai'); ?>>OpenAI</option>
                                <option value="openrouter" <?php selected($provider, 'openrouter'); ?>>OpenRouter</option>
                            </select>
                            <p class="description">
                                <?php _e('Kies een provider. OpenRouter kan zonder creditcard werken.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_openai_model"><?php _e('Model', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <select id="aipc_openai_model" name="aipc_openai_model"></select>
                            <button type="button" id="aipc-fetch-models" class="button button-secondary" data-nonce="<?php echo esc_attr(wp_create_nonce('aipc_test_api')); ?>"><?php _e('Laad Modellen', 'ai-product-chatbot'); ?></button>
                            <p class="description">
                                <?php _e('Kies het model. Voor OpenRouter gebruik je hun modelnamen.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_api_base"><?php _e('API Base URL (optioneel)', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="aipc_api_base" name="aipc_api_base" value="<?php echo esc_attr($api_base); ?>" class="regular-text" placeholder="<?php echo esc_attr('https://api.openai.com/v1 of https://openrouter.ai/api/v1'); ?>" />
                            <p class="description">
                                <?php _e('Laat leeg voor standaard: OpenAI https://api.openai.com/v1, OpenRouter https://openrouter.ai/api/v1', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_show_api_errors"><?php _e('Toon API-fouten in chat (debug)', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="aipc_show_api_errors" name="aipc_show_api_errors" value="1" <?php checked(get_option('aipc_show_api_errors', false)); ?> />
                            <p class="description">
                                <?php _e('Toon ruwe API-fouten in de chat i.p.v. de fallback. Alleen voor debug.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="aipc_chatbot_tone"><?php _e('Toon van antwoord', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <select id="aipc_chatbot_tone" name="aipc_chatbot_tone">
                                <option value="neutral" <?php selected($tone, 'neutral'); ?>><?php _e('Neutraal (aanbevolen)', 'ai-product-chatbot'); ?></option>
                                <option value="formal" <?php selected($tone, 'formal'); ?>><?php _e('Formeel', 'ai-product-chatbot'); ?></option>
                                <option value="friendly" <?php selected($tone, 'friendly'); ?>><?php _e('Vriendelijk', 'ai-product-chatbot'); ?></option>
                            </select>
                            <p class="description"><?php _e('Bepaalt de schrijfstijl van de chatbot.', 'ai-product-chatbot'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_allow_judgement"><?php _e('Waardeoordelen toestaan', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="aipc_allow_judgement" name="aipc_allow_judgement" value="1" <?php checked(get_option('aipc_allow_judgement', false)); ?> />
                            <label for="aipc_allow_judgement"><?php _e('Sta subjectieve kwalificaties toe (bijv. "lang", "kort").', 'ai-product-chatbot'); ?></label>
                            <p class="description"><?php _e('Uitgeschakeld = feitelijk/neutraal; ingeschakeld = mag licht opiniërend antwoorden.', 'ai-product-chatbot'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_tips_style"><?php _e('Tips format', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <select id="aipc_tips_style" name="aipc_tips_style">
                                <option value="default" <?php selected(get_option('aipc_tips_style', 'default'), 'default'); ?>><?php _e('Standaard (vrij)', 'ai-product-chatbot'); ?></option>
                                <option value="bullets_3_short" <?php selected(get_option('aipc_tips_style', 'default'), 'bullets_3_short'); ?>><?php _e('3 bullets, 1 zin per bullet', 'ai-product-chatbot'); ?></option>
                                <option value="bullets_5_short" <?php selected(get_option('aipc_tips_style', 'default'), 'bullets_5_short'); ?>><?php _e('5 bullets, 1 zin per bullet', 'ai-product-chatbot'); ?></option>
                            </select>
                            <p class="description"><?php _e('Bepaalt het antwoordformat voor "tips"-achtige vragen wanneer AI samenvat.', 'ai-product-chatbot'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_allow_ai_ingredient_info"><?php _e('AI ingrediënt kennis gebruiken', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="aipc_allow_ai_ingredient_info" name="aipc_allow_ai_ingredient_info" value="1" <?php checked(get_option('aipc_allow_ai_ingredient_info', true)); ?> />
                            <label for="aipc_allow_ai_ingredient_info"><?php _e('Laat AI eigen kennis gebruiken voor ingrediënten die niet in database staan', 'ai-product-chatbot'); ?></label>
                            <p class="description"><?php _e('Uitgeschakeld = alleen database ingrediënten; ingeschakeld = AI mag algemene ingrediënt kennis delen als fallback.', 'ai-product-chatbot'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <!-- OpenRouter API Monitoring -->
                <h3 style="margin-top: 30px;"><?php _e('🔍 OpenRouter API Monitoring', 'ai-product-chatbot'); ?></h3>
                <p class="description"><?php _e('Controleer je OpenRouter API limieten en verbruik.', 'ai-product-chatbot'); ?></p>
                <p style="margin: 15px 0;">
                    <button type="button" id="aipc-openrouter-key-info" class="button button-secondary">
                        <?php _e('📊 Toon OpenRouter Limieten', 'ai-product-chatbot'); ?>
                    </button>
                    <button type="button" id="aipc-openrouter-free-usage" class="button button-secondary" style="margin-left: 10px;">
                        <?php _e('🆓 Toon Free Requests Vandaag', 'ai-product-chatbot'); ?>
                    </button>
                </p>
                <div id="aipc-openrouter-result" style="margin-top: 15px;"></div>
                <div id="aipc-openrouter-free-result" style="margin-top: 15px;"></div>
            </div>
            
            <!-- AI Persona & Prompts Section -->
            <div class="aipc-settings-section">
                <h2>
                    <span class="dashicons dashicons-format-quote" style="color: #2271b1;"></span>
                    <?php _e('AI Persona & Prompts', 'ai-product-chatbot'); ?>
                    <small style="color: #f0b849; font-weight: normal;"><?php _e('(Business+ Feature)', 'ai-product-chatbot'); ?></small>
                </h2>
                <div class="notice notice-info inline" style="margin: 0 0 20px 0; padding: 15px;">
                    <p><strong><?php _e('💡 Pro-tip:', 'ai-product-chatbot'); ?></strong> <?php _e('Het System Prompt is de meest krachtige instelling! Hier definieer je precies wat jouw business doet en hoe de AI moet reageren. Dit werkt voor elke branche - beauty, fashion, electronics, food, of wat dan ook.', 'ai-product-chatbot'); ?></p>
                </div>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="aipc_system_prompt"><?php _e('System prompt (persona)', 'ai-product-chatbot'); ?> <span class="dashicons dashicons-editor-help" title="Hier definieer je precies hoe de AI zich gedraagt en wat je business doet. Dit is veel krachtiger dan voorgedefinieerde opties!"></span></label></th>
                        <td>
                            <textarea id="aipc_system_prompt" name="aipc_system_prompt" rows="8" class="large-text" placeholder="Voorbeelden:

💄 BEAUTY/SKINCARE:
Je bent een AI productassistent voor [Jouw Winkel], een schoonheids- en verzorgingswebshop. Je helpt met productaanbevelingen op basis van huidtype, ingrediëntuitleg en routine-advies.

👗 FASHION:
Je bent een AI stylist voor [Jouw Merk], een fashion webshop. Je helpt klanten met outfit-aanbevelingen, maatadvies en seizoenstrends op basis van hun stijl en voorkeuren.

📱 ELECTRONICS:
Je bent een tech-expert voor [Jouw Shop], een electronica webshop. Je helpt met specificatie-vergelijkingen, compatibiliteit en gebruik-scenario's voor tech producten.

🍽️ FOOD/SUPPLEMENTS:
Je bent een voedingsadviseur voor [Jouw Winkel], gespecialiseerd in gezonde voeding en supplementen. Je geeft advies op basis van dieetwensen, allergieën en gezondheidsdoelen.

🏬 ALGEMEEN:
Je bent een behulpzame productassistent voor [Jouw Bedrijf]. Je helpt klanten het juiste product vinden op basis van hun behoeften, budget en voorkeuren."><?php echo esc_textarea(get_option('aipc_system_prompt', '')); ?></textarea>
                            <p class="description"><?php _e('<strong>Hier definieer je jouw business!</strong> Dit bepaalt hoe de AI zich gedraagt - veel krachtiger dan een dropdown. Kopieer een voorbeeld en pas aan naar jouw situatie.', 'ai-product-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aipc_greet_with_products"><?php _e('Groet (met productcontext)', 'ai-product-chatbot'); ?> <span class="dashicons dashicons-editor-help" title="Voorbeeld: Hallo! Ik kan je helpen met aanbevelingen op basis van onze catalogus. Waar ben je naar op zoek?"></span></label></th>
                        <td>
                            <textarea id="aipc_greet_with_products" name="aipc_greet_with_products" rows="3" class="large-text" placeholder="Hallo! Ik kan je helpen met aanbevelingen op basis van onze catalogus. Waar ben je naar op zoek?
(Leeg laten = standaard)"><?php echo esc_textarea(get_option('aipc_greet_with_products', '')); ?></textarea>
                            <p class="description"><?php _e('Gebruik aangepaste groet als er productcontext is. Leeg = standaard.', 'ai-product-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aipc_greet_without_products"><?php _e('Groet (zonder productcontext)', 'ai-product-chatbot'); ?> <span class="dashicons dashicons-editor-help" title="Voorbeeld: Hallo! Ik help je met productadvies en ingrediënteninformatie. Hoe kan ik je vandaag helpen?"></span></label></th>
                        <td>
                            <textarea id="aipc_greet_without_products" name="aipc_greet_without_products" rows="3" class="large-text" placeholder="Hallo! Ik help je met productadvies en ingrediënteninformatie. Hoe kan ik je vandaag helpen?
(Leeg laten = standaard)"><?php echo esc_textarea(get_option('aipc_greet_without_products', '')); ?></textarea>
                            <p class="description"><?php _e('Gebruik aangepaste groet als er geen productcontext is. Leeg = standaard.', 'ai-product-chatbot'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Product Quiz & Features - Business+ Feature -->
            <?php if ($license->has_feature('custom_skin_test')): ?>
            <div class="aipc-settings-section">
                <h2>
                    <span class="dashicons dashicons-awards" style="color: #f0b849;"></span>
                    <?php _e('Product Quiz & Features', 'ai-product-chatbot'); ?>
                    <small style="color: #f0b849; font-weight: normal;"><?php _e('(Business+ Feature)', 'ai-product-chatbot'); ?></small>
                </h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="aipc_enable_skin_test"><?php _e('Product Quiz', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="aipc_enable_skin_test" name="aipc_enable_skin_test" 
                                   value="1" <?php checked(get_option('aipc_enable_skin_test', true)); ?> />
                            <label for="aipc_enable_skin_test"><?php _e('Interactieve quiz voor gepersonaliseerde productaanbevelingen', 'ai-product-chatbot'); ?></label>
                            <p class="description">
                                <?php _e('Gebruikers kunnen "product quiz" typen om een interactieve vragenlijst te starten die tot gepersonaliseerde productaanbevelingen leidt.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_enable_product_recommendations"><?php _e('Smart Product Recommendations', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="aipc_enable_product_recommendations" name="aipc_enable_product_recommendations" 
                                   value="1" <?php checked(get_option('aipc_enable_product_recommendations', true)); ?> />
                            <label for="aipc_enable_product_recommendations"><?php _e('AI-gedreven productaanbevelingen op basis van gebruikersinput', 'ai-product-chatbot'); ?></label>
                            <p class="description">
                                <?php _e('Laat de AI slimme productaanbevelingen doen gebaseerd op gebruikersvragen, onafhankelijk van de quiz functie.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Privacy & API Monitoring Settings - Business+ Feature -->
            <?php if ($license->has_feature('api_integrations')): ?>
            <div class="aipc-settings-section">
                <h2>
                    <span class="dashicons dashicons-privacy" style="color: #46b450;"></span>
                    <?php _e('Privacy & API Monitoring', 'ai-product-chatbot'); ?>
                    <small style="color: #f0b849; font-weight: normal;"><?php _e('(Business+ Feature)', 'ai-product-chatbot'); ?></small>
                </h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="aipc_store_conversations"><?php _e('Gesprekken opslaan', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="aipc_store_conversations" name="aipc_store_conversations" value="1" <?php checked(get_option('aipc_store_conversations', true)); ?> />
                            <label for="aipc_store_conversations"><?php _e('Sla chatgesprekken lokaal op', 'ai-product-chatbot'); ?></label>
                            <p class="description"><?php _e('Schakel uit voor maximale dataminimalisatie. Analytics kunnen beperkter zijn.', 'ai-product-chatbot'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_retention_days"><?php _e('Gesprekken bewaartermijn (dagen)', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="aipc_retention_days" name="aipc_retention_days" class="small-text" min="0" value="<?php echo esc_attr(get_option('aipc_retention_days', 90)); ?>" />
                            <p class="description"><?php _e('Conversaties ouder dan dit aantal dagen worden verwijderd bij purge/auto-purge. 0 = niet automatisch verwijderen.', 'ai-product-chatbot'); ?></p>
                            <button type="button" class="button" id="aipc-purge-conversations"><?php _e('Verwijder oude gesprekken nu', 'ai-product-chatbot'); ?></button>
                            <button type="button" class="button" id="aipc-export-conversations"><?php _e('Exporteren (JSON)', 'ai-product-chatbot'); ?></button>
                            <div id="aipc-purge-export-result" style="margin-top:10px;"></div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_mask_email"><?php _e('E‑mail maskeren', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="aipc_mask_email" name="aipc_mask_email" value="1" <?php checked(get_option('aipc_mask_email', true)); ?> />
                            <p class="description"><?php _e('Verberg e‑mailadressen volledig in chat en opslag (AVG compliance).', 'ai-product-chatbot'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_mask_phone"><?php _e('Telefoon maskeren', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="aipc_mask_phone" name="aipc_mask_phone" value="1" <?php checked(get_option('aipc_mask_phone', true)); ?> />
                            <p class="description"><?php _e('Verberg telefoonnummers volledig in chat en opslag (AVG compliance).', 'ai-product-chatbot'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_rate_limit_per_minute"><?php _e('Rate Limit (per minuut)', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="aipc_rate_limit_per_minute" name="aipc_rate_limit_per_minute" class="small-text" min="1" max="100" value="<?php echo esc_attr(get_option('aipc_rate_limit_per_minute', 10)); ?>" />
                            <p class="description"><?php _e('Maximum aantal API verzoeken per minuut per IP adres. Voorkomt API misbruik.', 'ai-product-chatbot'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_monitoring_retention_days"><?php _e('API Monitoring bewaartermijn (dagen)', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="aipc_monitoring_retention_days" name="aipc_monitoring_retention_days" class="small-text" min="1" max="365" value="<?php echo esc_attr(get_option('aipc_monitoring_retention_days', 30)); ?>" />
                            <p class="description"><?php _e('Monitoring data ouder dan dit aantal dagen wordt automatisch verwijderd.', 'ai-product-chatbot'); ?></p>
                            <button type="button" class="button" id="aipc-cleanup-monitoring"><?php _e('Verwijder oude monitoring data nu', 'ai-product-chatbot'); ?></button>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_anonymize_monitoring_ips"><?php _e('IP Adressen anonimiseren', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="aipc_anonymize_monitoring_ips" name="aipc_anonymize_monitoring_ips" value="1" <?php checked(get_option('aipc_anonymize_monitoring_ips', true)); ?> />
                            <p class="description"><?php _e('Anonimiseer IP adressen in monitoring data (AVG/GDPR compliance). IPv4: 192.168.1.123 → 192.168.1.0', 'ai-product-chatbot'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
            
            <!-- AVG/GDPR Compliance Status -->
            <?php if ($license->has_feature('api_integrations')): ?>
            <div class="aipc-settings-section">
                <h2>
                    <span class="dashicons dashicons-shield" style="color: <?php echo $avg_pass ? '#46b450' : '#d63638'; ?>;"></span>
                    <?php _e('AVG/GDPR Compliance Status', 'ai-product-chatbot'); ?>
                </h2>
                
                <div class="aipc-compliance-status" style="background: <?php echo $avg_pass ? '#d1ecf1' : '#f8d7da'; ?>; border: 1px solid <?php echo $avg_pass ? '#bee5eb' : '#f5c6cb'; ?>; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
                    <?php if ($avg_pass): ?>
                        <h3 style="margin-top: 0; color: #0c5460;">
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e('✅ AVG/GDPR Compliant', 'ai-product-chatbot'); ?>
                        </h3>
                        <p style="color: #0c5460;"><?php _e('Je chatbot voldoet aan de AVG/GDPR richtlijnen voor privacy en dataminimalisatie.', 'ai-product-chatbot'); ?></p>
                    <?php else: ?>
                        <h3 style="margin-top: 0; color: #721c24;">
                            <span class="dashicons dashicons-warning"></span>
                            <?php _e('⚠️ AVG/GDPR Aandachtspunten', 'ai-product-chatbot'); ?>
                        </h3>
                        <p style="color: #721c24;"><?php _e('Er zijn enkele privacy-instellingen die aandacht behoeven voor volledige AVG/GDPR compliance.', 'ai-product-chatbot'); ?></p>
                    <?php endif; ?>
                </div>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('E-mail maskering', 'ai-product-chatbot'); ?></th>
                        <td>
                            <?php if ($email_masking_on): ?>
                                <span class="dashicons dashicons-yes" style="color: #46b450;"></span> <?php _e('AAN', 'ai-product-chatbot'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-warning" style="color: #d63638;"></span> <?php _e('UIT', 'ai-product-chatbot'); ?>
                                <span class="description" style="color:#dc3232;">— <?php _e('Aanbevolen voor AVG compliance', 'ai-product-chatbot'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Telefoon maskering', 'ai-product-chatbot'); ?></th>
                        <td>
                            <?php if ($phone_masking_on): ?>
                                <span class="dashicons dashicons-yes" style="color: #46b450;"></span> <?php _e('AAN', 'ai-product-chatbot'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-warning" style="color: #d63638;"></span> <?php _e('UIT', 'ai-product-chatbot'); ?>
                                <span class="description" style="color:#dc3232;">— <?php _e('Aanbevolen voor AVG compliance', 'ai-product-chatbot'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('IP Adressen anonimiseren', 'ai-product-chatbot'); ?></th>
                        <td>
                            <?php if ($ip_anonymized): ?>
                                <span class="dashicons dashicons-yes" style="color: #46b450;"></span> <?php _e('AAN', 'ai-product-chatbot'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-warning" style="color: #d63638;"></span> <?php _e('UIT', 'ai-product-chatbot'); ?>
                                <span class="description" style="color:#dc3232;">— <?php _e('Aanbevolen voor AVG compliance', 'ai-product-chatbot'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Gesprekken opslaan', 'ai-product-chatbot'); ?></th>
                        <td>
                            <?php if ($store_conversations): ?>
                                <span class="dashicons dashicons-yes"></span> <?php _e('AAN', 'ai-product-chatbot'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-hidden"></span> <?php _e('UIT', 'ai-product-chatbot'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Bewaartermijn', 'ai-product-chatbot'); ?></th>
                        <td>
                            <?php echo esc_html($retention_days); ?> <?php _e('dagen', 'ai-product-chatbot'); ?>
                            <?php if ($store_conversations && intval($retention_days) === 0): ?>
                                <span class="description" style="color:#dc3232;">— <?php _e('Aanbevolen: > 0 dagen bij opslag', 'ai-product-chatbot'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Automatische purge', 'ai-product-chatbot'); ?></th>
                        <td>
                            <?php if ($auto_purge_scheduled): ?>
                                <span class="dashicons dashicons-yes" style="color: #46b450;"></span> <?php _e('ACTIEF', 'ai-product-chatbot'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-warning" style="color: #d63638;"></span> <?php _e('NIET ACTIEF', 'ai-product-chatbot'); ?>
                                <?php if ($store_conversations && intval($retention_days) > 0): ?>
                                    <span class="description" style="color:#dc3232;">— <?php _e('Auto-purge is vereist bij data opslag', 'ai-product-chatbot'); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- License Status Section - Always visible -->
            <div class="aipc-settings-section">
                <h2><?php _e('Licentie Status', 'ai-product-chatbot'); ?></h2>
                
                <?php $license_info = $license->get_license_info(); ?>
                <?php $grace = $license->get_grace_status(); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Huidige Tier', 'ai-product-chatbot'); ?></th>
                        <td>
                            <strong style="text-transform: capitalize; color: <?php echo $is_active ? '#46b450' : '#d63638'; ?>;">  
                                <?php echo ucfirst($current_tier); ?> 
                                <?php if ($is_active): ?>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning"></span> <?php _e('Niet geactiveerd', 'ai-product-chatbot'); ?>
                                <?php endif; ?>
                            </strong>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Licentie Server URL', 'ai-product-chatbot'); ?></th>
                        <td>
                            <?php $server_url = get_option('aipc_license_server_url', ''); ?>
                            <input type="url" name="aipc_license_server_url" value="<?php echo esc_attr($server_url); ?>" class="regular-text" placeholder="https://your-license-server.com" required />
                            <?php if (empty($server_url)): ?>
                                <p class="description" style="color: #d63638; font-weight: bold;">
                                    <span class="dashicons dashicons-warning"></span> 
                                    <?php _e('⚠️ VERPLICHT: Stel eerst de licentie server URL in voordat je een licentie kunt activeren.', 'ai-product-chatbot'); ?>
                                </p>
                            <?php else: ?>
                                <p class="description">
                                    <?php _e('URL van je licentie server (waar de license manager plugin geïnstalleerd is).', 'ai-product-chatbot'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Upgrade/Koop URL', 'ai-product-chatbot'); ?></th>
                        <td>
                            <input type="url" name="aipc_upgrade_url" value="<?php echo esc_attr(get_option('aipc_upgrade_url', 'https://your-website.com/upgrade')); ?>" class="regular-text" placeholder="https://your-website.com/upgrade" />
                            <p class="description">
                                <?php _e('URL waar gebruikers naartoe worden gestuurd om een licentie te kopen of te upgraden.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <?php if ($is_active): ?>
                    <tr>
                        <th scope="row"><?php _e('Licentie Info', 'ai-product-chatbot'); ?></th>
                        <td>
                            <p><strong><?php _e('Key:', 'ai-product-chatbot'); ?></strong> <?php echo esc_html(substr($license_info['key'], 0, 15) . '...'); ?></p>
                            <p><strong><?php _e('Domain:', 'ai-product-chatbot'); ?></strong> <?php echo esc_html($license_info['domain']); ?></p>
                            <p><strong><?php _e('Verloopt:', 'ai-product-chatbot'); ?></strong> <?php echo esc_html($license_info['expires']); ?></p>
                            <button type="button" id="aipc_deactivate_license" class="button button-secondary"><?php _e('Deactiveren', 'ai-product-chatbot'); ?></button>
                        </td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <th scope="row"><?php _e('Licentie Key', 'ai-product-chatbot'); ?></th>
                        <td>
                            <?php $server_configured = !empty(get_option('aipc_license_server_url', '')); ?>
                            <input type="text" id="aipc_license_key" placeholder="AIPC-BUSINESS-12345678" class="regular-text" <?php echo $server_configured ? '' : 'disabled'; ?> />
                            <button type="button" id="aipc_activate_license" class="button button-primary" <?php echo $server_configured ? '' : 'disabled'; ?>><?php _e('Activeren', 'ai-product-chatbot'); ?></button>
                            <?php if (!$server_configured): ?>
                                <p class="description" style="color: #d63638;">
                                    <?php _e('⚠️ Stel eerst de licentie server URL in om een licentie te kunnen activeren.', 'ai-product-chatbot'); ?>
                                </p>
                            <?php else: ?>
                                <p class="description">
                                    <?php _e('Voer je licentie key in om premium features te ontgrendelen.', 'ai-product-chatbot'); ?>
                                    <a href="https://your-website.com/purchase" target="_blank"><?php _e('Koop een licentie', 'ai-product-chatbot'); ?></a>
                                </p>
                            <?php endif; ?>
                            <div id="aipc_license_result" style="margin-top: 10px;"></div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr>
                        <th scope="row"><?php _e('Beschikbare Features', 'ai-product-chatbot'); ?></th>
                        <td>
                            <?php $features = $license->get_tier_features($current_tier); ?>
                            <ul style="list-style: disc; margin-left: 20px;">
                                <?php foreach ($features as $feature): ?>
                                    <li><?php echo esc_html($feature); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            
                            <?php if ($current_tier !== 'enterprise'): ?>
                                <p style="margin-top: 15px;">
                                    <?php $next_tier = ($current_tier === 'basic') ? 'business' : 'enterprise'; ?>
                                    <a href="<?php echo $license->generate_upgrade_url($next_tier); ?>" class="button button-primary">
                                        <?php printf(__('Upgrade naar %s', 'ai-product-chatbot'), ucfirst($next_tier)); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php submit_button(__('Instellingen Opslaan', 'ai-product-chatbot')); ?>
        </form>
        
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    console.log('AIPC Admin: jQuery loaded and ready');
    console.log('AIPC Admin: ajaxurl = ' + (typeof ajaxurl !== 'undefined' ? ajaxurl : 'UNDEFINED'));
    
    // Debug: Check if buttons exist
    if ($('#aipc-test-api').length) console.log('AIPC Admin: Test API button found');
    if ($('#aipc-fetch-models').length) console.log('AIPC Admin: Fetch models button found');
    if ($('#aipc-openrouter-key-info').length) console.log('AIPC Admin: OpenRouter key info button found');
    if ($('#aipc-openrouter-free-usage').length) console.log('AIPC Admin: OpenRouter free usage button found');
    // Test API functionality
    console.log('AIPC Admin: Registering test API click handler');
    $('#aipc-test-api').on('click', function() {
        console.log('AIPC Admin: Test API button clicked');
        var button = $(this);
        var result = $('#aipc-api-test-result');
        
        button.prop('disabled', true).text('<?php echo esc_js(__('Testen...', 'ai-product-chatbot')); ?>');
        result.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_test_api',
                nonce: button.data('nonce')
            },
            success: function(response) {
                if (response.success) {
                    result.html('<div class="notice notice-success inline"><p>✅ ' + response.data.message + '</p></div>');
                } else {
                    result.html('<div class="notice notice-error inline"><p>❌ ' + response.data.message + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('API Test Error:', xhr.responseText, status, error);
                result.html('<div class="notice notice-error inline"><p><?php echo esc_js(__('❌ Fout bij testen van API verbinding. Check console voor details.', 'ai-product-chatbot')); ?></p></div>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php echo esc_js(__('Test API', 'ai-product-chatbot')); ?>');
            }
        });
    });
    
    // Fetch models functionality
    console.log('AIPC Admin: Registering fetch models click handler');
    $('#aipc-fetch-models').on('click', function() {
        console.log('AIPC Admin: Fetch models button clicked');
        var button = $(this);
        var modelSelect = $('#aipc_openai_model');
        var currentModel = modelSelect.val();
        
        button.prop('disabled', true).text('<?php echo esc_js(__('Laden...', 'ai-product-chatbot')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_fetch_models',
                nonce: button.data('nonce')
            },
            success: function(response) {
                    if (response.success && response.data.models) {
                        modelSelect.empty();
                        var models = response.data.models;
                        
                        // Group models by free/paid for better organization
                        var freeModels = [];
                        var paidModels = [];
                        
                        $.each(models, function(index, modelId) {
                            if (modelId.includes(':free')) {
                                freeModels.push(modelId);
                            } else {
                                paidModels.push(modelId);
                            }
                        });
                        
                        // Add free models first with group label
                        if (freeModels.length > 0) {
                            modelSelect.append('<optgroup label="🆓 Gratis Modellen (' + freeModels.length + ')"></optgroup>');
                            $.each(freeModels, function(index, modelId) {
                                var selected = (modelId === currentModel) ? 'selected' : '';
                                modelSelect.append('<option value="' + modelId + '" ' + selected + '>  ' + modelId + '</option>');
                            });
                        }
                        
                        // Add paid models with group label
                        if (paidModels.length > 0) {
                            modelSelect.append('<optgroup label="💰 Betaalde Modellen (' + paidModels.length + ')"></optgroup>');
                            $.each(paidModels, function(index, modelId) {
                                var selected = (modelId === currentModel) ? 'selected' : '';
                                modelSelect.append('<option value="' + modelId + '" ' + selected + '>  ' + modelId + '</option>');
                            });
                        }
                        
                        // Show success message
                        var successMsg = '✅ ' + models.length + ' modellen geladen! (' + freeModels.length + ' gratis, ' + paidModels.length + ' betaald)';
                        $('<div class="notice notice-success inline" style="margin-top: 10px; padding: 10px;"><p>' + successMsg + '</p></div>').insertAfter(button).delay(3000).fadeOut();
                    } else {
                        var errorMsg = '❌ <?php echo esc_js(__('Fout bij laden modellen:', 'ai-product-chatbot')); ?> ' + (response.data.message || '<?php echo esc_js(__('Onbekende fout', 'ai-product-chatbot')); ?>');
                        $('<div class="notice notice-error inline" style="margin-top: 10px; padding: 10px;"><p>' + errorMsg + '</p></div>').insertAfter(button).delay(5000).fadeOut();
                    }
            },
            error: function(xhr, status, error) {
                console.error('Model fetch error:', xhr.responseText, status, error);
                var errorMsg = '❌ AJAX fout bij laden modellen: ' + status + ' - ' + error;
                if (xhr.responseText) {
                    try {
                        var errorData = JSON.parse(xhr.responseText);
                        if (errorData.data && errorData.data.message) {
                            errorMsg = '❌ ' + errorData.data.message;
                        }
                    } catch (e) {
                        // responseText is not JSON, use as-is
                        errorMsg += ' (' + xhr.responseText.substring(0, 100) + ')';
                    }
                }
                $('<div class="notice notice-error inline" style="margin-top: 10px; padding: 10px;"><p>' + errorMsg + '</p></div>').insertAfter(button).delay(8000).fadeOut();
            },
            complete: function() {
                button.prop('disabled', false).text('<?php echo esc_js(__('Laad Modellen', 'ai-product-chatbot')); ?>');
            }
        });
    });
    
    // Load models on provider change
    $('#aipc_api_provider').on('change', function() {
        var provider = $(this).val();
        var modelSelect = $('#aipc_openai_model');
        var currentModel = '<?php echo esc_js(get_option('aipc_openai_model', 'gpt-4')); ?>';
        
        // Clear current models
        modelSelect.empty();
        modelSelect.append('<option value="">Selecteer een model...</option>');
        
        if (provider === 'openai') {
            // OpenAI standard models
            modelSelect.append('<option value="gpt-4o">gpt-4o</option>');
            modelSelect.append('<option value="gpt-4-turbo">gpt-4-turbo</option>');
            modelSelect.append('<option value="gpt-4">gpt-4</option>');
            modelSelect.append('<option value="gpt-3.5-turbo">gpt-3.5-turbo</option>');
            
            // Select current model
            if (currentModel) {
                modelSelect.val(currentModel);
            }
        } else if (provider === 'openrouter') {
            // For OpenRouter, show loading and fetch real models
            modelSelect.append('<option value="">⏳ Modellen laden van OpenRouter...</option>');
            
            // Check if API key is set before trying to fetch
            var apiKey = $('#aipc_openai_api_key').val();
            if (!apiKey || apiKey.trim() === '') {
                modelSelect.empty();
                modelSelect.append('<option value="">❌ Voer eerst een OpenRouter API key in</option>');
                return;
            }
            
            // Auto-fetch models from OpenRouter
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'aipc_fetch_models',
                    nonce: '<?php echo wp_create_nonce('aipc_test_api'); ?>'
                },
                success: function(response) {
                    modelSelect.empty();
                    if (response.success && response.data.models) {
                        var models = response.data.models;
                        
                        // Add models with nice labels
                        $.each(models, function(index, modelId) {
                            var label = modelId;
                            if (modelId.includes(':free')) {
                                label = modelId + ' (🆓 GRATIS)';
                            }
                            var selected = (modelId === currentModel) ? 'selected' : '';
                            modelSelect.append('<option value="' + modelId + '" ' + selected + '>' + label + '</option>');
                        });
                        
                        // Select current model if it exists and is valid
                        if (currentModel && models.includes(currentModel)) {
                            modelSelect.val(currentModel);
                        } else if (models.length > 0) {
                            // Try to find a good working free model
                            var preferredFreeModels = [
                                'x-ai/grok-beta:free',
                                'x-ai/grok-vision-beta:free', 
                                'meta-llama/llama-3.2-3b-instruct:free',
                                'google/gemma-2-9b-it:free', 
                                'microsoft/phi-3-medium-128k-instruct:free',
                                'huggingfaceh4/zephyr-7b-beta:free'
                            ];
                            
                            var selectedModel = null;
                            
                            // First try preferred models
                            for (var i = 0; i < preferredFreeModels.length; i++) {
                                if (models.includes(preferredFreeModels[i])) {
                                    selectedModel = preferredFreeModels[i];
                                    break;
                                }
                            }
                            
                            // If no preferred model found, select any free model
                            if (!selectedModel) {
                                selectedModel = models.find(function(m) { return m.includes(':free'); });
                            }
                            
                            // If still no free model, select first model
                            if (!selectedModel && models.length > 0) {
                                selectedModel = models[0];
                            }
                            
                            if (selectedModel) {
                                modelSelect.val(selectedModel);
                                console.log('✅ Auto-selected model: ' + selectedModel);
                            }
                        }
                        
                        console.log('✅ Loaded ' + models.length + ' OpenRouter models');
                    } else {
                        modelSelect.append('<option value="">❌ Fout bij laden: ' + (response.data.message || 'Onbekende fout') + '</option>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Model fetch error:', xhr.responseText);
                    modelSelect.empty();
                    modelSelect.append('<option value="">❌ Netwerkfout: ' + status + '</option>');
                    modelSelect.append('<option value="openai/gpt-3.5-turbo">openai/gpt-3.5-turbo (fallback)</option>');
                }
            });
        }
    });
    
    // Initialize model dropdown on page load
    $(document).ready(function() {
        $('#aipc_api_provider').trigger('change');
    });
    
    // OpenRouter Key Info
    console.log('AIPC Admin: Registering OpenRouter key info click handler');
    $('#aipc-openrouter-key-info').on('click', function() {
        console.log('AIPC Admin: OpenRouter key info button clicked');
        var button = $(this);
        var result = $('#aipc-openrouter-result');
        
        button.prop('disabled', true).text('<?php echo esc_js(__('Ophalen...', 'ai-product-chatbot')); ?>');
        result.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_openrouter_key_info',
                nonce: '<?php echo wp_create_nonce('aipc_test_api'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var k = response.data.key;
                    var limitText = (k.limit === null) ? 'onbeperkt' : k.limit;
                    var freeText = k.is_free_tier ? 'ja' : 'nee';
                    var html = '<div class="notice notice-success inline"><p>' +
                        '<strong><?php echo esc_js(__('OpenRouter API Key Info:', 'ai-product-chatbot')); ?></strong><br>' +
                        '<?php echo esc_js(__('Label:', 'ai-product-chatbot')); ?> ' + (k.label || '-') + '<br>' +
                        '<?php echo esc_js(__('Credits gebruikt:', 'ai-product-chatbot')); ?> ' + k.usage + '<br>' +
                        '<?php echo esc_js(__('Credit limiet:', 'ai-product-chatbot')); ?> ' + limitText + '<br>' +
                        '<?php echo esc_js(__('Free tier:', 'ai-product-chatbot')); ?> ' + freeText +
                    '</p></div>';
                    result.html(html);
                } else {
                    result.html('<div class="notice notice-error inline"><p>' + (response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Fout bij ophalen sleutelinfo', 'ai-product-chatbot')); ?>') + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('OpenRouter key info error:', xhr.responseText, status, error);
                var errorMsg = '<?php echo esc_js(__('Fout bij ophalen OpenRouter key info', 'ai-product-chatbot')); ?>';
                if (xhr.responseText) {
                    errorMsg += ' (Debug: ' + xhr.status + ' - ' + xhr.responseText.substring(0, 200) + ')';
                }
                result.html('<div class="notice notice-error inline"><p>' + errorMsg + '</p></div>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php echo esc_js(__('📊 Toon OpenRouter Limieten', 'ai-product-chatbot')); ?>');
            }
        });
    });
    
    // OpenRouter Free Usage
    console.log('AIPC Admin: Registering OpenRouter free usage click handler');
    $('#aipc-openrouter-free-usage').on('click', function() {
        console.log('AIPC Admin: OpenRouter free usage button clicked');
        var button = $(this);
        var result = $('#aipc-openrouter-free-result');
        
        button.prop('disabled', true).text('<?php echo esc_js(__('Ophalen...', 'ai-product-chatbot')); ?>');
        result.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_openrouter_free_usage',
                nonce: '<?php echo wp_create_nonce('aipc_test_api'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var d = response.data;
                    var isOverLimit = d.used_today > d.daily_cap;
                    var isAtLimit = d.remaining <= 0;
                    
                    // Choose notice type based on usage status
                    var noticeType = 'notice-success';
                    var statusIcon = '✅';
                    var statusMessage = '';
                    
                    if (isOverLimit) {
                        noticeType = 'notice-error';
                        statusIcon = '🚫';
                        statusMessage = '<br><strong style="color: #d63638;"><?php echo esc_js(__('⚠️ LIMIET OVERSCHREDEN! Je kunt vandaag geen :free modellen meer gebruiken.', 'ai-product-chatbot')); ?></strong><br><?php echo esc_js(__('Tip: Switch naar betaalde modellen of wacht tot morgen.', 'ai-product-chatbot')); ?>';
                    } else if (isAtLimit) {
                        noticeType = 'notice-warning';
                        statusIcon = '⚠️';
                        statusMessage = '<br><strong style="color: #f0b849;"><?php echo esc_js(__('Limiet bereikt! Geen :free requests meer beschikbaar vandaag.', 'ai-product-chatbot')); ?></strong>';
                    } else if (d.remaining <= 5) {
                        noticeType = 'notice-warning';
                        statusIcon = '⚠️';
                        statusMessage = '<br><strong style="color: #f0b849;"><?php echo esc_js(__('Bijna op! Nog maar', 'ai-product-chatbot')); ?> ' + d.remaining + ' <?php echo esc_js(__(':free requests vandaag.', 'ai-product-chatbot')); ?></strong>';
                    }
                    
                    var html = '<div class="notice ' + noticeType + ' inline"><p>' +
                        '<strong>' + statusIcon + ' <?php echo esc_js(__('Free Requests Vandaag:', 'ai-product-chatbot')); ?></strong><br>' +
                        '<?php echo esc_js(__('Vandaag gebruikt:', 'ai-product-chatbot')); ?> <strong>' + d.used_today + '</strong><br>' +
                        '<?php echo esc_js(__('Daglimiet:', 'ai-product-chatbot')); ?> <strong>' + d.daily_cap + '</strong><br>' +
                        '<?php echo esc_js(__('Resterend:', 'ai-product-chatbot')); ?> <strong>' + d.remaining + '</strong>' +
                        statusMessage +
                    '</p></div>';
                    result.html(html);
                } else {
                    result.html('<div class="notice notice-error inline"><p>' + (response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Fout bij ophalen free usage', 'ai-product-chatbot')); ?>') + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('OpenRouter free usage error:', xhr.responseText, status, error);
                var errorMsg = '<?php echo esc_js(__('Fout bij ophalen free usage info', 'ai-product-chatbot')); ?>';
                if (xhr.responseText) {
                    errorMsg += ' (Debug: ' + xhr.status + ' - ' + xhr.responseText.substring(0, 200) + ')';
                }
                result.html('<div class="notice notice-error inline"><p>' + errorMsg + '</p></div>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php echo esc_js(__('🆓 Toon Free Requests Vandaag', 'ai-product-chatbot')); ?>');
            }
        });
    });
    
    // License activation
    $('#aipc_activate_license').on('click', function() {
        var button = $(this);
        var key = $('#aipc_license_key').val();
        var result = $('#aipc_license_result');
        
        if (!key) {
            result.html('<div class="notice notice-error inline"><p><?php echo esc_js(__('Voer een licentie key in.', 'ai-product-chatbot')); ?></p></div>');
            return;
        }
        
        button.prop('disabled', true).text('<?php echo esc_js(__('Activeren...', 'ai-product-chatbot')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_activate_license',
                license_key: key,
                nonce: '<?php echo wp_create_nonce('aipc_test_api'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', xhr.responseText, status, error);
                result.html('<div class="notice notice-error inline"><p><?php echo esc_js(__('Er is een fout opgetreden.', 'ai-product-chatbot')); ?> Debug: ' + status + ' - ' + error + '</p></div>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php echo esc_js(__('Activeren', 'ai-product-chatbot')); ?>');
            }
        });
    });
    
    // License deactivation
    $('#aipc_deactivate_license').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Weet je zeker dat je je licentie wilt deactiveren?', 'ai-product-chatbot')); ?>')) {
            return;
        }
        
        var button = $(this);
        button.prop('disabled', true).text('<?php _e('Deactiveren...', 'ai-product-chatbot'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_deactivate_license',
                nonce: '<?php echo wp_create_nonce('aipc_test_api'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php _e('Licentie succesvol gedeactiveerd!', 'ai-product-chatbot'); ?>');
                    location.reload();
                } else {
                    alert('<?php _e('Er is een fout opgetreden bij het deactiveren.', 'ai-product-chatbot'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('Er is een fout opgetreden.', 'ai-product-chatbot'); ?>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Deactiveren', 'ai-product-chatbot'); ?>');
            }
        });
    });
    
    // Manual conversation cleanup
    $('#aipc-purge-conversations').on('click', function() {
        if (!confirm('<?php _e('Weet je zeker dat je oude gesprekken wilt verwijderen? Dit kan niet ongedaan gemaakt worden.', 'ai-product-chatbot'); ?>')) {
            return;
        }
        
        var button = $(this);
        button.prop('disabled', true).text('<?php _e('Verwijderen...', 'ai-product-chatbot'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_purge_conversations',
                nonce: '<?php echo wp_create_nonce('aipc_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php _e('Succesvol verwijderd:', 'ai-product-chatbot'); ?> ' + response.data.deleted + ' <?php _e('gesprekken', 'ai-product-chatbot'); ?>');
                } else {
                    alert('<?php _e('Fout:', 'ai-product-chatbot'); ?> ' + (response.data.message || '<?php _e('Onbekende fout', 'ai-product-chatbot'); ?>'));
                }
            },
            error: function() {
                alert('<?php _e('Er is een fout opgetreden bij het verwijderen.', 'ai-product-chatbot'); ?>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Verwijder oude gesprekken nu', 'ai-product-chatbot'); ?>');
            }
        });
    });
    
    // Export conversations
    $('#aipc-export-conversations').on('click', function() {
        var button = $(this);
        var result = $('#aipc-purge-export-result');
        
        button.prop('disabled', true).text('<?php _e('Exporteren...', 'ai-product-chatbot'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_export_conversations',
                nonce: '<?php echo wp_create_nonce('aipc_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Create download link
                    var data = JSON.stringify(response.data.conversations, null, 2);
                    var blob = new Blob([data], {type: 'application/json'});
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'aipc-conversations-' + new Date().toISOString().split('T')[0] + '.json';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    result.html('<div class="notice notice-success inline"><p><?php _e('Export gestart! Bestand wordt gedownload:', 'ai-product-chatbot'); ?> ' + response.data.count + ' <?php _e('gesprekken', 'ai-product-chatbot'); ?></p></div>');
                    setTimeout(function() { result.html(''); }, 5000);
                } else {
                    result.html('<div class="notice notice-error inline"><p><?php _e('Fout:', 'ai-product-chatbot'); ?> ' + (response.data.message || '<?php _e('Onbekende fout', 'ai-product-chatbot'); ?>') + '</p></div>');
                    setTimeout(function() { result.html(''); }, 5000);
                }
            },
            error: function() {
                result.html('<div class="notice notice-error inline"><p><?php echo esc_js(__('Er is een fout opgetreden bij het exporteren.', 'ai-product-chatbot')); ?></p></div>');
                setTimeout(function() { result.html(''); }, 5000);
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Exporteren (JSON)', 'ai-product-chatbot'); ?>');
            }
        });
    });
    
    // Manual monitoring cleanup
    $('#aipc-cleanup-monitoring').on('click', function() {
        if (!confirm('<?php _e('Weet je zeker dat je oude monitoring data wilt verwijderen? Dit kan niet ongedaan gemaakt worden.', 'ai-product-chatbot'); ?>')) {
            return;
        }
        
        var button = $(this);
        button.prop('disabled', true).text('<?php _e('Verwijderen...', 'ai-product-chatbot'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_cleanup_monitoring',
                nonce: '<?php echo wp_create_nonce('aipc_test_api'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php _e('Monitoring cleanup voltooid!', 'ai-product-chatbot'); ?> ' + response.data.message);
                } else {
                    alert('<?php _e('Fout:', 'ai-product-chatbot'); ?> ' + (response.data.message || '<?php _e('Onbekende fout', 'ai-product-chatbot'); ?>'));
                }
            },
            error: function() {
                alert('<?php _e('Er is een fout opgetreden bij het opschonen.', 'ai-product-chatbot'); ?>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Verwijder oude monitoring data nu', 'ai-product-chatbot'); ?>');
            }
        });
    });
    
    // Enable license activation when server URL is provided
    $('input[name="aipc_license_server_url"]').on('input', function() {
        var serverUrl = $(this).val().trim();
        var isValid = serverUrl.length > 0 && (serverUrl.indexOf('http://') === 0 || serverUrl.indexOf('https://') === 0);
        
        $('#aipc_license_key, #aipc_activate_license').prop('disabled', !isValid);
        
        if (isValid) {
            $('#aipc_activate_license').removeClass('button-secondary').addClass('button-primary');
        } else {
            $('#aipc_activate_license').removeClass('button-primary').addClass('button-secondary');
        }
    });
});
</script>
