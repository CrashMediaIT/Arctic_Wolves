<!-- Home Dashboard View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-home"></i> Dashboard
    </h1>
    <p class="page-description">Welcome back! Here's your overview.</p>
</div>

<div class="dashboard-content">
    <!-- Role-specific content will be loaded here -->
    <?php if (isset($_SESSION['role'])): ?>
        
        <?php if ($_SESSION['role'] === 'athlete'): ?>
            <!-- Athlete Dashboard -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-check"></i> Upcoming Sessions</h3>
                    </div>
                    <div class="card-body">
                        <div class="session-list">
                            <p class="placeholder-text">Your upcoming training sessions will appear here.</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-bell"></i> Notifications</h3>
                    </div>
                    <div class="card-body">
                        <p class="placeholder-text">New notifications and updates will appear here.</p>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-comments"></i> Coach Notes</h3>
                    </div>
                    <div class="card-body">
                        <p class="placeholder-text">Recent feedback from your coaches will appear here.</p>
                    </div>
                </div>
            </div>

        <?php elseif (in_array($_SESSION['role'], ['coach', 'health_coach', 'admin'])): ?>
            <!-- Coach/Admin Dashboard -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-alt"></i> Today's Sessions</h3>
                        <button class="btn-secondary"><i class="fas fa-plus"></i> Add Session</button>
                    </div>
                    <div class="card-body">
                        <p class="placeholder-text">Sessions scheduled for today will appear here.</p>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-clock"></i> Athlete Notifications</h3>
                    </div>
                    <div class="card-body">
                        <p class="placeholder-text">Important athlete updates and alerts will appear here.</p>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-clipboard-check"></i> Pending Reviews</h3>
                    </div>
                    <div class="card-body">
                        <p class="placeholder-text">Videos and evaluations awaiting your review will appear here.</p>
                    </div>
                </div>
            </div>

        <?php elseif ($_SESSION['role'] === 'parent'): ?>
            <!-- Parent Dashboard -->
            <div class="parent-dashboard">
                <div class="athlete-selector-card">
                    <h3><i class="fas fa-users"></i> Select Athlete</h3>
                    <select class="form-input" id="athlete-selector">
                        <option value="">-- Select an athlete --</option>
                        <!-- Athletes will be populated here -->
                    </select>
                </div>

                <div id="athlete-dashboard" style="display: none;">
                    <div class="dashboard-grid">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-calendar-check"></i> Upcoming Sessions</h3>
                            </div>
                            <div class="card-body">
                                <p class="placeholder-text">Athlete's upcoming sessions will appear here.</p>
                            </div>
                        </div>

                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-line"></i> Progress Overview</h3>
                            </div>
                            <div class="card-body">
                                <p class="placeholder-text">Athlete's progress and stats will appear here.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<style>
.page-header {
    margin-bottom: 30px;
}

.page-title {
    font-size: 32px;
    font-weight: 900;
    color: var(--text-white);
    margin-bottom: 8px;
}

.page-title i {
    color: var(--neon);
    margin-right: 10px;
}

.page-description {
    font-size: 14px;
    color: var(--text-dim);
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.dashboard-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
}

.card-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
}

.card-header h3 i {
    color: var(--neon);
    margin-right: 8px;
}

.card-body {
    padding: 20px;
}

.placeholder-text {
    color: var(--text-dim);
    font-size: 14px;
    text-align: center;
    padding: 40px 20px;
}

.form-input {
    width: 100%;
    height: 45px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    color: var(--text-white);
    padding: 0 15px;
    border-radius: 4px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
}

.form-input:focus {
    outline: none;
    border-color: var(--neon);
}

.btn-secondary {
    height: 45px;
    padding: 0 20px;
    background: transparent;
    border: 1px solid var(--neon);
    color: var(--neon);
    border-radius: 4px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-secondary:hover {
    background: var(--neon);
    color: #fff;
}

.athlete-selector-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 30px;
    margin-bottom: 20px;
}

.athlete-selector-card h3 {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 15px;
}

.athlete-selector-card h3 i {
    color: var(--neon);
    margin-right: 8px;
}
</style>
