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
        update_option('aipc_chatbot_enabled', isset($_POST['aipc_chatbot_enabled']) ? 1 : 0);
        update_option('aipc_openai_api_key', sanitize_text_field($_POST['aipc_openai_api_key'] ?? ''));
        update_option('aipc_openai_model', sanitize_text_field($_POST['aipc_openai_model'] ?? 'gpt-4'));
        // Provider/base for alternative backends (e.g., OpenRouter)
        update_option('aipc_api_provider', sanitize_text_field($_POST['aipc_api_provider'] ?? 'openai'));
        update_option('aipc_api_base', esc_url_raw($_POST['aipc_api_base'] ?? ''));
        // Debug toggle
        update_option('aipc_show_api_errors', isset($_POST['aipc_show_api_errors']) ? 1 : 0);
        update_option('aipc_chatbot_title', sanitize_text_field($_POST['aipc_chatbot_title'] ?? 'AI Product Assistant'));
        update_option('aipc_chatbot_welcome_message', sanitize_textarea_field($_POST['aipc_chatbot_welcome_message'] ?? 'Hallo! Ik ben je AI product assistant. Hoe kan ik je vandaag helpen?'));
        update_option('aipc_chatbot_position', sanitize_text_field($_POST['aipc_chatbot_position'] ?? 'bottom-right'));
        update_option('aipc_chatbot_theme', sanitize_text_field($_POST['aipc_chatbot_theme'] ?? 'modern'));
        update_option('aipc_chatbot_tone', sanitize_text_field($_POST['aipc_chatbot_tone'] ?? 'neutral'));
        update_option('aipc_fallback_message', sanitize_textarea_field($_POST['aipc_fallback_message'] ?? ''));
        update_option('aipc_allow_judgement', isset($_POST['aipc_allow_judgement']) ? 1 : 0);
        update_option('aipc_tips_style', sanitize_text_field($_POST['aipc_tips_style'] ?? 'default'));
        update_option('aipc_live_only_mode', isset($_POST['aipc_live_only_mode']) ? 1 : 0);
        // Privacy: conversations storage + retention
        update_option('aipc_store_conversations', isset($_POST['aipc_store_conversations']) ? 1 : 0);
        update_option('aipc_retention_days', absint($_POST['aipc_retention_days'] ?? 90));
        update_option('aipc_mask_email', isset($_POST['aipc_mask_email']) ? 1 : 0);
        update_option('aipc_mask_phone', isset($_POST['aipc_mask_phone']) ? 1 : 0);
        // Custom prompts and greetings
        update_option('aipc_system_prompt', wp_kses_post($_POST['aipc_system_prompt'] ?? ''));
        update_option('aipc_greet_with_products', sanitize_textarea_field($_POST['aipc_greet_with_products'] ?? ''));
        update_option('aipc_greet_without_products', sanitize_textarea_field($_POST['aipc_greet_without_products'] ?? ''));
        update_option('aipc_max_faq_items', absint($_POST['aipc_max_faq_items'] ?? 20));
        update_option('aipc_max_doc_snippet', absint($_POST['aipc_max_doc_snippet'] ?? 500));
        
        // Content Sources (CPT + WooCommerce)
        $use_woo_source = isset($_POST['aipc_source_use_woocommerce']) ? 1 : 0;
        update_option('aipc_source_use_woocommerce', $use_woo_source);

        if (isset($_POST['aipc_content_sources']) && is_array($_POST['aipc_content_sources'])) {
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

        // Token pricing (per 1K tokens)
        update_option('aipc_token_input_rate_per_1k', isset($_POST['aipc_token_input_rate_per_1k']) ? floatval($_POST['aipc_token_input_rate_per_1k']) : 0);
        update_option('aipc_token_output_rate_per_1k', isset($_POST['aipc_token_output_rate_per_1k']) ? floatval($_POST['aipc_token_output_rate_per_1k']) : 0);

        // Feature toggles
        update_option('aipc_enable_skin_test', isset($_POST['aipc_enable_skin_test']) ? 1 : 0);
        update_option('aipc_enable_product_recommendations', isset($_POST['aipc_enable_product_recommendations']) ? 1 : 0);
        
        // WooCommerce settings
        update_option('aipc_woocommerce_enabled', isset($_POST['aipc_woocommerce_enabled']) ? 1 : 0);
        update_option('aipc_woocommerce_auto_sync', isset($_POST['aipc_woocommerce_auto_sync']) ? 1 : 0);
        update_option('aipc_woocommerce_show_cart', isset($_POST['aipc_woocommerce_show_cart']) ? 1 : 0);
        update_option('aipc_woocommerce_ingredients_field', sanitize_text_field($_POST['aipc_woocommerce_ingredients_field'] ?? '_ingredients'));
        update_option('aipc_woocommerce_skin_types_field', sanitize_text_field($_POST['aipc_woocommerce_skin_types_field'] ?? '_skin_types'));
        
        // Skin test settings
        if (isset($_POST['aipc_skin_test_questions'])) {
            update_option('aipc_skin_test_questions', is_string($_POST['aipc_skin_test_questions']) ? wp_unslash($_POST['aipc_skin_test_questions']) : '');
        }
        if (isset($_POST['aipc_skin_test_mapping'])) {
            update_option('aipc_skin_test_mapping', is_string($_POST['aipc_skin_test_mapping']) ? wp_unslash($_POST['aipc_skin_test_mapping']) : '');
        }
    } // End license check
    
    // Show appropriate success message
    if ($is_license_active) {
        echo '<div class="notice notice-success"><p>' . __('Instellingen opgeslagen!', 'ai-product-chatbot') . '</p></div>';
    } else {
        echo '<div class="notice notice-success"><p>' . __('Licentie server URLs opgeslagen! Activeer nu je licentie om alle instellingen te ontgrendelen.', 'ai-product-chatbot') . '</p></div>';
    }
}

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
$allow_judgement = get_option('aipc_allow_judgement', 0);
$tips_style = get_option('aipc_tips_style', 'default');
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

// Content Sources current config
$use_woo_source = (bool) get_option('aipc_source_use_woocommerce', class_exists('WooCommerce'));
$content_sources = get_option('aipc_content_sources', []);
$public_types = get_post_types(['public' => true], 'objects');

// Preset for 'vacature' CPT if available and not yet configured
if (isset($public_types['vacature']) && empty($content_sources['vacature'])) {
    $content_sources['vacature'] = [
        'enabled'   => true,
        'fields'    => ['title','excerpt','link'],
        'meta_keys' => ['locatie','salaris'],
        'max_items' => 10,
        'max_chars' => 400,
        'orderby'   => 'date',
        'order'     => 'DESC',
        'tax'       => [],
    ];
    // Persist preset so het standaard actief is
    update_option('aipc_content_sources', $content_sources);
}

// AVG status helpers
$auto_purge_scheduled = (bool) wp_next_scheduled('aipc_daily_purge');
$email_masking_on = (bool)$mask_email;
$phone_masking_on = (bool)$mask_phone;
$storage_ok = (!$store_conversations) || ($store_conversations && $retention_days > 0 && $auto_purge_scheduled);
$avg_pass = ($email_masking_on && $phone_masking_on && $storage_ok);
?>

<?php
// Check license status early for UI decisions
if (!class_exists('AIPC_License')) {
    require_once AIPC_PLUGIN_DIR . 'includes/class-aipc-license.php';
}
$license = AIPC_License::getInstance();
$current_tier = $license->get_current_tier();
$is_active = $license->is_active();
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
        <!-- Full Settings Mode -->
        <form method="post" action="">
            <?php wp_nonce_field('aipc_save_settings', 'aipc_settings_nonce'); ?>
            
            <!-- BASIC LICENSE FEATURES - Always available with any active license -->
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
                            </select>
                            <p class="description">
                                <?php _e('Het visuele thema van de chatbot.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- BUSINESS+ LICENSE FEATURES -->
            <?php if ($license->has_feature('api_integrations')): ?>
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
                            <p class="description">
                                <?php if ($provider === 'openrouter'): ?>
                                    <?php _e('Je OpenRouter API key. Krijg er een op', 'ai-product-chatbot'); ?> 
                                    <a href="https://openrouter.ai/keys" target="_blank">openrouter.ai/keys</a>
                                <?php else: ?>
                                    <?php _e('Je OpenAI API key. Krijg er een op', 'ai-product-chatbot'); ?> 
                                    <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
            
            <tr>
                <th scope="row">
                    <label for="aipc_openai_model"><?php _e('Model', 'ai-product-chatbot'); ?></label>
                </th>
                <td>
                    <select id="aipc_openai_model" name="aipc_openai_model"></select>
                    <p class="description">
                        <?php _e('Kies het model. Voor OpenRouter gebruik je hun modelnamen.', 'ai-product-chatbot'); ?>
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
                    <label for="aipc_fallback_message"><?php _e('Fallback bericht (zonder AI)', 'ai-product-chatbot'); ?></label>
                </th>
                <td>
                    <textarea id="aipc_fallback_message" name="aipc_fallback_message" 
                              rows="3" cols="50" class="large-text"><?php echo esc_textarea($fallback_message); ?></textarea>
                    <p class="description">
                        <?php _e('Wordt gebruikt als er geen AI‑antwoord is en geen FAQ/productmatch gevonden wordt.', 'ai-product-chatbot'); ?>
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
                    </select>
                    <p class="description">
                        <?php _e('Het visuele thema van de chatbot.', 'ai-product-chatbot'); ?>
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
                    <input type="checkbox" id="aipc_allow_judgement" name="aipc_allow_judgement" value="1" <?php checked($allow_judgement); ?> />
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
                        <option value="default" <?php selected($tips_style, 'default'); ?>><?php _e('Standaard (vrij)', 'ai-product-chatbot'); ?></option>
                        <option value="bullets_3_short" <?php selected($tips_style, 'bullets_3_short'); ?>><?php _e('3 bullets, 1 zin per bullet', 'ai-product-chatbot'); ?></option>
                        <option value="bullets_5_short" <?php selected($tips_style, 'bullets_5_short'); ?>><?php _e('5 bullets, 1 zin per bullet', 'ai-product-chatbot'); ?></option>
                    </select>
                    <p class="description"><?php _e('Bepaalt het antwoordformat voor “tips”-achtige vragen wanneer AI samenvat.', 'ai-product-chatbot'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="aipc_mask_email"><?php _e('E‑mail maskeren', 'ai-product-chatbot'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="aipc_mask_email" name="aipc_mask_email" value="1" <?php checked($mask_email); ?> />
                    <p class="description"><?php _e('Verberg e‑mailadressen volledig in chat en opslag.', 'ai-product-chatbot'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="aipc_mask_phone"><?php _e('Telefoon maskeren', 'ai-product-chatbot'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="aipc_mask_phone" name="aipc_mask_phone" value="1" <?php checked($mask_phone); ?> />
                    <p class="description"><?php _e('Verberg telefoonnummers volledig in chat en opslag.', 'ai-product-chatbot'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="aipc_store_conversations"><?php _e('Gesprekken opslaan', 'ai-product-chatbot'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="aipc_store_conversations" name="aipc_store_conversations" value="1" <?php checked($store_conversations); ?> />
                    <label for="aipc_store_conversations"><?php _e('Sla chatgesprekken lokaal op', 'ai-product-chatbot'); ?></label>
                    <p class="description"><?php _e('Schakel uit voor maximale dataminimalisatie. Analytics kunnen beperkter zijn.', 'ai-product-chatbot'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="aipc_retention_days"><?php _e('Bewaartermijn (dagen)', 'ai-product-chatbot'); ?></label>
                </th>
                <td>
                    <input type="number" id="aipc_retention_days" name="aipc_retention_days" class="small-text" min="0" value="<?php echo esc_attr($retention_days); ?>" />
                    <p class="description"><?php _e('Conversaties ouder dan dit aantal dagen worden verwijderd bij purge/auto-purge. 0 = niet automatisch verwijderen.', 'ai-product-chatbot'); ?></p>
                    <button type="button" class="button" id="aipc-purge-conversations"><?php _e('Verwijder oude gesprekken nu', 'ai-product-chatbot'); ?></button>
                    <button type="button" class="button" id="aipc-export-conversations"><?php _e('Exporteren (JSON)', 'ai-product-chatbot'); ?></button>
                    <div id="aipc-purge-export-result" style="margin-top:10px;"></div>
                </td>
            </tr>
        </table>
    
        

    <?php if ($license->has_feature('api_integrations')): ?>
    <div class="aipc-settings-section">
        <h2><?php _e('AI Persona & Prompts', 'ai-product-chatbot'); ?></h2>
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
Je bent een behulpzame productassistent voor [Jouw Bedrijf]. Je helpt klanten het juiste product vinden op basis van hun behoeften, budget en voorkeuren."><?php echo esc_textarea($system_prompt); ?></textarea>
                    <p class="description"><?php _e('<strong>Hier definieer je jouw business!</strong> Dit bepaalt hoe de AI zich gedraagt - veel krachtiger dan een dropdown. Kopieer een voorbeeld en pas aan naar jouw situatie.', 'ai-product-chatbot'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="aipc_greet_with_products"><?php _e('Groet (met productcontext)', 'ai-product-chatbot'); ?> <span class="dashicons dashicons-editor-help" title="Voorbeeld: Hallo! Ik kan je helpen met aanbevelingen op basis van onze catalogus. Waar ben je naar op zoek?"></span></label></th>
                <td>
                    <textarea id="aipc_greet_with_products" name="aipc_greet_with_products" rows="3" class="large-text" placeholder="Hallo! Ik kan je helpen met aanbevelingen op basis van onze catalogus. Waar ben je naar op zoek?
(Leeg laten = standaard)"><?php echo esc_textarea($greet_with_products); ?></textarea>
                    <p class="description"><?php _e('Gebruik aangepaste groet als er productcontext is. Leeg = standaard.', 'ai-product-chatbot'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="aipc_greet_without_products"><?php _e('Groet (zonder productcontext)', 'ai-product-chatbot'); ?> <span class="dashicons dashicons-editor-help" title="Voorbeeld: Hallo! Ik help je met productadvies en ingrediënteninformatie. Hoe kan ik je vandaag helpen?"></span></label></th>
                <td>
                    <textarea id="aipc_greet_without_products" name="aipc_greet_without_products" rows="3" class="large-text" placeholder="Hallo! Ik help je met productadvies en ingrediënteninformatie. Hoe kan ik je vandaag helpen?
(Leeg laten = standaard)"><?php echo esc_textarea($greet_without_products); ?></textarea>
                    <p class="description"><?php _e('Gebruik aangepaste groet als er geen productcontext is. Leeg = standaard.', 'ai-product-chatbot'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="aipc_max_faq_items"><?php _e('Max FAQ items in prompt', 'ai-product-chatbot'); ?> <span class="dashicons dashicons-editor-help" title="Voorbeeld prompt-fragment: ALGEMENE FAQ: Q: Wat zijn de verzendkosten? A: Boven €50 gratis, anders €4,95. Q: Hoe lang is de levertijd? A: 1–2 werkdagen. Q: Geschikt voor gevoelige huid? A: Ja, let op aloë vera/niacinamide/ceramiden."></span></label></th>
                <td>
                    <input type="number" id="aipc_max_faq_items" name="aipc_max_faq_items" class="small-text" min="0" value="<?php echo esc_attr($max_faq_items); ?>" />
                    <p class="description"><?php _e('Hoeveel FAQ Q&A’s maximaal worden toegevoegd aan de AI-“system prompt”. Hoger = meer context (maar langere prompt en mogelijk hogere kosten). 0 = geen FAQ meenemen.', 'ai-product-chatbot'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="aipc_max_doc_snippet"><?php _e('Max doc snippet lengte', 'ai-product-chatbot'); ?> <span class="dashicons dashicons-editor-help" title="Voorbeeld: 500 (neem per document max 500 tekens op)"></span></label></th>
                <td>
                    <input type="number" id="aipc_max_doc_snippet" name="aipc_max_doc_snippet" class="small-text" min="0" value="<?php echo esc_attr($max_doc_snippet); ?>" />
                    <p class="description"><?php _e('Maximum aantal tekens per document dat in de AI-“system prompt” wordt opgenomen. Helpt om de prompt compact en snel te houden. 0 = geen documentinhoud toevoegen.', 'ai-product-chatbot'); ?></p>
                </td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!$license->has_feature('api_integrations')): ?>
    <!-- Basic License Limited Settings -->
    <div class="aipc-settings-section">
        <h2><?php _e('✨ Basic Licentie Upgrade Opties', 'ai-product-chatbot'); ?></h2>
        <div class="notice notice-info inline" style="margin: 0 0 20px 0; padding: 15px;">
            <p><strong><?php _e('🔒 Geavanceerde Instellingen:', 'ai-product-chatbot'); ?></strong><br>
                <?php _e('AI Persona & Prompts, Token Pricing en geavanceerde Content Sources zijn beschikbaar met Business+ licentie.', 'ai-product-chatbot'); ?><br>
                <?php _e('Upgrade nu voor volledige controle over je AI chatbot!', 'ai-product-chatbot'); ?>
            </p>
            <a href="<?php echo esc_url($license->generate_upgrade_url('business')); ?>" class="button button-primary" style="margin-top: 10px;">
                <?php _e('🚀 Upgrade naar Business', 'ai-product-chatbot'); ?>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php submit_button(__('Instellingen Opslaan', 'ai-product-chatbot')); ?>

    <div class="aipc-settings-section">
        <h2><?php _e('Shortcode Gebruik', 'ai-product-chatbot'); ?></h2>
        <p><?php _e('Gebruik de volgende shortcode om de chatbot in te voegen op specifieke pagina\'s:', 'ai-product-chatbot'); ?></p>
        <code>[aipc_chatbot]</code>
        
        <h3><?php _e('Shortcode Opties', 'ai-product-chatbot'); ?></h3>
        <ul>
            <li><code>title</code> - <?php _e('Aangepaste titel voor de chatbot', 'ai-product-chatbot'); ?></li>
            <li><code>theme</code> - <?php _e('Thema (modern, minimal, colorful)', 'ai-product-chatbot'); ?></li>
            <li><code>welcome_message</code> - <?php _e('Aangepast welkomstbericht', 'ai-product-chatbot'); ?></li>
        </ul>
        
        <h3><?php _e('Voorbeelden', 'ai-product-chatbot'); ?></h3>
        <code>[aipc_chatbot title="Mijn Assistent" theme="colorful"]</code><br>
        <code>[aipc_chatbot welcome_message="Hoe kan ik je helpen met onze producten?"]</code>
    </div>
    
    <?php if ($license->has_feature('api_integrations')): ?>
    <div class="aipc-settings-section">
        <h2><?php _e('Token Pricing (Cost Estimate)', 'ai-product-chatbot'); ?></h2>
        <p class="description"><?php _e('Stel tarieven per 1.000 tokens in voor het geselecteerde model. Wordt gebruikt voor kostenindicaties op basis van gemelde usage (prompt/completion tokens).', 'ai-product-chatbot'); ?></p>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Input (Prompt) rate / 1K', 'ai-product-chatbot'); ?></th>
                <td>
                    <input type="number" step="0.0001" min="0" name="aipc_token_input_rate_per_1k" value="<?php echo esc_attr($token_in_rate); ?>" /> €
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Output (Completion) rate / 1K', 'ai-product-chatbot'); ?></th>
                <td>
                    <input type="number" step="0.0001" min="0" name="aipc_token_output_rate_per_1k" value="<?php echo esc_attr($token_out_rate); ?>" /> €
                </td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <div class="aipc-settings-section">
        <h2><?php _e('Content Sources', 'ai-product-chatbot'); ?></h2>
        <p class="description"><?php _e('Kies welke content-bronnen de chatbot mag gebruiken (WooCommerce en/of Custom Post Types).', 'ai-product-chatbot'); ?></p>
        
        <?php 
        // Content Sources license checking
        $content_license_allowed = true;
        $basic_cpt_limit = 3; // Basic tier can use max 3 post types (post, page, product)
        $allowed_basic_types = ['post', 'page', 'product']; // Basic tier allowed types
        
        if (class_exists('AIPC_License')) {
            $lic = AIPC_License::getInstance();
            $current_tier = $lic->get_current_tier();
            $is_active = $lic->is_active();
            
            if ($current_tier === 'basic' || !$is_active) {
                $content_license_allowed = false;
            }
            $upgrade_url = $lic->generate_upgrade_url('business');
        }
        
        if (!$content_license_allowed): ?>
        <div class="notice notice-info inline" style="margin: 0 0 15px 0;">
            <p>
                <strong><?php _e('🔒 Content Sources Beperking (Basic/Geen Licentie):', 'ai-product-chatbot'); ?></strong><br>
                <?php _e('• WooCommerce bron: Volledig beschikbaar', 'ai-product-chatbot'); ?><br>
                <?php _e('• Standaard post types (post, page, product): Basis configuratie', 'ai-product-chatbot'); ?><br>
                <?php _e('• Custom Post Types: Niet beschikbaar', 'ai-product-chatbot'); ?><br>
                <?php _e('• Geavanceerde opties (meta fields, taxonomie filters, templates): Niet beschikbaar', 'ai-product-chatbot'); ?><br>
                <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-secondary" style="margin-top:8px;">
                    <?php _e('Upgrade naar Business voor volledige Content Sources', 'ai-product-chatbot'); ?>
                </a>
            </p>
        </div>
        <?php endif; ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('WooCommerce productbron gebruiken', 'ai-product-chatbot'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="aipc_source_use_woocommerce" value="1" <?php checked($use_woo_source); ?> <?php disabled(!class_exists('WooCommerce')); ?> />
                        <?php _e('Gebruik WooCommerce-specifieke productcontext (aanbevolen wanneer WooCommerce actief is).', 'ai-product-chatbot'); ?>
                    </label>
                    <?php if (!class_exists('WooCommerce')): ?>
                        <p class="description" style="color:#d63638;">
                            <?php _e('WooCommerce is niet actief. Deze optie heeft geen effect.', 'ai-product-chatbot'); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <h3><?php _e('Custom Post Types selecteren', 'ai-product-chatbot'); ?></h3>
        <p class="description"><?php _e('Schakel per post type in wat de chatbot mag meenemen. Voor elk type kun je velden en limieten instellen. Product (WooCommerce) kun je óf via de WooCommerce bron (boven) gebruiken, óf als generiek post type.', 'ai-product-chatbot'); ?></p>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Post Type', 'ai-product-chatbot'); ?></th>
                    <th><?php _e('Inschakelen', 'ai-product-chatbot'); ?></th>
                    <th><?php _e('Velden', 'ai-product-chatbot'); ?></th>
                    <th><?php _e('Meta keys (comma)', 'ai-product-chatbot'); ?></th>
                    <th><?php _e('Max items', 'ai-product-chatbot'); ?></th>
                    <th><?php _e('Max chars', 'ai-product-chatbot'); ?></th>
                    <th><?php _e('Sortering', 'ai-product-chatbot'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $row_count = 0;
                foreach ($public_types as $pt => $obj): 
                    // Skip attachments
                    if ($pt === 'attachment') continue;
                    
                    // License restrictions
                    $is_basic_allowed = in_array($pt, $allowed_basic_types, true);
                    $row_disabled = (!$content_license_allowed && !$is_basic_allowed);
                    $advanced_disabled = !$content_license_allowed;
                    
                    $cfg = $content_sources[$pt] ?? [];
                    $enabled = !empty($cfg['enabled']);
                    $fields = $cfg['fields'] ?? ['title','excerpt','link'];
                    $meta_keys = isset($cfg['meta_keys']) ? implode(', ', (array)$cfg['meta_keys']) : '';
                    $max_items = isset($cfg['max_items']) ? intval($cfg['max_items']) : 5;
                    $max_chars = isset($cfg['max_chars']) ? intval($cfg['max_chars']) : 400;
                    $orderby = $cfg['orderby'] ?? 'date';
                    $order = $cfg['order'] ?? 'DESC';
                    
                    $row_count++;
                ?>
                <tr<?php if ($row_disabled) echo ' class="aipc-disabled-row" style="opacity:0.5; background:#f9f9f9;"'; ?>>
                    <td>
                        <strong><?php echo esc_html($obj->labels->name . ' (' . $pt . ')'); ?></strong>
                        <?php if ($row_disabled): ?>
                            <span class="dashicons dashicons-lock" title="<?php esc_attr_e('Vereist Business+ licentie', 'ai-product-chatbot'); ?>" style="color:#d63638; margin-left:5px;"></span>
                            <div class="description" style="color:#d63638;">
                                <?php _e('Custom Post Type - Vereist Business+ licentie', 'ai-product-chatbot'); ?>
                            </div>
                        <?php elseif ($advanced_disabled && $is_basic_allowed): ?>
                            <span class="dashicons dashicons-info" title="<?php esc_attr_e('Basis configuratie - Upgrade voor geavanceerde opties', 'ai-product-chatbot'); ?>" style="color:#f0b849; margin-left:5px;"></span>
                            <div class="description" style="color:#f0b849;">
                                <?php _e('Basis configuratie beschikbaar - Upgrade voor meta fields, taxonomieën en templates', 'ai-product-chatbot'); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($pt === 'product' && class_exists('WooCommerce') && $use_woo_source): ?>
                            <div class="description" style="color:#646970;">
                                <?php _e('Wordt al gedekt via WooCommerce-bron hierboven (deze rij kun je uit laten).', 'ai-product-chatbot'); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <label>
                            <input type="checkbox" name="aipc_content_sources[<?php echo esc_attr($pt); ?>][enabled]" value="1" <?php checked($enabled); ?> <?php disabled($row_disabled); ?> />
                        </label>
                    </td>
                    <td>
                        <label style="margin-right:8px;">
                            <input type="checkbox" name="aipc_content_sources[<?php echo esc_attr($pt); ?>][fields][title]" value="1" <?php checked(in_array('title', $fields, true)); ?> <?php disabled($row_disabled); ?> /> <?php _e('Titel', 'ai-product-chatbot'); ?>
                        </label>
                        <label style="margin-right:8px;">
                            <input type="checkbox" name="aipc_content_sources[<?php echo esc_attr($pt); ?>][fields][excerpt]" value="1" <?php checked(in_array('excerpt', $fields, true)); ?> <?php disabled($row_disabled); ?> /> <?php _e('Excerpt', 'ai-product-chatbot'); ?>
                        </label>
                        <label style="margin-right:8px;">
                            <input type="checkbox" name="aipc_content_sources[<?php echo esc_attr($pt); ?>][fields][content]" value="1" <?php checked(in_array('content', $fields, true)); ?> <?php disabled($row_disabled); ?> /> <?php _e('Content', 'ai-product-chatbot'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="aipc_content_sources[<?php echo esc_attr($pt); ?>][fields][link]" value="1" <?php checked(in_array('link', $fields, true)); ?> <?php disabled($row_disabled); ?> /> <?php _e('Link', 'ai-product-chatbot'); ?>
                        </label>
                    </td>
                    <td>
                        <input type="text" class="regular-text" name="aipc_content_sources[<?php echo esc_attr($pt); ?>][meta_keys]" value="<?php echo esc_attr($meta_keys); ?>" placeholder="<?php echo $advanced_disabled ? __('Premium feature', 'ai-product-chatbot') : __('locatie, salaris', 'ai-product-chatbot'); ?>" <?php disabled($advanced_disabled); ?> />
                        <?php if ($advanced_disabled): ?>
                            <p class="description" style="color:#d63638;"><?php _e('Meta fields vereisen Business+ licentie', 'ai-product-chatbot'); ?></p>
                        <?php endif; ?>
                    </td>
                    <td>
                        <input type="number" class="small-text" name="aipc_content_sources[<?php echo esc_attr($pt); ?>][max_items]" value="<?php echo esc_attr($max_items); ?>" min="1" <?php disabled($row_disabled); ?> />
                    </td>
                    <td>
                        <input type="number" class="small-text" name="aipc_content_sources[<?php echo esc_attr($pt); ?>][max_chars]" value="<?php echo esc_attr($max_chars); ?>" min="50" <?php disabled($row_disabled); ?> />
                    </td>
                    <td>
                        <select name="aipc_content_sources[<?php echo esc_attr($pt); ?>][orderby]" <?php disabled($row_disabled); ?>>
                            <option value="date" <?php selected($orderby, 'date'); ?>><?php _e('Datum', 'ai-product-chatbot'); ?></option>
                            <option value="title" <?php selected($orderby, 'title'); ?>><?php _e('Titel', 'ai-product-chatbot'); ?></option>
                        </select>
                        <select name="aipc_content_sources[<?php echo esc_attr($pt); ?>][order]" <?php disabled($row_disabled); ?>>
                            <option value="DESC" <?php selected($order, 'DESC'); ?>>DESC</option>
                            <option value="ASC" <?php selected($order, 'ASC'); ?>>ASC</option>
                        </select>
                    </td>
                </tr>
                <?php if (!$advanced_disabled): ?>
                <tr class="aipc-cpt-advanced">
                    <td colspan="7">
                        <details>
                            <summary><?php _e('Filters & Template', 'ai-product-chatbot'); ?></summary>
                            <div style="margin-top:10px; display:flex; gap:20px; flex-wrap:wrap;">
                                <div style="min-width:300px;">
                                    <h4 style="margin:0 0 8px;"><?php _e('Taxonomie filters', 'ai-product-chatbot'); ?></h4>
                                    <p class="description"><?php _e('Geef per taxonomie slugs, komma-gescheiden. Voorbeeld: amsterdam, fulltime', 'ai-product-chatbot'); ?></p>
                                    <?php 
                                    $tax_objs = get_object_taxonomies($pt, 'objects');
                                    if (!empty($tax_objs)):
                                        foreach ($tax_objs as $tax_name => $tax_obj):
                                            $existing_terms = '';
                                            if (!empty($cfg['tax'][$tax_name])) { $existing_terms = implode(', ', (array)$cfg['tax'][$tax_name]); }
                                    ?>
                                        <label style="display:block; margin-bottom:6px;">
                                            <strong><?php echo esc_html($tax_obj->labels->name . ' (' . $tax_name . ')'); ?></strong><br>
                                            <input type="text" class="regular-text" name="aipc_content_sources[<?php echo esc_attr($pt); ?>][tax][<?php echo esc_attr($tax_name); ?>]" value="<?php echo esc_attr($existing_terms); ?>" placeholder="term-slug-1, term-slug-2" <?php disabled($advanced_disabled); ?> />
                                        </label>
                                    <?php 
                                        endforeach;
                                    else:
                                    ?>
                                        <p class="description"><?php _e('Geen taxonomieën voor dit post type.', 'ai-product-chatbot'); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div style="flex:1; min-width:300px;">
                                    <h4 style="margin:0 0 8px;"><?php _e('Label Template', 'ai-product-chatbot'); ?></h4>
                                    <?php $template_val = isset($cfg['template']) ? (string)$cfg['template'] : ''; ?>
                                    <textarea class="large-text" rows="6" name="aipc_content_sources[<?php echo esc_attr($pt); ?>][template]" placeholder="- {title}
  Locatie: {meta:locatie}
  Salaris: {meta:salaris}
  Samenvatting: {excerpt}
  URL: {link}
" <?php disabled($advanced_disabled); ?>><?php echo esc_textarea($template_val); ?></textarea>
                                    <p class="description">
                                        <?php _e('Gebruik placeholders: {title}, {excerpt}, {content}, {link} en {meta:veldnaam}. Excerpt/content worden automatisch ingekort tot Max chars.', 'ai-product-chatbot'); ?>
                                    </p>
                                </div>
                            </div>
                        </details>
                    </td>
                </tr>
                <?php else: ?>
                <tr class="aipc-cpt-advanced-disabled">
                    <td colspan="7" style="background:#f9f9f9; padding:15px; text-align:center; border-left:4px solid #f0b849;">
                        <strong><?php _e('🔒 Geavanceerde Content Sources Features', 'ai-product-chatbot'); ?></strong><br>
                        <p class="description" style="margin:8px 0;">
                            <?php _e('Taxonomie filters, meta fields en custom templates zijn beschikbaar met Business+ licentie.', 'ai-product-chatbot'); ?>
                        </p>
                        <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-secondary">
                            <?php _e('Upgrade naar Business', 'ai-product-chatbot'); ?>
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="aipc-settings-section">
        <h2><?php _e('Features', 'ai-product-chatbot'); ?></h2>
        <div class="notice notice-success inline" style="margin: 0 0 15px 0; padding: 10px;">
            <p><strong><?php _e('✅ Business Type:', 'ai-product-chatbot'); ?></strong> <?php _e('Geen configuratie nodig! Definieer je business in het <strong>System Prompt</strong> hierboven - dat werkt voor elke branche.', 'ai-product-chatbot'); ?></p>
        </div>
        <p class="description"><?php _e('Schakel hier optionele features in of uit:', 'ai-product-chatbot'); ?></p>
        
        <?php 
        // Load license info
        if (!class_exists('AIPC_License')) {
            require_once AIPC_PLUGIN_DIR . 'includes/class-aipc-license.php';
        }
        $license = AIPC_License::getInstance();
        $current_tier = $license->get_current_tier();
        $is_active = $license->is_active();
        ?>
        
        <table class="form-table">
            
            <tr>
                <th scope="row">
                    <label for="aipc_enable_skin_test"><?php _e('Producttest/Product Quiz', 'ai-product-chatbot'); ?></label>
                </th>
                <td>
                    <?php 
                    $can_skin_test = ($is_active && $license->has_feature('custom_skin_test'));
                    // Default to false for non-licensed users to avoid upgrade prompts
                    $default_enabled = $can_skin_test ? true : false;
                    $skin_checked = get_option('aipc_enable_skin_test', $default_enabled);
                    ?>
                    <input type="checkbox" id="aipc_enable_skin_test" name="aipc_enable_skin_test" 
                           value="1" <?php checked($skin_checked); ?> <?php disabled(!$can_skin_test); ?> />
                    <label for="aipc_enable_skin_test">
                        <?php _e('Interactieve test voor gepersonaliseerde aanbevelingen', 'ai-product-chatbot'); ?>
                        <?php if (!$can_skin_test): ?>
                            <span class="dashicons dashicons-lock" title="<?php esc_attr_e('Vereist Business of Enterprise licentie', 'ai-product-chatbot'); ?>" style="vertical-align: text-bottom; color:#d63638;"></span>
                        <?php endif; ?>
                    </label>
                    <?php if (!$can_skin_test): ?>
                        <p class="description" style="color:#d63638;">
                            <?php _e('Deze functie is beschikbaar met een Business of Enterprise licentie.', 'ai-product-chatbot'); ?>
                            <a href="<?php echo esc_url($license->generate_upgrade_url('business')); ?>" class="button button-secondary" style="margin-left:10px;">
                                <?php _e('Upgrade naar Business', 'ai-product-chatbot'); ?>
                            </a>
                        </p>
                    <?php else: ?>
                        <p class="description">
                        <?php _e('Interactieve test/quiz voor gepersonaliseerde productaanbevelingen, aangepast per business type.', 'ai-product-chatbot'); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="aipc_enable_product_recommendations"><?php _e('Smart Product Recommendations', 'ai-product-chatbot'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="aipc_enable_product_recommendations" name="aipc_enable_product_recommendations" 
                           value="1" <?php checked(get_option('aipc_enable_product_recommendations', true)); ?> />
                    <label for="aipc_enable_product_recommendations"><?php _e('Geavanceerde productaanbevelingen op basis van gebruikersinput', 'ai-product-chatbot'); ?></label>
                    <p class="description">
                        <?php _e('AI-gedreven matching van producten aan gebruikersbehoeften, onafhankelijk van de quiz functionaliteit.', 'ai-product-chatbot'); ?>
                    </p>
                </td>
            </tr>
        </table>
    </div>
    
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
                    <input type="url" name="aipc_license_server_url" value="<?php echo esc_attr(get_option('aipc_license_server_url', '')); ?>" class="regular-text" placeholder="https://your-license-server.com" />
                    <p class="description">
                        <?php _e('URL van je licentie server (waar de license manager plugin geïnstalleerd is).', 'ai-product-chatbot'); ?>
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
            
            <?php if (!$is_active): ?>
            <tr>
                <th scope="row"><?php _e('Licentie Key', 'ai-product-chatbot'); ?></th>
                <td>
                    <input type="text" id="aipc_license_key" placeholder="AIPC-BUSINESS-12345678" class="regular-text" />
                    <button type="button" id="aipc_activate_license" class="button button-primary"><?php _e('Activeren', 'ai-product-chatbot'); ?></button>
                    <p class="description">
                        <?php _e('Voer je licentie key in om premium features te ontgrendelen.', 'ai-product-chatbot'); ?>
                        <a href="https://your-website.com/purchase" target="_blank"><?php _e('Koop een licentie', 'ai-product-chatbot'); ?></a>
                    </p>
                    <div id="aipc_license_result" style="margin-top: 10px;"></div>
                </td>
            </tr>
            <?php else: ?>
            <tr>
                <th scope="row"><?php _e('Licentie Info', 'ai-product-chatbot'); ?></th>
                <td>
                    <p><strong><?php _e('Key:', 'ai-product-chatbot'); ?></strong> <?php echo esc_html(substr($license_info['key'], 0, 15) . '...'); ?></p>
                    <p><strong><?php _e('Domain:', 'ai-product-chatbot'); ?></strong> <?php echo esc_html($license_info['domain']); ?></p>
                    <p><strong><?php _e('Verloopt:', 'ai-product-chatbot'); ?></strong> <?php echo esc_html($license_info['expires']); ?></p>
                    <button type="button" id="aipc_deactivate_license" class="button button-secondary"><?php _e('Deactiveren', 'ai-product-chatbot'); ?></button>
                </td>
            </tr>
            <?php if (!empty($grace['in_grace'])): ?>
            <tr>
                <th scope="row">&nbsp;</th>
                <td>
                    <?php 
                    $remaining = isset($grace['remaining_seconds']) ? (int)$grace['remaining_seconds'] : 0;
                    $hours = max(1, (int) ceil($remaining / 3600));
                    ?>
                    <div class="notice notice-warning inline">
                        <p>
                            <?php echo esc_html(sprintf(__('Grace‑mode actief: licentieserver onbereikbaar. Ongeveer %d uur resterend.', 'ai-product-chatbot'), $hours)); ?>
                            <button type="button" class="button button-primary aipc-force-validate-license" style="margin-left:10px;" data-nonce="<?php echo esc_attr(wp_create_nonce('aipc_test_api')); ?>"><?php _e('Nu opnieuw valideren', 'ai-product-chatbot'); ?></button>
                        </p>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
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
    
            <div class="aipc-settings-section">
            <h2><?php _e('WooCommerce Integratie', 'ai-product-chatbot'); ?></h2>
            <?php if (class_exists('WooCommerce')): ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="aipc_woocommerce_enabled"><?php _e('WooCommerce Integratie', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="aipc_woocommerce_enabled" name="aipc_woocommerce_enabled" 
                                   value="1" <?php checked(get_option('aipc_woocommerce_enabled', true)); ?> />
                            <label for="aipc_woocommerce_enabled"><?php _e('WooCommerce integratie inschakelen', 'ai-product-chatbot'); ?></label>
                            <p class="description">
                                <?php _e('Schakel WooCommerce integratie in. Automatische sync bij wijzigingen + individuele handmatige sync per product inbegrepen. Bulk "sync alle producten" vereist Business+ upgrade.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_woocommerce_auto_sync"><?php _e('Automatische Sync', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="aipc_woocommerce_auto_sync" name="aipc_woocommerce_auto_sync" 
                                   value="1" <?php checked(get_option('aipc_woocommerce_auto_sync', true)); ?> />
                            <label for="aipc_woocommerce_auto_sync"><?php _e('Producten automatisch synchroniseren bij wijzigingen', 'ai-product-chatbot'); ?></label>
                            <p class="description">
                                <?php _e('Wanneer ingeschakeld worden producten automatisch gesynchroniseerd naar de chatbot als ze worden toegevoegd, bewerkt of verwijderd in WooCommerce.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_woocommerce_show_cart"><?php _e('Shopping Cart', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="aipc_woocommerce_show_cart" name="aipc_woocommerce_show_cart" 
                                   value="1" <?php checked(get_option('aipc_woocommerce_show_cart', true)); ?> />
                            <label for="aipc_woocommerce_show_cart"><?php _e('Shopping cart functionaliteit in chatbot', 'ai-product-chatbot'); ?></label>
                            <p class="description">
                                <?php _e('Toon shopping cart knoppen en functionaliteit in chatbot antwoorden.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_woocommerce_ingredients_field"><?php _e('Eigenschappen Custom Field', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <?php 
                            $can_custom_fields = ($is_active && $license->has_feature('api_integrations'));
                            ?>
                            <input type="text" id="aipc_woocommerce_ingredients_field" name="aipc_woocommerce_ingredients_field" 
                                   value="<?php echo esc_attr(get_option('aipc_woocommerce_ingredients_field', '_ingredients')); ?>" 
                                   class="regular-text" <?php disabled(!$can_custom_fields); ?> />
                            <?php if (!$can_custom_fields): ?>
                                <span class="dashicons dashicons-lock" title="<?php esc_attr_e('Vereist Business of Enterprise licentie', 'ai-product-chatbot'); ?>" style="vertical-align: text-bottom; color:#d63638;"></span>
                            <?php endif; ?>
                            <?php if (!$can_custom_fields): ?>
                                <p class="description" style="color:#d63638;">
                                    <?php _e('Custom Fields zijn beschikbaar met een Business of Enterprise licentie. Fallback: Product Tags worden gebruikt.', 'ai-product-chatbot'); ?>
                                </p>
                            <?php else: ?>
                                <p class="description">
                                    <?php _e('Custom field naam voor product eigenschappen (bijv. ingrediënten, materialen, specificaties). Fallback: Product Tags.', 'ai-product-chatbot'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aipc_woocommerce_skin_types_field"><?php _e('Geschikt Voor Custom Field', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="aipc_woocommerce_skin_types_field" name="aipc_woocommerce_skin_types_field" 
                                   value="<?php echo esc_attr(get_option('aipc_woocommerce_skin_types_field', '_skin_types')); ?>" 
                                   class="regular-text" <?php disabled(!$can_custom_fields); ?> />
                            <?php if (!$can_custom_fields): ?>
                                <span class="dashicons dashicons-lock" title="<?php esc_attr_e('Vereist Business of Enterprise licentie', 'ai-product-chatbot'); ?>" style="vertical-align: text-bottom; color:#d63638;"></span>
                            <?php endif; ?>
                            <?php if (!$can_custom_fields): ?>
                                <p class="description" style="color:#d63638;">
                                    <?php _e('Custom Fields zijn beschikbaar met een Business of Enterprise licentie. Fallback: Product Categories worden gebruikt.', 'ai-product-chatbot'); ?>
                                </p>
                            <?php else: ?>
                                <p class="description">
                                    <?php _e('Custom field naam voor doelgroep/geschiktheid (bijv. huidtypes, leeftijdsgroepen, gebruik). Fallback: Product Categories.', 'ai-product-chatbot'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Instellingen Opslaan', 'ai-product-chatbot')); ?>
            <?php else: ?>
                <div class="notice notice-info">
                    <p><strong><?php _e('WooCommerce niet geïnstalleerd', 'ai-product-chatbot'); ?></strong></p>
                    <p><?php _e('Installeer en activeer WooCommerce om e-commerce functionaliteit te gebruiken.', 'ai-product-chatbot'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="aipc-settings-section">
            <h2><?php _e('AVG status (technisch)', 'ai-product-chatbot'); ?></h2>
            <p class="description"><?php _e('Indicatie van de technische privacy-instellingen. Juridische vereisten (privacyverklaring, verwerkersovereenkomsten) worden hier niet gemeten.', 'ai-product-chatbot'); ?></p>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('E‑mail maskering', 'ai-product-chatbot'); ?></th>
                    <td>
                        <?php if ($email_masking_on): ?>
                            <span class="dashicons dashicons-yes" style="color: #46b450;"></span> <?php _e('AAN (volledig verborgen)', 'ai-product-chatbot'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span> <?php _e('UIT', 'ai-product-chatbot'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Telefoon maskering', 'ai-product-chatbot'); ?></th>
                    <td>
                        <?php if ($phone_masking_on): ?>
                            <span class="dashicons dashicons-yes" style="color: #46b450;"></span> <?php _e('AAN (volledig verborgen)', 'ai-product-chatbot'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span> <?php _e('UIT', 'ai-product-chatbot'); ?>
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
                    <th scope="row"><?php _e('Auto‑purge (cron)', 'ai-product-chatbot'); ?></th>
                    <td>
                        <?php if ($auto_purge_scheduled): ?>
                            <span class="dashicons dashicons-yes" style="color: #46b450;"></span> <?php _e('Ingepland (dagelijks)', 'ai-product-chatbot'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-no-alt" style="color: #dc3232;"></span> <?php _e('Niet ingepland', 'ai-product-chatbot'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <p>
                <?php if ($avg_pass): ?>
                    <strong style="color:#46b450;"><?php _e('Technisch AVG‑klaar ingesteld.', 'ai-product-chatbot'); ?></strong>
                <?php else: ?>
                    <strong style="color:#dc3232;"><?php _e('Aandacht nodig voor technische instellingen.', 'ai-product-chatbot'); ?></strong>
                <?php endif; ?>
                <br>
                <span class="description"><?php _e('Let op: dit is een technische indicatie. Actualiseer je privacyverklaring en sluit verwerkersovereenkomsten met gekozen AI‑providers.', 'ai-product-chatbot'); ?></span>
            </p>
        </div>
    </form>
        
        <div class="aipc-settings-section">
            <h2><?php _e('API Test', 'ai-product-chatbot'); ?></h2>
            <p><?php _e('Test je OpenAI API verbinding:', 'ai-product-chatbot'); ?></p>
            <button type="button" class="button" id="aipc-test-api">
                <?php _e('Test API Verbinding', 'ai-product-chatbot'); ?>
            </button>
            <div id="aipc-test-result" style="margin-top: 10px;"></div>

            <h3 style="margin-top:20px;"><?php _e('OpenRouter Key Info', 'ai-product-chatbot'); ?></h3>
            <p class="description"><?php _e('Bekijk credits/limieten voor je OpenRouter API key.', 'ai-product-chatbot'); ?></p>
            <button type="button" class="button" id="aipc-openrouter-key-info">
                <?php _e('Toon OpenRouter limieten', 'ai-product-chatbot'); ?>
            </button>
            <div id="aipc-openrouter-result" style="margin-top: 10px;"></div>

            <h3 style="margin-top:20px;"><?php _e('OpenRouter Free Requests (vandaag)', 'ai-product-chatbot'); ?></h3>
            <p class="description"><?php _e('Toont je lokale teller en de dagelijkse limiet voor :free modellen.', 'ai-product-chatbot'); ?></p>
            <button type="button" class="button" id="aipc-openrouter-free-usage">
                <?php _e('Toon resterende :free requests vandaag', 'ai-product-chatbot'); ?>
            </button>
            <div id="aipc-openrouter-free-result" style="margin-top:10px;"></div>
        </div>
        
        <?php submit_button(__('Instellingen Opslaan', 'ai-product-chatbot')); ?>
        
        </form>
    <?php endif; // End license check ?>
</div>

<style>
.aipc-settings-section {
    margin: 30px 0;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
}

.aipc-settings-section h2 {
    margin-top: 0;
}

.aipc-settings-section code {
    background: #f1f1f1;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
}

.aipc-settings-section ul {
    margin-left: 20px;
}

.aipc-settings-section li {
    margin-bottom: 5px;
}

/* License Configuration Mode */
.aipc-settings-section table.form-table {
    background: white;
    border-radius: 6px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.aipc-settings-section h2 {
    color: #23282d;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Skin test product picker
    (function(){
        const $box = $('#aipc-product-picker');
        if (!$box.length) return;
        const $q = $('#aipc-product-search');
        const $btn = $('#aipc-product-search-btn');
        const $res = $('#aipc-product-search-results');
        const $copy = $('#aipc-product-insert-btn');
        function search(page=1){
            $btn.prop('disabled', true).text('<?php echo esc_js(__('Zoeken…', 'ai-product-chatbot')); ?>');
            $res.html('<?php echo esc_js(__('Bezig met zoeken…', 'ai-product-chatbot')); ?>');
            $.post(ajaxurl, {
                action: 'aipc_search_products',
                nonce: '<?php echo wp_create_nonce('aipc_test_api'); ?>',
                q: $q.val() || '',
                page: page
            }).done(function(resp){
                if (!resp.success){
                    $res.html('<div class="notice notice-error inline"><p>'+ (resp.data && resp.data.message ? resp.data.message : 'Fout') +'</p></div>');
                    return;
                }
                const arr = resp.data.results || [];
                if (!arr.length){
                    $res.html('<?php echo esc_js(__('Geen producten gevonden.', 'ai-product-chatbot')); ?>');
                    return;
                }
                const html = arr.map(function(p){
                    const price = p.price ? (' €'+p.price) : '';
                    const sku = p.sku ? (' • SKU: '+ $('<div>').text(p.sku).html()) : '';
                    return '<label style="display:flex; align-items:center; gap:8px; margin:4px 0;">\
                        <input type="checkbox" class="aipc-prod-check" value="'+p.id+'" />\
                        <span><strong>#'+p.id+'</strong> — '+$('<div>').text(p.name).html()+ price + sku +'</span>\
                    </label>';
                }).join('');
                const next = resp.data.has_more ? '<button type="button" class="button button-small" id="aipc-next-page"><?php echo esc_js(__('Meer laden', 'ai-product-chatbot')); ?></button>' : '';
                $res.html(html + (next ? '<div style="margin-top:8px;">'+next+'</div>' : ''));
            }).fail(function(){
                $res.html('<div class="notice notice-error inline"><p>Fout</p></div>');
            }).always(function(){
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Zoeken', 'ai-product-chatbot')); ?>');
            });
        }
        $btn.on('click', function(){ search(1); });
        $q.on('keypress', function(e){ if (e.which === 13) { e.preventDefault(); search(1); }});
        $(document).on('click', '#aipc-next-page', function(){
            const txt = $('#aipc-next-page').text();
            $('#aipc-next-page').prop('disabled', true).text('<?php echo esc_js(__('Laden…', 'ai-product-chatbot')); ?>');
            // crude way to increment by discovering how many loaded; not storing page state to keep code minimal
            search( (parseInt($('#aipc-product-search-results').data('page')||'1',10)) + 1 );
        });
        $copy.on('click', function(){
            const ids = $('.aipc-prod-check:checked').map(function(){ return this.value; }).get();
            if (!ids.length){
                alert('<?php echo esc_js(__('Geen producten geselecteerd.', 'ai-product-chatbot')); ?>');
                return;
            }
            const txt = '['+ ids.join(', ') +']';
            navigator.clipboard.writeText(txt).then(function(){
                alert('<?php echo esc_js(__('Geselecteerde IDs gekopieerd naar klembord.', 'ai-product-chatbot')); ?>');
            }).catch(function(){
                alert(txt);
            });
        });
    })();
    // Populate models dynamically from provider
    function populateModels() {
        const $select = $('#aipc_openai_model');
        $select.prop('disabled', true).html('<option>Loading models…</option>');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_fetch_models',
                nonce: '<?php echo wp_create_nonce('aipc_test_api'); ?>'
            },
            success: function(resp) {
                if (resp.success) {
                    const current = '<?php echo esc_js($model); ?>';
                    $select.empty();
                    resp.data.models.forEach(function(id) {
                        const opt = $('<option>').attr('value', id).text(id);
                        if (id === current) opt.attr('selected', 'selected');
                        $select.append(opt);
                    });
                } else {
                    $select.html('<option>' + (resp.data && resp.data.message ? resp.data.message : 'Failed to load models') + '</option>');
                }
            },
            error: function() {
                $select.html('<option>Failed to load models</option>');
            },
            complete: function() {
                $select.prop('disabled', false);
            }
        });
    }

    populateModels();

    $('#aipc-test-api').on('click', function() {
        const $button = $(this);
        const $result = $('#aipc-test-result');
        
        $button.prop('disabled', true).text('Testen...');
        $result.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_test_api',
                nonce: '<?php echo wp_create_nonce('aipc_test_api'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success inline"><p>✅ ' + response.data.message + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error inline"><p>❌ ' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error inline"><p>❌ Fout bij testen van API verbinding</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test API Verbinding');
            }
        });
    });

    $('#aipc-openrouter-key-info').on('click', function() {
        const $btn = $(this);
        const $res = $('#aipc-openrouter-result');
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Ophalen...', 'ai-product-chatbot')); ?>');
        $res.html('');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_openrouter_key_info',
                nonce: '<?php echo wp_create_nonce('aipc_test_api'); ?>'
            },
            success: function(resp) {
                if (resp.success) {
                    const k = resp.data.key;
                    const limitText = (k.limit === null) ? 'onbeperkt' : k.limit;
                    const freeText = k.is_free_tier ? 'ja' : 'nee';
                    $res.html('<div class="notice notice-success inline"><p>'+
                        'Label: '+ (k.label || '-') +'<br>'+ 
                        'Credits gebruikt: '+ k.usage +'<br>'+ 
                        'Credit limiet: '+ limitText +'<br>'+ 
                        'Free tier: '+ freeText +
                    '</p></div>');
                } else {
                    $res.html('<div class="notice notice-error inline"><p>'+ (resp.data && resp.data.message ? resp.data.message : 'Fout bij ophalen sleutelinfo') +'</p></div>');
                }
            },
            error: function() {
                $res.html('<div class="notice notice-error inline"><p>Fout bij openrouter key info</p></div>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Toon OpenRouter limieten', 'ai-product-chatbot')); ?>');
            }
        });
    });

    $('#aipc-openrouter-free-usage').on('click', function() {
        const $btn = $(this);
        const $res = $('#aipc-openrouter-free-result');
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Ophalen...', 'ai-product-chatbot')); ?>');
        $res.html('');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_openrouter_free_usage',
                nonce: '<?php echo wp_create_nonce('aipc_test_api'); ?>'
            },
            success: function(resp) {
                if (resp.success) {
                    const d = resp.data;
                    $res.html('<div class="notice notice-success inline"><p>'+
                        '<?php echo esc_js(__('Vandaag gebruikt', 'ai-product-chatbot')); ?>: '+ d.used_today +'<br>'+ 
                        '<?php echo esc_js(__('Daglimiet', 'ai-product-chatbot')); ?>: '+ d.daily_cap +'<br>'+ 
                        '<?php echo esc_js(__('Resterend', 'ai-product-chatbot')); ?>: '+ d.remaining +
                    '</p></div>');
                } else {
                    $res.html('<div class="notice notice-error inline"><p>'+ (resp.data && resp.data.message ? resp.data.message : 'Fout bij ophalen free usage') +'</p></div>');
                }
            },
            error: function() {
                $res.html('<div class="notice notice-error inline"><p>Fout bij free usage</p></div>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Toon resterende :free requests vandaag', 'ai-product-chatbot')); ?>');
            }
        });
    });

    

    $('#aipc-purge-conversations').on('click', function(){
        const $btn = $(this), $res = $('#aipc-purge-export-result');
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Verwijderen...', 'ai-product-chatbot')); ?>');
        $res.html('');
        $.post(ajaxurl, {
            action: 'aipc_purge_conversations',
            nonce: '<?php echo wp_create_nonce('aipc_admin_nonce'); ?>'
        }).done(function(resp){
            if (resp.success) {
                $res.html('<div class="notice notice-success inline"><p><?php echo esc_js(__('Verwijderd:', 'ai-product-chatbot')); ?> '+resp.data.deleted+'</p></div>');
            } else {
                $res.html('<div class="notice notice-error inline"><p>'+ (resp.data && resp.data.message ? resp.data.message : 'Fout') +'</p></div>');
            }
        }).fail(function(){
            $res.html('<div class="notice notice-error inline"><p>Fout</p></div>');
        }).always(function(){
            $btn.prop('disabled', false).text('<?php echo esc_js(__('Verwijder oude gesprekken nu', 'ai-product-chatbot')); ?>');
        });
    });

    $('#aipc-export-conversations').on('click', function(){
        const $btn = $(this), $res = $('#aipc-purge-export-result');
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Exporteren...', 'ai-product-chatbot')); ?>');
        $res.html('');
        $.post(ajaxurl, {
            action: 'aipc_export_conversations',
            nonce: '<?php echo wp_create_nonce('aipc_admin_nonce'); ?>'
        }).done(function(resp){
            if (resp.success) {
                const data = JSON.stringify(resp.data.rows || [], null, 2);
                const blob = new Blob([data], {type: 'application/json'});
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'conversations.json';
                a.click();
                URL.revokeObjectURL(url);
                $res.html('<div class="notice notice-success inline"><p><?php echo esc_js(__('Export gereed.', 'ai-product-chatbot')); ?></p></div>');
            } else {
                $res.html('<div class="notice notice-error inline"><p>'+ (resp.data && resp.data.message ? resp.data.message : 'Fout') +'</p></div>');
            }
        }).fail(function(){
            $res.html('<div class="notice notice-error inline"><p>Fout</p></div>');
        }).always(function(){
            $btn.prop('disabled', false).text('<?php echo esc_js(__('Exporteren (JSON)', 'ai-product-chatbot')); ?>');
        });
    });
    
    // License activation
    $('#aipc_activate_license').on('click', function() {
        const $btn = $(this);
        const $result = $('#aipc_license_result');
        const licenseKey = $('#aipc_license_key').val().trim();
        
        if (!licenseKey) {
            $result.html('<div class="notice notice-error inline"><p>Voer een licentie key in.</p></div>');
            return;
        }
        
        $btn.prop('disabled', true).text('Activeren...');
        $result.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_activate_license',
                license_key: licenseKey,
                nonce: '<?php echo wp_create_nonce('aipc_test_api'); ?>'
            },
            success: function(resp) {
                if (resp.success) {
                    $result.html('<div class="notice notice-success inline"><p>' + resp.data.message + '</p></div>');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    $result.html('<div class="notice notice-error inline"><p>' + (resp.data.message || 'Activatie mislukt') + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error inline"><p>Verbindingsfout bij licentie activatie</p></div>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Activeren');
            }
        });
    });
    
    // License deactivation
    $('#aipc_deactivate_license').on('click', function() {
        if (!confirm('Weet je zeker dat je de licentie wilt deactiveren?')) return;
        
        const $btn = $(this);
        $btn.prop('disabled', true).text('Deactiveren...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_deactivate_license',
                nonce: '<?php echo wp_create_nonce('aipc_test_api'); ?>'
            },
            success: function(resp) {
                if (resp.success) {
                    location.reload();
                }
            },
            complete: function() {
                $btn.prop('disabled', false).text('Deactiveren');
            }
        });
    });
});
</script>
