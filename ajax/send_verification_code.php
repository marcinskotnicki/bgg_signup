<?php
/**
 * AJAX Handler: Send Verification Code
 * Sends the verification code to user's email when they try to resign
 */

header('Content-Type: application/json');

// Load configuration
require_once '../config.php';

// Database connection
require_once '../includes/db.php';

// Get parameters
$player_id = intval($_POST['player_id'] ?? 0);
$poll_id = intval($_POST['poll_id'] ?? 0);

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
        $context = 'game: ' . $record['game_name'];
        
    } elseif ($poll_id) {
        // Get poll details
        $stmt = $db->prepare("
            SELECT p.creator_email, p.verification_code, p.created_by_user_id
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
        $context = 'poll';
        
    } else {
        echo json_encode(['success' => false, 'error' => 'No player_id or poll_id provided']);
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
    
    $email_sent = false;
    
    // Try to send email if configured and email exists
    if ($email && defined('SEND_EMAILS') && SEND_EMAILS === 'yes') {
        require_once '../includes/email_helper.php';
        
        try {
            $subject = 'Your Verification Code';
            $message = "Your verification code is: $code\n\n";
            $message .= "You requested this code to resign from $context.\n\n";
            $message .= "If you did not request this code, please ignore this email.";
            
            if (send_email($email, $subject, $message)) {
                $email_sent = true;
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
        // Email not configured or no email address
        echo json_encode([
            'success' => false,
            'error' => 'Email verification is not configured. Please contact the administrator.',
            'config_error' => true
        ]);
        exit;
    }
    
    // Email sent successfully
    echo json_encode([
        'success' => true,
        'email_sent' => $email_sent,
        'email' => $email ? substr($email, 0, 3) . '***' . substr($email, -10) : null
        // Never send the code to the frontend!
    ]);
    
} catch (PDOException $e) {
    error_log('Send verification code error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
