<?php
/**
 * PWA Admin Discounts - Mobile-native discounts list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$discounts = [];
try {
    $stmt = $pdo->prepare("SELECT id, code, discount_type, discount_value, max_uses, times_used, valid_from, valid_until, is_active FROM discount_codes ORDER BY id DESC LIMIT 20");
    $stmt->execute();
    $discounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $discounts = []; }
?>
<style>
.m-discounts { padding: 16px 16px 100px; font-family: Inter, sans-serif; }
.m-discounts-header { margin-bottom: 16px; }
.m-discounts-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-discounts-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-disc-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-disc-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.m-disc-code { font-size: 14px; font-weight: 700; color: #8B5CF6; font-family: monospace; letter-spacing: 0.5px; }
.m-disc-badge { font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600; }
.m-disc-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-disc-inactive { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-disc-desc { font-size: 12px; color: #A8A8B8; margin-bottom: 6px; }
.m-disc-details { display: flex; gap: 12px; flex-wrap: wrap; }
.m-disc-detail { font-size: 11px; color: #6B6B7B; display: inline-flex; align-items: center; gap: 4px; }
.m-disc-value { font-size: 13px; font-weight: 700; color: #10B981; }
.m-disc-actions { display: flex; gap: 8px; margin-top: 10px; justify-content: flex-end; }
.m-disc-btn { width: 36px; height: 36px; border-radius: 8px; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 13px; }
.m-disc-btn-edit { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-disc-btn-del { background: rgba(239,68,68,0.15); color: #EF4444; }
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
.m-form-input, .m-form-select { width: 100%; padding: 10px 12px; background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff; font-size: 14px; min-height: 44px; box-sizing: border-box; font-family: inherit; }
.m-form-input:focus, .m-form-select:focus { outline: none; border-color: #6B46C1; }
.m-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.m-btn-submit { width: 100%; padding: 14px; background: #6B46C1; color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; min-height: 44px; margin-top: 4px; }
.m-toast { position: fixed; top: 20px; left: 16px; right: 16px; padding: 14px 16px; border-radius: 10px; color: #fff; font-size: 13px; font-weight: 600; z-index: 2000; display: flex; align-items: center; gap: 8px; }
</style>

<div class="m-discounts">
    <div class="m-discounts-header">
        <h2 class="m-discounts-title">Discounts</h2>
        <p class="m-discounts-sub"><?= count($discounts) ?> discount<?= count($discounts) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($discounts)): ?>
        <div class="m-empty-state">
            <i class="fas fa-percent"></i>
            <p>No discounts found</p>
        </div>
    <?php else: ?>
        <?php foreach ($discounts as $d):
            $active = (int)($d['is_active'] ?? 0);
            $type = $d['discount_type'] ?? 'fixed';
            $val = (float)($d['discount_value'] ?? 0);
            $display = $type === 'percentage' ? $val . '%' : '$' . number_format($val, 2);
        ?>
        <div class="m-disc-card" data-id="<?= (int)$d['id'] ?>" data-code="<?= htmlspecialchars($d['code'] ?? '', ENT_QUOTES) ?>" data-type="<?= $type === 'percentage' ? 'percent' : 'fixed' ?>" data-value="<?= $val ?>" data-maxuses="<?= (int)($d['max_uses'] ?? 0) ?>" data-until="<?= htmlspecialchars($d['valid_until'] ?? '', ENT_QUOTES) ?>" data-active="<?= $active ?>">
            <div class="m-disc-top">
                <div class="m-disc-code"><?= htmlspecialchars($d['code'] ?? '') ?></div>
                <span class="m-disc-badge <?= $active ? 'm-disc-active' : 'm-disc-inactive' ?>"><?= $active ? 'Active' : 'Inactive' ?></span>
            </div>
            <div class="m-disc-details">
                <span class="m-disc-value"><?= $display ?> off</span>
                <?php if (!empty($d['max_uses'])): ?>
                <span class="m-disc-detail"><i class="fas fa-hashtag"></i> <?= (int)($d['times_used'] ?? 0) ?>/<?= (int)$d['max_uses'] ?> used</span>
                <?php endif; ?>
                <?php if (!empty($d['valid_until'])): ?>
                <span class="m-disc-detail"><i class="fas fa-calendar"></i> Expires <?= htmlspecialchars(date('M j, Y', strtotime($d['valid_until']))) ?></span>
                <?php endif; ?>
            </div>
            <div class="m-disc-actions">
                <button class="m-disc-btn m-disc-btn-edit" onclick="mEditDisc(this.closest('.m-disc-card'))"><i class="fas fa-pencil-alt"></i></button>
                <button class="m-disc-btn m-disc-btn-del" onclick="mDeleteDisc(this.closest('.m-disc-card'))"><i class="fas fa-trash"></i></button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button class="m-fab" onclick="mOpenDiscSheet('create')"><i class="fas fa-plus"></i></button>

<div id="mDiscSheet" class="m-sheet-overlay">
    <div class="m-sheet">
        <div class="m-sheet-handle"></div>
        <h3 class="m-sheet-title" id="mDiscSheetTitle">Create Discount Code</h3>
        <form method="POST" action="process_admin_action.php" id="mDiscForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" id="mDiscAction" value="create_discount">
            <input type="hidden" name="discount_id" id="mDiscId" value="">
            <div class="m-form-group">
                <label class="m-form-label">Code *</label>
                <input type="text" name="code" id="mDiscCode" class="m-form-input" required placeholder="e.g., SPRING2024" style="text-transform:uppercase;">
            </div>
            <div class="m-form-row">
                <div class="m-form-group">
                    <label class="m-form-label">Type *</label>
                    <select name="type" id="mDiscType" class="m-form-select" required>
                        <option value="percent">Percentage</option>
                        <option value="fixed">Fixed Amount</option>
                    </select>
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Value *</label>
                    <input type="number" name="value" id="mDiscValue" class="m-form-input" step="0.01" min="0" required>
                </div>
            </div>
            <div class="m-form-row">
                <div class="m-form-group">
                    <label class="m-form-label">Usage Limit</label>
                    <input type="number" name="usage_limit" id="mDiscLimit" class="m-form-input" min="1" placeholder="Unlimited">
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Expiry Date</label>
                    <input type="date" name="end_date" id="mDiscExpiry" class="m-form-input">
                </div>
            </div>
            <div class="m-form-group">
                <label class="m-form-label">Status</label>
                <select name="is_active" id="mDiscActive" class="m-form-select">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <button type="submit" class="m-btn-submit"><i class="fas fa-save"></i> Save Discount</button>
        </form>
    </div>
</div>

<script>
(function(){
    var csrfToken = document.querySelector('#mDiscForm [name="csrf_token"]') ? document.querySelector('#mDiscForm [name="csrf_token"]').value : '';

    window.mOpenDiscSheet = function(mode, card) {
        var sheet = document.getElementById('mDiscSheet');
        document.getElementById('mDiscSheetTitle').textContent = mode === 'edit' ? 'Edit Discount Code' : 'Create Discount Code';
        document.getElementById('mDiscAction').value = mode === 'edit' ? 'edit_discount' : 'create_discount';
        if (mode === 'edit' && card) {
            document.getElementById('mDiscId').value = card.dataset.id;
            document.getElementById('mDiscCode').value = card.dataset.code;
            document.getElementById('mDiscType').value = card.dataset.type;
            document.getElementById('mDiscValue').value = card.dataset.value;
            document.getElementById('mDiscLimit').value = card.dataset.maxuses > 0 ? card.dataset.maxuses : '';
            document.getElementById('mDiscExpiry').value = card.dataset.until || '';
            document.getElementById('mDiscActive').value = card.dataset.active;
        } else {
            document.getElementById('mDiscId').value = '';
            document.getElementById('mDiscCode').value = '';
            document.getElementById('mDiscType').value = 'percent';
            document.getElementById('mDiscValue').value = '';
            document.getElementById('mDiscLimit').value = '';
            document.getElementById('mDiscExpiry').value = '';
            document.getElementById('mDiscActive').value = '1';
        }
        sheet.classList.add('m-active');
    };

    window.mEditDisc = function(card) { mOpenDiscSheet('edit', card); };

    window.mDeleteDisc = function(card) {
        if (!confirm('Delete this discount code?')) return;
        fetch('process_admin_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: 'action=delete_discount&discount_id=' + encodeURIComponent(card.dataset.id) + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) { persistToast(d.message || 'Deleted!', 'success'); location.reload(); }
            else { mToast(d.message || 'Error', 'error'); }
        })
        .catch(function() { mToast('An error occurred', 'error'); });
    };

    document.getElementById('mDiscSheet').addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('m-active');
    });

    document.getElementById('mDiscForm').addEventListener('submit', function(e) {
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
            if (d.success) { persistToast(d.message || 'Saved!', 'success'); document.getElementById('mDiscSheet').classList.remove('m-active'); location.reload(); }
            else { mToast(d.message || 'Error', 'error'); }
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
