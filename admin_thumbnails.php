<?php
/**
 * Admin: Manage Default Thumbnails
 * Upload and delete default game thumbnails for non-BGG games
 */

session_start();

// Load configuration
$config = require_once 'config.php';

// Load auth helper
require_once 'includes/auth.php';

// Database connection
try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed');
}

// Check if user is admin
$current_user = get_current_user($db);
if (!$current_user || !$current_user['is_admin']) {
    header('Location: admin.php');
    exit;
}

// Handle file upload
$upload_message = '';
$upload_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['thumbnail'])) {
    $upload_dir = 'thumbnails/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file = $_FILES['thumbnail'];
    $filename = basename($file['name']);
    $target_path = $upload_dir . $filename;
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        $upload_error = 'Invalid file type. Only JPG, PNG, GIF, and WEBP images are allowed.';
    } elseif ($file['size'] > 2 * 1024 * 1024) { // 2MB limit
        $upload_error = 'File too large. Maximum size is 2MB.';
    } elseif (file_exists($target_path)) {
        $upload_error = 'File already exists. Please rename the file or delete the existing one first.';
    } else {
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $upload_message = 'Thumbnail uploaded successfully: ' . htmlspecialchars($filename);
        } else {
            $upload_error = 'Failed to upload file.';
        }
    }
}

// Handle file deletion
if (isset($_POST['delete_thumbnail'])) {
    $filename = $_POST['delete_thumbnail'];
    $file_path = 'thumbnails/' . basename($filename); // Sanitize
    
    if (file_exists($file_path)) {
        if (unlink($file_path)) {
            $upload_message = 'Thumbnail deleted successfully: ' . htmlspecialchars($filename);
        } else {
            $upload_error = 'Failed to delete thumbnail.';
        }
    }
}

// Get all thumbnails
$thumbnails = [];
$thumbnail_dir = 'thumbnails/';
if (is_dir($thumbnail_dir)) {
    $files = scandir($thumbnail_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && !is_dir($thumbnail_dir . $file)) {
            $thumbnails[] = $file;
        }
    }
    sort($thumbnails);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Thumbnails - Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            margin: 0;
            color: #2c3e50;
        }
        
        .btn-back {
            background: #95a5a6;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .btn-back:hover {
            background: #7f8c8d;
        }
        
        .upload-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .upload-section h2 {
            margin-top: 0;
            color: #2c3e50;
        }
        
        .upload-form {
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        
        .file-input-wrapper {
            flex: 1;
        }
        
        .file-input {
            width: 100%;
            padding: 10px;
            border: 2px dashed #ddd;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .file-input:hover {
            border-color: #3498db;
        }
        
        .btn-upload {
            background: #27ae60;
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            white-space: nowrap;
        }
        
        .btn-upload:hover {
            background: #229954;
        }
        
        .message {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .message.success {
            background: #d5f4e6;
            color: #27ae60;
            border-left: 4px solid #27ae60;
        }
        
        .message.error {
            background: #ffebee;
            color: #e74c3c;
            border-left: 4px solid #e74c3c;
        }
        
        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        
        .info-box ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }
        
        .thumbnails-grid {
            background: white;
            padding: 25px;
            border-radius: 8px;
        }
        
        .thumbnails-grid h2 {
            margin-top: 0;
            color: #2c3e50;
        }
        
        .thumbnails-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .thumbnail-item {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
        }
        
        .thumbnail-item img {
            width: 100%;
            height: 150px;
            object-fit: contain;
            border-radius: 4px;
            background: white;
            padding: 5px;
        }
        
        .thumbnail-name {
            margin: 10px 0;
            font-size: 13px;
            color: #2c3e50;
            word-break: break-all;
        }
        
        .thumbnail-actions {
            display: flex;
            gap: 5px;
            margin-top: 10px;
        }
        
        .btn-delete {
            flex: 1;
            background: #e74c3c;
            color: white;
            padding: 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
        }
        
        .btn-delete:hover {
            background: #c0392b;
        }
        
        .btn-copy {
            flex: 1;
            background: #3498db;
            color: white;
            padding: 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
        }
        
        .btn-copy:hover {
            background: #2980b9;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #95a5a6;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📁 Manage Default Thumbnails</h1>
        <a href="admin.php" class="btn-back">← Back to Admin</a>
    </div>
    
    <?php if ($upload_message): ?>
        <div class="message success"><?php echo $upload_message; ?></div>
    <?php endif; ?>
    
    <?php if ($upload_error): ?>
        <div class="message error"><?php echo $upload_error; ?></div>
    <?php endif; ?>
    
    <div class="upload-section">
        <h2>Upload New Thumbnail</h2>
        
        <div class="info-box">
            <strong>ℹ️ Usage:</strong> These thumbnails can be selected when adding games manually (not from BGG).
            <ul>
                <li>Supported formats: JPG, PNG, GIF, WEBP</li>
                <li>Maximum size: 2MB</li>
                <li>Recommended dimensions: 200x200 pixels or similar</li>
            </ul>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="upload-form">
            <div class="file-input-wrapper">
                <input type="file" name="thumbnail" accept="image/jpeg,image/png,image/gif,image/webp" required class="file-input">
            </div>
            <button type="submit" class="btn-upload">Upload Thumbnail</button>
        </form>
    </div>
    
    <div class="thumbnails-grid">
        <h2>Existing Thumbnails (<?php echo count($thumbnails); ?>)</h2>
        
        <?php if (empty($thumbnails)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🖼️</div>
                <p>No thumbnails uploaded yet.</p>
                <p>Upload your first thumbnail above!</p>
            </div>
        <?php else: ?>
            <div class="thumbnails-list">
                <?php foreach ($thumbnails as $thumbnail): ?>
                    <div class="thumbnail-item">
                        <img src="thumbnails/<?php echo htmlspecialchars($thumbnail); ?>" alt="<?php echo htmlspecialchars($thumbnail); ?>">
                        <div class="thumbnail-name"><?php echo htmlspecialchars($thumbnail); ?></div>
                        <div class="thumbnail-actions">
                            <button class="btn-copy" onclick="copyPath('<?php echo htmlspecialchars($thumbnail); ?>')">Copy Path</button>
                            <form method="POST" style="flex: 1; margin: 0;" onsubmit="return confirm('Delete this thumbnail?');">
                                <input type="hidden" name="delete_thumbnail" value="<?php echo htmlspecialchars($thumbnail); ?>">
                                <button type="submit" class="btn-delete">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function copyPath(filename) {
            const path = 'thumbnails/' + filename;
            navigator.clipboard.writeText(path).then(function() {
                alert('Path copied to clipboard: ' + path);
            }, function() {
                alert('Failed to copy path');
            });
        }
    </script>
</body>
</html>
