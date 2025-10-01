/**
 * Direct Fix for Occupied Slots - Simple and Reliable
 * This script directly calls our API and applies blocked dates
 */

(function() {
    'use strict';
    
    // Configuration
    const API_ENDPOINT = '/wp-json/timetics-occupied-slots/v1/bookings/entries';
    const BLOCKED_CLASS = 'timetics-date-blocked';
    
    // PERFORMANCE OPTIMIZATION: Track resources for cleanup
    let observer = null;
    let applyTimeouts = [];
    let isDestroyed = false;
    let debounceTimeout = null;
    
    // Get URL parameters
    function getUrlParams() {
        const params = new URLSearchParams(window.location.search);
        return {
            staff_id: params.get('staff_id') || '71',
            meeting_id: params.get('meeting_id') || '2315'
        };
    }
    
    // Call our API to get blocked dates
    async function getBlockedDates() {
        try {
            const params = getUrlParams();
            const startDate = new Date().toISOString().split('T')[0];
            const endDate = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
            
            const url = `${API_ENDPOINT}?staff_id=${params.staff_id}&meeting_id=${params.meeting_id}&start_date=${startDate}&end_date=${endDate}&timezone=${encodeURIComponent(timezone)}`;
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data && data.data && data.data.blocked_dates) {
                return data.data.blocked_dates;
            } else {
                return [];
            }
        } catch (error) {
            return [];
        }
    }
    
    // Apply blocked date styling
    function applyBlockedDates(blockedDates) {
        if (!blockedDates || blockedDates.length === 0 || isDestroyed) {
            return;
        }
        
        let styledCount = 0;
        
        // PERFORMANCE OPTIMIZATION: Cache DOM queries and use more specific selectors
        const calendarContainers = document.querySelectorAll(
            '[class*="calendar"], [class*="date"], [class*="timetics"], [class*="booking"], [class*="flatpickr"]'
        );
        
        // Get all potential date elements from calendar containers only (not entire DOM)
        const potentialDateElements = Array.from(calendarContainers).flatMap(container => 
            Array.from(container.querySelectorAll('*'))
        );
        
        blockedDates.forEach(date => {
            // Convert date to different formats for matching
            const dateObj = new Date(date);
            const monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"];
            const ariaLabelDate = `${monthNames[dateObj.getMonth()]} ${dateObj.getDate()}, ${dateObj.getFullYear()}`;
            
            // PERFORMANCE OPTIMIZATION: Use specific selectors first (fastest)
            const selectors = [
                `[aria-label="${ariaLabelDate}"]`,
                `[aria-label*="${date}"]`,
                `[data-date="${date}"]`,
                `[data-value="${date}"]`,
                `[title*="${date}"]`,
                `[title*="${ariaLabelDate}"]`
            ];
            
            let foundWithSelectors = false;
            selectors.forEach(selector => {
                const elements = document.querySelectorAll(selector);
                if (elements.length > 0) {
                    elements.forEach(element => {
                        if (!element.classList.contains(BLOCKED_CLASS)) {
                            styleBlockedElement(element);
                            styledCount++;
                            foundWithSelectors = true;
                        }
                    });
                }
            });
            
            // PERFORMANCE OPTIMIZATION: Only do expensive text search if selectors didn't find anything
            if (!foundWithSelectors) {
                potentialDateElements.forEach(element => {
                    if (element.classList.contains(BLOCKED_CLASS)) return; // Skip already processed
                    
                    const text = element.textContent || element.innerText || '';
                    const ariaLabel = element.getAttribute('aria-label') || '';
                    
                    if (text.includes(date) || ariaLabel.includes(date) || ariaLabel.includes(ariaLabelDate)) {
                        styleBlockedElement(element);
                        styledCount++;
                    }
                });
            }
        });
    }
    
    // Style a blocked element
    function styleBlockedElement(element) {
        if (isDestroyed) return;
        
        // Add blocked class
        element.classList.add(BLOCKED_CLASS);
        
        // Add visual styling
        element.style.backgroundColor = '#666666';
        element.style.color = '#ffffff';
        element.style.cursor = 'not-allowed';
        element.style.opacity = '0.7';
        element.style.pointerEvents = 'none';
        
        // Add attributes
        element.setAttribute('disabled', 'true');
        element.setAttribute('data-blocked', 'true');
        element.setAttribute('data-blocked-reason', 'date_fully_occupied');
        element.setAttribute('aria-disabled', 'true');
        element.title = 'This date is fully occupied';
        
        // Prevent interactions
        element.onclick = null;
        element.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }, true);
    }
    
    // PERFORMANCE OPTIMIZATION: Debounced apply function to prevent race conditions
    function debouncedApplyBlockedDates(blockedDates) {
        if (isDestroyed) return;
        
        // Clear existing debounce timeout
        if (debounceTimeout) {
            clearTimeout(debounceTimeout);
        }
        
        // Set new debounce timeout
        debounceTimeout = setTimeout(() => {
            if (!isDestroyed) {
                applyBlockedDates(blockedDates);
            }
        }, 100); // 100ms debounce delay
    }
    
    // PERFORMANCE OPTIMIZATION: Cleanup function to prevent memory leaks
    function cleanup() {
        isDestroyed = true;
        
        // Clear debounce timeout
        if (debounceTimeout) {
            clearTimeout(debounceTimeout);
            debounceTimeout = null;
        }
        
        // Clear all timeouts
        applyTimeouts.forEach(timeout => clearTimeout(timeout));
        applyTimeouts = [];
        
        // Disconnect observer
        if (observer) {
            observer.disconnect();
            observer = null;
        }
    }
    
    // Main execution
    async function main() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', runDirectFix);
        } else {
            runDirectFix();
        }
    }
    
    async function runDirectFix() {
        if (isDestroyed) return;
        
        // Get blocked dates
        const blockedDates = await getBlockedDates();
        
        if (blockedDates.length > 0) {
            // PERFORMANCE OPTIMIZATION: Use debounced apply to prevent race conditions
            debouncedApplyBlockedDates(blockedDates);
            
            // Apply again after delays to catch dynamic content (using debounced version)
            const timeout1 = setTimeout(() => debouncedApplyBlockedDates(blockedDates), 500);
            const timeout2 = setTimeout(() => debouncedApplyBlockedDates(blockedDates), 2000);
            const timeout3 = setTimeout(() => debouncedApplyBlockedDates(blockedDates), 5000);
            
            // Track timeouts for cleanup
            applyTimeouts.push(timeout1, timeout2, timeout3);
            
            // Monitor for new content with debounced observer
            observer = new MutationObserver(() => {
                debouncedApplyBlockedDates(blockedDates);
            });
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }
    
    // Start the direct fix
    main();
    
    // PERFORMANCE OPTIMIZATION: Add cleanup event listeners
    window.addEventListener('beforeunload', cleanup);
    window.addEventListener('pagehide', cleanup);
    
    // Expose for debugging and manual cleanup (if needed)
    window.DirectFixOccupiedSlots = {
        getBlockedDates,
        applyBlockedDates,
        styleBlockedElement,
        cleanup,
        isDestroyed: () => isDestroyed
    };
    
})();
