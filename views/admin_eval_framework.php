<!-- Admin Evaluation Framework View -->
<?php
// Fetch categories and skills from database
try {
    // Get all categories and their skills in a single query to avoid N+1 problem
    $stmt = $pdo->prepare("
        SELECT 
            c.id as category_id,
            c.name as category_name,
            c.description as category_description,
            c.display_order as category_order,
            s.id as skill_id,
            s.name as skill_name,
            s.description as skill_description,
            s.display_order as skill_order
        FROM eval_categories c
        LEFT JOIN eval_skills s ON c.id = s.category_id
        ORDER BY c.display_order ASC, c.id ASC, s.display_order ASC, s.id ASC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group results by category
    $categories = [];
    $skillsByCategory = [];
    
    foreach ($rows as $row) {
        $catId = $row['category_id'];
        
        // Add category if not already added
        if (!isset($categories[$catId])) {
            $categories[$catId] = [
                'id' => $row['category_id'],
                'name' => $row['category_name'],
                'description' => $row['category_description'],
                'display_order' => $row['category_order']
            ];
            $skillsByCategory[$catId] = [];
        }
        
        // Add skill if exists
        if ($row['skill_id']) {
            $skillsByCategory[$catId][] = [
                'id' => $row['skill_id'],
                'name' => $row['skill_name'],
                'description' => $row['skill_description'],
                'display_order' => $row['skill_order']
            ];
        }
    }
    
    $total_categories = count($categories);
    $total_skills = 0;
    foreach ($skillsByCategory as $skills) {
        $total_skills += count($skills);
    }
    
    // Get all available skills from the Skills Library (Categories view)
    // Shows all skills with their current category assignment (if any) to allow reassignment
    $stmt = $pdo->prepare("
        SELECT es.id, es.name, es.description, ec.name as current_category
        FROM eval_skills es
        LEFT JOIN eval_categories ec ON es.category_id = ec.id
        ORDER BY es.name ASC
    ");
    $stmt->execute();
    $allSkillsLibrary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $categories = [];
    $skillsByCategory = [];
    $total_categories = 0;
    $total_skills = 0;
    $allSkillsLibrary = [];
}
?>

<div class="eval-page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-clipboard-check"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">Evaluation Framework</h1>
            <p class="page-description">Build and manage athlete evaluation criteria, categories, and scoring scales</p>
        </div>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value"><?php echo $total_categories; ?></span>
            <span class="stat-label">Categories</span>
        </div>
        <div class="header-stat">
            <span class="stat-value"><?php echo $total_skills; ?></span>
            <span class="stat-label">Criteria</span>
        </div>
        <div class="header-stat">
            <span class="stat-value">2</span>
            <span class="stat-label">Scales</span>
        </div>
    </div>
</div>

<div class="eval-framework-content">
    <!-- Framework Builder -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-tools"></i> Framework Builder</h3>
            <div class="card-header-actions">
                <a href="?page=categories&tab=skills" class="btn-secondary btn-sm"><i class="fas fa-tags"></i> Manage Skills Library</a>
                <button class="btn-primary" data-action="add" data-modal="add-eval-category-modal"><i class="fas fa-plus"></i> Add Evaluation Category</button>
            </div>
        </div>
        <div class="card-body">
            <div class="framework-tree">
                <?php if (empty($categories)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-check" style="font-size: 48px; color: var(--text-dim); margin-bottom: 16px;"></i>
                        <p>No evaluation categories yet. Click "Add Evaluation Category" to get started.</p>
                        <p style="font-size: 13px; color: var(--text-dim); margin-top: 8px;">
                            First, <a href="?page=categories&tab=skills" style="color: var(--primary-light);">add skills to your library</a>, then create evaluation categories here.
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <!-- Category -->
                        <div class="framework-category" data-category-id="<?php echo $category['id']; ?>">
                            <div class="category-header">
                                <div class="category-title">
                                    <i class="fas fa-clipboard-list"></i>
                                    <h4><?php echo htmlspecialchars($category['name']); ?></h4>
                                    <span class="criteria-count"><?php echo count($skillsByCategory[$category['id']] ?? []); ?> criteria</span>
                                </div>
                                <div class="category-actions">
                                    <button class="btn-icon btn-scale" title="Assign Scale to Category" data-action="assign-scale" data-category-id="<?php echo $category['id']; ?>"><i class="fas fa-star-half-alt"></i></button>
                                    <button class="btn-icon" title="Add Criteria from Library" data-action="add-skill" data-category-id="<?php echo $category['id']; ?>"><i class="fas fa-plus"></i></button>
                                    <button class="btn-icon" title="Edit" data-action="edit-category" data-category-id="<?php echo $category['id']; ?>"><i class="fas fa-edit"></i></button>
                                    <button class="btn-icon" title="Delete" data-action="delete-category" data-category-id="<?php echo $category['id']; ?>"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                            <div class="criteria-list">
                                <?php 
                                $skills = $skillsByCategory[$category['id']] ?? [];
                                if (empty($skills)): 
                                ?>
                                    <div class="empty-criteria">
                                        <p style="color: var(--text-dim); font-size: 13px; text-align: center; padding: 20px;">No criteria in this category yet. Click <i class="fas fa-plus"></i> to add from Skills Library.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($skills as $skill): ?>
                                        <div class="criteria-item" data-skill-id="<?php echo $skill['id']; ?>">
                                            <div class="criteria-handle"><i class="fas fa-grip-vertical"></i></div>
                                            <div class="criteria-details">
                                                <span class="criteria-name"><?php echo htmlspecialchars($skill['name']); ?></span>
                                            </div>
                                            <div class="criteria-actions">
                                                <button class="btn-icon btn-scale-sm" title="Set Scale" data-action="set-skill-scale" data-skill-id="<?php echo $skill['id']; ?>"><i class="fas fa-star-half-alt"></i></button>
                                                <button class="btn-icon" title="Edit" data-action="edit-skill" data-skill-id="<?php echo $skill['id']; ?>"><i class="fas fa-edit"></i></button>
                                                <button class="btn-icon" title="Delete" data-action="delete-skill" data-skill-id="<?php echo $skill['id']; ?>"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scoring Scales -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-star-half-alt"></i> Scoring Scales</h3>
            <button class="btn-primary" data-action="add" data-modal="add-scale-modal"><i class="fas fa-plus"></i> Add Scale</button>
        </div>
        <div class="card-body">
            <div class="scales-grid">
                <div class="scale-card">
                    <h4>1-5 Scale (Default)</h4>
                    <div class="scale-levels">
                        <div class="scale-level">1 - Needs Improvement</div>
                        <div class="scale-level">2 - Below Average</div>
                        <div class="scale-level">3 - Average</div>
                        <div class="scale-level">4 - Above Average</div>
                        <div class="scale-level">5 - Excellent</div>
                    </div>
                    <button class="btn-secondary btn-small" data-action="edit" data-id="1" data-modal="edit-scale-modal"><i class="fas fa-edit"></i> Edit</button>
                </div>

                <div class="scale-card">
                    <h4>10 Point Scale</h4>
                    <div class="scale-levels">
                        <div class="scale-level">1-2 - Poor</div>
                        <div class="scale-level">3-4 - Fair</div>
                        <div class="scale-level">5-6 - Good</div>
                        <div class="scale-level">7-8 - Very Good</div>
                        <div class="scale-level">9-10 - Excellent</div>
                    </div>
                    <button class="btn-secondary btn-small" data-action="edit" data-id="2" data-modal="edit-scale-modal"><i class="fas fa-edit"></i> Edit</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Eval Framework Enhanced Styles */
.eval-page-header {
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
    gap: 20px;
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

/* Content Card Enhanced */
.content-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 24px;
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

.card-header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

.btn-sm {
    font-size: 13px;
    padding: 8px 14px;
}

.btn-scale, .btn-scale-sm {
    background: rgba(251, 191, 36, 0.15) !important;
    border-color: rgba(251, 191, 36, 0.3) !important;
    color: #fbbf24 !important;
}

.btn-scale:hover, .btn-scale-sm:hover {
    background: #fbbf24 !important;
    border-color: #fbbf24 !important;
    color: #000 !important;
}

.skill-checkbox-item:hover {
    background: rgba(107, 70, 193, 0.1);
}

.content-card .card-body {
    padding: 24px;
}

.framework-tree {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.framework-category {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.framework-category:hover {
    border-color: var(--primary);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

.category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    background: linear-gradient(135deg, rgba(107, 70, 193, 0.1) 0%, transparent 100%);
    border-bottom: 1px solid var(--border);
}

.category-title {
    display: flex;
    align-items: center;
    gap: 14px;
}

.category-title i {
    font-size: 22px;
    color: var(--primary-light);
}

.category-title h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.criteria-count {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--text-muted);
    padding: 6px 14px;
    background: var(--bg-card);
    border-radius: 20px;
    border: 1px solid var(--border);
}

.category-actions {
    display: flex;
    gap: 8px;
}

.category-actions .btn-icon {
    width: 36px;
    height: 36px;
    padding: 0;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: #9ca3af;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.category-actions .btn-icon i {
    font-size: 14px;
    color: inherit;
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

.criteria-list {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.criteria-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 18px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 10px;
    transition: all 0.3s;
    position: relative;
}

.criteria-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--primary);
    opacity: 0;
    border-radius: 10px 0 0 10px;
    transition: opacity 0.3s ease;
}

.criteria-item:hover {
    border-color: var(--primary);
    transform: translateX(4px);
}

.criteria-item:hover::before {
    opacity: 1;
}

.criteria-handle {
    color: var(--text-muted);
    cursor: grab;
    padding: 4px;
}

.criteria-handle:active {
    cursor: grabbing;
}

.criteria-details {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.criteria-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
}

.criteria-weight {
    font-size: 12px;
    color: var(--text-muted);
    padding: 4px 10px;
    background: var(--bg-main);
    border-radius: 6px;
}

.criteria-actions {
    display: flex;
    gap: 6px;
}

.criteria-actions .btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 6px;
    color: #9ca3af;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.criteria-actions .btn-icon i {
    font-size: 13px;
    color: inherit;
}

.criteria-actions .btn-icon:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

.criteria-actions .btn-icon[title="Delete"]:hover {
    background: var(--error);
    border-color: var(--error);
}

/* Scales Grid */
.scales-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.scale-card {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    transition: all 0.3s ease;
}

.scale-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

.scale-card h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.scale-card h4::before {
    content: '';
    width: 4px;
    height: 20px;
    background: var(--primary);
    border-radius: 2px;
}

.scale-levels {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 20px;
}

.scale-level {
    font-size: 13px;
    color: var(--text-secondary);
    padding: 12px 16px;
    background: var(--bg-card);
    border-radius: 8px;
    border-left: 3px solid var(--primary);
}

/* Drag and Drop Styles */
.sortable-ghost {
    opacity: 0.4;
    background: rgba(107, 70, 193, 0.1);
}

.sortable-drag {
    opacity: 0.8;
    cursor: grabbing !important;
}

.criteria-item.sortable-chosen {
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.3);
    transform: scale(1.02);
}

.framework-category.sortable-chosen {
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.3);
    transform: scale(1.01);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}

.empty-state i {
    display: block;
    font-size: 56px;
    margin: 0 auto 20px;
    color: var(--border);
}

.empty-state p {
    margin: 0;
    font-size: 14px;
}

.empty-criteria {
    text-align: center;
    padding: 24px;
}

.empty-criteria p {
    color: var(--text-muted);
    font-size: 13px;
}

@media (max-width: 768px) {
    .eval-page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .page-header-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .page-header-stats {
        width: 100%;
        justify-content: space-between;
    }
    
    .category-header {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }
    
    .category-actions {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>

<!-- Add Evaluation Category Modal -->
<div id="add-eval-category-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Evaluation Category</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('add-eval-category-modal')">&times;</button>
        </div>
        <form method="POST" action="process_eval_framework.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_category">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Category Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="e.g., Skating Skills">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3" placeholder="Describe this evaluation category..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Select Skills from Library</label>
                    <p class="form-help-text" style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">
                        <i class="fas fa-info-circle"></i> Select existing skills from the Skills Library to include in this category. 
                        <a href="?page=categories&tab=skills" style="color: var(--primary-light);">Manage Skills Library →</a>
                    </p>
                    <div class="skills-checkbox-list" style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; padding: 12px;">
                        <?php if (empty($allSkillsLibrary)): ?>
                            <p style="color: var(--text-dim); font-size: 13px; text-align: center; padding: 10px;">
                                No skills in library. <a href="?page=categories&tab=skills" style="color: var(--primary-light);">Add skills first →</a>
                            </p>
                        <?php else: ?>
                            <?php foreach ($allSkillsLibrary as $skill): ?>
                                <label class="skill-checkbox-item" style="display: flex; align-items: center; gap: 10px; padding: 8px; border-radius: 6px; cursor: pointer; transition: background 0.2s;">
                                    <input type="checkbox" name="skill_ids[]" value="<?php echo $skill['id']; ?>" style="width: 18px; height: 18px; accent-color: var(--primary);">
                                    <span>
                                        <strong><?php echo htmlspecialchars($skill['name']); ?></strong>
                                        <?php if ($skill['current_category']): ?>
                                            <small style="color: var(--text-dim);"> (in <?php echo htmlspecialchars($skill['current_category']); ?>)</small>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Weight (%)</label>
                    <input type="number" name="weight" class="form-input" min="0" max="100" step="1" placeholder="Optional weight for scoring">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Icon (Font Awesome class)</label>
                    <input type="text" name="icon" class="form-input" placeholder="e.g., fa-star">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('add-eval-category-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Scale Modal -->
<div id="add-scale-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Evaluation Scale</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('add-scale-modal')">&times;</button>
        </div>
        <form method="POST" action="process_eval_framework.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_scale">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Scale Name *</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="2"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Min Value *</label>
                        <input type="number" name="min_value" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Max Value *</label>
                        <input type="number" name="max_value" class="form-input" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Step</label>
                    <input type="number" name="step" class="form-input" step="0.1" value="1">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('add-scale-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Scale</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Scale Modal -->
<div id="edit-scale-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Edit Scale</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('edit-scale-modal')">&times;</button>
        </div>
        <form method="POST" action="process_eval_framework.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="edit_scale">
            <input type="hidden" name="scale_id" id="edit-scale-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Scale Name *</label>
                    <input type="text" name="name" id="edit-scale-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-scale-description" class="form-textarea" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Min Value *</label>
                    <input type="number" name="min_value" id="edit-scale-min" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Max Value *</label>
                    <input type="number" name="max_value" id="edit-scale-max" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Scale Levels (JSON format)</label>
                    <textarea name="scale_data" id="edit-scale-data" class="form-textarea" rows="8" placeholder='{"1": "Needs Improvement", "2": "Below Average", ...}'></textarea>
                    <small>Enter score levels in JSON format</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('edit-scale-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update Scale</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Skill Modal -->
<div id="add-skill-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Skill/Criteria to Category</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('add-skill-modal')">&times;</button>
        </div>
        <form method="POST" action="process_eval_framework.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="add_skill_to_category">
            <input type="hidden" name="category_id" id="add-skill-category-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Adding to Category:</label>
                    <input type="text" id="add-skill-category-name" class="form-input" readonly style="opacity: 0.7;">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Select from Skills Library *</label>
                    <select name="skill_id" class="form-select" id="add-skill-select">
                        <option value="">-- Select a Skill --</option>
                        <?php foreach ($allSkillsLibrary as $skill): ?>
                            <option value="<?php echo $skill['id']; ?>">
                                <?php echo htmlspecialchars($skill['name']); ?>
                                <?php if ($skill['current_category']): ?>
                                    (in <?php echo htmlspecialchars($skill['current_category']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-help-text" style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">
                        <i class="fas fa-info-circle"></i> Skills are managed in the <a href="?page=categories&tab=skills" style="color: var(--primary-light);">Skills Library</a>
                    </p>
                </div>
                
                <div class="divider-text" style="text-align: center; margin: 20px 0; color: var(--text-muted);">
                    <span style="background: var(--bg-card); padding: 0 10px;">or create new</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label">New Skill Name</label>
                    <input type="text" name="new_skill_name" class="form-input" placeholder="e.g., Skating Speed">
                </div>
                
                <div class="form-group">
                    <label class="form-label">New Skill Description</label>
                    <textarea name="new_skill_description" class="form-textarea" rows="2" placeholder="Describe what this skill measures..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('add-skill-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-plus"></i> Add to Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="edit-category-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Edit Category</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('edit-category-modal')">&times;</button>
        </div>
        <form method="POST" action="process_eval_framework.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update_category">
            <input type="hidden" name="category_id" id="edit-category-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Category Name *</label>
                    <input type="text" name="name" id="edit-category-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-category-description" class="form-textarea" rows="3"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('edit-category-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Skill Modal -->
<div id="edit-skill-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Edit Skill</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('edit-skill-modal')">&times;</button>
        </div>
        <form method="POST" action="process_eval_framework.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update_skill">
            <input type="hidden" name="skill_id" id="edit-skill-id">
            <input type="hidden" name="category_id" id="edit-skill-category-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Skill Name *</label>
                    <input type="text" name="name" id="edit-skill-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description *</label>
                    <textarea name="description" id="edit-skill-description" class="form-textarea" rows="3" required></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('edit-skill-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update Skill</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Scale Modal -->
<div id="assign-scale-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Assign Scale</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('assign-scale-modal')">&times;</button>
        </div>
        <form method="POST" action="process_eval_framework.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="assign_scale">
            <input type="hidden" name="target_type" id="assign-scale-target-type">
            <input type="hidden" name="target_id" id="assign-scale-target-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Assigning Scale To:</label>
                    <input type="text" id="assign-scale-target-name" class="form-input" readonly style="opacity: 0.7;">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Select Scale *</label>
                    <select name="scale_id" class="form-select" required>
                        <option value="">-- Choose a Scale --</option>
                        <option value="1">1-5 Scale (Default)</option>
                        <option value="2">10 Point Scale</option>
                    </select>
                    <p class="form-help-text" style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">
                        <i class="fas fa-info-circle"></i> This scale will be used when evaluating this skill.
                    </p>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('assign-scale-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Assign Scale</button>
            </div>
        </form>
    </div>
</div>

<!-- Include SortableJS library for drag-and-drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js" 
        integrity="sha256-ipiJrswvAR4VAx/th+6zWsdeYmVae0iJuiR+6OqHJHQ=" 
        crossorigin="anonymous"></script>

<!-- Include Evaluation Framework JavaScript -->
<script src="js/eval_framework.js"></script>

<script>
// Store categories and skills data for client-side lookup
var categoriesData = <?php echo json_encode(array_values($categories), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var skillsData = <?php 
    $allSkills = [];
    foreach ($skillsByCategory as $catId => $skills) {
        foreach ($skills as $skill) {
            $skill['category_id'] = $catId;
            $allSkills[] = $skill;
        }
    }
    echo json_encode($allSkills, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>;

document.addEventListener('DOMContentLoaded', function() {
    var csrfToken = document.querySelector('[name="csrf_token"]')?.value || '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES) ?>';
    
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
        div.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message + '<button onclick="this.parentElement.remove()" style="margin-left: 16px; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;">&times;</button>';
        document.body.appendChild(div);
        setTimeout(function() { if (div.parentElement) div.remove(); }, 5000);
    }
    
    // AJAX helper for delete operations
    function ajaxPost(action, data, onSuccess) {
        var formData = new FormData();
        formData.append('action', action);
        formData.append('csrf_token', csrfToken);
        for (var key in data) {
            formData.append(key, data[key]);
        }
        
        fetch('process_eval_framework.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification(data.message || 'Operation successful!', 'success');
                if (onSuccess) onSuccess(data);
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showNotification('Error: ' + (data.message || 'Operation failed'), 'error');
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            showNotification('An error occurred. Please try again.', 'error');
        });
    }
    
    // Handle add buttons for modals
    document.querySelectorAll('[data-action="add"][data-modal]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var modalId = this.getAttribute('data-modal');
            var modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
            }
        });
    });
    
    // Handle add-skill buttons
    document.querySelectorAll('[data-action="add-skill"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var categoryId = this.getAttribute('data-category-id');
            var category = categoriesData.find(function(c) { return c.id == categoryId; });
            
            document.getElementById('add-skill-category-id').value = categoryId;
            document.getElementById('add-skill-category-name').value = category ? category.name : 'Unknown Category';
            
            var modal = document.getElementById('add-skill-modal');
            if (modal) modal.classList.add('active');
        });
    });
    
    // Handle edit-category buttons
    document.querySelectorAll('[data-action="edit-category"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var categoryId = this.getAttribute('data-category-id');
            var category = categoriesData.find(function(c) { return c.id == categoryId; });
            
            if (category) {
                document.getElementById('edit-category-id').value = category.id;
                document.getElementById('edit-category-name').value = category.name;
                document.getElementById('edit-category-description').value = category.description || '';
            }
            
            var modal = document.getElementById('edit-category-modal');
            if (modal) modal.classList.add('active');
        });
    });
    
    // Handle delete-category buttons
    document.querySelectorAll('[data-action="delete-category"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var categoryId = this.getAttribute('data-category-id');
            var category = categoriesData.find(function(c) { return c.id == categoryId; });
            var name = category ? category.name : 'this category';
            
            if (confirm('Are you sure you want to delete "' + name + '"? This cannot be undone.')) {
                ajaxPost('delete_category', { category_id: categoryId });
            }
        });
    });
    
    // Handle edit-skill buttons
    document.querySelectorAll('[data-action="edit-skill"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var skillId = this.getAttribute('data-skill-id');
            var skill = skillsData.find(function(s) { return s.id == skillId; });
            
            if (skill) {
                document.getElementById('edit-skill-id').value = skill.id;
                document.getElementById('edit-skill-category-id').value = skill.category_id;
                document.getElementById('edit-skill-name').value = skill.name;
                document.getElementById('edit-skill-description').value = skill.description || '';
            }
            
            var modal = document.getElementById('edit-skill-modal');
            if (modal) modal.classList.add('active');
        });
    });
    
    // Handle delete-skill buttons
    document.querySelectorAll('[data-action="delete-skill"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var skillId = this.getAttribute('data-skill-id');
            var skill = skillsData.find(function(s) { return s.id == skillId; });
            var name = skill ? skill.name : 'this skill';
            
            if (confirm('Are you sure you want to delete "' + name + '"? This cannot be undone.')) {
                ajaxPost('delete_skill', { skill_id: skillId });
            }
        });
    });
    
    // Handle assign-scale buttons (for categories)
    document.querySelectorAll('[data-action="assign-scale"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var categoryId = this.getAttribute('data-category-id');
            var category = categoriesData.find(function(c) { return c.id == categoryId; });
            
            document.getElementById('assign-scale-target-type').value = 'category';
            document.getElementById('assign-scale-target-id').value = categoryId;
            document.getElementById('assign-scale-target-name').value = 'Category: ' + (category ? category.name : 'Unknown');
            
            var modal = document.getElementById('assign-scale-modal');
            if (modal) modal.classList.add('active');
        });
    });
    
    // Handle set-skill-scale buttons (for individual skills)
    document.querySelectorAll('[data-action="set-skill-scale"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var skillId = this.getAttribute('data-skill-id');
            var skill = skillsData.find(function(s) { return s.id == skillId; });
            
            document.getElementById('assign-scale-target-type').value = 'skill';
            document.getElementById('assign-scale-target-id').value = skillId;
            document.getElementById('assign-scale-target-name').value = 'Skill: ' + (skill ? skill.name : 'Unknown');
            
            var modal = document.getElementById('assign-scale-modal');
            if (modal) modal.classList.add('active');
        });
    });
    
    // Convert forms to AJAX submissions with success widget
    document.querySelectorAll('.modal form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(form);
            var modal = form.closest('.modal');
            var submitBtn = form.querySelector('button[type="submit"]');
            var originalBtnText = submitBtn ? submitBtn.innerHTML : '';
            // Use getAttribute to get the action URL (not the shadowed property)
            var actionUrl = form.getAttribute('action') || 'process_eval_framework.php';
            
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
            }
            
            fetch(actionUrl, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
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
                showNotification('An error occurred. Please try again.', 'error');
            });
        });
    });
});

function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        var form = modal.querySelector('form');
        if (form) form.reset();
    }
}
</script>
