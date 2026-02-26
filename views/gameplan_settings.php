<?php
/**
 * Game Plan Settings View
 * Admin settings for the Video Companion Server, hardware acceleration,
 * and NFS/SMB video storage configuration.
 */

// Ensure admin access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo '<div class="alert alert-danger"><i class="fa-solid fa-lock"></i> Access denied. Admin privileges required.</div>';
    return;
}

// Load current settings from database
$gameplan_settings = [];
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'gameplan_%'");
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $gameplan_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // Table or column may not exist yet
}

$companion_url = $gameplan_settings['gameplan_companion_url'] ?? '';
$companion_api_key = $gameplan_settings['gameplan_companion_api_key'] ?? '';
$hw_accel_enabled = ($gameplan_settings['gameplan_hw_accel_enabled'] ?? '0') === '1';
$hw_accel_method = $gameplan_settings['gameplan_hw_accel_method'] ?? 'auto';
$video_storage_type = $gameplan_settings['gameplan_video_storage_type'] ?? 'local';
$video_storage_path = $gameplan_settings['gameplan_video_storage_path'] ?? '/videos';
$nfs_server = $gameplan_settings['gameplan_nfs_server'] ?? '';
$nfs_export = $gameplan_settings['gameplan_nfs_export'] ?? '';
$nfs_options = $gameplan_settings['gameplan_nfs_options'] ?? 'rw,sync,no_subtree_check';
$smb_server = $gameplan_settings['gameplan_smb_server'] ?? '';
$smb_share = $gameplan_settings['gameplan_smb_share'] ?? '';
$smb_username = $gameplan_settings['gameplan_smb_username'] ?? '';
$smb_domain = $gameplan_settings['gameplan_smb_domain'] ?? '';
$gameplan_url = $gameplan_settings['gameplan_app_url'] ?? 'https://gameplan.arcticwolves.ca';

// Handle success/error messages
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <i class="fa-solid fa-chess-board"></i> Game Plan Settings
        </h1>
        <p class="page-description">Configure the Video Companion Server, hardware acceleration, and video storage</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= htmlspecialchars($gameplan_url) ?>" class="btn btn-primary" target="_blank" rel="noopener noreferrer">
            <i class="fa-solid fa-external-link-alt"></i> Open Game Plan
        </a>
    </div>
</div>

<?php if ($success === 'settings_saved'): ?>
<div class="alert alert-success">
    <i class="fa-solid fa-check-circle"></i> Game Plan settings saved successfully.
</div>
<?php endif; ?>

<?php if ($success === 'companion_connected'): ?>
<div class="alert alert-success">
    <i class="fa-solid fa-check-circle"></i> Companion server connection verified successfully.
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fa-solid fa-exclamation-circle"></i> <?= htmlspecialchars(urldecode($error)) ?>
</div>
<?php endif; ?>

<div class="global-settings-content">

    <!-- Companion Server Configuration -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fa-solid fa-server" style="color:var(--primary-light);margin-right:8px;"></i> Companion Server</h3>
            <span class="badge <?= $companion_url ? 'badge-success' : 'badge-warning' ?>" id="companionStatus">
                <?= $companion_url ? 'Configured' : 'Not Configured' ?>
            </span>
        </div>
        <div class="card-body">
            <p style="color:var(--text-secondary);font-size:13px;margin-bottom:20px;">
                The companion server is a worker/integration service that handles hardware-accelerated
                video transcoding.  Generate an API key in the companion's Settings UI, then paste it here.
                RustFS credentials are pushed from the main app (see RustFS settings).
            </p>
            <form class="settings-form" method="POST" action="process_gameplan_settings.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="action" value="save_companion">

                <div class="form-group">
                    <label>Companion Server URL *</label>
                    <input type="url" name="companion_url" class="form-input"
                           value="<?= htmlspecialchars($companion_url) ?>"
                           placeholder="http://localhost:5100">
                    <small class="form-hint">The base URL of the companion server (e.g. http://companion:5100 for Docker)</small>
                </div>

                <div class="form-group">
                    <label>API Key *</label>
                    <div style="position:relative;display:flex;align-items:center;">
                        <input type="password" name="companion_api_key" id="companionApiKey" class="form-input"
                               value="<?= htmlspecialchars($companion_api_key) ?>"
                               placeholder="Paste the key generated in the companion" style="padding-right:40px;">
                        <button type="button" onclick="toggleVisibility('companionApiKey', this)" aria-label="Toggle visibility"
                                style="position:absolute;right:10px;background:none;border:none;cursor:pointer;color:#64748b;padding:5px;">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <small class="form-hint">Generated in the companion app's Settings → Generate API Key, then pasted here</small>
                </div>

                <div class="form-group">
                    <label>Game Plan App URL</label>
                    <input type="url" name="gameplan_app_url" class="form-input"
                           value="<?= htmlspecialchars($gameplan_url) ?>"
                           placeholder="https://gameplan.arcticwolves.ca">
                    <small class="form-hint">Used by the companion to send transcode-complete callbacks back to this application</small>
                </div>

                <div class="form-actions" style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Companion Settings</button>
                    <button type="button" class="btn btn-secondary" id="testCompanionBtn" onclick="testCompanion()">
                        <i class="fa-solid fa-plug"></i> Test Connection
                    </button>
                    <button type="button" class="btn btn-secondary" id="pushRustFsBtn" onclick="pushRustFsToCompanion()">
                        <i class="fa-solid fa-paper-plane"></i> Push RustFS to Companion
                    </button>
                </div>
                <div id="pushRustFsResult" style="margin-top:8px;font-size:13px;"></div>
            </form>
        </div>
    </div>

    <!-- Hardware Acceleration Settings -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fa-solid fa-microchip" style="color:var(--primary-light);margin-right:8px;"></i> Hardware Acceleration</h3>
        </div>
        <div class="card-body">
            <p style="color:var(--text-secondary);font-size:13px;margin-bottom:20px;">
                Enable hardware-accelerated video processing on the companion server. Requires a compatible GPU (NVIDIA, Intel, or AMD).
            </p>
            <form class="settings-form" method="POST" action="process_gameplan_settings.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="action" value="save_hw_accel">

                <div class="setting-toggle-item">
                    <div class="setting-info">
                        <h4>Enable Hardware Acceleration</h4>
                        <p>Use GPU for video encoding, decoding, and transcoding operations on the companion server</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="hw_accel_enabled" value="1" <?= $hw_accel_enabled ? 'checked' : '' ?> onchange="toggleHwOptions(this)">
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div id="hwAccelOptions" style="<?= $hw_accel_enabled ? '' : 'display:none;' ?>">
                    <div class="form-group">
                        <label>Acceleration Method</label>
                        <select name="hw_accel_method" class="form-input">
                            <option value="auto" <?= $hw_accel_method === 'auto' ? 'selected' : '' ?>>Auto-Detect</option>
                            <option value="nvenc" <?= $hw_accel_method === 'nvenc' ? 'selected' : '' ?>>NVIDIA NVENC (CUDA)</option>
                            <option value="qsv" <?= $hw_accel_method === 'qsv' ? 'selected' : '' ?>>Intel Quick Sync Video (QSV)</option>
                            <option value="vaapi" <?= $hw_accel_method === 'vaapi' ? 'selected' : '' ?>>VA-API (Linux Intel/AMD)</option>
                            <option value="amf" <?= $hw_accel_method === 'amf' ? 'selected' : '' ?>>AMD AMF</option>
                            <option value="none" <?= $hw_accel_method === 'none' ? 'selected' : '' ?>>Software Only (CPU)</option>
                        </select>
                        <small class="form-hint">Auto-detect will probe the companion server's GPU capabilities</small>
                    </div>

                    <div id="hwCapabilities" style="background:var(--bg-main);border:1px solid var(--border);border-radius:8px;padding:16px;margin-bottom:16px;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                            <i class="fa-solid fa-info-circle" style="color:var(--info);"></i>
                            <strong style="font-size:13px;">Server Capabilities</strong>
                        </div>
                        <p style="color:var(--text-muted);font-size:12px;" id="hwCapText">
                            Save the companion server URL above and click "Test Connection" to detect hardware capabilities.
                        </p>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Hardware Settings</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Video Storage Configuration -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fa-solid fa-hard-drive" style="color:var(--primary-light);margin-right:8px;"></i> Video Storage</h3>
        </div>
        <div class="card-body">
            <p style="color:var(--text-secondary);font-size:13px;margin-bottom:20px;">
                Configure where video files are stored. Both the Game Plan app and the companion server must have
                access to the same storage location. Use NFS or SMB mounts for network-attached storage.
            </p>
            <form class="settings-form" method="POST" action="process_gameplan_settings.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="action" value="save_video_storage">

                <div class="form-group">
                    <label>Storage Type</label>
                    <select name="video_storage_type" class="form-input" id="storageType" onchange="toggleStorageOptions(this.value)">
                        <option value="local" <?= $video_storage_type === 'local' ? 'selected' : '' ?>>Local Directory</option>
                        <option value="nfs" <?= $video_storage_type === 'nfs' ? 'selected' : '' ?>>NFS Mount</option>
                        <option value="smb" <?= $video_storage_type === 'smb' ? 'selected' : '' ?>>SMB/CIFS Mount</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Video Storage Path *</label>
                    <input type="text" name="video_storage_path" class="form-input"
                           value="<?= htmlspecialchars($video_storage_path) ?>"
                           placeholder="/videos">
                    <small class="form-hint">Local mount point where video files are stored (must be same path on both servers)</small>
                </div>

                <!-- NFS Options -->
                <div id="nfsOptions" style="<?= $video_storage_type === 'nfs' ? '' : 'display:none;' ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>NFS Server</label>
                            <input type="text" name="nfs_server" class="form-input"
                                   value="<?= htmlspecialchars($nfs_server) ?>"
                                   placeholder="nas.local or 192.168.1.100">
                        </div>
                        <div class="form-group">
                            <label>NFS Export Path</label>
                            <input type="text" name="nfs_export" class="form-input"
                                   value="<?= htmlspecialchars($nfs_export) ?>"
                                   placeholder="/volume1/videos">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>NFS Mount Options</label>
                        <input type="text" name="nfs_options" class="form-input"
                               value="<?= htmlspecialchars($nfs_options) ?>"
                               placeholder="rw,sync,no_subtree_check">
                    </div>

                    <div style="background:rgba(107,70,193,0.05);border-left:3px solid var(--primary);padding:14px;margin-bottom:16px;border-radius:0 8px 8px 0;">
                        <strong style="font-size:12px;color:var(--text-white);">NFS Mount Command</strong>
                        <code id="nfsMountCmd" style="display:block;margin-top:8px;font-size:12px;color:var(--text-secondary);word-break:break-all;">
                            mount -t nfs <?= htmlspecialchars($nfs_server ?: 'nas.local') ?>:<?= htmlspecialchars($nfs_export ?: '/volume1/videos') ?> <?= htmlspecialchars($video_storage_path ?: '/videos') ?> -o <?= htmlspecialchars($nfs_options ?: 'rw,sync') ?>
                        </code>
                    </div>
                </div>

                <!-- SMB Options -->
                <div id="smbOptions" style="<?= $video_storage_type === 'smb' ? '' : 'display:none;' ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>SMB/CIFS Server</label>
                            <input type="text" name="smb_server" class="form-input"
                                   value="<?= htmlspecialchars($smb_server) ?>"
                                   placeholder="nas.local or 192.168.1.100">
                        </div>
                        <div class="form-group">
                            <label>Share Name</label>
                            <input type="text" name="smb_share" class="form-input"
                                   value="<?= htmlspecialchars($smb_share) ?>"
                                   placeholder="videos">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>SMB Username</label>
                            <input type="text" name="smb_username" class="form-input"
                                   value="<?= htmlspecialchars($smb_username) ?>"
                                   placeholder="username">
                        </div>
                        <div class="form-group">
                            <label>SMB Domain (optional)</label>
                            <input type="text" name="smb_domain" class="form-input"
                                   value="<?= htmlspecialchars($smb_domain) ?>"
                                   placeholder="WORKGROUP">
                        </div>
                    </div>

                    <div style="background:rgba(107,70,193,0.05);border-left:3px solid var(--primary);padding:14px;margin-bottom:16px;border-radius:0 8px 8px 0;">
                        <strong style="font-size:12px;color:var(--text-white);">SMB Mount Command</strong>
                        <code id="smbMountCmd" style="display:block;margin-top:8px;font-size:12px;color:var(--text-secondary);word-break:break-all;">
                            mount -t cifs //<?= htmlspecialchars($smb_server ?: 'nas.local') ?>/<?= htmlspecialchars($smb_share ?: 'videos') ?> <?= htmlspecialchars($video_storage_path ?: '/videos') ?> -o username=<?= htmlspecialchars($smb_username ?: 'user') ?>,uid=911,gid=911
                        </code>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Storage Settings</button>
                </div>
            </form>
        </div>
    </div>

</div>

<style>
.setting-toggle-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 12px;
}
.setting-toggle-item .setting-info h4 {
    margin: 0 0 4px;
    font-size: 14px;
}
.setting-toggle-item .setting-info p {
    margin: 0;
    color: var(--text-muted);
    font-size: 12px;
}
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
    flex-shrink: 0;
}
.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.toggle-slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background: var(--border);
    border-radius: 26px;
    transition: 0.3s;
}
.toggle-slider:before {
    content: "";
    position: absolute;
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background: white;
    border-radius: 50%;
    transition: 0.3s;
}
.toggle-switch input:checked + .toggle-slider {
    background: var(--primary);
}
.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(22px);
}
.form-hint {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 4px;
}
.global-settings-content .content-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 24px;
    overflow: hidden;
}
.global-settings-content .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 24px;
    border-bottom: 1px solid var(--border);
}
.global-settings-content .card-header h3 {
    margin: 0;
    font-size: 16px;
    display: flex;
    align-items: center;
}
.global-settings-content .card-body {
    padding: 24px;
}
.form-actions {
    padding-top: 10px;
}
</style>

<script>
function toggleVisibility(inputId, button) {
    var input = document.getElementById(inputId);
    var icon = button.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function toggleHwOptions(checkbox) {
    document.getElementById('hwAccelOptions').style.display = checkbox.checked ? '' : 'none';
}

function toggleStorageOptions(type) {
    document.getElementById('nfsOptions').style.display = (type === 'nfs') ? '' : 'none';
    document.getElementById('smbOptions').style.display = (type === 'smb') ? '' : 'none';
}

function showTestAlert(type, message) {
    var container = document.getElementById('testAlertContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'testAlertContainer';
        var formActions = document.getElementById('testCompanionBtn').closest('.form-actions');
        formActions.parentNode.insertBefore(container, formActions);
    }
    var cls = (type === 'success') ? 'alert-success' : 'alert-danger';
    var icon = (type === 'success') ? 'fa-check-circle' : 'fa-exclamation-circle';
    container.innerHTML = '<div class="alert ' + cls + '" style="margin-bottom:16px;"><i class="fa-solid ' + icon + '"></i> ' + message + '</div>';
}

function testCompanion() {
    var urlInput = document.querySelector('input[name="companion_url"]');
    var keyInput = document.querySelector('input[name="companion_api_key"]');
    var btn = document.getElementById('testCompanionBtn');
    var statusBadge = document.getElementById('companionStatus');

    if (!urlInput.value) {
        showTestAlert('error', 'Please enter the companion server URL first.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Testing...';

    // Test via server-side proxy to avoid CORS
    var form = new FormData();
    form.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
    form.append('action', 'test_companion');
    form.append('companion_url', urlInput.value);
    form.append('companion_api_key', keyInput.value);

    fetch('process_gameplan_settings.php', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                statusBadge.className = 'badge badge-success';
                statusBadge.textContent = 'Connected';
                var cap = document.getElementById('hwCapText');
                if (data.hw_accel) {
                    var html = '<strong style="color:var(--success);"><i class="fas fa-check"></i> Server Online</strong><br>';
                    html += 'Available methods: ' + (data.hw_accel.available.join(', ') || 'none') + '<br>';
                    html += 'Encoders: ' + (data.hw_accel.encoders.join(', ') || 'none') + '<br>';
                    html += 'Decoders: ' + (data.hw_accel.decoders.join(', ') || 'none');
                    cap.innerHTML = html;
                }
                showTestAlert('success', 'Connection successful! Companion server is online.');
            } else {
                statusBadge.className = 'badge badge-danger';
                statusBadge.textContent = 'Error';
                showTestAlert('error', 'Connection failed: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(function(err) {
            statusBadge.className = 'badge badge-danger';
            statusBadge.textContent = 'Error';
            showTestAlert('error', 'Connection failed: ' + err.message);
        })
        .finally(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-plug"></i> Test Connection';
        });
}

function pushRustFsToCompanion() {
    var btn = document.getElementById('pushRustFsBtn');
    var resultEl = document.getElementById('pushRustFsResult');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Pushing...';
    resultEl.innerHTML = '';

    var form = new FormData();
    form.append('csrf_token', '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>');
    form.append('action', 'push_rustfs_to_companion');

    fetch('process_gameplan_settings.php', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                resultEl.innerHTML = '<span style="color:var(--success);"><i class="fa-solid fa-check"></i> RustFS settings pushed to companion successfully.</span>';
            } else {
                resultEl.innerHTML = '<span style="color:var(--danger);"><i class="fa-solid fa-exclamation-circle"></i> ' + (data.error || 'Push failed') + '</span>';
            }
        })
        .catch(function(err) {
            resultEl.innerHTML = '<span style="color:var(--danger);"><i class="fa-solid fa-exclamation-circle"></i> Network error: ' + err.message + '</span>';
        })
        .finally(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Push RustFS to Companion';
        });
}
</script>
