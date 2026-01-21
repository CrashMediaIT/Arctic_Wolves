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
    :root {
        --primary: #7000a4;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
    }
    .btn-create {
        background: var(--primary);
        color: #fff;
        padding: 12px 24px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 700;
        font-size: 14px;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
    }
    .btn-create:hover {
        background: #e64500;
    }
    .types-table {
        width: 100%;
        border-collapse: collapse;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        overflow: hidden;
    }
    .types-table thead {
        background: #06080b;
    }
    .types-table th {
        text-align: left;
        padding: 15px;
        color: #94a3b8;
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 700;
    }
    .types-table td {
        padding: 15px;
        border-bottom: 1px solid #1e293b;
        color: #fff;
    }
    .types-table tr:hover {
        background: rgba(255, 77, 0, 0.05);
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
        padding: 30px;
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
    <h1 class="page-title">
        <i class="fas fa-list-alt"></i> Manage Session Types
    </h1>
    <button onclick="openCreateModal()" class="btn-create">
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
            <button class="modal-close" onclick="closeModal()">&times;</button>
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
    document.getElementById('typeDescription').value = description;
    document.getElementById('typeModal').classList.add('active');
}

function closeModal() {
    document.getElementById('typeModal').classList.remove('active');
}

function deleteType(id) {
    if (confirm('Are you sure you want to delete this session type?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'process_admin_action.php';
        form.innerHTML = '<?= csrfTokenInput() ?>' +
            '<input type="hidden" name="action" value="delete_session_type">' +
            '<input type="hidden" name="type_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
document.getElementById('typeModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>
