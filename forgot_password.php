<?php
/**
 * Forgot Password Page
 * Request password reset email
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = t('email_required');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('invalid_email');
    } else {
        // Check if user exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Delete any existing tokens for this user
            $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            
            // Insert new token
            $stmt = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $token, $expires_at]);
            
            // Send reset email
            require_once 'includes/email.php';
            $reset_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                         "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=$token";
            
            try {
                email_password_reset($db, $email, $reset_link);
                $message = t('password_reset_email_sent');
            } catch (Exception $e) {
                error_log("Password reset email failed: " . $e->getMessage());
                $error = t('email_send_failed');
            }
        } else {
            // Don't reveal if email exists or not (security)
            $message = t('password_reset_email_sent');
        }
    }
}

$venue_name = isset($config['venue_name']) ? $config['venue_name'] : 'BGG Signup';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(get_current_language()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('forgot_password'); ?> - <?php echo htmlspecialchars($venue_name); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .forgot-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            max-width: 450px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        input[type="email"]:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover {
            background: #5568d3;
        }
        .message {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 14px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .links {
            margin-top: 20px;
            text-align: center;
        }
        .links a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        .links a:hover {
            text-decoration: underline;
        }
        .divider {
            margin: 15px 0;
            text-align: center;
            color: #999;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <h1><?php echo t('forgot_password'); ?></h1>
        <p class="subtitle"><?php echo t('forgot_password_instructions'); ?></p>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="email"><?php echo t('email'); ?>:</label>
                <input type="email" id="email" name="email" required autofocus 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <button type="submit" name="request_reset"><?php echo t('send_reset_link'); ?></button>
        </form>
        
        <div class="divider">·  ·  ·</div>
        
        <div class="links">
            <a href="login.php"><?php echo t('back_to_login'); ?></a>
            <span style="color: #ddd; margin: 0 10px;">|</span>
            <a href="index.php"><?php echo t('back_to_homepage'); ?></a>
        </div>
    </div>
</body>
</html>
