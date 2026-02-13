<?php
/**
 * PWA Reports - Mobile-native accounting reports
 * Purpose-built for mobile phones.
 */

if (!$isAdmin) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Admin access required.</p>';
    echo '</div>';
    return;
}
?>
<style>
.m-reports { padding: 16px; font-family: Inter, sans-serif; }
.m-reports-header { margin-bottom: 16px; }
.m-reports-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-reports-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-report-link {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
    text-decoration: none; min-height: 44px;
}
.m-report-icon {
    width: 44px; height: 44px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.m-report-info { flex: 1; min-width: 0; }
.m-report-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-report-desc { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-report-chevron { color: #6B6B7B; font-size: 14px; flex-shrink: 0; }
</style>

<div class="m-reports">
    <div class="m-reports-header">
        <h2 class="m-reports-title">Reports</h2>
        <p class="m-reports-sub">Accounting & analytics</p>
    </div>

    <a href="?page=reports_income" class="m-report-link">
        <div class="m-report-icon" style="background:rgba(16,185,129,0.15);color:#10B981;"><i class="fas fa-dollar-sign"></i></div>
        <div class="m-report-info">
            <div class="m-report-name">Income Reports</div>
            <div class="m-report-desc">Revenue summaries and trends</div>
        </div>
        <i class="fas fa-chevron-right m-report-chevron"></i>
    </a>

    <a href="?page=reports_athlete" class="m-report-link">
        <div class="m-report-icon" style="background:rgba(59,130,246,0.15);color:#3B82F6;"><i class="fas fa-chart-line"></i></div>
        <div class="m-report-info">
            <div class="m-report-name">Athlete Reports</div>
            <div class="m-report-desc">Performance metrics & stats</div>
        </div>
        <i class="fas fa-chevron-right m-report-chevron"></i>
    </a>

    <a href="?page=financial_reports" class="m-report-link">
        <div class="m-report-icon" style="background:rgba(245,158,11,0.15);color:#F59E0B;"><i class="fas fa-file-invoice"></i></div>
        <div class="m-report-info">
            <div class="m-report-name">Financial Reports</div>
            <div class="m-report-desc">Detailed financial breakdowns</div>
        </div>
        <i class="fas fa-chevron-right m-report-chevron"></i>
    </a>

    <a href="?page=scheduled_reports" class="m-report-link">
        <div class="m-report-icon" style="background:rgba(107,70,193,0.15);color:#8B5CF6;"><i class="fas fa-clock"></i></div>
        <div class="m-report-info">
            <div class="m-report-name">Scheduled Reports</div>
            <div class="m-report-desc">Automated report schedules</div>
        </div>
        <i class="fas fa-chevron-right m-report-chevron"></i>
    </a>
</div>
