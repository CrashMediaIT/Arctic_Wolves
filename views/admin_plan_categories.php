<?php
// Admin Plan Categories Management
require_once '../security.php';
requirePermission($pdo, $_SESSION['user_id'], $_SESSION['role'], 'admin.manage_settings');

// Get all categories
$workout_categories = $pdo->query("SELECT * FROM workout_plan_categories ORDER BY display_order, name")->fetchAll(PDO::FETCH_ASSOC);
$nutrition_categories = $pdo->query("SELECT * FROM nutrition_plan_categories ORDER BY display_order, name")->fetchAll(PDO::FETCH_ASSOC);
$practice_categories = $pdo->query("SELECT * FROM practice_plan_categories ORDER BY display_order, name")->fetchAll(PDO::FETCH_ASSOC);

// Count plans using each category (table/column names are hardcoded at call sites)
function getCategoryCount($pdo, $table, $column, $category_id) {
    // Validate identifiers to prevent SQL injection
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table) ||
        !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
        return 0;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `" . $table . "` WHERE `" . $column . "` = ?");
    $stmt->execute([$category_id]);
    return $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan Categories - Arctic Wolves</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        :root {
            --primary: #6B46C1;
            --primary-dark: #cc3d00;
            --background: #f5f5f5;
        }

        .categories-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .category-section {
            background: white;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .category-section h2 {
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .categories-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .categories-table th {
            background: var(--background);
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }

        .categories-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .categories-table tr:hover {
            background: #f9f9f9;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 24px;
            border-radius: 8px;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--primary);
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .category-count {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 13px;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        @media (max-width: 768px) {
            .categories-container {
                padding: 10px;
            }

            .category-section {
                padding: 16px;
            }

            .categories-table {
                font-size: 13px;
            }

            .categories-table th,
            .categories-table td {
                padding: 8px 6px;
            }

            .btn-sm {
                padding: 4px 8px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="categories-container">
        <h1>Plan Categories Management</h1>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Workout Plan Categories -->
        <div class="category-section">
            <h2>
                <span>Workout Plan Categories</span>
                <button class="btn btn-primary" onclick="openModal('workout')">
                    + Add Category
                </button>
            </h2>

            <table class="categories-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Order</th>
                        <th>Plans Using</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workout_categories as $cat): ?>
                        <?php $count = getCategoryCount($pdo, 'workout_templates', 'category_id', $cat['id']); ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($cat['description']); ?></td>
                            <td><?php echo htmlspecialchars($cat['display_order']); ?></td>
                            <td><span class="category-count"><?php echo $count; ?> plans</span></td>
                            <td>
                                <button class="btn btn-danger btn-sm" 
                                        onclick="deleteCategory('workout', <?php echo $cat['id']; ?>, <?php echo json_encode($cat['name']); ?>, <?php echo $count; ?>)">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Nutrition Plan Categories -->
        <div class="category-section">
            <h2>
                <span>Nutrition Plan Categories</span>
                <button class="btn btn-primary" onclick="openModal('nutrition')">
                    + Add Category
                </button>
            </h2>

            <table class="categories-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Order</th>
                        <th>Plans Using</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nutrition_categories as $cat): ?>
                        <?php $count = getCategoryCount($pdo, 'nutrition_templates', 'category_id', $cat['id']); ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($cat['description']); ?></td>
                            <td><?php echo htmlspecialchars($cat['display_order']); ?></td>
                            <td><span class="category-count"><?php echo $count; ?> plans</span></td>
                            <td>
                                <button class="btn btn-danger btn-sm" 
                                        onclick="deleteCategory('nutrition', <?php echo $cat['id']; ?>, <?php echo json_encode($cat['name']); ?>, <?php echo $count; ?>)">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Practice Plan Categories -->
        <div class="category-section">
            <h2>
                <span>Practice Plan Categories</span>
                <button class="btn btn-primary" onclick="openModal('practice')">
                    + Add Category
                </button>
            </h2>

            <table class="categories-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Order</th>
                        <th>Plans Using</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($practice_categories as $cat): ?>
                        <?php $count = getCategoryCount($pdo, 'practice_plans', 'category_id', $cat['id']); ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($cat['description']); ?></td>
                            <td><?php echo htmlspecialchars($cat['display_order']); ?></td>
                            <td><span class="category-count"><?php echo $count; ?> plans</span></td>
                            <td>
                                <button class="btn btn-danger btn-sm" 
                                        onclick="deleteCategory('practice', <?php echo $cat['id']; ?>, <?php echo json_encode($cat['name']); ?>, <?php echo $count; ?>)">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add Category</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="categoryForm" method="POST" action="../process_plan_categories.php">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="category_type" id="categoryType" value="">

                <div class="form-group">
                    <label for="name">Category Name*</label>
                    <input type="text" id="name" name="name" required maxlength="100">
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"></textarea>
                </div>

                <div class="form-group">
                    <label for="display_order">Display Order</label>
                    <input type="number" id="display_order" name="display_order" value="0" min="0">
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Create Category</button>
            </form>
        </div>
    </div>

    <script>
        var csrfToken = document.querySelector('[name="csrf_token"]')?.value || '<?php echo generateCSRFToken(); ?>';
        
        // Show notification helper
        function showNotification(message, type) {
            var existing = document.querySelector('.notification-widget');
            if (existing) existing.remove();
            
            var div = document.createElement('div');
            div.className = 'notification-widget';
            div.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; padding: 16px 24px; border-radius: 8px; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);';
            if (type === 'success') {
                div.style.background = 'rgba(16, 185, 129, 0.95)';
                div.style.color = '#fff';
            } else {
                div.style.background = 'rgba(239, 68, 68, 0.95)';
                div.style.color = '#fff';
            }
            var safeMsg = document.createElement('span');
            safeMsg.textContent = message;
            div.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ';
            div.appendChild(safeMsg);
            var closeBtn = document.createElement('button');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.cssText = 'margin-left: 16px; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;';
            closeBtn.onclick = function() { div.remove(); };
            div.appendChild(closeBtn);
            document.body.appendChild(div);
            setTimeout(function() { if (div.parentElement) div.remove(); }, 5000);
        }
        
        function openModal(type) {
            document.getElementById('categoryType').value = type;
            document.getElementById('modalTitle').textContent = 'Add ' + capitalizeFirst(type) + ' Category';
            document.getElementById('addModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('addModal').style.display = 'none';
            document.getElementById('categoryForm').reset();
        }

        function capitalizeFirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        async function deleteCategory(type, id, name, count) {
            let message = 'Are you sure you want to delete the category "' + name + '"?';
            if (count > 0) {
                message += '\n\nWarning: This category is used by ' + count + ' plan(s). ';
                message += 'Deleting it will set those plans\' categories to NULL.';
            }

            if (!await showConfirmModal(message)) return;
            
            fetch('../process_plan_categories.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=delete&category_type=' + encodeURIComponent(type) + '&category_id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    persistToast(data.message || 'Category deleted!', 'success');
                    location.reload();
                } else {
                    showNotification('Error: ' + (data.message || 'Failed to delete'), 'error');
                }
            })
            .catch(function() { showNotification('An error occurred', 'error'); });
        }
        
        // Handle form submission via AJAX
        document.getElementById('categoryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            var form = this;
            var formData = new FormData(form);
            var submitBtn = form.querySelector('button[type="submit"]');
            var originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            submitBtn.disabled = true;
            
            fetch(form.getAttribute('action'), {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                if (data.success) {
                    persistToast(data.message || 'Category created!', 'success');
                    closeModal();
                    location.reload();
                } else {
                    showNotification('Error: ' + (data.message || 'Failed to create'), 'error');
                }
            })
            .catch(function() {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                showNotification('An error occurred', 'error');
            });
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
