<?php
/**
 * AJAX Handler: Edit Poll Form
 */

// Load configuration
$config = require_once '../config.php';

// Load auth helper
require_once '../includes/auth.php';

// Load translations
require_once '../includes/translations.php';

// Database connection
try {
    $db = new PDO('sqlite:../' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed");
}

// Get current user
$current_user = get_current_user($db);

// Get poll ID
$poll_id = isset($_GET['poll_id']) ? intval($_GET['poll_id']) : 0;

if (!$poll_id) {
    die("Poll not found");
}

// Get poll details
$stmt = $db->prepare("SELECT p.*, t.table_number, ed.day_number, e.name as event_name
    FROM polls p
    JOIN tables t ON p.table_id = t.id
    JOIN event_days ed ON t.event_day_id = ed.id
    JOIN events e ON ed.event_id = e.id
    WHERE p.id = ?");
$stmt->execute([$poll_id]);
$poll = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$poll) {
    die("Poll not found");
}

// Check permission - only creator or admin can edit
$can_edit = false;
if ($current_user) {
    if ($current_user['is_admin']) {
        $can_edit = true;
    } elseif ($poll['created_by_user_id'] && $poll['created_by_user_id'] == $current_user['id']) {
        $can_edit = true;
    }
}

if (!$can_edit) {
    die("You don't have permission to edit this poll");
}

// Get poll options with vote counts
$stmt = $db->prepare("SELECT po.*, 
    (SELECT COUNT(*) FROM poll_votes pv WHERE pv.poll_option_id = po.id) as vote_count
    FROM poll_options po
    WHERE po.poll_id = ?
    ORDER BY po.display_order");
$stmt->execute([$poll_id]);
$poll_options = $stmt->fetchAll(PDO::FETCH_ASSOC);

$default_name = $current_user ? $current_user['name'] : $poll['creator_name'];
$default_email = $current_user ? $current_user['email'] : $poll['creator_email'];
?>

<div class="edit-poll-form">
    <h2><?php echo t('edit_poll'); ?></h2>
    
    <div class="poll-info">
        <strong><?php echo t('table'); ?> <?php echo $poll['table_number']; ?></strong> - 
        <?php echo htmlspecialchars($poll['event_name']); ?> (<?php echo t('day'); ?> <?php echo $poll['day_number']; ?>)
    </div>
    
    <!-- Poll Settings -->
    <div id="poll-settings" class="form-step">
        <h3><?php echo t('poll_settings'); ?></h3>
        
        <div class="form-group">
            <label><?php echo t('poll_start_time'); ?>: <span class="required">*</span></label>
            <input type="time" id="edit_poll_start_time" class="form-control" value="<?php echo htmlspecialchars($poll['start_time']); ?>" required>
            <small><?php echo t('poll_start_time_help'); ?></small>
        </div>
    </div>
    
    <!-- Current Poll Options -->
    <div id="edit-poll-options-container" style="margin-top: 20px;">
        <h3><?php echo t('poll_options'); ?>:</h3>
        <div id="current-options-list"></div>
    </div>
    
    <!-- Add New Option Button -->
    <div style="margin-top: 15px;">
        <button type="button" id="add-new-poll-option-btn" class="btn btn-primary"><?php echo t('add_game_option'); ?></button>
    </div>
    
    <!-- Form Actions -->
    <div id="edit-poll-actions" style="margin-top: 20px;">
        <div class="form-actions">
            <button type="button" onclick="closeModal()" class="btn btn-secondary"><?php echo t('cancel'); ?></button>
            <button type="button" id="update-poll" class="btn btn-primary"><?php echo t('save_changes'); ?></button>
        </div>
    </div>
    
    <input type="hidden" id="edit_poll_id" value="<?php echo $poll_id; ?>">
    <input type="hidden" id="edit_table_id" value="<?php echo $poll['table_id']; ?>">
</div>

<!-- Modal for adding game option (reuse from create) -->
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


<script>
$(document).ready(function() {
    // Store poll options
    let editPollOptions = <?php echo json_encode($poll_options); ?>;
    let nextEditOptionId = editPollOptions.length;
    
    // Display current options
    function displayEditOptions() {
        let html = '';
        
        editPollOptions.forEach(function(option, index) {
            const canDelete = (option.vote_count == 0);
            
            html += '<div class="poll-option-edit">';
            html += '<div class="poll-option-header">';
            html += '<div class="poll-option-name">' + option.game_name + '</div>';
            html += '<div class="poll-option-votes">' + option.vote_count + ' <?php echo t('votes'); ?></div>';
            html += '</div>';
            html += '<div class="poll-option-threshold"><?php echo t('needs'); ?> ' + option.vote_threshold + ' <?php echo t('votes'); ?></div>';
            
            if (option.id) {
                // Existing option from database
                html += '<div class="poll-option-actions">';
                if (canDelete) {
                    html += '<button class="btn-remove-option" onclick="removeEditOption(' + index + ', ' + option.id + ')"><?php echo t('remove'); ?></button>';
                } else {
                    html += '<button class="btn-remove-option" disabled title="<?php echo t('cannot_remove_voted_option'); ?>"><?php echo t('remove'); ?> (<?php echo t('has_votes'); ?>)</button>';
                }
                html += '</div>';
            } else {
                // New option not yet saved
                html += '<div class="poll-option-actions">';
                html += '<button class="btn-remove-option" onclick="removeEditOption(' + index + ')"><?php echo t('remove'); ?></button>';
                html += '</div>';
            }
            
            html += '</div>';
        });
        
        $('#current-options-list').html(html);
    }
    
    // Remove option
    window.removeEditOption = function(index, optionId) {
        if (optionId) {
            if (!confirm('<?php echo t('confirm_remove_option'); ?>')) {
                return;
            }
        }
        
        editPollOptions.splice(index, 1);
        displayEditOptions();
    };
    
    // Initial display
    displayEditOptions();
    
    // Add new option button
    $('#add-new-poll-option-btn').click(function() {
        openModal($('#add-option-modal').html());
        initAddOptionModal();
    });
    
    // Initialize add option modal (reuse logic from create_poll_form.php)
    function initAddOptionModal() {
        // BGG Search
        $('#option-search-btn').click(function() {
            const query = $('#option-search-input').val().trim();
            if (!query) return;
            
            $(this).prop('disabled', true).text('<?php echo t('loading'); ?>...');
            
            $.get('../ajax/search_bgg.php', { q: query }, function(results) {
                $('#option-search-btn').prop('disabled', false).text('<?php echo t('search_bgg'); ?>');
                
                if (results && results.length > 0) {
                    let html = '<div class="search-results-list">';
                    results.forEach(function(game) {
                        html += '<div class="search-result-item" data-bgg-id="' + game.id + '">';
                        if (game.thumbnail) {
                            html += '<img src="' + game.thumbnail + '" class="search-result-thumb">';
                        }
                        html += '<div class="search-result-info">';
                        html += '<div class="search-result-name">' + game.name + ' (' + game.year + ')</div>';
                        html += '<div class="search-result-players">' + game.minplayers + '-' + game.maxplayers + ' <?php echo t('players'); ?>, ' + game.playingtime + ' <?php echo t('minutes'); ?></div>';
                        html += '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                    
                    $('#option-search-results').html(html).show();
                    
                    $('.search-result-item').click(function() {
                        const bggId = $(this).data('bgg-id');
                        selectBGGGame(bggId);
                    });
                } else {
                    $('#option-search-results').html('<p><?php echo t('no_results_found'); ?></p>').show();
                }
            });
        });
        
        // Manual entry
        $('#option-manual-btn').click(function() {
            $('#option-search-results').hide();
            $('#option-details-form').show();
            $('#option_bgg_id').val('');
            $('#option_bgg_url').val('');
            $('#option_thumbnail').val('');
            $('#option_game_name').val('').focus();
        });
        
        function selectBGGGame(bggId) {
            $.get('../ajax/get_bgg_game.php', { id: bggId }, function(game) {
                $('#option_bgg_id').val(game.id);
                $('#option_bgg_url').val(game.url);
                $('#option_thumbnail').val(game.thumbnail || '');
                $('#option_game_name').val(game.name);
                
                $('#option-search-results').hide();
                $('#option-details-form').show();
            });
        }
        
        // Cancel option
        $('#cancel-option').click(function() {
            closeModal();
        });
        
        // Save option
        $('#save-option').click(function() {
            const option = {
                game_name: $('#option_game_name').val().trim(),
                vote_threshold: parseInt($('#option_threshold').val()),
                bgg_id: $('#option_bgg_id').val() || null,
                bgg_url: $('#option_bgg_url').val() || null,
                thumbnail: $('#option_thumbnail').val() || null,
                display_order: nextEditOptionId++,
                vote_count: 0  // New options have no votes
            };
            
            if (!option.game_name || !option.vote_threshold) {
                alert('<?php echo t('all_fields_required'); ?>');
                return;
            }
            
            editPollOptions.push(option);
            displayEditOptions();
            closeModal();
        });
    }
    
    // Update poll
    $('#update-poll').click(function() {
        const data = {
            poll_id: $('#edit_poll_id').val(),
            start_time: $('#edit_poll_start_time').val(),
            options: editPollOptions
        };
        
        if (!data.start_time) {
            alert('<?php echo t('poll_start_time'); ?> <?php echo t('field_required'); ?>');
            return;
        }
        
        $('#update-poll').prop('disabled', true).text('<?php echo t('saving'); ?>...');
        
        $.post('../ajax/edit_poll_submit.php', { poll_data: JSON.stringify(data) }, function(response) {
            if (response.success) {
                closeModal();
                location.reload();
            } else {
                alert(response.error || '<?php echo t('error_occurred'); ?>');
                $('#update-poll').prop('disabled', false).text('<?php echo t('save_changes'); ?>');
            }
        });
    });
});
</script>
