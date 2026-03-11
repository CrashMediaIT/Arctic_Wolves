/**
 * Arctic Wolves – Scoreboard Module JavaScript
 *
 * Professional scoreboard controller (Nevco / Daktronics style):
 * - Working game clock with start/stop/reset
 * - Real-time penalty countdown timers
 * - Goal light flash animation
 * - Goal / shot / penalty tracking via AJAX
 * - Period management
 * - Buzzer / horn triggering
 * - Video board source switching
 * - Music integration (Spotify / Subsonic)
 * - Scoresheet sync to Game Plan
 */

/* global CSRF_TOKEN, ACTIVE_GAME_ID */

// ── AJAX helper ───────────────────────────────────────────
function sbFetch(action, data) {
    var fd = new FormData();
    fd.append('action', action);
    fd.append('csrf_token', CSRF_TOKEN);
    if (ACTIVE_GAME_ID) fd.append('game_id', ACTIVE_GAME_ID);
    if (data) {
        Object.keys(data).forEach(function(k) { fd.append(k, data[k]); });
    }
    return fetch('/process_scoreboard.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF_TOKEN },
        body: fd
    }).then(function(r) {
        if (!r.ok) {
            return r.text().then(function(t) {
                try { return JSON.parse(t); } catch (e) {
                    return { success: false, message: 'Server error (' + r.status + ')' };
                }
            });
        }
        return r.json();
    }).catch(function() {
        return { success: false, message: 'Network error – please try again.' };
    });
}

// ══════════════════════════════════════════════════════════
// GAME CLOCK – Countdown timer (configurable per period)
// ══════════════════════════════════════════════════════════
var REGULATION_PERIOD_SECS = 20 * 60; // 1200 seconds = 20 min (default)
var OVERTIME_PERIOD_SECS = 5 * 60;    // 300 seconds = 5 min OT (default)
var sbClockSeconds = REGULATION_PERIOD_SECS;
var sbClockRunning = false;
var sbClockInterval = null;
var sbClockLastTick = 0; // Wall-clock timestamp (ms) of last applied tick

// ── Clock State Persistence (survives page reload / navigation) ──
function sbSaveClockState() {
    try {
        sessionStorage.setItem('sb_clock_seconds', sbClockSeconds);
        sessionStorage.setItem('sb_clock_running', sbClockRunning ? '1' : '0');
        sessionStorage.setItem('sb_regulation_secs', REGULATION_PERIOD_SECS);
        sessionStorage.setItem('sb_overtime_secs', OVERTIME_PERIOD_SECS);
        sessionStorage.setItem('sb_clock_saved_at', Date.now());
        // Persist penalty timers so they survive reload
        sbSavePenaltyState();
        // Persist recurring buzzer state
        sbSaveRecurringBuzzerState();
    } catch (e) { /* sessionStorage unavailable */ }
}

function sbRestoreClockState() {
    try {
        var saved = sessionStorage.getItem('sb_clock_seconds');
        if (saved !== null) {
            var regSecs = parseInt(sessionStorage.getItem('sb_regulation_secs'), 10);
            var otSecs = parseInt(sessionStorage.getItem('sb_overtime_secs'), 10);
            if (!isNaN(regSecs) && regSecs > 0) REGULATION_PERIOD_SECS = regSecs;
            if (!isNaN(otSecs) && otSecs > 0) OVERTIME_PERIOD_SECS = otSecs;

            var restored = parseInt(saved, 10);
            if (!isNaN(restored) && restored >= 0) {
                var wasRunning = sessionStorage.getItem('sb_clock_running') === '1';
                var elapsed = 0;
                if (wasRunning) {
                    // Account for time elapsed during reload
                    var savedAt = parseInt(sessionStorage.getItem('sb_clock_saved_at'), 10);
                    if (!isNaN(savedAt)) {
                        elapsed = Math.round((Date.now() - savedAt) / 1000);
                        restored = Math.max(0, restored - elapsed);
                    }
                }
                sbClockSeconds = restored;
                sbUpdateClockDisplay();
                // Sync period time select dropdown to match saved value
                sbSyncPeriodTimeSelects();
                // Restore penalty timer state (compensating for elapsed time)
                sbRestorePenaltyState(wasRunning ? elapsed : 0);
                // Restore recurring buzzer state
                sbRestoreRecurringBuzzerState(wasRunning ? elapsed : 0);
                // Auto-restart clock if it was running before reload
                if (wasRunning && sbClockSeconds > 0) {
                    sbClockStart();
                }
                return true;
            }
        }
    } catch (e) { /* sessionStorage unavailable */ }
    return false;
}

function sbSyncPeriodTimeSelects() {
    var regMin = REGULATION_PERIOD_SECS / 60;
    var otMin = OVERTIME_PERIOD_SECS / 60;
    var regSelect = document.getElementById('sbPeriodTimeSelect');
    if (regSelect) {
        var matched = false;
        for (var i = 0; i < regSelect.options.length; i++) {
            if (parseInt(regSelect.options[i].value, 10) === regMin) {
                regSelect.selectedIndex = i;
                matched = true;
                break;
            }
        }
        if (!matched) {
            var customInput = document.getElementById('sbPeriodCustomMin');
            if (customInput) customInput.value = regMin;
        }
    }
    var otSelect = document.getElementById('sbOTTimeSelect');
    if (otSelect) {
        for (var j = 0; j < otSelect.options.length; j++) {
            if (parseInt(otSelect.options[j].value, 10) === otMin) {
                otSelect.selectedIndex = j;
                break;
            }
        }
    }
}

function sbGetPeriodDuration(period) {
    return (period <= 3) ? REGULATION_PERIOD_SECS : OVERTIME_PERIOD_SECS;
}

function sbSaveAndReload() {
    sbSaveClockState();
    window.location.reload();
}

// ── Adjustable Period Times ───────────────────────────────
function sbSetPeriodTime(minutes) {
    minutes = parseInt(minutes, 10);
    if (isNaN(minutes) || minutes < 1 || minutes > 30) return;
    REGULATION_PERIOD_SECS = minutes * 60;
    // Reset the clock to the new duration if not running
    if (!sbClockRunning) {
        sbClockSeconds = sbGetPeriodDuration(sbCurrentPeriod);
        sbUpdateClockDisplay();
    }
    sbSaveClockState();
    // Update reset button label
    var resetBtn = document.querySelector('.sb-clock-reset');
    if (resetBtn) resetBtn.innerHTML = '<i class="fas fa-redo"></i> Reset ' + sbFormatClock(REGULATION_PERIOD_SECS);
}

function sbSetOvertimeTime(minutes) {
    minutes = parseInt(minutes, 10);
    if (isNaN(minutes) || minutes < 1 || minutes > 20) return;
    OVERTIME_PERIOD_SECS = minutes * 60;
    if (!sbClockRunning && sbCurrentPeriod > 3) {
        sbClockSeconds = sbGetPeriodDuration(sbCurrentPeriod);
        sbUpdateClockDisplay();
    }
    sbSaveClockState();
}

function sbFormatClock(totalSeconds) {
    var m = Math.floor(totalSeconds / 60);
    var s = totalSeconds % 60;
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
}

function sbUpdateClockDisplay() {
    var el = document.getElementById('sbGameClock');
    if (el) el.textContent = sbFormatClock(sbClockSeconds);
}

function sbClockTick() {
    // Timestamp-based: compute real elapsed seconds since last tick
    var now = Date.now();
    var delta = Math.floor((now - sbClockLastTick) / 1000);
    if (delta < 1) return; // less than 1 second elapsed
    sbClockLastTick += delta * 1000; // advance by whole seconds only

    if (sbClockSeconds > 0) {
        var tickCount = Math.min(delta, sbClockSeconds);
        sbClockSeconds -= tickCount;
        sbUpdateClockDisplay();
        sbSaveClockState();
        // Tick penalty timers by the same delta
        sbTickPenaltyTimers(tickCount);
        // Tick recurring buzzer by the same delta
        sbTickRecurringBuzzer(tickCount);
        if (sbClockSeconds <= 0) {
            // Period ended
            sbClockStop();
            sbBuzzer(); // Auto-buzzer at end of period
        }
    } else {
        // Period ended
        sbClockStop();
        sbBuzzer(); // Auto-buzzer at end of period
    }
}

function sbClockStart() {
    if (sbClockRunning) return;
    sbClockRunning = true;
    sbClockLastTick = Date.now();
    // 200ms polling for responsiveness; actual ticks are timestamp-based (1s)
    sbClockInterval = setInterval(sbClockTick, 200);
    var btn = document.getElementById('sbClockStart');
    if (btn) {
        btn.innerHTML = '<i class="fas fa-pause"></i> Stop';
        btn.classList.add('running');
    }
    sbSaveClockState();
    // Update game status
    var statusEl = document.getElementById('sbStatus');
    if (statusEl) statusEl.textContent = 'IN PROGRESS';
}

function sbClockStop() {
    sbClockRunning = false;
    if (sbClockInterval) {
        clearInterval(sbClockInterval);
        sbClockInterval = null;
    }
    var btn = document.getElementById('sbClockStart');
    if (btn) {
        btn.innerHTML = '<i class="fas fa-play"></i> Start';
        btn.classList.remove('running');
    }
    sbSaveClockState();
}

function sbClockToggle() {
    if (sbClockRunning) {
        sbClockStop();
    } else {
        sbClockStart();
    }
}

function sbClockReset() {
    sbClockStop();
    sbClockSeconds = sbGetPeriodDuration(sbCurrentPeriod);
    sbUpdateClockDisplay();
    sbSaveClockState();
}

// ══════════════════════════════════════════════════════════
// PENALTY COUNTDOWN TIMERS
// ══════════════════════════════════════════════════════════
var sbPenaltyTimers = {
    home: [null, null], // [{seconds, element}, ...]
    away: [null, null]
};

function sbInitPenaltyTimers() {
    // Initialize from DOM if penalty boxes have times
    ['home', 'away'].forEach(function(team) {
        for (var i = 0; i < 2; i++) {
            var el = document.getElementById('sb' + (team === 'home' ? 'Home' : 'Away') + 'PenTime' + i);
            if (el && el.textContent !== '--:--') {
                var parts = el.textContent.split(':');
                if (parts.length === 2) {
                    var secs = parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
                    if (secs > 0) {
                        sbPenaltyTimers[team][i] = { seconds: secs, element: el };
                    }
                }
            }
        }
    });
}

function sbTickPenaltyTimers(delta) {
    if (!delta) delta = 1;
    ['home', 'away'].forEach(function(team) {
        for (var i = 0; i < 2; i++) {
            var timer = sbPenaltyTimers[team][i];
            if (timer && timer.seconds > 0) {
                timer.seconds = Math.max(0, timer.seconds - delta);
                timer.element.textContent = sbFormatClock(timer.seconds);
                if (timer.seconds === 0) {
                    // Penalty expired
                    var box = document.getElementById('sb' + (team === 'home' ? 'Home' : 'Away') + 'Pen' + i);
                    if (box) box.classList.remove('active');
                    sbPenaltyTimers[team][i] = null;
                }
            }
        }
    });
    // Also tick individual penalty item clocks in operator controls
    sbTickPenaltyItemClocks(delta);
}

// ── Individual Penalty Item Clocks (in operator control list) ──
var sbPenaltyItemClocks = [];

function sbInitPenaltyItemClocks() {
    sbPenaltyItemClocks = [];
    var clocks = document.querySelectorAll('[data-penalty-clock]');
    clocks.forEach(function(el) {
        var secs = parseInt(el.getAttribute('data-penalty-seconds'), 10);
        if (isNaN(secs) || secs < 0) secs = 0;
        if (secs === 0) {
            el.textContent = '0:00';
            el.classList.add('expired');
        }
        sbPenaltyItemClocks.push({ seconds: secs, element: el });
    });
}

function sbTickPenaltyItemClocks(delta) {
    if (!delta) delta = 1;
    sbPenaltyItemClocks.forEach(function(clock) {
        if (clock.seconds > 0) {
            clock.seconds = Math.max(0, clock.seconds - delta);
            clock.element.textContent = sbFormatClock(clock.seconds);
            clock.element.setAttribute('data-penalty-seconds', clock.seconds);
            if (clock.seconds === 0) {
                clock.element.textContent = '0:00';
                clock.element.classList.add('expired');
            }
        }
    });
}

// Init penalty timers on load
if (document.getElementById('sbHomePenTime0')) {
    sbInitPenaltyTimers();
}
// Init individual penalty item clocks on load
sbInitPenaltyItemClocks();

// ── Penalty State Persistence (survives page reload) ──────
function sbSavePenaltyState() {
    try {
        var state = { home: [], away: [] };
        ['home', 'away'].forEach(function(team) {
            for (var i = 0; i < 2; i++) {
                var timer = sbPenaltyTimers[team][i];
                state[team].push(timer ? timer.seconds : null);
            }
        });
        sessionStorage.setItem('sb_penalty_timers', JSON.stringify(state));
        var itemState = [];
        sbPenaltyItemClocks.forEach(function(clock) {
            itemState.push(clock.seconds);
        });
        sessionStorage.setItem('sb_penalty_item_clocks', JSON.stringify(itemState));
    } catch (e) { /* sessionStorage unavailable */ }
}

function sbRestorePenaltyState(elapsed) {
    if (!elapsed) elapsed = 0;
    try {
        var raw = sessionStorage.getItem('sb_penalty_timers');
        if (raw) {
            var state = JSON.parse(raw);
            ['home', 'away'].forEach(function(team) {
                if (!state[team]) return;
                for (var i = 0; i < 2; i++) {
                    if (state[team][i] !== null && state[team][i] !== undefined) {
                        var secs = Math.max(0, state[team][i] - elapsed);
                        var el = document.getElementById('sb' + (team === 'home' ? 'Home' : 'Away') + 'PenTime' + i);
                        if (el) {
                            el.textContent = sbFormatClock(secs);
                            if (secs > 0) {
                                sbPenaltyTimers[team][i] = { seconds: secs, element: el };
                            } else {
                                sbPenaltyTimers[team][i] = null;
                                var box = document.getElementById('sb' + (team === 'home' ? 'Home' : 'Away') + 'Pen' + i);
                                if (box) box.classList.remove('active');
                            }
                        }
                    }
                }
            });
        }
        var rawItems = sessionStorage.getItem('sb_penalty_item_clocks');
        if (rawItems) {
            var itemState = JSON.parse(rawItems);
            for (var j = 0; j < sbPenaltyItemClocks.length && j < itemState.length; j++) {
                var restored = Math.max(0, itemState[j] - elapsed);
                sbPenaltyItemClocks[j].seconds = restored;
                sbPenaltyItemClocks[j].element.textContent = sbFormatClock(restored);
                sbPenaltyItemClocks[j].element.setAttribute('data-penalty-seconds', restored);
                if (restored === 0) {
                    sbPenaltyItemClocks[j].element.classList.add('expired');
                }
            }
        }
    } catch (e) { /* sessionStorage unavailable or bad data */ }
}

// ══════════════════════════════════════════════════════════
// PERIOD MANAGEMENT
// ══════════════════════════════════════════════════════════
var sbCurrentPeriod = 1;

function sbInitPeriod() {
    var el = document.querySelector('.sb-period-value');
    if (el) sbCurrentPeriod = parseInt(el.textContent, 10) || 1;
}

function sbUpdatePeriodDisplay() {
    var el = document.querySelector('.sb-period-value');
    if (el) {
        if (sbCurrentPeriod <= 3) {
            el.textContent = sbCurrentPeriod;
        } else if (sbCurrentPeriod === 4) {
            el.textContent = 'OT';
        } else {
            el.textContent = 'SO';
        }
    }
}

function sbPeriodNext() {
    sbClockStop();
    if (sbCurrentPeriod < 5) {
        sbCurrentPeriod++;
        sbUpdatePeriodDisplay();
        sbClockSeconds = sbGetPeriodDuration(sbCurrentPeriod);
        sbUpdateClockDisplay();
        sbSaveClockState();
        sbFetch('update_period', { period: sbCurrentPeriod });
    }
}

function sbPeriodPrev() {
    sbClockStop();
    if (sbCurrentPeriod > 1) {
        sbCurrentPeriod--;
        sbUpdatePeriodDisplay();
        sbClockSeconds = sbGetPeriodDuration(sbCurrentPeriod);
        sbUpdateClockDisplay();
        sbSaveClockState();
        sbFetch('update_period', { period: sbCurrentPeriod });
    }
}

function sbSetStatus(status) {
    var statusEl = document.getElementById('sbStatus');
    if (statusEl) {
        statusEl.textContent = status.toUpperCase();
        statusEl.className = 'sb-board-status' + (status === 'intermission' ? ' intermission' : '');
    }
    sbClockStop();
    sbFetch('update_status', { status: status });
}

// Init period on load
sbInitPeriod();
// Restore clock state from sessionStorage (survives reload/navigation)
sbRestoreClockState();

// ══════════════════════════════════════════════════════════
// GOAL LIGHT FLASH
// ══════════════════════════════════════════════════════════
function sbFlashGoalLight() {
    var light = document.getElementById('sbGoalLight');
    if (!light) return;
    light.classList.remove('flash');
    // Force reflow to restart animation
    void light.offsetWidth;
    light.classList.add('flash');
    setTimeout(function() { light.classList.remove('flash'); }, 1600);
}

// ── Goal tracking ─────────────────────────────────────────
function sbAddGoal(team) {
    // Check if opposing team has active penalties (power play goal)
    var opposingTeam = (team === 'home') ? 'away' : 'home';
    var ppgClearable = sbHasClearableMinor(opposingTeam);

    sbFetch('add_goal', { team: team }).then(function(d) {
        if (d.success) {
            document.getElementById('sbHomeScore').textContent = d.home_score;
            document.getElementById('sbAwayScore').textContent = d.away_score;
            sbFlashGoalLight();
            sbGoalHorn(); // Play goal horn on goal

            // NHL Rule 16.2: Minor penalty expires on PPG
            if (ppgClearable) {
                var opposingLabel = (opposingTeam === 'home')
                    ? (document.querySelector('.sb-board-team.home .sb-board-team-name') || {}).textContent || 'Home'
                    : (document.querySelector('.sb-board-team.away .sb-board-team-name') || {}).textContent || 'Away';
                if (confirm('Power play goal! Clear the oldest minor penalty for ' + opposingLabel + '? (NHL Rule 16.2 – minors expire on PPG, majors do not)')) {
                    sbClearPenaltyOnGoal(opposingTeam);
                }
            }
        }
    });
}

// Check if the given team has a clearable minor penalty (not major/misconduct)
function sbHasClearableMinor(team) {
    var items = document.querySelectorAll('.sb-ctrl-penalty-item[data-team="' + team + '"]');
    for (var i = 0; i < items.length; i++) {
        var penType = items[i].getAttribute('data-penalty-type') || 'minor';
        if (penType === 'minor' || penType === 'double_minor' || penType === 'bench') {
            return true;
        }
    }
    return false;
}

// NHL Rule 16.2: Clear oldest minor penalty on PPG (majors are NOT cleared)
function sbClearPenaltyOnGoal(team) {
    var items = document.querySelectorAll('.sb-ctrl-penalty-item[data-team="' + team + '"]');
    for (var i = 0; i < items.length; i++) {
        var penType = items[i].getAttribute('data-penalty-type') || 'minor';
        // Only clear minor, double_minor, or bench minor – NOT major or misconduct
        if (penType === 'minor' || penType === 'double_minor' || penType === 'bench') {
            var penId = items[i].getAttribute('data-penalty-id');
            if (penId) {
                sbFetch('clear_penalty', { penalty_id: penId }).then(function(d) {
                    if (d.success) {
                        // Remove cleared penalty from DOM without full page reload
                        var clearedItem = document.querySelector('.sb-ctrl-penalty-item[data-penalty-id="' + penId + '"]');
                        if (clearedItem) clearedItem.remove();
                        var boardSlot = document.querySelector('.sb-board-pen-slot[data-penalty-id="' + penId + '"]');
                        if (boardSlot) {
                            boardSlot.classList.remove('active');
                            boardSlot.removeAttribute('data-penalty-id');
                            var player = boardSlot.querySelector('.sb-pen-player');
                            if (player) player.textContent = '—';
                            var countdown = boardSlot.querySelector('.sb-pen-countdown');
                            if (countdown) countdown.textContent = '--:--';
                        }
                        sbUpdatePenaltyQueueStatus();
                    }
                });
                return; // Only clear ONE (the oldest)
            }
        }
    }
}

function sbUndoGoal(team) {
    sbFetch('undo_goal', { team: team }).then(function(d) {
        if (d.success) {
            document.getElementById('sbHomeScore').textContent = d.home_score;
            document.getElementById('sbAwayScore').textContent = d.away_score;
        }
    });
}

function sbSetScore(team, score) {
    score = Math.max(0, parseInt(score, 10) || 0);
    sbFetch('set_score', { team: team, score: score }).then(function(d) {
        if (d.success) {
            document.getElementById('sbHomeScore').textContent = d.home_score;
            document.getElementById('sbAwayScore').textContent = d.away_score;
        }
    });
}

// ── Shot tracking ─────────────────────────────────────────
function sbAddShot(team) {
    sbFetch('add_shot', { team: team }).then(function(d) {
        if (d.success) {
            document.getElementById('sbHomeShots').textContent = d.home_shots;
            document.getElementById('sbAwayShots').textContent = d.away_shots;
        }
    });
}

function sbSetShots(team, shots) {
    shots = Math.max(0, parseInt(shots, 10) || 0);
    sbFetch('set_shots', { team: team, shots: shots }).then(function(d) {
        if (d.success) {
            document.getElementById('sbHomeShots').textContent = d.home_shots;
            document.getElementById('sbAwayShots').textContent = d.away_shots;
        }
    });
}

// ── Penalty tracking ──────────────────────────────────────
// NHL Rule: Max 2 penalties running concurrently per team.
// 3rd+ penalties are queued and start when an earlier one expires or is cleared.
var SB_MAX_CONCURRENT_PENALTIES = 2;

function sbShowPenaltyModal(team) {
    document.getElementById('sb-penalty-team').value = team;
    document.getElementById('sb-penalty-modal').classList.add('active');
}

function sbAddPenalty(e) {
    e.preventDefault();
    var form = document.getElementById('sbPenaltyForm');
    var fd = new FormData(form);
    // Support custom penalty duration (beer league / minor hockey)
    var duration = fd.get('duration_minutes');
    if (duration === 'custom') {
        var customVal = fd.get('duration_minutes_custom');
        duration = parseInt(customVal, 10);
        if (isNaN(duration) || duration < 1 || duration > 60) {
            alert('Please enter a valid penalty duration (1-60 minutes).');
            return false;
        }
    }
    sbFetch('add_penalty', {
        team: fd.get('team'),
        player_number: fd.get('player_number'),
        player_name: fd.get('player_name'),
        infraction: fd.get('infraction'),
        duration_minutes: duration,
        served_by: fd.get('served_by') || ''
    }).then(function(d) {
        if (d.success) {
            document.getElementById('sb-penalty-modal').classList.remove('active');
            form.reset();
            var customInput = document.getElementById('sb-pen-duration-custom');
            if (customInput) customInput.style.display = 'none';
            sbSaveAndReload();
        }
    });
    return false;
}

// ── Clear / Delete Penalty ────────────────────────────────
function sbClearPenalty(penaltyId) {
    if (!penaltyId) return;
    if (!confirm('Clear this penalty?')) return;
    sbFetch('clear_penalty', { penalty_id: penaltyId }).then(function(d) {
        if (d.success) {
            sbSaveAndReload();
        } else {
            alert(d.message || 'Failed to clear penalty');
        }
    });
}

// ── Individual Penalty Board Visibility ───────────────────
function sbTogglePenaltyItemVisibility(penaltyId) {
    var el = document.querySelector('.sb-board-pen-slot[data-penalty-id="' + penaltyId + '"]');
    if (!el) return;
    el.classList.toggle('sb-hidden-from-display');
    var btn = document.querySelector('.sb-ctrl-penalty-vis-btn[data-penalty-id="' + penaltyId + '"]');
    if (btn) {
        var hidden = el.classList.contains('sb-hidden-from-display');
        btn.innerHTML = hidden ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
        btn.title = hidden ? 'Show on board' : 'Hide from board';
    }
}

// ── Penalty Queue Status Update ───────────────────────────
// Marks penalties beyond the 2-concurrent cap as "queued" in the UI
function sbUpdatePenaltyQueueStatus() {
    ['home', 'away'].forEach(function(team) {
        var items = document.querySelectorAll('.sb-ctrl-penalty-item[data-team="' + team + '"]');
        var running = 0;
        items.forEach(function(item) {
            var penType = item.getAttribute('data-penalty-type') || 'minor';
            // Misconducts don't count against shorthanded cap
            if (penType === 'misconduct' || penType === 'game_misconduct') {
                item.classList.remove('sb-penalty-queued');
                return;
            }
            running++;
            if (running > SB_MAX_CONCURRENT_PENALTIES) {
                item.classList.add('sb-penalty-queued');
            } else {
                item.classList.remove('sb-penalty-queued');
            }
        });
    });
}

// Run on page load
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', function() {
        sbUpdatePenaltyQueueStatus();
    });
}

// ── Buzzer / Horn ─────────────────────────────────────────
function sbBuzzer() {
    var btn = document.getElementById('sbBuzzerBtn');
    if (btn) {
        btn.classList.add('buzzing');
        setTimeout(function() { btn.classList.remove('buzzing'); }, 500);
    }

    // Use custom buzzer sound if uploaded (admin configurable via Settings)
    if (typeof CUSTOM_BUZZER_URL !== 'undefined' && CUSTOM_BUZZER_URL) {
        try {
            var audio = new Audio(CUSTOM_BUZZER_URL);
            audio.volume = 1.0;
            audio.play().catch(function() {});
            return;
        } catch (e) { /* fall through to synthesized */ }
    }

    // Fallback: synthesized buzzer via Web Audio API
    try {
        var ctx = new (window.AudioContext || window.webkitAudioContext)();
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sawtooth';
        osc.frequency.value = 220;
        gain.gain.value = 0.8;
        osc.start();
        // Ramp up then fade
        gain.gain.setValueAtTime(0.8, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 2.5);
        osc.stop(ctx.currentTime + 2.5);
    } catch (e) {
        // Audio API not available
    }
}

// ── Goal Horn (separate from period buzzer) ───────────────
function sbGoalHorn() {
    // Use custom horn sound if uploaded (admin configurable via Settings)
    if (typeof CUSTOM_HORN_URL !== 'undefined' && CUSTOM_HORN_URL) {
        try {
            var audio = new Audio(CUSTOM_HORN_URL);
            audio.volume = 1.0;
            audio.play().catch(function() {});
            return;
        } catch (e) { /* fall through to buzzer fallback */ }
    }

    // Fallback: use buzzer if no separate horn is configured
    sbBuzzer();
}

// ── Clock Mode (stop time vs running time) ────────────────
var sbClockMode = 'stop_time'; // 'stop_time' (NHL) or 'running_time' (beer league)
function sbSetClockMode(mode) {
    sbClockMode = (mode === 'running_time') ? 'running_time' : 'stop_time';
}

// ══════════════════════════════════════════════════════════
// RECURRING TIMED BUZZER (e.g. U7 shift change every 1:30)
// ══════════════════════════════════════════════════════════
var sbRecurringBuzzerInterval = 0;  // seconds between buzzes (0 = off)
var sbRecurringBuzzerCountdown = 0; // seconds remaining until next buzz
var sbRecurringBuzzerActive = false;

function sbSetRecurringBuzzer(seconds) {
    seconds = parseInt(seconds, 10);
    if (isNaN(seconds) || seconds < 0) seconds = 0;
    sbRecurringBuzzerInterval = seconds;
    sbRecurringBuzzerCountdown = seconds;
    sbRecurringBuzzerActive = (seconds > 0);
    sbUpdateRecurringBuzzerDisplay();
}

function sbToggleRecurringBuzzer() {
    if (sbRecurringBuzzerInterval <= 0) return;
    sbRecurringBuzzerActive = !sbRecurringBuzzerActive;
    if (sbRecurringBuzzerActive) {
        sbRecurringBuzzerCountdown = sbRecurringBuzzerInterval;
    }
    sbUpdateRecurringBuzzerDisplay();
}

function sbTickRecurringBuzzer(delta) {
    if (!delta) delta = 1;
    if (!sbRecurringBuzzerActive || sbRecurringBuzzerInterval <= 0) return;
    sbRecurringBuzzerCountdown -= delta;
    if (sbRecurringBuzzerCountdown <= 0) {
        sbBuzzer(); // Fire the buzzer
        sbRecurringBuzzerCountdown = sbRecurringBuzzerInterval; // Reset for next cycle
    }
    sbUpdateRecurringBuzzerDisplay();
}

function sbUpdateRecurringBuzzerDisplay() {
    var statusEl = document.getElementById('sbRecurringStatus');
    var countdownEl = document.getElementById('sbRecurringCountdown');
    var toggleBtn = document.getElementById('sbRecurringToggle');
    if (statusEl) {
        if (sbRecurringBuzzerActive && sbRecurringBuzzerInterval > 0) {
            statusEl.textContent = 'Every ' + sbFormatClock(sbRecurringBuzzerInterval);
            statusEl.classList.add('active');
        } else {
            statusEl.textContent = 'Off';
            statusEl.classList.remove('active');
        }
    }
    if (countdownEl) {
        if (sbRecurringBuzzerActive && sbRecurringBuzzerInterval > 0) {
            countdownEl.textContent = sbFormatClock(sbRecurringBuzzerCountdown);
            countdownEl.style.display = '';
        } else {
            countdownEl.style.display = 'none';
        }
    }
    if (toggleBtn) {
        if (sbRecurringBuzzerActive) {
            toggleBtn.innerHTML = '<i class="fas fa-pause"></i> Pause';
            toggleBtn.classList.add('active');
        } else {
            toggleBtn.innerHTML = '<i class="fas fa-play"></i> Resume';
            toggleBtn.classList.remove('active');
        }
    }
}

// ── Recurring Buzzer State Persistence ────────────────────
function sbSaveRecurringBuzzerState() {
    try {
        sessionStorage.setItem('sb_recurring_buzzer', JSON.stringify({
            interval: sbRecurringBuzzerInterval,
            countdown: sbRecurringBuzzerCountdown,
            active: sbRecurringBuzzerActive
        }));
    } catch (e) { /* sessionStorage unavailable */ }
}

function sbRestoreRecurringBuzzerState(elapsed) {
    if (!elapsed) elapsed = 0;
    try {
        var raw = sessionStorage.getItem('sb_recurring_buzzer');
        if (raw) {
            var state = JSON.parse(raw);
            sbRecurringBuzzerInterval = state.interval || 0;
            sbRecurringBuzzerActive = !!state.active;
            sbRecurringBuzzerCountdown = (state.countdown || 0) - elapsed;
            if (sbRecurringBuzzerCountdown <= 0 && sbRecurringBuzzerInterval > 0) {
                sbRecurringBuzzerCountdown = sbRecurringBuzzerInterval;
            }
            sbUpdateRecurringBuzzerDisplay();
        }
    } catch (e) { /* sessionStorage unavailable or bad data */ }
}

// ══════════════════════════════════════════════════════════
// PENALTY DISPLAY VISIBILITY TOGGLE
// ══════════════════════════════════════════════════════════
var sbPenaltiesHidden = false;

function sbTogglePenaltyDisplay() {
    sbPenaltiesHidden = !sbPenaltiesHidden;
    var stacks = document.querySelectorAll('.sb-board-penalty-stack');
    var ppIndicators = document.querySelectorAll('.sb-pp-indicator');
    stacks.forEach(function(el) {
        el.classList.toggle('sb-hidden-from-display', sbPenaltiesHidden);
    });
    ppIndicators.forEach(function(el) {
        el.classList.toggle('sb-hidden-from-display', sbPenaltiesHidden);
    });
    var toggleBtn = document.getElementById('sbPenaltyDisplayToggle');
    if (toggleBtn) {
        if (sbPenaltiesHidden) {
            toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i> Penalties Hidden from Board';
            toggleBtn.classList.add('sb-toggle-hidden');
        } else {
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i> Penalties Shown on Board';
            toggleBtn.classList.remove('sb-toggle-hidden');
        }
    }
}

// ── Game management ───────────────────────────────────────
function sbStartGame(e) {
    e.preventDefault();
    var form = document.getElementById('sbNewGameForm');
    var fd = new FormData(form);
    sbFetch('start_game', {
        home_team_name: fd.get('home_team_name'),
        away_team_name: fd.get('away_team_name'),
        home_team_id: fd.get('home_team_id'),
        away_team_id: fd.get('away_team_id'),
        is_arctic_wolves_game: fd.get('is_arctic_wolves_game') || '0'
    }).then(function(d) {
        if (d.success) {
            // Preserve custom period/OT duration settings across new game
            sbClockSeconds = sbGetPeriodDuration(1);
            sbSaveAndReload();
        } else {
            alert(d.message || 'Failed to start game');
        }
    });
    return false;
}

function sbEndGame() {
    if (!confirm('End this game? The final score will be recorded.')) return;
    sbFetch('end_game').then(function(d) {
        if (d.success) {
            // Clear clock state – game is over
            try { sessionStorage.removeItem('sb_clock_seconds'); sessionStorage.removeItem('sb_clock_running'); sessionStorage.removeItem('sb_clock_saved_at'); sessionStorage.removeItem('sb_penalty_timers'); sessionStorage.removeItem('sb_penalty_item_clocks'); sessionStorage.removeItem('sb_recurring_buzzer'); } catch (e) {}
            window.location.reload();
        }
    });
}

// ── Video Board ───────────────────────────────────────────
function sbLoadVideo(source) {
    var container = document.getElementById('sbVideoContainer');
    if (!container) return;

    // Reset active state on buttons
    document.querySelectorAll('.sb-video-source-btn').forEach(function(b) {
        b.classList.remove('active');
    });
    var activeBtn = document.querySelector('[data-source="' + source + '"]');
    if (activeBtn) activeBtn.classList.add('active');

    switch (source) {
        case 'pregame':
            container.innerHTML = '<video autoplay loop><source src="/videos/scoreboard/pregame_hype.mp4" type="video/mp4"></video>';
            break;
        case 'ingame_promo':
            container.innerHTML = '<video autoplay loop><source src="/videos/scoreboard/ingame_promo.mp4" type="video/mp4"></video>';
            break;
        case 'arena_cam':
            // Use getUserMedia for in-arena camera + mic
            container.innerHTML = '<video id="sbArenaCam" autoplay muted></video>';
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({ video: true, audio: true })
                    .then(function(stream) {
                        var vid = document.getElementById('sbArenaCam');
                        if (vid) { vid.srcObject = stream; vid.muted = false; }
                    }).catch(function() {
                        container.innerHTML = '<div style="text-align:center;color:#EF4444;padding:40px;"><i class="fas fa-video-slash" style="font-size:48px;display:block;margin-bottom:16px;"></i>Camera access denied or unavailable</div>';
                    });
            }
            break;
        case 'broadcast':
            container.innerHTML = '<div style="text-align:center;color:#888;padding:40px;"><i class="fas fa-satellite-dish" style="font-size:48px;display:block;margin-bottom:16px;"></i>Connect your broadcast feed source.<br><small>Configure in System Tools → Scoreboard Settings</small></div>';
            break;
    }
}

function sbStopVideo() {
    var container = document.getElementById('sbVideoContainer');
    if (!container) return;
    // Stop any active streams
    var videos = container.querySelectorAll('video');
    videos.forEach(function(v) {
        if (v.srcObject) {
            v.srcObject.getTracks().forEach(function(t) { t.stop(); });
        }
    });
    container.innerHTML = '<div style="text-align:center;color:#555;"><i class="fas fa-tv" style="font-size:64px;margin-bottom:16px;display:block;"></i><p>Select a video source below</p></div>';
    document.querySelectorAll('.sb-video-source-btn').forEach(function(b) { b.classList.remove('active'); });
}

function sbShowBrowserVideoModal() {
    document.getElementById('sb-browser-video-modal').classList.add('active');
}

function sbLoadBrowserVideo(e) {
    e.preventDefault();
    var url = document.getElementById('sb-video-url').value.trim();
    if (!url) return false;

    var container = document.getElementById('sbVideoContainer');
    if (!container) return false;

    document.getElementById('sb-browser-video-modal').classList.remove('active');

    // Detect YouTube or Vimeo embeds
    var ytMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]+)/);
    var vmMatch = url.match(/vimeo\.com\/(\d+)/);

    if (ytMatch) {
        container.innerHTML = '<iframe src="https://www.youtube.com/embed/' + ytMatch[1] + '?autoplay=1&rel=0" frameborder="0" allow="autoplay; fullscreen" allowfullscreen style="width:100%;height:100%;"></iframe>';
    } else if (vmMatch) {
        container.innerHTML = '<iframe src="https://player.vimeo.com/video/' + vmMatch[1] + '?autoplay=1" frameborder="0" allow="autoplay; fullscreen" allowfullscreen style="width:100%;height:100%;"></iframe>';
    } else {
        container.innerHTML = '<video autoplay controls><source src="' + url.replace(/"/g, '&quot;') + '"></video>';
    }

    document.querySelectorAll('.sb-video-source-btn').forEach(function(b) { b.classList.remove('active'); });
    var browserBtn = document.querySelector('[data-source="browser"]');
    if (browserBtn) browserBtn.classList.add('active');

    return false;
}

// ── Scoresheet ────────────────────────────────────────────
function sbShowGoalDetailModal() {
    document.getElementById('sb-goal-detail-modal').classList.add('active');
}

function sbAddGoalDetail(e) {
    e.preventDefault();
    var form = document.getElementById('sbGoalDetailForm');
    var fd = new FormData(form);
    sbFetch('add_goal_detail', {
        period: fd.get('period'),
        game_time: fd.get('game_time'),
        team: fd.get('team'),
        scorer_number: fd.get('scorer_number'),
        scorer_name: fd.get('scorer_name'),
        assist1_number: fd.get('assist1_number'),
        assist1_name: fd.get('assist1_name'),
        assist2_number: fd.get('assist2_number'),
        assist2_name: fd.get('assist2_name'),
        goal_type: fd.get('goal_type')
    }).then(function(d) {
        if (d.success) {
            document.getElementById('sb-goal-detail-modal').classList.remove('active');
            form.reset();
            sbSaveAndReload();
        }
    });
    return false;
}

function sbSyncToGamePlan() {
    if (!confirm('Sync this game\'s scoresheet to Game Plan and update player stats?')) return;
    sbFetch('sync_to_gameplan').then(function(d) {
        if (d.success) {
            alert('Synced! ' + (d.stats_updated || 0) + ' stat updates applied.');
        } else {
            alert(d.message || 'Sync failed');
        }
    });
}

// ── Music Integration ─────────────────────────────────────
var sbMusicPlayer = {
    audio: null,
    currentTrack: null,
    playing: false,
    queue: [],
    queueIndex: -1
};

function sbMusicPlay(url, title, artist, source) {
    if (!sbMusicPlayer.audio) {
        sbMusicPlayer.audio = new Audio();
        sbMusicPlayer.audio.addEventListener('ended', function() {
            sbMusicNext();
        });
        sbMusicPlayer.audio.addEventListener('error', function() {
            sbUpdateNowPlaying(null, null, null);
            sbMusicPlayer.playing = false;
            var btn = document.getElementById('sbMusicPlayPause');
            if (btn) btn.innerHTML = '<i class="fas fa-play"></i>';
        });
    }
    sbMusicPlayer.audio.src = url;
    sbMusicPlayer.audio.volume = window._sbMusicVolume || 0.8;
    sbMusicPlayer.currentTrack = { url: url, title: title, artist: artist, source: source };
    sbUpdateNowPlaying(title, artist, source);
    sbMusicPlayer.audio.play().then(function() {
        sbMusicPlayer.playing = true;
        var btn = document.getElementById('sbMusicPlayPause');
        if (btn) btn.innerHTML = '<i class="fas fa-pause"></i>';
    }).catch(function() {
        sbMusicPlayer.playing = false;
        var btn = document.getElementById('sbMusicPlayPause');
        if (btn) btn.innerHTML = '<i class="fas fa-play"></i>';
    });
}

function sbMusicPause() {
    if (sbMusicPlayer.audio && sbMusicPlayer.playing) {
        sbMusicPlayer.audio.pause();
        sbMusicPlayer.playing = false;
        var btn = document.getElementById('sbMusicPlayPause');
        if (btn) btn.innerHTML = '<i class="fas fa-play"></i>';
    }
}

function sbMusicResume() {
    if (sbMusicPlayer.audio && !sbMusicPlayer.playing && sbMusicPlayer.currentTrack) {
        sbMusicPlayer.audio.play().catch(function() {});
        sbMusicPlayer.playing = true;
        var btn = document.getElementById('sbMusicPlayPause');
        if (btn) btn.innerHTML = '<i class="fas fa-pause"></i>';
    }
}

function sbMusicToggle() {
    if (sbMusicPlayer.playing) sbMusicPause();
    else sbMusicResume();
}

function sbMusicStop() {
    if (sbMusicPlayer.audio) {
        sbMusicPlayer.audio.pause();
        sbMusicPlayer.audio.currentTime = 0;
    }
    sbMusicPlayer.playing = false;
    sbMusicPlayer.currentTrack = null;
    sbUpdateNowPlaying(null, null, null);
    var btn = document.getElementById('sbMusicPlayPause');
    if (btn) btn.innerHTML = '<i class="fas fa-play"></i>';
}

function sbMusicNext() {
    if (sbMusicPlayer.queue.length === 0) {
        sbMusicStop();
        return;
    }
    sbMusicPlayer.queueIndex++;
    if (sbMusicPlayer.queueIndex >= sbMusicPlayer.queue.length) sbMusicPlayer.queueIndex = 0;
    var track = sbMusicPlayer.queue[sbMusicPlayer.queueIndex];
    sbMusicPlay(track.url, track.title, track.artist, track.source);
    sbHighlightActiveTrack();
}

function sbMusicPrev() {
    if (sbMusicPlayer.queue.length === 0) return;
    sbMusicPlayer.queueIndex--;
    if (sbMusicPlayer.queueIndex < 0) sbMusicPlayer.queueIndex = sbMusicPlayer.queue.length - 1;
    var track = sbMusicPlayer.queue[sbMusicPlayer.queueIndex];
    sbMusicPlay(track.url, track.title, track.artist, track.source);
    sbHighlightActiveTrack();
}

function sbHighlightActiveTrack() {
    document.querySelectorAll('.sb-ml-track').forEach(function(el) {
        el.classList.remove('sb-ml-track-active');
    });
    if (sbMusicPlayer.currentTrack) {
        var active = document.querySelector('.sb-ml-track[data-url="' + CSS.escape(sbMusicPlayer.currentTrack.url) + '"]');
        if (active) active.classList.add('sb-ml-track-active');
    }
}

function sbUpdateNowPlaying(title, artist, source) {
    var el = document.getElementById('sbNowPlaying');
    if (!el) return;
    if (!title) {
        el.innerHTML = '<i class="fas fa-music"></i> <strong>No music playing</strong>';
        return;
    }
    var sourceIcon = source === 'subsonic' ? '<i class="fas fa-server"></i>' :
                     source === 'spotify' ? '<i class="fab fa-spotify"></i>' :
                     source === 'apple_music' ? '<i class="fab fa-apple"></i>' :
                     '<i class="fas fa-music"></i>';
    el.innerHTML = sourceIcon + ' <strong>' + (title || '').replace(/</g, '&lt;') + '</strong>' +
                   (artist ? ' — ' + artist.replace(/</g, '&lt;') : '');
}

// ── Unified Music Library Modal ───────────────────────────
function sbOpenMusicLibrary() {
    var modal = document.getElementById('sb-music-library-modal');
    if (modal) {
        modal.classList.add('active');
        // Load Subsonic library by default if configured
        var subsonicTab = document.querySelector('.sb-ml-tab[data-source="subsonic"]');
        if (subsonicTab) {
            sbMusicLibraryLoadSource('subsonic');
            document.querySelectorAll('.sb-ml-tab').forEach(function(t) { t.classList.remove('active'); });
            subsonicTab.classList.add('active');
        } else {
            // Load the first available tab
            var firstTab = document.querySelector('.sb-ml-tab');
            if (firstTab) {
                firstTab.classList.add('active');
                sbMusicLibraryLoadSource(firstTab.getAttribute('data-source'));
            }
        }
    }
}

function sbMusicLibraryLoadSource(source) {
    document.querySelectorAll('.sb-ml-tab').forEach(function(t) { t.classList.remove('active'); });
    var tab = document.querySelector('.sb-ml-tab[data-source="' + source + '"]');
    if (tab) tab.classList.add('active');

    var content = document.getElementById('sbMusicLibraryContent');
    if (!content) return;

    if (source === 'subsonic') {
        content.innerHTML = '<div class="sb-ml-loading"><i class="fas fa-spinner fa-spin"></i> Loading library…</div>';
        sbSubsonicFetchLibrary(content);
    } else if (source === 'spotify') {
        content.innerHTML = '<div class="sb-ml-loading"><i class="fab fa-spotify"></i> Spotify Web Playback requires OAuth. Configure credentials in <a href="?view=settings" style="color:#1DB954;">Settings</a>.</div>';
    } else if (source === 'apple_music') {
        content.innerHTML = '<div class="sb-ml-loading"><i class="fab fa-apple"></i> Apple Music requires MusicKit JS. Configure credentials in <a href="?view=settings" style="color:#FC3C44;">Settings</a>.</div>';
    }
}

function sbSubsonicFetchLibrary(container) {
    sbFetch('subsonic_library').then(function(d) {
        if (!d.success) {
            container.innerHTML = '<div class="sb-ml-empty"><i class="fas fa-exclamation-triangle"></i> ' +
                ((d.message || 'Could not load library').replace(/</g, '&lt;')) +
                (IS_ADMIN ? '<br><a href="?view=settings" style="color:#6B46C1;">Configure in Settings</a>' : '') + '</div>';
            return;
        }
        sbRenderSubsonicLibrary(container, d.artists || [], d.albums || [], d.songs || []);
    });
}

function sbRenderSubsonicLibrary(container, artists, albums, songs) {
    if (!artists.length && !albums.length && !songs.length) {
        container.innerHTML = '<div class="sb-ml-empty"><i class="fas fa-music"></i> Library is empty</div>';
        return;
    }
    var html = '';
    // Search bar
    html += '<div class="sb-ml-search"><input type="text" id="sbMusicSearch" placeholder="Search music…" oninput="sbMusicLibraryFilter(this.value)"><i class="fas fa-search"></i></div>';

    // Albums section
    if (albums.length) {
        html += '<div class="sb-ml-section-title">Albums</div>';
        html += '<div class="sb-ml-grid">';
        albums.forEach(function(album) {
            var coverUrl = album.cover || '';
            html += '<div class="sb-ml-album-card" data-album-id="' + (album.id || '').replace(/"/g, '&quot;') + '" data-search="' + ((album.name || '') + ' ' + (album.artist || '')).replace(/"/g, '&quot;').toLowerCase() + '">' +
                    (coverUrl ? '<img class="sb-ml-album-cover" src="' + coverUrl.replace(/"/g, '&quot;') + '" alt="">' : '<div class="sb-ml-album-cover sb-ml-no-art"><i class="fas fa-compact-disc"></i></div>') +
                    '<div class="sb-ml-album-name">' + (album.name || 'Unknown Album').replace(/</g, '&lt;') + '</div>' +
                    '<div class="sb-ml-album-artist">' + (album.artist || '').replace(/</g, '&lt;') + '</div>' +
                    '</div>';
        });
        html += '</div>';
    }

    // Songs section
    if (songs.length) {
        html += '<div class="sb-ml-section-title">Songs</div>';
        html += '<div class="sb-ml-track-list">';
        songs.forEach(function(song, idx) {
            html += '<div class="sb-ml-track" data-url="' + (song.url || '').replace(/"/g, '&quot;') + '" data-search="' + ((song.title || '') + ' ' + (song.artist || '') + ' ' + (song.album || '')).replace(/"/g, '&quot;').toLowerCase() + '">' +
                    '<div class="sb-ml-track-num">' + (idx + 1) + '</div>' +
                    '<div class="sb-ml-track-info">' +
                    '<div class="sb-ml-track-title">' + (song.title || 'Unknown').replace(/</g, '&lt;') + '</div>' +
                    '<div class="sb-ml-track-artist">' + (song.artist || '').replace(/</g, '&lt;') + (song.album ? ' — ' + song.album.replace(/</g, '&lt;') : '') + '</div>' +
                    '</div>' +
                    '<div class="sb-ml-track-duration">' + (song.duration || '') + '</div>' +
                    '<button class="sb-ml-track-play" onclick="sbPlaySubsonicTrack(this.parentElement)" title="Play"><i class="fas fa-play"></i></button>' +
                    '</div>';
        });
        html += '</div>';
    }

    container.innerHTML = html;
    // Event delegation for album cards
    container.querySelectorAll('.sb-ml-album-card').forEach(function(card) {
        card.addEventListener('click', function() {
            var albumId = this.getAttribute('data-album-id');
            if (albumId) sbSubsonicLoadAlbum(albumId);
        });
    });
}

function sbSubsonicLoadAlbum(albumId) {
    var container = document.getElementById('sbMusicLibraryContent');
    if (!container) return;
    container.innerHTML = '<div class="sb-ml-loading"><i class="fas fa-spinner fa-spin"></i> Loading album…</div>';
    sbFetch('subsonic_album', { album_id: albumId }).then(function(d) {
        if (!d.success) {
            container.innerHTML = '<div class="sb-ml-empty"><i class="fas fa-exclamation-triangle"></i> ' + (d.message || 'Failed to load album').replace(/</g, '&lt;') + '</div>';
            return;
        }
        var html = '<button class="sb-ml-back-btn" onclick="sbMusicLibraryLoadSource(\'subsonic\')"><i class="fas fa-arrow-left"></i> Back to Library</button>';
        var album = d.album || {};
        html += '<div class="sb-ml-album-header">';
        if (album.cover) html += '<img class="sb-ml-album-header-cover" src="' + album.cover.replace(/"/g, '&quot;') + '" alt="">';
        html += '<div class="sb-ml-album-header-info">';
        html += '<div class="sb-ml-album-header-name">' + (album.name || 'Album').replace(/</g, '&lt;') + '</div>';
        html += '<div class="sb-ml-album-header-artist">' + (album.artist || '').replace(/</g, '&lt;') + '</div>';
        html += '<button class="sb-ml-play-all-btn" onclick="sbPlayAllSubsonicTracks()"><i class="fas fa-play"></i> Play All</button>';
        html += '</div></div>';

        var songs = d.songs || [];
        html += '<div class="sb-ml-track-list">';
        songs.forEach(function(song, idx) {
            html += '<div class="sb-ml-track" data-url="' + (song.url || '').replace(/"/g, '&quot;') + '" data-search="' + ((song.title || '') + ' ' + (song.artist || '')).replace(/"/g, '&quot;').toLowerCase() + '">' +
                    '<div class="sb-ml-track-num">' + (idx + 1) + '</div>' +
                    '<div class="sb-ml-track-info">' +
                    '<div class="sb-ml-track-title">' + (song.title || 'Unknown').replace(/</g, '&lt;') + '</div>' +
                    '<div class="sb-ml-track-artist">' + (song.artist || '').replace(/</g, '&lt;') + '</div>' +
                    '</div>' +
                    '<div class="sb-ml-track-duration">' + (song.duration || '') + '</div>' +
                    '<button class="sb-ml-track-play" onclick="sbPlaySubsonicTrack(this.parentElement)" title="Play"><i class="fas fa-play"></i></button>' +
                    '</div>';
        });
        html += '</div>';
        container.innerHTML = html;
    });
}

function sbPlaySubsonicTrack(trackEl) {
    var url = trackEl.getAttribute('data-url');
    var titleEl = trackEl.querySelector('.sb-ml-track-title');
    var artistEl = trackEl.querySelector('.sb-ml-track-artist');
    var title = titleEl ? titleEl.textContent : 'Unknown';
    var artist = artistEl ? artistEl.textContent : '';
    // Build queue from visible tracks
    sbMusicPlayer.queue = [];
    sbMusicPlayer.queueIndex = -1;
    var allTracks = trackEl.parentElement.querySelectorAll('.sb-ml-track');
    allTracks.forEach(function(t, idx) {
        var tUrl = t.getAttribute('data-url');
        var tTitle = t.querySelector('.sb-ml-track-title');
        var tArtist = t.querySelector('.sb-ml-track-artist');
        sbMusicPlayer.queue.push({
            url: tUrl,
            title: tTitle ? tTitle.textContent : 'Unknown',
            artist: tArtist ? tArtist.textContent : '',
            source: 'subsonic'
        });
        if (tUrl === url) sbMusicPlayer.queueIndex = idx;
    });
    sbMusicPlay(url, title, artist, 'subsonic');
    sbHighlightActiveTrack();
}

function sbPlayAllSubsonicTracks() {
    var container = document.getElementById('sbMusicLibraryContent');
    if (!container) return;
    var allTracks = container.querySelectorAll('.sb-ml-track');
    if (!allTracks.length) return;
    sbMusicPlayer.queue = [];
    allTracks.forEach(function(t) {
        var tUrl = t.getAttribute('data-url');
        var tTitle = t.querySelector('.sb-ml-track-title');
        var tArtist = t.querySelector('.sb-ml-track-artist');
        sbMusicPlayer.queue.push({
            url: tUrl,
            title: tTitle ? tTitle.textContent : 'Unknown',
            artist: tArtist ? tArtist.textContent : '',
            source: 'subsonic'
        });
    });
    sbMusicPlayer.queueIndex = 0;
    var first = sbMusicPlayer.queue[0];
    sbMusicPlay(first.url, first.title, first.artist, 'subsonic');
    sbHighlightActiveTrack();
}

function sbMusicLibraryFilter(query) {
    query = (query || '').toLowerCase().trim();
    document.querySelectorAll('.sb-ml-track, .sb-ml-album-card').forEach(function(el) {
        var search = el.getAttribute('data-search') || '';
        el.style.display = (!query || search.indexOf(query) >= 0) ? '' : 'none';
    });
}

function sbSpotifyConnect() {
    sbOpenMusicLibrary();
    var tab = document.querySelector('.sb-ml-tab[data-source="spotify"]');
    if (tab) {
        sbMusicLibraryLoadSource('spotify');
    }
}

function sbAppleMusicConnect() {
    sbOpenMusicLibrary();
    var tab = document.querySelector('.sb-ml-tab[data-source="apple_music"]');
    if (tab) {
        sbMusicLibraryLoadSource('apple_music');
    }
}

function sbSubsonicBrowse() {
    sbOpenMusicLibrary();
    var tab = document.querySelector('.sb-ml-tab[data-source="subsonic"]');
    if (tab) {
        sbMusicLibraryLoadSource('subsonic');
    }
}

function sbToggleMic() {
    // Toggle arena mic input via getUserMedia
    if (window._sbMicStream) {
        window._sbMicStream.getTracks().forEach(function(t) { t.stop(); });
        window._sbMicStream = null;
        document.querySelector('.sb-music-btn.mic').style.borderColor = '#2D2D3F';
        return;
    }
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ audio: true })
            .then(function(stream) {
                window._sbMicStream = stream;
                // Route mic to speakers via Web Audio API
                var ctx = new (window.AudioContext || window.webkitAudioContext)();
                var src = ctx.createMediaStreamSource(stream);
                src.connect(ctx.destination);
                window._sbMicCtx = ctx;
                document.querySelector('.sb-music-btn.mic').style.borderColor = '#EF4444';
            }).catch(function() {
                alert('Microphone access denied or unavailable.');
            });
    }
}

function sbSpeakerSettings() {
    // Wireless speaker configuration placeholder
    alert('Wireless Speaker Setup: Connect Bluetooth or network speakers through your system audio settings. The scoreboard audio output (buzzer, music, mic) will route to the default audio device.');
}

function sbSetAudioConfig(config) {
    // Apply audio configuration from the Audio Settings modal
    if (config.speaker && typeof HTMLMediaElement !== 'undefined' && HTMLMediaElement.prototype.setSinkId) {
        // Set output device for all media elements
        document.querySelectorAll('audio, video').forEach(function(el) {
            if (typeof el.setSinkId === 'function') {
                el.setSinkId(config.speaker).catch(function() { /* ignore */ });
            }
        });
    }
    // Store volume preference
    window._sbMusicVolume = parseInt(config.volume, 10) / 100;
    // Store mic device preference for announce mode
    window._sbMicDeviceId = config.mic || '';
}
