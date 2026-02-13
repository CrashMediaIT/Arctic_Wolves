<?php
/**
 * PWA Notifications - Mobile-native notification list
 * Purpose-built for mobile phones.
 */

$notifications = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, message, type, read_status, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $notifications = []; }

$unreadCount = 0;
foreach ($notifications as $n) {
    if (empty($n['read_status'])) $unreadCount++;
}

function mTimeAgo($datetime) {
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', $ts);
}
?>
<style>
.m-notifs { padding: 16px; font-family: Inter, sans-serif; }
.m-notifs-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 16px;
}
.m-notifs-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-mark-read-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; border-radius: 8px;
    background: rgba(107,70,193,0.15); color: #8B5CF6;
    font-size: 12px; font-weight: 600; border: none; cursor: pointer;
    font-family: Inter, sans-serif; min-height: 44px;
    text-decoration: none;
}
.m-notif-card {
    display: flex; align-items: flex-start; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
    min-height: 44px;
}
.m-notif-card-unread { border-left: 3px solid #6B46C1; }
.m-notif-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
}
.m-notif-icon-info { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-notif-icon-success { background: rgba(16,185,129,0.15); color: #10B981; }
.m-notif-icon-warning { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-notif-icon-error { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-notif-icon-message { background: rgba(139,92,246,0.15); color: #8B5CF6; }
.m-notif-icon-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-notif-body { flex: 1; min-width: 0; }
.m-notif-title { font-size: 13px; font-weight: 600; color: #fff; margin: 0 0 3px; }
.m-notif-msg { font-size: 12px; color: #A8A8B8; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-notif-time { font-size: 11px; color: #6B6B7B; margin-top: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-notifs">
    <div class="m-notifs-header">
        <h2 class="m-notifs-title">Notifications</h2>
        <?php if ($unreadCount > 0): ?>
        <form method="post" action="process_notifications.php" style="margin:0;">
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="m-mark-read-btn">
                <i class="fas fa-check-double"></i> Mark all read
            </button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="m-empty-state">
            <i class="fas fa-bell-slash"></i>
            <p>No notifications yet</p>
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $n):
            $type = $n['type'] ?? 'default';
            $isUnread = empty($n['read_status']);
            $iconMap = [
                'info' => ['fa-info-circle', 'info'],
                'success' => ['fa-check-circle', 'success'],
                'warning' => ['fa-exclamation-triangle', 'warning'],
                'error' => ['fa-circle-exclamation', 'error'],
                'message' => ['fa-envelope', 'message'],
                'booking' => ['fa-calendar-check', 'info'],
                'session' => ['fa-calendar', 'info'],
                'payment' => ['fa-credit-card', 'success'],
                'goal' => ['fa-bullseye', 'info'],
            ];
            $icon = $iconMap[$type][0] ?? 'fa-bell';
            $iconClass = $iconMap[$type][1] ?? 'default';
        ?>
        <div class="m-notif-card<?= $isUnread ? ' m-notif-card-unread' : '' ?>">
            <div class="m-notif-icon m-notif-icon-<?= $iconClass ?>">
                <i class="fas <?= $icon ?>"></i>
            </div>
            <div class="m-notif-body">
                <p class="m-notif-title"><?= htmlspecialchars($n['title'] ?? 'Notification') ?></p>
                <p class="m-notif-msg"><?= htmlspecialchars($n['message'] ?? '') ?></p>
                <div class="m-notif-time"><?= mTimeAgo($n['created_at']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
