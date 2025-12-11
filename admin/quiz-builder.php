<?php
if (!defined('ABSPATH')) { exit; }

// License gating
$license_allowed = true;
$upgrade_url = '';
if (class_exists('AIPC_License')) {
    $lic = AIPC_License::getInstance();
    $license_allowed = ($lic->is_active() && $lic->has_feature('product_quiz'));
    $upgrade_url = $lic->generate_upgrade_url('business');
}

// Get existing quiz data if any
$existing_quiz_data = get_option('aipc_quiz_builder_data', '[]');

// Handle save
if (isset($_POST['aipc_quiz_save']) && wp_verify_nonce($_POST['aipc_quiz_nonce'] ?? '', 'aipc_save_quiz')) {
    if (!$license_allowed) {
        echo '<div class="notice notice-error"><p>' . __('Opslaan niet toegestaan: Business of Enterprise licentie vereist.', 'ai-product-chatbot') . '</p></div>';
    } else {
        if (isset($_POST['aipc_quiz_data'])) {
            $quiz_data = wp_unslash($_POST['aipc_quiz_data']);
            update_option('aipc_quiz_builder_data', $quiz_data);
            
            // Convert to old format for compatibility
            convert_visual_to_json($quiz_data);
            
            echo '<div class="notice notice-success"><p>' . __('Quiz opgeslagen!', 'ai-product-chatbot') . '</p></div>';
            $existing_quiz_data = $quiz_data;
        }
    }
}

// Converter function from visual data to JSON format
function convert_visual_to_json($visual_data_json) {
    try {
        $visual_data = json_decode($visual_data_json, true);
        if (!$visual_data || !isset($visual_data['blocks'])) {
            return;
        }
        
        error_log('Converting visual data: ' . $visual_data_json);
        
        $questions = [];
        $question_blocks = [];
        $result_blocks = [];
        
        // First pass: collect all blocks
        foreach ($visual_data['blocks'] as $blockId => $block) {
            if ($block['type'] === 'question') {
                $question_blocks[$blockId] = $block;
            } elseif ($block['type'] === 'result') {
                $result_blocks[$blockId] = $block;
            }
        }
        
        error_log('Found ' . count($question_blocks) . ' question blocks and ' . count($result_blocks) . ' result blocks');
        
        $mappings = [];
        
        // Find first question block (entry point)
        $startBlock = null;
        foreach ($question_blocks as $blockId => $block) {
            // Look for a block that's not referenced by others (start of flow)
            $isReferenced = false;
            foreach ($question_blocks as $otherId => $otherBlock) {
                if ($otherId !== $blockId) {
                    $connections = $otherBlock['connections'] ?? [];
                    if (in_array($blockId, $connections)) {
                        $isReferenced = true;
                        break;
                    }
                }
            }
            if (!$isReferenced) {
                $startBlock = $blockId;
                break;
            }
        }
        
        // If no clear start found, use first question
        if (!$startBlock && !empty($question_blocks)) {
            $startBlock = array_keys($question_blocks)[0];
        }
        
        error_log("Start block: $startBlock");
        
        // Helper to trace one specific path from start block
        $tracePath = function($currentBlockId, $path = []) use (&$tracePath, $question_blocks, $result_blocks) {
            if (isset($result_blocks[$currentBlockId])) {
                // Reached a result - return this path
                return [['path' => $path, 'result' => $currentBlockId]];
            }
            
            if (!isset($question_blocks[$currentBlockId])) {
                return [];
            }
            
            $currentBlock = $question_blocks[$currentBlockId];
            $connections = $currentBlock['connections'] ?? [];
            $questionKey = $currentBlock['key'] ?? sanitize_key($currentBlock['question'] ?? 'question');
            
            $allPaths = [];
            
            foreach ($connections as $optionIndex => $targetBlockId) {
                $answer = $currentBlock['options'][$optionIndex] ?? "Option $optionIndex";
                $newPath = array_merge($path, [$questionKey => $answer]);
                
                // Continue from target block
                $subPaths = $tracePath($targetBlockId, $newPath);
                $allPaths = array_merge($allPaths, $subPaths);
            }
            
            return $allPaths;
        };
        
        // Trace all paths from start
        if ($startBlock) {
            $allPaths = $tracePath($startBlock);
            
            error_log("Found " . count($allPaths) . " total paths from start");
            
            foreach ($allPaths as $pathInfo) {
                $conditions = $pathInfo['path'];
                $resultBlockId = $pathInfo['result'];
                $resultBlock = $result_blocks[$resultBlockId];
                
                if (!empty($conditions)) {
                    $mappings[] = [
                        'if' => $conditions,
                        'label' => $resultBlock['label'] ?? 'Uitslag',
                        'summary' => $resultBlock['summary'] ?? 'Beschrijving', 
                        'products' => $resultBlock['products'] ?? []
                    ];
                    
                    error_log("Path to $resultBlockId: " . json_encode($conditions));
                }
            }
        }
        
        // Generate questions with conditional logic based on traced paths
        $questions = [];
        $questionConditions = [];
        
        // Analyze all paths to determine when each question should show
        foreach ($allPaths as $pathInfo) {
            $path = $pathInfo['path'];
            
            foreach ($path as $questionKey => $answer) {
                if (!isset($questionConditions[$questionKey])) {
                    $questionConditions[$questionKey] = [];
                }
                
                // Find the question block for this key
                $questionBlock = null;
                foreach ($question_blocks as $block) {
                    $blockKey = $block['key'] ?? sanitize_key($block['question'] ?? 'question');
                    if ($blockKey === $questionKey) {
                        $questionBlock = $block;
                        break;
                    }
                }
                
                if ($questionBlock) {
                    // Determine what conditions need to be met for this question to show
                    $showIf = [];
                    
                    // Find which question comes before this one in the path
                    $pathKeys = array_keys($path);
                    $currentIndex = array_search($questionKey, $pathKeys);
                    
                    if ($currentIndex > 0) {
                        // Add conditions from all previous questions in this path
                        for ($i = 0; $i < $currentIndex; $i++) {
                            $prevKey = $pathKeys[$i];
                            $prevAnswer = $path[$prevKey];
                            $showIf[$prevKey] = $prevAnswer;
                        }
                    }
                    
                    $questionConditions[$questionKey][] = $showIf;
                }
            }
        }
        
        // Build final questions array - only for questions that are actually used in paths
        $usedQuestions = array_keys($questionConditions);
        
        foreach ($usedQuestions as $questionKey) {
            // Find the question block for this key
            $questionBlock = null;
            foreach ($question_blocks as $block) {
                $blockKey = $block['key'] ?? sanitize_key($block['question'] ?? 'question');
                if ($blockKey === $questionKey) {
                    $questionBlock = $block;
                    break;
                }
            }
            
            if (!$questionBlock) {
                error_log("Warning: Could not find question block for key $questionKey");
                continue;
            }
            
            $questionData = [
                'key' => $questionKey,
                'question' => $questionBlock['question'] ?? 'Vraag',
                'options' => $questionBlock['options'] ?? ['Optie 1', 'Optie 2']
            ];
            
            // Add show_if conditions if this question is conditional
            if (isset($questionConditions[$questionKey])) {
                $conditions = $questionConditions[$questionKey];
                
                // If all conditions are the same, use that condition
                // If they differ, the question should always show (no condition)
                if (count($conditions) === 1) {
                    $condition = $conditions[0];
                    if (!empty($condition)) {
                        $questionData['show_if'] = $condition;
                    }
                } else {
                    // Multiple different conditions - find common prefix
                    $commonCondition = [];
                    if (!empty($conditions[0])) {
                        foreach ($conditions[0] as $key => $value) {
                            $isCommon = true;
                            foreach ($conditions as $cond) {
                                if (!isset($cond[$key]) || $cond[$key] !== $value) {
                                    $isCommon = false;
                                    break;
                                }
                            }
                            if ($isCommon) {
                                $commonCondition[$key] = $value;
                            }
                        }
                    }
                    
                    if (!empty($commonCondition)) {
                        $questionData['show_if'] = $commonCondition;
                    }
                }
            }
            
            $questions[] = $questionData;
            error_log("Question $questionKey conditions: " . json_encode($questionData['show_if'] ?? 'always show'));
        }
        
        error_log('Generated questions: ' . json_encode($questions, JSON_PRETTY_PRINT));
        error_log('Generated mappings: ' . json_encode($mappings, JSON_PRETTY_PRINT));
        
        // Save to old format options
        update_option('aipc_skin_test_questions', json_encode($questions));
        update_option('aipc_skin_test_mapping', json_encode($mappings));
        
    } catch (Exception $e) {
        error_log('Error converting visual quiz data: ' . $e->getMessage());
    }
}

// Use existing admin_search_products method from main plugin file

// Handler for getting individual product info is now in main plugin class
?>

<div class="wrap">
    <h1><?php _e('Quiz Builder', 'ai-product-chatbot'); ?> <span class="title-count">✨ Visual</span></h1>
    
    <?php if (!$license_allowed): ?>
        <div class="notice notice-warning">
            <p>
                <?php _e('Visuele Quiz Builder vereist Business of Enterprise licentie.', 'ai-product-chatbot'); ?>
                <?php if (!empty($upgrade_url)): ?>
                    <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-secondary" style="margin-left:10px;">
                        <?php _e('Upgrade naar Business', 'ai-product-chatbot'); ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <div id="quiz-builder-container" <?php echo !$license_allowed ? 'style="opacity: 0.5; pointer-events: none;"' : ''; ?>>
        
        <!-- Toolbar -->
        <div id="quiz-toolbar">
            <div class="toolbar-section">
                <h3><?php _e('Blokken', 'ai-product-chatbot'); ?></h3>
                <button type="button" class="quiz-block-btn" data-type="question">
                    <span class="dashicons dashicons-editor-help"></span>
                    <?php _e('Vraag', 'ai-product-chatbot'); ?>
                </button>
                <button type="button" class="quiz-block-btn" data-type="result">
                    <span class="dashicons dashicons-flag"></span>
                    <?php _e('Uitslag', 'ai-product-chatbot'); ?>
                </button>
            </div>
            
            <div class="toolbar-section">
                <h3><?php _e('Acties', 'ai-product-chatbot'); ?></h3>
                <button type="button" id="quiz-test-btn" class="button">
                    <span class="dashicons dashicons-controls-play"></span>
                    <?php _e('Test Quiz', 'ai-product-chatbot'); ?>
                </button>
                <button type="button" id="quiz-clear-btn" class="button">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Wis Alles', 'ai-product-chatbot'); ?>
                </button>
            </div>
            
            <div class="toolbar-section">
                <h3><?php _e('Navigatie', 'ai-product-chatbot'); ?></h3>
                <div class="zoom-controls">
                    <button type="button" id="zoom-out-btn" class="button button-small">
                        <span class="dashicons dashicons-minus"></span>
                    </button>
                    <span id="zoom-level">100%</span>
                    <button type="button" id="zoom-in-btn" class="button button-small">
                        <span class="dashicons dashicons-plus"></span>
                    </button>
                </div>
                <button type="button" id="zoom-fit-btn" class="button button-small" style="width: 100%; margin-top: 8px;">
                    <span class="dashicons dashicons-image-crop"></span>
                    <?php _e('Zoom naar fit', 'ai-product-chatbot'); ?>
                </button>
                <div class="canvas-info">
                    <small>Sleep canvas om te verplaatsen</small>
                </div>
            </div>
        </div>

        <!-- Canvas -->
        <div id="quiz-canvas">
            <div id="canvas-content">
                <div id="canvas-grid"></div>
                <div id="quiz-blocks-container"></div>
                <svg id="connection-lines"></svg>
            </div>
        </div>
        
        <!-- Properties Panel -->
        <div id="quiz-properties">
            <h3><?php _e('Eigenschappen', 'ai-product-chatbot'); ?></h3>
            <div id="properties-content">
                <p class="description"><?php _e('Selecteer een blok om eigenschappen te bewerken', 'ai-product-chatbot'); ?></p>
            </div>
        </div>

    </div>

    <!-- Save Form -->
    <form method="post" id="quiz-save-form" style="margin-top: 20px;">
        <?php wp_nonce_field('aipc_save_quiz', 'aipc_quiz_nonce'); ?>
        <input type="hidden" name="aipc_quiz_data" id="quiz-data-input" value="">
        <button type="submit" name="aipc_quiz_save" value="1" class="button button-primary" <?php disabled(!$license_allowed); ?>>
            <?php _e('Quiz Opslaan', 'ai-product-chatbot'); ?>
        </button>
        <button type="button" id="debug-data-btn" class="button" style="margin-left: 10px;">
            <?php _e('Debug Data', 'ai-product-chatbot'); ?>
        </button>
        <button type="button" id="export-quiz-btn" class="button button-secondary" style="margin-left: 10px;">
            <span class="dashicons dashicons-download" style="margin-right: 5px;"></span>
            <?php _e('Exporteer Quiz', 'ai-product-chatbot'); ?>
        </button>
        <button type="button" id="import-quiz-btn" class="button button-secondary" style="margin-left: 10px;">
            <span class="dashicons dashicons-upload" style="margin-right: 5px;"></span>
            <?php _e('Importeer Quiz', 'ai-product-chatbot'); ?>
        </button>
        <a href="admin.php?page=aipc-skin-test" class="button button-secondary">
            <?php _e('Terug naar JSON Editor', 'ai-product-chatbot'); ?>
        </a>
    </form>
</div>

<!-- Pass existing data to JavaScript -->
<script type="application/json" data-quiz-data>
<?php echo wp_json_encode(json_decode($existing_quiz_data, true), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
</script>

<!-- WordPress AJAX configuration -->
<script>
window.wpAjax = {
    url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('aipc_test_api'); ?>'
};
window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
</script>

<!-- Debug info -->
<div id="debug-info" style="margin-top: 20px; padding: 15px; background: #f0f0f1; border-radius: 4px; display: none;">
    <h4>Debug Informatie:</h4>
    <p><strong>Opgeslagen data lengte:</strong> <?php echo strlen($existing_quiz_data); ?> karakters</p>
    <p><strong>Data inhoud:</strong></p>
    <pre style="background: white; padding: 10px; border-radius: 4px; max-height: 200px; overflow-y: auto;"><?php echo esc_html($existing_quiz_data); ?></pre>
</div>

<!-- Quiz Test Modal -->
<div id="quiz-test-modal" class="quiz-modal" style="display: none;">
    <div class="quiz-modal-content">
        <div class="quiz-modal-header">
            <h2><?php _e('Test Quiz', 'ai-product-chatbot'); ?></h2>
            <span class="quiz-modal-close">&times;</span>
        </div>
        <div class="quiz-modal-body">
            <div id="quiz-test-content"></div>
        </div>
    </div>
</div>

<style>
/* Quiz Builder Styles */
#quiz-builder-container {
    display: flex;
    gap: 20px;
    margin-top: 20px;
    height: 70vh;
    min-height: 500px;
}

#quiz-toolbar {
    width: 200px;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 6px;
    padding: 20px;
    overflow-y: auto;
}

.toolbar-section {
    margin-bottom: 30px;
}

.toolbar-section h3 {
    margin: 0 0 15px 0;
    font-size: 14px;
    font-weight: 600;
    color: #1d2327;
}

.quiz-block-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 12px;
    margin-bottom: 10px;
    background: #f6f7f7;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s ease;
}

.quiz-block-btn:hover {
    background: #2271b1;
    color: white;
    border-color: #2271b1;
}

.quiz-block-btn .dashicons {
    font-size: 16px;
}

#quiz-canvas {
    flex: 1;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 6px;
    position: relative;
    overflow: hidden;
    cursor: grab;
}

#quiz-canvas.panning {
    cursor: grabbing;
}

#canvas-content {
    position: relative;
    width: 8000px;
    height: 5000px;
    min-width: 8000px;
    min-height: 5000px;
    background-image: 
        linear-gradient(rgba(0,0,0,0.1) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0,0,0,0.1) 1px, transparent 1px);
    background-size: 20px 20px;
    transform-origin: 0 0;
    transition: transform 0.2s ease;
}

#connection-lines {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 1;
    shape-rendering: crispEdges;
    text-rendering: optimizeLegibility;
}

.connection-label {
    dominant-baseline: central;
    text-anchor: middle;
    font-family: system-ui, -apple-system, sans-serif;
    user-select: none;
    pointer-events: none;
    -webkit-transform: translateZ(0);
    transform: translateZ(0);
}

.connection-line, .connection-label-bg {
    pointer-events: none;
    -webkit-transform: translateZ(0);
    transform: translateZ(0);
}

/* Zoom Controls */
.zoom-controls {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}

.zoom-controls button {
    padding: 4px 8px;
    min-width: 32px;
}

#zoom-level {
    font-size: 12px;
    font-weight: 600;
    color: #1d2327;
    min-width: 40px;
    text-align: center;
}

.canvas-info {
    margin-top: 10px;
    padding: 8px;
    background: #f6f7f7;
    border-radius: 4px;
    text-align: center;
}

.canvas-info small {
    color: #646970;
    font-size: 11px;
}

#quiz-blocks-container {
    position: relative;
    width: 100%;
    height: 100%;
    z-index: 2;
}

.quiz-block {
    position: absolute;
    background: #fff;
    border: 2px solid #c3c4c7;
    border-radius: 8px;
    padding: 15px;
    min-width: 200px;
    max-width: 300px;
    cursor: move;
    user-select: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

.quiz-block:hover {
    border-color: #2271b1;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.quiz-block.selected {
    border-color: #2271b1;
    box-shadow: 0 0 0 2px rgba(34, 113, 177, 0.2);
}

.quiz-block.question-block {
    border-color: #007cba;
    background: linear-gradient(135deg, #f7f9fc 0%, #ffffff 100%);
}

.quiz-block.result-block {
    border-color: #00a32a;
    background: linear-gradient(135deg, #f6fdf7 0%, #ffffff 100%);
}

/* Validation styling for incomplete blocks */
.quiz-block.incomplete-block {
    border-color: #d63638 !important;
    background: linear-gradient(135deg, #fdf2f2 0%, #ffffff 100%) !important;
    box-shadow: 0 0 0 2px rgba(214, 54, 56, 0.2) !important;
}

.quiz-block.incomplete-block .block-header {
    background: rgba(214, 54, 56, 0.1);
    border-radius: 4px 4px 0 0;
    margin: -15px -15px 12px -15px;
    padding: 15px;
}

.quiz-block.complete-block {
    border-color: #00a32a;
    box-shadow: 0 0 0 1px rgba(0, 163, 42, 0.1);
}

/* Pulse animation for incomplete blocks */
.quiz-block.incomplete-block {
    animation: incompletePulse 2s ease-in-out infinite;
}

@keyframes incompletePulse {
    0%, 100% {
        box-shadow: 0 0 0 2px rgba(214, 54, 56, 0.2);
    }
    50% {
        box-shadow: 0 0 0 4px rgba(214, 54, 56, 0.1);
    }
}

.block-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e0e0e0;
}

.block-icon {
    width: 20px;
    height: 20px;
    font-size: 16px;
}

.block-title {
    font-weight: 600;
    color: #1d2327;
    flex: 1;
}

.block-delete {
    background: none;
    border: none;
    color: #d63638;
    cursor: pointer;
    padding: 2px;
    border-radius: 3px;
}

.block-delete:hover {
    background: #d63638;
    color: white;
}

.block-content {
    font-size: 13px;
    color: #3c434a;
    line-height: 1.4;
}

.block-question {
    font-weight: 500;
    margin-bottom: 8px;
}

.block-options {
    list-style: none;
    margin: 0;
    padding: 0;
}

.block-options li {
    padding: 4px 0;
    padding-left: 12px;
    position: relative;
}

.block-options li:before {
    content: "•";
    position: absolute;
    left: 0;
    color: #007cba;
}

/* Quick Add Buttons */
.quick-add-buttons {
    position: absolute;
    bottom: -20px;
    right: 10px;
    display: none;
    gap: 5px;
    z-index: 10;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.quiz-block:hover .quick-add-buttons {
    display: flex;
    opacity: 1;
}

/* Hide quick-add buttons for result blocks (end of quiz flow) */
.quiz-block.result-block .quick-add-buttons {
    display: none !important;
}

.quick-add-btn {
    width: 30px;
    height: 30px;
    border: 2px solid #2271b1;
    background: #2271b1;
    color: white;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    transition: all 0.2s ease;
    position: relative;
}

.quick-add-btn:hover {
    background: #1a5a8a;
    border-color: #1a5a8a;
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
}

.quick-add-btn .dashicons {
    font-size: 14px;
    line-height: 1;
}

.quick-add-btn:hover::after {
    content: attr(title);
    position: absolute;
    bottom: 35px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0,0,0,0.8);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    white-space: nowrap;
    pointer-events: none;
    z-index: 1000;
}

#quiz-properties {
    width: 300px;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 6px;
    padding: 20px;
    overflow-y: auto;
}

#quiz-properties h3 {
    margin: 0 0 15px 0;
    color: #1d2327;
}

.property-group {
    margin-bottom: 20px;
}

.property-group label {
    display: block;
    font-weight: 500;
    margin-bottom: 5px;
    color: #1d2327;
}

.property-group input,
.property-group textarea,
.property-group select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    font-size: 13px;
}

.property-group textarea {
    min-height: 80px;
    resize: vertical;
}

.quiz-options-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.quiz-option-item {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    padding: 8px;
    background: #f6f7f7;
    border-radius: 4px;
}

.quiz-option-item input {
    flex: 1;
    margin: 0;
}

.quiz-option-delete {
    background: #d63638;
    color: white;
    border: none;
    border-radius: 3px;
    padding: 4px 8px;
    cursor: pointer;
    font-size: 12px;
}

.quiz-add-option {
    background: #2271b1;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 8px 12px;
    cursor: pointer;
    font-size: 12px;
    margin-top: 8px;
}

/* Modal Styles */
.quiz-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quiz-modal-content {
    background: white;
    border-radius: 8px;
    max-width: 600px;
    max-height: 80vh;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.quiz-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
}

.quiz-modal-header h2 {
    margin: 0;
}

.quiz-modal-close {
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.quiz-modal-close:hover {
    color: #000;
}

.quiz-modal-body {
    padding: 20px;
    overflow-y: auto;
    max-height: 60vh;
}

/* Connection Lines */
.connection-line {
    stroke: #2271b1;
    stroke-width: 2;
    fill: none;
    marker-end: url(#arrowhead);
}

.connection-line:hover {
    stroke: #d63638;
    stroke-width: 3;
}

/* Responsive */
@media (max-width: 1200px) {
    #quiz-builder-container {
        flex-direction: column;
        height: auto;
    }
    
    #quiz-toolbar {
        width: 100%;
        height: auto;
        display: flex;
        gap: 40px;
    }
    
    #quiz-canvas {
        height: 500px;
    }
    
    #quiz-properties {
        width: 100%;
    }
}

/* Utility */
.description {
    color: #646970;
    font-style: italic;
}

.title-count {
    background: #2271b1;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: normal;
}
</style>

<script>
// Quiz Builder JavaScript will be loaded here
document.addEventListener('DOMContentLoaded', function() {
    console.log('Quiz Builder loaded!');
    // Initialize the quiz builder
    initQuizBuilder();
});

function initQuizBuilder() {
    const existingData = <?php echo json_encode($existing_quiz_data); ?>;
    
    // Quiz Builder implementation goes here
    // This will be expanded in the next steps
}
</script>