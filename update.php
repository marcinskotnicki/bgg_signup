<?php
/**
 * Update Script for BGG Signup System
 * 
 * This script:
 * 1. Creates a backup of the entire site (deletes previous backups)
 * 2. Checks GitHub repository for new/updated files
 * 3. Downloads and updates files
 * 4. Updates database schema if needed (adds new tables/columns)
 * 5. Logs all actions
 * 
 * NOTE: This should be called from admin.php, not directly
 */

// This file should only be included, not run directly
if (!defined('ADMIN_PANEL')) {
    die('Direct access not allowed. Use admin panel to run updates.');
}

// Load helper functions
require_once __DIR__ . '/includes/http_helper.php';
require_once __DIR__ . '/includes/log_helper.php';
require_once __DIR__ . '/includes/file_helper.php';
require_once __DIR__ . '/includes/schema_helper.php';


/**
 * Delete all previous backups
 */
function delete_previous_backups() {
    log_update_message("Deleting previous backups...");
    
    if (is_dir(BACKUP_DIR)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(BACKUP_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        
        rmdir(BACKUP_DIR);
        log_update_message("Previous backups deleted.");
    } else {
        log_update_message("No previous backups found.");
    }
}

/**
 * Create backup of entire site
 */
function create_backup() {
    log_update_message("Creating backup...");
    
    // Delete old backups first
    delete_previous_backups();
    
    // Create backup directory
    if (!mkdir(BACKUP_DIR, 0755, true)) {
        log_update_message("ERROR: Could not create backup directory!");
        return false;
    }
    
    $backup_timestamp = date('Y-m-d_H-i-s');
    $backup_path = BACKUP_DIR . '/backup_' . $backup_timestamp;
    
    if (!mkdir($backup_path, 0755, true)) {
        log_update_message("ERROR: Could not create timestamped backup directory!");
        return false;
    }
    
    // Get all files in current directory (excluding backup dir)
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator('.', RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $backed_up = 0;
    foreach ($files as $file) {
        $filepath = $file->getPathname();
        
        // Skip backup directory itself
        if (strpos($filepath, BACKUP_DIR) === 0 || strpos($filepath, './'.BACKUP_DIR) === 0) {
            continue;
        }
        
        // Create relative path
        $relativePath = substr($filepath, 2); // Remove './'
        $backupFile = $backup_path . '/' . $relativePath;
        
        if ($file->isDir()) {
            // Create directory in backup
            if (!is_dir($backupFile)) {
                mkdir($backupFile, 0755, true);
            }
        } else {
            // Copy file to backup
            $backupFileDir = dirname($backupFile);
            if (!is_dir($backupFileDir)) {
                mkdir($backupFileDir, 0755, true);
            }
            
            if (copy($filepath, $backupFile)) {
                $backed_up++;
            } else {
                log_update_message("WARNING: Could not backup file: $filepath");
            }
        }
    }
    
    log_update_message("Backup created successfully! ($backed_up files backed up)");
    return true;
}


/**
 * Update database schema
 * Adds new tables and columns if they don't exist
 */
function update_database_schema() {
    log_update_message("Checking database schema...");
    
    try {
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $current_schema = get_current_schema();
        $updates_made = 0;
        
        // Define expected tables and their creation SQL
        $expected_tables = [
            'events' => "CREATE TABLE IF NOT EXISTS events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            'event_days' => "CREATE TABLE IF NOT EXISTS event_days (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                day_number INTEGER NOT NULL,
                date DATE NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                max_tables INTEGER NOT NULL,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
            )",
            'tables' => "CREATE TABLE IF NOT EXISTS tables (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_day_id INTEGER NOT NULL,
                table_number INTEGER NOT NULL,
                FOREIGN KEY (event_day_id) REFERENCES event_days(id) ON DELETE CASCADE
            )",
            'games' => "CREATE TABLE IF NOT EXISTS games (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                table_id INTEGER NOT NULL,
                bgg_id INTEGER,
                bgg_url TEXT,
                name TEXT NOT NULL,
                thumbnail TEXT,
                play_time INTEGER NOT NULL,
                min_players INTEGER NOT NULL,
                max_players INTEGER NOT NULL,
                difficulty REAL,
                start_time TIME NOT NULL,
                host_name TEXT NOT NULL,
                host_email TEXT,
                language TEXT NOT NULL,
                rules_explanation TEXT NOT NULL,
                initial_comment TEXT,
                is_active INTEGER DEFAULT 1,
                created_by_user_id INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE CASCADE
            )",
            'players' => "CREATE TABLE IF NOT EXISTS players (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                game_id INTEGER NOT NULL,
                player_name TEXT NOT NULL,
                player_email TEXT,
                knows_rules TEXT,
                comment TEXT,
                is_reserve INTEGER DEFAULT 0,
                position INTEGER NOT NULL,
                user_id INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
            )",
            'comments' => "CREATE TABLE IF NOT EXISTS comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                game_id INTEGER NOT NULL,
                author_name TEXT NOT NULL,
                author_email TEXT,
                comment TEXT NOT NULL,
                user_id INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
            )",
            'users' => "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                is_admin INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            'options' => "CREATE TABLE IF NOT EXISTS options (
                option_key TEXT PRIMARY KEY,
                option_value TEXT
            )",
            'bgg_cache' => "CREATE TABLE IF NOT EXISTS bgg_cache (
                cache_key TEXT PRIMARY KEY,
                cache_data TEXT NOT NULL,
                cached_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            'polls' => "CREATE TABLE IF NOT EXISTS polls (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                table_id INTEGER NOT NULL,
                creator_name TEXT NOT NULL,
                creator_email TEXT,
                created_by_user_id INTEGER,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                closed_at DATETIME,
                FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE CASCADE
            )",
            'poll_options' => "CREATE TABLE IF NOT EXISTS poll_options (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                poll_id INTEGER NOT NULL,
                bgg_id INTEGER,
                bgg_url TEXT,
                game_name TEXT NOT NULL,
                thumbnail TEXT,
                play_time INTEGER,
                min_players INTEGER,
                max_players INTEGER,
                difficulty REAL,
                vote_threshold INTEGER NOT NULL,
                display_order INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
            )",
            'poll_votes' => "CREATE TABLE IF NOT EXISTS poll_votes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                poll_option_id INTEGER NOT NULL,
                voter_name TEXT NOT NULL,
                voter_email TEXT NOT NULL,
                user_id INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (poll_option_id) REFERENCES poll_options(id) ON DELETE CASCADE
            )"
        ];
        
        // Check and create missing tables
        foreach ($expected_tables as $table_name => $create_sql) {
            if (!isset($current_schema[$table_name])) {
                $db->exec($create_sql);
                log_update_message("Created missing table: $table_name");
                $updates_made++;
            }
        }
        
        // Check for new options that might need to be added
        $expected_options = [
            'venue_name', 'default_event_name', 'default_start_time', 'default_end_time',
            'timeline_extension', 'default_tables', 'smtp_email', 'smtp_login', 
            'smtp_password', 'smtp_server', 'smtp_port', 'allow_reserve_list',
            'homepage_message', 'add_game_message', 'add_player_message',
            'allow_logged_in', 'require_emails', 'verification_method',
            'send_emails', 'allow_full_deletion', 'bgg_api_token',
            'default_language', 'restrict_comments', 'use_captcha', 'admin_password'
        ];
        
        $stmt = $db->prepare("SELECT option_key FROM options WHERE option_key = ?");
        foreach ($expected_options as $option) {
            $stmt->execute([$option]);
            if (!$stmt->fetch()) {
                // Add missing option with default value
                $default_value = '';
                if ($option === 'timeline_extension') $default_value = '3';
                if ($option === 'default_tables') $default_value = '5';
                if ($option === 'smtp_port') $default_value = '587';
                if ($option === 'allow_reserve_list') $default_value = '1';
                if ($option === 'allow_logged_in') $default_value = 'no';
                if ($option === 'require_emails') $default_value = 'no';
                if ($option === 'verification_method') $default_value = 'email';
                if ($option === 'send_emails') $default_value = 'no';
                if ($option === 'allow_full_deletion') $default_value = 'no';
                if ($option === 'default_language') $default_value = 'en';
                if ($option === 'restrict_comments') $default_value = 'no';
                if ($option === 'use_captcha') $default_value = 'no';
                if ($option === 'admin_password') $default_value = password_hash('admin123', PASSWORD_DEFAULT);
                
                $insert = $db->prepare("INSERT OR IGNORE INTO options (option_key, option_value) VALUES (?, ?)");
                $insert->execute([$option, $default_value]);
                log_update_message("Added missing option: $option");
                $updates_made++;
            }
        }
        
        if ($updates_made > 0) {
            log_update_message("Database schema updated! ($updates_made changes made)");
        } else {
            log_update_message("Database schema is up to date.");
        }
        
        return true;
        
    } catch (PDOException $e) {
        log_update_message("ERROR: Database update failed: " . $e->getMessage());
        return false;
    }
}





/**
 * Download and extract files from GitHub repository ZIP
 */
function update_files_from_github() {
    log_update_message("Downloading files from GitHub...");
    
    // GitHub ZIP URL - no API needed, no rate limits!
    $zip_url = GITHUB_REPO . '/archive/refs/heads/main.zip';
    $zip_file = 'github_update.zip';
    $extract_dir = 'github_update_extract';
    
    // Download ZIP file
    log_update_message("Downloading ZIP from: $zip_url");
    $zip_content = fetch_url($zip_url);
    
    if ($zip_content === false) {
        log_update_message("ERROR: Failed to download ZIP file from GitHub");
        return false;
    }
    
    // Save ZIP file
    file_put_contents($zip_file, $zip_content);
    log_update_message("ZIP file downloaded (" . number_format(strlen($zip_content)) . " bytes)");
    
    // Extract ZIP file
    if (!class_exists('ZipArchive')) {
        log_update_message("ERROR: ZipArchive extension not available. Trying PharData...");
        
        // Try using PharData as fallback
        try {
            $phar = new PharData($zip_file);
            $phar->extractTo($extract_dir, null, true);
            log_update_message("ZIP extracted using PharData");
        } catch (Exception $e) {
            log_update_message("ERROR: Could not extract ZIP: " . $e->getMessage());
            @unlink($zip_file);
            return false;
        }
    } else {
        $zip = new ZipArchive;
        if ($zip->open($zip_file) === TRUE) {
            $zip->extractTo($extract_dir);
            $zip->close();
            log_update_message("ZIP file extracted");
        } else {
            log_update_message("ERROR: Could not open ZIP file");
            @unlink($zip_file);
            return false;
        }
    }
    
    // Find the extracted folder (it will be something like 'bgg_signup-main')
    $extracted_folders = glob($extract_dir . '/*', GLOB_ONLYDIR);
    if (empty($extracted_folders)) {
        log_update_message("ERROR: Could not find extracted folder");
        @unlink($zip_file);
        return false;
    }
    
    $source_dir = $extracted_folders[0];
    log_update_message("Found extracted folder: $source_dir");
    
    // Copy files from extracted folder to current directory
    $files_copied = copy_directory_contents($source_dir, '.');
    
    // Clean up
    @unlink($zip_file);
    delete_directory($extract_dir);
    
    log_update_message("Update completed! ($files_copied files updated)");
    return true;
}

/**
 * Main update process
 * Returns array with status and messages
 */
function run_update() {
    $messages = [];
    $messages[] = "=== UPDATE STARTED ===";
    
    // Step 1: Create backup
    if (!create_backup()) {
        $messages[] = "ERROR: Backup creation failed! Update aborted.";
        return ['success' => false, 'messages' => $messages];
    }
    
    // Step 2: Update database schema
    if (!update_database_schema()) {
        $messages[] = "WARNING: Database schema update had issues.";
    }
    
    // Step 3: Update files from GitHub
    if (!update_files_from_github()) {
        $messages[] = "WARNING: File update had issues.";
    }
    
    $messages[] = "=== UPDATE COMPLETED ===";
    
    return ['success' => true, 'messages' => $messages];
}
?>