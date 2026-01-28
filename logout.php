<?php
/**
 * Logout Handler
 * 
 * Simple script to clear authentication cookies
 */

// Clear authentication cookies
setcookie('bgg_user_id', '', time() - 3600, '/');
setcookie('bgg_auth_token', '', time() - 3600, '/');

// Return success (for AJAX calls)
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>