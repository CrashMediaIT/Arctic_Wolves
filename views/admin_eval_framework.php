<!-- Admin Evaluation Framework View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-clipboard-check"></i> Evaluation Framework
    </h1>
    <p class="page-description">Build and manage athlete evaluation criteria</p>
</div>

<div class="eval-framework-content">
    <!-- Framework Builder -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-tools"></i> Framework Builder</h3>
            <button class="btn-primary" data-action="add" data-modal="add-eval-category-modal"><i class="fas fa-plus"></i> Add Evaluation Category</button>
        </div>
        <div class="card-body">
            <div class="framework-tree">
                <!-- Skating Category -->
                <div class="framework-category">
                    <div class="category-header">
                        <div class="category-title">
                            <i class="fas fa-skating"></i>
                            <h4>Skating</h4>
                            <span class="criteria-count">8 criteria</span>
                        </div>
                        <div class="category-actions">
                            <button class="btn-icon" title="Add Criteria"><i class="fas fa-plus"></i></button>
                            <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="criteria-list">
                        <div class="criteria-item">
                            <div class="criteria-handle"><i class="fas fa-grip-vertical"></i></div>
                            <div class="criteria-details">
                                <span class="criteria-name">Forward Stride</span>
                                <span class="criteria-weight">Weight: 15%</span>
                            </div>
                            <div class="criteria-actions">
                                <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <div class="criteria-item">
                            <div class="criteria-handle"><i class="fas fa-grip-vertical"></i></div>
                            <div class="criteria-details">
                                <span class="criteria-name">Crossovers</span>
                                <span class="criteria-weight">Weight: 12%</span>
                            </div>
                            <div class="criteria-actions">
                                <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <div class="criteria-item">
                            <div class="criteria-handle"><i class="fas fa-grip-vertical"></i></div>
                            <div class="criteria-details">
                                <span class="criteria-name">Edge Work</span>
                                <span class="criteria-weight">Weight: 10%</span>
                            </div>
                            <div class="criteria-actions">
                                <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shooting Category -->
                <div class="framework-category">
                    <div class="category-header">
                        <div class="category-title">
                            <i class="fas fa-hockey-puck"></i>
                            <h4>Shooting</h4>
                            <span class="criteria-count">5 criteria</span>
                        </div>
                        <div class="category-actions">
                            <button class="btn-icon" title="Add Criteria"><i class="fas fa-plus"></i></button>
                            <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="criteria-list">
                        <div class="criteria-item">
                            <div class="criteria-handle"><i class="fas fa-grip-vertical"></i></div>
                            <div class="criteria-details">
                                <span class="criteria-name">Wrist Shot Accuracy</span>
                                <span class="criteria-weight">Weight: 20%</span>
                            </div>
                            <div class="criteria-actions">
                                <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <div class="criteria-item">
                            <div class="criteria-handle"><i class="fas fa-grip-vertical"></i></div>
                            <div class="criteria-details">
                                <span class="criteria-name">Shot Release Speed</span>
                                <span class="criteria-weight">Weight: 15%</span>
                            </div>
                            <div class="criteria-actions">
                                <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
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
.framework-tree {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.framework-category {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
}

.category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: rgba(255, 77, 0, 0.05);
    border-bottom: 1px solid var(--border);
}

.category-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.category-title i {
    font-size: 20px;
    color: var(--neon);
}

.category-title h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
}

.criteria-count {
    font-size: 12px;
    color: var(--text-dim);
    padding: 4px 10px;
    background: var(--bg-card);
    border-radius: 4px;
}

.criteria-list {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.criteria-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px 15px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 6px;
    transition: all 0.3s;
}

.criteria-item:hover {
    border-color: var(--neon);
}

.criteria-handle {
    color: var(--text-dim);
    cursor: grab;
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
    font-size: 14px;
    font-weight: 700;
    color: var(--text-white);
}

.criteria-weight {
    font-size: 12px;
    color: var(--text-dim);
    padding: 4px 10px;
    background: var(--bg-main);
    border-radius: 4px;
}

.scales-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.scale-card {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
}

.scale-card h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 12px;
}

.scale-levels {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 20px;
}

.scale-level {
    font-size: 13px;
    color: var(--text-dim);
    padding: 8px 12px;
    background: var(--bg-card);
    border-radius: 4px;
}
</style>

<!-- Add Evaluation Category Modal -->
<div id="add-eval-category-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Evaluation Category</h2>
            <button class="modal-close" onclick="closeModal('add-eval-category-modal')">&times;</button>
        </div>
        <form method="POST" action="process_eval_framework.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_category">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Category Name *</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Weight (%)</label>
                    <input type="number" name="weight" class="form-input" min="0" max="100" step="1">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Icon (Font Awesome class)</label>
                    <input type="text" name="icon" class="form-input" placeholder="e.g., fa-star">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('add-eval-category-modal')">Cancel</button>
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
            <button class="modal-close" onclick="closeModal('add-scale-modal')">&times;</button>
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
                <button type="button" class="btn-secondary" onclick="closeModal('add-scale-modal')">Cancel</button>
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
            <button class="modal-close" onclick="closeModal('edit-scale-modal')">&times;</button>
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
                    <label class="form-label">Minimum Score *</label>
                    <input type="number" name="min_score" id="edit-scale-min" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Maximum Score *</label>
                    <input type="number" name="max_score" id="edit-scale-max" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Scale Levels (JSON format)</label>
                    <textarea name="scale_data" id="edit-scale-data" class="form-textarea" rows="8" placeholder='{"1": "Needs Improvement", "2": "Below Average", ...}'></textarea>
                    <small>Enter score levels in JSON format</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('edit-scale-modal')">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update Scale</button>
            </div>
        </form>
    </div>
</div>
