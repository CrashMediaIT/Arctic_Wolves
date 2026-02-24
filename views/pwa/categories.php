<?php
/**
 * PWA Categories - Mobile-native resource management
 * All 8 resource sections in collapsible accordion layout.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

// 1. Skills
$skills = [];
try {
    $stmt = $pdo->prepare("SELECT es.id, es.name, es.description, GROUP_CONCAT(ec.name ORDER BY ec.name SEPARATOR ', ') as category_name FROM eval_skills es LEFT JOIN eval_skill_categories esc ON es.id = esc.skill_id LEFT JOIN eval_categories ec ON esc.category_id = ec.id GROUP BY es.id, es.name, es.description ORDER BY es.name ASC");
    $stmt->execute();
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $skills = []; }

// 2. Drill Types
$drillTypes = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description, position_type FROM drill_categories ORDER BY name ASC");
    $stmt->execute();
    $drillTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $drillTypes = []; }

// 3. Merchandise Categories
$merchCats = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description, is_active FROM merchandise_categories ORDER BY display_order, name ASC");
    $stmt->execute();
    $merchCats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $merchCats = []; }

// 4. Teams
$teams = [];
try {
    $stmt = $pdo->prepare("SELECT t.id, t.name, t.age_group, t.skill_level, t.division, t.is_active, c.first_name as coach_first_name, c.last_name as coach_last_name, a.first_name as asst_first_name, a.last_name as asst_last_name FROM teams t LEFT JOIN users c ON c.id=t.coach_id LEFT JOIN users a ON a.id=t.assistant_coach_id ORDER BY t.is_active DESC, t.name ASC");
    $stmt->execute();
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $teams = decryptUserRows($teams);
    foreach ($teams as &$t) {
        $t['coach_name'] = trim(($t['coach_first_name'] ?? '') . ' ' . ($t['coach_last_name'] ?? ''));
        $t['asst_name'] = trim(($t['asst_first_name'] ?? '') . ' ' . ($t['asst_last_name'] ?? ''));
    }
    unset($t);
} catch (PDOException $e) { $teams = []; }

// 5. Locations
$locations = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, city, address, province, postal_code, phone, is_active FROM locations ORDER BY city, name");
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $locations = []; }

// 6. Skill Levels
$skillLevels = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description, display_order FROM skill_levels ORDER BY display_order ASC, name ASC");
    $stmt->execute();
    $skillLevels = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $skillLevels = []; }

// 7. Seasons
$seasons = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, start_date, end_date, is_active FROM seasons ORDER BY is_active DESC, start_date DESC");
    $stmt->execute();
    $seasons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $seasons = []; }

// 8. Age Groups
$ageGroups = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description, min_age, max_age, display_order FROM age_groups ORDER BY display_order ASC, name ASC");
    $stmt->execute();
    $ageGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $ageGroups = []; }

$totalItems = count($skills) + count($drillTypes) + count($merchCats) + count($teams) + count($locations) + count($skillLevels) + count($seasons) + count($ageGroups);
?>
<style>
.m-cats { padding: 16px; font-family: Inter, sans-serif; padding-bottom: 100px; }
.m-cats-header { margin-bottom: 16px; }
.m-cats-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-cats-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-cat-alert {
    padding: 10px 12px; border-radius: 10px; margin-bottom: 12px; font-size: 12px;
    background: rgba(16,185,129,0.15); color: #10B981; border: 1px solid rgba(16,185,129,0.3);
}

/* Accordion */
.m-acc { margin-bottom: 8px; border-radius: 12px; overflow: hidden; border: 1px solid #2D2D3F; }
.m-acc-head {
    display: flex; align-items: center; gap: 10px; padding: 14px; min-height: 44px;
    background: #16161F; cursor: pointer; -webkit-tap-highlight-color: transparent; user-select: none;
}
.m-acc-head:active { background: #1E1E2E; }
.m-acc-icon {
    width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center;
    justify-content: center; font-size: 13px; flex-shrink: 0;
}
.m-acc-label { flex: 1; font-size: 14px; font-weight: 600; color: #fff; }
.m-acc-badge {
    background: rgba(107,70,193,0.2); color: #8B5CF6; font-size: 11px; font-weight: 700;
    padding: 2px 8px; border-radius: 10px; min-width: 20px; text-align: center;
}
.m-acc-chevron { color: #6B6B7B; font-size: 12px; transition: transform 0.2s; flex-shrink: 0; width: 20px; text-align: center; }
.m-acc.m-open .m-acc-chevron { transform: rotate(180deg); }
.m-acc-body { display: none; padding: 0 14px 14px; background: #16161F; }
.m-acc.m-open .m-acc-body { display: block; }

/* Cards */
.m-cat-card {
    display: flex; align-items: center; gap: 12px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    padding: 12px; margin-bottom: 6px; min-height: 44px;
}
.m-cat-body { flex: 1; min-width: 0; }
.m-cat-name { font-size: 13px; font-weight: 600; color: #fff; }
.m-cat-desc { font-size: 11px; color: #A8A8B8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-cat-meta { font-size: 11px; color: #6B6B7B; margin-top: 2px; }
.m-cat-actions { display: flex; gap: 4px; flex-shrink: 0; }
.m-cat-action-btn {
    width: 34px; height: 34px; border-radius: 8px; border: 1px solid #2D2D3F;
    background: transparent; color: #A8A8B8; display: flex; align-items: center;
    justify-content: center; cursor: pointer; font-size: 12px;
}
.m-cat-action-btn:active { background: #2D2D3F; }
.m-cat-action-btn.m-del { color: #EF4444; border-color: rgba(239,68,68,0.3); }
.m-cat-badge-active {
    font-size: 10px; font-weight: 600; padding: 2px 6px; border-radius: 6px;
    background: rgba(16,185,129,0.15); color: #10B981; border: 1px solid rgba(16,185,129,0.3);
}
.m-cat-badge-inactive {
    font-size: 10px; font-weight: 600; padding: 2px 6px; border-radius: 6px;
    background: rgba(239,68,68,0.1); color: #EF4444; border: 1px solid rgba(239,68,68,0.2);
}
.m-cat-readonly-note {
    font-size: 11px; color: #6B6B7B; padding: 8px 0 4px; font-style: italic;
}
.m-season-btn {
    padding: 6px 12px; border-radius: 8px; border: 1px solid #2D2D3F; background: #0A0A0F;
    color: #8B5CF6; font-size: 11px; font-weight: 600; cursor: pointer; min-height: 34px;
    font-family: Inter, sans-serif;
}
.m-season-btn:active { background: #2D2D3F; }

/* Empty */
.m-empty-small { text-align: center; padding: 20px 10px; color: #6B6B7B; font-size: 12px; }

/* FAB */
.m-cat-fab {
    position: fixed; bottom: 80px; right: 16px; width: 52px; height: 52px;
    border-radius: 14px; background: #6B46C1; color: #fff; border: none;
    font-size: 20px; cursor: pointer; z-index: 100;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(107,70,193,0.4);
}
.m-cat-fab:active { background: #5B3AAF; }

/* Bottom Sheet */
.m-cat-overlay {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6); z-index: 9998;
}
.m-cat-overlay.m-active { display: block; }
.m-cat-sheet {
    display: none; position: fixed; left: 0; right: 0; bottom: 0;
    background: #16161F; border-radius: 16px 16px 0 0; z-index: 9999;
    max-height: 85vh; overflow-y: auto; padding: 20px 16px 32px;
}
.m-cat-sheet.m-active { display: block; }
.m-cat-sheet-handle {
    width: 36px; height: 4px; border-radius: 2px; background: #3D3D4F;
    margin: 0 auto 16px;
}
.m-cat-sheet-title {
    font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 16px;
    display: flex; align-items: center; justify-content: space-between;
}
.m-cat-sheet-close {
    background: none; border: none; color: #A8A8B8; font-size: 22px; cursor: pointer;
    width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;
}
.m-cat-form-group { margin-bottom: 12px; }
.m-cat-form-label { font-size: 12px; font-weight: 600; color: #A8A8B8; margin-bottom: 4px; display: block; }
.m-cat-form-input {
    width: 100%; background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; padding: 12px; min-height: 44px; font-size: 14px;
    font-family: Inter, sans-serif; box-sizing: border-box;
}
.m-cat-form-input:focus { outline: none; border-color: #6B46C1; }
.m-cat-form-select {
    width: 100%; background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; padding: 12px; min-height: 44px; font-size: 14px;
    font-family: Inter, sans-serif; box-sizing: border-box; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236B6B7B' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 12px center;
}
.m-cat-form-select:focus { outline: none; border-color: #6B46C1; }
.m-cat-submit {
    width: 100%; padding: 12px; background: #6B46C1; color: #fff; border: none;
    border-radius: 10px; font-size: 14px; font-weight: 600; min-height: 44px;
    cursor: pointer; margin-top: 8px; font-family: Inter, sans-serif;
}
.m-cat-submit:active { background: #5B3AAF; }
</style>

<div class="m-cats">
    <div class="m-cats-header">
        <h2 class="m-cats-title">Resource Management</h2>
        <p class="m-cats-sub"><?= $totalItems ?> total items across 8 sections</p>
    </div>

    <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <div class="m-cat-alert"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['message'] ?? 'Operation completed!') ?></div>
    <?php endif; ?>

    <!-- 1. Skills -->
    <div class="m-acc m-open" data-section="skill">
        <div class="m-acc-head" onclick="mAccToggle(this)">
            <div class="m-acc-icon" style="background:rgba(107,70,193,0.15);color:#8B5CF6;"><i class="fas fa-star"></i></div>
            <span class="m-acc-label">Skills</span>
            <span class="m-acc-badge"><?= count($skills) ?></span>
            <span class="m-acc-chevron"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="m-acc-body">
            <?php if (empty($skills)): ?>
                <div class="m-empty-small"><i class="fas fa-star"></i> No skills found</div>
            <?php else: ?>
                <?php foreach ($skills as $item): ?>
                <div class="m-cat-card">
                    <div class="m-cat-body">
                        <div class="m-cat-name"><?= htmlspecialchars($item['name'] ?? '') ?></div>
                        <?php if (!empty($item['description'])): ?>
                        <div class="m-cat-desc"><?= htmlspecialchars($item['description']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($item['category_name'])): ?>
                        <div class="m-cat-meta"><?= htmlspecialchars($item['category_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="m-cat-actions">
                        <button class="m-cat-action-btn" onclick='mResEdit("skill",<?= json_encode($item, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="m-cat-action-btn m-del" onclick='mResDel("skill",<?= (int)$item["id"] ?>,<?= json_encode($item["name"], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)' title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. Drill Types -->
    <div class="m-acc" data-section="drill_type">
        <div class="m-acc-head" onclick="mAccToggle(this)">
            <div class="m-acc-icon" style="background:rgba(59,130,246,0.15);color:#3B82F6;"><i class="fas fa-hockey-puck"></i></div>
            <span class="m-acc-label">Drill Types</span>
            <span class="m-acc-badge"><?= count($drillTypes) ?></span>
            <span class="m-acc-chevron"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="m-acc-body">
            <?php if (empty($drillTypes)): ?>
                <div class="m-empty-small"><i class="fas fa-hockey-puck"></i> No drill types found</div>
            <?php else: ?>
                <?php foreach ($drillTypes as $item): ?>
                <div class="m-cat-card">
                    <div class="m-cat-body">
                        <div class="m-cat-name"><?= htmlspecialchars($item['name'] ?? '') ?></div>
                        <?php if (!empty($item['description'])): ?>
                        <div class="m-cat-desc"><?= htmlspecialchars($item['description']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($item['position_type'])): ?>
                        <div class="m-cat-meta"><?= htmlspecialchars(ucfirst($item['position_type'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="m-cat-actions">
                        <button class="m-cat-action-btn" onclick='mResEdit("drill_type",<?= json_encode($item, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="m-cat-action-btn m-del" onclick='mResDel("drill_type",<?= (int)$item["id"] ?>,<?= json_encode($item["name"], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)' title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. Merchandise Categories -->
    <div class="m-acc" data-section="merchandise">
        <div class="m-acc-head" onclick="mAccToggle(this)">
            <div class="m-acc-icon" style="background:rgba(245,158,11,0.15);color:#F59E0B;"><i class="fas fa-shopping-bag"></i></div>
            <span class="m-acc-label">Merchandise Categories</span>
            <span class="m-acc-badge"><?= count($merchCats) ?></span>
            <span class="m-acc-chevron"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="m-acc-body">
            <?php if (empty($merchCats)): ?>
                <div class="m-empty-small"><i class="fas fa-shopping-bag"></i> No merchandise categories found</div>
            <?php else: ?>
                <?php foreach ($merchCats as $item): ?>
                <div class="m-cat-card">
                    <div class="m-cat-body">
                        <div class="m-cat-name"><?= htmlspecialchars($item['name'] ?? '') ?></div>
                        <?php if (!empty($item['description'])): ?>
                        <div class="m-cat-desc"><?= htmlspecialchars($item['description']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="m-cat-actions">
                        <?php if (!empty($item['is_active'])): ?>
                            <span class="m-cat-badge-active">Active</span>
                        <?php else: ?>
                            <span class="m-cat-badge-inactive">Inactive</span>
                        <?php endif; ?>
                        <button class="m-cat-action-btn" onclick='mResEdit("merchandise",<?= json_encode($item, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="m-cat-action-btn m-del" onclick='mResDel("merchandise",<?= (int)$item["id"] ?>,<?= json_encode($item["name"], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)' title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4. Teams (read-only) -->
    <div class="m-acc" data-section="team">
        <div class="m-acc-head" onclick="mAccToggle(this)">
            <div class="m-acc-icon" style="background:rgba(16,185,129,0.15);color:#10B981;"><i class="fas fa-users"></i></div>
            <span class="m-acc-label">Teams</span>
            <span class="m-acc-badge"><?= count($teams) ?></span>
            <span class="m-acc-chevron"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="m-acc-body">
            <div class="m-cat-readonly-note"><i class="fas fa-info-circle"></i> View only — use desktop for full team management</div>
            <?php if (empty($teams)): ?>
                <div class="m-empty-small"><i class="fas fa-users"></i> No teams found</div>
            <?php else: ?>
                <?php foreach ($teams as $item): ?>
                <div class="m-cat-card">
                    <div class="m-cat-body">
                        <div class="m-cat-name"><?= htmlspecialchars($item['name'] ?? '') ?></div>
                        <div class="m-cat-meta">
                            <?php
                            $meta = [];
                            if (!empty($item['age_group'])) $meta[] = htmlspecialchars($item['age_group']);
                            if (!empty($item['division'])) $meta[] = htmlspecialchars($item['division']);
                            if (!empty($item['coach_name']) && trim($item['coach_name'])) $meta[] = 'Coach: ' . htmlspecialchars(trim($item['coach_name']));
                            echo implode(' · ', $meta);
                            ?>
                        </div>
                    </div>
                    <?php if (!empty($item['is_active'])): ?>
                        <span class="m-cat-badge-active">Active</span>
                    <?php else: ?>
                        <span class="m-cat-badge-inactive">Inactive</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 5. Locations -->
    <div class="m-acc" data-section="location">
        <div class="m-acc-head" onclick="mAccToggle(this)">
            <div class="m-acc-icon" style="background:rgba(239,68,68,0.15);color:#EF4444;"><i class="fas fa-map-marker-alt"></i></div>
            <span class="m-acc-label">Locations</span>
            <span class="m-acc-badge"><?= count($locations) ?></span>
            <span class="m-acc-chevron"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="m-acc-body">
            <?php if (empty($locations)): ?>
                <div class="m-empty-small"><i class="fas fa-map-marker-alt"></i> No locations found</div>
            <?php else: ?>
                <?php foreach ($locations as $item): ?>
                <div class="m-cat-card">
                    <div class="m-cat-body">
                        <div class="m-cat-name"><?= htmlspecialchars($item['name'] ?? '') ?></div>
                        <div class="m-cat-desc">
                            <?php
                            $parts = [];
                            if (!empty($item['address'])) $parts[] = htmlspecialchars($item['address']);
                            if (!empty($item['city'])) $parts[] = htmlspecialchars($item['city']);
                            if (!empty($item['province'])) $parts[] = htmlspecialchars($item['province']);
                            if (!empty($item['postal_code'])) $parts[] = htmlspecialchars($item['postal_code']);
                            echo implode(', ', $parts);
                            ?>
                        </div>
                        <?php if (!empty($item['phone'])): ?>
                        <div class="m-cat-meta"><i class="fas fa-phone"></i> <?= htmlspecialchars($item['phone']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="m-cat-actions">
                        <?php if (isset($item['is_active'])): ?>
                            <?php if (!empty($item['is_active'])): ?>
                                <span class="m-cat-badge-active">Active</span>
                            <?php else: ?>
                                <span class="m-cat-badge-inactive">Inactive</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <button class="m-cat-action-btn" onclick='mResEdit("location",<?= json_encode($item, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="m-cat-action-btn m-del" onclick='mResDel("location",<?= (int)$item["id"] ?>,<?= json_encode($item["name"], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)' title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 6. Skill Levels (read-only) -->
    <div class="m-acc" data-section="skill_level">
        <div class="m-acc-head" onclick="mAccToggle(this)">
            <div class="m-acc-icon" style="background:rgba(168,85,247,0.15);color:#A855F7;"><i class="fas fa-layer-group"></i></div>
            <span class="m-acc-label">Skill Levels</span>
            <span class="m-acc-badge"><?= count($skillLevels) ?></span>
            <span class="m-acc-chevron"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="m-acc-body">
            <div class="m-cat-readonly-note"><i class="fas fa-info-circle"></i> View only</div>
            <?php if (empty($skillLevels)): ?>
                <div class="m-empty-small"><i class="fas fa-layer-group"></i> No skill levels found</div>
            <?php else: ?>
                <?php foreach ($skillLevels as $item): ?>
                <div class="m-cat-card">
                    <div class="m-cat-body">
                        <div class="m-cat-name"><?= htmlspecialchars($item['name'] ?? '') ?></div>
                        <?php if (!empty($item['description'])): ?>
                        <div class="m-cat-desc"><?= htmlspecialchars($item['description']) ?></div>
                        <?php endif; ?>
                        <div class="m-cat-meta">Order: <?= (int)($item['display_order'] ?? 0) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 7. Seasons -->
    <div class="m-acc" data-section="season">
        <div class="m-acc-head" onclick="mAccToggle(this)">
            <div class="m-acc-icon" style="background:rgba(6,182,212,0.15);color:#06B6D4;"><i class="fas fa-calendar-alt"></i></div>
            <span class="m-acc-label">Seasons</span>
            <span class="m-acc-badge"><?= count($seasons) ?></span>
            <span class="m-acc-chevron"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="m-acc-body">
            <?php if (empty($seasons)): ?>
                <div class="m-empty-small"><i class="fas fa-calendar-alt"></i> No seasons found</div>
            <?php else: ?>
                <?php foreach ($seasons as $item): ?>
                <div class="m-cat-card">
                    <div class="m-cat-body">
                        <div class="m-cat-name"><?= htmlspecialchars($item['name'] ?? '') ?></div>
                        <div class="m-cat-meta">
                            <?php
                            $sd = !empty($item['start_date']) ? date('M j, Y', strtotime($item['start_date'])) : '';
                            $ed = !empty($item['end_date']) ? date('M j, Y', strtotime($item['end_date'])) : '';
                            if ($sd && $ed) echo htmlspecialchars($sd . ' – ' . $ed);
                            elseif ($sd) echo htmlspecialchars($sd);
                            ?>
                        </div>
                    </div>
                    <div class="m-cat-actions">
                        <?php if (!empty($item['is_active'])): ?>
                            <span class="m-cat-badge-active">Active</span>
                        <?php else: ?>
                            <form method="POST" action="process_admin_team_coaches.php" style="display:inline;">
                                <?= csrfTokenInput() ?>
                                <input type="hidden" name="action" value="activate_season">
                                <input type="hidden" name="season_id" value="<?= (int)$item['id'] ?>">
                                <input type="hidden" name="redirect_page" value="categories">
                                <button type="submit" class="m-season-btn" title="Activate">Activate</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 8. Age Groups (read-only) -->
    <div class="m-acc" data-section="age_group">
        <div class="m-acc-head" onclick="mAccToggle(this)">
            <div class="m-acc-icon" style="background:rgba(234,179,8,0.15);color:#EAB308;"><i class="fas fa-child"></i></div>
            <span class="m-acc-label">Age Groups</span>
            <span class="m-acc-badge"><?= count($ageGroups) ?></span>
            <span class="m-acc-chevron"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="m-acc-body">
            <div class="m-cat-readonly-note"><i class="fas fa-info-circle"></i> View only</div>
            <?php if (empty($ageGroups)): ?>
                <div class="m-empty-small"><i class="fas fa-child"></i> No age groups found</div>
            <?php else: ?>
                <?php foreach ($ageGroups as $item): ?>
                <div class="m-cat-card">
                    <div class="m-cat-body">
                        <div class="m-cat-name"><?= htmlspecialchars($item['name'] ?? '') ?></div>
                        <?php if (!empty($item['description'])): ?>
                        <div class="m-cat-desc"><?= htmlspecialchars($item['description']) ?></div>
                        <?php endif; ?>
                        <div class="m-cat-meta">
                            Ages <?= (int)($item['min_age'] ?? 0) ?>–<?= (int)($item['max_age'] ?? 0) ?>
                            · Order: <?= (int)($item['display_order'] ?? 0) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- FAB -->
<button class="m-cat-fab" onclick="mResAdd()" title="Add Resource"><i class="fas fa-plus"></i></button>

<!-- Overlay + Bottom Sheet -->
<div class="m-cat-overlay" id="mCatOverlay" onclick="mSheetClose()"></div>
<div class="m-cat-sheet" id="mCatSheet">
    <div class="m-cat-sheet-handle"></div>
    <div class="m-cat-sheet-title">
        <span id="mSheetLabel"><i class="fas fa-plus-circle"></i> Add Resource</span>
        <button class="m-cat-sheet-close" onclick="mSheetClose()">&times;</button>
    </div>
    <form method="POST" action="process_admin_action.php" id="mResForm">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" id="mResAction" value="create_skill">
        <input type="hidden" name="type" id="mResType" value="skill">
        <input type="hidden" name="id" id="mResEditId" value="">
        <input type="hidden" name="redirect_page" value="categories">

        <!-- Resource Type Selector (shown only when adding) -->
        <div class="m-cat-form-group" id="mGrpResType">
            <label class="m-cat-form-label">Resource Type *</label>
            <select id="mFldResType" class="m-cat-form-select" onchange="mResTypeChanged(this.value)">
                <option value="skill">Skill</option>
                <option value="drill_type">Drill Type</option>
                <option value="merchandise">Merchandise Category</option>
                <option value="location">Location</option>
            </select>
        </div>

        <!-- Shared: name -->
        <div class="m-cat-form-group" id="mGrpName">
            <label class="m-cat-form-label" id="mLblName">Name *</label>
            <input type="text" name="name" id="mFldName" class="m-cat-form-input" required placeholder="Enter name">
        </div>

        <!-- Shared: description -->
        <div class="m-cat-form-group" id="mGrpDesc">
            <label class="m-cat-form-label">Description</label>
            <textarea name="description" id="mFldDesc" class="m-cat-form-input" style="resize:vertical;min-height:60px;" rows="3" placeholder="Optional description"></textarea>
        </div>

        <!-- Drill Type: position_type -->
        <div class="m-cat-form-group" id="mGrpPosType" style="display:none;">
            <label class="m-cat-form-label">Position Type *</label>
            <select name="position_type" id="mFldPosType" class="m-cat-form-select">
                <option value="both">Both</option>
                <option value="player">Player</option>
                <option value="goalie">Goalie</option>
            </select>
        </div>

        <!-- Location: city -->
        <div class="m-cat-form-group" id="mGrpCity" style="display:none;">
            <label class="m-cat-form-label">City *</label>
            <input type="text" name="city" id="mFldCity" class="m-cat-form-input" placeholder="City">
        </div>

        <!-- Location: address -->
        <div class="m-cat-form-group" id="mGrpAddr" style="display:none;">
            <label class="m-cat-form-label">Address</label>
            <input type="text" name="address" id="mFldAddr" class="m-cat-form-input" placeholder="Street address">
        </div>

        <!-- Location: province -->
        <div class="m-cat-form-group" id="mGrpProv" style="display:none;">
            <label class="m-cat-form-label">Province</label>
            <input type="text" name="province" id="mFldProv" class="m-cat-form-input" placeholder="Province/State">
        </div>

        <!-- Location: postal_code -->
        <div class="m-cat-form-group" id="mGrpPostal" style="display:none;">
            <label class="m-cat-form-label">Postal Code</label>
            <input type="text" name="postal_code" id="mFldPostal" class="m-cat-form-input" placeholder="Postal/ZIP code">
        </div>

        <!-- Location: phone -->
        <div class="m-cat-form-group" id="mGrpPhone" style="display:none;">
            <label class="m-cat-form-label">Phone</label>
            <input type="tel" name="phone" id="mFldPhone" class="m-cat-form-input" placeholder="Phone number">
        </div>

        <button type="submit" class="m-cat-submit" id="mResSubmitBtn"><i class="fas fa-save"></i> Create</button>
    </form>
</div>

<!-- Hidden delete form -->
<form method="POST" action="process_admin_action.php" id="mResDeleteForm" style="display:none;">
    <?= csrfTokenInput() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="type" id="mResDelType" value="">
    <input type="hidden" name="id" id="mResDelId" value="">
    <input type="hidden" name="redirect_page" value="categories">
</form>

<script>
/* Accordion */
function mAccToggle(el) {
    var acc = el.closest('.m-acc');
    acc.classList.toggle('m-open');
}

/* Track which section the user last opened */
var mActiveSection = 'skill';
document.querySelectorAll('.m-acc-head').forEach(function(h) {
    h.addEventListener('click', function() {
        var sec = this.closest('.m-acc').getAttribute('data-section');
        if (sec && mEditableSections.indexOf(sec) !== -1) mActiveSection = sec;
    });
});

var mEditableSections = ['skill','drill_type','merchandise','location'];

/* Section config for form fields */
var mSectionConfig = {
    skill: {
        createAction: 'create_skill', editType: 'skill', label: 'Skill',
        fields: ['name','desc'], namePlaceholder: 'e.g., Skating, Passing',
        nameLabel: 'Skill Name *'
    },
    drill_type: {
        createAction: 'create_drill_type', editType: 'drill_type', label: 'Drill Type',
        fields: ['name','desc','posType'], namePlaceholder: 'e.g., Shooting Drill',
        nameLabel: 'Drill Type Name *'
    },
    merchandise: {
        createAction: 'create_merchandise_category', editType: 'merchandise', label: 'Merchandise Category',
        fields: ['name','desc'], namePlaceholder: 'e.g., Jerseys, Equipment',
        nameLabel: 'Category Name *'
    },
    location: {
        createAction: 'create_location', editType: 'location', label: 'Location',
        fields: ['name','desc','city','addr','prov','postal','phone'], namePlaceholder: 'e.g., Main Arena',
        nameLabel: 'Location Name *'
    }
};

/* Show/hide form fields based on section */
function mConfigureForm(section, isEdit) {
    var cfg = mSectionConfig[section];
    if (!cfg) return;

    var allOptional = ['mGrpPosType','mGrpCity','mGrpAddr','mGrpProv','mGrpPostal','mGrpPhone'];
    allOptional.forEach(function(id) { document.getElementById(id).style.display = 'none'; });

    // Reset required on conditional fields
    document.getElementById('mFldCity').removeAttribute('required');

    if (cfg.fields.indexOf('posType') !== -1) document.getElementById('mGrpPosType').style.display = '';
    if (cfg.fields.indexOf('city') !== -1) {
        document.getElementById('mGrpCity').style.display = '';
        document.getElementById('mFldCity').setAttribute('required', 'required');
    }
    if (cfg.fields.indexOf('addr') !== -1) document.getElementById('mGrpAddr').style.display = '';
    if (cfg.fields.indexOf('prov') !== -1) document.getElementById('mGrpProv').style.display = '';
    if (cfg.fields.indexOf('postal') !== -1) document.getElementById('mGrpPostal').style.display = '';
    if (cfg.fields.indexOf('phone') !== -1) document.getElementById('mGrpPhone').style.display = '';

    document.getElementById('mLblName').textContent = cfg.nameLabel;
    document.getElementById('mFldName').placeholder = cfg.namePlaceholder;

    var icon = isEdit ? 'fa-edit' : 'fa-plus-circle';
    var verb = isEdit ? 'Edit' : 'Add';
    document.getElementById('mSheetLabel').innerHTML = '<i class="fas ' + icon + '"></i> ' + verb + ' ' + cfg.label;
    document.getElementById('mResSubmitBtn').innerHTML = '<i class="fas fa-save"></i> ' + (isEdit ? 'Update' : 'Create') + ' ' + cfg.label;

    document.getElementById('mResAction').value = isEdit ? 'edit' : cfg.createAction;
    document.getElementById('mResType').value = cfg.editType;
}

/* Clear all form fields */
function mClearForm() {
    document.getElementById('mFldName').value = '';
    document.getElementById('mFldDesc').value = '';
    document.getElementById('mFldPosType').value = 'both';
    document.getElementById('mFldCity').value = '';
    document.getElementById('mFldAddr').value = '';
    document.getElementById('mFldProv').value = '';
    document.getElementById('mFldPostal').value = '';
    document.getElementById('mFldPhone').value = '';
    document.getElementById('mResEditId').value = '';
}

/* FAB: add resource - show type selector so user can pick any category */
function mResAdd() {
    var section = mActiveSection;
    if (mEditableSections.indexOf(section) === -1) section = 'skill';
    mClearForm();
    mConfigureForm(section, false);
    // Show the resource type selector and set it to the current section
    document.getElementById('mGrpResType').style.display = '';
    document.getElementById('mFldResType').value = section;
    mSheetOpen();
}

/* Called when user changes the resource type dropdown */
function mResTypeChanged(section) {
    if (mEditableSections.indexOf(section) === -1) return;
    mClearForm();
    mConfigureForm(section, false);
    // Keep the type selector visible during add
    document.getElementById('mGrpResType').style.display = '';
    document.getElementById('mFldResType').value = section;
}

/* Edit a resource */
function mResEdit(section, data) {
    mClearForm();
    mConfigureForm(section, true);
    // Hide the resource type selector during edit
    document.getElementById('mGrpResType').style.display = 'none';
    document.getElementById('mResEditId').value = data.id || '';
    document.getElementById('mFldName').value = data.name || '';
    document.getElementById('mFldDesc').value = data.description || '';

    if (section === 'drill_type') {
        document.getElementById('mFldPosType').value = data.position_type || 'both';
    }
    if (section === 'location') {
        document.getElementById('mFldCity').value = data.city || '';
        document.getElementById('mFldAddr').value = data.address || '';
        document.getElementById('mFldProv').value = data.province || '';
        document.getElementById('mFldPostal').value = data.postal_code || '';
        document.getElementById('mFldPhone').value = data.phone || '';
    }
    mSheetOpen();
}

/* Delete a resource */
function mResDel(section, id, name) {
    if (confirm('Delete "' + name + '"? This cannot be undone.')) {
        var cfg = mSectionConfig[section];
        document.getElementById('mResDelType').value = cfg ? cfg.editType : section;
        document.getElementById('mResDelId').value = id;
        document.getElementById('mResDeleteForm').submit();
    }
}

/* Sheet open/close */
function mSheetOpen() {
    document.getElementById('mCatOverlay').classList.add('m-active');
    document.getElementById('mCatSheet').classList.add('m-active');
}
function mSheetClose() {
    document.getElementById('mCatOverlay').classList.remove('m-active');
    document.getElementById('mCatSheet').classList.remove('m-active');
}
</script>
