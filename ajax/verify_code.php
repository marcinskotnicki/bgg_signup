<?php
/**
 * AJAX Handler: Verify Code
 * 
 * Verifies that the provided 6-digit code matches the player/poll verification code
 * Used when verification_method is 'code' or 'link'
 * 
 * NOTE: Verification codes are stored with player/poll records (for non-logged-in users)
 * Logged-in users don't need codes - they're already verified by their login
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
    ob_end_clean();
    echo json_encode(['success' => false, 'verified' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get parameters
$action = $_POST['action'] ?? '';
$code = trim($_POST['code'] ?? '');
$game_id = intval($_POST['game_id'] ?? 0);
$player_id = intval($_POST['player_id'] ?? 0);
$poll_id = intval($_POST['poll_id'] ?? 0);
$vote_id = intval($_POST['vote_id'] ?? 0);

// Validate code (must be 6 digits)
if (empty($code)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'verified' => false, 'message' => t('enter_code')]);
    exit;
}

if (!preg_match('/^\d{6}$/', $code)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'verified' => false, 'message' => t('invalid_code')]);
    exit;
}

// Verify based on action
$verified = false;

try {
    switch ($action) {
        case 'edit_game':
        case 'delete_game':
            if ($game_id) {
                // Get game's verification code
                $stmt = $db->prepare("SELECT verification_code, created_by_user_id FROM games WHERE id = ?");
                $stmt->execute([$game_id]);
                $game = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$game) {
                    ob_end_clean();
                    echo json_encode([
                        'success' => true,
                        'verified' => false,
                        'message' => 'Game not found.'
                    ]);
                    exit;
                }
                
                // If game has created_by_user_id, creator was logged in
                if ($game['created_by_user_id']) {
                    ob_end_clean();
                    echo json_encode([
                        'success' => true,
                        'verified' => false,
                        'message' => 'Please log in to edit or delete this game.'
                    ]);
                    exit;
                }
                
                // Check if code matches
                if ($game['verification_code'] && $game['verification_code'] === $code) {
                    $verified = true;
                    
                    // Regenerate verification code after successful verification
                    $new_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $update = $db->prepare("UPDATE games SET verification_code = ? WHERE id = ?");
                    $update->execute([$new_code, $game_id]);
                }
            }
            break;
            
        case 'resign_player':
            if ($player_id) {
                // Get player's verification code
                $stmt = $db->prepare("SELECT verification_code, user_id FROM players WHERE id = ?");
                $stmt->execute([$player_id]);
                $player = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$player) {
                    ob_end_clean();
                    echo json_encode([
                        'success' => true,
                        'verified' => false,
                        'message' => 'Player not found.'
                    ]);
                    exit;
                }
                
                // If player has user_id, they're logged in and shouldn't use codes
                if ($player['user_id']) {
                    ob_end_clean();
                    echo json_encode([
                        'success' => true,
                        'verified' => false,
                        'message' => 'Logged-in players do not need verification codes. Please log in to resign.'
                    ]);
                    exit;
                }
                
                // Check if code matches
                if ($player['verification_code'] && $player['verification_code'] === $code) {
                    $verified = true;
                    
                    // Regenerate verification code after successful verification
                    $new_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $update = $db->prepare("UPDATE players SET verification_code = ? WHERE id = ?");
                    $update->execute([$new_code, $player_id]);
                }
            }
            break;
            
        case 'edit_poll':
        case 'delete_poll':
            if ($poll_id) {
                // Get poll's verification code
                $stmt = $db->prepare("SELECT verification_code, created_by_user_id FROM polls WHERE id = ?");
                $stmt->execute([$poll_id]);
                $poll = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$poll) {
                    ob_end_clean();
                    echo json_encode([
                        'success' => true,
                        'verified' => false,
                        'message' => 'Poll not found.'
                    ]);
                    exit;
                }
                
                // If poll has created_by_user_id, creator was logged in and shouldn't use codes
                if ($poll['created_by_user_id']) {
                    ob_end_clean();
                    echo json_encode([
                        'success' => true,
                        'verified' => false,
                        'message' => 'Please log in to edit or delete this poll.'
                    ]);
                    exit;
                }
                
                // Check if code matches
                if ($poll['verification_code'] && $poll['verification_code'] === $code) {
                    $verified = true;
                    
                    // Regenerate verification code after successful verification
                    $new_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $update = $db->prepare("UPDATE polls SET verification_code = ? WHERE id = ?");
                    $update->execute([$new_code, $poll_id]);
                }
            }
            break;
            
        case 'cancel_vote':
            if ($vote_id) {
                // Get vote's verification code
                $stmt = $db->prepare("SELECT verification_code, user_id FROM poll_votes WHERE id = ?");
                $stmt->execute([$vote_id]);
                $vote = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$vote) {
                    ob_end_clean();
                    echo json_encode([
                        'success' => true,
                        'verified' => false,
                        'message' => 'Vote not found.'
                    ]);
                    exit;
                }
                
                // If vote has user_id, they're logged in and shouldn't use codes
                if ($vote['user_id']) {
                    ob_end_clean();
                    echo json_encode([
                        'success' => true,
                        'verified' => false,
                        'message' => 'Logged-in users do not need verification codes. Please log in to cancel your vote.'
                    ]);
                    exit;
                }
                
                // Check if code matches
                if ($vote['verification_code'] && $vote['verification_code'] === $code) {
                    $verified = true;
                    
                    // Regenerate verification code after successful verification
                    $new_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $update = $db->prepare("UPDATE poll_votes SET verification_code = ? WHERE id = ?");
                    $update->execute([$new_code, $vote_id]);
                    
                    // Store verification in session (used by cancel_vote.php)
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $_SESSION['verified_vote_' . $vote_id] = time();
                }
            }
            break;
            
        default:
            ob_end_clean();
            echo json_encode(['success' => false, 'verified' => false, 'message' => 'Invalid action']);
            exit;
    }
    
    if ($verified) {
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'verified' => true,
            'message' => t('code_verified_successfully') ?: 'Code verified successfully'
        ]);
    } else {
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'verified' => false,
            'message' => t('code_does_not_match')
        ]);
    }
    
} catch (PDOException $e) {
    error_log('Verification code database error: ' . $e->getMessage());
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'verified' => false,
        'message' => 'Database error occurred: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
} catch (Exception $e) {
    error_log('Verification code error: ' . $e->getMessage());
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'verified' => false,
        'message' => 'Error occurred: ' . $e->getMessage()
    ]);
}
?>
