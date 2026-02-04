<?php
/**
 * HR Complaints Management View
 * Handle internal and external complaints per Canada's HR best practices
 * Includes confidentiality levels, investigation tracking, and resolution management
 */

// Pagination settings
$page_num = isset($_GET['comp_page']) ? max(1, intval($_GET['comp_page'])) : 1;
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

// Active tab
$active_tab = $_GET['tab'] ?? 'list';

// Status filter
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';

// Build query with filters
$where_clauses = [];
$params = [];

if (!empty($status_filter)) {
    $where_clauses[] = "c.status = :status";
    $params['status'] = $status_filter;
}

if (!empty($type_filter)) {
    $where_clauses[] = "c.complaint_type = :type";
    $params['type'] = $type_filter;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count for pagination
try {
    $countQuery = "SELECT COUNT(*) FROM hr_complaints c $where_sql";
    $count_stmt = $pdo->prepare($countQuery);
    $count_stmt->execute($params);
    $total_complaints = $count_stmt->fetchColumn();
    $total_pages = max(1, ceil($total_complaints / $per_page));
} catch (PDOException $e) {
    $total_complaints = 0;
    $total_pages = 1;
}

// Fetch complaints with pagination
try {
    $complaintsQuery = "SELECT c.*, 
            comp.first_name as complainant_first, comp.last_name as complainant_last,
            resp.first_name as respondent_first, resp.last_name as respondent_last,
            assign.first_name as assigned_first, assign.last_name as assigned_last,
            creator.first_name as created_first, creator.last_name as created_last
        FROM hr_complaints c
        LEFT JOIN users comp ON c.complainant_id = comp.id
        LEFT JOIN users resp ON c.respondent_id = resp.id
        LEFT JOIN users assign ON c.assigned_to = assign.id
        LEFT JOIN users creator ON c.created_by = creator.id
        $where_sql
        ORDER BY c.created_at DESC
        LIMIT :limit OFFSET :offset";
    $complaints_stmt = $pdo->prepare($complaintsQuery);
    foreach ($params as $key => $value) {
        $complaints_stmt->bindValue(':' . $key, $value);
    }
    $complaints_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $complaints_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $complaints_stmt->execute();
    $complaints = $complaints_stmt->fetchAll();
} catch (PDOException $e) {
    $complaints = [];
    error_log("HR Complaints fetch error: " . $e->getMessage());
}

// Fetch complaint stats
try {
    $statsQuery = "SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status IN ('received', 'under_review', 'investigation') THEN 1 END) as active,
        COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved,
        COUNT(CASE WHEN severity IN ('high', 'critical') AND status NOT IN ('resolved', 'dismissed') THEN 1 END) as high_priority,
        COUNT(CASE WHEN complaint_type = 'internal' THEN 1 END) as internal_count,
        COUNT(CASE WHEN complaint_type = 'external' THEN 1 END) as external_count
        FROM hr_complaints";
    $stats = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total' => 0, 'active' => 0, 'resolved' => 0, 'high_priority' => 0, 'internal_count' => 0, 'external_count' => 0];
}

// Fetch staff list for dropdowns
try {
    $staffQuery = "SELECT id, first_name, last_name, role, email 
        FROM users 
        WHERE is_active = 1 AND role IN ('admin', 'coach', 'health_coach', 'team_coach', 'front_desk_staff') 
        ORDER BY first_name, last_name";
    $staff = $pdo->query($staffQuery)->fetchAll();
} catch (PDOException $e) {
    $staff = [];
}

// HR staff for assignment
$hr_staff = array_filter($staff, function($s) {
    return $s['role'] === 'admin';
});

// Complaint categories based on Canadian HR best practices
$complaint_categories = [
    'harassment' => 'Harassment',
    'discrimination' => 'Discrimination',
    'workplace_safety' => 'Workplace Safety',
    'policy_violation' => 'Policy Violation',
    'performance' => 'Performance Issues',
    'conduct' => 'Misconduct',
    'interpersonal_conflict' => 'Interpersonal Conflict',
    'other' => 'Other'
];

// Status options
$status_options = [
    'received' => 'Received',
    'under_review' => 'Under Review',
    'investigation' => 'Investigation',
    'pending_resolution' => 'Pending Resolution',
    'resolved' => 'Resolved',
    'dismissed' => 'Dismissed',
    'escalated' => 'Escalated'
];

// Severity levels
$severity_levels = [
    'low' => 'Low',
    'medium' => 'Medium',
    'high' => 'High',
    'critical' => 'Critical'
];

// Confidentiality levels
$confidentiality_levels = [
    'standard' => 'Standard',
    'restricted' => 'Restricted',
    'highly_confidential' => 'Highly Confidential'
];

// Generate next complaint number
function generateComplaintNumber($pdo) {
    $year = date('Y');
    try {
        $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(complaint_number, 8) AS UNSIGNED)) as max_num 
            FROM hr_complaints WHERE complaint_number LIKE :prefix");
        $stmt->execute(['prefix' => "COMP-$year-%"]);
        $result = $stmt->fetch();
        $next_num = ($result['max_num'] ?? 0) + 1;
        return "COMP-$year-" . str_pad($next_num, 4, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return "COMP-$year-0001";
    }
}

$next_complaint_number = generateComplaintNumber($pdo);
?>
<!-- HR Complaints View -->

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i> HR Complaints Management</h1>
        <p class="page-description">Manage internal and external workplace complaints per Canada's HR best practices. Track investigations, resolutions, and maintain confidentiality.</p>
    </div>
</div>

<!-- Info Banner -->
<div class="alert alert-info" style="margin-bottom: 24px;">
    <i class="fas fa-info-circle"></i>
    <div>
        <strong>Canadian HR Compliance</strong>
        <p style="margin: 4px 0 0 0;">This module follows Canadian employment standards and privacy requirements. All complaints are tracked with appropriate confidentiality levels and documentation for legal compliance.</p>
    </div>
</div>

<style>
/* Complaint Stats Cards */
.complaint-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.complaint-stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.complaint-stat-card .stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
.complaint-stat-card.total .stat-icon { background: rgba(107, 70, 193, 0.15); color: var(--primary); }
.complaint-stat-card.active .stat-icon { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.complaint-stat-card.resolved .stat-icon { background: rgba(16, 185, 129, 0.15); color: var(--success); }
.complaint-stat-card.high-priority .stat-icon { background: rgba(239, 68, 68, 0.15); color: var(--error); }
.complaint-stat-card .stat-info { display: flex; flex-direction: column; }
.complaint-stat-card .stat-value { font-size: 22px; font-weight: 700; color: var(--text-white); }
.complaint-stat-card .stat-label { font-size: 12px; color: var(--text-dim); }

/* Status badges */
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.status-badge.received { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.status-badge.under_review { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.status-badge.investigation { background: rgba(168, 85, 247, 0.15); color: #a855f7; }
.status-badge.pending_resolution { background: rgba(236, 72, 153, 0.15); color: #ec4899; }
.status-badge.resolved { background: rgba(16, 185, 129, 0.15); color: var(--success); }
.status-badge.dismissed { background: rgba(107, 114, 128, 0.15); color: #6b7280; }
.status-badge.escalated { background: rgba(239, 68, 68, 0.15); color: var(--error); }

/* Severity badges */
.severity-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}
.severity-badge.low { background: rgba(16, 185, 129, 0.15); color: var(--success); }
.severity-badge.medium { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.severity-badge.high { background: rgba(249, 115, 22, 0.15); color: #f97316; }
.severity-badge.critical { background: rgba(239, 68, 68, 0.15); color: var(--error); }

/* Type badges */
.type-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}
.type-badge.internal { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.type-badge.external { background: rgba(168, 85, 247, 0.15); color: #a855f7; }

/* Confidentiality indicator */
.confidential-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}
.confidential-badge.standard { background: rgba(107, 114, 128, 0.15); color: #6b7280; }
.confidential-badge.restricted { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.confidential-badge.highly_confidential { background: rgba(239, 68, 68, 0.15); color: var(--error); }

/* Filter bar */
.filter-bar {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 20px;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: flex-end;
}
.filter-group {
    flex: 1;
    min-width: 150px;
}
.filter-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-dim);
    margin-bottom: 6px;
}
.filter-select {
    width: 100%;
    padding: 8px 12px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-white);
    font-size: 13px;
}

/* Complaints table */
.complaints-table {
    width: 100%;
    border-collapse: collapse;
}
.complaints-table th,
.complaints-table td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid var(--border);
}
.complaints-table th {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-dim);
    background: var(--bg-main);
}
.complaints-table tr:hover {
    background: rgba(107, 70, 193, 0.05);
}
.complaint-number {
    font-family: monospace;
    font-weight: 600;
    color: var(--primary);
}

/* Action buttons */
.action-buttons {
    display: flex;
    gap: 8px;
}
.btn-action {
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
    border: none;
    transition: 0.2s;
}
.btn-view { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.btn-view:hover { background: rgba(59, 130, 246, 0.25); }
.btn-edit { background: rgba(107, 70, 193, 0.15); color: var(--primary); }
.btn-edit:hover { background: rgba(107, 70, 193, 0.25); }
</style>

<!-- Complaint Stats -->
<div class="complaint-stats">
    <div class="complaint-stat-card total">
        <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['total'] ?></span>
            <span class="stat-label">Total Complaints</span>
        </div>
    </div>
    <div class="complaint-stat-card active">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['active'] ?></span>
            <span class="stat-label">Active Cases</span>
        </div>
    </div>
    <div class="complaint-stat-card resolved">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['resolved'] ?></span>
            <span class="stat-label">Resolved</span>
        </div>
    </div>
    <div class="complaint-stat-card high-priority">
        <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['high_priority'] ?></span>
            <span class="stat-label">High Priority</span>
        </div>
    </div>
</div>

<!-- Tab Navigation -->
<div class="page-tabs">
    <a href="?page=complaints&tab=list" class="page-tab <?= $active_tab === 'list' ? 'active' : '' ?>">
        <i class="fas fa-list"></i> All Complaints
    </a>
    <a href="?page=complaints&tab=new" class="page-tab <?= $active_tab === 'new' ? 'active' : '' ?>">
        <i class="fas fa-plus"></i> New Complaint
    </a>
</div>

<div class="page-tab-content">
<?php if ($active_tab === 'list'): ?>
<!-- Complaints List -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-clipboard-list"></i> Complaints Register</h3>
        <a href="?page=complaints&tab=new" class="btn btn-primary">
            <i class="fas fa-plus"></i> File New Complaint
        </a>
    </div>
    
    <!-- Filters -->
    <div class="filter-bar">
        <div class="filter-group">
            <label class="filter-label">Status</label>
            <select class="filter-select" onchange="applyFilters()" id="statusFilter">
                <option value="">All Statuses</option>
                <?php foreach ($status_options as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $status_filter === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">Type</label>
            <select class="filter-select" onchange="applyFilters()" id="typeFilter">
                <option value="">All Types</option>
                <option value="internal" <?= $type_filter === 'internal' ? 'selected' : '' ?>>Internal</option>
                <option value="external" <?= $type_filter === 'external' ? 'selected' : '' ?>>External</option>
            </select>
        </div>
        <div class="filter-group" style="flex: 0;">
            <label class="filter-label">&nbsp;</label>
            <button class="btn btn-secondary" onclick="clearFilters()">
                <i class="fas fa-times"></i> Clear
            </button>
        </div>
    </div>
    
    <div class="card-body" style="overflow-x: auto;">
        <?php if (count($complaints) > 0): ?>
        <table class="complaints-table">
            <thead>
                <tr>
                    <th>Complaint #</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Complainant</th>
                    <th>Respondent</th>
                    <th>Date Filed</th>
                    <th>Status</th>
                    <th>Severity</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($complaints as $complaint): ?>
                <tr>
                    <td>
                        <span class="complaint-number"><?= htmlspecialchars($complaint['complaint_number']) ?></span>
                        <?php if ($complaint['confidentiality_level'] !== 'standard'): ?>
                        <span class="confidential-badge <?= $complaint['confidentiality_level'] ?>">
                            <i class="fas fa-lock"></i>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="type-badge <?= $complaint['complaint_type'] ?>">
                            <?= ucfirst($complaint['complaint_type']) ?>
                        </span>
                    </td>
                    <td><?= $complaint_categories[$complaint['category']] ?? $complaint['category'] ?></td>
                    <td>
                        <?php if ($complaint['complainant_first']): ?>
                            <?= htmlspecialchars($complaint['complainant_first'] . ' ' . $complaint['complainant_last']) ?>
                        <?php elseif ($complaint['complainant_name']): ?>
                            <?= htmlspecialchars($complaint['complainant_name']) ?>
                            <small>(External)</small>
                        <?php else: ?>
                            <em>Anonymous</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($complaint['respondent_first']): ?>
                            <?= htmlspecialchars($complaint['respondent_first'] . ' ' . $complaint['respondent_last']) ?>
                        <?php elseif ($complaint['respondent_name']): ?>
                            <?= htmlspecialchars($complaint['respondent_name']) ?>
                        <?php else: ?>
                            <em>-</em>
                        <?php endif; ?>
                    </td>
                    <td><?= date('M d, Y', strtotime($complaint['complaint_date'])) ?></td>
                    <td>
                        <span class="status-badge <?= $complaint['status'] ?>">
                            <?= $status_options[$complaint['status']] ?? $complaint['status'] ?>
                        </span>
                    </td>
                    <td>
                        <span class="severity-badge <?= $complaint['severity'] ?>">
                            <?= ucfirst($complaint['severity']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-action btn-view" onclick="viewComplaint(<?= $complaint['id'] ?>)" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn-action btn-edit" onclick="editComplaint(<?= $complaint['id'] ?>)" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination" style="margin-top: 20px; display: flex; justify-content: center; gap: 8px;">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=complaints&tab=list&comp_page=<?= $i ?>&status=<?= $status_filter ?>&type=<?= $type_filter ?>" 
                   class="btn <?= $i === $page_num ? 'btn-primary' : 'btn-secondary' ?>" style="min-width: 40px;">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="empty-state" style="text-align: center; padding: 60px 20px; color: var(--text-dim);">
            <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
            <h3 style="color: var(--text-white); margin-bottom: 8px;">No Complaints Found</h3>
            <p>No complaints match your current filters, or no complaints have been filed yet.</p>
            <a href="?page=complaints&tab=new" class="btn btn-primary" style="margin-top: 16px;">
                <i class="fas fa-plus"></i> File New Complaint
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($active_tab === 'new'): ?>
<!-- New Complaint Form -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-plus-circle"></i> File New Complaint</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="process_hr_complaints.php" id="newComplaintForm" enctype="multipart/form-data">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="complaint_number" value="<?= htmlspecialchars($next_complaint_number) ?>">
            
            <!-- Complaint Information -->
            <div class="form-section">
                <h4 class="form-section-title"><i class="fas fa-info-circle"></i> Complaint Information</h4>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Complaint Number</label>
                        <input type="text" class="form-input" value="<?= htmlspecialchars($next_complaint_number) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Complaint Type *</label>
                        <select name="complaint_type" class="form-input" required onchange="toggleComplainantFields()">
                            <option value="">Select Type</option>
                            <option value="internal">Internal (Employee to Employee)</option>
                            <option value="external">External (Outside Party)</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-input" required>
                            <option value="">Select Category</option>
                            <?php foreach ($complaint_categories as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Severity *</label>
                        <select name="severity" class="form-input" required>
                            <?php foreach ($severity_levels as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $key === 'medium' ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Confidentiality Level *</label>
                        <select name="confidentiality_level" class="form-input" required>
                            <?php foreach ($confidentiality_levels as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-input">
                            <option value="low">Low</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Complainant Information -->
            <div class="form-section">
                <h4 class="form-section-title"><i class="fas fa-user"></i> Complainant Information</h4>
                
                <div id="internalComplainantFields">
                    <div class="form-group">
                        <label class="form-label">Complainant (Employee)</label>
                        <select name="complainant_id" class="form-input" id="complainantSelect">
                            <option value="">Select Employee (or leave blank for anonymous)</option>
                            <?php foreach ($staff as $employee): ?>
                                <option value="<?= $employee['id'] ?>">
                                    <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?> 
                                    (<?= ucfirst(str_replace('_', ' ', $employee['role'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div id="externalComplainantFields" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Complainant Name</label>
                            <input type="text" name="complainant_name" class="form-input" placeholder="Full name of external complainant">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Information</label>
                            <input type="text" name="complainant_contact" class="form-input" placeholder="Email or phone number">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Respondent Information -->
            <div class="form-section">
                <h4 class="form-section-title"><i class="fas fa-user-tag"></i> Respondent Information</h4>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Respondent (Employee)</label>
                        <select name="respondent_id" class="form-input">
                            <option value="">Select Employee</option>
                            <?php foreach ($staff as $employee): ?>
                                <option value="<?= $employee['id'] ?>">
                                    <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?> 
                                    (<?= ucfirst(str_replace('_', ' ', $employee['role'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Or Enter Name (if not in system)</label>
                        <input type="text" name="respondent_name" class="form-input" placeholder="Name of person complaint is about">
                    </div>
                </div>
            </div>
            
            <!-- Incident Details -->
            <div class="form-section">
                <h4 class="form-section-title"><i class="fas fa-calendar-alt"></i> Incident Details</h4>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date Filed *</label>
                        <input type="date" name="complaint_date" class="form-input" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Incident Date</label>
                        <input type="date" name="incident_date" class="form-input">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Incident Location</label>
                    <input type="text" name="incident_location" class="form-input" placeholder="Where did the incident occur?">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description of Complaint *</label>
                    <textarea name="description" class="form-input" rows="6" required placeholder="Provide a detailed description of the complaint, including what happened, who was involved, and any relevant context..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Witnesses</label>
                    <textarea name="witnesses" class="form-input" rows="3" placeholder="List any witnesses to the incident (names and contact information if available)"></textarea>
                </div>
            </div>
            
            <!-- Assignment -->
            <div class="form-section">
                <h4 class="form-section-title"><i class="fas fa-user-shield"></i> Case Assignment</h4>
                
                <div class="form-group">
                    <label class="form-label">Assign to HR Representative</label>
                    <select name="assigned_to" class="form-input">
                        <option value="">Unassigned</option>
                        <?php foreach ($hr_staff as $hr): ?>
                            <option value="<?= $hr['id'] ?>">
                                <?= htmlspecialchars($hr['first_name'] . ' ' . $hr['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-actions" style="margin-top: 24px; display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> File Complaint
                </button>
                <a href="?page=complaints&tab=list" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
</div>

<!-- View Complaint Modal -->
<div id="viewComplaintModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-clipboard-list"></i> Complaint Details</h2>
            <button class="modal-close" onclick="closeModal('viewComplaintModal')">&times;</button>
        </div>
        <div class="modal-body" id="viewComplaintContent">
            <p>Loading...</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewComplaintModal')">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<!-- Edit Complaint Modal -->
<div id="editComplaintModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Update Complaint</h2>
            <button class="modal-close" onclick="closeModal('editComplaintModal')">&times;</button>
        </div>
        <form method="POST" action="process_hr_complaints.php" id="editComplaintForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="complaint_id" id="editComplaintId">
            
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="editStatus" class="form-input">
                            <?php foreach ($status_options as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Severity</label>
                        <select name="severity" id="editSeverity" class="form-input">
                            <?php foreach ($severity_levels as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Assigned To</label>
                    <select name="assigned_to" id="editAssignedTo" class="form-input">
                        <option value="">Unassigned</option>
                        <?php foreach ($hr_staff as $hr): ?>
                            <option value="<?= $hr['id'] ?>">
                                <?= htmlspecialchars($hr['first_name'] . ' ' . $hr['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Resolution</label>
                    <textarea name="resolution" id="editResolution" class="form-input" rows="4" placeholder="Describe how the complaint was resolved..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Resolution Date</label>
                    <input type="date" name="resolution_date" id="editResolutionDate" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Corrective Actions</label>
                    <textarea name="corrective_actions" id="editCorrectiveActions" class="form-input" rows="3" placeholder="Actions taken to prevent recurrence..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Add Note</label>
                    <textarea name="new_note" class="form-input" rows="3" placeholder="Add a note to the complaint record..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editComplaintModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Toggle complainant fields based on complaint type
function toggleComplainantFields() {
    const type = document.querySelector('[name="complaint_type"]').value;
    const internalFields = document.getElementById('internalComplainantFields');
    const externalFields = document.getElementById('externalComplainantFields');
    
    if (type === 'external') {
        internalFields.style.display = 'none';
        externalFields.style.display = 'block';
    } else {
        internalFields.style.display = 'block';
        externalFields.style.display = 'none';
    }
}

// Apply filters
function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const type = document.getElementById('typeFilter').value;
    let url = '?page=complaints&tab=list';
    if (status) url += '&status=' + encodeURIComponent(status);
    if (type) url += '&type=' + encodeURIComponent(type);
    window.location.href = url;
}

// Clear filters
function clearFilters() {
    window.location.href = '?page=complaints&tab=list';
}

// View complaint details
function viewComplaint(id) {
    const modal = document.getElementById('viewComplaintModal');
    const content = document.getElementById('viewComplaintContent');
    content.innerHTML = '<p style="text-align: center;"><i class="fas fa-spinner fa-spin"></i> Loading...</p>';
    modal.classList.add('active');
    modal.style.display = 'flex';
    
    // Get CSRF token safely
    const csrfTokenEl = document.querySelector('[name="csrf_token"]');
    const csrfToken = csrfTokenEl ? csrfTokenEl.value : '';
    
    // Fetch complaint details via AJAX
    fetch('process_hr_complaints.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_details&complaint_id=' + id + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            content.innerHTML = data.html;
        } else {
            content.innerHTML = '<p class="error">Failed to load complaint details.</p>';
        }
    })
    .catch(error => {
        content.innerHTML = '<p class="error">An error occurred while loading details.</p>';
    });
}

// Edit complaint
function editComplaint(id) {
    const modal = document.getElementById('editComplaintModal');
    document.getElementById('editComplaintId').value = id;
    
    // Get CSRF token safely
    const csrfTokenEl = document.querySelector('[name="csrf_token"]');
    const csrfToken = csrfTokenEl ? csrfTokenEl.value : '';
    
    // Fetch current values
    fetch('process_hr_complaints.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_details&complaint_id=' + id + '&format=json&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.complaint) {
            const c = data.complaint;
            document.getElementById('editStatus').value = c.status || '';
            document.getElementById('editSeverity').value = c.severity || '';
            document.getElementById('editAssignedTo').value = c.assigned_to || '';
            document.getElementById('editResolution').value = c.resolution || '';
            document.getElementById('editResolutionDate').value = c.resolution_date || '';
            document.getElementById('editCorrectiveActions').value = c.corrective_actions || '';
        }
    });
    
    modal.classList.add('active');
    modal.style.display = 'flex';
}

// Close modal
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('active');
    modal.style.display = 'none';
}

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal(this.id);
        }
    });
});
</script>
