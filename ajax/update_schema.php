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

// Load helper functions
require_once '../includes/http_helper.php';
require_once '../includes/log_helper.php';
require_once '../includes/schema_helper.php';


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
