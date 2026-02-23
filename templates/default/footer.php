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
    
</body>
</html>
