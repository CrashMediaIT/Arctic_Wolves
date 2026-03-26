<?php
/**
 * PWA View Drill - Mobile-native drill detail view
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

$drillId = (int)($_GET['id'] ?? 0);
$drill = null;
$steps = [];

if ($drillId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT d.*, u.first_name, u.last_name
            FROM drills d
            LEFT JOIN users u ON u.id = d.created_by
            WHERE d.id = ?
        ");
        $stmt->execute([$drillId]);
        $drill = $stmt->fetch(PDO::FETCH_ASSOC);
        $drill = decryptUserRow($drill);
    } catch (PDOException $e) { $drill = null; }

    if ($drill) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM drill_steps WHERE drill_id = ? ORDER BY step_order ASC");
            $stmt->execute([$drillId]);
            $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { $steps = []; }
    }
}

if (!$drill):
?>
<style>
.m-not-found { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; color: #6B6B7B; font-family: Inter, sans-serif; text-align: center; }
.m-not-found i { font-size: 48px; margin-bottom: 16px; }
.m-not-found p { font-size: 15px; margin: 0 0 16px; }
.m-not-found a { color: #8B5CF6; text-decoration: none; font-size: 14px; font-weight: 600; }
</style>
<div class="m-not-found">
    <i class="fas fa-hockey-puck"></i>
    <p>Drill not found</p>
    <a href="?page=drills"><i class="fas fa-arrow-left"></i> Back to Drills</a>
</div>
<?php
    return;
endif;

$diff = strtolower($drill['difficulty'] ?? '');
$badgeClass = match($diff) {
    'easy', 'beginner' => 'easy',
    'medium', 'intermediate' => 'medium',
    'hard', 'advanced' => 'hard',
    default => 'default',
};
$creatorName = trim(($drill['first_name'] ?? '') . ' ' . ($drill['last_name'] ?? ''));

// Get category name
$categoryName = '';
if (!empty($drill['category_id'])) {
    try {
        $catStmt = $pdo->prepare("SELECT name FROM drill_categories WHERE id = ?");
        $catStmt->execute([$drill['category_id']]);
        $categoryName = $catStmt->fetchColumn() ?: '';
    } catch (PDOException $e) { /* ignore */ }
}
?>
<style>
.m-drill-detail { padding: 16px; font-family: Inter, sans-serif; }
.m-back-link {
    display: inline-flex; align-items: center; gap: 6px;
    color: #8B5CF6; text-decoration: none; font-size: 13px; font-weight: 600;
    margin-bottom: 16px; min-height: 44px; padding: 8px 0;
}
.m-drill-hero {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 16px;
    padding: 20px; margin-bottom: 16px;
}
.m-drill-hero-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
.m-drill-hero-title { font-size: 18px; font-weight: 700; color: #fff; flex: 1; margin-right: 8px; }
.m-drill-hero-badge {
    font-size: 11px; padding: 4px 10px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-drill-hero-badge-easy { background: rgba(16,185,129,0.15); color: #10B981; }
.m-drill-hero-badge-medium { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-drill-hero-badge-hard { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-drill-hero-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-drill-hero-desc { font-size: 13px; color: #A8A8B8; margin: 0 0 14px; line-height: 1.5; }
.m-drill-hero-meta { display: flex; flex-wrap: wrap; gap: 12px; }
.m-drill-hero-tag {
    font-size: 11px; display: flex; align-items: center; gap: 4px; color: #6B6B7B;
}
.m-drill-hero-tag i { font-size: 12px; }
.m-drill-category-tag {
    font-size: 10px; padding: 3px 8px; border-radius: 6px;
    background: rgba(107,70,193,0.12); color: #8B5CF6; font-weight: 500;
}
.m-section { margin-bottom: 20px; }
.m-section-title {
    font-size: 13px; font-weight: 600; color: #6B6B7B;
    text-transform: uppercase; letter-spacing: 0.5px;
    margin: 0 0 10px; padding: 0 4px;
}
.m-step-item {
    display: flex; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-step-num {
    width: 32px; height: 32px; border-radius: 50%;
    background: rgba(107,70,193,0.2); color: #8B5CF6;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; flex-shrink: 0;
}
.m-step-body { flex: 1; min-width: 0; }
.m-step-title { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 4px; }
.m-step-desc { font-size: 12px; color: #A8A8B8; line-height: 1.4; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-drill-detail">
    <a href="?page=drills" class="m-back-link"><i class="fas fa-arrow-left"></i> Back to Drills</a>

    <div class="m-drill-hero">
        <div class="m-drill-hero-top">
            <span class="m-drill-hero-title"><?= htmlspecialchars($drill['title']) ?></span>
            <?php if ($diff): ?>
            <span class="m-drill-hero-badge m-drill-hero-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($diff)) ?></span>
            <?php endif; ?>
        </div>
        <?php if (!empty($drill['description'])): ?>
        <p class="m-drill-hero-desc"><?= htmlspecialchars($drill['description']) ?></p>
        <?php endif; ?>
        <div class="m-drill-hero-meta">
            <?php if ($drill['duration_minutes'] ?? null): ?>
            <span class="m-drill-hero-tag"><i class="fas fa-clock"></i> <?= (int)$drill['duration_minutes'] ?> min</span>
            <?php endif; ?>
            <?php if ($creatorName): ?>
            <span class="m-drill-hero-tag"><i class="fas fa-user"></i> <?= htmlspecialchars($creatorName) ?></span>
            <?php endif; ?>
            <?php if (!empty($categoryName)): ?>
            <span class="m-drill-category-tag"><?= htmlspecialchars($categoryName) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $drillImageUrl = '';
    if (!empty($drill['custom_image'])) {
        $drillImageUrl = resolveRustfsUrl($pdo, $drill['custom_image']);
    } elseif (!empty($drill['thumbnail_path'])) {
        $drillImageUrl = resolveRustfsUrl($pdo, $drill['thumbnail_path']);
    }
    if ($drillImageUrl): ?>
    <div class="m-section">
        <h3 class="m-section-title">Diagram</h3>
        <div style="background:#16161F;border:1px solid #2D2D3F;border-radius:12px;overflow:hidden;">
            <img src="<?= htmlspecialchars($drillImageUrl) ?>" alt="<?= htmlspecialchars($drill['title']) ?> diagram" style="width:100%;display:block;border-radius:12px;" loading="lazy" onerror="this.parentElement.parentElement.style.display='none'">
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($drill['video_url'])): ?>
    <div class="m-section">
        <h3 class="m-section-title">Video</h3>
        <div style="background:#16161F;border:1px solid #2D2D3F;border-radius:12px;padding:14px;">
            <?php
            $videoUrl = $drill['video_url'];
            $embedUrl = '';
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $videoUrl, $ytMatch)) {
                $embedUrl = 'https://www.youtube.com/embed/' . $ytMatch[1];
            } elseif (preg_match('/vimeo\.com\/(\d+)/', $videoUrl, $vmMatch)) {
                $embedUrl = 'https://player.vimeo.com/video/' . $vmMatch[1];
            }
            if ($embedUrl): ?>
            <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:8px;">
                <iframe src="<?= htmlspecialchars($embedUrl) ?>" style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;" allowfullscreen loading="lazy"></iframe>
            </div>
            <?php else: ?>
            <a href="<?= htmlspecialchars($videoUrl) ?>" target="_blank" rel="noopener" style="color:#8B5CF6;text-decoration:none;font-size:13px;display:flex;align-items:center;gap:6px;">
                <i class="fas fa-external-link-alt"></i> Watch Video
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($drill['setup'])): ?>
    <div class="m-section">
        <h3 class="m-section-title">Setup</h3>
        <div style="background:#16161F;border:1px solid #2D2D3F;border-radius:12px;padding:14px;">
            <p style="font-size:13px;color:#A8A8B8;line-height:1.5;margin:0;"><?= nl2br(htmlspecialchars($drill['setup'])) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($drill['coaching_points'])): ?>
    <div class="m-section">
        <h3 class="m-section-title">Coaching Points</h3>
        <div style="background:#16161F;border:1px solid #2D2D3F;border-radius:12px;padding:14px;">
            <p style="font-size:13px;color:#A8A8B8;line-height:1.5;margin:0;"><?= nl2br(htmlspecialchars($drill['coaching_points'])) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($drill['progression'])): ?>
    <div class="m-section">
        <h3 class="m-section-title">Progression</h3>
        <div style="background:#16161F;border:1px solid #2D2D3F;border-radius:12px;padding:14px;">
            <p style="font-size:13px;color:#A8A8B8;line-height:1.5;margin:0;"><?= nl2br(htmlspecialchars($drill['progression'])) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <div class="m-section">
        <h3 class="m-section-title">Steps</h3>
        <?php if (empty($steps)): ?>
            <div class="m-empty-state">
                <i class="fas fa-list-ol"></i>
                No steps defined for this drill
            </div>
        <?php else: ?>
            <?php foreach ($steps as $i => $step): ?>
            <div class="m-step-item">
                <div class="m-step-num"><?= $i + 1 ?></div>
                <div class="m-step-body">
                    <?php if (!empty($step['title'])): ?>
                    <div class="m-step-title"><?= htmlspecialchars($step['title']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($step['description'])): ?>
                    <div class="m-step-desc"><?= htmlspecialchars($step['description']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
