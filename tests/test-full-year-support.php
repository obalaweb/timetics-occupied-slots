<?php
/**
 * Test Full Year Support
 * 
 * Tests the new full year functionality without breaking existing 30-day default
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TestFullYearSupport {
    
    public function __construct() {
        add_action('wp_ajax_test_full_year', [$this, 'test_full_year_api']);
        add_action('wp_ajax_nopriv_test_full_year', [$this, 'test_full_year_api']);
    }
    
    /**
     * Test full year API endpoints
     */
    public function test_full_year_api() {
        $results = [
            'timestamp' => current_time('mysql'),
            'tests' => []
        ];
        
        // Test 1: Existing 30-day endpoint (should work as before)
        $results['tests']['existing_30_day'] = $this->test_existing_endpoint();
        
        // Test 2: New full year endpoint
        $results['tests']['new_full_year'] = $this->test_full_year_endpoint();
        
        // Test 3: Backward compatibility
        $results['tests']['backward_compatibility'] = $this->test_backward_compatibility();
        
        wp_send_json_success($results);
    }
    
    /**
     * Test existing 30-day endpoint
     */
    private function test_existing_endpoint() {
        $url = home_url('/wp-json/timetics-occupied-slots/v1/bookings/entries');
        $params = [
            'staff_id' => 71,
            'meeting_id' => 2315,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+30 days')),
            'timezone' => 'Africa/Johannesburg'
        ];
        
        $url .= '?' . http_build_query($params);
        
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return [
                'status' => 'error',
                'message' => $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return [
            'status' => 'success',
            'url' => $url,
            'response_code' => wp_remote_retrieve_response_code($response),
            'has_blocked_dates' => isset($data['data']['blocked_dates']),
            'blocked_dates_count' => isset($data['data']['blocked_dates']) ? count($data['data']['blocked_dates']) : 0
        ];
    }
    
    /**
     * Test new full year endpoint
     */
    private function test_full_year_endpoint() {
        $url = home_url('/wp-json/timetics-occupied-slots/v1/bookings/entries/full-year');
        $params = [
            'staff_id' => 71,
            'meeting_id' => 2315,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+90 days')),
            'timezone' => 'Africa/Johannesburg',
            'tier' => 'warm'
        ];
        
        $url .= '?' . http_build_query($params);
        
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return [
                'status' => 'error',
                'message' => $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return [
            'status' => 'success',
            'url' => $url,
            'response_code' => wp_remote_retrieve_response_code($response),
            'has_blocked_dates' => isset($data['data']['blocked_dates']),
            'blocked_dates_count' => isset($data['data']['blocked_dates']) ? count($data['data']['blocked_dates']) : 0,
            'cache_info' => isset($data['data']['cache_info']) ? $data['data']['cache_info'] : null
        ];
    }
    
    /**
     * Test backward compatibility
     */
    private function test_backward_compatibility() {
        // Test that existing endpoint still works with full_year=false
        $url = home_url('/wp-json/timetics-occupied-slots/v1/bookings/entries');
        $params = [
            'staff_id' => 71,
            'meeting_id' => 2315,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+30 days')),
            'timezone' => 'Africa/Johannesburg',
            'full_year' => false  // Explicitly set to false
        ];
        
        $url .= '?' . http_build_query($params);
        
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return [
                'status' => 'error',
                'message' => $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return [
            'status' => 'success',
            'url' => $url,
            'response_code' => wp_remote_retrieve_response_code($response),
            'uses_intelligent_detector' => isset($data['data']['cache_info']['source']) && $data['data']['cache_info']['source'] === 'intelligent_detector',
            'has_blocked_dates' => isset($data['data']['blocked_dates']),
            'blocked_dates_count' => isset($data['data']['blocked_dates']) ? count($data['data']['blocked_dates']) : 0
        ];
    }
}

// Initialize test class
new TestFullYearSupport();

// Add test page for manual testing
add_action('wp_ajax_test_full_year_ui', function() {
    ?>
    <div style="padding: 20px; font-family: Arial, sans-serif;">
        <h2>Full Year Support Test</h2>
        
        <div style="margin: 20px 0;">
            <h3>Test 1: Existing 30-Day Endpoint</h3>
            <button onclick="testEndpoint('/wp-json/timetics-occupied-slots/v1/bookings/entries?staff_id=71&meeting_id=2315&start_date=<?php echo date('Y-m-d'); ?>&end_date=<?php echo date('Y-m-d', strtotime('+30 days')); ?>&timezone=Africa/Johannesburg')">
                Test 30-Day Endpoint
            </button>
            <div id="result1" style="margin-top: 10px; padding: 10px; background: #f0f0f0;"></div>
        </div>
        
        <div style="margin: 20px 0;">
            <h3>Test 2: Full Year Endpoint</h3>
            <button onclick="testEndpoint('/wp-json/timetics-occupied-slots/v1/bookings/entries/full-year?staff_id=71&meeting_id=2315&start_date=<?php echo date('Y-m-d'); ?>&end_date=<?php echo date('Y-m-d', strtotime('+90 days')); ?>&timezone=Africa/Johannesburg&tier=warm')">
                Test Full Year Endpoint
            </button>
            <div id="result2" style="margin-top: 10px; padding: 10px; background: #f0f0f0;"></div>
        </div>
        
        <div style="margin: 20px 0;">
            <h3>Test 3: Backward Compatibility</h3>
            <button onclick="testEndpoint('/wp-json/timetics-occupied-slots/v1/bookings/entries?staff_id=71&meeting_id=2315&start_date=<?php echo date('Y-m-d'); ?>&end_date=<?php echo date('Y-m-d', strtotime('+30 days')); ?>&timezone=Africa/Johannesburg&full_year=false')">
                Test Backward Compatibility
            </button>
            <div id="result3" style="margin-top: 10px; padding: 10px; background: #f0f0f0;"></div>
        </div>
        
        <script>
        function testEndpoint(url) {
            const resultId = event.target.nextElementSibling.id;
            const resultDiv = document.getElementById(resultId);
            resultDiv.innerHTML = 'Testing...';
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    resultDiv.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                })
                .catch(error => {
                    resultDiv.innerHTML = '<span style="color: red;">Error: ' + error.message + '</span>';
                });
        }
        </script>
    </div>
    <?php
    wp_die();
});
