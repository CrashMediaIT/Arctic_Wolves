<?php
/**
 * PWA Coach Calendar - Mobile-native session calendar for coaches
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAnyCoach):
?>
<style>
.m-denied { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; color: #6B6B7B; font-family: Inter, sans-serif; text-align: center; }
.m-denied i { font-size: 48px; margin-bottom: 16px; }
.m-denied p { font-size: 15px; margin: 0; }
</style>
<div class="m-denied">
    <i class="fas fa-lock"></i>
    <p>Access denied</p>
</div>
<?php
    return;
endif;

$sessions = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.title, s.session_date, s.session_time, s.duration_minutes,
               s.status, s.arena, s.session_type,
               (SELECT COUNT(*) FROM bookings b WHERE b.session_id = s.id AND b.status = 'confirmed') as athlete_count
        FROM sessions s
        WHERE s.coach_id = ? AND s.session_date >= CURDATE()
        ORDER BY s.session_date ASC, s.session_time ASC
        LIMIT 30
    ");
    $stmt->execute([$user_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $sessions = []; }

// Group sessions by date
$grouped = [];
foreach ($sessions as $s) {
    $dateKey = $s['session_date'];
    $grouped[$dateKey][] = $s;
}
?>
<style>
.m-calendar { padding: 16px; font-family: Inter, sans-serif; }
.m-calendar-header { margin-bottom: 16px; }
.m-calendar-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-calendar-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-date-group { margin-bottom: 20px; }
.m-date-label {
    font-size: 13px; font-weight: 600; color: #8B5CF6;
    margin: 0 0 10px; padding: 0 4px;
    display: flex; align-items: center; gap: 6px;
}
.m-date-label-today {
    font-size: 10px; background: rgba(107,70,193,0.2); color: #8B5CF6;
    padding: 2px 8px; border-radius: 6px; font-weight: 600;
}
.m-cal-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 12px; margin-bottom: 8px;
    text-decoration: none; min-height: 44px;
}
.m-cal-time {
    min-width: 52px; text-align: center;
    background: rgba(107,70,193,0.1); border-radius: 10px;
    padding: 8px 6px;
}
.m-cal-time-value { font-size: 13px; font-weight: 700; color: #fff; display: block; }
.m-cal-time-period { font-size: 10px; color: #A8A8B8; display: block; }
.m-cal-info { flex: 1; min-width: 0; }
.m-cal-title { font-size: 14px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-cal-meta { font-size: 12px; color: #A8A8B8; margin-top: 3px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.m-cal-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-cal-badge-scheduled { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-cal-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-cal-badge-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-cal-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-calendar">
    <div class="m-calendar-header">
        <h2 class="m-calendar-title">My Schedule</h2>
        <p class="m-calendar-sub"><?= count($sessions) ?> upcoming session<?= count($sessions) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($sessions)): ?>
        <div class="m-empty-state">
            <i class="fas fa-calendar-xmark"></i>
            <p>No upcoming sessions</p>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $dateKey => $daySessions):
            $dateTs = strtotime($dateKey);
            $isToday = ($dateKey === date('Y-m-d'));
            $dateLabel = $isToday ? 'Today' : date('l, M j', $dateTs);
        ?>
        <div class="m-date-group">
            <h3 class="m-date-label">
                <?= htmlspecialchars($dateLabel) ?>
                <?php if ($isToday): ?><span class="m-date-label-today">TODAY</span><?php endif; ?>
            </h3>
            <?php foreach ($daySessions as $sess):
                $sTime = $sess['session_time'] ? date('g:i', strtotime($sess['session_time'])) : '--';
                $sPeriod = $sess['session_time'] ? date('A', strtotime($sess['session_time'])) : '';
                $status = strtolower($sess['status'] ?? 'scheduled');
                $badgeClass = match($status) {
                    'completed' => 'completed',
                    'cancelled' => 'cancelled',
                    'scheduled' => 'scheduled',
                    default => 'default',
                };
            ?>
            <a href="?page=session_detail&id=<?= (int)$sess['id'] ?>" class="m-cal-card">
                <div class="m-cal-time">
                    <span class="m-cal-time-value"><?= $sTime ?></span>
                    <span class="m-cal-time-period"><?= $sPeriod ?></span>
                </div>
                <div class="m-cal-info">
                    <div class="m-cal-title"><?= htmlspecialchars($sess['title']) ?></div>
                    <div class="m-cal-meta">
                        <span><i class="fas fa-users"></i> <?= (int)$sess['athlete_count'] ?></span>
                        <?php if ($sess['arena']): ?><span><i class="fas fa-location-dot"></i> <?= htmlspecialchars($sess['arena']) ?></span><?php endif; ?>
                        <?php if ($sess['duration_minutes']): ?><span><?= (int)$sess['duration_minutes'] ?>min</span><?php endif; ?>
                    </div>
                </div>
                <span class="m-cal-badge m-cal-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
