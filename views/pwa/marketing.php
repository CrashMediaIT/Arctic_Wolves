<?php
/**
 * PWA Marketing - Mobile-native business cards / contacts list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$cards = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, title, company, email, phone FROM business_cards ORDER BY name ASC LIMIT 20");
    $stmt->execute();
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $cards = []; }
?>
<style>
.m-marketing { padding: 16px; font-family: Inter, sans-serif; }
.m-marketing-header { margin-bottom: 16px; }
.m-marketing-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-marketing-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-bcard {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-bcard-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 700; color: #fff; flex-shrink: 0;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
}
.m-bcard-body { flex: 1; min-width: 0; }
.m-bcard-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-bcard-detail { font-size: 12px; color: #A8A8B8; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-bcard-contact { display: flex; gap: 8px; margin-top: 6px; flex-wrap: wrap; }
.m-bcard-link {
    font-size: 11px; color: #8B5CF6; text-decoration: none;
    display: inline-flex; align-items: center; gap: 4px;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-marketing">
    <div class="m-marketing-header">
        <h2 class="m-marketing-title">Business Cards</h2>
        <p class="m-marketing-sub"><?= count($cards) ?> contact<?= count($cards) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($cards)): ?>
        <div class="m-empty-state">
            <i class="fas fa-address-card"></i>
            <p>No business cards found</p>
        </div>
    <?php else: ?>
        <?php foreach ($cards as $c):
            $initial = strtoupper(mb_substr($c['name'] ?? '?', 0, 1));
        ?>
        <div class="m-bcard">
            <div class="m-bcard-avatar"><?= $initial ?></div>
            <div class="m-bcard-body">
                <div class="m-bcard-name"><?= htmlspecialchars($c['name'] ?? '') ?></div>
                <?php if (!empty($c['title']) || !empty($c['company'])): ?>
                <div class="m-bcard-detail"><?= htmlspecialchars(trim(($c['title'] ?? '') . ' · ' . ($c['company'] ?? ''), ' ·')) ?></div>
                <?php endif; ?>
                <div class="m-bcard-contact">
                    <?php if (!empty($c['email'])): ?>
                    <a href="mailto:<?= htmlspecialchars($c['email']) ?>" class="m-bcard-link"><i class="fas fa-envelope"></i> <?= htmlspecialchars($c['email']) ?></a>
                    <?php endif; ?>
                    <?php if (!empty($c['phone'])): ?>
                    <a href="tel:<?= htmlspecialchars($c['phone']) ?>" class="m-bcard-link"><i class="fas fa-phone"></i> <?= htmlspecialchars($c['phone']) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
