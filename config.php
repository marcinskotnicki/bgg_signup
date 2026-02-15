<?php
/**
 * Configuration File for BGG Signup System
 * 
 * Edit this file directly to change system settings.
 * All settings are stored here instead of in the database for easier management.
 */

// Database Configuration
if (!defined('DB_FILE')) {
    define('DB_FILE', 'boardgame_events.db');
}

// Cache Version (increment to force browser cache refresh)
if (!defined('CACHE_VERSION')) {
    define('CACHE_VERSION', '1.0.0');
}

// GitHub Repository (for updates - uses ZIP download, no API, no rate limits!)
if (!defined('GITHUB_REPO')) {
    define('GITHUB_REPO', 'https://github.com/marcinskotnicki/bgg_signup');
}
if (!defined('GITHUB_ZIP')) {
    define('GITHUB_ZIP', 'https://github.com/marcinskotnicki/bgg_signup/archive/refs/heads/main.zip');
}
if (!defined('GITHUB_ZIP_ROOT')) {
    define('GITHUB_ZIP_ROOT', 'bgg_signup-main/'); // Root folder name inside the ZIP
}

// System Paths
if (!defined('BACKUP_DIR')) {
    define('BACKUP_DIR', 'backup');
}
if (!defined('LOGS_DIR')) {
    define('LOGS_DIR', 'logs');
}
if (!defined('THUMBNAILS_DIR')) {
    define('THUMBNAILS_DIR', 'thumbnails');
}
if (!defined('LANGUAGES_DIR')) {
    define('LANGUAGES_DIR', 'languages');
}
if (!defined('TEMPLATES_DIR')) {
    define('TEMPLATES_DIR', 'templates');
}

// Cookie Settings (authentication - 1 year)
if (!defined('COOKIE_LIFETIME')) {
    define('COOKIE_LIFETIME', 365 * 24 * 60 * 60); // 1 year in seconds
}
if (!defined('AUTH_SALT')) {
    define('AUTH_SALT', 'bgg_secret_salt_change_this'); // Change this to a random string!
}

// General Settings
$config = [
    // Venue Information
    'venue_name' => 'Game Venue',
    'default_event_name' => 'Board Game Event',
    
    // Default Times
    'default_start_time' => '10:00',
    'default_end_time' => '22:00',
    'timeline_extension' => 3, // Hours to extend timeline after end time
    
    // Default Tables
    'default_tables' => 5,
    
    // BoardGameGeek API
    'bgg_api_token' => '', // Get your token from BoardGameGeek
    'bgg_cache_duration' => 604800, // 1 week in seconds
    
    // Language
    'default_language' => 'en', // en, pl, or any language file in /languages/
    
    // SMTP Settings (for email notifications)
    'smtp_email' => '',
    'smtp_login' => '',
    'smtp_password' => '',
    'smtp_server' => '',
    'smtp_port' => 587,
    
    // User Interaction Settings
    'allow_reserve_list' => true,
    
    // User Authentication Options:
    // 'no' - no login system
    // 'yes' - optional login
    // 'required_games' - login required to add games
    // 'required_all' - login required for everything (adding games and signing up)
    'allow_logged_in' => 'no',
    
    'require_emails' => false, // Require email addresses for all signups
    
    // Verification Method for edits/deletions:
    // 'email' - user must provide same email they used
    // 'link' - send confirmation link to email
    'verification_method' => 'email',
    
    'send_emails' => false, // Send email notifications
    
    // Game Deletion Options:
    // 'allow_choice' - Users can choose between hard delete or soft delete
    // 'soft_only' - Only soft delete allowed
    // 'hard_only' - Only hard delete allowed (immediate permanent deletion)
    'deletion_mode' => 'soft_only',
    
    // Comment Settings
    'restrict_comments' => false, // Require login to post comments
    'use_captcha' => false, // Use CAPTCHA for comments
    
    // Private Messages
    'allow_private_messages' => false, // Allow users to send private messages to players/hosts
    
    // Poll Settings
    'closed_poll_action' => 'grey', // What to do with closed polls: 'grey' (grey out) or 'delete' (remove)
    
    // Template Settings
    'active_template' => 'default', // Active template folder name
    
    // Custom Messages (can contain HTML)
    'homepage_message' => '', // Displayed under event title
    'add_game_message' => '', // Displayed above game form
    'add_player_message' => '', // Displayed above player signup form
];

// Admin User (created during installation)
// Note: This is just the initial admin. After installation, admin info is stored in database.
// The password hash is stored in the database during installation.
$admin_config = [
    'name' => 'Admin',
    'email' => 'admin@localhost',
    'password_hash' => '' // Will be set during installation
];

return $config;