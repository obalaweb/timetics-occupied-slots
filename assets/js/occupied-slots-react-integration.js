/**
 * Timetics Occupied Slots - React Integration
 * 
 * This script integrates with Timetics React components to show blocked dates
 * in the calendar interface. It works by:
 * 1. Intercepting API responses to get blocked dates
 * 2. Modifying React component state to show blocked dates
 * 3. Adding visual indicators to the calendar
 */

(function() {
    'use strict';

    // Configuration - use localized config if available, otherwise fallback to hardcoded
    const CONFIG = window.timeticsOccupiedSlotsConfig || {
        apiEndpoint: '/wp-json/timetics-occupied-slots/v1/bookings/entries',
        blockedDateClass: 'timetics-date-blocked',
        blockedDateIcon: '🚫',
        debug: false // Disabled for production
    };

    // Debug logging (disabled for production)
    function log(message, data = null) {
        // console.log(`[Timetics Occupied Slots] ${message}`, data);
    }

    // Main React Integration Class
    class TimeticsOccupiedSlotsReactIntegration {
        constructor() {
            console.log('🏗️ CONSTRUCTING TIMETICS OCCUPIED SLOTS INTEGRATION...');
            this.blockedDates = [];
            this.timeticsAvailabilityData = null;
            this.originalFetch = null;
            this.originalXHR = null;
            this.isInitialized = false;
            
            console.log('🔄 Starting initialization...');
            this.init();
        }

        async         init() {
            console.log('🚀 INITIALIZING REACT INTEGRATION...');
            log('Initializing React integration...');
            
            // Inject CSS styles
            this.injectBlockedDateStyles();
            console.log('✅ CSS styles injected');
            
            // Wait for React to be ready
            this.waitForReact();
            console.log('✅ React waiting configured');
            
            // Intercept API calls
            this.interceptAPICalls();
            console.log('✅ API interception configured');
            
            // Monitor DOM changes
            this.monitorDOMChanges();
            console.log('✅ DOM monitoring configured');
            
            // Hook into Flatpickr lifecycle
            this.hookIntoFlatpickr();
            console.log('✅ Flatpickr hooks configured');
            
            // Load blocked dates from API
            console.log('🔄 About to load blocked dates...');
            await this.loadBlockedDates();
            console.log('✅ Blocked dates loading completed');
            
            this.isInitialized = true;
            console.log('🎉 React integration fully initialized');
            log('React integration initialized');
            
            // Expose methods globally for debugging
            window.TimeticsOccupiedSlots = {
                applyBlockedDateStyling: () => this.applyBlockedDateStyling(),
                loadBlockedDates: () => this.loadBlockedDates(),
                getBlockedDates: () => this.blockedDates || window.timeticsBlockedDates || [],
                forceStyleAllDates: () => this.forceStyleAllDates(),
                setBlockedDates: (dates) => {
                    this.blockedDates = dates;
                    window.timeticsBlockedDates = dates;
                    log('Manually set blocked dates:', dates);
                    this.applyBlockedDateStyling();
                },
                testBlockedDates: () => {
                    const testDates = [];
                    this.setBlockedDates(testDates);
                },
                testWithToday: () => {
                    const today = new Date().toISOString().split('T')[0];
                    const tomorrow = new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    const testDates = [today, tomorrow];
                    log('Testing with today and tomorrow:', testDates);
                    this.setBlockedDates(testDates);
                },
                debugAllElements: () => {
                    const allElements = document.querySelectorAll('*');
                    log(`Total elements on page: ${allElements.length}`);
                    
                    // Look for any elements with date-like content
                    let dateElements = [];
                    allElements.forEach(el => {
                        const text = el.textContent || el.innerText || '';
                        const ariaLabel = el.getAttribute('aria-label') || '';
                        if (false ||
                            ariaLabel.includes('September 30, 2025') || ariaLabel.includes('October 1, 2025') || ariaLabel.includes('October 2, 2025')) {
                            dateElements.push({
                                element: el,
                                text: text.substring(0, 100),
                                ariaLabel: ariaLabel,
                                tagName: el.tagName,
                                className: el.className
                            });
                        }
                    });
                    log('Elements containing blocked dates:', dateElements);
                    return dateElements;
                },
                testFlatpickrBlocking: () => {
                    // Test Flatpickr native blocking
                    const testDates = [];
                    log('Testing Flatpickr native blocking with dates:', testDates);
                    
                    const result = this.configureFlatpickrBlocking(testDates);
                    log(`Configured ${result} Flatpickr instances with native blocking`);
                    return result;
                },
                configureFlatpickrNow: () => {
                    const blockedDates = this.blockedDates || window.timeticsBlockedDates || [];
                    if (blockedDates.length > 0) {
                        return this.configureFlatpickrBlocking(blockedDates);
                    } else {
                        log('No blocked dates available for Flatpickr configuration');
                        return 0;
                    }
                },
                rehookFlatpickr: () => {
                    log('Re-hooking into Flatpickr instances...');
                    this.hookIntoExistingFlatpickrInstances();
                    return 'Flatpickr re-hooked';
                },
                debugFlatpickrInstances: () => {
                    const flatpickrInputs = document.querySelectorAll('.flatpickr-input');
                    const instances = [];
                    
                    flatpickrInputs.forEach((input, index) => {
                        if (input._flatpickr) {
                            instances.push({
                                index: index,
                                element: input,
                                instance: input._flatpickr,
                                config: input._flatpickr.config
                            });
                        }
                    });
                    
                    log('Found Flatpickr instances:', instances);
                    return instances;
                },
                debugNextAvailable: () => {
                    log('Debugging next available date calculation...');
                    const nextAvailable = this.getNextAvailableDate();
                    log('Next available date result:', nextAvailable);
                    
                    const blockedDates = this.blockedDates || window.timeticsBlockedDates || [];
                    log('Current blocked dates:', blockedDates);
                    
                    return {
                        nextAvailable: nextAvailable,
                        blockedDates: blockedDates,
                        isDateBlocked: (date) => this.isDateBlocked(date)
                    };
                },
                refreshNextAvailable: () => {
                    return this.refreshNextAvailableDate();
                },
                testDateBlocking: (dateString) => {
                    log(`Testing if date ${dateString} is blocked...`);
                    const isBlocked = this.isDateBlocked(dateString);
                    log(`Date ${dateString} is blocked: ${isBlocked}`);
                    return isBlocked;
                },
                testAllBlockedDates: () => {
                    const blockedDates = this.blockedDates || window.timeticsBlockedDates || [];
                    log('Testing all blocked dates:');
                    blockedDates.forEach(date => {
                        const isBlocked = this.isDateBlocked(date);
                        log(`  ${date}: ${isBlocked ? 'BLOCKED' : 'NOT BLOCKED'}`);
                    });
                    return blockedDates.map(date => ({
                        date: date,
                        isBlocked: this.isDateBlocked(date)
                    }));
                }
            };
        }

        forceStyleAllDates() {
            log('Force styling all dates - aggressive approach');
            const blockedDates = this.blockedDates || window.timeticsBlockedDates || [];
            
            log('Current blocked dates:', blockedDates);
            log('this.blockedDates:', this.blockedDates);
            log('window.timeticsBlockedDates:', window.timeticsBlockedDates);
            
            if (blockedDates.length === 0) {
                log('No blocked dates to force style - trying to reload...');
                this.loadBlockedDates();
                return;
            }
            
            // Get all elements on the page
            const allElements = document.querySelectorAll('*');
            let styledCount = 0;
            
            log(`Checking ${allElements.length} elements for blocked dates:`, blockedDates);
            
            allElements.forEach(element => {
                const text = element.textContent || element.innerText || '';
                const date = element.getAttribute('data-date') || 
                           element.getAttribute('data-value') || 
                           element.getAttribute('title') || '';
                
                // Check if this element contains any blocked date
                blockedDates.forEach(blockedDate => {
                    if (text.includes(blockedDate) || date.includes(blockedDate)) {
                        log(`Found blocked date ${blockedDate} in element:`, element);
                        this.markElementAsBlocked(element);
                        styledCount++;
                    }
                });
            });
            
            log(`Force styled ${styledCount} elements`);
            return styledCount;
        }

        hookIntoFlatpickr() {
            log('Hooking into Flatpickr lifecycle...');
            
            // Method 1: Hook into Flatpickr events if available
            if (window.flatpickr) {
                log('Flatpickr library detected, hooking into events');
                
                // Override Flatpickr's onReady callback
                const originalFlatpickr = window.flatpickr;
                const self = this; // Store reference to this
                window.flatpickr = function(element, options) {
                    const defaultOptions = {
                        onReady: function(selectedDates, dateStr, instance) {
                            log('Flatpickr onReady triggered');
                            // Apply blocked dates after calendar is ready
                            setTimeout(() => {
                                self.configureFlatpickrBlocking(self.blockedDates || window.timeticsBlockedDates || []);
                            }, 100);
                            
                            // Call original onReady if it exists
                            if (options && options.onReady) {
                                options.onReady(selectedDates, dateStr, instance);
                            }
                        }
                    };
                    
                    const mergedOptions = Object.assign({}, defaultOptions, options);
                    return originalFlatpickr(element, mergedOptions);
                };
            }
            
            // Method 2: Listen for Flatpickr calendar creation events
            const self = this; // Store reference to this
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'childList') {
                        mutation.addedNodes.forEach((node) => {
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                // Check if Flatpickr calendar was added
                                if (node.classList && node.classList.contains('flatpickr-calendar')) {
                                    log('Flatpickr calendar detected in DOM');
                                    setTimeout(() => {
                                        self.configureFlatpickrBlocking(self.blockedDates || window.timeticsBlockedDates || []);
                                    }, 100);
                                }
                                
                                // Also check for nested Flatpickr elements
                                const flatpickrCalendars = node.querySelectorAll('.flatpickr-calendar');
                                if (flatpickrCalendars.length > 0) {
                                    log('Nested Flatpickr calendar detected');
                                    setTimeout(() => {
                                        self.configureFlatpickrBlocking(self.blockedDates || window.timeticsBlockedDates || []);
                                    }, 100);
                                }
                            }
                        });
                    }
                });
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
            
            // Method 3: Listen for window events that might trigger calendar updates
            window.addEventListener('click', (e) => {
                if (e.target.classList && e.target.classList.contains('flatpickr-input')) {
                    log('Flatpickr input clicked, calendar should open soon');
                    setTimeout(() => {
                        self.configureFlatpickrBlocking(self.blockedDates || window.timeticsBlockedDates || []);
                    }, 500);
                }
            });
            
            // Method 4: Listen for month changes in Flatpickr
            document.addEventListener('click', (e) => {
                if (e.target.classList && (e.target.classList.contains('flatpickr-prev-month') || e.target.classList.contains('flatpickr-next-month'))) {
                    log('Flatpickr month navigation clicked');
                    setTimeout(() => {
                        self.configureFlatpickrBlocking(self.blockedDates || window.timeticsBlockedDates || []);
                    }, 300);
                }
            });
            
            // Method 5: Try to find and hook into existing Flatpickr instances
            this.hookIntoExistingFlatpickrInstances();
            
            log('Flatpickr lifecycle hooks installed');
        }

        hookIntoExistingFlatpickrInstances() {
            // Try to find existing Flatpickr instances and hook into them
            const flatpickrInputs = document.querySelectorAll('.flatpickr-input');
            log(`Found ${flatpickrInputs.length} Flatpickr inputs`);
            
            flatpickrInputs.forEach((input, index) => {
                // Try to get the Flatpickr instance from the input
                if (input._flatpickr) {
                    log(`Found Flatpickr instance on input ${index}`);
                    const instance = input._flatpickr;
                    
                    // Hook into the instance's onReady event
                    const originalOnReady = instance.config.onReady;
                    instance.config.onReady = function(selectedDates, dateStr, instance) {
                        log('Flatpickr instance onReady triggered');
                        setTimeout(() => {
                            this.configureFlatpickrBlocking(this.blockedDates || window.timeticsBlockedDates || []);
                        }, 100);
                        
                        if (originalOnReady) {
                            originalOnReady(selectedDates, dateStr, instance);
                        }
                    }.bind(this);
                }
            });
            
            // Also try to hook into any global Flatpickr instances
            if (window.flatpickr && window.flatpickr.l10n) {
                log('Global Flatpickr detected, attempting to hook into existing instances');
                
                // Override the global flatpickr function to catch new instances
                const originalFlatpickr = window.flatpickr;
                const self = this;
                
                window.flatpickr = function(element, options) {
                    const instance = originalFlatpickr(element, options);
                    
                    // Hook into this instance
                    if (instance && instance.config) {
                        const originalOnReady = instance.config.onReady;
                        instance.config.onReady = function(selectedDates, dateStr, instance) {
                            log('New Flatpickr instance onReady triggered');
                            setTimeout(() => {
                                self.configureFlatpickrBlocking(self.blockedDates || window.timeticsBlockedDates || []);
                            }, 100);
                            
                            if (originalOnReady) {
                                originalOnReady(selectedDates, dateStr, instance);
                            }
                        };
                    }
                    
                    return instance;
                };
            }
        }

        injectBlockedDateStyles() {
            // No custom styles needed - using Flatpickr's native disabled styling
            log('Using Flatpickr native disabled styling - no custom CSS needed');
        }

        waitForReact() {
            // Wait for React to be available
            const checkReact = () => {
                if (window.React && window.ReactDOM) {
                    log('React detected');
                    this.setupReactIntegration();
                } else {
                    setTimeout(checkReact, 100);
                }
            };
            checkReact();
        }

        setupReactIntegration() {
            // Hook into React's state updates
            this.hookIntoReactState();
            
            // Monitor for Timetics components
            this.monitorTimeticsComponents();
            
            // Monitor for "No slot available" state
            this.monitorNoSlotState();
        }

        interceptAPICalls() {
            // Intercept fetch requests
            this.originalFetch = window.fetch;
            window.fetch = (url, options) => {
                return this.originalFetch(url, options).then(response => {
                    // Intercept Timetics API calls
                    if (url.includes('/wp-json/timetics/v1/') || 
                        url.includes('timetics') || 
                        url.includes('bookings') ||
                        url.includes('entries')) {
                        return this.handleAPIResponse(response);
                    }
                    return response;
                });
            };

            // Intercept XMLHttpRequest
            this.originalXHR = window.XMLHttpRequest;
            const self = this;
            window.XMLHttpRequest = function() {
                const xhr = new self.originalXHR();
                const originalOpen = xhr.open;
                const originalSend = xhr.send;
                
                xhr.open = function(method, url, ...args) {
                    this._url = url;
                    return originalOpen.apply(this, [method, url, ...args]);
                };
                
                xhr.send = function(data) {
                    if (this._url && this._url.includes(CONFIG.apiEndpoint)) {
                        this.addEventListener('load', function() {
                            self.handleXHRResponse(this);
                        });
                    }
                    return originalSend.apply(this, arguments);
                };
                
                return xhr;
            };

            log('API interception setup complete');
        }

        async handleAPIResponse(response) {
            try {
                const data = await response.clone().json();
                
                if (data && data.data) {
                    // Store blocked dates
                    if (data.data.blocked_dates) {
                        this.blockedDates = data.data.blocked_dates;
                        log('Blocked dates received:', this.blockedDates);
                    }
                    
                    // Store availability data for next available date calculation
                    if (data.data.days) {
                        this.timeticsAvailabilityData = data.data;
                        log('Timetics availability data received:', data.data);
                        
                        // Store in window object for easy access
                        window.timeticsAvailabilityData = data.data;
                    }
                    
                    // Modify the response to include blocked dates for initial render
                    const modifiedData = this.attachBlockedDatesToResponse(data);
                    
                    // Create a new response with modified data
                    const modifiedResponse = new Response(JSON.stringify(modifiedData), {
                        status: response.status,
                        statusText: response.statusText,
                        headers: response.headers
                    });
                    
                    // Trigger React update
                    this.updateReactComponents();
                    
                    return modifiedResponse;
                }
            } catch (error) {
                log('Error handling API response:', error);
            }
            
            return response;
        }

        attachBlockedDatesToResponse(data) {
            // Ensure blocked_dates array exists in the response
            if (!data.data.blocked_dates) {
                data.data.blocked_dates = [];
            }
            
            // Add our blocked dates to the response
            if (this.blockedDates && this.blockedDates.length > 0) {
                // Merge with existing blocked dates (avoid duplicates)
                const existingBlockedDates = data.data.blocked_dates || [];
                const allBlockedDates = [...new Set([...existingBlockedDates, ...this.blockedDates])];
                data.data.blocked_dates = allBlockedDates;
                
                log('Attached blocked dates to API response:', allBlockedDates);
            }
            
            // Also add blocked dates to individual days if they exist
            if (data.data.days && Array.isArray(data.data.days)) {
                data.data.days.forEach(day => {
                    if (this.blockedDates.includes(day.date)) {
                        day.status = 'unavailable';
                        day.slots = [];
                        day.reason = 'blocked_date';
                        day.blocked = true;
                        
                        log('Marked day as blocked:', day.date);
                    }
                });
            }
            
            return data;
        }

        handleXHRResponse(xhr) {
            try {
                const data = JSON.parse(xhr.responseText);
                
                if (data && data.data) {
                    // Store blocked dates
                    if (data.data.blocked_dates) {
                        this.blockedDates = data.data.blocked_dates;
                        log('Blocked dates received via XHR:', this.blockedDates);
                    }
                    
                    // Store availability data
                    if (data.data.days) {
                        this.timeticsAvailabilityData = data.data;
                        window.timeticsAvailabilityData = data.data;
                    }
                    
                    // Modify the response data to include blocked dates
                    const modifiedData = this.attachBlockedDatesToResponse(data);
                    
                    // Update the XHR response text with modified data
                    Object.defineProperty(xhr, 'responseText', {
                        writable: true,
                        value: JSON.stringify(modifiedData)
                    });
                    
                    // Trigger React update
                    this.updateReactComponents();
                }
            } catch (error) {
                log('Error handling XHR response:', error);
            }
        }

        hookIntoReactState() {
            // Hook into React's state management
            const originalSetState = React.Component.prototype.setState;
            const self = this;
            
            React.Component.prototype.setState = function(partialState, callback) {
                const result = originalSetState.call(this, partialState, callback);
                
                // Check if this is a Timetics component
                if (this.constructor.name.includes('Timetics') || 
                    this.constructor.name.includes('Calendar') ||
                    this.constructor.name.includes('Booking')) {
                    setTimeout(() => self.updateReactComponents(), 100);
                }
                
                return result;
            };
            
            log('React state hook installed');
        }

        monitorTimeticsComponents() {
            // Monitor for Timetics-specific components
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'childList') {
                        mutation.addedNodes.forEach((node) => {
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                this.processNewNode(node);
                            }
                        });
                    }
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });

            // Also apply styling periodically to catch any missed elements
            setInterval(() => {
                this.applyBlockedDateStyling();
            }, 2000);
            
            // Also apply Flatpickr native blocking more frequently
            setInterval(() => {
                const blockedDates = this.blockedDates || window.timeticsBlockedDates || [];
                if (blockedDates.length > 0) {
                    this.configureFlatpickrBlocking(blockedDates);
                }
            }, 1000);

            log('DOM monitoring started');
        }

        monitorNoSlotState() {
            // Monitor for "No slot available" state using valid selectors
            const noSlotElements = document.querySelectorAll(
                '[class*="no-slot"], ' +
                '[class*="unavailable"]'
            );
            
            if (noSlotElements.length > 0) {
                log('No slot available state detected');
                this.handleNoSlotState();
            }
            
            // Check for the specific Timetics "No slot available" text
            const noSlotText = document.querySelector('p[style*="text-align: center"]');
            if (noSlotText && noSlotText.textContent.includes('No slot available')) {
                log('Timetics no slot state detected');
                this.handleNoSlotState();
            }
            
            // Also check for any paragraph containing "No slot available"
            const allParagraphs = document.querySelectorAll('p');
            allParagraphs.forEach(p => {
                if (p.textContent.includes('No slot available')) {
                    log('No slot available text found in paragraph');
                    this.handleNoSlotState();
                }
            });
        }

        handleNoSlotState() {
            // Find the slot wrapper container
            const slotWrapper = document.querySelector('.tt-booking-slot-wrapper, .tt-slot-list-wrap');
            if (!slotWrapper) {
                log('Slot wrapper not found');
                return;
            }

            // Check if we already added the next available button
            if (slotWrapper.querySelector('.timetics-next-available-btn')) {
                return;
            }

            // Get the next available date
            const nextAvailableDate = this.getNextAvailableDate();
            if (!nextAvailableDate) {
                log('No next available date found');
                return;
            }

            // Create the next available button
            const nextAvailableBtn = this.createNextAvailableButton(nextAvailableDate);
            
            // Insert the button into the slot wrapper
            const noSlotContainer = slotWrapper.querySelector('div[style*="height: 100%"]');
            if (noSlotContainer) {
                noSlotContainer.appendChild(nextAvailableBtn);
                log('Next available button added');
            }
        }

        getNextAvailableDate() {
            log('Getting next available date...');
            
            // Get current selected date
            const selectedDateElement = document.querySelector('.tt-selected-date');
            if (!selectedDateElement) {
                log('No selected date element found');
                return null;
            }

            const selectedDateText = selectedDateElement.textContent;
            log('Selected date text:', selectedDateText);
            
            const selectedDate = this.parseDateFromText(selectedDateText);
            if (!selectedDate) {
                log('Could not parse selected date');
                return null;
            }
            
            log('Parsed selected date:', selectedDate);

            // First, try to get availability data from Timetics API
            const timeticsData = this.getTimeticsAvailabilityData();
            if (timeticsData && timeticsData.days) {
                log('Using Timetics API data for next available date');
                // Find the first day with available slots that is NOT blocked
                for (const day of timeticsData.days) {
                    log(`Checking day: ${day.date}, status: ${day.status}, slots: ${day.slots?.length || 0}`);
                    if (day.status === 'available' && day.slots && day.slots.length > 0) {
                        // Check if this date is blocked
                        const isBlocked = this.isDateBlocked(day.date);
                        log(`Date ${day.date} - available: true, blocked: ${isBlocked}`);
                        if (!isBlocked) {
                            const date = new Date(day.date);
                            log(`Found next available date: ${day.date} (not blocked)`);
                            return {
                                date: date,
                                dateString: day.date,
                                formatted: this.formatDateForDisplay(date)
                            };
                        } else {
                            log(`Date ${day.date} is available but blocked, skipping`);
                        }
                    }
                }
            }

            // Fallback: Find next available date (not in blocked dates)
            log('Using fallback method to find next available date');
            let nextDate = new Date(selectedDate);
            nextDate.setDate(nextDate.getDate() + 1);

            // Look for next available date within 30 days
            for (let i = 0; i < 30; i++) {
                const dateString = nextDate.toISOString().split('T')[0];
                const isBlocked = this.isDateBlocked(dateString);
                log(`Checking fallback date ${dateString}: blocked = ${isBlocked}`);
                if (!isBlocked) {
                    log(`Found next available date via fallback: ${dateString}`);
                    return {
                        date: new Date(nextDate),
                        dateString: dateString,
                        formatted: this.formatDateForDisplay(nextDate)
                    };
                }
                nextDate.setDate(nextDate.getDate() + 1);
            }

            log('No next available date found');
            return null;
        }

        getTimeticsAvailabilityData() {
            // First, try to get from stored data
            if (this.timeticsAvailabilityData) {
                return this.timeticsAvailabilityData;
            }

            // Try to get from window object
            if (window.timeticsAvailabilityData) {
                return window.timeticsAvailabilityData;
            }

            // Try to get from window.timetics object
            if (window.timetics && window.timetics.availabilityData) {
                return window.timetics.availabilityData;
            }

            // Try to get from any script tags on the page
            const scripts = document.querySelectorAll('script');
            for (const script of scripts) {
                if (script.textContent && script.textContent.includes('"days"')) {
                    try {
                        // Look for the full API response structure
                        const match = script.textContent.match(/\{[^}]*"days"[^}]*\}/);
                        if (match) {
                            const data = JSON.parse(match[0]);
                            if (data.days) {
                                return data;
                            }
                        }
                    } catch (e) {
                        // Continue searching
                    }
                }
            }

            return null;
        }

        parseDateFromText(dateText) {
            // Parse date from "Tuesday - September 30, 2025" format
            const match = dateText.match(/(\w+)\s+(\d+),\s+(\d{4})/);
            if (match) {
                const month = match[1];
                const day = match[2];
                const year = match[3];
                
                const monthMap = {
                    'January': 0, 'February': 1, 'March': 2, 'April': 3,
                    'May': 4, 'June': 5, 'July': 6, 'August': 7,
                    'September': 8, 'October': 9, 'November': 10, 'December': 11
                };
                
                const monthIndex = monthMap[month];
                if (monthIndex !== undefined) {
                    return new Date(year, monthIndex, day);
                }
            }
            return null;
        }

        formatDateForDisplay(date) {
            return date.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        async loadBlockedDates() {
            try {
                // Get current URL parameters
                const urlParams = new URLSearchParams(window.location.search);
                const staffId = urlParams.get('staff_id') || '1';
                const meetingId = urlParams.get('meeting_id') || '1';
                
                const startDate = new Date().toISOString().split('T')[0];
                const endDate = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                
                console.log('🚀 Starting API call for blocked dates...');
                log(`Loading blocked dates for staff ${staffId}, meeting ${meetingId}, dates ${startDate} to ${endDate}`);
                
                // Get timezone dynamically
                const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
                
                const apiUrl = `${CONFIG.apiEndpoint}?staff_id=${staffId}&meeting_id=${meetingId}&start_date=${startDate}&end_date=${endDate}&timezone=${encodeURIComponent(timezone)}`;
                console.log('🌐 API URL:', apiUrl);
                console.log('🔧 Using endpoint from CONFIG:', CONFIG.apiEndpoint);
                
                const response = await fetch(apiUrl);
                console.log('📡 API Response status:', response.status);
                
                const data = await response.json();
                console.log('📦 API Response data:', data);
                log('API Response:', data);
                
                if (data && data.data && data.data.blocked_dates) {
                    this.blockedDates = data.data.blocked_dates;
                    log('Loaded blocked dates:', this.blockedDates);
                    
                    // Store blocked dates globally for other components
                    window.timeticsBlockedDates = this.blockedDates;
                    
                    // Apply styling immediately
                    this.applyBlockedDateStyling();
                    
                    // Also apply styling after a short delay to catch any late-loading elements
                    setTimeout(() => {
                        this.applyBlockedDateStyling();
                    }, 500);
                    
                    // Force refresh every 3 seconds to catch dynamic content
                    setTimeout(() => {
                        this.applyBlockedDateStyling();
                    }, 3000);
                    
                    // Also force Flatpickr native blocking immediately
                    setTimeout(() => {
                        this.configureFlatpickrBlocking(this.blockedDates);
                    }, 100);
                    
                    // And again after a longer delay
                    setTimeout(() => {
                        this.configureFlatpickrBlocking(this.blockedDates);
                    }, 2000);
                    
                    // Refresh next available date calculation
                    setTimeout(() => {
                        this.refreshNextAvailableDate();
                    }, 500);
                } else {
                    console.log('❌ No blocked dates found in API response');
                    console.log('📋 Full API response structure:', {
                        success: data?.success,
                        data: data?.data,
                        hasBlockedDates: data?.data?.blocked_dates,
                        blockedDatesValue: data?.data?.blocked_dates
                    });
                    log('No blocked dates found in API response');
                    log('Full API response:', data);
                    
                    // Try to get blocked dates from the days data
                    if (data && data.data && data.data.days) {
                        console.log('🔍 Checking days data for blocked dates...');
                        const blockedDays = data.data.days.filter(day => day.status === 'unavailable' && day.reason === 'no_available_slots');
                        console.log('📅 Found blocked days:', blockedDays);
                        if (blockedDays.length > 0) {
                            const blockedDates = blockedDays.map(day => day.date);
                            console.log('✅ Extracted blocked dates from days data:', blockedDates);
                            log('Found blocked dates from days data:', blockedDates);
                            this.blockedDates = blockedDates;
                            window.timeticsBlockedDates = blockedDates;
                            this.applyBlockedDateStyling();
                        } else {
                            console.log('❌ No blocked days found in days data');
                        }
                    } else {
                        console.log('❌ No days data found in API response');
                    }
                }
            } catch (error) {
                log('Error loading blocked dates:', error);
            }
        }

        applyBlockedDateStyling() {
            // Get blocked dates from the API response or global variable
            const blockedDates = this.blockedDates || window.timeticsBlockedDates || [];
            
            console.log('=== BLOCKED DATES DEBUG ===');
            console.log('Current blocked dates:', blockedDates);
            console.log('this.blockedDates:', this.blockedDates);
            console.log('window.timeticsBlockedDates:', window.timeticsBlockedDates);
            
            if (blockedDates.length === 0) {
                log('No blocked dates to apply');
                console.log('❌ No blocked dates found - dates will not be disabled');
                return;
            }

            log('Applying blocked date styling for dates:', blockedDates);
            console.log('✅ Found blocked dates, applying styling...');
            
            // First, configure Flatpickr native blocking
            this.configureFlatpickrBlocking(blockedDates);
            
            // Debug: Log all calendar-related elements
            const calendarElements = document.querySelectorAll('[class*="calendar"], [class*="date"], [class*="day"], [class*="picker"]');
            log('Found calendar elements:', calendarElements.length);
            calendarElements.forEach((el, index) => {
                if (index < 5) { // Log first 5 elements
                    log(`Calendar element ${index}:`, {
                        tagName: el.tagName,
                        className: el.className,
                        textContent: el.textContent?.substring(0, 50),
                        attributes: Array.from(el.attributes).map(attr => `${attr.name}="${attr.value}"`)
                    });
                }
            });
            
            // Debug: Look for any elements containing date-like text
            const allElements = document.querySelectorAll('*');
            let dateElements = [];
            allElements.forEach(el => {
                const text = el.textContent || el.innerText || '';
                if (text.match(/\d{4}-\d{2}-\d{2}/) || text.match(/\d{1,2}\/\d{1,2}\/\d{4}/) || text.match(/\d{1,2}-\d{1,2}-\d{4}/)) {
                    dateElements.push({
                        element: el,
                        text: text.substring(0, 100),
                        tagName: el.tagName,
                        className: el.className
                    });
                }
            });
            log('Found elements with date-like text:', dateElements.slice(0, 10));

            // Find and style date elements with multiple approaches
            blockedDates.forEach(date => {
                console.log(`🔍 Processing blocked date: ${date}`);
                log(`Processing blocked date: ${date}`);
                
                // Convert date format from YYYY-MM-DD to "Month DD, YYYY" format for aria-label matching
                const dateObj = new Date(date);
                const monthNames = ["January", "February", "March", "April", "May", "June",
                    "July", "August", "September", "October", "November", "December"];
                const ariaLabelDate = `${monthNames[dateObj.getMonth()]} ${dateObj.getDate()}, ${dateObj.getFullYear()}`;
                
                console.log(`📅 Looking for aria-label: ${ariaLabelDate}`);
                log(`Looking for aria-label: ${ariaLabelDate}`);
                
                // Approach 1: Flatpickr specific selectors
                const flatpickrSelectors = [
                    `[aria-label="${ariaLabelDate}"]`,
                    `[aria-label*="${date}"]`,
                    `[title*="${ariaLabelDate}"]`,
                    `[title*="${date}"]`
                ];
                
                flatpickrSelectors.forEach(selector => {
                    const elements = document.querySelectorAll(selector);
                    console.log(`🎯 Flatpickr selector "${selector}" found ${elements.length} elements`);
                    log(`Flatpickr selector "${selector}" found ${elements.length} elements`);
                    elements.forEach(element => {
                        console.log(`✅ Styling element:`, element);
                        this.markElementAsBlocked(element);
                    });
                });
                
                // Approach 2: Direct attribute selectors
                const directSelectors = [
                    `[data-date="${date}"]`,
                    `[data-value="${date}"]`,
                    `[title*="${date}"]`
                ];
                
                directSelectors.forEach(selector => {
                    const elements = document.querySelectorAll(selector);
                    console.log(`🎯 Direct selector "${selector}" found ${elements.length} elements`);
                    log(`Direct selector "${selector}" found ${elements.length} elements`);
                    elements.forEach(element => {
                        console.log(`✅ Styling element:`, element);
                        this.markElementAsBlocked(element);
                    });
                });
                
                // Approach 3: Text content matching - more aggressive
                const allElements = document.querySelectorAll('*');
                let foundElements = 0;
                allElements.forEach(element => {
                    const elementText = element.textContent || element.innerText || '';
                    const elementDate = element.getAttribute('data-date') || 
                                      element.getAttribute('data-value') || 
                                      element.getAttribute('title') || 
                                      element.getAttribute('aria-label') || '';
                    
                    // Check if element contains the blocked date
                    if (elementDate.includes(date) || elementText.includes(date) || elementDate.includes(ariaLabelDate)) {
                        foundElements++;
                        console.log(`✅ Text content match found element:`, element);
                        this.markElementAsBlocked(element);
                    }
                });
                console.log(`📊 Text content matching found ${foundElements} elements for date ${date}`);
                log(`Text content matching found ${foundElements} elements for date ${date}`);
                
                // Approach 4: Look for calendar/date picker elements
                const calendarSelectors = [
                    '[class*="flatpickr"]',
                    '[class*="calendar"]',
                    '[class*="picker"]',
                    '[class*="date"]',
                    '[class*="day"]',
                    '[class*="booking"]',
                    '[class*="appointment"]',
                    '[class*="timetics"]'
                ];
                
                calendarSelectors.forEach(selector => {
                    const elements = document.querySelectorAll(selector);
                    let styledCount = 0;
                    elements.forEach(element => {
                        const elementText = element.textContent || element.innerText || '';
                        const ariaLabel = element.getAttribute('aria-label') || '';
                        if (elementText.includes(date) || ariaLabel.includes(ariaLabelDate) || ariaLabel.includes(date)) {
                            styledCount++;
                            this.markElementAsBlocked(element);
                        }
                    });
                    if (styledCount > 0) {
                        log(`Calendar selector "${selector}" styled ${styledCount} elements for date ${date}`);
                    }
                });
                
                // Approach 5: Look for any element with date-like content
                const datePattern = new RegExp(date.replace(/-/g, '[-/]'), 'i');
                const allDateElements = document.querySelectorAll('*');
                let patternMatches = 0;
                allDateElements.forEach(element => {
                    const text = element.textContent || element.innerText || '';
                    const ariaLabel = element.getAttribute('aria-label') || '';
                    if (datePattern.test(text) || datePattern.test(ariaLabel)) {
                        patternMatches++;
                        this.markElementAsBlocked(element);
                    }
                });
                log(`Date pattern matching found ${patternMatches} elements for date ${date}`);
            });
            
            console.log('=== BLOCKED DATES STYLING COMPLETE ===');
            console.log('Total blocked dates processed:', blockedDates.length);
            console.log('Blocked dates:', blockedDates);
        }

        configureFlatpickrBlocking(blockedDates) {
            log('Configuring Flatpickr blocking for dates:', blockedDates);
            
            // Convert blocked dates to Date objects for Flatpickr
            const disabledDates = blockedDates.map(date => new Date(date));
            log('Converted to Date objects:', disabledDates);
            
            // Find all Flatpickr inputs and configure them
            const flatpickrInputs = document.querySelectorAll('.flatpickr-input');
            log(`Found ${flatpickrInputs.length} Flatpickr inputs to configure`);
            
            let configuredCount = 0;
            
            flatpickrInputs.forEach((input, index) => {
                if (input._flatpickr) {
                    // Update existing instance
                    log(`Updating existing Flatpickr instance ${index}`);
                    const instance = input._flatpickr;
                    
                    // Set disabled dates
                    instance.set('disable', disabledDates);
                    configuredCount++;
                    
                    log(`Configured Flatpickr instance ${index} with disabled dates`);
                } else {
                    // Configure for future instances
                    log(`Pre-configuring Flatpickr input ${index} for future instances`);
                    
                    // Store configuration for when Flatpickr is initialized
                    input.setAttribute('data-timetics-blocked-dates', JSON.stringify(blockedDates));
                }
            });
            
            // Also configure any future Flatpickr instances
            this.configureFutureFlatpickrInstances(disabledDates);
            
            log(`Configured ${configuredCount} Flatpickr instances`);
            return configuredCount;
        }

        configureFutureFlatpickrInstances(disabledDates) {
            log('Configuring future Flatpickr instances...');
            
            // Override the global flatpickr function to automatically add disabled dates
            if (window.flatpickr) {
                const originalFlatpickr = window.flatpickr;
                const self = this;
                
                window.flatpickr = function(element, options) {
                    // Merge disabled dates with existing options
                    const defaultOptions = {
                        disable: disabledDates,
                        onReady: function(selectedDates, dateStr, instance) {
                            log('New Flatpickr instance ready with blocked dates');
                            
                            // Call original onReady if it exists
                            if (options && options.onReady) {
                                options.onReady(selectedDates, dateStr, instance);
                            }
                        }
                    };
                    
                    const mergedOptions = Object.assign({}, defaultOptions, options);
                    return originalFlatpickr(element, mergedOptions);
                };
                
                log('Global Flatpickr function overridden for automatic blocking');
            }
        }

        refreshNextAvailableDate() {
            log('Refreshing next available date calculation...');
            
            // Remove existing next available buttons
            const existingButtons = document.querySelectorAll('.timetics-next-available-btn');
            existingButtons.forEach(button => button.remove());
            
            // Recalculate and add new next available button if needed
            const nextAvailableDate = this.getNextAvailableDate();
            if (nextAvailableDate) {
                log('New next available date found:', nextAvailableDate);
                this.handleNoSlotState();
            } else {
                log('No next available date found after refresh');
            }
        }

        // Removed custom styling - using Flatpickr native disabled styling instead

        createNextAvailableButton(nextAvailableDate) {
            const button = document.createElement('button');
            button.className = 'timetics-next-available-btn';
            button.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <div style="margin-bottom: 10px;">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" style="color: #78AEFF;">
                            <path d="M8 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3 style="margin: 0 0 8px 0; color: #2c3e50; font-size: 16px; font-weight: 600;">
                        Next Available Date
                    </h3>
                    <p style="margin: 0 0 16px 0; color: #7f8c8d; font-size: 14px;">
                        ${nextAvailableDate.formatted}
                    </p>
                    <button style="
                        background: #78AEFF;
                        color: white;
                        border: none;
                        padding: 12px 24px;
                        border-radius: 6px;
                        font-size: 14px;
                        font-weight: 500;
                        cursor: pointer;
                        transition: all 0.3s ease;
                    " onmouseover="this.style.background='#5a9cff'" onmouseout="this.style.background='#78AEFF'">
                        Go to Next Available Date
                    </button>
                </div>
            `;

            // Add click handler
            button.addEventListener('click', () => {
                this.navigateToDate(nextAvailableDate.dateString);
            });

            return button;
        }

        navigateToDate(dateString) {
            log('Navigating to date:', dateString);
            
            // Method 1: Direct API call to fetch slots for the new date
            this.fetchSlotsForDate(dateString);
            
            // Method 2: Try to find and click the date in the calendar
            const dateElements = document.querySelectorAll('[data-date], [class*="date"], [class*="day"]');
            let found = false;
            
            dateElements.forEach(element => {
                const elementDate = this.extractDateFromElement(element);
                if (elementDate === dateString) {
                    // Try multiple click methods
                    this.attemptDateClick(element, dateString);
                    found = true;
                    log('Date clicked:', dateString);
                }
            });
            
            // Method 3: Try to find date in calendar grid
            if (!found) {
                const calendarGrid = document.querySelector('[class*="calendar"], [class*="picker"]');
                if (calendarGrid) {
                    const gridDates = calendarGrid.querySelectorAll('[data-date], [class*="date"], [class*="day"]');
                    gridDates.forEach(element => {
                        const elementDate = this.extractDateFromElement(element);
                        if (elementDate === dateString) {
                            this.attemptDateClick(element, dateString);
                            found = true;
                        }
                    });
                }
            }
            
            // Method 4: Try to trigger date change via Timetics API
            if (!found) {
                this.triggerDateChange(dateString);
            }
            
            // Method 5: Try to update the selected date display directly
            if (!found) {
                this.updateSelectedDateDisplay(dateString);
            }
        }

        attemptDateClick(element, dateString) {
            try {
                // Try regular click
                element.click();
                log('Regular click attempted on:', dateString);
            } catch (e) {
                log('Regular click failed:', e.message);
            }
            
            // Try mouse events
            try {
                const clickEvent = new MouseEvent('click', {
                    bubbles: true,
                    cancelable: true,
                    view: window
                });
                element.dispatchEvent(clickEvent);
                log('Mouse event dispatched on:', dateString);
            } catch (e) {
                log('Mouse event failed:', e.message);
            }
            
            // Try touch events (for mobile)
            try {
                const touchEvent = new TouchEvent('touchend', {
                    bubbles: true,
                    cancelable: true,
                    view: window
                });
                element.dispatchEvent(touchEvent);
                log('Touch event dispatched on:', dateString);
            } catch (e) {
                log('Touch event failed:', e.message);
            }
        }

        updateSelectedDateDisplay(dateString) {
            // Try to update the selected date display
            const selectedDateElement = document.querySelector('.tt-selected-date');
            if (selectedDateElement) {
                const date = new Date(dateString);
                const formattedDate = this.formatDateForDisplay(date);
                selectedDateElement.textContent = formattedDate;
                log('Updated selected date display to:', formattedDate);
                
                // Trigger a change event
                const changeEvent = new Event('change', { bubbles: true });
                selectedDateElement.dispatchEvent(changeEvent);
            }
        }

        async fetchSlotsForDate(dateString) {
            log('Fetching slots for date:', dateString);
            
            try {
                // Get current URL parameters to extract staff_id and meeting_id
                const urlParams = new URLSearchParams(window.location.search);
                const staffId = urlParams.get('staff_id') || this.extractStaffIdFromPage();
                const meetingId = urlParams.get('meeting_id') || this.extractMeetingIdFromPage();
                
                if (!staffId || !meetingId) {
                    log('Missing staff_id or meeting_id, cannot fetch slots');
                    return;
                }
                
                // Get timezone dynamically
                const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
                
                // Construct the API URL with new namespace
                const apiUrl = `${window.location.origin}/wp-json/timetics-occupied-slots/v1/bookings/entries?staff_id=${staffId}&meeting_id=${meetingId}&start_date=${dateString}&end_date=${dateString}&timezone=${encodeURIComponent(timezone)}`;
                
                log('Making API call to:', apiUrl);
                
                // Make the API call
                const response = await fetch(apiUrl);
                const data = await response.json();
                
                if (data && data.success) {
                    log('API response received:', data);
                    
                    // Update the selected date display
                    this.updateSelectedDateDisplay(dateString);
                    
                    // Trigger React update with new data
                    this.updateReactComponentsWithNewData(data);
                    
                    // Dispatch custom event for Timetics to handle
                    const event = new CustomEvent('timetics-date-changed', {
                        detail: {
                            date: dateString,
                            data: data
                        }
                    });
                    document.dispatchEvent(event);
                    
                    log('Successfully navigated to date:', dateString);
                } else {
                    log('API call failed:', data);
                }
            } catch (error) {
                log('Error fetching slots for date:', error);
            }
        }
        
        extractStaffIdFromPage() {
            // Try to extract staff_id from various sources
            const staffIdInput = document.querySelector('input[name="staff_id"], input[id*="staff"]');
            if (staffIdInput) {
                return staffIdInput.value;
            }
            
            // Try to get from URL hash or data attributes
            const staffElement = document.querySelector('[data-staff-id]');
            if (staffElement) {
                return staffElement.getAttribute('data-staff-id');
            }
            
            // Try to get from window object
            if (window.timetics && window.timetics.staffId) {
                return window.timetics.staffId;
            }
            
            return null;
        }
        
        extractMeetingIdFromPage() {
            // Try to extract meeting_id from various sources
            const meetingIdInput = document.querySelector('input[name="meeting_id"], input[id*="meeting"]');
            if (meetingIdInput) {
                return meetingIdInput.value;
            }
            
            // Try to get from URL hash or data attributes
            const meetingElement = document.querySelector('[data-meeting-id]');
            if (meetingElement) {
                return meetingElement.getAttribute('data-meeting-id');
            }
            
            // Try to get from window object
            if (window.timetics && window.timetics.meetingId) {
                return window.timetics.meetingId;
            }
            
            return null;
        }
        
        updateReactComponentsWithNewData(data) {
            console.log('[React Integration] 🔄 Updating React components with new data');
            console.log('[React Integration] Data received:', data);
            
            // Update the slot wrapper with new data
            const slotWrapper = document.querySelector('.tt-booking-slot-wrapper, .tt-slot-list-wrap');
            if (slotWrapper && data.data && data.data.days) {
                const day = data.data.days[0];
                if (day) {
                    console.log('[React Integration] Day data:', day);
                    console.log('[React Integration] Day status:', day.status);
                    console.log('[React Integration] Day slots count:', day.slots?.length || 0);
                    
                    // Update the slot list
                    const slotList = slotWrapper.querySelector('.tt-slot-list-wrap');
                    if (slotList) {
                        if (day.status === 'available' && day.slots && day.slots.length > 0) {
                            console.log('[React Integration] 🎯 Rendering', day.slots.length, 'available slots');
                            
                            // Create slot HTML with validation
                            const slotHTML = day.slots.map((slot, index) => {
                                console.log(`[React Integration] Slot ${index + 1}/${day.slots.length}:`, slot.time);
                                return `
                                    <div class="tt-slot-item" 
                                         data-time="${slot.time}" 
                                         data-slot-index="${index}"
                                         data-total-slots="${day.slots.length}"
                                         data-rendered-by="react-integration">
                                        ${slot.time}
                                    </div>
                                `;
                            }).join('');
                            
                            // Set innerHTML with validation
                            slotList.innerHTML = slotHTML;
                            
                            // Validate rendering after a short delay
                            setTimeout(() => {
                                const renderedSlots = slotList.querySelectorAll('.tt-slot-item');
                                console.log('[React Integration] ✅ Rendered', renderedSlots.length, 'slots, expected', day.slots.length);
                                
                                if (renderedSlots.length !== day.slots.length) {
                                    console.log('[React Integration] ⚠️ Slot count mismatch detected!');
                                    console.log('[React Integration] Expected:', day.slots.length, 'Actual:', renderedSlots.length);
                                    
                                    // Attempt to fix by re-rendering
                                    console.log('[React Integration] 🔄 Attempting to fix slot rendering...');
                                    this.fixSlotRendering(slotList, day.slots);
                                } else {
                                    console.log('[React Integration] ✅ All slots rendered successfully');
                                }
                            }, 100);
                            
                        } else {
                            console.log('[React Integration] 📭 No slots available, showing empty state');
                            // Show no slots available
                            slotList.innerHTML = `
                                <div style="height: 100%; display: flex; justify-content: center; align-items: center; flex-direction: column;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" fill="none">
                                        <rect width="16" height="16" y="22" fill="#ECF2F8" rx="3"></rect>
                                        <rect width="16" height="16" y="44" fill="#ECF2F8" rx="3"></rect>
                                        <rect width="16" height="16" x="22" fill="#ECF2F8" rx="3"></rect>
                                        <rect width="16" height="16" fill="#ECF2F8" rx="3"></rect>
                                        <rect width="16" height="16" x="22" y="22" fill="#78AEFF" rx="3"></rect>
                                        <path fill="#fff" d="M28.038 26.727V34h-1.317v-5.99h-.043l-1.701 1.086v-1.207l1.807-1.162h1.254Zm4.375 7.372c-.473 0-.897-.088-1.271-.266a2.266 2.266 0 0 1-.892-.739 1.955 1.955 0 0 1-.348-1.072h1.279c.023.298.152.542.387.732.234.187.516.28.845.28.258 0 .488-.06.689-.178.201-.118.36-.282.476-.493.116-.21.173-.451.17-.721a1.454 1.454 0 0 0-.174-.732 1.3 1.3 0 0 0-.486-.5 1.358 1.358 0 0 0-.71-.185 1.692 1.692 0 0 0-.643.12c-.211.084-.378.193-.501.328l-1.19-.196.38-3.75h4.22v1.101h-3.13l-.209 1.928h.043c.135-.158.325-.29.571-.394.247-.106.516-.16.81-.16.44 0 .833.105 1.179.313.346.206.618.49.817.852.199.362.298.777.298 1.243 0 .48-.111.91-.334 1.286-.22.374-.526.668-.92.884-.39.213-.842.32-1.356.32Z"></path>
                                        <rect width="16" height="16" x="22" y="44" fill="#ECF2F8" rx="3"></rect>
                                        <rect width="16" height="16" x="44" fill="#ECF2F8" rx="3"></rect>
                                        <rect width="16" height="16" x="44" y="22" fill="#ECF2F8" rx="3"></rect>
                                    </svg>
                                    <p style="text-align: center; color: rgb(85, 104, 128); font-weight: 400;">No slot available</p>
                                </div>
                            `;
                        }
                    } else {
                        console.log('[React Integration] ⚠️ Slot list container not found');
                    }
                } else {
                    console.log('[React Integration] ⚠️ No day data available');
                }
            } else {
                console.log('[React Integration] ⚠️ Slot wrapper not found or no data available');
            }
        }
        
        /**
         * Fix slot rendering when count mismatch is detected
         */
        fixSlotRendering(slotList, slots) {
            console.log('[React Integration] 🔧 Fixing slot rendering...');
            
            // Clear the container first
            slotList.innerHTML = '';
            
            // Add slots one by one with validation
            slots.forEach((slot, index) => {
                console.log(`[React Integration] Adding slot ${index + 1}/${slots.length}:`, slot.time);
                
                const slotElement = document.createElement('div');
                slotElement.className = 'tt-slot-item';
                slotElement.setAttribute('data-time', slot.time);
                slotElement.setAttribute('data-slot-index', index);
                slotElement.setAttribute('data-total-slots', slots.length);
                slotElement.setAttribute('data-rendered-by', 'react-integration-fix');
                slotElement.textContent = slot.time;
                
                slotList.appendChild(slotElement);
                
                // Validate after each addition
                setTimeout(() => {
                    const currentSlots = slotList.querySelectorAll('.tt-slot-item');
                    console.log(`[React Integration] After slot ${index + 1}: ${currentSlots.length} slots rendered`);
                }, 10);
            });
            
            // Final validation
            setTimeout(() => {
                const finalSlots = slotList.querySelectorAll('.tt-slot-item');
                console.log('[React Integration] 🎯 Final slot count:', finalSlots.length, 'Expected:', slots.length);
                
                if (finalSlots.length === slots.length) {
                    console.log('[React Integration] ✅ Slot rendering fixed successfully');
                } else {
                    console.log('[React Integration] ❌ Slot rendering fix failed');
                }
            }, 200);
        }

        triggerDateChange(dateString) {
            // Try to trigger date change via Timetics API
            const date = new Date(dateString);
            const formattedDate = date.toISOString().split('T')[0];
            
            // Method 1: Dispatch custom event for Timetics to handle
            const event = new CustomEvent('timetics-date-change', {
                detail: {
                    date: formattedDate,
                    dateString: dateString
                }
            });
            document.dispatchEvent(event);
            log('Date change event dispatched:', formattedDate);
            
            // Method 2: Try to trigger Timetics date change via window object
            if (window.timetics && window.timetics.changeDate) {
                try {
                    window.timetics.changeDate(formattedDate);
                    log('Timetics changeDate called:', formattedDate);
                } catch (e) {
                    log('Timetics changeDate failed:', e.message);
                }
            }
            
            // Method 3: Try to trigger via React state update
            if (window.React && window.ReactDOM) {
                try {
                    // Look for React components that might handle date changes
                    const reactRoots = document.querySelectorAll('[data-reactroot], [data-react-helmet]');
                    reactRoots.forEach(root => {
                        const reactEvent = new CustomEvent('react-date-change', {
                            detail: {
                                date: formattedDate,
                                dateString: dateString
                            }
                        });
                        root.dispatchEvent(reactEvent);
                    });
                    log('React date change events dispatched');
                } catch (e) {
                    log('React date change failed:', e.message);
                }
            }
            
            // Method 4: Try to update URL parameters if Timetics uses them
            if (window.history && window.history.pushState) {
                try {
                    const url = new URL(window.location);
                    url.searchParams.set('date', formattedDate);
                    window.history.pushState({}, '', url);
                    log('URL updated with date:', formattedDate);
                } catch (e) {
                    log('URL update failed:', e.message);
                }
            }
        }

        processNewNode(node) {
            // Look for calendar-related elements
            const calendarElements = node.querySelectorAll ? 
                node.querySelectorAll('[class*="calendar"], [class*="date"], [class*="picker"]') : 
                [];
            
            if (calendarElements.length > 0) {
                log('Calendar elements detected:', calendarElements.length);
                setTimeout(() => this.updateReactComponents(), 100);
            }
        }

        updateReactComponents() {
            if (this.blockedDates.length === 0) {
                return;
            }

            log('Updating React components with blocked dates...');

            // Find and update calendar elements
            this.updateCalendarElements();
            
            // Update date picker elements
            this.updateDatePickerElements();
            
            // Update booking form elements
            this.updateBookingFormElements();
            
            // Disable blocked dates in calendar
            this.disableBlockedDates();
            
            // Monitor for "No slot available" state
            this.monitorNoSlotState();
        }

        updateCalendarElements() {
            // Find calendar date elements
            const dateElements = document.querySelectorAll(
                '[class*="calendar"] [class*="date"], ' +
                '[class*="picker"] [class*="date"], ' +
                '[data-date], [class*="day"]'
            );

            dateElements.forEach(element => {
                const dateText = this.extractDateFromElement(element);
                if (dateText && this.isDateBlocked(dateText)) {
                    this.markElementAsBlocked(element);
                }
            });

            log(`Updated ${dateElements.length} calendar elements`);
        }

        updateDatePickerElements() {
            // Find date picker elements
            const pickerElements = document.querySelectorAll(
                '[class*="ant-picker"], ' +
                '[class*="date-picker"], ' +
                '[class*="calendar-picker"]'
            );

            pickerElements.forEach(picker => {
                const dateCells = picker.querySelectorAll('[class*="cell"], [class*="day"]');
                dateCells.forEach(cell => {
                    const dateText = this.extractDateFromElement(cell);
                    if (dateText && this.isDateBlocked(dateText)) {
                        this.markElementAsBlocked(cell);
                    }
                });
            });

            log(`Updated ${pickerElements.length} date picker elements`);
        }

        updateBookingFormElements() {
            // Find booking form elements
            const formElements = document.querySelectorAll(
                '[class*="booking"], ' +
                '[class*="appointment"], ' +
                '[class*="schedule"]'
            );

            formElements.forEach(form => {
                const dateElements = form.querySelectorAll('[data-date], [class*="date"]');
                dateElements.forEach(element => {
                    const dateText = this.extractDateFromElement(element);
                    if (dateText && this.isDateBlocked(dateText)) {
                        this.markElementAsBlocked(element);
                    }
                });
            });

            log(`Updated ${formElements.length} booking form elements`);
        }

        disableBlockedDates() {
            log('Disabling blocked dates in calendar...');
            
            // Find all calendar date elements
            const dateElements = document.querySelectorAll(
                '[class*="calendar"] [class*="date"], ' +
                '[class*="picker"] [class*="date"], ' +
                '[class*="day"], ' +
                '[data-date], ' +
                '[class*="cell"]'
            );

            let disabledCount = 0;
            dateElements.forEach(element => {
                const dateText = this.extractDateFromElement(element);
                if (dateText && this.isDateBlocked(dateText)) {
                    this.disableDateElement(element, dateText);
                    disabledCount++;
                }
            });

            log(`Disabled ${disabledCount} blocked dates in calendar`);
        }

        disableDateElement(element, dateString) {
            log('Disabling date element (natural method):', dateString, element);
            
            // Add disabled class for identification (no custom styles)
            element.classList.add('timetics-date-disabled');
            
            // Disable interaction using standard HTML attributes
            element.setAttribute('disabled', 'true');
            element.setAttribute('data-disabled', 'true');
            element.setAttribute('data-disabled-reason', 'blocked_date');
            element.setAttribute('data-blocked-date', dateString);
            element.setAttribute('aria-disabled', 'true');
            
            // Add tooltip for accessibility
            element.title = 'This date is not available';
            
            // Disable pointer events (this is the key for natural disabling)
            element.style.pointerEvents = 'none';
            
            // Remove all existing click handlers
            element.onclick = null;
            
            // Add click handler that prevents interaction
            element.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                log('Blocked click on disabled date:', dateString);
                return false;
            }, true);
            
            // Add focus prevention
            element.addEventListener('focus', (e) => {
                e.preventDefault();
                element.blur();
            });
            
            // Add keydown prevention
            element.addEventListener('keydown', (e) => {
                e.preventDefault();
                e.stopPropagation();
                return false;
            });
            
            // Add touch prevention for mobile
            element.addEventListener('touchstart', (e) => {
                e.preventDefault();
                e.stopPropagation();
                return false;
            });
            
            element.addEventListener('touchend', (e) => {
                e.preventDefault();
                e.stopPropagation();
                return false;
            });
            
            log('Date element disabled successfully (natural method):', dateString);
        }

        extractDateFromElement(element) {
            // Try multiple methods to extract date
            const dateText = element.getAttribute('data-date') ||
                           element.getAttribute('data-value') ||
                           element.textContent?.trim() ||
                           element.getAttribute('title');

            if (dateText) {
                // Try to parse the date
                const date = new Date(dateText);
                if (!isNaN(date.getTime())) {
                    return date.toISOString().split('T')[0];
                }
                
                // Try to extract YYYY-MM-DD format
                const dateMatch = dateText.match(/(\d{4}-\d{2}-\d{2})/);
                if (dateMatch) {
                    return dateMatch[1];
                }
            }

            return null;
        }

        isDateBlocked(dateString) {
            // Check multiple sources for blocked dates
            const blockedDates = this.blockedDates || window.timeticsBlockedDates || [];
            const isBlocked = blockedDates.includes(dateString);
            
            log(`Checking if date ${dateString} is blocked:`, {
                blockedDates: blockedDates,
                isBlocked: isBlocked,
                thisBlockedDates: this.blockedDates,
                windowBlockedDates: window.timeticsBlockedDates
            });
            
            return isBlocked;
        }

        markElementAsBlocked(element) {
            console.log('🎨 Marking element as blocked:', element);
            console.log('Element details:', {
                tagName: element.tagName,
                className: element.className,
                textContent: element.textContent?.substring(0, 50),
                attributes: Array.from(element.attributes).map(attr => `${attr.name}="${attr.value}"`)
            });
            
            // Add blocked class for identification
            element.classList.add(CONFIG.blockedDateClass);
            console.log('✅ Added blocked date class:', CONFIG.blockedDateClass);
            
            // Disable interaction using standard HTML attributes
            element.setAttribute('disabled', 'true');
            element.setAttribute('data-blocked', 'true');
            element.setAttribute('data-blocked-reason', 'date_fully_occupied');
            element.setAttribute('aria-disabled', 'true');
            console.log('✅ Added disabled attributes');
            
            // Add tooltip for accessibility
            element.title = 'This date is fully occupied';
            
            // Disable pointer events (natural disabling)
            element.style.pointerEvents = 'none';
            
            // Remove click handlers
            element.onclick = null;
            
            // Add click prevention
            element.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }, true);
            
            // Add focus prevention
            element.addEventListener('focus', (e) => {
                e.preventDefault();
                element.blur();
            });
            
            // Add keydown prevention
            element.addEventListener('keydown', (e) => {
                e.preventDefault();
                e.stopPropagation();
                return false;
            });
        }

        monitorDOMChanges() {
            // Use MutationObserver to watch for React updates
            const observer = new MutationObserver((mutations) => {
                let shouldUpdate = false;
                
                mutations.forEach((mutation) => {
                    if (mutation.type === 'childList' || mutation.type === 'attributes') {
                        shouldUpdate = true;
                    }
                });
                
                if (shouldUpdate) {
                    setTimeout(() => this.updateReactComponents(), 200);
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['class', 'data-date', 'data-value']
            });

            log('DOM change monitoring started');
        }

        // Public API methods
        getBlockedDates() {
            return this.blockedDates;
        }

        addBlockedDate(date) {
            if (!this.blockedDates.includes(date)) {
                this.blockedDates.push(date);
                this.updateReactComponents();
            }
        }

        removeBlockedDate(date) {
            const index = this.blockedDates.indexOf(date);
            if (index > -1) {
                this.blockedDates.splice(index, 1);
                this.updateReactComponents();
            }
        }

        refresh() {
            this.updateReactComponents();
        }
    }

    // Initialize when DOM is ready
    console.log('📜 SCRIPT LOADED - Document ready state:', document.readyState);
    if (document.readyState === 'loading') {
        console.log('⏳ Document still loading, waiting for DOMContentLoaded...');
        document.addEventListener('DOMContentLoaded', () => {
            console.log('🎯 DOMContentLoaded fired, creating integration...');
            window.TimeticsOccupiedSlots = new TimeticsOccupiedSlotsReactIntegration();
        });
    } else {
        console.log('✅ Document already ready, creating integration immediately...');
        window.TimeticsOccupiedSlots = new TimeticsOccupiedSlotsReactIntegration();
    }

    // Expose global API
    window.TimeticsOccupiedSlotsAPI = {
        getBlockedDates: () => window.TimeticsOccupiedSlots?.getBlockedDates() || [],
        addBlockedDate: (date) => window.TimeticsOccupiedSlots?.addBlockedDate(date),
        removeBlockedDate: (date) => window.TimeticsOccupiedSlots?.removeBlockedDate(date),
        refresh: () => window.TimeticsOccupiedSlots?.refresh()
    };

    log('Timetics Occupied Slots React Integration loaded');

})();
