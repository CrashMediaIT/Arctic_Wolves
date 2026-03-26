<?php
/**
 * PWA Coach Stopwatch - Mobile-native stopwatch tool for coaches
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAnyCoach):
?>
<style>
.m-denied { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; color: #6B6B7B; font-family: Inter, sans-serif; text-align: center; }
.m-denied i { font-size: 48px; margin-bottom: 16px; }
.m-denied p { font-size: 15px; margin: 0; }
</style>
<div class="m-denied">
    <i class="fas fa-lock"></i>
    <p>Access denied</p>
</div>
<?php
    return;
endif;

// Fetch all active users for athlete assignment dropdown
$athletes = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name
        FROM users u
        WHERE u.is_active = 1
        ORDER BY u.last_name, u.first_name
    ");
    $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $athletes = decryptUserRows($athletes);
} catch (Exception $e) {
    $athletes = [];
}

// Fetch skills with stopwatch enabled for optional skill linking
$stopwatch_skills = [];
try {
    $stmt = $pdo->query("
        SELECT es.id, es.name, ec.name as category_name
        FROM eval_skills es
        JOIN eval_categories ec ON es.category_id = ec.id
        WHERE es.has_stopwatch = 1 AND es.is_active = 1
        ORDER BY ec.name, es.name
    ");
    $stopwatch_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stopwatch_skills = [];
}
?>
<style>
.m-stopwatch { padding: 16px; font-family: Inter, sans-serif; text-align: center; }
.m-stopwatch-header { margin-bottom: 24px; }
.m-stopwatch-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-stopwatch-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-time-display {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 20px;
    padding: 32px 16px; margin-bottom: 24px;
}
.m-time-value {
    font-size: 52px; font-weight: 700; color: #fff;
    font-variant-numeric: tabular-nums; letter-spacing: 2px;
    font-family: 'Inter', monospace;
}
.m-time-ms {
    font-size: 28px; color: #8B5CF6; font-weight: 600;
}
.m-sw-controls { display: flex; gap: 12px; justify-content: center; margin-bottom: 24px; flex-wrap: wrap; }
.m-sw-btn {
    min-width: 56px; min-height: 56px; border-radius: 50%;
    border: none; cursor: pointer; font-size: 18px;
    display: flex; align-items: center; justify-content: center;
    font-family: Inter, sans-serif; font-weight: 600;
    padding: 0; width: 64px; height: 64px;
}
.m-sw-btn-start { background: #10B981; color: #fff; }
.m-sw-btn-stop { background: #EF4444; color: #fff; }
.m-sw-btn-lap { background: rgba(59,130,246,0.2); color: #3B82F6; border: 1px solid rgba(59,130,246,0.3); }
.m-sw-btn-reset { background: rgba(168,168,184,0.15); color: #A8A8B8; border: 1px solid #2D2D3F; }
.m-sw-btn:active { transform: scale(0.95); }
.m-sw-save-section {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 16px;
    padding: 16px; margin-bottom: 24px; text-align: left;
}
.m-sw-save-input {
    width: 100%; min-height: 44px; padding: 12px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    margin-bottom: 10px; box-sizing: border-box;
}
.m-sw-save-input:focus { border-color: #8B5CF6; outline: none; }
.m-sw-save-btn {
    width: 100%; min-height: 44px; border-radius: 10px;
    background: #6B46C1; color: #fff; font-size: 14px; font-weight: 600;
    border: none; cursor: pointer; font-family: Inter, sans-serif;
}
.m-sw-save-btn:disabled { opacity: 0.5; }
.m-sw-save-btn:active { transform: scale(0.98); }
.m-sw-alert {
    padding: 10px 14px; border-radius: 10px; font-size: 13px; margin-top: 10px;
    display: none; text-align: center;
}
.m-sw-alert-success { background: rgba(16,185,129,0.15); color: #10B981; }
.m-sw-alert-error { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-lap-section { text-align: left; }
.m-lap-title {
    font-size: 13px; font-weight: 600; color: #6B6B7B;
    text-transform: uppercase; letter-spacing: 0.5px;
    margin: 0 0 10px; padding: 0 4px;
}
.m-lap-list { list-style: none; margin: 0; padding: 0; }
.m-lap-item {
    display: flex; justify-content: space-between; align-items: center;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    padding: 12px 14px; margin-bottom: 6px; min-height: 44px;
}
.m-lap-num { font-size: 13px; font-weight: 600; color: #8B5CF6; }
.m-lap-time { font-size: 14px; font-weight: 600; color: #fff; font-variant-numeric: tabular-nums; }
.m-lap-diff { font-size: 12px; color: #A8A8B8; font-variant-numeric: tabular-nums; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
.m-sw-selectors { margin-bottom: 20px; text-align: left; }
.m-sw-selectors label { display: block; font-size: 12px; font-weight: 600; color: #6B6B7B; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
.m-sw-select {
    width: 100%; min-height: 44px; padding: 12px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    margin-bottom: 12px; box-sizing: border-box;
    -webkit-appearance: none; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236B6B7B' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 12px center;
}
.m-sw-select:focus { border-color: #8B5CF6; outline: none; }
.m-sw-notes {
    width: 100%; min-height: 66px; padding: 12px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    margin-bottom: 10px; box-sizing: border-box; resize: vertical;
}
.m-sw-notes:focus { border-color: #8B5CF6; outline: none; }
.m-sw-history-section { text-align: left; margin-top: 24px; }
.m-sw-hist-item {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    padding: 12px 14px; margin-bottom: 6px; min-height: 44px;
}
.m-sw-hist-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-sw-hist-meta { font-size: 12px; color: #6B6B7B; margin-top: 2px; }
.m-sw-hist-laps { font-size: 12px; color: #8B5CF6; font-weight: 600; }

/* Mode Toggle */
.m-sw-mode-toggle {
    display: flex; background: #16161F; border: 1px solid #2D2D3F;
    border-radius: 12px; padding: 3px; margin-bottom: 20px; gap: 2px;
}
.m-sw-mode-btn {
    flex: 1; min-height: 44px; border: none; border-radius: 10px;
    background: transparent; color: #6B6B7B; font-size: 14px; font-weight: 600;
    font-family: Inter, sans-serif; cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
.m-sw-mode-btn.active { background: #8B5CF6; color: #fff; }
.m-sw-mode-btn:active { transform: scale(0.97); }

/* Countdown inputs */
.m-sw-countdown-setup { display: none; margin-bottom: 20px; }
.m-sw-countdown-setup.active { display: block; }
.m-sw-cd-inputs {
    display: flex; gap: 8px; justify-content: center; align-items: center;
    margin-bottom: 12px;
}
.m-sw-cd-field { text-align: center; }
.m-sw-cd-field label {
    display: block; font-size: 11px; color: #6B6B7B; text-transform: uppercase;
    letter-spacing: 0.5px; margin-bottom: 4px;
}
.m-sw-cd-input {
    width: 72px; min-height: 52px; padding: 8px; text-align: center;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 12px;
    color: #fff; font-size: 24px; font-weight: 700; font-family: Inter, monospace;
    box-sizing: border-box;
}
.m-sw-cd-input:focus { border-color: #8B5CF6; outline: none; }
.m-sw-cd-sep { font-size: 28px; color: #6B6B7B; padding-top: 18px; font-weight: 700; }
.m-sw-cd-set {
    min-height: 44px; padding: 0 20px; border-radius: 10px; border: none;
    background: #8B5CF6; color: #fff; font-size: 14px; font-weight: 600;
    font-family: Inter, sans-serif; cursor: pointer; margin-top: 18px;
}
.m-sw-cd-set:active { transform: scale(0.97); }
.m-sw-cd-presets {
    display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;
    margin-top: 10px;
}
.m-sw-cd-preset {
    min-height: 36px; padding: 0 14px; border-radius: 8px;
    background: rgba(139,92,246,0.15); color: #8B5CF6; font-size: 13px;
    font-weight: 600; border: 1px solid rgba(139,92,246,0.25);
    font-family: Inter, sans-serif; cursor: pointer;
}
.m-sw-cd-preset:active { transform: scale(0.95); }

/* Countdown flash animation */
@keyframes m-sw-flash {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}
.m-time-display.m-sw-flashing {
    animation: m-sw-flash 0.4s ease-in-out 6;
    border-color: #EF4444;
}

/* Multi-Athlete Watches */
.m-sw-multi-section {
    text-align: left; margin-top: 28px;
    border-top: 1px solid #2D2D3F; padding-top: 24px;
}
.m-sw-multi-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 12px;
}
.m-sw-add-watch-btn {
    min-height: 44px; padding: 0 16px; border-radius: 10px; border: none;
    background: rgba(139,92,246,0.15); color: #8B5CF6; font-size: 13px;
    font-weight: 600; font-family: Inter, sans-serif; cursor: pointer;
    display: flex; align-items: center; gap: 6px;
    border: 1px solid rgba(139,92,246,0.25);
}
.m-sw-add-watch-btn:active { transform: scale(0.97); }
.m-sw-add-watch-btn:disabled { opacity: 0.4; }
.m-sw-watch-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 14px;
    margin-bottom: 10px; overflow: hidden; transition: border-color 0.2s;
}
.m-sw-watch-card.m-sw-watch-running { border-color: #10B981; box-shadow: 0 0 12px rgba(16,185,129,0.12); }
.m-sw-watch-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 14px; cursor: pointer; min-height: 44px;
}
.m-sw-watch-head-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
.m-sw-watch-head-label {
    font-size: 14px; font-weight: 600; color: #fff;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.m-sw-watch-head-time { font-size: 14px; font-weight: 700; color: #8B5CF6; font-variant-numeric: tabular-nums; margin-right: 8px; }
.m-sw-watch-chevron { color: #6B6B7B; font-size: 12px; transition: transform 0.2s; }
.m-sw-watch-card.m-sw-watch-expanded .m-sw-watch-chevron { transform: rotate(180deg); }
.m-sw-watch-body { display: none; padding: 0 14px 14px; }
.m-sw-watch-card.m-sw-watch-expanded .m-sw-watch-body { display: block; }
.m-sw-watch-display {
    font-size: 36px; font-weight: 700; color: #fff; text-align: center;
    font-variant-numeric: tabular-nums; letter-spacing: 1px;
    font-family: 'Inter', monospace; padding: 12px 0;
}
.m-sw-watch-display .m-sw-watch-ms { font-size: 20px; color: #8B5CF6; }
.m-sw-watch-ctrls { display: flex; gap: 10px; justify-content: center; margin-bottom: 10px; flex-wrap: wrap; }
.m-sw-watch-ctrls .m-sw-btn { width: 52px; height: 52px; min-width: 52px; min-height: 52px; font-size: 16px; }
.m-sw-watch-select {
    width: 100%; min-height: 40px; padding: 10px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 8px;
    color: #fff; font-size: 13px; font-family: Inter, sans-serif;
    margin-bottom: 10px; box-sizing: border-box;
    -webkit-appearance: none; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236B6B7B' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center;
}
.m-sw-watch-select:focus { border-color: #8B5CF6; outline: none; }
.m-sw-watch-actions { display: flex; gap: 8px; margin-top: 8px; }
.m-sw-watch-action-btn {
    flex: 1; min-height: 38px; border-radius: 8px; border: none;
    font-size: 12px; font-weight: 600; font-family: Inter, sans-serif;
    cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px;
}
.m-sw-watch-save-btn { background: rgba(107,70,193,0.2); color: #8B5CF6; border: 1px solid rgba(107,70,193,0.3); }
.m-sw-watch-remove-btn { background: rgba(239,68,68,0.12); color: #EF4444; border: 1px solid rgba(239,68,68,0.2); }
.m-sw-watch-laps-list { list-style: none; margin: 8px 0 0; padding: 0; max-height: 150px; overflow-y: auto; }
.m-sw-watch-laps-list .m-lap-item { padding: 8px 10px; margin-bottom: 4px; min-height: 36px; }
.m-sw-watch-laps-list .m-lap-num { font-size: 12px; }
.m-sw-watch-laps-list .m-lap-time { font-size: 13px; }
.m-sw-watch-laps-list .m-lap-diff { font-size: 11px; }
.m-sw-watch-empty { text-align: center; padding: 20px; color: #6B6B7B; font-size: 13px; }

/* Session detail expansion */
.m-sw-hist-item { cursor: pointer; transition: border-color 0.2s; }
.m-sw-hist-item:active { border-color: #8B5CF6; }
.m-sw-hist-item.m-sw-hist-expanded { border-color: #8B5CF6; }
.m-sw-hist-detail { display: none; margin-top: 10px; padding-top: 10px; border-top: 1px solid #2D2D3F; }
.m-sw-hist-item.m-sw-hist-expanded .m-sw-hist-detail { display: block; }
.m-sw-hist-detail-loading { text-align: center; padding: 12px; color: #6B6B7B; font-size: 13px; }
.m-sw-hist-lap-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 6px 0; border-bottom: 1px solid rgba(45,45,63,0.5);
    font-size: 13px;
}
.m-sw-hist-lap-row:last-child { border-bottom: none; }
.m-sw-hist-lap-num { color: #8B5CF6; font-weight: 600; font-size: 12px; min-width: 50px; }
.m-sw-hist-lap-split { color: #A8A8B8; font-variant-numeric: tabular-nums; }
.m-sw-hist-lap-total { color: #fff; font-weight: 600; font-variant-numeric: tabular-nums; }
.m-sw-hist-toggle { float: right; color: #6B6B7B; font-size: 11px; transition: transform 0.2s; }
.m-sw-hist-item.m-sw-hist-expanded .m-sw-hist-toggle { transform: rotate(180deg); }

/* Camera Trigger */
.m-sw-camera-section {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 14px;
    padding: 14px; margin-bottom: 24px; text-align: left;
}
.m-sw-camera-toggle {
    width: 100%; min-height: 44px; border: 1px solid rgba(139,92,246,0.25);
    border-radius: 10px; background: rgba(139,92,246,0.12); color: #8B5CF6;
    font-size: 13px; font-weight: 600; cursor: pointer;
    font-family: Inter, sans-serif;
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.m-sw-camera-toggle.active { background: #8B5CF6; color: #fff; border-color: #8B5CF6; }
.m-sw-camera-toggle:active { transform: scale(0.98); }
.m-sw-camera-panel { display: none; margin-top: 14px; }
.m-sw-camera-panel.active { display: block; }
.m-sw-camera-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
.m-sw-camera-feed {
    position: relative; border-radius: 10px; overflow: hidden;
    background: #0A0A0F; border: 1px solid #2D2D3F;
}
.m-sw-camera-feed video {
    width: 100%; display: block; border-radius: 10px;
}
.m-sw-camera-feed canvas { display: none; }
.m-sw-camera-label {
    position: absolute; top: 6px; left: 6px;
    background: rgba(0,0,0,0.7); color: #fff;
    font-size: 10px; font-weight: 600; padding: 3px 8px;
    border-radius: 6px;
}
.m-sw-camera-label.armed { background: #10B981; }
.m-sw-camera-status {
    text-align: center; font-size: 12px; color: #A8A8B8;
    margin-bottom: 10px;
}
.m-sw-camera-select {
    width: 100%; min-height: 38px; padding: 8px 10px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 8px;
    color: #fff; font-size: 12px; font-family: Inter, sans-serif;
    margin-bottom: 8px; box-sizing: border-box;
}
.m-sw-camera-select:focus { border-color: #8B5CF6; outline: none; }
.m-sw-motion-bar {
    height: 4px; border-radius: 2px; background: #2D2D3F; overflow: hidden;
    margin-top: 4px;
}
.m-sw-motion-fill { height: 100%; background: #10B981; transition: width 0.1s; width: 0; }
</style>

<div class="m-stopwatch">
    <div class="m-stopwatch-header">
        <h2 class="m-stopwatch-title">Stopwatch</h2>
        <p class="m-stopwatch-sub" id="mSwSubtitle">Tap to start timing</p>
    </div>

    <!-- Mode Toggle: Stopwatch / Countdown -->
    <div class="m-sw-mode-toggle">
        <button class="m-sw-mode-btn active" id="mSwModeStopwatch" type="button" onclick="mSwSetMode('stopwatch')">
            <i class="fas fa-stopwatch"></i> Stopwatch
        </button>
        <button class="m-sw-mode-btn" id="mSwModeCountdown" type="button" onclick="mSwSetMode('countdown')">
            <i class="fas fa-hourglass-half"></i> Countdown
        </button>
    </div>

    <!-- Countdown Setup (hidden by default) -->
    <div class="m-sw-countdown-setup" id="mSwCountdownSetup">
        <div class="m-sw-cd-inputs">
            <div class="m-sw-cd-field">
                <label>Min</label>
                <input type="number" class="m-sw-cd-input" id="mSwCdMin" min="0" max="99" value="0" inputmode="numeric">
            </div>
            <span class="m-sw-cd-sep">:</span>
            <div class="m-sw-cd-field">
                <label>Sec</label>
                <input type="number" class="m-sw-cd-input" id="mSwCdSec" min="0" max="59" value="30" inputmode="numeric">
            </div>
            <button class="m-sw-cd-set" type="button" onclick="mSwCdSet()"><i class="fas fa-check"></i> Set</button>
        </div>
        <div class="m-sw-cd-presets">
            <button class="m-sw-cd-preset" type="button" onclick="mSwCdQuick(30)">30s</button>
            <button class="m-sw-cd-preset" type="button" onclick="mSwCdQuick(60)">1m</button>
            <button class="m-sw-cd-preset" type="button" onclick="mSwCdQuick(120)">2m</button>
            <button class="m-sw-cd-preset" type="button" onclick="mSwCdQuick(300)">5m</button>
            <button class="m-sw-cd-preset" type="button" onclick="mSwCdQuick(600)">10m</button>
        </div>
    </div>

    <div class="m-sw-selectors">
        <?php if (!empty($athletes)): ?>
        <label for="mSwAthlete">Assign to Athlete</label>
        <select class="m-sw-select" id="mSwAthlete">
            <option value="">— No athlete —</option>
            <?php foreach ($athletes as $a): ?>
            <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['last_name'] . ', ' . $a['first_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <?php if (!empty($stopwatch_skills)): ?>
        <label for="mSwSkill">Link to Skill</label>
        <select class="m-sw-select" id="mSwSkill">
            <option value="">— No skill —</option>
            <?php foreach ($stopwatch_skills as $sk): ?>
            <option value="<?= (int)$sk['id'] ?>"><?= htmlspecialchars($sk['category_name'] . ' — ' . $sk['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
    </div>

    <div class="m-time-display">
        <span class="m-time-value" id="mSwTime">00:00:00</span><span class="m-time-ms" id="mSwMs">.00</span>
    </div>

    <div class="m-sw-controls">
        <button class="m-sw-btn m-sw-btn-reset" id="mSwReset" type="button" onclick="mSwReset()" title="Reset">
            <i class="fas fa-redo"></i>
        </button>
        <button class="m-sw-btn m-sw-btn-start" id="mSwToggle" type="button" onclick="mSwToggle()" title="Start">
            <i class="fas fa-play" id="mSwToggleIcon"></i>
        </button>
        <button class="m-sw-btn m-sw-btn-lap" id="mSwLap" type="button" onclick="mSwLap()" title="Lap">
            <i class="fas fa-flag"></i>
        </button>
    </div>

    <!-- Camera Trigger Section -->
    <div class="m-sw-camera-section">
        <button type="button" class="m-sw-camera-toggle" id="mSwCameraToggle">
            <i class="fas fa-video"></i> Camera Trigger Mode
        </button>
        <div class="m-sw-camera-panel" id="mSwCameraPanel">
            <div class="m-sw-camera-status" id="mSwCameraStatus">Select cameras and press Activate to enable motion detection.</div>
            <div style="margin-bottom:10px;">
                <label style="font-size:11px;color:#6B6B7B;display:block;margin-bottom:4px;">Start Line Camera</label>
                <select class="m-sw-camera-select" id="mSwStartCamSelect"><option value="">Loading cameras...</option></select>
            </div>
            <div style="margin-bottom:10px;">
                <label style="font-size:11px;color:#6B6B7B;display:block;margin-bottom:4px;">Finish Line Camera</label>
                <select class="m-sw-camera-select" id="mSwFinishCamSelect"><option value="">Loading cameras...</option></select>
            </div>
            <div class="m-sw-camera-grid">
                <div class="m-sw-camera-feed" id="mSwStartFeed">
                    <video id="mSwStartVideo" autoplay muted playsinline></video>
                    <canvas id="mSwStartCanvas"></canvas>
                    <div class="m-sw-camera-label" id="mSwStartLabel">Start Line</div>
                    <div class="m-sw-motion-bar"><div class="m-sw-motion-fill" id="mSwStartMotion"></div></div>
                </div>
                <div class="m-sw-camera-feed" id="mSwFinishFeed">
                    <video id="mSwFinishVideo" autoplay muted playsinline></video>
                    <canvas id="mSwFinishCanvas"></canvas>
                    <div class="m-sw-camera-label" id="mSwFinishLabel">Finish Line</div>
                    <div class="m-sw-motion-bar"><div class="m-sw-motion-fill" id="mSwFinishMotion"></div></div>
                </div>
            </div>
            <button type="button" class="m-sw-camera-toggle" id="mSwCameraActivate" style="margin-top:4px;">
                <i class="fas fa-play"></i> Activate Cameras
            </button>
        </div>
    </div>

    <div class="m-sw-save-section" id="mSwSaveSection" style="display:none;">
        <input type="text" class="m-sw-save-input" id="mSwSessionName" placeholder="Session name (e.g., Sprint Drill)">
        <textarea class="m-sw-notes" id="mSwNotes" placeholder="Notes (optional)"></textarea>
        <button class="m-sw-save-btn" id="mSwSaveBtn" type="button" onclick="mSwSave()">
            <i class="fas fa-save"></i> Save Session
        </button>
        <div class="m-sw-alert" id="mSwAlert"></div>
    </div>

    <div class="m-lap-section">
        <h3 class="m-lap-title">Laps</h3>
        <ul class="m-lap-list" id="mSwLaps">
            <li class="m-empty-state"><i class="fas fa-flag"></i>No laps recorded</li>
        </ul>
    </div>

    <?php
    $recent_sessions = [];
    try {
        $coach_id = $_SESSION['user_id'] ?? 0;
        $stmt = $pdo->prepare("
            SELECT ss.id, ss.session_name, ss.created_at,
                   (SELECT COUNT(*) FROM stopwatch_times WHERE session_id = ss.id) as lap_count
            FROM stopwatch_sessions ss
            WHERE ss.coach_id = ?
            ORDER BY ss.created_at DESC LIMIT 10
        ");
        $stmt->execute([$coach_id]);
        $recent_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $recent_sessions = []; }
    ?>
    <?php if (!empty($recent_sessions)): ?>
    <div class="m-sw-history-section">
        <h3 class="m-lap-title">Saved Sessions</h3>
        <?php foreach ($recent_sessions as $sess): ?>
        <div class="m-sw-hist-item" onclick="mSwToggleSession(this, <?= (int)$sess['id'] ?>)" data-session-id="<?= (int)$sess['id'] ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div class="m-sw-hist-name"><?= htmlspecialchars($sess['session_name']) ?></div>
                    <div class="m-sw-hist-meta">
                        <?= date('M j, Y g:ia', strtotime($sess['created_at'])) ?>
                        &middot; <span class="m-sw-hist-laps"><?= (int)$sess['lap_count'] ?> laps</span>
                    </div>
                </div>
                <i class="fas fa-chevron-down m-sw-hist-toggle"></i>
            </div>
            <div class="m-sw-hist-detail" id="mSwHistDetail-<?= (int)$sess['id'] ?>"></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Multi-Athlete Watches -->
    <div class="m-sw-multi-section">
        <div class="m-sw-multi-header">
            <h3 class="m-lap-title" style="margin:0;">Multi-Athlete Watches</h3>
            <button class="m-sw-add-watch-btn" id="mSwAddWatchBtn" type="button" onclick="mSwAddWatch()">
                <i class="fas fa-plus"></i> Add Watch
            </button>
        </div>
        <div id="mSwWatchesContainer">
            <div class="m-sw-watch-empty" id="mSwWatchesEmpty">
                <i class="fas fa-users" style="font-size:24px;display:block;margin-bottom:8px;"></i>
                Tap <strong>Add Watch</strong> to create athlete-specific timers
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    /* ========================================
       Main Stopwatch State
       ======================================== */
    var running = false, startTime = 0, elapsed = 0, timer = null;
    var laps = [], lastLap = 0;
    var csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>';

    /* Mode: 'stopwatch' or 'countdown' */
    var currentMode = 'stopwatch';
    var cdTargetMs = 0;  // countdown target in ms
    var cdRunning = false;
    var cdStartTime = 0;
    var cdElapsed = 0;   // elapsed ms in countdown
    var cdTimer = null;

    var athleteJson = <?= json_encode(array_map(function($a) {
        return ['id' => (int)$a['id'], 'label' => ($a['last_name'] ?? '') . ', ' . ($a['first_name'] ?? '')];
    }, $athletes), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function pad(n, d) { return String(n).padStart(d || 2, '0'); }

    function formatTime(ms) {
        var h = Math.floor(ms / 3600000);
        var m = Math.floor((ms % 3600000) / 60000);
        var s = Math.floor((ms % 60000) / 1000);
        var cs = Math.floor((ms % 1000) / 10);
        return { main: pad(h) + ':' + pad(m) + ':' + pad(s), ms: '.' + pad(cs) };
    }

    function formatTimeMs(ms) {
        var t = formatTime(ms);
        return t.main + t.ms;
    }

    function escHtml(str) {
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    /* ========================================
       Stopwatch Mode (existing)
       ======================================== */
    function update() {
        var now = Date.now();
        var total = elapsed + (now - startTime);
        var t = formatTime(total);
        document.getElementById('mSwTime').textContent = t.main;
        document.getElementById('mSwMs').textContent = t.ms;
    }

    function showSaveSection() {
        document.getElementById('mSwSaveSection').style.display = (laps.length > 0 && !running) ? 'block' : 'none';
    }

    function showAlert(type, msg) {
        var el = document.getElementById('mSwAlert');
        el.className = 'm-sw-alert m-sw-alert-' + type;
        el.textContent = msg;
        el.style.display = 'block';
        setTimeout(function() { el.style.display = 'none'; }, 4000);
    }

    window.mSwToggle = function() {
        if (currentMode === 'countdown') { mSwCdToggle(); return; }
        var btn = document.getElementById('mSwToggle');
        var icon = document.getElementById('mSwToggleIcon');
        if (running) {
            clearInterval(timer);
            elapsed += Date.now() - startTime;
            running = false;
            btn.className = 'm-sw-btn m-sw-btn-start';
            icon.className = 'fas fa-play';
        } else {
            startTime = Date.now();
            running = true;
            timer = setInterval(update, 33);
            btn.className = 'm-sw-btn m-sw-btn-stop';
            icon.className = 'fas fa-pause';
        }
        showSaveSection();
    };

    window.mSwReset = function() {
        if (currentMode === 'countdown') { mSwCdReset(); return; }
        clearInterval(timer);
        running = false;
        elapsed = 0;
        startTime = 0;
        laps = [];
        lastLap = 0;
        document.getElementById('mSwTime').textContent = '00:00:00';
        document.getElementById('mSwMs').textContent = '.00';
        var btn = document.getElementById('mSwToggle');
        var icon = document.getElementById('mSwToggleIcon');
        btn.className = 'm-sw-btn m-sw-btn-start';
        icon.className = 'fas fa-play';
        document.getElementById('mSwLaps').innerHTML = '<li class="m-empty-state"><i class="fas fa-flag"></i>No laps recorded</li>';
        document.getElementById('mSwTime').parentElement.parentElement.classList.remove('m-sw-flashing');
        showSaveSection();
    };

    window.mSwLap = function() {
        if (currentMode === 'countdown' || !running) return;
        var total = elapsed + (Date.now() - startTime);
        var diff = total - lastLap;
        lastLap = total;
        laps.push({ number: laps.length + 1, lapTimeMs: diff, totalTimeMs: total });
        var list = document.getElementById('mSwLaps');
        if (laps.length === 1) list.innerHTML = '';
        var t = formatTime(total);
        var d = formatTime(diff);
        var li = document.createElement('li');
        li.className = 'm-lap-item';
        li.innerHTML = '<span class="m-lap-num">Lap ' + laps.length + '</span>' +
            '<span class="m-lap-diff">+' + d.main + d.ms + '</span>' +
            '<span class="m-lap-time">' + t.main + t.ms + '</span>';
        list.insertBefore(li, list.firstChild);
    };

    window.mSwSave = function() {
        var name = document.getElementById('mSwSessionName').value.trim();
        if (!name) { showAlert('error', 'Enter a session name'); return; }
        if (laps.length === 0) { showAlert('error', 'No laps to save'); return; }
        var btn = document.getElementById('mSwSaveBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        var fd = new FormData();
        fd.append('action', 'save_session');
        fd.append('csrf_token', csrfToken);
        fd.append('session_name', name);
        var athleteEl = document.getElementById('mSwAthlete');
        fd.append('athlete_id', athleteEl ? athleteEl.value : '');
        var skillEl = document.getElementById('mSwSkill');
        fd.append('skill_id', skillEl ? skillEl.value : '');
        fd.append('notes', (document.getElementById('mSwNotes').value || '').trim());
        fd.append('laps', JSON.stringify(laps));
        fetch('process_stopwatch.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    persistToast(data.message || 'Session saved!', 'success');
                    mSwReset();
                    document.getElementById('mSwSessionName').value = '';
                    document.getElementById('mSwNotes').value = '';
                    window.location.reload();
                } else { showAlert('error', data.message || 'Failed to save'); }
            })
            .catch(function() { showAlert('error', 'Failed to save session'); })
            .finally(function() { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Session'; });
    };

    /* ========================================
       Mode Toggle: Stopwatch / Countdown
       ======================================== */
    window.mSwSetMode = function(mode) {
        // Stop everything first
        if (running) { clearInterval(timer); elapsed += Date.now() - startTime; running = false; }
        if (cdRunning) { clearInterval(cdTimer); cdElapsed += Date.now() - cdStartTime; cdRunning = false; }

        currentMode = mode;
        var swBtn = document.getElementById('mSwModeStopwatch');
        var cdBtn = document.getElementById('mSwModeCountdown');
        var cdSetup = document.getElementById('mSwCountdownSetup');
        var lapBtn = document.getElementById('mSwLap');
        var subtitle = document.getElementById('mSwSubtitle');

        if (mode === 'countdown') {
            swBtn.classList.remove('active');
            cdBtn.classList.add('active');
            cdSetup.classList.add('active');
            lapBtn.style.display = 'none';
            subtitle.textContent = 'Set time and start countdown';
            mSwCdReset();
        } else {
            cdBtn.classList.remove('active');
            swBtn.classList.add('active');
            cdSetup.classList.remove('active');
            lapBtn.style.display = '';
            subtitle.textContent = 'Tap to start timing';
            mSwReset();
        }

        var btn = document.getElementById('mSwToggle');
        var icon = document.getElementById('mSwToggleIcon');
        btn.className = 'm-sw-btn m-sw-btn-start';
        icon.className = 'fas fa-play';
        showSaveSection();
    };

    /* ========================================
       Countdown Mode
       ======================================== */
    function mSwCdUpdateDisplay() {
        var remaining = cdTargetMs - cdElapsed - (cdRunning ? (Date.now() - cdStartTime) : 0);
        if (remaining < 0) remaining = 0;
        var t = formatTime(remaining);
        document.getElementById('mSwTime').textContent = t.main;
        document.getElementById('mSwMs').textContent = t.ms;
        if (remaining <= 0 && cdRunning) {
            mSwCdComplete();
        }
    }

    function mSwCdToggle() {
        var btn = document.getElementById('mSwToggle');
        var icon = document.getElementById('mSwToggleIcon');
        if (cdRunning) {
            clearInterval(cdTimer);
            cdElapsed += Date.now() - cdStartTime;
            cdRunning = false;
            btn.className = 'm-sw-btn m-sw-btn-start';
            icon.className = 'fas fa-play';
        } else {
            if (cdTargetMs <= 0) return;
            var remaining = cdTargetMs - cdElapsed;
            if (remaining <= 0) return;
            cdStartTime = Date.now();
            cdRunning = true;
            cdTimer = setInterval(mSwCdUpdateDisplay, 33);
            btn.className = 'm-sw-btn m-sw-btn-stop';
            icon.className = 'fas fa-pause';
        }
    }

    function mSwCdReset() {
        clearInterval(cdTimer);
        cdRunning = false;
        cdElapsed = 0;
        cdStartTime = 0;
        var btn = document.getElementById('mSwToggle');
        var icon = document.getElementById('mSwToggleIcon');
        btn.className = 'm-sw-btn m-sw-btn-start';
        icon.className = 'fas fa-play';
        document.getElementById('mSwTime').parentElement.parentElement.classList.remove('m-sw-flashing');
        // Show the set time or zero
        if (cdTargetMs > 0) {
            var t = formatTime(cdTargetMs);
            document.getElementById('mSwTime').textContent = t.main;
            document.getElementById('mSwMs').textContent = t.ms;
        } else {
            document.getElementById('mSwTime').textContent = '00:00:00';
            document.getElementById('mSwMs').textContent = '.00';
        }
    }

    function mSwCdComplete() {
        clearInterval(cdTimer);
        cdRunning = false;
        cdElapsed = cdTargetMs;
        document.getElementById('mSwTime').textContent = '00:00:00';
        document.getElementById('mSwMs').textContent = '.00';
        var btn = document.getElementById('mSwToggle');
        var icon = document.getElementById('mSwToggleIcon');
        btn.className = 'm-sw-btn m-sw-btn-start';
        icon.className = 'fas fa-play';

        // Flash display
        var display = document.getElementById('mSwTime').parentElement.parentElement;
        display.classList.add('m-sw-flashing');

        // Vibrate if available
        if (navigator.vibrate) {
            navigator.vibrate([200, 100, 200, 100, 400]);
        }

        // Audio beep
        try {
            var ac = new (window.AudioContext || window.webkitAudioContext)();
            function beep(freq, delay, dur) {
                setTimeout(function() {
                    var osc = ac.createOscillator();
                    var gain = ac.createGain();
                    osc.connect(gain);
                    gain.connect(ac.destination);
                    osc.frequency.value = freq;
                    osc.type = 'sine';
                    gain.gain.value = 0.3;
                    osc.start(ac.currentTime);
                    osc.stop(ac.currentTime + dur);
                }, delay);
            }
            beep(800, 0, 0.2);
            beep(800, 250, 0.2);
            beep(1000, 500, 0.5);
        } catch (e) { /* audio not available */ }
    }

    window.mSwCdSet = function() {
        var m = parseInt(document.getElementById('mSwCdMin').value) || 0;
        var s = parseInt(document.getElementById('mSwCdSec').value) || 0;
        var totalMs = (m * 60 + s) * 1000;
        if (totalMs <= 0) return;
        cdTargetMs = totalMs;
        mSwCdReset();
    };

    window.mSwCdQuick = function(seconds) {
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        document.getElementById('mSwCdMin').value = m;
        document.getElementById('mSwCdSec').value = s;
        cdTargetMs = seconds * 1000;
        mSwCdReset();
    };

    /* ========================================
       Session Detail Viewing
       ======================================== */
    var loadedSessions = {};

    window.mSwToggleSession = function(el, sessionId) {
        var wasExpanded = el.classList.contains('m-sw-hist-expanded');
        // Collapse all others
        var items = document.querySelectorAll('.m-sw-hist-item');
        for (var i = 0; i < items.length; i++) {
            items[i].classList.remove('m-sw-hist-expanded');
        }
        if (wasExpanded) return;

        el.classList.add('m-sw-hist-expanded');
        var detailEl = document.getElementById('mSwHistDetail-' + sessionId);

        if (loadedSessions[sessionId]) {
            detailEl.innerHTML = loadedSessions[sessionId];
            return;
        }

        detailEl.innerHTML = '<div class="m-sw-hist-detail-loading"><i class="fas fa-spinner fa-spin"></i> Loading laps...</div>';

        var fd = new FormData();
        fd.append('action', 'get_session');
        fd.append('csrf_token', csrfToken);
        fd.append('session_id', sessionId);
        fetch('process_stopwatch.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.times) {
                    var html = '';
                    if (data.times.length === 0) {
                        html = '<div class="m-sw-hist-detail-loading">No lap data</div>';
                    } else {
                        for (var i = 0; i < data.times.length; i++) {
                            var t = data.times[i];
                            html += '<div class="m-sw-hist-lap-row">' +
                                '<span class="m-sw-hist-lap-num">Lap ' + escHtml(String(t.lap_number)) + '</span>' +
                                '<span class="m-sw-hist-lap-split">+' + formatTimeMs(parseInt(t.lap_time_ms) || 0) + '</span>' +
                                '<span class="m-sw-hist-lap-total">' + formatTimeMs(parseInt(t.total_time_ms) || 0) + '</span>' +
                                '</div>';
                        }
                    }
                    loadedSessions[sessionId] = html;
                    detailEl.innerHTML = html;
                } else {
                    detailEl.innerHTML = '<div class="m-sw-hist-detail-loading" style="color:#EF4444;">Failed to load</div>';
                }
            })
            .catch(function() {
                detailEl.innerHTML = '<div class="m-sw-hist-detail-loading" style="color:#EF4444;">Failed to load</div>';
            });
    };

    /* ========================================
       Multi-Athlete Watches
       ======================================== */
    var mwWatches = [];
    var mwNextId = 1;
    var MW_MAX = 8;

    function mwUpdateAddBtn() {
        var btn = document.getElementById('mSwAddWatchBtn');
        btn.disabled = mwWatches.length >= MW_MAX;
        document.getElementById('mSwWatchesEmpty').style.display = mwWatches.length === 0 ? 'block' : 'none';
    }

    window.mSwAddWatch = function() {
        if (mwWatches.length >= MW_MAX) return;
        var id = mwNextId++;
        var watch = {
            id: id,
            running: false,
            startTime: 0,
            elapsed: 0,
            timer: null,
            laps: [],
            lastLap: 0,
            expanded: true,
            athleteId: ''
        };
        mwWatches.push(watch);
        mwRenderWatch(watch);
        mwUpdateAddBtn();
    };

    function mwGetWatch(id) {
        for (var i = 0; i < mwWatches.length; i++) {
            if (mwWatches[i].id === id) return mwWatches[i];
        }
        return null;
    }

    function mwBuildAthleteOptions(selectedId) {
        var html = '<option value="">— No athlete —</option>';
        for (var i = 0; i < athleteJson.length; i++) {
            var a = athleteJson[i];
            var sel = (String(a.id) === String(selectedId)) ? ' selected' : '';
            html += '<option value="' + a.id + '"' + sel + '>' + escHtml(a.label) + '</option>';
        }
        return html;
    }

    function mwRenderWatch(watch) {
        var container = document.getElementById('mSwWatchesContainer');
        var div = document.createElement('div');
        div.className = 'm-sw-watch-card m-sw-watch-expanded';
        div.id = 'mSwWatch-' + watch.id;
        div.innerHTML =
            '<div class="m-sw-watch-head" onclick="mSwWatchToggleCollapse(' + watch.id + ')">' +
                '<div class="m-sw-watch-head-left">' +
                    '<span class="m-sw-watch-head-label" id="mSwWatchLabel-' + watch.id + '">Watch ' + watch.id + '</span>' +
                '</div>' +
                '<span class="m-sw-watch-head-time" id="mSwWatchHeadTime-' + watch.id + '">00:00:00.00</span>' +
                '<i class="fas fa-chevron-down m-sw-watch-chevron"></i>' +
            '</div>' +
            '<div class="m-sw-watch-body">' +
                '<select class="m-sw-watch-select" id="mSwWatchAthlete-' + watch.id + '" onchange="mSwWatchAthleteChange(' + watch.id + ', this.value)">' +
                    mwBuildAthleteOptions('') +
                '</select>' +
                '<div class="m-sw-watch-display" id="mSwWatchTime-' + watch.id + '">' +
                    '<span id="mSwWatchTimeMain-' + watch.id + '">00:00:00</span>' +
                    '<span class="m-sw-watch-ms" id="mSwWatchTimeMs-' + watch.id + '">.00</span>' +
                '</div>' +
                '<div class="m-sw-watch-ctrls">' +
                    '<button class="m-sw-btn m-sw-btn-reset" type="button" onclick="mSwWatchReset(' + watch.id + ')" title="Reset"><i class="fas fa-redo"></i></button>' +
                    '<button class="m-sw-btn m-sw-btn-start" id="mSwWatchToggle-' + watch.id + '" type="button" onclick="mSwWatchToggle(' + watch.id + ')" title="Start"><i class="fas fa-play" id="mSwWatchToggleIcon-' + watch.id + '"></i></button>' +
                    '<button class="m-sw-btn m-sw-btn-lap" type="button" onclick="mSwWatchLap(' + watch.id + ')" title="Lap"><i class="fas fa-flag"></i></button>' +
                '</div>' +
                '<ul class="m-sw-watch-laps-list" id="mSwWatchLaps-' + watch.id + '"></ul>' +
                '<div class="m-sw-watch-actions">' +
                    '<button class="m-sw-watch-action-btn m-sw-watch-save-btn" type="button" onclick="mSwWatchSave(' + watch.id + ')"><i class="fas fa-save"></i> Save</button>' +
                    '<button class="m-sw-watch-action-btn m-sw-watch-remove-btn" type="button" onclick="mSwWatchRemove(' + watch.id + ')"><i class="fas fa-trash"></i> Remove</button>' +
                '</div>' +
            '</div>';
        container.appendChild(div);
    }

    window.mSwWatchToggleCollapse = function(id) {
        var card = document.getElementById('mSwWatch-' + id);
        if (card) card.classList.toggle('m-sw-watch-expanded');
    };

    window.mSwWatchAthleteChange = function(id, value) {
        var watch = mwGetWatch(id);
        if (!watch) return;
        watch.athleteId = value;
        var label = document.getElementById('mSwWatchLabel-' + id);
        if (value) {
            var sel = document.getElementById('mSwWatchAthlete-' + id);
            label.textContent = sel.options[sel.selectedIndex].text;
        } else {
            label.textContent = 'Watch ' + id;
        }
    };

    function mwUpdateDisplay(watch) {
        var total = watch.elapsed + (watch.running ? (Date.now() - watch.startTime) : 0);
        var t = formatTime(total);
        document.getElementById('mSwWatchTimeMain-' + watch.id).textContent = t.main;
        document.getElementById('mSwWatchTimeMs-' + watch.id).textContent = t.ms;
        document.getElementById('mSwWatchHeadTime-' + watch.id).textContent = t.main + t.ms;
    }

    window.mSwWatchToggle = function(id) {
        var watch = mwGetWatch(id);
        if (!watch) return;
        var btn = document.getElementById('mSwWatchToggle-' + id);
        var icon = document.getElementById('mSwWatchToggleIcon-' + id);
        var card = document.getElementById('mSwWatch-' + id);
        if (watch.running) {
            clearInterval(watch.timer);
            watch.elapsed += Date.now() - watch.startTime;
            watch.running = false;
            btn.className = 'm-sw-btn m-sw-btn-start';
            icon.className = 'fas fa-play';
            card.classList.remove('m-sw-watch-running');
            mwUpdateDisplay(watch);
        } else {
            watch.startTime = Date.now();
            watch.running = true;
            watch.timer = setInterval(function() { mwUpdateDisplay(watch); }, 33);
            btn.className = 'm-sw-btn m-sw-btn-stop';
            icon.className = 'fas fa-pause';
            card.classList.add('m-sw-watch-running');
        }
    };

    window.mSwWatchReset = function(id) {
        var watch = mwGetWatch(id);
        if (!watch) return;
        clearInterval(watch.timer);
        watch.running = false;
        watch.elapsed = 0;
        watch.startTime = 0;
        watch.laps = [];
        watch.lastLap = 0;
        var btn = document.getElementById('mSwWatchToggle-' + id);
        var icon = document.getElementById('mSwWatchToggleIcon-' + id);
        btn.className = 'm-sw-btn m-sw-btn-start';
        icon.className = 'fas fa-play';
        document.getElementById('mSwWatch-' + id).classList.remove('m-sw-watch-running');
        document.getElementById('mSwWatchTimeMain-' + id).textContent = '00:00:00';
        document.getElementById('mSwWatchTimeMs-' + id).textContent = '.00';
        document.getElementById('mSwWatchHeadTime-' + id).textContent = '00:00:00.00';
        document.getElementById('mSwWatchLaps-' + id).innerHTML = '';
    };

    window.mSwWatchLap = function(id) {
        var watch = mwGetWatch(id);
        if (!watch || !watch.running) return;
        var total = watch.elapsed + (Date.now() - watch.startTime);
        var diff = total - watch.lastLap;
        watch.lastLap = total;
        watch.laps.push({ number: watch.laps.length + 1, lapTimeMs: diff, totalTimeMs: total });
        var list = document.getElementById('mSwWatchLaps-' + id);
        var t = formatTime(total);
        var d = formatTime(diff);
        var li = document.createElement('li');
        li.className = 'm-lap-item';
        li.innerHTML = '<span class="m-lap-num">Lap ' + watch.laps.length + '</span>' +
            '<span class="m-lap-diff">+' + d.main + d.ms + '</span>' +
            '<span class="m-lap-time">' + t.main + t.ms + '</span>';
        list.insertBefore(li, list.firstChild);
    };

    window.mSwWatchSave = function(id) {
        var watch = mwGetWatch(id);
        if (!watch) return;
        if (watch.laps.length === 0) {
            showAlert('error', 'No laps to save for this watch');
            return;
        }
        var sel = document.getElementById('mSwWatchAthlete-' + id);
        var athleteName = sel.options[sel.selectedIndex].text;
        var sessionName = athleteName !== '— No athlete —' ? athleteName + ' - Stopwatch' : 'Watch ' + id + ' - Stopwatch';

        var fd = new FormData();
        fd.append('action', 'save_session');
        fd.append('csrf_token', csrfToken);
        fd.append('session_name', sessionName);
        fd.append('athlete_id', watch.athleteId || '');
        fd.append('skill_id', '');
        fd.append('notes', '');
        fd.append('laps', JSON.stringify(watch.laps));
        fetch('process_stopwatch.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showAlert('success', 'Watch ' + id + ' saved!');
                } else {
                    showAlert('error', data.message || 'Failed to save');
                }
            })
            .catch(function() { showAlert('error', 'Failed to save watch'); });
    };

    window.mSwWatchRemove = function(id) {
        var watch = mwGetWatch(id);
        if (!watch) return;
        if (watch.running) {
            clearInterval(watch.timer);
            watch.running = false;
        }
        mwWatches = mwWatches.filter(function(w) { return w.id !== id; });
        var el = document.getElementById('mSwWatch-' + id);
        if (el) el.parentNode.removeChild(el);
        mwUpdateAddBtn();
    };

    /* ── Camera Trigger ─────────────────────────────────────────────── */
    var camTrigger = null;
    var camActive = false;

    var camToggleBtn = document.getElementById('mSwCameraToggle');
    var camPanel = document.getElementById('mSwCameraPanel');
    var camActivateBtn = document.getElementById('mSwCameraActivate');
    var camStatus = document.getElementById('mSwCameraStatus');

    if (camToggleBtn) {
        camToggleBtn.addEventListener('click', function() {
            camPanel.classList.toggle('active');
            camToggleBtn.classList.toggle('active');
            if (camPanel.classList.contains('active')) {
                loadCameraList();
            }
        });
    }

    function loadCameraList() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
            camStatus.textContent = 'Camera not supported on this device.';
            return;
        }
        navigator.mediaDevices.getUserMedia({ video: true })
            .then(function(stream) {
                stream.getTracks().forEach(function(t) { t.stop(); });
                return navigator.mediaDevices.enumerateDevices();
            })
            .then(function(devices) {
                var cameras = devices.filter(function(d) { return d.kind === 'videoinput'; });
                var startSel = document.getElementById('mSwStartCamSelect');
                var finishSel = document.getElementById('mSwFinishCamSelect');
                startSel.innerHTML = '<option value="">Select camera...</option>';
                finishSel.innerHTML = '<option value="">Select camera...</option>';
                cameras.forEach(function(cam, i) {
                    var label = cam.label || ('Camera ' + (i + 1));
                    startSel.innerHTML += '<option value="' + cam.deviceId + '">' + label + '</option>';
                    finishSel.innerHTML += '<option value="' + cam.deviceId + '">' + label + '</option>';
                });
                if (cameras.length >= 2) {
                    startSel.selectedIndex = 1;
                    finishSel.selectedIndex = 2;
                } else if (cameras.length === 1) {
                    startSel.selectedIndex = 1;
                    finishSel.selectedIndex = 1;
                }
                camStatus.textContent = cameras.length + ' camera(s) found. Select cameras and activate.';
            })
            .catch(function(err) {
                camStatus.textContent = 'Camera access denied. Please allow camera permission.';
            });
    }

    if (camActivateBtn) {
        camActivateBtn.addEventListener('click', function() {
            if (camActive) {
                deactivateCamera();
                return;
            }
            var startDeviceId = document.getElementById('mSwStartCamSelect').value;
            var finishDeviceId = document.getElementById('mSwFinishCamSelect').value;
            if (!startDeviceId && !finishDeviceId) {
                camStatus.textContent = 'Please select at least one camera.';
                return;
            }
            activateCamera(startDeviceId, finishDeviceId);
        });
    }

    function activateCamera(startDeviceId, finishDeviceId) {
        camTrigger = new CameraTrigger({ sensitivity: 30, motionThreshold: 8 });
        camStatus.textContent = 'Activating cameras...';
        camActivateBtn.disabled = true;

        camTrigger.startMonitoring({
            startVideoEl: document.getElementById('mSwStartVideo'),
            finishVideoEl: document.getElementById('mSwFinishVideo'),
            startCanvasEl: document.getElementById('mSwStartCanvas'),
            finishCanvasEl: document.getElementById('mSwFinishCanvas'),
            startDeviceId: startDeviceId || undefined,
            finishDeviceId: finishDeviceId || startDeviceId || undefined,
            onStartTrigger: function() {
                if (!running) mSwToggle(); // Start the timer
                document.getElementById('mSwStartLabel').classList.remove('armed');
                document.getElementById('mSwFinishLabel').classList.add('armed');
            },
            onFinishTrigger: function() {
                if (running) mSwLap(); // Record finish/lap
                document.getElementById('mSwFinishLabel').classList.remove('armed');
                document.getElementById('mSwStartLabel').classList.add('armed');
                camTrigger.armStart();
            },
            onMotionLevel: function(which, level) {
                var el = document.getElementById(which === 'start' ? 'mSwStartMotion' : 'mSwFinishMotion');
                if (el) el.style.width = Math.min(level, 100) + '%';
            }
        }).then(function() {
            camActive = true;
            camStatus.textContent = 'Cameras active. Start line armed — motion will start timer.';
            camActivateBtn.innerHTML = '<i class="fas fa-stop"></i> Deactivate Cameras';
            camActivateBtn.classList.add('active');
            camActivateBtn.disabled = false;
            document.getElementById('mSwStartLabel').classList.add('armed');
        }).catch(function(err) {
            camStatus.textContent = 'Failed to start cameras: ' + (err.message || 'Unknown error');
            camActivateBtn.disabled = false;
        });
    }

    function deactivateCamera() {
        if (camTrigger) {
            camTrigger.stopMonitoring();
            camTrigger = null;
        }
        camActive = false;
        camStatus.textContent = 'Cameras deactivated.';
        camActivateBtn.innerHTML = '<i class="fas fa-play"></i> Activate Cameras';
        camActivateBtn.classList.remove('active');
        document.getElementById('mSwStartLabel').classList.remove('armed');
        document.getElementById('mSwFinishLabel').classList.remove('armed');
        document.getElementById('mSwStartMotion').style.width = '0';
        document.getElementById('mSwFinishMotion').style.width = '0';
    }

})();
</script>
<script src="js/camera_trigger.js"></script>
