<?php
/**
 * AJAX Handler: User Management
 */

header('Content-Type: application/json');

// Load configuration
$config = require_once '../config.php';

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

// Check if user is admin
$current_user = get_current_user($db);
if (!$current_user || $current_user['is_admin'] != 1) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get action
$action = isset($_POST['action']) ? $_POST['action'] : '';
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit;
}

// Prevent admin from modifying themselves in some operations
if ($action === 'delete_user' && $user_id === $current_user['id']) {
    echo json_encode(['success' => false, 'error' => 'Cannot delete your own account']);
    exit;
}

try {
    switch ($action) {
        case 'toggle_role':
            $is_admin = isset($_POST['is_admin']) ? intval($_POST['is_admin']) : 0;
            
            // Prevent removing last admin
            if ($is_admin == 0) {
                $stmt = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 1");
                $admin_count = $stmt->fetchColumn();
                
                if ($admin_count <= 1) {
                    echo json_encode(['success' => false, 'error' => 'Cannot remove the last admin']);
                    exit;
                }
            }
            
            $stmt = $db->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
            $stmt->execute([$is_admin, $user_id]);
            
            // Log activity
            $stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $role_text = $is_admin ? 'admin' : 'user';
            log_activity($db, $current_user['id'], 'user_role_changed', 
                "Changed {$user['name']}'s role to {$role_text}");
            
            echo json_encode(['success' => true]);
            break;
            
        case 'reset_password':
            $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
            
            if (strlen($new_password) < 6) {
                echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
                exit;
            }
            
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$password_hash, $user_id]);
            
            // Log activity
            $stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            log_activity($db, $current_user['id'], 'password_reset', 
                "Reset password for user: {$user['name']}");
            
            echo json_encode(['success' => true]);
            break;
            
        case 'delete_user':
            // Get user info for logging
            $stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                echo json_encode(['success' => false, 'error' => 'User not found']);
                exit;
            }
            
            // Check if user is admin
            $stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($target_user['is_admin']) {
                // Prevent deleting last admin
                $stmt = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 1");
                $admin_count = $stmt->fetchColumn();
                
                if ($admin_count <= 1) {
                    echo json_encode(['success' => false, 'error' => 'Cannot delete the last admin']);
                    exit;
                }
            }
            
            // Delete user
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // Log activity
            log_activity($db, $current_user['id'], 'user_deleted', 
                "Deleted user: {$user['name']}");
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    error_log("user_management error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
} catch (Exception $e) {
    error_log("user_management unexpected error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred']);
}
