<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'aipc_faq';
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// License gating: FAQ access depends on license tier
// No license: read-only access
// Basic tier: limited to 20 FAQ items  
// Business+: unlimited FAQ items
$license_allowed = true;
$faq_readonly = false;
$faq_limit_reached = false;
$upgrade_url = '';
$current_count = 0;
$faq_limit = 20;
$lic = null;
$current_tier = 'none';
$is_active = false;
$show_limit_info = false;
$show_readonly_info = false;

// Get current count
$current_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status != 'deleted'");

if (class_exists('AIPC_License')) {
    $lic = AIPC_License::getInstance();
    $is_active = $lic->is_active();
    $current_tier = $is_active ? $lic->get_current_tier() : 'none';
    
    if ($lic) {
        $upgrade_url = $lic->generate_upgrade_url('business');
    }
} else {
    $current_tier = 'none';
}

// Apply restrictions based on license tier
if ($current_tier === 'none') {
    // No license: read-only access
    $faq_readonly = true;
    $show_readonly_info = true;
    $license_allowed = false;
} elseif ($current_tier === 'basic') {
    // Basic: limited FAQ items
    $show_limit_info = true;
    $faq_limit_reached = ($current_count >= $faq_limit);
    // Block adding new items when limit is reached
    if ($action === 'add' && $faq_limit_reached) {
        $license_allowed = false;
    }
} elseif (in_array($current_tier, ['business', 'enterprise'])) {
    // Business+: unlimited access
    $license_allowed = true;
}

// Save
if (isset($_POST['submit']) && wp_verify_nonce($_POST['aipc_faq_nonce'], 'aipc_save_faq')) {
    $can_save = true;
    $error_message = '';
    
    // Check license restrictions
    if ($faq_readonly) {
        $can_save = false;
        $error_message = __('FAQ bewerken niet toegestaan: Actieve licentie vereist. Upgrade naar Basic voor FAQ beheer.', 'ai-product-chatbot');
    } elseif ($current_tier === 'basic' && $id === 0 && $current_count >= $faq_limit) {
        $can_save = false;
        $error_message = sprintf(__('Opslaan niet toegestaan: FAQ limiet bereikt (%d items). Upgrade naar Business voor onbeperkte FAQ items.', 'ai-product-chatbot'), $faq_limit);
    }
    
    if (!$can_save) {
        echo '<div class="notice notice-error"><p>' . $error_message . '</p></div>';
    } else {
        $data = [
            'question' => sanitize_text_field($_POST['question'] ?? ''),
            'answer' => wp_kses_post($_POST['answer'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? ''),
            'status' => isset($_POST['status']) ? 'active' : 'inactive',
            'updated_at' => current_time('mysql')
        ];
        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
        }
        echo '<div class="notice notice-success"><p>' . __('FAQ opgeslagen', 'ai-product-chatbot') . '</p></div>';
        $action = 'list';
    }
}

// Delete
if ($action === 'delete' && $id > 0 && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'aipc_delete_faq_' . $id)) {
    $wpdb->update($table, ['status' => 'deleted', 'updated_at' => current_time('mysql')], ['id' => $id]);
    echo '<div class="notice notice-success"><p>' . __('FAQ verwijderd', 'ai-product-chatbot') . '</p></div>';
    $action = 'list';
}

if ($action === 'list') {
    $rows = $wpdb->get_results("SELECT * FROM $table WHERE status != 'deleted' ORDER BY created_at DESC LIMIT 100", ARRAY_A);
    ?>
    <div class="wrap">
        <h1>
            <?php _e('FAQ', 'ai-product-chatbot'); ?>
            <?php if ($license_allowed && !$faq_limit_reached && !$faq_readonly): ?>
                <a href="<?php echo admin_url('admin.php?page=aipc-faq&action=add'); ?>" class="page-title-action"><?php _e('Nieuwe FAQ', 'ai-product-chatbot'); ?></a>
            <?php elseif ($faq_readonly): ?>
                <span class="page-title-action button-disabled" title="<?php _e('Licentie vereist voor FAQ beheer', 'ai-product-chatbot'); ?>" style="color:#666; cursor:not-allowed; text-decoration:none;">
                    <span class="dashicons dashicons-lock" style="font-size:14px;vertical-align:text-top;margin-right:3px;"></span>
                    <?php _e('Nieuwe FAQ', 'ai-product-chatbot'); ?>
                </span>
            <?php endif; ?>
        </h1>
        
        <?php if ($show_readonly_info): ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('🔒 FAQ Database (Read-only)', 'ai-product-chatbot'); ?></strong><br>
                    <?php _e('Zonder licentie kun je bestaande FAQ items bekijken, maar niet bewerken. De chatbot gebruikt deze FAQ items wel voor automatische antwoorden. Voor volledig FAQ beheer heb je een Basic licentie nodig.', 'ai-product-chatbot'); ?>
                    <?php if ($upgrade_url): ?>
                        <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-secondary" style="margin-left:10px;">
                            <?php _e('Upgrade naar Basic', 'ai-product-chatbot'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </div>
        <?php elseif ($show_limit_info): ?>
            <div class="notice notice-info">
                <p><?php printf(__('FAQ items: %d / %d (Basis tier limiet)', 'ai-product-chatbot'), $current_count, $faq_limit); ?></p>
            </div>
            <?php if ($faq_limit_reached): ?>
                <div class="notice notice-warning">
                    <p>
                        <?php _e('🔒 FAQ limiet bereikt. Upgrade naar Business voor onbeperkte FAQ items.', 'ai-product-chatbot'); ?>
                        <?php if ($upgrade_url): ?>
                            <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-secondary" style="margin-left:10px;">
                                <?php _e('Upgrade naar Business', 'ai-product-chatbot'); ?>
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($rows)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th><?php _e('Vraag', 'ai-product-chatbot'); ?></th>
                    <th><?php _e('Categorie', 'ai-product-chatbot'); ?></th>
                    <th><?php _e('Status', 'ai-product-chatbot'); ?></th>
                    <th><?php _e('Acties', 'ai-product-chatbot'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?php echo esc_html($row['question']); ?></strong></td>
                        <td><?php echo esc_html($row['category']); ?></td>
                        <td><?php echo $row['status'] === 'active' ? __('Actief', 'ai-product-chatbot') : __('Inactief', 'ai-product-chatbot'); ?></td>
                        <td>
                            <?php if (!$faq_readonly): ?>
                                <a class="button button-small" href="<?php echo wp_nonce_url(admin_url('admin.php?page=aipc-faq&action=edit&id=' . $row['id']), 'aipc_edit_faq_' . $row['id']); ?>"><?php _e('Bewerken', 'ai-product-chatbot'); ?></a>
                                <a class="button button-small button-link-delete" href="<?php echo wp_nonce_url(admin_url('admin.php?page=aipc-faq&action=delete&id=' . $row['id']), 'aipc_delete_faq_' . $row['id']); ?>" onclick="return confirm('<?php _e('Weet je zeker dat je deze FAQ wilt verwijderen?', 'ai-product-chatbot'); ?>')"><?php _e('Verwijderen', 'ai-product-chatbot'); ?></a>
                            <?php else: ?>
                                <span class="button button-small button-disabled" title="<?php _e('Licentie vereist voor bewerken', 'ai-product-chatbot'); ?>"><?php _e('Bewerken', 'ai-product-chatbot'); ?></span>
                                <span class="button button-small button-disabled" title="<?php _e('Licentie vereist voor verwijderen', 'ai-product-chatbot'); ?>"><?php _e('Verwijderen', 'ai-product-chatbot'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('Nog geen FAQ items. Voeg er een toe.', 'ai-product-chatbot'); ?></p>
        <?php endif; ?>
    </div>
    <?php
} else {
    $row = ['question' => '', 'answer' => '', 'category' => '', 'status' => 'active'];
    if ($action === 'edit' && $id > 0) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
        if (!$row) {
            echo '<div class="notice notice-error"><p>' . __('FAQ niet gevonden', 'ai-product-chatbot') . '</p></div>';
            $action = 'list';
        }
    }
    if ($action !== 'list') {
        ?>
        <div class="wrap">
            <h1><?php echo $action === 'add' ? __('Nieuwe FAQ', 'ai-product-chatbot') : __('FAQ bewerken', 'ai-product-chatbot'); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field('aipc_save_faq', 'aipc_faq_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="question"><?php _e('Vraag', 'ai-product-chatbot'); ?> *</label></th>
                        <td><input type="text" id="question" name="question" value="<?php echo esc_attr($row['question']); ?>" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="answer"><?php _e('Antwoord', 'ai-product-chatbot'); ?></label></th>
                        <td><textarea id="answer" name="answer" rows="6" class="large-text"><?php echo esc_textarea($row['answer']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="category"><?php _e('Categorie', 'ai-product-chatbot'); ?></label></th>
                        <td><input type="text" id="category" name="category" value="<?php echo esc_attr($row['category']); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="status"><?php _e('Actief', 'ai-product-chatbot'); ?></label></th>
                        <td><input type="checkbox" id="status" name="status" value="1" <?php checked($row['status'] === 'active'); ?> /></td>
                    </tr>
                </table>
                <?php submit_button($action === 'add' ? __('Toevoegen', 'ai-product-chatbot') : __('Opslaan', 'ai-product-chatbot')); ?>
                <a href="<?php echo admin_url('admin.php?page=aipc-faq'); ?>" class="button"><?php _e('Annuleren', 'ai-product-chatbot'); ?></a>
            </form>
        </div>
        <?php
    }
}


