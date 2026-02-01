/**
 * Main JavaScript for JE Alumni Connect
 * Chart.js integration and other interactive features
 */

// Configuration constants
const SKELETON_LOADER_DELAY = 500; // ms - delay before removing skeleton loaders
const INVENTORY_BACK_TO_TOP_THRESHOLD = 500; // px - scroll position to show back-to-top button
const FADE_ANIMATION_DURATION = 300; // ms - duration of fade animations
const BANNER_ANIMATION_DELAY = 10; // ms - delay before cookie banner animation starts
const COOKIE_BANNER_HIDE_DELAY = 400; // ms - delay before hiding cookie banner
const COOKIE_BANNER_SHOW_DELAY = 1500; // ms - delay before showing cookie banner initially
const AOS_INIT_TIMEOUT = 2000; // ms - maximum time to wait for images before initializing AOS

// Get base URL and API URL from global config
// Use ibcConfig (new) with fallback to appConfig (legacy) for backward compatibility
const API_BASE_URL = window.ibcConfig?.apiUrl ?? window.appConfig?.baseUrl ?? '';
const BASE_URL = window.ibcConfig?.baseUrl ?? window.appConfig?.baseUrl ?? '';

/**
 * Build API URL with base path
 * @param {string} path - Path to append to base URL
 * @return {string} Full URL
 */
function buildApiUrl(path) {
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
const FLAG_URLS = {
    'de': 'assets/img/flags/de.svg',
    'en': 'assets/img/flags/gb.svg',
    'fr': 'assets/img/flags/fr.svg'
};

// Flag alt texts for accessibility
const FLAG_ALT_TEXTS = {
    'de': 'Deutsche Flagge',
    'en': 'Britische Flagge',
    'fr': 'Französische Flagge'
};

// Storage helper functions with fallback logic
function getStorageItem(key) {
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

function setStorageItem(key, value) {
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

/**
 * Get CSRF token from meta tag with fallback and validation
 * @return {string|null} CSRF token or null if not found
 */
function getCsrfToken() {
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
function addCsrfHeader(headers = {}) {
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

/**
 * Backward compatibility: Enhance native fetch to auto-add CSRF
 * This ensures all existing fetch calls get CSRF protection without code changes
 * Only applies to same-origin requests
 */
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

/**
 * Toggle button loading state with spinner
 * Universal function that disables the button, inserts a spinner (Bootstrap .spinner-border-sm),
 * and caches the original text to prevent duplicate submissions
 * @param {HTMLElement} button - The button element to modify
 * @param {boolean} isLoading - Whether to show loading state (true) or restore normal state (false)
 */
function toggleButtonState(button, isLoading) {
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
function toggleButtonLoading(buttonElement, isLoading) {
    toggleButtonState(buttonElement, isLoading);
}

/**
 * Toggle button loading state (convenience alias)
 * Shows a spinner on click and disables the button to prevent duplicate submissions
 * @param {HTMLElement} btn - The button element to modify
 * @param {boolean} loading - Whether to show loading state (true) or restore normal state (false)
 */
function toggleButton(btn, loading) {
    toggleButtonState(btn, loading);
}

/**
 * Initialize global image error handler
 * Automatically replaces failed image sources with professional SVG placeholders with IBC logo
 */
function initGlobalImageErrorHandler() {
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
function acceptCookies() {
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
function declineCookies() {
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
function reopenCookieBanner() {
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

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize high-contrast mode from localStorage
    const highContrastMode = getStorageItem('high_contrast_mode') === 'true';
    if (highContrastMode) {
        document.body.classList.add('high-contrast-mode');
    }
    
    // Cookie Banner Logic
    const cookieBanner = document.getElementById("cookie-banner");
    if (cookieBanner) {
        // Check for consent - try primary key first, then fallback key
        // Note: cookie_banner_shown is a legacy fallback for when localStorage fails
        const consentGiven = getStorageItem("cookie_consent") || getStorageItem("cookie_banner_shown");
        
        if (!consentGiven) {
            setTimeout(() => {
                cookieBanner.classList.add("show");
            }, COOKIE_BANNER_SHOW_DELAY);
        }
        
        // Add event listener for accept button
        const acceptButton = document.getElementById('cookie-accept-btn');
        if (acceptButton) {
            acceptButton.addEventListener('click', acceptCookies);
        }
        
        // Add event listener for decline button
        const declineButton = document.getElementById('cookie-decline-btn');
        if (declineButton) {
            declineButton.addEventListener('click', declineCookies);
        }
        
        // Add event listener for Cookie-Einstellungen button
        const cookieSettingsButton = document.querySelector('.cookie-settings-link');
        if (cookieSettingsButton) {
            cookieSettingsButton.addEventListener('click', function(e) {
                e.preventDefault();
                reopenCookieBanner();
            });
        }
    }
    
    // Initialize Chart.js if on newsroom page
    if (document.getElementById('newsChart') && typeof window.newsChartData !== 'undefined') {
        initNewsChart();
    }
    
    // Add fade-in animation to cards
    addFadeInAnimation();
    
    // Initialize form validation
    initFormValidation();
    
    // Auto-hide alerts after 5 seconds
    autoHideAlerts();
    
    // Initialize language flag on page load
    initializeLanguageFlag();
    
    // Initialize email copy functionality
    initializeEmailCopy();
    
    // Initialize scroll animations
    initScrollAnimations();
    
    // Initialize skeleton loading removal
    removeSkeletonLoaders();
    
    // Initialize logo error handling
    initLogoErrorHandling();
    
    // Initialize global image error handler
    initGlobalImageErrorHandler();
    
    // Initialize inventory management if on inventory page
    if (document.getElementById('inventoryForm')) {
        initInventoryManagement();
    }
    
    // Initialize button loading spinners
    initButtonLoadingSpinners();
    
    // Initialize notification bell
    initNotificationBell();
    
    // Initialize AOS after content is fully rendered to prevent layout jumps
    // Wait for images to load and DOM to stabilize before starting animations
    initializeAOSWhenReady();
    
    // Initialize event countdown timer
    initEventCountdown();
});

/**
 * Initialize event countdown timer
 * Reads event date from #event-countdown element and updates countdown display every second
 * Stops and shows message when event time has passed
 */
function initEventCountdown() {
    // Check if countdown element exists on the page
    const countdownElement = document.getElementById('event-countdown');
    if (!countdownElement) {
        return;
    }
    
    // Get event date from data attribute
    const eventDateStr = countdownElement.getAttribute('data-event-date');
    if (!eventDateStr) {
        console.warn('Event countdown: data-event-date attribute is missing');
        return;
    }
    
    // Parse event date
    const eventDate = new Date(eventDateStr);
    if (isNaN(eventDate.getTime())) {
        console.error('Event countdown: Invalid date format:', eventDateStr);
        return;
    }
    
    // Get countdown display elements
    const daysElement = document.getElementById('days');
    const hoursElement = document.getElementById('hours');
    const minutesElement = document.getElementById('minutes');
    const secondsElement = document.getElementById('seconds');
    const countdownDisplay = document.getElementById('countdown-display');
    
    // Verify all elements exist
    if (!daysElement || !hoursElement || !minutesElement || !secondsElement || !countdownDisplay) {
        console.warn('Event countdown: One or more countdown display elements are missing');
        return;
    }
    
    // Time constants for calculations
    const MILLISECONDS_PER_SECOND = 1000;
    const SECONDS_PER_MINUTE = 60;
    const MINUTES_PER_HOUR = 60;
    const HOURS_PER_DAY = 24;
    
    // Variable to store interval ID
    let countdownInterval;
    
    /**
     * Update countdown display
     * Calculates time difference and updates DOM elements
     */
    const updateCountdown = () => {
        // Check if elements still exist in DOM
        if (!document.body.contains(countdownDisplay)) {
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }
            return;
        }
        
        const now = new Date();
        const timeDiff = eventDate - now;
        
        // Check if event has started or passed
        if (timeDiff <= 0) {
            // Stop countdown and show message
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }
            
            // Hide countdown display
            countdownDisplay.style.display = 'none';
            
            // Create and show event running message
            const messageElement = document.createElement('div');
            messageElement.className = 'alert alert-success text-center';
            
            // Create icon element
            const iconElement = document.createElement('i');
            iconElement.className = 'fas fa-check-circle me-2';
            
            // Create text node
            const textNode = document.createTextNode('Event läuft');
            
            // Append icon and text to message element
            messageElement.appendChild(iconElement);
            messageElement.appendChild(textNode);
            
            // Insert message after countdown display with parent check
            if (countdownDisplay.parentNode) {
                countdownDisplay.parentNode.insertBefore(messageElement, countdownDisplay.nextSibling);
            }
            
            return;
        }
        
        // Calculate time units using constants
        const millisecondsPerDay = MILLISECONDS_PER_SECOND * SECONDS_PER_MINUTE * MINUTES_PER_HOUR * HOURS_PER_DAY;
        const millisecondsPerHour = MILLISECONDS_PER_SECOND * SECONDS_PER_MINUTE * MINUTES_PER_HOUR;
        const millisecondsPerMinute = MILLISECONDS_PER_SECOND * SECONDS_PER_MINUTE;
        
        const days = Math.floor(timeDiff / millisecondsPerDay);
        const hours = Math.floor((timeDiff % millisecondsPerDay) / millisecondsPerHour);
        const minutes = Math.floor((timeDiff % millisecondsPerHour) / millisecondsPerMinute);
        const seconds = Math.floor((timeDiff % millisecondsPerMinute) / MILLISECONDS_PER_SECOND);
        
        // Format with leading zeros
        const formatNumber = (num) => String(num).padStart(2, '0');
        
        // Update DOM elements
        daysElement.textContent = formatNumber(days);
        hoursElement.textContent = formatNumber(hours);
        minutesElement.textContent = formatNumber(minutes);
        secondsElement.textContent = formatNumber(seconds);
    };
    
    // Run initial update
    updateCountdown();
    
    // Update every second
    countdownInterval = setInterval(updateCountdown, 1000);
}

/**
 * Initialize AOS (Animate On Scroll) after content is fully rendered
 * Waits for images to load and content containers to be ready to prevent layout jumps
 * This ensures smooth animations without unexpected shifts in page layout
 */
function initializeAOSWhenReady() {
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

/**
 * Change language
 * Stores language preference and reloads page
 */
function changeLanguage(langCode) {
    // Validate language code
    const allowedLanguages = ['de', 'en', 'fr'];
    if (!allowedLanguages.includes(langCode)) {
        langCode = 'de';
    }
    
    // Store in cookie and localStorage
    document.cookie = 'language=' + langCode + '; path=/; max-age=31536000; SameSite=Strict';
    localStorage.setItem('language', langCode);
    
    // Update the active flag image before reload
    var activeFlag = document.getElementById('activeFlag');
    if (activeFlag && FLAG_URLS[langCode]) {
        activeFlag.src = FLAG_URLS[langCode];
        activeFlag.alt = FLAG_ALT_TEXTS[langCode];
    }
    
    // Update aria-current attribute for language items
    var langItems = document.querySelectorAll('.lang-item');
    langItems.forEach(function(item) {
        var itemLang = item.getAttribute('data-lang');
        if (itemLang === langCode) {
            item.setAttribute('aria-current', 'true');
        } else {
            item.removeAttribute('aria-current');
        }
    });
    
    // Update URL with language parameter
    const url = new URL(window.location.href);
    if (langCode === 'de') {
        url.searchParams.delete('lang');
    } else {
        url.searchParams.set('lang', langCode);
    }
    
    // Reload with new language
    window.location.href = url.toString();
}

/**
 * Initialize news statistics chart
 */
function initNewsChart() {
    const ctx = document.getElementById('newsChart');
    if (!ctx) return;
    
    const data = window.newsChartData || [];
    
    // Prepare data for Chart.js
    const labels = data.map(item => item.category || 'Ohne Kategorie');
    const values = data.map(item => parseInt(item.count));
    
    // Generate random colors for each category
    const colors = labels.map(() => {
        return `rgba(${Math.floor(Math.random() * 255)}, ${Math.floor(Math.random() * 255)}, ${Math.floor(Math.random() * 255)}, 0.7)`;
    });
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                label: 'Anzahl Nachrichten',
                data: values,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.parsed + ' Nachrichten';
                        }
                    }
                }
            }
        }
    });
}

/**
 * Add fade-in animation to cards
 */
function addFadeInAnimation() {
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
function initScrollAnimations() {
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
function initFormValidation() {
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
function autoHideAlerts() {
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
function previewImage(input, previewId) {
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
function confirmDelete(message) {
    return confirm(message || 'Sind Sie sicher, dass Sie diesen Eintrag löschen möchten?');
}

/**
 * Copy text to clipboard
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('In Zwischenablage kopiert!', 'success');
    }).catch(() => {
        showToast('Fehler beim Kopieren', 'danger');
    });
}

/**
 * Show bootstrap toast notification
 */
function showToast(message, type = 'info') {
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

/**
 * Debounce function for search/filter inputs
 */
function debounce(func, wait) {
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
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('de-DE', options);
}

/**
 * Scroll to top smoothly
 */
function scrollToTop() {
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

/**
 * Initialize the active language flag on page load
 * Reads the language from session/cookie and sets the correct flag
 */
function initializeLanguageFlag() {
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
function initializeEmailCopy() {
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

/**
 * Initialize scroll-based animations for elements
 * Simple intersection observer implementation
 */
function initScrollAnimations() {
    // Check if IntersectionObserver is supported
    if (!('IntersectionObserver' in window)) {
        // Fallback: show all elements immediately
        document.querySelectorAll('[data-aos], .fade-in, .reveal-fx').forEach(el => {
            el.classList.add('aos-animate');
            el.style.opacity = '1';
            el.style.transform = 'none';
        });
        return;
    }
    
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('aos-animate');
                // Unobserve after animation to improve performance
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    // Observe all elements with data-aos attribute
    document.querySelectorAll('[data-aos]').forEach(el => {
        el.classList.add('aos-init');
        observer.observe(el);
    });
    
    // Also observe fade-in elements
    document.querySelectorAll('.fade-in, .reveal-fx').forEach(el => {
        observer.observe(el);
    });
}

/**
 * Remove skeleton loaders once content is loaded
 * Uses a short delay to ensure smooth transition
 */
function removeSkeletonLoaders() {
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
function initLogoErrorHandling() {
    const logo = document.getElementById('navbar-logo');
    const fallbackText = document.getElementById('navbar-fallback-text');
    
    if (logo && fallbackText) {
        logo.addEventListener('error', function() {
            logo.style.display = 'none';
            fallbackText.style.display = 'inline';
        });
    }
}

/**
 * Select role functionality for role selection page
 * Sends AJAX request to update user role
 */
function selectRole(role) {
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
function applyToProject(projectTitle) {
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

/**
 * Inventory Management Functions
 */

/**
 * Get CSRF token from the page
 * @return {string|null} CSRF token or null if not found
 */
function getCsrfToken() {
    const container = document.querySelector('[data-csrf-token]');
    return container ? container.getAttribute('data-csrf-token') : null;
}

/**
 * Initialize inventory image preview functionality
 * Shows preview immediately using FileReader (no upload simulation needed)
 */
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
    return `
        <div class="col-12">
            <div class="card glass-card text-center py-5 px-4" style="max-width: 600px; margin: 0 auto;">
                <div class="card-body">
                    <div class="empty-state-icon mb-4">
                        <i class="fas fa-box-open fa-5x text-primary" style="opacity: 0.3;"></i>
                    </div>
                    <h3 class="mb-3">Keine Gegenstände gefunden</h3>
                    ${hasFilters ? `
                        <p class="text-muted mb-4">
                            Ihre Suche oder Filter ergab keine Treffer. 
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

// ============================================================================
// ALUMNI DATABASE SEARCH FUNCTIONALITY
// ============================================================================

/**
 * Initialize Alumni Database search functionality
 * Sets up real-time AJAX search for alumni profiles
 */
function initAlumniDatabaseSearch() {
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
function initAlumniEditModal() {
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
function initEditModeToggle() {
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
                // Re-apply the correct active state after button restoration
                // This ensures the active class is preserved even after innerHTML is restored
                if (isActive) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
        });
    }
    
    // Add click handler to all toggle buttons
    toggleButtons.forEach(btn => {
        btn.addEventListener('click', toggleEditMode);
    });
}

// ============================================================================
// NOTIFICATION BELL FUNCTIONALITY
// ============================================================================

/**
 * Initialize notification bell functionality
 * Handles bell click, panel display, and marking notifications as read
 */
function initNotificationBell() {
    const notificationBell = document.getElementById('notificationBell');
    const notificationPanel = document.getElementById('notificationPanel');
    const notificationContent = document.getElementById('notificationContent');
    const notificationBadge = document.getElementById('notificationBadge');
    
    if (!notificationBell || !notificationPanel || !notificationContent) {
        return;
    }
    
    let panelOpen = false;
    
    /**
     * Toggle notification panel
     */
    notificationBell.addEventListener('click', function(e) {
        e.stopPropagation();
        
        if (panelOpen) {
            closeNotificationPanel();
        } else {
            openNotificationPanel();
        }
    });
    
    /**
     * Open notification panel
     */
    function openNotificationPanel() {
        // Show panel
        notificationPanel.style.display = 'block';
        panelOpen = true;
        
        // Load helper requests
        loadHelperRequests();
        
        // Mark notifications as read
        markNotificationsAsRead();
    }
    
    /**
     * Close notification panel
     */
    function closeNotificationPanel() {
        notificationPanel.style.display = 'none';
        panelOpen = false;
    }
    
    /**
     * Load helper requests via AJAX
     */
    function loadHelperRequests() {
        // Show loading spinner
        notificationContent.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Wird geladen...</span>
                </div>
            </div>
        `;
        
        // Fetch helper requests
        fetch(buildApiUrl('api/notification_api.php'), {
            method: 'POST',
            headers: addCsrfHeader({
                'Content-Type': 'application/x-www-form-urlencoded',
            }),
            body: 'action=get_helper_requests'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderHelperRequests(data.requests);
            } else {
                showError('Fehler beim Laden der Helfer-Gesuche');
            }
        })
        .catch(error => {
            console.error('Error loading helper requests:', error);
            showError('Verbindungsfehler');
        });
    }
    
    /**
     * Render helper requests in the panel
     */
    function renderHelperRequests(requests) {
        if (!requests || requests.length === 0) {
            notificationContent.innerHTML = `
                <div class="text-center py-4 px-3">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Keine neuen Helfer-Gesuche vorhanden</p>
                </div>
            `;
            return;
        }
        
        let html = '<div class="list-group list-group-flush">';
        
        requests.forEach(request => {
            const eventDate = new Date(request.event_date).toLocaleDateString('de-DE', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            const startTime = new Date(request.start_time).toLocaleTimeString('de-DE', {
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const endTime = new Date(request.end_time).toLocaleTimeString('de-DE', {
                hour: '2-digit',
                minute: '2-digit'
            });
            
            html += `
                <a href="index.php?page=events#event-${request.event_id}" class="list-group-item list-group-item-action">
                    <div class="d-flex w-100 justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="mb-1 fw-bold">${escapeHtml(request.event_title)}</h6>
                            <p class="mb-1 small">
                                <i class="fas fa-tasks me-1 text-primary"></i>
                                <strong>${escapeHtml(request.task_name)}</strong>
                            </p>
                            <p class="mb-1 small text-muted">
                                <i class="fas fa-calendar me-1"></i>${eventDate}
                            </p>
                            <p class="mb-0 small text-muted">
                                <i class="fas fa-clock me-1"></i>${startTime} - ${endTime}
                            </p>
                        </div>
                        <div class="text-end ms-3">
                            <span class="badge bg-success">
                                ${request.slots_available} ${request.slots_available === 1 ? 'Platz' : 'Plätze'}
                            </span>
                        </div>
                    </div>
                </a>
            `;
        });
        
        html += '</div>';
        
        // Add "View all events" link at bottom
        html += `
            <div class="card-footer text-center">
                <a href="index.php?page=events" class="text-decoration-none">
                    Alle Events anzeigen <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        `;
        
        notificationContent.innerHTML = html;
    }
    
    /**
     * Show error message in panel
     */
    function showError(message) {
        notificationContent.innerHTML = `
            <div class="text-center py-4 px-3">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <p class="text-danger">${message}</p>
            </div>
        `;
    }
    
    /**
     * Mark notifications as read via AJAX
     * Sends request to api/clear_notifications.php to remove the red badge
     */
    function markNotificationsAsRead() {
        // Only mark as read if badge is visible
        if (!notificationBadge) {
            return;
        }
        
        fetch(buildApiUrl('api/clear_notifications.php'), {
            method: 'POST',
            headers: addCsrfHeader({
                'Content-Type': 'application/x-www-form-urlencoded'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove badge without page refresh
                if (notificationBadge) {
                    notificationBadge.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error marking notifications as read:', error);
        });
    }
    
    /**
     * Close panel when clicking outside
     */
    document.addEventListener('click', function(e) {
        if (panelOpen && !notificationBell.contains(e.target) && !notificationPanel.contains(e.target)) {
            closeNotificationPanel();
        }
    });
}

/**
 * Initialize Quick Add Location functionality
 * Allows users to quickly add a new location while editing/creating inventory items
 */
function initQuickAddLocation() {
    const quickAddBtn = document.getElementById('quickAddLocationBtn');
    const quickAddInput = document.getElementById('quickAddLocationInput');
    const locationSelect = document.getElementById('itemLocation');
    
    if (!quickAddBtn || !quickAddInput || !locationSelect) return;
    
    quickAddBtn.addEventListener('click', async function() {
        const locationName = quickAddInput.value.trim();
        
        if (!locationName) {
            showToast('Bitte geben Sie einen Standort-Namen ein', 'warning');
            return;
        }
        
        // Get CSRF token
        const csrfToken = document.querySelector('[data-csrf-token]')?.getAttribute('data-csrf-token');
        
        if (!csrfToken) {
            showToast('CSRF-Token fehlt. Bitte laden Sie die Seite neu.', 'danger');
            return;
        }
        
        // Disable button and show loading state using toggleButtonLoading
        toggleButtonLoading(quickAddBtn, true);
        
        try {
            const formData = new FormData();
            formData.append('action', 'add_location');
            formData.append('location_name', locationName);
            formData.append('csrf_token', csrfToken);
            
            const response = await fetch(buildApiUrl('index.php?page=inventory'), {
                method: 'POST',
                headers: addCsrfHeader({}),
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast(data.message || 'Standort erfolgreich hinzugefügt', 'success');
                
                // Clear input
                quickAddInput.value = '';
                
                // Update location dropdown with new location
                if (data.location) {
                    // Create new option
                    const option = document.createElement('option');
                    option.value = data.location;
                    option.textContent = data.location;
                    option.selected = true;
                    
                    // Add to select (insert alphabetically if possible, or just add at end)
                    locationSelect.add(option);
                    
                    // If we have the full list, recreate the dropdown
                    if (data.locations && Array.isArray(data.locations)) {
                        // Clear existing options except the first (placeholder)
                        while (locationSelect.options.length > 1) {
                            locationSelect.remove(1);
                        }
                        
                        // Add all locations
                        data.locations.forEach(loc => {
                            const opt = document.createElement('option');
                            opt.value = loc;
                            opt.textContent = loc;
                            if (loc === data.location) {
                                opt.selected = true;
                            }
                            locationSelect.add(opt);
                        });
                    }
                }
            } else {
                showToast(data.message || 'Fehler beim Hinzufügen des Standorts', 'danger');
            }
        } catch (error) {
            console.error('Error adding location:', error);
            showToast('Fehler beim Hinzufügen des Standorts', 'danger');
        } finally {
            // Re-enable button
            toggleButtonLoading(quickAddBtn, false);
        }
    });
    
    // Allow Enter key to trigger add
    quickAddInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            quickAddBtn.click();
        }
    });
}
