<?php
/**
 * Poll Display Template
 * 
 * Variables expected:
 * - $poll: Poll data array
 * - $poll_options: Array of poll options with vote counts
 * - $current_user: Current logged in user (or null)
 * - $config: Configuration array
 */

$is_closed = $poll['is_active'] == 0;
$is_greyed = $is_closed && $config['closed_poll_action'] === 'grey';

// Check if current user can edit/delete this poll
$can_edit = false;
if ($current_user) {
    if ($current_user['is_admin']) {
        $can_edit = true;
    } elseif (isset($poll['created_by_user_id']) && $poll['created_by_user_id'] == $current_user['id']) {
        $can_edit = true;
    }
}
?>

<div class="poll-container <?php echo $is_greyed ? 'poll-closed' : ''; ?>" data-poll-id="<?php echo $poll['id']; ?>">
    <!-- Poll Number -->
    <div class="game-number-badge"><?php echo $poll_number; ?></div>
    
    <div class="poll-header">
        <div class="poll-header-top">
            <div class="poll-title-section">
                <h3 class="poll-title">
                    <?php echo t('game_poll'); ?>
                    <?php if ($is_closed): ?>
                        <span class="poll-status-closed">(<?php echo t('closed'); ?>)</span>
                    <?php endif; ?>
                </h3>
                <div class="poll-creator">
                    <?php echo t('created_by'); ?>: <?php echo htmlspecialchars($poll['creator_name']); ?>
                    <?php if (!empty($poll['start_time'])): ?>
                        <br><?php echo t('poll_start_time'); ?>: <strong><?php echo htmlspecialchars($poll['start_time']); ?></strong>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($can_edit && !$is_closed): ?>
                <div class="poll-actions">
                    <button class="btn-edit-poll" data-poll-id="<?php echo $poll['id']; ?>">
                        <?php echo t('edit_poll'); ?>
                    </button>
                    <button class="btn-delete-poll" data-poll-id="<?php echo $poll['id']; ?>">
                        <?php echo t('delete_poll'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($poll['comment']) && $is_closed == false): ?>
        <div class="poll-comment">
            <?php echo nl2br(htmlspecialchars($poll['comment'])); ?>
        </div>
    <?php endif; ?>
    
    <div class="poll-options-list">
        <?php foreach ($poll_options as $option): ?>
            <?php
            $vote_count = $option['vote_count'];
            $threshold = $option['vote_threshold'];
            $percentage = $threshold > 0 ? min(($vote_count / $threshold) * 100, 100) : 0;
            $is_winner = isset($option['is_winner']) && $option['is_winner'];
            ?>
            
            <div class="poll-option <?php echo $is_winner ? 'poll-winner' : ''; ?>">
                <?php if ($option['thumbnail']): ?>
                    <div class="poll-option-thumbnail">
                        <img src="<?php echo htmlspecialchars($option['thumbnail']); ?>" alt="<?php echo htmlspecialchars($option['game_name']); ?>">
                    </div>
                <?php endif; ?>
                
                <div class="poll-option-content">
                    <div class="poll-option-header">
                        <div class="poll-option-name">
                            <?php if ($option['bgg_url']): ?>
                                <a href="<?php echo htmlspecialchars($option['bgg_url']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($option['game_name']); ?>
                                </a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($option['game_name']); ?>
                            <?php endif; ?>
                        </div>
                        <div class="poll-option-votes">
                            <?php echo $vote_count; ?> / <?php echo $threshold; ?> <?php echo t('votes'); ?>
                        </div>
                    </div>
                    
                    <?php 
                    // Show game details if available
                    $details = [];
                    if ($option['play_time']) $details[] = $option['play_time'] . ' ' . t('minutes');
                    if ($option['min_players'] && $option['max_players']) {
                        $details[] = $option['min_players'] . '-' . $option['max_players'] . ' ' . t('players');
                    }
                    if ($option['difficulty']) $details[] = '⚙️ ' . number_format($option['difficulty'], 1) . '/5';
                    
                    if (!empty($details)): 
                    ?>
                        <div class="poll-option-details">
                            <?php echo implode(' • ', $details); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="poll-progress-bar">
                        <div class="poll-progress-fill" style="width: <?php echo $percentage; %>%"></div>
                    </div>
                    
                    <?php if (!$is_closed): ?>
                        <button class="btn-vote" data-option-id="<?php echo $option['id']; ?>" data-poll-id="<?php echo $poll['id']; ?>">
                            <?php echo t('vote_for_this'); ?>
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if ($is_winner): ?>
                    <div class="winner-badge"><?php echo t('winner'); ?>!</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.poll-container {
    background: #fff;
    border: 2px solid #f39c12;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.poll-container.poll-closed {
    opacity: 0.6;
    background: #f5f5f5;
}

.poll-header {
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #ecf0f1;
}

.poll-header-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
}

.poll-title-section {
    flex: 1;
}

.poll-title {
    margin: 0 0 5px 0;
    color: #f39c12;
    font-size: 20px;
}

.poll-status-closed {
    color: #95a5a6;
    font-size: 16px;
}

.poll-creator {
    color: #7f8c8d;
    font-size: 14px;
}

.poll-comment {
    background: #e8f4f8;
    border-left: 4px solid #3498db;
    padding: 12px 15px;
    margin-bottom: 20px;
    border-radius: 4px;
    font-size: 14px;
    line-height: 1.5;
    color: #2c3e50;
}

.poll-options-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.poll-option {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    border-left: 4px solid #3498db;
    position: relative;
    display: flex;
    gap: 15px;
}

.poll-option.poll-winner {
    background: #d5f4e6;
    border-left-color: #27ae60;
}

.poll-option-thumbnail {
    width: 80px;
    height: 80px;
    flex-shrink: 0;
    border-radius: 4px;
    overflow: hidden;
}

.poll-option-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.poll-option-content {
    flex: 1;
}

.poll-option-details {
    font-size: 13px;
    color: #7f8c8d;
    margin-bottom: 8px;
}

.poll-option-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.poll-option-name {
    font-weight: bold;
    font-size: 16px;
    color: #2c3e50;
}

.poll-option-name a {
    color: #2c3e50;
}

.poll-option-votes {
    font-size: 14px;
    color: #7f8c8d;
    font-weight: bold;
}

.poll-progress-bar {
    width: 100%;
    height: 20px;
    background: #ecf0f1;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 10px;
}

.poll-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #3498db, #2ecc71);
    transition: width 0.3s ease;
}

.btn-vote {
    background: #f39c12;
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    transition: background 0.3s;
}

.btn-vote:hover {
    background: #e67e22;
}

.winner-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #27ae60;
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 12px;
}

.poll-actions {
    display: flex;
    gap: 10px;
    flex-shrink: 0;
}

.btn-edit-poll,
.btn-delete-poll {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    font-size: 13px;
    transition: background 0.3s;
}

.btn-edit-poll {
    background: #3498db;
    color: white;
}

.btn-edit-poll:hover {
    background: #2980b9;
}

.btn-delete-poll {
    background: #e74c3c;
    color: white;
}

.btn-delete-poll:hover {
    background: #c0392b;
}
</style>

<script>
$(document).on('click', '.btn-vote', function() {
    const optionId = $(this).data('option-id');
    const pollId = $(this).data('poll-id');
    loadVoteForm(optionId, pollId);
});

$(document).on('click', '.btn-edit-poll', function() {
    const pollId = $(this).data('poll-id');
    loadEditPollForm(pollId);
});

$(document).on('click', '.btn-delete-poll', function() {
    const pollId = $(this).data('poll-id');
    deletePoll(pollId);
});

function loadVoteForm(optionId, pollId) {
    $.get('../ajax/vote_form.php', { option_id: optionId, poll_id: pollId }, function(html) {
        openModal(html);
    });
}

function loadEditPollForm(pollId) {
    $.get('../ajax/edit_poll_form.php', { poll_id: pollId }, function(html) {
        openModal(html);
    });
}

function deletePoll(pollId) {
    if (!confirm('<?php echo t('confirm_delete_poll'); ?>')) {
        return;
    }
    
    $.post('../ajax/delete_poll.php', { poll_id: pollId }, function(response) {
        if (response.success) {
            alert(response.message || '<?php echo t('poll_deleted_success'); ?>');
            location.reload();
        } else {
            alert(response.error || '<?php echo t('error_occurred'); ?>');
        }
    });
}
</script>