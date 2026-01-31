/**
 * Home Page JavaScript
 * Countdown timer for next event
 */
document.addEventListener('DOMContentLoaded', function() {
    const countdownElement = document.getElementById('event-countdown');
    if (countdownElement) {
        const eventDateStr = countdownElement.getAttribute('data-event-date');
        
        // Check for empty or null date string
        if (!eventDateStr || eventDateStr.trim() === '') {
            // Hide the countdown element elegantly
            countdownElement.style.display = 'none';
            return;
        }
        
        // Replace spaces with 'T' for ISO-8601 conformity
        const isoDateStr = eventDateStr.replace(/ /g, 'T');
        const eventDate = new Date(isoDateStr).getTime();
        
        // Validate date
        if (isNaN(eventDate)) {
            // Hide the countdown element elegantly for invalid dates
            countdownElement.style.display = 'none';
            return;
        }
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = eventDate - now;
            
            if (distance < 0) {
                // Hide the countdown element when event has passed
                countdownElement.style.display = 'none';
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            // Format with leading zeros
            const formatWithZero = (num) => num.toString().padStart(2, '0');
            
            // Check if DOM elements exist before updating them
            const daysElement = document.getElementById('days');
            const hoursElement = document.getElementById('hours');
            const minutesElement = document.getElementById('minutes');
            const secondsElement = document.getElementById('seconds');
            
            if (daysElement) daysElement.textContent = formatWithZero(days);
            if (hoursElement) hoursElement.textContent = formatWithZero(hours);
            if (minutesElement) minutesElement.textContent = formatWithZero(minutes);
            if (secondsElement) secondsElement.textContent = formatWithZero(seconds);
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
    }
});
