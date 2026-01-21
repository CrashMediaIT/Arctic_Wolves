<!-- Drills Library View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-book-open"></i> Drill Library
    </h1>
    <p class="page-description">Browse and search hockey drills</p>
</div>

<div class="drills-content">
    <!-- Actions Bar -->
    <div class="action-bar">
        <div class="filter-group">
            <input type="text" class="form-input-small" placeholder="Search drills...">
            <select class="form-input-small">
                <option>All Categories</option>
                <option>Skating</option>
                <option>Shooting</option>
                <option>Passing</option>
                <option>Stickhandling</option>
                <option>Defensive</option>
                <option>Offensive</option>
                <option>Conditioning</option>
            </select>
            <select class="form-input-small">
                <option>All Skill Levels</option>
                <option>Beginner</option>
                <option>Intermediate</option>
                <option>Advanced</option>
            </select>
        </div>
        <div class="action-buttons">
            <button class="btn-secondary"><i class="fas fa-download"></i> Import from IHS</button>
            <button class="btn-primary"><i class="fas fa-plus"></i> Create Drill</button>
        </div>
    </div>

    <!-- Drills Grid -->
    <div class="drills-grid">
        <!-- Sample Drill Card -->
        <div class="drill-card">
            <div class="drill-image">
                <div class="drill-diagram">
                    <i class="fas fa-hockey-puck"></i>
                </div>
                <span class="drill-level intermediate">Intermediate</span>
            </div>
            <div class="drill-content">
                <div class="drill-header">
                    <h4 class="drill-title">Figure 8 Skating Drill</h4>
                    <div class="drill-category">
                        <span class="category-badge">Skating</span>
                    </div>
                </div>
                <p class="drill-description">Improves edge control and crossover technique through continuous figure-8 patterns.</p>
                <div class="drill-meta">
                    <span><i class="fas fa-clock"></i> 10 min</span>
                    <span><i class="fas fa-users"></i> 1-20 players</span>
                    <span><i class="fas fa-star"></i> 4.5</span>
                </div>
            </div>
            <div class="drill-actions">
                <button class="btn-secondary btn-small"><i class="fas fa-eye"></i> View</button>
                <button class="btn-icon" title="Add to Practice"><i class="fas fa-plus"></i></button>
                <button class="btn-icon" title="Favorite"><i class="far fa-heart"></i></button>
            </div>
        </div>

        <div class="drill-card">
            <div class="drill-image">
                <div class="drill-diagram">
                    <i class="fas fa-hockey-puck"></i>
                </div>
                <span class="drill-level advanced">Advanced</span>
            </div>
            <div class="drill-content">
                <div class="drill-header">
                    <h4 class="drill-title">2-on-1 Rush Defense</h4>
                    <div class="drill-category">
                        <span class="category-badge">Defensive</span>
                    </div>
                </div>
                <p class="drill-description">Develops defensive positioning and gap control in odd-man rush situations.</p>
                <div class="drill-meta">
                    <span><i class="fas fa-clock"></i> 15 min</span>
                    <span><i class="fas fa-users"></i> 6-18 players</span>
                    <span><i class="fas fa-star"></i> 4.8</span>
                </div>
            </div>
            <div class="drill-actions">
                <button class="btn-secondary btn-small"><i class="fas fa-eye"></i> View</button>
                <button class="btn-icon" title="Add to Practice"><i class="fas fa-plus"></i></button>
                <button class="btn-icon" title="Favorite"><i class="far fa-heart"></i></button>
            </div>
        </div>

        <div class="drill-card">
            <div class="drill-image">
                <div class="drill-diagram">
                    <i class="fas fa-hockey-puck"></i>
                </div>
                <span class="drill-level beginner">Beginner</span>
            </div>
            <div class="drill-content">
                <div class="drill-header">
                    <h4 class="drill-title">Stationary Passing</h4>
                    <div class="drill-category">
                        <span class="category-badge">Passing</span>
                    </div>
                </div>
                <p class="drill-description">Basic passing fundamentals focusing on hand positioning and puck control.</p>
                <div class="drill-meta">
                    <span><i class="fas fa-clock"></i> 8 min</span>
                    <span><i class="fas fa-users"></i> 2-20 players</span>
                    <span><i class="fas fa-star"></i> 4.2</span>
                </div>
            </div>
            <div class="drill-actions">
                <button class="btn-secondary btn-small"><i class="fas fa-eye"></i> View</button>
                <button class="btn-icon" title="Add to Practice"><i class="fas fa-plus"></i></button>
                <button class="btn-icon" title="Favorite"><i class="far fa-heart"></i></button>
            </div>
        </div>
    </div>
</div>

<style>
.action-buttons {
    display: flex;
    gap: 10px;
}

.drills-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 25px;
}

.drill-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s;
}

.drill-card:hover {
    border-color: var(--neon);
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(255, 77, 0, 0.2);
}

.drill-image {
    position: relative;
    width: 100%;
    padding-top: 60%;
    background: var(--bg-main);
}

.drill-diagram {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(255, 77, 0, 0.1), rgba(255, 157, 0, 0.1));
}

.drill-diagram i {
    font-size: 48px;
    color: var(--neon);
    opacity: 0.3;
}

.drill-level {
    position: absolute;
    top: 15px;
    right: 15px;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.drill-level.beginner {
    background: #10b981;
    color: #fff;
}

.drill-level.intermediate {
    background: #f59e0b;
    color: #fff;
}

.drill-level.advanced {
    background: #ef4444;
    color: #fff;
}

.drill-content {
    padding: 20px;
}

.drill-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 12px;
    gap: 10px;
}

.drill-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    flex: 1;
}

.drill-category {
    display: flex;
    gap: 5px;
}

.category-badge {
    background: rgba(255, 77, 0, 0.1);
    color: var(--neon);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.drill-description {
    font-size: 14px;
    color: var(--text-dim);
    line-height: 1.5;
    margin-bottom: 15px;
}

.drill-meta {
    display: flex;
    gap: 15px;
    font-size: 13px;
    color: var(--text-dim);
    padding-top: 15px;
    border-top: 1px solid var(--border);
}

.drill-meta i {
    color: var(--neon);
    margin-right: 5px;
}

.drill-actions {
    padding: 15px 20px;
    background: var(--bg-main);
    border-top: 1px solid var(--border);
    display: flex;
    gap: 10px;
}
</style>
