<?php
/**
 * PWA Practice Plans - Mobile-native practice plan list for coaches
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAnyCoach):
?>
<style>
.m-denied { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; color: #6B6B7B; font-family: Inter, sans-serif; text-align: center; }
.m-denied i { font-size: 48px; margin-bottom: 16px; }
.m-denied p { font-size: 15px; margin: 0; }
</style>
<div class="m-denied">
    <i class="fas fa-lock"></i>
    <p>Access denied</p>
</div>
<?php
    return;
endif;

$plans = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, title, description, total_duration, created_at
        FROM practice_plans
        WHERE created_by = ?
        ORDER BY created_at DESC
        LIMIT 30
    ");
    $stmt->execute([$user_id]);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $plans = []; }
?>
<style>
.m-practice { padding: 16px; font-family: Inter, sans-serif; }
.m-practice-header { margin-bottom: 12px; }
.m-practice-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-practice-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-toolbar { display: flex; gap: 8px; margin-bottom: 12px; }
.m-toolbar a {
    display: flex; align-items: center; gap: 5px; padding: 7px 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 8px;
    color: #A8A8B8; font-size: 12px; text-decoration: none; font-weight: 500;
}
.m-toolbar a:hover { border-color: #8B5CF6; color: #8B5CF6; }
.m-search-wrap { margin-bottom: 12px; position: relative; }
.m-search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #6B6B7B; font-size: 13px; }
.m-search {
    width: 100%; padding: 10px 12px 10px 34px; box-sizing: border-box;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif; outline: none;
}
.m-search::placeholder { color: #6B6B7B; }
.m-search:focus { border-color: #8B5CF6; }
.m-plan-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
    text-decoration: none; display: block; min-height: 44px;
}
.m-plan-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-plan-title { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-plan-duration {
    font-size: 11px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    background: rgba(107,70,193,0.15); color: #8B5CF6; white-space: nowrap; flex-shrink: 0;
}
.m-plan-desc {
    font-size: 12px; color: #A8A8B8; margin: 0 0 10px;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.m-plan-footer { display: flex; justify-content: space-between; align-items: center; }
.m-plan-meta { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-plan-actions { display: flex; gap: 6px; }
.m-plan-actions button {
    width: 32px; height: 32px; border-radius: 8px; border: 1px solid #2D2D3F;
    background: #0A0A0F; color: #A8A8B8; font-size: 13px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.m-plan-actions button:hover { border-color: #8B5CF6; color: #8B5CF6; }
.m-plan-actions button.m-btn-del:hover { border-color: #EF4444; color: #EF4444; }
.m-fab {
    position: fixed; bottom: 80px; right: 20px; z-index: 50;
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    color: #fff; font-size: 22px;
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; box-shadow: 0 4px 16px rgba(107,70,193,0.4);
    border: none; cursor: pointer;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
/* Edit modal */
.m-modal-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6);
    z-index: 100; align-items: flex-end; justify-content: center;
}
.m-modal-overlay.active { display: flex; }
.m-modal {
    background: #16161F; border-top: 1px solid #2D2D3F; border-radius: 16px 16px 0 0;
    width: 100%; max-width: 480px; padding: 20px 16px 24px; animation: m-slide-up .25s ease;
}
@keyframes m-slide-up { from { transform: translateY(100%); } to { transform: translateY(0); } }
.m-modal-handle { width: 36px; height: 4px; background: #2D2D3F; border-radius: 2px; margin: 0 auto 16px; }
.m-modal h3 { font-size: 16px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-modal label { font-size: 12px; font-weight: 600; color: #A8A8B8; display: block; margin-bottom: 4px; }
.m-modal input, .m-modal textarea {
    width: 100%; padding: 10px 12px; box-sizing: border-box;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 8px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif; outline: none; margin-bottom: 12px;
}
.m-modal textarea { resize: vertical; min-height: 70px; }
.m-modal input:focus, .m-modal textarea:focus { border-color: #8B5CF6; }
.m-modal-btns { display: flex; gap: 10px; margin-top: 4px; }
.m-modal-btns button {
    flex: 1; padding: 11px; border-radius: 10px; font-size: 14px; font-weight: 600;
    border: none; cursor: pointer; font-family: Inter, sans-serif;
}
.m-modal-btns .m-btn-cancel { background: #0A0A0F; color: #A8A8B8; border: 1px solid #2D2D3F; }
.m-modal-btns .m-btn-save { background: linear-gradient(135deg, #6B46C1, #8B5CF6); color: #fff; }
.m-modal-btns .m-btn-save:disabled { opacity: 0.5; cursor: not-allowed; }
.m-toast {
    position: fixed; bottom: 90px; left: 50%; transform: translateX(-50%);
    padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 500;
    color: #fff; z-index: 200; opacity: 0; transition: opacity .3s; pointer-events: none;
    font-family: Inter, sans-serif;
}
.m-toast.show { opacity: 1; }
.m-toast.success { background: #10B981; }
.m-toast.error { background: #EF4444; }
</style>

<div class="m-practice">
    <div class="m-practice-header">
        <h2 class="m-practice-title">Practice Plans</h2>
        <p class="m-practice-sub"><?= count($plans) ?> plan<?= count($plans) !== 1 ? 's' : '' ?></p>
    </div>

    <div class="m-toolbar">
        <a href="?page=practice_import"><i class="fas fa-file-import"></i> Import</a>
        <a href="?page=export_import_plans"><i class="fas fa-file-export"></i> Export</a>
    </div>

    <div class="m-search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" class="m-search" id="ppSearch" placeholder="Search plans…" autocomplete="off">
    </div>

    <?php if (empty($plans)): ?>
        <div class="m-empty-state" id="ppEmpty">
            <i class="fas fa-clipboard-list"></i>
            <p>No practice plans created yet</p>
        </div>
    <?php else: ?>
        <div id="ppList">
        <?php foreach ($plans as $p): ?>
        <div class="m-plan-card" data-plan-id="<?= (int)$p['id'] ?>" data-title="<?= htmlspecialchars($p['title'], ENT_QUOTES) ?>" data-desc="<?= htmlspecialchars($p['description'] ?? '', ENT_QUOTES) ?>" data-duration="<?= (int)($p['total_duration'] ?? 0) ?>">
            <a href="?page=view_practice_plan&id=<?= (int)$p['id'] ?>" style="text-decoration:none;display:block;">
                <div class="m-plan-top">
                    <span class="m-plan-title"><?= htmlspecialchars($p['title']) ?></span>
                    <?php if (!empty($p['total_duration'])): ?>
                    <span class="m-plan-duration"><i class="fas fa-clock"></i> <?= (int)$p['total_duration'] ?>min</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($p['description'])): ?>
                <p class="m-plan-desc"><?= htmlspecialchars($p['description']) ?></p>
                <?php endif; ?>
            </a>
            <div class="m-plan-footer">
                <span class="m-plan-meta"><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($p['created_at'])) ?></span>
                <div class="m-plan-actions">
                    <button type="button" class="m-btn-edit" onclick="openEditModal(this.closest('.m-plan-card'))" title="Edit"><i class="fas fa-pen"></i></button>
                    <button type="button" class="m-btn-del" onclick="deletePlan(<?= (int)$p['id'] ?>, this.closest('.m-plan-card'))" title="Delete"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <a href="?page=create_practice_plan" class="m-fab" title="Create Practice Plan"><i class="fas fa-plus"></i></a>
</div>

<!-- Edit Modal -->
<div class="m-modal-overlay" id="ppEditOverlay">
    <div class="m-modal">
        <div class="m-modal-handle"></div>
        <h3>Edit Practice Plan</h3>
        <form id="ppEditForm" onsubmit="return submitEdit(event)">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="update_plan">
            <input type="hidden" name="plan_id" id="ppEditId">
            <label for="ppEditTitle">Title</label>
            <input type="text" name="title" id="ppEditTitle" required>
            <label for="ppEditDesc">Description</label>
            <textarea name="description" id="ppEditDesc"></textarea>
            <label for="ppEditDur">Duration (minutes)</label>
            <input type="number" name="duration" id="ppEditDur" min="0" step="1">
            <div class="m-modal-btns">
                <button type="button" class="m-btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="m-btn-save" id="ppEditSave">Save</button>
            </div>
        </form>
    </div>
</div>
<div class="m-toast" id="ppToast"></div>

<script>
(function(){
    // Search filter
    const search = document.getElementById('ppSearch');
    if (search) {
        search.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#ppList .m-plan-card').forEach(function(card) {
                const title = (card.getAttribute('data-title') || '').toLowerCase();
                card.style.display = title.includes(q) ? '' : 'none';
            });
        });
    }

    // Toast helper
    window.ppToast = function(msg, type) {
        var t = document.getElementById('ppToast');
        t.textContent = msg;
        t.className = 'm-toast show ' + (type || 'success');
        setTimeout(function(){ t.classList.remove('show'); }, 2500);
    };

    // Edit modal
    window.openEditModal = function(card) {
        document.getElementById('ppEditId').value = card.getAttribute('data-plan-id');
        document.getElementById('ppEditTitle').value = card.getAttribute('data-title');
        document.getElementById('ppEditDesc').value = card.getAttribute('data-desc');
        document.getElementById('ppEditDur').value = card.getAttribute('data-duration') || '';
        document.getElementById('ppEditOverlay').classList.add('active');
    };

    window.closeEditModal = function() {
        document.getElementById('ppEditOverlay').classList.remove('active');
    };

    document.getElementById('ppEditOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });

    window.submitEdit = function(e) {
        e.preventDefault();
        var form = document.getElementById('ppEditForm');
        var btn = document.getElementById('ppEditSave');
        btn.disabled = true;
        fetch('process_practice_plans.php', {
            method: 'POST',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            body: new FormData(form)
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
            btn.disabled = false;
            if (data.success) {
                var id = document.getElementById('ppEditId').value;
                var card = document.querySelector('.m-plan-card[data-plan-id="'+id+'"]');
                if (card) {
                    var newTitle = document.getElementById('ppEditTitle').value;
                    var newDesc = document.getElementById('ppEditDesc').value;
                    card.setAttribute('data-title', newTitle);
                    card.setAttribute('data-desc', newDesc);
                    var titleEl = card.querySelector('.m-plan-title');
                    if (titleEl) titleEl.textContent = newTitle;
                    var descEl = card.querySelector('.m-plan-desc');
                    if (descEl) { descEl.textContent = newDesc; }
                    else if (newDesc) {
                        var p = document.createElement('p');
                        p.className = 'm-plan-desc';
                        p.textContent = newDesc;
                        card.querySelector('a').appendChild(p);
                    }
                }
                closeEditModal();
                ppToast('Plan updated', 'success');
            } else {
                ppToast(data.message || 'Update failed', 'error');
            }
        })
        .catch(function(){
            btn.disabled = false;
            ppToast('Network error', 'error');
        });
        return false;
    };

    // Delete
    window.deletePlan = function(id, card) {
        if (!confirm('Delete this practice plan?')) return;
        var fd = new FormData();
        fd.append('action', 'delete_plan');
        fd.append('plan_id', id);
        var tokenInput = document.querySelector('#ppEditForm input[name="csrf_token"]');
        if (tokenInput) fd.append('csrf_token', tokenInput.value);
        fetch('process_practice_plans.php', {
            method: 'POST',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            body: fd
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data.success) {
                card.remove();
                ppToast('Plan deleted', 'success');
            } else {
                ppToast(data.message || 'Delete failed', 'error');
            }
        })
        .catch(function(){ ppToast('Network error', 'error'); });
    };
})();
</script>
