<?php
/**
 * Installation Script for BGG Signup System
 * 
 * This script:
 * 1. Collects admin credentials
 * 2. Creates the SQLite database with all necessary tables
 * 3. Downloads all files from GitHub repository
 * 4. Creates the admin user
 * 5. Deletes itself after successful installation
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
}

/**
 * Create the SQLite database with all necessary tables
 */
function create_database($admin_name, $admin_email, $admin_password) {
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
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            is_admin INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // BGG API cache
        $db->exec("CREATE TABLE IF NOT EXISTS bgg_cache (
            cache_key TEXT PRIMARY KEY,
            cache_data TEXT NOT NULL,
            cached_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create admin user
        $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, is_admin) VALUES (?, ?, ?, 1)");
        $stmt->execute([$admin_name, $admin_email, $password_hash]);
        
        log_message("Database created successfully!");
        log_message("Admin user created: $admin_email");
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
 * Show installation form
 */
function show_install_form($error = '') {
    echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>BGG Signup - Installation</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        h1 { color: #333; text-align: center; }
        .install-form { background: #f5f5f5; padding: 30px; border-radius: 8px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; }
        .form-group small { color: #666; }
        button { width: 100%; padding: 12px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #45a049; }
        .error { color: red; background: #ffebee; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .info { color: #666; background: #e3f2fd; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>BGG Signup System - Installation</h1>
    
    <div class='info'>
        <strong>Welcome!</strong> This installer will set up your board game event signup system.
        Please provide the admin account details below.
    </div>";
    
    if ($error) {
        echo "<div class='error'>$error</div>";
    }
    
    echo "<div class='install-form'>
        <form method='POST'>
            <div class='form-group'>
                <label>Admin Name:</label>
                <input type='text' name='admin_name' value='" . htmlspecialchars($_POST['admin_name'] ?? '') . "' required autofocus>
                <small>This will be displayed as your name in the system</small>
            </div>
            
            <div class='form-group'>
                <label>Admin Email:</label>
                <input type='email' name='admin_email' value='" . htmlspecialchars($_POST['admin_email'] ?? '') . "' required>
                <small>Used for login and notifications</small>
            </div>
            
            <div class='form-group'>
                <label>Admin Password:</label>
                <input type='password' name='admin_password' required minlength='6'>
                <small>At least 6 characters</small>
            </div>
            
            <div class='form-group'>
                <label>Confirm Password:</label>
                <input type='password' name='admin_password_confirm' required minlength='6'>
            </div>
            
            <button type='submit' name='install'>Install System</button>
        </form>
    </div>
</body>
</html>";
}

/**
 * Main installation process
 */
function install() {
    // Check if already installed
    if (file_exists(DB_FILE)) {
        echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>BGG Signup - Already Installed</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; text-align: center; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Installation Already Completed</h1>
    <p class='error'>The database file already exists.</p>
    <p>If you need to reinstall, please delete the database file first.</p>
    <p><a href='index.php'>Go to Homepage</a> | <a href='admin.php'>Go to Admin Panel</a></p>
</body>
</html>";
        return;
    }
    
    // Show form if not submitted
    if (!isset($_POST['install'])) {
        show_install_form();
        return;
    }
    
    // Validate form submission
    $admin_name = trim($_POST['admin_name'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';
    $admin_password_confirm = $_POST['admin_password_confirm'] ?? '';
    
    if (empty($admin_name) || empty($admin_email) || empty($admin_password)) {
        show_install_form("All fields are required!");
        return;
    }
    
    if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        show_install_form("Please provide a valid email address!");
        return;
    }
    
    if (strlen($admin_password) < 6) {
        show_install_form("Password must be at least 6 characters long!");
        return;
    }
    
    if ($admin_password !== $admin_password_confirm) {
        show_install_form("Passwords do not match!");
        return;
    }
    
    // Start installation
    echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>BGG Signup - Installing</title>
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
    
    flush();
    ob_flush();
    
    // Step 1: Create database
    echo "<p>Creating database...</p>";
    flush();
    ob_flush();
    
    if (!create_database($admin_name, $admin_email, $admin_password)) {
        echo "<p class='error'>Installation failed at database creation step.</p>";
        echo "<p>Check install_log.txt for details.</p>";
        echo "</body></html>";
        return;
    }
    
    echo "<p class='success'>Database created successfully!</p>";
    flush();
    ob_flush();
    
    // Step 2: Download files from GitHub
    echo "<p>Downloading files from GitHub...</p>";
    flush();
    ob_flush();
    
    if (!download_from_github()) {
        echo "<p class='error'>Warning: Could not download files from GitHub.</p>";
        echo "<p>You may need to manually upload the files.</p>";
    } else {
        log_message("All files downloaded successfully!");
        echo "<p class='success'>All files downloaded successfully!</p>";
    }
    
    flush();
    ob_flush();
    
    // Step 3: Create necessary directories
    $directories = ['logs', 'thumbnails', 'languages', 'backup'];
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            log_message("Created directory: $dir");
        }
    }
    
    // Step 4: Self-destruct
    log_message("Installation complete! Deleting installer...");
    
    echo "<p class='success'>Installation completed successfully!</p>";
    echo "<p><strong>Admin Account Created:</strong></p>";
    echo "<p>Email: <strong>" . htmlspecialchars($admin_email) . "</strong></p>";
    echo "<p><strong>IMPORTANT:</strong> Please note your login credentials!</p>";
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