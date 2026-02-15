<?php
/**
 * BoardGameGeek API Helper
 * 
 * Handles BGG API requests with caching
 * BGG API Documentation: https://boardgamegeek.com/wiki/page/BGG_XML_API2
 */

/**
 * Fetch URL from BGG API - uses working cURL configuration
 * 
 * @param string $url URL to fetch
 * @param string $api_token Optional BGG API token
 * @return string|false Content or false on failure
 */
function fetch_bgg_url($url, $api_token = '') {
    error_log("BGG API: Attempting to fetch URL: $url");
    
    // Try cURL (most reliable)
    if (function_exists('curl_init')) {
        error_log("BGG API: Using cURL");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'BGG-Signup-System/1.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        // Add Authorization header if token provided
        if (!empty($api_token)) {
            $headers = ["Authorization: Bearer $api_token"];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            error_log("BGG API: Using API token");
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        curl_close($ch);
        
        error_log("BGG API: cURL response - HTTP Code: $http_code, cURL Error: $curl_error, Errno: $curl_errno");
        
        // Check if request failed
        if ($response === false) {
            error_log("BGG API: cURL exec failed - Error: $curl_error (Errno: $curl_errno)");
            return false;
        }
        
        // Check HTTP response code
        if ($http_code != 200) {
            error_log("BGG API: HTTP code $http_code (expected 200)");
            error_log("BGG API: Response preview: " . substr($response, 0, 200));
            return false;
        }
        
        // Check if data is not empty
        if (empty($response)) {
            error_log("BGG API: Empty response received");
            return false;
        }
        
        error_log("BGG API: Success! Response length: " . strlen($response));
        return $response;
    }
    
    error_log("BGG API: cURL not available");
    
    // Fall back to file_get_contents
    if (ini_get('allow_url_fopen')) {
        error_log("BGG API: Trying file_get_contents");
        
        $context_options = [
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: BGG-Signup-System/1.0',
                'timeout' => 30
            ]
        ];
        
        // Add Authorization header if token provided
        if (!empty($api_token)) {
            $context_options['http']['header'] .= "\r\nAuthorization: Bearer $api_token";
        }
        
        $context = stream_context_create($context_options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response !== false && !empty($response)) {
            error_log("BGG API: file_get_contents success! Response length: " . strlen($response));
            return $response;
        }
        
        $error = error_get_last();
        error_log("BGG API file_get_contents failed: " . ($error['message'] ?? 'Unknown error'));
    } else {
        error_log("BGG API: allow_url_fopen is DISABLED");
    }
    
    error_log("BGG API: ALL methods failed");
    return false;
}

/**
 * Clean expired cache entries (older than 1 week)
 * 
 * @param PDO $db Database connection
 */
function clean_bgg_cache($db) {
    $config = require __DIR__ . '/../config.php';
    $cache_duration = $config['bgg_cache_duration'];
    
    $cutoff = date('Y-m-d H:i:s', time() - $cache_duration);
    $stmt = $db->prepare("DELETE FROM bgg_cache WHERE cached_at < ?");
    $stmt->execute([$cutoff]);
}

/**
 * Get from cache or return null
 * 
 * @param PDO $db Database connection
 * @param string $cache_key Cache key
 * @return mixed|null Cached data or null if not found/expired
 */
function get_from_cache($db, $cache_key) {
    $config = require __DIR__ . '/../config.php';
    $cache_duration = $config['bgg_cache_duration'];
    
    $stmt = $db->prepare("SELECT cache_data, cached_at FROM bgg_cache WHERE cache_key = ?");
    $stmt->execute([$cache_key]);
    $cached = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cached) {
        return null;
    }
    
    // Check if expired
    $cached_time = strtotime($cached['cached_at']);
    if (time() - $cached_time > $cache_duration) {
        // Delete expired entry
        $stmt = $db->prepare("DELETE FROM bgg_cache WHERE cache_key = ?");
        $stmt->execute([$cache_key]);
        return null;
    }
    
    return json_decode($cached['cache_data'], true);
}

/**
 * Save to cache
 * 
 * @param PDO $db Database connection
 * @param string $cache_key Cache key
 * @param mixed $data Data to cache
 */
function save_to_cache($db, $cache_key, $data) {
    $cache_data = json_encode($data);
    $stmt = $db->prepare("INSERT OR REPLACE INTO bgg_cache (cache_key, cache_data, cached_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
    $stmt->execute([$cache_key, $cache_data]);
}

/**
 * Search for games on BoardGameGeek
 * 
 * @param PDO $db Database connection
 * @param string $query Search query
 * @return array Array of search results
 */
function search_bgg_games($db, $query) {
    // Clean old cache periodically (10% chance)
    if (rand(1, 10) === 1) {
        clean_bgg_cache($db);
    }
    
    // Check cache first
    $cache_key = 'search_' . md5(strtolower(trim($query)));
    $cached = get_from_cache($db, $cache_key);
    
    if ($cached !== null) {
        error_log("BGG API: Using cached search results for: $query");
        return $cached;
    }
    
    // Get API token from config
    $config = require __DIR__ . '/../config.php';
    $api_token = isset($config['bgg_api_token']) ? $config['bgg_api_token'] : '';
    
    // Make API request (exclude expansions)
    $query_encoded = urlencode($query);
    $url = "https://boardgamegeek.com/xmlapi2/search?type=boardgame&excludesubtype=boardgameexpansion&query={$query_encoded}";
    
    error_log("BGG API: Searching for: $query");
    $xml = fetch_bgg_url($url, $api_token);
    
    if ($xml === false) {
        error_log("BGG API search failed for query: $query");
        return ['error' => 'Failed to connect to BoardGameGeek API. Please try again later.'];
    }
    
    // Validate and parse XML
    libxml_use_internal_errors(true);
    $xml_obj = simplexml_load_string($xml);
    
    if ($xml_obj === false) {
        $errors = libxml_get_errors();
        $error_messages = [];
        foreach ($errors as $error) {
            $error_messages[] = trim($error->message);
        }
        libxml_clear_errors();
        error_log("BGG API: XML parsing errors: " . implode(', ', $error_messages));
        return ['error' => 'Failed to parse BGG response'];
    }
    
    $results = [];
    
    // Check if there are any items
    if (isset($xml_obj->item)) {
        foreach ($xml_obj->item as $item) {
            // Only include board games (should already be filtered by API, but double-check)
            if ((string)$item['type'] === 'boardgame') {
                $results[] = [
                    'id' => (int)$item['id'],
                    'name' => (string)$item->name['value'],
                    'year' => isset($item->yearpublished) ? (int)$item->yearpublished['value'] : null
                ];
            }
        }
    }
    
    error_log("BGG API: Found " . count($results) . " results for: $query");
    
    // Cache the results
    save_to_cache($db, $cache_key, $results);
    
    return $results;
}

/**
 * Get detailed game information from BoardGameGeek by ID
 * 
 * @param PDO $db Database connection
 * @param int $game_id BGG game ID
 * @return array|null Game details or null if not found
 */
function get_bgg_game_details($db, $game_id) {
    // Clean old cache periodically (10% chance)
    if (rand(1, 10) === 1) {
        clean_bgg_cache($db);
    }
    
    // Check cache first
    $cache_key = 'game_' . $game_id;
    $cached = get_from_cache($db, $cache_key);
    
    if ($cached !== null) {
        error_log("BGG API: Using cached game details for ID: $game_id");
        return $cached;
    }
    
    // Get API token from config
    $config = require __DIR__ . '/../config.php';
    $api_token = isset($config['bgg_api_token']) ? $config['bgg_api_token'] : '';
    
    // Make API request
    $url = "https://boardgamegeek.com/xmlapi2/thing?id={$game_id}&stats=1";
    
    error_log("BGG API: Fetching details for game ID: $game_id");
    $xml = fetch_bgg_url($url, $api_token);
    
    if ($xml === false) {
        error_log("BGG API details failed for game ID: $game_id");
        return null;
    }
    
    // Validate and parse XML
    libxml_use_internal_errors(true);
    $xml_obj = simplexml_load_string($xml);
    
    if ($xml_obj === false || !isset($xml_obj->item)) {
        $errors = libxml_get_errors();
        if ($errors) {
            $error_messages = [];
            foreach ($errors as $error) {
                $error_messages[] = trim($error->message);
            }
            libxml_clear_errors();
            error_log("BGG API: XML parsing errors for game $game_id: " . implode(', ', $error_messages));
        }
        return null;
    }
    
    $item = $xml_obj->item;
    
    // Extract game details
    $game_details = [
        'id' => (int)$item['id'],
        'name' => '',
        'thumbnail' => '',
        'image' => '',
        'year' => null,
        'min_players' => 1,
        'max_players' => 1,
        'play_time' => 0,
        'min_play_time' => 0,
        'max_play_time' => 0,
        'min_age' => 0,
        'description' => '',
        'difficulty' => 0.0, // BGG weight
        'url' => "https://boardgamegeek.com/boardgame/{$game_id}"
    ];
    
    // Get primary name
    foreach ($item->name as $name) {
        if ((string)$name['type'] === 'primary') {
            $game_details['name'] = (string)$name['value'];
            break;
        }
    }
    
    // Get thumbnail and image
    if (isset($item->thumbnail)) {
        $game_details['thumbnail'] = (string)$item->thumbnail;
    }
    if (isset($item->image)) {
        $game_details['image'] = (string)$item->image;
    }
    
    // Get year published
    if (isset($item->yearpublished)) {
        $game_details['year'] = (int)$item->yearpublished['value'];
    }
    
    // Get player counts
    if (isset($item->minplayers)) {
        $game_details['min_players'] = (int)$item->minplayers['value'];
    }
    if (isset($item->maxplayers)) {
        $game_details['max_players'] = (int)$item->maxplayers['value'];
    }
    
    // Get play times
    if (isset($item->playingtime)) {
        $game_details['play_time'] = (int)$item->playingtime['value'];
    }
    if (isset($item->minplaytime)) {
        $game_details['min_play_time'] = (int)$item->minplaytime['value'];
    }
    if (isset($item->maxplaytime)) {
        $game_details['max_play_time'] = (int)$item->maxplaytime['value'];
    }
    
    // Get minimum age
    if (isset($item->minage)) {
        $game_details['min_age'] = (int)$item->minage['value'];
    }
    
    // Get description
    if (isset($item->description)) {
        $game_details['description'] = (string)$item->description;
    }
    
    // Get difficulty (average weight)
    if (isset($item->statistics->ratings->averageweight)) {
        $game_details['difficulty'] = (float)$item->statistics->ratings->averageweight['value'];
    }
    
    error_log("BGG API: Successfully fetched details for: " . $game_details['name'] . " (ID: $game_id)");
    
    // Cache the results
    save_to_cache($db, $cache_key, $game_details);
    
    return $game_details;
}

/**
 * Format difficulty level for display
 * 
 * @param float $difficulty Difficulty level (1-5)
 * @return array Array with 'level' (text) and 'color' (CSS color)
 */
function format_difficulty($difficulty) {
    if ($difficulty < 2.0) {
        return [
            'level' => 'Light',
            'color' => '#90EE90', // Light green
            'percentage' => ($difficulty / 5) * 100
        ];
    } elseif ($difficulty < 3.0) {
        return [
            'level' => 'Medium Light',
            'color' => '#228B22', // Dark green
            'percentage' => ($difficulty / 5) * 100
        ];
    } elseif ($difficulty < 4.0) {
        return [
            'level' => 'Medium',
            'color' => '#FFA500', // Orange
            'percentage' => ($difficulty / 5) * 100
        ];
    } elseif ($difficulty < 4.5) {
        return [
            'level' => 'Medium Heavy',
            'color' => '#FF4500', // Red
            'percentage' => ($difficulty / 5) * 100
        ];
    } else {
        return [
            'level' => 'Heavy',
            'color' => '#8B0000', // Dark red
            'percentage' => ($difficulty / 5) * 100
        ];
    }
}

/**
 * Get available custom thumbnails from thumbnails directory
 * 
 * @return array Array of thumbnail filenames
 */
function get_custom_thumbnails() {
    $thumbnails = [];
    
    // Determine the correct path to thumbnails directory
    // If called from ajax/, we need to go up one level
    $thumbnail_dir = THUMBNAILS_DIR;
    
    // Check if we need to adjust path (if called from subdirectory)
    if (!is_dir($thumbnail_dir)) {
        $thumbnail_dir = '../' . THUMBNAILS_DIR;
    }
    
    if (is_dir($thumbnail_dir)) {
        $files = scandir($thumbnail_dir);
        foreach ($files as $file) {
            if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $thumbnails[] = $file;
            }
        }
    }
    
    return $thumbnails;
}
?>