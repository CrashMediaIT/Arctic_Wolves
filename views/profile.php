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
    
    // Get user preferences for notifications
    $userPreferences = [];
    try {
        $prefStmt = $pdo->prepare("
            SELECT preference_key, preference_value 
            FROM user_preferences 
            WHERE user_id = ?
        ");
        $prefStmt->execute([$user_id]);
        while ($row = $prefStmt->fetch(PDO::FETCH_ASSOC)) {
            $userPreferences[$row['preference_key']] = $row['preference_value'];
        }
    } catch (PDOException $prefError) {
        // Table may not exist yet - use defaults
        error_log("User preferences fetch error: " . $prefError->getMessage());
    }
} catch (PDOException $e) {
    error_log("Profile data fetch error: " . $e->getMessage());
    $userData = [];
    $athleteData = null;
    $userPreferences = [];
}

$activeTab = $_GET['tab'] ?? 'personal';

// Helper function to check if preference is enabled (default to true for email_notifications, session_reminders, goal_updates)
function isPreferenceEnabled($preferences, $key) {
    $defaults = [
        'email_notifications' => 1,
        'session_reminders' => 1,
        'goal_updates' => 1,
        'marketing_emails' => 0
    ];
    if (isset($preferences[$key])) {
        return (int)$preferences[$key] === 1;
    }
    return isset($defaults[$key]) ? (bool)$defaults[$key] : false;
}
?>

<div class="profile-page-header">
    <div class="profile-header-content">
        <div class="profile-header-avatar">
            <?php if (!empty($userData['profile_image'])): ?>
                <img src="<?php echo htmlspecialchars($userData['profile_image']); ?>" alt="Profile">
            <?php else: ?>
                <span class="avatar-initials"><?php echo strtoupper(substr($userData['first_name'] ?? 'U', 0, 1) . substr($userData['last_name'] ?? 'N', 0, 1)); ?></span>
            <?php endif; ?>
            <div class="avatar-badge">
                <i class="fas fa-check"></i>
            </div>
        </div>
        <div class="profile-header-info">
            <h1 class="profile-name"><?php echo htmlspecialchars(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')); ?></h1>
            <p class="profile-role">
                <span class="role-badge <?php echo $user_role; ?>"><?php echo ucfirst(str_replace('_', ' ', $user_role)); ?></span>
                <span class="profile-email"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($userData['email'] ?? ''); ?></span>
            </p>
        </div>
    </div>
</div>

<!-- Tab Navigation - Enhanced -->
<div class="profile-tabs-wrapper">
    <div class="profile-tabs">
        <button class="profile-tab-btn <?php echo $activeTab === 'personal' ? 'active' : ''; ?>" 
                data-tab="personal" onclick="switchTab('personal')">
            <i class="fas fa-id-card"></i>
            <span>Personal Info</span>
        </button>
        <?php if ($user_role === 'athlete'): ?>
            <button class="profile-tab-btn <?php echo $activeTab === 'player' ? 'active' : ''; ?>" 
                    data-tab="player" onclick="switchTab('player')">
                <i class="fas fa-hockey-puck"></i>
                <span>Player Info</span>
            </button>
        <?php endif; ?>
        <button class="profile-tab-btn <?php echo $activeTab === 'security' ? 'active' : ''; ?>" 
                data-tab="security" onclick="switchTab('security')">
            <i class="fas fa-lock"></i>
            <span>Security</span>
        </button>
        <button class="profile-tab-btn <?php echo $activeTab === 'notifications' ? 'active' : ''; ?>" 
                data-tab="notifications" onclick="switchTab('notifications')">
            <i class="fas fa-bell"></i>
            <span>Notifications</span>
        </button>
    </div>
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
                    <div class="profile-photo" onclick="document.getElementById('profilePhotoInput').click()" style="cursor: pointer;" title="Click to change profile photo">
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
                            <input type="checkbox" name="email_notifications" <?php echo isPreferenceEnabled($userPreferences, 'email_notifications') ? 'checked' : ''; ?> data-action="toggle-pref">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <h4>Session Reminders</h4>
                            <p>Get reminders before scheduled sessions</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="session_reminders" <?php echo isPreferenceEnabled($userPreferences, 'session_reminders') ? 'checked' : ''; ?> data-action="toggle-pref">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <h4>Goal Updates</h4>
                            <p>Notifications when you achieve milestones</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="goal_updates" <?php echo isPreferenceEnabled($userPreferences, 'goal_updates') ? 'checked' : ''; ?> data-action="toggle-pref">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <h4>Marketing Emails</h4>
                            <p>Receive updates about new features and promotions</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="marketing_emails" <?php echo isPreferenceEnabled($userPreferences, 'marketing_emails') ? 'checked' : ''; ?> data-action="toggle-pref">
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
    // Fix: Use correct selector for profile tab buttons
    document.querySelectorAll('.profile-tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
}

// Handle notification preference toggles
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-action="toggle-pref"]').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const prefName = this.name;
            const prefValue = this.checked ? 1 : 0;
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
            
            // Send AJAX request to save preference
            fetch('process_profile_update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_preference&preference=${prefName}&value=${prefValue}&csrf_token=${encodeURIComponent(csrfToken)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Optionally show a toast notification
                    console.log('Preference saved:', prefName, prefValue);
                } else {
                    // Revert the toggle if save failed
                    this.checked = !this.checked;
                    alert('Failed to save preference: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error saving preference:', error);
                // Revert the toggle on error
                this.checked = !this.checked;
            });
        });
    });
});
</script>

<style>
/* Profile Page Enhanced Styles */
.profile-page-header {
    background: linear-gradient(135deg, rgba(107, 70, 193, 0.15) 0%, rgba(139, 92, 246, 0.08) 100%);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 32px;
    margin-bottom: 24px;
}

.profile-header-content {
    display: flex;
    align-items: center;
    gap: 24px;
}

.profile-header-avatar {
    position: relative;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    color: #fff;
    font-weight: 700;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(107, 70, 193, 0.3);
    flex-shrink: 0;
}

.profile-header-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-header-avatar .avatar-initials {
    font-size: 36px;
    font-weight: 700;
}

.avatar-badge {
    position: absolute;
    bottom: 4px;
    right: 4px;
    width: 28px;
    height: 28px;
    background: var(--success);
    border: 3px solid var(--bg-card);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: #fff;
}

.profile-header-info {
    flex: 1;
}

.profile-name {
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 8px 0;
    letter-spacing: -0.5px;
}

.profile-role {
    display: flex;
    align-items: center;
    gap: 16px;
    margin: 0;
    flex-wrap: wrap;
}

.profile-role .role-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.profile-role .role-badge.admin {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.profile-role .role-badge.coach,
.profile-role .role-badge.team_coach,
.profile-role .role-badge.health_coach {
    background: rgba(59, 130, 246, 0.15);
    color: #3B82F6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.profile-role .role-badge.athlete {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.profile-role .role-badge.parent {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.profile-email {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-secondary);
    font-size: 14px;
}

.profile-email i {
    color: var(--primary-light);
}

/* Profile Tabs - Enhanced */
.profile-tabs-wrapper {
    margin-bottom: 24px;
}

.profile-tabs {
    display: flex;
    gap: 8px;
    padding: 6px;
    background: var(--bg-card);
    border-radius: 14px;
    border: 1px solid var(--border);
    overflow-x: auto;
}

.profile-tab-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 24px;
    background: transparent;
    border: none;
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.profile-tab-btn:hover {
    background: rgba(107, 70, 193, 0.1);
    color: var(--text-primary);
}

.profile-tab-btn.active {
    background: var(--primary);
    color: #fff;
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.3);
}

.profile-tab-btn i {
    font-size: 16px;
}

/* Tab Navigation - Legacy Override */
.tabs {
    display: none !important;
}

/* Tab Content */
.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Profile Photo Section - Enhanced */
.profile-photo-section {
    display: flex;
    align-items: center;
    gap: 30px;
    padding: 28px;
    background: linear-gradient(135deg, rgba(107, 70, 193, 0.08) 0%, transparent 100%);
    border: 1px solid var(--border);
    border-radius: 16px;
    margin-bottom: 28px;
}

.profile-photo {
    width: 130px;
    height: 130px;
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 52px;
    color: #fff;
    flex-shrink: 0;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(107, 70, 193, 0.3);
    transition: transform 0.3s ease;
    cursor: pointer;
}

.profile-photo:hover {
    transform: scale(1.05);
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

/* Form Enhancements */
.profile-form .form-row,
.player-form .form-row,
.password-form .form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 0;
}

.profile-form .form-group,
.player-form .form-group,
.password-form .form-group {
    margin-bottom: 24px;
}

.profile-form label,
.player-form label,
.password-form label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 10px;
    display: block;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-hint {
    display: block;
    margin-top: 8px;
    font-size: 12px;
    color: var(--text-muted);
    font-style: normal;
}

/* Preferences List - Enhanced */
.preferences-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.preference-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px;
    background: linear-gradient(135deg, rgba(107, 70, 193, 0.05) 0%, transparent 100%);
    border: 1px solid var(--border);
    border-radius: 14px;
    transition: all 0.3s ease;
}

.preference-item:hover {
    border-color: var(--primary);
    transform: translateX(4px);
}

.preference-info {
    flex: 1;
}

.preference-info h4 {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 6px;
}

.preference-info p {
    font-size: 13px;
    color: var(--text-muted);
    margin: 0;
}

/* Toggle Switch - Enhanced */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 56px;
    height: 30px;
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
    border-radius: 30px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 24px;
    width: 24px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}

.toggle-switch input:checked + .toggle-slider {
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(26px);
}

.toggle-switch:hover .toggle-slider {
    box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.15);
}

/* Card Enhancements */
.profile-content .card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
}

.profile-content .card-header {
    padding: 24px;
    background: linear-gradient(180deg, rgba(107, 70, 193, 0.08) 0%, transparent 100%);
    border-bottom: 1px solid var(--border);
}

.profile-content .card-header h3 {
    font-size: 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
}

.profile-content .card-header h3 i {
    color: var(--primary-light);
}

.profile-content .card-body {
    padding: 28px;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 28px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
}

@media (max-width: 768px) {
    .profile-header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-role {
        justify-content: center;
    }
    
    .profile-tabs {
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .profile-tab-btn span {
        display: none;
    }
    
    .profile-tab-btn {
        padding: 14px;
    }
    
    .profile-photo-section {
        flex-direction: column;
        text-align: center;
    }
    
    .photo-actions {
        width: 100%;
        flex-direction: column;
    }
    
    .profile-form .form-row,
    .player-form .form-row {
        grid-template-columns: 1fr;
    }
}
</style>
