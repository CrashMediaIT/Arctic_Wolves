<!-- Admin Business Partners View -->
<?php
$activeTab = $_GET['tab'] ?? 'partners';

// Fetch business partners
$partners = [];
$partner_contracts = [];
try {
    $partners = $pdo->query("SELECT * FROM business_partners ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $partners = [];
}

// Fetch contracts for selected partner
$selected_partner_id = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : 0;
$selected_partner = null;
if ($selected_partner_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM business_partners WHERE id = ?");
        $stmt->execute([$selected_partner_id]);
        $selected_partner = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $contracts_stmt = $pdo->prepare("SELECT * FROM partner_contracts WHERE partner_id = ? ORDER BY created_at DESC");
        $contracts_stmt->execute([$selected_partner_id]);
        $partner_contracts = $contracts_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $selected_partner = null;
        $partner_contracts = [];
    }
}
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-handshake"></i> Business Partners</h1>
        <p class="page-description">Manage business partnerships, contracts, and contact information</p>
    </div>
</div>

<!-- Tabs -->
<div class="page-tabs" style="flex-wrap: wrap;">
    <a href="?page=business_partners&tab=partners" class="page-tab <?php echo $activeTab === 'partners' ? 'active' : ''; ?>">
        <i class="fas fa-handshake"></i> Partners
    </a>
    <a href="?page=business_partners&tab=add" class="page-tab <?php echo $activeTab === 'add' ? 'active' : ''; ?>">
        <i class="fas fa-plus-circle"></i> Add Partner
    </a>
    <?php if ($selected_partner): ?>
    <a href="?page=business_partners&tab=contracts&partner_id=<?= $selected_partner_id ?>" class="page-tab <?php echo $activeTab === 'contracts' ? 'active' : ''; ?>">
        <i class="fas fa-file-contract"></i> <?= htmlspecialchars($selected_partner['company_name']) ?> Contracts
    </a>
    <?php endif; ?>
</div>

<div class="page-tab-content">
    <!-- Success/Error Messages -->
    <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <div class="alert alert-success" style="margin-bottom: 24px;">
        <i class="fas fa-check-circle"></i>
        <span><?= htmlspecialchars($_GET['message'] ?? 'Operation completed successfully!') ?></span>
        <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">&times;</button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['status']) && $_GET['status'] === 'error'): ?>
    <div class="alert alert-error" style="margin-bottom: 24px;">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= htmlspecialchars($_GET['message'] ?? 'An error occurred.') ?></span>
        <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Partners List Tab -->
    <?php if ($activeTab === 'partners'): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-building"></i> All Business Partners</h3>
            <span class="badge badge-primary"><?= count($partners) ?> Partners</span>
        </div>
        <div class="card-body">
            <?php if (empty($partners)): ?>
            <div style="text-align: center; padding: 60px 20px; color: var(--text-dim);">
                <i class="fas fa-handshake" style="font-size: 48px; margin-bottom: 16px; display: block; color: var(--text-dim);"></i>
                <p style="font-size: 16px; margin-bottom: 8px;">No business partners yet</p>
                <p style="font-size: 13px;">Click "Add Partner" to create your first partnership record.</p>
            </div>
            <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Point of Contact</th>
                            <th>Contact Email</th>
                            <th>Contact Phone</th>
                            <th>Company Phone</th>
                            <th>Status</th>
                            <th>Contracts</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partners as $partner): 
                            $contract_count = 0;
                            try {
                                $cc_stmt = $pdo->prepare("SELECT COUNT(*) FROM partner_contracts WHERE partner_id = ?");
                                $cc_stmt->execute([$partner['id']]);
                                $contract_count = $cc_stmt->fetchColumn();
                            } catch (PDOException $e) { $contract_count = 0; }
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($partner['company_name']) ?></strong>
                                <?php if ($partner['description']): ?>
                                <br><span style="color: var(--text-muted); font-size: 11px;"><?= htmlspecialchars(substr($partner['description'], 0, 60)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($partner['contact_name'] ?? '-') ?></td>
                            <td>
                                <?php if ($partner['contact_email']): ?>
                                <a href="mailto:<?= htmlspecialchars($partner['contact_email']) ?>" style="color: var(--primary-light);"><?= htmlspecialchars($partner['contact_email']) ?></a>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($partner['contact_phone'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($partner['company_phone'] ?? '-') ?></td>
                            <td>
                                <span class="badge badge-<?= $partner['status'] === 'active' ? 'success' : ($partner['status'] === 'inactive' ? 'secondary' : 'warning') ?>">
                                    <?= ucfirst($partner['status']) ?>
                                </span>
                            </td>
                            <td><span class="badge badge-primary"><?= $contract_count ?></span></td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <a href="?page=business_partners&tab=contracts&partner_id=<?= $partner['id'] ?>" class="btn btn-sm btn-primary" title="View Contracts"><i class="fas fa-file-contract"></i></a>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="editPartner(<?= htmlspecialchars(json_encode($partner), ENT_QUOTES) ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                                    <form method="POST" action="process_business_partners.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this partner and all associated contracts?')">
                                        <?php echo csrfTokenInput(); ?>
                                        <input type="hidden" name="action" value="delete_partner">
                                        <input type="hidden" name="partner_id" value="<?= $partner['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Partner Tab -->
    <?php if ($activeTab === 'add'): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Business Partner</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="process_business_partners.php">
                <?php echo csrfTokenInput(); ?>
                <input type="hidden" name="action" value="create_partner">
                
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header"><h3><i class="fas fa-building"></i> Company Information</h3></div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label class="form-label">Company Name *</label>
                                <input type="text" name="company_name" class="form-input" required placeholder="Partner company name">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Company Email</label>
                                <input type="email" name="company_email" class="form-input" placeholder="info@company.com">
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label class="form-label">Company Phone</label>
                                <input type="tel" name="company_phone" class="form-input" placeholder="(555) 123-4567">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Company Website</label>
                                <input type="url" name="company_website" class="form-input" placeholder="https://www.company.com">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Company Address</label>
                            <input type="text" name="company_address" class="form-input" placeholder="Full company address">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-input" rows="3" placeholder="Brief description of the partnership"></textarea>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header"><h3><i class="fas fa-user-tie"></i> Point of Contact</h3></div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label class="form-label">Contact Name</label>
                                <input type="text" name="contact_name" class="form-input" placeholder="Full name of point of contact">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Contact Title/Role</label>
                                <input type="text" name="contact_title" class="form-input" placeholder="e.g., Account Manager">
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label class="form-label">Contact Email</label>
                                <input type="email" name="contact_email" class="form-input" placeholder="contact@company.com">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Contact Phone</label>
                                <input type="tel" name="contact_phone" class="form-input" placeholder="(555) 987-6543">
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Partner</button>
                    <a href="?page=business_partners&tab=partners" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Contracts Tab (for selected partner) -->
    <?php if ($activeTab === 'contracts' && $selected_partner): ?>
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header">
            <div>
                <h3><i class="fas fa-building"></i> <?= htmlspecialchars($selected_partner['company_name']) ?></h3>
                <div style="margin-top: 8px; display: flex; gap: 12px; flex-wrap: wrap;">
                    <?php if ($selected_partner['contact_name']): ?>
                    <span style="color: var(--text-dim); font-size: 13px;"><i class="fas fa-user" style="color: var(--primary); margin-right: 4px;"></i> <?= htmlspecialchars($selected_partner['contact_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($selected_partner['contact_email']): ?>
                    <span style="color: var(--text-dim); font-size: 13px;"><i class="fas fa-envelope" style="color: var(--primary); margin-right: 4px;"></i> <?= htmlspecialchars($selected_partner['contact_email']) ?></span>
                    <?php endif; ?>
                    <?php if ($selected_partner['contact_phone']): ?>
                    <span style="color: var(--text-dim); font-size: 13px;"><i class="fas fa-phone" style="color: var(--primary); margin-right: 4px;"></i> <?= htmlspecialchars($selected_partner['contact_phone']) ?></span>
                    <?php endif; ?>
                    <?php if ($selected_partner['company_phone']): ?>
                    <span style="color: var(--text-dim); font-size: 13px;"><i class="fas fa-building" style="color: var(--primary); margin-right: 4px;"></i> <?= htmlspecialchars($selected_partner['company_phone']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <span class="badge badge-<?= $selected_partner['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($selected_partner['status']) ?></span>
        </div>
    </div>

    <!-- Partner Contracts List -->
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header">
            <h3><i class="fas fa-file-contract"></i> Partnership Contracts</h3>
            <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('newContractForm').style.display = document.getElementById('newContractForm').style.display === 'none' ? 'block' : 'none'">
                <i class="fas fa-plus"></i> Add Contract
            </button>
        </div>
        <div class="card-body">
            <?php if (empty($partner_contracts)): ?>
            <div style="text-align: center; padding: 40px; color: var(--text-dim);">
                <i class="fas fa-file-contract" style="font-size: 36px; margin-bottom: 12px; display: block;"></i>
                <p>No contracts found for this partner. Click "Add Contract" to create one.</p>
            </div>
            <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Contract Title</th>
                            <th>Partnership Items</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Value</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partner_contracts as $contract): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($contract['title']) ?></strong>
                                <?php if ($contract['description']): ?>
                                <br><span style="color:var(--text-muted);font-size:11px;"><?= htmlspecialchars(substr($contract['description'], 0, 80)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($contract['partnership_items']): ?>
                                <span style="color: var(--text-dim); font-size: 13px;"><?= htmlspecialchars($contract['partnership_items']) ?></span>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td><?= $contract['start_date'] ? date('M j, Y', strtotime($contract['start_date'])) : '-' ?></td>
                            <td><?= $contract['end_date'] ? date('M j, Y', strtotime($contract['end_date'])) : '-' ?></td>
                            <td style="font-weight: 700;"><?= $contract['value'] ? '$' . number_format($contract['value'], 2) : '-' ?></td>
                            <td><span class="badge badge-<?= $contract['status'] === 'active' ? 'success' : ($contract['status'] === 'expired' ? 'error' : 'secondary') ?>"><?= ucfirst($contract['status']) ?></span></td>
                            <td>
                                <form method="POST" action="process_business_partners.php" style="display:inline;" onsubmit="return confirm('Delete this contract?')">
                                    <?php echo csrfTokenInput(); ?>
                                    <input type="hidden" name="action" value="delete_contract">
                                    <input type="hidden" name="contract_id" value="<?= $contract['id'] ?>">
                                    <input type="hidden" name="partner_id" value="<?= $selected_partner_id ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- New Contract Form -->
    <div id="newContractForm" class="card" style="display: none;">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Add Partnership Contract</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="process_business_partners.php">
                <?php echo csrfTokenInput(); ?>
                <input type="hidden" name="action" value="create_contract">
                <input type="hidden" name="partner_id" value="<?= $selected_partner_id ?>">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label">Contract Title *</label>
                        <input type="text" name="title" class="form-input" required placeholder="e.g., Equipment Sponsorship 2026">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contract Value</label>
                        <input type="number" name="value" step="0.01" min="0" class="form-input" placeholder="0.00">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-input" rows="3" placeholder="Describe the partnership contract details"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Partnership Items</label>
                    <textarea name="partnership_items" class="form-input" rows="3" placeholder="What comes with this partnership? e.g., 30 hockey sticks, team jerseys, equipment discount"></textarea>
                    <p style="color: var(--text-dim); font-size: 11px; margin-top: 4px;">List all items, equipment, services, or benefits included in this partnership</p>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-input">
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="expired">Expired</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-input" rows="2" placeholder="Additional notes about this contract"></textarea>
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Contract</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('newContractForm').style.display='none'"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Edit Partner Modal -->
<div class="modal" id="editPartnerModal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Edit Business Partner</h2>
            <button class="modal-close" onclick="document.getElementById('editPartnerModal').classList.remove('active')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="process_business_partners.php">
                <?php echo csrfTokenInput(); ?>
                <input type="hidden" name="action" value="update_partner">
                <input type="hidden" name="partner_id" id="edit-partner-id" value="">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label">Company Name *</label>
                        <input type="text" name="company_name" id="edit-company-name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Company Email</label>
                        <input type="email" name="company_email" id="edit-company-email" class="form-input">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label">Company Phone</label>
                        <input type="tel" name="company_phone" id="edit-company-phone" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Company Website</label>
                        <input type="url" name="company_website" id="edit-company-website" class="form-input">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Company Address</label>
                    <input type="text" name="company_address" id="edit-company-address" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-partner-description" class="form-input" rows="2"></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label">Contact Name</label>
                        <input type="text" name="contact_name" id="edit-contact-name" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Title</label>
                        <input type="text" name="contact_title" id="edit-contact-title" class="form-input">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label">Contact Email</label>
                        <input type="email" name="contact_email" id="edit-contact-email" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Phone</label>
                        <input type="tel" name="contact_phone" id="edit-contact-phone" class="form-input">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="edit-partner-status" class="form-input">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block" style="width: 100%;"><i class="fas fa-save"></i> Save Changes</button>
            </form>
        </div>
    </div>
</div>

<script>
function editPartner(partner) {
    document.getElementById('edit-partner-id').value = partner.id;
    document.getElementById('edit-company-name').value = partner.company_name || '';
    document.getElementById('edit-company-email').value = partner.company_email || '';
    document.getElementById('edit-company-phone').value = partner.company_phone || '';
    document.getElementById('edit-company-website').value = partner.company_website || '';
    document.getElementById('edit-company-address').value = partner.company_address || '';
    document.getElementById('edit-partner-description').value = partner.description || '';
    document.getElementById('edit-contact-name').value = partner.contact_name || '';
    document.getElementById('edit-contact-title').value = partner.contact_title || '';
    document.getElementById('edit-contact-email').value = partner.contact_email || '';
    document.getElementById('edit-contact-phone').value = partner.contact_phone || '';
    document.getElementById('edit-partner-status').value = partner.status || 'active';
    document.getElementById('editPartnerModal').classList.add('active');
}
</script>
