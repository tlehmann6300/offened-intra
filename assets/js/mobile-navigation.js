/**
 * Mobile Navigation Handler
 * Handles mobile bottom navigation, mobile search overlay, and mobile notifications
 * Note: Mobile offcanvas menu is handled by Bootstrap 5.3
 */
(function() {
    'use strict';
    
    // Configuration constants
    const SEARCH_FOCUS_DELAY_MS = 300; // Delay before focusing search input
    
    // Initialize mobile navigation on page load
    document.addEventListener('DOMContentLoaded', function() {
        initMobileSearch();
        initMobileNotifications();
    });
    
    /**
     * Initialize mobile search overlay
     */
    function initMobileSearch() {
        const mobileSearchBtn = document.getElementById('mobileSearchBtn');
        const mobileSearchOverlay = document.getElementById('mobileSearchOverlay');
        const mobileSearchClose = document.getElementById('mobileSearchClose');
        
        if (!mobileSearchBtn || !mobileSearchOverlay) {
            return;
        }
        
        // Open mobile search
        mobileSearchBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openMobileSearch();
        });
        
        // Close mobile search
        if (mobileSearchClose) {
            mobileSearchClose.addEventListener('click', function(e) {
                e.preventDefault();
                closeMobileSearch();
            });
        }
        
        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && mobileSearchOverlay.classList.contains('active')) {
                closeMobileSearch();
            }
        });
    }
    
    /**
     * Open mobile search overlay
     */
    function openMobileSearch() {
        const mobileSearchOverlay = document.getElementById('mobileSearchOverlay');
        
        if (mobileSearchOverlay) {
            mobileSearchOverlay.classList.add('active');
            
            // Focus on search input after overlay transition completes
            setTimeout(function() {
                const searchInput = mobileSearchOverlay.querySelector('input[type="search"], input[type="text"]');
                if (searchInput) {
                    searchInput.focus();
                }
            }, SEARCH_FOCUS_DELAY_MS);
        }
    }
    
    /**
     * Close mobile search overlay
     */
    function closeMobileSearch() {
        const mobileSearchOverlay = document.getElementById('mobileSearchOverlay');
        
        if (mobileSearchOverlay) {
            mobileSearchOverlay.classList.remove('active');
        }
    }
    
    /**
     * Initialize mobile notifications (sync with desktop notification bell)
     */
    function initMobileNotifications() {
        const mobileNotificationBell = document.getElementById('mobileNotificationBell');
        const desktopNotificationBell = document.getElementById('notificationBell');
        
        if (!mobileNotificationBell || !desktopNotificationBell) {
            return;
        }
        
        // When mobile notification bell is clicked, trigger desktop bell functionality
        mobileNotificationBell.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Dispatch custom event to trigger notification panel
            const notificationEvent = new CustomEvent('showNotifications', {
                bubbles: true,
                detail: { source: 'mobile' }
            });
            desktopNotificationBell.dispatchEvent(notificationEvent);
            
            // Fallback: trigger click if custom event not handled
            desktopNotificationBell.click();
            
            // Position the notification panel for mobile view
            const notificationPanel = document.getElementById('notificationPanel');
            if (notificationPanel) {
                // Add mobile-specific positioning class
                notificationPanel.classList.add('mobile-positioned');
            }
        });
    }
    
})();
