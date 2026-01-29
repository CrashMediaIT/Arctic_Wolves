<?php
/**
 * Admin Settings - Comprehensive System Settings with Tabbed Interface
 * All system-wide configuration in one place
 */

require_once __DIR__ . '/../security.php';

// Check if user is admin
if ($user_role !== 'admin') {
    header('Location: dashboard.php?page=home');
    exit;
}

// Get all current settings
$settings_query = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $settings_query->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Helper function to get setting with default
function getSetting($settings, $key, $default = '') {
    return $settings[$key] ?? $default;
}
?>

<style>
    /* Admin Settings - Uses application theme colors from shared_styles.css */
    
    .settings-header {
        margin-bottom: 24px;
    }
    
    .settings-header h1 {
        font-size: 32px;
        font-weight: 900;
        margin-bottom: 8px;
    }
    
    .settings-header p {
        color: var(--text-secondary, #A8A8B8);
        font-size: 14px;
    }
    
    .tabs {
        display: flex;
        gap: 8px;
        border-bottom: 2px solid var(--border, #2D2D3F);
        margin-bottom: 24px;
        overflow-x: auto;
    }
    
    .tab {
        padding: 12px 20px;
        background: transparent;
        border: none;
        color: var(--text-secondary, #A8A8B8);
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        border-bottom: 3px solid transparent;
        transition: all 0.2s;
        white-space: nowrap;
    }
    
    .tab:hover {
        color: #fff;
        background: rgba(107, 70, 193, 0.1);
    }
    
    .tab.active {
        color: var(--primary, #6B46C1);
        border-bottom-color: var(--primary, #6B46C1);
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .settings-card {
        background: var(--bg-card, #16161F);
        border: 1px solid var(--border, #2D2D3F);
        border-radius: 8px;
        padding: 24px;
        margin-bottom: 24px;
    }
    
    .card-header {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border, #2D2D3F);
    }
    
    .card-title {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 5px;
    }
    
    .card-description {
        font-size: 13px;
        color: var(--text-secondary, #A8A8B8);
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: var(--text-secondary, #A8A8B8);
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .form-input,
    .form-select,
    .form-textarea {
        width: 100%;
        padding: 12px;
        background: var(--bg-main, #0A0A0F);
        border: 1px solid var(--border, #2D2D3F);
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
    }
    
    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
        outline: none;
        border-color: var(--primary, #6B46C1);
    }
    
    .form-textarea {
        resize: vertical;
        min-height: 100px;
    }
    
    .help-text {
        font-size: 12px;
        color: var(--text-muted, #6B6B7B);
        margin-top: 5px;
        line-height: 1.4;
    }
    
    .help-text i {
        color: var(--primary, #6B46C1);
    }
    
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .checkbox-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    
    .checkbox-group label {
        margin: 0;
        color: #fff;
        font-size: 14px;
        cursor: pointer;
    }
    
    .btn-primary {
        background: var(--primary, #6B46C1);
        color: #fff;
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
    }
    
    .btn-primary:hover {
        background: var(--primary-hover, #7C3AED);
        transform: translateY(-2px);
    }
    
    .btn-secondary {
        background: transparent;
        border: 1px solid var(--border, #2D2D3F);
        color: var(--text-secondary, #A8A8B8);
        padding: 12px 24px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
        margin-left: 10px;
    }
    
    .btn-secondary:hover {
        border-color: var(--primary, #6B46C1);
        color: var(--primary, #6B46C1);
    }
    
    .alert {
        padding: 16px 20px;
        border-radius: 6px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid var(--success, #10B981);
        color: var(--success, #10B981);
    }
    
    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid var(--error, #EF4444);
        color: var(--error, #EF4444);
    }
    
    /* Alias for alert-error */
    .alert-danger {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid var(--error, #EF4444);
        color: var(--error, #EF4444);
    }
    
    .alert-warning {
        background: rgba(245, 158, 11, 0.1);
        border: 1px solid var(--warning, #F59E0B);
        color: #fbbf24;
    }
    
    .alert-info {
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid #3b82f6;
        color: #3b82f6;
    }
    
    .info-box {
        background: rgba(112, 0, 164, 0.1);
        border: 1px solid var(--primary);
        border-radius: 6px;
        padding: 16px;
        margin-top: 12px;
    }
    
    .info-box h4 {
        color: var(--primary);
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 8px;
        text-transform: uppercase;
    }
    
    .info-box ul {
        margin: 0;
        padding-left: 20px;
        color: #94a3b8;
        font-size: 12px;
        line-height: 1.8;
    }
    
    .info-box a {
        color: var(--primary);
        text-decoration: none;
    }
    
    .info-box a:hover {
        text-decoration: underline;
    }
</style>

<div class="settings-header">
    <h1><i class="fas fa-cog"></i> System Settings</h1>
    <p>Configure all system-wide settings and integrations</p>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        Settings updated successfully!
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($_GET['error']) ?>
    </div>
<?php endif; ?>

<div class="tabs">
    <button class="tab active" onclick="switchTab('general')">
        <i class="fas fa-sliders"></i> General
    </button>
    <button class="tab" onclick="switchTab('smtp')">
        <i class="fas fa-envelope"></i> SMTP
    </button>
    <button class="tab" onclick="switchTab('nextcloud')">
        <i class="fas fa-cloud"></i> Nextcloud
    </button>
    <button class="tab" onclick="switchTab('payments')">
        <i class="fas fa-credit-card"></i> Payments
    </button>
    <button class="tab" onclick="switchTab('security')">
        <i class="fas fa-shield"></i> Security
    </button>
    <button class="tab" onclick="switchTab('advanced')">
        <i class="fas fa-code"></i> Advanced
    </button>
    <button class="tab" onclick="switchTab('updates')">
        <i class="fas fa-download"></i> Updates
    </button>
    <button class="tab" onclick="switchTab('landing')">
        <i class="fas fa-home"></i> Landing Page
    </button>
</div>

<!-- General Settings Tab -->
<div id="tab-general" class="tab-content active">
    <form method="POST" action="process_settings.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="update_general">
        
        <div class="settings-card">
            <div class="card-header">
                <h3 class="card-title">General Settings</h3>
                <p class="card-description">Basic site configuration and preferences</p>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Site Name</label>
                    <input type="text" name="site_name" class="form-input" 
                           value="<?= htmlspecialchars(getSetting($settings, 'site_name', 'Arctic Wolves')) ?>" required>
                    <div class="help-text">Display name for the application</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Timezone</label>
                    <select name="timezone" class="form-select">
                        <?php
                        $timezones = ['America/Toronto', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'America/Vancouver'];
                        $current_tz = getSetting($settings, 'timezone', 'America/Toronto');
                        foreach ($timezones as $tz) {
                            $selected = ($tz === $current_tz) ? 'selected' : '';
                            echo "<option value=\"$tz\" $selected>$tz</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Language</label>
                    <select name="language" class="form-select">
                        <?php
                        $current_lang = getSetting($settings, 'language', 'en');
                        ?>
                        <option value="en" <?= $current_lang === 'en' ? 'selected' : '' ?>>English</option>
                        <option value="fr" <?= $current_lang === 'fr' ? 'selected' : '' ?>>Français</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> Save General Settings
            </button>
        </div>
    </form>
</div>

<!-- SMTP Settings Tab -->
<div id="tab-smtp" class="tab-content">
    <form method="POST" action="process_settings.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="update_smtp">
        
        <div class="settings-card">
            <div class="card-header">
                <h3 class="card-title">SMTP Configuration</h3>
                <p class="card-description">Configure email server settings for notifications</p>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">SMTP Host</label>
                    <input type="text" name="smtp_host" class="form-input" 
                           value="<?= htmlspecialchars(getSetting($settings, 'smtp_host')) ?>" required>
                    <div class="help-text">e.g., smtp.gmail.com or mail.yourdomain.com</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">SMTP Port</label>
                    <input type="number" name="smtp_port" class="form-input" 
                           value="<?= htmlspecialchars(getSetting($settings, 'smtp_port', '587')) ?>" required>
                    <div class="help-text">587 (TLS) or 465 (SSL)</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Encryption</label>
                    <select name="smtp_encryption" class="form-select">
                        <?php $current_enc = getSetting($settings, 'smtp_encryption', 'tls'); ?>
                        <option value="tls" <?= $current_enc === 'tls' ? 'selected' : '' ?>>TLS</option>
                        <option value="ssl" <?= $current_enc === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">SMTP Username</label>
                    <input type="text" name="smtp_user" class="form-input" 
                           value="<?= htmlspecialchars(getSetting($settings, 'smtp_user')) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">SMTP Password</label>
                    <input type="password" name="smtp_pass" class="form-input" 
                           value="<?= htmlspecialchars(getSetting($settings, 'smtp_pass')) ?>">
                    <div class="help-text">Leave blank to keep existing password</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">From Email</label>
                    <input type="email" name="smtp_from_email" class="form-input" 
                           value="<?= htmlspecialchars(getSetting($settings, 'smtp_from_email')) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">From Name</label>
                    <input type="text" name="smtp_from_name" class="form-input" 
                           value="<?= htmlspecialchars(getSetting($settings, 'smtp_from_name', 'Arctic Wolves')) ?>" required>
                </div>
            </div>
            
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> Save SMTP Settings
            </button>
            <button type="button" class="btn-secondary" onclick="testSmtp()">
                <i class="fas fa-paper-plane"></i> Send Test Email
            </button>
        </div>
    </form>
</div>

<!-- Nextcloud Integration Tab -->
<div id="tab-nextcloud" class="tab-content">
    <form method="POST" action="process_settings.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="update_nextcloud">
        
        <div class="settings-card">
            <div class="card-header">
                <h3 class="card-title">Nextcloud Integration</h3>
                <p class="card-description">Connect to Nextcloud for receipt scanning and document management</p>
            </div>
            
            <div class="form-group">
                <label class="form-label">Nextcloud URL</label>
                <input type="url" name="nextcloud_url" class="form-input" 
                       value="<?= htmlspecialchars(getSetting($settings, 'nextcloud_url')) ?>" 
                       placeholder="https://cloud.example.com">
                <div class="help-text">
                    <i class="fas fa-info-circle"></i> Full URL to your Nextcloud instance (e.g., https://cloud.yourdomain.com)
                </div>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="nextcloud_username" class="form-input" 
                           value="<?= htmlspecialchars(getSetting($settings, 'nextcloud_username')) ?>">
                    <div class="help-text">Nextcloud admin or app-specific user</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password / App Token</label>
                    <input type="password" name="nextcloud_password" class="form-input" 
                           value="<?= htmlspecialchars(getSetting($settings, 'nextcloud_password')) ?>">
                    <div class="help-text">Use app-specific password for security</div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Receipt Folder Path</label>
                <input type="text" name="nextcloud_receipt_folder" class="form-input" 
                       value="<?= htmlspecialchars(getSetting($settings, 'nextcloud_receipt_folder', '/receipts')) ?>" 
                       placeholder="/receipts">
                <div class="help-text">Path where receipts are stored (e.g., /receipts or /Documents/Receipts)</div>
            </div>
            
            <div class="form-group">
                <label class="form-label">WebDAV Path</label>
                <input type="text" name="nextcloud_webdav_path" class="form-input" 
                       value="<?= htmlspecialchars(getSetting($settings, 'nextcloud_webdav_path', '/remote.php/dav/files/')) ?>" 
                       placeholder="/remote.php/dav/files/">
                <div class="help-text">WebDAV endpoint (usually /remote.php/dav/files/)</div>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" name="nextcloud_ocr_enabled" id="nextcloud_ocr_enabled" value="1" 
                       <?= getSetting($settings, 'nextcloud_ocr_enabled') == '1' ? 'checked' : '' ?>>
                <label for="nextcloud_ocr_enabled">Enable OCR processing for receipts</label>
            </div>
            
            <div class="info-box">
                <h4><i class="fas fa-lightbulb"></i> Where to find these settings in Nextcloud:</h4>
                <ul>
                    <li><strong>URL:</strong> Your Nextcloud domain (visible in browser address bar)</li>
                    <li><strong>App Token:</strong> Settings → Security → Devices & Sessions → Create new app password</li>
                    <li><strong>Folder Path:</strong> Create folder in Files app, use path from root (e.g., /receipts)</li>
                    <li><strong>WebDAV:</strong> Default is /remote.php/dav/files/ (check Settings → WebDAV)</li>
                </ul>
            </div>
            
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> Save Nextcloud Settings
            </button>
            <button type="button" class="btn-secondary" onclick="testNextcloud()">
                <i class="fas fa-plug"></i> Test Connection
            </button>
            <button type="button" class="btn-secondary" onclick="testReceiptUpload()">
                <i class="fas fa-receipt"></i> Test Receipt Folder
            </button>
        </div>
    </form>
</div>

<!-- Payment Settings Tab -->
<div id="tab-payments" class="tab-content">
    <form method="POST" action="process_settings.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="update_payments">
        
        <div class="settings-card">
            <div class="card-header">
                <h3 class="card-title">Stripe Configuration</h3>
                <p class="card-description">Configure Stripe payment gateway for processing payments</p>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Stripe Publishable Key</label>
                    <input type="text" name="stripe_publishable_key" class="form-input" 
                           value="<?= htmlspecialchars(getSetting($settings, 'stripe_publishable_key')) ?>" 
                           placeholder="pk_test_..." required>
                    <div class="help-text"><i class="fas fa-info-circle"></i> Public key for frontend (starts with pk_)</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Stripe Secret Key</label>
                    <input type="password" name="stripe_secret_key" class="form-input" 
                           value="<?= htmlspecialchars(getSetting($settings, 'stripe_secret_key')) ?>" 
                           placeholder="sk_test_..." required>
                    <div class="help-text"><i class="fas fa-info-circle"></i> Secret key for backend (starts with sk_)</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Currency</label>
                    <select name="currency" class="form-select" required>
                        <?php
                        $currencies = ['CAD' => 'Canadian Dollar (CAD)', 'USD' => 'US Dollar (USD)', 'EUR' => 'Euro (EUR)', 'GBP' => 'British Pound (GBP)'];
                        $current_currency = getSetting($settings, 'currency', 'CAD');
                        foreach ($currencies as $code => $name) {
                            $selected = ($code === $current_currency) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($code) . "\" $selected>" . htmlspecialchars($name) . "</option>";
                        }
                        ?>
                    </select>
                    <div class="help-text">Default currency for all transactions</div>
                </div>
            </div>
            
            <div class="info-box">
                <h4><i class="fas fa-key"></i> How to Get Your Stripe API Keys</h4>
                <ul>
                    <li>Go to <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener noreferrer">Stripe Dashboard → Developers → API Keys</a></li>
                    <li>Copy both Publishable key (pk_...) and Secret key (sk_...)</li>
                    <li>Use test keys (pk_test_... / sk_test_...) for testing</li>
                    <li>Use live keys (pk_live_... / sk_live_...) for production</li>
                </ul>
            </div>
        </div>
        
        <div class="settings-card">
            <div class="card-header">
                <h3 class="card-title">Tax Settings</h3>
                <p class="card-description">Configure sales tax (HST/GST/VAT) for invoices and payments</p>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Tax Name</label>
                    <input type="text" name="tax_name" class="form-input" 
                           value="<?= htmlspecialchars(getSetting($settings, 'tax_name', 'HST')) ?>" required>
                    <div class="help-text">e.g., HST, GST, VAT, Sales Tax</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tax Rate (%)</label>
                    <input type="number" step="0.01" name="tax_rate" class="form-input" 
                           value="<?= htmlspecialchars(getSetting($settings, 'tax_rate', '13.00')) ?>" required>
                    <div class="help-text">Enter as percentage (e.g., 13.00 for 13%)</div>
                </div>
            </div>
        </div>
        
        <button type="submit" class="btn-primary">
            <i class="fas fa-save"></i> Save Payment & Tax Settings
        </button>
    </form>
</div>

<!-- Security Settings Tab -->
<div id="tab-security" class="tab-content">
    <form method="POST" action="process_settings.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="update_security">
        
        <div class="settings-card">
            <div class="card-header">
                <h3 class="card-title">Security Settings</h3>
                <p class="card-description">Configure security and session management</p>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Session Timeout (minutes)</label>
                    <input type="number" name="session_timeout_minutes" class="form-input" 
                           value="<?= htmlspecialchars(getSetting($settings, 'session_timeout_minutes', '60')) ?>" required>
                    <div class="help-text">Automatic logout after inactivity</div>
                </div>
            </div>
            
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> Save Security Settings
            </button>
        </div>
    </form>
</div>

<!-- Advanced Settings Tab -->
<div id="tab-advanced" class="tab-content">
    <form method="POST" action="process_settings.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="update_advanced">
        
        <div class="settings-card">
            <div class="card-header">
                <h3 class="card-title">Advanced Settings</h3>
                <p class="card-description">Maintenance mode and debugging options</p>
            </div>
            
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" name="maintenance_mode" id="maintenance_mode" value="1" 
                           <?= getSetting($settings, 'maintenance_mode') == '1' ? 'checked' : '' ?>>
                    <label for="maintenance_mode">Enable Maintenance Mode</label>
                </div>
                <div class="help-text">When enabled, only admins can access the site</div>
            </div>
            
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" name="debug_mode" id="debug_mode" value="1" 
                           <?= getSetting($settings, 'debug_mode') == '1' ? 'checked' : '' ?>>
                    <label for="debug_mode">Enable Debug Mode</label>
                </div>
                <div class="help-text">Shows detailed error messages (disable in production)</div>
            </div>
            
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> Save Advanced Settings
            </button>
        </div>
    </form>
</div>

<!-- Updates Tab -->
<div id="tab-updates" class="tab-content">
    <form method="POST" action="process_settings.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="update_github_settings">
        
        <div class="settings-card">
            <div class="card-header">
                <h3 class="card-title">GitHub Authentication</h3>
                <p class="card-description">Configure GitHub access for private repository updates</p>
            </div>
            
            <div class="form-group">
                <label class="form-label">GitHub Personal Access Token</label>
                <input type="password" name="github_token" class="form-input" 
                       value="<?= htmlspecialchars(getSetting($settings, 'github_token', '')) ?>" 
                       placeholder="ghp_xxxxxxxxxxxxxxxxxxxx">
                <div class="help-text">
                    Required for private repositories. 
                    <a href="https://github.com/settings/tokens/new?scopes=repo&description=Arctic%20Wolves%20Updater" 
                       target="_blank" style="color: var(--primary);">Generate token here</a> with 'repo' scope.
                </div>
            </div>
            
            <div class="form-group">
                <button type="button" onclick="testGitHubConnection()" class="btn-secondary">
                    <i class="fas fa-plug"></i> Test Connection
                </button>
            </div>
            
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> Save GitHub Settings
            </button>
        </div>
    </form>
    
    <div class="settings-card" style="margin-top: 20px;">
        <div class="card-header">
            <h3 class="card-title">System Updates</h3>
            <p class="card-description">Check for and apply updates from GitHub repository</p>
        </div>
        
        <div id="update-status" class="form-group" style="display: none;">
            <div class="alert"></div>
        </div>
        
        <div class="form-group">
            <button type="button" onclick="checkForUpdates()" class="btn-secondary">
                <i class="fas fa-search"></i> Check for Updates
            </button>
            
            <button type="button" onclick="applyUpdates()" class="btn-primary" style="margin-left: 10px;">
                <i class="fas fa-download"></i> Apply Updates
            </button>
        </div>
        
        <div class="help-text" style="margin-top: 20px;">
            <strong>⚠️ Important:</strong> Updates will modify system files. Always backup your database and custom configurations before applying updates.
            Files removed from the repository will be deleted from your installation.
        </div>
    </div>
</div>

<!-- Landing Page Settings Tab -->
<div id="tab-landing" class="tab-content">
    <form method="POST" action="process_settings.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="update_landing">
        
        <!-- Programs Section -->
        <div class="settings-card">
            <div class="card-header">
                <h3 class="card-title">Programs Section</h3>
                <p class="card-description">Edit the training programs displayed on the landing page. Leave fields empty to use default values.</p>
            </div>
            
            <?php for ($i = 1; $i <= 4; $i++): 
                $program_prefix = "landing_program_{$i}_";
            ?>
            <div style="border: 1px solid var(--border); border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                <h4 style="color: var(--primary); margin-bottom: 15px;">Program <?= $i ?></h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Title</label>
                        <input type="text" name="<?= $program_prefix ?>title" class="form-input" 
                               value="<?= htmlspecialchars(getSetting($settings, $program_prefix . 'title', '')) ?>"
                               placeholder="<?= ['Player Dev', 'Goalie Elite', 'Conditioning', 'Nutrition'][$i-1] ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Image URL</label>
                        <input type="url" name="<?= $program_prefix ?>image" class="form-input" 
                               value="<?= htmlspecialchars(getSetting($settings, $program_prefix . 'image', '')) ?>"
                               placeholder="https://...">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Tags (comma-separated)</label>
                    <input type="text" name="<?= $program_prefix ?>tags" class="form-input" 
                           value="<?= htmlspecialchars(getSetting($settings, $program_prefix . 'tags', '')) ?>"
                           placeholder="<?= ['Power Skating, Shooting', 'Positioning, Tracking', 'Strength, Power', 'Protein, Recovery'][$i-1] ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="<?= $program_prefix ?>description" class="form-textarea" rows="2"
                              placeholder="<?= ['Forwards & Defense: Explosive edgework and shot mechanics.', 'Crease management, angle control, and rebound psychology.', 'Dryland training for endurance and explosive 60-minute power.', 'Meal planning to fuel muscle growth and accelerate recovery.'][$i-1] ?>"><?= htmlspecialchars(getSetting($settings, $program_prefix . 'description', '')) ?></textarea>
                </div>
            </div>
            <?php endfor; ?>
        </div>
        
        <!-- Standards Section -->
        <div class="settings-card">
            <div class="card-header">
                <h3 class="card-title">Standards Section</h3>
                <p class="card-description">Edit the elite standards displayed on the landing page. Leave fields empty to use default values.</p>
            </div>
            
            <?php 
            $default_standards = [
                ['label' => 'Ice Ratio', 'value' => '4:1 Player/Coach'],
                ['label' => 'Technology', 'value' => 'Video Analysis'],
                ['label' => 'Facility', 'value' => 'Pro-Grade Gym'],
                ['label' => 'Methodology', 'value' => 'Periodization']
            ];
            for ($i = 1; $i <= 4; $i++): 
                $standard_prefix = "landing_standard_{$i}_";
            ?>
            <div class="form-grid" style="margin-bottom: 15px; padding: 15px; border: 1px solid var(--border); border-radius: 8px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Label <?= $i ?></label>
                    <input type="text" name="<?= $standard_prefix ?>label" class="form-input" 
                           value="<?= htmlspecialchars(getSetting($settings, $standard_prefix . 'label', '')) ?>"
                           placeholder="<?= $default_standards[$i-1]['label'] ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Value <?= $i ?></label>
                    <input type="text" name="<?= $standard_prefix ?>value" class="form-input" 
                           value="<?= htmlspecialchars(getSetting($settings, $standard_prefix . 'value', '')) ?>"
                           placeholder="<?= $default_standards[$i-1]['value'] ?>">
                </div>
            </div>
            <?php endfor; ?>
        </div>
        
        <button type="submit" class="btn-primary">
            <i class="fas fa-save"></i> Save Landing Page Settings
        </button>
        <button type="button" class="btn-secondary" onclick="if(confirm('This will clear all custom landing page content. After clearing, click Save to apply the changes and use default values.')) { document.querySelectorAll('#tab-landing input, #tab-landing textarea').forEach(el => el.value = ''); }">
            <i class="fas fa-undo"></i> Reset to Defaults
        </button>
    </form>
</div>

<script>
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Remove active from all tab buttons
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById('tab-' + tabName).classList.add('active');
    
    // Mark button as active
    event.target.classList.add('active');
}

function testSmtp() {
    const email = prompt('Enter test email address:');
    if (!email) return;
    
    if (confirm('Send test email to ' + email + '?')) {
        fetch('process_settings.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=test_smtp&test_email=' + encodeURIComponent(email) + '&<?= csrfTokenInput() ?>'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✓ Test email sent successfully!');
            } else {
                alert('✗ Error: ' + data.message);
            }
        });
    }
}

function testNextcloud() {
    const form = document.querySelector('#tab-nextcloud form');
    const formData = new FormData(form);
    formData.set('action', 'test_nextcloud');
    
    fetch('process_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✓ Nextcloud connection successful!\n\n' + data.message);
        } else {
            alert('✗ Connection failed:\n\n' + data.message);
        }
    });
}

function testReceiptUpload() {
    const form = document.querySelector('#tab-nextcloud form');
    const formData = new FormData(form);
    
    fetch('process_test_nextcloud.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const message = document.createElement('div');
            message.innerHTML = data.message;
            const text = message.innerText || message.textContent;
            alert('✓ ' + text);
        } else {
            alert('✗ Test failed:\n\n' + data.message);
        }
    });
}

function testGitHubConnection() {
    const csrfInput = document.querySelector('[name="csrf_token"]');
    if (!csrfInput) {
        alert('Error: CSRF token not found');
        return;
    }
    
    fetch('process_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'action': 'test_github',
            'csrf_token': csrfInput.value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ GitHub Connection Successful!\n\n' + data.message + 
                  (data.repo_name ? '\n\nRepository: ' + data.repo_name : '') +
                  (data.private !== undefined ? '\n\nPrivate: ' + (data.private ? 'Yes' : 'No') : ''));
        } else {
            alert('✗ Connection failed:\n\n' + data.message);
        }
    });
}

function checkForUpdates() {
    const statusDiv = document.getElementById('update-status');
    const alertDiv = statusDiv.querySelector('.alert');
    const csrfInput = document.querySelector('[name="csrf_token"]');
    
    if (!csrfInput) {
        alertDiv.className = 'alert alert-danger';
        alertDiv.innerHTML = '<i class="fas fa-times-circle"></i> Error: CSRF token not found';
        statusDiv.style.display = 'block';
        return;
    }
    
    statusDiv.style.display = 'block';
    alertDiv.className = 'alert alert-info';
    alertDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking for updates...';
    
    fetch('process_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'action': 'check_updates',
            'csrf_token': csrfInput.value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.has_updates) {
                alertDiv.className = 'alert alert-warning';
                alertDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <strong>Updates Available!</strong><br>' +
                    'Latest commit: ' + data.latest_commit.message + '<br>' +
                    'Date: ' + new Date(data.latest_commit.date).toLocaleString() + '<br>' +
                    'Author: ' + data.latest_commit.author;
            } else {
                alertDiv.className = 'alert alert-success';
                alertDiv.innerHTML = '<i class="fas fa-check-circle"></i> Your system is up to date!';
            }
        } else {
            alertDiv.className = 'alert alert-danger';
            alertDiv.innerHTML = '<i class="fas fa-times-circle"></i> Error: ' + data.message;
        }
    })
    .catch(error => {
        alertDiv.className = 'alert alert-danger';
        alertDiv.innerHTML = '<i class="fas fa-times-circle"></i> Error checking for updates: ' + error;
    });
}

function applyUpdates() {
    if (!confirm('⚠️ WARNING: This will update system files and delete files removed from the repository.\n\n' +
                 'Please ensure you have:\n' +
                 '• Backed up your database\n' +
                 '• Backed up custom configurations\n' +
                 '• Tested in a staging environment\n\n' +
                 'Continue with update?')) {
        return;
    }
    
    const statusDiv = document.getElementById('update-status');
    const alertDiv = statusDiv.querySelector('.alert');
    const csrfInput = document.querySelector('[name="csrf_token"]');
    
    if (!csrfInput) {
        alertDiv.className = 'alert alert-danger';
        alertDiv.innerHTML = '<i class="fas fa-times-circle"></i> Error: CSRF token not found';
        statusDiv.style.display = 'block';
        return;
    }
    
    statusDiv.style.display = 'block';
    alertDiv.className = 'alert alert-info';
    alertDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying updates... This may take a few minutes.';
    
    fetch('process_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'action': 'apply_updates',
            'csrf_token': csrfInput.value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let message = '<i class="fas fa-check-circle"></i> <strong>Update Completed!</strong><br>' + data.message;
            if (data.errors && data.errors.length > 0) {
                message += '<br><br><strong>Errors:</strong><br>' + data.errors.join('<br>');
                alertDiv.className = 'alert alert-warning';
            } else {
                alertDiv.className = 'alert alert-success';
            }
            alertDiv.innerHTML = message;
            
            // Suggest reload if successful
            if (!data.errors || data.errors.length === 0) {
                setTimeout(() => {
                    if (confirm('Update completed successfully! Reload the page to see changes?')) {
                        location.reload();
                    }
                }, 2000);
            }
        } else {
            alertDiv.className = 'alert alert-danger';
            alertDiv.innerHTML = '<i class="fas fa-times-circle"></i> Error: ' + data.message;
        }
    })
    .catch(error => {
        alertDiv.className = 'alert alert-danger';
        alertDiv.innerHTML = '<i class="fas fa-times-circle"></i> Error applying updates: ' + error;
    });
}
</script>
