/**
 * Inventory Module - JE Alumni Connect
 * Inventory management functionality including search, filters, and CRUD operations
 */

// Dependencies: Functions from core.js
function initInventoryImagePreview() {
    const itemImageElement = document.getElementById('itemImage');
    
    if (itemImageElement) {
        itemImageElement.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Show progress bar
                const progressContainer = document.getElementById('uploadProgress');
                const progressBar = document.getElementById('uploadProgressBar');
                const progressText = document.getElementById('uploadProgressText');
                const imagePreview = document.getElementById('imagePreview');
                
                if (progressContainer && progressBar && progressText && imagePreview) {
                    // Hide preview initially
                    imagePreview.style.display = 'none';
                    progressContainer.style.display = 'block';
                    progressBar.style.width = '0%';
                    progressBar.setAttribute('aria-valuenow', '0');
                    progressText.textContent = '0%';
                    
                    // Use FileReader to show preview (reading is fast for local files)
                    const reader = new FileReader();
                    
                    // Track reading progress
                    reader.onprogress = function(e) {
                        if (e.lengthComputable) {
                            const percentComplete = Math.round((e.loaded / e.total) * 100);
                            progressBar.style.width = percentComplete + '%';
                            progressBar.setAttribute('aria-valuenow', percentComplete);
                            progressText.textContent = percentComplete + '%';
                        }
                    };
                    
                    reader.onload = function(e) {
                        // Ensure progress shows 100%
                        progressBar.style.width = '100%';
                        progressBar.setAttribute('aria-valuenow', '100');
                        progressText.textContent = '100%';
                        
                        setTimeout(() => {
                            progressContainer.style.display = 'none';
                            // Show preview
                            const previewImg = document.getElementById('previewImg');
                            if (previewImg && imagePreview) {
                                previewImg.src = e.target.result;
                                imagePreview.style.display = 'block';
                            }
                        }, 300);
                    };
                    
                    reader.onerror = function() {
                        progressContainer.style.display = 'none';
                        showToast('Fehler beim Laden der Vorschau', 'danger');
                    };
                    
                    reader.readAsDataURL(file);
                }
            } else {
                const imagePreview = document.getElementById('imagePreview');
                const uploadProgress = document.getElementById('uploadProgress');
                if (imagePreview) imagePreview.style.display = 'none';
                if (uploadProgress) uploadProgress.style.display = 'none';
            }
        });
    }
}

/**
 * Perfect Search - Live AJAX Search with Multi-Filter Support
 * Current filter state
 */
let currentFilters = {
    search: '',
    category: 'all',
    location: 'all',
    status: 'all'
};

/**
 * Check if any search filters are active
 * @return {boolean} True if search text or any filter is not set to default
 */
function hasActiveFilters() {
    return !!(currentFilters.search || 
              currentFilters.category !== 'all' || 
              currentFilters.location !== 'all' || 
              currentFilters.status !== 'all');
}

/**
 * Reset all filters to default values
 */
function resetFilters() {
    // Reset filter state
    currentFilters = {
        search: '',
        category: 'all',
        location: 'all',
        status: 'all'
    };
    
    // Reset UI elements
    const searchInput = document.getElementById('liveSearchInput');
    if (searchInput) {
        searchInput.value = '';
    }
    
    // Reset location filters (both desktop and mobile)
    const locationFilter = document.getElementById('locationFilter');
    const locationFilterMobile = document.getElementById('locationFilterMobile');
    if (locationFilter) {
        locationFilter.value = 'all';
    }
    if (locationFilterMobile) {
        locationFilterMobile.value = 'all';
    }
    
    // Reset all filter pills to 'all'
    const filterPills = document.querySelectorAll('.filter-pill');
    filterPills.forEach(pill => {
        const value = pill.getAttribute('data-value');
        if (value === 'all') {
            pill.classList.add('active');
        } else {
            pill.classList.remove('active');
        }
    });
    
    // Trigger new search
    performSearch();
}

/**
 * Initialize Perfect Search functionality
 * Sets up live search with debounce and multi-filter support
 */
function initPerfectSearch() {
    const searchInput = document.getElementById('liveSearchInput');
    
    if (!searchInput) return;
    
    // Debounced search function
    const debouncedSearch = debounce(() => {
        performSearch();
    }, 300);
    
    // Listen to search input with debounce
    searchInput.addEventListener('input', function(e) {
        currentFilters.search = e.target.value;
        debouncedSearch();
    });
}

/**
 * Apply filter and trigger search
 * @param {string} filterType - Type of filter (category, location, status)
 * @param {string} value - Filter value
 * @param {HTMLElement} element - Clicked filter pill element
 */
function applyFilter(filterType, value, element) {
    // Update active state for this filter type
    const filterGroup = element.closest('.filter-pills');
    if (filterGroup) {
        const pills = filterGroup.querySelectorAll('.filter-pill');
        pills.forEach(pill => pill.classList.remove('active'));
        element.classList.add('active');
    }
    
    // Update current filter state
    currentFilters[filterType] = value;
    
    // Perform search with updated filters
    performSearch();
}

/**
 * Generate error state HTML template
 * @return {string} HTML template for error state
 */
function getErrorStateTemplate() {
    return `
        <div class="col-12">
            <div class="card glass-card text-center py-5 px-4 border-danger" style="max-width: 600px; margin: 0 auto;">
                <div class="card-body">
                    <div class="error-state-icon mb-4">
                        <i class="fas fa-exclamation-triangle fa-5x text-danger" style="opacity: 0.5;"></i>
                    </div>
                    <h3 class="mb-3 text-danger">Fehler beim Laden</h3>
                    <p class="text-muted mb-4">
                        Es ist ein Fehler beim Laden der Inventardaten aufgetreten. 
                        Bitte überprüfen Sie Ihre Internetverbindung und versuchen Sie es erneut.
                    </p>
                    <button type="button" class="btn btn-danger btn-lg" id="retrySearchBtn">
                        <i class="fas fa-sync-alt me-2"></i>Erneut versuchen
                    </button>
                </div>
            </div>
        </div>
    `;
}

/**
 * Generate empty state HTML template
 * @param {boolean} hasFilters - Whether filters are currently active
 * @return {string} HTML template for empty state
 */
function getEmptyStateTemplate(hasFilters) {
    // Build specific message based on active filters
    let filterMessage = '';
    if (hasFilters) {
        const filterParts = [];
        
        if (currentFilters.search) {
            filterParts.push(`Suchbegriff "${currentFilters.search}"`);
        }
        if (currentFilters.category !== 'all') {
            // Get the category name from the select element for display
            const categorySelect = document.getElementById('categoryFilter');
            const categoryOption = categorySelect?.querySelector(`option[value="${currentFilters.category}"]`);
            const categoryName = categoryOption?.textContent || currentFilters.category;
            filterParts.push(`Kategorie "${categoryName}"`);
        }
        if (currentFilters.location !== 'all') {
            // Get the location name from the select element for display
            const locationSelect = document.getElementById('locationFilter');
            const locationOption = locationSelect?.querySelector(`option[value="${currentFilters.location}"]`);
            const locationName = locationOption?.textContent || currentFilters.location;
            filterParts.push(`Standort "${locationName}"`);
        }
        if (currentFilters.status !== 'all') {
            // Get the status name from the select element for display
            const statusSelect = document.getElementById('statusFilter');
            const statusOption = statusSelect?.querySelector(`option[value="${currentFilters.status}"]`);
            const statusName = statusOption?.textContent || currentFilters.status;
            filterParts.push(`Status "${statusName}"`);
        }
        
        if (filterParts.length > 0) {
            filterMessage = `Keine Gegenstände gefunden für: ${filterParts.join(', ')}.`;
        }
    }
    
    return `
        <div class="col-12">
            <div class="card glass-card text-center py-5 px-4" style="max-width: 600px; margin: 0 auto;">
                <div class="card-body">
                    <div class="mb-4 empty-state-icon">
                        <i class="fas fa-box-open fa-5x text-primary"></i>
                    </div>
                    <h3 class="mb-3">Keine Gegenstände gefunden</h3>
                    ${hasFilters ? `
                        <p class="text-muted mb-4">
                            ${filterMessage || 'Ihre Suche oder Filter ergab keine Treffer.'}<br>
                            Versuchen Sie andere Suchbegriffe oder setzen Sie die Filter zurück.
                        </p>
                        <button type="button" class="btn btn-primary btn-lg" id="resetFiltersBtn">
                            <i class="fas fa-redo me-2"></i>Filter zurücksetzen
                        </button>
                    ` : `
                        <p class="text-muted mb-4">
                            Es sind noch keine Gegenstände im Inventar vorhanden.
                        </p>
                    `}
                </div>
            </div>
        </div>
    `;
}

/**
 * Perform AJAX search with current filters
 * Shows skeleton loaders during loading
 */
function performSearch() {
    const inventoryContainer = document.getElementById('inventoryContainer');
    const searchSpinner = document.getElementById('searchSpinner');
    
    if (!inventoryContainer) return;
    
    // Show loading spinner
    if (searchSpinner) {
        searchSpinner.style.display = 'flex';
    }
    
    // Show skeleton loaders
    showSkeletonLoaders(inventoryContainer);
    
    // Build form data
    const formData = new FormData();
    formData.append('action', 'search');
    formData.append('search', currentFilters.search);
    formData.append('category', currentFilters.category);
    formData.append('location', currentFilters.location);
    formData.append('status', currentFilters.status);
    // Note: search is a read-only operation, so CSRF token is not required
    
    // Perform AJAX request with consistent error handling
    fetch(buildApiUrl('index.php?page=inventory'), {
        method: 'POST',
        headers: addCsrfHeader({}),
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Update inventory grid with results
            renderInventoryItems(data.items, inventoryContainer);
            
            // Show toast notification if no results found and there's an active search/filter
            if (data.items.length === 0 && hasActiveFilters()) {
                showToast('Keine Ergebnisse gefunden. Versuchen Sie andere Suchbegriffe oder Filter.', 'info');
            }
            
            // Refresh AOS animations only after successful search
            if (typeof AOS !== 'undefined') {
                AOS.refresh();
            }
        } else {
            console.error('Search error:', data.message);
            showToast('Fehler beim Suchen: ' + (data.message || 'Unbekannter Fehler'), 'danger');
        }
    })
    .catch(error => {
        console.error('Error performing search:', error);
        
        // Show error UI in the container with retry button
        if (inventoryContainer) {
            inventoryContainer.innerHTML = getErrorStateTemplate();
            
            // Add event listener for retry button (remove any existing first)
            const retryBtn = document.getElementById('retrySearchBtn');
            if (retryBtn) {
                // Use replaceWith to ensure clean event listener attachment
                const newRetryBtn = retryBtn.cloneNode(true);
                retryBtn.replaceWith(newRetryBtn);
                newRetryBtn.addEventListener('click', performSearch);
            }
        }
        
        showToast('Ein Fehler ist aufgetreten beim Suchen.', 'danger');
    })
    .finally(() => {
        // Hide loading spinner
        if (searchSpinner) {
            searchSpinner.style.display = 'none';
        }
    });
}

/**
 * Show skeleton loaders in the inventory grid using Bootstrap placeholder classes
 * @param {HTMLElement} container - Inventory grid container
 * @param {number} count - Number of skeleton items to show (default: 8)
 */
function showSkeletonLoaders(container, count = 8) {
    const skeletonCards = [];
    
    for (let i = 0; i < count; i++) {
        skeletonCards.push(`
            <div class="col" aria-hidden="true">
                <div class="card glass-card inventory-card h-100">
                    <div class="placeholder-glow">
                        <div class="inventory-image-container">
                            <div class="placeholder col-12 bg-secondary" style="width: 100%; height: 100%;"></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="placeholder-glow mb-2">
                            <span class="placeholder col-8 bg-secondary"></span>
                        </div>
                        <div class="placeholder-glow mb-3">
                            <span class="placeholder col-12 bg-secondary"></span>
                            <span class="placeholder col-10 bg-secondary"></span>
                        </div>
                        <div class="placeholder-glow">
                            <span class="placeholder col-6 bg-secondary"></span>
                            <span class="placeholder col-5 bg-secondary"></span>
                        </div>
                    </div>
                </div>
            </div>
        `);
    }
    
    container.innerHTML = skeletonCards.join('');
}

/**
 * Show inventory skeletons during AJAX loading
 * Displays 6 gray placeholder cards with pulse animation
 * This is the main function for showing loading state in the inventory module
 */
function showInventorySkeletons() {
    const inventoryContainer = document.getElementById('inventoryContainer');
    if (inventoryContainer) {
        showSkeletonLoaders(inventoryContainer, 6);
    }
}

/**
 * Create skeleton loaders for alumni profiles using Bootstrap placeholder classes
 * @param {number} count - Number of skeleton items to show (default: 6)
 * @returns {string} HTML string containing skeleton cards
 */
function createAlumniSkeleton(count = 6) {
    const skeletonCards = [];
    
    for (let i = 0; i < count; i++) {
        skeletonCards.push(`
            <div class="col" aria-hidden="true">
                <div class="card glass-card h-100">
                    <div class="card-body p-4">
                        <!-- Profile Header with Avatar -->
                        <div class="d-flex align-items-start mb-3">
                            <!-- Avatar Placeholder -->
                            <div class="flex-shrink-0 me-3">
                                <div class="placeholder-glow">
                                    <div class="placeholder bg-secondary" style="width: 80px; height: 80px; border-radius: 50%;"></div>
                                </div>
                            </div>
                            
                            <!-- Profile Info Placeholder -->
                            <div class="flex-grow-1">
                                <div class="placeholder-glow mb-2">
                                    <span class="placeholder col-8 bg-secondary"></span>
                                </div>
                                <div class="placeholder-glow">
                                    <span class="placeholder col-6 bg-secondary"></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Position and Company Placeholder -->
                        <div class="mb-3">
                            <div class="placeholder-glow mb-2">
                                <span class="placeholder col-10 bg-secondary"></span>
                            </div>
                            <div class="placeholder-glow">
                                <span class="placeholder col-8 bg-secondary"></span>
                            </div>
                        </div>
                        
                        <!-- Bio Placeholder -->
                        <div class="mb-3">
                            <div class="placeholder-glow">
                                <span class="placeholder col-12 bg-secondary"></span>
                                <span class="placeholder col-12 bg-secondary"></span>
                                <span class="placeholder col-9 bg-secondary"></span>
                            </div>
                        </div>
                        
                        <!-- Badges Placeholder -->
                        <div class="mb-3">
                            <div class="placeholder-glow">
                                <span class="placeholder col-4 bg-secondary" style="height: 24px; display: inline-block; border-radius: 12px; margin-right: 8px;"></span>
                                <span class="placeholder col-4 bg-secondary" style="height: 24px; display: inline-block; border-radius: 12px;"></span>
                            </div>
                        </div>
                        
                        <!-- Contact Buttons Placeholder -->
                        <div class="d-flex gap-2 pt-2 border-top">
                            <div class="placeholder-glow flex-fill">
                                <span class="placeholder col-12 bg-secondary" style="height: 32px; display: block; border-radius: 6px;"></span>
                            </div>
                            <div class="placeholder-glow flex-fill">
                                <span class="placeholder col-12 bg-secondary" style="height: 32px; display: block; border-radius: 6px;"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `);
    }
    
    return skeletonCards.join('');
}

/**
 * Render inventory items in the grid using DocumentFragment to minimize reflows
 * DocumentFragment reduces DOM manipulation overhead by batching all DOM insertions
 * into a single operation, preventing multiple layout recalculations
 * @param {Array} items - Array of inventory items
 * @param {HTMLElement} container - Inventory grid container
 */
function renderInventoryItems(items, container) {
    if (items.length === 0) {
        // Check if filters are active to show appropriate empty state
        const hasFilters = hasActiveFilters();
        
        container.innerHTML = getEmptyStateTemplate(hasFilters);
        
        // Add event listener for reset button if filters are active
        if (hasFilters) {
            const resetBtn = document.getElementById('resetFiltersBtn');
            if (resetBtn) {
                // Use replaceWith to ensure clean event listener attachment
                const newResetBtn = resetBtn.cloneNode(true);
                resetBtn.replaceWith(newResetBtn);
                newResetBtn.addEventListener('click', resetFilters);
            }
        }
        
        return;
    }
    
    const canEdit = document.querySelector('[onclick*="openEditModal"]') !== null;
    
    // Use DocumentFragment for better performance and fewer reflows
    const fragment = document.createDocumentFragment();
    
    items.forEach((item, index) => {
        // Normalize category for data-attribute (remove umlauts and special chars)
        const category = (item.category || '').toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '') // Remove diacritics
            .replace(/[äöüß]/g, m => ({ä: 'ae', ö: 'oe', ü: 'ue', ß: 'ss'}[m] || m));
        
        // Determine status indicator
        const quantity = parseInt(item.quantity || 0);
        const status = item.status || 'active';
        let statusColor = 'success';
        let statusTitle = 'Auf Lager';
        
        if (quantity === 0 || status === 'broken') {
            statusColor = 'danger';
            statusTitle = 'Nicht verfügbar';
        } else if (quantity >= 1 && quantity <= 5) {
            statusColor = 'warning';
            statusTitle = 'Niedriger Bestand';
        }
        
        const outOfStock = quantity === 0 ? 'out-of-stock' : '';
        const imagePath = item.image_path ? 
            `/assets/uploads/inventory/${encodeURIComponent(item.image_path)}` : '';
        
        // Create wrapper div
        const colDiv = document.createElement('div');
        colDiv.className = 'col';
        colDiv.setAttribute('data-aos', 'fade-up');
        colDiv.setAttribute('data-aos-delay', String((index % 4) * 100));
        
        // Build HTML content for this item
        const itemHTML = `
            <div class="card glass-card inventory-card h-100 ${outOfStock}" 
                 data-category="${escapeHtml(category)}" 
                 data-item-id="${item.id}">
                <!-- Status Indicator -->
                <div class="status-indicator">
                    <i class="fas fa-circle text-${statusColor}" title="${statusTitle}"></i>
                </div>
                
                <!-- Image with fixed aspect ratio -->
                <div class="inventory-image-container">
                    ${imagePath ? 
                        `<img src="${imagePath}" class="inventory-image" alt="${escapeHtml(item.name)}" loading="lazy">` :
                        `<div class="inventory-image-placeholder">
                            <i class="fas fa-image fa-3x text-white-50"></i>
                        </div>`
                    }
                    
                    ${canEdit ? `
                        <div class="inventory-actions">
                            <button type="button" class="btn btn-sm btn-light me-2" 
                                    data-action="edit-inventory"
                                    data-item-id="${item.id}"
                                    title="Bearbeiten">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" 
                                    data-action="delete-inventory"
                                    data-item-id="${item.id}"
                                    title="Löschen">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    ` : ''}
                    
                    <!-- Quantity Badge - floating on top right -->
                    ${quantity === 0 ? 
                        '<span class="badge bg-danger inventory-badge">Nicht verfügbar</span>' :
                        `<span class="badge bg-success inventory-badge">${quantity}x</span>`
                    }
                </div>
                
                <!-- Card Body -->
                <div class="card-body">
                    <h5 class="card-title fw-bold">${escapeHtml(item.name)}</h5>
                    
                    ${item.description ? 
                        `<p class="card-text text-muted small">${escapeHtml(item.description)}</p>` : 
                        ''
                    }
                    
                    ${canEdit ? `
                        <div class="mobile-quantity-controls d-lg-none mt-3 mb-3">
                            <div class="d-flex align-items-center justify-content-center gap-3">
                                <button type="button" 
                                        class="btn btn-lg btn-outline-danger quantity-btn" 
                                        onclick="adjustQuantity(${item.id}, -1, this)"
                                        title="Menge verringern"
                                        ${quantity <= 0 ? 'disabled' : ''}>
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="quantity-display fs-4 fw-bold" data-item-id="${item.id}">
                                    ${quantity}
                                </span>
                                <button type="button" 
                                        class="btn btn-lg btn-outline-success quantity-btn" 
                                        onclick="adjustQuantity(${item.id}, 1, this)"
                                        title="Menge erhöhen">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    ` : ''}
                    
                    <div class="mt-3">
                        ${item.location ? 
                            `<span class="badge bg-primary me-2">
                                <i class="fas fa-map-marker-alt me-1"></i>${escapeHtml(item.location)}
                            </span>` : 
                            ''
                        }
                        
                        ${item.category ? 
                            `<span class="badge bg-info">
                                <i class="fas fa-tag me-1"></i>${escapeHtml(item.category)}
                            </span>` : 
                            ''
                        }
                    </div>
                </div>
            </div>
        `;
        
        colDiv.innerHTML = itemHTML;
        fragment.appendChild(colDiv);
    });
    
    // Clear container and append fragment in a single DOM operation
    container.innerHTML = '';
    container.appendChild(fragment);
}

/**
 * Escape HTML to prevent XSS
 * @param {string} text - Text to escape
 * @return {string} Escaped text
 */
function escapeHtml(text) {
    // Explicitly check for null and undefined
    if (text == null) return '';
    // Convert to string to handle non-string types
    if (typeof text !== 'string') text = String(text);
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Open create modal for inventory
 */
function openCreateModal() {
    const modalLabel = document.getElementById('inventoryModalLabel');
    const formAction = document.getElementById('formAction');
    const submitBtnText = document.getElementById('submitBtnText');
    const inventoryForm = document.getElementById('inventoryForm');
    const itemId = document.getElementById('itemId');
    const imagePreview = document.getElementById('imagePreview');
    
    if (modalLabel) modalLabel.textContent = 'Neuer Gegenstand';
    if (formAction) formAction.value = 'create';
    if (submitBtnText) submitBtnText.textContent = 'Erstellen';
    if (inventoryForm) {
        inventoryForm.reset();
        inventoryForm.classList.remove('was-validated');
    }
    if (itemId) itemId.value = '';
    if (imagePreview) imagePreview.style.display = 'none';
}

/**
 * Update inventory card UI after quantity change
 */
function updateInventoryCardUI(itemId, newQuantity) {
    const card = document.querySelector(`.inventory-card[data-item-id="${itemId}"]`);
    if (!card) return;
    
    // Update quantity display
    const quantityDisplay = card.querySelector(`.quantity-display[data-item-id="${itemId}"]`);
    if (quantityDisplay) {
        quantityDisplay.textContent = newQuantity;
    }
    
    // Update quantity badge
    const quantityBadge = card.querySelector('.inventory-badge');
    if (quantityBadge) {
        if (newQuantity === 0) {
            quantityBadge.className = 'badge bg-danger inventory-badge';
            quantityBadge.textContent = 'Nicht verfügbar';
        } else {
            quantityBadge.className = 'badge bg-success inventory-badge';
            quantityBadge.textContent = newQuantity + 'x';
        }
    }
    
    // Update status indicator
    const statusIndicator = card.querySelector('.status-indicator i');
    if (statusIndicator) {
        let statusColor = 'success';
        let title = 'Auf Lager';
        
        if (newQuantity === 0) {
            statusColor = 'danger';
            title = 'Nicht verfügbar';
        } else if (newQuantity >= 1 && newQuantity <= 5) {
            statusColor = 'warning';
            title = 'Niedriger Bestand';
        }
        
        statusIndicator.className = `fas fa-circle text-${statusColor}`;
        statusIndicator.setAttribute('title', title);
    }
    
    // Re-enable buttons
    const buttons = card.querySelectorAll('.quantity-btn');
    buttons.forEach(btn => {
        btn.disabled = false;
        // Disable minus button if quantity is 0
        if (btn.querySelector('.fa-minus')) {
            btn.disabled = (newQuantity <= 0);
        }
    });
    
    // Update card styling for out-of-stock
    if (newQuantity === 0) {
        card.classList.add('out-of-stock');
    } else {
        card.classList.remove('out-of-stock');
    }
}

/**
 * Adjust inventory quantity (for mobile buttons)
 */
function adjustQuantity(itemId, adjustment, buttonElement) {
    // Disable buttons temporarily using toggleButtonLoading
    const card = buttonElement.closest('.inventory-card');
    const buttons = card.querySelectorAll('.quantity-btn');
    buttons.forEach(btn => toggleButtonLoading(btn, true));
    
    const formData = new FormData();
    formData.append('action', 'adjust_quantity');
    formData.append('id', itemId);
    formData.append('adjustment', adjustment);
    formData.append('comment', ''); // Empty comment for quick adjustments
    
    // Add CSRF token for protection
    const csrfToken = getCsrfToken();
    if (csrfToken) {
        formData.append('csrf_token', csrfToken);
    }
    
    fetch(buildApiUrl('index.php?page=inventory'), {
        method: 'POST',
        headers: addCsrfHeader({}),
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            updateInventoryCardUI(itemId, data.newQuantity);
            // Show success toast notification
            const adjustmentText = adjustment > 0 ? `+${adjustment}` : `${adjustment}`;
            showToast(`Menge aktualisiert (${adjustmentText})`, 'success');
        } else {
            showToast('Fehler: ' + (data.message || 'Unbekannter Fehler'), 'danger');
        }
    })
    .catch(error => {
        console.error('Error adjusting quantity:', error);
        showToast('Ein Fehler ist aufgetreten.', 'danger');
    })
    .finally(() => {
        // Re-enable buttons
        buttons.forEach(btn => toggleButtonLoading(btn, false));
    });
}

/**
 * Open quantity comment modal
 */
function openQuantityCommentModal(itemId) {
    // Store the item ID in the modal
    document.getElementById('commentItemId').value = itemId;
    // Clear previous comment
    const commentField = document.getElementById('quantityComment');
    if (commentField) {
        commentField.value = '';
    }
    // Open the modal
    const modalElement = document.getElementById('quantityCommentModal');
    const modal = new bootstrap.Modal(modalElement);
    
    // Set focus to textarea when modal is fully shown
    // Using 'once: true' to automatically remove the listener after it fires
    modalElement.addEventListener('shown.bs.modal', function() {
        if (commentField) {
            commentField.focus();
        }
    }, { once: true });
    
    modal.show();
}

/**
 * Submit quantity adjustment with comment
 */
function submitQuantityAdjustment() {
    const itemId = document.getElementById('commentItemId').value;
    const comment = document.getElementById('quantityComment').value.trim();
    
    // Ask user for adjustment amount
    const adjustmentStr = prompt('Mengenänderung eingeben (z.B. +5, -3):');
    
    if (adjustmentStr === null) {
        // User cancelled
        return;
    }
    
    const adjustment = parseInt(adjustmentStr);
    
    if (isNaN(adjustment) || adjustment === 0) {
        showToast('Bitte geben Sie eine gültige Mengenänderung ein (z.B. +5 oder -3)', 'danger');
        return;
    }
    
    // Close the modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('quantityCommentModal'));
    modal.hide();
    
    // Find the card element for this item
    const card = document.querySelector(`.inventory-card[data-item-id="${itemId}"]`);
    if (!card) {
        showToast('Fehler: Karte nicht gefunden', 'danger');
        return;
    }
    
    // Disable quantity buttons
    const buttons = card.querySelectorAll('.quantity-btn');
    buttons.forEach(btn => toggleButtonLoading(btn, true));
    
    // Send AJAX request with comment
    const formData = new FormData();
    formData.append('action', 'adjust_quantity');
    formData.append('id', itemId);
    formData.append('adjustment', adjustment);
    formData.append('comment', comment);
    
    // Add CSRF token for protection
    const csrfToken = getCsrfToken();
    if (csrfToken) {
        formData.append('csrf_token', csrfToken);
    }
    
    fetch(buildApiUrl('index.php?page=inventory'), {
        method: 'POST',
        headers: addCsrfHeader({}),
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            updateInventoryCardUI(itemId, data.newQuantity);
            
            // Show success message with toast instead of alert
            if (comment) {
                showToast('Menge erfolgreich aktualisiert mit Kommentar: ' + comment, 'success');
            } else {
                showToast('Menge erfolgreich aktualisiert', 'success');
            }
        } else {
            showToast('Fehler: ' + (data.message || 'Unbekannter Fehler'), 'danger');
        }
    })
    .catch(error => {
        console.error('Error adjusting quantity:', error);
        showToast('Ein Fehler ist aufgetreten.', 'danger');
    })
    .finally(() => {
        // Re-enable buttons
        buttons.forEach(btn => toggleButtonLoading(btn, false));
    });
}

/**
 * Open edit modal for inventory
 */
function openEditModal(itemId) {
    const modalLabel = document.getElementById('inventoryModalLabel');
    const formAction = document.getElementById('formAction');
    const submitBtnText = document.getElementById('submitBtnText');
    const imagePreview = document.getElementById('imagePreview');
    const inventoryForm = document.getElementById('inventoryForm');
    
    if (modalLabel) modalLabel.textContent = 'Gegenstand bearbeiten';
    if (formAction) formAction.value = 'update';
    if (submitBtnText) submitBtnText.textContent = 'Aktualisieren';
    if (imagePreview) imagePreview.style.display = 'none';
    if (inventoryForm) inventoryForm.classList.remove('was-validated');
    
    // Find the button that triggered this action and add loading state
    const editButton = document.querySelector(`button[data-action="edit-inventory"][data-item-id="${itemId}"]`);
    if (editButton) {
        toggleButtonState(editButton, true);
    }
    
    // Fetch item data
    const formData = new FormData();
    formData.append('action', 'get');
    formData.append('id', itemId);
    // Note: 'get' is a read-only operation, so CSRF token is not required
    
    fetch(buildApiUrl('index.php?page=inventory'), {
        method: 'POST',
        headers: addCsrfHeader({}),
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const item = data.item;
            const itemIdField = document.getElementById('itemId');
            const itemName = document.getElementById('itemName');
            const itemDescription = document.getElementById('itemDescription');
            const itemLocation = document.getElementById('itemLocation');
            const itemCategory = document.getElementById('itemCategory');
            const itemQuantity = document.getElementById('itemQuantity');
            const itemPurchaseDate = document.getElementById('itemPurchaseDate');
            const itemTags = document.getElementById('itemTags');
            
            if (itemIdField) itemIdField.value = item.id;
            if (itemName) itemName.value = item.name || '';
            if (itemDescription) itemDescription.value = item.description || '';
            if (itemLocation) itemLocation.value = item.location || '';
            if (itemCategory) itemCategory.value = item.category || '';
            if (itemQuantity) itemQuantity.value = item.quantity || 0;
            if (itemPurchaseDate) itemPurchaseDate.value = item.purchase_date || '';
            if (itemTags) itemTags.value = item.tags || '';
            
            // Show current image if exists
            if (item.image_path) {
                const previewImg = document.getElementById('previewImg');
                if (previewImg && imagePreview) {
                    previewImg.src = BASE_URL + '/assets/uploads/inventory/' + encodeURIComponent(item.image_path);
                    imagePreview.style.display = 'block';
                }
            }
            
            // Open modal
            const modalElement = document.getElementById('inventoryModal');
            if (modalElement && typeof bootstrap !== 'undefined') {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            }
        } else {
            showToast('Fehler beim Laden des Gegenstands: ' + (data.message || 'Unbekannter Fehler'), 'danger');
        }
    })
    .catch(error => {
        console.error('Error loading item:', error);
        showToast('Ein Fehler ist aufgetreten beim Laden.', 'danger');
    })
    .finally(() => {
        // Restore button state
        if (editButton) {
            toggleButtonState(editButton, false);
        }
    });
}

/**
 * Delete inventory item
 */
function deleteItem(itemId) {
    if (!confirm('Möchten Sie diesen Gegenstand wirklich löschen?')) {
        return;
    }
    
    // Find the delete button and add loading state
    const deleteButton = document.querySelector(`button[data-action="delete-inventory"][data-item-id="${itemId}"]`);
    if (deleteButton) {
        toggleButtonState(deleteButton, true);
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', itemId);
    
    // Add CSRF token for protection
    const csrfToken = getCsrfToken();
    if (csrfToken) {
        formData.append('csrf_token', csrfToken);
    }
    
    fetch(buildApiUrl('index.php?page=inventory'), {
        method: 'POST',
        headers: addCsrfHeader({}),
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Reload page (button state restoration not needed as page reloads)
            window.location.reload();
        } else {
            showToast('Fehler beim Löschen: ' + (data.message || 'Unbekannter Fehler'), 'danger');
            // Restore button state on error
            if (deleteButton) {
                toggleButtonState(deleteButton, false);
            }
        }
    })
    .catch(error => {
        console.error('Error deleting item:', error);
        showToast('Ein Fehler ist aufgetreten beim Löschen.', 'danger');
        // Restore button state on error
        if (deleteButton) {
            toggleButtonState(deleteButton, false);
        }
    });
}

/**
 * Filter inventory by category
 */
function filterCategory(category, element) {
    // Update active pill
    const pills = document.querySelectorAll('.filter-pill');
    pills.forEach(pill => {
        pill.classList.remove('active');
    });
    if (element) {
        element.classList.add('active');
    }
    
    // Filter cards by data-category attribute
    const cards = document.querySelectorAll('.inventory-card');
    cards.forEach(card => {
        const cardCategory = card.getAttribute('data-category') || '';
        const cardParent = card.closest('.col-12');
        
        if (category === 'all' || cardCategory === category) {
            if (cardParent) cardParent.style.display = 'block';
        } else {
            if (cardParent) cardParent.style.display = 'none';
        }
    });
}

/**
 * Initialize inventory form submission with modern fetch API
 */
function initInventoryFormSubmit() {
    const inventoryFormElement = document.getElementById('inventoryForm');
    if (inventoryFormElement) {
        inventoryFormElement.addEventListener('submit', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Check Bootstrap validation
            if (!this.checkValidity()) {
                this.classList.add('was-validated');
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const submitSpinner = document.getElementById('submitSpinner');
            
            // Show loading state using toggleButtonLoading
            if (submitBtn) {
                toggleButtonLoading(submitBtn, true);
            }
            // Keep submitSpinner for backward compatibility if it exists separately
            if (submitSpinner) submitSpinner.style.display = 'inline-block';
            
            const formData = new FormData(this);
            
            // Add CSRF token for protection (for create/update actions)
            const csrfToken = getCsrfToken();
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }
            
            try {
                // Use modern fetch API
                const response = await fetch(buildApiUrl('index.php?page=inventory'), {
                    method: 'POST',
                    headers: addCsrfHeader({}),
                    body: formData
                });
                
                // Check response status
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    // Close modal and reload page
                    const modalElement = document.getElementById('inventoryModal');
                    if (modalElement && typeof bootstrap !== 'undefined') {
                        const modalInstance = bootstrap.Modal.getInstance(modalElement);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                    }
                    window.location.reload();
                } else {
                    showToast('Fehler: ' + (data.message || 'Unbekannter Fehler'), 'danger');
                    if (submitBtn) toggleButtonLoading(submitBtn, false);
                    if (submitSpinner) submitSpinner.style.display = 'none';
                }
            } catch (error) {
                console.error('Error submitting form:', error);
                showToast('Ein Fehler ist aufgetreten beim Speichern.', 'danger');
                if (submitBtn) toggleButtonLoading(submitBtn, false);
                if (submitSpinner) submitSpinner.style.display = 'none';
            }
        });
    }
}

/**
 * Initialize drag and drop for inventory image upload
 */
function initInventoryDragDrop() {
    const dragDropZone = document.getElementById('dragDropZone');
    const itemImageInput = document.getElementById('itemImage');
    
    if (dragDropZone && itemImageInput) {
        // Click to select file
        dragDropZone.addEventListener('click', () => {
            itemImageInput.click();
        });
        
        // Drag and drop events
        dragDropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dragDropZone.classList.add('dragover');
        });
        
        dragDropZone.addEventListener('dragleave', () => {
            dragDropZone.classList.remove('dragover');
        });
        
        dragDropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dragDropZone.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                itemImageInput.files = files;
                // Trigger change event for preview
                const event = new Event('change', { bubbles: true });
                itemImageInput.dispatchEvent(event);
            }
        });
    }
}

/**
 * Initialize all inventory-related functionality
 * Called on DOMContentLoaded for inventory pages
 */
function initInventoryManagement() {
    initInventoryImagePreview();
    initInventoryFormSubmit();
    initInventoryDragDrop();
    initPerfectSearch();
    initInventoryEventDelegation();
    initInventoryBackToTop();
    initQuickAddLocation();
}

/**
 * Initialize Back to Top button for inventory page
 * Shows button when user scrolls more than INVENTORY_BACK_TO_TOP_THRESHOLD
 * Uses throttling to improve performance
 * Note: This is separate from the global back-to-top button and only appears on inventory page
 */
function initInventoryBackToTop() {
    const backToTopBtn = document.getElementById('inventoryBackToTop');
    
    if (!backToTopBtn) return;
    
    let isVisible = false;
    let scrollTimeout = null;
    
    // Throttled scroll handler - executes at most once every 100ms
    function handleScroll() {
        if (scrollTimeout) return;
        
        scrollTimeout = setTimeout(() => {
            const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollPosition > INVENTORY_BACK_TO_TOP_THRESHOLD) {
                if (!isVisible) {
                    backToTopBtn.style.display = 'block';
                    backToTopBtn.classList.remove('hide');
                    backToTopBtn.classList.add('show');
                    isVisible = true;
                }
            } else {
                if (isVisible) {
                    backToTopBtn.classList.remove('show');
                    backToTopBtn.classList.add('hide');
                    // Hide after animation completes
                    setTimeout(() => {
                        if (!backToTopBtn.classList.contains('show')) {
                            backToTopBtn.style.display = 'none';
                        }
                    }, FADE_ANIMATION_DURATION);
                    isVisible = false;
                }
            }
            
            scrollTimeout = null;
        }, 100);
    }
    
    window.addEventListener('scroll', handleScroll, { passive: true });
}

/**
 * Initialize event delegation for inventory actions
 * Uses event delegation to handle dynamically loaded content
 */
function initInventoryEventDelegation() {
    // Use event delegation on document for all inventory actions
    document.addEventListener('click', function(e) {
        const target = e.target.closest('[data-action]');
        if (!target) return;
        
        const action = target.getAttribute('data-action');
        const itemIdStr = target.getAttribute('data-item-id');
        const itemId = itemIdStr ? parseInt(itemIdStr) : null;
        
        switch(action) {
            case 'create-inventory':
                openCreateModal();
                break;
                
            case 'edit-inventory':
                if (itemId) {
                    openEditModal(itemId);
                }
                break;
                
            case 'delete-inventory':
                if (itemId) {
                    deleteItem(itemId);
                }
                break;
                
            case 'adjust-quantity':
                const adjustment = parseInt(target.getAttribute('data-adjustment'));
                if (itemId && !isNaN(adjustment) && adjustment !== 0) {
                    adjustQuantity(itemId, adjustment, target);
                }
                break;
                
            case 'quantity-comment':
                if (itemId) {
                    openQuantityCommentModal(itemId);
                }
                break;
                
            case 'submit-quantity-comment':
                submitQuantityAdjustment();
                break;
        }
    });
    
    // Filter pills - use event delegation
    document.addEventListener('click', function(e) {
        const pill = e.target.closest('.filter-pill');
        if (!pill) return;
        
        const filterType = pill.getAttribute('data-filter-type');
        const value = pill.getAttribute('data-value');
        
        if (filterType && value) {
            applyFilter(filterType, value, pill);
        }
    });
    
    // Filter pills - keyboard accessibility
    document.addEventListener('keydown', function(e) {
        const pill = e.target.closest('.filter-pill');
        if (!pill) return;
        
        // Activate on Enter or Space
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            const filterType = pill.getAttribute('data-filter-type');
            const value = pill.getAttribute('data-value');
            
            if (filterType && value) {
                applyFilter(filterType, value, pill);
            }
        }
    });
}

/**
 * Initialize button loading spinners
 * Adds loading spinner to buttons on click to improve perceived performance
 */
function initButtonLoadingSpinners() {
    // Select all buttons that should show loading spinners
    // Exclude modal close buttons, dropdown toggles, and regular type="button" used for JavaScript handlers
    const buttons = document.querySelectorAll('a.btn, button.btn:not([data-bs-dismiss]):not(.dropdown-toggle)');
    
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Don't add spinner if button is already disabled or has a spinner
            if (this.disabled || this.classList.contains('loading') || this.querySelector('.spinner-border')) {
                return;
            }
            
            // Don't add spinner for buttons that open modals or dropdowns
            if (this.hasAttribute('data-bs-toggle') || this.hasAttribute('data-bs-target')) {
                return;
            }
            
            // Don't add spinner for delete/cancel actions that use confirmDelete
            if (this.getAttribute('onclick')?.includes('confirmDelete')) {
                return;
            }
            
            // Skip if this is a regular button without href or form submission
            // (e.g., buttons used for JavaScript-only actions)
            if (this.tagName === 'BUTTON' && this.type === 'button' && !this.form) {
                return;
            }
            
            // Add loading class and disable button
            this.classList.add('loading');
            this.disabled = true;
            
            // Add spinner
            const spinner = document.createElement('span');
            spinner.className = 'spinner-border spinner-border-sm me-2';
            spinner.setAttribute('role', 'status');
            spinner.setAttribute('aria-hidden', 'true');
            
            // Insert spinner at the beginning
            this.insertBefore(spinner, this.firstChild);
            
            // For anchor tags, allow navigation to proceed - spinner will be visible during page load
            // For form submission buttons, the form handler will navigate away or handle errors
        });
    });
}
