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

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-cog"></i> System Tools
    </h1>
    <p class="page-description">System settings, theme, and database tools</p>
</div>

<div class="system-tools-content">
    <!-- System Tools Tabs -->
    <div class="tabs">
        <button class="tab-btn <?php echo $activeTab === 'settings' ? 'active' : ''; ?>" 
                data-tab="settings" onclick="switchToolTab('settings')">
            <i class="fas fa-sliders-h"></i> Settings
        </button>
        <button class="tab-btn <?php echo $activeTab === 'theme' ? 'active' : ''; ?>" 
                data-tab="theme" onclick="switchToolTab('theme')">
            <i class="fas fa-palette"></i> Theme
        </button>
        <button class="tab-btn <?php echo $activeTab === 'database' ? 'active' : ''; ?>" 
                data-tab="database" onclick="switchToolTab('database')">
            <i class="fas fa-database"></i> Database
        </button>
        <button class="tab-btn <?php echo $activeTab === 'cron' ? 'active' : ''; ?>" 
                data-tab="cron" onclick="switchToolTab('cron')">
            <i class="fas fa-clock"></i> Cron Jobs
        </button>
        <button class="tab-btn <?php echo $activeTab === 'production' ? 'active' : ''; ?>" 
                data-tab="production" onclick="switchToolTab('production')">
            <i class="fas fa-rocket"></i> Production Mode
        </button>
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
    width: 60px;
    height: 30px;
    display: inline-block;
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
    transition: 0.4s;
    border-radius: 30px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 4px;
    bottom: 4px;
    background: #fff;
    transition: 0.4s;
    border-radius: 50%;
}

.toggle-switch input:checked + .toggle-slider {
    background: var(--neon);
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(30px);
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
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 24px;
    text-align: center;
    transition: all 0.3s;
}

.db-tool-card:hover {
    border-color: var(--neon);
    transform: translateY(-3px);
}

.db-tool-card i {
    font-size: 48px;
    color: var(--neon);
    display: block;
    margin-bottom: 12px;
}

.db-tool-card.warning i {
    color: #ef4444;
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
</style>
