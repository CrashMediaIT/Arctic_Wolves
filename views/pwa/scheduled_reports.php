<?php
/**
 * PWA Scheduled Reports - Mobile-native scheduled reports list
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
        SELECT id, name, report_name, report_type, frequency, schedule_frequency,
               next_run, status, is_active, recipients, email_recipients, parameters,
               schedule_day, schedule_time
        FROM report_schedules
        ORDER BY next_run ASC
        LIMIT 20
    ");
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, frequency, next_run, status
            FROM report_schedules
            ORDER BY next_run ASC
            LIMIT 20
        ");
        $stmt->execute();
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) { $schedules = []; }
}

$totalSchedules = count($schedules);

$srReportTypes = [
    'income_report' => 'Income Report',
    'athlete_report' => 'Athlete Report',
    'session_report' => 'Session Report',
    'attendance_report' => 'Attendance Report',
    'goal_progress_report' => 'Goal Progress Report',
    'evaluation_report' => 'Evaluation Report',
    'team_roster' => 'Team Roster',
    'financial_summary' => 'Financial Summary',
    'revenue_summary' => 'Revenue Summary',
    'expense_report' => 'Expense Report',
    'profit_loss' => 'Profit & Loss',
    'tax_summary' => 'Tax Report',
];
?>
<style>
.m-schedrpt { padding: 16px; font-family: Inter, sans-serif; padding-bottom: 80px; }
.m-schedrpt-header { margin-bottom: 16px; }
.m-schedrpt-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-schedrpt-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-schedrpt-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-schedrpt-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-schedrpt-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-schedrpt-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-schedrpt-badge-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-schedrpt-badge-paused { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-schedrpt-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-schedrpt-meta { font-size: 12px; color: #A8A8B8; display: flex; gap: 12px; flex-wrap: wrap; }
.m-schedrpt-meta i { font-size: 10px; }
.m-schedrpt-actions { display: flex; gap: 8px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #2D2D3F; }
.m-schedrpt-btn {
    flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 8px;
    color: #A8A8B8; padding: 8px; font-size: 12px; font-weight: 600;
    min-height: 36px; cursor: pointer;
}
.m-schedrpt-btn:active { border-color: #6B46C1; color: #fff; }
.m-schedrpt-fab {
    position: fixed; bottom: 60px; right: 20px; z-index: 999;
    width: 56px; height: 56px; border-radius: 50%; border: none;
    background: #6B46C1; color: #fff; font-size: 22px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(107,70,193,0.4); cursor: pointer;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-schedrpt">
    <div class="m-schedrpt-header">
        <h2 class="m-schedrpt-title">Scheduled Reports</h2>
        <p class="m-schedrpt-sub"><?= $totalSchedules ?> schedule<?= $totalSchedules !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($schedules)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clock"></i>
            <p>No scheduled reports configured</p>
        </div>
    <?php else: ?>
        <?php foreach ($schedules as $s):
            $sStatus = strtolower($s['status'] ?? ($s['is_active'] ?? 1 ? 'active' : 'paused'));
            $isActive = ($sStatus === 'active') || (!empty($s['is_active']) && $s['is_active'] == 1);
            $badgeClass = $isActive ? 'active' : 'paused';
            $freq = $s['frequency'] ?? $s['schedule_frequency'] ?? '';
            $rName = $s['report_name'] ?? $s['name'] ?? 'Unnamed';
            $rType = $s['report_type'] ?? '';
        ?>
        <div class="m-schedrpt-card">
            <div class="m-schedrpt-top">
                <span class="m-schedrpt-name"><?= htmlspecialchars($rName) ?></span>
                <span class="m-schedrpt-badge m-schedrpt-badge-<?= $badgeClass ?>"><?= $isActive ? 'Active' : 'Paused' ?></span>
            </div>
            <div class="m-schedrpt-meta">
                <?php if ($freq): ?>
                <span><i class="fas fa-sync"></i> <?= htmlspecialchars(ucfirst($freq)) ?></span>
                <?php endif; ?>
                <?php if (!empty($s['next_run'])): ?>
                <span><i class="fas fa-calendar"></i> Next: <?= date('M j, Y', strtotime($s['next_run'])) ?></span>
                <?php endif; ?>
                <?php if ($rType && isset($srReportTypes[$rType])): ?>
                <span><i class="fas fa-file-alt"></i> <?= htmlspecialchars($srReportTypes[$rType]) ?></span>
                <?php endif; ?>
            </div>
            <div class="m-schedrpt-actions">
                <button class="m-schedrpt-btn" onclick="mSrEdit(<?= htmlspecialchars(json_encode($s)) ?>)">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="m-schedrpt-btn" onclick="mSrToggle(<?= (int)$s['id'] ?>, <?= $isActive ? 'false' : 'true' ?>)">
                    <i class="fas fa-<?= $isActive ? 'pause' : 'play' ?>"></i> <?= $isActive ? 'Pause' : 'Enable' ?>
                </button>
                <button class="m-schedrpt-btn" onclick="mSrDelete(<?= (int)$s['id'] ?>, <?= htmlspecialchars(json_encode($rName)) ?>)" style="color:#EF4444;">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- FAB -->
<button class="m-schedrpt-fab" onclick="mSrOpenCreate()"><i class="fas fa-plus"></i></button>

<!-- Bottom Sheet Overlay -->
<div id="mSrOverlay" onclick="mSrCloseSheet()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1000;"></div>
<!-- Bottom Sheet -->
<div id="mSrSheet" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:1001;background:#16161F;border-radius:16px 16px 0 0;max-height:85vh;overflow-y:auto;padding:20px 16px 32px;font-family:Inter,sans-serif;">
    <div style="width:36px;height:4px;background:#2D2D3F;border-radius:2px;margin:0 auto 16px;"></div>
    <h3 style="font-size:17px;font-weight:700;color:#fff;margin:0 0 16px;" id="mSrSheetTitle">New Scheduled Report</h3>
    <form id="mSrForm" method="POST">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" id="mSrAction" value="schedule_create">
        <input type="hidden" name="schedule_id" id="mSrId" value="">

        <label style="font-size:12px;color:#A8A8B8;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:6px;">Report Type</label>
        <select name="report_type" id="mSrType" required style="width:100%;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:12px;min-height:44px;font-size:14px;margin-bottom:12px;-webkit-appearance:none;">
            <option value="">Select type...</option>
            <?php foreach ($srReportTypes as $k => $v): ?>
            <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
            <?php endforeach; ?>
        </select>

        <label style="font-size:12px;color:#A8A8B8;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:6px;">Frequency</label>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px;">
            <div class="m-sr-freq" data-val="daily" onclick="mSrSetFreq(this)" style="text-align:center;background:#0A0A0F;border:2px solid #2D2D3F;border-radius:10px;padding:10px;cursor:pointer;color:#fff;font-size:13px;font-weight:600;min-height:44px;display:flex;align-items:center;justify-content:center;gap:4px;">
                <i class="fas fa-calendar-day"></i> Daily
            </div>
            <div class="m-sr-freq" data-val="weekly" onclick="mSrSetFreq(this)" style="text-align:center;background:#0A0A0F;border:2px solid #2D2D3F;border-radius:10px;padding:10px;cursor:pointer;color:#fff;font-size:13px;font-weight:600;min-height:44px;display:flex;align-items:center;justify-content:center;gap:4px;">
                <i class="fas fa-calendar-week"></i> Weekly
            </div>
            <div class="m-sr-freq" data-val="monthly" onclick="mSrSetFreq(this)" style="text-align:center;background:#0A0A0F;border:2px solid #2D2D3F;border-radius:10px;padding:10px;cursor:pointer;color:#fff;font-size:13px;font-weight:600;min-height:44px;display:flex;align-items:center;justify-content:center;gap:4px;">
                <i class="fas fa-calendar-alt"></i> Monthly
            </div>
        </div>
        <input type="hidden" name="frequency" id="mSrFreq" required value="">

        <label style="font-size:12px;color:#A8A8B8;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:6px;">Format</label>
        <select name="format" id="mSrFormat" required style="width:100%;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:12px;min-height:44px;font-size:14px;margin-bottom:12px;-webkit-appearance:none;">
            <option value="">Select format...</option>
            <option value="pdf">PDF</option>
            <option value="csv">CSV</option>
        </select>

        <label style="font-size:12px;color:#A8A8B8;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:6px;">Email Recipients</label>
        <input type="text" name="email_recipients" id="mSrEmail" required placeholder="email@example.com" style="width:100%;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:12px;min-height:44px;font-size:14px;margin-bottom:4px;box-sizing:border-box;">
        <p style="font-size:11px;color:#6B6B7B;margin:0 0 12px;">Comma-separated emails</p>

        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
            <input type="checkbox" name="is_active" id="mSrActive" value="1" checked style="width:20px;height:20px;accent-color:#6B46C1;">
            <label for="mSrActive" style="font-size:14px;color:#fff;">Active</label>
        </div>

        <button type="submit" id="mSrSubmitBtn" style="width:100%;background:#6B46C1;color:#fff;border:none;border-radius:10px;padding:14px;font-size:15px;font-weight:600;min-height:44px;cursor:pointer;">
            <i class="fas fa-check"></i> Create Schedule
        </button>
    </form>
</div>

<script>
function mSrSetFreq(el) {
    document.querySelectorAll('.m-sr-freq').forEach(function(e){ e.style.borderColor = '#2D2D3F'; });
    el.style.borderColor = '#6B46C1';
    document.getElementById('mSrFreq').value = el.getAttribute('data-val');
}

function mSrOpenCreate() {
    document.getElementById('mSrSheetTitle').textContent = 'New Scheduled Report';
    document.getElementById('mSrAction').value = 'schedule_create';
    document.getElementById('mSrSubmitBtn').innerHTML = '<i class="fas fa-check"></i> Create Schedule';
    document.getElementById('mSrId').value = '';
    document.getElementById('mSrType').value = '';
    document.getElementById('mSrFormat').value = '';
    document.getElementById('mSrEmail').value = '';
    document.getElementById('mSrFreq').value = '';
    document.getElementById('mSrActive').checked = true;
    document.querySelectorAll('.m-sr-freq').forEach(function(e){ e.style.borderColor = '#2D2D3F'; });
    document.getElementById('mSrOverlay').style.display = 'block';
    document.getElementById('mSrSheet').style.display = 'block';
}

function mSrEdit(s) {
    document.getElementById('mSrSheetTitle').textContent = 'Edit Schedule';
    document.getElementById('mSrAction').value = 'schedule_update';
    document.getElementById('mSrSubmitBtn').innerHTML = '<i class="fas fa-check"></i> Update Schedule';
    document.getElementById('mSrId').value = s.id;
    document.getElementById('mSrType').value = s.report_type || '';
    document.getElementById('mSrFormat').value = s.format || '';
    document.getElementById('mSrEmail').value = s.email_recipients || s.recipients || '';
    var freq = s.frequency || s.schedule_frequency || '';
    document.getElementById('mSrFreq').value = freq;
    document.getElementById('mSrActive').checked = (s.is_active == 1) || (s.status === 'active');
    document.querySelectorAll('.m-sr-freq').forEach(function(e){
        e.style.borderColor = e.getAttribute('data-val') === freq ? '#6B46C1' : '#2D2D3F';
    });
    document.getElementById('mSrOverlay').style.display = 'block';
    document.getElementById('mSrSheet').style.display = 'block';
}

function mSrCloseSheet() {
    document.getElementById('mSrOverlay').style.display = 'none';
    document.getElementById('mSrSheet').style.display = 'none';
}

function mSrToggle(id, activate) {
    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('#mSrForm input[name="csrf_token"]').value);
    fd.append('action', 'schedule_toggle');
    fd.append('schedule_id', id);
    fd.append('is_active', activate ? '1' : '0');
    fetch('process_reports.php', {method:'POST', body:fd})
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) { persistToast(d.message || 'Operation completed successfully', 'success'); location.reload(); }
            else { alert(d.message || 'Error'); }
        })
        .catch(function(){ alert('An error occurred'); });
}

function mSrDelete(id, name) {
    if (!confirm('Delete "' + name + '"? This cannot be undone.')) return;
    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('#mSrForm input[name="csrf_token"]').value);
    fd.append('action', 'schedule_delete');
    fd.append('schedule_id', id);
    fetch('process_reports.php', {method:'POST', body:fd})
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) { persistToast(d.message || 'Operation completed successfully', 'success'); location.reload(); }
            else { alert(d.message || 'Error'); }
        })
        .catch(function(){ alert('An error occurred'); });
}

document.getElementById('mSrForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    var fd = new FormData(this);
    try {
        var r = await fetch('process_reports.php', {method:'POST', body:fd});
        var d = await r.json();
        if (d.success) { mSrCloseSheet(); persistToast(d.message || 'Operation completed successfully', 'success'); location.reload(); }
        else { alert(d.message || 'Error saving schedule'); }
    } catch(err) { alert('An error occurred'); }
});
</script>
