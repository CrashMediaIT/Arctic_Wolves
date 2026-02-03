<?php
/**
 * HR Employee Contracts View
 * Manage employee contracts with e-signature workflow using DocuSeal API
 */

// Pagination settings
$page_num = isset($_GET['ec_page']) ? max(1, intval($_GET['ec_page'])) : 1;
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

// Active tab
$active_tab = $_GET['tab'] ?? 'list';

// Filter by status
$status_filter = $_GET['status'] ?? '';

// Pre-populate from onboarding if provided
$prefill_onboarding_id = isset($_GET['onboarding_id']) ? intval($_GET['onboarding_id']) : 0;
$prefill_employee = null;
if ($prefill_onboarding_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, role, start_date FROM employee_onboarding WHERE id = ?");
        $stmt->execute([$prefill_onboarding_id]);
        $prefill_employee = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $prefill_employee = null;
    }
}

// Get DocuSeal settings to check if configured
$docuseal_enabled = false;
$docuseal_templates = [];
try {
    $settingsQuery = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('docuseal_url', 'docuseal_enabled', 'docuseal_api_key')");
    $docuseal_settings = [];
    while ($row = $settingsQuery->fetch(PDO::FETCH_ASSOC)) {
        $docuseal_settings[$row['setting_key']] = $row['setting_value'];
    }
    $docuseal_enabled = !empty($docuseal_settings['docuseal_url']) && 
                        !empty($docuseal_settings['docuseal_api_key']) && 
                        ($docuseal_settings['docuseal_enabled'] ?? '0') === '1';
    
    // Fetch templates from DocuSeal if enabled
    if ($docuseal_enabled) {
        require_once __DIR__ . '/../lib/docuseal.php';
        $docuseal_templates = listDocuSealTemplates($pdo, $docuseal_settings);
    }
} catch (PDOException $e) {
    $docuseal_enabled = false;
}

// Get contract templates
$templates = [];
try {
    $templates = $pdo->query("SELECT * FROM contract_templates WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $templates = [];
}

// Get total count
$total_contracts = 0;
$total_pages = 1;
try {
    $countQuery = "SELECT COUNT(*) FROM employee_contracts";
    if ($status_filter && in_array($status_filter, ['draft', 'pending_signature', 'signed', 'expired', 'cancelled'])) {
        $countQuery .= " WHERE status = ?";
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute([$status_filter]);
    } else {
        $countStmt = $pdo->query($countQuery);
    }
    $total_contracts = $countStmt->fetchColumn();
    $total_pages = ceil($total_contracts / $per_page);
} catch (PDOException $e) {
    $total_contracts = 0;
    $total_pages = 1;
}

// Fetch contracts
$contracts = [];
try {
    $contractQuery = "SELECT ec.*, ct.name as template_name,
            u.first_name as created_first, u.last_name as created_last
        FROM employee_contracts ec
        LEFT JOIN contract_templates ct ON ec.template_id = ct.id
        LEFT JOIN users u ON ec.created_by = u.id";
    
    $params = [];
    if ($status_filter && in_array($status_filter, ['draft', 'pending_signature', 'signed', 'expired', 'cancelled'])) {
        $contractQuery .= " WHERE ec.status = ?";
        $params[] = $status_filter;
    }
    
    $contractQuery .= " ORDER BY ec.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($contractQuery);
    $stmt->execute($params);
    $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $contracts = [];
}

// Get onboarding records for linking
$onboardings = [];
try {
    $onboardings = $pdo->query("
        SELECT id, first_name, last_name, email, role 
        FROM employee_onboarding 
        WHERE onboarding_status IN ('pending', 'in_progress')
        ORDER BY created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $onboardings = [];
}

// Status badge colors
$statusColors = [
    'draft' => 'secondary',
    'pending_signature' => 'warning',
    'signed' => 'success',
    'expired' => 'error',
    'cancelled' => 'error'
];
?>
<!-- HR Employee Contracts View -->

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-file-signature"></i> Employee Contracts</h1>
        <p class="page-description">Create and manage employee contracts with e-signature workflow using DocuSeal. Signed contracts are automatically stored in Nextcloud.</p>
    </div>
</div>

<?php if (!$docuseal_enabled): ?>
<!-- Configuration Warning -->
<div class="alert alert-warning" style="margin-bottom: 24px;">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong>DocuSeal Not Configured</strong>
        <p style="margin: 4px 0 0 0;">E-signature functionality requires DocuSeal to be configured. 
        <a href="?page=system_tools&tab=docuseal" style="color: inherit; text-decoration: underline;">Configure DocuSeal Settings</a></p>
    </div>
</div>
<?php else: ?>
<!-- Info Banner -->
<div class="alert alert-info" style="margin-bottom: 24px;">
    <i class="fas fa-info-circle"></i>
    <div>
        <strong>E-Signature Workflow</strong>
        <p style="margin: 4px 0 0 0;">Create contracts, select a DocuSeal template, and send for e-signature. DocuSeal handles the signing process and once signed, contracts are automatically saved to Nextcloud in the HR/Employee Contract folder organized by year, month, and employee name.</p>
    </div>
</div>
<?php endif; ?>

<!-- Tab Navigation -->
<div class="page-tabs">
    <a href="?page=employee_contracts&tab=list" class="page-tab <?= $active_tab === 'list' ? 'active' : '' ?>">
        <i class="fas fa-list"></i> All Contracts
    </a>
    <a href="?page=employee_contracts&tab=new" class="page-tab <?= $active_tab === 'new' ? 'active' : '' ?>">
        <i class="fas fa-plus"></i> New Contract
    </a>
    <a href="?page=employee_contracts&tab=templates" class="page-tab <?= $active_tab === 'templates' ? 'active' : '' ?>">
        <i class="fas fa-file-alt"></i> Templates
    </a>
</div>

<div class="page-tab-content">

<?php if ($active_tab === 'list'): ?>
<!-- Contracts List -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-file-contract"></i> Contract Records</h3>
        <div style="display: flex; gap: 12px; align-items: center;">
            <select id="status-filter" class="form-input" style="width: auto;" onchange="filterByStatus(this.value)">
                <option value="">All Statuses</option>
                <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="pending_signature" <?= $status_filter === 'pending_signature' ? 'selected' : '' ?>>Pending Signature</option>
                <option value="signed" <?= $status_filter === 'signed' ? 'selected' : '' ?>>Signed</option>
                <option value="expired" <?= $status_filter === 'expired' ? 'selected' : '' ?>>Expired</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            <span class="badge badge-primary"><?= $total_contracts ?> Records</span>
        </div>
    </div>
    <div class="card-body">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Contract</th>
                        <th>Template</th>
                        <th>Status</th>
                        <th>Sent</th>
                        <th>Signed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($contracts)): ?>
                        <?php foreach ($contracts as $contract): ?>
                        <tr data-contract-id="<?= $contract['id'] ?>">
                            <td>
                                <div class="staff-info">
                                    <strong><?= htmlspecialchars($contract['employee_name']) ?></strong>
                                    <small><?= htmlspecialchars($contract['employee_email']) ?></small>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($contract['contract_title']) ?></td>
                            <td><?= htmlspecialchars($contract['template_name'] ?? 'Custom') ?></td>
                            <td>
                                <span class="status-badge <?= $statusColors[$contract['status']] ?? 'secondary' ?>">
                                    <?= ucfirst(str_replace('_', ' ', $contract['status'])) ?>
                                </span>
                            </td>
                            <td><?= $contract['sent_at'] ? date('M j, Y', strtotime($contract['sent_at'])) : '-' ?></td>
                            <td><?= $contract['signed_at'] ? date('M j, Y', strtotime($contract['signed_at'])) : '-' ?></td>
                            <td>
                                <div class="table-actions">
                                    <?php if ($contract['status'] === 'draft'): ?>
                                    <button type="button" class="btn-icon" title="Send for Signature" onclick="sendForSignature(<?= $contract['id'] ?>)">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($contract['status'] === 'pending_signature'): ?>
                                    <button type="button" class="btn-icon" title="Resend Email" onclick="resendContract(<?= $contract['id'] ?>)">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($contract['status'] === 'signed' && !empty($contract['nextcloud_path'])): ?>
                                    <span class="btn-icon nextcloud-path-btn" title="<?= htmlspecialchars($contract['nextcloud_path']) ?>" data-path="<?= htmlspecialchars($contract['nextcloud_path']) ?>">
                                        <i class="fas fa-cloud"></i>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($contract['status'] !== 'signed' && $contract['status'] !== 'cancelled'): ?>
                                    <button type="button" class="btn-icon text-error" title="Cancel Contract" onclick="cancelContract(<?= $contract['id'] ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="empty-state">
                                <div class="empty-state-content">
                                    <i class="fas fa-file-signature"></i>
                                    <p>No contracts found</p>
                                    <span>Create a new contract to get started</span>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page_num > 1): ?>
                <a href="?page=employee_contracts&tab=list&ec_page=<?= $page_num - 1 ?><?= $status_filter ? '&status=' . $status_filter : '' ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = max(1, $page_num - 2); $i <= min($total_pages, $page_num + 2); $i++): ?>
                <a href="?page=employee_contracts&tab=list&ec_page=<?= $i ?><?= $status_filter ? '&status=' . $status_filter : '' ?>" class="page-btn <?= $i === $page_num ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page_num < $total_pages): ?>
                <a href="?page=employee_contracts&tab=list&ec_page=<?= $page_num + 1 ?><?= $status_filter ? '&status=' . $status_filter : '' ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($active_tab === 'new'): ?>
<!-- New Contract Form -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-plus"></i> Create New Contract</h3>
    </div>
    <div class="card-body">
        <form id="new-contract-form" method="POST" action="process_employee_contracts.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <input type="hidden" name="action" value="create">
            
            <div class="form-section">
                <h4><i class="fas fa-user"></i> Employee Information</h4>
                
                <div class="form-group">
                    <label>Link to Onboarding Record (Optional)</label>
                    <select name="onboarding_id" id="onboarding-select" class="form-input" onchange="populateFromOnboarding(this)">
                        <option value="">-- Select or enter manually --</option>
                        <?php foreach ($onboardings as $ob): ?>
                        <option value="<?= $ob['id'] ?>" 
                                data-name="<?= htmlspecialchars($ob['first_name'] . ' ' . $ob['last_name']) ?>"
                                data-email="<?= htmlspecialchars($ob['email']) ?>"
                                data-role="<?= htmlspecialchars($ob['role']) ?>"
                                <?= ($prefill_onboarding_id == $ob['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ob['first_name'] . ' ' . $ob['last_name']) ?> 
                            (<?= ucfirst(str_replace('_', ' ', $ob['role'])) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Employee Name *</label>
                        <input type="text" name="employee_name" id="employee-name" class="form-input" required
                               value="<?= $prefill_employee ? htmlspecialchars($prefill_employee['first_name'] . ' ' . $prefill_employee['last_name']) : '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Employee Email *</label>
                        <input type="email" name="employee_email" id="employee-email" class="form-input" required
                               value="<?= $prefill_employee ? htmlspecialchars($prefill_employee['email']) : '' ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h4><i class="fas fa-file-alt"></i> Contract Details</h4>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Local Template Reference</label>
                        <select name="template_id" class="form-input">
                            <option value="">-- No Local Template --</option>
                            <?php foreach ($templates as $template): ?>
                            <option value="<?= $template['id'] ?>" data-docuseal-id="<?= $template['docuseal_template_id'] ?? '' ?>">
                                <?= htmlspecialchars($template['name']) ?>
                                (<?= ucfirst($template['template_type']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contract Title *</label>
                        <input type="text" name="contract_title" class="form-input" value="Employment Contract" required>
                    </div>
                </div>
                
                <?php if ($docuseal_enabled && !empty($docuseal_templates)): ?>
                <div class="form-group">
                    <label>DocuSeal Template *</label>
                    <select name="docuseal_template_id" id="docuseal-template" class="form-input" required>
                        <option value="">-- Select DocuSeal Template --</option>
                        <?php foreach ($docuseal_templates as $dsTemplate): ?>
                        <option value="<?= $dsTemplate['id'] ?>">
                            <?= htmlspecialchars($dsTemplate['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--text-secondary); margin-top: 4px; display: block;">
                        Select the DocuSeal template to use for e-signature. Templates are created and managed in DocuSeal.
                    </small>
                </div>
                <?php elseif ($docuseal_enabled): ?>
                <div class="alert alert-warning" style="margin-top: 16px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>No templates found in DocuSeal. Please create a template in DocuSeal first.</span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="form-section">
                <h4><i class="fas fa-edit"></i> Contract Data</h4>
                <p class="form-hint">Enter values for the contract template fields. These will be pre-filled in the DocuSeal document.</p>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Position/Role</label>
                        <input type="text" name="contract_data[position]" class="form-input" placeholder="e.g., Head Coach"
                               value="<?= $prefill_employee ? ucfirst(str_replace('_', ' ', htmlspecialchars($prefill_employee['role']))) : '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="contract_data[start_date]" class="form-input"
                               value="<?= $prefill_employee && !empty($prefill_employee['start_date']) ? htmlspecialchars($prefill_employee['start_date']) : '' ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Salary/Rate</label>
                        <input type="text" name="contract_data[salary]" class="form-input" placeholder="e.g., $50,000/year or $25/hour">
                    </div>
                    <div class="form-group">
                        <label>Pay Frequency</label>
                        <select name="contract_data[pay_frequency]" class="form-input">
                            <option value="">-- Select --</option>
                            <option value="weekly">Weekly</option>
                            <option value="bi-weekly">Bi-Weekly</option>
                            <option value="semi-monthly">Semi-Monthly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Employee Address</label>
                    <textarea name="contract_data[employee_address]" class="form-textarea" rows="2" placeholder="Full mailing address"></textarea>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Create Contract
                </button>
                <a href="?page=employee_contracts&tab=list" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php elseif ($active_tab === 'templates'): ?>
<!-- Contract Templates -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-file-alt"></i> Contract Templates</h3>
        <span class="badge badge-primary"><?= count($templates) ?> Templates</span>
    </div>
    <div class="card-body">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Template Name</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Variables</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($templates)): ?>
                        <?php foreach ($templates as $template): 
                            $variables = json_decode($template['variables'], true) ?? [];
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($template['name']) ?></strong></td>
                            <td><span class="role-badge"><?= ucfirst($template['template_type']) ?></span></td>
                            <td><?= htmlspecialchars($template['description'] ?? '-') ?></td>
                            <td>
                                <?php foreach (array_slice($variables, 0, 3) as $var): ?>
                                    <span class="tag"><?= htmlspecialchars($var) ?></span>
                                <?php endforeach; ?>
                                <?php if (count($variables) > 3): ?>
                                    <span class="tag">+<?= count($variables) - 3 ?> more</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($template['is_active']): ?>
                                    <span class="status-badge success">Active</span>
                                <?php else: ?>
                                    <span class="status-badge secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="empty-state">
                                <div class="empty-state-content">
                                    <i class="fas fa-file-alt"></i>
                                    <p>No templates found</p>
                                    <span>Templates are defined in the database schema</span>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="alert alert-info" style="margin-top: 24px;">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Template Management</strong>
                <p style="margin: 4px 0 0 0;">Templates are created and managed in <strong>DocuSeal</strong>. Create your contract templates with fillable fields in DocuSeal, then link them here by updating the <code>docuseal_template_id</code> in the local template records. When sending for signature, select the DocuSeal template to use.</p>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

</div>

<style>
.form-section {
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
}

.form-section:last-of-type {
    border-bottom: none;
}

.form-section h4 {
    margin-bottom: 16px;
    color: var(--text-primary);
}

.form-section h4 i {
    margin-right: 8px;
    color: var(--primary);
}

.form-hint {
    color: var(--text-secondary);
    font-size: 13px;
    margin-bottom: 16px;
}

.tag {
    display: inline-block;
    padding: 2px 8px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 4px;
    font-size: 12px;
    margin-right: 4px;
    margin-bottom: 4px;
}

.staff-info {
    display: flex;
    flex-direction: column;
}

.staff-info small {
    color: var(--text-secondary);
    font-size: 12px;
}

.text-error {
    color: var(--error) !important;
}

.text-error:hover {
    background: rgba(239, 68, 68, 0.1) !important;
}

.nextcloud-path-btn {
    position: relative;
    cursor: help;
}

.nextcloud-path-btn:hover::after {
    content: attr(data-path);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: var(--bg-card);
    border: 1px solid var(--border);
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 11px;
    white-space: nowrap;
    z-index: 100;
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}
</style>

<script>
// Filter by status
function filterByStatus(status) {
    let url = '?page=employee_contracts&tab=list';
    if (status) {
        url += '&status=' + status;
    }
    window.location.href = url;
}

// Populate form from onboarding selection
function populateFromOnboarding(select) {
    const option = select.options[select.selectedIndex];
    if (option.value) {
        document.getElementById('employee-name').value = option.dataset.name || '';
        document.getElementById('employee-email').value = option.dataset.email || '';
    }
}

// Send contract for signature
function sendForSignature(contractId) {
    if (!confirm('Send this contract for e-signature? An email will be sent to the employee with a signing link.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
    formData.append('action', 'send_for_signature');
    formData.append('contract_id', contractId);
    
    fetch('process_employee_contracts.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Contract sent for signature successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error sending contract: ' + error);
    });
}

// Resend contract email
function resendContract(contractId) {
    if (!confirm('Resend the e-signature request email?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
    formData.append('action', 'resend');
    formData.append('contract_id', contractId);
    
    fetch('process_employee_contracts.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('E-signature request resent!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error resending: ' + error);
    });
}

// Cancel contract
function cancelContract(contractId) {
    if (!confirm('Cancel this contract? This action cannot be undone.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
    formData.append('action', 'cancel');
    formData.append('contract_id', contractId);
    
    fetch('process_employee_contracts.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Contract cancelled.');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error cancelling contract: ' + error);
    });
}

// Form submission handler
document.getElementById('new-contract-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('process_employee_contracts.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Contract created successfully!');
            window.location.href = '?page=employee_contracts&tab=list';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error creating contract: ' + error);
    });
});
</script>
