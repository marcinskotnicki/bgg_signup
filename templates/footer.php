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
        
        // Close modal when clicking outside or on close button
        $(document).ready(function() {
            $('.modal-close').click(closeModal);
            
            $('#modal-overlay').click(function(e) {
                if (e.target.id === 'modal-overlay') {
                    closeModal();
                }
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