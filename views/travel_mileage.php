<?php
// Get current mileage rate
$rate_query = "SELECT value FROM settings WHERE setting_key = 'mileage_rate' LIMIT 1";
$mileage_rate = $pdo->query($rate_query)->fetchColumn() ?: 0.65;

// Get filter period
$filter_period = $_GET['period'] ?? 'month';

// Calculate date range
$date_filter = "";
$date_params = [$user_id];

if ($filter_period === 'month') {
    $date_filter = " AND DATE(m.travel_date) >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
} elseif ($filter_period === 'last_month') {
    $date_filter = " AND DATE(m.travel_date) >= DATE_SUB(NOW(), INTERVAL 2 MONTH) AND DATE(m.travel_date) < DATE_SUB(NOW(), INTERVAL 1 MONTH)";
} elseif ($filter_period === '3months') {
    $date_filter = " AND DATE(m.travel_date) >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
} elseif ($filter_period === '6months') {
    $date_filter = " AND DATE(m.travel_date) >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
} elseif ($filter_period === 'year') {
    $date_filter = " AND YEAR(m.travel_date) = YEAR(NOW())";
}

// Get mileage entries
$mileage_query = "
    SELECT m.*,
           (m.distance_miles * m.rate_per_mile) as calculated_amount
    FROM mileage_tracking m
    WHERE m.user_id = ?" . $date_filter . "
    ORDER BY m.travel_date DESC, m.created_at DESC
    LIMIT 100
";

$mileage_stmt = $pdo->prepare($mileage_query);
$mileage_stmt->execute($date_params);
$mileage_entries = $mileage_stmt->fetchAll();

// Calculate summary
$summary = [
    'total_miles' => 0,
    'total_amount' => 0,
    'total_trips' => count($mileage_entries)
];

foreach ($mileage_entries as $entry) {
    $summary['total_miles'] += $entry['distance_miles'];
    $summary['total_amount'] += $entry['calculated_amount'];
}
?>

<!-- Travel Mileage Tracking View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-route"></i> Mileage Tracking
    </h1>
    <p class="page-description">Track and manage your travel mileage for reimbursement</p>
</div>

<div class="mileage-content">
    <!-- Summary Cards -->
    <div class="mileage-summary">
        <div class="summary-card" data-component="StatsCard">
            <div class="summary-icon">
                <i class="fas fa-car"></i>
            </div>
            <div class="summary-details">
                <h4>This Month</h4>
                <p class="summary-value"><?= number_format($summary['total_miles'], 1) ?> miles</p>
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
                <input type="hidden" name="action" value="add_mileage">
                <input type="hidden" name="user_id" value="<?= $user_id ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="travel_date" class="form-input" required max="<?= date('Y-m-d') ?>">
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
                        <label>Distance (miles) *</label>
                        <input type="number" name="distance_miles" class="form-input" placeholder="0.0" step="0.1" min="0" required data-field="distance">
                    </div>
                    <div class="form-group">
                        <label>Rate per Mile</label>
                        <input type="number" name="rate_per_mile" class="form-input" value="<?= $mileage_rate ?>" step="0.01" min="0" readonly data-field="rate">
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
                            <td><?= date('M d, Y', strtotime($entry['travel_date'])) ?></td>
                            <td><?= htmlspecialchars($entry['purpose']) ?></td>
                            <td>
                                <div class="route-info">
                                    <span class="route-from"><?= htmlspecialchars($entry['from_location']) ?></span>
                                    <i class="fas fa-arrow-right"></i>
                                    <span class="route-to"><?= htmlspecialchars($entry['to_location']) ?></span>
                                </div>
                            </td>
                            <td><?= number_format($entry['distance_miles'], 1) ?> mi</td>
                            <td>$<?= number_format($entry['calculated_amount'], 2) ?></td>
                            <td>
                                <span class="status-badge <?= $entry['status'] ?>">
                                    <?= ucfirst($entry['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <?php if ($entry['status'] === 'pending'): ?>
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
    margin-bottom: 30px;
}

.summary-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 25px;
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
    padding: 15px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border);
}

.mileage-table td {
    padding: 15px;
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
