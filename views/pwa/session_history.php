<?php
/**
 * PWA Session History - Mobile-native past sessions list
 * Purpose-built for mobile phones.
 */

$sessions = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, title, session_date, session_time, status, arena
        FROM sessions
        WHERE session_date < CURDATE()
        ORDER BY session_date DESC
        LIMIT 30
    ");
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $sessions = []; }

$totalSessions = count($sessions);
?>
<style>
.m-sesshist { padding: 16px; font-family: Inter, sans-serif; }
.m-sesshist-header { margin-bottom: 16px; }
.m-sesshist-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-sesshist-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-sesshist-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
    text-decoration: none; min-height: 44px;
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
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-sesshist">
    <div class="m-sesshist-header">
        <h2 class="m-sesshist-title">Session History</h2>
        <p class="m-sesshist-sub"><?= $totalSessions ?> past session<?= $totalSessions !== 1 ? 's' : '' ?></p>
    </div>

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
        <a href="?page=session_detail&id=<?= (int)$sess['id'] ?>" class="m-sesshist-card">
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
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
