<?php
/**
 * PWA Session Templates - Mobile-native session template library
 * Purpose-built for mobile phones.
 */

$templates = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, name, description, duration_minutes
        FROM training_session_templates
        WHERE is_active = 1
        ORDER BY name
        LIMIT 20
    ");
    $stmt->execute();
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $templates = []; }

$totalTemplates = count($templates);

// Also load from session_templates table (desktop uses this)
$sessionTemplates = [];
try {
    $stmt2 = $pdo->prepare("SELECT id, title, description, session_type, age_group, session_plan FROM session_templates ORDER BY created_at DESC LIMIT 20");
    $stmt2->execute();
    $sessionTemplates = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $sessionTemplates = []; }
?>
<style>
.m-sesstpl { padding: 16px; font-family: Inter, sans-serif; }
.m-sesstpl-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.m-sesstpl-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-sesstpl-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-sesstpl-add-btn {
    min-width: 44px; min-height: 44px; border-radius: 50%;
    background: #6B46C1; color: #fff; border: none; font-size: 18px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.m-sesstpl-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-sesstpl-name { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 4px; }
.m-sesstpl-desc { font-size: 12px; color: #A8A8B8; margin: 0 0 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-sesstpl-meta {
    display: flex; align-items: center; gap: 8px;
    padding-top: 10px; border-top: 1px solid #2D2D3F;
}
.m-sesstpl-dur { font-size: 12px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-sesstpl-badge { font-size: 10px; padding: 2px 8px; border-radius: 6px; font-weight: 600; background: rgba(107,70,193,0.2); color: #8B5CF6; }
.m-sesstpl-badge-green { background: rgba(16,185,129,0.15); color: #10B981; }
.m-sesstpl-actions { display: flex; gap: 6px; margin-left: auto; }
.m-sesstpl-action-btn {
    min-width: 36px; min-height: 36px; border: none; border-radius: 8px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 13px; background: none;
}
.m-sesstpl-action-btn.m-edit { color: #8B5CF6; }
.m-sesstpl-action-btn.m-del { color: #EF4444; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-bs-overlay {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); z-index: 999;
}
.m-bs-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000;
    background: #16161F; border-radius: 16px 16px 0 0;
    padding: 20px 16px 32px; display: none;
    max-height: 85vh; overflow-y: auto;
}
.m-bs-handle { width: 40px; height: 4px; background: #2D2D3F; border-radius: 2px; margin: 0 auto 16px; }
.m-bs-title { font-size: 16px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-form-group { margin-bottom: 12px; }
.m-form-label { font-size: 12px; color: #A8A8B8; margin-bottom: 6px; display: block; }
.m-form-input {
    width: 100%; min-height: 44px; padding: 12px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box;
}
.m-form-input:focus { border-color: #8B5CF6; outline: none; }
.m-form-textarea {
    width: 100%; min-height: 80px; padding: 12px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box; resize: vertical;
}
.m-form-textarea:focus { border-color: #8B5CF6; outline: none; }
.m-form-submit {
    width: 100%; min-height: 44px; border-radius: 10px;
    background: #6B46C1; color: #fff; font-size: 14px; font-weight: 600;
    border: none; cursor: pointer; font-family: Inter, sans-serif; margin-top: 8px;
}
.m-form-submit:disabled { opacity: 0.5; }
.m-alert {
    padding: 10px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 10px;
    display: none; text-align: center;
}
.m-alert-success { background: rgba(16,185,129,0.15); color: #10B981; }
.m-alert-error { background: rgba(239,68,68,0.15); color: #EF4444; }
</style>

<div class="m-sesstpl">
    <div class="m-sesstpl-header">
        <div>
            <h2 class="m-sesstpl-title">Session Templates</h2>
            <p class="m-sesstpl-sub"><?= $totalTemplates + count($sessionTemplates) ?> template<?= ($totalTemplates + count($sessionTemplates)) !== 1 ? 's' : '' ?></p>
        </div>
        <button class="m-sesstpl-add-btn" type="button" onclick="mSessTplOpen()" title="Create Template">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <div id="mSessTplAlert" class="m-alert"></div>

    <?php if (empty($templates) && empty($sessionTemplates)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clipboard-list"></i>
            <p>No session templates available</p>
        </div>
    <?php else: ?>
        <?php foreach ($sessionTemplates as $st): ?>
        <div class="m-sesstpl-card" id="mSessTpl-<?= (int)$st['id'] ?>">
            <div class="m-sesstpl-name"><?= htmlspecialchars($st['title']) ?></div>
            <?php if (!empty($st['description'])): ?>
            <p class="m-sesstpl-desc"><?= htmlspecialchars($st['description']) ?></p>
            <?php endif; ?>
            <div class="m-sesstpl-meta">
                <?php if (!empty($st['session_type'])): ?>
                <span class="m-sesstpl-badge"><?= htmlspecialchars($st['session_type']) ?></span>
                <?php endif; ?>
                <?php if (!empty($st['age_group'])): ?>
                <span class="m-sesstpl-badge m-sesstpl-badge-green"><?= htmlspecialchars($st['age_group']) ?></span>
                <?php endif; ?>
                <div class="m-sesstpl-actions">
                    <button class="m-sesstpl-action-btn m-edit" type="button" onclick="mSessTplEdit(<?= (int)$st['id'] ?>, <?= htmlspecialchars(json_encode($st), ENT_QUOTES) ?>)" title="Edit"><i class="fas fa-pen"></i></button>
                    <button class="m-sesstpl-action-btn m-del" type="button" onclick="mSessTplDel(<?= (int)$st['id'] ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php foreach ($templates as $t): ?>
        <div class="m-sesstpl-card">
            <div class="m-sesstpl-name"><?= htmlspecialchars($t['name']) ?></div>
            <?php if (!empty($t['description'])): ?>
            <p class="m-sesstpl-desc"><?= htmlspecialchars($t['description']) ?></p>
            <?php endif; ?>
            <div class="m-sesstpl-meta">
                <?php if (!empty($t['duration_minutes'])): ?>
                <span class="m-sesstpl-dur"><i class="fas fa-clock"></i> <?= (int)$t['duration_minutes'] ?> min</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="m-bs-overlay" id="mSessTplOverlay" onclick="mSessTplClose()"></div>
<div class="m-bs-sheet" id="mSessTplSheet">
    <div class="m-bs-handle"></div>
    <h3 class="m-bs-title" id="mSessTplSheetTitle">Create Template</h3>
    <form id="mSessTplForm" onsubmit="return mSessTplSubmit(event)">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" id="mSessTplAction" value="create">
        <input type="hidden" name="id" id="mSessTplId" value="">
        <div class="m-form-group">
            <label class="m-form-label">Title *</label>
            <input type="text" name="title" id="mSessTplTitle" class="m-form-input" required>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Session Type</label>
            <input type="text" name="session_type" id="mSessTplType" class="m-form-input" placeholder="e.g., Practice, Game Prep">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Age Group</label>
            <input type="text" name="age_group" id="mSessTplAge" class="m-form-input" placeholder="e.g., U12, U14">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Description</label>
            <textarea name="description" id="mSessTplDesc" class="m-form-textarea" rows="3"></textarea>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Session Plan</label>
            <textarea name="session_plan" id="mSessTplPlan" class="m-form-textarea" rows="4" placeholder="Detailed session plan..."></textarea>
        </div>
        <button type="submit" class="m-form-submit" id="mSessTplSubmitBtn">Save Template</button>
    </form>
</div>

<script>
(function() {
    var csrfToken = document.querySelector('#mSessTplForm [name="csrf_token"]')?.value || '';

    function showAlert(type, msg) {
        var el = document.getElementById('mSessTplAlert');
        el.className = 'm-alert m-alert-' + type;
        el.textContent = msg;
        el.style.display = 'block';
        setTimeout(function() { el.style.display = 'none'; }, 4000);
    }

    window.mSessTplOpen = function(data) {
        var sheet = document.getElementById('mSessTplSheet');
        var overlay = document.getElementById('mSessTplOverlay');
        document.getElementById('mSessTplSheetTitle').textContent = data ? 'Edit Template' : 'Create Template';
        document.getElementById('mSessTplAction').value = data ? 'update' : 'create';
        document.getElementById('mSessTplId').value = data ? data.id : '';
        document.getElementById('mSessTplTitle').value = data ? (data.title || '') : '';
        document.getElementById('mSessTplType').value = data ? (data.session_type || '') : '';
        document.getElementById('mSessTplAge').value = data ? (data.age_group || '') : '';
        document.getElementById('mSessTplDesc').value = data ? (data.description || '') : '';
        document.getElementById('mSessTplPlan').value = data ? (data.session_plan || '') : '';
        sheet.style.display = 'block';
        overlay.style.display = 'block';
    };

    window.mSessTplEdit = function(id, data) { mSessTplOpen(data); };

    window.mSessTplClose = function() {
        document.getElementById('mSessTplSheet').style.display = 'none';
        document.getElementById('mSessTplOverlay').style.display = 'none';
    };

    window.mSessTplSubmit = function(e) {
        e.preventDefault();
        var btn = document.getElementById('mSessTplSubmitBtn');
        btn.disabled = true;
        btn.textContent = 'Saving...';
        var fd = new FormData(document.getElementById('mSessTplForm'));
        fetch('process_create_session.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    persistToast(data.message || 'Template saved', 'success');
                    mSessTplClose();
                    window.location.reload();
                } else { showAlert('error', data.message || 'Error saving template'); }
            })
            .catch(function() { showAlert('error', 'Network error'); })
            .finally(function() { btn.disabled = false; btn.textContent = 'Save Template'; });
        return false;
    };

    window.mSessTplDel = function(id) {
        if (!confirm('Delete this template?')) return;
        var fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fd.append('csrf_token', csrfToken);
        fetch('process_create_session.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var el = document.getElementById('mSessTpl-' + id);
                    if (el) el.remove();
                    showAlert('success', 'Template deleted');
                } else { showAlert('error', data.message || 'Error deleting'); }
            })
            .catch(function() { showAlert('error', 'Network error'); });
    };
})();
</script>
