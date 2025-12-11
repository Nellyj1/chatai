<?php
class AIPC_Chatbot_Frontend {
    
    public function __construct() {
        add_action('wp_footer', [$this, 'render_chatbot']);
        add_shortcode('aipc_chatbot', [$this, 'shortcode_chatbot']);
    }
    
    private function get_welcome_message() {
        // Get current language - Polylang specific
        $current_language = function_exists('pll_current_language') ? pll_current_language() : null;
        
        // If no Polylang, fall back to WordPress locale detection
        if (!$current_language) {
            $current_locale = function_exists('determine_locale') ? determine_locale() : get_locale();
            $current_language = substr($current_locale, 0, 2); // Extract language code (en, nl, etc.)
        }
        
        $default_nl = 'Hallo! Ik ben je AI product assistant. Hoe kan ik je vandaag helpen?';
        $default_en = 'Hello! I am your AI product assistant. How can I help you today?';
        
        // Get saved welcome message
        $welcome_option = get_option('aipc_chatbot_welcome_message', '');
        
        // Determine if current language is English
        $is_english = ($current_language === 'en');
        
        // Set default message based on language if no custom message is saved
        $welcome_message = $welcome_option ?: ($is_english ? $default_en : $default_nl);
        
        // Try Polylang string translation first
        if (function_exists('pll_translate_string')) {
            $translated = pll_translate_string($welcome_message, $current_language);
            if ($translated && $translated !== $welcome_message) {
                $welcome_message = $translated;
            } else {
                // No translation found, use language-specific default
                $welcome_message = $is_english ? $default_en : $default_nl;
            }
        } elseif (has_filter('wpml_translate_single_string')) {
            // WPML fallback
            $translated = apply_filters('wpml_translate_single_string', $welcome_message, 'ai-product-chatbot', 'aipc_welcome_message', $current_language);
            if ($translated && $translated !== $welcome_message) {
                $welcome_message = $translated;
            } else {
                $welcome_message = $is_english ? $default_en : $default_nl;
            }
        } else {
            // No translation plugin: use language-based defaults
            $welcome_message = $is_english ? $default_en : $default_nl;
        }
        
        return $welcome_message;
    }
    
    private function get_ui_text($key) {
        $current_language = function_exists('pll_current_language') ? pll_current_language() : null;
        
        if (!$current_language) {
            $current_locale = function_exists('determine_locale') ? determine_locale() : get_locale();
            $current_language = substr($current_locale, 0, 2);
        }
        
        $is_english = ($current_language === 'en');
        
        $ui_texts = [
            'nieuwe_chat' => $is_english ? 'New chat' : 'Nieuwe chat',
            'sluit_chat' => $is_english ? 'Close chat' : 'Sluit chat',
            'online' => $is_english ? 'Online' : 'Online',
            'typ_vraag' => $is_english ? 'Type your question here...' : 'Typ je vraag hier...',
            'verstuur' => $is_english ? 'Send' : 'Verstuur',
            'ai_typing' => $is_english ? 'AI is typing...' : 'AI is aan het typen...'
        ];
        
        return $ui_texts[$key] ?? $key;
    }
    
    
    private function get_svg_icon($key, $size = 20) {
        $icons = [
            'robot' => '<path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM12 8.5C13.66 8.5 15 9.84 15 11.5V17.5C15 19.16 13.66 20.5 12 20.5S9 19.16 9 17.5V11.5C9 9.84 10.34 8.5 12 8.5ZM7.5 11C7.78 11 8 11.22 8 11.5V13.5C8 13.78 7.78 14 7.5 14S7 13.78 7 13.5V11.5C7 11.22 7.22 11 7.5 11ZM16.5 11C16.78 11 17 11.22 17 11.5V13.5C17 13.78 16.78 14 16.5 14S16 13.78 16 13.5V11.5C16 11.22 16.22 11 16.5 11Z"/>',
            'chat' => '<path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM20 16H5.17L4 17.17V4H20V16Z"/>',
            'support' => '<path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM12 20C7.59 20 4 16.41 4 12S7.59 4 12 4 20 7.59 20 12 16.41 20 12 20ZM12 6C8.69 6 6 8.69 6 12S8.69 18 12 18 18 15.31 18 12 15.31 6 12 6ZM12 8C13.1 8 14 8.9 14 10S13.1 12 12 12 10 11.1 10 10 10.9 8 12 8ZM7 19H9V21H7V19ZM15 19H17V21H15V19Z"/>',
            'help' => '<path d="M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12 17.52 2 12 2ZM13 19H11V17H13V19ZM15.07 11.25L14.17 12.17C13.45 12.9 13 13.5 13 15H11V14.5C11 13.4 11.45 12.4 12.17 11.67L13.41 10.41C13.78 10.05 14 9.55 14 9C14 7.9 13.1 7 12 7S10 7.9 10 9H8C8 6.79 9.79 5 12 5S16 6.79 16 9C16 9.88 15.64 10.67 15.07 11.25Z"/>',
            'message' => '<path d="M20 4H4C2.9 4 2 4.9 2 6V18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V6C22 4.9 21.1 4 20 4ZM20 8L12 13L4 8V6L12 11L20 6V8Z"/>',
            'phone' => '<path d="M6.62 10.79C8.06 13.62 10.38 15.94 13.21 17.38L15.41 15.18C15.69 14.9 16.08 14.82 16.43 14.93C17.55 15.3 18.75 15.5 20 15.5C20.55 15.5 21 15.95 21 16.5V20C21 20.55 20.55 21 20 21C10.61 21 3 13.39 3 4C3 3.45 3.45 3 4 3H7.5C8.05 3 8.5 3.45 8.5 4C8.5 5.25 8.7 6.45 9.07 7.57C9.18 7.92 9.1 8.31 8.82 8.59L6.62 10.79Z"/>',
            'star' => '<path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>',
            'heart' => '<path d="M12 21.35L10.55 20.03C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3C9.24 3 10.91 3.81 12 5.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5 22 12.28 18.6 15.36 13.45 20.04L12 21.35Z"/>',
            'lightbulb' => '<path d="M9 21C9 21.55 9.45 22 10 22H14C14.55 22 15 21.55 15 21V20H9V21ZM12 2C8.14 2 5 5.14 5 9C5 11.38 6.19 13.47 8 14.74V17C8 17.55 8.45 18 9 18H15C15.55 18 16 17.55 16 17V14.74C17.81 13.47 19 11.38 19 9C19 5.14 15.86 2 12 2Z"/>',
            'gear' => '<path d="M12 15.5C10.07 15.5 8.5 13.93 8.5 12S10.07 8.5 12 8.5 15.5 10.07 15.5 12 13.93 15.5 12 15.5ZM19.43 12.98C19.47 12.66 19.5 12.34 19.5 12S19.47 11.34 19.43 11.02L21.54 9.37C21.73 9.22 21.78 8.95 21.66 8.73L19.66 5.27C19.54 5.05 19.27 4.97 19.05 5.05L16.56 6.05C16.04 5.65 15.48 5.32 14.87 5.07L14.49 2.42C14.46 2.18 14.25 2 14 2H10C9.75 2 9.54 2.18 9.51 2.42L9.13 5.07C8.52 5.32 7.96 5.66 7.44 6.05L4.95 5.05C4.72 4.96 4.46 5.05 4.34 5.27L2.34 8.73C2.21 8.95 2.27 9.22 2.46 9.37L4.57 11.02C4.53 11.34 4.5 11.67 4.5 12S4.53 12.66 4.57 12.98L2.46 14.63C2.27 14.78 2.21 15.05 2.34 15.27L4.34 18.73C4.46 18.95 4.73 19.03 4.95 18.95L7.44 17.95C7.96 18.35 8.52 18.68 9.13 18.93L9.51 21.58C9.54 21.82 9.75 22 10 22H14C14.25 22 14.46 21.82 14.49 21.58L14.87 18.93C15.48 18.68 16.04 18.34 16.56 17.95L19.05 18.95C19.28 19.04 19.54 18.95 19.66 18.73L21.66 15.27C21.78 15.05 21.73 14.78 21.54 14.63L19.43 12.98Z"/>',
            'shield' => '<path d="M12 1L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 1ZM10 17L6 13L7.41 11.59L10 14.17L16.59 7.58L18 9L10 17Z"/>',
            'rocket' => '<path d="M12 2C12 2 21 6.5 21 12C21 12 21 12 21 12H12V2ZM11 4V11H4C4 11 4 11 4 11C4 6.5 11 4 11 4ZM12 13H21C21 17.5 12 22 12 22V13ZM4 13H11V20C11 20 4 17.5 4 13Z"/>',
            'diamond' => '<path d="M6 2L2 8L12 22L22 8L18 2H6ZM6.5 4H8.5L7 7H4L6.5 4ZM11 4H13V7H11V4ZM15.5 4H17.5L20 7H17L15.5 4ZM5 9H8L12 18L5 9ZM10 9H14L12 16L10 9ZM16 9H19L12 18L16 9Z"/>',
            'crown' => '<path d="M5 16L3 5L8.5 10L12 4L15.5 10L21 5L19 16H5ZM19 19C19 19.6 18.6 20 18 20H6C5.4 20 5 19.6 5 19V18H19V19Z"/>',
            'compass' => '<path d="M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12S7.59 4 12 4 20 7.59 20 12 16.41 20 12 20ZM12 7C9.24 7 7 9.24 7 12S9.24 17 12 17 17 14.76 17 12 14.76 7 12 7ZM15.5 8.5L13 11L10.5 13.5L13 11L15.5 8.5Z"/>',
            'magic' => '<path d="M7.5 5.6L5 7L6.4 4.5L5 2L7.5 3.4L10 2L8.6 4.5L10 7L7.5 5.6ZM19.5 15.4L22 14L20.6 16.5L22 19L19.5 17.6L17 19L18.4 16.5L17 14L19.5 15.4ZM22 2L20.6 4.5L22 7L19.5 5.6L17 7L18.4 4.5L17 2L19.5 3.4L22 2ZM13.34 12.78L15.78 10.34L13.66 8.22L11.22 10.66L13.34 12.78ZM14.37 7.29L16.71 9.63C17.1 10.02 17.1 10.65 16.71 11.04L11.04 16.71C10.65 17.1 10.02 17.1 9.63 16.71L7.29 14.37C6.9 13.98 6.9 13.35 7.29 12.96L12.96 7.29C13.35 6.9 13.98 6.9 14.37 7.29Z"/>'
        ];
        
        $path = $icons[$key] ?? $icons['robot'];
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="currentColor">' . $path . '</svg>';
    }
    
    private function render_license_required_message() {
        $current_language = function_exists('pll_current_language') ? pll_current_language() : null;
        if (!$current_language) {
            $current_locale = function_exists('determine_locale') ? determine_locale() : get_locale();
            $current_language = substr($current_locale, 0, 2);
        }
        $is_english = ($current_language === 'en');
        
        $title = $is_english ? 'License Required' : 'Licentie Vereist';
        $message = $is_english ? 
            'This AI Product Chatbot requires a valid license to function. Please contact your site administrator to activate your license.' :
            'Deze AI Product Chatbot vereist een geldige licentie om te functioneren. Neem contact op met je site-beheerder om je licentie te activeren.';
        
        ?>
        <div id="aipc-chatbot" class="aipc-chatbot aipc-position-bottom-right aipc-theme-modern aipc-license-required">
            <div class="aipc-chatbot-toggle">
                <div class="aipc-chatbot-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 1L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 1ZM11 7H13V13H11V7ZM11 15H13V17H11V15Z" fill="currentColor"/>
                    </svg>
                </div>
                <span class="aipc-chatbot-text"><?php echo esc_html($title); ?></span>
            </div>
            
            <div class="aipc-chatbot-window">
                <div class="aipc-chatbot-header">
                    <div class="aipc-chatbot-title">
                        <div class="aipc-chatbot-avatar">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 1L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 1ZM11 7H13V13H11V7ZM11 15H13V17H11V15Z" fill="currentColor"/>
                            </svg>
                        </div>
                        <div class="aipc-chatbot-info">
                            <h3><?php echo esc_html($title); ?></h3>
                            <span class="aipc-chatbot-status" style="color: #dc3545;"><?php echo $is_english ? 'Inactive' : 'Inactief'; ?></span>
                        </div>
                    </div>
                    <div class="aipc-chatbot-actions">
                        <button class="aipc-chatbot-close" type="button">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M19 6.41L17.59 5L12 10.59L6.41 5L5 6.41L10.59 12L5 17.59L6.41 19L12 13.41L17.59 19L19 17.59L13.41 12L19 6.41Z" fill="currentColor"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="aipc-chatbot-messages">
                    <div class="aipc-message aipc-message-assistant">
                        <div class="aipc-message-avatar">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 1L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 1ZM11 7H13V13H11V7ZM11 15H13V17H11V15Z" fill="currentColor"/>
                            </svg>
                        </div>
                        <div class="aipc-message-content">
                            <p><?php echo esc_html($message); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <style>
        .aipc-license-required .aipc-chatbot-toggle {
            background-color: #dc3545 !important;
        }
        .aipc-license-required .aipc-chatbot-window {
            border-top: 3px solid #dc3545 !important;
        }
        </style>
        <?php
    }
    
    public function render_chatbot() {
        // Only show on frontend, not in admin
        if (is_admin()) {
            return;
        }
        
        // Check if chatbot is enabled
        $chatbot_enabled = get_option('aipc_chatbot_enabled', true);
        if (!$chatbot_enabled) {
            return;
        }
        
        // License check: alleen zonder licentie is chatbot uitgeschakeld
        // Basic licentie toont chatbot, maar zonder AI functionaliteit
        if (class_exists('AIPC_License')) {
            $license = AIPC_License::getInstance();
            if (!$license->is_active()) {
                $this->render_license_required_message();
                return;
            }
        } else {
            // No license class: block chatbot
            $this->render_license_required_message();
            return;
        }
        
        // Get language for UI text
        $current_language = function_exists('pll_current_language') ? pll_current_language() : null;
        if (!$current_language) {
            $current_locale = function_exists('determine_locale') ? determine_locale() : get_locale();
            $current_language = substr($current_locale, 0, 2);
        }
        $is_english = ($current_language === 'en');
        
        $position = get_option('aipc_chatbot_position', 'bottom-right');
        $theme = get_option('aipc_chatbot_theme', 'modern');
        $title = get_option('aipc_chatbot_title', 'AI Product Assistant');
        $welcome_message = $this->get_welcome_message();
        $chatbot_icon_key = get_option('aipc_chatbot_icon', 'robot');
        $chatbot_icon = $this->get_svg_icon($chatbot_icon_key, 20);
        
        ?>
        <div id="aipc-chatbot" class="aipc-chatbot aipc-position-<?php echo esc_attr($position); ?> aipc-theme-<?php echo esc_attr($theme); ?>">
            <div class="aipc-chatbot-toggle">
                <div class="aipc-chatbot-icon" style="font-size: 20px;">
                    <?php echo $chatbot_icon; ?>
                </div>
                <span class="aipc-chatbot-text"><?php echo esc_html($title); ?></span>
            </div>
            
            <div class="aipc-chatbot-window">
                <div class="aipc-chatbot-header">
                    <div class="aipc-chatbot-title">
                        <div class="aipc-chatbot-avatar">
                            <?php echo $chatbot_icon; ?>
                        </div>
                        <div class="aipc-chatbot-info">
                            <h3><?php echo esc_html($title); ?></h3>
                            <span class="aipc-chatbot-status"><?php echo esc_html($this->get_ui_text('online')); ?></span>
                        </div>
                    </div>
                    <div class="aipc-chatbot-actions">
                        <button class="aipc-chatbot-new" type="button" aria-label="<?php echo esc_attr($this->get_ui_text('nieuwe_chat')); ?>" title="<?php echo esc_attr($this->get_ui_text('nieuwe_chat')); ?>" style="background:none;border:none;color:white;cursor:pointer;padding:5px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M19 13H13V19H11V13H5V11H11V5H13V11H19V13Z" fill="currentColor"/>
                            </svg>
                        </button>
                        <button class="aipc-chatbot-close" type="button" aria-label="<?php echo esc_attr($this->get_ui_text('sluit_chat')); ?>" title="<?php echo esc_attr($this->get_ui_text('sluit_chat')); ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M19 6.41L17.59 5L12 10.59L6.41 5L5 6.41L10.59 12L5 17.59L6.41 19L12 13.41L17.59 19L19 17.59L13.41 12L19 6.41Z" fill="currentColor"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="aipc-chatbot-messages">
                    <div class="aipc-message aipc-message-assistant">
                        <div class="aipc-message-avatar">
                            <?php echo $this->get_svg_icon($chatbot_icon_key, 16); ?>
                        </div>
                        <div class="aipc-message-content">
                            <p><?php echo esc_html($welcome_message); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="aipc-chatbot-input-container">
                    <div class="aipc-chatbot-quick-actions">
                        <button class="aipc-quick-btn aipc-faq-btn" type="button" title="<?php echo esc_attr($is_english ? 'Frequently Asked Questions' : 'Veelgestelde Vragen'); ?>">
                            <?php echo esc_html($is_english ? 'FAQ' : 'FAQ'); ?>
                        </button>
                        <?php
                        // Check if quiz/skin test is enabled
                        $quiz_enabled = false;
                        if (class_exists('AIPC_License')) {
                            $license = AIPC_License::getInstance();
                            $quiz_enabled = $license->has_feature('product_quiz') && get_option('aipc_enable_skin_test', false);
                        }
                        if ($quiz_enabled):
                        ?>
                        <button class="aipc-quick-btn aipc-quiz-btn" type="button" title="<?php echo esc_attr($is_english ? 'Product Quiz' : 'Product Quiz'); ?>">
                            <?php echo esc_html($is_english ? 'Quiz' : 'Quiz'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="aipc-chatbot-input-wrapper">
                        <input type="text" class="aipc-chatbot-input" placeholder="<?php echo esc_attr($this->get_ui_text('typ_vraag')); ?>" autocomplete="off">
                        <button class="aipc-chatbot-send" type="button" title="<?php echo esc_attr($this->get_ui_text('verstuur')); ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2.01 21L23 12L2.01 3L2 10L17 12L2 14L2.01 21Z" fill="currentColor"/>
                            </svg>
                            <span class="aipc-send-text"><?php echo esc_html($this->get_ui_text('verstuur')); ?></span>
                        </button>
                    </div>
                    <div class="aipc-chatbot-typing" style="display: none;">
                        <div class="aipc-typing-indicator">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                        <span class="aipc-typing-text"><?php echo esc_html($this->get_ui_text('ai_typing')); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function shortcode_chatbot($atts) {
        $atts = shortcode_atts([
            'title' => get_option('aipc_chatbot_title', 'AI Product Assistant'),
            'theme' => get_option('aipc_chatbot_theme', 'modern'),
            'welcome_message' => $this->get_welcome_message(),
            'position' => 'inline'
        ], $atts);
        
        $chatbot_icon_key = get_option('aipc_chatbot_icon', 'robot');
        
        ob_start();
        ?>
        <div class="aipc-chatbot-shortcode aipc-theme-<?php echo esc_attr($atts['theme']); ?>">
            <div class="aipc-chatbot-window">
                <div class="aipc-chatbot-header">
                    <div class="aipc-chatbot-title">
                        <div class="aipc-chatbot-avatar">
                            <?php echo $this->get_svg_icon($chatbot_icon_key, 20); ?>
                        </div>
                        <div class="aipc-chatbot-info">
                            <h3><?php echo esc_html($atts['title']); ?></h3>
                            <span class="aipc-chatbot-status"><?php echo esc_html($this->get_ui_text('online')); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="aipc-chatbot-messages">
                    <div class="aipc-message aipc-message-assistant">
                        <div class="aipc-message-avatar">
                            <?php echo $this->get_svg_icon($chatbot_icon_key, 16); ?>
                        </div>
                        <div class="aipc-message-content">
                            <p><?php echo esc_html($atts['welcome_message']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="aipc-chatbot-input-container">
                    <?php
                    // Get language for shortcode buttons
                    $current_language = function_exists('pll_current_language') ? pll_current_language() : null;
                    if (!$current_language) {
                        $current_locale = function_exists('determine_locale') ? determine_locale() : get_locale();
                        $current_language = substr($current_locale, 0, 2);
                    }
                    $is_english = ($current_language === 'en');
                    ?>
                    <div class="aipc-chatbot-quick-actions">
                        <button class="aipc-quick-btn aipc-faq-btn" type="button" title="<?php echo esc_attr($is_english ? 'Frequently Asked Questions' : 'Veelgestelde Vragen'); ?>">
                            <?php echo esc_html($is_english ? 'FAQ' : 'FAQ'); ?>
                        </button>
                        <?php
                        // Check if quiz/skin test is enabled for shortcode
                        $quiz_enabled = false;
                        if (class_exists('AIPC_License')) {
                            $license = AIPC_License::getInstance();
                            $quiz_enabled = $license->has_feature('product_quiz') && get_option('aipc_enable_skin_test', false);
                        }
                        if ($quiz_enabled):
                        ?>
                        <button class="aipc-quick-btn aipc-quiz-btn" type="button" title="<?php echo esc_attr($is_english ? 'Product Quiz' : 'Product Quiz'); ?>">
                            <?php echo esc_html($is_english ? 'Quiz' : 'Quiz'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="aipc-chatbot-input-wrapper">
                        <input type="text" class="aipc-chatbot-input" placeholder="<?php echo esc_attr($this->get_ui_text('typ_vraag')); ?>" autocomplete="off">
                        <button class="aipc-chatbot-send" type="button" title="<?php echo esc_attr($this->get_ui_text('verstuur')); ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2.01 21L23 12L2.01 3L2 10L17 12L2 14L2.01 21Z" fill="currentColor"/>
                            </svg>
                            <span class="aipc-send-text"><?php echo esc_html($this->get_ui_text('verstuur')); ?></span>
                        </button>
                    </div>
                    <div class="aipc-chatbot-typing" style="display: none;">
                        <div class="aipc-typing-indicator">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                        <span class="aipc-typing-text"><?php echo esc_html($this->get_ui_text('ai_typing')); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public static function show_chatbot() {
        update_option('aipc_chatbot_enabled', true);
    }
    
    public static function hide_chatbot() {
        update_option('aipc_chatbot_enabled', false);
    }
    
    public static function is_chatbot_enabled() {
        return get_option('aipc_chatbot_enabled', true);
    }
}