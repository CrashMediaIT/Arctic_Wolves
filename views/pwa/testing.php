<?php
/**
 * PWA Testing - Mobile-native testing/sandbox page
 * Purpose-built for mobile phones.
 */

// Permission check — match desktop views/testing.php
if (!$isAdmin) {
    echo '<div style="text-align:center;padding:60px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:48px;display:block;margin-bottom:16px;opacity:0.5;"></i>';
    echo '<h3 style="color:#fff;">Access Denied</h3>';
    echo '<p style="font-size:14px;">Admin privileges required.</p>';
    echo '</div>';
    return;
}
?>
<style>
.m-testing { padding: 16px; font-family: Inter, sans-serif; }
.m-testing-header { margin-bottom: 16px; }
.m-testing-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-testing-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-testing-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; margin-bottom: 12px;
}
.m-testing-card-title { font-size: 13px; font-weight: 600; color: #6B6B7B; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 8px; }
.m-testing-row {
    display: flex; justify-content: space-between; padding: 8px 0;
    border-bottom: 1px solid #2D2D3F; font-size: 13px;
}
.m-testing-row:last-child { border-bottom: none; }
.m-testing-label { color: #A8A8B8; }
.m-testing-value { color: #fff; font-weight: 500; }
</style>

<div class="m-testing">
    <div class="m-testing-header">
        <h2 class="m-testing-title">Testing Environment</h2>
        <p class="m-testing-sub">System information & diagnostics</p>
    </div>

    <div class="m-testing-card">
        <h3 class="m-testing-card-title">Server Info</h3>
        <div class="m-testing-row">
            <span class="m-testing-label">PHP Version</span>
            <span class="m-testing-value"><?= htmlspecialchars(phpversion()) ?></span>
        </div>
        <div class="m-testing-row">
            <span class="m-testing-label">Server Software</span>
            <span class="m-testing-value"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') ?></span>
        </div>
        <div class="m-testing-row">
            <span class="m-testing-label">Server Time</span>
            <span class="m-testing-value"><?= date('Y-m-d H:i:s') ?></span>
        </div>
        <div class="m-testing-row">
            <span class="m-testing-label">Timezone</span>
            <span class="m-testing-value"><?= htmlspecialchars(date_default_timezone_get()) ?></span>
        </div>
    </div>

    <div class="m-testing-card">
        <h3 class="m-testing-card-title">Session Info</h3>
        <div class="m-testing-row">
            <span class="m-testing-label">User ID</span>
            <span class="m-testing-value"><?= (int)$user_id ?></span>
        </div>
        <div class="m-testing-row">
            <span class="m-testing-label">User Role</span>
            <span class="m-testing-value"><?= htmlspecialchars($user_role ?? 'N/A') ?></span>
        </div>
        <div class="m-testing-row">
            <span class="m-testing-label">User Name</span>
            <span class="m-testing-value"><?= htmlspecialchars($user_name ?? 'N/A') ?></span>
        </div>
    </div>
</div>
