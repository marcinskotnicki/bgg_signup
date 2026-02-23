<?php
/**
 * Installation Script for BGG Signup System
 * 
 * This script:
 * 1. Collects admin credentials and system settings
 * 2. Creates the SQLite database with all necessary tables
 * 3. Downloads all files from GitHub repository
 * 4. Updates configuration with user preferences
 * 5. Creates the admin user
 * 6. Deletes itself after successful installation
 */

// Configuration
define('GITHUB_REPO', 'https://github.com/marcinskotnicki/bgg_signup');
define('DB_FILE', 'boardgame_events.db');
define('INSTALL_LOG', 'install_log.txt');

require_once __DIR__ . '/includes/http_helper.php';
require_once __DIR__ . '/includes/log_helper.php';
require_once __DIR__ . '/includes/file_helper.php';


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
/**
 * Create the SQLite database with all necessary tables
 */
function create_database($admin_name, $admin_email, $admin_password) {
    log_message("Creating database...");
    
    try {
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Load schema from centralized file
        define('SCHEMA_ACCESS', true);
        require_once __DIR__ . '/schema.php';
        
        $tables = get_database_schema();
        
        log_message("Creating " . count($tables) . " tables...");
        
        // Create all tables
        foreach ($tables as $table_name => $create_sql) {
            $db->exec($create_sql);
            log_message("Created table: $table_name");
        }
        
        log_message("All tables created successfully");
        
        // Insert default options
        log_message("Setting up default options...");
        
        $default_options = get_default_options();
        
        // Set admin password
        $default_options['admin_password'] = password_hash($admin_password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("INSERT INTO options (option_key, option_value) VALUES (?, ?)");
        foreach ($default_options as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        
        log_message("Default options inserted");
        
        // Create admin user
        log_message("Creating admin user...");
        
        $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, is_admin) VALUES (?, ?, ?, 1)");
        $stmt->execute([$admin_name, $admin_email, $password_hash]);
        
        log_message("Admin user created: $admin_email");
        
        log_message("Database setup complete!");
        return true;
        
    } catch (PDOException $e) {
        log_message("Database error: " . $e->getMessage());
        return false;
    }
}
    } else {
        $zip = new ZipArchive;
        if ($zip->open($zip_file) === TRUE) {
            $zip->extractTo($extract_dir);
            $zip->close();
            log_message("ZIP file extracted");
        } else {
            log_message("ERROR: Could not open ZIP file");
            @unlink($zip_file);
            return false;
        }
    }
    
    // Find the extracted folder (it will be something like 'bgg_signup-main')
    $extracted_folders = glob($extract_dir . '/*', GLOB_ONLYDIR);
    if (empty($extracted_folders)) {
        log_message("ERROR: Could not find extracted folder");
        @unlink($zip_file);
        return false;
    }
    
    $source_dir = $extracted_folders[0];
    log_message("Found extracted folder: $source_dir");
    
    // Copy files from extracted folder to current directory
    $files_copied = copy_directory_contents($source_dir, '.');
    
    // Clean up
    @unlink($zip_file);
    delete_directory($extract_dir);
    
    log_message("Files copied successfully! ($files_copied files)");
    return true;
}




/**
 * Update config.php with installation settings
 */
function update_config_file($default_language, $bgg_api_token) {
    log_message("Updating configuration file...");
    
    $config_file = 'config.php';
    
    if (!file_exists($config_file)) {
        log_message("WARNING: config.php not found, skipping configuration update");
        return false;
    }
    
    $config_content = file_get_contents($config_file);
    
    // Update default_language
    $pattern = "/'default_language'\s*=>\s*'[^']*'/";
    $replacement = "'default_language' => '$default_language'";
    $config_content = preg_replace($pattern, $replacement, $config_content);
    
    // Update bgg_api_token
    $pattern = "/'bgg_api_token'\s*=>\s*'[^']*'/";
    $replacement = "'bgg_api_token' => '" . addslashes($bgg_api_token) . "'";
    $config_content = preg_replace($pattern, $replacement, $config_content);
    
    file_put_contents($config_file, $config_content);
    
    log_message("Configuration updated: Language=$default_language, BGG Token=" . ($bgg_api_token ? 'SET' : 'NOT SET'));
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
        .form-group input, .form-group select { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; }
        .form-group small { color: #666; display: block; margin-top: 5px; }
        .form-group small a { color: #4CAF50; }
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
            
            <div class='form-group'>
                <label>Default Language:</label>
                <select name='default_language'>
                    <option value='en' " . (($_POST['default_language'] ?? 'en') === 'en' ? 'selected' : '') . ">English</option>
                    <option value='pl' " . (($_POST['default_language'] ?? '') === 'pl' ? 'selected' : '') . ">Polski (Polish)</option>
                </select>
                <small>The default language for the system</small>
            </div>
            
            <div class='form-group'>
                <label>BoardGameGeek API Token (Optional):</label>
                <input type='text' name='bgg_api_token' value='" . htmlspecialchars($_POST['bgg_api_token'] ?? '') . "'>
                <small>Optional - improves BGG integration. <a href='https://boardgamegeek.com/wiki/page/BGG_XML_API2#toc17' target='_blank'>Get token here</a></small>
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
    $default_language = trim($_POST['default_language'] ?? 'en');
    $bgg_api_token = trim($_POST['bgg_api_token'] ?? '');
    
    // Validate language
    if (!in_array($default_language, ['en', 'pl'])) {
        $default_language = 'en';
    }
    
    if (empty($admin_name) || empty($admin_email) || empty($admin_password)) {
        show_install_form("All required fields must be filled!");
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
    
    // Step 3: Update configuration file
    echo "<p>Updating configuration...</p>";
    flush();
    ob_flush();
    
    update_config_file($default_language, $bgg_api_token);
    echo "<p class='success'>Configuration updated!</p>";
    
    flush();
    ob_flush();
    
    // Step 4: Create necessary directories
    $directories = ['logs', 'thumbnails', 'languages', 'backup'];
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            log_message("Created directory: $dir");
        }
    }
    
    // Step 5: Self-destruct
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