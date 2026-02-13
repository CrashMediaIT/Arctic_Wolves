<?php
/**
 * PWA Admin Theme Settings - Mobile-native theme settings view
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$theme = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM settings WHERE setting_key LIKE 'theme%' ORDER BY setting_key");
    $stmt->execute();
    $theme = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) { $theme = []; }
?>
<style>
.m-theme { padding: 16px; font-family: Inter, sans-serif; }
.m-theme-header { margin-bottom: 16px; }
.m-theme-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-theme-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-theme-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 14px;
    padding: 24px 20px; text-align: center;
}
.m-theme-icon {
    width: 56px; height: 56px; border-radius: 16px; margin: 0 auto 16px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(139,92,246,0.15); color: #8B5CF6; font-size: 24px;
}
.m-theme-label { font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 8px; }
.m-theme-desc { font-size: 13px; color: #A8A8B8; margin-bottom: 20px; line-height: 1.5; }
.m-theme-info {
    background: #0A0A0F; border-radius: 10px; padding: 12px; margin-bottom: 20px;
    text-align: left;
}
.m-theme-row {
    display: flex; justify-content: space-between; padding: 6px 0;
    font-size: 12px; border-bottom: 1px solid #2D2D3F;
}
.m-theme-row:last-child { border-bottom: none; }
.m-theme-key { color: #A8A8B8; }
.m-theme-val { color: #fff; font-weight: 600; }
.m-theme-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 24px; border-radius: 10px;
    background: rgba(107,70,193,0.15); color: #8B5CF6;
    font-size: 14px; font-weight: 600; text-decoration: none;
    min-height: 44px; font-family: Inter, sans-serif;
}
</style>

<div class="m-theme">
    <div class="m-theme-header">
        <h2 class="m-theme-title">Theme Settings</h2>
        <p class="m-theme-sub">Appearance & branding</p>
    </div>

    <div class="m-theme-card">
        <div class="m-theme-icon"><i class="fas fa-palette"></i></div>
        <div class="m-theme-label">Current Theme</div>

        <?php if (!empty($theme)): ?>
        <div class="m-theme-info">
            <?php foreach ($theme as $key => $val): ?>
            <div class="m-theme-row">
                <span class="m-theme-key"><?= htmlspecialchars(ucwords(str_replace(['theme_', '_'], ['', ' '], $key))) ?></span>
                <span class="m-theme-val"><?= htmlspecialchars($val) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="m-theme-desc">Using default theme. Configure on desktop.</div>
        <?php endif; ?>

        <a href="?page=admin_theme_settings&desktop=1" class="m-theme-btn">
            <i class="fas fa-desktop"></i> Edit on Desktop
        </a>
    </div>
</div>
