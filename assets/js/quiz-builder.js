/**
 * Visual Quiz Builder for AI Product Chatbot
 * Drag-and-drop interface for creating product quizzes
 */

class QuizBuilder {
    constructor() {
        console.log('🎨 QUIZ BUILDER: Constructor called');
        this.blocks = new Map();
        this.connections = new Map();
        this.selectedBlock = null;
        this.draggedBlock = null;
        this.blockCounter = 0;
        
        // Canvas and UI elements
        this.canvas = document.getElementById('quiz-blocks-container');
        this.canvasContent = document.getElementById('canvas-content');
        this.canvasContainer = document.getElementById('quiz-canvas');
        this.propertiesPanel = document.getElementById('properties-content');
        this.connectionsSvg = document.getElementById('connection-lines');
        
        // Zoom and pan state
        this.zoomLevel = 1;
        this.panX = 0;
        this.panY = 0;
        this.isPanning = false;
        this.lastPanPoint = { x: 0, y: 0 };
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.setupCanvas();
        this.createArrowMarker();
        
        // Initialize transform
        this.updateTransform();
        
        // Load existing data if any
        this.loadExistingData();
        
        // Restore viewport state if recently saved (after page reload from save)
        setTimeout(() => {
            this.restoreViewportState();
        }, 100);
    }
    
    setupEventListeners() {
        // Block creation buttons
        document.querySelectorAll('.quiz-block-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const type = e.currentTarget.dataset.type;
                this.addBlock(type);
            });
        });
        
        // Action buttons
        document.getElementById('quiz-test-btn').addEventListener('click', () => {
            this.testQuiz();
        });
        
        document.getElementById('quiz-clear-btn').addEventListener('click', () => {
            this.clearAll();
        });
        
        // Save form
        document.getElementById('quiz-save-form').addEventListener('submit', (e) => {
            this.saveQuizData();
        });
        
        // Debug button
        if (document.getElementById('debug-data-btn')) {
            document.getElementById('debug-data-btn').addEventListener('click', () => {
                this.showDebugInfo();
            });
        }
        
        // Export button
        if (document.getElementById('export-quiz-btn')) {
            document.getElementById('export-quiz-btn').addEventListener('click', () => {
                this.exportQuiz();
            });
        }
        
        // Import button
        if (document.getElementById('import-quiz-btn')) {
            document.getElementById('import-quiz-btn').addEventListener('click', () => {
                this.importQuiz();
            });
        }
        
        // Canvas events
        this.canvas.addEventListener('click', (e) => {
            if (e.target === this.canvas) {
                this.deselectBlock();
            }
        });
        
        // Modal events
        document.querySelector('.quiz-modal-close').addEventListener('click', () => {
            document.getElementById('quiz-test-modal').style.display = 'none';
        });
        
        // Zoom and pan events
        this.setupZoomAndPan();
    }
    
    setupCanvas() {
        // Make canvas droppable
        this.canvas.addEventListener('dragover', (e) => {
            e.preventDefault();
        });
        
        this.canvas.addEventListener('drop', (e) => {
            e.preventDefault();
            // Handle drop if needed
        });
    }
    
    setupZoomAndPan() {
        // Zoom controls
        document.getElementById('zoom-in-btn').addEventListener('click', () => {
            this.zoomToCenter(this.zoomLevel * 1.05); // Langzamer: 1.1 -> 1.05
        });
        
        document.getElementById('zoom-out-btn').addEventListener('click', () => {
            this.zoomToCenter(this.zoomLevel / 1.05); // Langzamer: 1.1 -> 1.05
        });
        
        document.getElementById('zoom-fit-btn').addEventListener('click', () => {
            this.zoomToFit();
        });
        
        // Mouse wheel zoom
        this.canvasContainer.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? 0.98 : 1.02; // Langzamer: 0.95/1.05 -> 0.98/1.02
            
            // Get mouse position relative to canvas container
            const rect = this.canvasContainer.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;
            
            this.zoomToPoint(this.zoomLevel * delta, mouseX, mouseY);
        });
        
        // Pan functionality
        this.canvasContainer.addEventListener('mousedown', (e) => {
            // Only pan if not clicking on a block or UI element
            const isBlock = e.target.closest('.quiz-block');
            const isUI = e.target.closest('button, input, select, textarea');
            
            if (!isBlock && !isUI) {
                this.isPanning = true;
                this.lastPanPoint = { x: e.clientX, y: e.clientY };
                this.canvasContainer.classList.add('panning');
                e.preventDefault();
            }
        });
        
        document.addEventListener('mousemove', (e) => {
            if (this.isPanning) {
                const deltaX = e.clientX - this.lastPanPoint.x;
                const deltaY = e.clientY - this.lastPanPoint.y;
                
                this.panX += deltaX;
                this.panY += deltaY;
                
                this.updateTransform();
                
                this.lastPanPoint = { x: e.clientX, y: e.clientY };
            }
        });
        
        document.addEventListener('mouseup', () => {
            if (this.isPanning) {
                this.isPanning = false;
                this.canvasContainer.classList.remove('panning');
            }
        });
    }
    
    createArrowMarker() {
        // Create SVG arrow marker for connection lines
        const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        const marker = document.createElementNS('http://www.w3.org/2000/svg', 'marker');
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        
        marker.setAttribute('id', 'arrowhead');
        marker.setAttribute('markerWidth', '10');
        marker.setAttribute('markerHeight', '7');
        marker.setAttribute('refX', '9');
        marker.setAttribute('refY', '3.5');
        marker.setAttribute('orient', 'auto');
        
        path.setAttribute('d', 'M 0 0 L 10 3.5 L 0 7 z');
        path.setAttribute('fill', '#2271b1');
        
        marker.appendChild(path);
        defs.appendChild(marker);
        this.connectionsSvg.appendChild(defs);
    }
    
    addBlock(type, data = null, parentBlockId = null) {
        const blockId = data?.id || `block_${++this.blockCounter}`;
        
        // Update counter if loading existing block with higher ID
        if (data?.id) {
            const idNum = parseInt(data.id.replace('block_', ''));
            if (!isNaN(idNum) && idNum > this.blockCounter) {
                this.blockCounter = idNum;
            }
        }
        
        const blockElement = this.createBlockElement(type, blockId, data);
        
        // Position new blocks based on context
        if (!data || !data.position) {
            if (parentBlockId) {
                // Position relative to parent block (for quick-add)
                this.positionBlockRelativeToParent(blockElement, parentBlockId);
            } else {
                // Position in center of current viewport (for toolbar clicks)
                this.positionBlockInViewport(blockElement);
            }
        }
        
        this.canvas.appendChild(blockElement);
        this.makeDraggable(blockElement);
        
        // Store block data
        const blockData = data || this.getDefaultBlockData(type);
        blockData.id = blockId;
        blockData.type = type;
        this.blocks.set(blockId, blockData);
        
        console.log(`Added block ${blockId}, counter now at ${this.blockCounter}`);
        
        // Update validation for new block
        this.updateBlockValidation(blockId, blockData);
        
        return blockElement;
    }
    
    quickAddBlock(type, parentBlockId) {
        const newBlockElement = this.addBlock(type, null, parentBlockId);
        const newBlockId = newBlockElement.dataset.blockId;
        
        // Auto-connect if parent is a question block
        const parentBlockData = this.blocks.get(parentBlockId);
        if (parentBlockData && parentBlockData.type === 'question') {
            // Find the first available option without a connection
            const connections = parentBlockData.connections || {};
            const options = parentBlockData.options || [];
            
            for (let i = 0; i < options.length; i++) {
                if (!connections[i]) {
                    // Connect this option to the new block
                    connections[i] = newBlockId;
                    parentBlockData.connections = connections;
                    this.blocks.set(parentBlockId, parentBlockData);
                    
                    console.log(`Auto-connected option ${i} of ${parentBlockId} to ${newBlockId}`);
                    break;
                }
            }
            
            // Update connections visual
            this.updateConnections();
        }
        
        // Select the new block
        this.selectBlock(newBlockId);
        
        return newBlockElement;
    }
    
    positionBlockRelativeToParent(blockElement, parentBlockId) {
        const parentElement = document.querySelector(`[data-block-id="${parentBlockId}"]`);
        if (!parentElement) {
            // Fallback to viewport positioning if parent not found
            this.positionBlockInViewport(blockElement);
            return;
        }
        
        const parentX = parseInt(parentElement.style.left) || 0;
        const parentY = parseInt(parentElement.style.top) || 0;
        const parentHeight = parentElement.offsetHeight || 150;
        
        // Position below and slightly to the right of parent
        const offsetX = 20;
        const offsetY = parentHeight + 30;
        
        blockElement.style.left = (parentX + offsetX) + 'px';
        blockElement.style.top = (parentY + offsetY) + 'px';
    }
    
    positionBlockInViewport(blockElement) {
        // Get current viewport center in canvas coordinates
        const canvasRect = this.canvasContainer.getBoundingClientRect();
        const centerViewportX = canvasRect.width / 2;
        const centerViewportY = canvasRect.height / 2;
        
        // Convert viewport coordinates to canvas coordinates
        const canvasX = (centerViewportX - this.panX) / this.zoomLevel;
        const canvasY = (centerViewportY - this.panY) / this.zoomLevel;
        
        // Add some randomization to avoid stacking
        const randomOffset = 30;
        const offsetX = (Math.random() - 0.5) * randomOffset;
        const offsetY = (Math.random() - 0.5) * randomOffset;
        
        blockElement.style.left = Math.max(0, canvasX + offsetX - 100) + 'px'; // -100 to center the block
        blockElement.style.top = Math.max(0, canvasY + offsetY - 75) + 'px'; // -75 to center the block
    }
    
    createBlockElement(type, blockId, data) {
        const block = document.createElement('div');
        block.className = `quiz-block ${type}-block`;
        block.dataset.blockId = blockId;
        block.dataset.blockType = type;
        
        if (data?.position) {
            block.style.left = data.position.x + 'px';
            block.style.top = data.position.y + 'px';
        }
        
        const header = document.createElement('div');
        header.className = 'block-header';
        
        const icon = document.createElement('span');
        icon.className = `dashicons block-icon ${type === 'question' ? 'dashicons-editor-help' : 'dashicons-flag'}`;
        
        const title = document.createElement('span');
        title.className = 'block-title';
        title.textContent = data?.title || (type === 'question' ? 'Vraag' : 'Uitslag');
        
        const idSpan = document.createElement('span');
        idSpan.className = 'block-id';
        idSpan.textContent = `(${blockId})`;
        idSpan.style.cssText = 'font-size: 11px; color: #666; margin-left: 6px; font-weight: normal;';
        
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'block-delete';
        deleteBtn.innerHTML = '×';
        deleteBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.deleteBlock(blockId);
        });
        
        header.appendChild(icon);
        header.appendChild(title);
        header.appendChild(idSpan);
        header.appendChild(deleteBtn);
        
        // Quick Add buttons container
        const quickAddContainer = document.createElement('div');
        quickAddContainer.className = 'quick-add-buttons';
        quickAddContainer.innerHTML = `
            <button type="button" class="quick-add-btn add-question" title="Voeg vraag toe">
                <span class="dashicons dashicons-editor-help"></span>
            </button>
            <button type="button" class="quick-add-btn add-result" title="Voeg uitslag toe">
                <span class="dashicons dashicons-flag"></span>
            </button>
        `;
        
        // Add quick-add functionality
        quickAddContainer.querySelector('.add-question').addEventListener('click', (e) => {
            e.stopPropagation();
            this.quickAddBlock('question', blockId);
        });
        
        quickAddContainer.querySelector('.add-result').addEventListener('click', (e) => {
            e.stopPropagation();
            this.quickAddBlock('result', blockId);
        });
        
        const content = document.createElement('div');
        content.className = 'block-content';
        
        if (type === 'question') {
            this.populateQuestionContent(content, data);
        } else {
            this.populateResultContent(content, data);
        }
        
        block.appendChild(header);
        block.appendChild(content);
        block.appendChild(quickAddContainer);
        
        // Click to select
        block.addEventListener('click', (e) => {
            e.stopPropagation();
            this.selectBlock(blockId);
        });
        
        return block;
    }
    
    populateQuestionContent(content, data) {
        // Clear existing content first
        content.innerHTML = '';
        
        const question = document.createElement('div');
        question.className = 'block-question';
        question.textContent = data?.question || 'Vraag tekst...';
        
        const options = document.createElement('ul');
        options.className = 'block-options';
        
        const optionsList = data?.options || ['Optie 1', 'Optie 2'];
        optionsList.forEach(option => {
            const li = document.createElement('li');
            li.textContent = option;
            options.appendChild(li);
        });
        
        content.appendChild(question);
        content.appendChild(options);
    }
    
    populateResultContent(content, data) {
        // Clear existing content first
        content.innerHTML = '';
        
        const label = document.createElement('div');
        label.className = 'block-question';
        label.textContent = data?.label || 'Uitslag label...';
        
        const summary = document.createElement('div');
        summary.textContent = data?.summary || 'Uitslag beschrijving...';
        
        const products = document.createElement('div');
        products.style.marginTop = '8px';
        products.style.fontSize = '12px';
        const productCount = data?.products?.length || 0;
        
        // Style based on whether products are added
        if (productCount === 0) {
            products.style.color = '#d63638';
            products.style.fontWeight = 'bold';
            products.textContent = '⚠️ Geen producten geselecteerd';
        } else {
            products.style.color = '#00a32a';
            products.style.fontWeight = 'normal';
            products.textContent = `✅ ${productCount} product(en) geselecteerd`;
        }
        
        content.appendChild(label);
        content.appendChild(summary);
        content.appendChild(products);
    }
    
    makeDraggable(element) {
        let isDragging = false;
        let dragStartMousePos = { x: 0, y: 0 };
        let dragStartElementPos = { x: 0, y: 0 };
        
        element.addEventListener('mousedown', (e) => {
            if (e.target.classList.contains('block-delete')) return;
            
            if (e.target === element || element.contains(e.target)) {
                isDragging = true;
                element.style.cursor = 'grabbing';
                
                // Store initial positions
                dragStartMousePos = { x: e.clientX, y: e.clientY };
                dragStartElementPos = {
                    x: parseInt(element.style.left) || 0,
                    y: parseInt(element.style.top) || 0
                };
                
                // Clear connections immediately when starting to drag to prevent traces
                this.connectionsSvg.querySelectorAll('.connection-line, .connection-label, .connection-label-bg').forEach(el => {
                    el.remove();
                });
                
                e.preventDefault();
            }
        });
        
        document.addEventListener('mousemove', (e) => {
            if (isDragging) {
                e.preventDefault();
                
                // Calculate mouse movement
                const mouseDeltaX = e.clientX - dragStartMousePos.x;
                const mouseDeltaY = e.clientY - dragStartMousePos.y;
                
                // Apply zoom factor to mouse delta (inverted because we scale up the movement)
                const scaledDeltaX = mouseDeltaX / this.zoomLevel;
                const scaledDeltaY = mouseDeltaY / this.zoomLevel;
                
                // Calculate new position
                const newX = dragStartElementPos.x + scaledDeltaX;
                const newY = dragStartElementPos.y + scaledDeltaY;
                
                // Apply bounds checking (optional, can be removed for unlimited canvas)
                const canvasWidth = 8000; // Match the CSS canvas content width
                const canvasHeight = 5000; // Match the CSS canvas content height
                
                const boundedX = Math.max(0, Math.min(newX, canvasWidth - element.offsetWidth));
                const boundedY = Math.max(0, Math.min(newY, canvasHeight - element.offsetHeight));
                
                element.style.left = boundedX + 'px';
                element.style.top = boundedY + 'px';
                
                this.updateConnections();
            }
        });
        
        document.addEventListener('mouseup', () => {
            if (isDragging) {
                isDragging = false;
                element.style.cursor = 'move';
                
                // Save position to block data
                const blockId = element.dataset.blockId;
                if (this.blocks.has(blockId)) {
                    const blockData = this.blocks.get(blockId);
                    blockData.position = {
                        x: parseInt(element.style.left),
                        y: parseInt(element.style.top)
                    };
                    this.blocks.set(blockId, blockData);
                }
                
                // Redraw connections after drag is complete
                setTimeout(() => {
                    this.updateConnections();
                }, 50);
            }
        });
    }
    
    selectBlock(blockId) {
        // Deselect previous
        document.querySelectorAll('.quiz-block').forEach(block => {
            block.classList.remove('selected');
        });
        
        // Select new
        const blockElement = document.querySelector(`[data-block-id="${blockId}"]`);
        if (blockElement) {
            blockElement.classList.add('selected');
        }
        
        this.selectedBlock = blockId;
        this.showBlockProperties(blockId);
        
        // Ensure connections are visible after selection
        this.updateConnections();
    }
    
    deselectBlock() {
        document.querySelectorAll('.quiz-block').forEach(block => {
            block.classList.remove('selected');
        });
        this.selectedBlock = null;
        this.showDefaultProperties();
    }
    
    showBlockProperties(blockId) {
        const blockData = this.blocks.get(blockId);
        if (!blockData) return;
        
        if (blockData.type === 'question') {
            this.showQuestionProperties(blockData);
        } else {
            this.showResultProperties(blockData);
        }
    }
    
    showQuestionProperties(blockData) {
        this.propertiesPanel.innerHTML = `
            <div class="property-group">
                <label>Vraag</label>
                <textarea id="prop-question" rows="3">${blockData.question || ''}</textarea>
            </div>
            
            <div class="property-group">
                <label>Antwoord opties</label>
                <div class="quiz-options-list" id="prop-options">
                    ${this.renderOptionsList(blockData.options || ['Optie 1', 'Optie 2'])}
                </div>
                <button type="button" class="quiz-add-option" onclick="quizBuilder.addOption()">+ Voeg optie toe</button>
            </div>
            
            <div class="property-group">
                <label>Verbindingen per antwoord</label>
                <div id="prop-connections">
                    ${this.renderConnectionsList(blockData)}
                </div>
            </div>
            
            <div class="property-group">
                <button type="button" class="button button-secondary" onclick="quizBuilder.updateBlock()">Bijwerken</button>
            </div>
        `;
    }
    
    showResultProperties(blockData) {
        this.propertiesPanel.innerHTML = `
            <div class="property-group">
                <label>Uitslag label</label>
                <input type="text" id="prop-label" value="${blockData.label || ''}">
            </div>
            
            <div class="property-group">
                <label>Samenvatting</label>
                <textarea id="prop-summary" rows="4">${blockData.summary || ''}</textarea>
            </div>
            
            <div class="property-group">
                <label>Geselecteerde producten</label>
                <div id="selected-products" style="max-height: 150px !important; overflow-y: auto !important; border: 1px solid #ddd !important; padding: 10px !important; background: #f9f9f9 !important; margin-bottom: 20px !important; display: block !important; width: 100% !important; box-sizing: border-box !important; border-radius: 4px !important;">
                    ${this.renderSelectedProducts(blockData.products || [], blockData)}
                </div>
            </div>
                
            <div class="property-group" style="border-top: 2px solid #e0e0e0 !important; padding-top: 15px !important; margin-top: 15px !important;">
                <label>Producten toevoegen</label>
                <div id="prop-products">
                    <div style="margin-bottom: 10px !important; position: relative !important;">
                        <input type="text" id="product-search" placeholder="Zoek producten of klik voor alle producten..." class="regular-text" style="width: 70% !important; margin-right: 8px !important;">
                        <button type="button" class="button" onclick="quizBuilder.searchProducts()" style="vertical-align: top !important;">Zoeken</button>
                        <div id="product-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 8px; max-height: 300px; overflow-y: auto; border: 1px solid #ddd; background: white; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); z-index: 1000;">
                            <!-- Dropdown content will be populated here -->
                        </div>
                    </div>
                    <div id="product-results" style="max-height: 200px !important; overflow-y: auto !important; border: 1px solid #ddd !important; padding: 10px !important; background: #f0f8ff !important; border-radius: 4px !important;">
                        <p style="color: #666 !important; font-style: italic !important; margin: 0 !important;">Klik in het veld hierboven om alle producten te zien, of typ een zoekterm en klik "Zoeken" om te filteren. Klik op een product om toe te voegen.</p>
                    </div>
                </div>
            </div>
            
            <div class="property-group">
                <button type="button" class="button button-secondary" onclick="quizBuilder.updateBlock()">Bijwerken</button>
            </div>
        `;
        
        // Add event listeners for product management after the HTML is inserted
        setTimeout(() => {
            this.setupProductEventListeners();
            this.setupProductDropdown();
            
            // Load missing product details for existing products
            this.loadMissingProductDetails(blockData.products || [], blockData);
        }, 10);
    }
    
    renderOptionsList(options) {
        return options.map((option, index) => `
            <div class="quiz-option-item">
                <input type="text" value="${option}" data-option-index="${index}">
                <button type="button" class="quiz-option-delete" onclick="quizBuilder.removeOption(${index})">×</button>
            </div>
        `).join('');
    }
    
    renderConnectionsList(blockData) {
        const options = blockData.options || ['Optie 1', 'Optie 2'];
        const connections = blockData.connections || {};
        
        return options.map((option, index) => {
            const currentConnection = connections[index] || null;
            return `
                <div class="connection-item" style="margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <strong>"${option}"</strong> → 
                    <select data-option-index="${index}" style="margin-left: 8px; min-width: 200px;">
                        <option value="">Geen vervolgactie</option>
                        ${this.getAvailableBlocksOptions(blockData.id, currentConnection)}
                    </select>
                </div>
            `;
        }).join('');
    }
    
    getAvailableBlocksOptions(currentBlockId, selectedBlockId) {
        let options = '';
        
        for (const [blockId, blockData] of this.blocks) {
            if (blockId === currentBlockId) continue; // Skip zichzelf
            
            const selected = blockId === selectedBlockId ? 'selected' : '';
            
            let label;
            if (blockData.type === 'question') {
                const questionText = blockData.question?.substring(0, 25) || 'Onbekende vraag';
                label = `${blockId} - Vraag: ${questionText}${blockData.question?.length > 25 ? '...' : ''}`;
            } else {
                const labelText = blockData.label?.substring(0, 25) || 'Onbekende uitslag';
                label = `${blockId} - Uitslag: ${labelText}${blockData.label?.length > 25 ? '...' : ''}`;
            }
            
            options += `<option value="${blockId}" ${selected}>${label}</option>`;
        }
        
        return options;
    }
    
    renderSelectedProducts(products, blockData = null) {
        if (!products || products.length === 0) {
            return '<p style="color: #666; font-style: italic;">Geen producten geselecteerd</p>';
        }
        
        console.log('renderSelectedProducts called with:', { products, blockData });
        
        // Get product info from block data if available
        const productInfo = blockData?.productInfo || {};
        console.log('Available product info:', productInfo);
        console.log('ProductInfo keys:', Object.keys(productInfo));
        console.log('ProductInfo entries:', Object.entries(productInfo));
        
        const productItems = products.map(productId => {
            const info = productInfo[productId];
            const name = info?.name || `Product ID: ${productId}`;
            const price = info?.price || '';
            const hasImage = info?.image;
            
            console.log(`Rendering product ${productId}:`, { name, price, hasImage });
            
            return `
                <div id="product-${productId}" class="selected-product-item" style="display: flex !important; align-items: center !important; gap: 10px !important; padding: 8px !important; border: 1px solid #ddd !important; border-radius: 4px !important; margin-bottom: 8px !important; background: white !important; width: 100% !important; box-sizing: border-box !important; clear: both !important; float: none !important;">
                    <div class="product-thumbnail" style="width: 40px !important; height: 40px !important; background: #f0f0f0 !important; border-radius: 4px !important; display: flex !important; align-items: center !important; justify-content: center !important; color: #666 !important; font-size: 16px !important; flex-shrink: 0 !important;">
                        ${hasImage ? `<img src="${info.image}" style="width: 100% !important; height: 100% !important; object-fit: cover !important; border-radius: 4px !important;" alt="${name}">` : '📦'}
                    </div>
                    <div class="product-info" style="flex: 1 !important; min-width: 0 !important;">
                        <div class="product-name" style="font-weight: 600 !important; margin-bottom: 2px !important; word-wrap: break-word !important; overflow-wrap: anywhere !important;">${name}</div>
                        <div class="product-price" style="font-size: 12px !important; color: #666 !important; word-wrap: break-word !important;">${price}</div>
                    </div>
                    <button type="button" class="remove-product-btn" data-product-id="${productId}" style="background: #d63638 !important; color: white !important; border: none !important; border-radius: 3px !important; padding: 4px 8px !important; cursor: pointer !important; font-size: 12px !important; flex-shrink: 0 !important;">×</button>
                </div>
            `;
        }).join('');
        
        return `
            <div style="margin-bottom: 10px !important;">
                <strong>${products.length} product(en) geselecteerd:</strong>
            </div>
            <div class="selected-products-list" style="max-height: 150px !important; overflow-y: auto !important; display: block !important; width: 100% !important; box-sizing: border-box !important;">
                ${productItems}
            </div>
            <button type="button" id="clear-all-products" class="button button-small" style="margin-top: 8px !important; width: 100% !important; display: block !important;">
                Alle producten verwijderen
            </button>
        `;
    }
    
    showDefaultProperties() {
        this.propertiesPanel.innerHTML = `
            <p class="description">Selecteer een blok om eigenschappen te bewerken</p>
        `;
    }
    
    addOption() {
        const optionsList = document.getElementById('prop-options');
        const index = optionsList.children.length;
        
        const optionHtml = `
            <div class="quiz-option-item">
                <input type="text" value="Nieuwe optie" data-option-index="${index}">
                <button type="button" class="quiz-option-delete" onclick="quizBuilder.removeOption(${index})">×</button>
            </div>
        `;
        
        optionsList.insertAdjacentHTML('beforeend', optionHtml);
        
        // Refresh connections list
        this.refreshConnectionsList();
    }
    
    removeOption(index) {
        const optionsList = document.getElementById('prop-options');
        const option = optionsList.children[index];
        if (option) {
            option.remove();
            // Re-index remaining options
            Array.from(optionsList.children).forEach((child, newIndex) => {
                const input = child.querySelector('input');
                const button = child.querySelector('button');
                if (input) input.dataset.optionIndex = newIndex;
                if (button) button.setAttribute('onclick', `quizBuilder.removeOption(${newIndex})`);
            });
            
            // Refresh connections list
            this.refreshConnectionsList();
        }
    }
    
    refreshConnectionsList() {
        if (!this.selectedBlock) return;
        
        const blockData = this.blocks.get(this.selectedBlock);
        if (blockData && blockData.type === 'question') {
            const connectionsDiv = document.getElementById('prop-connections');
            if (connectionsDiv) {
                // Get current options from inputs
                const currentOptions = Array.from(document.querySelectorAll('#prop-options input')).map(input => input.value);
                blockData.options = currentOptions;
                
                connectionsDiv.innerHTML = this.renderConnectionsList(blockData);
            }
        }
    }
    
    updateBlock() {
        if (!this.selectedBlock) return;
        
        const blockData = this.blocks.get(this.selectedBlock);
        const blockElement = document.querySelector(`[data-block-id="${this.selectedBlock}"]`);
        
        if (blockData.type === 'question') {
            // Update question data
            const question = document.getElementById('prop-question').value;
            const options = Array.from(document.querySelectorAll('#prop-options input')).map(input => input.value);
            
            // Update connections data
            const connections = {};
            document.querySelectorAll('#prop-connections select').forEach(select => {
                const optionIndex = parseInt(select.dataset.optionIndex);
                const targetBlockId = select.value;
                if (targetBlockId) {
                    connections[optionIndex] = targetBlockId;
                }
            });
            
            blockData.question = question;
            blockData.options = options;
            blockData.connections = connections;
            
            // Update visual
            const content = blockElement.querySelector('.block-content');
            this.populateQuestionContent(content, blockData);
        } else {
            // Update result data
            const label = document.getElementById('prop-label').value;
            const summary = document.getElementById('prop-summary').value;
            
            blockData.label = label;
            blockData.summary = summary;
            
            // Update visual
            const content = blockElement.querySelector('.block-content');
            this.populateResultContent(content, blockData);
            
            // Update title
            const title = blockElement.querySelector('.block-title');
            title.textContent = label || 'Uitslag';
        }
        
        this.blocks.set(this.selectedBlock, blockData);
        
        // Refresh connections
        this.updateConnections();
    }
    
    searchProducts() {
        const query = document.getElementById('product-search').value;
        if (!query) return;
        
        console.log('Starting product search for:', query);
        console.log('AJAX URL:', window.ajaxurl || ajaxurl);
        console.log('Nonce:', window.wpAjax?.nonce);
        
        // Show loading state
        const resultsDiv = document.getElementById('product-results');
        resultsDiv.innerHTML = '<p style="color: #666;">Zoeken...</p>';
        
        // Check if jQuery is available
        if (typeof jQuery === 'undefined') {
            resultsDiv.innerHTML = '<p style="color: #d63638;">jQuery niet beschikbaar</p>';
            return;
        }
        
        // Use WordPress AJAX to search products
        jQuery.post(window.ajaxurl || ajaxurl, {
            action: 'aipc_search_products',
            nonce: window.wpAjax?.nonce || '',
            q: query,
            limit: -1 // No limit for search results either
        }).done((response) => {
            console.log('Product search response:', response);
            if (response && response.success) {
                this.displayProductResults(response.data.results || []);
            } else {
                const errorMsg = response?.data?.message || 'Onbekende fout';
                resultsDiv.innerHTML = '<p style="color: #d63638;">Fout bij zoeken: ' + errorMsg + '</p>';
                console.error('Server error:', response);
            }
        }).fail((xhr, status, error) => {
            console.error('AJAX error details:', {
                status: status,
                error: error,
                xhr: xhr,
                responseText: xhr.responseText,
                url: window.ajaxurl || ajaxurl
            });
            
            let errorMsg = 'Verbindingsfout bij zoeken';
            if (xhr.status === 403) {
                errorMsg = 'Geen toegang (403) - mogelijk een nonce probleem';
            } else if (xhr.status === 404) {
                errorMsg = 'AJAX URL niet gevonden (404)';
            } else if (xhr.status === 500) {
                errorMsg = 'Server fout (500) - check WordPress logs';
            }
            
            resultsDiv.innerHTML = '<p style="color: #d63638;">' + errorMsg + '<br><small>Status: ' + xhr.status + '</small></p>';
        });
    }
    
    displayProductResults(products) {
        const resultsDiv = document.getElementById('product-results');
        if (products.length === 0) {
            resultsDiv.innerHTML = '<p style="color: #666;">Geen producten gevonden</p>';
            return;
        }
        
        // Use same styling as dropdown for consistency
        const html = products.map(product => {
            // Clean price HTML for display
            let cleanPrice = product.price_html || '';
            if (cleanPrice) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = cleanPrice;
                cleanPrice = (tempDiv.textContent || tempDiv.innerText || '').trim();
            }
            
            return `
                <div class="search-result-product-item" data-product-id="${product.id}" data-product-name="${product.name.replace(/"/g, '&quot;')}" data-product-price="${cleanPrice.replace(/"/g, '&quot;')}" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; background: white; border-radius: 4px; margin-bottom: 4px; border: 1px solid #e0e0e0;">
                    <div style="flex: 1;">
                        <div style="font-weight: 600; color: #1d2327;">${product.name}</div>
                        <div style="font-size: 12px; color: #666; margin-top: 2px;">ID: ${product.id} ${cleanPrice ? ` • ${cleanPrice}` : ''}</div>
                    </div>
                    <div style="color: #2271b1; font-size: 12px; font-weight: 600;">+ Toevoegen</div>
                </div>
            `;
        }).join('');
        
        resultsDiv.innerHTML = html;
        
        // Add click listeners to search result items (same as dropdown)
        resultsDiv.querySelectorAll('.search-result-product-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.stopPropagation();
                this.addProductFromDropdown(item); // Reuse same function
            });
            
            // Hover effects
            item.addEventListener('mouseenter', () => {
                item.style.backgroundColor = '#f6f7f7';
            });
            
            item.addEventListener('mouseleave', () => {
                item.style.backgroundColor = 'white';
            });
        });
    }
    
    deleteBlock(blockId) {
        const blockElement = document.querySelector(`[data-block-id="${blockId}"]`);
        if (blockElement) {
            blockElement.remove();
        }
        
        this.blocks.delete(blockId);
        
        if (this.selectedBlock === blockId) {
            this.selectedBlock = null;
            this.showDefaultProperties();
        }
        
        this.updateConnections();
    }
    
    updateConnections() {
        // Clear ALL existing connection elements (lines, labels, and backgrounds)
        this.connectionsSvg.querySelectorAll('.connection-line, .connection-label, .connection-label-bg').forEach(element => {
            element.remove();
        });
        
        // Draw connections based on block data
        for (const [blockId, blockData] of this.blocks) {
            if (blockData.type === 'question' && blockData.connections) {
                this.drawBlockConnections(blockId, blockData);
            }
        }
    }
    
    drawBlockConnections(fromBlockId, blockData) {
        const fromElement = document.querySelector(`[data-block-id="${fromBlockId}"]`);
        if (!fromElement) return;
        
        Object.entries(blockData.connections).forEach(([optionIndex, toBlockId]) => {
            const toElement = document.querySelector(`[data-block-id="${toBlockId}"]`);
            if (!toElement) return;
            
            this.drawConnectionLine(fromElement, toElement, optionIndex, blockData.options[optionIndex]);
        });
    }
    
    drawConnectionLine(fromElement, toElement, optionIndex, optionText) {
        // Get positions relative to the canvas content (not viewport)
        const fromX = parseInt(fromElement.style.left) || 0;
        const fromY = parseInt(fromElement.style.top) || 0;
        const fromWidth = fromElement.offsetWidth;
        const fromHeight = fromElement.offsetHeight;
        
        const toX = parseInt(toElement.style.left) || 0;
        const toY = parseInt(toElement.style.top) || 0;
        const toHeight = toElement.offsetHeight;
        
        // Calculate connection points (right side of from block to left side of to block)
        const startX = fromX + fromWidth;
        const startY = fromY + (fromHeight / 2);
        const endX = toX;
        const endY = toY + (toHeight / 2);
        
        // Create SVG line
        const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.setAttribute('class', 'connection-line');
        line.setAttribute('x1', startX);
        line.setAttribute('y1', startY);
        line.setAttribute('x2', endX);
        line.setAttribute('y2', endY);
        line.setAttribute('stroke', '#2271b1');
        line.setAttribute('stroke-width', '2');
        line.setAttribute('marker-end', 'url(#arrowhead)');
        
        // Add option label with better rendering
        const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        const midX = (startX + endX) / 2;
        const midY = (startY + endY) / 2 - 10;
        text.setAttribute('x', midX);
        text.setAttribute('y', midY);
        text.setAttribute('text-anchor', 'middle');
        text.setAttribute('dominant-baseline', 'middle');
        text.setAttribute('fill', '#2271b1');
        text.setAttribute('font-size', '12');
        text.setAttribute('font-weight', 'bold');
        text.setAttribute('font-family', 'system-ui, -apple-system, sans-serif');
        text.setAttribute('class', 'connection-label');
        text.style.userSelect = 'none';
        text.style.pointerEvents = 'none';
        text.textContent = optionText?.substring(0, 15) + (optionText?.length > 15 ? '...' : '');
        
        // Add white background for text readability
        const textWidth = (optionText?.length || 8) * 7 + 8;
        const textBg = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        textBg.setAttribute('x', midX - textWidth/2);
        textBg.setAttribute('y', midY - 10);
        textBg.setAttribute('width', textWidth);
        textBg.setAttribute('height', 20);
        textBg.setAttribute('fill', 'white');
        textBg.setAttribute('stroke', '#2271b1');
        textBg.setAttribute('stroke-width', '1');
        textBg.setAttribute('rx', '3');
        textBg.setAttribute('class', 'connection-label-bg');
        textBg.style.pointerEvents = 'none';
        
        this.connectionsSvg.appendChild(line);
        this.connectionsSvg.appendChild(textBg);
        this.connectionsSvg.appendChild(text);
    }
    
    testQuiz() {
        const modal = document.getElementById('quiz-test-modal');
        const content = document.getElementById('quiz-test-content');
        
        if (this.blocks.size === 0) {
            content.innerHTML = '<p>Voeg eerst blokken toe aan je quiz!</p>';
            modal.style.display = 'flex';
            return;
        }
        
        // Find first question (no connections pointing to it)
        const startBlock = this.findStartBlock();
        if (!startBlock) {
            content.innerHTML = '<p>Geen startvraag gevonden! Zorg ervoor dat er een vraag is die niet als vervolgvraag is gekoppeld.</p>';
            modal.style.display = 'flex';
            return;
        }
        
        // Start conditional quiz simulation
        this.startConditionalQuiz(startBlock, content);
        modal.style.display = 'flex';
    }
    
    findStartBlock() {
        // Find question blocks that are not targets of any connection
        const targetBlocks = new Set();
        
        // Collect all blocks that are targets of connections
        for (const [blockId, blockData] of this.blocks) {
            if (blockData.type === 'question' && blockData.connections) {
                Object.values(blockData.connections).forEach(targetId => {
                    targetBlocks.add(targetId);
                });
            }
        }
        
        // Find a question block that's not a target
        for (const [blockId, blockData] of this.blocks) {
            if (blockData.type === 'question' && !targetBlocks.has(blockId)) {
                return blockData;
            }
        }
        
        // Fallback: return first question if no clear start found
        for (const [blockId, blockData] of this.blocks) {
            if (blockData.type === 'question') {
                return blockData;
            }
        }
        
        return null;
    }
    
    setZoom(newZoom) {
        // Limit zoom range
        this.zoomLevel = Math.max(0.1, Math.min(3, newZoom));
        
        // Update zoom display
        document.getElementById('zoom-level').textContent = Math.round(this.zoomLevel * 100) + '%';
        
        // Apply transform
        this.updateTransform();
        
        // Update connections after zoom
        setTimeout(() => {
            this.updateConnections();
        }, 100);
    }
    
    zoomToCenter(newZoom) {
        // Get center of viewport
        const rect = this.canvasContainer.getBoundingClientRect();
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        
        this.zoomToPoint(newZoom, centerX, centerY);
    }
    
    zoomToPoint(newZoom, viewportX, viewportY) {
        const oldZoom = this.zoomLevel;
        const limitedZoom = Math.max(0.1, Math.min(3, newZoom));
        
        if (limitedZoom === oldZoom) return; // No change needed
        
        // Convert viewport coordinates to canvas coordinates before zoom
        const canvasX = (viewportX - this.panX) / oldZoom;
        const canvasY = (viewportY - this.panY) / oldZoom;
        
        // Update zoom level
        this.zoomLevel = limitedZoom;
        
        // Calculate new pan to keep the point under the mouse/center
        this.panX = viewportX - canvasX * this.zoomLevel;
        this.panY = viewportY - canvasY * this.zoomLevel;
        
        // Update zoom display
        document.getElementById('zoom-level').textContent = Math.round(this.zoomLevel * 100) + '%';
        
        // Apply transform
        this.updateTransform();
        
        // Update connections after zoom
        setTimeout(() => {
            this.updateConnections();
        }, 100);
    }
    
    updateTransform() {
        const transform = `translate(${this.panX}px, ${this.panY}px) scale(${this.zoomLevel})`;
        this.canvasContent.style.transform = transform;
        
        // SVG is now inside canvas-content, so it inherits the transform automatically
    }
    
    zoomToFit() {
        if (this.blocks.size === 0) {
            this.panX = 0;
            this.panY = 0;
            this.setZoom(1);
            return;
        }
        
        // Calculate bounding box of all blocks
        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        
        this.blocks.forEach((blockData, blockId) => {
            const element = document.querySelector(`[data-block-id="${blockId}"]`);
            if (element) {
                const x = parseInt(element.style.left) || 0;
                const y = parseInt(element.style.top) || 0;
                const width = element.offsetWidth || 200;
                const height = element.offsetHeight || 150;
                
                minX = Math.min(minX, x);
                minY = Math.min(minY, y);
                maxX = Math.max(maxX, x + width);
                maxY = Math.max(maxY, y + height);
            }
        });
        
        if (minX === Infinity) return;
        
        // Add padding
        const padding = 50;
        minX -= padding;
        minY -= padding;
        maxX += padding;
        maxY += padding;
        
        // Calculate required zoom to fit
        const contentWidth = maxX - minX;
        const contentHeight = maxY - minY;
        const containerRect = this.canvasContainer.getBoundingClientRect();
        
        const scaleX = containerRect.width / contentWidth;
        const scaleY = containerRect.height / contentHeight;
        const scale = Math.min(scaleX, scaleY, 1); // Don't zoom in beyond 100%
        
        // Center the content
        this.panX = (containerRect.width - contentWidth * scale) / 2 - minX * scale;
        this.panY = (containerRect.height - contentHeight * scale) / 2 - minY * scale;
        
        this.setZoom(scale);
    }
    
    setupProductEventListeners() {
        // Handle individual product removal
        document.querySelectorAll('.remove-product-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const productId = parseInt(e.target.dataset.productId);
                this.removeProduct(productId);
            });
        });
        
        // Handle clear all products
        const clearAllBtn = document.getElementById('clear-all-products');
        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', () => {
                this.clearAllProducts();
            });
        }
    }
    
    setupProductDropdown() {
        const searchInput = document.getElementById('product-search');
        const dropdown = document.getElementById('product-dropdown');
        
        if (!searchInput || !dropdown) return;
        
        // Show dropdown on focus/click
        searchInput.addEventListener('focus', () => {
            this.showProductDropdown();
        });
        
        searchInput.addEventListener('click', () => {
            this.showProductDropdown();
        });
        
        // Hide dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
        
        // Filter dropdown on input
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            this.filterProductDropdown(query);
        });
    }
    
    showProductDropdown() {
        const dropdown = document.getElementById('product-dropdown');
        if (!dropdown) return;
        
        console.log('Showing product dropdown - loading all products...');
        
        // Show loading state
        dropdown.innerHTML = '<div style="padding: 10px; color: #666; font-style: italic;">Alle producten laden...</div>';
        dropdown.style.display = 'block';
        
        // Load all products via AJAX
        this.loadAllProductsForDropdown();
    }
    
    loadAllProductsForDropdown() {
        // Use WordPress AJAX to get all products (empty query returns all)
        jQuery.post(window.ajaxurl || ajaxurl, {
            action: 'aipc_search_products',
            nonce: window.wpAjax?.nonce || '',
            q: '', // Empty query to get all products
            limit: -1 // No limit - get ALL products
        }).done((response) => {
            console.log('All products response:', response);
            if (response && response.success) {
                this.displayProductDropdown(response.data.results || []);
            } else {
                const dropdown = document.getElementById('product-dropdown');
                if (dropdown) {
                    dropdown.innerHTML = '<div style="padding: 10px; color: #d63638;">Fout bij laden van producten</div>';
                }
            }
        }).fail((xhr, status, error) => {
            console.error('Failed to load all products:', error);
            const dropdown = document.getElementById('product-dropdown');
            if (dropdown) {
                dropdown.innerHTML = '<div style="padding: 10px; color: #d63638;">Verbindingsfout bij laden van producten</div>';
            }
        });
    }
    
    displayProductDropdown(products) {
        const dropdown = document.getElementById('product-dropdown');
        if (!dropdown) return;
        
        if (products.length === 0) {
            dropdown.innerHTML = '<div style="padding: 10px; color: #666; font-style: italic;">Geen producten beschikbaar</div>';
            return;
        }
        
        // Store products for filtering
        this.allProducts = products;
        
        const html = products.map(product => {
            // Clean price HTML for display
            let cleanPrice = product.price_html || '';
            if (cleanPrice) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = cleanPrice;
                cleanPrice = (tempDiv.textContent || tempDiv.innerText || '').trim();
            }
            
            // Get SKU for display
            const sku = product.sku || '';
            
            return `
                <div class="dropdown-product-item" data-product-id="${product.id}" data-product-name="${product.name.replace(/"/g, '&quot;')}" data-product-price="${cleanPrice.replace(/"/g, '&quot;')}" data-product-sku="${sku.replace(/"/g, '&quot;')}" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center;">
                    <div style="flex: 1;">
                        <div style="font-weight: 600; color: #1d2327;">${product.name}${sku ? ` (${sku})` : ''}</div>
                        <div style="font-size: 12px; color: #666; margin-top: 2px;">ID: ${product.id}${cleanPrice ? ` • ${cleanPrice}` : ''}</div>
                    </div>
                    <div style="color: #2271b1; font-size: 12px; font-weight: 600;">+ Toevoegen</div>
                </div>
            `;
        }).join('');
        
        dropdown.innerHTML = html;
        
        // Add click listeners to dropdown items
        dropdown.querySelectorAll('.dropdown-product-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.stopPropagation();
                this.addProductFromDropdown(item);
            });
            
            // Hover effects
            item.addEventListener('mouseenter', () => {
                item.style.backgroundColor = '#f6f7f7';
            });
            
            item.addEventListener('mouseleave', () => {
                item.style.backgroundColor = 'transparent';
            });
        });
    }
    
    filterProductDropdown(query) {
        if (!this.allProducts || !query) {
            // If no query, show all products
            if (this.allProducts) {
                this.displayProductDropdown(this.allProducts);
            }
            return;
        }
        
        // Filter products based on query
        const filtered = this.allProducts.filter(product => {
            const name = product.name.toLowerCase();
            const id = product.id.toString();
            const sku = (product.sku || '').toLowerCase();
            return name.includes(query) || id.includes(query) || sku.includes(query);
        });
        
        this.displayProductDropdown(filtered);
    }
    
    addProductFromDropdown(itemElement) {
        const productId = parseInt(itemElement.dataset.productId);
        const productName = itemElement.dataset.productName;
        const productPrice = itemElement.dataset.productPrice;
        const productSku = itemElement.dataset.productSku;
        
        console.log('Adding product from dropdown:', { productId, productName, productPrice });
        
        if (!this.selectedBlock) return;
        
        const blockData = this.blocks.get(this.selectedBlock);
        if (!blockData) return;
        
        // Check if product is already added
        if (blockData.products && blockData.products.includes(productId)) {
            console.log('Product already exists, skipping...');
            return;
        }
        
        // Add product to block data
        if (!blockData.productInfo) blockData.productInfo = {};
        if (!blockData.products) blockData.products = [];
        
        // Store product info
        blockData.productInfo[productId] = {
            id: productId,
            name: productName,
            price: productPrice || 'Prijs onbekend',
            sku: productSku || '',
            image: null
        };
        
        blockData.products.push(productId);
        this.blocks.set(this.selectedBlock, blockData);
        
        // Update UI
        const selectedProductsDiv = document.getElementById('selected-products');
        if (selectedProductsDiv) {
            selectedProductsDiv.innerHTML = this.renderSelectedProducts(blockData.products || [], blockData);
            setTimeout(() => {
                this.setupProductEventListeners();
                this.setupProductDropdown();
            }, 10);
        }
        
        // Update visual in block
        const blockElement = document.querySelector(`[data-block-id="${this.selectedBlock}"]`);
        const content = blockElement.querySelector('.block-content');
        this.populateResultContent(content, blockData);
        
        // Update validation
        this.updateBlockValidation(this.selectedBlock, blockData);
        
        // Load complete product details (including image)
        this.loadMissingProductDetails([productId], blockData);
        
        // Clear search input and hide dropdown
        const searchInput = document.getElementById('product-search');
        const dropdown = document.getElementById('product-dropdown');
        if (searchInput) searchInput.value = '';
        if (dropdown) dropdown.style.display = 'none';
        
        console.log(`Product ${productName} (${productId}) successfully added!`);
    }
    
    removeProduct(productId) {
        if (!this.selectedBlock) return;
        
        const blockData = this.blocks.get(this.selectedBlock);
        if (blockData && blockData.products) {
            // Remove product from array
            blockData.products = blockData.products.filter(id => id !== productId);
            
            // Update data
            this.blocks.set(this.selectedBlock, blockData);
            
            // Update selected products display only
            const selectedProductsDiv = document.getElementById('selected-products');
            if (selectedProductsDiv) {
                selectedProductsDiv.innerHTML = this.renderSelectedProducts(blockData.products || [], blockData);
                // Re-setup event listeners
                setTimeout(() => {
                    this.setupProductEventListeners();
                    this.setupProductDropdown();
                }, 10);
                
                // Load product details for remaining products
                this.loadMissingProductDetails(blockData.products || [], blockData);
            }
            
            // Update visual in block
            const blockElement = document.querySelector(`[data-block-id="${this.selectedBlock}"]`);
            const content = blockElement.querySelector('.block-content');
            this.populateResultContent(content, blockData);
            
            // Update validation AFTER content update
            this.updateBlockValidation(this.selectedBlock, blockData);
        }
    }
    
    clearAllProducts() {
        if (!this.selectedBlock) return;
        
        const blockData = this.blocks.get(this.selectedBlock);
        if (blockData) {
            // Clear products array
            blockData.products = [];
            
            // Update data
            this.blocks.set(this.selectedBlock, blockData);
            
            // Update selected products display only
            const selectedProductsDiv = document.getElementById('selected-products');
            if (selectedProductsDiv) {
                selectedProductsDiv.innerHTML = this.renderSelectedProducts(blockData.products || [], blockData);
                
                // Load product details for remaining products (should be empty but just in case)
                this.loadMissingProductDetails(blockData.products || [], blockData);
            }
            
            // Update visual
            const blockElement = document.querySelector(`[data-block-id="${this.selectedBlock}"]`);
            const content = blockElement.querySelector('.block-content');
            this.populateResultContent(content, blockData);
            
            // Update validation AFTER content update
            this.updateBlockValidation(this.selectedBlock, blockData);
        }
    }
    
    loadProductDetails(productIds) {
        if (!productIds || productIds.length === 0) return;
        
        // Load details for each product
        productIds.forEach(productId => {
            this.fetchProductInfo(productId).then(productInfo => {
                this.updateProductDisplay(productId, productInfo);
            }).catch(error => {
                console.warn(`Failed to load details for product ${productId}:`, error);
                this.updateProductDisplay(productId, {
                    name: `Product ${productId}`,
                    price: 'Prijs onbekend',
                    image: null
                });
            });
        });
    }
    
    loadMissingProductDetails(productIds, blockData) {
        if (!productIds || productIds.length === 0) return;
        
        const productInfo = blockData?.productInfo || {};
        
        productIds.forEach(productId => {
            const currentInfo = productInfo[productId];
            
            // Check if product info is missing or incomplete
            const needsRefresh = !currentInfo || 
                                currentInfo.name === `Product ${productId}` ||
                                currentInfo.name === `Product ID: ${productId}` ||
                                !currentInfo.price ||
                                currentInfo.price === 'Prijs onbekend' ||
                                currentInfo.price.includes('<span class=') ||
                                currentInfo.price.includes('class=') ||
                                currentInfo.image === null; // Also refresh if image is missing
            
            if (needsRefresh) {
                this.fetchProductInfo(productId).then(productInfo => {
                    
                    // Update the stored product info
                    if (!blockData.productInfo) {
                        blockData.productInfo = {};
                    }
                    
                    // Clean up the received data
                    let cleanName = productInfo.name || `Product ${productId}`;
                    let cleanPrice = productInfo.price || 'Prijs onbekend';
                    
                    // Decode HTML entities in name
                    if (cleanName.includes('&amp;') || cleanName.includes('&lt;') || cleanName.includes('&gt;')) {
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = cleanName;
                        cleanName = tempDiv.textContent || tempDiv.innerText || cleanName;
                    }
                    
                    // Clean price HTML
                    if (cleanPrice.includes('<') || cleanPrice.includes('&lt;')) {
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = cleanPrice;
                        cleanPrice = (tempDiv.textContent || tempDiv.innerText || cleanPrice).trim();
                    }
                    
                    blockData.productInfo[productId] = {
                        id: productId,
                        name: cleanName,
                        price: cleanPrice,
                        image: productInfo.image || null
                    };
                    
                    // Update the stored block data
                    this.blocks.set(this.selectedBlock, blockData);
                    
                    // Update visual display
                    this.updateProductDisplay(productId, blockData.productInfo[productId]);
                }).catch(error => {
                    console.warn(`Failed to load details for product ${productId}:`, error);
                    
                    // Store fallback info
                    if (!blockData.productInfo) {
                        blockData.productInfo = {};
                    }
                    blockData.productInfo[productId] = {
                        id: productId,
                        name: `Product ${productId}`,
                        price: 'Prijs onbekend',
                        image: null
                    };
                    
                    this.updateProductDisplay(productId, blockData.productInfo[productId]);
                });
            } else {
                // Still update display in case the HTML wasn't rendered yet
                this.updateProductDisplay(productId, currentInfo);
            }
        });
    }
    
    fetchProductInfo(productId) {
        return new Promise((resolve, reject) => {
            const requestData = {
                action: 'aipc_get_product_info',
                nonce: window.wpAjax?.nonce || '',
                product_id: productId
            };
            
            jQuery.post(window.ajaxurl || ajaxurl, requestData)
            .done((response) => {
                if (response.success && response.data) {
                    resolve(response.data);
                } else {
                    reject(new Error(response.data?.message || 'Product niet gevonden'));
                }
            })
            .fail((xhr, status, error) => {
                console.error('Product info AJAX error:', xhr.status, error);
                reject(new Error(`AJAX fout: ${error} (Status: ${xhr.status})`));
            });
        });
    }
    
    updateProductDisplay(productId, productInfo) {
        const productElement = document.getElementById(`product-${productId}`);
        if (!productElement) return;
        
        const thumbnail = productElement.querySelector('.product-thumbnail');
        const nameElement = productElement.querySelector('.product-name');
        const priceElement = productElement.querySelector('.product-price');
        
        // Update name
        if (nameElement) {
            nameElement.textContent = productInfo.name || `Product ${productId}`;
        }
        
        // Update price
        if (priceElement) {
            priceElement.textContent = productInfo.price || 'Prijs onbekend';
        }
        
        // Update thumbnail
        if (thumbnail && productInfo.image) {
            thumbnail.innerHTML = `<img src="${productInfo.image}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 4px;" alt="${productInfo.name}">`;
        } else if (thumbnail) {
            // Show product icon if no image
            thumbnail.innerHTML = '<span style="font-size: 16px;">📦</span>';
        }
    }
    
    startConditionalQuiz(startBlock, container) {
        let answers = {};
        let questionCount = 0;
        
        const showBlock = (currentBlock) => {
            if (!currentBlock) {
                this.showQuizResult(answers, [], container);
                return;
            }
            
            if (currentBlock.type === 'result') {
                this.showQuizResult(answers, [currentBlock], container);
                return;
            }
            
            questionCount++;
            const html = `
                <div class="quiz-test-question">
                    <h3>Vraag ${questionCount}</h3>
                    <p class="question-text">${currentBlock.question || 'Geen vraag tekst'}</p>
                    <div class="question-options">
                        ${(currentBlock.options || []).map((option, index) => `
                            <button class="quiz-option-btn" data-option-index="${index}" data-answer="${option}">
                                ${option}
                            </button>
                        `).join('')}
                    </div>
                    <div class="quiz-test-flow">
                        <small style="color: #666;">Flow: ${currentBlock.id || 'Onbekend'}</small>
                    </div>
                </div>
                <style>
                .quiz-test-question { padding: 20px; }
                .question-text { font-size: 16px; margin-bottom: 20px; }
                .question-options { margin-bottom: 20px; }
                .quiz-option-btn {
                    display: block;
                    width: 100%;
                    padding: 12px 16px;
                    margin-bottom: 10px;
                    background: #f6f7f7;
                    border: 1px solid #c3c4c7;
                    border-radius: 4px;
                    cursor: pointer;
                    text-align: left;
                    font-size: 14px;
                }
                .quiz-option-btn:hover {
                    background: #2271b1;
                    color: white;
                    border-color: #2271b1;
                }
                .quiz-test-flow {
                    margin-top: 15px;
                    padding-top: 15px;
                    border-top: 1px solid #e0e0e0;
                }
                </style>
            `;
            
            container.innerHTML = html;
            
            // Add click handlers with conditional logic
            container.querySelectorAll('.quiz-option-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const answer = btn.dataset.answer;
                    const optionIndex = parseInt(btn.dataset.optionIndex);
                    
                    // Store answer
                    answers[currentBlock.key || currentBlock.id || `question_${questionCount}`] = answer;
                    
                    // Find next block based on connections
                    let nextBlockId = null;
                    if (currentBlock.connections && currentBlock.connections[optionIndex]) {
                        nextBlockId = currentBlock.connections[optionIndex];
                    }
                    
                    console.log(`Answer: ${answer}, Next block: ${nextBlockId}`);
                    
                    // Get next block data
                    const nextBlock = nextBlockId ? this.blocks.get(nextBlockId) : null;
                    
                    if (nextBlock) {
                        showBlock(nextBlock);
                    } else {
                        // No connection found, show result or end
                        this.showQuizResult(answers, [], container);
                    }
                });
            });
        };
        
        // Start with the first block
        showBlock(startBlock);
    }
    
    showQuizResult(answers, results, container) {
        // Simple result matching (would be improved with connection logic)
        let matchedResult = null;
        
        if (results.length > 0) {
            // For now, just show the first result
            matchedResult = results[0];
        }
        
        const html = `
            <div class="quiz-test-result">
                <h3>🎉 Quiz Voltooid!</h3>
                ${matchedResult ? `
                    <div class="result-content">
                        <h4>${matchedResult.label || 'Uitslag'}</h4>
                        <p>${matchedResult.summary || 'Geen beschrijving beschikbaar'}</p>
                        ${matchedResult.products && matchedResult.products.length > 0 ? `
                            <div class="result-products">
                                <strong>Aanbevolen producten:</strong>
                                <p>${matchedResult.products.length} product(en) geselecteerd</p>
                            </div>
                        ` : ''}
                    </div>
                ` : `
                    <p>Geen uitslag blok gevonden. Voeg een uitslag blok toe aan je quiz.</p>
                `}
                <div class="quiz-answers">
                    <details>
                        <summary>Jouw antwoorden</summary>
                        <ul>
                            ${Object.entries(answers).map(([key, answer]) => `
                                <li><strong>${key}:</strong> ${answer}</li>
                            `).join('')}
                        </ul>
                    </details>
                </div>
                <button type="button" class="button button-primary" onclick="document.getElementById('quiz-test-modal').style.display='none'">
                    Sluiten
                </button>
            </div>
            <style>
            .quiz-test-result { padding: 20px; text-align: center; }
            .result-content { margin: 20px 0; padding: 20px; background: #f0f9ff; border-radius: 8px; }
            .result-products { margin-top: 15px; padding: 10px; background: #f6fdf7; border-radius: 4px; }
            .quiz-answers { margin: 20px 0; text-align: left; }
            .quiz-answers ul { margin: 10px 0; padding-left: 20px; }
            .quiz-answers li { margin-bottom: 5px; }
            </style>
        `;
        
        container.innerHTML = html;
    }
    
    clearAll(silent = false) {
        if (!silent) {
            const blockCount = this.blocks.size;
            
            // Extra warning for large quizzes
            if (blockCount > 10) {
                const message = `Je hebt ${blockCount} blokken gemaakt. Dit zal al je werk verwijderen!\n\nOverweeg eerst een export te maken als backup.\n\nWeet je zeker dat je alles wilt verwijderen?`;
                if (!confirm(message)) {
                    return false;
                }
            } else if (blockCount > 0) {
                if (!confirm(`Weet je zeker dat je alle ${blockCount} blokken wilt verwijderen?`)) {
                    return false;
                }
            }
        }
        
        // Clear visual elements
        this.canvas.innerHTML = '';
        
        // Clear SVG connections
        if (this.connectionsSvg) {
            this.connectionsSvg.querySelectorAll('.connection-line, .connection-label, .connection-label-bg').forEach(el => {
                el.remove();
            });
        }
        
        // Clear data structures
        this.blocks.clear();
        this.connections.clear();
        this.selectedBlock = null;
        this.blockCounter = 0;
        
        // Reset UI
        this.showDefaultProperties();
        
        // Reset viewport to default (optional - comment out if you want to keep current zoom/pan)
        // this.zoomLevel = 1;
        // this.panX = 0;
        // this.panY = 0;
        // this.updateTransform();
        // document.getElementById('zoom-level').textContent = '100%';
        
        console.log('🧹 All blocks cleared successfully');
        
        return true;
    }
    
    saveQuizData() {
        // Save current viewport state before saving
        this.saveViewportState();
        
        // Create a clean data structure with all block positions
        const blocksData = {};
        
        this.blocks.forEach((blockData, blockId) => {
            // Get current visual position from DOM
            const blockElement = document.querySelector(`[data-block-id="${blockId}"]`);
            if (blockElement) {
                const currentPosition = {
                    x: parseInt(blockElement.style.left) || 0,
                    y: parseInt(blockElement.style.top) || 0
                };
                
                // Ensure all required data is preserved
                blocksData[blockId] = {
                    ...blockData,
                    id: blockId,
                    position: currentPosition
                };
            }
        });
        
        const quizData = {
            blocks: blocksData,
            connections: Object.fromEntries(this.connections)
        };
        
        console.log('Saving quiz data:', quizData);
        document.getElementById('quiz-data-input').value = JSON.stringify(quizData);
    }
    
    saveViewportState() {
        // Save viewport state to localStorage
        const viewportState = {
            zoomLevel: this.zoomLevel,
            panX: this.panX,
            panY: this.panY,
            timestamp: Date.now()
        };
        
        localStorage.setItem('quizBuilder_viewport', JSON.stringify(viewportState));
        console.log('Saved viewport state:', viewportState);
    }
    
    restoreViewportState() {
        try {
            const saved = localStorage.getItem('quizBuilder_viewport');
            if (saved) {
                const viewportState = JSON.parse(saved);
                
                // Only restore if saved recently (within 30 seconds)
                const age = Date.now() - (viewportState.timestamp || 0);
                if (age < 30000) {
                    this.zoomLevel = viewportState.zoomLevel || 1;
                    this.panX = viewportState.panX || 0;
                    this.panY = viewportState.panY || 0;
                    
                    // Update zoom display
                    document.getElementById('zoom-level').textContent = Math.round(this.zoomLevel * 100) + '%';
                    
                    // Apply transform
                    this.updateTransform();
                    
                    console.log('Restored viewport state:', viewportState);
                    
                    // Clean up the stored state
                    localStorage.removeItem('quizBuilder_viewport');
                }
            }
        } catch (e) {
            console.log('Could not restore viewport state:', e);
        }
    }
    
    loadExistingData() {
        try {
            const existingDataElement = document.querySelector('script[data-quiz-data]');
            if (existingDataElement && existingDataElement.textContent.trim()) {
                let rawData = existingDataElement.textContent.trim();
                
                // Decode HTML entities if they exist
                if (rawData.includes('&quot;')) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = rawData;
                    rawData = tempDiv.textContent || tempDiv.innerText || '';
                }
                
                if (rawData && rawData !== '[]') {
                    const data = JSON.parse(rawData);
                    
                    console.log('Loading existing quiz data:', data);
                    
                    if (data.blocks && typeof data.blocks === 'object') {
                        let loadedCount = 0;
                        
                        Object.entries(data.blocks).forEach(([blockId, blockData]) => {
                            // Validate block data
                            if (blockData && blockData.type && (blockData.type === 'question' || blockData.type === 'result')) {
                                // Make sure to restore block ID
                                blockData.id = blockId;
                                
                                try {
                                    this.addBlock(blockData.type, blockData);
                                    loadedCount++;
                                } catch (error) {
                                    console.warn(`Failed to load block ${blockId}:`, error);
                                }
                            } else {
                                console.warn(`Invalid block data for ${blockId}:`, blockData);
                            }
                        });
                        
                        // Update connections and validation after all blocks are loaded
                        setTimeout(() => {
                            this.updateConnections();
                            this.validateBlocks();
                        }, 200);
                        
                        console.log(`Loaded ${loadedCount} of ${Object.keys(data.blocks).length} blocks successfully!`);
                    }
                }
            }
        } catch (e) {
            console.log('Error loading existing quiz data:', e);
            console.log('Raw data was:', existingDataElement?.textContent);
        }
    }
    
    validateBlocks() {
        // Update visual validation for all blocks
        this.blocks.forEach((blockData, blockId) => {
            this.updateBlockValidation(blockId, blockData);
        });
    }
    
    updateBlockValidation(blockId, blockData) {
        const blockElement = document.querySelector(`[data-block-id="${blockId}"]`);
        if (!blockElement) return;
        
        // Remove existing validation classes
        blockElement.classList.remove('incomplete-block', 'complete-block');
        
        if (blockData.type === 'result') {
            const productCount = blockData.products?.length || 0;
            if (productCount === 0) {
                blockElement.classList.add('incomplete-block');
            } else {
                blockElement.classList.add('complete-block');
            }
        }
        
        // Could add validation for question blocks too if needed
        // e.g., check if all options have connections
    }
    
    getDefaultBlockData(type) {
        if (type === 'question') {
            return {
                question: 'Nieuwe vraag...',
                options: ['Optie 1', 'Optie 2'],
                key: `question_${this.blockCounter}`
            };
        } else {
            return {
                label: 'Nieuwe uitslag',
                summary: 'Beschrijving van deze uitslag...',
                products: []
            };
        }
    }
    
    showDebugInfo() {
        const debugDiv = document.getElementById('debug-info');
        if (debugDiv) {
            debugDiv.style.display = debugDiv.style.display === 'none' ? 'block' : 'none';
        }
        
        console.log('=== QUIZ DEBUG INFO ===');
        console.log('Current blocks:', this.blocks);
        console.log('Current connections:', this.connections);
        
        const scriptElement = document.querySelector('script[data-quiz-data]');
        if (scriptElement) {
            console.log('Script element found:', scriptElement);
            console.log('Script content:', scriptElement.textContent);
        } else {
            console.log('No script element found');
        }
        
        alert('Debug informatie gelogd naar browser console (F12 -> Console). ' + 
              (debugDiv ? 'Debug info panel omgeschakeld.' : ''));
    }
    
    exportQuiz() {
        // Show export options modal
        this.showExportModal();
    }
    
    importQuiz() {
        // Show import modal
        this.showImportModal();
    }
    
    showExportModal() {
        const modalHtml = `
            <div id="export-modal" class="quiz-modal" style="display: flex;">
                <div class="quiz-modal-content">
                    <div class="quiz-modal-header">
                        <h2>Quiz Exporteren</h2>
                        <span class="quiz-modal-close" onclick="document.getElementById('export-modal').remove()">&times;</span>
                    </div>
                    <div class="quiz-modal-body">
                        <p>Kies het gewenste export formaat:</p>
                        
                        <div style="margin: 20px 0;">
                            <label style="display: block; margin-bottom: 15px; cursor: pointer;">
                                <input type="radio" name="export-format" value="complete-json" checked style="margin-right: 10px;">
                                <strong>Volledige JSON Export</strong>
                                <br><small style="color: #666; margin-left: 25px;">Alle data inclusief productinformatie - ideaal voor backup en import</small>
                            </label>
                            
                            <label style="display: block; margin-bottom: 15px; cursor: pointer;">
                                <input type="radio" name="export-format" value="quiz-only-json" style="margin-right: 10px;">
                                <strong>Quiz Structuur JSON</strong>
                                <br><small style="color: #666; margin-left: 25px;">Alleen de quiz structuur - voor gebruik in andere systemen</small>
                            </label>
                            
                            <label style="display: block; margin-bottom: 15px; cursor: pointer;">
                                <input type="radio" name="export-format" value="csv" style="margin-right: 10px;">
                                <strong>CSV Export</strong>
                                <br><small style="color: #666; margin-left: 25px;">Product mappings in spreadsheet formaat</small>
                            </label>
                            
                            <label style="display: block; margin-bottom: 15px; cursor: pointer;">
                                <input type="radio" name="export-format" value="readable" style="margin-right: 10px;">
                                <strong>Leesbare Export</strong>
                                <br><small style="color: #666; margin-left: 25px;">Menselijk leesbare tekst voor documentatie</small>
                            </label>
                        </div>
                        
                        <div style="text-align: center; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                            <button type="button" class="button button-primary" onclick="quizBuilder.executeExport()" style="margin-right: 10px;">
                                <span class="dashicons dashicons-download" style="margin-right: 5px;"></span>
                                Exporteer
                            </button>
                            <button type="button" class="button" onclick="document.getElementById('export-modal').remove()">
                                Annuleren
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }
    
    executeExport() {
        const selectedFormat = document.querySelector('input[name="export-format"]:checked')?.value;
        if (!selectedFormat) return;
        
        // Close modal
        document.getElementById('export-modal')?.remove();
        
        // Execute export based on format
        switch (selectedFormat) {
            case 'complete-json':
                this.exportCompleteJSON();
                break;
            case 'quiz-only-json':
                this.exportQuizOnlyJSON();
                break;
            case 'csv':
                this.exportCSV();
                break;
            case 'readable':
                this.exportReadable();
                break;
        }
    }
    
    exportCompleteJSON() {
        // Get complete quiz data including all block info
        const exportData = {
            metadata: {
                exported_at: new Date().toISOString(),
                quiz_builder_version: '1.0.0',
                total_blocks: this.blocks.size,
                export_type: 'complete'
            },
            quiz_data: {
                blocks: Object.fromEntries(this.blocks),
                connections: Object.fromEntries(this.connections)
            }
        };
        
        this.downloadJSON(exportData, `quiz-complete-export-${this.getDateString()}.json`);
    }
    
    exportQuizOnlyJSON() {
        // Export only the quiz structure (same as what gets saved to WordPress)
        const blocksData = {};
        
        this.blocks.forEach((blockData, blockId) => {
            const blockElement = document.querySelector(`[data-block-id="${blockId}"]`);
            const currentPosition = blockElement ? {
                x: parseInt(blockElement.style.left) || 0,
                y: parseInt(blockElement.style.top) || 0
            } : { x: 0, y: 0 };
            
            blocksData[blockId] = {
                ...blockData,
                id: blockId,
                position: currentPosition
            };
        });
        
        const exportData = {
            blocks: blocksData,
            connections: Object.fromEntries(this.connections)
        };
        
        this.downloadJSON(exportData, `quiz-structure-${this.getDateString()}.json`);
    }
    
    exportCSV() {
        // Create CSV with product mappings
        let csvContent = 'Flow Path,Result Label,Product IDs,Product Names,Product Prices\n';
        
        this.blocks.forEach((blockData, blockId) => {
            if (blockData.type === 'result' && blockData.products && blockData.products.length > 0) {
                const flowPath = this.getFlowPathToBlock(blockId);
                const label = blockData.label || 'Nieuwe uitslag';
                const productIds = blockData.products.join(';');
                
                // Get product names and prices
                const productNames = [];
                const productPrices = [];
                
                blockData.products.forEach(productId => {
                    const productInfo = blockData.productInfo?.[productId];
                    productNames.push(productInfo?.name || `Product ${productId}`);
                    productPrices.push(productInfo?.price || 'Onbekend');
                });
                
                const escapedLabel = `"${label.replace(/"/g, '""')}"`;
                const escapedNames = `"${productNames.join('; ').replace(/"/g, '""')}"`;
                const escapedPrices = `"${productPrices.join('; ').replace(/"/g, '""')}"`;
                
                csvContent += `"${flowPath}",${escapedLabel},"${productIds}",${escapedNames},${escapedPrices}\n`;
            }
        });
        
        this.downloadFile(csvContent, `quiz-products-${this.getDateString()}.csv`, 'text/csv');
    }
    
    exportReadable() {
        let content = `QUIZ EXPORT - ${new Date().toLocaleString()}\n`;
        content += `=${'='.repeat(50)}\n\n`;
        
        // Count statistics
        const questionBlocks = Array.from(this.blocks.values()).filter(b => b.type === 'question');
        const resultBlocks = Array.from(this.blocks.values()).filter(b => b.type === 'result');
        const totalProducts = resultBlocks.reduce((sum, b) => sum + (b.products?.length || 0), 0);
        
        content += `STATISTIEKEN:\n`;
        content += `- Totaal blokken: ${this.blocks.size}\n`;
        content += `- Vraag blokken: ${questionBlocks.length}\n`;
        content += `- Uitslag blokken: ${resultBlocks.length}\n`;
        content += `- Totaal producten: ${totalProducts}\n\n`;
        
        content += `QUIZ STRUCTUUR:\n`;
        content += `${'='.repeat(20)}\n\n`;
        
        // List all questions
        questionBlocks.forEach((block, index) => {
            content += `${index + 1}. VRAAG: ${block.question || 'Geen tekst'}\n`;
            content += `   ID: ${block.id}\n`;
            content += `   Opties: ${(block.options || []).join(', ')}\n`;
            if (block.connections && Object.keys(block.connections).length > 0) {
                content += `   Verbindingen:\n`;
                Object.entries(block.connections).forEach(([optionIndex, targetId]) => {
                    const option = block.options[optionIndex] || `Optie ${optionIndex}`;
                    content += `     "${option}" → ${targetId}\n`;
                });
            }
            content += `\n`;
        });
        
        content += `PRODUCT MAPPINGS:\n`;
        content += `${'='.repeat(20)}\n\n`;
        
        // List all results with products
        resultBlocks.forEach((block, index) => {
            content += `${index + 1}. UITSLAG: ${block.label || 'Nieuwe uitslag'}\n`;
            content += `   ID: ${block.id}\n`;
            content += `   Beschrijving: ${block.summary || 'Geen beschrijving'}\n`;
            content += `   Flow pad: ${this.getFlowPathToBlock(block.id)}\n`;
            
            if (block.products && block.products.length > 0) {
                content += `   Producten (${block.products.length}):\n`;
                block.products.forEach(productId => {
                    const productInfo = block.productInfo?.[productId];
                    const name = productInfo?.name || `Product ${productId}`;
                    const price = productInfo?.price || 'Prijs onbekend';
                    content += `     - ${name} (ID: ${productId}, ${price})\n`;
                });
            } else {
                content += `   Producten: Geen producten toegevoegd\n`;
            }
            content += `\n`;
        });
        
        this.downloadFile(content, `quiz-readable-${this.getDateString()}.txt`, 'text/plain');
    }
    
    getFlowPathToBlock(targetBlockId) {
        // Simple flow path reconstruction - could be more sophisticated
        const paths = [];
        
        this.blocks.forEach((blockData, blockId) => {
            if (blockData.type === 'question' && blockData.connections) {
                Object.entries(blockData.connections).forEach(([optionIndex, connectedBlockId]) => {
                    if (connectedBlockId === targetBlockId) {
                        const question = blockData.question || `Vraag ${blockId}`;
                        const option = blockData.options?.[optionIndex] || `Optie ${optionIndex}`;
                        paths.push(`${question} → ${option}`);
                    }
                });
            }
        });
        
        return paths.length > 0 ? paths.join(' | ') : 'Direct bereikbaar';
    }
    
    downloadJSON(data, filename) {
        const jsonString = JSON.stringify(data, null, 2);
        this.downloadFile(jsonString, filename, 'application/json');
    }
    
    downloadFile(content, filename, mimeType) {
        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        
        // Show success message
        const notice = document.createElement('div');
        notice.className = 'notice notice-success is-dismissible';
        notice.innerHTML = `<p><strong>Export succesvol!</strong> Het bestand "${filename}" is gedownload.</p>`;
        document.querySelector('.wrap').insertBefore(notice, document.querySelector('.wrap').firstChild);
        
        // Auto-remove notice after 5 seconds
        setTimeout(() => {
            if (notice.parentNode) {
                notice.parentNode.removeChild(notice);
            }
        }, 5000);
    }
    
    getDateString() {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    }
    
    showImportModal() {
        const modalHtml = `
            <div id="import-modal" class="quiz-modal" style="display: flex;">
                <div class="quiz-modal-content" style="max-width: 700px;">
                    <div class="quiz-modal-header">
                        <h2>Quiz Importeren</h2>
                        <span class="quiz-modal-close" onclick="window.currentImportData = null; document.getElementById('import-modal').remove()">&times;</span>
                    </div>
                    <div class="quiz-modal-body">
                        <!-- File Upload Zone -->
                        <div id="import-drop-zone" style="border: 2px dashed #c3c4c7; border-radius: 8px; padding: 40px; text-align: center; background: #f9f9f9; margin-bottom: 20px; transition: all 0.3s ease; cursor: pointer;">
                            <div style="margin-bottom: 15px;">
                                <span class="dashicons dashicons-upload" style="font-size: 48px; color: #2271b1;"></span>
                            </div>
                            <h3 style="margin: 0 0 10px 0; color: #1d2327;">Sleep je quiz bestand hierheen</h3>
                            <p style="margin: 0 0 15px 0; color: #646970;">of klik om een bestand te selecteren</p>
                            <input type="file" id="import-file-input" accept=".json,.txt" style="display: none;">
                            <button type="button" class="button button-secondary" onclick="document.getElementById('import-file-input').click()">
                                Bestand Selecteren
                            </button>
                            <p style="margin: 15px 0 0 0; font-size: 12px; color: #646970;">Ondersteunde formaten: JSON, TXT (max 5MB)</p>
                        </div>
                        
                        <!-- Preview Area -->
                        <div id="import-preview" style="display: none; margin-bottom: 20px;">
                            <h4>Bestand Preview:</h4>
                            <div id="import-file-info" style="background: #f0f8ff; padding: 15px; border-radius: 4px; margin-bottom: 15px;"></div>
                            <div id="import-data-preview" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px;"></div>
                        </div>
                        
                        <!-- Import Options -->
                        <div id="import-options" style="display: none; margin-bottom: 20px;">
                            <h4>Import Instellingen:</h4>
                            <label style="display: block; margin-bottom: 10px;">
                                <input type="radio" name="import-mode" value="replace" checked style="margin-right: 8px;">
                                <strong>Vervang huidige quiz</strong> - Alle bestaande data wordt overschreven
                            </label>
                            <label style="display: block; margin-bottom: 10px;">
                                <input type="radio" name="import-mode" value="merge" style="margin-right: 8px;">
                                <strong>Voeg samen met huidige quiz</strong> - Nieuwe blokken worden toegevoegd
                            </label>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div style="text-align: center; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                            <button type="button" class="button button-primary" id="execute-import-btn" disabled style="margin-right: 10px;">
                                <span class="dashicons dashicons-upload" style="margin-right: 5px;"></span>
                                Importeren
                            </button>
                            <button type="button" class="button" onclick="window.currentImportData = null; document.getElementById('import-modal').remove()">
                                Annuleren
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Setup import functionality
        this.setupImportModal();
    }
    
    setupImportModal() {
        const dropZone = document.getElementById('import-drop-zone');
        const fileInput = document.getElementById('import-file-input');
        const executeBtn = document.getElementById('execute-import-btn');
        
        let currentFileData = null;
        
        // Drag and drop events
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#2271b1';
            dropZone.style.backgroundColor = '#f0f8ff';
        });
        
        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#c3c4c7';
            dropZone.style.backgroundColor = '#f9f9f9';
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#c3c4c7';
            dropZone.style.backgroundColor = '#f9f9f9';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                this.handleImportFile(files[0]);
            }
        });
        
        // Click to select file
        dropZone.addEventListener('click', () => {
            fileInput.click();
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                this.handleImportFile(e.target.files[0]);
            }
        });
        
        // Execute import button
        executeBtn.addEventListener('click', () => {
            console.log('🔥 Import button clicked!');
            console.log('Import data available:', !!window.currentImportData);
            
            if (window.currentImportData) {
                const mode = document.querySelector('input[name="import-mode"]:checked')?.value || 'replace';
                console.log('Import mode:', mode);
                this.executeImport(window.currentImportData, mode);
            } else {
                console.warn('No import data available');
                alert('Geen importdata beschikbaar. Selecteer eerst een bestand.');
            }
        });
    }
    
    handleImportFile(file) {
        console.log('📁 Handling import file:', file.name, 'Size:', file.size, 'Type:', file.type);
        
        // File size validation (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            alert('Bestand is te groot! Maximum grootte is 5MB.');
            return;
        }
        
        // File type validation
        const allowedTypes = ['application/json', 'text/plain', 'text/json'];
        const fileName = file.name.toLowerCase();
        const isValidType = allowedTypes.includes(file.type) || fileName.endsWith('.json') || fileName.endsWith('.txt');
        
        if (!isValidType) {
            alert('Ongeldig bestandstype! Alleen JSON en TXT bestanden zijn toegestaan.');
            return;
        }
        
        // Read file content
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const content = e.target.result;
                this.processImportData(content, file);
            } catch (error) {
                console.error('Error reading file:', error);
                alert('Fout bij het lezen van het bestand: ' + error.message);
            }
        };
        
        reader.onerror = () => {
            alert('Fout bij het lezen van het bestand.');
        };
        
        reader.readAsText(file);
    }
    
    processImportData(content, file) {
        console.log('📊 Processing import data from:', file.name);
        
        try {
            // Try to parse as JSON
            const data = JSON.parse(content);
            
            // Validate data structure
            const validation = this.validateImportData(data);
            if (!validation.isValid) {
                alert('Ongeldig bestand: ' + validation.error);
                return;
            }
            
            // Show preview
            this.showImportPreview(data, file, validation);
            
            // Enable import button
            document.getElementById('execute-import-btn').disabled = false;
            
            // Store data for import
            window.currentImportData = data;
            
        } catch (error) {
            console.error('JSON parse error:', error);
            alert('Bestand kan niet gelezen worden als JSON: ' + error.message);
        }
    }
    
    validateImportData(data) {
        // Check for different export formats
        
        // Complete export format
        if (data.metadata && data.quiz_data && data.quiz_data.blocks) {
            return {
                isValid: true,
                format: 'complete_export',
                blockCount: Object.keys(data.quiz_data.blocks).length
            };
        }
        
        // Quiz structure format
        if (data.blocks && typeof data.blocks === 'object') {
            return {
                isValid: true,
                format: 'quiz_structure',
                blockCount: Object.keys(data.blocks).length
            };
        }
        
        // JSON Editor format
        if (data.questions && data.mapping) {
            return {
                isValid: true,
                format: 'json_editor',
                questionCount: data.questions.length,
                mappingCount: data.mapping.length
            };
        }
        
        return {
            isValid: false,
            error: 'Onbekend bestandsformaat. Zorg ervoor dat het bestand geëxporteerd is uit deze Quiz Builder.'
        };
    }
    
    showImportPreview(data, file, validation) {
        const fileInfoDiv = document.getElementById('import-file-info');
        const previewDiv = document.getElementById('import-data-preview');
        const previewContainer = document.getElementById('import-preview');
        const optionsContainer = document.getElementById('import-options');
        
        // File info
        const fileSize = (file.size / 1024).toFixed(1) + ' KB';
        const formatInfo = this.getFormatDescription(validation.format, validation);
        
        fileInfoDiv.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong>📄 ${file.name}</strong> (${fileSize})
                    <br><span style="color: #666;">${formatInfo}</span>
                </div>
                <div style="color: #00a32a; font-weight: bold;">✓ Geldig</div>
            </div>
        `;
        
        // Data preview
        const previewText = JSON.stringify(data, null, 2);
        const truncatedPreview = previewText.length > 2000 
            ? previewText.substring(0, 2000) + '\n\n... (inhoud ingekort voor preview)'
            : previewText;
        
        previewDiv.textContent = truncatedPreview;
        
        // Show containers
        previewContainer.style.display = 'block';
        optionsContainer.style.display = 'block';
    }
    
    getFormatDescription(format, validation) {
        switch (format) {
            case 'complete_export':
                return `Volledige Quiz Export - ${validation.blockCount} blokken met metadata`;
            case 'quiz_structure':
                return `Quiz Structuur - ${validation.blockCount} blokken`;
            case 'json_editor':
                return `JSON Editor Format - ${validation.questionCount} vragen, ${validation.mappingCount} mappings`;
            default:
                return 'Onbekend formaat';
        }
    }
    
    executeImport(data, mode) {
        console.log('🚀 Executing import with mode:', mode);
        console.log('📊 Import data structure:', {
            hasMetadata: !!data.metadata,
            hasQuizData: !!data.quiz_data,
            hasBlocks: !!(data.blocks || data.quiz_data?.blocks),
            blockCount: Object.keys(data.blocks || data.quiz_data?.blocks || {}).length
        });
        
        if (!confirm(`Weet je zeker dat je de quiz wilt ${mode === 'replace' ? 'vervangen' : 'samenvoegen'}? Deze actie kan niet ongedaan worden gemaakt.`)) {
            return;
        }
        
        try {
            // Close modal first
            document.getElementById('import-modal')?.remove();
            
            if (mode === 'replace') {
                // Clear current quiz
                this.clearAll(true); // true = silent clear
            }
            
            // Import the data based on format
            const validation = this.validateImportData(data);
            this.importDataByFormat(data, validation.format);
            
            // Show success message
            const notice = document.createElement('div');
            notice.className = 'notice notice-success is-dismissible';
            notice.innerHTML = `<p><strong>Import succesvol!</strong> Quiz is ${mode === 'replace' ? 'vervangen' : 'samengevoegd'}.</p>`;
            document.querySelector('.wrap').insertBefore(notice, document.querySelector('.wrap').firstChild);
            
            // Auto-remove notice after 5 seconds
            setTimeout(() => {
                if (notice.parentNode) {
                    notice.parentNode.removeChild(notice);
                }
            }, 5000);
            
            console.log('✅ Import completed successfully');
            
        } catch (error) {
            console.error('Import error:', error);
            alert('Fout bij importeren: ' + error.message);
        }
    }
    
    importDataByFormat(data, format) {
        switch (format) {
            case 'complete_export':
                this.importCompleteExport(data);
                break;
            case 'quiz_structure':
                this.importQuizStructure(data);
                break;
            case 'json_editor':
                // JSON Editor format is not directly importable to visual builder
                alert('JSON Editor formaat kan niet direct geïmporteerd worden naar de Visual Builder. Gebruik een Visual Builder export bestand.');
                break;
            default:
                throw new Error('Onbekend import formaat');
        }
    }
    
    importCompleteExport(data) {
        console.log('Importing complete export data');
        const quizData = data.quiz_data;
        this.importQuizStructure(quizData);
    }
    
    importQuizStructure(data) {
        console.log('Importing quiz structure data', data);
        
        // Import blocks
        if (data.blocks) {
            Object.entries(data.blocks).forEach(([blockId, blockData]) => {
                console.log(`Importing block: ${blockId}`, blockData);
                
                // Ensure the block data has the correct ID and type
                const completeBlockData = {
                    ...blockData,
                    id: blockId,
                    type: blockData.type
                };
                
                // Add block to visual builder with complete data
                const blockElement = this.addBlock(blockData.type, completeBlockData);
                
                // Update counter to avoid ID conflicts
                const idNum = parseInt(blockId.replace('block_', ''));
                if (!isNaN(idNum) && idNum >= this.blockCounter) {
                    this.blockCounter = idNum + 1;
                }
                
                console.log(`Successfully imported block ${blockId}`);
            });
        }
        
        // Import connections - they are stored in the blocks themselves
        if (data.blocks) {
            Object.entries(data.blocks).forEach(([blockId, blockData]) => {
                if (blockData.connections && Array.isArray(blockData.connections)) {
                    // Convert array of connections to connection mapping
                    const connectionMap = {};
                    blockData.connections.forEach((targetId, index) => {
                        if (targetId) {
                            connectionMap[index] = targetId;
                        }
                    });
                    if (Object.keys(connectionMap).length > 0) {
                        this.connections.set(blockId, connectionMap);
                        console.log(`Imported connections for ${blockId}:`, connectionMap);
                    }
                }
            });
        }
        
        // Also handle separate connections object if it exists
        if (data.connections && Object.keys(data.connections).length > 0) {
            Object.entries(data.connections).forEach(([fromBlock, toBlocks]) => {
                this.connections.set(fromBlock, toBlocks);
            });
        }
        
        // Ensure visual updates after all blocks are loaded
        setTimeout(() => {
            this.updateConnections();
            this.validateBlocks();
            
            // Re-render all blocks to show correct content
            this.blocks.forEach((blockData, blockId) => {
                const blockElement = document.querySelector(`[data-block-id="${blockId}"]`);
                if (blockElement) {
                    const content = blockElement.querySelector('.block-content');
                    if (content) {
                        if (blockData.type === 'question') {
                            this.populateQuestionContent(content, blockData);
                        } else {
                            this.populateResultContent(content, blockData);
                        }
                    }
                }
            });
            
            console.log('✅ Import rendering completed');
        }, 300);
    }
    
}

// Global instance
let quizBuilder;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 QUIZ BUILDER: DOM ready, looking for container...');
    if (document.getElementById('quiz-builder-container')) {
        console.log('🔧 QUIZ BUILDER: Container found, initializing...');
        try {
            quizBuilder = new QuizBuilder();
            console.log('🔧 QUIZ BUILDER: Successfully initialized!');
            
            // Make available globally for onclick handlers
            window.quizBuilder = quizBuilder;
        } catch (error) {
            console.error('🔥 QUIZ BUILDER ERROR:', error);
            console.error('Stack:', error.stack);
        }
    } else {
        console.log('🔧 QUIZ BUILDER: Container not found');
    }
});
