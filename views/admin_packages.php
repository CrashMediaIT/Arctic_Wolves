<?php
// views/admin_packages.php - Admin UI for package management
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once 'security.php';

// Get all packages
$packages_stmt = $pdo->query("
    SELECT p.*, 
           ag.name as age_group_name,
           sl.name as skill_level_name,
           (SELECT COUNT(*) FROM package_sessions WHERE package_id = p.id) as session_count,
           (SELECT COUNT(*) FROM user_package_credits WHERE package_id = p.id) as purchases
    FROM packages p
    LEFT JOIN age_groups ag ON p.age_group_id = ag.id
    LEFT JOIN skill_levels sl ON p.skill_level_id = sl.id
    ORDER BY p.package_type, p.created_at DESC
");
$packages = $packages_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get age groups and skill levels for form
$age_groups = $pdo->query("SELECT * FROM age_groups ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);
$skill_levels = $pdo->query("SELECT * FROM skill_levels ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);

// Get available sessions for bundled packages
$sessions = $pdo->query("
    SELECT id, title, session_type, session_date, session_time, price, arena 
    FROM sessions 
    WHERE session_date >= CURDATE() 
    ORDER BY session_date, session_time
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-packages-container">
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title"><i class="fas fa-box"></i> Package Management</h1>
            <p class="page-description">Create and manage training packages</p>
        </div>
        <button type="button" class="btn btn-primary" onclick="openPackageModal()">
            <i class="fas fa-plus"></i> Create Package
        </button>
    </div>

    <?php if (isset($_GET['status'])): ?>
        <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'error'; ?>">
            <?php 
            if ($_GET['status'] === 'success') {
                echo $_GET['action'] === 'delete' ? 'Package deleted successfully!' : 'Package saved successfully!';
            } else {
                echo 'An error occurred. Please try again.';
            }
            ?>
        </div>
    <?php endif; ?>

    <div class="packages-table">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Price</th>
                    <th>Credits/Sessions</th>
                    <th>Age/Skill</th>
                    <th>Valid Days</th>
                    <th>Status</th>
                    <th>Purchases</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packages as $pkg): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($pkg['name']); ?></strong>
                        <?php if ($pkg['description']): ?>
                            <br><small><?php echo htmlspecialchars(substr($pkg['description'], 0, 100)); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo $pkg['package_type']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $pkg['package_type'])); ?>
                        </span>
                    </td>
                    <td>$<?php echo number_format($pkg['price'], 2); ?></td>
                    <td>
                        <?php if ($pkg['package_type'] === 'credits'): ?>
                            <?php echo $pkg['credits']; ?> credits
                        <?php elseif ($pkg['package_type'] === 'camp'): ?>
                            <?php 
                            if ($pkg['camp_start_date'] && $pkg['camp_end_date']) {
                                echo date('M j', strtotime($pkg['camp_start_date'])) . ' - ' . date('M j, Y', strtotime($pkg['camp_end_date']));
                            } else {
                                echo 'No dates set';
                            }
                            ?>
                        <?php elseif ($pkg['package_type'] === 'multi_week'): ?>
                            <?php 
                            $mw_count = $pdo->prepare("SELECT COUNT(*) FROM multiweek_program_dates WHERE package_id = ?");
                            $mw_count->execute([$pkg['id']]);
                            echo $mw_count->fetchColumn() . ' sessions';
                            ?>
                        <?php else: ?>
                            <?php echo $pkg['session_count']; ?> sessions
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($pkg['age_group_name'] || $pkg['skill_level_name']): ?>
                            <?php echo htmlspecialchars($pkg['age_group_name'] ?? 'Any'); ?><br>
                            <small><?php echo htmlspecialchars($pkg['skill_level_name'] ?? 'Any'); ?></small>
                        <?php else: ?>
                            <em>All</em>
                        <?php endif; ?>
                    </td>
                    <td><?php echo in_array($pkg['package_type'], ['camp', 'multi_week']) ? 'N/A' : ($pkg['valid_days'] . ' days'); ?></td>
                    <td>
                        <span class="status-badge <?php echo $pkg['is_active'] ? 'active' : 'inactive'; ?>">
                            <?php echo $pkg['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td><?php echo $pkg['purchases']; ?></td>
                    <td class="actions">
                        <button onclick="editPackage(<?php echo htmlspecialchars(json_encode($pkg)); ?>)" 
                                class="btn-icon" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if ($pkg['package_type'] === 'bundled'): ?>
                            <button onclick="manageSessions(<?php echo $pkg['id']; ?>)" 
                                    class="btn-icon" title="Manage Sessions">
                                <i class="fas fa-list"></i>
                            </button>
                        <?php endif; ?>
                        <button onclick="deletePackage(<?php echo $pkg['id']; ?>, '<?php echo addslashes($pkg['name']); ?>')" 
                                class="btn-icon btn-danger" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($packages)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 40px;">
                        <i class="fas fa-box" style="font-size: 48px; color: #ccc;"></i>
                        <p>No packages created yet. Create your first package!</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Package Modal -->
<div id="packageModal" class="modal">
    <div class="modal-content modal-large">
        <span class="close" onclick="closePackageModal()">&times;</span>
        <h3 id="modalTitle">Create Package</h3>
        
        <form action="process_packages.php" method="POST" id="packageForm">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create" id="formAction">
            <input type="hidden" name="package_id" id="packageId">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Package Name <span class="required">*</span></label>
                    <input type="text" name="name" id="packageName" required>
                </div>
                
                <div class="form-group">
                    <label>Package Type <span class="required">*</span></label>
                    <select name="package_type" id="packageType" required onchange="togglePackageFields()">
                        <option value="credits">Session Credits (set number of sessions)</option>
                        <option value="dollar_value">Dollar Value (store credit amount)</option>
                        <option value="bundled">Bundled Sessions (pick from sessions library)</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="packageDescription" rows="3"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Price <span class="required">*</span></label>
                    <input type="number" name="price" id="packagePrice" step="0.01" min="0" required>
                </div>
                
                <div class="form-group" id="creditsGroup">
                    <label>Number of Sessions <span class="required">*</span></label>
                    <input type="number" name="credits" id="packageCredits" min="1">
                    <small style="color: #94a3b8; font-size: 12px;">How many sessions can be booked with this package</small>
                </div>
                
                <div class="form-group" id="storeCreditsGroup" style="display: none;">
                    <label>Store Credit Value ($) <span class="required">*</span></label>
                    <input type="number" name="store_credit" id="packageStoreCredit" step="0.01" min="0">
                    <small style="color: #94a3b8; font-size: 12px;">Dollar amount of store credit included</small>
                </div>
                
                <div class="form-group" id="validDaysGroup">
                    <label>Valid for (days)</label>
                    <input type="number" name="valid_days" id="packageValidDays" value="365" min="1">
                </div>
            </div>
            
            <div id="bundledNote" style="display: none; background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.3); border-radius: 8px; padding: 12px; margin-bottom: 20px;">
                <i class="fas fa-info-circle" style="color: #8B5CF6;"></i>
                <span style="color: #94a3b8;">After creating this package, use the <strong style="color: #e2e8f0;">Manage Sessions</strong> button to select specific sessions from your sessions library.</span>
            </div>
            
            <!-- Camp/Program Calendar Section -->
            <div id="campDatesSection" style="display: none;">
                <h4 style="color: #e2e8f0; margin-bottom: 16px; border-bottom: 1px solid #334155; padding-bottom: 8px;">
                    <i class="fas fa-campground"></i> Camp Dates
                </h4>
                <p style="color: #94a3b8; font-size: 13px; margin-bottom: 12px;">Click dates on the calendar to select or deselect them. Each date can have its own time and location.</p>
                <div class="form-row">
                    <div class="form-group">
                        <label>Default Daily Start Time</label>
                        <input type="time" name="daily_start_time" id="dailyStartTime" value="09:00">
                    </div>
                    <div class="form-group">
                        <label>Default Daily End Time</label>
                        <input type="time" name="daily_end_time" id="dailyEndTime" value="17:00">
                    </div>
                </div>
                <div class="arctic-calendar" id="camp-calendar">
                    <div class="arctic-cal-header">
                        <button type="button" class="arctic-cal-nav" onclick="campCalNav(-1)"><i class="fas fa-chevron-left"></i></button>
                        <span class="arctic-cal-title" id="camp-cal-title"></span>
                        <button type="button" class="arctic-cal-nav" onclick="campCalNav(1)"><i class="fas fa-chevron-right"></i></button>
                    </div>
                    <div class="arctic-cal-weekdays">
                        <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                    </div>
                    <div class="arctic-cal-days" id="camp-cal-days"></div>
                </div>
                <div id="campCalendarDatesContainer"></div>
                <p id="campCalendarDatesEmpty" style="color: #94a3b8; font-size: 13px; text-align: center; padding: 12px; display: block;"><i class="fas fa-mouse-pointer"></i> Click on dates above to add them to this camp</p>
            </div>
            
            <!-- Multi-Week Program Section -->
            <div id="multiWeekSection" style="display: none;">
                <h4 style="color: #e2e8f0; margin-bottom: 16px; border-bottom: 1px solid #334155; padding-bottom: 8px;">
                    <i class="fas fa-calendar-alt"></i> Program Dates
                </h4>
                <p style="color: #94a3b8; font-size: 13px; margin-bottom: 12px;">Click dates on the calendar to select or deselect them. Each date can have its own time and location.</p>
                <div class="form-row">
                    <div class="form-group">
                        <label>Default Start Time</label>
                        <input type="time" name="daily_start_time" id="mwStartTime" value="09:00">
                    </div>
                    <div class="form-group">
                        <label>Default End Time</label>
                        <input type="time" name="daily_end_time" id="mwEndTime" value="10:00">
                    </div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #06080b; border: 1px solid #1e293b; border-radius: 8px; margin-bottom: 16px;">
                    <div>
                        <label style="color: #fff; font-size: 14px; font-weight: 600; margin: 0; display: block;">Allow Individual Session Purchases</label>
                        <small style="color: #94a3b8; font-size: 12px;">Auto-create individual sessions so people can buy single sessions instead of the full program</small>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="allow_individual_sessions" id="allowIndividualSessions" value="1">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="arctic-calendar" id="mw-calendar">
                    <div class="arctic-cal-header">
                        <button type="button" class="arctic-cal-nav" onclick="mwCalNav(-1)"><i class="fas fa-chevron-left"></i></button>
                        <span class="arctic-cal-title" id="mw-cal-title"></span>
                        <button type="button" class="arctic-cal-nav" onclick="mwCalNav(1)"><i class="fas fa-chevron-right"></i></button>
                    </div>
                    <div class="arctic-cal-weekdays">
                        <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                    </div>
                    <div class="arctic-cal-days" id="mw-cal-days"></div>
                </div>
                <div id="mwCalendarDatesContainer"></div>
                <p id="mwCalendarDatesEmpty" style="color: #94a3b8; font-size: 13px; text-align: center; padding: 12px; display: block;"><i class="fas fa-mouse-pointer"></i> Click on dates above to add them to this program</p>
            </div>
            
            <!-- Add-Ons Section (for Camp and Multi-Week) -->
            <div id="addOnsSection" style="display: none;">
                <h4 style="color: #e2e8f0; margin-bottom: 16px; border-bottom: 1px solid #334155; padding-bottom: 8px;">
                    <i class="fas fa-puzzle-piece"></i> Add-On Options
                </h4>
                <p style="color: #94a3b8; font-size: 13px; margin-bottom: 12px;">
                    Add optional extras like meal plans, bus transportation, etc. Users can opt in or out during registration.
                </p>
                <div id="addOnRows"></div>
                <button type="button" class="btn-secondary" onclick="addAddOnRow()" style="padding: 8px 16px; font-size: 13px; margin-top: 8px;">
                    <i class="fas fa-plus"></i> Add Option
                </button>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Age Group (Optional)</label>
                    <select name="age_group_id" id="packageAgeGroup">
                        <option value="">All Ages</option>
                        <?php foreach ($age_groups as $ag): ?>
                            <option value="<?php echo $ag['id']; ?>">
                                <?php echo htmlspecialchars($ag['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Skill Level (Optional)</label>
                    <select name="skill_level_id" id="packageSkillLevel">
                        <option value="">All Levels</option>
                        <?php foreach ($skill_levels as $sl): ?>
                            <option value="<?php echo $sl['id']; ?>">
                                <?php echo htmlspecialchars($sl['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" id="packageActive" value="1" checked>
                    Active (visible to users)
                </label>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #06080b; border: 1px solid #1e293b; border-radius: 8px; margin-bottom: 20px;">
                <div>
                    <label style="color: #fff; font-size: 14px; font-weight: 600; margin: 0; display: block;">Enable Child Check-In/Check-Out</label>
                    <small style="color: #94a3b8; font-size: 12px;">Require QR code scan for child drop-off and pickup at sessions in this package</small>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="enable_child_checkin" id="packageChildCheckin" value="1">
                    <span class="toggle-slider"></span>
                </label>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closePackageModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Package</button>
            </div>
        </form>
    </div>
</div>

<!-- Sessions Modal (for bundled packages) -->
<div id="sessionsModal" class="modal">
    <div class="modal-content modal-large">
        <span class="close" onclick="closeSessionsModal()">&times;</span>
        <h3>Manage Package Sessions</h3>
        
        <form action="process_packages.php" method="POST" id="sessionsForm">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update_sessions">
            <input type="hidden" name="package_id" id="sessionsPackageId">
            
            <div class="sessions-list">
                <?php foreach ($sessions as $session): ?>
                <label class="session-item">
                    <input type="checkbox" name="session_ids[]" value="<?php echo $session['id']; ?>">
                    <div class="session-info">
                        <strong><?php echo htmlspecialchars($session['title']); ?></strong>
                        <span class="session-type"><?php echo htmlspecialchars($session['session_type']); ?></span>
                        <span class="session-date">
                            <?php echo date('M j, Y', strtotime($session['session_date'])); ?> at 
                            <?php echo date('g:i A', strtotime($session['session_time'])); ?>
                        </span>
                        <span class="session-location"><?php echo htmlspecialchars($session['arena']); ?></span>
                        <span class="session-price">$<?php echo number_format($session['price'], 2); ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeSessionsModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-sync-alt"></i> Update Sessions</button>
            </div>
        </form>
    </div>
</div>

<style>
.admin-packages-container {
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.page-header h2 {
    margin: 0;
    color: #fff;
}

.alert {
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #10b981;
    color: white;
}

.alert-error {
    background: #ef4444;
    color: white;
}

.packages-table {
    background: #0a0f16;
    border-radius: 10px;
    overflow: hidden;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: #020305;
}

th, td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid #1e293b;
}

th {
    color: #94a3b8;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

td {
    color: #e2e8f0;
}

.badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-credits {
    background: #7000a4;
    color: white;
}

.badge-bundled {
    background: #ec4899;
    color: white;
}

.badge-camp {
    background: #10b981;
    color: white;
}

.badge-multi_week {
    background: #f59e0b;
    color: white;
}

.badge-dollar_value {
    background: #3b82f6;
    color: white;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.status-badge.active {
    background: #10b981;
    color: white;
}

.status-badge.inactive {
    background: #6b7280;
    color: white;
}

.actions {
    display: flex;
    gap: 8px;
}

.btn-icon {
    background: transparent;
    border: 1px solid #334155;
    color: #94a3b8;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-icon:hover {
    background: #1e293b;
    color: #fff;
}

.btn-icon.btn-danger:hover {
    background: #ef4444;
    border-color: #ef4444;
    color: white;
}

.btn-primary {
    background: var(--primary, #7000a4);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-primary:hover {
    background: #e64400;
}

.btn-secondary {
    background: #334155;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}

.modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    overflow-y: auto;
}

.modal-content {
    background: #0a0f16;
    margin: 50px auto;
    padding: 24px;
    border-radius: 12px;
    max-width: 700px;
    position: relative;
    color: #e2e8f0;
}

.modal-large {
    max-width: 900px;
}

.close {
    position: absolute;
    right: 20px;
    top: 20px;
    font-size: 28px;
    font-weight: bold;
    color: #94a3b8;
    cursor: pointer;
}

.close:hover {
    color: #fff;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #94a3b8;
    font-weight: 600;
    font-size: 14px;
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    background: #020305;
    border: 1px solid #334155;
    border-radius: 6px;
    color: #e2e8f0;
    font-size: 14px;
}

.required {
    color: #ef4444;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 24px;
}

.sessions-list {
    max-height: 500px;
    overflow-y: auto;
    margin: 20px 0;
}

.session-item {
    display: flex;
    gap: 15px;
    padding: 16px;
    background: #020305;
    border: 1px solid #334155;
    border-radius: 8px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s;
}

.session-item:hover {
    background: #1e293b;
}

.session-item input[type="checkbox"] {
    width: 20px;
    height: 20px;
    margin-top: 5px;
}

.session-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.session-info strong {
    color: #e2e8f0;
}

.session-type,
.session-date,
.session-location,
.session-price {
    font-size: 13px;
    color: #94a3b8;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        margin: 20px;
        padding: 20px;
    }
}

.schedule-row, .addon-row, .program-date-row {
    display: grid;
    gap: 10px;
    padding: 12px;
    background: #020305;
    border: 1px solid #334155;
    border-radius: 8px;
    margin-bottom: 8px;
    align-items: end;
}

.schedule-row {
    grid-template-columns: 130px 100px 100px 1fr 1fr auto;
}

.program-date-row {
    grid-template-columns: 130px 100px 100px 1fr 1fr 100px auto;
}

.addon-row {
    grid-template-columns: 1fr 1fr 100px 80px auto;
}

.schedule-row label, .addon-row label, .program-date-row label {
    color: #94a3b8;
    font-size: 12px;
    display: block;
    margin-bottom: 4px;
}

.schedule-row input, .addon-row input, .program-date-row input {
    width: 100%;
    padding: 8px;
    background: #0a0f16;
    border: 1px solid #334155;
    border-radius: 4px;
    color: #e2e8f0;
    font-size: 13px;
}

.btn-remove-row {
    background: transparent;
    border: 1px solid #ef4444;
    color: #ef4444;
    padding: 8px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s;
}

.btn-remove-row:hover {
    background: #ef4444;
    color: white;
}

@media (max-width: 900px) {
    .schedule-row, .program-date-row, .addon-row {
        grid-template-columns: 1fr 1fr;
    }
}

/* Arctic Calendar Picker */
.arctic-calendar {
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
    max-width: 420px;
    overflow: hidden;
    box-sizing: border-box;
}
.arctic-cal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}
.arctic-cal-title {
    color: #e2e8f0;
    font-weight: 700;
    font-size: 15px;
}
.arctic-cal-nav {
    background: transparent;
    border: 1px solid #334155;
    color: #94a3b8;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.arctic-cal-nav:hover {
    background: #334155;
    color: #fff;
}
.arctic-cal-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    text-align: center;
    margin-bottom: 8px;
}
.arctic-cal-weekdays span {
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    padding: 4px 0;
}
.arctic-cal-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
}
.arctic-cal-day {
    width: 100%;
    aspect-ratio: 1;
    border: none;
    border-radius: 8px;
    background: transparent;
    color: #e2e8f0;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.arctic-cal-day:hover:not(.disabled):not(.empty) {
    background: #334155;
}
.arctic-cal-day.disabled {
    color: #334155;
    cursor: default;
}
.arctic-cal-day.empty {
    cursor: default;
}
.arctic-cal-day.today {
    border: 1px solid #6B46C1;
}
.arctic-cal-day.selected {
    background: #6B46C1;
    color: #fff;
    font-weight: 700;
}
.session-date-entry {
    background: #020305;
    border: 1px solid #334155;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 8px;
    position: relative;
}
.session-date-entry .date-label {
    color: #e2e8f0;
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 8px;
}
.session-date-entry .date-fields {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.session-date-entry .form-group {
    margin-bottom: 0;
}
.session-date-entry .form-label {
    display: block;
    color: #94a3b8;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 4px;
}
.session-date-entry .form-input {
    width: 100%;
    padding: 8px;
    background: #0a0f16;
    border: 1px solid #334155;
    border-radius: 4px;
    color: #e2e8f0;
    font-size: 13px;
}
.remove-date-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    background: transparent;
    border: 1px solid #ef4444;
    color: #ef4444;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.remove-date-btn:hover {
    background: #ef4444;
    color: white;
}
</style>

<script>
var csrfToken = document.querySelector('[name="csrf_token"]')?.value || '<?php echo generateCsrfToken(); ?>';

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

function openPackageModal() {
    document.getElementById('modalTitle').textContent = 'Create Package';
    document.getElementById('formAction').value = 'create';
    document.getElementById('packageForm').reset();
    document.getElementById('packageId').value = '';
    document.getElementById('packageActive').checked = true;
    document.getElementById('packageChildCheckin').checked = false;
    // Clear dynamic sections
    document.getElementById('addOnRows').innerHTML = '';
    // Reset calendar pickers
    campCal.clearAll();
    mwCal.clearAll();
    togglePackageFields();
    document.getElementById('packageModal').style.display = 'block';
}

function closePackageModal() {
    document.getElementById('packageModal').style.display = 'none';
}

function editPackage(pkg) {
    document.getElementById('modalTitle').textContent = 'Edit Package';
    document.getElementById('formAction').value = 'update';
    document.getElementById('packageId').value = pkg.id;
    document.getElementById('packageName').value = pkg.name;
    document.getElementById('packageType').value = pkg.package_type;
    document.getElementById('packageDescription').value = pkg.description || '';
    document.getElementById('packagePrice').value = pkg.price;
    document.getElementById('packageCredits').value = pkg.credits || '';
    document.getElementById('packageValidDays').value = pkg.valid_days;
    document.getElementById('packageAgeGroup').value = pkg.age_group_id || '';
    document.getElementById('packageSkillLevel').value = pkg.skill_level_id || '';
    document.getElementById('packageActive').checked = pkg.is_active == 1;
    document.getElementById('packageChildCheckin').checked = pkg.enable_child_checkin == 1;
    
    // Camp fields
    if (pkg.daily_start_time) document.getElementById('dailyStartTime').value = pkg.daily_start_time;
    if (pkg.daily_end_time) document.getElementById('dailyEndTime').value = pkg.daily_end_time;
    
    // Multi-week fields
    if (pkg.daily_start_time) document.getElementById('mwStartTime').value = pkg.daily_start_time;
    if (pkg.daily_end_time) document.getElementById('mwEndTime').value = pkg.daily_end_time;
    if (pkg.allow_individual_sessions) document.getElementById('allowIndividualSessions').checked = pkg.allow_individual_sessions == 1;
    
    // Reset calendars before loading
    campCal.clearAll();
    mwCal.clearAll();
    
    togglePackageFields();
    
    // Load camp schedules into calendar if camp type
    if (pkg.package_type === 'camp') {
        loadCampSchedules(pkg.id);
        loadAddOns(pkg.id);
    }
    
    // Load multi-week dates into calendar if multi_week type
    if (pkg.package_type === 'multi_week') {
        loadProgramDates(pkg.id);
        loadAddOns(pkg.id);
    }
    
    document.getElementById('packageModal').style.display = 'block';
}

// ArcticCalendar - Interactive calendar date picker for packages
function ArcticCalendar(config) {
    var self = this;
    this.calendarId = config.calendarId;
    this.daysId = config.daysId;
    this.titleId = config.titleId;
    this.datesContainerId = config.datesContainerId;
    this.emptyId = config.emptyId;
    this.fieldPrefix = config.fieldPrefix || 'program_dates';
    this.defaultStartTime = config.defaultStartTime || '09:00';
    this.defaultEndTime = config.defaultEndTime || '17:00';
    this.selectedDates = {};
    this.dateIndex = 0;
    
    var now = new Date();
    this.currentMonth = now.getMonth();
    this.currentYear = now.getFullYear();
    
    this.render = function() {
        var titleEl = document.getElementById(self.titleId);
        var daysEl = document.getElementById(self.daysId);
        if (!titleEl || !daysEl) return;
        
        var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        titleEl.textContent = months[self.currentMonth] + ' ' + self.currentYear;
        
        var firstDay = new Date(self.currentYear, self.currentMonth, 1).getDay();
        var daysInMonth = new Date(self.currentYear, self.currentMonth + 1, 0).getDate();
        var today = new Date();
        today.setHours(0,0,0,0);
        
        var html = '';
        for (var e = 0; e < firstDay; e++) {
            html += '<button type="button" class="arctic-cal-day empty" disabled></button>';
        }
        
        for (var d = 1; d <= daysInMonth; d++) {
            var dateObj = new Date(self.currentYear, self.currentMonth, d);
            var dateStr = self.formatDate(dateObj);
            var isPast = dateObj < today;
            var isToday = dateObj.getTime() === today.getTime();
            var isSelected = self.selectedDates.hasOwnProperty(dateStr);
            
            var cls = 'arctic-cal-day';
            if (isPast) cls += ' disabled';
            if (isToday) cls += ' today';
            if (isSelected) cls += ' selected';
            
            if (isPast) {
                html += '<button type="button" class="' + cls + '" disabled>' + d + '</button>';
            } else {
                html += '<button type="button" class="' + cls + '" data-date="' + dateStr + '" onclick="' + self.calendarId + 'Cal.toggleDate(\'' + dateStr + '\')">' + d + '</button>';
            }
        }
        
        daysEl.innerHTML = html;
    };
    
    this.formatDate = function(d) {
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    };
    
    this.formatDisplayDate = function(dateStr) {
        var parts = dateStr.split('-');
        var d = new Date(parts[0], parts[1] - 1, parts[2]);
        var days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return days[d.getDay()] + ', ' + months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
    };
    
    this.toggleDate = function(dateStr) {
        if (self.selectedDates.hasOwnProperty(dateStr)) {
            self.removeDateEntry(dateStr);
            delete self.selectedDates[dateStr];
        } else {
            self.dateIndex++;
            self.selectedDates[dateStr] = self.dateIndex;
            self.addDateEntry(dateStr, self.dateIndex);
        }
        self.render();
        self.updateEmpty();
    };
    
    this.addDateEntry = function(dateStr, idx) {
        var container = document.getElementById(self.datesContainerId);
        
        var entry = document.createElement('div');
        entry.className = 'session-date-entry';
        entry.setAttribute('data-date', dateStr);
        entry.innerHTML = '<input type="hidden" name="' + self.fieldPrefix + '[' + idx + '][date]" value="' + dateStr + '">' +
            '<button type="button" class="remove-date-btn" onclick="' + self.calendarId + 'Cal.toggleDate(\'' + dateStr + '\')"><i class="fas fa-times"></i></button>' +
            '<div class="date-label"><i class="fas fa-calendar-day"></i> ' + self.formatDisplayDate(dateStr) + '</div>' +
            '<div class="date-fields">' +
                '<div class="form-group" style="flex: 0 0 110px;">' +
                    '<label class="form-label" style="font-size:12px;">Start Time</label>' +
                    '<input type="time" name="' + self.fieldPrefix + '[' + idx + '][start_time]" class="form-input" value="' + self.defaultStartTime + '" style="font-size:13px;">' +
                '</div>' +
                '<div class="form-group" style="flex: 0 0 110px;">' +
                    '<label class="form-label" style="font-size:12px;">End Time</label>' +
                    '<input type="time" name="' + self.fieldPrefix + '[' + idx + '][end_time]" class="form-input" value="' + self.defaultEndTime + '" style="font-size:13px;">' +
                '</div>' +
                '<div class="form-group" style="flex: 1; min-width: 150px;">' +
                    '<label class="form-label" style="font-size:12px;">Location</label>' +
                    '<input type="text" name="' + self.fieldPrefix + '[' + idx + '][location_id]" class="form-input" placeholder="Arena / Venue" style="font-size:13px;">' +
                '</div>' +
            '</div>';
        
        // Insert sorted by date
        var entries = container.querySelectorAll('.session-date-entry');
        var inserted = false;
        for (var i = 0; i < entries.length; i++) {
            if (entries[i].getAttribute('data-date') > dateStr) {
                container.insertBefore(entry, entries[i]);
                inserted = true;
                break;
            }
        }
        if (!inserted) container.appendChild(entry);
    };
    
    this.removeDateEntry = function(dateStr) {
        var container = document.getElementById(self.datesContainerId);
        var entry = container.querySelector('.session-date-entry[data-date="' + dateStr + '"]');
        if (entry) entry.remove();
    };
    
    this.updateEmpty = function() {
        var emptyEl = document.getElementById(self.emptyId);
        if (emptyEl) {
            emptyEl.style.display = Object.keys(self.selectedDates).length === 0 ? 'block' : 'none';
        }
    };
    
    this.nav = function(dir) {
        self.currentMonth += dir;
        if (self.currentMonth > 11) { self.currentMonth = 0; self.currentYear++; }
        if (self.currentMonth < 0) { self.currentMonth = 11; self.currentYear--; }
        self.render();
    };
    
    this.clearAll = function() {
        self.selectedDates = {};
        self.dateIndex = 0;
        var container = document.getElementById(self.datesContainerId);
        if (container) container.innerHTML = '';
        self.updateEmpty();
        self.render();
    };
    
    this.addExistingDate = function(dateStr, startTime, endTime, location) {
        self.dateIndex++;
        self.selectedDates[dateStr] = self.dateIndex;
        var container = document.getElementById(self.datesContainerId);
        
        var entry = document.createElement('div');
        entry.className = 'session-date-entry';
        entry.setAttribute('data-date', dateStr);
        entry.innerHTML = '<input type="hidden" name="' + self.fieldPrefix + '[' + self.dateIndex + '][date]" value="' + dateStr + '">' +
            '<button type="button" class="remove-date-btn" onclick="' + self.calendarId + 'Cal.toggleDate(\'' + dateStr + '\')"><i class="fas fa-times"></i></button>' +
            '<div class="date-label"><i class="fas fa-calendar-day"></i> ' + self.formatDisplayDate(dateStr) + '</div>' +
            '<div class="date-fields">' +
                '<div class="form-group" style="flex: 0 0 110px;">' +
                    '<label class="form-label" style="font-size:12px;">Start Time</label>' +
                    '<input type="time" name="' + self.fieldPrefix + '[' + self.dateIndex + '][start_time]" class="form-input" value="' + (startTime || self.defaultStartTime) + '" style="font-size:13px;">' +
                '</div>' +
                '<div class="form-group" style="flex: 0 0 110px;">' +
                    '<label class="form-label" style="font-size:12px;">End Time</label>' +
                    '<input type="time" name="' + self.fieldPrefix + '[' + self.dateIndex + '][end_time]" class="form-input" value="' + (endTime || self.defaultEndTime) + '" style="font-size:13px;">' +
                '</div>' +
                '<div class="form-group" style="flex: 1; min-width: 150px;">' +
                    '<label class="form-label" style="font-size:12px;">Location</label>' +
                    '<input type="text" name="' + self.fieldPrefix + '[' + self.dateIndex + '][location_id]" class="form-input" value="' + escapeHtml(location || '') + '" placeholder="Arena / Venue" style="font-size:13px;">' +
                '</div>' +
            '</div>';
        
        var entries = container.querySelectorAll('.session-date-entry');
        var inserted = false;
        for (var i = 0; i < entries.length; i++) {
            if (entries[i].getAttribute('data-date') > dateStr) {
                container.insertBefore(entry, entries[i]);
                inserted = true;
                break;
            }
        }
        if (!inserted) container.appendChild(entry);
        self.updateEmpty();
        self.render();
    };
    
    this.render();
}

// Camp calendar instance
var campCal = new ArcticCalendar({
    calendarId: 'camp',
    daysId: 'camp-cal-days',
    titleId: 'camp-cal-title',
    datesContainerId: 'campCalendarDatesContainer',
    emptyId: 'campCalendarDatesEmpty',
    fieldPrefix: 'program_dates',
    defaultStartTime: '09:00',
    defaultEndTime: '17:00'
});
window.campCal = campCal;
function campCalNav(dir) { campCal.nav(dir); }

// Multi-week calendar instance
var mwCal = new ArcticCalendar({
    calendarId: 'mw',
    daysId: 'mw-cal-days',
    titleId: 'mw-cal-title',
    datesContainerId: 'mwCalendarDatesContainer',
    emptyId: 'mwCalendarDatesEmpty',
    fieldPrefix: 'program_dates',
    defaultStartTime: '09:00',
    defaultEndTime: '10:00'
});
window.mwCal = mwCal;
function mwCalNav(dir) { mwCal.nav(dir); }

// Legacy function stubs for backward compatibility
function generateCampDays() {}
function addScheduleRow() {}
function addProgramDate() {}

// Add add-on row
function addAddOnRow(name, desc, price, isDefault) {
    var container = document.getElementById('addOnRows');
    var idx = container.children.length;
    var row = document.createElement('div');
    row.className = 'addon-row';
    row.innerHTML = '<div><label>Name</label><input type="text" name="addon_names[]" value="' + escapeHtml(name || '') + '" placeholder="e.g. Meal Plan" required></div>' +
        '<div><label>Description</label><input type="text" name="addon_descriptions[]" value="' + escapeHtml(desc || '') + '" placeholder="Optional description"></div>' +
        '<div><label>Price ($)</label><input type="number" name="addon_prices[]" step="0.01" min="0" value="' + (price || '0') + '"></div>' +
        '<div><label>Default?</label><input type="checkbox" name="addon_defaults[' + idx + ']" value="1"' + (isDefault ? ' checked' : '') + ' style="width:auto;margin-top:8px;"></div>' +
        '<div><button type="button" class="btn-remove-row" onclick="this.closest(\'.addon-row\').remove()"><i class="fas fa-trash"></i></button></div>';
    container.appendChild(row);
}

// Load existing camp schedules into calendar via AJAX
function loadCampSchedules(packageId) {
    fetch('process_packages.php?action=get_camp_schedules&package_id=' + packageId)
        .then(function(r) { return r.json(); })
        .then(function(schedules) {
            campCal.clearAll();
            schedules.forEach(function(s) {
                campCal.addExistingDate(s.schedule_date, s.start_time, s.end_time, s.location || '');
            });
        });
}

// Load existing program dates into calendar via AJAX
function loadProgramDates(packageId) {
    fetch('process_packages.php?action=get_program_dates&package_id=' + packageId)
        .then(function(r) { return r.json(); })
        .then(function(dates) {
            mwCal.clearAll();
            dates.forEach(function(d) {
                mwCal.addExistingDate(d.session_date, d.start_time, d.end_time, d.location || '');
            });
        });
}

// Load existing add-ons via AJAX
function loadAddOns(packageId) {
    fetch('process_packages.php?action=get_camp_addons&package_id=' + packageId)
        .then(function(r) { return r.json(); })
        .then(function(addons) {
            document.getElementById('addOnRows').innerHTML = '';
            addons.forEach(function(a) {
                addAddOnRow(a.name, a.description || '', a.price, a.is_default == 1);
            });
        });
}

// HTML escape helper
function escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

function deletePackage(id, name) {
    if (!confirm('Are you sure you want to delete the package "' + name + '"?')) return;
    
    fetch('process_packages.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'action=delete&package_id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            persistToast(data.message || 'Package deleted!', 'success');
            location.reload();
        } else {
            showNotification('Error: ' + (data.message || 'Failed to delete'), 'error');
        }
    })
    .catch(function() { showNotification('An error occurred', 'error'); });
}

function togglePackageFields() {
    const type = document.getElementById('packageType').value;
    const creditsGroup = document.getElementById('creditsGroup');
    const creditsInput = document.getElementById('packageCredits');
    const storeCreditsGroup = document.getElementById('storeCreditsGroup');
    const storeCreditsInput = document.getElementById('packageStoreCredit');
    const bundledNote = document.getElementById('bundledNote');
    const campDatesSection = document.getElementById('campDatesSection');
    const multiWeekSection = document.getElementById('multiWeekSection');
    const addOnsSection = document.getElementById('addOnsSection');
    const validDaysGroup = document.getElementById('validDaysGroup');
    
    // Hide all type-specific sections
    creditsGroup.style.display = 'none';
    creditsInput.required = false;
    storeCreditsGroup.style.display = 'none';
    if (storeCreditsInput) storeCreditsInput.required = false;
    bundledNote.style.display = 'none';
    campDatesSection.style.display = 'none';
    multiWeekSection.style.display = 'none';
    addOnsSection.style.display = 'none';
    // Show valid_days by default
    if (validDaysGroup) validDaysGroup.style.display = 'block';
    
    if (type === 'credits') {
        creditsGroup.style.display = 'block';
        creditsInput.required = true;
    } else if (type === 'dollar_value') {
        storeCreditsGroup.style.display = 'block';
        if (storeCreditsInput) storeCreditsInput.required = true;
    } else if (type === 'bundled') {
        bundledNote.style.display = 'block';
    } else if (type === 'camp') {
        campDatesSection.style.display = 'block';
        addOnsSection.style.display = 'block';
        // Camps don't need expiry - they have scheduled dates
        if (validDaysGroup) validDaysGroup.style.display = 'none';
        campCal.render();
    } else if (type === 'multi_week') {
        multiWeekSection.style.display = 'block';
        addOnsSection.style.display = 'block';
        // Programs don't need expiry - they have scheduled dates
        if (validDaysGroup) validDaysGroup.style.display = 'none';
        mwCal.render();
    }
}

function manageSessions(packageId) {
    document.getElementById('sessionsPackageId').value = packageId;
    
    // Load currently selected sessions
    fetch(`process_packages.php?action=get_sessions&package_id=${packageId}`)
        .then(response => response.json())
        .then(sessionIds => {
            // Uncheck all
            document.querySelectorAll('#sessionsForm input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });
            
            // Check selected sessions
            sessionIds.forEach(id => {
                const checkbox = document.querySelector(`#sessionsForm input[value="${id}"]`);
                if (checkbox) checkbox.checked = true;
            });
            
            document.getElementById('sessionsModal').style.display = 'block';
        });
}

function closeSessionsModal() {
    document.getElementById('sessionsModal').style.display = 'none';
}

// Handle package form submission via AJAX
document.getElementById('packageForm').addEventListener('submit', function(e) {
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
            persistToast(data.message || 'Package saved successfully!', 'success');
            closePackageModal();
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

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>
