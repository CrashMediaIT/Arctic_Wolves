<?php
/**
 * Admin Cron Jobs View
 * Manage scheduled tasks and automated jobs
 */

// Fetch cron jobs from database
try {
    $cronJobsStmt = $pdo->query("SELECT * FROM cron_jobs ORDER BY job_name");
    $cronJobs = $cronJobsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Cron jobs fetch error: " . $e->getMessage());
    $cronJobs = [];
}

// Fetch execution history (if table exists)
try {
    $historyStmt = $pdo->query("SELECT * FROM cron_job_history ORDER BY started_at DESC LIMIT 20");
    $executionHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist yet
    $executionHistory = [];
}

// Calculate stats
$activeJobs = count(array_filter($cronJobs, function($j) { return !empty($j['is_active']); }));
$inactiveJobs = count($cronJobs) - $activeJobs;
?>
<!-- Admin Cron Jobs View -->
<?php if (isset($_GET['status']) && in_array($_GET['status'], ['success', 'added'])): ?>
<div class="success-alert" style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-check-circle" style="color: #10b981; font-size: 20px;"></i>
    <span style="color: #10b981; font-weight: 600;">Operation completed successfully!</span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #10b981; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>
<?php if (isset($_GET['status']) && $_GET['status'] === 'error'): ?>
<div class="error-alert" style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-exclamation-circle" style="color: #ef4444; font-size: 20px;"></i>
    <span style="color: #ef4444; font-weight: 600;"><?= htmlspecialchars($_GET['message'] ?? 'An error occurred') ?></span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #ef4444; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>
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
            <h3><i class="fas fa-tasks"></i> Cron Jobs (<?= $activeJobs ?> active, <?= $inactiveJobs ?> inactive)</h3>
            <button class="btn-primary" data-action="add" data-modal="add-cron-job-modal"><i class="fas fa-plus"></i> Add Cron Job</button>
        </div>
        <div class="card-body">
            <?php if (empty($cronJobs)): ?>
                <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                    <i class="fas fa-clock" style="font-size: 48px; color: var(--text-dim); margin-bottom: 16px;"></i>
                    <p style="color: var(--text-dim);">No cron jobs configured. Click "Add Cron Job" to create one.</p>
                </div>
            <?php else: ?>
            <div class="cron-list">
                <?php foreach ($cronJobs as $job): 
                    $isActive = !empty($job['is_active']);
                    $lastRun = $job['last_run_at'] ? date('M d, g:i A', strtotime($job['last_run_at'])) : 'Never';
                    $nextRun = $job['next_run_at'] ? date('M d, g:i A', strtotime($job['next_run_at'])) : 'Not scheduled';
                ?>
                <div class="cron-item <?= $isActive ? '' : 'paused' ?>">
                    <div class="cron-status <?= $isActive ? 'running' : 'paused' ?>">
                        <i class="fas fa-<?= $isActive ? 'circle' : 'pause-circle' ?>"></i>
                    </div>
                    <div class="cron-details">
                        <h4><?= htmlspecialchars($job['job_name']) ?></h4>
                        <div class="cron-meta">
                            <span><i class="fas fa-calendar"></i> <?= htmlspecialchars($job['schedule']) ?></span>
                            <span><i class="fas fa-<?= $isActive ? 'check' : 'times' ?>"></i> <?= $isActive ? 'Last run: ' . $lastRun : 'Disabled' ?></span>
                            <?php if ($isActive && $job['next_run_at']): ?>
                            <span><i class="fas fa-clock"></i> Next run: <?= $nextRun ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="cron-description"><?= htmlspecialchars($job['job_description'] ?? '') ?></p>
                    </div>
                    <div class="cron-actions">
                        <?php if ($isActive): ?>
                        <button class="btn-icon" title="Run Now" data-action="run" data-id="<?= $job['id'] ?>"><i class="fas fa-play"></i></button>
                        <?php endif; ?>
                        <button class="btn-icon" title="Edit" data-action="edit" data-id="<?= $job['id'] ?>" data-modal="edit-cron-job-modal" 
                                data-name="<?= htmlspecialchars($job['job_name'], ENT_QUOTES) ?>"
                                data-description="<?= htmlspecialchars($job['job_description'] ?? '', ENT_QUOTES) ?>"
                                data-command="<?= htmlspecialchars($job['command'] ?? '', ENT_QUOTES) ?>"
                                data-schedule="<?= htmlspecialchars($job['schedule'], ENT_QUOTES) ?>"
                                data-status="<?= $isActive ? '1' : '0' ?>"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon" title="<?= $isActive ? 'Disable' : 'Enable' ?>" data-action="toggle" data-id="<?= $job['id'] ?>"><i class="fas fa-<?= $isActive ? 'pause' : 'play' ?>"></i></button>
                        <button class="btn-icon danger" title="Delete" data-action="delete" data-id="<?= $job['id'] ?>"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Execution History -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Execution History</h3>
        </div>
        <div class="card-body">
            <?php if (empty($executionHistory)): ?>
                <div class="empty-state" style="text-align: center; padding: 40px 20px;">
                    <i class="fas fa-history" style="font-size: 32px; color: var(--text-dim); margin-bottom: 12px;"></i>
                    <p style="color: var(--text-dim);">No execution history yet.</p>
                </div>
            <?php else: ?>
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
                        <?php foreach ($executionHistory as $history): 
                            $duration = $history['completed_at'] && $history['started_at'] 
                                ? strtotime($history['completed_at']) - strtotime($history['started_at']) 
                                : 0;
                            $durationStr = $duration > 60 ? floor($duration/60) . 'm ' . ($duration % 60) . 's' : $duration . 's';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($history['job_name'] ?? 'Unknown') ?></td>
                            <td><?= $history['started_at'] ? date('M d, g:i A', strtotime($history['started_at'])) : '-' ?></td>
                            <td><?= $history['completed_at'] ? date('M d, g:i A', strtotime($history['completed_at'])) : '-' ?></td>
                            <td><?= $durationStr ?></td>
                            <td><span class="status-badge <?= ($history['status'] ?? '') === 'success' ? 'success' : 'error' ?>"><?= ucfirst($history['status'] ?? 'Unknown') ?></span></td>
                            <td><button class="btn-link" title="View Output"><i class="fas fa-eye"></i> View</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
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
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('add-cron-job-modal')">&times;</button>
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
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('edit-cron-job-modal')">&times;</button>
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
                    <label class="form-label">Command/Script *</label>
                    <input type="text" name="command" id="edit-cron-job-command" class="form-input" required placeholder="e.g., cron_notifications.php">
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
                        <option value="custom">Custom (cron syntax)</option>
                    </select>
                </div>
                
                <div class="form-group" id="edit-custom-schedule-group" style="display: none;">
                    <label class="form-label">Custom Cron Expression</label>
                    <input type="text" name="custom_schedule" id="edit-cron-job-custom-schedule" class="form-input" placeholder="* * * * *">
                    <small style="color: var(--text-dim);">Format: minute hour day month weekday</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="is_active" id="edit-cron-job-status" class="form-input">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
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
    var csrfToken = document.querySelector('[name="csrf_token"]')?.value || '<?= $_SESSION['csrf_token'] ?? '' ?>';
    
    // Show notification helper
    function showNotification(message, type) {
        var existing = document.querySelector('.notification-widget');
        if (existing) existing.remove();
        
        var div = document.createElement('div');
        div.className = 'notification-widget';
        div.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; padding: 16px 24px; border-radius: 8px; display: flex; align-items: center; gap: 12px;';
        if (type === 'success') {
            div.style.background = 'rgba(16, 185, 129, 0.95)';
            div.style.color = '#fff';
        } else {
            div.style.background = 'rgba(239, 68, 68, 0.95)';
            div.style.color = '#fff';
        }
        // Escape message to prevent XSS
        var escapedMessage = message.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        div.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + escapedMessage + '<button onclick="this.parentElement.remove()" style="margin-left: 16px; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">&times;</button>';
        document.body.appendChild(div);
        setTimeout(function() { if (div.parentElement) div.remove(); }, 5000);
    }
    
    // Custom schedule toggle
    var scheduleSelects = document.querySelectorAll('select[name="schedule"]');
    var customGroup = document.getElementById('custom-schedule-group');
    var editCustomGroup = document.getElementById('edit-custom-schedule-group');
    
    scheduleSelects.forEach(function(select) {
        select.addEventListener('change', function() {
            var targetGroup = this.closest('#edit-cron-job-modal') ? editCustomGroup : customGroup;
            if (targetGroup && this.value === 'custom') {
                targetGroup.style.display = 'block';
            } else if (targetGroup) {
                targetGroup.style.display = 'none';
            }
        });
    });
    
    // Handle add buttons for modals
    document.querySelectorAll('[data-action="add"][data-modal]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var modalId = this.getAttribute('data-modal');
            var modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
            }
        });
    });
    
    // Handle edit buttons for modals with data population
    document.querySelectorAll('[data-action="edit"][data-modal]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var modalId = this.getAttribute('data-modal');
            var modal = document.getElementById(modalId);
            
            if (modal) {
                document.getElementById('edit-cron-job-id').value = this.getAttribute('data-id') || '';
                document.getElementById('edit-cron-job-name').value = this.getAttribute('data-name') || '';
                document.getElementById('edit-cron-job-description').value = this.getAttribute('data-description') || '';
                document.getElementById('edit-cron-job-command').value = this.getAttribute('data-command') || '';
                
                var schedule = this.getAttribute('data-schedule') || '';
                var scheduleSelect = document.getElementById('edit-cron-job-schedule');
                var foundOption = false;
                for (var i = 0; i < scheduleSelect.options.length; i++) {
                    if (scheduleSelect.options[i].value === schedule) {
                        scheduleSelect.selectedIndex = i;
                        foundOption = true;
                        break;
                    }
                }
                if (!foundOption && schedule) {
                    scheduleSelect.value = 'custom';
                    document.getElementById('edit-cron-job-custom-schedule').value = schedule;
                    document.getElementById('edit-custom-schedule-group').style.display = 'block';
                } else {
                    document.getElementById('edit-custom-schedule-group').style.display = 'none';
                }
                
                document.getElementById('edit-cron-job-status').value = this.getAttribute('data-status') || '1';
                
                modal.classList.add('active');
            }
        });
    });
    
    // Handle run/toggle/delete buttons via AJAX
    document.querySelectorAll('[data-action="run"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var jobId = this.getAttribute('data-id');
            if (!confirm('Run this cron job now?')) return;
            
            fetch('process_cron_jobs.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=run&job_id=' + encodeURIComponent(jobId) + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message || 'Cron job executed successfully!', 'success');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    showNotification('Error: ' + (data.message || 'Failed to run cron job'), 'error');
                }
            })
            .catch(function() { showNotification('An error occurred', 'error'); });
        });
    });
    
    document.querySelectorAll('[data-action="toggle"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var jobId = this.getAttribute('data-id');
            
            fetch('process_cron_jobs.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=toggle&job_id=' + encodeURIComponent(jobId) + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message || 'Status toggled successfully!', 'success');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    showNotification('Error: ' + (data.message || 'Failed to toggle status'), 'error');
                }
            })
            .catch(function() { showNotification('An error occurred', 'error'); });
        });
    });
    
    document.querySelectorAll('[data-action="delete"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var jobId = this.getAttribute('data-id');
            if (!confirm('Are you sure you want to delete this cron job?')) return;
            
            fetch('process_cron_jobs.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=delete&job_id=' + encodeURIComponent(jobId) + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message || 'Cron job deleted!', 'success');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    showNotification('Error: ' + (data.message || 'Failed to delete'), 'error');
                }
            })
            .catch(function() { showNotification('An error occurred', 'error'); });
        });
    });
    
    // Convert modal forms to AJAX submissions
    document.querySelectorAll('.modal form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(form);
            var modal = form.closest('.modal');
            var submitBtn = form.querySelector('button[type="submit"]');
            var originalBtnText = submitBtn ? submitBtn.innerHTML : '';
            
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
            }
            
            fetch(form.getAttribute('action'), {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (submitBtn) {
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                }
                
                if (data.success) {
                    showNotification(data.message || 'Cron job saved successfully!', 'success');
                    if (modal) closeModal(modal.id);
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showNotification('Error: ' + (data.message || 'Operation failed'), 'error');
                }
            })
            .catch(function() {
                if (submitBtn) {
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                }
                showNotification('An error occurred', 'error');
            });
        });
    });
});

function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        var form = modal.querySelector('form');
        if (form) form.reset();
    }
}
</script>
