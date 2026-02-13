<?php
/**
 * PWA Staff Time History - Mobile-native time entry history
 * Purpose-built for mobile phones.
 */

$entries = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, clock_in_time, clock_out_time, total_hours
        FROM time_entries
        WHERE user_id = ?
        ORDER BY clock_in_time DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $entries = []; }

$totalEntries = count($entries);
?>
<style>
.m-timehist { padding: 16px; font-family: Inter, sans-serif; }
.m-timehist-header { margin-bottom: 16px; }
.m-timehist-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-timehist-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-timehist-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px; min-height: 44px;
}
.m-timehist-date {
    min-width: 48px; text-align: center;
    background: rgba(107,70,193,0.15); border-radius: 10px;
    padding: 8px 6px; flex-shrink: 0;
}
.m-timehist-date-month { font-size: 10px; color: #8B5CF6; text-transform: uppercase; font-weight: 600; display: block; }
.m-timehist-date-day { font-size: 20px; color: #fff; font-weight: 700; display: block; line-height: 1.1; }
.m-timehist-info { flex: 1; min-width: 0; }
.m-timehist-times { font-size: 14px; color: #fff; font-weight: 600; }
.m-timehist-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-timehist-hours {
    font-size: 14px; font-weight: 700; color: #10B981; flex-shrink: 0;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-timehist">
    <div class="m-timehist-header">
        <h2 class="m-timehist-title">Time History</h2>
        <p class="m-timehist-sub"><?= $totalEntries ?> entr<?= $totalEntries !== 1 ? 'ies' : 'y' ?></p>
    </div>

    <?php if (empty($entries)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clock"></i>
            <p>No time entries recorded</p>
        </div>
    <?php else: ?>
        <?php foreach ($entries as $e):
            $clockIn = $e['clock_in_time'] ? strtotime($e['clock_in_time']) : null;
            $clockOut = $e['clock_out_time'] ? strtotime($e['clock_out_time']) : null;
            $hours = $e['total_hours'] ? number_format((float)$e['total_hours'], 1) : '—';
        ?>
        <div class="m-timehist-card">
            <?php if ($clockIn): ?>
            <div class="m-timehist-date">
                <span class="m-timehist-date-month"><?= date('M', $clockIn) ?></span>
                <span class="m-timehist-date-day"><?= date('j', $clockIn) ?></span>
            </div>
            <?php endif; ?>
            <div class="m-timehist-info">
                <div class="m-timehist-times">
                    <?= $clockIn ? date('g:i A', $clockIn) : '—' ?>
                    → <?= $clockOut ? date('g:i A', $clockOut) : 'Active' ?>
                </div>
                <div class="m-timehist-meta">
                    <?= $clockIn ? date('l', $clockIn) : '' ?>
                </div>
            </div>
            <span class="m-timehist-hours"><?= $hours ?>h</span>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
