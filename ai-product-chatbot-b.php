<?php
/*
Plugin Name: AI Product Chatbot
Description: Intelligente AI chatbot voor productkennis, ingrediënten en persoonlijke aanbevelingen
Version: 1.0.0
Author: Danny Koenen
Text Domain: ai-product-chatbot
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('AIPC_VERSION', '1.0.0');
define('AIPC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIPC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader voor classes
spl_autoload_register(function ($class) {
    if (strpos($class, 'AIPC_') === 0) {
        $file = AIPC_PLUGIN_DIR . 'includes/class-' . strtolower(str_replace('_', '-', $class)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

class AI_Product_Chatbot {
    
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // AJAX handlers
        add_action('wp_ajax_aipc_send_message', [$this, 'handle_chat_message']);
        add_action('wp_ajax_nopriv_aipc_send_message', [$this, 'handle_chat_message']);
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_notices', [$this, 'admin_license_notice']);
            add_action('wp_ajax_aipc_test_api', [$this, 'admin_test_api']);
            add_action('wp_ajax_aipc_fetch_models', [$this, 'admin_fetch_models']);
            add_action('wp_ajax_aipc_openrouter_key_info', [$this, 'admin_openrouter_key_info']);
            add_action('wp_ajax_aipc_openrouter_free_usage', [$this, 'admin_openrouter_free_usage']);
            add_action('wp_ajax_aipc_search_products', [$this, 'admin_search_products']);
            add_action('wp_ajax_aipc_get_product_info', [$this, 'admin_get_product_info']);
            add_action('wp_ajax_aipc_get_conversation', [$this, 'admin_get_conversation']);
            add_action('wp_ajax_aipc_purge_conversations', [$this, 'admin_purge_conversations']);
            add_action('wp_ajax_aipc_export_conversations', [$this, 'admin_export_conversations']);
            add_action('wp_ajax_aipc_activate_license', [$this, 'admin_activate_license']);
            add_action('wp_ajax_aipc_deactivate_license', [$this, 'admin_deactivate_license']);
            add_action('wp_ajax_aipc_force_validate_license', [$this, 'admin_force_validate_license']);
            add_action('wp_ajax_aipc_cleanup_monitoring', [$this, 'admin_cleanup_monitoring']);
        }

        // Daily auto-purge
        add_action('aipc_daily_purge', [$this, 'cron_purge_old_conversations']);
        add_action('aipc_daily_cleanup_monitoring', [$this, 'cron_cleanup_monitoring']);
    }
    
    public function init() {
        // Load text domain
        load_plugin_textdomain('ai-product-chatbot', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize core components
        new AIPC_Chatbot_Frontend();
        new AIPC_Product_Manager();
        new AIPC_Document_Manager();
        new AIPC_OpenAI_Handler();
        
        // Initialize API monitoring
        AIPC_API_Monitor::getInstance();
        
        // Initialize optional integrations
        $this->load_integrations();

        // Ensure required tables exist on upgrades (safe due to dbDelta)
        $this->ensure_tables();
        // Run lightweight migrations (e.g., add missing columns)
        if (class_exists('AIPC_Database')) {
            AIPC_Database::migrate();
        }

        // One-time cleanup of legacy product tables - DISABLED om WooCommerce sync te behouden
        // $this->cleanup_legacy_product_tables();

        // Register dynamic strings for translation (Polylang/WPML)
        $this->register_translatable_strings();
    }
    
    private function load_integrations() {
        // WooCommerce integration
        $woo_exists = class_exists('WooCommerce');
        $woo_enabled = get_option('aipc_woocommerce_enabled', true);
        
        // Force enable WooCommerce integration for Business+ licenses with woocommerce_full feature
        if ($woo_exists && class_exists('AIPC_License')) {
            static $license_cache = null;
            if ($license_cache === null) {
                $license_cache = AIPC_License::getInstance();
            }
            
            if ($license_cache->is_active() && $license_cache->has_feature('woocommerce_full')) {
                $woo_enabled = true;
                // Only update database once per request
                static $woo_updated = false;
                if (!$woo_updated && get_option('aipc_woocommerce_enabled') != 1) {
                    update_option('aipc_woocommerce_enabled', 1);
                    $woo_updated = true;
                }
            }
        }
        
        if ($woo_exists && $woo_enabled) {
            new AIPC_WooCommerce_Integration();
        }
        
        // Future integrations can be added here
        // if (class_exists('Elementor') && get_option('aipc_elementor_enabled', false)) {
        //     new AIPC_Elementor_Integration();
        // }
    }
    
    public function enqueue_scripts() {
        $js_ver = AIPC_VERSION;
        $css_ver = AIPC_VERSION;
        $js_path = AIPC_PLUGIN_DIR . 'assets/js/chatbot.js';
        $css_path = AIPC_PLUGIN_DIR . 'assets/css/chatbot.css';
        if (file_exists($js_path)) { $js_ver .= '-' . filemtime($js_path); }
        if (file_exists($css_path)) { $css_ver .= '-' . filemtime($css_path); }
        wp_enqueue_script('aipc-chatbot', AIPC_PLUGIN_URL . 'assets/js/chatbot.js', ['jquery'], $js_ver, true);
        wp_enqueue_style('aipc-chatbot', AIPC_PLUGIN_URL . 'assets/css/chatbot.css', [], $css_ver);
        
        $current_lang = function_exists('pll_current_language') ? pll_current_language('locale') : (function_exists('determine_locale') ? determine_locale() : get_locale());
        // Single translatable welcome value
        $welcome = get_option('aipc_chatbot_welcome_message', '');
        if (!$welcome) {
            $welcome = (strpos($current_lang, 'en') === 0)
                ? __('Hello! I am your AI product assistant. How can I help you today?', 'ai-product-chatbot')
                : __('Hallo! Ik ben je AI product assistant. Hoe kan ik je vandaag helpen?', 'ai-product-chatbot');
        }
        // Translate via Polylang/WPML if present
        if (function_exists('pll_translate_string')) {
            $welcome = pll_translate_string($welcome, 'ai-product-chatbot');
        } elseif (has_filter('wpml_translate_single_string')) {
            $welcome = apply_filters('wpml_translate_single_string', $welcome, 'ai-product-chatbot', 'aipc_welcome_message');
        }
        // Allow integrators to override
        $welcome = apply_filters('aipc_welcome_text', $welcome, $current_lang);
        // UI labels (translate via string translation if available)
        $is_en = (strpos($current_lang, 'en') === 0);
        $ui_typing = $is_en ? __('Typing...', 'ai-product-chatbot') : __('Typing...', 'ai-product-chatbot');
        $ui_error = $is_en ? __('Sorry, an error occurred. Please try again.', 'ai-product-chatbot') : __('Sorry, er is een fout opgetreden. Probeer het opnieuw.', 'ai-product-chatbot');
        $ui_no_products = $is_en ? __('No products found.', 'ai-product-chatbot') : __('Geen producten gevonden.', 'ai-product-chatbot');
        $ui_btn_open = $is_en ? __('Open chat', 'ai-product-chatbot') : __('Open chat', 'ai-product-chatbot');
        $ui_btn_close = $is_en ? __('Close', 'ai-product-chatbot') : __('Sluiten', 'ai-product-chatbot');
        $ui_btn_send = $is_en ? __('Send', 'ai-product-chatbot') : __('Verstuur', 'ai-product-chatbot');
        $ui_input_ph = $is_en ? __('Type your question here…', 'ai-product-chatbot') : __('Typ je vraag hier…', 'ai-product-chatbot');
        if (function_exists('pll_translate_string')) {
            $ui_typing = pll_translate_string($ui_typing, 'ai-product-chatbot');
            $ui_error = pll_translate_string($ui_error, 'ai-product-chatbot');
            $ui_no_products = pll_translate_string($ui_no_products, 'ai-product-chatbot');
            $ui_btn_open = pll_translate_string($ui_btn_open, 'ai-product-chatbot');
            $ui_btn_close = pll_translate_string($ui_btn_close, 'ai-product-chatbot');
            $ui_btn_send = pll_translate_string($ui_btn_send, 'ai-product-chatbot');
            $ui_input_ph = pll_translate_string($ui_input_ph, 'ai-product-chatbot');
        } elseif (has_filter('wpml_translate_single_string')) {
            $ui_typing = apply_filters('wpml_translate_single_string', $ui_typing, 'ai-product-chatbot', 'aipc_ui_typing');
            $ui_error = apply_filters('wpml_translate_single_string', $ui_error, 'ai-product-chatbot', 'aipc_ui_error');
            $ui_no_products = apply_filters('wpml_translate_single_string', $ui_no_products, 'ai-product-chatbot', 'aipc_ui_no_products');
            $ui_btn_open = apply_filters('wpml_translate_single_string', $ui_btn_open, 'ai-product-chatbot', 'aipc_ui_btn_open');
            $ui_btn_close = apply_filters('wpml_translate_single_string', $ui_btn_close, 'ai-product-chatbot', 'aipc_ui_btn_close');
            $ui_btn_send = apply_filters('wpml_translate_single_string', $ui_btn_send, 'ai-product-chatbot', 'aipc_ui_btn_send');
            $ui_input_ph = apply_filters('wpml_translate_single_string', $ui_input_ph, 'ai-product-chatbot', 'aipc_ui_input_placeholder');
        }
        wp_localize_script('aipc-chatbot', 'aipc_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aipc_nonce'),
            'lang' => $current_lang,
            'chatbot_icon' => get_option('aipc_chatbot_icon', 'robot'),
            'strings' => [
                'typing' => $ui_typing,
                'error' => $ui_error,
                'no_products' => $ui_no_products,
                'welcome' => $welcome,
                // UI labels
                'btn_open' => $ui_btn_open,
                'btn_close' => $ui_btn_close,
                'btn_send' => $ui_btn_send,
                'input_placeholder' => $ui_input_ph
            ]
        ]);
        
        // Add custom colors CSS if custom theme is selected
        $theme = get_option('aipc_chatbot_theme', 'modern');
        if ($theme === 'custom') {
            $this->add_custom_css();
        }
    }
    
    private function add_custom_css() {
        $primary_color = get_option('aipc_primary_color', '#667eea');
        $secondary_color = get_option('aipc_secondary_color', '#764ba2');
        $background_color = get_option('aipc_background_color', '#f8f9fa');
        $text_color = get_option('aipc_text_color', '#333333');
        $user_message_color = get_option('aipc_user_message_color', '#667eea');
        $assistant_message_color = get_option('aipc_assistant_message_color', '#ffffff');
        $header_text_color = get_option('aipc_header_text_color', '#ffffff');
        $input_background_color = get_option('aipc_input_background_color', '#f8f9fa');
        $input_border_color = get_option('aipc_input_border_color', '#e9ecef');
        $input_padding = get_option('aipc_input_padding', 5);
        $input_border_radius = get_option('aipc_input_border_radius', 25);
        $input_border_width = get_option('aipc_input_border_width', 1);
        $chatbot_icon = get_option('aipc_chatbot_icon', '🤖');
        
        $custom_css = "
        <style id='aipc-custom-colors'>
        /* Custom chatbot colors */
        .aipc-chatbot-toggle,
        .aipc-chatbot-header,
        .aipc-nav-btn,
        .aipc-chatbot-send {
            background: linear-gradient(135deg, {$primary_color} 0%, {$secondary_color} 100%) !important;
        }
        
        .aipc-message-assistant .aipc-message-avatar {
            background: linear-gradient(135deg, {$primary_color} 0%, {$secondary_color} 100%) !important;
        }
        
        .aipc-message-user .aipc-message-content {
            background: linear-gradient(135deg, {$user_message_color} 0%, {$secondary_color} 100%) !important;
        }
        
        .aipc-message-assistant .aipc-message-content {
            background: {$assistant_message_color} !important;
            color: {$text_color} !important;
        }
        
        .aipc-chatbot-messages {
            background: {$background_color} !important;
        }
        
        .aipc-chatbot-input {
            color: {$text_color} !important;
        }
        
        .aipc-message-content a {
            color: {$primary_color} !important;
        }
        
        .aipc-chatbot-input-wrapper:focus-within {
            border-color: {$primary_color} !important;
        }
        
        .aipc-typing-indicator span {
            background: {$primary_color} !important;
        }
        
        .aipc-nav-btn:hover {
            box-shadow: 0 4px 12px rgba(". $this->hex_to_rgba($primary_color, 0.3) .") !important;
        }
        
        /* Header title and status text */
        .aipc-chatbot-info h3,
        .aipc-chatbot-status {
            color: {$header_text_color} !important;
        }
        
        /* Toggle button text */
        .aipc-chatbot-text {
            color: {$header_text_color} !important;
        }
        
        /* Input field wrapper styling */
        .aipc-chatbot-input-wrapper {
            background: {$input_background_color} !important;
            border: {$input_border_width}px solid {$input_border_color} !important;
            border-radius: {$input_border_radius}px !important;
            padding: {$input_padding}px !important;
        }
        
        .aipc-chatbot-input-wrapper:focus-within {
            border-color: {$primary_color} !important;
        }
        </style>
        ";
        
        echo $custom_css;
    }
    
    private function hex_to_rgba($hex, $alpha = 1) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "{$r}, {$g}, {$b}";
    }

    private function register_translatable_strings() {
        $welcome = get_option('aipc_chatbot_welcome_message', '');
        $fallback = get_option('aipc_fallback_message', '');
        if (function_exists('pll_register_string')) {
            // Proactief oude entries met oude namen opruimen
            if (function_exists('pll_unregister_string')) {
                pll_unregister_string('welcome_message');
                pll_unregister_string('fallback_message');
            }
            if ($welcome) { pll_register_string('aipc_welcome_message', $welcome, 'ai-product-chatbot'); }
            if ($fallback) { pll_register_string('aipc_fallback_message', $fallback, 'ai-product-chatbot'); }
            // Register static UI strings so they can be overridden per language
            pll_register_string('aipc_ui_btn_open', __('Open chat', 'ai-product-chatbot'), 'ai-product-chatbot');
            pll_register_string('aipc_ui_btn_close', __('Sluiten', 'ai-product-chatbot'), 'ai-product-chatbot');
            pll_register_string('aipc_ui_btn_send', __('Verstuur', 'ai-product-chatbot'), 'ai-product-chatbot');
            pll_register_string('aipc_ui_input_placeholder', __('Typ je vraag hier…', 'ai-product-chatbot'), 'ai-product-chatbot');
            pll_register_string('aipc_ui_typing', __('Typing...', 'ai-product-chatbot'), 'ai-product-chatbot');
            pll_register_string('aipc_ui_error', __('Sorry, er is een fout opgetreden. Probeer het opnieuw.', 'ai-product-chatbot'), 'ai-product-chatbot');
            pll_register_string('aipc_ui_no_products', __('Geen producten gevonden.', 'ai-product-chatbot'), 'ai-product-chatbot');
            // New customizable strings
            $sys = get_option('aipc_system_prompt', '');
            $gwp = get_option('aipc_greet_with_products', '');
            $gwo = get_option('aipc_greet_without_products', '');
            if ($sys) { pll_register_string('aipc_system_prompt', $sys, 'ai-product-chatbot'); }
            if ($gwp) { pll_register_string('aipc_greet_with_products', $gwp, 'ai-product-chatbot'); }
            if ($gwo) { pll_register_string('aipc_greet_without_products', $gwo, 'ai-product-chatbot'); }
        }
        if (has_action('wpml_register_single_string')) {
            if ($welcome) { do_action('wpml_register_single_string', 'ai-product-chatbot', 'aipc_welcome_message', $welcome); }
            if ($fallback) { do_action('wpml_register_single_string', 'ai-product-chatbot', 'aipc_fallback_message', $fallback); }
            do_action('wpml_register_single_string', 'ai-product-chatbot', 'aipc_ui_btn_open', __('Open chat', 'ai-product-chatbot'));
            do_action('wpml_register_single_string', 'ai-product-chatbot', 'aipc_ui_btn_close', __('Sluiten', 'ai-product-chatbot'));
            do_action('wpml_register_single_string', 'ai-product-chatbot', 'aipc_ui_btn_send', __('Verstuur', 'ai-product-chatbot'));
            do_action('wpml_register_single_string', 'ai-product-chatbot', 'aipc_ui_input_placeholder', __('Typ je vraag hier…', 'ai-product-chatbot'));
            do_action('wpml_register_single_string', 'ai-product-chatbot', 'aipc_ui_typing', __('Typing...', 'ai-product-chatbot'));
            do_action('wpml_register_single_string', 'ai-product-chatbot', 'aipc_ui_error', __('Sorry, er is een fout opgetreden. Probeer het opnieuw.', 'ai-product-chatbot'));
            do_action('wpml_register_single_string', 'ai-product-chatbot', 'aipc_ui_no_products', __('Geen producten gevonden.', 'ai-product-chatbot'));
            // New customizable strings
            $sys2 = get_option('aipc_system_prompt', '');
            $gwp2 = get_option('aipc_greet_with_products', '');
            $gwo2 = get_option('aipc_greet_without_products', '');
            if ($sys2) { do_action('wpml_register_single_string', 'ai-product-chatbot', 'aipc_system_prompt', $sys2); }
            if ($gwp2) { do_action('wpml_register_single_string', 'ai-product-chatbot', 'aipc_greet_with_products', $gwp2); }
            if ($gwo2) { do_action('wpml_register_single_string', 'ai-product-chatbot', 'aipc_greet_without_products', $gwo2); }
        }
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'aipc') !== false) {
            wp_enqueue_script('aipc-admin', AIPC_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], AIPC_VERSION, true);
            wp_enqueue_style('aipc-admin', AIPC_PLUGIN_URL . 'assets/css/admin.css', [], AIPC_VERSION);
            
            // Load Quiz Builder on quiz builder page
            if (strpos($hook, 'quiz-builder') !== false) {
                wp_enqueue_script('aipc-quiz-builder', AIPC_PLUGIN_URL . 'assets/js/quiz-builder.js', ['jquery'], AIPC_VERSION, true);
            }
            
            wp_localize_script('aipc-admin', 'aipc_admin', [
                'nonce' => wp_create_nonce('aipc_test_api')
            ]);
        }
    }
    
    public function add_admin_menu() {
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
        
        // Main menu - always visible
        add_menu_page(
            __('AI Chatbot', 'ai-product-chatbot'),
            __('AI Chatbot', 'ai-product-chatbot'),
            'manage_options',
            'aipc-dashboard',
            [$this, 'admin_dashboard'],
            'dashicons-format-chat',
            30
        );
        
        // FAQ - Only visible with Basic+ license
        if ($has_basic) {
            add_submenu_page(
                'aipc-dashboard',
                __('FAQ', 'ai-product-chatbot'),
                __('FAQ', 'ai-product-chatbot'),
                'manage_options',
                'aipc-faq',
                [$this, 'admin_faq']
            );
        }
        
        // Licenties - Always visible so users can see upgrade options
        add_submenu_page(
            'aipc-dashboard',
            __('Licentie Vergelijking', 'ai-product-chatbot'),
            __('Licenties', 'ai-product-chatbot'),
            'manage_options',
            'aipc-license-compare',
            [$this, 'admin_license_compare']
        );
        
        // Settings - Always visible (needed for license server URL configuration)
        add_submenu_page(
            'aipc-dashboard',
            __('Instellingen', 'ai-product-chatbot'),
            __('Instellingen', 'ai-product-chatbot'),
            'manage_options',
            'aipc-settings',
            [$this, 'admin_settings']
        );
        
        // Kenmerken - Only available for Business+ users
        if ($has_business) {
            add_submenu_page(
                'aipc-dashboard',
                __('Kenmerken', 'ai-product-chatbot'),
                __('Kenmerken', 'ai-product-chatbot'),
                'manage_options',
                'aipc-ingredients',
                [$this, 'admin_ingredients']
            );
        }
        
        // Business+ tier features
        if ($has_business) {
            // Product Quiz - Only for Business+ with product_quiz feature
            $has_quiz_feature = $license->has_feature('product_quiz');
            
            // Force enable skin test for Business+ users with product_quiz feature
            if ($has_quiz_feature) {
                $skin_test_enabled = true;
                // Only update database once per request
                static $skin_updated = false;
                if (!$skin_updated && get_option('aipc_enable_skin_test') != 1) {
                    update_option('aipc_enable_skin_test', 1);
                    $skin_updated = true;
                }
            } else {
                $skin_test_enabled = get_option('aipc_enable_skin_test', false);
            }
            
            if ($has_quiz_feature && $skin_test_enabled) {
                // Visual Quiz Builder (new)
                add_submenu_page(
                    'aipc-dashboard',
                    __('Quiz Builder', 'ai-product-chatbot'),
                    __('Quiz Builder ✨', 'ai-product-chatbot'),
                    'manage_options',
                    'aipc-quiz-builder',
                    [$this, 'admin_quiz_builder']
                );
                
                // JSON Editor (legacy)
                $menu_title = __('Product Quiz', 'ai-product-chatbot');
                add_submenu_page(
                    'aipc-dashboard',
                    $menu_title . ' (JSON)',
                    $menu_title . ' (JSON)',
                    'manage_options',
                    'aipc-skin-test',
                    [$this, 'admin_skin_test']
                );
            }
        }
        
        // Enterprise tier features
        if ($current_tier === 'enterprise') {
            add_submenu_page(
                'aipc-dashboard',
                __('Documenten', 'ai-product-chatbot'),
                __('Documenten', 'ai-product-chatbot'),
                'manage_options',
                'aipc-documents',
                [$this, 'admin_documents']
            );
        }
        
        // WooCommerce menu wordt toegevoegd door AIPC_WooCommerce_Integration class
        // Alleen zichtbaar met Basic+ licentie
        
        // Analytics - Only visible with Enterprise license
        if ($current_tier === 'enterprise') {
            add_submenu_page(
                'aipc-dashboard',
                __('Analytics', 'ai-product-chatbot'),
                __('Analytics', 'ai-product-chatbot'),
                'manage_options',
                'aipc-analytics',
                [$this, 'admin_analytics']
            );
            
            // API Monitoring - Only for Enterprise users
            add_submenu_page(
                'aipc-dashboard',
                __('API Monitoring', 'ai-product-chatbot'),
                __('API Monitoring', 'ai-product-chatbot'),
                'manage_options',
                'aipc-api-monitoring',
                [$this, 'admin_api_monitoring']
            );
        }
        
        // Debug Tool - Only visible when debugging enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_submenu_page(
                'aipc-dashboard',
                __('🔧 Debug Tool', 'ai-product-chatbot'),
                __('🔧 Debug', 'ai-product-chatbot'),
                'manage_options',
                'aipc-license-fix',
                [$this, 'admin_license_fix']
            );
        }
    }
    
    public function register_settings() {
        register_setting('aipc_settings', 'aipc_openai_api_key');
        register_setting('aipc_settings', 'aipc_openai_model', ['default' => 'gpt-4']);
        register_setting('aipc_settings', 'aipc_api_provider', ['default' => 'openai']);
        register_setting('aipc_settings', 'aipc_api_base', ['default' => '']);
        register_setting('aipc_settings', 'aipc_show_api_errors', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ]);
        register_setting('aipc_settings', 'aipc_chatbot_tone', ['default' => 'neutral']);
        register_setting('aipc_settings', 'aipc_allow_judgement', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ]);
        register_setting('aipc_settings', 'aipc_tips_style', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'default'
        ]);
        register_setting('aipc_settings', 'aipc_mask_email', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ]);
        register_setting('aipc_settings', 'aipc_mask_phone', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ]);
        register_setting('aipc_settings', 'aipc_store_conversations', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ]);
        register_setting('aipc_settings', 'aipc_retention_days', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 90
        ]);
        register_setting('aipc_settings', 'aipc_chatbot_title', ['default' => 'AI Product Assistant']);
        register_setting('aipc_settings', 'aipc_chatbot_welcome_message', ['default' => 'Hallo! Ik ben je AI product assistant. Hoe kan ik je vandaag helpen?']);
        register_setting('aipc_settings', 'aipc_fallback_message', ['default' => 'Bedankt voor je bericht! Ik ben hier om je te helpen met productaanbevelingen, ingrediënten uitleg en persoonlijke adviezen. Kun je me meer vertellen over wat je zoekt?']);
        register_setting('aipc_settings', 'aipc_chatbot_enabled', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ]);
        register_setting('aipc_settings', 'aipc_chatbot_position', ['default' => 'bottom-right']);
        register_setting('aipc_settings', 'aipc_chatbot_theme', ['default' => 'modern']);
        // Customizable prompts and greetings
        register_setting('aipc_settings', 'aipc_system_prompt', [
            'type' => 'string',
            'sanitize_callback' => 'wp_kses_post',
            'default' => ''
        ]);
        register_setting('aipc_settings', 'aipc_greet_with_products', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => ''
        ]);
        register_setting('aipc_settings', 'aipc_greet_without_products', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => ''
        ]);
        register_setting('aipc_settings', 'aipc_max_faq_items', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 20
        ]);
        register_setting('aipc_settings', 'aipc_max_doc_snippet', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 500
        ]);
        
        // Feature toggles
        register_setting('aipc_settings', 'aipc_enable_skin_test', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ]);
        register_setting('aipc_settings', 'aipc_enable_product_recommendations', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ]);
        register_setting('aipc_settings', 'aipc_business_type', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'generic'
        ]);
        
        
        // WooCommerce integration settings
        register_setting('aipc_settings', 'aipc_woocommerce_enabled', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ]);
        register_setting('aipc_settings', 'aipc_woocommerce_auto_sync', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ]);
        register_setting('aipc_settings', 'aipc_woocommerce_show_cart', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ]);
        register_setting('aipc_settings', 'aipc_woocommerce_ingredients_field', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '_ingredients'
        ]);
        register_setting('aipc_settings', 'aipc_woocommerce_skin_types_field', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '_skin_types'
        ]);
        
        // API monitoring and rate limiting settings
        register_setting('aipc_settings', 'aipc_rate_limit_per_minute', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 10
        ]);
        register_setting('aipc_settings', 'aipc_monitoring_retention_days', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 30
        ]);
        register_setting('aipc_settings', 'aipc_anonymize_monitoring_ips', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ]);
    }
    
    public function handle_chat_message() {
        // Ensure HTML/PHP notices don't leak into AJAX JSON
        if (defined('DOING_AJAX') && DOING_AJAX) {
            @ini_set('display_errors', 0);
        }
        check_ajax_referer('aipc_nonce', 'nonce');
        nocache_headers();
        
        // Rate limiting check
        $api_monitor = AIPC_API_Monitor::getInstance();
        if ($api_monitor->is_rate_limited()) {
            // Log rate limit hit
            $api_monitor->log_api_request([
                'status' => 'rate_limit',
                'error_message' => 'Rate limit exceeded',
                'request_type' => 'chat'
            ]);
            wp_send_json_error(['message' => __('Te veel verzoeken. Probeer het over een minuut opnieuw.', 'ai-product-chatbot')]);
        }
        
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        $conversation_id = isset($_POST['conversation_id']) ? sanitize_text_field($_POST['conversation_id']) : '';
        
        // Input validation
        if (empty(trim($message))) {
            wp_send_json_error(['message' => __('Leeg bericht niet toegestaan.', 'ai-product-chatbot')]);
        }
        
        if (strlen($message) > 1000) {
            wp_send_json_error(['message' => __('Bericht is te lang (maximum 1000 tekens).', 'ai-product-chatbot')]);
        }
        
        if (strlen($message) < 2) {
            wp_send_json_error(['message' => __('Bericht is te kort.', 'ai-product-chatbot')]);
        }
        
        // Check license tier and API key to determine response handling
        $has_ai_access = false;
        $api_key = get_option('aipc_openai_api_key', '');
        
        if (class_exists('AIPC_License')) {
            $license = AIPC_License::getInstance();
            if ($license->is_active()) {
                $has_api_feature = $license->has_feature('api_integrations');
                // Only enable AI if license allows it AND API key is configured
                $has_ai_access = $has_api_feature && !empty($api_key);
            }
        }
        
        $start_time = microtime(true);
        
        try {
            error_log('AIPC DEBUG: has_ai_access = ' . ($has_ai_access ? 'TRUE' : 'FALSE') . ', API key = ' . (empty($api_key) ? 'EMPTY' : 'SET'));
            if (class_exists('AIPC_License')) {
                $license = AIPC_License::getInstance();
                error_log('AIPC DEBUG: License active = ' . ($license->is_active() ? 'TRUE' : 'FALSE') . ', has API feature = ' . ($license->has_feature('api_integrations') ? 'TRUE' : 'FALSE'));
            }
            
            if ($has_ai_access) {
                // Business+ license with API key: Use full AI handler
                error_log('AIPC DEBUG: Using FULL AI HANDLER for: ' . $message);
                $openai_handler = new AIPC_OpenAI_Handler();
                $response = $openai_handler->process_message($message, $conversation_id);
            } else {
                // Basic license OR no API key: Use FAQ/WooCommerce only fallback
                error_log('AIPC DEBUG: Using BASIC HANDLER for: ' . $message);
                $response = $this->process_basic_message($message, $conversation_id);
            }
            
            if (isset($response['success']) && $response['success']) {
                // Log successful request
                $response_time = round((microtime(true) - $start_time) * 1000, 2);
                $api_monitor->log_api_request([
                    'conversation_id' => $conversation_id,
                    'status' => 'success',
                    'response_time_ms' => $response_time,
                    'request_type' => $has_ai_access ? 'ai_chat' : 'basic_chat'
                ]);
                
                // Lightweight analytics (best-effort)
                try {
                    global $wpdb;
                    $table = $wpdb->prefix . 'aipc_analytics';
                    $cid = $conversation_id ? sanitize_text_field($conversation_id) : wp_generate_uuid4();
                    // Create or update a row per conversation; if table lacks unique key, fall back to insert
                    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE conversation_id=%s ORDER BY id ASC LIMIT 1", $cid));
                    if ($existing) {
                        $wpdb->update($table, ['message_count' => new \wpdb\Literal('message_count + 1')], ['id' => $existing]);
                    } else {
                        $wpdb->insert($table, ['conversation_id' => $cid, 'message_count' => 1, 'session_duration' => 0, 'created_at' => current_time('mysql')]);
                    }
                } catch (Throwable $ignore) {}
                wp_send_json_success($response['data']);
            }
            
            // Log error response
            $response_time = round((microtime(true) - $start_time) * 1000, 2);
            $error_msg = isset($response['message']) ? $response['message'] : __('Onbekende fout.', 'ai-product-chatbot');
            $api_monitor->log_api_request([
                'conversation_id' => $conversation_id,
                'status' => 'error',
                'error_message' => $error_msg,
                'response_time_ms' => $response_time,
                'request_type' => $has_ai_access ? 'ai_chat' : 'basic_chat'
            ]);
            
            wp_send_json_error(['message' => $error_msg]);
        } catch (Throwable $e) {
            // Log unexpected error
            $response_time = round((microtime(true) - $start_time) * 1000, 2);
            $api_monitor->log_api_request([
                'conversation_id' => $conversation_id,
                'status' => 'error',
                'error_message' => 'Internal server error: ' . $e->getMessage(),
                'response_time_ms' => $response_time,
                'request_type' => $has_ai_access ? 'ai_chat' : 'basic_chat'
            ]);
            
            // Return user-friendly error message
            wp_send_json_error(['message' => __('Sorry, er is een interne fout opgetreden. Probeer het over een paar minuten opnieuw.', 'ai-product-chatbot')]);
        }
    }
    
    /**
     * Process chat messages for Basic license users (FAQ + WooCommerce only, no AI)
     */
    private function process_basic_message($message, $conversation_id) {
        try {
            $results = [];
            $found_matches = false;
            
            // Pre-process message: clean up common punctuation that interferes with search
            $original_message = $message;
            $message = preg_replace('/([\p{L}\p{N}])[?!.,;:]+(\s|$)/u', '$1$2', $message); // Remove punctuation attached to words
            $message = trim($message);
            
            // Debug logging
            error_log('AIPC Debug: Processing basic message: "' . $original_message . '" (cleaned: "' . $message . '")');
            error_log('AIPC Debug: WooCommerce active: ' . (class_exists('WooCommerce') ? 'yes' : 'no'));
            
            // Validate input
            if (empty(trim($message))) {
                return [
                    'success' => false,
                    'message' => 'Leeg bericht ontvangen.'
                ];
            }
        
        // Search FAQ (simplified and robust)
        global $wpdb;
        $faq_table = $wpdb->prefix . 'aipc_faq';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $faq_table)) === $faq_table) {
            // Clean search terms
            $original_message = strtolower(trim($message));
            $search_terms = explode(' ', $original_message);
            
            // Filter out stop words and short terms
            $stop_words = ['de', 'het', 'een', 'voor', 'van', 'is', 'zijn', 'wat', 'hoe', 'welke', 'waar', 'wanneer'];
            $clean_terms = [];
            foreach ($search_terms as $term) {
                // Remove punctuation and trim
                $term = trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', $term));
                if (strlen($term) >= 3 && !in_array($term, $stop_words)) {
                    $clean_terms[] = $term;
                }
            }
            
            if (!empty($clean_terms)) {
                // Try exact phrase match first
                $faq_results = $wpdb->get_results($wpdb->prepare(
                    "SELECT question, answer FROM {$faq_table} 
                     WHERE status != 'deleted' 
                     AND (question LIKE %s OR answer LIKE %s) 
                     ORDER BY 
                       CASE WHEN question LIKE %s THEN 1 ELSE 2 END,
                       CHAR_LENGTH(question) ASC 
                     LIMIT 2",
                    '%' . $wpdb->esc_like($original_message) . '%',
                    '%' . $wpdb->esc_like($original_message) . '%',
                    '%' . $wpdb->esc_like($original_message) . '%'
                ));
                
                // If no exact phrase match, try individual terms
                if (empty($faq_results)) {
                    $like_conditions = [];
                    $params = [];
                    
                    foreach ($clean_terms as $term) {
                        $like_conditions[] = "(question LIKE %s OR answer LIKE %s)";
                        $params[] = '%' . $wpdb->esc_like($term) . '%';
                        $params[] = '%' . $wpdb->esc_like($term) . '%';
                    }
                    
                    if (!empty($like_conditions)) {
                        $where_clause = implode(' OR ', $like_conditions);
                        $faq_results = $wpdb->get_results($wpdb->prepare(
                            "SELECT question, answer FROM {$faq_table} 
                             WHERE status != 'deleted' AND ({$where_clause}) 
                             ORDER BY CHAR_LENGTH(question) ASC 
                             LIMIT 2",
                            ...$params
                        ));
                    }
                }
                
                // Add FAQ results
                if ($faq_results) {
                    $found_matches = true;
                    foreach ($faq_results as $faq) {
                        $results[] = "**Q: {$faq->question}**\n{$faq->answer}";
                    }
                }
            }
        }
        
        // Check for ingredient questions FIRST (before product search)
        $is_ingredient_question = $this->is_ingredient_question(strtolower($message));
        
        if ($is_ingredient_question && class_exists('WooCommerce')) {
            $ingredient_response = $this->get_ingredient_benefits_with_products($message);
            if (!empty($ingredient_response)) {
                $found_matches = true;
                $results[] = $ingredient_response;
            }
        }
        
        // Search WooCommerce products if available (only if no FAQ results or specific product query, and not ingredient question)
        if (class_exists('WooCommerce') && !$is_ingredient_question) {
            // Check if this looks like a product search
            $product_keywords = ['product', 'crème', 'creme', 'olie', 'serum', 'lotion', 'gel', 'balsem', 'masker', 'scrub', 'reiniger'];
            $is_product_query = false;
            
            foreach ($product_keywords as $keyword) {
                if (strpos(strtolower($message), $keyword) !== false) {
                    $is_product_query = true;
                    break;
                }
            }
            
            // Search products if it's a product query OR if no FAQ matches found
            if ($is_product_query || !$found_matches) {
                // Use full message for better context matching
                $search_query = $message;
                
                // First try search with important terms
                $products = wc_get_products([
                    'status' => 'publish',
                    'limit' => 20,
                    's' => $search_query
                ]);
                
                
                
                // If few results, try with individual keywords to get more variety
                if (count($products) < 5 && !empty($clean_terms)) {
                    // Try each clean term separately
                    foreach ($clean_terms as $term) {
                        if (strlen($term) >= 3) {
                            $term_products = wc_get_products([
                                'status' => 'publish',
                                'limit' => 10,
                                's' => $term
                            ]);
                            
                            
                            if ($term_products) {
                                $products = array_merge($products, $term_products);
                            }
                        }
                    }
                    
                    // Remove duplicates but keep more products for relevance checking
                    if (!empty($products)) {
                        $unique_products = [];
                        $product_ids = [];
                        foreach ($products as $product) {
                            if (!in_array($product->get_id(), $product_ids)) {
                                $unique_products[] = $product;
                                $product_ids[] = $product->get_id();
                                if (count($unique_products) >= 20) break; // Keep more for filtering
                            }
                        }
                        $products = $unique_products;
                    }
                }
                
                if ($products) {
                    $relevant_products = [];
                    
                    // Check if this might be a navigation request before processing products
                    $navigation_commands = ['volgende', 'volgend', 'meer', 'andere', 'next', 'show more'];
                    $might_be_navigation = false;
                    foreach ($navigation_commands as $nav_cmd) {
                        if (stripos($message, $nav_cmd) !== false) {
                            $might_be_navigation = true;
                            break;
                        }
                    }
                    
                    // If this might be navigation, check existing state first
                    if ($might_be_navigation) {
                        $cid = $conversation_id ?: wp_generate_uuid4();
                        $existing_nav_state = get_transient('aipc_nav_state_' . $cid);
                        
                        if ($existing_nav_state) {
                            // Skip product searching and jump to navigation handling
                            $current_index = min($existing_nav_state['current_index'] + 1, $existing_nav_state['total_products'] - 1);
                            $existing_nav_state['current_index'] = $current_index;
                            
                            // Recreate products from stored IDs
                            $relevant_products = [];
                            foreach ($existing_nav_state['product_ids'] as $product_id) {
                                $product = wc_get_product($product_id);
                                if ($product && $product->is_visible()) {
                                    $relevant_products[] = $product;
                                }
                            }
                            
                            // Save updated state
                            set_transient('aipc_nav_state_' . $cid, $existing_nav_state, 30 * MINUTE_IN_SECONDS);
                            
                            // Show current product
                            if (!empty($relevant_products) && isset($relevant_products[$current_index])) {
                                $found_matches = true;
                                $current_product = $relevant_products[$current_index];
                                
                                $description = $current_product->get_short_description();
                                if (empty($description)) {
                                    $description = wp_trim_words($current_product->get_description(), 20);
                                }
                                if (empty($description)) {
                                    $description = 'Bekijk productpagina voor meer informatie.';
                                }
                                
                                $product_info = "**{$current_product->get_name()}**\n{$description}\n[Bekijk product]({$current_product->get_permalink()})";
                                
                                // Add navigation buttons
                                $current_num = $current_index + 1;
                                $total_num = $existing_nav_state['total_products'];
                                $nav_info = "\n\n**Product {$current_num} van {$total_num}**\n\n";
                                
                                if ($current_index < $existing_nav_state['total_products'] - 1) {
                                    $nav_info .= "Is dit het product dat je zocht?\n\n";
                                    $nav_info .= "<button class='aipc-nav-btn' data-command='volgende'>Toon volgend product</button>\n";
                                } else {
                                    $nav_info .= "Dit was het laatste gevonden product voor je zoekopdracht.\n\n";
                                    $nav_info .= "<button class='aipc-nav-btn' data-command='reset-search'>Nieuwe zoekopdracht starten</button>\n";
                                }
                                
                                $results[] = $product_info . $nav_info;
                                
                                // Skip the rest of the product search logic
                                goto generate_response;
                            }
                        }
                    }
                    foreach ($products as $product) {
                        // Get product info for relevance checking - include all searchable content
                        $product_name = strtolower($product->get_name());
                        $short_desc = strtolower($product->get_short_description());
                        $long_desc = strtolower($product->get_description());
                        
                        // Also include categories, tags, and other searchable fields
                        $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
                        $tags = wp_get_post_terms($product->get_id(), 'product_tag', ['fields' => 'names']);
                        $cat_text = !empty($categories) ? strtolower(implode(' ', $categories)) : '';
                        $tag_text = !empty($tags) ? strtolower(implode(' ', $tags)) : '';
                        
                        $search_content = $product_name . ' ' . $short_desc . ' ' . $long_desc . ' ' . $cat_text . ' ' . $tag_text;
                        
                        
                        // Smart relevance checking based on context
                        $is_relevant = false;
                        $matched_terms = [];
                        $relevance_score = 0;
                        
                        // Dynamic relevance scoring without hardcoded lists
                        $filler_words = ['heb', 'heeft', 'van', 'voor', 'een', 'het', 'de', 'is', 'zijn', 'wordt', 'kan', 'met', 'aan', 'bij', 'op', 'in', 'last', 'mijn', 'mij', 'ook', 'wel', 'nog', 'dan', 'maar', 'want', 'dus', 'dat', 'dit', 'die', 'als', 'wat', 'wie', 'waar', 'wanneer', 'hoe', 'waarom'];
                        
                        // Get original query words for matching
                        $original_words = explode(' ', strtolower($message));
                        
                        // Calculate dynamic relevance score
                        foreach ($original_words as $word) {
                            $word = trim($word);
                            if (strlen($word) >= 3 && !in_array($word, $filler_words)) {
                                if (strpos($search_content, $word) !== false) {
                                    $matched_terms[] = $word;
                                    
                                    // Base score for any match
                                    $relevance_score++;
                                    
                                    // Bonus points for title matches (most important)
                                    if (strpos($product_name, $word) !== false) {
                                        $relevance_score += 3; // Strong bonus for title match
                                    }
                                    
                                    // Extra bonus for description matches (where benefits are usually described)
                                    if (strpos($short_desc . ' ' . $long_desc, $word) !== false) {
                                        $relevance_score += 2; // Description matches are important for benefits
                                    }
                                }
                            }
                        }
                        
                        // Dynamic relevance calculation - no hardcoded medical logic
                        $meaningful_words = count(array_filter($original_words, function($w) use ($filler_words) { return strlen(trim($w)) >= 3 && !in_array($w, $filler_words); }));
                        
                        if ($meaningful_words == 0) {
                            // Query contains only filler words - not relevant
                            $is_relevant = false;
                        } else {
                            // Use dynamic scoring - any product that matches search terms is potentially relevant
                            $min_score = max(1, floor($meaningful_words * 0.3));
                            $is_relevant = $relevance_score >= $min_score;
                        }
                        
                        
                        // Skip irrelevant products (like gift boxes for medical terms)
                        $irrelevant_terms = ['geschenk', 'cadeau', 'gift', 'box', 'doosje', 'pakket'];
                        $contains_irrelevant = false;
                        foreach ($irrelevant_terms as $irrelevant) {
                            if (strpos($product_name, $irrelevant) !== false) {
                                $contains_irrelevant = true;
                                break;
                            }
                        }
                        
                        if ($is_relevant && !$contains_irrelevant) {
                            // Store product with its relevance score for sorting
                            $relevant_products[] = [
                                'product' => $product,
                                'relevance_score' => $relevance_score
                            ];
                        }
                    }
                    
                    // Sort products by relevance score (highest first)
                    if (!empty($relevant_products)) {
                        usort($relevant_products, function($a, $b) {
                            return $b['relevance_score'] - $a['relevance_score'];
                        });
                        
                        // Extract just the product objects for the rest of the code
                        $sorted_products = array_map(function($item) { return $item['product']; }, $relevant_products);
                        $relevant_products = $sorted_products;
                    }
                    
                    if (!empty($relevant_products)) {
                        // Check if this is a navigation request
                        $navigation_commands = ['volgende', 'volgend', 'meer', 'andere', 'next', 'show more'];
                        $is_navigation = false;
                        
                        foreach ($navigation_commands as $nav_cmd) {
                            if (stripos($message, $nav_cmd) !== false) {
                                $is_navigation = true;
                                break;
                            }
                        }
                        
                        // Get current navigation state from session
                        $cid = $conversation_id ?: wp_generate_uuid4();
                        $nav_state = get_transient('aipc_nav_state_' . $cid);
                        
                        if (!$nav_state || !$is_navigation) {
                            // New search - start from beginning
                            $current_index = 0;
                            $search_query = $message;
                            $product_ids = array_map(function($p) { return $p->get_id(); }, $relevant_products);
                            
                            $nav_state = [
                                'search_query' => $search_query,
                                'product_ids' => $product_ids,
                                'current_index' => $current_index,
                                'total_products' => count($relevant_products)
                            ];
                        } else {
                            // Navigation request - move to next product
                            $current_index = min($nav_state['current_index'] + 1, $nav_state['total_products'] - 1);
                            $nav_state['current_index'] = $current_index;
                            
                            // Recreate products from stored IDs
                            $relevant_products = [];
                            foreach ($nav_state['product_ids'] as $product_id) {
                                $product = wc_get_product($product_id);
                                if ($product && $product->is_visible()) {
                                    $relevant_products[] = $product;
                                }
                            }
                        }
                        
                        // Save updated state
                        set_transient('aipc_nav_state_' . $cid, $nav_state, 30 * MINUTE_IN_SECONDS);
                        
                        // Show current product if we have any
                        if (!empty($relevant_products) && isset($relevant_products[$current_index])) {
                            $found_matches = true;
                            $current_product = $relevant_products[$current_index];
                            
                            // Smart description selection - use long description if it contains search terms
                            $short_desc = $current_product->get_short_description();
                            $long_desc = $current_product->get_description();
                            
                            // Check if query terms are in long but not short description
                            $query_in_short = false;
                            $query_in_long = false;
                            
                            foreach ($original_words as $word) {
                                $word = trim($word);
                                if (strlen($word) >= 3) {
                                    if (stripos($short_desc, $word) !== false) $query_in_short = true;
                                    if (stripos($long_desc, $word) !== false) $query_in_long = true;
                                }
                            }
                            
                            // Use long description if it has query terms but short doesn't, otherwise prefer short
                            if (!$query_in_short && $query_in_long && !empty($long_desc)) {
                                $description = wp_trim_words($long_desc, 50); // More words for relevant content
                            } elseif (!empty($short_desc)) {
                                $description = $short_desc;
                            } elseif (!empty($long_desc)) {
                                $description = wp_trim_words($long_desc, 20);
                            } else {
                                $description = 'Bekijk productpagina voor meer informatie.';
                            }
                            
                            $product_info = "**{$current_product->get_name()}**\n{$description}\n[Bekijk product]({$current_product->get_permalink()})";
                            
                            // Add navigation buttons
                            $current_num = $current_index + 1;
                            $total_num = $nav_state['total_products'];
                            $nav_info = "\n\n**Product {$current_num} van {$total_num}**\n\n";
                            
                            if ($current_index < $nav_state['total_products'] - 1) {
                                $nav_info .= "Is dit het product dat je zocht?\n\n";
                                $nav_info .= "<button class='aipc-nav-btn' data-command='volgende'>Toon volgend product</button>\n";
                            } else {
                                $nav_info .= "Dit was het laatste gevonden product voor je zoekopdracht.\n\n";
                                $nav_info .= "<button class='aipc-nav-btn' data-command='reset-search'>Nieuwe zoekopdracht starten</button>\n";
                            }
                            
                            $results[] = $product_info . $nav_info;
                        }
                    }
                }
            }
        }
        
        generate_response:
        // Generate response
        if ($found_matches && !empty($results)) {
            // Check if this is an ingredient question response
            $is_ingredient_response = $this->is_ingredient_question(strtolower($message));
            
            // Check if the first result is an FAQ (contains **Q: pattern)
            $is_faq_response = !empty($results) && strpos($results[0], '**Q:') !== false;
            
            if ($is_ingredient_response) {
                // Ingredient question - show response without confusing intro
                $response_text = implode("", $results);
            } elseif ($is_faq_response) {
                // FAQ response - show answer without product prefix
                $response_text = implode("", $results);
            } else {
                // Check if this is a navigation context or new product search
                $cid = $conversation_id ?: wp_generate_uuid4();
                $nav_state = get_transient('aipc_nav_state_' . $cid);
                
                // Check if this is a navigation command
                $navigation_commands = ['volgende', 'volgend', 'meer', 'andere', 'next', 'show more'];
                $is_navigation_command = false;
                foreach ($navigation_commands as $nav_cmd) {
                    if (stripos($message, $nav_cmd) !== false) {
                        $is_navigation_command = true;
                        break;
                    }
                }
                
                if ($nav_state && $is_navigation_command) {
                    // Navigation context - show current product without prefix
                    $response_text = implode("", $results);
                } else {
                    // New product search - show first result with prefix
                    $response_text = "Ik heb meerdere producten gevonden voor je zoekopdracht. Hier is het eerste product:\n\n" . implode("", $results);
                }
            }
        } else {
            // Clear any existing navigation state if no matches found
            $cid = $conversation_id ?: wp_generate_uuid4();
            delete_transient('aipc_nav_state_' . $cid);
            
            // Create generic fallback response
            $response_text = "Sorry, ik kon geen direct antwoord vinden op je vraag.\n\n";
            $response_text .= "**Wat kan ik voor je doen?**\n";
            $response_text .= "• Stel een vraag over onze producten of services\n";
            $response_text .= "• Zoek naar specifieke producten\n";
            $response_text .= "• Probeer andere zoektermen\n";
            $response_text .= "• Check onze veelgestelde vragen (FAQ)\n\n";
            $response_text .= "**Heb je specifieke vragen? Neem gerust contact met ons op!**";
        }
        
        // Ensure we have a response
        if (empty(trim($response_text))) {
            $response_text = 'Sorry, ik kon geen passend antwoord vinden. Probeer een andere vraag of neem contact met ons op.';
        }
        
        // Store conversation if enabled
        $store_conversations = get_option('aipc_store_conversations', true);
        if ($store_conversations) {
            $conversations_table = $wpdb->prefix . 'aipc_conversations';
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $conversations_table)) === $conversations_table) {
                $cid = $conversation_id ?: wp_generate_uuid4();
                
                // Store user message
                $wpdb->insert($conversations_table, [
                    'conversation_id' => $cid,
                    'role' => 'user',
                    'content' => $message,
                    'created_at' => current_time('mysql')
                ]);
                
                // Store assistant response
                $wpdb->insert($conversations_table, [
                    'conversation_id' => $cid,
                    'role' => 'assistant', 
                    'content' => $response_text,
                    'created_at' => current_time('mysql')
                ]);
            }
        }
        
        return [
            'success' => true,
            'data' => [
                'message' => $response_text,
                'conversation_id' => $conversation_id ?: wp_generate_uuid4()
            ]
        ];
        } catch (Exception $e) {
            // Log error and return fallback
            error_log('AIPC Basic Message Error: ' . $e->getMessage());
            return [
                'success' => true,
                'data' => [
                    'message' => 'Sorry, er is een technische fout opgetreden. Probeer het opnieuw of neem contact met ons op.',
                    'conversation_id' => $conversation_id ?: wp_generate_uuid4()
                ]
            ];
        }
    }
    
    private function is_ingredient_question($message_lower) {
        // Check if message contains ingredient-related question patterns
        $question_patterns = [
            // Specific ingredient questions
            'voordelen.*heeft',      // "Welke voordelen heeft X?"
            'voordelen.*van',        // "voordelen van X"
            'voordeel.*heeft',       // "Welk voordeel heeft X?"
            'voordeel.*van',         // "voordeel van X"
            'wat doet',              // "wat doet X"
            'wat is',                // "wat is X?"
            'werking.*van',          // "werking van X"
            'eigenschappen.*van',    // "eigenschappen van X"
            'effect.*van',           // "effect van X"
            'waarom.*gebruiken',     // "waarom X gebruiken"
            'waarvoor.*gebruikt',    // "waarvoor wordt X gebruikt"
            'helpt.*bij',            // "helpt X bij..."
            'goed voor',             // "is X goed voor"
            'nut.*van',              // "nut van X"
            'functie.*van',          // "functie van X"
            // English patterns
            'what is',               // "what is X?"
            'benefits.*of',          // "benefits of X"
            'benefit.*of',           // "benefit of X"
            'what.*does.*do',        // "what does X do"
            'effects.*of',           // "effects of X"
            'properties.*of',        // "properties of X"
            'good for',              // "is X good for"
            'helps with',            // "X helps with"
            'function.*of',          // "function of X"
            'purpose.*of'            // "purpose of X"
        ];
        
        // Check for specific ingredient question patterns first
        foreach ($question_patterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $message_lower)) {
                return true;
            }
        }
        
        // For general inquiry patterns, check if it might be about ingredients
        $general_patterns = [
            'meer vertellen over',   // "kun je meer vertellen over X?"
            'vertel.*over',          // "vertel me over X"
            'informatie.*over',      // "informatie over X"
            'uitleggen.*over',       // "kun je uitleggen over X?"
            'weten.*over',           // "wat moet ik weten over X?"
            'info.*over',            // "info over X"
            'tell.*about',           // "tell me about X"
            'information.*about',    // "information about X"
            'know.*about'            // "what should I know about X"
        ];
        
        foreach ($general_patterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $message_lower)) {
                // For general patterns, also check if it might contain ingredient terms
                // by looking for words that could be ingredients (non-product terms)
                if ($this->contains_potential_ingredient($message_lower)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private function contains_potential_ingredient($message_lower) {
        // Check if message contains terms that are likely ingredients
        // by excluding obvious product/business terms
        $product_terms = [
            'product', 'producten', 'crème', 'creme', 'lotion', 'serum', 
            'olie', 'gel', 'masker', 'scrub', 'reiniger', 'balsem',
            'bedrijf', 'service', 'winkel', 'bestelling', 'levering', 'prijs'
        ];
        
        // If it contains obvious product terms, it's likely not about ingredients
        foreach ($product_terms as $term) {
            if (strpos($message_lower, $term) !== false) {
                return false;
            }
        }
        
        // Look for ingredient-like terms (chemical/botanical names)
        // This is a heuristic approach - if word contains 'acid', 'zuur', 'olie', etc.
        $ingredient_indicators = [
            'acid', 'zuur', 'vera', 'extract', 'butter', 'boter', 
            'vitamine', 'vitamin', 'oxide', 'hyaluron'
        ];
        
        foreach ($ingredient_indicators as $indicator) {
            if (strpos($message_lower, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function get_ingredient_benefits_with_products($message) {
        if (!class_exists('WooCommerce')) {
            return '';
        }
        
        $message_lower = strtolower($message);
        
        // Get WooCommerce products to search for ingredients
        $products = wc_get_products([
            'status' => 'publish',
            'limit' => -1  // Get all products to search through
        ]);
        
        if (empty($products)) {
            return '';
        }
        
        // Extract ingredients from product descriptions and find matches
        $found_ingredient = null;
        $ingredient_name = '';
        $matching_products = [];
        
        // Ingredient detection will use database-only approach
        
        // Extract ingredient name from message for database lookup
        $ingredient_name = $this->extract_ingredient_from_message($message_lower);
        
        // If no ingredient extracted from message, try to find in product descriptions
        if (!$ingredient_name) {
            foreach ($products as $product) {
                $description = strtolower($product->get_description() . ' ' . $product->get_short_description());
                // Look for potential ingredient mentions that match the user's query
                $potential_ingredient = $this->find_ingredient_in_text($message_lower, $description);
                if ($potential_ingredient) {
                    $ingredient_name = $potential_ingredient;
                    break;
                }
            }
        }
        
        if (!$ingredient_name) {
            return '';
        }
        
        // Find products that mention this ingredient
        foreach ($products as $product) {
            $product_text = strtolower($product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description());
            $ingredient_lower = strtolower($ingredient_name);
            
            if (strpos($product_text, $ingredient_lower) !== false) {
                $matching_products[] = [
                    'name' => $product->get_name(),
                    'price' => $product->get_price_html(),
                    'url' => $product->get_permalink()
                ];
            }
        }
        
        // Build response with ingredient benefits + matching products
        $response = "**Over " . $ingredient_name . ":**\n\n";
        
        // Add ingredient benefits
        $benefits = $this->get_ingredient_benefits($ingredient_name);
        if (!empty($benefits)) {
            $response .= $benefits . "\n\n";
        } else {
            $response .= "Dit ingrediënt wordt gebruikt in cosmetische producten om specifieke huidvoordelen te bieden.\n\n";
        }
        
        // Add matching products if found
        if (!empty($matching_products)) {
            $response .= "**Ik heb " . count($matching_products) . " producten gevonden die dit ingrediënt bevatten:**\n";
            
            foreach ($matching_products as $product) {
                // Clean HTML entities from price
                $clean_price = '';
                if (!empty($product['price'])) {
                    $clean_price = html_entity_decode(strip_tags($product['price']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $clean_price = ' - ' . $clean_price;
                }
                
                $response .= "• [" . $product['name'] . "](" . $product['url'] . ")" . $clean_price . "\n";
            }
        }
        
        return $response;
    }
    
    private function get_ingredient_benefits($ingredient_name) {
        // Only check database/admin-defined ingredients now
        global $wpdb;
        $ingredient_table = $wpdb->prefix . 'aipc_ingredients';
        
        // Check if ingredients table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $ingredient_table)) === $ingredient_table) {
            $ingredient = $wpdb->get_row($wpdb->prepare(
                "SELECT description, benefits FROM $ingredient_table WHERE LOWER(name) = %s AND status != 'deleted'",
                strtolower($ingredient_name)
            ));
            
            if ($ingredient) {
                // Prefer description over benefits for main explanation
                if (!empty($ingredient->description)) {
                    return $ingredient->description;
                }
                // Fallback to benefits if no description (for backwards compatibility)
                if (!empty($ingredient->benefits)) {
                    $benefits = json_decode($ingredient->benefits, true);
                    if (is_array($benefits) && !empty($benefits)) {
                        return implode('. ', $benefits) . '.';
                    }
                }
            }
        }
        
        return '';
    }
    
    private function extract_ingredient_from_message($message_lower) {
        // Extract potential ingredient names from the message
        // Look for patterns like "over [ingredient]", "wat doet [ingredient]", etc.
        $patterns = [
            '/(?:over|about)\s+([\w\s]+?)(?:\?|$|\s+voor|\s+tegen|\s+bij)/i',
            '/(?:wat doet|what does)\s+([\w\s]+?)(?:\?|$|\s+voor|\s+tegen)/i',
            '/(?:voordelen van|benefits of)\s+([\w\s]+?)(?:\?|$)/i',
            '/(?:werking van|effects of)\s+([\w\s]+?)(?:\?|$)/i',
            '/(?:info.*over|information.*about)\s+([\w\s]+?)(?:\?|$)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message_lower, $matches)) {
                $potential = trim($matches[1]);
                // Clean up common words
                $potential = preg_replace('/\b(het|de|een|ingredient|ingrediënt|component)\b/i', '', $potential);
                $potential = trim(preg_replace('/\s+/', ' ', $potential));
                if (strlen($potential) > 2) {
                    return $potential;
                }
            }
        }
        
        return null;
    }
    
    private function find_ingredient_in_text($query, $text) {
        // Simple word matching to find ingredients mentioned in both query and text
        $query_words = array_filter(explode(' ', $query), function($word) {
            return strlen(trim($word)) > 3; // Skip short words
        });
        
        foreach ($query_words as $word) {
            $word = trim($word);
            if (strpos($text, $word) !== false) {
                // Found a match, return it as potential ingredient
                return $word;
            }
        }
        
        return null;
    }
    
    public function admin_dashboard() {
        include AIPC_PLUGIN_DIR . 'admin/dashboard.php';
    }
    
    public function admin_products() {
        include AIPC_PLUGIN_DIR . 'admin/products.php';
    }
    
    public function admin_ingredients() {
        include AIPC_PLUGIN_DIR . 'admin/ingredients.php';
    }
    
    public function admin_documents() {
        include AIPC_PLUGIN_DIR . 'admin/documents.php';
    }
    
    public function admin_settings() {
        include AIPC_PLUGIN_DIR . 'admin/settings.php';
    }

    public function admin_faq() {
        include AIPC_PLUGIN_DIR . 'admin/faq.php';
    }
    
    public function admin_skin_test() {
        include AIPC_PLUGIN_DIR . 'admin/skin-test.php';
    }
    
    public function admin_quiz_builder() {
        include AIPC_PLUGIN_DIR . 'admin/quiz-builder.php';
    }
    
    public function admin_woocommerce() {
        // Delegate to WooCommerce integration class
        if (class_exists('AIPC_WooCommerce_Integration')) {
            $integration = new AIPC_WooCommerce_Integration();
            $integration->admin_woocommerce_page();
        } else {
            echo '<div class="wrap"><h1>WooCommerce integratie niet beschikbaar</h1><p>WooCommerce integratie klasse niet gevonden.</p></div>';
        }
    }
    
    public function admin_license_compare() {
        include AIPC_PLUGIN_DIR . 'admin/license-compare.php';
    }
    
    public function admin_analytics() {
        include AIPC_PLUGIN_DIR . 'admin/analytics.php';
    }
    
    public function admin_api_monitoring() {
        include AIPC_PLUGIN_DIR . 'admin/api-monitoring.php';
    }
    
    public function admin_license_fix() {
        include AIPC_PLUGIN_DIR . 'admin/license-fix.php';
    }

    private function ensure_tables() {
        global $wpdb;
        $faq_table = $wpdb->prefix . 'aipc_faq';
        $products_table = $wpdb->prefix . 'aipc_products';
        
        $faq_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $faq_table)) === $faq_table);
        $products_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $products_table)) === $products_table);
        
        if (!$faq_exists || !$products_exists) {
            if (class_exists('AIPC_Database')) {
                AIPC_Database::create_tables();
                // Reset the products removed flag so products can be used again
                delete_option('aipc_products_removed');
            }
        }
    }

    private function cleanup_legacy_product_tables() {
        if (get_option('aipc_products_removed', false)) {
            return;
        }
        global $wpdb;
        $tables = [
            $wpdb->prefix . 'aipc_products',
            $wpdb->prefix . 'aipc_ingredients'
        ];
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        update_option('aipc_products_removed', 1);
    }

    public function cron_purge_old_conversations() {
        $days = absint(get_option('aipc_retention_days', 90));
        if ($days <= 0) {
            return; // disabled
        }
        global $wpdb;
        $table = $wpdb->prefix . 'aipc_conversations';
        $date = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE created_at < %s", $date));
    }
    
    public function cron_cleanup_monitoring() {
        $days = absint(get_option('aipc_monitoring_retention_days', 30));
        if ($days <= 0) {
            return; // disabled
        }
        
        $api_monitor = AIPC_API_Monitor::getInstance();
        $deleted = $api_monitor->cleanup_old_data($days);
        
        // Optional: Log cleanup activity
        if ($deleted > 0) {
            error_log("AIPC: Cleaned up {$deleted} old monitoring records (older than {$days} days)");
        }
    }

    public function admin_purge_conversations() {
        check_ajax_referer('aipc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Onvoldoende rechten.', 'ai-product-chatbot')]);
        }
        $days = absint(get_option('aipc_retention_days', 90));
        global $wpdb;
        $table = $wpdb->prefix . 'aipc_conversations';
        $date = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE created_at < %s", $date));
        wp_send_json_success(['deleted' => intval($deleted)]);
    }

    public function admin_export_conversations() {
        check_ajax_referer('aipc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Onvoldoende rechten.', 'ai-product-chatbot')]);
        }
        global $wpdb;
        $table = $wpdb->prefix . 'aipc_conversations';
        $rows = $wpdb->get_results("SELECT conversation_id, role, content, created_at FROM $table ORDER BY conversation_id, created_at", ARRAY_A);
        wp_send_json_success(['conversations' => $rows, 'count' => count($rows)]);
    }

    

    public function admin_get_conversation() {
        check_ajax_referer('aipc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Onvoldoende rechten.', 'ai-product-chatbot')]);
        }
        $conv = isset($_POST['conversation_id']) ? sanitize_text_field($_POST['conversation_id']) : '';
        if (empty($conv)) {
            wp_send_json_error(['message' => __('Ongeldige conversatie.', 'ai-product-chatbot')]);
        }
        global $wpdb;
        $table = $wpdb->prefix . 'aipc_conversations';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT role, content, created_at FROM $table WHERE conversation_id=%s ORDER BY created_at ASC", $conv), ARRAY_A);
        wp_send_json_success(['messages' => $rows]);
    }

    public function admin_test_api() {
        check_ajax_referer('aipc_test_api', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Onvoldoende rechten.', 'ai-product-chatbot')]);
        }
        $api_key = get_option('aipc_openai_api_key');
        $provider = get_option('aipc_api_provider', 'openai');
        $api_base = trim(get_option('aipc_api_base', ''));
        if (empty($api_base)) {
            $api_base = ($provider === 'openrouter') ? 'https://openrouter.ai/api/v1' : 'https://api.openai.com/v1';
        }
        if (empty($api_key)) {
            wp_send_json_error(['message' => __('Geen API key geconfigureerd.', 'ai-product-chatbot')]);
        }
        $response = wp_remote_get(rtrim($api_base, '/') . '/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                // OpenRouter aanmoedigingen (geen harde vereiste, maar helpt)
                'HTTP-Referer' => home_url('/'),
                'X-Title' => get_bloginfo('name')
            ],
            'timeout' => 20
        ]);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => __('Verbindingsfout met OpenAI API.', 'ai-product-chatbot')]);
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            wp_send_json_success(['message' => __('API verbinding OK', 'ai-product-chatbot')]);
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $msg = isset($data['error']['message']) ? $data['error']['message'] : __('Onbekende API fout.', 'ai-product-chatbot');
        wp_send_json_error(['message' => $msg]);
    }

    public function admin_fetch_models() {
        check_ajax_referer('aipc_test_api', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Onvoldoende rechten.', 'ai-product-chatbot')]);
        }
        $api_key = get_option('aipc_openai_api_key');
        $provider = get_option('aipc_api_provider', 'openai');
        $api_base = trim(get_option('aipc_api_base', ''));
        if (empty($api_base)) {
            $api_base = ($provider === 'openrouter') ? 'https://openrouter.ai/api/v1' : 'https://api.openai.com/v1';
        }
        if (empty($api_key)) {
            wp_send_json_error(['message' => __('Geen API key geconfigureerd.', 'ai-product-chatbot')]);
        }
        $headers = [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ];
        if ($provider === 'openrouter') {
            $headers['HTTP-Referer'] = home_url('/');
            $headers['X-Title'] = get_bloginfo('name');
        }
        $resp = wp_remote_get(rtrim($api_base, '/') . '/models', [
            'headers' => $headers,
            'timeout' => 20
        ]);
        if (is_wp_error($resp)) {
            wp_send_json_error(['message' => __('Verbindingsfout met models endpoint.', 'ai-product-chatbot')]);
        }
        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code !== 200 || isset($body['error'])) {
            $msg = isset($body['error']['message']) ? $body['error']['message'] : __('Onbekende API fout.', 'ai-product-chatbot');
            wp_send_json_error(['message' => $msg]);
        }
        $ids = [];
        if (isset($body['data']) && is_array($body['data'])) {
            foreach ($body['data'] as $m) {
                if (isset($m['id'])) $ids[] = $m['id'];
            }
        }
        // Fallback structure sometimes under 'models'
        if (empty($ids) && isset($body['models']) && is_array($body['models'])) {
            foreach ($body['models'] as $m) {
                if (isset($m['id'])) $ids[] = $m['id'];
            }
        }
        if (empty($ids)) {
            wp_send_json_error(['message' => __('Geen modellen gevonden in API response.', 'ai-product-chatbot')]);
        }
        
        // Sort models - put free models first, then popular ones
        usort($ids, function($a, $b) {
            $a_free = strpos($a, ':free') !== false;
            $b_free = strpos($b, ':free') !== false;
            
            if ($a_free && !$b_free) return -1;
            if (!$a_free && $b_free) return 1;
            
            // Both free or both paid - sort alphabetically
            return strcmp($a, $b);
        });
        
        wp_send_json_success(['models' => $ids]);
    }

    public function admin_openrouter_key_info() {
        check_ajax_referer('aipc_test_api', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Onvoldoende rechten.', 'ai-product-chatbot')]);
        }
        $provider = get_option('aipc_api_provider', 'openai');
        if ($provider !== 'openrouter') {
            wp_send_json_error(['message' => __('Alleen beschikbaar voor OpenRouter provider.', 'ai-product-chatbot')]);
        }
        $api_key = get_option('aipc_openai_api_key');
        if (empty($api_key)) {
            wp_send_json_error(['message' => __('Geen OpenRouter API key geconfigureerd.', 'ai-product-chatbot')]);
        }
        $headers = [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => home_url('/'),
            'X-Title' => get_bloginfo('name')
        ];
        $resp = wp_remote_get('https://openrouter.ai/api/v1/key', [
            'headers' => $headers,
            'timeout' => 20
        ]);
        if (is_wp_error($resp)) {
            wp_send_json_error(['message' => __('Verbindingsfout met OpenRouter key endpoint.', 'ai-product-chatbot')]);
        }
        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code !== 200 || isset($body['error'])) {
            $msg = isset($body['error']['message']) ? $body['error']['message'] : __('Onbekende API fout.', 'ai-product-chatbot');
            wp_send_json_error(['message' => $msg]);
        }
        // Normalize minimal fields
        $data = isset($body['data']) ? $body['data'] : $body;
        $out = [
            'label' => isset($data['label']) ? $data['label'] : '',
            'usage' => isset($data['usage']) ? intval($data['usage']) : 0,
            'limit' => isset($data['limit']) ? $data['limit'] : null,
            'is_free_tier' => isset($data['is_free_tier']) ? (bool)$data['is_free_tier'] : null,
        ];
        wp_send_json_success(['key' => $out]);
    }

    public function admin_openrouter_free_usage() {
        check_ajax_referer('aipc_test_api', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Onvoldoende rechten.', 'ai-product-chatbot')]);
        }
        $provider = get_option('aipc_api_provider', 'openai');
        $model = get_option('aipc_openai_model', '');
        if ($provider !== 'openrouter' || substr($model, -5) !== ':free') {
            wp_send_json_error(['message' => __('Geen :free model actief.', 'ai-product-chatbot')]);
        }
        // Daily used (local counter)
        $key = 'aipc_or_free_used_' . gmdate('Ymd');
        $used = intval(get_option($key, 0));

        // Get credits state from OpenRouter to determine daily cap per docs
        $api_key = get_option('aipc_openai_api_key');
        $headers = [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => home_url('/'),
            'X-Title' => get_bloginfo('name')
        ];
        $resp = wp_remote_get('https://openrouter.ai/api/v1/key', [
            'headers' => $headers,
            'timeout' => 20
        ]);
        $daily_cap = 50; // default if <10 credits
        if (!is_wp_error($resp)) {
            $code = wp_remote_retrieve_response_code($resp);
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            if ($code === 200 && isset($body['data'])) {
                $data = $body['data'];
                // If user purchased at least 10 credits -> 1000 free requests/day per docs
                if (isset($data['usage']) && isset($data['limit']) && is_numeric($data['limit'])) {
                    $purchased = intval($data['limit']);
                    if ($purchased >= 10) {
                        $daily_cap = 1000;
                    }
                }
            }
        }
        $remaining = max(0, $daily_cap - $used);
        wp_send_json_success([
            'used_today' => $used,
            'daily_cap' => $daily_cap,
            'remaining' => $remaining
        ]);
    }

    public function admin_search_products() {
        check_ajax_referer('aipc_test_api', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Onvoldoende rechten.', 'ai-product-chatbot')]);
        }
        if (!class_exists('WooCommerce')) {
            wp_send_json_error(['message' => __('WooCommerce niet actief.', 'ai-product-chatbot')]);
        }
        $q = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';
        $page = max(1, intval($_POST['page'] ?? 1));
        $limit = intval($_POST['limit'] ?? 20);
        // Allow unlimited results with -1, but cap at reasonable number for performance
        if ($limit === -1) {
            $per_page = 999; // Get up to 999 products (reasonable limit)
        } else {
            $per_page = min(max(1, $limit), 999); // Between 1 and 999
        }
        
        // Input validation for search query
        if (strlen($q) > 200) {
            wp_send_json_error(['message' => __('Zoekterm te lang.', 'ai-product-chatbot')]);
        }
        
        if ($page > 100) {
            wp_send_json_error(['message' => __('Pagina te hoog.', 'ai-product-chatbot')]);
        }
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            's' => $q,
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids'
        ];
        $query = new WP_Query($args);
        $ids = !empty($query->posts) ? $query->posts : [];
        // Extra: zoeken op SKU (gedeeltelijke match)
        if ($q !== '') {
            $sku_q = new WP_Query([
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => $per_page,
                'paged' => $page,
                'orderby' => 'title',
                'order' => 'ASC',
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => '_sku',
                        'value' => $q,
                        'compare' => 'LIKE'
                    ]
                ]
            ]);
            if (!empty($sku_q->posts)) {
                $ids = array_values(array_unique(array_merge($ids, $sku_q->posts)));
            }
        }
        $items = [];
        if (!empty($ids)) {
            foreach ($ids as $pid) {
                $prod = wc_get_product($pid);
                if (!$prod) { continue; }
                $items[] = [
                    'id' => $pid,
                    'name' => html_entity_decode(get_the_title($pid), ENT_QUOTES, 'UTF-8'),
                    'price' => $prod->get_price(),
                    'price_html' => $prod->get_price_html(),
                    'url' => get_permalink($pid),
                    'sku' => $prod->get_sku()
                ];
            }
        }
        wp_send_json_success([
            'results' => $items,
            'has_more' => ($query->max_num_pages > $page)
        ]);
    }
    
    public function admin_get_product_info() {
        error_log('=== AIPC PRODUCT INFO HANDLER START (MAIN CLASS) ===');
        error_log('POST data: ' . print_r($_POST, true));
        error_log('Request method: ' . $_SERVER['REQUEST_METHOD']);
        error_log('Current user can manage_options: ' . (current_user_can('manage_options') ? 'YES' : 'NO'));
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            error_log('AIPC: Permission denied');
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }
        
        // Verify nonce
        $nonce = $_POST['nonce'] ?? '';
        error_log('Received nonce: ' . $nonce);
        $nonce_valid = wp_verify_nonce($nonce, 'aipc_test_api');
        error_log('Nonce valid: ' . ($nonce_valid ? 'YES' : 'NO'));
        
        if (!$nonce_valid) {
            error_log('AIPC: Nonce verification failed');
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }
        
        $product_id = intval($_POST['product_id'] ?? 0);
        error_log('Product ID: ' . $product_id);
        
        if ($product_id <= 0) {
            error_log('AIPC: Invalid product ID');
            wp_send_json_error(['message' => 'Invalid product ID']);
            return;
        }
        
        // Try to get product info
        $product_info = null;
        error_log('Starting product info retrieval...');
        
        // First try WooCommerce
        error_log('WooCommerce class exists: ' . (class_exists('WooCommerce') ? 'YES' : 'NO'));
        if (class_exists('WooCommerce')) {
            $product = wc_get_product($product_id);
            error_log('WooCommerce product found: ' . ($product ? 'YES' : 'NO'));
            
            if ($product) {
                $product_info = [
                    'id' => $product_id,
                    'name' => $product->get_name(),
                    'price' => $product->get_price_html(),
                    'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: null,
                    'url' => get_permalink($product_id)
                ];
                error_log('WooCommerce product info: ' . print_r($product_info, true));
            }
        }
        
        // Fallback to AIPC products table
        if (!$product_info) {
            error_log('Trying AIPC products table fallback...');
            global $wpdb;
            $table = $wpdb->prefix . 'aipc_products';
            error_log('AIPC products table: ' . $table);
            
            $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table);
            error_log('AIPC products table exists: ' . ($table_exists ? 'YES' : 'NO'));
            
            if ($table_exists) {
                $product = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table WHERE id = %d",
                    $product_id
                ));
                error_log('AIPC product found: ' . ($product ? 'YES' : 'NO'));
                
                if ($product) {
                    $product_info = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price ? '€' . number_format($product->price, 2) : null,
                        'image' => $product->image_url ?: null,
                        'url' => $product->product_url ?: null
                    ];
                    error_log('AIPC product info: ' . print_r($product_info, true));
                }
            }
        }
        
        error_log('Final product info: ' . print_r($product_info, true));
        
        if ($product_info) {
            error_log('Sending success response');
            wp_send_json_success($product_info);
        } else {
            error_log('Sending error response - product not found');
            wp_send_json_error(['message' => 'Product not found']);
        }
    }
    
    public function admin_activate_license() {
        check_ajax_referer('aipc_test_api', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Onvoldoende rechten.', 'ai-product-chatbot')]);
        }
        
        $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
        
        // Debug logging
        error_log('AIPC License Activation: Key=' . substr($license_key, 0, 10) . '...');
        
        if (empty($license_key)) {
            wp_send_json_error(['message' => __('Licentie key is verplicht.', 'ai-product-chatbot')]);
        }
        
        // Check if license server URL is configured
        $server_url = get_option('aipc_license_server_url', '');
        if (empty($server_url)) {
            wp_send_json_error(['message' => __('Licentie server URL is niet geconfigureerd. Stel eerst de server URL in.', 'ai-product-chatbot')]);
        }
        
        if (!class_exists('AIPC_License')) {
            require_once AIPC_PLUGIN_DIR . 'includes/class-aipc-license.php';
        }
        
        try {
            $license = AIPC_License::getInstance();
            $result = $license->activate_license($license_key);
            
            // Debug logging
            error_log('AIPC License Result: ' . json_encode($result));
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (Exception $e) {
            error_log('AIPC License Exception: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Interne fout bij licentieactivatie: ', 'ai-product-chatbot') . $e->getMessage()]);
        }
    }
    
    public function admin_deactivate_license() {
        check_ajax_referer('aipc_test_api', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Onvoldoende rechten.', 'ai-product-chatbot')]);
        }
        
        if (!class_exists('AIPC_License')) {
            require_once AIPC_PLUGIN_DIR . 'includes/class-aipc-license.php';
        }
        
        $license = AIPC_License::getInstance();
        $result = $license->deactivate_license();
        
        wp_send_json_success($result);
    }

    public function admin_force_validate_license() {
        check_ajax_referer('aipc_test_api', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Onvoldoende rechten.', 'ai-product-chatbot')]);
        }
        if (!class_exists('AIPC_License')) {
            require_once AIPC_PLUGIN_DIR . 'includes/class-aipc-license.php';
        }
        $license = AIPC_License::getInstance();
        $res = $license->force_validate_now();
        if ($res['result'] === 'ok') {
            wp_send_json_success(['message' => __('Licentie opnieuw gevalideerd.', 'ai-product-chatbot')]);
        } elseif ($res['result'] === 'network_error') {
            wp_send_json_error(['message' => __('Licentieserver onbereikbaar. Probeer later opnieuw.', 'ai-product-chatbot')]);
        } else {
            wp_send_json_error(['message' => __('Licentie ongeldig of verlopen.', 'ai-product-chatbot')]);
        }
    }
    
    public function admin_cleanup_monitoring() {
        check_ajax_referer('aipc_test_api', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Onvoldoende rechten.', 'ai-product-chatbot')]);
        }
        
        $api_monitor = AIPC_API_Monitor::getInstance();
        $retention_days = absint(get_option('aipc_monitoring_retention_days', 30));
        
        if ($retention_days <= 0) {
            wp_send_json_error(['message' => __('Data retention is disabled.', 'ai-product-chatbot')]);
        }
        
        $deleted = $api_monitor->cleanup_old_data($retention_days);
        
        wp_send_json_success([
            'message' => sprintf(
                __('Successfully cleaned up %d monitoring records older than %d days.', 'ai-product-chatbot'),
                $deleted,
                $retention_days
            )
        ]);
    }

    public function admin_license_notice() {
        if (!current_user_can('manage_options')) return;
        // Show only on our plugin admin pages
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || strpos($screen->id, 'aipc') === false) return;
        if (!class_exists('AIPC_License')) {
            require_once AIPC_PLUGIN_DIR . 'includes/class-aipc-license.php';
        }
        $license = AIPC_License::getInstance();
        $status = $license->get_grace_status();
        if (!empty($status['in_grace'])) {
            $remaining = isset($status['remaining_seconds']) ? (int)$status['remaining_seconds'] : 0;
            $hours = max(1, (int) ceil($remaining / 3600));
            echo '<div class="notice notice-warning is-dismissible"><p>'
                . esc_html(sprintf(__('Licentieserver momenteel onbereikbaar. De plugin werkt in grace-mode (ongeveer %d uur resterend).', 'ai-product-chatbot'), $hours))
                . '</p><p><button type="button" class="button button-primary aipc-force-validate-license" data-nonce="' . esc_attr(wp_create_nonce('aipc_test_api')) . '">' . esc_html__('Nu opnieuw valideren', 'ai-product-chatbot') . '</button></p></div>';
        }
    }
}

// Initialize the plugin
new AI_Product_Chatbot();

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create database tables
    AIPC_Database::create_tables();
    
    // Set default options
    add_option('aipc_version', AIPC_VERSION);
    if (!wp_next_scheduled('aipc_daily_purge')) {
        wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'aipc_daily_purge');
    }
    if (!wp_next_scheduled('aipc_daily_cleanup_monitoring')) {
        wp_schedule_event(time() + DAY_IN_SECONDS + 3600, 'daily', 'aipc_daily_cleanup_monitoring'); // 1 hour after purge
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clean up scheduled events
    $ts = wp_next_scheduled('aipc_daily_purge');
    if ($ts) {
        wp_unschedule_event($ts, 'aipc_daily_purge');
    }
    
    $ts_monitoring = wp_next_scheduled('aipc_daily_cleanup_monitoring');
    if ($ts_monitoring) {
        wp_unschedule_event($ts_monitoring, 'aipc_daily_cleanup_monitoring');
    }
});
