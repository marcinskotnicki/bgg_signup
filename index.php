<?php
/**
 * Main Frontend Page - Event Display
 * 
 * Shows the active event with:
 * - Day tabs for multi-day events
 * - Tables with games
 * - Player signups
 * - Timeline visualization
 */

// Load configuration
$config = require_once 'config.php';

// Auto-migrate old allow_full_deletion to deletion_mode if needed
if (!isset($config['deletion_mode']) && isset($config['allow_full_deletion'])) {
    $config['deletion_mode'] = $config['allow_full_deletion'] ? 'allow_choice' : 'soft_only';
}
// Set default if neither exists
if (!isset($config['deletion_mode'])) {
    $config['deletion_mode'] = 'soft_only';
}

// Prevent browser caching to ensure fresh data is always displayed
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Load translation system
require_once 'includes/translations.php';

// Load auth helper
require_once 'includes/auth.php';

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout_user();
    header('Location: index.php');
    exit;
}

// Load BGG API helper
require_once 'includes/bgg_api.php';

// Database connection
try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get current user
$current_user = get_current_user($db);

// Check if viewing archived event by date
$viewing_archive = false;
$archive_date = null;

if (isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
    $archive_date = $_GET['date'];
    $viewing_archive = true;
    
    // Find event that has this date
    $stmt = $db->prepare("
        SELECT DISTINCT e.* 
        FROM events e
        JOIN event_days ed ON e.id = ed.event_id
        WHERE ed.date = ?
        ORDER BY e.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$archive_date]);
    $active_event = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Get active event (normal behavior)
    $stmt = $db->query("SELECT * FROM events WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $active_event = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get event days if event exists
$event_days = [];
if ($active_event) {
    $stmt = $db->prepare("SELECT * FROM event_days WHERE event_id = ? ORDER BY day_number ASC");
    $stmt->execute([$active_event['id']]);
    $event_days = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If viewing archive, filter to show only the specific date
    if ($viewing_archive && $archive_date) {
        $event_days = array_filter($event_days, function($day) use ($archive_date) {
            return $day['date'] === $archive_date;
        });
        $event_days = array_values($event_days); // Re-index array
    }
}

// Determine which day to show (default to first day)
$selected_day_id = null;
if (!empty($event_days)) {
    if (isset($_GET['day']) && is_numeric($_GET['day'])) {
        $day_index = intval($_GET['day']) - 1;
        if (isset($event_days[$day_index])) {
            $selected_day_id = $event_days[$day_index]['id'];
        }
    }
    
    // Default to first day if not set
    if (!$selected_day_id) {
        $selected_day_id = $event_days[0]['id'];
    }
}

// Get selected day data
$selected_day = null;
if ($selected_day_id) {
    foreach ($event_days as $day) {
        if ($day['id'] == $selected_day_id) {
            $selected_day = $day;
            break;
        }
    }
}

// Get tables for selected day
$tables = [];
if ($selected_day_id) {
    $stmt = $db->prepare("SELECT * FROM tables WHERE event_day_id = ? ORDER BY table_number ASC");
    $stmt->execute([$selected_day_id]);
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get games for each table with players
$tables_with_games = [];
foreach ($tables as $table) {
    $stmt = $db->prepare("SELECT * FROM games WHERE table_id = ? ORDER BY start_time ASC");
    $stmt->execute([$table['id']]);
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get players for each game
    foreach ($games as &$game) {
        $stmt = $db->prepare("SELECT * FROM players WHERE game_id = ? ORDER BY is_reserve ASC, position ASC");
        $stmt->execute([$game['id']]);
        $game['players'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get comments for each game
        $stmt = $db->prepare("SELECT * FROM comments WHERE game_id = ? ORDER BY created_at ASC");
        $stmt->execute([$game['id']]);
        $game['comments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($game); // CRITICAL: Break reference to prevent variable pollution across tables
    
    // Get active polls for this table
    $stmt = $db->prepare("SELECT * FROM polls WHERE table_id = ? AND is_active = 1 ORDER BY created_at ASC");
    $stmt->execute([$table['id']]);
    $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get poll options with vote counts for each poll
    foreach ($polls as &$poll) {
        $stmt = $db->prepare("
            SELECT po.*, COUNT(pv.id) as vote_count 
            FROM poll_options po 
            LEFT JOIN poll_votes pv ON po.id = pv.poll_option_id 
            WHERE po.poll_id = ? 
            GROUP BY po.id 
            ORDER BY po.display_order ASC
        ");
        $stmt->execute([$poll['id']]);
        $poll['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($poll); // CRITICAL: Break reference
    
    $tables_with_games[] = [
        'table' => $table,
        'games' => $games,
        'polls' => $polls
    ];
}

// Page title
$page_title = $active_event ? $active_event['name'] : t('no_active_event');

// Template directory - check user preference first, then site default
$active_template = $config['active_template']; // Site default

// If user is logged in and has a preferred template, use it
if ($current_user && !empty($current_user['preferred_template'])) {
    // Verify the template exists
    $user_template_path = TEMPLATES_DIR . '/' . $current_user['preferred_template'];
    if (is_dir($user_template_path)) {
        $active_template = $current_user['preferred_template'];
    }
}

$template_dir = TEMPLATES_DIR . '/' . $active_template;

// Include header
include $template_dir . '/header.php';
?>

<?php if ($viewing_archive && $archive_date): ?>
<div class="archive-banner">
    <div class="archive-banner-content">
        <span class="archive-icon">📅</span>
        <span class="archive-text">
            <strong><?php echo t('viewing_archive'); ?>:</strong> 
            <?php echo format_date($archive_date, 'full'); ?>
        </span>
        <a href="index.php" class="btn-current-event"><?php echo t('view_current_event'); ?></a>
    </div>
</div>
<style>
.archive-banner {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 0;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.archive-banner-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}
.archive-icon {
    font-size: 24px;
}
.archive-text {
    flex: 1;
    font-size: 16px;
}
.btn-current-event {
    background: white;
    color: #667eea;
    padding: 8px 16px;
    border-radius: 4px;
    text-decoration: none;
    font-weight: bold;
    white-space: nowrap;
}
.btn-current-event:hover {
    background: #f0f0f0;
}
</style>
<?php endif; ?>

<div class="event-container">
    <?php if (!$active_event): ?>
        <!-- No Active Event -->
        <div class="no-event-message">
            <h2><?php echo t('no_active_event'); ?></h2>
            <p><?php echo t('no_event_description'); ?></p>
        </div>
    <?php else: ?>
        <!-- Event Header -->
        <div class="event-header">
            <h1 class="event-title"><?php echo htmlspecialchars($active_event['name']); ?></h1>
            
            <?php if ($config['homepage_message']): ?>
                <div class="homepage-message">
                    <?php echo nl2br(htmlspecialchars($config['homepage_message'])); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Day Tabs (if multi-day event) -->
        <?php if (count($event_days) > 1): ?>
            <div class="day-tabs">
                <?php foreach ($event_days as $index => $day): ?>
                    <a href="?day=<?php echo $day['day_number']; ?>" 
                       class="day-tab <?php echo $day['id'] == $selected_day_id ? 'active' : ''; ?>">
                        <?php echo t('day'); ?> <?php echo $day['day_number']; ?>
                        <span class="day-date"><?php echo format_date($day['date'], 'short'); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php elseif (count($event_days) == 1): ?>
            <div class="single-day-info">
                <strong><?php echo format_date($event_days[0]['date'], 'full'); ?></strong>
                <span class="day-time">
                    <?php echo $event_days[0]['start_time']; ?> - <?php echo $event_days[0]['end_time']; ?>
                </span>
            </div>
        <?php endif; ?>
        
        <!-- Tables and Games -->
        <?php if ($selected_day): ?>
            <div class="tables-container">
                <?php foreach ($tables_with_games as $table_data): ?>
                    <div class="table-section" data-table-id="<?php echo $table_data['table']['id']; ?>">
                        <h2 class="table-header">
                            <?php echo t('table'); ?> <?php echo $table_data['table']['table_number']; ?>
                        </h2>
                        
                        <ul class="games-list">
                            <?php if (empty($table_data['games']) && empty($table_data['polls'])): ?>
                                <li class="no-games">
                                    <?php echo t('no_games_yet'); ?>
                                </li>
                            <?php else: ?>
                                <?php 
                                // Number counter for games and polls on this table
                                $item_number = 1; 
                                ?>
                                
                                <?php foreach ($table_data['games'] as $game): ?>
                                    <?php
                                    // Include game template
                                    $players = $game['players'];
                                    $game_number = $item_number++;
                                    include $template_dir . '/game.php';
                                    ?>
                                <?php endforeach; ?>
                            
                            <!-- Display Polls -->
                            <?php if (!empty($table_data['polls'])): ?>
                                <?php foreach ($table_data['polls'] as $poll): ?>
                                    <?php
                                    $poll_options = $poll['options'];
                                    $poll_number = $item_number++;
                                    include $template_dir . '/poll.php';
                                    ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php endif; ?>
                        </ul>
                        
                        <!-- Add Game Button -->
                        <div class="add-game-section">
                            <button class="btn-add-game" data-table-id="<?php echo $table_data['table']['id']; ?>">
                                <?php echo t('add_game_to_table'); ?>
                            </button>
                            <button class="btn-create-poll" data-table-id="<?php echo $table_data['table']['id']; ?>">
                                <?php echo t('create_poll'); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Add New Table Button -->
                <?php if (count($tables) < $selected_day['max_tables']): ?>
                    <div class="add-table-section">
                        <button class="btn-add-table" data-day-id="<?php echo $selected_day_id; ?>">
                            <?php echo t('add_new_table'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Timeline -->
            <div class="timeline-container">
                <h2><?php echo t('timeline'); ?></h2>
                <div id="timeline" class="timeline">
                    <?php include $template_dir . '/timeline_php.php'; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// Configuration passed to JavaScript
const CONFIG = {
    allowLoggedIn: '<?php echo $config['allow_logged_in']; ?>',
    requireEmails: <?php echo $config['require_emails'] ? 'true' : 'false'; ?>,
    isLoggedIn: <?php echo $current_user ? 'true' : 'false'; ?>,
    userId: <?php echo $current_user ? $current_user['id'] : 'null'; ?>,
    isAdmin: <?php echo ($current_user && $current_user['is_admin']) ? 'true' : 'false'; ?>
};

$(document).ready(function() {
    
    // Add Game Button
    $('.btn-add-game').click(function() {
        const tableId = $(this).data('table-id');
        
        // Check login requirement
        if ((CONFIG.allowLoggedIn === 'required_games' || CONFIG.allowLoggedIn === 'required_all') && !CONFIG.isLoggedIn) {
            alert('<?php echo t('login_required_to_add_game'); ?>');
            window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
            return;
        }
        
        loadAddGameForm(tableId);
    });
    
    // Create Poll Button
    $('.btn-create-poll').click(function() {
        const tableId = $(this).data('table-id');
        
        // Check login requirement
        if ((CONFIG.allowLoggedIn === 'required_games' || CONFIG.allowLoggedIn === 'required_all') && !CONFIG.isLoggedIn) {
            alert('<?php echo t('login_required_to_add_game'); ?>');
            window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
            return;
        }
        
        loadCreatePollForm(tableId);
    });
    
    // Add Table Button
    $('.btn-add-table').click(function() {
        const dayId = $(this).data('day-id');
        
        // Check login requirement
        if ((CONFIG.allowLoggedIn === 'required_games' || CONFIG.allowLoggedIn === 'required_all') && !CONFIG.isLoggedIn) {
            alert('<?php echo t('login_required_to_add_table'); ?>');
            window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
            return;
        }
        
        $.post('ajax/add_table.php', { day_id: dayId }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.error || '<?php echo t('error_adding_table'); ?>');
            }
        });
    });
    
    // Join Game Button
    $(document).on('click', '.join-game-btn', function(e) {
        e.preventDefault();
        const gameId = $(this).data('game-id');
        
        // Check login requirement
        if (CONFIG.allowLoggedIn === 'required_all' && !CONFIG.isLoggedIn) {
            alert('<?php echo t('login_required_to_join'); ?>');
            window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
            return;
        }
        
        loadJoinGameForm(gameId, false);
    });
    
    // Join Reserve Button
    $(document).on('click', '.join-reserve-btn', function(e) {
        e.preventDefault();
        const gameId = $(this).data('game-id');
        
        // Check login requirement
        if (CONFIG.allowLoggedIn === 'required_all' && !CONFIG.isLoggedIn) {
            alert('<?php echo t('login_required_to_join'); ?>');
            window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
            return;
        }
        
        loadJoinGameForm(gameId, true);
    });
    
    // Resign Button
    $(document).on('click', '.resign-btn', function(e) {
        e.preventDefault();
        const playerId = $(this).data('player-id');
        const gameId = $(this).data('game-id');
        
        if (confirm('<?php echo t('confirm_resign'); ?>')) {
            resignFromGame(playerId, gameId);
        }
    });
    
    // Edit Game Button
    $(document).on('click', '.edit-game-btn', function(e) {
        e.preventDefault();
        const gameId = $(this).data('game-id');
        loadEditGameForm(gameId);
    });
    
    // Delete Game Button
    $(document).on('click', '.delete-game-btn', function(e) {
        e.preventDefault();
        const gameId = $(this).data('game-id');
        const deletionMode = '<?php echo $config['deletion_mode']; ?>';
        
        if (deletionMode === 'hard_only') {
            // Hard delete only
            if (confirm('<?php echo t('confirm_hard_delete_game'); ?>')) {
                deleteGame(gameId, 'hard');
            }
            
        } else if (deletionMode === 'allow_choice') {
            // User can choose
            const deleteChoice = confirm('<?php echo t('confirm_delete_game'); ?>\n\n<?php echo t('delete_choice_prompt'); ?>');
            
            if (deleteChoice) {
                // Soft delete
                deleteGame(gameId, 'soft');
            } else {
                // Ask about hard delete
                const hardDelete = confirm('<?php echo t('confirm_hard_delete'); ?>');
                if (hardDelete) {
                    deleteGame(gameId, 'hard');
                }
            }
            
        } else {
            // Soft delete only
            if (confirm('<?php echo t('confirm_delete_game'); ?>')) {
                deleteGame(gameId, 'soft');
            }
        }
    });
    
    // Restore Game Button
    $(document).on('click', '.restore-game-btn', function(e) {
        e.preventDefault();
        const gameId = $(this).data('game-id');
        loadRestoreGameForm(gameId);
    });
    
    // Fully Delete Button
    $(document).on('click', '.fully-delete-btn', function(e) {
        e.preventDefault();
        const gameId = $(this).data('game-id');
        
        if (confirm('<?php echo t('confirm_fully_delete'); ?>')) {
            fullyDeleteGame(gameId);
        }
    });
    
    // Add Comment Button
    $(document).on('click', '.add-comment-btn, .btn-add-comment', function(e) {
        e.preventDefault();
        const gameId = $(this).data('game-id');
        loadAddCommentForm(gameId);
    });
    
    // Mail Icon - Player
    $(document).on('click', '.mail-icon[data-player-id]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const playerId = $(this).data('player-id');
        loadPrivateMessageForm(playerId, null);
    });
    
    // Mail Icon - Game (all players)
    $(document).on('click', '.mail-icon[data-game-id]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const gameId = $(this).data('game-id');
        loadPrivateMessageForm(null, gameId);
    });
    
    // Timeline: scroll to game when clicked
    $(document).on('click', '.timeline-game', function() {
        const gameId = $(this).data('game-id');
        const gameElement = $('#game_' + gameId);
        if (gameElement.length) {
            $('html, body').animate({
                scrollTop: gameElement.offset().top - 100
            }, 500);
        }
    });
    
    // Timeline is server-side rendered (no JS initialization needed)
});

// Load Add Game Form
function loadAddGameForm(tableId) {
    $.get('ajax/add_game_form.php', { table_id: tableId }, function(html) {
        openModal(html);
    });
}

// Load Join Game Form
function loadJoinGameForm(gameId, isReserve) {
    $.get('ajax/join_game_form.php', { game_id: gameId, is_reserve: isReserve ? 1 : 0 }, function(html) {
        openModal(html);
    });
}

// Resign from Game
function resignFromGame(playerId, gameId) {
    $.ajax({
        url: 'ajax/resign_player.php',
        method: 'POST',
        data: { player_id: playerId, game_id: gameId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.error || '<?php echo t('error_occurred'); ?>');
            }
        },
        error: function(xhr) {
            console.error('Resign error:', xhr.responseText);
            alert('<?php echo t('error_occurred'); ?>');
        }
    });
}


</script>

<?php
// Include footer
include $template_dir . '/footer.php';
?>