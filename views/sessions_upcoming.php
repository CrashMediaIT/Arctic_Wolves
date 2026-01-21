<!-- Upcoming Sessions View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-calendar-alt"></i> Upcoming Sessions
    </h1>
    <p class="page-description">Your scheduled training sessions</p>
</div>

<div class="sessions-content">
    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="filter-group">
            <label>Filter by:</label>
            <select class="form-input-small">
                <option>All Sessions</option>
                <option>This Week</option>
                <option>Next Week</option>
                <option>This Month</option>
            </select>
            <select class="form-input-small">
                <option>All Coaches</option>
                <!-- Coaches will be populated here -->
            </select>
        </div>
        <button class="btn-primary"><i class="fas fa-plus"></i> Book Session</button>
    </div>

    <!-- Sessions List -->
    <div class="sessions-list">
        <!-- Sample Session Card -->
        <div class="session-card">
            <div class="session-date">
                <div class="date-box">
                    <span class="date-day">15</span>
                    <span class="date-month">JAN</span>
                </div>
            </div>
            <div class="session-details">
                <h3 class="session-title">Sample Training Session</h3>
                <div class="session-meta">
                    <span><i class="fas fa-clock"></i> 3:00 PM - 4:30 PM</span>
                    <span><i class="fas fa-user"></i> Coach Name</span>
                    <span><i class="fas fa-map-marker-alt"></i> Main Rink</span>
                </div>
                <div class="session-tags">
                    <span class="tag">Skating</span>
                    <span class="tag">Shooting</span>
                </div>
            </div>
            <div class="session-actions">
                <button class="btn-secondary"><i class="fas fa-eye"></i> View</button>
                <button class="btn-danger"><i class="fas fa-times"></i> Cancel</button>
            </div>
        </div>

        <div class="placeholder-container">
            <p class="placeholder-text">No upcoming sessions found. Book a session to get started!</p>
        </div>
    </div>
</div>

<style>
.filter-bar {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-group label {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-dim);
}

.sessions-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.session-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    display: flex;
    gap: 20px;
    align-items: center;
    transition: all 0.3s;
}

.session-card:hover {
    border-color: var(--neon);
    box-shadow: 0 4px 20px rgba(255, 77, 0, 0.1);
}

.date-box {
    background: linear-gradient(135deg, var(--neon), var(--accent));
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    min-width: 80px;
}

.date-day {
    display: block;
    font-size: 28px;
    font-weight: 900;
    color: #fff;
    line-height: 1;
}

.date-month {
    display: block;
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    margin-top: 5px;
}

.session-details {
    flex: 1;
}

.session-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 10px;
}

.session-meta {
    display: flex;
    gap: 20px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.session-meta span {
    font-size: 14px;
    color: var(--text-dim);
}

.session-meta i {
    color: var(--neon);
    margin-right: 5px;
}

.session-tags {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.tag {
    background: rgba(255, 77, 0, 0.1);
    color: var(--neon);
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
}

.session-actions {
    display: flex;
    gap: 10px;
}

.btn-danger {
    height: 45px;
    padding: 0 20px;
    background: transparent;
    border: 1px solid #ef4444;
    color: #ef4444;
    border-radius: 4px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-danger:hover {
    background: #ef4444;
    color: #fff;
}

.placeholder-container {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 60px 20px;
}
</style>
