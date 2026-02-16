<?php
/**
 * Coach Stopwatch - Full-featured sports stopwatch with lap timing and athlete assignment
 * Coaches Corner > Stopwatch
 */

$csrf_token = $_SESSION['csrf_token'] ?? '';
if (empty($csrf_token)) {
    $csrf_token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf_token;
}

// Fetch athletes for assignment dropdown
$athletes = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name
        FROM users u
        JOIN user_roles ur ON u.id = ur.user_id
        WHERE ur.role = 'athlete' AND u.is_active = 1
        ORDER BY u.last_name, u.first_name
    ");
    $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

// Fetch recent stopwatch sessions for history
$recent_sessions = [];
try {
    $coach_id = $_SESSION['user_id'] ?? 0;
    $stmt = $pdo->prepare("
        SELECT ss.id, ss.session_name, ss.notes, ss.created_at, ss.skill_id,
               es.name as skill_name,
               (SELECT COUNT(*) FROM stopwatch_times WHERE session_id = ss.id) as lap_count
        FROM stopwatch_sessions ss
        LEFT JOIN eval_skills es ON ss.skill_id = es.id
        WHERE ss.coach_id = ?
        ORDER BY ss.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$coach_id]);
    $recent_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_sessions = [];
}
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-stopwatch"></i> Stopwatch</h1>
        <p class="page-description">High-performance sports stopwatch with lap timing, time history, and athlete assignment</p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-secondary" id="sw-camera-toggle" onclick="swToggleCameraMode()">
            <i class="fas fa-video"></i> Camera Mode
        </button>
    </div>
</div>

<style>
    .sw-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-6);
        margin-top: var(--space-5);
    }
    @media (max-width: 992px) {
        .sw-container {
            grid-template-columns: 1fr;
        }
    }
    .sw-display-wrapper {
        text-align: center;
        padding: var(--space-8) var(--space-6);
    }
    .sw-time-display {
        font-family: 'Courier New', 'Consolas', monospace;
        font-size: 72px;
        font-weight: 900;
        color: var(--text-white);
        letter-spacing: 4px;
        margin-bottom: var(--space-6);
        text-shadow: 0 0 20px rgba(107, 70, 193, 0.4);
        user-select: none;
    }
    @media (max-width: 768px) {
        .sw-time-display {
            font-size: 48px;
        }
    }
    .sw-controls {
        display: flex;
        gap: var(--space-3);
        justify-content: center;
        flex-wrap: wrap;
    }
    .sw-btn {
        min-width: 120px;
        height: 50px;
        border: none;
        border-radius: var(--radius-lg);
        font-size: var(--font-size-md);
        font-weight: var(--font-weight-bold);
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-2);
    }
    .sw-btn-start {
        background: var(--success);
        color: #fff;
    }
    .sw-btn-start:hover {
        background: #0ea572;
        transform: translateY(-2px);
    }
    .sw-btn-stop {
        background: var(--error);
        color: #fff;
    }
    .sw-btn-stop:hover {
        background: #dc2626;
        transform: translateY(-2px);
    }
    .sw-btn-lap {
        background: var(--primary);
        color: #fff;
    }
    .sw-btn-lap:hover {
        background: var(--primary-hover);
        transform: translateY(-2px);
    }
    .sw-btn-reset {
        background: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border);
    }
    .sw-btn-reset:hover {
        background: var(--bg-card);
        border-color: var(--primary);
        transform: translateY(-1px);
    }
    .sw-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
        transform: none !important;
    }
    .sw-lap-table {
        width: 100%;
        border-collapse: collapse;
    }
    .sw-lap-table th {
        text-align: left;
        padding: var(--space-3) var(--space-4);
        font-size: var(--font-size-sm);
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid var(--border);
        font-weight: var(--font-weight-semibold);
    }
    .sw-lap-table td {
        padding: var(--space-3) var(--space-4);
        font-size: var(--font-size-base);
        color: var(--text-primary);
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .sw-lap-table tr:hover td {
        background: rgba(107, 70, 193, 0.05);
    }
    .sw-lap-time {
        font-family: 'Courier New', 'Consolas', monospace;
        font-weight: var(--font-weight-bold);
    }
    .sw-lap-best {
        color: var(--success);
    }
    .sw-lap-worst {
        color: var(--error);
    }
    .sw-empty {
        text-align: center;
        padding: var(--space-8);
        color: var(--text-muted);
    }
    .sw-assign-select {
        background: var(--bg-main);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        color: var(--text-primary);
        font-size: var(--font-size-sm);
        padding: 4px 8px;
        width: 100%;
        max-width: 180px;
    }
    .sw-session-form {
        display: flex;
        gap: var(--space-3);
        flex-wrap: wrap;
        align-items: flex-end;
    }
    .sw-session-form .form-group {
        flex: 1;
        min-width: 180px;
    }
    .sw-history-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--space-3) var(--space-4);
        border-bottom: 1px solid rgba(255,255,255,0.05);
        cursor: pointer;
        transition: background 0.15s;
    }
    .sw-history-item:hover {
        background: rgba(107, 70, 193, 0.05);
    }
    .sw-history-item:last-child {
        border-bottom: none;
    }
    .sw-history-name {
        font-weight: var(--font-weight-bold);
        color: var(--text-white);
    }
    .sw-history-meta {
        font-size: var(--font-size-sm);
        color: var(--text-muted);
    }
    .sw-history-laps {
        background: var(--primary);
        color: #fff;
        font-size: 12px;
        padding: 2px 8px;
        border-radius: 10px;
        font-weight: var(--font-weight-bold);
    }

    /* Camera Mode Styles */
    .sw-camera-panel {
        display: none;
        margin-bottom: var(--space-6);
    }
    .sw-camera-panel.active {
        display: block;
    }
    .sw-camera-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-5);
    }
    @media (max-width: 768px) {
        .sw-camera-grid {
            grid-template-columns: 1fr;
        }
    }
    .sw-camera-feed {
        position: relative;
        border-radius: var(--radius-lg);
        overflow: hidden;
        background: var(--bg-main);
        aspect-ratio: 4/3;
    }
    .sw-camera-feed video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .sw-camera-feed canvas {
        display: none;
    }
    .sw-camera-label {
        position: absolute;
        top: var(--space-2);
        left: var(--space-2);
        background: rgba(0,0,0,0.7);
        color: #fff;
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-bold);
        padding: 4px 10px;
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        gap: var(--space-2);
        z-index: 2;
    }
    .sw-camera-label .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--error);
    }
    .sw-camera-label .dot.live {
        background: var(--success);
        animation: camPulse 1.5s infinite;
    }
    @keyframes camPulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }
    .sw-camera-indicator {
        position: absolute;
        bottom: var(--space-2);
        right: var(--space-2);
        background: rgba(0,0,0,0.7);
        color: var(--text-muted);
        font-size: 11px;
        padding: 3px 8px;
        border-radius: var(--radius-sm);
        z-index: 2;
    }
    .sw-camera-indicator.triggered {
        background: var(--success);
        color: #fff;
    }
    .sw-motion-bar {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 4px;
        background: var(--primary);
        transition: width 0.1s;
        z-index: 2;
    }
    .sw-camera-setup {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-4);
        align-items: end;
    }
    @media (max-width: 768px) {
        .sw-camera-setup {
            grid-template-columns: 1fr;
        }
    }
    .sw-sensitivity-slider {
        width: 100%;
        accent-color: var(--primary);
        height: 6px;
    }
    .sw-cam-status {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2);
        font-size: var(--font-size-sm);
        color: var(--text-muted);
        padding: var(--space-2) var(--space-3);
        background: var(--bg-secondary);
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
    }
    .sw-cam-status.ready {
        color: var(--success);
        border-color: var(--success);
    }
    .sw-cam-status.active {
        color: var(--primary-light);
        border-color: var(--primary);
    }
    @keyframes pulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.05); opacity: 0.8; }
    }
</style>

<!-- Camera Mode Panel -->
<div id="sw-camera-panel" class="sw-camera-panel">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-video"></i> Dual-Camera Trigger Mode</h3>
            <span id="sw-cam-global-status" class="sw-cam-status">
                <i class="fas fa-circle" style="font-size:8px;"></i> Inactive
            </span>
        </div>
        <div class="card-body">
            <div class="alert alert-info" style="margin-bottom: var(--space-5);">
                <i class="fas fa-info-circle"></i>
                Use two separate cameras for precision timing. Camera 1 (Start Line) detects motion to start the timer. Camera 2 (Finish Line) detects motion to record a lap/stop. Position cameras at the start and finish of a sprint or drill.
            </div>

            <!-- Camera Selection -->
            <div class="sw-camera-setup" style="margin-bottom: var(--space-5);">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-play-circle" style="color:var(--success);"></i> Start Line Camera</label>
                    <select id="sw-cam-start-select" class="form-select">
                        <option value="">-- Select Camera --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-flag-checkered" style="color:var(--error);"></i> Finish Line Camera</label>
                    <select id="sw-cam-finish-select" class="form-select">
                        <option value="">-- Select Camera --</option>
                    </select>
                </div>
            </div>

            <!-- Sensitivity Controls -->
            <div class="sw-camera-setup" style="margin-bottom: var(--space-5);">
                <div class="form-group">
                    <label class="form-label">Motion Sensitivity: <span id="sw-cam-sensitivity-val">30</span></label>
                    <input type="range" id="sw-cam-sensitivity" class="sw-sensitivity-slider" min="5" max="100" value="30" oninput="swCamUpdateSensitivity(this.value)">
                    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);margin-top:2px;">
                        <span>Less Sensitive</span><span>More Sensitive</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Trigger Threshold: <span id="sw-cam-threshold-val">8</span>%</label>
                    <input type="range" id="sw-cam-threshold" class="sw-sensitivity-slider" min="1" max="50" value="8" oninput="swCamUpdateThreshold(this.value)">
                    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);margin-top:2px;">
                        <span>Hair Trigger</span><span>Requires More Motion</span>
                    </div>
                </div>
            </div>

            <!-- Camera Action Buttons -->
            <div style="display:flex; gap:var(--space-3); flex-wrap:wrap; margin-bottom: var(--space-5);">
                <button class="btn btn-primary" id="sw-cam-activate-btn" onclick="swCamActivate()">
                    <i class="fas fa-power-off"></i> Activate Cameras
                </button>
                <button class="btn btn-secondary" id="sw-cam-deactivate-btn" onclick="swCamDeactivate()" style="display:none;">
                    <i class="fas fa-stop"></i> Deactivate Cameras
                </button>
                <button class="btn btn-secondary" id="sw-cam-arm-btn" onclick="swCamArm()" style="display:none;">
                    <i class="fas fa-crosshairs"></i> Re-Arm Start
                </button>
            </div>

            <!-- Live Camera Feeds -->
            <div id="sw-cam-feeds" class="sw-camera-grid" style="display:none;">
                <div>
                    <div class="sw-camera-feed" id="sw-cam-start-feed">
                        <video id="sw-cam-start-video" autoplay playsinline muted></video>
                        <canvas id="sw-cam-start-canvas"></canvas>
                        <div class="sw-camera-label"><span class="dot" id="sw-cam-start-dot"></span> Start Line</div>
                        <div class="sw-camera-indicator" id="sw-cam-start-indicator">Waiting</div>
                        <div class="sw-motion-bar" id="sw-cam-start-motion" style="width:0%;"></div>
                    </div>
                </div>
                <div>
                    <div class="sw-camera-feed" id="sw-cam-finish-feed">
                        <video id="sw-cam-finish-video" autoplay playsinline muted></video>
                        <canvas id="sw-cam-finish-canvas"></canvas>
                        <div class="sw-camera-label"><span class="dot" id="sw-cam-finish-dot"></span> Finish Line</div>
                        <div class="sw-camera-indicator" id="sw-cam-finish-indicator">Waiting</div>
                        <div class="sw-motion-bar" id="sw-cam-finish-motion" style="width:0%;"></div>
                    </div>
                </div>
            </div>

            <div id="sw-cam-alert" class="alert alert-success" style="display:none; margin-top: var(--space-4);">
                <i class="fas fa-check-circle"></i> <span id="sw-cam-alert-msg"></span>
            </div>
        </div>
    </div>
</div>

<div class="sw-container">
    <!-- Stopwatch Panel -->
    <div>
        <div class="card">
            <div class="card-header">
                <h3 id="sw-mode-title"><i class="fas fa-stopwatch"></i> Stopwatch Mode</h3>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <label class="toggle-switch" style="margin: 0;">
                        <input type="checkbox" id="sw-mode-toggle" onchange="swToggleMode()">
                        <span class="toggle-slider"></span>
                    </label>
                    <span id="sw-mode-label" style="font-size: 14px; color: var(--text-muted);">Countdown</span>
                </div>
            </div>
            <div class="card-body sw-display-wrapper">
                <!-- Countdown Time Input (hidden by default) -->
                <div id="sw-countdown-input" style="display: none; margin-bottom: var(--space-4);">
                    <div style="display: flex; gap: var(--space-3); justify-content: center; align-items: center; flex-wrap: wrap;">
                        <div style="text-align: center;">
                            <label class="form-label" style="display: block; margin-bottom: 4px;">Minutes</label>
                            <input type="number" id="sw-countdown-minutes" min="0" max="99" value="0" 
                                   class="form-input" 
                                   style="width: 70px; text-align: center; font-size: 20px; padding: 8px;" 
                                   oninput="swUpdateCountdownPreview()">
                        </div>
                        <span style="font-size: 28px; color: var(--text-muted); padding-top: 20px;">:</span>
                        <div style="text-align: center;">
                            <label class="form-label" style="display: block; margin-bottom: 4px;">Seconds</label>
                            <input type="number" id="sw-countdown-seconds" min="0" max="59" value="30" 
                                   class="form-input" 
                                   style="width: 70px; text-align: center; font-size: 20px; padding: 8px;" 
                                   oninput="swUpdateCountdownPreview()">
                        </div>
                        <button onclick="swSetCountdown()" class="btn btn-sm btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-check"></i> Set
                        </button>
                    </div>
                    <div style="text-align: center; margin-top: var(--space-2);">
                        <div style="display: inline-flex; gap: 8px; flex-wrap: wrap; justify-content: center;">
                            <button onclick="swQuickCountdown(30)" class="btn btn-sm btn-secondary">30s</button>
                            <button onclick="swQuickCountdown(60)" class="btn btn-sm btn-secondary">1m</button>
                            <button onclick="swQuickCountdown(120)" class="btn btn-sm btn-secondary">2m</button>
                            <button onclick="swQuickCountdown(300)" class="btn btn-sm btn-secondary">5m</button>
                            <button onclick="swQuickCountdown(600)" class="btn btn-sm btn-secondary">10m</button>
                        </div>
                    </div>
                </div>
                
                <div id="sw-display" class="sw-time-display">00:00.00</div>
                <div class="sw-controls">
                    <button id="sw-start-btn" class="sw-btn sw-btn-start" onclick="swStart()">
                        <i class="fas fa-play"></i> Start
                    </button>
                    <button id="sw-stop-btn" class="sw-btn sw-btn-stop" onclick="swStop()" disabled>
                        <i class="fas fa-pause"></i> Stop
                    </button>
                    <button id="sw-lap-btn" class="sw-btn sw-btn-lap" onclick="swLap()" disabled>
                        <i class="fas fa-flag"></i> Lap
                    </button>
                    <button id="sw-reset-btn" class="sw-btn sw-btn-reset" onclick="swReset()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Save Session -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-save"></i> Save Session</h3>
            </div>
            <div class="card-body">
                <form id="sw-save-form" class="sw-session-form" onsubmit="return swSaveSession(event)">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div class="form-group">
                        <label class="form-label">Session Name *</label>
                        <input type="text" name="session_name" class="form-input" placeholder="e.g., Sprint Drill" required id="sw-session-name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Link to Skill</label>
                        <select name="skill_id" class="form-select" id="sw-skill-select">
                            <option value="">-- None --</option>
                            <?php foreach ($stopwatch_skills as $skill): ?>
                                <option value="<?= $skill['id'] ?>"><?= htmlspecialchars($skill['category_name'] . ' > ' . $skill['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 0 0 auto;">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary" id="sw-save-btn" disabled>
                            <i class="fas fa-save"></i> Save
                        </button>
                    </div>
                </form>
                <div id="sw-save-alert" class="alert alert-success" style="display:none; margin-top: var(--space-4);">
                    <i class="fas fa-check-circle"></i> <span id="sw-save-msg"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Laps & History Panel -->
    <div>
        <!-- Current Laps -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list-ol"></i> Lap Times</h3>
                <span id="sw-lap-count" style="color: var(--text-muted); font-size: var(--font-size-sm);">0 laps</span>
            </div>
            <div class="card-body" style="padding: 0; max-height: 400px; overflow-y: auto;">
                <table class="sw-lap-table" id="sw-lap-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Lap Time</th>
                            <th>Total</th>
                            <th>Athlete</th>
                        </tr>
                    </thead>
                    <tbody id="sw-lap-tbody">
                    </tbody>
                </table>
                <div id="sw-no-laps" class="sw-empty">
                    <i class="fas fa-stopwatch" style="font-size: 24px; margin-bottom: 8px; display: block;"></i>
                    Press <strong>Lap</strong> while the timer is running to record splits
                </div>
            </div>
        </div>

        <!-- Time History -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Session History</h3>
            </div>
            <div class="card-body" style="padding: 0; max-height: 300px; overflow-y: auto;">
                <?php if (empty($recent_sessions)): ?>
                    <div class="sw-empty">
                        No saved sessions yet. Record and save your first timing session!
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_sessions as $session): ?>
                        <div class="sw-history-item" onclick="swLoadSession(<?= $session['id'] ?>)">
                            <div>
                                <div class="sw-history-name"><?= htmlspecialchars($session['session_name']) ?></div>
                                <div class="sw-history-meta">
                                    <?= date('M j, Y g:ia', strtotime($session['created_at'])) ?>
                                    <?php if ($session['skill_name']): ?>
                                        &middot; <?= htmlspecialchars($session['skill_name']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="sw-history-laps"><?= $session['lap_count'] ?> laps</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Session Detail Modal -->
<div id="sw-session-modal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2 class="modal-title" id="sw-modal-title">Session Details</h2>
            <button class="modal-close" onclick="closeModal('sw-session-modal')">&times;</button>
        </div>
        <div class="modal-body" id="sw-modal-body">
            <div style="text-align:center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
    </div>
</div>

<script src="js/stopwatch.js"></script>
<script src="js/camera_trigger.js"></script>
<script>
const swDisplay = document.getElementById('sw-display');
const stopwatch = new Stopwatch(swDisplay);
const csrfToken = '<?= htmlspecialchars($csrf_token) ?>';

const athleteOptions = <?= json_encode(array_map(function($a) {
    return ['id' => $a['id'], 'name' => ($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')];
}, $athletes), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function swStart() {
    stopwatch.start();
    document.getElementById('sw-start-btn').disabled = true;
    document.getElementById('sw-stop-btn').disabled = false;
    document.getElementById('sw-lap-btn').disabled = false;
    document.getElementById('sw-save-btn').disabled = true;
}

function swStop() {
    stopwatch.stop();
    document.getElementById('sw-start-btn').disabled = false;
    document.getElementById('sw-stop-btn').disabled = true;
    document.getElementById('sw-lap-btn').disabled = true;
    document.getElementById('sw-save-btn').disabled = stopwatch.getLaps().length === 0;
}

function swLap() {
    const lap = stopwatch.lap();
    if (!lap) return;
    renderLaps();
}

function swReset() {
    stopwatch.reset();
    document.getElementById('sw-start-btn').disabled = false;
    document.getElementById('sw-stop-btn').disabled = true;
    document.getElementById('sw-lap-btn').disabled = true;
    document.getElementById('sw-save-btn').disabled = true;
    renderLaps();
}

// Countdown Timer Mode Functions
function swToggleMode() {
    const toggle = document.getElementById('sw-mode-toggle');
    const modeTitle = document.getElementById('sw-mode-title');
    const modeLabel = document.getElementById('sw-mode-label');
    const countdownInput = document.getElementById('sw-countdown-input');
    const lapBtn = document.getElementById('sw-lap-btn');
    
    if (toggle.checked) {
        // Switch to countdown mode
        modeTitle.innerHTML = '<i class="fas fa-hourglass-half"></i> Countdown Timer';
        modeLabel.textContent = 'Countdown';
        countdownInput.style.display = 'block';
        lapBtn.style.display = 'none'; // Hide lap button in countdown mode
        
        // Set initial countdown time
        const minutes = parseInt(document.getElementById('sw-countdown-minutes').value) || 0;
        const seconds = parseInt(document.getElementById('sw-countdown-seconds').value) || 0;
        const totalSeconds = minutes * 60 + seconds;
        
        if (totalSeconds > 0) {
            stopwatch.setCountdownMode(totalSeconds, onCountdownComplete);
        }
    } else {
        // Switch to stopwatch mode
        modeTitle.innerHTML = '<i class="fas fa-stopwatch"></i> Stopwatch Mode';
        modeLabel.textContent = 'Stopwatch';
        countdownInput.style.display = 'none';
        lapBtn.style.display = 'inline-flex'; // Show lap button in stopwatch mode
        stopwatch.setStopwatchMode();
    }
    
    // Reset buttons
    document.getElementById('sw-start-btn').disabled = false;
    document.getElementById('sw-stop-btn').disabled = true;
    document.getElementById('sw-lap-btn').disabled = true;
}

function swUpdateCountdownPreview() {
    // Optional: Could update display to show preview
}

function swSetCountdown() {
    const minutes = parseInt(document.getElementById('sw-countdown-minutes').value) || 0;
    const seconds = parseInt(document.getElementById('sw-countdown-seconds').value) || 0;
    const totalSeconds = minutes * 60 + seconds;
    
    if (totalSeconds <= 0) {
        // Show inline error instead of alert
        const display = document.getElementById('sw-display');
        const originalColor = display.style.color;
        display.style.color = 'var(--error)';
        display.textContent = 'Invalid Time';
        setTimeout(() => {
            display.style.color = originalColor;
            display.textContent = '00:00.00';
        }, 2000);
        return;
    }
    
    stopwatch.setCountdownMode(totalSeconds, onCountdownComplete);
    document.getElementById('sw-start-btn').disabled = false;
}

function swQuickCountdown(seconds) {
    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;
    
    document.getElementById('sw-countdown-minutes').value = minutes;
    document.getElementById('sw-countdown-seconds').value = secs;
    
    stopwatch.setCountdownMode(seconds, onCountdownComplete);
    document.getElementById('sw-start-btn').disabled = false;
}

function onCountdownComplete() {
    // Visual and audio feedback when countdown reaches zero
    const display = document.getElementById('sw-display');
    display.style.color = 'var(--error)';
    display.style.animation = 'pulse 0.5s ease-in-out 3';
    
    // Play beep sound (if audio is available)
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        gainNode.gain.value = 0.3;
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.2);
        
        // Second beep
        setTimeout(() => {
            const osc2 = audioContext.createOscillator();
            const gain2 = audioContext.createGain();
            osc2.connect(gain2);
            gain2.connect(audioContext.destination);
            osc2.frequency.value = 800;
            osc2.type = 'sine';
            gain2.gain.value = 0.3;
            osc2.start(audioContext.currentTime);
            osc2.stop(audioContext.currentTime + 0.2);
        }, 250);
        
        // Third beep
        setTimeout(() => {
            const osc3 = audioContext.createOscillator();
            const gain3 = audioContext.createGain();
            osc3.connect(gain3);
            gain3.connect(audioContext.destination);
            osc3.frequency.value = 1000;
            osc3.type = 'sine';
            gain3.gain.value = 0.4;
            osc3.start(audioContext.currentTime);
            osc3.stop(audioContext.currentTime + 0.5);
        }, 500);
    } catch (e) {
        console.log('Audio not available');
    }
    
    // Show non-blocking notification - use existing notification system if available
    setTimeout(() => {
        display.style.color = '';
        display.style.animation = '';
        
        // Try to use existing notification system
        if (typeof showNotification === 'function') {
            showNotification('Time\'s up!', 'info');
        } else if (typeof swShowAlert === 'function') {
            swShowAlert('info', 'Time\'s up!');
        } else {
            // Fallback: Show visual notification in display
            const originalText = display.textContent;
            display.textContent = 'TIME UP!';
            display.style.color = 'var(--success)';
            setTimeout(() => {
                display.textContent = originalText;
                display.style.color = '';
            }, 3000);
        }
    }, 1500);
    
    // Update buttons
    document.getElementById('sw-start-btn').disabled = false;
    document.getElementById('sw-stop-btn').disabled = true;
}

function renderLaps() {
    const laps = stopwatch.getLaps();
    const tbody = document.getElementById('sw-lap-tbody');
    const noLaps = document.getElementById('sw-no-laps');
    const lapCount = document.getElementById('sw-lap-count');

    lapCount.textContent = laps.length + ' lap' + (laps.length !== 1 ? 's' : '');

    if (laps.length === 0) {
        tbody.innerHTML = '';
        noLaps.style.display = 'block';
        return;
    }

    noLaps.style.display = 'none';

    // Find best/worst lap times
    let bestIdx = 0, worstIdx = 0;
    laps.forEach((lap, i) => {
        if (lap.lapTimeMs < laps[bestIdx].lapTimeMs) bestIdx = i;
        if (lap.lapTimeMs > laps[worstIdx].lapTimeMs) worstIdx = i;
    });

    // Build rows in reverse order (most recent first)
    let html = '';
    for (let i = laps.length - 1; i >= 0; i--) {
        const lap = laps[i];
        let cls = '';
        if (laps.length > 2) {
            if (i === bestIdx) cls = 'sw-lap-best';
            else if (i === worstIdx) cls = 'sw-lap-worst';
        }

        let athleteSelect = '<select class="sw-assign-select" onchange="swAssignAthlete(' + i + ', this.value)">';
        athleteSelect += '<option value="">-- Assign --</option>';
        athleteOptions.forEach(a => {
            const sel = lap.athleteId == a.id ? ' selected' : '';
            athleteSelect += '<option value="' + a.id + '"' + sel + '>' + a.name + '</option>';
        });
        athleteSelect += '</select>';

        html += '<tr class="' + cls + '">';
        html += '<td>' + lap.number + '</td>';
        html += '<td class="sw-lap-time">' + Stopwatch.formatTimeMs(lap.lapTimeMs) + '</td>';
        html += '<td class="sw-lap-time">' + Stopwatch.formatTimeMs(lap.totalTimeMs) + '</td>';
        html += '<td>' + athleteSelect + '</td>';
        html += '</tr>';
    }
    tbody.innerHTML = html;
}

function swAssignAthlete(lapIndex, athleteId) {
    const laps = stopwatch.getLaps();
    if (laps[lapIndex]) {
        laps[lapIndex].athleteId = athleteId || null;
        const athlete = athleteOptions.find(a => a.id == athleteId);
        laps[lapIndex].athleteName = athlete ? athlete.name : '';
    }
}

function swSaveSession(e) {
    e.preventDefault();

    const laps = stopwatch.getLaps();
    if (laps.length === 0) {
        swShowAlert('error', 'No laps to save. Record at least one lap time.');
        return false;
    }

    const sessionName = document.getElementById('sw-session-name').value.trim();
    if (!sessionName) {
        swShowAlert('error', 'Please enter a session name.');
        return false;
    }

    const btn = document.getElementById('sw-save-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const formData = new FormData();
    formData.append('action', 'save_session');
    formData.append('csrf_token', csrfToken);
    formData.append('session_name', sessionName);
    formData.append('skill_id', document.getElementById('sw-skill-select').value || '');
    formData.append('laps', JSON.stringify(laps));

    fetch('process_stopwatch.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            swShowAlert('success', data.message || 'Session saved successfully!');
            swReset();
            document.getElementById('sw-session-name').value = '';
            // Reload page after short delay to update history
            window.location.reload();
        } else {
            swShowAlert('error', data.message || 'Failed to save session.');
        }
    })
    .catch(() => {
        swShowAlert('error', 'Failed to save session. Please try again.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save';
    });

    return false;
}

function swShowAlert(type, message) {
    const alertEl = document.getElementById('sw-save-alert');
    const msgEl = document.getElementById('sw-save-msg');
    alertEl.className = 'alert alert-' + (type === 'error' ? 'error' : 'success');
    msgEl.textContent = message;
    alertEl.style.display = 'flex';
    setTimeout(() => { alertEl.style.display = 'none'; }, 6000);
}

function swLoadSession(sessionId) {
    const modal = document.getElementById('sw-session-modal');
    const body = document.getElementById('sw-modal-body');
    const title = document.getElementById('sw-modal-title');

    body.innerHTML = '<div style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    modal.style.display = 'flex';

    fetch('process_stopwatch.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_session&session_id=' + sessionId + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            title.textContent = data.session.session_name;
            let html = '';
            if (data.session.skill_name) {
                html += '<div class="alert alert-info"><i class="fas fa-link"></i> Linked to skill: <strong>' + escHtml(data.session.skill_name) + '</strong></div>';
            }
            html += '<table class="sw-lap-table"><thead><tr><th>#</th><th>Lap Time</th><th>Total</th><th>Athlete</th></tr></thead><tbody>';
            data.times.forEach(t => {
                html += '<tr><td>' + t.lap_number + '</td>';
                html += '<td class="sw-lap-time">' + Stopwatch.formatTimeMs(t.lap_time_ms) + '</td>';
                html += '<td class="sw-lap-time">' + Stopwatch.formatTimeMs(t.total_time_ms) + '</td>';
                html += '<td>' + (t.athlete_name ? escHtml(t.athlete_name) : '<span style="color:var(--text-muted)">—</span>') + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            html += '<div style="margin-top:var(--space-4);text-align:right;"><span style="color:var(--text-muted);font-size:var(--font-size-sm);">' + escHtml(data.session.created_at) + '</span></div>';
            body.innerHTML = html;
        } else {
            body.innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> ' + (data.message || 'Failed to load session.') + '</div>';
        }
    })
    .catch(() => {
        body.innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Failed to load session.</div>';
    });
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

// ==========================================
// Dual-Camera Trigger Mode
// ==========================================
const cameraTrigger = new CameraTrigger();
let cameraMode = false;

function swToggleCameraMode() {
    const panel = document.getElementById('sw-camera-panel');
    const btn = document.getElementById('sw-camera-toggle');
    cameraMode = !cameraMode;

    if (cameraMode) {
        panel.classList.add('active');
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-primary');
        btn.innerHTML = '<i class="fas fa-video"></i> Camera Mode ON';
        swCamLoadDevices();
    } else {
        panel.classList.remove('active');
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-secondary');
        btn.innerHTML = '<i class="fas fa-video"></i> Camera Mode';
        swCamDeactivate();
    }
}

async function swCamLoadDevices() {
    const cameras = await cameraTrigger.getAvailableCameras();
    const startSelect = document.getElementById('sw-cam-start-select');
    const finishSelect = document.getElementById('sw-cam-finish-select');

    startSelect.innerHTML = '<option value="">-- Select Camera --</option>';
    finishSelect.innerHTML = '<option value="">-- Select Camera --</option>';

    cameras.forEach((cam, i) => {
        const label = cam.label || ('Camera ' + (i + 1));
        startSelect.innerHTML += '<option value="' + cam.deviceId + '">' + escHtml(label) + '</option>';
        finishSelect.innerHTML += '<option value="' + cam.deviceId + '">' + escHtml(label) + '</option>';
    });

    if (cameras.length >= 2) {
        startSelect.value = cameras[0].deviceId;
        finishSelect.value = cameras[1].deviceId;
    } else if (cameras.length === 1) {
        startSelect.value = cameras[0].deviceId;
        swCamShowAlert('info', 'Only one camera detected. For best results, connect a second camera for the finish line. You can use the same camera for both if needed.');
    } else {
        swCamShowAlert('error', 'No cameras found. Please ensure camera access is allowed in your browser settings.');
    }
}

async function swCamActivate() {
    const startDeviceId = document.getElementById('sw-cam-start-select').value;
    const finishDeviceId = document.getElementById('sw-cam-finish-select').value;

    if (!startDeviceId || !finishDeviceId) {
        swCamShowAlert('error', 'Please select both a start line camera and a finish line camera.');
        return;
    }

    const activateBtn = document.getElementById('sw-cam-activate-btn');
    activateBtn.disabled = true;
    activateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connecting...';

    try {
        await cameraTrigger.startMonitoring({
            startVideoEl: document.getElementById('sw-cam-start-video'),
            finishVideoEl: document.getElementById('sw-cam-finish-video'),
            startCanvasEl: document.getElementById('sw-cam-start-canvas'),
            finishCanvasEl: document.getElementById('sw-cam-finish-canvas'),
            startDeviceId: startDeviceId,
            finishDeviceId: finishDeviceId,
            onStartTrigger: swCamOnStart,
            onFinishTrigger: swCamOnFinish,
            onMotionLevel: swCamOnMotion
        });

        document.getElementById('sw-cam-feeds').style.display = 'grid';
        activateBtn.style.display = 'none';
        document.getElementById('sw-cam-deactivate-btn').style.display = '';
        document.getElementById('sw-cam-arm-btn').style.display = '';
        document.getElementById('sw-cam-start-dot').classList.add('live');
        document.getElementById('sw-cam-finish-dot').classList.add('live');

        swCamUpdateStatus('ready', 'Armed — Waiting for Start');
        document.getElementById('sw-cam-start-indicator').textContent = 'Armed';
        document.getElementById('sw-cam-start-indicator').classList.remove('triggered');
        document.getElementById('sw-cam-finish-indicator').textContent = 'Waiting';
        document.getElementById('sw-cam-finish-indicator').classList.remove('triggered');

        swCamShowAlert('success', 'Cameras activated! Motion at Start Line will begin the timer.');
    } catch (e) {
        swCamShowAlert('error', 'Failed to access cameras: ' + e.message);
    } finally {
        activateBtn.disabled = false;
        activateBtn.innerHTML = '<i class="fas fa-power-off"></i> Activate Cameras';
    }
}

function swCamDeactivate() {
    cameraTrigger.stopMonitoring();

    document.getElementById('sw-cam-feeds').style.display = 'none';
    document.getElementById('sw-cam-activate-btn').style.display = '';
    document.getElementById('sw-cam-deactivate-btn').style.display = 'none';
    document.getElementById('sw-cam-arm-btn').style.display = 'none';
    document.getElementById('sw-cam-start-dot').classList.remove('live');
    document.getElementById('sw-cam-finish-dot').classList.remove('live');

    swCamUpdateStatus('', 'Inactive');
}

function swCamArm() {
    cameraTrigger.armStart();
    cameraTrigger.finishArmed = false;

    document.getElementById('sw-cam-start-indicator').textContent = 'Armed';
    document.getElementById('sw-cam-start-indicator').classList.remove('triggered');
    document.getElementById('sw-cam-finish-indicator').textContent = 'Waiting';
    document.getElementById('sw-cam-finish-indicator').classList.remove('triggered');

    swCamUpdateStatus('ready', 'Armed — Waiting for Start');
    swCamShowAlert('success', 'Start camera re-armed. Motion at Start Line will begin a new timing run.');
}

function swCamOnStart(timestamp) {
    // Auto-reset if timer is stopped with laps
    if (!stopwatch.running && stopwatch.getLaps().length > 0) {
        swReset();
    }
    swStart();

    document.getElementById('sw-cam-start-indicator').textContent = 'Triggered!';
    document.getElementById('sw-cam-start-indicator').classList.add('triggered');
    document.getElementById('sw-cam-finish-indicator').textContent = 'Armed';
    document.getElementById('sw-cam-finish-indicator').classList.remove('triggered');

    swCamUpdateStatus('active', 'Running — Finish Line Armed');
}

function swCamOnFinish(timestamp) {
    swLap();
    swStop();

    document.getElementById('sw-cam-finish-indicator').textContent = 'Triggered!';
    document.getElementById('sw-cam-finish-indicator').classList.add('triggered');

    swCamUpdateStatus('ready', 'Finished — Use Re-Arm for next run');
}

function swCamOnMotion(which, level) {
    const barId = which === 'start' ? 'sw-cam-start-motion' : 'sw-cam-finish-motion';
    const bar = document.getElementById(barId);
    if (bar) {
        bar.style.width = Math.min(level * 3, 100) + '%';
        bar.style.background = level > cameraTrigger.motionThreshold ? 'var(--success)' : 'var(--primary)';
    }
}

function swCamUpdateSensitivity(val) {
    document.getElementById('sw-cam-sensitivity-val').textContent = val;
    cameraTrigger.setSensitivity(parseInt(val));
}

function swCamUpdateThreshold(val) {
    document.getElementById('sw-cam-threshold-val').textContent = val;
    cameraTrigger.setMotionThreshold(parseInt(val));
}

function swCamUpdateStatus(cls, text) {
    const el = document.getElementById('sw-cam-global-status');
    el.className = 'sw-cam-status' + (cls ? ' ' + cls : '');
    el.innerHTML = '<i class="fas fa-circle" style="font-size:8px;"></i> ' + escHtml(text);
}

function swCamShowAlert(type, message) {
    const alertEl = document.getElementById('sw-cam-alert');
    const msgEl = document.getElementById('sw-cam-alert-msg');
    const cls = type === 'error' ? 'alert-error' : type === 'info' ? 'alert-info' : 'alert-success';
    alertEl.className = 'alert ' + cls;
    alertEl.querySelector('i').className = type === 'error' ? 'fas fa-exclamation-circle' : type === 'info' ? 'fas fa-info-circle' : 'fas fa-check-circle';
    msgEl.textContent = message;
    alertEl.style.display = 'flex';
    if (type !== 'info') {
        setTimeout(() => { alertEl.style.display = 'none'; }, 6000);
    }
}
</script>
