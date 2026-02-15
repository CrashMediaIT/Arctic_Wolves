<?php
/**
 * PWA Home - Mobile-native dashboard
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

$greeting = 'Good ' . (date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'));
$today = date('l, M j');

// Unread notifications
$unreadCount = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_status = 0");
    $stmt->execute([$user_id]);
    $unreadCount = (int)$stmt->fetchColumn();
} catch (PDOException $e) { $unreadCount = 0; }

// Upcoming sessions (next 5)
$upcomingSessions = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.title, s.session_date, s.session_time, s.duration_minutes,
               s.status, s.arena, s.session_type, s.coach_id
        FROM sessions s
        WHERE s.session_date >= CURDATE() AND s.status = 'scheduled'
        ORDER BY s.session_date ASC, s.session_time ASC
        LIMIT 5
    ");
    $stmt->execute();
    $upcomingSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $upcomingSessions = []; }

// Role-specific stats
$sessionsCompleted = 0;
$activeGoals = 0;
$pendingVideos = 0;
$todaySessions = 0;

try {
    if ($isAnyCoach) {
        // Coach: today's sessions
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE coach_id = ? AND session_date = CURDATE() AND status = 'scheduled'");
        $stmt->execute([$user_id]);
        $todaySessions = (int)$stmt->fetchColumn();

        // Pending video reviews
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE assigned_coach_id = ? AND review_status = 'pending'");
            $stmt->execute([$user_id]);
            $pendingVideos = (int)$stmt->fetchColumn();
        } catch (PDOException $e) { $pendingVideos = 0; }
    } else {
        // Athlete: completed sessions
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'confirmed'");
        $stmt->execute([$user_id]);
        $sessionsCompleted = (int)$stmt->fetchColumn();

        // Active goals
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM goals WHERE athlete_id = ? AND status = 'active'");
        $stmt->execute([$user_id]);
        $activeGoals = (int)$stmt->fetchColumn();
    }
} catch (PDOException $e) { /* fallback to zeros */ }

// System notifications
$sysNotifs = [];
try {
    $stmt = $pdo->prepare("
        SELECT title, message, notification_type
        FROM system_notifications
        WHERE is_active = 1
          AND (start_date IS NULL OR start_date <= NOW())
          AND (end_date IS NULL OR end_date >= NOW())
        ORDER BY created_at DESC LIMIT 3
    ");
    $stmt->execute();
    $sysNotifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $sysNotifs = []; }
?>
<style>
.m-home { padding: 16px; font-family: Inter, sans-serif; }
.m-greeting {
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 16px;
}
.m-greeting-name { font-size: 20px; font-weight: 700; color: #fff; margin: 0; }
.m-greeting-date { font-size: 13px; color: rgba(255,255,255,0.7); margin: 4px 0 0; }
.m-greeting-notif {
    display: inline-flex; align-items: center; gap: 6px;
    margin-top: 12px; padding: 6px 12px;
    background: rgba(255,255,255,0.15); border-radius: 20px;
    color: #fff; font-size: 12px; font-weight: 500;
    text-decoration: none;
}
.m-stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
.m-stat {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; text-align: center;
}
.m-stat-value { font-size: 28px; font-weight: 700; color: #fff; }
.m-stat-label { font-size: 11px; color: #A8A8B8; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.m-stat-icon { font-size: 16px; margin-bottom: 6px; }
.m-quick-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 20px; }
.m-quick-btn {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px 8px; text-decoration: none;
    min-height: 44px; min-width: 44px;
}
.m-quick-btn i { font-size: 18px; color: #8B5CF6; }
.m-quick-btn span { font-size: 10px; color: #A8A8B8; font-weight: 500; text-align: center; }
.m-section-title {
    font-size: 15px; font-weight: 600; color: #fff;
    margin: 0 0 12px; padding: 0;
}
.m-session-item {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 12px; margin-bottom: 8px;
    text-decoration: none; min-height: 44px;
}
.m-session-date {
    min-width: 44px; text-align: center;
    background: rgba(107,70,193,0.15); border-radius: 10px;
    padding: 8px 6px;
}
.m-session-date-month { font-size: 10px; color: #8B5CF6; text-transform: uppercase; font-weight: 600; display: block; }
.m-session-date-day { font-size: 18px; color: #fff; font-weight: 700; display: block; line-height: 1.1; }
.m-session-info { flex: 1; min-width: 0; }
.m-session-title { font-size: 14px; color: #fff; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-session-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-session-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    background: rgba(16,185,129,0.15); color: #10B981;
}
.m-alert {
    border-radius: 10px; padding: 12px; margin-bottom: 8px;
    display: flex; align-items: flex-start; gap: 10px;
    font-size: 13px; color: #fff;
}
.m-alert-info { background: rgba(59,130,246,0.12); border: 1px solid rgba(59,130,246,0.25); }
.m-alert-warning { background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.25); }
.m-alert-alert { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.25); }
.m-alert-maintenance { background: rgba(168,168,184,0.12); border: 1px solid rgba(168,168,184,0.25); }
.m-alert i { margin-top: 2px; }
.m-empty { text-align: center; padding: 24px; color: #6B6B7B; font-size: 13px; }
</style>

<div class="m-home">
    <!-- Greeting Card -->
    <div class="m-greeting">
        <?php $firstName = explode(' ', trim($user_name ?: 'Guest'))[0]; ?>
        <p class="m-greeting-name" id="pwa-greeting"><?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars($firstName) ?>!</p>
        <p class="m-greeting-date" id="pwa-greeting-date"><?= $today ?></p>
        <?php if ($unreadCount > 0): ?>
        <a href="?page=notifications" class="m-greeting-notif">
            <i class="fas fa-bell"></i> <?= $unreadCount ?> unread notification<?= $unreadCount !== 1 ? 's' : '' ?>
        </a>
        <?php endif; ?>
    </div>

    <!-- System Alerts -->
    <?php foreach ($sysNotifs as $sn):
        $aType = $sn['notification_type'] ?? 'info';
        $aIcon = match($aType) {
            'warning' => 'fa-exclamation-triangle',
            'alert' => 'fa-circle-exclamation',
            'maintenance' => 'fa-wrench',
            default => 'fa-info-circle',
        };
        $aColor = match($aType) {
            'warning' => '#F59E0B',
            'alert' => '#EF4444',
            'maintenance' => '#A8A8B8',
            default => '#3B82F6',
        };
    ?>
    <div class="m-alert m-alert-<?= $aType ?>">
        <i class="fas <?= $aIcon ?>" style="color:<?= $aColor ?>"></i>
        <div>
            <strong style="font-size:13px;"><?= htmlspecialchars($sn['title']) ?></strong>
            <div style="font-size:12px;color:#A8A8B8;margin-top:2px;"><?= htmlspecialchars($sn['message']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- KPI Stats Grid -->
    <div class="m-stat-grid">
        <?php if ($isAnyCoach): ?>
            <div class="m-stat">
                <div class="m-stat-icon" style="color:#8B5CF6;"><i class="fas fa-calendar-day"></i></div>
                <div class="m-stat-value"><?= $todaySessions ?></div>
                <div class="m-stat-label">Today's Sessions</div>
            </div>
            <div class="m-stat">
                <div class="m-stat-icon" style="color:#F59E0B;"><i class="fas fa-video"></i></div>
                <div class="m-stat-value"><?= $pendingVideos ?></div>
                <div class="m-stat-label">Video Reviews</div>
            </div>
        <?php else: ?>
            <div class="m-stat">
                <div class="m-stat-icon" style="color:#10B981;"><i class="fas fa-check-circle"></i></div>
                <div class="m-stat-value"><?= $sessionsCompleted ?></div>
                <div class="m-stat-label">Sessions</div>
            </div>
            <div class="m-stat">
                <div class="m-stat-icon" style="color:#3B82F6;"><i class="fas fa-bullseye"></i></div>
                <div class="m-stat-value"><?= $activeGoals ?></div>
                <div class="m-stat-label">Active Goals</div>
            </div>
        <?php endif; ?>
        <div class="m-stat">
            <div class="m-stat-icon" style="color:#10B981;"><i class="fas fa-arrow-up"></i></div>
            <div class="m-stat-value"><?= count($upcomingSessions) ?></div>
            <div class="m-stat-label">Upcoming</div>
        </div>
        <div class="m-stat">
            <div class="m-stat-icon" style="color:#EF4444;"><i class="fas fa-bell"></i></div>
            <div class="m-stat-value"><?= $unreadCount ?></div>
            <div class="m-stat-label">Notifications</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="m-quick-grid">
        <a href="?page=sessions" class="m-quick-btn">
            <i class="fas fa-calendar-check"></i><span>Sessions</span>
        </a>
        <a href="?page=messages" class="m-quick-btn">
            <i class="fas fa-comment-dots"></i><span>Messages</span>
        </a>
        <?php if ($isAnyCoach): ?>
            <a href="?page=create_session" class="m-quick-btn">
                <i class="fas fa-calendar-plus" style="color:#10B981;"></i><span>New Session</span>
            </a>
            <a href="?page=roster" class="m-quick-btn">
                <i class="fas fa-users"></i><span>Roster</span>
            </a>
            <a href="?page=drills" class="m-quick-btn">
                <i class="fas fa-hockey-puck"></i><span>Drills</span>
            </a>
            <a href="?page=practice" class="m-quick-btn">
                <i class="fas fa-clipboard-list"></i><span>Plans</span>
            </a>
            <a href="?page=coach_calendar" class="m-quick-btn">
                <i class="fas fa-calendar"></i><span>Calendar</span>
            </a>
            <a href="?page=video" class="m-quick-btn">
                <i class="fas fa-video"></i><span>Video</span>
            </a>
        <?php else: ?>
            <a href="?page=stats" class="m-quick-btn">
                <i class="fas fa-chart-line"></i><span>Stats</span>
            </a>
            <a href="?page=goals" class="m-quick-btn">
                <i class="fas fa-bullseye"></i><span>Goals</span>
            </a>
            <a href="?page=health" class="m-quick-btn">
                <i class="fas fa-heartbeat"></i><span>Health</span>
            </a>
            <a href="?page=video" class="m-quick-btn">
                <i class="fas fa-video"></i><span>Video</span>
            </a>
            <a href="?page=shop" class="m-quick-btn">
                <i class="fas fa-store"></i><span>Shop</span>
            </a>
            <a href="?page=notifications" class="m-quick-btn">
                <i class="fas fa-bell"></i><span>Alerts</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Upcoming Sessions -->
    <h3 class="m-section-title">Upcoming Sessions</h3>
    <?php if (empty($upcomingSessions)): ?>
        <div class="m-empty"><i class="fas fa-calendar-xmark" style="font-size:24px;display:block;margin-bottom:8px;"></i>No upcoming sessions</div>
    <?php else: ?>
        <?php foreach ($upcomingSessions as $sess):
            $sDate = strtotime($sess['session_date']);
            $sTime = $sess['session_time'] ? date('g:i A', strtotime($sess['session_time'])) : '';
        ?>
        <a href="?page=session_detail&id=<?= (int)$sess['id'] ?>" class="m-session-item">
            <div class="m-session-date">
                <span class="m-session-date-month"><?= date('M', $sDate) ?></span>
                <span class="m-session-date-day"><?= date('j', $sDate) ?></span>
            </div>
            <div class="m-session-info">
                <div class="m-session-title"><?= htmlspecialchars($sess['title']) ?></div>
                <div class="m-session-meta">
                    <?php if ($sTime): ?><i class="fas fa-clock"></i> <?= $sTime ?><?php endif; ?>
                    <?php if ($sess['duration_minutes']): ?> · <?= (int)$sess['duration_minutes'] ?>min<?php endif; ?>
                    <?php if ($sess['arena']): ?> · <?= htmlspecialchars($sess['arena']) ?><?php endif; ?>
                </div>
            </div>
            <span class="m-session-badge">Upcoming</span>
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
        var name = el.textContent.split(',').slice(1).join(',').trim();
        el.textContent = 'Good ' + timeOfDay + ', ' + name;
    }
    var dateEl = document.getElementById('pwa-greeting-date');
    if (dateEl) {
        dateEl.textContent = days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' + now.getDate();
    }
})();
</script>
