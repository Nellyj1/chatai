<?php
class AIPC_WooCommerce_Integration {
    
    private $product_manager;
    private $auto_sync_enabled;
    
    public function __construct() {
        $this->product_manager = new AIPC_Product_Manager();
        $this->auto_sync_enabled = get_option('aipc_woocommerce_auto_sync', true);
        
        $this->init_hooks();
        $this->init_admin_hooks();
    }
    
    private function init_hooks() {
        // WooCommerce product hooks
        add_action('woocommerce_new_product', [$this, 'sync_product_to_chatbot'], 10, 1);
        add_action('woocommerce_update_product', [$this, 'sync_product_to_chatbot'], 10, 1);
        add_action('woocommerce_delete_product', [$this, 'remove_product_from_chatbot'], 10, 1);
        
        // Product status changes
        add_action('woocommerce_product_status_changed', [$this, 'handle_product_status_change'], 10, 3);
        
        // Stock changes
        add_action('woocommerce_product_set_stock_status', [$this, 'update_product_stock'], 10, 1);
        
        // Price changes
        add_action('woocommerce_product_object_updated_props', [$this, 'update_product_price'], 10, 2);
    }
    
    private function init_admin_hooks() {
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_woocommerce_menu']);
            add_action('wp_ajax_aipc_sync_woocommerce_products', [$this, 'ajax_sync_products']);
            add_action('wp_ajax_aipc_clear_woocommerce_sync', [$this, 'ajax_clear_sync']);
            add_action('wp_ajax_aipc_cleanup_duplicates', [$this, 'ajax_cleanup_duplicates']);
            add_action('wp_ajax_aipc_sync_single_product', [$this, 'ajax_sync_single_product']);
            add_action('wp_ajax_aipc_unsync_single_product', [$this, 'ajax_unsync_single_product']);
        }
    }
    
    public function add_woocommerce_menu() {
        // Only show WooCommerce menu if has WooCommerce sync feature
        $has_woocommerce_feature = false;
        if (class_exists('AIPC_License')) {
            $license = AIPC_License::getInstance();
            $is_active = $license->is_active();
            $has_feature = $license->has_feature('woocommerce_full');
            $has_woocommerce_feature = $is_active && $has_feature;
            
        }
        
        if ($has_woocommerce_feature) {
            add_submenu_page(
                'aipc-dashboard',
                __('WooCommerce Sync', 'ai-product-chatbot'),
                __('WooCommerce Sync', 'ai-product-chatbot'),
                'manage_options',
                'aipc-woocommerce',
                [$this, 'admin_woocommerce_page']
            );
        }
    }
    
    public function sync_product_to_chatbot($product_id) {
        if (!$this->auto_sync_enabled) {
            return;
        }
        
        // Check product limits for Basic tier
        if (!$this->can_sync_product()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIPC: Product sync blocked due to Basic tier limit (50 products)');
            }
            return;
        }
        
        $this->manual_sync_product($product_id);
    }
    
    private function can_sync_product() {
        // Check license restrictions
        if (class_exists('AIPC_License')) {
            $license = AIPC_License::getInstance();
            
            if (!$license->is_active()) {
                // No license: no WooCommerce sync
                return false;
            }
            
            $current_tier = $license->get_current_tier();
            
            if ($current_tier === 'basic') {
                // Basic tier: 50 product limit
                global $wpdb;
                $table = $wpdb->prefix . 'aipc_products';
                $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE woocommerce_id IS NOT NULL");
                return $count < 50;
            }
            
            // Business+ tier: unlimited
            return true;
        }
        
        // No license class: no sync
        return false;
    }
    
    public function manual_sync_product($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        // Skip if product is not published
        if ($product->get_status() !== 'publish') {
            $this->remove_product_from_chatbot($product_id);
            return false;
        }
        
        $product_data = $this->prepare_product_data($product);
        
        // Check if product already exists in chatbot
        $existing_product = $this->get_chatbot_product_by_woo_id($product_id);
        
        if ($existing_product) {
            // Update existing product
            $result = $this->product_manager->update_product($existing_product['id'], $product_data);
        } else {
            // Add new product
            $product_data['woocommerce_id'] = $product_id;
            $result = $this->product_manager->add_product($product_data);
        }
        
        // Invalidate cache after sync
        $this->invalidate_cache();
        
        return true;
    }
    
    public function remove_product_from_chatbot($product_id) {
        $existing_product = $this->get_chatbot_product_by_woo_id($product_id);
        if ($existing_product) {
            $this->product_manager->delete_product($existing_product['id']);
            // Invalidate cache after removal
            $this->invalidate_cache();
        }
    }
    
    public function handle_product_status_change($product_id, $old_status, $new_status) {
        if ($new_status === 'publish') {
            $this->sync_product_to_chatbot($product_id);
        } else {
            $this->remove_product_from_chatbot($product_id);
        }
    }
    
    public function update_product_stock($product) {
        if ($this->auto_sync_enabled) {
            $this->sync_product_to_chatbot($product->get_id());
        }
    }
    
    public function update_product_price($product, $updated_props) {
        if ($this->auto_sync_enabled && in_array('price', $updated_props)) {
            $this->sync_product_to_chatbot($product->get_id());
        }
    }
    
    private function prepare_product_data($product) {
        $ingredients_field = get_option('aipc_woocommerce_ingredients_field', '_ingredients');
        $skin_types_field = get_option('aipc_woocommerce_skin_types_field', '_skin_types');
        
        // Get ingredients from custom field or product tags
        $ingredients = get_post_meta($product->get_id(), $ingredients_field, true);
        if (empty($ingredients)) {
            $ingredients = wp_get_post_terms($product->get_id(), 'product_tag', ['fields' => 'names']);
        }
        if (is_string($ingredients)) {
            $ingredients = array_map('trim', explode(',', $ingredients));
        }
        
        // Get skin types from custom field or product categories
        $skin_types = get_post_meta($product->get_id(), $skin_types_field, true);
        if (empty($skin_types)) {
            $skin_types = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
        }
        if (is_string($skin_types)) {
            $skin_types = array_map('trim', explode(',', $skin_types));
        }
        
        return [
            'name' => $product->get_name(),
            'description' => $product->get_description() ?: $product->get_short_description(),
            'ingredients' => $ingredients ?: [],
            'skin_types' => $skin_types ?: [],
            'price' => $product->get_price(),
            'image_url' => wp_get_attachment_url($product->get_image_id()),
            'product_url' => get_permalink($product->get_id()),
            'woocommerce_id' => $product->get_id(),
            'stock_status' => $product->get_stock_status(),
            'sku' => $product->get_sku()
        ];
    }
    
    private function get_chatbot_product_by_woo_id($woo_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_products';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE woocommerce_id = %d",
            $woo_id
        ), ARRAY_A);
    }
    
    private function cleanup_duplicate_products() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_products';
        
        // Debug: Show all products in database (limited for performance) - only in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $all_products = $wpdb->get_results("SELECT id, name, woocommerce_id FROM $table_name ORDER BY id LIMIT 100");
            error_log('AIPC: All products before cleanup: ' . print_r($all_products, true));
        }
        
        // Define the manually created products that should be preserved
        $manual_products = [
            'Anti-Aging Nachtcrème',
            'Gezichtsreiniger voor Gevoelige Huid', 
            'Hydraterende Dagcrème'
        ];
        
        // Find products with NULL woocommerce_id that have the same name as products with real woocommerce_id
        $duplicates = $wpdb->get_results("
            SELECT n.name, COUNT(*) as count
            FROM $table_name n
            WHERE n.woocommerce_id IS NULL 
            AND EXISTS (
                SELECT 1 FROM $table_name w 
                WHERE w.name = n.name 
                AND w.woocommerce_id IS NOT NULL
            )
            GROUP BY n.name
        ");
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIPC: Found duplicates: ' . print_r($duplicates, true));
        }
        
        $deleted_count = 0;
        foreach ($duplicates as $duplicate) {
            // Skip manual products - only clean up WooCommerce duplicates
            if (in_array($duplicate->name, $manual_products)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AIPC: Skipping manual product: ' . $duplicate->name);
                }
                continue;
            }
            
            $products_to_delete = $wpdb->get_results($wpdb->prepare("
                SELECT id FROM $table_name 
                WHERE name = %s AND woocommerce_id IS NULL 
                ORDER BY id ASC
            ", $duplicate->name));
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIPC: Products to delete for ' . $duplicate->name . ': ' . print_r($products_to_delete, true));
            }
            
            // Delete all NULL woocommerce_id versions since we have a real WooCommerce version
            foreach ($products_to_delete as $product_to_delete) {
                $result = $wpdb->delete($table_name, ['id' => $product_to_delete->id]);
                if ($result) {
                    $deleted_count++;
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('AIPC: Deleted duplicate product: ' . $duplicate->name . ' (ID: ' . $product_to_delete->id . ')');
                    }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('AIPC: Failed to delete product ID: ' . $product_to_delete->id);
                    }
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIPC: Cleanup completed - removed ' . $deleted_count . ' duplicate products (preserved manual products)');
        }
    }
    
    public function ajax_cleanup_duplicates() {
        check_ajax_referer('aipc_woocommerce_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $this->cleanup_duplicate_products();
        
        $status = $this->get_sync_status();
        
        // Restore default products if they were accidentally removed
        $this->restore_default_products();
        
        wp_send_json_success([
            'message' => 'Duplicaten opgeruimd en standaard producten hersteld!',
            'status' => $status
        ]);
    }
    
    private function restore_default_products() {
        $default_products = [
            [
                'name' => 'Anti-Aging Nachtcrème',
                'description' => 'Rijke nachtcrème met retinol en hyaluronzuur voor een stralende huid.',
                'ingredients' => ['retinol', 'hyaluronzuur', 'vitamine e', 'ceramiden'],
                'skin_types' => ['rijpe huid', 'droge huid'],
                'price' => 45.99,
                'image_url' => '',
                'product_url' => '',
                'woocommerce_id' => null,
                'sku' => '',
                'stock_status' => 'instock'
            ],
            [
                'name' => 'Gezichtsreiniger voor Gevoelige Huid',
                'description' => 'Milde reiniger die de huid reinigt zonder uit te drogen.',
                'ingredients' => ['glycerine', 'aloe vera', 'chamomile extract'],
                'skin_types' => ['gevoelige huid', 'droge huid'],
                'price' => 18.50,
                'image_url' => '',
                'product_url' => '',
                'woocommerce_id' => null,
                'sku' => '',
                'stock_status' => 'instock'
            ],
            [
                'name' => 'Hydraterende Dagcrème',
                'description' => 'Lichte dagcrème met SPF 30 voor dagelijkse bescherming.',
                'ingredients' => ['hyaluronzuur', 'vitamine c', 'niacinamide', 'zink oxide'],
                'skin_types' => ['alle huidtypes'],
                'price' => 32.00,
                'image_url' => '',
                'product_url' => '',
                'woocommerce_id' => null,
                'sku' => '',
                'stock_status' => 'instock'
            ]
        ];
        
        foreach ($default_products as $product_data) {
            // Check if product already exists
            $existing = $this->product_manager->search_products([$product_data['name']]);
            if (empty($existing)) {
                $this->product_manager->add_product($product_data);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AIPC: Restored default product: ' . $product_data['name']);
                }
            }
        }
    }
    
    public function admin_woocommerce_page() {
        $sync_status = $this->get_sync_status();
        $woo_products = $this->get_woocommerce_products();
        $synced_products = $this->get_synced_products();
        
        include AIPC_PLUGIN_DIR . 'admin/woocommerce.php';
    }
    
    public function get_sync_status() {
        global $wpdb;
        
        // Cache sync status for 5 minutes to reduce DB queries
        $cache_key = 'aipc_sync_status';
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        $total_woo_products = wp_count_posts('product')->publish;
        $synced_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aipc_products WHERE woocommerce_id IS NOT NULL");
        $legacy_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aipc_products WHERE woocommerce_id IS NULL");
        $total_chatbot_products = $synced_count + $legacy_count;
        
        // Only log in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIPC: Sync status - Total WooCommerce products: ' . $total_woo_products . ', Synced: ' . $synced_count . ', Legacy: ' . $legacy_count);
        }
        
        $result = [
            'total_woo_products' => $total_woo_products,
            'synced_products' => $synced_count,
            'legacy_products' => $legacy_count,
            'total_chatbot_products' => $total_chatbot_products,
            'sync_percentage' => $total_woo_products > 0 ? round(($synced_count / $total_woo_products) * 100, 1) : 0
        ];
        
        // Cache for 5 minutes
        set_transient($cache_key, $result, 300);
        
        return $result;
    }
    
    public function get_woocommerce_products($limit = null) {
        // Cache products list for 2 minutes to reduce load
        $cache_key = 'aipc_woo_products_' . ($limit ?? 'auto');
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // If no limit specified, use a smart default based on total products
        if ($limit === null) {
            $total_products = wp_count_posts('product')->publish;
            if ($total_products <= 50) {
                $limit = -1; // Show all if 50 or fewer
            } elseif ($total_products <= 200) {
                $limit = 100; // Show 100 if between 51-200
            } else {
                $limit = 200; // Cap at 200 for performance
            }
        }
        
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit
        ];
        
        $products = get_posts($args);
        $result = [];
        
        // Only log in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIPC: Found ' . count($products) . ' WooCommerce products for display');
        }
        
        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            if ($product) {
                $result[] = [
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'price' => $product->get_price(),
                    'stock_status' => $product->get_stock_status(),
                    'is_synced' => $this->get_chatbot_product_by_woo_id($product->get_id()) !== null
                ];
            }
        }
        
        // Only log in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIPC: Returning ' . count($result) . ' WooCommerce products for display');
        }
        
        // Cache for 2 minutes
        set_transient($cache_key, $result, 120);
        
        return $result;
    }
    
    /**
     * Clear all WooCommerce related cache
     */
    private function invalidate_cache() {
        // Clear sync status cache
        delete_transient('aipc_sync_status');
        
        // Clear products cache for all possible limits
        delete_transient('aipc_woo_products_auto');
        delete_transient('aipc_woo_products_');
        delete_transient('aipc_woo_products_50');
        delete_transient('aipc_woo_products_100');
        delete_transient('aipc_woo_products_200');
        delete_transient('aipc_woo_products_-1');
        
        // Clear any other product-related caches that might exist
        $cache_keys = [
            'aipc_woo_products_10',
            'aipc_woo_products_25'
        ];
        
        foreach ($cache_keys as $key) {
            delete_transient($key);
        }
    }
    
    public function get_synced_products($limit = 10) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_products';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE woocommerce_id IS NOT NULL ORDER BY updated_at DESC LIMIT %d",
            $limit
        ), ARRAY_A);
    }
    
    public function ajax_sync_products() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIPC: AJAX sync_products called');
        }
        check_ajax_referer('aipc_woocommerce_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        // Clean up duplicate products first
        $this->cleanup_duplicate_products();
        
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1
        ];
        
        $products = get_posts($args);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIPC: Found ' . count($products) . ' WooCommerce products');
            
            // Debug: List all products
            foreach ($products as $post) {
                error_log('AIPC: Product found: ' . $post->post_title . ' (ID: ' . $post->ID . ', Status: ' . $post->post_status . ')');
            }
        }
        
        $synced_count = 0;
        $skipped_count = 0;
        
        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            if ($product) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('AIPC: Processing product: ' . $product->get_name() . ' (ID: ' . $product->get_id() . ')');
                }
                $this->sync_product_to_chatbot($product->get_id());
                $synced_count++;
            } else {
                $skipped_count++;
            }
        }
        
        // Invalidate cache after bulk sync
        $this->invalidate_cache();
        
        wp_send_json_success([
            'message' => sprintf(
                __('Sync voltooid! %d producten gesynchroniseerd, %d overgeslagen.', 'ai-product-chatbot'),
                $synced_count,
                $skipped_count
            ),
            'synced_count' => $synced_count,
            'skipped_count' => $skipped_count
        ]);
    }
    
    public function ajax_clear_sync() {
        check_ajax_referer('aipc_woocommerce_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_products';
        
        $deleted_count = $wpdb->query("DELETE FROM $table_name WHERE woocommerce_id IS NOT NULL");
        
        // Invalidate cache after bulk clear
        $this->invalidate_cache();
        
        wp_send_json_success([
            'message' => sprintf(__('%d WooCommerce producten verwijderd uit chatbot.', 'ai-product-chatbot'), $deleted_count),
            'deleted_count' => $deleted_count
        ]);
    }
    
    public function get_woocommerce_product_context($message) {
        if (!class_exists('WooCommerce')) {
            return '';
        }
        
        $context = '';
        
        // Search WooCommerce products
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            's' => $message,
            'meta_query' => [
                [
                    'key' => '_visibility',
                    'value' => ['visible', 'catalog'],
                    'compare' => 'IN'
                ]
            ]
        ];
        
        $products = get_posts($args);
        
        if (!empty($products)) {
            $context .= "WOOCOMMERCE PRODUCTEN:\n";
            foreach ($products as $post) {
                $product = wc_get_product($post->ID);
                if ($product) {
                    $context .= "- " . $product->get_name() . " (€" . $product->get_price() . ")\n";
                    $context .= "  " . $product->get_short_description() . "\n";
                    $context .= "  URL: " . get_permalink($product->get_id()) . "\n";
                    $context .= "  Voorraad: " . $product->get_stock_status() . "\n\n";
                }
            }
        }
        
        return $context;
    }
    
    public function ajax_sync_single_product() {
        check_ajax_referer('aipc_woocommerce_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $product_id = intval($_POST['product_id']);
        if (!$product_id) {
            wp_send_json_error(['message' => 'Invalid product ID']);
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(['message' => 'Product niet gevonden']);
        }
        
        // Check if already synced
        $existing_product = $this->get_chatbot_product_by_woo_id($product_id);
        if ($existing_product) {
            wp_send_json_error(['message' => 'Product is al gesynchroniseerd']);
        }
        
        // Sync the product manually (bypass auto-sync check)
        $this->manual_sync_product($product_id);
        
        wp_send_json_success([
            'message' => sprintf(__('Product "%s" succesvol gesynchroniseerd!', 'ai-product-chatbot'), $product->get_name())
        ]);
    }
    
    public function ajax_unsync_single_product() {
        check_ajax_referer('aipc_woocommerce_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $product_id = intval($_POST['product_id']);
        if (!$product_id) {
            wp_send_json_error(['message' => 'Invalid product ID']);
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(['message' => 'Product niet gevonden']);
        }
        
        // Check if synced
        $existing_product = $this->get_chatbot_product_by_woo_id($product_id);
        if (!$existing_product) {
            wp_send_json_error(['message' => 'Product is niet gesynchroniseerd']);
        }
        
        // Remove the product
        $this->remove_product_from_chatbot($product_id);
        
        wp_send_json_success([
            'message' => sprintf(__('Product "%s" verwijderd uit chatbot!', 'ai-product-chatbot'), $product->get_name())
        ]);
    }
}
