<?php
/**
 * PWA Phone Directory - Mobile-native staff contact list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */
require_once __DIR__ . '/../../lib/image_helper.php';

// Permission check - admins, front desk staff, HR, and accounting can access
if (!$isAdmin && !$canAccessPOS && !$isHR && !$isAccounting) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Access denied</div>';
    return;
}

// Search filter
$dir_search = $_GET['dir_search'] ?? '';
$dir_role = $_GET['dir_role'] ?? '';

// Fetch staff users with phone/SIP info
try {
    $where = ["u.role IN ('admin', 'coach', 'health_coach', 'team_coach', 'front_desk_staff', 'hr', 'accounting')"];
    $params = [];

    if (!empty($dir_search)) {
        $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.job_title LIKE ?)";
        $search_param = "%$dir_search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    if (!empty($dir_role)) {
        $where[] = "u.role = ?";
        $params[] = $dir_role;
    }

    $where_clause = 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.role, u.job_title,
               u.sip_extension, u.sip_did, u.sip_username, u.profile_image, u.is_verified
        FROM users u
        $where_clause
        ORDER BY u.first_name ASC, u.last_name ASC
    ");
    $stmt->execute($params);
    $directory_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $directory_users = decryptUserRows($directory_users);
} catch (PDOException $e) {
    error_log("Phone directory fetch error: " . $e->getMessage());
    $directory_users = [];
}

$roles_list = [
    '' => 'All Roles',
    'admin' => 'Admin',
    'coach' => 'Coach',
    'health_coach' => 'Health Coach',
    'team_coach' => 'Team Coach',
    'front_desk_staff' => 'Front Desk',
    'hr' => 'HR',
    'accounting' => 'Accounting',
];
?>
<style>
.m-dir { padding: 16px; font-family: Inter, sans-serif; padding-bottom: 100px; }
.m-dir-header { margin-bottom: 16px; }
.m-dir-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-dir-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-dir-search-wrap { position: relative; margin-bottom: 12px; }
.m-dir-search-wrap i {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: #6B6B7B; font-size: 14px;
}
.m-dir-search {
    width: 100%; padding: 12px 16px 12px 40px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box; min-height: 44px;
}
.m-dir-search::placeholder { color: #6B6B7B; }
.m-dir-filters { display: flex; gap: 8px; overflow-x: auto; margin-bottom: 16px; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
.m-dir-filters::-webkit-scrollbar { display: none; }
.m-dir-chip {
    flex-shrink: 0; padding: 8px 14px; border-radius: 20px; font-size: 12px;
    font-weight: 600; border: 1px solid #2D2D3F; background: #16161F; color: #A8A8B8;
    text-decoration: none; white-space: nowrap; min-height: 36px;
    display: inline-flex; align-items: center;
}
.m-dir-chip.active { background: rgba(139,92,246,0.15); color: #8B5CF6; border-color: #8B5CF6; }
.m-dir-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-dir-card-top { display: flex; align-items: center; gap: 12px; }
.m-dir-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700; color: #fff; flex-shrink: 0;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
}
.m-dir-avatar img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
.m-dir-info { flex: 1; min-width: 0; }
.m-dir-name { font-size: 14px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-dir-jobtitle { font-size: 12px; color: #A8A8B8; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-dir-role {
    font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600;
    display: inline-block; flex-shrink: 0;
}
.m-dir-role-admin { background: rgba(139,92,246,0.15); color: #8B5CF6; }
.m-dir-role-coach { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-dir-role-front_desk_staff { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-dir-role-hr { background: rgba(236,72,153,0.15); color: #EC4899; }
.m-dir-role-accounting { background: rgba(16,185,129,0.15); color: #10B981; }
.m-dir-role-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-dir-details { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #2D2D3F; }
.m-dir-detail {
    display: flex; align-items: center; gap: 6px; font-size: 12px; color: #A8A8B8;
    min-height: 32px;
}
.m-dir-detail i { font-size: 12px; width: 14px; text-align: center; color: #6B6B7B; }
.m-dir-detail a {
    color: #8B5CF6; text-decoration: none; word-break: break-all;
}
.m-dir-detail a:active { opacity: 0.7; }
.m-dir-empty { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-dir-empty i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-dir">
    <div class="m-dir-header">
        <h2 class="m-dir-title"><i class="fas fa-address-book"></i> Phone Directory</h2>
        <p class="m-dir-sub"><?= count($directory_users) ?> staff member<?= count($directory_users) !== 1 ? 's' : '' ?></p>
    </div>

    <form method="GET" action="" id="mDirForm">
        <input type="hidden" name="page" value="phone_directory">
        <input type="hidden" name="dir_role" id="mDirRoleInput" value="<?= htmlspecialchars($dir_role) ?>">

        <div class="m-dir-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" class="m-dir-search" name="dir_search" placeholder="Search name, email, job title..."
                   value="<?= htmlspecialchars($dir_search) ?>" id="mDirSearch">
        </div>

        <div class="m-dir-filters">
            <?php foreach ($roles_list as $rval => $rlabel): ?>
                <a href="#" class="m-dir-chip <?= $dir_role === $rval ? 'active' : '' ?>"
                   data-role="<?= htmlspecialchars($rval) ?>"
                   onclick="selectRole(event, this)"><?= htmlspecialchars($rlabel) ?></a>
            <?php endforeach; ?>
        </div>
    </form>

    <div id="mDirList">
    <?php if (empty($directory_users)): ?>
        <div class="m-dir-empty">
            <i class="fas fa-address-book"></i>
            No staff members found
        </div>
    <?php else: ?>
        <?php foreach ($directory_users as $du):
            $role = strtolower($du['role'] ?? 'default');
            $roleClass = match($role) {
                'admin' => 'admin',
                'coach', 'head_coach', 'team_coach', 'health_coach' => 'coach',
                'front_desk_staff' => 'front_desk_staff',
                'hr' => 'hr',
                'accounting' => 'accounting',
                default => 'default',
            };
            $fullName = htmlspecialchars(($du['first_name'] ?? '') . ' ' . ($du['last_name'] ?? ''));
            $initials = htmlspecialchars(strtoupper(substr($du['first_name'] ?? '', 0, 1) . substr($du['last_name'] ?? '', 0, 1)));
            $email = htmlspecialchars($du['email'] ?? '');
            $phone = formatPhone($du['phone'] ?? '') ?: '';
            $phoneRaw = preg_replace('/[^0-9+]/', '', $du['phone'] ?? '');
            $extension = !empty($du['sip_extension']) ? htmlspecialchars(formatPhone($du['sip_extension'])) : '';
            $did = !empty($du['sip_did']) ? htmlspecialchars(formatPhone($du['sip_did'])) : '';
            $jobTitle = htmlspecialchars($du['job_title'] ?? '');
            $profile_img = resolveRustfsUrl($pdo, $du['profile_image'] ?? '');
            $is_valid_image = !empty($profile_img) && (preg_match('#^https?://#', $profile_img) || strpos($profile_img, 'api/media.php') !== false);
        ?>
        <div class="m-dir-card" data-name="<?= strtolower($fullName) ?>" data-email="<?= strtolower($email) ?>">
            <div class="m-dir-card-top">
                <div class="m-dir-avatar">
                    <?php if ($is_valid_image): ?>
                        <img src="<?= htmlspecialchars($profile_img) ?>" alt="<?= $fullName ?>">
                    <?php else: ?>
                        <?= $initials ?>
                    <?php endif; ?>
                </div>
                <div class="m-dir-info">
                    <div class="m-dir-name"><?= $fullName ?></div>
                    <?php if ($jobTitle): ?>
                        <div class="m-dir-jobtitle"><?= $jobTitle ?></div>
                    <?php endif; ?>
                </div>
                <span class="m-dir-role m-dir-role-<?= $roleClass ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $role))) ?></span>
            </div>
            <div class="m-dir-details">
                <?php if ($extension): ?>
                    <div class="m-dir-detail"><i class="fas fa-hashtag"></i> Ext <?= $extension ?></div>
                <?php endif; ?>
                <?php if ($did): ?>
                    <div class="m-dir-detail"><i class="fas fa-phone-office"></i> <?= $did ?></div>
                <?php endif; ?>
                <?php if ($phone): ?>
                    <div class="m-dir-detail"><i class="fas fa-mobile-alt"></i> <a href="tel:<?= htmlspecialchars($phoneRaw) ?>"><?= htmlspecialchars($phone) ?></a></div>
                <?php endif; ?>
                <?php if ($email): ?>
                    <div class="m-dir-detail"><i class="fas fa-envelope"></i> <a href="mailto:<?= $email ?>"><?= $email ?></a></div>
                <?php endif; ?>
                <?php if (!$extension && !$did && !$phone && !$email): ?>
                    <div class="m-dir-detail" style="color:#6B6B7B;">No contact info available</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>

<script>
function selectRole(e, el) {
    e.preventDefault();
    document.getElementById('mDirRoleInput').value = el.getAttribute('data-role');
    document.getElementById('mDirForm').submit();
}

(function() {
    var timeout;
    var searchInput = document.getElementById('mDirSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            var q = this.value.toLowerCase();
            timeout = setTimeout(function() {
                var cards = document.querySelectorAll('.m-dir-card');
                cards.forEach(function(card) {
                    var name = card.getAttribute('data-name') || '';
                    var email = card.getAttribute('data-email') || '';
                    card.style.display = (name.indexOf(q) !== -1 || email.indexOf(q) !== -1) ? '' : 'none';
                });
            }, 200);
        });
    }
})();
</script>
