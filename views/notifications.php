<?php
/**
 * Notifications View
 * Display all notifications for the user
 */

require_once __DIR__ . '/../security.php';

// Mark notifications as read when viewed
if (isset($_GET['mark_read'])) {
    $notif_id = intval($_GET['mark_read']);
    $stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $user_id]);
    header("Location: dashboard.php?page=notifications");
    exit();
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header("Location: dashboard.php?page=notifications");
    exit();
}

// Get all notifications
$notifications = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$notifications->execute([$user_id]);
$notifs = $notifications->fetchAll();

$unread_count = 0;
foreach ($notifs as $n) {
    if ($n['read_status'] == 0) $unread_count++;
}

// Fetch active system notifications
$systemNotifications = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, title, message, notification_type, start_date, end_date
        FROM system_notifications
        WHERE is_active = 1
          AND start_date <= NOW()
          AND (end_date IS NULL OR end_date >= NOW())
        ORDER BY 
            CASE notification_type 
                WHEN 'alert' THEN 1 
                WHEN 'maintenance' THEN 2 
                WHEN 'warning' THEN 3 
                ELSE 4 
            END,
            start_date DESC
        LIMIT 5
    ");
    $stmt->execute();
    $systemNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("System notifications fetch error: " . $e->getMessage());
}
?>

<style>
    :root {
        --primary: #7000a4;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
    }
    .btn {
        padding: 10px 20px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        font-size: 13px;
    }
    .btn-secondary {
        background: #1e293b;
    }
    .notification-item {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 12px;
        display: flex;
        gap: 20px;
        align-items: start;
        transition: 0.2s;
    }
    .notification-item:hover {
        border-color: var(--primary);
    }
    .notification-item.unread {
        border-color: var(--primary);
        background: rgba(255, 77, 0, 0.05);
    }
    .notification-icon {
        width: 50px;
        height: 50px;
        background: rgba(255, 77, 0, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        flex-shrink: 0;
        font-size: 20px;
    }
    .notification-content {
        flex: 1;
    }
    .notification-title {
        font-size: 16px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 8px;
    }
    .notification-message {
        font-size: 14px;
        color: #94a3b8;
        line-height: 1.6;
        margin-bottom: 10px;
    }
    .notification-meta {
        display: flex;
        gap: 15px;
        align-items: center;
        font-size: 13px;
        color: #64748b;
    }
    .notification-link {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #64748b;
    }
    .empty-state i {
        font-size: 64px;
        margin-bottom: 20px;
        opacity: 0.3;
    }
    
    /* System Notifications Widget Styles */
    .system-notifications-widget {
        margin-bottom: 24px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .system-notifications-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid #1e293b;
    }
    
    .system-notifications-header h2 {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .system-notifications-header h2 i {
        color: var(--primary);
    }
    
    .system-alert {
        display: flex;
        align-items: flex-start;
        gap: 16px;
        padding: 16px 20px;
        border-radius: 12px;
        border-left: 4px solid;
        position: relative;
        animation: slideIn 0.3s ease;
        transition: opacity 0.3s ease, transform 0.3s ease;
    }
    
    .system-alert-info {
        background: rgba(59, 130, 246, 0.1);
        border-color: #3b82f6;
    }
    
    .system-alert-info .system-alert-icon {
        color: #3b82f6;
    }
    
    .system-alert-warning {
        background: rgba(245, 158, 11, 0.1);
        border-color: #f59e0b;
    }
    
    .system-alert-warning .system-alert-icon {
        color: #f59e0b;
    }
    
    .system-alert-alert {
        background: rgba(239, 68, 68, 0.1);
        border-color: #ef4444;
    }
    
    .system-alert-alert .system-alert-icon {
        color: #ef4444;
    }
    
    .system-alert-maintenance {
        background: rgba(251, 191, 36, 0.1);
        border-color: #fbbf24;
    }
    
    .system-alert-maintenance .system-alert-icon {
        color: #fbbf24;
    }
    
    .system-alert-icon {
        font-size: 20px;
        flex-shrink: 0;
        margin-top: 2px;
    }
    
    .system-alert-content {
        flex: 1;
    }
    
    .system-alert-content strong {
        display: block;
        font-size: 15px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 4px;
    }
    
    .system-alert-content p {
        font-size: 14px;
        color: #94a3b8;
        margin: 0 0 4px 0;
        line-height: 1.5;
    }
    
    .system-alert-content small {
        font-size: 12px;
        color: #64748b;
    }
    
    .system-alert-dismiss {
        background: transparent;
        border: none;
        color: #64748b;
        cursor: pointer;
        padding: 4px;
        font-size: 16px;
        transition: color 0.2s;
        flex-shrink: 0;
    }
    
    .system-alert-dismiss:hover {
        color: #fff;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .section-divider {
        margin: 24px 0;
        padding-bottom: 12px;
        border-bottom: 1px solid #1e293b;
    }
    
    .section-divider h2 {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .section-divider h2 i {
        color: var(--primary);
    }
</style>

<?php if (!empty($systemNotifications)): ?>
<!-- System Notifications Widget -->
<div class="system-notifications-widget">
    <div class="system-notifications-header">
        <h2><i class="fas fa-bullhorn"></i> System Announcements</h2>
    </div>
    <?php foreach ($systemNotifications as $sysNotif): ?>
        <div class="system-alert system-alert-<?php echo htmlspecialchars($sysNotif['notification_type']); ?>" id="system-alert-<?php echo (int)$sysNotif['id']; ?>">
            <div class="system-alert-icon">
                <?php 
                $icon = 'info-circle';
                switch ($sysNotif['notification_type']) {
                    case 'warning': $icon = 'exclamation-triangle'; break;
                    case 'alert': $icon = 'exclamation-circle'; break;
                    case 'maintenance': $icon = 'tools'; break;
                }
                ?>
                <i class="fas fa-<?php echo $icon; ?>" aria-hidden="true"></i>
            </div>
            <div class="system-alert-content">
                <strong><?php echo htmlspecialchars($sysNotif['title']); ?></strong>
                <p><?php echo htmlspecialchars($sysNotif['message']); ?></p>
                <?php if ($sysNotif['end_date']): ?>
                    <small>Until <?php echo date('M j, Y g:i A', strtotime($sysNotif['end_date'])); ?></small>
                <?php endif; ?>
            </div>
            <button class="system-alert-dismiss" 
                    aria-label="Dismiss notification: <?php echo htmlspecialchars($sysNotif['title']); ?>"
                    data-notification-id="<?php echo (int)$sysNotif['id']; ?>">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
    <?php endforeach; ?>
</div>

<script>
// System notification dismiss handler
document.querySelectorAll('.system-alert-dismiss').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var alert = this.closest('.system-alert');
        var notifId = this.getAttribute('data-notification-id');
        if (alert) {
            alert.style.opacity = '0';
            alert.style.transform = 'translateX(100%)';
            setTimeout(function() { alert.style.display = 'none'; }, 300);
            // Store dismissed notification in sessionStorage to persist during session
            if (notifId) {
                try {
                    var dismissed = JSON.parse(sessionStorage.getItem('dismissedNotifications') || '[]');
                    if (Array.isArray(dismissed) && !dismissed.includes(notifId)) {
                        dismissed.push(notifId);
                        sessionStorage.setItem('dismissedNotifications', JSON.stringify(dismissed));
                    }
                } catch (e) {
                    // Reset if data is malformed
                    sessionStorage.setItem('dismissedNotifications', JSON.stringify([notifId]));
                }
            }
        }
    });
});

// Hide already dismissed notifications on page load
document.addEventListener('DOMContentLoaded', function() {
    try {
        var dismissed = JSON.parse(sessionStorage.getItem('dismissedNotifications') || '[]');
        if (Array.isArray(dismissed)) {
            dismissed.forEach(function(id) {
                var alert = document.getElementById('system-alert-' + id);
                if (alert) alert.style.display = 'none';
            });
        }
    } catch (e) {
        // Reset if data is malformed
        sessionStorage.setItem('dismissedNotifications', '[]');
    }
});
</script>
<?php endif; ?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-bell"></i> Notifications
        <?php if ($unread_count > 0): ?>
            <span style="background: var(--primary); color: #fff; border-radius: 12px; padding: 2px 10px; font-size: 14px; margin-left: 10px;">
                <?= $unread_count ?> New
            </span>
        <?php endif; ?>
    </h1>
    <?php if ($unread_count > 0): ?>
        <a href="?page=notifications&mark_all_read=1" class="btn btn-secondary">
            <i class="fas fa-check-double"></i> Mark All Read
        </a>
    <?php endif; ?>
</div>

<?php if (empty($notifs)): ?>
    <div class="empty-state">
        <i class="fas fa-bell-slash"></i>
        <h2 style="color: #fff; margin-bottom: 10px;">No Notifications</h2>
        <p>You're all caught up! Check back later for updates.</p>
    </div>
<?php else: ?>
    <?php foreach ($notifs as $notif): ?>
        <div class="notification-item <?= $notif['read_status'] == 0 ? 'unread' : '' ?>">
            <div class="notification-icon">
                <i class="fas fa-<?= getNotificationIcon($notif['type']) ?>"></i>
            </div>
            <div class="notification-content">
                <div class="notification-title"><?= htmlspecialchars($notif['title']) ?></div>
                <div class="notification-message"><?= htmlspecialchars($notif['message']) ?></div>
                <div class="notification-meta">
                    <span><i class="fas fa-clock"></i> <?= timeAgo($notif['created_at']) ?></span>
                    <?php if ($notif['link']): ?>
                        <a href="<?= htmlspecialchars($notif['link']) ?>" class="notification-link">
                            <i class="fas fa-arrow-right"></i> View Details
                        </a>
                    <?php endif; ?>
                    <?php if ($notif['read_status'] == 0): ?>
                        <a href="?page=notifications&mark_read=<?= $notif['id'] ?>" class="notification-link">
                            <i class="fas fa-check"></i> Mark as Read
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php
function getNotificationIcon($type) {
    $icons = [
        'practice_plan' => 'clipboard-list',
        'workout' => 'dumbbell',
        'nutrition' => 'apple-whole',
        'note' => 'sticky-note',
        'video_review' => 'video',
        'default' => 'bell'
    ];
    return $icons[$type] ?? $icons['default'];
}

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y', $timestamp);
}
?>
