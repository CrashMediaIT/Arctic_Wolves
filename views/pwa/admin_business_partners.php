<?php
/**
 * PWA Admin Business Partners - Mobile-native business partners list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$partners = [];
try {
    $stmt = $pdo->prepare("SELECT id, company_name, contact_name, email, phone, status FROM business_partners ORDER BY company_name ASC LIMIT 20");
    $stmt->execute();
    $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $partners = []; }
?>
<style>
.m-partners { padding: 16px; font-family: Inter, sans-serif; }
.m-partners-header { margin-bottom: 16px; }
.m-partners-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-partners-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-partner-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-partner-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(59,130,246,0.15); color: #3B82F6; font-size: 16px; flex-shrink: 0;
}
.m-partner-body { flex: 1; min-width: 0; }
.m-partner-company { font-size: 14px; font-weight: 600; color: #fff; }
.m-partner-contact { font-size: 12px; color: #A8A8B8; margin-top: 1px; }
.m-partner-links { display: flex; gap: 8px; margin-top: 4px; flex-wrap: wrap; }
.m-partner-link {
    font-size: 11px; color: #8B5CF6; text-decoration: none;
    display: inline-flex; align-items: center; gap: 4px;
}
.m-partner-badge { font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600; flex-shrink: 0; }
.m-partner-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-partner-inactive { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-partner-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-partners">
    <div class="m-partners-header">
        <h2 class="m-partners-title">Business Partners</h2>
        <p class="m-partners-sub"><?= count($partners) ?> partner<?= count($partners) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($partners)): ?>
        <div class="m-empty-state">
            <i class="fas fa-handshake"></i>
            <p>No business partners found</p>
        </div>
    <?php else: ?>
        <?php foreach ($partners as $p):
            $status = strtolower($p['status'] ?? 'unknown');
            $badgeClass = match($status) {
                'active' => 'active',
                'inactive', 'terminated' => 'inactive',
                default => 'default',
            };
        ?>
        <div class="m-partner-card">
            <div class="m-partner-icon"><i class="fas fa-building"></i></div>
            <div class="m-partner-body">
                <div class="m-partner-company"><?= htmlspecialchars($p['company_name'] ?? '') ?></div>
                <?php if (!empty($p['contact_name'])): ?>
                <div class="m-partner-contact"><?= htmlspecialchars($p['contact_name']) ?></div>
                <?php endif; ?>
                <div class="m-partner-links">
                    <?php if (!empty($p['email'])): ?>
                    <a href="mailto:<?= htmlspecialchars($p['email']) ?>" class="m-partner-link"><i class="fas fa-envelope"></i> <?= htmlspecialchars($p['email']) ?></a>
                    <?php endif; ?>
                    <?php if (!empty($p['phone'])): ?>
                    <a href="tel:<?= htmlspecialchars($p['phone']) ?>" class="m-partner-link"><i class="fas fa-phone"></i> <?= htmlspecialchars($p['phone']) ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <span class="m-partner-badge m-partner-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
