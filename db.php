<?php
/**
 * Centralized Database Connection
 * 
 * Creates and configures the SQLite database connection with:
 * - WAL mode for better concurrent access
 * - Proper error handling
 * - Timeout settings to prevent "database locked" errors
 * 
 * Usage:
 *   require_once 'includes/db.php';
 *   // $db is now available
 */

// Make sure config is loaded
if (!defined('DB_FILE')) {
    require_once __DIR__ . '/../config.php';
}

// Create database connection
try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set timeout to 10 seconds (prevents immediate failure on locks)
    $db->setAttribute(PDO::ATTR_TIMEOUT, 10);
    
    // Enable WAL (Write-Ahead Logging) mode for better concurrency
    // This allows multiple readers while a writer is active
    $db->exec('PRAGMA journal_mode=WAL;');
    
    // Set busy timeout to 10 seconds (10000 milliseconds)
    // SQLite will wait this long for locks instead of failing immediately
    $db->exec('PRAGMA busy_timeout=10000;');
    
    // Optional: Enable foreign key constraints (recommended)
    $db->exec('PRAGMA foreign_keys=ON;');
    
} catch (PDOException $e) {
    // Log the error
    error_log('Database connection failed: ' . $e->getMessage());
    
    // Show user-friendly error
    die('Database connection failed. Please try again later.');
}

// Database connection is now available as $db variable
// No return statement needed - $db is in global scope
?>
