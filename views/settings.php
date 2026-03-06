<!-- Global Settings View -->
<?php
// Load current settings from database
$_gs_settings = [];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $gs_q = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        while ($gs_r = $gs_q->fetch(PDO::FETCH_ASSOC)) {
            $_gs_settings[$gs_r['setting_key']] = $gs_r['setting_value'];
        }
    }
} catch (Exception $e) { /* table may not exist yet */ }
$_gs = function($key, $default = '') use ($_gs_settings) {
    return $_gs_settings[$key] ?? $default;
};
?>
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-cogs"></i> Global Settings
    </h1>
    <p class="page-description">Configure system-wide settings and preferences</p>
</div>

<div class="global-settings-content">
    <!-- General Settings -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-sliders-h"></i> General Settings</h3>
        </div>
        <div class="card-body">
            <form class="settings-form" method="POST" action="process_settings.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="action" value="update_general">
                <div class="form-group">
                    <label>Organization Name *</label>
                    <input type="text" name="org_name" class="form-input" value="<?= htmlspecialchars($_gs('org_name', 'Arctic Wolves')) ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Contact Email *</label>
                        <input type="email" name="contact_email" class="form-input" value="<?= htmlspecialchars($_gs('contact_email', 'info@arcticwolves.ca')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Phone</label>
                        <input type="tel" name="contact_phone" class="form-input" value="<?= htmlspecialchars($_gs('contact_phone', '')) ?>" placeholder="(555) 123-4567">
                    </div>
                </div>

                <div class="form-group">
                    <label>Organization Address</label>
                    <textarea name="org_address" class="form-textarea" rows="3" placeholder="Full address"><?= htmlspecialchars($_gs('org_address', '')) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Timezone *</label>
                    <select name="timezone" class="form-input" required>
                        <?php
                        $tzOptions = [
                            'America/St_Johns' => 'Newfoundland (NST)',
                            'America/Halifax' => 'Atlantic (AST)',
                            'America/New_York' => 'Eastern (EST)',
                            'America/Chicago' => 'Central (CST)',
                            'America/Denver' => 'Mountain (MST)',
                            'America/Los_Angeles' => 'Pacific (PST)',
                        ];
                        $curTz = $_gs('timezone', 'America/New_York');
                        foreach ($tzOptions as $tzVal => $tzLabel):
                        ?>
                        <option value="<?= htmlspecialchars($tzVal) ?>" <?= $curTz === $tzVal ? 'selected' : '' ?>><?= htmlspecialchars($tzLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Currency</label>
                        <select name="currency" class="form-input">
                            <?php
                            $currOptions = ['USD ($)', 'CAD ($)', 'EUR (€)'];
                            $curCurrency = $_gs('currency', 'USD ($)');
                            foreach ($currOptions as $c):
                            ?>
                            <option <?= $curCurrency === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date Format</label>
                        <select name="date_format" class="form-input">
                            <?php
                            $fmtOptions = ['MM/DD/YYYY', 'DD/MM/YYYY', 'YYYY-MM-DD'];
                            $curFmt = $_gs('date_format', 'MM/DD/YYYY');
                            foreach ($fmtOptions as $f):
                            ?>
                            <option <?= $curFmt === $f ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Settings</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Booking Settings -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-check"></i> Booking Settings</h3>
        </div>
        <div class="card-body">
            <form class="settings-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Booking Window (days)</label>
                        <input type="number" class="form-input" value="30" min="1">
                        <small class="form-hint">How far in advance can clients book sessions</small>
                    </div>
                    <div class="form-group">
                        <label>Cancellation Window (hours)</label>
                        <input type="number" class="form-input" value="24" min="1">
                        <small class="form-hint">Minimum notice required for cancellation</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Session Duration Options (minutes)</label>
                    <input type="text" class="form-input" value="30, 60, 90, 120">
                    <small class="form-hint">Comma-separated values</small>
                </div>

                <div class="setting-toggle-item">
                    <div class="setting-info">
                        <h4>Auto-Confirm Bookings</h4>
                        <p>Automatically confirm session bookings without manual approval</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="setting-toggle-item">
                    <div class="setting-info">
                        <h4>Send Booking Confirmations</h4>
                        <p>Send email confirmations when sessions are booked</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Settings</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Settings -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-credit-card"></i> Payment Settings</h3>
        </div>
        <div class="card-body">
            <form class="settings-form">
                <div class="form-group">
                    <label>Payment Gateway</label>
                    <select class="form-input">
                        <option selected>Stripe</option>
                        <option>PayPal</option>
                        <option>Square</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tax Rate (%)</label>
                    <input type="number" class="form-input" value="0" step="0.01" min="0" max="100">
                </div>

                <div class="setting-toggle-item">
                    <div class="setting-info">
                        <h4>Accept Credit Cards</h4>
                        <p>Allow online credit card payments</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="setting-toggle-item">
                    <div class="setting-info">
                        <h4>Accept ACH/Bank Transfer</h4>
                        <p>Allow direct bank transfers</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox">
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.setting-toggle-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 12px;
}
</style>
