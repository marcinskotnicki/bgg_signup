<?php
/**
 * AJAX: Delete Event
 */

header('Content-Type: application/json');

// Check admin access
session_start();
require_once '../includes/auth.php';
require_once '../config.php';

try {
    $db = new PDO('sqlite:../' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$current_user = get_current_user($db);
if (!$current_user || !$current_user['is_admin']) {
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

// Handle event deletion
if (!isset($_POST['event_id'])) {
    echo json_encode(['success' => false, 'error' => 'No event ID provided']);
    exit;
}

$event_id = intval($_POST['event_id']);

try {
    // Check if event is active
    $stmt = $db->prepare("SELECT is_active FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        echo json_encode(['success' => false, 'error' => 'Event not found']);
        exit;
    }
    
    if ($event['is_active']) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete the currently active event!']);
        exit;
    }
    
    // Delete event (cascade will handle related data)
    $stmt = $db->prepare("DELETE FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Event deleted successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error deleting event: ' . $e->getMessage()]);
}
?>
