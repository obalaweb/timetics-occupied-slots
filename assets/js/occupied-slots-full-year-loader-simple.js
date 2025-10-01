/**
 * Timetics Occupied Slots - Full Year Loader (Simplified)
 * 
 * Simplified version to debug the issue
 */

(function() {
    'use strict';
    
    // DEBUG: Confirm script is loading
    console.log('[Full Year Loader] 🚀 Script loaded and executing');
    
    // Simple configuration
    const CONFIG = {
        API_ENDPOINT: '/wp-json/timetics-occupied-slots/v1/bookings/entries',
        FULL_YEAR_ENDPOINT: '/wp-json/timetics-occupied-slots/v1/bookings/entries/full-year',
        HOT_CACHE_DAYS: 30,
        WARM_CACHE_DAYS: 90,
        COLD_CACHE_DAYS: 365
    };
    
    // State management
    let isInitialized = false;
    let cachedBlockedDates = new Map(); // Cache for blocked dates
    
    /**
     * Initialize full year loader
     */
    function init() {
        if (isInitialized) {
            return;
        }
        
        // Load immediate dates (0-30 days)
        loadImmediateDates();
        
        // Load medium-term dates (31-90 days) in background
        setTimeout(() => loadMediumTermDates(), 1000);
        
        isInitialized = true;
    }
    
    /**
     * Load immediate dates (0-30 days)
     */
    async function loadImmediateDates() {
        try {
            const blockedDates = await fetchBlockedDates('2025-09-30', '2025-10-30', 'hot');
            applyBlockedDates(blockedDates);
        } catch (error) {
            console.error('[Full Year Loader] ❌ Failed to load immediate dates:', error);
        }
    }
    
    /**
     * Load medium-term dates (31-90 days) - Focus on November
     */
    async function loadMediumTermDates() {
        try {
            // Focus on November 2025 specifically
            const blockedDates = await fetchBlockedDates('2025-11-01', '2025-11-30', 'warm');
            applyBlockedDates(blockedDates);
        } catch (error) {
            console.error('[Full Year Loader] ❌ Failed to load medium-term dates:', error);
        }
    }
    
    /**
     * Fetch blocked dates from API with caching
     */
    async function fetchBlockedDates(startDate, endDate, tier = 'auto') {
        // Check cache first
        const cacheKey = `${startDate}-${endDate}-${tier}`;
        if (cachedBlockedDates.has(cacheKey)) {
            return cachedBlockedDates.get(cacheKey);
        }
        
        const params = {
            staff_id: '71',
            meeting_id: '2315'
        };
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
        
        // Use regular endpoint for all requests
        const url = `${CONFIG.API_ENDPOINT}?staff_id=${params.staff_id}&meeting_id=${params.meeting_id}&start_date=${startDate}&end_date=${endDate}&timezone=${encodeURIComponent(timezone)}`;
        
        try {
            const response = await fetch(url);
            const data = await response.json();
            
            if (data && data.data && data.data.blocked_dates) {
                // Cache the result
                cachedBlockedDates.set(cacheKey, data.data.blocked_dates);
                return data.data.blocked_dates;
            } else {
                // Cache empty result too
                cachedBlockedDates.set(cacheKey, []);
                return [];
            }
        } catch (error) {
            console.error('[Full Year Loader] ❌ API request failed:', error);
            // Cache empty result on error
            cachedBlockedDates.set(cacheKey, []);
            return [];
        }
    }
    
    /**
     * Apply blocked dates to calendar
     */
    function applyBlockedDates(blockedDates) {
        if (!blockedDates || blockedDates.length === 0) {
            return;
        }
        
        // Simple calendar detection without expensive logging
        const calendarContainers = document.querySelectorAll(
            '[class*="calendar"], [class*="date"], [class*="timetics"], [class*="booking"], [class*="flatpickr"]'
        );
        
        let appliedCount = 0;
        
        blockedDates.forEach(date => {
            // Convert date to different formats for matching
            const dateObj = new Date(date);
            const monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"];
            const ariaLabelDate = `${monthNames[dateObj.getMonth()]} ${dateObj.getDate()}, ${dateObj.getFullYear()}`;
            
            // Try different selectors to find the date element
            const selectors = [
                `[aria-label="${ariaLabelDate}"]`,
                `[aria-label*="${date}"]`,
                `[data-date="${date}"]`,
                `[data-value="${date}"]`,
                `[title*="${date}"]`,
                `[title*="${ariaLabelDate}"]`
            ];
            
            let found = false;
            selectors.forEach(selector => {
                const elements = document.querySelectorAll(selector);
                if (elements.length > 0) {
                    elements.forEach(element => {
                        if (!element.classList.contains('timetics-date-blocked')) {
                            // Add blocked class
                            element.classList.add('timetics-date-blocked');
                            
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
                            
                            appliedCount++;
                            found = true;
                        }
                    });
                }
            });
            
            // If not found with selectors, try text search
            if (!found) {
                // Search in calendar containers
                calendarContainers.forEach(container => {
                    const potentialElements = container.querySelectorAll('*');
                    potentialElements.forEach(element => {
                        if (element.classList.contains('timetics-date-blocked')) return;
                        
                        const text = element.textContent || element.innerText || '';
                        const ariaLabel = element.getAttribute('aria-label') || '';
                        
                        if (text.includes(date) || ariaLabel.includes(date) || ariaLabel.includes(ariaLabelDate)) {
                            // Apply blocking
                            element.classList.add('timetics-date-blocked');
                            element.style.backgroundColor = '#666666';
                            element.style.color = '#ffffff';
                            element.style.cursor = 'not-allowed';
                            element.style.opacity = '0.7';
                            element.style.pointerEvents = 'none';
                            element.setAttribute('disabled', 'true');
                            element.setAttribute('data-blocked', 'true');
                            element.setAttribute('data-blocked-reason', 'date_fully_occupied');
                            element.setAttribute('aria-disabled', 'true');
                            element.title = 'This date is fully occupied';
                            
                            element.onclick = null;
                            element.addEventListener('click', (e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                return false;
                            }, true);
                            
                            appliedCount++;
                            found = true;
                        }
                    });
                });
            }
        });
        
        // Simple retry only if no elements found and we have blocked dates
        if (appliedCount === 0 && blockedDates.length > 0) {
            // Single retry after 2 seconds
            setTimeout(() => {
                applyBlockedDates(blockedDates);
            }, 2000);
        }
    }
    
    /**
     * Initialize when DOM is ready
     */
    function initializeWhenReady() {
        console.log('[Full Year Loader] 📄 DOM ready check');
        console.log('[Full Year Loader] - Document ready state:', document.readyState);
        
        if (document.readyState === 'loading') {
            console.log('[Full Year Loader] ⏳ Document still loading, waiting for DOMContentLoaded');
            document.addEventListener('DOMContentLoaded', init);
        } else {
            console.log('[Full Year Loader] ✅ Document ready, initializing immediately');
            init();
        }
    }
    
    // Start initialization
    console.log('[Full Year Loader] 🚀 Starting initialization process');
    initializeWhenReady();
    
    // Expose for debugging
    window.FullYearLoaderSimple = {
        init,
        loadImmediateDates,
        loadMediumTermDates,
        fetchBlockedDates,
        applyBlockedDates
    };
    
    console.log('[Full Year Loader] ✅ Script initialization complete');
    
})();
