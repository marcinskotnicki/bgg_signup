<?php
/**
 * BoardGameGeek API Helper
 * 
 * Handles BGG API requests with caching
 * BGG API Documentation: https://boardgamegeek.com/wiki/page/BGG_XML_API2
 */

/**
 * Fetch URL from BGG API - tries cURL first, then file_get_contents
 * 
 * @param string $url URL to fetch
 * @return string|false Content or false on failure
 */
function fetch_bgg_url($url) {
    // Try cURL first (most reliable)
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'BGG-Signup-System/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response !== false && $http_code === 200) {
            return $response;
        }
        
        // Log the error for debugging
        error_log("BGG API cURL failed: HTTP $http_code - $error - URL: $url");
        
        // If cURL failed, try file_get_contents
    }
    
    // Fall back to file_get_contents
    if (ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: BGG-Signup-System/1.0',
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
        error_log("BGG API file_get_contents failed: " . ($error['message'] ?? 'Unknown error') . " - URL: $url");
    } else {
        error_log("BGG API: allow_url_fopen is disabled in php.ini");
    }
    
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
        return $cached;
    }
    
    // Make API request
    $query_encoded = urlencode($query);
    $url = "https://boardgamegeek.com/xmlapi2/search?query={$query_encoded}&type=boardgame";
    
    $xml = fetch_bgg_url($url);
    
    if ($xml === false) {
        error_log("BGG API search failed for query: $query");
        return ['error' => 'Failed to connect to BoardGameGeek API. Please try again later.'];
    }
    
    // Parse XML
    $xml_obj = @simplexml_load_string($xml);
    
    if ($xml_obj === false) {
        return ['error' => 'Failed to parse BGG response'];
    }
    
    $results = [];
    
    // Check if there are any items
    if (isset($xml_obj->item)) {
        foreach ($xml_obj->item as $item) {
            $results[] = [
                'id' => (int)$item['id'],
                'name' => (string)$item->name['value'],
                'year' => isset($item->yearpublished) ? (int)$item->yearpublished['value'] : null
            ];
        }
    }
    
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
        return $cached;
    }
    
    // Make API request
    $url = "https://boardgamegeek.com/xmlapi2/thing?id={$game_id}&stats=1";
    
    $xml = fetch_bgg_url($url);
    
    if ($xml === false) {
        error_log("BGG API details failed for game ID: $game_id");
        return null;
    }
    
    // Parse XML
    $xml_obj = @simplexml_load_string($xml);
    
    if ($xml_obj === false || !isset($xml_obj->item)) {
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
    $thumbnail_dir = THUMBNAILS_DIR;
    
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