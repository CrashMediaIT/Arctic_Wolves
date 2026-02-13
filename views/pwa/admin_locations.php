<?php
/**
 * PWA Admin Locations - Mobile-native locations list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$locations = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, address, city, capacity FROM locations WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $locations = []; }
?>
<style>
.m-locations { padding: 16px; font-family: Inter, sans-serif; }
.m-locations-header { margin-bottom: 16px; }
.m-locations-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-locations-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-loc-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-loc-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(59,130,246,0.15); color: #3B82F6; font-size: 16px; flex-shrink: 0;
}
.m-loc-body { flex: 1; min-width: 0; }
.m-loc-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-loc-addr { font-size: 12px; color: #A8A8B8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-loc-cap {
    font-size: 11px; color: #6B6B7B; margin-top: 4px;
    display: inline-flex; align-items: center; gap: 4px;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-locations">
    <div class="m-locations-header">
        <h2 class="m-locations-title">Locations</h2>
        <p class="m-locations-sub"><?= count($locations) ?> location<?= count($locations) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($locations)): ?>
        <div class="m-empty-state">
            <i class="fas fa-map-marker-alt"></i>
            <p>No locations found</p>
        </div>
    <?php else: ?>
        <?php foreach ($locations as $loc): ?>
        <div class="m-loc-card">
            <div class="m-loc-icon"><i class="fas fa-map-marker-alt"></i></div>
            <div class="m-loc-body">
                <div class="m-loc-name"><?= htmlspecialchars($loc['name'] ?? '') ?></div>
                <?php
                    $addrParts = array_filter([
                        $loc['address'] ?? '',
                        $loc['city'] ?? ''
                    ]);
                ?>
                <?php if (!empty($addrParts)): ?>
                <div class="m-loc-addr"><?= htmlspecialchars(implode(', ', $addrParts)) ?></div>
                <?php endif; ?>
                <?php if (!empty($loc['capacity'])): ?>
                <div class="m-loc-cap"><i class="fas fa-users"></i> Capacity: <?= (int)$loc['capacity'] ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
