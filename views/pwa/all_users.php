<?php
/**
 * PWA All Users - Mobile-native user management list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$users = [];
try {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, role, is_active, created_at FROM users ORDER BY created_at DESC LIMIT 50");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $users = []; }
?>
<style>
.m-users { padding: 16px; font-family: Inter, sans-serif; }
.m-users-header { margin-bottom: 16px; }
.m-users-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-users-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-users-search {
    width: 100%; padding: 12px 16px 12px 40px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    margin-bottom: 16px; box-sizing: border-box;
    min-height: 44px;
}
.m-users-search::placeholder { color: #6B6B7B; }
.m-users-search-wrap {
    position: relative; margin-bottom: 16px;
}
.m-users-search-wrap i {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: #6B6B7B; font-size: 14px;
}
.m-user-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; text-decoration: none;
    min-height: 44px;
}
.m-user-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 700; color: #fff; flex-shrink: 0;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
}
.m-user-body { flex: 1; min-width: 0; }
.m-user-name { font-size: 14px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-user-email { font-size: 12px; color: #A8A8B8; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-user-badges { display: flex; gap: 6px; flex-shrink: 0; flex-wrap: wrap; justify-content: flex-end; }
.m-user-role {
    font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600;
    display: inline-block;
}
.m-user-role-admin { background: rgba(139,92,246,0.15); color: #8B5CF6; }
.m-user-role-coach { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-user-role-athlete { background: rgba(16,185,129,0.15); color: #10B981; }
.m-user-role-parent { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-user-role-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-user-active {
    font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600;
    display: inline-block;
}
.m-user-active-yes { background: rgba(16,185,129,0.15); color: #10B981; }
.m-user-active-no { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-users">
    <div class="m-users-header">
        <h2 class="m-users-title">All Users</h2>
        <p class="m-users-sub"><?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?></p>
    </div>

    <div class="m-users-search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" class="m-users-search" id="mUserSearch" placeholder="Search by name or email..." oninput="filterUsers()">
    </div>

    <div id="mUserList">
    <?php if (empty($users)): ?>
        <div class="m-empty-state">
            <i class="fas fa-users"></i>
            No users found
        </div>
    <?php else: ?>
        <?php foreach ($users as $u):
            $role = strtolower($u['role'] ?? 'default');
            $roleClass = match($role) {
                'admin' => 'admin',
                'coach', 'head_coach', 'team_coach', 'health_coach' => 'coach',
                'athlete' => 'athlete',
                'parent' => 'parent',
                default => 'default',
            };
            $initial = strtoupper(mb_substr($u['first_name'] ?? '?', 0, 1));
            $fullName = htmlspecialchars(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            $email = htmlspecialchars($u['email'] ?? '');
            $isActive = (int)($u['is_active'] ?? 0);
        ?>
        <a href="?page=user_permissions&user_id=<?= (int)$u['id'] ?>" class="m-user-card" data-name="<?= strtolower($fullName) ?>" data-email="<?= strtolower($email) ?>">
            <div class="m-user-avatar"><?= $initial ?></div>
            <div class="m-user-body">
                <div class="m-user-name"><?= $fullName ?></div>
                <div class="m-user-email"><?= $email ?></div>
            </div>
            <div class="m-user-badges">
                <span class="m-user-role m-user-role-<?= $roleClass ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $role))) ?></span>
                <span class="m-user-active m-user-active-<?= $isActive ? 'yes' : 'no' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>

<script>
function filterUsers() {
    var q = document.getElementById('mUserSearch').value.toLowerCase();
    var cards = document.querySelectorAll('.m-user-card');
    cards.forEach(function(card) {
        var name = card.getAttribute('data-name') || '';
        var email = card.getAttribute('data-email') || '';
        card.style.display = (name.indexOf(q) !== -1 || email.indexOf(q) !== -1) ? '' : 'none';
    });
}
</script>
