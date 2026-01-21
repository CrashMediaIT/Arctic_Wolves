<!-- Admin Users Management View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-users-cog"></i> User Management
    </h1>
    <p class="page-description">Manage all system users and permissions</p>
</div>

<div class="users-content">
    <!-- Filter and Actions -->
    <div class="action-bar">
        <div class="filter-group">
            <input type="text" class="form-input-small" placeholder="Search users...">
            <select class="form-input-small">
                <option>All Roles</option>
                <option>Admin</option>
                <option>Coach</option>
                <option>Health Coach</option>
                <option>Athlete</option>
                <option>Parent</option>
            </select>
            <select class="form-input-small">
                <option>All Status</option>
                <option>Active</option>
                <option>Inactive</option>
                <option>Pending</option>
            </select>
        </div>
        <button class="btn-primary"><i class="fas fa-user-plus"></i> Add User</button>
    </div>

    <!-- Users Table -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-users"></i> All Users (127)</h3>
            <button class="btn-secondary"><i class="fas fa-file-export"></i> Export</button>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Joined</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar">JD</div>
                                    <span>John Doe</span>
                                </div>
                            </td>
                            <td><span class="role-badge admin">Admin</span></td>
                            <td>john@crashhockey.com</td>
                            <td>(555) 123-4567</td>
                            <td>Jan 1, 2024</td>
                            <td><span class="status-badge active">Active</span></td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                                    <button class="btn-icon" title="Permissions"><i class="fas fa-key"></i></button>
                                    <button class="btn-icon" title="Disable"><i class="fas fa-ban"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar">MS</div>
                                    <span>Mike Smith</span>
                                </div>
                            </td>
                            <td><span class="role-badge coach">Coach</span></td>
                            <td>mike.smith@email.com</td>
                            <td>(555) 234-5678</td>
                            <td>Dec 15, 2023</td>
                            <td><span class="status-badge active">Active</span></td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                                    <button class="btn-icon" title="Permissions"><i class="fas fa-key"></i></button>
                                    <button class="btn-icon" title="Disable"><i class="fas fa-ban"></i></button>
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
.user-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--neon), var(--accent));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 900;
    color: #fff;
}

.role-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.role-badge.admin {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.role-badge.coach {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.role-badge.athlete {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.role-badge.parent {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.status-badge.active {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-badge.inactive {
    background: rgba(148, 163, 184, 0.1);
    color: var(--text-dim);
}
</style>
