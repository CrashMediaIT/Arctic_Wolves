<?php
/**
 * PWA Admin Staff Scheduling - Mobile-native staff schedule management
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$canAccessHR) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$allSchedules = [];
$staffMembers = [];
try {
    $scheduleStmt = $pdo->query("
        SELECT ss.id, ss.staff_id, ss.schedule_date, ss.start_time, ss.end_time,
               ss.lunch_break_minutes, ss.location, ss.notes,
               u.first_name, u.last_name
        FROM staff_schedules ss
        JOIN users u ON ss.staff_id = u.id
        WHERE ss.schedule_date >= CURDATE()
        ORDER BY ss.schedule_date ASC, ss.start_time ASC
        LIMIT 50
    ");
    $allSchedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
    $allSchedules = decryptUserRows($allSchedules);

    $staffStmt = $pdo->query("
        SELECT u.*,
               (SELECT COUNT(*) FROM staff_schedules ss WHERE ss.staff_id = u.id AND ss.schedule_date >= CURDATE()) as upcoming_shifts
        FROM users u
        WHERE u.role = 'front_desk_staff' AND u.is_active = 1
        ORDER BY u.first_name, u.last_name
    ");
    $staffMembers = $staffStmt->fetchAll(PDO::FETCH_ASSOC);
    $staffMembers = decryptUserRows($staffMembers);
} catch (PDOException $e) { $allSchedules = []; $staffMembers = []; }
?>
<style>
.m-staffsched { padding: 16px; font-family: Inter, sans-serif; padding-bottom: 80px; }
.m-staffsched-header { margin-bottom: 16px; }
.m-staffsched-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-staffsched-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-staffsched-card {
    display: flex; align-items: flex-start; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-staffsched-date {
    min-width: 50px; text-align: center;
    background: rgba(107,70,193,0.15); border-radius: 10px;
    padding: 8px 6px; flex-shrink: 0;
}
.m-staffsched-date-month { font-size: 10px; color: #8B5CF6; text-transform: uppercase; font-weight: 600; display: block; }
.m-staffsched-date-day { font-size: 18px; color: #fff; font-weight: 700; display: block; line-height: 1.1; }
.m-staffsched-body { flex: 1; min-width: 0; }
.m-staffsched-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-staffsched-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-staffsched-meta i { font-size: 10px; }
.m-staffsched-location {
    font-size: 11px; color: #8B5CF6; margin-top: 3px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.m-staffsched-actions { display: flex; gap: 6px; flex-shrink: 0; }
.m-staffsched-action-btn {
    width: 44px; height: 44px; border-radius: 10px; border: none;
    background: rgba(255,255,255,0.05); color: #A8A8B8;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 14px;
    -webkit-tap-highlight-color: transparent;
}
.m-staffsched-action-btn:active { background: rgba(107,70,193,0.2); color: #8B5CF6; }
.m-staffsched-action-btn.m-delete-btn:active { background: rgba(239,68,68,0.2); color: #EF4444; }

.m-staffsched-fab {
    position: fixed; bottom: 60px; right: 20px; z-index: 1000;
    width: 56px; height: 56px; border-radius: 50%; border: none;
    background: #6B46C1; color: #fff; font-size: 24px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(107,70,193,0.4);
    cursor: pointer; -webkit-tap-highlight-color: transparent;
}
.m-staffsched-fab:active { transform: scale(0.93); }

.m-staffsched-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    z-index: 1003; -webkit-tap-highlight-color: transparent;
}
.m-staffsched-overlay.m-active { display: block; }
.m-staffsched-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1004;
    background: #16161F; border-radius: 16px 16px 0 0;
    border-top: 1px solid #2D2D3F; max-height: 85vh;
    overflow-y: auto; -webkit-overflow-scrolling: touch;
    transform: translateY(100%); transition: transform 0.3s ease;
}
.m-staffsched-sheet.m-active { transform: translateY(0); }
.m-staffsched-sheet-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px; border-bottom: 1px solid #2D2D3F;
}
.m-staffsched-sheet-title { font-size: 16px; font-weight: 700; color: #fff; margin: 0; }
.m-staffsched-sheet-close {
    width: 44px; height: 44px; border-radius: 10px; border: none;
    background: rgba(255,255,255,0.05); color: #A8A8B8;
    font-size: 20px; cursor: pointer; display: flex;
    align-items: center; justify-content: center;
    -webkit-tap-highlight-color: transparent;
}
.m-staffsched-sheet-body { padding: 16px; }
.m-staffsched-form-group { margin-bottom: 14px; }
.m-staffsched-form-label { display: block; font-size: 12px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; }
.m-staffsched-form-input,
.m-staffsched-form-select,
.m-staffsched-form-textarea {
    width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #2D2D3F;
    background: #0A0A0F; color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    min-height: 44px; box-sizing: border-box;
    -webkit-appearance: none; appearance: none;
}
.m-staffsched-form-select { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23A8A8B8' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px; }
.m-staffsched-form-textarea { min-height: 70px; resize: vertical; }
.m-staffsched-form-input:focus,
.m-staffsched-form-select:focus,
.m-staffsched-form-textarea:focus { outline: none; border-color: #6B46C1; }
.m-staffsched-form-row { display: flex; gap: 10px; }
.m-staffsched-form-row .m-staffsched-form-group { flex: 1; }
.m-staffsched-submit {
    width: 100%; padding: 14px; border-radius: 10px; border: none;
    background: #6B46C1; color: #fff; font-size: 15px; font-weight: 600;
    cursor: pointer; min-height: 44px; margin-top: 4px;
    -webkit-tap-highlight-color: transparent;
}
.m-staffsched-submit:active { background: #5a3aab; }
.m-staffsched-submit:disabled { opacity: 0.6; }

.m-staffsched-toast {
    position: fixed; top: 20px; left: 16px; right: 16px;
    padding: 14px 16px; border-radius: 10px; color: #fff;
    font-size: 13px; font-weight: 500; z-index: 2000;
    display: flex; align-items: center; gap: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    animation: m-staffsched-slidein 0.3s ease;
}
@keyframes m-staffsched-slidein { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-staffsched">
    <div class="m-staffsched-header">
        <h2 class="m-staffsched-title">Staff Scheduling</h2>
        <p class="m-staffsched-sub"><?= count($allSchedules) ?> upcoming shift<?= count($allSchedules) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($allSchedules)): ?>
        <div class="m-empty-state">
            <i class="fas fa-calendar-alt"></i>
            No upcoming shifts scheduled
        </div>
    <?php else: ?>
        <?php foreach ($allSchedules as $sched):
            $dateObj = strtotime($sched['schedule_date']);
            $startTime = $sched['start_time'] ? date('g:i A', strtotime($sched['start_time'])) : '--';
            $endTime = $sched['end_time'] ? date('g:i A', strtotime($sched['end_time'])) : '--';
            $staffName = htmlspecialchars(trim(($sched['first_name'] ?? '') . ' ' . ($sched['last_name'] ?? '')) ?: 'Unassigned');
            $lunchMin = (int)($sched['lunch_break_minutes'] ?? 0);
            $location = htmlspecialchars($sched['location'] ?? '');
            $notes = htmlspecialchars($sched['notes'] ?? '');
        ?>
        <div class="m-staffsched-card"
             data-id="<?= (int)$sched['id'] ?>"
             data-staff-id="<?= (int)$sched['staff_id'] ?>"
             data-date="<?= htmlspecialchars($sched['schedule_date']) ?>"
             data-start="<?= htmlspecialchars($sched['start_time'] ?? '') ?>"
             data-end="<?= htmlspecialchars($sched['end_time'] ?? '') ?>"
             data-lunch="<?= $lunchMin ?>"
             data-location="<?= $location ?>"
             data-notes="<?= $notes ?>">
            <div class="m-staffsched-date">
                <span class="m-staffsched-date-month"><?= date('M', $dateObj) ?></span>
                <span class="m-staffsched-date-day"><?= date('j', $dateObj) ?></span>
            </div>
            <div class="m-staffsched-body">
                <div class="m-staffsched-name"><?= $staffName ?></div>
                <div class="m-staffsched-meta">
                    <i class="fas fa-clock"></i> <?= $startTime ?> — <?= $endTime ?>
                    <?php if ($lunchMin > 0): ?>
                        &middot; <i class="fas fa-utensils"></i> <?= $lunchMin ?>min
                    <?php endif; ?>
                </div>
                <?php if ($location): ?>
                    <div class="m-staffsched-location"><i class="fas fa-map-marker-alt"></i> <?= $location ?></div>
                <?php endif; ?>
            </div>
            <div class="m-staffsched-actions">
                <button type="button" class="m-staffsched-action-btn m-staffsched-edit-btn" aria-label="Edit schedule"><i class="fas fa-pencil-alt"></i></button>
                <button type="button" class="m-staffsched-action-btn m-delete-btn m-staffsched-delete-btn" aria-label="Delete schedule"><i class="fas fa-trash"></i></button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button type="button" class="m-staffsched-fab" id="m-staffsched-fab" aria-label="Add schedule"><i class="fas fa-plus"></i></button>

<div class="m-staffsched-overlay" id="m-staffsched-overlay"></div>
<div class="m-staffsched-sheet" id="m-staffsched-sheet">
    <div class="m-staffsched-sheet-header">
        <h3 class="m-staffsched-sheet-title" id="m-staffsched-sheet-title">New Schedule</h3>
        <button type="button" class="m-staffsched-sheet-close" id="m-staffsched-sheet-close" aria-label="Close">&times;</button>
    </div>
    <div class="m-staffsched-sheet-body">
        <form id="m-staffsched-form" autocomplete="off">
            <?= csrfTokenInput() ?>
            <input type="hidden" id="m-staffsched-id" name="schedule_id" value="">

            <div class="m-staffsched-form-group">
                <label class="m-staffsched-form-label" for="m-staffsched-staff">Staff Member</label>
                <select class="m-staffsched-form-select" id="m-staffsched-staff" name="staff_id" required>
                    <option value="">Select staff…</option>
                    <?php foreach ($staffMembers as $sm): ?>
                        <option value="<?= (int)$sm['id'] ?>"><?= htmlspecialchars(trim(($sm['first_name'] ?? '') . ' ' . ($sm['last_name'] ?? ''))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="m-staffsched-form-group">
                <label class="m-staffsched-form-label" for="m-staffsched-date-input">Date</label>
                <input type="date" class="m-staffsched-form-input" id="m-staffsched-date-input" name="schedule_date" required min="<?= date('Y-m-d') ?>">
            </div>

            <div class="m-staffsched-form-row">
                <div class="m-staffsched-form-group">
                    <label class="m-staffsched-form-label" for="m-staffsched-start">Start Time</label>
                    <input type="time" class="m-staffsched-form-input" id="m-staffsched-start" name="start_time" required>
                </div>
                <div class="m-staffsched-form-group">
                    <label class="m-staffsched-form-label" for="m-staffsched-end">End Time</label>
                    <input type="time" class="m-staffsched-form-input" id="m-staffsched-end" name="end_time" required>
                </div>
            </div>

            <div class="m-staffsched-form-group">
                <label class="m-staffsched-form-label" for="m-staffsched-lunch">Lunch Break</label>
                <select class="m-staffsched-form-select" id="m-staffsched-lunch" name="lunch_break_minutes">
                    <option value="0">No break</option>
                    <option value="15">15 minutes</option>
                    <option value="30" selected>30 minutes</option>
                    <option value="45">45 minutes</option>
                    <option value="60">60 minutes</option>
                    <option value="90">90 minutes</option>
                </select>
            </div>

            <div class="m-staffsched-form-group">
                <label class="m-staffsched-form-label" for="m-staffsched-location">Location</label>
                <input type="text" class="m-staffsched-form-input" id="m-staffsched-location" name="location" placeholder="e.g. Main Rink, Front Desk">
            </div>

            <div class="m-staffsched-form-group">
                <label class="m-staffsched-form-label" for="m-staffsched-notes">Notes</label>
                <textarea class="m-staffsched-form-textarea" id="m-staffsched-notes" name="notes" placeholder="Optional notes…" rows="2"></textarea>
            </div>

            <button type="submit" class="m-staffsched-submit" id="m-staffsched-submit-btn">Create Schedule</button>
        </form>
    </div>
</div>

<script>
(function() {
    var overlay = document.getElementById('m-staffsched-overlay');
    var sheet = document.getElementById('m-staffsched-sheet');
    var sheetTitle = document.getElementById('m-staffsched-sheet-title');
    var closeBtn = document.getElementById('m-staffsched-sheet-close');
    var fab = document.getElementById('m-staffsched-fab');
    var form = document.getElementById('m-staffsched-form');
    var submitBtn = document.getElementById('m-staffsched-submit-btn');
    var schedIdField = document.getElementById('m-staffsched-id');
    var csrfToken = form.querySelector('[name="csrf_token"]').value;

    function toast(msg, type) {
        var old = document.querySelector('.m-staffsched-toast');
        if (old) old.remove();
        var d = document.createElement('div');
        d.className = 'm-staffsched-toast';
        d.style.background = type === 'success' ? 'rgba(16,185,129,0.95)' : 'rgba(239,68,68,0.95)';
        var icon = document.createElement('i');
        icon.className = 'fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle');
        d.appendChild(icon);
        var span = document.createElement('span');
        span.textContent = msg;
        d.appendChild(span);
        document.body.appendChild(d);
        setTimeout(function() { if (d.parentElement) d.remove(); }, 4000);
    }

    function openSheet() { overlay.classList.add('m-active'); sheet.classList.add('m-active'); }
    function closeSheet() { overlay.classList.remove('m-active'); sheet.classList.remove('m-active'); }

    function resetForm() {
        form.reset();
        schedIdField.value = '';
        sheetTitle.textContent = 'New Schedule';
        submitBtn.textContent = 'Create Schedule';
        document.getElementById('m-staffsched-lunch').value = '30';
    }

    fab.addEventListener('click', function() { resetForm(); openSheet(); });
    closeBtn.addEventListener('click', closeSheet);
    overlay.addEventListener('click', closeSheet);

    // Edit buttons
    document.querySelectorAll('.m-staffsched-edit-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var card = btn.closest('.m-staffsched-card');
            sheetTitle.textContent = 'Edit Schedule';
            submitBtn.textContent = 'Update Schedule';
            schedIdField.value = card.dataset.id;
            document.getElementById('m-staffsched-staff').value = card.dataset.staffId;
            document.getElementById('m-staffsched-date-input').value = card.dataset.date;
            document.getElementById('m-staffsched-start').value = card.dataset.start;
            document.getElementById('m-staffsched-end').value = card.dataset.end;
            document.getElementById('m-staffsched-lunch').value = card.dataset.lunch;
            document.getElementById('m-staffsched-location').value = card.dataset.location;
            document.getElementById('m-staffsched-notes').value = card.dataset.notes;
            openSheet();
        });
    });

    // Delete buttons
    document.querySelectorAll('.m-staffsched-delete-btn').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            var card = btn.closest('.m-staffsched-card');
            if (!await showConfirmModal('Delete this schedule?')) return;
            fetch('process_time_tracking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ action: 'delete_schedule', schedule_id: parseInt(card.dataset.id), csrf_token: csrfToken })
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                toast(d.message || (d.success ? 'Schedule deleted' : 'Delete failed'), d.success ? 'success' : 'error');
                if (d.success) { persistToast(d.message || 'Operation completed successfully', 'success'); location.reload(); }
            })
            .catch(function() { toast('An error occurred', 'error'); });
        });
    });

    // Form submit (create or update)
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var isEdit = !!schedIdField.value;
        var payload = {
            action: isEdit ? 'update_schedule' : 'create_schedule',
            staff_id: parseInt(document.getElementById('m-staffsched-staff').value),
            schedule_date: document.getElementById('m-staffsched-date-input').value,
            start_time: document.getElementById('m-staffsched-start').value,
            end_time: document.getElementById('m-staffsched-end').value,
            lunch_break_minutes: parseInt(document.getElementById('m-staffsched-lunch').value),
            location: document.getElementById('m-staffsched-location').value,
            notes: document.getElementById('m-staffsched-notes').value,
            csrf_token: csrfToken
        };
        if (isEdit) payload.schedule_id = parseInt(schedIdField.value);
        submitBtn.disabled = true;
        fetch('process_time_tracking.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            submitBtn.disabled = false;
            toast(d.message || (d.success ? (isEdit ? 'Schedule updated' : 'Schedule created') : 'Operation failed'), d.success ? 'success' : 'error');
            if (d.success) { closeSheet(); persistToast(d.message || 'Operation completed successfully', 'success'); location.reload(); }
        })
        .catch(function() { submitBtn.disabled = false; toast('An error occurred', 'error'); });
    });
})();
</script>
