<?php
/**
 * PWA Marketing - Mobile-native business cards / contacts list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$cards = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, title, company, email, phone FROM business_cards ORDER BY name ASC LIMIT 20");
    $stmt->execute();
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $cards = []; }
?>
<style>
.m-marketing { padding: 16px; font-family: Inter, sans-serif; }
.m-marketing-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.m-marketing-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-marketing-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-marketing-add-btn {
    min-width: 44px; min-height: 44px; border-radius: 50%;
    background: #6B46C1; color: #fff; border: none; font-size: 18px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.m-bcard {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-bcard-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 700; color: #fff; flex-shrink: 0;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
}
.m-bcard-body { flex: 1; min-width: 0; }
.m-bcard-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-bcard-detail { font-size: 12px; color: #A8A8B8; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-bcard-contact { display: flex; gap: 8px; margin-top: 6px; flex-wrap: wrap; }
.m-bcard-link {
    font-size: 11px; color: #8B5CF6; text-decoration: none;
    display: inline-flex; align-items: center; gap: 4px;
}
.m-bcard-actions { display: flex; gap: 4px; flex-shrink: 0; }
.m-bcard-action-btn {
    min-width: 36px; min-height: 36px; border: none; border-radius: 8px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 13px; background: none;
}
.m-bcard-action-btn.m-edit { color: #8B5CF6; }
.m-bcard-action-btn.m-del { color: #EF4444; }
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
.m-form-submit {
    width: 100%; min-height: 44px; border-radius: 10px;
    background: #6B46C1; color: #fff; font-size: 14px; font-weight: 600;
    border: none; cursor: pointer; font-family: Inter, sans-serif; margin-top: 8px;
}
.m-form-submit:disabled { opacity: 0.5; }
.m-alert {
    padding: 10px 14px; border-radius: 10px; font-size: 13px; margin-top: 10px;
    display: none; text-align: center;
}
.m-alert-success { background: rgba(16,185,129,0.15); color: #10B981; }
.m-alert-error { background: rgba(239,68,68,0.15); color: #EF4444; }
</style>

<div class="m-marketing">
    <div class="m-marketing-header">
        <div>
            <h2 class="m-marketing-title">Business Cards</h2>
            <p class="m-marketing-sub"><?= count($cards) ?> contact<?= count($cards) !== 1 ? 's' : '' ?></p>
        </div>
        <button class="m-marketing-add-btn" type="button" onclick="mBcardOpen()" title="Add Card">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <div id="mBcardAlert" class="m-alert"></div>

    <?php if (empty($cards)): ?>
        <div class="m-empty-state" id="mBcardEmpty">
            <i class="fas fa-address-card"></i>
            <p>No business cards found</p>
        </div>
    <?php else: ?>
        <div id="mBcardList">
        <?php foreach ($cards as $c):
            $initial = strtoupper(mb_substr($c['name'] ?? '?', 0, 1));
        ?>
        <div class="m-bcard" id="mBcard-<?= (int)$c['id'] ?>">
            <div class="m-bcard-avatar"><?= $initial ?></div>
            <div class="m-bcard-body">
                <div class="m-bcard-name"><?= htmlspecialchars($c['name'] ?? '') ?></div>
                <?php if (!empty($c['title']) || !empty($c['company'])): ?>
                <div class="m-bcard-detail"><?= htmlspecialchars(trim(($c['title'] ?? '') . ' · ' . ($c['company'] ?? ''), ' ·')) ?></div>
                <?php endif; ?>
                <div class="m-bcard-contact">
                    <?php if (!empty($c['email'])): ?>
                    <a href="mailto:<?= htmlspecialchars($c['email']) ?>" class="m-bcard-link"><i class="fas fa-envelope"></i> <?= htmlspecialchars($c['email']) ?></a>
                    <?php endif; ?>
                    <?php if (!empty($c['phone'])): ?>
                    <a href="tel:<?= htmlspecialchars($c['phone']) ?>" class="m-bcard-link"><i class="fas fa-phone"></i> <?= htmlspecialchars($c['phone']) ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="m-bcard-actions">
                <button class="m-bcard-action-btn m-edit" type="button" onclick="mBcardEdit(<?= (int)$c['id'] ?>, <?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)" title="Edit"><i class="fas fa-pen"></i></button>
                <button class="m-bcard-action-btn m-del" type="button" onclick="mBcardDel(<?= (int)$c['id'] ?>)" title="Delete"><i class="fas fa-trash"></i></button>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="m-bs-overlay" id="mBcardOverlay" onclick="mBcardClose()"></div>
<div class="m-bs-sheet" id="mBcardSheet">
    <div class="m-bs-handle"></div>
    <h3 class="m-bs-title" id="mBcardSheetTitle">Add Business Card</h3>
    <form id="mBcardForm" onsubmit="return mBcardSubmit(event)">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" id="mBcardAction" value="create">
        <input type="hidden" name="id" id="mBcardId" value="">
        <div class="m-form-group">
            <label class="m-form-label">Name *</label>
            <input type="text" name="name" id="mBcardName" class="m-form-input" required>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Title</label>
            <input type="text" name="title" id="mBcardTitle" class="m-form-input">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Company</label>
            <input type="text" name="company" id="mBcardCompany" class="m-form-input">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Email</label>
            <input type="email" name="email" id="mBcardEmail" class="m-form-input">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Phone</label>
            <input type="tel" name="phone" id="mBcardPhone" class="m-form-input">
        </div>
        <button type="submit" class="m-form-submit" id="mBcardSubmitBtn">Save</button>
    </form>
</div>

<script>
(function() {
    var csrfToken = document.querySelector('#mBcardForm [name="csrf_token"]')?.value || '';

    function showAlert(type, msg) {
        var el = document.getElementById('mBcardAlert');
        el.className = 'm-alert m-alert-' + type;
        el.textContent = msg;
        el.style.display = 'block';
        setTimeout(function() { el.style.display = 'none'; }, 4000);
    }

    window.mBcardOpen = function(data) {
        var sheet = document.getElementById('mBcardSheet');
        var overlay = document.getElementById('mBcardOverlay');
        document.getElementById('mBcardSheetTitle').textContent = data ? 'Edit Business Card' : 'Add Business Card';
        document.getElementById('mBcardAction').value = data ? 'update' : 'create';
        document.getElementById('mBcardId').value = data ? data.id : '';
        document.getElementById('mBcardName').value = data ? (data.name || '') : '';
        document.getElementById('mBcardTitle').value = data ? (data.title || '') : '';
        document.getElementById('mBcardCompany').value = data ? (data.company || '') : '';
        document.getElementById('mBcardEmail').value = data ? (data.email || '') : '';
        document.getElementById('mBcardPhone').value = data ? (data.phone || '') : '';
        sheet.style.display = 'block';
        overlay.style.display = 'block';
    };

    window.mBcardEdit = function(id, data) { mBcardOpen(data); };

    window.mBcardClose = function() {
        document.getElementById('mBcardSheet').style.display = 'none';
        document.getElementById('mBcardOverlay').style.display = 'none';
    };

    window.mBcardSubmit = function(e) {
        e.preventDefault();
        var btn = document.getElementById('mBcardSubmitBtn');
        btn.disabled = true;
        btn.textContent = 'Saving...';
        var fd = new FormData(document.getElementById('mBcardForm'));
        fetch('process_business_partners.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    persistToast(data.message || 'Saved', 'success');
                    mBcardClose();
                    window.location.reload();
                } else { showAlert('error', data.message || 'Error saving'); }
            })
            .catch(function() { showAlert('error', 'Network error'); })
            .finally(function() { btn.disabled = false; btn.textContent = 'Save'; });
        return false;
    };

    window.mBcardDel = async function(id) {
        if (!await showConfirmModal('Delete this business card?')) return;
        var fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fd.append('csrf_token', csrfToken);
        fetch('process_business_partners.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var el = document.getElementById('mBcard-' + id);
                    if (el) el.remove();
                    showAlert('success', 'Card deleted');
                } else { showAlert('error', data.message || 'Error deleting'); }
            })
            .catch(function() { showAlert('error', 'Network error'); });
    };
})();
</script>
