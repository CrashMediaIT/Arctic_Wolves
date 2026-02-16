<?php
/**
 * PWA Merchandise Products - Mobile-native product management
 * Purpose-built for mobile phones.
 */

if (!$isAdmin) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Admin access required.</p>';
    echo '</div>';
    return;
}

$products = [];
$prodCategories = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, name, sku, category_id, description, price, cost_price, is_active, track_inventory
        FROM merchandise_products
        ORDER BY name
        LIMIT 30
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $catStmt = $pdo->prepare("SELECT id, name FROM merchandise_categories WHERE is_active = 1 ORDER BY name");
    $catStmt->execute();
    $prodCategories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $products = []; $prodCategories = []; }

$totalProducts = count($products);
?>
<style>
.m-merchprod { padding: 16px 16px 100px; font-family: Inter, sans-serif; }
.m-merchprod-header { margin-bottom: 16px; }
.m-merchprod-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-merchprod-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-merchprod-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-merchprod-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-merchprod-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-merchprod-price { font-size: 15px; font-weight: 700; color: #10B981; flex-shrink: 0; }
.m-merchprod-bottom { display: flex; justify-content: space-between; align-items: center; }
.m-merchprod-stock { font-size: 12px; color: #A8A8B8; }
.m-merchprod-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap;
}
.m-merchprod-badge-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-merchprod-badge-inactive { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-merchprod-actions { display: flex; gap: 8px; margin-top: 10px; justify-content: flex-end; }
.m-merchprod-btn { width: 36px; height: 36px; border-radius: 8px; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 13px; }
.m-merchprod-btn-edit { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-merchprod-btn-del { background: rgba(239,68,68,0.15); color: #EF4444; }
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

<div class="m-merchprod">
    <div class="m-merchprod-header">
        <h2 class="m-merchprod-title">Merchandise Products</h2>
        <p class="m-merchprod-sub"><?= $totalProducts ?> product<?= $totalProducts !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($products)): ?>
        <div class="m-empty-state">
            <i class="fas fa-box-open"></i>
            <p>No products found</p>
        </div>
    <?php else: ?>
        <?php foreach ($products as $p):
            $isActive = (int)($p['is_active'] ?? 1);
        ?>
        <div class="m-merchprod-card" data-id="<?= (int)$p['id'] ?>" data-name="<?= htmlspecialchars($p['name'] ?? '', ENT_QUOTES) ?>" data-sku="<?= htmlspecialchars($p['sku'] ?? '', ENT_QUOTES) ?>" data-category="<?= (int)($p['category_id'] ?? 0) ?>" data-description="<?= htmlspecialchars($p['description'] ?? '', ENT_QUOTES) ?>" data-price="<?= (float)($p['price'] ?? 0) ?>" data-cost="<?= (float)($p['cost_price'] ?? 0) ?>" data-active="<?= $isActive ?>" data-track="<?= (int)($p['track_inventory'] ?? 1) ?>">
            <div class="m-merchprod-top">
                <span class="m-merchprod-name"><?= htmlspecialchars($p['name']) ?></span>
                <span class="m-merchprod-price">$<?= number_format((float)($p['price'] ?? 0), 2) ?></span>
            </div>
            <div class="m-merchprod-bottom">
                <span class="m-merchprod-stock"><?= htmlspecialchars($p['sku'] ?? '') ?></span>
                <span class="m-merchprod-badge m-merchprod-badge-<?= $isActive ? 'active' : 'inactive' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
            </div>
            <div class="m-merchprod-actions">
                <button class="m-merchprod-btn m-merchprod-btn-edit" onclick="mEditProd(this.closest('.m-merchprod-card'))"><i class="fas fa-pencil-alt"></i></button>
                <button class="m-merchprod-btn m-merchprod-btn-del" onclick="mDeleteProd(this.closest('.m-merchprod-card'))"><i class="fas fa-trash"></i></button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button class="m-fab" onclick="mOpenProdSheet('create')"><i class="fas fa-plus"></i></button>

<div id="mProdSheet" class="m-sheet-overlay">
    <div class="m-sheet">
        <div class="m-sheet-handle"></div>
        <h3 class="m-sheet-title" id="mProdSheetTitle">Add Product</h3>
        <form method="POST" action="process_merchandise_products.php" id="mProdForm" enctype="multipart/form-data">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" id="mProdAction" value="create">
            <input type="hidden" name="id" id="mProdId" value="">
            <div class="m-form-group">
                <label class="m-form-label">Name *</label>
                <input type="text" name="name" id="mProdName" class="m-form-input" required placeholder="e.g., Arctic Wolves Jersey">
            </div>
            <div class="m-form-row">
                <div class="m-form-group">
                    <label class="m-form-label">SKU</label>
                    <input type="text" name="sku" id="mProdSku" class="m-form-input" placeholder="e.g., AW-001">
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Category</label>
                    <select name="category_id" id="mProdCategory" class="m-form-select">
                        <option value="">None</option>
                        <?php foreach ($prodCategories as $pc): ?>
                        <option value="<?= (int)$pc['id'] ?>"><?= htmlspecialchars($pc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="m-form-group">
                <label class="m-form-label">Description</label>
                <textarea name="description" id="mProdDesc" class="m-form-textarea" placeholder="Product description"></textarea>
            </div>
            <div class="m-form-row">
                <div class="m-form-group">
                    <label class="m-form-label">Price *</label>
                    <input type="number" name="price" id="mProdPrice" class="m-form-input" required min="0" step="0.01" placeholder="0.00">
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Cost Price</label>
                    <input type="number" name="cost_price" id="mProdCost" class="m-form-input" min="0" step="0.01" placeholder="0.00">
                </div>
            </div>
            <div class="m-form-group">
                <label class="m-form-label">Status</label>
                <select name="is_active" id="mProdActive" class="m-form-select">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="m-form-group">
                <label class="m-form-label">Image</label>
                <input type="file" name="image" class="m-form-input" accept="image/jpeg,image/png,image/gif,image/webp">
            </div>
            <button type="submit" class="m-btn-submit"><i class="fas fa-save"></i> Save Product</button>
        </form>
    </div>
</div>

<script>
(function(){
    var csrfToken = document.querySelector('#mProdForm [name="csrf_token"]') ? document.querySelector('#mProdForm [name="csrf_token"]').value : '';

    window.mOpenProdSheet = function(mode, card) {
        var sheet = document.getElementById('mProdSheet');
        document.getElementById('mProdSheetTitle').textContent = mode === 'edit' ? 'Edit Product' : 'Add Product';
        document.getElementById('mProdAction').value = mode === 'edit' ? 'update' : 'create';
        if (mode === 'edit' && card) {
            document.getElementById('mProdId').value = card.dataset.id;
            document.getElementById('mProdName').value = card.dataset.name;
            document.getElementById('mProdSku').value = card.dataset.sku;
            document.getElementById('mProdCategory').value = card.dataset.category || '';
            document.getElementById('mProdDesc').value = card.dataset.description;
            document.getElementById('mProdPrice').value = card.dataset.price;
            document.getElementById('mProdCost').value = card.dataset.cost > 0 ? card.dataset.cost : '';
            document.getElementById('mProdActive').value = card.dataset.active;
        } else {
            document.getElementById('mProdId').value = '';
            document.getElementById('mProdName').value = '';
            document.getElementById('mProdSku').value = '';
            document.getElementById('mProdCategory').value = '';
            document.getElementById('mProdDesc').value = '';
            document.getElementById('mProdPrice').value = '';
            document.getElementById('mProdCost').value = '';
            document.getElementById('mProdActive').value = '1';
        }
        sheet.classList.add('m-active');
    };

    window.mEditProd = function(card) { mOpenProdSheet('edit', card); };

    window.mDeleteProd = function(card) {
        if (!confirm('Delete "' + card.dataset.name + '"?')) return;
        fetch('process_merchandise_products.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: 'action=delete&id=' + encodeURIComponent(card.dataset.id) + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) { persistToast(d.message || 'Deleted!', 'success'); location.reload(); }
            else { mToast(d.message || 'Error', 'error'); }
        })
        .catch(function() { mToast('An error occurred', 'error'); });
    };

    document.getElementById('mProdSheet').addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('m-active');
    });

    document.getElementById('mProdForm').addEventListener('submit', function(e) {
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
            if (d.success) { persistToast(d.message || 'Saved!', 'success'); document.getElementById('mProdSheet').classList.remove('m-active'); location.reload(); }
            else { mToast(d.message || 'Error', 'error'); }
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
