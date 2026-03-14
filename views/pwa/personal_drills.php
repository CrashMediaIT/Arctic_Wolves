<?php
/**
 * PWA Personal Drills - Mobile-native personal drill management
 * Shows drills created by the current user
 */
require_once __DIR__ . '/../../lib/image_helper.php';

$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['user_role'] ?? 'athlete';
$is_admin = ($user_role === 'admin');

$personal_drills = [];
try {
    if ($is_admin) {
        $personal_drills = $pdo->query("
            SELECT pd.*, u.first_name, u.last_name
            FROM personal_drills pd
            JOIN users u ON pd.created_by = u.id
            ORDER BY pd.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("
            SELECT pd.*, u.first_name, u.last_name
            FROM personal_drills pd
            JOIN users u ON pd.created_by = u.id
            WHERE pd.created_by = ?
            ORDER BY pd.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $personal_drills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (function_exists('decryptUserRows')) { $personal_drills = decryptUserRows($personal_drills); }
    if (class_exists('FieldEncryption')) { $personal_drills = FieldEncryption::decryptRows($personal_drills, ['first_name', 'last_name']); }
} catch (PDOException $e) { $personal_drills = []; }
?>
<style>
.m-pdrills { padding: 16px; font-family: Inter, sans-serif; }
.m-pdrills-header { margin-bottom: 16px; }
.m-pdrills-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-pdrills-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-pdrills-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 14px;
    overflow: hidden; margin-bottom: 12px;
}
.m-pdrills-card-thumb {
    width: 100%; height: 160px; object-fit: cover; display: block;
    background: #1E1E2E;
}
.m-pdrills-card-body { padding: 14px 16px; }
.m-pdrills-card-title { font-size: 15px; font-weight: 600; color: #fff; margin-bottom: 4px; }
.m-pdrills-card-desc { font-size: 12px; color: #A8A8B8; line-height: 1.5; margin-bottom: 8px; }
.m-pdrills-card-meta { display: flex; align-items: center; gap: 10px; font-size: 11px; color: #6B6B7B; }
.m-pdrills-card-meta span { display: flex; align-items: center; gap: 4px; }
.m-pdrills-nav { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.m-pdrills-nav-link {
    padding: 8px 14px; background: #16161F; border: 1px solid #2D2D3F;
    border-radius: 10px; color: #A8A8B8; font-size: 12px; font-weight: 600;
    text-decoration: none; display: flex; align-items: center; gap: 6px;
    min-height: 36px;
}
.m-pdrills-nav-link:active { border-color: #6B46C1; color: #8B5CF6; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
</style>

<div class="m-pdrills">
    <div class="m-pdrills-header">
        <h2 class="m-pdrills-title"><i class="fas fa-user-pen" style="color:#8B5CF6;margin-right:6px;"></i> Personal Drills</h2>
        <p class="m-pdrills-sub"><?= count($personal_drills) ?> drill<?= count($personal_drills) !== 1 ? 's' : '' ?> created</p>
    </div>

    <div class="m-pdrills-nav">
        <a href="?page=drills" class="m-pdrills-nav-link"><i class="fas fa-book"></i> Drill Library</a>
        <a href="?page=create_drill" class="m-pdrills-nav-link"><i class="fas fa-plus-circle"></i> Create Drill</a>
    </div>

    <?php if (empty($personal_drills)): ?>
    <div class="m-empty-state">
        <i class="fas fa-clipboard-list"></i>
        <p>No personal drills yet. Create your first drill!</p>
    </div>
    <?php else: ?>
    <?php foreach ($personal_drills as $pd):
        $thumb = '';
        if (!empty($pd['video_url'])) {
            $thumb = htmlspecialchars($pd['video_url']);
        } elseif (!empty($pd['custom_image'])) {
            $thumb = htmlspecialchars($pd['custom_image']);
        }
        $creator = trim(($pd['first_name'] ?? '') . ' ' . ($pd['last_name'] ?? ''));
    ?>
    <div class="m-pdrills-card">
        <?php if ($thumb): ?>
        <img class="m-pdrills-card-thumb" src="<?= $thumb ?>" alt="" loading="lazy" onerror="this.style.display='none'">
        <?php endif; ?>
        <div class="m-pdrills-card-body">
            <div class="m-pdrills-card-title"><?= htmlspecialchars($pd['title'] ?? 'Untitled') ?></div>
            <?php if (!empty($pd['description'])): ?>
            <div class="m-pdrills-card-desc"><?= htmlspecialchars(mb_strimwidth($pd['description'], 0, 150, '...')) ?></div>
            <?php endif; ?>
            <div class="m-pdrills-card-meta">
                <?php if ($creator): ?>
                <span><i class="fas fa-user"></i> <?= htmlspecialchars($creator) ?></span>
                <?php endif; ?>
                <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($pd['created_at'])) ?></span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
