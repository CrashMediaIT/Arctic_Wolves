<?php
/**
 * PWA Game Plan Settings - Mobile-native admin settings
 * Purpose-built for mobile phones with full CRUD functionality.
 */

// Ensure admin access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo '<div style="text-align:center;padding:40px 20px;color:#EF4444;font-family:Inter,sans-serif;">
        <i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>
        <p style="font-size:14px;font-weight:600;">Admin access required</p>
    </div>';
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
} catch (PDOException $e) { /* Table may not exist yet */ }

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

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<style>
.m-gps { padding: 0; font-family: Inter, sans-serif; }
.m-gps-header {
    padding: 16px; border-bottom: 1px solid #2D2D3F;
}
.m-gps-title { font-size: 17px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px; margin: 0; }
.m-gps-title i { color: #8B5CF6; }
.m-gps-sub { font-size: 12px; color: #A8A8B8; margin: 4px 0 0; }
.m-gps-body { padding: 16px; }
.m-gps-alert {
    border-radius: 10px; padding: 12px 14px; margin-bottom: 14px;
    font-size: 13px; font-weight: 600;
    display: flex; align-items: center; gap: 8px;
}
.m-gps-alert-ok { background: rgba(16,185,129,.12); color: #10B981; border: 1px solid rgba(16,185,129,.2); }
.m-gps-alert-err { background: rgba(239,68,68,.12); color: #EF4444; border: 1px solid rgba(239,68,68,.2); }
.m-gps-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    margin-bottom: 16px; overflow: hidden;
}
.m-gps-card-header {
    padding: 14px 16px; border-bottom: 1px solid #2D2D3F;
    display: flex; align-items: center; justify-content: space-between;
}
.m-gps-card-title { font-size: 14px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px; }
.m-gps-card-title i { color: #8B5CF6; font-size: 14px; }
.m-gps-card-badge {
    font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 6px;
}
.m-gps-card-badge.ok { background: rgba(16,185,129,.15); color: #10B981; }
.m-gps-card-badge.warn { background: rgba(245,158,11,.15); color: #F59E0B; }
.m-gps-card-body { padding: 14px 16px; }
.m-gps-card-desc { font-size: 12px; color: #A8A8B8; margin-bottom: 14px; line-height: 1.5; }
.m-gps-field { margin-bottom: 14px; }
.m-gps-field label {
    display: block; font-size: 12px; color: #A8A8B8; font-weight: 600;
    margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;
}
.m-gps-field input, .m-gps-field select {
    width: 100%; padding: 12px; background: #0A0A0F; border: 1px solid #2D2D3F;
    border-radius: 10px; color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    min-height: 44px;
}
.m-gps-field input:focus, .m-gps-field select:focus {
    outline: none; border-color: #6B46C1; box-shadow: 0 0 0 2px rgba(107,70,193,.2);
}
.m-gps-field small { font-size: 11px; color: #6B6B7B; margin-top: 4px; display: block; }
.m-gps-field .m-input-row { display: flex; align-items: center; gap: 0; }
.m-gps-field .m-input-row input { border-top-right-radius: 0; border-bottom-right-radius: 0; flex: 1; }
.m-gps-field .m-eye-btn {
    width: 44px; min-height: 44px; background: #16161F; border: 1px solid #2D2D3F;
    border-left: none; border-radius: 0 10px 10px 0; color: #6B6B7B;
    font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.m-gps-toggle-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 0; min-height: 44px;
}
.m-gps-toggle-info { flex: 1; }
.m-gps-toggle-info h4 { font-size: 14px; font-weight: 600; color: #fff; margin: 0 0 2px; }
.m-gps-toggle-info p { font-size: 11px; color: #6B6B7B; margin: 0; }
.m-gps-toggle {
    position: relative; width: 48px; height: 28px;
    background: #2D2D3F; border-radius: 14px; cursor: pointer;
    border: none; padding: 0; flex-shrink: 0;
}
.m-gps-toggle::after {
    content: ''; position: absolute; top: 3px; left: 3px;
    width: 22px; height: 22px; border-radius: 50%;
    background: #6B6B7B; transition: all .2s ease;
}
.m-gps-toggle.on { background: rgba(107,70,193,.3); }
.m-gps-toggle.on::after { left: 23px; background: #8B5CF6; }
.m-gps-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; padding: 14px; border-radius: 10px; font-size: 14px; font-weight: 600;
    border: none; cursor: pointer; font-family: Inter, sans-serif; min-height: 44px;
}
.m-gps-btn-primary { background: linear-gradient(135deg, #6B46C1, #8B5CF6); color: #fff; }
.m-gps-btn-secondary { background: #2D2D3F; color: #A8A8B8; }
.m-gps-btn-row { display: flex; gap: 10px; margin-top: 4px; }
.m-gps-btn-row .m-gps-btn { flex: 1; }
.m-gps-mount-cmd {
    background: rgba(107,70,193,.05); border-left: 3px solid #6B46C1;
    padding: 12px; border-radius: 0 8px 8px 0; margin-bottom: 14px;
}
.m-gps-mount-cmd strong { font-size: 11px; color: #A8A8B8; }
.m-gps-mount-cmd code { display: block; font-size: 11px; color: #6B6B7B; margin-top: 6px; word-break: break-all; }
</style>

<div class="m-gps">
    <div class="m-gps-header">
        <h2 class="m-gps-title"><i class="fas fa-chess-board"></i> Game Plan Settings</h2>
        <p class="m-gps-sub">Companion server, hardware &amp; storage</p>
    </div>

    <div class="m-gps-body">
        <?php if ($success === 'settings_saved'): ?>
        <div class="m-gps-alert m-gps-alert-ok"><i class="fas fa-check-circle"></i> Settings saved</div>
        <?php endif; ?>
        <?php if ($success === 'companion_connected'): ?>
        <div class="m-gps-alert m-gps-alert-ok"><i class="fas fa-check-circle"></i> Companion connected</div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="m-gps-alert m-gps-alert-err"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars(urldecode($error)) ?></div>
        <?php endif; ?>

        <!-- Companion Server -->
        <div class="m-gps-card">
            <div class="m-gps-card-header">
                <span class="m-gps-card-title"><i class="fas fa-server"></i> Companion Server</span>
                <span class="m-gps-card-badge <?= $companion_url ? 'ok' : 'warn' ?>" id="mCompStatus">
                    <?= $companion_url ? 'Configured' : 'Not Set' ?>
                </span>
            </div>
            <div class="m-gps-card-body">
                <p class="m-gps-card-desc">Handles video encoding, decoding, and clip extraction.</p>
                <form method="POST" action="process_gameplan_settings.php" id="mCompForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="save_companion">

                    <div class="m-gps-field">
                        <label>Companion URL *</label>
                        <input type="url" name="companion_url" value="<?= htmlspecialchars($companion_url) ?>" placeholder="http://localhost:5100">
                        <small>Base URL of companion server</small>
                    </div>

                    <div class="m-gps-field">
                        <label>API Key *</label>
                        <div class="m-input-row">
                            <input type="password" name="companion_api_key" id="mCompKey" value="<?= htmlspecialchars($companion_api_key) ?>" placeholder="Paste key from companion">
                            <button type="button" class="m-eye-btn" onclick="mGpsToggleVis('mCompKey', this)" aria-label="Show"><i class="fas fa-eye"></i></button>
                        </div>
                        <small>Generated in the companion app Settings, then pasted here</small>
                    </div>

                    <div class="m-gps-field">
                        <label>Game Plan App URL</label>
                        <input type="url" name="gameplan_app_url" value="<?= htmlspecialchars($gameplan_url) ?>" placeholder="https://gameplan.arcticwolves.ca">
                        <small>URL of the Game Plan application</small>
                    </div>

                    <div class="m-gps-btn-row">
                        <button type="submit" class="m-gps-btn m-gps-btn-primary"><i class="fas fa-save"></i> Save</button>
                        <button type="button" class="m-gps-btn m-gps-btn-secondary" onclick="mGpsTestComp()"><i class="fas fa-plug"></i> Test</button>
                    </div>
                    <div id="mCompTestResult" style="margin-top:10px;"></div>
                </form>
            </div>
        </div>

        <!-- Hardware Acceleration -->
        <div class="m-gps-card">
            <div class="m-gps-card-header">
                <span class="m-gps-card-title"><i class="fas fa-microchip"></i> Hardware Accel</span>
            </div>
            <div class="m-gps-card-body">
                <p class="m-gps-card-desc">GPU-accelerated video processing on the companion server.</p>
                <form method="POST" action="process_gameplan_settings.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="save_hw_accel">
                    <input type="hidden" name="hw_accel_enabled" id="mHwAccelVal" value="<?= $hw_accel_enabled ? '1' : '0' ?>">

                    <div class="m-gps-toggle-row">
                        <div class="m-gps-toggle-info">
                            <h4>Enable GPU Acceleration</h4>
                            <p>Use GPU for encoding &amp; decoding</p>
                        </div>
                        <button type="button" class="m-gps-toggle <?= $hw_accel_enabled ? 'on' : '' ?>" id="mHwToggle" onclick="mGpsToggleHw()"></button>
                    </div>

                    <div id="mHwOptions" style="<?= $hw_accel_enabled ? '' : 'display:none;' ?>">
                        <div class="m-gps-field">
                            <label>Method</label>
                            <select name="hw_accel_method">
                                <option value="auto" <?= $hw_accel_method === 'auto' ? 'selected' : '' ?>>Auto-Detect</option>
                                <option value="nvenc" <?= $hw_accel_method === 'nvenc' ? 'selected' : '' ?>>NVIDIA NVENC</option>
                                <option value="qsv" <?= $hw_accel_method === 'qsv' ? 'selected' : '' ?>>Intel QSV</option>
                                <option value="vaapi" <?= $hw_accel_method === 'vaapi' ? 'selected' : '' ?>>VA-API (Linux)</option>
                                <option value="amf" <?= $hw_accel_method === 'amf' ? 'selected' : '' ?>>AMD AMF</option>
                                <option value="none" <?= $hw_accel_method === 'none' ? 'selected' : '' ?>>Software Only</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="m-gps-btn m-gps-btn-primary" style="margin-top:4px;"><i class="fas fa-save"></i> Save Hardware</button>
                </form>
            </div>
        </div>

        <!-- Video Storage -->
        <div class="m-gps-card">
            <div class="m-gps-card-header">
                <span class="m-gps-card-title"><i class="fas fa-hard-drive"></i> Video Storage</span>
            </div>
            <div class="m-gps-card-body">
                <p class="m-gps-card-desc">Where video files are stored. Both app and companion need access to same location.</p>
                <form method="POST" action="process_gameplan_settings.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="save_video_storage">

                    <div class="m-gps-field">
                        <label>Storage Type</label>
                        <select name="video_storage_type" id="mStorageType" onchange="mGpsStorageType(this.value)">
                            <option value="local" <?= $video_storage_type === 'local' ? 'selected' : '' ?>>Local Directory</option>
                            <option value="nfs" <?= $video_storage_type === 'nfs' ? 'selected' : '' ?>>NFS Mount</option>
                            <option value="smb" <?= $video_storage_type === 'smb' ? 'selected' : '' ?>>SMB/CIFS Mount</option>
                        </select>
                    </div>

                    <div class="m-gps-field">
                        <label>Storage Path *</label>
                        <input type="text" name="video_storage_path" value="<?= htmlspecialchars($video_storage_path) ?>" placeholder="/videos">
                        <small>Mount point for video files</small>
                    </div>

                    <!-- NFS Options -->
                    <div id="mNfsOpts" style="<?= $video_storage_type === 'nfs' ? '' : 'display:none;' ?>">
                        <div class="m-gps-field">
                            <label>NFS Server</label>
                            <input type="text" name="nfs_server" value="<?= htmlspecialchars($nfs_server) ?>" placeholder="nas.local">
                        </div>
                        <div class="m-gps-field">
                            <label>NFS Export Path</label>
                            <input type="text" name="nfs_export" value="<?= htmlspecialchars($nfs_export) ?>" placeholder="/volume1/videos">
                        </div>
                        <div class="m-gps-field">
                            <label>Mount Options</label>
                            <input type="text" name="nfs_options" value="<?= htmlspecialchars($nfs_options) ?>" placeholder="rw,sync,no_subtree_check">
                        </div>
                        <div class="m-gps-mount-cmd">
                            <strong>NFS Mount Command</strong>
                            <code>mount -t nfs <?= htmlspecialchars($nfs_server ?: 'nas.local') ?>:<?= htmlspecialchars($nfs_export ?: '/volume1/videos') ?> <?= htmlspecialchars($video_storage_path ?: '/videos') ?> -o <?= htmlspecialchars($nfs_options ?: 'rw,sync') ?></code>
                        </div>
                    </div>

                    <!-- SMB Options -->
                    <div id="mSmbOpts" style="<?= $video_storage_type === 'smb' ? '' : 'display:none;' ?>">
                        <div class="m-gps-field">
                            <label>SMB Server</label>
                            <input type="text" name="smb_server" value="<?= htmlspecialchars($smb_server) ?>" placeholder="nas.local">
                        </div>
                        <div class="m-gps-field">
                            <label>Share Name</label>
                            <input type="text" name="smb_share" value="<?= htmlspecialchars($smb_share) ?>" placeholder="videos">
                        </div>
                        <div class="m-gps-field">
                            <label>Username</label>
                            <input type="text" name="smb_username" value="<?= htmlspecialchars($smb_username) ?>" placeholder="username">
                        </div>
                        <div class="m-gps-field">
                            <label>Domain (optional)</label>
                            <input type="text" name="smb_domain" value="<?= htmlspecialchars($smb_domain) ?>" placeholder="WORKGROUP">
                        </div>
                        <div class="m-gps-mount-cmd">
                            <strong>SMB Mount Command</strong>
                            <code>mount -t cifs //<?= htmlspecialchars($smb_server ?: 'nas.local') ?>/<?= htmlspecialchars($smb_share ?: 'videos') ?> <?= htmlspecialchars($video_storage_path ?: '/videos') ?> -o username=<?= htmlspecialchars($smb_username ?: 'user') ?>,uid=911,gid=911</code>
                        </div>
                    </div>

                    <button type="submit" class="m-gps-btn m-gps-btn-primary"><i class="fas fa-save"></i> Save Storage</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function mGpsToggleVis(inputId, btn) {
    var input = document.getElementById(inputId);
    var icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

function mGpsToggleHw() {
    var toggle = document.getElementById('mHwToggle');
    var val = document.getElementById('mHwAccelVal');
    var opts = document.getElementById('mHwOptions');
    toggle.classList.toggle('on');
    var isOn = toggle.classList.contains('on');
    val.value = isOn ? '1' : '0';
    opts.style.display = isOn ? '' : 'none';
}

function mGpsStorageType(type) {
    document.getElementById('mNfsOpts').style.display = (type === 'nfs') ? '' : 'none';
    document.getElementById('mSmbOpts').style.display = (type === 'smb') ? '' : 'none';
}

function mGpsTestComp() {
    var url = document.querySelector('#mCompForm input[name="companion_url"]').value;
    var key = document.querySelector('#mCompForm input[name="companion_api_key"]').value;
    var status = document.getElementById('mCompStatus');
    var result = document.getElementById('mCompTestResult');

    if (!url) { result.innerHTML = '<div class="m-gps-alert m-gps-alert-err" style="margin:0;"><i class="fas fa-exclamation-circle"></i> Enter URL first</div>'; return; }

    result.innerHTML = '<div style="color:#A8A8B8;font-size:12px;"><i class="fas fa-spinner fa-spin"></i> Testing...</div>';

    var form = new FormData();
    form.append('csrf_token', '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>');
    form.append('action', 'test_companion');
    form.append('companion_url', url);
    form.append('companion_api_key', key);

    fetch('process_gameplan_settings.php', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                status.className = 'm-gps-card-badge ok';
                status.textContent = 'Connected';
                result.innerHTML = '<div class="m-gps-alert m-gps-alert-ok" style="margin:0;"><i class="fas fa-check-circle"></i> Connected!</div>';
            } else {
                status.className = 'm-gps-card-badge warn';
                status.textContent = 'Error';
                result.innerHTML = '<div class="m-gps-alert m-gps-alert-err" style="margin:0;"><i class="fas fa-exclamation-circle"></i> ' + (data.error || 'Failed') + '</div>';
            }
        })
        .catch(function(err) {
            status.className = 'm-gps-card-badge warn';
            status.textContent = 'Error';
            result.innerHTML = '<div class="m-gps-alert m-gps-alert-err" style="margin:0;"><i class="fas fa-exclamation-circle"></i> ' + err.message + '</div>';
        });
}
</script>
