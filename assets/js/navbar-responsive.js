/**
 * Responsive Navbar Handler
 * Moves less important navbar links into a "Mehr" (More) submenu on small screens
 */
(function() {
    'use strict';
    
    // Configuration
    const BREAKPOINT = 992; // lg breakpoint (Bootstrap default)
    const LINK_IDS_TO_HIDE = ['navbar-item-projects', 'navbar-item-inventory'];
    
    let moreDropdownCreated = false;
    let originalLinksMap = new Map(); // Store original link elements
    
    /**
     * Create the "Mehr" dropdown menu structure
     */
    function createMoreDropdown() {
        if (moreDropdownCreated) {
            return document.getElementById('more-dropdown-container');
        }
        
        const navbar = document.querySelector('.navbar-nav');
        if (!navbar) {
            return null;
        }
        
        // Create dropdown container
        const dropdownContainer = document.createElement('li');
        dropdownContainer.className = 'nav-item dropdown';
        dropdownContainer.id = 'more-dropdown-container';
        
        // Create dropdown toggle button
        const dropdownToggle = document.createElement('a');
        dropdownToggle.className = 'nav-link dropdown-toggle';
        dropdownToggle.href = '#';
        dropdownToggle.id = 'moreDropdown';
        dropdownToggle.setAttribute('role', 'button');
        dropdownToggle.setAttribute('data-bs-toggle', 'dropdown');
        dropdownToggle.setAttribute('aria-expanded', 'false');
        dropdownToggle.innerHTML = '<i class="fas fa-ellipsis-h me-2"></i>Mehr';
        
        // Create dropdown menu
        const dropdownMenu = document.createElement('ul');
        dropdownMenu.className = 'dropdown-menu';
        dropdownMenu.setAttribute('aria-labelledby', 'moreDropdown');
        dropdownMenu.id = 'more-dropdown-menu';
        
        dropdownContainer.appendChild(dropdownToggle);
        dropdownContainer.appendChild(dropdownMenu);
        
        // Insert before notification bell
        const notificationBell = document.getElementById('notificationBellContainer');
        if (notificationBell) {
            navbar.insertBefore(dropdownContainer, notificationBell);
        } else {
            navbar.appendChild(dropdownContainer);
        }
        
        moreDropdownCreated = true;
        return dropdownContainer;
    }
    
    /**
     * Move link to "Mehr" dropdown
     */
    function moveToMoreDropdown(linkElement, linkId) {
        const moreDropdown = createMoreDropdown();
        if (!moreDropdown) {
            return;
        }
        
        const dropdownMenu = moreDropdown.querySelector('#more-dropdown-menu');
        if (!dropdownMenu) {
            return;
        }
        
        // Store original element
        if (!originalLinksMap.has(linkId)) {
            originalLinksMap.set(linkId, linkElement);
        }
        
        // Get link content
        const originalLink = linkElement.querySelector('a');
        if (!originalLink) {
            return;
        }
        
        // Create dropdown item
        const dropdownItem = document.createElement('li');
        const link = document.createElement('a');
        link.className = 'dropdown-item';
        link.href = originalLink.href;
        link.innerHTML = originalLink.innerHTML;
        
        dropdownItem.setAttribute('data-link-id', linkId);
        dropdownItem.appendChild(link);
        dropdownMenu.appendChild(dropdownItem);
        
        // Hide original link
        linkElement.classList.add('d-lg-block');
        linkElement.classList.add('d-none');
    }
    
    /**
     * Restore link to main navbar
     */
    function restoreToNavbar(linkElement, linkId) {
        const moreDropdown = document.getElementById('more-dropdown-container');
        
        // Show original link (it will be visible on large screens due to Bootstrap classes)
        linkElement.classList.remove('d-none');
        
        // Remove from "Mehr" dropdown if it's there
        if (moreDropdown) {
            const dropdownMenu = moreDropdown.querySelector('#more-dropdown-menu');
            if (dropdownMenu) {
                const items = dropdownMenu.querySelectorAll(`li[data-link-id="${linkId}"]`);
                items.forEach(item => item.remove());
                
                // Remove "Mehr" dropdown if empty
                if (dropdownMenu.children.length === 0) {
                    moreDropdown.remove();
                    moreDropdownCreated = false;
                }
            }
        }
    }
    
    /**
     * Handle responsive navbar behavior
     */
    function handleResponsiveNavbar() {
        const width = window.innerWidth;
        
        LINK_IDS_TO_HIDE.forEach(linkId => {
            const linkElement = document.getElementById(linkId);
            if (!linkElement) {
                return;
            }
            
            if (width < BREAKPOINT) {
                // Small screen: Move links to "Mehr" dropdown
                if (!linkElement.classList.contains('d-none') || !moreDropdownCreated) {
                    moveToMoreDropdown(linkElement, linkId);
                }
            } else {
                // Large screen: Restore links to main navbar
                restoreToNavbar(linkElement, linkId);
            }
        });
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        handleResponsiveNavbar();
    });
    
    // Handle window resize with debounce
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            handleResponsiveNavbar();
        }, 250);
    });
})();
