<?php
/**
 * PWA Admin Locations - Mobile-native locations management
 * Purpose-built for mobile phones, not a desktop adaptation.
 * Supports full CRUD: list, create, edit, delete.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$locations = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, address, city, capacity FROM locations WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $locations = []; }
?>
<style>
.m-locations { padding: 16px; font-family: Inter, sans-serif; }
.m-locations-header { margin-bottom: 16px; }
.m-locations-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-locations-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-loc-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-loc-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(59,130,246,0.15); color: #3B82F6; font-size: 16px; flex-shrink: 0;
}
.m-loc-body { flex: 1; min-width: 0; }
.m-loc-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-loc-addr { font-size: 12px; color: #A8A8B8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-loc-cap {
    font-size: 11px; color: #6B6B7B; margin-top: 4px;
    display: inline-flex; align-items: center; gap: 4px;
}
.m-loc-actions {
    display: flex; flex-direction: column; gap: 6px; flex-shrink: 0;
}
.m-loc-btn {
    width: 40px; height: 40px; border-radius: 10px; border: none;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; cursor: pointer; min-height: 44px; min-width: 44px;
}
.m-loc-btn-edit { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-loc-btn-delete { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }

/* FAB */
.m-loc-fab {
    position: fixed; bottom: 80px; right: 20px; z-index: 50;
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    color: #fff; font-size: 22px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(107,70,193,0.4);
    border: none; cursor: pointer;
}

/* Bottom-sheet modal */
.m-loc-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 200; }
.m-loc-overlay.m-show { display: flex; align-items: flex-end; }
.m-loc-sheet {
    width: 100%; max-height: 90vh; background: #16161F;
    border-radius: 16px 16px 0 0;
    padding: 20px; overflow-y: auto; -webkit-overflow-scrolling: touch;
}
.m-loc-handle {
    width: 40px; height: 4px; background: #3D3D4F; border-radius: 2px;
    margin: 0 auto 16px;
}
.m-loc-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-loc-field { margin-bottom: 14px; }
.m-loc-field label {
    display: block; font-size: 12px; font-weight: 600; color: #A8A8B8;
    margin-bottom: 6px;
}
.m-loc-field input {
    width: 100%; padding: 10px 12px; border-radius: 10px;
    background: #0A0A0F; border: 1px solid #2D2D3F; color: #fff;
    font-size: 14px; font-family: Inter, sans-serif;
    min-height: 44px; box-sizing: border-box;
}
.m-loc-field input:focus { outline: none; border-color: #6B46C1; }
.m-loc-modal-actions {
    display: flex; gap: 10px; margin-top: 16px; padding-bottom: env(safe-area-inset-bottom, 12px);
}
.m-loc-btn-cancel, .m-loc-btn-save {
    flex: 1; padding: 12px; border-radius: 10px; font-size: 14px; font-weight: 600;
    border: none; cursor: pointer; min-height: 44px; font-family: Inter, sans-serif;
}
.m-loc-btn-cancel { background: #2D2D3F; color: #A8A8B8; }
.m-loc-btn-save { background: linear-gradient(135deg, #6B46C1, #8B5CF6); color: #fff; }
</style>

<div class="m-locations">
    <div class="m-locations-header">
        <h2 class="m-locations-title">Locations</h2>
        <p class="m-locations-sub"><?= count($locations) ?> location<?= count($locations) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($locations)): ?>
        <div class="m-empty-state">
            <i class="fas fa-map-marker-alt"></i>
            <p>No locations found</p>
        </div>
    <?php else: ?>
        <?php foreach ($locations as $loc): ?>
        <div class="m-loc-card">
            <div class="m-loc-icon"><i class="fas fa-map-marker-alt"></i></div>
            <div class="m-loc-body">
                <div class="m-loc-name"><?= htmlspecialchars($loc['name'] ?? '') ?></div>
                <?php
                    $addrParts = array_filter([
                        $loc['address'] ?? '',
                        $loc['city'] ?? ''
                    ]);
                ?>
                <?php if (!empty($addrParts)): ?>
                <div class="m-loc-addr"><?= htmlspecialchars(implode(', ', $addrParts)) ?></div>
                <?php endif; ?>
                <?php if (!empty($loc['capacity'])): ?>
                <div class="m-loc-cap"><i class="fas fa-users"></i> Capacity: <?= (int)$loc['capacity'] ?></div>
                <?php endif; ?>
            </div>
            <div class="m-loc-actions">
                <button class="m-loc-btn m-loc-btn-edit" data-id="<?= (int)$loc['id'] ?>" data-name="<?= htmlspecialchars($loc['name'] ?? '', ENT_QUOTES) ?>" data-address="<?= htmlspecialchars($loc['address'] ?? '', ENT_QUOTES) ?>" data-city="<?= htmlspecialchars($loc['city'] ?? '', ENT_QUOTES) ?>" data-capacity="<?= htmlspecialchars($loc['capacity'] ?? '', ENT_QUOTES) ?>" title="Edit"><i class="fas fa-pencil-alt"></i></button>
                <button class="m-loc-btn m-loc-btn-delete" data-id="<?= (int)$loc['id'] ?>" title="Delete"><i class="fas fa-trash"></i></button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- FAB: Add Location -->
<button class="m-loc-fab" onclick="mOpenLocationModal()" title="Add Location">
    <i class="fas fa-plus"></i>
</button>

<!-- Add/Edit Location Bottom-Sheet -->
<div class="m-loc-overlay" id="mLocModal">
    <div class="m-loc-sheet">
        <div class="m-loc-handle"></div>
        <div class="m-loc-sheet-title" id="mLocModalTitle">Add Location</div>
        <form method="POST" action="process_admin_action.php" id="mLocForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="create_location" id="mLocAction">
            <input type="hidden" name="location_id" value="" id="mLocId">
            <div class="m-loc-field">
                <label for="mLocName">Name *</label>
                <input type="text" name="name" id="mLocName" placeholder="Arena name" required>
            </div>
            <div class="m-loc-field">
                <label for="mLocAddress">Address</label>
                <input type="text" name="address" id="mLocAddress" placeholder="Street address">
            </div>
            <div class="m-loc-field">
                <label for="mLocCity">City *</label>
                <input type="text" name="city" id="mLocCity" placeholder="City" required>
            </div>
            <div class="m-loc-field">
                <label for="mLocCapacity">Capacity</label>
                <input type="number" name="capacity" id="mLocCapacity" placeholder="0" min="0">
            </div>
            <div class="m-loc-modal-actions">
                <button type="button" class="m-loc-btn-cancel" onclick="mCloseLocationModal()">Cancel</button>
                <button type="submit" class="m-loc-btn-save" id="mLocSaveBtn">Save Location</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden delete form -->
<form method="POST" action="process_admin_action.php" id="mLocDeleteForm" style="display:none;">
    <?= csrfTokenInput() ?>
    <input type="hidden" name="action" value="delete_location">
    <input type="hidden" name="location_id" value="" id="mLocDeleteId">
</form>

<script>
(function() {
    var modal = document.getElementById('mLocModal');
    var form = document.getElementById('mLocForm');

    window.mOpenLocationModal = function() {
        document.getElementById('mLocModalTitle').textContent = 'Add Location';
        document.getElementById('mLocAction').value = 'create_location';
        document.getElementById('mLocId').value = '';
        document.getElementById('mLocName').value = '';
        document.getElementById('mLocAddress').value = '';
        document.getElementById('mLocCity').value = '';
        document.getElementById('mLocCapacity').value = '';
        document.getElementById('mLocSaveBtn').textContent = 'Save Location';
        modal.classList.add('m-show');
    };

    window.mEditLocation = function(id, name, address, city, capacity) {
        document.getElementById('mLocModalTitle').textContent = 'Edit Location';
        document.getElementById('mLocAction').value = 'edit_location';
        document.getElementById('mLocId').value = id;
        document.getElementById('mLocName').value = name || '';
        document.getElementById('mLocAddress').value = address || '';
        document.getElementById('mLocCity').value = city || '';
        document.getElementById('mLocCapacity').value = capacity || '';
        document.getElementById('mLocSaveBtn').textContent = 'Update Location';
        modal.classList.add('m-show');
    };

    window.mCloseLocationModal = function() {
        modal.classList.remove('m-show');
    };

    window.mDeleteLocation = function(id) {
        if (confirm('Are you sure you want to delete this location?')) {
            document.getElementById('mLocDeleteId').value = id;
            document.getElementById('mLocDeleteForm').submit();
        }
    };

    // Close on overlay tap
    modal.addEventListener('click', function(e) {
        if (e.target === modal) mCloseLocationModal();
    });

    // Delegate edit/delete button clicks
    document.addEventListener('click', function(e) {
        var editBtn = e.target.closest('.m-loc-btn-edit');
        if (editBtn) {
            mEditLocation(
                editBtn.getAttribute('data-id'),
                editBtn.getAttribute('data-name'),
                editBtn.getAttribute('data-address'),
                editBtn.getAttribute('data-city'),
                editBtn.getAttribute('data-capacity')
            );
            return;
        }
        var deleteBtn = e.target.closest('.m-loc-btn-delete');
        if (deleteBtn) {
            mDeleteLocation(deleteBtn.getAttribute('data-id'));
        }
    });

    // AJAX form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = document.getElementById('mLocSaveBtn');
        var origText = btn.textContent;
        btn.textContent = 'Saving...';
        btn.disabled = true;

        fetch(form.getAttribute('action'), {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.textContent = origText;
            btn.disabled = false;
            if (data.success) {
                mCloseLocationModal();
                persistToast(data.message || 'Operation completed successfully', 'success');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to save'));
            }
        })
        .catch(function() {
            btn.textContent = origText;
            btn.disabled = false;
            alert('An error occurred. Please try again.');
        });
    });
})();
</script>
