<!-- Admin Wishlist View -->
<?php
// Check if user is admin (or actual admin in persona mode)
$actualRole = $_SESSION['persona_original_role'] ?? $user_role;
if ($actualRole !== 'admin') {
    header('Location: dashboard.php?page=home');
    exit;
}

// Ensure wishlist_items table exists
try {
    $pdo->query("SELECT 1 FROM wishlist_items LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `wishlist_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `price` DECIMAL(10,2) DEFAULT NULL,
        `link` VARCHAR(2048) DEFAULT NULL COMMENT 'URL to purchase or distributor',
        `sort_order` INT NOT NULL DEFAULT 0,
        `purchased` TINYINT(1) NOT NULL DEFAULT 0,
        `created_by` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
        INDEX `idx_sort_order` (`sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// Fetch wishlist items
$items = [];
try {
    $items = $pdo->query("SELECT * FROM wishlist_items ORDER BY sort_order ASC, created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $items = [];
}

$activeTab = $_GET['tab'] ?? 'list';
$editItemId = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
$editItem = null;
if ($editItemId > 0) {
    foreach ($items as $item) {
        if ((int)$item['id'] === $editItemId) {
            $editItem = $item;
            break;
        }
    }
}
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-clipboard-list"></i> Purchasing Wishlist</h1>
        <p class="page-description">Manage items needed for the business. Drag items to reorder purchasing priority.</p>
    </div>
</div>

<!-- Tabs -->
<div class="page-tabs">
    <a href="?page=admin_wishlist&tab=list" class="page-tab <?= $activeTab === 'list' ? 'active' : '' ?>">
        <i class="fas fa-list"></i> Wishlist
    </a>
    <a href="?page=admin_wishlist&tab=add" class="page-tab <?= $activeTab === 'add' ? 'active' : '' ?>">
        <i class="fas fa-plus-circle"></i> Add Item
    </a>
</div>

<div class="page-tab-content">
    <!-- Success/Error Messages -->
    <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <div class="alert alert-success" style="margin-bottom: 24px;">
        <i class="fas fa-check-circle"></i>
        <span><?= htmlspecialchars($_GET['message'] ?? 'Operation completed successfully!') ?></span>
        <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">&times;</button>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['status']) && $_GET['status'] === 'error'): ?>
    <div class="alert alert-error" style="margin-bottom: 24px;">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= htmlspecialchars($_GET['message'] ?? 'An error occurred.') ?></span>
        <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Wishlist Items Tab -->
    <?php if ($activeTab === 'list'): ?>
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <h2><i class="fas fa-clipboard-list"></i> Wishlist Items</h2>
            <span style="font-size:.85rem;color:var(--text-secondary,#6b7280);">
                <i class="fas fa-arrows-alt-v"></i> Drag rows to reorder priority
            </span>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($items)): ?>
                <div style="padding:2rem;text-align:center;color:var(--text-secondary,#6b7280);">
                    <i class="fas fa-clipboard-list" style="font-size:2rem;margin-bottom:.5rem;display:block;"></i>
                    No items yet. <a href="?page=admin_wishlist&tab=add">Add your first item</a>.
                </div>
            <?php else: ?>
                <table class="data-table" style="width:100%;" id="wishlist-table">
                    <thead>
                        <tr>
                            <th style="width:36px;"></th>
                            <th style="width:36px;">#</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th style="text-align:right;">Price</th>
                            <th>Link / Distributor</th>
                            <th style="width:100px;text-align:center;">Purchased</th>
                            <th style="width:120px;text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="wishlist-body">
                        <?php foreach ($items as $index => $item): ?>
                        <tr data-id="<?= (int)$item['id'] ?>" class="wishlist-row <?= $item['purchased'] ? 'wishlist-purchased' : '' ?>" draggable="true">
                            <td class="drag-handle" style="cursor:grab;text-align:center;color:var(--text-secondary,#9ca3af);">
                                <i class="fas fa-grip-vertical"></i>
                            </td>
                            <td class="wishlist-priority"><?= $index + 1 ?></td>
                            <td style="font-weight:600;"><?= htmlspecialchars($item['name']) ?></td>
                            <td style="max-width:250px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <?= htmlspecialchars($item['description'] ?? '') ?>
                            </td>
                            <td style="text-align:right;white-space:nowrap;">
                                <?= $item['price'] !== null ? '$' . number_format((float)$item['price'], 2) : '—' ?>
                            </td>
                            <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <?php if (!empty($item['link'])): ?>
                                    <?php
                                    $isUrl = preg_match('/^https?:\/\//i', $item['link']);
                                    ?>
                                    <?php if ($isUrl): ?>
                                        <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank" rel="noopener noreferrer">
                                            <i class="fas fa-external-link-alt"></i> <?= htmlspecialchars(parse_url($item['link'], PHP_URL_HOST) ?: $item['link']) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($item['link']) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <form method="POST" action="process_wishlist.php" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                    <input type="hidden" name="action" value="toggle_purchased">
                                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                    <button type="submit" class="btn-icon" title="<?= $item['purchased'] ? 'Mark as not purchased' : 'Mark as purchased' ?>"
                                            style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:<?= $item['purchased'] ? '#10b981' : '#d1d5db' ?>;">
                                        <i class="fas fa-<?= $item['purchased'] ? 'check-circle' : 'circle' ?>"></i>
                                    </button>
                                </form>
                            </td>
                            <td style="text-align:center;white-space:nowrap;">
                                <a href="?page=admin_wishlist&tab=add&edit_id=<?= (int)$item['id'] ?>" class="btn btn-sm" title="Edit" style="padding:4px 8px;">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" action="process_wishlist.php" style="display:inline;" onsubmit="return confirm('Delete this item?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                    <input type="hidden" name="action" value="delete_item">
                                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete" style="padding:4px 8px;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add / Edit Item Tab -->
    <?php if ($activeTab === 'add'): ?>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-<?= $editItem ? 'edit' : 'plus-circle' ?>"></i> <?= $editItem ? 'Edit Item' : 'Add Wishlist Item' ?></h2>
        </div>
        <div class="card-body">
            <form method="POST" action="process_wishlist.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="<?= $editItem ? 'update_item' : 'create_item' ?>">
                <?php if ($editItem): ?>
                <input type="hidden" name="item_id" value="<?= (int)$editItem['id'] ?>">
                <?php endif; ?>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label for="wl-name" style="display:block;font-weight:600;margin-bottom:.35rem;">Name <span style="color:#ef4444;">*</span></label>
                    <input type="text" id="wl-name" name="name" required class="form-control"
                           value="<?= htmlspecialchars($editItem['name'] ?? '') ?>"
                           placeholder="e.g. Hockey Nets, Pucks, Skate Sharpener">
                </div>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label for="wl-description" style="display:block;font-weight:600;margin-bottom:.35rem;">Description</label>
                    <textarea id="wl-description" name="description" rows="3" class="form-control"
                              placeholder="Additional details about the item..."><?= htmlspecialchars($editItem['description'] ?? '') ?></textarea>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                    <div class="form-group">
                        <label for="wl-price" style="display:block;font-weight:600;margin-bottom:.35rem;">Price ($)</label>
                        <input type="number" id="wl-price" name="price" step="0.01" min="0" class="form-control"
                               value="<?= htmlspecialchars($editItem['price'] ?? '') ?>"
                               placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label for="wl-link" style="display:block;font-weight:600;margin-bottom:.35rem;">Link / Distributor</label>
                        <input type="text" id="wl-link" name="link" class="form-control"
                               value="<?= htmlspecialchars($editItem['link'] ?? '') ?>"
                               placeholder="https://... or distributor name">
                    </div>
                </div>

                <div style="display:flex;gap:.5rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-<?= $editItem ? 'save' : 'plus' ?>"></i> <?= $editItem ? 'Save Changes' : 'Add Item' ?>
                    </button>
                    <?php if ($editItem): ?>
                    <a href="?page=admin_wishlist&tab=list" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Drag-and-drop reorder script -->
<script>
(function() {
    var tbody = document.getElementById('wishlist-body');
    if (!tbody) return;
    var csrfToken = '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES) ?>';

    var dragRow = null;

    tbody.addEventListener('dragstart', function(e) {
        var row = e.target.closest('tr.wishlist-row');
        if (!row) return;
        dragRow = row;
        row.style.opacity = '0.4';
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', row.dataset.id);
    });

    tbody.addEventListener('dragend', function(e) {
        var row = e.target.closest('tr.wishlist-row');
        if (row) row.style.opacity = '1';
        var rows = tbody.querySelectorAll('tr.wishlist-row');
        for (var i = 0; i < rows.length; i++) {
            rows[i].classList.remove('wishlist-drag-over');
        }
        dragRow = null;
    });

    tbody.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        var targetRow = e.target.closest('tr.wishlist-row');
        if (!targetRow || targetRow === dragRow) return;

        var rows = tbody.querySelectorAll('tr.wishlist-row');
        for (var i = 0; i < rows.length; i++) {
            rows[i].classList.remove('wishlist-drag-over');
        }
        targetRow.classList.add('wishlist-drag-over');
    });

    tbody.addEventListener('drop', function(e) {
        e.preventDefault();
        var targetRow = e.target.closest('tr.wishlist-row');
        if (!targetRow || !dragRow || targetRow === dragRow) return;

        // Determine insert position
        var allRows = Array.from(tbody.querySelectorAll('tr.wishlist-row'));
        var dragIndex = allRows.indexOf(dragRow);
        var targetIndex = allRows.indexOf(targetRow);

        if (dragIndex < targetIndex) {
            tbody.insertBefore(dragRow, targetRow.nextSibling);
        } else {
            tbody.insertBefore(dragRow, targetRow);
        }

        // Update priority numbers
        var updatedRows = tbody.querySelectorAll('tr.wishlist-row');
        var order = [];
        for (var i = 0; i < updatedRows.length; i++) {
            updatedRows[i].querySelector('.wishlist-priority').textContent = i + 1;
            order.push(parseInt(updatedRows[i].dataset.id, 10));
        }

        // Send new order to server
        fetch('process_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reorder', order: order, csrf_token: csrfToken })
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (!data.success) {
                alert('Failed to save new order: ' + (data.error || 'Unknown error'));
            }
        }).catch(function(err) {
            alert('Failed to save new order. Please refresh and try again.');
            console.error('Reorder request failed:', err);
        });
    });
})();
</script>

<style>
.wishlist-row { transition: opacity 0.2s; }
.wishlist-drag-over { border-top: 2px solid var(--primary, #3b82f6); }
.wishlist-purchased td { opacity: 0.5; text-decoration: line-through; }
.wishlist-purchased td:last-child,
.wishlist-purchased td:nth-last-child(2) { opacity: 1; text-decoration: none; }
.wishlist-purchased .drag-handle { text-decoration: none; }
</style>
