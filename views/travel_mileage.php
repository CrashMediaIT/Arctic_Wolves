<?php
// Get mileage rates and unit preference from system settings
$rate_stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('mileage_rate', 'mileage_rate_per_km', 'mileage_rate_per_mile', 'mileage_unit')");
$rates = $rate_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$mileage_rate_per_mile = floatval($rates['mileage_rate_per_mile'] ?? $rates['mileage_rate'] ?? 0.65);
$mileage_rate_per_km = floatval($rates['mileage_rate_per_km'] ?? ($mileage_rate_per_mile / 1.60934));
$mileage_unit = $rates['mileage_unit'] ?? 'km';

// Get filter period
$filter_period = $_GET['period'] ?? 'month';

// Calculate date range
$date_filter = "";
$date_params = [$user_id];

if ($filter_period === 'month') {
    $date_filter = " AND m.trip_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
} elseif ($filter_period === 'last_month') {
    $date_filter = " AND m.trip_date >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND m.trip_date < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
} elseif ($filter_period === '3months') {
    $date_filter = " AND m.trip_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
} elseif ($filter_period === '6months') {
    $date_filter = " AND m.trip_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
} elseif ($filter_period === 'year') {
    $date_filter = " AND YEAR(m.trip_date) = YEAR(CURDATE())";
}

// Get mileage entries with route info from stops
$mileage_query = "
    SELECT m.*,
           m.reimbursement_amount as calculated_amount,
           start_stop.address as from_location,
           end_stop.address as to_location
    FROM mileage_logs m
    LEFT JOIN mileage_stops start_stop ON m.id = start_stop.mileage_log_id AND start_stop.stop_order = 0
    LEFT JOIN (
        SELECT ms.mileage_log_id, ms.address
        FROM mileage_stops ms
        INNER JOIN (
            SELECT mileage_log_id, MAX(stop_order) as max_order
            FROM mileage_stops
            GROUP BY mileage_log_id
        ) max_stops ON ms.mileage_log_id = max_stops.mileage_log_id AND ms.stop_order = max_stops.max_order
    ) end_stop ON m.id = end_stop.mileage_log_id
    WHERE m.user_id = ?" . $date_filter . "
    ORDER BY m.trip_date DESC, m.created_at DESC
    LIMIT 100
";

$mileage_stmt = $pdo->prepare($mileage_query);
$mileage_stmt->execute($date_params);
$mileage_entries = $mileage_stmt->fetchAll();

// Calculate summary
$summary = [
    'total_km' => 0,
    'total_miles' => 0,
    'total_amount' => 0,
    'total_trips' => count($mileage_entries)
];

foreach ($mileage_entries as $entry) {
    $summary['total_km'] += $entry['total_distance_km'] ?? 0;
    $summary['total_miles'] += $entry['total_distance_miles'] ?? 0;
    $summary['total_amount'] += $entry['calculated_amount'] ?? 0;
}
?>

<!-- Travel Mileage Tracking View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-route"></i> Mileage Tracking
    </h1>
    <p class="page-description">Track and manage your travel mileage for reimbursement</p>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
<div class="success-alert" style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-check-circle" style="color: #10b981; font-size: 20px;"></i>
    <span style="color: #10b981; font-weight: 600;"><?= htmlspecialchars($_GET['message'] ?? 'Mileage entry added successfully!') ?></span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #10b981; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>

<div class="mileage-content">
    <!-- Summary Cards -->
    <div class="mileage-summary">
        <div class="summary-card" data-component="StatsCard">
            <div class="summary-icon">
                <i class="fas fa-car"></i>
            </div>
            <div class="summary-details">
                <h4>This Month</h4>
                <?php if ($mileage_unit === 'miles'): ?>
                <p class="summary-value"><?= number_format($summary['total_miles'], 1) ?> miles</p>
                <?php else: ?>
                <p class="summary-value"><?= number_format($summary['total_km'], 1) ?> km</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="summary-card" data-component="StatsCard">
            <div class="summary-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="summary-details">
                <h4>Estimated Amount</h4>
                <p class="summary-value">$<?= number_format($summary['total_amount'], 2) ?></p>
            </div>
        </div>
        <div class="summary-card" data-component="StatsCard">
            <div class="summary-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="summary-details">
                <h4>Total Trips</h4>
                <p class="summary-value"><?= $summary['total_trips'] ?></p>
            </div>
        </div>
    </div>

    <!-- Add Mileage Form -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Add Mileage Entry</h3>
        </div>
        <div class="card-body">
            <form class="mileage-form" method="POST" action="process_mileage.php" data-form="mileage-entry">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="create">
                <!-- Note: user_id will be validated server-side from session -->
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="trip_date" class="form-input" required max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Purpose *</label>
                        <select name="purpose" class="form-input" required>
                            <option value="">-- Select Purpose --</option>
                            <option>Training Session</option>
                            <option>Team Practice</option>
                            <option>Game/Tournament</option>
                            <option>Meeting</option>
                            <option>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>From Location *</label>
                        <input type="text" name="from_location" class="form-input" placeholder="Starting location" required>
                    </div>
                    <div class="form-group">
                        <label>To Location *</label>
                        <input type="text" name="to_location" class="form-input" placeholder="Destination" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <?php if ($mileage_unit === 'miles'): ?>
                        <label>Distance (miles) *</label>
                        <input type="number" name="distance_miles" class="form-input" placeholder="0.0" step="0.1" min="0" required data-field="distance">
                        <?php else: ?>
                        <label>Distance (km) *</label>
                        <input type="number" name="distance_km" class="form-input" placeholder="0.0" step="0.1" min="0" required data-field="distance">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <?php if ($mileage_unit === 'miles'): ?>
                        <label>Rate per Mile</label>
                        <input type="number" name="rate_per_mile" class="form-input" value="<?= $mileage_rate_per_mile ?>" step="0.01" min="0" readonly data-field="rate">
                        <?php else: ?>
                        <label>Rate per Kilometer</label>
                        <input type="number" name="rate_per_km" class="form-input" value="<?= $mileage_rate_per_km ?>" step="0.01" min="0" readonly data-field="rate">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Total Amount</label>
                        <input type="text" class="form-input" value="$0.00" readonly data-field="total-display">
                    </div>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-textarea" rows="2" placeholder="Additional notes (optional)"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary" data-action="submit-form"><i class="fas fa-plus"></i> Add Entry</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Mileage Log -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Mileage Log</h3>
            <form method="GET" action="" class="filter-group">
                <input type="hidden" name="page" value="mileage">
                <select name="period" class="form-input-small" data-action="auto-submit">
                    <option value="month" <?= $filter_period === 'month' ? 'selected' : '' ?>>This Month</option>
                    <option value="last_month" <?= $filter_period === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                    <option value="3months" <?= $filter_period === '3months' ? 'selected' : '' ?>>Last 3 Months</option>
                    <option value="6months" <?= $filter_period === '6months' ? 'selected' : '' ?>>Last 6 Months</option>
                    <option value="year" <?= $filter_period === 'year' ? 'selected' : '' ?>>This Year</option>
                </select>
                <button type="button" class="btn-secondary" data-action="export-mileage"><i class="fas fa-file-export"></i> Export</button>
            </form>
        </div>
        <div class="card-body">
            <?php if (count($mileage_entries) > 0): ?>
            <div class="mileage-table-container" data-component="DataTable">
                <table class="mileage-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Purpose</th>
                            <th>Route</th>
                            <th>Distance</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mileage_entries as $entry): ?>
                        <tr data-entry-id="<?= $entry['id'] ?>">
                            <td><?= date('M d, Y', strtotime($entry['trip_date'])) ?></td>
                            <td><?= htmlspecialchars($entry['purpose'] ?? 'N/A') ?></td>
                            <td>
                                <div class="route-info">
                                    <span class="route-from"><?= htmlspecialchars($entry['from_location'] ?? 'N/A') ?></span>
                                    <i class="fas fa-arrow-right"></i>
                                    <span class="route-to"><?= htmlspecialchars($entry['to_location'] ?? 'N/A') ?></span>
                                </div>
                            </td>
                            <td>
                                <?php if ($mileage_unit === 'miles'): ?>
                                <?= number_format($entry['total_distance_miles'] ?? 0, 1) ?> mi
                                <?php else: ?>
                                <?= number_format($entry['total_distance_km'] ?? 0, 1) ?> km
                                <?php endif; ?>
                            </td>
                            <td>$<?= number_format($entry['calculated_amount'] ?? 0, 2) ?></td>
                            <td>
                                <span class="status-badge <?= $entry['is_reimbursed'] ? 'reimbursed' : 'pending' ?>">
                                    <?= $entry['is_reimbursed'] ? 'Reimbursed' : 'Pending' ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <?php if (!$entry['is_reimbursed']): ?>
                                        <button class="btn-icon" title="Edit" data-action="edit-entry" data-entry-id="<?= $entry['id'] ?>"><i class="fas fa-edit"></i></button>
                                        <button class="btn-icon" title="Delete" data-action="delete-entry" data-entry-id="<?= $entry['id'] ?>"><i class="fas fa-trash"></i></button>
                                    <?php else: ?>
                                        <button class="btn-icon" title="View" data-action="view-entry" data-entry-id="<?= $entry['id'] ?>"><i class="fas fa-eye"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="placeholder-container">
                <i class="fas fa-car placeholder-icon"></i>
                <p class="placeholder-text">No mileage entries found for the selected period.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.mileage-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.summary-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.summary-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--neon), var(--accent));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    flex-shrink: 0;
}

.summary-details h4 {
    font-size: 14px;
    color: var(--text-dim);
    margin-bottom: 5px;
}

.summary-value {
    font-size: 24px;
    font-weight: 900;
    color: var(--text-white);
}

.mileage-form .form-actions {
    display: flex;
    justify-content: flex-end;
    padding-top: 15px;
    border-top: 1px solid var(--border);
}

.mileage-table-container {
    overflow-x: auto;
}

.mileage-table {
    width: 100%;
    border-collapse: collapse;
}

.mileage-table thead {
    background: var(--bg-main);
}

.mileage-table th {
    padding: 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border);
}

.mileage-table td {
    padding: 16px;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
    color: var(--text-white);
}

.mileage-table tbody tr {
    transition: all 0.3s;
}

.mileage-table tbody tr:hover {
    background: var(--bg-main);
}

.route-info {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}

.route-from,
.route-to {
    color: var(--text-dim);
}

.route-info i {
    color: var(--neon);
    font-size: 10px;
}
</style>

<!-- Edit Mileage Modal -->
<div id="edit-mileage-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Edit Mileage Entry</h2>
            <button class="modal-close" onclick="closeMileageModal()">&times;</button>
        </div>
        <form method="POST" action="process_mileage.php" id="editMileageForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="log_id" id="editLogId">
            
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="trip_date" id="editTripDate" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label>Purpose *</label>
                        <select name="purpose" id="editPurpose" class="form-input" required>
                            <option value="">-- Select Purpose --</option>
                            <option>Training Session</option>
                            <option>Team Practice</option>
                            <option>Game/Tournament</option>
                            <option>Meeting</option>
                            <option>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>From Location *</label>
                        <input type="text" name="from_location" id="editFromLocation" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label>To Location *</label>
                        <input type="text" name="to_location" id="editToLocation" class="form-input" required>
                    </div>
                </div>

                <div class="form-row">
                    <?php if ($mileage_unit === 'miles'): ?>
                    <div class="form-group">
                        <label>Distance (miles) *</label>
                        <input type="number" name="distance_miles" id="editDistanceMiles" class="form-input" step="0.1" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Distance (km)</label>
                        <input type="number" name="distance_km" id="editDistanceKm" class="form-input" step="0.1" min="0" readonly>
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label>Distance (km) *</label>
                        <input type="number" name="distance_km" id="editDistanceKm" class="form-input" step="0.1" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Distance (miles)</label>
                        <input type="number" name="distance_miles" id="editDistanceMiles" class="form-input" step="0.1" min="0" readonly>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeMileageModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Entry</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-mileage-modal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2 class="modal-title">Delete Entry</h2>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center;">
            <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ef4444; margin-bottom: 16px;"></i>
            <p style="color: var(--text-white); margin-bottom: 8px;">Are you sure you want to delete this mileage entry?</p>
            <p style="color: var(--text-dim); font-size: 13px;">This action cannot be undone.</p>
        </div>
        <div class="modal-footer" style="justify-content: center;">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()"><i class="fas fa-times"></i> Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteBtn" onclick="confirmDelete()"><i class="fas fa-trash"></i> Delete</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var csrfToken = document.querySelector('[name="csrf_token"]')?.value || '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES) ?>';
    var pendingDeleteId = null;
    
    // Show notification helper
    function showNotification(message, type) {
        var existing = document.querySelector('.notification-widget');
        if (existing) existing.remove();
        
        var div = document.createElement('div');
        div.className = 'notification-widget';
        div.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; padding: 16px 24px; border-radius: 8px; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);';
        if (type === 'success') {
            div.style.background = 'rgba(16, 185, 129, 0.95)';
            div.style.color = '#fff';
        } else {
            div.style.background = 'rgba(239, 68, 68, 0.95)';
            div.style.color = '#fff';
        }
        var safeMsg = document.createElement('span');
        safeMsg.textContent = message;
        div.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ';
        div.appendChild(safeMsg);
        var closeBtn = document.createElement('button');
        closeBtn.innerHTML = '&times;';
        closeBtn.style.cssText = 'margin-left: 16px; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;';
        closeBtn.onclick = function() { div.remove(); };
        div.appendChild(closeBtn);
        document.body.appendChild(div);
        setTimeout(function() { if (div.parentElement) div.remove(); }, 5000);
    }
    
    // Calculate total amount on distance change
    var distanceField = document.querySelector('[data-field="distance"]');
    var rateField = document.querySelector('[data-field="rate"]');
    var totalDisplay = document.querySelector('[data-field="total-display"]');
    
    if (distanceField && rateField && totalDisplay) {
        distanceField.addEventListener('input', function() {
            var distance = parseFloat(this.value) || 0;
            var rate = parseFloat(rateField.value) || 0;
            var total = distance * rate;
            totalDisplay.value = '$' + total.toFixed(2);
        });
    }
    
    // Edit entry handlers
    document.querySelectorAll('[data-action="edit-entry"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var entryId = this.getAttribute('data-entry-id');
            var row = this.closest('tr');
            
            // Extract data from the row
            var dateCell = row.cells[0].textContent;
            var purpose = row.cells[1].textContent;
            var fromLocation = row.querySelector('.route-from')?.textContent || '';
            var toLocation = row.querySelector('.route-to')?.textContent || '';
            var distance = row.cells[3].textContent.replace(' mi', '');
            
            // Convert date format (M dd, YYYY to YYYY-MM-DD)
            var date = new Date(dateCell);
            var formattedDate = date.toISOString().split('T')[0];
            
            // Populate modal
            document.getElementById('editLogId').value = entryId;
            document.getElementById('editTripDate').value = formattedDate;
            document.getElementById('editPurpose').value = purpose.trim() !== 'N/A' ? purpose.trim() : '';
            document.getElementById('editFromLocation').value = fromLocation.trim() !== 'N/A' ? fromLocation.trim() : '';
            document.getElementById('editToLocation').value = toLocation.trim() !== 'N/A' ? toLocation.trim() : '';
            document.getElementById('editDistanceMiles').value = parseFloat(distance) || 0;
            document.getElementById('editDistanceKm').value = (parseFloat(distance) * 1.60934).toFixed(2) || 0;
            
            // Show modal
            document.getElementById('edit-mileage-modal').classList.add('active');
        });
    });
    
    // Delete entry handlers
    document.querySelectorAll('[data-action="delete-entry"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            pendingDeleteId = this.getAttribute('data-entry-id');
            document.getElementById('delete-mileage-modal').classList.add('active');
        });
    });
    
    // Handle edit form submission via AJAX
    var editForm = document.getElementById('editMileageForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            // Create waypoints from locations
            var fromLocation = document.getElementById('editFromLocation').value;
            var toLocation = document.getElementById('editToLocation').value;
            var waypoints = JSON.stringify([
                {name: 'Start', address: fromLocation},
                {name: 'End', address: toLocation}
            ]);
            formData.append('waypoints', waypoints);
            
            var submitBtn = this.querySelector('button[type="submit"]');
            var originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            submitBtn.disabled = true;
            
            fetch('process_mileage.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                if (data.success) {
                    showNotification(data.message || 'Mileage entry updated!', 'success');
                    closeMileageModal();
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showNotification('Error: ' + (data.message || 'Failed to update'), 'error');
                }
            })
            .catch(function() {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                showNotification('An error occurred', 'error');
            });
        });
    }
    
    // Confirm delete function
    window.confirmDelete = function() {
        if (!pendingDeleteId) return;
        
        var deleteBtn = document.getElementById('confirmDeleteBtn');
        var originalText = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
        deleteBtn.disabled = true;
        
        fetch('process_mileage.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=delete&log_id=' + encodeURIComponent(pendingDeleteId) + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            deleteBtn.innerHTML = originalText;
            deleteBtn.disabled = false;
            
            if (data.success) {
                showNotification(data.message || 'Entry deleted!', 'success');
                closeDeleteModal();
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showNotification('Error: ' + (data.message || 'Failed to delete'), 'error');
            }
        })
        .catch(function() {
            deleteBtn.innerHTML = originalText;
            deleteBtn.disabled = false;
            showNotification('An error occurred', 'error');
        });
    };
    
    // Auto-submit filter
    document.querySelectorAll('[data-action="auto-submit"]').forEach(function(select) {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });
    
    // Export mileage
    document.querySelectorAll('[data-action="export-mileage"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var startDate = '<?= htmlspecialchars(date("Y-m-01"), ENT_QUOTES) ?>';
            var endDate = '<?= htmlspecialchars(date("Y-m-t"), ENT_QUOTES) ?>';
            window.location.href = 'process_mileage.php?action=export_csv&start_date=' + encodeURIComponent(startDate) + '&end_date=' + encodeURIComponent(endDate);
        });
    });
});

function closeMileageModal() {
    document.getElementById('edit-mileage-modal').classList.remove('active');
}

function closeDeleteModal() {
    document.getElementById('delete-mileage-modal').classList.remove('active');
}

// Close modals when clicking outside
document.querySelectorAll('.modal').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>
