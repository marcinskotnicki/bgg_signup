<?php
/**
 * AJAX Handler: Update Database Schema
 * Automatically detects and applies missing database columns
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
    $log_file = UPDATE_LOG_DIR . '/schema_update.log';
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
        curl_setopt($ch, CURLOPT_USERAGENT, 'BGG-Signup-Schema-Update');
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
                'header' => 'User-Agent: BGG-Signup-Schema-Update',
                'timeout' => 30
            ]
        ]);
        
        return @file_get_contents($url, false, $context);
    }
    
    return false;
}

/**
 * Parse CREATE TABLE statements from install.php
 */
function parse_schema_from_github() {
    add_log("Fetching install.php from GitHub...");
    
    $url = GITHUB_RAW . 'install.php';
    $content = fetch_url($url);
    
    if (!$content) {
        add_log("Failed to fetch install.php from GitHub", "ERROR");
        return false;
    }
    
    add_log("Parsing schema definitions...");
    
    $schema = [];
    
    // Match all CREATE TABLE statements from install.php
    // Use greedy .* to capture everything up to the LAST ) before ");
    preg_match_all('/CREATE TABLE IF NOT EXISTS (\w+)\s*\((.*)\)"\);/s', $content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $table_name = $match[1];
        $table_def = trim($match[2]);
        
        // Parse columns
        $columns = [];
        $lines = explode("\n", $table_def);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip FOREIGN KEY constraints
            if (stripos($line, 'FOREIGN KEY') !== false) {
                continue;
            }
            
            // Match column definition
            if (preg_match('/^(\w+)\s+(\w+)/', $line, $col_match)) {
                $col_name = $col_match[1];
                
                // Skip PRIMARY KEY line
                if (strtoupper($col_name) === 'PRIMARY') {
                    continue;
                }
                
                $columns[$col_name] = [
                    'definition' => rtrim($line, ','),
                    'type' => $col_match[2]
                ];
            }
        }
        
        // Store both columns and full CREATE TABLE statement
        $schema[$table_name] = [
            'columns' => $columns,
            'create_sql' => "CREATE TABLE IF NOT EXISTS $table_name ($table_def)"
        ];
    }
    
    add_log("Parsed " . count($schema) . " tables from GitHub schema");
    
    return $schema;
}

/**
 * Get current database schema
 */
function get_current_schema($db) {
    add_log("Reading current database schema...");
    
    $schema = [];
    
    // Get all tables
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        $columns = [];
        
        // Get table info
        $table_info = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($table_info as $col) {
            $columns[$col['name']] = [
                'type' => $col['type'],
                'notnull' => $col['notnull'],
                'dflt_value' => $col['dflt_value'],
                'pk' => $col['pk']
            ];
        }
        
        $schema[$table] = $columns;
    }
    
    add_log("Read " . count($schema) . " tables from current database");
    
    return $schema;
}

/**
 * Compare schemas and generate ALTER TABLE statements
 */
function detect_schema_changes($github_schema, $current_schema) {
    add_log("Comparing schemas to detect changes...");
    
    $changes = [];
    
    foreach ($github_schema as $table => $github_data) {
        // Handle missing tables
        if (!isset($current_schema[$table])) {
            add_log("Table $table is missing - will create it", "WARNING");
            $changes[] = [
                'table' => $table,
                'action' => 'CREATE_TABLE',
                'sql' => $github_data['create_sql']
            ];
            continue;
        }
        
        $github_cols = $github_data['columns'];
        $current_cols = $current_schema[$table];
        
        // Check for missing columns
        foreach ($github_cols as $col_name => $col_def) {
            if (!isset($current_cols[$col_name])) {
                $changes[] = [
                    'table' => $table,
                    'column' => $col_name,
                    'action' => 'ADD',
                    'definition' => $col_def['definition'],
                    'sql' => "ALTER TABLE $table ADD COLUMN " . $col_def['definition']
                ];
                
                add_log("Detected missing column: $table.$col_name");
            }
        }
    }
    
    add_log("Found " . count($changes) . " schema change(s)");
    
    return $changes;
}

/**
 * Apply schema changes
 */
function apply_schema_changes($db, $changes) {
    if (empty($changes)) {
        add_log("No schema changes to apply");
        return true;
    }
    
    add_log("Applying " . count($changes) . " schema change(s)...");
    
    // Backup database first
    $backup_dir = '../backup';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $backup_file = $backup_dir . '/database_' . date('Y-m-d_His') . '.db';
    
    if (copy('../' . DB_FILE, $backup_file)) {
        add_log("Created backup: $backup_file", "SUCCESS");
    } else {
        add_log("Failed to create backup!", "ERROR");
        return false;
    }
    
    try {
        $db->beginTransaction();
        
        foreach ($changes as $change) {
            add_log("Applying: " . $change['sql']);
            
            try {
                $db->exec($change['sql']);
                
                if ($change['action'] === 'CREATE_TABLE') {
                    add_log("✓ Successfully created table: {$change['table']}", "SUCCESS");
                } else {
                    add_log("✓ Successfully added {$change['table']}.{$change['column']}", "SUCCESS");
                }
            } catch (PDOException $e) {
                if ($change['action'] === 'CREATE_TABLE') {
                    add_log("✗ Failed to create table {$change['table']}: " . $e->getMessage(), "ERROR");
                } else {
                    add_log("✗ Failed to add {$change['table']}.{$change['column']}: " . $e->getMessage(), "ERROR");
                }
                throw $e;
            }
        }
        
        $db->commit();
        add_log("All schema changes applied successfully!", "SUCCESS");
        return true;
        
    } catch (Exception $e) {
        $db->rollBack();
        add_log("Schema update failed, rolled back: " . $e->getMessage(), "ERROR");
        
        // Restore from backup
        if (file_exists($backup_file)) {
            copy($backup_file, '../' . DB_FILE);
            add_log("Restored database from backup", "INFO");
        }
        
        return false;
    }
}

// Main execution
try {
    // Step 1: Fetch GitHub schema
    $github_schema = parse_schema_from_github();
    
    if (!$github_schema) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch schema from GitHub',
            'log' => $log
        ]);
        exit;
    }
    
    // Step 2: Get current schema
    $current_schema = get_current_schema($db);
    
    // Step 3: Detect changes
    $changes = detect_schema_changes($github_schema, $current_schema);
    
    // Step 4: Apply changes
    if (apply_schema_changes($db, $changes)) {
        echo json_encode([
            'success' => true,
            'changes' => $changes,
            'log' => $log
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to apply schema changes',
            'changes' => $changes,
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
