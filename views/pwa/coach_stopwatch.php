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
</style>

<div class="m-stopwatch">
    <div class="m-stopwatch-header">
        <h2 class="m-stopwatch-title">Stopwatch</h2>
        <p class="m-stopwatch-sub">Tap to start timing</p>
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

    <div class="m-lap-section">
        <h3 class="m-lap-title">Laps</h3>
        <ul class="m-lap-list" id="mSwLaps">
            <li class="m-empty-state"><i class="fas fa-flag"></i>No laps recorded</li>
        </ul>
    </div>
</div>

<script>
(function() {
    var running = false, startTime = 0, elapsed = 0, timer = null;
    var laps = [], lastLap = 0;

    function pad(n, d) { return String(n).padStart(d || 2, '0'); }

    function formatTime(ms) {
        var h = Math.floor(ms / 3600000);
        var m = Math.floor((ms % 3600000) / 60000);
        var s = Math.floor((ms % 60000) / 1000);
        var cs = Math.floor((ms % 1000) / 10);
        return { main: pad(h) + ':' + pad(m) + ':' + pad(s), ms: '.' + pad(cs) };
    }

    function update() {
        var now = Date.now();
        var total = elapsed + (now - startTime);
        var t = formatTime(total);
        document.getElementById('mSwTime').textContent = t.main;
        document.getElementById('mSwMs').textContent = t.ms;
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
    };

    window.mSwLap = function() {
        if (!running) return;
        var total = elapsed + (Date.now() - startTime);
        var diff = total - lastLap;
        lastLap = total;
        laps.push({ total: total, diff: diff });
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
})();
</script>
