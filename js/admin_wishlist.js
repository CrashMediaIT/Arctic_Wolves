/**
 * Admin Wishlist – Drag-and-Drop Reordering & CRUD helpers
 * Uses SortableJS for priority reordering.
 */

(function() {
    'use strict';

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        var list = document.getElementById('wishlist-sortable');
        if (!list || typeof Sortable === 'undefined') return;

        new Sortable(list, {
            animation: 150,
            handle: '.wishlist-handle',
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            onEnd: function() {
                saveOrder(list);
            }
        });
    }

    function saveOrder(list) {
        var items = list.querySelectorAll('.wishlist-item');
        var order = [];
        items.forEach(function(el, idx) {
            order.push({ id: parseInt(el.dataset.id, 10), display_order: idx });
        });

        fetch('process_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({
                action: 'reorder_items',
                order: JSON.stringify(order),
                csrf_token: getCsrfToken()
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('Priority order saved', 'success');
            } else {
                showToast(data.message || 'Failed to save order', 'error');
            }
        })
        .catch(function() {
            showToast('Failed to save order', 'error');
        });
    }

    function getCsrfToken() {
        var input = document.querySelector('input[name="csrf_token"]');
        if (input) return input.value;
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.getAttribute('content');
        return '';
    }

    function showToast(message, type) {
        var toast = document.createElement('div');
        toast.textContent = message;
        var bg = type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#6B46C1';
        toast.style.cssText = 'position:fixed;top:20px;right:20px;padding:16px 24px;background:' + bg +
            ';color:#fff;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.3);z-index:10000;font-family:Inter,sans-serif;font-size:14px;';
        document.body.appendChild(toast);
        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            setTimeout(function() { toast.remove(); }, 300);
        }, 3000);
    }

    // Expose helpers globally for inline onclick handlers
    window.openWishlistModal = function(id, name, description, price, link) {
        var modal = document.getElementById('wishlist-modal');
        var title = document.getElementById('wishlist-modal-title');
        var actionField = document.getElementById('wl-action');
        var idField = document.getElementById('wl-id');
        var submitBtn = document.getElementById('wl-submit-btn');

        document.getElementById('wl-name').value = name || '';
        document.getElementById('wl-description').value = description || '';
        document.getElementById('wl-price').value = (price !== null && price !== undefined && price !== '') ? price : '';
        document.getElementById('wl-link').value = link || '';

        if (id) {
            title.innerHTML = '<i class="fas fa-edit"></i> Edit Wishlist Item';
            actionField.value = 'update_item';
            idField.value = id;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        } else {
            title.innerHTML = '<i class="fas fa-plus-circle"></i> Add Wishlist Item';
            actionField.value = 'create_item';
            idField.value = '';
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Add Item';
        }

        modal.style.display = 'flex';
    };

    window.closeWishlistModal = function() {
        document.getElementById('wishlist-modal').style.display = 'none';
        document.getElementById('wishlist-form').reset();
    };

    window.deleteWishlistItem = function(id, name) {
        if (!confirm('Delete "' + name + '" from the wishlist?')) return;

        fetch('process_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({
                action: 'delete_item',
                id: id,
                csrf_token: getCsrfToken()
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var el = document.querySelector('.wishlist-item[data-id="' + id + '"]');
                if (el) el.remove();
                showToast('Item deleted', 'success');
            } else {
                showToast(data.message || 'Delete failed', 'error');
            }
        })
        .catch(function() {
            showToast('Delete failed', 'error');
        });
    };

    window.togglePurchased = function(id) {
        fetch('process_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({
                action: 'toggle_purchased',
                id: id,
                csrf_token: getCsrfToken()
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var el = document.querySelector('.wishlist-item[data-id="' + id + '"]');
                if (el) {
                    el.classList.toggle('wishlist-purchased');
                    var icon = el.querySelector('.btn-toggle-purchased i');
                    if (icon) {
                        icon.classList.toggle('fa-check-circle');
                        icon.classList.toggle('fa-circle');
                    }
                }
                showToast('Status updated', 'success');
            } else {
                showToast(data.message || 'Update failed', 'error');
            }
        })
        .catch(function() {
            showToast('Update failed', 'error');
        });
    };

})();
