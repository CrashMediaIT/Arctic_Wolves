<?php
/**
 * POS Transactions View
 * View history of POS transactions
 */

// Check access
if (!$canAccessPOS && !$isAdmin) {
    echo '<div style="text-align: center; padding: 60px;"><h2>Access Denied</h2><p>You do not have permission to access this page.</p></div>';
    return;
}

// Check IP whitelist for POS access (admins exempt)
if (!checkPOSIPAccess($pdo, $user_role)) {
    logSecurityEvent('pos_ip_blocked', 'POS access denied from unauthorized IP', ['ip' => getClientIP(), 'page' => 'pos_transactions']);
    echo '<div style="text-align: center; padding: 60px;"><h2>Access Denied</h2><p>POS access is not available from this location. Please contact an administrator.</p></div>';
    return;
}

// Fetch transactions
$dateFilter = $_GET['date'] ?? '';
$paymentFilter = $_GET['payment'] ?? '';
$currentPage = max(1, intval($_GET['pg'] ?? 1));
$perPage = 25;
$offset = ($currentPage - 1) * $perPage;

$where = ["1=1"];
$params = [];

if ($dateFilter) {
    $where[] = "DATE(pt.created_at) = ?";
    $params[] = $dateFilter;
}

if ($paymentFilter) {
    $where[] = "pt.payment_method = ?";
    $params[] = $paymentFilter;
}

$whereClause = implode(' AND ', $where);

try {
    // Count total
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM pos_transactions pt WHERE $whereClause");
    $countStmt->execute($params);
    $totalTransactions = $countStmt->fetchColumn();
    $totalPages = ceil($totalTransactions / $perPage);
    
    // Fetch transactions
    $stmt = $pdo->prepare("
        SELECT pt.*, 
               u.first_name, u.last_name,
               (SELECT COUNT(*) FROM pos_transaction_items WHERE transaction_id = pt.id) as item_count
        FROM pos_transactions pt
        LEFT JOIN users u ON pt.staff_id = u.id
        WHERE $whereClause
        ORDER BY pt.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $perPage;
    $params[] = $offset;
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $transactions = decryptUserRows($transactions);
    
    // Get stats for today
    $todayStmt = $pdo->query("
        SELECT 
            COUNT(*) as transaction_count,
            SUM(total) as total_sales,
            SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END) as card_sales,
            SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END) as cash_sales
        FROM pos_transactions
        WHERE DATE(created_at) = CURDATE() AND status = 'completed'
    ");
    $todayStats = $todayStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("POS transactions fetch error: " . $e->getMessage());
    $transactions = [];
    $todayStats = [];
}

// Check if being loaded as a tab in Finance Dashboard
$in_finance_dashboard = (isset($tab) && in_array($tab, ['pos_transactions', 'shop_orders']));
?>

<?php if (!$in_finance_dashboard): ?>
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-receipt"></i> POS Transactions</h1>
        <p class="page-description">View point of sale transaction history</p>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value"><?= $todayStats['transaction_count'] ?? 0 ?></span>
            <span class="stat-label">Today</span>
        </div>
        <div class="header-stat">
            <span class="stat-value">$<?= number_format($todayStats['total_sales'] ?? 0, 0) ?></span>
            <span class="stat-label">Today's Sales</span>
        </div>
        <div class="header-stat">
            <span class="stat-value" style="color: #3b82f6;">$<?= number_format($todayStats['card_sales'] ?? 0, 0) ?></span>
            <span class="stat-label">Card</span>
        </div>
        <div class="header-stat">
            <span class="stat-value" style="color: #10b981;">$<?= number_format($todayStats['cash_sales'] ?? 0, 0) ?></span>
            <span class="stat-label">Cash</span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stats Row for tab view -->
<?php if ($in_finance_dashboard): ?>
<div class="stats-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="stat-card" style="background: var(--bg-main); border: 1px solid var(--border); border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 16px;">
        <div class="stat-icon" style="width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; background: rgba(107, 70, 193, 0.15); color: var(--primary);">
            <i class="fas fa-receipt"></i>
        </div>
        <div>
            <h4 style="font-size: 24px; font-weight: 800; color: var(--text-white); margin: 0;"><?= $todayStats['transaction_count'] ?? 0 ?></h4>
            <p style="font-size: 12px; color: var(--text-dim); margin: 4px 0 0 0; text-transform: uppercase; letter-spacing: 0.5px;">Today's Transactions</p>
        </div>
    </div>
    <div class="stat-card" style="background: var(--bg-main); border: 1px solid var(--border); border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 16px;">
        <div class="stat-icon" style="width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; background: rgba(16, 185, 129, 0.15); color: #10b981;">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <div>
            <h4 style="font-size: 24px; font-weight: 800; color: var(--text-white); margin: 0;">$<?= number_format($todayStats['total_sales'] ?? 0, 0) ?></h4>
            <p style="font-size: 12px; color: var(--text-dim); margin: 4px 0 0 0; text-transform: uppercase; letter-spacing: 0.5px;">Today's Sales</p>
        </div>
    </div>
    <div class="stat-card" style="background: var(--bg-main); border: 1px solid var(--border); border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 16px;">
        <div class="stat-icon" style="width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; background: rgba(59, 130, 246, 0.15); color: #3b82f6;">
            <i class="fas fa-credit-card"></i>
        </div>
        <div>
            <h4 style="font-size: 24px; font-weight: 800; color: var(--text-white); margin: 0;">$<?= number_format($todayStats['card_sales'] ?? 0, 0) ?></h4>
            <p style="font-size: 12px; color: var(--text-dim); margin: 4px 0 0 0; text-transform: uppercase; letter-spacing: 0.5px;">Card Sales</p>
        </div>
    </div>
    <div class="stat-card" style="background: var(--bg-main); border: 1px solid var(--border); border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 16px;">
        <div class="stat-icon" style="width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; background: rgba(16, 185, 129, 0.15); color: #10b981;">
            <i class="fas fa-money-bill"></i>
        </div>
        <div>
            <h4 style="font-size: 24px; font-weight: 800; color: var(--text-white); margin: 0;">$<?= number_format($todayStats['cash_sales'] ?? 0, 0) ?></h4>
            <p style="font-size: 12px; color: var(--text-dim); margin: 4px 0 0 0; text-transform: uppercase; letter-spacing: 0.5px;">Cash Sales</p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Determine base URL for filters
$posBaseUrl = $in_finance_dashboard ? '?page=finance_dashboard&tab=pos_transactions' : '?page=pos_transactions';
?>

<div class="content-card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <h3><i class="fas fa-history"></i> Transaction History</h3>
        
        <form method="GET" action="" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if ($in_finance_dashboard): ?>
            <input type="hidden" name="page" value="finance_dashboard">
            <input type="hidden" name="tab" value="pos_transactions">
            <?php else: ?>
            <input type="hidden" name="page" value="pos_transactions">
            <?php endif; ?>
            <input type="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>" class="form-input" style="width: auto;">
            <select name="payment" class="form-input" style="width: auto;">
                <option value="">All Payments</option>
                <option value="card" <?= $paymentFilter === 'card' ? 'selected' : '' ?>>Card</option>
                <option value="cash" <?= $paymentFilter === 'cash' ? 'selected' : '' ?>>Cash</option>
                <option value="mixed" <?= $paymentFilter === 'mixed' ? 'selected' : '' ?>>Mixed</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <?php if ($dateFilter || $paymentFilter): ?>
                <a href="<?= $posBaseUrl ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="card-body">
        <?php if (empty($transactions)): ?>
            <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-receipt" style="font-size: 48px; color: var(--text-dim); margin-bottom: 16px;"></i>
                <p style="color: var(--text-dim);">No transactions found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Transaction #</th>
                            <th>Date/Time</th>
                            <th>Staff</th>
                            <th>Items</th>
                            <th>Payment</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $trans): ?>
                            <tr>
                                <td>
                                    <span style="font-weight: 600; font-family: monospace;"><?= htmlspecialchars($trans['transaction_number']) ?></span>
                                </td>
                                <td>
                                    <div style="font-size: 13px;">
                                        <?= date('M j, Y', strtotime($trans['created_at'])) ?>
                                        <div style="color: var(--text-dim); font-size: 11px;"><?= date('g:i:s A', strtotime($trans['created_at'])) ?></div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars(($trans['first_name'] ?? '') . ' ' . ($trans['last_name'] ?? '') ?: 'Unknown') ?></td>
                                <td><?= $trans['item_count'] ?></td>
                                <td>
                                    <?php
                                    $paymentIcons = ['card' => 'fa-credit-card', 'cash' => 'fa-money-bill', 'mixed' => 'fa-coins'];
                                    $paymentColors = ['card' => '#3b82f6', 'cash' => '#10b981', 'mixed' => '#f59e0b'];
                                    $icon = $paymentIcons[$trans['payment_method']] ?? 'fa-dollar-sign';
                                    $color = $paymentColors[$trans['payment_method']] ?? '#6b7280';
                                    ?>
                                    <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: <?= $color ?>20; color: <?= $color ?>;">
                                        <i class="fas <?= $icon ?>"></i>
                                        <?= ucfirst($trans['payment_method']) ?>
                                    </span>
                                </td>
                                <td style="font-weight: 700; color: var(--primary);">$<?= number_format($trans['total'], 2) ?></td>
                                <td>
                                    <?php
                                    $statusColors = [
                                        'completed' => '#10b981',
                                        'pending' => '#f59e0b',
                                        'cancelled' => '#ef4444',
                                        'refunded' => '#8b5cf6'
                                    ];
                                    $statusColor = $statusColors[$trans['status']] ?? '#6b7280';
                                    ?>
                                    <span style="padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: <?= $statusColor ?>20; color: <?= $statusColor ?>;">
                                        <?= ucfirst($trans['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn-action" onclick="viewTransactionDetails(<?= $trans['id'] ?>)" title="View Details" style="padding: 6px 10px; border: none; border-radius: 6px; background: rgba(107, 70, 193, 0.1); color: var(--primary-light); cursor: pointer;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination" style="display: flex; justify-content: center; gap: 8px; margin-top: 20px;">
                    <?php if ($currentPage > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['pg' => $currentPage - 1])) ?>" class="page-link" style="padding: 8px 14px; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; color: #fff; text-decoration: none;">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['pg' => $i])) ?>" class="page-link <?= $i === $currentPage ? 'active' : '' ?>" style="padding: 8px 14px; background: <?= $i === $currentPage ? 'var(--primary)' : 'var(--bg)' ?>; border: 1px solid <?= $i === $currentPage ? 'var(--primary)' : 'var(--border)' ?>; border-radius: 6px; color: #fff; text-decoration: none;">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['pg' => $currentPage + 1])) ?>" class="page-link" style="padding: 8px 14px; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; color: #fff; text-decoration: none;">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Transaction Details Modal -->
<div id="transaction-details-modal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-receipt"></i> Transaction Details</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('transaction-details-modal')">&times;</button>
        </div>
        <div class="modal-body" id="transaction-details-content">
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: var(--primary);"></i>
            </div>
        </div>
    </div>
</div>

<style>
.data-table {
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 14px 12px;
    text-align: left;
    border-bottom: 1px solid var(--border);
}

.data-table th {
    font-size: 12px;
    text-transform: uppercase;
    color: var(--text-dim);
    font-weight: 600;
}

.data-table tr:hover {
    background: rgba(107, 70, 193, 0.05);
}
</style>

<script>
function viewTransactionDetails(transactionId) {
    const modal = document.getElementById('transaction-details-modal');
    const content = document.getElementById('transaction-details-content');
    
    content.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 32px; color: var(--primary);"></i></div>';
    modal.classList.add('active');
    
    fetch('ajax_get_pos_transaction.php?id=' + transactionId)
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = '<p style="color: #ef4444; text-align: center;">Failed to load transaction details</p>';
        });
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}
</script>
