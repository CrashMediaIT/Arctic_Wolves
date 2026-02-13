<?php
/**
 * PWA Front Desk Home - Mobile-native front desk dashboard
 * Purpose-built for mobile phones.
 */

if (!$isFrontDesk && !$isAdmin) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Front desk access required.</p>';
    echo '</div>';
    return;
}

$todaySessions = 0;
$upcomingCheckins = 0;
$recentActivity = [];

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM sessions WHERE session_date = CURDATE() AND status = 'scheduled'");
    $todaySessions = (int)$stmt->fetchColumn();
} catch (PDOException $e) { $todaySessions = 0; }

try {
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM bookings b
        JOIN sessions s ON s.id = b.session_id
        WHERE s.session_date = CURDATE() AND b.status = 'confirmed'
    ");
    $upcomingCheckins = (int)$stmt->fetchColumn();
} catch (PDOException $e) { $upcomingCheckins = 0; }

try {
    $stmt = $pdo->prepare("
        SELECT s.title, s.session_time, s.arena,
               (SELECT COUNT(*) FROM bookings b WHERE b.session_id = s.id AND b.status = 'confirmed') as booked
        FROM sessions s
        WHERE s.session_date = CURDATE() AND s.status = 'scheduled'
        ORDER BY s.session_time ASC
        LIMIT 5
    ");
    $stmt->execute();
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $recentActivity = []; }
?>
<style>
.m-fdhome { padding: 16px; font-family: Inter, sans-serif; }
.m-fdhome-header { margin-bottom: 16px; }
.m-fdhome-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-fdhome-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-fd-stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
.m-fd-stat {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; text-align: center;
}
.m-fd-stat-value { font-size: 28px; font-weight: 700; color: #fff; }
.m-fd-stat-label { font-size: 11px; color: #A8A8B8; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.m-fd-stat-icon { font-size: 16px; margin-bottom: 6px; }
.m-fd-quick-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; }
.m-fd-quick-btn {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px 8px; text-decoration: none; min-height: 44px;
}
.m-fd-quick-btn i { font-size: 18px; color: #8B5CF6; }
.m-fd-quick-btn span { font-size: 10px; color: #A8A8B8; font-weight: 500; text-align: center; }
.m-fd-section-title {
    font-size: 15px; font-weight: 600; color: #fff;
    margin: 0 0 12px; padding: 0;
}
.m-fd-activity-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 12px 14px; margin-bottom: 8px;
    display: flex; align-items: center; gap: 12px; min-height: 44px;
}
.m-fd-activity-info { flex: 1; min-width: 0; }
.m-fd-activity-name { font-size: 14px; color: #fff; font-weight: 600; }
.m-fd-activity-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-fd-activity-count {
    font-size: 12px; font-weight: 600; color: #3B82F6;
    background: rgba(59,130,246,0.15); padding: 3px 8px; border-radius: 6px;
}
.m-empty-state { text-align: center; padding: 24px; color: #6B6B7B; font-size: 13px; }
</style>

<div class="m-fdhome">
    <div class="m-fdhome-header">
        <h2 class="m-fdhome-title">Front Desk</h2>
        <p class="m-fdhome-sub"><?= date('l, M j') ?></p>
    </div>

    <div class="m-fd-stat-grid">
        <div class="m-fd-stat">
            <div class="m-fd-stat-icon" style="color:#8B5CF6;"><i class="fas fa-calendar-day"></i></div>
            <div class="m-fd-stat-value"><?= $todaySessions ?></div>
            <div class="m-fd-stat-label">Today's Sessions</div>
        </div>
        <div class="m-fd-stat">
            <div class="m-fd-stat-icon" style="color:#10B981;"><i class="fas fa-user-check"></i></div>
            <div class="m-fd-stat-value"><?= $upcomingCheckins ?></div>
            <div class="m-fd-stat-label">Check-ins</div>
        </div>
    </div>

    <div class="m-fd-quick-grid">
        <a href="?page=camp_checkin" class="m-fd-quick-btn">
            <i class="fas fa-qrcode"></i><span>Check-in</span>
        </a>
        <a href="?page=pos_terminal" class="m-fd-quick-btn">
            <i class="fas fa-cash-register"></i><span>POS</span>
        </a>
        <a href="?page=sessions" class="m-fd-quick-btn">
            <i class="fas fa-calendar"></i><span>Sessions</span>
        </a>
    </div>

    <h3 class="m-fd-section-title">Today's Sessions</h3>
    <?php if (empty($recentActivity)): ?>
        <div class="m-empty-state"><i class="fas fa-calendar-xmark" style="font-size:24px;display:block;margin-bottom:8px;"></i>No sessions today</div>
    <?php else: ?>
        <?php foreach ($recentActivity as $act): ?>
        <div class="m-fd-activity-card">
            <div class="m-fd-activity-info">
                <div class="m-fd-activity-name"><?= htmlspecialchars($act['title']) ?></div>
                <div class="m-fd-activity-meta">
                    <?php if (!empty($act['session_time'])): ?><i class="fas fa-clock" style="font-size:10px;"></i> <?= date('g:i A', strtotime($act['session_time'])) ?><?php endif; ?>
                    <?php if (!empty($act['arena'])): ?> · <?= htmlspecialchars($act['arena']) ?><?php endif; ?>
                </div>
            </div>
            <span class="m-fd-activity-count"><i class="fas fa-users"></i> <?= (int)($act['booked'] ?? 0) ?></span>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
