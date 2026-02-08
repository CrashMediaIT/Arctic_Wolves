<?php
/**
 * HR Onboarding Management View
 * Staff onboarding with user creation, payroll setup, equipment & perks tracking
 * Uploads to Nextcloud per Canada best practices
 */

// Pagination settings
$page_num = isset($_GET['ob_page']) ? max(1, intval($_GET['ob_page'])) : 1;
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

// Active tab
$active_tab = $_GET['tab'] ?? 'list';

// Get Google Maps API key for address autocomplete
$api_key_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'google_maps_api_key'");
$google_maps_api_key = $api_key_stmt->fetchColumn() ?: '';

// Get total count
try {
    $countQuery = "SELECT COUNT(*) FROM employee_onboarding";
    $total_onboardings = $pdo->query($countQuery)->fetchColumn();
    $total_pages = ceil($total_onboardings / $per_page);
} catch (PDOException $e) {
    $total_onboardings = 0;
    $total_pages = 1;
}

// Fetch onboarding records
try {
    $onboardingQuery = "SELECT eo.*, u.email as user_email, u.first_name as created_first, u.last_name as created_last,
            p.first_name as processor_first, p.last_name as processor_last
        FROM employee_onboarding eo
        LEFT JOIN users u ON eo.user_id = u.id
        LEFT JOIN users p ON eo.processed_by = p.id
        ORDER BY eo.created_at DESC
        LIMIT :limit OFFSET :offset";
    $ob_stmt = $pdo->prepare($onboardingQuery);
    $ob_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $ob_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $ob_stmt->execute();
    $onboardings = $ob_stmt->fetchAll();
} catch (PDOException $e) {
    $onboardings = [];
}

// Canadian provinces
$provinces = [
    'AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba',
    'NB' => 'New Brunswick', 'NL' => 'Newfoundland and Labrador', 'NS' => 'Nova Scotia',
    'NT' => 'Northwest Territories', 'NU' => 'Nunavut', 'ON' => 'Ontario',
    'PE' => 'Prince Edward Island', 'QC' => 'Quebec', 'SK' => 'Saskatchewan', 'YT' => 'Yukon'
];

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

// Equipment types
$equipmentTypes = [
    'camera' => 'Camera', 'tablet' => 'Tablet', 'laptop' => 'Laptop', 
    'phone' => 'Phone', 'uniform' => 'Uniform', 'keys' => 'Keys/Access Card',
    'access_card' => 'Access Card', 'other' => 'Other'
];

// Perk types
$perkTypes = [
    'equipment' => 'Equipment (Sticks, Pads)', 
    'clothing' => 'Clothing (Track Suit, Jacket)',
    'gear' => 'Gear', 
    'membership' => 'Membership/Discount',
    'discount' => 'Staff Discount',
    'other' => 'Other'
];
?>
<!-- HR Onboarding View -->

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-user-plus"></i> Staff Onboarding</h1>
        <p class="page-description">Onboard new coaches, health coaches, and administrators per Canada's best practices. Create accounts, setup payroll, and assign equipment and perks.</p>
    </div>
</div>

<!-- Info Banner -->
<div class="alert alert-info" style="margin-bottom: 24px;">
    <i class="fas fa-info-circle"></i>
    <div>
        <strong>Canadian Onboarding Best Practices</strong>
        <p style="margin: 4px 0 0 0;">This module follows Canadian employment standards for onboarding. Collect SIN, TD1 forms, banking details, and emergency contacts. All documents are securely uploaded to Nextcloud organized by year and employee name.</p>
    </div>
</div>

<!-- Tab Navigation -->
<div class="page-tabs">
    <a href="?page=onboarding&tab=list" class="page-tab <?= $active_tab === 'list' ? 'active' : '' ?>">
        <i class="fas fa-list"></i> All Onboardings
    </a>
    <a href="?page=onboarding&tab=new" class="page-tab <?= $active_tab === 'new' ? 'active' : '' ?>">
        <i class="fas fa-plus"></i> New Onboarding
    </a>
</div>

<div class="page-tab-content">
<?php if ($active_tab === 'list'): ?>
<!-- Onboarding List -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-clipboard-list"></i> Onboarding Records</h3>
        <span class="badge badge-primary"><?= $total_onboardings ?> Records</span>
    </div>
    <div class="card-body">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>New Staff</th>
                        <th>Role</th>
                        <th>Start Date</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Documents</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($onboardings)): ?>
                        <?php foreach($onboardings as $ob): 
                            $progress = 0;
                            $checks = ['personal_info_collected', 'banking_info_collected', 'tax_forms_completed', 
                                      'payroll_setup_completed', 'equipment_assigned', 'perks_assigned'];
                            foreach ($checks as $check) {
                                if ($ob[$check]) $progress++;
                                }
                                $progressPct = round(($progress / count($checks)) * 100);
                            ?>
                            <tr data-onboarding-id="<?= $ob['id'] ?>">
                                <td>
                                    <div class="staff-info">
                                        <strong><?= htmlspecialchars($ob['first_name'] . ' ' . $ob['last_name']) ?></strong>
                                        <small><?= htmlspecialchars($ob['email']) ?></small>
                                    </div>
                                </td>
                                <td><span class="role-badge"><?= ucfirst(str_replace('_', ' ', $ob['role'])) ?></span></td>
                                <td><?= date('M j, Y', strtotime($ob['start_date'])) ?></td>
                                <td><span class="status-badge <?= $ob['onboarding_status'] ?>"><?= ucfirst(str_replace('_', ' ', $ob['onboarding_status'])) ?></span></td>
                                <td>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: <?= $progressPct ?>%"></div>
                                        <span class="progress-text"><?= $progressPct ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if(!empty($ob['nextcloud_folder'])): ?>
                                        <span class="doc-indicator has-docs" title="Documents in Nextcloud"><i class="fas fa-cloud"></i></span>
                                    <?php else: ?>
                                        <span class="doc-indicator no-docs"><i class="fas fa-file-excel"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="?page=onboarding&tab=view&id=<?= $ob['id'] ?>" class="btn-icon" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($ob['onboarding_status'] !== 'completed'): ?>
                                        <a href="?page=onboarding&tab=edit&id=<?= $ob['id'] ?>" class="btn-icon" title="Continue Onboarding">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        <a href="?page=employee_contracts&tab=new&onboarding_id=<?= $ob['id'] ?>" class="btn-icon" title="Create Contract">
                                            <i class="fas fa-file-signature"></i>
                                        </a>
                                        <?php if(!empty($ob['nextcloud_folder'])): ?>
                                        <a href="<?= htmlspecialchars($ob['nextcloud_folder']) ?>" target="_blank" class="btn-icon" title="Open in Nextcloud">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <div class="empty-state-content">
                                        <i class="fas fa-user-plus"></i>
                                        <p>No onboarding records found</p>
                                        <span>Start by adding a new staff member</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php if($page_num > 1): ?>
                    <a href="?page=onboarding&tab=list&ob_page=<?= $page_num - 1 ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for($i = max(1, $page_num - 2); $i <= min($total_pages, $page_num + 2); $i++): ?>
                    <a href="?page=onboarding&tab=list&ob_page=<?= $i ?>" class="page-btn <?= $i === $page_num ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if($page_num < $total_pages): ?>
                    <a href="?page=onboarding&tab=list&ob_page=<?= $page_num + 1 ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab === 'new'): ?>
    <!-- New Onboarding Form -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-user-plus"></i> Start New Onboarding</h3>
            <span class="header-badge">New Staff Member</span>
        </div>
        <div class="card-body">
            <form class="termination-form" method="POST" action="process_onboarding.php" enctype="multipart/form-data" id="onboardingForm">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="create">
                
                <!-- Section 1: Basic Information -->
                <h4 class="section-title"><i class="fas fa-user"></i> Basic Information</h4>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> First Name *</label>
                        <input type="text" name="first_name" class="form-input" required placeholder="John">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> Last Name *</label>
                        <input type="text" name="last_name" class="form-input" required placeholder="Smith">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-envelope"></i> Email Address *</label>
                        <input type="email" name="email" class="form-input" required placeholder="john.smith@arcticwolves.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="tel" name="phone" class="form-input" placeholder="(604) 555-0123">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-briefcase"></i> Role *</label>
                        <select name="role" class="form-input" required>
                            <option value="">-- Select Role --</option>
                            <option value="coach">Coach</option>
                            <option value="health_coach">Health Coach</option>
                            <option value="team_coach">Team Coach</option>
                            <option value="front_desk_staff">Front Desk Staff</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-clock"></i> Employment Type *</label>
                        <select name="employee_type" class="form-input" required>
                            <option value="part_time">Part-Time</option>
                            <option value="full_time">Full-Time</option>
                            <option value="contract">Contract</option>
                            <option value="seasonal">Seasonal</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar"></i> Start Date *</label>
                        <input type="date" name="start_date" class="form-input" required value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-birthday-cake"></i> Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-id-card"></i> SIN (Last 4 digits)</label>
                        <input type="text" name="sin_last_four" class="form-input" maxlength="4" pattern="\d{4}" placeholder="1234">
                        <small class="form-hint">For verification purposes only</small>
                    </div>
                </div>

                <!-- Section 2: Address -->
                <h4 class="section-title"><i class="fas fa-home"></i> Home Address</h4>

                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label class="form-label"><i class="fas fa-road"></i> Street Address *</label>
                        <input type="text" name="street_address" class="form-input" required placeholder="123 Main Street">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label"><i class="fas fa-building"></i> Unit #</label>
                        <input type="text" name="unit_number" class="form-input" placeholder="Apt 4B">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-city"></i> City *</label>
                        <input type="text" name="city" class="form-input" required placeholder="Vancouver">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-map"></i> Province *</label>
                        <select name="province" class="form-input" required>
                            <?php foreach($provinces as $code => $name): ?>
                            <option value="<?= $code ?>" <?= $code === 'BC' ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-mail-bulk"></i> Postal Code *</label>
                        <input type="text" name="postal_code" class="form-input" required placeholder="V6B 1A1">
                    </div>
                </div>

                <!-- Section 3: Emergency Contact -->
                <h4 class="section-title"><i class="fas fa-phone-alt"></i> Emergency Contact</h4>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user-friends"></i> Contact Name</label>
                        <input type="text" name="emergency_contact_name" class="form-input" placeholder="Jane Smith">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-phone"></i> Contact Phone</label>
                        <input type="tel" name="emergency_contact_phone" class="form-input" placeholder="(604) 555-0124">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-heart"></i> Relationship</label>
                        <select name="emergency_contact_relationship" class="form-input">
                            <option value="">-- Select --</option>
                            <option value="spouse">Spouse</option>
                            <option value="parent">Parent</option>
                            <option value="sibling">Sibling</option>
                            <option value="friend">Friend</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <!-- Section 4: Account Creation -->
                <h4 class="section-title"><i class="fas fa-key"></i> System Account</h4>

                <div class="form-group">
                    <label class="checkbox-option" style="max-width: 400px;">
                        <input type="checkbox" name="create_account" value="1" checked>
                        <span><i class="fas fa-user-plus"></i> Create system account for this staff member</span>
                    </label>
                    <small class="form-hint">A temporary password will be generated and the staff member will be prompted to change it on first login.</small>
                </div>

                <!-- Section 5: Payroll Setup -->
                <h4 class="section-title"><i class="fas fa-money-check-dollar"></i> Payroll Setup</h4>

                <div class="form-group">
                    <label class="checkbox-option" style="max-width: 400px;">
                        <input type="checkbox" name="setup_payroll" value="1" id="setupPayroll">
                        <span><i class="fas fa-dollar-sign"></i> Setup payroll for this staff member</span>
                    </label>
                </div>

                <div id="payrollSection" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-briefcase"></i> Pay Type</label>
                            <select name="pay_type" class="form-input" id="payType">
                                <option value="hourly">Hourly</option>
                                <option value="salary">Salary</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-dollar-sign"></i> Pay Rate <span id="payRateLabel">(per hour)</span></label>
                            <input type="number" name="pay_rate" class="form-input" step="0.01" min="0" placeholder="25.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-calendar-alt"></i> Pay Frequency</label>
                            <select name="pay_frequency" class="form-input">
                                <option value="bi-weekly">Bi-Weekly</option>
                                <option value="weekly">Weekly</option>
                                <option value="semi-monthly">Semi-Monthly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-landmark"></i> Institution #</label>
                            <input type="text" name="institution_number" class="form-input" placeholder="001" maxlength="3">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-code-branch"></i> Transit #</label>
                            <input type="text" name="transit_number" class="form-input" placeholder="00001" maxlength="5">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-wallet"></i> Account #</label>
                            <input type="text" name="account_number" class="form-input" placeholder="1234567">
                        </div>
                    </div>
                </div>

                <!-- Section 6: Equipment -->
                <h4 class="section-title"><i class="fas fa-laptop"></i> Company Equipment</h4>
                
                <div id="equipmentSection">
                    <div class="equipment-row" data-index="0">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Equipment Type</label>
                                <select name="equipment[0][type]" class="form-input">
                                    <option value="">-- Select --</option>
                                    <?php foreach($equipmentTypes as $code => $name): ?>
                                    <option value="<?= $code ?>"><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Name/Description</label>
                                <input type="text" name="equipment[0][name]" class="form-input" placeholder="e.g., iPad Pro 12.9">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Serial #</label>
                                <input type="text" name="equipment[0][serial]" class="form-input" placeholder="Serial number">
                            </div>
                            <div class="form-group" style="flex: 0 0 60px;">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn-icon remove-equipment" title="Remove"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-secondary btn-small" id="addEquipment"><i class="fas fa-plus"></i> Add Equipment</button>

                <!-- Section 7: Perks -->
                <h4 class="section-title"><i class="fas fa-gift"></i> Staff Perks</h4>
                <p class="section-description">Assign perks such as hockey sticks, track suits, equipment, or other benefits.</p>
                
                <div id="perksSection">
                    <div class="perk-row" data-index="0">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Perk Type</label>
                                <select name="perks[0][type]" class="form-input">
                                    <option value="">-- Select --</option>
                                    <?php foreach($perkTypes as $code => $name): ?>
                                    <option value="<?= $code ?>"><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <input type="text" name="perks[0][name]" class="form-input" placeholder="e.g., Hockey Stick - CCM Ribcor">
                            </div>
                            <div class="form-group" style="flex: 0 0 100px;">
                                <label class="form-label">Qty</label>
                                <input type="number" name="perks[0][quantity]" class="form-input" value="1" min="1">
                            </div>
                            <div class="form-group" style="flex: 0 0 60px;">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn-icon remove-perk" title="Remove"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-secondary btn-small" id="addPerk"><i class="fas fa-plus"></i> Add Perk</button>

                <!-- Section 8: Documents -->
                <h4 class="section-title"><i class="fas fa-file-upload"></i> Documents</h4>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-paperclip"></i> Upload Documents</label>
                    <div class="file-upload-zone" id="onboardDocDropZone">
                        <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <p class="upload-text">Drag & drop files or click to browse</p>
                        <span class="upload-hint">ID, SIN Card, TD1 Forms, Banking Void Cheque, Certifications - Will be uploaded to Nextcloud</span>
                        <input type="file" name="documents[]" id="onboardingDocuments" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display: none;">
                        <div class="upload-buttons">
                            <button type="button" class="btn-secondary btn-small" onclick="document.getElementById('onboardingDocuments').click()">
                                <i class="fas fa-folder-open"></i> Choose Files
                            </button>
                        </div>
                        <span id="fileCount" class="file-count"></span>
                    </div>
                </div>

                <!-- Section 9: Employee Contract -->
                <h4 class="section-title"><i class="fas fa-file-signature"></i> Employee Contract</h4>
                <p class="section-description">Optionally create and send an employment contract for e-signature during onboarding.</p>

                <?php if ($docuseal_enabled): ?>
                <div class="form-group">
                    <label class="checkbox-option" style="max-width: 500px;">
                        <input type="checkbox" name="create_contract" value="1" id="createContract">
                        <span><i class="fas fa-file-signature"></i> Create and send employment contract for e-signature</span>
                    </label>
                    <small class="form-hint">The employee will receive an email with a link to sign their employment contract electronically.</small>
                </div>

                <div id="contractSection" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-file-alt"></i> DocuSeal Template *</label>
                            <select name="docuseal_template_id" id="docuseal-template" class="form-input">
                                <option value="">-- Select Contract Template --</option>
                                <?php foreach ($docuseal_templates as $dsTemplate): ?>
                                <?php if (isset($dsTemplate['id']) && isset($dsTemplate['name'])): ?>
                                <option value="<?= $dsTemplate['id'] ?>">
                                    <?= htmlspecialchars($dsTemplate['name']) ?>
                                </option>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-hint">Select the DocuSeal template to use for the employment contract.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-heading"></i> Contract Title</label>
                            <input type="text" name="contract_title" class="form-input" value="Employment Contract" placeholder="Employment Contract">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-dollar-sign"></i> Salary/Rate</label>
                            <input type="text" name="contract_salary" class="form-input" placeholder="e.g., $50,000/year or $25/hour">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-calendar-alt"></i> Pay Frequency</label>
                            <select name="contract_pay_frequency" class="form-input">
                                <option value="">-- Select --</option>
                                <option value="weekly">Weekly</option>
                                <option value="bi-weekly">Bi-Weekly</option>
                                <option value="semi-monthly">Semi-Monthly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                    </div>

                    <div class="alert-card info" style="margin-top: 10px;">
                        <i class="fas fa-info-circle"></i>
                        <div class="alert-content">
                            <p>The contract will be created with the employee's name, email, role, start date, and address pre-filled from the onboarding form above. After submission, the employee will receive an email with a signing link.</p>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert-card warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div class="alert-content">
                        <p><strong>DocuSeal Not Configured</strong> - E-signature functionality requires DocuSeal to be configured. <a href="?page=system_tools&tab=docuseal">Configure DocuSeal Settings</a></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Section 10: Notes -->
                <h4 class="section-title"><i class="fas fa-sticky-note"></i> Additional Notes</h4>

                <div class="form-group">
                    <textarea name="notes" class="form-textarea" rows="4" placeholder="Any additional notes about this onboarding..."></textarea>
                </div>

                <div class="alert-card info">
                    <i class="fas fa-info-circle"></i>
                    <div class="alert-content">
                        <p>All information collected will be stored securely. Documents will be uploaded to Nextcloud under the Onboarding directory organized by year and employee name. The complete form details will also be exported to Nextcloud for record keeping.</p>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn-secondary"><i class="fas fa-redo"></i> Reset</button>
                    <button type="submit" class="btn-primary"><i class="fas fa-user-plus"></i> Start Onboarding</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
/* Section titles */
.section-title {
    margin: 30px 0 20px 0;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
    color: var(--text-white);
    font-size: 16px;
    font-weight: 600;
}

.section-title i {
    margin-right: 10px;
    color: var(--primary);
}

.section-description {
    color: var(--text-muted);
    font-size: 13px;
    margin-bottom: 15px;
}

/* Progress bar */
.progress-bar-container {
    width: 100%;
    height: 20px;
    background: var(--bg-main);
    border-radius: 10px;
    position: relative;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    border-radius: 10px;
    transition: width 0.3s ease;
}

.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 11px;
    font-weight: 600;
    color: var(--text-white);
}

/* Equipment and perk rows */
.equipment-row, .perk-row {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
}

.equipment-row:last-child, .perk-row:last-child {
    margin-bottom: 15px;
}

/* Status badges */
.status-badge.pending { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.status-badge.in_progress { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.status-badge.completed { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.status-badge.cancelled { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

/* Tab Navigation */
.tab-navigation {
    display: flex;
    gap: 4px;
    background: var(--bg-card);
    padding: 6px;
    border-radius: 12px;
    margin-bottom: 24px;
    border: 1px solid var(--border);
    flex-wrap: wrap;
}

.tab-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 8px;
    color: var(--text);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.tab-link:hover { background: rgba(107, 70, 193, 0.1); color: var(--primary); }
.tab-link.active { background: var(--primary); color: white; }

/* File upload zone */
.file-upload-zone {
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
}

.file-upload-zone:hover {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.05);
}

.file-upload-zone .upload-icon {
    font-size: 40px;
    color: var(--primary);
    margin-bottom: 15px;
}

.file-upload-zone .upload-text {
    color: var(--text-white);
    margin-bottom: 8px;
}

.file-upload-zone .upload-hint {
    font-size: 12px;
    color: var(--text-muted);
}

.btn-small {
    padding: 8px 16px;
    font-size: 13px;
}

.form-hint {
    display: block;
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 4px;
}
</style>

<script>
// Toggle payroll section
document.getElementById('setupPayroll')?.addEventListener('change', function() {
    document.getElementById('payrollSection').style.display = this.checked ? 'block' : 'none';
});

// Toggle contract section
document.getElementById('createContract')?.addEventListener('change', function() {
    document.getElementById('contractSection').style.display = this.checked ? 'block' : 'none';
    // Make docuseal template required when checkbox is checked
    const templateSelect = document.getElementById('docuseal-template');
    if (templateSelect) {
        templateSelect.required = this.checked;
    }
});

// Pay type change
document.getElementById('payType')?.addEventListener('change', function() {
    document.getElementById('payRateLabel').textContent = this.value === 'salary' ? '(annual)' : '(per hour)';
});

// Equipment type options (generated from PHP)
const equipmentTypeOptions = `<option value="">-- Select --</option><?php foreach($equipmentTypes as $code => $name): ?><option value="<?= $code ?>"><?= $name ?></option><?php endforeach; ?>`;

// Perk type options (generated from PHP)
const perkTypeOptions = `<option value="">-- Select --</option><?php foreach($perkTypes as $code => $name): ?><option value="<?= $code ?>"><?= $name ?></option><?php endforeach; ?>`;

// Add equipment row
let equipmentIndex = 1;
document.getElementById('addEquipment')?.addEventListener('click', function() {
    const template = `
        <div class="equipment-row" data-index="${equipmentIndex}">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Equipment Type</label>
                    <select name="equipment[${equipmentIndex}][type]" class="form-input">
                        ${equipmentTypeOptions}
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Name/Description</label>
                    <input type="text" name="equipment[${equipmentIndex}][name]" class="form-input" placeholder="e.g., iPad Pro 12.9">
                </div>
                <div class="form-group">
                    <label class="form-label">Serial #</label>
                    <input type="text" name="equipment[${equipmentIndex}][serial]" class="form-input" placeholder="Serial number">
                </div>
                <div class="form-group" style="flex: 0 0 60px;">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn-icon remove-equipment" title="Remove"><i class="fas fa-times"></i></button>
                </div>
            </div>
        </div>`;
    document.getElementById('equipmentSection').insertAdjacentHTML('beforeend', template);
    equipmentIndex++;
});

// Add perk row
let perkIndex = 1;
document.getElementById('addPerk')?.addEventListener('click', function() {
    const template = `
        <div class="perk-row" data-index="${perkIndex}">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Perk Type</label>
                    <select name="perks[${perkIndex}][type]" class="form-input">
                        ${perkTypeOptions}
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="perks[${perkIndex}][name]" class="form-input" placeholder="e.g., Hockey Stick - CCM Ribcor">
                </div>
                <div class="form-group" style="flex: 0 0 100px;">
                    <label class="form-label">Qty</label>
                    <input type="number" name="perks[${perkIndex}][quantity]" class="form-input" value="1" min="1">
                </div>
                <div class="form-group" style="flex: 0 0 60px;">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn-icon remove-perk" title="Remove"><i class="fas fa-times"></i></button>
                </div>
            </div>
        </div>`;
    document.getElementById('perksSection').insertAdjacentHTML('beforeend', template);
    perkIndex++;
});

// Remove equipment/perk rows
document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-equipment')) {
        const row = e.target.closest('.equipment-row');
        if (document.querySelectorAll('.equipment-row').length > 1) {
            row.remove();
        }
    }
    if (e.target.closest('.remove-perk')) {
        const row = e.target.closest('.perk-row');
        if (document.querySelectorAll('.perk-row').length > 1) {
            row.remove();
        }
    }
});

// File upload display
document.getElementById('onboardingDocuments')?.addEventListener('change', function() {
    const count = this.files.length;
    document.getElementById('fileCount').textContent = count > 0 ? `${count} file(s) selected` : '';
});

// Drag and drop
const dropZone = document.getElementById('onboardDocDropZone');
if (dropZone) {
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        document.getElementById('onboardingDocuments').files = e.dataTransfer.files;
        document.getElementById('fileCount').textContent = `${e.dataTransfer.files.length} file(s) selected`;
    });
    dropZone.addEventListener('click', () => {
        document.getElementById('onboardingDocuments').click();
    });
}
</script>

<?php if (!empty($google_maps_api_key)): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($google_maps_api_key) ?>&libraries=places" async defer></script>
<script>
(function() {
    var MAX_INIT_ATTEMPTS = 20;
    var RETRY_DELAY_MS = 250;
    var initAttempts = 0;

    function initAddressAutocomplete() {
        if (typeof google === 'undefined' || !google.maps || !google.maps.places) return;
        document.querySelectorAll('input[name="street_address"]').forEach(function(input) {
            if (!input.dataset.autocompleteInit) {
                var autocomplete = new google.maps.places.Autocomplete(input, {
                    fields: ['formatted_address', 'address_components', 'name'],
                    types: ['address']
                });
                autocomplete.addListener('place_changed', function() {
                    var place = autocomplete.getPlace();
                    if (place.address_components) {
                        var form = input.closest('form');
                        if (!form) return;
                        var city = '', province = '', postal = '';
                        place.address_components.forEach(function(c) {
                            if (c.types.includes('locality')) city = c.long_name;
                            if (c.types.includes('administrative_area_level_1')) province = c.short_name;
                            if (c.types.includes('postal_code')) postal = c.long_name;
                        });
                        var cityInput = form.querySelector('input[name="city"]');
                        var postalInput = form.querySelector('input[name="postal_code"]');
                        var provinceSelect = form.querySelector('select[name="province"]');
                        if (cityInput && city) cityInput.value = city;
                        if (postalInput && postal) postalInput.value = postal;
                        if (provinceSelect && province) {
                            for (var i = 0; i < provinceSelect.options.length; i++) {
                                if (provinceSelect.options[i].value === province) {
                                    provinceSelect.selectedIndex = i;
                                    break;
                                }
                            }
                        }
                    }
                });
                input.dataset.autocompleteInit = 'true';
            }
        });
    }

    function tryInit() {
        if (typeof google !== 'undefined' && google.maps && google.maps.places) {
            initAddressAutocomplete();
        } else if (initAttempts < MAX_INIT_ATTEMPTS) {
            initAttempts++;
            setTimeout(tryInit, RETRY_DELAY_MS);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInit);
    } else {
        tryInit();
    }
})();
</script>
<?php endif; ?>

</div><!-- End page-tab-content -->
