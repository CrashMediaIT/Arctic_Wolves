<?php
/**
 * PWA Financial Reports - Mobile-native report summary
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$monthlyRevenue = 0;
$totalCustomers = 0;
$topProduct = 'N/A';

try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $stmt->execute();
    $monthlyRevenue = (float)$stmt->fetchColumn();
} catch (PDOException $e) { $monthlyRevenue = 0; }

try {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM payments WHERE status = 'completed'");
    $stmt->execute();
    $totalCustomers = (int)$stmt->fetchColumn();
} catch (PDOException $e) { $totalCustomers = 0; }

try {
    $stmt = $pdo->prepare("SELECT description FROM payments WHERE status = 'completed' GROUP BY description ORDER BY COUNT(*) DESC LIMIT 1");
    $stmt->execute();
    $topProduct = $stmt->fetchColumn() ?: 'N/A';
} catch (PDOException $e) { $topProduct = 'N/A'; }
?>
<style>
.m-reports { padding: 16px; font-family: Inter, sans-serif; }
.m-reports-header { margin-bottom: 16px; }
.m-reports-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-reports-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-reports-kpi { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
.m-reports-stat {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; text-align: center;
}
.m-reports-stat-icon { font-size: 16px; margin-bottom: 6px; }
.m-reports-stat-value { font-size: 24px; font-weight: 700; color: #fff; line-height: 1.1; }
.m-reports-stat-label { font-size: 11px; color: #A8A8B8; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.m-reports-top-product {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; margin-bottom: 20px;
}
.m-reports-top-label { font-size: 11px; color: #A8A8B8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
.m-reports-top-name { font-size: 15px; font-weight: 600; color: #fff; }
.m-section-title { font-size: 15px; font-weight: 600; color: #fff; margin: 0 0 12px; }
.m-reports-link {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; text-decoration: none;
    min-height: 44px;
}
.m-reports-link-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
    background: rgba(139,92,246,0.15); color: #8B5CF6;
}
.m-reports-link-text { flex: 1; font-size: 14px; font-weight: 500; color: #fff; }
.m-reports-link-arrow { color: #6B6B7B; font-size: 12px; }
</style>

<div class="m-reports">
    <div class="m-reports-header">
        <h2 class="m-reports-title">Financial Reports</h2>
        <p class="m-reports-sub">Quick summary &amp; report links</p>
    </div>

    <div class="m-reports-kpi">
        <div class="m-reports-stat">
            <div class="m-reports-stat-icon" style="color:#10B981;"><i class="fas fa-dollar-sign"></i></div>
            <div class="m-reports-stat-value">$<?= number_format($monthlyRevenue, 0) ?></div>
            <div class="m-reports-stat-label">Monthly Revenue</div>
        </div>
        <div class="m-reports-stat">
            <div class="m-reports-stat-icon" style="color:#3B82F6;"><i class="fas fa-users"></i></div>
            <div class="m-reports-stat-value"><?= $totalCustomers ?></div>
            <div class="m-reports-stat-label">Total Customers</div>
        </div>
    </div>

    <div class="m-reports-top-product">
        <div class="m-reports-top-label"><i class="fas fa-trophy" style="color:#F59E0B;"></i> Top Selling Item</div>
        <div class="m-reports-top-name"><?= htmlspecialchars($topProduct) ?></div>
    </div>

    <h3 class="m-section-title">Generate Reports</h3>
    <p style="font-size:12px;color:#6B6B7B;margin:0 0 12px;">Full reports are best viewed on desktop</p>

    <a href="?page=reports" class="m-reports-link">
        <div class="m-reports-link-icon"><i class="fas fa-chart-bar"></i></div>
        <span class="m-reports-link-text">Revenue Reports</span>
        <i class="fas fa-chevron-right m-reports-link-arrow"></i>
    </a>
    <a href="?page=reports&type=payments" class="m-reports-link">
        <div class="m-reports-link-icon"><i class="fas fa-credit-card"></i></div>
        <span class="m-reports-link-text">Payment Reports</span>
        <i class="fas fa-chevron-right m-reports-link-arrow"></i>
    </a>
    <a href="?page=reports&type=expenses" class="m-reports-link">
        <div class="m-reports-link-icon"><i class="fas fa-file-invoice-dollar"></i></div>
        <span class="m-reports-link-text">Expense Reports</span>
        <i class="fas fa-chevron-right m-reports-link-arrow"></i>
    </a>
</div>
