<?php
/**
 * User Profile Page
 * 
 * Allows logged in users to:
 * - Change their display name
 * - Change their email
 * - Change their password
 */

// Load configuration
$config = require_once 'config.php';

// Load translation system
require_once 'includes/translations.php';

// Load auth helper
require_once 'includes/auth.php';

// Database connection
try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Require login
require_login($db, 'profile.php');

// Get current user
$current_user = get_current_user($db);

$message = '';
$error = '';

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout_user();
    header('Location: index.php');
    exit;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = trim($_POST['new_password']);
    
    $result = update_user_profile($db, $current_user['id'], $name, $email, $current_password, $new_password);
    
    if ($result['success']) {
        $message = t('profile_updated_successfully');
        // Reload user data
        $current_user = get_current_user($db);
    } else {
        $error = t($result['error']);
    }
}

// Get user's activity summary
$stmt = $db->prepare("SELECT COUNT(*) as game_count FROM games WHERE created_by_user_id = ? AND is_active = 1");
$stmt->execute([$current_user['id']]);
$games_created = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) as signup_count FROM players WHERE user_id = ?");
$stmt->execute([$current_user['id']]);
$games_joined = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) as comment_count FROM comments WHERE user_id = ?");
$stmt->execute([$current_user['id']]);
$comments_made = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo t('user_profile'); ?> - BGG Signup</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: #ecf0f1; 
            margin: 0; 
            padding: 20px;
        }
        .container { 
            max-width: 800px; 
            margin: 30px auto; 
            background: white; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: #3498db;
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .header .user-info {
            text-align: right;
        }
        .header .user-name {
            font-size: 18px;
            font-weight: bold;
        }
        .header .user-email {
            font-size: 14px;
            opacity: 0.9;
        }
        .nav-buttons {
            background: #2c3e50;
            padding: 15px 30px;
            display: flex;
            gap: 10px;
        }
        .nav-buttons a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 4px;
            background: #34495e;
            transition: background 0.3s;
        }
        .nav-buttons a:hover {
            background: #4a6278;
        }
        .nav-buttons a.logout {
            background: #e74c3c;
            margin-left: auto;
        }
        .nav-buttons a.logout:hover {
            background: #c0392b;
        }
        .content {
            padding: 30px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 2px solid #e9ecef;
        }
        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            color: #3498db;
            margin: 10px 0;
        }
        .stat-card .label {
            color: #7f8c8d;
            font-size: 14px;
        }
        h2 {
            color: #2c3e50;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 10px;
            margin-top: 0;
        }
        .form-group { 
            margin-bottom: 20px; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 5px; 
            font-weight: bold; 
            color: #34495e;
        }
        .form-group input { 
            width: 100%; 
            padding: 12px; 
            box-sizing: border-box; 
            border: 1px solid #ddd; 
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
        }
        .form-group small {
            color: #7f8c8d;
            font-size: 13px;
        }
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .form-section h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        button { 
            padding: 12px 30px; 
            background: #3498db; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 16px;
            font-weight: bold;
            transition: background 0.3s;
        }
        button:hover { 
            background: #2980b9; 
        }
        .error { 
            color: #e74c3c; 
            background: #fadbd8; 
            padding: 12px; 
            border-radius: 4px; 
            margin-bottom: 20px;
        }
        .message { 
            color: #27ae60; 
            background: #d5f4e6; 
            padding: 12px; 
            border-radius: 4px; 
            margin-bottom: 20px;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo t('user_profile'); ?></h1>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($current_user['name']); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($current_user['email']); ?></div>
            </div>
        </div>
        
        <div class="nav-buttons">
            <a href="index.php">← <?php echo t('back_to_homepage'); ?></a>
            <?php if ($current_user['is_admin']): ?>
                <a href="admin.php"><?php echo t('admin_panel'); ?></a>
            <?php endif; ?>
            <a href="?action=logout" class="logout"><?php echo t('logout'); ?></a>
        </div>
        
        <div class="content">
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <h2><?php echo t('your_activity'); ?></h2>
            <div class="stats">
                <div class="stat-card">
                    <div class="label"><?php echo t('games_created'); ?></div>
                    <div class="number"><?php echo $games_created; ?></div>
                </div>
                <div class="stat-card">
                    <div class="label"><?php echo t('games_joined'); ?></div>
                    <div class="number"><?php echo $games_joined; ?></div>
                </div>
                <div class="stat-card">
                    <div class="label"><?php echo t('comments_made'); ?></div>
                    <div class="number"><?php echo $comments_made; ?></div>
                </div>
            </div>
            
            <h2><?php echo t('update_profile'); ?></h2>
            
            <div class="info-box">
                <?php echo t('profile_update_info'); ?>
            </div>
            
            <form method="POST">
                <div class="form-section">
                    <h3><?php echo t('basic_information'); ?></h3>
                    
                    <div class="form-group">
                        <label><?php echo t('display_name'); ?>:</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($current_user['name']); ?>" required>
                        <small><?php echo t('name_visible_to_others'); ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label><?php echo t('email'); ?>:</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                        <small><?php echo t('email_used_for_login'); ?></small>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><?php echo t('change_password'); ?></h3>
                    
                    <div class="form-group">
                        <label><?php echo t('new_password'); ?>:</label>
                        <input type="password" name="new_password" minlength="6">
                        <small><?php echo t('leave_blank_keep_current'); ?></small>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><?php echo t('confirm_changes'); ?></h3>
                    
                    <div class="form-group">
                        <label><?php echo t('current_password'); ?>:</label>
                        <input type="password" name="current_password" required>
                        <small><?php echo t('required_to_confirm_changes'); ?></small>
                    </div>
                </div>
                
                <button type="submit" name="update_profile"><?php echo t('save_changes'); ?></button>
            </form>
        </div>
    </div>
</body>
</html>