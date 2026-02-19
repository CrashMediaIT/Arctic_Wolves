<!-- Phone Directory View -->
<?php
/**
 * Phone Directory
 * Lists staff members with name, job title, extension, DID, and email.
 * Accessible from POS and HR navigation.
 */

// Permission check - admins, front desk staff, HR, and accounting can access
if (!$isAdmin && !$canAccessPOS && !$isHR && !$isAccounting) {
    echo '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Access denied.</div>';
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
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-address-book"></i> Phone Directory</h1>
        <p class="page-description">Staff contact information, extensions, and direct dial numbers</p>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value"><?php echo count($directory_users); ?></span>
            <span class="stat-label">Staff Members</span>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="filter-box">
    <div class="filter-box-header"><i class="fas fa-filter"></i> Search Directory</div>
    <div class="filter-box-content">
        <form method="GET" action="">
            <input type="hidden" name="page" value="phone_directory">
            <div class="filter-row">
                <div class="filter-field" style="grid-column: span 2;">
                    <label>Search</label>
                    <div class="search-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" name="dir_search" class="form-input" placeholder="Search by name, email, or job title..."
                               value="<?php echo htmlspecialchars($dir_search); ?>">
                    </div>
                </div>
                <div class="filter-field">
                    <label>Role</label>
                    <select name="dir_role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo $dir_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="coach" <?php echo $dir_role === 'coach' ? 'selected' : ''; ?>>Coach</option>
                        <option value="health_coach" <?php echo $dir_role === 'health_coach' ? 'selected' : ''; ?>>Health Coach</option>
                        <option value="team_coach" <?php echo $dir_role === 'team_coach' ? 'selected' : ''; ?>>Team Coach</option>
                        <option value="front_desk_staff" <?php echo $dir_role === 'front_desk_staff' ? 'selected' : ''; ?>>Front Desk</option>
                        <option value="hr" <?php echo $dir_role === 'hr' ? 'selected' : ''; ?>>HR</option>
                        <option value="accounting" <?php echo $dir_role === 'accounting' ? 'selected' : ''; ?>>Accounting</option>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                <a href="?page=phone_directory" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Directory Table -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-phone"></i> Staff Directory</h3>
        <span class="header-badge"><?php echo count($directory_users); ?> entries</span>
    </div>
    <div class="card-body">
        <?php if (count($directory_users) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table enhanced-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Job Title</th>
                            <th>Role</th>
                            <th>Extension</th>
                            <th>DID</th>
                            <th>Phone</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($directory_users as $du): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <?php
                                        $profile_img = $du['profile_image'] ?? '';
                                        $is_valid_image = !empty($profile_img) &&
                                                          strpos($profile_img, 'uploads/profiles/') === 0 &&
                                                          preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $profile_img) &&
                                                          file_exists($profile_img);
                                        ?>
                                        <?php if ($is_valid_image): ?>
                                            <img src="<?php echo htmlspecialchars($profile_img); ?>" alt="Profile" class="user-avatar-img" style="width: 32px; height: 32px; border-radius: 50%;">
                                        <?php else: ?>
                                            <div class="user-avatar" style="width: 32px; height: 32px; font-size: 12px;">
                                                <?php echo htmlspecialchars(strtoupper(substr($du['first_name'], 0, 1) . substr($du['last_name'], 0, 1))); ?>
                                            </div>
                                        <?php endif; ?>
                                        <span class="user-name"><?php echo htmlspecialchars(($du['first_name'] ?? '') . ' ' . ($du['last_name'] ?? '')); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($du['job_title'] ?? '—'); ?></td>
                                <td>
                                    <span class="role-badge <?php echo $du['role']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $du['role'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($du['sip_extension'])): ?>
                                        <span class="badge" style="background: var(--primary); color: #fff; padding: 2px 8px; border-radius: 4px;">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($du['sip_extension']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($du['sip_did'])): ?>
                                        <span><?php echo htmlspecialchars($du['sip_did']); ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($du['phone'] ?? '—'); ?></td>
                                <td><a href="mailto:<?php echo htmlspecialchars($du['email']); ?>"><?php echo htmlspecialchars($du['email']); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-address-book"></i>
                <p>No staff members found matching your criteria.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
