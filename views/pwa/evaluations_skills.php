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
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
