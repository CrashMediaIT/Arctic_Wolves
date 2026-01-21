<!-- Admin System Tools View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-cog"></i> System Tools
    </h1>
    <p class="page-description">System settings, theme, and database tools</p>
</div>

<div class="system-tools-content">
    <!-- System Tools Tabs -->
    <div class="system-tabs">
        <button class="tab-btn active" data-tab="settings">
            <i class="fas fa-sliders-h"></i> Settings
        </button>
        <button class="tab-btn" data-tab="theme">
            <i class="fas fa-palette"></i> Theme
        </button>
        <button class="tab-btn" data-tab="database">
            <i class="fas fa-database"></i> Database
        </button>
    </div>

    <!-- Settings Tab -->
    <div class="tab-content active" id="settings-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-sliders-h"></i> System Settings</h3>
            </div>
            <div class="card-body">
                <div class="settings-list">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Site Title</h4>
                            <p>The name of your site</p>
                        </div>
                        <input type="text" class="form-input" value="Crash Hockey">
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Site Email</h4>
                            <p>Primary contact email</p>
                        </div>
                        <input type="email" class="form-input" value="info@crashhockey.com">
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Session Duration</h4>
                            <p>Default session length in minutes</p>
                        </div>
                        <input type="number" class="form-input" value="60">
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Enable Notifications</h4>
                            <p>Send email notifications to users</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Maintenance Mode</h4>
                            <p>Put site in maintenance mode</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn-primary"><i class="fas fa-save"></i> Save Settings</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Theme Tab -->
    <div class="tab-content" id="theme-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-palette"></i> Theme Customization</h3>
            </div>
            <div class="card-body">
                <div class="theme-colors">
                    <div class="color-picker-item">
                        <label>Primary Color</label>
                        <div class="color-input-group">
                            <input type="color" value="#6B46C1">
                            <input type="text" class="form-input" value="#6B46C1">
                        </div>
                    </div>
                    <div class="color-picker-item">
                        <label>Accent Color</label>
                        <div class="color-input-group">
                            <input type="color" value="#8B5CF6">
                            <input type="text" class="form-input" value="#8B5CF6">
                        </div>
                    </div>
                    <div class="color-picker-item">
                        <label>Background Color</label>
                        <div class="color-input-group">
                            <input type="color" value="#06080b">
                            <input type="text" class="form-input" value="#06080b">
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn-secondary"><i class="fas fa-undo"></i> Reset to Default</button>
                    <button class="btn-primary"><i class="fas fa-save"></i> Save Theme</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Database Tab -->
    <div class="tab-content" id="database-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-database"></i> Database Tools</h3>
            </div>
            <div class="card-body">
                <div class="db-tools-grid">
                    <div class="db-tool-card">
                        <i class="fas fa-download"></i>
                        <h4>Backup Database</h4>
                        <p>Create a full database backup</p>
                        <button class="btn-primary"><i class="fas fa-download"></i> Backup Now</button>
                    </div>
                    <div class="db-tool-card">
                        <i class="fas fa-upload"></i>
                        <h4>Restore Database</h4>
                        <p>Restore from backup file</p>
                        <button class="btn-secondary"><i class="fas fa-upload"></i> Restore</button>
                    </div>
                    <div class="db-tool-card">
                        <i class="fas fa-sync"></i>
                        <h4>Optimize Database</h4>
                        <p>Optimize tables and clean up</p>
                        <button class="btn-secondary"><i class="fas fa-sync"></i> Optimize</button>
                    </div>
                    <div class="db-tool-card warning">
                        <i class="fas fa-trash-alt"></i>
                        <h4>Clear Cache</h4>
                        <p>Clear all cached data</p>
                        <button class="btn-secondary"><i class="fas fa-trash-alt"></i> Clear</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.system-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
}

.settings-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 30px;
}

.setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
}

.setting-info {
    flex: 1;
    max-width: 50%;
}

.setting-info h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 5px;
}

.setting-info p {
    font-size: 13px;
    color: var(--text-dim);
}

.setting-item .form-input {
    max-width: 300px;
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
    margin-bottom: 30px;
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
    padding: 30px;
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
    margin-bottom: 15px;
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
</style>
