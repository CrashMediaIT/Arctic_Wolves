<?php
/**
 * HR Payroll Management View
 * Comprehensive payroll management with Stripe integration
 * Handles payroll, banking info, tax deductions, T4s per Canada CRA standards
 */

// Pagination settings
$page_num = isset($_GET['payroll_page']) ? max(1, intval($_GET['payroll_page'])) : 1;
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

// Active tab
$active_tab = $_GET['tab'] ?? 'employees';

// Get total count for pagination
try {
    $countQuery = "SELECT COUNT(*) FROM employee_payroll";
    $total_employees = $pdo->query($countQuery)->fetchColumn();
    $total_pages = ceil($total_employees / $per_page);
} catch (PDOException $e) {
    $total_employees = 0;
    $total_pages = 1;
}

// Fetch employees on payroll with pagination
try {
    $payrollQuery = "SELECT ep.*, u.first_name, u.last_name, u.email, u.role, u.phone,
            ea.street_address, ea.city, ea.province, ea.postal_code
        FROM employee_payroll ep
        LEFT JOIN users u ON ep.user_id = u.id
        LEFT JOIN employee_addresses ea ON ep.user_id = ea.user_id AND ea.is_primary = 1
        WHERE ep.status != 'terminated'
        ORDER BY u.last_name, u.first_name
        LIMIT :limit OFFSET :offset";
    $payroll_stmt = $pdo->prepare($payrollQuery);
    $payroll_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $payroll_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $payroll_stmt->execute();
    $payroll_employees = $payroll_stmt->fetchAll();
} catch (PDOException $e) {
    $payroll_employees = [];
}

// Fetch recent pay runs
try {
    $recentPayQuery = "SELECT ph.*, u.first_name, u.last_name
        FROM payroll_history ph
        LEFT JOIN users u ON ph.user_id = u.id
        ORDER BY ph.pay_date DESC
        LIMIT 10";
    $recentPay = $pdo->query($recentPayQuery)->fetchAll();
} catch (PDOException $e) {
    $recentPay = [];
}

// Fetch current CRA rates
$currentYear = date('Y');
try {
    $craRatesQuery = "SELECT * FROM cra_tax_rates WHERE tax_year = :year";
    $cra_stmt = $pdo->prepare($craRatesQuery);
    $cra_stmt->execute(['year' => $currentYear]);
    $craRates = $cra_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $craRates = [];
}

// Get CPP and EI rates for display
$cppRate = null;
$eiRate = null;
foreach ($craRates as $rate) {
    if ($rate['rate_type'] === 'cpp') $cppRate = $rate;
    if ($rate['rate_type'] === 'ei') $eiRate = $rate;
}

// Fetch T4 records for previous tax year
try {
    $t4Query = "SELECT t4.*, u.first_name, u.last_name FROM t4_slips t4
        LEFT JOIN users u ON t4.user_id = u.id WHERE t4.tax_year = :year
        ORDER BY u.last_name, u.first_name";
    $t4_stmt = $pdo->prepare($t4Query);
    $t4_stmt->execute(['year' => $currentYear - 1]);
    $t4Slips = $t4_stmt->fetchAll();
} catch (PDOException $e) {
    $t4Slips = [];
}

// Fetch active staff for adding to payroll
try {
    $staffQuery = "SELECT u.id, u.first_name, u.last_name, u.role, u.email 
        FROM users u LEFT JOIN employee_payroll ep ON u.id = ep.user_id
        WHERE u.is_active = 1 AND u.role IN ('admin', 'coach', 'health_coach', 'team_coach')
        AND ep.id IS NULL ORDER BY u.first_name, u.last_name";
    $availableStaff = $pdo->query($staffQuery)->fetchAll();
} catch (PDOException $e) {
    $availableStaff = [];
}

// Canadian provinces
$provinces = [
    'AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba',
    'NB' => 'New Brunswick', 'NL' => 'Newfoundland and Labrador', 'NS' => 'Nova Scotia',
    'NT' => 'Northwest Territories', 'NU' => 'Nunavut', 'ON' => 'Ontario',
    'PE' => 'Prince Edward Island', 'QC' => 'Quebec', 'SK' => 'Saskatchewan', 'YT' => 'Yukon'
];
?>
<!-- HR Payroll View -->
<style>
/* Payroll Page Header - Financial Reports Hub Style */
.payroll-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 20px;
}
.payroll-page-header .page-header-content {
    display: flex;
    align-items: center;
    gap: 20px;
}
.payroll-page-header .page-header-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, var(--primary), #5a0080);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    box-shadow: 0 8px 24px rgba(107, 70, 193, 0.3);
}
.payroll-page-header .page-title {
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 4px 0;
    letter-spacing: -0.5px;
}
.payroll-page-header .page-description {
    font-size: 14px;
    color: var(--text-dim);
    margin: 0;
    max-width: 600px;
}

/* Payroll Tabs - Financial Reports Hub Style */
.payroll-tabs { display: flex; gap: 0; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px 12px 0 0; overflow: hidden; margin-bottom: -1px; flex-wrap: wrap; }
.payroll-tab { flex: 1; min-width: 120px; padding: 18px 16px; background: transparent; border: none; border-bottom: 3px solid transparent; color: var(--text-dim); font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; }
.payroll-tab:hover { background: rgba(139, 92, 246, 0.05); color: var(--text-white); }
.payroll-tab.active { background: rgba(139, 92, 246, 0.1); color: var(--primary); border-bottom-color: var(--primary); }
.payroll-tab i { font-size: 14px; }

/* Tab Content Container */
.payroll-tab-content { background: var(--bg-card); border: 1px solid var(--border); border-radius: 0 0 12px 12px; padding: 24px; }
</style>

<div class="payroll-page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-money-check-dollar"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">Payroll Management</h1>
            <p class="page-description">Manage staff payroll, banking information, tax deductions, and T4 generation. Integrated with Stripe for secure payments and Nextcloud for document storage.</p>
        </div>
    </div>
</div>

<div class="payroll-content">
    <!-- CRA Info Banner -->
    <div class="alert-card info">
        <i class="fas fa-info-circle"></i>
        <div class="alert-content">
            <h4>CRA Tax Rates (<?= $currentYear ?>)</h4>
            <p>
                <strong>CPP:</strong> <?= $cppRate ? number_format($cppRate['rate_percentage'], 2) . '% (Max: $' . number_format($cppRate['max_pensionable_earnings'], 0) . ')' : 'Not configured' ?> |
                <strong>EI:</strong> <?= $eiRate ? number_format($eiRate['rate_percentage'], 2) . '% (Max: $' . number_format($eiRate['max_insurable_earnings'], 0) . ')' : 'Not configured' ?>
                <br><small>Rates are updated automatically based on CRA standards each year.</small>
            </p>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="payroll-tabs">
        <a href="?page=payroll&tab=employees" class="payroll-tab <?= $active_tab === 'employees' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Employees
        </a>
        <a href="?page=payroll&tab=add" class="payroll-tab <?= $active_tab === 'add' ? 'active' : '' ?>">
            <i class="fas fa-user-plus"></i> Add to Payroll
        </a>
        <a href="?page=payroll&tab=run" class="payroll-tab <?= $active_tab === 'run' ? 'active' : '' ?>">
            <i class="fas fa-play"></i> Run Payroll
        </a>
        <a href="?page=payroll&tab=time_hours" class="payroll-tab <?= $active_tab === 'time_hours' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i> Time Hours
        </a>
        <a href="?page=payroll&tab=t4" class="payroll-tab <?= $active_tab === 't4' ? 'active' : '' ?>">
            <i class="fas fa-file-invoice"></i> T4 Slips
        </a>
        <a href="?page=payroll&tab=rates" class="payroll-tab <?= $active_tab === 'rates' ? 'active' : '' ?>">
            <i class="fas fa-percentage"></i> Tax Rates
        </a>
    </div>

    <?php if ($active_tab === 'employees'): ?>
    <!-- Employee List -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Employees on Payroll</h3>
            <span class="header-badge history"><?= $total_employees ?> Records</span>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table" id="payrollTable">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Role</th>
                            <th>Type</th>
                            <th>Pay Rate</th>
                            <th>Frequency</th>
                            <th>Province</th>
                            <th>CPP/EI</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($payroll_employees)): ?>
                            <?php foreach($payroll_employees as $emp): ?>
                            <tr data-payroll-id="<?= $emp['id'] ?>">
                                <td>
                                    <div class="staff-info">
                                        <strong><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></strong>
                                        <small><?= htmlspecialchars($emp['email'] ?? '') ?></small>
                                    </div>
                                </td>
                                <td><span class="role-badge"><?= ucfirst(str_replace('_', ' ', $emp['role'] ?? 'N/A')) ?></span></td>
                                <td><span class="type-badge <?= $emp['employee_type'] ?>"><?= ucfirst($emp['employee_type']) ?></span></td>
                                <td>
                                    <?php if ($emp['employee_type'] === 'salary'): ?>
                                        $<?= number_format($emp['pay_rate'], 0) ?>/yr
                                    <?php else: ?>
                                        $<?= number_format($emp['pay_rate'], 2) ?>/hr
                                    <?php endif; ?>
                                </td>
                                <td><?= ucfirst(str_replace('-', ' ', $emp['pay_frequency'])) ?></td>
                                <td><?= $emp['tax_province'] ?></td>
                                <td>
                                    <span class="tax-indicators">
                                        <?php if (!$emp['cpp_exempt']): ?><span class="tax-badge cpp">CPP</span><?php endif; ?>
                                        <?php if (!$emp['ei_exempt']): ?><span class="tax-badge ei">EI</span><?php endif; ?>
                                        <?php if ($emp['pension_enrolled']): ?><span class="tax-badge pension">Pension</span><?php endif; ?>
                                    </span>
                                </td>
                                <td><span class="status-badge <?= $emp['status'] ?>"><?= ucfirst($emp['status']) ?></span></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-icon edit-payroll" title="Edit Payroll" 
                                                data-id="<?= $emp['id'] ?>"
                                                data-user-id="<?= $emp['user_id'] ?>"
                                                data-name="<?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>"
                                                data-type="<?= $emp['employee_type'] ?>"
                                                data-rate="<?= $emp['pay_rate'] ?>"
                                                data-frequency="<?= $emp['pay_frequency'] ?>"
                                                data-province="<?= $emp['tax_province'] ?>"
                                                data-cpp-exempt="<?= $emp['cpp_exempt'] ?>"
                                                data-ei-exempt="<?= $emp['ei_exempt'] ?>"
                                                data-pension="<?= $emp['pension_enrolled'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-icon edit-banking" title="Banking Info" data-user-id="<?= $emp['user_id'] ?>">
                                            <i class="fas fa-university"></i>
                                        </button>
                                        <button class="btn-icon edit-address" title="Address" data-user-id="<?= $emp['user_id'] ?>">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </button>
                                        <button class="btn-icon remove-payroll" title="Remove from Payroll" data-id="<?= $emp['id'] ?>">
                                            <i class="fas fa-user-minus"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="empty-state">
                                    <div class="empty-state-content">
                                        <i class="fas fa-users-slash"></i>
                                        <p>No employees on payroll</p>
                                        <span>Add staff members to payroll to get started</span>
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
                    <a href="?page=payroll&tab=employees&payroll_page=<?= $page_num - 1 ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for($i = max(1, $page_num - 2); $i <= min($total_pages, $page_num + 2); $i++): ?>
                    <a href="?page=payroll&tab=employees&payroll_page=<?= $i ?>" class="page-btn <?= $i === $page_num ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if($page_num < $total_pages): ?>
                    <a href="?page=payroll&tab=employees&payroll_page=<?= $page_num + 1 ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab === 'add'): ?>
    <!-- Add to Payroll Form -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-user-plus"></i> Add Employee to Payroll</h3>
            <span class="header-badge">New Employee</span>
        </div>
        <div class="card-body">
            <?php if (empty($availableStaff)): ?>
            <div class="alert-card warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="alert-content">
                    <h4>No Available Staff</h4>
                    <p>All active staff members are already on payroll. Use the Onboarding module to add new staff members first.</p>
                </div>
            </div>
            <?php else: ?>
            <form class="termination-form" method="POST" action="process_payroll.php" id="addPayrollForm">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="add_employee">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> Staff Member *</label>
                        <select name="user_id" class="form-input" required>
                            <option value="">-- Select Staff Member --</option>
                            <?php foreach($availableStaff as $staff): ?>
                            <option value="<?= $staff['id'] ?>"><?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?> (<?= ucfirst(str_replace('_', ' ', $staff['role'])) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar"></i> Start Date *</label>
                        <input type="date" name="start_date" class="form-input" required value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-briefcase"></i> Employee Type *</label>
                        <select name="employee_type" class="form-input" required id="employeeType">
                            <option value="hourly">Hourly</option>
                            <option value="salary">Salary</option>
                            <option value="contract">Contract</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-dollar-sign"></i> Pay Rate * <span id="rateLabel">(per hour)</span></label>
                        <input type="number" name="pay_rate" class="form-input" step="0.01" min="0" required placeholder="25.00">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar-alt"></i> Pay Frequency *</label>
                        <select name="pay_frequency" class="form-input" required>
                            <option value="bi-weekly">Bi-Weekly</option>
                            <option value="weekly">Weekly</option>
                            <option value="semi-monthly">Semi-Monthly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-map-marker-alt"></i> Tax Province *</label>
                        <select name="tax_province" class="form-input" required>
                            <?php foreach($provinces as $code => $name): ?>
                            <option value="<?= $code ?>" <?= $code === 'BC' ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-percentage"></i> Tax Deductions (CRA Standards)</label>
                    <div class="checklist-grid">
                        <label class="checkbox-option">
                            <input type="checkbox" name="cpp_enabled" value="1" checked>
                            <span><i class="fas fa-landmark"></i> CPP Contributions (<?= $cppRate ? $cppRate['rate_percentage'] : '5.95' ?>%)</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="ei_enabled" value="1" checked>
                            <span><i class="fas fa-shield-alt"></i> EI Premiums (<?= $eiRate ? $eiRate['rate_percentage'] : '1.66' ?>%)</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="pension_enrolled" value="1">
                            <span><i class="fas fa-piggy-bank"></i> Company Pension Plan</span>
                        </label>
                    </div>
                </div>

                <div class="form-row" id="pensionDetails" style="display: none;">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-percentage"></i> Employee Pension Rate (%)</label>
                        <input type="number" name="pension_contribution_rate" class="form-input" step="0.01" min="0" max="100" placeholder="5.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-handshake"></i> Employer Match (%)</label>
                        <input type="number" name="employer_pension_match" class="form-input" step="0.01" min="0" max="100" placeholder="5.00">
                    </div>
                </div>

                <h4 style="margin-top: 30px; margin-bottom: 20px; color: var(--text-white);"><i class="fas fa-home" style="margin-right: 10px; color: var(--primary);"></i> Home Address</h4>
                
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
                        <select name="address_province" class="form-input" required>
                            <?php foreach($provinces as $code => $name): ?>
                            <option value="<?= $code ?>" <?= $code === 'BC' ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-mail-bulk"></i> Postal Code *</label>
                        <input type="text" name="postal_code" class="form-input" required placeholder="V6B 1A1" pattern="[A-Za-z][0-9][A-Za-z] ?[0-9][A-Za-z][0-9]">
                    </div>
                </div>

                <h4 style="margin-top: 30px; margin-bottom: 20px; color: var(--text-white);"><i class="fas fa-university" style="margin-right: 10px; color: var(--primary);"></i> Banking Information (Direct Deposit)</h4>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-landmark"></i> Institution # *</label>
                        <input type="text" name="institution_number" class="form-input" required placeholder="001" maxlength="3" pattern="\d{3}">
                        <small class="form-hint">3-digit bank code</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-code-branch"></i> Transit # *</label>
                        <input type="text" name="transit_number" class="form-input" required placeholder="00001" maxlength="5" pattern="\d{5}">
                        <small class="form-hint">5-digit branch code</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-wallet"></i> Account # *</label>
                        <input type="text" name="account_number" class="form-input" required placeholder="1234567" minlength="7" maxlength="12">
                        <small class="form-hint">7-12 digit account number</small>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-sticky-note"></i> Notes</label>
                    <textarea name="notes" class="form-textarea" rows="3" placeholder="Any additional payroll notes..."></textarea>
                </div>

                <div class="alert-card info">
                    <i class="fas fa-lock"></i>
                    <div class="alert-content">
                        <p>Banking information is encrypted and stored securely. Payments are processed through Stripe for direct deposit. Employee data will be uploaded to Nextcloud for record keeping.</p>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn-secondary"><i class="fas fa-redo"></i> Reset</button>
                    <button type="submit" class="btn-primary"><i class="fas fa-user-plus"></i> Add to Payroll</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab === 'run'): ?>
    <!-- Run Payroll -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-play-circle"></i> Run Payroll</h3>
            <span class="header-badge">Process Payments</span>
        </div>
        <div class="card-body">
            <div class="alert-card warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="alert-content">
                    <h4>Review Before Processing</h4>
                    <p>Running payroll will calculate deductions based on current CRA rates and process payments through Stripe. Ensure all employee information is up to date before proceeding.</p>
                </div>
            </div>

            <form class="termination-form" method="POST" action="process_payroll.php" id="runPayrollForm">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="run_payroll">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar-alt"></i> Pay Period Start *</label>
                        <input type="date" name="pay_period_start" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar-check"></i> Pay Period End *</label>
                        <input type="date" name="pay_period_end" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-money-bill-wave"></i> Pay Date *</label>
                        <input type="date" name="pay_date" class="form-input" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-users"></i> Employees to Include</label>
                    <div class="checklist-grid">
                        <?php foreach($payroll_employees as $emp): ?>
                        <label class="checkbox-option">
                            <input type="checkbox" name="employees[]" value="<?= $emp['user_id'] ?>" checked>
                            <span>
                                <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                <small>(<?= $emp['employee_type'] === 'salary' ? '$' . number_format($emp['pay_rate'], 0) . '/yr' : '$' . number_format($emp['pay_rate'], 2) . '/hr' ?>)</small>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="location.reload()"><i class="fas fa-times"></i> Cancel</button>
                    <button type="submit" class="btn-primary"><i class="fas fa-calculator"></i> Calculate & Preview</button>
                </div>
            </form>

            <!-- Recent Pay Runs -->
            <h4 style="margin-top: 40px; margin-bottom: 20px;"><i class="fas fa-history" style="margin-right: 10px; color: var(--primary);"></i> Recent Pay Runs</h4>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Pay Date</th>
                            <th>Period</th>
                            <th>Gross</th>
                            <th>Deductions</th>
                            <th>Net Pay</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentPay)): ?>
                            <?php foreach($recentPay as $pay): ?>
                            <tr>
                                <td><?= htmlspecialchars($pay['first_name'] . ' ' . $pay['last_name']) ?></td>
                                <td><?= date('M j, Y', strtotime($pay['pay_date'])) ?></td>
                                <td><?= date('M j', strtotime($pay['pay_period_start'])) ?> - <?= date('M j', strtotime($pay['pay_period_end'])) ?></td>
                                <td>$<?= number_format($pay['gross_pay'], 2) ?></td>
                                <td>$<?= number_format($pay['total_deductions'], 2) ?></td>
                                <td><strong>$<?= number_format($pay['net_pay'], 2) ?></strong></td>
                                <td><span class="status-badge <?= $pay['payment_status'] ?>"><?= ucfirst($pay['payment_status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <div class="empty-state-content">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                        <p>No payroll history yet</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab === 't4'): ?>
    <!-- T4 Management -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-file-invoice"></i> T4 Tax Slips</h3>
            <span class="header-badge"><?= $currentYear - 1 ?> Tax Year</span>
        </div>
        <div class="card-body">
            <div class="alert-card info">
                <i class="fas fa-info-circle"></i>
                <div class="alert-content">
                    <h4>T4 Generation</h4>
                    <p>T4 slips are generated for the previous tax year (<?= $currentYear - 1 ?>). Completed T4s will be automatically uploaded to Nextcloud under the Payroll directory organized by year and employee name.</p>
                </div>
            </div>

            <div class="form-actions" style="margin-bottom: 20px;">
                <form method="POST" action="process_payroll.php" style="display: inline;">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="generate_all_t4s">
                    <input type="hidden" name="tax_year" value="<?= $currentYear - 1 ?>">
                    <button type="submit" class="btn-primary"><i class="fas fa-file-export"></i> Generate All T4s</button>
                </form>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Tax Year</th>
                            <th>Employment Income</th>
                            <th>CPP</th>
                            <th>EI</th>
                            <th>Income Tax</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($t4Slips)): ?>
                            <?php foreach($t4Slips as $t4): ?>
                            <tr>
                                <td><?= htmlspecialchars($t4['first_name'] . ' ' . $t4['last_name']) ?></td>
                                <td><?= $t4['tax_year'] ?></td>
                                <td>$<?= number_format($t4['employment_income'], 2) ?></td>
                                <td>$<?= number_format($t4['cpp_contributions'], 2) ?></td>
                                <td>$<?= number_format($t4['ei_premiums'], 2) ?></td>
                                <td>$<?= number_format($t4['income_tax_deducted'], 2) ?></td>
                                <td><span class="status-badge <?= $t4['status'] ?>"><?= ucfirst($t4['status']) ?></span></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-icon" title="View T4"><i class="fas fa-eye"></i></button>
                                        <button class="btn-icon" title="Download PDF"><i class="fas fa-download"></i></button>
                                        <?php if (!empty($t4['nextcloud_path'])): ?>
                                        <a href="<?= htmlspecialchars($t4['nextcloud_path']) ?>" target="_blank" class="btn-icon" title="Open in Nextcloud">
                                            <i class="fas fa-cloud"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <div class="empty-state-content">
                                        <i class="fas fa-file-invoice"></i>
                                        <p>No T4 slips for <?= $currentYear - 1 ?></p>
                                        <span>Generate T4s after running payroll throughout the year</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab === 'time_hours'): ?>
    <!-- Time Tracking Hours for Payroll -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-clock"></i> Time Tracking Hours</h3>
            <span class="header-badge info">Integrated with Time Tracking</span>
        </div>
        <div class="card-body">
            <div class="alert-card info">
                <i class="fas fa-info-circle"></i>
                <div class="alert-content">
                    <h4>Automatic Hours Calculation</h4>
                    <p>Hours from the front desk staff time tracking system are automatically available for payroll. Select a pay period to view and sync hours for hourly employees.</p>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 25px 0;">
                <div class="form-group">
                    <label class="form-label">Pay Period Start</label>
                    <input type="date" id="timeHoursStart" class="form-input" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Pay Period End</label>
                    <input type="date" id="timeHoursEnd" class="form-input" value="<?= date('Y-m-t') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn-primary" onclick="loadTimeHours()" style="width: 100%;">
                        <i class="fas fa-search"></i> Load Hours
                    </button>
                </div>
            </div>

            <div id="timeHoursResults" style="display: none;">
                <h4 style="margin: 20px 0 15px;"><i class="fas fa-users" style="margin-right: 10px; color: var(--primary);"></i> Staff Hours Summary</h4>
                <div class="table-container">
                    <table class="data-table" id="timeHoursTable">
                        <thead>
                            <tr>
                                <th>Staff Member</th>
                                <th>Shifts Worked</th>
                                <th>Total Hours</th>
                                <th>Hourly Rate</th>
                                <th>Gross Pay</th>
                            </tr>
                        </thead>
                        <tbody id="timeHoursBody">
                            <!-- Populated by JavaScript -->
                        </tbody>
                        <tfoot>
                            <tr style="background: var(--bg); font-weight: bold;">
                                <td>Total</td>
                                <td id="totalShifts">0</td>
                                <td id="totalHours">0.00</td>
                                <td>-</td>
                                <td id="totalGross">$0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div style="margin-top: 20px; display: flex; gap: 12px;">
                    <button type="button" class="btn-primary" onclick="applyHoursToPayroll()">
                        <i class="fas fa-sync"></i> Apply to Current Payroll Run
                    </button>
                    <a href="?page=hr_time_tracking" class="btn-secondary">
                        <i class="fas fa-external-link-alt"></i> View Detailed Reports
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    function loadTimeHours() {
        const startDate = document.getElementById('timeHoursStart').value;
        const endDate = document.getElementById('timeHoursEnd').value;
        const csrfToken = <?= json_encode($_SESSION['csrf_token'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        fetch('process_time_tracking.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'calculate_payroll_hours',
                start_date: startDate,
                end_date: endDate,
                staff_id: 'all',
                csrf_token: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('timeHoursResults').style.display = 'block';
                
                let html = '';
                let totalShifts = 0;
                let totalHours = 0;
                let totalGross = 0;

                data.staff_breakdown.forEach(staff => {
                    const hourlyRate = 15.50; // Default rate - would be fetched from payroll
                    const gross = staff.hours * hourlyRate;
                    totalShifts += staff.shifts;
                    totalHours += staff.hours;
                    totalGross += gross;

                    html += `<tr>
                        <td><strong>${staff.name}</strong></td>
                        <td>${staff.shifts}</td>
                        <td>${staff.hours.toFixed(2)} hrs</td>
                        <td>$${hourlyRate.toFixed(2)}/hr</td>
                        <td>$${gross.toFixed(2)}</td>
                    </tr>`;
                });

                document.getElementById('timeHoursBody').innerHTML = html || '<tr><td colspan="5" style="text-align: center; padding: 30px;">No time tracking data for this period</td></tr>';
                document.getElementById('totalShifts').textContent = totalShifts;
                document.getElementById('totalHours').textContent = totalHours.toFixed(2) + ' hrs';
                document.getElementById('totalGross').textContent = '$' + totalGross.toFixed(2);
            } else {
                alert(data.message || 'Failed to load hours');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while loading hours');
        });
    }

    function applyHoursToPayroll() {
        if (!confirm('Apply these hours to the current payroll run? This will update the hours worked for hourly employees.')) {
            return;
        }
        
        const startDate = document.getElementById('timeHoursStart').value;
        const endDate = document.getElementById('timeHoursEnd').value;
        const csrfToken = <?= json_encode($_SESSION['csrf_token'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        fetch('process_time_tracking.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'sync_to_payroll',
                start_date: startDate,
                end_date: endDate,
                staff_id: 'all',
                csrf_token: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Hours have been applied to payroll. Go to "Run Payroll" tab to process payments.');
            } else {
                alert(data.message || 'Failed to apply hours');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
    </script>
    <?php endif; ?>

    <?php if ($active_tab === 'rates'): ?>
    <!-- Tax Rates Management -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-percentage"></i> CRA Tax Rates</h3>
            <span class="header-badge"><?= $currentYear ?></span>
        </div>
        <div class="card-body">
            <div class="alert-card info">
                <i class="fas fa-info-circle"></i>
                <div class="alert-content">
                    <h4>Automatic Updates</h4>
                    <p>Tax rates are configured based on CRA (Canada Revenue Agency) standards. Rates are updated annually to ensure compliance. You can manually update rates if needed.</p>
                </div>
            </div>

            <h4 style="margin-top: 20px; margin-bottom: 15px;"><i class="fas fa-flag" style="margin-right: 10px; color: var(--primary);"></i> Federal Rates</h4>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Income Range</th>
                            <th>Rate</th>
                            <th>Maximum/Exemption</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($craRates as $rate): if ($rate['province'] !== null) continue; ?>
                        <tr>
                            <td><span class="type-badge"><?= ucfirst(str_replace('_', ' ', $rate['rate_type'])) ?></span></td>
                            <td>
                                <?php if ($rate['rate_type'] === 'federal_bracket'): ?>
                                    $<?= number_format($rate['bracket_min'], 0) ?> - <?= $rate['bracket_max'] ? '$' . number_format($rate['bracket_max'], 0) : 'Unlimited' ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><strong><?= number_format($rate['rate_percentage'], 2) ?>%</strong></td>
                            <td>
                                <?php if ($rate['max_pensionable_earnings']): ?>
                                    Max: $<?= number_format($rate['max_pensionable_earnings'], 0) ?>
                                <?php elseif ($rate['max_insurable_earnings']): ?>
                                    Max: $<?= number_format($rate['max_insurable_earnings'], 0) ?>
                                <?php elseif ($rate['basic_exemption']): ?>
                                    Exempt: $<?= number_format($rate['basic_exemption'], 0) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn-icon edit-rate" title="Edit Rate" data-id="<?= $rate['id'] ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h4 style="margin-top: 30px; margin-bottom: 15px;"><i class="fas fa-map-marker-alt" style="margin-right: 10px; color: var(--primary);"></i> Provincial Rates (BC)</h4>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Income Range</th>
                            <th>Rate</th>
                            <th>Exemption</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($craRates as $rate): if ($rate['province'] !== 'BC') continue; ?>
                        <tr>
                            <td><span class="type-badge"><?= ucfirst(str_replace('_', ' ', $rate['rate_type'])) ?></span></td>
                            <td>
                                <?php if ($rate['rate_type'] === 'provincial_bracket'): ?>
                                    $<?= number_format($rate['bracket_min'], 0) ?> - <?= $rate['bracket_max'] ? '$' . number_format($rate['bracket_max'], 0) : 'Unlimited' ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><strong><?= number_format($rate['rate_percentage'], 2) ?>%</strong></td>
                            <td><?= $rate['basic_exemption'] ? '$' . number_format($rate['basic_exemption'], 0) : '-' ?></td>
                            <td>
                                <button class="btn-icon edit-rate" title="Edit Rate" data-id="<?= $rate['id'] ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-actions" style="margin-top: 20px;">
                <form method="POST" action="process_payroll.php" style="display: inline;">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="update_cra_rates">
                    <input type="hidden" name="tax_year" value="<?= $currentYear + 1 ?>">
                    <button type="submit" class="btn-primary"><i class="fas fa-sync"></i> Load <?= $currentYear + 1 ?> Rates</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Edit Payroll Modal -->
<div id="editPayrollModal" class="modal-overlay" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Payroll Settings</h3>
            <button class="modal-close" aria-label="Close modal" onclick="closeEditPayrollModal()">&times;</button>
        </div>
        <form method="POST" action="process_payroll.php">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="update_employee">
            <input type="hidden" name="payroll_id" id="editPayrollId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Employee</label>
                    <input type="text" id="editEmployeeName" class="form-input" readonly>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Employee Type</label>
                        <select name="employee_type" id="editEmployeeType" class="form-input">
                            <option value="hourly">Hourly</option>
                            <option value="salary">Salary</option>
                            <option value="contract">Contract</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pay Rate</label>
                        <input type="number" name="pay_rate" id="editPayRate" class="form-input" step="0.01">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Pay Frequency</label>
                        <select name="pay_frequency" id="editPayFrequency" class="form-input">
                            <option value="bi-weekly">Bi-Weekly</option>
                            <option value="weekly">Weekly</option>
                            <option value="semi-monthly">Semi-Monthly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tax Province</label>
                        <select name="tax_province" id="editTaxProvince" class="form-input">
                            <?php foreach($provinces as $code => $name): ?>
                            <option value="<?= $code ?>"><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Deductions</label>
                    <div class="checklist-grid">
                        <label class="checkbox-option">
                            <input type="checkbox" name="cpp_enabled" id="editCppEnabled" value="1">
                            <span>CPP Contributions</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="ei_enabled" id="editEiEnabled" value="1">
                            <span>EI Premiums</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="pension_enrolled" id="editPensionEnrolled" value="1">
                            <span>Company Pension</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeEditPayrollModal()">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<style>
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
.tab-link i { font-size: 14px; }

/* Tax Indicators */
.tax-indicators { display: flex; gap: 4px; flex-wrap: wrap; }
.tax-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
.tax-badge.cpp { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.tax-badge.ei { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.tax-badge.pension { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }

/* Type badges */
.type-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
.type-badge.salary { background: rgba(107, 70, 193, 0.15); color: #8B5CF6; }
.type-badge.hourly { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.type-badge.contract { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }

/* Status badges */
.status-badge.active { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.status-badge.on_leave, .status-badge.pending { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.status-badge.completed, .status-badge.generated { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.status-badge.processing { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.status-badge.draft { background: rgba(107, 114, 128, 0.15); color: #6b7280; }

/* Form hints */
.form-hint { display: block; font-size: 11px; color: var(--text-muted); margin-top: 4px; }

/* Modal styling */
.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.8); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-container { background: var(--bg-card); border-radius: 16px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; border: 1px solid var(--border); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid var(--border); }
.modal-header h3 { margin: 0; color: var(--text-white); }
.modal-close { background: none; border: none; color: var(--text); font-size: 24px; cursor: pointer; padding: 0; line-height: 1; }
.modal-close:hover { color: var(--text-white); }
.modal-body { padding: 24px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 12px; padding: 16px 24px; border-top: 1px solid var(--border); }
</style>

<script>
// Employee type change - update pay rate label
document.getElementById('employeeType')?.addEventListener('change', function() {
    const label = document.getElementById('rateLabel');
    label.textContent = this.value === 'salary' ? '(annual salary)' : '(per hour)';
});

// Pension checkbox toggle
document.querySelector('input[name="pension_enrolled"]')?.addEventListener('change', function() {
    document.getElementById('pensionDetails').style.display = this.checked ? 'flex' : 'none';
});

// Edit payroll modal
document.querySelectorAll('.edit-payroll').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('editPayrollId').value = this.dataset.id;
        document.getElementById('editEmployeeName').value = this.dataset.name;
        document.getElementById('editEmployeeType').value = this.dataset.type;
        document.getElementById('editPayRate').value = this.dataset.rate;
        document.getElementById('editPayFrequency').value = this.dataset.frequency;
        document.getElementById('editTaxProvince').value = this.dataset.province;
        document.getElementById('editCppEnabled').checked = this.dataset.cppExempt === '0';
        document.getElementById('editEiEnabled').checked = this.dataset.eiExempt === '0';
        document.getElementById('editPensionEnrolled').checked = this.dataset.pension === '1';
        document.getElementById('editPayrollModal').style.display = 'flex';
    });
});

function closeEditPayrollModal() {
    document.getElementById('editPayrollModal').style.display = 'none';
}

// Remove from payroll confirmation
document.querySelectorAll('.remove-payroll').forEach(btn => {
    btn.addEventListener('click', function() {
        if (confirm('Are you sure you want to remove this employee from payroll?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'process_payroll.php';
            form.innerHTML = `<?= csrfTokenInput() ?><input type="hidden" name="action" value="remove_employee"><input type="hidden" name="payroll_id" value="${this.dataset.id}">`;
            document.body.appendChild(form);
            form.submit();
        }
    });
});

// Close modal on outside click
document.getElementById('editPayrollModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeEditPayrollModal();
});
</script>
