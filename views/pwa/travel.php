<?php
/**
 * PWA Travel - Mobile-native mileage tracker for coaches
 * Single unified view matching desktop travel_mileage.php functionality.
 * Queries mileage_logs + mileage_stops tables.
 */

if (!$isAnyCoach && !$isAdmin):
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

// Fetch athletes for the assignment dropdown
$athletes = [];
try {
    $stmt = $pdo->query("SELECT u.id, u.first_name, u.last_name FROM users u WHERE u.is_active = 1 ORDER BY u.last_name, u.first_name");
    $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $athletes = decryptUserRows($athletes);
} catch (Exception $e) { $athletes = []; }

// Get Google Maps API key for distance calculation
$google_maps_api_key = '';
try {
    $apiStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'google_maps_api_key'");
    $google_maps_api_key = $apiStmt->fetchColumn() ?: '';
} catch (Exception $e) { /* no key */ }

// Summary stats for the current month
$monthTotalKm = 0;
$monthReimbursement = 0;
$monthTripCount = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as trip_count,
               COALESCE(SUM(total_distance_km), 0) as total_km,
               COALESCE(SUM(reimbursement_amount), 0) as total_reimbursement
        FROM mileage_logs
        WHERE user_id = ? AND MONTH(trip_date) = MONTH(CURDATE()) AND YEAR(trip_date) = YEAR(CURDATE())
    ");
    $stmt->execute([$user_id]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    $monthTotalKm = floatval($summary['total_km']);
    $monthReimbursement = floatval($summary['total_reimbursement']);
    $monthTripCount = intval($summary['trip_count']);
} catch (Exception $e) { /* defaults remain 0 */ }

// Fetch mileage log entries with from/to locations from mileage_stops
$entries = [];
try {
    $stmt = $pdo->prepare("
        SELECT m.id, m.trip_date, m.title, m.purpose, m.description,
               m.total_distance_km, m.reimbursement_amount, m.is_reimbursed,
               m.athlete_id,
               start_stop.address AS from_location,
               end_stop.address AS to_location,
               a.first_name AS athlete_first_name, a.last_name AS athlete_last_name
        FROM mileage_logs m
        LEFT JOIN mileage_stops start_stop ON m.id = start_stop.mileage_log_id AND start_stop.stop_order = 0
        LEFT JOIN (
            SELECT ms.mileage_log_id, ms.address
            FROM mileage_stops ms
            INNER JOIN (
                SELECT mileage_log_id, MAX(stop_order) AS max_order
                FROM mileage_stops GROUP BY mileage_log_id
            ) mx ON ms.mileage_log_id = mx.mileage_log_id AND ms.stop_order = mx.max_order
        ) end_stop ON m.id = end_stop.mileage_log_id
        LEFT JOIN users a ON m.athlete_id = a.id
        WHERE m.user_id = ?
        ORDER BY m.trip_date DESC, m.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Decrypt athlete names
    foreach ($entries as &$entry) {
        if (!empty($entry['athlete_first_name']) || !empty($entry['athlete_last_name'])) {
            $decrypted = decryptUserRows([['first_name' => $entry['athlete_first_name'], 'last_name' => $entry['athlete_last_name']]]);
            $entry['athlete_first_name'] = $decrypted[0]['first_name'] ?? '';
            $entry['athlete_last_name'] = $decrypted[0]['last_name'] ?? '';
        }
    }
    unset($entry);
} catch (Exception $e) { $entries = []; }

$csrfField = csrfTokenInput();
$csrfToken = $_SESSION['csrf_token'] ?? '';
?>
<style>
.m-travel { padding: 0 0 80px; font-family: Inter, sans-serif; }

/* Summary cards */
.m-stats-row {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
    padding: 0 16px 16px;
}
.m-stat-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px 12px; text-align: center;
}
.m-stat-value {
    font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 4px;
    line-height: 1.2;
}
.m-stat-value-purple { color: #8B5CF6; }
.m-stat-value-green { color: #10B981; }
.m-stat-label {
    font-size: 10px; font-weight: 600; color: #6B6B7B;
    text-transform: uppercase; letter-spacing: 0.5px;
}

/* Add trip button */
.m-add-trip-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: calc(100% - 32px); margin: 0 16px 16px; background: #6B46C1;
    color: #fff; border: none; border-radius: 12px; min-height: 48px;
    font-size: 15px; font-weight: 600; cursor: pointer;
    font-family: Inter, sans-serif; -webkit-tap-highlight-color: transparent;
}
.m-add-trip-btn:active { background: #5B38B0; }

/* Section header */
.m-section-header {
    font-size: 14px; font-weight: 700; color: #A8A8B8; padding: 0 16px 10px;
    text-transform: uppercase; letter-spacing: 0.5px;
}

/* Entry cards */
.m-entry-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin: 0 16px 10px;
}
.m-entry-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: 8px;
}
.m-entry-title { font-size: 15px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-entry-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-badge-reimbursed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-badge-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-entry-meta {
    display: flex; flex-wrap: wrap; gap: 6px 14px;
    font-size: 12px; color: #A8A8B8; margin-bottom: 8px;
}
.m-entry-meta i { font-size: 11px; margin-right: 3px; color: #6B6B7B; }
.m-entry-route {
    display: flex; align-items: center; gap: 6px; font-size: 13px;
    color: #8B5CF6; margin-bottom: 8px;
}
.m-entry-route i { font-size: 11px; color: #6B6B7B; }
.m-entry-footer {
    display: flex; justify-content: space-between; align-items: center;
    border-top: 1px solid #2D2D3F; padding-top: 10px; margin-top: 4px;
}
.m-entry-amount { font-size: 16px; font-weight: 700; color: #10B981; }
.m-entry-distance { font-size: 13px; font-weight: 600; color: #A8A8B8; }
.m-entry-actions { display: flex; gap: 4px; }

/* Empty state */
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 36px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }

/* Bottom sheet overlay */
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

/* Form elements */
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
.m-form-input:focus { outline: none; border-color: #8B5CF6; }
.m-form-submit {
    width: 100%; background: #6B46C1; color: #fff; border: none;
    border-radius: 10px; min-height: 48px; font-size: 15px;
    font-weight: 600; cursor: pointer; margin-top: 8px;
    font-family: Inter, sans-serif;
}
.m-form-submit:active { background: #5B38B0; }
.m-form-row {
    display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
}

/* Delete button */
.m-delete-btn {
    background: none; border: none; color: #6B6B7B; cursor: pointer;
    padding: 6px; min-height: 44px; min-width: 44px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
    -webkit-tap-highlight-color: transparent;
}
.m-delete-btn:active { color: #EF4444; }

/* Confirm dialog buttons */
.m-confirm-actions { display: flex; gap: 10px; margin-top: 16px; }
.m-confirm-actions button {
    flex: 1; min-height: 44px; border-radius: 10px; border: none;
    font-size: 14px; font-weight: 600; cursor: pointer;
    font-family: Inter, sans-serif;
}
.m-btn-cancel { background: #2D2D3F; color: #A8A8B8; }
.m-btn-cancel:active { background: #3D3D4F; }
.m-btn-danger { background: #EF4444; color: #fff; }
.m-btn-danger:active { background: #DC2626; }

/* Success banner */
.m-success-banner {
    display: flex; align-items: center; gap: 8px; margin: 0 16px 12px;
    background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.25);
    border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #10B981;
}
.m-success-banner i { flex-shrink: 0; }

/* Stop rows */
.m-stops-section {
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    padding: 12px; margin-bottom: 14px;
}
.m-stops-title {
    font-size: 12px; font-weight: 600; color: #A8A8B8;
    margin-bottom: 10px; display: flex; align-items: center; gap: 6px;
}
.m-stop-row {
    display: flex; gap: 8px; align-items: flex-start; margin-bottom: 10px;
    position: relative;
}
.m-stop-row .m-stop-num {
    width: 28px; height: 28px; border-radius: 50%;
    background: rgba(107,70,193,0.2); color: #8B5CF6;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; flex-shrink: 0; margin-top: 8px;
}
.m-stop-row .m-stop-fields { flex: 1; min-width: 0; }
.m-stop-row .m-stop-remove {
    width: 28px; height: 28px; border-radius: 50%; border: none;
    background: rgba(239,68,68,0.15); color: #EF4444;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; cursor: pointer; flex-shrink: 0; margin-top: 8px;
}
.m-stop-row .m-stop-remove:active { background: rgba(239,68,68,0.3); }
.m-add-stop-btn {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    width: 100%; padding: 10px; border: 1px dashed #2D2D3F;
    border-radius: 8px; background: transparent; color: #8B5CF6;
    font-size: 12px; font-weight: 600; cursor: pointer; margin-top: 4px;
    font-family: Inter, sans-serif;
}
.m-add-stop-btn:active { background: rgba(107,70,193,0.08); }
.m-calc-btn {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    width: 100%; padding: 10px; border: none; border-radius: 8px;
    background: rgba(59,130,246,0.15); color: #3B82F6;
    font-size: 13px; font-weight: 600; cursor: pointer; margin-bottom: 8px;
    font-family: Inter, sans-serif; min-height: 44px;
}
.m-calc-btn:active { background: rgba(59,130,246,0.25); }
.m-calc-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.m-calc-status {
    font-size: 11px; text-align: center; margin-bottom: 8px;
    min-height: 16px;
}
</style>

<div class="m-travel">
    <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <div class="m-success-banner" id="m-success-msg">
        <i class="fas fa-check-circle"></i>
        <span><?= htmlspecialchars($_GET['message'] ?? 'Success') ?></span>
    </div>
    <?php endif; ?>

    <!-- Summary Stats -->
    <div class="m-stats-row">
        <div class="m-stat-card">
            <div class="m-stat-value"><?= number_format($monthTotalKm, 1) ?></div>
            <div class="m-stat-label">KM This Month</div>
        </div>
        <div class="m-stat-card">
            <div class="m-stat-value m-stat-value-green">$<?= number_format($monthReimbursement, 2) ?></div>
            <div class="m-stat-label">Est. Amount</div>
        </div>
        <div class="m-stat-card">
            <div class="m-stat-value m-stat-value-purple"><?= $monthTripCount ?></div>
            <div class="m-stat-label">Trips</div>
        </div>
    </div>

    <!-- Add Trip Button -->
    <button type="button" class="m-add-trip-btn" onclick="mOpenSheet('add')">
        <i class="fas fa-plus"></i> Add Trip
    </button>

    <!-- Mileage Log Section -->
    <div class="m-section-header">Mileage Log</div>

    <?php if (empty($entries)): ?>
        <div class="m-empty-state">
            <i class="fas fa-car"></i>
            <p>No mileage entries yet.<br>Tap "Add Trip" to log your first trip.</p>
        </div>
    <?php else: ?>
        <?php foreach ($entries as $entry):
            $isReimbursed = !empty($entry['is_reimbursed']);
            $hasAthlete = !empty($entry['athlete_first_name']) || !empty($entry['athlete_last_name']);
            $athleteName = trim(($entry['athlete_first_name'] ?? '') . ' ' . ($entry['athlete_last_name'] ?? ''));
            $fromLoc = $entry['from_location'] ?? '';
            $toLoc = $entry['to_location'] ?? '';
        ?>
        <div class="m-entry-card" id="m-entry-<?= (int)$entry['id'] ?>">
            <div class="m-entry-header">
                <span class="m-entry-title"><?= htmlspecialchars($entry['title'] ?: $entry['purpose'] ?: 'Trip') ?></span>
                <span class="m-entry-badge <?= $isReimbursed ? 'm-badge-reimbursed' : 'm-badge-pending' ?>">
                    <?= $isReimbursed ? 'Reimbursed' : 'Pending' ?>
                </span>
            </div>

            <div class="m-entry-meta">
                <span><i class="fas fa-calendar"></i><?= date('M j, Y', strtotime($entry['trip_date'])) ?></span>
                <?php if (!empty($entry['purpose'])): ?>
                <span><i class="fas fa-tag"></i><?= htmlspecialchars($entry['purpose']) ?></span>
                <?php endif; ?>
                <?php if ($hasAthlete): ?>
                <span><i class="fas fa-user"></i><?= htmlspecialchars($athleteName) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($fromLoc || $toLoc): ?>
            <div class="m-entry-route">
                <span><?= htmlspecialchars($fromLoc ?: '—') ?></span>
                <i class="fas fa-arrow-right"></i>
                <span><?= htmlspecialchars($toLoc ?: '—') ?></span>
            </div>
            <?php endif; ?>

            <div class="m-entry-footer">
                <div>
                    <div class="m-entry-amount">$<?= number_format(floatval($entry['reimbursement_amount'] ?? 0), 2) ?></div>
                    <div class="m-entry-distance"><?= number_format(floatval($entry['total_distance_km'] ?? 0), 1) ?> km</div>
                </div>
                <div class="m-entry-actions">
                    <button type="button" class="m-delete-btn" onclick="mOpenDelete(<?= (int)$entry['id'] ?>)" aria-label="Delete entry">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Add Trip Bottom Sheet -->
    <div class="m-overlay" id="m-sheet-add">
        <div class="m-sheet">
            <div class="m-sheet-handle"></div>
            <h2 class="m-sheet-title">Add Trip</h2>
            <form method="POST" action="process_mileage.php" id="m-add-form">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="pwa_context" value="1">
                <input type="hidden" name="waypoints" id="m-waypoints-data" value="">

                <div class="m-form-group">
                    <label class="m-form-label">Title *</label>
                    <input type="text" name="title" class="m-form-input" required placeholder="e.g., Arena Practice Run">
                </div>

                <div class="m-form-row">
                    <div class="m-form-group">
                        <label class="m-form-label">Trip Date *</label>
                        <input type="date" name="trip_date" class="m-form-input" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="m-form-group">
                        <label class="m-form-label">Purpose *</label>
                        <select name="purpose" class="m-form-input" required>
                            <option value="">Select</option>
                            <option value="Training Session">Training Session</option>
                            <option value="Team Practice">Team Practice</option>
                            <option value="Game/Tournament">Game/Tournament</option>
                            <option value="Meeting">Meeting</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="m-form-group">
                    <label class="m-form-label">Description</label>
                    <textarea name="description" class="m-form-input" rows="2" placeholder="Optional notes" style="min-height:60px;resize:vertical;"></textarea>
                </div>

                <div class="m-form-group">
                    <label class="m-form-label">Assign to Athlete</label>
                    <select name="athlete_id" class="m-form-input">
                        <option value="0">— None —</option>
                        <?php foreach ($athletes as $ath): ?>
                        <option value="<?= (int)$ath['id'] ?>"><?= htmlspecialchars(($ath['first_name'] ?? '') . ' ' . ($ath['last_name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Multi-stop locations -->
                <div class="m-stops-section">
                    <div class="m-stops-title"><i class="fas fa-map-marker-alt"></i> Trip Stops</div>
                    <div id="m-stops-container">
                        <div class="m-stop-row" data-stop-index="0">
                            <div class="m-stop-num">1</div>
                            <div class="m-stop-fields">
                                <input type="text" class="m-form-input m-stop-address" data-index="0" required placeholder="Start location" style="margin-bottom:4px;">
                                <input type="text" class="m-form-input m-stop-name" data-index="0" placeholder="Name (e.g., Home)" value="Start" style="font-size:12px;min-height:36px;padding:8px 12px;">
                            </div>
                        </div>
                        <div class="m-stop-row" data-stop-index="1">
                            <div class="m-stop-num">2</div>
                            <div class="m-stop-fields">
                                <input type="text" class="m-form-input m-stop-address" data-index="1" required placeholder="End location" style="margin-bottom:4px;">
                                <input type="text" class="m-form-input m-stop-name" data-index="1" placeholder="Name (e.g., Arena)" value="End" style="font-size:12px;min-height:36px;padding:8px 12px;">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="m-add-stop-btn" id="m-add-stop-btn"><i class="fas fa-plus"></i> Add Stop</button>
                </div>

                <!-- Distance calculation -->
                <?php if (!empty($google_maps_api_key)): ?>
                <button type="button" class="m-calc-btn" id="m-calc-distance-btn"><i class="fas fa-route"></i> Calculate Distance</button>
                <div class="m-calc-status" id="m-calc-status"></div>
                <?php endif; ?>

                <div class="m-form-group">
                    <label class="m-form-label">Distance (km) *</label>
                    <input type="number" name="distance_km" id="m-distance-field" class="m-form-input" required step="0.1" min="0.1" placeholder="0.0">
                </div>

                <button type="submit" class="m-form-submit"><i class="fas fa-check"></i> Save Trip</button>
            </form>
            <button type="button" class="m-form-submit m-btn-cancel" style="margin-top:8px;" onclick="mCloseSheet('add')">Cancel</button>
        </div>
    </div>

    <!-- Delete Confirmation -->
    <div class="m-overlay" id="m-sheet-delete">
        <div class="m-sheet" style="padding-bottom:24px;">
            <div class="m-sheet-handle"></div>
            <h2 class="m-sheet-title">Delete Entry</h2>
            <p style="color:#A8A8B8;font-size:14px;margin:0 0 8px;">Are you sure you want to delete this mileage entry? This cannot be undone.</p>
            <div class="m-confirm-actions">
                <button type="button" class="m-btn-cancel" onclick="mCloseSheet('delete')">Cancel</button>
                <button type="button" class="m-btn-danger" id="m-confirm-delete-btn">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var deleteId = null;
    var stopIndex = 2; // Start and End are 0, 1

    window.mOpenSheet = function(name) {
        var el = document.getElementById('m-sheet-' + name);
        if (el) el.classList.add('m-overlay-open');
    };

    window.mCloseSheet = function(name) {
        var el = document.getElementById('m-sheet-' + name);
        if (el) el.classList.remove('m-overlay-open');
    };

    window.mOpenDelete = function(id) {
        deleteId = id;
        mOpenSheet('delete');
    };

    // Close overlay on backdrop click
    document.querySelectorAll('.m-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) overlay.classList.remove('m-overlay-open');
        });
    });

    // Add Stop button
    var addStopBtn = document.getElementById('m-add-stop-btn');
    if (addStopBtn) {
        addStopBtn.addEventListener('click', function() {
            var container = document.getElementById('m-stops-container');
            var endRow = container.lastElementChild;
            var newRow = document.createElement('div');
            newRow.className = 'm-stop-row';
            newRow.dataset.stopIndex = stopIndex;
            newRow.innerHTML = '<div class="m-stop-num">' + (stopIndex + 1) + '</div>'
                + '<div class="m-stop-fields">'
                + '<input type="text" class="m-form-input m-stop-address" data-index="' + stopIndex + '" required placeholder="Stop location" style="margin-bottom:4px;">'
                + '<input type="text" class="m-form-input m-stop-name" data-index="' + stopIndex + '" placeholder="Stop name" value="Stop ' + (stopIndex) + '" style="font-size:12px;min-height:36px;padding:8px 12px;">'
                + '</div>'
                + '<button type="button" class="m-stop-remove" onclick="mRemoveStop(this)" aria-label="Remove stop"><i class="fas fa-times"></i></button>';
            // Insert before the last (End) row
            container.insertBefore(newRow, endRow);
            stopIndex++;
            renumberStops();
        });
    }

    window.mRemoveStop = function(btn) {
        var row = btn.closest('.m-stop-row');
        if (row) {
            row.remove();
            renumberStops();
        }
    };

    function renumberStops() {
        var rows = document.querySelectorAll('#m-stops-container .m-stop-row');
        rows.forEach(function(row, i) {
            var num = row.querySelector('.m-stop-num');
            if (num) num.textContent = (i + 1);
        });
    }

    function getWaypoints() {
        var waypoints = [];
        var rows = document.querySelectorAll('#m-stops-container .m-stop-row');
        rows.forEach(function(row) {
            var addr = row.querySelector('.m-stop-address');
            var name = row.querySelector('.m-stop-name');
            if (addr && addr.value.trim()) {
                waypoints.push({
                    name: (name ? name.value.trim() : '') || 'Stop',
                    address: addr.value.trim()
                });
            }
        });
        return waypoints;
    }

    // Calculate Distance via Google Maps API
    var calcBtn = document.getElementById('m-calc-distance-btn');
    if (calcBtn) {
        calcBtn.addEventListener('click', function() {
            var waypoints = getWaypoints();
            if (waypoints.length < 2) {
                document.getElementById('m-calc-status').textContent = 'Enter at least 2 locations.';
                document.getElementById('m-calc-status').style.color = '#F59E0B';
                return;
            }

            calcBtn.disabled = true;
            calcBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calculating...';
            document.getElementById('m-calc-status').textContent = '';

            var formData = new FormData();
            formData.append('action', 'get_distance');
            formData.append('waypoints', JSON.stringify(waypoints));
            formData.append('csrf_token', <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);

            fetch('process_mileage.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                calcBtn.disabled = false;
                calcBtn.innerHTML = '<i class="fas fa-route"></i> Calculate Distance';
                if (data.success && data.data) {
                    var distField = document.getElementById('m-distance-field');
                    distField.value = data.data.distance_km;
                    document.getElementById('m-calc-status').textContent = 'Distance calculated via Google Maps.';
                    document.getElementById('m-calc-status').style.color = '#10B981';
                } else {
                    document.getElementById('m-calc-status').textContent = data.message || 'Could not calculate distance. Enter manually.';
                    document.getElementById('m-calc-status').style.color = '#F59E0B';
                }
            })
            .catch(function() {
                calcBtn.disabled = false;
                calcBtn.innerHTML = '<i class="fas fa-route"></i> Calculate Distance';
                document.getElementById('m-calc-status').textContent = 'Network error. Enter distance manually.';
                document.getElementById('m-calc-status').style.color = '#EF4444';
            });
        });
    }

    // Populate waypoints JSON on form submit
    var addForm = document.getElementById('m-add-form');
    if (addForm) {
        addForm.addEventListener('submit', function() {
            var waypoints = getWaypoints();
            document.getElementById('m-waypoints-data').value = JSON.stringify(waypoints);
        });
    }

    // Delete via AJAX (process_mileage.php delete returns JSON)
    document.getElementById('m-confirm-delete-btn').addEventListener('click', function() {
        if (!deleteId) return;
        var btn = this;
        btn.disabled = true;
        btn.textContent = 'Deleting…';

        var formData = new FormData();
        formData.append('action', 'delete');
        formData.append('log_id', deleteId);
        formData.append('csrf_token', <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);

        fetch('process_mileage.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var card = document.getElementById('m-entry-' + deleteId);
                if (card) card.remove();
                mCloseSheet('delete');
            } else {
                alert(data.message || 'Failed to delete');
            }
        })
        .catch(function() { alert('Network error. Please try again.'); })
        .finally(function() {
            btn.disabled = false;
            btn.textContent = 'Delete';
            deleteId = null;
        });
    });

    // Auto-dismiss success banner
    var banner = document.getElementById('m-success-msg');
    if (banner) setTimeout(function() { banner.style.display = 'none'; }, 4000);
})();
</script>
