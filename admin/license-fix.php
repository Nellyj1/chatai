<?php
if (!defined('ABSPATH')) {
    exit;
}

// Handle database repair request
if (isset($_POST['repair_database']) && wp_verify_nonce($_POST['aipc_debug_nonce'], 'aipc_debug_action')) {
    // Ensure database tables exist
    if (class_exists('AIPC_Database')) {
        AIPC_Database::create_tables();
        echo '<div class="notice notice-success"><p><strong>✅ Database tabellen gerepareerd!</strong> Ontbrekende tabellen zijn toegevoegd.</p></div>';
    } else {
        echo '<div class="notice notice-warning"><p><strong>⚠️ Database warning:</strong> AIPC_Database class niet gevonden. Tabellen kunnen niet worden aangemaakt.</p></div>';
    }
}

// Load current license status
$current_license = get_option('aipc_license_data', []);
$license = class_exists('AIPC_License') ? AIPC_License::getInstance() : null;
?>

<div class="wrap">
    <h1>🔧 Debug Tool</h1>
    
    <div class="notice notice-info">
        <p><strong>Deze tool toont de huidige status voor troubleshooting:</strong></p>
        <ul>
            <li>📊 Licentie en feature status</li>
            <li>🗄️ Database tabellen status</li>
            <li>⚙️ Configuratie instellingen</li>
            <li>🔧 Database reparatie tools</li>
        </ul>
        <p><em>Alleen zichtbaar als WP_DEBUG ingeschakeld is.</em></p>
    </div>
    
    <h2>Huidige Licentie Status</h2>
    <table class="widefat">
        <tr>
            <th>Key:</th>
            <td><?php echo esc_html($current_license['key'] ?? 'Geen'); ?></td>
        </tr>
        <tr>
            <th>Tier:</th>
            <td><?php echo esc_html($current_license['tier'] ?? 'Geen'); ?></td>
        </tr>
        <tr>
            <th>Status:</th>
            <td>
                <?php 
                $status = $current_license['status'] ?? 'Geen';
                $color = $status === 'active' ? 'green' : 'red';
                echo '<span style="color: ' . $color . ';">' . esc_html($status) . '</span>';
                ?>
            </td>
        </tr>
        <tr>
            <th>Features:</th>
            <td>
                <?php 
                if (!empty($current_license['features']) && is_array($current_license['features'])) {
                    echo esc_html(implode(', ', $current_license['features']));
                } else {
                    echo 'Geen features';
                }
                ?>
            </td>
        </tr>
        <tr>
            <th>WordPress Check:</th>
            <td>
                <?php 
                if ($license) {
                    echo 'Actief: ' . ($license->is_active() ? '<span style="color: green;">✅ JA</span>' : '<span style="color: red;">❌ NEE</span>');
                    echo '<br>Tier: ' . esc_html($license->get_current_tier());
                } else {
                    echo '<span style="color: red;">Licentie klasse niet geladen</span>';
                }
                ?>
            </td>
        </tr>
    </table>
    
    <h2>Test Features</h2>
    <?php if ($license && $license->is_active()): ?>
        <p style="color: green;"><strong>✅ Licentie is actief! Test de volgende features:</strong></p>
        <ul>
            <li>Basic Chatbot: <?php echo $license->has_feature('basic_chatbot') ? '✅' : '❌'; ?></li>
            <li>Ingredients Full: <?php echo $license->has_feature('ingredients_full') ? '✅' : '❌'; ?></li>
            <li>API Integrations: <?php echo $license->has_feature('api_integrations') ? '✅' : '❌'; ?></li>
            <li>Product Quiz: <?php echo $license->has_feature('product_quiz') ? '✅' : '❌'; ?></li>
            <li>WooCommerce Full: <?php echo $license->has_feature('woocommerce_full') ? '✅' : '❌'; ?></li>
        </ul>
    <?php else: ?>
        <p style="color: red;"><strong>❌ Licentie is niet actief - dit veroorzaakt de problemen!</strong></p>
    <?php endif; ?>
    
    <h2>Database Status</h2>
    <?php
    global $wpdb;
    $missing_tables = [];
    $required_tables = [
        'aipc_products' => 'Producten database',
        'aipc_ingredients' => 'Ingrediënten database', 
        'aipc_conversations' => 'Gesprekken historie',
        'aipc_faq' => 'FAQ items',
        'aipc_api_monitoring' => 'API monitoring',
        'aipc_analytics' => 'Analytics data',
        'aipc_documents' => 'Documenten',
        'aipc_token_usage' => 'Token gebruik'
    ];
    
    foreach ($required_tables as $table => $description) {
        $full_table = $wpdb->prefix . $table;
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table;
        if (!$exists) {
            $missing_tables[] = $description . " ($table)";
        }
    }
    ?>
    
    <?php if (!empty($missing_tables)): ?>
    <div class="notice notice-warning">
        <p><strong>⚠️ Ontbrekende Database Tabellen:</strong></p>
        <ul style="margin-left: 20px;">
            <?php foreach ($missing_tables as $missing): ?>
                <li><?php echo esc_html($missing); ?></li>
            <?php endforeach; ?>
        </ul>
        <p>Dit veroorzaakt de database errors die je ziet. Fix dit hieronder.</p>
    </div>
    <?php else: ?>
    <div class="notice notice-success">
        <p><strong>✅ Alle database tabellen bestaan!</strong></p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($missing_tables)): ?>
    <h2>Database Repareren</h2>
    <form method="post" action="">
        <?php wp_nonce_field('aipc_debug_action', 'aipc_debug_nonce'); ?>
        <p>Er ontbreken database tabellen. Klik hieronder om ze aan te maken:</p>
        <p class="submit">
            <input type="submit" name="repair_database" class="button button-primary" value="🔧 Repareer Database Tabellen" 
                   onclick="return confirm('Database tabellen aanmaken. Doorgaan?');" />
        </p>
    </form>
    <?php endif; ?>
    
    <h2>Configuratie Status</h2>
    <div class="notice notice-info">
        <p><strong>Huidige configuratie:</strong></p>
        <?php 
        $api_key = get_option('aipc_openai_api_key', '');
        $provider = get_option('aipc_api_provider', 'openai');
        $model = get_option('aipc_openai_model', 'gpt-4');
        ?>
        <p><strong>API Key:</strong> <?php echo !empty($api_key) ? '✅ Ingesteld (' . substr($api_key, 0, 8) . '...)' : '❌ Niet ingesteld'; ?></p>
        <p><strong>Provider:</strong> <?php echo esc_html($provider); ?></p>
        <p><strong>Model:</strong> <?php echo esc_html($model); ?></p>
        
        <?php if ($provider === 'openrouter' && !empty($api_key)): ?>
        <p><strong>OpenRouter Model Status:</strong> 
            <?php if (empty($model)): ?>
                ❌ Geen model geselecteerd
            <?php elseif (strpos($model, ':free') !== false): ?>
                🆓 Gratis model - geen kosten
            <?php else: ?>
                💰 Betaald model - kosten per gebruik
            <?php endif; ?>
        </p>
        
        <?php if (empty($model) || strpos($model, 'microsoft/phi-3-mini-128k-instruct:free') !== false): ?>
        <div class="notice notice-warning" style="margin: 10px 0;">
            <p><strong>⚠️ Model Probleem Gedetecteerd!</strong></p>
            <p>Het huidige model werkt niet of is niet geselecteerd. <strong>Stappen om dit op te lossen:</strong></p>
            <ol style="margin-left: 20px;">
                <li>Ga naar <strong>AI Chatbot > Instellingen</strong></li>
                <li>Bij <strong>API Provider</strong> kies <strong>OpenRouter</strong></li>
                <li>Wacht tot alle modellen worden geladen</li>
                <li>Kies een <strong>🆓 GRATIS model</strong> uit de dropdown</li>
                <li><strong>Sla instellingen op</strong></li>
                <li>Kom terug naar deze pagina en test opnieuw</li>
            </ol>
            <p>
                <a href="<?php echo admin_url('admin.php?page=aipc-settings'); ?>" class="button button-secondary">
                    ⚙️ Ga naar Instellingen
                </a>
            </p>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <h4>🔧 Direct Chatbot Test</h4>
        <p>Test de chatbot direct hier om te zien wat er misgaat:</p>
        <div style="border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 4px;">
            <input type="text" id="test-message" placeholder="Typ een testbericht..." style="width: 70%; padding: 8px;" />
            <button type="button" id="test-chatbot" class="button button-secondary">Test Chatbot</button>
        </div>
        <div id="test-result" style="margin: 10px 0; padding: 10px; border-radius: 4px; display: none;"></div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#test-chatbot').on('click', function() {
            var message = $('#test-message').val().trim();
            var button = $(this);
            var result = $('#test-result');
            
            if (!message) {
                alert('Voer een testbericht in.');
                return;
            }
            
            button.prop('disabled', true).text('Testen...');
            result.show().html('<div style="background: #f0f0f0; padding: 10px;">⏳ Chatbot test bezig...</div>');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'aipc_send_message',
                    message: message,
                    conversation_id: 'test-' + Date.now(),
                    nonce: '<?php echo wp_create_nonce('aipc_nonce'); ?>'
                },
                success: function(response) {
                    console.log('Chatbot test response:', response);
                    if (response.success && response.data) {
                        result.html('<div style="background: #d4edda; color: #155724; padding: 10px; border: 1px solid #c3e6cb;"><strong>✅ Chatbot werkt!</strong><br><strong>Antwoord:</strong> ' + response.data.message + '</div>');
                    } else {
                        var errorMsg = response.data ? response.data.message : 'Onbekende fout';
                        result.html('<div style="background: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb;"><strong>❌ Chatbot fout:</strong><br>' + errorMsg + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', xhr.responseText, status, error);
                    result.html('<div style="background: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb;"><strong>❌ AJAX Fout:</strong><br>' + status + ' - ' + error + '<br><strong>Response:</strong> ' + xhr.responseText + '</div>');
                },
                complete: function() {
                    button.prop('disabled', false).text('Test Chatbot');
                }
            });
        });
        
        // Allow Enter key to test
        $('#test-message').on('keypress', function(e) {
            if (e.which == 13) {
                $('#test-chatbot').click();
            }
        });
    });
    </script>
    
    <h2>Na het Fixen</h2>
    <div class="notice notice-warning">
        <p><strong>Na het klikken op 'Fix Licentie Nu':</strong></p>
        <ol>
            <li>Herlaad deze pagina om de nieuwe status te zien</li>
            <li>Ga naar <strong>AI Chatbot > Instellingen</strong> - zou nu alle Business features moeten tonen</li>
            <li>Voer een API key in als je volledige AI antwoorden wilt</li>
            <li>Test de chatbot - zou nu moeten werken (FAQ/WooCommerce ook zonder API key)</li>
            <li>Ga naar <strong>AI Chatbot > Kenmerken</strong> - zou nu volledig bewerkbaar moeten zijn</li>
            <li>Ga naar <strong>AI Chatbot > WooCommerce Sync</strong> - zou nu toegankelijk moeten zijn</li>
        </ol>
    </div>
</div>

<style>
.widefat th {
    width: 200px;
    font-weight: bold;
    background: #f1f1f1;
    padding: 10px;
}
.widefat td {
    padding: 10px;
}
</style>