<!-- Coach Roster View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-user-friends"></i> Athlete Roster
    </h1>
    <p class="page-description">Manage your athletes and track their progress</p>
</div>

<div class="coach-roster-content">
    <!-- Filter and Actions Bar -->
    <div class="action-bar">
        <div class="filter-group">
            <input type="text" class="form-input-small" placeholder="Search athletes...">
            <select class="form-input-small">
                <option>All Programs</option>
                <option>Individual Training</option>
                <option>Team Training</option>
                <option>Skills Development</option>
            </select>
            <select class="form-input-small">
                <option>All Age Groups</option>
                <option>Under 10</option>
                <option>Under 12</option>
                <option>Under 14</option>
                <option>Under 16</option>
                <option>Under 18</option>
            </select>
        </div>
        <button class="btn-primary"><i class="fas fa-user-plus"></i> Add Athlete</button>
    </div>

    <!-- Athletes Table -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-users"></i> My Athletes (15)</h3>
            <div class="view-toggle">
                <button class="view-btn active"><i class="fas fa-table"></i></button>
                <button class="view-btn"><i class="fas fa-th"></i></button>
            </div>
        </div>
        <div class="card-body">
            <div class="athletes-table-container">
                <table class="athletes-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Program</th>
                            <th>Sessions</th>
                            <th>Last Session</th>
                            <th>Progress</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="athlete-cell">
                                    <div class="athlete-avatar-small">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="athlete-info">
                                        <div class="athlete-name">John Smith</div>
                                        <div class="athlete-email">john.smith@email.com</div>
                                    </div>
                                </div>
                            </td>
                            <td>14</td>
                            <td><span class="program-badge">Individual</span></td>
                            <td>
                                <div class="sessions-info">
                                    <span class="sessions-count">12 / 20</span>
                                    <div class="mini-progress">
                                        <div class="mini-progress-bar" style="width: 60%;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>Jan 15, 2024</td>
                            <td>
                                <span class="progress-badge excellent">Excellent</span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon" title="View Profile"><i class="fas fa-eye"></i></button>
                                    <button class="btn-icon" title="Schedule Session"><i class="fas fa-calendar-plus"></i></button>
                                    <button class="btn-icon" title="Message"><i class="fas fa-envelope"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="athlete-cell">
                                    <div class="athlete-avatar-small">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="athlete-info">
                                        <div class="athlete-name">Sarah Johnson</div>
                                        <div class="athlete-email">sarah.j@email.com</div>
                                    </div>
                                </div>
                            </td>
                            <td>16</td>
                            <td><span class="program-badge">Team</span></td>
                            <td>
                                <div class="sessions-info">
                                    <span class="sessions-count">8 / 15</span>
                                    <div class="mini-progress">
                                        <div class="mini-progress-bar" style="width: 53%;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>Jan 14, 2024</td>
                            <td>
                                <span class="progress-badge good">Good</span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon" title="View Profile"><i class="fas fa-eye"></i></button>
                                    <button class="btn-icon" title="Schedule Session"><i class="fas fa-calendar-plus"></i></button>
                                    <button class="btn-icon" title="Message"><i class="fas fa-envelope"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="athlete-cell">
                                    <div class="athlete-avatar-small">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="athlete-info">
                                        <div class="athlete-name">Mike Williams</div>
                                        <div class="athlete-email">m.williams@email.com</div>
                                    </div>
                                </div>
                            </td>
                            <td>12</td>
                            <td><span class="program-badge">Skills</span></td>
                            <td>
                                <div class="sessions-info">
                                    <span class="sessions-count">5 / 10</span>
                                    <div class="mini-progress">
                                        <div class="mini-progress-bar" style="width: 50%;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>Jan 10, 2024</td>
                            <td>
                                <span class="progress-badge needs-attention">Needs Attention</span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon" title="View Profile"><i class="fas fa-eye"></i></button>
                                    <button class="btn-icon" title="Schedule Session"><i class="fas fa-calendar-plus"></i></button>
                                    <button class="btn-icon" title="Message"><i class="fas fa-envelope"></i></button>
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
.athletes-table-container {
    overflow-x: auto;
}

.athletes-table {
    width: 100%;
    border-collapse: collapse;
}

.athletes-table thead {
    background: var(--bg-main);
}

.athletes-table th {
    padding: 15px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border);
}

.athletes-table td {
    padding: 15px;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
    color: var(--text-white);
}

.athletes-table tbody tr {
    transition: all 0.3s;
}

.athletes-table tbody tr:hover {
    background: var(--bg-main);
}

.athlete-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.athlete-avatar-small {
    width: 40px;
    height: 40px;
    background: var(--bg-main);
    border: 2px solid var(--border);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: var(--text-dim);
    flex-shrink: 0;
}

.athlete-info {
    display: flex;
    flex-direction: column;
}

.athlete-name {
    font-weight: 700;
    color: var(--text-white);
}

.athlete-email {
    font-size: 12px;
    color: var(--text-dim);
}

.program-badge {
    display: inline-block;
    background: rgba(255, 77, 0, 0.1);
    color: var(--neon);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.sessions-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.sessions-count {
    font-size: 13px;
    font-weight: 700;
}

.mini-progress {
    width: 80px;
    height: 6px;
    background: var(--bg-main);
    border-radius: 3px;
    overflow: hidden;
}

.mini-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--neon), var(--accent));
    border-radius: 3px;
    transition: width 0.5s;
}

.progress-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.progress-badge.excellent {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.progress-badge.good {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.progress-badge.needs-attention {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.table-actions {
    display: flex;
    gap: 5px;
}
</style>
