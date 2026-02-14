<?php
/**
 * PWA Schedules - Mobile-native accounting report schedules
 * Purpose-built for mobile phones.
 */

if (!$isAdmin) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Admin access required.</p>';
    echo '</div>';
    return;
}

$schedules = [];
try {
    $stmt = $pdo->prepare("
        SELECT rs.id, rs.report_name, rs.report_type, rs.schedule_frequency, rs.next_run, rs.is_active,
               rs.schedule_time, rs.recipients, rs.output_format
        FROM report_schedules rs
        WHERE rs.created_by = ?
        ORDER BY rs.is_active DESC, rs.next_run ASC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $schedules = []; }

$totalSchedules = count($schedules);
?>
<style>
.m-schedules { padding: 16px; font-family: Inter, sans-serif; }
.m-schedules-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.m-schedules-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-schedules-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-schedules-add-btn {
    min-width: 44px; min-height: 44px; border-radius: 50%;
    background: #6B46C1; color: #fff; border: none; font-size: 18px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.m-sched-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-sched-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-sched-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-sched-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-sched-badge-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-sched-badge-paused { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-sched-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-sched-meta { font-size: 12px; color: #A8A8B8; display: flex; gap: 12px; flex-wrap: wrap; }
.m-sched-meta i { font-size: 10px; }
.m-sched-actions { display: flex; gap: 4px; margin-top: 8px; padding-top: 8px; border-top: 1px solid #2D2D3F; }
.m-sched-action-btn {
    min-width: 36px; min-height: 36px; border: none; border-radius: 8px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 13px; background: none;
}
.m-sched-action-btn.m-toggle { color: #F59E0B; }
.m-sched-action-btn.m-edit { color: #8B5CF6; }
.m-sched-action-btn.m-del { color: #EF4444; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
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

<div class="m-schedules">
    <div class="m-schedules-header">
        <div>
            <h2 class="m-schedules-title">Report Schedules</h2>
            <p class="m-schedules-sub"><?= $totalSchedules ?> schedule<?= $totalSchedules !== 1 ? 's' : '' ?></p>
        </div>
        <button class="m-schedules-add-btn" type="button" onclick="mSchedFormOpen()" title="Create Schedule">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <div id="mSchedPageAlert" class="m-alert"></div>

    <?php if (empty($schedules)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clock"></i>
            <p>No report schedules configured</p>
        </div>
    <?php else: ?>
        <?php foreach ($schedules as $s):
            $isActive = ($s['is_active'] ?? 1) == 1;
            $badgeClass = $isActive ? 'active' : 'paused';
            $name = $s['report_name'] ?? ucwords(str_replace('_', ' ', $s['report_type'] ?? 'Schedule'));
        ?>
        <div class="m-sched-card" id="mSched-<?= (int)$s['id'] ?>">
            <div class="m-sched-top">
                <span class="m-sched-name"><?= htmlspecialchars($name) ?></span>
                <span class="m-sched-badge m-sched-badge-<?= $badgeClass ?>"><?= $isActive ? 'Active' : 'Paused' ?></span>
            </div>
            <div class="m-sched-meta">
                <?php if (!empty($s['schedule_frequency'])): ?>
                <span><i class="fas fa-sync"></i> <?= htmlspecialchars(ucfirst($s['schedule_frequency'])) ?></span>
                <?php endif; ?>
                <?php if (!empty($s['next_run'])): ?>
                <span><i class="fas fa-calendar"></i> Next: <?= date('M j, Y', strtotime($s['next_run'])) ?></span>
                <?php endif; ?>
            </div>
            <div class="m-sched-actions">
                <button class="m-sched-action-btn m-toggle" type="button" onclick="mSchedToggle(<?= (int)$s['id'] ?>, <?= $isActive ? 1 : 0 ?>)" title="<?= $isActive ? 'Pause' : 'Resume' ?>">
                    <i class="fas fa-<?= $isActive ? 'pause' : 'play' ?>"></i>
                </button>
                <button class="m-sched-action-btn m-edit" type="button" onclick="mSchedFormOpen(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)" title="Edit">
                    <i class="fas fa-pen"></i>
                </button>
                <button class="m-sched-action-btn m-del" type="button" onclick="mSchedDelete(<?= (int)$s['id'] ?>)" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="m-bs-overlay" id="mSchedOverlay" onclick="mSchedFormClose()"></div>
<div class="m-bs-sheet" id="mSchedSheet">
    <div class="m-bs-handle"></div>
    <h3 class="m-bs-title" id="mSchedSheetTitle">Create Schedule</h3>
    <form id="mSchedCreateForm" onsubmit="return mSchedFormSubmit(event)">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" id="mSchedCreateAction" value="schedule_create">
        <input type="hidden" name="schedule_id" id="mSchedCreateId" value="">
        <div class="m-form-group">
            <label class="m-form-label">Schedule Name *</label>
            <input type="text" name="schedule_name" id="mSchedCreateName" class="m-form-input" required placeholder="e.g., Monthly Revenue Report">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Report Type *</label>
            <select name="report_type" id="mSchedCreateType" class="m-form-input" required>
                <option value="">-- Select Report --</option>
                <option value="revenue_summary">Revenue Summary</option>
                <option value="expense_report">Expense Report</option>
                <option value="profit_loss">Profit & Loss</option>
                <option value="client_billing">Client Billing</option>
                <option value="session_analytics">Session Analytics</option>
            </select>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Frequency *</label>
            <select name="frequency" id="mSchedCreateFreq" class="m-form-input" required>
                <option value="">-- Select --</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly</option>
                <option value="annually">Annually</option>
            </select>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Time</label>
            <input type="time" name="time" id="mSchedCreateTime" class="m-form-input" value="09:00">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Email Recipients</label>
            <input type="text" name="email_recipients" id="mSchedCreateRecipients" class="m-form-input" placeholder="email1@example.com, email2@example.com">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Format</label>
            <select name="format" id="mSchedCreateFormat" class="m-form-input">
                <option value="pdf">PDF</option>
                <option value="excel">Excel</option>
            </select>
        </div>
        <button type="submit" class="m-form-submit" id="mSchedCreateBtn">Create Schedule</button>
    </form>
</div>

<script>
(function() {
    var csrfToken = document.querySelector('#mSchedCreateForm [name="csrf_token"]')?.value || '';

    function showAlert(type, msg) {
        var el = document.getElementById('mSchedPageAlert');
        el.className = 'm-alert m-alert-' + type;
        el.textContent = msg;
        el.style.display = 'block';
        setTimeout(function() { el.style.display = 'none'; }, 4000);
    }

    window.mSchedFormOpen = function(data) {
        var sheet = document.getElementById('mSchedSheet');
        var overlay = document.getElementById('mSchedOverlay');
        var isEdit = !!data;
        document.getElementById('mSchedSheetTitle').textContent = isEdit ? 'Edit Schedule' : 'Create Schedule';
        document.getElementById('mSchedCreateAction').value = isEdit ? 'schedule_update' : 'schedule_create';
        document.getElementById('mSchedCreateId').value = isEdit ? data.id : '';
        document.getElementById('mSchedCreateName').value = isEdit ? (data.report_name || '') : '';
        document.getElementById('mSchedCreateType').value = isEdit ? (data.report_type || '') : '';
        document.getElementById('mSchedCreateFreq').value = isEdit ? (data.schedule_frequency || '') : '';
        document.getElementById('mSchedCreateTime').value = isEdit ? (data.schedule_time || '09:00') : '09:00';
        document.getElementById('mSchedCreateRecipients').value = isEdit ? (data.recipients || '') : '';
        document.getElementById('mSchedCreateFormat').value = isEdit ? (data.output_format || 'pdf') : 'pdf';
        document.getElementById('mSchedCreateBtn').textContent = isEdit ? 'Update Schedule' : 'Create Schedule';
        sheet.style.display = 'block';
        overlay.style.display = 'block';
    };

    window.mSchedFormClose = function() {
        document.getElementById('mSchedSheet').style.display = 'none';
        document.getElementById('mSchedOverlay').style.display = 'none';
    };

    window.mSchedFormSubmit = function(e) {
        e.preventDefault();
        var btn = document.getElementById('mSchedCreateBtn');
        btn.disabled = true;
        btn.textContent = 'Saving...';
        var fd = new FormData(document.getElementById('mSchedCreateForm'));
        fetch('process_reports.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showAlert('success', data.message || 'Schedule saved');
                    mSchedFormClose();
                    setTimeout(function() { window.location.reload(); }, 1000);
                } else { showAlert('error', data.message || 'Error saving schedule'); }
            })
            .catch(function() { showAlert('error', 'Network error'); })
            .finally(function() { btn.disabled = false; btn.textContent = 'Save'; });
        return false;
    };

    window.mSchedToggle = function(id, currentActive) {
        var fd = new FormData();
        fd.append('action', 'toggle_schedule');
        fd.append('schedule_id', id);
        fd.append('is_active', currentActive ? '0' : '1');
        fd.append('csrf_token', csrfToken);
        fetch('process_reports.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams(fd)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showAlert('success', 'Schedule ' + (currentActive ? 'paused' : 'resumed'));
                setTimeout(function() { window.location.reload(); }, 1000);
            } else { showAlert('error', data.message || 'Error updating schedule'); }
        })
        .catch(function() { showAlert('error', 'Network error'); });
    };

    window.mSchedDelete = function(id) {
        if (!confirm('Delete this schedule?')) return;
        fetch('process_reports.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: 'action=delete_schedule&schedule_id=' + id + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var el = document.getElementById('mSched-' + id);
                if (el) el.remove();
                showAlert('success', 'Schedule deleted');
            } else { showAlert('error', data.message || 'Error deleting'); }
        })
        .catch(function() { showAlert('error', 'Network error'); });
    };
})();
</script>
