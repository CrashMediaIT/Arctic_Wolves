<!-- Admin Categories Management View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-tags"></i> Category Management
    </h1>
    <p class="page-description">Manage system categories and classifications</p>
</div>

<div class="categories-content">
    <!-- Category Tabs -->
    <div class="category-tabs">
        <button class="tab-btn active" data-tab="skills">
            <i class="fas fa-star"></i> Skills
        </button>
        <button class="tab-btn" data-tab="drills">
            <i class="fas fa-hockey-puck"></i> Drill Types
        </button>
        <button class="tab-btn" data-tab="positions">
            <i class="fas fa-user-tag"></i> Positions
        </button>
        <button class="tab-btn" data-tab="equipment">
            <i class="fas fa-tools"></i> Equipment
        </button>
    </div>

    <!-- Skills Tab -->
    <div class="tab-content active" id="skills-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-star"></i> Skill Categories</h3>
                <button class="btn-primary"><i class="fas fa-plus"></i> Add Skill</button>
            </div>
            <div class="card-body">
                <div class="categories-list">
                    <div class="category-item">
                        <div class="category-icon"><i class="fas fa-skating"></i></div>
                        <div class="category-info">
                            <h4>Skating</h4>
                            <p>Speed, agility, edge work, transitions</p>
                        </div>
                        <div class="category-actions">
                            <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="category-item">
                        <div class="category-icon"><i class="fas fa-hockey-puck"></i></div>
                        <div class="category-info">
                            <h4>Shooting</h4>
                            <p>Wrist shot, slap shot, snapshot, accuracy</p>
                        </div>
                        <div class="category-actions">
                            <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="category-item">
                        <div class="category-icon"><i class="fas fa-exchange-alt"></i></div>
                        <div class="category-info">
                            <h4>Passing</h4>
                            <p>Tape to tape, saucer pass, breakout passes</p>
                        </div>
                        <div class="category-actions">
                            <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Drill Types Tab -->
    <div class="tab-content" id="drills-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-hockey-puck"></i> Drill Type Categories</h3>
                <button class="btn-primary"><i class="fas fa-plus"></i> Add Type</button>
            </div>
            <div class="card-body">
                <p class="placeholder-text">Drill type categories will be managed here.</p>
            </div>
        </div>
    </div>

    <!-- Positions Tab -->
    <div class="tab-content" id="positions-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-user-tag"></i> Player Positions</h3>
                <button class="btn-primary"><i class="fas fa-plus"></i> Add Position</button>
            </div>
            <div class="card-body">
                <p class="placeholder-text">Player position categories will be managed here.</p>
            </div>
        </div>
    </div>

    <!-- Equipment Tab -->
    <div class="tab-content" id="equipment-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-tools"></i> Equipment Types</h3>
                <button class="btn-primary"><i class="fas fa-plus"></i> Add Equipment</button>
            </div>
            <div class="card-body">
                <p class="placeholder-text">Equipment type categories will be managed here.</p>
            </div>
        </div>
    </div>
</div>

<style>
.category-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.categories-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.category-item {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    transition: all 0.3s;
}

.category-item:hover {
    border-color: var(--neon);
}

.category-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--neon), var(--accent));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: #fff;
    flex-shrink: 0;
}

.category-info {
    flex: 1;
}

.category-info h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 5px;
}

.category-info p {
    font-size: 14px;
    color: var(--text-dim);
}

.category-actions {
    display: flex;
    gap: 8px;
}
</style>
