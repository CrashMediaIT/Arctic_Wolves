<!-- Admin System Tools View -->
<?php
$activeTab = $_GET['tab'] ?? 'settings';

// Fetch system settings from database (if stored there)
try {
    // You could have a settings table, for now using defaults
    $settings = [
        'site_title' => 'Arctic Wolves',
        'site_email' => 'info@arcticwolves.ca',
        'session_duration' => 60,
        'notifications_enabled' => true,
        'maintenance_mode' => false
    ];
} catch (PDOException $e) {
    error_log("Settings fetch error: " . $e->getMessage());
    $settings = [];
}
?>

<div class="system-tools-page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-cog"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">System Tools</h1>
            <p class="page-description">Configure system settings, integrations, database, and maintenance</p>
        </div>
    </div>
</div>

<div class="system-tools-content">
    <!-- System Tools Tabs - Modern Navigation -->
    <div class="system-tools-tabs-wrapper">
        <div class="system-tools-tabs">
            <a href="?page=system_tools&tab=settings" class="tools-tab-link <?php echo $activeTab === 'settings' ? 'active' : ''; ?>">
                <i class="fas fa-sliders-h"></i>
                <span>Settings</span>
            </a>
            <a href="?page=system_tools&tab=mileage" class="tools-tab-link <?php echo $activeTab === 'mileage' ? 'active' : ''; ?>">
                <i class="fas fa-car"></i>
                <span>Mileage</span>
            </a>
            <a href="?page=system_tools&tab=smtp" class="tools-tab-link <?php echo $activeTab === 'smtp' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i>
                <span>SMTP</span>
            </a>
            <a href="?page=system_tools&tab=nextcloud" class="tools-tab-link <?php echo $activeTab === 'nextcloud' ? 'active' : ''; ?>">
                <i class="fas fa-cloud"></i>
                <span>Nextcloud</span>
            </a>
            <a href="?page=system_tools&tab=theme" class="tools-tab-link <?php echo $activeTab === 'theme' ? 'active' : ''; ?>">
                <i class="fas fa-palette"></i>
                <span>Theme</span>
            </a>
            <a href="?page=system_tools&tab=database" class="tools-tab-link <?php echo $activeTab === 'database' ? 'active' : ''; ?>">
                <i class="fas fa-database"></i>
                <span>Database</span>
            </a>
            <a href="?page=system_tools&tab=production" class="tools-tab-link <?php echo $activeTab === 'production' ? 'active' : ''; ?>">
                <i class="fas fa-rocket"></i>
                <span>Production</span>
            </a>
            <a href="system_health_validator.php" class="tools-tab-link">
                <i class="fas fa-heartbeat"></i>
                <span>Health</span>
            </a>
        </div>
    </div>

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
                            <i class="fas fa-save"></i> Save Mileage Rates
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
                            <input type="text" name="smtp_username" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>"
                                   placeholder="user@example.com">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>SMTP Password</h4>
                                <p>Email account password or app password</p>
                            </div>
                            <input type="password" name="smtp_password" class="form-input" 
                                   value="<?php echo !empty($settings['smtp_password']) ? '********' : ''; ?>"
                                   placeholder="Enter password">
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
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-cloud"></i> Nextcloud Integration</h3>
            </div>
            <div class="card-body">
                <div class="integration-status <?php echo !empty($settings['nextcloud_url']) ? 'connected' : 'disconnected'; ?>">
                    <div class="status-icon">
                        <i class="fas <?php echo !empty($settings['nextcloud_url']) ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    </div>
                    <div class="status-info">
                        <h4><?php echo !empty($settings['nextcloud_url']) ? 'Connected to Nextcloud' : 'Not Connected'; ?></h4>
                        <p><?php echo !empty($settings['nextcloud_url']) ? htmlspecialchars($settings['nextcloud_url']) : 'Configure Nextcloud settings to enable file sync'; ?></p>
                    </div>
                </div>
                
                <form id="nextcloud-form" method="POST" action="process_settings.php" data-form-type="nextcloud">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="update_nextcloud">
                    <div class="settings-list">
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Nextcloud URL</h4>
                                <p>Your Nextcloud server address</p>
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
                                <p>App-specific password (recommended over main password)</p>
                            </div>
                            <input type="password" name="nextcloud_password" class="form-input" 
                                   value="<?php echo !empty($settings['nextcloud_password']) ? '********' : ''; ?>"
                                   placeholder="Enter app password">
                        </div>
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Sync Folder</h4>
                                <p>Folder path for uploaded files</p>
                            </div>
                            <input type="text" name="nextcloud_folder" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['nextcloud_folder'] ?? '/Arctic_Wolves'); ?>"
                                   placeholder="/Arctic_Wolves">
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
                    
                    <div class="sync-options">
                        <h4><i class="fas fa-folder-open"></i> Sync Options</h4>
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
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="testNextcloudConnection()">
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
    </div>

    <!-- Theme Tab -->
    <div class="tab-content <?php echo $activeTab === 'theme' ? 'active' : ''; ?>" id="theme-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-palette"></i> Theme Customization</h3>
            </div>
            <div class="card-body">
                <form id="theme-form" method="POST" action="process_settings.php" data-form-type="theme">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="update_theme">
                    <div class="theme-colors">
                        <div class="color-picker-item">
                            <label>Primary Color</label>
                            <div class="color-input-group">
                                <input type="color" name="primary_color" value="#6B46C1">
                                <input type="text" class="form-input" value="#6B46C1" readonly>
                            </div>
                        </div>
                        <div class="color-picker-item">
                            <label>Accent Color</label>
                            <div class="color-input-group">
                                <input type="color" name="accent_color" value="#8B5CF6">
                                <input type="text" class="form-input" value="#8B5CF6" readonly>
                            </div>
                        </div>
                        <div class="color-picker-item">
                            <label>Background Color</label>
                            <div class="color-input-group">
                                <input type="color" name="bg_color" value="#06080b">
                                <input type="text" class="form-input" value="#06080b" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" data-action="reset">
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
                        <button class="btn btn-primary" data-action="backup">
                            <i class="fas fa-download"></i> Backup Now
                        </button>
                    </div>
                    <div class="db-tool-card">
                        <i class="fas fa-upload"></i>
                        <h4>Restore Database</h4>
                        <p>Restore from backup file</p>
                        <button class="btn btn-secondary" data-action="restore">
                            <i class="fas fa-upload"></i> Restore
                        </button>
                    </div>
                    <div class="db-tool-card">
                        <i class="fas fa-sync"></i>
                        <h4>Optimize Database</h4>
                        <p>Optimize tables and clean up</p>
                        <button class="btn btn-secondary" data-action="optimize">
                            <i class="fas fa-sync"></i> Optimize
                        </button>
                    </div>
                    <div class="db-tool-card warning">
                        <i class="fas fa-trash-alt"></i>
                        <h4>Clear Cache</h4>
                        <p>Clear all cached data</p>
                        <button class="btn btn-secondary" data-action="clear-cache">
                            <i class="fas fa-trash-alt"></i> Clear
                        </button>
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
        body: 'action=get_demo_count&<?php echo csrfTokenField(); ?>'
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
        body: 'action=cleanup_demo_data&<?php echo csrfTokenField(); ?>'
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
</script>

<style>
/* =========================================================
   SYSTEM TOOLS - Enhanced Modern Design
   ========================================================= */

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
    background: linear-gradient(135deg, var(--primary), #8B5CF6);
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
    color: var(--text-white);
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
    color: #9ca3af;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.tools-tab-link:hover {
    background: rgba(107, 70, 193, 0.1);
    color: var(--text-white);
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
