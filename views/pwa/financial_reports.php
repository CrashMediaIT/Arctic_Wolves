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

    <div class="m-reports-link" onclick="mFinRepOpen('revenue_summary')" style="cursor:pointer;">
        <div class="m-reports-link-icon" style="background:rgba(16,185,129,0.15);color:#10B981;"><i class="fas fa-dollar-sign"></i></div>
        <span class="m-reports-link-text">Revenue Summary</span>
        <i class="fas fa-chevron-right m-reports-link-arrow"></i>
    </div>
    <div class="m-reports-link" onclick="mFinRepOpen('expense_report')" style="cursor:pointer;">
        <div class="m-reports-link-icon" style="background:rgba(239,68,68,0.15);color:#EF4444;"><i class="fas fa-receipt"></i></div>
        <span class="m-reports-link-text">Expense Report</span>
        <i class="fas fa-chevron-right m-reports-link-arrow"></i>
    </div>
    <div class="m-reports-link" onclick="mFinRepOpen('profit_loss')" style="cursor:pointer;">
        <div class="m-reports-link-icon" style="background:rgba(139,92,246,0.15);color:#8B5CF6;"><i class="fas fa-chart-line"></i></div>
        <span class="m-reports-link-text">Profit &amp; Loss</span>
        <i class="fas fa-chevron-right m-reports-link-arrow"></i>
    </div>
    <div class="m-reports-link" onclick="mFinRepOpen('tax_summary')" style="cursor:pointer;">
        <div class="m-reports-link-icon" style="background:rgba(245,158,11,0.15);color:#F59E0B;"><i class="fas fa-calculator"></i></div>
        <span class="m-reports-link-text">Tax Report</span>
        <i class="fas fa-chevron-right m-reports-link-arrow"></i>
    </div>
</div>

<!-- Generate Report Bottom Sheet -->
<div class="m-reports-overlay" id="mFinRepOverlay" onclick="mFinRepClose()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1000;"></div>
<div class="m-reports-sheet" id="mFinRepSheet" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:1001;background:#16161F;border-radius:16px 16px 0 0;max-height:85vh;overflow-y:auto;padding:20px 16px 32px;font-family:Inter,sans-serif;">
    <div style="width:36px;height:4px;background:#2D2D3F;border-radius:2px;margin:0 auto 16px;"></div>
    <h3 style="font-size:17px;font-weight:700;color:#fff;margin:0 0 16px;" id="mFinRepTitle">Generate Report</h3>
    <form id="mFinRepForm" method="POST">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="generate_report">
        <input type="hidden" name="report_type" id="mFinRepType" value="">

        <label style="font-size:12px;color:#A8A8B8;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:6px;">Date Range</label>
        <select name="date_range" id="mFinRepDateRange" style="width:100%;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:12px;min-height:44px;font-size:14px;margin-bottom:12px;-webkit-appearance:none;">
            <option value="this_month">This Month</option>
            <option value="last_month">Last Month</option>
            <option value="this_quarter">This Quarter</option>
            <option value="last_quarter">Last Quarter</option>
            <option value="this_year">This Year</option>
            <option value="last_year">Last Year</option>
            <option value="custom">Custom Range</option>
        </select>

        <div id="mFinRepCustomDates" style="display:none;margin-bottom:12px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div>
                    <label style="font-size:11px;color:#A8A8B8;display:block;margin-bottom:4px;">Start</label>
                    <input type="date" name="date_from" style="width:100%;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:12px;min-height:44px;font-size:14px;" value="<?= date('Y-m-01') ?>">
                </div>
                <div>
                    <label style="font-size:11px;color:#A8A8B8;display:block;margin-bottom:4px;">End</label>
                    <input type="date" name="date_to" style="width:100%;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:12px;min-height:44px;font-size:14px;" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
        </div>

        <label style="font-size:12px;color:#A8A8B8;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:6px;">Export Format</label>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:16px;">
            <label style="display:flex;align-items:center;justify-content:center;gap:6px;background:#0A0A0F;border:2px solid #6B46C1;border-radius:10px;padding:10px;cursor:pointer;color:#fff;font-size:13px;font-weight:600;min-height:44px;">
                <input type="radio" name="format" value="pdf" checked style="display:none;"> <i class="fas fa-file-pdf" style="color:#EF4444;"></i> PDF
            </label>
            <label style="display:flex;align-items:center;justify-content:center;gap:6px;background:#0A0A0F;border:2px solid #2D2D3F;border-radius:10px;padding:10px;cursor:pointer;color:#fff;font-size:13px;font-weight:600;min-height:44px;">
                <input type="radio" name="format" value="excel" style="display:none;"> <i class="fas fa-file-excel" style="color:#10B981;"></i> Excel
            </label>
            <label style="display:flex;align-items:center;justify-content:center;gap:6px;background:#0A0A0F;border:2px solid #2D2D3F;border-radius:10px;padding:10px;cursor:pointer;color:#fff;font-size:13px;font-weight:600;min-height:44px;">
                <input type="radio" name="format" value="csv" style="display:none;"> <i class="fas fa-file-csv" style="color:#3B82F6;"></i> CSV
            </label>
        </div>

        <button type="submit" style="width:100%;background:#6B46C1;color:#fff;border:none;border-radius:10px;padding:14px;font-size:15px;font-weight:600;min-height:44px;cursor:pointer;">
            <i class="fas fa-chart-bar"></i> Generate Report
        </button>
    </form>
</div>

<script>
const reportNames = {revenue_summary:'Revenue Summary',expense_report:'Expense Report',profit_loss:'Profit & Loss',tax_summary:'Tax Report'};
function mFinRepOpen(type) {
    document.getElementById('mFinRepType').value = type;
    document.getElementById('mFinRepTitle').textContent = reportNames[type] || 'Generate Report';
    document.getElementById('mFinRepOverlay').style.display = 'block';
    document.getElementById('mFinRepSheet').style.display = 'block';
}
function mFinRepClose() {
    document.getElementById('mFinRepOverlay').style.display = 'none';
    document.getElementById('mFinRepSheet').style.display = 'none';
}
document.getElementById('mFinRepDateRange').addEventListener('change', function() {
    document.getElementById('mFinRepCustomDates').style.display = this.value === 'custom' ? 'block' : 'none';
});
document.querySelectorAll('#mFinRepForm input[name="format"]').forEach(function(r) {
    r.addEventListener('change', function() {
        document.querySelectorAll('#mFinRepForm input[name="format"]').forEach(function(el) {
            el.closest('label').style.borderColor = '#2D2D3F';
        });
        this.closest('label').style.borderColor = '#6B46C1';
    });
});
document.getElementById('mFinRepForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    var fd = new FormData(this);
    try {
        var r = await fetch('process_reports.php', {method:'POST', body:fd});
        var d = await r.json();
        if (d.success) { showToast(d.message || 'Report generated', 'success'); mFinRepClose(); }
        else { showToast(d.message || 'Error generating report', 'error'); }
    } catch(err) { showToast('An error occurred', 'error'); }
});
</script>
