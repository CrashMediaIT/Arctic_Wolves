<!-- Global Settings View -->
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
            <form class="settings-form">
                <div class="form-group">
                    <label>Organization Name *</label>
                    <input type="text" class="form-input" value="Crash Hockey" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Contact Email *</label>
                        <input type="email" class="form-input" value="info@crashhockey.com" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Phone</label>
                        <input type="tel" class="form-input" placeholder="(555) 123-4567">
                    </div>
                </div>

                <div class="form-group">
                    <label>Organization Address</label>
                    <textarea class="form-textarea" rows="3" placeholder="Full address"></textarea>
                </div>

                <div class="form-group">
                    <label>Timezone *</label>
                    <select class="form-input" required>
                        <option>-- Select Timezone --</option>
                        <option selected>America/New_York (EST)</option>
                        <option>America/Chicago (CST)</option>
                        <option>America/Denver (MST)</option>
                        <option>America/Los_Angeles (PST)</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Currency</label>
                        <select class="form-input">
                            <option selected>USD ($)</option>
                            <option>CAD ($)</option>
                            <option>EUR (€)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date Format</label>
                        <select class="form-input">
                            <option selected>MM/DD/YYYY</option>
                            <option>DD/MM/YYYY</option>
                            <option>YYYY-MM-DD</option>
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
    margin-bottom: 15px;
}
</style>
