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
?>
<!-- HR Termination View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-user-times"></i> Employee Termination
    </h1>
    <p class="page-description">Process employee termination and offboarding</p>
</div>

<div class="termination-content">
    <!-- Warning Notice -->
    <div class="alert-card warning">
        <i class="fas fa-exclamation-triangle"></i>
        <div class="alert-content">
            <h4>Important Notice</h4>
            <p>Employee termination is a sensitive process. Please ensure all required documentation and approvals are in place before proceeding.</p>
        </div>
    </div>

    <!-- Termination Form -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-file-alt"></i> Termination Details</h3>
        </div>
        <div class="card-body">
            <form class="termination-form" method="POST" action="process_coach_termination.php" enctype="multipart/form-data">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="create">
                <div class="form-row">
                    <div class="form-group">
                        <label>Employee *</label>
                        <select name="user_id" class="form-input" required>
                            <option value="">-- Select Employee --</option>
                            <!-- Employees will be populated here -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Termination Date *</label>
                        <input type="date" name="termination_date" class="form-input" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Termination Type *</label>
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
                        <label>Reason Category *</label>
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
                    <label>Detailed Reason/Notes *</label>
                    <textarea name="notes" class="form-textarea" rows="4" placeholder="Provide detailed reason for termination..." required></textarea>
                </div>

                <div class="form-group">
                    <label>Notice Period (days)</label>
                    <input type="number" name="notice_period" class="form-input" placeholder="14" min="0">
                </div>

                <div class="form-group">
                    <label>Offboarding Checklist</label>
                    <div class="checklist">
                        <label class="checkbox-option">
                            <input type="checkbox" name="checklist[]" value="equipment">
                            <span>Return company equipment (keys, access cards, etc.)</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="checklist[]" value="access">
                            <span>Revoke system access and credentials</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="checklist[]" value="paycheck">
                            <span>Process final paycheck</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="checklist[]" value="pto">
                            <span>Return unused vacation/PTO</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="checklist[]" value="interview">
                            <span>Conduct exit interview</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="checklist[]" value="records">
                            <span>Update employee records</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="checklist[]" value="letter">
                            <span>Provide termination letter</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Supporting Documents</label>
                    <div class="file-upload-zone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Upload relevant documentation (resignation letter, termination notice, etc.)</p>
                        <input type="file" name="documents[]" id="terminationDocuments" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display: none;">
                        <button type="button" class="btn-secondary" onclick="document.getElementById('terminationDocuments').click()">Choose Files</button>
                        <span id="fileCount" style="margin-left: 10px; color: #10b981;"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Final Comments</label>
                    <textarea class="form-textarea" rows="3" placeholder="Any additional comments or notes..."></textarea>
                </div>

                <div class="alert-card info">
                    <i class="fas fa-info-circle"></i>
                    <div class="alert-content">
                        <p>This action will archive the employee record and trigger notifications to relevant departments. The employee's system access will be scheduled for revocation on the termination date.</p>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="location.reload()"><i class="fas fa-times"></i> Cancel</button>
                    <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Process Termination</button>
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
                                <td colspan="6" style="text-align: center; padding: 24px;">
                                    <p class="placeholder-text">No termination records found.</p>
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
.alert-card {
    display: flex;
    gap: 15px;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 24px;
}

.alert-card i {
    font-size: 24px;
    flex-shrink: 0;
}

.alert-card.warning {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid #f59e0b;
    color: #f59e0b;
}

.alert-card.info {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid #3b82f6;
    color: #3b82f6;
    margin-top: 20px;
}

.alert-content {
    flex: 1;
}

.alert-content h4 {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 5px;
}

.alert-content p {
    font-size: 14px;
    line-height: 1.6;
}

.checklist {
    display: flex;
    flex-direction: column;
    gap: 12px;
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
