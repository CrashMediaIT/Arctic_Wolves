<?php
/**
 * PWA View Practice Plan - Mobile-native practice plan detail view
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

$planId = (int)($_GET['id'] ?? 0);
$plan = null;
$planDrills = [];

if ($planId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT pp.*, u.first_name, u.last_name
            FROM practice_plans pp
            LEFT JOIN users u ON u.id = pp.created_by
            WHERE pp.id = ?
        ");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        $plan = decryptUserRow($plan);
    } catch (PDOException $e) { $plan = null; }

    if ($plan) {
        try {
            $stmt = $pdo->prepare("
                SELECT pd.*, d.title as drill_title, d.description as drill_description,
                       d.setup as drill_setup, d.coaching_points as drill_coaching_points,
                       d.video_url as drill_video_url, d.custom_image as drill_image,
                       d.thumbnail_path as drill_thumbnail, dc.name as category_name
                FROM practice_plan_drills pd
                LEFT JOIN drills d ON d.id = pd.drill_id
                LEFT JOIN drill_categories dc ON d.category_id = dc.id
                WHERE pd.practice_plan_id = ?
                ORDER BY pd.drill_order ASC
            ");
            $stmt->execute([$planId]);
            $planDrills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { $planDrills = []; }
    }
}

if (!$plan):
?>
<style>
.m-not-found { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; color: #6B6B7B; font-family: Inter, sans-serif; text-align: center; }
.m-not-found i { font-size: 48px; margin-bottom: 16px; }
.m-not-found p { font-size: 15px; margin: 0 0 16px; }
.m-not-found a { color: #8B5CF6; text-decoration: none; font-size: 14px; font-weight: 600; }
</style>
<div class="m-not-found">
    <i class="fas fa-clipboard-list"></i>
    <p>Practice plan not found</p>
    <a href="?page=practice"><i class="fas fa-arrow-left"></i> Back to Practice Plans</a>
</div>
<?php
    return;
endif;

$creatorName = trim(($plan['first_name'] ?? '') . ' ' . ($plan['last_name'] ?? ''));
?>
<style>
.m-plan-detail { padding: 16px; font-family: Inter, sans-serif; }
.m-back-link {
    display: inline-flex; align-items: center; gap: 6px;
    color: #8B5CF6; text-decoration: none; font-size: 13px; font-weight: 600;
    margin-bottom: 16px; min-height: 44px; padding: 8px 0;
}
.m-plan-hero {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 16px;
    padding: 20px; margin-bottom: 16px;
}
.m-plan-hero-title { font-size: 18px; font-weight: 700; color: #fff; margin: 0 0 10px; }
.m-plan-hero-meta { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 12px; }
.m-plan-hero-tag {
    font-size: 11px; display: flex; align-items: center; gap: 4px; color: #6B6B7B;
}
.m-plan-hero-tag i { font-size: 12px; }
.m-plan-hero-tag-highlight { color: #8B5CF6; font-weight: 600; }
.m-plan-hero-notes { font-size: 13px; color: #A8A8B8; line-height: 1.5; margin: 0; }
.m-section { margin-bottom: 20px; }
.m-section-title {
    font-size: 13px; font-weight: 600; color: #6B6B7B;
    text-transform: uppercase; letter-spacing: 0.5px;
    margin: 0 0 10px; padding: 0 4px;
}
.m-plan-drill-item {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    margin-bottom: 10px; text-decoration: none; display: block;
    min-height: 44px; overflow: hidden;
}
.m-plan-drill-thumb {
    width: 100%; height: 140px; object-fit: cover; display: block;
    background: #0A0A0F;
}
.m-plan-drill-content {
    display: flex; gap: 12px; align-items: flex-start; padding: 14px;
}
.m-plan-drill-num {
    width: 32px; height: 32px; border-radius: 50%;
    background: rgba(107,70,193,0.2); color: #8B5CF6;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; flex-shrink: 0;
}
.m-plan-drill-body { flex: 1; min-width: 0; }
.m-plan-drill-title { font-size: 14px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-plan-drill-meta { font-size: 12px; color: #A8A8B8; margin-top: 3px; display: flex; align-items: center; gap: 8px; }
.m-plan-drill-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-plan-drill-badge-easy { background: rgba(16,185,129,0.15); color: #10B981; }
.m-plan-drill-badge-medium { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-plan-drill-badge-hard { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-plan-drill-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-plan-detail">
    <a href="?page=practice" class="m-back-link"><i class="fas fa-arrow-left"></i> Back to Practice Plans</a>

    <div class="m-plan-hero">
        <h2 class="m-plan-hero-title"><?= htmlspecialchars($plan['title']) ?></h2>
        <div class="m-plan-hero-meta">
            <?php if ($plan['total_duration']): ?>
            <span class="m-plan-hero-tag m-plan-hero-tag-highlight"><i class="fas fa-clock"></i> <?= (int)$plan['total_duration'] ?> min total</span>
            <?php endif; ?>
            <?php if ($creatorName): ?>
            <span class="m-plan-hero-tag"><i class="fas fa-user"></i> <?= htmlspecialchars($creatorName) ?></span>
            <?php endif; ?>
            <span class="m-plan-hero-tag"><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($plan['created_at'])) ?></span>
        </div>
        <?php if (!empty($plan['description'])): ?>
        <p class="m-plan-hero-notes"><?= htmlspecialchars($plan['description']) ?></p>
        <?php endif; ?>
        <?php if (!empty($plan['notes'])): ?>
        <p class="m-plan-hero-notes" style="margin-top:8px;"><?= htmlspecialchars($plan['notes']) ?></p>
        <?php endif; ?>
    </div>

    <div class="m-section">
        <h3 class="m-section-title">Drills (<?= count($planDrills) ?>)</h3>
        <?php if (empty($planDrills)): ?>
            <div class="m-empty-state">
                <i class="fas fa-hockey-puck"></i>
                No drills added to this plan
            </div>
        <?php else: ?>
            <?php foreach ($planDrills as $i => $pd):
                $diff = strtolower($pd['difficulty'] ?? '');
                $badgeClass = match($diff) {
                    'easy', 'beginner' => 'easy',
                    'medium', 'intermediate' => 'medium',
                    'hard', 'advanced' => 'hard',
                    default => 'default',
                };
                $drillTitle = $pd['drill_title'] ?? 'Untitled Drill';
                $drillDesc = $pd['drill_description'] ?? '';
                $categoryName = $pd['category_name'] ?? '';
            ?>
            <a href="?page=view_drill&id=<?= (int)($pd['drill_id'] ?? 0) ?>" class="m-plan-drill-item">
                <?php
                $pdImgUrl = '';
                if (!empty($pd['drill_image'])) {
                    $pdImgUrl = resolveRustfsUrl($pdo, $pd['drill_image']);
                } elseif (!empty($pd['drill_thumbnail'])) {
                    $pdImgUrl = resolveRustfsUrl($pdo, $pd['drill_thumbnail']);
                }
                if ($pdImgUrl): ?>
                <img class="m-plan-drill-thumb" src="<?= htmlspecialchars($pdImgUrl) ?>" alt="<?= htmlspecialchars($drillTitle) ?>" loading="lazy" onerror="this.style.display='none'">
                <?php endif; ?>
                <div class="m-plan-drill-content">
                    <div class="m-plan-drill-num"><?= $i + 1 ?></div>
                    <div class="m-plan-drill-body">
                        <div class="m-plan-drill-title"><?= htmlspecialchars($drillTitle) ?></div>
                        <div class="m-plan-drill-meta">
                            <?php if ($pd['duration_minutes'] ?? null): ?>
                            <span><i class="fas fa-clock"></i> <?= (int)$pd['duration_minutes'] ?>min</span>
                            <?php endif; ?>
                            <?php if (!empty($categoryName)): ?>
                            <span style="font-size:10px;padding:2px 8px;border-radius:6px;background:rgba(107,70,193,0.12);color:#8B5CF6;"><?= htmlspecialchars($categoryName) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($drillDesc)): ?>
                        <div style="font-size:11px;color:#6B6B7B;margin-top:4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?= htmlspecialchars($drillDesc) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($pd['notes'])): ?>
                        <div style="font-size:11px;color:#A8A8B8;margin-top:4px;font-style:italic;"><i class="fas fa-comment" style="font-size:9px;"></i> <?= htmlspecialchars($pd['notes']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($diff): ?>
                    <span class="m-plan-drill-badge m-plan-drill-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($diff)) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
