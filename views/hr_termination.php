<?php
// Pagination settings
$page_num = isset($_GET['term_page']) ? max(1, intval($_GET['term_page'])) : 1;
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

// Get total count for pagination
$countQuery = "SELECT COUNT(*) FROM employee_terminations";
$total_terminations = $pdo->query($countQuery)->fetchColumn();
$total_pages = ceil($total_terminations / $per_page);

// Fetch all terminations with pagination
$terminationsQuery = "SELECT t.*, u.first_name, u.last_name, u.role, u.email,
        p.first_name as processed_first, p.last_name as processed_last
    FROM employee_terminations t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN users p ON t.processed_by = p.id
    ORDER BY t.termination_date DESC
    LIMIT :limit OFFSET :offset";
$terminations_stmt = $pdo->prepare($terminationsQuery);
$terminations_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$terminations_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$terminations_stmt->execute();
$terminations = $terminations_stmt->fetchAll();

// Fetch termination documents for modal
$docsQuery = "SELECT td.*, et.id as termination_id 
    FROM termination_documents td
    JOIN employee_terminations et ON td.termination_id = et.id";
try {
    $docs_stmt = $pdo->query($docsQuery);
    $all_docs = $docs_stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_docs = [];
}

// Fetch termination stats
$terminationStatsQuery = "SELECT 
    COUNT(*) as total_terminations,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN termination_date > CURDATE() AND status IN ('pending', 'scheduled') THEN 1 END) as upcoming
    FROM employee_terminations";
try {
    $statsResult = $pdo->query($terminationStatsQuery);
    $terminationStats = $statsResult->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $terminationStats = ['total_terminations' => 0, 'completed' => 0, 'pending' => 0, 'upcoming' => 0];
}

// Fetch active employees for the dropdown - Staff roles based on Canadian best practices
$employeesQuery = "SELECT id, first_name, last_name, role, email FROM users WHERE is_active = 1 AND role IN ('admin', 'coach', 'health_coach', 'team_coach') ORDER BY first_name, last_name";
$employees_stmt = $pdo->query($employeesQuery);
$employees = $employees_stmt->fetchAll();
?>
<!-- HR Termination View -->
<style>
/* Termination Page Header - Financial Reports Hub Style */
.termination-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 20px;
}
.termination-page-header .page-header-content {
    display: flex;
    align-items: center;
    gap: 20px;
}
.termination-page-header .page-header-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    box-shadow: 0 8px 24px rgba(239, 68, 68, 0.3);
}
.termination-page-header .page-title {
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 4px 0;
    letter-spacing: -0.5px;
}
.termination-page-header .page-description {
    font-size: 14px;
    color: var(--text-dim);
    margin: 0;
}
</style>

<div class="termination-page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-user-times"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">Staff Termination Management</h1>
            <p class="page-description">Process staff termination and offboarding procedures. Track all terminations for administration review.</p>
        </div>
    </div>
</div>

<div class="termination-content">
    <!-- Termination Stats -->
    <div class="termination-stats">
        <div class="termination-stat-card total">
            <div class="stat-icon"><i class="fas fa-user-times"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= $terminationStats['total_terminations'] ?></span>
                <span class="stat-label">Total Terminations</span>
            </div>
        </div>
        <div class="termination-stat-card completed">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= $terminationStats['completed'] ?></span>
                <span class="stat-label">Completed</span>
            </div>
        </div>
        <div class="termination-stat-card pending">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= $terminationStats['pending'] ?></span>
                <span class="stat-label">Pending</span>
            </div>
        </div>
        <div class="termination-stat-card upcoming">
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= $terminationStats['upcoming'] ?></span>
                <span class="stat-label">Upcoming</span>
            </div>
        </div>
    </div>

    <!-- Warning Notice -->
    <div class="alert-card warning">
        <i class="fas fa-exclamation-triangle"></i>
        <div class="alert-content">
            <h4>Important Notice</h4>
            <p>Staff termination is a sensitive process. Please ensure all required documentation and approvals are in place before proceeding. This action will be logged in the audit trail. Supporting documents will be uploaded to Nextcloud.</p>
        </div>
    </div>

    <!-- Termination Form -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-file-alt"></i> Process Termination</h3>
            <span class="header-badge">New Request</span>
        </div>
        <div class="card-body">
            <form class="termination-form" method="POST" action="process_coach_termination.php" enctype="multipart/form-data">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="create">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> Staff Member *</label>
                        <select name="user_id" class="form-input" required>
                            <option value="">-- Select Staff Member --</option>
                            <?php foreach($employees as $employee): ?>
                            <option value="<?= $employee['id'] ?>" data-email="<?= htmlspecialchars($employee['email']) ?>"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?> (<?= ucfirst(str_replace('_', ' ', $employee['role'])) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar"></i> Termination Date *</label>
                        <input type="date" name="termination_date" class="form-input" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-tag"></i> Termination Type *</label>
                        <select name="termination_type" class="form-input" required>
                            <option value="">-- Select Type --</option>
                            <option value="voluntary">Voluntary Resignation</option>
                            <option value="involuntary">Involuntary Termination</option>
                            <option value="retirement">Retirement</option>
                            <option value="contract_end">Contract End</option>
                            <option value="mutual">Mutual Agreement</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-question-circle"></i> Reason Category *</label>
                        <select name="reason_category" class="form-input" required>
                            <option value="">-- Select Reason --</option>
                            <option value="performance">Performance Issues</option>
                            <option value="policy">Policy Violation</option>
                            <option value="downsizing">Downsizing</option>
                            <option value="opportunity">Better Opportunity</option>
                            <option value="personal">Personal Reasons</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-align-left"></i> Detailed Reason/Notes *</label>
                    <textarea name="notes" class="form-textarea" rows="4" placeholder="Provide detailed reason for termination..." required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-hourglass-half"></i> Notice Period (days)</label>
                    <input type="number" name="notice_period" class="form-input" placeholder="14" min="0" style="max-width: 200px;">
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-tasks"></i> Offboarding Checklist</label>
                    <div class="checklist-grid">
                        <label class="checkbox-option">
                            <input type="checkbox" name="checklist[]" value="equipment">
                            <span><i class="fas fa-key"></i> Return company equipment</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="checklist[]" value="access">
                            <span><i class="fas fa-lock"></i> Revoke system access</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="checklist[]" value="paycheck">
                            <span><i class="fas fa-money-check"></i> Process final paycheck</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="checklist[]" value="pto">
                            <span><i class="fas fa-umbrella-beach"></i> Settle unused PTO</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="checklist[]" value="interview">
                            <span><i class="fas fa-comments"></i> Conduct exit interview</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="checklist[]" value="records">
                            <span><i class="fas fa-folder-open"></i> Update employee records</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="checklist[]" value="letter">
                            <span><i class="fas fa-envelope"></i> Provide termination letter</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-paperclip"></i> Supporting Documents</label>
                    <div class="file-upload-zone" id="termDocDropZone">
                        <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <p class="upload-text">Drag & drop files or click to browse</p>
                        <span class="upload-hint">Resignation letter, termination notice, etc. (PDF, DOC, Images) - Files will be uploaded to Nextcloud</span>
                        <input type="file" name="documents[]" id="terminationDocuments" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display: none;">
                        <div class="upload-buttons">
                            <button type="button" class="btn-secondary btn-small" onclick="document.getElementById('terminationDocuments').click()">
                                <i class="fas fa-folder-open"></i> Choose Files
                            </button>
                        </div>
                        <span id="fileCount" class="file-count"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-sticky-note"></i> Final Comments</label>
                    <textarea name="final_comments" class="form-textarea" rows="3" placeholder="Any additional comments or notes..."></textarea>
                </div>

                <div class="alert-card info">
                    <i class="fas fa-info-circle"></i>
                    <div class="alert-content">
                        <p>This action will archive the staff record and trigger notifications to relevant departments. The staff member's system access will be scheduled for revocation on the termination date. All documents and form data will be exported to Nextcloud.</p>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn-secondary"><i class="fas fa-redo"></i> Reset</button>
                    <button type="submit" class="btn-danger"><i class="fas fa-user-times"></i> Process Termination</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Historical Terminations -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Termination History</h3>
            <span class="header-badge history"><?= $total_terminations ?> Records</span>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table" id="terminationsTable">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Role</th>
                            <th>Termination Date</th>
                            <th>Type</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Documents</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($terminations)): ?>
                            <?php foreach($terminations as $term): 
                                $statusClass = strtolower($term['status']);
                                $termId = $term['id'];
                                $docs = isset($all_docs[$termId]) ? $all_docs[$termId] : [];
                                $checklistData = !empty($term['offboarding_checklist']) ? json_decode($term['offboarding_checklist'], true) : [];
                            ?>
                            <tr data-termination-id="<?= $termId ?>">
                                <td>
                                    <div class="staff-info">
                                        <strong><?= htmlspecialchars($term['first_name'] . ' ' . $term['last_name']) ?></strong>
                                        <small><?= htmlspecialchars($term['email'] ?? '') ?></small>
                                    </div>
                                </td>
                                <td><span class="role-badge"><?= ucfirst(str_replace('_', ' ', $term['role'] ?? 'N/A')) ?></span></td>
                                <td><?= date('M j, Y', strtotime($term['termination_date'])) ?></td>
                                <td><span class="type-badge <?= $term['termination_type'] ?>"><?= ucfirst(str_replace('_', ' ', $term['termination_type'])) ?></span></td>
                                <td><?= htmlspecialchars($term['reason_category'] ?? 'N/A') ?></td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= ucfirst($term['status']) ?></span></td>
                                <td>
                                    <?php if(!empty($term['nextcloud_folder'])): ?>
                                        <span class="doc-indicator has-docs" title="Documents in Nextcloud"><i class="fas fa-cloud"></i> <?= count($docs) ?></span>
                                    <?php elseif(count($docs) > 0): ?>
                                        <span class="doc-indicator has-docs"><i class="fas fa-file"></i> <?= count($docs) ?></span>
                                    <?php else: ?>
                                        <span class="doc-indicator no-docs"><i class="fas fa-file-excel"></i> 0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-icon view-details" title="View Details" 
                                                data-id="<?= $termId ?>"
                                                data-name="<?= htmlspecialchars($term['first_name'] . ' ' . $term['last_name']) ?>"
                                                data-email="<?= htmlspecialchars($term['email'] ?? '') ?>"
                                                data-role="<?= htmlspecialchars($term['role'] ?? '') ?>"
                                                data-date="<?= $term['termination_date'] ?>"
                                                data-type="<?= htmlspecialchars($term['termination_type']) ?>"
                                                data-reason-category="<?= htmlspecialchars($term['reason_category'] ?? '') ?>"
                                                data-reason="<?= htmlspecialchars($term['reason'] ?? $term['notes'] ?? '') ?>"
                                                data-notice="<?= $term['notice_period_days'] ?? '' ?>"
                                                data-status="<?= $term['status'] ?>"
                                                data-checklist='<?= htmlspecialchars(json_encode($checklistData)) ?>'
                                                data-final-comments="<?= htmlspecialchars($term['final_comments'] ?? '') ?>"
                                                data-nextcloud="<?= htmlspecialchars($term['nextcloud_folder'] ?? '') ?>"
                                                data-processed-by="<?= htmlspecialchars(($term['processed_first'] ?? '') . ' ' . ($term['processed_last'] ?? '')) ?>"
                                                data-created="<?= $term['created_at'] ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if(!empty($term['nextcloud_folder'])): ?>
                                        <a href="<?= htmlspecialchars($term['nextcloud_folder']) ?>" target="_blank" class="btn-icon" title="Open in Nextcloud">
                                            <i class="fas fa-external-link-alt"></i>
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
                                        <i class="fas fa-user-times"></i>
                                        <p>No termination records found</p>
                                        <span>All staff members are currently active</span>
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
                    <a href="?page=termination&term_page=<?= $page_num - 1 ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                
                <?php for($i = max(1, $page_num - 2); $i <= min($total_pages, $page_num + 2); $i++): ?>
                    <a href="?page=termination&term_page=<?= $i ?>" class="page-btn <?= $i === $page_num ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                
                <?php if($page_num < $total_pages): ?>
                    <a href="?page=termination&term_page=<?= $page_num + 1 ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Termination Details Modal -->
<div id="terminationModal" class="modal-overlay" style="display: none;">
    <div class="modal-container modal-large">
        <div class="modal-header">
            <h3><i class="fas fa-user-times"></i> Termination Details</h3>
            <button class="modal-close" aria-label="Close modal" onclick="closeTerminationModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="detail-grid">
                <div class="detail-section">
                    <h4><i class="fas fa-user"></i> Staff Information</h4>
                    <div class="detail-row">
                        <label>Name:</label>
                        <span id="modal-name"></span>
                    </div>
                    <div class="detail-row">
                        <label>Email:</label>
                        <span id="modal-email"></span>
                    </div>
                    <div class="detail-row">
                        <label>Role:</label>
                        <span id="modal-role"></span>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h4><i class="fas fa-calendar-times"></i> Termination Information</h4>
                    <div class="detail-row">
                        <label>Termination Date:</label>
                        <span id="modal-date"></span>
                    </div>
                    <div class="detail-row">
                        <label>Type:</label>
                        <span id="modal-type"></span>
                    </div>
                    <div class="detail-row">
                        <label>Reason Category:</label>
                        <span id="modal-reason-category"></span>
                    </div>
                    <div class="detail-row">
                        <label>Notice Period:</label>
                        <span id="modal-notice"></span>
                    </div>
                    <div class="detail-row">
                        <label>Status:</label>
                        <span id="modal-status"></span>
                    </div>
                </div>
            </div>
            
            <div class="detail-section full-width">
                <h4><i class="fas fa-align-left"></i> Detailed Reason/Notes</h4>
                <div class="detail-text" id="modal-reason"></div>
            </div>
            
            <div class="detail-section full-width">
                <h4><i class="fas fa-tasks"></i> Offboarding Checklist</h4>
                <div class="checklist-display" id="modal-checklist"></div>
            </div>
            
            <div class="detail-section full-width">
                <h4><i class="fas fa-sticky-note"></i> Final Comments</h4>
                <div class="detail-text" id="modal-final-comments"></div>
            </div>
            
            <div class="detail-section full-width">
                <h4><i class="fas fa-cloud"></i> Nextcloud Documents</h4>
                <div id="modal-nextcloud"></div>
            </div>
            
            <div class="detail-section full-width">
                <h4><i class="fas fa-info-circle"></i> Processing Information</h4>
                <div class="detail-row">
                    <label>Processed By:</label>
                    <span id="modal-processed-by"></span>
                </div>
                <div class="detail-row">
                    <label>Created At:</label>
                    <span id="modal-created"></span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeTerminationModal()"><i class="fas fa-times"></i> Close</button>
        </div>
    </div>
</div>

<style>
/* Termination Stats */
.termination-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}

.termination-stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 22px;
    display: flex;
    align-items: center;
    gap: 18px;
    transition: all 0.3s ease;
}

.termination-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
}

.termination-stat-card .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.termination-stat-card.total .stat-icon { background: rgba(107, 70, 193, 0.15); color: #8B5CF6; }
.termination-stat-card.completed .stat-icon { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.termination-stat-card.pending .stat-icon { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.termination-stat-card.upcoming .stat-icon { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

.termination-stat-card .stat-info { flex: 1; }

.termination-stat-card .stat-value {
    font-size: 24px;
    font-weight: 900;
    color: var(--text-white);
    display: block;
    margin-bottom: 4px;
}

.termination-stat-card .stat-label {
    font-size: 12px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

/* Header badge */
.header-badge {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.header-badge.history {
    background: rgba(107, 70, 193, 0.15);
    color: #8B5CF6;
}

/* Form labels with icons */
.form-label i {
    margin-right: 8px;
    color: var(--primary);
}

/* Alert Cards */
.alert-card {
    display: flex;
    gap: 18px;
    padding: 22px;
    border-radius: 12px;
    margin-bottom: 28px;
}

.alert-card i {
    font-size: 26px;
    flex-shrink: 0;
}

.alert-card.warning {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: #f59e0b;
}

.alert-card.info {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: #3b82f6;
    margin-top: 24px;
}

.alert-content {
    flex: 1;
}

.alert-content h4 {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 6px;
}

.alert-content p {
    font-size: 14px;
    line-height: 1.6;
}

/* Checklist Grid */
.checklist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 12px;
}

.checkbox-option {
    display: flex;
    align-items: center;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 14px 16px;
    cursor: pointer;
    transition: all 0.3s;
}

.checkbox-option:hover {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.05);
}

.checkbox-option input {
    margin-right: 12px;
}

.checkbox-option span {
    font-size: 14px;
    color: var(--text-white);
    display: flex;
    align-items: center;
    gap: 10px;
}

.checkbox-option span i {
    color: var(--text-dim);
    font-size: 14px;
}

/* File Upload Zone */
.file-upload-zone {
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 32px 24px;
    text-align: center;
    background: var(--bg-main);
    transition: all 0.3s;
    cursor: pointer;
}

.file-upload-zone:hover {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.05);
}

.file-upload-zone .upload-icon i {
    font-size: 42px;
    color: var(--primary);
    opacity: 0.6;
    margin-bottom: 12px;
}

.file-upload-zone .upload-text {
    font-size: 15px;
    color: var(--text-white);
    font-weight: 600;
    margin-bottom: 6px;
}

.file-upload-zone .upload-hint {
    font-size: 12px;
    color: var(--text-dim);
    display: block;
    margin-bottom: 16px;
}

.upload-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.file-count {
    display: block;
    margin-top: 12px;
    color: #10b981;
    font-weight: 600;
}

.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
}

/* Staff info in table */
.staff-info {
    display: flex;
    flex-direction: column;
}

.staff-info strong {
    color: var(--text-white);
}

.staff-info small {
    color: var(--text-dim);
    font-size: 11px;
}

/* Role badge */
.role-badge {
    background: rgba(107, 70, 193, 0.15);
    color: #8B5CF6;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

/* Type badges */
.type-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.type-badge.voluntary { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.type-badge.involuntary { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.type-badge.retirement { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.type-badge.contract_end { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.type-badge.mutual { background: rgba(156, 163, 175, 0.15); color: #9ca3af; }

/* Document indicator */
.doc-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.doc-indicator.has-docs {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.doc-indicator.no-docs {
    background: rgba(107, 114, 128, 0.15);
    color: #6b7280;
}

/* Table enhancements */
.empty-state {
    padding: 60px 20px !important;
}

.empty-state-content {
    text-align: center;
}

.empty-state-content i {
    font-size: 48px;
    color: var(--border);
    margin-bottom: 16px;
}

.empty-state-content p {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 8px;
}

.empty-state-content span {
    font-size: 13px;
    color: var(--text-dim);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
}

.page-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 12px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-white);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s;
}

.page-btn:hover {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.1);
}

.page-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 20px;
}

.modal-container {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-container.modal-large {
    max-width: 800px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    background: var(--bg-main);
}

.modal-header h3 {
    color: var(--text-white);
    font-size: 18px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header h3 i {
    color: var(--primary);
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-dim);
    font-size: 28px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
    transition: color 0.2s;
}

.modal-close:hover {
    color: var(--text-white);
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

/* Detail Grid */
.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
    margin-bottom: 24px;
}

.detail-section {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 18px;
}

.detail-section.full-width {
    grid-column: span 2;
}

.detail-section h4 {
    color: var(--text-white);
    font-size: 14px;
    font-weight: 700;
    margin: 0 0 16px 0;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 8px;
}

.detail-section h4 i {
    color: var(--primary);
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row label {
    color: var(--text-dim);
    font-size: 13px;
    font-weight: 600;
}

.detail-row span {
    color: var(--text-white);
    font-size: 13px;
}

.detail-text {
    color: var(--text-white);
    font-size: 14px;
    line-height: 1.6;
    white-space: pre-wrap;
    padding: 12px;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 8px;
}

/* Checklist display in modal */
.checklist-display {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 8px;
}

.checklist-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 6px;
    font-size: 13px;
}

.checklist-item.checked {
    color: #10b981;
}

.checklist-item.unchecked {
    color: var(--text-dim);
}

.checklist-item i {
    width: 16px;
}

.nextcloud-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 8px;
    color: #3b82f6;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s;
}

.nextcloud-link:hover {
    background: rgba(59, 130, 246, 0.2);
}

.no-nextcloud {
    color: var(--text-dim);
    font-style: italic;
}

@media (max-width: 768px) {
    .termination-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .checklist-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .detail-grid {
        grid-template-columns: 1fr;
    }
    
    .detail-section.full-width {
        grid-column: span 1;
    }
    
    .modal-container.modal-large {
        max-width: 100%;
    }
}

@media (max-width: 480px) {
    .termination-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Show file count when files are selected
document.getElementById('terminationDocuments').addEventListener('change', function(e) {
    const fileCount = e.target.files.length;
    const fileCountSpan = document.getElementById('fileCount');
    if (fileCount > 0) {
        fileCountSpan.textContent = fileCount + ' file(s) selected';
    } else {
        fileCountSpan.textContent = '';
    }
});

// View details modal
document.querySelectorAll('.view-details').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const data = this.dataset;
        
        document.getElementById('modal-name').textContent = data.name || 'N/A';
        document.getElementById('modal-email').textContent = data.email || 'N/A';
        document.getElementById('modal-role').textContent = data.role ? data.role.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'N/A';
        document.getElementById('modal-date').textContent = data.date ? new Date(data.date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
        document.getElementById('modal-type').textContent = data.type ? data.type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'N/A';
        document.getElementById('modal-reason-category').textContent = data.reasonCategory ? data.reasonCategory.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'N/A';
        document.getElementById('modal-notice').textContent = data.notice ? data.notice + ' days' : 'Not specified';
        
        const statusSpan = document.getElementById('modal-status');
        statusSpan.textContent = data.status ? data.status.charAt(0).toUpperCase() + data.status.slice(1) : 'N/A';
        statusSpan.className = 'status-badge ' + (data.status || '').toLowerCase();
        
        document.getElementById('modal-reason').textContent = data.reason || 'No details provided';
        document.getElementById('modal-final-comments').textContent = data.finalComments || 'No comments';
        document.getElementById('modal-processed-by').textContent = data.processedBy ? data.processedBy.trim() : 'N/A';
        document.getElementById('modal-created').textContent = data.created ? new Date(data.created).toLocaleString() : 'N/A';
        
        // Checklist
        const checklistContainer = document.getElementById('modal-checklist');
        const allItems = ['equipment', 'access', 'paycheck', 'pto', 'interview', 'records', 'letter'];
        const itemLabels = {
            'equipment': 'Return company equipment',
            'access': 'Revoke system access',
            'paycheck': 'Process final paycheck',
            'pto': 'Settle unused PTO',
            'interview': 'Conduct exit interview',
            'records': 'Update employee records',
            'letter': 'Provide termination letter'
        };
        
        let checkedItems = [];
        try {
            checkedItems = JSON.parse(data.checklist || '[]');
        } catch (e) {
            checkedItems = [];
        }
        
        checklistContainer.innerHTML = allItems.map(function(item) {
            const isChecked = checkedItems.includes(item);
            return '<div class="checklist-item ' + (isChecked ? 'checked' : 'unchecked') + '">' +
                   '<i class="fas ' + (isChecked ? 'fa-check-circle' : 'fa-circle') + '"></i> ' +
                   itemLabels[item] +
                   '</div>';
        }).join('');
        
        // Nextcloud
        const nextcloudContainer = document.getElementById('modal-nextcloud');
        if (data.nextcloud) {
            nextcloudContainer.innerHTML = '<a href="' + data.nextcloud + '" target="_blank" class="nextcloud-link">' +
                '<i class="fas fa-cloud"></i> View Documents in Nextcloud' +
                '</a><br><small style="color: var(--text-dim); margin-top: 8px; display: block;">Path: ' + data.nextcloud + '</small>';
        } else {
            nextcloudContainer.innerHTML = '<span class="no-nextcloud">No documents uploaded to Nextcloud</span>';
        }
        
        document.getElementById('terminationModal').style.display = 'flex';
    });
});

function closeTerminationModal() {
    document.getElementById('terminationModal').style.display = 'none';
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeTerminationModal();
    }
});

// Close modal on overlay click
document.getElementById('terminationModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeTerminationModal();
    }
});
</script>
