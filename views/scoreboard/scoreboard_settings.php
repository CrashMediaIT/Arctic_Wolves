<?php
/**
 * Scoreboard Settings View – Admin-Only Configuration
 *
 * Configure:
 *   - Music sources (Spotify, Apple Music, Subsonic)
 *   - Custom buzzer/horn sound upload
 *   - Network speakers (Bluesound Professional BSP1000, etc.)
 *   - Team logo management
 */

// Fetch current settings for the form
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'scoreboard_%' OR setting_key LIKE 'spotify_%' OR setting_key LIKE 'subsonic_%' OR setting_key LIKE 'apple_music_%'");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $settings[$s['setting_key']] = $s['setting_value'] ?? '';
    }
} catch (PDOException $e) { /* ignore */ }

// Fetch existing team logos
$team_logos = [];
try {
    $stmt = $pdo->query("SELECT id, team_name, logo_url FROM teams WHERE logo_url IS NOT NULL AND logo_url != '' ORDER BY team_name");
    $team_logos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* ignore */ }
?>

<div class="sb-settings">
    <div class="sb-topbar">
        <div class="sb-topbar-brand">
            <img src="<?= htmlspecialchars($site_logo_url ?? '/images/logo.png') ?>" alt="Logo">
            <span>Scoreboard Settings</span>
        </div>
        <div class="sb-topbar-actions">
            <a href="scoreboard.php?view=scoreboard" class="sb-btn"><i class="fas fa-arrow-left"></i> Back to Scoreboard</a>
        </div>
    </div>

    <div class="sb-settings-content">

        <!-- ═══════════ MUSIC SOURCES ═══════════ -->
        <div class="sb-settings-section">
            <h3><i class="fab fa-spotify" style="color:#1DB954;"></i> Spotify</h3>
            <p class="sb-settings-desc">Connect Spotify Web Playback SDK for in-arena music.</p>
            <form id="sbSettingsSpotifyForm" onsubmit="return sbSaveSettings(event, 'spotify')">
                <div class="sb-settings-field">
                    <label>Client ID</label>
                    <input type="text" name="spotify_client_id" value="<?= htmlspecialchars($settings['spotify_client_id'] ?? '') ?>" placeholder="Spotify Client ID">
                </div>
                <div class="sb-settings-field">
                    <label>Client Secret</label>
                    <input type="password" name="spotify_client_secret" value="<?= htmlspecialchars($settings['spotify_client_secret'] ?? '') ?>" placeholder="Spotify Client Secret">
                </div>
                <button type="submit" class="sb-btn sb-btn-primary"><i class="fas fa-save"></i> Save Spotify</button>
            </form>
        </div>

        <div class="sb-settings-section">
            <h3><i class="fab fa-apple" style="color:#FC3C44;"></i> Apple Music</h3>
            <p class="sb-settings-desc">Connect Apple MusicKit JS for streaming. Requires an Apple Developer account.</p>
            <form id="sbSettingsAppleForm" onsubmit="return sbSaveSettings(event, 'apple_music')">
                <div class="sb-settings-field">
                    <label>Developer Token</label>
                    <input type="password" name="apple_music_token" value="<?= htmlspecialchars($settings['apple_music_token'] ?? '') ?>" placeholder="Apple Music Developer Token (JWT)">
                </div>
                <div class="sb-settings-field">
                    <label>Team ID</label>
                    <input type="text" name="apple_music_team_id" value="<?= htmlspecialchars($settings['apple_music_team_id'] ?? '') ?>" placeholder="Apple Developer Team ID">
                </div>
                <button type="submit" class="sb-btn sb-btn-primary"><i class="fas fa-save"></i> Save Apple Music</button>
            </form>
        </div>

        <div class="sb-settings-section">
            <h3><i class="fas fa-server" style="color:#3B82F6;"></i> Subsonic / Navidrome</h3>
            <p class="sb-settings-desc">Connect to a Subsonic-compatible music server (Subsonic, Navidrome, Airsonic).</p>
            <form id="sbSettingsSubsonicForm" onsubmit="return sbSaveSettings(event, 'subsonic')">
                <div class="sb-settings-field">
                    <label>Server URL</label>
                    <input type="url" name="subsonic_url" value="<?= htmlspecialchars($settings['subsonic_url'] ?? '') ?>" placeholder="https://music.example.com">
                </div>
                <div class="sb-settings-field">
                    <label>Username</label>
                    <input type="text" name="subsonic_username" value="<?= htmlspecialchars($settings['subsonic_username'] ?? '') ?>" placeholder="Username">
                </div>
                <div class="sb-settings-field">
                    <label>Password</label>
                    <input type="password" name="subsonic_password" value="<?= htmlspecialchars($settings['subsonic_password'] ?? '') ?>" placeholder="Password">
                </div>
                <button type="submit" class="sb-btn sb-btn-primary"><i class="fas fa-save"></i> Save Subsonic</button>
            </form>
        </div>

        <!-- ═══════════ BUZZER / HORN SOUND ═══════════ -->
        <div class="sb-settings-section">
            <h3><i class="fas fa-bullhorn" style="color:#F59E0B;"></i> Custom Buzzer / Horn Sound</h3>
            <p class="sb-settings-desc">Upload a custom buzzer or horn sound (MP3/WAV/OGG). Falls back to the synthesized tone if no custom sound is set.</p>
            <?php if (!empty($settings['scoreboard_buzzer_url'])): ?>
            <div class="sb-settings-current">
                <span>Current: <strong><?= htmlspecialchars(basename($settings['scoreboard_buzzer_url'])) ?></strong></span>
                <audio controls src="<?= htmlspecialchars($settings['scoreboard_buzzer_url']) ?>" style="height:32px;"></audio>
                <button type="button" class="sb-btn sb-btn-danger" onclick="sbRemoveBuzzerSound()" style="margin-left:8px;"><i class="fas fa-trash"></i> Remove</button>
            </div>
            <?php endif; ?>
            <form id="sbBuzzerUploadForm" onsubmit="return sbUploadBuzzerSound(event)">
                <div class="sb-settings-field">
                    <label>Upload Sound File</label>
                    <input type="file" id="sbBuzzerFile" name="buzzer_file" accept="audio/mpeg,audio/wav,audio/ogg,audio/mp3,.mp3,.wav,.ogg">
                </div>
                <button type="submit" class="sb-btn sb-btn-primary"><i class="fas fa-upload"></i> Upload Buzzer Sound</button>
            </form>
        </div>

        <!-- ═══════════ NETWORK SPEAKERS ═══════════ -->
        <div class="sb-settings-section">
            <h3><i class="fas fa-broadcast-tower" style="color:#8B5CF6;"></i> Network Speakers</h3>
            <p class="sb-settings-desc">Configure network audio outputs for the arena. Supports Bluesound Professional BSP1000, Sonos, and other network speakers via their HTTP APIs.</p>
            <form id="sbSettingsSpeakersForm" onsubmit="return sbSaveSettings(event, 'network_speakers')">
                <div id="sbNetworkSpeakersList">
                    <?php if (!empty($network_speakers)): ?>
                    <?php foreach ($network_speakers as $idx => $spk): ?>
                    <div class="sb-settings-speaker-row" data-idx="<?= $idx ?>">
                        <input type="text" name="speaker_name[]" value="<?= htmlspecialchars($spk['name'] ?? '') ?>" placeholder="Speaker name (e.g. Arena Main)">
                        <select name="speaker_type[]">
                            <option value="bluesound" <?= ($spk['type'] ?? '') === 'bluesound' ? 'selected' : '' ?>>Bluesound Professional BSP1000</option>
                            <option value="sonos" <?= ($spk['type'] ?? '') === 'sonos' ? 'selected' : '' ?>>Sonos</option>
                            <option value="generic" <?= ($spk['type'] ?? '') === 'generic' ? 'selected' : '' ?>>Generic HTTP</option>
                            <option value="browser" <?= ($spk['type'] ?? '') === 'browser' ? 'selected' : '' ?>>Browser Audio Output</option>
                        </select>
                        <input type="text" name="speaker_host[]" value="<?= htmlspecialchars($spk['host'] ?? '') ?>" placeholder="IP / hostname (e.g. 192.168.1.100)">
                        <input type="number" name="speaker_port[]" value="<?= htmlspecialchars($spk['port'] ?? '11000') ?>" placeholder="Port" min="1" max="65535" style="width:80px;">
                        <button type="button" class="sb-btn sb-btn-danger" onclick="this.parentElement.remove()" style="padding:6px 10px;"><i class="fas fa-trash"></i></button>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="sb-btn" onclick="sbAddSpeakerRow()" style="margin-bottom:12px;"><i class="fas fa-plus"></i> Add Speaker</button>
                <br>
                <button type="submit" class="sb-btn sb-btn-primary"><i class="fas fa-save"></i> Save Speakers</button>
            </form>
        </div>

        <!-- ═══════════ TEAM LOGOS ═══════════ -->
        <div class="sb-settings-section">
            <h3><i class="fas fa-shield-alt" style="color:#6B46C1;"></i> Team Logos</h3>
            <p class="sb-settings-desc">Upload team logos for display on the scoreboard. Browse existing logos or upload new ones.</p>
            <form id="sbLogoUploadForm" onsubmit="return sbUploadTeamLogo(event)">
                <div class="sb-settings-field">
                    <label>Team</label>
                    <select id="sbLogoTeamSelect" name="team_id">
                        <option value="">— Select Team —</option>
                        <?php foreach ($teams as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['team_name']) ?></option>
                        <?php endforeach; ?>
                        <option value="new">+ Create New Team…</option>
                    </select>
                </div>
                <div id="sbLogoNewTeamFields" style="display:none;">
                    <div class="sb-settings-field">
                        <label>New Team Name</label>
                        <input type="text" name="new_team_name" placeholder="Team name">
                    </div>
                </div>
                <div class="sb-settings-field">
                    <label>Logo Image</label>
                    <input type="file" id="sbLogoFile" name="logo_file" accept="image/png,image/jpeg,image/svg+xml,image/webp,.png,.jpg,.jpeg,.svg,.webp">
                </div>
                <button type="submit" class="sb-btn sb-btn-primary"><i class="fas fa-upload"></i> Upload Logo</button>
            </form>

            <?php if (!empty($team_logos)): ?>
            <div class="sb-settings-logos-grid">
                <?php foreach ($team_logos as $tl): ?>
                <div class="sb-settings-logo-card">
                    <img src="<?= htmlspecialchars($tl['logo_url']) ?>" alt="<?= htmlspecialchars($tl['team_name']) ?>">
                    <span><?= htmlspecialchars($tl['team_name']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:#555;font-size:13px;margin-top:12px;">No team logos uploaded yet.</p>
            <?php endif; ?>
        </div>

    </div><!-- /.sb-settings-content -->
</div>

<style>
.sb-settings {
    min-height: 100vh;
    min-height: 100dvh;
    background: #0A0A0F;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}
.sb-settings-content {
    max-width: 800px;
    margin: 0 auto;
    padding: clamp(12px, 2vw, 24px);
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.sb-settings-section {
    background: #111118;
    border: 1px solid #2D2D3F;
    border-radius: 12px;
    padding: clamp(14px, 2vw, 20px);
}
.sb-settings-section h3 {
    font-size: 16px;
    font-weight: 700;
    color: #E2E8F0;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sb-settings-desc {
    font-size: 13px;
    color: #8B8BA3;
    margin-bottom: 14px;
    line-height: 1.5;
}
.sb-settings-field {
    margin-bottom: 12px;
}
.sb-settings-field label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #A8A8B8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.sb-settings-field input,
.sb-settings-field select {
    width: 100%;
    padding: 10px 12px;
    border-radius: 6px;
    border: 1px solid #2D2D3F;
    background: #1A1A24;
    color: #fff;
    font-size: 14px;
    font-family: inherit;
}
.sb-settings-field input:focus,
.sb-settings-field select:focus {
    outline: none;
    border-color: #6B46C1;
}
.sb-settings-field input[type="file"] {
    padding: 8px;
    font-size: 13px;
}
.sb-settings-current {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: #0A0A0F;
    border: 1px solid #2D2D3F;
    border-radius: 8px;
    margin-bottom: 14px;
    font-size: 13px;
    color: #C4C4D4;
    flex-wrap: wrap;
}
.sb-settings-speaker-row {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
    align-items: center;
    flex-wrap: wrap;
}
.sb-settings-speaker-row input,
.sb-settings-speaker-row select {
    flex: 1;
    min-width: 100px;
    padding: 8px 10px;
    border-radius: 6px;
    border: 1px solid #2D2D3F;
    background: #1A1A24;
    color: #fff;
    font-size: 13px;
    font-family: inherit;
}
.sb-settings-logos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 12px;
    margin-top: 14px;
}
.sb-settings-logo-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 12px;
    background: #0A0A0F;
    border: 1px solid #2D2D3F;
    border-radius: 8px;
    text-align: center;
}
.sb-settings-logo-card img {
    width: 64px;
    height: 64px;
    object-fit: contain;
    border-radius: 4px;
}
.sb-settings-logo-card span {
    font-size: 11px;
    color: #A8A8B8;
    font-weight: 600;
}
</style>

<script>
function sbSaveSettings(e, section) {
    e.preventDefault();
    var form = e.target;
    var fd = new FormData(form);
    fd.append('section', section);

    fetch('process_scoreboard.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': CSRF_TOKEN
        },
        body: (function() {
            var params = new URLSearchParams();
            params.append('action', 'save_settings');
            params.append('section', section);
            for (var pair of fd.entries()) {
                params.append(pair[0], pair[1]);
            }
            return params;
        })()
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            alert('Settings saved successfully!');
        } else {
            alert(d.message || 'Failed to save settings');
        }
    });
    return false;
}

function sbUploadBuzzerSound(e) {
    e.preventDefault();
    var fileInput = document.getElementById('sbBuzzerFile');
    if (!fileInput.files.length) { alert('Please select a sound file.'); return false; }

    var fd = new FormData();
    fd.append('action', 'upload_buzzer');
    fd.append('buzzer_file', fileInput.files[0]);

    fetch('process_scoreboard.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': CSRF_TOKEN
        },
        body: fd
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            alert('Buzzer sound uploaded!');
            window.location.reload();
        } else {
            alert(d.message || 'Upload failed');
        }
    });
    return false;
}

function sbRemoveBuzzerSound() {
    if (!confirm('Remove custom buzzer sound?')) return;
    fetch('process_scoreboard.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': CSRF_TOKEN,
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=remove_buzzer'
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) window.location.reload();
    });
}

function sbUploadTeamLogo(e) {
    e.preventDefault();
    var fileInput = document.getElementById('sbLogoFile');
    if (!fileInput.files.length) { alert('Please select a logo image.'); return false; }

    var fd = new FormData();
    fd.append('action', 'upload_team_logo');
    fd.append('logo_file', fileInput.files[0]);
    fd.append('team_id', document.getElementById('sbLogoTeamSelect').value);
    var newName = document.querySelector('[name="new_team_name"]');
    if (newName) fd.append('new_team_name', newName.value);

    fetch('process_scoreboard.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': CSRF_TOKEN
        },
        body: fd
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            alert('Logo uploaded!');
            window.location.reload();
        } else {
            alert(d.message || 'Upload failed');
        }
    });
    return false;
}

function sbAddSpeakerRow() {
    var list = document.getElementById('sbNetworkSpeakersList');
    var row = document.createElement('div');
    row.className = 'sb-settings-speaker-row';
    row.innerHTML = '<input type="text" name="speaker_name[]" placeholder="Speaker name">' +
        '<select name="speaker_type[]">' +
            '<option value="bluesound">Bluesound Professional BSP1000</option>' +
            '<option value="sonos">Sonos</option>' +
            '<option value="generic">Generic HTTP</option>' +
            '<option value="browser">Browser Audio Output</option>' +
        '</select>' +
        '<input type="text" name="speaker_host[]" placeholder="IP / hostname">' +
        '<input type="number" name="speaker_port[]" value="11000" placeholder="Port" min="1" max="65535" style="width:80px;">' +
        '<button type="button" class="sb-btn sb-btn-danger" onclick="this.parentElement.remove()" style="padding:6px 10px;"><i class="fas fa-trash"></i></button>';
    list.appendChild(row);
}

// Show/hide new team name field
document.getElementById('sbLogoTeamSelect').addEventListener('change', function() {
    document.getElementById('sbLogoNewTeamFields').style.display = this.value === 'new' ? '' : 'none';
});
</script>
