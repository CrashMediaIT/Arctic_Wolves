<!-- Admin Users Management View -->
<?php
// Fetch users from database
try {
    // Get filter values
    $role_filter = $_GET['role'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $team_filter = $_GET['team'] ?? '';
    $age_filter = $_GET['age'] ?? '';
    
    // Fetch teams for filter dropdown
    $teams_stmt = $pdo->query("SELECT id, name FROM teams WHERE is_active = 1 ORDER BY name");
    $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch coaches for assignment dropdown (includes admin, coach, team_coach, health_coach)
    $coaches_stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name, role FROM users WHERE role IN ('admin', 'coach', 'team_coach', 'health_coach') AND is_verified = 1 ORDER BY first_name");
    $coaches = $coaches_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch existing athlete-coach assignments (for multiple coach support)
    $athlete_coaches_map = [];
    try {
        $ac_stmt = $pdo->query("SELECT athlete_id, coach_id FROM athlete_coaches WHERE status = 'active'");
        while ($row = $ac_stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($athlete_coaches_map[$row['athlete_id']])) {
                $athlete_coaches_map[$row['athlete_id']] = [];
            }
            $athlete_coaches_map[$row['athlete_id']][] = $row['coach_id'];
        }
    } catch (PDOException $e) {
        // Table may not exist yet, silently continue
        $athlete_coaches_map = [];
    }
    
    // Build query
    $where = [];
    $params = [];
    $join_clauses = "";
    
    if (!empty($role_filter)) {
        $where[] = "u.role = ?";
        $params[] = $role_filter;
    }
    
    if (!empty($status_filter)) {
        if ($status_filter === 'active') {
            $where[] = "u.is_verified = 1";
        } elseif ($status_filter === 'inactive') {
            $where[] = "u.is_verified = 0";
        }
    }
    
    // Enhanced search - includes phone number
    if (!empty($search)) {
        $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Team filter
    if (!empty($team_filter)) {
        $join_clauses .= " LEFT JOIN team_roster tr ON u.id = tr.athlete_id";
        $where[] = "tr.team_id = ?";
        $params[] = $team_filter;
    }
    
    // Age filter (calculate age from birth_date)
    if (!empty($age_filter)) {
        switch ($age_filter) {
            case 'u10':
                $where[] = "TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) < 10";
                break;
            case 'u12':
                $where[] = "TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) >= 10 AND TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) < 12";
                break;
            case 'u14':
                $where[] = "TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) >= 12 AND TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) < 14";
                break;
            case 'u16':
                $where[] = "TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) >= 14 AND TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) < 16";
                break;
            case 'u18':
                $where[] = "TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) >= 16 AND TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) < 18";
                break;
            case '18plus':
                $where[] = "TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) >= 18";
                break;
        }
    }
    
    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $stmt = $pdo->prepare("
        SELECT u.*, 
               CONCAT(u.first_name, ' ', u.last_name) as full_name,
               COUNT(DISTINCT s.id) as session_count,
               coach.first_name as coach_first_name,
               coach.last_name as coach_last_name,
               t.name as team_name,
               (SELECT lh.login_time FROM login_history lh WHERE lh.user_id = u.id AND lh.login_status = 'success' ORDER BY lh.login_time DESC LIMIT 1) as last_login
        FROM users u
        LEFT JOIN sessions s ON u.id = s.coach_id
        LEFT JOIN users coach ON u.assigned_coach_id = coach.id
        LEFT JOIN team_roster tr2 ON u.id = tr2.athlete_id
        LEFT JOIN teams t ON tr2.team_id = t.id
        $join_clauses
        $where_clause
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_users = count($users);
} catch (PDOException $e) {
    error_log("Users fetch error: " . $e->getMessage());
    $users = [];
    $total_users = 0;
    $teams = [];
    $coaches = [];
}

// Count by role for stats
$admin_count = 0;
$coach_count = 0;
$athlete_count = 0;
$parent_count = 0;
foreach ($users as $u) {
    switch ($u['role']) {
        case 'admin': $admin_count++; break;
        case 'coach': case 'team_coach': case 'health_coach': $coach_count++; break;
        case 'athlete': $athlete_count++; break;
        case 'parent': $parent_count++; break;
    }
}
?>

<?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
<div class="success-alert" style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-check-circle" style="color: #10b981; font-size: 20px;"></i>
    <span style="color: #10b981; font-weight: 600;">User updated successfully!</span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #10b981; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>

<?php if (isset($_GET['status']) && $_GET['status'] === 'error'): ?>
<div class="error-alert" style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-exclamation-circle" style="color: #ef4444; font-size: 20px;"></i>
    <span style="color: #ef4444; font-weight: 600;">
    <?php
    $error_messages = [
        'required_fields' => 'Please fill in all required fields.',
        'invalid_role' => 'Invalid role selected.'
    ];
    echo $error_messages[$_GET['msg'] ?? ''] ?? 'An error occurred. Please try again.';
    ?>
    </span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #ef4444; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-users-cog"></i> User Management</h1>
        <p class="page-description">Manage all system users, roles, and permissions</p>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value"><?php echo $total_users; ?></span>
            <span class="stat-label">Total Users</span>
        </div>
        <div class="header-stat">
            <span class="stat-value"><?php echo $coach_count; ?></span>
            <span class="stat-label">Coaches</span>
        </div>
        <div class="header-stat">
            <span class="stat-value"><?php echo $athlete_count; ?></span>
            <span class="stat-label">Athletes</span>
        </div>
    </div>
</div>

<div class="users-content">
    <!-- Filter and Actions -->
    <div class="action-bar-enhanced">
        <form method="GET" action="" class="filter-form-enhanced">
            <input type="hidden" name="page" value="all_users">
            <div class="search-input-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" name="search" class="form-input" placeholder="Search by name, email, or phone..." 
                       value="<?php echo htmlspecialchars($search); ?>" id="userSearch">
            </div>
            <select name="role" class="form-select" id="roleFilter">
                <option value="">All Roles</option>
                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="coach" <?php echo $role_filter === 'coach' ? 'selected' : ''; ?>>Coach</option>
                <option value="health_coach" <?php echo $role_filter === 'health_coach' ? 'selected' : ''; ?>>Health Coach</option>
                <option value="team_coach" <?php echo $role_filter === 'team_coach' ? 'selected' : ''; ?>>Team Coach</option>
                <option value="athlete" <?php echo $role_filter === 'athlete' ? 'selected' : ''; ?>>Athlete</option>
                <option value="parent" <?php echo $role_filter === 'parent' ? 'selected' : ''; ?>>Parent</option>
            </select>
            <select name="status" class="form-select" id="statusFilter">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <select name="team" class="form-select" id="teamFilter">
                <option value="">All Teams</option>
                <?php foreach ($teams as $team): ?>
                    <option value="<?php echo $team['id']; ?>" <?php echo $team_filter == $team['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($team['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="age" class="form-select" id="ageFilter">
                <option value="">All Ages</option>
                <option value="u10" <?php echo $age_filter === 'u10' ? 'selected' : ''; ?>>Under 10</option>
                <option value="u12" <?php echo $age_filter === 'u12' ? 'selected' : ''; ?>>U12 (10-11)</option>
                <option value="u14" <?php echo $age_filter === 'u14' ? 'selected' : ''; ?>>U14 (12-13)</option>
                <option value="u16" <?php echo $age_filter === 'u16' ? 'selected' : ''; ?>>U16 (14-15)</option>
                <option value="u18" <?php echo $age_filter === 'u18' ? 'selected' : ''; ?>>U18 (16-17)</option>
                <option value="18plus" <?php echo $age_filter === '18plus' ? 'selected' : ''; ?>>18+</option>
            </select>
            <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filter</button>
        </form>
        <div class="action-buttons">
            <form method="POST" action="process_admin_action.php" style="display: inline;" id="exportForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="action" value="export_users">
                <input type="hidden" name="filter_role" value="<?php echo htmlspecialchars($role_filter); ?>">
                <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <input type="hidden" name="filter_team" value="<?php echo htmlspecialchars($team_filter); ?>">
                <input type="hidden" name="filter_age" value="<?php echo htmlspecialchars($age_filter); ?>">
                <input type="hidden" name="filter_search" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-file-export"></i> Export
                </button>
            </form>
            <button class="btn btn-primary" data-action="add" data-modal="add-user-modal">
                <i class="fas fa-user-plus"></i> Add User
            </button>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card users-card">
        <div class="card-header">
            <h3><i class="fas fa-users"></i> All Users</h3>
            <span class="users-count-badge"><?php echo $total_users; ?> users</span>
        </div>
        <div class="card-body">
            <?php if (count($users) > 0): ?>
                <div class="table-wrapper">
                    <table class="data-table enhanced-table" id="users-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Contact</th>
                                <th>Coach/Team</th>
                                <th>Joined</th>
                                <th>Last Login</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <?php 
                                            // Validate profile image path
                                            $profile_img = $user['profile_image'] ?? '';
                                            $is_valid_image = !empty($profile_img) && 
                                                              strpos($profile_img, 'uploads/profiles/') === 0 && 
                                                              preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $profile_img) && 
                                                              file_exists($profile_img);
                                            ?>
                                            <?php if ($is_valid_image): ?>
                                                <img src="<?php echo htmlspecialchars($profile_img); ?>" alt="Profile" class="user-avatar-img">
                                            <?php else: ?>
                                                <div class="user-avatar">
                                                    <?php 
                                                        $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
                                                        echo htmlspecialchars($initials);
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="user-info-cell">
                                                <span class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
                                                <span class="user-id">#<?php echo $user['id']; ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="role-badge <?php echo $user['role']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="contact-cell">
                                            <span class="contact-email"><?php echo htmlspecialchars($user['email']); ?></span>
                                            <?php if (!empty($user['phone'])): ?>
                                                <span class="contact-phone"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="assignment-cell">
                                            <?php if (!empty($user['coach_first_name'])): ?>
                                                <span class="assignment-coach"><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($user['coach_first_name'] . ' ' . $user['coach_last_name']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($user['team_name'])): ?>
                                                <span class="assignment-team"><i class="fas fa-users"></i> <?php echo htmlspecialchars($user['team_name']); ?></span>
                                            <?php endif; ?>
                                            <?php if (empty($user['coach_first_name']) && empty($user['team_name'])): ?>
                                                <span class="no-assignment">-</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="date-cell"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td class="date-cell">
                                        <?php if (!empty($user['last_login'])): ?>
                                            <?php echo date('M d, Y g:ia', strtotime($user['last_login'])); ?>
                                        <?php else: ?>
                                            <span style="color: var(--text-dim);">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $user['is_verified'] ? 'active' : 'inactive'; ?>">
                                            <i class="fas fa-<?php echo $user['is_verified'] ? 'check-circle' : 'clock'; ?>"></i>
                                            <?php echo $user['is_verified'] ? 'Active' : 'Pending'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn-icon" data-action="edit" data-id="<?php echo $user['id']; ?>" data-modal="edit-user-modal" 
                                                    data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                    data-first-name="<?php echo htmlspecialchars($user['first_name']); ?>"
                                                    data-last-name="<?php echo htmlspecialchars($user['last_name']); ?>"
                                                    data-phone="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                                    data-role="<?php echo htmlspecialchars($user['role']); ?>"
                                                    data-coach-id="<?php echo htmlspecialchars($user['assigned_coach_id'] ?? ''); ?>"
                                                    data-birth-date="<?php echo htmlspecialchars($user['birth_date'] ?? ''); ?>"
                                                    data-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                    title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon" data-action="security" data-id="<?php echo $user['id']; ?>" 
                                                    data-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                    data-role="<?php echo htmlspecialchars($user['role']); ?>"
                                                    title="Password & PIN">
                                                <i class="fas fa-lock"></i>
                                            </button>
                                            <?php if ($user['id'] != $user_id): ?>
                                                <button class="btn-icon <?php echo $user['is_verified'] ? 'danger' : 'success'; ?>" data-action="toggle-status" data-id="<?php echo $user['id']; ?>" data-type="user" title="<?php echo $user['is_verified'] ? 'Disable' : 'Enable'; ?>">
                                                    <i class="fas fa-<?php echo $user['is_verified'] ? 'ban' : 'check'; ?>"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Users Found</h3>
                    <p>No users match your search criteria. Try adjusting your filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var csrfToken = document.querySelector('[name="csrf_token"]')?.value || '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES) ?>';
    
    // Show notification helper
    function showNotification(message, type) {
        var existing = document.querySelector('.notification-widget');
        if (existing) existing.remove();
        
        var div = document.createElement('div');
        div.className = 'notification-widget';
        div.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; padding: 16px 24px; border-radius: 8px; display: flex; align-items: center; gap: 12px;';
        if (type === 'success') {
            div.style.background = 'rgba(16, 185, 129, 0.95)';
            div.style.color = '#fff';
        } else {
            div.style.background = 'rgba(239, 68, 68, 0.95)';
            div.style.color = '#fff';
        }
        div.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message + '<button onclick="this.parentElement.remove()" style="margin-left: 16px; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">&times;</button>';
        document.body.appendChild(div);
        setTimeout(function() { if (div.parentElement) div.remove(); }, 5000);
    }
    
    // Handle toggle-status buttons for users
    document.querySelectorAll('[data-action="toggle-status"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var userId = this.getAttribute('data-id');
            var isCurrentlyActive = this.classList.contains('danger');
            
            if (!confirm('Are you sure you want to ' + (isCurrentlyActive ? 'disable' : 'enable') + ' this user?')) return;
            
            fetch('process_admin_action.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=toggle_user_status&id=' + encodeURIComponent(userId) + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message || 'User status updated!', 'success');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    showNotification('Error: ' + (data.message || 'Failed to update status'), 'error');
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
        });
    });
    
    
    // Handle add user button
    document.querySelectorAll('[data-action="add"][data-modal]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var modalId = this.getAttribute('data-modal');
            var modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
            }
        });
    });
    
    // Apply filters
    var roleFilter = document.getElementById('roleFilter');
    var statusFilter = document.getElementById('statusFilter');
    var teamFilter = document.getElementById('teamFilter');
    var ageFilter = document.getElementById('ageFilter');
    if (roleFilter) roleFilter.addEventListener('change', applyFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);
    if (teamFilter) teamFilter.addEventListener('change', applyFilters);
    if (ageFilter) ageFilter.addEventListener('change', applyFilters);
});

function applyFilters() {
    var role = document.getElementById('roleFilter').value;
    var status = document.getElementById('statusFilter').value;
    var search = document.getElementById('userSearch').value;
    var team = document.getElementById('teamFilter').value;
    var age = document.getElementById('ageFilter').value;
    
    var url = '?page=all_users';
    if (role) url += '&role=' + encodeURIComponent(role);
    if (status) url += '&status=' + encodeURIComponent(status);
    if (search) url += '&search=' + encodeURIComponent(search);
    if (team) url += '&team=' + encodeURIComponent(team);
    if (age) url += '&age=' + encodeURIComponent(age);
    
    window.location.href = url;
}

function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        var form = modal.querySelector('form');
        if (form) form.reset();
    }
}
</script>

<style>
/* Users Page - Action Bar Enhanced */
.action-bar-enhanced {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.filter-form-enhanced {
    display: flex;
    gap: 12px;
    flex: 1;
    flex-wrap: wrap;
    align-items: center;
}

.search-input-wrapper {
    position: relative;
    flex: 1;
    min-width: 200px;
}

.search-input-wrapper i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}

.search-input-wrapper input {
    padding-left: 42px;
}

.action-buttons {
    display: flex;
    gap: 10px;
}

/* Users Card */
.users-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.users-count-badge {
    padding: 6px 14px;
    background: rgba(107, 70, 193, 0.15);
    color: var(--primary-light);
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

/* Enhanced Table */
.enhanced-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.enhanced-table thead th {
    padding: 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: rgba(107, 70, 193, 0.05);
    border-bottom: 2px solid var(--border);
}

.enhanced-table tbody td {
    padding: 16px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.enhanced-table tbody tr {
    transition: all 0.2s ease;
}

.enhanced-table tbody tr:hover {
    background: rgba(107, 70, 193, 0.05);
}

.user-cell {
    display: flex;
    align-items: center;
    gap: 14px;
}

.user-avatar {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.25);
}

.user-info-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.user-info-cell .user-name {
    font-weight: 600;
    color: var(--text-primary);
}

.user-info-cell .user-id {
    font-size: 11px;
    color: var(--text-muted);
}

.email-cell {
    color: var(--text-secondary);
    font-size: 13px;
}

.date-cell {
    font-size: 13px;
    color: var(--text-muted);
}

.role-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.role-badge.admin {
    background: rgba(239, 68, 68, 0.15);
    color: var(--error);
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.role-badge.coach, .role-badge.team_coach, .role-badge.health_coach {
    background: rgba(59, 130, 246, 0.15);
    color: #3B82F6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.role-badge.athlete {
    background: rgba(16, 185, 129, 0.15);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.role-badge.parent {
    background: rgba(245, 158, 11, 0.15);
    color: var(--warning);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.status-badge.active {
    background: rgba(16, 185, 129, 0.15);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-badge.inactive {
    background: rgba(148, 163, 184, 0.15);
    color: var(--text-muted);
    border: 1px solid var(--border);
}

.table-actions {
    display: flex;
    gap: 8px;
}

.btn-icon {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s ease;
    padding: 0;
}

.btn-icon:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

.btn-icon.danger:hover {
    background: var(--error);
    border-color: var(--error);
}

.btn-icon.success:hover {
    background: var(--success);
    border-color: var(--success);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 24px;
}

.empty-state i {
    font-size: 56px;
    color: var(--border);
    margin-bottom: 20px;
}

.empty-state h3 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--text-muted);
    font-size: 14px;
}

/* Profile Image Avatar */
.user-avatar-img {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    object-fit: cover;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.25);
}

/* Contact Cell Styles */
.contact-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.contact-email {
    color: var(--text-secondary);
    font-size: 13px;
}

.contact-phone {
    color: var(--text-muted);
    font-size: 12px;
}

.contact-phone i {
    margin-right: 4px;
    font-size: 10px;
}

/* Assignment Cell Styles */
.assignment-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.assignment-coach, .assignment-team {
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.assignment-coach {
    color: #3B82F6;
}

.assignment-team {
    color: var(--primary-light);
}

.no-assignment {
    color: var(--text-muted);
}

/* Form Hints */
.form-hint {
    display: block;
    margin-top: 6px;
    font-size: 12px;
    color: var(--text-muted);
}

/* Checkbox Label */
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    color: var(--text-secondary);
    font-size: 14px;
}

/* Full-width submit buttons inside tabs */
.btn-block {
    width: 100%;
}

/* Profile Upload Preview */
.profile-upload-area {
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.profile-upload-area:hover {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.05);
}

.profile-upload-area i {
    font-size: 40px;
    color: var(--primary);
    margin-bottom: 10px;
}

.profile-preview {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 15px;
}

/* Notification Settings */
.notification-option {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid var(--border);
}

.notification-option:last-child {
    border-bottom: none;
}

.notification-option-info h4 {
    margin: 0 0 4px 0;
    font-size: 14px;
    color: var(--text-primary);
}

.notification-option-info p {
    margin: 0;
    font-size: 12px;
    color: var(--text-muted);
}


@media (max-width: 768px) {
    .action-bar-enhanced {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-form-enhanced {
        flex-direction: column;
    }
    
    .action-buttons {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>

<!-- Add User Modal -->
<div id="add-user-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add New User</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('add-user-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php" enctype="multipart/form-data">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_user">
            
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-input" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="phone" class="form-input">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Role *</label>
                        <select name="role" class="form-input" required id="add-user-role">
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="coach">Coach</option>
                            <option value="health_coach">Health Coach</option>
                            <option value="team_coach">Team Coach</option>
                            <option value="athlete">Athlete</option>
                            <option value="parent">Parent</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="is_verified" class="form-input">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="birth_date" class="form-input">
                </div>
                
                <div class="form-row athlete-coach-fields" style="display: none;">
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">Assign Coaches</label>
                        <div id="add-user-coach-typeahead"></div>
                    </div>
                </div>
                
                <div class="form-row athlete-coach-fields" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Assign Team</label>
                        <select name="team_id" class="form-input">
                            <option value="">No Team</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Temporary Password *</label>
                    <input type="password" name="password" class="form-input" required>
                    <small class="form-hint">User will be prompted to change on first login</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('add-user-modal')">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create User</button>
            </div>
        </form>
    </div>
</div>

<script>
// Toggle athlete/coach fields based on role selection
document.getElementById('add-user-role').addEventListener('change', function() {
    var athleteFields = this.closest('form').querySelector('.athlete-coach-fields');
    if (this.value === 'athlete') {
        athleteFields.style.display = 'grid';
    } else {
        athleteFields.style.display = 'none';
    }
});
</script>

<!-- Edit User Modal (combined with Manage Assignments) -->
<div id="edit-user-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Edit User - <span class="edit-user-display-name"></span></h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('edit-user-modal')">&times;</button>
        </div>
        
        <div class="modal-body">
            <input type="hidden" id="edit-user-id" value="">
            
            <!-- Edit User Tabs -->
            <div class="tabs edit-user-tabs">
                <button type="button" class="tab active" data-tab="edit-details-tab">
                    <i class="fas fa-user"></i> Details
                </button>
                <button type="button" class="tab" data-tab="edit-assignments-tab">
                    <i class="fas fa-users"></i> Assignments
                </button>
                <button type="button" class="tab" data-tab="edit-profile-tab">
                    <i class="fas fa-user-circle"></i> Profile Image
                </button>
                <button type="button" class="tab" data-tab="edit-notifications-tab">
                    <i class="fas fa-bell"></i> Notifications
                </button>
            </div>
            
            <!-- Details Tab -->
            <div id="edit-details-tab" class="tab-content active">
                <form method="POST" action="process_admin_action.php" id="edit-user-details-form">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" class="edit-form-user-id" value="">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" id="edit-user-first-name" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" id="edit-user-last-name" class="form-input" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="edit-user-email" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" id="edit-user-phone" class="form-input">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Role *</label>
                            <select name="role" id="edit-user-role" class="form-input" required>
                                <option value="">Select Role</option>
                                <option value="admin">Admin</option>
                                <option value="coach">Coach</option>
                                <option value="health_coach">Health Coach</option>
                                <option value="team_coach">Team Coach</option>
                                <option value="athlete">Athlete</option>
                                <option value="parent">Parent</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="birth_date" id="edit-user-birth-date" class="form-input">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Update Details</button>
                </form>
            </div>
            
            <!-- Assignments Tab -->
            <div id="edit-assignments-tab" class="tab-content">
                <form id="edit-assignments-form">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="admin_update_assignments">
                    <input type="hidden" name="user_id" class="edit-form-user-id" value="">
                    
                    <div class="form-group">
                        <label class="form-label">Assigned Coaches</label>
                        <div id="edit-user-coach-typeahead"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Team Assignment</label>
                        <select name="team_id" class="form-input" id="edit-user-team-id">
                            <option value="">No Team Assigned</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Jersey Number</label>
                        <input type="number" name="jersey_number" class="form-input" id="edit-user-jersey" min="0" max="99" placeholder="Enter jersey number">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <select name="position" class="form-input" id="edit-user-position">
                            <option value="">Select Position</option>
                            <option value="Center">Center</option>
                            <option value="Left Wing">Left Wing</option>
                            <option value="Right Wing">Right Wing</option>
                            <option value="Left Defense">Left Defense</option>
                            <option value="Right Defense">Right Defense</option>
                            <option value="Goalie">Goalie</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Save Assignments</button>
                </form>
            </div>
            
            <!-- Profile Image Tab -->
            <div id="edit-profile-tab" class="tab-content">
                <form id="edit-profile-image-form" enctype="multipart/form-data">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="admin_update_profile_image">
                    <input type="hidden" name="user_id" class="edit-form-user-id" value="">
                    
                    <div class="profile-upload-area" onclick="document.getElementById('edit-profile-image-input').click()">
                        <img src="" alt="" class="profile-preview" id="edit-profile-preview" style="display: none;">
                        <div id="edit-profile-upload-placeholder">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload profile image</p>
                            <small class="form-hint">JPG, PNG, GIF up to 5MB</small>
                        </div>
                    </div>
                    <input type="file" id="edit-profile-image-input" name="profile_image" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="edit-remove-profile-image"><i class="fas fa-trash"></i> Remove</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload Image</button>
                    </div>
                </form>
            </div>
            
            <!-- Notifications Tab -->
            <div id="edit-notifications-tab" class="tab-content">
                <form id="edit-notifications-form">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="admin_update_notifications">
                    <input type="hidden" name="user_id" class="edit-form-user-id" value="">
                    
                    <div class="notification-option">
                        <div class="notification-option-info">
                            <h4>Email Notifications</h4>
                            <p>Receive notifications via email</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="email_notifications" value="1" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="notification-option">
                        <div class="notification-option-info">
                            <h4>Session Reminders</h4>
                            <p>Get reminders before scheduled sessions</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="session_reminders" value="1" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="notification-option">
                        <div class="notification-option-info">
                            <h4>Goal Updates</h4>
                            <p>Notifications about goal progress</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="goal_updates" value="1" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="notification-option">
                        <div class="notification-option-info">
                            <h4>Marketing Emails</h4>
                            <p>Receive promotional content and updates</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="marketing_emails" value="1">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Save Notification Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Edit user modal tab switching
document.querySelectorAll('.edit-user-tabs .tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
        var modal = this.closest('.modal');
        modal.querySelectorAll('.edit-user-tabs .tab').forEach(function(t) { t.classList.remove('active'); });
        modal.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
        this.classList.add('active');
        var tabId = this.getAttribute('data-tab');
        document.getElementById(tabId).classList.add('active');
    });
});

// Handle edit user button clicks - populate modal fields
document.querySelectorAll('[data-action="edit"][data-modal="edit-user-modal"]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var id = this.getAttribute('data-id');
        var email = this.getAttribute('data-email');
        var firstName = this.getAttribute('data-first-name');
        var lastName = this.getAttribute('data-last-name');
        var phone = this.getAttribute('data-phone');
        var role = this.getAttribute('data-role');
        var coachId = this.getAttribute('data-coach-id');
        var birthDate = this.getAttribute('data-birth-date');
        var userName = this.getAttribute('data-name');
        
        document.getElementById('edit-user-id').value = id;
        document.querySelectorAll('.edit-form-user-id').forEach(function(el) { el.value = id; });
        document.querySelector('.edit-user-display-name').textContent = userName || (firstName + ' ' + lastName);
        document.getElementById('edit-user-email').value = email;
        document.getElementById('edit-user-first-name').value = firstName;
        document.getElementById('edit-user-last-name').value = lastName;
        document.getElementById('edit-user-phone').value = phone || '';
        document.getElementById('edit-user-role').value = role;
        document.getElementById('edit-user-birth-date').value = birthDate || '';
        
        // Pre-populate edit coach typeahead
        if (window._editCoachTypeahead) {
            window._editCoachTypeahead.clear();
            var coachAssignments = window._athleteCoachAssignments || {};
            var coachNames = window._coachNamesMap || {};
            var assignedCoachIds = coachAssignments[id] || [];
            // Fallback: use primary coach_id from the data attribute
            if (assignedCoachIds.length === 0 && coachId) {
                assignedCoachIds = [parseInt(coachId)];
            }
            var preItems = [];
            assignedCoachIds.forEach(function(cid) {
                if (coachNames[cid]) {
                    preItems.push({ id: cid, name: coachNames[cid].name, role: coachNames[cid].role });
                }
            });
            window._editCoachTypeahead.setPreSelected(preItems);
        }
        
        // Reset to first tab
        var modal = document.getElementById('edit-user-modal');
        modal.querySelectorAll('.edit-user-tabs .tab').forEach(function(t) { t.classList.remove('active'); });
        modal.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
        modal.querySelector('.edit-user-tabs .tab[data-tab="edit-details-tab"]').classList.add('active');
        document.getElementById('edit-details-tab').classList.add('active');
        
        modal.classList.add('active');
    });
});

// Profile image preview (edit modal)
document.getElementById('edit-profile-image-input').addEventListener('change', function(e) {
    var file = e.target.files[0];
    if (file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('edit-profile-preview').src = e.target.result;
            document.getElementById('edit-profile-preview').style.display = 'block';
            document.getElementById('edit-profile-upload-placeholder').style.display = 'none';
        };
        reader.readAsDataURL(file);
    }
});

// Profile image upload (edit modal)
document.getElementById('edit-profile-image-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    
    fetch('process_admin_action.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Profile image updated!', 'success');
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showNotification('Error: ' + (data.message || 'Failed to update image'), 'error');
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    });
});

// Remove profile image (edit modal)
document.getElementById('edit-remove-profile-image').addEventListener('click', function() {
    if (!confirm('Are you sure you want to remove the profile image?')) return;
    
    var userId = document.getElementById('edit-user-id').value;
    var formData = new FormData();
    formData.append('action', 'admin_remove_profile_image');
    formData.append('user_id', userId);
    formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
    
    fetch('process_admin_action.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Profile image removed!', 'success');
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showNotification('Error: ' + (data.message || 'Failed to remove image'), 'error');
        }
    });
});

// Assignments form submit (edit modal)
document.getElementById('edit-assignments-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    
    fetch('process_admin_action.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Assignments updated!', 'success');
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showNotification('Error: ' + (data.message || 'Failed to update assignments'), 'error');
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    });
});

// Notifications form submit (edit modal)
document.getElementById('edit-notifications-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    
    fetch('process_admin_action.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Notification settings updated!', 'success');
        } else {
            showNotification('Error: ' + (data.message || 'Failed to update settings'), 'error');
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    });
});
</script>

<!-- Security Modal (Password & PIN) -->
<div id="security-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Security - <span class="security-user-name"></span></h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('security-modal')">&times;</button>
        </div>
        
        <div class="modal-body">
            <!-- Security Tabs -->
            <div class="tabs security-tabs">
                <button type="button" class="tab active" data-tab="security-password-tab">
                    <i class="fas fa-key"></i> Password
                </button>
                <button type="button" class="tab" data-tab="security-pin-tab" id="security-pin-tab-btn">
                    <i class="fas fa-th"></i> PIN
                </button>
            </div>
            
            <!-- Password Tab -->
            <div id="security-password-tab" class="tab-content active">
                <form method="POST" action="process_admin_action.php" id="reset-password-form">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="reset_user_password">
                    <input type="hidden" name="user_id" class="security-form-user-id" value="">
                    
                    <div class="form-group">
                        <label class="form-label">New Password *</label>
                        <input type="password" name="new_password" class="form-input" required minlength="8" placeholder="Enter new password">
                        <small class="form-hint">Minimum 8 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm Password *</label>
                        <input type="password" name="confirm_password" class="form-input" required minlength="8" placeholder="Confirm new password">
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="force_change" value="1" checked>
                            <span>Force user to change password on next login</span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-key"></i> Reset Password</button>
                </form>
            </div>
            
            <!-- PIN Tab -->
            <div id="security-pin-tab" class="tab-content">
                <form method="POST" action="process_admin_action.php" id="reset-pin-form">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="admin_reset_pin">
                    <input type="hidden" name="user_id" class="security-form-user-id" value="">
                    
                    <p class="form-hint" style="margin-bottom: 20px;">
                        Set or reset POS/Kiosk PIN for this user.
                    </p>
                    
                    <div class="form-group">
                        <label class="form-label">New PIN (4 digits) *</label>
                        <input type="password" name="new_pin" class="form-input" required pattern="\d{4}" maxlength="4" inputmode="numeric" placeholder="••••" autocomplete="off">
                        <small class="form-hint">Must be exactly 4 digits</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm PIN *</label>
                        <input type="password" name="confirm_pin" class="form-input" required pattern="\d{4}" maxlength="4" inputmode="numeric" placeholder="••••" autocomplete="off">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-th"></i> Set PIN</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Security modal tab switching
document.querySelectorAll('.security-tabs .tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
        var modal = this.closest('.modal');
        modal.querySelectorAll('.security-tabs .tab').forEach(function(t) { t.classList.remove('active'); });
        modal.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
        this.classList.add('active');
        var tabId = this.getAttribute('data-tab');
        document.getElementById(tabId).classList.add('active');
    });
});

// Handle security button click
document.querySelectorAll('[data-action="security"]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var userId = this.getAttribute('data-id');
        var userName = this.getAttribute('data-name') || 'this user';
        var userRole = this.getAttribute('data-role') || '';
        
        var modal = document.getElementById('security-modal');
        if (modal) {
            modal.querySelectorAll('.security-form-user-id').forEach(function(el) { el.value = userId; });
            modal.querySelector('.security-user-name').textContent = userName;
            
            // Clear previous values
            modal.querySelector('input[name="new_password"]').value = '';
            modal.querySelector('input[name="confirm_password"]').value = '';
            modal.querySelector('input[name="new_pin"]').value = '';
            modal.querySelector('input[name="confirm_pin"]').value = '';
            
            // Show/hide PIN tab based on role
            var pinRoles = ['admin', 'coach', 'health_coach', 'front_desk_staff'];
            var pinTabBtn = document.getElementById('security-pin-tab-btn');
            if (pinTabBtn) {
                pinTabBtn.style.display = pinRoles.indexOf(userRole) !== -1 ? '' : 'none';
            }
            
            // Reset to password tab
            modal.querySelectorAll('.security-tabs .tab').forEach(function(t) { t.classList.remove('active'); });
            modal.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
            modal.querySelector('.security-tabs .tab[data-tab="security-password-tab"]').classList.add('active');
            document.getElementById('security-password-tab').classList.add('active');
            
            modal.classList.add('active');
        }
    });
});

// Handle reset password form submission
document.getElementById('reset-password-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var newPassword = this.querySelector('input[name="new_password"]').value;
    var confirmPassword = this.querySelector('input[name="confirm_password"]').value;
    
    if (newPassword !== confirmPassword) {
        showNotification('Passwords do not match', 'error');
        return;
    }
    
    var formData = new FormData(this);
    var submitBtn = this.querySelector('button[type="submit"]');
    var originalBtnText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    submitBtn.disabled = true;
    
    fetch(this.getAttribute('action'), {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
        
        if (data.success) {
            showNotification(data.message || 'Password reset successfully!', 'success');
            closeModal('security-modal');
            // Reload page after successful password reset to show updated data
            setTimeout(function() { location.reload(); }, 1500);
        } else {
            showNotification('Error: ' + (data.message || 'Failed to reset password'), 'error');
        }
    })
    .catch(function(error) {
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    });
});

// Handle reset PIN form submission
var resetPinForm = document.getElementById('reset-pin-form');
if (resetPinForm) {
    resetPinForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        var newPin = this.querySelector('input[name="new_pin"]').value;
        var confirmPin = this.querySelector('input[name="confirm_pin"]').value;
        
        if (newPin !== confirmPin) {
            showNotification('PINs do not match', 'error');
            return;
        }
        
        if (!/^\d{4}$/.test(newPin)) {
            showNotification('PIN must be exactly 4 digits', 'error');
            return;
        }
        
        var formData = new FormData(this);
        var submitBtn = this.querySelector('button[type="submit"]');
        var originalBtnText = submitBtn.innerHTML;
        
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        submitBtn.disabled = true;
        
        fetch(this.action, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
            
            if (data.success) {
                showNotification(data.message || 'PIN set successfully!', 'success');
                closeModal('security-modal');
            } else {
                showNotification('Error: ' + (data.message || 'Failed to set PIN'), 'error');
            }
        })
        .catch(function(error) {
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
            console.error('Error:', error);
            showNotification('An error occurred. Please try again.', 'error');
        });
    });
}
</script>
<script>
// Build coach name map and athlete-coach assignments for pre-populating typeaheads
window._coachNamesMap = <?php
    $coachMap = [];
    foreach ($coaches as $c) {
        $roleLabel = '';
        switch($c['role']) {
            case 'admin': $roleLabel = 'Admin'; break;
            case 'health_coach': $roleLabel = 'Health Coach'; break;
            case 'team_coach': $roleLabel = 'Team Coach'; break;
            case 'coach': $roleLabel = 'Coach'; break;
        }
        $coachMap[$c['id']] = ['name' => $c['name'], 'role' => $roleLabel];
    }
    echo json_encode($coachMap);
?>;
window._athleteCoachAssignments = <?= json_encode($athlete_coaches_map) ?>;

// Initialize coach typeaheads
window._addCoachTypeahead = new ArcticTypeahead({
    container: '#add-user-coach-typeahead',
    name: 'assigned_coach_ids',
    placeholder: 'Search for coaches…',
    roles: 'admin,coach,team_coach,health_coach',
    multiple: true
});

window._editCoachTypeahead = new ArcticTypeahead({
    container: '#edit-user-coach-typeahead',
    name: 'assigned_coach_ids',
    placeholder: 'Search for coaches…',
    roles: 'admin,coach,team_coach,health_coach',
    multiple: true
});
</script>
