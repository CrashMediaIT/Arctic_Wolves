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
.m-speed-input {
    width: 100%; max-width: 220px; min-height: 56px;
    background: #0A0A0F; border: 2px solid #2D2D3F; border-radius: 12px;
    color: #fff; font-size: 32px; font-weight: 700; text-align: center;
    padding: 8px 16px; font-family: Inter, sans-serif;
    -moz-appearance: textfield;
}
.m-speed-input:focus { border-color: #8B5CF6; outline: none; }
.m-speed-input::-webkit-outer-spin-button,
.m-speed-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
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
    display: block; width: 100%; max-width: 220px; margin: 16px auto 0;
    min-height: 56px; border-radius: 12px;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    color: #fff; font-size: 16px; font-weight: 600;
    border: none; cursor: pointer; font-family: Inter, sans-serif;
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.m-record-btn:active { transform: scale(0.97); }
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
.m-history-del {
    background: none; border: none; color: #EF4444; font-size: 14px;
    cursor: pointer; padding: 8px; min-width: 44px; min-height: 44px;
    display: flex; align-items: center; justify-content: center;
}
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-shotspeed">
    <div class="m-shotspeed-header">
        <h2 class="m-shotspeed-title">Shot Speed</h2>
        <p class="m-shotspeed-sub">Record and track shot speeds</p>
    </div>

    <div class="m-speed-input-wrap">
        <input type="number" class="m-speed-input" id="mSpeedVal" placeholder="0" min="0" max="999" step="0.1" inputmode="decimal">
        <span class="m-speed-label">Enter speed value</span>
        <div class="m-unit-toggle">
            <button class="m-unit-btn m-unit-active" id="mUnitMph" type="button" onclick="mSetUnit('mph')">mph</button>
            <button class="m-unit-btn" id="mUnitKmh" type="button" onclick="mSetUnit('kmh')">km/h</button>
        </div>
        <button class="m-record-btn" type="button" onclick="mRecordSpeed()">
            <i class="fas fa-plus-circle"></i> Record
        </button>
    </div>

    <div class="m-history-section">
        <h3 class="m-history-title">Recent Entries</h3>
        <div id="mSpeedHistory"></div>
    </div>
</div>

<script>
(function() {
    var unit = 'mph';
    var STORAGE_KEY = 'pwa_shot_speeds';

    function getHistory() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
        } catch(e) { return []; }
    }

    function saveHistory(data) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    }

    function renderHistory() {
        var hist = getHistory();
        var container = document.getElementById('mSpeedHistory');
        if (hist.length === 0) {
            container.innerHTML = '<div class="m-empty-state"><i class="fas fa-gauge-high"></i>No entries recorded</div>';
            return;
        }
        var html = '';
        hist.forEach(function(entry, idx) {
            var d = new Date(entry.timestamp);
            var timeStr = d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
            html += '<div class="m-history-item">' +
                '<div><span class="m-history-speed">' + entry.speed + '</span><span class="m-history-unit">' + entry.unit + '</span></div>' +
                '<span class="m-history-time">' + timeStr + '</span>' +
                '<button class="m-history-del" type="button" onclick="mDelSpeed(' + idx + ')" title="Delete"><i class="fas fa-trash"></i></button>' +
                '</div>';
        });
        container.innerHTML = html;
    }

    window.mSetUnit = function(u) {
        unit = u;
        document.getElementById('mUnitMph').className = 'm-unit-btn' + (u === 'mph' ? ' m-unit-active' : '');
        document.getElementById('mUnitKmh').className = 'm-unit-btn' + (u === 'kmh' ? ' m-unit-active' : '');
    };

    window.mRecordSpeed = function() {
        var val = parseFloat(document.getElementById('mSpeedVal').value);
        if (!val || val <= 0) return;
        var hist = getHistory();
        hist.unshift({ speed: val, unit: unit, timestamp: new Date().toISOString() });
        if (hist.length > 50) hist = hist.slice(0, 50);
        saveHistory(hist);
        document.getElementById('mSpeedVal').value = '';
        renderHistory();
    };

    window.mDelSpeed = function(idx) {
        var hist = getHistory();
        hist.splice(idx, 1);
        saveHistory(hist);
        renderHistory();
    };

    renderHistory();
})();
</script>
