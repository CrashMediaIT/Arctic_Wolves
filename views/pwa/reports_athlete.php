<?php
/**
 * PWA Reports Athlete - Mobile-native athlete performance reports
 * Purpose-built for mobile phones.
 */

$athleteId = $user_id;
if (($isAnyCoach || $isAdmin) && !empty($_GET['athlete_id'])) {
    $athleteId = (int)$_GET['athlete_id'];
}

$recentEvals = [];
$recentGoals = [];
try {
    $stmt = $pdo->prepare("
        SELECT es.score, es.max_score, es.evaluation_date, ek.name as skill_name
        FROM evaluation_scores es
        LEFT JOIN eval_skills ek ON ek.id = es.skill_id
        WHERE es.athlete_id = ?
        ORDER BY es.evaluation_date DESC
        LIMIT 5
    ");
    $stmt->execute([$athleteId]);
    $recentEvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $recentEvals = []; }

try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(title, goal_title) as title, status, completion_percentage
        FROM goals WHERE athlete_id = ?
        ORDER BY created_at DESC LIMIT 5
    ");
    $stmt->execute([$athleteId]);
    $recentGoals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $recentGoals = []; }
?>
<style>
.m-rptath { padding: 16px; font-family: Inter, sans-serif; }
.m-rptath-header { margin-bottom: 16px; }
.m-rptath-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-rptath-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-rptath-section { margin-bottom: 20px; }
.m-rptath-section-title {
    font-size: 13px; font-weight: 600; color: #6B6B7B;
    text-transform: uppercase; letter-spacing: 0.5px;
    margin: 0 0 10px; padding: 0 4px;
}
.m-rptath-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
}
.m-rptath-card-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.m-rptath-card-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; }
.m-rptath-card-score { font-size: 12px; font-weight: 700; color: #8B5CF6; }
.m-rptath-bar { height: 6px; background: #2D2D3F; border-radius: 3px; overflow: hidden; }
.m-rptath-bar-fill { height: 100%; border-radius: 3px; }
.m-rptath-meta { font-size: 11px; color: #6B6B7B; margin-top: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<?php
$allAthletes = [];
if ($isAnyCoach || $isAdmin) {
    try {
        $aSt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'athlete' ORDER BY last_name, first_name LIMIT 200");
        $aSt->execute();
        $allAthletes = $aSt->fetchAll(PDO::FETCH_ASSOC);
        if (function_exists('decryptUserRows')) { $allAthletes = decryptUserRows($allAthletes); }
    } catch (PDOException $e) { $allAthletes = []; }
}
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
?>
<div class="m-rptath">
    <div class="m-rptath-header">
        <h2 class="m-rptath-title">Athlete Report</h2>
        <p class="m-rptath-sub">Performance metrics</p>
    </div>

    <?php if ($isAnyCoach || $isAdmin): ?>
    <!-- Athlete selector & filters -->
    <div style="margin-bottom:16px;">
        <label style="font-size:12px;color:#A8A8B8;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:6px;">Select Athlete</label>
        <select id="mRptAthSelect" style="width:100%;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:12px;min-height:44px;font-size:14px;-webkit-appearance:none;" onchange="mRptAthGo()">
            <option value="">-- Choose athlete --</option>
            <?php foreach ($allAthletes as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $athleteId == $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
        <div>
            <label style="font-size:11px;color:#A8A8B8;display:block;margin-bottom:4px;">Year</label>
            <select id="mRptAthYear" style="width:100%;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:12px;min-height:44px;font-size:14px;-webkit-appearance:none;" onchange="mRptAthGo()">
                <?php for ($y = (int)date('Y'); $y >= 2020; $y--): ?>
                <option value="<?= $y ?>" <?= $selectedYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div style="display:flex;align-items:flex-end;">
            <button onclick="mRptAthExport()" style="width:100%;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:12px;font-size:13px;font-weight:600;min-height:44px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;">
                <i class="fas fa-file-pdf" style="color:#EF4444;"></i> Export
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="m-rptath-section">
        <h3 class="m-rptath-section-title">Recent Evaluations</h3>
        <?php if (empty($recentEvals)): ?>
            <div class="m-empty-state">
                <i class="fas fa-clipboard-check"></i>
                <p>No evaluations yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($recentEvals as $ev):
                $score = (float)($ev['score'] ?? 0);
                $maxScore = (float)($ev['max_score'] ?? 10);
                $pct = $maxScore > 0 ? min(100, round(($score / $maxScore) * 100)) : 0;
                $barColor = $pct >= 75 ? '#10B981' : ($pct >= 40 ? '#F59E0B' : '#EF4444');
            ?>
            <div class="m-rptath-card">
                <div class="m-rptath-card-top">
                    <span class="m-rptath-card-name"><?= htmlspecialchars($ev['skill_name'] ?? 'Skill') ?></span>
                    <span class="m-rptath-card-score"><?= $score ?>/<?= $maxScore ?></span>
                </div>
                <div class="m-rptath-bar">
                    <div class="m-rptath-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                </div>
                <?php if (!empty($ev['evaluation_date'])): ?>
                <div class="m-rptath-meta"><?= date('M j, Y', strtotime($ev['evaluation_date'])) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="m-rptath-section">
        <h3 class="m-rptath-section-title">Recent Goals</h3>
        <?php if (empty($recentGoals)): ?>
            <div class="m-empty-state">
                <i class="fas fa-bullseye"></i>
                <p>No goals yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($recentGoals as $g):
                $pct = max(0, min(100, (int)($g['completion_percentage'] ?? 0)));
                $barColor = $pct >= 75 ? '#10B981' : ($pct >= 40 ? '#F59E0B' : '#8B5CF6');
            ?>
            <div class="m-rptath-card">
                <div class="m-rptath-card-top">
                    <span class="m-rptath-card-name"><?= htmlspecialchars($g['title'] ?? 'Goal') ?></span>
                    <span class="m-rptath-card-score"><?= $pct ?>%</span>
                </div>
                <div class="m-rptath-bar">
                    <div class="m-rptath-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function mRptAthGo() {
    var aid = document.getElementById('mRptAthSelect') ? document.getElementById('mRptAthSelect').value : '';
    var yr = document.getElementById('mRptAthYear') ? document.getElementById('mRptAthYear').value : '';
    var url = '?page=reports_athlete';
    if (aid) url += '&athlete_id=' + aid;
    if (yr) url += '&year=' + yr;
    window.location.href = url;
}
function mRptAthExport() { window.print(); }
</script>
