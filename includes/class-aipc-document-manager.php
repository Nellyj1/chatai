<?php
class AIPC_Document_Manager {
    
    private $upload_dir;
    private $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'md'];
    private $max_file_size = 10485760; // 10MB
    
    public function __construct() {
        $this->upload_dir = wp_upload_dir()['basedir'] . '/aipc-documents/';
        $this->create_upload_directory();
    }
    
    private function create_upload_directory() {
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }
    }
    
    public function upload_document($file, $title = '', $description = '') {
        // Validate file
        $validation = $this->validate_file($file);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message']
            ];
        }
        
        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = sanitize_file_name($title ?: pathinfo($file['name'], PATHINFO_FILENAME));
        $filename = $filename . '_' . time() . '.' . $file_extension;
        $file_path = $this->upload_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            return [
                'success' => false,
                'message' => 'Fout bij uploaden van bestand.'
            ];
        }
        
        // Extract text content
        $content = $this->extract_text_content($file_path, $file_extension);
        
        // Save to database
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_documents';
        
        $result = $wpdb->insert($table_name, [
            'title' => sanitize_text_field($title ?: pathinfo($file['name'], PATHINFO_FILENAME)),
            'description' => sanitize_textarea_field($description),
            'filename' => $filename,
            'file_path' => $file_path,
            'file_size' => $file['size'],
            'file_type' => $file_extension,
            'content' => $content,
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);
        
        if ($result === false) {
            unlink($file_path); // Clean up file
            return [
                'success' => false,
                'message' => 'Fout bij opslaan in database.'
            ];
        }
        
        return [
            'success' => true,
            'data' => [
                'id' => $wpdb->insert_id,
                'filename' => $filename,
                'content_length' => strlen($content)
            ]
        ];
    }
    
    private function validate_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'message' => 'Fout bij uploaden van bestand.'
            ];
        }
        
        // Check file size
        if ($file['size'] > $this->max_file_size) {
            return [
                'valid' => false,
                'message' => 'Bestand is te groot. Maximum 10MB toegestaan.'
            ];
        }
        
        // Check file type
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $this->allowed_types)) {
            return [
                'valid' => false,
                'message' => 'Bestandstype niet ondersteund. Toegestaan: ' . implode(', ', $this->allowed_types)
            ];
        }
        
        return ['valid' => true];
    }
    
    private function extract_text_content($file_path, $file_extension) {
        switch ($file_extension) {
            case 'txt':
            case 'md':
                return file_get_contents($file_path);
                
            case 'pdf':
                return $this->extract_pdf_text($file_path);
                
            case 'doc':
            case 'docx':
                return $this->extract_word_text($file_path);
                
            default:
                return '';
        }
    }
    
    private function extract_pdf_text($file_path) {
        // Simple PDF text extraction (you might want to use a library like Smalot\PdfParser)
        // For now, return a placeholder
        return "PDF content extraction - implementeer PDF parser library voor volledige functionaliteit";
    }
    
    private function extract_word_text($file_path) {
        // Simple Word text extraction (you might want to use a library like PhpOffice\PhpWord)
        // For now, return a placeholder
        return "Word document content extraction - implementeer Word parser library voor volledige functionaliteit";
    }
    
    public function get_documents($status = 'active') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_documents';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE status = %s ORDER BY created_at DESC",
            $status
        ), ARRAY_A);
    }
    
    public function get_document($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_documents';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ), ARRAY_A);
    }
    
    public function search_documents($query) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_documents';
        
        $search_term = '%' . $wpdb->esc_like($query) . '%';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE status = 'active' AND (title LIKE %s OR description LIKE %s OR content LIKE %s) ORDER BY title ASC",
            $search_term, $search_term, $search_term
        ), ARRAY_A);
    }
    
    public function delete_document($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aipc_documents';
        
        // Get document info
        $document = $this->get_document($id);
        if (!$document) {
            return false;
        }
        
        // Delete file
        if (file_exists($document['file_path'])) {
            unlink($document['file_path']);
        }
        
        // Delete from database
        return $wpdb->update(
            $table_name,
            ['status' => 'deleted', 'updated_at' => current_time('mysql')],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );
    }
    
    public function get_document_content_for_ai($query = '') {
        $documents = $this->get_documents();
        $content = '';
        
        foreach ($documents as $document) {
            $content .= "DOCUMENT: " . $document['title'] . "\n";
            $content .= "DESCRIPTION: " . $document['description'] . "\n";
            
            // If query provided, try to find relevant sections
            if ($query) {
                $relevant_content = $this->extract_relevant_content($document['content'], $query);
                $content .= "CONTENT: " . $relevant_content . "\n\n";
            } else {
                $content .= "CONTENT: " . substr($document['content'], 0, 2000) . "...\n\n";
            }
        }
        
        return $content;
    }
    
    private function extract_relevant_content($content, $query) {
        // Simple relevance extraction - you could implement more sophisticated search
        $sentences = explode('.', $content);
        $relevant_sentences = [];
        
        foreach ($sentences as $sentence) {
            if (stripos($sentence, $query) !== false) {
                $relevant_sentences[] = trim($sentence);
            }
        }
        
        if (empty($relevant_sentences)) {
            return substr($content, 0, 1000) . "...";
        }
        
        return implode('. ', array_slice($relevant_sentences, 0, 10)) . "...";
    }
    
    public function create_database_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aipc_documents';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
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
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
