<?php
/**
 * PWA Accounting Products - Mobile-native product catalog
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$canAccessAccounting) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$products = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description, price, stock_quantity, is_active FROM products ORDER BY name ASC LIMIT 30");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $products = []; }

$merchCategories = [];
try {
    $catStmt = $pdo->query("SELECT id, name FROM merchandise_categories WHERE is_active = 1 ORDER BY name");
    $merchCategories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $merchCategories = []; }
?>
<style>
.m-products { padding: 16px; font-family: Inter, sans-serif; padding-bottom: 100px; }
.m-products-header { margin-bottom: 16px; }
.m-products-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-products-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-products-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.m-product-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; min-height: 44px; position: relative;
}
.m-product-name { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-product-desc { font-size: 11px; color: #6B6B7B; margin-bottom: 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-product-price { font-size: 18px; font-weight: 700; color: #8B5CF6; margin-bottom: 6px; }
.m-product-meta { display: flex; justify-content: space-between; align-items: center; }
.m-product-stock { font-size: 11px; color: #A8A8B8; }
.m-product-badge {
    font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600;
    display: inline-block;
}
.m-product-badge-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-product-badge-inactive { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-product-actions {
    display: flex; gap: 8px; margin-top: 10px; border-top: 1px solid #2D2D3F; padding-top: 10px;
}
.m-product-btn {
    flex: 1; display: flex; align-items: center; justify-content: center; gap: 4px;
    min-height: 44px; border: none; border-radius: 8px; font-size: 12px; font-weight: 600;
    cursor: pointer; font-family: Inter, sans-serif;
}
.m-product-btn-edit { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-product-btn-delete { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }

/* FAB */
.m-product-fab {
    position: fixed; bottom: 80px; right: 20px; z-index: 50;
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    color: #fff; font-size: 22px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(107,70,193,0.4);
    border: none; cursor: pointer;
}

/* Bottom-sheet */
.m-product-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 200; }
.m-product-overlay.m-show { display: flex; align-items: flex-end; }
.m-product-sheet {
    width: 100%; max-height: 90vh; background: #16161F;
    border-radius: 16px 16px 0 0;
    padding: 20px; overflow-y: auto; -webkit-overflow-scrolling: touch;
}
.m-product-handle {
    width: 40px; height: 4px; background: #3D3D4F; border-radius: 2px;
    margin: 0 auto 16px;
}
.m-product-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-product-field { margin-bottom: 14px; }
.m-product-field label {
    display: block; font-size: 12px; font-weight: 600; color: #A8A8B8;
    margin-bottom: 6px;
}
.m-product-field input,
.m-product-field select,
.m-product-field textarea {
    width: 100%; padding: 10px 12px; border-radius: 10px;
    background: #0A0A0F; border: 1px solid #2D2D3F; color: #fff;
    font-size: 14px; font-family: Inter, sans-serif;
    min-height: 44px; box-sizing: border-box;
}
.m-product-field textarea { min-height: 66px; resize: vertical; }
.m-product-field input:focus,
.m-product-field select:focus,
.m-product-field textarea:focus { outline: none; border-color: #6B46C1; }
.m-product-field-row { display: flex; gap: 10px; }
.m-product-field-row .m-product-field { flex: 1; }
.m-product-modal-actions {
    display: flex; gap: 10px; margin-top: 16px; padding-bottom: env(safe-area-inset-bottom, 12px);
}
.m-product-btn-cancel, .m-product-btn-save {
    flex: 1; padding: 12px; border-radius: 10px; font-size: 14px; font-weight: 600;
    border: none; cursor: pointer; min-height: 44px; font-family: Inter, sans-serif;
}
.m-product-btn-cancel { background: #2D2D3F; color: #A8A8B8; }
.m-product-btn-save { background: linear-gradient(135deg, #6B46C1, #8B5CF6); color: #fff; }
</style>

<div class="m-products">
    <div class="m-products-header">
        <h2 class="m-products-title">Products</h2>
        <p class="m-products-sub"><?= count($products) ?> product<?= count($products) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($products)): ?>
        <div class="m-empty-state">
            <i class="fas fa-box-open"></i>
            No products found
        </div>
    <?php else: ?>
        <div class="m-products-grid">
        <?php foreach ($products as $prod):
            $isActive = (int)($prod['is_active'] ?? 0);
            $stock = (int)($prod['stock_quantity'] ?? 0);
        ?>
            <div class="m-product-card">
                <div class="m-product-name"><?= htmlspecialchars($prod['name'] ?? 'Product') ?></div>
                <div class="m-product-desc"><?= htmlspecialchars($prod['description'] ?? '') ?></div>
                <div class="m-product-price">$<?= number_format((float)($prod['price'] ?? 0), 2) ?></div>
                <div class="m-product-meta">
                    <span class="m-product-stock"><i class="fas fa-box"></i> <?= $stock ?> in stock</span>
                    <span class="m-product-badge m-product-badge-<?= $isActive ? 'active' : 'inactive' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
                </div>
                <div class="m-product-actions">
                    <button class="m-product-btn m-product-btn-edit" data-id="<?= (int)$prod['id'] ?>" data-name="<?= htmlspecialchars($prod['name'] ?? '', ENT_QUOTES) ?>" data-description="<?= htmlspecialchars($prod['description'] ?? '', ENT_QUOTES) ?>" data-price="<?= (float)($prod['price'] ?? 0) ?>" data-stock="<?= $stock ?>" data-active="<?= $isActive ?>"><i class="fas fa-pencil-alt"></i> Edit</button>
                    <button class="m-product-btn m-product-btn-delete" data-id="<?= (int)$prod['id'] ?>"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- FAB: Add Product -->
<button class="m-product-fab" onclick="mOpenProductModal()" title="Add Product">
    <i class="fas fa-plus"></i>
</button>

<!-- Add/Edit Product Bottom-Sheet -->
<div class="m-product-overlay" id="mProductModal">
    <div class="m-product-sheet">
        <div class="m-product-handle"></div>
        <div class="m-product-sheet-title" id="mProductModalTitle">Add Product</div>
        <form method="POST" action="process_merchandise_products.php" id="mProductForm" enctype="multipart/form-data">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="create" id="mProductAction">
            <input type="hidden" name="id" value="" id="mProductId">
            <div class="m-product-field">
                <label for="mProductName">Product Name *</label>
                <input type="text" name="name" id="mProductName" placeholder="e.g., Team Jersey" required>
            </div>
            <div class="m-product-field-row">
                <div class="m-product-field">
                    <label for="mProductPrice">Price *</label>
                    <input type="number" name="price" id="mProductPrice" step="0.01" min="0" placeholder="0.00" required>
                </div>
                <div class="m-product-field">
                    <label for="mProductSku">SKU</label>
                    <input type="text" name="sku" id="mProductSku" placeholder="PROD-001">
                </div>
            </div>
            <div class="m-product-field">
                <label for="mProductCategory">Category</label>
                <select name="category_id" id="mProductCategory">
                    <option value="">-- No Category --</option>
                    <?php foreach ($merchCategories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="m-product-field">
                <label for="mProductDesc">Description</label>
                <textarea name="description" id="mProductDesc" placeholder="Product description..." rows="3"></textarea>
            </div>
            <div class="m-product-field-row">
                <div class="m-product-field">
                    <label for="mProductTrack">Track Inventory</label>
                    <select name="track_inventory" id="mProductTrack">
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
                <div class="m-product-field">
                    <label for="mProductStatus">Status</label>
                    <select name="is_active" id="mProductStatus">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="m-product-field">
                <label for="mProductImage">Product Image</label>
                <input type="file" name="image" id="mProductImage" accept="image/*">
            </div>
            <div class="m-product-modal-actions">
                <button type="button" class="m-product-btn-cancel" onclick="mCloseProductModal()">Cancel</button>
                <button type="submit" class="m-product-btn-save" id="mProductSaveBtn">Add Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden delete form -->
<form method="POST" action="process_merchandise_products.php" id="mProductDeleteForm" style="display:none;">
    <?= csrfTokenInput() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="" id="mProductDeleteId">
</form>

<script>
(function() {
    var modal = document.getElementById('mProductModal');
    var form = document.getElementById('mProductForm');

    window.mOpenProductModal = function() {
        document.getElementById('mProductModalTitle').textContent = 'Add Product';
        document.getElementById('mProductAction').value = 'create';
        document.getElementById('mProductId').value = '';
        document.getElementById('mProductName').value = '';
        document.getElementById('mProductPrice').value = '';
        document.getElementById('mProductSku').value = '';
        document.getElementById('mProductCategory').value = '';
        document.getElementById('mProductDesc').value = '';
        document.getElementById('mProductTrack').value = '1';
        document.getElementById('mProductStatus').value = '1';
        document.getElementById('mProductImage').value = '';
        document.getElementById('mProductSaveBtn').textContent = 'Add Product';
        modal.classList.add('m-show');
    };

    window.mEditProduct = function(id, name, description, price, stock, active) {
        document.getElementById('mProductModalTitle').textContent = 'Edit Product';
        document.getElementById('mProductAction').value = 'update';
        document.getElementById('mProductId').value = id;
        document.getElementById('mProductName').value = name || '';
        document.getElementById('mProductPrice').value = price || '';
        document.getElementById('mProductSku').value = '';
        document.getElementById('mProductCategory').value = '';
        document.getElementById('mProductDesc').value = description || '';
        document.getElementById('mProductTrack').value = '1';
        document.getElementById('mProductStatus').value = active ? '1' : '0';
        document.getElementById('mProductImage').value = '';
        document.getElementById('mProductSaveBtn').textContent = 'Save Changes';
        modal.classList.add('m-show');
    };

    window.mCloseProductModal = function() {
        modal.classList.remove('m-show');
    };

    window.mDeleteProduct = async function(id) {
        if (await showConfirmModal('Are you sure you want to delete this product? This cannot be undone.')) {
            document.getElementById('mProductDeleteId').value = id;
            document.getElementById('mProductDeleteForm').submit();
        }
    };

    // Close on overlay tap
    modal.addEventListener('click', function(e) {
        if (e.target === modal) mCloseProductModal();
    });

    // Delegate edit/delete button clicks
    document.addEventListener('click', function(e) {
        var editBtn = e.target.closest('.m-product-btn-edit');
        if (editBtn) {
            mEditProduct(
                editBtn.getAttribute('data-id'),
                editBtn.getAttribute('data-name'),
                editBtn.getAttribute('data-description'),
                editBtn.getAttribute('data-price'),
                editBtn.getAttribute('data-stock'),
                editBtn.getAttribute('data-active') === '1'
            );
            return;
        }
        var deleteBtn = e.target.closest('.m-product-btn-delete');
        if (deleteBtn) {
            mDeleteProduct(deleteBtn.getAttribute('data-id'));
        }
    });

    // AJAX form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = document.getElementById('mProductSaveBtn');
        var origText = btn.textContent;
        btn.textContent = 'Saving...';
        btn.disabled = true;

        fetch(form.getAttribute('action'), {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.textContent = origText;
            btn.disabled = false;
            if (data.success) {
                mCloseProductModal();
                persistToast(data.message || 'Operation completed successfully', 'success');
                location.reload();
            } else {
                showToast('Error: ' + (data.message || 'Failed to save'), 'error');
            }
        })
        .catch(function() {
            btn.textContent = origText;
            btn.disabled = false;
            showToast('An error occurred. Please try again.', 'error');
        });
    });
})();
</script>
