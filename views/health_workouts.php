<!-- Health Workouts View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-dumbbell"></i> Strength & Conditioning
    </h1>
    <p class="page-description">Your personalized workout programs</p>
</div>

<div class="workouts-content">
    <!-- Current Program Card -->
    <div class="current-program-card">
        <div class="program-header">
            <div>
                <h3><i class="fas fa-fire"></i> Active Program</h3>
                <p class="program-name">Off-Season Strength Builder</p>
            </div>
            <button class="btn-primary"><i class="fas fa-play"></i> Start Workout</button>
        </div>
        <div class="program-progress">
            <div class="progress-stats">
                <div class="stat">
                    <span class="stat-value">12</span>
                    <span class="stat-label">Workouts Completed</span>
                </div>
                <div class="stat">
                    <span class="stat-value">4</span>
                    <span class="stat-label">This Week</span>
                </div>
                <div class="stat">
                    <span class="stat-value">75%</span>
                    <span class="stat-label">Program Progress</span>
                </div>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: 75%;"></div>
            </div>
        </div>
    </div>

    <!-- Workout Calendar -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-week"></i> This Week's Schedule</h3>
            <div class="calendar-nav">
                <button class="btn-icon"><i class="fas fa-chevron-left"></i></button>
                <span class="current-week">Week of Jan 15, 2024</span>
                <button class="btn-icon"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
        <div class="card-body">
            <div class="workout-schedule">
                <div class="schedule-day completed">
                    <div class="day-header">
                        <span class="day-name">MON</span>
                        <span class="day-date">15</span>
                    </div>
                    <div class="day-workout">
                        <i class="fas fa-check-circle"></i>
                        <span>Upper Body Strength</span>
                    </div>
                </div>
                <div class="schedule-day completed">
                    <div class="day-header">
                        <span class="day-name">TUE</span>
                        <span class="day-date">16</span>
                    </div>
                    <div class="day-workout">
                        <i class="fas fa-check-circle"></i>
                        <span>Cardio & Core</span>
                    </div>
                </div>
                <div class="schedule-day active">
                    <div class="day-header">
                        <span class="day-name">WED</span>
                        <span class="day-date">17</span>
                    </div>
                    <div class="day-workout">
                        <i class="fas fa-play-circle"></i>
                        <span>Lower Body Power</span>
                    </div>
                </div>
                <div class="schedule-day">
                    <div class="day-header">
                        <span class="day-name">THU</span>
                        <span class="day-date">18</span>
                    </div>
                    <div class="day-workout">
                        <i class="fas fa-circle"></i>
                        <span>Active Recovery</span>
                    </div>
                </div>
                <div class="schedule-day">
                    <div class="day-header">
                        <span class="day-name">FRI</span>
                        <span class="day-date">19</span>
                    </div>
                    <div class="day-workout">
                        <i class="fas fa-circle"></i>
                        <span>Full Body Circuit</span>
                    </div>
                </div>
                <div class="schedule-day rest">
                    <div class="day-header">
                        <span class="day-name">SAT</span>
                        <span class="day-date">20</span>
                    </div>
                    <div class="day-workout">
                        <i class="fas fa-bed"></i>
                        <span>Rest Day</span>
                    </div>
                </div>
                <div class="schedule-day rest">
                    <div class="day-header">
                        <span class="day-name">SUN</span>
                        <span class="day-date">21</span>
                    </div>
                    <div class="day-workout">
                        <i class="fas fa-bed"></i>
                        <span>Rest Day</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Exercise Library -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-book"></i> Exercise Library</h3>
            <div class="filter-group">
                <select class="form-input-small">
                    <option>All Categories</option>
                    <option>Upper Body</option>
                    <option>Lower Body</option>
                    <option>Core</option>
                    <option>Cardio</option>
                    <option>Flexibility</option>
                </select>
                <input type="text" class="form-input-small" placeholder="Search exercises...">
            </div>
        </div>
        <div class="card-body">
            <div class="exercise-grid">
                <!-- Sample Exercise Card -->
                <div class="exercise-card">
                    <div class="exercise-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <h4>Bench Press</h4>
                    <p class="exercise-category">Upper Body</p>
                    <button class="btn-secondary btn-small"><i class="fas fa-play"></i> View Demo</button>
                </div>

                <div class="exercise-card">
                    <div class="exercise-icon">
                        <i class="fas fa-running"></i>
                    </div>
                    <h4>Squats</h4>
                    <p class="exercise-category">Lower Body</p>
                    <button class="btn-secondary btn-small"><i class="fas fa-play"></i> View Demo</button>
                </div>

                <div class="exercise-card">
                    <div class="exercise-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <h4>Planks</h4>
                    <p class="exercise-category">Core</p>
                    <button class="btn-secondary btn-small"><i class="fas fa-play"></i> View Demo</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.current-program-card {
    background: linear-gradient(135deg, rgba(255, 77, 0, 0.1), rgba(255, 157, 0, 0.1));
    border: 1px solid var(--neon);
    border-radius: 8px;
    padding: 30px;
    margin-bottom: 30px;
}

.program-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.program-header h3 {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
}

.program-header h3 i {
    color: var(--neon);
    margin-right: 8px;
}

.program-name {
    font-size: 24px;
    font-weight: 900;
    color: var(--text-white);
}

.progress-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.stat {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 32px;
    font-weight: 900;
    color: var(--neon);
    line-height: 1;
    margin-bottom: 5px;
}

.stat-label {
    display: block;
    font-size: 12px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.progress-bar-container {
    background: var(--bg-main);
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--neon), var(--accent));
    border-radius: 4px;
    transition: width 0.5s;
}

.calendar-nav {
    display: flex;
    align-items: center;
    gap: 15px;
}

.current-week {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-white);
}

.workout-schedule {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
}

.schedule-day {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    transition: all 0.3s;
}

.schedule-day:hover {
    border-color: var(--neon);
}

.schedule-day.completed {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.1);
}

.schedule-day.active {
    border-color: var(--neon);
    background: rgba(255, 77, 0, 0.1);
}

.schedule-day.rest {
    opacity: 0.5;
}

.day-header {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
}

.day-name {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.day-date {
    display: block;
    font-size: 24px;
    font-weight: 900;
    color: var(--text-white);
}

.day-workout {
    font-size: 12px;
    color: var(--text-dim);
}

.day-workout i {
    display: block;
    font-size: 24px;
    margin-bottom: 8px;
}

.schedule-day.completed .day-workout i {
    color: #10b981;
}

.schedule-day.active .day-workout i {
    color: var(--neon);
}

.exercise-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
}

.exercise-card {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s;
}

.exercise-card:hover {
    border-color: var(--neon);
    transform: translateY(-3px);
}

.exercise-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--neon), var(--accent));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    font-size: 24px;
    color: #fff;
}

.exercise-card h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 5px;
}

.exercise-category {
    font-size: 12px;
    color: var(--text-dim);
    margin-bottom: 15px;
}

.btn-small {
    height: 35px;
    padding: 0 15px;
    font-size: 12px;
}
</style>
