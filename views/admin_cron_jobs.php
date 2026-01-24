<!-- Admin Cron Jobs View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-clock"></i> Cron Job Management
    </h1>
    <p class="page-description">Manage scheduled tasks and automated jobs</p>
</div>

<div class="cron-content">
    <!-- Active Cron Jobs -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-tasks"></i> Active Cron Jobs</h3>
            <button class="btn-primary" data-action="add" data-modal="add-cron-job-modal"><i class="fas fa-plus"></i> Add Cron Job</button>
        </div>
        <div class="card-body">
            <div class="cron-list">
                <div class="cron-item">
                    <div class="cron-status running">
                        <i class="fas fa-circle"></i>
                    </div>
                    <div class="cron-details">
                        <h4>Send Session Reminders</h4>
                        <div class="cron-meta">
                            <span><i class="fas fa-calendar"></i> Daily at 8:00 AM</span>
                            <span><i class="fas fa-check"></i> Last run: 2 hours ago</span>
                            <span><i class="fas fa-clock"></i> Next run: Tomorrow 8:00 AM</span>
                        </div>
                        <p class="cron-description">Sends reminder emails to athletes 24 hours before sessions</p>
                    </div>
                    <div class="cron-actions">
                        <button class="btn-icon" title="Run Now" data-action="run" data-id="1" onclick="runCronJob(1)"><i class="fas fa-play"></i></button>
                        <button class="btn-icon" title="Edit" data-action="edit" data-id="1" data-modal="edit-cron-job-modal"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon" title="Disable" data-action="toggle" data-id="1" onclick="toggleCronJob(1)"><i class="fas fa-pause"></i></button>
                    </div>
                </div>

                <div class="cron-item">
                    <div class="cron-status running">
                        <i class="fas fa-circle"></i>
                    </div>
                    <div class="cron-details">
                        <h4>Database Backup</h4>
                        <div class="cron-meta">
                            <span><i class="fas fa-calendar"></i> Daily at 2:00 AM</span>
                            <span><i class="fas fa-check"></i> Last run: 14 hours ago</span>
                            <span><i class="fas fa-clock"></i> Next run: Today 2:00 AM</span>
                        </div>
                        <p class="cron-description">Creates daily database backups</p>
                    </div>
                    <div class="cron-actions">
                        <button class="btn-icon" title="Run Now" data-action="run" data-id="2" onclick="runCronJob(2)"><i class="fas fa-play"></i></button>
                        <button class="btn-icon" title="Edit" data-action="edit" data-id="2" data-modal="edit-cron-job-modal"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon" title="Disable" data-action="toggle" data-id="2" onclick="toggleCronJob(2)"><i class="fas fa-pause"></i></button>
                    </div>
                </div>

                <div class="cron-item paused">
                    <div class="cron-status paused">
                        <i class="fas fa-pause-circle"></i>
                    </div>
                    <div class="cron-details">
                        <h4>Clean Temp Files</h4>
                        <div class="cron-meta">
                            <span><i class="fas fa-calendar"></i> Weekly on Sunday at 3:00 AM</span>
                            <span><i class="fas fa-times"></i> Disabled</span>
                        </div>
                        <p class="cron-description">Removes temporary files older than 7 days</p>
                    </div>
                    <div class="cron-actions">
                        <button class="btn-icon" title="Enable" data-action="toggle" data-id="3" onclick="toggleCronJob(3)"><i class="fas fa-play"></i></button>
                        <button class="btn-icon" title="Edit" data-action="edit" data-id="3" data-modal="edit-cron-job-modal"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon" title="Delete" data-action="delete" data-id="3" onclick="deleteCronJob(3)"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Execution History -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Execution History</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Job Name</th>
                            <th>Started</th>
                            <th>Completed</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Output</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Send Session Reminders</td>
                            <td>Jan 15, 8:00 AM</td>
                            <td>Jan 15, 8:02 AM</td>
                            <td>2m 15s</td>
                            <td><span class="status-badge success">Success</span></td>
                            <td><button class="btn-link"><i class="fas fa-eye"></i> View</button></td>
                        </tr>
                        <tr>
                            <td>Database Backup</td>
                            <td>Jan 15, 2:00 AM</td>
                            <td>Jan 15, 2:05 AM</td>
                            <td>5m 32s</td>
                            <td><span class="status-badge success">Success</span></td>
                            <td><button class="btn-link"><i class="fas fa-eye"></i> View</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.cron-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.cron-item {
    display: flex;
    gap: 20px;
    padding: 24px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    transition: all 0.3s;
}

.cron-item:hover {
    border-color: var(--neon);
}

.cron-item.paused {
    opacity: 0.6;
}

.cron-status {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.cron-status.running {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.cron-status.paused {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.cron-details {
    flex: 1;
}

.cron-details h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 10px;
}

.cron-meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}

.cron-meta span {
    font-size: 13px;
    color: var(--text-dim);
}

.cron-meta i {
    color: var(--neon);
    margin-right: 5px;
}

.cron-description {
    font-size: 14px;
    color: var(--text-dim);
}

.cron-actions {
    display: flex;
    gap: 8px;
}
</style>

<!-- Add Cron Job Modal -->
<div id="add-cron-job-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Cron Job</h2>
            <button class="modal-close" onclick="closeModal('add-cron-job-modal')">&times;</button>
        </div>
        <form method="POST" action="process_cron_jobs.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Job Name *</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Command/Script *</label>
                    <input type="text" name="command" class="form-input" required placeholder="e.g., cron_notifications.php">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Schedule *</label>
                    <select name="schedule" class="form-input" required>
                        <option value="">Select Schedule</option>
                        <option value="*/5 * * * *">Every 5 minutes</option>
                        <option value="*/15 * * * *">Every 15 minutes</option>
                        <option value="*/30 * * * *">Every 30 minutes</option>
                        <option value="0 * * * *">Every hour</option>
                        <option value="0 0 * * *">Daily at midnight</option>
                        <option value="0 8 * * *">Daily at 8:00 AM</option>
                        <option value="0 0 * * 0">Weekly on Sunday</option>
                        <option value="0 0 1 * *">Monthly on 1st</option>
                        <option value="custom">Custom (cron syntax)</option>
                    </select>
                </div>
                
                <div class="form-group" id="custom-schedule-group" style="display: none;">
                    <label class="form-label">Custom Cron Expression</label>
                    <input type="text" name="custom_schedule" class="form-input" placeholder="* * * * *">
                    <small style="color: var(--text-dim);">Format: minute hour day month weekday</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-input">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('add-cron-job-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Cron Job</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Cron Job Modal -->
<div id="edit-cron-job-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Edit Cron Job</h2>
            <button class="modal-close" onclick="closeModal('edit-cron-job-modal')">&times;</button>
        </div>
        <form method="POST" action="process_cron_jobs.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-cron-job-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Job Name *</label>
                    <input type="text" name="name" id="edit-cron-job-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-cron-job-description" class="form-textarea" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Schedule *</label>
                    <select name="schedule" id="edit-cron-job-schedule" class="form-input" required>
                        <option value="">Select Schedule</option>
                        <option value="*/5 * * * *">Every 5 minutes</option>
                        <option value="*/15 * * * *">Every 15 minutes</option>
                        <option value="*/30 * * * *">Every 30 minutes</option>
                        <option value="0 * * * *">Every hour</option>
                        <option value="0 0 * * *">Daily at midnight</option>
                        <option value="0 8 * * *">Daily at 8:00 AM</option>
                        <option value="0 0 * * 0">Weekly on Sunday</option>
                        <option value="0 0 1 * *">Monthly on 1st</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="edit-cron-job-status" class="form-input">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('edit-cron-job-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update Cron Job</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const scheduleSelect = document.querySelector('select[name="schedule"]');
    const customGroup = document.getElementById('custom-schedule-group');
    
    if (scheduleSelect) {
        scheduleSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                customGroup.style.display = 'block';
            } else {
                customGroup.style.display = 'none';
            }
        });
    }
});

// Helper function to get CSRF token
function getCsrfToken() {
    return '<?= $_SESSION['csrf_token'] ?? '' ?>';
}

function runCronJob(jobId) {
    if (!confirm('Run this cron job now?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'run');
    formData.append('job_id', jobId);
    formData.append('csrf_token', getCsrfToken());
    
    fetch('process_cron_jobs.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Cron job executed successfully');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to run cron job'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while running the cron job');
    });
}

function toggleCronJob(jobId) {
    const formData = new FormData();
    formData.append('action', 'toggle');
    formData.append('job_id', jobId);
    formData.append('csrf_token', getCsrfToken());
    
    fetch('process_cron_jobs.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to toggle cron job'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while toggling the cron job');
    });
}

function deleteCronJob(jobId) {
    if (!confirm('Are you sure you want to delete this cron job?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('job_id', jobId);
    formData.append('csrf_token', getCsrfToken());
    
    fetch('process_cron_jobs.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete cron job'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the cron job');
    });
}
</script>
