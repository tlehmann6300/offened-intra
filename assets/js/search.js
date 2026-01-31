/**
 * Unified Global Search Implementation
 * Single search functionality that works across all instances (navbar search)
 * Uses the centralized global_search.php API endpoint
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all search instances
    initializeSearchInstances();
});

/**
 * Initialize all search input instances on the page
 */
function initializeSearchInstances() {
    const searchInputs = document.querySelectorAll('.global-search-input');
    
    searchInputs.forEach(input => {
        const instance = input.dataset.searchInstance;
        const resultsContainer = document.querySelector(`.global-search-results[data-search-instance="${instance}"]`);
        const submitButton = document.querySelector(`.global-search-submit[data-search-instance="${instance}"]`);
        
        if (!resultsContainer) return;
        
        let searchTimeout = null;
        
        // Input event handler
        input.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            // Validate query length
            if (query.length < 2) {
                resultsContainer.classList.remove('show');
                resultsContainer.innerHTML = '';
                return;
            }
            
            if (query.length > 100) {
                resultsContainer.innerHTML = '<div class="dropdown-item text-warning">Suchanfrage zu lang (max. 100 Zeichen)</div>';
                resultsContainer.classList.add('show');
                return;
            }
            
            // Debounce search
            searchTimeout = setTimeout(function() {
                performSearch(query, instance, resultsContainer);
            }, 300);
        });
        
        // Enter key handler
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = this.value.trim();
                if (query.length >= 2 && query.length <= 100) {
                    performSearch(query, instance, resultsContainer);
                }
            }
        });
        
        // Submit button handler
        if (submitButton) {
            submitButton.addEventListener('click', function() {
                const query = input.value.trim();
                if (query.length >= 2 && query.length <= 100) {
                    performSearch(query, instance, resultsContainer);
                }
            });
        }
    });
    
    // Close dropdowns when clicking outside (desktop instances)
    document.addEventListener('click', function(e) {
        const searchWrappers = document.querySelectorAll('.global-search-wrapper');
        searchWrappers.forEach(wrapper => {
            if (!wrapper.contains(e.target)) {
                const resultsContainer = wrapper.querySelector('.global-search-results');
                if (resultsContainer) {
                    resultsContainer.classList.remove('show');
                }
            }
        });
    });
}

/**
 * Perform search API call using new global_search.php endpoint
 */
function performSearch(query, instance, resultsContainer) {
    // Show loading state
    resultsContainer.innerHTML = '<div class="dropdown-item"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Suche läuft...</div>';
    resultsContainer.classList.add('show');
    
    fetch('api/global_search.php?q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySearchResults(data.results, data.counts, data.total, instance, resultsContainer);
            } else {
                resultsContainer.innerHTML = '<div class="dropdown-item text-danger">' + escapeHtml(data.message || 'Fehler bei der Suche') + '</div>';
                resultsContainer.classList.add('show');
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            resultsContainer.innerHTML = '<div class="dropdown-item text-danger">Fehler bei der Suche</div>';
            resultsContainer.classList.add('show');
        });
}

/**
 * Helper function for German pluralization
 */
function pluralize(count, singular, plural) {
    return count + ' ' + (count !== 1 ? plural : singular);
}

/**
 * Display search results with counts and visual feedback
 */
function displaySearchResults(results, counts, total, instance, resultsContainer) {
    let html = '';
    
    if (total === 0) {
        html = '<div class="dropdown-item text-muted">Keine Ergebnisse gefunden</div>';
    } else {
        // Display summary header with counts
        html += '<div class="dropdown-header bg-light border-bottom">';
        html += '<strong>' + total + ' Ergebnisse gefunden:</strong>';
        html += '<div class="mt-1 small">';
        const summaryParts = [];
        if (counts.user > 0) summaryParts.push(pluralize(counts.user, 'Person', 'Personen'));
        if (counts.inventory > 0) summaryParts.push(pluralize(counts.inventory, 'Gegenstand', 'Gegenstände'));
        if (counts.news > 0) summaryParts.push(counts.news + ' News');
        if (counts.event > 0) summaryParts.push(pluralize(counts.event, 'Event', 'Events'));
        if (counts.project > 0) summaryParts.push(pluralize(counts.project, 'Projekt', 'Projekte'));
        html += summaryParts.join(' · ');
        html += '</div></div>';
        
        // Display Inventory as "Gegenstände"
        if (results.inventory && results.inventory.length > 0) {
            html += '<h6 class="dropdown-header"><i class="fas fa-boxes me-2"></i>Gegenstände (' + counts.inventory + ')</h6>';
            results.inventory.forEach(item => {
                const quantityBadge = item.quantity > 0 
                    ? `<span class="badge bg-success ms-2">${item.quantity}x</span>` 
                    : `<span class="badge bg-secondary ms-2">0x</span>`;
                html += `<a href="${escapeHtml(item.url)}" class="dropdown-item">
                    <div class="fw-bold">${escapeHtml(item.title)} ${quantityBadge}</div>
                    <small class="text-muted">${escapeHtml(item.subtitle)}</small>
                </a>`;
            });
            html += '<div class="dropdown-divider"></div>';
        }
        
        // Display Users as "Personen"
        if (results.user && results.user.length > 0) {
            html += '<h6 class="dropdown-header"><i class="fas fa-user-graduate me-2"></i>Personen (' + counts.user + ')</h6>';
            results.user.forEach(item => {
                html += `<a href="${escapeHtml(item.url)}" class="dropdown-item">
                    <div class="fw-bold">${escapeHtml(item.title)}</div>
                    <small class="text-muted">${escapeHtml(item.subtitle)}</small>
                </a>`;
            });
            html += '<div class="dropdown-divider"></div>';
        }
        
        // Display News
        if (results.news && results.news.length > 0) {
            html += '<h6 class="dropdown-header"><i class="fas fa-newspaper me-2"></i>News (' + counts.news + ')</h6>';
            results.news.forEach(item => {
                html += `<a href="${escapeHtml(item.url)}" class="dropdown-item">
                    <div class="fw-bold">${escapeHtml(item.title)}</div>
                    <small class="text-muted">${escapeHtml(item.subtitle)} · ${formatDate(item.date)}</small>
                </a>`;
            });
            html += '<div class="dropdown-divider"></div>';
        }
        
        // Display Events
        if (results.event && results.event.length > 0) {
            html += '<h6 class="dropdown-header"><i class="fas fa-calendar-alt me-2"></i>Events (' + counts.event + ')</h6>';
            results.event.forEach(item => {
                html += `<a href="${escapeHtml(item.url)}" class="dropdown-item">
                    <div class="fw-bold">${escapeHtml(item.title)}</div>
                    <small class="text-muted">${escapeHtml(item.subtitle)} · ${formatDate(item.date)}</small>
                </a>`;
            });
            html += '<div class="dropdown-divider"></div>';
        }
        
        // Display Projects
        if (results.project && results.project.length > 0) {
            html += '<h6 class="dropdown-header"><i class="fas fa-briefcase me-2"></i>Projekte (' + counts.project + ')</h6>';
            results.project.forEach(item => {
                html += `<a href="${escapeHtml(item.url)}" class="dropdown-item">
                    <div class="fw-bold">${escapeHtml(item.title)}</div>
                    <small class="text-muted">${escapeHtml(item.subtitle)} · ${formatDate(item.date)}</small>
                </a>`;
            });
        }
    }
    
    resultsContainer.innerHTML = html;
    resultsContainer.classList.add('show');
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Format date for display
 */
function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
