<?php
/**
 * PWA Termination - Mobile-native HR termination records
 * Purpose-built for mobile phones.
 */

if (!$isAdmin) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Admin access required.</p>';
    echo '</div>';
    return;
}

$terminations = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, employee_name, termination_date, reason, status
        FROM terminations
        ORDER BY termination_date DESC
        LIMIT 20
    ");
    $stmt->execute();
    $terminations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $terminations = []; }

$totalTerms = count($terminations);
?>
<style>
.m-termin { padding: 16px; font-family: Inter, sans-serif; }
.m-termin-header { margin-bottom: 16px; }
.m-termin-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-termin-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-termin-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-termin-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; }
.m-termin-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-termin-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-termin-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-termin-badge-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-termin-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-termin-reason { font-size: 12px; color: #A8A8B8; margin: 4px 0 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-termin-date { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-termin">
    <div class="m-termin-header">
        <h2 class="m-termin-title">Terminations</h2>
        <p class="m-termin-sub"><?= $totalTerms ?> record<?= $totalTerms !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($terminations)): ?>
        <div class="m-empty-state">
            <i class="fas fa-user-minus"></i>
            <p>No termination records</p>
        </div>
    <?php else: ?>
        <?php foreach ($terminations as $t):
            $status = strtolower($t['status'] ?? 'pending');
            $badgeClass = match($status) {
                'completed', 'processed' => 'completed',
                'pending' => 'pending',
                default => 'default',
            };
        ?>
        <div class="m-termin-card">
            <div class="m-termin-top">
                <span class="m-termin-name"><?= htmlspecialchars($t['employee_name'] ?? 'Unknown') ?></span>
                <span class="m-termin-badge m-termin-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
            <?php if (!empty($t['reason'])): ?>
            <div class="m-termin-reason"><?= htmlspecialchars($t['reason']) ?></div>
            <?php endif; ?>
            <?php if (!empty($t['termination_date'])): ?>
            <div class="m-termin-date">
                <i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($t['termination_date'])) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
