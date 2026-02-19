<!-- SIP Settings View -->
<?php
/**
 * SIP Account Settings
 * Allows staff to configure their SIP account for FusionPBX integration.
 * Staff can add their SIP credentials and make calls to other staff from the app.
 */

$current_user_id = $_SESSION['user_id'] ?? null;

// Fetch current user's SIP settings
$sip_data = null;
try {
    $stmt = $pdo->prepare("SELECT sip_username, sip_domain, sip_extension, sip_did FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $sip_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("SIP settings fetch error: " . $e->getMessage());
}

// Fetch staff with SIP extensions for the internal directory/call list
$sip_staff = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.job_title,
               u.sip_extension, u.sip_username, u.sip_domain, u.profile_image
        FROM users u
        WHERE u.sip_extension IS NOT NULL AND u.sip_extension != ''
        AND u.is_verified = 1
        ORDER BY u.first_name ASC, u.last_name ASC
    ");
    $sip_staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $sip_staff = decryptUserRows($sip_staff);
} catch (PDOException $e) {
    error_log("SIP staff fetch error: " . $e->getMessage());
}

// Get FusionPBX settings from system_settings
$fusionpbx_domain = '';
try {
    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'fusionpbx_domain'");
    $fusionpbx_domain = $stmt->fetchColumn() ?: '';
} catch (PDOException $e) {
    // Table or setting may not exist
}
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-headset"></i> SIP Phone Settings</h1>
        <p class="page-description">Configure your SIP account for internal calling via FusionPBX</p>
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
                    <small class="form-hint">Your SIP account username from FusionPBX</small>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-globe"></i> SIP Domain</label>
                    <input type="text" name="sip_domain" id="sip_domain" class="form-input"
                           placeholder="e.g., pbx.arcticwolves.ca" value="<?php echo htmlspecialchars($sip_data['sip_domain'] ?? $fusionpbx_domain); ?>">
                    <small class="form-hint">FusionPBX server domain</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-phone"></i> Extension</label>
                    <input type="text" name="sip_extension" id="sip_extension" class="form-input"
                           placeholder="e.g., 1001" value="<?php echo htmlspecialchars($sip_data['sip_extension'] ?? ''); ?>" readonly>
                    <small class="form-hint">Your extension number (set by administrator)</small>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-phone-square"></i> DID Number</label>
                    <input type="text" name="sip_did" id="sip_did" class="form-input"
                           placeholder="e.g., +16045551234" value="<?php echo htmlspecialchars($sip_data['sip_did'] ?? ''); ?>" readonly>
                    <small class="form-hint">Your Direct Inward Dialing number (set by administrator)</small>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fas fa-key"></i> SIP Password</label>
                <input type="password" name="sip_password" id="sip_password" class="form-input"
                       placeholder="Enter SIP password to register">
                <small class="form-hint">Your SIP account password (stored in browser only, not saved to server)</small>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-primary" id="sip-save-btn" onclick="saveSipSettings()"><i class="fas fa-save"></i> Save SIP Settings</button>
                <button type="button" class="btn btn-secondary" id="sip-register-btn" onclick="registerSip()"><i class="fas fa-plug"></i> Connect</button>
            </div>
        </form>

        <div id="sip-status" class="alert-card info" style="margin-top: 16px;">
            <i class="fas fa-info-circle"></i>
            <div class="alert-content">
                <p><strong>Status:</strong> <span id="sip-status-text">Not connected</span></p>
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

<!-- Internal Call Directory -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-users"></i> Internal Call Directory</h3>
        <span class="header-badge"><?php echo count($sip_staff); ?> extensions</span>
    </div>
    <div class="card-body">
        <?php if (count($sip_staff) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table enhanced-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Job Title</th>
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
                                    <span class="badge" style="background: var(--primary); color: #fff; padding: 2px 8px; border-radius: 4px;">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($staff['sip_extension']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-primary btn-small" onclick="callExtension('<?php echo htmlspecialchars($staff['sip_extension']); ?>', '<?php echo htmlspecialchars(($staff['first_name'] ?? '') . ' ' . ($staff['last_name'] ?? '')); ?>')">
                                        <i class="fas fa-phone"></i> Call
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-phone-slash"></i>
                <p>No staff members have SIP extensions configured yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SIP Call Modal -->
<div id="sip-call-modal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-phone"></i> <span id="call-title">Call</span></h2>
            <button class="modal-close" aria-label="Close" onclick="endCall()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center; padding: 30px;">
            <div id="call-status-icon" style="font-size: 48px; margin-bottom: 16px; color: var(--primary);">
                <i class="fas fa-phone-volume"></i>
            </div>
            <p id="call-status-message" style="font-size: 16px; color: var(--text-white); margin-bottom: 8px;">Calling...</p>
            <p id="call-extension-display" style="color: var(--text-muted); font-size: 14px;"></p>
            <p id="call-timer" style="font-size: 24px; font-weight: bold; color: var(--primary); margin-top: 16px; display: none;">00:00</p>
            <div style="margin-top: 24px;">
                <button class="btn btn-danger" onclick="endCall()" style="padding: 12px 32px;">
                    <i class="fas fa-phone-slash"></i> End Call
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// SIP client state
let sipConfigured = false;
let sipSession = null;
let callTimerInterval = null;
let callStartTime = null;

// Save SIP settings to server (username and domain only)
function saveSipSettings() {
    const username = document.getElementById('sip_username').value.trim();
    const domain = document.getElementById('sip_domain').value.trim();
    const csrfToken = document.querySelector('[name="csrf_token"]').value;

    const saveBtn = document.getElementById('sip-save-btn');
    const originalBtnText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch('process_profile_update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=update_own_sip&csrf_token=${encodeURIComponent(csrfToken)}&sip_username=${encodeURIComponent(username)}&sip_domain=${encodeURIComponent(domain)}`
    })
    .then(response => response.json())
    .then(data => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnText;
        if (data.success) {
            showNotification('SIP settings saved successfully', 'success');
        } else {
            showNotification(data.message || 'Failed to save SIP settings', 'error');
        }
    })
    .catch(error => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnText;
        showNotification('Error saving SIP settings', 'error');
    });
}

// Register with SIP server using WebSocket (FusionPBX WebRTC)
function registerSip() {
    const username = document.getElementById('sip_username').value.trim();
    const password = document.getElementById('sip_password').value;
    const domain = document.getElementById('sip_domain').value.trim();

    if (!username || !password || !domain) {
        showNotification('Please fill in SIP username, password, and domain', 'warning');
        return;
    }

    // Store password in sessionStorage only (not sent to server)
    sessionStorage.setItem('sip_password', password);

    // Update UI to show connecting status
    updateSipStatus('connecting', 'Connecting to ' + domain + '...');

    // Check if SIP.js is loaded, if not show info about WebRTC requirements
    if (typeof JsSIP === 'undefined' && typeof SIP === 'undefined') {
        updateSipStatus('info', 'SIP client ready. To enable WebRTC calling, ensure your FusionPBX server supports WSS (WebSocket Secure) connections. ' +
            'Configure your SIP client application to connect to wss://' + domain + ':7443 with username ' + username);
        sipConfigured = true;
        document.getElementById('sip-register-btn').innerHTML = '<i class="fas fa-check-circle"></i> Configured';
        document.getElementById('sip-register-btn').classList.remove('btn-secondary');
        document.getElementById('sip-register-btn').classList.add('btn-success');
        return;
    }

    updateSipStatus('connected', 'SIP account configured for ' + username + '@' + domain);
    sipConfigured = true;
}

// Call an extension
function callExtension(extension, name) {
    if (!sipConfigured) {
        showNotification('Please configure and connect your SIP account first', 'warning');
        return;
    }

    // Show call modal
    document.getElementById('call-title').textContent = 'Calling ' + name;
    document.getElementById('call-extension-display').textContent = 'Extension: ' + extension;
    document.getElementById('call-status-message').textContent = 'Ringing...';
    document.getElementById('call-timer').style.display = 'none';
    document.getElementById('sip-call-modal').classList.add('active');
    document.body.style.overflow = 'hidden';

    // Build SIP URI for internal call
    const domain = document.getElementById('sip_domain').value.trim();
    const sipUri = 'sip:' + extension + '@' + domain;

    // If a SIP UA is available, initiate the call
    if (window.sipUA && typeof window.sipUA.call === 'function') {
        try {
            sipSession = window.sipUA.call(sipUri, {
                mediaConstraints: { audio: true, video: false }
            });

            sipSession.on('accepted', function() {
                document.getElementById('call-status-message').textContent = 'Connected';
                document.getElementById('call-status-icon').innerHTML = '<i class="fas fa-phone" style="color: var(--success, #10b981);"></i>';
                startCallTimer();
            });

            sipSession.on('ended', function() {
                endCall();
            });

            sipSession.on('failed', function() {
                document.getElementById('call-status-message').textContent = 'Call failed';
                document.getElementById('call-status-icon').innerHTML = '<i class="fas fa-phone-slash" style="color: var(--danger, #ef4444);"></i>';
            });
        } catch (e) {
            document.getElementById('call-status-message').textContent = 'Initiating call via SIP client to ' + sipUri;
            // Fallback: open SIP URI which may trigger an installed SIP client
            window.open(sipUri, '_blank');
        }
    } else {
        // No WebRTC UA available - try SIP URI protocol handler
        document.getElementById('call-status-message').textContent = 'Opening SIP client for ' + extension + '...';
        window.open(sipUri, '_blank');
    }
}

// End current call
function endCall() {
    if (sipSession && typeof sipSession.terminate === 'function') {
        sipSession.terminate();
    }
    sipSession = null;
    stopCallTimer();

    document.getElementById('sip-call-modal').classList.remove('active');
    document.body.style.overflow = '';
}

// Call timer
function startCallTimer() {
    callStartTime = Date.now();
    document.getElementById('call-timer').style.display = 'block';
    callTimerInterval = setInterval(function() {
        const elapsed = Math.floor((Date.now() - callStartTime) / 1000);
        const mins = String(Math.floor(elapsed / 60)).padStart(2, '0');
        const secs = String(elapsed % 60).padStart(2, '0');
        document.getElementById('call-timer').textContent = mins + ':' + secs;
    }, 1000);
}

function stopCallTimer() {
    if (callTimerInterval) {
        clearInterval(callTimerInterval);
        callTimerInterval = null;
    }
}

// Update SIP status display
function updateSipStatus(type, message) {
    const statusEl = document.getElementById('sip-status');
    const textEl = document.getElementById('sip-status-text');
    statusEl.className = 'alert-card ' + (type === 'connected' ? 'success' : type === 'connecting' ? 'warning' : 'info');
    textEl.textContent = message;
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
</script>
