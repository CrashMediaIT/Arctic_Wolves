<!-- Import Drill from IHS View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-file-import"></i> Import from IHS
    </h1>
    <p class="page-description">Import drills from Ice Hockey Systems database</p>
</div>

<div class="import-content">
    <!-- IHS Connection Status -->
    <div class="connection-status-card">
        <div class="status-icon connected">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="status-details">
            <h3>Connected to IHS Database</h3>
            <p>Access to 2,500+ professional hockey drills</p>
        </div>
        <button class="btn-secondary"><i class="fas fa-sync"></i> Refresh Connection</button>
    </div>

    <!-- Search and Filter -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-search"></i> Search IHS Drills</h3>
        </div>
        <div class="card-body">
            <div class="search-form">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" class="form-input" placeholder="Search by drill name or keyword...">
                    </div>
                    <button class="btn-primary"><i class="fas fa-search"></i> Search</button>
                </div>
                <div class="filter-row">
                    <select class="form-input-small">
                        <option>All Categories</option>
                        <option>Skating</option>
                        <option>Shooting</option>
                        <option>Passing</option>
                        <option>Team Play</option>
                        <option>Goalie</option>
                    </select>
                    <select class="form-input-small">
                        <option>All Age Groups</option>
                        <option>Mite (U8)</option>
                        <option>Squirt (U10)</option>
                        <option>Peewee (U12)</option>
                        <option>Bantam (U14)</option>
                        <option>Midget (U16)</option>
                        <option>Junior (U18)</option>
                    </select>
                    <select class="form-input-small">
                        <option>All Skill Levels</option>
                        <option>Beginner</option>
                        <option>Intermediate</option>
                        <option>Advanced</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Results -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Available Drills</h3>
            <div class="results-info">
                <span>Showing 0 results</span>
            </div>
        </div>
        <div class="card-body">
            <!-- Sample IHS Drill Items -->
            <div class="ihs-drill-list">
                <div class="ihs-drill-item">
                    <div class="drill-preview">
                        <div class="drill-thumbnail">
                            <i class="fas fa-hockey-puck"></i>
                        </div>
                        <span class="drill-id">IHS-SK-001</span>
                    </div>
                    <div class="drill-info">
                        <h4>Swedish 5-Puck Weave</h4>
                        <div class="drill-tags">
                            <span class="tag-category">Skating</span>
                            <span class="tag-level">Intermediate</span>
                            <span class="tag-duration"><i class="fas fa-clock"></i> 12 min</span>
                        </div>
                        <p class="drill-preview-text">Classic Swedish drill focusing on edge work, puck control, and agility through cone weaving patterns.</p>
                    </div>
                    <div class="drill-import-actions">
                        <button class="btn-secondary"><i class="fas fa-eye"></i> Preview</button>
                        <button class="btn-primary"><i class="fas fa-download"></i> Import</button>
                    </div>
                </div>

                <div class="ihs-drill-item">
                    <div class="drill-preview">
                        <div class="drill-thumbnail">
                            <i class="fas fa-hockey-puck"></i>
                        </div>
                        <span class="drill-id">IHS-SH-042</span>
                    </div>
                    <div class="drill-info">
                        <h4>One-Timer Power Play Setup</h4>
                        <div class="drill-tags">
                            <span class="tag-category">Shooting</span>
                            <span class="tag-level">Advanced</span>
                            <span class="tag-duration"><i class="fas fa-clock"></i> 15 min</span>
                        </div>
                        <p class="drill-preview-text">Develops quick release and power play positioning with emphasis on timing and accuracy.</p>
                    </div>
                    <div class="drill-import-actions">
                        <button class="btn-secondary"><i class="fas fa-eye"></i> Preview</button>
                        <button class="btn-primary"><i class="fas fa-download"></i> Import</button>
                    </div>
                </div>

                <div class="placeholder-container">
                    <i class="fas fa-search placeholder-icon"></i>
                    <p class="placeholder-text">Use the search filters above to find drills from the IHS database.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recently Imported -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recently Imported</h3>
        </div>
        <div class="card-body">
            <div class="recent-imports-list">
                <p class="placeholder-text">No recent imports. Drills you import will appear here.</p>
            </div>
        </div>
    </div>
</div>

<style>
.connection-status-card {
    background: var(--bg-card);
    border: 1px solid #10b981;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.status-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    flex-shrink: 0;
}

.status-icon.connected {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-icon.disconnected {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.status-details {
    flex: 1;
}

.status-details h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 5px;
}

.status-details p {
    font-size: 14px;
    color: var(--text-dim);
}

.search-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.filter-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.results-info {
    font-size: 14px;
    color: var(--text-dim);
}

.ihs-drill-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.ihs-drill-item {
    display: flex;
    gap: 20px;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    align-items: center;
    transition: all 0.3s;
}

.ihs-drill-item:hover {
    border-color: var(--neon);
    box-shadow: 0 4px 20px rgba(255, 77, 0, 0.1);
}

.drill-preview {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.drill-thumbnail {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, rgba(255, 77, 0, 0.1), rgba(255, 157, 0, 0.1));
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
}

.drill-thumbnail i {
    font-size: 36px;
    color: var(--neon);
    opacity: 0.5;
}

.drill-id {
    font-size: 11px;
    color: var(--text-dim);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.drill-info {
    flex: 1;
}

.drill-info h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 10px;
}

.drill-tags {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.tag-category {
    background: rgba(255, 77, 0, 0.1);
    color: var(--neon);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.tag-level {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.tag-duration {
    color: var(--text-dim);
    font-size: 12px;
    padding: 4px 0;
}

.tag-duration i {
    color: var(--neon);
    margin-right: 5px;
}

.drill-preview-text {
    font-size: 14px;
    color: var(--text-dim);
    line-height: 1.5;
}

.drill-import-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.recent-imports-list {
    min-height: 100px;
}
</style>
