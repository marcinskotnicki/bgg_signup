<?php
/**
 * English Language File for BGG Signup System
 */

return [
    // Language name (displayed in language selector)
    '_language_name' => 'English',
    
    // Admin Panel
    'admin_panel' => 'Admin Panel',
    'admin_login' => 'Admin Login',
    'admin_password' => 'Admin Password',
    'login' => 'Login',
    'logout' => 'Logout',
    'invalid_password' => 'Invalid password!',
    'invalid_credentials' => 'Invalid email or password!',
    'not_admin_user' => 'This account does not have admin privileges!',
    'admin_email' => 'Admin Email',
    'view_site' => 'View Site',
    
    // Admin Tabs
    'tab_add_event' => 'Add New Event',
    'tab_options' => 'Options',
    'tab_logs' => 'Logs',
    'tab_update' => 'Update System',
    
    // Add Event Form
    'add_new_event' => 'Add New Event',
    'event_name' => 'Event Name',
    'number_of_days' => 'Number of Days',
    'day_number' => 'Day {number}',
    'date' => 'Date',
    'start_time' => 'Start Time',
    'end_time' => 'End Time',
    'number_of_tables' => 'Number of Tables',
    'create_event' => 'Create Event',
    'event_created_success' => 'Event created successfully!',
    'event_creation_error' => 'Error creating event: {error}',
    
    // Options
    'system_options' => 'System Options',
    'general_settings' => 'General Settings',
    'venue_name' => 'Venue Name',
    'default_event_name' => 'Default Event Name',
    'default_start_time' => 'Default Start Time',
    'default_end_time' => 'Default End Time',
    'timeline_extension' => 'Timeline Extension (hours after end time)',
    'default_tables' => 'Default Number of Tables',
    'bgg_api_token' => 'BoardGameGeek API Token',
    'default_language' => 'Default Language',
    'active_template' => 'Active Template',
    
    'smtp_settings' => 'SMTP Settings (for email notifications)',
    'smtp_email' => 'SMTP Email',
    'smtp_login' => 'SMTP Login',
    'smtp_password' => 'SMTP Password',
    'smtp_server' => 'SMTP Server',
    'smtp_port' => 'SMTP Port',
    
    'user_interaction_settings' => 'User Interaction Settings',
    'allow_reserve_list' => 'Allow Reserve List',
    'allow_logged_in' => 'Allow Logged In Users',
    'require_emails' => 'Require Emails',
    'verification_method' => 'Verification Method (for edits/deletions)',
    'send_emails' => 'Send Email Notifications',
    'allow_full_deletion' => 'Allow Full Deletion of Games',
    'restrict_comments' => 'Restrict Comments to Logged In Users',
    'use_captcha' => 'Use CAPTCHA for Comments',
    
    'custom_messages' => 'Custom Messages',
    'homepage_message' => 'Homepage Message (displayed under event title)',
    'add_game_message' => 'Add Game Message (displayed above game form)',
    'add_player_message' => 'Add Player Message (displayed above player signup form)',
    
    'change_admin_password' => 'Change Admin Password',
    'new_admin_password' => 'New Admin Password (leave blank to keep current)',
    
    'save_options' => 'Save Options',
    'options_updated_success' => 'Options updated successfully!',
    'options_and_password_updated' => 'Options and password updated successfully!',
    'options_update_error' => 'Error updating options: {error}',
    
    // Options - Dropdowns
    'yes' => 'Yes',
    'no' => 'No',
    'login_no' => 'No',
    'login_yes' => 'Yes',
    'login_required_games' => 'Required for Adding Games',
    'login_required_all' => 'Required Everywhere',
    'verification_email' => 'Require Email Match',
    'verification_link' => 'Send Confirmation Link',
    'deletion_soft_only' => 'Soft Delete Only',
    'deletion_allow_choice' => 'Allow Choice (Soft or Hard)',
    'deletion_hard_only' => 'Hard Delete Only',
    'deletion_mode' => 'Game Deletion Mode',
    'deletion_mode_help' => 'Soft delete allows restoring games. Hard delete is permanent.',
    'delete_choice_prompt' => 'Click OK for soft delete (can be restored) or Cancel for permanent deletion.',
    'confirm_hard_delete' => 'PERMANENTLY delete this game? This cannot be undone!',
    'confirm_hard_delete_game' => 'PERMANENTLY delete this game? This cannot be undone!',
    
    // Logs
    'activity_logs' => 'Activity Logs',
    'no_logs_found' => 'No logs found yet.',
    'show_logs_from' => 'Show logs from',
    'today' => 'Today',
    'last_100_entries' => 'Last 100 entries',
    'all_logs' => 'All logs',
    'filter' => 'Filter',
    'log_entries_shown' => 'log entries shown',
    'timestamp' => 'Timestamp',
    'user' => 'User',
    'ip_address' => 'IP Address',
    'action' => 'Action',
    'details' => 'Details',
    'logs_directory_not_found' => 'Logs directory not found. Logs will be created automatically when actions are performed.',
    
    // Update System
    'update_system' => 'Update System',
    'update_warning' => 'Warning: This will check GitHub for updates and apply them to your system. A backup will be created automatically before any changes are made.',
    'github_repository' => 'GitHub Repository',
    'run_update' => 'Run Update',
    'update_confirm' => 'Are you sure you want to run the update? A backup will be created first.',
    'update_completed_success' => 'Update completed successfully!',
    'update_failed' => 'Update failed! Check the logs for details.',
    'update_log' => 'Update Log',
    
    // Messages
    'success' => 'Success',
    'error' => 'Error',
    'warning' => 'Warning',
    
    // Frontend - Game Display
    'game_name' => 'Game Name',
    'play_time' => 'Play Time',
    'players' => 'Players',
    'difficulty' => 'Difficulty',
    'language' => 'Language',
    'host' => 'Host',
    'rules_explanation' => 'Rules Explanation',
    'rules_will_be_explained' => 'Rules will be explained',
    'rules_knowledge_required' => 'All players should know the rules',
    'minutes' => 'minutes',
    'min_max_players' => '{min}-{max} players',
    
    // Frontend - Player List
    'join_game' => 'Join Game',
    'join_reserve' => 'Join Reserve List',
    'reserve_list' => 'Reserve List',
    'player_name' => 'Player Name',
    'player_email' => 'Email',
    'knows_rules' => 'Do you know the rules?',
    'knows_rules_yes' => 'Yes',
    'knows_rules_no' => 'No',
    'knows_rules_somewhat' => 'Somewhat',
    'comment' => 'Comment',
    'additional_comment' => 'Additional Comment',
    'sign_up' => 'Sign Up',
    
    // Frontend - Game Actions
    'add_new_table' => 'Add New Table',
    'add_game_to_table' => 'Add Game to This Table',
    'edit' => 'Edit',
    'delete' => 'Delete',
    'restore' => 'Restore',
    'fully_delete' => 'Fully Delete',
    
    // Frontend - Add Game
    'search_bgg' => 'Search BoardGameGeek',
    'add_game_manual' => 'Add Game Manually',
    'search_game' => 'Search for a game',
    'add_game' => 'Add Game',
    'game_details' => 'Game Details',
    'select_thumbnail' => 'Select Thumbnail',
    'host_name' => 'Your Name',
    'host_email' => 'Your Email',
    'join_as_first_player' => 'Do you also want to join this game as the first player?',
    'language_independent' => 'Language Independent',
    
    // Frontend - Comments
    'comments' => 'Comments',
    'add_comment' => 'Add Comment',
    'post_comment' => 'Post Comment',
    'author_name' => 'Your Name',
    
    // Frontend - Timeline
    'timeline' => 'Timeline',
    'table' => 'Table',
    
    // Frontend - Messages
    'no_active_event' => 'No active event at the moment.',
    'loading' => 'Loading...',
    
    // Validation Messages
    'field_required' => 'This field is required',
    'invalid_email' => 'Please provide a valid email address',
    'passwords_dont_match' => 'Passwords do not match',
    'password_too_short' => 'Password must be at least 6 characters',
    'all_fields_required' => 'All fields are required',
    'email_already_exists' => 'This email address is already registered',
    'registration_failed' => 'Registration failed. Please try again.',
    'user_not_found' => 'User not found',
    'incorrect_current_password' => 'Current password is incorrect',
    'update_failed' => 'Update failed. Please try again.',
    
    // Login/Register
    'register' => 'Register',
    'email' => 'Email',
    'password' => 'Password',
    'name' => 'Name',
    'confirm_password' => 'Confirm Password',
    'password_min_6_chars' => 'At least 6 characters',
    'dont_have_account' => "Don't have an account?",
    'register_now' => 'Register now',
    'already_have_account' => 'Already have an account?',
    'login_here' => 'Login here',
    'back_to_homepage' => 'Back to homepage',
    
    // User Profile
    'user_profile' => 'User Profile',
    'your_activity' => 'Your Activity',
    'games_created' => 'Games Created',
    'games_joined' => 'Games Joined',
    'comments_made' => 'Comments Made',
    'update_profile' => 'Update Profile',
    'profile_update_info' => 'You need to enter your current password to confirm any changes to your profile.',
    'basic_information' => 'Basic Information',
    'display_name' => 'Display Name',
    'name_visible_to_others' => 'This name will be visible to other users',
    'email_used_for_login' => 'Used for login and notifications',
    'change_password' => 'Change Password',
    'new_password' => 'New Password',
    'leave_blank_keep_current' => 'Leave blank to keep current password',
    'confirm_changes' => 'Confirm Changes',
    'current_password' => 'Current Password',
    'required_to_confirm_changes' => 'Required to confirm any changes',
    'save_changes' => 'Save Changes',
    'profile_updated_successfully' => 'Profile updated successfully!',
    
    // Additional UI
    'player' => 'Player',
    'resign' => 'Resign',
    'day' => 'Day',
    'no_games_yet' => 'No games added to this table yet.',
    'no_event_description' => 'Check back later or contact the organizer.',
    'login_required_to_add_game' => 'You must be logged in to add a game.',
    'login_required_to_add_table' => 'You must be logged in to add a table.',
    'login_required_to_join' => 'You must be logged in to join a game.',
    'error_adding_table' => 'Error adding table.',
    'confirm_resign' => 'Are you sure you want to resign from this game?',
    'confirm_delete_game' => 'Are you sure you want to delete this game?',
    'confirm_soft_delete' => 'Soft delete (can be restored)?',
    'confirm_hard_delete' => 'PERMANENTLY delete? This cannot be undone!',
    'confirm_fully_delete' => 'Are you sure you want to permanently delete this game? This cannot be undone!',
    'error_occurred' => 'An error occurred. Please try again.',
    
    // Email subjects
    'email_subject_player_joined' => 'New player joined: {game}',
    'email_subject_player_joined_reserve' => 'New player joined reserve list: {game}',
    'email_subject_player_resigned' => 'Player resigned: {game}',
    'email_subject_promoted_from_reserve' => 'You have been promoted from reserve: {game}',
    'email_subject_game_changed' => 'Game details changed: {game}',
    'email_subject_game_deleted' => 'Game cancelled: {game}',
    'email_subject_comment_added' => 'New comment on: {game}',
    
    // Email bodies
    'email_body_player_joined' => '<strong>{player}</strong> has joined your game <strong>{game}</strong>.<br><br>Event: {event}<br>Date: {date}<br>Time: {time}',
    'email_body_player_joined_reserve' => '<strong>{player}</strong> has joined the reserve list for your game <strong>{game}</strong>.<br><br>Event: {event}<br>Date: {date}<br>Time: {time}',
    'email_body_player_resigned' => '<strong>{player}</strong> has resigned from your game <strong>{game}</strong>.<br><br>Event: {event}<br>Date: {date}<br>Time: {time}',
    'email_body_promoted_from_reserve' => 'Good news! A spot has opened up in <strong>{game}</strong> and you have been promoted from the reserve list.<br><br>Event: {event}<br>Date: {date}<br>Time: {time}',
    'email_body_game_changed' => 'The details for <strong>{game}</strong> have been updated.<br><br>Event: {event}<br>Date: {date}<br>Time: {time}',
    'email_body_game_deleted' => 'Unfortunately, <strong>{game}</strong> has been cancelled.<br><br>Event: {event}<br>Date: {date}<br>Time: {time}',
    'email_body_comment_added' => '<strong>{author}</strong> added a comment to <strong>{game}</strong>:<br><br>{comment}',
    
    // Email footers
    'email_footer_view_game' => 'View Game',
    'email_footer_view_event' => 'View Event',
    'email_automated_message' => 'This is an automated message. Please do not reply.',
    'changes' => 'Changes',
    
    // Form labels
    'search_or_add_manually' => 'Search BoardGameGeek or add manually',
    'enter_game_name' => 'Please enter a game name',
    'no_results_found' => 'No results found',
    'loading_game_details' => 'Loading game details',
    'saving' => 'Saving',
    'back' => 'Back',
    'min_players' => 'Minimum Players',
    'max_players' => 'Maximum Players',
    'cancel' => 'Cancel',
    'select_option' => 'Select an option',
    'restore_game' => 'Restore Game',
    'players_signed_up' => 'players signed up',
    'restore_game_info' => 'By restoring this game, you will become the new host. All previously signed-up players will remain.',
    'your_name' => 'Your Name',
    'your_email' => 'Your Email',
    'login_required_to_comment' => 'You must be logged in to add comments.',
    'existing_comments' => 'Existing Comments',
    'captcha_question' => 'What is {num1} + {num2}?',
    
    // Poll System
    'create_poll' => 'Create Poll',
    'create_game_poll' => 'Create Game Selection Poll',
    'poll_info_text' => 'Create a poll to let players vote on which game to play. When a game reaches its vote threshold, it will be automatically added to the schedule and the poll will close.',
    'your_information' => 'Your Information',
    'add_game_option' => 'Add Game Option',
    'add_game_to_poll' => 'Add Games to Poll',
    'game_poll' => 'Game Selection Poll',
    'vote_for_game' => 'Vote for Game',
    'vote_threshold' => 'Votes needed',
    'votes' => 'votes',
    'vote_for_this' => 'Vote for this',
    'submit_vote' => 'Submit Vote',
    'email_notification_on_poll_close' => 'You will receive an email when the poll closes',
    'winner' => 'Winner',
    'created_by' => 'Created by',
    'closed' => 'Closed',
    'email_subject_poll_closed' => 'Poll closed: {game} was selected',
    'email_body_poll_closed' => 'The poll has closed and <strong>{game}</strong> was selected for the event <strong>{event}</strong>!',
    
    // Private Messages
    'allow_private_messages' => 'Allow Private Messages',
    'allow_private_messages_help' => 'Allow users to send private messages to players via email',
    'send_private_message' => 'Send Private Message',
    'send_message_to_all_players' => 'Send message to all players in this game',
    'message_sent_successfully' => 'Message sent successfully',
    'to' => 'To',
    'regarding' => 'Regarding',
    'message' => 'Message',
    'send_message' => 'Send Message',
    'sending' => 'Sending',
    'reply_to_address' => 'Recipients can reply directly to this address',
    'all_players' => 'All Players',
    
    // User Management
    'tab_users' => 'Users',
    'user_management' => 'User Management',
    'role' => 'Role',
    'admin' => 'Admin',
    'user' => 'User',
    'actions' => 'Actions',
    'make_admin' => 'Make Admin',
    'make_user' => 'Make User',
    'reset_password' => 'Reset Password',
    
    // Date formatting - Month names (short)
    '_months_short' => [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
    ],
    
    // Date formatting - Month names (full)
    '_months_full' => [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ],
    
    // Date formatting - Day names (full)
    '_days_full' => [
        0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
        4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'
    ],
];
?>