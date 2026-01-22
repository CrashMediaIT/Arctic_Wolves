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
        <button class="tab-btn active" data-tab="skills" data-action="switch-tab">
            <i class="fas fa-star"></i> Skills
        </button>
        <button class="tab-btn" data-tab="drills" data-action="switch-tab">
            <i class="fas fa-hockey-puck"></i> Drill Types
        </button>
        <button class="tab-btn" data-tab="positions" data-action="switch-tab">
            <i class="fas fa-user-tag"></i> Positions
        </button>
        <button class="tab-btn" data-tab="equipment" data-action="switch-tab">
            <i class="fas fa-tools"></i> Equipment
        </button>
    </div>

    <!-- Skills Tab -->
    <div class="tab-content active" id="skills-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-star"></i> Skill Categories</h3>
                <button class="btn-primary" data-action="add" data-modal="add-skill-modal"><i class="fas fa-plus"></i> Add Skill</button>
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
                            <button class="btn-icon" title="Edit" data-action="edit" data-id="skill-1" data-type="skill"><i class="fas fa-edit"></i></button>
                            <button class="btn-icon" title="Delete" data-action="delete" data-id="skill-1" data-type="skill" data-name="Skating"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="category-item">
                        <div class="category-icon"><i class="fas fa-hockey-puck"></i></div>
                        <div class="category-info">
                            <h4>Shooting</h4>
                            <p>Wrist shot, slap shot, snapshot, accuracy</p>
                        </div>
                        <div class="category-actions">
                            <button class="btn-icon" title="Edit" data-action="edit" data-id="skill-2" data-type="skill"><i class="fas fa-edit"></i></button>
                            <button class="btn-icon" title="Delete" data-action="delete" data-id="skill-2" data-type="skill" data-name="Shooting"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="category-item">
                        <div class="category-icon"><i class="fas fa-exchange-alt"></i></div>
                        <div class="category-info">
                            <h4>Passing</h4>
                            <p>Tape to tape, saucer pass, breakout passes</p>
                        </div>
                        <div class="category-actions">
                            <button class="btn-icon" title="Edit" data-action="edit" data-id="skill-3" data-type="skill"><i class="fas fa-edit"></i></button>
                            <button class="btn-icon" title="Delete" data-action="delete" data-id="skill-3" data-type="skill" data-name="Passing"><i class="fas fa-trash"></i></button>
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
                <button class="btn-primary" data-action="add" data-modal="add-drill-type-modal"><i class="fas fa-plus"></i> Add Type</button>
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
                <button class="btn-primary" data-action="add" data-modal="add-position-modal"><i class="fas fa-plus"></i> Add Position</button>
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
                <button class="btn-primary" data-action="add" data-modal="add-equipment-modal"><i class="fas fa-plus"></i> Add Equipment</button>
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
    margin-bottom: 24px;
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

<!-- Add Skill Modal -->
<div id="add-skill-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Skill Category</h2>
            <button class="modal-close" onclick="closeModal('add-skill-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_skill">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Skill Name *</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Icon (Font Awesome class)</label>
                    <input type="text" name="icon" class="form-input" placeholder="e.g., fa-skating">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('add-skill-modal')">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Skill</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Drill Type Modal -->
<div id="add-drill-type-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Drill Type</h2>
            <button class="modal-close" onclick="closeModal('add-drill-type-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_drill_type">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Drill Type Name *</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Icon (Font Awesome class)</label>
                    <input type="text" name="icon" class="form-input" placeholder="e.g., fa-hockey-puck">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('add-drill-type-modal')">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Drill Type</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Position Modal -->
<div id="add-position-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Player Position</h2>
            <button class="modal-close" onclick="closeModal('add-position-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_position">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Position Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="e.g., Center, Left Wing">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Abbreviation</label>
                    <input type="text" name="abbreviation" class="form-input" placeholder="e.g., C, LW, RW">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('add-position-modal')">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Position</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Equipment Modal -->
<div id="add-equipment-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Equipment</h2>
            <button class="modal-close" onclick="closeModal('add-equipment-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_equipment">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Equipment Name *</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Icon (Font Awesome class)</label>
                    <input type="text" name="icon" class="form-input" placeholder="e.g., fa-tools">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('add-equipment-modal')">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Equipment</button>
            </div>
        </form>
    </div>
</div>
