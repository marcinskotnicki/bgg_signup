<?php
/**
 * Database Schema Definition
 * 
 * Single source of truth for database structure.
 * Used by both install.php and update.php
 * 
 * DO NOT modify this file directly in production.
 * Schema changes should be made via GitHub and deployed through update.php
 */

// Prevent direct access
if (!defined('SCHEMA_ACCESS')) {
    die('Direct access not allowed');
}

/**
 * Get database schema definition
 * 
 * @return array Associative array of table names => CREATE TABLE SQL
 */
function get_database_schema() {
    return [
        'events' => "CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        'event_days' => "CREATE TABLE IF NOT EXISTS event_days (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_id INTEGER NOT NULL,
            day_number INTEGER NOT NULL,
            date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            max_tables INTEGER NOT NULL,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        )",
        
        'tables' => "CREATE TABLE IF NOT EXISTS tables (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_day_id INTEGER NOT NULL,
            table_number INTEGER NOT NULL,
            FOREIGN KEY (event_day_id) REFERENCES event_days(id) ON DELETE CASCADE
        )",
        
        'games' => "CREATE TABLE IF NOT EXISTS games (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            table_id INTEGER NOT NULL,
            bgg_id INTEGER,
            bgg_url TEXT,
            name TEXT NOT NULL,
            thumbnail TEXT,
            play_time INTEGER NOT NULL,
            min_players INTEGER NOT NULL,
            max_players INTEGER NOT NULL,
            difficulty REAL,
            start_time TIME NOT NULL,
            host_name TEXT NOT NULL,
            host_email TEXT,
            creator_email TEXT,
            language TEXT NOT NULL,
            rules_explanation TEXT NOT NULL,
            initial_comment TEXT,
            is_active INTEGER DEFAULT 1,
            created_by_user_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE CASCADE
        )",
        
        'players' => "CREATE TABLE IF NOT EXISTS players (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            game_id INTEGER NOT NULL,
            player_name TEXT NOT NULL,
            player_email TEXT,
            knows_rules TEXT,
            comment TEXT,
            is_reserve INTEGER DEFAULT 0,
            position INTEGER NOT NULL,
            user_id INTEGER,
            verification_code TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
        )",
        
        'comments' => "CREATE TABLE IF NOT EXISTS comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            game_id INTEGER NOT NULL,
            author_name TEXT NOT NULL,
            author_email TEXT,
            comment TEXT NOT NULL,
            user_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
        )",
        
        'users' => "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            is_admin INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        'options' => "CREATE TABLE IF NOT EXISTS options (
            option_key TEXT PRIMARY KEY,
            option_value TEXT
        )",
        
        'bgg_cache' => "CREATE TABLE IF NOT EXISTS bgg_cache (
            cache_key TEXT PRIMARY KEY,
            cache_data TEXT NOT NULL,
            cached_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        'polls' => "CREATE TABLE IF NOT EXISTS polls (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            table_id INTEGER NOT NULL,
            creator_name TEXT NOT NULL,
            creator_email TEXT,
            comment TEXT,
            created_by_user_id INTEGER,
            start_time TIME,
            verification_code TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            closed_at DATETIME,
            FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE CASCADE
        )",
        
        'poll_options' => "CREATE TABLE IF NOT EXISTS poll_options (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            poll_id INTEGER NOT NULL,
            bgg_id INTEGER,
            bgg_url TEXT,
            game_name TEXT NOT NULL,
            thumbnail TEXT,
            play_time INTEGER,
            min_players INTEGER,
            max_players INTEGER,
            difficulty REAL,
            vote_threshold INTEGER NOT NULL,
            display_order INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
        )",
        
        'poll_votes' => "CREATE TABLE IF NOT EXISTS poll_votes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            poll_option_id INTEGER NOT NULL,
            voter_name TEXT NOT NULL,
            voter_email TEXT NOT NULL,
            user_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (poll_option_id) REFERENCES poll_options(id) ON DELETE CASCADE
        )"
    ];
}

/**
 * Get expected columns for tables that may need migration
 * 
 * @return array Associative array of table => [column => ALTER statement]
 */
function get_schema_migrations() {
    return [
        'players' => [
            'verification_code' => "ALTER TABLE players ADD COLUMN verification_code TEXT"
        ],
        'polls' => [
            'start_time' => "ALTER TABLE polls ADD COLUMN start_time TIME",
            'comment' => "ALTER TABLE polls ADD COLUMN comment TEXT",
            'verification_code' => "ALTER TABLE polls ADD COLUMN verification_code TEXT"
        ],
        'games' => [
            'creator_email' => "ALTER TABLE games ADD COLUMN creator_email TEXT",
            'verification_code' => "ALTER TABLE games ADD COLUMN verification_code TEXT"
        ]
    ];
}

/**
 * Get default options to be inserted during installation
 * 
 * @return array Associative array of option_key => default_value
 */
function get_default_options() {
    return [
        'venue_name' => '',
        'default_event_name' => '',
        'default_start_time' => '',
        'default_end_time' => '',
        'timeline_extension' => '3',
        'default_tables' => '5',
        'smtp_email' => '',
        'smtp_login' => '',
        'smtp_password' => '',
        'smtp_server' => '',
        'smtp_port' => '587',
        'allow_reserve_list' => '1',
        'homepage_message' => '',
        'add_game_message' => '',
        'add_player_message' => '',
        'allow_logged_in' => 'no',
        'require_emails' => 'no',
        'verification_method' => 'email',
        'send_emails' => 'no',
        'allow_full_deletion' => 'no',
        'bgg_api_token' => '',
        'default_language' => 'en',
        'restrict_comments' => 'no',
        'use_captcha' => 'no',
        'admin_password' => '' // Will be set during installation
    ];
}
