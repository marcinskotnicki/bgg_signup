<?php
/**
 * AJAX Handler: Verify Email
 * 
 * Verifies that the provided email matches the creator's email
 * for games or players
 */

header('Content-Type: application/json');

// Load configuration
$config = require_once '../config.php';

// Database connection
try {
    $db = new PDO('sqlite:../' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get parameters
$action = $_POST['action'] ?? '';
$email = trim($_POST['email'] ?? '');
$game_id = intval($_POST['game_id'] ?? 0);
$player_id = intval($_POST['player_id'] ?? 0);

// Validate email
if (empty($email)) {
    echo json_encode(['success' => false, 'verified' => false, 'message' => 'Email is required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'verified' => false, 'message' => 'Invalid email format']);
    exit;
}

// Verify based on action
$verified = false;

switch ($action) {
    case 'edit_game':
    case 'delete_game':
        if ($game_id) {
            $stmt = $db->prepare("SELECT creator_email FROM games WHERE id = ?");
            $stmt->execute([$game_id]);
            $game = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($game && strcasecmp($game['creator_email'], $email) === 0) {
                $verified = true;
            }
        }
        break;
        
    case 'resign_player':
        if ($player_id) {
            $stmt = $db->prepare("SELECT player_email FROM players WHERE id = ?");
            $stmt->execute([$player_id]);
            $player = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($player && strcasecmp($player['player_email'], $email) === 0) {
                $verified = true;
            }
        }
        break;
        
    case 'edit_poll':
    case 'delete_poll':
        $poll_id = intval($_POST['poll_id'] ?? 0);
        if ($poll_id) {
            $stmt = $db->prepare("SELECT creator_email FROM polls WHERE id = ?");
            $stmt->execute([$poll_id]);
            $poll = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($poll && strcasecmp($poll['creator_email'], $email) === 0) {
                $verified = true;
            }
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'verified' => false, 'message' => 'Invalid action']);
        exit;
}

if ($verified) {
    echo json_encode([
        'success' => true,
        'verified' => true,
        'message' => 'Email verified successfully'
    ]);
} else {
    echo json_encode([
        'success' => true,
        'verified' => false,
        'message' => 'Email does not match. You can only modify items you created.'
    ]);
}
