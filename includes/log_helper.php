<?php
/**
 * Logging Helper Functions
 * 
 * Consolidates duplicate add_log() implementations from:
 * - ajax/update_config.php
 * - ajax/update_schema.php
 * 
 * Use: require_once 'includes/log_helper.php';
 */

/**
 * Add log entry to log file
 * 
 * @param string $message Log message
 * @param string $level Log level (INFO, WARNING, ERROR, SUCCESS)
 * @return void
 */
function add_log($message, $level = 'INFO') {
    $log_dir = __DIR__ . '/../logs';
    
    // Create logs directory if it doesn't exist
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/system.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Log info message
 * 
 * @param string $message Log message
 * @return void
 */
function log_info($message) {
    add_log($message, 'INFO');
}

/**
 * Log warning message
 * 
 * @param string $message Log message
 * @return void
 */
function log_warning($message) {
    add_log($message, 'WARNING');
}

/**
 * Log error message
 * 
 * @param string $message Log message
 * @return void
 */
function log_error($message) {
    add_log($message, 'ERROR');
}

/**
 * Log success message
 * 
 * @param string $message Log message
 * @return void
 */
function log_success($message) {
    add_log($message, 'SUCCESS');
}
