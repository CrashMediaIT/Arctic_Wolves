<?php
/**
 * Permissions Management (Admin Only)
 * Manage role-based and user-specific permissions
 */

require_once __DIR__ . '/../security.php';

// Admin only
if ($user_role !== 'admin') {
    echo '<h2 style="color:#ef4444;">Access Denied</h2>';
    echo '<p>Only administrators can access this page.</p>';
    exit;
}

// Get all permissions grouped by category
$permissions = $pdo->query("
    SELECT * FROM permissions 
    ORDER BY category, permission_name
")->fetchAll(PDO::FETCH_GROUP);

// Get all roles
$roles = ['athlete', 'coach', 'coach_plus', 'admin'];

// Get current role permissions
$role_perms = [];
foreach ($roles as $role) {
    $stmt = $pdo->prepare("
        SELECT p.permission_key, rp.granted
        FROM permissions p
        LEFT JOIN role_permissions rp ON p.id = rp.permission_id AND rp.role = ?
    ");
    $stmt->execute([$role]);
    $role_perms[$role] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Get users for user-specific permissions
$users = $pdo->query("
    SELECT id, first_name, last_name, email, role 
    FROM users 
    WHERE role != 'athlete'
    ORDER BY role, first_name
")->fetchAll();
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
    }
    .tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 25px;
        border-bottom: 2px solid #1e293b;
    }
    .tab {
        padding: 12px 24px;
        background: none;
        border: none;
        color: #94a3b8;
        font-weight: 700;
        cursor: pointer;
        transition: 0.2s;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
    }
    .tab.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }
    .tab-content {
        display: none;
    }
    .tab-content.active {
        display: block;
    }
    .permissions-grid {
        display: grid;
        gap: 25px;
    }
    .permission-category {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 20px;
    }
    .category-header {
        font-size: 16px;
        font-weight: 700;
        color: var(--primary);
        text-transform: uppercase;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .permission-table {
        width: 100%;
        border-collapse: collapse;
    }
    .permission-table th {
        text-align: left;
        padding: 10px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748b;
        border-bottom: 1px solid #1e293b;
    }
    .permission-table td {
        padding: 12px 10px;
        border-bottom: 1px solid #1e293b;
        font-size: 13px;
    }
    .permission-name {
        color: #fff;
        font-weight: 600;
    }
    .permission-desc {
        color: #64748b;
        font-size: 12px;
        margin-top: 3px;
    }
    .checkbox-cell {
        text-align: center;
        width: 80px;
    }
    .perm-checkbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: var(--primary);
    }
    .role-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .role-athlete { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .role-coach { background: rgba(168, 85, 247, 0.1); color: #a855f7; }
    .role-coach_plus { background: rgba(236, 72, 153, 0.1); color: #ec4899; }
    .role-admin { background: rgba(255, 77, 0, 0.1); color: var(--primary); }
    .user-list {
        display: grid;
        gap: 12px;
    }
    .user-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 15px;
        cursor: pointer;
        transition: 0.2s;
    }
    .user-card:hover {
        border-color: var(--primary);
    }
    .user-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .user-name {
        font-weight: 700;
        color: #fff;
    }
    .user-email {
        color: #64748b;
        font-size: 12px;
    }
    .btn {
        padding: 10px 20px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.2s;
        font-size: 13px;
    }
    .btn:hover {
        background: #5a0080;
        transform: translateY(-2px);
    }
    .alert {
        padding: 12px 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-size: 13px;
    }
    .alert-success {
        background: rgba(0, 255, 136, 0.1);
        border: 1px solid #00ff88;
        color: #00ff88;
    }
    .alert-info {
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid #3b82f6;
        color: #3b82f6;
    }
</style>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-shield-halved"></i> Permissions Management</h1>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] === 'permissions_updated'): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> Permissions updated successfully!
    </div>
<?php endif; ?>

<div class="tabs">
    <button class="tab active" onclick="switchTab('role-perms')">
        <i class="fas fa-user-tag"></i> Role Permissions
    </button>
    <button class="tab" onclick="switchTab('user-perms')">
        <i class="fas fa-user-shield"></i> User Overrides
    </button>
</div>

<!-- Role Permissions Tab -->
<div id="role-perms" class="tab-content active">
    <div class="alert alert-info" style="margin-bottom: 25px;">
        <i class="fas fa-info-circle"></i>
        <strong>Role Permissions:</strong> Define what each role can do by default. 
        Changes apply to all users with that role unless overridden.
    </div>
    
    <form method="POST" action="process_permissions.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="update_role_permissions">
        
        <div class="permissions-grid">
            <?php foreach ($permissions as $category => $perms): ?>
                <div class="permission-category">
                    <div class="category-header">
                        <i class="fas fa-folder"></i>
                        <?= htmlspecialchars(ucwords($category)) ?>
                    </div>
                    
                    <table class="permission-table">
                        <thead>
                            <tr>
                                <th>Permission</th>
                                <th class="checkbox-cell">Athlete</th>
                                <th class="checkbox-cell">Coach</th>
                                <th class="checkbox-cell">Coach+</th>
                                <th class="checkbox-cell">Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($perms as $perm): ?>
                                <tr>
                                    <td>
                                        <div class="permission-name"><?= htmlspecialchars($perm['permission_name']) ?></div>
                                        <?php if ($perm['description']): ?>
                                            <div class="permission-desc"><?= htmlspecialchars($perm['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <?php foreach ($roles as $role): ?>
                                        <td class="checkbox-cell">
                                            <?php 
                                            $is_checked = isset($role_perms[$role][$perm['permission_key']]) && $role_perms[$role][$perm['permission_key']];
                                            $is_disabled = $role === 'admin'; // Admin always has all permissions
                                            ?>
                                            <input 
                                                type="checkbox" 
                                                class="perm-checkbox"
                                                name="perms[<?= $role ?>][<?= $perm['permission_key'] ?>]"
                                                value="1"
                                                <?= $is_checked ? 'checked' : '' ?>
                                                <?= $is_disabled ? 'disabled' : '' ?>
                                            >
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="margin-top: 30px; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn">
                <i class="fas fa-save"></i> Save Role Permissions
            </button>
        </div>
    </form>
</div>

<!-- User Overrides Tab -->
<div id="user-perms" class="tab-content">
    <div class="alert alert-info" style="margin-bottom: 25px;">
        <i class="fas fa-info-circle"></i>
        <strong>User Overrides:</strong> Grant or revoke specific permissions for individual users, 
        overriding their role's default permissions.
    </div>
    
    <div class="user-list">
        <?php foreach ($users as $user): ?>
            <div class="user-card" onclick="window.location.href='dashboard.php?page=user_permissions&user_id=<?= $user['id'] ?>'">
                <div class="user-info">
                    <div>
                        <div class="user-name">
                            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                        </div>
                        <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                    <span class="role-badge role-<?= $user['role'] ?>">
                        <?= htmlspecialchars(str_replace('_', ' ', $user['role'])) ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function switchTab(tabId) {
    // Remove active class from all tabs and contents
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    // Add active class to selected tab and content
    event.target.classList.add('active');
    document.getElementById(tabId).classList.add('active');
}
</script>
