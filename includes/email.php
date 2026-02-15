<?php
/**
 * Email Helper Functions
 * 
 * Handles sending email notifications for various events
 */

/**
 * Send email using SMTP or phpmail() fallback
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email body (HTML)
 * @param array $config Configuration array
 * @param string $reply_to Optional reply-to email address
 * @return bool Success status
 */
function send_email($to, $subject, $message, $config, $reply_to = null) {
    // Check if emails are enabled
    if (!$config['send_emails']) {
        return false;
    }
    
    // Validate recipient email
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("Email not sent: Invalid recipient email");
        return false;
    }
    
    // Determine sender email
    $from_email = !empty($config['smtp_email']) ? $config['smtp_email'] : 'noreply@' . $_SERVER['HTTP_HOST'];
    
    // Determine reply-to
    if ($reply_to === null) {
        $reply_to = $from_email;
    }
    
    // Prepare email headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$from_email}\r\n";
    $headers .= "Reply-To: {$reply_to}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Use PHP mail() function (works with or without SMTP configured)
    // Note: Requires proper mail server configuration on the hosting server
    $result = @mail($to, $subject, $message, $headers);
    
    if (!$result) {
        $last_error = error_get_last();
        error_log("Email send failed: " . ($last_error ? $last_error['message'] : 'Unknown error'));
    }
    
    return $result;
}

/**
 * Send email when a new player joins a game
 * 
 * @param PDO $db Database connection
 * @param int $game_id Game ID
 * @param string $player_name Player name
 * @param bool $is_reserve Whether player joined reserve list
 */
function email_player_joined($db, $game_id, $player_name, $is_reserve = false) {
    $config = require __DIR__ . '/../config.php';
    
    if (!$config['send_emails']) {
        return;
    }
    
    // Get game details
    $stmt = $db->prepare("SELECT g.*, e.name as event_name, ed.date as event_date 
                          FROM games g 
                          JOIN tables t ON g.table_id = t.id 
                          JOIN event_days ed ON t.event_day_id = ed.id 
                          JOIN events e ON ed.event_id = e.id 
                          WHERE g.id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game || !$game['host_email']) {
        return;
    }
    
    // Prepare email
    $subject = $is_reserve 
        ? t('email_subject_player_joined_reserve', ['game' => $game['name']])
        : t('email_subject_player_joined', ['game' => $game['name']]);
    
    $message = email_template([
        'title' => $subject,
        'content' => $is_reserve
            ? t('email_body_player_joined_reserve', [
                'player' => $player_name,
                'game' => $game['name'],
                'event' => $game['event_name'],
                'date' => format_date($game['event_date'], 'full'),
                'time' => $game['start_time']
            ])
            : t('email_body_player_joined', [
                'player' => $player_name,
                'game' => $game['name'],
                'event' => $game['event_name'],
                'date' => format_date($game['event_date'], 'full'),
                'time' => $game['start_time']
            ]),
        'footer' => t('email_footer_view_game'),
        'link' => get_site_url() . 'index.php#game_' . $game_id
    ]);
    
    send_email($game['host_email'], $subject, $message, $config);
}

/**
 * Send email when a player resigns from a game
 * 
 * @param PDO $db Database connection
 * @param int $game_id Game ID
 * @param string $player_name Player name
 */
function email_player_resigned($db, $game_id, $player_name) {
    $config = require __DIR__ . '/../config.php';
    
    if (!$config['send_emails']) {
        return;
    }
    
    // Get game details
    $stmt = $db->prepare("SELECT g.*, e.name as event_name, ed.date as event_date 
                          FROM games g 
                          JOIN tables t ON g.table_id = t.id 
                          JOIN event_days ed ON t.event_day_id = ed.id 
                          JOIN events e ON ed.event_id = e.id 
                          WHERE g.id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game || !$game['host_email']) {
        return;
    }
    
    // Prepare email
    $subject = t('email_subject_player_resigned', ['game' => $game['name']]);
    
    $message = email_template([
        'title' => $subject,
        'content' => t('email_body_player_resigned', [
            'player' => $player_name,
            'game' => $game['name'],
            'event' => $game['event_name'],
            'date' => format_date($game['event_date'], 'full'),
            'time' => $game['start_time']
        ]),
        'footer' => t('email_footer_view_game'),
        'link' => get_site_url() . 'index.php#game_' . $game_id
    ]);
    
    send_email($game['host_email'], $subject, $message, $config);
}

/**
 * Send email when a player is promoted from reserve to active
 * 
 * @param PDO $db Database connection
 * @param int $player_id Player ID
 * @param int $game_id Game ID
 */
function email_player_promoted($db, $player_id, $game_id) {
    $config = require __DIR__ . '/../config.php';
    
    if (!$config['send_emails']) {
        return;
    }
    
    // Get player and game details
    $stmt = $db->prepare("SELECT p.player_email, g.*, e.name as event_name, ed.date as event_date 
                          FROM players p
                          JOIN games g ON p.game_id = g.id 
                          JOIN tables t ON g.table_id = t.id 
                          JOIN event_days ed ON t.event_day_id = ed.id 
                          JOIN events e ON ed.event_id = e.id 
                          WHERE p.id = ? AND g.id = ?");
    $stmt->execute([$player_id, $game_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data || !$data['player_email']) {
        return;
    }
    
    // Prepare email
    $subject = t('email_subject_promoted_from_reserve', ['game' => $data['name']]);
    
    $message = email_template([
        'title' => $subject,
        'content' => t('email_body_promoted_from_reserve', [
            'game' => $data['name'],
            'event' => $data['event_name'],
            'date' => format_date($data['event_date'], 'full'),
            'time' => $data['start_time']
        ]),
        'footer' => t('email_footer_view_game'),
        'link' => get_site_url() . 'index.php#game_' . $game_id
    ]);
    
    send_email($data['player_email'], $subject, $message, $config);
}

/**
 * Send email to all players when a game is changed
 * 
 * @param PDO $db Database connection
 * @param int $game_id Game ID
 * @param string $change_description Description of what changed
 */
function email_game_changed($db, $game_id, $change_description = '') {
    $config = require __DIR__ . '/../config.php';
    
    if (!$config['send_emails']) {
        return;
    }
    
    // Get game details
    $stmt = $db->prepare("SELECT g.*, e.name as event_name, ed.date as event_date 
                          FROM games g 
                          JOIN tables t ON g.table_id = t.id 
                          JOIN event_days ed ON t.event_day_id = ed.id 
                          JOIN events e ON ed.event_id = e.id 
                          WHERE g.id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        return;
    }
    
    // Get all players with email addresses
    $stmt = $db->prepare("SELECT DISTINCT player_email FROM players WHERE game_id = ? AND player_email IS NOT NULL AND player_email != ''");
    $stmt->execute([$game_id]);
    $players = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($players)) {
        return;
    }
    
    // Prepare email
    $subject = t('email_subject_game_changed', ['game' => $game['name']]);
    
    $content = t('email_body_game_changed', [
        'game' => $game['name'],
        'event' => $game['event_name'],
        'date' => format_date($game['event_date'], 'full'),
        'time' => $game['start_time']
    ]);
    
    if ($change_description) {
        $content .= "<br><br><strong>" . t('changes') . ":</strong><br>" . htmlspecialchars($change_description);
    }
    
    $message = email_template([
        'title' => $subject,
        'content' => $content,
        'footer' => t('email_footer_view_game'),
        'link' => get_site_url() . 'index.php#game_' . $game_id
    ]);
    
    // Send to all players
    foreach ($players as $email) {
        send_email($email, $subject, $message, $config);
    }
}

/**
 * Send email to all players when a game is deleted
 * 
 * @param PDO $db Database connection
 * @param int $game_id Game ID
 */
function email_game_deleted($db, $game_id) {
    $config = require __DIR__ . '/../config.php';
    
    if (!$config['send_emails']) {
        return;
    }
    
    // Get game details
    $stmt = $db->prepare("SELECT g.*, e.name as event_name, ed.date as event_date 
                          FROM games g 
                          JOIN tables t ON g.table_id = t.id 
                          JOIN event_days ed ON t.event_day_id = ed.id 
                          JOIN events e ON ed.event_id = e.id 
                          WHERE g.id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        return;
    }
    
    // Get all players with email addresses
    $stmt = $db->prepare("SELECT DISTINCT player_email FROM players WHERE game_id = ? AND player_email IS NOT NULL AND player_email != ''");
    $stmt->execute([$game_id]);
    $players = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($players)) {
        return;
    }
    
    // Prepare email
    $subject = t('email_subject_game_deleted', ['game' => $game['name']]);
    
    $message = email_template([
        'title' => $subject,
        'content' => t('email_body_game_deleted', [
            'game' => $game['name'],
            'event' => $game['event_name'],
            'date' => format_date($game['event_date'], 'full'),
            'time' => $game['start_time']
        ]),
        'footer' => t('email_footer_view_event'),
        'link' => get_site_url() . 'index.php'
    ]);
    
    // Send to all players
    foreach ($players as $email) {
        send_email($email, $subject, $message, $config);
    }
}

/**
 * Send email to all players when a comment is added to a game
 * 
 * @param PDO $db Database connection
 * @param int $game_id Game ID
 * @param string $author_name Comment author name
 * @param string $comment Comment text
 */
function email_comment_added($db, $game_id, $author_name, $comment) {
    $config = require __DIR__ . '/../config.php';
    
    if (!$config['send_emails']) {
        return;
    }
    
    // Get game details
    $stmt = $db->prepare("SELECT g.*, e.name as event_name, ed.date as event_date 
                          FROM games g 
                          JOIN tables t ON g.table_id = t.id 
                          JOIN event_days ed ON t.event_day_id = ed.id 
                          JOIN events e ON ed.event_id = e.id 
                          WHERE g.id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        return;
    }
    
    // Get all players with email addresses
    $stmt = $db->prepare("SELECT DISTINCT player_email FROM players WHERE game_id = ? AND player_email IS NOT NULL AND player_email != ''");
    $stmt->execute([$game_id]);
    $players = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($players)) {
        return;
    }
    
    // Prepare email
    $subject = t('email_subject_comment_added', ['game' => $game['name']]);
    
    $message = email_template([
        'title' => $subject,
        'content' => t('email_body_comment_added', [
            'author' => $author_name,
            'game' => $game['name'],
            'comment' => nl2br(htmlspecialchars($comment))
        ]),
        'footer' => t('email_footer_view_game'),
        'link' => get_site_url() . 'index.php#game_' . $game_id
    ]);
    
    // Send to all players
    foreach ($players as $email) {
        send_email($email, $subject, $message, $config);
    }
}

/**
 * Get site URL
 * 
 * @return string Site URL with trailing slash
 */
function get_site_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    
    // Remove trailing slash if exists, then add one
    $path = rtrim($path, '/') . '/';
    
    return $protocol . '://' . $host . $path;
}

/**
 * Email HTML template
 * 
 * @param array $data Template data (title, content, footer, link)
 * @return string HTML email
 */
function email_template($data) {
    $config = require __DIR__ . '/../config.php';
    
    $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .email-header {
            background: #3498db;
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
        }
        .email-body {
            padding: 30px 20px;
        }
        .email-content {
            margin-bottom: 20px;
            font-size: 15px;
        }
        .email-button {
            text-align: center;
            margin: 30px 0;
        }
        .email-button a {
            display: inline-block;
            padding: 12px 30px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .email-footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 13px;
            color: #7f8c8d;
            border-top: 1px solid #ecf0f1;
        }
        .email-footer p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>' . htmlspecialchars($config['venue_name']) . '</h1>
        </div>
        <div class="email-body">
            <div class="email-content">
                ' . $data['content'] . '
            </div>
            ' . (isset($data['link']) ? '
            <div class="email-button">
                <a href="' . htmlspecialchars($data['link']) . '">' . $data['footer'] . '</a>
            </div>
            ' : '') . '
        </div>
        <div class="email-footer">
            <p>&copy; ' . date('Y') . ' ' . htmlspecialchars($config['venue_name']) . '</p>
            <p>' . t('email_automated_message') . '</p>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Send email when poll closes
 */
function email_poll_closed($db, $poll_id, $winning_game_name, $voter_email) {
    $config = require __DIR__ . '/../config.php';
    
    if (!$config['send_emails'] || !$voter_email) {
        return;
    }
    
    // Get poll details
    $stmt = $db->prepare("SELECT p.*, e.name as event_name 
                          FROM polls p 
                          JOIN tables t ON p.table_id = t.id 
                          JOIN event_days ed ON t.event_day_id = ed.id 
                          JOIN events e ON ed.event_id = e.id 
                          WHERE p.id = ?");
    $stmt->execute([$poll_id]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$poll) {
        return;
    }
    
    $subject = t('email_subject_poll_closed', ['game' => $winning_game_name]);
    
    $message = email_template([
        'title' => $subject,
        'content' => t('email_body_poll_closed', [
            'game' => $winning_game_name,
            'event' => $poll['event_name']
        ]),
        'footer' => t('email_footer_view_event'),
        'link' => get_site_url() . 'index.php'
    ]);
    
    send_email($voter_email, $subject, $message, $config);
}

/**
 * Send password reset email
 * 
 * @param PDO $db Database connection
 * @param string $email User email address
 * @param string $reset_link Password reset link
 */
function email_password_reset($db, $email, $reset_link) {
    global $config;
    
    if ($config['send_emails'] !== 'yes') {
        return; // Emails disabled
    }
    
    $stmt = $db->prepare("SELECT name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return;
    }
    
    $subject = t('password_reset_email_subject');
    
    $message = email_template([
        'title' => t('password_reset_email_title'),
        'content' => sprintf(t('password_reset_email_body'), htmlspecialchars($user['name'])) . 
            '<br><br><div style="text-align: center;"><a href="' . htmlspecialchars($reset_link) . 
            '" style="display:inline-block; padding:12px 24px; background:#667eea; color:white; text-decoration:none; border-radius:5px; font-weight:bold;">' . 
            t('reset_password_button') . '</a></div><br>' .
            '<p style="color:#666; font-size:13px;">' . t('password_reset_link_expires') . '</p>' .
            '<p style="color:#666; font-size:13px;">' . t('password_reset_ignore_if_not_you') . '</p>',
        'footer' => '',
        'link' => ''
    ]);
    
    send_email($email, $subject, $message, $config);
}