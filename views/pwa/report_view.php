<?php
/**
 * PWA Report View - Mobile-native single report viewer
 * Purpose-built for mobile phones.
 */

$report_id = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;

$report = null;
if ($report_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, description, report_type, created_at, status FROM reports WHERE id = ?");
        $stmt->execute([$report_id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $report = null; }
}
?>
<style>
.m-rptview { padding: 16px; font-family: Inter, sans-serif; }
.m-rptview-header { margin-bottom: 16px; }
.m-rptview-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-rptview-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-rptview-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; margin-bottom: 16px;
}
.m-rptview-label { font-size: 11px; color: #6B6B7B; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
.m-rptview-value { font-size: 14px; color: #fff; margin-bottom: 12px; }
.m-rptview-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    display: inline-block;
}
.m-rptview-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-rptview-badge-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-rptview-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-rptview-desktop {
    text-align: center; padding: 24px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    color: #6B6B7B; font-size: 13px;
}
.m-rptview-desktop i { font-size: 24px; display: block; margin-bottom: 8px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-rptview">
    <?php if (!$report): ?>
        <div class="m-empty-state">
            <i class="fas fa-file-circle-xmark"></i>
            <p>Report not found</p>
        </div>
    <?php else: ?>
        <div class="m-rptview-header">
            <h2 class="m-rptview-title"><?= htmlspecialchars($report['name'] ?? 'Report') ?></h2>
            <p class="m-rptview-sub">Report #<?= (int)$report['id'] ?></p>
        </div>

        <div class="m-rptview-card">
            <?php if (!empty($report['report_type'])): ?>
            <div class="m-rptview-label">Type</div>
            <div class="m-rptview-value"><?= htmlspecialchars(ucfirst($report['report_type'])) ?></div>
            <?php endif; ?>

            <?php if (!empty($report['description'])): ?>
            <div class="m-rptview-label">Description</div>
            <div class="m-rptview-value"><?= htmlspecialchars($report['description']) ?></div>
            <?php endif; ?>

            <?php if (!empty($report['created_at'])): ?>
            <div class="m-rptview-label">Created</div>
            <div class="m-rptview-value"><?= date('M j, Y g:i A', strtotime($report['created_at'])) ?></div>
            <?php endif; ?>

            <?php
                $status = strtolower($report['status'] ?? 'pending');
                $badgeClass = match($status) {
                    'completed' => 'completed',
                    'pending' => 'pending',
                    default => 'default',
                };
            ?>
            <div class="m-rptview-label">Status</div>
            <span class="m-rptview-badge m-rptview-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
        </div>

        <div class="m-rptview-desktop">
            <i class="fas fa-desktop"></i>
            View full report on Desktop for detailed data
        </div>
    <?php endif; ?>
</div>
