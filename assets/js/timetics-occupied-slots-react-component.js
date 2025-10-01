/**
 * Timetics Occupied Slots - React Component Wrapper
 * 
 * This creates a React component that wraps Timetics components
 * and adds blocked date functionality
 */

(function() {
    'use strict';

    // Wait for React to be available
    function waitForReact() {
        return new Promise((resolve) => {
            if (window.React && window.ReactDOM) {
                resolve();
            } else {
                const checkReact = () => {
                    if (window.React && window.ReactDOM) {
                        resolve();
                    } else {
                        setTimeout(checkReact, 100);
                    }
                };
                checkReact();
            }
        });
    }

    // Create React component wrapper
    async function createOccupiedSlotsWrapper() {
        await waitForReact();
        
        const { React, ReactDOM } = window;
        
        // Occupied Slots Context
        const OccupiedSlotsContext = React.createContext({
            blockedDates: [],
            addBlockedDate: () => {},
            removeBlockedDate: () => {},
            isDateBlocked: () => false
        });

        // Occupied Slots Provider Component
        const OccupiedSlotsProvider = ({ children }) => {
            const [blockedDates, setBlockedDates] = React.useState([]);
            const [isLoading, setIsLoading] = React.useState(false);

            // Load blocked dates from API
            const loadBlockedDates = React.useCallback(async () => {
                setIsLoading(true);
                try {
                    // Get current URL parameters for staff_id and meeting_id
                    const urlParams = new URLSearchParams(window.location.search);
                    const staffId = urlParams.get('staff_id') || '1';
                    const meetingId = urlParams.get('meeting_id') || '1';
                    
                    const startDate = new Date().toISOString().split('T')[0];
                    const endDate = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    
                    // Get timezone dynamically
                    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
                    
                    const response = await fetch(`/wp-json/timetics-occupied-slots/v1/bookings/entries?staff_id=${staffId}&meeting_id=${meetingId}&start_date=${startDate}&end_date=${endDate}&timezone=${encodeURIComponent(timezone)}`);
                    
                    const data = await response.json();
                    
                    // Check for blocked dates in the main response
                    if (data && data.blocked_dates) {
                        setBlockedDates(data.blocked_dates);
                        console.log('Loaded blocked dates:', data.blocked_dates);
                    } else if (data && data.data && data.data.blocked_dates) {
                        setBlockedDates(data.data.blocked_dates);
                        console.log('Loaded blocked dates from data:', data.data.blocked_dates);
                    }
                } catch (error) {
                    console.error('Error loading blocked dates:', error);
                } finally {
                    setIsLoading(false);
                }
            }, []);

            // Load blocked dates on mount
            React.useEffect(() => {
                loadBlockedDates();
            }, [loadBlockedDates]);

            // Add blocked date
            const addBlockedDate = React.useCallback((date) => {
                setBlockedDates(prev => [...prev, date]);
            }, []);

            // Remove blocked date
            const removeBlockedDate = React.useCallback((date) => {
                setBlockedDates(prev => prev.filter(d => d !== date));
            }, []);

            // Check if date is blocked
            const isDateBlocked = React.useCallback((date) => {
                return blockedDates.includes(date);
            }, [blockedDates]);

            const value = {
                blockedDates,
                addBlockedDate,
                removeBlockedDate,
                isDateBlocked,
                isLoading,
                refresh: loadBlockedDates
            };

            return React.createElement(OccupiedSlotsContext.Provider, { value }, children);
        };

        // Hook to use occupied slots context
        const useOccupiedSlots = () => {
            const context = React.useContext(OccupiedSlotsContext);
            if (!context) {
                throw new Error('useOccupiedSlots must be used within an OccupiedSlotsProvider');
            }
            return context;
        };

        // Blocked Date Component
        const BlockedDateIndicator = ({ date, children, ...props }) => {
            const { isDateBlocked } = useOccupiedSlots();
            const isBlocked = isDateBlocked(date);

            if (isBlocked) {
                return React.createElement('div', {
                    ...props,
                    className: `timetics-date-blocked ${props.className || ''}`,
                    style: {
                        backgroundColor: '#ff6b6b',
                        color: '#ffffff',
                        cursor: 'not-allowed',
                        opacity: 0.7,
                        pointerEvents: 'none',
                        ...props.style
                    },
                    title: 'This date is fully occupied',
                    'data-blocked': 'true',
                    'data-blocked-reason': 'date_fully_occupied'
                }, children, ' 🚫');
            }

            return React.createElement('div', props, children);
        };

        // Calendar Wrapper Component
        const OccupiedSlotsCalendarWrapper = ({ children, ...props }) => {
            const { blockedDates, isLoading } = useOccupiedSlots();

            return React.createElement('div', {
                ...props,
                className: `timetics-occupied-slots-calendar ${props.className || ''}`,
                'data-blocked-dates': JSON.stringify(blockedDates),
                'data-loading': isLoading
            }, children);
        };

        // Booking Form Wrapper Component
        const OccupiedSlotsBookingWrapper = ({ children, ...props }) => {
            const { blockedDates, isLoading } = useOccupiedSlots();

            return React.createElement('div', {
                ...props,
                className: `timetics-occupied-slots-booking ${props.className || ''}`,
                'data-blocked-dates': JSON.stringify(blockedDates),
                'data-loading': isLoading
            }, children);
        };

        // Date Picker Wrapper Component
        const OccupiedSlotsDatePickerWrapper = ({ children, ...props }) => {
            const { blockedDates, isLoading } = useOccupiedSlots();

            return React.createElement('div', {
                ...props,
                className: `timetics-occupied-slots-datepicker ${props.className || ''}`,
                'data-blocked-dates': JSON.stringify(blockedDates),
                'data-loading': isLoading
            }, children);
        };

        // Expose components globally
        window.TimeticsOccupiedSlotsReact = {
            OccupiedSlotsProvider,
            OccupiedSlotsContext,
            useOccupiedSlots,
            BlockedDateIndicator,
            OccupiedSlotsCalendarWrapper,
            OccupiedSlotsBookingWrapper,
            OccupiedSlotsDatePickerWrapper
        };

        console.log('[Timetics Occupied Slots] React components created');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createOccupiedSlotsWrapper);
    } else {
        createOccupiedSlotsWrapper();
    }

})();
