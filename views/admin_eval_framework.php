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
            <button class="btn-primary"><i class="fas fa-plus"></i> Add Evaluation Category</button>
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
            <button class="btn-primary"><i class="fas fa-plus"></i> Add Scale</button>
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
                    <button class="btn-secondary btn-small"><i class="fas fa-edit"></i> Edit</button>
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
                    <button class="btn-secondary btn-small"><i class="fas fa-edit"></i> Edit</button>
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
    padding: 15px;
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
    margin-bottom: 15px;
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
