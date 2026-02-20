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