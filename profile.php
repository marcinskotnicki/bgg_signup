<?php
/**
 * User Profile Page - Clean version with external CSS
 */

// Load configuration
$config = require_once 'config.php';
require_once 'includes/translations.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Check login
$current_user = get_current_user($db);
if (!$current_user) {
    header('Location: login.php');
    exit;
}

$error = '';
$message = '';

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_name') {
        $new_name = trim($_POST['name'] ?? '');
        if (empty($new_name)) {
            $error = t('name_required');
        } else {
            $stmt = $db->prepare("UPDATE users SET name = ? WHERE id = ?");
            $stmt->execute([$new_name, $current_user['id']]);
            $message = t('name_updated');
            $current_user['name'] = $new_name;
        }
    }
    
    if ($action === 'update_email') {
        $new_email = trim($_POST['email'] ?? '');
        if (empty($new_email)) {
            $error = t('email_required');
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = t('invalid_email');
        } else {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$new_email, $current_user['id']]);
            if ($stmt->fetch()) {
                $error = t('email_already_exists');
            } else {
                $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$new_email, $current_user['id']]);
                $message = t('email_updated');
                $current_user['email'] = $new_email;
            }
        }
    }
    
    if ($action === 'update_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = t('all_password_fields_required');
        } elseif (!password_verify($current_password, $current_user['password'])) {
            $error = t('current_password_incorrect');
        } elseif ($new_password !== $confirm_password) {
            $error = t('passwords_do_not_match');
        } elseif (strlen($new_password) < 6) {
            $error = t('password_too_short');
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $current_user['id']]);
            $message = t('password_updated');
        }
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get user stats
$stmt = $db->prepare("SELECT COUNT(*) FROM games WHERE created_by_user_id = ?");
$stmt->execute([$current_user['id']]);
$games_created = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM players WHERE user_id = ?");
$stmt->execute([$current_user['id']]);
$games_joined = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ?");
$stmt->execute([$current_user['id']]);
$comments_made = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('user_profile'); ?> - BGG Signup</title>
    <link rel="stylesheet" href="css/auth.css">
</head>
<body class="profile-page">
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
            <p><?php echo t('profile_password_notice'); ?></p>
            
            <div class="form-section">
                <h3><?php echo t('basic_information'); ?></h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_name">
                    <div class="form-group">
                        <label><?php echo t('display_name'); ?>:</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($current_user['name']); ?>" required>
                        <small><?php echo t('display_name_visible'); ?></small>
                    </div>
                    <button type="submit"><?php echo t('update_name'); ?></button>
                </form>
            </div>
            
            <div class="form-section">
                <h3><?php echo t('email'); ?></h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_email">
                    <div class="form-group">
                        <label><?php echo t('email_address'); ?>:</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                        <small><?php echo t('email_login_notice'); ?></small>
                    </div>
                    <button type="submit"><?php echo t('update_email'); ?></button>
                </form>
            </div>
            
            <div class="form-section">
                <h3><?php echo t('change_password'); ?></h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_password">
                    <div class="form-group">
                        <label><?php echo t('current_password'); ?>:</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label><?php echo t('new_password'); ?>:</label>
                        <input type="password" name="new_password" required>
                        <small><?php echo t('password_min_length'); ?></small>
                    </div>
                    <div class="form-group">
                        <label><?php echo t('confirm_password'); ?>:</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button type="submit"><?php echo t('update_password'); ?></button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
