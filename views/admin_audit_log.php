<!-- Admin Audit Log View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-history"></i> Audit Log
    </h1>
    <p class="page-description">Track and review system activity</p>
</div>

<div class="audit-content">
    <!-- Filter Bar -->
    <div class="action-bar">
        <div class="filter-group">
            <input type="text" class="form-input-small" placeholder="Search logs...">
            <select class="form-input-small">
                <option>All Actions</option>
                <option>Login/Logout</option>
                <option>User Management</option>
                <option>Data Changes</option>
                <option>Settings</option>
                <option>Security</option>
            </select>
            <select class="form-input-small">
                <option>All Users</option>
                <!-- Users will be populated -->
            </select>
            <input type="date" class="form-input-small" placeholder="Start Date">
            <input type="date" class="form-input-small" placeholder="End Date">
        </div>
        <button class="btn-secondary"><i class="fas fa-file-export"></i> Export</button>
    </div>

    <!-- Audit Log Table -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Activity Log</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Jan 15, 2024 3:42 PM</td>
                            <td>John Doe</td>
                            <td><span class="action-badge login">Login</span></td>
                            <td>Successful login</td>
                            <td>192.168.1.100</td>
                            <td><span class="status-badge success">Success</span></td>
                        </tr>
                        <tr>
                            <td>Jan 15, 2024 3:35 PM</td>
                            <td>Mike Smith</td>
                            <td><span class="action-badge data">Update User</span></td>
                            <td>Modified athlete profile</td>
                            <td>192.168.1.101</td>
                            <td><span class="status-badge success">Success</span></td>
                        </tr>
                        <tr>
                            <td>Jan 15, 2024 3:20 PM</td>
                            <td>Unknown</td>
                            <td><span class="action-badge security">Failed Login</span></td>
                            <td>Invalid credentials</td>
                            <td>45.123.45.67</td>
                            <td><span class="status-badge failed">Failed</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.action-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.action-badge.login {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.action-badge.data {
    background: rgba(255, 77, 0, 0.1);
    color: var(--neon);
}

.action-badge.security {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.status-badge.success {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-badge.failed {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}
</style>
