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

// Prevent browser caching to ensure fresh data is always displayed
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Load translation system
require_once 'includes/translations.php';

// Load auth helper
require_once 'includes/auth.php';

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

// Get active event
$stmt = $db->query("SELECT * FROM events WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
$active_event = $stmt->fetch(PDO::FETCH_ASSOC);

// Get event days if event exists
$event_days = [];
if ($active_event) {
    $stmt = $db->prepare("SELECT * FROM event_days WHERE event_id = ? ORDER BY day_number ASC");
    $stmt->execute([$active_event['id']]);
    $event_days = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    
    $tables_with_games[] = [
        'table' => $table,
        'games' => $games
    ];
}

// Page title
$page_title = $active_event ? $active_event['name'] : t('no_active_event');

// Template directory
$template_dir = TEMPLATES_DIR . '/' . $config['active_template'];

// Include header
include $template_dir . '/header.php';
?>

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
                            <?php if (empty($table_data['games'])): ?>
                                <li class="no-games">
                                    <?php echo t('no_games_yet'); ?>
                                </li>
                            <?php else: ?>
                                <?php foreach ($table_data['games'] as $game): ?>
                                    <?php
                                    // Include game template
                                    $players = $game['players'];
                                    include $template_dir . '/game.php';
                                    ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                        
                        <!-- Add Game Button -->
                        <div class="add-game-section">
                            <button class="btn-add-game" data-table-id="<?php echo $table_data['table']['id']; ?>">
                                <?php echo t('add_game_to_table'); ?>
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
                <div id="timeline" class="timeline"></div>
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
        
        // Check if hard deletion is allowed
        <?php if ($config['allow_full_deletion']): ?>
        // Ask user if they want soft or hard delete
        const deleteChoice = confirm('<?php echo t('confirm_delete_game'); ?>\n\nClick OK for soft delete (game can be restored).\nClick Cancel, then you\'ll be asked about permanent deletion.');
        
        if (deleteChoice) {
            // Soft delete
            deleteGame(gameId, 'soft');
        } else {
            // Ask about hard delete
            const hardDelete = confirm('Do you want to PERMANENTLY delete this game?\n\nThis cannot be undone!');
            if (hardDelete) {
                fullyDeleteGame(gameId);
            }
        }
        <?php else: ?>
        // Only soft delete allowed
        if (confirm('<?php echo t('confirm_delete_game'); ?>')) {
            deleteGame(gameId, 'soft');
        }
        <?php endif; ?>
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
    
    // Initialize timeline
    <?php if ($selected_day && !empty($tables_with_games)): ?>
        initTimeline();
    <?php endif; ?>
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
    $.post('ajax/resign_player.php', { player_id: playerId, game_id: gameId }, function(response) {
        if (response.success) {
            location.reload();
        } else {
            alert(response.error || '<?php echo t('error_occurred'); ?>');
        }
    });
}

// Load Edit Game Form
function loadEditGameForm(gameId) {
    $.get('ajax/edit_game_form.php', { game_id: gameId }, function(html) {
        openModal(html);
    });
}

// Delete Game
function deleteGame(gameId, deletionType) {
    deletionType = deletionType || 'soft'; // Default to soft delete
    
    $.post('ajax/delete_game.php', { 
        game_id: gameId,
        deletion_type: deletionType
    }, function(response) {
        if (response.success) {
            // Force page reload with cache busting
            window.location.href = window.location.href.split('?')[0] + '?' + new Date().getTime();
        } else {
            alert(response.error || '<?php echo t('error_occurred'); ?>');
        }
    }, 'json').fail(function() {
        alert('<?php echo t('error_occurred'); ?>');
    });
}

// Load Restore Game Form
function loadRestoreGameForm(gameId) {
    $.get('ajax/restore_game_form.php', { game_id: gameId }, function(html) {
        openModal(html);
    });
}

// Fully Delete Game
function fullyDeleteGame(gameId) {
    $.post('ajax/fully_delete_game.php', { game_id: gameId }, function(response) {
        if (response.success) {
            // Force page reload with cache busting
            window.location.href = window.location.href.split('?')[0] + '?' + new Date().getTime();
        } else {
            alert(response.error || '<?php echo t('error_occurred'); ?>');
        }
    }, 'json').fail(function() {
        alert('<?php echo t('error_occurred'); ?>');
    });
}

// Load Add Comment Form
function loadAddCommentForm(gameId) {
    $.get('ajax/add_comment_form.php', { game_id: gameId }, function(html) {
        openModal(html);
    });
}

// Initialize Timeline
function initTimeline() {
    const timeline = $('#timeline');
    const startTime = '<?php echo $selected_day['start_time']; ?>';
    const endTime = '<?php echo $selected_day['end_time']; ?>';
    const extension = <?php echo $config['timeline_extension']; ?>;
    
    // Calculate timeline hours
    const start = parseTime(startTime);
    const end = parseTime(endTime);
    const endWithExtension = end + (extension * 60);
    
    // Build hour markers
    const startHour = Math.floor(start / 60);
    const endHour = Math.ceil(endWithExtension / 60);
    
    // Build timeline HTML
    let html = '<div class="timeline-container-inner">';
    
    // Add hour markers header
    html += '<div class="timeline-hours">';
    html += '<div class="timeline-table-label-spacer"></div>'; // Spacer for table labels
    html += '<div class="timeline-hours-bar">';
    for (let hour = startHour; hour <= endHour; hour++) {
        const hourMinutes = hour * 60;
        const position = ((hourMinutes - start) / (endWithExtension - start)) * 100;
        
        if (position >= 0 && position <= 100) {
            const displayHour = hour % 24;
            const hourStr = displayHour.toString().padStart(2, '0') + ':00';
            html += '<div class="timeline-hour-marker" style="left: ' + position + '%;">' + hourStr + '</div>';
        }
    }
    html += '</div>';
    html += '</div>';
    
    html += '<div class="timeline-grid">';
    
    // Add hour background stripes
    html += '<div class="timeline-hour-stripes">';
    for (let hour = startHour; hour < endHour; hour++) {
        const hourStart = hour * 60;
        const hourEnd = (hour + 1) * 60;
        const leftPos = Math.max(0, ((hourStart - start) / (endWithExtension - start)) * 100);
        const rightPos = Math.min(100, ((hourEnd - start) / (endWithExtension - start)) * 100);
        const width = rightPos - leftPos;
        
        const isEven = (hour - startHour) % 2 === 0;
        const className = isEven ? 'timeline-hour-stripe-even' : 'timeline-hour-stripe-odd';
        
        html += '<div class="' + className + '" style="left: ' + leftPos + '%; width: ' + width + '%;"></div>';
    }
    html += '</div>';
    
    // Add table rows
    <?php foreach ($tables_with_games as $index => $table_data): ?>
        html += '<div class="timeline-row">';
        html += '<div class="timeline-table-label"><?php echo t('table'); ?> <?php echo $table_data['table']['table_number']; ?></div>';
        html += '<div class="timeline-games">';
        
        <?php foreach ($table_data['games'] as $game): ?>
            <?php
            // Calculate end time for this game
            $start_timestamp = strtotime($game['start_time']);
            $end_timestamp = $start_timestamp + ($game['play_time'] * 60);
            $end_time_formatted = date('H:i', $end_timestamp);
            
            // Count active players (non-reserve)
            $active_players = count(array_filter($game['players'], function($p) { return $p['is_reserve'] == 0; }));
            ?>
            html += '<div class="timeline-game" data-game-id="<?php echo $game['id']; ?>" style="' + 
                'left: ' + (((parseTime('<?php echo $game['start_time']; ?>') - start) / (endWithExtension - start)) * 100) + '%; ' +
                'width: ' + ((<?php echo $game['play_time']; ?> / (endWithExtension - start)) * 100) + '%;' +
                '">';
            html += '<span class="timeline-game-name"><?php echo htmlspecialchars($game['name']); ?></span>';
            html += '<span class="timeline-game-players">(<?php echo t('players'); ?>: <?php echo $active_players; ?>/<?php echo $game['max_players']; ?>)</span>';
            html += '<span class="timeline-game-time"><?php echo $game['start_time']; ?> - <?php echo $end_time_formatted; ?></span>';
            html += '</div>';
        <?php endforeach; ?>
        
        html += '</div>';
        html += '</div>';
    <?php endforeach; ?>
    
    html += '</div>';
    html += '</div>';
    
    timeline.html(html);
}

// Parse time string to minutes since midnight
function parseTime(timeStr) {
    const parts = timeStr.split(':');
    return parseInt(parts[0]) * 60 + parseInt(parts[1]);
}
</script>

<?php
// Include footer
include $template_dir . '/footer.php';
?>