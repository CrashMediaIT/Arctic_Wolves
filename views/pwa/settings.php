<?php
/**
 * PWA Settings - Mobile-native app settings
 * Purpose-built for mobile phones.
 */

// Load current settings from DB
$currentSettings = [];
try {
    $sStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('org_name','contact_email','contact_phone','org_address','timezone','currency','date_format')");
    $currentSettings = $sStmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) { $currentSettings = []; }
?>
<style>
.m-settings { padding: 16px; font-family: Inter, sans-serif; }
.m-settings-header { margin-bottom: 16px; }
.m-settings-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-settings-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-settings-section { margin-bottom: 20px; }
.m-settings-section-title {
    font-size: 13px; font-weight: 600; color: #6B6B7B;
    text-transform: uppercase; letter-spacing: 0.5px;
    margin: 0 0 10px; padding: 0 4px;
}
.m-settings-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    overflow: hidden;
}
.m-settings-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 16px; min-height: 44px;
    border-bottom: 1px solid #2D2D3F;
}
.m-settings-item:last-child { border-bottom: none; }
.m-settings-item-left { display: flex; align-items: center; gap: 12px; }
.m-settings-item-icon { font-size: 16px; color: #8B5CF6; width: 20px; text-align: center; }
.m-settings-item-label { font-size: 14px; color: #fff; }
.m-settings-item-desc { font-size: 11px; color: #6B6B7B; margin-top: 2px; }
.m-settings-toggle {
    position: relative; width: 48px; height: 28px;
    background: #2D2D3F; border-radius: 14px; cursor: pointer;
    border: none; padding: 0; flex-shrink: 0;
}
.m-settings-toggle::after {
    content: ''; position: absolute; top: 3px; left: 3px;
    width: 22px; height: 22px; border-radius: 50%;
    background: #6B6B7B; transition: all 0.2s ease;
}
.m-settings-toggle.m-toggle-on { background: rgba(107,70,193,0.3); }
.m-settings-toggle.m-toggle-on::after { left: 23px; background: #8B5CF6; }
.m-settings-chevron { color: #6B6B7B; font-size: 14px; }
.m-settings-version {
    text-align: center; padding: 20px; color: #6B6B7B; font-size: 12px;
}
.m-settings-edit-btn {
    background: rgba(107,70,193,0.15); color: #8B5CF6; border: none; border-radius: 8px;
    padding: 8px 14px; font-size: 12px; font-weight: 600; cursor: pointer;
    font-family: Inter, sans-serif; min-height: 44px; display: flex; align-items: center;
    gap: 6px; width: 100%; justify-content: center; margin-top: 10px;
}
/* Bottom sheet */
.m-settings-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6);
    z-index: 1000; align-items: flex-end; justify-content: center;
}
.m-settings-overlay.m-visible { display: flex; }
.m-settings-sheet {
    background: #16161F; border-radius: 16px 16px 0 0; width: 100%; max-width: 500px;
    max-height: 85vh; overflow-y: auto; padding: 20px 16px 32px;
    animation: mSlideUp 0.3s ease;
}
@keyframes mSlideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
.m-settings-sheet-handle {
    width: 36px; height: 4px; background: #2D2D3F; border-radius: 2px;
    margin: 0 auto 16px;
}
.m-settings-sheet-title {
    font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; text-align: center;
}
.m-settings-form-group { margin-bottom: 14px; }
.m-settings-form-label { font-size: 12px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; display: block; }
.m-settings-form-input {
    width: 100%; background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; padding: 12px; min-height: 44px; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box;
}
.m-settings-form-input:focus { outline: none; border-color: #8B5CF6; }
.m-settings-form-textarea { resize: vertical; min-height: 66px; }
.m-settings-save-btn {
    background: #6B46C1; color: #fff; border: none; border-radius: 10px; min-height: 44px;
    font-weight: 600; font-size: 14px; cursor: pointer; width: 100%; margin-top: 6px;
    font-family: Inter, sans-serif; display: flex; align-items: center; justify-content: center; gap: 6px;
}
.m-settings-save-btn:active { opacity: 0.85; }
.m-settings-toast {
    display: none; position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%);
    background: #10B981; color: #fff; padding: 10px 20px; border-radius: 10px;
    font-size: 13px; font-weight: 600; z-index: 1100; font-family: Inter, sans-serif;
}
.m-settings-toast.m-visible { display: block; }
</style>

<div class="m-settings">
    <div class="m-settings-header">
        <h2 class="m-settings-title">Settings</h2>
        <p class="m-settings-sub">App preferences</p>
    </div>

    <div class="m-settings-section">
        <h3 class="m-settings-section-title">Display</h3>
        <div class="m-settings-card">
            <div class="m-settings-item">
                <div class="m-settings-item-left">
                    <span class="m-settings-item-icon"><i class="fas fa-text-height"></i></span>
                    <div>
                        <div class="m-settings-item-label">Large Text</div>
                        <div class="m-settings-item-desc">Increase font sizes</div>
                    </div>
                </div>
                <button class="m-settings-toggle" onclick="this.classList.toggle('m-toggle-on')" type="button"></button>
            </div>
        </div>
    </div>

    <div class="m-settings-section">
        <h3 class="m-settings-section-title">Notifications</h3>
        <div class="m-settings-card">
            <div class="m-settings-item">
                <div class="m-settings-item-left">
                    <span class="m-settings-item-icon"><i class="fas fa-bell"></i></span>
                    <div>
                        <div class="m-settings-item-label">Push Notifications</div>
                        <div class="m-settings-item-desc">Session reminders & updates</div>
                    </div>
                </div>
                <button class="m-settings-toggle m-toggle-on" onclick="this.classList.toggle('m-toggle-on')" type="button"></button>
            </div>
            <div class="m-settings-item">
                <div class="m-settings-item-left">
                    <span class="m-settings-item-icon"><i class="fas fa-envelope"></i></span>
                    <div>
                        <div class="m-settings-item-label">Email Notifications</div>
                        <div class="m-settings-item-desc">Weekly digest & alerts</div>
                    </div>
                </div>
                <button class="m-settings-toggle m-toggle-on" onclick="this.classList.toggle('m-toggle-on')" type="button"></button>
            </div>
        </div>
    </div>

    <!-- General Settings (editable, matches desktop process_settings.php action=update_general) -->
    <div class="m-settings-section">
        <h3 class="m-settings-section-title">General</h3>
        <div class="m-settings-card">
            <div class="m-settings-item">
                <div class="m-settings-item-left">
                    <span class="m-settings-item-icon"><i class="fas fa-building"></i></span>
                    <div>
                        <div class="m-settings-item-label">Organization</div>
                        <div class="m-settings-item-desc"><?= htmlspecialchars($currentSettings['org_name'] ?? 'Arctic Wolves') ?></div>
                    </div>
                </div>
                <i class="fas fa-chevron-right m-settings-chevron"></i>
            </div>
            <div class="m-settings-item">
                <div class="m-settings-item-left">
                    <span class="m-settings-item-icon"><i class="fas fa-globe"></i></span>
                    <div>
                        <div class="m-settings-item-label">Timezone</div>
                        <div class="m-settings-item-desc"><?= htmlspecialchars(date_default_timezone_get()) ?> (Docker ENV)</div>
                    </div>
                </div>
            </div>
            <div class="m-settings-item">
                <div class="m-settings-item-left">
                    <span class="m-settings-item-icon"><i class="fas fa-dollar-sign"></i></span>
                    <div>
                        <div class="m-settings-item-label">Currency</div>
                        <div class="m-settings-item-desc"><?= htmlspecialchars($currentSettings['currency'] ?? 'USD ($)') ?></div>
                    </div>
                </div>
                <i class="fas fa-chevron-right m-settings-chevron"></i>
            </div>
        </div>
        <button class="m-settings-edit-btn" onclick="mOpenSettingsSheet()" type="button">
            <i class="fas fa-pen"></i> Edit General Settings
        </button>
    </div>

    <div class="m-settings-section">
        <h3 class="m-settings-section-title">Account</h3>
        <div class="m-settings-card">
            <a href="?page=profile" class="m-settings-item" style="text-decoration:none;">
                <div class="m-settings-item-left">
                    <span class="m-settings-item-icon"><i class="fas fa-user"></i></span>
                    <div class="m-settings-item-label">Edit Profile</div>
                </div>
                <i class="fas fa-chevron-right m-settings-chevron"></i>
            </a>
            <a href="?page=notifications" class="m-settings-item" style="text-decoration:none;">
                <div class="m-settings-item-left">
                    <span class="m-settings-item-icon"><i class="fas fa-bell"></i></span>
                    <div class="m-settings-item-label">Notifications</div>
                </div>
                <i class="fas fa-chevron-right m-settings-chevron"></i>
            </a>
        </div>
    </div>

    <div class="m-settings-version">
        Arctic Wolves PWA v1.0
    </div>
</div>

<!-- General Settings Bottom Sheet -->
<div class="m-settings-overlay" id="mSettingsOverlay" onclick="if(event.target===this)mCloseSettingsSheet()">
    <div class="m-settings-sheet">
        <div class="m-settings-sheet-handle"></div>
        <div class="m-settings-sheet-title">General Settings</div>
        <form method="POST" action="process_settings.php" id="mGeneralSettingsForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="update_general">
            <div class="m-settings-form-group">
                <label class="m-settings-form-label">Organization Name *</label>
                <input type="text" name="org_name" class="m-settings-form-input" value="<?= htmlspecialchars($currentSettings['org_name'] ?? 'Arctic Wolves') ?>" required>
            </div>
            <div class="m-settings-form-group">
                <label class="m-settings-form-label">Contact Email *</label>
                <input type="email" name="contact_email" class="m-settings-form-input" value="<?= htmlspecialchars($currentSettings['contact_email'] ?? '') ?>" required>
            </div>
            <div class="m-settings-form-group">
                <label class="m-settings-form-label">Contact Phone</label>
                <input type="tel" name="contact_phone" class="m-settings-form-input" value="<?= htmlspecialchars($currentSettings['contact_phone'] ?? '') ?>" placeholder="(555) 123-4567">
            </div>
            <div class="m-settings-form-group">
                <label class="m-settings-form-label">Organization Address</label>
                <textarea name="org_address" class="m-settings-form-input m-settings-form-textarea" placeholder="Full address"><?= htmlspecialchars($currentSettings['org_address'] ?? '') ?></textarea>
            </div>
            <div class="m-settings-form-group">
                <label class="m-settings-form-label">Timezone</label>
                <span class="m-settings-form-input" style="background: var(--bg-secondary); cursor: default;">
                    <?= htmlspecialchars(date_default_timezone_get()) ?> (Docker ENV)
                </span>
            </div>
            <div class="m-settings-form-group">
                <label class="m-settings-form-label">Currency</label>
                <select name="currency" class="m-settings-form-input">
                    <?php
                    $currOptions = ['USD ($)','CAD ($)','EUR (€)'];
                    $curCurrency = $currentSettings['currency'] ?? 'USD ($)';
                    foreach ($currOptions as $c):
                    ?>
                    <option <?= $curCurrency === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="m-settings-form-group">
                <label class="m-settings-form-label">Date Format</label>
                <select name="date_format" class="m-settings-form-input">
                    <?php
                    $dfOptions = ['MM/DD/YYYY','DD/MM/YYYY','YYYY-MM-DD'];
                    $curDf = $currentSettings['date_format'] ?? 'MM/DD/YYYY';
                    foreach ($dfOptions as $df):
                    ?>
                    <option <?= $curDf === $df ? 'selected' : '' ?>><?= htmlspecialchars($df) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="m-settings-save-btn"><i class="fas fa-save"></i> Save Settings</button>
        </form>
    </div>
</div>

<div class="m-settings-toast" id="mSettingsToast">Settings saved!</div>

<script>
function mOpenSettingsSheet() {
    document.getElementById('mSettingsOverlay').classList.add('m-visible');
}
function mCloseSettingsSheet() {
    document.getElementById('mSettingsOverlay').classList.remove('m-visible');
}
</script>
