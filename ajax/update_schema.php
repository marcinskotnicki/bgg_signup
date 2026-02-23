<?php
/**
 * AJAX Handler: Update Database Schema - ULTRA DEFENSIVE VERSION
 * This version aggressively suppresses all output until the final JSON
 */

// Suppress ALL errors and output
error_reporting(0);
ini_set('display_errors', 0);

// Start multiple levels of output buffering
ob_start();
ob_start();
ob_start();

try {
    // Load configuration
    require_once '../config.php';
    
    // Load auth helper
    require_once '../includes/auth.php';
    
    // Database connection
    $db = new PDO('sqlite:../' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if user is admin
    $current_user = get_current_user($db);
    if (!$current_user || !$current_user['is_admin']) {
        // Clean all buffers
        while (ob_get_level()) { ob_end_clean(); }
        
        header('Content-Type: application/json');
        die(json_encode([
            'success' => false,
            'error' => 'Admin access required',
            'log' => [['level' => 'ERROR', 'message' => 'Admin access required']]
        ]));
    }
    
    $log = [];
    
    // Load local schema
    if (!defined('SCHEMA_ACCESS')) {
        define('SCHEMA_ACCESS', true);
    }
    
    require_once '../schema.php';
    $log[] = ['level' => 'INFO', 'message' => 'Loaded schema from schema.php'];
    
    // Get expected schema
    $expected_tables = get_database_schema();
    $expected_columns = get_schema_migrations();
    
    // Load helper to get current schema
    require_once '../includes/schema_helper.php';
    $current_schema = get_current_schema($db);
    
    $changes = [
        'tables_added' => [],
        'columns_added' => [],
        'errors' => []
    ];
    
    // Check for missing tables
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
    
    // Check for missing columns
    foreach ($expected_columns as $table_name => $columns) {
        if (!isset($current_schema[$table_name])) {
            continue;
        }
        
        $existing_columns = array_keys($current_schema[$table_name]['columns']);
        
        foreach ($columns as $column_name => $alter_sql) {
            if (!in_array($column_name, $existing_columns)) {
                try {
                    $db->exec($alter_sql);
                    $changes['columns_added'][] = "$table_name.$column_name";
                    $log[] = ['level' => 'SUCCESS', 'message' => "Added column '$column_name' to table '$table_name'"];
                    
                    // Generate verification codes
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
                                $log[] = ['level' => 'INFO', 'message' => "Generated codes for " . count($records) . " players"];
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
                                $log[] = ['level' => 'INFO', 'message' => "Generated codes for " . count($records) . " polls"];
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
    } else {
        $log[] = ['level' => 'INFO', 'message' => 'Database schema is up to date'];
    }
    
    $response = [
        'success' => true,
        'changes' => $changes,
        'log' => $log
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'log' => [
            ['level' => 'ERROR', 'message' => 'Error: ' . $e->getMessage()],
            ['level' => 'ERROR', 'message' => 'File: ' . $e->getFile()],
            ['level' => 'ERROR', 'message' => 'Line: ' . $e->getLine()]
        ]
    ];
}

// Clean ALL output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// NOW send the clean JSON
header('Content-Type: application/json');
echo json_encode($response);
