<?php
class AIPC_Product_Manager {
    
    public function __construct() {
        // Initialize if needed
    }
    
    public function get_all_products($limit = 50) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_products';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE status = 'active' ORDER BY name ASC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }
    
    public function get_product($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_products';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ), ARRAY_A);
    }
    
    public function search_products($keywords) {
        if (empty($keywords)) {
            return [];
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_products';
        
        $where_conditions = [];
        $where_values = [];
        
        foreach ($keywords as $keyword) {
            $where_conditions[] = "(name LIKE %s OR description LIKE %s OR ingredients LIKE %s OR skin_types LIKE %s)";
            $like_pattern = '%' . $wpdb->esc_like($keyword) . '%';
            $where_values = array_merge($where_values, [$like_pattern, $like_pattern, $like_pattern, $like_pattern]);
        }
        
        $where_clause = implode(' OR ', $where_conditions);
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE status = 'active' AND ($where_clause) ORDER BY name ASC LIMIT 10",
            $where_values
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Process results to convert JSON fields
        return array_map([$this, 'process_product'], $results);
    }
    
    public function search_ingredients($keywords) {
        if (empty($keywords)) {
            return [];
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_ingredients';
        
        $where_conditions = [];
        $where_values = [];
        
        foreach ($keywords as $keyword) {
            $where_conditions[] = "(name LIKE %s OR description LIKE %s OR benefits LIKE %s)";
            $like_pattern = '%' . $wpdb->esc_like($keyword) . '%';
            $where_values = array_merge($where_values, [$like_pattern, $like_pattern, $like_pattern]);
        }
        
        $where_clause = implode(' OR ', $where_conditions);
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE status = 'active' AND ($where_clause) ORDER BY name ASC LIMIT 10",
            $where_values
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Process results to convert JSON fields
        return array_map([$this, 'process_ingredient'], $results);
    }
    
    public function add_product($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_products';
        
        $insert_data = [
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description']),
            'ingredients' => json_encode($data['ingredients'] ?? []),
            'skin_types' => json_encode($data['skin_types'] ?? []),
            'price' => floatval($data['price'] ?? 0),
            'image_url' => esc_url_raw($data['image_url'] ?? ''),
            'product_url' => esc_url_raw($data['product_url'] ?? ''),
            'woocommerce_id' => !empty($data['woocommerce_id']) ? intval($data['woocommerce_id']) : null,
            'sku' => sanitize_text_field($data['sku'] ?? ''),
            'stock_status' => sanitize_text_field($data['stock_status'] ?? ''),
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($table_name, $insert_data);
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    public function update_product($id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_products';
        
        $update_data = [
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description']),
            'ingredients' => json_encode($data['ingredients'] ?? []),
            'skin_types' => json_encode($data['skin_types'] ?? []),
            'price' => floatval($data['price'] ?? 0),
            'image_url' => esc_url_raw($data['image_url'] ?? ''),
            'product_url' => esc_url_raw($data['product_url'] ?? ''),
            'woocommerce_id' => !empty($data['woocommerce_id']) ? intval($data['woocommerce_id']) : null,
            'sku' => sanitize_text_field($data['sku'] ?? ''),
            'stock_status' => sanitize_text_field($data['stock_status'] ?? ''),
            'updated_at' => current_time('mysql')
        ];
        
        return $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $id],
            ['%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d', '%s', '%s', '%s'],
            ['%d']
        );
    }
    
    public function delete_product($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_products';
        
        return $wpdb->update(
            $table_name,
            ['status' => 'deleted', 'updated_at' => current_time('mysql')],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );
    }
    
    public function add_ingredient($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_ingredients';
        
        $insert_data = [
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description']),
            'benefits' => json_encode($data['benefits'] ?? []),
            'skin_types' => json_encode($data['skin_types'] ?? []),
            'category' => sanitize_text_field($data['category'] ?? ''),
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($table_name, $insert_data);
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    public function update_ingredient($id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_ingredients';
        
        $update_data = [
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description']),
            'benefits' => json_encode($data['benefits'] ?? []),
            'skin_types' => json_encode($data['skin_types'] ?? []),
            'category' => sanitize_text_field($data['category'] ?? ''),
            'updated_at' => current_time('mysql')
        ];
        
        return $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $id],
            ['%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
    }
    
    public function delete_ingredient($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_ingredients';
        
        return $wpdb->update(
            $table_name,
            ['status' => 'deleted', 'updated_at' => current_time('mysql')],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );
    }
    
    private function process_product($product) {
        $product['ingredients'] = json_decode($product['ingredients'], true) ?: [];
        $product['skin_types'] = json_decode($product['skin_types'], true) ?: [];
        return $product;
    }
    
    private function process_ingredient($ingredient) {
        $ingredient['benefits'] = json_decode($ingredient['benefits'], true) ?: [];
        $ingredient['skin_types'] = json_decode($ingredient['skin_types'], true) ?: [];
        return $ingredient;
    }
    
    public function get_skin_type_recommendations($skin_type) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_products';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE status = 'active' AND skin_types LIKE %s ORDER BY name ASC",
            '%' . $wpdb->esc_like($skin_type) . '%'
        ), ARRAY_A);
        
        return array_map([$this, 'process_product'], $results);
    }
    
    public function get_ingredient_benefits($ingredient_name) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_ingredients';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE name = %s AND status = 'active'",
            $ingredient_name
        ), ARRAY_A);
        
        if ($result) {
            return $this->process_ingredient($result);
        }
        
        return null;
    }
}
