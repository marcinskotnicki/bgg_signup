<?php
/**
 * File Helper Functions
 * 
 * Consolidates duplicate file operation implementations from:
 * - install.php (copy_directory_contents, delete_directory)
 * - update.php (copy_directory_contents, delete_directory)
 * 
 * Use: require_once 'includes/file_helper.php';
 */

/**
 * Recursively copy directory contents
 * 
 * @param string $source Source directory path
 * @param string $dest Destination directory path
 * @return bool True on success, false on failure
 */
/**
 * Recursively copy directory contents
 * 
 * @param string $source Source directory path
 * @param string $dest Destination directory path
 * @param array $exclude Files/directories to exclude (basenames)
 * @return int Number of files copied
 */
function copy_directory_contents($source, $dest, $exclude = []) {
    $files_copied = 0;
    
    // Default exclusions for security
    $default_exclude = ['install.php', '.git', '.github'];
    $exclude = array_merge($default_exclude, $exclude);
    
    // Create destination directory if it doesn't exist
    if (!is_dir($dest)) {
        if (!mkdir($dest, 0755, true)) {
            if (function_exists('log_error')) {
                log_error("Failed to create directory: $dest");
            }
            return 0;
        }
    }
    
    // Open source directory
    $dir = opendir($source);
    if (!$dir) {
        if (function_exists('log_error')) {
            log_error("Failed to open directory: $source");
        }
        return 0;
    }
    
    // Copy each item
    while (($file = readdir($dir)) !== false) {
        // Skip . and ..
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        // Skip excluded files/directories
        if (in_array($file, $exclude)) {
            if (function_exists('log_update_message')) {
                log_update_message("Skipping excluded file: $file");
            }
            continue;
        }
        
        $source_path = $source . '/' . $file;
        $dest_path = $dest . '/' . $file;
        
        if (is_dir($source_path)) {
            // Recursively copy subdirectory
            $files_copied += copy_directory_contents($source_path, $dest_path, $exclude);
        } else {
            // Copy file
            if (copy($source_path, $dest_path)) {
                $files_copied++;
            } else {
                if (function_exists('log_error')) {
                    log_error("Failed to copy file: $source_path to $dest_path");
                }
            }
        }
    }
    
    closedir($dir);
    return $files_copied;
}

/**
 * Recursively delete directory and its contents
 * 
 * @param string $dir Directory path to delete
 * @return bool True on success, false on failure
 */
function delete_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    // Open directory
    $handle = opendir($dir);
    if (!$handle) {
        if (function_exists('log_error')) {
            log_error("Failed to open directory for deletion: $dir");
        }
        return false;
    }
    
    // Delete each item
    while (($file = readdir($handle)) !== false) {
        // Skip . and ..
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            // Recursively delete subdirectory
            if (!delete_directory($path)) {
                closedir($handle);
                return false;
            }
        } else {
            // Delete file
            if (!unlink($path)) {
                if (function_exists('log_error')) {
                    log_error("Failed to delete file: $path");
                }
                closedir($handle);
                return false;
            }
        }
    }
    
    closedir($handle);
    
    // Delete the directory itself
    if (!rmdir($dir)) {
        if (function_exists('log_error')) {
            log_error("Failed to delete directory: $dir");
        }
        return false;
    }
    
    return true;
}

/**
 * Create directory with proper permissions if it doesn't exist
 * 
 * @param string $dir Directory path
 * @param int $permissions Unix permissions (default: 0755)
 * @return bool True on success or if already exists, false on failure
 */
function ensure_directory_exists($dir, $permissions = 0755) {
    if (is_dir($dir)) {
        return true;
    }
    
    if (mkdir($dir, $permissions, true)) {
        return true;
    }
    
    if (function_exists('log_error')) {
        log_error("Failed to create directory: $dir");
    }
    
    return false;
}

/**
 * Check if directory is writable, create if doesn't exist
 * 
 * @param string $dir Directory path
 * @return bool True if writable, false otherwise
 */
function is_directory_writable($dir) {
    if (!is_dir($dir)) {
        if (!ensure_directory_exists($dir)) {
            return false;
        }
    }
    
    return is_writable($dir);
}
