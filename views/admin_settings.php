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
    :root {
        --primary: #7000a4;
    }
    
    .settings-header {
        margin-bottom: 30px;
    }
    
    .settings-header h1 {
        font-size: 32px;
        font-weight: 900;
        margin-bottom: 8px;
    }
    
    .settings-header p {
        color: #94a3b8;
        font-size: 14px;
    }
    
    .tabs {
        display: flex;
        gap: 8px;
        border-bottom: 2px solid #1e293b;
        margin-bottom: 30px;
        overflow-x: auto;
    }
    
    .tab {
        padding: 12px 20px;
        background: transparent;
        border: none;
        color: #94a3b8;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        border-bottom: 3px solid transparent;
        transition: all 0.2s;
        white-space: nowrap;
    }
    
    .tab:hover {
        color: #fff;
        background: rgba(112, 0, 164, 0.1);
    }
    
    .tab.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .settings-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 30px;
        margin-bottom: 25px;
    }
    
    .card-header {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #1e293b;
    }
    
    .card-title {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 5px;
    }
    
    .card-description {
        font-size: 13px;
        color: #94a3b8;
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
        color: #94a3b8;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .form-input,
    .form-select,
    .form-textarea {
        width: 100%;
        padding: 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
    }
    
    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
        outline: none;
        border-color: var(--primary);
    }
    
    .form-textarea {
        resize: vertical;
        min-height: 100px;
    }
    
    .help-text {
        font-size: 12px;
        color: #64748b;
        margin-top: 5px;
        line-height: 1.4;
    }
    
    .help-text i {
        color: var(--primary);
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
        background: var(--primary);
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
        background: #5a0080;
        transform: translateY(-2px);
    }
    
    .btn-secondary {
        background: transparent;
        border: 1px solid #1e293b;
        color: #94a3b8;
        padding: 12px 24px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
        margin-left: 10px;
    }
    
    .btn-secondary:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .alert {
        padding: 15px 20px;
        border-radius: 6px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .alert-success {
        background: rgba(0, 255, 136, 0.1);
        border: 1px solid #00ff88;
        color: #00ff88;
    }
    
    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid #ef4444;
        color: #ef4444;
    }
    
    .info-box {
        background: rgba(112, 0, 164, 0.1);
        border: 1px solid var(--primary);
        border-radius: 6px;
        padding: 15px;
        margin-top: 15px;
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
                           value="<?= htmlspecialchars(getSetting($settings, 'site_name', 'Crash Hockey')) ?>" required>
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
                           value="<?= htmlspecialchars(getSetting($settings, 'smtp_from_name', 'Crash Hockey')) ?>" required>
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
                <h3 class="card-title">Payment & Tax Settings</h3>
                <p class="card-description">Configure Stripe and tax settings</p>
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
            
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> Save Payment Settings
            </button>
        </div>
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
</script>
