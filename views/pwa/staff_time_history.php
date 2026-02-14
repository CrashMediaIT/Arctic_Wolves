<?php
/**
 * PWA Staff Time History - Mobile-native time entry history
 * Purpose-built for mobile phones.
 */

$entries = [];
$summary = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, clock_in_time, clock_out_time, total_hours
        FROM time_entries
        WHERE user_id = ?
        ORDER BY clock_in_time DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $entries = []; }

// Calculate summary periods
try {
    $allowedPeriods = ['week', 'month'];
    $periods = [
        'week' => 'DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
        'month' => 'DATE_SUB(CURDATE(), INTERVAL 1 MONTH)',
    ];
    foreach ($periods as $period => $dateExpr) {
        if (!in_array($period, $allowedPeriods)) continue;
        $sStmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_hours), 0) as total_hours, COUNT(*) as entry_count
            FROM time_entries
            WHERE user_id = ? AND clock_in_time >= $dateExpr
        ");
        $sStmt->execute([$user_id]);
        $summary[$period] = $sStmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) { $summary = []; }

$totalEntries = count($entries);
?>
<style>
.m-timehist { padding: 16px; font-family: Inter, sans-serif; }
.m-timehist-header { margin-bottom: 16px; }
.m-timehist-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-timehist-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-timehist-summary {
    display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 16px;
}
.m-timehist-stat {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    padding: 12px; text-align: center;
}
.m-timehist-stat-val { font-size: 22px; font-weight: 700; color: #8B5CF6; }
.m-timehist-stat-label { font-size: 11px; color: #6B6B7B; text-transform: uppercase; margin-top: 2px; }
.m-timehist-filter {
    display: flex; gap: 0; margin-bottom: 16px;
    background: #0A0A0F; border-radius: 10px; border: 1px solid #2D2D3F;
    overflow: hidden;
}
.m-timehist-filter-btn {
    flex: 1; padding: 10px; min-height: 40px;
    border: none; background: none; cursor: pointer;
    font-size: 13px; font-weight: 600; color: #6B6B7B;
    font-family: Inter, sans-serif; transition: all 0.2s;
}
.m-timehist-filter-btn.m-active { background: #6B46C1; color: #fff; }
.m-timehist-export-btn {
    width: 100%; min-height: 44px; border-radius: 10px;
    border: 1px solid #2D2D3F; background: #16161F; color: #fff;
    font-size: 13px; font-weight: 600; cursor: pointer;
    font-family: Inter, sans-serif; margin-bottom: 16px;
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
.m-timehist-export-btn i { color: #8B5CF6; }
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

    <div class="m-timehist-summary">
        <div class="m-timehist-stat">
            <div class="m-timehist-stat-val"><?= number_format($summary['week']['total_hours'] ?? 0, 1) ?></div>
            <div class="m-timehist-stat-label">Hours this week</div>
        </div>
        <div class="m-timehist-stat">
            <div class="m-timehist-stat-val"><?= number_format($summary['month']['total_hours'] ?? 0, 1) ?></div>
            <div class="m-timehist-stat-label">Hours this month</div>
        </div>
    </div>

    <div class="m-timehist-filter">
        <button class="m-timehist-filter-btn m-active" type="button" onclick="mTimeFilter('all', this)">All</button>
        <button class="m-timehist-filter-btn" type="button" onclick="mTimeFilter('week', this)">Week</button>
        <button class="m-timehist-filter-btn" type="button" onclick="mTimeFilter('month', this)">Month</button>
    </div>

    <button class="m-timehist-export-btn" type="button" onclick="mTimeExport()">
        <i class="fas fa-download"></i> Export History
    </button>

    <div id="mTimeList">
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
            $dateStr = $clockIn ? date('Y-m-d', $clockIn) : '';
        ?>
        <div class="m-timehist-card m-timehist-entry" data-date="<?= $dateStr ?>">
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
</div>

<script>
(function() {
    var csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>';

    window.mTimeFilter = function(filter, btn) {
        document.querySelectorAll('.m-timehist-filter-btn').forEach(function(b) { b.classList.remove('m-active'); });
        btn.classList.add('m-active');
        var items = document.querySelectorAll('.m-timehist-entry');
        var today = new Date();
        items.forEach(function(item) {
            var dateStr = item.getAttribute('data-date');
            if (!dateStr) { item.style.display = 'flex'; return; }
            var d = new Date(dateStr);
            var show = true;
            if (filter === 'week') {
                var weekAgo = new Date(today); weekAgo.setDate(today.getDate() - 7);
                show = d >= weekAgo;
            } else if (filter === 'month') {
                var monthAgo = new Date(today); monthAgo.setMonth(today.getMonth() - 1);
                show = d >= monthAgo;
            }
            item.style.display = show ? 'flex' : 'none';
        });
    };

    window.mTimeExport = function() {
        var fd = new FormData();
        fd.append('action', 'export_time_history');
        fd.append('csrf_token', csrfToken);
        fetch('process_time_tracking.php', { method: 'POST', body: fd })
            .then(function(r) {
                if (r.ok && r.headers.get('content-type')?.includes('text/csv')) {
                    return r.blob().then(function(blob) {
                        var url = URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = 'time_history.csv';
                        a.click();
                        URL.revokeObjectURL(url);
                    });
                }
                return r.json().then(function(data) {
                    if (data.success && data.download_url) {
                        window.location.href = data.download_url;
                    } else if (data.message) {
                        var el = document.querySelector('.m-timehist-export-btn');
                        el.textContent = data.message;
                        setTimeout(function() { el.innerHTML = '<i class="fas fa-download"></i> Export History'; }, 3000);
                    }
                });
            })
            .catch(function() {
                var el = document.querySelector('.m-timehist-export-btn');
                el.textContent = 'Export not available';
                setTimeout(function() { el.innerHTML = '<i class="fas fa-download"></i> Export History'; }, 3000);
            });
    };
})();
</script>
