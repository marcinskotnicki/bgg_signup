<?php
/**
 * AJAX Handler: Delete Poll
 * Soft deletes poll and notifies all voters
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

// Get poll ID
$poll_id = isset($_POST['poll_id']) ? intval($_POST['poll_id']) : 0;

if (!$poll_id) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Poll ID required']);
    exit;
}

try {
    // Get poll details
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
    $can_delete = false;
    if ($current_user) {
        if ($current_user['is_admin']) {
            $can_delete = true;
        } elseif ($poll['created_by_user_id'] && $poll['created_by_user_id'] == $current_user['id']) {
            $can_delete = true;
        }
    }
    
    if (!$can_delete) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    
    // Get all voters before deleting
    $voters = [];
    if ($config['send_emails']) {
        $stmt = $db->prepare("
            SELECT DISTINCT pv.voter_email, pv.voter_name
            FROM poll_votes pv
            JOIN poll_options po ON pv.poll_option_id = po.id
            WHERE po.poll_id = ? AND pv.voter_email != ''
        ");
        $stmt->execute([$poll_id]);
        $voters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $db->beginTransaction();
    
    // Soft delete poll (set is_active = 0)
    $stmt = $db->prepare("UPDATE polls SET is_active = 0, closed_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$poll_id]);
    
    $db->commit();
    
    // Send email notifications
    if (!empty($voters) && $config['send_emails']) {
        $poll_name = $poll['event_name'] . ' - ' . t('table') . ' ' . $poll['table_number'];
        
        $subject = t('email_subject_poll_deleted', ['poll' => $poll_name]);
        $message = t('email_body_poll_deleted');
        
        // Send to each voter
        foreach ($voters as $voter) {
            send_email($voter['voter_email'], $subject, $message, $config);
        }
        
        // Log deletion
        $log_dir = '../' . LOGS_DIR;
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        $log_file = $log_dir . '/poll_deletions_' . date('Y-m-d') . '.log';
        $log_entry = date('Y-m-d H:i:s') . " - Poll #$poll_id ($poll_name) deleted by " . 
                     ($current_user ? $current_user['name'] : 'guest') . 
                     " - Notified " . count($voters) . " voters\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => t('poll_deleted_success')
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
