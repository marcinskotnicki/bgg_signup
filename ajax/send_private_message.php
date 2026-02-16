<?php
/**
 * AJAX Handler: Send Private Message
 */

header('Content-Type: application/json');

// Load configuration
$config = require_once '../config.php';

// Load translations
require_once '../includes/translations.php';

// Load auth helper
require_once '../includes/auth.php';

// Load email helper
require_once '../includes/email.php';

// Database connection
try {
    $db = new PDO('sqlite:../' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Check if private messages are enabled
if (!$config['allow_private_messages']) {
    echo json_encode(['success' => false, 'error' => 'Private messages are disabled']);
    exit;
}

// Get current user
$current_user = get_current_user($db);

// Get form data
$player_id = isset($_POST['player_id']) ? intval($_POST['player_id']) : 0;
$game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;
$sender_name = isset($_POST['sender_name']) ? trim($_POST['sender_name']) : '';
$sender_email = isset($_POST['sender_email']) ? trim($_POST['sender_email']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$context = isset($_POST['context']) ? trim($_POST['context']) : '';

// Validate required fields
if (!$sender_name || !$sender_email || !$message || (!$player_id && !$game_id)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Validate email
if (!filter_var($sender_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

try {
    $recipients = [];
    
    if ($player_id) {
        // Send to specific player
        $stmt = $db->prepare("SELECT player_name, player_email FROM players WHERE id = ?");
        $stmt->execute([$player_id]);
        $player = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$player || !$player['player_email']) {
            echo json_encode(['success' => false, 'error' => 'Player email not found']);
            exit;
        }
        
        $recipients[] = [
            'name' => $player['player_name'],
            'email' => $player['player_email']
        ];
        
    } elseif ($game_id) {
        // Send to all players in game
        $stmt = $db->prepare("SELECT player_name, player_email FROM players WHERE game_id = ? AND player_email IS NOT NULL AND player_email != ''");
        $stmt->execute([$game_id]);
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($players)) {
            echo json_encode(['success' => false, 'error' => 'No players with email addresses found']);
            exit;
        }
        
        foreach ($players as $player) {
            $recipients[] = [
                'name' => $player['player_name'],
                'email' => $player['player_email']
            ];
        }
    }
    
    // Prepare email
    $subject = "Message from {$sender_name} ({$context})";
    
    $email_body = email_template([
        'title' => $subject,
        'content' => nl2br(htmlspecialchars($message)),
        'footer' => '<p><strong>From:</strong> ' . htmlspecialchars($sender_name) . ' (' . htmlspecialchars($sender_email) . ')</p>
                     <p><strong>Regarding:</strong> ' . htmlspecialchars($context) . '</p>'
    ]);
    
    // Send emails to all recipients
    $sent_count = 0;
    $failed = [];
    
    foreach ($recipients as $recipient) {
        $result = send_email(
            $recipient['email'],
            $subject,
            $email_body,
            $config,
            $sender_email  // Reply-to sender's email
        );
        
        if ($result) {
            $sent_count++;
        } else {
            $failed[] = $recipient['name'];
        }
    }
    
    // Log activity
    if ($player_id) {
        log_activity($db, $current_user ? $current_user['id'] : null, 'private_message_sent',
            "Private message sent from {$sender_name} to player ID {$player_id}");
    } else {
        log_activity($db, $current_user ? $current_user['id'] : null, 'private_message_sent',
            "Private message sent from {$sender_name} to {$sent_count} players in game ID {$game_id}");
    }
    
    if ($sent_count > 0) {
        $response = ['success' => true, 'sent_count' => $sent_count];
        if (!empty($failed)) {
            $response['warning'] = 'Some messages failed to send: ' . implode(', ', $failed);
        }
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send any messages']);
    }
    
} catch (PDOException $e) {
    error_log("send_private_message error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
} catch (Exception $e) {
    error_log("send_private_message unexpected error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred']);
}
