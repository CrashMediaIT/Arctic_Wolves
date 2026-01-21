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
        <h2><i class="fas fa-box"></i> Package Management</h2>
        <button class="btn-primary" onclick="openPackageModal()">
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
                            <?php echo ucfirst($pkg['package_type']); ?>
                        </span>
                    </td>
                    <td>$<?php echo number_format($pkg['price'], 2); ?></td>
                    <td>
                        <?php if ($pkg['package_type'] === 'credits'): ?>
                            <?php echo $pkg['credits']; ?> credits
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
                    <td><?php echo $pkg['valid_days']; ?> days</td>
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
    <div class="modal-content">
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
                        <option value="credits">Credit Package</option>
                        <option value="bundled">Bundled Package</option>
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
                    <label>Number of Credits <span class="required">*</span></label>
                    <input type="number" name="credits" id="packageCredits" min="1">
                </div>
                
                <div class="form-group">
                    <label>Valid for (days)</label>
                    <input type="number" name="valid_days" id="packageValidDays" value="365" min="1">
                </div>
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
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closePackageModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Package</button>
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
                <button type="button" class="btn-secondary" onclick="closeSessionsModal()">Cancel</button>
                <button type="submit" class="btn-primary">Update Sessions</button>
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
    margin-bottom: 30px;
}

.page-header h2 {
    margin: 0;
    color: #fff;
}

.alert {
    padding: 15px;
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
    padding: 15px;
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
    padding: 30px;
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
    margin-top: 30px;
}

.sessions-list {
    max-height: 500px;
    overflow-y: auto;
    margin: 20px 0;
}

.session-item {
    display: flex;
    gap: 15px;
    padding: 15px;
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
</style>

<script>
function openPackageModal() {
    document.getElementById('modalTitle').textContent = 'Create Package';
    document.getElementById('formAction').value = 'create';
    document.getElementById('packageForm').reset();
    document.getElementById('packageId').value = '';
    document.getElementById('packageActive').checked = true;
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
    togglePackageFields();
    document.getElementById('packageModal').style.display = 'block';
}

function deletePackage(id, name) {
    if (confirm(`Are you sure you want to delete the package "${name}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'process_packages.php';
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = '<?php echo generateCsrfToken(); ?>';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'package_id';
        idInput.value = id;
        
        form.appendChild(csrfInput);
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function togglePackageFields() {
    const type = document.getElementById('packageType').value;
    const creditsGroup = document.getElementById('creditsGroup');
    const creditsInput = document.getElementById('packageCredits');
    
    if (type === 'credits') {
        creditsGroup.style.display = 'block';
        creditsInput.required = true;
    } else {
        creditsGroup.style.display = 'none';
        creditsInput.required = false;
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

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>
