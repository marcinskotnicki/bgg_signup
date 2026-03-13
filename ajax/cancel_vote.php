<?php
/**
 * AJAX Handler: Cancel Vote
 * Similar to resign_player.php but for poll votes
 */

// Start output buffering
ob_start();

header('Content-Type: application/json');

// Load configuration
$config = require_once '../config.php';

// Load translations
require_once '../includes/translations.php';

// Load auth helper
require_once '../includes/auth.php';

// Database connection
try {
    $db = new PDO('sqlite:../' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Get current user
$current_user = get_current_user($db);

// Get vote ID
$vote_id = isset($_POST['vote_id']) ? intval($_POST['vote_id']) : 0;

if (!$vote_id) {
    echo json_encode(['success' => false, 'error' => t('invalid_parameters')]);
    exit;
}

try {
    // Get vote details
    $stmt = $db->prepare("
        SELECT pv.*, po.game_name, po.poll_id, p.is_active
        FROM poll_votes pv
        JOIN poll_options po ON pv.poll_option_id = po.id
        JOIN polls p ON po.poll_id = p.id
        WHERE pv.id = ?
    ");
    $stmt->execute([$vote_id]);
    $vote = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vote) {
        echo json_encode(['success' => false, 'error' => t('vote_not_found')]);
        exit;
    }
    
    // Check if poll is still active
    if ($vote['is_active'] == 0) {
        echo json_encode(['success' => false, 'error' => t('poll_already_closed')]);
        exit;
    }
    
    // Check permissions
    $can_cancel = false;
    
    if ($current_user) {
        // Admin or voter's own vote (if they were logged in when voting)
        if ($current_user['is_admin'] == 1 || ($vote['user_id'] && $vote['user_id'] == $current_user['id'])) {
            $can_cancel = true;
        }
    } else {
        // Not logged in - need to verify
        
        // CASE 1: Voter HAS an email address
        if ($vote['voter_email']) {
            // EMAIL verification method: check if verified_email was provided
            if (isset($_POST['verified_email'])) {
                $verified_email = trim($_POST['verified_email']);
                if (strcasecmp($vote['voter_email'], $verified_email) === 0) {
                    $can_cancel = true;
                }
            }
            // CODE verification method: check session for recent verification
            elseif (!$vote['user_id']) {
                // Start session if not already started
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                // Check if this vote was verified via code in the last 5 minutes
                $session_key = 'verified_vote_' . $vote_id;
                if (isset($_SESSION[$session_key])) {
                    $verified_time = $_SESSION[$session_key];
                    if ((time() - $verified_time) < 300) { // 5 minutes
                        $can_cancel = true;
                        // Clear the session variable after use (one-time token)
                        unset($_SESSION[$session_key]);
                    }
                }
            }
        } 
        // CASE 2: Voter has NO email AND no user_id - allow without verification
        elseif (!$vote['user_id']) {
            $can_cancel = true;
        }
    }
    
    if (!$can_cancel) {
        echo json_encode(['success' => false, 'error' => t('permission_denied_verify_email')]);
        exit;
    }
    
    $db->beginTransaction();
    
    $voter_name = $vote['voter_name'];
    $game_name = $vote['game_name'];
    $poll_id = $vote['poll_id'];
    
    // Delete vote
    $stmt = $db->prepare("DELETE FROM poll_votes WHERE id = ?");
    $stmt->execute([$vote_id]);
    
    $db->commit();
    
    // Log activity
    log_activity($db, $current_user ? $current_user['id'] : null, 'vote_cancelled', 
        "Vote cancelled: $voter_name cancelled vote for $game_name in poll ID: $poll_id");
    
    // Clean output buffer
    ob_end_clean();
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
