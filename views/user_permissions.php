<?php
/**
 * User Permissions Management
 * Manage user roles and permissions
 */

require_once __DIR__ . '/../security.php';

// Admin only
if (!$isAdmin) {
    echo "<div class='alert alert-error'>Access Denied: Admin privileges required.</div>";
    exit;
}

$target_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Get user details if editing
$target_user = null;
if ($target_user_id) {
    $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->execute([$target_user_id]);
    $target_user = $user_stmt->fetch();
}

// Get current permissions for this user
$user_permissions = [];
if ($target_user_id) {
    $perm_stmt = $pdo->prepare("
        SELECT p.* 
        FROM permissions p
        INNER JOIN user_permissions up ON p.id = up.permission_id
        WHERE up.user_id = ?
    ");
    $perm_stmt->execute([$target_user_id]);
    $user_permissions = $perm_stmt->fetchAll();
}

// Get all available permissions
$all_perms_stmt = $pdo->query("SELECT * FROM permissions ORDER BY permission_name");
$all_permissions = $all_perms_stmt->fetchAll();

// Get role permissions
$role_permissions = [];
if ($target_user) {
    $role_perm_stmt = $pdo->prepare("
        SELECT p.* 
        FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        WHERE rp.role = ?
    ");
    $role_perm_stmt->execute([$target_user['role']]);
    $role_permissions = $role_perm_stmt->fetchAll();
}

$user_perm_ids = array_column($user_permissions, 'id');
$role_perm_ids = array_column($role_permissions, 'id');
?>

<style>
    :root {
        --primary: #7000a4;
        --neon: #7000a4;
    }
    .permissions-header {
        background: linear-gradient(135deg, var(--primary) 0%, #4a0070 100%);
        border-radius: 8px;
        padding: 30px;
        margin-bottom: 30px;
        color: #fff;
    }
    .permissions-header h1 {
        margin: 0 0 10px 0;
        font-size: 28px;
        font-weight: 900;
    }
    .section-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 25px;
        margin-bottom: 30px;
    }
    .permission-item {
        background: #161b22;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .permission-item.active {
        border-color: var(--neon);
        background: rgba(112, 0, 164, 0.1);
    }
    .permission-item.inherited {
        border-color: #3b82f6;
        background: rgba(59, 130, 246, 0.05);
    }
    .badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .badge-active {
        background: var(--neon);
        color: #fff;
    }
    .badge-inherited {
        background: #3b82f6;
        color: #fff;
    }
</style>

<div class="permissions-header">
    <h1><i class="fa-solid fa-shield-halved"></i> User Permissions</h1>
    <p>Manage user roles and access controls</p>
</div>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-<?= $_GET['status'] === 'updated' ? 'success' : 'error' ?>">
        <?php if ($_GET['status'] === 'updated'): ?>
            Permissions updated successfully!
        <?php else: ?>
            Failed to update permissions. Please try again.
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!$target_user): ?>
    <div class="section-card">
        <h2>Select a User</h2>
        <p style="margin-bottom: 20px;">Choose a user to manage their permissions:</p>
        
        <?php
        $users_stmt = $pdo->query("SELECT id, first_name, last_name, email, role FROM users ORDER BY last_name, first_name");
        $all_users = $users_stmt->fetchAll();
        ?>
        
        <table style="width: 100%;">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="badge badge-active"><?= ucfirst($u['role']) ?></span></td>
                        <td>
                            <a href="?page=user_permissions&user_id=<?= $u['id'] ?>" class="btn-sm">Manage Permissions</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="section-card">
        <h2>User Information</h2>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 15px;">
            <div>
                <strong>Name:</strong> <?= htmlspecialchars($target_user['first_name'] . ' ' . $target_user['last_name']) ?>
            </div>
            <div>
                <strong>Email:</strong> <?= htmlspecialchars($target_user['email']) ?>
            </div>
            <div>
                <strong>Role:</strong> <span class="badge badge-active"><?= ucfirst($target_user['role']) ?></span>
            </div>
        </div>
    </div>

    <div class="section-card">
        <h2>Permissions</h2>
        <p style="margin-bottom: 20px;">
            <span class="badge badge-inherited">INHERITED</span> permissions come from the user's role.
            <span class="badge badge-active">CUSTOM</span> permissions are specifically assigned to this user.
        </p>

        <form action="process_permissions.php" method="POST">
            <?php csrfTokenGenerate(); ?>
            <input type="hidden" name="action" value="update_user_permissions">
            <input type="hidden" name="user_id" value="<?= $target_user_id ?>">

            <?php foreach ($all_permissions as $perm): ?>
                <?php 
                $has_custom = in_array($perm['id'], $user_perm_ids);
                $has_inherited = in_array($perm['id'], $role_perm_ids);
                $is_active = $has_custom || $has_inherited;
                ?>
                <div class="permission-item <?= $is_active ? ($has_custom ? 'active' : 'inherited') : '' ?>">
                    <div>
                        <strong><?= htmlspecialchars($perm['permission_name']) ?></strong>
                        <p style="margin: 5px 0 0 0; font-size: 13px; color: #8b949e;">
                            <?= htmlspecialchars($perm['description'] ?? '') ?>
                        </p>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <?php if ($has_inherited): ?>
                            <span class="badge badge-inherited">Inherited</span>
                        <?php endif; ?>
                        <label style="display: flex; align-items: center; gap: 5px; margin: 0;">
                            <input type="checkbox" name="permissions[]" value="<?= $perm['id'] ?>" 
                                   <?= $has_custom ? 'checked' : '' ?>>
                            Custom
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>

            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-save"></i> Save Permissions
                </button>
                <a href="?page=user_permissions" class="btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Back to User List
                </a>
            </div>
        </form>
    </div>
<?php endif; ?>
