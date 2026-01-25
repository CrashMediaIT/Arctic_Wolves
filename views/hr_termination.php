<?php
// Fetch recent terminations
$terminationsQuery = "SELECT t.*, u.first_name, u.last_name, u.role
    FROM employee_terminations t
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY t.termination_date DESC
    LIMIT 10";
$terminations_stmt = $pdo->prepare($terminationsQuery);
$terminations_stmt->execute();
$terminations = $terminations_stmt->fetchAll();

// Fetch termination stats
$terminationStatsQuery = "SELECT 
    COUNT(*) as total_terminations,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN termination_date > CURDATE() THEN 1 END) as upcoming
    FROM employee_terminations";
try {
    $statsResult = $pdo->query($terminationStatsQuery);
    $terminationStats = $statsResult->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $terminationStats = ['total_terminations' => 0, 'completed' => 0, 'pending' => 0, 'upcoming' => 0];
}

// Fetch active employees for the dropdown
$employeesQuery = "SELECT id, first_name, last_name, role FROM users WHERE is_active = 1 AND role IN ('coach', 'health_coach', 'team_coach') ORDER BY first_name, last_name";
$employees_stmt = $pdo->query($employeesQuery);
$employees = $employees_stmt->fetchAll();
?>
<!-- HR Termination View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-user-times"></i> Employee Termination
    </h1>
    <p class="page-description">Process employee termination and offboarding procedures</p>
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
            <p>Employee termination is a sensitive process. Please ensure all required documentation and approvals are in place before proceeding. This action will be logged in the audit trail.</p>
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
                        <label class="form-label"><i class="fas fa-user"></i> Employee *</label>
                        <select name="user_id" class="form-input" required>
                            <option value="">-- Select Employee --</option>
                            <?php foreach($employees as $employee): ?>
                            <option value="<?= $employee['id'] ?>"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?> (<?= ucfirst($employee['role']) ?>)</option>
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
                        <span class="upload-hint">Resignation letter, termination notice, etc. (PDF, DOC, Images)</span>
                        <!-- TODO: Add server-side file validation using file_upload_validator.php (see Issue #603) -->
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
                        <p>This action will archive the employee record and trigger notifications to relevant departments. The employee's system access will be scheduled for revocation on the termination date.</p>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn-secondary"><i class="fas fa-redo"></i> Reset</button>
                    <button type="submit" class="btn-danger"><i class="fas fa-user-times"></i> Process Termination</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Recent Terminations -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recent Terminations</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Position</th>
                            <th>Termination Date</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($terminations)): ?>
                            <?php foreach($terminations as $term): 
                                $statusClass = strtolower($term['status']);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($term['first_name'] . ' ' . $term['last_name']) ?></td>
                                <td><?= htmlspecialchars($term['role']) ?></td>
                                <td><?= date('M j, Y', strtotime($term['termination_date'])) ?></td>
                                <td><?= htmlspecialchars($term['termination_type']) ?></td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= ucfirst($term['status']) ?></span></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-icon" title="View Details" data-action="view"><i class="fas fa-eye"></i></button>
                                        <?php if($term['status'] === 'completed'): ?>
                                            <button class="btn-icon" title="Restore Employee" data-action="restore" data-termination-id="<?= $term['termination_id'] ?>">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <div class="empty-state-content">
                                        <i class="fas fa-user-times"></i>
                                        <p>No termination records found</p>
                                        <span>All employees are currently active</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
</script>
