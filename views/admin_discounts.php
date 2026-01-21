<?php
/**
 * Admin - Manage Discount Codes
 * Create and manage discount codes for sessions and packages
 */

require_once __DIR__ . '/../security.php';

// Check if user has permission
if ($user_role !== 'admin') {
    header('Location: dashboard.php?page=home');
    exit;
}

// Get all discount codes
$discounts = $pdo->query("
    SELECT * FROM discount_codes
    ORDER BY created_at DESC
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
    .discounts-table {
        width: 100%;
        border-collapse: collapse;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        overflow: hidden;
    }
    .discounts-table thead {
        background: #06080b;
    }
    .discounts-table th {
        text-align: left;
        padding: 15px;
        color: #94a3b8;
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 700;
    }
    .discounts-table td {
        padding: 15px;
        border-bottom: 1px solid #1e293b;
        color: #fff;
    }
    .discounts-table tr:hover {
        background: rgba(255, 77, 0, 0.05);
    }
    .discount-code {
        font-family: monospace;
        font-weight: 700;
        color: var(--primary);
        font-size: 14px;
    }
    .discount-type {
        display: inline-block;
        background: var(--primary);
        color: #fff;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
    }
    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
    }
    .status-badge.active {
        background: #10b981;
        color: #fff;
    }
    .status-badge.expired {
        background: #ef4444;
        color: #fff;
    }
    .status-badge.used {
        background: #f59e0b;
        color: #fff;
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
    .form-input, .form-select {
        width: 100%;
        padding: 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
    }
    .form-input:focus, .form-select:focus {
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
        <i class="fas fa-tags"></i> Manage Discount Codes
    </h1>
    <button onclick="openCreateModal()" class="btn-create">
        <i class="fas fa-plus"></i> Create Discount Code
    </button>
</div>

<?php if (empty($discounts)): ?>
    <div class="empty-state">
        <i class="fas fa-tags"></i>
        <h2 style="font-size: 24px; color: #fff; margin-bottom: 10px;">No Discount Codes</h2>
        <p style="color: #64748b;">Create your first discount code to get started</p>
    </div>
<?php else: ?>
    <table class="discounts-table">
        <thead>
            <tr>
                <th>Code</th>
                <th>Type</th>
                <th>Value</th>
                <th>Usage</th>
                <th>Expiry</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($discounts as $discount): ?>
                <?php
                $is_expired = $discount['expiry_date'] && strtotime($discount['expiry_date']) < time();
                $is_fully_used = $discount['usage_limit'] && $discount['times_used'] >= $discount['usage_limit'];
                $is_active = !$is_expired && !$is_fully_used;
                ?>
                <tr>
                    <td><span class="discount-code"><?= htmlspecialchars($discount['code']) ?></span></td>
                    <td>
                        <span class="discount-type">
                            <?= $discount['type'] === 'percent' ? 'PERCENTAGE' : 'FIXED AMOUNT' ?>
                        </span>
                    </td>
                    <td style="font-weight: 600;">
                        <?php if ($discount['type'] === 'percent'): ?>
                            <?= number_format($discount['value'], 0) ?>% OFF
                        <?php else: ?>
                            $<?= number_format($discount['value'], 2) ?> OFF
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $discount['times_used'] ?>
                        <?php if ($discount['usage_limit']): ?>
                            / <?= $discount['usage_limit'] ?>
                        <?php else: ?>
                            / Unlimited
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($discount['expiry_date']): ?>
                            <?= date('M d, Y', strtotime($discount['expiry_date'])) ?>
                        <?php else: ?>
                            <span style="color: #64748b;">No expiry</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($is_expired): ?>
                            <span class="status-badge expired">EXPIRED</span>
                        <?php elseif ($is_fully_used): ?>
                            <span class="status-badge used">FULLY USED</span>
                        <?php else: ?>
                            <span class="status-badge active">ACTIVE</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="#" onclick="openEditModal(<?= $discount['id'] ?>, '<?= htmlspecialchars($discount['code'], ENT_QUOTES) ?>', '<?= $discount['type'] ?>', <?= $discount['value'] ?>, <?= $discount['usage_limit'] ?? 'null' ?>, '<?= $discount['expiry_date'] ?? '' ?>')" class="btn-edit">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="#" onclick="deleteDiscount(<?= $discount['id'] ?>)" class="btn-delete">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- Create/Edit Modal -->
<div id="discountModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Create Discount Code</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        
        <form method="POST" action="process_admin_action.php" id="discountForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" id="formAction" value="create_discount">
            <input type="hidden" name="discount_id" id="discountId">
            
            <div class="form-group">
                <label class="form-label">Discount Code *</label>
                <input type="text" name="code" id="discountCode" class="form-input" required
                       placeholder="e.g., SPRING2024" style="text-transform: uppercase;">
            </div>
            
            <div class="form-group">
                <label class="form-label">Discount Type *</label>
                <select name="type" id="discountType" class="form-select" required>
                    <option value="percent">Percentage Off</option>
                    <option value="fixed">Fixed Amount Off</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Discount Value *</label>
                <input type="number" name="value" id="discountValue" class="form-input" 
                       step="0.01" min="0" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Usage Limit</label>
                <input type="number" name="usage_limit" id="usageLimit" class="form-input" 
                       min="1" placeholder="Leave blank for unlimited">
            </div>
            
            <div class="form-group">
                <label class="form-label">Expiry Date</label>
                <input type="date" name="expiry_date" id="expiryDate" class="form-input"
                       min="<?= date('Y-m-d') ?>">
            </div>
            
            <button type="submit" class="btn-submit">
                <i class="fas fa-save"></i> Save Discount Code
            </button>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Create Discount Code';
    document.getElementById('formAction').value = 'create_discount';
    document.getElementById('discountId').value = '';
    document.getElementById('discountCode').value = '';
    document.getElementById('discountType').value = 'percent';
    document.getElementById('discountValue').value = '';
    document.getElementById('usageLimit').value = '';
    document.getElementById('expiryDate').value = '';
    document.getElementById('discountModal').classList.add('active');
}

function openEditModal(id, code, type, value, usageLimit, expiryDate) {
    document.getElementById('modalTitle').textContent = 'Edit Discount Code';
    document.getElementById('formAction').value = 'edit_discount';
    document.getElementById('discountId').value = id;
    document.getElementById('discountCode').value = code;
    document.getElementById('discountType').value = type;
    document.getElementById('discountValue').value = value;
    document.getElementById('usageLimit').value = usageLimit;
    document.getElementById('expiryDate').value = expiryDate;
    document.getElementById('discountModal').classList.add('active');
}

function closeModal() {
    document.getElementById('discountModal').classList.remove('active');
}

function deleteDiscount(id) {
    if (confirm('Are you sure you want to delete this discount code?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'process_admin_action.php';
        form.innerHTML = '<?= csrfTokenInput() ?>' +
            '<input type="hidden" name="action" value="delete_discount">' +
            '<input type="hidden" name="discount_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
document.getElementById('discountModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>
