<?php
/**
 * Login and Registration Page
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

// Check if user is already logged in
if (is_logged_in($db)) {
    $redirect = $_GET['redirect'] ?? 'index.php';
    header('Location: ' . $redirect);
    exit;
}

$message = '';
$error = '';
$show_register = isset($_GET['register']) || isset($_POST['register']);

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    $result = login_user($db, $email, $password);
    
    if ($result['success']) {
        $redirect = $_GET['redirect'] ?? 'index.php';
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = t($result['error']);
    }
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    
    if ($password !== $password_confirm) {
        $error = t('passwords_dont_match');
    } else {
        $result = register_user($db, $name, $email, $password);
        
        if ($result['success']) {
            $redirect = $_GET['redirect'] ?? 'index.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = t($result['error']);
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $show_register ? t('register') : t('login'); ?> - BGG Signup</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: #ecf0f1; 
            margin: 0; 
            padding: 20px;
        }
        .container { 
            max-width: 450px; 
            margin: 50px auto; 
            background: white; 
            padding: 40px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { 
            color: #2c3e50; 
            text-align: center; 
            margin-top: 0;
            margin-bottom: 30px;
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
        button { 
            width: 100%; 
            padding: 14px; 
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
            text-align: center;
        }
        .message { 
            color: #27ae60; 
            background: #d5f4e6; 
            padding: 12px; 
            border-radius: 4px; 
            margin-bottom: 20px;
            text-align: center;
        }
        .toggle-form { 
            text-align: center; 
            margin-top: 20px; 
            color: #7f8c8d;
        }
        .toggle-form a { 
            color: #3498db; 
            text-decoration: none; 
            font-weight: bold;
        }
        .toggle-form a:hover { 
            text-decoration: underline; 
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #7f8c8d;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo $show_register ? t('register') : t('login'); ?></h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if (!$show_register): ?>
            <!-- Login Form -->
            <form method="POST">
                <div class="form-group">
                    <label><?php echo t('email'); ?>:</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autofocus>
                </div>
                
                <div class="form-group">
                    <label><?php echo t('password'); ?>:</label>
                    <input type="password" name="password" required>
                </div>
                
                <button type="submit" name="login"><?php echo t('login'); ?></button>
                
                <div class="forgot-password-link" style="text-align: center; margin-top: 15px;">
                    <a href="forgot_password.php" style="color: #667eea; text-decoration: none; font-size: 14px;">
                        <?php echo t('forgot_password'); ?>?
                    </a>
                </div>
            </form>
            
            <div class="toggle-form">
                <?php echo t('dont_have_account'); ?> 
                <a href="?register<?php echo isset($_GET['redirect']) ? '&redirect=' . urlencode($_GET['redirect']) : ''; ?>">
                    <?php echo t('register_now'); ?>
                </a>
            </div>
        <?php else: ?>
            <!-- Registration Form -->
            <form method="POST">
                <div class="form-group">
                    <label><?php echo t('name'); ?>:</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required autofocus>
                </div>
                
                <div class="form-group">
                    <label><?php echo t('email'); ?>:</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label><?php echo t('password'); ?>:</label>
                    <input type="password" name="password" required minlength="6">
                    <small style="color: #7f8c8d;"><?php echo t('password_min_6_chars'); ?></small>
                </div>
                
                <div class="form-group">
                    <label><?php echo t('confirm_password'); ?>:</label>
                    <input type="password" name="password_confirm" required minlength="6">
                </div>
                
                <button type="submit" name="register"><?php echo t('register'); ?></button>
            </form>
            
            <div class="toggle-form">
                <?php echo t('already_have_account'); ?> 
                <a href="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>">
                    <?php echo t('login_here'); ?>
                </a>
            </div>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="index.php">← <?php echo t('back_to_homepage'); ?></a>
        </div>
    </div>
</body>
</html>