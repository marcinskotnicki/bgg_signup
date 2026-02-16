<?php
/**
 * Email Test & Debug Page
 * Admin-only page to test email configuration
 */

session_start();

// Load configuration
$config = require_once 'config.php';

// Load auth helper
require_once 'includes/auth.php';

// Load translations
require_once 'includes/translations.php';

// Load email helper
require_once 'includes/email.php';

// Database connection
try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed");
}

// Get current user
$current_user = get_current_user($db);

// Check if user is admin
if (!$current_user || !$current_user['is_admin']) {
    header('Location: admin.php');
    exit;
}

$test_result = null;
$config_issues = [];

// Check configuration
if ($config['send_emails'] !== 'yes') {
    $config_issues[] = "Email sending is DISABLED in settings. Go to Admin Panel → Options and set 'Send Email Notifications' to 'Yes'";
}

if (empty($config['smtp_email'])) {
    $config_issues[] = "SMTP Email is not configured. This will use 'noreply@{$_SERVER['HTTP_HOST']}' as sender.";
}

// Handle test email submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $test_email = trim($_POST['test_email']);
    
    if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        // Temporarily enable emails for testing
        $original_send_emails = $config['send_emails'];
        $config['send_emails'] = 'yes';
        
        $subject = "BGG Signup System - Test Email";
        $message = email_template([
            'title' => "Test Email Successful!",
            'content' => "<p>If you're reading this, your email configuration is working correctly!</p>" .
                "<p><strong>Test Details:</strong></p>" .
                "<ul>" .
                "<li>Sent at: " . date('Y-m-d H:i:s') . "</li>" .
                "<li>Server: " . $_SERVER['HTTP_HOST'] . "</li>" .
                "<li>PHP Version: " . phpversion() . "</li>" .
                "</ul>"
        ]);
        
        // Enable error reporting for debugging
        $old_error_level = error_reporting(E_ALL);
        ini_set('display_errors', 1);
        
        // Capture any errors
        ob_start();
        $result = send_email($test_email, $subject, $message, $config);
        $output = ob_get_clean();
        
        // Restore settings
        error_reporting($old_error_level);
        $config['send_emails'] = $original_send_emails;
        
        if ($result) {
            $test_result = [
                'success' => true,
                'message' => "Test email sent successfully to {$test_email}! Check your inbox (and spam folder)."
            ];
        } else {
            $last_error = error_get_last();
            $test_result = [
                'success' => false,
                'message' => "Failed to send test email.",
                'error' => $last_error ? $last_error['message'] : 'Unknown error',
                'output' => $output
            ];
        }
    } else {
        $test_result = [
            'success' => false,
            'message' => "Invalid email address"
        ];
    }
}

// Check PHP mail configuration
$mail_configured = function_exists('mail');
$sendmail_path = ini_get('sendmail_path');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Email Test - BGG Signup System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #2c3e50;
            margin-top: 0;
        }
        
        h2 {
            color: #34495e;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-top: 30px;
        }
        
        .status-box {
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        
        .status-ok {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .status-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        
        .status-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .config-item {
            padding: 10px;
            margin: 10px 0;
            background: #f8f9fa;
            border-left: 4px solid #3498db;
            font-family: monospace;
        }
        
        .form-group {
            margin: 20px 0;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        button {
            background: #3498db;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        
        button:hover {
            background: #2980b9;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #3498db;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        
        ul {
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Email System Test & Debug</h1>
        
        <?php if ($test_result): ?>
            <div class="status-box <?php echo $test_result['success'] ? 'status-ok' : 'status-error'; ?>">
                <strong><?php echo $test_result['success'] ? '✓ Success!' : '✗ Error:'; ?></strong>
                <p><?php echo htmlspecialchars($test_result['message']); ?></p>
                <?php if (!$test_result['success'] && isset($test_result['error'])): ?>
                    <p><strong>Error details:</strong></p>
                    <pre><?php echo htmlspecialchars($test_result['error']); ?></pre>
                <?php endif; ?>
                <?php if (!$test_result['success'] && isset($test_result['output']) && !empty($test_result['output'])): ?>
                    <p><strong>Output:</strong></p>
                    <pre><?php echo htmlspecialchars($test_result['output']); ?></pre>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <h2>Configuration Status</h2>
        
        <?php if (empty($config_issues)): ?>
            <div class="status-box status-ok">
                ✓ Email configuration looks good!
            </div>
        <?php else: ?>
            <?php foreach ($config_issues as $issue): ?>
                <div class="status-box status-warning">
                    ⚠ <?php echo htmlspecialchars($issue); ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div class="config-item">
            <strong>Send Emails:</strong> <?php echo $config['send_emails'] === 'yes' ? '✓ Enabled' : '✗ Disabled'; ?>
        </div>
        
        <div class="config-item">
            <strong>SMTP Email:</strong> <?php echo htmlspecialchars($config['smtp_email'] ?: '(not configured)'); ?>
        </div>
        
        <div class="config-item">
            <strong>SMTP Server:</strong> <?php echo htmlspecialchars($config['smtp_server'] ?: '(not configured)'); ?>
        </div>
        
        <div class="config-item">
            <strong>SMTP Port:</strong> <?php echo htmlspecialchars($config['smtp_port'] ?: '(not configured)'); ?>
        </div>
        
        <h2>Server Configuration</h2>
        
        <div class="config-item">
            <strong>PHP mail() function:</strong> <?php echo $mail_configured ? '✓ Available' : '✗ Not available'; ?>
        </div>
        
        <div class="config-item">
            <strong>Sendmail path:</strong> <?php echo htmlspecialchars($sendmail_path ?: '(not configured)'); ?>
        </div>
        
        <div class="config-item">
            <strong>Server hostname:</strong> <?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?>
        </div>
        
        <h2>Send Test Email</h2>
        
        <form method="POST">
            <div class="form-group">
                <label for="test_email">Send test email to:</label>
                <input type="email" id="test_email" name="test_email" 
                       value="<?php echo htmlspecialchars($current_user['email']); ?>" 
                       required>
                <small>Default: your admin email</small>
            </div>
            
            <button type="submit" name="send_test">Send Test Email</button>
        </form>
        
        <h2>Troubleshooting Guide</h2>
        
        <h3>If test email fails:</h3>
        <ul>
            <li><strong>Check spam folder</strong> - Emails from PHP mail() often go to spam</li>
            <li><strong>Enable email sending</strong> - Go to Admin Panel → Options → "Send Email Notifications" → Yes</li>
            <li><strong>Configure SMTP</strong> - Some servers require SMTP settings (contact your hosting provider)</li>
            <li><strong>Check server logs</strong> - Look for mail-related errors in your hosting control panel</li>
            <li><strong>Contact hosting support</strong> - They may need to enable mail() function or configure sendmail</li>
        </ul>
        
        <h3>Common issues:</h3>
        <ul>
            <li><strong>"mail() has been disabled"</strong> - Contact your hosting provider to enable it</li>
            <li><strong>Emails go to spam</strong> - Configure SMTP with proper authentication</li>
            <li><strong>No error but no email</strong> - Check server mail queue and logs</li>
        </ul>
        
        <h3>For shared hosting (like dhosting.pl):</h3>
        <ul>
            <li>SMTP settings are usually required</li>
            <li>Get SMTP details from hosting control panel (cPanel, DirectAdmin, etc.)</li>
            <li>Typical SMTP server: smtp.yourdomain.pl or mail.yourdomain.pl</li>
            <li>Port: Usually 587 (with STARTTLS) or 465 (with SSL)</li>
        </ul>
        
        <a href="admin.php" class="back-link">← Back to Admin Panel</a>
    </div>
</body>
</html>
