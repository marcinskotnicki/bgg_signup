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
                    ?>
                    
                    <?php if (!empty($details)): ?>
                        <div class="poll-option-details">
                            <?php echo implode(' • ', $details); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="poll-progress-bar">
                        <div class="poll-progress-fill" style="width: <?php echo $percentage . '%'; ?>"></div>
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



function loadEditPollForm(pollId) {
    $.get('../ajax/edit_poll_form.php', { poll_id: pollId }, function(html) {
        openModal(html);
    });
}


</script>