jQuery(document).ready(function($) {
    'use strict';
    
    class AIPCChatbot {
        constructor() {
            this.isOpen = false;
            this.conversationId = null;
            this.isTyping = false;
            this.messageQueue = [];
            this.chatHistory = [];
            this.currentFAQs = [];
            
            this.init();
        }
        
        init() {
            console.log('AIPCChatbot initializing...');
            this.bindEvents();
            this.generateConversationId();
            console.log('AIPCChatbot initialized with conversation ID:', this.conversationId);
        }
        
        bindEvents() {
            console.log('Binding events...');
            
            // Toggle chatbot
            $(document).on('click', '.aipc-chatbot-toggle', (e) => {
                console.log('Toggle button clicked');
                e.preventDefault();
                this.toggleChatbot();
            });
            
            // Close chatbot
            $(document).on('click', '.aipc-chatbot-close', (e) => {
                console.log('Close button clicked');
                e.preventDefault();
                this.closeChatbot();
            });
            
            // Send message - use more specific selector
            $(document).on('click', '.aipc-chatbot-send', (e) => {
                console.log('Send button clicked');
                e.preventDefault();
                e.stopPropagation();
                this.sendMessage();
            });
            
            // Alternative event binding for send button
            $(document).on('click', 'button.aipc-chatbot-send', (e) => {
                console.log('Send button clicked (alternative)');
                e.preventDefault();
                e.stopPropagation();
                this.sendMessage();
            });
            
            // Send message on Enter key
            $(document).on('keypress', '.aipc-chatbot-input', (e) => {
                if (e.which === 13 && !e.shiftKey) {
                    console.log('Enter key pressed');
                    e.preventDefault();
                    this.sendMessage();
                }
            });
            
            // Auto-resize input
            $(document).on('input', '.aipc-chatbot-input', (e) => {
                this.autoResizeInput(e.target);
            });
            
            // Handle navigation button clicks in chat messages
            $(document).on('click', '.aipc-nav-btn', (e) => {
                e.preventDefault();
                const command = $(e.target).attr('data-command');
                
                if (command === 'reset-search') {
                    // Clear navigation state by clearing the input and showing a prompt
                    $('.aipc-chatbot-input').val('').focus();
                    this.addMessage('Stel een nieuwe vraag...', 'assistant');
                } else if (command) {
                    console.log('Navigation command:', command);
                    this.addMessage(command, 'user');
                    this.sendToServer(command);
                }
            });
            
            // Handle FAQ button clicks
            $(document).on('click', '.aipc-faq-btn', (e) => {
                console.log('FAQ button clicked');
                e.preventDefault();
                this.showFAQList();
            });
            
            // Handle Quiz button clicks
            $(document).on('click', '.aipc-quiz-btn', (e) => {
                console.log('Quiz button clicked');
                e.preventDefault();
                this.startQuiz();
            });
            
            // Handle FAQ option clicks
            $(document).on('click', '.aipc-faq-option', (e) => {
                e.preventDefault();
                const $btn = $(e.target);
                const faqIndex = parseInt($btn.attr('data-faq-index'));
                const question = $btn.text();
                console.log('FAQ option clicked:', question, 'index:', faqIndex);
                if (this.currentFAQs[faqIndex]) {
                    this.showFAQAnswer(question, this.currentFAQs[faqIndex].answer);
                }
            });
            
            // Handle back to FAQ button
            $(document).on('click', '.aipc-back-to-faq', (e) => {
                e.preventDefault();
                this.showFAQList();
            });
            
            console.log('Events bound successfully');
        }
        
        toggleChatbot() {
            const $chatbot = $('.aipc-chatbot');
            
            if (this.isOpen) {
                this.closeChatbot();
            } else {
                this.openChatbot();
            }
        }
        
        openChatbot() {
            const $chatbot = $('.aipc-chatbot');
            $chatbot.addClass('aipc-open').show();
            this.isOpen = true;
            // Apply localized UI strings on open to ensure latest language
            if (typeof this.applyLocalizedStrings === 'function') {
                this.applyLocalizedStrings();
            }
            
            // Load chat history from localStorage
            this.loadChatFromStorage();
            
            // Focus on input
            setTimeout(() => {
                $('.aipc-chatbot-input').focus();
            }, 300);
            
            // Re-bind events when chatbot opens
            this.bindDirectEvents();
            // Bind new chat button
            $('.aipc-chatbot-new').off('click').on('click', (e) => {
                e.preventDefault();
                this.resetChatHistory(true);
            });
        }
        
        bindDirectEvents() {
            console.log('Binding direct events for send button...');
            
            // Remove existing events to avoid duplicates
            $('.aipc-chatbot-send').off('click');
            
            // Bind new events
            $('.aipc-chatbot-send').on('click', (e) => {
                console.log('Direct send button click (re-bound)');
                e.preventDefault();
                e.stopPropagation();
                console.log('About to call sendMessage()');
                this.sendMessage();
                console.log('Message sent and response added');
            });
            
            // Add Enter key support for input
            $('.aipc-chatbot-input').off('keypress');
            $('.aipc-chatbot-input').on('keypress', (e) => {
                if (e.which === 13) { // Enter key
                    console.log('Enter key pressed');
                    e.preventDefault();
                    console.log('About to call sendMessage() from Enter key');
                    this.sendMessage();
                    console.log('Message sent and response added from Enter key');
                }
            });
            
            
            console.log('Direct events bound. Send buttons:', $('.aipc-chatbot-send').length);
        }
        
        resetChatHistory(forceWelcome = false) {
            try {
                const lang = (window.aipc_ajax && aipc_ajax.lang) ? aipc_ajax.lang : 'default';
                const key = 'aipc_chat_messages_' + lang;
                localStorage.removeItem(key);
            } catch (e) {}
            const $messages = $('.aipc-chatbot-messages');
            $messages.empty();
            if (forceWelcome) {
                const welcome = (window.aipc_ajax && aipc_ajax.strings && aipc_ajax.strings.welcome)
                    ? aipc_ajax.strings.welcome
                    : 'Hallo! Ik ben je AI product assistant. Hoe kan ik je vandaag helpen?';
                this.addMessage(welcome, 'assistant');
            }
        }
        
        closeChatbot() {
            const $chatbot = $('.aipc-chatbot');
            $chatbot.removeClass('aipc-open');
            this.isOpen = false;
        }

        applyLocalizedStrings() {
            try {
                if (window.aipc_ajax && aipc_ajax.strings) {
                    const s = aipc_ajax.strings;
                    if (s.input_placeholder) {
                        $('.aipc-chatbot-input').attr('placeholder', s.input_placeholder);
                    }
                    if (s.btn_send) {
                        const $btn = $('.aipc-chatbot-send');
                        if ($btn.is('input')) $btn.val(s.btn_send);
                        else $btn.text(s.btn_send);
                    }
                    if (s.btn_close) {
                        $('.aipc-chatbot-close').attr('title', s.btn_close);
                    }
                    if (s.btn_open) {
                        $('.aipc-chatbot-toggle').attr('title', s.btn_open);
                    }
                    if (s.typing) {
                        $('.aipc-typing-text').text(s.typing);
                    }
                }
            } catch (e) {
                console.log('applyLocalizedStrings error:', e);
            }
        }
        
        loadChatFromStorage() {
            try {
                const storageKey = this.getStorageKey();
                const saved = localStorage.getItem(storageKey);
                if (saved) {
                    const messages = JSON.parse(saved);
                    console.log('Loading', messages.length, 'messages from storage');
                    $('.aipc-chatbot-messages').empty();
                    messages.forEach(msg => {
                        this.addMessage(msg.content, msg.role);
                    });
                    this.scrollToBottom();
                    return;
                }
                {
                    // No saved messages in storage; only add welcome if no messages exist in DOM
                    const hasExistingMessages = $('.aipc-chatbot-messages .aipc-message').length > 0;
                    if (!hasExistingMessages) {
                        const welcome = (window.aipc_ajax && aipc_ajax.strings && aipc_ajax.strings.welcome)
                            ? aipc_ajax.strings.welcome
                            : 'Hallo! Ik ben je AI product assistant. Hoe kan ik je vandaag helpen?';
                        this.addMessage(welcome, 'assistant');
                    }
                }
            } catch (e) {
                console.log('Error loading chat from storage:', e);
                // On error, only add welcome if no messages exist in DOM
                const hasExistingMessages = $('.aipc-chatbot-messages .aipc-message').length > 0;
                if (!hasExistingMessages) {
                    const welcome = (window.aipc_ajax && aipc_ajax.strings && aipc_ajax.strings.welcome)
                        ? aipc_ajax.strings.welcome
                        : 'Hallo! Ik ben je AI product assistant. Hoe kan ik je vandaag helpen?';
                    this.addMessage(welcome, 'assistant');
                }
            }
        }
        
        saveChatToStorage() {
            try {
                const messages = [];
                $('.aipc-chatbot-messages .aipc-message').each(function() {
                    const $msg = $(this);
                    const role = $msg.hasClass('aipc-message-user') ? 'user' : 'assistant';
                    const $p = $msg.find('.aipc-message-content p');
                    const original = $p.attr('data-original');
                    const content = original !== undefined ? original : $p.text();
                    if (content.trim()) {
                        messages.push({ role, content });
                    }
                });
                localStorage.setItem(this.getStorageKey(), JSON.stringify(messages));
                console.log('Saved', messages.length, 'messages to storage');
            } catch (e) {
                console.log('Error saving chat to storage:', e);
            }
        }
        
        generateConversationId() {
            this.conversationId = 'conv_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }
        
        
        
        sendMessage() {
            console.log('=== sendMessage called ===');
            try {
                const $input = $('.aipc-chatbot-input');
                const message = ($input.val() || '').trim();
                if (!message) { console.log('No message to send'); return; }
                this.addMessage(message, 'user');
                $input.val('');
                this.sendToServer(message);
            } catch (error) {
                console.error('Error in sendMessage:', error);
            }
        }
        
        sendToServer(message, attempt = 1) {
            console.log('sendToServer called with message:', message);
            console.log('aipc_ajax object:', aipc_ajax);
            this.showTyping();
            const data = { action: 'aipc_send_message', message, conversation_id: this.conversationId, nonce: aipc_ajax.nonce };
            $.ajax({
                url: aipc_ajax.ajax_url,
                type: 'POST',
                data,
                timeout: 60000,
                success: (response) => {
                    console.log('AJAX success response:', response);
                    this.hideTyping();
                    if (response && response.success) {
                        const msg = response.data && response.data.message ? response.data.message : aipc_ajax.strings.error;
                        this.addMessage(msg, 'assistant');
                        // Handle skin test meta: quick replies + active flag
                        try {
                            if (response.data && response.data.meta) {
                                const meta = response.data.meta;
                                if (meta.skin_test && typeof meta.skin_test.active !== 'undefined') {
                                    this.aipcSkinTestActive = !!meta.skin_test.active;
                                }
                                if (Array.isArray(meta.quick_replies) && meta.quick_replies.length) {
                                    this.renderQuickReplies(meta.quick_replies, !!(meta.skin_test && meta.skin_test.active));
                                }
                            }
                        } catch (e) { console.log('skin test meta handling error', e); }
                    } else {
                        const err = (response && response.data && response.data.message) ? response.data.message : aipc_ajax.strings.error;
                        this.addMessage(err, 'assistant', true);
                    }
                },
                error: (xhr, status, error) => {
                    console.log('AJAX error:', xhr, status, error);
                    this.hideTyping();
                    let errorMessage = aipc_ajax.strings.error;
                    if (status === 'timeout') {
                        if (attempt < 2) {
                            console.log('Timeout occurred, retrying once...');
                            this.showTyping();
                            return this.sendToServer(message, attempt + 1);
                        }
                        errorMessage = 'Het duurt te lang om een antwoord te krijgen. Probeer het opnieuw.';
                    } else if (xhr && xhr.responseJSON && xhr.responseJSON.data) {
                        if (typeof xhr.responseJSON.data === 'string') {
                            errorMessage = xhr.responseJSON.data;
                        } else if (xhr.responseJSON.data.message) {
                            errorMessage = xhr.responseJSON.data.message;
                        }
                    } else if (xhr && typeof xhr.responseText === 'string' && xhr.responseText.trim()) {
                        errorMessage = xhr.responseText.trim();
                    }
                    this.addMessage(errorMessage, 'assistant', true);
                }
            });
        }
        
        showTyping() {
            const $typing = $('.aipc-chatbot-typing');
            $typing.show();
            this.scrollToBottom();
        }
        
        hideTyping() {
            const $typing = $('.aipc-chatbot-typing');
            $typing.hide();
        }
        
        scrollToBottom() {
            const $messages = $('.aipc-chatbot-messages');
            $messages.scrollTop($messages[0].scrollHeight);
        }
        
        addMessage(content, role, isError = false) {
            console.log('addMessage called:', content, role, isError);
            
            try {
                const $messages = $('.aipc-chatbot-messages');
                console.log('Messages container found:', $messages.length);
                
                if ($messages.length === 0) {
                    console.log('No messages container found!');
                    return;
                }
                
                const messageClass = isError ? 'aipc-message-error' : '';
                const formatted = this.formatMessageContent(String(content || ''));
                const messageHtml = `
                    <div class="aipc-message aipc-message-${role} aipc-message-new ${messageClass}">
                        <div class="aipc-message-avatar">
                            ${role === 'assistant' ? this.getAssistantIcon() : this.getUserIcon()}
                        </div>
                        <div class="aipc-message-content">
                            <p data-original="${this.escapeHtml(String(content || ''))}">${formatted}</p>
                        </div>
                    </div>
                `;
                
                console.log('Appending message HTML');
                $messages.append(messageHtml);
                console.log('Message appended');
                
                this.scrollToBottom();
                console.log('Scrolled to bottom');
                
                // Save to storage after adding message
                this.saveChatToStorage();
                console.log('Message added and saved to storage');
                
            } catch (error) {
                console.error('Error in addMessage:', error);
            }
        }
        
        // Method for adding HTML messages without escaping
        addHtmlMessage(htmlContent, role, isError = false) {
            console.log('addHtmlMessage called:', htmlContent, role, isError);
            
            try {
                const $messages = $('.aipc-chatbot-messages');
                
                if ($messages.length === 0) {
                    console.log('No messages container found!');
                    return;
                }
                
                const messageClass = isError ? 'aipc-message-error' : '';
                const messageHtml = `
                    <div class="aipc-message aipc-message-${role} aipc-message-new ${messageClass}">
                        <div class="aipc-message-avatar">
                            ${role === 'assistant' ? this.getAssistantIcon() : this.getUserIcon()}
                        </div>
                        <div class="aipc-message-content">
                            ${htmlContent}
                        </div>
                    </div>
                `;
                
                console.log('Appending HTML message');
                $messages.append(messageHtml);
                console.log('HTML message appended');
                
                this.scrollToBottom();
                
                // Save to storage after adding message
                this.saveChatToStorage();
                
            } catch (error) {
                console.error('Error in addHtmlMessage:', error);
            }
        }
        
        showTyping() {
            console.log('showTyping called');
            this.isTyping = true;
            $('.aipc-chatbot-typing').show();
            console.log('Typing indicator shown');
            this.scrollToBottom();
        }
        
        hideTyping() {
            console.log('hideTyping called');
            this.isTyping = false;
            $('.aipc-chatbot-typing').hide();
            console.log('Typing indicator hidden');
        }
        
        sendToServer(message, attempt = 1) {
            console.log('sendToServer called with message:', message);
            console.log('aipc_ajax object:', aipc_ajax);
            
            const data = {
                action: 'aipc_send_message',
                message: message,
                conversation_id: this.conversationId,
                nonce: aipc_ajax.nonce
            };
            if (this.aipcSkinTestActive) {
                data.skin_test_active = 1;
            }
            
            console.log('Sending data:', data);
            // Ensure typing indicator is visible for this path as well
            this.showTyping();
            
            $.ajax({
                url: aipc_ajax.ajax_url,
                type: 'POST',
                data: data,
                timeout: 60000,
                success: (response) => {
                    console.log('AJAX success response:', response);
                    this.hideTyping();
                    
                    if (response.success) {
                        this.addMessage(response.data.message, 'assistant');
                        try {
                            if (response.data && response.data.meta) {
                                const meta = response.data.meta;
                                if (meta.skin_test && typeof meta.skin_test.active !== 'undefined') {
                                    this.aipcSkinTestActive = !!meta.skin_test.active;
                                }
                                if (Array.isArray(meta.quick_replies) && meta.quick_replies.length) {
                                    this.renderQuickReplies(meta.quick_replies, !!(meta.skin_test && meta.skin_test.active));
                                }
                            }
                        } catch (e) { console.log('skin test meta handling error (secondary path)', e); }
                    } else {
                        this.addMessage(response.data.message || aipc_ajax.strings.error, 'assistant', true);
                    }
                },
                error: (xhr, status, error) => {
                    console.log('AJAX error:', xhr, status, error);
                    this.hideTyping();
                    
                    let errorMessage = aipc_ajax.strings.error;
                    
                    if (status === 'timeout') {
                        if (attempt < 2) {
                            console.log('Timeout occurred, retrying once (secondary path)...');
                            this.showTyping();
                            return this.sendToServer(message, attempt + 1);
                        }
                        errorMessage = 'Het duurt te lang om een antwoord te krijgen. Probeer het opnieuw.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    }
                    
                    this.addMessage(errorMessage, 'assistant', true);
                }
            });
        }

        renderQuickReplies(labels, active) {
            try {
                const $messages = $('.aipc-chatbot-messages');
                if (!Array.isArray(labels) || labels.length === 0 || $messages.length === 0) return;
                const $wrap = $('<div class="aipc-quick-replies" />');
                labels.forEach((label) => {
                    const $btn = $('<button type="button" class="aipc-qr" />').text(label);
                    $btn.on('click', (e) => {
                        e.preventDefault();
                        // Mark skin test active for next request if needed
                        if (active) { this.aipcSkinTestActive = true; }
                        this.addMessage(label, 'user');
                        this.sendToServer(label);
                    });
                    $wrap.append($btn);
                });
                $messages.append($wrap);
                this.scrollToBottom();
            } catch (e) { console.log('renderQuickReplies error', e); }
        }
        
        scrollToBottom() {
            const $messages = $('.aipc-chatbot-messages');
            $messages.scrollTop($messages[0].scrollHeight);
        }
        
        autoResizeInput(input) {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 100) + 'px';
        }
        
        getAssistantIcon() {
            // Use the same icon as selected in the admin settings
            if (window.aipc_ajax && aipc_ajax.chatbot_icon) {
                return this.getSvgIcon(aipc_ajax.chatbot_icon, 16);
            }
            // Default robot icon
            return `<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM12 8.5C13.66 8.5 15 9.84 15 11.5V17.5C15 19.16 13.66 20.5 12 20.5S9 19.16 9 17.5V11.5C9 9.84 10.34 8.5 12 8.5ZM7.5 11C7.78 11 8 11.22 8 11.5V13.5C8 13.78 7.78 14 7.5 14S7 13.78 7 13.5V11.5C7 11.22 7.22 11 7.5 11ZM16.5 11C16.78 11 17 11.22 17 11.5V13.5C17 13.78 16.78 14 16.5 14S16 13.78 16 13.5V11.5C16 11.22 16.22 11 16.5 11Z"/>
            </svg>`;
        }
        
        getUserIcon() {
            return `<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 12C14.21 12 16 10.21 16 8C16 5.79 14.21 4 12 4C9.79 4 8 5.79 8 8C8 10.21 9.79 12 12 12ZM12 14C9.33 14 4 15.34 4 18V20H20V18C20 15.34 14.67 14 12 14Z"/>
            </svg>`;
        }
        
        getSvgIcon(key, size = 16) {
            const icons = {
                'robot': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM12 8.5C13.66 8.5 15 9.84 15 11.5V17.5C15 19.16 13.66 20.5 12 20.5S9 19.16 9 17.5V11.5C9 9.84 10.34 8.5 12 8.5ZM7.5 11C7.78 11 8 11.22 8 11.5V13.5C8 13.78 7.78 14 7.5 14S7 13.78 7 13.5V11.5C7 11.22 7.22 11 7.5 11ZM16.5 11C16.78 11 17 11.22 17 11.5V13.5C17 13.78 16.78 14 16.5 14S16 13.78 16 13.5V11.5C16 11.22 16.22 11 16.5 11Z"/></svg>`,
                'chat': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM20 16H5.17L4 17.17V4H20V16Z"/></svg>`,
                'support': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM12 20C7.59 20 4 16.41 4 12S7.59 4 12 4 20 7.59 20 12 16.41 20 12 20ZM12 6C8.69 6 6 8.69 6 12S8.69 18 12 18 18 15.31 18 12 15.31 6 12 6ZM12 8C13.1 8 14 8.9 14 10S13.1 12 12 12 10 11.1 10 10 10.9 8 12 8ZM7 19H9V21H7V19ZM15 19H17V21H15V19Z"/></svg>`,
                'help': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12 17.52 2 12 2ZM13 19H11V17H13V19ZM15.07 11.25L14.17 12.17C13.45 12.9 13 13.5 13 15H11V14.5C11 13.4 11.45 12.4 12.17 11.67L13.41 10.41C13.78 10.05 14 9.55 14 9C14 7.9 13.1 7 12 7S10 7.9 10 9H8C8 6.79 9.79 5 12 5S16 6.79 16 9C16 9.88 15.64 10.67 15.07 11.25Z"/></svg>`,
                'message': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4C2.9 4 2 4.9 2 6V18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V6C22 4.9 21.1 4 20 4ZM20 8L12 13L4 8V6L12 11L20 6V8Z"/></svg>`,
                'phone': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79C8.06 13.62 10.38 15.94 13.21 17.38L15.41 15.18C15.69 14.9 16.08 14.82 16.43 14.93C17.55 15.3 18.75 15.5 20 15.5C20.55 15.5 21 15.95 21 16.5V20C21 20.55 20.55 21 20 21C10.61 21 3 13.39 3 4C3 3.45 3.45 3 4 3H7.5C8.05 3 8.5 3.45 8.5 4C8.5 5.25 8.7 6.45 9.07 7.57C9.18 7.92 9.1 8.31 8.82 8.59L6.62 10.79Z"/></svg>`,
                'star': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>`,
                'heart': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35L10.55 20.03C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3C9.24 3 10.91 3.81 12 5.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5 22 12.28 18.6 15.36 13.45 20.04L12 21.35Z"/></svg>`,
                'lightbulb': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="currentColor"><path d="M9 21C9 21.55 9.45 22 10 22H14C14.55 22 15 21.55 15 21V20H9V21ZM12 2C8.14 2 5 5.14 5 9C5 11.38 6.19 13.47 8 14.74V17C8 17.55 8.45 18 9 18H15C15.55 18 16 17.55 16 17V14.74C17.81 13.47 19 11.38 19 9C19 5.14 15.86 2 12 2Z"/></svg>`,
                'gear': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="currentColor"><path d="M12 15.5C10.07 15.5 8.5 13.93 8.5 12S10.07 8.5 12 8.5 15.5 10.07 15.5 12 13.93 15.5 12 15.5ZM19.43 12.98C19.47 12.66 19.5 12.34 19.5 12S19.47 11.34 19.43 11.02L21.54 9.37C21.73 9.22 21.78 8.95 21.66 8.73L19.66 5.27C19.54 5.05 19.27 4.97 19.05 5.05L16.56 6.05C16.04 5.65 15.48 5.32 14.87 5.07L14.49 2.42C14.46 2.18 14.25 2 14 2H10C9.75 2 9.54 2.18 9.51 2.42L9.13 5.07C8.52 5.32 7.96 5.66 7.44 6.05L4.95 5.05C4.72 4.96 4.46 5.05 4.34 5.27L2.34 8.73C2.21 8.95 2.27 9.22 2.46 9.37L4.57 11.02C4.53 11.34 4.5 11.67 4.5 12S4.53 12.66 4.57 12.98L2.46 14.63C2.27 14.78 2.21 15.05 2.34 15.27L4.34 18.73C4.46 18.95 4.73 19.03 4.95 18.95L7.44 17.95C7.96 18.35 8.52 18.68 9.13 18.93L9.51 21.58C9.54 21.82 9.75 22 10 22H14C14.25 22 14.46 21.82 14.49 21.58L14.87 18.93C15.48 18.68 16.04 18.34 16.56 17.95L19.05 18.95C19.28 19.04 19.54 18.95 19.66 18.73L21.66 15.27C21.78 15.05 21.73 14.78 21.54 14.63L19.43 12.98Z"/></svg>`,
                'shield': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 1ZM10 17L6 13L7.41 11.59L10 14.17L16.59 7.58L18 9L10 17Z"/></svg>`,
                'rocket': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C12 2 21 6.5 21 12C21 12 21 12 21 12H12V2ZM11 4V11H4C4 11 4 11 4 11C4 6.5 11 4 11 4ZM12 13H21C21 17.5 12 22 12 22V13ZM4 13H11V20C11 20 4 17.5 4 13Z"/></svg>`,
                'diamond': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="currentColor"><path d="M6 2L2 8L12 22L22 8L18 2H6ZM6.5 4H8.5L7 7H4L6.5 4ZM11 4H13V7H11V4ZM15.5 4H17.5L20 7H17L15.5 4ZM5 9H8L12 18L5 9ZM10 9H14L12 16L10 9ZM16 9H19L12 18L16 9Z"/></svg>`,
                'crown': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="currentColor"><path d="M5 16L3 5L8.5 10L12 4L15.5 10L21 5L19 16H5ZM19 19C19 19.6 18.6 20 18 20H6C5.4 20 5 19.6 5 19V18H19V19Z"/></svg>`,
                'compass': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12S7.59 4 12 4 20 7.59 20 12 16.41 20 12 20ZM12 7C9.24 7 7 9.24 7 12S9.24 17 12 17 17 14.76 17 12 14.76 7 12 7ZM15.5 8.5L13 11L10.5 13.5L13 11L15.5 8.5Z"/></svg>`,
                'magic': `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="currentColor"><path d="M7.5 5.6L5 7L6.4 4.5L5 2L7.5 3.4L10 2L8.6 4.5L10 7L7.5 5.6ZM19.5 15.4L22 14L20.6 16.5L22 19L19.5 17.6L17 19L18.4 16.5L17 14L19.5 15.4ZM22 2L20.6 4.5L22 7L19.5 5.6L17 7L18.4 4.5L17 2L19.5 3.4L22 2ZM13.34 12.78L15.78 10.34L13.66 8.22L11.22 10.66L13.34 12.78ZM14.37 7.29L16.71 9.63C17.1 10.02 17.1 10.65 16.71 11.04L11.04 16.71C10.65 17.1 10.02 17.1 9.63 16.71L7.29 14.37C6.9 13.98 6.9 13.35 7.29 12.96L12.96 7.29C13.35 6.9 13.98 6.9 14.37 7.29Z"/></svg>`
            };
            
            return icons[key] || icons['robot'];
        }
        
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        getStorageKey() {
            const lang = (window.aipc_ajax && aipc_ajax.lang) ? aipc_ajax.lang : 'default';
            return 'aipc_chat_messages_' + lang;
        }
        

        formatMessageContent(raw) {
            // First check if content contains navigation buttons - if so, don't escape HTML
            if (raw.includes('<button') && raw.includes('aipc-nav-btn')) {
                // Content has navigation buttons - process without escaping HTML first
                let s = raw;
                // Bold **text** (but be careful not to break HTML)
                s = s.replace(/\*\*([^<>]+?)\*\*/g, '<strong>$1</strong>');
                // Inline code `code`
                s = s.replace(/`([^`<>]+)`/g, '<code>$1</code>');
                // Markdown links [text](url) - process before plain URLs to avoid conflicts
                s = s.replace(/\[([^\]]+)\]\(([^\)]+)\)/g, function(match, text, url) {
                    // Validate URL
                    if (url.match(/^https?:\/\//)) {
                        return '<a href="' + url + '" target="_blank" rel="noopener">' + text + '</a>';
                    }
                    return match; // Return original if not a valid URL
                });
                // Autolink plain URLs (avoid double-wrapping existing links)
                s = s.replace(/(^|\s)(https?:\/\/[^\s<]+)(?=\s|$)/g, function(_, pre, url){
                    return pre + '<a href="' + url + '" target="_blank" rel="noopener">' + url + '</a>';
                });
                // Newlines
                s = s.replace(/\n/g, '<br>');
                return s;
            } else {
                // Regular content - escape HTML first
                let s = this.escapeHtml(raw);
                // Bold **text**
                s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                // Inline code `code`
                s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
                // Markdown links [text](url) - process before plain URLs to avoid conflicts
                s = s.replace(/\[([^\]]+)\]\(([^\)]+)\)/g, function(match, text, url) {
                    // Validate URL
                    if (url.match(/^https?:\/\//)) {
                        return '<a href="' + url + '" target="_blank" rel="noopener">' + text + '</a>';
                    }
                    return match; // Return original if not a valid URL
                });
                // Autolink plain URLs (avoid double-wrapping existing links)
                s = s.replace(/(^|\s)(https?:\/\/[^\s<]+)(?=\s|$)/g, function(_, pre, url){
                    // Don't wrap if already inside a link tag
                    return pre + '<a href="' + url + '" target="_blank" rel="noopener">' + url + '</a>';
                });
                // Newlines
                s = s.replace(/\n/g, '<br>');
                return s;
            }
        }
        
        // Public methods for external use
        open() {
            this.openChatbot();
        }
        
        close() {
            this.closeChatbot();
        }
        
        // Public external sender to avoid recursion with internal sendMessage()
        sendExternalMessage(message) {
            if (message && message.trim()) {
                $('.aipc-chatbot-input').val(message);
                this.sendMessage();
            }
        }
        
        // FAQ functionality
        showFAQList() {
            console.log('Showing FAQ list...');
            
            // Show loading message
            this.addMessage('FAQ vragen worden geladen...', 'assistant');
            
            // Make AJAX call to get FAQ list
            $.ajax({
                url: aipc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'aipc_get_faq_list',
                    nonce: aipc_ajax.nonce
                },
                success: (response) => {
                    console.log('FAQ AJAX success:', response);
                    
                    if (response && response.success && response.data.faqs) {
                        this.currentFAQs = response.data.faqs;
                        this.renderFAQList(response.data.faqs);
                    } else {
                        const errorMsg = response.data.message || 'Geen FAQ items gevonden.';
                        this.addMessage(errorMsg, 'assistant');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('FAQ AJAX error:', xhr, status, error);
                    this.addMessage('Sorry, er is een fout opgetreden bij het laden van de FAQ.', 'assistant', true);
                }
            });
        }
        
        renderFAQList(faqs) {
            if (!faqs || faqs.length === 0) {
                this.addMessage('Geen FAQ items beschikbaar.', 'assistant');
                return;
            }
            
            console.log('Rendering FAQ list with', faqs.length, 'items');
            
            // Create FAQ message with options
            const $messages = $('.aipc-chatbot-messages');
            const faqHtml = `
                <div class="aipc-message aipc-message-assistant aipc-message-new">
                    <div class="aipc-message-avatar">
                        ${this.getAssistantIcon()}
                    </div>
                    <div class="aipc-message-content">
                        <p><strong>Veelgestelde vragen:</strong></p>
                        <div class="aipc-faq-options">
                            ${faqs.map((faq, index) => `
                                <button class="aipc-faq-option" data-faq-index="${index}">
                                    ${this.escapeHtml(faq.question)}
                                </button>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `;
            
            $messages.append(faqHtml);
            this.scrollToBottom();
            this.saveChatToStorage();
        }
        
        showFAQAnswer(question, answer) {
            console.log('Showing FAQ answer for:', question);
            
            // Add user's question
            this.addMessage(question, 'user');
            
            // Add answer with back button using HTML method
            const answerWithBackButton = `
                ${answer}
                <br><br>
                <button class="aipc-nav-btn aipc-back-to-faq">Terug naar FAQ</button>
            `;
            
            this.addHtmlMessage(answerWithBackButton, 'assistant');
        }
        
        // Quiz functionality
        startQuiz() {
            console.log('Starting quiz...');
            
            // Add user message
            this.addMessage('Start Product Quiz', 'user');
            
            // Send quiz start command to server
            this.sendToServer('product quiz');
        }
    }
    
    // Initialize chatbot with delay to avoid Elementor conflicts
    console.log('Creating AIPCChatbot instance...');
    
    // Wait for Elementor to load
    setTimeout(() => {
        window.aipcChatbot = new AIPCChatbot();
        console.log('AIPCChatbot instance created:', window.aipcChatbot);
    }, 2000);
    
    // Expose global methods
    window.openAIPCChatbot = () => window.aipcChatbot.open();
    window.closeAIPCChatbot = () => window.aipcChatbot.close();
    window.sendAIPCMessage = (message) => window.aipcChatbot && window.aipcChatbot.sendExternalMessage(message);
    
    // Test function
    window.testAIPCChatbot = () => {
        console.log('Testing AIPCChatbot...');
        console.log('Chatbot instance:', window.aipcChatbot);
        console.log('Send buttons:', $('.aipc-chatbot-send').length);
        console.log('Input fields:', $('.aipc-chatbot-input').length);
        
        // Test sending a message
        if (window.aipcChatbot) {
            window.aipcChatbot.addMessage('Test bericht', 'user');
            console.log('Test message added');
        }
    };
    
    // Simple test function
    window.testSendMessage = () => {
        console.log('Testing sendMessage directly...');
        if (window.aipcChatbot) {
            // Set a test message in the input
            $('.aipc-chatbot-input').val('Test bericht');
            console.log('Test message set in input');
            // Call sendMessage
            window.aipcChatbot.sendMessage();
        } else {
            console.log('AIPCChatbot not found!');
        }
    };
    
    // Direct test function
    window.testDirect = () => {
        console.log('Testing direct message addition...');
        if (window.aipcChatbot) {
            window.aipcChatbot.addMessage('Direct test message', 'user');
            window.aipcChatbot.addMessage('Direct test response', 'assistant');
        } else {
            console.log('AIPCChatbot not found!');
        }
    };
    
    // Reset chat history function
    window.resetChat = () => {
        console.log('Resetting chat history...');
        if (window.aipcChatbot) {
            window.aipcChatbot.resetChatHistory();
            console.log('Chat history reset complete');
        } else {
            console.log('AIPCChatbot not found!');
        }
    };
    
    // Clean chat history function
    window.cleanChat = () => {
        console.log('Cleaning chat history...');
        if (window.aipcChatbot) {
            window.aipcChatbot.cleanChatHistory();
            console.log('Chat history cleaned');
        } else {
            console.log('AIPCChatbot not found!');
        }
    };
    
    // Debug chat history function
    window.debugChat = () => {
        console.log('Debugging chat history...');
        if (window.aipcChatbot) {
            console.log('Current chat history:', window.aipcChatbot.chatHistory);
            console.log('LocalStorage:', localStorage.getItem('aipc_chat_history'));
            console.log('Visible messages:', $('.aipc-chatbot-messages .aipc-message').length);
        } else {
            console.log('AIPCChatbot not found!');
        }
    };
    
    // Force open chatbot function
    window.openChatbot = () => {
        console.log('Force opening chatbot...');
        if (window.aipcChatbot) {
            window.aipcChatbot.openChatbot();
            console.log('Chatbot opened');
        } else {
            console.log('AIPCChatbot not found!');
        }
    };
    
    // Test message function
    window.testMessage = () => {
        console.log('Testing message...');
        if (window.aipcChatbot) {
            window.aipcChatbot.addMessage('Test bericht', 'user');
            console.log('Test message added');
        } else {
            console.log('AIPCChatbot not found!');
        }
    };
    
    // Test sendMessage function directly
    window.testSendMessageDirect = () => {
        console.log('Testing sendMessage function directly...');
        if (window.aipcChatbot) {
            // Set a test message in the input
            $('.aipc-chatbot-input').val('Test bericht');
            console.log('Test message set in input');
            // Call sendMessage directly
            window.aipcChatbot.sendMessage();
        } else {
            console.log('AIPCChatbot not found!');
        }
    };
    
    // Simple test function
    window.testSimple = () => {
        console.log('Testing simple message addition...');
        const $messages = $('.aipc-chatbot-messages');
        console.log('Messages container found:', $messages.length);
        
        if ($messages.length > 0) {
            // Add a very simple message first
            $messages.append('<div style="background: red; color: white; padding: 10px; margin: 10px;">TEST MESSAGE - This should be visible!</div>');
            console.log('Simple test message added');
            
            // Then add the proper message
            const messageHtml = '<div class="aipc-message aipc-message-user"><div class="aipc-message-avatar">👤</div><div class="aipc-message-content"><p>Simple test message</p></div></div>';
            $messages.append(messageHtml);
            console.log('Styled message added');
            
            console.log('Messages container HTML length:', $messages.html().length);
            console.log('Messages container height:', $messages.height());
            console.log('Messages container scroll height:', $messages[0].scrollHeight);
        } else {
            console.log('No messages container found');
        }
    };
    
    // Direct test function
    window.testDirect = () => {
        console.log('Testing direct message addition...');
        const $messages = $('.aipc-chatbot-messages');
        console.log('Messages container found:', $messages.length);
        
        if ($messages.length > 0) {
            $messages.append('<div style="background: blue; color: white; padding: 10px; margin: 10px;">DIRECT TEST MESSAGE</div>');
            console.log('Direct test message added');
        } else {
            console.log('No messages container found');
        }
    };
    
    // Test sendMessage function directly
    window.testSendMessage = () => {
        console.log('Testing sendMessage function directly...');
        console.log('jQuery available:', typeof $ !== 'undefined');
        console.log('AIPCChatbot available:', typeof window.aipcChatbot !== 'undefined');
        
        if (window.aipcChatbot) {
            console.log('About to call sendMessage...');
            try {
                window.aipcChatbot.sendMessage();
                console.log('sendMessage call completed');
            } catch (error) {
                console.error('Error calling sendMessage:', error);
                console.error('Error stack:', error.stack);
            }
        } else {
            console.log('AIPCChatbot not found!');
        }
    };
    
    // Simple test function
    window.testSimple = () => {
        console.log('Testing simple message addition...');
        const $messages = $('.aipc-chatbot-messages');
        console.log('Messages container found:', $messages.length);
        
        if ($messages.length > 0) {
            $messages.append('<div style="background: green; color: white; padding: 10px; margin: 10px;">SIMPLE TEST MESSAGE</div>');
            console.log('Simple test message added');
        } else {
            console.log('No messages container found');
        }
    };
    
    // Direct test function
    window.testDirect = () => {
        console.log('Testing direct message addition...');
        const $messages = $('.aipc-chatbot-messages');
        console.log('Messages container found:', $messages.length);
        
        if ($messages.length > 0) {
            $messages.append('<div style="background: blue; color: white; padding: 10px; margin: 10px;">DIRECT TEST MESSAGE</div>');
            console.log('Direct test message added');
        } else {
            console.log('No messages container found');
        }
    };
    
    // Test sendMessage function directly without class
    window.testSendMessageDirect = () => {
        console.log('Testing sendMessage function directly...');
        
        try {
            // Get input element
            const $input = $('.aipc-chatbot-input');
            console.log('Input element found:', $input.length);
            
            // Get message
            const message = $input.val().trim();
            console.log('Message:', message);
            
            // Get messages container
            const $messages = $('.aipc-chatbot-messages');
            console.log('Messages container found:', $messages.length);
            
            // Add test message
            $messages.append('<div style="background: red; color: white; padding: 10px; margin: 10px;">DIRECT TEST MESSAGE</div>');
            console.log('Direct test message added');
            
            console.log('Direct sendMessage completed');
        } catch (error) {
            console.error('Error in direct sendMessage:', error);
            console.error('Error stack:', error.stack);
        }
    };
    
    // Auto-open chatbot on page load (optional)
    // Uncomment the line below if you want the chatbot to open automatically
    // setTimeout(() => window.aipcChatbot.open(), 2000);
    
    // Show chatbot toggle button by default
    $('.aipc-chatbot').show();
    
    // Additional event binding after DOM is ready with delay for Elementor
    $(document).ready(function() {
        console.log('DOM ready, setting up additional event bindings...');
        
        // Wait for Elementor to fully load
        setTimeout(() => {
            // Direct event binding for send button
            $(document).off('click', '.aipc-chatbot-send').on('click', '.aipc-chatbot-send', function(e) {
                console.log('Direct send button click event');
                e.preventDefault();
                e.stopPropagation();
                if (window.aipcChatbot) {
                    console.log('About to call sendMessage() from direct event');
                    window.aipcChatbot.sendMessage();
                    console.log('sendMessage() called from direct event');
                } else {
                    console.log('AIPCChatbot instance not found!');
                }
            });
            
            // Test if elements exist
            console.log('Send buttons found:', $('.aipc-chatbot-send').length);
            console.log('Input fields found:', $('.aipc-chatbot-input').length);
            console.log('Chatbot elements found:', $('.aipc-chatbot').length);
            // Apply localized UI strings
            if (window.aipcChatbot && typeof window.aipcChatbot.applyLocalizedStrings === 'function') {
                window.aipcChatbot.applyLocalizedStrings();
            }
        }, 2500);
    });
    
    // Handle page visibility changes
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Page is hidden, you could pause chatbot or save state
        } else {
            // Page is visible again
        }
    });
    
    // Handle window resize
    $(window).on('resize', function() {
        if (window.aipcChatbot && window.aipcChatbot.isOpen) {
            window.aipcChatbot.scrollToBottom();
        }
    });
    
    // Add some helpful console messages for developers
    if (window.console && window.console.log) {
        console.log('AI Product Chatbot loaded successfully!');
        console.log('Available methods: openAIPCChatbot(), closeAIPCChatbot(), sendAIPCMessage(message)');
    }
});
