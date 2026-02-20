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

        
        // Close modal - Simple and reliable
        $(document).ready(function() {
            $('.modal-close').click(closeModal);
            
            // Track where mousedown occurred
            let mouseDownOnOverlay = false;
            
            $('#modal-overlay').on('mousedown', function(e) {
                // Only flag if mousedown is directly on overlay (not on modal-content)
                mouseDownOnOverlay = (e.target.id === 'modal-overlay');
            });
            
            $('#modal-overlay').on('mouseup', function(e) {
                // Only close if BOTH mousedown AND mouseup were on overlay
                // This prevents closing during text selection or accidental drags
                if (mouseDownOnOverlay && e.target.id === 'modal-overlay') {
                    closeModal();
                }
                // Reset for next interaction
                mouseDownOnOverlay = false;
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