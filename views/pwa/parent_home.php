<?php
/**
 * PWA Parent Home - Mobile-native parent dashboard
 * Purpose-built for mobile phones.
 */

if (!$isParent) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Parent access required.</p>';
    echo '</div>';
    return;
}

$greeting = 'Good ' . (date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'));
$today = date('l, M j');

// Get children
$children = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name
        FROM users u
        INNER JOIN parent_athlete_relationships par ON par.athlete_id = u.id
        WHERE par.parent_id = ?
    ");
    $stmt->execute([$user_id]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $children = []; }

// Upcoming sessions for children
$upcomingSessions = [];
if (!empty($children)) {
    $childIds = array_column($children, 'id');
    $placeholders = implode(',', array_fill(0, count($childIds), '?'));
    try {
        $stmt = $pdo->prepare("
            SELECT s.id, s.title, s.session_date, s.session_time, s.arena,
                   u.first_name as child_name
            FROM sessions s
            INNER JOIN bookings b ON b.session_id = s.id AND b.status = 'confirmed'
            INNER JOIN users u ON u.id = b.user_id
            WHERE b.user_id IN ($placeholders)
              AND s.session_date >= CURDATE() AND s.status = 'scheduled'
            ORDER BY s.session_date ASC, s.session_time ASC
            LIMIT 5
        ");
        $stmt->execute($childIds);
        $upcomingSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $upcomingSessions = []; }
}

// Unread notifications
$unreadCount = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_status = 0");
    $stmt->execute([$user_id]);
    $unreadCount = (int)$stmt->fetchColumn();
} catch (PDOException $e) { $unreadCount = 0; }
?>
<style>
.m-parent { padding: 16px; font-family: Inter, sans-serif; }
.m-parent-greeting {
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    border-radius: 16px; padding: 20px; margin-bottom: 16px;
}
.m-parent-greeting-name { font-size: 20px; font-weight: 700; color: #fff; margin: 0; }
.m-parent-greeting-date { font-size: 13px; color: rgba(255,255,255,0.7); margin: 4px 0 0; }
.m-parent-greeting-notif {
    display: inline-flex; align-items: center; gap: 6px;
    margin-top: 12px; padding: 6px 12px;
    background: rgba(255,255,255,0.15); border-radius: 20px;
    color: #fff; font-size: 12px; font-weight: 500; text-decoration: none;
}
.m-section-title {
    font-size: 15px; font-weight: 600; color: #fff;
    margin: 0 0 12px; padding: 0;
}
.m-child-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; text-decoration: none; min-height: 44px;
}
.m-child-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; font-weight: 700; color: #fff; flex-shrink: 0;
}
.m-child-info { flex: 1; min-width: 0; }
.m-child-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-child-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-child-chevron { color: #6B6B7B; font-size: 14px; flex-shrink: 0; }
.m-parent-session {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 12px; margin-bottom: 8px; text-decoration: none; min-height: 44px;
}
.m-parent-session-date {
    min-width: 44px; text-align: center;
    background: rgba(107,70,193,0.15); border-radius: 10px; padding: 8px 6px;
}
.m-parent-session-date-month { font-size: 10px; color: #8B5CF6; text-transform: uppercase; font-weight: 600; display: block; }
.m-parent-session-date-day { font-size: 18px; color: #fff; font-weight: 700; display: block; line-height: 1.1; }
.m-parent-session-info { flex: 1; min-width: 0; }
.m-parent-session-title { font-size: 14px; color: #fff; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-parent-session-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-empty { text-align: center; padding: 24px; color: #6B6B7B; font-size: 13px; }
</style>

<div class="m-parent">
    <div class="m-parent-greeting">
        <?php $firstName = explode(' ', trim($user_name ?: 'Guest'))[0]; ?>
        <p class="m-parent-greeting-name" id="pwa-greeting"><?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars($firstName) ?>!</p>
        <p class="m-parent-greeting-date" id="pwa-greeting-date"><?= $today ?></p>
        <?php if ($unreadCount > 0): ?>
        <a href="?page=notifications" class="m-parent-greeting-notif">
            <i class="fas fa-bell"></i> <?= $unreadCount ?> unread
        </a>
        <?php endif; ?>
    </div>

    <!-- Children -->
    <h3 class="m-section-title">My Athletes</h3>
    <?php if (empty($children)): ?>
        <div class="m-empty"><i class="fas fa-users" style="font-size:24px;display:block;margin-bottom:8px;"></i>No athletes linked</div>
    <?php else: ?>
        <?php foreach ($children as $c):
            $initial = strtoupper(mb_substr($c['first_name'], 0, 1) . mb_substr($c['last_name'], 0, 1));
            $cName = htmlspecialchars($c['first_name'] . ' ' . $c['last_name']);
        ?>
        <a href="?page=athlete_detail&id=<?= (int)$c['id'] ?>" class="m-child-card">
            <div class="m-child-avatar"><?= $initial ?></div>
            <div class="m-child-info">
                <div class="m-child-name"><?= $cName ?></div>
                <div class="m-child-meta">Athlete</div>
            </div>
            <i class="fas fa-chevron-right m-child-chevron"></i>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Upcoming Sessions -->
    <h3 class="m-section-title" style="margin-top:20px;">Upcoming Sessions</h3>
    <?php if (empty($upcomingSessions)): ?>
        <div class="m-empty"><i class="fas fa-calendar-xmark" style="font-size:24px;display:block;margin-bottom:8px;"></i>No upcoming sessions</div>
    <?php else: ?>
        <?php foreach ($upcomingSessions as $sess):
            $sDate = strtotime($sess['session_date']);
            $sTime = $sess['session_time'] ? date('g:i A', strtotime($sess['session_time'])) : '';
        ?>
        <a href="?page=session_detail&id=<?= (int)$sess['id'] ?>" class="m-parent-session">
            <div class="m-parent-session-date">
                <span class="m-parent-session-date-month"><?= date('M', $sDate) ?></span>
                <span class="m-parent-session-date-day"><?= date('j', $sDate) ?></span>
            </div>
            <div class="m-parent-session-info">
                <div class="m-parent-session-title"><?= htmlspecialchars($sess['title']) ?></div>
                <div class="m-parent-session-meta">
                    <?php if ($sTime): ?><i class="fas fa-clock" style="font-size:10px;"></i> <?= $sTime ?><?php endif; ?>
                    <?php if (!empty($sess['arena'])): ?> · <?= htmlspecialchars($sess['arena']) ?><?php endif; ?>
                    <?php if (!empty($sess['child_name'])): ?> · <?= htmlspecialchars($sess['child_name']) ?><?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<script>
(function() {
    var h = new Date().getHours();
    var timeOfDay = h < 12 ? 'Morning' : (h < 17 ? 'Afternoon' : 'Evening');
    var days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var now = new Date();
    var el = document.getElementById('pwa-greeting');
    if (el) {
        var parts = el.textContent.split(',');
        var name = parts.length > 1 ? parts.slice(1).join(',').trim() : '';
        if (name) {
            el.textContent = 'Good ' + timeOfDay + ', ' + name;
        } else {
            el.textContent = 'Good ' + timeOfDay;
        }
    }
    var dateEl = document.getElementById('pwa-greeting-date');
    if (dateEl) {
        dateEl.textContent = days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' + now.getDate();
    }
})();
</script>
