<?php
/**
 * Translation System for BGG Signup
 * 
 * Simple translation function that loads language files from /languages/ directory
 */

// Load configuration
if (!isset($config)) {
    $config = require __DIR__ . '/../config.php';
}

// Current language (can be overridden by user preference or cookie)
$current_language = isset($config['default_language']) ? $config['default_language'] : 'en';

// Check if user has set a language preference
if (isset($_COOKIE['bgg_language'])) {
    $current_language = $_COOKIE['bgg_language'];
}

// Load translations
$translations = [];
$language_file = __DIR__ . '/../languages/' . $current_language . '.php';

if (file_exists($language_file)) {
    $translations = require $language_file;
} else {
    // Fallback to English if selected language not found
    $fallback_file = __DIR__ . '/../languages/en.php';
    if (file_exists($fallback_file)) {
        $translations = require $fallback_file;
    }
}

/**
 * Translate a string
 * 
 * @param string $key Translation key
 * @param array $replacements Optional array of replacements for placeholders
 * @return string Translated string
 */
function t($key, $replacements = []) {
    global $translations;
    
    // Get translation or return key if not found
    $translation = $translations[$key] ?? $key;
    
    // Replace placeholders
    if (!empty($replacements)) {
        foreach ($replacements as $placeholder => $value) {
            $translation = str_replace('{' . $placeholder . '}', $value, $translation);
        }
    }
    
    return $translation;
}

/**
 * Get available languages by scanning the languages directory
 * 
 * @return array Array of language codes and names
 */
function get_available_languages() {
    $languages = [];
    $language_dir = __DIR__ . '/../languages';
    
    if (is_dir($language_dir)) {
        $files = scandir($language_dir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $code = pathinfo($file, PATHINFO_FILENAME);
                $lang_data = require $language_dir . '/' . $file;
                $languages[$code] = $lang_data['_language_name'] ?? strtoupper($code);
            }
        }
    }
    
    return $languages;
}

/**
 * Set user's language preference
 * 
 * @param string $language_code Language code (e.g., 'en', 'pl')
 */
function set_language($language_code) {
    global $current_language, $translations;
    
    $language_file = __DIR__ . '/../languages/' . $language_code . '.php';
    
    if (file_exists($language_file)) {
        $current_language = $language_code;
        $translations = require $language_file;
        
        // Set cookie for 1 year
        setcookie('bgg_language', $language_code, time() + (365 * 24 * 60 * 60), '/');
        
        return true;
    }
    
    return false;
}

/**
 * Get current language code
 * 
 * @return string Current language code
 */
function get_current_language() {
    global $current_language;
    return $current_language;
}

/**
 * Format a date according to the current language
 * 
 * @param string $date Date string (any format accepted by strtotime)
 * @param string $format Format string ('short', 'long', 'full', or custom)
 * @return string Formatted date
 */
function format_date($date, $format = 'long') {
    global $translations, $current_language;
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    
    if ($timestamp === false) {
        return $date; // Return original if parsing fails
    }
    
    // Get date components
    $day_num = date('j', $timestamp);      // Day of month (1-31)
    $month_num = date('n', $timestamp);    // Month (1-12)
    $year = date('Y', $timestamp);         // Year
    $day_of_week = date('w', $timestamp);  // Day of week (0=Sunday, 6=Saturday)
    
    // Get localized month names
    $months_short = $translations['_months_short'] ?? [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
    ];
    
    $months_full = $translations['_months_full'] ?? [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    
    $days_full = $translations['_days_full'] ?? [
        0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
        4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'
    ];
    
    // Format based on requested format
    switch ($format) {
        case 'short':
            // "Jan 15" or "15 sty" (Polish format)
            if ($current_language === 'pl') {
                return $day_num . ' ' . $months_short[$month_num];
            }
            return $months_short[$month_num] . ' ' . $day_num;
            
        case 'long':
            // "January 15, 2024" or "15 stycznia 2024" (Polish format)
            if ($current_language === 'pl') {
                return $day_num . ' ' . $months_full[$month_num] . ' ' . $year;
            }
            return $months_full[$month_num] . ' ' . $day_num . ', ' . $year;
            
        case 'full':
            // "Monday, January 15, 2024" or "poniedziałek, 15 stycznia 2024" (Polish format)
            if ($current_language === 'pl') {
                return $days_full[$day_of_week] . ', ' . $day_num . ' ' . $months_full[$month_num] . ' ' . $year;
            }
            return $days_full[$day_of_week] . ', ' . $months_full[$month_num] . ' ' . $day_num . ', ' . $year;
            
        default:
            // Custom format - just use date() for now
            return date($format, $timestamp);
    }
}