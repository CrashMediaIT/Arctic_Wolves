<?php
/**
 * PWA HR Payroll - Mobile-native payroll summary
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$canAccessHR) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>HR access required</div>';
    return;
}

$totalActive = 0;
$staffCount = 0;

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_staff, SUM(CASE WHEN role != 'athlete' AND role != 'parent' THEN 1 ELSE 0 END) as staff_count FROM users WHERE is_active = 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalActive = (int)($row['total_staff'] ?? 0);
    $staffCount = (int)($row['staff_count'] ?? 0);
} catch (PDOException $e) { $totalActive = 0; $staffCount = 0; }
?>
<style>
.m-payroll { padding: 16px; font-family: Inter, sans-serif; }
.m-payroll-header { margin-bottom: 16px; }
.m-payroll-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-payroll-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-payroll-kpi { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
.m-payroll-stat {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; text-align: center;
}
.m-payroll-stat-icon { font-size: 16px; margin-bottom: 6px; }
.m-payroll-stat-value { font-size: 28px; font-weight: 700; color: #fff; line-height: 1.1; }
.m-payroll-stat-label { font-size: 11px; color: #A8A8B8; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.m-payroll-notice {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 16px;
    padding: 24px; text-align: center; margin-bottom: 20px;
}
.m-payroll-notice-icon { font-size: 32px; color: #8B5CF6; margin-bottom: 12px; }
.m-payroll-notice-text { font-size: 14px; color: #A8A8B8; margin-bottom: 16px; }
.m-payroll-notice-btn {
    display: inline-flex; align-items: center; gap: 8px;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    color: #fff; padding: 12px 24px; border-radius: 10px;
    text-decoration: none; font-size: 14px; font-weight: 600;
    min-height: 44px;
}
.m-section-title { font-size: 15px; font-weight: 600; color: #fff; margin: 0 0 12px; }
.m-payroll-info {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
}
.m-payroll-info-label { font-size: 12px; color: #A8A8B8; margin-bottom: 4px; }
.m-payroll-info-value { font-size: 15px; font-weight: 600; color: #fff; }
</style>

<div class="m-payroll">
    <div class="m-payroll-header">
        <h2 class="m-payroll-title">Payroll</h2>
        <p class="m-payroll-sub">Payroll summary</p>
    </div>

    <div class="m-payroll-kpi">
        <div class="m-payroll-stat">
            <div class="m-payroll-stat-icon" style="color:#8B5CF6;"><i class="fas fa-users"></i></div>
            <div class="m-payroll-stat-value"><?= $totalActive ?></div>
            <div class="m-payroll-stat-label">Active Users</div>
        </div>
        <div class="m-payroll-stat">
            <div class="m-payroll-stat-icon" style="color:#10B981;"><i class="fas fa-user-tie"></i></div>
            <div class="m-payroll-stat-value"><?= $staffCount ?></div>
            <div class="m-payroll-stat-label">Staff Members</div>
        </div>
    </div>

    <div class="m-payroll-notice">
        <div class="m-payroll-notice-icon"><i class="fas fa-money-check-alt"></i></div>
        <div class="m-payroll-notice-text">Full payroll management is best on desktop</div>
        <a href="?page=payroll" class="m-payroll-notice-btn">
            <i class="fas fa-external-link-alt"></i> View Full Payroll
        </a>
    </div>

    <h3 class="m-section-title">Quick Info</h3>
    <div class="m-payroll-info">
        <div class="m-payroll-info-label">Total Active Staff (excl. athletes &amp; parents)</div>
        <div class="m-payroll-info-value"><?= $staffCount ?> staff member<?= $staffCount !== 1 ? 's' : '' ?></div>
    </div>
    <div class="m-payroll-info">
        <div class="m-payroll-info-label">Total Active Users</div>
        <div class="m-payroll-info-value"><?= $totalActive ?> user<?= $totalActive !== 1 ? 's' : '' ?></div>
    </div>
</div>
