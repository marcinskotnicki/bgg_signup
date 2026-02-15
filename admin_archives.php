<?php
/**
 * Admin: Event Archives
 * View and manage previous event editions
 */

session_start();

// Load configuration
$config = require_once 'config.php';

// Load auth helper
require_once 'includes/auth.php';

// Load translation system
require_once 'includes/translations.php';

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

// Handle event deletion
$message = '';
$error = '';

if (isset($_POST['delete_event'])) {
    $event_id = intval($_POST['event_id']);
    
    try {
        // Don't allow deleting active event
        $stmt = $db->prepare("SELECT is_active FROM events WHERE id = ?");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($event && $event['is_active']) {
            $error = "Cannot delete the currently active event!";
        } else {
            // Delete event (cascade will handle related data)
            $stmt = $db->prepare("DELETE FROM events WHERE id = ?");
            $stmt->execute([$event_id]);
            $message = "Event archived and deleted successfully.";
        }
    } catch (Exception $e) {
        $error = "Error deleting event: " . $e->getMessage();
    }
}

// Get all events with their dates
$stmt = $db->query("
    SELECT 
        e.*,
        MIN(ed.date) as first_date,
        MAX(ed.date) as last_date,
        COUNT(DISTINCT ed.id) as num_days,
        COUNT(DISTINCT t.id) as num_tables,
        COUNT(DISTINCT g.id) as num_games,
        COUNT(DISTINCT p.id) as num_players
    FROM events e
    LEFT JOIN event_days ed ON e.id = ed.event_id
    LEFT JOIN tables t ON ed.id = t.event_day_id
    LEFT JOIN games g ON t.id = g.table_id
    LEFT JOIN players p ON g.id = p.game_id
    GROUP BY e.id
    ORDER BY e.created_at DESC
");
$all_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate active and archived events
$active_events = array_filter($all_events, function($e) { return $e['is_active']; });
$archived_events = array_filter($all_events, function($e) { return !$e['is_active']; });
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Archives - Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1400px;
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
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .section h2 {
            margin-top: 0;
            color: #2c3e50;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }
        
        .events-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .events-table th {
            background: #34495e;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        
        .events-table td {
            padding: 12px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .events-table tr:hover {
            background: #f8f9fa;
        }
        
        .event-active {
            background: #d5f4e6 !important;
        }
        
        .event-stats {
            font-size: 13px;
            color: #7f8c8d;
        }
        
        .event-stats span {
            margin-right: 15px;
        }
        
        .btn-view {
            background: #3498db;
            color: white;
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
            display: inline-block;
            margin-right: 5px;
        }
        
        .btn-view:hover {
            background: #2980b9;
        }
        
        .btn-delete {
            background: #e74c3c;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
        }
        
        .btn-delete:hover {
            background: #c0392b;
        }
        
        .btn-delete:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #95a5a6;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .badge-active {
            background: #27ae60;
            color: white;
        }
        
        .badge-archived {
            background: #95a5a6;
            color: white;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📚 Event Archives</h1>
        <a href="admin.php" class="btn-back">← Back to Admin</a>
    </div>
    
    <?php if ($message): ?>
        <div class="message success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <!-- Active Events -->
    <div class="section">
        <h2>🟢 Active Events (<?php echo count($active_events); ?>)</h2>
        
        <?php if (empty($active_events)): ?>
            <div class="empty-state">
                <p>No active events.</p>
            </div>
        <?php else: ?>
            <table class="events-table">
                <thead>
                    <tr>
                        <th>Event Name</th>
                        <th>Dates</th>
                        <th>Statistics</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_events as $event): ?>
                        <tr class="event-active">
                            <td>
                                <strong><?php echo htmlspecialchars($event['name']); ?></strong>
                                <br>
                                <span class="badge badge-active">ACTIVE</span>
                            </td>
                            <td>
                                <?php echo $event['first_date'] ? date('M j, Y', strtotime($event['first_date'])) : 'N/A'; ?>
                                <?php if ($event['first_date'] !== $event['last_date']): ?>
                                    <br>to<br><?php echo date('M j, Y', strtotime($event['last_date'])); ?>
                                <?php endif; ?>
                            </td>
                            <td class="event-stats">
                                <span>📅 <?php echo $event['num_days']; ?> days</span>
                                <span>🎲 <?php echo $event['num_games']; ?> games</span>
                                <span>👥 <?php echo $event['num_players']; ?> players</span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($event['created_at'])); ?></td>
                            <td>
                                <a href="index.php" class="btn-view" target="_blank">View</a>
                                <button class="btn-delete" disabled title="Cannot delete active event">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Archived Events -->
    <div class="section">
        <h2>📦 Archived Events (<?php echo count($archived_events); ?>)</h2>
        
        <?php if (empty($archived_events)): ?>
            <div class="empty-state">
                <p>No archived events yet.</p>
                <p>Events become archived when you create a new event.</p>
            </div>
        <?php else: ?>
            <table class="events-table">
                <thead>
                    <tr>
                        <th>Event Name</th>
                        <th>Dates</th>
                        <th>Statistics</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archived_events as $event): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($event['name']); ?></strong>
                                <br>
                                <span class="badge badge-archived">ARCHIVED</span>
                            </td>
                            <td>
                                <?php if ($event['first_date']): ?>
                                    <?php echo date('M j, Y', strtotime($event['first_date'])); ?>
                                    <?php if ($event['first_date'] !== $event['last_date']): ?>
                                        <br>to<br><?php echo date('M j, Y', strtotime($event['last_date'])); ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td class="event-stats">
                                <span>📅 <?php echo $event['num_days']; ?> days</span>
                                <span>🎲 <?php echo $event['num_games']; ?> games</span>
                                <span>👥 <?php echo $event['num_players']; ?> players</span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($event['created_at'])); ?></td>
                            <td>
                                <?php if ($event['first_date']): ?>
                                    <a href="index.php?date=<?php echo $event['first_date']; ?>" class="btn-view" target="_blank">View</a>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Permanently delete this event and all its data?\n\nThis cannot be undone!');">
                                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                    <button type="submit" name="delete_event" class="btn-delete">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
