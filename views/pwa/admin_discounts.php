<?php
/**
 * PWA Admin Discounts - Mobile-native discounts list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$discounts = [];
try {
    $stmt = $pdo->prepare("SELECT id, code, description, discount_type, discount_value, is_active, expiry_date FROM discounts ORDER BY created_at DESC LIMIT 20");
    $stmt->execute();
    $discounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $discounts = []; }
?>
<style>
.m-discounts { padding: 16px; font-family: Inter, sans-serif; }
.m-discounts-header { margin-bottom: 16px; }
.m-discounts-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-discounts-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-disc-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-disc-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.m-disc-code { font-size: 14px; font-weight: 700; color: #8B5CF6; font-family: monospace; letter-spacing: 0.5px; }
.m-disc-badge { font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600; }
.m-disc-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-disc-inactive { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-disc-desc { font-size: 12px; color: #A8A8B8; margin-bottom: 6px; }
.m-disc-details { display: flex; gap: 12px; flex-wrap: wrap; }
.m-disc-detail { font-size: 11px; color: #6B6B7B; display: inline-flex; align-items: center; gap: 4px; }
.m-disc-value { font-size: 13px; font-weight: 700; color: #10B981; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-discounts">
    <div class="m-discounts-header">
        <h2 class="m-discounts-title">Discounts</h2>
        <p class="m-discounts-sub"><?= count($discounts) ?> discount<?= count($discounts) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($discounts)): ?>
        <div class="m-empty-state">
            <i class="fas fa-percent"></i>
            <p>No discounts found</p>
        </div>
    <?php else: ?>
        <?php foreach ($discounts as $d):
            $active = (int)($d['is_active'] ?? 0);
            $type = $d['discount_type'] ?? 'fixed';
            $val = (float)($d['discount_value'] ?? 0);
            $display = $type === 'percentage' ? $val . '%' : '$' . number_format($val, 2);
        ?>
        <div class="m-disc-card">
            <div class="m-disc-top">
                <div class="m-disc-code"><?= htmlspecialchars($d['code'] ?? '') ?></div>
                <span class="m-disc-badge <?= $active ? 'm-disc-active' : 'm-disc-inactive' ?>"><?= $active ? 'Active' : 'Inactive' ?></span>
            </div>
            <?php if (!empty($d['description'])): ?>
            <div class="m-disc-desc"><?= htmlspecialchars($d['description']) ?></div>
            <?php endif; ?>
            <div class="m-disc-details">
                <span class="m-disc-value"><?= $display ?> off</span>
                <?php if (!empty($d['expiry_date'])): ?>
                <span class="m-disc-detail"><i class="fas fa-calendar"></i> Expires <?= htmlspecialchars(date('M j, Y', strtotime($d['expiry_date']))) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
