/**
 * Simple test script to verify JavaScript execution
 */

(function() {
    'use strict';
    
    console.log('[TEST] 🚀 Simple test script loaded and executing');
    
    // Test basic functionality
    function testFunction() {
        console.log('[TEST] ✅ Test function executed successfully');
        return true;
    }
    
    // Test DOM ready
    function testDOMReady() {
        console.log('[TEST] 📄 DOM ready test');
        console.log('[TEST] - Document ready state:', document.readyState);
        console.log('[TEST] - Document body exists:', !!document.body);
        console.log('[TEST] - Window loaded:', document.readyState === 'complete');
    }
    
    // Initialize when ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', testDOMReady);
    } else {
        testDOMReady();
    }
    
    // Test function
    testFunction();
    
    // Expose for debugging
    window.SimpleTest = {
        testFunction,
        testDOMReady
    };
    
    console.log('[TEST] ✅ Simple test script initialization complete');
    
})();
