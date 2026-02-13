<?php
/**
 * PWA Mileage Tracker - Mobile-native mileage tracking tool
 * Purpose-built for mobile phones.
 */

if (!$isAnyCoach) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Coach access required.</p>';
    echo '</div>';
    return;
}

$entries = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, trip_date, start_location, end_location, distance, purpose
        FROM mileage_entries
        WHERE user_id = ?
        ORDER BY trip_date DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $entries = []; }

$totalEntries = count($entries);
?>
<style>
.m-mileage { padding: 16px; font-family: Inter, sans-serif; }
.m-mileage-header { margin-bottom: 16px; }
.m-mileage-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-mileage-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-mileage-form {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; margin-bottom: 20px;
}
.m-mileage-form-title { font-size: 14px; font-weight: 600; color: #fff; margin: 0 0 12px; }
.m-form-group { margin-bottom: 12px; }
.m-form-label {
    font-size: 13px; font-weight: 600; color: #A8A8B8;
    display: block; margin-bottom: 6px;
}
.m-form-input {
    width: 100%; padding: 12px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box; min-height: 44px; outline: none;
}
.m-form-input::placeholder { color: #6B6B7B; }
.m-form-input:focus { border-color: #6B46C1; }
.m-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.m-form-btn {
    display: block; width: 100%; padding: 12px; border: none; border-radius: 10px;
    background: #6B46C1; color: #fff; font-size: 14px; font-weight: 600;
    font-family: Inter, sans-serif; cursor: pointer; min-height: 44px; text-align: center;
}
.m-form-btn:active { background: #8B5CF6; }
.m-section-title {
    font-size: 13px; font-weight: 600; color: #6B6B7B;
    text-transform: uppercase; letter-spacing: 0.5px;
    margin: 0 0 10px; padding: 0 4px;
}
.m-mileage-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
}
.m-mileage-card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; }
.m-mileage-card-route { font-size: 14px; font-weight: 600; color: #fff; flex: 1; }
.m-mileage-card-dist { font-size: 14px; font-weight: 700; color: #8B5CF6; flex-shrink: 0; }
.m-mileage-card-meta { font-size: 12px; color: #A8A8B8; display: flex; gap: 8px; flex-wrap: wrap; }
.m-empty-state { text-align: center; padding: 24px; color: #6B6B7B; font-size: 13px; }
</style>

<div class="m-mileage">
    <div class="m-mileage-header">
        <h2 class="m-mileage-title">Mileage Tracker</h2>
        <p class="m-mileage-sub"><?= $totalEntries ?> entr<?= $totalEntries !== 1 ? 'ies' : 'y' ?></p>
    </div>

    <div class="m-mileage-form">
        <h3 class="m-mileage-form-title">New Entry</h3>
        <form method="post" action="process_mileage.php">
            <div class="m-form-group">
                <label class="m-form-label">Date</label>
                <input type="date" name="trip_date" class="m-form-input" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="m-form-row">
                <div class="m-form-group">
                    <label class="m-form-label">Start Location</label>
                    <input type="text" name="start_location" class="m-form-input" placeholder="From..." required>
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">End Location</label>
                    <input type="text" name="end_location" class="m-form-input" placeholder="To..." required>
                </div>
            </div>
            <div class="m-form-row">
                <div class="m-form-group">
                    <label class="m-form-label">Distance (km)</label>
                    <input type="number" name="distance" class="m-form-input" placeholder="0.0" step="0.1" min="0" required>
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Purpose</label>
                    <input type="text" name="purpose" class="m-form-input" placeholder="e.g. Game">
                </div>
            </div>
            <button type="submit" class="m-form-btn">Add Entry</button>
        </form>
    </div>

    <h3 class="m-section-title">Recent Entries</h3>
    <?php if (empty($entries)): ?>
        <div class="m-empty-state"><i class="fas fa-road" style="font-size:24px;display:block;margin-bottom:8px;"></i>No mileage entries yet</div>
    <?php else: ?>
        <?php foreach ($entries as $e):
            $route = htmlspecialchars(($e['start_location'] ?? '?') . ' → ' . ($e['end_location'] ?? '?'));
        ?>
        <div class="m-mileage-card">
            <div class="m-mileage-card-top">
                <span class="m-mileage-card-route"><?= $route ?></span>
                <span class="m-mileage-card-dist"><?= number_format((float)($e['distance'] ?? 0), 1) ?> km</span>
            </div>
            <div class="m-mileage-card-meta">
                <?php if (!empty($e['trip_date'])): ?>
                <span><i class="fas fa-calendar" style="font-size:10px;"></i> <?= date('M j', strtotime($e['trip_date'])) ?></span>
                <?php endif; ?>
                <?php if (!empty($e['purpose'])): ?>
                <span><i class="fas fa-tag" style="font-size:10px;"></i> <?= htmlspecialchars($e['purpose']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
