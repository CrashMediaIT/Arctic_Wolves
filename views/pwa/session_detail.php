<?php
/**
 * PWA Session Detail - Mobile-native single session view
 * Purpose-built for mobile phones.
 */

$sessionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$session = null;
$bookingStatus = null;
$bookingId = null;

if ($sessionId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, u.first_name as coach_first, u.last_name as coach_last
            FROM sessions s
            LEFT JOIN users u ON u.id = s.coach_id
            WHERE s.id = ?
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($session) {
            $session['coach_first'] = FieldEncryption::decrypt($session['coach_first'] ?? '');
            $session['coach_last'] = FieldEncryption::decrypt($session['coach_last'] ?? '');
        }
    } catch (PDOException $e) { $session = null; }

    // Check if user has a booking
    if ($session && !$isAnyCoach) {
        try {
            $stmt = $pdo->prepare("SELECT id, status FROM bookings WHERE session_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$sessionId, $user_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($booking) {
                $bookingId = (int)$booking['id'];
                $bookingStatus = $booking['status'];
            }
        } catch (PDOException $e) { /* no booking */ }
    }

    // Coach: load practice plan, evaluation, and plan options
    $sdPracticePlan = null;
    $sdEvaluation = null;
    $sdPracticePlans = [];
    if ($session && $isAnyCoach) {
        try {
            $pp_stmt = $pdo->prepare("SELECT pp.id, pp.name FROM session_practice_plans spp JOIN practice_plans pp ON spp.practice_plan_id = pp.id WHERE spp.session_id = ? LIMIT 1");
            $pp_stmt->execute([$sessionId]);
            $sdPracticePlan = $pp_stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}
        try {
            $ev_stmt = $pdo->prepare("SELECT id, name, status FROM session_evaluations WHERE session_id = ? LIMIT 1");
            $ev_stmt->execute([$sessionId]);
            $sdEvaluation = $ev_stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}
        try {
            $sdPracticePlans = $pdo->query("SELECT id, COALESCE(title, name) as name FROM practice_plans ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}
    }
}
?>
<style>
.m-session-detail { padding: 16px; font-family: Inter, sans-serif; }
.m-back-link {
    display: inline-flex; align-items: center; gap: 6px;
    color: #8B5CF6; font-size: 13px; font-weight: 500;
    text-decoration: none; margin-bottom: 16px;
    min-height: 44px; padding: 8px 0;
}
.m-sd-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 16px;
    padding: 20px; margin-bottom: 16px;
}
.m-sd-title { font-size: 18px; font-weight: 700; color: #fff; margin: 0 0 4px; }
.m-sd-status {
    display: inline-block; font-size: 11px; padding: 3px 10px; border-radius: 6px;
    font-weight: 600; margin-bottom: 16px;
}
.m-sd-status-scheduled { background: rgba(16,185,129,0.15); color: #10B981; }
.m-sd-status-completed { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-sd-status-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-sd-status-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-sd-field {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 0; border-bottom: 1px solid #2D2D3F;
    min-height: 44px;
}
.m-sd-field:last-child { border-bottom: none; }
.m-sd-field-icon { width: 20px; text-align: center; color: #6B6B7B; font-size: 14px; flex-shrink: 0; }
.m-sd-field-body { flex: 1; }
.m-sd-field-label { font-size: 11px; color: #6B6B7B; }
.m-sd-field-value { font-size: 14px; color: #fff; font-weight: 500; margin-top: 1px; }
.m-sd-actions { display: flex; flex-direction: column; gap: 8px; margin-top: 16px; }
.m-sd-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    padding: 14px; border-radius: 12px;
    font-size: 14px; font-weight: 600; text-decoration: none;
    min-height: 44px; border: none; cursor: pointer;
    font-family: Inter, sans-serif; text-align: center;
}
.m-sd-btn-book { background: #6B46C1; color: #fff; }
.m-sd-btn-cancel { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-sd-btn-booked { background: rgba(16,185,129,0.15); color: #10B981; }
.m-sd-booking-badge {
    text-align: center; padding: 12px; border-radius: 10px;
    font-size: 13px; font-weight: 600; margin-bottom: 8px;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
/* Coach action section */
.m-sd-coach-section {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 16px;
    padding: 16px; margin-bottom: 16px;
}
.m-sd-coach-section h3 { font-size: 14px; font-weight: 700; color: #8B5CF6; margin: 0 0 12px; }
.m-sd-plan-info {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; padding: 10px 12px; border-radius: 10px;
    margin-bottom: 10px;
}
.m-sd-plan-info.has-plan { background: rgba(16,185,129,0.1); color: #10B981; }
.m-sd-plan-info.no-plan { background: rgba(251,191,36,0.1); color: #FBBF24; }
.m-sd-eval-info {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; padding: 10px 12px; border-radius: 10px;
    background: rgba(59,130,246,0.1); color: #3B82F6; margin-bottom: 10px;
}
.m-sd-coach-actions {
    display: flex; flex-wrap: wrap; gap: 8px;
}
.m-sd-coach-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 10px 14px; border-radius: 10px; font-size: 13px; font-weight: 600;
    border: none; cursor: pointer; font-family: Inter, sans-serif;
    min-height: 44px; text-decoration: none;
    background: rgba(107,70,193,0.12); color: #8B5CF6;
    -webkit-tap-highlight-color: transparent; flex: 1; min-width: 120px;
}
.m-sd-coach-btn:active { background: rgba(107,70,193,0.2); }
/* Bottom sheet for assign plan from session detail */
.m-sd-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1001; }
.m-sd-overlay.m-active { display: block; }
.m-sd-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1002;
    background: #1A1A2E; border-radius: 16px 16px 0 0;
    padding: 20px 16px 32px; transform: translateY(100%); transition: transform .3s ease;
}
.m-sd-overlay.m-active .m-sd-sheet { transform: translateY(0); }
.m-sd-sheet-handle { width: 36px; height: 4px; background: #3D3D4F; border-radius: 2px; margin: 0 auto 16px; }
.m-sd-sheet-title { font-size: 16px; font-weight: 700; color: #fff; margin: 0 0 16px; text-align: center; }
.m-sd-sheet-field { margin-bottom: 14px; }
.m-sd-sheet-field label { display: block; font-size: 12px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; text-transform: uppercase; }
.m-sd-sheet-field select {
    width: 100%; padding: 12px; background: #16161F; border: 1px solid #2D2D3F;
    border-radius: 10px; color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    min-height: 44px; appearance: none; -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236B6B7B' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 12px center;
}
.m-sd-sheet-actions { display: flex; gap: 10px; margin-top: 16px; }
.m-sd-sheet-actions button { flex: 1; padding: 14px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; font-family: Inter, sans-serif; min-height: 48px; }
.m-sd-sheet-cancel { background: #2D2D3F; color: #A8A8B8; }
.m-sd-sheet-save { background: #6B46C1; color: #fff; }
.m-sd-sheet-save:disabled { opacity: 0.5; }
</style>

<div class="m-session-detail">
    <a href="?page=sessions" class="m-back-link">
        <i class="fas fa-chevron-left"></i> Back to Sessions
    </a>

    <?php if (!$session): ?>
        <div class="m-empty-state">
            <i class="fas fa-calendar-xmark"></i>
            <p>Session not found</p>
        </div>
    <?php else:
        $status = strtolower($session['status'] ?? 'scheduled');
        $statusClass = match($status) {
            'scheduled' => 'scheduled',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => 'default',
        };
        $sDate = $session['session_date'] ? date('l, F j, Y', strtotime($session['session_date'])) : 'TBD';
        $sTime = $session['session_time'] ? date('g:i A', strtotime($session['session_time'])) : 'TBD';
        $coachName = trim(($session['coach_first'] ?? '') . ' ' . ($session['coach_last'] ?? ''));
    ?>
        <div class="m-sd-card">
            <h2 class="m-sd-title"><?= htmlspecialchars($session['title'] ?? 'Untitled Session') ?></h2>
            <span class="m-sd-status m-sd-status-<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>

            <div class="m-sd-field">
                <span class="m-sd-field-icon"><i class="fas fa-calendar"></i></span>
                <div class="m-sd-field-body">
                    <div class="m-sd-field-label">Date</div>
                    <div class="m-sd-field-value"><?= htmlspecialchars($sDate) ?></div>
                </div>
            </div>
            <div class="m-sd-field">
                <span class="m-sd-field-icon"><i class="fas fa-clock"></i></span>
                <div class="m-sd-field-body">
                    <div class="m-sd-field-label">Time</div>
                    <div class="m-sd-field-value"><?= htmlspecialchars($sTime) ?></div>
                </div>
            </div>
            <?php if (!empty($session['duration_minutes'])): ?>
            <div class="m-sd-field">
                <span class="m-sd-field-icon"><i class="fas fa-hourglass-half"></i></span>
                <div class="m-sd-field-body">
                    <div class="m-sd-field-label">Duration</div>
                    <div class="m-sd-field-value"><?= (int)$session['duration_minutes'] ?> minutes</div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($session['arena'])): ?>
            <div class="m-sd-field">
                <span class="m-sd-field-icon"><i class="fas fa-location-dot"></i></span>
                <div class="m-sd-field-body">
                    <div class="m-sd-field-label">Arena</div>
                    <div class="m-sd-field-value"><?= htmlspecialchars($session['arena']) ?></div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($coachName): ?>
            <div class="m-sd-field">
                <span class="m-sd-field-icon"><i class="fas fa-user-tie"></i></span>
                <div class="m-sd-field-body">
                    <div class="m-sd-field-label">Coach</div>
                    <div class="m-sd-field-value"><?= htmlspecialchars($coachName) ?></div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($session['session_type'])): ?>
            <div class="m-sd-field">
                <span class="m-sd-field-icon"><i class="fas fa-tag"></i></span>
                <div class="m-sd-field-body">
                    <div class="m-sd-field-label">Type</div>
                    <div class="m-sd-field-value"><?= htmlspecialchars($session['session_type']) ?></div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (isset($session['price']) && $session['price'] > 0): ?>
            <div class="m-sd-field">
                <span class="m-sd-field-icon"><i class="fas fa-dollar-sign"></i></span>
                <div class="m-sd-field-body">
                    <div class="m-sd-field-label">Price</div>
                    <div class="m-sd-field-value">$<?= number_format((float)$session['price'], 2) ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Booking actions for athletes -->
        <?php if (!$isAnyCoach && $status === 'scheduled'): ?>
            <div class="m-sd-actions">
                <?php if ($bookingStatus === 'confirmed'): ?>
                    <div class="m-sd-booking-badge" style="background:rgba(16,185,129,0.15);color:#10B981;">
                        <i class="fas fa-check-circle"></i> You're booked for this session
                    </div>
                    <a href="?page=cancel_booking&booking_id=<?= $bookingId ?>" class="m-sd-btn m-sd-btn-cancel">
                        <i class="fas fa-times"></i> Cancel Booking
                    </a>
                <?php else: ?>
                    <a href="?page=book_session&session_id=<?= $sessionId ?>" class="m-sd-btn m-sd-btn-book">
                        <i class="fas fa-calendar-plus"></i> Book This Session
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Coach action section -->
        <?php if ($isAnyCoach && $session): ?>
        <div class="m-sd-coach-section">
            <h3><i class="fas fa-chalkboard-user"></i> Coach Actions</h3>

            <?php if ($sdPracticePlan): ?>
            <div class="m-sd-plan-info has-plan"><i class="fas fa-clipboard-list"></i> Plan: <?= htmlspecialchars($sdPracticePlan['name']) ?></div>
            <?php else: ?>
            <div class="m-sd-plan-info no-plan"><i class="fas fa-exclamation-circle"></i> No practice plan assigned</div>
            <?php endif; ?>

            <?php if ($sdEvaluation): ?>
            <div class="m-sd-eval-info"><i class="fas fa-clipboard-check"></i> <?= htmlspecialchars($sdEvaluation['name'] ?: 'Evaluation') ?> (<?= ucfirst($sdEvaluation['status'] ?? 'draft') ?>)</div>
            <?php endif; ?>

            <div class="m-sd-coach-actions">
                <button type="button" class="m-sd-coach-btn" onclick="mSdOpenAssignPlan()">
                    <i class="fas fa-clipboard-list"></i> <?= $sdPracticePlan ? 'Change Plan' : 'Add Plan' ?>
                </button>
                <?php if ($sdEvaluation): ?>
                <a href="?page=session_evaluation_form&evaluation_id=<?= (int)$sdEvaluation['id'] ?>" class="m-sd-coach-btn" style="text-decoration:none;">
                    <i class="fas fa-clipboard-check"></i> Continue Evaluation
                </a>
                <?php else: ?>
                <a href="?page=coach_calendar" class="m-sd-coach-btn" style="text-decoration:none;">
                    <i class="fas fa-clipboard-check"></i> Start Evaluation
                </a>
                <?php endif; ?>
                <a href="?page=record_drill_video&session_id=<?= $sessionId ?>" class="m-sd-coach-btn" style="text-decoration:none;">
                    <i class="fas fa-video"></i> Record Drill
                </a>
            </div>
        </div>

        <!-- Assign Plan Bottom Sheet -->
        <div class="m-sd-overlay" id="mSdPlanOverlay" onclick="mSdClosePlanSheet()">
            <div class="m-sd-sheet" onclick="event.stopPropagation()">
                <div class="m-sd-sheet-handle"></div>
                <h3 class="m-sd-sheet-title">Assign Practice Plan</h3>
                <form id="mSdAssignPlanForm" style="display:none;">
                    <?= csrfTokenInput() ?>
                </form>
                <div class="m-sd-sheet-field">
                    <label>Practice Plan</label>
                    <select id="mSdPlanSelect" required>
                        <option value="">— Select Plan —</option>
                        <?php foreach ($sdPracticePlans as $plan): ?>
                        <option value="<?= (int)$plan['id'] ?>"<?= ($sdPracticePlan && $sdPracticePlan['id'] == $plan['id']) ? ' selected' : '' ?>><?= htmlspecialchars($plan['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="m-sd-sheet-actions">
                    <button type="button" class="m-sd-sheet-cancel" onclick="mSdClosePlanSheet()">Cancel</button>
                    <button type="button" class="m-sd-sheet-save" id="mSdPlanSaveBtn" onclick="mSdSubmitAssignPlan()">Assign Plan</button>
                </div>
            </div>
        </div>

        <script>
        function mSdOpenAssignPlan() {
            document.getElementById('mSdPlanOverlay').classList.add('m-active');
        }
        function mSdClosePlanSheet() {
            document.getElementById('mSdPlanOverlay').classList.remove('m-active');
        }
        function mSdSubmitAssignPlan() {
            var btn = document.getElementById('mSdPlanSaveBtn');
            var planId = document.getElementById('mSdPlanSelect').value;
            if (!planId) { return; }
            btn.disabled = true;
            btn.textContent = 'Saving...';
            var csrf = document.querySelector('#mSdAssignPlanForm input[name="csrf_token"]').value;
            var form = new FormData();
            form.append('action', 'assign_practice_plan');
            form.append('session_id', <?= json_encode($sessionId) ?>);
            form.append('practice_plan_id', planId);
            form.append('csrf_token', csrf);
            fetch('process_edit_session.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: form
            })
            .then(function(r) { return r.text(); })
            .then(function() { location.reload(); })
            .catch(function() {
                if (typeof showToast === 'function') showToast('Failed to assign plan', 'error');
                btn.disabled = false;
                btn.textContent = 'Assign Plan';
            });
        }
        </script>
        <?php endif; ?>
    <?php endif; ?>
</div>
