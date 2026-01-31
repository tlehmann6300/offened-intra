/**
 * Pull-to-Refresh Module for Mobile
 * Implements pull-to-refresh functionality for News and Events pages
 * Only active on mobile devices (< 992px)
 */

(function() {
    'use strict';
    
    // Configuration
    const PULL_THRESHOLD = 80; // pixels to pull before triggering refresh
    const MAX_PULL_DISTANCE = 120; // maximum pull distance for animation
    const MOBILE_BREAKPOINT = 992; // lg breakpoint in pixels
    
    // State
    let startY = 0;
    let currentY = 0;
    let isPulling = false;
    let isRefreshing = false;
    let pullIndicator = null;
    
    /**
     * Check if device is mobile based on screen width
     */
    function isMobileDevice() {
        return window.innerWidth < MOBILE_BREAKPOINT;
    }
    
    /**
     * Create pull-to-refresh indicator
     */
    function createPullIndicator() {
        const indicator = document.createElement('div');
        indicator.id = 'pull-to-refresh-indicator';
        indicator.className = 'pull-to-refresh-indicator';
        indicator.innerHTML = `
            <div class="pull-to-refresh-content">
                <div class="pull-to-refresh-spinner">
                    <i class="fas fa-sync-alt"></i>
                </div>
                <div class="pull-to-refresh-text">Zum Aktualisieren ziehen</div>
            </div>
        `;
        return indicator;
    }
    
    /**
     * Get the scrollable container
     */
    function getScrollContainer() {
        // Try to find specific containers first
        const mainContent = document.querySelector('.main-content');
        if (mainContent) return mainContent;
        return document.documentElement;
    }
    
    /**
     * Check if we can pull (at top of page)
     */
    function canPull() {
        const container = getScrollContainer();
        return container.scrollTop === 0 || window.pageYOffset === 0;
    }
    
    /**
     * Handle touch start
     */
    function handleTouchStart(e) {
        if (!isMobileDevice() || isRefreshing || !canPull()) {
            return;
        }
        
        startY = e.touches[0].clientY;
        isPulling = true;
    }
    
    /**
     * Handle touch move
     */
    function handleTouchMove(e) {
        if (!isPulling || isRefreshing) {
            return;
        }
        
        currentY = e.touches[0].clientY;
        const pullDistance = currentY - startY;
        
        // Only proceed if pulling down and at top of page
        if (pullDistance > 0 && canPull()) {
            // Prevent default scrolling
            e.preventDefault();
            
            // Calculate display distance with diminishing returns
            const displayDistance = Math.min(
                pullDistance * 0.5,
                MAX_PULL_DISTANCE
            );
            
            // Update indicator
            if (pullIndicator) {
                pullIndicator.style.transform = `translateY(${displayDistance}px)`;
                pullIndicator.style.opacity = Math.min(displayDistance / PULL_THRESHOLD, 1);
                
                const spinner = pullIndicator.querySelector('.pull-to-refresh-spinner i');
                const text = pullIndicator.querySelector('.pull-to-refresh-text');
                
                if (pullDistance >= PULL_THRESHOLD) {
                    spinner.style.transform = `rotate(180deg)`;
                    text.textContent = 'Loslassen zum Aktualisieren';
                    pullIndicator.classList.add('ready');
                } else {
                    spinner.style.transform = `rotate(${pullDistance * 2}deg)`;
                    text.textContent = 'Zum Aktualisieren ziehen';
                    pullIndicator.classList.remove('ready');
                }
            }
        }
    }
    
    /**
     * Handle touch end
     */
    function handleTouchEnd(e) {
        if (!isPulling || isRefreshing) {
            return;
        }
        
        const pullDistance = currentY - startY;
        
        if (pullDistance >= PULL_THRESHOLD && canPull()) {
            // Trigger refresh
            triggerRefresh();
        } else {
            // Reset indicator
            resetIndicator();
        }
        
        isPulling = false;
        startY = 0;
        currentY = 0;
    }
    
    /**
     * Reset the pull indicator
     */
    function resetIndicator() {
        if (pullIndicator) {
            pullIndicator.style.transform = 'translateY(0)';
            pullIndicator.style.opacity = '0';
            pullIndicator.classList.remove('ready');
        }
    }
    
    /**
     * Trigger page refresh
     */
    function triggerRefresh() {
        if (isRefreshing) return;
        
        isRefreshing = true;
        
        if (pullIndicator) {
            const text = pullIndicator.querySelector('.pull-to-refresh-text');
            const spinner = pullIndicator.querySelector('.pull-to-refresh-spinner i');
            text.textContent = 'Wird aktualisiert...';
            spinner.style.animation = 'spin 1s linear infinite';
            pullIndicator.classList.add('refreshing');
        }
        
        // Perform the actual refresh
        performRefresh().finally(() => {
            // Delay hiding the indicator for better UX
            setTimeout(() => {
                isRefreshing = false;
                resetIndicator();
                if (pullIndicator) {
                    pullIndicator.classList.remove('refreshing');
                    const spinner = pullIndicator.querySelector('.pull-to-refresh-spinner i');
                    spinner.style.animation = '';
                }
            }, 500);
        });
    }
    
    /**
     * Perform the actual page refresh
     */
    function performRefresh() {
        // Determine which page we're on and refresh accordingly
        const currentPage = getCurrentPage();
        
        if (currentPage === 'newsroom') {
            return refreshNewsroom();
        } else if (currentPage === 'events') {
            return refreshEvents();
        } else {
            // Default: reload the page
            return new Promise((resolve) => {
                window.location.reload();
                resolve();
            });
        }
    }
    
    /**
     * Get current page from URL
     */
    function getCurrentPage() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('page') || 'home';
    }
    
    /**
     * Refresh newsroom page
     */
    function refreshNewsroom() {
        return new Promise((resolve) => {
            // Simply reload the page for now
            // In a more sophisticated implementation, we could load new content via AJAX
            window.location.reload();
            resolve();
        });
    }
    
    /**
     * Refresh events page
     */
    function refreshEvents() {
        return new Promise((resolve) => {
            // Simply reload the page for now
            // In a more sophisticated implementation, we could load new content via AJAX
            window.location.reload();
            resolve();
        });
    }
    
    /**
     * Initialize pull-to-refresh
     */
    function init() {
        if (!isMobileDevice()) {
            return;
        }
        
        // Only enable on specific pages
        const currentPage = getCurrentPage();
        if (currentPage !== 'newsroom' && currentPage !== 'events') {
            return;
        }
        
        // Create and insert indicator
        pullIndicator = createPullIndicator();
        document.body.insertBefore(pullIndicator, document.body.firstChild);
        
        // Add event listeners
        document.addEventListener('touchstart', handleTouchStart, { passive: true });
        document.addEventListener('touchmove', handleTouchMove, { passive: false });
        document.addEventListener('touchend', handleTouchEnd, { passive: true });
        
        // Re-initialize on window resize
        window.addEventListener('resize', function() {
            if (!isMobileDevice()) {
                // Remove event listeners on desktop
                document.removeEventListener('touchstart', handleTouchStart);
                document.removeEventListener('touchmove', handleTouchMove);
                document.removeEventListener('touchend', handleTouchEnd);
                
                if (pullIndicator && pullIndicator.parentNode) {
                    pullIndicator.parentNode.removeChild(pullIndicator);
                    pullIndicator = null;
                }
            }
        });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
