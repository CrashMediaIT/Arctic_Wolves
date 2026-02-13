<?php
/**
 * PWA Coach Session Evaluations - Mobile-native evaluation list for coaches
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

$evaluations = [];
try {
    $stmt = $pdo->prepare("
        SELECT se.id, se.session_id, se.created_at,
               s.title as session_title, s.session_date,
               (SELECT COUNT(*) FROM evaluation_scores es WHERE es.evaluation_id = se.id) as score_count
        FROM session_evaluations se
        JOIN sessions s ON s.id = se.session_id
        WHERE se.evaluator_id = ?
        ORDER BY se.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $evaluations = []; }
?>
<style>
.m-evals { padding: 16px; font-family: Inter, sans-serif; }
.m-evals-header { margin-bottom: 16px; }
.m-evals-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-evals-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-eval-card {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
    text-decoration: none; min-height: 44px;
}
.m-eval-icon {
    width: 44px; height: 44px; border-radius: 10px;
    background: rgba(107,70,193,0.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: #8B5CF6; flex-shrink: 0;
}
.m-eval-body { flex: 1; min-width: 0; }
.m-eval-title { font-size: 14px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-eval-meta { font-size: 12px; color: #A8A8B8; margin-top: 3px; }
.m-eval-count {
    font-size: 12px; padding: 4px 10px; border-radius: 8px; font-weight: 600;
    background: rgba(59,130,246,0.15); color: #3B82F6; white-space: nowrap; flex-shrink: 0;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-eval-actions { display: flex; gap: 8px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #2D2D3F; width: 100%; }
.m-eval-btn { background: rgba(107,70,193,0.15); color: #8B5CF6; border: none; border-radius: 8px; padding: 8px 12px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: Inter, sans-serif; min-height: 44px; display: flex; align-items: center; gap: 4px; flex: 1; justify-content: center; text-decoration: none; }
.m-fab { position: fixed; bottom: 60px; right: 20px; width: 56px; height: 56px; border-radius: 50%; background: #6B46C1; color: #fff; border: none; font-size: 22px; cursor: pointer; z-index: 999; box-shadow: 0 4px 12px rgba(107,70,193,0.4); display: flex; align-items: center; justify-content: center; }
.m-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: none; }
.m-overlay.m-visible { display: block; }
.m-sheet { position: fixed; bottom: 0; left: 0; right: 0; background: #16161F; border-radius: 16px 16px 0 0; max-height: 85vh; overflow-y: auto; z-index: 1001; transform: translateY(100%); transition: transform 0.3s ease; padding: 20px 16px 32px; }
.m-sheet.m-visible { transform: translateY(0); }
.m-sheet-handle { width: 36px; height: 4px; background: #2D2D3F; border-radius: 2px; margin: 0 auto 16px; }
.m-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-form-group { margin-bottom: 14px; }
.m-form-label { font-size: 13px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; display: block; }
.m-form-input, .m-form-select, .m-form-textarea { background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff; padding: 12px; min-height: 44px; width: 100%; box-sizing: border-box; font-family: Inter, sans-serif; font-size: 14px; }
.m-form-textarea { min-height: 80px; resize: vertical; }
.m-form-submit { background: #6B46C1; color: #fff; border-radius: 10px; min-height: 44px; font-weight: 600; width: 100%; border: none; cursor: pointer; font-family: Inter, sans-serif; font-size: 14px; margin-top: 8px; }
.m-form-help { font-size: 11px; color: #6B6B7B; margin-top: 4px; }
</style>

<div class="m-evals">
    <div class="m-evals-header">
        <h2 class="m-evals-title">Session Evaluations</h2>
        <p class="m-evals-sub"><?= count($evaluations) ?> evaluation<?= count($evaluations) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($evaluations)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clipboard-check"></i>
            <p>No evaluations submitted yet</p>
        </div>
    <?php else: ?>
        <?php foreach ($evaluations as $ev): ?>
        <div class="m-eval-card" style="cursor:default;">
            <div class="m-eval-icon"><i class="fas fa-clipboard-check"></i></div>
            <div class="m-eval-body">
                <div class="m-eval-title"><?= htmlspecialchars($ev['session_title'] ?? 'Session Evaluation') ?></div>
                <div class="m-eval-meta">
                    <i class="fas fa-calendar" style="font-size:10px;"></i>
                    <?= date('M j, Y', strtotime($ev['session_date'])) ?>
                    · <?= date('g:i A', strtotime($ev['created_at'])) ?>
                </div>
            </div>
            <span class="m-eval-count"><?= (int)$ev['score_count'] ?> score<?= (int)$ev['score_count'] !== 1 ? 's' : '' ?></span>
            <div class="m-eval-actions">
                <a href="?page=session_detail&id=<?= (int)$ev['session_id'] ?>" class="m-eval-btn"><i class="fas fa-eye"></i> View</a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button class="m-fab" onclick="mOpenAssignSheet()" aria-label="Assign evaluation"><i class="fas fa-plus"></i></button>

<div class="m-overlay" id="mSessEvalOv" onclick="mCloseSheet('mSessEvalOv','mSessEvalSh')"></div>
<div class="m-sheet" id="mSessEvalSh">
    <div class="m-sheet-handle"></div>
    <h3 class="m-sheet-title">Assign Evaluation to Session</h3>
    <form method="POST" action="process_session_evaluations.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="assign_evaluation_to_session">
        <div class="m-form-group">
            <label class="m-form-label">Select Session *</label>
            <select name="session_id" id="mSessSelect" class="m-form-select" required>
                <option value="">Loading sessions…</option>
            </select>
            <p class="m-form-help">Only sessions without evaluations are shown</p>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Evaluation Name</label>
            <input type="text" name="name" class="m-form-input" placeholder="e.g., Skills Assessment">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Description</label>
            <textarea name="description" class="m-form-textarea" placeholder="Add notes about this evaluation…"></textarea>
        </div>
        <button type="submit" class="m-form-submit">Assign Evaluation</button>
    </form>
</div>

<script>
function mOpenSheet(ovId, shId) { document.getElementById(ovId).classList.add('m-visible'); document.getElementById(shId).classList.add('m-visible'); }
function mCloseSheet(ovId, shId) { document.getElementById(ovId).classList.remove('m-visible'); document.getElementById(shId).classList.remove('m-visible'); }

function mOpenAssignSheet() {
    var select = document.getElementById('mSessSelect');
    select.innerHTML = '<option value="">Loading…</option>';
    fetch('process_session_evaluations.php?action=get_available_sessions')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            select.innerHTML = '<option value="">-- Select Session --</option>';
            if (data.success && data.sessions) {
                data.sessions.forEach(function(s) {
                    var d = new Date(s.session_date + 'T00:00:00');
                    var label = s.title + ' - ' + d.toLocaleDateString();
                    if (s.location_name) label += ' @ ' + s.location_name;
                    var opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = label;
                    select.appendChild(opt);
                });
            }
        })
        .catch(function() { select.innerHTML = '<option value="">Error loading sessions</option>'; });
    mOpenSheet('mSessEvalOv', 'mSessEvalSh');
}
</script>
