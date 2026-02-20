<?php
/**
 * Classic Template - Header
 */

// Ensure config is available
if (!isset($config)) {
    $config = require __DIR__ . '/../../config.php';
}

// Get available languages
$available_languages = get_available_languages();

$venue_name = isset($config['venue_name']) ? $config['venue_name'] : 'BGG Signup';
$page_title = isset($page_title) ? $page_title : '';
?>
<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?><?php echo htmlspecialchars($venue_name); ?></title>
    <link rel="stylesheet" href="<?php echo TEMPLATES_DIR . '/' . (isset($active_template) ? $active_template : $config['active_template']); ?>/css/style.css?v=<?php echo defined('CACHE_VERSION') ? CACHE_VERSION : '1.0.0'; ?>">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	<!-- Shared JavaScript functions -->
	<script src="templates/shared/js/common.js"></script>
</head>
<body>
    <header class="site-header">
        <div class="header-container">
            <h1 class="site-title"><?php echo htmlspecialchars($venue_name); ?></h1>
            <div class="header-right">
                <?php if ($current_user): ?>
                    <span class="user-name"><?php echo htmlspecialchars($current_user['name']); ?></span>
                    <?php if ($current_user['is_admin']): ?>
                        <a href="admin.php" class="btn-admin"><?php echo t('admin_panel'); ?></a>
                    <?php endif; ?>
                    <a href="profile.php" class="btn-profile"><?php echo t('user_profile'); ?></a>
                    <a href="?action=logout" class="btn-logout"><?php echo t('logout'); ?></a>
                <?php else: ?>
                    <a href="login.php" class="btn-login"><?php echo t('login'); ?></a>
                <?php endif; ?>
                
                <!-- Language Selector -->
                <?php if (!empty($available_languages) && count($available_languages) > 1): ?>
                <div class="language-selector">
                    <?php foreach ($available_languages as $lang_code => $lang_name): ?>
                        <?php if ($lang_code !== $current_language): ?>
                            <a href="?lang=<?php echo $lang_code; ?>" class="lang-link">
                                <?php echo $lang_name; ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <main class="main-content">
