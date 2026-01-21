<?php
// views/expense_categories.php - Manage expense categories
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once 'security.php';

$categories = $pdo->query("
    SELECT * FROM expense_categories 
    ORDER BY display_order, name
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="expense-categories">
    <div class="page-header">
        <h2><i class="fas fa-tags"></i> Expense Categories</h2>
        <button onclick="openCategoryModal()" class="btn-primary">
            <i class="fas fa-plus"></i> Add Category
        </button>
    </div>

    <div class="categories-table">
        <table>
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><?php echo $cat['display_order']; ?></td>
                    <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($cat['description'] ?? ''); ?></td>
                    <td>
                        <span class="status-badge <?php echo $cat['is_active'] ? 'active' : 'inactive'; ?>">
                            <?php echo $cat['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <button onclick='editCategory(<?php echo json_encode($cat); ?>)' class="btn-icon">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteCategory(<?php echo $cat['id']; ?>)" class="btn-icon btn-danger">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="categoryModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeCategoryModal()">&times;</span>
        <h3 id="modalTitle">Add Category</h3>
        
        <form action="process_expenses.php" method="POST">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_category" id="formAction">
            <input type="hidden" name="category_id" id="categoryId">
            
            <div class="form-group">
                <label>Name <span class="required">*</span></label>
                <input type="text" name="name" id="categoryName" required>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="categoryDescription" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label>Display Order</label>
                <input type="number" name="display_order" id="displayOrder" value="0" min="0">
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" id="isActive" value="1" checked>
                    Active
                </label>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeCategoryModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Category</button>
            </div>
        </form>
    </div>
</div>

<style>
.expense-categories { padding: 20px; }
.page-header { display: flex; justify-content: space-between; margin-bottom: 30px; }
.categories-table { background: #0a0f16; border-radius: 10px; overflow: hidden; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 15px; text-align: left; }
th { background: #020305; color: #94a3b8; font-size: 12px; text-transform: uppercase; }
td { border-bottom: 1px solid #1e293b; color: #e2e8f0; }
.status-badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.status-badge.active { background: #10b981; color: white; }
.status-badge.inactive { background: #6b7280; color: white; }
.btn-icon { background: transparent; border: 1px solid #334155; color: #94a3b8; padding: 8px 12px; border-radius: 6px; cursor: pointer; margin-right: 5px; }
.btn-icon:hover { background: #1e293b; color: #fff; }
.btn-icon.btn-danger:hover { background: #ef4444; border-color: #ef4444; color: white; }
.btn-primary { background: var(--primary, #7000a4); color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; }
.btn-secondary { background: #334155; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; }
.modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); overflow-y: auto; }
.modal-content { background: #0a0f16; margin: 100px auto; padding: 30px; border-radius: 12px; max-width: 600px; position: relative; color: #e2e8f0; }
.close { position: absolute; right: 20px; top: 20px; font-size: 28px; color: #94a3b8; cursor: pointer; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; color: #94a3b8; font-weight: 600; font-size: 14px; }
.form-group input, .form-group textarea { width: 100%; padding: 10px; background: #020305; border: 1px solid #334155; border-radius: 6px; color: #e2e8f0; }
.required { color: #ef4444; }
.form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 30px; }
</style>

<script>
function openCategoryModal() {
    document.getElementById('modalTitle').textContent = 'Add Category';
    document.getElementById('formAction').value = 'create_category';
    document.getElementById('categoryId').value = '';
    document.getElementById('categoryName').value = '';
    document.getElementById('categoryDescription').value = '';
    document.getElementById('displayOrder').value = '0';
    document.getElementById('isActive').checked = true;
    document.getElementById('categoryModal').style.display = 'block';
}

function closeCategoryModal() {
    document.getElementById('categoryModal').style.display = 'none';
}

function editCategory(cat) {
    document.getElementById('modalTitle').textContent = 'Edit Category';
    document.getElementById('formAction').value = 'update_category';
    document.getElementById('categoryId').value = cat.id;
    document.getElementById('categoryName').value = cat.name;
    document.getElementById('categoryDescription').value = cat.description || '';
    document.getElementById('displayOrder').value = cat.display_order;
    document.getElementById('isActive').checked = cat.is_active == 1;
    document.getElementById('categoryModal').style.display = 'block';
}

function deleteCategory(id) {
    if (confirm('Are you sure you want to delete this category?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'process_expenses.php';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="action" value="delete_category">
            <input type="hidden" name="category_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
