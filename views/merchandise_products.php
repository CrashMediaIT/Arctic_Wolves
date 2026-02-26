<?php
require_once __DIR__ . '/../lib/image_helper.php';
// Merchandise Products Management View
// Fetch all merchandise categories for the dropdown
try {
    $categoriesStmt = $pdo->prepare("SELECT id, name FROM merchandise_categories WHERE is_active = 1 ORDER BY display_order ASC, name ASC");
    $categoriesStmt->execute();
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Merchandise categories fetch error: " . $e->getMessage());
    $categories = [];
}

// Fetch all merchandise products from database
try {
    $stmt = $pdo->prepare("
        SELECT mp.*, 
               mc.name as category_name,
               u.first_name as creator_first_name, u.last_name as creator_last_name,
               (SELECT SUM(mps.quantity) FROM merchandise_product_sizes mps WHERE mps.product_id = mp.id) as total_quantity
        FROM merchandise_products mp
        LEFT JOIN merchandise_categories mc ON mp.category_id = mc.id
        LEFT JOIN users u ON mp.created_by = u.id
        ORDER BY mp.created_at DESC
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $products = decryptUserRows($products);
    // Build created_by_name from decrypted fields
    foreach ($products as &$p) {
        $p['created_by_name'] = (!empty($p['creator_first_name'])) ? $p['creator_first_name'] . ' ' . $p['creator_last_name'] : null;
    }
    unset($p);
    
    // Fetch sizes for each product
    $sizesStmt = $pdo->prepare("SELECT * FROM merchandise_product_sizes WHERE product_id = ? ORDER BY id ASC");
    foreach ($products as &$product) {
        $sizesStmt->execute([$product['id']]);
        $product['sizes'] = $sizesStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Merchandise products fetch error: " . $e->getMessage());
    $products = [];
}

$totalProducts = count($products);
$activeProducts = count(array_filter($products, function($p) { return !empty($p['is_active']); }));
$totalInventory = array_sum(array_column($products, 'total_quantity'));
$totalValue = array_sum(array_map(function($p) { 
    return ($p['price'] ?? 0) * ($p['total_quantity'] ?? 0); 
}, $products));

// Filter by category if specified
$filterCategory = $_GET['category'] ?? '';
?>

<style>
/* Products Page Header - Financial Reports Hub Style */
.products-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 20px;
}
.products-page-header .page-header-content {
    display: flex;
    align-items: center;
    gap: 20px;
}
.products-page-header .page-header-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, var(--primary), #5a0080);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    box-shadow: 0 8px 24px rgba(107, 70, 193, 0.3);
}
.products-page-header .page-title {
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 4px 0;
    letter-spacing: -0.5px;
}
.products-page-header .page-description {
    font-size: 14px;
    color: var(--text-dim);
    margin: 0;
}
.products-page-header .page-header-stats {
    display: flex;
    gap: 24px;
}
.products-page-header .header-stat {
    text-align: center;
}
.products-page-header .stat-value {
    display: block;
    font-size: 24px;
    font-weight: 800;
    color: var(--text-white);
}
.products-page-header .stat-label {
    display: block;
    font-size: 11px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 4px;
}
</style>

<!-- Merchandise Products Management View -->
<div class="products-page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-tshirt"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">Merchandise Products</h1>
            <p class="page-description">Manage merchandise inventory with sizing and quantities for the POS system</p>
        </div>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value"><?= $totalProducts ?></span>
            <span class="stat-label">Products</span>
        </div>
        <div class="header-stat">
            <span class="stat-value"><?= $activeProducts ?></span>
            <span class="stat-label">Active</span>
        </div>
        <div class="header-stat">
            <span class="stat-value"><?= $totalInventory ?? 0 ?></span>
            <span class="stat-label">Total Items</span>
        </div>
        <div class="header-stat">
            <span class="stat-value">$<?= number_format($totalValue, 0) ?></span>
            <span class="stat-label">Inventory Value</span>
        </div>
    </div>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
<div class="success-alert" style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-check-circle" style="color: #10b981; font-size: 20px;"></i>
    <span style="color: #10b981; font-weight: 600;">Operation completed successfully!</span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #10b981; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>
<?php if (isset($_GET['status']) && $_GET['status'] === 'error'): ?>
<div class="error-alert" style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-exclamation-circle" style="color: #ef4444; font-size: 20px;"></i>
    <span style="color: #ef4444; font-weight: 600;"><?= htmlspecialchars($_GET['message'] ?? 'An error occurred') ?></span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #ef4444; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>

<div class="merchandise-content">
    <div class="content-card">
        <div class="card-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <h3><i class="fas fa-tshirt"></i> Merchandise Products</h3>
                <?php if (!empty($categories)): ?>
                <select id="category-filter" class="form-input" style="width: auto; padding: 8px 12px;" onchange="filterByCategory(this.value)">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $filterCategory == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn-primary" onclick="openModal('add-product-modal')">
                <i class="fas fa-plus"></i> Add Product
            </button>
        </div>
        <div class="card-body">
            <?php if (empty($products)): ?>
                <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                    <i class="fas fa-tshirt" style="font-size: 48px; color: var(--text-dim); margin-bottom: 16px;"></i>
                    <p style="color: var(--text-dim);">No products yet. Click "Add Product" to create one.</p>
                    <?php if (empty($categories)): ?>
                        <p style="color: var(--text-dim); margin-top: 8px;">
                            <a href="?page=merchandise_categories" style="color: var(--primary-light);">Create a category first</a> to organize your products.
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="products-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
                    <?php foreach ($products as $product): 
                        if ($filterCategory && $product['category_id'] != $filterCategory) continue;
                    ?>
                        <div class="product-card" style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; position: relative;">
                            <!-- Product Image -->
                            <?php if (!empty($product['image_url'])): ?>
                                <div class="product-image" style="height: 180px; overflow: hidden;">
                                    <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $product['image_url'])) ?>" alt="<?= htmlspecialchars($product['name']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                            <?php else: ?>
                                <div class="product-image-placeholder" style="height: 180px; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-tshirt" style="font-size: 48px; color: rgba(255,255,255,0.5);"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Status Badge -->
                            <div class="product-badge" style="position: absolute; top: 12px; right: 12px;">
                                <span class="status-badge" style="padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; background: <?= $product['is_active'] ? 'rgba(16, 185, 129, 0.9)' : 'rgba(239, 68, 68, 0.9)' ?>; color: #fff;">
                                    <?= $product['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                            
                            <div class="product-details" style="padding: 16px;">
                                <!-- Category Tag -->
                                <?php if (!empty($product['category_name'])): ?>
                                    <span style="display: inline-block; padding: 2px 8px; background: rgba(107, 70, 193, 0.1); color: var(--primary-light); border-radius: 4px; font-size: 11px; margin-bottom: 8px;"><?= htmlspecialchars($product['category_name']) ?></span>
                                <?php endif; ?>
                                
                                <h4 style="font-size: 16px; font-weight: 600; color: var(--text); margin-bottom: 4px;"><?= htmlspecialchars($product['name']) ?></h4>
                                
                                <?php if (!empty($product['sku'])): ?>
                                    <p style="color: var(--text-dim); font-size: 12px; margin-bottom: 8px;">SKU: <?= htmlspecialchars($product['sku']) ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($product['description'])): ?>
                                    <p style="color: var(--text-dim); font-size: 13px; margin-bottom: 12px; line-height: 1.4;"><?= htmlspecialchars(substr($product['description'], 0, 80)) ?><?= strlen($product['description']) > 80 ? '...' : '' ?></p>
                                <?php endif; ?>
                                
                                <!-- Price -->
                                <div style="font-size: 24px; font-weight: 700; color: var(--primary-light); margin-bottom: 12px;">
                                    $<?= number_format($product['price'], 2) ?>
                                </div>
                                
                                <!-- Sizes and Quantities -->
                                <?php if (!empty($product['sizes'])): ?>
                                    <div class="sizes-display" style="margin-bottom: 16px;">
                                        <p style="font-size: 12px; color: var(--text-dim); margin-bottom: 8px;">Sizes & Inventory:</p>
                                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                            <?php foreach ($product['sizes'] as $size): ?>
                                                <span style="padding: 4px 10px; background: <?= $size['quantity'] > 0 ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)' ?>; color: <?= $size['quantity'] > 0 ? '#10b981' : '#ef4444' ?>; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                                    <?= htmlspecialchars($size['size']) ?>: <?= $size['quantity'] ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div style="margin-bottom: 16px;">
                                        <p style="font-size: 12px; color: var(--text-dim);">No sizes configured</p>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Total Inventory -->
                                <div style="display: flex; align-items: center; gap: 8px; padding: 8px 0; border-top: 1px solid var(--border); margin-bottom: 12px;">
                                    <i class="fas fa-boxes" style="color: var(--text-dim);"></i>
                                    <span style="font-size: 13px; color: var(--text);">Total Inventory: <strong><?= $product['total_quantity'] ?? 0 ?></strong></span>
                                </div>
                                
                                <!-- Actions -->
                                <div class="product-actions" style="display: flex; gap: 8px;">
                                    <button class="btn-action" onclick='editProduct(<?= json_encode($product) ?>)' title="Edit" style="flex: 1; padding: 8px; border: none; border-radius: 6px; background: rgba(107, 70, 193, 0.1); color: var(--primary-light); cursor: pointer;">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn-action" onclick='manageInventory(<?= json_encode(["id" => $product["id"], "name" => $product["name"]]) ?>)' title="Manage Inventory" style="padding: 8px 12px; border: none; border-radius: 6px; background: rgba(16, 185, 129, 0.1); color: #10b981; cursor: pointer;">
                                        <i class="fas fa-warehouse"></i>
                                    </button>
                                    <button class="btn-action" onclick='recordShipment(<?= json_encode(["id" => $product["id"], "name" => $product["name"]]) ?>)' title="Record Shipment" style="padding: 8px 12px; border: none; border-radius: 6px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; cursor: pointer;">
                                        <i class="fas fa-truck"></i>
                                    </button>
                                    <button class="btn-action" onclick='stockAudit(<?= json_encode(["id" => $product["id"], "name" => $product["name"]]) ?>)' title="Stock Audit" style="padding: 8px 12px; border: none; border-radius: 6px; background: rgba(168, 85, 247, 0.1); color: #a855f7; cursor: pointer;">
                                        <i class="fas fa-clipboard-check"></i>
                                    </button>
                                    <button class="btn-action" onclick="toggleProductStatus(<?= intval($product['id']) ?>, <?= intval($product['is_active']) ?>)" title="<?= $product['is_active'] ? 'Deactivate' : 'Activate' ?>" style="padding: 8px 12px; border: none; border-radius: 6px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; cursor: pointer;">
                                        <i class="fas fa-toggle-<?= $product['is_active'] ? 'on' : 'off' ?>"></i>
                                    </button>
                                    <button class="btn-action" onclick='deleteProduct(<?= json_encode(["id" => $product["id"], "name" => $product["name"]]) ?>)' title="Delete" style="padding: 8px 12px; border: none; border-radius: 6px; background: rgba(239, 68, 68, 0.1); color: #ef4444; cursor: pointer;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div id="add-product-modal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-plus"></i> Add Merchandise Product</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('add-product-modal')">&times;</button>
        </div>
        <form method="POST" action="process_merchandise_products.php" enctype="multipart/form-data" id="add-product-form">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create">
            
            <div class="modal-body">
                <div class="form-row" style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="name" class="form-input" required placeholder="e.g., Arctic Wolves Jersey">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" class="form-input" placeholder="e.g., AW-JRS-001">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-input">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3" placeholder="Product description for customers"></textarea>
                </div>
                
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Price *</label>
                        <input type="number" name="price" class="form-input" required min="0" step="0.01" placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Cost Price</label>
                        <input type="number" name="cost_price" class="form-input" min="0" step="0.01" placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="is_active" class="form-input">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Product Image</label>
                    <input type="file" name="image" class="form-input" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small style="color: var(--text-dim);">Recommended: 600x600px, max 5MB</small>
                </div>
                
                <!-- Sizes and Quantities Section -->
                <div class="form-group" style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <label class="form-label" style="margin-bottom: 0;">Sizes & Inventory</label>
                        <button type="button" class="btn btn-secondary" onclick="addSizeRow('add')" style="padding: 6px 12px; font-size: 12px;">
                            <i class="fas fa-plus"></i> Add Size
                        </button>
                    </div>
                    <div id="add-sizes-container">
                        <!-- Size rows will be added here -->
                        <div class="size-row" style="display: grid; grid-template-columns: 1fr 1fr 40px; gap: 12px; margin-bottom: 12px;">
                            <input type="text" name="sizes[]" class="form-input" placeholder="Size (e.g., S, M, L, XL)">
                            <input type="number" name="quantities[]" class="form-input" placeholder="Quantity" min="0" value="0">
                            <button type="button" class="btn-remove-size" onclick="this.parentElement.remove()" style="padding: 8px; background: rgba(239, 68, 68, 0.1); border: none; border-radius: 6px; color: #ef4444; cursor: pointer;" title="Remove">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <p style="font-size: 12px; color: var(--text-dim);">Add sizes like XS, S, M, L, XL, XXL, or custom sizes like Youth S, Adult L</p>
                </div>
                
                <div class="form-group">
                    <label class="form-checkbox" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="track_inventory" value="1" checked style="width: 16px; height: 16px;">
                        <span>Track inventory levels</span>
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-product-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<div id="edit-product-modal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Merchandise Product</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('edit-product-modal')">&times;</button>
        </div>
        <form method="POST" action="process_merchandise_products.php" enctype="multipart/form-data" id="edit-product-form">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-product-id">
            
            <div class="modal-body">
                <div class="form-row" style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="name" id="edit-product-name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" id="edit-product-sku" class="form-input">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category_id" id="edit-product-category" class="form-input">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-product-description" class="form-textarea" rows="3"></textarea>
                </div>
                
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Price *</label>
                        <input type="number" name="price" id="edit-product-price" class="form-input" required min="0" step="0.01">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Cost Price</label>
                        <input type="number" name="cost_price" id="edit-product-cost" class="form-input" min="0" step="0.01">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="is_active" id="edit-product-status" class="form-input">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Product Image</label>
                    <div id="edit-product-image-preview" style="margin-bottom: 8px;"></div>
                    <input type="file" name="image" class="form-input" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small style="color: var(--text-dim);">Leave empty to keep current image</small>
                </div>
                
                <!-- Sizes and Quantities Section -->
                <div class="form-group" style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <label class="form-label" style="margin-bottom: 0;">Sizes & Inventory</label>
                        <button type="button" class="btn btn-secondary" onclick="addSizeRow('edit')" style="padding: 6px 12px; font-size: 12px;">
                            <i class="fas fa-plus"></i> Add Size
                        </button>
                    </div>
                    <div id="edit-sizes-container">
                        <!-- Size rows will be populated by JavaScript -->
                    </div>
                    <p style="font-size: 12px; color: var(--text-dim);">Add sizes like XS, S, M, L, XL, XXL, or custom sizes like Youth S, Adult L</p>
                </div>
                
                <div class="form-group">
                    <label class="form-checkbox" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="track_inventory" id="edit-product-track" value="1" style="width: 16px; height: 16px;">
                        <span>Track inventory levels</span>
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-product-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Manage Inventory Modal -->
<div id="inventory-modal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-warehouse"></i> Manage Inventory - <span id="inventory-product-name"></span></h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('inventory-modal')">&times;</button>
        </div>
        <form method="POST" action="process_merchandise_products.php" id="inventory-form">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update_inventory">
            <input type="hidden" name="product_id" id="inventory-product-id">
            
            <div class="modal-body">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <p style="color: var(--text-dim);">Update quantities for each size or add new sizes.</p>
                    <button type="button" class="btn btn-secondary" onclick="addSizeRow('inventory')" style="padding: 6px 12px; font-size: 12px;">
                        <i class="fas fa-plus"></i> Add Size
                    </button>
                </div>
                <div id="inventory-sizes-container">
                    <!-- Size rows will be populated here -->
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('inventory-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Inventory</button>
            </div>
        </form>
    </div>
</div>

<!-- Record Shipment Modal -->
<div id="shipment-modal" class="modal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-truck"></i> Record Shipment - <span id="shipment-product-name"></span></h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('shipment-modal')">&times;</button>
        </div>
        <form method="POST" action="process_merchandise_products.php" id="shipment-form">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="record_shipment">
            <input type="hidden" name="product_id" id="shipment-product-id">
            
            <div class="modal-body">
                <p style="color: var(--text-dim); margin-bottom: 16px;">Enter the quantities received in this shipment. Current stock will be increased by the amounts entered.</p>
                
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="form-group">
                        <label class="form-label">Reference / PO Number</label>
                        <input type="text" name="reference" class="form-input" placeholder="e.g., PO-2024-001">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-input" placeholder="Optional notes about this shipment">
                    </div>
                </div>
                
                <div style="border-top: 1px solid var(--border); padding-top: 16px;">
                    <div style="display: grid; grid-template-columns: 1fr 100px 120px; gap: 12px; margin-bottom: 8px; padding: 0 4px;">
                        <span style="font-weight: 600; font-size: 12px; text-transform: uppercase; color: var(--text-dim);">Size</span>
                        <span style="font-weight: 600; font-size: 12px; text-transform: uppercase; color: var(--text-dim);">Current Stock</span>
                        <span style="font-weight: 600; font-size: 12px; text-transform: uppercase; color: var(--text-dim);">Qty Received</span>
                    </div>
                    <div id="shipment-sizes-container">
                        <!-- Size rows will be populated here -->
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('shipment-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-truck"></i> Record Shipment</button>
            </div>
        </form>
    </div>
</div>

<!-- Stock Audit Modal -->
<div id="audit-modal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-clipboard-check"></i> Stock Audit - <span id="audit-product-name"></span></h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('audit-modal')">&times;</button>
        </div>
        <form method="POST" action="process_merchandise_products.php" id="audit-form">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="stock_audit">
            <input type="hidden" name="product_id" id="audit-product-id">
            
            <div class="modal-body">
                <p style="color: var(--text-dim); margin-bottom: 16px;">Count the actual physical stock for each size and enter the numbers below. The system will compare against recorded levels and highlight any discrepancies.</p>
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Audit Notes</label>
                    <input type="text" name="audit_notes" class="form-input" placeholder="e.g., Monthly inventory count - January 2025">
                </div>
                
                <div style="border-top: 1px solid var(--border); padding-top: 16px;">
                    <div style="display: grid; grid-template-columns: 1fr 100px 120px 100px; gap: 12px; margin-bottom: 8px; padding: 0 4px;">
                        <span style="font-weight: 600; font-size: 12px; text-transform: uppercase; color: var(--text-dim);">Size</span>
                        <span style="font-weight: 600; font-size: 12px; text-transform: uppercase; color: var(--text-dim);">System Qty</span>
                        <span style="font-weight: 600; font-size: 12px; text-transform: uppercase; color: var(--text-dim);">Actual Count</span>
                        <span style="font-weight: 600; font-size: 12px; text-transform: uppercase; color: var(--text-dim);">Difference</span>
                    </div>
                    <div id="audit-sizes-container">
                        <!-- Size rows will be populated here -->
                    </div>
                </div>
                
                <div id="audit-summary" style="display: none; margin-top: 16px; padding: 12px; border-radius: 8px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2);">
                    <p style="font-weight: 600; color: #f59e0b; margin-bottom: 4px;"><i class="fas fa-exclamation-triangle"></i> Discrepancies Detected</p>
                    <p id="audit-summary-text" style="font-size: 13px; color: var(--text);"></p>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('audit-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="button" class="btn btn-secondary" onclick="viewAuditHistory()" id="audit-history-btn" style="display:none;"><i class="fas fa-history"></i> View History</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-clipboard-check"></i> Submit Audit</button>
            </div>
        </form>
    </div>
</div>

<!-- Stock History Modal -->
<div id="stock-history-modal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-history"></i> Stock History - <span id="history-product-name"></span></h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('stock-history-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display: flex; gap: 8px; margin-bottom: 16px;">
                <button type="button" class="btn btn-secondary history-tab active" onclick="switchHistoryTab('movements')" id="tab-movements"><i class="fas fa-exchange-alt"></i> Stock Movements</button>
                <button type="button" class="btn btn-secondary history-tab" onclick="switchHistoryTab('audits')" id="tab-audits"><i class="fas fa-clipboard-check"></i> Audit History</button>
            </div>
            <div id="movements-content">
                <div id="movements-loading" style="text-align: center; padding: 40px; color: var(--text-dim);">
                    <i class="fas fa-spinner fa-spin" style="font-size: 24px;"></i>
                    <p>Loading stock movements...</p>
                </div>
                <div id="movements-table" style="display: none;"></div>
            </div>
            <div id="audits-content" style="display: none;">
                <div id="audits-loading" style="text-align: center; padding: 40px; color: var(--text-dim);">
                    <i class="fas fa-spinner fa-spin" style="font-size: 24px;"></i>
                    <p>Loading audit history...</p>
                </div>
                <div id="audits-table" style="display: none;"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('stock-history-modal')"><i class="fas fa-times"></i> Close</button>
        </div>
    </div>
</div>

<style>
/* Merchandise Products Styles */
.merchandise-content {
    padding: 0;
}

.content-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
}

.card-header h3 {
    font-size: 18px;
    font-weight: 600;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header h3 i {
    color: var(--primary-light);
}

.card-body {
    padding: 24px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
}

.btn-primary {
    background: var(--primary);
    color: #fff;
}

.btn-primary:hover {
    background: var(--primary-hover);
}

.btn-secondary {
    background: var(--bg);
    color: var(--text);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: var(--bg-secondary);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
}

.modal-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-title i {
    color: var(--primary-light);
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: var(--text-dim);
    cursor: pointer;
}

.modal-close:hover {
    color: var(--text);
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 24px;
    border-top: 1px solid var(--border);
}

.form-group {
    margin-bottom: 16px;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 6px;
}

.form-input, .form-textarea {
    width: 100%;
    padding: 10px 14px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    font-size: 14px;
}

.form-input:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--primary);
}

.form-textarea {
    resize: vertical;
    min-height: 80px;
}

.product-card:hover {
    border-color: var(--primary);
}

.btn-action:hover {
    opacity: 0.8;
}

.size-row {
    display: grid;
    grid-template-columns: 1fr 1fr 40px;
    gap: 12px;
    margin-bottom: 12px;
}
</style>

<script>
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function filterByCategory(categoryId) {
    const url = new URL(window.location.href);
    if (categoryId) {
        url.searchParams.set('category', categoryId);
    } else {
        url.searchParams.delete('category');
    }
    window.location.href = url.toString();
}

function addSizeRow(context) {
    let containerId;
    if (context === 'inventory') {
        containerId = 'inventory-sizes-container';
    } else if (context === 'edit') {
        containerId = 'edit-sizes-container';
    } else {
        containerId = 'add-sizes-container';
    }
    
    const container = document.getElementById(containerId);
    const row = document.createElement('div');
    row.className = 'size-row';
    row.style.cssText = 'display: grid; grid-template-columns: 1fr 1fr 40px; gap: 12px; margin-bottom: 12px;';
    
    if (context === 'inventory' || context === 'edit') {
        const sizeIdInput = document.createElement('input');
        sizeIdInput.type = 'hidden';
        sizeIdInput.name = 'size_ids[]';
        sizeIdInput.value = '';
        row.appendChild(sizeIdInput);
    }
    
    const sizeInput = document.createElement('input');
    sizeInput.type = 'text';
    sizeInput.name = 'sizes[]';
    sizeInput.className = 'form-input';
    sizeInput.placeholder = 'Size (e.g., S, M, L)';
    
    const quantityInput = document.createElement('input');
    quantityInput.type = 'number';
    quantityInput.name = 'quantities[]';
    quantityInput.className = 'form-input';
    quantityInput.placeholder = 'Quantity';
    quantityInput.min = '0';
    quantityInput.value = '0';
    
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn-remove-size';
    removeBtn.onclick = function() { this.parentElement.remove(); };
    removeBtn.style.cssText = 'padding: 8px; background: rgba(239, 68, 68, 0.1); border: none; border-radius: 6px; color: #ef4444; cursor: pointer;';
    removeBtn.title = 'Remove';
    removeBtn.innerHTML = '<i class="fas fa-times"></i>';
    
    row.appendChild(sizeInput);
    row.appendChild(quantityInput);
    row.appendChild(removeBtn);
    
    container.appendChild(row);
}

function editProduct(product) {
    document.getElementById('edit-product-id').value = product.id;
    document.getElementById('edit-product-name').value = product.name || '';
    document.getElementById('edit-product-sku').value = product.sku || '';
    document.getElementById('edit-product-category').value = product.category_id || '';
    document.getElementById('edit-product-description').value = product.description || '';
    document.getElementById('edit-product-price').value = product.price || '';
    document.getElementById('edit-product-cost').value = product.cost_price || '';
    document.getElementById('edit-product-status').value = product.is_active;
    document.getElementById('edit-product-track').checked = product.track_inventory == 1;
    
    // Show image preview
    const previewDiv = document.getElementById('edit-product-image-preview');
    if (product.image_url) {
        previewDiv.innerHTML = '<img src="' + product.image_url + '" style="max-width: 150px; max-height: 100px; border-radius: 8px; object-fit: cover;">';
    } else {
        previewDiv.innerHTML = '';
    }
    
    // Fetch and populate sizes
    fetch('process_merchandise_products.php?action=get_sizes&product_id=' + encodeURIComponent(product.id))
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to fetch sizes');
            }
            return response.json();
        })
        .then(data => {
            const container = document.getElementById('edit-sizes-container');
            container.innerHTML = '';
            
            if (data.sizes && data.sizes.length > 0) {
                data.sizes.forEach(size => {
                    const row = document.createElement('div');
                    row.className = 'size-row';
                    row.style.cssText = 'display: grid; grid-template-columns: 1fr 1fr 40px; gap: 12px; margin-bottom: 12px;';
                    
                    const sizeIdInput = document.createElement('input');
                    sizeIdInput.type = 'hidden';
                    sizeIdInput.name = 'size_ids[]';
                    sizeIdInput.value = size.id;
                    
                    const sizeInput = document.createElement('input');
                    sizeInput.type = 'text';
                    sizeInput.name = 'sizes[]';
                    sizeInput.className = 'form-input';
                    sizeInput.value = size.size;
                    sizeInput.placeholder = 'Size';
                    
                    const quantityInput = document.createElement('input');
                    quantityInput.type = 'number';
                    quantityInput.name = 'quantities[]';
                    quantityInput.className = 'form-input';
                    quantityInput.value = size.quantity;
                    quantityInput.min = '0';
                    
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'btn-remove-size';
                    removeBtn.onclick = function() { this.parentElement.remove(); };
                    removeBtn.style.cssText = 'padding: 8px; background: rgba(239, 68, 68, 0.1); border: none; border-radius: 6px; color: #ef4444; cursor: pointer;';
                    removeBtn.title = 'Remove';
                    removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                    
                    row.appendChild(sizeIdInput);
                    row.appendChild(sizeInput);
                    row.appendChild(quantityInput);
                    row.appendChild(removeBtn);
                    
                    container.appendChild(row);
                });
            } else {
                // Add empty row if no sizes exist
                addSizeRow('edit');
            }
        })
        .catch(error => {
            console.error('Error fetching sizes:', error);
            // Add empty row on error
            const container = document.getElementById('edit-sizes-container');
            container.innerHTML = '';
            addSizeRow('edit');
        });
    
    openModal('edit-product-modal');
}

function manageInventory(product) {
    document.getElementById('inventory-product-id').value = product.id;
    document.getElementById('inventory-product-name').textContent = product.name;
    
    // Fetch current sizes
    fetch('process_merchandise_products.php?action=get_sizes&product_id=' + encodeURIComponent(product.id))
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('inventory-sizes-container');
            container.innerHTML = '';
            
            if (data.sizes && data.sizes.length > 0) {
                data.sizes.forEach(size => {
                    const row = document.createElement('div');
                    row.className = 'size-row';
                    row.style.cssText = 'display: grid; grid-template-columns: 1fr 1fr 40px; gap: 12px; margin-bottom: 12px;';
                    row.innerHTML = `
                        <input type="hidden" name="size_ids[]" value="${size.id}">
                        <input type="text" name="sizes[]" class="form-input" value="${size.size}" placeholder="Size">
                        <input type="number" name="quantities[]" class="form-input" value="${size.quantity}" min="0">
                        <button type="button" class="btn-remove-size" onclick="this.parentElement.remove()" style="padding: 8px; background: rgba(239, 68, 68, 0.1); border: none; border-radius: 6px; color: #ef4444; cursor: pointer;" title="Remove">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    container.appendChild(row);
                });
            } else {
                // Add empty row
                addSizeRow('inventory');
            }
            
            openModal('inventory-modal');
        })
        .catch(error => {
            console.error('Error fetching sizes:', error);
            // Add empty row on error
            const container = document.getElementById('inventory-sizes-container');
            container.innerHTML = '';
            addSizeRow('inventory');
            openModal('inventory-modal');
        });
}

function toggleProductStatus(id, currentStatus) {
    if (!confirm('Are you sure you want to ' + (currentStatus ? 'deactivate' : 'activate') + ' this product?')) {
        return;
    }
    
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    const csrfToken = csrfInput ? csrfInput.value : '';
    
    fetch('process_merchandise_products.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=toggle_status&id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            persistToast(data.message || 'Operation completed successfully', 'success');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        location.reload();
    });
}

function deleteProduct(product) {
    if (!confirm('Are you sure you want to delete "' + product.name + '"? This action cannot be undone.')) {
        return;
    }
    
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    const csrfToken = csrfInput ? csrfInput.value : '';
    
    fetch('process_merchandise_products.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=delete&id=' + encodeURIComponent(product.id) + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            persistToast(data.message || 'Operation completed successfully', 'success');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        location.reload();
    });
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(modal => {
            closeModal(modal.id);
        });
    }
});

// Show notification helper - uses DOM methods for security
function showNotification(message, type) {
    var existing = document.querySelector('.notification-widget');
    if (existing) existing.remove();
    
    var div = document.createElement('div');
    div.className = 'notification-widget';
    div.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; padding: 16px 24px; border-radius: 8px; display: flex; align-items: center; gap: 12px;';
    if (type === 'success') {
        div.style.background = 'rgba(16, 185, 129, 0.95)';
        div.style.color = '#fff';
    } else {
        div.style.background = 'rgba(239, 68, 68, 0.95)';
        div.style.color = '#fff';
    }
    
    // Create icon element safely
    var icon = document.createElement('i');
    icon.className = 'fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle');
    div.appendChild(icon);
    
    // Create text element safely (textContent is XSS-safe)
    var text = document.createElement('span');
    text.textContent = message;
    div.appendChild(text);
    
    // Create close button safely
    var closeBtn = document.createElement('button');
    closeBtn.style.cssText = 'margin-left: 16px; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;';
    closeBtn.innerHTML = '&times;';
    closeBtn.addEventListener('click', function() { div.remove(); });
    div.appendChild(closeBtn);
    
    document.body.appendChild(div);
    setTimeout(function() { if (div.parentElement) div.remove(); }, 5000);
}

// Record Shipment - loads sizes and opens the shipment modal
function recordShipment(product) {
    document.getElementById('shipment-product-id').value = product.id;
    document.getElementById('shipment-product-name').textContent = product.name;
    
    fetch('process_merchandise_products.php?action=get_sizes&product_id=' + encodeURIComponent(product.id))
        .then(response => response.json())
        .then(data => {
            var container = document.getElementById('shipment-sizes-container');
            container.innerHTML = '';
            
            if (data.sizes && data.sizes.length > 0) {
                data.sizes.forEach(function(size) {
                    var row = document.createElement('div');
                    row.style.cssText = 'display: grid; grid-template-columns: 1fr 100px 120px; gap: 12px; margin-bottom: 8px; align-items: center;';
                    
                    var sizeIdInput = document.createElement('input');
                    sizeIdInput.type = 'hidden';
                    sizeIdInput.name = 'size_ids[]';
                    sizeIdInput.value = size.id;
                    
                    var sizeLabel = document.createElement('span');
                    sizeLabel.style.cssText = 'font-weight: 600; padding: 8px 12px; background: var(--bg); border-radius: 6px;';
                    sizeLabel.textContent = size.size;
                    
                    var currentQty = document.createElement('span');
                    currentQty.style.cssText = 'text-align: center; padding: 8px; background: var(--bg); border-radius: 6px; color: var(--text-dim);';
                    currentQty.textContent = size.quantity;
                    
                    var qtyInput = document.createElement('input');
                    qtyInput.type = 'number';
                    qtyInput.name = 'shipment_quantities[]';
                    qtyInput.className = 'form-input';
                    qtyInput.min = '0';
                    qtyInput.value = '0';
                    qtyInput.placeholder = '0';
                    qtyInput.style.textAlign = 'center';
                    
                    row.appendChild(sizeIdInput);
                    row.appendChild(sizeLabel);
                    row.appendChild(currentQty);
                    row.appendChild(qtyInput);
                    container.appendChild(row);
                });
            } else {
                container.innerHTML = '<p style="color: var(--text-dim); text-align: center; padding: 20px;">No sizes configured for this product. Add sizes first via Edit or Manage Inventory.</p>';
            }
            
            openModal('shipment-modal');
        })
        .catch(function(error) {
            console.error('Error fetching sizes:', error);
            alert('Error loading product sizes. Please try again.');
        });
}

// Stock Audit - loads sizes with system quantities and opens audit modal
function stockAudit(product) {
    document.getElementById('audit-product-id').value = product.id;
    document.getElementById('audit-product-name').textContent = product.name;
    document.getElementById('audit-summary').style.display = 'none';
    
    // Store product id for history button
    document.getElementById('audit-modal').dataset.productId = product.id;
    document.getElementById('audit-modal').dataset.productName = product.name;
    document.getElementById('audit-history-btn').style.display = 'inline-flex';
    
    fetch('process_merchandise_products.php?action=get_sizes&product_id=' + encodeURIComponent(product.id))
        .then(response => response.json())
        .then(data => {
            var container = document.getElementById('audit-sizes-container');
            container.innerHTML = '';
            
            if (data.sizes && data.sizes.length > 0) {
                data.sizes.forEach(function(size) {
                    var row = document.createElement('div');
                    row.style.cssText = 'display: grid; grid-template-columns: 1fr 100px 120px 100px; gap: 12px; margin-bottom: 8px; align-items: center;';
                    
                    var sizeIdInput = document.createElement('input');
                    sizeIdInput.type = 'hidden';
                    sizeIdInput.name = 'size_ids[]';
                    sizeIdInput.value = size.id;
                    
                    var sizeLabel = document.createElement('span');
                    sizeLabel.style.cssText = 'font-weight: 600; padding: 8px 12px; background: var(--bg); border-radius: 6px;';
                    sizeLabel.textContent = size.size;
                    
                    var systemQty = document.createElement('span');
                    systemQty.style.cssText = 'text-align: center; padding: 8px; background: var(--bg); border-radius: 6px; color: var(--text-dim);';
                    systemQty.textContent = size.quantity;
                    systemQty.dataset.systemQty = size.quantity;
                    
                    var actualInput = document.createElement('input');
                    actualInput.type = 'number';
                    actualInput.name = 'actual_quantities[]';
                    actualInput.className = 'form-input';
                    actualInput.min = '0';
                    actualInput.value = size.quantity;
                    actualInput.style.textAlign = 'center';
                    
                    var diffSpan = document.createElement('span');
                    diffSpan.style.cssText = 'text-align: center; padding: 8px; border-radius: 6px; font-weight: 600;';
                    diffSpan.textContent = '0';
                    diffSpan.style.background = 'rgba(16, 185, 129, 0.1)';
                    diffSpan.style.color = '#10b981';
                    
                    // Update difference on input change
                    actualInput.addEventListener('input', function() {
                        var sysQ = parseInt(systemQty.dataset.systemQty) || 0;
                        var actQ = parseInt(this.value) || 0;
                        var diff = actQ - sysQ;
                        diffSpan.textContent = (diff > 0 ? '+' : '') + diff;
                        
                        if (diff < 0) {
                            diffSpan.style.background = 'rgba(239, 68, 68, 0.1)';
                            diffSpan.style.color = '#ef4444';
                        } else if (diff > 0) {
                            diffSpan.style.background = 'rgba(245, 158, 11, 0.1)';
                            diffSpan.style.color = '#f59e0b';
                        } else {
                            diffSpan.style.background = 'rgba(16, 185, 129, 0.1)';
                            diffSpan.style.color = '#10b981';
                        }
                        
                        updateAuditSummary();
                    });
                    
                    row.appendChild(sizeIdInput);
                    row.appendChild(sizeLabel);
                    row.appendChild(systemQty);
                    row.appendChild(actualInput);
                    row.appendChild(diffSpan);
                    container.appendChild(row);
                });
            } else {
                container.innerHTML = '<p style="color: var(--text-dim); text-align: center; padding: 20px;">No sizes configured for this product.</p>';
            }
            
            openModal('audit-modal');
        })
        .catch(function(error) {
            console.error('Error fetching sizes:', error);
            alert('Error loading product sizes. Please try again.');
        });
}

function updateAuditSummary() {
    var container = document.getElementById('audit-sizes-container');
    var inputs = container.querySelectorAll('input[name="actual_quantities[]"]');
    var systemSpans = container.querySelectorAll('[data-system-qty]');
    var totalDiscrepancy = 0;
    var discrepancyCount = 0;
    
    inputs.forEach(function(input, idx) {
        var sysQ = parseInt(systemSpans[idx].dataset.systemQty) || 0;
        var actQ = parseInt(input.value) || 0;
        var diff = actQ - sysQ;
        if (diff !== 0) {
            totalDiscrepancy += diff;
            discrepancyCount++;
        }
    });
    
    var summary = document.getElementById('audit-summary');
    var summaryText = document.getElementById('audit-summary-text');
    
    if (discrepancyCount > 0) {
        summary.style.display = 'block';
        summaryText.textContent = discrepancyCount + ' size(s) with discrepancies. Net change: ' + (totalDiscrepancy > 0 ? '+' : '') + totalDiscrepancy + ' units. Submitting will adjust stock levels to match actual counts.';
    } else {
        summary.style.display = 'none';
    }
}

// View audit/movement history
function viewAuditHistory() {
    var auditModal = document.getElementById('audit-modal');
    var productId = auditModal.dataset.productId;
    var productName = auditModal.dataset.productName;
    
    closeModal('audit-modal');
    openStockHistory(productId, productName);
}

function openStockHistory(productId, productName) {
    document.getElementById('history-product-name').textContent = productName;
    document.getElementById('stock-history-modal').dataset.productId = productId;
    
    switchHistoryTab('movements');
    openModal('stock-history-modal');
}

function switchHistoryTab(tab) {
    var productId = document.getElementById('stock-history-modal').dataset.productId;
    
    document.querySelectorAll('.history-tab').forEach(function(btn) { btn.classList.remove('active'); });
    document.getElementById('tab-' + tab).classList.add('active');
    
    document.getElementById('movements-content').style.display = tab === 'movements' ? 'block' : 'none';
    document.getElementById('audits-content').style.display = tab === 'audits' ? 'block' : 'none';
    
    if (tab === 'movements') {
        loadMovements(productId);
    } else {
        loadAudits(productId);
    }
}

function loadMovements(productId) {
    document.getElementById('movements-loading').style.display = 'block';
    document.getElementById('movements-table').style.display = 'none';
    
    fetch('process_merchandise_products.php?action=get_stock_movements&product_id=' + encodeURIComponent(productId))
        .then(response => response.json())
        .then(data => {
            document.getElementById('movements-loading').style.display = 'none';
            var tableDiv = document.getElementById('movements-table');
            tableDiv.style.display = 'block';
            
            if (data.movements && data.movements.length > 0) {
                var html = '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">' +
                    '<thead><tr style="border-bottom: 2px solid var(--border);">' +
                    '<th style="padding: 8px; text-align: left;">Date</th>' +
                    '<th style="padding: 8px; text-align: left;">Type</th>' +
                    '<th style="padding: 8px; text-align: left;">Size</th>' +
                    '<th style="padding: 8px; text-align: center;">Before</th>' +
                    '<th style="padding: 8px; text-align: center;">Change</th>' +
                    '<th style="padding: 8px; text-align: center;">After</th>' +
                    '<th style="padding: 8px; text-align: left;">Reference</th>' +
                    '<th style="padding: 8px; text-align: left;">By</th>' +
                    '</tr></thead><tbody>';
                
                data.movements.forEach(function(m) {
                    var typeLabel = m.movement_type.replace('_', ' ');
                    typeLabel = typeLabel.charAt(0).toUpperCase() + typeLabel.slice(1);
                    var changeColor = m.quantity_change > 0 ? '#10b981' : (m.quantity_change < 0 ? '#ef4444' : 'var(--text-dim)');
                    var changeSign = m.quantity_change > 0 ? '+' : '';
                    
                    html += '<tr style="border-bottom: 1px solid var(--border);">' +
                        '<td style="padding: 8px;">' + new Date(m.created_at).toLocaleDateString() + '</td>' +
                        '<td style="padding: 8px;"><span style="padding: 2px 8px; border-radius: 4px; font-size: 11px; background: rgba(107, 70, 193, 0.1); color: var(--primary-light);">' + escapeHtml(typeLabel) + '</span></td>' +
                        '<td style="padding: 8px;">' + escapeHtml(m.size || 'N/A') + '</td>' +
                        '<td style="padding: 8px; text-align: center;">' + m.quantity_before + '</td>' +
                        '<td style="padding: 8px; text-align: center; color: ' + changeColor + '; font-weight: 600;">' + changeSign + m.quantity_change + '</td>' +
                        '<td style="padding: 8px; text-align: center;">' + m.quantity_after + '</td>' +
                        '<td style="padding: 8px; color: var(--text-dim);">' + escapeHtml(m.reference || '-') + '</td>' +
                        '<td style="padding: 8px; color: var(--text-dim);">' + escapeHtml((m.first_name || '') + ' ' + (m.last_name || '')) + '</td>' +
                        '</tr>';
                });
                
                html += '</tbody></table>';
                tableDiv.innerHTML = html;
            } else {
                tableDiv.innerHTML = '<p style="color: var(--text-dim); text-align: center; padding: 40px;">No stock movements recorded yet.</p>';
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            document.getElementById('movements-loading').style.display = 'none';
            document.getElementById('movements-table').innerHTML = '<p style="color: #ef4444; text-align: center; padding: 20px;">Error loading stock movements.</p>';
            document.getElementById('movements-table').style.display = 'block';
        });
}

function loadAudits(productId) {
    document.getElementById('audits-loading').style.display = 'block';
    document.getElementById('audits-table').style.display = 'none';
    
    fetch('process_merchandise_products.php?action=get_audit_history&product_id=' + encodeURIComponent(productId))
        .then(response => response.json())
        .then(data => {
            document.getElementById('audits-loading').style.display = 'none';
            var tableDiv = document.getElementById('audits-table');
            tableDiv.style.display = 'block';
            
            if (data.audits && data.audits.length > 0) {
                var html = '';
                data.audits.forEach(function(audit) {
                    var hasDiscrepancies = audit.items.some(function(item) { return item.discrepancy !== 0; });
                    var borderColor = hasDiscrepancies ? 'rgba(239, 68, 68, 0.3)' : 'rgba(16, 185, 129, 0.3)';
                    
                    html += '<div style="border: 1px solid ' + borderColor + '; border-radius: 8px; padding: 16px; margin-bottom: 12px;">' +
                        '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">' +
                        '<div>' +
                        '<strong>Audit #' + audit.id + '</strong>' +
                        '<span style="margin-left: 12px; color: var(--text-dim);">' + new Date(audit.created_at).toLocaleString() + '</span>' +
                        '</div>' +
                        '<span style="padding: 3px 10px; border-radius: 4px; font-size: 11px; background: ' + (hasDiscrepancies ? 'rgba(239, 68, 68, 0.1)' : 'rgba(16, 185, 129, 0.1)') + '; color: ' + (hasDiscrepancies ? '#ef4444' : '#10b981') + ';">' +
                        (hasDiscrepancies ? 'Discrepancies Found' : 'All Matched') + '</span>' +
                        '</div>';
                    
                    if (audit.notes) {
                        html += '<p style="color: var(--text-dim); font-size: 12px; margin-bottom: 8px;"><i class="fas fa-sticky-note"></i> ' + escapeHtml(audit.notes) + '</p>';
                    }
                    
                    html += '<table style="width: 100%; border-collapse: collapse; font-size: 12px;">' +
                        '<tr style="border-bottom: 1px solid var(--border);">' +
                        '<th style="padding: 4px 8px; text-align: left;">Size</th>' +
                        '<th style="padding: 4px 8px; text-align: center;">System</th>' +
                        '<th style="padding: 4px 8px; text-align: center;">Actual</th>' +
                        '<th style="padding: 4px 8px; text-align: center;">Diff</th>' +
                        '</tr>';
                    
                    audit.items.forEach(function(item) {
                        var diffColor = item.discrepancy < 0 ? '#ef4444' : (item.discrepancy > 0 ? '#f59e0b' : '#10b981');
                        html += '<tr><td style="padding: 4px 8px;">' + escapeHtml(item.size) + '</td>' +
                            '<td style="padding: 4px 8px; text-align: center;">' + item.system_quantity + '</td>' +
                            '<td style="padding: 4px 8px; text-align: center;">' + item.actual_quantity + '</td>' +
                            '<td style="padding: 4px 8px; text-align: center; color: ' + diffColor + '; font-weight: 600;">' + (item.discrepancy > 0 ? '+' : '') + item.discrepancy + '</td></tr>';
                    });
                    
                    html += '</table>';
                    
                    if (audit.first_name) {
                        html += '<p style="font-size: 11px; color: var(--text-dim); margin-top: 8px;">By: ' + escapeHtml(audit.first_name + ' ' + (audit.last_name || '')) + '</p>';
                    }
                    
                    html += '</div>';
                });
                tableDiv.innerHTML = html;
            } else {
                tableDiv.innerHTML = '<p style="color: var(--text-dim); text-align: center; padding: 40px;">No audit history recorded yet.</p>';
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            document.getElementById('audits-loading').style.display = 'none';
            document.getElementById('audits-table').innerHTML = '<p style="color: #ef4444; text-align: center; padding: 20px;">Error loading audit history.</p>';
            document.getElementById('audits-table').style.display = 'block';
        });
}

function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Convert modal forms to AJAX submissions for better UX
document.querySelectorAll('.modal form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(form);
        var modal = form.closest('.modal');
        var submitBtn = form.querySelector('button[type="submit"]');
        var originalBtnText = submitBtn ? submitBtn.innerHTML : '';
        
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
        }
        
        // Use getAttribute to avoid conflict with input[name="action"]
        fetch(form.getAttribute('action'), {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (submitBtn) {
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
            }
            
            if (data.success) {
                persistToast(data.message || 'Operation completed successfully!', 'success');
                if (modal) closeModal(modal.id);
                location.reload();
            } else {
                showNotification('Error: ' + (data.message || 'Operation failed'), 'error');
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            if (submitBtn) {
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
            }
            showNotification('An error occurred. Please try again.', 'error');
        });
    });
});
</script>
