<?php
// Merchandise Categories Management View
// Fetch all merchandise categories from database
try {
    $stmt = $pdo->prepare("
        SELECT mc.*, 
               CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
               (SELECT COUNT(*) FROM merchandise_products mp WHERE mp.category_id = mc.id) as product_count
        FROM merchandise_categories mc
        LEFT JOIN users u ON mc.created_by = u.id
        ORDER BY mc.display_order ASC, mc.name ASC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Merchandise categories fetch error: " . $e->getMessage());
    $categories = [];
}

$totalCategories = count($categories);
$activeCategories = count(array_filter($categories, function($c) { return !empty($c['is_active']); }));
$totalProducts = array_sum(array_column($categories, 'product_count'));
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
            <button class="btn btn-primary" data-action="add" onclick="openModal('add-category-modal')">
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
                            </div>
                            
                            <div class="category-actions" style="display: flex; gap: 8px;">
                                <button class="btn-action" onclick="editCategory(<?= $category['id'] ?>, '<?= htmlspecialchars(addslashes($category['name'])) ?>', '<?= htmlspecialchars(addslashes($category['description'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($category['image_url'] ?? '')) ?>', <?= $category['display_order'] ?>, <?= $category['is_active'] ?>)" title="Edit" style="flex: 1; padding: 8px; border: none; border-radius: 6px; background: rgba(107, 70, 193, 0.1); color: var(--primary-light); cursor: pointer;">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn-action" onclick="toggleCategoryStatus(<?= $category['id'] ?>, <?= $category['is_active'] ?>)" title="<?= $category['is_active'] ? 'Deactivate' : 'Activate' ?>" style="padding: 8px 12px; border: none; border-radius: 6px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; cursor: pointer;">
                                    <i class="fas fa-toggle-<?= $category['is_active'] ? 'on' : 'off' ?>"></i>
                                </button>
                                <button class="btn-action" onclick="deleteCategory(<?= $category['id'] ?>, '<?= htmlspecialchars(addslashes($category['name'])) ?>', <?= $category['product_count'] ?>)" title="Delete" style="padding: 8px 12px; border: none; border-radius: 6px; background: rgba(239, 68, 68, 0.1); color: #ef4444; cursor: pointer;">
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
            <button class="modal-close" onclick="closeModal('add-category-modal')">&times;</button>
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
            <button class="modal-close" onclick="closeModal('edit-category-modal')">&times;</button>
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

function editCategory(id, name, description, imageUrl, displayOrder, isActive) {
    document.getElementById('edit-category-id').value = id;
    document.getElementById('edit-category-name').value = name;
    document.getElementById('edit-category-description').value = description;
    document.getElementById('edit-category-order').value = displayOrder;
    document.getElementById('edit-category-status').value = isActive;
    
    // Show image preview if exists
    const previewDiv = document.getElementById('edit-category-image-preview');
    if (imageUrl) {
        previewDiv.innerHTML = '<img src="' + imageUrl + '" style="max-width: 150px; max-height: 100px; border-radius: 8px;">';
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
        body: 'action=toggle_status&id=' + id + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
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

function deleteCategory(id, name, productCount) {
    if (productCount > 0) {
        alert('Cannot delete category "' + name + '" because it has ' + productCount + ' products. Please move or delete the products first.');
        return;
    }
    
    if (!confirm('Are you sure you want to delete "' + name + '"? This action cannot be undone.')) {
        return;
    }
    
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    const csrfToken = csrfInput ? csrfInput.value : '';
    
    fetch('process_merchandise_categories.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=delete&id=' + id + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
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

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal(this.id);
        }
    });
});

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(modal => {
            closeModal(modal.id);
        });
    }
});
</script>
