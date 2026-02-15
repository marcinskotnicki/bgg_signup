<?php
/**
 * AJAX Handler: Submit Vote
 */

header('Content-Type: application/json');

// Load configuration
$config = require_once '../config.php';

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

// Get current user
$current_user = get_current_user($db);

// Get form data
$option_id = isset($_POST['option_id']) ? intval($_POST['option_id']) : 0;
$poll_id = isset($_POST['poll_id']) ? intval($_POST['poll_id']) : 0;
$voter_name = isset($_POST['voter_name']) ? trim($_POST['voter_name']) : '';
$voter_email = isset($_POST['voter_email']) ? trim($_POST['voter_email']) : '';

// Validate required fields
if (!$option_id || !$poll_id || !$voter_name || !$voter_email) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Validate email
if (!filter_var($voter_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

try {
    // Get poll details
    $stmt = $db->prepare("SELECT * FROM polls WHERE id = ?");
    $stmt->execute([$poll_id]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$poll) {
        echo json_encode(['success' => false, 'error' => 'Poll not found']);
        exit;
    }
    
    // Check if poll is active
    if ($poll['is_active'] == 0) {
        echo json_encode(['success' => false, 'error' => 'Poll is closed']);
        exit;
    }
    
    // Check if multiple votes are allowed (config setting)
    if (!$config['allow_multiple_poll_votes']) {
        // Check if this email has already voted in this poll
        $stmt = $db->prepare("SELECT COUNT(*) FROM poll_votes pv 
                              JOIN poll_options po ON pv.poll_option_id = po.id 
                              WHERE po.poll_id = ? AND pv.voter_email = ?");
        $stmt->execute([$poll_id, $voter_email]);
        $existing_votes = $stmt->fetchColumn();
        
        if ($existing_votes > 0) {
            echo json_encode(['success' => false, 'error' => 'You have already voted in this poll']);
            exit;
        }
    }
    
    // Always check if this email has already voted for THIS SPECIFIC OPTION
    $stmt = $db->prepare("SELECT COUNT(*) FROM poll_votes WHERE poll_option_id = ? AND voter_email = ?");
    $stmt->execute([$option_id, $voter_email]);
    $voted_for_this_option = $stmt->fetchColumn();
    
    if ($voted_for_this_option > 0) {
        echo json_encode(['success' => false, 'error' => 'You have already voted for this option']);
        exit;
    }
    
    // Get poll option details
    $stmt = $db->prepare("SELECT * FROM poll_options WHERE id = ?");
    $stmt->execute([$option_id]);
    $option = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$option) {
        echo json_encode(['success' => false, 'error' => 'Poll option not found']);
        exit;
    }
    
    $db->beginTransaction();
    
    // Insert vote
    $stmt = $db->prepare("INSERT INTO poll_votes (
        poll_option_id, voter_name, voter_email, user_id, created_at
    ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
    
    $stmt->execute([
        $option_id,
        $voter_name,
        $voter_email,
        $current_user ? $current_user['id'] : null
    ]);
    
    $vote_id = $db->lastInsertId();
    
    // Get current vote count for this option
    $stmt = $db->prepare("SELECT COUNT(*) FROM poll_votes WHERE poll_option_id = ?");
    $stmt->execute([$option_id]);
    $vote_count = $stmt->fetchColumn();
    
    // Check if threshold reached
    $threshold_reached = ($vote_count >= $option['vote_threshold']);
    
    if ($threshold_reached) {
        // Check if any other options also reached threshold at the same time
        $stmt = $db->prepare("
            SELECT po.*, COUNT(pv.id) as vote_count
            FROM poll_options po
            LEFT JOIN poll_votes pv ON po.id = pv.poll_option_id
            WHERE po.poll_id = ?
            GROUP BY po.id
            HAVING vote_count >= po.vote_threshold
            ORDER BY po.vote_threshold DESC, po.display_order ASC
        ");
        $stmt->execute([$poll_id]);
        $winning_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // The first one in the list is the winner (highest threshold, then earliest)
        $winner = $winning_options[0];
        
        if ($winner['id'] == $option_id) {
            // This option is the winner! Create the game
            
            // Get table_id from poll
            $table_id = $poll['table_id'];
            
            // Insert game
            $stmt = $db->prepare("INSERT INTO games (
                table_id, bgg_id, bgg_url, name, thumbnail, play_time, 
                min_players, max_players, difficulty, start_time, 
                host_name, host_email, language, rules_explanation, 
                initial_comment, is_active, created_by_user_id, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, CURRENT_TIMESTAMP)");
            
            $stmt->execute([
                $table_id,
                $winner['bgg_id'],
                $winner['bgg_url'],
                $winner['game_name'],
                $winner['thumbnail'],
                $winner['play_time'],
                $winner['min_players'],
                $winner['max_players'],
                $winner['difficulty'],
                '12:00', // Default start time - could be improved
                $poll['creator_name'],
                $poll['creator_email'],
                'en', // Default language - could be improved
                'Will be explained',
                'Game selected by poll!',
                $poll['created_by_user_id']
            ]);
            
            $new_game_id = $db->lastInsertId();
            
            // Close the poll
            $stmt = $db->prepare("UPDATE polls SET is_active = 0, closed_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$poll_id]);
            
            // Get all voters to notify them
            $stmt = $db->prepare("
                SELECT DISTINCT pv.voter_name, pv.voter_email
                FROM poll_votes pv
                JOIN poll_options po ON pv.poll_option_id = po.id
                WHERE po.poll_id = ?
            ");
            $stmt->execute([$poll_id]);
            $voters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Send notifications to all voters
            foreach ($voters as $voter) {
                try {
                    email_poll_closed($db, $poll_id, $winner['game_name'], $voter['voter_email']);
                } catch (Exception $e) {
                    error_log("Email sending failed for poll voter: " . $e->getMessage());
                }
            }
        }
    }
    
    $db->commit();
    
    // Log activity
    log_activity($db, $current_user ? $current_user['id'] : null, 'vote_submitted', 
        "Vote submitted for: {$option['game_name']} in poll ID: $poll_id by $voter_name");
    
    echo json_encode([
        'success' => true,
        'vote_id' => $vote_id,
        'threshold_reached' => $threshold_reached
    ]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("vote_submit error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("vote_submit unexpected error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred']);
}
