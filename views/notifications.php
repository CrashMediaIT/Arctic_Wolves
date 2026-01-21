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
?>

<style>
    :root {
        --primary: #7000a4;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
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
        margin-bottom: 15px;
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
</style>

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
