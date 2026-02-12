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

// GitHub Repository (for updates)
if (!defined('GITHUB_REPO')) {
    define('GITHUB_REPO', 'https://github.com/marcinskotnicki/bgg_signup');
}
if (!defined('GITHUB_API')) {
    define('GITHUB_API', 'https://api.github.com/repos/marcinskotnicki/bgg_signup/contents/');
}

// Optional: GitHub Personal Access Token (to avoid rate limits)
// Get one at: https://github.com/settings/tokens (no permissions needed for public repos)
if (!defined('GITHUB_TOKEN')) {
    define('GITHUB_TOKEN', ''); // Add your token here if you have rate limit issues
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
    // true - users can choose between full deletion or soft delete
    // false - only soft delete allowed (except for admin)
    'allow_full_deletion' => false,
    
    // Comment Settings
    'restrict_comments' => false, // Require login to post comments
    'use_captcha' => false, // Use CAPTCHA for comments
    
    // Poll Settings
    'closed_poll_action' => 'grey', // What to do with closed polls: 'grey' (grey out) or 'delete' (remove)
    
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
?>