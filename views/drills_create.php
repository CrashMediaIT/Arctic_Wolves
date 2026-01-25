<!-- Create Drill View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-plus-circle"></i> Create New Drill
    </h1>
    <p class="page-description">Design a custom drill with the interactive tool</p>
</div>

<div class="create-drill-content">
    <!-- Interactive Drill Designer - Now on top -->
    <div class="drill-designer-section">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-drafting-compass"></i> Drill Diagram</h3>
            </div>
            <div class="card-body">
                <!-- Tool Groups -->
                <div class="designer-toolbar">
                    <div class="tool-group">
                        <span class="tool-group-label">Selection</span>
                        <button class="tool-btn active" title="Select" data-tool="select"><i class="fas fa-mouse-pointer"></i></button>
                    </div>
                    <div class="tool-group">
                        <span class="tool-group-label">Players</span>
                        <button class="tool-btn" title="Forward (F)" data-tool="forward"><span class="tool-label">F</span></button>
                        <button class="tool-btn" title="Forward 1 (F1)" data-tool="f1"><span class="tool-label">F1</span></button>
                        <button class="tool-btn" title="Forward 2 (F2)" data-tool="f2"><span class="tool-label">F2</span></button>
                        <button class="tool-btn" title="Forward 3 (F3)" data-tool="f3"><span class="tool-label">F3</span></button>
                        <button class="tool-btn" title="Defense (D)" data-tool="defense"><span class="tool-label">D</span></button>
                        <button class="tool-btn" title="Defense 1 (D1)" data-tool="d1"><span class="tool-label">D1</span></button>
                        <button class="tool-btn" title="Defense 2 (D2)" data-tool="d2"><span class="tool-label">D2</span></button>
                        <button class="tool-btn" title="Coach" data-tool="coach"><i class="fas fa-user-tie"></i></button>
                        <button class="tool-btn" title="Goalie (G)" data-tool="goalie"><span class="tool-label">G</span></button>
                    </div>
                    <div class="tool-group">
                        <span class="tool-group-label">Positions</span>
                        <button class="tool-btn" title="Center (C)" data-tool="center"><span class="tool-label">C</span></button>
                        <button class="tool-btn" title="Left Wing (LW)" data-tool="lw"><span class="tool-label">LW</span></button>
                        <button class="tool-btn" title="Right Wing (RW)" data-tool="rw"><span class="tool-label">RW</span></button>
                        <button class="tool-btn" title="Left Defense (LD)" data-tool="ld"><span class="tool-label">LD</span></button>
                        <button class="tool-btn" title="Right Defense (RD)" data-tool="rd"><span class="tool-label">RD</span></button>
                    </div>
                    <div class="tool-group">
                        <span class="tool-group-label">Equipment</span>
                        <button class="tool-btn" title="Single Puck" data-tool="puck"><i class="fas fa-hockey-puck"></i></button>
                        <button class="tool-btn" title="Puck Group" data-tool="pucks"><i class="fas fa-circle"></i><i class="fas fa-circle" style="margin-left: -8px;"></i></button>
                        <button class="tool-btn" title="Cone" data-tool="cone"><i class="fas fa-play" style="transform: rotate(-90deg);"></i></button>
                        <button class="tool-btn" title="Net" data-tool="net"><i class="fas fa-border-all"></i></button>
                        <button class="tool-btn" title="Mini Net" data-tool="mininet"><i class="fas fa-th-large"></i></button>
                        <button class="tool-btn" title="Tire" data-tool="tire"><i class="fas fa-circle-notch"></i></button>
                        <button class="tool-btn" title="Stick" data-tool="stick"><i class="fas fa-slash"></i></button>
                    </div>
                    <div class="tool-group">
                        <span class="tool-group-label">Drawing</span>
                        <button class="tool-btn" title="Draw Line" data-tool="line"><i class="fas fa-minus"></i></button>
                        <button class="tool-btn" title="Draw Dashed Line" data-tool="dashed"><i class="fas fa-ellipsis-h"></i></button>
                        <button class="tool-btn" title="Arrow" data-tool="arrow"><i class="fas fa-long-arrow-alt-right"></i></button>
                        <button class="tool-btn" title="Add Text" data-tool="text"><i class="fas fa-font"></i></button>
                    </div>
                    <div class="tool-group">
                        <span class="tool-group-label">Numbers</span>
                        <button class="tool-btn" title="Number 0" data-tool="num0"><span class="tool-label">0</span></button>
                        <button class="tool-btn" title="Number 1" data-tool="num1"><span class="tool-label">1</span></button>
                        <button class="tool-btn" title="Number 2" data-tool="num2"><span class="tool-label">2</span></button>
                        <button class="tool-btn" title="Number 3" data-tool="num3"><span class="tool-label">3</span></button>
                        <button class="tool-btn" title="Number 4" data-tool="num4"><span class="tool-label">4</span></button>
                        <button class="tool-btn" title="Number 5" data-tool="num5"><span class="tool-label">5</span></button>
                        <button class="tool-btn" title="Number 6" data-tool="num6"><span class="tool-label">6</span></button>
                        <button class="tool-btn" title="Number 7" data-tool="num7"><span class="tool-label">7</span></button>
                        <button class="tool-btn" title="Number 8" data-tool="num8"><span class="tool-label">8</span></button>
                        <button class="tool-btn" title="Number 9" data-tool="num9"><span class="tool-label">9</span></button>
                    </div>
                    <div class="tool-group">
                        <span class="tool-group-label">Actions</span>
                        <button class="tool-btn danger-btn" title="Clear All" data-tool="clear"><i class="fas fa-trash"></i></button>
                        <button class="tool-btn fullscreen-btn" title="Fullscreen" data-tool="fullscreen"><i class="fas fa-expand"></i></button>
                    </div>
                </div>
                
                <!-- Ice View Selector -->
                <div class="ice-view-selector">
                    <label>Ice View:</label>
                    <select class="form-input-small" id="iceViewSelect" data-ice-view>
                        <option value="full" selected>Full Ice</option>
                        <option value="half-top">Half Ice (Top)</option>
                        <option value="half-bottom">Half Ice (Bottom)</option>
                        <option value="left-zone">Left Zone</option>
                        <option value="right-zone">Right Zone</option>
                        <option value="center">Center Ice</option>
                    </select>
                </div>
                <div class="ice-rink-canvas" id="drill-rink-container" data-ice-view="full">
                    <div class="rink-overlay">
                        <p><i class="fas fa-info-circle"></i> Click the tools above to start designing your drill</p>
                    </div>
                </div>
                <div class="canvas-controls">
                    <button class="btn-secondary" data-drill-action="undo"><i class="fas fa-undo"></i> Undo</button>
                    <button class="btn-secondary" data-drill-action="redo"><i class="fas fa-redo"></i> Redo</button>
                    <button class="btn-secondary" data-drill-action="export"><i class="fas fa-download"></i> Export Image</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Drill Form - Now below the diagram -->
    <div class="drill-form-section">
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Drill Information</h3>
            </div>
            <div class="card-body">
                <form class="drill-form" method="POST" action="process_drills.php">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="diagram_data" id="diagram_data" value="">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Drill Name *</label>
                            <input type="text" name="drill_name" class="form-input" placeholder="Enter drill name" required>
                        </div>

                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category" class="form-input" required>
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
                            <select name="skill_level" class="form-input" required>
                                <option value="">-- Select Level --</option>
                                <option>Beginner</option>
                                <option>Intermediate</option>
                                <option>Advanced</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Duration (minutes)</label>
                            <input type="number" name="duration" class="form-input" placeholder="10" min="1">
                        </div>

                        <div class="form-group">
                            <label>Number of Players</label>
                            <input type="text" name="num_players" class="form-input" placeholder="e.g., 6-18">
                        </div>

                        <div class="form-group">
                            <label>Tags (comma separated)</label>
                            <input type="text" name="tags" class="form-input" placeholder="e.g., warmup, power play, breakout">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" class="form-textarea" rows="3" placeholder="Describe the drill objectives and key points..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label>Instructions</label>
                        <textarea name="instructions" class="form-textarea" rows="4" placeholder="Step-by-step instructions for executing the drill..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Equipment Needed</label>
                        <div class="equipment-tags">
                            <label class="checkbox-tag">
                                <input type="checkbox" name="equipment[]" value="pucks">
                                <span><i class="fas fa-hockey-puck"></i> Pucks</span>
                            </label>
                            <label class="checkbox-tag">
                                <input type="checkbox" name="equipment[]" value="cones">
                                <span><i class="fas fa-traffic-cone"></i> Cones</span>
                            </label>
                            <label class="checkbox-tag">
                                <input type="checkbox" name="equipment[]" value="nets">
                                <span><i class="fas fa-bullseye"></i> Nets</span>
                            </label>
                            <label class="checkbox-tag">
                                <input type="checkbox" name="equipment[]" value="sticks">
                                <span><i class="fas fa-hockey-stick"></i> Extra Sticks</span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="form-actions-bar">
        <a href="?page=drill_library" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
        <div class="action-group">
            <button type="button" class="btn btn-secondary" onclick="saveDrillDraft()"><i class="fas fa-save"></i> Save Draft</button>
            <button type="button" class="btn btn-primary" onclick="submitDrillForm()"><i class="fas fa-check"></i> Create Drill</button>
        </div>
    </div>
</div>

<style>
/* New stacked layout - diagram on top, form below */
.drill-designer-section {
    margin-bottom: 24px;
}

.drill-form-section {
    min-width: 0;
}

/* Form grid for better organization */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}

/* Designer Toolbar Styles */
.designer-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    padding: 16px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 16px;
}

.tool-group {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    align-items: center;
    padding: 8px 12px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 6px;
}

.tool-group-label {
    font-size: 10px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    width: 100%;
    margin-bottom: 6px;
}

.tool-btn {
    width: 36px;
    height: 36px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    color: var(--text-white);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.tool-btn:hover {
    background: rgba(107, 70, 193, 0.2);
    border-color: var(--primary);
}

.tool-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

.tool-btn .tool-label {
    font-size: 12px;
    font-weight: 700;
}

.tool-btn.danger-btn:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: #ef4444;
    color: #ef4444;
}

.tool-btn i {
    font-size: 14px;
}

.fullscreen-btn {
    margin-left: auto;
}

/* Ice View Selector */
.ice-view-selector {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    padding: 12px 16px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
}

.ice-view-selector label {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-white);
    margin: 0;
}

.ice-view-selector .form-input-small {
    min-width: 160px;
}

.ice-rink-canvas {
    width: 100%;
    min-height: 450px;
    max-height: 600px;
    aspect-ratio: 2/1;
    background: linear-gradient(135deg, #f0f7fa 0%, #e8f4f8 100%);
    border: 3px solid #0033a0;
    border-radius: 80px;
    position: relative;
    margin-bottom: 16px;
    overflow: hidden;
}

/* Canvas element inside takes over rendering */
.ice-rink-canvas canvas {
    border-radius: 77px;
}

/* Fullscreen mode */
.ice-rink-canvas.fullscreen {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100vw;
    height: 100vh;
    max-height: 100vh;
    min-height: 100vh;
    z-index: 9999;
    border-radius: 0;
    margin: 0;
    aspect-ratio: auto;
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
    z-index: 10;
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

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .designer-tools {
        justify-content: center;
    }
    
    .form-actions-bar {
        flex-direction: column;
    }
    
    .action-group {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
// Notification helper function
function showNotification(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'notification-toast';
    alertDiv.innerHTML = '<i class="fas fa-' + (type === 'error' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : 'info-circle') + '"></i> ' + message;
    alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; min-width: 300px; padding: 15px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; animation: slideIn 0.3s ease;';
    
    if (type === 'error') {
        alertDiv.style.background = 'rgba(239, 68, 68, 0.9)';
        alertDiv.style.color = '#fff';
    } else if (type === 'success') {
        alertDiv.style.background = 'rgba(16, 185, 129, 0.9)';
        alertDiv.style.color = '#fff';
    } else {
        alertDiv.style.background = 'rgba(59, 130, 246, 0.9)';
        alertDiv.style.color = '#fff';
    }
    
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 4000);
}

// Drill form submission handler
function submitDrillForm() {
    // Get diagram data if drill designer is available
    if (window.drillDesigner) {
        document.getElementById('diagram_data').value = window.drillDesigner.getDiagramData();
    }
    document.querySelector('.drill-form').submit();
}

// Save draft functionality
function saveDrillDraft() {
    const form = document.querySelector('.drill-form');
    const formData = new FormData(form);
    
    // Save to localStorage
    const draftData = {};
    for (let [key, value] of formData.entries()) {
        draftData[key] = value;
    }
    
    // Add diagram data
    if (window.drillDesigner) {
        draftData.diagram_data = window.drillDesigner.getDiagramData();
    }
    
    localStorage.setItem('drill_draft', JSON.stringify(draftData));
    showNotification('Draft saved! Your progress has been saved locally.', 'success');
}

// Load draft on page load
document.addEventListener('DOMContentLoaded', function() {
    const draft = localStorage.getItem('drill_draft');
    if (draft) {
        const loadDraft = confirm('You have a saved draft. Would you like to load it?');
        if (loadDraft) {
            const draftData = JSON.parse(draft);
            Object.keys(draftData).forEach(key => {
                const input = document.querySelector(`[name="${key}"]`);
                if (input) {
                    if (input.type === 'checkbox') {
                        input.checked = draftData[key] === input.value;
                    } else {
                        input.value = draftData[key];
                    }
                }
            });
            
            // Load diagram data
            if (draftData.diagram_data && window.drillDesigner) {
                window.drillDesigner.loadDiagramData(draftData.diagram_data);
            }
        }
    }
});
</script>

<!-- Load Drill Designer JavaScript -->
<script src="js/drill_designer.js"></script>
