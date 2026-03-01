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
.m-segment-control {
    display: flex; background: #1E1E2E; border-radius: 12px; padding: 4px;
    margin: 0 16px 16px; position: relative; border: 1px solid #2D2D3F;
}
.m-segment {
    flex: 1; padding: 10px 12px; border: none; background: transparent;
    color: #A8A8B8; font-size: 13px; font-weight: 600; font-family: inherit;
    cursor: pointer; border-radius: 10px; display: flex; align-items: center;
    justify-content: center; gap: 6px; z-index: 1; transition: color 0.2s;
    min-height: 44px; -webkit-tap-highlight-color: transparent;
}
.m-segment i { font-size: 14px; }
.m-segment-active {
    color: #fff; background: #6B46C1;
    box-shadow: 0 2px 8px rgba(107,70,193,0.3);
}
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
.m-fab {
    position: fixed; bottom: 60px; right: 16px; z-index: 50;
    width: 56px; height: 56px; border-radius: 50%;
    background: #6B46C1; color: #fff; border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; box-shadow: 0 4px 12px rgba(107,70,193,0.4);
    min-height: 44px; font-family: Inter, sans-serif;
}
.m-overlay {
    display: none; position: fixed; inset: 0; z-index: 100;
    background: rgba(0,0,0,0.6);
}
.m-overlay.m-overlay-open { display: flex; align-items: flex-end; }
.m-sheet {
    width: 100%; max-height: 90vh; overflow-y: auto;
    background: #16161F; border: 1px solid #2D2D3F;
    border-radius: 16px 16px 0 0; padding: 20px 16px 32px;
}
.m-sheet-handle {
    width: 36px; height: 4px; background: #2D2D3F; border-radius: 2px;
    margin: 0 auto 16px;
}
.m-sheet-title {
    font-size: 16px; font-weight: 700; color: #fff; margin: 0 0 16px;
    font-family: Inter, sans-serif;
}
.m-form-group { margin-bottom: 14px; }
.m-form-label {
    display: block; font-size: 12px; font-weight: 600; color: #A8A8B8;
    margin-bottom: 6px; font-family: Inter, sans-serif;
}
.m-form-input {
    width: 100%; box-sizing: border-box;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; padding: 12px; min-height: 44px;
    font-size: 14px; font-family: Inter, sans-serif;
    -webkit-appearance: none;
}
.m-form-input:focus { outline: none; border-color: #6B46C1; }
.m-form-submit {
    width: 100%; background: #6B46C1; color: #fff; border: none;
    border-radius: 10px; min-height: 44px; font-size: 14px;
    font-weight: 600; cursor: pointer; margin-top: 8px;
    font-family: Inter, sans-serif;
}
.m-delete-btn {
    background: none; border: none; color: #6B6B7B; cursor: pointer;
    padding: 6px; min-height: 44px; min-width: 44px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
}
.m-delete-btn:hover { color: #EF4444; }
.m-confirm-actions { display: flex; gap: 10px; margin-top: 16px; }
.m-confirm-actions button {
    flex: 1; min-height: 44px; border-radius: 10px; border: none;
    font-size: 14px; font-weight: 600; cursor: pointer;
    font-family: Inter, sans-serif;
}
.m-btn-cancel { background: #2D2D3F; color: #A8A8B8; }
.m-btn-danger { background: #EF4444; color: #fff; }
.m-add-mileage-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; background: #6B46C1; color: #fff; border: none;
    border-radius: 10px; min-height: 44px; font-size: 14px;
    font-weight: 600; cursor: pointer; margin-bottom: 12px;
    font-family: Inter, sans-serif;
}
</style>

<div class="m-travel">
    <div class="m-segment-control">
        <button class="m-segment m-segment-active" data-panel="trips" aria-pressed="true">
            <i class="fas fa-plane"></i> Trips
        </button>
        <button class="m-segment" data-panel="mileage" aria-pressed="false">
            <i class="fas fa-road"></i> Mileage
        </button>
        <div class="m-segment-slider"></div>
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
                    <button type="button" class="m-delete-btn" onclick="mOpenDelete('trip', <?= (int)$t['id'] ?>)" aria-label="Delete trip"><i class="fas fa-trash"></i></button>
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
        <button type="button" class="m-add-mileage-btn" onclick="mOpenSheet('mileage')"><i class="fas fa-plus"></i> Add Mileage Entry</button>
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
                <button type="button" class="m-delete-btn" onclick="mOpenDelete('mileage', <?= (int)$m['id'] ?>)" aria-label="Delete mileage entry"><i class="fas fa-trash"></i></button>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <!-- FAB: Add Trip -->
    <button type="button" class="m-fab" onclick="mOpenSheet('trip')" aria-label="Add trip"><i class="fas fa-plus"></i></button>

    <!-- Trip Bottom Sheet -->
    <div class="m-overlay" id="m-sheet-trip">
        <div class="m-sheet">
            <div class="m-sheet-handle"></div>
            <h2 class="m-sheet-title">Add Trip</h2>
            <form method="POST" action="process_mileage.php">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="create">
                <div class="m-form-group">
                    <label class="m-form-label">Destination *</label>
                    <input type="text" name="destination" class="m-form-input" required placeholder="e.g., Toronto Arena">
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Purpose *</label>
                    <select name="purpose" class="m-form-input" required>
                        <option value="">Select purpose</option>
                        <option>Training Session</option>
                        <option>Team Practice</option>
                        <option>Game/Tournament</option>
                        <option>Meeting</option>
                        <option>Other</option>
                    </select>
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Departure Date *</label>
                    <input type="date" name="departure_date" class="m-form-input" required value="<?= date('Y-m-d') ?>" id="m-departure-date">
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Return Date</label>
                    <input type="date" name="return_date" class="m-form-input" id="m-return-date">
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Description</label>
                    <textarea name="description" class="m-form-input" rows="3" placeholder="Trip details (optional)" style="min-height:66px;resize:vertical;"></textarea>
                </div>
                <button type="submit" class="m-form-submit"><i class="fas fa-paper-plane"></i> Submit Trip</button>
            </form>
            <button type="button" class="m-form-submit m-btn-cancel" style="margin-top:8px;" onclick="mCloseSheet('trip')">Cancel</button>
        </div>
    </div>

    <!-- Mileage Bottom Sheet -->
    <div class="m-overlay" id="m-sheet-mileage">
        <div class="m-sheet">
            <div class="m-sheet-handle"></div>
            <h2 class="m-sheet-title">Add Mileage Entry</h2>
            <form method="POST" action="process_mileage.php">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="create">
                <div class="m-form-group">
                    <label class="m-form-label">Date *</label>
                    <input type="date" name="trip_date" class="m-form-input" required value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Start Location *</label>
                    <input type="text" name="from_location" class="m-form-input" required placeholder="e.g., Home">
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">End Location *</label>
                    <input type="text" name="to_location" class="m-form-input" required placeholder="e.g., Arena">
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Distance (km) *</label>
                    <input type="number" name="distance_km" class="m-form-input" required step="0.1" min="0" placeholder="0.0">
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Purpose *</label>
                    <select name="purpose" class="m-form-input" required>
                        <option value="">Select purpose</option>
                        <option>Training Session</option>
                        <option>Team Practice</option>
                        <option>Game/Tournament</option>
                        <option>Meeting</option>
                        <option>Other</option>
                    </select>
                </div>
                <button type="submit" class="m-form-submit"><i class="fas fa-plus"></i> Add Entry</button>
            </form>
            <button type="button" class="m-form-submit m-btn-cancel" style="margin-top:8px;" onclick="mCloseSheet('mileage')">Cancel</button>
        </div>
    </div>

    <!-- Delete Confirmation -->
    <div class="m-overlay" id="m-sheet-delete">
        <div class="m-sheet" style="padding-bottom:24px;">
            <div class="m-sheet-handle"></div>
            <h2 class="m-sheet-title">Delete Entry</h2>
            <p style="color:#A8A8B8;font-size:14px;margin:0 0 8px;">Are you sure you want to delete this entry? This cannot be undone.</p>
            <form method="POST" action="process_mileage.php" id="m-delete-form">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="log_id" id="m-delete-id" value="">
                <div class="m-confirm-actions">
                    <button type="button" class="m-btn-cancel" onclick="mCloseSheet('delete')">Cancel</button>
                    <button type="submit" class="m-btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.m-segment-control .m-segment').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var control = this.closest('.m-segment-control');
        control.querySelectorAll('.m-segment').forEach(function(s) {
            s.classList.remove('m-segment-active');
            s.setAttribute('aria-pressed', 'false');
        });
        this.classList.add('m-segment-active');
        this.setAttribute('aria-pressed', 'true');
        var panelId = this.getAttribute('data-panel');
        document.querySelectorAll('.m-tab-panel').forEach(function(p) { p.classList.remove('m-tab-visible'); });
        var target = document.getElementById('m-panel-' + panelId);
        if (target) target.classList.add('m-tab-visible');
    });
});
function mOpenSheet(name) {
    var el = document.getElementById('m-sheet-' + name);
    if (el) el.classList.add('m-overlay-open');
}
function mCloseSheet(name) {
    var el = document.getElementById('m-sheet-' + name);
    if (el) el.classList.remove('m-overlay-open');
}
function mOpenDelete(type, id) {
    document.getElementById('m-delete-id').value = id;
    mOpenSheet('delete');
}
document.querySelectorAll('.m-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            overlay.classList.remove('m-overlay-open');
        }
    });
});
var depDate = document.getElementById('m-departure-date');
var retDate = document.getElementById('m-return-date');
if (depDate && retDate) {
    retDate.min = depDate.value;
    depDate.addEventListener('change', function() {
        retDate.min = depDate.value;
        if (retDate.value && retDate.value < depDate.value) retDate.value = depDate.value;
    });
}
</script>
