<?php
/**
 * Site Footer Template
 */

// Ensure config is available
if (!isset($config)) {
    $config = require __DIR__ . '/../config.php';
}

$venue_name = isset($config['venue_name']) ? $config['venue_name'] : 'BGG Signup';
?>
    </main>
    
    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-content">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($venue_name); ?></p>
                <p class="powered-by">
                    Powered by <a href="https://github.com/marcinskotnicki/bgg_signup" target="_blank">BGG Signup System</a>
                </p>
            </div>
        </div>
    </footer>
    
    <!-- Modal container for forms -->
    <div id="modal-overlay" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <div id="modal-body"></div>
        </div>
    </div>
    
    <!-- Timeline JavaScript -->
    <script src="templates/default/timeline.js"></script>
    
    <script>
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
            $.get('ajax/join_game_form.php', { game_id: gameId, is_reserve: isReserve }, function(html) {
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
            if (confirm('<?php echo t('confirm_fully_delete'); ?>')) {
                $.post('ajax/fully_delete_game.php', { game_id: gameId }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.message || '<?php echo t('error_occurred'); ?>');
                    }
                }, 'json');
            }
        }
        
        function resignFromGame(gameId, playerId) {
            if (confirm('<?php echo t('confirm_resign'); ?>')) {
                $.post('ajax/resign_player.php', { game_id: gameId, player_id: playerId }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.message || '<?php echo t('error_occurred'); ?>');
                    }
                }, 'json');
            }
        }
        
        function loadComments(gameId) {
            $.get('ajax/add_comment_form.php', { game_id: gameId }, function(html) {
                openModal(html);
            });
        }
        
        // Alias for loadComments (used in classic template)
        function addComment(gameId) {
            loadComments(gameId);
        }
        
        function addTable(eventDayId) {
            $.post('ajax/add_table.php', { event_day_id: eventDayId }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.message || '<?php echo t('error_adding_table'); ?>');
                }
            }, 'json');
        }
        
        function addGameToTable(tableId) {
            $.get('ajax/add_game_form.php', { table_id: tableId }, function(html) {
                openModal(html);
            });
        }
        
        function createPoll(tableId) {
            $.get('ajax/create_poll_form.php', { table_id: tableId }, function(html) {
                openModal(html);
            });
        }
        
        function loadVoteForm(optionId, pollId) {
            $.get('ajax/vote_form.php', { option_id: optionId, poll_id: pollId }, function(html) {
                openModal(html);
            });
        }
        
        // Close modal when clicking outside or on close button
        $(document).ready(function() {
            $('.modal-close').click(closeModal);
            
            // Track where mouse down started to prevent closing during text selection
            let mouseDownTarget = null;
            
            $('#modal-overlay').on('mousedown', function(e) {
                mouseDownTarget = e.target;
            });
            
            $('#modal-overlay').on('click', function(e) {
                // Only close if:
                // 1. Click target is the overlay itself (not modal content)
                // 2. Mouse down also started on the overlay (not during text selection)
                if (e.target.id === 'modal-overlay' && mouseDownTarget === e.target) {
                    closeModal();
                }
                // Reset tracking
                mouseDownTarget = null;
            });
            
            // Prevent clicks on modal content from closing
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
    </script>
</body>
</html>