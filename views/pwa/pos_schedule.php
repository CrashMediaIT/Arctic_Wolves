<?php
/**
 * PWA POS Schedule - Mobile-native staff schedule view
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$canAccessPOS) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>POS access required</div>';
    return;
}

$shifts = [];
try {
    $stmt = $pdo->prepare("SELECT id, shift_date, start_time, end_time, role_assigned, notes FROM staff_schedules WHERE user_id = ? AND shift_date >= CURDATE() ORDER BY shift_date ASC, start_time ASC LIMIT 14");
    $stmt->execute([$user_id]);
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $shifts = []; }

// Group shifts by date
$grouped = [];
foreach ($shifts as $s) {
    $dateKey = $s['shift_date'];
    $grouped[$dateKey][] = $s;
}
?>
<style>
.m-schedule { padding: 16px; font-family: Inter, sans-serif; }
.m-schedule-header { margin-bottom: 16px; }
.m-schedule-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-schedule-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-schedule-date-group { margin-bottom: 16px; }
.m-schedule-date-label {
    font-size: 13px; font-weight: 600; color: #8B5CF6;
    margin-bottom: 8px; padding-bottom: 4px;
    border-bottom: 1px solid #2D2D3F;
}
.m-schedule-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-schedule-time-block {
    min-width: 60px; text-align: center;
    background: rgba(107,70,193,0.15); border-radius: 10px;
    padding: 8px 6px; flex-shrink: 0;
}
.m-schedule-time-start { font-size: 13px; font-weight: 700; color: #fff; display: block; }
.m-schedule-time-end { font-size: 11px; color: #A8A8B8; display: block; margin-top: 2px; }
.m-schedule-info { flex: 1; min-width: 0; }
.m-schedule-role { font-size: 13px; font-weight: 600; color: #fff; }
.m-schedule-notes { font-size: 12px; color: #A8A8B8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-schedule">
    <div class="m-schedule-header">
        <h2 class="m-schedule-title">My Schedule</h2>
        <p class="m-schedule-sub">Upcoming shifts</p>
    </div>

    <?php if (empty($grouped)): ?>
        <div class="m-empty-state">
            <i class="fas fa-calendar-alt"></i>
            No upcoming shifts scheduled
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $dateKey => $dayShifts):
            $dateObj = strtotime($dateKey);
            $isToday = date('Y-m-d', $dateObj) === date('Y-m-d');
            $dateLabel = $isToday ? 'Today — ' . date('M j', $dateObj) : date('l, M j', $dateObj);
        ?>
        <div class="m-schedule-date-group">
            <div class="m-schedule-date-label"><?= $dateLabel ?></div>
            <?php foreach ($dayShifts as $shift):
                $startTime = $shift['start_time'] ? date('g:i A', strtotime($shift['start_time'])) : '--';
                $endTime = $shift['end_time'] ? date('g:i A', strtotime($shift['end_time'])) : '--';
            ?>
            <div class="m-schedule-card">
                <div class="m-schedule-time-block">
                    <span class="m-schedule-time-start"><?= $startTime ?></span>
                    <span class="m-schedule-time-end"><?= $endTime ?></span>
                </div>
                <div class="m-schedule-info">
                    <div class="m-schedule-role"><?= htmlspecialchars($shift['role_assigned'] ?: 'General') ?></div>
                    <?php if (!empty($shift['notes'])): ?>
                        <div class="m-schedule-notes"><?= htmlspecialchars($shift['notes']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
