<?php
/**
 * Admin Panel for BGG Signup System
 * 
 * Tabs:
 * 1. Logs
 * 2. Options
 * 3. Add New Event
 * 4. Update System
 */

// Load configuration
$config = require_once 'config.php';

// Auto-migrate old allow_full_deletion to deletion_mode if needed
if (!isset($config['deletion_mode']) && isset($config['allow_full_deletion'])) {
    $config['deletion_mode'] = $config['allow_full_deletion'] ? 'allow_choice' : 'soft_only';
}
// Set default if neither exists
if (!isset($config['deletion_mode'])) {
    $config['deletion_mode'] = 'soft_only';
}

// Load translation system
require_once 'includes/translations.php';

// Database connection
try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check authentication using cookies (1 year duration)
$is_logged_in = false;
$user_id = null;
$is_admin = false;
$user_name = '';

if (isset($_COOKIE['bgg_user_id']) && isset($_COOKIE['bgg_auth_token'])) {
    $user_id = $_COOKIE['bgg_user_id'];
    $auth_token = $_COOKIE['bgg_auth_token'];
    
    // Verify auth token (token = hash of user_id + password_hash + secret salt)
    $stmt = $db->prepare("SELECT name, password_hash, is_admin FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $expected_token = hash('sha256', $user_id . $user['password_hash'] . AUTH_SALT);
        if ($auth_token === $expected_token) {
            $is_logged_in = true;
            $is_admin = $user['is_admin'] == 1;
            $user_name = $user['name'];
        }
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    setcookie('bgg_user_id', '', time() - 3600, '/');
    setcookie('bgg_auth_token', '', time() - 3600, '/');
    header('Location: admin.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    // Check user credentials
    $stmt = $db->prepare("SELECT id, name, password_hash, is_admin FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        // Check if user is admin
        if ($user['is_admin'] == 1) {
            // Set cookies (1 year = 365 days)
            $auth_token = hash('sha256', $user['id'] . $user['password_hash'] . AUTH_SALT);
            setcookie('bgg_user_id', $user['id'], time() + COOKIE_LIFETIME, '/');
            setcookie('bgg_auth_token', $auth_token, time() + COOKIE_LIFETIME, '/');
            
            header('Location: admin.php');
            exit;
        } else {
            $login_error = t('not_admin_user');
        }
    } else {
        $login_error = t('invalid_credentials');
    }
}

// If not admin, show login form
if (!$is_admin) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo t('admin_login'); ?> - <?php echo htmlspecialchars($config['venue_name']); ?></title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 400px; margin: 100px auto; padding: 20px; }
            h1 { color: #333; text-align: center; }
            .login-form { background: #f5f5f5; padding: 30px; border-radius: 8px; }
            .form-group { margin-bottom: 20px; }
            .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
            input[type="email"], input[type="password"] { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; }
            button { width: 100%; padding: 12px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
            button:hover { background: #45a049; }
            .error { color: red; background: #ffebee; padding: 10px; border-radius: 4px; text-align: center; margin-bottom: 15px; }
        </style>
    </head>
    <body>
        <h1><?php echo t('admin_login'); ?></h1>
        <div class="login-form">
            <?php if (isset($login_error)): ?>
                <div class="error"><?php echo htmlspecialchars($login_error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label><?php echo t('admin_email'); ?>:</label>
                    <input type="email" name="email" required autofocus>
                </div>
                <div class="form-group">
                    <label><?php echo t('admin_password'); ?>:</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" name="login"><?php echo t('login'); ?></button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Define constants for update script
define('ADMIN_PANEL', true);
if (!defined('UPDATE_LOG')) {
    define('UPDATE_LOG', LOGS_DIR . '/update.log');
}

// Helper function for update messages
function log_update_message($message) {
    global $update_messages;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    
    // Create logs directory if it doesn't exist
    if (!is_dir(LOGS_DIR)) {
        mkdir(LOGS_DIR, 0755, true);
    }
    
    file_put_contents(UPDATE_LOG, $log_entry, FILE_APPEND);
    $update_messages[] = $message;
}

$update_messages = [];

// Include update functions
require_once 'update.php';

// Handle form submissions
$message = '';
$error = '';

// Check for success message from redirect
if (isset($_GET['msg']) && $_GET['msg'] === 'success') {
    $message = t('options_updated_success');
}


// Increment Cache Version
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['increment_cache'])) {
    try {
        // Read current config file
        $config_file = 'config.php';
        $config_content = file_get_contents($config_file);
        
        // Extract current version
        if (preg_match("/define\('CACHE_VERSION',\s*'([^']+)'\)/", $config_content, $matches)) {
            $current_version = $matches[1];
            
            // Increment version (e.g., 1.0.0 -> 1.0.1, or timestamp)
            $version_parts = explode('.', $current_version);
            if (count($version_parts) == 3) {
                $version_parts[2] = (int)$version_parts[2] + 1;
                $new_version = implode('.', $version_parts);
            } else {
                // Fallback to timestamp
                $new_version = date('YmdHis');
            }
            
            // Update config file
            $config_content = preg_replace(
                "/define\('CACHE_VERSION',\s*'[^']+'\)/",
                "define('CACHE_VERSION', '$new_version')",
                $config_content
            );
            
            file_put_contents($config_file, $config_content);
            
            $message = sprintf(t('cache_version_updated'), $current_version, $new_version);
        } else {
            $error = "Could not find CACHE_VERSION in config.php";
        }
    } catch (Exception $e) {
        $error = "Failed to update cache version: " . $e->getMessage();
    }
}

// Add New Event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    $event_name = $_POST['event_name'];
    $num_days = intval($_POST['num_days']);
    
    try {
        $db->beginTransaction();
        
        // Deactivate all previous events
        $db->exec("UPDATE events SET is_active = 0");
        
        // Create new event
        $stmt = $db->prepare("INSERT INTO events (name, is_active) VALUES (?, 1)");
        $stmt->execute([$event_name]);
        $event_id = $db->lastInsertId();
        
        // Create event days
        for ($i = 0; $i < $num_days; $i++) {
            $date = $_POST["day_date_$i"];
            $start_time = $_POST["day_start_$i"];
            $end_time = $_POST["day_end_$i"];
            $max_tables = intval($_POST["day_tables_$i"]);
            
            $stmt = $db->prepare("INSERT INTO event_days (event_id, day_number, date, start_time, end_time, max_tables) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$event_id, $i + 1, $date, $start_time, $end_time, $max_tables]);
        }
        
        $db->commit();
        
        // Log the action
        $log_dir = LOGS_DIR;
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        $log_file = $log_dir . '/' . date('Y-m-d') . '.log';
        $log_entry = date('Y-m-d H:i:s') . " - Admin " . $user_name . " (ID: $user_id) created new event: $event_name\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND);
        
        $message = t('event_created_success');
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = t('event_creation_error', ['error' => $e->getMessage()]);
    }
}

// Update Options (save to config.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_options'])) {
    try {
        // Read current config file
        $config_file = 'config.php';
        $config_content = file_get_contents($config_file);
        
        // Create backup before making changes
        copy($config_file, $config_file . '.backup');
        
        // Update config values
        $config_updates = [
            'venue_name', 'default_event_name', 'default_start_time', 'default_end_time',
            'timeline_extension', 'default_tables', 'smtp_email', 'smtp_login',
            'smtp_password', 'smtp_server', 'smtp_port', 'bgg_api_token',
            'default_language', 'active_template', 'homepage_message', 'add_game_message', 'add_player_message'
        ];
        
        foreach ($config_updates as $key) {
            if (isset($_POST[$key])) {
                $value = $_POST[$key];
                // Escape single quotes
                $value = str_replace("'", "\\'", $value);
                // Update in config content
                $pattern = "/'$key'\s*=>\s*'[^']*'/";
                $replacement = "'$key' => '$value'";
                $config_content = preg_replace($pattern, $replacement, $config_content);
            }
        }
        
        // Handle boolean and special values
        $bool_fields = [
            'allow_reserve_list', 'allow_multiple_poll_votes', 'require_emails', 'send_emails', 
            'restrict_comments', 'use_captcha', 'allow_private_messages'
        ];
        
        foreach ($bool_fields as $key) {
            if (isset($_POST[$key])) {
                $value = $_POST[$key] === '1' || $_POST[$key] === 'yes' ? 'true' : 'false';
                $pattern = "/'$key'\s*=>\s*(true|false)/";
                $replacement = "'$key' => $value";
                
                // Debug logging for send_emails
                if ($key === 'send_emails') {
                    error_log("BGG Admin: send_emails POST value: " . $_POST[$key]);
                    error_log("BGG Admin: send_emails computed value: " . $value);
                    error_log("BGG Admin: send_emails pattern: " . $pattern);
                    error_log("BGG Admin: send_emails replacement: " . $replacement);
                }
                
                $config_content = preg_replace($pattern, $replacement, $config_content);
            }
        }
        
        // Handle allow_logged_in (special field with multiple options)
        if (isset($_POST['allow_logged_in'])) {
            $value = $_POST['allow_logged_in'];
            $pattern = "/'allow_logged_in'\s*=>\s*'[^']*'/";
            $replacement = "'allow_logged_in' => '$value'";
            $config_content = preg_replace($pattern, $replacement, $config_content);
        }
        
        // Handle deletion_mode (with backward compatibility for allow_full_deletion)
        if (isset($_POST['deletion_mode'])) {
            $value = $_POST['deletion_mode'];
            
            // Try to replace deletion_mode if it exists
            $pattern = "/'deletion_mode'\s*=>\s*'[^']*'/";
            $replacement = "'deletion_mode' => '$value'";
            $new_content = preg_replace($pattern, $replacement, $config_content);
            
            // If deletion_mode wasn't found, try to replace old allow_full_deletion
            if ($new_content === $config_content) {
                // Replace the old allow_full_deletion section with new deletion_mode
                $old_pattern = "/(\/\/ Game Deletion Options:.*?)'allow_full_deletion'\s*=>\s*(true|false),/s";
                $new_section = "$1'deletion_mode' => '$value',";
                $new_content = preg_replace($old_pattern, $new_section, $config_content);
            }
            
            $config_content = $new_content;
        }
        
        // Handle verification_method
        if (isset($_POST['verification_method'])) {
            $value = $_POST['verification_method'];
            $pattern = "/'verification_method'\s*=>\s*'[^']*'/";
            $replacement = "'verification_method' => '$value'";
            $config_content = preg_replace($pattern, $replacement, $config_content);
        }
        
        // Handle default_language
        if (isset($_POST['default_language'])) {
            $value = $_POST['default_language'];
            $pattern = "/'default_language'\s*=>\s*'[^']*'/";
            $replacement = "'default_language' => '$value'";
            $config_content = preg_replace($pattern, $replacement, $config_content);
        }
        
        // Write back to config file
        $write_result = file_put_contents($config_file, $config_content);
        
        if ($write_result === false) {
            $error = t('options_update_error', ['error' => 'Failed to write config file - check file permissions']);
            error_log("BGG Admin: Failed to write config file at: " . $config_file);
        } else {
            error_log("BGG Admin: Successfully wrote " . $write_result . " bytes to config file");
            
            // Verify the file was written correctly by trying to load it
            try {
                $test_config = require $config_file;
                if (!is_array($test_config)) {
                    // Config is corrupted, restore from backup
                    if (file_exists($config_file . '.backup')) {
                        copy($config_file . '.backup', $config_file);
                    }
                    $error = t('options_update_error', ['error' => 'Config file corrupted - restored from backup']);
                    error_log("BGG Admin: Config file corrupted after write");
                } else {
                    // Log the send_emails value to verify
                    error_log("BGG Admin: Config loaded, send_emails = " . var_export($test_config['send_emails'], true));
                }
            } catch (Exception $e) {
                // Config has syntax error, restore from backup
                if (file_exists($config_file . '.backup')) {
                    copy($config_file . '.backup', $config_file);
                }
                $error = t('options_update_error', ['error' => 'Config syntax error: ' . $e->getMessage()]);
                error_log("BGG Admin: Config syntax error: " . $e->getMessage());
            }
        }
        
        // Handle password change separately (in database)
        $password_changed = false;
        if (!empty($_POST['new_admin_password'])) {
            $new_hash = password_hash($_POST['new_admin_password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$new_hash, $user_id]);
            $password_changed = true;
            
            // Update auth cookie with new password hash
            $auth_token = hash('sha256', $user_id . $new_hash . AUTH_SALT);
            setcookie('bgg_auth_token', $auth_token, time() + COOKIE_LIFETIME, '/');
        }
        
        // Log the action
        $log_dir = LOGS_DIR;
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        $log_file = $log_dir . '/' . date('Y-m-d') . '.log';
        $log_entry = date('Y-m-d H:i:s') . " - Admin " . $user_name . " (ID: $user_id) updated system options\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND);
        
        if ($password_changed) {
            $message = t('options_and_password_updated');
        } else {
            $message = t('options_updated_success');
        }
        
        // Clear opcache for config file
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($config_file, true);
        }
        
        // Force reload config with absolute path (bypass require_once cache)
        unset($config);
        $config = include $config_file;
        
        error_log("BGG Admin: Config reloaded, send_emails = " . var_export($config['send_emails'], true));
        
        // Redirect to preserve tab
        if (isset($_POST['return_tab']) && !empty($_POST['return_tab'])) {
            $return_tab = $_POST['return_tab'];
            // Validate tab name (security)
            $valid_tabs = ['logs', 'options', 'add-event', 'thumbnails', 'archives', 'update'];
            if (in_array($return_tab, $valid_tabs)) {
                header("Location: admin.php?msg=success#" . $return_tab);
                exit;
            }
        }
        
    } catch (Exception $e) {
        $error = t('options_update_error', ['error' => $e->getMessage()]);
    }
}

// Run Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_update'])) {
    $update_result = run_update();
    
    // Log the action
    $log_dir = LOGS_DIR;
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $log_file = $log_dir . '/' . date('Y-m-d') . '.log';
    $log_entry = date('Y-m-d H:i:s') . " - Admin " . $user_name . " (ID: $user_id) ran system update\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    if ($update_result['success']) {
        $message = t('update_completed_success');
    } else {
        $error = t('update_failed');
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo t('admin_panel'); ?> - <?php echo htmlspecialchars($config['venue_name']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .header { background: #2c3e50; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .header-right { display: flex; align-items: center; gap: 10px; }
        .header-right span { margin-right: 10px; }
        .header-right a { color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px; }
        .header-right a.view-site { background: #3498db; }
        .header-right a.view-site:hover { background: #2980b9; }
        .logout { background: #e74c3c !important; }
        .logout:hover { background: #c0392b !important; }
        .tabs { background: #34495e; overflow: hidden; }
        .tab-button { background: inherit; float: left; border: none; outline: none; cursor: pointer; padding: 14px 20px; transition: 0.3s; color: white; font-size: 16px; }
        .tab-button:hover { background: #4a6278; }
        .tab-button.active { background: #4CAF50; }
        .tab-content { display: none; padding: 30px; max-width: 1200px; margin: 0 auto; }
        .tab-content.active { display: block; }
        .message { padding: 15px; margin: 20px 30px; border-radius: 4px; max-width: 1200px; margin-left: auto; margin-right: auto; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group input[type="time"],
        .form-group input[type="password"],
        .form-group select,
        .form-group textarea { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; }
        .form-group textarea { min-height: 100px; }
        button[type="submit"] { background: #4CAF50; color: white; padding: 12px 30px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button[type="submit"]:hover { background: #45a049; }
        .day-config { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .day-config h4 { margin-top: 0; }
        .log-viewer { background: #f5f5f5; padding: 20px; border-radius: 4px; max-height: 700px; overflow-y: auto; }
        .log-entry { padding: 5px 0; border-bottom: 1px solid #ddd; }
        .log-controls { background: #fff; padding: 20px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #ddd; }
        .log-filter-form { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        .log-filter-form label { font-weight: bold; margin: 0; }
        .log-filter-form select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; min-width: 200px; }
        .log-stats { background: #e3f2fd; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .log-stats p { margin: 0; color: #1976d2; }
        .log-table { width: 100%; border-collapse: collapse; background: white; font-size: 13px; }
        .log-table thead { background: #34495e; color: white; position: sticky; top: 0; }
        .log-table th { padding: 12px 8px; text-align: left; font-weight: bold; border-bottom: 2px solid #2c3e50; }
        .log-table td { padding: 10px 8px; border-bottom: 1px solid #ecf0f1; vertical-align: top; }
        .log-row:hover { background: #f8f9fa; }
        .log-timestamp { white-space: nowrap; color: #7f8c8d; font-family: monospace; min-width: 150px; }
        .log-user { color: #2c3e50; font-weight: 500; min-width: 120px; }
        .log-ip { font-family: monospace; color: #e74c3c; min-width: 120px; }
        .log-action { color: #27ae60; font-weight: 500; min-width: 100px; }
        .log-details { color: #34495e; max-width: 400px; word-wrap: break-word; }
        .update-info { background: #fff3cd; padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #ffc107; }
        #num_days { max-width: 100px; }
        h2 { color: #2c3e50; margin-top: 0; }
        h3 { color: #34495e; margin-top: 30px; border-bottom: 2px solid #ecf0f1; padding-bottom: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo t('admin_panel'); ?></h1>
        <div class="header-right">
            <span><?php echo htmlspecialchars($user_name); ?></span>
            <a href="index.php" class="view-site"><?php echo t('view_site'); ?></a>
            <a href="?action=logout" class="logout"><?php echo t('logout'); ?></a>
        </div>
    </div>
    
    <div class="tabs">
        <button class="tab-button active" onclick="openTab(event, 'logs')"><?php echo t('tab_logs'); ?></button>
        <button class="tab-button" onclick="openTab(event, 'options')"><?php echo t('tab_options'); ?></button>
        <button class="tab-button" onclick="openTab(event, 'users')"><?php echo t('tab_users'); ?></button>
        <button class="tab-button" onclick="openTab(event, 'add-event')"><?php echo t('tab_add_event'); ?></button>
        <button class="tab-button" onclick="openTab(event, 'thumbnails')"><?php echo t('tab_thumbnails'); ?></button>
        <button class="tab-button" onclick="openTab(event, 'archives')"><?php echo t('tab_archives'); ?></button>
        <button class="tab-button" onclick="openTab(event, 'update')"><?php echo t('tab_update'); ?></button>
    </div>
    
    <?php if ($message): ?>
        <div class="message success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <!-- Tab 1: Logs -->
    <div id="logs" class="tab-content active">
        <h2><?php echo t('activity_logs'); ?></h2>
        
        <div class="log-controls">
            <form method="GET" class="log-filter-form">
                <input type="hidden" name="active_tab" value="logs">
                
                <label for="log-filter"><?php echo t('show_logs_from'); ?>:</label>
                <select name="log_filter" id="log-filter" onchange="this.form.submit()">
                    <option value="today" <?php echo (!isset($_GET['log_filter']) || $_GET['log_filter'] === 'today') ? 'selected' : ''; ?>>
                        <?php echo t('today'); ?>
                    </option>
                    <option value="last_100" <?php echo (isset($_GET['log_filter']) && $_GET['log_filter'] === 'last_100') ? 'selected' : ''; ?>>
                        <?php echo t('last_100_entries'); ?>
                    </option>
                    <?php
                    // Get available months from log files
                    $log_dir_check = LOGS_DIR;
                    if (is_dir($log_dir_check)) {
                        $log_files_check = array_diff(scandir($log_dir_check), ['.', '..']);
                        rsort($log_files_check);
                        
                        $months = [];
                        foreach ($log_files_check as $log_file_check) {
                            if (preg_match('/^(\d{4}-\d{2})/', $log_file_check, $matches)) {
                                $months[$matches[1]] = $matches[1];
                            }
                        }
                        
                        foreach ($months as $month) {
                            $selected = (isset($_GET['log_filter']) && $_GET['log_filter'] === $month) ? 'selected' : '';
                            $month_name = date('F Y', strtotime($month . '-01'));
                            echo "<option value='" . htmlspecialchars($month) . "' $selected>" . htmlspecialchars($month_name) . "</option>";
                        }
                    }
                    ?>
                    <option value="all" <?php echo (isset($_GET['log_filter']) && $_GET['log_filter'] === 'all') ? 'selected' : ''; ?>>
                        <?php echo t('all_logs'); ?>
                    </option>
                </select>
                
                <button type="submit" class="btn-secondary"><?php echo t('filter'); ?></button>
            </form>
        </div>
        
        <div class="log-viewer">
            <?php
            $log_filter = $_GET['log_filter'] ?? 'today';
            $all_entries = [];
            $log_dir = LOGS_DIR;
            
            if (is_dir($log_dir)) {
                $log_files = array_diff(scandir($log_dir), ['.', '..']);
                rsort($log_files);
                
                // Collect log entries based on filter
                foreach ($log_files as $log_file) {
                    $should_include = false;
                    
                    switch ($log_filter) {
                        case 'today':
                            $should_include = ($log_file === date('Y-m-d') . '.log');
                            break;
                        case 'all':
                            $should_include = true;
                            break;
                        default:
                            // Check if it's a month filter (YYYY-MM format)
                            if (preg_match('/^\d{4}-\d{2}$/', $log_filter)) {
                                $should_include = (strpos($log_file, $log_filter) === 0);
                            } elseif ($log_filter === 'last_100') {
                                $should_include = true; // Will limit after collecting
                            }
                    }
                    
                    if ($should_include) {
                        $log_content = file_get_contents($log_dir . '/' . $log_file);
                        $lines = explode("\n", $log_content);
                        
                        foreach ($lines as $line) {
                            if (trim($line) !== '') {
                                // Parse log entry to extract components
                                // Format: YYYY-MM-DD HH:MM:SS - User ID: X (IP: xxx.xxx.xxx.xxx) - action - details
                                if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) - (.+?) \(IP: (.+?)\) - (.+?) - (.+)$/', $line, $matches)) {
                                    $all_entries[] = [
                                        'timestamp' => $matches[1],
                                        'user' => $matches[2],
                                        'ip' => $matches[3],
                                        'action' => $matches[4],
                                        'details' => $matches[5],
                                        'raw' => $line
                                    ];
                                } else {
                                    // Fallback for entries that don't match expected format
                                    $all_entries[] = [
                                        'timestamp' => '',
                                        'user' => '',
                                        'ip' => '',
                                        'action' => '',
                                        'details' => $line,
                                        'raw' => $line
                                    ];
                                }
                            }
                        }
                    }
                }
                
                // Sort by timestamp (newest first)
                usort($all_entries, function($a, $b) {
                    return strcmp($b['timestamp'], $a['timestamp']);
                });
                
                // Limit to last 100 if that filter is selected
                if ($log_filter === 'last_100') {
                    $all_entries = array_slice($all_entries, 0, 100);
                }
                
                // Display entries
                if (count($all_entries) > 0) {
                    echo "<div class='log-stats'>";
                    echo "<p><strong>" . count($all_entries) . "</strong> " . t('log_entries_shown') . "</p>";
                    echo "</div>";
                    
                    echo "<table class='log-table'>";
                    echo "<thead>";
                    echo "<tr>";
                    echo "<th>" . t('timestamp') . "</th>";
                    echo "<th>" . t('user') . "</th>";
                    echo "<th>" . t('ip_address') . "</th>";
                    echo "<th>" . t('action') . "</th>";
                    echo "<th>" . t('details') . "</th>";
                    echo "</tr>";
                    echo "</thead>";
                    echo "<tbody>";
                    
                    foreach ($all_entries as $entry) {
                        echo "<tr class='log-row'>";
                        echo "<td class='log-timestamp'>" . htmlspecialchars($entry['timestamp']) . "</td>";
                        echo "<td class='log-user'>" . htmlspecialchars($entry['user']) . "</td>";
                        echo "<td class='log-ip'>" . htmlspecialchars($entry['ip']) . "</td>";
                        echo "<td class='log-action'>" . htmlspecialchars($entry['action']) . "</td>";
                        echo "<td class='log-details'>" . htmlspecialchars($entry['details']) . "</td>";
                        echo "</tr>";
                    }
                    
                    echo "</tbody>";
                    echo "</table>";
                } else {
                    echo "<p>" . t('no_logs_found') . "</p>";
                }
            } else {
                echo "<p>" . t('logs_directory_not_found') . "</p>";
            }
            ?>
        </div>
    </div>
    
    
    
    <!-- Tab 2: Options -->
    <div id="options" class="tab-content">
        <h2><?php echo t('system_options'); ?></h2>
        <form method="POST" id="options-form">
            <h3><?php echo t('general_settings'); ?></h3>
            
            <div class="form-group">
                <label><?php echo t('venue_name'); ?>:</label>
                <input type="text" name="venue_name" value="<?php echo htmlspecialchars($config['venue_name']); ?>">
            </div>
            
            <div class="form-group">
                <label><?php echo t('default_event_name'); ?>:</label>
                <input type="text" name="default_event_name" value="<?php echo htmlspecialchars($config['default_event_name']); ?>">
            </div>
            
            <div class="form-group">
                <label><?php echo t('default_start_time'); ?>:</label>
                <input type="time" name="default_start_time" value="<?php echo htmlspecialchars($config['default_start_time']); ?>">
            </div>
            
            <div class="form-group">
                <label><?php echo t('default_end_time'); ?>:</label>
                <input type="time" name="default_end_time" value="<?php echo htmlspecialchars($config['default_end_time']); ?>">
            </div>
            
            <div class="form-group">
                <label><?php echo t('timeline_extension'); ?>:</label>
                <input type="number" name="timeline_extension" value="<?php echo htmlspecialchars($config['timeline_extension']); ?>" min="0" max="12">
            </div>
            
            <div class="form-group">
                <label><?php echo t('default_tables'); ?>:</label>
                <input type="number" name="default_tables" value="<?php echo htmlspecialchars($config['default_tables']); ?>" min="1">
            </div>
            
            <div class="form-group">
                <label><?php echo t('bgg_api_token'); ?>:</label>
                <input type="text" name="bgg_api_token" value="<?php echo htmlspecialchars($config['bgg_api_token']); ?>">
            </div>
            
            <div class="form-group">
                <label><?php echo t('default_language'); ?>:</label>
                <select name="default_language">
                    <?php foreach (get_available_languages() as $code => $name): ?>
                        <option value="<?php echo $code; ?>" <?php echo $config['default_language'] === $code ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label><?php echo t('active_template'); ?>:</label>
                <select name="active_template">
                    <?php 
                    // Get available templates
                    $templates_path = TEMPLATES_DIR;
                    if (is_dir($templates_path)) {
                        $template_folders = array_diff(scandir($templates_path), ['.', '..']);
                        foreach ($template_folders as $template_folder) {
                            if (is_dir($templates_path . '/' . $template_folder)) {
                                $selected = $config['active_template'] === $template_folder ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($template_folder) . "\" $selected>" . htmlspecialchars(ucfirst($template_folder)) . "</option>";
                            }
                        }
                    }
                    ?>
                </select>
            </div>
            
            <h3><?php echo t('smtp_settings'); ?></h3>
            
            <div class="form-group">
                <label><?php echo t('smtp_email'); ?>:</label>
                <input type="text" name="smtp_email" value="<?php echo htmlspecialchars($config['smtp_email']); ?>">
            </div>
            
            <div class="form-group">
                <label><?php echo t('smtp_login'); ?>:</label>
                <input type="text" name="smtp_login" value="<?php echo htmlspecialchars($config['smtp_login']); ?>">
            </div>
            
            <div class="form-group">
                <label><?php echo t('smtp_password'); ?>:</label>
                <input type="password" name="smtp_password" value="<?php echo htmlspecialchars($config['smtp_password']); ?>">
            </div>
            
            <div class="form-group">
                <label><?php echo t('smtp_server'); ?>:</label>
                <input type="text" name="smtp_server" value="<?php echo htmlspecialchars($config['smtp_server']); ?>">
            </div>
            
            <div class="form-group">
                <label><?php echo t('smtp_port'); ?>:</label>
                <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($config['smtp_port']); ?>">
            </div>
            
            <h3><?php echo t('user_interaction_settings'); ?></h3>
            
            <div class="form-group">
                <label><?php echo t('allow_reserve_list'); ?>:</label>
                <select name="allow_reserve_list">
                    <option value="1" <?php echo $config['allow_reserve_list'] ? 'selected' : ''; ?>><?php echo t('yes'); ?></option>
                    <option value="0" <?php echo !$config['allow_reserve_list'] ? 'selected' : ''; ?>><?php echo t('no'); ?></option>
                </select>
            </div>
            
            <div class="form-group">
                <label><?php echo t('allow_multiple_poll_votes'); ?>:</label>
                <select name="allow_multiple_poll_votes">
                    <option value="1" <?php echo $config['allow_multiple_poll_votes'] ? 'selected' : ''; ?>><?php echo t('yes'); ?></option>
                    <option value="0" <?php echo !$config['allow_multiple_poll_votes'] ? 'selected' : ''; ?>><?php echo t('no'); ?></option>
                </select>
                <small style="display: block; color: #666; margin-top: 5px;">
                    <?php echo t('allow_multiple_poll_votes_description'); ?>
                </small>
            </div>
            
            <div class="form-group">
                <label><?php echo t('allow_logged_in'); ?>:</label>
                <select name="allow_logged_in" id="allow_logged_in" onchange="updateEmailRequirement()">
                    <option value="no" <?php echo $config['allow_logged_in'] === 'no' ? 'selected' : ''; ?>><?php echo t('login_no'); ?></option>
                    <option value="yes" <?php echo $config['allow_logged_in'] === 'yes' ? 'selected' : ''; ?>><?php echo t('login_yes'); ?></option>
                    <option value="required_games" <?php echo $config['allow_logged_in'] === 'required_games' ? 'selected' : ''; ?>><?php echo t('login_required_games'); ?></option>
                    <option value="required_all" <?php echo $config['allow_logged_in'] === 'required_all' ? 'selected' : ''; ?>><?php echo t('login_required_all'); ?></option>
                </select>
            </div>
            
            <div class="form-group">
                <label><?php echo t('require_emails'); ?>:</label>
                <select name="require_emails" id="require_emails">
                    <option value="yes" <?php echo $config['require_emails'] ? 'selected' : ''; ?>><?php echo t('yes'); ?></option>
                    <option value="no" <?php echo !$config['require_emails'] ? 'selected' : ''; ?>><?php echo t('no'); ?></option>
                </select>
            </div>
            
            <div class="form-group">
                <label><?php echo t('verification_method'); ?>:</label>
                <select name="verification_method">
                    <option value="email" <?php echo $config['verification_method'] === 'email' ? 'selected' : ''; ?>><?php echo t('verification_email'); ?></option>
                    <option value="link" <?php echo $config['verification_method'] === 'link' ? 'selected' : ''; ?>><?php echo t('verification_link'); ?></option>
                </select>
            </div>
            
            <div class="form-group">
                <label><?php echo t('send_emails'); ?>:</label>
                <select name="send_emails">
                    <option value="yes" <?php echo ($config['send_emails'] === 'yes' || $config['send_emails'] === true) ? 'selected' : ''; ?>><?php echo t('yes'); ?></option>
                    <option value="no" <?php echo ($config['send_emails'] === 'no' || $config['send_emails'] === false || !$config['send_emails']) ? 'selected' : ''; ?>><?php echo t('no'); ?></option>
                </select>
                <small style="display: block; margin-top: 5px;">
                    💡 <a href="admin_email_test.php" target="_blank" style="color: #3498db; font-weight: bold;">Test Email Configuration →</a>
                </small>
            </div>
            
            <div class="form-group">
                <label><?php echo t('deletion_mode'); ?>:</label>
                <select name="deletion_mode">
                    <option value="soft_only" <?php echo $config['deletion_mode'] === 'soft_only' ? 'selected' : ''; ?>><?php echo t('deletion_soft_only'); ?></option>
                    <option value="allow_choice" <?php echo $config['deletion_mode'] === 'allow_choice' ? 'selected' : ''; ?>><?php echo t('deletion_allow_choice'); ?></option>
                    <option value="hard_only" <?php echo $config['deletion_mode'] === 'hard_only' ? 'selected' : ''; ?>><?php echo t('deletion_hard_only'); ?></option>
                </select>
                <small><?php echo t('deletion_mode_help'); ?></small>
            </div>
            
            <div class="form-group">
                <label><?php echo t('restrict_comments'); ?>:</label>
                <select name="restrict_comments">
                    <option value="yes" <?php echo $config['restrict_comments'] ? 'selected' : ''; ?>><?php echo t('yes'); ?></option>
                    <option value="no" <?php echo !$config['restrict_comments'] ? 'selected' : ''; ?>><?php echo t('no'); ?></option>
                </select>
            </div>
            
            <div class="form-group">
                <label><?php echo t('use_captcha'); ?>:</label>
                <select name="use_captcha">
                    <option value="yes" <?php echo $config['use_captcha'] ? 'selected' : ''; ?>><?php echo t('yes'); ?></option>
                    <option value="no" <?php echo !$config['use_captcha'] ? 'selected' : ''; ?>><?php echo t('no'); ?></option>
                </select>
            </div>
            
            <div class="form-group">
                <label><?php echo t('allow_private_messages'); ?>:</label>
                <select name="allow_private_messages">
                    <option value="yes" <?php echo $config['allow_private_messages'] ? 'selected' : ''; ?>><?php echo t('yes'); ?></option>
                    <option value="no" <?php echo !$config['allow_private_messages'] ? 'selected' : ''; ?>><?php echo t('no'); ?></option>
                </select>
                <small><?php echo t('allow_private_messages_help'); ?></small>
            </div>
            
            <h3><?php echo t('custom_messages'); ?></h3>
            
            <div class="form-group">
                <label><?php echo t('homepage_message'); ?>:</label>
                <textarea name="homepage_message"><?php echo htmlspecialchars($config['homepage_message']); ?></textarea>
            </div>
            
            <div class="form-group">
                <label><?php echo t('add_game_message'); ?>:</label>
                <textarea name="add_game_message"><?php echo htmlspecialchars($config['add_game_message']); ?></textarea>
            </div>
            
            <div class="form-group">
                <label><?php echo t('add_player_message'); ?>:</label>
                <textarea name="add_player_message"><?php echo htmlspecialchars($config['add_player_message']); ?></textarea>
            </div>
            
            <h3><?php echo t('change_admin_password'); ?></h3>
            
            <div class="form-group">
                <label><?php echo t('new_admin_password'); ?>:</label>
                <input type="password" name="new_admin_password">
            </div>
            
            <button type="submit" name="update_options"><?php echo t('save_options'); ?></button>
        </form>
    </div>
    
    <!-- Tab 3: Users -->
    <div id="users" class="tab-content">
        <h2><?php echo t('user_management'); ?></h2>
        
        <?php
        // Get all users
        $stmt = $db->query("SELECT * FROM users ORDER BY is_admin DESC, name ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <table class="users-table">
            <thead>
                <tr>
                    <th><?php echo t('name'); ?></th>
                    <th><?php echo t('email'); ?></th>
                    <th><?php echo t('role'); ?></th>
                    <th><?php echo t('created_at'); ?></th>
                    <th><?php echo t('actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <?php if ($user['is_admin']): ?>
                            <span class="badge-admin"><?php echo t('admin'); ?></span>
                        <?php else: ?>
                            <span class="badge-user"><?php echo t('user'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                    <td class="user-actions">
                        <button onclick="toggleUserRole(<?php echo $user['id']; ?>, <?php echo $user['is_admin'] ? '0' : '1'; ?>)" class="btn-small">
                            <?php echo $user['is_admin'] ? t('make_user') : t('make_admin'); ?>
                        </button>
                        <button onclick="resetUserPassword(<?php echo $user['id']; ?>)" class="btn-small">
                            <?php echo t('reset_password'); ?>
                        </button>
                        <button onclick="deleteUser(<?php echo $user['id']; ?>)" class="btn-small btn-danger">
                            <?php echo t('delete'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <style>
        .users-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .users-table th, .users-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .users-table th { background: #34495e; color: white; font-weight: bold; }
        .users-table tr:hover { background: #f5f5f5; }
        .badge-admin { background: #e74c3c; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px; }
        .badge-user { background: #3498db; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px; }
        .user-actions { white-space: nowrap; }
        .btn-small { padding: 6px 12px; margin: 0 4px; font-size: 13px; cursor: pointer; border: none; border-radius: 3px; background: #3498db; color: white; }
        .btn-small:hover { background: #2980b9; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        </style>
    </div>
    
    
    <!-- Tab 4: Add New Event -->
    <div id="add-event" class="tab-content">
        <h2><?php echo t('add_new_event'); ?></h2>
        <form method="POST" id="event-form">
            <div class="form-group">
                <label><?php echo t('event_name'); ?>:</label>
                <input type="text" name="event_name" value="<?php echo htmlspecialchars($config['default_event_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label><?php echo t('number_of_days'); ?>:</label>
                <input type="number" id="num_days" name="num_days" value="1" min="1" max="30" onchange="generateDayFields()">
            </div>
            
            <div id="days-container">
                <!-- Day fields will be generated here by JavaScript -->
            </div>
            
            <button type="submit" name="add_event"><?php echo t('create_event'); ?></button>
        </form>
    </div>
    
    
    <!-- Tab 5: Update System -->
    <div id="update" class="tab-content">
        <h2><?php echo t('update_system'); ?></h2>
        
        <div class="update-info">
            <strong><?php echo t('warning'); ?>:</strong> <?php echo t('update_warning'); ?>
        </div>
        
        <div class="update-section">
            <h3>🔄 <?php echo t('update_files_github'); ?></h3>
            <p><strong><?php echo t('github_repository'); ?>:</strong> <a href="<?php echo GITHUB_REPO; ?>" target="_blank"><?php echo GITHUB_REPO; ?></a></p>
            <p><?php echo t('update_files_description'); ?></p>
            <form method="POST" onsubmit="return confirm('<?php echo t('update_confirm'); ?>');">
                <button type="submit" name="run_update" class="btn-update"><?php echo t('update_files_button'); ?></button>
            </form>
        </div>
        
        <div class="update-section">
            <h3>🚀 <?php echo t('update_database_schema'); ?></h3>
            <p><?php echo t('update_schema_description'); ?></p>
            <ul class="update-features">
                <li>✓ <?php echo t('update_schema_feature_1'); ?></li>
                <li>✓ <?php echo t('update_schema_feature_2'); ?></li>
                <li>✓ <?php echo t('update_schema_feature_3'); ?></li>
                <li>✓ <?php echo t('update_schema_feature_4'); ?></li>
            </ul>
            <button type="button" id="update-schema-btn" class="btn-update-schema"><?php echo t('update_schema_button'); ?></button>
            <div id="schema-update-log" style="display: none; margin-top: 15px;"></div>
        </div>
        
        <div class="update-section">
            <h3>♻️ <?php echo t('clear_browser_cache'); ?></h3>
            <p><?php echo t('cache_description'); ?></p>
            <p><strong><?php echo t('current_version'); ?>:</strong> <code style="background: #ecf0f1; padding: 2px 6px; border-radius: 3px;"><?php echo defined('CACHE_VERSION') ? CACHE_VERSION : '1.0.0'; ?></code></p>
            <ul class="update-features">
                <li>✓ <?php echo t('cache_feature_1'); ?></li>
                <li>✓ <?php echo t('cache_feature_2'); ?></li>
                <li>✓ <?php echo t('cache_feature_3'); ?></li>
                <li>✓ <?php echo t('cache_feature_4'); ?></li>
            </ul>
            <form method="POST" onsubmit="return confirm('<?php echo t('increment_cache_confirm'); ?>');">
                <button type="submit" name="increment_cache" class="btn-update">♻️ <?php echo t('increment_cache_button'); ?></button>
            </form>
        </div>
        
        <div class="update-section">
            <h3>⚙️ <?php echo t('update_config_file'); ?></h3>
            <p><?php echo t('config_description'); ?></p>
            <ul class="update-features">
                <li>✓ <?php echo t('config_feature_1'); ?></li>
                <li>✓ <?php echo t('config_feature_2'); ?></li>
                <li>✓ <?php echo t('config_feature_3'); ?></li>
                <li>✓ <?php echo t('config_feature_4'); ?></li>
            </ul>
            <button type="button" id="update-config-btn" class="btn-update-schema">⚙️ <?php echo t('update_config_button'); ?></button>
            <div id="config-update-log" style="display: none; margin-top: 15px;"></div>
        </div>
        
        <?php if (!empty($update_messages)): ?>
            <h3><?php echo t('update_log'); ?>:</h3>
            <div class="log-viewer">
                <?php foreach ($update_messages as $msg): ?>
                    <div class="log-entry"><?php echo htmlspecialchars($msg); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <style>
        .update-section { 
            background: #f8f9fa; 
            padding: 20px; 
            margin: 20px 0; 
            border-left: 4px solid #3498db; 
            border-radius: 4px; 
        }
        .update-section h3 { 
            margin-top: 0; 
            color: #2c3e50; 
        }
        .update-features { 
            margin: 10px 0; 
        }
        .update-features li { 
            margin: 5px 0; 
            color: #27ae60; 
        }
        .btn-update, .btn-update-schema { 
            background: #27ae60; 
            color: white; 
            padding: 12px 24px; 
            border: none;
            border-radius: 4px; 
            font-weight: bold; 
            cursor: pointer; 
            font-size: 14px;
        }
        .btn-update:hover, .btn-update-schema:hover { 
            background: #229954; 
        }
        .btn-update { 
            background: #3498db; 
        }
        .btn-update:hover { 
            background: #2980b9; 
        }
        #schema-update-log {
            background: #fff;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
            max-height: 400px;
            overflow-y: auto;
        }
        .schema-log-entry {
            padding: 5px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .schema-log-entry:last-child {
            border-bottom: none;
        }
        .schema-log-success { color: #27ae60; }
        .schema-log-error { color: #e74c3c; }
        .schema-log-info { color: #3498db; }
        .schema-log-warning { color: #f39c12; }
        </style>
    </div>
    
    <!-- Tab 6: Thumbnails -->
    <div id="thumbnails" class="tab-content">
        <h2><?php echo t('manage_thumbnails'); ?></h2>
        
        <div class="info-box">
            <strong>ℹ️ <?php echo t('usage'); ?>:</strong> <?php echo t('thumbnails_usage'); ?>
            <ul>
                <li><?php echo t('supported_formats'); ?>: JPG, PNG, GIF, WEBP</li>
                <li><?php echo t('maximum_size'); ?>: 2MB</li>
                <li><?php echo t('recommended_dimensions'); ?>: 200x200 pixels</li>
            </ul>
        </div>
        
        <div class="upload-section">
            <h3><?php echo t('upload_new_thumbnail'); ?></h3>
            <form id="thumbnail-upload-form" enctype="multipart/form-data">
                <div style="display: flex; gap: 10px; align-items: flex-start; margin-bottom: 20px;">
                    <input type="file" name="thumbnail" id="thumbnail-file" accept="image/jpeg,image/png,image/gif,image/webp" required style="flex: 1; padding: 10px;">
                    <button type="submit" class="btn-update"><?php echo t('upload_thumbnail'); ?></button>
                </div>
                <div id="upload-message"></div>
            </form>
        </div>
        
        <h3><?php echo t('existing_thumbnails'); ?> (<span id="thumbnail-count">0</span>)</h3>
        <div id="thumbnails-grid" class="thumbnails-grid"></div>
        
        <style>
        #thumbnail-file {
            border: 2px dashed #3498db;
            border-radius: 4px;
            padding: 15px;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }
        #thumbnail-file:hover {
            border-color: #2980b9;
            background: #e9ecef;
        }
        .thumbnails-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .thumbnail-item {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
        }
        .thumbnail-item img {
            width: 100%;
            height: 150px;
            object-fit: contain;
            border-radius: 4px;
            background: white;
            padding: 5px;
        }
        .thumbnail-name {
            margin: 10px 0;
            font-size: 13px;
            color: #2c3e50;
            word-break: break-all;
        }
        .thumbnail-actions {
            display: flex;
            gap: 5px;
            margin-top: 10px;
        }
        .btn-delete-thumb {
            flex: 1;
            background: #e74c3c;
            color: white;
            padding: 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
        }
        .btn-delete-thumb:hover {
            background: #c0392b;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #95a5a6;
        }
        </style>
    </div>
    
    <!-- Tab 7: Archives -->
    <div id="archives" class="tab-content">
        <h2><?php echo t('event_archives'); ?></h2>
        
        <div id="archives-content">
            <p><?php echo t('loading'); ?>...</p>
        </div>
        
        <style>
        .events-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .events-table th {
            background: #34495e;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        .events-table td {
            padding: 12px;
            border-bottom: 1px solid #ecf0f1;
        }
        .events-table tr:hover {
            background: #f8f9fa;
        }
        .event-active {
            background: #d5f4e6 !important;
        }
        .event-stats {
            font-size: 13px;
            color: #7f8c8d;
        }
        .event-stats span {
            margin-right: 15px;
        }
        .btn-view {
            background: #3498db;
            color: white;
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
            display: inline-block;
            margin-right: 5px;
        }
        .btn-view:hover {
            background: #2980b9;
        }
        .btn-delete {
            background: #e74c3c;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            display: inline-block;
            margin-right: 5px;
        }
        .btn-delete:hover {
            background: #c0392b;
        }
        .btn-delete:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-active {
            background: #27ae60;
            color: white;
        }
        .badge-archived {
            background: #95a5a6;
            color: white;
        }
        </style>
    </div>
    
    <script>
        // Translations for dynamic button text
        const translations = {
            updatingDatabase: <?php echo json_encode(t('updating_database')); ?>,
            updateComplete: <?php echo json_encode(t('update_complete')); ?>,
            updateFailed: <?php echo json_encode(t('update_failed_try_again')); ?>,
            updateDatabaseSchema: <?php echo json_encode(t('update_database_schema')); ?>,
            updatingConfig: <?php echo json_encode(t('updating_config_file')); ?>,
            updateConfigFile: <?php echo json_encode(t('update_config_file')); ?>
        };
        
        // Tab switching
        function openTab(evt, tabName) {
            var i, tabcontent, tabbuttons;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].className = tabcontent[i].className.replace(" active", "");
            }
            tabbuttons = document.getElementsByClassName("tab-button");
            for (i = 0; i < tabbuttons.length; i++) {
                tabbuttons[i].className = tabbuttons[i].className.replace(" active", "");
            }
            document.getElementById(tabName).className += " active";
            evt.currentTarget.className += " active";
            
            // Save tab to URL hash for persistence
            window.location.hash = tabName;
        }
        
        // Restore tab from URL hash on page load
        window.addEventListener('load', function() {
            var hash = window.location.hash.substring(1); // Remove #
            if (hash && document.getElementById(hash)) {
                // Find and click the corresponding tab button
                var tabButtons = document.getElementsByClassName('tab-button');
                for (var i = 0; i < tabButtons.length; i++) {
                    var button = tabButtons[i];
                    // Check if this button's onclick contains the hash
                    var onclick = button.getAttribute('onclick');
                    if (onclick && onclick.indexOf("'" + hash + "'") > -1) {
                        button.click();
                        break;
                    }
                }
            }
        });
        
        // Update email requirement based on login setting
        function updateEmailRequirement() {
            var loginSelect = document.getElementById('allow_logged_in');
            var emailSelect = document.getElementById('require_emails');
            
            if (loginSelect.value === 'required_games' || loginSelect.value === 'required_all') {
                emailSelect.value = 'yes';
                emailSelect.disabled = true;
            } else {
                emailSelect.disabled = false;
            }
        }
        
        // Generate day fields for event creation
        function generateDayFields() {
            var numDays = parseInt(document.getElementById('num_days').value);
            var container = document.getElementById('days-container');
            container.innerHTML = '';
            
            var today = new Date();
            
            for (var i = 0; i < numDays; i++) {
                var dayDate = new Date(today);
                dayDate.setDate(today.getDate() + i);
                var dateStr = dayDate.toISOString().split('T')[0];
                
                var dayDiv = document.createElement('div');
                dayDiv.className = 'day-config';
                dayDiv.innerHTML = `
                    <h4><?php echo t('day_number', ['number' => '']); ?>${i + 1}</h4>
                    <div class="form-group">
                        <label><?php echo t('date'); ?>:</label>
                        <input type="date" name="day_date_${i}" value="${dateStr}" required>
                    </div>
                    <div class="form-group">
                        <label><?php echo t('start_time'); ?>:</label>
                        <input type="time" name="day_start_${i}" value="<?php echo $config['default_start_time']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?php echo t('end_time'); ?>:</label>
                        <input type="time" name="day_end_${i}" value="<?php echo $config['default_end_time']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?php echo t('number_of_tables'); ?>:</label>
                        <input type="number" name="day_tables_${i}" value="<?php echo $config['default_tables']; ?>" min="1" required>
                    </div>
                `;
                container.appendChild(dayDiv);
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            generateDayFields();
            updateEmailRequirement();
        });
        
        // User management functions
        function toggleUserRole(userId, makeAdmin) {
            const action = makeAdmin ? 'make this user an admin' : 'remove admin privileges from this user';
            if (!confirm(`Are you sure you want to ${action}?`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('is_admin', makeAdmin);
            formData.append('action', 'toggle_role');
            
            fetch('ajax/user_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Operation failed');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        }
        
        function resetUserPassword(userId) {
            const newPassword = prompt('Enter new password for this user (min 6 characters):');
            if (!newPassword) return;
            
            if (newPassword.length < 6) {
                alert('Password must be at least 6 characters long');
                return;
            }
            
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('new_password', newPassword);
            formData.append('action', 'reset_password');
            
            fetch('ajax/user_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Password reset successfully');
                } else {
                    alert(data.error || 'Operation failed');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        }
        
        function deleteUser(userId) {
            if (!confirm('Are you sure you want to delete this user? This action cannot be undone!')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('action', 'delete_user');
            
            fetch('ajax/user_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Operation failed');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        }
        
        // Schema update function
        document.getElementById('update-schema-btn')?.addEventListener('click', function() {
            const btn = this;
            const logDiv = document.getElementById('schema-update-log');
            
            // Disable button and show loading
            btn.disabled = true;
            btn.textContent = translations.updatingDatabase;
            
            // Show log area
            logDiv.style.display = 'block';
            logDiv.innerHTML = '<div class="schema-log-entry schema-log-info">Starting database schema update...</div>';
            
            // Call update endpoint
            fetch('ajax/update_schema.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                // Display log messages
                if (data.log && data.log.length > 0) {
                    let logHtml = '';
                    data.log.forEach(entry => {
                        const levelClass = 'schema-log-' + entry.level.toLowerCase();
                        logHtml += `<div class="schema-log-entry ${levelClass}">${entry.message}</div>`;
                    });
                    logDiv.innerHTML = logHtml;
                }
                
                // Re-enable button
                btn.disabled = false;
                
                if (data.success) {
                    btn.textContent = '✓ ' + translations.updateComplete;
                    btn.style.background = '#27ae60';
                    
                    if (data.changes && data.changes.length === 0) {
                        alert('Database is already up to date!');
                    } else {
                        alert('Database schema updated successfully!\n\n' + data.changes.length + ' change(s) applied.');
                    }
                } else {
                    btn.textContent = translations.updateFailed;
                    btn.style.background = '#e74c3c';
                    alert('Schema update failed: ' + (data.error || 'Unknown error'));
                }
                
                // Reset button after 3 seconds
                setTimeout(() => {
                    btn.textContent = '<?php echo t('update_database_schema'); ?>';
                    btn.style.background = '';
                }, 3000);
            })
            .catch(error => {
                console.error('Error:', error);
                logDiv.innerHTML += '<div class="schema-log-entry schema-log-error">Error: ' + error.message + '</div>';
                btn.disabled = false;
                btn.textContent = translations.updateFailed;
                alert('An error occurred during schema update');
            });
        });
        
        // Config update function
        document.getElementById('update-config-btn')?.addEventListener('click', function() {
            const btn = this;
            const logDiv = document.getElementById('config-update-log');
            
            // Confirm action
            if (!confirm(<?php echo json_encode(t('update_config_confirm')); ?>)) {
                return;
            }
            
            // Disable button and show loading
            btn.disabled = true;
            btn.textContent = 'Updating Config...';
            
            // Show log area
            logDiv.style.display = 'block';
            logDiv.innerHTML = '<div class="schema-log-entry schema-log-info">Starting config file update...</div>';
            
            // Call update endpoint
            fetch('ajax/update_config.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                // Display log messages
                if (data.log && data.log.length > 0) {
                    let logHtml = '';
                    data.log.forEach(entry => {
                        const levelClass = 'schema-log-' + entry.level.toLowerCase();
                        logHtml += `<div class="schema-log-entry ${levelClass}">${entry.message}</div>`;
                    });
                    logDiv.innerHTML = logHtml;
                }
                
                // Re-enable button
                btn.disabled = false;
                
                if (data.success) {
                    btn.textContent = '✓ ' + translations.updateComplete;
                    btn.style.background = '#27ae60';
                    alert('Config file updated successfully!\n\nYour settings have been preserved.');
                } else {
                    btn.textContent = translations.updateFailed;
                    btn.style.background = '#e74c3c';
                    alert('Config update failed: ' + (data.error || 'Unknown error'));
                }
                
                // Reset button after 3 seconds
                setTimeout(() => {
                    btn.textContent = '⚙️ <?php echo t('update_config_button'); ?>';
                    btn.style.background = '';
                }, 3000);
            })
            .catch(error => {
                console.error('Error:', error);
                logDiv.innerHTML += '<div class="schema-log-entry schema-log-error">Error: ' + error.message + '</div>';
                btn.disabled = false;
                btn.textContent = translations.updateFailed;
                alert('An error occurred during config update');
            });
        });
        
        // Load thumbnails when tab is opened
        function loadThumbnails() {
            fetch('ajax/list_thumbnails.php')
                .then(response => response.json())
                .then(data => {
                    const grid = document.getElementById('thumbnails-grid');
                    const count = document.getElementById('thumbnail-count');
                    
                    if (data.success && data.thumbnails.length > 0) {
                        count.textContent = data.thumbnails.length;
                        grid.innerHTML = data.thumbnails.map(thumb => `
                            <div class="thumbnail-item">
                                <img src="thumbnails/${thumb}" alt="${thumb}">
                                <div class="thumbnail-name">${thumb}</div>
                                <div class="thumbnail-actions">
                                    <button class="btn-delete-thumb" onclick="deleteThumbnail('${thumb}')">
                                        <?php echo t('delete'); ?>
                                    </button>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        count.textContent = '0';
                        grid.innerHTML = '<div class="empty-state"><p><?php echo t('no_thumbnails_uploaded'); ?></p></div>';
                    }
                })
                .catch(error => console.error('Error loading thumbnails:', error));
        }
        
        // Upload thumbnail
        document.getElementById('thumbnail-upload-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const messageDiv = document.getElementById('upload-message');
            
            fetch('ajax/upload_thumbnail.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageDiv.innerHTML = '<div class="message success">' + data.message + '</div>';
                    this.reset();
                    loadThumbnails();
                } else {
                    messageDiv.innerHTML = '<div class="message error">' + data.error + '</div>';
                }
            })
            .catch(error => {
                messageDiv.innerHTML = '<div class="message error">Upload failed</div>';
            });
        });
        
        // Delete thumbnail
        function deleteThumbnail(filename) {
            if (!confirm('<?php echo t('confirm_delete_thumbnail'); ?>')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('filename', filename);
            
            fetch('ajax/delete_thumbnail.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadThumbnails();
                } else {
                    alert(data.error || 'Delete failed');
                }
            });
        }
        
        // Load archives when tab is opened
        function loadArchives() {
            fetch('ajax/list_archives.php')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('archives-content');
                    
                    if (data.success) {
                        container.innerHTML = data.html;
                    } else {
                        container.innerHTML = '<p class="message error">' + data.error + '</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading archives:', error);
                    document.getElementById('archives-content').innerHTML = '<p class="message error">Failed to load archives</p>';
                });
        }
        
        // Override openTab to load data when needed
        const originalOpenTab = openTab;
        openTab = function(evt, tabName) {
            originalOpenTab(evt, tabName);
            
            if (tabName === 'thumbnails') {
                loadThumbnails();
            } else if (tabName === 'archives') {
                loadArchives();
            }
        };
        
        // Preserve tab on form submission
        document.addEventListener('DOMContentLoaded', function() {
            var forms = document.querySelectorAll('#options-form, #event-form');
            forms.forEach(function(form) {
                form.addEventListener('submit', function() {
                    // Add current hash to a hidden input
                    var currentHash = window.location.hash;
                    if (currentHash) {
                        var hashInput = document.createElement('input');
                        hashInput.type = 'hidden';
                        hashInput.name = 'return_tab';
                        hashInput.value = currentHash.substring(1); // Remove #
                        form.appendChild(hashInput);
                    }
                });
            });
        });
    </script>
</body>
</html>