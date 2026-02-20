<?php
/**
 * Database Schema Helper Functions
 * 
 * Consolidates duplicate schema-related implementations from:
 * - ajax/update_schema.php
 * - auto_update.php
 * - update.php
 * 
 * Use: require_once 'includes/schema_helper.php';
 */

/**
 * Get current database schema
 * 
 * @param PDO $db Database connection
 * @return array Schema information
 */
function get_current_schema($db) {
    $schema = [];
    
    try {
        // Get list of tables
        $tables_query = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $tables = $tables_query->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            // Get table structure
            $table_info = $db->query("PRAGMA table_info('$table')")->fetchAll(PDO::FETCH_ASSOC);
            
            $columns = [];
            foreach ($table_info as $column) {
                $columns[$column['name']] = [
                    'type' => $column['type'],
                    'notnull' => $column['notnull'],
                    'dflt_value' => $column['dflt_value'],
                    'pk' => $column['pk']
                ];
            }
            
            $schema[$table] = [
                'columns' => $columns,
                'indexes' => get_table_indexes($db, $table)
            ];
        }
        
        return $schema;
        
    } catch (PDOException $e) {
        if (function_exists('log_error')) {
            log_error("Failed to get current schema: " . $e->getMessage());
        }
        return [];
    }
}

/**
 * Get indexes for a table
 * 
 * @param PDO $db Database connection
 * @param string $table Table name
 * @return array Index information
 */
function get_table_indexes($db, $table) {
    try {
        $indexes_query = $db->query("PRAGMA index_list('$table')");
        return $indexes_query->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Parse schema from GitHub schema.sql file
 * 
 * @param string|null $github_token Optional GitHub token
 * @return array|null Parsed schema or null on failure
 */
function parse_schema_from_github($github_token = null) {
    $schema_url = 'https://raw.githubusercontent.com/marcinskotnicki/bgg_signup/main/schema.sql';
    
    if (!function_exists('fetch_url')) {
        if (function_exists('log_error')) {
            log_error("fetch_url function not available - include http_helper.php first");
        }
        return null;
    }
    
    $schema_sql = fetch_url($schema_url, $github_token);
    
    if ($schema_sql === false) {
        if (function_exists('log_error')) {
            log_error("Failed to fetch schema from GitHub");
        }
        return null;
    }
    
    // Parse SQL to extract table structures
    $schema = [];
    $current_table = null;
    
    $lines = explode("\n", $schema_sql);
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Detect CREATE TABLE
        if (preg_match('/CREATE TABLE (?:IF NOT EXISTS )?([a-z_]+)/i', $line, $matches)) {
            $current_table = $matches[1];
            $schema[$current_table] = [
                'columns' => [],
                'indexes' => []
            ];
        }
        
        // Detect column definition
        if ($current_table && preg_match('/^([a-z_]+)\s+([A-Z]+)/i', $line, $matches)) {
            $column_name = $matches[1];
            $column_type = $matches[2];
            
            $schema[$current_table]['columns'][$column_name] = [
                'type' => $column_type,
                'definition' => $line
            ];
        }
    }
    
    return $schema;
}

/**
 * Detect schema changes between GitHub and current database
 * 
 * @param array $github_schema Schema from GitHub
 * @param array $current_schema Current database schema
 * @return array Array of changes (tables_to_add, columns_to_add, etc.)
 */
function detect_schema_changes($github_schema, $current_schema) {
    $changes = [
        'tables_to_add' => [],
        'tables_to_remove' => [],
        'columns_to_add' => [],
        'columns_to_remove' => []
    ];
    
    // Detect new tables
    foreach ($github_schema as $table => $structure) {
        if (!isset($current_schema[$table])) {
            $changes['tables_to_add'][] = $table;
        }
    }
    
    // Detect removed tables
    foreach ($current_schema as $table => $structure) {
        if (!isset($github_schema[$table])) {
            $changes['tables_to_remove'][] = $table;
        }
    }
    
    // Detect column changes
    foreach ($github_schema as $table => $structure) {
        if (isset($current_schema[$table])) {
            $github_columns = $structure['columns'];
            $current_columns = $current_schema[$table]['columns'];
            
            // New columns
            foreach ($github_columns as $column => $def) {
                if (!isset($current_columns[$column])) {
                    $changes['columns_to_add'][] = [
                        'table' => $table,
                        'column' => $column,
                        'definition' => $def
                    ];
                }
            }
            
            // Removed columns
            foreach ($current_columns as $column => $def) {
                if (!isset($github_columns[$column])) {
                    $changes['columns_to_remove'][] = [
                        'table' => $table,
                        'column' => $column
                    ];
                }
            }
        }
    }
    
    return $changes;
}

/**
 * Apply schema changes to database
 * 
 * @param PDO $db Database connection
 * @param array $changes Changes to apply
 * @return bool True on success, false on failure
 */
function apply_schema_changes($db, $changes) {
    try {
        $db->beginTransaction();
        
        // Add new tables
        foreach ($changes['tables_to_add'] as $table) {
            if (function_exists('log_info')) {
                log_info("Adding new table: $table");
            }
            // Note: This would need the full CREATE TABLE statement
            // For now, just log it
        }
        
        // Add new columns
        foreach ($changes['columns_to_add'] as $change) {
            $table = $change['table'];
            $column = $change['column'];
            $definition = $change['definition']['definition'] ?? '';
            
            if ($definition) {
                $sql = "ALTER TABLE $table ADD COLUMN $definition";
                $db->exec($sql);
                
                if (function_exists('log_info')) {
                    log_info("Added column $column to table $table");
                }
            }
        }
        
        $db->commit();
        return true;
        
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        if (function_exists('log_error')) {
            log_error("Failed to apply schema changes: " . $e->getMessage());
        }
        
        return false;
    }
}
