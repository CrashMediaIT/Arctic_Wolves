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
        SELECT id, COALESCE(title, name) as title, description, COALESCE(total_duration, duration_minutes) as total_duration, focus_area, created_at
        FROM practice_plans
        WHERE created_by = ?
        ORDER BY created_at DESC
        LIMIT 30
    ");
    $stmt->execute([$user_id]);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $plans = []; }

$drills_list = [];
try {
    $stmt = $pdo->prepare("
        SELECT d.id, d.title, dc.name as category
        FROM drills d
        LEFT JOIN drill_categories dc ON d.category_id = dc.id
        WHERE d.created_by = ?
        ORDER BY dc.name, d.title
        LIMIT 200
    ");
    $stmt->execute([$user_id]);
    $drills_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $drills_list = []; }
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
    max-height: 90vh; overflow-y: auto; -webkit-overflow-scrolling: touch;
    padding-bottom: env(safe-area-inset-bottom, 24px);
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
/* Create modal extras */
.m-modal select {
    width: 100%; padding: 10px 12px; box-sizing: border-box;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 8px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif; outline: none; margin-bottom: 12px;
    -webkit-appearance: none; appearance: none;
}
.m-modal select:focus { border-color: #8B5CF6; }
.m-drill-search { width: 100%; padding: 8px 10px; box-sizing: border-box; background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 8px 8px 0 0; color: #fff; font-size: 13px; font-family: Inter, sans-serif; outline: none; border-bottom: none; }
.m-drill-search:focus { border-color: #8B5CF6; }
.m-drill-picker { max-height: 160px; overflow-y: auto; border: 1px solid #2D2D3F; border-radius: 0 0 8px 8px; background: #0A0A0F; margin-bottom: 12px; }
.m-drill-item { display: flex; align-items: center; padding: 8px 12px; border-bottom: 1px solid #1a1a2e; cursor: pointer; gap: 8px; }
.m-drill-item:last-child { border-bottom: none; }
.m-drill-item:active { background: #1a1a2e; }
.m-drill-item.selected { background: rgba(107,70,193,0.15); }
.m-drill-item .m-drill-check { width: 18px; height: 18px; border: 2px solid #2D2D3F; border-radius: 4px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 10px; color: transparent; }
.m-drill-item.selected .m-drill-check { border-color: #8B5CF6; background: #8B5CF6; color: #fff; }
.m-drill-item .m-drill-name { font-size: 13px; color: #fff; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.m-drill-item .m-drill-cat { font-size: 10px; color: #6B6B7B; white-space: nowrap; }
.m-selected-drills { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; min-height: 0; }
.m-selected-drill-tag { display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; background: rgba(107,70,193,0.2); border: 1px solid rgba(139,92,246,0.4); border-radius: 6px; font-size: 11px; color: #C4B5FD; }
.m-selected-drill-tag button { background: none; border: none; color: #C4B5FD; font-size: 13px; cursor: pointer; padding: 0; line-height: 1; }
/* Delete confirmation */
.m-confirm-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 150; align-items: center; justify-content: center; }
.m-confirm-overlay.active { display: flex; }
.m-confirm-box { background: #16161F; border: 1px solid #2D2D3F; border-radius: 16px; padding: 24px 20px; width: 90%; max-width: 340px; text-align: center; }
.m-confirm-box i { font-size: 32px; color: #EF4444; margin-bottom: 12px; display: block; }
.m-confirm-box h4 { font-size: 16px; font-weight: 700; color: #fff; margin: 0 0 8px; }
.m-confirm-box p { font-size: 13px; color: #A8A8B8; margin: 0 0 20px; }
.m-confirm-btns { display: flex; gap: 10px; }
.m-confirm-btns button { flex: 1; padding: 11px; border-radius: 10px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; font-family: Inter, sans-serif; }
.m-confirm-btns .m-btn-cancel { background: #0A0A0F; color: #A8A8B8; border: 1px solid #2D2D3F; }
.m-confirm-btns .m-btn-danger { background: #EF4444; color: #fff; }
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
        <div class="m-plan-card" data-plan-id="<?= (int)$p['id'] ?>" data-title="<?= htmlspecialchars($p['title'], ENT_QUOTES) ?>" data-desc="<?= htmlspecialchars($p['description'] ?? '', ENT_QUOTES) ?>" data-duration="<?= (int)($p['total_duration'] ?? 0) ?>" data-focus="<?= htmlspecialchars($p['focus_area'] ?? '', ENT_QUOTES) ?>">
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

    <button type="button" class="m-fab" onclick="openCreateModal()" title="Create Practice Plan"><i class="fas fa-plus"></i></button>
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
            <label for="ppEditFocus">Focus Area</label>
            <select name="focus_area" id="ppEditFocus">
                <option value="">— None —</option>
                <option value="skating">Skating</option>
                <option value="shooting">Shooting</option>
                <option value="passing">Passing</option>
                <option value="defense">Defense</option>
                <option value="goaltending">Goaltending</option>
                <option value="conditioning">Conditioning</option>
            </select>
            <div class="m-modal-btns">
                <button type="button" class="m-btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="m-btn-save" id="ppEditSave">Save</button>
            </div>
        </form>
    </div>
</div>
<div class="m-toast" id="ppToast"></div>

<!-- Create Modal -->
<div class="m-modal-overlay" id="ppCreateOverlay">
    <div class="m-modal">
        <div class="m-modal-handle"></div>
        <h3>Create Practice Plan</h3>
        <form id="ppCreateForm" onsubmit="return submitCreate(event)">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="drills" id="ppCreateDrills" value="[]">
            <label for="ppCreateTitle">Title <span style="color:#EF4444">*</span></label>
            <input type="text" name="title" id="ppCreateTitle" required placeholder="e.g. Power skating drill day">
            <label for="ppCreateDesc">Description</label>
            <textarea name="description" id="ppCreateDesc" placeholder="Practice goals and notes…"></textarea>
            <label for="ppCreateDur">Duration (minutes)</label>
            <input type="number" name="duration" id="ppCreateDur" min="1" step="1" placeholder="60">
            <label for="ppCreateFocus">Focus Area</label>
            <select name="focus_area" id="ppCreateFocus">
                <option value="">— Select —</option>
                <option value="skating">Skating</option>
                <option value="shooting">Shooting</option>
                <option value="passing">Passing</option>
                <option value="defense">Defense</option>
                <option value="goaltending">Goaltending</option>
                <option value="conditioning">Conditioning</option>
            </select>
            <label>Drills</label>
            <div class="m-selected-drills" id="ppSelectedDrills"></div>
            <input type="text" class="m-drill-search" id="ppDrillSearch" placeholder="Search drills…" autocomplete="off">
            <div class="m-drill-picker" id="ppDrillPicker">
                <?php if (empty($drills_list)): ?>
                <div style="padding:12px;text-align:center;color:#6B6B7B;font-size:12px;">No drills available</div>
                <?php else: ?>
                <?php foreach ($drills_list as $d): ?>
                <div class="m-drill-item" data-drill-id="<?= (int)$d['id'] ?>" data-drill-title="<?= htmlspecialchars($d['title'], ENT_QUOTES) ?>" onclick="toggleDrill(this)">
                    <span class="m-drill-check"><i class="fas fa-check"></i></span>
                    <span class="m-drill-name"><?= htmlspecialchars($d['title']) ?></span>
                    <span class="m-drill-cat"><?= htmlspecialchars($d['category'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="m-modal-btns">
                <button type="button" class="m-btn-cancel" onclick="closeCreateModal()">Cancel</button>
                <button type="submit" class="m-btn-save" id="ppCreateSave">Create Plan</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Dialog -->
<div class="m-confirm-overlay" id="ppDeleteOverlay">
    <div class="m-confirm-box">
        <i class="fas fa-exclamation-triangle"></i>
        <h4>Delete Practice Plan</h4>
        <p id="ppDeleteMsg">Are you sure? This cannot be undone.</p>
        <div class="m-confirm-btns">
            <button type="button" class="m-btn-cancel" onclick="closeDeleteDialog()">Cancel</button>
            <button type="button" class="m-btn-danger" id="ppDeleteConfirm">Delete</button>
        </div>
    </div>
</div>

<script>
(function(){
    // Search filter
    var search = document.getElementById('ppSearch');
    if (search) {
        search.addEventListener('input', function() {
            var q = this.value.toLowerCase();
            document.querySelectorAll('#ppList .m-plan-card').forEach(function(card) {
                var title = (card.getAttribute('data-title') || '').toLowerCase();
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

    // ---- Edit Modal ----
    window.openEditModal = function(card) {
        document.getElementById('ppEditId').value = card.getAttribute('data-plan-id');
        document.getElementById('ppEditTitle').value = card.getAttribute('data-title');
        document.getElementById('ppEditDesc').value = card.getAttribute('data-desc');
        document.getElementById('ppEditDur').value = card.getAttribute('data-duration') || '';
        var focusSel = document.getElementById('ppEditFocus');
        if (focusSel) focusSel.value = card.getAttribute('data-focus') || '';
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
                    var newDur = document.getElementById('ppEditDur').value;
                    var newFocus = document.getElementById('ppEditFocus').value;
                    card.setAttribute('data-title', newTitle);
                    card.setAttribute('data-desc', newDesc);
                    card.setAttribute('data-duration', newDur);
                    card.setAttribute('data-focus', newFocus);
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
                    var durEl = card.querySelector('.m-plan-duration');
                    if (durEl && newDur) { durEl.innerHTML = '<i class="fas fa-clock"></i> ' + newDur + 'min'; }
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

    // ---- Create Modal ----
    var selectedDrills = [];

    window.openCreateModal = function() {
        document.getElementById('ppCreateForm').reset();
        document.getElementById('ppCreateDrills').value = '[]';
        selectedDrills = [];
        renderSelectedDrills();
        document.querySelectorAll('#ppDrillPicker .m-drill-item').forEach(function(el) {
            el.classList.remove('selected');
        });
        document.getElementById('ppCreateOverlay').classList.add('active');
    };

    window.closeCreateModal = function() {
        document.getElementById('ppCreateOverlay').classList.remove('active');
    };

    document.getElementById('ppCreateOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeCreateModal();
    });

    window.toggleDrill = function(el) {
        var id = parseInt(el.getAttribute('data-drill-id'));
        var title = el.getAttribute('data-drill-title');
        var idx = selectedDrills.findIndex(function(d){ return d.id === id; });
        if (idx >= 0) {
            selectedDrills.splice(idx, 1);
            el.classList.remove('selected');
        } else {
            selectedDrills.push({id: id, title: title});
            el.classList.add('selected');
        }
        renderSelectedDrills();
    };

    window.removeDrill = function(id) {
        selectedDrills = selectedDrills.filter(function(d){ return d.id !== id; });
        var el = document.querySelector('#ppDrillPicker .m-drill-item[data-drill-id="'+id+'"]');
        if (el) el.classList.remove('selected');
        renderSelectedDrills();
    };

    function renderSelectedDrills() {
        var container = document.getElementById('ppSelectedDrills');
        var drillsInput = document.getElementById('ppCreateDrills');
        container.innerHTML = '';
        selectedDrills.forEach(function(d) {
            var tag = document.createElement('span');
            tag.className = 'm-selected-drill-tag';
            tag.innerHTML = '<span>' + escHtml(d.title) + '</span><button type="button" onclick="removeDrill('+d.id+')">&times;</button>';
            container.appendChild(tag);
        });
        drillsInput.value = JSON.stringify(selectedDrills.map(function(d){ return {id: d.id}; }));
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    // Drill search filter
    var drillSearch = document.getElementById('ppDrillSearch');
    if (drillSearch) {
        drillSearch.addEventListener('input', function() {
            var q = this.value.toLowerCase();
            document.querySelectorAll('#ppDrillPicker .m-drill-item').forEach(function(item) {
                var name = (item.getAttribute('data-drill-title') || '').toLowerCase();
                item.style.display = name.includes(q) ? '' : 'none';
            });
        });
    }

    window.submitCreate = function(e) {
        e.preventDefault();
        var form = document.getElementById('ppCreateForm');
        var btn = document.getElementById('ppCreateSave');
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
                closeCreateModal();
                persistToast('Practice plan created!', 'success');
                location.reload();
            } else {
                ppToast(data.message || 'Create failed', 'error');
            }
        })
        .catch(function(){
            btn.disabled = false;
            ppToast('Network error', 'error');
        });
        return false;
    };

    // ---- Delete Confirmation ----
    var pendingDeleteId = null;
    var pendingDeleteCard = null;

    window.deletePlan = function(id, card) {
        pendingDeleteId = id;
        pendingDeleteCard = card;
        var title = card.getAttribute('data-title') || 'this plan';
        document.getElementById('ppDeleteMsg').textContent = 'Delete "' + title + '"? This cannot be undone.';
        document.getElementById('ppDeleteOverlay').classList.add('active');
    };

    window.closeDeleteDialog = function() {
        document.getElementById('ppDeleteOverlay').classList.remove('active');
        pendingDeleteId = null;
        pendingDeleteCard = null;
    };

    document.getElementById('ppDeleteOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteDialog();
    });

    document.getElementById('ppDeleteConfirm').addEventListener('click', function() {
        if (!pendingDeleteId) return;
        var btn = this;
        btn.disabled = true;
        var fd = new FormData();
        fd.append('action', 'delete_plan');
        fd.append('plan_id', pendingDeleteId);
        var tokenInput = document.querySelector('#ppEditForm input[name="csrf_token"]');
        if (tokenInput) fd.append('csrf_token', tokenInput.value);
        fetch('process_practice_plans.php', {
            method: 'POST',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            body: fd
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
            btn.disabled = false;
            if (data.success) {
                if (pendingDeleteCard) pendingDeleteCard.remove();
                closeDeleteDialog();
                ppToast('Plan deleted', 'success');
            } else {
                closeDeleteDialog();
                ppToast(data.message || 'Delete failed', 'error');
            }
        })
        .catch(function(){
            btn.disabled = false;
            closeDeleteDialog();
            ppToast('Network error', 'error');
        });
    });
})();
</script>
