<?php
/**
 * PWA POS Schedule - Mobile-native staff schedule view
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$canAccessPOS) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>POS access required</div>';
    return;
}

$shifts = [];
try {
    $stmt = $pdo->prepare("SELECT id, shift_date, start_time, end_time, role_assigned, notes FROM staff_schedules WHERE user_id = ? AND shift_date >= CURDATE() ORDER BY shift_date ASC, start_time ASC LIMIT 14");
    $stmt->execute([$user_id]);
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $shifts = []; }

// Group shifts by date
$grouped = [];
foreach ($shifts as $s) {
    $dateKey = $s['shift_date'];
    $grouped[$dateKey][] = $s;
}
?>
<style>
.m-schedule { padding: 16px; font-family: Inter, sans-serif; }
.m-schedule-header { margin-bottom: 16px; }
.m-schedule-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-schedule-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-schedule-actions-bar { display: flex; gap: 8px; margin-bottom: 16px; }
.m-schedule-action-btn {
    flex: 1; min-height: 44px; border-radius: 10px;
    border: 1px solid #2D2D3F; background: #16161F; color: #fff;
    font-size: 13px; font-weight: 600; cursor: pointer;
    font-family: Inter, sans-serif; display: flex; align-items: center;
    justify-content: center; gap: 6px;
}
.m-schedule-action-btn i { color: #8B5CF6; }
.m-schedule-action-btn:active { transform: scale(0.98); }
.m-schedule-date-group { margin-bottom: 16px; }
.m-schedule-date-label {
    font-size: 13px; font-weight: 600; color: #8B5CF6;
    margin-bottom: 8px; padding-bottom: 4px;
    border-bottom: 1px solid #2D2D3F;
}
.m-schedule-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-schedule-time-block {
    min-width: 60px; text-align: center;
    background: rgba(107,70,193,0.15); border-radius: 10px;
    padding: 8px 6px; flex-shrink: 0;
}
.m-schedule-time-start { font-size: 13px; font-weight: 700; color: #fff; display: block; }
.m-schedule-time-end { font-size: 11px; color: #A8A8B8; display: block; margin-top: 2px; }
.m-schedule-info { flex: 1; min-width: 0; }
.m-schedule-role { font-size: 13px; font-weight: 600; color: #fff; }
.m-schedule-notes { font-size: 12px; color: #A8A8B8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
.m-bs-overlay {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); z-index: 999;
}
.m-bs-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000;
    background: #16161F; border-radius: 16px 16px 0 0;
    padding: 20px 16px 32px; display: none;
    max-height: 85vh; overflow-y: auto;
}
.m-bs-handle { width: 40px; height: 4px; background: #2D2D3F; border-radius: 2px; margin: 0 auto 16px; }
.m-bs-title { font-size: 16px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-form-group { margin-bottom: 12px; }
.m-form-label { font-size: 12px; color: #A8A8B8; margin-bottom: 6px; display: block; }
.m-form-input {
    width: 100%; min-height: 44px; padding: 12px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box;
}
.m-form-input:focus { border-color: #8B5CF6; outline: none; }
.m-form-textarea {
    width: 100%; min-height: 80px; padding: 12px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box; resize: vertical;
}
.m-form-textarea:focus { border-color: #8B5CF6; outline: none; }
.m-form-submit {
    width: 100%; min-height: 44px; border-radius: 10px;
    background: #6B46C1; color: #fff; font-size: 14px; font-weight: 600;
    border: none; cursor: pointer; font-family: Inter, sans-serif; margin-top: 8px;
}
.m-form-submit:disabled { opacity: 0.5; }
.m-alert {
    padding: 10px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 10px;
    display: none; text-align: center;
}
.m-alert-success { background: rgba(16,185,129,0.15); color: #10B981; }
.m-alert-error { background: rgba(239,68,68,0.15); color: #EF4444; }
</style>

<div class="m-schedule">
    <div class="m-schedule-header">
        <h2 class="m-schedule-title">My Schedule</h2>
        <p class="m-schedule-sub">Upcoming shifts</p>
    </div>

    <div id="mSchedAlert" class="m-alert"></div>

    <div class="m-schedule-actions-bar">
        <button class="m-schedule-action-btn" type="button" onclick="mSchedOpenSheet('swap')">
            <i class="fas fa-exchange-alt"></i> Shift Swap
        </button>
        <button class="m-schedule-action-btn" type="button" onclick="mSchedOpenSheet('timeoff')">
            <i class="fas fa-calendar-minus"></i> Time Off
        </button>
    </div>

    <?php if (empty($grouped)): ?>
        <div class="m-empty-state">
            <i class="fas fa-calendar-alt"></i>
            No upcoming shifts scheduled
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $dateKey => $dayShifts):
            $dateObj = strtotime($dateKey);
            $isToday = date('Y-m-d', $dateObj) === date('Y-m-d');
            $dateLabel = $isToday ? 'Today — ' . date('M j', $dateObj) : date('l, M j', $dateObj);
        ?>
        <div class="m-schedule-date-group">
            <div class="m-schedule-date-label"><?= $dateLabel ?></div>
            <?php foreach ($dayShifts as $shift):
                $startTime = $shift['start_time'] ? date('g:i A', strtotime($shift['start_time'])) : '--';
                $endTime = $shift['end_time'] ? date('g:i A', strtotime($shift['end_time'])) : '--';
            ?>
            <div class="m-schedule-card">
                <div class="m-schedule-time-block">
                    <span class="m-schedule-time-start"><?= $startTime ?></span>
                    <span class="m-schedule-time-end"><?= $endTime ?></span>
                </div>
                <div class="m-schedule-info">
                    <div class="m-schedule-role"><?= htmlspecialchars($shift['role_assigned'] ?: 'General') ?></div>
                    <?php if (!empty($shift['notes'])): ?>
                        <div class="m-schedule-notes"><?= htmlspecialchars($shift['notes']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="m-bs-overlay" id="mSchedOverlay" onclick="mSchedCloseSheet()"></div>
<div class="m-bs-sheet" id="mSchedSheet">
    <div class="m-bs-handle"></div>
    <h3 class="m-bs-title" id="mSchedSheetTitle">Request</h3>
    <form id="mSchedForm" onsubmit="return mSchedSubmit(event)">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" id="mSchedAction" value="">
        <div id="mSchedSwapFields">
            <div class="m-form-group">
                <label class="m-form-label">Shift Date *</label>
                <input type="date" name="shift_date" id="mSchedDate" class="m-form-input" required>
            </div>
            <div class="m-form-group" id="mSchedSwapWith" style="display:none;">
                <label class="m-form-label">Swap With (Staff Name)</label>
                <input type="text" name="swap_with" class="m-form-input" placeholder="Enter colleague's name">
            </div>
        </div>
        <div class="m-form-group" id="mSchedTimeOffEnd" style="display:none;">
            <label class="m-form-label">End Date</label>
            <input type="date" name="end_date" class="m-form-input">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Reason / Notes</label>
            <textarea name="notes" class="m-form-textarea" rows="3" placeholder="Reason for request..."></textarea>
        </div>
        <button type="submit" class="m-form-submit" id="mSchedSubmitBtn">Submit Request</button>
    </form>
</div>

<script>
(function() {
    var csrfToken = document.querySelector('#mSchedForm [name="csrf_token"]')?.value || '';

    function showAlert(type, msg) {
        var el = document.getElementById('mSchedAlert');
        el.className = 'm-alert m-alert-' + type;
        el.textContent = msg;
        el.style.display = 'block';
        setTimeout(function() { el.style.display = 'none'; }, 4000);
    }

    window.mSchedOpenSheet = function(type) {
        var title = document.getElementById('mSchedSheetTitle');
        var action = document.getElementById('mSchedAction');
        var swapWith = document.getElementById('mSchedSwapWith');
        var timeOffEnd = document.getElementById('mSchedTimeOffEnd');
        if (type === 'swap') {
            title.textContent = 'Request Shift Swap';
            action.value = 'request_shift_swap';
            swapWith.style.display = 'block';
            timeOffEnd.style.display = 'none';
        } else {
            title.textContent = 'Request Time Off';
            action.value = 'request_time_off';
            swapWith.style.display = 'none';
            timeOffEnd.style.display = 'block';
        }
        document.getElementById('mSchedSheet').style.display = 'block';
        document.getElementById('mSchedOverlay').style.display = 'block';
    };

    window.mSchedCloseSheet = function() {
        document.getElementById('mSchedSheet').style.display = 'none';
        document.getElementById('mSchedOverlay').style.display = 'none';
    };

    window.mSchedSubmit = function(e) {
        e.preventDefault();
        var btn = document.getElementById('mSchedSubmitBtn');
        btn.disabled = true;
        btn.textContent = 'Submitting...';
        var fd = new FormData(document.getElementById('mSchedForm'));
        fetch('process_pos.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showAlert('success', data.message || 'Request submitted');
                    mSchedCloseSheet();
                } else { showAlert('error', data.message || 'Error submitting request'); }
            })
            .catch(function() { showAlert('error', 'Network error'); })
            .finally(function() { btn.disabled = false; btn.textContent = 'Submit Request'; });
        return false;
    };
})();
</script>
