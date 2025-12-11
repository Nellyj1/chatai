jQuery(document).ready(function($) {
    'use strict';
    
    // Admin functionality for AI Product Chatbot
    
    // Initialize admin features
    initAdminFeatures();

    // Revalidate license now (grace-mode notice button)
    $(document).on('click', '.aipc-force-validate-license', function() {
        const $btn = $(this);
        const original = $btn.text();
        $btn.prop('disabled', true).text('Valideren...');
        $.post(ajaxurl, {
            action: 'aipc_force_validate_license',
            nonce: $btn.data('nonce') || (window.aipc_admin ? aipc_admin.nonce : '')
        }).done(function(res) {
            if (res && res.success) {
                aipcAdmin.showNotice(res.data.message || 'Licentie opnieuw gevalideerd.', 'success');
                setTimeout(function(){ location.reload(); }, 800);
            } else {
                const msg = (res && res.data && res.data.message) ? res.data.message : 'Kon niet valideren.';
                aipcAdmin.showNotice(msg, 'error');
            }
        }).fail(function(){
            aipcAdmin.showNotice('Netwerkfout tijdens valideren.', 'error');
        }).always(function(){
            $btn.prop('disabled', false).text(original);
        });
    });
    
    function initAdminFeatures() {
        // Initialize tooltips
        initTooltips();
        
        // Initialize form validation
        initFormValidation();
        
        // Initialize dynamic rows
        initDynamicRows();
        
        // Initialize API testing
        initAPITesting();
        
        // Initialize data tables
        initDataTables();
    }
    
    function initTooltips() {
        // Add tooltips to help text
        $('.aipc-help-text').each(function() {
            const $this = $(this);
            const helpText = $this.data('help');
            if (helpText) {
                $this.attr('title', helpText);
            }
        });
    }
    
    function initFormValidation() {
        // Validate required fields
        $('form').on('submit', function(e) {
            const $form = $(this);
            let isValid = true;
            
            $form.find('[required]').each(function() {
                const $field = $(this);
                if (!$field.val().trim()) {
                    $field.addClass('error');
                    isValid = false;
                } else {
                    $field.removeClass('error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showNotice('Vul alle verplichte velden in.', 'error');
            }
        });
        
        // Real-time validation
        $('[required]').on('blur', function() {
            const $field = $(this);
            if (!$field.val().trim()) {
                $field.addClass('error');
            } else {
                $field.removeClass('error');
            }
        });
    }
    
    function initDynamicRows() {
        // Add ingredient row
        $('#add-ingredient').on('click', function() {
            addDynamicRow('ingredients', 'product_ingredients[]', 'Ingrediënt naam');
        });
        
        // Add skin type row
        $('#add-skin-type').on('click', function() {
            addDynamicRow('skin-types', 'product_skin_types[]', 'Huidtype');
        });
        
        // Add benefit row
        $('#add-benefit').on('click', function() {
            addDynamicRow('benefits', 'ingredient_benefits[]', 'Voordeel');
        });
        
        // Remove dynamic rows
        $(document).on('click', '.remove-ingredient, .remove-skin-type, .remove-benefit', function() {
            $(this).closest('.ingredient-row, .skin-type-row, .benefit-row').fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    function addDynamicRow(containerId, fieldName, placeholder) {
        const container = '#' + containerId + '-container';
        const rowClass = containerId.replace('-', '-') + '-row';
        
        const rowHtml = `
            <div class="${rowClass}">
                <input type="text" name="${fieldName}" class="regular-text" placeholder="${placeholder}" />
                <button type="button" class="button remove-${containerId.replace('-', '-')}">Verwijderen</button>
            </div>
        `;
        
        $(container).append(rowHtml);
        $(container + ' .' + rowClass).last().hide().fadeIn(300);
    }
    
    function initAPITesting() {
        $('#aipc-test-api').on('click', function() {
            testAPIConnection();
        });
    }
    
    function testAPIConnection() {
        const $button = $('#aipc-test-api');
        const $result = $('#aipc-test-result');
        
        $button.prop('disabled', true).html('<span class="aipc-loading"></span> Testen...');
        $result.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aipc_test_api',
                nonce: aipc_admin.nonce
            },
            timeout: 30000,
            success: function(response) {
                if (response.success) {
                    showResult('success', '✅ ' + response.data.message);
                } else {
                    showResult('error', '❌ ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                let message = 'Fout bij testen van API verbinding';
                if (status === 'timeout') {
                    message = 'Timeout: API reageert niet binnen 30 seconden';
                } else if (xhr.responseJSON && xhr.responseJSON.data) {
                    message = xhr.responseJSON.data.message || message;
                }
                showResult('error', '❌ ' + message);
            },
            complete: function() {
                $button.prop('disabled', false).text('Test API Verbinding');
            }
        });
    }
    
    function showResult(type, message) {
        const $result = $('#aipc-test-result');
        const alertClass = type === 'success' ? 'notice-success' : 'notice-error';
        $result.html(`<div class="notice ${alertClass} inline"><p>${message}</p></div>`);
    }
    
    function initDataTables() {
        // Initialize sortable tables if DataTables is available
        if ($.fn.DataTable) {
            $('.aipc-table').DataTable({
                pageLength: 25,
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Dutch.json'
                }
            });
        }
    }
    
    function showNotice(message, type = 'info') {
        const noticeClass = 'notice-' + type;
        const $notice = $(`
            <div class="notice ${noticeClass} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Sluit deze melding.</span>
                </button>
            </div>
        `);
        
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Global functions for external use
    window.aipcAdmin = {
        showNotice: showNotice,
        testAPI: testAPIConnection,
        addDynamicRow: addDynamicRow
    };
    
    // Handle notice dismissal
    $(document).on('click', '.notice-dismiss', function() {
        $(this).closest('.notice').fadeOut(300, function() {
            $(this).remove();
        });
    });
    
    // Auto-save functionality for forms
    let autoSaveTimeout;
    $('form input, form textarea, form select').on('input change', function() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(function() {
            // Auto-save logic could be implemented here
            console.log('Auto-save triggered');
        }, 2000);
    });
    
    // Confirmation dialogs
    $('.aipc-confirm-delete').on('click', function(e) {
        if (!confirm('Weet je zeker dat je dit item wilt verwijderen? Deze actie kan niet ongedaan worden gemaakt.')) {
            e.preventDefault();
        }
    });
    
    // Bulk actions
    $('#aipc-bulk-action').on('change', function() {
        const action = $(this).val();
        if (action) {
            $('#aipc-bulk-submit').prop('disabled', false);
        } else {
            $('#aipc-bulk-submit').prop('disabled', true);
        }
    });
    
    // Select all functionality
    $('#aipc-select-all').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.aipc-item-checkbox').prop('checked', isChecked);
        updateBulkActions();
    });
    
    $('.aipc-item-checkbox').on('change', function() {
        updateBulkActions();
    });
    
    function updateBulkActions() {
        const checkedCount = $('.aipc-item-checkbox:checked').length;
        const totalCount = $('.aipc-item-checkbox').length;
        
        $('#aipc-select-all').prop('indeterminate', checkedCount > 0 && checkedCount < totalCount);
        $('#aipc-select-all').prop('checked', checkedCount === totalCount);
        
        if (checkedCount > 0) {
            $('#aipc-bulk-submit').prop('disabled', false);
            $('#aipc-selected-count').text(`${checkedCount} items geselecteerd`);
        } else {
            $('#aipc-bulk-submit').prop('disabled', true);
            $('#aipc-selected-count').text('');
        }
    }
    
    // Search functionality
    $('#aipc-search').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('.aipc-table tbody tr').each(function() {
            const rowText = $(this).text().toLowerCase();
            if (rowText.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Export functionality
    $('#aipc-export').on('click', function() {
        const format = $(this).data('format');
        exportData(format);
    });
    
    function exportData(format) {
        // Export logic would be implemented here
        showNotice(`Export naar ${format} wordt voorbereid...`, 'info');
    }
    
    // Import functionality
    $('#aipc-import-file').on('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // Import logic would be implemented here
                showNotice('Bestand geüpload. Import wordt verwerkt...', 'info');
            };
            reader.readAsText(file);
        }
    });
    
    // Real-time preview for chatbot settings
    $('#aipc_chatbot_title, #aipc_chatbot_welcome_message').on('input', function() {
        updateChatbotPreview();
    });
    
    function updateChatbotPreview() {
        const title = $('#aipc_chatbot_title').val() || 'AI Product Assistant';
        const welcomeMessage = $('#aipc_chatbot_welcome_message').val() || 'Hallo! Ik ben je AI product assistant.';
        
        $('#aipc-preview-title').text(title);
        $('#aipc-preview-welcome').text(welcomeMessage);
    }
    
    // Initialize preview on page load
    updateChatbotPreview();
    
    // Theme preview
    $('#aipc_chatbot_theme').on('change', function() {
        const theme = $(this).val();
        $('#aipc-preview-chatbot').removeClass().addClass('aipc-theme-' + theme);
    });
    
    // Position preview
    $('#aipc_chatbot_position').on('change', function() {
        const position = $(this).val();
        $('#aipc-preview-chatbot').removeClass().addClass('aipc-position-' + position);
    });
    
    console.log('AI Product Chatbot Admin loaded successfully!');
});
