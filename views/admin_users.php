<!-- Admin Users Management View -->
<?php
// Fetch users from database
try {
    // Get filter values
    $role_filter = $_GET['role'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Build query
    $where = [];
    $params = [];
    
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
    
    if (!empty($search)) {
        $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $stmt = $pdo->prepare("
        SELECT u.*, 
               CONCAT(u.first_name, ' ', u.last_name) as full_name,
               COUNT(DISTINCT s.id) as session_count
        FROM users u
        LEFT JOIN sessions s ON u.id = s.coach_id
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

<div class="users-page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-users-cog"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">User Management</h1>
            <p class="page-description">Manage all system users, roles, and permissions</p>
        </div>
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
                <input type="text" name="search" class="form-input" placeholder="Search users by name or email..." 
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
            <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filter</button>
        </form>
        <div class="action-buttons">
            <form method="POST" action="process_admin_action.php" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="action" value="export">
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
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Joined</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar">
                                                <?php 
                                                    $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
                                                    echo htmlspecialchars($initials);
                                                ?>
                                            </div>
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
                                    <td class="email-cell"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                    <td class="date-cell"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
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
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon" data-action="permissions" data-id="<?php echo $user['id']; ?>" title="Permissions">
                                                <i class="fas fa-key"></i>
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
function openAddUserModal() {
    // Implement modal for adding user
    alert('Add user modal - implement with form');
}

// Apply filters
document.getElementById('roleFilter').addEventListener('change', applyFilters);
document.getElementById('statusFilter').addEventListener('change', applyFilters);

function applyFilters() {
    const role = document.getElementById('roleFilter').value;
    const status = document.getElementById('statusFilter').value;
    const search = document.getElementById('userSearch').value;
    
    let url = '?page=all_users';
    if (role) url += '&role=' + encodeURIComponent(role);
    if (status) url += '&status=' + encodeURIComponent(status);
    if (search) url += '&search=' + encodeURIComponent(search);
    
    window.location.href = url;
}
</script>

<style>
/* Users Page Enhanced Styles */
.users-page-header {
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
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
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
}

.page-header-text p {
    font-size: 14px;
    color: var(--text-secondary);
    margin: 0;
}

.page-header-stats {
    display: flex;
    gap: 20px;
}

.header-stat {
    text-align: center;
    padding: 12px 20px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    min-width: 90px;
}

.header-stat .stat-value {
    display: block;
    font-size: 24px;
    font-weight: 700;
    color: var(--primary-light);
}

.header-stat .stat-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Action Bar Enhanced */
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

@media (max-width: 768px) {
    .users-page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .page-header-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .page-header-stats {
        width: 100%;
        justify-content: space-between;
    }
    
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
            <button class="modal-close" onclick="closeModal('add-user-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
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
                        <select name="role" class="form-input" required>
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="coach">Coach</option>
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
                    <label class="form-label">Temporary Password *</label>
                    <input type="password" name="password" class="form-input" required>
                    <small style="color: var(--text-dim);">User will be prompted to change on first login</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('add-user-modal')">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="edit-user-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Edit User</h2>
            <button class="modal-close" onclick="closeModal('edit-user-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" id="edit-user-id">
            
            <div class="modal-body">
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
                
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select name="role" id="edit-user-role" class="form-input" required>
                        <option value="">Select Role</option>
                        <option value="admin">Admin</option>
                        <option value="coach">Coach</option>
                        <option value="athlete">Athlete</option>
                        <option value="parent">Parent</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">New Password (leave blank to keep current)</label>
                    <input type="password" name="password" class="form-input" placeholder="Enter new password if changing">
                    <small style="color: var(--text-dim);">Leave empty to keep current password</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('edit-user-modal')">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update User</button>
            </div>
        </form>
    </div>
</div>

<script>
// Handle edit user button clicks
document.querySelectorAll('[data-action="edit"][data-modal="edit-user-modal"]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var id = this.getAttribute('data-id');
        var email = this.getAttribute('data-email');
        var firstName = this.getAttribute('data-first-name');
        var lastName = this.getAttribute('data-last-name');
        var phone = this.getAttribute('data-phone');
        var role = this.getAttribute('data-role');
        
        document.getElementById('edit-user-id').value = id;
        document.getElementById('edit-user-email').value = email;
        document.getElementById('edit-user-first-name').value = firstName;
        document.getElementById('edit-user-last-name').value = lastName;
        document.getElementById('edit-user-phone').value = phone || '';
        document.getElementById('edit-user-role').value = role;
        
        document.getElementById('edit-user-modal').classList.add('active');
    });
});

// Handle permissions button clicks - navigate to user permissions page
document.querySelectorAll('[data-action="permissions"]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var userId = this.getAttribute('data-id');
        if (userId) {
            window.location.href = 'dashboard.php?page=user_permissions&user_id=' + userId;
        }
    });
});

// Handle toggle-status button clicks
document.querySelectorAll('[data-action="toggle-status"]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var userId = this.getAttribute('data-id');
        var isActive = this.classList.contains('danger');
        var action = isActive ? 'disable_user' : 'enable_user';
        
        if (!confirm('Are you sure you want to ' + (isActive ? 'disable' : 'enable') + ' this user?')) {
            return;
        }
        
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'process_admin_action.php';
        
        var csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = document.querySelector('input[name="csrf_token"]').value;
        form.appendChild(csrfInput);
        
        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;
        form.appendChild(actionInput);
        
        var userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = userId;
        form.appendChild(userIdInput);
        
        document.body.appendChild(form);
        form.submit();
    });
});
</script>
