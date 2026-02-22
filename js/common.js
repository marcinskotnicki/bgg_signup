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

// Helper function to reload page and scroll to a specific game
function reloadAndScrollToGame(gameId) {
    if (gameId) {
        window.location.href = window.location.pathname + '#game-' + gameId;
        location.reload();
    } else {
        location.reload();
    }
}

// On page load, scroll to game if hash is present
$(document).ready(function() {
    if (window.location.hash && window.location.hash.startsWith('#game-')) {
        const gameId = window.location.hash.replace('#game-', '');
        const gameElement = $('.gameitem[data-game-id="' + gameId + '"]').first();
        if (gameElement.length) {
            setTimeout(function() {
                $('html, body').animate({
                    scrollTop: gameElement.offset().top - 100
                }, 500);
                // Add highlight
                gameElement.addClass('timeline-highlight-glow');
                setTimeout(function() {
                    gameElement.removeClass('timeline-highlight-glow');
                }, 3000);
                // Clear hash after scrolling
                history.replaceState(null, null, window.location.pathname);
            }, 100);
        }
    }
});

// Game actions
function joinGame(gameId, isReserve) {
    $.get('ajax/join_game_form.php', { 
        game_id: gameId, 
        is_reserve: isReserve ? 1 : 0 
    }, function(html) {
        openModal(html);
    });
}

// Alias for index.php compatibility
function loadJoinGameForm(gameId, isReserve) {
    joinGame(gameId, isReserve);
}

function editGame(gameId) {
    const t = (typeof CONFIG !== 'undefined' && CONFIG.translations) ? CONFIG.translations : {};
    
    // Check if user is logged in or admin
    if (typeof CONFIG !== 'undefined' && (CONFIG.isLoggedIn || CONFIG.isAdmin)) {
        // Logged in users can edit
        $.get('ajax/edit_game_form.php', { game_id: gameId }, function(html) {
            openModal(html);
        });
        return;
    }
    
    // Not logged in - check verification method
    if (typeof CONFIG !== 'undefined' && CONFIG.verificationMethod === 'email') {
        // Require email verification
        showEmailVerification(
            t.enter_email_for_creating || 'Enter the email address you used when creating this game', 
            function(email) {
                // Verify email with backend
                $.post('ajax/verify_email.php', {
                    game_id: gameId,
                    email: email,
                    action: 'edit_game'
                }, function(response) {
                    if (response.verified) {
                        // Email matches - allow edit
                        $.get('ajax/edit_game_form.php', { game_id: gameId }, function(html) {
                            openModal(html);
                        });
                    } else {
                        showAlert(response.message || t.email_does_not_match || 'Email does not match. You can only edit games you created.');
                    }
                }, 'json').fail(function() {
                    showAlert(t.verification_failed || 'Verification failed. Please try again.');
                });
            }, 
            t.email_verification_required || 'Verify Email'
        );
    } else {
        // No verification required
        $.get('ajax/edit_game_form.php', { game_id: gameId }, function(html) {
            openModal(html);
        });
    }
}

// Alias for index.php compatibility
function loadEditGameForm(gameId) {
    editGame(gameId);
}

function deleteGame(gameId) {
    const t = (typeof CONFIG !== 'undefined' && CONFIG.translations) ? CONFIG.translations : {};
    
    // Check if user is logged in or admin
    if (typeof CONFIG !== 'undefined' && (CONFIG.isLoggedIn || CONFIG.isAdmin)) {
        // Logged in users can delete
        $.get('ajax/delete_game_choice.php', { game_id: gameId }, function(html) {
            openModal(html);
        });
        return;
    }
    
    // Not logged in - check verification method
    if (typeof CONFIG !== 'undefined' && CONFIG.verificationMethod === 'email') {
        // Require email verification
        showEmailVerification(
            t.enter_email_for_creating || 'Enter the email address you used when creating this game', 
            function(email) {
                // Verify email with backend
                $.post('ajax/verify_email.php', {
                    game_id: gameId,
                    email: email,
                    action: 'delete_game'
                }, function(response) {
                    if (response.verified) {
                        // Email matches - allow delete
                        $.get('ajax/delete_game_choice.php', { game_id: gameId }, function(html) {
                            openModal(html);
                        });
                    } else {
                        showAlert(response.message || t.email_does_not_match || 'Email does not match. You can only delete games you created.');
                    }
                }, 'json').fail(function() {
                    showAlert(t.verification_failed || 'Verification failed. Please try again.');
                });
            }, 
            t.email_verification_required || 'Verify Email'
        );
    } else {
        // No verification required
        $.get('ajax/delete_game_choice.php', { game_id: gameId }, function(html) {
            openModal(html);
        });
    }
}

function restoreGame(gameId) {
    $.get('ajax/restore_game_form.php', { game_id: gameId }, function(html) {
        openModal(html);
    });
}

// Alias for index.php compatibility
function loadRestoreGameForm(gameId) {
    restoreGame(gameId);
}

function fullyDeleteGame(gameId) {
    showConfirm('Are you sure you want to permanently delete this game? This cannot be undone!', function() {
        $.post('ajax/fully_delete_game.php', { game_id: gameId }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                showAlert(response.message || 'Error occurred');
            }
        }, 'json');
    }, 'Confirm Delete');
}

function resignFromGame(gameId, playerId) {
    console.log('resignFromGame called with gameId:', gameId, 'playerId:', playerId);
    
    // Get translations or use fallbacks
    const t = (typeof CONFIG !== 'undefined' && CONFIG.translations) ? CONFIG.translations : {};
    
    // Inner function to actually resign
    function doResign(verifiedEmail) {
        console.log('Resigning from game:', gameId, 'player:', playerId, 'verifiedEmail:', verifiedEmail);
        
        var postData = { 
            game_id: gameId, 
            player_id: playerId 
        };
        
        // Add verified email if provided
        if (verifiedEmail) {
            postData.verified_email = verifiedEmail;
        }
        
        $.post('ajax/resign_player.php', postData, function(response) {
            console.log('Resign response:', response);
            if (response.success) {
                reloadAndScrollToGame(gameId);
            } else {
                showAlert(response.error || response.message || t.error_occurred || 'Error occurred');
            }
        }, 'json').fail(function(xhr) {
            console.error('Resign failed:', xhr.responseText);
            showAlert(t.verification_failed || 'Failed to resign. Please try again.');
        });
    }
    
    // Check if user is logged in or admin
    if (typeof CONFIG !== 'undefined' && (CONFIG.isLoggedIn || CONFIG.isAdmin)) {
        // Logged in users can resign
        showConfirm(
            t.confirm_resign || 'Are you sure you want to resign from this game?', 
            function() {
                doResign(); // No verified email needed
            }, 
            t.confirm_resignation || 'Confirm Resignation'
        );
        return;
    }
    
    // Not logged in - check verification method
    if (typeof CONFIG !== 'undefined' && CONFIG.verificationMethod === 'email') {
        // Require email verification
        showEmailVerification(
            t.enter_email_for_joining || 'Enter the email address you used when joining this game', 
            function(email) {
                // Verify email with backend
                $.post('ajax/verify_email.php', {
                    player_id: playerId,
                    email: email,
                    action: 'resign_player'
                }, function(response) {
                    if (response.verified) {
                        // Email matches - confirm resignation and pass verified email
                        showConfirm(
                            t.confirm_resign || 'Are you sure you want to resign from this game?', 
                            function() {
                                doResign(email); // Pass the verified email
                            }, 
                            t.confirm_resignation || 'Confirm Resignation'
                        );
                    } else {
                        showAlert(response.message || t.email_does_not_match || 'Email does not match. You can only resign from games you joined.');
                    }
                }, 'json').fail(function() {
                    showAlert(t.verification_failed || 'Verification failed. Please try again.');
                });
            }, 
            t.email_verification_required || 'Verify Email'
        );
    } else {
        // No verification required
        showConfirm(
            t.confirm_resign || 'Are you sure you want to resign from this game?', 
            function() {
                doResign(); // No verified email needed
            }, 
            t.confirm_resignation || 'Confirm Resignation'
        );
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

// Alias for consistency
function loadAddCommentForm(gameId) {
    loadComments(gameId);
}

// Table/Event actions
function addTable(eventDayId) {
    $.post('ajax/add_table.php', { event_day_id: eventDayId }, function(response) {
        if (response.success) {
            location.reload();
        } else {
            showAlert(response.message || 'Error adding table');
        }
    }, 'json');
}

function addGameToTable(tableId) {
    $.get('ajax/add_game_form.php', { table_id: tableId }, function(html) {
        openModal(html);
    });
}

// Alias for index.php compatibility
function loadAddGameForm(tableId) {
    addGameToTable(tableId);
}

// Poll functions
function createPoll(tableId) {
    $.get('ajax/create_poll_form.php', { table_id: tableId }, function(html) {
        openModal(html);
    });
}

// Alias for index.php compatibility
function loadCreatePollForm(tableId) {
    createPoll(tableId);
}

function editPoll(pollId) {
    const t = (typeof CONFIG !== 'undefined' && CONFIG.translations) ? CONFIG.translations : {};
    
    // Check if user is logged in or admin
    if (typeof CONFIG !== 'undefined' && (CONFIG.isLoggedIn || CONFIG.isAdmin)) {
        // Logged in users can edit
        $.get('ajax/edit_poll_form.php', { poll_id: pollId }, function(html) {
            openModal(html);
        });
        return;
    }
    
    // Not logged in - check verification method
    if (typeof CONFIG !== 'undefined' && CONFIG.verificationMethod === 'email') {
        // Require email verification
        showEmailVerification(
            t.enter_email_for_poll || 'Enter the email address you used when creating this poll', 
            function(email) {
                // Verify email with backend
                $.post('ajax/verify_email.php', {
                    poll_id: pollId,
                    email: email,
                    action: 'edit_poll'
                }, function(response) {
                    if (response.verified) {
                        // Email matches - allow edit
                        $.get('ajax/edit_poll_form.php', { poll_id: pollId }, function(html) {
                            openModal(html);
                        });
                    } else {
                        showAlert(response.message || t.email_does_not_match || 'Email does not match. You can only edit polls you created.');
                    }
                }, 'json').fail(function() {
                    showAlert(t.verification_failed || 'Verification failed. Please try again.');
                });
            }, 
            t.email_verification_required || 'Verify Email'
        );
    } else {
        // No verification required
        $.get('ajax/edit_poll_form.php', { poll_id: pollId }, function(html) {
            openModal(html);
        });
    }
}

function loadVoteForm(optionId, pollId) {
    $.get('ajax/vote_form.php', { 
        option_id: optionId, 
        poll_id: pollId 
    }, function(html) {
        openModal(html);
    });
}

// Alias for template compatibility
function voteOption(optionId, pollId) {
    loadVoteForm(optionId, pollId);
}

function deletePoll(pollId) {
    const t = (typeof CONFIG !== 'undefined' && CONFIG.translations) ? CONFIG.translations : {};
    
    // Inner function to actually delete
    function doDelete() {
        $.post('ajax/delete_poll.php', { poll_id: pollId }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                showAlert(response.message || t.error_occurred || 'Error deleting poll');
            }
        }, 'json');
    }
    
    // Check if user is logged in or admin
    if (typeof CONFIG !== 'undefined' && (CONFIG.isLoggedIn || CONFIG.isAdmin)) {
        // Logged in users can delete
        showConfirm(
            t.confirm_delete_poll || 'Are you sure you want to delete this poll?', 
            function() {
                doDelete();
            }, 
            t.confirm_delete || 'Confirm Delete Poll'
        );
        return;
    }
    
    // Not logged in - check verification method
    if (typeof CONFIG !== 'undefined' && CONFIG.verificationMethod === 'email') {
        // Require email verification
        showEmailVerification(
            t.enter_email_for_poll || 'Enter the email address you used when creating this poll', 
            function(email) {
                // Verify email with backend
                $.post('ajax/verify_email.php', {
                    poll_id: pollId,
                    email: email,
                    action: 'delete_poll'
                }, function(response) {
                    if (response.verified) {
                        // Email matches - confirm delete
                        showConfirm(
                            t.confirm_delete_poll || 'Are you sure you want to delete this poll?', 
                            function() {
                                doDelete();
                            }, 
                            t.confirm_delete || 'Confirm Delete Poll'
                        );
                    } else {
                        showAlert(response.message || t.email_does_not_match || 'Email does not match. You can only delete polls you created.');
                    }
                }, 'json').fail(function() {
                    showAlert(t.verification_failed || 'Verification failed. Please try again.');
                });
            }, 
            t.email_verification_required || 'Verify Email'
        );
    } else {
        // No verification required
        showConfirm(
            t.confirm_delete_poll || 'Are you sure you want to delete this poll?', 
            function() {
                doDelete();
            }, 
            t.confirm_delete || 'Confirm Delete Poll'
        );
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
    // Close modal only via close button (X) or ESC key
    // Overlay clicks do NOT close modal (prevents accidental closes)
    $('.modal-close').click(closeModal);
    
    // ESC key to close modal
    $(document).keyup(function(e) {
        if (e.key === "Escape") {
            closeModal();
        }
    });
});
// Modal confirmation and alert system (replaces browser alerts/confirms)

function showAlert(message, title) {
    const t = (typeof CONFIG !== 'undefined' && CONFIG.translations) ? CONFIG.translations : {};
    title = title || t.notice || 'Notice';
    const html = `
        <div class="modal-alert">
            <h3>${title}</h3>
            <div class="alert-message">${message}</div>
            <button type="button" class="btn btn-primary" onclick="closeModal()">OK</button>
        </div>
    `;
    openModal(html);
}

function showConfirm(message, onConfirm, title) {
    title = title || 'Confirm';
    const confirmId = 'confirm_' + Date.now();
    
    const html = `
        <div class="modal-confirm">
            <h3>${title}</h3>
            <div class="confirm-message">${message}</div>
            <div class="confirm-buttons">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn btn-primary" id="${confirmId}">OK</button>
            </div>
        </div>
    `;
    
    openModal(html);
    
    // Attach confirm handler
    $('#' + confirmId).one('click', function() {
        closeModal();
        if (onConfirm && typeof onConfirm === 'function') {
            onConfirm();
        }
    });
}

// Email verification prompt
function showEmailVerification(message, onVerify, title) {
    const t = (typeof CONFIG !== 'undefined' && CONFIG.translations) ? CONFIG.translations : {};
    title = title || t.email_verification_required || 'Email Verification Required';
    const verifyId = 'verify_' + Date.now();
    const emailId = 'email_' + Date.now();
    
    const html = `
        <div class="modal-verify">
            <h3>${title}</h3>
            <div class="verify-message">${message}</div>
            <div class="form-group">
                <label>Email Address:</label>
                <input type="email" id="${emailId}" class="form-control" placeholder="Enter your email" required>
            </div>
            <div class="verify-buttons">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn btn-primary" id="${verifyId}">Verify</button>
            </div>
        </div>
    `;
    
    openModal(html);
    
    // Focus on email input
    $('#' + emailId).focus();
    
    // Attach verify handler
    $('#' + verifyId).on('click', function() {
        const email = $('#' + emailId).val().trim();
        if (!email) {
            showAlert(t.enter_email || 'Please enter an email address');
            return;
        }
        if (!isValidEmail(email)) {
            showAlert(t.enter_valid_email || 'Please enter a valid email address');
            return;
        }
        closeModal();
        if (onVerify && typeof onVerify === 'function') {
            onVerify(email);
        }
    });
    
    // Allow Enter key to submit
    $('#' + emailId).on('keypress', function(e) {
        if (e.key === 'Enter') {
            $('#' + verifyId).click();
        }
    });
}

// Email validation helper
function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// ============================================================================
// EVENT HANDLERS (moved from index.php for clean separation)
// ============================================================================

$(document).ready(function() {
    
    // Add Game Button
    $('.btn-add-game').click(function() {
        const tableId = $(this).data('table-id');
        
        // Check login requirement
        if (typeof CONFIG !== 'undefined' && 
            (CONFIG.allowLoggedIn === 'required_games' || CONFIG.allowLoggedIn === 'required_all') && 
            !CONFIG.isLoggedIn) {
            showAlert(CONFIG.translations?.login_required_to_add_game || 'You must be logged in to add games');
            window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
            return;
        }
        
        loadAddGameForm(tableId);
    });
    
    // Create Poll Button
    $('.btn-create-poll').click(function() {
        const tableId = $(this).data('table-id');
        
        // Check login requirement
        if (typeof CONFIG !== 'undefined' && 
            (CONFIG.allowLoggedIn === 'required_games' || CONFIG.allowLoggedIn === 'required_all') && 
            !CONFIG.isLoggedIn) {
            showAlert(CONFIG.translations?.login_required_to_add_game || 'You must be logged in to create polls');
            window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
            return;
        }
        
        loadCreatePollForm(tableId);
    });
    
    // Add Table Button
    $('.btn-add-table').click(function() {
        const dayId = $(this).data('day-id');
        
        // Check login requirement
        if (typeof CONFIG !== 'undefined' && 
            (CONFIG.allowLoggedIn === 'required_games' || CONFIG.allowLoggedIn === 'required_all') && 
            !CONFIG.isLoggedIn) {
            showAlert(CONFIG.translations?.login_required_to_add_table || 'You must be logged in to add tables');
            window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
            return;
        }
        
        $.post('ajax/add_table.php', { day_id: dayId }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                showAlert(response.error || CONFIG.translations?.error_adding_table || 'Error adding table');
            }
        });
    });
    
    // Join Game Button
    $(document).on('click', '.join-game-btn', function(e) {
        e.preventDefault();
        const gameId = $(this).data('game-id');
        
        // Check login requirement
        if (typeof CONFIG !== 'undefined' && CONFIG.allowLoggedIn === 'required_all' && !CONFIG.isLoggedIn) {
            showAlert(CONFIG.translations?.login_required_to_join || 'You must be logged in to join games');
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
        if (typeof CONFIG !== 'undefined' && CONFIG.allowLoggedIn === 'required_all' && !CONFIG.isLoggedIn) {
            showAlert(CONFIG.translations?.login_required_to_join || 'You must be logged in to join games');
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
        
        // resignFromGame handles confirmation and email verification
        resignFromGame(gameId, playerId);
    });
    
    // Edit Game Button
    $(document).on('click', '.edit-game-btn', function(e) {
        e.preventDefault();
        const gameId = $(this).data('game-id');
        editGame(gameId);
    });
    
    // Delete Game Button
    $(document).on('click', '.delete-game-btn', function(e) {
        e.preventDefault();
        const gameId = $(this).data('game-id');
        deleteGame(gameId);
    });
    
    // Restore Game Button
    $(document).on('click', '.restore-game-btn', function(e) {
        e.preventDefault();
        const gameId = $(this).data('game-id');
        restoreGame(gameId);
    });
    
    // Fully Delete Button
    $(document).on('click', '.fully-delete-btn', function(e) {
        e.preventDefault();
        const gameId = $(this).data('game-id');
        fullyDeleteGame(gameId);
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
    
    // Poll button handlers (moved from poll.php templates)
    $(document).on('click', '.btn-vote', function(e) {
        e.preventDefault();
        const optionId = $(this).data('option-id');
        const pollId = $(this).data('poll-id');
        voteOption(optionId, pollId);
    });
    
    $(document).on('click', '.btn-edit-poll', function(e) {
        e.preventDefault();
        const pollId = $(this).data('poll-id');
        editPoll(pollId);
    });
    
    $(document).on('click', '.btn-delete-poll', function(e) {
        e.preventDefault();
        const pollId = $(this).data('poll-id');
        deletePoll(pollId);
    });
});
