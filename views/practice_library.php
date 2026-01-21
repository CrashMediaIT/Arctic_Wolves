<!-- Practice Library View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-clipboard-list"></i> Practice Plans
    </h1>
    <p class="page-description">Browse and manage your practice plans</p>
</div>

<div class="practice-content">
    <!-- Actions Bar -->
    <div class="action-bar">
        <div class="filter-group">
            <input type="text" class="form-input-small" placeholder="Search practice plans...">
            <select class="form-input-small">
                <option>All Teams</option>
                <!-- Teams will be populated here -->
            </select>
            <select class="form-input-small">
                <option>All Seasons</option>
                <option>2023-2024</option>
                <option>2022-2023</option>
            </select>
        </div>
        <button class="btn-primary"><i class="fas fa-plus"></i> Create Practice Plan</button>
    </div>

    <!-- Practice Plans List -->
    <div class="practice-list">
        <!-- Sample Practice Plan Card -->
        <div class="practice-card">
            <div class="practice-header">
                <div class="practice-date">
                    <div class="date-box">
                        <span class="date-day">18</span>
                        <span class="date-month">JAN</span>
                    </div>
                </div>
                <div class="practice-title-section">
                    <h3 class="practice-title">Power Play Development</h3>
                    <div class="practice-meta">
                        <span><i class="fas fa-users"></i> Bantam AA</span>
                        <span><i class="fas fa-clock"></i> 90 minutes</span>
                        <span><i class="fas fa-map-marker-alt"></i> Main Rink</span>
                    </div>
                </div>
                <div class="practice-status">
                    <span class="status-badge upcoming">Upcoming</span>
                </div>
            </div>
            <div class="practice-body">
                <div class="practice-drills">
                    <h4><i class="fas fa-list-ul"></i> Drills (5)</h4>
                    <div class="drill-list-compact">
                        <div class="drill-item-compact">
                            <span class="drill-time">5 min</span>
                            <span class="drill-name">Dynamic Warmup</span>
                        </div>
                        <div class="drill-item-compact">
                            <span class="drill-time">15 min</span>
                            <span class="drill-name">Figure 8 Skating</span>
                        </div>
                        <div class="drill-item-compact">
                            <span class="drill-time">20 min</span>
                            <span class="drill-name">PP Zone Entry Drills</span>
                        </div>
                        <div class="drill-item-compact">
                            <span class="drill-time">30 min</span>
                            <span class="drill-name">5v4 Power Play Situations</span>
                        </div>
                        <div class="drill-item-compact">
                            <span class="drill-time">20 min</span>
                            <span class="drill-name">Scrimmage</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="practice-actions">
                <button class="btn-secondary"><i class="fas fa-eye"></i> View</button>
                <button class="btn-secondary"><i class="fas fa-edit"></i> Edit</button>
                <button class="btn-secondary"><i class="fas fa-copy"></i> Duplicate</button>
                <button class="btn-secondary"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>

        <div class="practice-card">
            <div class="practice-header">
                <div class="practice-date">
                    <div class="date-box completed">
                        <span class="date-day">15</span>
                        <span class="date-month">JAN</span>
                    </div>
                </div>
                <div class="practice-title-section">
                    <h3 class="practice-title">Defensive Zone Coverage</h3>
                    <div class="practice-meta">
                        <span><i class="fas fa-users"></i> Bantam AA</span>
                        <span><i class="fas fa-clock"></i> 90 minutes</span>
                        <span><i class="fas fa-map-marker-alt"></i> Main Rink</span>
                    </div>
                </div>
                <div class="practice-status">
                    <span class="status-badge completed">Completed</span>
                </div>
            </div>
            <div class="practice-body">
                <div class="practice-drills">
                    <h4><i class="fas fa-list-ul"></i> Drills (6)</h4>
                    <div class="drill-list-compact">
                        <div class="drill-item-compact">
                            <span class="drill-time">5 min</span>
                            <span class="drill-name">Skating Warmup</span>
                        </div>
                        <div class="drill-item-compact">
                            <span class="drill-time">15 min</span>
                            <span class="drill-name">Edge Work Drills</span>
                        </div>
                        <div class="drill-item-compact">
                            <span class="drill-time">20 min</span>
                            <span class="drill-name">1v1 Defensive Positioning</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="practice-actions">
                <button class="btn-secondary"><i class="fas fa-eye"></i> View</button>
                <button class="btn-secondary"><i class="fas fa-copy"></i> Duplicate</button>
                <button class="btn-secondary"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>

        <div class="placeholder-container">
            <i class="fas fa-clipboard-list placeholder-icon"></i>
            <p class="placeholder-text">No practice plans found. Click "Create Practice Plan" to get started.</p>
        </div>
    </div>
</div>

<style>
.practice-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.practice-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s;
}

.practice-card:hover {
    border-color: var(--neon);
    box-shadow: 0 4px 20px rgba(255, 77, 0, 0.1);
}

.practice-header {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 25px;
    background: var(--bg-main);
    border-bottom: 1px solid var(--border);
}

.practice-date {
    flex-shrink: 0;
}

.date-box.completed {
    background: #10b981;
}

.practice-title-section {
    flex: 1;
}

.practice-title {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 8px;
}

.practice-meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.practice-meta span {
    font-size: 14px;
    color: var(--text-dim);
}

.practice-meta i {
    color: var(--neon);
    margin-right: 5px;
}

.practice-status {
    flex-shrink: 0;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.upcoming {
    background: rgba(255, 77, 0, 0.1);
    color: var(--neon);
}

.status-badge.completed {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-badge.draft {
    background: rgba(148, 163, 184, 0.1);
    color: var(--text-dim);
}

.practice-body {
    padding: 25px;
}

.practice-drills h4 {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 15px;
}

.practice-drills h4 i {
    color: var(--neon);
    margin-right: 8px;
}

.drill-list-compact {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.drill-item-compact {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 10px 15px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 4px;
}

.drill-time {
    font-size: 12px;
    font-weight: 700;
    color: var(--neon);
    min-width: 50px;
}

.drill-name {
    font-size: 14px;
    color: var(--text-white);
}

.practice-actions {
    padding: 20px 25px;
    background: var(--bg-main);
    border-top: 1px solid var(--border);
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
</style>
