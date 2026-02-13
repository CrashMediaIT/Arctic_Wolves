<?php
/**
 * PWA Admin Age/Skill Groups - Mobile-native age/skill groups list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$groups = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, min_age, max_age, skill_level FROM age_skill_groups ORDER BY min_age ASC");
    $stmt->execute();
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $groups = []; }
?>
<style>
.m-ageskill { padding: 16px; font-family: Inter, sans-serif; }
.m-ageskill-header { margin-bottom: 16px; }
.m-ageskill-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-ageskill-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-ageskill-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-ageskill-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(139,92,246,0.15); color: #8B5CF6; font-size: 16px; flex-shrink: 0;
}
.m-ageskill-body { flex: 1; min-width: 0; }
.m-ageskill-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-ageskill-meta { display: flex; gap: 12px; margin-top: 4px; flex-wrap: wrap; }
.m-ageskill-detail { font-size: 12px; color: #A8A8B8; display: inline-flex; align-items: center; gap: 4px; }
.m-ageskill-skill {
    font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600;
    background: rgba(59,130,246,0.15); color: #3B82F6; flex-shrink: 0;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-ageskill">
    <div class="m-ageskill-header">
        <h2 class="m-ageskill-title">Age & Skill Groups</h2>
        <p class="m-ageskill-sub"><?= count($groups) ?> group<?= count($groups) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($groups)): ?>
        <div class="m-empty-state">
            <i class="fas fa-layer-group"></i>
            <p>No age/skill groups defined</p>
        </div>
    <?php else: ?>
        <?php foreach ($groups as $g): ?>
        <div class="m-ageskill-card">
            <div class="m-ageskill-icon"><i class="fas fa-layer-group"></i></div>
            <div class="m-ageskill-body">
                <div class="m-ageskill-name"><?= htmlspecialchars($g['name'] ?? '') ?></div>
                <div class="m-ageskill-meta">
                    <span class="m-ageskill-detail"><i class="fas fa-birthday-cake"></i> <?= (int)($g['min_age'] ?? 0) ?>–<?= (int)($g['max_age'] ?? 0) ?> yrs</span>
                </div>
            </div>
            <?php if (!empty($g['skill_level'])): ?>
            <span class="m-ageskill-skill"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $g['skill_level']))) ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
