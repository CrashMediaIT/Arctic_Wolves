<?php
/**
 * PWA Session History - Mobile-native past sessions list
 * Purpose-built for mobile phones.
 */

$filterFrom = $_GET['from'] ?? '';
$filterTo = $_GET['to'] ?? '';

$sessions = [];
try {
    $sql = "
        SELECT id, title, session_date, session_time, status, arena, session_type, city
        FROM sessions
        WHERE session_date < CURDATE()
    ";
    $params = [];
    if (!empty($filterFrom)) {
        $sql .= " AND session_date >= ?";
        $params[] = $filterFrom;
    }
    if (!empty($filterTo)) {
        $sql .= " AND session_date <= ?";
        $params[] = $filterTo;
    }
    $sql .= " ORDER BY session_date DESC LIMIT 30";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $sessions = []; }

$totalSessions = count($sessions);
?>
<style>
.m-sesshist { padding: 16px; font-family: Inter, sans-serif; }
.m-sesshist-header { margin-bottom: 16px; }
.m-sesshist-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-sesshist-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-sesshist-filters {
    display: flex; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; align-items: flex-end;
}
.m-sesshist-filter-field { flex: 1; min-width: 120px; }
.m-sesshist-filter-field label {
    font-size: 13px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; display: block;
}
.m-sesshist-filter-field input {
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff;
    padding: 12px; min-height: 44px; width: 100%; box-sizing: border-box;
    font-family: Inter, sans-serif; font-size: 14px;
}
.m-sesshist-filter-btn {
    background: #6B46C1; color: #fff; border-radius: 10px; min-height: 44px;
    font-weight: 600; border: none; cursor: pointer; padding: 0 16px;
    font-family: Inter, sans-serif; font-size: 13px;
}
.m-sesshist-filter-clear {
    background: rgba(168,168,184,0.15); color: #A8A8B8; border-radius: 10px; min-height: 44px;
    font-weight: 600; border: none; cursor: pointer; padding: 0 12px;
    font-family: Inter, sans-serif; font-size: 13px; text-decoration: none;
    display: inline-flex; align-items: center;
}
.m-sesshist-export {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(107,70,193,0.15); color: #8B5CF6; border: none; border-radius: 10px;
    padding: 8px 14px; font-size: 13px; font-weight: 600; cursor: pointer;
    font-family: Inter, sans-serif; margin-bottom: 14px;
}
.m-sesshist-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px; cursor: pointer;
    text-decoration: none; min-height: 44px;
}
.m-sesshist-card-link {
    display: flex; align-items: center; gap: 12px; text-decoration: none;
}
.m-sesshist-date {
    min-width: 48px; text-align: center;
    background: rgba(107,70,193,0.15); border-radius: 10px;
    padding: 8px 6px; flex-shrink: 0;
}
.m-sesshist-date-month { font-size: 10px; color: #8B5CF6; text-transform: uppercase; font-weight: 600; display: block; }
.m-sesshist-date-day { font-size: 20px; color: #fff; font-weight: 700; display: block; line-height: 1.1; }
.m-sesshist-body { flex: 1; min-width: 0; }
.m-sesshist-name { font-size: 14px; color: #fff; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-sesshist-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; display: flex; gap: 6px; flex-wrap: wrap; }
.m-sesshist-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-sesshist-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-sesshist-badge-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-sesshist-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-sesshist-detail {
    display: none; margin-top: 10px; padding-top: 10px; border-top: 1px solid #2D2D3F;
    font-size: 12px; color: #A8A8B8;
}
.m-sesshist-detail.active { display: block; }
.m-sesshist-detail-row { display: flex; justify-content: space-between; padding: 4px 0; }
.m-sesshist-detail-label { color: #6B6B7B; }
.m-sesshist-detail-val { color: #fff; font-weight: 600; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-sesshist">
    <div class="m-sesshist-header">
        <h2 class="m-sesshist-title">Session History</h2>
        <p class="m-sesshist-sub"><?= $totalSessions ?> past session<?= $totalSessions !== 1 ? 's' : '' ?></p>
    </div>

    <!-- Date Filter -->
    <form method="GET" class="m-sesshist-filters">
        <input type="hidden" name="page" value="session_history">
        <div class="m-sesshist-filter-field">
            <label>From</label>
            <input type="date" name="from" value="<?= htmlspecialchars($filterFrom) ?>">
        </div>
        <div class="m-sesshist-filter-field">
            <label>To</label>
            <input type="date" name="to" value="<?= htmlspecialchars($filterTo) ?>">
        </div>
        <button type="submit" class="m-sesshist-filter-btn"><i class="fas fa-filter"></i> Filter</button>
        <?php if ($filterFrom || $filterTo): ?>
        <a href="?page=session_history" class="m-sesshist-filter-clear"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>

    <!-- Export Button -->
    <?php if (!empty($sessions)): ?>
    <button type="button" class="m-sesshist-export" onclick="mSessExport()">
        <i class="fas fa-download"></i> Export CSV
    </button>
    <?php endif; ?>

    <?php if (empty($sessions)): ?>
        <div class="m-empty-state">
            <i class="fas fa-calendar-xmark"></i>
            <p>No past sessions found</p>
        </div>
    <?php else: ?>
        <?php foreach ($sessions as $sess):
            $sDate = strtotime($sess['session_date']);
            $sTime = $sess['session_time'] ? date('g:i A', strtotime($sess['session_time'])) : '';
            $status = strtolower($sess['status'] ?? 'completed');
            $badgeClass = match($status) {
                'completed' => 'completed',
                'cancelled' => 'cancelled',
                default => 'default',
            };
        ?>
        <div class="m-sesshist-card" onclick="mSessToggle(this)">
            <div class="m-sesshist-card-link">
                <div class="m-sesshist-date">
                    <span class="m-sesshist-date-month"><?= date('M', $sDate) ?></span>
                    <span class="m-sesshist-date-day"><?= date('j', $sDate) ?></span>
                </div>
                <div class="m-sesshist-body">
                    <div class="m-sesshist-name"><?= htmlspecialchars($sess['title']) ?></div>
                    <div class="m-sesshist-meta">
                        <?php if ($sTime): ?><span><i class="fas fa-clock" style="font-size:10px;"></i> <?= $sTime ?></span><?php endif; ?>
                        <?php if (!empty($sess['arena'])): ?><span><i class="fas fa-location-dot" style="font-size:10px;"></i> <?= htmlspecialchars($sess['arena']) ?></span><?php endif; ?>
                    </div>
                </div>
                <span class="m-sesshist-badge m-sesshist-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
            <div class="m-sesshist-detail">
                <div class="m-sesshist-detail-row">
                    <span class="m-sesshist-detail-label">Date</span>
                    <span class="m-sesshist-detail-val"><?= date('M j, Y', $sDate) ?></span>
                </div>
                <?php if ($sTime): ?>
                <div class="m-sesshist-detail-row">
                    <span class="m-sesshist-detail-label">Time</span>
                    <span class="m-sesshist-detail-val"><?= $sTime ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($sess['session_type'])): ?>
                <div class="m-sesshist-detail-row">
                    <span class="m-sesshist-detail-label">Type</span>
                    <span class="m-sesshist-detail-val"><?= htmlspecialchars($sess['session_type']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($sess['arena'])): ?>
                <div class="m-sesshist-detail-row">
                    <span class="m-sesshist-detail-label">Location</span>
                    <span class="m-sesshist-detail-val"><?= htmlspecialchars($sess['arena']) ?><?= !empty($sess['city']) ? ', ' . htmlspecialchars($sess['city']) : '' ?></span>
                </div>
                <?php endif; ?>
                <div style="margin-top:8px;">
                    <a href="?page=session_detail&id=<?= (int)$sess['id'] ?>" style="color:#8B5CF6;font-size:12px;font-weight:600;text-decoration:none;">
                        <i class="fas fa-arrow-right"></i> View Full Details
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function mSessToggle(card) {
    var detail = card.querySelector('.m-sesshist-detail');
    if (detail) detail.classList.toggle('active');
}
function mSessExport() {
    var rows = [['Date','Title','Time','Status','Location','City']];
    <?php foreach ($sessions as $s): ?>
    rows.push([<?= json_encode(date('Y-m-d', strtotime($s['session_date']))) ?>,<?= json_encode($s['title']) ?>,<?= json_encode($s['session_time'] ?? '') ?>,<?= json_encode($s['status'] ?? '') ?>,<?= json_encode($s['arena'] ?? '') ?>,<?= json_encode($s['city'] ?? '') ?>]);
    <?php endforeach; ?>
    var csv = rows.map(function(r){return r.map(function(c){return '"'+String(c).replace(/"/g,'""')+'"';}).join(',');}).join('\n');
    var blob = new Blob([csv], {type:'text/csv'});
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'session_history.csv';
    a.click();
}
</script>
