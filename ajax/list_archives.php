<?php
/**
 * AJAX: List Archives
 */

header('Content-Type: application/json');

// Check admin access
session_start();
require_once '../includes/auth.php';
require_once '../includes/translations.php';
require_once '../config.php';

try {
    $db = new PDO('sqlite:../' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$current_user = get_current_user($db);
if (!$current_user || !$current_user['is_admin']) {
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
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

// Build HTML
ob_start();
?>

<!-- Active Events -->
<div class="archive-section">
    <h3>🟢 <?php echo t('active_events'); ?> (<?php echo count($active_events); ?>)</h3>
    
    <?php if (empty($active_events)): ?>
        <div class="empty-state">
            <p><?php echo t('no_active_events'); ?></p>
        </div>
    <?php else: ?>
        <table class="events-table">
            <thead>
                <tr>
                    <th><?php echo t('event_name'); ?></th>
                    <th><?php echo t('dates'); ?></th>
                    <th><?php echo t('statistics'); ?></th>
                    <th><?php echo t('created'); ?></th>
                    <th><?php echo t('actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($active_events as $event): ?>
                    <tr class="event-active">
                        <td>
                            <strong><?php echo htmlspecialchars($event['name']); ?></strong>
                            <br>
                            <span class="badge badge-active"><?php echo t('active'); ?></span>
                        </td>
                        <td>
                            <?php if ($event['first_date']): ?>
                                <?php echo date('M j, Y', strtotime($event['first_date'])); ?>
                                <?php if ($event['first_date'] !== $event['last_date']): ?>
                                    <br><?php echo t('to'); ?><br><?php echo date('M j, Y', strtotime($event['last_date'])); ?>
                                <?php endif; ?>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td class="event-stats">
                            <span>📅 <?php echo $event['num_days']; ?> <?php echo t('days'); ?></span>
                            <span>🎲 <?php echo $event['num_games']; ?> <?php echo t('games'); ?></span>
                            <span>👥 <?php echo $event['num_players']; ?> <?php echo t('players'); ?></span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($event['created_at'])); ?></td>
                        <td>
                            <a href="../index.php" class="btn-view" target="_blank"><?php echo t('view'); ?></a>
                            <button class="btn-delete" disabled title="<?php echo t('cannot_delete_active'); ?>"><?php echo t('delete'); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Archived Events -->
<div class="archive-section" style="margin-top: 30px;">
    <h3>📦 <?php echo t('archived_events'); ?> (<?php echo count($archived_events); ?>)</h3>
    
    <?php if (empty($archived_events)): ?>
        <div class="empty-state">
            <p><?php echo t('no_archived_events'); ?></p>
        </div>
    <?php else: ?>
        <table class="events-table">
            <thead>
                <tr>
                    <th><?php echo t('event_name'); ?></th>
                    <th><?php echo t('dates'); ?></th>
                    <th><?php echo t('statistics'); ?></th>
                    <th><?php echo t('created'); ?></th>
                    <th><?php echo t('actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($archived_events as $event): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($event['name']); ?></strong>
                            <br>
                            <span class="badge badge-archived"><?php echo t('archived'); ?></span>
                        </td>
                        <td>
                            <?php if ($event['first_date']): ?>
                                <?php echo date('M j, Y', strtotime($event['first_date'])); ?>
                                <?php if ($event['first_date'] !== $event['last_date']): ?>
                                    <br><?php echo t('to'); ?><br><?php echo date('M j, Y', strtotime($event['last_date'])); ?>
                                <?php endif; ?>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td class="event-stats">
                            <span>📅 <?php echo $event['num_days']; ?> <?php echo t('days'); ?></span>
                            <span>🎲 <?php echo $event['num_games']; ?> <?php echo t('games'); ?></span>
                            <span>👥 <?php echo $event['num_players']; ?> <?php echo t('players'); ?></span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($event['created_at'])); ?></td>
                        <td>
                            <?php if ($event['first_date']): ?>
                                <a href="../index.php?date=<?php echo $event['first_date']; ?>" class="btn-view" target="_blank"><?php echo t('view'); ?></a>
                            <?php endif; ?>
                            <button class="btn-delete" onclick="deleteEvent(<?php echo $event['id']; ?>)"><?php echo t('delete'); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
function deleteEvent(eventId) {
    if (!confirm('<?php echo t('confirm_delete_event'); ?>')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('event_id', eventId);
    
    fetch('ajax/delete_event.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadArchives(); // Reload the list
            alert(data.message);
        } else {
            alert(data.error || 'Delete failed');
        }
    });
}
</script>

<?php
$html = ob_get_clean();

echo json_encode([
    'success' => true,
    'html' => $html
]);
?>
