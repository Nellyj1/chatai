<?php
class AIPC_Database {
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Products table
        $table_products = $wpdb->prefix . 'aipc_products';
        $sql_products = "CREATE TABLE $table_products (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            ingredients longtext,
            skin_types longtext,
            price decimal(10,2) DEFAULT 0.00,
            image_url varchar(500),
            product_url varchar(500),
            woocommerce_id int(11) DEFAULT NULL,
            stock_status varchar(20) DEFAULT 'instock',
            sku varchar(100) DEFAULT NULL,
            status enum('active','inactive','deleted') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name),
            KEY status (status),
            KEY woocommerce_id (woocommerce_id),
            KEY sku (sku)
        ) $charset_collate;";
        
        // Ingredients table
        $table_ingredients = $wpdb->prefix . 'aipc_ingredients';
        $sql_ingredients = "CREATE TABLE $table_ingredients (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            benefits longtext,
            skin_types longtext,
            category varchar(100),
            status enum('active','inactive','deleted') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name),
            KEY status (status),
            KEY category (category)
        ) $charset_collate;";
        
        // Conversations table
        $table_conversations = $wpdb->prefix . 'aipc_conversations';
        $sql_conversations = "CREATE TABLE $table_conversations (
            id int(11) NOT NULL AUTO_INCREMENT,
            conversation_id varchar(36) NOT NULL,
            role enum('user','assistant') NOT NULL,
            content longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Chat analytics table
        $table_analytics = $wpdb->prefix . 'aipc_analytics';
        $sql_analytics = "CREATE TABLE $table_analytics (
            id int(11) NOT NULL AUTO_INCREMENT,
            conversation_id varchar(36) NOT NULL,
            message_count int(11) DEFAULT 1,
            session_duration int(11) DEFAULT 0,
            user_satisfaction enum('positive','neutral','negative') DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Documents table
        $table_documents = $wpdb->prefix . 'aipc_documents';
        $sql_documents = "CREATE TABLE $table_documents (
            id int(11) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            filename varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_size int(11) DEFAULT 0,
            file_type varchar(10) NOT NULL,
            content longtext,
            status enum('active','inactive','deleted') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY title (title),
            KEY status (status),
            KEY file_type (file_type)
        ) $charset_collate;";
        
        // Token usage table
        $table_tokens = $wpdb->prefix . 'aipc_token_usage';
        $sql_tokens = "CREATE TABLE $table_tokens (
            id int(11) NOT NULL AUTO_INCREMENT,
            conversation_id varchar(36) DEFAULT NULL,
            provider varchar(32) DEFAULT NULL,
            model varchar(100) DEFAULT NULL,
            prompt_tokens int(11) DEFAULT 0,
            completion_tokens int(11) DEFAULT 0,
            total_tokens int(11) DEFAULT 0,
            input_rate_per_1k decimal(10,4) DEFAULT 0.0000,
            output_rate_per_1k decimal(10,4) DEFAULT 0.0000,
            cost_estimate decimal(12,6) DEFAULT 0.000000,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY created_at (created_at),
            KEY model (model)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_products);
        dbDelta($sql_ingredients);
        dbDelta($sql_conversations);
        dbDelta($sql_analytics);
        dbDelta($sql_documents);
        dbDelta($sql_tokens);

        // FAQ table
        $table_faq = $wpdb->prefix . 'aipc_faq';
        $sql_faq = "CREATE TABLE $table_faq (
            id int(11) NOT NULL AUTO_INCREMENT,
            question varchar(255) NOT NULL,
            answer text NOT NULL,
            category varchar(100) DEFAULT NULL,
            status enum('active','inactive','deleted') DEFAULT 'active',
            lang varchar(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY category (category),
            KEY lang (lang)
        ) $charset_collate;";

        dbDelta($sql_faq);
        
        // API monitoring table
        $table_api_monitoring = $wpdb->prefix . 'aipc_api_monitoring';
        $sql_api_monitoring = "CREATE TABLE $table_api_monitoring (
            id int(11) NOT NULL AUTO_INCREMENT,
            conversation_id varchar(36) DEFAULT NULL,
            user_ip varchar(45) DEFAULT NULL,
            provider varchar(32) DEFAULT NULL,
            model varchar(100) DEFAULT NULL,
            request_type enum('chat','test','retry') DEFAULT 'chat',
            status enum('success','rate_limit','error','timeout','quota_exceeded') NOT NULL,
            http_status int(11) DEFAULT NULL,
            error_code varchar(50) DEFAULT NULL,
            error_message text DEFAULT NULL,
            response_time_ms int(11) DEFAULT NULL,
            prompt_tokens int(11) DEFAULT 0,
            completion_tokens int(11) DEFAULT 0,
            total_tokens int(11) DEFAULT 0,
            cost_estimate decimal(12,6) DEFAULT 0.000000,
            retry_attempt int(11) DEFAULT 0,
            user_agent varchar(500) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY user_ip (user_ip),
            KEY provider_model (provider, model),
            KEY status (status),
            KEY created_at (created_at),
            KEY request_type (request_type)
        ) $charset_collate;";
        
        dbDelta($sql_api_monitoring);
        
        // Insert sample data
        self::insert_sample_data();
    }

    public static function migrate() {
        global $wpdb;
        $faq = $wpdb->prefix . 'aipc_faq';
        // Ensure table exists before migrating
        $exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $faq)) === $faq);
        if (!$exists) {
            return;
        }
        // Add missing 'lang' column if not present
        $has_lang = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $faq LIKE %s", 'lang'));
        if (!$has_lang) {
            $prev = $wpdb->suppress_errors(true);
            $wpdb->query("ALTER TABLE $faq ADD COLUMN lang varchar(20) DEFAULT NULL AFTER status");
            // Add index as well (ignore failure if exists)
            $wpdb->query("ALTER TABLE $faq ADD INDEX lang (lang)");
            $wpdb->suppress_errors($prev);
        }
    }
    
    private static function insert_sample_data() {
        global $wpdb;
        
        $products_table = $wpdb->prefix . 'aipc_products';
        $ingredients_table = $wpdb->prefix . 'aipc_ingredients';
        
        // Check if data already exists (limit check for performance)
        $existing_products = $wpdb->get_var("SELECT COUNT(*) FROM $products_table LIMIT 1");
        if ($existing_products > 0) {
            return; // Don't insert if data already exists
        }
        
        // Sample data - universele voorbeelden die gebruikers kunnen aanpassen
        // Sample products - generieke voorbeelden
        $sample_products = [
            [
                'name' => 'Premium Product A',
                'description' => 'High-quality product with excellent features and customer satisfaction guarantee.',
                'ingredients' => json_encode(['Component 1', 'Component 2', 'Feature X', 'Benefit Y']),
                'skin_types' => json_encode(['Category A', 'Category B', 'General Use']),
                'price' => 29.99,
                'image_url' => '',
                'product_url' => ''
            ],
            [
                'name' => 'Advanced Product B',
                'description' => 'Professional-grade product designed for enhanced performance and results.',
                'ingredients' => json_encode(['Advanced Formula', 'Quality Components', 'Special Technology', 'Premium Materials']),
                'skin_types' => json_encode(['Professional Use', 'Category A', 'Specialized Applications']),
                'price' => 49.99,
                'image_url' => '',
                'product_url' => ''
            ],
            [
                'name' => 'Gentle Product C',
                'description' => 'Mild and effective product suitable for sensitive requirements and daily use.',
                'ingredients' => json_encode(['Natural Components', 'Gentle Formula', 'Safe Materials', 'Quality Assurance']),
                'skin_types' => json_encode(['Sensitive Use', 'Daily Use']),
                'price' => 19.99,
                'image_url' => '',
                'product_url' => ''
            ]
        ];
        
        foreach ($sample_products as $product) {
            $wpdb->insert($products_table, $product);
        }
        
        // Geen automatische sample kenmerken - gebruikers starten met lege database
        // Dit voorkomt verwarring en rommel in de database
    }
    
    public static function drop_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'aipc_products',
            $wpdb->prefix . 'aipc_ingredients',
            $wpdb->prefix . 'aipc_conversations',
            $wpdb->prefix . 'aipc_analytics'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
}
