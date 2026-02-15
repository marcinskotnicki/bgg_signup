<?php
/**
 * AJAX Handler: Update Config File
 * Merges new config constants while preserving user values
 */

header('Content-Type: application/json');

// Load configuration
$config = require_once '../config.php';

// Load auth helper
require_once '../includes/auth.php';

// Database connection
try {
    $db = new PDO('sqlite:../' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed',
        'log' => [['level' => 'ERROR', 'message' => 'Database connection failed']]
    ]);
    exit;
}

// Check if user is admin
$current_user = get_current_user($db);
if (!$current_user || !$current_user['is_admin']) {
    echo json_encode([
        'success' => false,
        'error' => 'Admin access required',
        'log' => [['level' => 'ERROR', 'message' => 'Admin access required']]
    ]);
    exit;
}

// Configuration
define('GITHUB_RAW', 'https://raw.githubusercontent.com/marcinskotnicki/bgg_signup/main/');
define('UPDATE_LOG_DIR', '../logs');

$log = [];

/**
 * Add log entry
 */
function add_log($message, $level = 'INFO') {
    global $log;
    $log[] = [
        'level' => $level,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Also log to file
    if (!is_dir(UPDATE_LOG_DIR)) {
        mkdir(UPDATE_LOG_DIR, 0755, true);
    }
    $log_file = UPDATE_LOG_DIR . '/config_update.log';
    $log_entry = "[" . date('Y-m-d H:i:s') . "] [$level] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Fetch URL with error handling
 */
function fetch_url($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'BGG-Signup-Config-Update');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response !== false && $http_code === 200) {
            return $response;
        }
    }
    
    if (ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: BGG-Signup-Config-Update',
                'timeout' => 30
            ]
        ]);
        
        return @file_get_contents($url, false, $context);
    }
    
    return false;
}

/**
 * Parse config file to extract define() constants
 */
function parse_config_array($content) {
    $config_values = [];
    
    // Extract the return array content
    // Look for: return [ ... ];
    if (preg_match('/return\s*\[(.*)\];/s', $content, $match)) {
        $array_content = $match[1];
        
        // Match all key => value pairs
        // Pattern handles: 'key' => 'value', 'key' => true, 'key' => 123, etc.
        preg_match_all(
            "/['\"]([^'\"]+)['\"]\s*=>\s*(.+?)(?=,\s*['\"]|\s*\])/s",
            $array_content,
            $matches,
            PREG_SET_ORDER
        );
        
        foreach ($matches as $match) {
            $key = $match[1];
            $value = trim($match[2]);
            // Remove trailing comma if present
            $value = rtrim($value, ',');
            
            $config_values[$key] = [
                'value' => $value,
                'key' => $key
            ];
        }
    }
    
    return $config_values;
}

/**
 * Merge current config values into new template
 */
function merge_configs($current_content, $new_content) {
    add_log("Starting config merge...");
    
    // Parse both configs to extract values
    $current_values = parse_config_values($current_content);
    $new_values = parse_config_values($new_content);
    
    add_log("Current config has " . count($current_values['defines']) . " constants and " . count($current_values['array']) . " array settings");
    add_log("New template has " . count($new_values['defines']) . " constants and " . count($new_values['array']) . " array settings");
    
    // Start with new template
    $merged_content = $new_content;
    
    // Merge defines (constants)
    foreach ($new_values['defines'] as $const_name => $new_data) {
        if (isset($current_values['defines'][$const_name])) {
            // Preserve user's value
            $user_value = $current_values['defines'][$const_name]['value'];
            $old_line = $new_data['full_statement'];
            $new_line = "    define('$const_name', $user_value);";
            
            $merged_content = str_replace($old_line, $new_line, $merged_content);
            add_log("Preserved constant: $const_name", "SUCCESS");
        } else {
            add_log("New constant: $const_name (using default)", "INFO");
        }
    }
    
    // Merge array values
    foreach ($new_values['array'] as $key => $new_data) {
        if (isset($current_values['array'][$key])) {
            // Preserve user's value
            $user_value = $current_values['array'][$key]['value'];
            $old_line = $new_data['full_statement'];
            
            // Rebuild the line with user's value
            $new_line = "    '$key' => $user_value,";
            
            $merged_content = str_replace($old_line, $new_line, $merged_content);
            add_log("Preserved setting: $key", "SUCCESS");
        } else {
            add_log("New setting: $key (using default)", "INFO");
        }
    }
    
    add_log("Config merge completed successfully");
    return $merged_content;
}

/**
 * Parse config file to extract both defines and array values
 */
function parse_config_values($content) {
    $defines = [];
    $array = [];
    
    // Parse define() statements
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        
        // Match: define('KEY', value);
        if (preg_match("/define\('([^']+)',\s*(.+?)\);/", $trimmed, $matches)) {
            $const_name = $matches[1];
            $value = $matches[2];
            
            $defines[$const_name] = [
                'value' => $value,
                'full_statement' => $trimmed
            ];
        }
        
        // Match: 'key' => value, (with optional trailing comma and whitespace)
        // This handles both middle items (with comma) and last item (without comma)
        if (preg_match("/'([^']+)'\s*=>\s*(.+?)(?:,\s*(?:\/\/.*)?)?$/", $trimmed, $matches)) {
            $key = $matches[1];
            $value = rtrim($matches[2], ', '); // Remove any trailing comma and spaces
            
            $array[$key] = [
                'value' => $value,
                'full_statement' => $trimmed
            ];
        }
    }
    
    return [
        'defines' => $defines,
        'array' => $array
    ];
}

/**
 * Update config file
 */
function update_config_file() {
    add_log("Starting config file update...");
    
    // Fetch new config template from GitHub
    add_log("Fetching config.php from GitHub...");
    $url = GITHUB_RAW . 'config.php';
    $new_config_content = fetch_url($url);
    
    if (!$new_config_content) {
        add_log("Failed to fetch config.php from GitHub", "ERROR");
        return false;
    }
    
    add_log("Successfully fetched new config template");
    
    // Read current config
    $config_file = '../config.php';
    if (!file_exists($config_file)) {
        add_log("Current config.php not found!", "ERROR");
        return false;
    }
    
    $current_config_content = file_get_contents($config_file);
    add_log("Read current config.php");
    
    // Create backup
    $backup_dir = '../backup';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $backup_file = $backup_dir . '/config_' . date('Y-m-d_His') . '.php';
    if (copy($config_file, $backup_file)) {
        add_log("Created backup: $backup_file", "SUCCESS");
    } else {
        add_log("Failed to create backup!", "ERROR");
        return false;
    }
    
    // Merge configs
    $merged_content = merge_configs($current_config_content, $new_config_content);
    
    // Write merged config
    if (file_put_contents($config_file, $merged_content)) {
        add_log("Successfully updated config.php", "SUCCESS");
        return true;
    } else {
        add_log("Failed to write config.php", "ERROR");
        
        // Restore from backup
        copy($backup_file, $config_file);
        add_log("Restored from backup", "INFO");
        
        return false;
    }
}

// Main execution
try {
    if (update_config_file()) {
        echo json_encode([
            'success' => true,
            'log' => $log
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Config update failed',
            'log' => $log
        ]);
    }
} catch (Exception $e) {
    add_log("Unexpected error: " . $e->getMessage(), "ERROR");
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'log' => $log
    ]);
}
?>
