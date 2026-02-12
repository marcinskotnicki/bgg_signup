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
        <title><?php echo t('admin_login'); ?> - BGG Signup</title>
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
        
        // Update config values
        $config_updates = [
            'venue_name', 'default_event_name', 'default_start_time', 'default_end_time',
            'timeline_extension', 'default_tables', 'smtp_email', 'smtp_login',
            'smtp_password', 'smtp_server', 'smtp_port', 'bgg_api_token',
            'default_language', 'homepage_message', 'add_game_message', 'add_player_message'
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
            'allow_reserve_list', 'require_emails', 'send_emails', 
            'allow_full_deletion', 'restrict_comments', 'use_captcha'
        ];
        
        foreach ($bool_fields as $key) {
            if (isset($_POST[$key])) {
                $value = $_POST[$key] === '1' || $_POST[$key] === 'yes' ? 'true' : 'false';
                $pattern = "/'$key'\s*=>\s*(true|false)/";
                $replacement = "'$key' => $value";
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
        file_put_contents($config_file, $config_content);
        
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
        
        // Reload config
        $config = require 'config.php';
        
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
    <title><?php echo t('admin_panel'); ?> - BGG Signup</title>
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
        .log-viewer { background: #f5f5f5; padding: 20px; border-radius: 4px; max-height: 600px; overflow-y: auto; font-family: monospace; font-size: 14px; }
        .log-entry { padding: 5px 0; border-bottom: 1px solid #ddd; }
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
        <button class="tab-button" onclick="openTab(event, 'add-event')"><?php echo t('tab_add_event'); ?></button>
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
        <div class="log-viewer">
            <?php
            $log_dir = LOGS_DIR;
            if (is_dir($log_dir)) {
                $log_files = array_diff(scandir($log_dir), ['.', '..']);
                // Sort by date (newest first)
                rsort($log_files);
                
                if (count($log_files) > 0) {
                    foreach ($log_files as $log_file) {
                        echo "<h3>" . htmlspecialchars($log_file) . "</h3>";
                        $log_content = file_get_contents($log_dir . '/' . $log_file);
                        $lines = explode("
", $log_content);
                        // Reverse to show newest first
                        $lines = array_reverse($lines);
                        foreach ($lines as $line) {
                            if (trim($line) !== '') {
                                echo "<div class='log-entry'>" . htmlspecialchars($line) . "</div>";
                            }
                        }
                    }
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
        <form method="POST">
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
                    <option value="yes" <?php echo $config['send_emails'] ? 'selected' : ''; ?>><?php echo t('yes'); ?></option>
                    <option value="no" <?php echo !$config['send_emails'] ? 'selected' : ''; ?>><?php echo t('no'); ?></option>
                </select>
            </div>
            
            <div class="form-group">
                <label><?php echo t('allow_full_deletion'); ?>:</label>
                <select name="allow_full_deletion">
                    <option value="yes" <?php echo $config['allow_full_deletion'] ? 'selected' : ''; ?>><?php echo t('deletion_allowed'); ?></option>
                    <option value="no" <?php echo !$config['allow_full_deletion'] ? 'selected' : ''; ?>><?php echo t('deletion_soft_only'); ?></option>
                </select>
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
    
    
    <!-- Tab 3: Add New Event -->
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
    
    
    <!-- Tab 4: Update System -->
    <div id="update" class="tab-content">
        <h2><?php echo t('update_system'); ?></h2>
        
        <div class="update-info">
            <strong><?php echo t('warning'); ?>:</strong> <?php echo t('update_warning'); ?>
        </div>
        
        <p><strong><?php echo t('github_repository'); ?>:</strong> <a href="<?php echo GITHUB_REPO; ?>" target="_blank"><?php echo GITHUB_REPO; ?></a></p>
        
        <form method="POST" onsubmit="return confirm('<?php echo t('update_confirm'); ?>');">
            <button type="submit" name="run_update"><?php echo t('run_update'); ?></button>
        </form>
        
        <?php if (!empty($update_messages)): ?>
            <h3><?php echo t('update_log'); ?>:</h3>
            <div class="log-viewer">
                <?php foreach ($update_messages as $msg): ?>
                    <div class="log-entry"><?php echo htmlspecialchars($msg); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
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
        }
        
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
    </script>
</body>
</html>