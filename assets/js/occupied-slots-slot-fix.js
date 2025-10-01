/**
 * Timetics Occupied Slots - Slot Population Fix
 * 
 * Fixes the issue where the last slot is not being populated to the view
 * by implementing validation, retry logic, and proper timing controls
 */

(function() {
    'use strict';
    
    console.log('[Slot Fix] 🚀 Initializing slot population fix');
    
    // Configuration
    const CONFIG = {
        MAX_RETRY_ATTEMPTS: 3,
        RETRY_DELAY: 1000,
        VALIDATION_DELAY: 500,
        SLOT_SELECTORS: [
            '.tt-slot-item',
            '.tt-booking-slot-wrapper .tt-slot-item',
            '.tt-slot-list-wrap .tt-slot-item',
            '[data-time]'
        ]
    };
    
    // State management
    let isProcessing = false;
    let retryCount = 0;
    let lastSlotCount = 0;
    let validationTimeout = null;
    
    /**
     * Initialize slot population fix
     */
    function init() {
        console.log('[Slot Fix] 🔧 Setting up slot population monitoring');
        
        // Monitor for slot list changes
        monitorSlotListChanges();
        
        // Add retry mechanism for incomplete slot populations
        setupRetryMechanism();
        
        // Override the original slot rendering to add validation
        overrideSlotRendering();
        
        console.log('[Slot Fix] ✅ Slot population fix initialized');
    }
    
    /**
     * Monitor for slot list changes and validate completeness
     */
    function monitorSlotListChanges() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    const addedNodes = Array.from(mutation.addedNodes);
                    const removedNodes = Array.from(mutation.removedNodes);
                    
                    // Check if slot-related elements were added/removed
                    const hasSlotChanges = addedNodes.some(node => 
                        node.nodeType === Node.ELEMENT_NODE && 
                        (node.classList?.contains('tt-slot-item') || 
                         node.querySelector?.('.tt-slot-item'))
                    ) || removedNodes.some(node => 
                        node.nodeType === Node.ELEMENT_NODE && 
                        (node.classList?.contains('tt-slot-item') || 
                         node.querySelector?.('.tt-slot-item'))
                    );
                    
                    if (hasSlotChanges) {
                        console.log('[Slot Fix] 📊 Slot list changed, validating...');
                        validateSlotPopulation();
                    }
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    /**
     * Setup retry mechanism for incomplete slot populations
     */
    function setupRetryMechanism() {
        // Check for incomplete slot populations every 2 seconds
        setInterval(() => {
            if (!isProcessing) {
                validateSlotPopulation();
            }
        }, 2000);
    }
    
    /**
     * Override the original slot rendering to add validation
     */
    function overrideSlotRendering() {
        // Store original innerHTML setter
        const originalInnerHTML = Object.getOwnPropertyDescriptor(Element.prototype, 'innerHTML');
        
        // Override innerHTML setter for slot containers
        Object.defineProperty(Element.prototype, 'innerHTML', {
            set: function(value) {
                // Check if this is a slot container
                if (this.classList?.contains('tt-slot-list-wrap') || 
                    this.classList?.contains('tt-booking-slot-wrapper') ||
                    this.querySelector?.('.tt-slot-item')) {
                    
                    console.log('[Slot Fix] 🎯 Slot container innerHTML being set');
                    console.log('[Slot Fix] Content length:', value.length);
                    
                    // Call original setter
                    originalInnerHTML.set.call(this, value);
                    
                    // Validate after a short delay
                    setTimeout(() => {
                        validateSlotPopulation();
                    }, CONFIG.VALIDATION_DELAY);
                    
                } else {
                    // Call original setter for non-slot elements
                    originalInnerHTML.set.call(this, value);
                }
            },
            get: originalInnerHTML.get,
            configurable: true
        });
    }
    
    /**
     * Validate slot population and fix if incomplete
     */
    function validateSlotPopulation() {
        if (isProcessing) {
            console.log('[Slot Fix] ⏳ Already processing, skipping validation');
            return;
        }
        
        isProcessing = true;
        
        // Clear existing validation timeout
        if (validationTimeout) {
            clearTimeout(validationTimeout);
        }
        
        // Get current slot count
        const currentSlotCount = getCurrentSlotCount();
        console.log('[Slot Fix] 📊 Current slot count:', currentSlotCount);
        console.log('[Slot Fix] 📊 Last known slot count:', lastSlotCount);
        
        // Check if slots are missing or incomplete
        if (currentSlotCount === 0 && lastSlotCount > 0) {
            console.log('[Slot Fix] ⚠️ Slots disappeared, attempting to restore...');
            attemptSlotRestoration();
        } else if (currentSlotCount > 0 && currentSlotCount < lastSlotCount) {
            console.log('[Slot Fix] ⚠️ Slot count decreased, checking for missing slots...');
            checkForMissingSlots();
        } else if (currentSlotCount > lastSlotCount) {
            console.log('[Slot Fix] ✅ Slot count increased, updating last known count');
            lastSlotCount = currentSlotCount;
        }
        
        // Update last known count
        if (currentSlotCount > 0) {
            lastSlotCount = currentSlotCount;
        }
        
        isProcessing = false;
    }
    
    /**
     * Get current slot count from DOM
     */
    function getCurrentSlotCount() {
        let totalSlots = 0;
        
        CONFIG.SLOT_SELECTORS.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            totalSlots += elements.length;
        });
        
        return totalSlots;
    }
    
    /**
     * Attempt to restore missing slots
     */
    function attemptSlotRestoration() {
        if (retryCount >= CONFIG.MAX_RETRY_ATTEMPTS) {
            console.log('[Slot Fix] ❌ Max retry attempts reached, giving up');
            retryCount = 0;
            return;
        }
        
        retryCount++;
        console.log('[Slot Fix] 🔄 Attempting slot restoration (attempt', retryCount, ')');
        
        // Try to trigger a re-render by dispatching events
        dispatchSlotRefreshEvents();
        
        // Retry after delay
        setTimeout(() => {
            validateSlotPopulation();
        }, CONFIG.RETRY_DELAY);
    }
    
    /**
     * Check for missing slots and attempt to restore them
     */
    function checkForMissingSlots() {
        console.log('[Slot Fix] 🔍 Checking for missing slots...');
        
        // Look for slot containers that might be incomplete
        const slotContainers = document.querySelectorAll('.tt-slot-list-wrap, .tt-booking-slot-wrapper');
        
        slotContainers.forEach(container => {
            const slots = container.querySelectorAll('.tt-slot-item');
            console.log('[Slot Fix] Container has', slots.length, 'slots');
            
            // If container exists but has no slots, try to trigger a refresh
            if (slots.length === 0) {
                console.log('[Slot Fix] 🔄 Empty slot container found, triggering refresh...');
                triggerSlotContainerRefresh(container);
            }
        });
    }
    
    /**
     * Dispatch events to trigger slot refresh
     */
    function dispatchSlotRefreshEvents() {
        // Dispatch custom events that might trigger slot re-rendering
        const events = [
            'timetics-slot-refresh',
            'timetics-date-changed',
            'timetics-slot-update',
            'react-slot-refresh'
        ];
        
        events.forEach(eventName => {
            const event = new CustomEvent(eventName, {
                detail: { source: 'slot-fix' },
                bubbles: true
            });
            document.dispatchEvent(event);
        });
        
        // Also try to trigger via window object if available
        if (window.timetics && typeof window.timetics.refreshSlots === 'function') {
            try {
                window.timetics.refreshSlots();
            } catch (e) {
                console.log('[Slot Fix] ⚠️ Failed to call timetics.refreshSlots:', e.message);
            }
        }
    }
    
    /**
     * Trigger refresh for a specific slot container
     */
    function triggerSlotContainerRefresh(container) {
        // Try multiple methods to trigger refresh
        const methods = [
            () => {
                // Method 1: Dispatch click event on container
                const clickEvent = new MouseEvent('click', { bubbles: true });
                container.dispatchEvent(clickEvent);
            },
            () => {
                // Method 2: Trigger change event
                const changeEvent = new Event('change', { bubbles: true });
                container.dispatchEvent(changeEvent);
            },
            () => {
                // Method 3: Force re-render by temporarily hiding and showing
                container.style.display = 'none';
                setTimeout(() => {
                    container.style.display = '';
                }, 10);
            }
        ];
        
        methods.forEach((method, index) => {
            try {
                console.log('[Slot Fix] 🔄 Trying refresh method', index + 1);
                method();
            } catch (e) {
                console.log('[Slot Fix] ⚠️ Refresh method', index + 1, 'failed:', e.message);
            }
        });
    }
    
    /**
     * Enhanced slot rendering with validation
     */
    function renderSlotsWithValidation(slots, container) {
        console.log('[Slot Fix] 🎨 Rendering', slots.length, 'slots with validation');
        
        // Create slot HTML with validation attributes
        const slotHTML = slots.map((slot, index) => {
            return `
                <div class="tt-slot-item" 
                     data-time="${slot.time}" 
                     data-slot-index="${index}"
                     data-total-slots="${slots.length}"
                     data-rendered-by="slot-fix">
                    ${slot.time}
                </div>
            `;
        }).join('');
        
        // Set innerHTML
        container.innerHTML = slotHTML;
        
        // Validate rendering after a short delay
        setTimeout(() => {
            const renderedSlots = container.querySelectorAll('.tt-slot-item');
            console.log('[Slot Fix] ✅ Rendered', renderedSlots.length, 'slots, expected', slots.length);
            
            if (renderedSlots.length !== slots.length) {
                console.log('[Slot Fix] ⚠️ Slot count mismatch, attempting to fix...');
                attemptSlotRestoration();
            }
        }, 100);
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
    window.SlotFix = {
        validateSlotPopulation,
        getCurrentSlotCount,
        renderSlotsWithValidation,
        retryCount: () => retryCount,
        lastSlotCount: () => lastSlotCount
    };
    
    console.log('[Slot Fix] 🎉 Slot population fix loaded');
    
})();
