<?php
/**
 * PWA Admin Wishlist - Mobile-native wishlist management
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

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
    if (strpos($e->getMessage(), 'already exists') === false) {
        error_log('Wishlist table creation error: ' . $e->getMessage());
    }
}

$items = [];
$totalCost = 0;
$purchasedCount = 0;
try {
    $stmt = $pdo->query("SELECT * FROM admin_wishlist ORDER BY display_order ASC, id ASC");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as $item) {
        if ($item['price']) $totalCost += (float)$item['price'];
        if ($item['purchased']) $purchasedCount++;
    }
} catch (PDOException $e) { $items = []; }
?>
<style>
.m-wishlist { padding: 16px; font-family: Inter, sans-serif; }
.m-wishlist-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.m-wishlist-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-wishlist-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-wishlist-add-btn {
    min-width: 44px; min-height: 44px; border-radius: 50%;
    background: #6B46C1; color: #fff; border: none; font-size: 18px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.m-wl-stats {
    display: flex; gap: 8px; margin-bottom: 16px;
}
.m-wl-stat {
    flex: 1; text-align: center; padding: 10px 6px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
}
.m-wl-stat-val { font-size: 16px; font-weight: 700; color: #fff; display: block; }
.m-wl-stat-label { font-size: 10px; color: #A8A8B8; text-transform: uppercase; letter-spacing: 0.5px; }
.m-wl-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-wl-card.m-wl-purchased { opacity: 0.55; }
.m-wl-card.m-wl-purchased .m-wl-name { text-decoration: line-through; }
.m-wl-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
.m-wl-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; min-width: 0; }
.m-wl-price {
    font-size: 12px; padding: 3px 10px; border-radius: 8px; font-weight: 600;
    background: rgba(16,185,129,0.15); color: #10B981; white-space: nowrap; flex-shrink: 0;
}
.m-wl-desc {
    font-size: 12px; color: #A8A8B8; margin: 6px 0 0;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.m-wl-link {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; color: #8B5CF6; text-decoration: none; margin-top: 6px;
}
.m-wl-actions { display: flex; gap: 4px; margin-top: 10px; justify-content: flex-end; }
.m-wl-btn {
    min-width: 36px; min-height: 36px; border: none; border-radius: 8px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 13px; background: none; padding: 0;
}
.m-wl-btn-check { color: #10B981; background: rgba(16,185,129,0.1); }
.m-wl-btn-edit { color: #8B5CF6; background: rgba(139,92,246,0.1); }
.m-wl-btn-del { color: #EF4444; background: rgba(239,68,68,0.1); }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
/* Bottom sheet */
.m-bs-overlay {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); z-index: 999;
}
.m-bs-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000;
    background: #16161F; border-radius: 16px 16px 0 0;
    padding: 20px 16px calc(20px + env(safe-area-inset-bottom)); display: none;
    max-height: 85vh; overflow-y: auto;
}
.m-bs-handle { width: 40px; height: 4px; background: #2D2D3F; border-radius: 2px; margin: 0 auto 16px; }
.m-bs-title { font-size: 16px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-form-group { margin-bottom: 12px; }
.m-form-label { font-size: 12px; color: #A8A8B8; margin-bottom: 6px; display: block; }
.m-form-input, .m-form-textarea {
    width: 100%; min-height: 44px; padding: 12px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box;
}
.m-form-textarea { min-height: 70px; resize: vertical; }
.m-form-input:focus, .m-form-textarea:focus { border-color: #8B5CF6; outline: none; }
.m-form-submit {
    width: 100%; min-height: 44px; border-radius: 10px;
    background: #6B46C1; color: #fff; font-size: 14px; font-weight: 600;
    border: none; cursor: pointer; font-family: Inter, sans-serif; margin-top: 8px;
}
.m-form-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.m-wl-toast {
    position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%);
    padding: 10px 20px; border-radius: 10px; font-size: 13px; font-weight: 600;
    z-index: 2000; opacity: 0; transition: opacity 0.3s; font-family: Inter, sans-serif;
}
.m-wl-toast.m-show { opacity: 1; }
.m-wl-toast-ok { background: rgba(16,185,129,0.9); color: #fff; }
.m-wl-toast-err { background: rgba(239,68,68,0.9); color: #fff; }
</style>

<div class="m-wishlist">
    <div class="m-wishlist-header">
        <div>
            <h2 class="m-wishlist-title">Business Wishlist</h2>
            <p class="m-wishlist-sub"><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?></p>
        </div>
        <button class="m-wishlist-add-btn" type="button" onclick="mWlOpen()" title="Add Item">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <div class="m-wl-stats">
        <div class="m-wl-stat">
            <span class="m-wl-stat-val"><?= count($items) ?></span>
            <span class="m-wl-stat-label">Items</span>
        </div>
        <div class="m-wl-stat">
            <span class="m-wl-stat-val">$<?= number_format($totalCost, 2) ?></span>
            <span class="m-wl-stat-label">Total</span>
        </div>
        <div class="m-wl-stat">
            <span class="m-wl-stat-val"><?= $purchasedCount ?></span>
            <span class="m-wl-stat-label">Purchased</span>
        </div>
    </div>

    <?php if (empty($items)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clipboard-list"></i>
            <p>No wishlist items yet.<br>Tap <strong>+</strong> to add one.</p>
        </div>
    <?php else: ?>
        <div id="mWlList">
        <?php foreach ($items as $item): ?>
        <div class="m-wl-card <?= $item['purchased'] ? 'm-wl-purchased' : '' ?>"
             id="mWl-<?= (int)$item['id'] ?>"
             data-id="<?= (int)$item['id'] ?>"
             data-name="<?= htmlspecialchars($item['name'] ?? '', ENT_QUOTES) ?>"
             data-description="<?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES) ?>"
             data-price="<?= htmlspecialchars($item['price'] ?? '', ENT_QUOTES) ?>"
             data-link="<?= htmlspecialchars($item['link'] ?? '', ENT_QUOTES) ?>">
            <div class="m-wl-top">
                <span class="m-wl-name"><?= htmlspecialchars($item['name']) ?></span>
                <?php if ($item['price']): ?>
                <span class="m-wl-price">$<?= number_format((float)$item['price'], 2) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($item['description'])): ?>
            <p class="m-wl-desc"><?= htmlspecialchars($item['description']) ?></p>
            <?php endif; ?>
            <?php if (!empty($item['link'])):
                $linkUrl = $item['link'];
                $linkScheme = strtolower(parse_url($linkUrl, PHP_URL_SCHEME) ?: '');
                $isSafeLink = in_array($linkScheme, ['http', 'https', ''], true);
            ?>
            <?php if ($isSafeLink): ?>
            <a href="<?= htmlspecialchars($linkUrl) ?>" target="_blank" rel="noopener noreferrer" class="m-wl-link">
                <i class="fas fa-external-link-alt"></i> <?= htmlspecialchars(parse_url($linkUrl, PHP_URL_HOST) ?: 'Link') ?>
            </a>
            <?php endif; ?>
            <?php endif; ?>
            <div class="m-wl-actions">
                <button type="button" class="m-wl-btn m-wl-btn-check" onclick="mWlToggle(<?= (int)$item['id'] ?>)" title="<?= $item['purchased'] ? 'Unmark purchased' : 'Mark purchased' ?>">
                    <i class="fas <?= $item['purchased'] ? 'fa-check-circle' : 'fa-circle' ?>"></i>
                </button>
                <button type="button" class="m-wl-btn m-wl-btn-edit" onclick="mWlEditFromCard(<?= (int)$item['id'] ?>)" title="Edit">
                    <i class="fas fa-pen"></i>
                </button>
                <button type="button" class="m-wl-btn m-wl-btn-del" onclick="mWlDel(<?= (int)$item['id'] ?>)" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="m-bs-overlay" id="mWlOverlay" onclick="mWlClose()"></div>
<div class="m-bs-sheet" id="mWlSheet">
    <div class="m-bs-handle"></div>
    <h3 class="m-bs-title" id="mWlSheetTitle">Add Wishlist Item</h3>
    <form id="mWlForm" onsubmit="return mWlSubmit(event)">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
        <input type="hidden" name="action" id="mWlAction" value="create_item">
        <input type="hidden" name="id" id="mWlId" value="">
        <div class="m-form-group">
            <label class="m-form-label">Name *</label>
            <input type="text" name="name" id="mWlName" class="m-form-input" required maxlength="255">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Description</label>
            <textarea name="description" id="mWlDesc" class="m-form-textarea" rows="2"></textarea>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Estimated Price ($)</label>
            <input type="number" name="price" id="mWlPrice" class="m-form-input" step="0.01" min="0" inputmode="decimal">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Purchase Link</label>
            <input type="url" name="link" id="mWlLink" class="m-form-input" maxlength="2048" inputmode="url">
        </div>
        <button type="submit" class="m-form-submit" id="mWlSubmitBtn">Add Item</button>
    </form>
</div>
<div class="m-wl-toast" id="mWlToast"></div>

<script>
(function() {
    var csrfToken = document.querySelector('#mWlForm [name="csrf_token"]')?.value || '';

    function toast(msg, type) {
        var t = document.getElementById('mWlToast');
        t.textContent = msg;
        t.className = 'm-wl-toast m-show ' + (type === 'error' ? 'm-wl-toast-err' : 'm-wl-toast-ok');
        setTimeout(function() { t.classList.remove('m-show'); }, 3000);
    }

    window.mWlOpen = function(data) {
        document.getElementById('mWlSheetTitle').textContent = data ? 'Edit Wishlist Item' : 'Add Wishlist Item';
        document.getElementById('mWlAction').value = data ? 'update_item' : 'create_item';
        document.getElementById('mWlId').value = data ? data.id : '';
        document.getElementById('mWlName').value = data ? (data.name || '') : '';
        document.getElementById('mWlDesc').value = data ? (data.description || '') : '';
        document.getElementById('mWlPrice').value = (data && data.price !== '' && data.price != null) ? data.price : '';
        document.getElementById('mWlLink').value = data ? (data.link || '') : '';
        document.getElementById('mWlSubmitBtn').textContent = data ? 'Save Changes' : 'Add Item';
        document.getElementById('mWlSubmitBtn').disabled = false;
        document.getElementById('mWlSheet').style.display = 'block';
        document.getElementById('mWlOverlay').style.display = 'block';
    };

    window.mWlEditFromCard = function(id) {
        var card = document.getElementById('mWl-' + id);
        if (!card) return;
        mWlOpen({ id: id, name: card.dataset.name, description: card.dataset.description, price: card.dataset.price, link: card.dataset.link });
    };

    window.mWlClose = function() {
        document.getElementById('mWlSheet').style.display = 'none';
        document.getElementById('mWlOverlay').style.display = 'none';
        document.getElementById('mWlForm').reset();
    };

    window.mWlSubmit = function(e) {
        e.preventDefault();
        var btn = document.getElementById('mWlSubmitBtn');
        btn.disabled = true;
        btn.textContent = 'Saving…';
        var fd = new FormData(document.getElementById('mWlForm'));
        fetch('process_wishlist.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    mWlClose();
                    persistToast(data.message || 'Saved', 'success');
                    window.location.reload();
                } else { toast(data.message || 'Error saving', 'error'); }
            })
            .catch(function() { toast('Network error', 'error'); })
            .finally(function() { btn.disabled = false; btn.textContent = 'Save'; });
        return false;
    };

    window.mWlToggle = function(id) {
        fetch('process_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ action: 'toggle_purchased', id: id, csrf_token: csrfToken })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var card = document.getElementById('mWl-' + id);
                if (card) {
                    card.classList.toggle('m-wl-purchased');
                    var icon = card.querySelector('.m-wl-btn-check i');
                    if (icon) { icon.classList.toggle('fa-check-circle'); icon.classList.toggle('fa-circle'); }
                }
                toast('Status updated', 'success');
            } else { toast(data.message || 'Update failed', 'error'); }
        })
        .catch(function() { toast('Network error', 'error'); });
    };

    window.mWlDel = async function(id) {
        if (!await showConfirmModal('Delete this wishlist item?')) return;
        fetch('process_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ action: 'delete_item', id: id, csrf_token: csrfToken })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var el = document.getElementById('mWl-' + id);
                if (el) el.remove();
                toast('Item deleted', 'success');
            } else { toast(data.message || 'Delete failed', 'error'); }
        })
        .catch(function() { toast('Network error', 'error'); });
    };
})();
</script>
