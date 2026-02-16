<?php
/**
 * PWA Admin Plan Categories - Mobile-native plan categories management
 * Purpose-built for mobile phones, not a desktop adaptation.
 * Supports create & delete for workout, nutrition, and practice categories.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$workout_categories = $nutrition_categories = $practice_categories = [];
try {
    $workout_categories = $pdo->query("SELECT id, name, description, display_order FROM workout_plan_categories ORDER BY display_order, name")->fetchAll(PDO::FETCH_ASSOC);
    $nutrition_categories = $pdo->query("SELECT id, name, description, display_order FROM nutrition_plan_categories ORDER BY display_order, name")->fetchAll(PDO::FETCH_ASSOC);
    $practice_categories = $pdo->query("SELECT id, name, description, display_order FROM practice_plan_categories ORDER BY display_order, name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* tables may not exist yet */ }

$totalCount = count($workout_categories) + count($nutrition_categories) + count($practice_categories);
?>
<style>
.m-plancat { padding: 16px 16px 80px; font-family: Inter, sans-serif; }
.m-plancat-header { margin-bottom: 16px; }
.m-plancat-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-plancat-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-plancat-section { margin-bottom: 20px; }
.m-plancat-section-title { font-size: 14px; font-weight: 700; color: #A8A8B8; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 10px; padding-bottom: 8px; border-bottom: 1px solid #2D2D3F; }
.m-plancat-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-plancat-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(107,70,193,0.15); color: #8B5CF6; font-size: 14px; flex-shrink: 0;
}
.m-plancat-body { flex: 1; min-width: 0; }
.m-plancat-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-plancat-desc { font-size: 12px; color: #A8A8B8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-plancat-order {
    background: rgba(107,70,193,0.2); color: #8B5CF6; font-size: 11px; font-weight: 600;
    padding: 2px 8px; border-radius: 10px; flex-shrink: 0;
}
.m-plancat-del {
    width: 44px; height: 44px; border: none; border-radius: 10px;
    background: rgba(239,68,68,0.1); color: #EF4444; font-size: 14px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0; cursor: pointer;
}
.m-plancat-del:active { background: rgba(239,68,68,0.25); }
.m-plancat-empty { text-align: center; padding: 24px 16px; color: #6B6B7B; font-size: 13px; }
.m-plancat-fab {
    position: fixed; bottom: 60px; right: 20px;
    width: 56px; height: 56px; border-radius: 50%;
    background: #6B46C1; color: #fff; border: none;
    font-size: 22px; display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 12px rgba(107,70,193,0.4); z-index: 1000; cursor: pointer;
}
.m-plancat-fab:active { background: #5a3aad; }
/* Overlay */
.m-plancat-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    z-index: 1001; display: none; align-items: flex-end; justify-content: center;
}
.m-plancat-overlay.m-active { display: flex; }
/* Action sheet */
.m-plancat-actionsheet {
    background: #16161F; border-radius: 16px 16px 0 0;
    width: 100%; max-width: 500px; padding: 16px 16px 24px;
    transform: translateY(100%); transition: transform 0.3s ease;
}
.m-plancat-overlay.m-active .m-plancat-actionsheet { transform: translateY(0); }
.m-plancat-as-title { font-size: 15px; font-weight: 700; color: #fff; text-align: center; margin: 0 0 16px; }
.m-plancat-as-btn {
    display: flex; align-items: center; gap: 12px;
    width: 100%; padding: 14px; background: #0A0A0F; border: 1px solid #2D2D3F;
    border-radius: 10px; margin-bottom: 8px; color: #fff; font-size: 14px;
    font-weight: 500; cursor: pointer; min-height: 44px;
}
.m-plancat-as-btn:active { background: #1a1a2e; }
.m-plancat-as-btn i { color: #8B5CF6; width: 20px; text-align: center; }
.m-plancat-as-cancel {
    display: block; width: 100%; padding: 14px; background: none; border: 1px solid #2D2D3F;
    border-radius: 10px; color: #A8A8B8; font-size: 14px; text-align: center;
    cursor: pointer; margin-top: 4px; min-height: 44px;
}
/* Bottom sheet modal */
.m-plancat-sheet {
    background: #16161F; border-radius: 16px 16px 0 0;
    width: 100%; max-width: 500px; max-height: 85vh; overflow-y: auto;
    padding: 20px 16px 32px;
    transform: translateY(100%); transition: transform 0.3s ease;
}
.m-plancat-overlay.m-active .m-plancat-sheet { transform: translateY(0); }
.m-plancat-sheet-handle {
    width: 36px; height: 4px; background: #3D3D4F; border-radius: 2px;
    margin: 0 auto 16px;
}
.m-plancat-sheet-title { font-size: 16px; font-weight: 700; color: #fff; margin: 0 0 20px; text-align: center; }
.m-plancat-field { margin-bottom: 16px; }
.m-plancat-field label { display: block; font-size: 12px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.3px; }
.m-plancat-field input,
.m-plancat-field textarea {
    width: 100%; padding: 12px; background: #0A0A0F; border: 1px solid #2D2D3F;
    border-radius: 10px; color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box;
}
.m-plancat-field input:focus,
.m-plancat-field textarea:focus { border-color: #6B46C1; outline: none; }
.m-plancat-field textarea { resize: vertical; min-height: 80px; }
.m-plancat-submit {
    width: 100%; padding: 14px; background: #6B46C1; color: #fff; border: none;
    border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer;
    min-height: 44px; margin-top: 8px;
}
.m-plancat-submit:active { background: #5a3aad; }
.m-plancat-submit:disabled { opacity: 0.6; }
/* Toast */
.m-plancat-toast {
    position: fixed; top: 20px; left: 16px; right: 16px;
    padding: 14px 16px; border-radius: 10px; color: #fff;
    font-size: 13px; font-weight: 500; z-index: 2000;
    display: flex; align-items: center; gap: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    animation: m-plancat-slidein 0.3s ease;
}
@keyframes m-plancat-slidein { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>

<div class="m-plancat">
    <div class="m-plancat-header">
        <h2 class="m-plancat-title">Plan Categories</h2>
        <p class="m-plancat-sub"><?= $totalCount ?> categor<?= $totalCount !== 1 ? 'ies' : 'y' ?> across 3 types</p>
    </div>

    <?php
    $sections = [
        ['key' => 'workout', 'title' => 'Workout Categories', 'icon' => 'fa-dumbbell', 'data' => $workout_categories],
        ['key' => 'nutrition', 'title' => 'Nutrition Categories', 'icon' => 'fa-utensils', 'data' => $nutrition_categories],
        ['key' => 'practice', 'title' => 'Practice Categories', 'icon' => 'fa-clipboard-list', 'data' => $practice_categories],
    ];
    foreach ($sections as $section):
    ?>
    <div class="m-plancat-section">
        <h3 class="m-plancat-section-title"><i class="fas <?= $section['icon'] ?>"></i> <?= $section['title'] ?></h3>
        <?php if (empty($section['data'])): ?>
            <div class="m-plancat-empty">No <?= strtolower($section['title']) ?> yet</div>
        <?php else: ?>
            <?php foreach ($section['data'] as $c): ?>
            <div class="m-plancat-card" data-type="<?= $section['key'] ?>" data-id="<?= (int)$c['id'] ?>" data-name="<?= htmlspecialchars($c['name'] ?? '', ENT_QUOTES) ?>">
                <div class="m-plancat-icon"><i class="fas <?= $section['icon'] ?>"></i></div>
                <div class="m-plancat-body">
                    <div class="m-plancat-name"><?= htmlspecialchars($c['name'] ?? '') ?></div>
                    <?php if (!empty($c['description'])): ?>
                    <div class="m-plancat-desc"><?= htmlspecialchars(mb_strimwidth($c['description'], 0, 80, '...')) ?></div>
                    <?php endif; ?>
                </div>
                <span class="m-plancat-order"><?= (int)($c['display_order'] ?? 0) ?></span>
                <button class="m-plancat-del" onclick="mPlanCatDelete(this.closest('.m-plancat-card'))" aria-label="Delete category"><i class="fas fa-trash"></i></button>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- FAB -->
<button class="m-plancat-fab" onclick="mPlanCatShowActions()" aria-label="Add category"><i class="fas fa-plus"></i></button>

<!-- Action sheet overlay (pick type) -->
<div class="m-plancat-overlay" id="mPlanCatActionOverlay" onclick="if(event.target===this)mPlanCatHideActions()">
    <div class="m-plancat-actionsheet">
        <h3 class="m-plancat-as-title">Add Category</h3>
        <button class="m-plancat-as-btn" onclick="mPlanCatOpenCreate('workout')"><i class="fas fa-dumbbell"></i> Workout Category</button>
        <button class="m-plancat-as-btn" onclick="mPlanCatOpenCreate('nutrition')"><i class="fas fa-utensils"></i> Nutrition Category</button>
        <button class="m-plancat-as-btn" onclick="mPlanCatOpenCreate('practice')"><i class="fas fa-clipboard-list"></i> Practice Category</button>
        <button class="m-plancat-as-cancel" onclick="mPlanCatHideActions()">Cancel</button>
    </div>
</div>

<!-- Create sheet overlay -->
<div class="m-plancat-overlay" id="mPlanCatCreateOverlay" onclick="if(event.target===this)mPlanCatHideCreate()">
    <div class="m-plancat-sheet">
        <div class="m-plancat-sheet-handle"></div>
        <h3 class="m-plancat-sheet-title" id="mPlanCatSheetTitle">Add Category</h3>
        <form id="mPlanCatForm" method="POST" action="process_plan_categories.php">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="category_type" id="mPlanCatType" value="">
            <div class="m-plancat-field">
                <label for="mPlanCatName">Category Name *</label>
                <input type="text" id="mPlanCatName" name="name" required maxlength="100" placeholder="e.g. Strength Training">
            </div>
            <div class="m-plancat-field">
                <label for="mPlanCatDesc">Description</label>
                <textarea id="mPlanCatDesc" name="description" placeholder="Optional description"></textarea>
            </div>
            <div class="m-plancat-field">
                <label for="mPlanCatOrder">Display Order</label>
                <input type="number" id="mPlanCatOrder" name="display_order" value="0" min="0">
            </div>
            <button type="submit" class="m-plancat-submit" id="mPlanCatSubmitBtn"><i class="fas fa-plus"></i> Create Category</button>
        </form>
    </div>
</div>

<script>
(function() {
    var csrfToken = document.querySelector('#mPlanCatForm [name="csrf_token"]').value;
    var actionOverlay = document.getElementById('mPlanCatActionOverlay');
    var createOverlay = document.getElementById('mPlanCatCreateOverlay');
    var typeLabels = { workout: 'Workout', nutrition: 'Nutrition', practice: 'Practice' };

    function toast(msg, type) {
        var old = document.querySelector('.m-plancat-toast');
        if (old) old.remove();
        var d = document.createElement('div');
        d.className = 'm-plancat-toast';
        d.style.background = type === 'success' ? 'rgba(16,185,129,0.95)' : 'rgba(239,68,68,0.95)';
        var icon = document.createElement('i');
        icon.className = 'fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle');
        d.appendChild(icon);
        var span = document.createElement('span');
        span.textContent = msg;
        d.appendChild(span);
        document.body.appendChild(d);
        setTimeout(function() { if (d.parentElement) d.remove(); }, 4000);
    }

    window.mPlanCatShowActions = function() {
        actionOverlay.classList.add('m-active');
    };

    window.mPlanCatHideActions = function() {
        actionOverlay.classList.remove('m-active');
    };

    window.mPlanCatOpenCreate = function(type) {
        mPlanCatHideActions();
        document.getElementById('mPlanCatType').value = type;
        document.getElementById('mPlanCatSheetTitle').textContent = 'Add ' + typeLabels[type] + ' Category';
        document.getElementById('mPlanCatForm').reset();
        document.getElementById('mPlanCatOrder').value = '0';
        document.getElementById('mPlanCatType').value = type;
        createOverlay.classList.add('m-active');
    };

    window.mPlanCatHideCreate = function() {
        createOverlay.classList.remove('m-active');
    };

    window.mPlanCatDelete = function(card) {
        var catName = card.dataset.name;
        var catId = card.dataset.id;
        var catType = card.dataset.type;
        if (!confirm('Delete "' + catName + '"?')) return;

        fetch('process_plan_categories.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: 'action=delete&category_type=' + encodeURIComponent(catType) + '&category_id=' + encodeURIComponent(catId) + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) { persistToast(d.message || 'Deleted!', 'success'); location.reload(); }
            else { toast(d.message || 'Error', 'error'); }
        })
        .catch(function() { toast('An error occurred', 'error'); });
    };

    // Form submit via AJAX
    document.getElementById('mPlanCatForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = this;
        var btn = document.getElementById('mPlanCatSubmitBtn');
        var origText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
        btn.disabled = true;

        fetch(form.getAttribute('action'), {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            btn.innerHTML = origText;
            btn.disabled = false;
            if (d.success) {
                persistToast(d.message || 'Category created!', 'success');
                mPlanCatHideCreate();
                location.reload();
            } else {
                toast(d.message || 'Failed to create', 'error');
            }
        })
        .catch(function() {
            btn.innerHTML = origText;
            btn.disabled = false;
            toast('An error occurred', 'error');
        });
    });
})();
</script>
