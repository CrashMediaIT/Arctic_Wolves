<?php
/**
 * PWA System Tools - Mobile-native system tools hub
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$tools = [
    ['icon' => 'fa-cog', 'label' => 'Settings', 'page' => 'system_tools', 'tab' => 'settings', 'color' => '#6B46C1'],
    ['icon' => 'fa-database', 'label' => 'Database Tools', 'page' => 'admin_database_tools', 'color' => '#3B82F6'],
    ['icon' => 'fa-heartbeat', 'label' => 'System Check', 'page' => 'admin_system_check', 'color' => '#10B981'],
    ['icon' => 'fa-download', 'label' => 'Database Backup', 'page' => 'admin_database_backup', 'color' => '#8B5CF6'],
    ['icon' => 'fa-upload', 'label' => 'Database Restore', 'page' => 'admin_database_restore', 'color' => '#F59E0B'],
    ['icon' => 'fa-shield-alt', 'label' => 'Security', 'page' => 'admin_security', 'color' => '#EF4444'],
    ['icon' => 'fa-clock', 'label' => 'Cron Jobs', 'page' => 'cron_jobs', 'color' => '#3B82F6'],
    ['icon' => 'fa-file-import', 'label' => 'Feature Import', 'page' => 'admin_feature_import', 'color' => '#10B981'],
    ['icon' => 'fa-palette', 'label' => 'Theme Settings', 'page' => 'admin_theme_settings', 'color' => '#8B5CF6'],
    ['icon' => 'fa-car', 'label' => 'Mileage', 'page' => 'system_tools', 'tab' => 'mileage', 'color' => '#10B981'],
    ['icon' => 'fa-envelope', 'label' => 'SMTP', 'page' => 'system_tools', 'tab' => 'smtp', 'color' => '#3B82F6'],
    ['icon' => 'fa-server', 'label' => 'RustFS Storage', 'page' => 'system_tools', 'tab' => 'rustfs', 'color' => '#F59E0B'],
    ['icon' => 'fa-file-contract', 'label' => 'DocuSeal', 'page' => 'system_tools', 'tab' => 'docuseal', 'color' => '#6B46C1'],
    ['icon' => 'fa-credit-card', 'label' => 'Payments', 'page' => 'system_tools', 'tab' => 'payments', 'color' => '#10B981'],
    ['icon' => 'fa-truck', 'label' => 'Stallion Express', 'page' => 'system_tools', 'tab' => 'stallion', 'color' => '#EF4444'],
    ['icon' => 'fa-file-alt', 'label' => 'Paperless-NGX', 'page' => 'system_tools', 'tab' => 'paperless', 'color' => '#3B82F6'],
    ['icon' => 'fa-lock', 'label' => 'Encryption', 'page' => 'system_tools', 'tab' => 'encryption', 'color' => '#F59E0B'],
    ['icon' => 'fa-globe', 'label' => 'Landing Page', 'page' => 'system_tools', 'tab' => 'landing', 'color' => '#8B5CF6'],
    ['icon' => 'fa-sync-alt', 'label' => 'Updates', 'page' => 'system_tools', 'tab' => 'updates', 'color' => '#6B46C1'],
    ['icon' => 'fa-key', 'label' => 'API Keys', 'page' => 'system_tools', 'tab' => 'api_keys', 'color' => '#10B981'],
    ['icon' => 'fa-video', 'label' => 'NDI Cameras', 'page' => 'system_tools', 'tab' => 'ndi_cameras', 'color' => '#3B82F6'],
    ['icon' => 'fa-chess-board', 'label' => 'Game Plan', 'page' => 'system_tools', 'tab' => 'gameplan', 'color' => '#EF4444'],
];
?>
<style>
.m-systools { padding: 16px; font-family: Inter, sans-serif; }
.m-systools-header { margin-bottom: 16px; }
.m-systools-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-systools-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-systools-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.m-systool-card {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 14px;
    padding: 20px 12px; text-decoration: none; min-height: 100px;
    transition: border-color 0.2s;
}
.m-systool-card:active { border-color: #6B46C1; }
.m-systool-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; margin-bottom: 10px;
}
.m-systool-label { font-size: 12px; font-weight: 600; color: #fff; text-align: center; }
</style>

<div class="m-systools">
    <div class="m-systools-header">
        <h2 class="m-systools-title">System Tools</h2>
        <p class="m-systools-sub">Administration & maintenance</p>
    </div>

    <div class="m-systools-grid">
        <?php foreach ($tools as $t):
            $href = isset($t['tab'])
                ? '?page=' . htmlspecialchars($t['page']) . '&tab=' . htmlspecialchars($t['tab'])
                : '?page=' . htmlspecialchars($t['page']);
        ?>
        <a href="<?= $href ?>" class="m-systool-card">
            <div class="m-systool-icon" style="background:<?= $t['color'] ?>20;color:<?= $t['color'] ?>;">
                <i class="fas <?= $t['icon'] ?>"></i>
            </div>
            <div class="m-systool-label"><?= htmlspecialchars($t['label']) ?></div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
