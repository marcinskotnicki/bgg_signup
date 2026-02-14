<?php
/**
 * AJAX Handler: Edit Poll Submit
 * Updates poll, tracks changes, sends email notifications
 */

header('Content-Type: application/json');

// Prevent any output before JSON
ob_start();

// Load configuration
$config = require_once '../config.php';

// Load auth helper
require_once '../includes/auth.php';

// Load translation system
require_once '../includes/translations.php';

// Load email helper
require_once '../includes/email.php';

// Database connection
try {
    $db = new PDO('sqlite:../' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Get current user
$current_user = get_current_user($db);

// Get poll data
$poll_data_json = isset($_POST['poll_data']) ? $_POST['poll_data'] : '';
$poll_data = json_decode($poll_data_json, true);

if (!$poll_data || !isset($poll_data['poll_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid poll data']);
    exit;
}

$poll_id = intval($poll_data['poll_id']);
$new_start_time = isset($poll_data['start_time']) ? trim($poll_data['start_time']) : '';
$new_options = isset($poll_data['options']) ? $poll_data['options'] : [];

// Validate
if (empty($new_start_time)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Start time is required']);
    exit;
}

try {
    // Get existing poll
    $stmt = $db->prepare("SELECT p.*, t.table_number, ed.day_number, e.name as event_name
        FROM polls p
        JOIN tables t ON p.table_id = t.id
        JOIN event_days ed ON t.event_day_id = ed.id
        JOIN events e ON ed.event_id = e.id
        WHERE p.id = ?");
    $stmt->execute([$poll_id]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$poll) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Poll not found']);
        exit;
    }
    
    // Check permission
    $can_edit = false;
    if ($current_user) {
        if ($current_user['is_admin']) {
            $can_edit = true;
        } elseif ($poll['created_by_user_id'] && $poll['created_by_user_id'] == $current_user['id']) {
            $can_edit = true;
        }
    }
    
    if (!$can_edit) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    
    // Get existing options
    $stmt = $db->prepare("SELECT * FROM poll_options WHERE poll_id = ? ORDER BY display_order");
    $stmt->execute([$poll_id]);
    $existing_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Track changes for email
    $changes = [];
    
    // Check if start time changed
    if ($poll['start_time'] !== $new_start_time) {
        $changes[] = t('poll_start_time') . ': ' . $poll['start_time'] . ' → ' . $new_start_time;
    }
    
    $db->beginTransaction();
    
    // Update poll start time
    $stmt = $db->prepare("UPDATE polls SET start_time = ? WHERE id = ?");
    $stmt->execute([$new_start_time, $poll_id]);
    
    // Track option changes
    $existing_option_ids = array_column($existing_options, 'id');
    $new_option_ids = array_filter(array_column($new_options, 'id'));
    
    // Find removed options
    $removed_option_ids = array_diff($existing_option_ids, $new_option_ids);
    
    foreach ($removed_option_ids as $option_id) {
        // Get option details
        $option = array_filter($existing_options, function($o) use ($option_id) {
            return $o['id'] == $option_id;
        });
        $option = reset($option);
        
        if ($option) {
            // Check if option has votes
            $stmt = $db->prepare("SELECT COUNT(*) FROM poll_votes WHERE poll_option_id = ?");
            $stmt->execute([$option_id]);
            $vote_count = $stmt->fetchColumn();
            
            if ($vote_count > 0) {
                $db->rollBack();
                ob_end_clean();
                echo json_encode([
                    'success' => false, 
                    'error' => t('cannot_remove_voted_option') . ': ' . $option['game_name']
                ]);
                exit;
            }
            
            // Delete option
            $stmt = $db->prepare("DELETE FROM poll_options WHERE id = ?");
            $stmt->execute([$option_id]);
            
            $changes[] = t('removed') . ': ' . $option['game_name'];
        }
    }
    
    // Add new options
    foreach ($new_options as $option) {
        if (!isset($option['id']) || empty($option['id'])) {
            // New option
            $stmt = $db->prepare("INSERT INTO poll_options (
                poll_id, bgg_id, bgg_url, game_name, thumbnail, 
                play_time, min_players, max_players, difficulty,
                vote_threshold, display_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $poll_id,
                $option['bgg_id'] ?? null,
                $option['bgg_url'] ?? null,
                $option['game_name'],
                $option['thumbnail'] ?? null,
                $option['play_time'] ?? null,
                $option['min_players'] ?? null,
                $option['max_players'] ?? null,
                $option['difficulty'] ?? null,
                $option['vote_threshold'],
                $option['display_order']
            ]);
            
            $changes[] = t('added') . ': ' . $option['game_name'];
        }
    }
    
    $db->commit();
    
    // Send email notifications if there were changes
    if (!empty($changes) && $config['send_emails']) {
        // Get all voters
        $stmt = $db->prepare("
            SELECT DISTINCT pv.voter_email, pv.voter_name
            FROM poll_votes pv
            JOIN poll_options po ON pv.poll_option_id = po.id
            WHERE po.poll_id = ? AND pv.voter_email != ''
        ");
        $stmt->execute([$poll_id]);
        $voters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Prepare email
        $subject = t('email_subject_poll_updated', [
            'poll' => $poll['event_name'] . ' - ' . t('table') . ' ' . $poll['table_number']
        ]);
        
        $changes_html = '<ul>';
        foreach ($changes as $change) {
            $changes_html .= '<li>' . htmlspecialchars($change) . '</li>';
        }
        $changes_html .= '</ul>';
        
        $message = t('email_body_poll_updated', ['changes' => $changes_html]);
        
        // Send to each voter
        foreach ($voters as $voter) {
            send_email($voter['voter_email'], $subject, $message, $config);
        }
        
        // Log email sending
        $log_dir = '../' . LOGS_DIR;
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        $log_file = $log_dir . '/poll_updates_' . date('Y-m-d') . '.log';
        $log_entry = date('Y-m-d H:i:s') . " - Poll #$poll_id updated by " . 
                     ($current_user ? $current_user['name'] : 'guest') . 
                     " - Notified " . count($voters) . " voters\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => t('poll_updated_success'),
        'changes' => $changes
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
