<?php
/**
 * Authentication Helper Functions
 * 
 * Handles user authentication, registration, and session management
 */

/**
 * Get current logged in user
 * 
 * @param PDO $db Database connection
 * @return array|null User data or null if not logged in
 */
function get_current_user($db) {
    if (!isset($_COOKIE['bgg_user_id']) || !isset($_COOKIE['bgg_auth_token'])) {
        return null;
    }
    
    $user_id = $_COOKIE['bgg_user_id'];
    $auth_token = $_COOKIE['bgg_auth_token'];
    
    // Verify auth token
    $stmt = $db->prepare("SELECT id, name, email, password_hash, is_admin FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return null;
    }
    
    // Verify token matches
    $expected_token = hash('sha256', $user_id . $user['password_hash'] . AUTH_SALT);
    if ($auth_token !== $expected_token) {
        return null;
    }
    
    return $user;
}

/**
 * Check if user is logged in
 * 
 * @param PDO $db Database connection
 * @return bool
 */
function is_logged_in($db) {
    return get_current_user($db) !== null;
}

/**
 * Check if current user is admin
 * 
 * @param PDO $db Database connection
 * @return bool
 */
function is_admin($db) {
    $user = get_current_user($db);
    return $user && $user['is_admin'] == 1;
}

/**
 * Login user
 * 
 * @param PDO $db Database connection
 * @param string $email User email
 * @param string $password Password
 * @return array Result array with 'success' and 'error' keys
 */
function login_user($db, $email, $password) {
    $stmt = $db->prepare("SELECT id, name, email, password_hash, is_admin FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return ['success' => false, 'error' => 'invalid_credentials'];
    }
    
    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'invalid_credentials'];
    }
    
    // Create auth token and set cookies
    $auth_token = hash('sha256', $user['id'] . $user['password_hash'] . AUTH_SALT);
    setcookie('bgg_user_id', $user['id'], time() + COOKIE_LIFETIME, '/');
    setcookie('bgg_auth_token', $auth_token, time() + COOKIE_LIFETIME, '/');
    
    return ['success' => true, 'user' => $user];
}

/**
 * Register new user
 * 
 * @param PDO $db Database connection
 * @param string $name Display name
 * @param string $email Email address
 * @param string $password Password
 * @return array Result array with 'success' and 'error' keys
 */
function register_user($db, $name, $email, $password) {
    // Validate inputs
    if (empty($name) || empty($email) || empty($password)) {
        return ['success' => false, 'error' => 'all_fields_required'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'invalid_email'];
    }
    
    if (strlen($password) < 6) {
        return ['success' => false, 'error' => 'password_too_short'];
    }
    
    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'email_already_exists'];
    }
    
    // Create user
    try {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, is_admin) VALUES (?, ?, ?, 0)");
        $stmt->execute([$name, $email, $password_hash]);
        $user_id = $db->lastInsertId();
        
        // Log user in automatically
        $auth_token = hash('sha256', $user_id . $password_hash . AUTH_SALT);
        setcookie('bgg_user_id', $user_id, time() + COOKIE_LIFETIME, '/');
        setcookie('bgg_auth_token', $auth_token, time() + COOKIE_LIFETIME, '/');
        
        // Log the action
        log_activity($db, null, 'user_registered', "User registered: $name ($email)");
        
        return ['success' => true, 'user_id' => $user_id];
        
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'registration_failed'];
    }
}

/**
 * Logout current user
 */
function logout_user() {
    setcookie('bgg_user_id', '', time() - 3600, '/');
    setcookie('bgg_auth_token', '', time() - 3600, '/');
}

/**
 * Update user profile
 * 
 * @param PDO $db Database connection
 * @param int $user_id User ID
 * @param string $name New display name
 * @param string $email New email
 * @param string $current_password Current password (for verification)
 * @param string $new_password New password (optional)
 * @return array Result array with 'success' and 'error' keys
 */
function update_user_profile($db, $user_id, $name, $email, $current_password, $new_password = '') {
    // Get current user data
    $stmt = $db->prepare("SELECT email, password_hash FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return ['success' => false, 'error' => 'user_not_found'];
    }
    
    // Verify current password
    if (!password_verify($current_password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'incorrect_current_password'];
    }
    
    // Validate new data
    if (empty($name) || empty($email)) {
        return ['success' => false, 'error' => 'all_fields_required'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'invalid_email'];
    }
    
    // Check if email is taken by another user
    if ($email !== $user['email']) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'email_already_exists'];
        }
    }
    
    // Validate new password if provided
    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            return ['success' => false, 'error' => 'password_too_short'];
        }
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    } else {
        $password_hash = $user['password_hash'];
    }
    
    // Update user
    try {
        $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, password_hash = ? WHERE id = ?");
        $stmt->execute([$name, $email, $password_hash, $user_id]);
        
        // Update auth cookie if password changed
        if (!empty($new_password)) {
            $auth_token = hash('sha256', $user_id . $password_hash . AUTH_SALT);
            setcookie('bgg_auth_token', $auth_token, time() + COOKIE_LIFETIME, '/');
        }
        
        // Log the action
        log_activity($db, $user_id, 'profile_updated', "User updated profile: $name");
        
        return ['success' => true];
        
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'update_failed'];
    }
}

/**
 * Log user activity
 * 
 * @param PDO $db Database connection
 * @param int|null $user_id User ID (null for anonymous)
 * @param string $action Action performed
 * @param string $details Additional details
 */
function log_activity($db, $user_id, $action, $details) {
    $log_dir = LOGS_DIR;
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/' . date('Y-m-d') . '.log';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $user_info = $user_id ? "User ID: $user_id" : "Anonymous";
    $log_entry = date('Y-m-d H:i:s') . " - $user_info (IP: $ip) - $action - $details\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Require login - redirect to login page if not logged in
 * 
 * @param PDO $db Database connection
 * @param string $redirect_to URL to redirect to after login
 */
function require_login($db, $redirect_to = '') {
    if (!is_logged_in($db)) {
        $redirect_param = $redirect_to ? '?redirect=' . urlencode($redirect_to) : '';
        header('Location: login.php' . $redirect_param);
        exit;
    }
}

/**
 * Require admin - redirect if not admin
 * 
 * @param PDO $db Database connection
 */
function require_admin($db) {
    if (!is_admin($db)) {
        header('Location: index.php');
        exit;
    }
}
// Note: Closing ?> tag omitted to prevent whitespace output issues