<?php
/**
 * PWA Admin Coach Termination - Mobile-native coach termination form
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$coaches = [];
try {
    $coaches_query = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.email, u.role,
               COUNT(DISTINCT ma.athlete_id) as athlete_count,
               COUNT(DISTINCT g.id) as goal_count,
               COUNT(DISTINCT ae.id) as evaluation_count
        FROM users u
        LEFT JOIN managed_athletes ma ON ma.parent_id = u.id
        LEFT JOIN goals g ON g.created_by = u.id
        LEFT JOIN athlete_evaluations ae ON ae.coach_id = u.id
        WHERE u.role IN ('coach', 'coach_plus', 'team_coach')
        AND (u.is_deleted = 0 OR u.is_deleted IS NULL)
        GROUP BY u.id
        ORDER BY u.first_name, u.last_name
    ");
    $coaches = $coaches_query->fetchAll(PDO::FETCH_ASSOC);
    $coaches = decryptUserRows($coaches);
} catch (PDOException $e) { $coaches = []; }
?>
<style>
.m-coachterm { padding: 16px; font-family: Inter, sans-serif; padding-bottom: 140px; }
.m-coachterm-header { margin-bottom: 16px; }
.m-coachterm-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-coachterm-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }

/* Warning banner */
.m-coachterm-warning {
    background: rgba(239,68,68,0.08); border: 2px solid #EF4444; border-radius: 12px;
    padding: 14px; margin-bottom: 16px;
}
.m-coachterm-warning h3 {
    font-size: 14px; font-weight: 700; color: #EF4444; margin: 0 0 6px;
    display: flex; align-items: center; gap: 8px;
}
.m-coachterm-warning p {
    font-size: 12px; color: #A8A8B8; margin: 0; line-height: 1.5;
}

/* Coach cards */
.m-coachterm-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-coachterm-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 700; color: #fff; flex-shrink: 0;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
}
.m-coachterm-body { flex: 1; min-width: 0; }
.m-coachterm-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-coachterm-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-coachterm-role-badge {
    font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600;
    flex-shrink: 0;
}
.m-coachterm-role-badge.m-role-coach { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-coachterm-role-badge.m-role-coach_plus { background: rgba(16,185,129,0.15); color: #10B981; }
.m-coachterm-role-badge.m-role-team_coach { background: rgba(59,130,246,0.15); color: #3B82F6; }

.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }

/* FAB */
.m-coachterm-fab {
    position: fixed; bottom: 60px; right: 20px; z-index: 50;
    width: 56px; height: 56px; border-radius: 50%;
    background: #6B46C1; color: #fff; font-size: 22px;
    display: flex; align-items: center; justify-content: center;
    border: none; cursor: pointer;
    box-shadow: 0 4px 16px rgba(107,70,193,0.4);
}

/* Bottom sheet overlay */
.m-coachterm-overlay {
    display: none; position: fixed; inset: 0; z-index: 100;
    background: rgba(0,0,0,0.6); align-items: flex-end; justify-content: center;
}
.m-coachterm-overlay.m-modal-open { display: flex; }
.m-coachterm-sheet {
    background: #16161F; border-radius: 16px 16px 0 0; width: 100%; max-width: 480px;
    max-height: 90vh; overflow-y: auto; padding: 20px 16px 32px;
    animation: mCtSlideUp 0.25s ease-out;
}
@keyframes mCtSlideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }

/* Sheet header */
.m-coachterm-sheet-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 16px;
}
.m-coachterm-sheet-header h3 { font-size: 16px; font-weight: 700; color: #fff; margin: 0; }
.m-coachterm-sheet-close {
    width: 36px; height: 36px; border-radius: 50%; border: none;
    background: #0A0A0F; color: #A8A8B8; font-size: 16px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}

/* Form sections */
.m-ct-section {
    margin-bottom: 16px; padding-bottom: 16px;
    border-bottom: 1px solid #2D2D3F;
}
.m-ct-section:last-of-type { border-bottom: none; }
.m-ct-section-title {
    font-size: 13px; font-weight: 700; color: #8B5CF6; margin: 0 0 10px;
    display: flex; align-items: center; gap: 6px;
}
.m-ct-label {
    display: block; font-size: 12px; font-weight: 600; color: #A8A8B8;
    margin-bottom: 4px; margin-top: 8px;
}
.m-ct-label .m-required { color: #EF4444; }
.m-ct-select, .m-ct-textarea {
    width: 100%; background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 8px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    padding: 10px 12px; box-sizing: border-box; min-height: 44px;
}
.m-ct-select:focus, .m-ct-textarea:focus { border-color: #8B5CF6; outline: none; }
.m-ct-textarea { resize: vertical; min-height: 80px; }
.m-ct-help { font-size: 11px; color: #6B6B7B; margin-top: 4px; }

/* Coach info card */
.m-ct-info {
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 8px;
    padding: 12px; margin-top: 10px; display: none;
}
.m-ct-info.m-ct-info-show { display: block; }
.m-ct-info-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
}
.m-ct-info-label { font-size: 10px; color: #6B6B7B; text-transform: uppercase; letter-spacing: 0.5px; }
.m-ct-info-value { font-size: 15px; font-weight: 700; color: #fff; margin-top: 2px; }

/* Checkbox groups */
.m-ct-checkbox {
    display: flex; align-items: center; gap: 10px;
    padding: 14px; background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 8px;
    cursor: pointer; margin-bottom: 8px; min-height: 44px; width: 100%; box-sizing: border-box;
}
.m-ct-checkbox input[type="checkbox"] {
    width: 22px; height: 22px; cursor: pointer; flex-shrink: 0;
    accent-color: #6B46C1;
}
.m-ct-checkbox label {
    font-size: 13px; color: #fff; cursor: pointer; flex: 1; line-height: 1.4;
}

/* Submit button */
.m-ct-submit {
    margin-top: 16px; width: 100%; padding: 14px; border: none; border-radius: 10px;
    background: #EF4444; color: #fff; font-size: 15px; font-weight: 600;
    font-family: Inter, sans-serif; cursor: pointer; min-height: 48px;
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.m-ct-submit:disabled { opacity: 0.5; cursor: not-allowed; }
</style>

<div class="m-coachterm">
    <div class="m-coachterm-header">
        <h2 class="m-coachterm-title"><i class="fas fa-user-times"></i> Coach Termination</h2>
        <p class="m-coachterm-sub"><?= count($coaches) ?> active coach<?= count($coaches) !== 1 ? 'es' : '' ?></p>
    </div>

    <div class="m-coachterm-warning">
        <h3><i class="fas fa-exclamation-triangle"></i> Irreversible Action</h3>
        <p>Terminating a coach will permanently remove access, transfer all athletes and data to another coach, and create an audit trail. This cannot be easily undone.</p>
    </div>

    <?php if (empty($coaches)): ?>
        <div class="m-empty-state">
            <i class="fas fa-user-shield"></i>
            <p>No coaches available</p>
        </div>
    <?php else: ?>
        <?php foreach ($coaches as $c):
            $initial = strtoupper(mb_substr($c['first_name'] ?? '?', 0, 1));
            $fullName = htmlspecialchars(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
            $role = $c['role'] ?? 'coach';
            $roleClass = 'm-role-' . $role;
            $roleLabel = ucwords(str_replace('_', ' ', $role));
        ?>
        <div class="m-coachterm-card">
            <div class="m-coachterm-avatar"><?= $initial ?></div>
            <div class="m-coachterm-body">
                <div class="m-coachterm-name"><?= $fullName ?></div>
                <div class="m-coachterm-meta"><?= (int)$c['athlete_count'] ?> athlete<?= (int)$c['athlete_count'] !== 1 ? 's' : '' ?></div>
            </div>
            <span class="m-coachterm-role-badge <?= $roleClass ?>"><?= htmlspecialchars($roleLabel) ?></span>
        </div>
        <?php endforeach; ?>

        <!-- FAB -->
        <button class="m-coachterm-fab" onclick="mCtOpenSheet()" type="button" aria-label="Open termination form">
            <i class="fas fa-user-times"></i>
        </button>

        <!-- Bottom sheet -->
        <div class="m-coachterm-overlay" id="mCtOverlay" onclick="if(event.target===this)mCtCloseSheet()">
            <div class="m-coachterm-sheet">
                <div class="m-coachterm-sheet-header">
                    <h3><i class="fas fa-user-times"></i> Terminate Coach</h3>
                    <button class="m-coachterm-sheet-close" onclick="mCtCloseSheet()" type="button" aria-label="Close">&times;</button>
                </div>

                <form id="mCtForm" onsubmit="mCtSubmit(event)">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                    <!-- Step 1: Select Coach -->
                    <div class="m-ct-section">
                        <div class="m-ct-section-title"><i class="fas fa-user-minus"></i> Step 1: Select Coach</div>
                        <label class="m-ct-label">Coach to Terminate <span class="m-required">*</span></label>
                        <select name="coach_to_terminate" id="mCtCoach" class="m-ct-select" required onchange="mCtCoachChanged()">
                            <option value="">-- Select a coach --</option>
                            <?php foreach ($coaches as $coach): ?>
                            <option value="<?= $coach['id'] ?>"
                                    data-name="<?= htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']) ?>"
                                    data-email="<?= htmlspecialchars($coach['email']) ?>"
                                    data-role="<?= htmlspecialchars($coach['role']) ?>"
                                    data-athletes="<?= $coach['athlete_count'] ?>"
                                    data-goals="<?= $coach['goal_count'] ?>"
                                    data-evaluations="<?= $coach['evaluation_count'] ?>">
                                <?= htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']) ?> (<?= htmlspecialchars($coach['email']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="m-ct-info" id="mCtInfo">
                            <div class="m-ct-info-grid">
                                <div><div class="m-ct-info-label">Role</div><div class="m-ct-info-value" id="mCtInfoRole">-</div></div>
                                <div><div class="m-ct-info-label">Athletes</div><div class="m-ct-info-value" id="mCtInfoAthletes">-</div></div>
                                <div><div class="m-ct-info-label">Goals</div><div class="m-ct-info-value" id="mCtInfoGoals">-</div></div>
                                <div><div class="m-ct-info-label">Evaluations</div><div class="m-ct-info-value" id="mCtInfoEvals">-</div></div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Transfer Coach -->
                    <div class="m-ct-section">
                        <div class="m-ct-section-title"><i class="fas fa-exchange-alt"></i> Step 2: Transfer Coach</div>
                        <label class="m-ct-label">New Coach (Transfer Target) <span class="m-required">*</span></label>
                        <select name="transfer_to_coach" id="mCtTransfer" class="m-ct-select" required>
                            <option value="">-- Select a coach --</option>
                            <?php foreach ($coaches as $coach): ?>
                            <option value="<?= $coach['id'] ?>">
                                <?= htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']) ?> (<?= htmlspecialchars(ucwords(str_replace('_', ' ', $coach['role']))) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="m-ct-help">All athletes, goals, and evaluations will be transferred to this coach</div>
                    </div>

                    <!-- Step 3: Reason -->
                    <div class="m-ct-section">
                        <div class="m-ct-section-title"><i class="fas fa-file-alt"></i> Step 3: Termination Details</div>
                        <label class="m-ct-label">Reason for Termination <span class="m-required">*</span></label>
                        <textarea name="termination_reason" class="m-ct-textarea" required placeholder="Enter reason for termination (for audit purposes)"></textarea>
                        <div class="m-ct-help">This will be stored in the audit log</div>
                    </div>

                    <!-- Step 4: Confirmation -->
                    <div class="m-ct-section">
                        <div class="m-ct-section-title"><i class="fas fa-check-double"></i> Step 4: Confirmation</div>

                        <div class="m-ct-checkbox" onclick="this.querySelector('input').click()">
                            <input type="checkbox" id="mCtConfirmBackup" name="confirm_backup" required onclick="event.stopPropagation()">
                            <label for="mCtConfirmBackup" onclick="event.stopPropagation()">I understand that an automatic database backup will be created before termination</label>
                        </div>
                        <div class="m-ct-checkbox" onclick="this.querySelector('input').click()">
                            <input type="checkbox" id="mCtConfirmTransfer" name="confirm_transfer" required onclick="event.stopPropagation()">
                            <label for="mCtConfirmTransfer" onclick="event.stopPropagation()">I confirm that all athletes and data will be transferred to the selected coach</label>
                        </div>
                        <div class="m-ct-checkbox" onclick="this.querySelector('input').click()">
                            <input type="checkbox" id="mCtConfirmPermanent" name="confirm_permanent" required onclick="event.stopPropagation()">
                            <label for="mCtConfirmPermanent" onclick="event.stopPropagation()">I understand this action will soft-delete the coach account and cannot be easily reversed</label>
                        </div>
                    </div>

                    <button type="submit" class="m-ct-submit" id="mCtSubmitBtn">
                        <i class="fas fa-user-times"></i> Terminate Coach
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function mCtOpenSheet() {
    document.getElementById('mCtOverlay').classList.add('m-modal-open');
}
function mCtCloseSheet() {
    document.getElementById('mCtOverlay').classList.remove('m-modal-open');
}

function mCtCoachChanged() {
    var sel = document.getElementById('mCtCoach');
    var opt = sel.options[sel.selectedIndex];
    var info = document.getElementById('mCtInfo');
    if (opt.value) {
        document.getElementById('mCtInfoRole').textContent = opt.dataset.role ? opt.dataset.role.replace(/_/g, ' ') : '-';
        document.getElementById('mCtInfoAthletes').textContent = opt.dataset.athletes || '0';
        document.getElementById('mCtInfoGoals').textContent = opt.dataset.goals || '0';
        document.getElementById('mCtInfoEvals').textContent = opt.dataset.evaluations || '0';
        info.classList.add('m-ct-info-show');
        // Disable same coach in transfer select
        var transfer = document.getElementById('mCtTransfer');
        for (var i = 0; i < transfer.options.length; i++) {
            transfer.options[i].disabled = (transfer.options[i].value === opt.value);
        }
        if (transfer.value === opt.value) transfer.value = '';
    } else {
        info.classList.remove('m-ct-info-show');
        var transfer = document.getElementById('mCtTransfer');
        for (var i = 0; i < transfer.options.length; i++) {
            transfer.options[i].disabled = false;
        }
    }
}

function mCtSubmit(e) {
    e.preventDefault();
    var sel = document.getElementById('mCtCoach');
    var opt = sel.options[sel.selectedIndex];
    var coachName = opt.dataset.name || 'this coach';
    var athleteCount = opt.dataset.athletes || '0';

    var msg = 'Are you sure you want to terminate ' + coachName + '?\n\n' +
              'This will:\n' +
              '- Create an automatic database backup\n' +
              '- Transfer ' + athleteCount + ' athlete(s) to the new coach\n' +
              '- Soft-delete the coach account\n\n' +
              'Type "TERMINATE" to confirm:';

    var confirmation = prompt(msg);
    if (confirmation !== 'TERMINATE') {
        alert('Termination cancelled. You must type "TERMINATE" to confirm.');
        return;
    }

    var btn = document.getElementById('mCtSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing…';

    var formData = new FormData(document.getElementById('mCtForm'));

    fetch('process_coach_termination.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('SUCCESS: ' + data.message + '\n\nBackup created: ' + (data.backup_file || 'N/A'));
            window.location.href = '?page=admin_team_coaches';
        } else {
            alert('ERROR: ' + data.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-user-times"></i> Terminate Coach';
        }
    })
    .catch(function(err) {
        alert('ERROR: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-user-times"></i> Terminate Coach';
    });
}
</script>
