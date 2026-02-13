<?php
/**
 * PWA HR Time Tracking - Mobile-native admin time tracking overview
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$todayHours = 0;
$weekHours = 0;

try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_hours), 0) FROM time_entries WHERE DATE(clock_in_time) = CURDATE()");
    $stmt->execute();
    $todayHours = round((float)$stmt->fetchColumn(), 1);
} catch (PDOException $e) { $todayHours = 0; }

try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_hours), 0) FROM time_entries WHERE clock_in_time >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)");
    $stmt->execute();
    $weekHours = round((float)$stmt->fetchColumn(), 1);
} catch (PDOException $e) { $weekHours = 0; }

$entries = [];
try {
    $stmt = $pdo->prepare("
        SELECT te.id, te.clock_in_time, te.clock_out_time, te.total_hours,
               u.first_name, u.last_name
        FROM time_entries te
        LEFT JOIN users u ON u.id = te.user_id
        ORDER BY te.clock_in_time DESC LIMIT 20
    ");
    $stmt->execute();
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $entries = []; }
?>
<style>
.m-hrtime { padding: 16px; font-family: Inter, sans-serif; }
.m-hrtime-header { margin-bottom: 16px; }
.m-hrtime-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-hrtime-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-hrtime-kpi { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
.m-hrtime-stat {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; text-align: center;
}
.m-hrtime-stat-icon { font-size: 16px; margin-bottom: 6px; }
.m-hrtime-stat-value { font-size: 28px; font-weight: 700; color: #fff; line-height: 1.1; }
.m-hrtime-stat-label { font-size: 11px; color: #A8A8B8; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.m-section-title { font-size: 15px; font-weight: 600; color: #fff; margin: 0 0 12px; }
.m-hrtime-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-hrtime-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 700; color: #fff; flex-shrink: 0;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
}
.m-hrtime-body { flex: 1; min-width: 0; }
.m-hrtime-name { font-size: 13px; font-weight: 600; color: #fff; }
.m-hrtime-times { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-hrtime-hours { font-size: 14px; font-weight: 700; color: #8B5CF6; flex-shrink: 0; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-hrtime">
    <div class="m-hrtime-header">
        <h2 class="m-hrtime-title">Time Tracking</h2>
        <p class="m-hrtime-sub">All staff time entries</p>
    </div>

    <div class="m-hrtime-kpi">
        <div class="m-hrtime-stat">
            <div class="m-hrtime-stat-icon" style="color:#10B981;"><i class="fas fa-clock"></i></div>
            <div class="m-hrtime-stat-value"><?= $todayHours ?>h</div>
            <div class="m-hrtime-stat-label">Today Total</div>
        </div>
        <div class="m-hrtime-stat">
            <div class="m-hrtime-stat-icon" style="color:#3B82F6;"><i class="fas fa-calendar-week"></i></div>
            <div class="m-hrtime-stat-value"><?= $weekHours ?>h</div>
            <div class="m-hrtime-stat-label">This Week</div>
        </div>
    </div>

    <h3 class="m-section-title">Recent Entries</h3>
    <?php if (empty($entries)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clock"></i>
            No time entries found
        </div>
    <?php else: ?>
        <?php foreach ($entries as $entry):
            $staffName = htmlspecialchars(trim(($entry['first_name'] ?? '') . ' ' . ($entry['last_name'] ?? '')) ?: 'Unknown');
            $initial = strtoupper(mb_substr($entry['first_name'] ?? '?', 0, 1));
            $inTime = $entry['clock_in_time'] ? date('M j, g:i A', strtotime($entry['clock_in_time'])) : '--';
            $outTime = $entry['clock_out_time'] ? date('g:i A', strtotime($entry['clock_out_time'])) : 'Active';
            $hours = $entry['total_hours'] !== null ? number_format((float)$entry['total_hours'], 1) . 'h' : 'Active';
        ?>
        <div class="m-hrtime-card">
            <div class="m-hrtime-avatar"><?= $initial ?></div>
            <div class="m-hrtime-body">
                <div class="m-hrtime-name"><?= $staffName ?></div>
                <div class="m-hrtime-times">
                    <i class="fas fa-sign-in-alt" style="color:#10B981;font-size:10px;"></i> <?= $inTime ?>
                    · <i class="fas fa-sign-out-alt" style="color:#EF4444;font-size:10px;"></i> <?= $outTime ?>
                </div>
            </div>
            <div class="m-hrtime-hours"><?= $hours ?></div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
