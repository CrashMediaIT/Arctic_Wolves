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
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-users-cog"></i> User Management
    </h1>
    <p class="page-description">Manage all system users and permissions</p>
</div>

<div class="users-content">
    <!-- Filter and Actions -->
    <div class="action-bar">
        <div class="filter-group">
            <input type="text" class="form-input search-input" placeholder="Search users..." 
                   data-search-table="users" value="<?php echo htmlspecialchars($search); ?>" id="userSearch">
            <select class="form-select" data-filter-table="users" data-filter-column="role" id="roleFilter">
                <option value="">All Roles</option>
                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="coach" <?php echo $role_filter === 'coach' ? 'selected' : ''; ?>>Coach</option>
                <option value="health_coach" <?php echo $role_filter === 'health_coach' ? 'selected' : ''; ?>>Health Coach</option>
                <option value="team_coach" <?php echo $role_filter === 'team_coach' ? 'selected' : ''; ?>>Team Coach</option>
                <option value="athlete" <?php echo $role_filter === 'athlete' ? 'selected' : ''; ?>>Athlete</option>
                <option value="parent" <?php echo $role_filter === 'parent' ? 'selected' : ''; ?>>Parent</option>
            </select>
            <select class="form-select" data-filter-table="users" data-filter-column="status" id="statusFilter">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <button class="btn btn-primary" data-action="add" onclick="openAddUserModal()">
            <i class="fas fa-user-plus"></i> Add User
        </button>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-users"></i> All Users (<?php echo $total_users; ?>)</h3>
            <button class="btn btn-secondary" data-action="export">
                <i class="fas fa-file-export"></i> Export
            </button>
        </div>
        <div class="card-body">
            <?php if (count($users) > 0): ?>
                <div class="table-wrapper">
                    <table class="data-table" id="users-table">
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
                                            <span><?php echo htmlspecialchars($user['full_name']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="role-badge <?php echo $user['role']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $user['is_verified'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $user['is_verified'] ? 'Active' : 'Pending'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn-icon" data-action="edit" data-id="<?php echo $user['id']; ?>" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon" data-action="permissions" data-id="<?php echo $user['id']; ?>" title="Permissions">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <?php if ($user['id'] != $user_id): ?>
                                                <button class="btn-icon" data-action="toggle-status" data-id="<?php echo $user['id']; ?>" title="<?php echo $user['is_verified'] ? 'Disable' : 'Enable'; ?>">
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
                <p class="placeholder-text">No users found matching your criteria.</p>
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
    
    let url = '?page=admin_users';
    if (role) url += '&role=' + encodeURIComponent(role);
    if (status) url += '&status=' + encodeURIComponent(status);
    if (search) url += '&search=' + encodeURIComponent(search);
    
    window.location.href = url;
}
</script>

<style>
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    gap: 12px;
    flex: 1;
    min-width: 300px;
}

.user-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
}

.role-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.role-badge.admin {
    background: rgba(239, 68, 68, 0.15);
    color: var(--error);
}

.role-badge.coach, .role-badge.team_coach, .role-badge.health_coach {
    background: rgba(59, 130, 246, 0.15);
    color: #3B82F6;
}

.role-badge.athlete {
    background: rgba(16, 185, 129, 0.15);
    color: var(--success);
}

.role-badge.parent {
    background: rgba(245, 158, 11, 0.15);
    color: var(--warning);
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.status-badge.active {
    background: rgba(16, 185, 129, 0.15);
    color: var(--success);
}

.status-badge.inactive, .status-badge.pending {
    background: rgba(239, 68, 68, 0.15);
    color: var(--error);
}

.table-actions {
    display: flex;
    gap: 8px;
}

.btn-icon {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-white);
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 0;
}

.btn-icon:hover {
    background: rgba(107, 70, 193, 0.1);
    border-color: var(--primary);
    color: var(--primary);
}

@media (max-width: 768px) {
    .action-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        flex-direction: column;
    }
}
</style>

.status-badge.inactive {
    background: rgba(148, 163, 184, 0.1);
    color: var(--text-dim);
}
</style>
