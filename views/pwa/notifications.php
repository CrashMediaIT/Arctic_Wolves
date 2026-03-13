<?php
/**
 * PWA Notifications - Mobile-native notification list
 * Purpose-built for mobile phones.
 */

// Mark all notifications as read
if (isset($_GET['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } catch (PDOException $e) { /* silently fail */ }
    header("Location: pwa.php?page=notifications");
    exit();
}

// Mark individual notification as read
if (isset($_GET['mark_read'])) {
    $notif_id = intval($_GET['mark_read']);
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notif_id, $user_id]);
    } catch (PDOException $e) { /* silently fail */ }
    header("Location: pwa.php?page=notifications");
    exit();
}

$notifications = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, message, type, link, read_status, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $notifications = []; }

$unreadCount = 0;
foreach ($notifications as $n) {
    if (empty($n['read_status'])) $unreadCount++;
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
    $systemNotifications = [];
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

function mGetNotifIcon($type) {
    $icons = [
        'info'          => ['fa-info-circle',          'info'],
        'success'       => ['fa-check-circle',         'success'],
        'warning'       => ['fa-exclamation-triangle',  'warning'],
        'error'         => ['fa-circle-exclamation',    'error'],
        'message'       => ['fa-envelope',             'message'],
        'booking'       => ['fa-calendar-check',       'info'],
        'session'       => ['fa-calendar',             'info'],
        'payment'       => ['fa-credit-card',          'success'],
        'goal'          => ['fa-bullseye',             'info'],
        'practice_plan' => ['fa-clipboard-list',       'info'],
        'workout'       => ['fa-dumbbell',             'success'],
        'nutrition'     => ['fa-apple-whole',           'success'],
        'note'          => ['fa-sticky-note',          'message'],
        'video_review'  => ['fa-video',                'message'],
    ];
    return $icons[$type] ?? ['fa-bell', 'default'];
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
    min-height: 44px; text-decoration: none;
    transition: opacity 0.2s, transform 0.2s;
    position: relative; cursor: pointer;
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
.m-notif-meta {
    display: flex; align-items: center; gap: 8px; margin-top: 4px; flex-wrap: wrap;
}
.m-notif-time { font-size: 11px; color: #6B6B7B; }
.m-notif-type-badge {
    font-size: 10px; font-weight: 600; color: #8B5CF6;
    background: rgba(139,92,246,0.12); padding: 1px 7px;
    border-radius: 6px; text-transform: capitalize;
}
.m-notif-mark-read {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; color: #8B5CF6; background: none;
    border: none; cursor: pointer; padding: 2px 0;
    font-family: Inter, sans-serif; min-height: 28px;
}
.m-notif-link-hint {
    font-size: 11px; color: #6B6B7B;
    margin-left: auto;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }

/* System notifications collapsible section */
.m-sys-section { margin-bottom: 16px; }
.m-sys-toggle {
    display: flex; justify-content: space-between; align-items: center;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    padding: 12px 14px; cursor: pointer; min-height: 44px;
    width: 100%; font-family: Inter, sans-serif; color: #fff;
    font-size: 13px; font-weight: 700;
}
.m-sys-toggle-left { display: flex; align-items: center; gap: 8px; }
.m-sys-toggle-icon { color: #8B5CF6; }
.m-sys-toggle-count {
    background: rgba(239,68,68,0.15); color: #EF4444;
    font-size: 11px; font-weight: 700; padding: 1px 8px;
    border-radius: 10px;
}
.m-sys-toggle-chevron { color: #6B6B7B; transition: transform 0.2s; font-size: 12px; }
.m-sys-section.open .m-sys-toggle-chevron { transform: rotate(180deg); }
.m-sys-list {
    display: none; margin-top: 8px;
    flex-direction: column; gap: 8px;
}
.m-sys-section.open .m-sys-list { display: flex; }
.m-sys-alert {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px; border-radius: 10px; border-left: 3px solid;
    position: relative;
    animation: mSysSlideIn 0.25s ease;
    transition: opacity 0.25s, transform 0.25s;
}
.m-sys-alert-info { background: rgba(59,130,246,0.08); border-color: #3B82F6; }
.m-sys-alert-info .m-sys-alert-icon { color: #3B82F6; }
.m-sys-alert-warning { background: rgba(245,158,11,0.08); border-color: #F59E0B; }
.m-sys-alert-warning .m-sys-alert-icon { color: #F59E0B; }
.m-sys-alert-alert { background: rgba(239,68,68,0.08); border-color: #EF4444; }
.m-sys-alert-alert .m-sys-alert-icon { color: #EF4444; }
.m-sys-alert-maintenance { background: rgba(251,191,36,0.08); border-color: #FBBF24; }
.m-sys-alert-maintenance .m-sys-alert-icon { color: #FBBF24; }
.m-sys-alert-icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
.m-sys-alert-body { flex: 1; min-width: 0; }
.m-sys-alert-body strong { display: block; font-size: 12px; font-weight: 700; color: #fff; margin-bottom: 2px; }
.m-sys-alert-body p { font-size: 11px; color: #A8A8B8; margin: 0; line-height: 1.4; }
.m-sys-alert-body small { font-size: 10px; color: #6B6B7B; }
.m-sys-dismiss {
    background: none; border: none; color: #6B6B7B; cursor: pointer;
    padding: 4px; font-size: 14px; flex-shrink: 0; min-width: 28px;
    min-height: 28px; display: flex; align-items: center; justify-content: center;
}
@keyframes mSysSlideIn {
    from { opacity: 0; transform: translateY(-6px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="m-notifs">
    <?php if (!empty($systemNotifications)): ?>
    <!-- System Notifications (collapsible) -->
    <div class="m-sys-section open" id="m-sys-section">
        <button type="button" class="m-sys-toggle" id="m-sys-toggle">
            <span class="m-sys-toggle-left">
                <i class="fas fa-bullhorn m-sys-toggle-icon" aria-hidden="true"></i>
                System Announcements
                <span class="m-sys-toggle-count"><?php echo count($systemNotifications); ?></span>
            </span>
            <i class="fas fa-chevron-down m-sys-toggle-chevron" aria-hidden="true"></i>
        </button>
        <div class="m-sys-list">
            <?php foreach ($systemNotifications as $sysNotif):
                $sysType = htmlspecialchars($sysNotif['notification_type'] ?? 'info', ENT_QUOTES, 'UTF-8');
                $sysIcon = 'info-circle';
                switch ($sysNotif['notification_type']) {
                    case 'warning': $sysIcon = 'exclamation-triangle'; break;
                    case 'alert': $sysIcon = 'exclamation-circle'; break;
                    case 'maintenance': $sysIcon = 'tools'; break;
                }
            ?>
            <div class="m-sys-alert m-sys-alert-<?php echo $sysType; ?>"
                 id="m-sys-alert-<?php echo (int)$sysNotif['id']; ?>"
                 data-sys-id="<?php echo (int)$sysNotif['id']; ?>">
                <div class="m-sys-alert-icon">
                    <i class="fas fa-<?php echo htmlspecialchars($sysIcon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                </div>
                <div class="m-sys-alert-body">
                    <strong><?php echo htmlspecialchars($sysNotif['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
                    <p><?php echo htmlspecialchars($sysNotif['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php if (!empty($sysNotif['end_date'])): ?>
                        <small>Until <?php echo htmlspecialchars(date('M j, g:i A', strtotime($sysNotif['end_date'])), ENT_QUOTES, 'UTF-8'); ?></small>
                    <?php endif; ?>
                </div>
                <button type="button" class="m-sys-dismiss"
                        aria-label="Dismiss notification"
                        data-dismiss-id="<?php echo (int)$sysNotif['id']; ?>">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="m-notifs-header">
        <h2 class="m-notifs-title">Notifications</h2>
        <?php if ($unreadCount > 0): ?>
        <a href="?page=notifications&mark_all_read=1" class="m-mark-read-btn">
            <i class="fas fa-check-double"></i> Mark all read
        </a>
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
            list($icon, $iconClass) = mGetNotifIcon($type);
            $link = $n['link'] ?? '';
            $typeLabel = str_replace('_', ' ', $type);
        ?>
        <div class="m-notif-card<?php echo $isUnread ? ' m-notif-card-unread' : ''; ?>"
             data-notif-id="<?php echo (int)$n['id']; ?>"
             data-notif-link="<?php echo htmlspecialchars($link, ENT_QUOTES, 'UTF-8'); ?>"
             data-notif-unread="<?php echo $isUnread ? '1' : '0'; ?>">
            <div class="m-notif-icon m-notif-icon-<?php echo htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
            </div>
            <div class="m-notif-body">
                <p class="m-notif-title"><?php echo htmlspecialchars($n['title'] ?? 'Notification', ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="m-notif-msg"><?php echo htmlspecialchars($n['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="m-notif-meta">
                    <span class="m-notif-time"><?php echo htmlspecialchars(mTimeAgo($n['created_at']), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if ($type !== 'default'): ?>
                        <span class="m-notif-type-badge"><?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <?php if ($isUnread): ?>
                        <button type="button" class="m-notif-mark-read" data-mark-id="<?php echo (int)$n['id']; ?>">
                            <i class="fas fa-check"></i> Mark read
                        </button>
                    <?php endif; ?>
                    <?php if (!empty($link)): ?>
                        <span class="m-notif-link-hint"><i class="fas fa-arrow-right"></i></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
(function() {
    'use strict';

    // --- System notification toggle ---
    var sysToggle = document.getElementById('m-sys-toggle');
    if (sysToggle) {
        sysToggle.addEventListener('click', function() {
            var section = document.getElementById('m-sys-section');
            if (section) section.classList.toggle('open');
        });
    }

    // --- System notification dismiss with sessionStorage ---
    function getDismissedSysIds() {
        try {
            var raw = sessionStorage.getItem('pwa_dismissed_sys_notifs');
            var arr = JSON.parse(raw || '[]');
            return Array.isArray(arr) ? arr : [];
        } catch (e) {
            return [];
        }
    }

    function saveDismissedSysId(id) {
        var dismissed = getDismissedSysIds();
        if (dismissed.indexOf(id) === -1) dismissed.push(id);
        try { sessionStorage.setItem('pwa_dismissed_sys_notifs', JSON.stringify(dismissed)); } catch (e) {}
    }

    // Hide already-dismissed system alerts on load
    var dismissed = getDismissedSysIds();
    dismissed.forEach(function(id) {
        var el = document.getElementById('m-sys-alert-' + id);
        if (el) el.style.display = 'none';
    });

    document.querySelectorAll('.m-sys-dismiss').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var alertEl = this.closest('.m-sys-alert');
            var id = this.getAttribute('data-dismiss-id');
            if (alertEl) {
                alertEl.style.opacity = '0';
                alertEl.style.transform = 'translateX(100%)';
                setTimeout(function() { alertEl.style.display = 'none'; }, 250);
            }
            if (id) saveDismissedSysId(id);
        });
    });

    // --- Mark individual notification as read ---
    document.querySelectorAll('.m-notif-mark-read').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var id = this.getAttribute('data-mark-id');
            if (id) window.location.href = 'pwa.php?page=notifications&mark_read=' + encodeURIComponent(id);
        });
    });

    // --- Tap notification card to navigate ---
    document.querySelectorAll('.m-notif-card').forEach(function(card) {
        card.addEventListener('click', function() {
            var link = this.getAttribute('data-notif-link');
            if (link) {
                window.location.href = link;
            }
        });
    });
})();
</script>
