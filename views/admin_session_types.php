<?php
/**
 * Admin - Manage Session Types
 * Add, edit, and manage session types
 */

require_once __DIR__ . '/../security.php';

// Check if user has permission
if ($user_role !== 'admin') {
    header('Location: dashboard.php?page=home');
    exit;
}

// Get all session types
$session_types = $pdo->query("
    SELECT st.*, 
           (SELECT COUNT(*) FROM sessions WHERE session_type = st.name) as session_count
    FROM session_types st
    ORDER BY st.name
")->fetchAll();
?>

<style>
    /* Session Types - Component Specific Styles */
    .types-table {
        width: 100%;
        border-collapse: collapse;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow: hidden;
    }
    .types-table thead {
        background: var(--bg-main);
    }
    .types-table th {
        text-align: left;
        padding: 16px;
        color: var(--text-dim);
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 700;
    }
    .types-table td {
        padding: 16px;
        border-bottom: 1px solid var(--border);
        color: var(--text-white);
    }
    .types-table tr:hover {
        background: rgba(107, 70, 193, 0.05);
    }
    .btn-edit, .btn-delete {
        padding: 6px 12px;
        border-radius: 4px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        margin-right: 8px;
        transition: all 0.2s;
    }
    .btn-edit {
        background: var(--primary);
        color: #fff;
    }
    .btn-edit:hover {
        background: #e64500;
    }
    .btn-delete {
        background: transparent;
        border: 1px solid #ef4444;
        color: #ef4444;
    }
    .btn-delete:hover {
        background: #ef4444;
        color: #fff;
    }
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }
    .modal.active {
        display: flex;
    }
    .modal-content {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 24px;
        max-width: 500px;
        width: 90%;
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .modal-title {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
    }
    .modal-close {
        background: none;
        border: none;
        color: #94a3b8;
        font-size: 24px;
        cursor: pointer;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #94a3b8;
        margin-bottom: 8px;
        text-transform: uppercase;
    }
    .form-input, .form-textarea {
        width: 100%;
        padding: 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
    }
    .form-textarea {
        min-height: 100px;
        resize: vertical;
        font-family: inherit;
    }
    .form-input:focus, .form-textarea:focus {
        outline: none;
        border-color: var(--primary);
    }
    .btn-submit {
        width: 100%;
        padding: 12px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        font-size: 14px;
    }
    .btn-submit:hover {
        background: #e64500;
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
    }
    .empty-state i {
        font-size: 64px;
        color: #64748b;
        opacity: 0.3;
        margin-bottom: 20px;
    }
</style>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-list-alt"></i> Manage Session Types</h1>
        <p class="page-description">Add, edit, and manage session types</p>
    </div>
    <button type="button" onclick="openCreateModal()" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add Session Type
    </button>
</div>

<?php if (empty($session_types)): ?>
    <div class="empty-state">
        <i class="fas fa-list-alt"></i>
        <h2 style="font-size: 24px; color: #fff; margin-bottom: 10px;">No Session Types</h2>
        <p style="color: #64748b;">Add your first session type to get started</p>
    </div>
<?php else: ?>
    <table class="types-table">
        <thead>
            <tr>
                <th>Session Type Name</th>
                <th>Description</th>
                <th>Sessions Using Type</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($session_types as $type): ?>
                <tr>
                    <td style="font-weight: 600;"><?= htmlspecialchars($type['name']) ?></td>
                    <td style="max-width: 300px;">
                        <?= htmlspecialchars(substr($type['description'] ?? '', 0, 100)) ?>
                        <?= strlen($type['description'] ?? '') > 100 ? '...' : '' ?>
                    </td>
                    <td><?= $type['session_count'] ?> sessions</td>
                    <td><?= date('M d, Y', strtotime($type['created_at'])) ?></td>
                    <td>
                        <a href="#" onclick="openEditModal(<?= $type['id'] ?>, '<?= htmlspecialchars($type['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($type['description'] ?? '', ENT_QUOTES) ?>')" class="btn-edit">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <?php if ($type['session_count'] == 0): ?>
                            <a href="#" onclick="deleteType(<?= $type['id'] ?>)" class="btn-delete">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- Create/Edit Modal -->
<div id="typeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Add Session Type</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal()">&times;</button>
        </div>
        
        <form method="POST" action="process_admin_action.php" id="typeForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" id="formAction" value="create_session_type">
            <input type="hidden" name="type_id" id="typeId">
            
            <div class="form-group">
                <label class="form-label">Session Type Name *</label>
                <input type="text" name="name" id="typeName" class="form-input" required
                       placeholder="e.g., Skills Development, Power Skating">
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="typeDescription" class="form-textarea"
                          placeholder="Optional description of this session type..."></textarea>
            </div>
            
            <button type="submit" class="btn-submit">
                <i class="fas fa-save"></i> Save Session Type
            </button>
        </form>
    </div>
</div>

<script>
var csrfToken = document.querySelector('[name="csrf_token"]')?.value || '';

// Show notification helper
function showNotification(message, type) {
    var existing = document.querySelector('.notification-widget');
    if (existing) existing.remove();
    
    var div = document.createElement('div');
    div.className = 'notification-widget';
    div.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; padding: 16px 24px; border-radius: 8px; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);';
    if (type === 'success') {
        div.style.background = 'rgba(16, 185, 129, 0.95)';
        div.style.color = '#fff';
    } else {
        div.style.background = 'rgba(239, 68, 68, 0.95)';
        div.style.color = '#fff';
    }
    var safeMsg = document.createElement('span');
    safeMsg.textContent = message;
    div.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ';
    div.appendChild(safeMsg);
    var closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&times;';
    closeBtn.style.cssText = 'margin-left: 16px; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;';
    closeBtn.onclick = function() { div.remove(); };
    div.appendChild(closeBtn);
    document.body.appendChild(div);
    setTimeout(function() { if (div.parentElement) div.remove(); }, 5000);
}

function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Add Session Type';
    document.getElementById('formAction').value = 'create_session_type';
    document.getElementById('typeId').value = '';
    document.getElementById('typeName').value = '';
    document.getElementById('typeDescription').value = '';
    document.getElementById('typeModal').classList.add('active');
}

function openEditModal(id, name, description) {
    document.getElementById('modalTitle').textContent = 'Edit Session Type';
    document.getElementById('formAction').value = 'edit_session_type';
    document.getElementById('typeId').value = id;
    document.getElementById('typeName').value = name;
    document.getElementById('typeDescription').value = description || '';
    document.getElementById('typeModal').classList.add('active');
}

function closeModal() {
    document.getElementById('typeModal').classList.remove('active');
}

async function deleteType(id) {
    if (!await showConfirmModal('Are you sure you want to delete this session type?')) return;
    
    fetch('process_admin_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'action=delete_session_type&type_id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            persistToast(data.message || 'Session type deleted!', 'success');
            location.reload();
        } else {
            showNotification('Error: ' + (data.message || 'Failed to delete'), 'error');
        }
    })
    .catch(function() { showNotification('An error occurred', 'error'); });
}

// Handle form submission via AJAX
document.getElementById('typeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var form = this;
    var formData = new FormData(form);
    var submitBtn = form.querySelector('button[type="submit"]');
    var originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    submitBtn.disabled = true;
    
    fetch(form.getAttribute('action'), {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        if (data.success) {
            persistToast(data.message || 'Session type saved!', 'success');
            closeModal();
            location.reload();
        } else {
            showNotification('Error: ' + (data.message || 'Failed to save'), 'error');
        }
    })
    .catch(function() {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        showNotification('An error occurred', 'error');
    });
});


</script>
