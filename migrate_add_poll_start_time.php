<?php
/**
 * Database Migration: Add start_time to polls table
 * Run this once to update existing databases
 */

require_once 'config.php';

try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if start_time column already exists
    $result = $db->query("PRAGMA table_info(polls)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    $has_start_time = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'start_time') {
            $has_start_time = true;
            break;
        }
    }
    
    if (!$has_start_time) {
        echo "Adding start_time column to polls table...\n";
        $db->exec("ALTER TABLE polls ADD COLUMN start_time TIME");
        echo "✓ Migration completed successfully!\n";
    } else {
        echo "✓ start_time column already exists. No migration needed.\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
