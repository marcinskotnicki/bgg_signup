<?php
/**
 * Diagnostic Script - Check for PHP Errors
 * Upload this temporarily to see what's wrong
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>Diagnostic Check</h1>";

// Test 1: Basic PHP
echo "<p>✓ PHP is working</p>";

// Test 2: Config file
echo "<p>Testing config.php...</p>";
try {
    $config = require_once 'config.php';
    echo "<p>✓ Config loaded successfully</p>";
    
    // Check if CACHE_VERSION exists
    if (defined('CACHE_VERSION')) {
        echo "<p>✓ CACHE_VERSION defined: " . CACHE_VERSION . "</p>";
    } else {
        echo "<p style='color:red;'>✗ CACHE_VERSION not defined!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>✗ Config error: " . $e->getMessage() . "</p>";
}

// Test 3: Index file
echo "<p>Testing index.php...</p>";
ob_start();
try {
    include 'index.php';
    $output = ob_get_clean();
    echo "<p>✓ Index.php loaded</p>";
} catch (Exception $e) {
    ob_end_clean();
    echo "<p style='color:red;'>✗ Index error: " . $e->getMessage() . "</p>";
}

echo "<h2>If you see this, PHP is working!</h2>";
echo "<p>The error is likely in one of the files we just updated.</p>";
?>
