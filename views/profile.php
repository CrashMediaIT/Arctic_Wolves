<!-- User Profile View -->
<?php
// Fetch user data
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               CASE WHEN u.date_of_birth IS NOT NULL THEN u.date_of_birth ELSE u.birth_date END as dob
        FROM users u
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get additional athlete data if athlete
    $athleteData = null;
    if ($user_role === 'athlete') {
        $stmt = $pdo->prepare("
            SELECT * FROM athlete_stats WHERE user_id = ? ORDER BY season DESC LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $athleteData = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Profile data fetch error: " . $e->getMessage());
    $userData = [];
    $athleteData = null;
}

$activeTab = $_GET['tab'] ?? 'personal';
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-user"></i> My Profile
    </h1>
    <p class="page-description">Manage your personal information and preferences</p>
</div>

<!-- Tab Navigation -->
<div class="tabs">
    <button class="tab-btn <?php echo $activeTab === 'personal' ? 'active' : ''; ?>" 
            data-tab="personal" onclick="switchTab('personal')">
        <i class="fas fa-id-card"></i> Personal Info
    </button>
    <?php if ($user_role === 'athlete'): ?>
        <button class="tab-btn <?php echo $activeTab === 'player' ? 'active' : ''; ?>" 
                data-tab="player" onclick="switchTab('player')">
            <i class="fas fa-hockey-puck"></i> Player Info
        </button>
    <?php endif; ?>
    <button class="tab-btn <?php echo $activeTab === 'security' ? 'active' : ''; ?>" 
            data-tab="security" onclick="switchTab('security')">
        <i class="fas fa-lock"></i> Security
    </button>
    <button class="tab-btn <?php echo $activeTab === 'notifications' ? 'active' : ''; ?>" 
            data-tab="notifications" onclick="switchTab('notifications')">
        <i class="fas fa-bell"></i> Notifications
    </button>
</div>

<div class="profile-content">
    <!-- Personal Information Tab -->
    <div class="tab-content <?php echo $activeTab === 'personal' ? 'active' : ''; ?>" id="personal-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-id-card"></i> Personal Information</h3>
            </div>
            <div class="card-body">
                <div class="profile-photo-section">
                    <div class="profile-photo">
                        <?php if (!empty($userData['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($userData['profile_image']); ?>" alt="Profile">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="photo-actions">
                        <form method="POST" action="process_profile_update.php" enctype="multipart/form-data" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <input type="hidden" name="action" value="upload_photo">
                            <input type="file" name="profile_photo" id="profilePhotoInput" accept="image/*" style="display: none;" onchange="this.form.submit()">
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('profilePhotoInput').click()">
                                <i class="fas fa-camera"></i> Change Photo
                            </button>
                        </form>
                        <?php if (!empty($userData['profile_image'])): ?>
                            <form method="POST" action="process_profile_update.php" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                <input type="hidden" name="action" value="remove_photo">
                                <button type="submit" class="btn btn-secondary" onclick="return confirm('Remove profile photo?')">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <form class="profile-form" id="profile-form" method="POST" action="process_profile_update.php" data-form-type="profile">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="first_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($userData['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($userData['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" class="form-input" 
                                   value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" class="form-input" 
                                   value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Birth Date</label>
                            <input type="date" name="birth_date" class="form-input" 
                                   value="<?php echo htmlspecialchars($userData['dob'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Position</label>
                            <select name="position" class="form-select">
                                <option value="">Select Position</option>
                                <option value="forward" <?php echo ($userData['position'] ?? '') === 'forward' ? 'selected' : ''; ?>>Forward</option>
                                <option value="defense" <?php echo ($userData['position'] ?? '') === 'defense' ? 'selected' : ''; ?>>Defense</option>
                                <option value="goalie" <?php echo ($userData['position'] ?? '') === 'goalie' ? 'selected' : ''; ?>>Goalie</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Primary Arena</label>
                        <input type="text" name="primary_arena" class="form-input" 
                               value="<?php echo htmlspecialchars($userData['primary_arena'] ?? ''); ?>">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" data-action="save">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="button" class="btn btn-secondary" data-action="cancel">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Player Information Tab (Athletes Only) -->
    <?php if ($user_role === 'athlete'): ?>
        <div class="tab-content <?php echo $activeTab === 'player' ? 'active' : ''; ?>" id="player-tab">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-hockey-puck"></i> Player Information</h3>
                </div>
                <div class="card-body">
                    <form class="player-form" id="player-form" method="POST" action="process_profile_update.php" data-form-type="player">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="action" value="update_player_info">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Height (inches)</label>
                                <input type="number" name="height" class="form-input" 
                                       value="<?php echo htmlspecialchars($athleteData['height'] ?? ''); ?>" 
                                       placeholder="e.g., 72">
                                <small class="form-hint">Enter height in inches (5'10" = 70 inches)</small>
                            </div>
                            <div class="form-group">
                                <label>Weight (lbs)</label>
                                <input type="number" name="weight" class="form-input" 
                                       value="<?php echo htmlspecialchars($athleteData['weight'] ?? ''); ?>" 
                                       placeholder="e.g., 180">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Handedness / Shoots</label>
                                <select name="handedness" class="form-select">
                                    <option value="">Select</option>
                                    <option value="left" <?php echo ($athleteData['handedness'] ?? '') === 'left' ? 'selected' : ''; ?>>Left</option>
                                    <option value="right" <?php echo ($athleteData['handedness'] ?? '') === 'right' ? 'selected' : ''; ?>>Right</option>
                                </select>
                            </div>
                            <?php if (($userData['position'] ?? '') === 'goalie'): ?>
                            <div class="form-group">
                                <label>Catching Hand (Goalie)</label>
                                <select name="catching_hand" class="form-select">
                                    <option value="">Select</option>
                                    <option value="left" <?php echo ($athleteData['catching_hand'] ?? '') === 'left' ? 'selected' : ''; ?>>Left</option>
                                    <option value="right" <?php echo ($athleteData['catching_hand'] ?? '') === 'right' ? 'selected' : ''; ?>>Right</option>
                                </select>
                            </div>
                            <?php else: ?>
                            <div class="form-group">
                                <label>Jersey Number</label>
                                <input type="number" name="jersey_number" class="form-input" 
                                       value="<?php echo htmlspecialchars($athleteData['jersey_number'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (($userData['position'] ?? '') === 'goalie'): ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Jersey Number</label>
                                <input type="number" name="jersey_number" class="form-input" 
                                       value="<?php echo htmlspecialchars($athleteData['jersey_number'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <!-- Empty to maintain grid layout -->
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Current Team</label>
                                <input type="text" name="team" class="form-input" 
                                       value="<?php echo htmlspecialchars($athleteData['team'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>League</label>
                                <input type="text" name="league" class="form-input" 
                                       value="<?php echo htmlspecialchars($athleteData['league'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" data-action="save">
                                <i class="fas fa-save"></i> Save Player Info
                            </button>
                            <button type="button" class="btn btn-secondary" data-action="cancel">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Security Tab -->
    <div class="tab-content <?php echo $activeTab === 'security' ? 'active' : ''; ?>" id="security-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-lock"></i> Change Password</h3>
            </div>
            <div class="card-body">
                <form class="password-form" id="password-form" method="POST" action="process_profile_update.php" data-form-type="password">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label>Current Password *</label>
                        <input type="password" name="current_password" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label>New Password *</label>
                        <input type="password" name="new_password" class="form-input" required>
                        <small class="form-hint">Minimum 8 characters, include uppercase, lowercase, and numbers</small>
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password *</label>
                        <input type="password" name="confirm_password" class="form-input" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" data-action="change-password">
                            <i class="fas fa-key"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Notifications Tab -->
    <div class="tab-content <?php echo $activeTab === 'notifications' ? 'active' : ''; ?>" id="notifications-tab">
        <div class="card">
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
                            <input type="checkbox" name="email_notifications" checked data-action="toggle-pref">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <h4>Session Reminders</h4>
                            <p>Get reminders before scheduled sessions</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="session_reminders" checked data-action="toggle-pref">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <h4>Goal Updates</h4>
                            <p>Notifications when you achieve milestones</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="goal_updates" checked data-action="toggle-pref">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <h4>Marketing Emails</h4>
                            <p>Receive updates about new features and promotions</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="marketing_emails" data-action="toggle-pref">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tabName) {
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
    
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
}
</script>

<style>
/* Tab Navigation */
.tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    border-bottom: 2px solid var(--border);
    padding-bottom: 0;
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

.profile-photo-section {
    display: flex;
    align-items: center;
    gap: 30px;
    padding: 24px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 24px;
}

.profile-photo {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    color: #fff;
    flex-shrink: 0;
    overflow: hidden;
}

.profile-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.photo-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.form-hint {
    display: block;
    margin-top: 6px;
    font-size: 12px;
    color: var(--text-dim);
    font-style: italic;
}

.preferences-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.preference-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    transition: border-color 0.3s ease;
}

.preference-item:hover {
    border-color: var(--primary);
}

.preference-info {
    flex: 1;
}

.preference-info h4 {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 4px;
}

.preference-info p {
    font-size: 13px;
    color: var(--text-dim);
    margin: 0;
}

/* Toggle Switch */
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

.toggle-switch:hover .toggle-slider {
    opacity: 0.9;
}

@media (max-width: 768px) {
    .tabs {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .profile-photo-section {
        flex-direction: column;
        text-align: center;
    }
    
    .photo-actions {
        width: 100%;
        flex-direction: column;
    }
}
</style>
