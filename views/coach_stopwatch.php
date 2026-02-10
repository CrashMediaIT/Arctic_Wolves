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
</style>

<div class="sw-container">
    <!-- Stopwatch Panel -->
    <div>
        <div class="card">
            <div class="card-body sw-display-wrapper">
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
<script>
const swDisplay = document.getElementById('sw-display');
const stopwatch = new Stopwatch(swDisplay);
const csrfToken = '<?= htmlspecialchars($csrf_token) ?>';

const athleteOptions = <?= json_encode(array_map(function($a) {
    return ['id' => $a['id'], 'name' => htmlspecialchars(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))];
}, $athletes)) ?>;

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
            setTimeout(() => window.location.reload(), 1500);
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
</script>
