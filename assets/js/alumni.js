/**
 * Alumni Module - JE Alumni Connect
 * Alumni database search, profile management, and edit mode functionality
 */

import { getCsrfToken, showToast, SKELETON_LOADER_DELAY, debounce, addCsrfHeader, toggleButtonState, toggleButtonLoading, buildApiUrl } from './core.js';

export function createAlumniSkeleton(count = 6) {
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
export function initAlumniDatabaseSearch() {
    const searchInput = document.getElementById('alumniDatabaseSearch');
    const gridContainer = document.getElementById('alumniDatabaseGrid');
    const searchSpinner = document.getElementById('alumniSearchSpinner');
    const graduationYearFilter = document.getElementById('graduationYearFilter');
    
    if (!searchInput || !gridContainer) return;
    
    // Get bio preview length from window context (set by PHP) or use fallback constant
    // Fallback value matches ALUMNI_BIO_PREVIEW_LENGTH_DB in alumni_database.php
    const ALUMNI_BIO_PREVIEW_LENGTH = window.ALUMNI_BIO_PREVIEW_LENGTH || 120;
    
    // Number of skeleton placeholders to show during loading
    const ALUMNI_SKELETON_COUNT = 6;
    
    let searchTimeout = null;
    
    /**
     * Perform AJAX search for alumni profiles
     * Note: CSRF token not required for read-only search operations
     * @param {string} searchTerm - Search query
     * @param {string} graduationYear - Graduation year filter
     */
    function performAlumniSearch(searchTerm, graduationYear) {
        
        // Show spinner
        if (searchSpinner) {
            searchSpinner.style.display = 'flex';
        }
        
        // Show skeleton loaders instead of empty grid
        if (gridContainer) {
            gridContainer.innerHTML = createAlumniSkeleton(ALUMNI_SKELETON_COUNT);
        }
        
        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'search');
        formData.append('search', searchTerm);
        if (graduationYear && !isNaN(parseInt(graduationYear))) {
            formData.append('graduation_year', parseInt(graduationYear));
        }
        
        // Perform AJAX request
        fetch(window.location.href, {
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
            // Hide spinner
            if (searchSpinner) {
                searchSpinner.style.display = 'none';
            }
            
            if (data.success) {
                updateAlumniGrid(data.profiles, searchTerm);
            } else {
                console.error('Search failed:', data.message);
                showAlumniError();
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            // Hide spinner
            if (searchSpinner) {
                searchSpinner.style.display = 'none';
            }
            showAlumniError();
        });
    }
    
    /**
     * Update alumni grid with search results
     * @param {Array} profiles - Array of alumni profiles
     * @param {string} searchTerm - Current search term
     */
    function updateAlumniGrid(profiles, searchTerm) {
        if (!gridContainer) return;
        
        // Clear current content
        gridContainer.innerHTML = '';
        
        // Show empty state if no results
        if (profiles.length === 0) {
            gridContainer.innerHTML = `
                <div class="col-12">
                    <div class="card glass-card text-center py-5 px-4">
                        <div class="card-body">
                            <i class="fas fa-user-friends fa-4x text-muted mb-3"></i>
                            <h4>Keine Alumni-Profile gefunden</h4>
                            <p class="text-muted">
                                ${searchTerm ? 'Ihre Suche ergab keine Treffer. Versuchen Sie andere Suchbegriffe.' : 'Das Alumni-Netzwerk wird derzeit aufgebaut. Schauen Sie bald wieder vorbei!'}
                            </p>
                        </div>
                    </div>
                </div>
            `;
            return;
        }
        
        // Get current user ID from page context if available
        // Note: created_by field in alumni table indicates profile ownership
        const currentUserId = window.currentUserId || null;
        const canEdit = window.canEditAlumni || false;
        
        // Render profile cards
        profiles.forEach((profile, index) => {
            const isOwnProfile = currentUserId && parseInt(profile.created_by) === parseInt(currentUserId);
            const showEditButton = canEdit || isOwnProfile;
            
            const card = document.createElement('div');
            card.className = 'col';
            card.setAttribute('data-aos', 'fade-up');
            card.setAttribute('data-aos-delay', (index % 3) * 100);
            
            card.innerHTML = `
                <div class="card glass-card h-100" data-profile-id="${profile.id}">
                    <div class="card-body p-4">
                        <!-- Profile Header with Avatar -->
                        <div class="d-flex align-items-start mb-3">
                            <!-- Profile Picture / Avatar -->
                            <div class="flex-shrink-0 me-3">
                                ${profile.profile_picture ? `
                                    <img src="${escapeHtml(profile.profile_picture)}" 
                                         alt="${escapeHtml(profile.firstname + ' ' + profile.lastname)}" 
                                         class="alumni-avatar-container" 
                                         style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);">
                                ` : `
                                    <div class="alumni-avatar-container" style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--ibc-blue) 0%, var(--ibc-green) 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);">
                                        <i class="fas fa-user fa-2x text-white"></i>
                                    </div>
                                `}
                            </div>
                            
                            <!-- Profile Info -->
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1 fw-bold" style="color: var(--ibc-blue);">
                                    ${escapeHtml(profile.firstname + ' ' + profile.lastname)}
                                </h5>
                                ${profile.graduation_year ? `
                                    <small class="text-muted">
                                        <i class="fas fa-graduation-cap me-1"></i>Jahrgang ${escapeHtml(profile.graduation_year)}
                                    </small>
                                ` : ''}
                            </div>
                            
                            <!-- Edit Button -->
                            ${showEditButton ? `
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Aktionen">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="#" data-action="edit-alumni-profile" data-profile-id="${profile.id}">
                                                <i class="fas fa-edit me-2"></i>
                                                ${isOwnProfile ? 'Profil bearbeiten' : 'Bearbeiten'}
                                            </a>
                                        </li>
                                        ${canEdit ? `
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="#" data-action="delete-alumni-profile" data-profile-id="${profile.id}">
                                                    <i class="fas fa-trash me-2"></i>Löschen
                                                </a>
                                            </li>
                                        ` : ''}
                                    </ul>
                                </div>
                            ` : ''}
                        </div>
                        
                        <!-- Position and Company -->
                        ${(profile.position || profile.company) ? `
                            <div class="mb-3">
                                ${profile.position ? `
                                    <p class="mb-1 fw-semibold" style="color: var(--ibc-text-primary);">
                                        <i class="fas fa-briefcase me-2 text-primary"></i>
                                        ${escapeHtml(profile.position)}
                                    </p>
                                ` : ''}
                                ${profile.company ? `
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-building me-2"></i>
                                        ${escapeHtml(profile.company)}
                                    </p>
                                ` : ''}
                            </div>
                        ` : ''}
                        
                        <!-- Bio Preview -->
                        ${profile.bio ? `
                            <p class="card-text text-muted small mb-3" style="line-height: 1.6;">
                                ${escapeHtml(profile.bio.length > ALUMNI_BIO_PREVIEW_LENGTH ? profile.bio.substring(0, ALUMNI_BIO_PREVIEW_LENGTH) + '...' : profile.bio)}
                            </p>
                        ` : ''}
                        
                        <!-- Badges -->
                        ${(profile.industry || profile.location) ? `
                            <div class="mb-3">
                                ${profile.industry ? `
                                    <span class="badge bg-info me-2">
                                        <i class="fas fa-industry me-1"></i>${escapeHtml(profile.industry)}
                                    </span>
                                ` : ''}
                                ${profile.location ? `
                                    <span class="badge bg-primary">
                                        <i class="fas fa-map-marker-alt me-1"></i>${escapeHtml(profile.location)}
                                    </span>
                                ` : ''}
                            </div>
                        ` : ''}
                        
                        <!-- Social Contact Links -->
                        <div class="d-flex gap-2 pt-2 border-top">
                            ${profile.email ? `
                                <a href="mailto:${escapeHtml(profile.email)}" 
                                   class="btn btn-sm btn-outline-primary flex-fill" 
                                   title="E-Mail senden"
                                   style="border-radius: var(--border-radius-soft);">
                                    <i class="fas fa-envelope"></i>
                                    <span class="d-none d-md-inline ms-1">E-Mail</span>
                                </a>
                            ` : ''}
                            
                            ${profile.phone ? `
                                <a href="tel:${escapeHtml(profile.phone)}" 
                                   class="btn btn-sm btn-outline-primary flex-fill" 
                                   title="Anrufen"
                                   style="border-radius: var(--border-radius-soft);">
                                    <i class="fas fa-phone"></i>
                                    <span class="d-none d-md-inline ms-1">Anrufen</span>
                                </a>
                            ` : ''}
                            
                            ${profile.linkedin_url ? `
                                <a href="${escapeHtml(profile.linkedin_url)}" 
                                   target="_blank" 
                                   rel="noopener noreferrer"
                                   class="btn btn-sm btn-outline-primary flex-fill" 
                                   title="LinkedIn-Profil anzeigen"
                                   style="border-radius: var(--border-radius-soft);">
                                    <i class="fab fa-linkedin"></i>
                                    <span class="d-none d-md-inline ms-1">LinkedIn</span>
                                </a>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
            
            gridContainer.appendChild(card);
        });
    }
    
    /**
     * Show error message in grid
     */
    function showAlumniError() {
        if (!gridContainer) return;
        
        gridContainer.innerHTML = `
            <div class="col-12">
                <div class="card glass-card text-center py-5 px-4 border-danger">
                    <div class="card-body">
                        <i class="fas fa-exclamation-triangle fa-4x text-danger mb-3" style="opacity: 0.5;"></i>
                        <h4 class="text-danger">Fehler beim Laden</h4>
                        <p class="text-muted">
                            Es ist ein Fehler beim Laden der Alumni-Daten aufgetreten. 
                            Bitte versuchen Sie es später erneut.
                        </p>
                        <button type="button" class="btn btn-danger" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-2"></i>Seite neu laden
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @return {string} Escaped text
     */
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
    
    // Set up search input event listener with debounce
    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.trim();
        const graduationYear = graduationYearFilter ? graduationYearFilter.value : '';
        
        // Clear existing timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Set new timeout for debounced search
        searchTimeout = setTimeout(() => {
            performAlumniSearch(searchTerm, graduationYear);
        }, 300);
    });
    
    // Set up graduation year filter event listener
    if (graduationYearFilter) {
        graduationYearFilter.addEventListener('change', function(e) {
            const searchTerm = searchInput.value.trim();
            const graduationYear = e.target.value;
            performAlumniSearch(searchTerm, graduationYear);
        });
    }
}

// ============================================================================
// ALUMNI EDIT MODAL FUNCTIONALITY
// ============================================================================

/**
 * Initialize Alumni Edit Modal functionality
 * Handles edit button clicks, form submission, and image preview
 */
export function initAlumniEditModal() {
    const editModal = document.getElementById('alumniEditModal');
    const editForm = document.getElementById('alumniEditForm');
    const profilePictureInput = document.getElementById('profilePictureInput');
    const profilePicturePreview = document.getElementById('profilePicturePreview');
    
    if (!editModal || !editForm) return;
    
    // Handle edit button clicks (using event delegation)
    document.addEventListener('click', function(e) {
        const editButton = e.target.closest('[data-action="edit-alumni-profile"]');
        if (!editButton) return;
        
        e.preventDefault();
        
        const profileId = editButton.getAttribute('data-profile-id');
        if (!profileId) return;
        
        loadProfileData(profileId);
    });
    
    /**
     * Load profile data into edit modal
     * @param {number} profileId - Profile ID to load
     */
    function loadProfileData(profileId) {
        const csrfToken = document.querySelector('.container[data-csrf-token]')?.getAttribute('data-csrf-token');
        
        if (!csrfToken) {
            showToast('CSRF-Token nicht gefunden. Bitte laden Sie die Seite neu.', 'danger');
            return;
        }
        
        // Find the edit button and add loading state
        const editButton = document.querySelector(`a[data-action="edit-alumni-profile"][data-profile-id="${profileId}"]`);
        if (editButton) {
            toggleButtonState(editButton, true);
        }
        
        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'get');
        formData.append('id', profileId);
        formData.append('csrf_token', csrfToken);
        
        // Fetch profile data
        fetch(window.location.href, {
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
            if (data.success && data.profile) {
                populateEditForm(data.profile);
                
                // Show modal
                const modal = new bootstrap.Modal(editModal);
                modal.show();
                
                // Initialize tooltips in modal
                const tooltipTriggerList = editModal.querySelectorAll('[data-bs-toggle="tooltip"]');
                tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                    new bootstrap.Tooltip(tooltipTriggerEl);
                });
            } else {
                showToast(data.message || 'Fehler beim Laden des Profils', 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading profile:', error);
            showToast('Fehler beim Laden des Profils', 'danger');
        })
        .finally(() => {
            // Restore button state
            if (editButton) {
                toggleButtonState(editButton, false);
            }
        });
    }
    
    /**
     * Populate edit form with profile data
     * @param {Object} profile - Profile data
     */
    function populateEditForm(profile) {
        document.getElementById('editProfileId').value = profile.id || '';
        document.getElementById('editFirstname').value = profile.firstname || '';
        document.getElementById('editLastname').value = profile.lastname || '';
        document.getElementById('editEmail').value = profile.email || '';
        document.getElementById('editPhone').value = profile.phone || '';
        document.getElementById('editCompany').value = profile.company || '';
        document.getElementById('editPosition').value = profile.position || '';
        document.getElementById('editIndustry').value = profile.industry || '';
        document.getElementById('editLocation').value = profile.location || '';
        document.getElementById('editGraduationYear').value = profile.graduation_year || '';
        document.getElementById('editLinkedIn').value = profile.linkedin_url || '';
        document.getElementById('editBio').value = profile.bio || '';
        document.getElementById('editIsPublished').checked = profile.is_published == 1;
        
        // Update profile picture preview
        if (profile.profile_picture) {
            profilePicturePreview.innerHTML = `<img src="${escapeHtml(profile.profile_picture)}" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
        } else {
            profilePicturePreview.innerHTML = '<i class="fas fa-user fa-4x text-white"></i>';
        }
        
        // Reset file input
        if (profilePictureInput) {
            profilePictureInput.value = '';
        }
    }
    
    /**
     * Handle image preview
     */
    if (profilePictureInput) {
        profilePictureInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            
            if (file) {
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    showToast('Bitte wählen Sie ein gültiges Bildformat (JPG, PNG, GIF, WebP)', 'warning');
                    e.target.value = '';
                    return;
                }
                
                // Validate file size (5MB max)
                const maxSize = 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    showToast('Das Bild ist zu groß. Maximale Größe: 5MB', 'warning');
                    e.target.value = '';
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(event) {
                    profilePicturePreview.innerHTML = `<img src="${event.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    /**
     * Handle form submission
     */
    editForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate form
        if (!editForm.checkValidity()) {
            e.stopPropagation();
            editForm.classList.add('was-validated');
            return;
        }
        
        const submitBtn = document.getElementById('alumniEditSubmitBtn');
        const submitBtnText = document.getElementById('editSubmitBtnText');
        const submitSpinner = document.getElementById('editSubmitSpinner');
        
        // Show loading state using toggleButtonLoading
        if (submitBtn) {
            toggleButtonLoading(submitBtn, true);
        }
        // Keep existing elements for backward compatibility
        if (submitBtnText) submitBtnText.textContent = 'Wird gespeichert...';
        if (submitSpinner) submitSpinner.style.display = 'inline-block';
        
        const csrfToken = document.querySelector('.container[data-csrf-token]')?.getAttribute('data-csrf-token');
        
        if (!csrfToken) {
            showToast('CSRF-Token nicht gefunden. Bitte laden Sie die Seite neu.', 'danger');
            if (submitBtn) toggleButtonLoading(submitBtn, false);
            if (submitBtnText) submitBtnText.textContent = 'Speichern';
            if (submitSpinner) submitSpinner.style.display = 'none';
            return;
        }
        
        // Prepare form data
        const formData = new FormData(editForm);
        formData.append('action', 'update');
        formData.append('csrf_token', csrfToken);
        
        // Submit form
        fetch(window.location.href, {
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
                showToast(data.message || 'Profil erfolgreich aktualisiert', 'success');
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(editModal);
                if (modal) modal.hide();
                
                // Reload page to show updated data
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showToast(data.message || 'Fehler beim Aktualisieren des Profils', 'danger');
            }
        })
        .catch(error => {
            console.error('Error updating profile:', error);
            showToast('Fehler beim Aktualisieren des Profils', 'danger');
        })
        .finally(() => {
            // Re-enable submit button
            if (submitBtn) toggleButtonLoading(submitBtn, false);
            if (submitBtnText) submitBtnText.textContent = 'Speichern';
            if (submitSpinner) submitSpinner.style.display = 'none';
        });
    });
}

// Initialize Alumni Database search when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        initAlumniDatabaseSearch();
        initAlumniEditModal();
        initEditModeToggle();
    });
} else {
    initAlumniDatabaseSearch();
    initAlumniEditModal();
    initEditModeToggle();
}

/**
 * Initialize Edit Mode Toggle functionality
 * Allows admins to toggle global edit mode for the system
 */
export function initEditModeToggle() {
    const editModeToggle = document.getElementById('edit-mode-toggle');
    const editModeFab = document.getElementById('edit-mode-fab');
    
    // Support both navbar button and FAB
    const toggleButtons = [editModeToggle, editModeFab].filter(btn => btn !== null);
    
    if (toggleButtons.length === 0) {
        // No toggle buttons present (user is not an admin)
        return;
    }
    
    // Get initial state from data attribute (from first available button)
    const initialState = toggleButtons[0].dataset.editModeActive === 'true';
    
    // Set initial button state for all buttons
    if (initialState) {
        toggleButtons.forEach(btn => btn.classList.add('active'));
    }
    
    // Function to toggle edit mode
    function toggleEditMode() {
        // Store previous state for rollback
        const wasActive = toggleButtons[0].classList.contains('active');
        
        // Disable all toggle buttons and add loading state
        toggleButtons.forEach(btn => {
            toggleButtonState(btn, true);
        });
        
        // Toggle button active state immediately for responsiveness
        const isActive = !wasActive;
        toggleButtons.forEach(btn => {
            if (isActive) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
        
        // Toggle body class to edit-mode-active
        document.body.classList.toggle('edit-mode-active', isActive);
        
        // Toggle banner visibility
        const banner = document.getElementById('edit-mode-banner');
        if (banner) {
            if (isActive) {
                banner.classList.add('show');
            } else {
                banner.classList.remove('show');
            }
        }
        
        // Send AJAX request to save state in session
        fetch(buildApiUrl('api/set_edit_mode.php'), {
            method: 'POST',
            headers: addCsrfHeader({
                'Content-Type': 'application/x-www-form-urlencoded',
            }),
            body: 'action=toggle_edit_mode'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Optionally show a toast notification
                const mode = data.edit_mode_active ? 'aktiviert' : 'deaktiviert';
                console.log(`Edit-Modus ${mode}`);
            } else {
                // Revert changes on error to previous state
                toggleButtons.forEach(btn => {
                    if (wasActive) {
                        btn.classList.add('active');
                    } else {
                        btn.classList.remove('active');
                    }
                });
                document.body.classList.toggle('edit-mode-active', wasActive);
                if (banner) {
                    if (wasActive) {
                        banner.classList.add('show');
                    } else {
                        banner.classList.remove('show');
                    }
                }
                console.error('Failed to toggle edit mode:', data.message);
            }
        })
        .catch(error => {
            // Revert changes on error to previous state
            toggleButtons.forEach(btn => {
                if (wasActive) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            document.body.classList.toggle('edit-mode-active', wasActive);
            if (banner) {
                if (wasActive) {
                    banner.classList.add('show');
                } else {
                    banner.classList.remove('show');
                }
            }
            console.error('Error toggling edit mode:', error);
        })
        .finally(() => {
            // Restore button state for all toggle buttons
            toggleButtons.forEach(btn => {
                toggleButtonState(btn, false);
            });
        });
    }
    
    // Add click handler to all toggle buttons
    toggleButtons.forEach(btn => {
        btn.addEventListener('click', toggleEditMode);
    });
}
