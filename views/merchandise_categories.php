<?php
// Merchandise Categories Management View
// Fetch all merchandise categories from database with parent relationship
try {
    $stmt = $pdo->prepare("
        SELECT mc.*, 
               u.first_name as creator_first_name, u.last_name as creator_last_name,
               pc.name as parent_name,
               (SELECT COUNT(*) FROM merchandise_products mp WHERE mp.category_id = mc.id) as product_count
        FROM merchandise_categories mc
        LEFT JOIN users u ON mc.created_by = u.id
        LEFT JOIN merchandise_categories pc ON mc.parent_id = pc.id
        ORDER BY COALESCE(mc.parent_id, mc.id), mc.parent_id IS NOT NULL, mc.display_order ASC, mc.name ASC
    ");
    $stmt->execute();
    $allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allCategories = decryptUserRows($allCategories);
    // Build created_by_name from decrypted fields
    foreach ($allCategories as &$cat) {
        $cat['created_by_name'] = (!empty($cat['creator_first_name'])) ? $cat['creator_first_name'] . ' ' . $cat['creator_last_name'] : null;
    }
    unset($cat);
    
    // Separate parent and child categories
    $categories = [];
    $subcategories = [];
    foreach ($allCategories as $cat) {
        if (empty($cat['parent_id'])) {
            $categories[] = $cat;
        } else {
            $subcategories[$cat['parent_id']][] = $cat;
        }
    }
    
    // Get parent categories for dropdown
    $parentCategoriesStmt = $pdo->prepare("
        SELECT id, name FROM merchandise_categories WHERE parent_id IS NULL OR parent_id = 0 ORDER BY name ASC
    ");
    $parentCategoriesStmt->execute();
    $parentCategories = $parentCategoriesStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Merchandise categories fetch error: " . $e->getMessage());
    $categories = [];
    $subcategories = [];
    $parentCategories = [];
    $allCategories = [];
}

$totalCategories = count($allCategories);
$activeCategories = count(array_filter($allCategories, function($c) { return !empty($c['is_active']); }));
$totalProducts = array_sum(array_column($allCategories, 'product_count'));
$parentCount = count($categories);
$subcategoryCount = $totalCategories - $parentCount;
?>

<!-- Merchandise Categories Management View -->
<div class="page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-tags"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">Merchandise Categories</h1>
            <p class="page-description">Organize merchandise products into categories for the POS system</p>
        </div>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value"><?= $totalCategories ?></span>
            <span class="stat-label">Total Categories</span>
        </div>
        <div class="header-stat">
            <span class="stat-value"><?= $activeCategories ?></span>
            <span class="stat-label">Active</span>
        </div>
        <div class="header-stat">
            <span class="stat-value"><?= $totalProducts ?></span>
            <span class="stat-label">Products</span>
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
            <h3><i class="fas fa-tags"></i> Merchandise Categories</h3>
            <button class="btn btn-primary" onclick="openModal('add-category-modal')">
                <i class="fas fa-plus"></i> Add Category
            </button>
        </div>
        <div class="card-body">
            <?php if (empty($categories)): ?>
                <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                    <i class="fas fa-tags" style="font-size: 48px; color: var(--text-dim); margin-bottom: 16px;"></i>
                    <p style="color: var(--text-dim);">No categories yet. Click "Add Category" to create one.</p>
                </div>
            <?php else: ?>
                <div class="categories-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-card" style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 20px; position: relative;">
                            <?php if (!empty($category['image_url'])): ?>
                                <div class="category-image" style="height: 120px; border-radius: 8px; overflow: hidden; margin-bottom: 16px;">
                                    <img src="<?= htmlspecialchars($category['image_url']) ?>" alt="<?= htmlspecialchars($category['name']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                            <?php else: ?>
                                <div class="category-image-placeholder" style="height: 120px; border-radius: 8px; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%); display: flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                                    <i class="fas fa-tags" style="font-size: 32px; color: rgba(255,255,255,0.7);"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="category-badge" style="position: absolute; top: 12px; right: 12px;">
                                <span class="status-badge <?= $category['is_active'] ? 'active' : 'inactive' ?>" style="padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; background: <?= $category['is_active'] ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)' ?>; color: <?= $category['is_active'] ? '#10b981' : '#ef4444' ?>;">
                                    <?= $category['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                            
                            <h4 style="font-size: 18px; font-weight: 600; color: var(--text); margin-bottom: 8px;"><?= htmlspecialchars($category['name']) ?></h4>
                            
                            <?php if (!empty($category['description'])): ?>
                                <p style="color: var(--text-dim); font-size: 13px; margin-bottom: 12px; line-height: 1.5;"><?= htmlspecialchars(substr($category['description'], 0, 100)) ?><?= strlen($category['description']) > 100 ? '...' : '' ?></p>
                            <?php endif; ?>
                            
                            <div class="category-meta" style="display: flex; align-items: center; gap: 16px; font-size: 12px; color: var(--text-dim); margin-bottom: 16px;">
                                <span><i class="fas fa-box" style="margin-right: 4px;"></i> <?= $category['product_count'] ?> Products</span>
                                <span><i class="fas fa-sort" style="margin-right: 4px;"></i> Order: <?= $category['display_order'] ?></span>
                                <?php 
                                $subCount = isset($subcategories[$category['id']]) ? count($subcategories[$category['id']]) : 0;
                                if ($subCount > 0): ?>
                                    <span><i class="fas fa-folder-tree" style="margin-right: 4px;"></i> <?= $subCount ?> Subcategories</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (isset($subcategories[$category['id']]) && !empty($subcategories[$category['id']])): ?>
                            <div class="subcategories-list" style="margin-bottom: 16px; padding: 12px; background: rgba(0,0,0,0.2); border-radius: 8px;">
                                <div style="font-size: 11px; color: var(--text-dim); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">Subcategories:</div>
                                <?php foreach ($subcategories[$category['id']] as $subcat): ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid var(--border);">
                                        <span style="font-size: 13px;">
                                            <?= htmlspecialchars($subcat['name']) ?>
                                            <span style="color: var(--text-dim);">(<?= $subcat['product_count'] ?>)</span>
                                        </span>
                                        <div style="display: flex; gap: 6px;">
                                            <button class="btn-action" onclick='editCategory(<?= json_encode($subcat) ?>)' title="Edit" style="padding: 4px 8px; border: none; border-radius: 4px; background: rgba(107, 70, 193, 0.1); color: var(--primary-light); cursor: pointer; font-size: 11px;">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="category-actions" style="display: flex; gap: 8px;">
                                <button class="btn-action" onclick='editCategory(<?= json_encode($category) ?>)' title="Edit" style="flex: 1; padding: 8px; border: none; border-radius: 6px; background: rgba(107, 70, 193, 0.1); color: var(--primary-light); cursor: pointer;">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn-action" onclick="toggleCategoryStatus(<?= intval($category['id']) ?>, <?= intval($category['is_active']) ?>)" title="<?= $category['is_active'] ? 'Deactivate' : 'Activate' ?>" style="padding: 8px 12px; border: none; border-radius: 6px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; cursor: pointer;">
                                    <i class="fas fa-toggle-<?= $category['is_active'] ? 'on' : 'off' ?>"></i>
                                </button>
                                <button class="btn-action" onclick='deleteCategory(<?= json_encode(["id" => $category["id"], "name" => $category["name"], "product_count" => $category["product_count"]]) ?>)' title="Delete" style="padding: 8px 12px; border: none; border-radius: 6px; background: rgba(239, 68, 68, 0.1); color: #ef4444; cursor: pointer;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div id="add-category-modal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-plus"></i> Add Merchandise Category</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('add-category-modal')">&times;</button>
        </div>
        <form method="POST" action="process_merchandise_categories.php" enctype="multipart/form-data">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Category Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="e.g., Jerseys, Accessories">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Parent Category</label>
                    <select name="parent_id" class="form-input">
                        <option value="">None (Top-Level Category)</option>
                        <?php foreach ($parentCategories as $parentCat): ?>
                            <option value="<?= $parentCat['id'] ?>"><?= htmlspecialchars($parentCat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--text-dim);">Select a parent to make this a subcategory</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">URL Slug</label>
                    <input type="text" name="slug" class="form-input" placeholder="jerseys (auto-generated if empty)">
                    <small style="color: var(--text-dim);">Used for shop URLs</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3" placeholder="Brief description of this category"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category Image</label>
                    <input type="file" name="image" class="form-input" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small style="color: var(--text-dim);">Recommended: 400x300px, max 5MB</small>
                </div>
                
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="display_order" class="form-input" value="0" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="is_active" class="form-input">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-category-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="edit-category-modal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Merchandise Category</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('edit-category-modal')">&times;</button>
        </div>
        <form method="POST" action="process_merchandise_categories.php" enctype="multipart/form-data">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-category-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Category Name *</label>
                    <input type="text" name="name" id="edit-category-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Parent Category</label>
                    <select name="parent_id" id="edit-category-parent" class="form-input">
                        <option value="">None (Top-Level Category)</option>
                        <?php foreach ($parentCategories as $parentCat): ?>
                            <option value="<?= $parentCat['id'] ?>"><?= htmlspecialchars($parentCat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">URL Slug</label>
                    <input type="text" name="slug" id="edit-category-slug" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-category-description" class="form-textarea" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category Image</label>
                    <div id="edit-category-image-preview" style="margin-bottom: 8px;"></div>
                    <input type="file" name="image" class="form-input" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small style="color: var(--text-dim);">Leave empty to keep current image</small>
                </div>
                
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="display_order" id="edit-category-order" class="form-input" value="0" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="is_active" id="edit-category-status" class="form-input">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-category-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Category</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Merchandise Categories Styles */
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

.category-card:hover {
    border-color: var(--primary);
}

.btn-action:hover {
    opacity: 0.8;
}
</style>

<script>
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function editCategory(category) {
    document.getElementById('edit-category-id').value = category.id;
    document.getElementById('edit-category-name').value = category.name || '';
    document.getElementById('edit-category-description').value = category.description || '';
    document.getElementById('edit-category-order').value = category.display_order || 0;
    document.getElementById('edit-category-status').value = category.is_active;
    document.getElementById('edit-category-parent').value = category.parent_id || '';
    document.getElementById('edit-category-slug').value = category.slug || '';
    
    // Don't allow selecting self as parent
    const parentSelect = document.getElementById('edit-category-parent');
    Array.from(parentSelect.options).forEach(option => {
        if (option.value == category.id) {
            option.disabled = true;
        } else {
            option.disabled = false;
        }
    });
    
    // Show image preview if exists
    const previewDiv = document.getElementById('edit-category-image-preview');
    if (category.image_url) {
        previewDiv.innerHTML = '<img src="' + category.image_url + '" style="max-width: 150px; max-height: 100px; border-radius: 8px;">';
    } else {
        previewDiv.innerHTML = '';
    }
    
    openModal('edit-category-modal');
}

function toggleCategoryStatus(id, currentStatus) {
    if (!confirm('Are you sure you want to ' + (currentStatus ? 'deactivate' : 'activate') + ' this category?')) {
        return;
    }
    
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    const csrfToken = csrfInput ? csrfInput.value : '';
    
    fetch('process_merchandise_categories.php', {
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

function deleteCategory(category) {
    if (category.product_count > 0) {
        alert('Cannot delete category "' + category.name + '" because it has ' + category.product_count + ' products. Please move or delete the products first.');
        return;
    }
    
    if (!confirm('Are you sure you want to delete "' + category.name + '"? This action cannot be undone.')) {
        return;
    }
    
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    const csrfToken = csrfInput ? csrfInput.value : '';
    
    fetch('process_merchandise_categories.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=delete&id=' + encodeURIComponent(category.id) + '&csrf_token=' + encodeURIComponent(csrfToken)
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
