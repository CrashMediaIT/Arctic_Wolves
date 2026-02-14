<?php
/**
 * PWA Categories - Mobile-native resource categories management
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$categories = [];
try {
    $stmt = $pdo->prepare("SELECT es.id, es.name, es.description, ec.name as category_name FROM eval_skills es LEFT JOIN eval_categories ec ON es.category_id = ec.id ORDER BY es.name ASC");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, type, description FROM categories ORDER BY type, name");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) { $categories = []; }
}

$grouped = [];
foreach ($categories as $c) {
    $type = $c['category_name'] ?? $c['type'] ?? 'Other';
    $grouped[$type][] = $c;
}
?>
<style>
.m-cats { padding: 16px; font-family: Inter, sans-serif; }
.m-cats-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.m-cats-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-cats-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-cats-group-title { font-size: 13px; font-weight: 600; color: #8B5CF6; margin: 16px 0 8px; text-transform: uppercase; letter-spacing: 0.5px; }
.m-cat-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-cat-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(107,70,193,0.15); color: #8B5CF6; font-size: 14px; flex-shrink: 0;
}
.m-cat-body { flex: 1; min-width: 0; }
.m-cat-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-cat-desc { font-size: 12px; color: #A8A8B8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-cat-actions { display: flex; gap: 6px; flex-shrink: 0; }
.m-cat-action-btn {
    width: 34px; height: 34px; border-radius: 8px; border: 1px solid #2D2D3F;
    background: #0A0A0F; color: #A8A8B8; display: flex; align-items: center;
    justify-content: center; cursor: pointer; font-size: 12px;
}
.m-cat-action-btn.m-del { color: #EF4444; border-color: rgba(239,68,68,0.3); }
.m-cat-fab {
    position: fixed; bottom: 80px; right: 16px; width: 52px; height: 52px;
    border-radius: 14px; background: #6B46C1; color: #fff; border: none;
    font-size: 20px; cursor: pointer; z-index: 100;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(107,70,193,0.4);
}
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
.m-cat-sheet-title {
    font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 16px;
    display: flex; align-items: center; justify-content: space-between;
}
.m-cat-sheet-close {
    background: none; border: none; color: #A8A8B8; font-size: 22px; cursor: pointer;
    width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
}
.m-cat-form-group { margin-bottom: 12px; }
.m-cat-form-label { font-size: 12px; font-weight: 600; color: #A8A8B8; margin-bottom: 4px; display: block; }
.m-cat-form-input {
    width: 100%; background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; padding: 12px; min-height: 44px; font-size: 14px;
    font-family: Inter, sans-serif; box-sizing: border-box;
}
.m-cat-form-input:focus { outline: none; border-color: #6B46C1; }
.m-cat-submit {
    width: 100%; padding: 12px; background: #6B46C1; color: #fff; border: none;
    border-radius: 10px; font-size: 14px; font-weight: 600; min-height: 44px;
    cursor: pointer; margin-top: 8px; font-family: Inter, sans-serif;
}
.m-cat-alert {
    padding: 10px 12px; border-radius: 10px; margin-bottom: 12px; font-size: 12px;
    background: rgba(16,185,129,0.15); color: #10B981; border: 1px solid rgba(16,185,129,0.3);
}
</style>

<div class="m-cats">
    <div class="m-cats-header">
        <div>
            <h2 class="m-cats-title">Categories</h2>
            <p class="m-cats-sub"><?= count($categories) ?> categor<?= count($categories) !== 1 ? 'ies' : 'y' ?></p>
        </div>
    </div>

    <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <div class="m-cat-alert"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['message'] ?? 'Operation completed!') ?></div>
    <?php endif; ?>

    <?php if (empty($categories)): ?>
        <div class="m-empty-state">
            <i class="fas fa-folder-open"></i>
            <p>No categories found</p>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $type => $items): ?>
            <div class="m-cats-group-title"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $type))) ?></div>
            <?php foreach ($items as $c): ?>
            <div class="m-cat-card">
                <div class="m-cat-icon"><i class="fas fa-tag"></i></div>
                <div class="m-cat-body">
                    <div class="m-cat-name"><?= htmlspecialchars($c['name'] ?? '') ?></div>
                    <?php if (!empty($c['description'])): ?>
                    <div class="m-cat-desc"><?= htmlspecialchars($c['description']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="m-cat-actions">
                    <button class="m-cat-action-btn" onclick='mCatEdit(<?= json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="m-cat-action-btn m-del" onclick='mCatDelete(<?= (int)$c["id"] ?>, <?= json_encode($c["name"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Delete"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button class="m-cat-fab" onclick="mCatAdd()" title="Add Skill"><i class="fas fa-plus"></i></button>

<div class="m-cat-overlay" id="mCatOverlay" onclick="mCatClose()"></div>
<div class="m-cat-sheet" id="mCatSheet">
    <div class="m-cat-sheet-title">
        <span id="mCatSheetLabel"><i class="fas fa-plus-circle"></i> Add Skill</span>
        <button class="m-cat-sheet-close" onclick="mCatClose()">&times;</button>
    </div>
    <form method="POST" action="process_admin_action.php" id="mCatForm">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" id="mCatAction" value="create_skill">
        <input type="hidden" name="type" id="mCatType" value="skill">
        <input type="hidden" name="id" id="mCatEditId" value="">

        <div class="m-cat-form-group">
            <label class="m-cat-form-label">Skill Name *</label>
            <input type="text" name="name" id="mCatName" class="m-cat-form-input" required placeholder="e.g., Skating, Passing, Shooting">
        </div>
        <div class="m-cat-form-group">
            <label class="m-cat-form-label">Description</label>
            <textarea name="description" id="mCatDesc" class="m-cat-form-input" style="resize:vertical;min-height:60px;" rows="3" placeholder="Describe what this skill evaluates"></textarea>
        </div>
        <button type="submit" class="m-cat-submit" id="mCatSubmitBtn"><i class="fas fa-save"></i> Create Skill</button>
    </form>
</div>

<!-- Hidden delete form -->
<form method="POST" action="process_admin_action.php" id="mCatDeleteForm" style="display:none;">
    <?= csrfTokenInput() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="type" value="skill">
    <input type="hidden" name="id" id="mCatDeleteId" value="">
</form>

<script>
function mCatAdd() {
    document.getElementById('mCatAction').value = 'create_skill';
    document.getElementById('mCatType').value = 'skill';
    document.getElementById('mCatEditId').value = '';
    document.getElementById('mCatSheetLabel').innerHTML = '<i class="fas fa-plus-circle"></i> Add Skill';
    document.getElementById('mCatSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Create Skill';
    document.getElementById('mCatName').value = '';
    document.getElementById('mCatDesc').value = '';
    mCatOpen();
}
function mCatEdit(c) {
    document.getElementById('mCatAction').value = 'edit';
    document.getElementById('mCatType').value = 'skill';
    document.getElementById('mCatEditId').value = c.id || '';
    document.getElementById('mCatSheetLabel').innerHTML = '<i class="fas fa-edit"></i> Edit Skill';
    document.getElementById('mCatSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Update Skill';
    document.getElementById('mCatName').value = c.name || '';
    document.getElementById('mCatDesc').value = c.description || '';
    mCatOpen();
}
function mCatDelete(id, name) {
    if (confirm('Are you sure you want to delete "' + name + '"?')) {
        document.getElementById('mCatDeleteId').value = id;
        document.getElementById('mCatDeleteForm').submit();
    }
}
function mCatOpen() {
    document.getElementById('mCatOverlay').classList.add('m-active');
    document.getElementById('mCatSheet').classList.add('m-active');
}
function mCatClose() {
    document.getElementById('mCatOverlay').classList.remove('m-active');
    document.getElementById('mCatSheet').classList.remove('m-active');
}
</script>
