<?php
/**
 * PWA Evaluations & Goals - Mobile-native tab view for goals and evaluations
 * Purpose-built for mobile phones.
 */

$goalsUserId = $user_id;
if ($isParent && !empty($_SESSION['viewing_athlete_id'])) {
    $goalsUserId = (int)$_SESSION['viewing_athlete_id'];
}

// Goals
$goals = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, COALESCE(title, goal_title) as title, status, completion_percentage, target_date, category
        FROM goals
        WHERE athlete_id = ?
        ORDER BY CASE status WHEN 'active' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END, created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$goalsUserId]);
    $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $goals = []; }

// Evaluations
$evaluations = [];
try {
    $stmt = $pdo->prepare("
        SELECT es.score, es.max_score, es.evaluation_date, ek.name as skill_name, ek.category
        FROM evaluation_scores es
        LEFT JOIN eval_skills ek ON ek.id = es.skill_id
        WHERE es.athlete_id = ?
        ORDER BY es.evaluation_date DESC
        LIMIT 20
    ");
    $stmt->execute([$goalsUserId]);
    $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $evaluations = []; }
?>
<style>
.m-evalgoals { padding: 0; font-family: Inter, sans-serif; }
.m-tabs {
    display: flex; position: sticky; top: 0; z-index: 10;
    background: #0A0A0F; border-bottom: 1px solid #2D2D3F;
    padding: 0 16px;
}
.m-tab {
    flex: 1; text-align: center; padding: 14px 0; font-size: 14px; font-weight: 600;
    color: #6B6B7B; border: none; background: none; cursor: pointer;
    border-bottom: 2px solid transparent;
    min-height: 44px; font-family: Inter, sans-serif;
}
.m-tab.m-tab-active { color: #8B5CF6; border-bottom-color: #8B5CF6; }
.m-tab-panel { display: none; padding: 16px; }
.m-tab-panel.m-tab-visible { display: block; }
.m-eg-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-eg-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-eg-title { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-eg-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-eg-badge-active { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-eg-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-eg-badge-paused { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-eg-badge-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-eg-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-eg-badge-cat { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-eg-progress { margin-bottom: 8px; }
.m-eg-progress-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.m-eg-progress-label { font-size: 11px; color: #6B6B7B; }
.m-eg-progress-pct { font-size: 11px; color: #8B5CF6; font-weight: 600; }
.m-eg-progress-bar { height: 6px; background: #2D2D3F; border-radius: 3px; overflow: hidden; }
.m-eg-progress-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
.m-eg-meta { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-eg-footer { display: flex; gap: 12px; align-items: center; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-evalgoals">
    <div class="m-tabs">
        <button class="m-tab m-tab-active" onclick="mEGTab('goals', this)" type="button">Goals (<?= count($goals) ?>)</button>
        <button class="m-tab" onclick="mEGTab('evals', this)" type="button">Evaluations (<?= count($evaluations) ?>)</button>
    </div>

    <!-- Goals Tab -->
    <div class="m-tab-panel m-tab-visible" id="m-panel-goals">
        <?php if (empty($goals)): ?>
            <div class="m-empty-state">
                <i class="fas fa-bullseye"></i>
                <p>No goals set yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($goals as $g):
                $pct = max(0, min(100, (int)($g['completion_percentage'] ?? 0)));
                $status = strtolower($g['status'] ?? 'active');
                $badgeClass = match($status) {
                    'active', 'in_progress' => 'active',
                    'completed' => 'completed',
                    'paused' => 'paused',
                    'cancelled' => 'cancelled',
                    default => 'default',
                };
                $barColor = $pct >= 75 ? '#10B981' : ($pct >= 40 ? '#F59E0B' : '#8B5CF6');
            ?>
            <div class="m-eg-card">
                <div class="m-eg-top">
                    <span class="m-eg-title"><?= htmlspecialchars($g['title'] ?? 'Untitled Goal') ?></span>
                    <span class="m-eg-badge m-eg-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                </div>
                <div class="m-eg-progress">
                    <div class="m-eg-progress-header">
                        <span class="m-eg-progress-label">Progress</span>
                        <span class="m-eg-progress-pct"><?= $pct ?>%</span>
                    </div>
                    <div class="m-eg-progress-bar">
                        <div class="m-eg-progress-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                    </div>
                </div>
                <div class="m-eg-footer">
                    <?php if (!empty($g['target_date'])): ?>
                    <span class="m-eg-meta"><i class="fas fa-flag"></i> <?= date('M j, Y', strtotime($g['target_date'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($g['category'])): ?>
                    <span class="m-eg-meta"><i class="fas fa-tag"></i> <?= htmlspecialchars($g['category']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Evaluations Tab -->
    <div class="m-tab-panel" id="m-panel-evals">
        <?php if (empty($evaluations)): ?>
            <div class="m-empty-state">
                <i class="fas fa-clipboard-check"></i>
                <p>No evaluations yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($evaluations as $ev):
                $score = (float)($ev['score'] ?? 0);
                $maxScore = (float)($ev['max_score'] ?? 10);
                $pct = $maxScore > 0 ? min(100, round(($score / $maxScore) * 100)) : 0;
                $barColor = $pct >= 75 ? '#10B981' : ($pct >= 40 ? '#F59E0B' : '#EF4444');
            ?>
            <div class="m-eg-card">
                <div class="m-eg-top">
                    <span class="m-eg-title"><?= htmlspecialchars($ev['skill_name'] ?? 'Unnamed Skill') ?></span>
                    <?php if (!empty($ev['category'])): ?>
                    <span class="m-eg-badge m-eg-badge-cat"><?= htmlspecialchars($ev['category']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="m-eg-progress">
                    <div class="m-eg-progress-header">
                        <span class="m-eg-progress-label">Score</span>
                        <span class="m-eg-progress-pct"><?= $score ?> / <?= $maxScore ?></span>
                    </div>
                    <div class="m-eg-progress-bar">
                        <div class="m-eg-progress-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                    </div>
                </div>
                <?php if (!empty($ev['evaluation_date'])): ?>
                <div class="m-eg-footer">
                    <span class="m-eg-meta"><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($ev['evaluation_date'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function mEGTab(tabId, btn) {
    document.querySelectorAll('.m-tab-panel').forEach(function(p) { p.classList.remove('m-tab-visible'); });
    document.querySelectorAll('.m-tab').forEach(function(t) { t.classList.remove('m-tab-active'); });
    var panel = document.getElementById('m-panel-' + tabId);
    if (panel) panel.classList.add('m-tab-visible');
    if (btn) btn.classList.add('m-tab-active');
}
</script>
