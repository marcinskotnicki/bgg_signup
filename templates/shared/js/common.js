/**
 * Shared JavaScript Functions for BGG Signup System
 * 
 * Consolidates functions that were duplicated across:
 * - index.php
 * - templates/default/footer.php
 * - templates/classic/footer.php
 * 
 * Load this file ONCE per page to avoid redeclaration errors
 */

// Modal functions
function openModal(content) {
    $('#modal-body').html(content);
    $('#modal-overlay').fadeIn(200);
}

function closeModal() {
    $('#modal-overlay').fadeOut(200);
    setTimeout(function() {
        $('#modal-body').html('');
    }, 200);
}

// Game actions
function joinGame(gameId, isReserve) {
    $.get('ajax/join_game_form.php', { 
        game_id: gameId, 
        is_reserve: isReserve ? 1 : 0 
    }, function(html) {
        openModal(html);
    });
}

function editGame(gameId) {
    $.get('ajax/edit_game_form.php', { game_id: gameId }, function(html) {
        openModal(html);
    });
}

function deleteGame(gameId) {
    // Load delete choice dialog
    $.get('ajax/delete_game_choice.php', { game_id: gameId }, function(html) {
        openModal(html);
    });
}

function restoreGame(gameId) {
    $.get('ajax/restore_game_form.php', { game_id: gameId }, function(html) {
        openModal(html);
    });
}

function fullyDeleteGame(gameId) {
    if (confirm('Are you sure you want to permanently delete this game? This cannot be undone!')) {
        $.post('ajax/fully_delete_game.php', { game_id: gameId }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.message || 'Error occurred');
            }
        }, 'json');
    }
}

function resignFromGame(gameId, playerId) {
    if (confirm('Are you sure you want to resign from this game?')) {
        $.post('ajax/resign_player.php', { 
            game_id: gameId, 
            player_id: playerId 
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.message || 'Error occurred');
            }
        }, 'json');
    }
}

// Comment functions
function loadComments(gameId) {
    $.get('ajax/add_comment_form.php', { game_id: gameId }, function(html) {
        openModal(html);
    });
}

function addComment(gameId) {
    loadComments(gameId);
}

// Table/Event actions
function addTable(eventDayId) {
    $.post('ajax/add_table.php', { event_day_id: eventDayId }, function(response) {
        if (response.success) {
            location.reload();
        } else {
            alert(response.message || 'Error adding table');
        }
    }, 'json');
}

function addGameToTable(tableId) {
    $.get('ajax/add_game_form.php', { table_id: tableId }, function(html) {
        openModal(html);
    });
}

// Poll functions
function createPoll(tableId) {
    $.get('ajax/create_poll_form.php', { table_id: tableId }, function(html) {
        openModal(html);
    });
}

function loadVoteForm(optionId, pollId) {
    $.get('ajax/vote_form.php', { 
        option_id: optionId, 
        poll_id: pollId 
    }, function(html) {
        openModal(html);
    });
}

function editPoll(pollId) {
    $.get('ajax/edit_poll_form.php', { poll_id: pollId }, function(html) {
        openModal(html);
    });
}

function deletePoll(pollId) {
    if (confirm('Are you sure you want to delete this poll?')) {
        $.post('ajax/delete_poll.php', { poll_id: pollId }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.message || 'Error deleting poll');
            }
        }, 'json');
    }
}

// Private message function
function loadPrivateMessageForm(playerId, gameId) {
    var params = { player_id: playerId };
    if (gameId) {
        params.game_id = gameId;
    }
    $.get('ajax/private_message_form.php', params, function(html) {
        openModal(html);
    });
}

// Utility functions
function parseTime(timeStr) {
    var parts = timeStr.split(':');
    return parseInt(parts[0]) * 60 + parseInt(parts[1]);
}

// jQuery ready
$(document).ready(function() {
    // Close modal when clicking outside or on close button
    $('.modal-close').click(closeModal);
    
    $('#modal-overlay').click(function(e) {
        if (e.target.id === 'modal-overlay') {
            closeModal();
        }
    });
    
    // Prevent modal content clicks from closing modal
    $(document).on('click', '.modal-content', function(e) {
        e.stopPropagation();
    });
    
    // ESC key to close modal
    $(document).keyup(function(e) {
        if (e.key === "Escape") {
            closeModal();
        }
    });
});
