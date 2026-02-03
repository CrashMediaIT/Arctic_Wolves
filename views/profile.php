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
    
    // Get additional player data (available for ALL users, not just athletes)
    $playerData = null;
    $stmt = $pdo->prepare("
        SELECT * FROM athlete_stats WHERE user_id = ? ORDER BY season DESC LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $playerData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get user's teams from athlete_teams table
    $userTeams = [];
    $currentTeam = null;
    try {
        // Try to fetch with league and position columns
        $teamsStmt = $pdo->prepare("
            SELECT id, team_name, league, position, season_year, season_type, season, is_current, created_at
            FROM athlete_teams 
            WHERE user_id = ? OR athlete_id = ?
            ORDER BY is_current DESC, created_at DESC
        ");
        $teamsStmt->execute([$user_id, $user_id]);
        $userTeams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Find current team
        foreach ($userTeams as $team) {
            if ($team['is_current']) {
                $currentTeam = $team;
                break;
            }
        }
    } catch (PDOException $teamsError) {
        // Try without position column as fallback
        try {
            $teamsStmt = $pdo->prepare("
                SELECT id, team_name, league, '' as position, season_year, season_type, season, is_current, created_at
                FROM athlete_teams 
                WHERE user_id = ? OR athlete_id = ?
                ORDER BY is_current DESC, created_at DESC
            ");
            $teamsStmt->execute([$user_id, $user_id]);
            $userTeams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $fallbackError) {
            // Try without league column as additional fallback
            try {
                $teamsStmt = $pdo->prepare("
                    SELECT id, team_name, '' as league, '' as position, season_year, season_type, season, is_current, created_at
                    FROM athlete_teams 
                    WHERE user_id = ? OR athlete_id = ?
                    ORDER BY is_current DESC, created_at DESC
                ");
                $teamsStmt->execute([$user_id, $user_id]);
                $userTeams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $finalError) {
                // Table may not exist or have different schema
                error_log("Failed to load user teams from database: " . $finalError->getMessage());
            }
        }
    }
    
    // Get performance stats for current team (based on position)
    $performanceStats = [];
    if ($playerData) {
        $performanceStats = [
            'games_played' => $playerData['games_played'] ?? 0,
            'goals' => $playerData['goals'] ?? 0,
            'assists' => $playerData['assists'] ?? 0,
            'points' => $playerData['points'] ?? ($playerData['goals'] ?? 0) + ($playerData['assists'] ?? 0),
            'plus_minus' => $playerData['plus_minus'] ?? 0,
            'penalty_minutes' => $playerData['penalty_minutes'] ?? 0,
            'shots' => $playerData['shots'] ?? 0,
            // Goalie-specific stats
            'shots_against' => $playerData['shots_against'] ?? 0,
            'goals_against' => $playerData['goals_against'] ?? 0,
            'saves' => $playerData['saves'] ?? 0,
            'save_percentage' => $playerData['save_percentage'] ?? 0,
        ];
    }
    
    // Determine if user is goalie (from current team position or user position)
    $currentPosition = $currentTeam['position'] ?? $userData['position'] ?? '';
    $isGoalie = ($currentPosition === 'goalie');
    
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
        error_log("Failed to load user preferences from database: " . $prefError->getMessage());
    }
} catch (PDOException $e) {
    error_log("Profile data fetch error: " . $e->getMessage());
    $userData = [];
    $playerData = null;
    $userPreferences = [];
    $userTeams = [];
    $currentTeam = null;
    $performanceStats = [];
    $isGoalie = false;
}

$activeTab = $_GET['tab'] ?? 'personal';

// Helper function to check if preference is enabled (defaults: email_notifications=true, session_reminders=true, goal_updates=true, marketing_emails=false)
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
        <button class="profile-tab-btn <?php echo $activeTab === 'player' ? 'active' : ''; ?>" 
                data-tab="player" onclick="switchTab('player')">
            <i class="fas fa-hockey-puck"></i>
            <span>Player Info</span>
        </button>
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

<?php
// Message handling
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';
$messages = [
    'profile_updated' => ['type' => 'success', 'text' => 'Profile updated successfully!'],
    'email_change_pending' => ['type' => 'info', 'text' => 'A confirmation email has been sent to your current email address. Please check your inbox to confirm the email change.'],
    'email_changed' => ['type' => 'success', 'text' => 'Your email address has been successfully changed!'],
    'pass_updated' => ['type' => 'success', 'text' => 'Password changed successfully!'],
    'team_added' => ['type' => 'success', 'text' => 'Team added to your history!'],
    'team_removed' => ['type' => 'success', 'text' => 'Team removed from your history.'],
    'player_info_updated' => ['type' => 'success', 'text' => 'Player information updated!'],
    'stats_updated' => ['type' => 'success', 'text' => 'Performance stats updated successfully!'],
    'photo_uploaded' => ['type' => 'success', 'text' => 'Profile photo updated!'],
    'photo_removed' => ['type' => 'success', 'text' => 'Profile photo removed.'],
];
$errors = [
    'passwords_mismatch' => 'New passwords do not match.',
    'password_too_short' => 'Password must be at least 8 characters.',
    'invalid_current_password' => 'Current password is incorrect.',
    'password_change_failed' => 'Failed to change password. Please try again.',
    'update_failed' => 'Failed to update profile. Please try again.',
    'email_change_failed' => 'Failed to confirm email change. Please try again.',
    'invalid_or_expired_token' => 'The email confirmation link is invalid or has expired.',
    'team_name_required' => 'Team name is required.',
    'team_add_failed' => 'Failed to add team. Please try again.',
    'stats_update_failed' => 'Failed to update performance stats. Please try again.',
];
?>

<?php if ($msg && isset($messages[$msg])): ?>
<div class="alert alert-<?php echo $messages[$msg]['type']; ?>" id="alertMessage">
    <i class="fas <?php echo $messages[$msg]['type'] === 'success' ? 'fa-check-circle' : 'fa-info-circle'; ?>"></i>
    <span><?php echo $messages[$msg]['text']; ?></span>
    <button type="button" onclick="document.getElementById('alertMessage').style.display='none'" class="alert-close">&times;</button>
</div>
<?php endif; ?>

<?php if ($error && isset($errors[$error])): ?>
<div class="alert alert-error" id="alertError">
    <i class="fas fa-exclamation-circle"></i>
    <span><?php echo $errors[$error]; ?></span>
    <button type="button" onclick="document.getElementById('alertError').style.display='none'" class="alert-close">&times;</button>
</div>
<?php endif; ?>

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

    <!-- Player Information Tab (Available for ALL Users) -->
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
                                   value="<?php echo htmlspecialchars($playerData['height'] ?? ''); ?>" 
                                   placeholder="e.g., 72">
                            <small class="form-hint">Enter height in inches (5'10" = 70 inches)</small>
                        </div>
                        <div class="form-group">
                            <label>Weight (lbs)</label>
                            <input type="number" name="weight" class="form-input" 
                                   value="<?php echo htmlspecialchars($playerData['weight'] ?? ''); ?>" 
                                   placeholder="e.g., 180">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Handedness / Shoots</label>
                            <select name="handedness" class="form-select">
                                <option value="">Select</option>
                                <option value="left" <?php echo ($playerData['handedness'] ?? '') === 'left' ? 'selected' : ''; ?>>Left</option>
                                <option value="right" <?php echo ($playerData['handedness'] ?? '') === 'right' ? 'selected' : ''; ?>>Right</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Catching Hand</label>
                            <select name="catching_hand" class="form-select">
                                <option value="">Select</option>
                                <option value="left" <?php echo ($playerData['catching_hand'] ?? '') === 'left' ? 'selected' : ''; ?>>Left</option>
                                <option value="right" <?php echo ($playerData['catching_hand'] ?? '') === 'right' ? 'selected' : ''; ?>>Right</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Jersey Number</label>
                            <input type="number" name="jersey_number" class="form-input" 
                                   value="<?php echo htmlspecialchars($playerData['jersey_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <!-- Empty to maintain grid layout -->
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
        
        <!-- Teams Management Section -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> Team History</h3>
            </div>
            <div class="card-body">
                <!-- Existing Teams Table -->
                <?php if (!empty($userTeams)): ?>
                <div class="teams-table-container" style="margin-bottom: 24px;">
                    <table class="teams-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <th style="padding: 12px; text-align: left; color: var(--text); font-size: 13px;">Team Name</th>
                                <th style="padding: 12px; text-align: left; color: var(--text); font-size: 13px;">Position</th>
                                <th style="padding: 12px; text-align: left; color: var(--text); font-size: 13px;">League</th>
                                <th style="padding: 12px; text-align: left; color: var(--text); font-size: 13px;">Season</th>
                                <th style="padding: 12px; text-align: center; color: var(--text); font-size: 13px;">Current</th>
                                <th style="padding: 12px; text-align: center; color: var(--text); font-size: 13px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userTeams as $team): ?>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <td style="padding: 12px; color: var(--text-primary, #fff);"><?php echo htmlspecialchars($team['team_name'] ?? ''); ?></td>
                                <td style="padding: 12px; color: var(--text);">
                                    <?php 
                                    $positionLabels = [
                                        'forward' => 'Forward',
                                        'defense' => 'Defense', 
                                        'goalie' => 'Goalie',
                                        'left_wing' => 'Left Wing',
                                        'right_wing' => 'Right Wing',
                                        'center' => 'Center',
                                        'left_defense' => 'Left Defense',
                                        'right_defense' => 'Right Defense'
                                    ];
                                    $pos = $team['position'] ?? '';
                                    echo htmlspecialchars($positionLabels[$pos] ?? ($pos ?: '-'));
                                    ?>
                                </td>
                                <td style="padding: 12px; color: var(--text);"><?php echo htmlspecialchars($team['league'] ?? '-'); ?></td>
                                <td style="padding: 12px; color: var(--text);">
                                    <?php 
                                    $season = $team['season'] ?? '';
                                    if (empty($season) && !empty($team['season_type']) && !empty($team['season_year'])) {
                                        $season = $team['season_type'] . ' ' . $team['season_year'];
                                    }
                                    echo htmlspecialchars($season ?: '-');
                                    ?>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <?php if ($team['is_current']): ?>
                                        <span style="background: rgba(16, 185, 129, 0.2); color: #10b981; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                            <i class="fas fa-check"></i> Current
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 12px;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <form method="POST" action="process_profile_update.php" style="display: inline;" class="remove-team-form">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                        <input type="hidden" name="action" value="remove_team">
                                        <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" style="padding: 6px 12px; font-size: 12px;">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 24px; color: var(--text-muted);">
                    <i class="fas fa-users" style="font-size: 32px; margin-bottom: 12px; opacity: 0.5;"></i>
                    <p>No teams added yet. Add your first team below.</p>
                </div>
                <?php endif; ?>
                
                <!-- Add New Team Form -->
                <div class="add-team-section" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
                    <h4 style="margin-bottom: 16px; color: #fff; font-size: 16px;"><i class="fas fa-plus-circle"></i> Add New Team</h4>
                    <form method="POST" action="process_profile_update.php" id="add-team-form">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="action" value="add_team">
                        <div class="form-row" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                            <div class="form-group">
                                <label>Team Name *</label>
                                <input type="text" name="team_name" class="form-input" placeholder="e.g., Arctic Wolves U16" required>
                            </div>
                            <div class="form-group">
                                <label>Position *</label>
                                <select name="team_position" class="form-select" required>
                                    <option value="">Select Position</option>
                                    <optgroup label="Forwards">
                                        <option value="left_wing">Left Wing</option>
                                        <option value="center">Center</option>
                                        <option value="right_wing">Right Wing</option>
                                        <option value="forward">Forward (General)</option>
                                    </optgroup>
                                    <optgroup label="Defense">
                                        <option value="left_defense">Left Defense</option>
                                        <option value="right_defense">Right Defense</option>
                                        <option value="defense">Defense (General)</option>
                                    </optgroup>
                                    <optgroup label="Goalie">
                                        <option value="goalie">Goalie</option>
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                        <div class="form-row" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-top: 16px;">
                            <div class="form-group">
                                <label>League</label>
                                <input type="text" name="league" class="form-input" placeholder="e.g., CSSHL">
                            </div>
                            <div class="form-group">
                                <label>Season Year</label>
                                <input type="text" name="season_year" class="form-input" placeholder="e.g., 2024-2025">
                            </div>
                        </div>
                        <div class="form-row" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-top: 16px;">
                            <div class="form-group">
                                <label>Season Type</label>
                                <select name="season_type" class="form-select">
                                    <option value="">Select Type</option>
                                    <option value="Fall">Fall</option>
                                    <option value="Winter">Winter</option>
                                    <option value="Spring">Spring</option>
                                    <option value="Summer">Summer</option>
                                </select>
                            </div>
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" name="is_current" value="1">
                                    <span>This is my current team</span>
                                </label>
                            </div>
                        </div>
                        <div class="form-actions" style="margin-top: 16px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Team
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Performance Stats Section (Position-based) -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3><i class="fas fa-chart-bar"></i> Performance Stats</h3>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <?php if ($currentTeam): ?>
                    <span class="badge" style="background: rgba(107, 70, 193, 0.15); color: #8B5CF6; padding: 6px 12px; border-radius: 12px; font-size: 12px;">
                        <?php echo htmlspecialchars($currentTeam['team_name']); ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($userTeams)): ?>
                    <button type="button" class="btn btn-secondary btn-sm" id="edit-stats-btn" onclick="toggleStatsEdit()">
                        <i class="fas fa-edit"></i> Edit Stats
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($userTeams)): ?>
                <div style="text-align: center; padding: 24px; color: var(--text-muted);">
                    <i class="fas fa-chart-bar" style="font-size: 32px; margin-bottom: 12px; opacity: 0.5;"></i>
                    <p>Add a team above to track your performance stats.</p>
                </div>
                <?php else: ?>
                <!-- Display Mode -->
                <div id="stats-display-mode">
                    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px;">
                        <?php if ($isGoalie): ?>
                        <!-- Goalie Stats -->
                        <div class="stat-box">
                            <div class="stat-label">Games Played</div>
                            <div class="stat-value"><?php echo $performanceStats['games_played'] ?? 0; ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Goals Against</div>
                            <div class="stat-value"><?php echo $performanceStats['goals_against'] ?? 0; ?></div>
                        </div>
                        <div class="stat-box stat-highlight">
                            <div class="stat-label">GAA</div>
                            <div class="stat-value">
                                <?php 
                                $gamesPlayed = $performanceStats['games_played'] ?? 0;
                                $goalsAgainst = $performanceStats['goals_against'] ?? 0;
                                // Calculate Goals Against Average (goals against per game)
                                $gaa = 0;
                                if ($gamesPlayed > 0) {
                                    $gaa = $goalsAgainst / $gamesPlayed;
                                }
                                echo number_format($gaa, 2);
                                ?>
                            </div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Saves</div>
                            <div class="stat-value"><?php echo $performanceStats['saves'] ?? 0; ?></div>
                        </div>
                        <div class="stat-box stat-highlight">
                            <div class="stat-label">Save %</div>
                            <div class="stat-value">
                                <?php 
                                $displayShotsAgainst = $performanceStats['shots_against'] ?? 0;
                                $displaySaves = $performanceStats['saves'] ?? 0;
                                // Calculate save percentage using saves value directly
                                $displaySavePercentage = 0;
                                if ($displayShotsAgainst > 0) {
                                    $displaySavePercentage = max(0, min(100, ($displaySaves / $displayShotsAgainst * 100)));
                                }
                                echo number_format($displaySavePercentage, 1) . '%';
                                ?>
                            </div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Shots Against</div>
                            <div class="stat-value"><?php echo $performanceStats['shots_against'] ?? 0; ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Goals</div>
                            <div class="stat-value"><?php echo $performanceStats['goals'] ?? 0; ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Assists</div>
                            <div class="stat-value"><?php echo $performanceStats['assists'] ?? 0; ?></div>
                        </div>
                        <?php else: ?>
                        <!-- Player Stats (Forward/Defense) -->
                        <div class="stat-box">
                            <div class="stat-label">Games Played</div>
                            <div class="stat-value"><?php echo $performanceStats['games_played'] ?? 0; ?></div>
                        </div>
                        <div class="stat-box stat-highlight">
                            <div class="stat-label">Goals</div>
                            <div class="stat-value"><?php echo $performanceStats['goals'] ?? 0; ?></div>
                        </div>
                        <div class="stat-box stat-highlight">
                            <div class="stat-label">Assists</div>
                            <div class="stat-value"><?php echo $performanceStats['assists'] ?? 0; ?></div>
                        </div>
                        <div class="stat-box stat-highlight">
                            <div class="stat-label">Points</div>
                            <div class="stat-value"><?php echo ($performanceStats['goals'] ?? 0) + ($performanceStats['assists'] ?? 0); ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">+/-</div>
                            <div class="stat-value <?php echo ($performanceStats['plus_minus'] ?? 0) >= 0 ? 'stat-positive' : 'stat-negative'; ?>">
                                <?php echo ($performanceStats['plus_minus'] ?? 0) >= 0 ? '+' : ''; ?><?php echo $performanceStats['plus_minus'] ?? 0; ?>
                            </div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Shots</div>
                            <div class="stat-value"><?php echo $performanceStats['shots'] ?? 0; ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">PIM</div>
                            <div class="stat-value"><?php echo $performanceStats['penalty_minutes'] ?? 0; ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Edit Mode (hidden by default) -->
                <div id="stats-edit-mode" style="display: none;">
                    <form method="POST" action="process_profile_update.php" id="stats-form">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="action" value="update_performance_stats">
                        
                        <?php if ($isGoalie): ?>
                        <!-- Goalie Stats Edit -->
                        <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px;">
                            <div class="form-group">
                                <label>Games Played</label>
                                <input type="number" name="games_played" class="form-input" min="0" max="9999"
                                       value="<?php echo htmlspecialchars($performanceStats['games_played'] ?? 0); ?>">
                            </div>
                            <div class="form-group">
                                <label>Goals Against</label>
                                <input type="number" name="goals_against" class="form-input" min="0" max="9999"
                                       value="<?php echo htmlspecialchars($performanceStats['goals_against'] ?? 0); ?>">
                            </div>
                            <div class="form-group">
                                <label>Shots Against</label>
                                <input type="number" name="shots_against" class="form-input" min="0" max="9999"
                                       value="<?php echo htmlspecialchars($performanceStats['shots_against'] ?? 0); ?>">
                            </div>
                            <div class="form-group">
                                <label>Saves</label>
                                <input type="number" name="saves" class="form-input" min="0" max="9999"
                                       value="<?php echo htmlspecialchars($performanceStats['saves'] ?? 0); ?>">
                            </div>
                            <div class="form-group">
                                <label>Goals</label>
                                <input type="number" name="goals" class="form-input" min="0" max="9999"
                                       value="<?php echo htmlspecialchars($performanceStats['goals'] ?? 0); ?>">
                            </div>
                            <div class="form-group">
                                <label>Assists</label>
                                <input type="number" name="assists" class="form-input" min="0" max="9999"
                                       value="<?php echo htmlspecialchars($performanceStats['assists'] ?? 0); ?>">
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- Player Stats Edit -->
                        <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px;">
                            <div class="form-group">
                                <label>Games Played</label>
                                <input type="number" name="games_played" class="form-input" min="0" max="9999"
                                       value="<?php echo htmlspecialchars($performanceStats['games_played'] ?? 0); ?>">
                            </div>
                            <div class="form-group">
                                <label>Goals</label>
                                <input type="number" name="goals" class="form-input" min="0" max="9999"
                                       value="<?php echo htmlspecialchars($performanceStats['goals'] ?? 0); ?>">
                            </div>
                            <div class="form-group">
                                <label>Assists</label>
                                <input type="number" name="assists" class="form-input" min="0" max="9999"
                                       value="<?php echo htmlspecialchars($performanceStats['assists'] ?? 0); ?>">
                            </div>
                            <div class="form-group">
                                <label>+/-</label>
                                <input type="number" name="plus_minus" class="form-input" min="-999" max="999"
                                       value="<?php echo htmlspecialchars($performanceStats['plus_minus'] ?? 0); ?>">
                            </div>
                            <div class="form-group">
                                <label>Shots</label>
                                <input type="number" name="shots" class="form-input" min="0" max="9999"
                                       value="<?php echo htmlspecialchars($performanceStats['shots'] ?? 0); ?>">
                            </div>
                            <div class="form-group">
                                <label>Penalty Minutes (PIM)</label>
                                <input type="number" name="penalty_minutes" class="form-input" min="0" max="9999"
                                       value="<?php echo htmlspecialchars($performanceStats['penalty_minutes'] ?? 0); ?>">
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-actions" style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Stats
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="toggleStatsEdit()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <p style="color: var(--text-muted); font-size: 12px; margin-top: 16px; text-align: center;">
                    <i class="fas fa-info-circle"></i> Stats are shown based on your current team position. 
                    <?php if ($isGoalie): ?>Goalie stats displayed. GAA = Goals Against / Games Played.<?php else: ?>Player stats displayed.<?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

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
                        <input type="password" name="new_password" class="form-input" minlength="8" required>
                        <small class="form-hint">Minimum 8 characters</small>
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password *</label>
                        <input type="password" name="confirm_password" class="form-input" minlength="8" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" data-action="change-password">
                            <i class="fas fa-key"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- PIN Management Section -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <h3><i class="fas fa-th"></i> PIN Settings</h3>
            </div>
            <div class="card-body">
                <p style="color: var(--text-dim); margin-bottom: 20px;">
                    <i class="fas fa-info-circle"></i> Your PIN is used for quick login to the POS system and time tracking kiosk.
                </p>
                <form id="pin-form">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" name="action" value="update_pin">
                    
                    <div class="form-group">
                        <label>New PIN (4 digits) *</label>
                        <input type="password" name="new_pin" class="form-input" required pattern="\d{4}" maxlength="4" inputmode="numeric" placeholder="••••" autocomplete="off">
                        <small class="form-hint">Must be exactly 4 digits</small>
                    </div>

                    <div class="form-group">
                        <label>Confirm PIN *</label>
                        <input type="password" name="confirm_pin" class="form-input" required pattern="\d{4}" maxlength="4" inputmode="numeric" placeholder="••••" autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label>Current Password *</label>
                        <input type="password" name="current_password" class="form-input" required>
                        <small class="form-hint">Required to verify your identity</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="pin-submit-btn">
                            <i class="fas fa-fingerprint"></i> Update PIN
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Notifications Tab -->
    <div class="tab-content <?php echo $activeTab === 'notifications' ? 'active' : ''; ?>" id="notifications-tab">
        <!-- Hidden CSRF token for AJAX requests -->
        <input type="hidden" name="csrf_token" id="notifications-csrf-token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
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

// Toggle stats edit mode
function toggleStatsEdit() {
    const displayMode = document.getElementById('stats-display-mode');
    const editMode = document.getElementById('stats-edit-mode');
    const editBtn = document.getElementById('edit-stats-btn');
    const statsForm = document.getElementById('stats-form');
    
    if (displayMode && editMode && editBtn) {
        if (editMode.style.display === 'none') {
            displayMode.style.display = 'none';
            editMode.style.display = 'block';
            editBtn.innerHTML = '<i class="fas fa-times"></i> Cancel';
        } else {
            // Reset form to original values when canceling
            if (statsForm) {
                statsForm.reset();
            }
            displayMode.style.display = 'block';
            editMode.style.display = 'none';
            editBtn.innerHTML = '<i class="fas fa-edit"></i> Edit Stats';
        }
    }
}

// Handle notification preference toggles
document.addEventListener('DOMContentLoaded', function() {
    // Handle remove team form submissions
    document.querySelectorAll('.remove-team-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm('Remove this team from your history?')) {
                e.preventDefault();
            }
        });
    });

    // Handle notification preference toggles
    const toggles = document.querySelectorAll('[data-action="toggle-pref"]');
    console.log('Found', toggles.length, 'notification toggles');
    
    toggles.forEach(toggle => {
        // Add click event as well as change event for better compatibility
        toggle.addEventListener('click', function(e) {
            console.log('Toggle clicked:', this.name);
        });
        
        toggle.addEventListener('change', function(e) {
            e.stopPropagation();
            const prefName = this.name;
            const prefValue = this.checked ? 1 : 0;
            // Try to get CSRF token from multiple sources (notifications tab has its own hidden input)
            const csrfToken = document.getElementById('notifications-csrf-token')?.value || document.querySelector('input[name="csrf_token"]')?.value;
            const toggleElement = this;
            const parentItem = this.closest('.preference-item');
            
            console.log('Saving preference:', prefName, '=', prefValue);
            
            // Add saving indicator
            if (parentItem) {
                parentItem.style.opacity = '0.7';
            }
            
            // Send AJAX request to save preference
            fetch('process_profile_update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_preference&preference=${prefName}&value=${prefValue}&csrf_token=${encodeURIComponent(csrfToken || '')}`
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (parentItem) {
                    parentItem.style.opacity = '1';
                }
                if (data.success) {
                    // Show brief success indicator
                    if (parentItem) {
                        const originalBorder = parentItem.style.borderColor;
                        parentItem.style.borderColor = '#10b981';
                        setTimeout(() => {
                            parentItem.style.borderColor = originalBorder;
                        }, 1000);
                    }
                    console.log('Preference saved successfully:', prefName, prefValue);
                } else {
                    // Revert the toggle if save failed
                    toggleElement.checked = !toggleElement.checked;
                    alert('Failed to save preference: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                if (parentItem) {
                    parentItem.style.opacity = '1';
                }
                console.error('Error saving preference:', error);
                // Revert the toggle on error
                toggleElement.checked = !toggleElement.checked;
                alert('Error saving preference. Please try again.');
            });
        });
    });

    // Handle PIN form submission
    const pinForm = document.getElementById('pin-form');
    if (pinForm) {
        pinForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = document.getElementById('pin-submit-btn');
            const originalBtnText = submitBtn.innerHTML;
            
            // Helper function for PIN notifications
            function showPinNotification(message, type) {
                var existing = document.querySelector('.pin-notification');
                if (existing) existing.remove();
                
                var div = document.createElement('div');
                div.className = 'pin-notification';
                div.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; padding: 16px 24px; border-radius: 8px; display: flex; align-items: center; gap: 12px; font-family: Inter, sans-serif; font-size: 14px;';
                div.style.background = type === 'success' ? 'rgba(16, 185, 129, 0.95)' : 'rgba(239, 68, 68, 0.95)';
                div.style.color = '#fff';
                
                var icon = document.createElement('i');
                icon.className = 'fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle');
                div.appendChild(icon);
                
                var text = document.createElement('span');
                text.textContent = message;
                div.appendChild(text);
                
                var closeBtn = document.createElement('button');
                closeBtn.style.cssText = 'margin-left: 16px; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;';
                closeBtn.innerHTML = '&times;';
                closeBtn.onclick = function() { div.remove(); };
                div.appendChild(closeBtn);
                
                document.body.appendChild(div);
                setTimeout(function() { if (div.parentElement) div.remove(); }, 5000);
            }
            
            // Validate PINs match
            if (formData.get('new_pin') !== formData.get('confirm_pin')) {
                showPinNotification('PINs do not match', 'error');
                return;
            }
            
            // Validate PIN format
            if (!/^\d{4}$/.test(formData.get('new_pin'))) {
                showPinNotification('PIN must be exactly 4 digits', 'error');
                return;
            }
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            submitBtn.disabled = true;
            
            fetch('process_profile_update.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                
                if (data.success) {
                    showPinNotification('PIN updated successfully!', 'success');
                    pinForm.reset();
                } else {
                    showPinNotification('Error: ' + (data.message || 'Failed to update PIN'), 'error');
                }
            })
            .catch(error => {
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                console.error('Error:', error);
                showPinNotification('An error occurred. Please try again.', 'error');
            });
        });
    }
});
</script>

<style>
/* Profile Page Enhanced Styles */

.profile-content {
    max-height: calc(100vh - 350px);
    overflow-y: auto;
    padding-right: 8px;
}

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
    margin-bottom: -1px;
}

.profile-tabs {
    display: flex;
    gap: 0;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px 12px 0 0;
    overflow-x: auto;
}

.profile-tab-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 18px 24px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-dim);
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.profile-tab-btn:hover {
    background: rgba(139, 92, 246, 0.05);
    color: var(--text-white);
}

.profile-tab-btn.active {
    background: rgba(139, 92, 246, 0.1);
    color: var(--primary);
    border-bottom-color: var(--primary);
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

/* Toggle Switch - Enhanced with higher specificity for profile page */
.profile-content .toggle-switch,
.preferences-list .toggle-switch {
    position: relative;
    display: inline-block;
    width: 56px;
    height: 30px;
    flex-shrink: 0;
    cursor: pointer;
}

.profile-content .toggle-switch input,
.preferences-list .toggle-switch input {
    opacity: 0;
    width: 100%;
    height: 100%;
    position: absolute;
    top: 0;
    left: 0;
    cursor: pointer;
    z-index: 2;
    margin: 0;
}

.profile-content .toggle-slider,
.preferences-list .toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--border, #2D2D3F);
    transition: 0.3s;
    border-radius: 30px;
    z-index: 1;
}

.profile-content .toggle-slider:before,
.preferences-list .toggle-slider:before {
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
    transform: translateX(0);
}

.profile-content .toggle-switch input:checked + .toggle-slider,
.preferences-list .toggle-switch input:checked + .toggle-slider {
    background: linear-gradient(135deg, var(--primary, #6B46C1), var(--primary-hover, #7C3AED));
}

.profile-content .toggle-switch input:checked + .toggle-slider:before,
.preferences-list .toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(26px);
}

.profile-content .toggle-switch:hover .toggle-slider,
.preferences-list .toggle-switch:hover .toggle-slider {
    box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.15);
}

/* Override hover states to not interfere with toggle visual */
.profile-content .toggle-slider:hover,
.preferences-list .toggle-slider:hover {
    background-color: var(--border-light, #3A3A4F);
}

.profile-content .toggle-switch input:checked + .toggle-slider:hover,
.preferences-list .toggle-switch input:checked + .toggle-slider:hover {
    background: linear-gradient(135deg, var(--primary-hover, #7C3AED), var(--primary-light, #8B5CF6));
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

/* Alert Messages */
.alert {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(16, 185, 129, 0.25));
    border: 1px solid rgba(16, 185, 129, 0.5);
    color: #10b981;
}

.alert-info {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(59, 130, 246, 0.25));
    border: 1px solid rgba(59, 130, 246, 0.5);
    color: #3b82f6;
}

.alert-error {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(239, 68, 68, 0.25));
    border: 1px solid rgba(239, 68, 68, 0.5);
    color: #ef4444;
}

.alert i {
    font-size: 20px;
}

.alert span {
    flex: 1;
    font-weight: 500;
}

.alert-close {
    background: none;
    border: none;
    color: inherit;
    font-size: 20px;
    cursor: pointer;
    opacity: 0.7;
    transition: opacity 0.2s;
    padding: 0;
    line-height: 1;
}

.alert-close:hover {
    opacity: 1;
}

/* Performance Stats Section */
.stat-box {
    background: linear-gradient(135deg, rgba(107, 70, 193, 0.08) 0%, transparent 100%);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
}

.stat-box:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
}

.stat-box.stat-highlight {
    background: linear-gradient(135deg, rgba(107, 70, 193, 0.15) 0%, rgba(139, 92, 246, 0.08) 100%);
    border-color: rgba(107, 70, 193, 0.3);
}

.stat-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-bottom: 8px;
}

.stat-value {
    font-size: 28px;
    font-weight: 800;
    color: var(--text-white);
}

.stat-value.stat-positive {
    color: #10b981;
}

.stat-value.stat-negative {
    color: #ef4444;
}
</style>
