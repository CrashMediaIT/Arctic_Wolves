<!-- SIP Settings View -->
<?php
/**
 * SIP Account Settings
 * Allows staff to configure their SIP account for internal calling.
 * Staff can add their SIP credentials and make calls to other staff from the app.
 * Admins can also add non-user entries (rooms, shared lines) to the phone directory.
 * Access restricted to staff roles: Admin, Coach, Health Coach, Front Desk, HR, Accounting.
 */

// Permission check - staff only
if (!$isStaff) {
    echo '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Access denied. SIP Phone is available to staff members only.</div>';
    return;
}

$current_user_id = $_SESSION['user_id'] ?? null;

// Fetch current user's SIP settings
$sip_data = null;
$has_saved_password = false;
try {
    $stmt = $pdo->prepare("SELECT sip_username, sip_domain, sip_extension, sip_did, sip_password, sip_wss_port FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $sip_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $has_saved_password = !empty($sip_data['sip_password']);
} catch (PDOException $e) {
    error_log("SIP settings fetch error: " . $e->getMessage());
}

// Fetch staff with SIP profile info for the internal directory
$sip_staff = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.job_title,
               u.sip_extension, u.sip_username, u.sip_domain, u.sip_did, u.profile_image
        FROM users u
        WHERE ((u.sip_extension IS NOT NULL AND u.sip_extension != '')
           OR (u.sip_username IS NOT NULL AND u.sip_username != '' AND u.sip_domain IS NOT NULL AND u.sip_domain != ''))
        AND u.is_verified = 1
        ORDER BY u.first_name ASC, u.last_name ASC
    ");
    $sip_staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $sip_staff = decryptUserRows($sip_staff);
} catch (PDOException $e) {
    error_log("SIP staff fetch error: " . $e->getMessage());
}

// Fetch custom directory entries (rooms, non-users) - admin managed
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
        <h1 class="page-title"><i class="fas fa-headset"></i> SIP Phone Settings</h1>
        <p class="page-description">Configure your SIP account and dial via your SIP application or FusionPBX Web Dialer</p>
    </div>
</div>

<!-- SIP Account Configuration -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-cog"></i> My SIP Account</h3>
    </div>
    <div class="card-body">
        <form id="sip-settings-form">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <input type="hidden" name="action" value="update_sip_settings">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-user"></i> SIP Username</label>
                    <input type="text" name="sip_username" id="sip_username" class="form-input"
                           placeholder="e.g., 1001" value="<?php echo htmlspecialchars($sip_data['sip_username'] ?? ''); ?>">
                    <small class="form-hint">Your SIP account username</small>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-globe"></i> SIP Domain</label>
                    <input type="text" name="sip_domain" id="sip_domain" class="form-input"
                           placeholder="e.g., pbx.arcticwolves.ca" value="<?php echo htmlspecialchars($sip_data['sip_domain'] ?? ''); ?>">
                    <small class="form-hint">SIP server domain</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-phone"></i> Extension</label>
                    <input type="text" name="sip_extension" id="sip_extension" class="form-input"
                           placeholder="e.g., 1001" value="<?php echo htmlspecialchars($sip_data['sip_extension'] ?? ''); ?>" <?php echo !$isAdmin ? 'readonly' : ''; ?>>
                    <small class="form-hint"><?php echo $isAdmin ? 'Your extension number' : 'Your extension number (set by administrator)'; ?></small>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-phone-square"></i> DID Number</label>
                    <input type="text" name="sip_did" id="sip_did" class="form-input"
                           placeholder="e.g., +16045551234" value="<?php echo htmlspecialchars($sip_data['sip_did'] ?? ''); ?>" <?php echo !$isAdmin ? 'readonly' : ''; ?>>
                    <small class="form-hint"><?php echo $isAdmin ? 'Your Direct Inward Dialing number' : 'Your Direct Inward Dialing number (set by administrator)'; ?></small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-network-wired"></i> WSS Port</label>
                    <input type="number" name="sip_wss_port" id="sip_wss_port" class="form-input"
                           placeholder="7443" value="<?php echo htmlspecialchars($sip_data['sip_wss_port'] ?? '7443'); ?>" min="1" max="65535">
                    <small class="form-hint">WebSocket Secure port for FusionPBX connection (default: 7443)</small>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-key"></i> SIP Password</label>
                    <input type="password" name="sip_password" id="sip_password" class="form-input"
                           placeholder="<?php echo $has_saved_password ? 'Password saved — leave blank to keep current' : 'Enter SIP password'; ?>">
                    <small class="form-hint">Your SIP account password (encrypted and saved securely on the server)<?php echo $has_saved_password ? ' — already configured' : ''; ?></small>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-primary" id="sip-save-btn" onclick="saveSipSettings()"><i class="fas fa-save"></i> Save SIP Settings</button>
            </div>
        </form>

        <div class="alert-card info" style="margin-top: 16px;">
            <i class="fas fa-info-circle"></i>
            <div class="alert-content">
                <p><strong>How calls work:</strong> When you click Call or dial a number, a <code>sip:</code> link opens your native SIP application (e.g., MicroSIP, Zoiper, Ooma) or the <strong>FusionPBX Web Dialer</strong> browser extension if installed.</p>
                <p style="margin-top: 8px; color: var(--text-muted); font-size: 13px;"><i class="fas fa-external-link-alt"></i> Configure your SIP application with the same credentials above to make and receive calls.</p>
            </div>
        </div>
    </div>
</div>

<!-- Dialer -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-th"></i> Dialer</h3>
    </div>
    <div class="card-body">
        <div style="max-width: 320px; margin: 0 auto; text-align: center;">
            <input type="text" id="dialer-input" class="form-input" placeholder="Enter number or extension"
                   style="text-align: center; font-size: 22px; font-weight: 700; letter-spacing: 2px; margin-bottom: 16px; padding: 14px;">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 16px;">
                <button type="button" class="btn btn-secondary dialer-key" onclick="dialerPress('1')" style="height: 56px; font-size: 22px; font-weight: 700;">1</button>
                <button type="button" class="btn btn-secondary dialer-key" onclick="dialerPress('2')" style="height: 56px; font-size: 22px; font-weight: 700;">2</button>
                <button type="button" class="btn btn-secondary dialer-key" onclick="dialerPress('3')" style="height: 56px; font-size: 22px; font-weight: 700;">3</button>
                <button type="button" class="btn btn-secondary dialer-key" onclick="dialerPress('4')" style="height: 56px; font-size: 22px; font-weight: 700;">4</button>
                <button type="button" class="btn btn-secondary dialer-key" onclick="dialerPress('5')" style="height: 56px; font-size: 22px; font-weight: 700;">5</button>
                <button type="button" class="btn btn-secondary dialer-key" onclick="dialerPress('6')" style="height: 56px; font-size: 22px; font-weight: 700;">6</button>
                <button type="button" class="btn btn-secondary dialer-key" onclick="dialerPress('7')" style="height: 56px; font-size: 22px; font-weight: 700;">7</button>
                <button type="button" class="btn btn-secondary dialer-key" onclick="dialerPress('8')" style="height: 56px; font-size: 22px; font-weight: 700;">8</button>
                <button type="button" class="btn btn-secondary dialer-key" onclick="dialerPress('9')" style="height: 56px; font-size: 22px; font-weight: 700;">9</button>
                <button type="button" class="btn btn-secondary dialer-key" onclick="dialerPress('*')" style="height: 56px; font-size: 22px; font-weight: 700;">*</button>
                <button type="button" class="btn btn-secondary dialer-key" onclick="dialerPress('0')" style="height: 56px; font-size: 22px; font-weight: 700;">0</button>
                <button type="button" class="btn btn-secondary dialer-key" onclick="dialerPress('#')" style="height: 56px; font-size: 22px; font-weight: 700;">#</button>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn btn-primary" onclick="dialerCall()" style="flex: 1; height: 52px; font-size: 16px;">
                    <i class="fas fa-phone"></i> Call
                </button>
                <button type="button" class="btn btn-secondary" onclick="dialerClear()" style="height: 52px; font-size: 16px;">
                    <i class="fas fa-backspace"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Internal Phone Directory -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-address-book"></i> Phone Directory</h3>
        <span class="header-badge"><?php echo count($sip_staff) + count($custom_entries); ?> entries</span>
    </div>
    <div class="card-body">
        <?php if (count($sip_staff) > 0 || count($custom_entries) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table enhanced-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Job Title / Type</th>
                            <th>Extension</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sip_staff as $staff): ?>
                            <?php if ($staff['id'] == $current_user_id) continue; ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <?php
                                        $profile_img = $staff['profile_image'] ?? '';
                                        $is_valid_image = !empty($profile_img) &&
                                                          strpos($profile_img, 'uploads/profiles/') === 0 &&
                                                          preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $profile_img) &&
                                                          file_exists($profile_img);
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
                                    <?php if (!empty($staff['sip_extension'])): ?>
                                        <span class="badge" style="background: var(--primary); color: #fff; padding: 2px 8px; border-radius: 4px;">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($staff['sip_extension']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($staff['sip_extension'])): ?>
                                        <button class="btn btn-primary btn-small" onclick="callExtension('<?php echo htmlspecialchars($staff['sip_extension']); ?>', '<?php echo htmlspecialchars(($staff['first_name'] ?? '') . ' ' . ($staff['last_name'] ?? '')); ?>')">
                                            <i class="fas fa-phone"></i> Call
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ($custom_entries as $entry): ?>
                            <tr>
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
                                    <?php if (!empty($entry['extension'])): ?>
                                        <span class="badge" style="background: var(--primary); color: #fff; padding: 2px 8px; border-radius: 4px;">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($entry['extension']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($entry['extension'])): ?>
                                        <button class="btn btn-primary btn-small" onclick="callExtension('<?php echo htmlspecialchars($entry['extension']); ?>', '<?php echo htmlspecialchars($entry['display_name']); ?>')">
                                            <i class="fas fa-phone"></i> Call
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($isAdmin): ?>
                                        <button class="btn btn-danger btn-small" onclick="deleteDirectoryEntry(<?php echo intval($entry['id']); ?>, '<?php echo htmlspecialchars($entry['display_name']); ?>')" style="margin-left: 4px;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-phone-slash"></i>
                <p>No directory entries yet. Staff with SIP profile information will appear here automatically.</p>
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
        <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 16px;">Add non-user entries such as conference rooms, shared lines, or external numbers to the phone directory.</p>
        <form id="add-directory-entry-form">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-tag"></i> Name</label>
                    <input type="text" name="entry_name" id="entry_name" class="form-input" placeholder="e.g., Board Room, Lobby Phone" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-phone"></i> Extension</label>
                    <input type="text" name="entry_extension" id="entry_extension" class="form-input" placeholder="e.g., 2001">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-list"></i> Type</label>
                    <select name="entry_type" id="entry_type" class="form-input">
                        <option value="room">Room</option>
                        <option value="shared">Shared Line</option>
                        <option value="external">External</option>
                        <option value="other">Other</option>
                    </select>
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
// SIP Phone notification helper
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

// Save SIP settings to server
function saveSipSettings() {
    console.log('[SIP] Saving SIP settings...');
    const username = document.getElementById('sip_username').value.trim();
    const domain = document.getElementById('sip_domain').value.trim();
    const password = document.getElementById('sip_password').value;
    const extension = document.getElementById('sip_extension').value.trim();
    const did = document.getElementById('sip_did').value.trim();
    const wssPort = document.getElementById('sip_wss_port').value.trim() || '7443';
    const csrfToken = document.querySelector('[name="csrf_token"]').value;
    console.log('[SIP] Settings - username:', username, 'domain:', domain, 'extension:', extension, 'wssPort:', wssPort);

    const saveBtn = document.getElementById('sip-save-btn');
    const originalBtnText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    let body = `action=update_own_sip&csrf_token=${encodeURIComponent(csrfToken)}&sip_username=${encodeURIComponent(username)}&sip_domain=${encodeURIComponent(domain)}&sip_password=${encodeURIComponent(password)}&sip_extension=${encodeURIComponent(extension)}&sip_did=${encodeURIComponent(did)}&sip_wss_port=${encodeURIComponent(wssPort)}`;

    fetch('process_profile_update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: body
    })
    .then(response => response.json())
    .then(data => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnText;
        if (data.success) {
            console.log('[SIP] Settings saved successfully');
            showNotification('SIP settings saved successfully', 'success');
        } else {
            console.warn('[SIP] Save failed:', data.message || 'Unknown error');
            showNotification(data.message || 'Failed to save SIP settings', 'error');
        }
    })
    .catch(error => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnText;
        console.error('[SIP] Save error:', error);
        showNotification('Error saving SIP settings', 'error');
    });
}

// Call an extension via sip: URI (opens native SIP app or FusionPBX Web Dialer)
function callExtension(extension, name) {
    const domain = document.getElementById('sip_domain').value.trim();
    if (!domain) {
        showNotification('Please configure your SIP domain first', 'warning');
        return;
    }
    const sipUri = 'sip:' + extension + '@' + domain;
    console.log('[SIP] Opening SIP URI:', sipUri, 'for:', name);
    showNotification('Opening ' + name + ' in your SIP application...', 'info');
    window.location.href = sipUri;
}

// Dialer functions
function dialerPress(key) {
    const input = document.getElementById('dialer-input');
    input.value += key;
    input.focus();
}

function dialerClear() {
    const input = document.getElementById('dialer-input');
    input.value = input.value.slice(0, -1);
    input.focus();
}

function dialerCall() {
    const input = document.getElementById('dialer-input');
    const number = input.value.trim();
    if (!number) {
        showNotification('Please enter a number or extension to call', 'warning');
        return;
    }
    callExtension(number, number);
}

// Directory management functions (admin only)
function addDirectoryEntry() {
    const name = document.getElementById('entry_name').value.trim();
    const extension = document.getElementById('entry_extension').value.trim();
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

function deleteDirectoryEntry(id, name) {
    if (!confirm('Remove "' + name + '" from the phone directory?')) return;
    const csrfToken = document.querySelector('#sip-settings-form [name="csrf_token"]').value;

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
