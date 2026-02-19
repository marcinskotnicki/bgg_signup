<?php
/**
 * Classic Template - Poll Display
 * 
 * Variables expected:
 * - $poll: Poll data array
 * - $poll_options: Array of poll options with vote counts
 * - $current_user: Current logged in user (or null)
 * - $config: Configuration array
 * - $poll_number: Poll number on table
 */

$is_closed = $poll['is_active'] == 0;

// Check if current user can edit/delete
$can_edit = false;
if ($current_user) {
    if ($current_user['is_admin']) {
        $can_edit = true;
    } elseif (isset($poll['created_by_user_id']) && $poll['created_by_user_id'] == $current_user['id']) {
        $can_edit = true;
    }
}
?>

<div class="poll-card <?php echo $is_closed ? 'poll-closed' : ''; ?>" data-poll-id="<?php echo $poll['id']; ?>">
    <!-- Poll Number Badge -->
    <div class="game-number"><?php echo $poll_number; ?></div>
    
    <div class="poll-header">
        <h3 class="poll-title">
            📊 <?php echo t('game_poll'); ?>
            <?php if ($is_closed): ?>
                <span class="poll-status">(<?php echo t('closed'); ?>)</span>
            <?php endif; ?>
        </h3>
        <div class="poll-creator">
            <?php echo t('created_by'); ?>: <?php echo htmlspecialchars($poll['creator_name']); ?>
        </div>
    </div>
    
    <?php if (!empty($poll['comment']) && $is_closed == false): ?>
        <div class="poll-comment">
            <?php echo nl2br(htmlspecialchars($poll['comment'])); ?>
        </div>
    <?php endif; ?>
    
    <div class="poll-options">
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
                        <span class="poll-option-name">
                            <?php if ($option['bgg_url']): ?>
                                <a href="<?php echo htmlspecialchars($option['bgg_url']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($option['game_name']); ?>
                                </a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($option['game_name']); ?>
                            <?php endif; ?>
                        </span>
                        <span class="poll-option-votes"><?php echo $vote_count; ?>/<?php echo $threshold; ?></span>
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
                    
                    <div class="poll-progress">
                        <div class="poll-progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    
                    <?php if (!$is_closed): ?>
                        <button class="btn-sm btn-vote" onclick="voteOption(<?php echo $option['id']; ?>, <?php echo $poll['id']; ?>)">
                            <?php echo t('vote_for_this'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if ($can_edit && !$is_closed): ?>
        <div class="poll-actions">
            <button class="btn-sm btn-edit" onclick="editPoll(<?php echo $poll['id']; ?>)">✏️ <?php echo t('edit'); ?></button>
            <button class="btn-sm btn-delete" onclick="deletePoll(<?php echo $poll['id']; ?>)">🗑️ <?php echo t('delete'); ?></button>
        </div>
    <?php endif; ?>
</div>

<script>
function voteOption(optionId, pollId) {
    $.get('ajax/vote_form.php', { option_id: optionId, poll_id: pollId }, function(html) {
        openModal(html);
    });
}

function editPoll(pollId) {
    $.get('ajax/edit_poll_form.php', { poll_id: pollId }, function(html) {
        openModal(html);
    });
}

function deletePoll(pollId) {
    if (!confirm('<?php echo t('confirm_delete_poll'); ?>')) {
        return;
    }
    
    $.post('ajax/delete_poll.php', { poll_id: pollId }, function(response) {
        if (response.success) {
            location.reload();
        } else {
            alert(response.error || 'Error deleting poll');
        }
    }, 'json');
}
</script>
