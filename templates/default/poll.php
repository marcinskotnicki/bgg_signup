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
?>

<div class="poll-container <?php echo $is_greyed ? 'poll-closed' : ''; ?>" data-poll-id="<?php echo $poll['id']; ?>">
    <div class="poll-header">
        <h3 class="poll-title">
            <?php echo t('game_poll'); ?>
            <?php if ($is_closed): ?>
                <span class="poll-status-closed">(<?php echo t('closed'); ?>)</span>
            <?php endif; ?>
        </h3>
        <div class="poll-creator">
            <?php echo t('created_by'); ?>: <?php echo htmlspecialchars($poll['creator_name']); ?>
        </div>
    </div>
    
    <div class="poll-options-list">
        <?php foreach ($poll_options as $option): ?>
            <?php
            $vote_count = $option['vote_count'];
            $threshold = $option['vote_threshold'];
            $percentage = $threshold > 0 ? min(($vote_count / $threshold) * 100, 100) : 0;
            $is_winner = isset($option['is_winner']) && $option['is_winner'];
            ?>
            
            <div class="poll-option <?php echo $is_winner ? 'poll-winner' : ''; ?>">
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
                
                <div class="poll-progress-bar">
                    <div class="poll-progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                </div>
                
                <?php if (!$is_closed): ?>
                    <button class="btn-vote" data-option-id="<?php echo $option['id']; ?>" data-poll-id="<?php echo $poll['id']; ?>">
                        <?php echo t('vote_for_this'); ?>
                    </button>
                <?php endif; ?>
                
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
}

.poll-option.poll-winner {
    background: #d5f4e6;
    border-left-color: #27ae60;
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
</style>

<script>
$(document).on('click', '.btn-vote', function() {
    const optionId = $(this).data('option-id');
    const pollId = $(this).data('poll-id');
    loadVoteForm(optionId, pollId);
});

function loadVoteForm(optionId, pollId) {
    $.get('../ajax/vote_form.php', { option_id: optionId, poll_id: pollId }, function(html) {
        openModal(html);
    });
}
</script>