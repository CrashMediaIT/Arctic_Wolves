<!-- User Profile View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-user"></i> My Profile
    </h1>
    <p class="page-description">Manage your personal information and preferences</p>
</div>

<div class="profile-content">
    <!-- Profile Information -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-id-card"></i> Personal Information</h3>
        </div>
        <div class="card-body">
            <div class="profile-photo-section">
                <div class="profile-photo">
                    <i class="fas fa-user"></i>
                </div>
                <div class="photo-actions">
                    <button class="btn-secondary"><i class="fas fa-camera"></i> Change Photo</button>
                    <button class="btn-secondary"><i class="fas fa-trash"></i> Remove</button>
                </div>
            </div>

            <form class="profile-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" class="form-input" placeholder="First name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" class="form-input" placeholder="Last name" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" class="form-input" placeholder="email@example.com" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" class="form-input" placeholder="(555) 123-4567">
                    </div>
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <input type="text" class="form-input" placeholder="Street address">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" class="form-input" placeholder="City">
                    </div>
                    <div class="form-group">
                        <label>State</label>
                        <input type="text" class="form-input" placeholder="State">
                    </div>
                    <div class="form-group">
                        <label>Zip Code</label>
                        <input type="text" class="form-input" placeholder="12345">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-lock"></i> Change Password</h3>
        </div>
        <div class="card-body">
            <form class="password-form">
                <div class="form-group">
                    <label>Current Password *</label>
                    <input type="password" class="form-input" required>
                </div>

                <div class="form-group">
                    <label>New Password *</label>
                    <input type="password" class="form-input" required>
                </div>

                <div class="form-group">
                    <label>Confirm New Password *</label>
                    <input type="password" class="form-input" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-key"></i> Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Notification Preferences -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-bell"></i> Notification Preferences</h3>
        </div>
        <div class="card-body">
            <div class="preferences-list">
                <div class="preference-item">
                    <div class="preference-info">
                        <h4>Email Notifications</h4>
                        <p>Receive email updates for important activities</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="preference-item">
                    <div class="preference-info">
                        <h4>Session Reminders</h4>
                        <p>Get reminders before scheduled sessions</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="preference-item">
                    <div class="preference-info">
                        <h4>Marketing Emails</h4>
                        <p>Receive updates about new features and promotions</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.profile-photo-section {
    display: flex;
    align-items: center;
    gap: 30px;
    padding: 30px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 30px;
}

.profile-photo {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, var(--neon), var(--accent));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    color: #fff;
    flex-shrink: 0;
}

.photo-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.preferences-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.preference-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
}

.preference-info {
    flex: 1;
}

.preference-info h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 5px;
}

.preference-info p {
    font-size: 13px;
    color: var(--text-dim);
}
</style>
