<?php
/**
 * AJAX Handler: Send Verification Code
 * Sends the verification code to user's email when they try to resign
 */

// Start output buffering
ob_start();

header('Content-Type: application/json');

// Load configuration
$config = require_once '../config.php';

// Load translations
require_once '../includes/translations.php';

// Database connection
try {
    $db = new PDO('sqlite:../' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Get parameters
$player_id = intval($_POST['player_id'] ?? 0);
$poll_id = intval($_POST['poll_id'] ?? 0);
$game_id = intval($_POST['game_id'] ?? 0);

try {
    if ($player_id) {
        // Get player details
        $stmt = $db->prepare("
            SELECT p.player_email, p.verification_code, p.user_id, g.name as game_name
            FROM players p
            JOIN games g ON p.game_id = g.id
            WHERE p.id = ?
        ");
        $stmt->execute([$player_id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$record) {
            echo json_encode(['success' => false, 'error' => 'Player not found']);
            exit;
        }
        
        // Logged-in users don't need codes
        if ($record['user_id']) {
            echo json_encode([
                'success' => false,
                'error' => 'Logged-in players do not need verification codes. Please log in to resign.',
                'requires_login' => true
            ]);
            exit;
        }
        
        $email = $record['player_email'];
        $code = $record['verification_code'];
        $context = t('game') . ': ' . $record['game_name'];
        
    } elseif ($poll_id) {
        // Get poll details
        $stmt = $db->prepare("
            SELECT p.creator_email, p.verification_code, p.created_by_user_id, p.question
            FROM polls p
            WHERE p.id = ?
        ");
        $stmt->execute([$poll_id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$record) {
            echo json_encode(['success' => false, 'error' => 'Poll not found']);
            exit;
        }
        
        // Logged-in users don't need codes
        if ($record['created_by_user_id']) {
            echo json_encode([
                'success' => false,
                'error' => 'Please log in to edit or delete this poll.',
                'requires_login' => true
            ]);
            exit;
        }
        
        $email = $record['creator_email'];
        $code = $record['verification_code'];
        $context = t('poll') . ': ' . $record['question'];
        
    } elseif ($game_id) {
        // Get game details
        $stmt = $db->prepare("
            SELECT g.creator_email, g.verification_code, g.created_by_user_id, g.name as game_name
            FROM games g
            WHERE g.id = ?
        ");
        $stmt->execute([$game_id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$record) {
            echo json_encode(['success' => false, 'error' => 'Game not found']);
            exit;
        }
        
        // Logged-in users don't need codes
        if ($record['created_by_user_id']) {
            echo json_encode([
                'success' => false,
                'error' => 'Please log in to edit or delete this game.',
                'requires_login' => true
            ]);
            exit;
        }
        
        $email = $record['creator_email'];
        $code = $record['verification_code'];
        $context = t('game') . ': ' . $record['game_name'];
        
    } else {
        echo json_encode(['success' => false, 'error' => 'No player_id, poll_id, or game_id provided']);
        exit;
    }
    
    // Check if code exists
    if (!$code) {
        echo json_encode([
            'success' => false,
            'error' => 'No verification code found. This record may be from an older version.'
        ]);
        exit;
    }
    
    // Check if email exists
    if (!$email) {
        // No email on record - return success with flag to allow trust-based resignation
        echo json_encode([
            'success' => true,
            'no_email' => true,
            'message' => 'No email on record. Resignation will be allowed with confirmation only.'
        ]);
        exit;
    }
    
    $email_sent = false;
    
    // Try to send email if configured
    if (!empty($config['send_emails'])) {
        require_once '../includes/email.php';
        
        try {
            $subject = t('email_verification_code_subject');
            $message = t('email_verification_code_body') . ": $code\n\n";
            $message .= t('email_verification_code_context') . " $context.\n\n";
            $message .= t('email_verification_code_ignore');
            
            if (send_email($email, $subject, $message, $config)) {
                $email_sent = true;
            } else {
                // Email sending failed
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to send verification code email. Please contact the administrator.',
                    'email_error' => true
                ]);
                exit;
            }
        } catch (Exception $e) {
            error_log("Failed to send verification code email: " . $e->getMessage());
            // Email failed - return error
            echo json_encode([
                'success' => false,
                'error' => 'Failed to send verification code email. Please contact the administrator.',
                'email_error' => true
            ]);
            exit;
        }
    } else {
        // Email not configured
        echo json_encode([
            'success' => false,
            'error' => 'Email sending is not enabled. Please contact the administrator to enable email verification.',
            'config_error' => true
        ]);
        exit;
    }
    
    // Email sent successfully
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'email_sent' => true,
        'email' => $email ? substr($email, 0, 3) . '***' . substr($email, -10) : null
        // Never send the code to the frontend!
    ]);
    
} catch (PDOException $e) {
    error_log('Send verification code database error: ' . $e->getMessage());
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
} catch (Exception $e) {
    error_log('Send verification code error: ' . $e->getMessage());
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error occurred: ' . $e->getMessage()
    ]);
}
