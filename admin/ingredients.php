<?php
if (!defined('ABSPATH')) {
    exit;
}

$product_manager = new AIPC_Product_Manager();
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
$ingredient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// License gating: Business+ tier required voor full ingredients management
$ingredients_readonly = true; // Basic tier: read-only
$ingredients_allowed = false; // Business+ tier: full access
$show_ingredients_page = false;
$upgrade_url = '';

if (class_exists('AIPC_License')) {
    $lic = AIPC_License::getInstance();
    $current_tier = $lic->get_current_tier();
    $is_active = $lic->is_active();
    
    // Check for full access first (Business/Enterprise)
    if ($lic->has_feature('ingredients_full')) {
        $show_ingredients_page = true;
        $ingredients_readonly = false;
        $ingredients_allowed = true;
    }
    // Otherwise check for read-only access (Basic)
    elseif ($lic->has_feature('ingredients_readonly')) {
        $show_ingredients_page = true;
        $ingredients_readonly = true;
        $ingredients_allowed = false;
    }
    
    $upgrade_url = $lic->generate_upgrade_url('business');
} else {
    // Geen licentie: geen toegang tot ingrediënten pagina
    return;
}

// Handle form submissions
if (isset($_POST['submit'])) {
if (!$ingredients_allowed) {
        echo '<div class="notice notice-error"><p>' . __('Kenmerken bewerken niet toegestaan: Business+ licentie vereist voor volledig kenmerken beheer.', 'ai-product-chatbot') . '</p></div>';
    } elseif (wp_verify_nonce($_POST['aipc_ingredient_nonce'], 'aipc_save_ingredient')) {
        $ingredient_data = [
            'name' => sanitize_text_field($_POST['ingredient_name']),
            'description' => sanitize_textarea_field($_POST['ingredient_description']),
            'benefits' => array_map('sanitize_text_field', $_POST['ingredient_benefits'] ?? []),
            'skin_types' => array_map('sanitize_text_field', $_POST['ingredient_skin_types'] ?? [])
        ];
        
        if ($ingredient_id > 0) {
            $result = $product_manager->update_ingredient($ingredient_id, $ingredient_data);
            if ($result !== false) {
                echo '<div class="notice notice-success"><p>' . __('Kenmerk bijgewerkt!', 'ai-product-chatbot') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __('Fout bij bijwerken van kenmerk.', 'ai-product-chatbot') . '</p></div>';
            }
        } else {
            $result = $product_manager->add_ingredient($ingredient_data);
            if ($result) {
                echo '<div class="notice notice-success"><p>' . __('Kenmerk toegevoegd!', 'ai-product-chatbot') . '</p></div>';
                $action = 'list';
            } else {
                echo '<div class="notice notice-error"><p>' . __('Fout bij toevoegen van kenmerk.', 'ai-product-chatbot') . '</p></div>';
            }
        }
    }
}

// Handle delete action
if ($action === 'delete' && $ingredient_id > 0) {
    if (wp_verify_nonce($_GET['_wpnonce'], 'aipc_delete_ingredient_' . $ingredient_id)) {
        $result = $product_manager->delete_ingredient($ingredient_id);
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>' . __('Kenmerk verwijderd!', 'ai-product-chatbot') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __('Fout bij verwijderen van kenmerk.', 'ai-product-chatbot') . '</p></div>';
        }
        $action = 'list';
    }
}

if ($action === 'list') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'aipc_ingredients';
    $ingredients = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'active' ORDER BY name ASC", ARRAY_A);
    ?>
    <div class="wrap">
        <h1>
            <?php _e('Kenmerken', 'ai-product-chatbot'); ?>
            <?php if ($ingredients_allowed): ?>
                <a href="<?php echo admin_url('admin.php?page=aipc-ingredients&action=add'); ?>" class="page-title-action">
                    <?php _e('Nieuw Kenmerk', 'ai-product-chatbot'); ?>
                </a>
            <?php elseif ($ingredients_readonly): ?>
                <span class="page-title-action button-disabled" title="<?php _e('Business+ licentie vereist voor volledig beheer', 'ai-product-chatbot'); ?>" style="color:#666; cursor:not-allowed; text-decoration:none;">
                    <span class="dashicons dashicons-lock" style="font-size:14px;vertical-align:text-top;margin-right:3px;"></span>
                    <?php _e('Nieuw Kenmerk', 'ai-product-chatbot'); ?>
                </span>
            <?php endif; ?>
        </h1>
        
        <?php if (class_exists('AIPC_License') && $ingredients_readonly): ?>
            <div class="notice notice-info">
                <p>
                    <strong><?php _e('📖 Kenmerken Database (Read-only)', 'ai-product-chatbot'); ?></strong><br>
                    <?php _e('Met je Basic licentie kun je bestaande kenmerken bekijken en gebruiken in de chatbot. Voor volledig beheer (toevoegen, bewerken, verwijderen) heb je een Business+ licentie nodig.', 'ai-product-chatbot'); ?>
                    <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-secondary" style="margin-left:10px;">
                        <?php _e('Upgrade naar Business', 'ai-product-chatbot'); ?>
                    </a>
                </p>
            </div>
        <?php endif; ?>
        
        <?php 
        // Show explanation if no kenmerken exist yet
        if (empty($ingredients)): 
        ?>
            <div class="aipc-kenmerken-info" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h3 style="margin-top: 0;"><?php _e('Wat zijn Kenmerken?', 'ai-product-chatbot'); ?></h3>
                <p><?php _e('Kenmerken helpen de chatbot om relevante productaanbevelingen te doen. Ze bevatten eigenschappen die belangrijk zijn voor jouw producten.', 'ai-product-chatbot'); ?></p>
                
                <div class="aipc-examples">
                    <h4><?php _e('Voorbeelden van Kenmerken:', 'ai-product-chatbot'); ?></h4>
                    <p class="description"><?php _e('Kenmerken kunnen voor elke branche gebruikt worden. Hier een paar universele voorbeelden:', 'ai-product-chatbot'); ?></p>
                    <ul style="margin-left: 20px;">
                        <li><strong>Duurzaam</strong> - Milieuvriendelijk (geschikt voor: eco-bewuste klanten)</li>
                        <li><strong>Premium</strong> - Hoge kwaliteit (geschikt voor: luxe segment, cadeau)</li>
                        <li><strong>Compact</strong> - Klein formaat (geschikt voor: kleine ruimtes, reizen)</li>
                        <li><strong>Waterbestendig</strong> - Bescherming tegen vocht (geschikt voor: outdoor, sport)</li>
                        <li><strong>Hypoallergeen</strong> - Geschikt voor gevoelige gebruikers</li>
                        <li><strong>Biologisch</strong> - Natuurlijk/organisch (geschikt voor: bewuste consumenten)</li>
                    </ul>
                </div>
                
                <p style="margin-bottom: 0;"><strong><?php _e('Tip:', 'ai-product-chatbot'); ?></strong> <?php _e('De chatbot gebruikt deze kenmerken om klanten te helpen het juiste product te vinden.', 'ai-product-chatbot'); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($ingredients)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Naam', 'ai-product-chatbot'); ?></th>
                        <th><?php _e('Beschrijving', 'ai-product-chatbot'); ?></th>
                        <th><?php _e('Voordelen', 'ai-product-chatbot'); ?></th>
                        <th><?php _e('Geschikt Voor', 'ai-product-chatbot'); ?></th>
                        <th><?php _e('Acties', 'ai-product-chatbot'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ingredients as $ingredient): 
                        $benefits = json_decode($ingredient['benefits'], true) ?: [];
                        $skin_types = json_decode($ingredient['skin_types'], true) ?: [];
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($ingredient['name']); ?></strong></td>
                            <td><?php echo esc_html(wp_trim_words($ingredient['description'], 10)); ?></td>
                            <td>
                                <?php 
                                echo esc_html(implode(', ', array_slice($benefits, 0, 3)));
                                if (count($benefits) > 3) echo '...';
                                ?>
                            </td>
                            <td>
                                <?php echo esc_html(implode(', ', $skin_types)); ?>
                            </td>
                            <td>
                                <?php if ($ingredients_allowed): ?>
                                    <a href="<?php echo admin_url('admin.php?page=aipc-ingredients&action=edit&id=' . $ingredient['id']); ?>" class="button button-small">
                                        <?php _e('Bewerken', 'ai-product-chatbot'); ?>
                                    </a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=aipc-ingredients&action=delete&id=' . $ingredient['id']), 'aipc_delete_ingredient_' . $ingredient['id']); ?>" 
                                       class="button button-small button-link-delete" 
                                       onclick="return confirm('<?php _e('Weet je zeker dat je dit kenmerk wilt verwijderen?', 'ai-product-chatbot'); ?>')">
                                        <?php _e('Verwijderen', 'ai-product-chatbot'); ?>
                                    </a>
                                <?php elseif ($ingredients_readonly): ?>
                                    <span class="button button-small button-disabled" title="<?php _e('Business+ licentie vereist voor bewerken', 'ai-product-chatbot'); ?>"><?php _e('Bewerken', 'ai-product-chatbot'); ?></span>
                                    <span class="button button-small button-disabled" title="<?php _e('Business+ licentie vereist voor verwijderen', 'ai-product-chatbot'); ?>"><?php _e('Verwijderen', 'ai-product-chatbot'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('Geen kenmerken gevonden. Voeg je eerste kenmerk toe!', 'ai-product-chatbot'); ?></p>
        <?php endif; ?>
    </div>
    <?php
} elseif ($action === 'add' || $action === 'edit') {
    $ingredient = null;
    if ($action === 'edit' && $ingredient_id > 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_ingredients';
        $ingredient = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $ingredient_id), ARRAY_A);
        if (!$ingredient) {
            echo '<div class="notice notice-error"><p>' . __('Kenmerk niet gevonden.', 'ai-product-chatbot') . '</p></div>';
            $action = 'list';
        }
    }
    
    if ($action !== 'list') {
        $ingredient_name = $ingredient['name'] ?? '';
        $ingredient_description = $ingredient['description'] ?? '';
        $ingredient_benefits = json_decode($ingredient['benefits'] ?? '[]', true) ?: [];
        $ingredient_skin_types = json_decode($ingredient['skin_types'] ?? '[]', true) ?: [];
        // Removed category field - not used by chatbot functionality
        ?>
        <div class="wrap">
                        <h1><?php echo $action === 'add' ? __('Nieuw Kenmerk', 'ai-product-chatbot') : __('Kenmerk Bewerken', 'ai-product-chatbot'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('aipc_save_ingredient', 'aipc_ingredient_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ingredient_name"><?php _e('Kenmerk naam', 'ai-product-chatbot'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" id="ingredient_name" name="ingredient_name" 
                                   value="<?php echo esc_attr($ingredient_name); ?>" class="regular-text" required <?php disabled(!$ingredients_allowed); ?> />
                        </td>
                    </tr>
                    <!-- Category field removed - not used by chatbot -->
                    
                    <tr>
                        <th scope="row">
                            <label for="ingredient_description"><?php _e('Beschrijving', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <textarea id="ingredient_description" name="ingredient_description" 
                                      rows="4" cols="50" class="large-text"><?php echo esc_textarea($ingredient_description); ?></textarea>
                            <p class="description">
                                <?php _e('Uitgebreide beschrijving van het kenmerk en wat het betekent.', 'ai-product-chatbot'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Voordelen', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <div id="benefits-container">
                                <?php foreach ($ingredient_benefits as $index => $benefit): ?>
                                    <div class="benefit-row">
                                        <input type="text" name="ingredient_benefits[]" 
                                               value="<?php echo esc_attr($benefit); ?>" 
                                               class="regular-text" placeholder="<?php _e('Voordeel', 'ai-product-chatbot'); ?>" />
                                        <button type="button" class="button remove-benefit"><?php _e('Verwijderen', 'ai-product-chatbot'); ?></button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" id="add-benefit" class="button"><?php _e('Voordeel Toevoegen', 'ai-product-chatbot'); ?></button>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Geschikt voor', 'ai-product-chatbot'); ?></label>
                        </th>
                        <td>
                            <div id="skin-types-container">
                                <?php foreach ($ingredient_skin_types as $index => $skin_type): ?>
                                    <div class="skin-type-row">
                                        <input type="text" name="ingredient_skin_types[]" 
                                               value="<?php echo esc_attr($skin_type); ?>" 
                                               class="regular-text" placeholder="<?php _e('Doelgroep/type', 'ai-product-chatbot'); ?>" />
                                        <button type="button" class="button remove-skin-type"><?php _e('Verwijderen', 'ai-product-chatbot'); ?></button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" id="add-skin-type" class="button"><?php _e('Doelgroep Toevoegen', 'ai-product-chatbot'); ?></button>
                        </td>
                    </tr>
                </table>
                
                <?php if ($ingredients_allowed): ?>
                    <?php submit_button($action === 'add' ? __('Kenmerk Toevoegen', 'ai-product-chatbot') : __('Kenmerk Bijwerken', 'ai-product-chatbot')); ?>
                <?php else: ?>
                    <p class="submit">
                        <span class="button button-primary button-disabled"><?php echo $action === 'add' ? __('Kenmerk Toevoegen', 'ai-product-chatbot') : __('Kenmerk Bijwerken', 'ai-product-chatbot'); ?></span>
                    </p>
                    <div class="aipc-upgrade-notice">
                        <p><?php _e('Voor custom ingrediënten in non-skincare businesses is een Enterprise licentie vereist.', 'ai-product-chatbot'); ?></p>
                        <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-secondary"><?php _e('Upgrade nu', 'ai-product-chatbot'); ?></a>
                    </div>
                <?php endif; ?>
                <a href="<?php echo admin_url('admin.php?page=aipc-ingredients'); ?>" class="button"><?php _e('Annuleren', 'ai-product-chatbot'); ?></a>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Track if we're already processing to prevent doubles
            let isAddingBenefit = false;
            let isAddingSkinType = false;
            
            // Completely remove ALL click handlers first
            $('#add-benefit, #add-skin-type').off('click');
            
            // Add benefit with state tracking
            $('#add-benefit').on('click.aipc-ingredients', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                
                // Prevent double execution
                if (isAddingBenefit) {
                    console.log('Already adding benefit, skipping...');
                    return false;
                }
                
                isAddingBenefit = true;
                console.log('Adding ONE benefit row');
                
                // Create and add just ONE row
                const row = $('<div class="benefit-row">');
                row.append('<input type="text" name="ingredient_benefits[]" class="regular-text" placeholder="<?php _e('Voordeel', 'ai-product-chatbot'); ?>" />');
                row.append('<button type="button" class="button remove-benefit"><?php _e('Verwijderen', 'ai-product-chatbot'); ?></button>');
                
                $('#benefits-container').append(row);
                
                // Reset flag after a short delay
                setTimeout(() => {
                    isAddingBenefit = false;
                }, 100);
                
                return false;
            });
            
            // Add skin type with state tracking
            $('#add-skin-type').on('click.aipc-ingredients', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                
                // Prevent double execution
                if (isAddingSkinType) {
                    console.log('Already adding skin type, skipping...');
                    return false;
                }
                
                isAddingSkinType = true;
                console.log('Adding ONE skin type row');
                
                // Create and add just ONE row
                const row = $('<div class="skin-type-row">');
                row.append('<input type="text" name="ingredient_skin_types[]" class="regular-text" placeholder="<?php _e('Doelgroep/type', 'ai-product-chatbot'); ?>" />');
                row.append('<button type="button" class="button remove-skin-type"><?php _e('Verwijderen', 'ai-product-chatbot'); ?></button>');
                
                $('#skin-types-container').append(row);
                
                // Reset flag after a short delay
                setTimeout(() => {
                    isAddingSkinType = false;
                }, 100);
                
                return false;
            });
            
            // Remove benefit - use event delegation with namespaced events
            $('#benefits-container').off('click.aipc-ingredients', '.remove-benefit').on('click.aipc-ingredients', '.remove-benefit', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                console.log('Removing benefit row');
                $(this).closest('.benefit-row').remove();
                return false;
            });
            
            // Remove skin type - use event delegation with namespaced events  
            $('#skin-types-container').off('click.aipc-ingredients', '.remove-skin-type').on('click.aipc-ingredients', '.remove-skin-type', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                console.log('Removing skin type row');
                $(this).closest('.skin-type-row').remove();
                return false;
            });
            
            console.log('AIPC Ingredients: Event handlers initialized with state tracking');
        });
        </script>
        
        <style>
        .benefit-row, .skin-type-row {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            gap: 10px;
        }
        
.benefit-row input, .skin-type-row input {
    flex: 1;
}

.button-disabled {
    opacity: 0.6 !important;
    cursor: not-allowed !important;
    pointer-events: none !important;
}

.aipc-upgrade-notice {
    margin: 15px 0;
    padding: 10px;
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
    color: #856404;
}
        </style>
        <?php
    }
}
?>
