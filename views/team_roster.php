<!-- Team Roster View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-users"></i> Team Roster
    </h1>
    <p class="page-description">Manage your team members</p>
</div>

<div class="roster-content">
    <!-- Team Info Card -->
    <div class="team-info-card">
        <div class="team-details">
            <h3>Bantam AA - Blue Devils</h3>
            <div class="team-stats">
                <span><i class="fas fa-users"></i> 18 Players</span>
                <span><i class="fas fa-calendar"></i> 2023-2024 Season</span>
                <span><i class="fas fa-trophy"></i> Division Leaders</span>
            </div>
        </div>
        <button class="btn-primary"><i class="fas fa-user-plus"></i> Add Player</button>
    </div>

    <!-- Filter and Search -->
    <div class="filter-bar">
        <div class="filter-group">
            <select class="form-input-small">
                <option>All Positions</option>
                <option>Forward</option>
                <option>Defense</option>
                <option>Goalie</option>
            </select>
            <input type="text" class="form-input-small" placeholder="Search players...">
        </div>
        <div class="view-toggle">
            <button class="view-btn active"><i class="fas fa-th"></i></button>
            <button class="view-btn"><i class="fas fa-list"></i></button>
        </div>
    </div>

    <!-- Roster Grid -->
    <div class="roster-grid">
        <!-- Sample Player Card -->
        <div class="player-card">
            <div class="player-number">12</div>
            <div class="player-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h4 class="player-name">John Smith</h4>
            <div class="player-position">Center</div>
            <div class="player-stats">
                <div class="stat-item">
                    <span class="stat-value">15</span>
                    <span class="stat-label">Goals</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">22</span>
                    <span class="stat-label">Assists</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">37</span>
                    <span class="stat-label">Points</span>
                </div>
            </div>
            <div class="player-actions">
                <button class="btn-secondary btn-small"><i class="fas fa-eye"></i> View Profile</button>
            </div>
        </div>

        <div class="player-card">
            <div class="player-number">7</div>
            <div class="player-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h4 class="player-name">Sarah Johnson</h4>
            <div class="player-position">Defense</div>
            <div class="player-stats">
                <div class="stat-item">
                    <span class="stat-value">3</span>
                    <span class="stat-label">Goals</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">18</span>
                    <span class="stat-label">Assists</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">21</span>
                    <span class="stat-label">Points</span>
                </div>
            </div>
            <div class="player-actions">
                <button class="btn-secondary btn-small"><i class="fas fa-eye"></i> View Profile</button>
            </div>
        </div>

        <div class="player-card">
            <div class="player-number">31</div>
            <div class="player-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h4 class="player-name">Mike Williams</h4>
            <div class="player-position">Goalie</div>
            <div class="player-stats">
                <div class="stat-item">
                    <span class="stat-value">.915</span>
                    <span class="stat-label">Save %</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">2.45</span>
                    <span class="stat-label">GAA</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">12</span>
                    <span class="stat-label">Wins</span>
                </div>
            </div>
            <div class="player-actions">
                <button class="btn-secondary btn-small"><i class="fas fa-eye"></i> View Profile</button>
            </div>
        </div>
    </div>
</div>

<style>
.team-info-card {
    background: linear-gradient(135deg, rgba(255, 77, 0, 0.1), rgba(255, 157, 0, 0.1));
    border: 1px solid var(--neon);
    border-radius: 8px;
    padding: 30px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.team-details h3 {
    font-size: 28px;
    font-weight: 900;
    color: var(--text-white);
    margin-bottom: 12px;
}

.team-stats {
    display: flex;
    gap: 25px;
    flex-wrap: wrap;
}

.team-stats span {
    font-size: 14px;
    color: var(--text-dim);
}

.team-stats i {
    color: var(--neon);
    margin-right: 5px;
}

.view-toggle {
    display: flex;
    gap: 5px;
}

.view-btn {
    width: 40px;
    height: 40px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    color: var(--text-dim);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
}

.view-btn:hover,
.view-btn.active {
    background: var(--neon);
    border-color: var(--neon);
    color: #fff;
}

.roster-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.player-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 25px;
    text-align: center;
    position: relative;
    transition: all 0.3s;
}

.player-card:hover {
    border-color: var(--neon);
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(255, 77, 0, 0.2);
}

.player-number {
    position: absolute;
    top: 15px;
    right: 15px;
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--neon), var(--accent));
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 900;
    color: #fff;
}

.player-avatar {
    width: 80px;
    height: 80px;
    background: var(--bg-main);
    border: 3px solid var(--border);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    font-size: 32px;
    color: var(--text-dim);
}

.player-name {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 5px;
}

.player-position {
    font-size: 12px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border);
}

.player-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat-item {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 20px;
    font-weight: 900;
    color: var(--neon);
    line-height: 1;
    margin-bottom: 5px;
}

.stat-label {
    display: block;
    font-size: 11px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.player-actions {
    padding-top: 15px;
    border-top: 1px solid var(--border);
}
</style>
