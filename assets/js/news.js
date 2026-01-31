/**
 * News Module JavaScript
 * Handles news loading, subscription toggle, and interactions
 */

// ============================================================================
// CSRF Token Helper Functions
// ============================================================================

/**
 * Get CSRF token from meta tag
 * @return {string|null} CSRF token or null if not found
 */
function getCsrfToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    return metaTag ? metaTag.getAttribute('content') : null;
}

/**
 * Add CSRF token to fetch headers
 * @param {Object} headers - Existing headers object
 * @return {Object} Headers with CSRF token added
 */
function addCsrfHeader(headers = {}) {
    const token = getCsrfToken();
    if (token) {
        headers['X-CSRF-Token'] = token;
    }
    return headers;
}

// ============================================================================
// News Module Configuration
// ============================================================================

const NEWS_CONFIG = {
    FADE_DURATION: 400, // ms - duration of fade-in animation
    LOAD_MORE_LIMIT: 6, // Number of articles to load per request
};

// Get base URL from global config for asset paths
const NEWS_BASE_URL = window.ibcConfig?.baseUrl ?? window.appConfig?.baseUrl ?? '';
const NEWS_API_URL = window.ibcConfig?.apiUrl ?? window.appConfig?.baseUrl ?? '';

// ============================================================================
// Load More News Functionality
// ============================================================================

/**
 * Create skeleton loader HTML for news cards using Bootstrap placeholder classes
 * @param {number} count - Number of skeleton cards to generate
 * @returns {string} HTML string with skeleton loaders
 */
function createNewsSkeletonLoaders(count = 6) {
    const skeletonCards = [];
    
    for (let i = 0; i < count; i++) {
        skeletonCards.push(`
            <div class="col skeleton-loader-item" aria-hidden="true">
                <div class="card h-100">
                    <div class="placeholder-glow">
                        <div class="placeholder col-12 bg-secondary" style="height: 12.5rem;"></div>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="placeholder-glow mb-2">
                            <span class="placeholder col-4 bg-secondary"></span>
                        </div>
                        <div class="placeholder-glow mb-2">
                            <span class="placeholder col-10 bg-secondary"></span>
                            <span class="placeholder col-8 bg-secondary"></span>
                        </div>
                        <div class="placeholder-glow flex-grow-1 mb-3">
                            <span class="placeholder col-12 bg-secondary"></span>
                            <span class="placeholder col-11 bg-secondary"></span>
                            <span class="placeholder col-9 bg-secondary"></span>
                        </div>
                        <div class="placeholder-glow mb-3">
                            <span class="placeholder col-6 bg-secondary"></span>
                        </div>
                        <div class="placeholder-glow">
                            <span class="placeholder col-12 btn btn-outline-primary disabled"></span>
                        </div>
                    </div>
                </div>
            </div>
        `);
    }
    
    return skeletonCards.join('');
}

/**
 * Show skeleton loaders in news grid while loading
 * @param {HTMLElement} container - News grid container
 * @param {number} count - Number of skeleton items to show
 */
function showNewsSkeletonLoaders(container, count = 6) {
    const skeletonHTML = createNewsSkeletonLoaders(count);
    container.insertAdjacentHTML('beforeend', skeletonHTML);
}

/**
 * Remove skeleton loaders from news grid
 * @param {HTMLElement} container - News grid container
 */
function removeNewsSkeletonLoaders(container) {
    const skeletons = container.querySelectorAll('.skeleton-loader-item');
    skeletons.forEach(skeleton => skeleton.remove());
}

/**
 * Load more news articles via AJAX and append to grid with fade-in animation
 * Fetches articles from server based on current offset and appends them smoothly
 */
function loadMoreNews() {
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const newsGrid = document.getElementById('newsGrid');
    
    if (!loadMoreBtn || !newsGrid) {
        console.error('Required elements not found for loadMoreNews');
        return;
    }
    
    // Get current offset from button data attribute
    const offset = parseInt(loadMoreBtn.getAttribute('data-offset')) || 0;
    
    // Show loading state using toggleButtonState to prevent multiple clicks
    if (typeof toggleButtonState === 'function') {
        toggleButtonState(loadMoreBtn, true);
    } else {
        // Fallback if toggleButtonState is not available
        loadMoreBtn.classList.add('d-none');
    }
    
    if (loadingSpinner) {
        loadingSpinner.classList.remove('d-none');
    }
    
    // Show skeleton loaders for perceived performance
    showNewsSkeletonLoaders(newsGrid, NEWS_CONFIG.LOAD_MORE_LIMIT);
    
    // Build form data for AJAX request
    const formData = new FormData();
    formData.append('action', 'load_more');
    formData.append('offset', offset);
    formData.append('limit', NEWS_CONFIG.LOAD_MORE_LIMIT);
    
    // Fetch additional articles from server
    fetch(buildApiUrl('index.php?page=newsroom'), {
        method: 'POST',
        body: formData,
        headers: addCsrfHeader({
            'X-Requested-With': 'XMLHttpRequest'
        })
    })
    .then(response => {
        // Check for HTTP error status
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        // Remove skeleton loaders
        removeNewsSkeletonLoaders(newsGrid);
        
        // Hide loading spinner
        if (loadingSpinner) {
            loadingSpinner.classList.add('d-none');
        }
        
        // Check if data indicates success AND articles exist AND array has content
        if (data.success && data.articles && data.articles.length > 0) {
            // Append new articles to grid with fade-in animation
            appendArticlesToGrid(data.articles, newsGrid);
            
            // Update offset for next load
            const newOffset = offset + data.articles.length;
            loadMoreBtn.setAttribute('data-offset', newOffset);
            
            // Restore button state using toggleButtonState
            if (typeof toggleButtonState === 'function') {
                toggleButtonState(loadMoreBtn, false);
            } else {
                // Fallback
                loadMoreBtn.classList.remove('d-none');
            }
            
            // If fewer articles than requested, we've reached the end
            if (data.articles.length < NEWS_CONFIG.LOAD_MORE_LIMIT) {
                loadMoreBtn.disabled = true;
                loadMoreBtn.innerHTML = '<i class="fas fa-check me-2"></i>Alle Beiträge geladen';
            }
        } else if (data.articles && data.articles.length === 0) {
            // No more articles available - end reached
            // Show "no more articles" message
            const message = document.createElement('div');
            message.className = 'col-12 fade-in-news';
            message.innerHTML = `
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    Keine weiteren Beiträge verfügbar.
                </div>
            `;
            
            // Insert message before the button container
            const buttonContainer = loadMoreBtn.parentElement.parentElement;
            buttonContainer.parentElement.insertBefore(message, buttonContainer);
            
            // Trigger fade-in animation
            setTimeout(() => {
                message.classList.add('visible');
            }, 10);
            
            // Remove load more button
            loadMoreBtn.remove();
        } else {
            // API error or unexpected response format - treat as error
            throw new Error(data.message || 'Unerwartetes Antwortformat vom Server');
        }
    })
    .catch(error => {
        console.error('Error loading more news:', error);
        
        // Remove skeleton loaders on error
        removeNewsSkeletonLoaders(newsGrid);
        
        // Hide spinner
        if (loadingSpinner) {
            loadingSpinner.classList.add('d-none');
        }
        
        // Remove any existing error messages to avoid duplicates
        const existingError = document.querySelector('.load-more-error-message');
        if (existingError) {
            existingError.remove();
        }
        
        // Update button text to "Fehler – Erneut versuchen"
        loadMoreBtn.innerHTML = '<i class="fas fa-sync-alt me-2"></i>Fehler – Erneut versuchen';
        
        // Update the original content for toggleButtonState if it exists
        if (loadMoreBtn.dataset.originalContent) {
            loadMoreBtn.dataset.originalContent = '<i class="fas fa-sync-alt me-2"></i>Fehler – Erneut versuchen';
        }
        
        // Restore button state using toggleButtonState (makes button visible and clickable)
        if (typeof toggleButtonState === 'function') {
            toggleButtonState(loadMoreBtn, false);
        } else {
            // Fallback
            loadMoreBtn.classList.remove('d-none');
            loadMoreBtn.disabled = false;
        }
        
        // Show error message
        const errorMsg = document.createElement('div');
        errorMsg.className = 'col-12 fade-in-news load-more-error-message';
        errorMsg.innerHTML = `
            <div class="alert alert-danger text-center">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Netzwerkfehler beim Laden der Beiträge. Bitte versuchen Sie es erneut.
            </div>
        `;
        
        const buttonContainer = loadMoreBtn.parentElement.parentElement;
        buttonContainer.parentElement.insertBefore(errorMsg, buttonContainer);
        
        // Trigger fade-in animation
        setTimeout(() => {
            errorMsg.classList.add('visible');
        }, 10);
        
        // Show toast notification for network problems
        if (typeof showToast === 'function') {
            showToast('Netzwerkfehler beim Laden der Beiträge', 'danger');
        }
    });
}

/**
 * Append articles to news grid with smooth fade-in animation
 * @param {Array} articles - Array of article objects
 * @param {HTMLElement} newsGrid - Grid container element
 */
function appendArticlesToGrid(articles, newsGrid) {
    if (!articles || articles.length === 0) {
        return;
    }
    
    // Create document fragment for better performance
    const fragment = document.createDocumentFragment();
    
    articles.forEach((article, index) => {
        const col = createNewsCard(article);
        
        // Add initial hidden state for fade-in animation
        col.classList.add('fade-in-news');
        
        fragment.appendChild(col);
        
        // Trigger fade-in animation with staggered delay
        setTimeout(() => {
            col.classList.add('visible');
        }, index * 100); // Stagger by 100ms
    });
    
    // Append all articles at once
    newsGrid.appendChild(fragment);
    
    // Reinitialize AOS if available
    if (typeof AOS !== 'undefined') {
        AOS.refresh();
    }
}

/**
 * Create a news card element from article data
 * @param {Object} article - Article data
 * @return {HTMLElement} Card column element
 */
function createNewsCard(article) {
    const col = document.createElement('div');
    col.className = 'col';
    
    // Escape HTML to prevent XSS
    const escapeHtml = (text) => {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };
    
    // Truncate content for preview
    const truncateContent = (content, maxLength = 120) => {
        const stripped = content.replace(/<[^>]*>/g, ''); // Strip HTML tags
        if (stripped.length <= maxLength) {
            return escapeHtml(stripped);
        }
        return escapeHtml(stripped.substring(0, maxLength) + '...');
    };
    
    // Format date to German locale
    const formatDate = (dateString) => {
        const date = new Date(dateString);
        return date.toLocaleDateString('de-DE', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    };
    
    // Build card HTML
    col.innerHTML = `
        <div class="card h-100" style="border-radius: 1.5rem; transition: transform var(--transition-smooth), box-shadow var(--transition-smooth);">
            ${article.image_path ? `
                <img 
                    src="${NEWS_BASE_URL}/${escapeHtml(article.image_path).replace(/^\/+/, '')}" 
                    class="card-img-top" 
                    alt="${escapeHtml(article.title)}"
                    style="height: 200px; object-fit: cover; border-radius: 1.5rem 1.5rem 0 0;"
                >
            ` : `
                <div class="bg-gradient-animated" style="height: 200px; border-radius: 1.5rem 1.5rem 0 0;"></div>
            `}
            
            <div class="card-body d-flex flex-column">
                ${article.category ? `
                    <span class="badge bg-secondary mb-2" style="width: fit-content;">
                        ${escapeHtml(article.category)}
                    </span>
                ` : ''}
                
                <h5 class="card-title">
                    ${escapeHtml(article.title)}
                </h5>
                
                <p class="card-text text-muted flex-grow-1">
                    ${truncateContent(article.content)}
                </p>
                
                <div class="d-flex align-items-center mb-3">
                    <small class="text-muted">
                        <i class="fas fa-calendar me-1"></i>
                        ${formatDate(article.created_at)}
                    </small>
                </div>
                
                ${article.cta_link && article.cta_label ? `
                    <a href="${escapeHtml(article.cta_link)}" class="btn btn-outline-primary w-100">
                        ${escapeHtml(article.cta_label)}
                        <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                ` : ''}
            </div>
        </div>
    `;
    
    return col;
}

// ============================================================================
// News Subscription Toggle Functionality
// ============================================================================

/**
 * Toggle news subscription status with immediate AJAX call
 * Updates backend and provides user feedback via toast
 */
function toggleNewsSubscription() {
    const subscriptionToggle = document.getElementById('newsSubscriptionToggle');
    
    if (!subscriptionToggle) {
        console.error('Subscription toggle element not found');
        return;
    }
    
    subscriptionToggle.addEventListener('change', function() {
        const action = this.checked ? 'subscribe' : 'unsubscribe';
        const previousState = !this.checked; // Store for potential revert
        
        // Disable toggle during request to prevent multiple clicks
        this.disabled = true;
        
        // Send AJAX request to backend
        fetch(buildApiUrl('index.php?page=newsroom'), {
            method: 'POST',
            headers: addCsrfHeader({
                'Content-Type': 'application/x-www-form-urlencoded',
            }),
            body: 'action=' + encodeURIComponent(action)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            // Re-enable toggle
            this.disabled = false;
            
            if (data.success) {
                // Show success toast
                const message = action === 'subscribe' 
                    ? 'News-Benachrichtigungen aktiviert' 
                    : 'News-Benachrichtigungen deaktiviert';
                
                if (typeof showToast === 'function') {
                    showToast(message, 'success');
                }
            } else {
                // Revert toggle on error
                this.checked = previousState;
                
                const errorMsg = data.message || 'Fehler beim Aktualisieren der Benachrichtigungseinstellungen';
                
                if (typeof showToast === 'function') {
                    showToast(errorMsg, 'danger');
                } else {
                    alert(errorMsg);
                }
            }
        })
        .catch(error => {
            console.error('Error toggling subscription:', error);
            
            // Re-enable toggle and revert state
            this.disabled = false;
            this.checked = previousState;
            
            const errorMsg = 'Fehler beim Aktualisieren der Benachrichtigungseinstellungen';
            
            if (typeof showToast === 'function') {
                showToast(errorMsg, 'danger');
            } else {
                alert(errorMsg);
            }
        });
    });
}

// ============================================================================
// Initialization
// ============================================================================

/**
 * Initialize news module when DOM is ready
 */
function initNewsModule() {
    // Initialize subscription toggle
    toggleNewsSubscription();
    
    // Attach load more button handler
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', loadMoreNews);
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNewsModule);
} else {
    // DOM is already ready
    initNewsModule();
}

// ============================================================================
// CSS for fade-in animation (injected via JavaScript)
// ============================================================================

// Inject fade-in animation styles
const styleElement = document.createElement('style');
styleElement.textContent = `
    .fade-in-news {
        opacity: 0;
        transform: translateY(20px);
        transition: opacity ${NEWS_CONFIG.FADE_DURATION}ms ease-out, 
                    transform ${NEWS_CONFIG.FADE_DURATION}ms ease-out;
    }
    
    .fade-in-news.visible {
        opacity: 1;
        transform: translateY(0);
    }
`;
document.head.appendChild(styleElement);
