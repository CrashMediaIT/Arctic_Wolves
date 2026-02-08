<?php
// Get Google Maps API key
try {
    $api_key_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'google_maps_api_key'");
    $google_maps_api_key = $api_key_stmt->fetchColumn() ?: '';
} catch (Exception $e) {
    error_log('Failed to retrieve Google Maps API key: ' . $e->getMessage());
    $google_maps_api_key = '';
}

// Get mileage rates and unit preference from system settings
$rate_stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('mileage_rate', 'mileage_rate_per_km', 'mileage_rate_after_5000_per_km', 'mileage_rate_per_mile', 'mileage_unit')");
$rates = $rate_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$mileage_rate_per_mile = floatval($rates['mileage_rate_per_mile'] ?? $rates['mileage_rate'] ?? 0.65);
$mileage_rate_per_km = floatval($rates['mileage_rate_per_km'] ?? 0.70);
$mileage_rate_after_5000_per_km = floatval($rates['mileage_rate_after_5000_per_km'] ?? 0.64);
$mileage_unit = $rates['mileage_unit'] ?? 'km';

// Calculate total km driven this year for the current user (for CRA tiered rate)
$year_km_stmt = $pdo->prepare("SELECT COALESCE(SUM(total_distance_km), 0) FROM mileage_logs WHERE user_id = ? AND YEAR(trip_date) = YEAR(CURDATE())");
$year_km_stmt->execute([$user_id]);
$year_km_total = floatval($year_km_stmt->fetchColumn());

// Get filter parameters
$filter_period = $_GET['period'] ?? 'month';
$filter_search = trim($_GET['search'] ?? '');
$filter_athlete = isset($_GET['athlete_id']) ? intval($_GET['athlete_id']) : 0;
$filter_session = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

// Get athletes for filter dropdown
$athletes_stmt = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role = 'athlete' AND is_active = 1 ORDER BY first_name, last_name");
$athletes = $athletes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get sessions for filter dropdown
$sessions_stmt = $pdo->query("
    SELECT s.id, s.title as session_name, s.session_date
    FROM sessions s 
    WHERE s.session_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
    ORDER BY s.session_date DESC
    LIMIT 100
");
$sessions = $sessions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate date range based on period
$date_filter = "";
$date_params = [$user_id];

if ($filter_period === 'week') {
    $date_filter = " AND m.trip_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
} elseif ($filter_period === 'month') {
    $date_filter = " AND m.trip_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
} elseif ($filter_period === 'last_month') {
    $date_filter = " AND m.trip_date >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND m.trip_date < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
} elseif ($filter_period === '3months') {
    $date_filter = " AND m.trip_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
} elseif ($filter_period === '6months') {
    $date_filter = " AND m.trip_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
} elseif ($filter_period === 'year') {
    $date_filter = " AND YEAR(m.trip_date) = YEAR(CURDATE())";
} elseif ($filter_period === 'last_year') {
    $date_filter = " AND YEAR(m.trip_date) = YEAR(CURDATE()) - 1";
} elseif ($filter_period === 'all') {
    $date_filter = "";
}

// Add search filter
$search_filter = "";
if (!empty($filter_search)) {
    $search_filter = " AND (m.title LIKE ? OR m.purpose LIKE ? OR m.description LIKE ?)";
    $date_params[] = '%' . $filter_search . '%';
    $date_params[] = '%' . $filter_search . '%';
    $date_params[] = '%' . $filter_search . '%';
}

// Add athlete filter
$athlete_filter = "";
if ($filter_athlete > 0) {
    $athlete_filter = " AND m.athlete_id = ?";
    $date_params[] = $filter_athlete;
}

// Add session filter
$session_filter = "";
if ($filter_session > 0) {
    $session_filter = " AND m.session_id = ?";
    $date_params[] = $filter_session;
}

// Get mileage entries with route info from stops
$mileage_query = "
    SELECT m.*,
           m.reimbursement_amount as calculated_amount,
           start_stop.address as from_location,
           end_stop.address as to_location,
           CONCAT(a.first_name, ' ', a.last_name) as athlete_name,
           s.title as session_name,
           (SELECT COUNT(*) FROM mileage_stops WHERE mileage_log_id = m.id) as stop_count
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
    LEFT JOIN users a ON m.athlete_id = a.id
    LEFT JOIN sessions s ON m.session_id = s.id
    WHERE m.user_id = ?" . $date_filter . $search_filter . $athlete_filter . $session_filter . "
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

<?php if (!empty($google_maps_api_key)): ?>
<script>(g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.googleapis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({
  key: "<?= htmlspecialchars($google_maps_api_key) ?>",
  v: "weekly"
});</script>
<?php endif; ?>

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
            <form class="mileage-form" method="POST" action="process_mileage.php" data-form="mileage-entry" id="addMileageForm">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="waypoints" id="waypointsData">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Trip Title *</label>
                        <input type="text" name="title" class="form-input" placeholder="e.g., Weekly Training Trip" required>
                    </div>
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="trip_date" class="form-input" required max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="form-row">
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
                    <div class="form-group">
                        <label>Assign to Athlete (Optional)</label>
                        <select name="athlete_id" class="form-input">
                            <option value="">-- No Athlete --</option>
                            <?php foreach ($athletes as $athlete): ?>
                                <option value="<?= $athlete['id'] ?>"><?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Assign to Session (Optional)</label>
                        <select name="session_id" class="form-input">
                            <option value="">-- No Session --</option>
                            <?php foreach ($sessions as $session): ?>
                                <option value="<?= $session['id'] ?>"><?= htmlspecialchars($session['session_name']) ?> - <?= date('M d, Y', strtotime($session['session_date'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-textarea" rows="2" placeholder="Trip description (optional)"></textarea>
                </div>

                <hr style="border-color: var(--border); margin: 20px 0;">
                
                <h4 style="color: var(--text-white); margin-bottom: 15px;"><i class="fas fa-map-marker-alt"></i> Trip Stops</h4>
                
                <div id="stopsContainer">
                    <div class="stop-row" data-index="0">
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label>Start Location *</label>
                                <input type="text" class="form-input stop-address" data-index="0" placeholder="Starting address" required>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label>Stop Name</label>
                                <input type="text" class="form-input stop-name" data-index="0" placeholder="e.g., Home" value="Start">
                            </div>
                        </div>
                    </div>
                    <div class="stop-row" data-index="1">
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label>End Location *</label>
                                <input type="text" class="form-input stop-address" data-index="1" placeholder="Destination address" required>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label>Stop Name</label>
                                <input type="text" class="form-input stop-name" data-index="1" placeholder="e.g., Arena" value="End">
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="button" class="btn-secondary" id="addStopBtn" style="margin: 15px 0;">
                    <i class="fas fa-plus"></i> Add Stop
                </button>

                <hr style="border-color: var(--border); margin: 20px 0;">

                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <button type="button" class="btn-secondary" id="calcDistanceBtn">
                        <i class="fas fa-route"></i> Calculate Distance
                    </button>
                    <span id="distanceStatus" style="font-size: 13px; color: var(--text-dim);"></span>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <?php if ($mileage_unit === 'miles'): ?>
                        <label>Distance (miles) *</label>
                        <input type="number" name="distance_miles" class="form-input" placeholder="Calculated by Google Maps" step="0.1" min="0" required data-field="distance" readonly>
                        <?php else: ?>
                        <label>Distance (km) *</label>
                        <input type="number" name="distance_km" class="form-input" placeholder="Calculated by Google Maps" step="0.1" min="0" required data-field="distance" readonly>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <?php if ($mileage_unit === 'miles'): ?>
                        <label>Rate per Mile</label>
                        <input type="number" name="rate_per_mile" class="form-input" value="<?= $mileage_rate_per_mile ?>" step="0.01" min="0" readonly data-field="rate">
                        <?php else: ?>
                        <label>Rate per Kilometer (CRA Tiered)</label>
                        <input type="hidden" name="rate_per_km" data-field="rate" value="<?= $mileage_rate_per_km ?>">
                        <input type="hidden" name="rate_after_5000_per_km" data-field="rate-after-5000" value="<?= $mileage_rate_after_5000_per_km ?>">
                        <input type="hidden" data-field="year-km-total" value="<?= $year_km_total ?>">
                        <div style="color: rgba(255,255,255,0.7); font-size: 0.85rem; padding: 8px 12px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                            <div>First 5,000 km: <strong style="color: #fff;">$<?= number_format($mileage_rate_per_km, 2) ?>/km</strong></div>
                            <div>After 5,000 km: <strong style="color: #fff;">$<?= number_format($mileage_rate_after_5000_per_km, 2) ?>/km</strong></div>
                            <div style="margin-top: 4px; font-size: 0.8rem; color: rgba(255,255,255,0.5);">Year-to-date: <?= number_format($year_km_total, 1) ?> km driven</div>
                        </div>
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
        <div class="card-header mileage-log-header">
            <h3><i class="fas fa-list"></i> Mileage Log</h3>
            <form method="GET" action="" class="filter-group" id="filterForm">
                <input type="hidden" name="page" value="mileage">
                
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 11px; color: var(--text-dim);">Period</label>
                    <select name="period" class="form-input-small" data-action="auto-submit">
                        <option value="week" <?= $filter_period === 'week' ? 'selected' : '' ?>>This Week</option>
                        <option value="month" <?= $filter_period === 'month' ? 'selected' : '' ?>>This Month</option>
                        <option value="last_month" <?= $filter_period === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                        <option value="3months" <?= $filter_period === '3months' ? 'selected' : '' ?>>Last 3 Months</option>
                        <option value="6months" <?= $filter_period === '6months' ? 'selected' : '' ?>>Last 6 Months</option>
                        <option value="year" <?= $filter_period === 'year' ? 'selected' : '' ?>>This Year</option>
                        <option value="last_year" <?= $filter_period === 'last_year' ? 'selected' : '' ?>>Last Year</option>
                        <option value="all" <?= $filter_period === 'all' ? 'selected' : '' ?>>All Time</option>
                    </select>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 11px; color: var(--text-dim);">Search</label>
                    <input type="text" name="search" class="form-input-small" placeholder="Search title/purpose..." value="<?= htmlspecialchars($filter_search) ?>" style="width: 150px;">
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 11px; color: var(--text-dim);">Athlete</label>
                    <select name="athlete_id" class="form-input-small" data-action="auto-submit">
                        <option value="">All Athletes</option>
                        <?php foreach ($athletes as $athlete): ?>
                            <option value="<?= $athlete['id'] ?>" <?= $filter_athlete == $athlete['id'] ? 'selected' : '' ?>><?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 11px; color: var(--text-dim);">Session</label>
                    <select name="session_id" class="form-input-small" data-action="auto-submit">
                        <option value="">All Sessions</option>
                        <?php foreach ($sessions as $session): ?>
                            <option value="<?= $session['id'] ?>" <?= $filter_session == $session['id'] ? 'selected' : '' ?>><?= htmlspecialchars($session['session_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-secondary" style="height: fit-content;"><i class="fas fa-search"></i> Filter</button>
                <button type="button" class="btn-secondary" data-action="export-mileage" style="height: fit-content;"><i class="fas fa-file-export"></i> Export</button>
            </form>
        </div>
        <div class="card-body">
            <?php if (count($mileage_entries) > 0): ?>
            <div class="mileage-table-container" data-component="DataTable">
                <table class="mileage-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Purpose</th>
                            <th>Route</th>
                            <th>Assigned To</th>
                            <th>Distance</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mileage_entries as $entry): ?>
                        <tr data-entry-id="<?= $entry['id'] ?>" 
                            data-title="<?= htmlspecialchars($entry['title'] ?? '') ?>"
                            data-description="<?= htmlspecialchars($entry['description'] ?? '') ?>"
                            data-athlete-id="<?= $entry['athlete_id'] ?? '' ?>"
                            data-session-id="<?= $entry['session_id'] ?? '' ?>">
                            <td><?= date('M d, Y', strtotime($entry['trip_date'])) ?></td>
                            <td>
                                <div style="font-weight: 600;"><?= htmlspecialchars($entry['title'] ?? 'Untitled') ?></div>
                                <?php if (!empty($entry['description'])): ?>
                                <div style="font-size: 11px; color: var(--text-dim); max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($entry['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($entry['purpose'] ?? 'N/A') ?></td>
                            <td>
                                <div class="route-info">
                                    <span class="route-from"><?= htmlspecialchars($entry['from_location'] ?? 'N/A') ?></span>
                                    <i class="fas fa-arrow-right"></i>
                                    <span class="route-to"><?= htmlspecialchars($entry['to_location'] ?? 'N/A') ?></span>
                                    <?php if (($entry['stop_count'] ?? 0) > 2): ?>
                                    <span class="stops-badge">+<?= ($entry['stop_count'] - 2) ?> stops</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($entry['athlete_name'])): ?>
                                <div style="font-size: 12px;"><i class="fas fa-user" style="color: var(--primary);"></i> <?= htmlspecialchars($entry['athlete_name']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($entry['session_name'])): ?>
                                <div style="font-size: 11px; color: var(--text-dim);"><i class="fas fa-calendar-alt"></i> <?= htmlspecialchars($entry['session_name']) ?></div>
                                <?php endif; ?>
                                <?php if (empty($entry['athlete_name']) && empty($entry['session_name'])): ?>
                                <span style="color: var(--text-dim);">-</span>
                                <?php endif; ?>
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
                <p class="placeholder-text">No mileage entries found for the selected filters.</p>
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

.stops-badge {
    background: var(--primary);
    color: #fff;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 5px;
}

.stop-row {
    background: var(--bg-card);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 10px;
    position: relative;
}

.stop-row .remove-stop-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #ef4444;
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}

.stop-row .remove-stop-btn:hover {
    background: #dc2626;
}

.filter-group {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: flex-end;
    flex: 1;
    margin-left: auto;
}

.filter-group > div {
    min-width: fit-content;
}

.card-header.mileage-log-header {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: center;
}

.card-header.mileage-log-header h3 {
    margin: 0;
    flex-shrink: 0;
}

/* Responsive adjustments for filter group */
@media (max-width: 1200px) {
    .card-header.mileage-log-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .filter-group {
        margin-left: 0;
        width: 100%;
    }
}

@media (max-width: 768px) {
    .filter-group {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group > div {
        width: 100%;
    }
    
    .filter-group input[name="search"] {
        width: 100% !important;
    }
}

.form-input-small {
    padding: 8px 12px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-white);
    font-size: 13px;
    min-width: 120px;
}
</style>

<!-- Edit Mileage Modal -->
<div id="edit-mileage-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Edit Mileage Entry</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeMileageModal()">&times;</button>
        </div>
        <form method="POST" action="process_mileage.php" id="editMileageForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="log_id" id="editLogId">
            
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Title *</label>
                        <input type="text" name="title" id="editTitle" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="trip_date" id="editTripDate" class="form-input" required>
                    </div>
                </div>

                <div class="form-row">
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
                    <div class="form-group">
                        <label>Athlete (Optional)</label>
                        <select name="athlete_id" id="editAthleteId" class="form-input">
                            <option value="">-- No Athlete --</option>
                            <?php foreach ($athletes as $athlete): ?>
                                <option value="<?= $athlete['id'] ?>"><?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Session (Optional)</label>
                        <select name="session_id" id="editSessionId" class="form-input">
                            <option value="">-- No Session --</option>
                            <?php foreach ($sessions as $session): ?>
                                <option value="<?= $session['id'] ?>"><?= htmlspecialchars($session['session_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="editDescription" class="form-textarea" rows="2"></textarea>
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
            <button class="modal-close" aria-label="Close modal" onclick="closeDeleteModal()">&times;</button>
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
    var stopIndex = 2; // Start with 2 since we have start (0) and end (1)
    
    // Initialize Google Maps PlaceAutocompleteElement for address fields
    async function initGoogleMapsAutocomplete() {
        try {
            var { PlaceAutocompleteElement } = await google.maps.importLibrary('places');
            
            // Initialize autocomplete for all address input fields
            var addressInputs = document.querySelectorAll('.stop-address');
            addressInputs.forEach(function(input) {
                if (!input.dataset.autocompleteInit) {
                    var autocompleteEl = new PlaceAutocompleteElement();
                    autocompleteEl.style.cssText = 'width: 100%;';
                    autocompleteEl.setAttribute('placeholder', input.placeholder || 'Enter address');
                    autocompleteEl.className = input.className;
                    autocompleteEl.dataset.index = input.dataset.index;
                    if (input.value) {
                        autocompleteEl.value = input.value;
                    }

                    autocompleteEl.addEventListener('gmp-placeselect', async function(event) {
                        var place = event.place;
                        try {
                            await place.fetchFields({ fields: ['displayName', 'formattedAddress'] });
                            autocompleteEl.dataset.address = place.formattedAddress || '';
                            autocompleteEl.dataset.name = place.displayName || '';
                            // Auto-calculate distance when a stop address is selected
                            if (typeof autoCalculateDistance === 'function') {
                                autoCalculateDistance();
                            }
                        } catch (err) {
                            console.error('Failed to fetch place details:', err);
                        }
                    });

                    input.parentNode.replaceChild(autocompleteEl, input);
                    autocompleteEl.dataset.autocompleteInit = 'true';
                }
            });
            
            // Also initialize for modal edit fields
            var editFields = ['editFromLocation', 'editToLocation'];
            editFields.forEach(function(fieldId) {
                var field = document.getElementById(fieldId);
                if (field && !field.dataset.autocompleteInit) {
                    var autocompleteEl = new PlaceAutocompleteElement();
                    autocompleteEl.style.cssText = 'width: 100%;';
                    autocompleteEl.setAttribute('placeholder', field.placeholder || 'Enter address');
                    autocompleteEl.className = field.className;
                    autocompleteEl.id = field.id;
                    autocompleteEl.setAttribute('name', field.name);
                    if (field.value) {
                        autocompleteEl.value = field.value;
                    }

                    autocompleteEl.addEventListener('gmp-placeselect', async function(event) {
                        var place = event.place;
                        try {
                            await place.fetchFields({ fields: ['displayName', 'formattedAddress'] });
                            autocompleteEl.dataset.address = place.formattedAddress || '';
                        } catch (err) {
                            console.error('Failed to fetch place details:', err);
                        }
                    });

                    field.parentNode.replaceChild(autocompleteEl, field);
                    autocompleteEl.dataset.autocompleteInit = 'true';
                }
            });
        } catch (e) {
            console.error('Failed to initialize Google Maps Places:', e);
        }
    }
    
    // Initialize on page load
    initGoogleMapsAutocomplete();
    
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
    
    // Calculate total amount on distance change (CRA tiered rates)
    var distanceField = document.querySelector('[data-field="distance"]');
    var rateField = document.querySelector('[data-field="rate"]');
    var rateAfter5000Field = document.querySelector('[data-field="rate-after-5000"]');
    var yearKmTotalField = document.querySelector('[data-field="year-km-total"]');
    var totalDisplay = document.querySelector('[data-field="total-display"]');
    
    function calculateTieredReimbursement(distanceKm) {
        var rate = parseFloat(rateField ? rateField.value : 0) || 0;
        var rateAfter5000 = rateAfter5000Field ? (parseFloat(rateAfter5000Field.value) || rate) : rate;
        var yearKmTotal = yearKmTotalField ? (parseFloat(yearKmTotalField.value) || 0) : 0;
        
        if (!rateAfter5000Field) {
            // Miles mode or no tiered rate - simple calculation
            return distanceKm * rate;
        }
        
        // CRA tiered calculation
        var remainingFirst5000 = Math.max(0, 5000 - yearKmTotal);
        var kmAtHighRate = Math.min(distanceKm, remainingFirst5000);
        var kmAtLowRate = Math.max(0, distanceKm - kmAtHighRate);
        
        return (kmAtHighRate * rate) + (kmAtLowRate * rateAfter5000);
    }
    
    if (distanceField && rateField && totalDisplay) {
        distanceField.addEventListener('input', function() {
            var distance = parseFloat(this.value) || 0;
            var total = calculateTieredReimbursement(distance);
            totalDisplay.value = '$' + total.toFixed(2);
        });
    }
    
    // Auto-calculate distance via Google Maps API
    var calcDistanceBtn = document.getElementById('calcDistanceBtn');
    var distanceStatus = document.getElementById('distanceStatus');
    
    function getWaypointsFromForm() {
        var waypoints = [];
        var stopAddresses = document.querySelectorAll('gmp-place-autocomplete.stop-address, input.stop-address');
        var stopNames = document.querySelectorAll('.stop-name');
        
        stopAddresses.forEach(function(el, i) {
            var address = el.dataset.address || el.value || '';
            if (address.trim()) {
                waypoints.push({
                    name: stopNames[i]?.value || 'Stop',
                    address: address.trim()
                });
            }
        });
        return waypoints;
    }
    
    function autoCalculateDistance() {
        var waypoints = getWaypointsFromForm();
        
        if (waypoints.length < 2) {
            distanceStatus.textContent = 'Enter at least 2 locations to calculate distance.';
            distanceStatus.style.color = 'var(--text-dim)';
            return;
        }
        
        // Show calculating state
        calcDistanceBtn.disabled = true;
        calcDistanceBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calculating...';
        distanceStatus.textContent = '';
        
        var formData = new FormData();
        formData.append('action', 'get_distance');
        formData.append('waypoints', JSON.stringify(waypoints));
        formData.append('csrf_token', csrfToken);
        
        fetch('process_mileage.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            calcDistanceBtn.disabled = false;
            calcDistanceBtn.innerHTML = '<i class="fas fa-route"></i> Calculate Distance';
            
            if (data.success && data.data) {
                var distField = document.querySelector('[data-field="distance"]');
                var mileageUnit = '<?= $mileage_unit ?>';
                if (mileageUnit === 'miles') {
                    distField.value = data.data.distance_miles;
                } else {
                    distField.value = data.data.distance_km;
                }
                distField.readOnly = true;
                distField.dispatchEvent(new Event('input'));
                distanceStatus.textContent = 'Distance calculated via Google Maps.';
                distanceStatus.style.color = '#10b981';
            } else {
                enableManualDistance('API error: ' + (data.message || 'Could not calculate distance.'));
            }
        })
        .catch(function(err) {
            calcDistanceBtn.disabled = false;
            calcDistanceBtn.innerHTML = '<i class="fas fa-route"></i> Calculate Distance';
            enableManualDistance('Could not reach Google Maps. Enter distance manually.');
        });
    }
    
    function enableManualDistance(reason) {
        var distField = document.querySelector('[data-field="distance"]');
        distField.readOnly = false;
        distField.placeholder = 'Enter distance manually';
        distanceStatus.textContent = reason;
        distanceStatus.style.color = '#ef4444';
    }
    
    if (calcDistanceBtn) {
        calcDistanceBtn.addEventListener('click', autoCalculateDistance);
    }
    
    // Add stop functionality
    var addStopBtn = document.getElementById('addStopBtn');
    if (addStopBtn) {
        addStopBtn.addEventListener('click', function() {
            var container = document.getElementById('stopsContainer');
            var endStop = container.lastElementChild;
            
            var newStop = document.createElement('div');
            newStop.className = 'stop-row';
            newStop.dataset.index = stopIndex;
            newStop.innerHTML = '<button type="button" class="remove-stop-btn" onclick="removeStop(this)"><i class="fas fa-times"></i></button>' +
                '<div class="form-row">' +
                    '<div class="form-group" style="flex: 2;">' +
                        '<label>Stop ' + stopIndex + ' *</label>' +
                        '<input type="text" class="form-input stop-address" data-index="' + stopIndex + '" placeholder="Stop address" required>' +
                    '</div>' +
                    '<div class="form-group" style="flex: 1;">' +
                        '<label>Stop Name</label>' +
                        '<input type="text" class="form-input stop-name" data-index="' + stopIndex + '" placeholder="e.g., Gas Station" value="Stop ' + stopIndex + '">' +
                    '</div>' +
                '</div>';
            
            container.insertBefore(newStop, endStop);
            stopIndex++;
            
            // Initialize Google Maps Autocomplete for the new field
            initGoogleMapsAutocomplete();
        });
    }
    
    // Handle form submission to gather waypoints
    var addForm = document.getElementById('addMileageForm');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            var waypoints = [];
            var stopAddresses = document.querySelectorAll('gmp-place-autocomplete.stop-address, input.stop-address');
            var stopNames = document.querySelectorAll('.stop-name');
            
            stopAddresses.forEach(function(el, i) {
                var address = el.dataset.address || el.value || '';
                if (address.trim()) {
                    waypoints.push({
                        name: stopNames[i]?.value || 'Stop',
                        address: address.trim()
                    });
                }
            });
            
            document.getElementById('waypointsData').value = JSON.stringify(waypoints);
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
            var titleDiv = row.cells[1].querySelector('div');
            var title = titleDiv ? titleDiv.textContent : '';
            var description = row.dataset.description || '';
            var purpose = row.cells[2].textContent;
            var fromLocation = row.querySelector('.route-from')?.textContent || '';
            var toLocation = row.querySelector('.route-to')?.textContent || '';
            var distance = row.cells[5].textContent.replace(' mi', '').replace(' km', '');
            var athleteId = row.dataset.athleteId || '';
            var sessionId = row.dataset.sessionId || '';
            
            // Convert date format (M dd, YYYY to YYYY-MM-DD)
            var date = new Date(dateCell);
            var formattedDate = date.toISOString().split('T')[0];
            
            // Populate modal
            document.getElementById('editLogId').value = entryId;
            document.getElementById('editTitle').value = title.trim() !== 'Untitled' ? title.trim() : '';
            document.getElementById('editTripDate').value = formattedDate;
            document.getElementById('editPurpose').value = purpose.trim() !== 'N/A' ? purpose.trim() : '';
            document.getElementById('editDescription').value = description;
            var editFrom = document.getElementById('editFromLocation');
            var editTo = document.getElementById('editToLocation');
            var fromVal = fromLocation.trim() !== 'N/A' ? fromLocation.trim() : '';
            var toVal = toLocation.trim() !== 'N/A' ? toLocation.trim() : '';
            editFrom.value = fromVal;
            editFrom.dataset.address = fromVal;
            editTo.value = toVal;
            editTo.dataset.address = toVal;
            document.getElementById('editDistanceMiles').value = parseFloat(distance) || 0;
            document.getElementById('editDistanceKm').value = (parseFloat(distance) * 1.60934).toFixed(2) || 0;
            document.getElementById('editAthleteId').value = athleteId;
            document.getElementById('editSessionId').value = sessionId;
            
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
            var editFrom = document.getElementById('editFromLocation');
            var editTo = document.getElementById('editToLocation');
            var fromLocation = editFrom.dataset.address || editFrom.value;
            var toLocation = editTo.dataset.address || editTo.value;
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
    
    // Export mileage with current filters
    document.querySelectorAll('[data-action="export-mileage"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            // Get current filter values
            var form = document.getElementById('filterForm');
            var params = new URLSearchParams(new FormData(form));
            params.set('action', 'export_csv');
            window.location.href = 'process_mileage.php?' + params.toString();
        });
    });
});

// Remove stop function
function removeStop(btn) {
    var stopRow = btn.closest('.stop-row');
    stopRow.remove();
}

function closeMileageModal() {
    document.getElementById('edit-mileage-modal').classList.remove('active');
}

function closeDeleteModal() {
    document.getElementById('delete-mileage-modal').classList.remove('active');
}


</script>
