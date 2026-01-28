<?php
// Determine which tab should be active based on URL parameter
$activeTab = $_GET['tab'] ?? 'skills';
$validTabs = ['skills', 'drills', 'positions', 'equipment'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'skills';
}
?>
<!-- Admin Categories Management View -->
<div class="page-header categories-page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-tags"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">Category Management</h1>
            <p class="page-description">Organize and manage system categories, skills, and classifications</p>
        </div>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value" id="total-skills-count">-</span>
            <span class="stat-label">Skills</span>
        </div>
        <div class="header-stat">
            <span class="stat-value" id="total-drills-count">-</span>
            <span class="stat-label">Drill Types</span>
        </div>
        <div class="header-stat">
            <span class="stat-value" id="total-positions-count">-</span>
            <span class="stat-label">Positions</span>
        </div>
        <div class="header-stat">
            <span class="stat-value" id="total-equipment-count">-</span>
            <span class="stat-label">Equipment</span>
        </div>
    </div>
</div>

<div class="categories-content">
    <!-- Category Tabs - Modern Tab Navigation -->
    <div class="category-tabs-wrapper">
        <div class="category-tabs">
            <button class="tab-btn <?= $activeTab === 'skills' ? 'active' : '' ?>" data-tab="skills" data-action="switch-tab">
                <span class="tab-icon"><i class="fas fa-star"></i></span>
                <span class="tab-text">Skills</span>
            </button>
            <button class="tab-btn <?= $activeTab === 'drills' ? 'active' : '' ?>" data-tab="drills" data-action="switch-tab">
                <span class="tab-icon"><i class="fas fa-hockey-puck"></i></span>
                <span class="tab-text">Drill Types</span>
            </button>
            <button class="tab-btn <?= $activeTab === 'positions' ? 'active' : '' ?>" data-tab="positions" data-action="switch-tab">
                <span class="tab-icon"><i class="fas fa-user-tag"></i></span>
                <span class="tab-text">Positions</span>
            </button>
            <button class="tab-btn <?= $activeTab === 'equipment' ? 'active' : '' ?>" data-tab="equipment" data-action="switch-tab">
                <span class="tab-icon"><i class="fas fa-toolbox"></i></span>
                <span class="tab-text">Equipment</span>
            </button>
        </div>
    </div>

    <!-- Skills Tab -->
    <div class="tab-content <?= $activeTab === 'skills' ? 'active' : '' ?>" id="skills-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-star"></i> Skill Categories</h3>
                <button class="btn-primary" data-action="add" data-modal="add-skill-modal"><i class="fas fa-plus"></i> Add Skill</button>
            </div>
            <div class="card-body">
                <div class="categories-list">
                    <?php
                    // Fetch all skills from database
                    // Performance optimization suggestion: Consider adding index on eval_skills.created_at for faster ordering
                    $stmt = $pdo->prepare("SELECT es.id, es.name, es.description, ec.name as category_name 
                                          FROM eval_skills es 
                                          LEFT JOIN eval_categories ec ON es.category_id = ec.id 
                                          ORDER BY es.created_at DESC");
                    $stmt->execute();
                    $skills = $stmt->fetchAll();
                    
                    if (count($skills) > 0):
                        foreach ($skills as $skill):
                    ?>
                    <div class="category-item">
                        <div class="category-icon"><i class="fas fa-star"></i></div>
                        <div class="category-info">
                            <h4><?= htmlspecialchars($skill['name']) ?></h4>
                            <p><?= htmlspecialchars($skill['description'] ?: 'No description') ?></p>
                            <?php if ($skill['category_name']): ?>
                            <small style="color: var(--text-dim);">Category: <?= htmlspecialchars($skill['category_name']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="category-actions">
                            <button class="btn-icon" title="Edit" 
                                    data-action="edit" 
                                    data-id="<?= $skill['id'] ?>" 
                                    data-type="skill" 
                                    data-name="<?= htmlspecialchars($skill['name']) ?>"
                                    data-description="<?= htmlspecialchars($skill['description'] ?? '') ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon" title="Delete" 
                                    data-action="delete" 
                                    data-id="<?= $skill['id'] ?>" 
                                    data-type="skill" 
                                    data-name="<?= htmlspecialchars($skill['name']) ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <p class="placeholder-text">No skills found. Click "Add Skill" to create your first skill.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Drill Types Tab -->
    <div class="tab-content <?= $activeTab === 'drills' ? 'active' : '' ?>" id="drills-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-hockey-puck"></i> Drill Type Categories</h3>
                <button class="btn-primary" data-action="add" data-modal="add-drill-type-modal"><i class="fas fa-plus"></i> Add Type</button>
            </div>
            <div class="card-body">
                <div class="categories-list">
                    <?php
                    // Fetch all drill categories from database
                    $stmt = $pdo->prepare("SELECT id, name, description FROM drill_categories ORDER BY created_at DESC");
                    $stmt->execute();
                    $drill_types = $stmt->fetchAll();
                    
                    if (count($drill_types) > 0):
                        foreach ($drill_types as $type):
                    ?>
                    <div class="category-item">
                        <div class="category-icon"><i class="fas fa-hockey-puck"></i></div>
                        <div class="category-info">
                            <h4><?= htmlspecialchars($type['name']) ?></h4>
                            <p><?= htmlspecialchars($type['description'] ?: 'No description') ?></p>
                        </div>
                        <div class="category-actions">
                            <button class="btn-icon" title="Edit" 
                                    data-action="edit" 
                                    data-id="<?= $type['id'] ?>" 
                                    data-type="drill_type" 
                                    data-name="<?= htmlspecialchars($type['name']) ?>"
                                    data-description="<?= htmlspecialchars($type['description'] ?? '') ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon" title="Delete" 
                                    data-action="delete" 
                                    data-id="<?= $type['id'] ?>" 
                                    data-type="drill_type" 
                                    data-name="<?= htmlspecialchars($type['name']) ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <p class="placeholder-text">No drill types found. Click "Add Type" to create your first drill type.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Positions Tab -->
    <div class="tab-content <?= $activeTab === 'positions' ? 'active' : '' ?>" id="positions-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-user-tag"></i> Player Positions</h3>
                <button class="btn-primary" data-action="add" data-modal="add-position-modal"><i class="fas fa-plus"></i> Add Position</button>
            </div>
            <div class="card-body">
                <div class="categories-list">
                    <?php
                    // Fetch all positions from database
                    $stmt = $pdo->prepare("SELECT id, name, abbreviation, description, position_type FROM player_positions ORDER BY position_type, name");
                    $stmt->execute();
                    $positions = $stmt->fetchAll();
                    
                    if (count($positions) > 0):
                        foreach ($positions as $position):
                    ?>
                    <div class="category-item">
                        <div class="category-icon"><i class="fas fa-user-tag"></i></div>
                        <div class="category-info">
                            <h4><?= htmlspecialchars($position['name']) ?> 
                                <?php if ($position['abbreviation']): ?>
                                    <span style="color: var(--text-dim); font-weight: 400;">(<?= htmlspecialchars($position['abbreviation']) ?>)</span>
                                <?php endif; ?>
                            </h4>
                            <p><?= htmlspecialchars($position['description'] ?: 'No description') ?></p>
                            <?php if ($position['position_type']): ?>
                            <small style="color: var(--text-dim);">Type: <?= ucfirst($position['position_type']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="category-actions">
                            <button class="btn-icon" title="Edit" 
                                    data-action="edit" 
                                    data-id="<?= $position['id'] ?>" 
                                    data-type="position"
                                    data-name="<?= htmlspecialchars($position['name']) ?>"
                                    data-abbreviation="<?= htmlspecialchars($position['abbreviation']) ?>"
                                    data-description="<?= htmlspecialchars($position['description']) ?>"
                                    data-position-type="<?= htmlspecialchars($position['position_type']) ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon" title="Delete" 
                                    data-action="delete" 
                                    data-id="<?= $position['id'] ?>" 
                                    data-type="position" 
                                    data-name="<?= htmlspecialchars($position['name']) ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <p class="placeholder-text">No positions found. Click "Add Position" to create your first position.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Equipment Tab -->
    <div class="tab-content <?= $activeTab === 'equipment' ? 'active' : '' ?>" id="equipment-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-toolbox"></i> Equipment Categories</h3>
                <button class="btn-primary" data-action="add" data-modal="add-equipment-modal"><i class="fas fa-plus"></i> Add Equipment</button>
            </div>
            <div class="card-body">
                <p class="info-text" style="margin-bottom: 16px; padding: 12px; background: rgba(107, 70, 193, 0.1); border-radius: 8px; color: var(--text-secondary); font-size: 13px;">
                    <i class="fas fa-info-circle" style="color: var(--primary-light); margin-right: 8px;"></i>
                    Equipment categories defined here will be available when creating drills. Add equipment items that coaches commonly use during practice.
                </p>
                <div class="categories-list">
                    <?php
                    // Fetch all equipment categories from database
                    // Equipment used for drills is stored in the equipment table with equipment_type = 'category'
                    $stmt = $pdo->prepare("SELECT id, name, notes as description FROM equipment WHERE equipment_type = 'category' ORDER BY name ASC");
                    $stmt->execute();
                    $equipment_items = $stmt->fetchAll();
                    
                    if (count($equipment_items) > 0):
                        foreach ($equipment_items as $equip):
                    ?>
                    <div class="category-item">
                        <div class="category-icon"><i class="fas fa-toolbox"></i></div>
                        <div class="category-info">
                            <h4><?= htmlspecialchars($equip['name']) ?></h4>
                            <p><?= htmlspecialchars($equip['description'] ?: 'No description') ?></p>
                        </div>
                        <div class="category-actions">
                            <button class="btn-icon" title="Edit" 
                                    data-action="edit" 
                                    data-id="<?= $equip['id'] ?>" 
                                    data-type="equipment" 
                                    data-name="<?= htmlspecialchars($equip['name']) ?>"
                                    data-description="<?= htmlspecialchars($equip['description'] ?? '') ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon" title="Delete" 
                                    data-action="delete" 
                                    data-id="<?= $equip['id'] ?>" 
                                    data-type="equipment" 
                                    data-name="<?= htmlspecialchars($equip['name']) ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <p class="placeholder-text">No equipment found. Click "Add Equipment" to create your first equipment category.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Categories Page Enhanced Styles */
.categories-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
}

.page-header-content {
    display: flex;
    align-items: center;
    gap: 20px;
}

.page-header-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    box-shadow: 0 8px 24px rgba(107, 70, 193, 0.3);
}

.page-header-text h1 {
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 4px 0;
    letter-spacing: -0.5px;
}

.page-header-text p {
    font-size: 14px;
    color: var(--text-secondary);
    margin: 0;
}

.page-header-stats {
    display: flex;
    gap: 24px;
}

.header-stat {
    text-align: center;
    padding: 12px 20px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    min-width: 90px;
}

.header-stat .stat-value {
    display: block;
    font-size: 24px;
    font-weight: 700;
    color: var(--primary-light);
}

.header-stat .stat-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Category Tabs Wrapper */
.category-tabs-wrapper {
    margin-bottom: 24px;
}

.category-tabs {
    display: flex;
    gap: 8px;
    padding: 6px;
    background: var(--bg-card);
    border-radius: 12px;
    border: 1px solid var(--border);
}

.category-tabs .tab-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px 20px;
    background: transparent;
    border: none;
    border-radius: 8px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
}

.category-tabs .tab-btn:hover {
    background: rgba(107, 70, 193, 0.1);
    color: var(--text-primary);
}

.category-tabs .tab-btn.active {
    background: var(--primary);
    color: #fff;
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.3);
}

.category-tabs .tab-btn .tab-icon {
    font-size: 16px;
}

.categories-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}

.category-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 20px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.category-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--primary);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.category-item:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

.category-item:hover::before {
    opacity: 1;
}

.category-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: #fff;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.25);
}

.category-info {
    flex: 1;
    min-width: 0;
}

.category-info h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.category-info h4 span {
    font-weight: 400;
    font-size: 13px;
}

.category-info p {
    font-size: 13px;
    color: var(--text-muted);
    line-height: 1.5;
    margin: 0 0 8px 0;
}

.category-info small {
    display: inline-block;
    padding: 4px 10px;
    background: rgba(107, 70, 193, 0.1);
    color: var(--primary-light);
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

.category-actions {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.category-actions .btn-icon {
    width: 36px;
    height: 36px;
    padding: 0;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.category-actions .btn-icon:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

.category-actions .btn-icon[title="Delete"]:hover {
    background: var(--error);
    border-color: var(--error);
}

/* Content Card Enhancements */
.content-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
}

.content-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    background: linear-gradient(180deg, rgba(107, 70, 193, 0.08) 0%, transparent 100%);
    border-bottom: 1px solid var(--border);
}

.content-card .card-header h3 {
    font-size: 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
}

.content-card .card-header h3 i {
    color: var(--primary-light);
}

.content-card .card-body {
    padding: 24px;
}

/* Empty State Enhancement */
.placeholder-text {
    text-align: center;
    padding: 48px 24px;
    color: var(--text-muted);
    font-size: 14px;
}

@media (max-width: 768px) {
    .categories-page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .page-header-content {
        flex-direction: column;
        align-items: flex-start;
        text-align: left;
    }
    
    .page-header-stats {
        width: 100%;
        justify-content: space-between;
    }
    
    .category-tabs {
        flex-direction: column;
    }
    
    .categories-list {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Add Skill Modal -->
<div id="add-skill-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Skill Category</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('add-skill-modal')">&times;</button>
        </div>
        <form id="add-skill-form" method="POST" action="process_admin_action.php">
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
                <button type="button" class="btn-secondary" onclick="closeModal('add-skill-modal')"><i class="fas fa-times"></i> Cancel</button>
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
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('add-drill-type-modal')">&times;</button>
        </div>
        <form id="add-drill-type-form" method="POST" action="process_admin_action.php">
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
                <button type="button" class="btn-secondary" onclick="closeModal('add-drill-type-modal')"><i class="fas fa-times"></i> Cancel</button>
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
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('add-position-modal')">&times;</button>
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
                    <label class="form-label">Position Type</label>
                    <select name="position_type" class="form-input">
                        <option value="">Select Type</option>
                        <option value="forward">Forward</option>
                        <option value="defense">Defense</option>
                        <option value="goalie">Goalie</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('add-position-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Position</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Position Modal -->
<div id="edit-position-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Edit Player Position</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('edit-position-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update_position">
            <input type="hidden" name="id" id="edit-position-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Position Name *</label>
                    <input type="text" name="name" id="edit-position-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Abbreviation</label>
                    <input type="text" name="abbreviation" id="edit-position-abbreviation" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Position Type</label>
                    <select name="position_type" id="edit-position-type" class="form-input">
                        <option value="">Select Type</option>
                        <option value="forward">Forward</option>
                        <option value="defense">Defense</option>
                        <option value="goalie">Goalie</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-position-description" class="form-textarea" rows="3"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('edit-position-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update Position</button>
            </div>
        </form>
    </div>
</div>

<script>
// Handle edit and delete actions for all category types
document.addEventListener('DOMContentLoaded', function() {
    // Update stats counts in the header
    updateStatsCounts();
    
    // Handle edit buttons for all category types
    document.querySelectorAll('[data-action="edit"]').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');
            const name = this.getAttribute('data-name') || '';
            const description = this.getAttribute('data-description') || '';
            
            if (type === 'position') {
                const abbreviation = this.getAttribute('data-abbreviation') || '';
                const positionType = this.getAttribute('data-position-type') || '';
                
                document.getElementById('edit-position-id').value = id;
                document.getElementById('edit-position-name').value = name;
                document.getElementById('edit-position-abbreviation').value = abbreviation;
                document.getElementById('edit-position-description').value = description;
                document.getElementById('edit-position-type').value = positionType;
                
                document.getElementById('edit-position-modal').classList.add('active');
            } else if (type === 'skill') {
                document.getElementById('edit-skill-id').value = id;
                document.getElementById('edit-skill-name').value = name;
                document.getElementById('edit-skill-description').value = description;
                
                document.getElementById('edit-skill-modal').classList.add('active');
            } else if (type === 'drill_type') {
                document.getElementById('edit-drill-type-id').value = id;
                document.getElementById('edit-drill-type-name').value = name;
                document.getElementById('edit-drill-type-description').value = description;
                
                document.getElementById('edit-drill-type-modal').classList.add('active');
            } else if (type === 'equipment') {
                document.getElementById('edit-equipment-id').value = id;
                document.getElementById('edit-equipment-name').value = name;
                document.getElementById('edit-equipment-description').value = description;
                
                document.getElementById('edit-equipment-modal').classList.add('active');
            }
        });
    });
    
    // Handle delete buttons with CSRF token
    document.querySelectorAll('[data-action="delete"]').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');
            const name = this.getAttribute('data-name');
            
            if (confirm(`Are you sure you want to delete "${name}"?`)) {
                const csrfInput = document.querySelector('input[name="csrf_token"]');
                const csrfToken = csrfInput ? csrfInput.value : '';
                
                let actionName = 'delete';
                let actionType = type;
                
                if (type === 'position') {
                    actionName = 'delete_position';
                }
                
                fetch('process_admin_action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=${actionName}&id=${id}&type=${actionType}&csrf_token=${csrfToken}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Try redirect-based deletion as fallback
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'process_admin_action.php';
                    
                    form.innerHTML = `
                        <input type="hidden" name="action" value="${actionName}">
                        <input type="hidden" name="id" value="${id}">
                        <input type="hidden" name="type" value="${actionType}">
                        <input type="hidden" name="csrf_token" value="${csrfToken}">
                    `;
                    
                    document.body.appendChild(form);
                    form.submit();
                });
            }
        });
    });
    
    // Tab switching
    document.querySelectorAll('[data-action="switch-tab"]').forEach(button => {
        button.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            // Remove active from all tab buttons and contents
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Add active to clicked button and corresponding content
            this.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        });
    });
});

function updateStatsCounts() {
    // Count items in each tab
    const skillsCount = document.querySelectorAll('#skills-tab .category-item').length;
    const drillsCount = document.querySelectorAll('#drills-tab .category-item').length;
    const positionsCount = document.querySelectorAll('#positions-tab .category-item').length;
    const equipmentCount = document.querySelectorAll('#equipment-tab .category-item').length;
    
    // Update the header stats
    const skillsEl = document.getElementById('total-skills-count');
    const drillsEl = document.getElementById('total-drills-count');
    const positionsEl = document.getElementById('total-positions-count');
    const equipmentEl = document.getElementById('total-equipment-count');
    
    if (skillsEl) skillsEl.textContent = skillsCount;
    if (drillsEl) drillsEl.textContent = drillsCount;
    if (positionsEl) positionsEl.textContent = positionsCount;
    if (equipmentEl) equipmentEl.textContent = equipmentCount;
}

// Show notification helper
function showNotification(message, type) {
    var existing = document.querySelector('.notification-widget');
    if (existing) existing.remove();
    
    var div = document.createElement('div');
    div.className = 'notification-widget';
    div.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; padding: 16px 24px; border-radius: 8px; display: flex; align-items: center; gap: 12px;';
    if (type === 'success') {
        div.style.background = 'rgba(16, 185, 129, 0.95)';
        div.style.color = '#fff';
    } else {
        div.style.background = 'rgba(239, 68, 68, 0.95)';
        div.style.color = '#fff';
    }
    // Escape message to prevent XSS
    var escapedMessage = message.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    div.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + escapedMessage + '<button onclick="this.parentElement.remove()" style="margin-left: 16px; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">&times;</button>';
    document.body.appendChild(div);
    setTimeout(function() { if (div.parentElement) div.remove(); }, 5000);
}

// Convert modal forms to AJAX submissions
document.querySelectorAll('.modal form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(form);
        var modal = form.closest('.modal');
        var submitBtn = form.querySelector('button[type="submit"]');
        var originalBtnText = submitBtn ? submitBtn.innerHTML : '';
        
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
        }
        
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (submitBtn) {
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
            }
            
            if (data.success) {
                showNotification(data.message || 'Created successfully!', 'success');
                if (modal) closeModal(modal.id);
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showNotification('Error: ' + (data.message || 'Operation failed'), 'error');
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            if (submitBtn) {
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
            }
            showNotification('An error occurred', 'error');
        });
    });
});

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        var form = modal.querySelector('form');
        if (form) form.reset();
    }
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    }
}
</script>

<!-- Edit Skill Modal -->
<div id="edit-skill-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Edit Skill</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('edit-skill-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="type" value="skill">
            <input type="hidden" name="id" id="edit-skill-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Skill Name *</label>
                    <input type="text" name="name" id="edit-skill-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-skill-description" class="form-textarea" rows="3"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('edit-skill-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update Skill</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Drill Type Modal -->
<div id="edit-drill-type-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Edit Drill Type</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('edit-drill-type-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="type" value="drill_type">
            <input type="hidden" name="id" id="edit-drill-type-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Drill Type Name *</label>
                    <input type="text" name="name" id="edit-drill-type-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-drill-type-description" class="form-textarea" rows="3"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('edit-drill-type-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update Drill Type</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Equipment Modal -->
<div id="add-equipment-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Equipment Category</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('add-equipment-modal')">&times;</button>
        </div>
        <form id="add-equipment-form" method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_equipment">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Equipment Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="e.g., Pucks, Cones, Nets">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3" placeholder="Brief description of this equipment"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('add-equipment-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Equipment</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Equipment Modal -->
<div id="edit-equipment-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Edit Equipment</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('edit-equipment-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="type" value="equipment">
            <input type="hidden" name="id" id="edit-equipment-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Equipment Name *</label>
                    <input type="text" name="name" id="edit-equipment-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-equipment-description" class="form-textarea" rows="3"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('edit-equipment-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update Equipment</button>
            </div>
        </form>
    </div>
</div>
