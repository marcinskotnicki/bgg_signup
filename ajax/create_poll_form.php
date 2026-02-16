<?php
/**
 * AJAX Handler: Create Poll Form
 */

// Load configuration
$config = require_once '../config.php';

// Load translation system
require_once '../includes/translations.php';

// Load auth helper
require_once '../includes/auth.php';

// Load BGG API helper
require_once '../includes/bgg_api.php';

// Database connection
try {
    $db = new PDO('sqlite:../' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed');
}

// Get current user
$current_user = get_current_user($db);

// Get table ID
$table_id = isset($_GET['table_id']) ? intval($_GET['table_id']) : 0;

if (!$table_id) {
    die('Invalid table ID');
}

// Get table info and event times
$stmt = $db->prepare("SELECT t.*, ed.start_time, ed.end_time 
    FROM tables t 
    JOIN event_days ed ON t.event_day_id = ed.id 
    WHERE t.id = ?");
$stmt->execute([$table_id]);
$table = $stmt->fetch(PDO::FETCH_ASSOC);

// Get last game on this table to calculate default start time
$stmt = $db->prepare("SELECT * FROM games WHERE table_id = ? AND is_active = 1 ORDER BY start_time DESC LIMIT 1");
$stmt->execute([$table_id]);
$last_game = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate default start time
if ($last_game) {
    $last_start = strtotime($last_game['start_time']);
    $default_start_time = date('H:i', $last_start + ($last_game['play_time'] * 60));
} else {
    // No games yet, use event start time
    $default_start_time = $table['start_time'];
}

// Pre-fill user data if logged in
$default_name = $current_user ? $current_user['name'] : '';
$default_email = $current_user ? $current_user['email'] : '';
?>

<div class="create-poll-form">
    <h2><?php echo t('create_game_poll'); ?></h2>
    
    <div class="poll-info">
        <?php echo t('poll_info_text'); ?>
    </div>
    
    <!-- Step 1: Creator Info -->
    <div id="poll-creator-step" class="form-step">
        <h3><?php echo t('your_information'); ?></h3>
        
        <div class="form-group">
            <label><?php echo t('your_name'); ?>: <span class="required">*</span></label>
            <input type="text" id="creator_name" class="form-control" value="<?php echo htmlspecialchars($default_name); ?>" required>
        </div>
        
        <div class="form-group">
            <label><?php echo t('your_email'); ?>:<?php if ($config['require_emails']): ?> <span class="required">*</span><?php endif; ?></label>
            <input type="email" id="creator_email" class="form-control" value="<?php echo htmlspecialchars($default_email); ?>" <?php echo $config['require_emails'] ? 'required' : ''; ?>>
        </div>
        
        <div class="form-group">
            <label><?php echo t('poll_comment'); ?>:</label>
            <textarea id="poll_comment" class="form-control" rows="2" placeholder="<?php echo t('poll_comment_placeholder'); ?>"></textarea>
            <small><?php echo t('poll_comment_help'); ?></small>
        </div>
        
        <div class="form-group">
            <label><?php echo t('poll_start_time'); ?>: <span class="required">*</span></label>
            <input type="time" id="poll_start_time" class="form-control" 
                   value="<?php echo htmlspecialchars($default_start_time); ?>" 
                   min="<?php echo htmlspecialchars($table['start_time']); ?>"
                   max="<?php echo htmlspecialchars($table['end_time']); ?>"
                   required>
            <small><?php echo t('poll_start_time_help'); ?> (<?php echo htmlspecialchars($table['start_time']); ?> - <?php echo htmlspecialchars($table['end_time']); ?>)</small>
        </div>
    </div>
    
    <!-- Step 2: Poll Options -->
    <div id="poll-options-container" style="margin-top: 20px;">
        <!-- Poll options will be added here -->
    </div>
    
    <!-- Add option button - appears below all options -->
    <div style="margin-top: 15px;">
        <button type="button" id="add-poll-option-btn" class="btn btn-primary"><?php echo t('add_game_option'); ?></button>
    </div>
    
    <div id="poll-actions" style="display: none; margin-top: 20px;">
        <div class="form-actions">
            <button type="button" onclick="closeModal()" class="btn btn-secondary"><?php echo t('cancel'); ?></button>
            <button type="button" id="submit-poll" class="btn btn-primary" disabled><?php echo t('create_poll'); ?></button>
        </div>
    </div>
    
    <input type="hidden" id="table_id" value="<?php echo $table_id; ?>">
</div>

<!-- Modal for adding game option -->
<div id="add-option-modal" style="display: none;">
    <h3><?php echo t('add_game_to_poll'); ?></h3>
    
    <div class="search-section">
        <input type="text" id="option-search-input" placeholder="<?php echo t('search_game'); ?>" class="form-control">
        <button type="button" id="option-search-btn" class="btn btn-primary"><?php echo t('search_bgg'); ?></button>
        <button type="button" id="option-manual-btn" class="btn btn-secondary"><?php echo t('add_game_manual'); ?></button>
    </div>
    
    <div id="option-search-results" class="search-results" style="display: none;"></div>
    
    <div id="option-details-form" style="display: none;">
        <input type="hidden" id="option_bgg_id">
        <input type="hidden" id="option_bgg_url">
        <input type="hidden" id="option_thumbnail">
        
        <div class="form-group">
            <label><?php echo t('game_name'); ?>: <span class="required">*</span></label>
            <input type="text" id="option_game_name" class="form-control" required>
        </div>
        
        <div class="form-row">
            <div class="form-group form-col">
                <label><?php echo t('play_time'); ?> (<?php echo t('minutes'); ?>):</label>
                <input type="number" id="option_play_time" class="form-control" min="1" placeholder="60">
            </div>
            
            <div class="form-group form-col">
                <label><?php echo t('difficulty'); ?> (1-5):</label>
                <input type="number" id="option_difficulty" class="form-control" min="1" max="5" step="0.1" placeholder="2.5">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group form-col">
                <label><?php echo t('min_players'); ?>:</label>
                <input type="number" id="option_min_players" class="form-control" min="1" placeholder="2">
            </div>
            
            <div class="form-group form-col">
                <label><?php echo t('max_players'); ?>:</label>
                <input type="number" id="option_max_players" class="form-control" min="1" placeholder="4">
            </div>
        </div>
        
        <div class="form-group">
            <label><?php echo t('language'); ?>:</label>
            <select id="option_language" class="form-control">
                <option value="en">English (EN)</option>
                <option value="pl">Polski (PL)</option>
                <option value="de">Deutsch (DE)</option>
                <option value="fr">Français (FR)</option>
                <option value="language_independent"><?php echo t('language_independent'); ?></option>
            </select>
        </div>
        
        <div id="option-thumbnail-selector" class="form-group" style="display: none;">
            <label><?php echo t('select_thumbnail'); ?>:</label>
            <div class="thumbnail-grid">
                <?php
                $custom_thumbnails = get_custom_thumbnails();
                if (!empty($custom_thumbnails)):
                    foreach ($custom_thumbnails as $thumbnail):
                ?>
                    <div class="thumbnail-option" data-thumbnail="<?php echo htmlspecialchars($thumbnail); ?>">
                        <img src="<?php echo htmlspecialchars($thumbnail); ?>" alt="Thumbnail">
                    </div>
                <?php
                    endforeach;
                else:
                ?>
                    <p style="color: #666; font-size: 13px;"><?php echo t('no_thumbnails_uploaded'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="form-group">
            <label><?php echo t('vote_threshold'); ?>: <span class="required">*</span></label>
            <input type="number" id="option_threshold" class="form-control" min="1" value="3" required>
            <small><?php echo t('vote_threshold_help'); ?></small>
        </div>
        
        <div class="form-actions">
            <button type="button" id="cancel-option" class="btn btn-secondary"><?php echo t('cancel'); ?></button>
            <button type="button" id="save-option" class="btn btn-primary"><?php echo t('add_to_poll'); ?></button>
        </div>
    </div>
</div>

<style>
.create-poll-form {
    max-width: 700px;
}

.poll-info {
    background: #e3f2fd;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
    border-left: 4px solid #2196f3;
}

.form-step {
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-control {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: #3498db;
}

.required {
    color: #e74c3c;
}

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.form-col {
    flex: 1;
    margin-bottom: 0 !important;
}

.thumbnail-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.thumbnail-option {
    width: 80px;
    height: 80px;
    border: 2px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.2s;
}

.thumbnail-option:hover {
    border-color: #3498db;
    transform: scale(1.05);
}

.thumbnail-option.selected {
    border-color: #27ae60;
    border-width: 3px;
}

.thumbnail-option img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.poll-option-item {
    background: #f8f9fa;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 4px;
    border-left: 4px solid #3498db;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.poll-option-info {
    flex: 1;
}

.poll-option-thumbnail {
    width: 60px;
    height: 60px;
    margin-right: 15px;
    border-radius: 4px;
    overflow: hidden;
    flex-shrink: 0;
}

.poll-option-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.poll-option-details {
    color: #7f8c8d;
    font-size: 12px;
    margin-top: 4px;
}

.poll-option-name {
    font-weight: bold;
    color: #2c3e50;
}

.poll-option-threshold {
    color: #7f8c8d;
    font-size: 13px;
}

.poll-option-actions {
    display: flex;
    gap: 10px;
}

.btn-remove {
    background: #e74c3c;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}

.btn-remove:hover {
    background: #c0392b;
}

.btn-move {
    background: #95a5a6;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}

.btn-move:hover {
    background: #7f8c8d;
}

.search-section {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

#option-search-input {
    flex: 1;
}

.search-results {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f8f9fa;
    margin-bottom: 20px;
}

.search-result-item {
    padding: 15px;
    border-bottom: 1px solid #ddd;
    cursor: pointer;
    transition: background 0.2s;
}

.search-result-item:hover {
    background: #e9ecef;
}

.result-name {
    font-weight: bold;
    font-size: 16px;
    color: #2c3e50;
}

.result-year {
    color: #7f8c8d;
    font-size: 14px;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
    transition: background 0.3s;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background: #2980b9;
}

.btn-primary:disabled {
    background: #bdc3c7;
    cursor: not-allowed;
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

.btn-secondary:hover {
    background: #7f8c8d;
}

#add-option-modal {
    margin-top: 20px;
    padding: 20px;
    background: white;
    border: 2px solid #3498db;
    border-radius: 8px;
}
</style>

<script>
let pollOptions = [];
let currentOptionIndex = 0;

$(document).ready(function() {
    // Add poll option button
    $('#add-poll-option-btn').click(function() {
        $('#add-option-modal').show();
        $('#option-search-input').val('');
        $('#option-search-results').hide();
        $('#option-details-form').hide();
    });
    
    // Search BGG for option
    $('#option-search-btn').click(function() {
        const query = $('#option-search-input').val().trim();
        
        if (!query) {
            alert('<?php echo t('enter_game_name'); ?>');
            return;
        }
        
        $('#option-search-results').html('<div class="loading"><?php echo t('loading'); ?>...</div>').show();
        
        $.get('../ajax/search_bgg.php', { query: query }, function(response) {
            if (response.success) {
                displayOptionSearchResults(response.results);
            } else {
                $('#option-search-results').html('<div class="loading">' + response.error + '</div>');
            }
        });
    });
    
    // Manual option
    $('#option-manual-btn').click(function() {
        $('#option_bgg_id').val('');
        $('#option_bgg_url').val('');
        $('#option_thumbnail').val('');
        $('#option_game_name').val('').prop('readonly', false);
        $('#option-search-results').hide();
        $('#option-details-form').show();
        $('#option-thumbnail-selector').show(); // Show thumbnail selector for manual entries
    });
    
    // Thumbnail selection
    $(document).on('click', '.thumbnail-option', function() {
        $('.thumbnail-option').removeClass('selected');
        $(this).addClass('selected');
        const thumbnailPath = $(this).data('thumbnail');
        $('#option_thumbnail').val(thumbnailPath);
    });
    
    // Display search results
    function displayOptionSearchResults(results) {
        if (results.length === 0) {
            $('#option-search-results').html('<div class="loading"><?php echo t('no_results_found'); ?></div>');
            return;
        }
        
        let html = '';
        results.forEach(function(game) {
            html += '<div class="search-result-item" data-game-id="' + game.id + '">';
            html += '<div class="result-name">' + game.name + '</div>';
            if (game.year) {
                html += '<div class="result-year">(' + game.year + ')</div>';
            }
            html += '</div>';
        });
        
        $('#option-search-results').html(html);
    }
    
    // Select game from search
    $(document).on('click', '.search-result-item', function() {
        const gameId = $(this).data('game-id');
        
        $.get('../ajax/get_bgg_game.php', { game_id: gameId }, function(response) {
            if (response.success) {
                const game = response.game;
                $('#option_bgg_id').val(game.id);
                $('#option_bgg_url').val(game.url);
                $('#option_thumbnail').val(game.thumbnail);
                $('#option_game_name').val(game.name).prop('readonly', true);
                
                // Populate additional fields from BGG data
                if (game.play_time) $('#option_play_time').val(game.play_time);
                if (game.min_players) $('#option_min_players').val(game.min_players);
                if (game.max_players) $('#option_max_players').val(game.max_players);
                if (game.difficulty) $('#option_difficulty').val(game.difficulty);
                
                $('#option-search-results').hide();
                $('#option-details-form').show();
            }
        });
    });
    
    // Save option
    $('#save-option').click(function() {
        const gameName = $('#option_game_name').val().trim();
        const threshold = parseInt($('#option_threshold').val());
        
        if (!gameName || !threshold || threshold < 1) {
            alert('<?php echo t('fill_all_required_fields'); ?>');
            return;
        }
        
        const option = {
            bgg_id: $('#option_bgg_id').val(),
            bgg_url: $('#option_bgg_url').val(),
            game_name: gameName,
            thumbnail: $('#option_thumbnail').val(),
            play_time: $('#option_play_time').val() || null,
            min_players: $('#option_min_players').val() || null,
            max_players: $('#option_max_players').val() || null,
            difficulty: $('#option_difficulty').val() || null,
            language: $('#option_language').val() || 'en',
            vote_threshold: threshold,
            display_order: pollOptions.length
        };
        
        pollOptions.push(option);
        renderPollOptions();
        
        // Reset and hide modal
        $('#add-option-modal').hide();
        $('#option_bgg_id').val('');
        $('#option_bgg_url').val('');
        $('#option_thumbnail').val('');
        $('#option_game_name').val('');
        $('#option_play_time').val('');
        $('#option_min_players').val('');
        $('#option_max_players').val('');
        $('#option_difficulty').val('');
        $('#option_language').val('en');
        $('#option_threshold').val('3');
        $('#option-thumbnail-selector').hide();
        $('.thumbnail-option').removeClass('selected');
        
        validatePoll();
    });
    
    // Cancel option
    $('#cancel-option').click(function() {
        $('#add-option-modal').hide();
    });
    
    // Render poll options
    function renderPollOptions() {
        let html = '';
        
        if (pollOptions.length > 0) {
            html += '<h3><?php echo t('poll_options'); ?>:</h3>';
            pollOptions.forEach(function(option, index) {
                html += '<div class="poll-option-item">';
                
                // Add thumbnail if available
                if (option.thumbnail) {
                    html += '<div class="poll-option-thumbnail">';
                    html += '<img src="' + option.thumbnail + '" alt="' + option.game_name + '">';
                    html += '</div>';
                }
                
                html += '<div class="poll-option-info">';
                html += '<div class="poll-option-name">' + (index + 1) + '. ' + option.game_name + '</div>';
                
                // Show additional details if available
                let details = [];
                if (option.play_time) details.push(option.play_time + ' <?php echo t('minutes'); ?>');
                if (option.min_players && option.max_players) {
                    details.push(option.min_players + '-' + option.max_players + ' <?php echo t('players'); ?>');
                }
                if (option.difficulty) details.push('⚙️ ' + option.difficulty + '/5');
                
                if (details.length > 0) {
                    html += '<div class="poll-option-details">' + details.join(' • ') + '</div>';
                }
                
                html += '<div class="poll-option-threshold"><?php echo t('needs'); ?> ' + option.vote_threshold + ' <?php echo t('votes'); ?></div>';
                html += '</div>';
                html += '<div class="poll-option-actions">';
                if (index > 0) {
                    html += '<button class="btn-move" onclick="moveOption(' + index + ', -1)">↑</button>';
                }
                if (index < pollOptions.length - 1) {
                    html += '<button class="btn-move" onclick="moveOption(' + index + ', 1)">↓</button>';
                }
                html += '<button class="btn-remove" onclick="removeOption(' + index + ')"><?php echo t('remove'); ?></button>';
                html += '</div>';
                html += '</div>';
            });
            
            $('#poll-actions').show();
        } else {
            $('#poll-actions').hide();
        }
        
        $('#poll-options-container').html(html);
    }
    
    // Remove option
    window.removeOption = function(index) {
        pollOptions.splice(index, 1);
        // Update display_order
        pollOptions.forEach(function(opt, idx) {
            opt.display_order = idx;
        });
        renderPollOptions();
        validatePoll();
    };
    
    // Move option
    window.moveOption = function(index, direction) {
        const newIndex = index + direction;
        if (newIndex < 0 || newIndex >= pollOptions.length) return;
        
        [pollOptions[index], pollOptions[newIndex]] = [pollOptions[newIndex], pollOptions[index]];
        
        // Update display_order
        pollOptions.forEach(function(opt, idx) {
            opt.display_order = idx;
        });
        
        renderPollOptions();
    };
    
    // Validate poll
    function validatePoll() {
        const creatorName = $('#creator_name').val().trim();
        const creatorEmail = $('#creator_email').val().trim();
        const requireEmail = <?php echo $config['require_emails'] ? 'true' : 'false'; ?>;
        
        let isValid = creatorName && pollOptions.length >= 2;
        
        if (requireEmail) {
            isValid = isValid && creatorEmail;
        }
        
        $('#submit-poll').prop('disabled', !isValid);
    }
    
    // Monitor creator fields
    $('#creator_name, #creator_email').on('input', validatePoll);
    
    // Submit poll
    $('#submit-poll').click(function() {
        const data = {
            table_id: $('#table_id').val(),
            creator_name: $('#creator_name').val().trim(),
            creator_email: $('#creator_email').val().trim(),
            comment: $('#poll_comment').val().trim(),
            start_time: $('#poll_start_time').val(),
            options: pollOptions
        };
        
        $('#submit-poll').prop('disabled', true).text('<?php echo t('saving'); ?>...');
        
        $.post('../ajax/create_poll_submit.php', { poll_data: JSON.stringify(data) }, function(response) {
            if (response.success) {
                closeModal();
                location.reload();
            } else {
                alert(response.error || '<?php echo t('error_occurred'); ?>');
                $('#submit-poll').prop('disabled', false).text('<?php echo t('create_poll'); ?>');
            }
        });
    });
});
</script>