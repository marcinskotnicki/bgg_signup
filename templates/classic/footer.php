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
            
            // Improved modal closing - prevents closing during text selection
            let isMouseDownOnOverlay = false;
            
            $('#modal-overlay').on('mousedown', function(e) {
                // Check if mousedown is directly on overlay (not on modal-content or its children)
                const $modalContent = $('.modal-content');
                const clickedOnModalContent = $modalContent.is(e.target) || $modalContent.has(e.target).length > 0;
                
                isMouseDownOnOverlay = !clickedOnModalContent && e.target.id === 'modal-overlay';
            });
            
            $('#modal-overlay').on('mouseup', function(e) {
                // Check if mouseup is directly on overlay
                const $modalContent = $('.modal-content');
                const releasedOnModalContent = $modalContent.is(e.target) || $modalContent.has(e.target).length > 0;
                const isMouseUpOnOverlay = !releasedOnModalContent && e.target.id === 'modal-overlay';
                
                // Only close if BOTH mousedown AND mouseup happened on overlay
                // This prevents closing during text selection that ends on overlay
                if (isMouseDownOnOverlay && isMouseUpOnOverlay) {
                    closeModal();
                }
                
                // Reset for next interaction
                isMouseDownOnOverlay = false;
            });
            
            // Prevent clicks on modal content from propagating
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