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
            <button class="btn-primary"><i class="fas fa-plus"></i> Add Cron Job</button>
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
                        <button class="btn-icon" title="Run Now"><i class="fas fa-play"></i></button>
                        <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon" title="Disable"><i class="fas fa-pause"></i></button>
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
                        <button class="btn-icon" title="Run Now"><i class="fas fa-play"></i></button>
                        <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon" title="Disable"><i class="fas fa-pause"></i></button>
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
                        <button class="btn-icon" title="Enable"><i class="fas fa-play"></i></button>
                        <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
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
    padding: 25px;
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
