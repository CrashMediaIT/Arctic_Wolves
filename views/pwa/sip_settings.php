<?php
/**
 * PWA Company Directory - Mobile-native staff directory
 * Mobile-optimized version of views/sip_settings.php
 * Searchable directory of all staff members and custom entries (rooms, partner contacts).
 * Admins can add/delete non-user entries.
 */
require_once __DIR__ . '/../../lib/image_helper.php';

// Permission check - staff only
if (!$isStaff) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Access denied. Company Directory is available to staff members only.</div>';
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

$total_entries = count($directory_staff) + count($custom_entries);
?>
<style>
.m-sip { padding: 16px; font-family: Inter, sans-serif; padding-bottom: 100px; }
.m-sip-header { margin-bottom: 16px; }
.m-sip-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-sip-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-sip-search-wrap { position: relative; margin-bottom: 16px; }
.m-sip-search-wrap i {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: #6B6B7B; font-size: 14px;
}
.m-sip-search {
    width: 100%; padding: 12px 16px 12px 40px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box; min-height: 44px;
}
.m-sip-search::placeholder { color: #6B6B7B; }
.m-sip-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-sip-card-top { display: flex; align-items: center; gap: 12px; }
.m-sip-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700; color: #fff; flex-shrink: 0;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
}
.m-sip-avatar img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
.m-sip-avatar-custom {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; font-weight: 700; color: #000; flex-shrink: 0;
    background: #F59E0B;
}
.m-sip-info { flex: 1; min-width: 0; }
.m-sip-name { font-size: 14px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-sip-jobtitle { font-size: 12px; color: #A8A8B8; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-sip-type-badge {
    font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600;
    display: inline-block; flex-shrink: 0;
    background: rgba(245,158,11,0.15); color: #F59E0B;
}
.m-sip-details { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #2D2D3F; }
.m-sip-detail {
    display: flex; align-items: center; gap: 6px; font-size: 12px; color: #A8A8B8;
    min-height: 32px;
}
.m-sip-detail i { font-size: 12px; width: 14px; text-align: center; color: #6B6B7B; }
.m-sip-detail a { color: #8B5CF6; text-decoration: none; word-break: break-all; }
.m-sip-detail a:active { opacity: 0.7; }
.m-sip-ext-badge {
    background: rgba(139,92,246,0.15); color: #8B5CF6;
    padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;
}
.m-sip-delete-btn {
    background: rgba(239,68,68,0.15); color: #EF4444; border: none;
    padding: 8px 12px; border-radius: 8px; font-size: 12px; font-weight: 600;
    cursor: pointer; min-height: 36px; display: inline-flex; align-items: center; gap: 4px;
    font-family: Inter, sans-serif;
}
.m-sip-delete-btn:active { opacity: 0.7; }
.m-sip-empty { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-sip-empty i { font-size: 28px; display: block; margin-bottom: 10px; }
.m-sip-no-results { display: none; text-align: center; padding: 24px 20px; color: #6B6B7B; font-size: 13px; }
.m-sip-section-label {
    font-size: 11px; font-weight: 700; color: #6B6B7B; text-transform: uppercase;
    letter-spacing: 0.5px; margin: 16px 0 8px; padding-left: 4px;
}

/* Admin add form */
.m-sip-form-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; margin-top: 16px;
}
.m-sip-form-title { font-size: 14px; font-weight: 700; color: #fff; margin: 0 0 4px; }
.m-sip-form-desc { font-size: 12px; color: #6B6B7B; margin: 0 0 14px; }
.m-sip-form-group { margin-bottom: 12px; }
.m-sip-form-label { display: block; font-size: 12px; font-weight: 600; color: #A8A8B8; margin-bottom: 4px; }
.m-sip-form-input {
    width: 100%; padding: 10px 12px; background: #0D0D14; border: 1px solid #2D2D3F;
    border-radius: 8px; color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box; min-height: 44px;
}
.m-sip-form-input::placeholder { color: #6B6B7B; }
.m-sip-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.m-sip-form-submit {
    width: 100%; padding: 12px; background: #8B5CF6; color: #fff; border: none;
    border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer;
    min-height: 44px; font-family: Inter, sans-serif; margin-top: 4px;
}
.m-sip-form-submit:active { opacity: 0.8; }
</style>

<div class="m-sip">
    <div class="m-sip-header">
        <h2 class="m-sip-title"><i class="fas fa-address-book"></i> Company Directory</h2>
        <p class="m-sip-sub"><?= $total_entries ?> entr<?= $total_entries !== 1 ? 'ies' : 'y' ?></p>
    </div>

    <!-- Search Bar -->
    <div class="m-sip-search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" class="m-sip-search" id="m-sip-directory-search"
               placeholder="Search name, title, extension, email..." oninput="filterDirectory()">
    </div>

    <div id="m-sip-directory-list">
    <?php if ($total_entries === 0): ?>
        <div class="m-sip-empty">
            <i class="fas fa-address-book"></i>
            No directory entries yet. Verified staff members will appear here automatically.
        </div>
    <?php else: ?>

        <?php if (count($directory_staff) > 0): ?>
        <div class="m-sip-section-label">Staff</div>
        <?php foreach ($directory_staff as $staff):
            if ($staff['id'] == $current_user_id) continue;
            $fullName = htmlspecialchars(($staff['first_name'] ?? '') . ' ' . ($staff['last_name'] ?? ''));
            $initials = htmlspecialchars(strtoupper(substr($staff['first_name'] ?? '', 0, 1) . substr($staff['last_name'] ?? '', 0, 1)));
            $jobTitle = htmlspecialchars($staff['job_title'] ?? ucfirst(str_replace('_', ' ', $staff['role'])));
            $email = htmlspecialchars($staff['email'] ?? '');
            $extension = !empty($staff['sip_extension']) ? htmlspecialchars(formatPhone($staff['sip_extension'])) : '';
            $did = !empty($staff['sip_did']) ? htmlspecialchars(formatPhone($staff['sip_did'])) : '';
            $didRaw = preg_replace('/[^0-9+]/', '', $staff['sip_did'] ?? '');
            $profile_img = resolveRustfsUrl($pdo, $staff['profile_image'] ?? '');
            $is_valid_image = !empty($profile_img) && (preg_match('#^https?://#', $profile_img) || strpos($profile_img, 'api/media.php') !== false);
        ?>
        <div class="m-sip-card directory-row" data-search="<?= strtolower($fullName . ' ' . $jobTitle . ' ' . $extension . ' ' . $did . ' ' . $email) ?>">
            <div class="m-sip-card-top">
                <div class="m-sip-avatar">
                    <?php if ($is_valid_image): ?>
                        <img src="<?= htmlspecialchars($profile_img) ?>" alt="<?= $fullName ?>">
                    <?php else: ?>
                        <?= $initials ?>
                    <?php endif; ?>
                </div>
                <div class="m-sip-info">
                    <div class="m-sip-name"><?= $fullName ?></div>
                    <div class="m-sip-jobtitle"><?= $jobTitle ?></div>
                </div>
                <?php if ($extension): ?>
                    <span class="m-sip-ext-badge">Ext <?= $extension ?></span>
                <?php endif; ?>
            </div>
            <div class="m-sip-details">
                <?php if ($did): ?>
                    <div class="m-sip-detail"><i class="fas fa-phone"></i> <a href="tel:<?= htmlspecialchars($didRaw) ?>"><?= $did ?></a></div>
                <?php endif; ?>
                <?php if ($email): ?>
                    <div class="m-sip-detail"><i class="fas fa-envelope"></i> <a href="mailto:<?= $email ?>"><?= $email ?></a></div>
                <?php endif; ?>
                <?php if (!$did && !$email && !$extension): ?>
                    <div class="m-sip-detail" style="color:#6B6B7B;">No contact info available</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if (count($custom_entries) > 0): ?>
        <div class="m-sip-section-label">Other Entries</div>
        <?php foreach ($custom_entries as $entry):
            $entryName = htmlspecialchars($entry['display_name']);
            $entryType = htmlspecialchars(ucfirst($entry['entry_type']));
            $entryEmail = htmlspecialchars($entry['email'] ?? '');
            $entryExt = !empty($entry['extension']) ? htmlspecialchars(formatPhone($entry['extension'])) : '';
            $entryDid = !empty($entry['did']) ? htmlspecialchars(formatPhone($entry['did'])) : '';
            $entryDidRaw = preg_replace('/[^0-9+]/', '', $entry['did'] ?? '');
            $entryIcon = match($entry['entry_type']) {
                'room' => 'door-open',
                'shared' => 'users',
                'external' => 'external-link-alt',
                default => 'phone-alt',
            };
        ?>
        <div class="m-sip-card directory-row" data-search="<?= strtolower($entryName . ' ' . $entryType . ' ' . $entryExt . ' ' . $entryDid . ' ' . $entryEmail) ?>">
            <div class="m-sip-card-top">
                <div class="m-sip-avatar-custom">
                    <i class="fas fa-<?= $entryIcon ?>"></i>
                </div>
                <div class="m-sip-info">
                    <div class="m-sip-name"><?= $entryName ?></div>
                </div>
                <span class="m-sip-type-badge"><?= $entryType ?></span>
            </div>
            <div class="m-sip-details">
                <?php if ($entryExt): ?>
                    <div class="m-sip-detail"><i class="fas fa-hashtag"></i> <span class="m-sip-ext-badge">Ext <?= $entryExt ?></span></div>
                <?php endif; ?>
                <?php if ($entryDid): ?>
                    <div class="m-sip-detail"><i class="fas fa-phone"></i> <a href="tel:<?= htmlspecialchars($entryDidRaw) ?>"><?= $entryDid ?></a></div>
                <?php endif; ?>
                <?php if ($entryEmail): ?>
                    <div class="m-sip-detail"><i class="fas fa-envelope"></i> <a href="mailto:<?= $entryEmail ?>"><?= $entryEmail ?></a></div>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                    <div class="m-sip-detail" style="margin-left:auto;">
                        <button class="m-sip-delete-btn" onclick="deleteDirectoryEntry(<?= intval($entry['id']) ?>, '<?= htmlspecialchars(addslashes($entry['display_name'])) ?>')">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                <?php endif; ?>
                <?php if (!$entryExt && !$entryDid && !$entryEmail): ?>
                    <div class="m-sip-detail" style="color:#6B6B7B;">No contact info available</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>
    </div>

    <div class="m-sip-no-results" id="m-sip-no-results">
        <i class="fas fa-search" style="font-size:24px;display:block;margin-bottom:8px;"></i>
        No matching entries found.
    </div>

    <?php if ($isAdmin): ?>
    <!-- Admin: Add Directory Entry -->
    <div class="m-sip-section-label" style="margin-top:24px;">Admin</div>
    <div class="m-sip-form-card">
        <h3 class="m-sip-form-title"><i class="fas fa-plus-circle"></i> Add Directory Entry</h3>
        <p class="m-sip-form-desc">Add rooms, shared lines, partner contacts, or external numbers.</p>
        <form id="m-sip-add-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

            <div class="m-sip-form-row">
                <div class="m-sip-form-group">
                    <label class="m-sip-form-label">Name *</label>
                    <input type="text" name="entry_name" id="m-sip-entry-name" class="m-sip-form-input" placeholder="Board Room" required>
                </div>
                <div class="m-sip-form-group">
                    <label class="m-sip-form-label">Type</label>
                    <select name="entry_type" id="m-sip-entry-type" class="m-sip-form-input">
                        <option value="room">Room</option>
                        <option value="shared">Shared Line</option>
                        <option value="external">External</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            <div class="m-sip-form-row">
                <div class="m-sip-form-group">
                    <label class="m-sip-form-label">DID</label>
                    <input type="text" name="entry_did" id="m-sip-entry-did" class="m-sip-form-input" placeholder="+16045551234">
                </div>
                <div class="m-sip-form-group">
                    <label class="m-sip-form-label">Extension</label>
                    <input type="text" name="entry_extension" id="m-sip-entry-ext" class="m-sip-form-input" placeholder="2001">
                </div>
            </div>
            <div class="m-sip-form-group">
                <label class="m-sip-form-label">Email</label>
                <input type="email" name="entry_email" id="m-sip-entry-email" class="m-sip-form-input" placeholder="contact@partner.com">
            </div>
            <div class="m-sip-form-group">
                <label class="m-sip-form-label">Description</label>
                <input type="text" name="entry_description" id="m-sip-entry-desc" class="m-sip-form-input" placeholder="Optional description">
            </div>
            <button type="button" class="m-sip-form-submit" onclick="addDirectoryEntry()">
                <i class="fas fa-plus"></i> Add Entry
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
function showNotification(message, type) {
    type = type || 'info';
    var colors = { error: '#EF4444', success: '#10B981', warning: '#F59E0B', info: '#8B5CF6' };
    var div = document.createElement('div');
    div.style.cssText = 'position:fixed;top:16px;left:16px;right:16px;z-index:10000;padding:14px 16px;border-radius:12px;font-size:14px;font-family:Inter,sans-serif;color:#fff;background:' + (colors[type] || colors.info) + ';text-align:center;';
    div.textContent = message;
    document.body.appendChild(div);
    setTimeout(function() { div.remove(); }, 3000);
}

function filterDirectory() {
    var query = document.getElementById('m-sip-directory-search').value.toLowerCase().trim();
    var rows = document.querySelectorAll('.directory-row');
    var visibleCount = 0;
    rows.forEach(function(row) {
        var text = row.getAttribute('data-search') || '';
        var match = !query || text.indexOf(query) !== -1;
        row.style.display = match ? '' : 'none';
        if (match) visibleCount++;
    });
    var noResults = document.getElementById('m-sip-no-results');
    if (noResults) {
        noResults.style.display = (visibleCount === 0 && query) ? 'block' : 'none';
    }
    // Hide section labels when filtering
    var labels = document.querySelectorAll('.m-sip-section-label');
    labels.forEach(function(label) {
        if (!query) { label.style.display = ''; return; }
        var next = label.nextElementSibling;
        var hasVisible = false;
        while (next && !next.classList.contains('m-sip-section-label') && !next.classList.contains('m-sip-no-results') && !next.classList.contains('m-sip-form-card')) {
            if (next.classList.contains('directory-row') && next.style.display !== 'none') hasVisible = true;
            next = next.nextElementSibling;
        }
        label.style.display = hasVisible ? '' : 'none';
    });
}

function addDirectoryEntry() {
    var name = document.getElementById('m-sip-entry-name').value.trim();
    var extension = document.getElementById('m-sip-entry-ext').value.trim();
    var did = document.getElementById('m-sip-entry-did').value.trim();
    var email = document.getElementById('m-sip-entry-email').value.trim();
    var type = document.getElementById('m-sip-entry-type').value;
    var description = document.getElementById('m-sip-entry-desc').value.trim();
    var csrfToken = document.querySelector('#m-sip-add-form [name="csrf_token"]').value;

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
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showNotification('Directory entry added', 'success');
            setTimeout(function() { location.reload(); }, 800);
        } else {
            showNotification(data.message || 'Failed to add entry', 'error');
        }
    })
    .catch(function() { showNotification('Error adding directory entry', 'error'); });
}

function deleteDirectoryEntry(id, name) {
    if (!confirm('Remove "' + name + '" from the directory?')) return;
    var csrfToken = document.querySelector('#m-sip-add-form [name="csrf_token"]').value;

    fetch('process_profile_update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'action=delete_directory_entry&csrf_token=' + encodeURIComponent(csrfToken) + '&entry_id=' + encodeURIComponent(id)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showNotification('Directory entry removed', 'success');
            setTimeout(function() { location.reload(); }, 800);
        } else {
            showNotification(data.message || 'Failed to remove entry', 'error');
        }
    })
    .catch(function() { showNotification('Error removing directory entry', 'error'); });
}
</script>
