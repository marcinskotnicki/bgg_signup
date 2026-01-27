<?php
/**
 * Translation System for BGG Signup
 * 
 * Simple translation function that loads language files from /languages/ directory
 */

// Load configuration
$config = require_once __DIR__ . '/../config.php';

// Current language (can be overridden by user preference or cookie)
$current_language = $config['default_language'];

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
?>