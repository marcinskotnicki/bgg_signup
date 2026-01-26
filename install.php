<?php
/**
 * Installation Script for BGG Signup System
 * 
 * This script:
 * 1. Creates the SQLite database with all necessary tables
 * 2. Downloads all files from GitHub repository
 * 3. Sets up default configuration
 * 4. Deletes itself after successful installation
 */

// Configuration
define('GITHUB_REPO', 'https://github.com/marcinskotnicki/bgg_signup');
define('GITHUB_API', 'https://api.github.com/repos/marcinskotnicki/bgg_signup/contents/');
define('DB_FILE', 'boardgame_events.db');
define('INSTALL_LOG', 'install_log.txt');

// Start output buffering for better error handling
ob_start();

/**
 * Log installation messages
 */
function log_message($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    file_put_contents(INSTALL_LOG, $log_entry, FILE_APPEND);
    echo "<p>$message</p>";
    flush();
    ob_flush();
}

/**
 * Create the SQLite database with all necessary tables
 */
function create_database() {
    log_message("Creating database...");
    
    try {
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Events table
        $db->exec("CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Event days table (for multi-day events)
        $db->exec("CREATE TABLE IF NOT EXISTS event_days (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_id INTEGER NOT NULL,
            day_number INTEGER NOT NULL,
            date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            max_tables INTEGER NOT NULL,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        )");
        
        // Tables (physical tables at venue)
        $db->exec("CREATE TABLE IF NOT EXISTS tables (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_day_id INTEGER NOT NULL,
            table_number INTEGER NOT NULL,
            FOREIGN KEY (event_day_id) REFERENCES event_days(id) ON DELETE CASCADE
        )");
        
        // Board games
        $db->exec("CREATE TABLE IF NOT EXISTS games (
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
        )");
        
        // Players signed up for games
        $db->exec("CREATE TABLE IF NOT EXISTS players (
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
        )");
        
        // Comments on games
        $db->exec("CREATE TABLE IF NOT EXISTS comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            game_id INTEGER NOT NULL,
            author_name TEXT NOT NULL,
            author_email TEXT,
            comment TEXT NOT NULL,
            user_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
        )");
        
        // Users table (for login functionality)
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            is_admin INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Options/Settings table
        $db->exec("CREATE TABLE IF NOT EXISTS options (
            option_key TEXT PRIMARY KEY,
            option_value TEXT
        )");
        
        // BGG API cache
        $db->exec("CREATE TABLE IF NOT EXISTS bgg_cache (
            cache_key TEXT PRIMARY KEY,
            cache_data TEXT NOT NULL,
            cached_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Insert default options
        $default_options = [
            'venue_name' => 'Game Venue',
            'default_event_name' => 'Board Game Event',
            'default_start_time' => '10:00',
            'default_end_time' => '22:00',
            'timeline_extension' => '3',
            'default_tables' => '5',
            'smtp_email' => '',
            'smtp_login' => '',
            'smtp_password' => '',
            'smtp_server' => '',
            'smtp_port' => '587',
            'allow_reserve_list' => '1',
            'homepage_message' => '',
            'add_game_message' => '',
            'add_player_message' => '',
            'allow_logged_in' => 'no',
            'require_emails' => 'no',
            'verification_method' => 'email',
            'send_emails' => 'no',
            'allow_full_deletion' => 'no',
            'bgg_api_token' => '',
            'default_language' => 'en',
            'restrict_comments' => 'no',
            'use_captcha' => 'no',
            'admin_password' => password_hash('admin123', PASSWORD_DEFAULT)
        ];
        
        $stmt = $db->prepare("INSERT OR IGNORE INTO options (option_key, option_value) VALUES (?, ?)");
        foreach ($default_options as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        
        log_message("Database created successfully!");
        return true;
        
    } catch (PDOException $e) {
        log_message("Database creation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Download files from GitHub repository
 */
function download_from_github($path = '') {
    log_message("Downloading files from GitHub...");
    
    $api_url = GITHUB_API . $path;
    
    // Set up context for GitHub API (requires user agent)
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'User-Agent: BGG-Signup-Installer'
        ]
    ]);
    
    $response = @file_get_contents($api_url, false, $context);
    
    if ($response === false) {
        log_message("Failed to connect to GitHub API");
        return false;
    }
    
    $files = json_decode($response, true);
    
    if (!is_array($files)) {
        log_message("Invalid response from GitHub API");
        return false;
    }
    
    foreach ($files as $file) {
        // Skip install.php and database files
        if ($file['name'] === 'install.php' || strpos($file['name'], '.db') !== false) {
            continue;
        }
        
        if ($file['type'] === 'file') {
            // Download file
            $file_content = @file_get_contents($file['download_url'], false, $context);
            
            if ($file_content !== false) {
                // Create directory if needed
                $dir = dirname($file['path']);
                if ($dir !== '.' && !is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                
                file_put_contents($file['path'], $file_content);
                log_message("Downloaded: " . $file['path']);
            } else {
                log_message("Failed to download: " . $file['path']);
            }
            
        } elseif ($file['type'] === 'dir') {
            // Recursively download directory contents
            if (!is_dir($file['path'])) {
                mkdir($file['path'], 0755, true);
            }
            download_from_github($file['path']);
        }
    }
    
    return true;
}

/**
 * Main installation process
 */
function install() {
    echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>BGG Signup - Installation</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        h1 { color: #333; }
        p { padding: 5px; margin: 5px 0; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>BGG Signup System - Installation</h1>";
    
    // Check if already installed
    if (file_exists(DB_FILE)) {
        echo "<p class='error'>Installation already completed. Database file exists.</p>";
        echo "<p>If you need to reinstall, please delete the database file first.</p>";
        echo "</body></html>";
        return;
    }
    
    // Step 1: Create database
    if (!create_database()) {
        echo "<p class='error'>Installation failed at database creation step.</p>";
        echo "</body></html>";
        return;
    }
    
    // Step 2: Download files from GitHub
    if (!download_from_github()) {
        echo "<p class='error'>Warning: Could not download files from GitHub.</p>";
        echo "<p>You may need to manually upload the files.</p>";
    } else {
        log_message("All files downloaded successfully!");
    }
    
    // Step 3: Self-destruct
    log_message("Installation complete! Deleting installer...");
    
    echo "<p class='success'>Installation completed successfully!</p>";
    echo "<p>Default admin password: <strong>admin123</strong></p>";
    echo "<p><strong>IMPORTANT:</strong> Please change the admin password immediately!</p>";
    echo "<p>Redirecting to admin panel in 5 seconds...</p>";
    echo "<script>setTimeout(function(){ window.location.href = 'admin.php'; }, 5000);</script>";
    echo "</body></html>";
    
    // Delete this file
    @unlink(__FILE__);
}

// Run installation
install();
ob_end_flush();
?>