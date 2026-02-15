<?php
/**
 * Site Header Template
 * 
 * Variables expected:
 * - $config: Configuration array
 * - $db: Database connection
 * - $page_title: (optional) Page title
 */

// Ensure config is available
if (!isset($config)) {
    $config = require __DIR__ . '/../config.php';
}

// Get current user if logged in
$current_user = isset($db) ? get_current_user($db) : null;
$is_user_logged_in = $current_user !== null;
$is_user_admin = $current_user && $current_user['is_admin'] == 1;

// Get available languages
$available_languages = get_available_languages();
$current_lang = get_current_language();

// Safe access to config values
$venue_name = isset($config['venue_name']) ? $config['venue_name'] : 'BGG Signup';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?><?php echo htmlspecialchars($venue_name); ?></title>
    <link rel="stylesheet" href="<?php echo TEMPLATES_DIR . '/' . $config['active_template']; ?>/css/style.css?v=<?php echo defined('CACHE_VERSION') ? CACHE_VERSION : '1.0.0'; ?>">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <header class="site-header">
        <div class="header-container">
            <div class="header-left">
                <h1 class="site-title">
                    <a href="index.php"><?php echo htmlspecialchars($venue_name); ?></a>
                </h1>
            </div>
            
            <div class="header-right">
                <!-- Language Selector -->
                <?php if (count($available_languages) > 1): ?>
                <div class="language-selector">
                    <select id="language-select" onchange="changeLanguage(this.value)">
                        <?php foreach ($available_languages as $code => $name): ?>
                            <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $current_lang === $code ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <!-- User Menu -->
                <?php if ($is_user_logged_in): ?>
                    <div class="user-menu">
                        <span class="user-name"><?php echo htmlspecialchars($current_user['name']); ?></span>
                        <div class="user-dropdown">
                            <a href="profile.php"><?php echo t('user_profile'); ?></a>
                            <?php if ($is_user_admin): ?>
                                <a href="admin.php"><?php echo t('admin_panel'); ?></a>
                            <?php endif; ?>
                            <a href="?action=logout"><?php echo t('logout'); ?></a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php
                    $allow_logged_in = isset($config['allow_logged_in']) ? $config['allow_logged_in'] : 'no';
                    if ($allow_logged_in !== 'no'): 
                    ?>
                        <div class="auth-buttons">
                            <a href="login.php" class="btn-login"><?php echo t('login'); ?></a>
                            <a href="login.php?register" class="btn-register"><?php echo t('register'); ?></a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <script>
        // Handle logout
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'logout') {
            fetch('logout.php')
                .then(() => window.location.href = 'index.php');
        }
        
        // Change language
        function changeLanguage(langCode) {
            document.cookie = 'bgg_language=' + langCode + '; path=/; max-age=' + (365 * 24 * 60 * 60);
            location.reload();
        }
        
        // User dropdown toggle
        document.addEventListener('DOMContentLoaded', function() {
            const userMenu = document.querySelector('.user-menu');
            if (userMenu) {
                userMenu.addEventListener('click', function(e) {
                    if (e.target.classList.contains('user-name')) {
                        this.classList.toggle('active');
                    }
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!userMenu.contains(e.target)) {
                        userMenu.classList.remove('active');
                    }
                });
            }
        });
    </script>
    
    <main class="site-content">