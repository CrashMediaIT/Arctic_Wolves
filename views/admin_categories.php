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
                            <button class="btn-icon" title="Edit" data-action="edit" data-id="<?= $skill['id'] ?>" data-type="skill"><i class="fas fa-edit"></i></button>
                            <button class="btn-icon" title="Delete" data-action="delete" data-id="<?= $skill['id'] ?>" data-type="skill" data-name="<?= htmlspecialchars($skill['name']) ?>"><i class="fas fa-trash"></i></button>
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
    <div class="tab-content" id="drills-tab">
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
                            <button class="btn-icon" title="Edit" data-action="edit" data-id="<?= $type['id'] ?>" data-type="drill_type"><i class="fas fa-edit"></i></button>
                            <button class="btn-icon" title="Delete" data-action="delete" data-id="<?= $type['id'] ?>" data-type="drill_type" data-name="<?= htmlspecialchars($type['name']) ?>"><i class="fas fa-trash"></i></button>
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
    <div class="tab-content" id="positions-tab">
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
                            <button class="btn-icon" title="Edit" data-action="edit" data-id="<?= $position['id'] ?>" data-type="position"><i class="fas fa-edit"></i></button>
                            <button class="btn-icon" title="Delete" data-action="delete" data-id="<?= $position['id'] ?>" data-type="position" data-name="<?= htmlspecialchars($position['name']) ?>"><i class="fas fa-trash"></i></button>
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
    <div class="tab-content" id="equipment-tab">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-tools"></i> Equipment Types</h3>
                <button class="btn-primary" data-action="add" data-modal="add-equipment-modal"><i class="fas fa-plus"></i> Add Equipment</button>
            </div>
            <div class="card-body">
                <div class="categories-list">
                    <?php
                    // Fetch equipment categories (marked with equipment_type = 'category')
                    // Performance optimization suggestion: Consider adding compound index on (equipment_type, created_at) for faster filtering and ordering
                    $stmt = $pdo->prepare("SELECT id, name, notes FROM equipment WHERE equipment_type = 'category' ORDER BY created_at DESC");
                    $stmt->execute();
                    $equipment_items = $stmt->fetchAll();
                    
                    if (count($equipment_items) > 0):
                        foreach ($equipment_items as $item):
                    ?>
                    <div class="category-item">
                        <div class="category-icon"><i class="fas fa-tools"></i></div>
                        <div class="category-info">
                            <h4><?= htmlspecialchars($item['name']) ?></h4>
                            <p><?= htmlspecialchars($item['notes'] ?: 'No description') ?></p>
                        </div>
                        <div class="category-actions">
                            <button class="btn-icon" title="Edit" data-action="edit" data-id="<?= $item['id'] ?>" data-type="equipment"><i class="fas fa-edit"></i></button>
                            <button class="btn-icon" title="Delete" data-action="delete" data-id="<?= $item['id'] ?>" data-type="equipment" data-name="<?= htmlspecialchars($item['name']) ?>"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <p class="placeholder-text">No equipment types found. Click "Add Equipment" to create your first equipment type.</p>
                    <?php endif; ?>
                </div>
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

<!-- Edit Position Modal -->
<div id="edit-position-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Edit Player Position</h2>
            <button class="modal-close" onclick="closeModal('edit-position-modal')">&times;</button>
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
                <button type="button" class="btn-secondary" onclick="closeModal('edit-position-modal')">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update Position</button>
            </div>
        </form>
    </div>
</div>

<script>
// Handle edit and delete actions for all category types
document.addEventListener('DOMContentLoaded', function() {
    // Handle edit buttons
    document.querySelectorAll('[data-action="edit"]').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');
            
            if (type === 'position') {
                // Fetch position data and populate edit modal
                const item = this.closest('.category-item');
                const name = item.querySelector('h4').textContent.trim().split('(')[0].trim();
                const description = item.querySelector('p').textContent;
                
                // Simple approach: populate from DOM
                document.getElementById('edit-position-id').value = id;
                document.getElementById('edit-position-name').value = name;
                document.getElementById('edit-position-description').value = description !== 'No description' ? description : '';
                
                // Show modal
                document.getElementById('edit-position-modal').style.display = 'block';
            }
        });
    });
    
    // Handle delete buttons
    document.querySelectorAll('[data-action="delete"]').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');
            const name = this.getAttribute('data-name');
            
            if (type === 'position') {
                if (confirm(`Are you sure you want to delete the position "${name}"?`)) {
                    // Send delete request
                    fetch('process_admin_action.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete_position&id=${id}&<?php echo csrfTokenInput(); ?>`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while deleting the position.');
                    });
                }
            }
        });
    });
});
</script>
