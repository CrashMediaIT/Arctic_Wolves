<!-- Stats & Performance View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-chart-line"></i> Performance Stats
    </h1>
    <p class="page-description">Track your progress and achieve your goals</p>
</div>

<div class="stats-content">
    <!-- Stats Overview Cards -->
    <div class="stats-overview">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-bullseye"></i></div>
            <div class="stat-details">
                <h4>Goals Completed</h4>
                <p class="stat-value">0 / 0</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-fire"></i></div>
            <div class="stat-details">
                <h4>Training Streak</h4>
                <p class="stat-value">0 days</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-trophy"></i></div>
            <div class="stat-details">
                <h4>Skills Mastered</h4>
                <p class="stat-value">0</p>
            </div>
        </div>
    </div>

    <!-- Goals Tracker -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-bullseye"></i> Goals Tracker</h3>
            <button class="btn-primary"><i class="fas fa-plus"></i> Add Goal</button>
        </div>
        <div class="card-body">
            <p class="placeholder-text">Your performance goals will be tracked here.</p>
        </div>
    </div>

    <!-- Performance Metrics -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Performance Metrics</h3>
            <div class="filter-group">
                <select class="form-input-small">
                    <option>Last 7 Days</option>
                    <option>Last 30 Days</option>
                    <option>Last 90 Days</option>
                    <option>All Time</option>
                </select>
            </div>
        </div>
        <div class="card-body">
            <p class="placeholder-text">Performance charts and metrics will appear here.</p>
        </div>
    </div>

    <!-- Skills Progress -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-tasks"></i> Skills Progress</h3>
        </div>
        <div class="card-body">
            <p class="placeholder-text">Track your skill development across different areas.</p>
        </div>
    </div>
</div>

<style>
.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.stat-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--neon), var(--accent));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
}

.stat-details h4 {
    font-size: 14px;
    color: var(--text-dim);
    margin-bottom: 5px;
}

.stat-value {
    font-size: 24px;
    font-weight: 900;
    color: var(--text-white);
}

.content-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
}

.btn-primary {
    height: 45px;
    padding: 0 20px;
    background: var(--neon);
    border: none;
    color: #fff;
    border-radius: 4px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-primary:hover {
    box-shadow: 0 0 15px rgba(255, 77, 0, 0.4);
    transform: translateY(-1px);
}

.filter-group {
    display: flex;
    gap: 10px;
}

.form-input-small {
    height: 45px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    color: var(--text-white);
    padding: 0 15px;
    border-radius: 4px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
}
</style>
