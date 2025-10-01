/**
 * Timetics Occupied Slots - Full Year Loader
 * 
 * Progressive loading for full year blocked dates
 * Maintains backward compatibility with existing 30-day functionality
 */

(function() {
    'use strict';
    
    // DEBUG: Confirm script is loading
    console.log('[Full Year Loader] 🚀 Script loaded and executing');
    
    // Configuration
    const CONFIG = {
        API_ENDPOINT: '/wp-json/timetics-occupied-slots/v1/bookings/entries',
        FULL_YEAR_ENDPOINT: '/wp-json/timetics-occupied-slots/v1/bookings/entries/full-year',
        HOT_CACHE_DAYS: 30,
        WARM_CACHE_DAYS: 90,
        COLD_CACHE_DAYS: 365,
        LOAD_DELAYS: {
            hot: 0,      // Immediate
            warm: 1000,  // 1 second
            cold: 3000   // 3 seconds
        }
    };
    
    // State management
    let isInitialized = false;
    let loadedRanges = new Set();
    let loadingPromises = new Map();
    
    /**
     * Initialize full year loader
     */
    function init() {
        console.log('[Full Year Loader] 🚀 Initialization started');
        console.log('[Full Year Loader] - Already initialized:', isInitialized);
        
        if (isInitialized) {
            console.log('[Full Year Loader] ⚠️ Already initialized, skipping');
            return;
        }
        
        // Only initialize if full year support is needed
        const shouldLoad = shouldLoadFullYear();
        console.log('[Full Year Loader] - Should load full year:', shouldLoad);
        
        if (!shouldLoad) {
            console.log('[Full Year Loader] ⚠️ Full year support not needed, skipping initialization');
            return;
        }
        
        console.log('[Full Year Loader] ✅ Initializing full year support');
        
        // Load immediate dates (0-30 days) - same as existing functionality
        console.log('[Full Year Loader] 🔥 Loading immediate dates (0-30 days)');
        loadImmediateDates();
        
        // Load medium-term dates (31-90 days) in background
        console.log('[Full Year Loader] ⏰ Scheduling medium-term dates (31-90 days) in', CONFIG.LOAD_DELAYS.warm, 'ms');
        setTimeout(() => loadMediumTermDates(), CONFIG.LOAD_DELAYS.warm);
        
        // Setup on-demand loading for long-term dates (91-365 days)
        console.log('[Full Year Loader] 🎯 Setting up on-demand loading for long-term dates (91-365 days)');
        setupOnDemandLoading();
        
        isInitialized = true;
        console.log('[Full Year Loader] ✅ Full year support initialized successfully');
    }
    
    /**
     * Check if full year support should be loaded
     */
    function shouldLoadFullYear() {
        console.log('[Full Year Loader] 🔍 Checking if full year support should be loaded');
        
        // FORCE ENABLE FOR DEBUGGING - Remove this in production
        console.log('[Full Year Loader] 🚨 DEBUG MODE: Forcing full year support to always load');
        return true;
        
        // Check for full year parameter in URL
        const urlParams = new URLSearchParams(window.location.search);
        const fullYearParam = urlParams.get('full_year');
        console.log('[Full Year Loader] - URL full_year parameter:', fullYearParam);
        
        if (fullYearParam === 'true') {
            console.log('[Full Year Loader] ✅ Full year enabled via URL parameter');
            return true;
        }
        
        // Check for calendar navigation beyond 3 months
        const calendarElements = document.querySelectorAll('[class*="calendar"], [class*="date-picker"]');
        console.log('[Full Year Loader] - Calendar elements found:', calendarElements.length);
        
        if (calendarElements.length > 0) {
            console.log('[Full Year Loader] ✅ Calendar elements detected, enabling full year support');
            // Monitor for future month navigation
            return true;
        }
        
        console.log('[Full Year Loader] ❌ Full year support not needed');
        return false;
    }
    
    /**
     * Load immediate dates (0-30 days) - maintains existing functionality
     */
    async function loadImmediateDates() {
        const range = getDateRange(CONFIG.HOT_CACHE_DAYS);
        const cacheKey = `hot_${range.start}_${range.end}`;
        
        if (loadedRanges.has(cacheKey)) {
            return;
        }
        
        try {
            console.log('[Full Year Loader] Loading immediate dates (0-30 days)');
            const blockedDates = await fetchBlockedDates(range.start, range.end, 'hot');
            applyBlockedDates(blockedDates);
            loadedRanges.add(cacheKey);
        } catch (error) {
            console.warn('[Full Year Loader] Failed to load immediate dates:', error);
        }
    }
    
    /**
     * Load medium-term dates (31-90 days) in background
     */
    async function loadMediumTermDates() {
        const range = getDateRange(CONFIG.WARM_CACHE_DAYS, CONFIG.HOT_CACHE_DAYS);
        const cacheKey = `warm_${range.start}_${range.end}`;
        
        if (loadedRanges.has(cacheKey)) {
            console.log('[Full Year Loader] Medium-term dates already loaded for range:', range);
            return;
        }
        
        try {
            console.log('[Full Year Loader] 🔥 Loading medium-term dates (31-90 days)');
            console.log('[Full Year Loader] Date range:', range);
            console.log('[Full Year Loader] Cache key:', cacheKey);
            
            const blockedDates = await fetchBlockedDates(range.start, range.end, 'warm');
            
            console.log('[Full Year Loader] 📊 Medium-term blocked dates result:');
            console.log('[Full Year Loader] - Count:', blockedDates.length);
            console.log('[Full Year Loader] - Dates:', blockedDates);
            console.log('[Full Year Loader] - Range:', range.start, 'to', range.end);
            
            applyBlockedDates(blockedDates);
            loadedRanges.add(cacheKey);
            
            console.log('[Full Year Loader] ✅ Medium-term dates loaded and applied successfully');
        } catch (error) {
            console.error('[Full Year Loader] ❌ Failed to load medium-term dates:', error);
            console.error('[Full Year Loader] Error details:', error.message, error.stack);
        }
    }
    
    /**
     * Setup on-demand loading for long-term dates
     */
    function setupOnDemandLoading() {
        // Monitor for calendar navigation
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    checkForFutureNavigation();
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // Monitor for month navigation events
        document.addEventListener('calendarNavigation', (event) => {
            if (event.detail && event.detail.monthOffset > 3) {
                loadLongTermDates();
            }
        });
    }
    
    /**
     * Check for future navigation and load data if needed
     */
    function checkForFutureNavigation() {
        // Look for calendar elements showing future months
        const futureElements = document.querySelectorAll('[class*="month"], [class*="year"]');
        futureElements.forEach(element => {
            const text = element.textContent || element.innerText || '';
            if (text.match(/\b(2026|2027|2028)\b/)) {
                loadLongTermDates();
            }
        });
    }
    
    /**
     * Load long-term dates (91-365 days) on demand
     */
    async function loadLongTermDates() {
        const range = getDateRange(CONFIG.COLD_CACHE_DAYS, CONFIG.WARM_CACHE_DAYS);
        const cacheKey = `cold_${range.start}_${range.end}`;
        
        if (loadedRanges.has(cacheKey)) {
            console.log('[Full Year Loader] Long-term dates already loaded for range:', range);
            return;
        }
        
        // Check if already loading
        if (loadingPromises.has(cacheKey)) {
            console.log('[Full Year Loader] Long-term dates already loading for range:', range);
            return loadingPromises.get(cacheKey);
        }
        
        const loadingPromise = (async () => {
            try {
                console.log('[Full Year Loader] 🧊 Loading long-term dates (91-365 days)');
                console.log('[Full Year Loader] Date range:', range);
                console.log('[Full Year Loader] Cache key:', cacheKey);
                
                const blockedDates = await fetchBlockedDates(range.start, range.end, 'cold');
                
                console.log('[Full Year Loader] 📊 Long-term blocked dates result:');
                console.log('[Full Year Loader] - Count:', blockedDates.length);
                console.log('[Full Year Loader] - Dates:', blockedDates);
                console.log('[Full Year Loader] - Range:', range.start, 'to', range.end);
                
                applyBlockedDates(blockedDates);
                loadedRanges.add(cacheKey);
                
                console.log('[Full Year Loader] ✅ Long-term dates loaded and applied successfully');
            } catch (error) {
                console.error('[Full Year Loader] ❌ Failed to load long-term dates:', error);
                console.error('[Full Year Loader] Error details:', error.message, error.stack);
            } finally {
                loadingPromises.delete(cacheKey);
            }
        })();
        
        loadingPromises.set(cacheKey, loadingPromise);
        return loadingPromise;
    }
    
    /**
     * Fetch blocked dates from API
     */
    async function fetchBlockedDates(startDate, endDate, tier = 'auto') {
        const params = getUrlParams();
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
        
        let url;
        if (tier === 'auto' || tier === 'hot') {
            // Use existing endpoint for hot cache
            url = `${CONFIG.API_ENDPOINT}?staff_id=${params.staff_id}&meeting_id=${params.meeting_id}&start_date=${startDate}&end_date=${endDate}&timezone=${encodeURIComponent(timezone)}`;
        } else {
            // Use full year endpoint for warm and cold cache
            url = `${CONFIG.FULL_YEAR_ENDPOINT}?staff_id=${params.staff_id}&meeting_id=${params.meeting_id}&start_date=${startDate}&end_date=${endDate}&timezone=${encodeURIComponent(timezone)}&tier=${tier}`;
        }
        
        console.log('[Full Year Loader] 🌐 Making API request:');
        console.log('[Full Year Loader] - URL:', url);
        console.log('[Full Year Loader] - Tier:', tier);
        console.log('[Full Year Loader] - Date range:', startDate, 'to', endDate);
        console.log('[Full Year Loader] - Params:', params);
        console.log('[Full Year Loader] - Timezone:', timezone);
        
        try {
            const response = await fetch(url);
            console.log('[Full Year Loader] 📡 API Response:');
            console.log('[Full Year Loader] - Status:', response.status);
            console.log('[Full Year Loader] - OK:', response.ok);
            
            const data = await response.json();
            console.log('[Full Year Loader] 📦 API Data:');
            console.log('[Full Year Loader] - Success:', data.success);
            console.log('[Full Year Loader] - Has data:', !!data.data);
            console.log('[Full Year Loader] - Has blocked_dates:', !!(data.data && data.data.blocked_dates));
            console.log('[Full Year Loader] - Full response:', data);
            
            if (data && data.data && data.data.blocked_dates) {
                console.log('[Full Year Loader] ✅ Found blocked dates:', data.data.blocked_dates);
                return data.data.blocked_dates;
            } else {
                console.log('[Full Year Loader] ⚠️ No blocked dates found in response');
                return [];
            }
        } catch (error) {
            console.error('[Full Year Loader] ❌ API request failed:', error);
            console.error('[Full Year Loader] Error details:', error.message, error.stack);
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
        
        // Use existing direct fix functionality if available
        if (window.DirectFixOccupiedSlots && window.DirectFixOccupiedSlots.applyBlockedDates) {
            window.DirectFixOccupiedSlots.applyBlockedDates(blockedDates);
        } else {
            // Fallback: apply styling directly
            applyBlockedDatesDirectly(blockedDates);
        }
    }
    
    /**
     * Apply blocked dates directly (fallback)
     */
    function applyBlockedDatesDirectly(blockedDates) {
        blockedDates.forEach(date => {
            const dateObj = new Date(date);
            const monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"];
            const ariaLabelDate = `${monthNames[dateObj.getMonth()]} ${dateObj.getDate()}, ${dateObj.getFullYear()}`;
            
            // Multiple selector strategies
            const selectors = [
                `[aria-label="${ariaLabelDate}"]`,
                `[aria-label*="${date}"]`,
                `[data-date="${date}"]`,
                `[data-value="${date}"]`,
                `[title*="${date}"]`,
                `[title*="${ariaLabelDate}"]`
            ];
            
            selectors.forEach(selector => {
                const elements = document.querySelectorAll(selector);
                elements.forEach(element => {
                    if (!element.classList.contains('timetics-date-blocked')) {
                        element.classList.add('timetics-date-blocked');
                        element.style.backgroundColor = '#666666';
                        element.style.color = '#ffffff';
                        element.style.cursor = 'not-allowed';
                        element.style.opacity = '0.7';
                        element.style.pointerEvents = 'none';
                        element.setAttribute('disabled', 'true');
                        element.setAttribute('data-blocked', 'true');
                        element.title = 'This date is fully occupied';
                    }
                });
            });
        });
    }
    
    /**
     * Get URL parameters
     */
    function getUrlParams() {
        const params = new URLSearchParams(window.location.search);
        return {
            staff_id: params.get('staff_id') || '71',
            meeting_id: params.get('meeting_id') || '2315'
        };
    }
    
    /**
     * Get date range for specified days
     */
    function getDateRange(days, offset = 0) {
        const startDate = new Date();
        startDate.setDate(startDate.getDate() + offset);
        
        const endDate = new Date();
        endDate.setDate(endDate.getDate() + offset + days);
        
        return {
            start: startDate.toISOString().split('T')[0],
            end: endDate.toISOString().split('T')[0]
        };
    }
    
    /**
     * Initialize when DOM is ready
     */
    function initializeWhenReady() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    }
    
    // Start initialization
    initializeWhenReady();
    
    // Expose for debugging
    window.FullYearLoader = {
        init,
        loadImmediateDates,
        loadMediumTermDates,
        loadLongTermDates,
        getUrlParams,
        getDateRange
    };
    
})();
