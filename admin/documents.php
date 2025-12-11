<?php
if (!defined('ABSPATH')) {
    exit;
}

$document_manager = new AIPC_Document_Manager();
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// License gating: Documents require Enterprise tier
$license_allowed = false;
$document_limit_reached = false;
$upgrade_url = '';
$current_tier = 'none';

if (class_exists('AIPC_License')) {
    $lic = AIPC_License::getInstance();
    $current_tier = $lic->get_current_tier();
    $is_active = $lic->is_active();
    
    // Only Enterprise tier gets document access
    if ($current_tier === 'enterprise' && $is_active) {
        $license_allowed = true;
    }
    
    $upgrade_url = $lic->generate_upgrade_url('enterprise');
}

// Handle file upload
if (isset($_POST['submit']) && isset($_FILES['document_file'])) {
    if (!$license_allowed) {
        if (!$is_active) {
            echo '<div class="notice notice-error"><p>' . __('Upload niet toegestaan: Geen actieve licentie. Activeer je licentie om documenten te kunnen uploaden.', 'ai-product-chatbot') . '</p></div>';
        } elseif ($current_tier !== 'enterprise') {
            echo '<div class="notice notice-error"><p>' . __('Upload niet toegestaan: Document management vereist Enterprise licentie. Upgrade naar Enterprise voor onbeperkte documenten.', 'ai-product-chatbot') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __('Upload niet toegestaan. Controleer je licentie.', 'ai-product-chatbot') . '</p></div>';  
        }
    } elseif (wp_verify_nonce($_POST['aipc_document_nonce'], 'aipc_upload_document')) {
        $title = sanitize_text_field($_POST['document_title']);
        $description = sanitize_textarea_field($_POST['document_description']);
        
        $result = $document_manager->upload_document($_FILES['document_file'], $title, $description);
        
        if ($result['success']) {
            echo '<div class="notice notice-success"><p>' . __('Document geüpload!', 'ai-product-chatbot') . '</p></div>';
            $action = 'list';
        } else {
            echo '<div class="notice notice-error"><p>' . $result['message'] . '</p></div>';
        }
    }
}

// Handle delete action
if ($action === 'delete' && $document_id > 0) {
    if (wp_verify_nonce($_GET['_wpnonce'], 'aipc_delete_document_' . $document_id)) {
        $result = $document_manager->delete_document($document_id);
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>' . __('Document verwijderd!', 'ai-product-chatbot') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __('Fout bij verwijderen van document.', 'ai-product-chatbot') . '</p></div>';
        }
        $action = 'list';
    }
}

if ($action === 'list') {
    $documents = $document_manager->get_documents();
    ?>
    <div class="wrap">
        <h1>
            <?php _e('Documenten', 'ai-product-chatbot'); ?>
            <?php if ($license_allowed && !$document_limit_reached): ?>
                <a href="<?php echo admin_url('admin.php?page=aipc-documents&action=upload'); ?>" class="page-title-action">
                    <?php _e('Document Uploaden', 'ai-product-chatbot'); ?>
                </a>
            <?php endif; ?>
        </h1>
        
        <?php if (class_exists('AIPC_License')): ?>
            <?php if ($current_tier !== 'enterprise'): ?>
                <div class="notice notice-warning">
                    <p>
                        <?php _e('🔒 Document Management vereist Enterprise licentie. Upgrade voor onbeperkte documenten en geavanceerde content features.', 'ai-product-chatbot'); ?>
                        <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-secondary" style="margin-left:10px;">
                            <?php _e('Upgrade naar Enterprise', 'ai-product-chatbot'); ?>
                        </a>
                    </p>
                </div>
            <?php elseif (!$lic->is_active()): ?>
                <div class="notice notice-error">
                    <p>
                        <?php _e('🔒 Geen actieve licentie. Document beheer uitgeschakeld.', 'ai-product-chatbot'); ?>
                        <a href="<?php echo admin_url('admin.php?page=aipc-settings'); ?>" class="button button-secondary" style="margin-left:10px;">
                            <?php _e('Licentie activeren', 'ai-product-chatbot'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="aipc-documents-info">
            <p><?php _e('Upload productdocumenten, brochures, of andere informatieve bestanden die de chatbot kan gebruiken als kennisbank.', 'ai-product-chatbot'); ?></p>
            <p><strong><?php _e('Ondersteunde bestandstypen:', 'ai-product-chatbot'); ?></strong> PDF, DOC, DOCX, TXT, MD</p>
            <p><strong><?php _e('Maximum bestandsgrootte:', 'ai-product-chatbot'); ?></strong> 10MB</p>
        </div>
        
        <?php if (!empty($documents)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Titel', 'ai-product-chatbot'); ?></th>
                        <th><?php _e('Bestandstype', 'ai-product-chatbot'); ?></th>
                        <th><?php _e('Grootte', 'ai-product-chatbot'); ?></th>
                        <th><?php _e('Beschrijving', 'ai-product-chatbot'); ?></th>
                        <th><?php _e('Geüpload', 'ai-product-chatbot'); ?></th>
                        <th><?php _e('Acties', 'ai-product-chatbot'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $document): ?>
                        <tr>
                            <td><strong><?php echo esc_html($document['title']); ?></strong></td>
                            <td>
                                <span class="aipc-file-type aipc-file-type-<?php echo esc_attr($document['file_type']); ?>">
                                    <?php echo strtoupper($document['file_type']); ?>
                                </span>
                            </td>
                            <td><?php echo size_format($document['file_size']); ?></td>
                            <td><?php echo esc_html(wp_trim_words($document['description'], 10)); ?></td>
                            <td><?php echo esc_html(human_time_diff(strtotime($document['created_at']), current_time('timestamp'))); ?> <?php _e('geleden', 'ai-product-chatbot'); ?></td>
                            <td>
                                <a href="#" class="button button-small" onclick="aipcPreviewDocument(<?php echo $document['id']; ?>)">
                                    <?php _e('Bekijken', 'ai-product-chatbot'); ?>
                                </a>
                                <?php if (class_exists('AIPC_License') && $lic->is_active()): ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=aipc-documents&action=delete&id=' . $document['id']), 'aipc_delete_document_' . $document['id']); ?>" 
                                       class="button button-small button-link-delete" 
                                       onclick="return confirm('<?php _e('Weet je zeker dat je dit document wilt verwijderen?', 'ai-product-chatbot'); ?>')">
                                        <?php _e('Verwijderen', 'ai-product-chatbot'); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="button button-small button-disabled" title="<?php _e('Licentie vereist', 'ai-product-chatbot'); ?>"><?php _e('Verwijderen', 'ai-product-chatbot'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="aipc-empty-state">
                <h3><?php _e('Nog geen documenten geüpload', 'ai-product-chatbot'); ?></h3>
                <p><?php _e('Upload je eerste document om de chatbot kennisbank uit te breiden.', 'ai-product-chatbot'); ?></p>
                <?php if ($license_allowed): ?>
                <a href="<?php echo admin_url('admin.php?page=aipc-documents&action=upload'); ?>" class="button button-primary">
                    <?php _e('Document Uploaden', 'ai-product-chatbot'); ?>
                </a>
                <?php else: ?>
                    <?php if (class_exists('AIPC_License') && !$lic->is_active()): ?>
                    <a href="<?php echo admin_url('admin.php?page=aipc-settings'); ?>" class="button button-primary">
                        <?php _e('Licentie Activeren', 'ai-product-chatbot'); ?>
                    </a>
                    <?php else: ?>
                    <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-primary">
                        <?php _e('Upgrade naar Enterprise', 'ai-product-chatbot'); ?>
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
} elseif ($action === 'upload') {
    ?>
    <div class="wrap">
        <h1><?php _e('Document Uploaden', 'ai-product-chatbot'); ?></h1>
        
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field('aipc_upload_document', 'aipc_document_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="document_title"><?php _e('Document Titel', 'ai-product-chatbot'); ?> *</label>
                    </th>
                    <td>
                        <input type="text" id="document_title" name="document_title" 
                               class="regular-text" required />
                        <p class="description">
                            <?php _e('Een beschrijvende titel voor dit document.', 'ai-product-chatbot'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="document_description"><?php _e('Beschrijving', 'ai-product-chatbot'); ?></label>
                    </th>
                    <td>
                        <textarea id="document_description" name="document_description" 
                                  rows="4" cols="50" class="large-text"></textarea>
                        <p class="description">
                            <?php _e('Een korte beschrijving van de inhoud van dit document.', 'ai-product-chatbot'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="document_file"><?php _e('Bestand', 'ai-product-chatbot'); ?> *</label>
                    </th>
                    <td>
                        <input type="file" id="document_file" name="document_file" 
                               accept=".pdf,.doc,.docx,.txt,.md" required />
                        <p class="description">
                            <?php _e('Selecteer een PDF, Word document, of tekstbestand. Maximum 10MB.', 'ai-product-chatbot'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Document Uploaden', 'ai-product-chatbot')); ?>
            <a href="<?php echo admin_url('admin.php?page=aipc-documents'); ?>" class="button"><?php _e('Annuleren', 'ai-product-chatbot'); ?></a>
        </form>
        
        <div class="aipc-upload-tips">
            <h3><?php _e('Upload Tips', 'ai-product-chatbot'); ?></h3>
            <ul>
                <li><?php _e('Gebruik duidelijke, beschrijvende titels', 'ai-product-chatbot'); ?></li>
                <li><?php _e('Voeg een korte beschrijving toe voor betere zoekresultaten', 'ai-product-chatbot'); ?></li>
                <li><?php _e('PDF bestanden worden het beste ondersteund', 'ai-product-chatbot'); ?></li>
                <li><?php _e('Zorg dat de tekst in het document leesbaar is (niet gescand als afbeelding)', 'ai-product-chatbot'); ?></li>
            </ul>
        </div>
    </div>
    <?php
}
?>

<style>
.aipc-documents-info {
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 15px;
    margin: 20px 0;
}

.aipc-file-type {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.aipc-file-type-pdf {
    background: #ffebee;
    color: #c62828;
}

.aipc-file-type-doc,
.aipc-file-type-docx {
    background: #e3f2fd;
    color: #1565c0;
}

.aipc-file-type-txt,
.aipc-file-type-md {
    background: #f3e5f5;
    color: #7b1fa2;
}

.aipc-empty-state {
    text-align: center;
    padding: 40px 20px;
    background: #f9f9f9;
    border: 2px dashed #ccc;
    border-radius: 8px;
    margin: 20px 0;
}

.aipc-empty-state h3 {
    color: #666;
    margin-bottom: 10px;
}

.aipc-upload-tips {
    background: #f0f8ff;
    border: 1px solid #b3d9ff;
    border-radius: 6px;
    padding: 15px;
    margin-top: 20px;
}

.aipc-upload-tips h3 {
    margin-top: 0;
    color: #0066cc;
}

.aipc-upload-tips ul {
    margin-left: 20px;
}

.aipc-upload-tips li {
    margin-bottom: 5px;
    color: #333;
}
</style>

<script>
function aipcPreviewDocument(documentId) {
    // Open document preview in modal or new window
    alert('Document preview functionaliteit komt binnenkort beschikbaar!');
}
</script>
