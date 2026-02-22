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
    $preferred_template = isset($_POST['preferred_template']) ? $_POST['preferred_template'] : null;
    
    // If 'default' is selected, set to null (use site default)
    if ($preferred_template === 'default') {
        $preferred_template = null;
    }
    
    $result = update_user_profile($db, $current_user['id'], $name, $email, $current_password, $new_password, $preferred_template);
    
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
    <link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-page">
    <div class="auth-container">
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
                    
                    <div class="form-group">
                        <label><?php echo t('preferred_template'); ?>:</label>
                        <select name="preferred_template">
                            <option value="default" <?php echo empty($current_user['preferred_template']) ? 'selected' : ''; ?>>
                                <?php echo t('use_site_default'); ?> (<?php echo ucfirst($config['active_template']); ?>)
                            </option>
                            <?php 
                            // Get available templates
                            $templates_path = TEMPLATES_DIR;
                            if (is_dir($templates_path)) {
                                $template_folders = array_diff(scandir($templates_path), ['.', '..']);
                                foreach ($template_folders as $template_folder) {
                                    if (is_dir($templates_path . '/' . $template_folder)) {
                                        $selected = ($current_user['preferred_template'] === $template_folder) ? 'selected' : '';
                                        echo "<option value=\"" . htmlspecialchars($template_folder) . "\" $selected>" 
                                           . htmlspecialchars(ucfirst($template_folder)) . "</option>";
                                    }
                                }
                            }
                            ?>
                        </select>
                        <small><?php echo t('template_preference_description'); ?></small>
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
                        <input type="password" name="current_password">
                        <small><?php echo t('required_for_password_email_changes'); ?></small>
                    </div>
                </div>
                
                <button type="submit" name="update_profile"><?php echo t('save_changes'); ?></button>
            </form>
        </div>
    </div>
</body>
</html>
