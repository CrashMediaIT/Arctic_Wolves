<!-- Admin Wishlist View -->
<?php
// Ensure wishlist_items table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `wishlist_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `price` DECIMAL(10,2) DEFAULT NULL,
        `link` VARCHAR(2048) DEFAULT NULL COMMENT 'URL to purchase or distributor',
        `display_order` INT DEFAULT 0,
        `created_by` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_display_order` (`display_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Table may already exist; log other errors
    if (strpos($e->getMessage(), 'already exists') === false) {
        error_log("Wishlist table creation error: " . $e->getMessage());
    }
}

// Fetch wishlist items ordered by display_order
try {
    $stmt = $pdo->prepare("
        SELECT wi.*, u.first_name, u.last_name 
        FROM wishlist_items wi
        LEFT JOIN users u ON wi.created_by = u.id
        ORDER BY wi.display_order ASC, wi.id ASC
    ");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) {
        $items = decryptUserRows($items);
    }
} catch (PDOException $e) {
    error_log("Wishlist items fetch error: " . $e->getMessage());
    $items = [];
}

$total_items = count($items);
$total_cost = 0;
foreach ($items as $item) {
    if ($item['price']) {
        $total_cost += floatval($item['price']);
    }
}
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-clipboard-list"></i> Business Wishlist</h1>
        <p class="page-description">Track and prioritize items to purchase for the business. Drag items to reorder by priority.</p>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value"><?= $total_items ?></span>
            <span class="stat-label">Items</span>
        </div>
        <div class="header-stat">
            <span class="stat-value">$<?= number_format($total_cost, 2) ?></span>
            <span class="stat-label">Total Est. Cost</span>
        </div>
    </div>
</div>

<style>
.wishlist-toolbar {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 20px;
}
.wishlist-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.wishlist-item {
    background: var(--card-bg, #1e293b);
    border: 1px solid var(--border, #334155);
    border-radius: 10px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: box-shadow 0.2s, border-color 0.2s;
}
.wishlist-item:hover {
    border-color: var(--primary, #6366f1);
    box-shadow: 0 2px 12px rgba(99, 102, 241, 0.10);
}
.wishlist-handle {
    cursor: grab;
    color: var(--text-secondary, #94a3b8);
    font-size: 18px;
    padding: 4px;
    flex-shrink: 0;
}
.wishlist-handle:active {
    cursor: grabbing;
}
.wishlist-priority {
    background: var(--primary, #6366f1);
    color: #fff;
    font-weight: 800;
    font-size: 13px;
    min-width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.wishlist-info {
    flex: 1;
    min-width: 0;
}
.wishlist-name {
    font-weight: 700;
    font-size: 16px;
    color: var(--text, #f1f5f9);
    margin-bottom: 2px;
}
.wishlist-desc {
    font-size: 13px;
    color: var(--text-secondary, #94a3b8);
    line-height: 1.4;
    margin-bottom: 4px;
}
.wishlist-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    align-items: center;
}
.wishlist-price {
    font-weight: 700;
    color: #10b981;
    font-size: 15px;
}
.wishlist-link a {
    color: var(--primary, #6366f1);
    font-size: 13px;
    text-decoration: none;
    word-break: break-all;
}
.wishlist-link a:hover {
    text-decoration: underline;
}
.wishlist-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}
.wishlist-actions .btn-icon {
    background: none;
    border: 1px solid var(--border, #334155);
    color: var(--text-secondary, #94a3b8);
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: 0.2s;
    font-size: 14px;
}
.wishlist-actions .btn-icon:hover {
    background: var(--primary, #6366f1);
    color: #fff;
    border-color: var(--primary, #6366f1);
}
.wishlist-actions .btn-icon-danger:hover {
    background: #ef4444;
    border-color: #ef4444;
}

/* Sortable ghost/drag styles */
.wishlist-item.sortable-ghost {
    opacity: 0.4;
    border: 2px dashed var(--primary, #6366f1);
}
.wishlist-item.sortable-drag {
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
}

/* Empty state */
.wishlist-empty {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary, #94a3b8);
}
.wishlist-empty i {
    font-size: 48px;
    display: block;
    margin-bottom: 16px;
    opacity: 0.5;
}
.wishlist-empty h4 {
    font-size: 18px;
    color: var(--text, #f1f5f9);
    margin-bottom: 8px;
}

/* Modal styles */
.wl-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
.wl-modal-overlay.active {
    display: flex;
}
.wl-modal {
    background: var(--card-bg, #1e293b);
    border: 1px solid var(--border, #334155);
    border-radius: 12px;
    width: 100%;
    max-width: 520px;
    padding: 0;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
}
.wl-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border, #334155);
}
.wl-modal-header h3 {
    font-size: 18px;
    font-weight: 800;
    color: var(--text, #f1f5f9);
    margin: 0;
}
.wl-modal-close {
    background: none;
    border: none;
    color: var(--text-secondary, #94a3b8);
    font-size: 20px;
    cursor: pointer;
    padding: 4px;
}
.wl-modal-close:hover {
    color: var(--text, #f1f5f9);
}
.wl-modal-body {
    padding: 24px;
}
.wl-form-group {
    margin-bottom: 16px;
}
.wl-form-group label {
    display: block;
    font-weight: 600;
    font-size: 13px;
    color: var(--text-secondary, #94a3b8);
    margin-bottom: 6px;
}
.wl-form-group input,
.wl-form-group textarea {
    width: 100%;
    padding: 10px 14px;
    background: var(--bg, #0f172a);
    border: 1px solid var(--border, #334155);
    border-radius: 8px;
    color: var(--text, #f1f5f9);
    font-size: 14px;
    font-family: inherit;
    box-sizing: border-box;
}
.wl-form-group textarea {
    resize: vertical;
    min-height: 80px;
}
.wl-form-group input:focus,
.wl-form-group textarea:focus {
    outline: none;
    border-color: var(--primary, #6366f1);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
}
.wl-modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border, #334155);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.wl-btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    border: 1px solid var(--border, #334155);
    transition: 0.2s;
}
.wl-btn-cancel {
    background: none;
    color: var(--text-secondary, #94a3b8);
}
.wl-btn-cancel:hover {
    background: rgba(255,255,255,0.05);
}
.wl-btn-primary {
    background: var(--primary, #6366f1);
    color: #fff;
    border-color: var(--primary, #6366f1);
}
.wl-btn-primary:hover {
    background: #4f46e5;
}
.wl-btn-danger {
    background: #ef4444;
    color: #fff;
    border-color: #ef4444;
}
.wl-btn-danger:hover {
    background: #dc2626;
}

/* Toast notification */
.wl-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 14px 22px;
    border-radius: 8px;
    color: #fff;
    font-family: Inter, sans-serif;
    font-size: 14px;
    font-weight: 600;
    z-index: 10001;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    transition: opacity 0.3s ease;
}
</style>

<!-- Toolbar -->
<div class="wishlist-toolbar">
    <button class="btn btn-primary" id="wlAddBtn">
        <i class="fas fa-plus"></i> Add Item
    </button>
</div>

<!-- Wishlist Items -->
<div class="wishlist-list" id="wishlistList">
<?php if ($total_items > 0): ?>
    <?php foreach ($items as $index => $item): ?>
    <div class="wishlist-item" data-id="<?= intval($item['id']) ?>">
        <div class="wishlist-handle" title="Drag to reorder">
            <i class="fas fa-grip-vertical"></i>
        </div>
        <div class="wishlist-priority"><?= $index + 1 ?></div>
        <div class="wishlist-info">
            <div class="wishlist-name"><?= htmlspecialchars($item['name']) ?></div>
            <?php if (!empty($item['description'])): ?>
                <div class="wishlist-desc"><?= htmlspecialchars($item['description']) ?></div>
            <?php endif; ?>
            <div class="wishlist-meta">
                <?php if ($item['price']): ?>
                    <span class="wishlist-price">$<?= number_format($item['price'], 2) ?></span>
                <?php endif; ?>
                <?php if (!empty($item['link'])): ?>
                    <span class="wishlist-link">
                        <?php
                            $linkHost = parse_url($item['link'], PHP_URL_HOST);
                            $linkLabel = $linkHost ? $linkHost : 'View Link';
                        ?>
                        <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-external-link-alt"></i> <?= htmlspecialchars($linkLabel) ?>
                        </a>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="wishlist-actions">
            <button class="btn-icon" title="Edit"
                    data-action="edit"
                    data-id="<?= intval($item['id']) ?>"
                    data-name="<?= htmlspecialchars($item['name']) ?>"
                    data-description="<?= htmlspecialchars($item['description'] ?? '') ?>"
                    data-price="<?= htmlspecialchars($item['price'] ?? '') ?>"
                    data-link="<?= htmlspecialchars($item['link'] ?? '') ?>">
                <i class="fas fa-edit"></i>
            </button>
            <button class="btn-icon btn-icon-danger" title="Delete"
                    data-action="delete"
                    data-id="<?= intval($item['id']) ?>"
                    data-name="<?= htmlspecialchars($item['name']) ?>">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="wishlist-empty">
        <i class="fas fa-clipboard-list"></i>
        <h4>No Wishlist Items Yet</h4>
        <p>Add items you need to purchase for the business.</p>
    </div>
<?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div class="wl-modal-overlay" id="wlModal">
    <div class="wl-modal">
        <div class="wl-modal-header">
            <h3 id="wlModalTitle">Add Wishlist Item</h3>
            <button class="wl-modal-close" id="wlModalClose">&times;</button>
        </div>
        <div class="wl-modal-body">
            <input type="hidden" id="wlItemId" value="">
            <div class="wl-form-group">
                <label for="wlName">Name *</label>
                <input type="text" id="wlName" placeholder="Item name" maxlength="255" required>
            </div>
            <div class="wl-form-group">
                <label for="wlDescription">Description</label>
                <textarea id="wlDescription" placeholder="What is this item for?"></textarea>
            </div>
            <div class="wl-form-group">
                <label for="wlPrice">Estimated Price ($)</label>
                <input type="number" id="wlPrice" placeholder="0.00" step="0.01" min="0">
            </div>
            <div class="wl-form-group">
                <label for="wlLink">Purchase Link / Distributor</label>
                <input type="url" id="wlLink" placeholder="https://example.com/product">
            </div>
        </div>
        <div class="wl-modal-footer">
            <button class="wl-btn wl-btn-cancel" id="wlCancelBtn">Cancel</button>
            <button class="wl-btn wl-btn-primary" id="wlSaveBtn"><i class="fas fa-save"></i> Save</button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="wl-modal-overlay" id="wlDeleteModal">
    <div class="wl-modal" style="max-width: 400px;">
        <div class="wl-modal-header">
            <h3>Delete Item</h3>
            <button class="wl-modal-close" onclick="document.getElementById('wlDeleteModal').classList.remove('active')">&times;</button>
        </div>
        <div class="wl-modal-body" style="text-align:center;">
            <i class="fas fa-exclamation-triangle" style="font-size:36px;color:#ef4444;margin-bottom:12px;display:block;"></i>
            <p style="color:var(--text,#f1f5f9);margin-bottom:4px;">Are you sure you want to delete</p>
            <p style="font-weight:700;color:var(--text,#f1f5f9);" id="wlDeleteName"></p>
        </div>
        <div class="wl-modal-footer" style="justify-content:center;">
            <button class="wl-btn wl-btn-cancel" onclick="document.getElementById('wlDeleteModal').classList.remove('active')">Cancel</button>
            <button class="wl-btn wl-btn-danger" id="wlDeleteConfirmBtn"><i class="fas fa-trash"></i> Delete</button>
        </div>
    </div>
</div>

<!-- Include SortableJS library for drag-and-drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js" 
        integrity="sha256-ipiJrswvAR4VAx/th+6zWsdeYmVae0iJuiR+6OqHJHQ=" 
        crossorigin="anonymous"></script>

<script>
(function() {
    'use strict';

    var csrfToken = '<?= generateCSRFToken() ?>';
    var emptyStateHtml = '<div class="wishlist-empty"><i class="fas fa-clipboard-list"></i><h4>No Wishlist Items Yet</h4><p>Add items you need to purchase for the business.</p></div>';

    // Toast helper
    function showToast(message, type) {
        var existing = document.querySelector('.wl-toast');
        if (existing) existing.remove();
        var toast = document.createElement('div');
        toast.className = 'wl-toast';
        toast.textContent = message;
        toast.style.background = type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#6366f1';
        document.body.appendChild(toast);
        setTimeout(function() {
            toast.style.opacity = '0';
            setTimeout(function() { toast.remove(); }, 300);
        }, 3000);
    }

    // Update priority badges after reorder
    function updatePriorityBadges() {
        var items = document.querySelectorAll('#wishlistList .wishlist-item');
        items.forEach(function(item, idx) {
            var badge = item.querySelector('.wishlist-priority');
            if (badge) badge.textContent = idx + 1;
        });
    }

    // Initialize SortableJS for drag-and-drop reordering
    var list = document.getElementById('wishlistList');
    if (list && typeof Sortable !== 'undefined') {
        new Sortable(list, {
            animation: 150,
            handle: '.wishlist-handle',
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            onEnd: function() {
                updatePriorityBadges();
                saveOrder();
            }
        });
    }

    // Save reordered items
    function saveOrder() {
        var items = document.querySelectorAll('#wishlistList .wishlist-item');
        var order = [];
        items.forEach(function(item, idx) {
            order.push({ id: parseInt(item.dataset.id, 10), display_order: idx });
        });

        var formData = new URLSearchParams();
        formData.append('action', 'reorder');
        formData.append('order', JSON.stringify(order));
        formData.append('csrf_token', csrfToken);

        fetch('process_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: formData.toString()
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('Priority order saved', 'success');
            } else {
                showToast(data.message || 'Failed to save order', 'error');
            }
        })
        .catch(function() { showToast('Failed to save order', 'error'); });
    }

    // Modal controls
    var modal = document.getElementById('wlModal');
    var addBtn = document.getElementById('wlAddBtn');
    var closeBtn = document.getElementById('wlModalClose');
    var cancelBtn = document.getElementById('wlCancelBtn');
    var saveBtn = document.getElementById('wlSaveBtn');

    function openModal(editData) {
        document.getElementById('wlItemId').value = editData ? editData.id : '';
        document.getElementById('wlName').value = editData ? editData.name : '';
        document.getElementById('wlDescription').value = editData ? editData.description : '';
        document.getElementById('wlPrice').value = editData ? editData.price : '';
        document.getElementById('wlLink').value = editData ? editData.link : '';
        document.getElementById('wlModalTitle').textContent = editData ? 'Edit Wishlist Item' : 'Add Wishlist Item';
        modal.classList.add('active');
        document.getElementById('wlName').focus();
    }

    function closeModal() {
        modal.classList.remove('active');
    }

    addBtn.addEventListener('click', function() { openModal(null); });
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });

    // Save item (create or update)
    saveBtn.addEventListener('click', function() {
        var id = document.getElementById('wlItemId').value;
        var name = document.getElementById('wlName').value.trim();
        var description = document.getElementById('wlDescription').value.trim();
        var price = document.getElementById('wlPrice').value;
        var link = document.getElementById('wlLink').value.trim();

        if (!name) {
            showToast('Item name is required', 'error');
            document.getElementById('wlName').focus();
            return;
        }

        var formData = new URLSearchParams();
        formData.append('action', id ? 'update' : 'create');
        if (id) formData.append('id', id);
        formData.append('name', name);
        formData.append('description', description);
        formData.append('price', price);
        formData.append('link', link);
        formData.append('csrf_token', csrfToken);

        fetch('process_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: formData.toString()
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast(id ? 'Item updated' : 'Item added', 'success');
                closeModal();
                // Reload to reflect changes
                setTimeout(function() { location.reload(); }, 500);
            } else {
                showToast(data.message || 'Failed to save item', 'error');
            }
        })
        .catch(function() { showToast('Failed to save item', 'error'); });
    });

    // Edit and Delete button handlers (event delegation)
    document.getElementById('wishlistList').addEventListener('click', function(e) {
        var btn = e.target.closest('[data-action]');
        if (!btn) return;

        if (btn.dataset.action === 'edit') {
            openModal({
                id: btn.dataset.id,
                name: btn.dataset.name,
                description: btn.dataset.description,
                price: btn.dataset.price,
                link: btn.dataset.link
            });
        }

        if (btn.dataset.action === 'delete') {
            document.getElementById('wlDeleteName').textContent = '"' + btn.dataset.name + '"?';
            document.getElementById('wlDeleteModal').classList.add('active');
            document.getElementById('wlDeleteConfirmBtn').onclick = function() {
                var formData = new URLSearchParams();
                formData.append('action', 'delete');
                formData.append('id', btn.dataset.id);
                formData.append('csrf_token', csrfToken);

                fetch('process_wishlist.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData.toString()
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        showToast('Item deleted', 'success');
                        document.getElementById('wlDeleteModal').classList.remove('active');
                        // Remove the item from DOM
                        var itemEl = document.querySelector('.wishlist-item[data-id="' + btn.dataset.id + '"]');
                        if (itemEl) itemEl.remove();
                        updatePriorityBadges();
                        // Show empty state if no items left
                        if (!document.querySelectorAll('#wishlistList .wishlist-item').length) {
                            document.getElementById('wishlistList').innerHTML = emptyStateHtml;
                        }
                    } else {
                        showToast(data.message || 'Failed to delete', 'error');
                    }
                })
                .catch(function() { showToast('Failed to delete item', 'error'); });
            };
        }
    });

})();
</script>
