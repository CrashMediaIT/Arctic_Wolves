<!-- Create Drill View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-plus-circle"></i> Create New Drill
    </h1>
    <p class="page-description">Design a custom drill with the interactive tool</p>
</div>

<div class="create-drill-content">
    <div class="create-drill-layout">
        <!-- Drill Form -->
        <div class="drill-form-section">
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Drill Information</h3>
                </div>
                <div class="card-body">
                    <form class="drill-form">
                        <div class="form-group">
                            <label>Drill Name *</label>
                            <input type="text" class="form-input" placeholder="Enter drill name" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Category *</label>
                                <select class="form-input" required>
                                    <option value="">-- Select Category --</option>
                                    <option>Skating</option>
                                    <option>Shooting</option>
                                    <option>Passing</option>
                                    <option>Stickhandling</option>
                                    <option>Defensive</option>
                                    <option>Offensive</option>
                                    <option>Conditioning</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Skill Level *</label>
                                <select class="form-input" required>
                                    <option value="">-- Select Level --</option>
                                    <option>Beginner</option>
                                    <option>Intermediate</option>
                                    <option>Advanced</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Duration (minutes)</label>
                                <input type="number" class="form-input" placeholder="10" min="1">
                            </div>
                            <div class="form-group">
                                <label>Number of Players</label>
                                <input type="text" class="form-input" placeholder="e.g., 6-18">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Description *</label>
                            <textarea class="form-textarea" rows="4" placeholder="Describe the drill objectives and key points..." required></textarea>
                        </div>

                        <div class="form-group">
                            <label>Instructions</label>
                            <textarea class="form-textarea" rows="6" placeholder="Step-by-step instructions for executing the drill..."></textarea>
                        </div>

                        <div class="form-group">
                            <label>Equipment Needed</label>
                            <div class="equipment-tags">
                                <label class="checkbox-tag">
                                    <input type="checkbox" value="pucks">
                                    <span><i class="fas fa-hockey-puck"></i> Pucks</span>
                                </label>
                                <label class="checkbox-tag">
                                    <input type="checkbox" value="cones">
                                    <span><i class="fas fa-traffic-cone"></i> Cones</span>
                                </label>
                                <label class="checkbox-tag">
                                    <input type="checkbox" value="nets">
                                    <span><i class="fas fa-bullseye"></i> Nets</span>
                                </label>
                                <label class="checkbox-tag">
                                    <input type="checkbox" value="sticks">
                                    <span><i class="fas fa-hockey-sticks"></i> Extra Sticks</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Tags (comma separated)</label>
                            <input type="text" class="form-input" placeholder="e.g., warmup, power play, breakout">
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Interactive Drill Designer -->
        <div class="drill-designer-section">
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-drafting-compass"></i> Drill Diagram</h3>
                    <div class="designer-tools">
                        <button class="tool-btn active" title="Select"><i class="fas fa-mouse-pointer"></i></button>
                        <button class="tool-btn" title="Add Player"><i class="fas fa-user"></i></button>
                        <button class="tool-btn" title="Add Cone"><i class="fas fa-triangle"></i></button>
                        <button class="tool-btn" title="Draw Line"><i class="fas fa-pencil-alt"></i></button>
                        <button class="tool-btn" title="Add Arrow"><i class="fas fa-long-arrow-alt-right"></i></button>
                        <button class="tool-btn" title="Clear All"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="ice-rink-canvas">
                        <div class="rink-overlay">
                            <p><i class="fas fa-info-circle"></i> Click the tools above to start designing your drill</p>
                        </div>
                    </div>
                    <div class="canvas-controls">
                        <button class="btn-secondary"><i class="fas fa-undo"></i> Undo</button>
                        <button class="btn-secondary"><i class="fas fa-redo"></i> Redo</button>
                        <button class="btn-secondary"><i class="fas fa-download"></i> Export Image</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="form-actions-bar">
        <button class="btn-secondary"><i class="fas fa-times"></i> Cancel</button>
        <div class="action-group">
            <button class="btn-secondary"><i class="fas fa-save"></i> Save Draft</button>
            <button class="btn-primary"><i class="fas fa-check"></i> Create Drill</button>
        </div>
    </div>
</div>

<style>
.create-drill-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-bottom: 25px;
}

@media (max-width: 1200px) {
    .create-drill-layout {
        grid-template-columns: 1fr;
    }
}

.drill-form-section,
.drill-designer-section {
    min-width: 0;
}

.designer-tools {
    display: flex;
    gap: 5px;
}

.tool-btn {
    width: 40px;
    height: 40px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    color: var(--text-dim);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
}

.tool-btn:hover,
.tool-btn.active {
    background: var(--neon);
    border-color: var(--neon);
    color: #fff;
}

.ice-rink-canvas {
    width: 100%;
    aspect-ratio: 200 / 85;
    background: 
        linear-gradient(to right, var(--border) 1px, transparent 1px),
        linear-gradient(to bottom, var(--border) 1px, transparent 1px),
        linear-gradient(135deg, #e8f4f8 0%, #f0f9ff 100%);
    background-size: 20px 20px, 20px 20px, 100% 100%;
    border: 2px solid var(--border);
    border-radius: 8px;
    position: relative;
    margin-bottom: 15px;
}

.rink-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(6, 8, 11, 0.7);
    backdrop-filter: blur(2px);
}

.rink-overlay p {
    color: var(--text-white);
    font-size: 14px;
    text-align: center;
}

.rink-overlay i {
    color: var(--neon);
    margin-right: 8px;
}

.canvas-controls {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.equipment-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.checkbox-tag {
    display: inline-flex;
    align-items: center;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 10px 15px;
    cursor: pointer;
    transition: all 0.3s;
}

.checkbox-tag:hover {
    border-color: var(--neon);
}

.checkbox-tag input {
    display: none;
}

.checkbox-tag input:checked + span {
    color: var(--neon);
}

.checkbox-tag span {
    font-size: 14px;
    color: var(--text-dim);
    transition: all 0.3s;
}

.checkbox-tag i {
    margin-right: 8px;
}

.form-actions-bar {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.action-group {
    display: flex;
    gap: 10px;
}
</style>
