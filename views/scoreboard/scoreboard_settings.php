<?php
/**
 * Scoreboard Settings View – Admin-Only Configuration
 *
 * Configure:
 *   - Music sources (Spotify, Apple Music, Subsonic)
 *   - Custom buzzer sound upload with library
 *   - Custom goal horn sound upload with library
 *   - Network speakers (Bluesound Professional BSP1000, etc.)
 *   - Team logo management
 */

// Fetch current settings for the form
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'scoreboard_%' OR setting_key LIKE 'spotify_%' OR setting_key LIKE 'subsonic_%' OR setting_key LIKE 'apple_music_%'");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $value = $s['setting_value'] ?? '';
        // Decrypt sensitive credentials for display
        if (in_array($s['setting_key'], ['spotify_client_secret', 'apple_music_token', 'subsonic_password']) && !empty($value)) {
            $value = decryptCredential($value);
        }
        $settings[$s['setting_key']] = $value;
    }
} catch (PDOException $e) { /* ignore */ }

// Parse buzzer and horn libraries
$buzzer_library = json_decode($settings['scoreboard_buzzer_library'] ?? '[]', true) ?: [];
$horn_library = json_decode($settings['scoreboard_horn_library'] ?? '[]', true) ?: [];

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

        <!-- ═══════════ BUZZER SOUND (End of Period) ═══════════ -->
        <div class="sb-settings-section">
            <h3><i class="fas fa-bell" style="color:#F59E0B;"></i> Buzzer Sound (End of Period)</h3>
            <p class="sb-settings-desc">The buzzer plays at the end of periods and during recurring shift-change alerts. Upload sounds to build a library, then select the active one.</p>
            <?php if (!empty($settings['scoreboard_buzzer_url'])): ?>
            <div class="sb-settings-current">
                <span>Active: <strong><?= htmlspecialchars(basename($settings['scoreboard_buzzer_url'])) ?></strong></span>
                <audio controls src="<?= htmlspecialchars($settings['scoreboard_buzzer_url']) ?>" style="height:32px;"></audio>
                <button type="button" class="sb-btn sb-btn-danger" onclick="sbRemoveBuzzerSound()" style="margin-left:8px;"><i class="fas fa-trash"></i> Remove Active</button>
            </div>
            <?php endif; ?>
            <?php if (!empty($buzzer_library)): ?>
            <div class="sb-settings-library">
                <span class="sb-settings-library-label">Buzzer Library</span>
                <?php foreach ($buzzer_library as $bi): ?>
                <div class="sb-settings-library-item<?= ($bi['url'] ?? '') === ($settings['scoreboard_buzzer_url'] ?? '') ? ' active' : '' ?>">
                    <span><?= htmlspecialchars($bi['name'] ?? basename($bi['url'] ?? '')) ?></span>
                    <audio controls src="<?= htmlspecialchars($bi['url'] ?? '') ?>" style="height:28px;"></audio>
                    <button type="button" class="sb-btn sb-btn-primary" onclick="sbSelectLibraryItem('buzzer',<?= htmlspecialchars(json_encode($bi['url'] ?? ''), ENT_QUOTES) ?>)" style="padding:4px 10px;font-size:12px;"><i class="fas fa-check"></i> Use</button>
                    <button type="button" class="sb-btn sb-btn-danger" onclick="sbRemoveLibraryItem('buzzer',<?= htmlspecialchars(json_encode($bi['url'] ?? ''), ENT_QUOTES) ?>)" style="padding:4px 8px;font-size:12px;"><i class="fas fa-trash"></i></button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <form id="sbBuzzerUploadForm" onsubmit="return sbUploadBuzzerSound(event)">
                <div class="sb-settings-field">
                    <label>Upload Sound Files (select multiple)</label>
                    <div class="sb-upload-zone" id="sbBuzzerDropZone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag &amp; drop audio files here or click to browse</p>
                        <span class="sb-upload-hint">Supported: MP3, WAV, OGG (select multiple)</span>
                        <input type="file" id="sbBuzzerFile" name="buzzer_file" accept="audio/mpeg,audio/wav,audio/ogg,audio/mp3,.mp3,.wav,.ogg" multiple style="display:none;">
                    </div>
                    <div class="sb-selected-files" id="sbBuzzerSelected" style="display:none;"></div>
                </div>
                <button type="submit" class="sb-btn sb-btn-primary"><i class="fas fa-upload"></i> Upload Buzzer Sound(s)</button>
            </form>
        </div>

        <!-- ═══════════ GOAL HORN SOUND ═══════════ -->
        <div class="sb-settings-section">
            <h3><i class="fas fa-bullhorn" style="color:#EF4444;"></i> Goal Horn Sound</h3>
            <p class="sb-settings-desc">The goal horn plays when a goal is scored. Upload sounds to build a library, then select the active one. Falls back to the buzzer if no horn is set.</p>
            <?php if (!empty($settings['scoreboard_horn_url'])): ?>
            <div class="sb-settings-current">
                <span>Active: <strong><?= htmlspecialchars(basename($settings['scoreboard_horn_url'])) ?></strong></span>
                <audio controls src="<?= htmlspecialchars($settings['scoreboard_horn_url']) ?>" style="height:32px;"></audio>
                <button type="button" class="sb-btn sb-btn-danger" onclick="sbRemoveHornSound()" style="margin-left:8px;"><i class="fas fa-trash"></i> Remove Active</button>
            </div>
            <?php endif; ?>
            <?php if (!empty($horn_library)): ?>
            <div class="sb-settings-library">
                <span class="sb-settings-library-label">Horn Library</span>
                <?php foreach ($horn_library as $hi): ?>
                <div class="sb-settings-library-item<?= ($hi['url'] ?? '') === ($settings['scoreboard_horn_url'] ?? '') ? ' active' : '' ?>">
                    <span><?= htmlspecialchars($hi['name'] ?? basename($hi['url'] ?? '')) ?></span>
                    <audio controls src="<?= htmlspecialchars($hi['url'] ?? '') ?>" style="height:28px;"></audio>
                    <button type="button" class="sb-btn sb-btn-primary" onclick="sbSelectLibraryItem('horn',<?= htmlspecialchars(json_encode($hi['url'] ?? ''), ENT_QUOTES) ?>)" style="padding:4px 10px;font-size:12px;"><i class="fas fa-check"></i> Use</button>
                    <button type="button" class="sb-btn sb-btn-danger" onclick="sbRemoveLibraryItem('horn',<?= htmlspecialchars(json_encode($hi['url'] ?? ''), ENT_QUOTES) ?>)" style="padding:4px 8px;font-size:12px;"><i class="fas fa-trash"></i></button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <form id="sbHornUploadForm" onsubmit="return sbUploadHornSound(event)">
                <div class="sb-settings-field">
                    <label>Upload Horn Sound Files (select multiple)</label>
                    <div class="sb-upload-zone" id="sbHornDropZone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag &amp; drop audio files here or click to browse</p>
                        <span class="sb-upload-hint">Supported: MP3, WAV, OGG (select multiple)</span>
                        <input type="file" id="sbHornFile" name="horn_file" accept="audio/mpeg,audio/wav,audio/ogg,audio/mp3,.mp3,.wav,.ogg" multiple style="display:none;">
                    </div>
                    <div class="sb-selected-files" id="sbHornSelected" style="display:none;"></div>
                </div>
                <button type="submit" class="sb-btn sb-btn-primary"><i class="fas fa-upload"></i> Upload Horn Sound(s)</button>
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
                    <div class="sb-upload-zone" id="sbLogoDropZone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag &amp; drop an image here or click to browse</p>
                        <span class="sb-upload-hint">Supported: PNG, JPG, SVG, WebP</span>
                        <input type="file" id="sbLogoFile" name="logo_file" accept="image/png,image/jpeg,image/svg+xml,image/webp,.png,.jpg,.jpeg,.svg,.webp" style="display:none;">
                    </div>
                    <div class="sb-selected-files" id="sbLogoSelected" style="display:none;"></div>
                </div>
                <button type="submit" class="sb-btn sb-btn-primary"><i class="fas fa-upload"></i> Upload Logo</button>
            </form>

            <?php if (!empty($team_logos)): ?>
            <div class="sb-settings-logos-grid">
                <?php foreach ($team_logos as $tl): ?>
                <div class="sb-settings-logo-card">
                    <img src="<?= htmlspecialchars($tl['logo_url']) ?>" alt="<?= htmlspecialchars($tl['team_name']) ?>">
                    <span><?= htmlspecialchars($tl['team_name']) ?></span>
                    <button type="button" class="sb-settings-logo-delete" data-team-id="<?= (int)$tl['id'] ?>" data-team-name="<?= htmlspecialchars($tl['team_name'], ENT_QUOTES) ?>" onclick="sbDeleteTeamLogo(this.dataset.teamId, this.dataset.teamName)" title="Remove logo">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:#555;font-size:13px;margin-top:12px;">No team logos uploaded yet.</p>
            <?php endif; ?>
        </div>

    </div><!-- /.sb-settings-content -->

    <!-- Upload Progress Modal (standard pattern) -->
    <div id="sbUploadProgressOverlay" class="upload-progress-overlay" style="display: none;">
        <div class="upload-progress-card">
            <div class="spinner" id="sbUploadSpinner"></div>
            <h4 id="sbUploadTitle">Uploading...</h4>
            <p class="upload-progress-text" id="sbUploadSubtext">Please do not close this page.</p>
            <div class="upload-progress-bar-container">
                <div class="upload-progress-bar" id="sbUploadProgressBar"></div>
            </div>
            <span class="upload-progress-percent" id="sbUploadProgressPercent">0%</span>
            <span class="upload-progress-status" id="sbUploadProgressStatus">Preparing upload...</span>
        </div>
    </div>
</div>

<style>
.sb-settings {
    min-height: 100vh;
    min-height: 100dvh;
    background: #0A0A0F;
    /* No overflow-y here – let the html element be the sole scroll container.
       Setting overflow-y:auto here created a competing scroll container that
       broke touch scrolling on tablets/phones and mouse-wheel scrolling. */
    overflow: visible !important;
    padding-bottom: 40px;
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
    box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.3);
}
.sb-settings-field input[type="file"] {
    padding: 8px;
    font-size: 13px;
}
/* ── Drag-and-drop upload zone (matches record_video style) ── */
.sb-upload-zone {
    border: 2px dashed #2D2D3F;
    border-radius: 12px;
    padding: 36px 24px;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.3s ease, background 0.3s ease;
}
.sb-upload-zone:hover {
    border-color: #6B46C1;
    background: rgba(107, 70, 193, 0.05);
}
.sb-upload-zone.sb-drag-over {
    border-color: #6B46C1;
    background: rgba(107, 70, 193, 0.1);
}
.sb-upload-zone > i {
    font-size: 40px;
    color: #6B46C1;
    opacity: 0.5;
    display: block;
    margin-bottom: 12px;
}
.sb-upload-zone > p {
    color: #A8A8B8;
    margin: 0 0 6px;
    font-size: 14px;
}
.sb-upload-hint {
    font-size: 12px;
    color: #666680;
}
.sb-selected-files {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 8px;
}
.sb-selected-file {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: rgba(107, 70, 193, 0.1);
    border: 1px solid #6B46C1;
    border-radius: 8px;
}
.sb-selected-file i {
    font-size: 18px;
    color: #6B46C1;
}
.sb-selected-file span {
    flex: 1;
    color: #E2E8F0;
    font-size: 13px;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.sb-selected-file .sb-file-remove {
    background: transparent;
    border: none;
    color: #8B8BA3;
    cursor: pointer;
    padding: 2px 6px;
    font-size: 14px;
    line-height: 1;
}
.sb-selected-file .sb-file-remove:hover {
    color: #EF4444;
}
/* ── Settings Buttons (match display controls) ───────── */
.sb-settings .sb-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 18px;
    font-size: 14px;
    font-weight: 600;
    font-family: inherit;
    border-radius: 8px;
    border: 1px solid #2D2D3F;
    background: #1A1A24;
    color: #C4C4D4;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s, transform 0.1s;
    text-decoration: none;
}
.sb-settings .sb-btn:hover {
    background: #222233;
    border-color: #6B46C1;
    color: #E2E8F0;
}
.sb-settings .sb-btn:active { transform: scale(0.97); }
.sb-settings .sb-btn-primary {
    background: #6B46C1;
    border-color: #6B46C1;
    color: #fff;
}
.sb-settings .sb-btn-primary:hover {
    background: #7C5DD4;
    border-color: #7C5DD4;
}
.sb-settings .sb-btn-danger {
    background: rgba(220, 38, 38, 0.1);
    border-color: #DC2626;
    color: #DC2626;
}
.sb-settings .sb-btn-danger:hover {
    background: rgba(220, 38, 38, 0.25);
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
    position: relative;
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
.sb-settings-logo-delete {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 1px solid #DC2626;
    background: rgba(220, 38, 38, 0.15);
    color: #DC2626;
    font-size: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.15s;
}
.sb-settings-logo-card:hover .sb-settings-logo-delete { opacity: 1; }
.sb-settings-logo-delete:hover {
    background: rgba(220, 38, 38, 0.35);
}
.sb-settings-library {
    margin-bottom: 14px;
}
.sb-settings-library-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #A8A8B8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}
.sb-settings-library-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    background: #0A0A0F;
    border: 1px solid #2D2D3F;
    border-radius: 6px;
    margin-bottom: 6px;
    font-size: 13px;
    color: #C4C4D4;
    flex-wrap: wrap;
}
.sb-settings-library-item.active {
    border-color: #6B46C1;
    background: rgba(107,70,193,0.08);
}
.sb-settings-library-item span {
    flex: 1;
    min-width: 80px;
}
/* Upload Progress Overlay */
.upload-progress-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}
.upload-progress-card {
    background: #0d1117;
    border: 1px solid #1e293b;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    max-width: 420px;
    width: 90%;
}
.upload-progress-card .spinner {
    width: 36px;
    height: 36px;
    margin: 0 auto 16px;
    border: 3px solid #1e293b;
    border-top-color: #7c3aed;
    border-radius: 50%;
    animation: upload-spin 0.8s linear infinite;
}
@keyframes upload-spin {
    to { transform: rotate(360deg); }
}
.upload-progress-card h4 {
    color: #fff;
    font-size: 18px;
    margin-bottom: 8px;
}
.upload-progress-text {
    color: #64748b;
    font-size: 13px;
    margin-bottom: 20px;
}
.upload-progress-bar-container {
    width: 100%;
    height: 8px;
    background: #06080b;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 8px;
}
.upload-progress-bar {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, #7c3aed, #a78bfa);
    border-radius: 4px;
    transition: width 0.4s ease;
}
.upload-progress-percent {
    display: block;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 4px;
}
.upload-progress-status {
    color: #64748b;
    font-size: 12px;
}
</style>

<script>
// ── Upload progress modal helpers ──────────────────
function sbShowUploadProgress(title, subtext) {
    var overlay = document.getElementById('sbUploadProgressOverlay');
    document.getElementById('sbUploadTitle').textContent = title || 'Uploading...';
    document.getElementById('sbUploadSubtext').textContent = subtext || 'Please do not close this page.';
    document.getElementById('sbUploadProgressBar').style.width = '0%';
    document.getElementById('sbUploadProgressPercent').textContent = '0%';
    document.getElementById('sbUploadProgressStatus').textContent = 'Preparing upload...';
    overlay.style.display = 'flex';
}
function sbUpdateUploadProgress(pct) {
    document.getElementById('sbUploadProgressBar').style.width = pct + '%';
    document.getElementById('sbUploadProgressPercent').textContent = pct + '%';
    document.getElementById('sbUploadProgressStatus').textContent = pct < 100 ? 'Uploading... ' + pct + '%' : 'Finalizing...';
}
function sbHideUploadProgress() {
    document.getElementById('sbUploadProgressOverlay').style.display = 'none';
}
function sbUploadWithProgress(fd, title, subtext, onSuccess, onError) {
    sbShowUploadProgress(title, subtext);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'process_scoreboard.php', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('X-CSRF-Token', CSRF_TOKEN);
    xhr.upload.onprogress = function(ev) {
        if (ev.lengthComputable) {
            var pct = Math.round((ev.loaded / ev.total) * 100);
            sbUpdateUploadProgress(pct);
        }
    };
    xhr.onload = function() {
        sbHideUploadProgress();
        try {
            var d = JSON.parse(xhr.responseText);
            if (xhr.status >= 200 && xhr.status < 300 && d.success) {
                onSuccess(d);
            } else {
                onError(d.message || 'Upload failed');
            }
        } catch (e) {
            onError('Invalid server response');
        }
    };
    xhr.onerror = function() {
        sbHideUploadProgress();
        onError('Network error – please try again.');
    };
    xhr.send(fd);
}

function sbSaveSettings(e, section) {
    e.preventDefault();
    var form = e.target;
    var fd = new FormData(form);
    fd.append('action', 'save_settings');
    fd.append('csrf_token', CSRF_TOKEN);
    fd.append('section', section);

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
            alert('Settings saved successfully!');
        } else {
            alert(d.message || 'Failed to save settings');
        }
    }).catch(function(err) {
        alert('Network error – please try again.');
    });
    return false;
}

function sbUploadBuzzerSound(e) {
    e.preventDefault();
    var fileInput = document.getElementById('sbBuzzerFile');
    if (!fileInput.files.length) { alert('Please select one or more sound files.'); return false; }

    var fd = new FormData();
    fd.append('action', 'upload_buzzer');
    fd.append('csrf_token', CSRF_TOKEN);
    for (var i = 0; i < fileInput.files.length; i++) {
        fd.append('buzzer_file[]', fileInput.files[i]);
    }

    sbUploadWithProgress(fd, 'Uploading Buzzer Sound...', 'Uploading your buzzer sound files. Please wait.',
        function(d) {
            var msg = (d.count && d.count > 1) ? d.count + ' buzzer sounds uploaded!' : 'Buzzer sound uploaded!';
            alert(msg);
            window.location.reload();
        },
        function(errMsg) { alert(errMsg); }
    );
    return false;
}

function sbRemoveBuzzerSound() {
    if (!confirm('Remove active buzzer sound?')) return;
    fetch('process_scoreboard.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': CSRF_TOKEN,
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=remove_buzzer&csrf_token=' + encodeURIComponent(CSRF_TOKEN)
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) window.location.reload();
        else alert(d.message || 'Failed to remove buzzer');
    }).catch(function() { alert('Network error – please try again.'); });
}

function sbUploadHornSound(e) {
    e.preventDefault();
    var fileInput = document.getElementById('sbHornFile');
    if (!fileInput.files.length) { alert('Please select one or more horn sound files.'); return false; }

    var fd = new FormData();
    fd.append('action', 'upload_horn');
    fd.append('csrf_token', CSRF_TOKEN);
    for (var i = 0; i < fileInput.files.length; i++) {
        fd.append('horn_file[]', fileInput.files[i]);
    }

    sbUploadWithProgress(fd, 'Uploading Goal Horn...', 'Uploading your goal horn sound files. Please wait.',
        function(d) {
            var msg = (d.count && d.count > 1) ? d.count + ' goal horn sounds uploaded!' : 'Goal horn sound uploaded!';
            alert(msg);
            window.location.reload();
        },
        function(errMsg) { alert(errMsg); }
    );
    return false;
}

function sbRemoveHornSound() {
    if (!confirm('Remove active goal horn sound?')) return;
    fetch('process_scoreboard.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': CSRF_TOKEN,
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=remove_horn&csrf_token=' + encodeURIComponent(CSRF_TOKEN)
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) window.location.reload();
        else alert(d.message || 'Failed to remove horn');
    }).catch(function() { alert('Network error – please try again.'); });
}

function sbSelectLibraryItem(type, url) {
    var action = (type === 'horn') ? 'select_horn' : 'select_buzzer';
    fetch('process_scoreboard.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': CSRF_TOKEN,
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=' + encodeURIComponent(action) + '&url=' + encodeURIComponent(url) + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN)
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) window.location.reload();
        else alert(d.message || 'Failed to select sound');
    }).catch(function() { alert('Network error – please try again.'); });
}

function sbRemoveLibraryItem(type, url) {
    if (!confirm('Remove this sound from the ' + type + ' library?')) return;
    var action = (type === 'horn') ? 'remove_horn_library_item' : 'remove_buzzer_library_item';
    fetch('process_scoreboard.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': CSRF_TOKEN,
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=' + encodeURIComponent(action) + '&url=' + encodeURIComponent(url) + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN)
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) window.location.reload();
        else alert(d.message || 'Failed to remove sound');
    }).catch(function() { alert('Network error – please try again.'); });
}

function sbUploadTeamLogo(e) {
    e.preventDefault();
    var fileInput = document.getElementById('sbLogoFile');
    if (!fileInput.files.length) { alert('Please select a logo image.'); return false; }

    var fd = new FormData();
    fd.append('action', 'upload_team_logo');
    fd.append('csrf_token', CSRF_TOKEN);
    fd.append('logo_file', fileInput.files[0]);
    fd.append('team_id', document.getElementById('sbLogoTeamSelect').value);
    var newName = document.querySelector('[name="new_team_name"]');
    if (newName) fd.append('new_team_name', newName.value);

    sbUploadWithProgress(fd, 'Uploading Team Logo...', 'Uploading your team logo image. Please wait.',
        function(d) {
            alert('Logo uploaded!');
            window.location.reload();
        },
        function(errMsg) { alert(errMsg); }
    );
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

function sbDeleteTeamLogo(teamId, teamName) {
    if (!confirm('Remove the logo for ' + teamName + '?')) return;
    fetch('process_scoreboard.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': CSRF_TOKEN,
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=delete_team_logo&team_id=' + encodeURIComponent(teamId) + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN)
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            window.location.reload();
        } else {
            alert(d.message || 'Failed to remove logo');
        }
    }).catch(function() { alert('Network error – please try again.'); });
}

// ── Drag-and-drop upload zone helpers ──
function sbFilterDroppedFiles(fileList, acceptAttr) {
    var types = acceptAttr.split(',').map(function(t) { return t.trim().toLowerCase(); });
    var dt = new DataTransfer();
    for (var i = 0; i < fileList.length; i++) {
        var f = fileList[i];
        var ext = '.' + f.name.split('.').pop().toLowerCase();
        var mime = f.type.toLowerCase();
        var ok = types.some(function(t) { return t === mime || t === ext; });
        if (ok) dt.items.add(f);
    }
    return dt.files;
}

function sbInitUploadZone(zoneId, inputId, selectedId, iconClass) {
    var zone = document.getElementById(zoneId);
    var input = document.getElementById(inputId);
    var selectedContainer = document.getElementById(selectedId);
    if (!zone || !input || !selectedContainer) return;

    zone.addEventListener('click', function() { input.click(); });

    zone.addEventListener('dragover', function(e) {
        e.preventDefault();
        zone.classList.add('sb-drag-over');
    });
    zone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        zone.classList.remove('sb-drag-over');
    });
    zone.addEventListener('drop', function(e) {
        e.preventDefault();
        zone.classList.remove('sb-drag-over');
        if (e.dataTransfer.files.length) {
            var accept = input.getAttribute('accept') || '';
            var validFiles = sbFilterDroppedFiles(e.dataTransfer.files, accept);
            if (validFiles.length) {
                input.files = validFiles;
                sbShowSelectedFiles(zone, input, selectedContainer, iconClass);
            }
        }
    });
    input.addEventListener('change', function() {
        if (input.files.length) {
            sbShowSelectedFiles(zone, input, selectedContainer, iconClass);
        }
    });
}

function sbShowSelectedFiles(zone, input, container, iconClass) {
    container.innerHTML = '';
    for (var i = 0; i < input.files.length; i++) {
        var file = input.files[i];
        var sizeMB = (file.size / (1024 * 1024)).toFixed(1);
        var item = document.createElement('div');
        item.className = 'sb-selected-file';
        item.innerHTML = '<i class="fas ' + iconClass + '"></i>' +
            '<span>' + file.name + ' (' + sizeMB + ' MB)</span>' +
            '<button type="button" class="sb-file-remove" title="Remove"><i class="fas fa-times"></i></button>';
        container.appendChild(item);
    }
    container.style.display = 'flex';
    zone.style.display = 'none';

    container.querySelectorAll('.sb-file-remove').forEach(function(btn) {
        btn.addEventListener('click', function() {
            input.value = '';
            container.style.display = 'none';
            container.innerHTML = '';
            zone.style.display = '';
        });
    });
}

sbInitUploadZone('sbBuzzerDropZone', 'sbBuzzerFile', 'sbBuzzerSelected', 'fa-music');
sbInitUploadZone('sbHornDropZone', 'sbHornFile', 'sbHornSelected', 'fa-bullhorn');
sbInitUploadZone('sbLogoDropZone', 'sbLogoFile', 'sbLogoSelected', 'fa-image');
</script>
