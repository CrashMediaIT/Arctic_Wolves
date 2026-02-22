<?php
/**
 * PWA Eval Framework - Mobile-native evaluation framework management
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$skills = [];
$evalCategories = [];
$skillsByCategory = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.id as category_id, c.name as category_name, c.description as category_description,
               s.id as skill_id, s.name as skill_name, s.description as skill_description
        FROM eval_categories c
        LEFT JOIN eval_skill_categories esc ON c.id = esc.category_id
        LEFT JOIN eval_skills s ON esc.skill_id = s.id
        ORDER BY c.display_order ASC, c.id ASC, esc.display_order ASC, s.id ASC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $catId = $row['category_id'];
        if (!isset($evalCategories[$catId])) {
            $evalCategories[$catId] = [
                'id' => $row['category_id'],
                'name' => $row['category_name'],
                'description' => $row['category_description']
            ];
            $skillsByCategory[$catId] = [];
        }
        if ($row['skill_id']) {
            $skillsByCategory[$catId][] = [
                'id' => $row['skill_id'],
                'name' => $row['skill_name'],
                'description' => $row['skill_description'],
                'category_id' => $catId
            ];
            $skills[] = ['id' => $row['skill_id'], 'name' => $row['skill_name'], 'description' => $row['skill_description'], 'category' => $row['category_name'], 'category_id' => $catId];
        }
    }
} catch (PDOException $e) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, description, category FROM eval_skills ORDER BY category, name LIMIT 30");
        $stmt->execute();
        $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) { $skills = []; }
}

// For the add-skill-to-category form, get all skills
$allSkillsLibrary = [];
try {
    $stmt = $pdo->prepare("SELECT es.id, es.name, ec.name as current_category FROM eval_skills es LEFT JOIN eval_categories ec ON es.category_id = ec.id ORDER BY es.name ASC");
    $stmt->execute();
    $allSkillsLibrary = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $allSkillsLibrary = []; }

$grouped = [];
if (!empty($evalCategories)) {
    foreach ($evalCategories as $catId => $cat) {
        $grouped[$cat['name']] = $skillsByCategory[$catId] ?? [];
    }
} else {
    foreach ($skills as $s) {
        $cat = $s['category'] ?? 'Uncategorized';
        $grouped[$cat][] = $s;
    }
}
$totalSkills = 0;
foreach ($grouped as $items) { $totalSkills += count($items); }
?>
<style>
.m-evalfw { padding: 16px; font-family: Inter, sans-serif; }
.m-evalfw-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.m-evalfw-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-evalfw-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-evalfw-group { font-size: 13px; font-weight: 600; color: #8B5CF6; margin: 16px 0 8px; text-transform: uppercase; letter-spacing: 0.5px; }
.m-evalfw-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
    display: flex; align-items: flex-start; gap: 10px;
}
.m-evalfw-card-body { flex: 1; min-width: 0; }
.m-evalfw-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-evalfw-desc { font-size: 12px; color: #A8A8B8; margin-top: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-evalfw-desktop {
    display: block; text-align: center; margin-top: 16px; padding: 12px;
    background: rgba(107,70,193,0.15); color: #8B5CF6; border-radius: 10px;
    font-size: 13px; font-weight: 600; text-decoration: none; min-height: 44px;
    line-height: 20px;
}
.m-evalfw-actions { display: flex; gap: 6px; flex-shrink: 0; margin-top: 2px; }
.m-evalfw-action-btn {
    width: 34px; height: 34px; border-radius: 8px; border: 1px solid #2D2D3F;
    background: #0A0A0F; color: #A8A8B8; display: flex; align-items: center;
    justify-content: center; cursor: pointer; font-size: 12px;
}
.m-evalfw-action-btn.m-del { color: #EF4444; border-color: rgba(239,68,68,0.3); }
.m-evalfw-cat-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 12px 14px; margin-bottom: 6px; display: flex; align-items: center; gap: 10px;
}
.m-evalfw-cat-body { flex: 1; min-width: 0; }
.m-evalfw-cat-name { font-size: 14px; font-weight: 700; color: #8B5CF6; }
.m-evalfw-cat-desc { font-size: 11px; color: #A8A8B8; margin-top: 2px; }
.m-evalfw-cat-count { font-size: 10px; color: #A8A8B8; background: #0A0A0F; border-radius: 6px; padding: 2px 8px; flex-shrink: 0; }
.m-evalfw-fab {
    position: fixed; bottom: 80px; right: 16px; width: 52px; height: 52px;
    border-radius: 14px; background: #6B46C1; color: #fff; border: none;
    font-size: 20px; cursor: pointer; z-index: 100;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(107,70,193,0.4);
}
.m-evalfw-overlay {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6); z-index: 9998;
}
.m-evalfw-overlay.m-active { display: block; }
.m-evalfw-sheet {
    display: none; position: fixed; left: 0; right: 0; bottom: 0;
    background: #16161F; border-radius: 16px 16px 0 0; z-index: 9999;
    max-height: 85vh; overflow-y: auto; padding: 20px 16px 32px;
}
.m-evalfw-sheet.m-active { display: block; }
.m-evalfw-sheet-title {
    font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 16px;
    display: flex; align-items: center; justify-content: space-between;
}
.m-evalfw-sheet-close {
    background: none; border: none; color: #A8A8B8; font-size: 22px; cursor: pointer;
    width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
}
.m-evalfw-form-group { margin-bottom: 12px; }
.m-evalfw-form-label { font-size: 12px; font-weight: 600; color: #A8A8B8; margin-bottom: 4px; display: block; }
.m-evalfw-form-input {
    width: 100%; background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; padding: 12px; min-height: 44px; font-size: 14px;
    font-family: Inter, sans-serif; box-sizing: border-box;
}
.m-evalfw-form-input:focus { outline: none; border-color: #6B46C1; }
.m-evalfw-submit {
    width: 100%; padding: 12px; background: #6B46C1; color: #fff; border: none;
    border-radius: 10px; font-size: 14px; font-weight: 600; min-height: 44px;
    cursor: pointer; margin-top: 8px; font-family: Inter, sans-serif;
}
.m-evalfw-alert {
    padding: 10px 12px; border-radius: 10px; margin-bottom: 12px; font-size: 12px;
    background: rgba(16,185,129,0.15); color: #10B981; border: 1px solid rgba(16,185,129,0.3);
}
.m-evalfw-tabs { display: flex; gap: 8px; margin-bottom: 16px; }
.m-evalfw-tab {
    flex: 1; padding: 10px 8px; background: #16161F; border: 1px solid #2D2D3F;
    border-radius: 10px; color: #A8A8B8; font-size: 12px; font-weight: 600;
    text-align: center; cursor: pointer; min-height: 44px;
    display: flex; align-items: center; justify-content: center; gap: 4px;
    font-family: Inter, sans-serif;
}
.m-evalfw-tab.m-active { background: rgba(107,70,193,0.2); color: #8B5CF6; border-color: #6B46C1; }
.m-evalfw-panel { display: none; }
.m-evalfw-panel.m-active { display: block; }
</style>

<div class="m-evalfw">
    <div class="m-evalfw-header">
        <div>
            <h2 class="m-evalfw-title">Evaluation Framework</h2>
            <p class="m-evalfw-sub"><?= count($evalCategories) ?> categories, <?= $totalSkills ?> skill<?= $totalSkills !== 1 ? 's' : '' ?></p>
        </div>
    </div>

    <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <div class="m-evalfw-alert"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['message'] ?? 'Operation completed!') ?></div>
    <?php endif; ?>

    <div class="m-evalfw-tabs">
        <button class="m-evalfw-tab m-active" onclick="mEvalTab('categories')"><i class="fas fa-clipboard-list"></i> Categories</button>
        <button class="m-evalfw-tab" onclick="mEvalTab('skills')"><i class="fas fa-star"></i> Skills</button>
    </div>

    <!-- Categories Panel -->
    <div id="m-evalfw-categories" class="m-evalfw-panel m-active">
        <?php if (empty($evalCategories)): ?>
            <div class="m-empty-state">
                <i class="fas fa-clipboard-list"></i>
                <p>No evaluation categories defined</p>
            </div>
        <?php else: ?>
            <?php foreach ($evalCategories as $catId => $cat): ?>
            <div class="m-evalfw-cat-card">
                <div class="m-evalfw-cat-body">
                    <div class="m-evalfw-cat-name"><?= htmlspecialchars($cat['name'] ?? '') ?></div>
                    <?php if (!empty($cat['description'])): ?>
                    <div class="m-evalfw-cat-desc"><?= htmlspecialchars($cat['description']) ?></div>
                    <?php endif; ?>
                </div>
                <span class="m-evalfw-cat-count"><?= count($skillsByCategory[$catId] ?? []) ?> skills</span>
                <div class="m-evalfw-actions">
                    <button class="m-evalfw-action-btn" onclick='mEvalEditCat(<?= json_encode($cat, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="m-evalfw-action-btn m-del" onclick='mEvalDeleteCat(<?= (int)$cat["id"] ?>, <?= json_encode($cat["name"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Delete"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Skills Panel -->
    <div id="m-evalfw-skills" class="m-evalfw-panel">
        <?php if (empty($skills) && empty($grouped)): ?>
            <div class="m-empty-state">
                <i class="fas fa-clipboard-list"></i>
                <p>No evaluation skills defined</p>
            </div>
        <?php else: ?>
            <?php foreach ($grouped as $cat => $items): ?>
                <div class="m-evalfw-group"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $cat))) ?></div>
                <?php foreach ($items as $s): ?>
                <div class="m-evalfw-card">
                    <div class="m-evalfw-card-body">
                        <div class="m-evalfw-name"><?= htmlspecialchars($s['name'] ?? '') ?></div>
                        <?php if (!empty($s['description'])): ?>
                        <div class="m-evalfw-desc"><?= htmlspecialchars($s['description']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="m-evalfw-actions">
                        <button class="m-evalfw-action-btn" onclick='mEvalEditSkill(<?= json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="m-evalfw-action-btn m-del" onclick='mEvalDeleteSkill(<?= (int)$s["id"] ?>, <?= json_encode($s["name"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <a href="?page=eval_framework&desktop=1" class="m-evalfw-desktop">
        <i class="fas fa-desktop"></i> Manage on Desktop
    </a>
</div>

<button class="m-evalfw-fab" id="mEvalFab" onclick="mEvalAddCategory()" title="Add Category"><i class="fas fa-plus"></i></button>

<div class="m-evalfw-overlay" id="mEvalOverlay" onclick="mEvalClose()"></div>

<!-- Add/Edit Category Sheet -->
<div class="m-evalfw-sheet" id="mEvalCatSheet">
    <div class="m-evalfw-sheet-title">
        <span id="mEvalCatSheetLabel"><i class="fas fa-plus-circle"></i> Add Category</span>
        <button class="m-evalfw-sheet-close" onclick="mEvalClose()">&times;</button>
    </div>
    <form method="POST" action="process_eval_framework.php" id="mEvalCatForm">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" id="mEvalCatAction" value="create_category">
        <input type="hidden" name="category_id" id="mEvalCatEditId" value="">

        <div class="m-evalfw-form-group">
            <label class="m-evalfw-form-label">Category Name *</label>
            <input type="text" name="name" id="mEvalCatName" class="m-evalfw-form-input" required placeholder="e.g., Skating Skills">
        </div>
        <div class="m-evalfw-form-group">
            <label class="m-evalfw-form-label">Description</label>
            <textarea name="description" id="mEvalCatDesc" class="m-evalfw-form-input" style="resize:vertical;min-height:60px;" rows="3" placeholder="Describe this evaluation category..."></textarea>
        </div>
        <button type="submit" class="m-evalfw-submit" id="mEvalCatSubmitBtn"><i class="fas fa-save"></i> Create Category</button>
    </form>
</div>

<!-- Edit Skill Sheet -->
<div class="m-evalfw-sheet" id="mEvalSkillSheet">
    <div class="m-evalfw-sheet-title">
        <span id="mEvalSkillSheetLabel"><i class="fas fa-edit"></i> Edit Skill</span>
        <button class="m-evalfw-sheet-close" onclick="mEvalClose()">&times;</button>
    </div>
    <form method="POST" action="process_eval_framework.php" id="mEvalSkillForm">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="update_skill">
        <input type="hidden" name="skill_id" id="mEvalSkillEditId" value="">
        <input type="hidden" name="category_id" id="mEvalSkillCatId" value="">

        <div class="m-evalfw-form-group">
            <label class="m-evalfw-form-label">Skill Name *</label>
            <input type="text" name="name" id="mEvalSkillName" class="m-evalfw-form-input" required>
        </div>
        <div class="m-evalfw-form-group">
            <label class="m-evalfw-form-label">Description</label>
            <textarea name="description" id="mEvalSkillDesc" class="m-evalfw-form-input" style="resize:vertical;min-height:60px;" rows="3"></textarea>
        </div>
        <button type="submit" class="m-evalfw-submit"><i class="fas fa-save"></i> Update Skill</button>
    </form>
</div>

<!-- Hidden delete forms -->
<form method="POST" action="process_eval_framework.php" id="mEvalDeleteCatForm" style="display:none;">
    <?= csrfTokenInput() ?>
    <input type="hidden" name="action" value="delete_category">
    <input type="hidden" name="category_id" id="mEvalDeleteCatId" value="">
</form>
<form method="POST" action="process_eval_framework.php" id="mEvalDeleteSkillForm" style="display:none;">
    <?= csrfTokenInput() ?>
    <input type="hidden" name="action" value="delete_skill">
    <input type="hidden" name="skill_id" id="mEvalDeleteSkillId" value="">
</form>

<script>
var mEvalCurrentPanel = 'categories';

function mEvalTab(tab) {
    mEvalCurrentPanel = tab;
    document.querySelectorAll('.m-evalfw-tab').forEach(function(t) { t.classList.remove('m-active'); });
    document.querySelectorAll('.m-evalfw-panel').forEach(function(p) { p.classList.remove('m-active'); });
    document.getElementById('m-evalfw-' + tab).classList.add('m-active');
    event.currentTarget.classList.add('m-active');
    // Update FAB action based on tab
    var fab = document.getElementById('mEvalFab');
    if (tab === 'categories') {
        fab.setAttribute('onclick', 'mEvalAddCategory()');
        fab.setAttribute('title', 'Add Category');
    } else {
        fab.setAttribute('onclick', 'mEvalAddCategory()');
        fab.setAttribute('title', 'Add Category');
    }
}

function mEvalAddCategory() {
    document.getElementById('mEvalCatAction').value = 'create_category';
    document.getElementById('mEvalCatEditId').value = '';
    document.getElementById('mEvalCatSheetLabel').innerHTML = '<i class="fas fa-plus-circle"></i> Add Category';
    document.getElementById('mEvalCatSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Create Category';
    document.getElementById('mEvalCatName').value = '';
    document.getElementById('mEvalCatDesc').value = '';
    document.getElementById('mEvalCatSheet').classList.add('m-active');
    document.getElementById('mEvalOverlay').classList.add('m-active');
}

function mEvalEditCat(cat) {
    document.getElementById('mEvalCatAction').value = 'update_category';
    document.getElementById('mEvalCatEditId').value = cat.id || '';
    document.getElementById('mEvalCatSheetLabel').innerHTML = '<i class="fas fa-edit"></i> Edit Category';
    document.getElementById('mEvalCatSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Update Category';
    document.getElementById('mEvalCatName').value = cat.name || '';
    document.getElementById('mEvalCatDesc').value = cat.description || '';
    document.getElementById('mEvalCatSheet').classList.add('m-active');
    document.getElementById('mEvalOverlay').classList.add('m-active');
}

function mEvalEditSkill(s) {
    document.getElementById('mEvalSkillEditId').value = s.id || '';
    document.getElementById('mEvalSkillCatId').value = s.category_id || '';
    document.getElementById('mEvalSkillName').value = s.name || '';
    document.getElementById('mEvalSkillDesc').value = s.description || '';
    document.getElementById('mEvalSkillSheet').classList.add('m-active');
    document.getElementById('mEvalOverlay').classList.add('m-active');
}

function mEvalDeleteCat(id, name) {
    if (confirm('Delete category "' + name + '"? This cannot be undone.')) {
        document.getElementById('mEvalDeleteCatId').value = id;
        document.getElementById('mEvalDeleteCatForm').submit();
    }
}

function mEvalDeleteSkill(id, name) {
    if (confirm('Delete skill "' + name + '"? This cannot be undone.')) {
        document.getElementById('mEvalDeleteSkillId').value = id;
        document.getElementById('mEvalDeleteSkillForm').submit();
    }
}

function mEvalClose() {
    document.getElementById('mEvalOverlay').classList.remove('m-active');
    document.querySelectorAll('.m-evalfw-sheet').forEach(function(s) { s.classList.remove('m-active'); });
}
</script>
