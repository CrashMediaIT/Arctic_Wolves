<!-- Company Directory View -->
<?php
require_once __DIR__ . '/../lib/image_helper.php';
/**
 * Company Directory
 * Searchable directory of all staff members and custom entries (rooms, partner contacts).
 * Columns: Name, Title, DID, Extension, Email.
 * Admins can add non-user entries (rooms, shared lines, partner contacts) to the directory.
 * Access restricted to staff roles: Admin, Coach, Health Coach, Front Desk, HR, Accounting.
 */

// Permission check - staff only
if (!$isStaff) {
    echo '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Access denied. Company Directory is available to staff members only.</div>';
    return;
}

$current_user_id = $_SESSION['user_id'] ?? null;

// Fetch all verified staff for the company directory
$directory_staff = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.job_title,
               u.sip_extension, u.sip_did, u.profile_image
        FROM users u
        WHERE u.is_verified = 1
        ORDER BY u.first_name ASC, u.last_name ASC
    ");
    $directory_staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $directory_staff = decryptUserRows($directory_staff);
} catch (PDOException $e) {
    error_log("Company directory fetch error: " . $e->getMessage());
}

// Fetch custom directory entries (rooms, partner contacts) - admin managed
$custom_entries = [];
try {
    $stmt = $pdo->query("SELECT * FROM phone_directory_entries ORDER BY display_name ASC");
    $custom_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist yet
}
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-address-book"></i> Company Directory</h1>
        <p class="page-description">Search and browse the staff directory</p>
    </div>
</div>

<!-- Company Directory -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-address-book"></i> Staff Directory</h3>
        <span class="header-badge"><?php echo count($directory_staff) + count($custom_entries); ?> entries</span>
    </div>
    <div class="card-body">
        <!-- Search Bar -->
        <div style="margin-bottom: 16px;">
            <div style="position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                <input type="text" id="directory-search" class="form-input" placeholder="Search by name, title, extension, or email..."
                       style="padding-left: 40px; font-size: 15px;" oninput="filterDirectory()">
            </div>
        </div>

        <?php if (count($directory_staff) > 0 || count($custom_entries) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table enhanced-table" id="directory-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Title</th>
                            <th>DID</th>
                            <th>Extension</th>
                            <th>Email</th>
                            <?php if ($isAdmin): ?><th>Action</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($directory_staff as $staff): ?>
                            <?php if ($staff['id'] == $current_user_id) continue; ?>
                            <tr class="directory-row">
                                <td>
                                    <div class="user-cell">
                                        <?php
                                        $profile_img = resolveRustfsUrl($pdo, $staff['profile_image'] ?? '');
                                        $is_valid_image = !empty($profile_img) && (preg_match('#^https?://#', $profile_img) || strpos($profile_img, 'api/media.php') !== false);
                                        ?>
                                        <?php if ($is_valid_image): ?>
                                            <img src="<?php echo htmlspecialchars($profile_img); ?>" alt="Profile" class="user-avatar-img" style="width: 32px; height: 32px; border-radius: 50%;">
                                        <?php else: ?>
                                            <div class="user-avatar" style="width: 32px; height: 32px; font-size: 12px;">
                                                <?php echo htmlspecialchars(strtoupper(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1))); ?>
                                            </div>
                                        <?php endif; ?>
                                        <span class="user-name"><?php echo htmlspecialchars(($staff['first_name'] ?? '') . ' ' . ($staff['last_name'] ?? '')); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($staff['job_title'] ?? ucfirst(str_replace('_', ' ', $staff['role']))); ?></td>
                                <td>
                                    <?php if (!empty($staff['sip_did'])): ?>
                                        <?php echo htmlspecialchars(formatPhone($staff['sip_did'])); ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($staff['sip_extension'])): ?>
                                        <span class="badge" style="background: var(--primary); color: #fff; padding: 2px 8px; border-radius: 4px;">
                                            <?php echo htmlspecialchars(formatPhone($staff['sip_extension'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($staff['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($staff['email']); ?>" style="color: var(--primary);"><?php echo htmlspecialchars($staff['email']); ?></a>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($isAdmin): ?><td></td><?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ($custom_entries as $entry): ?>
                            <tr class="directory-row">
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar" style="width: 32px; height: 32px; font-size: 12px; background: var(--warning, #f59e0b);">
                                            <i class="fas fa-<?php
                                                switch($entry['entry_type']) {
                                                    case 'room': echo 'door-open'; break;
                                                    case 'shared': echo 'users'; break;
                                                    case 'external': echo 'external-link-alt'; break;
                                                    default: echo 'phone-alt'; break;
                                                }
                                            ?>" style="font-size: 14px;"></i>
                                        </div>
                                        <span class="user-name"><?php echo htmlspecialchars($entry['display_name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--warning, #f59e0b); color: #000; padding: 2px 8px; border-radius: 4px;">
                                        <?php echo htmlspecialchars(ucfirst($entry['entry_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($entry['did'])): ?>
                                        <?php echo htmlspecialchars(formatPhone($entry['did'])); ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($entry['extension'])): ?>
                                        <span class="badge" style="background: var(--primary); color: #fff; padding: 2px 8px; border-radius: 4px;">
                                            <?php echo htmlspecialchars(formatPhone($entry['extension'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($entry['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($entry['email']); ?>" style="color: var(--primary);"><?php echo htmlspecialchars($entry['email']); ?></a>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($isAdmin): ?>
                                <td>
                                    <button class="btn btn-danger btn-small" onclick="deleteDirectoryEntry(<?php echo intval($entry['id']); ?>, '<?php echo htmlspecialchars($entry['display_name']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p id="no-results-message" style="display: none; text-align: center; padding: 20px; color: var(--text-muted);">
                <i class="fas fa-search"></i> No matching entries found.
            </p>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-address-book"></i>
                <p>No directory entries yet. Verified staff members will appear here automatically.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- Admin: Add Directory Entry -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-plus-circle"></i> Add Directory Entry</h3>
    </div>
    <div class="card-body">
        <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 16px;">Add entries such as conference rooms, shared lines, partner contacts, or external numbers to the directory.</p>
        <form id="add-directory-entry-form">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-tag"></i> Name</label>
                    <input type="text" name="entry_name" id="entry_name" class="form-input" placeholder="e.g., Board Room, Partner Contact" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-list"></i> Type</label>
                    <select name="entry_type" id="entry_type" class="form-input">
                        <option value="room">Room</option>
                        <option value="shared">Shared Line</option>
                        <option value="external">External</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-phone-square"></i> DID</label>
                    <input type="text" name="entry_did" id="entry_did" class="form-input" placeholder="e.g., +16045551234">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-phone"></i> Extension</label>
                    <input type="text" name="entry_extension" id="entry_extension" class="form-input" placeholder="e.g., 2001">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="entry_email" id="entry_email" class="form-input" placeholder="e.g., contact@partner.com">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-sticky-note"></i> Description</label>
                    <input type="text" name="entry_description" id="entry_description" class="form-input" placeholder="Optional description">
                </div>
            </div>
            <button type="button" class="btn btn-primary" onclick="addDirectoryEntry()"><i class="fas fa-plus"></i> Add Entry</button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// Notification helper
function showNotification(message, type = 'info') {
    const alertClass = type === 'error' ? 'alert-error' : type === 'warning' ? 'alert-warning' : type === 'success' ? 'alert-success' : 'alert-info';
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert ' + alertClass;
    alertDiv.textContent = message;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '10000';
    alertDiv.style.minWidth = '300px';
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 3000);
}

// Directory search / filter
function filterDirectory() {
    const query = document.getElementById('directory-search').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.directory-row');
    let visibleCount = 0;
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const match = !query || text.includes(query);
        row.style.display = match ? '' : 'none';
        if (match) visibleCount++;
    });
    const noResults = document.getElementById('no-results-message');
    if (noResults) {
        noResults.style.display = (visibleCount === 0 && query) ? '' : 'none';
    }
}

// Directory management functions (admin only)
function addDirectoryEntry() {
    const name = document.getElementById('entry_name').value.trim();
    const extension = document.getElementById('entry_extension').value.trim();
    const did = document.getElementById('entry_did').value.trim();
    const email = document.getElementById('entry_email').value.trim();
    const type = document.getElementById('entry_type').value;
    const description = document.getElementById('entry_description').value.trim();
    const csrfToken = document.querySelector('#add-directory-entry-form [name="csrf_token"]').value;

    if (!name) {
        showNotification('Please enter a name for the directory entry', 'warning');
        return;
    }

    fetch('process_profile_update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'action=add_directory_entry&csrf_token=' + encodeURIComponent(csrfToken) +
              '&display_name=' + encodeURIComponent(name) +
              '&extension=' + encodeURIComponent(extension) +
              '&did=' + encodeURIComponent(did) +
              '&email=' + encodeURIComponent(email) +
              '&entry_type=' + encodeURIComponent(type) +
              '&description=' + encodeURIComponent(description)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification('Directory entry added', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showNotification(data.message || 'Failed to add entry', 'error');
        }
    })
    .catch(() => showNotification('Error adding directory entry', 'error'));
}

async function deleteDirectoryEntry(id, name) {
    if (!await showConfirmModal('Remove "' + name + '" from the directory?')) return;
    const csrfToken = document.querySelector('#add-directory-entry-form [name="csrf_token"]').value;

    fetch('process_profile_update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'action=delete_directory_entry&csrf_token=' + encodeURIComponent(csrfToken) + '&entry_id=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification('Directory entry removed', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showNotification(data.message || 'Failed to remove entry', 'error');
        }
    })
    .catch(() => showNotification('Error removing directory entry', 'error'));
}
</script>
