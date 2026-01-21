<?php
// views/reports_income.php - Income reports with filtering
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once 'security.php';

// Get filter parameters
$period = $_GET['period'] ?? 'month';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Calculate date range based on period
if ($period === 'today') {
    $start_date = $end_date = date('Y-m-d');
} elseif ($period === 'week') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = date('Y-m-d', strtotime('sunday this week'));
} elseif ($period === 'month') {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
} elseif ($period === 'year') {
    $start_date = date('Y-01-01');
    $end_date = date('Y-12-31');
}

// Get detailed bookings
$bookings_stmt = $pdo->prepare("
    SELECT b.*, 
           s.title as session_title, s.session_date, s.session_time,
           p.name as package_name,
           u.first_name, u.last_name, u.email,
           athlete.first_name as athlete_first, athlete.last_name as athlete_last
    FROM bookings b
    LEFT JOIN sessions s ON b.session_id = s.id
    LEFT JOIN packages p ON b.package_id = p.id
    JOIN users u ON b.user_id = u.id
    LEFT JOIN users athlete ON b.booked_for_user_id = athlete.id
    WHERE b.status = 'paid' AND b.created_at BETWEEN ? AND ?
    ORDER BY b.created_at DESC
");
$bookings_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$subtotal = array_sum(array_column($bookings, 'original_price'));
$tax = array_sum(array_column($bookings, 'tax_amount'));
$total = array_sum(array_column($bookings, 'amount_paid'));

$settings = $pdo->query("SELECT * FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$tax_name = $settings['tax_name'] ?? 'HST';
?>

<div class="income-reports">
    <div class="page-header">
        <h2><i class="fas fa-file-invoice-dollar"></i> Income Reports</h2>
        <div class="header-actions">
            <button onclick="exportToPDF()" class="btn-secondary">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
            <button onclick="exportToCSV()" class="btn-secondary">
                <i class="fas fa-file-csv"></i> Export CSV
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <div class="filter-group">
            <label>Quick Period:</label>
            <select id="periodFilter" onchange="changePeriod(this.value)" class="form-select">
                <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>This Week</option>
                <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>This Month</option>
                <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>This Year</option>
                <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
            </select>
        </div>

        <div id="customDateRange" style="display: <?php echo $period === 'custom' ? 'flex' : 'none'; ?>; gap: 10px;">
            <input type="date" id="startDate" value="<?php echo $start_date; ?>" class="form-input">
            <input type="date" id="endDate" value="<?php echo $end_date; ?>" class="form-input">
            <button onclick="applyCustomRange()" class="btn-primary">Apply</button>
        </div>
    </div>

    <!-- Summary -->
    <div class="report-summary">
        <div class="summary-row">
            <span>Period:</span>
            <strong><?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?></strong>
        </div>
        <div class="summary-row">
            <span>Total Bookings:</span>
            <strong><?php echo count($bookings); ?></strong>
        </div>
        <div class="summary-row">
            <span>Subtotal:</span>
            <strong>$<?php echo number_format($subtotal, 2); ?></strong>
        </div>
        <div class="summary-row">
            <span><?php echo $tax_name; ?>:</span>
            <strong>$<?php echo number_format($tax, 2); ?></strong>
        </div>
        <div class="summary-row total">
            <span>Total Revenue:</span>
            <strong>$<?php echo number_format($total, 2); ?></strong>
        </div>
    </div>

    <!-- Detailed Table -->
    <div class="table-container">
        <table id="incomeTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Item</th>
                    <th>Type</th>
                    <th>Subtotal</th>
                    <th><?php echo $tax_name; ?></th>
                    <th>Total</th>
                    <th>Payment ID</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                <tr>
                    <td><?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?></td>
                    <td>
                        <?php 
                        $customer_name = $booking['athlete_first'] 
                            ? $booking['athlete_first'] . ' ' . $booking['athlete_last']
                            : $booking['first_name'] . ' ' . $booking['last_name'];
                        echo htmlspecialchars($customer_name);
                        ?>
                        <br><small style="color: #64748b;"><?php echo htmlspecialchars($booking['email']); ?></small>
                    </td>
                    <td>
                        <?php 
                        if ($booking['payment_type'] === 'package') {
                            echo htmlspecialchars($booking['package_name'] ?? 'Package');
                        } else {
                            echo htmlspecialchars($booking['session_title']);
                            if ($booking['session_date']) {
                                echo '<br><small style="color: #64748b;">' . 
                                     date('M j, Y', strtotime($booking['session_date'])) . '</small>';
                            }
                        }
                        ?>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo $booking['payment_type']; ?>">
                            <?php echo ucfirst($booking['payment_type']); ?>
                        </span>
                    </td>
                    <td>$<?php echo number_format($booking['original_price'], 2); ?></td>
                    <td>$<?php echo number_format($booking['tax_amount'], 2); ?></td>
                    <td><strong>$<?php echo number_format($booking['amount_paid'], 2); ?></strong></td>
                    <td><code style="font-size: 11px;"><?php echo substr($booking['stripe_session_id'], 0, 20); ?>...</code></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($bookings)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px; color: #64748b;">
                        No bookings found for this period.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.income-reports {
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.btn-secondary {
    background: #334155;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-secondary:hover {
    background: #475569;
}

.filters-section {
    background: #0a0f16;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    gap: 20px;
    align-items: center;
}

.filter-group {
    display: flex;
    gap: 10px;
    align-items: center;
}

.filter-group label {
    color: #94a3b8;
    font-size: 14px;
}

.form-select,
.form-input {
    padding: 8px 12px;
    background: #020305;
    border: 1px solid #334155;
    border-radius: 6px;
    color: #e2e8f0;
    font-size: 14px;
}

.btn-primary {
    background: var(--primary, #7000a4);
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 6px;
    cursor: pointer;
}

.report-summary {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid var(--primary, #7000a4);
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #334155;
    color: #94a3b8;
}

.summary-row:last-child {
    border-bottom: none;
}

.summary-row.total {
    margin-top: 10px;
    padding-top: 15px;
    border-top: 2px solid #334155;
    font-size: 18px;
    color: #e2e8f0;
}

.table-container {
    background: #0a0f16;
    border-radius: 10px;
    overflow: hidden;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: #020305;
}

th {
    padding: 15px;
    text-align: left;
    color: #94a3b8;
    font-size: 12px;
    text-transform: uppercase;
    font-weight: 600;
}

td {
    padding: 15px;
    color: #e2e8f0;
    border-bottom: 1px solid #1e293b;
}

.badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 600;
}

.badge-session {
    background: #3b82f6;
    color: white;
}

.badge-package {
    background: #7000a4;
    color: white;
}
</style>

<script>
function changePeriod(period) {
    if (period === 'custom') {
        document.getElementById('customDateRange').style.display = 'flex';
    } else {
        document.getElementById('customDateRange').style.display = 'none';
        window.location.href = '?page=reports_income&period=' + period;
    }
}

function applyCustomRange() {
    const start = document.getElementById('startDate').value;
    const end = document.getElementById('endDate').value;
    window.location.href = `?page=reports_income&period=custom&start_date=${start}&end_date=${end}`;
}

function exportToCSV() {
    const table = document.getElementById('incomeTable');
    let csv = [];
    
    for (let row of table.rows) {
        let cols = [];
        for (let cell of row.cells) {
            cols.push('"' + cell.innerText.replace(/"/g, '""') + '"');
        }
        csv.push(cols.join(','));
    }
    
    const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    downloadLink.download = 'income_report_<?php echo $start_date; ?>_to_<?php echo $end_date; ?>.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.click();
}

function exportToPDF() {
    window.print();
}
</script>
