<?php
/**
 * PWA HR Complaints - Mobile-native complaint management
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$complaints = [];
try {
    $stmt = $pdo->prepare("SELECT id, subject, description, status, priority, created_at, reporter_name FROM complaints ORDER BY created_at DESC LIMIT 20");
    $stmt->execute();
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $complaints = []; }
?>
<style>
.m-complaints { padding: 16px; font-family: Inter, sans-serif; }
.m-complaints-header { margin-bottom: 16px; }
.m-complaints-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-complaints-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-complaint-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-complaint-top {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: 6px; gap: 8px;
}
.m-complaint-subject { font-size: 14px; font-weight: 600; color: #fff; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-complaint-badges { display: flex; gap: 6px; flex-shrink: 0; }
.m-complaint-badge {
    font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600;
    display: inline-block;
}
.m-complaint-priority-high { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-complaint-priority-medium { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-complaint-priority-low { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-complaint-priority-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-complaint-status-open { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-complaint-status-investigating { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-complaint-status-resolved { background: rgba(16,185,129,0.15); color: #10B981; }
.m-complaint-status-closed { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-complaint-status-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-complaint-desc { font-size: 12px; color: #A8A8B8; margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-complaint-meta { font-size: 11px; color: #6B6B7B; display: flex; gap: 12px; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-complaints">
    <div class="m-complaints-header">
        <h2 class="m-complaints-title">Complaints</h2>
        <p class="m-complaints-sub"><?= count($complaints) ?> complaint<?= count($complaints) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($complaints)): ?>
        <div class="m-empty-state">
            <i class="fas fa-flag"></i>
            No complaints filed
        </div>
    <?php else: ?>
        <?php foreach ($complaints as $comp):
            $priority = strtolower($comp['priority'] ?? 'default');
            $priorityClass = match($priority) {
                'high', 'critical', 'urgent' => 'high',
                'medium', 'normal' => 'medium',
                'low' => 'low',
                default => 'default',
            };
            $status = strtolower($comp['status'] ?? 'default');
            $statusClass = match($status) {
                'open', 'new' => 'open',
                'investigating', 'in_progress', 'in progress' => 'investigating',
                'resolved' => 'resolved',
                'closed', 'dismissed' => 'closed',
                default => 'default',
            };
        ?>
        <div class="m-complaint-card">
            <div class="m-complaint-top">
                <span class="m-complaint-subject"><?= htmlspecialchars($comp['subject'] ?: 'No subject') ?></span>
                <div class="m-complaint-badges">
                    <span class="m-complaint-badge m-complaint-priority-<?= $priorityClass ?>"><?= htmlspecialchars(ucfirst($priority)) ?></span>
                    <span class="m-complaint-badge m-complaint-status-<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                </div>
            </div>
            <?php if (!empty($comp['description'])): ?>
                <div class="m-complaint-desc"><?= htmlspecialchars($comp['description']) ?></div>
            <?php endif; ?>
            <div class="m-complaint-meta">
                <?php if (!empty($comp['reporter_name'])): ?>
                    <span><i class="fas fa-user" style="font-size:10px;"></i> <?= htmlspecialchars($comp['reporter_name']) ?></span>
                <?php endif; ?>
                <span><i class="fas fa-calendar" style="font-size:10px;"></i> <?= date('M j, Y', strtotime($comp['created_at'])) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
