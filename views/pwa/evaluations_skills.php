<?php
/**
 * PWA Evaluations Skills - Mobile-native skills evaluation list
 * Purpose-built for mobile phones.
 */

$skills = [];
try {
    $stmt = $pdo->prepare("
        SELECT es.id as skill_id, es.name, es.description, es.category
        FROM eval_skills es
        ORDER BY es.category, es.name
        LIMIT 40
    ");
    $stmt->execute();
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $skills = []; }

// Group by category
$grouped = [];
foreach ($skills as $s) {
    $cat = $s['category'] ?? 'Uncategorized';
    $grouped[$cat][] = $s;
}

$isCoachView = isset($isCoach) ? $isCoach : (isset($isAnyCoach) ? $isAnyCoach : false);
$evalAthletes = [];
if ($isCoachView) {
    try {
        $stmtA = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role = 'athlete' AND is_active = 1 ORDER BY last_name, first_name");
        $evalAthletes = $stmtA->fetchAll(PDO::FETCH_ASSOC);
        if (function_exists('decryptUserRows')) { $evalAthletes = decryptUserRows($evalAthletes); }
    } catch (PDOException $e) { $evalAthletes = []; }
}
?>
<style>
.m-evalskills { padding: 16px; font-family: Inter, sans-serif; }
.m-evalskills-header { margin-bottom: 16px; }
.m-evalskills-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-evalskills-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-skill-section { margin-bottom: 20px; }
.m-skill-section-title {
    font-size: 13px; font-weight: 600; color: #6B6B7B;
    text-transform: uppercase; letter-spacing: 0.5px;
    margin: 0 0 10px; padding: 0 4px;
}
.m-skill-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
}
.m-skill-name { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 4px; }
.m-skill-desc { font-size: 12px; color: #A8A8B8; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-skill-score-btn { background: rgba(107,70,193,0.15); color: #8B5CF6; border: none; border-radius: 8px; padding: 8px 12px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: Inter, sans-serif; min-height: 44px; display: flex; align-items: center; gap: 4px; margin-top: 8px; width: 100%; justify-content: center; }
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
.m-score-input { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
.m-score-field { background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff; padding: 8px; width: 60px; text-align: center; font-size: 14px; font-family: Inter, sans-serif; min-height: 44px; }
.m-score-max { font-size: 12px; color: #6B6B7B; }
</style>

<div class="m-evalskills">
    <div class="m-evalskills-header">
        <h2 class="m-evalskills-title">Evaluation Skills</h2>
        <p class="m-evalskills-sub"><?= count($skills) ?> skill<?= count($skills) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($skills)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clipboard-list"></i>
            <p>No evaluation skills defined</p>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $category => $catSkills): ?>
        <div class="m-skill-section">
            <h3 class="m-skill-section-title"><?= htmlspecialchars($category) ?></h3>
            <?php foreach ($catSkills as $s): ?>
            <div class="m-skill-card">
                <div class="m-skill-name"><?= htmlspecialchars($s['name'] ?? 'Unnamed') ?></div>
                <?php if (!empty($s['description'])): ?>
                <p class="m-skill-desc"><?= htmlspecialchars($s['description']) ?></p>
                <?php endif; ?>
                <?php if ($isCoachView): ?>
                <button class="m-skill-score-btn" onclick="mOpenScoreSheet(<?= (int)$s['skill_id'] ?>, '<?= htmlspecialchars($s['name'] ?? '', ENT_QUOTES) ?>')">
                    <i class="fas fa-star"></i> Score Athlete
                </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($isCoachView): ?>
<button class="m-fab" onclick="mOpenSheet('mSkillCreateOv','mSkillCreateSh')" aria-label="New evaluation"><i class="fas fa-plus"></i></button>

<div class="m-overlay" id="mSkillCreateOv" onclick="mCloseSheet('mSkillCreateOv','mSkillCreateSh')"></div>
<div class="m-sheet" id="mSkillCreateSh">
    <div class="m-sheet-handle"></div>
    <h3 class="m-sheet-title">Create Skills Evaluation</h3>
    <form id="mSkillCreateForm" onsubmit="mCreateSkillEval(event)">
        <?= csrfTokenInput() ?>
        <div class="m-form-group">
            <label class="m-form-label">Athlete *</label>
            <select name="athlete_id" class="m-form-select" required>
                <option value="">Select athlete…</option>
                <?php foreach ($evalAthletes as $a): ?>
                <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?></option>
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

<div class="m-overlay" id="mSkillScoreOv" onclick="mCloseSheet('mSkillScoreOv','mSkillScoreSh')"></div>
<div class="m-sheet" id="mSkillScoreSh">
    <div class="m-sheet-handle"></div>
    <h3 class="m-sheet-title" id="mScoreSkillTitle">Score Skill</h3>
    <form id="mSkillScoreForm" onsubmit="mSubmitScore(event)">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="skill_id" id="mScoreSkillId">
        <div class="m-form-group">
            <label class="m-form-label">Athlete *</label>
            <select name="athlete_id" id="mScoreAthleteId" class="m-form-select" required>
                <option value="">Select athlete…</option>
                <?php foreach ($evalAthletes as $a): ?>
                <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Score (0-10) *</label>
            <input type="number" name="score" class="m-form-input" min="0" max="10" step="0.5" required placeholder="0-10">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Notes</label>
            <textarea name="notes" class="m-form-textarea" placeholder="Optional notes…"></textarea>
        </div>
        <button type="submit" class="m-form-submit">Save Score</button>
    </form>
</div>
<?php endif; ?>

<script>
function mOpenSheet(ovId, shId) { document.getElementById(ovId).classList.add('m-visible'); document.getElementById(shId).classList.add('m-visible'); }
function mCloseSheet(ovId, shId) { document.getElementById(ovId).classList.remove('m-visible'); document.getElementById(shId).classList.remove('m-visible'); }

function mOpenScoreSheet(skillId, skillName) {
    document.getElementById('mScoreSkillId').value = skillId;
    document.getElementById('mScoreSkillTitle').textContent = 'Score: ' + skillName;
    mOpenSheet('mSkillScoreOv', 'mSkillScoreSh');
}

function mCreateSkillEval(e) {
    e.preventDefault();
    var fd = new FormData(e.target);
    fd.append('action', 'create_evaluation');
    fetch('process_eval_skills.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { persistToast(data.message || 'Operation completed successfully', 'success'); location.reload(); } else { alert(data.message || 'Error'); }
        })
        .catch(function() { alert('Error creating evaluation'); });
}

function mSubmitScore(e) {
    e.preventDefault();
    var fd = new FormData(e.target);
    fd.append('action', 'save_score');
    fetch('process_eval_skills.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { mCloseSheet('mSkillScoreOv', 'mSkillScoreSh'); e.target.reset(); alert('Score saved!'); }
            else { alert(data.message || 'Error saving score'); }
        })
        .catch(function() { alert('Error saving score'); });
}
</script>
