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
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, role, is_active, is_verified, created_at FROM users ORDER BY created_at DESC LIMIT 50");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $users = decryptUserRows($users);
} catch (PDOException $e) { $users = []; }
?>
<style>
.m-users { padding: 16px; font-family: Inter, sans-serif; padding-bottom: 100px; }
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
.m-user-actions { display: flex; gap: 6px; margin-top: 8px; }
.m-user-actions button {
    font-size: 11px; padding: 5px 10px; border-radius: 6px; border: none;
    font-weight: 600; cursor: pointer; min-height: 30px;
}
.m-user-btn-edit { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-user-btn-enable { background: rgba(16,185,129,0.15); color: #10B981; }
.m-user-btn-disable { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
.m-fab {
    position: fixed; bottom: 60px; right: 20px; z-index: 999;
    width: 56px; height: 56px; border-radius: 50%; border: none;
    background: #6B46C1; color: #fff; font-size: 22px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(107,70,193,0.4); cursor: pointer;
}
.m-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000;
    display: none;
}
.m-overlay.active { display: block; }
.m-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1001;
    background: #16161F; border-radius: 16px 16px 0 0;
    max-height: 85vh; overflow-y: auto; padding: 20px 16px 32px;
    transform: translateY(100%); transition: transform 0.3s ease;
}
.m-sheet.active { transform: translateY(0); }
.m-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-sheet-close {
    position: absolute; top: 16px; right: 16px; background: none; border: none;
    color: #A8A8B8; font-size: 18px; cursor: pointer;
}
.m-field { margin-bottom: 14px; }
.m-field label { display: block; font-size: 12px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; }
.m-field input, .m-field select {
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; padding: 12px; min-height: 44px; width: 100%;
    box-sizing: border-box; font-size: 14px; font-family: Inter, sans-serif;
}
.m-field select { appearance: auto; }
.m-submit {
    background: #6B46C1; color: #fff; border: none; border-radius: 10px;
    min-height: 44px; font-weight: 600; width: 100%; font-size: 15px;
    cursor: pointer; margin-top: 8px;
}
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
            $isActive = (int)($u['is_active'] ?? $u['is_verified'] ?? 0);
        ?>
        <div class="m-user-card" data-name="<?= strtolower($fullName) ?>" data-email="<?= strtolower($email) ?>" data-id="<?= (int)$u['id'] ?>">
            <a href="?page=user_permissions&user_id=<?= (int)$u['id'] ?>" style="display:flex;align-items:center;gap:12px;text-decoration:none;flex:1;min-width:0;">
                <div class="m-user-avatar"><?= $initial ?></div>
                <div class="m-user-body">
                    <div class="m-user-name"><?= $fullName ?></div>
                    <div class="m-user-email"><?= $email ?></div>
                    <div class="m-user-actions">
                        <button type="button" class="m-user-btn-edit" onclick="event.preventDefault();event.stopPropagation();openEditUser(<?= (int)$u['id'] ?>, <?= htmlspecialchars(json_encode($u['first_name'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($u['last_name'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($u['email'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($u['role'] ?? ''), ENT_QUOTES) ?>)"><i class="fas fa-edit"></i> Edit</button>
                        <button type="button" class="<?= $isActive ? 'm-user-btn-disable' : 'm-user-btn-enable' ?>" onclick="event.preventDefault();event.stopPropagation();toggleUserStatus(<?= (int)$u['id'] ?>, this)"><i class="fas <?= $isActive ? 'fa-ban' : 'fa-check' ?>"></i> <?= $isActive ? 'Disable' : 'Enable' ?></button>
                    </div>
                </div>
            </a>
            <div class="m-user-badges">
                <span class="m-user-role m-user-role-<?= $roleClass ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $role))) ?></span>
                <span class="m-user-active m-user-active-<?= $isActive ? 'yes' : 'no' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>

<!-- FAB: Create User -->
<button class="m-fab" onclick="openCreateUser()" aria-label="Create User"><i class="fas fa-plus"></i></button>

<!-- Overlay -->
<div class="m-overlay" id="mUserOverlay" onclick="closeUserSheet()"></div>

<!-- Create User Sheet -->
<div class="m-sheet" id="mCreateUserSheet">
    <button class="m-sheet-close" onclick="closeUserSheet()"><i class="fas fa-times"></i></button>
    <h3 class="m-sheet-title">Create User</h3>
    <form method="POST" action="process_admin_action.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="create_user">
        <div class="m-field"><label>First Name *</label><input type="text" name="first_name" required></div>
        <div class="m-field"><label>Last Name *</label><input type="text" name="last_name" required></div>
        <div class="m-field"><label>Email *</label><input type="email" name="email" required></div>
        <div class="m-field"><label>Phone</label><input type="tel" name="phone"></div>
        <div class="m-field">
            <label>Role *</label>
            <select name="role" required>
                <option value="">Select Role</option>
                <option value="admin">Admin</option>
                <option value="coach">Coach</option>
                <option value="health_coach">Health Coach</option>
                <option value="team_coach">Team Coach</option>
                <option value="athlete">Athlete</option>
                <option value="parent">Parent</option>
            </select>
        </div>
        <div class="m-field"><label>Password *</label><input type="password" name="password" required minlength="6"></div>
        <button type="submit" class="m-submit">Create User</button>
    </form>
</div>

<!-- Edit User Sheet -->
<div class="m-sheet" id="mEditUserSheet">
    <button class="m-sheet-close" onclick="closeUserSheet()"><i class="fas fa-times"></i></button>
    <h3 class="m-sheet-title">Edit User</h3>
    <form method="POST" action="process_admin_action.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="update_user">
        <input type="hidden" name="user_id" id="mEditUserId">
        <div class="m-field"><label>First Name *</label><input type="text" name="first_name" id="mEditFirstName" required></div>
        <div class="m-field"><label>Last Name *</label><input type="text" name="last_name" id="mEditLastName" required></div>
        <div class="m-field"><label>Email *</label><input type="email" name="email" id="mEditEmail" required></div>
        <div class="m-field">
            <label>Role *</label>
            <select name="role" id="mEditRole" required>
                <option value="admin">Admin</option>
                <option value="coach">Coach</option>
                <option value="health_coach">Health Coach</option>
                <option value="team_coach">Team Coach</option>
                <option value="athlete">Athlete</option>
                <option value="parent">Parent</option>
            </select>
        </div>
        <button type="submit" class="m-submit">Save Changes</button>
    </form>
</div>

<script>
var mCsrfToken = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>';

function filterUsers() {
    var q = document.getElementById('mUserSearch').value.toLowerCase();
    var cards = document.querySelectorAll('.m-user-card');
    cards.forEach(function(card) {
        var name = card.getAttribute('data-name') || '';
        var email = card.getAttribute('data-email') || '';
        card.style.display = (name.indexOf(q) !== -1 || email.indexOf(q) !== -1) ? '' : 'none';
    });
}

function openCreateUser() {
    document.getElementById('mUserOverlay').classList.add('active');
    document.getElementById('mCreateUserSheet').classList.add('active');
    document.getElementById('mEditUserSheet').classList.remove('active');
}

function openEditUser(id, first, last, email, role) {
    document.getElementById('mEditUserId').value = id;
    document.getElementById('mEditFirstName').value = first;
    document.getElementById('mEditLastName').value = last;
    document.getElementById('mEditEmail').value = email;
    document.getElementById('mEditRole').value = role;
    document.getElementById('mUserOverlay').classList.add('active');
    document.getElementById('mEditUserSheet').classList.add('active');
    document.getElementById('mCreateUserSheet').classList.remove('active');
}

function closeUserSheet() {
    document.getElementById('mUserOverlay').classList.remove('active');
    document.getElementById('mCreateUserSheet').classList.remove('active');
    document.getElementById('mEditUserSheet').classList.remove('active');
}

async function toggleUserStatus(userId, btn) {
    if (!await showConfirmModal('Toggle this user\'s status?')) return;
    fetch('process_admin_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=toggle_user_status&id=' + userId + '&csrf_token=' + encodeURIComponent(mCsrfToken)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) { persistToast(data.message || 'Operation completed successfully', 'success'); location.reload(); }
        else { showToast(data.message || 'Failed to toggle status', 'error'); }
    })
    .catch(function() { showToast('Request failed', 'error'); });
}
</script>
