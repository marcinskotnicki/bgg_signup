<?php
/**
 * Reset Password Page
 * Reset password using token from email
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
    die('Database connection failed');
}

// If already logged in, redirect to index
$current_user = get_current_user($db);
if ($current_user) {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';
$token = isset($_GET['token']) ? $_GET['token'] : '';
$token_valid = false;
$user_id = null;

// Verify token
if ($token) {
    $stmt = $db->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($reset) {
        if (strtotime($reset['expires_at']) > time()) {
            $token_valid = true;
            $user_id = $reset['user_id'];
        } else {
            $error = t('reset_link_expired');
        }
    } else {
        $error = t('invalid_reset_link');
    }
} else {
    $error = t('invalid_reset_link');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password']) && $token_valid) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = t('all_fields_required');
    } elseif (strlen($new_password) < 6) {
        $error = t('password_too_short');
    } elseif ($new_password !== $confirm_password) {
        $error = t('passwords_dont_match');
    } else {
        // Update password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$password_hash, $user_id]);
        
        // Delete used token
        $stmt = $db->prepare("DELETE FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        
        // Log activity
        require_once 'includes/logging.php';
        log_activity($db, $user_id, 'password_reset', "Password reset via email link");
        
        $message = t('password_reset_success');
        $token_valid = false; // Prevent form from showing again
    }
}

$venue_name = isset($config['venue_name']) ? $config['venue_name'] : 'BGG Signup';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(get_current_language()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('reset_password'); ?> - <?php echo htmlspecialchars($venue_name); ?></title>
    <link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-page">
    <div class="reset-container">
        <h1><?php echo t('reset_password'); ?></h1>
        <p class="subtitle"><?php echo t('enter_new_password'); ?></p>
        
        <?php if ($message): ?>
            <div class="message success">
                <?php echo htmlspecialchars($message); ?>
                <p style="margin-top: 10px;">
                    <a href="login.php" style="color: #155724; font-weight: bold;"><?php echo t('login_now'); ?> →</a>
                </p>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($token_valid && !$message): ?>
            <form method="POST">
                <div class="form-group">
                    <label for="new_password"><?php echo t('new_password'); ?>:</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6" autofocus>
                    <small><?php echo t('password_min_6_chars'); ?></small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password"><?php echo t('confirm_password'); ?>:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                
                <button type="submit" name="reset_password"><?php echo t('reset_password_button'); ?></button>
            </form>
        <?php endif; ?>
        
        <div class="links">
            <a href="login.php"><?php echo t('back_to_login'); ?></a>
            <span style="color: #ddd; margin: 0 10px;">|</span>
            <a href="index.php"><?php echo t('back_to_homepage'); ?></a>
        </div>
    </div>
</body>
</html>
