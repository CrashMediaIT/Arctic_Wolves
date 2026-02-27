<?php
/**
 * PWA Coach Evaluations - Mobile-native evaluations performed by coach
 * Purpose-built for mobile phones.
 */

if (!$isAnyCoach) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Coach access required.</p>';
    echo '</div>';
    return;
}

$evaluations = [];
try {
    $stmt = $pdo->prepare("
        SELECT es.id, es.score, es.max_score, es.evaluation_date,
               ek.name as skill_name, u.first_name, u.last_name
        FROM evaluation_scores es
        LEFT JOIN eval_skills ek ON ek.id = es.skill_id
        LEFT JOIN users u ON u.id = es.athlete_id
        WHERE es.evaluator_id = ?
        ORDER BY es.evaluation_date DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $evaluations = []; }

$athletes = [];
try {
    $stmt2 = $pdo->prepare("
        SELECT DISTINCT u.id, u.first_name, u.last_name
        FROM users u
        WHERE u.role = 'athlete' AND u.is_active = 1
        AND (
            EXISTS (SELECT 1 FROM managed_athletes ma WHERE ma.athlete_id = u.id AND ma.coach_id = ?)
            OR EXISTS (SELECT 1 FROM bookings b INNER JOIN sessions s ON b.session_id = s.id WHERE b.user_id = u.id AND s.coach_id = ?)
        )
        ORDER BY u.last_name, u.first_name
    ");
    $stmt2->execute([$user_id, $user_id]);
    $athletes = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) { $athletes = decryptUserRows($athletes); }
} catch (PDOException $e) { $athletes = []; }

$evalSkills = [];
try {
    $stmt3 = $pdo->query("SELECT id, name FROM eval_skills WHERE is_active = 1 ORDER BY name");
    $evalSkills = $stmt3->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $evalSkills = []; }

$totalEvals = count($evaluations);
?>
<style>
.m-coach-evals { padding: 16px; font-family: Inter, sans-serif; }
.m-coach-evals-header { margin-bottom: 16px; }
.m-coach-evals-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-coach-evals-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-ceval-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-ceval-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; }
.m-ceval-skill { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-ceval-score-badge {
    font-size: 12px; font-weight: 700; color: #8B5CF6; flex-shrink: 0;
}
.m-ceval-athlete { font-size: 12px; color: #A8A8B8; margin-bottom: 8px; display: flex; align-items: center; gap: 4px; }
.m-ceval-bar-wrap { margin-bottom: 8px; }
.m-ceval-bar { height: 6px; background: #2D2D3F; border-radius: 3px; overflow: hidden; }
.m-ceval-bar-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
.m-ceval-date { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-ceval-actions { display: flex; gap: 8px; margin-top: 10px; }
.m-ceval-btn { background: rgba(107,70,193,0.15); color: #8B5CF6; border: none; border-radius: 8px; padding: 8px 12px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: Inter, sans-serif; min-height: 44px; display: flex; align-items: center; gap: 4px; }
.m-ceval-detail { display: none; margin-top: 10px; padding-top: 10px; border-top: 1px solid #2D2D3F; }
.m-ceval-detail.m-visible { display: block; }
.m-ceval-detail-label { font-size: 11px; color: #6B6B7B; margin-bottom: 2px; }
.m-ceval-detail-text { font-size: 13px; color: #A8A8B8; margin-bottom: 8px; }
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
</style>

<div class="m-coach-evals">
    <div class="m-coach-evals-header">
        <h2 class="m-coach-evals-title">My Evaluations</h2>
        <p class="m-coach-evals-sub"><?= $totalEvals ?> evaluation<?= $totalEvals !== 1 ? 's' : '' ?> performed</p>
    </div>

    <?php if (empty($evaluations)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clipboard-check"></i>
            <p>No evaluations performed yet</p>
        </div>
    <?php else: ?>
        <?php foreach ($evaluations as $ev):
            $score = (float)($ev['score'] ?? 0);
            $maxScore = (float)($ev['max_score'] ?? 10);
            $pct = $maxScore > 0 ? min(100, round(($score / $maxScore) * 100)) : 0;
            $barColor = $pct >= 75 ? '#10B981' : ($pct >= 40 ? '#F59E0B' : '#EF4444');
            $athName = htmlspecialchars(($ev['first_name'] ?? '') . ' ' . ($ev['last_name'] ?? ''));
        ?>
        <div class="m-ceval-card">
            <div class="m-ceval-top">
                <span class="m-ceval-skill"><?= htmlspecialchars($ev['skill_name'] ?? 'Unnamed Skill') ?></span>
                <span class="m-ceval-score-badge"><?= $score ?>/<?= $maxScore ?></span>
            </div>
            <div class="m-ceval-athlete">
                <i class="fas fa-user"></i> <?= $athName ?>
            </div>
            <div class="m-ceval-bar-wrap">
                <div class="m-ceval-bar">
                    <div class="m-ceval-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                </div>
            </div>
            <?php if (!empty($ev['evaluation_date'])): ?>
            <div class="m-ceval-date">
                <i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($ev['evaluation_date'])) ?>
            </div>
            <?php endif; ?>
            <div class="m-ceval-actions">
                <button class="m-ceval-btn" onclick="mToggleDetail(<?= (int)$ev['id'] ?>)">
                    <i class="fas fa-eye"></i> Details
                </button>
            </div>
            <div class="m-ceval-detail" id="mCevalDetail-<?= (int)$ev['id'] ?>">
                <div class="m-ceval-detail-label">Athlete</div>
                <div class="m-ceval-detail-text"><?= $athName ?></div>
                <div class="m-ceval-detail-label">Score</div>
                <div class="m-ceval-detail-text"><?= $score ?> / <?= $maxScore ?> (<?= $pct ?>%)</div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button class="m-fab" onclick="mOpenCevalSheet()" aria-label="New evaluation">
    <i class="fas fa-plus"></i>
</button>

<div class="m-overlay" id="mCevalOverlay" onclick="mCloseCevalSheet()"></div>
<div class="m-sheet" id="mCevalSheet">
    <div class="m-sheet-handle"></div>
    <h3 class="m-sheet-title">New Evaluation</h3>
    <form id="mCevalForm" onsubmit="mSubmitCeval(event)">
        <?= csrfTokenInput() ?>
        <div class="m-form-group">
            <label class="m-form-label">Athlete *</label>
            <select name="athlete_id" class="m-form-select" required>
                <option value="">Select athlete…</option>
                <?php foreach ($athletes as $a): ?>
                <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Evaluation Date *</label>
            <input type="date" name="evaluation_date" class="m-form-input" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Title</label>
            <input type="text" name="title" class="m-form-input" placeholder="e.g., Mid-Season Assessment">
        </div>
        <button type="submit" class="m-form-submit">Create Evaluation</button>
    </form>
</div>

<script>
function mOpenCevalSheet() {
    document.getElementById('mCevalOverlay').classList.add('m-visible');
    document.getElementById('mCevalSheet').classList.add('m-visible');
}
function mCloseCevalSheet() {
    document.getElementById('mCevalOverlay').classList.remove('m-visible');
    document.getElementById('mCevalSheet').classList.remove('m-visible');
}
function mToggleDetail(id) {
    var el = document.getElementById('mCevalDetail-' + id);
    if (el) el.classList.toggle('m-visible');
}
function mSubmitCeval(e) {
    e.preventDefault();
    var form = e.target;
    var fd = new FormData(form);
    fd.append('action', 'create_evaluation');
    fetch('process_eval_skills.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { persistToast(data.message || 'Operation completed successfully', 'success'); location.reload(); } else { showToast(data.message || 'Error creating evaluation', 'error'); }
        })
        .catch(function() { showToast('Error creating evaluation', 'error'); });
}
</script>
