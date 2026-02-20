<?php
/**
 * HTTP Helper Functions
 * 
 * Consolidates duplicate fetch_url() implementations from:
 * - install.php
 * - update.php  
 * - auto_update.php
 * - ajax/update_config.php
 * - ajax/update_schema.php
 * 
 * Use: require_once 'includes/http_helper.php';
 */

/**
 * Fetch URL content - tries cURL first, then file_get_contents
 * 
 * @param string $url URL to fetch
 * @param string|null $github_token Optional GitHub personal access token
 * @return string|false Content on success, false on failure
 */
function fetch_url($url, $github_token = null) {
    // Try to get GitHub token from constant if not provided
    if (!$github_token && defined('GITHUB_TOKEN')) {
        $github_token = GITHUB_TOKEN;
    }
    
    // Try cURL first
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'BGG-Signup-System');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Add GitHub token if available
        if ($github_token) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: token ' . $github_token,
                'User-Agent: BGG-Signup-System'
            ]);
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response !== false && $http_code === 200) {
            return $response;
        }
        
        // Log error if logging function exists
        if (function_exists('add_log')) {
            add_log("cURL failed: HTTP $http_code - $error", 'ERROR');
        }
        
        // Check for rate limit
        if ($http_code === 403) {
            if (function_exists('add_log')) {
                add_log("GitHub API rate limit exceeded. Wait an hour or use a GitHub token.", 'WARNING');
            }
        }
    }
    
    // Fall back to file_get_contents
    if (ini_get('allow_url_fopen')) {
        $headers = ['User-Agent: BGG-Signup-System'];
        
        // Add GitHub token if available
        if ($github_token) {
            $headers[] = 'Authorization: token ' . $github_token;
        }
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 30
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response !== false) {
            return $response;
        }
        
        $error = error_get_last();
        if (function_exists('add_log')) {
            add_log("file_get_contents failed: " . ($error['message'] ?? 'Unknown error'), 'ERROR');
        }
    } else {
        if (function_exists('add_log')) {
            add_log("allow_url_fopen is disabled in php.ini", 'ERROR');
        }
    }
    
    return false;
}

/**
 * Fetch JSON from URL and decode
 * 
 * @param string $url URL to fetch
 * @param string|null $github_token Optional GitHub personal access token
 * @return array|null Decoded JSON on success, null on failure
 */
function fetch_json($url, $github_token = null) {
    $response = fetch_url($url, $github_token);
    
    if ($response === false) {
        return null;
    }
    
    $json = json_decode($response, true);
    
    if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
        if (function_exists('add_log')) {
            add_log("JSON decode error: " . json_last_error_msg(), 'ERROR');
        }
        return null;
    }
    
    return $json;
}
