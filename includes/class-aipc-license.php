<?php
class AIPC_License {
    
    const BASIC_TIER = 'basic';
    const BUSINESS_TIER = 'business'; 
    const ENTERPRISE_TIER = 'enterprise';
    
    private static $instance = null;
    private $license_data = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_license_data();
        // Schedule daily background validation
        add_action('init', [$this, 'schedule_cron']);
        add_action('aipc_daily_license_validate', [$this, 'cron_validate']);
    }
    
    private function load_license_data() {
        $this->license_data = get_option('aipc_license_data', [
            'key' => '',
            'tier' => self::BASIC_TIER,
            'status' => 'inactive',
            'expires' => '',
            'domain' => '',
            'activated_at' => '',
            'activation_token' => '',
            'features' => [],
            'last_server_check_at' => '',
            'last_valid_at' => '',
            'last_validate_result' => ''
        ]);
        
        // Ensure all required fields exist for existing installations
        if (empty($this->license_data['last_validate_result'])) {
            $this->license_data['last_validate_result'] = '';
        }
    }
    
    public function get_current_tier() {
        return $this->license_data['tier'] ?? self::BASIC_TIER;
    }
    
    public function is_active() {
        // Consider grace and server validation
        return $this->is_license_currently_valid();
    }
    
    public function has_feature($feature) {
        // Volledig betaald model: alle functies vereisen een actieve licentie
        if (!$this->is_active()) {
            return false;
        }
        
        // Check server-provided features with mapping fallback
        if (!empty($this->license_data['features']) && is_array($this->license_data['features'])) {
            $server_features = $this->license_data['features'];
            
            // Server to client feature mapping
            $feature_mapping = [
                'custom_skin_test' => 'product_quiz',
                'business_types' => 'api_integrations', 
                'woocommerce_sync' => 'woocommerce_full',
            ];
            
            // Check direct feature match
            if (in_array($feature, $server_features, true)) {
                return true;
            }
            
            // Check mapped feature match
            if (isset($feature_mapping[$feature]) && in_array($feature_mapping[$feature], $server_features, true)) {
                return true;
            }
            
            // If server features don't match, fall back to tier-based logic below
        }
        
        $tier = $this->get_current_tier();
        switch ($feature) {
            // Basic tier features (beperkt maar nuttig)
            case 'basic_chatbot':
            case 'woocommerce_basic':
            case 'faq_basic':
            case 'ingredients_readonly':
                return true; // Alle tiers hebben deze features
            
            // Business tier features
            case 'product_quiz':
            case 'content_sources_full':
            case 'woocommerce_full':
            case 'ingredients_full':
            case 'api_integrations':
            case 'multi_language':
            case 'privacy_gdpr':
            case 'error_tracking':
                return in_array($tier, [self::BUSINESS_TIER, self::ENTERPRISE_TIER], true);
            
            // Enterprise tier features
            case 'document_upload':
            case 'advanced_analytics':
            case 'unlimited_faq':
            case 'unlimited_documents':
            case 'api_monitoring':
            case 'rate_limiting':
                return $tier === self::ENTERPRISE_TIER;
            
            default:
                return false;
        }
    }

    // Grace-period helpers
    private function now_ts() {
        return time();
    }

    private function get_grace_seconds() {
        // Default: 48 hours; filterable
        return (int) apply_filters('aipc_license_grace_seconds', 48 * 3600);
    }

    private function can_use_grace(array $data): bool {
        if (empty($data['last_valid_at'])) return false;
        $elapsed = $this->now_ts() - strtotime($data['last_valid_at']);
        if ($elapsed <= $this->get_grace_seconds()) return true;
        // Hard cap, default 7 days
        $max_grace = (int) apply_filters('aipc_license_max_consecutive_grace_seconds', 7 * 24 * 3600);
        return $elapsed <= $max_grace;
    }

    public function is_license_currently_valid(): bool {
        $d = $this->license_data ?: [];
        $not_expired = empty($d['expires']) || strtotime($d['expires']) > $this->now_ts();
        
        if (($d['status'] ?? '') === 'active' && $not_expired) {
            // Check staleness of last server check - but be more lenient to reduce server calls
            $last_check = !empty($d['last_server_check_at']) ? strtotime($d['last_server_check_at']) : 0;
            $stale_after = (int) apply_filters('aipc_license_stale_after_seconds', 24 * 3600);
            
            // If recently checked and valid, don't check again
            if ($last_check > 0 && ($this->now_ts() - $last_check) < $stale_after) {
                return true;
            }
            
            // Only do remote validation if we're in admin area to avoid frontend performance issues
            if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
                $validated = $this->try_remote_validate_update_cache();
                if ($validated['result'] === 'ok') return true;
                if ($validated['result'] === 'network_error' && $this->can_use_grace($d)) return true;
                return false;
            } else {
                // On frontend, trust cached data longer to improve performance
                return $this->can_use_grace($d);
            }
        }
        
        // For inactive licenses, only validate in admin
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            $validated = $this->try_remote_validate_update_cache();
            if ($validated['result'] === 'ok') return true;
            if ($validated['result'] === 'network_error' && $this->can_use_grace($d)) return true;
        }
        
        return false;
    }

    // Background validation via WP-Cron
    public function schedule_cron() {
        if (!wp_next_scheduled('aipc_daily_license_validate')) {
            wp_schedule_event(time() + 3600, 'daily', 'aipc_daily_license_validate');
        }
    }

    public function cron_validate() {
        $this->try_remote_validate_update_cache();
    }

    // Remote validate and refresh cache
    public function force_validate_now(): array {
        // Public wrapper to trigger immediate validation
        return $this->try_remote_validate_update_cache();
    }

    private function try_remote_validate_update_cache(): array {
        $server = $this->get_server_url();
        if (empty($server) || empty($this->license_data['key'])) {
            return ['result' => 'no_server'];
        }

        $endpoint = trailingslashit($server) . 'wp-json/aipc/v1/license/validate';
        $domain = $this->normalize_domain($this->license_data['domain'] ?: ($_SERVER['HTTP_HOST'] ?? ''));

        $resp = wp_remote_post($endpoint, [
            'timeout' => 10,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'license_key' => $this->license_data['key'],
                'domain'      => $domain,
            ]),
        ]);

        $this->license_data['last_server_check_at'] = current_time('mysql');

        if (is_wp_error($resp)) {
            $this->license_data['last_validate_result'] = 'network_error';
            update_option('aipc_license_data', $this->license_data);
            return ['result' => 'network_error'];
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code >= 400 || empty($body) || empty($body['success'])) {
            // Explicit invalid -> disable locally
            $this->license_data['status'] = 'inactive';
            $this->license_data['last_validate_result'] = 'invalid';
            update_option('aipc_license_data', $this->license_data);
            return ['result' => 'explicit_invalid'];
        }

        $data = $body['data'] ?? [];
        $valid = !empty($data['valid']);
        if ($valid) {
            $this->license_data['tier']    = $data['tier'] ?? $this->license_data['tier'];
            $this->license_data['status']  = $data['status'] ?? 'active';
            $this->license_data['expires'] = $data['expires_at'] ?? $this->license_data['expires'];
            $this->license_data['features']= $data['features'] ?? $this->license_data['features'];
            $this->license_data['last_valid_at'] = current_time('mysql');
            $this->license_data['last_validate_result'] = 'ok';
            update_option('aipc_license_data', $this->license_data);
            return ['result' => 'ok'];
        }

        $this->license_data['status'] = 'inactive';
        $this->license_data['last_validate_result'] = 'invalid';
        update_option('aipc_license_data', $this->license_data);
        return ['result' => 'explicit_invalid'];
    }

    public function get_grace_status(): array {
        $d = $this->license_data ?: [];
        $in_grace = (($d['last_validate_result'] ?? '') === 'network_error') && $this->can_use_grace($d) && (($d['status'] ?? '') === 'active');
        $remaining = 0;
        if ($in_grace && !empty($d['last_valid_at'])) {
            $elapsed = $this->now_ts() - strtotime($d['last_valid_at']);
            $remaining = max(0, $this->get_grace_seconds() - $elapsed);
        }
        return [
            'in_grace' => $in_grace,
            'remaining_seconds' => $remaining,
        ];
    }
    
    public function activate_license($license_key, $domain = null) {
        if (empty($license_key)) {
            return ['success' => false, 'message' => __('Licentie key is verplicht.', 'ai-product-chatbot')];
        }
        
        // Require server URL to prevent offline activation
        $server = $this->get_server_url();
        if (empty($server)) {
            return ['success' => false, 'message' => __('Licentieserver-URL is niet geconfigureerd.', 'ai-product-chatbot')];
        }
        
        $domain = $this->normalize_domain($domain ?: ($_SERVER['HTTP_HOST'] ?? ''));
        
        // Optional: sanity check format before remote call
        if (!$this->validate_license_key($license_key)) {
            return ['success' => false, 'message' => __('Ongeldige licentie key.', 'ai-product-chatbot')];
        }
        
        $endpoint = trailingslashit($server) . 'wp-json/aipc/v1/license/activate';
        $response = wp_remote_post($endpoint, [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'license_key' => $license_key,
                'domain' => $domain,
                'plugin_version' => defined('AIPC_VERSION') ? AIPC_VERSION : 'unknown',
                'wp_version' => get_bloginfo('version'),
            ])
        ]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => __('Kon geen verbinding maken met de licentieserver.', 'ai-product-chatbot')];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code >= 400 || empty($body) || empty($body['success'])) {
            $msg = $body['message'] ?? __('Licentieactivatie mislukt.', 'ai-product-chatbot');
            return ['success' => false, 'message' => $msg];
        }
        
        $data = $body['data'] ?? [];
        $license_data = [
            'key' => $license_key,
            'tier' => $data['tier'] ?? self::BASIC_TIER,
            'status' => $data['status'] ?? 'active',
            'expires' => $data['expires_at'] ?? '',
            'domain' => $domain,
            'activated_at' => current_time('mysql'),
            'activation_token' => $data['activation_token'] ?? '',
            'features' => $data['features'] ?? [],
            'last_server_check_at' => current_time('mysql'),
            'last_valid_at' => current_time('mysql'),
            'last_validate_result' => 'ok'
        ];
        
        update_option('aipc_license_data', $license_data);
        $this->license_data = $license_data;
        
        return ['success' => true, 'message' => ($body['message'] ?? __('Licentie succesvol geactiveerd!', 'ai-product-chatbot'))];
    }
    
    private function validate_license_key($key) {
        // Simple key format validation (sanity check only)
        // Format: AIPC-{TIER}-{RANDOM}
        if (!preg_match('/^AIPC-(BASIC|BUSINESS|ENTERPRISE)-[A-Z0-9]{8}$/', $key, $matches)) {
            return false;
        }
        return strtolower($matches[1]);
    }
    
    public function deactivate_license() {
        $server = $this->get_server_url();
        if (!empty($server) && !empty($this->license_data['key']) && !empty($this->license_data['activation_token'])) {
            $endpoint = trailingslashit($server) . 'wp-json/aipc/v1/license/deactivate';
            $domain = $this->normalize_domain($this->license_data['domain'] ?: ($_SERVER['HTTP_HOST'] ?? ''));
            $response = wp_remote_post($endpoint, [
                'timeout' => 15,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode([
                    'license_key' => $this->license_data['key'],
                    'domain' => $domain,
                    'activation_token' => $this->license_data['activation_token']
                ])
            ]);
            // Ignore server errors for local state
        }
        
        $this->license_data['status'] = 'inactive';
        update_option('aipc_license_data', $this->license_data);
        return ['success' => true, 'message' => __('Licentie gedeactiveerd.', 'ai-product-chatbot')];
    }
    
    public function get_license_info() {
        return $this->license_data;
    }
    
    private function get_server_url() {
        // Stored in options; can be filtered
        $url = get_option('aipc_license_server_url', '');
        $url = apply_filters('aipc_license_server_url', $url);
        return rtrim($url, '/');
    }
    
    private function normalize_domain($raw) {
        $raw = trim((string) $raw);
        if ($raw === '') return '';
        $with_scheme = strpos($raw, '://') === false ? 'http://' . $raw : $raw;
        $host = wp_parse_url($with_scheme, PHP_URL_HOST);
        $host = $host ?: $raw;
        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host);
        return $host;
    }
    
    public function get_tier_features($tier) {
        $features = [
            self::BASIC_TIER => [
                'Basic AI Chatbot',
                'WooCommerce Integratie', 
                'FAQ Systeem',
                'Basis Analytics',
                'Nederlandse taal',
                '1 Business Type (Skincare)'
            ],
            self::BUSINESS_TIER => [
                'Alles van Basic +',
                'Alle Business Types',
                'Custom Product Quiz',
                'Multilingual Support',
                'Advanced API Integraties',
                'Priority Support',
                'Advanced Analytics'
            ],
            self::ENTERPRISE_TIER => [
                'Alles van Business +',
                'White-label Branding',
                'Custom Business Types',
                'API Access voor Developers',
                'Dedicated Support',
                'Custom Integrations',
                'Multi-site License'
            ]
        ];
        
        return $features[$tier] ?? [];
    }
    
    public function generate_upgrade_url($target_tier) {
        $base_url = get_option('aipc_upgrade_url', 'https://your-website.com/upgrade');
        $base_url = apply_filters('aipc_upgrade_url', $base_url);
        $current_key = $this->license_data['key'] ?? '';
        
        return add_query_arg([
            'from' => $this->get_current_tier(),
            'to' => $target_tier,
            'key' => $current_key,
            'domain' => $_SERVER['HTTP_HOST'] ?? ''
        ], $base_url);
    }
}
