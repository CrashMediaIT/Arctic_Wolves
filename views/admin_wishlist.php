<!-- Admin Business Wishlist -->
<?php
// Ensure table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_wishlist` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `price` DECIMAL(10,2) DEFAULT NULL,
        `link` VARCHAR(2048) DEFAULT NULL COMMENT 'Purchase URL or distributor info',
        `display_order` INT DEFAULT 0,
        `purchased` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_display_order` (`display_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Table creation may fail if it already exists — expected on first load
    if (strpos($e->getMessage(), 'already exists') === false) {
        error_log('Wishlist table creation error: ' . $e->getMessage());
    }
}

// Fetch wishlist items
$wishlistItems = [];
$totalItems = 0;
$totalCost = 0;
$purchasedCount = 0;
try {
    $stmt = $pdo->query("SELECT * FROM admin_wishlist ORDER BY display_order ASC, id ASC");
    $wishlistItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalItems = count($wishlistItems);
    foreach ($wishlistItems as $item) {
        if ($item['price']) $totalCost += (float)$item['price'];
        if ($item['purchased']) $purchasedCount++;
    }
} catch (PDOException $e) {
    // Table may not exist yet
}
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-clipboard-list"></i> Business Wishlist</h1>
        <p class="page-description">Track items to purchase for the business, ordered by priority</p>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value"><?= $totalItems ?></span>
            <span class="stat-label">Items</span>
        </div>
        <div class="header-stat">
            <span class="stat-value">$<?= number_format($totalCost, 2) ?></span>
            <span class="stat-label">Total Cost</span>
        </div>
        <div class="header-stat">
            <span class="stat-value"><?= $purchasedCount ?></span>
            <span class="stat-label">Purchased</span>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list-ol"></i> Wishlist Items</h3>
        <button type="button" class="btn btn-primary" onclick="openWishlistModal()"><i class="fas fa-plus"></i> Add Item</button>
    </div>
    <div class="card-body">
        <?php if (empty($wishlistItems)): ?>
            <div style="text-align:center;padding:40px 20px;color:var(--text-secondary,#6b7280);">
                <i class="fas fa-clipboard-list" style="font-size:48px;display:block;margin-bottom:12px;opacity:.5;"></i>
                <p style="font-size:15px;">No wishlist items yet. Click <strong>Add Item</strong> to get started.</p>
            </div>
        <?php else: ?>
            <p style="font-size:.85rem;color:var(--text-secondary,#6b7280);margin-bottom:1rem;">
                <i class="fas fa-grip-vertical"></i> Drag items up or down to change purchasing priority.
            </p>
            <div class="wishlist-items" id="wishlist-sortable">
                <?php foreach ($wishlistItems as $item): ?>
                <div class="wishlist-item <?= $item['purchased'] ? 'wishlist-purchased' : '' ?>"
                     data-id="<?= (int)$item['id'] ?>"
                     data-name="<?= htmlspecialchars($item['name'] ?? '', ENT_QUOTES) ?>"
                     data-description="<?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES) ?>"
                     data-price="<?= htmlspecialchars($item['price'] ?? '', ENT_QUOTES) ?>"
                     data-link="<?= htmlspecialchars($item['link'] ?? '', ENT_QUOTES) ?>">
                    <div class="wishlist-handle"><i class="fas fa-grip-vertical"></i></div>
                    <div class="wishlist-item-body">
                        <div class="wishlist-item-header">
                            <span class="wishlist-item-name"><?= htmlspecialchars($item['name']) ?></span>
                            <?php if ($item['price']): ?>
                            <span class="wishlist-item-price">$<?= number_format((float)$item['price'], 2) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($item['description'])): ?>
                        <p class="wishlist-item-desc"><?= htmlspecialchars($item['description']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($item['link'])): ?>
                        <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank" rel="noopener noreferrer" class="wishlist-item-link">
                            <i class="fas fa-external-link-alt"></i> <?= htmlspecialchars(parse_url($item['link'], PHP_URL_HOST) ?: $item['link']) ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="wishlist-item-actions">
                        <button type="button" class="btn-icon btn-toggle-purchased" title="<?= $item['purchased'] ? 'Mark as not purchased' : 'Mark as purchased' ?>">
                            <i class="fas <?= $item['purchased'] ? 'fa-check-circle' : 'fa-circle' ?>"></i>
                        </button>
                        <button type="button" class="btn-icon btn-edit" title="Edit">
                            <i class="fas fa-pen"></i>
                        </button>
                        <button type="button" class="btn-icon btn-delete" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Wishlist Item Modal -->
<div id="wishlist-modal" class="modal-overlay" style="display:none;">
    <div class="modal-container" style="max-width:560px;">
        <div class="modal-header">
            <h3 id="wishlist-modal-title"><i class="fas fa-plus-circle"></i> Add Wishlist Item</h3>
            <button type="button" class="modal-close" onclick="closeWishlistModal()">&times;</button>
        </div>
        <form id="wishlist-form" method="POST" action="process_wishlist.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
            <input type="hidden" name="action" id="wl-action" value="create_item">
            <input type="hidden" name="id" id="wl-id" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label for="wl-name">Name <span style="color:#ef4444;">*</span></label>
                    <input type="text" id="wl-name" name="name" class="form-control" required placeholder="Item name" maxlength="255">
                </div>
                <div class="form-group">
                    <label for="wl-description">Description</label>
                    <textarea id="wl-description" name="description" class="form-control" rows="3" placeholder="Brief description of the item"></textarea>
                </div>
                <div class="form-group">
                    <label for="wl-price">Estimated Price ($)</label>
                    <input type="number" id="wl-price" name="price" class="form-control" step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label for="wl-link">Purchase Link / Distributor</label>
                    <input type="url" id="wl-link" name="link" class="form-control" placeholder="https://example.com/product" maxlength="2048">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeWishlistModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary" id="wl-submit-btn"><i class="fas fa-save"></i> Add Item</button>
            </div>
        </form>
    </div>
</div>

<style>
.wishlist-items {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.wishlist-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: var(--bg-main, #06080b);
    border: 1px solid var(--border, #1e293b);
    border-radius: 10px;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.wishlist-item:hover {
    border-color: var(--primary, #7c3aed);
}
.wishlist-item.sortable-ghost {
    opacity: 0.4;
    border-color: var(--primary, #7c3aed);
    box-shadow: 0 0 0 2px rgba(124,58,237,0.3);
}
.wishlist-item.sortable-drag {
    background: var(--bg-card, #0f1117);
    box-shadow: 0 8px 24px rgba(0,0,0,0.4);
}
.wishlist-purchased {
    opacity: 0.6;
}
.wishlist-purchased .wishlist-item-name {
    text-decoration: line-through;
}
.wishlist-handle {
    cursor: grab;
    color: var(--text-secondary, #6b7280);
    font-size: 16px;
    padding: 4px;
    flex-shrink: 0;
}
.wishlist-handle:active {
    cursor: grabbing;
}
.wishlist-item-body {
    flex: 1;
    min-width: 0;
}
.wishlist-item-header {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.wishlist-item-name {
    font-weight: 600;
    font-size: 15px;
    color: var(--text-primary, #e2e8f0);
}
.wishlist-item-price {
    background: rgba(16,185,129,0.15);
    color: #10b981;
    font-size: 13px;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 12px;
    white-space: nowrap;
}
.wishlist-item-desc {
    margin: 4px 0 0;
    font-size: 13px;
    color: var(--text-secondary, #6b7280);
    line-height: 1.4;
}
.wishlist-item-link {
    display: inline-block;
    margin-top: 4px;
    font-size: 12px;
    color: var(--primary, #7c3aed);
    text-decoration: none;
}
.wishlist-item-link:hover {
    text-decoration: underline;
}
.wishlist-item-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}
.btn-icon {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.2s;
}
.btn-toggle-purchased {
    background: rgba(16,185,129,0.1);
    color: #10b981;
}
.btn-toggle-purchased:hover {
    background: rgba(16,185,129,0.25);
}
.btn-edit {
    background: rgba(59,130,246,0.1);
    color: #3b82f6;
}
.btn-edit:hover {
    background: rgba(59,130,246,0.25);
}
.btn-delete {
    background: rgba(239,68,68,0.1);
    color: #ef4444;
}
.btn-delete:hover {
    background: rgba(239,68,68,0.25);
}
</style>

<!-- SortableJS for drag-and-drop reordering -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"
        integrity="sha256-ipiJrswvAR4VAx/th+6zWsdeYmVae0iJuiR+6OqHJHQ="
        crossorigin="anonymous"></script>

<script src="js/admin_wishlist.js"></script>
