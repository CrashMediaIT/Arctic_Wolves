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
</style>

<div class="m-stopwatch">
    <div class="m-stopwatch-header">
        <h2 class="m-stopwatch-title">Stopwatch</h2>
        <p class="m-stopwatch-sub">Tap to start timing</p>
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
        <div class="m-sw-hist-item">
            <div class="m-sw-hist-name"><?= htmlspecialchars($sess['session_name']) ?></div>
            <div class="m-sw-hist-meta">
                <?= date('M j, Y g:ia', strtotime($sess['created_at'])) ?>
                &middot; <span class="m-sw-hist-laps"><?= (int)$sess['lap_count'] ?> laps</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
(function() {
    var running = false, startTime = 0, elapsed = 0, timer = null;
    var laps = [], lastLap = 0;
    var csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>';

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
        showSaveSection();
    };

    window.mSwLap = function() {
        if (!running) return;
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
})();
</script>
