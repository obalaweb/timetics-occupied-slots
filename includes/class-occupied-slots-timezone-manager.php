<?php
/**
 * Timetics Occupied Slots Addon - Timezone Manager
 * 
 * Handles timezone standardization across the plugin
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

class OccupiedSlotsTimezoneManager
{
    /**
     * Singleton instance.
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        // Initialize timezone handling
    }

    /**
     * Get standardized timezone string.
     * 
     * @return string
     */
    public function get_standardized_timezone()
    {
        // Try multiple methods to get the correct timezone
        $timezone = null;
        
        // Method 1: WordPress timezone setting
        if (function_exists('wp_timezone_string')) {
            $timezone = wp_timezone_string();
        }
        
        // Method 2: WordPress timezone option
        if (!$timezone) {
            $timezone = get_option('timezone_string');
        }
        
        // Method 3: GMT offset fallback
        if (!$timezone) {
            $gmt_offset = get_option('gmt_offset');
            $timezone = $this->gmt_offset_to_timezone($gmt_offset);
        }
        
        // Method 4: Server timezone
        if (!$timezone) {
            $timezone = date_default_timezone_get();
        }
        
        // Final fallback
        if (!$timezone) {
            $timezone = 'UTC';
        }
        
        return $timezone;
    }

    /**
     * Convert GMT offset to timezone string.
     * 
     * @param float $gmt_offset
     * @return string
     */
    private function gmt_offset_to_timezone($gmt_offset)
    {
        $offset = $gmt_offset * 3600;
        $timezone = timezone_name_from_abbr('', $offset, 0);
        
        if ($timezone === false) {
            $timezone = 'UTC';
        }
        
        return $timezone;
    }

    /**
     * Validate timezone string.
     * 
     * @param string $timezone
     * @return bool
     */
    public function is_valid_timezone($timezone)
    {
        if (empty($timezone)) {
            return false;
        }
        
        try {
            new DateTimeZone($timezone);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Convert date/time between timezones.
     * 
     * @param string $datetime
     * @param string $from_timezone
     * @param string $to_timezone
     * @return DateTime
     */
    public function convert_timezone($datetime, $from_timezone, $to_timezone)
    {
        try {
            $from_tz = new DateTimeZone($from_timezone);
            $to_tz = new DateTimeZone($to_timezone);
            
            $date = new DateTime($datetime, $from_tz);
            $date->setTimezone($to_tz);
            
            return $date;
        } catch (Exception $e) {
            // Fallback to UTC
            $utc_tz = new DateTimeZone('UTC');
            $date = new DateTime($datetime, $utc_tz);
            return $date;
        }
    }

    /**
     * Get timezone-aware date format.
     * 
     * @param string $format
     * @param string $timezone
     * @return string
     */
    public function get_timezone_aware_date($format = 'Y-m-d H:i:s', $timezone = null)
    {
        if (!$timezone) {
            $timezone = $this->get_standardized_timezone();
        }
        
        try {
            $tz = new DateTimeZone($timezone);
            $date = new DateTime('now', $tz);
            return $date->format($format);
        } catch (Exception $e) {
            return date($format);
        }
    }

    /**
     * Get timezone list for frontend.
     * 
     * @return array
     */
    public function get_timezone_list()
    {
        $timezones = [];
        
        // Get common timezones
        $common_timezones = [
            'UTC' => 'UTC',
            'America/New_York' => 'Eastern Time',
            'America/Chicago' => 'Central Time',
            'America/Denver' => 'Mountain Time',
            'America/Los_Angeles' => 'Pacific Time',
            'Europe/London' => 'London',
            'Europe/Paris' => 'Paris',
            'Asia/Tokyo' => 'Tokyo',
            'Asia/Shanghai' => 'Shanghai',
            'Australia/Sydney' => 'Sydney',
            'Africa/Johannesburg' => 'Johannesburg'
        ];
        
        foreach ($common_timezones as $tz => $label) {
            if ($this->is_valid_timezone($tz)) {
                $timezones[$tz] = $label;
            }
        }
        
        return $timezones;
    }
}
