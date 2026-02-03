<!-- Admin System Tools View -->
<?php
$activeTab = $_GET['tab'] ?? 'settings';

// Fetch system settings from database
try {
    $settings_query = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = [];
    while ($row = $settings_query->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Set defaults for missing settings
    $defaults = [
        'site_title' => 'Arctic Wolves',
        'site_email' => 'info@arcticwolves.ca',
        'session_duration' => 60,
        'notifications_enabled' => '1',
        'maintenance_mode' => '0',
        'mileage_rate_per_km' => '0.68',
        'mileage_rate_per_mile' => '1.10',
        'mileage_unit' => 'km',
        'smtp_port' => '587',
        'smtp_encryption' => 'tls',
        'smtp_from_name' => 'Arctic Wolves'
    ];
    
    foreach ($defaults as $key => $value) {
        if (!isset($settings[$key])) {
            $settings[$key] = $value;
        }
    }
} catch (PDOException $e) {
    error_log("Settings fetch error: " . $e->getMessage());
    $settings = [];
}
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-cog"></i> System Tools</h1>
        <p class="page-description">Configure system settings, integrations, database, and maintenance</p>
    </div>
</div>

<!-- System Tools Tabs -->
<div class="page-tabs" style="flex-wrap: wrap;">
    <a href="?page=system_tools&tab=settings" class="page-tab <?php echo $activeTab === 'settings' ? 'active' : ''; ?>">
        <i class="fas fa-sliders-h"></i> Settings
    </a>
    <a href="?page=system_tools&tab=mileage" class="page-tab <?php echo $activeTab === 'mileage' ? 'active' : ''; ?>">
        <i class="fas fa-car"></i> Mileage
    </a>
    <a href="?page=system_tools&tab=smtp" class="page-tab <?php echo $activeTab === 'smtp' ? 'active' : ''; ?>">
        <i class="fas fa-envelope"></i> SMTP
    </a>
    <a href="?page=system_tools&tab=nextcloud" class="page-tab <?php echo $activeTab === 'nextcloud' ? 'active' : ''; ?>">
        <i class="fas fa-cloud"></i> Nextcloud
    </a>
    <a href="?page=system_tools&tab=docuseal" class="page-tab <?php echo $activeTab === 'docuseal' ? 'active' : ''; ?>">
        <i class="fas fa-file-signature"></i> DocuSeal
    </a>
    <a href="?page=system_tools&tab=payments" class="page-tab <?php echo $activeTab === 'payments' ? 'active' : ''; ?>">
        <i class="fas fa-credit-card"></i> Payments
    </a>
    <a href="?page=system_tools&tab=theme" class="page-tab <?php echo $activeTab === 'theme' ? 'active' : ''; ?>">
        <i class="fas fa-palette"></i> Theme
    </a>
    <a href="?page=system_tools&tab=database" class="page-tab <?php echo $activeTab === 'database' ? 'active' : ''; ?>">
        <i class="fas fa-database"></i> Database
    </a>
    <a href="?page=system_tools&tab=production" class="page-tab <?php echo $activeTab === 'production' ? 'active' : ''; ?>">
        <i class="fas fa-rocket"></i> Production
    </a>
    <a href="?page=system_tools&tab=updates" class="page-tab <?php echo $activeTab === 'updates' ? 'active' : ''; ?>">
        <i class="fas fa-download"></i> Updates
    </a>
    <a href="?page=system_tools&tab=landing" class="page-tab <?php echo $activeTab === 'landing' ? 'active' : ''; ?>">
        <i class="fas fa-home"></i> Landing Page
    </a>
    <a href="system_health_validator.php" class="page-tab">
        <i class="fas fa-heartbeat"></i> Health
    </a>
</div>

<div class="page-tab-content">
    <!-- Success/Error Messages -->
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success" style="margin-bottom: 24px;">
        <i class="fas fa-check-circle"></i>
        <span>Settings saved successfully!</span>
        <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">&times;</button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error" style="margin-bottom: 24px;">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo htmlspecialchars($_GET['error']); ?></span>
        <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Settings Tab -->
    <div class="tab-content <?php echo $activeTab === 'settings' ? 'active' : ''; ?>" id="settings-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-sliders-h"></i> System Settings</h3>
            </div>
            <div class="card-body">
                <form id="settings-form" method="POST" action="process_settings.php" data-form-type="settings">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="update_settings">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Site Title</h4>
                                <p>The name of your site</p>
                            </div>
                            <input type="text" name="site_title" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['site_title'] ?? 'Arctic Wolves'); ?>">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Site Email</h4>
                                <p>Primary contact email</p>
                            </div>
                            <input type="email" name="site_email" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['site_email'] ?? 'info@arcticwolves.ca'); ?>">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Session Duration</h4>
                                <p>Default session length in minutes</p>
                            </div>
                            <input type="number" name="session_duration" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['session_duration'] ?? 60); ?>">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Enable Notifications</h4>
                                <p>Send email notifications to users</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="notifications_enabled" 
                                       <?php echo !empty($settings['notifications_enabled']) ? 'checked' : ''; ?>
                                       data-action="toggle-setting">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Maintenance Mode</h4>
                                <p>Put site in maintenance mode</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="maintenance_mode" 
                                       <?php echo !empty($settings['maintenance_mode']) ? 'checked' : ''; ?>
                                       data-action="toggle-setting">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" data-action="save">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mileage Rates Tab -->
    <div class="tab-content <?php echo $activeTab === 'mileage' ? 'active' : ''; ?>" id="mileage-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-car"></i> Mileage Reimbursement Rates</h3>
            </div>
            <div class="card-body">
                <form id="mileage-rates-form" method="POST" action="process_settings.php" data-form-type="mileage">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="update_mileage_rates">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Default Mileage Unit</h4>
                                <p>Select the default unit for displaying distances in travel logs</p>
                            </div>
                            <select name="mileage_unit" class="form-input" style="width: auto; min-width: 200px;">
                                <option value="km" <?php echo ($settings['mileage_unit'] ?? 'km') === 'km' ? 'selected' : ''; ?>>Kilometers (km)</option>
                                <option value="miles" <?php echo ($settings['mileage_unit'] ?? 'km') === 'miles' ? 'selected' : ''; ?>>Miles (mi)</option>
                            </select>
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Rate per Kilometer</h4>
                                <p>Reimbursement rate for travel in kilometers (CAD)</p>
                            </div>
                            <div class="rate-input-group">
                                <span class="currency-symbol">$</span>
                                <input type="number" name="mileage_rate_per_km" class="form-input" step="0.01" min="0"
                                       value="<?php echo htmlspecialchars($settings['mileage_rate_per_km'] ?? '0.68'); ?>"
                                       placeholder="0.68">
                                <span class="rate-unit">/km</span>
                            </div>
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Rate per Mile</h4>
                                <p>Reimbursement rate for travel in miles (CAD)</p>
                            </div>
                            <div class="rate-input-group">
                                <span class="currency-symbol">$</span>
                                <input type="number" name="mileage_rate_per_mile" class="form-input" step="0.01" min="0"
                                       value="<?php echo htmlspecialchars($settings['mileage_rate_per_mile'] ?? '1.10'); ?>"
                                       placeholder="1.10">
                                <span class="rate-unit">/mi</span>
                            </div>
                        </div>
                    </div>
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <p>These rates are used to calculate travel reimbursements for coaches. The standard CRA rate for 2024 is $0.70/km for the first 5,000 km and $0.64/km thereafter.</p>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" data-action="save">
                            <i class="fas fa-save"></i> Save Mileage Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Google Maps API Configuration -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <h3><i class="fas fa-map-marker-alt"></i> Google Maps API</h3>
            </div>
            <div class="card-body">
                <form id="google-maps-form" method="POST" action="process_settings.php" data-form-type="google-maps">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="update_google_maps">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Google Maps API Key</h4>
                                <p>Used for location autocomplete and distance calculations in travel logs</p>
                            </div>
                            <input type="text" name="google_maps_api_key" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['google_maps_api_key'] ?? ''); ?>"
                                   placeholder="Enter your Google Maps API key" style="min-width: 300px;">
                        </div>
                    </div>
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <p>To obtain a Google Maps API key, visit the <a href="https://console.cloud.google.com/google/maps-apis" target="_blank" style="color: #8B5CF6;">Google Cloud Console</a>. Enable the Maps JavaScript API and Places API for location autocomplete and distance calculations.</p>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="testGoogleMapsAPI()">
                            <i class="fas fa-vial"></i> Test API Key
                        </button>
                        <button type="submit" class="btn btn-primary" data-action="save">
                            <i class="fas fa-save"></i> Save API Key
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SMTP Settings Tab -->
    <div class="tab-content <?php echo $activeTab === 'smtp' ? 'active' : ''; ?>" id="smtp-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-envelope"></i> SMTP Email Settings</h3>
            </div>
            <div class="card-body">
                <form id="smtp-form" method="POST" action="process_settings.php" data-form-type="smtp">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="update_smtp">
                    <input type="hidden" name="redirect_page" value="system_tools">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>SMTP Host</h4>
                                <p>Mail server hostname (e.g., smtp.gmail.com)</p>
                            </div>
                            <input type="text" name="smtp_host" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>"
                                   placeholder="smtp.example.com">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>SMTP Port</h4>
                                <p>Port number (typically 587 for TLS, 465 for SSL)</p>
                            </div>
                            <input type="number" name="smtp_port" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>"
                                   placeholder="587">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>SMTP Username</h4>
                                <p>Email account username or address</p>
                            </div>
                            <input type="text" name="smtp_user" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>"
                                   placeholder="user@example.com">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>SMTP Password</h4>
                                <p>Email account password or app password<?php echo !empty($settings['smtp_pass']) ? ' (currently set)' : ''; ?></p>
                            </div>
                            <input type="password" name="smtp_pass" class="form-input" 
                                   placeholder="<?php echo !empty($settings['smtp_pass']) ? 'Leave blank to keep current password' : 'Enter password'; ?>">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Encryption</h4>
                                <p>Connection security protocol</p>
                            </div>
                            <select name="smtp_encryption" class="form-input">
                                <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?php echo ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>From Name</h4>
                                <p>Sender name displayed in emails</p>
                            </div>
                            <input type="text" name="smtp_from_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? 'Arctic Wolves'); ?>"
                                   placeholder="Arctic Wolves">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>From Email</h4>
                                <p>Sender email address</p>
                            </div>
                            <input type="email" name="smtp_from_email" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? ''); ?>"
                                   placeholder="noreply@example.com">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="testSmtpConnection()">
                            <i class="fas fa-vial"></i> Test Connection
                        </button>
                        <button type="submit" class="btn btn-primary" data-action="save">
                            <i class="fas fa-save"></i> Save SMTP Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Nextcloud Integration Tab -->
    <div class="tab-content <?php echo $activeTab === 'nextcloud' ? 'active' : ''; ?>" id="nextcloud-tab">
        <!-- Primary Server Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-cloud"></i> Primary Nextcloud Server</h3>
                <span class="badge badge-primary">Active</span>
            </div>
            <div class="card-body">
                <div class="integration-status <?php echo !empty($settings['nextcloud_url']) ? 'connected' : 'disconnected'; ?>">
                    <div class="status-icon">
                        <i class="fas <?php echo !empty($settings['nextcloud_url']) ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    </div>
                    <div class="status-info">
                        <h4><?php echo !empty($settings['nextcloud_url']) ? 'Connected to Primary Server' : 'Not Connected'; ?></h4>
                        <p><?php echo !empty($settings['nextcloud_url']) ? htmlspecialchars($settings['nextcloud_url']) : 'Configure Nextcloud settings to enable file sync'; ?></p>
                    </div>
                </div>
                
                <form id="nextcloud-form" method="POST" action="process_settings.php" data-form-type="nextcloud">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="update_nextcloud">
                    <input type="hidden" name="redirect_page" value="system_tools">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Nextcloud URL</h4>
                                <p>Your primary Nextcloud server address</p>
                            </div>
                            <input type="url" name="nextcloud_url" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['nextcloud_url'] ?? ''); ?>"
                                   placeholder="https://cloud.example.com">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Username</h4>
                                <p>Nextcloud account username</p>
                            </div>
                            <input type="text" name="nextcloud_username" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['nextcloud_username'] ?? ''); ?>"
                                   placeholder="admin">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>App Password</h4>
                                <p>App-specific password (recommended over main password)<?php echo !empty($settings['nextcloud_password']) ? ' (currently set)' : ''; ?></p>
                            </div>
                            <input type="password" name="nextcloud_password" class="form-input" 
                                   placeholder="<?php echo !empty($settings['nextcloud_password']) ? 'Leave blank to keep current password' : 'Enter app password'; ?>">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Enable Auto-Sync</h4>
                                <p>Automatically sync backups and uploads</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="nextcloud_auto_sync" 
                                       <?php echo !empty($settings['nextcloud_auto_sync']) ? 'checked' : ''; ?>
                                       data-action="toggle-setting">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Directory Configuration -->
                    <div class="sync-options">
                        <h4><i class="fas fa-folder-tree"></i> Directory Configuration</h4>
                        <p class="help-text" style="margin-bottom: 16px;">Configure the Nextcloud directory path for each file type</p>
                        <div class="settings-list">
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4><i class="fas fa-database" style="color: #8B5CF6; margin-right: 8px;"></i>Backups Directory</h4>
                                    <p>Database backups storage path</p>
                                </div>
                                <input type="text" name="nextcloud_backups_dir" class="form-input" 
                                       value="<?php echo htmlspecialchars($settings['nextcloud_backups_dir'] ?? '/Arctic_Wolves/Backups'); ?>"
                                       placeholder="/Arctic_Wolves/Backups">
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4><i class="fas fa-video" style="color: #10b981; margin-right: 8px;"></i>Videos Directory</h4>
                                    <p>Video uploads storage path</p>
                                </div>
                                <input type="text" name="nextcloud_videos_dir" class="form-input" 
                                       value="<?php echo htmlspecialchars($settings['nextcloud_videos_dir'] ?? '/Arctic_Wolves/Videos'); ?>"
                                       placeholder="/Arctic_Wolves/Videos">
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4><i class="fas fa-receipt" style="color: #f59e0b; margin-right: 8px;"></i>Receipts Directory</h4>
                                    <p>Scanned receipts storage path</p>
                                </div>
                                <input type="text" name="nextcloud_receipts_dir" class="form-input" 
                                       value="<?php echo htmlspecialchars($settings['nextcloud_receipts_dir'] ?? '/Arctic_Wolves/Receipts'); ?>"
                                       placeholder="/Arctic_Wolves/Receipts">
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4><i class="fas fa-file-alt" style="color: #3b82f6; margin-right: 8px;"></i>Documents Directory</h4>
                                    <p>General documents storage path</p>
                                </div>
                                <input type="text" name="nextcloud_documents_dir" class="form-input" 
                                       value="<?php echo htmlspecialchars($settings['nextcloud_documents_dir'] ?? '/Arctic_Wolves/Documents'); ?>"
                                       placeholder="/Arctic_Wolves/Documents">
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4><i class="fas fa-users-cog" style="color: #ec4899; margin-right: 8px;"></i>HR Directory</h4>
                                    <p>Human Resources files storage path</p>
                                </div>
                                <input type="text" name="nextcloud_hr_dir" class="form-input" 
                                       value="<?php echo htmlspecialchars($settings['nextcloud_hr_dir'] ?? '/Arctic_Wolves/HR'); ?>"
                                       placeholder="/Arctic_Wolves/HR">
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4><i class="fas fa-user-times" style="color: #ef4444; margin-right: 8px;"></i>Terminations Directory</h4>
                                    <p>Staff termination documents (organized by Year/Month/StaffName)</p>
                                </div>
                                <input type="text" name="nextcloud_terminations_dir" class="form-input" 
                                       value="<?php echo htmlspecialchars($settings['nextcloud_terminations_dir'] ?? '/Arctic_Wolves/HR/Terminations'); ?>"
                                       placeholder="/Arctic_Wolves/HR/Terminations">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sync Options -->
                    <div class="sync-options" style="margin-top: 24px;">
                        <h4><i class="fas fa-check-double"></i> Sync Options</h4>
                        <p class="help-text" style="margin-bottom: 16px;">Select which content types to sync to Nextcloud</p>
                        <div class="checkbox-grid">
                            <label class="checkbox-item">
                                <input type="checkbox" name="sync_backups" 
                                       <?php echo ($settings['sync_backups'] ?? true) ? 'checked' : ''; ?>>
                                <span>Database Backups</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="sync_videos" 
                                       <?php echo ($settings['sync_videos'] ?? true) ? 'checked' : ''; ?>>
                                <span>Video Uploads</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="sync_receipts" 
                                       <?php echo ($settings['sync_receipts'] ?? true) ? 'checked' : ''; ?>>
                                <span>Receipt Scans</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="sync_documents" 
                                       <?php echo ($settings['sync_documents'] ?? true) ? 'checked' : ''; ?>>
                                <span>Documents</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="sync_hr" 
                                       <?php echo ($settings['sync_hr'] ?? true) ? 'checked' : ''; ?>>
                                <span>HR Files</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="sync_terminations" 
                                       <?php echo ($settings['sync_terminations'] ?? true) ? 'checked' : ''; ?>>
                                <span>Termination Documents</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="testNextcloudConnection('primary')">
                            <i class="fas fa-vial"></i> Test Connection
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="syncNow()">
                            <i class="fas fa-sync"></i> Sync Now
                        </button>
                        <button type="submit" class="btn btn-primary" data-action="save">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Backup Server Card -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <h3><i class="fas fa-cloud-upload-alt"></i> Backup Nextcloud Server (Redundancy)</h3>
                <span class="badge badge-secondary">Standby</span>
            </div>
            <div class="card-body">
                <div class="info-box" style="margin-bottom: 24px;">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <p><strong>Redundancy Configuration:</strong> The backup server receives periodic copies of all files from the primary. If the primary becomes unavailable for 5 minutes, the system automatically fails over to the backup server and promotes it to primary. When the original primary comes back online, files saved during the outage are synced back to it.</p>
                    </div>
                </div>
                
                <div class="integration-status <?php echo !empty($settings['nextcloud_backup_url']) ? 'connected' : 'disconnected'; ?>">
                    <div class="status-icon">
                        <i class="fas <?php echo !empty($settings['nextcloud_backup_url']) ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    </div>
                    <div class="status-info">
                        <h4><?php echo !empty($settings['nextcloud_backup_url']) ? 'Backup Server Connected' : 'Backup Server Not Configured'; ?></h4>
                        <p><?php echo !empty($settings['nextcloud_backup_url']) ? htmlspecialchars($settings['nextcloud_backup_url']) : 'Configure backup server for redundancy'; ?></p>
                    </div>
                </div>
                
                <form id="nextcloud-backup-form" method="POST" action="process_settings.php" data-form-type="nextcloud-backup">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="update_nextcloud_backup">
                    <input type="hidden" name="redirect_page" value="system_tools">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Enable Backup Server</h4>
                                <p>Activate redundant Nextcloud server for failover</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="nextcloud_backup_enabled" 
                                       <?php echo !empty($settings['nextcloud_backup_enabled']) ? 'checked' : ''; ?>
                                       data-action="toggle-setting">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Backup Server URL</h4>
                                <p>Your backup Nextcloud server address</p>
                            </div>
                            <input type="url" name="nextcloud_backup_url" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['nextcloud_backup_url'] ?? ''); ?>"
                                   placeholder="https://backup-cloud.example.com">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Username</h4>
                                <p>Backup server account username</p>
                            </div>
                            <input type="text" name="nextcloud_backup_username" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['nextcloud_backup_username'] ?? ''); ?>"
                                   placeholder="admin">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>App Password</h4>
                                <p>Backup server app-specific password<?php echo !empty($settings['nextcloud_backup_password']) ? ' (currently set)' : ''; ?></p>
                            </div>
                            <input type="password" name="nextcloud_backup_password" class="form-input" 
                                   placeholder="<?php echo !empty($settings['nextcloud_backup_password']) ? 'Leave blank to keep current password' : 'Enter app password'; ?>">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Failover Timeout (seconds)</h4>
                                <p>Time to wait before failing over to backup (default: 300 = 5 minutes)</p>
                            </div>
                            <input type="number" name="nextcloud_failover_timeout" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['nextcloud_failover_timeout'] ?? '300'); ?>"
                                   placeholder="300" min="60" max="3600">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Sync Interval (minutes)</h4>
                                <p>How often to sync files from primary to backup</p>
                            </div>
                            <input type="number" name="nextcloud_sync_interval" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['nextcloud_sync_interval'] ?? '60'); ?>"
                                   placeholder="60" min="5" max="1440">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="testNextcloudConnection('backup')">
                            <i class="fas fa-vial"></i> Test Backup Connection
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="syncToBackup()">
                            <i class="fas fa-sync"></i> Sync to Backup Now
                        </button>
                        <button type="submit" class="btn btn-primary" data-action="save">
                            <i class="fas fa-save"></i> Save Backup Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- DocuSeal Tab -->
    <div class="tab-content <?php echo $activeTab === 'docuseal' ? 'active' : ''; ?>" id="docuseal-tab">
        <form id="docuseal-form" method="POST" action="process_settings.php" data-form-type="docuseal">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update_docuseal">
            <input type="hidden" name="redirect_page" value="system_tools">
            
            <!-- DocuSeal Configuration Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-file-signature"></i> DocuSeal Configuration</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info" style="margin-bottom: 20px;">
                        <i class="fas fa-info-circle"></i>
                        <span>DocuSeal is used for e-signature workflows on employee contracts. Create templates in DocuSeal and link them here. <a href="https://www.docuseal.co/docs" target="_blank" style="color: inherit;">Learn more</a></span>
                    </div>
                    
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Enable DocuSeal</h4>
                                <p>Enable e-signature features for employee contracts</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="docuseal_enabled" 
                                       <?php echo !empty($settings['docuseal_enabled']) ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>DocuSeal URL</h4>
                                <p>The base URL of your DocuSeal instance (e.g., https://docuseal.example.com or https://api.docuseal.co)</p>
                            </div>
                            <input type="url" name="docuseal_url" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['docuseal_url'] ?? ''); ?>"
                                   placeholder="https://api.docuseal.co">
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>API Key</h4>
                                <p>Your DocuSeal API authentication key (found in Settings > API)</p>
                            </div>
                            <input type="password" name="docuseal_api_key" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['docuseal_api_key'] ?? ''); ?>"
                                   placeholder="Enter your DocuSeal API key">
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Webhook Secret (Optional)</h4>
                                <p>Secret key for verifying webhook callbacks from DocuSeal</p>
                            </div>
                            <input type="password" name="docuseal_webhook_secret" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['docuseal_webhook_secret'] ?? ''); ?>"
                                   placeholder="Webhook secret for signature verification">
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Verify SSL Certificate</h4>
                                <p>Enable SSL certificate verification for secure connections (recommended for production)</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="docuseal_verify_ssl" 
                                       <?php echo ($settings['docuseal_verify_ssl'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Test Connection & Save -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-plug"></i> Connection Test</h3>
                </div>
                <div class="card-body">
                    <div class="flex-row" style="display: flex; gap: 12px; align-items: center;">
                        <button type="button" id="test-docuseal" class="btn-secondary">
                            <i class="fas fa-plug"></i> Test Connection
                        </button>
                        <span id="docuseal-status"></span>
                    </div>
                </div>
            </div>
            
            <!-- E-Signature Settings Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-signature"></i> E-Signature Settings</h3>
                </div>
                <div class="card-body">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Auto-send Confirmation Email</h4>
                                <p>Send confirmation email from Arctic Wolves when contract is signed (in addition to DocuSeal notifications)</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="docuseal_auto_confirm" 
                                       <?php echo ($settings['docuseal_auto_confirm'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save DocuSeal Settings</button>
            </div>
        </form>
    </div>

    <!-- Payments Tab -->
    <div class="tab-content <?php echo $activeTab === 'payments' ? 'active' : ''; ?>" id="payments-tab">
        <form id="payments-form" method="POST" action="process_settings.php" data-form-type="payments">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update_payments">
            <input type="hidden" name="redirect_page" value="system_tools">
            
            <!-- Stripe Payment Configuration Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-credit-card"></i> Stripe Payment Configuration</h3>
                </div>
                <div class="card-body">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Stripe Publishable Key</h4>
                                <p>Public key for frontend payment processing (starts with pk_)</p>
                            </div>
                            <input type="text" name="stripe_publishable_key" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['stripe_publishable_key'] ?? ''); ?>"
                                   placeholder="pk_test_..." style="min-width: 300px;">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Stripe Secret Key</h4>
                                <p>Secret key for backend processing (starts with sk_)<?php echo !empty($settings['stripe_secret_key']) ? ' (currently set)' : ''; ?></p>
                            </div>
                            <input type="password" name="stripe_secret_key" class="form-input" 
                                   placeholder="<?php echo !empty($settings['stripe_secret_key']) ? 'Leave blank to keep current key' : 'sk_test_...'; ?>" style="min-width: 300px;">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Currency</h4>
                                <p>Default currency for all transactions</p>
                            </div>
                            <select name="currency" class="form-input" style="width: auto; min-width: 200px;">
                                <?php
                                $currencies = ['CAD' => 'Canadian Dollar (CAD)', 'USD' => 'US Dollar (USD)', 'EUR' => 'Euro (EUR)', 'GBP' => 'British Pound (GBP)'];
                                $current_currency = $settings['currency'] ?? 'CAD';
                                foreach ($currencies as $code => $name) {
                                    $selected = ($code === $current_currency) ? 'selected' : '';
                                    echo "<option value=\"" . htmlspecialchars($code) . "\" $selected>" . htmlspecialchars($name) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <p>To obtain Stripe API keys, visit the <a href="https://dashboard.stripe.com/apikeys" target="_blank" style="color: #8B5CF6;">Stripe Dashboard → API Keys</a>. Use test keys (pk_test_/sk_test_) for development and live keys (pk_live_/sk_live_) for production.</p>
                    </div>
                </div>
            </div>
            
            <!-- Tax Settings Card -->
            <div class="card" style="margin-top: 24px;">
                <div class="card-header">
                    <h3><i class="fas fa-percentage"></i> Tax Settings</h3>
                </div>
                <div class="card-body">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Tax Name</h4>
                                <p>Label for tax on invoices (e.g., HST, GST, VAT)</p>
                            </div>
                            <input type="text" name="tax_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['tax_name'] ?? 'HST'); ?>"
                                   placeholder="HST" style="width: auto; min-width: 150px;">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Tax Rate (%)</h4>
                                <p>Percentage rate for tax calculations</p>
                            </div>
                            <input type="number" name="tax_rate" class="form-input" step="0.01" min="0" max="100"
                                   value="<?php echo htmlspecialchars($settings['tax_rate'] ?? '13.00'); ?>"
                                   placeholder="13.00" style="width: auto; min-width: 120px;">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" data-action="save">
                            <i class="fas fa-save"></i> Save Payment & Tax Settings
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Theme Tab -->
    <div class="tab-content <?php echo $activeTab === 'theme' ? 'active' : ''; ?>" id="theme-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-palette"></i> Theme Customization</h3>
            </div>
            <div class="card-body">
                <form id="theme-form" method="POST" action="process_theme.php" enctype="multipart/form-data" data-form-type="theme">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="update_theme">
                    <input type="hidden" name="redirect_page" value="system_tools">
                    
                    <!-- Color Scheme Section -->
                    <div class="sync-options" style="margin-bottom: 24px;">
                        <h4><i class="fas fa-swatchbook"></i> Color Scheme</h4>
                        <p class="help-text" style="margin-bottom: 16px;">Customize the application's color palette</p>
                        <div class="theme-colors">
                            <div class="color-picker-item">
                                <label>Primary Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="primary_color" id="primary_color" value="<?php echo htmlspecialchars($settings['primary_color'] ?? '#6B46C1'); ?>">
                                    <input type="text" class="form-input" id="primary_color_text" value="<?php echo htmlspecialchars($settings['primary_color'] ?? '#6B46C1'); ?>" readonly>
                                </div>
                            </div>
                            <div class="color-picker-item">
                                <label>Accent Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="accent_color" id="accent_color" value="<?php echo htmlspecialchars($settings['accent_color'] ?? '#8B5CF6'); ?>">
                                    <input type="text" class="form-input" id="accent_color_text" value="<?php echo htmlspecialchars($settings['accent_color'] ?? '#8B5CF6'); ?>" readonly>
                                </div>
                            </div>
                            <div class="color-picker-item">
                                <label>Background Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="bg_color" id="bg_color" value="<?php echo htmlspecialchars($settings['background_color'] ?? '#06080b'); ?>">
                                    <input type="text" class="form-input" id="bg_color_text" value="<?php echo htmlspecialchars($settings['background_color'] ?? '#06080b'); ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Logo & Branding Section -->
                    <div class="sync-options" style="margin-bottom: 24px;">
                        <h4><i class="fas fa-image"></i> Logo & Branding</h4>
                        <p class="help-text" style="margin-bottom: 16px;">Upload a logo or use a URL. The logo will also be used as the site favicon.</p>
                        
                        <div class="settings-list">
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Logo Source</h4>
                                    <p>Choose how to provide your logo</p>
                                </div>
                                <div class="logo-source-options" style="display: flex; gap: 20px;">
                                    <label class="radio-item">
                                        <input type="radio" name="logo_method" value="upload" <?php echo (($settings['logo_method'] ?? 'url') === 'upload') ? 'checked' : ''; ?> onchange="toggleLogoInput()">
                                        <span>Upload File</span>
                                    </label>
                                    <label class="radio-item">
                                        <input type="radio" name="logo_method" value="url" <?php echo (($settings['logo_method'] ?? 'url') === 'url') ? 'checked' : ''; ?> onchange="toggleLogoInput()">
                                        <span>Use URL</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="setting-item" id="logo-upload-row" style="<?php echo (($settings['logo_method'] ?? 'url') === 'url') ? 'display: none;' : ''; ?>">
                                <div class="setting-info">
                                    <h4>Upload Logo</h4>
                                    <p>Recommended: PNG or SVG, 200x50px or higher</p>
                                </div>
                                <input type="file" name="logo" class="form-input" accept="image/*" style="max-width: 300px;">
                            </div>
                            
                            <div class="setting-item" id="logo-url-row" style="<?php echo (($settings['logo_method'] ?? 'url') === 'upload') ? 'display: none;' : ''; ?>">
                                <div class="setting-info">
                                    <h4>Logo URL</h4>
                                    <p>Direct URL to your logo image</p>
                                </div>
                                <input type="url" name="logo_url" class="form-input" 
                                       value="<?php echo htmlspecialchars($settings['logo_url'] ?? ''); ?>"
                                       placeholder="https://example.com/logo.png" style="min-width: 300px;">
                            </div>
                            
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Use Logo as Favicon</h4>
                                    <p>Automatically use the logo as the browser favicon (tab icon)</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="use_logo_as_favicon" 
                                           <?php echo !empty($settings['use_logo_as_favicon']) ? 'checked' : ''; ?>
                                           data-action="toggle-setting">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <?php if (!empty($settings['logo_url'])): ?>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Current Logo Preview</h4>
                                    <p>This logo is currently in use</p>
                                </div>
                                <div class="logo-preview" style="background: #0A0A0F; padding: 16px; border-radius: 8px; border: 1px solid var(--border);">
                                    <img src="<?php echo htmlspecialchars($settings['logo_url']); ?>" alt="Current Logo" style="max-height: 60px; max-width: 200px;">
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="resetThemeToDefault()">
                            <i class="fas fa-undo"></i> Reset to Default
                        </button>
                        <button type="submit" class="btn btn-primary" data-action="save">
                            <i class="fas fa-save"></i> Save Theme
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Database Tab -->
    <div class="tab-content <?php echo $activeTab === 'database' ? 'active' : ''; ?>" id="database-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-database"></i> Database Tools</h3>
            </div>
            <div class="card-body">
                <div class="db-tools-grid">
                    <div class="db-tool-card">
                        <i class="fas fa-download"></i>
                        <h4>Backup Database</h4>
                        <p>Create a full database backup</p>
                        <button class="btn btn-primary" onclick="performDatabaseBackup()">
                            <i class="fas fa-download"></i> Backup Now
                        </button>
                    </div>
                    <div class="db-tool-card">
                        <i class="fas fa-upload"></i>
                        <h4>Restore Database</h4>
                        <p>Restore from backup file</p>
                        <button class="btn btn-secondary" onclick="showRestoreModal()">
                            <i class="fas fa-upload"></i> Restore
                        </button>
                    </div>
                    <div class="db-tool-card">
                        <i class="fas fa-wrench"></i>
                        <h4>Repair & Optimize</h4>
                        <p>Repair damaged tables and optimize for performance</p>
                        <button class="btn btn-secondary" onclick="repairOptimizeDatabase()">
                            <i class="fas fa-wrench"></i> Repair & Optimize
                        </button>
                    </div>
                    <div class="db-tool-card warning">
                        <i class="fas fa-trash-alt"></i>
                        <h4>Clear Cache</h4>
                        <p>Clear all cached data and temporary files</p>
                        <button class="btn btn-secondary" onclick="clearCache()">
                            <i class="fas fa-trash-alt"></i> Clear Cache
                        </button>
                    </div>
                </div>
                
                <!-- Database Status Info -->
                <div class="info-box" style="margin-top: 24px;">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <p><strong>Repair & Optimize</strong> performs the following operations:</p>
                        <ul style="margin: 8px 0; padding-left: 20px; color: var(--text-dim);">
                            <li>CHECK TABLE - Identifies corrupted or damaged tables</li>
                            <li>REPAIR TABLE - Fixes any corruption found</li>
                            <li>OPTIMIZE TABLE - Defragments tables and reclaims unused space</li>
                            <li>ANALYZE TABLE - Updates table statistics for better query performance</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cron Jobs Tab -->
    <div class="tab-content <?php echo $activeTab === 'cron' ? 'active' : ''; ?>" id="cron-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> Scheduled Tasks</h3>
                <button class="btn btn-secondary" data-action="run-all">
                    <i class="fas fa-play"></i> Run All
                </button>
            </div>
            <div class="card-body">
                <p class="placeholder-text">Cron job monitoring and management</p>
            </div>
        </div>
    </div>
    
    <!-- Production Mode Tab -->
    <div class="tab-content <?php echo $activeTab === 'production' ? 'active' : ''; ?>" id="production-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-rocket"></i> Production Mode</h3>
            </div>
            <div class="card-body">
                <div class="production-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <h4>⚠️ WARNING: Production Mode Preparation</h4>
                        <p>This action will permanently remove all demo data from the database. This operation cannot be undone.</p>
                    </div>
                </div>
                
                <div class="demo-status">
                    <h4><i class="fas fa-info-circle"></i> Demo Data Status</h4>
                    <div id="demo-count-display">
                        <p class="loading-text">
                            <i class="fas fa-spinner fa-spin"></i> Checking demo data...
                        </p>
                    </div>
                </div>
                
                <div class="production-info">
                    <h4>What happens when you activate Production Mode?</h4>
                    <ul>
                        <li><i class="fas fa-check"></i> All demo users (coaches, athletes, parents) will be removed</li>
                        <li><i class="fas fa-check"></i> All demo sessions, bookings, and practice plans will be deleted</li>
                        <li><i class="fas fa-check"></i> All demo drills, exercises, and videos will be removed</li>
                        <li><i class="fas fa-check"></i> All demo goals, evaluations, and notifications will be deleted</li>
                        <li><i class="fas fa-check"></i> All demo expenses, packages, and discount codes will be removed</li>
                        <li><i class="fas fa-check"></i> Your admin account and real data will remain intact</li>
                    </ul>
                </div>
                
                <div class="production-actions">
                    <button type="button" class="btn btn-danger btn-large" onclick="confirmProductionMode()">
                        <i class="fas fa-rocket"></i> Activate Production Mode
                    </button>
                    <p class="help-text">Make sure you have backed up your database before proceeding</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Updates Tab -->
    <div class="tab-content <?php echo $activeTab === 'updates' ? 'active' : ''; ?>" id="updates-tab">
        <!-- System Updates Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-code-branch"></i> System Updates</h3>
            </div>
            <div class="card-body">
                <div class="info-box" style="margin-bottom: 24px;">
                    <i class="fas fa-info-circle"></i>
                    <p>Check for and apply updates from the Arctic Wolves GitHub repository. Updates include new features, bug fixes, and security patches.</p>
                </div>
                
                <div id="github-update-status" class="integration-status disconnected" style="margin-bottom: 24px;">
                    <div class="status-icon">
                        <i class="fas fa-sync"></i>
                    </div>
                    <div class="status-info">
                        <h4>Update Status</h4>
                        <p>Click "Check for Updates" to see available updates</p>
                    </div>
                </div>
                
                <form id="github-form" method="POST" action="process_settings.php" data-form-type="github">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="update_github_settings">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>GitHub Personal Access Token (Optional)</h4>
                                <p>Required only if the repository is private. Leave blank for public repos.<?php echo !empty($settings['github_token']) ? ' (currently set)' : ''; ?></p>
                            </div>
                            <input type="password" name="github_token" class="form-input" 
                                   placeholder="<?php echo !empty($settings['github_token']) ? 'Leave blank to keep current token' : 'ghp_xxxxxxxxxxxxx'; ?>"
                                   style="min-width: 300px;">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="testGitHubConnection()">
                            <i class="fas fa-vial"></i> Test Connection
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="checkForUpdates()">
                            <i class="fas fa-sync"></i> Check for Updates
                        </button>
                        <button type="button" class="btn btn-primary" onclick="applyUpdates()" id="apply-updates-btn" disabled>
                            <i class="fas fa-download"></i> Apply Updates
                        </button>
                    </div>
                </form>
                
                <!-- Update Log -->
                <div id="update-log" style="display: none; margin-top: 24px;">
                    <h4 style="color: var(--text-white); margin-bottom: 12px;"><i class="fas fa-list"></i> Update Log</h4>
                    <div id="update-log-content" style="background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; padding: 16px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 13px; color: var(--text-dim);"></div>
                </div>
            </div>
        </div>
        
        <!-- Stripe PHP Library Updates -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <h3><i class="fab fa-stripe-s"></i> Stripe PHP Library</h3>
            </div>
            <div class="card-body">
                <div class="info-box" style="margin-bottom: 24px;">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <p>Update the Stripe PHP library from the official repository: <a href="https://github.com/stripe/stripe-php" target="_blank" style="color: #8B5CF6;">stripe/stripe-php</a></p>
                        <p style="margin-top: 8px; font-size: 13px; color: var(--text-dim);">The Stripe PHP library is located in the <code>/stripe-php</code> directory at the root of this application.</p>
                    </div>
                </div>
                
                <?php
                // Get current Stripe version if available - validate path is within expected directory
                $stripe_base_path = realpath(__DIR__ . '/../stripe-php');
                $stripe_version_file = $stripe_base_path ? $stripe_base_path . '/VERSION' : '';
                $current_stripe_version = 'Unknown';
                if ($stripe_version_file && strpos(realpath(dirname($stripe_version_file)), $stripe_base_path) === 0) {
                    if (file_exists($stripe_version_file)) {
                        $current_stripe_version = trim(file_get_contents($stripe_version_file));
                    }
                }
                ?>
                
                <div class="integration-status connected" style="margin-bottom: 24px;">
                    <div class="status-icon">
                        <i class="fab fa-stripe-s"></i>
                    </div>
                    <div class="status-info">
                        <h4>Current Version: <?php echo htmlspecialchars($current_stripe_version); ?></h4>
                        <p>Stripe PHP Library installed in /stripe-php</p>
                    </div>
                </div>
                
                <div id="stripe-update-status" style="display: none; margin-bottom: 24px;"></div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="checkStripeUpdates()">
                        <i class="fas fa-sync"></i> Check for Updates
                    </button>
                    <button type="button" class="btn btn-primary" onclick="updateStripeLibrary()" id="update-stripe-btn" disabled>
                        <i class="fas fa-download"></i> Update Stripe Library
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Landing Page Settings Tab -->
    <div class="tab-content <?php echo $activeTab === 'landing' ? 'active' : ''; ?>" id="landing-tab">
        <form method="POST" action="process_settings.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update_landing">
            
            <!-- Programs Section -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-columns"></i> Programs Section</h3>
                </div>
                <div class="card-body">
                    <div class="info-box" style="margin-bottom: 24px;">
                        <i class="fas fa-info-circle"></i>
                        <p>Edit the training programs displayed on the public landing page. Leave fields empty to use default values.</p>
                    </div>
                    
                    <?php 
                    $default_programs = [
                        ['title' => 'Player Dev', 'tags' => 'Power Skating, Shooting', 'description' => 'Forwards & Defense: Explosive edgework and shot mechanics.'],
                        ['title' => 'Goalie Elite', 'tags' => 'Positioning, Tracking', 'description' => 'Crease management, angle control, and rebound psychology.'],
                        ['title' => 'Conditioning', 'tags' => 'Strength, Power', 'description' => 'Dryland training for endurance and explosive 60-minute power.'],
                        ['title' => 'Nutrition', 'tags' => 'Protein, Recovery', 'description' => 'Meal planning to fuel muscle growth and accelerate recovery.']
                    ];
                    for ($i = 1; $i <= 4; $i++): 
                        $program_prefix = "landing_program_{$i}_";
                    ?>
                    <div class="card" style="margin-bottom: 20px; border: 1px solid var(--border);">
                        <div class="card-header" style="padding: 12px 20px; background: var(--bg-main);">
                            <h4 style="font-size: 14px; margin: 0; color: var(--primary);"><i class="fas fa-cube"></i> Program <?php echo $i; ?></h4>
                        </div>
                        <div class="card-body" style="padding: 20px;">
                            <div class="settings-list">
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <h4>Title</h4>
                                        <p>Default: <?php echo htmlspecialchars($default_programs[$i-1]['title']); ?></p>
                                    </div>
                                    <input type="text" name="<?php echo $program_prefix; ?>title" class="form-input" 
                                           value="<?php echo htmlspecialchars($settings[$program_prefix . 'title'] ?? ''); ?>"
                                           placeholder="<?php echo htmlspecialchars($default_programs[$i-1]['title']); ?>">
                                </div>
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <h4>Image URL</h4>
                                        <p>Full URL to the program image</p>
                                    </div>
                                    <input type="url" name="<?php echo $program_prefix; ?>image" class="form-input" 
                                           value="<?php echo htmlspecialchars($settings[$program_prefix . 'image'] ?? ''); ?>"
                                           placeholder="https://...">
                                </div>
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <h4>Tags (comma-separated)</h4>
                                        <p>Default: <?php echo htmlspecialchars($default_programs[$i-1]['tags']); ?></p>
                                    </div>
                                    <input type="text" name="<?php echo $program_prefix; ?>tags" class="form-input" 
                                           value="<?php echo htmlspecialchars($settings[$program_prefix . 'tags'] ?? ''); ?>"
                                           placeholder="<?php echo htmlspecialchars($default_programs[$i-1]['tags']); ?>">
                                </div>
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <h4>Description</h4>
                                        <p>Default: <?php echo htmlspecialchars($default_programs[$i-1]['description']); ?></p>
                                    </div>
                                    <textarea name="<?php echo $program_prefix; ?>description" class="form-input" rows="2"
                                              placeholder="<?php echo htmlspecialchars($default_programs[$i-1]['description']); ?>"><?php echo htmlspecialchars($settings[$program_prefix . 'description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <!-- Standards Section -->
            <div class="card" style="margin-top: 24px;">
                <div class="card-header">
                    <h3><i class="fas fa-medal"></i> Standards Section</h3>
                </div>
                <div class="card-body">
                    <div class="info-box" style="margin-bottom: 24px;">
                        <i class="fas fa-info-circle"></i>
                        <p>Edit the elite standards displayed on the landing page. Leave fields empty to use default values.</p>
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
                    <div class="setting-item" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: center; padding: 15px; background: var(--bg-main); border-radius: 8px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-dim); margin-bottom: 8px;">Label <?php echo $i; ?> (Default: <?php echo htmlspecialchars($default_standards[$i-1]['label']); ?>)</label>
                            <input type="text" name="<?php echo $standard_prefix; ?>label" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings[$standard_prefix . 'label'] ?? ''); ?>"
                                   placeholder="<?php echo htmlspecialchars($default_standards[$i-1]['label']); ?>">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-dim); margin-bottom: 8px;">Value <?php echo $i; ?> (Default: <?php echo htmlspecialchars($default_standards[$i-1]['value']); ?>)</label>
                            <input type="text" name="<?php echo $standard_prefix; ?>value" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings[$standard_prefix . 'value'] ?? ''); ?>"
                                   placeholder="<?php echo htmlspecialchars($default_standards[$i-1]['value']); ?>">
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="form-actions" style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary" data-action="save">
                    <i class="fas fa-save"></i> Save Landing Page Settings
                </button>
                <button type="button" class="btn btn-secondary" onclick="if(confirm('This will clear all custom landing page content. After clearing, click Save to apply the changes and use default values.')) { document.querySelectorAll('#landing-tab input, #landing-tab textarea').forEach(el => el.value = ''); }">
                    <i class="fas fa-undo"></i> Reset to Defaults
                </button>
            </div>
        </form>
    </div>
</div>

<!-- SMTP Test Modal -->
<div id="smtp-test-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-envelope"></i> Test SMTP Connection</h3>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeSmtpTestModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>Enter an email address to send a test message:</p>
            <div class="form-group" style="margin-top: 16px;">
                <input type="email" id="smtp-test-email" class="form-input" placeholder="email@example.com" style="width: 100%;">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeSmtpTestModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="sendSmtpTestEmail()">
                <i class="fas fa-paper-plane"></i> Send Test Email
            </button>
        </div>
    </div>
</div>

<script>
function switchToolTab(tabName) {
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
    
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    document.getElementById(tabName + '-tab').classList.add('active');
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
    
    // Load demo data count when switching to production tab
    if (tabName === 'production') {
        loadDemoDataCount();
    }
}

// Load demo data count
function loadDemoDataCount() {
    const displayDiv = document.getElementById('demo-count-display');
    
    fetch('process_admin_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_demo_count&csrf_token=<?php echo htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES); ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const count = data.count || 0;
            const hasDemoData = count > 0;
            
            displayDiv.innerHTML = `
                <div class="demo-count ${hasDemoData ? 'has-data' : 'no-data'}">
                    <div class="count-number">${count}</div>
                    <div class="count-label">Demo Records</div>
                </div>
                ${hasDemoData ? 
                    '<p class="status-text warning"><i class="fas fa-database"></i> Demo data is present in the database</p>' : 
                    '<p class="status-text success"><i class="fas fa-check-circle"></i> No demo data found - database is clean</p>'
                }
            `;
        } else {
            displayDiv.innerHTML = '<p class="error-text">Failed to check demo data count</p>';
        }
    })
    .catch(error => {
        displayDiv.innerHTML = '<p class="error-text">Error checking demo data</p>';
        console.error('Error:', error);
    });
}

// Confirm and activate production mode
function confirmProductionMode() {
    const confirmed = confirm(
        '⚠️ FINAL WARNING ⚠️\n\n' +
        'This will PERMANENTLY DELETE all demo data from the database.\n\n' +
        'This action CANNOT be undone!\n\n' +
        'Are you absolutely sure you want to continue?'
    );
    
    if (!confirmed) return;
    
    // Second confirmation
    const doubleConfirmed = confirm(
        'Please confirm one more time:\n\n' +
        'Delete all demo data and prepare for production?'
    );
    
    if (!doubleConfirmed) return;
    
    // Show loading state
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Removing Demo Data...';
    
    // Execute cleanup
    fetch('process_admin_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=cleanup_demo_data&csrf_token=<?php echo htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES); ?>'
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        if (data.success) {
            alert(
                '✓ Production Mode Activated!\n\n' +
                `Successfully removed ${data.deleted_count || 0} demo records.\n\n` +
                'Your database is now clean and ready for production use.'
            );
            // Reload demo count
            loadDemoDataCount();
        } else {
            alert('Error: ' + (data.message || 'Failed to cleanup demo data'));
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Error: Failed to cleanup demo data');
        console.error('Error:', error);
    });
}

// Load demo count on page load if on production tab
document.addEventListener('DOMContentLoaded', function() {
    const activeTab = '<?php echo $activeTab; ?>';
    if (activeTab === 'production') {
        loadDemoDataCount();
    }
});

// SMTP Test Modal Functions
function testSmtpConnection() {
    document.getElementById('smtp-test-modal').classList.add('active');
    document.getElementById('smtp-test-email').value = '';
    document.getElementById('smtp-test-email').focus();
}

function closeSmtpTestModal() {
    document.getElementById('smtp-test-modal').classList.remove('active');
}

function sendSmtpTestEmail() {
    const testEmail = document.getElementById('smtp-test-email').value.trim();
    if (!testEmail) {
        alert('Please enter an email address.');
        return;
    }
    
    // Validate email format
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(testEmail)) {
        alert('Please enter a valid email address.');
        return;
    }
    
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    
    // Get CSRF token
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    fetch('process_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=test_smtp&test_email=${encodeURIComponent(testEmail)}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        closeSmtpTestModal();
        
        if (data.success) {
            alert('✓ Test email sent successfully!\n\nCheck your inbox for the test email.');
        } else {
            alert('✗ Failed to send test email:\n\n' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        closeSmtpTestModal();
        alert('Error: Failed to test SMTP connection');
        console.error('Error:', error);
    });
}

// Test Google Maps API Key
function testGoogleMapsAPI() {
    const apiKeyInput = document.querySelector('input[name="google_maps_api_key"]');
    const apiKey = apiKeyInput ? apiKeyInput.value.trim() : '';
    
    if (!apiKey) {
        alert('Please enter a Google Maps API key first.');
        return;
    }
    
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    
    // Test by sending a request to Google's Geocoding API
    // Note: API key will be visible in browser network logs - this is acceptable for testing
    // In production, restrict the API key by HTTP referrer in Google Cloud Console
    fetch(`https://maps.googleapis.com/maps/api/geocode/json?address=Toronto,Canada&key=${encodeURIComponent(apiKey)}`)
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        if (data.status === 'OK' || data.status === 'ZERO_RESULTS') {
            alert('✓ Google Maps API key is valid!\n\nThe API key is working correctly.');
        } else if (data.status === 'REQUEST_DENIED') {
            alert('✗ API Key Invalid or Restricted\n\n' + (data.error_message || 'Please check your API key and ensure the Geocoding API is enabled.'));
        } else {
            alert('✗ API Test Result: ' + data.status + '\n\n' + (data.error_message || 'Please check your API key configuration.'));
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('✗ Failed to test Google Maps API\n\nPlease check your network connection and API key.');
        console.error('Error:', error);
    });
}

// Theme functions
function toggleLogoInput() {
    const method = document.querySelector('input[name="logo_method"]:checked').value;
    document.getElementById('logo-upload-row').style.display = method === 'upload' ? '' : 'none';
    document.getElementById('logo-url-row').style.display = method === 'url' ? '' : 'none';
}

function resetThemeToDefault() {
    if (confirm('Reset all theme settings to default values?')) {
        document.getElementById('primary_color').value = '#6B46C1';
        document.getElementById('primary_color_text').value = '#6B46C1';
        document.getElementById('accent_color').value = '#8B5CF6';
        document.getElementById('accent_color_text').value = '#8B5CF6';
        document.getElementById('bg_color').value = '#06080b';
        document.getElementById('bg_color_text').value = '#06080b';
    }
}

// Color picker sync
document.querySelectorAll('input[type="color"]').forEach(input => {
    input.addEventListener('input', function() {
        const textInput = this.nextElementSibling;
        if (textInput && textInput.tagName === 'INPUT') {
            textInput.value = this.value;
        }
    });
});

// Database Tools functions
function performDatabaseBackup() {
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Backing up...';
    
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    fetch('process_database_backup.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=manual_backup&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        if (data.success) {
            alert('✓ Database Backup Complete!\n\n' + (data.message || 'Backup created successfully.'));
        } else {
            alert('✗ Backup Failed\n\n' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Error: Failed to create backup');
        console.error('Error:', error);
    });
}

function showRestoreModal() {
    window.location.href = 'dashboard.php?page=database_restore';
}

function repairOptimizeDatabase() {
    if (!confirm('This will repair and optimize all database tables. Continue?')) return;
    
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Optimizing...';
    
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    fetch('process_database_backup.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=repair_optimize&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        if (data.success) {
            alert('✓ Database Optimized!\n\n' + (data.message || 'All tables have been repaired and optimized.'));
        } else {
            alert('✗ Optimization Failed\n\n' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Error: Failed to optimize database');
        console.error('Error:', error);
    });
}

function clearCache() {
    if (!confirm('Clear all cached data? This may temporarily slow down the application.')) return;
    
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Clearing...';
    
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    fetch('process_database_backup.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=clear_cache&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        if (data.success) {
            alert('✓ Cache Cleared!\n\n' + (data.message || 'All cached data has been cleared.'));
        } else {
            alert('✗ Clear Cache Failed\n\n' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Error: Failed to clear cache');
        console.error('Error:', error);
    });
}

// Nextcloud functions
function testNextcloudConnection(serverType = 'primary') {
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    
    const formId = serverType === 'backup' ? 'nextcloud-backup-form' : 'nextcloud-form';
    const formData = new FormData(document.getElementById(formId));
    formData.append('action', serverType === 'backup' ? 'test_nextcloud_backup' : 'test_nextcloud');
    
    fetch('process_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        if (data.success) {
            alert('✓ Nextcloud Connection Successful!\n\n' + (data.message || 'Connected successfully.'));
        } else {
            alert('✗ Nextcloud Connection Failed\n\n' + (data.message || 'Could not connect to server'));
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Error testing Nextcloud connection');
        console.error('Error:', error);
    });
}

function syncToBackup() {
    if (!confirm('Sync all files from primary to backup server? This may take some time.')) return;
    
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';
    
    const formData = new FormData(document.getElementById('nextcloud-backup-form'));
    formData.append('action', 'sync_to_backup');
    
    fetch('process_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        if (data.success) {
            alert('✓ Sync Completed!\n\n' + (data.message || 'Files synced to backup server.'));
        } else {
            alert('✗ Sync Failed\n\n' + (data.message || 'Could not sync files'));
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Error syncing files');
        console.error('Error:', error);
    });
}

// GitHub Update functions
function testGitHubConnection() {
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    fetch('process_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=test_github&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        const statusDiv = document.getElementById('github-update-status');
        if (data.success) {
            statusDiv.className = 'integration-status connected';
            statusDiv.innerHTML = `
                <div class="status-icon"><i class="fas fa-check-circle"></i></div>
                <div class="status-info">
                    <h4>Connected to ${data.repo_name || 'Arctic_Wolves'}</h4>
                    <p>Repository access confirmed. ${data.private ? '(Private repository)' : '(Public repository)'}</p>
                </div>
            `;
            alert('✓ GitHub Connection Successful!');
        } else {
            statusDiv.className = 'integration-status disconnected';
            statusDiv.innerHTML = `
                <div class="status-icon"><i class="fas fa-times-circle"></i></div>
                <div class="status-info">
                    <h4>Connection Failed</h4>
                    <p>${data.message || 'Could not connect to repository'}</p>
                </div>
            `;
            alert('✗ GitHub Connection Failed\n\n' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Error testing GitHub connection');
        console.error('Error:', error);
    });
}

function checkForUpdates() {
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
    
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    fetch('process_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=check_updates&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        const statusDiv = document.getElementById('github-update-status');
        const applyBtn = document.getElementById('apply-updates-btn');
        
        if (data.success) {
            if (data.has_updates) {
                statusDiv.className = 'integration-status connected';
                statusDiv.innerHTML = `
                    <div class="status-icon"><i class="fas fa-arrow-circle-down"></i></div>
                    <div class="status-info">
                        <h4>Updates Available</h4>
                        <p>Latest commit: ${data.latest_commit.message || 'No message'}<br>
                        <small>By ${data.latest_commit.author || 'Unknown'} on ${new Date(data.latest_commit.date).toLocaleString()}</small></p>
                    </div>
                `;
                applyBtn.disabled = false;
            } else {
                statusDiv.className = 'integration-status connected';
                statusDiv.innerHTML = `
                    <div class="status-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="status-info">
                        <h4>System Up to Date</h4>
                        <p>You are running the latest version.</p>
                    </div>
                `;
                applyBtn.disabled = true;
            }
        } else {
            statusDiv.className = 'integration-status disconnected';
            statusDiv.innerHTML = `
                <div class="status-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="status-info">
                    <h4>Check Failed</h4>
                    <p>${data.message || 'Could not check for updates'}</p>
                </div>
            `;
            applyBtn.disabled = true;
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Error checking for updates');
        console.error('Error:', error);
    });
}

function applyUpdates() {
    if (!confirm('Apply all available updates? This will update system files.\n\nMake sure you have a backup before proceeding.')) return;
    
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    
    const logDiv = document.getElementById('update-log');
    const logContent = document.getElementById('update-log-content');
    logDiv.style.display = 'block';
    logContent.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying updates...\n';
    
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    fetch('process_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=apply_updates&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        if (data.success) {
            logContent.innerHTML = `✓ ${data.message}\n\nUpdated: ${data.updated_count || 0} files\nDeleted: ${data.deleted_count || 0} files`;
            if (data.errors && data.errors.length > 0) {
                logContent.innerHTML += '\n\nWarnings:\n' + data.errors.join('\n');
            }
            document.getElementById('apply-updates-btn').disabled = true;
            
            const statusDiv = document.getElementById('github-update-status');
            statusDiv.className = 'integration-status connected';
            statusDiv.innerHTML = `
                <div class="status-icon"><i class="fas fa-check-circle"></i></div>
                <div class="status-info">
                    <h4>Updates Applied Successfully</h4>
                    <p>System has been updated to the latest version.</p>
                </div>
            `;
        } else {
            logContent.innerHTML = '✗ Update failed: ' + (data.message || 'Unknown error');
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        logContent.innerHTML = '✗ Error applying updates: ' + error.message;
        console.error('Error:', error);
    });
}

// Stripe Library Update functions
function checkStripeUpdates() {
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
    
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    // Route through server-side endpoint for security
    fetch('process_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=check_stripe_updates&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        const statusDiv = document.getElementById('stripe-update-status');
        const updateBtn = document.getElementById('update-stripe-btn');
        
        if (data.success && data.tag_name) {
            const latestVersion = data.tag_name.replace('v', '');
            statusDiv.style.display = 'block';
            statusDiv.className = 'integration-status connected';
            statusDiv.innerHTML = `
                <div class="status-icon"><i class="fas fa-info-circle"></i></div>
                <div class="status-info">
                    <h4>Latest Version: ${latestVersion}</h4>
                    <p>${data.name || 'Stripe PHP Library'}<br>
                    <small>Released: ${new Date(data.published_at).toLocaleDateString()}</small></p>
                </div>
            `;
            updateBtn.disabled = false;
            updateBtn.setAttribute('data-version', data.tag_name);
        } else {
            statusDiv.style.display = 'block';
            statusDiv.className = 'integration-status disconnected';
            statusDiv.innerHTML = `
                <div class="status-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="status-info">
                    <h4>Check Failed</h4>
                    <p>${data.message || 'Could not retrieve latest Stripe version.'}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Error checking Stripe updates');
        console.error('Error:', error);
    });
}

function updateStripeLibrary() {
    if (!confirm('Update the Stripe PHP library?\n\nThis will download the latest version from GitHub.\n\nMake sure you have a backup before proceeding.')) return;
    
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    fetch('process_settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_stripe_library&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        if (data.success) {
            alert('✓ Stripe Library Updated!\n\n' + (data.message || 'Update completed successfully.'));
            location.reload();
        } else {
            alert('✗ Update Failed\n\n' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Error updating Stripe library');
        console.error('Error:', error);
    });
}

// Test DocuSeal Connection
document.getElementById('test-docuseal')?.addEventListener('click', function() {
    const btn = this;
    const statusSpan = document.getElementById('docuseal-status');
    const url = document.querySelector('input[name="docuseal_url"]').value;
    const apiKey = document.querySelector('input[name="docuseal_api_key"]').value;
    
    if (!url) {
        statusSpan.innerHTML = '<span style="color: #ef4444;"><i class="fas fa-times-circle"></i> Please enter a DocuSeal URL first</span>';
        return;
    }
    
    if (!apiKey) {
        statusSpan.innerHTML = '<span style="color: #ef4444;"><i class="fas fa-times-circle"></i> Please enter your DocuSeal API key</span>';
        return;
    }
    
    btn.disabled = true;
    statusSpan.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing connection...';
    
    const formData = new FormData();
    formData.append('action', 'test_docuseal');
    formData.append('docuseal_url', url);
    formData.append('docuseal_api_key', apiKey);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
    
    fetch('process_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        if (data.success) {
            statusSpan.innerHTML = '<span style="color: #00ff88;"><i class="fas fa-check-circle"></i> ' + (data.message || 'Connection successful!') + '</span>';
        } else {
            statusSpan.innerHTML = '<span style="color: #ef4444;"><i class="fas fa-times-circle"></i> ' + (data.message || 'Connection failed') + '</span>';
        }
    })
    .catch(error => {
        btn.disabled = false;
        statusSpan.innerHTML = '<span style="color: #ef4444;"><i class="fas fa-times-circle"></i> Error testing connection</span>';
        console.error('Error:', error);
    });
});
</script>

<style>
/* =========================================================
   SYSTEM TOOLS - Enhanced Modern Design
   ========================================================= */

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    max-width: 450px;
    width: 90%;
    overflow: hidden;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border, #2D2D3F);
    background: linear-gradient(180deg, rgba(107, 70, 193, 0.08) 0%, transparent 100%);
}

.modal-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: #fff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header h3 i {
    color: #8B5CF6;
}

.modal-close {
    background: none;
    border: none;
    color: #9ca3af;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}

.modal-close:hover {
    color: #fff;
}

.modal-body {
    padding: 24px;
}

.modal-body p {
    color: #9ca3af;
    font-size: 14px;
    margin: 0;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 24px;
    border-top: 1px solid var(--border, #2D2D3F);
}

/* Alert Styles */
.alert {
    padding: 16px 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    font-weight: 500;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid #10b981;
    color: #10b981;
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid #ef4444;
    color: #ef4444;
}

/* Page Header Styles */
.system-tools-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
}

.page-header-content {
    display: flex;
    align-items: center;
    gap: 20px;
}

.page-header-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    box-shadow: 0 8px 24px rgba(107, 70, 193, 0.3);
}

.page-header-text h1 {
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 4px 0;
    letter-spacing: -0.5px;
    color: var(--text-primary);
}

.page-header-text p {
    font-size: 14px;
    color: #9ca3af;
    margin: 0;
}

/* Modern System Tools Tabs */
.system-tools-tabs-wrapper {
    margin-bottom: 28px;
}

.system-tools-tabs {
    display: flex;
    gap: 6px;
    padding: 6px;
    background: var(--bg-card);
    border-radius: 14px;
    border: 1px solid var(--border);
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.system-tools-tabs::-webkit-scrollbar {
    display: none;
}

.tools-tab-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 20px;
    background: transparent;
    border: none;
    border-radius: 10px;
    color: var(--text-secondary);
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.tools-tab-link:hover {
    background: rgba(107, 70, 193, 0.1);
    color: var(--text-primary);
}

.tools-tab-link.active {
    background: var(--primary);
    color: #fff;
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.3);
}

.tools-tab-link i {
    font-size: 16px;
}

/* Tab Navigation Styles (legacy) */
.system-tools-content {
    max-width: 1200px;
    margin: 0 auto;
}

.tab-navigation {
    display: none;
}

.tab-link {
    display: none;
}

/* Legacy .tabs and .tab-btn styles for backward compatibility */
.tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    border-bottom: 2px solid var(--border);
}

.tab-btn {
    padding: 12px 24px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    color: var(--text-dim);
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: -2px;
}

.tab-btn:hover {
    color: var(--text-white);
    background: rgba(107, 70, 193, 0.1);
}

.tab-btn.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Enhanced Card Styles for System Tools */
.tab-content .card {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border);
    border-radius: 16px;
    margin-bottom: 24px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.tab-content .card-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(180deg, rgba(107, 70, 193, 0.08) 0%, transparent 100%);
}

.tab-content .card-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.tab-content .card-header h3 i {
    color: #8B5CF6;
}

.tab-content .card-body {
    padding: 28px;
}

/* Settings List Enhancement */
.settings-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    background: linear-gradient(135deg, rgba(107, 70, 193, 0.05) 0%, transparent 100%);
    border: 1px solid var(--border);
    border-radius: 12px;
    transition: all 0.3s ease;
    gap: 20px;
}

.setting-item:hover {
    border-color: var(--primary);
    transform: translateX(4px);
}

.setting-info {
    flex: 1;
    min-width: 0;
}

.setting-info h4 {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-white);
    margin: 0 0 4px 0;
}

.setting-info p {
    font-size: 13px;
    color: var(--text-dim);
    margin: 0;
}

.setting-item .form-input,
.setting-item .form-select {
    max-width: 280px;
    min-width: 200px;
}

/* Mileage Rates Styles */
.rate-input-group {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 6px 14px;
    transition: all 0.3s ease;
}

.rate-input-group:hover,
.rate-input-group:focus-within {
    border-color: var(--primary);
}

.rate-input-group .form-input {
    max-width: 100px;
    background: transparent;
    border: none;
    text-align: center;
    font-size: 18px;
    font-weight: 700;
    color: #8B5CF6;
}

.rate-input-group .form-input:focus {
    outline: none;
}

.currency-symbol {
    color: var(--primary);
    font-weight: 700;
    font-size: 18px;
}

.rate-unit {
    color: var(--text-dim);
    font-size: 14px;
}

.info-box {
    background: rgba(107, 70, 193, 0.1);
    border: 1px solid rgba(107, 70, 193, 0.3);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 24px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.info-box i {
    color: var(--primary);
    font-size: 20px;
    margin-top: 2px;
}

.info-box p {
    color: var(--text-white);
    font-size: 13px;
    line-height: 1.6;
    margin: 0;
}

.settings-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 24px;
}

.setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    transition: border-color 0.3s ease;
}

.setting-item:hover {
    border-color: var(--primary);
}

.setting-info {
    flex: 1;
    max-width: 60%;
}

.setting-info h4 {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 4px;
}

.setting-info p {
    font-size: 13px;
    color: var(--text-dim);
    margin: 0;
}

.setting-item .form-input {
    max-width: 300px;
}

/* Form Actions styling */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
    margin-top: 24px;
}

.form-actions .btn {
    min-width: 140px;
}

.theme-colors {
    display: grid;
    gap: 20px;
    margin-bottom: 24px;
}

.color-picker-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.color-picker-item label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-white);
}

.color-input-group {
    display: flex;
    gap: 12px;
    align-items: center;
}

.color-input-group input[type="color"] {
    width: 60px;
    height: 45px;
    border: 1px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    background: transparent;
}

.color-input-group .form-input {
    flex: 1;
    max-width: 200px;
}

.db-tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.db-tool-card {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    transition: all 0.3s ease;
}

.db-tool-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(107, 70, 193, 0.2);
}

.db-tool-card.warning:hover {
    border-color: var(--error);
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.2);
}

.db-tool-card > i {
    font-size: 40px;
    color: var(--primary);
    margin-bottom: 16px;
}

.db-tool-card.warning > i {
    color: var(--error);
}

.db-tool-card h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 8px;
}

.db-tool-card p {
    font-size: 13px;
    color: var(--text-dim);
    margin-bottom: 20px;
}

.db-tool-card button {
    width: 100%;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
    flex-shrink: 0;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--border);
    transition: 0.3s;
    border-radius: 26px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

.toggle-switch input:checked + .toggle-slider {
    background-color: var(--primary);
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(24px);
}

@media (max-width: 768px) {
    .system-tools-page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .page-header-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .system-tools-tabs {
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .tools-tab-link span {
        display: none;
    }
    
    .tools-tab-link {
        padding: 14px;
    }
    
    .tabs {
        overflow-x: auto;
    }
    
    .setting-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .setting-info {
        max-width: 100%;
    }
    
    .setting-item .form-input {
        max-width: 100%;
        width: 100%;
    }
    
    .db-tools-grid {
        grid-template-columns: 1fr;
    }
}

.toggle-switch {
    position: relative;
    width: 56px;
    height: 28px;
    display: inline-block;
    flex-shrink: 0;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--border);
    transition: all 0.3s ease;
    border-radius: 28px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background: #fff;
    transition: all 0.3s ease;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle-switch input:checked + .toggle-slider {
    background: linear-gradient(135deg, var(--primary), var(--primary-light, #8B5CF6));
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(28px);
}

.toggle-switch:hover .toggle-slider {
    box-shadow: 0 0 8px rgba(107, 70, 193, 0.3);
}

.theme-colors {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.color-picker-item label {
    display: block;
    font-size: 14px;
    font-weight: 700;
    color: var(--text-dim);
    margin-bottom: 10px;
}

.color-input-group {
    display: flex;
    gap: 10px;
}

.color-input-group input[type="color"] {
    width: 60px;
    height: 45px;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--bg-main);
    cursor: pointer;
}

.db-tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.db-tool-card {
    background: linear-gradient(135deg, var(--bg-main) 0%, rgba(107, 70, 193, 0.03) 100%);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 28px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.db-tool-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--primary-light, #8B5CF6));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.db-tool-card:hover {
    border-color: var(--primary);
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(107, 70, 193, 0.15);
}

.db-tool-card:hover::before {
    opacity: 1;
}

.db-tool-card > i {
    font-size: 48px;
    color: var(--primary);
    display: block;
    margin-bottom: 16px;
    transition: transform 0.3s ease;
}

.db-tool-card:hover > i {
    transform: scale(1.1);
}

.db-tool-card.warning > i {
    color: #ef4444;
}

.db-tool-card.warning::before {
    background: linear-gradient(90deg, #ef4444, #f87171);
}

.db-tool-card.warning:hover {
    border-color: #ef4444;
    box-shadow: 0 12px 24px rgba(239, 68, 68, 0.15);
}

.db-tool-card h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 10px;
}

.db-tool-card p {
    font-size: 14px;
    color: var(--text-dim);
    margin-bottom: 20px;
    line-height: 1.5;
}

/* Production Mode Styles */
.production-warning {
    background: rgba(239, 68, 68, 0.1);
    border: 2px solid #ef4444;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 24px;
    display: flex;
    gap: 16px;
    align-items: flex-start;
}

.production-warning i {
    font-size: 28px;
    color: #ef4444;
    margin-top: 4px;
}

.production-warning h4 {
    font-size: 16px;
    font-weight: 700;
    color: #ef4444;
    margin-bottom: 8px;
}

.production-warning p {
    font-size: 14px;
    color: var(--text-white);
    margin: 0;
}

.demo-status {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 24px;
}

.demo-status h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.demo-count {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: rgba(107, 70, 193, 0.05);
    border-radius: 8px;
    margin-bottom: 12px;
}

.count-number {
    font-size: 42px;
    font-weight: 900;
    color: var(--primary);
}

.count-label {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-text {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    margin: 0;
}

.status-text.warning {
    color: #f59e0b;
}

.status-text.success {
    color: #10b981;
}

.loading-text {
    color: var(--text-dim);
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.error-text {
    color: #ef4444;
    font-size: 14px;
}

.production-info {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 24px;
}

.production-info h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 16px;
}

.production-info ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.production-info li {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 0;
    font-size: 14px;
    color: var(--text-dim);
}

.production-info li i {
    color: #10b981;
    margin-top: 2px;
}

.production-actions {
    text-align: center;
    padding-top: 8px;
}

.btn-large {
    height: 52px;
    padding: 0 32px;
    font-size: 16px;
    font-weight: 700;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.help-text {
    margin-top: 12px;
    font-size: 13px;
    color: var(--text-dim);
}

/* Integration Status Styles */
.integration-status {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px 24px;
    border-radius: 12px;
    margin-bottom: 24px;
    border: 1px solid var(--border);
}

.integration-status.connected {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.3);
}

.integration-status.disconnected {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.3);
}

.integration-status .status-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.integration-status.connected .status-icon {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.integration-status.disconnected .status-icon {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.integration-status .status-info h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 4px;
}

.integration-status .status-info p {
    font-size: 13px;
    color: var(--text-dim);
    margin: 0;
}

/* Sync Options */
.sync-options {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-top: 24px;
    margin-bottom: 24px;
}

.sync-options h4 {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sync-options h4 i {
    color: var(--primary);
}

.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.checkbox-item:hover {
    border-color: var(--primary);
}

.checkbox-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--primary);
    cursor: pointer;
}

.checkbox-item span {
    font-size: 14px;
    color: var(--text-white);
}

.checkbox-item:has(input:checked) {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.1);
}

/* Mileage rate input group */
.rate-input-group {
    display: flex;
    align-items: center;
    gap: 8px;
    max-width: 200px;
}

.rate-input-group .currency-symbol {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-white);
}

.rate-input-group .form-input {
    flex: 1;
    text-align: right;
    max-width: 100px;
}

.rate-input-group .rate-unit {
    font-size: 14px;
    color: var(--text-dim);
    font-weight: 500;
}

/* Badge Styles */
.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-primary {
    background: var(--primary);
    color: #fff;
}

.badge-secondary {
    background: rgba(156, 163, 175, 0.2);
    color: #9ca3af;
}

/* Radio Item Styles */
.radio-item {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 8px 16px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    transition: all 0.3s ease;
}

.radio-item:hover {
    border-color: var(--primary);
}

.radio-item:has(input:checked) {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.1);
}

.radio-item input[type="radio"] {
    width: 16px;
    height: 16px;
    accent-color: var(--primary);
    cursor: pointer;
}

.radio-item span {
    font-size: 14px;
    color: var(--text-white);
}

/* Logo Preview Styles */
.logo-preview {
    display: inline-block;
}

.logo-preview img {
    display: block;
    object-fit: contain;
}

/* Update Log Styles */
#update-log-content {
    white-space: pre-wrap;
    word-break: break-word;
}

/* Card Header with Badge */
.card-header .badge {
    margin-left: auto;
}
</style>

<script>
// SMTP Connection Test
function testSmtpConnection() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    btn.disabled = true;
    
    const formData = new FormData(document.getElementById('smtp-form'));
    formData.append('action', 'test_smtp');
    
    fetch('process_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if (data.success) {
            alert('✓ SMTP Connection Successful!\n\nTest email sent successfully.');
        } else {
            alert('✗ SMTP Connection Failed\n\n' + (data.message || 'Could not connect to SMTP server'));
        }
    })
    .catch(error => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('Error testing SMTP connection');
        console.error('Error:', error);
    });
}

// Nextcloud Connection Test
function testNextcloudConnection() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    btn.disabled = true;
    
    const formData = new FormData(document.getElementById('nextcloud-form'));
    formData.append('action', 'test_nextcloud');
    
    fetch('process_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if (data.success) {
            alert('✓ Nextcloud Connection Successful!\n\nConnected to: ' + data.server_name);
        } else {
            alert('✗ Nextcloud Connection Failed\n\n' + (data.message || 'Could not connect to Nextcloud server'));
        }
    })
    .catch(error => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('Error testing Nextcloud connection');
        console.error('Error:', error);
    });
}

// Sync Now
function syncNow() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';
    btn.disabled = true;
    
    const formData = new FormData(document.getElementById('nextcloud-form'));
    formData.append('action', 'sync_nextcloud');
    
    fetch('process_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if (data.success) {
            alert('✓ Sync Completed!\n\n' + (data.message || 'Files synced successfully'));
        } else {
            alert('✗ Sync Failed\n\n' + (data.message || 'Could not sync files'));
        }
    })
    .catch(error => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('Error syncing files');
        console.error('Error:', error);
    });
}
</script>
