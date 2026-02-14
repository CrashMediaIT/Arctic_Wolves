<?php
/**
 * PWA Admin Packages - Mobile-native packages management list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$packages = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description, price, credits, valid_days, is_active, package_type, age_group, skill_level, store_credit, enable_child_checkin FROM packages ORDER BY name");
    $stmt->execute();
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $packages = []; }
?>
<style>
.m-adminpkg { padding: 16px 16px 100px; font-family: Inter, sans-serif; }
.m-adminpkg-header { margin-bottom: 16px; }
.m-adminpkg-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-adminpkg-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-adminpkg-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-adminpkg-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
.m-adminpkg-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-adminpkg-badge { font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600; margin-left: 8px; flex-shrink: 0; }
.m-adminpkg-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-adminpkg-inactive { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-adminpkg-desc { font-size: 12px; color: #A8A8B8; margin-bottom: 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-adminpkg-meta { display: flex; gap: 14px; flex-wrap: wrap; }
.m-adminpkg-price { font-size: 15px; font-weight: 700; color: #10B981; }
.m-adminpkg-sessions { font-size: 12px; color: #6B6B7B; display: inline-flex; align-items: center; gap: 4px; }
.m-adminpkg-actions { display: flex; gap: 8px; margin-top: 10px; justify-content: flex-end; }
.m-adminpkg-btn { width: 36px; height: 36px; border-radius: 8px; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 13px; }
.m-adminpkg-btn-edit { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-adminpkg-btn-del { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-fab { position: fixed; bottom: 60px; right: 20px; width: 56px; height: 56px; border-radius: 50%; background: #6B46C1; color: #fff; border: none; font-size: 24px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(107,70,193,0.4); cursor: pointer; z-index: 100; }
.m-sheet-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1000; }
.m-sheet-overlay.m-active { display: block; }
.m-sheet { position: fixed; bottom: 0; left: 0; right: 0; background: #16161F; border-radius: 16px 16px 0 0; padding: 20px 16px 32px; z-index: 1001; max-height: 85vh; overflow-y: auto; transform: translateY(100%); transition: transform 0.3s ease; }
.m-sheet-overlay.m-active .m-sheet { transform: translateY(0); }
.m-sheet-handle { width: 40px; height: 4px; background: #3D3D4F; border-radius: 2px; margin: 0 auto 16px; }
.m-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-form-group { margin-bottom: 14px; }
.m-form-label { display: block; font-size: 12px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; text-transform: uppercase; }
.m-form-input, .m-form-select, .m-form-textarea { width: 100%; padding: 10px 12px; background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff; font-size: 14px; min-height: 44px; box-sizing: border-box; font-family: inherit; }
.m-form-textarea { min-height: 70px; resize: vertical; }
.m-form-input:focus, .m-form-select:focus, .m-form-textarea:focus { outline: none; border-color: #6B46C1; }
.m-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.m-form-check { display: flex; align-items: center; gap: 10px; min-height: 44px; }
.m-form-check input[type="checkbox"] { width: 18px; height: 18px; accent-color: #6B46C1; }
.m-form-check label { font-size: 14px; color: #fff; }
.m-btn-submit { width: 100%; padding: 14px; background: #6B46C1; color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; min-height: 44px; margin-top: 4px; }
.m-toast { position: fixed; top: 20px; left: 16px; right: 16px; padding: 14px 16px; border-radius: 10px; color: #fff; font-size: 13px; font-weight: 600; z-index: 2000; display: flex; align-items: center; gap: 8px; }
</style>

<div class="m-adminpkg">
    <div class="m-adminpkg-header">
        <h2 class="m-adminpkg-title">Manage Packages</h2>
        <p class="m-adminpkg-sub"><?= count($packages) ?> package<?= count($packages) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($packages)): ?>
        <div class="m-empty-state">
            <i class="fas fa-box-open"></i>
            <p>No packages found</p>
        </div>
    <?php else: ?>
        <?php foreach ($packages as $pkg):
            $active = (int)($pkg['is_active'] ?? 0);
        ?>
        <div class="m-adminpkg-card" data-id="<?= (int)$pkg['id'] ?>" data-name="<?= htmlspecialchars($pkg['name'] ?? '', ENT_QUOTES) ?>" data-description="<?= htmlspecialchars($pkg['description'] ?? '', ENT_QUOTES) ?>" data-price="<?= (float)($pkg['price'] ?? 0) ?>" data-credits="<?= (int)($pkg['credits'] ?? 0) ?>" data-validdays="<?= (int)($pkg['valid_days'] ?? 365) ?>" data-active="<?= $active ?>" data-type="<?= htmlspecialchars($pkg['package_type'] ?? 'credits', ENT_QUOTES) ?>" data-agegroup="<?= htmlspecialchars($pkg['age_group'] ?? '', ENT_QUOTES) ?>" data-skill="<?= htmlspecialchars($pkg['skill_level'] ?? '', ENT_QUOTES) ?>" data-storecredit="<?= (float)($pkg['store_credit'] ?? 0) ?>" data-checkin="<?= (int)($pkg['enable_child_checkin'] ?? 0) ?>">
            <div class="m-adminpkg-top">
                <div class="m-adminpkg-name"><?= htmlspecialchars($pkg['name'] ?? '') ?></div>
                <span class="m-adminpkg-badge <?= $active ? 'm-adminpkg-active' : 'm-adminpkg-inactive' ?>"><?= $active ? 'Active' : 'Inactive' ?></span>
            </div>
            <?php if (!empty($pkg['description'])): ?>
            <div class="m-adminpkg-desc"><?= htmlspecialchars($pkg['description']) ?></div>
            <?php endif; ?>
            <div class="m-adminpkg-meta">
                <span class="m-adminpkg-price">$<?= number_format((float)($pkg['price'] ?? 0), 2) ?></span>
                <?php if (!empty($pkg['credits'])): ?>
                <span class="m-adminpkg-sessions"><i class="fas fa-calendar-check"></i> <?= (int)$pkg['credits'] ?> credits</span>
                <?php endif; ?>
                <?php if (!empty($pkg['valid_days'])): ?>
                <span class="m-adminpkg-sessions"><i class="fas fa-clock"></i> <?= (int)$pkg['valid_days'] ?> days</span>
                <?php endif; ?>
            </div>
            <div class="m-adminpkg-actions">
                <button class="m-adminpkg-btn m-adminpkg-btn-edit" onclick="mEditPkg(this.closest('.m-adminpkg-card'))"><i class="fas fa-pencil-alt"></i></button>
                <button class="m-adminpkg-btn m-adminpkg-btn-del" onclick="mDeletePkg(this.closest('.m-adminpkg-card'))"><i class="fas fa-trash"></i></button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button class="m-fab" onclick="mOpenPkgSheet('create')"><i class="fas fa-plus"></i></button>

<div id="mPkgSheet" class="m-sheet-overlay">
    <div class="m-sheet">
        <div class="m-sheet-handle"></div>
        <h3 class="m-sheet-title" id="mPkgSheetTitle">Create Package</h3>
        <form method="POST" action="process_packages.php" id="mPkgForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" id="mPkgAction" value="create">
            <input type="hidden" name="package_id" id="mPkgId" value="">
            <div class="m-form-group">
                <label class="m-form-label">Name *</label>
                <input type="text" name="name" id="mPkgName" class="m-form-input" required placeholder="Package name">
            </div>
            <div class="m-form-group">
                <label class="m-form-label">Type *</label>
                <select name="package_type" id="mPkgType" class="m-form-select" required onchange="mTogglePkgFields()">
                    <option value="credits">Session Credits</option>
                    <option value="dollar_value">Dollar Value</option>
                    <option value="bundled">Bundled Sessions</option>
                </select>
            </div>
            <div class="m-form-group">
                <label class="m-form-label">Description</label>
                <textarea name="description" id="mPkgDesc" class="m-form-textarea" placeholder="Optional description"></textarea>
            </div>
            <div class="m-form-row">
                <div class="m-form-group">
                    <label class="m-form-label">Price *</label>
                    <input type="number" name="price" id="mPkgPrice" class="m-form-input" step="0.01" min="0" required>
                </div>
                <div class="m-form-group" id="mPkgCreditsGroup">
                    <label class="m-form-label">Sessions</label>
                    <input type="number" name="credits" id="mPkgCredits" class="m-form-input" min="1">
                </div>
            </div>
            <div class="m-form-group" id="mPkgStoreCreditGroup" style="display:none;">
                <label class="m-form-label">Store Credit ($)</label>
                <input type="number" name="store_credit" id="mPkgStoreCredit" class="m-form-input" step="0.01" min="0">
            </div>
            <div class="m-form-row">
                <div class="m-form-group">
                    <label class="m-form-label">Valid Days</label>
                    <input type="number" name="valid_days" id="mPkgDays" class="m-form-input" min="1" value="365">
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Status</label>
                    <select name="is_active" id="mPkgActive" class="m-form-select">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="m-form-check">
                <input type="checkbox" name="enable_child_checkin" id="mPkgCheckin" value="1">
                <label for="mPkgCheckin">Enable Child Check-In/Check-Out</label>
            </div>
            <button type="submit" class="m-btn-submit"><i class="fas fa-save"></i> Save Package</button>
        </form>
    </div>
</div>

<script>
(function(){
    var csrfToken = document.querySelector('#mPkgForm [name="csrf_token"]') ? document.querySelector('#mPkgForm [name="csrf_token"]').value : '';

    window.mTogglePkgFields = function() {
        var type = document.getElementById('mPkgType').value;
        document.getElementById('mPkgCreditsGroup').style.display = type === 'credits' ? '' : 'none';
        document.getElementById('mPkgStoreCreditGroup').style.display = type === 'dollar_value' ? '' : 'none';
    };

    window.mOpenPkgSheet = function(mode, card) {
        var sheet = document.getElementById('mPkgSheet');
        document.getElementById('mPkgSheetTitle').textContent = mode === 'edit' ? 'Edit Package' : 'Create Package';
        document.getElementById('mPkgAction').value = mode === 'edit' ? 'update' : 'create';
        if (mode === 'edit' && card) {
            document.getElementById('mPkgId').value = card.dataset.id;
            document.getElementById('mPkgName').value = card.dataset.name;
            document.getElementById('mPkgType').value = card.dataset.type || 'credits';
            document.getElementById('mPkgDesc').value = card.dataset.description;
            document.getElementById('mPkgPrice').value = card.dataset.price;
            document.getElementById('mPkgCredits').value = card.dataset.credits || '';
            document.getElementById('mPkgStoreCredit').value = card.dataset.storecredit > 0 ? card.dataset.storecredit : '';
            document.getElementById('mPkgDays').value = card.dataset.validdays || '365';
            document.getElementById('mPkgActive').value = card.dataset.active;
            document.getElementById('mPkgCheckin').checked = card.dataset.checkin === '1';
        } else {
            document.getElementById('mPkgId').value = '';
            document.getElementById('mPkgName').value = '';
            document.getElementById('mPkgType').value = 'credits';
            document.getElementById('mPkgDesc').value = '';
            document.getElementById('mPkgPrice').value = '';
            document.getElementById('mPkgCredits').value = '';
            document.getElementById('mPkgStoreCredit').value = '';
            document.getElementById('mPkgDays').value = '365';
            document.getElementById('mPkgActive').value = '1';
            document.getElementById('mPkgCheckin').checked = false;
        }
        mTogglePkgFields();
        sheet.classList.add('m-active');
    };

    window.mEditPkg = function(card) { mOpenPkgSheet('edit', card); };

    window.mDeletePkg = function(card) {
        if (!confirm('Delete "' + card.dataset.name + '"?')) return;
        fetch('process_packages.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: 'action=delete&package_id=' + encodeURIComponent(card.dataset.id) + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            mToast(d.message || (d.success ? 'Deleted!' : 'Error'), d.success ? 'success' : 'error');
            if (d.success) setTimeout(function() { location.reload(); }, 1200);
        })
        .catch(function() { mToast('An error occurred', 'error'); });
    };

    document.getElementById('mPkgSheet').addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('m-active');
    });

    document.getElementById('mPkgForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = this;
        var btn = form.querySelector('.m-btn-submit');
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;
        fetch(form.getAttribute('action'), {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            btn.innerHTML = orig; btn.disabled = false;
            mToast(d.message || (d.success ? 'Saved!' : 'Error'), d.success ? 'success' : 'error');
            if (d.success) { document.getElementById('mPkgSheet').classList.remove('m-active'); setTimeout(function() { location.reload(); }, 1200); }
        })
        .catch(function() { btn.innerHTML = orig; btn.disabled = false; mToast('An error occurred', 'error'); });
    });

    window.mToast = function(msg, type) {
        var old = document.querySelector('.m-toast');
        if (old) old.remove();
        var d = document.createElement('div');
        d.className = 'm-toast';
        d.style.background = type === 'success' ? 'rgba(16,185,129,0.95)' : 'rgba(239,68,68,0.95)';
        var icon = document.createElement('i');
        icon.className = 'fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle');
        d.appendChild(icon);
        var span = document.createElement('span');
        span.textContent = msg;
        d.appendChild(span);
        document.body.appendChild(d);
        setTimeout(function() { if (d.parentElement) d.remove(); }, 4000);
    };
})();
</script>
