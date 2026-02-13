<?php
/**
 * PWA HR Employee Contracts - Mobile-native contract management
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$contracts = [];
try {
    $stmt = $pdo->prepare("
        SELECT ec.id, ec.contract_type, ec.start_date, ec.end_date, ec.status,
               u.first_name, u.last_name
        FROM employee_contracts ec
        LEFT JOIN users u ON u.id = ec.user_id
        ORDER BY ec.start_date DESC LIMIT 20
    ");
    $stmt->execute();
    $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $contracts = []; }
?>
<style>
.m-contracts { padding: 16px; font-family: Inter, sans-serif; }
.m-contracts-header { margin-bottom: 16px; }
.m-contracts-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-contracts-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-contract-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-contract-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
    background: rgba(139,92,246,0.15); color: #8B5CF6;
}
.m-contract-body { flex: 1; min-width: 0; }
.m-contract-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-contract-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-contract-type {
    font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600;
    background: rgba(59,130,246,0.15); color: #3B82F6;
    display: inline-block; margin-top: 4px;
}
.m-contract-right { text-align: right; flex-shrink: 0; }
.m-contract-status {
    font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600;
    display: inline-block;
}
.m-contract-status-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-contract-status-expired { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-contract-status-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-contract-status-terminated { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-contract-status-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-contract-dates { font-size: 11px; color: #6B6B7B; margin-top: 4px; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-contracts">
    <div class="m-contracts-header">
        <h2 class="m-contracts-title">Employee Contracts</h2>
        <p class="m-contracts-sub"><?= count($contracts) ?> contract<?= count($contracts) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($contracts)): ?>
        <div class="m-empty-state">
            <i class="fas fa-file-contract"></i>
            No contracts found
        </div>
    <?php else: ?>
        <?php foreach ($contracts as $c):
            $status = strtolower($c['status'] ?? 'default');
            $statusClass = match($status) {
                'active' => 'active',
                'expired' => 'expired',
                'pending', 'draft' => 'pending',
                'terminated' => 'terminated',
                default => 'default',
            };
            $staffName = htmlspecialchars(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: 'Unknown');
            $startDate = $c['start_date'] ? date('M j, Y', strtotime($c['start_date'])) : 'N/A';
            $endDate = $c['end_date'] ? date('M j, Y', strtotime($c['end_date'])) : 'Ongoing';
        ?>
        <div class="m-contract-card">
            <div class="m-contract-icon">
                <i class="fas fa-file-contract"></i>
            </div>
            <div class="m-contract-body">
                <div class="m-contract-name"><?= $staffName ?></div>
                <div class="m-contract-meta"><?= $startDate ?> — <?= $endDate ?></div>
                <?php if (!empty($c['contract_type'])): ?>
                    <span class="m-contract-type"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $c['contract_type']))) ?></span>
                <?php endif; ?>
            </div>
            <div class="m-contract-right">
                <span class="m-contract-status m-contract-status-<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
