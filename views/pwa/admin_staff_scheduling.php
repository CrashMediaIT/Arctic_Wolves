<?php
/**
 * PWA Admin Staff Scheduling - Mobile-native staff schedule management
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$schedules = [];
try {
    $stmt = $pdo->prepare("
        SELECT ss.id, ss.shift_date, ss.start_time, ss.end_time, ss.role_assigned,
               u.first_name, u.last_name
        FROM staff_schedules ss
        LEFT JOIN users u ON u.id = ss.user_id
        WHERE ss.shift_date >= CURDATE()
        ORDER BY ss.shift_date ASC, ss.start_time ASC LIMIT 30
    ");
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $schedules = []; }
?>
<style>
.m-staffsched { padding: 16px; font-family: Inter, sans-serif; }
.m-staffsched-header { margin-bottom: 16px; }
.m-staffsched-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-staffsched-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-staffsched-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-staffsched-date {
    min-width: 50px; text-align: center;
    background: rgba(107,70,193,0.15); border-radius: 10px;
    padding: 8px 6px; flex-shrink: 0;
}
.m-staffsched-date-month { font-size: 10px; color: #8B5CF6; text-transform: uppercase; font-weight: 600; display: block; }
.m-staffsched-date-day { font-size: 18px; color: #fff; font-weight: 700; display: block; line-height: 1.1; }
.m-staffsched-body { flex: 1; min-width: 0; }
.m-staffsched-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-staffsched-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-staffsched-role {
    font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600;
    background: rgba(59,130,246,0.15); color: #3B82F6;
    flex-shrink: 0;
}
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-staffsched">
    <div class="m-staffsched-header">
        <h2 class="m-staffsched-title">Staff Scheduling</h2>
        <p class="m-staffsched-sub"><?= count($schedules) ?> upcoming shift<?= count($schedules) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($schedules)): ?>
        <div class="m-empty-state">
            <i class="fas fa-calendar-alt"></i>
            No upcoming shifts scheduled
        </div>
    <?php else: ?>
        <?php foreach ($schedules as $sched):
            $dateObj = strtotime($sched['shift_date']);
            $startTime = $sched['start_time'] ? date('g:i A', strtotime($sched['start_time'])) : '--';
            $endTime = $sched['end_time'] ? date('g:i A', strtotime($sched['end_time'])) : '--';
            $staffName = htmlspecialchars(trim(($sched['first_name'] ?? '') . ' ' . ($sched['last_name'] ?? '')) ?: 'Unassigned');
        ?>
        <div class="m-staffsched-card">
            <div class="m-staffsched-date">
                <span class="m-staffsched-date-month"><?= date('M', $dateObj) ?></span>
                <span class="m-staffsched-date-day"><?= date('j', $dateObj) ?></span>
            </div>
            <div class="m-staffsched-body">
                <div class="m-staffsched-name"><?= $staffName ?></div>
                <div class="m-staffsched-meta">
                    <i class="fas fa-clock" style="font-size:10px;"></i> <?= $startTime ?> — <?= $endTime ?>
                </div>
            </div>
            <?php if (!empty($sched['role_assigned'])): ?>
                <span class="m-staffsched-role"><?= htmlspecialchars($sched['role_assigned']) ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
