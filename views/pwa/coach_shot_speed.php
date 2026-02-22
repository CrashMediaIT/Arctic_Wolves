<?php
/**
 * PWA Coach Shot Speed - Mobile-native shot speed recording tool
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

$athletes = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name
        FROM users u
        WHERE u.role = 'athlete' AND u.is_active = 1
        ORDER BY u.last_name, u.first_name
    ");
    $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $athletes = decryptUserRows($athletes);
} catch (Exception $e) {
    $athletes = [];
}
?>
<style>
.m-shotspeed { padding: 16px; font-family: Inter, sans-serif; }
.m-shotspeed-header { margin-bottom: 20px; }
.m-shotspeed-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-shotspeed-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-speed-input-wrap {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 16px;
    padding: 24px 16px; margin-bottom: 16px; text-align: center;
}
.m-speed-select {
    width: 100%; min-height: 44px; padding: 12px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    margin-bottom: 16px; box-sizing: border-box;
}
.m-speed-select:focus { border-color: #8B5CF6; outline: none; }
.m-speed-input {
    width: 100%; max-width: 220px; min-height: 56px;
    background: #0A0A0F; border: 2px solid #2D2D3F; border-radius: 12px;
    color: #fff; font-size: 32px; font-weight: 700; text-align: center;
    padding: 8px 16px; font-family: Inter, sans-serif;
    -moz-appearance: textfield; box-sizing: border-box;
}
.m-speed-input:focus { border-color: #8B5CF6; outline: none; }
.m-speed-input::-webkit-outer-spin-button,
.m-speed-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.m-speed-notes {
    width: 100%; max-width: 220px; min-height: 44px; padding: 12px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    margin-top: 12px; box-sizing: border-box;
}
.m-speed-notes:focus { border-color: #8B5CF6; outline: none; }
.m-speed-label { font-size: 12px; color: #A8A8B8; margin-top: 8px; display: block; }
.m-unit-toggle {
    display: flex; gap: 0; margin: 16px auto; max-width: 220px;
    background: #0A0A0F; border-radius: 10px; border: 1px solid #2D2D3F;
    overflow: hidden;
}
.m-unit-btn {
    flex: 1; padding: 12px; min-height: 44px;
    border: none; background: none; cursor: pointer;
    font-size: 14px; font-weight: 600; color: #6B6B7B;
    font-family: Inter, sans-serif; transition: all 0.2s;
}
.m-unit-btn.m-unit-active { background: #6B46C1; color: #fff; }
.m-record-btn {
    display: flex; width: 100%; max-width: 220px; margin: 16px auto 0;
    min-height: 56px; border-radius: 12px;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    color: #fff; font-size: 16px; font-weight: 600;
    border: none; cursor: pointer; font-family: Inter, sans-serif;
    align-items: center; justify-content: center; gap: 8px;
}
.m-record-btn:active { transform: scale(0.97); }
.m-record-btn:disabled { opacity: 0.5; }
.m-history-section { margin-top: 24px; }
.m-history-title {
    font-size: 13px; font-weight: 600; color: #6B6B7B;
    text-transform: uppercase; letter-spacing: 0.5px;
    margin: 0 0 10px; padding: 0 4px;
}
.m-history-item {
    display: flex; justify-content: space-between; align-items: center;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    padding: 12px 14px; margin-bottom: 6px; min-height: 44px;
}
.m-history-speed { font-size: 18px; font-weight: 700; color: #fff; }
.m-history-unit { font-size: 12px; color: #8B5CF6; font-weight: 600; margin-left: 4px; }
.m-history-time { font-size: 12px; color: #6B6B7B; }
.m-history-notes { font-size: 11px; color: #A8A8B8; margin-top: 2px; }
.m-history-del {
    background: none; border: none; color: #EF4444; font-size: 14px;
    cursor: pointer; padding: 8px; min-width: 44px; min-height: 44px;
    display: flex; align-items: center; justify-content: center;
}
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
.m-speed-alert {
    padding: 10px 14px; border-radius: 10px; font-size: 13px; margin-top: 12px;
    display: none; text-align: center;
}
.m-speed-alert.m-alert-success { background: rgba(16,185,129,0.15); color: #10B981; }
.m-speed-alert.m-alert-error { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-stats-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 16px;
}
.m-stat-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    padding: 12px 8px; text-align: center;
}
.m-stat-label { font-size: 10px; color: #6B6B7B; text-transform: uppercase; margin-bottom: 4px; }
.m-stat-value { font-size: 18px; font-weight: 700; color: #8B5CF6; }
</style>

<div class="m-shotspeed">
    <div class="m-shotspeed-header">
        <h2 class="m-shotspeed-title">Shot Speed</h2>
        <p class="m-shotspeed-sub">Record and track shot speeds</p>
    </div>

    <div class="m-speed-input-wrap">
        <select class="m-speed-select" id="mAthleteSelect">
            <option value="">-- Select Athlete --</option>
            <?php foreach ($athletes as $a): ?>
            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['last_name'] . ', ' . $a['first_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" class="m-speed-input" id="mSpeedVal" placeholder="0" min="1" max="150" step="0.1" inputmode="decimal">
        <span class="m-speed-label">Enter speed value</span>
        <div class="m-unit-toggle">
            <button class="m-unit-btn m-unit-active" id="mUnitMph" type="button" onclick="mSetUnit('mph')">mph</button>
            <button class="m-unit-btn" id="mUnitKmh" type="button" onclick="mSetUnit('km/h')">km/h</button>
        </div>
        <input type="text" class="m-speed-notes" id="mSpeedNotes" placeholder="Notes (e.g., wrist shot, slap shot)">
        <button class="m-record-btn" id="mRecordBtn" type="button" onclick="mRecordSpeed()">
            <i class="fas fa-plus-circle"></i> Record
        </button>
        <div class="m-speed-alert" id="mSpeedAlert"></div>
    </div>

    <div id="mStatsSection" style="display:none;">
        <h3 class="m-history-title">Statistics</h3>
        <div class="m-stats-grid" id="mStatsGrid"></div>
    </div>

    <div class="m-history-section">
        <h3 class="m-history-title">Recent Entries</h3>
        <div id="mSpeedHistory"></div>
    </div>
</div>

<script>
(function() {
    var unit = 'mph';
    var csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>';
    var currentAthleteId = null;

    function escHtml(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

    function showAlert(type, msg) {
        var el = document.getElementById('mSpeedAlert');
        el.className = 'm-speed-alert m-alert-' + type;
        el.textContent = msg;
        el.style.display = 'block';
        setTimeout(function() { el.style.display = 'none'; }, 4000);
    }

    document.getElementById('mAthleteSelect').addEventListener('change', function() {
        currentAthleteId = this.value;
        if (currentAthleteId) { loadRecentSpeeds(); loadStats(); }
        else {
            document.getElementById('mSpeedHistory').innerHTML = '<div class="m-empty-state"><i class="fas fa-gauge-high"></i>Select an athlete to view history</div>';
            document.getElementById('mStatsSection').style.display = 'none';
        }
    });

    function loadRecentSpeeds() {
        if (!currentAthleteId) return;
        var fd = new FormData();
        fd.append('action', 'get_recent_speeds');
        fd.append('athlete_id', currentAthleteId);
        fd.append('csrf_token', csrfToken);
        fetch('process_shot_speed.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) { if (data.success) renderHistory(data.speeds); })
            .catch(function(e) { console.error('Error loading speeds:', e); });
    }

    function loadStats() {
        if (!currentAthleteId) return;
        var fd = new FormData();
        fd.append('action', 'get_stats');
        fd.append('athlete_id', currentAthleteId);
        fd.append('csrf_token', csrfToken);
        fetch('process_shot_speed.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.stats && data.stats.length > 0) renderStats(data.stats);
                else document.getElementById('mStatsSection').style.display = 'none';
            })
            .catch(function(e) { console.error('Error loading stats:', e); });
    }

    function renderStats(stats) {
        var sec = document.getElementById('mStatsSection');
        var grid = document.getElementById('mStatsGrid');
        sec.style.display = 'block';
        var html = '';
        stats.forEach(function(s) {
            html += '<div class="m-stat-card"><div class="m-stat-label">Max (' + escHtml(s.unit) + ')</div><div class="m-stat-value">' + parseFloat(s.max_speed).toFixed(1) + '</div></div>';
            html += '<div class="m-stat-card"><div class="m-stat-label">Avg (' + escHtml(s.unit) + ')</div><div class="m-stat-value">' + parseFloat(s.avg_speed).toFixed(1) + '</div></div>';
            html += '<div class="m-stat-card"><div class="m-stat-label">Tests</div><div class="m-stat-value">' + s.total_measurements + '</div></div>';
        });
        grid.innerHTML = html;
    }

    function renderHistory(speeds) {
        var container = document.getElementById('mSpeedHistory');
        if (!speeds || speeds.length === 0) {
            container.innerHTML = '<div class="m-empty-state"><i class="fas fa-gauge-high"></i>No entries recorded</div>';
            return;
        }
        var maxSpeed = 0;
        speeds.forEach(function(s) { var v = parseFloat(s.speed); if (v > maxSpeed) maxSpeed = v; });
        var html = '';
        speeds.forEach(function(entry) {
            var d = new Date(entry.created_at);
            var timeStr = d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
            var val = parseFloat(entry.speed);
            var color = (val === maxSpeed && speeds.length > 1) ? '#10B981' : '#fff';
            html += '<div class="m-history-item">' +
                '<div><span class="m-history-speed" style="color:' + color + '">' + val.toFixed(1) + '</span><span class="m-history-unit">' + escHtml(entry.unit) + '</span>' +
                (entry.notes ? '<div class="m-history-notes">' + escHtml(entry.notes) + '</div>' : '') +
                '</div>' +
                '<span class="m-history-time">' + timeStr + '</span>' +
                '<button class="m-history-del" type="button" onclick="mDelSpeed(' + entry.id + ')" title="Delete"><i class="fas fa-trash"></i></button>' +
                '</div>';
        });
        container.innerHTML = html;
    }

    window.mSetUnit = function(u) {
        unit = u;
        document.getElementById('mUnitMph').className = 'm-unit-btn' + (u === 'mph' ? ' m-unit-active' : '');
        document.getElementById('mUnitKmh').className = 'm-unit-btn' + (u === 'km/h' ? ' m-unit-active' : '');
    };

    window.mRecordSpeed = function() {
        var val = parseFloat(document.getElementById('mSpeedVal').value);
        if (!val || val <= 0) { showAlert('error', 'Enter a valid speed'); return; }
        if (!currentAthleteId) { showAlert('error', 'Select an athlete first'); return; }
        var btn = document.getElementById('mRecordBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        var fd = new FormData();
        fd.append('action', 'record_speed');
        fd.append('csrf_token', csrfToken);
        fd.append('athlete_id', currentAthleteId);
        fd.append('speed', val);
        fd.append('unit', unit);
        fd.append('notes', document.getElementById('mSpeedNotes').value);
        fetch('process_shot_speed.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showAlert('success', data.message || 'Speed recorded');
                    document.getElementById('mSpeedVal').value = '';
                    document.getElementById('mSpeedNotes').value = '';
                    loadRecentSpeeds();
                    loadStats();
                } else { showAlert('error', data.message || 'Error recording speed'); }
            })
            .catch(function() { showAlert('error', 'Error recording speed'); })
            .finally(function() { btn.disabled = false; btn.innerHTML = '<i class="fas fa-plus-circle"></i> Record'; });
    };

    window.mDelSpeed = function(id) {
        if (!confirm('Delete this measurement?')) return;
        var fd = new FormData();
        fd.append('action', 'delete_speed');
        fd.append('speed_id', id);
        fd.append('csrf_token', csrfToken);
        fetch('process_shot_speed.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) { loadRecentSpeeds(); loadStats(); showAlert('success', 'Deleted'); }
                else { showAlert('error', data.message || 'Error deleting'); }
            })
            .catch(function() { showAlert('error', 'Error deleting measurement'); });
    };

    document.getElementById('mSpeedHistory').innerHTML = '<div class="m-empty-state"><i class="fas fa-gauge-high"></i>Select an athlete to view history</div>';
})();
</script>
