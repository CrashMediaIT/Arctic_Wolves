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

// Get current theme colors (same as desktop)
$theme_colors = [];
try {
    $stmt2 = $pdo->query("SELECT setting_name, setting_value FROM theme_settings");
    $theme_colors = $stmt2->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) { $theme_colors = []; }

$defaults = [
    'primary_color' => '#7000a4',
    'secondary_color' => '#c0c0c0',
    'background_color' => '#06080b',
    'card_background_color' => '#0d1117',
    'text_color' => '#ffffff',
    'text_muted_color' => '#94a3b8',
    'border_color' => '#1e293b',
    'sidebar_color' => '#020305',
    'button_hover_color' => '#a78bfa',
    'success_color' => '#22c55e',
    'error_color' => '#ef4444',
    'warning_color' => '#f59e0b'
];
$colors = array_merge($defaults, $theme_colors);

$colorFields = [
    'primary_color' => 'Primary',
    'secondary_color' => 'Secondary',
    'background_color' => 'Background',
    'card_background_color' => 'Card BG',
    'text_color' => 'Text',
    'text_muted_color' => 'Muted Text',
    'border_color' => 'Border',
    'sidebar_color' => 'Sidebar',
    'button_hover_color' => 'Btn Hover',
    'success_color' => 'Success',
    'error_color' => 'Error',
    'warning_color' => 'Warning',
];
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
.m-theme-edit-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 12px 24px; border-radius: 10px; background: #6B46C1; color: #fff;
    font-size: 14px; font-weight: 600; border: none; cursor: pointer;
    min-height: 44px; font-family: Inter, sans-serif; margin-bottom: 8px;
}
.m-theme-overlay {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6); z-index: 9998;
}
.m-theme-overlay.m-active { display: block; }
.m-theme-sheet {
    display: none; position: fixed; left: 0; right: 0; bottom: 0;
    background: #16161F; border-radius: 16px 16px 0 0; z-index: 9999;
    max-height: 85vh; overflow-y: auto; padding: 20px 16px 32px;
}
.m-theme-sheet.m-active { display: block; }
.m-theme-sheet-title {
    font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 16px;
    display: flex; align-items: center; justify-content: space-between;
}
.m-theme-sheet-close {
    background: none; border: none; color: #A8A8B8; font-size: 22px; cursor: pointer;
    width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
}
.m-theme-color-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.m-theme-color-field { margin-bottom: 4px; }
.m-theme-color-label { font-size: 11px; font-weight: 600; color: #A8A8B8; margin-bottom: 4px; text-transform: uppercase; }
.m-theme-color-wrap {
    display: flex; align-items: center; gap: 8px; background: #0A0A0F;
    border: 1px solid #2D2D3F; border-radius: 10px; padding: 8px 10px;
}
.m-theme-swatch {
    width: 32px; height: 32px; border-radius: 6px; border: 1px solid #2D2D3F;
    cursor: pointer; flex-shrink: 0;
}
.m-theme-color-input {
    flex: 1; background: transparent; border: none; color: #fff;
    font-size: 13px; font-weight: 600; font-family: monospace; outline: none; min-width: 0;
}
.m-theme-color-picker { position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none; }
.m-theme-submit {
    width: 100%; padding: 12px; background: #6B46C1; color: #fff; border: none;
    border-radius: 10px; font-size: 14px; font-weight: 600; min-height: 44px;
    cursor: pointer; margin-top: 12px; font-family: Inter, sans-serif;
}
.m-theme-alert {
    padding: 10px 12px; border-radius: 10px; margin-bottom: 12px; font-size: 12px;
    background: rgba(16,185,129,0.15); color: #10B981; border: 1px solid rgba(16,185,129,0.3);
}
</style>

<div class="m-theme">
    <div class="m-theme-header">
        <h2 class="m-theme-title">Theme Settings</h2>
        <p class="m-theme-sub">Appearance & branding</p>
    </div>

    <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <div class="m-theme-alert"><i class="fas fa-check-circle"></i> Theme updated successfully!</div>
    <?php endif; ?>

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
        <div class="m-theme-desc">Using default theme.</div>
        <?php endif; ?>

        <button class="m-theme-edit-btn" onclick="mThemeOpen()">
            <i class="fas fa-paint-brush"></i> Edit Colors
        </button>
        <a href="?page=admin_theme_settings&desktop=1" class="m-theme-btn">
            <i class="fas fa-desktop"></i> Full Editor on Desktop
        </a>
    </div>
</div>

<div class="m-theme-overlay" id="mThemeOverlay" onclick="mThemeClose()"></div>
<div class="m-theme-sheet" id="mThemeSheet">
    <div class="m-theme-sheet-title">
        <span><i class="fas fa-palette"></i> Edit Theme Colors</span>
        <button class="m-theme-sheet-close" onclick="mThemeClose()">&times;</button>
    </div>
    <form method="POST" action="process_theme.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="update_colors">
        <div class="m-theme-color-grid">
            <?php foreach ($colorFields as $field => $label): ?>
            <div class="m-theme-color-field">
                <div class="m-theme-color-label"><?= $label ?></div>
                <div class="m-theme-color-wrap" style="position:relative;">
                    <div class="m-theme-swatch" style="background:<?= htmlspecialchars($colors[$field]) ?>;" onclick="this.nextElementSibling.nextElementSibling.click()"></div>
                    <input type="text" class="m-theme-color-input" name="<?= $field ?>" value="<?= htmlspecialchars($colors[$field]) ?>" pattern="^#[0-9A-Fa-f]{6}$" required>
                    <input type="color" class="m-theme-color-picker" value="<?= htmlspecialchars($colors[$field]) ?>" onchange="var w=this.parentNode;w.querySelector('.m-theme-swatch').style.background=this.value;w.querySelector('.m-theme-color-input').value=this.value;">
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="m-theme-submit"><i class="fas fa-save"></i> Save Colors</button>
    </form>
</div>

<script>
function mThemeOpen() {
    document.getElementById('mThemeOverlay').classList.add('m-active');
    document.getElementById('mThemeSheet').classList.add('m-active');
}
function mThemeClose() {
    document.getElementById('mThemeOverlay').classList.remove('m-active');
    document.getElementById('mThemeSheet').classList.remove('m-active');
}
</script>
