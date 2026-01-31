/**
 * Events Module - JE Alumni Connect
 * Event countdown timer functionality
 */

import { showToast } from './core.js';

export function initEventCountdown() {
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
