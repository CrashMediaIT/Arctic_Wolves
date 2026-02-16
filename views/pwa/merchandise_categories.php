<?php
/**
 * PWA Merchandise Categories - Mobile-native product category management
 * Purpose-built for mobile phones.
 */

if (!$isAdmin) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Admin access required.</p>';
    echo '</div>';
    return;
}

$categories = [];
$parentCategories = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, name, slug, description, display_order, is_active, parent_id,
               (SELECT COUNT(*) FROM merchandise_products mp WHERE mp.category_id = mc.id) as product_count
        FROM merchandise_categories mc
        ORDER BY display_order ASC, name ASC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $parentStmt = $pdo->prepare("SELECT id, name FROM merchandise_categories WHERE parent_id IS NULL OR parent_id = 0 ORDER BY name");
    $parentStmt->execute();
    $parentCategories = $parentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $categories = []; $parentCategories = []; }

$totalCats = count($categories);
?>
<style>
.m-merchcats { padding: 16px 16px 100px; font-family: Inter, sans-serif; }
.m-merchcats-header { margin-bottom: 16px; }
.m-merchcats-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-merchcats-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-merchcat-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
    display: flex; align-items: center; gap: 12px; min-height: 44px;
}
.m-merchcat-icon {
    width: 44px; height: 44px; border-radius: 10px;
    background: rgba(107,70,193,0.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: #8B5CF6; flex-shrink: 0;
}
.m-merchcat-info { flex: 1; min-width: 0; }
.m-merchcat-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-merchcat-desc { font-size: 12px; color: #A8A8B8; margin-top: 2px; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
.m-merchcat-meta { font-size: 11px; color: #6B6B7B; margin-top: 4px; display: flex; gap: 10px; }
.m-merchcat-actions { display: flex; gap: 6px; flex-shrink: 0; }
.m-merchcat-btn { width: 34px; height: 34px; border-radius: 8px; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 12px; }
.m-merchcat-btn-edit { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-merchcat-btn-del { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-fab { position: fixed; bottom: 60px; right: 20px; width: 56px; height: 56px; border-radius: 50%; background: #6B46C1; color: #fff; border: none; font-size: 24px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(107,70,193,0.4); cursor: pointer; z-index: 100; }
.m-sheet-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1000; }
.m-sheet-overlay.m-active { display: block; }
.m-sheet { position: fixed; bottom: 0; left: 0; right: 0; background: #16161F; border-radius: 16px 16px 0 0; padding: 20px 16px 32px; z-index: 1001; max-height: 85vh; overflow-y: auto; transform: translateY(100%); transition: transform 0.3s ease; }
.m-sheet-overlay.m-active .m-sheet { transform: translateY(0); }
.m-sheet-handle { width: 40px; height: 4px; background: #3D3D4F; border-radius: 2px; margin: 0 auto 16px; }
.m-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-form-group { margin-bottom: 14px; }
.m-form-label { display: block; font-size: 12px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; text-transform: uppercase; }
.m-form-input, .m-form-select, .m-form-textarea { width: 100%; padding: 10px 12px; background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff; font-size: 14px; min-height: 44px; box-sizing: border-box; font-family: inherit; }
.m-form-textarea { min-height: 70px; resize: vertical; }
.m-form-input:focus, .m-form-select:focus, .m-form-textarea:focus { outline: none; border-color: #6B46C1; }
.m-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.m-btn-submit { width: 100%; padding: 14px; background: #6B46C1; color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; min-height: 44px; margin-top: 4px; }
.m-toast { position: fixed; top: 20px; left: 16px; right: 16px; padding: 14px 16px; border-radius: 10px; color: #fff; font-size: 13px; font-weight: 600; z-index: 2000; display: flex; align-items: center; gap: 8px; }
</style>

<div class="m-merchcats">
    <div class="m-merchcats-header">
        <h2 class="m-merchcats-title">Merchandise Categories</h2>
        <p class="m-merchcats-sub"><?= $totalCats ?> categor<?= $totalCats !== 1 ? 'ies' : 'y' ?></p>
    </div>

    <?php if (empty($categories)): ?>
        <div class="m-empty-state">
            <i class="fas fa-folder-open"></i>
            <p>No categories defined</p>
        </div>
    <?php else: ?>
        <?php foreach ($categories as $c): ?>
        <div class="m-merchcat-card" data-id="<?= (int)$c['id'] ?>" data-name="<?= htmlspecialchars($c['name'] ?? '', ENT_QUOTES) ?>" data-slug="<?= htmlspecialchars($c['slug'] ?? '', ENT_QUOTES) ?>" data-description="<?= htmlspecialchars($c['description'] ?? '', ENT_QUOTES) ?>" data-order="<?= (int)($c['display_order'] ?? 0) ?>" data-active="<?= (int)($c['is_active'] ?? 1) ?>" data-parent="<?= (int)($c['parent_id'] ?? 0) ?>" data-products="<?= (int)($c['product_count'] ?? 0) ?>">
            <div class="m-merchcat-icon"><i class="fas fa-tag"></i></div>
            <div class="m-merchcat-info">
                <div class="m-merchcat-name"><?= htmlspecialchars($c['name']) ?></div>
                <?php if (!empty($c['description'])): ?>
                <div class="m-merchcat-desc"><?= htmlspecialchars($c['description']) ?></div>
                <?php endif; ?>
                <div class="m-merchcat-meta">
                    <span><i class="fas fa-box"></i> <?= (int)($c['product_count'] ?? 0) ?></span>
                    <span class="<?= !empty($c['is_active']) ? '' : 'style="color:#EF4444;"' ?>"><?= !empty($c['is_active']) ? 'Active' : 'Inactive' ?></span>
                </div>
            </div>
            <div class="m-merchcat-actions">
                <button class="m-merchcat-btn m-merchcat-btn-edit" onclick="mEditCat(this.closest('.m-merchcat-card'))"><i class="fas fa-pencil-alt"></i></button>
                <button class="m-merchcat-btn m-merchcat-btn-del" onclick="mDeleteCat(this.closest('.m-merchcat-card'))"><i class="fas fa-trash"></i></button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button class="m-fab" onclick="mOpenCatSheet('create')"><i class="fas fa-plus"></i></button>

<div id="mCatSheet" class="m-sheet-overlay">
    <div class="m-sheet">
        <div class="m-sheet-handle"></div>
        <h3 class="m-sheet-title" id="mCatSheetTitle">Add Category</h3>
        <form method="POST" action="process_merchandise_categories.php" id="mCatForm" enctype="multipart/form-data">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" id="mCatAction" value="create">
            <input type="hidden" name="id" id="mCatId" value="">
            <div class="m-form-group">
                <label class="m-form-label">Name *</label>
                <input type="text" name="name" id="mCatName" class="m-form-input" required placeholder="e.g., Jerseys, Accessories">
            </div>
            <div class="m-form-group">
                <label class="m-form-label">Parent Category</label>
                <select name="parent_id" id="mCatParent" class="m-form-select">
                    <option value="">None (Top-Level)</option>
                    <?php foreach ($parentCategories as $pc): ?>
                    <option value="<?= (int)$pc['id'] ?>"><?= htmlspecialchars($pc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="m-form-group">
                <label class="m-form-label">Description</label>
                <textarea name="description" id="mCatDesc" class="m-form-textarea" placeholder="Brief description"></textarea>
            </div>
            <div class="m-form-row">
                <div class="m-form-group">
                    <label class="m-form-label">Display Order</label>
                    <input type="number" name="display_order" id="mCatOrder" class="m-form-input" value="0" min="0">
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Status</label>
                    <select name="is_active" id="mCatActive" class="m-form-select">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="m-form-group">
                <label class="m-form-label">Image</label>
                <input type="file" name="image" class="m-form-input" accept="image/jpeg,image/png,image/gif,image/webp">
            </div>
            <button type="submit" class="m-btn-submit"><i class="fas fa-save"></i> Save Category</button>
        </form>
    </div>
</div>

<script>
(function(){
    var csrfToken = document.querySelector('#mCatForm [name="csrf_token"]') ? document.querySelector('#mCatForm [name="csrf_token"]').value : '';

    window.mOpenCatSheet = function(mode, card) {
        var sheet = document.getElementById('mCatSheet');
        document.getElementById('mCatSheetTitle').textContent = mode === 'edit' ? 'Edit Category' : 'Add Category';
        document.getElementById('mCatAction').value = mode === 'edit' ? 'update' : 'create';
        if (mode === 'edit' && card) {
            document.getElementById('mCatId').value = card.dataset.id;
            document.getElementById('mCatName').value = card.dataset.name;
            document.getElementById('mCatDesc').value = card.dataset.description;
            document.getElementById('mCatOrder').value = card.dataset.order;
            document.getElementById('mCatActive').value = card.dataset.active;
            document.getElementById('mCatParent').value = card.dataset.parent || '';
        } else {
            document.getElementById('mCatId').value = '';
            document.getElementById('mCatName').value = '';
            document.getElementById('mCatDesc').value = '';
            document.getElementById('mCatOrder').value = '0';
            document.getElementById('mCatActive').value = '1';
            document.getElementById('mCatParent').value = '';
        }
        sheet.classList.add('m-active');
    };

    window.mEditCat = function(card) { mOpenCatSheet('edit', card); };

    window.mDeleteCat = function(card) {
        if (parseInt(card.dataset.products) > 0) {
            alert('Cannot delete category with ' + card.dataset.products + ' products. Move or delete them first.');
            return;
        }
        if (!confirm('Delete "' + card.dataset.name + '"?')) return;
        fetch('process_merchandise_categories.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: 'action=delete&id=' + encodeURIComponent(card.dataset.id) + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            mToast(d.message || (d.success ? 'Deleted!' : 'Error'), d.success ? 'success' : 'error');
            if (d.success) location.reload();
        })
        .catch(function() { mToast('An error occurred', 'error'); });
    };

    document.getElementById('mCatSheet').addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('m-active');
    });

    document.getElementById('mCatForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = this;
        var btn = form.querySelector('.m-btn-submit');
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;
        fetch(form.getAttribute('action'), {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            btn.innerHTML = orig; btn.disabled = false;
            mToast(d.message || (d.success ? 'Saved!' : 'Error'), d.success ? 'success' : 'error');
            if (d.success) { document.getElementById('mCatSheet').classList.remove('m-active'); location.reload(); }
        })
        .catch(function() { btn.innerHTML = orig; btn.disabled = false; mToast('An error occurred', 'error'); });
    });

    window.mToast = function(msg, type) {
        var old = document.querySelector('.m-toast');
        if (old) old.remove();
        var d = document.createElement('div');
        d.className = 'm-toast';
        d.style.background = type === 'success' ? 'rgba(16,185,129,0.95)' : 'rgba(239,68,68,0.95)';
        var icon = document.createElement('i');
        icon.className = 'fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle');
        d.appendChild(icon);
        var span = document.createElement('span');
        span.textContent = msg;
        d.appendChild(span);
        document.body.appendChild(d);
        setTimeout(function() { if (d.parentElement) d.remove(); }, 4000);
    };
})();
</script>
