<?php
/**
 * PWA Termination - Mobile-native HR termination records
 * Purpose-built for mobile phones.
 */

if (!$canAccessHR) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">HR access required.</p>';
    echo '</div>';
    return;
}

$terminations = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, employee_name, termination_date, reason, status
        FROM terminations
        ORDER BY termination_date DESC
        LIMIT 20
    ");
    $stmt->execute();
    $terminations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $terminations = []; }

$employees = [];
try {
    $empStmt = $pdo->query("SELECT id, first_name, last_name, role FROM users WHERE is_active = 1 AND role IN ('admin', 'coach', 'health_coach', 'team_coach') ORDER BY first_name, last_name");
    $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) $employees = decryptUserRows($employees);
} catch (PDOException $e) { $employees = []; }

$totalTerms = count($terminations);
?>
<style>
.m-termin { padding: 16px; padding-bottom: 80px; font-family: Inter, sans-serif; }
.m-termin-header { margin-bottom: 16px; }
.m-termin-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-termin-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-termin-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-termin-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; }
.m-termin-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-termin-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-termin-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-termin-badge-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-termin-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-termin-reason { font-size: 12px; color: #A8A8B8; margin: 4px 0 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-termin-date { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-term-fab {
    position: fixed; bottom: 60px; right: 20px; width: 56px; height: 56px;
    background: #6B46C1; color: #fff; border: none; border-radius: 50%;
    font-size: 24px; cursor: pointer; z-index: 999;
    box-shadow: 0 4px 12px rgba(107,70,193,0.4);
    display: flex; align-items: center; justify-content: center;
}
.m-term-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: none;
}
.m-term-overlay.active { display: block; }
.m-term-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1001;
    background: #16161F; border-radius: 16px 16px 0 0; max-height: 85vh;
    overflow-y: auto; transform: translateY(100%); transition: transform 0.3s ease;
    padding: 20px 16px 32px;
}
.m-term-sheet.active { transform: translateY(0); }
.m-term-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-term-field label {
    font-size: 13px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; display: block;
}
.m-term-field { margin-bottom: 14px; }
.m-term-field input, .m-term-field select, .m-term-field textarea {
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff;
    padding: 12px; min-height: 44px; width: 100%; box-sizing: border-box;
    font-family: Inter, sans-serif; font-size: 14px;
}
.m-term-field textarea { min-height: 80px; resize: vertical; }
.m-term-submit {
    background: #6B46C1; color: #fff; border-radius: 10px; min-height: 44px;
    font-weight: 600; width: 100%; border: none; cursor: pointer;
    font-family: Inter, sans-serif; font-size: 15px; margin-top: 8px;
}
.m-term-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.m-term-checklist { display: grid; gap: 8px; }
.m-term-check-item {
    display: flex; align-items: center; gap: 8px; font-size: 13px; color: #A8A8B8;
}
.m-term-check-item input[type="checkbox"] { width: 18px; height: 18px; accent-color: #6B46C1; }
</style>

<div class="m-termin">
    <div class="m-termin-header">
        <h2 class="m-termin-title">Terminations</h2>
        <p class="m-termin-sub"><?= $totalTerms ?> record<?= $totalTerms !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($terminations)): ?>
        <div class="m-empty-state">
            <i class="fas fa-user-minus"></i>
            <p>No termination records</p>
        </div>
    <?php else: ?>
        <?php foreach ($terminations as $t):
            $status = strtolower($t['status'] ?? 'pending');
            $badgeClass = match($status) {
                'completed', 'processed' => 'completed',
                'pending' => 'pending',
                default => 'default',
            };
        ?>
        <div class="m-termin-card">
            <div class="m-termin-top">
                <span class="m-termin-name"><?= htmlspecialchars($t['employee_name'] ?? 'Unknown') ?></span>
                <span class="m-termin-badge m-termin-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
            <?php if (!empty($t['reason'])): ?>
            <div class="m-termin-reason"><?= htmlspecialchars($t['reason']) ?></div>
            <?php endif; ?>
            <?php if (!empty($t['termination_date'])): ?>
            <div class="m-termin-date">
                <i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($t['termination_date'])) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button type="button" class="m-term-fab" onclick="mTermOpen()"><i class="fas fa-plus"></i></button>

<div class="m-term-overlay" id="mTermOverlay" onclick="mTermClose()"></div>
<div class="m-term-sheet" id="mTermSheet">
    <h3 class="m-term-sheet-title">Process Termination</h3>
    <form method="POST" action="process_coach_termination.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="create">
        <div class="m-term-field">
            <label>Staff Member *</label>
            <select name="user_id" required>
                <option value="">-- Select Staff Member --</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= (int)$emp['id'] ?>"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= ucfirst(str_replace('_', ' ', $emp['role'])) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="m-term-field">
            <label>Termination Date *</label>
            <input type="date" name="termination_date" required>
        </div>
        <div class="m-term-row">
            <div class="m-term-field">
                <label>Termination Type *</label>
                <select name="termination_type" required>
                    <option value="">-- Select Type --</option>
                    <option value="voluntary">Voluntary Resignation</option>
                    <option value="involuntary">Involuntary Termination</option>
                    <option value="retirement">Retirement</option>
                    <option value="contract_end">Contract End</option>
                    <option value="mutual">Mutual Agreement</option>
                </select>
            </div>
            <div class="m-term-field">
                <label>Reason Category *</label>
                <select name="reason_category" required>
                    <option value="">-- Select Reason --</option>
                    <option value="performance">Performance Issues</option>
                    <option value="policy">Policy Violation</option>
                    <option value="downsizing">Downsizing</option>
                    <option value="opportunity">Better Opportunity</option>
                    <option value="personal">Personal Reasons</option>
                    <option value="other">Other</option>
                </select>
            </div>
        </div>
        <div class="m-term-field">
            <label>Detailed Reason/Notes *</label>
            <textarea name="notes" required placeholder="Provide detailed reason for termination..."></textarea>
        </div>
        <div class="m-term-field">
            <label>Notice Period (days)</label>
            <input type="number" name="notice_period" placeholder="14" min="0">
        </div>
        <div class="m-term-field">
            <label>Offboarding Checklist</label>
            <div class="m-term-checklist">
                <label class="m-term-check-item"><input type="checkbox" name="checklist[]" value="equipment"> Return company equipment</label>
                <label class="m-term-check-item"><input type="checkbox" name="checklist[]" value="access"> Revoke system access</label>
                <label class="m-term-check-item"><input type="checkbox" name="checklist[]" value="paycheck"> Process final paycheck</label>
                <label class="m-term-check-item"><input type="checkbox" name="checklist[]" value="pto"> Settle unused PTO</label>
                <label class="m-term-check-item"><input type="checkbox" name="checklist[]" value="interview"> Conduct exit interview</label>
                <label class="m-term-check-item"><input type="checkbox" name="checklist[]" value="records"> Update employee records</label>
                <label class="m-term-check-item"><input type="checkbox" name="checklist[]" value="letter"> Provide termination letter</label>
            </div>
        </div>
        <div class="m-term-field">
            <label>Final Comments</label>
            <textarea name="final_comments" placeholder="Any additional comments..."></textarea>
        </div>
        <button type="submit" class="m-term-submit">Process Termination</button>
    </form>
</div>

<script>
function mTermOpen() {
    document.getElementById('mTermOverlay').classList.add('active');
    document.getElementById('mTermSheet').classList.add('active');
}
function mTermClose() {
    document.getElementById('mTermOverlay').classList.remove('active');
    document.getElementById('mTermSheet').classList.remove('active');
}
</script>
