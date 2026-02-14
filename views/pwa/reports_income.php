<?php
/**
 * PWA Income Reports - Mobile-native income summary
 * Purpose-built for mobile phones.
 */

if (!$isAdmin) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Admin access required.</p>';
    echo '</div>';
    return;
}

$totalRevenue = 0;
$monthRevenue = 0;
$outstanding = 0;

try {
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'");
    $totalRevenue = (float)$stmt->fetchColumn();
} catch (PDOException $e) { $totalRevenue = 0; }

try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stmt->execute();
    $monthRevenue = (float)$stmt->fetchColumn();
} catch (PDOException $e) { $monthRevenue = 0; }

try {
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'pending'");
    $outstanding = (float)$stmt->fetchColumn();
} catch (PDOException $e) { $outstanding = 0; }
?>
<style>
.m-income { padding: 16px; font-family: Inter, sans-serif; }
.m-income-header { margin-bottom: 16px; }
.m-income-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-income-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-income-grid { display: grid; gap: 12px; margin-bottom: 16px; }
.m-income-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px;
}
.m-income-card-label { font-size: 12px; color: #A8A8B8; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.m-income-card-value { font-size: 28px; font-weight: 700; }
.m-income-card-icon { font-size: 16px; margin-bottom: 6px; }
.m-income-note {
    text-align: center; padding: 24px; color: #6B6B7B; font-size: 13px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
}
</style>

<div class="m-income">
    <div class="m-income-header">
        <h2 class="m-income-title">Income Reports</h2>
        <p class="m-income-sub">Revenue overview</p>
    </div>

    <div class="m-income-grid">
        <div class="m-income-card">
            <div class="m-income-card-icon" style="color:#10B981;"><i class="fas fa-dollar-sign"></i></div>
            <div class="m-income-card-label">Total Revenue</div>
            <div class="m-income-card-value" style="color:#10B981;">$<?= number_format($totalRevenue, 2) ?></div>
        </div>
        <div class="m-income-card">
            <div class="m-income-card-icon" style="color:#3B82F6;"><i class="fas fa-calendar-day"></i></div>
            <div class="m-income-card-label">This Month</div>
            <div class="m-income-card-value" style="color:#3B82F6;">$<?= number_format($monthRevenue, 2) ?></div>
        </div>
        <div class="m-income-card">
            <div class="m-income-card-icon" style="color:#F59E0B;"><i class="fas fa-hourglass-half"></i></div>
            <div class="m-income-card-label">Outstanding</div>
            <div class="m-income-card-value" style="color:#F59E0B;">$<?= number_format($outstanding, 2) ?></div>
        </div>
    </div>

    <!-- Filter & Export -->
    <div style="margin-bottom:16px;">
        <label style="font-size:12px;color:#A8A8B8;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:6px;">Period</label>
        <select id="mIncomePeriod" style="width:100%;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:12px;min-height:44px;font-size:14px;-webkit-appearance:none;" onchange="mIncomeChangePeriod(this.value)">
            <option value="today" <?= ($period ?? '') === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="week" <?= ($period ?? '') === 'week' ? 'selected' : '' ?>>This Week</option>
            <option value="month" <?= (!isset($period) || $period === 'month') ? 'selected' : '' ?>>This Month</option>
            <option value="year" <?= ($period ?? '') === 'year' ? 'selected' : '' ?>>This Year</option>
            <option value="custom" <?= ($period ?? '') === 'custom' ? 'selected' : '' ?>>Custom Range</option>
        </select>
    </div>

    <div id="mIncomeCustomDates" style="display:<?= ($period ?? '') === 'custom' ? 'block' : 'none' ?>;margin-bottom:16px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
            <div>
                <label style="font-size:11px;color:#A8A8B8;display:block;margin-bottom:4px;">Start</label>
                <input type="date" id="mIncomeStart" style="width:100%;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:12px;min-height:44px;font-size:14px;" value="<?= htmlspecialchars($start_date ?? date('Y-m-01')) ?>">
            </div>
            <div>
                <label style="font-size:11px;color:#A8A8B8;display:block;margin-bottom:4px;">End</label>
                <input type="date" id="mIncomeEnd" style="width:100%;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:12px;min-height:44px;font-size:14px;" value="<?= htmlspecialchars($end_date ?? date('Y-m-t')) ?>">
            </div>
        </div>
        <button onclick="mIncomeApplyCustom()" style="width:100%;background:#6B46C1;color:#fff;border:none;border-radius:10px;padding:12px;font-size:14px;font-weight:600;min-height:44px;cursor:pointer;">Apply Range</button>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <button onclick="mIncomeExport('csv')" style="display:flex;align-items:center;justify-content:center;gap:6px;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:12px;font-size:13px;font-weight:600;min-height:44px;cursor:pointer;">
            <i class="fas fa-file-csv" style="color:#3B82F6;"></i> Export CSV
        </button>
        <button onclick="mIncomeExport('pdf')" style="display:flex;align-items:center;justify-content:center;gap:6px;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:12px;font-size:13px;font-weight:600;min-height:44px;cursor:pointer;">
            <i class="fas fa-file-pdf" style="color:#EF4444;"></i> Export PDF
        </button>
    </div>
</div>

<script>
function mIncomeChangePeriod(val) {
    if (val === 'custom') {
        document.getElementById('mIncomeCustomDates').style.display = 'block';
    } else {
        document.getElementById('mIncomeCustomDates').style.display = 'none';
        window.location.href = '?page=reports_income&period=' + val;
    }
}
function mIncomeApplyCustom() {
    var s = document.getElementById('mIncomeStart').value;
    var e = document.getElementById('mIncomeEnd').value;
    window.location.href = '?page=reports_income&period=custom&start_date=' + s + '&end_date=' + e;
}
function mIncomeExport(fmt) {
    if (fmt === 'pdf') { window.print(); return; }
    var rows = [['Period','Total Revenue','This Month','Outstanding']];
    rows.push(['<?= htmlspecialchars($period ?? 'month') ?>','<?= number_format($totalRevenue, 2) ?>','<?= number_format($monthRevenue, 2) ?>','<?= number_format($outstanding, 2) ?>']);
    var csv = rows.map(function(r){ return r.map(function(c){ return '"'+c+'"'; }).join(','); }).join('\n');
    var blob = new Blob([csv], {type:'text/csv'});
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'income_report.csv';
    a.click();
}
</script>
