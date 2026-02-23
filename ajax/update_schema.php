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
    // Load local schema (don't fetch from GitHub - use local file)
    define('SCHEMA_ACCESS', true);
    require_once '../schema.php';
    
    $log[] = ['level' => 'INFO', 'message' => 'Loaded schema from schema.php'];
    
    // Get expected schema
    $expected_tables = get_database_schema();
    $expected_columns = get_schema_migrations();
    
    // Step 1: Get current schema
    $current_schema = get_current_schema($db);
    
    $changes = [
        'tables_added' => [],
        'columns_added' => [],
        'errors' => []
    ];
    
    // Step 2: Check for missing tables
    foreach ($expected_tables as $table_name => $create_sql) {
        if (!isset($current_schema[$table_name])) {
            try {
                $db->exec($create_sql);
                $changes['tables_added'][] = $table_name;
                $log[] = ['level' => 'SUCCESS', 'message' => "Created table: $table_name"];
            } catch (PDOException $e) {
                $changes['errors'][] = "Failed to create table $table_name: " . $e->getMessage();
                $log[] = ['level' => 'ERROR', 'message' => "Failed to create table $table_name: " . $e->getMessage()];
            }
        }
    }
    
    // Step 3: Check for missing columns
    foreach ($expected_columns as $table_name => $columns) {
        if (!isset($current_schema[$table_name])) {
            continue; // Table doesn't exist yet, skip
        }
        
        $existing_columns = array_keys($current_schema[$table_name]['columns']);
        
        foreach ($columns as $column_name => $alter_sql) {
            if (!in_array($column_name, $existing_columns)) {
                try {
                    $db->exec($alter_sql);
                    $changes['columns_added'][] = "$table_name.$column_name";
                    $log[] = ['level' => 'SUCCESS', 'message' => "Added column '$column_name' to table '$table_name'"];
                    
                    // Generate verification codes for existing non-logged-in records
                    if ($column_name === 'verification_code') {
                        if ($table_name === 'players') {
                            $stmt = $db->query("SELECT id FROM players WHERE user_id IS NULL AND (verification_code IS NULL OR verification_code = '')");
                            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (count($records) > 0) {
                                $update = $db->prepare("UPDATE players SET verification_code = ? WHERE id = ?");
                                foreach ($records as $record) {
                                    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                                    $update->execute([$code, $record['id']]);
                                }
                                $log[] = ['level' => 'INFO', 'message' => "Generated verification codes for " . count($records) . " players"];
                            }
                        } elseif ($table_name === 'polls') {
                            $stmt = $db->query("SELECT id FROM polls WHERE created_by_user_id IS NULL AND (verification_code IS NULL OR verification_code = '')");
                            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (count($records) > 0) {
                                $update = $db->prepare("UPDATE polls SET verification_code = ? WHERE id = ?");
                                foreach ($records as $record) {
                                    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                                    $update->execute([$code, $record['id']]);
                                }
                                $log[] = ['level' => 'INFO', 'message' => "Generated verification codes for " . count($records) . " polls"];
                            }
                        }
                    }
                } catch (PDOException $e) {
                    $changes['errors'][] = "Failed to add column $table_name.$column_name: " . $e->getMessage();
                    $log[] = ['level' => 'ERROR', 'message' => "Failed to add column $table_name.$column_name: " . $e->getMessage()];
                }
            }
        }
    }
    
    // Summary
    $total_changes = count($changes['tables_added']) + count($changes['columns_added']);
    if ($total_changes > 0) {
        $log[] = ['level' => 'SUCCESS', 'message' => "Database schema updated! ($total_changes changes made)"];
        echo json_encode([
            'success' => true,
            'changes' => $changes,
            'log' => $log
        ]);
    } else {
        $log[] = ['level' => 'INFO', 'message' => 'Database schema is up to date'];
        echo json_encode([
            'success' => true,
            'changes' => $changes,
            'log' => $log
        ]);
    }
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
