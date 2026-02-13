<?php
/**
 * PWA Drills - Mobile-native drill library for coaches
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

$myDrills = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, title, description, difficulty, duration_minutes, category, created_at
        FROM drills
        WHERE created_by = ?
        ORDER BY created_at DESC
        LIMIT 30
    ");
    $stmt->execute([$user_id]);
    $myDrills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $myDrills = []; }

$libraryDrills = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, title, description, difficulty, duration_minutes, category
        FROM drills
        WHERE is_public = 1
        ORDER BY title ASC
        LIMIT 30
    ");
    $stmt->execute();
    $libraryDrills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $libraryDrills = []; }
?>
<style>
.m-drills { padding: 0; font-family: Inter, sans-serif; }
.m-tabs {
    display: flex; position: sticky; top: 0; z-index: 10;
    background: #0A0A0F; border-bottom: 1px solid #2D2D3F;
    padding: 0 16px;
}
.m-tab {
    flex: 1; text-align: center; padding: 14px 0; font-size: 13px; font-weight: 600;
    color: #6B6B7B; border: none; background: none; cursor: pointer;
    border-bottom: 2px solid transparent;
    min-height: 44px; font-family: Inter, sans-serif;
}
.m-tab.m-tab-active { color: #8B5CF6; border-bottom-color: #8B5CF6; }
.m-tab-panel { display: none; padding: 16px; }
.m-tab-panel.m-tab-visible { display: block; }
.m-drill-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
    text-decoration: none; display: block; min-height: 44px;
}
.m-drill-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-drill-title { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-drill-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-drill-badge-easy { background: rgba(16,185,129,0.15); color: #10B981; }
.m-drill-badge-medium { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-drill-badge-hard { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-drill-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-drill-desc {
    font-size: 12px; color: #A8A8B8; margin: 0 0 10px;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.m-drill-footer { display: flex; gap: 12px; align-items: center; }
.m-drill-meta { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-drill-tag {
    font-size: 10px; padding: 2px 8px; border-radius: 6px;
    background: rgba(107,70,193,0.12); color: #8B5CF6; font-weight: 500;
}
.m-fab {
    position: fixed; bottom: 80px; right: 20px; z-index: 50;
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    color: #fff; font-size: 22px;
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; box-shadow: 0 4px 16px rgba(107,70,193,0.4);
    border: none; cursor: pointer;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-drills">
    <div class="m-tabs">
        <button class="m-tab m-tab-active" onclick="mDrillTab('mine', this)" type="button">My Drills</button>
        <button class="m-tab" onclick="mDrillTab('library', this)" type="button">Library</button>
    </div>

    <!-- My Drills Tab -->
    <div class="m-tab-panel m-tab-visible" id="m-panel-mine">
        <?php if (empty($myDrills)): ?>
            <div class="m-empty-state">
                <i class="fas fa-hockey-puck"></i>
                <p>No drills created yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($myDrills as $d):
                $diff = strtolower($d['difficulty'] ?? '');
                $badgeClass = match($diff) {
                    'easy', 'beginner' => 'easy',
                    'medium', 'intermediate' => 'medium',
                    'hard', 'advanced' => 'hard',
                    default => 'default',
                };
            ?>
            <a href="?page=view_drill&id=<?= (int)$d['id'] ?>" class="m-drill-card">
                <div class="m-drill-top">
                    <span class="m-drill-title"><?= htmlspecialchars($d['title']) ?></span>
                    <?php if ($diff): ?>
                    <span class="m-drill-badge m-drill-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($diff)) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($d['description'])): ?>
                <p class="m-drill-desc"><?= htmlspecialchars($d['description']) ?></p>
                <?php endif; ?>
                <div class="m-drill-footer">
                    <?php if ($d['duration_minutes']): ?>
                    <span class="m-drill-meta"><i class="fas fa-clock"></i> <?= (int)$d['duration_minutes'] ?>min</span>
                    <?php endif; ?>
                    <?php if (!empty($d['category'])): ?>
                    <span class="m-drill-tag"><?= htmlspecialchars($d['category']) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Library Tab -->
    <div class="m-tab-panel" id="m-panel-library">
        <?php if (empty($libraryDrills)): ?>
            <div class="m-empty-state">
                <i class="fas fa-book-open"></i>
                <p>No public drills available</p>
            </div>
        <?php else: ?>
            <?php foreach ($libraryDrills as $d):
                $diff = strtolower($d['difficulty'] ?? '');
                $badgeClass = match($diff) {
                    'easy', 'beginner' => 'easy',
                    'medium', 'intermediate' => 'medium',
                    'hard', 'advanced' => 'hard',
                    default => 'default',
                };
            ?>
            <a href="?page=view_drill&id=<?= (int)$d['id'] ?>" class="m-drill-card">
                <div class="m-drill-top">
                    <span class="m-drill-title"><?= htmlspecialchars($d['title']) ?></span>
                    <?php if ($diff): ?>
                    <span class="m-drill-badge m-drill-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($diff)) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($d['description'])): ?>
                <p class="m-drill-desc"><?= htmlspecialchars($d['description']) ?></p>
                <?php endif; ?>
                <div class="m-drill-footer">
                    <?php if ($d['duration_minutes']): ?>
                    <span class="m-drill-meta"><i class="fas fa-clock"></i> <?= (int)$d['duration_minutes'] ?>min</span>
                    <?php endif; ?>
                    <?php if (!empty($d['category'])): ?>
                    <span class="m-drill-tag"><?= htmlspecialchars($d['category']) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <a href="?page=create_drill" class="m-fab" title="Create Drill"><i class="fas fa-plus"></i></a>
</div>

<script>
function mDrillTab(tabId, btn) {
    document.querySelectorAll('.m-tab-panel').forEach(function(p) { p.classList.remove('m-tab-visible'); });
    document.querySelectorAll('.m-tab').forEach(function(t) { t.classList.remove('m-tab-active'); });
    var panel = document.getElementById('m-panel-' + tabId);
    if (panel) panel.classList.add('m-tab-visible');
    if (btn) btn.classList.add('m-tab-active');
}
</script>
