<?php
// Determine which tab should be active based on URL parameter
$activeTab = $_GET['tab'] ?? 'skills';
$validTabs = ['skills', 'drills', 'merchandise'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'skills';
}
?>
<!-- Admin Categories Management View - Recreated -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-folder-tree"></i> Category Management</h1>
        <p class="page-description">Manage skills for evaluations, drill types for training, and merchandise categories for the shop</p>
    </div>
</div>

<!-- Category Tabs -->
<div class="page-tabs">
    <button type="button" class="page-tab <?= $activeTab === 'skills' ? 'active' : '' ?>" data-tab="skills" data-action="switch-tab">
        <i class="fas fa-star"></i> Skills
    </button>
    <button type="button" class="page-tab <?= $activeTab === 'drills' ? 'active' : '' ?>" data-tab="drills" data-action="switch-tab">
        <i class="fas fa-hockey-puck"></i> Drill Types
    </button>
    <button type="button" class="page-tab <?= $activeTab === 'merchandise' ? 'active' : '' ?>" data-tab="merchandise" data-action="switch-tab">
        <i class="fas fa-shopping-bag"></i> Merchandise
    </button>
</div>

<div class="page-tab-content">
    <!-- Skills Tab -->
    <div class="tab-content <?= $activeTab === 'skills' ? 'active' : '' ?>" id="skills-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-star"></i> Evaluation Skills</h3>
                <button type="button" class="btn btn-primary" data-action="add" data-modal="add-skill-modal">
                    <i class="fas fa-plus"></i> Add Skill
                </button>
            </div>
            <div class="card-body">
                <p class="info-text">
                    <i class="fas fa-info-circle"></i>
                    Skills defined here are used in athlete evaluation forms to assess player development.
                </p>
                <div class="categories-grid">
                    <?php
                    // Fetch all skills from database
                    $stmt = $pdo->prepare("SELECT es.id, es.name, es.description, ec.name as category_name 
                                          FROM eval_skills es 
                                          LEFT JOIN eval_categories ec ON es.category_id = ec.id 
                                          ORDER BY es.name ASC");
                    $stmt->execute();
                    $skills = $stmt->fetchAll();
                    
                    if (count($skills) > 0):
                        foreach ($skills as $skill):
                    ?>
                    <div class="category-card">
                        <div class="category-card-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="category-card-content">
                            <h4><?= htmlspecialchars($skill['name']) ?></h4>
                            <p><?= htmlspecialchars($skill['description'] ?: 'No description') ?></p>
                            <?php if ($skill['category_name']): ?>
                            <span class="category-tag"><?= htmlspecialchars($skill['category_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="category-card-actions">
                            <button type="button" class="btn-icon" title="Edit" 
                                    data-action="edit" 
                                    data-id="<?= $skill['id'] ?>" 
                                    data-type="skill" 
                                    data-name="<?= htmlspecialchars($skill['name']) ?>"
                                    data-description="<?= htmlspecialchars($skill['description'] ?? '') ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn-icon btn-icon-danger" title="Delete" 
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
                    <div class="empty-state">
                        <i class="fas fa-star"></i>
                        <h4>No Skills Found</h4>
                        <p>Create your first skill to use in athlete evaluations.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Drill Types Tab -->
    <div class="tab-content <?= $activeTab === 'drills' ? 'active' : '' ?>" id="drills-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-hockey-puck"></i> Drill Types</h3>
                <button type="button" class="btn btn-primary" data-action="add" data-modal="add-drill-type-modal">
                    <i class="fas fa-plus"></i> Add Drill Type
                </button>
            </div>
            <div class="card-body">
                <p class="info-text">
                    <i class="fas fa-info-circle"></i>
                    Drill types help categorize and organize training drills by the skills they develop.
                </p>
                <div class="categories-grid">
                    <?php
                    // Fetch all drill categories from database
                    $stmt = $pdo->prepare("SELECT id, name, description FROM drill_categories ORDER BY name ASC");
                    $stmt->execute();
                    $drill_types = $stmt->fetchAll();
                    
                    if (count($drill_types) > 0):
                        foreach ($drill_types as $type):
                    ?>
                    <div class="category-card">
                        <div class="category-card-icon drill-type">
                            <i class="fas fa-hockey-puck"></i>
                        </div>
                        <div class="category-card-content">
                            <h4><?= htmlspecialchars($type['name']) ?></h4>
                            <p><?= htmlspecialchars($type['description'] ?: 'No description') ?></p>
                        </div>
                        <div class="category-card-actions">
                            <button type="button" class="btn-icon" title="Edit" 
                                    data-action="edit" 
                                    data-id="<?= $type['id'] ?>" 
                                    data-type="drill_type" 
                                    data-name="<?= htmlspecialchars($type['name']) ?>"
                                    data-description="<?= htmlspecialchars($type['description'] ?? '') ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn-icon btn-icon-danger" title="Delete" 
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
                    <div class="empty-state">
                        <i class="fas fa-hockey-puck"></i>
                        <h4>No Drill Types Found</h4>
                        <p>Create drill types to organize your training drills.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Merchandise Categories Tab -->
    <div class="tab-content <?= $activeTab === 'merchandise' ? 'active' : '' ?>" id="merchandise-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-shopping-bag"></i> Merchandise Categories</h3>
                <button type="button" class="btn btn-primary" data-action="add" data-modal="add-merchandise-modal">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>
            <div class="card-body">
                <p class="info-text">
                    <i class="fas fa-info-circle"></i>
                    Merchandise categories organize products in the online shop and POS system.
                </p>
                <div class="categories-grid">
                    <?php
                    // Fetch all merchandise categories from database
                    $stmt = $pdo->prepare("SELECT id, name, description, is_active FROM merchandise_categories ORDER BY sort_order, name ASC");
                    $stmt->execute();
                    $merchandise_categories = $stmt->fetchAll();
                    
                    if (count($merchandise_categories) > 0):
                        foreach ($merchandise_categories as $merch):
                    ?>
                    <div class="category-card <?= !$merch['is_active'] ? 'inactive' : '' ?>">
                        <div class="category-card-icon merchandise">
                            <i class="fas fa-tag"></i>
                        </div>
                        <div class="category-card-content">
                            <h4>
                                <?= htmlspecialchars($merch['name']) ?>
                                <?php if (!$merch['is_active']): ?>
                                <span class="status-badge inactive">Inactive</span>
                                <?php endif; ?>
                            </h4>
                            <p><?= htmlspecialchars($merch['description'] ?: 'No description') ?></p>
                        </div>
                        <div class="category-card-actions">
                            <button type="button" class="btn-icon" title="Edit" 
                                    data-action="edit" 
                                    data-id="<?= $merch['id'] ?>" 
                                    data-type="merchandise" 
                                    data-name="<?= htmlspecialchars($merch['name']) ?>"
                                    data-description="<?= htmlspecialchars($merch['description'] ?? '') ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn-icon btn-icon-danger" title="Delete" 
                                    data-action="delete" 
                                    data-id="<?= $merch['id'] ?>" 
                                    data-type="merchandise" 
                                    data-name="<?= htmlspecialchars($merch['name']) ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-bag"></i>
                        <h4>No Merchandise Categories Found</h4>
                        <p>Create categories to organize products in your shop.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Categories Page Styles - Following Style Guide */
.info-text {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    margin-bottom: var(--space-5);
    padding: var(--space-4);
    background: rgba(107, 70, 193, 0.1);
    border-radius: var(--radius-lg);
    color: var(--text-secondary);
    font-size: var(--font-size-sm);
}

.info-text i {
    color: var(--primary-light);
    font-size: 16px;
}

/* Category Cards Grid */
.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: var(--space-4);
}

/* Individual Category Card */
.category-card {
    display: flex;
    align-items: flex-start;
    gap: var(--space-4);
    padding: var(--space-5);
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    transition: all var(--transition-normal);
    position: relative;
}

.category-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--primary);
    border-radius: var(--radius-xl) 0 0 var(--radius-xl);
    opacity: 0;
    transition: opacity var(--transition-normal);
}

.category-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.category-card:hover::before {
    opacity: 1;
}

.category-card.inactive {
    opacity: 0.6;
}

/* Category Card Icon */
.category-card-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: var(--text-white);
    flex-shrink: 0;
    box-shadow: var(--shadow-primary);
}

.category-card-icon.drill-type {
    background: linear-gradient(135deg, #3B82F6, #2563EB);
}

.category-card-icon.merchandise {
    background: linear-gradient(135deg, #10B981, #059669);
}

/* Category Card Content */
.category-card-content {
    flex: 1;
    min-width: 0;
}

.category-card-content h4 {
    font-size: var(--font-size-md);
    font-weight: var(--font-weight-bold);
    color: var(--text-white);
    margin-bottom: var(--space-2);
    display: flex;
    align-items: center;
    gap: var(--space-2);
    flex-wrap: wrap;
}

.category-card-content p {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
    line-height: 1.5;
    margin: 0;
}

.category-tag {
    display: inline-block;
    margin-top: var(--space-2);
    padding: 4px 10px;
    background: rgba(107, 70, 193, 0.15);
    color: var(--primary-light);
    border-radius: var(--radius-md);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
}

/* Category Card Actions */
.category-card-actions {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
}

.btn-icon {
    width: 36px;
    height: 36px;
    padding: 0;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-normal);
}

.btn-icon:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: var(--text-white);
}

.btn-icon-danger:hover {
    background: var(--error);
    border-color: var(--error);
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: var(--radius-sm);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
    text-transform: uppercase;
}

.status-badge.inactive {
    background: rgba(239, 68, 68, 0.15);
    color: var(--error);
}

/* Empty State */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: var(--space-10) var(--space-6);
    background: var(--bg-secondary);
    border: 1px dashed var(--border);
    border-radius: var(--radius-xl);
}

.empty-state i {
    font-size: 48px;
    color: var(--text-muted);
    margin-bottom: var(--space-4);
    display: block;
}

.empty-state h4 {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-bold);
    color: var(--text-white);
    margin-bottom: var(--space-2);
}

.empty-state p {
    font-size: var(--font-size-base);
    color: var(--text-muted);
    margin-bottom: var(--space-5);
}

@media (max-width: 768px) {
    .categories-grid {
        grid-template-columns: 1fr;
    }
    
    .category-card {
        flex-wrap: wrap;
    }
    
    .category-card-actions {
        flex-direction: row;
        width: 100%;
        justify-content: flex-end;
        margin-top: var(--space-3);
    }
}
</style>

<!-- Add Skill Modal -->
<div id="add-skill-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-star"></i> Add Skill</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('add-skill-modal')">&times;</button>
        </div>
        <form id="add-skill-form" method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_skill">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Skill Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="e.g., Skating, Passing, Shooting">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3" placeholder="Describe what this skill evaluates"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-skill-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Skill
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Skill Modal -->
<div id="edit-skill-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Skill</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('edit-skill-modal')">&times;</button>
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
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-skill-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Skill
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Drill Type Modal -->
<div id="add-drill-type-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-hockey-puck"></i> Add Drill Type</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('add-drill-type-modal')">&times;</button>
        </div>
        <form id="add-drill-type-form" method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_drill_type">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Drill Type Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="e.g., Skating, Shooting, Passing">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3" placeholder="Describe what this drill type focuses on"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-drill-type-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Drill Type
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Drill Type Modal -->
<div id="edit-drill-type-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Drill Type</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('edit-drill-type-modal')">&times;</button>
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
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-drill-type-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Drill Type
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Merchandise Category Modal -->
<div id="add-merchandise-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-shopping-bag"></i> Add Merchandise Category</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('add-merchandise-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_merchandise_category">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Category Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="e.g., Apparel, Equipment, Accessories">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3" placeholder="Brief description of this category"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-merchandise-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Merchandise Category Modal -->
<div id="edit-merchandise-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Merchandise Category</h2>
            <button type="button" class="modal-close" aria-label="Close modal" onclick="closeModal('edit-merchandise-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="type" value="merchandise">
            <input type="hidden" name="id" id="edit-merchandise-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Category Name *</label>
                    <input type="text" name="name" id="edit-merchandise-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-merchandise-description" class="form-textarea" rows="3"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-merchandise-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Category
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Initialize event handlers
(function() {
    // Handle edit buttons for all category types
    document.querySelectorAll('[data-action="edit"]').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');
            const name = this.getAttribute('data-name') || '';
            const description = this.getAttribute('data-description') || '';
            
            if (type === 'skill') {
                document.getElementById('edit-skill-id').value = id;
                document.getElementById('edit-skill-name').value = name;
                document.getElementById('edit-skill-description').value = description;
                document.getElementById('edit-skill-modal').classList.add('active');
            } else if (type === 'drill_type') {
                document.getElementById('edit-drill-type-id').value = id;
                document.getElementById('edit-drill-type-name').value = name;
                document.getElementById('edit-drill-type-description').value = description;
                document.getElementById('edit-drill-type-modal').classList.add('active');
            } else if (type === 'merchandise') {
                document.getElementById('edit-merchandise-id').value = id;
                document.getElementById('edit-merchandise-name').value = name;
                document.getElementById('edit-merchandise-description').value = description;
                document.getElementById('edit-merchandise-modal').classList.add('active');
            }
        });
    });
    
    // Handle delete buttons
    document.querySelectorAll('[data-action="delete"]').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');
            const name = this.getAttribute('data-name');
            
            if (confirm('Are you sure you want to delete "' + name + '"? This action cannot be undone.')) {
                const csrfInput = document.querySelector('input[name="csrf_token"]');
                const csrfToken = csrfInput ? csrfInput.value : '';
                
                fetch('process_admin_action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'action=delete&id=' + encodeURIComponent(id) + '&type=' + encodeURIComponent(type) + '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Deleted successfully!', 'success');
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        showNotification('Error: ' + (data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    showNotification('An error occurred', 'error');
                });
            }
        });
    });
    
    // Tab switching
    document.querySelectorAll('[data-action="switch-tab"]').forEach(button => {
        button.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            // Remove active from all tab buttons and contents
            document.querySelectorAll('.page-tab').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Add active to clicked button and corresponding content
            this.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Update URL without page reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url);
        });
    });
    
    // Handle add buttons to open modals
    document.querySelectorAll('[data-action="add"][data-modal]').forEach(button => {
        button.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal');
            if (modalId) {
                openModal(modalId);
            }
        });
    });
})();

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
    // Escape message to prevent XSS (including single quotes)
    var escapedMessage = message.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
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
        
        fetch(form.getAttribute('action'), {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) {
            // Check if response is ok
            if (!response.ok) {
                throw new Error('Server responded with status: ' + response.status);
            }
            return response.text();
        })
        .then(function(text) {
            // Try to parse as JSON
            try {
                return JSON.parse(text);
            } catch (e) {
                // Don't log full response for security - it may contain sensitive info
                console.error('Invalid JSON response received');
                throw new Error('Server returned invalid response');
            }
        })
        .then(function(data) {
            if (submitBtn) {
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
            }
            
            if (data.success) {
                showNotification(data.message || 'Operation completed successfully!', 'success');
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
            showNotification('Error: ' + error.message, 'error');
        });
    });
});

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = '';
        document.body.style.overflow = '';
        var form = modal.querySelector('form');
        if (form) form.reset();
    }
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}
</script>
