<?php
/**
 * PWA Admin Session Types - Mobile-native session types list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$types = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description, default_price, duration_minutes FROM session_types ORDER BY name");
    $stmt->execute();
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $types = []; }
?>
<style>
.m-sesstypes { padding: 16px; font-family: Inter, sans-serif; }
.m-sesstypes-header { margin-bottom: 16px; }
.m-sesstypes-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-sesstypes-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-sesstype-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-sesstype-top { display: flex; justify-content: space-between; align-items: center; }
.m-sesstype-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-sesstype-price { font-size: 14px; font-weight: 700; color: #10B981; }
.m-sesstype-desc { font-size: 12px; color: #A8A8B8; margin-top: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-sesstype-dur { font-size: 11px; color: #6B6B7B; margin-top: 6px; display: inline-flex; align-items: center; gap: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-sesstypes">
    <div class="m-sesstypes-header">
        <h2 class="m-sesstypes-title">Session Types</h2>
        <p class="m-sesstypes-sub"><?= count($types) ?> type<?= count($types) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($types)): ?>
        <div class="m-empty-state">
            <i class="fas fa-calendar-alt"></i>
            <p>No session types defined</p>
        </div>
    <?php else: ?>
        <?php foreach ($types as $t): ?>
        <div class="m-sesstype-card">
            <div class="m-sesstype-top">
                <div class="m-sesstype-name"><?= htmlspecialchars($t['name'] ?? '') ?></div>
                <?php if (isset($t['default_price'])): ?>
                <div class="m-sesstype-price">$<?= number_format((float)$t['default_price'], 2) ?></div>
                <?php endif; ?>
            </div>
            <?php if (!empty($t['description'])): ?>
            <div class="m-sesstype-desc"><?= htmlspecialchars($t['description']) ?></div>
            <?php endif; ?>
            <?php if (!empty($t['duration_minutes'])): ?>
            <div class="m-sesstype-dur"><i class="fas fa-clock"></i> <?= (int)$t['duration_minutes'] ?> min</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
