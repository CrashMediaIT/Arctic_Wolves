<?php
/**
 * PWA Travel - Mobile-native travel & mileage tracker for coaches
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAnyCoach):
?>
<style>
.m-denied { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; color: #6B6B7B; font-family: Inter, sans-serif; text-align: center; }
.m-denied i { font-size: 48px; margin-bottom: 16px; }
.m-denied p { font-size: 15px; margin: 0; }
</style>
<div class="m-denied">
    <i class="fas fa-lock"></i>
    <p>Access denied</p>
</div>
<?php
    return;
endif;

$trips = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, destination, purpose, departure_date, return_date, status
        FROM travel_requests
        WHERE user_id = ?
        ORDER BY departure_date DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $trips = []; }

$mileage = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, trip_date, start_location, end_location, distance_km, purpose
        FROM mileage_entries
        WHERE user_id = ?
        ORDER BY trip_date DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $mileage = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $mileage = []; }
?>
<style>
.m-travel { padding: 0; font-family: Inter, sans-serif; }
.m-tabs {
    display: flex; position: sticky; top: 0; z-index: 10;
    background: #0A0A0F; border-bottom: 1px solid #2D2D3F;
    padding: 0 16px;
}
.m-tab {
    flex: 1; text-align: center; padding: 14px 0; font-size: 13px; font-weight: 600;
    color: #6B6B7B; border: none; background: none; cursor: pointer;
    border-bottom: 2px solid transparent;
    min-height: 44px; font-family: Inter, sans-serif;
}
.m-tab.m-tab-active { color: #8B5CF6; border-bottom-color: #8B5CF6; }
.m-tab-panel { display: none; padding: 16px; }
.m-tab-panel.m-tab-visible { display: block; }
.m-trip-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px; min-height: 44px;
}
.m-trip-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-trip-dest { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-trip-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-trip-badge-approved { background: rgba(16,185,129,0.15); color: #10B981; }
.m-trip-badge-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-trip-badge-rejected { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-trip-badge-completed { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-trip-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-trip-purpose { font-size: 12px; color: #A8A8B8; margin: 0 0 8px; }
.m-trip-dates { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-mile-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px; min-height: 44px;
}
.m-mile-icon {
    width: 44px; height: 44px; border-radius: 10px;
    background: rgba(16,185,129,0.12);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: #10B981; flex-shrink: 0;
}
.m-mile-body { flex: 1; min-width: 0; }
.m-mile-route { font-size: 13px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-mile-meta { font-size: 12px; color: #A8A8B8; margin-top: 3px; }
.m-mile-dist {
    font-size: 14px; font-weight: 700; color: #10B981; white-space: nowrap; flex-shrink: 0;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-travel">
    <div class="m-tabs">
        <button class="m-tab m-tab-active" onclick="mTravelTab('trips', this)" type="button">Trips</button>
        <button class="m-tab" onclick="mTravelTab('mileage', this)" type="button">Mileage</button>
    </div>

    <!-- Trips Tab -->
    <div class="m-tab-panel m-tab-visible" id="m-panel-trips">
        <?php if (empty($trips)): ?>
            <div class="m-empty-state">
                <i class="fas fa-plane"></i>
                <p>No travel requests</p>
            </div>
        <?php else: ?>
            <?php foreach ($trips as $t):
                $status = strtolower($t['status'] ?? 'pending');
                $badgeClass = match($status) {
                    'approved' => 'approved',
                    'pending' => 'pending',
                    'rejected', 'denied' => 'rejected',
                    'completed' => 'completed',
                    default => 'default',
                };
            ?>
            <div class="m-trip-card">
                <div class="m-trip-top">
                    <span class="m-trip-dest"><?= htmlspecialchars($t['destination'] ?? 'Unknown') ?></span>
                    <span class="m-trip-badge m-trip-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                </div>
                <?php if (!empty($t['purpose'])): ?>
                <p class="m-trip-purpose"><?= htmlspecialchars($t['purpose']) ?></p>
                <?php endif; ?>
                <div class="m-trip-dates">
                    <i class="fas fa-calendar"></i>
                    <?= date('M j', strtotime($t['departure_date'])) ?>
                    <?php if (!empty($t['return_date'])): ?>
                    → <?= date('M j, Y', strtotime($t['return_date'])) ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Mileage Tab -->
    <div class="m-tab-panel" id="m-panel-mileage">
        <?php if (empty($mileage)): ?>
            <div class="m-empty-state">
                <i class="fas fa-road"></i>
                <p>No mileage entries</p>
            </div>
        <?php else: ?>
            <?php foreach ($mileage as $m): ?>
            <div class="m-mile-card">
                <div class="m-mile-icon"><i class="fas fa-car"></i></div>
                <div class="m-mile-body">
                    <div class="m-mile-route">
                        <?= htmlspecialchars($m['start_location'] ?? '') ?> → <?= htmlspecialchars($m['end_location'] ?? '') ?>
                    </div>
                    <div class="m-mile-meta">
                        <i class="fas fa-calendar" style="font-size:10px;"></i>
                        <?= date('M j, Y', strtotime($m['trip_date'])) ?>
                        <?php if (!empty($m['purpose'])): ?> · <?= htmlspecialchars($m['purpose']) ?><?php endif; ?>
                    </div>
                </div>
                <span class="m-mile-dist"><?= number_format((float)$m['distance_km'], 1) ?> km</span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function mTravelTab(tabId, btn) {
    document.querySelectorAll('.m-tab-panel').forEach(function(p) { p.classList.remove('m-tab-visible'); });
    document.querySelectorAll('.m-tab').forEach(function(t) { t.classList.remove('m-tab-active'); });
    var panel = document.getElementById('m-panel-' + tabId);
    if (panel) panel.classList.add('m-tab-visible');
    if (btn) btn.classList.add('m-tab-active');
}
</script>
