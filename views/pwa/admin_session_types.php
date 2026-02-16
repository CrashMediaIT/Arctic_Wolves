<?php
/**
 * PWA Admin Session Types - Mobile-native session types list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$types = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description, default_price, duration_minutes FROM session_types ORDER BY name");
    $stmt->execute();
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $types = []; }
?>
<style>
.m-sesstypes { padding: 16px 16px 100px; font-family: Inter, sans-serif; }
.m-sesstypes-header { margin-bottom: 16px; }
.m-sesstypes-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-sesstypes-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-sesstype-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-sesstype-top { display: flex; justify-content: space-between; align-items: center; }
.m-sesstype-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-sesstype-price { font-size: 14px; font-weight: 700; color: #10B981; }
.m-sesstype-desc { font-size: 12px; color: #A8A8B8; margin-top: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-sesstype-dur { font-size: 11px; color: #6B6B7B; margin-top: 6px; display: inline-flex; align-items: center; gap: 4px; }
.m-sesstype-actions { display: flex; gap: 8px; margin-top: 10px; justify-content: flex-end; }
.m-sesstype-btn { width: 36px; height: 36px; border-radius: 8px; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 13px; }
.m-sesstype-btn-edit { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-sesstype-btn-del { background: rgba(239,68,68,0.15); color: #EF4444; }
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
.m-form-input { width: 100%; padding: 10px 12px; background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff; font-size: 14px; min-height: 44px; box-sizing: border-box; font-family: inherit; }
.m-form-input:focus { outline: none; border-color: #6B46C1; }
.m-form-textarea { width: 100%; padding: 10px 12px; background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff; font-size: 14px; min-height: 80px; resize: vertical; box-sizing: border-box; font-family: inherit; }
.m-form-textarea:focus { outline: none; border-color: #6B46C1; }
.m-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.m-btn-submit { width: 100%; padding: 14px; background: #6B46C1; color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; min-height: 44px; margin-top: 4px; }
.m-toast { position: fixed; top: 20px; left: 16px; right: 16px; padding: 14px 16px; border-radius: 10px; color: #fff; font-size: 13px; font-weight: 600; z-index: 2000; display: flex; align-items: center; gap: 8px; }
</style>

<div class="m-sesstypes">
    <div class="m-sesstypes-header">
        <h2 class="m-sesstypes-title">Session Types</h2>
        <p class="m-sesstypes-sub"><?= count($types) ?> type<?= count($types) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($types)): ?>
        <div class="m-empty-state">
            <i class="fas fa-calendar-alt"></i>
            <p>No session types defined</p>
        </div>
    <?php else: ?>
        <?php foreach ($types as $t): ?>
        <div class="m-sesstype-card" data-id="<?= (int)$t['id'] ?>" data-name="<?= htmlspecialchars($t['name'] ?? '', ENT_QUOTES) ?>" data-description="<?= htmlspecialchars($t['description'] ?? '', ENT_QUOTES) ?>" data-price="<?= (float)($t['default_price'] ?? 0) ?>" data-duration="<?= (int)($t['duration_minutes'] ?? 60) ?>">
            <div class="m-sesstype-top">
                <div class="m-sesstype-name"><?= htmlspecialchars($t['name'] ?? '') ?></div>
                <?php if (isset($t['default_price'])): ?>
                <div class="m-sesstype-price">$<?= number_format((float)$t['default_price'], 2) ?></div>
                <?php endif; ?>
            </div>
            <?php if (!empty($t['description'])): ?>
            <div class="m-sesstype-desc"><?= htmlspecialchars($t['description']) ?></div>
            <?php endif; ?>
            <?php if (!empty($t['duration_minutes'])): ?>
            <div class="m-sesstype-dur"><i class="fas fa-clock"></i> <?= (int)$t['duration_minutes'] ?> min</div>
            <?php endif; ?>
            <div class="m-sesstype-actions">
                <button class="m-sesstype-btn m-sesstype-btn-edit" onclick="mEditType(this.closest('.m-sesstype-card'))"><i class="fas fa-pencil-alt"></i></button>
                <button class="m-sesstype-btn m-sesstype-btn-del" onclick="mDeleteType(this.closest('.m-sesstype-card'))"><i class="fas fa-trash"></i></button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button class="m-fab" onclick="mOpenSheet('create')"><i class="fas fa-plus"></i></button>

<div id="mTypeSheet" class="m-sheet-overlay">
    <div class="m-sheet">
        <div class="m-sheet-handle"></div>
        <h3 class="m-sheet-title" id="mTypeSheetTitle">Add Session Type</h3>
        <form method="POST" action="process_admin_action.php" id="mTypeForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" id="mTypeAction" value="create_session_type">
            <input type="hidden" name="type_id" id="mTypeId" value="">
            <div class="m-form-group">
                <label class="m-form-label">Name *</label>
                <input type="text" name="name" id="mTypeName" class="m-form-input" required placeholder="e.g., Skills Development">
            </div>
            <div class="m-form-group">
                <label class="m-form-label">Description</label>
                <textarea name="description" id="mTypeDesc" class="m-form-textarea" placeholder="Optional description"></textarea>
            </div>
            <div class="m-form-row">
                <div class="m-form-group">
                    <label class="m-form-label">Price ($)</label>
                    <input type="number" name="price" id="mTypePrice" class="m-form-input" step="0.01" min="0" value="0" placeholder="0.00">
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Duration (min)</label>
                    <input type="number" name="duration" id="mTypeDuration" class="m-form-input" min="15" max="480" value="60">
                </div>
            </div>
            <button type="submit" class="m-btn-submit"><i class="fas fa-save"></i> Save Session Type</button>
        </form>
    </div>
</div>

<script>
(function(){
    var csrfToken = document.querySelector('#mTypeForm [name="csrf_token"]') ? document.querySelector('#mTypeForm [name="csrf_token"]').value : '';

    window.mOpenSheet = function(mode, card) {
        var sheet = document.getElementById('mTypeSheet');
        document.getElementById('mTypeSheetTitle').textContent = mode === 'edit' ? 'Edit Session Type' : 'Add Session Type';
        document.getElementById('mTypeAction').value = mode === 'edit' ? 'edit_session_type' : 'create_session_type';
        if (mode === 'edit' && card) {
            document.getElementById('mTypeId').value = card.dataset.id;
            document.getElementById('mTypeName').value = card.dataset.name;
            document.getElementById('mTypeDesc').value = card.dataset.description;
            document.getElementById('mTypePrice').value = card.dataset.price;
            document.getElementById('mTypeDuration').value = card.dataset.duration;
        } else {
            document.getElementById('mTypeId').value = '';
            document.getElementById('mTypeName').value = '';
            document.getElementById('mTypeDesc').value = '';
            document.getElementById('mTypePrice').value = '0';
            document.getElementById('mTypeDuration').value = '60';
        }
        sheet.classList.add('m-active');
    };

    window.mEditType = function(card) { mOpenSheet('edit', card); };

    window.mDeleteType = function(card) {
        if (!confirm('Delete this session type?')) return;
        fetch('process_admin_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: 'action=delete_session_type&type_id=' + encodeURIComponent(card.dataset.id) + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            mToast(d.message || (d.success ? 'Deleted!' : 'Error'), d.success ? 'success' : 'error');
            if (d.success) location.reload();
        })
        .catch(function() { mToast('An error occurred', 'error'); });
    };

    document.getElementById('mTypeSheet').addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('m-active');
    });

    document.getElementById('mTypeForm').addEventListener('submit', function(e) {
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
            if (d.success) { document.getElementById('mTypeSheet').classList.remove('m-active'); location.reload(); }
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
