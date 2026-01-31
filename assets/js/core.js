/**
 * Core Module - JE Alumni Connect
 * Core utilities, CSRF protection, toast notifications, and base functionality
 */

/**
 * Main JavaScript for JE Alumni Connect
 * Chart.js integration and other interactive features
 */

// Configuration constants
export const SKELETON_LOADER_DELAY = 500; // ms - delay before removing skeleton loaders
export const INVENTORY_BACK_TO_TOP_THRESHOLD = 500; // px - scroll position to show back-to-top button
export const FADE_ANIMATION_DURATION = 300; // ms - duration of fade animations
const BANNER_ANIMATION_DELAY = 10; // ms - delay before cookie banner animation starts
export const COOKIE_BANNER_HIDE_DELAY = 400; // ms - delay before hiding cookie banner
export const COOKIE_BANNER_SHOW_DELAY = 1500; // ms - delay before showing cookie banner initially
export const AOS_INIT_TIMEOUT = 2000; // ms - maximum time to wait for images before initializing AOS

// Get base URL and API URL from global config
// Use ibcConfig (new) with fallback to appConfig (legacy) for backward compatibility
const API_BASE_URL = window.ibcConfig?.apiUrl ?? window.appConfig?.baseUrl ?? '';
const BASE_URL = window.ibcConfig?.baseUrl ?? window.appConfig?.baseUrl ?? '';

/**
 * Build API URL with base path
 * @param {string} path - Path to append to base URL
 * @return {string} Full URL
 */
export function buildApiUrl(path) {
    // Input validation
    if (!path || typeof path !== 'string') {
        return API_BASE_URL || '';
    }
    
    // Remove leading slash from path if present
    const cleanPath = path.startsWith('/') ? path.substring(1) : path;
    // Combine base URL with path, ensuring no double slashes
    return API_BASE_URL ? `${API_BASE_URL}/${cleanPath}` : cleanPath;
}

// Flag image URLs for language switcher
export const FLAG_URLS = {
    'de': 'assets/img/flags/de.svg',
    'en': 'assets/img/flags/gb.svg',
    'fr': 'assets/img/flags/fr.svg'
};

// Flag alt texts for accessibility
export const FLAG_ALT_TEXTS = {
    'de': 'Deutsche Flagge',
    'en': 'Britische Flagge',
    'fr': 'Französische Flagge'
};
export function getStorageItem(key) {
    try {
        return localStorage.getItem(key);
    } catch (e) {
        try {
            return sessionStorage.getItem(key);
        } catch (err) {
            return null;
        }
    }
}

export function setStorageItem(key, value) {
    try {
        localStorage.setItem(key, value);
        return true;
    } catch (e) {
        try {
            sessionStorage.setItem(key, value);
            return true;
        } catch (err) {
            return false;
        }
    }
}
export function getCsrfToken() {
    // Try to get token from meta tag (primary source)
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag) {
        const token = metaTag.getAttribute('content');
        if (token && token.trim().length > 0) {
            return token.trim();
        }
    }
    
    // Fallback: Try to get from global config
    if (window.ibcConfig && window.ibcConfig.csrfToken) {
        const token = window.ibcConfig.csrfToken;
        if (token && token.trim().length > 0) {
            return token.trim();
        }
    }
    
    // Log warning if token is missing for POST requests
    console.warn('CSRF token not found. POST requests may fail.');
    return null;
}

/**
 * Add CSRF token to fetch headers (absolutely reliable)
 * @param {Object} headers - Existing headers object
 * @return {Object} Headers with CSRF token added
 */
export function addCsrfHeader(headers = {}) {
    const token = getCsrfToken();
    if (token) {
        headers['X-CSRF-Token'] = token;
    } else {
        // Log error if token is missing for mutating requests
        console.error('CRITICAL: CSRF token is missing. Request may be rejected by server.');
    }
    return headers;
}

/**
 * Global CSRF-Protected Fetch Wrapper with Automatic Loading Spinner and Error Handling
 * 
 * Features:
 * - Automatically adds CSRF token to all POST, PUT, PATCH, DELETE requests
 * - Automatically disables sending button and shows spinner
 * - Re-enables button after response (success or error)
 * - Global error handling with toast notifications for API errors
 * - Use this instead of native fetch() for all API calls
 * 
 * @param {string} url - Request URL
 * @param {Object} options - Fetch options (method, body, headers, etc.)
 * @param {HTMLElement} [options.button] - Optional button element to show loading state
 * @param {boolean} [options.skipErrorToast] - Set to true to skip automatic error toast (for custom error handling)
 * @return {Promise} Fetch promise
 */
window.secureFetch = function(url, options = {}) {
    // Clone options to avoid mutating original
    const secureOptions = { ...options };
    
    // Extract custom options
    const button = secureOptions.button;
    const skipErrorToast = secureOptions.skipErrorToast || false;
    
    // Remove custom options from fetch options
    delete secureOptions.button;
    delete secureOptions.skipErrorToast;
    
    // Determine if this is a mutating request that needs CSRF protection
    const method = (secureOptions.method || 'GET').toUpperCase();
    const needsCsrf = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);
    
    // Add CSRF token for mutating requests
    if (needsCsrf) {
        secureOptions.headers = addCsrfHeader(secureOptions.headers || {});
    }
    
    // Show loading state on button if provided
    if (button) {
        toggleButtonState(button, true);
    }
    
    // Call native fetch with secure options
    return fetch(url, secureOptions)
        .then(response => {
            // Always restore button state after response
            if (button) {
                toggleButtonState(button, false);
            }
            
            // Check if response is OK (status 200-299)
            if (!response.ok) {
                // Try to extract error message from response
                return response.json()
                    .then(data => {
                        const errorMessage = data.message || `Server error: ${response.status} ${response.statusText}`;
                        if (!skipErrorToast) {
                            showToast(errorMessage, 'danger');
                        }
                        // Reject with error data for caller to handle
                        return Promise.reject({ response, data });
                    })
                    .catch(jsonError => {
                        // If JSON parsing fails, show generic error
                        const errorMessage = `Server error: ${response.status} ${response.statusText}`;
                        if (!skipErrorToast) {
                            showToast(errorMessage, 'danger');
                        }
                        return Promise.reject({ response, data: { message: errorMessage } });
                    });
            }
            
            // Try to parse JSON response
            return response.json()
                .then(data => {
                    // Check for API-level errors (success: false)
                    if (data && typeof data.success === 'boolean' && !data.success) {
                        const errorMessage = data.message || 'Ein Fehler ist aufgetreten.';
                        if (!skipErrorToast) {
                            showToast(errorMessage, 'danger');
                        }
                    }
                    return data;
                })
                .catch(jsonError => {
                    // If response is not JSON, return response object
                    return response;
                });
        })
        .catch(error => {
            // Always restore button state on error
            if (button) {
                toggleButtonState(button, false);
            }
            
            // Handle network errors
            if (error.response) {
                // Error was already handled above
                throw error;
            } else {
                // Network error or other fetch error
                const errorMessage = 'Netzwerkfehler. Bitte überprüfen Sie Ihre Internetverbindung.';
                if (!skipErrorToast) {
                    showToast(errorMessage, 'danger');
                }
                throw error;
            }
        });
};
(function() {
    const originalFetch = window.fetch;
    window.fetch = function(url, options = {}) {
        // Determine if this is a same-origin request
        let isSameOrigin = false;
        
        try {
            // Relative URLs are always same-origin
            if (!url.startsWith('http://') && !url.startsWith('https://')) {
                isSameOrigin = true;
            } else {
                // For absolute URLs, check if they match current origin
                const requestUrl = new URL(url, window.location.origin);
                isSameOrigin = requestUrl.origin === window.location.origin;
            }
        } catch (e) {
            // If URL parsing fails, assume relative URL (same-origin)
            isSameOrigin = true;
        }
        
        if (isSameOrigin) {
            const method = (options.method || 'GET').toUpperCase();
            const needsCsrf = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);
            
            if (needsCsrf) {
                options.headers = addCsrfHeader(options.headers || {});
            }
        }
        
        return originalFetch(url, options);
    };
})();
export function toggleButtonState(button, isLoading) {
    if (!button) return;
    
    if (isLoading) {
        // Store original button content if not already stored
        if (!button.dataset.originalContent) {
            button.dataset.originalContent = button.innerHTML;
        }
        
        // Disable button to prevent multiple submissions
        button.disabled = true;
        
        // Check if button already has a spinner to avoid duplicates
        if (!button.querySelector('.spinner-border')) {
            // Create and add spinner
            const spinner = document.createElement('span');
            spinner.className = 'spinner-border spinner-border-sm me-2';
            spinner.setAttribute('role', 'status');
            spinner.setAttribute('aria-hidden', 'true');
            
            // Insert spinner at the beginning of the button
            button.insertBefore(spinner, button.firstChild);
        }
    } else {
        // Restore original button content
        if (button.dataset.originalContent) {
            button.innerHTML = button.dataset.originalContent;
            delete button.dataset.originalContent;
        } else {
            // Fallback: just remove the spinner if original content wasn't stored
            const spinner = button.querySelector('.spinner-border');
            if (spinner) {
                spinner.remove();
            }
        }
        
        // Re-enable button
        button.disabled = false;
    }
}

/**
 * Backward compatibility alias for toggleButtonState
 * @deprecated Use toggleButtonState instead
 */
export function toggleButtonLoading(buttonElement, isLoading) {
    toggleButtonState(buttonElement, isLoading);
}

/**
 * Toggle button loading state (convenience alias)
 * Shows a spinner on click and disables the button to prevent duplicate submissions
 * @param {HTMLElement} btn - The button element to modify
 * @param {boolean} loading - Whether to show loading state (true) or restore normal state (false)
 */
export function toggleButton(btn, loading) {
    toggleButtonState(btn, loading);
}
export function initGlobalImageErrorHandler() {
    // Generate unique IDs for SVG gradients to prevent conflicts
    const gradientId1 = 'ibcGrad1-' + Math.random().toString(36).substring(2, 11);
    const gradientId2 = 'ibcGrad2-' + Math.random().toString(36).substring(2, 11);
    const gradientId3 = 'avatarGrad-' + Math.random().toString(36).substring(2, 11);
    
    // Professional SVG placeholder with IBC logo (inline to avoid additional requests)
    const ibcLogoPlaceholder = 'data:image/svg+xml;base64,' + btoa(`
        <svg width="400" height="400" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="${gradientId1}" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#20234A;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#6D9744;stop-opacity:1" />
                </linearGradient>
                <linearGradient id="${gradientId2}" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" style="stop-color:#6D9744;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#4A7129;stop-opacity:1" />
                </linearGradient>
            </defs>
            <!-- Background -->
            <rect width="400" height="400" fill="url(#${gradientId1})" rx="20"/>
            
            <!-- IBC Logo representation -->
            <g transform="translate(200, 200)">
                <!-- Circle background for logo -->
                <circle cx="0" cy="0" r="80" fill="white" opacity="0.95"/>
                
                <!-- Stylized IBC letters -->
                <text x="0" y="0" text-anchor="middle" dominant-baseline="central" 
                      style="font-family: 'Arial', sans-serif; font-weight: bold; font-size: 48px; fill: #20234A;">IBC</text>
                
                <!-- Decorative elements -->
                <circle cx="0" cy="0" r="85" fill="none" stroke="white" stroke-width="3" opacity="0.8"/>
                <circle cx="0" cy="0" r="92" fill="none" stroke="white" stroke-width="2" opacity="0.5"/>
            </g>
            
            <!-- Bottom text -->
            <text x="200" y="330" text-anchor="middle" 
                  style="font-family: 'Arial', sans-serif; font-size: 18px; fill: white; opacity: 0.9;">
                Bild nicht verfügbar
            </text>
        </svg>
    `.trim());
    
    // SVG data URL for user avatar placeholder (inline to avoid additional requests)
    const userAvatarPlaceholder = 'data:image/svg+xml;base64,' + btoa(`
        <svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="${gradientId3}" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#20234A;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#6D9744;stop-opacity:1" />
                </linearGradient>
            </defs>
            <rect width="200" height="200" fill="url(#${gradientId3})" rx="100"/>
            <g transform="translate(100, 100)">
                <circle cx="0" cy="-20" r="30" fill="white" opacity="0.9"/>
                <ellipse cx="0" cy="50" rx="50" ry="40" fill="white" opacity="0.9"/>
            </g>
        </svg>
    `.trim());
    
    // Add global error handler to all existing images
    document.querySelectorAll('img').forEach(img => {
        if (!img.dataset.errorHandled) {
            img.addEventListener('error', function() {
                handleImageError(this);
            }, { once: true });
            img.dataset.errorHandled = 'true';
        }
    });
    
    // Use MutationObserver to handle dynamically added images
    const observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === 1) { // Element node
                    // Check if the node itself is an image
                    if (node.tagName === 'IMG' && !node.dataset.errorHandled) {
                        node.addEventListener('error', function() {
                            handleImageError(this);
                        }, { once: true });
                        node.dataset.errorHandled = 'true';
                    }
                    // Check for images within the node
                    if (node.querySelectorAll) {
                        node.querySelectorAll('img').forEach(img => {
                            if (!img.dataset.errorHandled) {
                                img.addEventListener('error', function() {
                                    handleImageError(this);
                                }, { once: true });
                                img.dataset.errorHandled = 'true';
                            }
                        });
                    }
                }
            });
        });
    });
    
    // Start observing
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    /**
     * Handle image load error by replacing with appropriate placeholder
     * @param {HTMLImageElement} img - The image element that failed to load
     */
    function handleImageError(img) {
        // Prevent infinite loop if placeholder also fails
        if (img.dataset.placeholderApplied) {
            return;
        }
        
        img.dataset.placeholderApplied = 'true';
        
        // Determine appropriate placeholder based on context
        const isProfilePicture = img.classList.contains('profile-picture') || 
                                img.classList.contains('avatar') ||
                                img.closest('.profile-picture-preview') ||
                                img.alt?.toLowerCase().includes('profile') ||
                                img.alt?.toLowerCase().includes('avatar');
        
        // Choose appropriate placeholder
        if (isProfilePicture) {
            img.src = userAvatarPlaceholder;
            img.alt = 'Platzhalter-Avatar';
        } else {
            // Use professional IBC logo placeholder for all other images
            img.src = ibcLogoPlaceholder;
            img.alt = 'Bild nicht verfügbar';
        }
        
        // Add visual indication that this is a placeholder
        img.style.opacity = '0.85';
    }
}

// Cookie Banner Functions
export function acceptCookies() {
    // Store consent in localStorage, with sessionStorage as fallback if localStorage fails
    const stored = setStorageItem("cookie_consent", "true");
    if (!stored) {
        // If localStorage is not available (privacy mode, etc.), try sessionStorage
        setStorageItem("cookie_banner_shown", "true");
    }
    
    const cookieBanner = document.getElementById("cookie-banner");
    if (cookieBanner) {
        cookieBanner.classList.add("hide");
        setTimeout(() => {
            cookieBanner.style.display = "none";
        }, COOKIE_BANNER_HIDE_DELAY);
    }
}

// Function to decline cookies
export function declineCookies() {
    // Store decline in localStorage, with sessionStorage as fallback if localStorage fails
    const stored = setStorageItem("cookie_consent", "false");
    if (!stored) {
        // If localStorage is not available (privacy mode, etc.), try sessionStorage
        setStorageItem("cookie_banner_shown", "false");
    }
    
    const cookieBanner = document.getElementById("cookie-banner");
    if (cookieBanner) {
        cookieBanner.classList.add("hide");
        setTimeout(() => {
            cookieBanner.style.display = "none";
        }, COOKIE_BANNER_HIDE_DELAY);
    }
}

// Function to reopen cookie banner (for Cookie-Einstellungen button)
// Note: This allows users to review their choice but doesn't automatically revoke consent
// If users want to revoke, they can simply not click "Akzeptieren" again and refresh
export function reopenCookieBanner() {
    const cookieBanner = document.getElementById("cookie-banner");
    if (cookieBanner) {
        cookieBanner.style.display = "block";
        cookieBanner.classList.remove("hide");
        // Small delay to ensure display takes effect before animation
        setTimeout(() => {
            cookieBanner.classList.add("show");
        }, BANNER_ANIMATION_DELAY);
    }
    
    // Note: We intentionally don't clear consent here to allow users to review
    // without losing their preference. Users can revoke by clearing browser data
    // or by using the browser's built-in cookie controls.
}
export function initializeAOSWhenReady() {
    if (typeof AOS === 'undefined') {
        console.warn('AOS library not loaded - animations will not be available');
        return;
    }
    
    // Identify main content containers that should be ready before AOS initializes
    const contentContainers = [
        document.getElementById('newsGrid'),
        document.getElementById('inventoryContainer'),
        document.querySelector('.container'),
        document.querySelector('main')
    ].filter(el => el !== null);
    
    // If no content containers found, initialize immediately
    if (contentContainers.length === 0) {
        AOS.init({
            duration: 800,
            easing: 'ease-out-cubic',
            once: true,
            offset: 100
        });
        return;
    }
    
    // Wait for images in content containers to load
    const images = [];
    contentContainers.forEach(container => {
        const containerImages = container.querySelectorAll('img');
        containerImages.forEach(img => {
            if (!img.complete) {
                images.push(img);
            }
        });
    });
    
    // Function to initialize AOS
    const initAOS = () => {
        AOS.init({
            duration: 800,
            easing: 'ease-out-cubic',
            once: true,
            offset: 100,
            disable: false
        });
    };
    
    // If all images are already loaded, initialize immediately
    if (images.length === 0) {
        // Small delay to ensure layout is stable
        requestAnimationFrame(() => {
            requestAnimationFrame(initAOS);
        });
        return;
    }
    
    // Wait for images to load with timeout fallback
    let loadedCount = 0;
    const totalImages = images.length;
    const timeout = setTimeout(() => {
        // Initialize even if some images haven't loaded after timeout
        initAOS();
    }, AOS_INIT_TIMEOUT);
    
    const checkAllLoaded = () => {
        loadedCount++;
        if (loadedCount >= totalImages) {
            clearTimeout(timeout);
            // Use requestAnimationFrame to ensure DOM is ready
            requestAnimationFrame(() => {
                requestAnimationFrame(initAOS);
            });
        }
    };
    
    // Listen for image load events
    images.forEach(img => {
        if (img.complete) {
            checkAllLoaded();
        } else {
            img.addEventListener('load', checkAllLoaded);
            img.addEventListener('error', checkAllLoaded); // Count errors as "loaded" to not block
        }
    });
}
export function addFadeInAnimation() {
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.classList.add('fade-in');
        }, index * 50);
    });
    
    // Initialize Intersection Observer for fade-in-up animations
    initScrollAnimations();
}

/**
 * Initialize scroll-based animations using Intersection Observer
 */
export function initScrollAnimations() {
    // Check if Intersection Observer is supported
    if (!('IntersectionObserver' in window)) {
        // Fallback: show all elements immediately
        document.querySelectorAll('.fade-in-up, .animate-on-scroll').forEach(el => {
            el.classList.add('is-visible');
        });
        return;
    }
    
    // Create observer for fade-in-up elements
    const observerOptions = {
        root: null,
        rootMargin: '0px 0px -100px 0px',
        threshold: 0.1
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                // Optionally unobserve after animation
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    // Observe all elements with animation classes
    document.querySelectorAll('.fade-in-up, .animate-on-scroll, .reveal-fx, section, .service-card, .info-card').forEach(el => {
        observer.observe(el);
    });
}

/**
 * Initialize Bootstrap form validation
 */
export function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
}

/**
 * Auto-hide alerts after 5 seconds
 */
export function autoHideAlerts() {
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
}

/**
 * Image preview before upload
 */
export function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const preview = document.getElementById(previewId);
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

/**
 * Confirm before delete
 */
export function confirmDelete(message) {
    return confirm(message || 'Sind Sie sicher, dass Sie diesen Eintrag löschen möchten?');
}

/**
 * Copy text to clipboard
 */
export function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('In Zwischenablage kopiert!', 'success');
    }).catch(() => {
        showToast('Fehler beim Kopieren', 'danger');
    });
}

/**
 * Show bootstrap toast notification
 */
export function showToast(message, type = 'info') {
    // Create toast element if it doesn't exist
    let toastContainer = document.getElementById('toastContainer');
    
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    const toastId = 'toast-' + Date.now();
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
    toast.show();
    
    // Remove toast element after it's hidden
    toastElement.addEventListener('hidden.bs.toast', function() {
        toastElement.remove();
    });
}
export function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Format date to German locale
 */
export function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('de-DE', options);
}

/**
 * Scroll to top smoothly
 */
export function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Add scroll to top button if page is long
window.addEventListener('scroll', function() {
    const scrollBtn = document.getElementById('scrollTopBtn');
    
    if (window.pageYOffset > 300) {
        if (!scrollBtn) {
            const btn = document.createElement('button');
            btn.id = 'scrollTopBtn';
            btn.className = 'btn btn-primary position-fixed bottom-0 end-0 m-3';
            btn.style.zIndex = '1000';
            btn.innerHTML = '<i class="bi bi-arrow-up"></i>';
            btn.onclick = scrollToTop;
            document.body.appendChild(btn);
        }
    } else if (scrollBtn) {
        scrollBtn.remove();
    }
});
export function initializeLanguageFlag() {
    // Read the language from cookie or localStorage
    var cookies = document.cookie.split(';');
    var currentLang = ''; // Empty by default
    
    for (var i = 0; i < cookies.length; i++) {
        var cookie = cookies[i].trim();
        if (cookie.startsWith('language=')) {
            currentLang = cookie.split('=')[1];
            break;
        }
    }
    
    // Fallback to localStorage if no cookie found
    if (!currentLang) {
        var storedLang = localStorage.getItem('language');
        if (storedLang && ['de', 'en', 'fr'].includes(storedLang)) {
            currentLang = storedLang;
        }
    }
    
    // Default to German if still no language found
    if (!currentLang) {
        currentLang = 'de';
    }
    
    // Update the active flag image
    var activeFlag = document.getElementById('activeFlag');
    if (activeFlag && FLAG_URLS[currentLang]) {
        activeFlag.src = FLAG_URLS[currentLang];
        activeFlag.alt = FLAG_ALT_TEXTS[currentLang];
    }
    
    // Update aria-current attribute for language items
    var langItems = document.querySelectorAll('.lang-item');
    langItems.forEach(function(item) {
        var itemLang = item.getAttribute('data-lang');
        if (itemLang === currentLang) {
            item.setAttribute('aria-current', 'true');
        } else {
            item.removeAttribute('aria-current');
        }
    });
}

/**
 * Initialize email copy functionality for footer
 * Allows users to click on email links to copy the address to clipboard
 */
export function initializeEmailCopy() {
    const emailLinks = document.querySelectorAll('.copy-email-link');
    
    emailLinks.forEach(function(link) {
        link.addEventListener('click', function(event) {
            event.preventDefault();
            
            // Get email text - if it's an icon link, find the text link
            var email = this.textContent.trim();
            if (this.classList.contains('email-icon-link')) {
                var emailCta = this.closest('.email-cta');
                if (emailCta) {
                    var emailTextLink = emailCta.querySelector('.copy-email-link:not(.email-icon-link)');
                    if (emailTextLink) {
                        email = emailTextLink.textContent.trim();
                    }
                }
            }
            
            // Copy to clipboard using existing function
            if (email && email.includes('@')) {
                copyToClipboard(email);
            }
        });
    });
}
export function removeSkeletonLoaders() {
    // Wait for initial render, then remove skeleton classes
    setTimeout(() => {
        const skeletons = document.querySelectorAll('.skeleton');
        skeletons.forEach(skeleton => {
            skeleton.classList.remove('skeleton');
            skeleton.style.opacity = '1';
        });
    }, SKELETON_LOADER_DELAY);
}

/**
 * Add micro-interaction on scroll for navbar
 */
(function() {
    let lastScroll = 0;
    const navbar = document.querySelector('.navbar');
    
    if (navbar) {
        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > 100) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
            
            lastScroll = currentScroll;
        });
    }
})();

/**
 * Handle logo image loading errors
 */
export function initLogoErrorHandling() {
    const logo = document.getElementById('navbar-logo');
    const fallbackText = document.getElementById('navbar-fallback-text');
    
    if (logo && fallbackText) {
        logo.addEventListener('error', function() {
            logo.style.display = 'none';
            fallbackText.style.display = 'inline';
        });
    }
}
export function selectRole(role) {
    // Show loading overlay
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.classList.add('active');
    }
    
    // Send AJAX request to update role with consistent error handling
    fetch(buildApiUrl('index.php?page=select_role'), {
        method: 'POST',
        headers: addCsrfHeader({
            'Content-Type': 'application/x-www-form-urlencoded',
        }),
        body: 'action=select_role&role=' + encodeURIComponent(role)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Redirect to dashboard - use relative URL for navigation
            window.location.href = 'index.php?page=home';
        } else {
            showToast('Fehler: ' + (data.message || 'Rolle konnte nicht gesetzt werden.'), 'danger');
            if (loadingOverlay) {
                loadingOverlay.classList.remove('active');
            }
        }
    })
    .catch(error => {
        console.error('Error selecting role:', error);
        showToast('Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.', 'danger');
        if (loadingOverlay) {
            loadingOverlay.classList.remove('active');
        }
    });
}

/**
 * Apply to project functionality
 * In production, this would send an application to the backend
 */
export function applyToProject(projectTitle) {
    // In production, this would send an application to the backend
    showToast('Bewerbung für Projekt "' + projectTitle + '" wird eingereicht.\n\nIn der finalen Version würde hier ein Formular zur Projektbewerbung erscheinen.', 'info');
    
    // Example of how this could work in production:
    /*
    fetch(buildApiUrl('index.php?page=projects'), {
        method: 'POST',
        headers: addCsrfHeader({
            'Content-Type': 'application/x-www-form-urlencoded',
        }),
        body: 'action=apply&project=' + encodeURIComponent(projectTitle)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Bewerbung erfolgreich eingereicht!', 'success');
        } else {
            showToast('Fehler: ' + data.message, 'danger');
        }
    });
    */
}
