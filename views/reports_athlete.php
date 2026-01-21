<?php
// views/reports_athlete.php - Per-athlete itemized billing
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once 'security.php';

$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$athlete_id = isset($_GET['athlete_id']) ? intval($_GET['athlete_id']) : null;

// Get all athletes with bookings
$athletes_stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.email,
           COUNT(b.id) as booking_count,
           SUM(b.amount_paid) as total_spent
    FROM users u
    JOIN bookings b ON (u.id = b.user_id OR u.id = b.booked_for_user_id)
    WHERE b.status = 'paid' AND YEAR(b.created_at) = ?
    GROUP BY u.id
    ORDER BY u.last_name, u.first_name
");
$athletes_stmt->execute([$selected_year]);
$athletes = $athletes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get detailed billing for selected athlete
$bookings = [];
if ($athlete_id) {
    $bookings_stmt = $pdo->prepare("
        SELECT b.*, 
               s.title as session_title, s.session_date, s.session_time,
               p.name as package_name
        FROM bookings b
        LEFT JOIN sessions s ON b.session_id = s.id
        LEFT JOIN packages p ON b.package_id = p.id
        WHERE (b.user_id = ? OR b.booked_for_user_id = ?)
          AND b.status = 'paid'
          AND YEAR(b.created_at) = ?
        ORDER BY b.created_at DESC
    ");
    $bookings_stmt->execute([$athlete_id, $athlete_id, $selected_year]);
    $bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $athlete_info = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $athlete_info->execute([$athlete_id]);
    $athlete = $athlete_info->fetch(PDO::FETCH_ASSOC);
}

$settings = $pdo->query("SELECT * FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$tax_name = $settings['tax_name'] ?? 'HST';
?>

<div class="athlete-billing">
    <div class="page-header">
        <h2><i class="fas fa-user-tag"></i> Athlete Billing Reports</h2>
        <select id="yearSelector" onchange="changeYear(this.value)" class="year-select">
            <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                    <?php echo $y; ?>
                </option>
            <?php endfor; ?>
        </select>
    </div>

    <div class="content-grid">
        <!-- Athletes List -->
        <div class="athletes-panel">
            <h3>Athletes (<?php echo count($athletes); ?>)</h3>
            <div class="athletes-list">
                <?php foreach ($athletes as $ath): ?>
                <a href="?page=reports_athlete&year=<?php echo $selected_year; ?>&athlete_id=<?php echo $ath['id']; ?>" 
                   class="athlete-card <?php echo $athlete_id == $ath['id'] ? 'active' : ''; ?>">
                    <div class="athlete-name">
                        <?php echo htmlspecialchars($ath['first_name'] . ' ' . $ath['last_name']); ?>
                    </div>
                    <div class="athlete-stats">
                        <span><?php echo $ath['booking_count']; ?> bookings</span>
                        <span class="amount">$<?php echo number_format($ath['total_spent'], 2); ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php if (empty($athletes)): ?>
                    <p class="empty-state">No athlete bookings in <?php echo $selected_year; ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Athlete Details -->
        <div class="details-panel">
            <?php if ($athlete_id && !empty($athlete)): ?>
                <div class="athlete-header">
                    <h3><?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?></h3>
                    <p><?php echo htmlspecialchars($athlete['email']); ?></p>
                    <button onclick="exportAthleteReport()" class="btn-export">
                        <i class="fas fa-file-pdf"></i> Export Report
                    </button>
                </div>

                <div class="billing-summary">
                    <div class="summary-item">
                        <span>Total Bookings:</span>
                        <strong><?php echo count($bookings); ?></strong>
                    </div>
                    <div class="summary-item">
                        <span>Total Spent:</span>
                        <strong>$<?php echo number_format(array_sum(array_column($bookings, 'amount_paid')), 2); ?></strong>
                    </div>
                    <div class="summary-item">
                        <span>Subtotal:</span>
                        <strong>$<?php echo number_format(array_sum(array_column($bookings, 'original_price')), 2); ?></strong>
                    </div>
                    <div class="summary-item">
                        <span><?php echo $tax_name; ?>:</span>
                        <strong>$<?php echo number_format(array_sum(array_column($bookings, 'tax_amount')), 2); ?></strong>
                    </div>
                </div>

                <div class="bookings-table">
                    <table id="athleteBookings">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th>Subtotal</th>
                                <th>Tax</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($booking['created_at'])); ?></td>
                                <td>
                                    <?php 
                                    if ($booking['payment_type'] === 'package') {
                                        echo htmlspecialchars($booking['package_name'] ?? 'Package Purchase');
                                    } else {
                                        echo htmlspecialchars($booking['session_title']);
                                        if ($booking['session_date']) {
                                            echo '<br><small>' . date('M j, Y g:i A', strtotime($booking['session_date'] . ' ' . $booking['session_time'])) . '</small>';
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
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-selection">
                    <i class="fas fa-user" style="font-size: 64px; color: #334155; margin-bottom: 20px;"></i>
                    <p>Select an athlete to view their billing details</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.athlete-billing {
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.year-select {
    padding: 8px 15px;
    background: #020305;
    border: 1px solid #334155;
    color: #e2e8f0;
    border-radius: 6px;
}

.content-grid {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 20px;
    height: calc(100vh - 200px);
}

.athletes-panel,
.details-panel {
    background: #0a0f16;
    border-radius: 10px;
    padding: 20px;
    overflow-y: auto;
}

.athletes-panel h3,
.details-panel h3 {
    margin: 0 0 20px 0;
    color: #e2e8f0;
}

.athletes-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.athlete-card {
    padding: 15px;
    background: #020305;
    border: 1px solid #334155;
    border-radius: 8px;
    text-decoration: none;
    color: #e2e8f0;
    transition: all 0.2s;
}

.athlete-card:hover,
.athlete-card.active {
    background: var(--primary, #7000a4);
    border-color: var(--primary, #7000a4);
    color: white;
}

.athlete-name {
    font-weight: 600;
    margin-bottom: 8px;
}

.athlete-stats {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    opacity: 0.8;
}

.athlete-header {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #334155;
}

.athlete-header h3 {
    margin-bottom: 5px;
}

.athlete-header p {
    color: #64748b;
    margin: 0 0 15px 0;
}

.btn-export {
    background: var(--primary, #7000a4);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
}

.billing-summary {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #334155;
    color: #94a3b8;
}

.summary-item:last-child {
    border-bottom: none;
}

.bookings-table {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    padding: 12px;
    background: #020305;
    color: #94a3b8;
    text-align: left;
    font-size: 12px;
    text-transform: uppercase;
}

td {
    padding: 12px;
    border-bottom: 1px solid #1e293b;
    color: #e2e8f0;
}

.badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
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

.empty-selection {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #64748b;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #64748b;
}

@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 1fr;
        height: auto;
    }
}
</style>

<script>
function changeYear(year) {
    const athleteId = new URLSearchParams(window.location.search).get('athlete_id');
    let url = '?page=reports_athlete&year=' + year;
    if (athleteId) url += '&athlete_id=' + athleteId;
    window.location.href = url;
}

function exportAthleteReport() {
    window.print();
}
</script>
