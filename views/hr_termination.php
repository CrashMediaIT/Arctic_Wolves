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
            <form class="termination-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Employee *</label>
                        <select class="form-input" required>
                            <option value="">-- Select Employee --</option>
                            <!-- Employees will be populated here -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Termination Date *</label>
                        <input type="date" class="form-input" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Termination Type *</label>
                        <select class="form-input" required>
                            <option value="">-- Select Type --</option>
                            <option>Voluntary Resignation</option>
                            <option>Involuntary Termination</option>
                            <option>Retirement</option>
                            <option>Contract End</option>
                            <option>Mutual Agreement</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reason Category *</label>
                        <select class="form-input" required>
                            <option value="">-- Select Reason --</option>
                            <option>Performance Issues</option>
                            <option>Policy Violation</option>
                            <option>Downsizing</option>
                            <option>Better Opportunity</option>
                            <option>Personal Reasons</option>
                            <option>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Detailed Reason/Notes *</label>
                    <textarea class="form-textarea" rows="4" placeholder="Provide detailed reason for termination..." required></textarea>
                </div>

                <div class="form-group">
                    <label>Notice Period (days)</label>
                    <input type="number" class="form-input" placeholder="14" min="0">
                </div>

                <div class="form-group">
                    <label>Offboarding Checklist</label>
                    <div class="checklist">
                        <label class="checkbox-option">
                            <input type="checkbox">
                            <span>Return company equipment (keys, access cards, etc.)</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox">
                            <span>Revoke system access and credentials</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox">
                            <span>Process final paycheck</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox">
                            <span>Return unused vacation/PTO</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox">
                            <span>Conduct exit interview</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox">
                            <span>Update employee records</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox">
                            <span>Provide termination letter</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Supporting Documents</label>
                    <div class="file-upload-zone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Upload relevant documentation (resignation letter, termination notice, etc.)</p>
                        <input type="file" multiple style="display: none;">
                        <button type="button" class="btn-secondary">Choose Files</button>
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
                    <button type="button" class="btn-secondary"><i class="fas fa-times"></i> Cancel</button>
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
                        <tr>
                            <td>Mike Johnson</td>
                            <td>Assistant Coach</td>
                            <td>Dec 31, 2023</td>
                            <td>Contract End</td>
                            <td><span class="status-badge completed">Completed</span></td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon" title="View Details"><i class="fas fa-eye"></i></button>
                                </div>
                            </td>
                        </tr>
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
    margin-bottom: 25px;
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
